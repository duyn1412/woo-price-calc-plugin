<?php
/**
 * Plugin Name: Custom Age Verifier and Tax Plugin
 * Description: Age gate + province-based pricing/visibility. Province via query param (?province=XX) to allow CDN/page cache variation by URL. Optional cookie mirror for UX.
 * Version: 4.2
 * Author: Block Agency
 * Author URI: https://blockagency.co
 */

if (!defined('ABSPATH')) { exit; }

/* --------------------------------------------------------------------------
   Activation
-------------------------------------------------------------------------- */
register_activation_hook(__FILE__, 'woo_price_calc_plugin_activate');
function woo_price_calc_plugin_activate() {
    // Ensure is_plugin_active() exists
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce to be activated', 'woo_price_calc_plugin'));
    }
    add_option('woo_price_calc_plugin_do_activation_redirect', true);
}

/* --------------------------------------------------------------------------
   Helpers
-------------------------------------------------------------------------- */

/**
 * Parse numeric options robustly (handles "19,00" and "19.00").
 */
function ba_parse_number($val, $default = 0.0) {
    if ($val === '' || $val === null) return (float) $default;
    // Normalize comma decimal to dot and strip thousands separators.
    $v = str_replace(array(' ', "\xC2\xA0"), '', (string) $val); // remove spaces including nbsp
    $v = str_replace(array(',',), '.', $v);
    return (float) $v;
}

/**
 * Debug logging helper (only logs when BA_DEBUG constant is defined)
 */
function ba_log($message, $level = 'info') {
    if (!defined('BA_DEBUG') || !BA_DEBUG) return;
    
    error_log(sprintf(
        '[BA Plugin][%s] %s',
        $level,
        is_array($message) || is_object($message) ? print_r($message, true) : $message
    ));
}

/* --------------------------------------------------------------------------
   Province helpers (query-string first)
-------------------------------------------------------------------------- */
function ba_get_current_province() {
    // Add simple caching for the request
    static $cached_province = null;
    static $cached = false;
    
    if ($cached) {
        return $cached_province;
    }
    
    $val = null;

    if (isset($_GET['province']) && is_string($_GET['province'])) {
        $val = strtoupper(sanitize_text_field($_GET['province']));
        if (!ba_is_valid_province_code($val)) { 
            $cached = true;
            $cached_province = null;
            return null; 
        }
        $cached = true;
        $cached_province = $val;
        return $val;
    }
    if (isset($_COOKIE['province']) && is_string($_COOKIE['province'])) {
        $val = strtoupper(sanitize_text_field($_COOKIE['province']));
        if (!ba_is_valid_province_code($val)) { 
            $cached = true;
            $cached_province = null;
            return null; 
        }
        $cached = true;
        $cached_province = $val;
        return $val;
    }
    
    $cached = true;
    $cached_province = null;
    return null;
}

// Helper: set or delete the 'province' cookie (covers www and non-www)
function ba_set_province_cookie($province) {
    $host = parse_url(home_url('/'), PHP_URL_HOST);

    $cookie_domain = (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN)
        ? COOKIE_DOMAIN
        : ((strpos($host, 'www.') === 0) ? '.' . substr($host, 4) : '.' . $host);

    // If empty, delete cookie
    $expire = ($province === '' || $province === null) ? time() - 3600 : (time() + 60 * 86400);

    setcookie('province', $province, [
        'expires'  => $expire,
        'path'     => '/',
        'domain'   => $cookie_domain,
        'secure'   => is_ssl(),
        'httponly' => true,   // safer; no JS access needed
        'samesite' => 'Lax',
    ]);
}

// Whitelist of valid province codes
function ba_is_valid_province_code($code) {
    static $valid = array('AB','BC','MB','NB','NL','NS','ON','PE','QC','SK','NT','NU','YT');
    return in_array(strtoupper((string)$code), $valid, true);
}

// Same-origin check for logged-out POSTs
function ba_is_same_origin($ref) {
    if (!$ref) return false;
    $refHost  = wp_parse_url($ref, PHP_URL_HOST);
    $siteHost = wp_parse_url(home_url('/'), PHP_URL_HOST);
    return $refHost && $siteHost && (strcasecmp($refHost, $siteHost) === 0);
}

// Treat admin previews (draft/pending/private) as trusted: skip age gate.
function ba_is_admin_preview_context() {
    if (is_user_logged_in() && is_preview()) return true;

    // Frontend preview of a non-published post by someone who can edit it
    $p = isset($_GET['p']) ? (int) $_GET['p'] : 0;
    if ($p > 0) {
        $st = get_post_status($p);
        if ($st && $st !== 'publish' && current_user_can('edit_post', $p)) {
            return true;
        }
    }
    return false;
}

function ba_is_agegate_context() {
    // Never show the gate in admin preview contexts
    if (ba_is_admin_preview_context()) return false;

    if (!isset($_GET['province'])) return true;
    $p = strtoupper((string) $_GET['province']);
    if ($p === 'NO') return true;
    return !ba_is_valid_province_code($p);
}

// When entering with ?province=XX (or NOT) set/remove the cookie
add_action('init', function () {
    if (!isset($_GET['province'])) return;
    $p = strtoupper(sanitize_text_field($_GET['province']));
    if ($p === 'NO') {
        ba_set_province_cookie('');
    } elseif (ba_is_valid_province_code($p)) {
        ba_set_province_cookie($p);
    }
}, 1);

// Age-gate: signal no-cache using standard WP constants
add_action('init', function () {
    if (function_exists('ba_is_agegate_context') && ba_is_agegate_context()) {
        if (!defined('DONOTCACHEPAGE'))   define('DONOTCACHEPAGE', true);
        if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
        if (!defined('DONOTCACHEDB'))     define('DONOTCACHEDB', true);
    }
}, 0);

/* --- Helper: true on cart/checkout screens and their AJAX endpoints (mini-cart, fragments, totals, etc.) --- */
function ba_is_cart_or_checkout_request() {
    // True on cart / checkout screens
    if (is_cart() || is_checkout()) {
        return true;
    }

    // True for Woo/CheckoutWC AJAX endpoints used by sidecart/mini-cart
    if (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['wc-ajax'])) {
        $endpoints = array(
            // Woo core
            'get_refreshed_fragments',
            'add_to_cart',
            'remove_from_cart',
            'get_cart_totals',
            'update_order_review',
            'checkout',
            'apply_coupon',
            'remove_coupon',
            'update_shipping_method',
            // Common extras (themes/plugins)
            'update_mini_cart',
            'reload_checkout',
        );
        $endpoint = sanitize_text_field(wp_unslash($_REQUEST['wc-ajax']));
        return in_array($endpoint, $endpoints, true);
    }

    return false;
}

/* --------------------------------------------------------------------------
   Front controller: redirects to stabilize URLs for caching
-------------------------------------------------------------------------- */
add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_preview() || is_customize_preview()) return;

    // If URL already stable, do nothing (age-gate logic will decide cache headers)
    if (isset($_GET['province'])) return;

    if (!empty($_COOKIE['province'])) {
        $province = strtoupper(sanitize_text_field($_COOKIE['province']));
        if (ba_is_valid_province_code($province)) {
            // Use safer URL construction
            $current_url = home_url( add_query_arg( array(), $_SERVER['REQUEST_URI'] ) );
            $target      = add_query_arg( 'province', $province, $current_url );
            wp_safe_redirect($target, 302);
            exit;
        } else {
            // Clear bad cookie to avoid repeated invalid states
            ba_set_province_cookie('');
        }
    }
    // else: show age gate (no redirect)
});

/* --------------------------------------------------------------------------
   Cache headers: only age gate should be no-cache
-------------------------------------------------------------------------- */
add_action('send_headers', function () {
    if (ba_is_agegate_context()) {
        nocache_headers();
    }
});

// Force no-cache for Woo/CheckoutWC AJAX endpoints (fragments, totals, etc.)
add_action('init', function () {
    if (isset($_GET['wc-ajax'])) {
        if (!defined('DONOTCACHEPAGE'))   define('DONOTCACHEPAGE', true);
        if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
        if (!defined('DONOTCACHEDB'))     define('DONOTCACHEDB', true);
    }
}, 0);

add_action('send_headers', function () {
    if (isset($_GET['wc-ajax'])) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}, 0);

// Prevent caching of Woo Store API (Blocks) responses (/wp-json/wc/store/*)
add_filter('rest_post_dispatch', function ($response, $server, $request) {
    try {
        $route = $request ? $request->get_route() : '';
        if (is_string($route) && strpos($route, '/wc/store') === 0) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
        }
    } catch (\Throwable $e) { 
        ba_log('Error setting REST headers: ' . $e->getMessage(), 'error');
    }
    return $response;
}, 10, 3);

/* --------------------------------------------------------------------------
   Assets
-------------------------------------------------------------------------- */
add_action('wp_enqueue_scripts', function () {
    $version = '5.1';
    wp_enqueue_style('ba-age-verifier', plugin_dir_url(__FILE__) . 'd-styles.css', [], $version);
    wp_enqueue_script('ba-age-verifier', plugin_dir_url(__FILE__) . 'js/age-verifier.js', ['jquery'], $version, true);
    wp_enqueue_script('ba-woocommerce-address-handler', plugin_dir_url(__FILE__) . 'js/woocommerce-address-handler.js', ['jquery'], $version, true);
});

/* --------------------------------------------------------------------------
   Body class
-------------------------------------------------------------------------- */
add_filter('body_class', function ($classes) {
    if (ba_is_agegate_context()) $classes[] = 'no-province';
    
    // Also add province class for better CSS targeting
    $province = ba_get_current_province();
    if ($province) {
        $classes[] = 'province-' . strtolower($province);
    }
    
    return $classes;
});

/* --------------------------------------------------------------------------
   Age gate markup
-------------------------------------------------------------------------- */
add_action('wp_footer', function () {
    if (!ba_is_agegate_context()) return;

    $province_group2 = array(
        'AB'=>'Alberta','BC'=>'British Columbia','MB'=>'Manitoba','NB'=>'New Brunswick',
        'NL'=>'Newfoundland and Labrador','NS'=>'Nova Scotia','ON'=>'Ontario',
        'PE'=>'Prince Edward Island','QC'=>'Quebec','SK'=>'Saskatchewan',
        'NT'=>'Northwest Territories','NU'=>'Nunavut','YT'=>'Yukon Territory'
    );

    $logo_url = false;
    // Fix XSS vulnerability
    $action_url = esc_url( home_url( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ) );
    $custom_logo_id = get_theme_mod('custom_logo');
    if ($custom_logo_id) {
        $img = wp_get_attachment_image_src($custom_logo_id, 'full');
        if ($img && isset($img[0])) $logo_url = $img[0];
    }

    ob_start(); ?>
    <div id="custom-age-popup-wrapper">
      <div id="custom-age-popup-box">
        <center>
          <?php if ($logo_url): ?>
            <img class="d-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"><br>
          <?php endif; ?>
          <hr class="custom-age-saparater">
          <p>This site is intended for adults <span class="bold-txt"><span id="age-limit">19</span> years and older</span>. If you are not legally able to purchase tobacco products in your province, please do not enter this site.</p>
        </center>

        <div class="custom-age-btn-box">
          <form id="d-age-verification-form" action="<?php echo $action_url; ?>" method="get">
            <div class="custom-age-btn-box-row" id="province-box">
              <div class="province_wrapper">
                <select id="province" name="province" required>
                  <option value="" disabled selected>Select your province</option>
                  <?php foreach ($province_group2 as $code => $name): ?>
                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div id="dob-box">
              <p class="select-dob-label">Please select your birthdate to confirm you are at least 19 years of age.</p>
              <div class="custom-age-btn-box-row custom-birthdate">
                <div class="birth_month_wrapper">
                  <select id="month">
                    <?php $months=array(1=>'January','February','March','April','May','June','July','August','September','October','November','December');
                    for($i=1;$i<=12;$i++): ?>
                      <option value="<?php echo esc_attr($i); ?>" <?php selected(date('n'),$i); ?>><?php echo esc_html($months[$i]); ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="birth_date_wrapper">
                  <select id="day">
                    <?php for($i=1;$i<=31;$i++): ?>
                      <option value="<?php echo esc_attr($i); ?>" <?php selected(date('j'),$i); ?>><?php echo esc_html($i); ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="birth_year_wrapper">
                  <select id="year">
                    <?php for($i=(int)date('Y'); $i>=(int)date('Y')-100; $i--): ?>
                      <option value="<?php echo esc_attr($i); ?>" <?php selected($i,2000); ?>><?php echo esc_html($i); ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
              </div>

              <?php
              // Preserve existing query args (e.g., preview nonce) inside the form
              foreach ($_GET as $k => $v) {
                  if ($k === 'province') continue;
                  if (is_scalar($v)) {
                      echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
                  }
              }
              ?>

              <input type="submit" value="Enter" class="custom-btn-age yes">
              <a href="https://www.google.com/" class="custom-btn-age no">Exit</a>
            </div>
          </form>

          <p id="error-message" style="display:none;color:red;">Sorry! You are under age to visit website. Only 19+ age person can visit.</p>
        </div>
      </div>
    </div>
    <?php echo ob_get_clean();
});

/* --------------------------------------------------------------------------
   Woo helpers
-------------------------------------------------------------------------- */
function is_admin_simulating_customer_role() {
    if (class_exists('VAA_API') && method_exists('VAA_API','is_view_active')) {
        return (bool) VAA_API::is_view_active();
    }
    return false;
}

add_filter('woocommerce_checkout_get_value', function ($value, $input) {
    if ($input === 'billing_state' || $input === 'shipping_state') {
        $province = ba_get_current_province();
        if ($province) return $province;
    }
    return $value;
}, 10, 2);

add_action('woocommerce_checkout_process', function () {
    $province = ba_get_current_province();
    if ($province) {
        if (empty($_POST['billing_state']))  { $_POST['billing_state']  = $province; if (function_exists('WC')) WC()->customer->set_billing_state($province); }
        if (empty($_POST['shipping_state'])) { $_POST['shipping_state'] = $province; if (function_exists('WC')) WC()->customer->set_shipping_state($province); }
    }
});

/* --------------------------------------------------------------------------
   Pricing & sizes - WITH CACHING IMPROVEMENTS
-------------------------------------------------------------------------- */
function ba_get_taxable_category_ids_cached() {
    // Use transient for better performance
    $cache_key = 'ba_taxable_categories_' . get_current_blog_id();
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }

    $roots = (array) get_option('woocommerce_taxable_categories', array());
    $all = array_map('intval', $roots);

    foreach ($roots as $cat_id) {
        $children = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => (int) $cat_id,
            'fields'     => 'ids',
        ));
        if (!is_wp_error($children) && !empty($children)) {
            foreach ($children as $cid) $all[] = (int) $cid;
        }
    }
    
    $all = array_values(array_unique($all));
    set_transient($cache_key, $all, HOUR_IN_SECONDS);
    
    return $all;
}

function check_taxable_categories($product) {
    if (!$product || !is_a($product, 'WC_Product')) return false;

    static $per_product = array(); // pid => bool
    $pid = $product->is_type('variation')
        ? (int) ($product->get_parent_id() ?: $product->get_id())
        : (int) $product->get_id();

    if (isset($per_product[$pid])) return $per_product[$pid];

    $product_cats = wp_get_post_terms($pid, 'product_cat', array('fields' => 'ids'));
    $taxable_ids  = ba_get_taxable_category_ids_cached();
    $match = (bool) array_intersect($taxable_ids, array_map('intval', (array) $product_cats));

    return $per_product[$pid] = $match;
}

/**
 * Province-level rate for products in taxable categories.
 * Returns 0.0 when not applicable.
 */
function get_curent_tax_province($product) {
    static $rate_cache = array(); // 'ON' => 13.0, etc.
    $zone = ba_get_current_province();
    if (!$zone || !check_taxable_categories($product)) return 0.0;

    if (!array_key_exists($zone, $rate_cache)) {
        $val = get_option('wc_tax_rate_' . $zone);
        $rate_cache[$zone] = ba_parse_number($val, 0.0);
    }
    return $rate_cache[$zone];
}

/**
 * How to apply a given tax:
 * - If option is "percentage", add X% to the price.
 * - If option is "fixed", add a fixed X amount.
 * $type === 'size' uses wc_tax_calculation_size_type, otherwise wc_tax_calculation_type.
 */
function calculate_price_based_on_type($price, $tax, $type = null) {
    $price = (float) $price;
    $tax   = (float) $tax;
    
    // Safety check for division by zero prevention
    if ($tax <= 0 || $price <= 0) return $price;

    $opt_key = ($type === 'size') ? 'wc_tax_calculation_size_type' : 'wc_tax_calculation_type';
    $calculation_type = get_option($opt_key, 'percentage');

    if ($calculation_type === 'percentage') {
        return $price + ($price * ($tax / 100));
    }
    // fixed
    return $price + $tax;
}

/**
 * Apply per-size adjustment (60ml / 120ml) ONLY to variations that have pa_size.
 * This does not add any province-level rate; it strictly applies the size rule.
 */
function check_product_pa_size($price, $product) {
    if ($price === '' || $price === null) return $price;

    $price = (float)$price;

    $customer_zone = ba_get_current_province();
    $provinces     = get_option('woocommerce_taxable_provinces', array());
    $tax_60_ml     = ba_parse_number(get_option('woocommerce_taxable_categories_60ml', 0), 0);
    $tax_120_ml    = ba_parse_number(get_option('woocommerce_taxable_categories_120ml', 0), 0);

    if (!$customer_zone || !in_array($customer_zone, (array)$provinces, true)) return $price;
    if (!check_taxable_categories($product)) return $price;

    // Type checking improvement
    if ($product instanceof WC_Product_Variation || $product->is_type('variation')) {
        $size = $product->get_attribute('pa_size');
        if ($size === '60ml')  return calculate_price_based_on_type($price, $tax_60_ml, 'size');
        if ($size === '120ml') return calculate_price_based_on_type($price, $tax_120_ml, 'size');
    }
    return $price;
}

/* --------------------------------------------------------------------------
   Display price (catalog/single) — keep consistent with cart math
-------------------------------------------------------------------------- */
add_filter('woocommerce_get_price_html', 'd_alter_price_display', 10, 2);
function d_alter_price_display($price, $product) {
    // Never decorate prices in cart/checkout or during their AJAX cycles.
    // This prevents double-adding tax/fees in sidecarts and checkout views.
    if (ba_is_cart_or_checkout_request()) {
        return $price;
    }

    // Fast exits
    if (is_admin() || ! $product || '' === $product->get_price()) {
        return $price;
    }

    $tax = get_curent_tax_province($product);

    // If no province or the product is not taxable for our logic, bail out early.
    if ($tax <= 0 && !$product->is_type('variable')) {
        // For simple/variation products without province rate, we don't need to recompute.
        return $price;
    }

    if ($product->is_type('simple') || $product->is_type('variation')) {
        // Start from the front-end display base.
        $base_display = wc_get_price_to_display($product);

        // If this is a variation, also apply size-based rule before province-level.
        if ($product->is_type('variation')) {
            $base_display = check_product_pa_size($base_display, $product);
        }

        // Now apply province-level rule (if any).
        $final = ($tax > 0) ? calculate_price_based_on_type($base_display, $tax) : $base_display;

        // Respect sale formatting when needed.
        if ($product->is_on_sale()) {
            $reg = wc_get_price_to_display($product, array('price' => $product->get_regular_price()));
            if ($product->is_type('variation')) {
                $reg = check_product_pa_size($reg, $product);
            }
            $reg = ($tax > 0) ? calculate_price_based_on_type($reg, $tax) : $reg;

            if ((float)$reg === (float)$final) {
                return wc_price($final) . $product->get_price_suffix();
            }
            return wc_format_sale_price($reg, $final) . $product->get_price_suffix();
        }

        return wc_price($final) . $product->get_price_suffix();

    } elseif ($product->is_type('variable')) {
        // Min/max logic for variable products
        $min_regular_price = $product->get_variation_regular_price('min', true);
        $max_regular_price = $product->get_variation_regular_price('max', true);
        $min_sale_price    = $product->get_variation_sale_price('min', true);
        $max_sale_price    = $product->get_variation_sale_price('max', true);

        $has_pa_size = false;
        foreach ((array)$product->get_attributes() as $attr_name => $_) {
            if (strpos($attr_name, 'pa_size') !== false) { $has_pa_size = true; break; }
        }

        if ($has_pa_size) {
            $adjusted = get_adjusted_min_max_price($product); // applies size rule
            $min_sale_price = $adjusted['min'];
            $max_sale_price = $adjusted['max'];
        }

        // Apply province rule to whichever we will display
        $apply_tax = function($val) use ($tax) {
            return ($tax > 0) ? calculate_price_based_on_type($val, $tax) : $val;
        };

        if (!empty($min_sale_price) && $min_sale_price < $min_regular_price) {
            if ($min_sale_price !== $max_sale_price) {
                $price = wc_format_price_range(
                    wc_price($apply_tax($min_sale_price)),
                    wc_price($apply_tax($max_sale_price))
                );
            } else {
                $price = wc_price($apply_tax($min_sale_price));
            }

            if ($min_regular_price !== $max_regular_price) {
                $regular_price = wc_format_price_range(
                    wc_price($apply_tax($min_regular_price)),
                    wc_price($apply_tax($max_regular_price))
                );
            } else {
                $regular_price = wc_price($apply_tax($min_regular_price));
            }

            if (has_no_price_range($product)) {
                $price = '<del>' . $regular_price . '</del> <ins>' . $price . '</ins>';
            }
        } else {
            if ($min_regular_price !== $max_regular_price) {
                if ($has_pa_size) {
                    $price = wc_format_price_range(
                        wc_price($apply_tax($min_sale_price)),
                        wc_price($apply_tax($max_sale_price))
                    );
                } else {
                    $price = wc_format_price_range(
                        wc_price($apply_tax($min_regular_price)),
                        wc_price($apply_tax($max_regular_price))
                    );
                }
            } else {
                if ($has_pa_size) {
                    $price = wc_price($apply_tax($min_sale_price));
                } else {
                    $price = wc_price($apply_tax($min_regular_price));
                }
            }
        }
        return $price;
    }

    return $price;
}

function has_no_price_range($product) {
    if ($product && $product->is_type('variable')) {
        $variations = $product->get_available_variations();
        if (empty($variations)) return false;
        $first = $variations[0]['display_price'];
        foreach ($variations as $v) { if ($v['display_price'] !== $first) return false; }
        return true;
    }
    return false;
}

function get_adjusted_min_max_price($product) {
    $min = $product->get_variation_price('min', true);
    $max = $product->get_variation_price('max', true);
    $min = check_product_pa_size($min, $product);
    $max = check_product_pa_size($max, $product);
    return array('min'=>$min, 'max'=>$max, 'd-regular-price'=>$max);
}

/* --------------------------------------------------------------------------
   Render mini-cart item unit price from normalized math (size + province)
-------------------------------------------------------------------------- */
add_filter('woocommerce_cart_item_price', function ($price_html, $cart_item, $cart_item_key) {
    if (empty($cart_item['data']) || !($cart_item['data'] instanceof WC_Product)) {
        return $price_html;
    }

    $product  = $cart_item['data'];
    $base     = ba_get_unadjusted_base_price($product);
    if ($base <= 0) return $price_html;

    $adjusted = check_product_pa_size($base, $product);
    $tax      = get_curent_tax_province($product);
    $final    = ($tax > 0) ? calculate_price_based_on_type($adjusted, $tax) : $adjusted;

    return wc_price($final) . $product->get_price_suffix();
}, 999, 3);

/* --------------------------------------------------------------------------
   Render mini-cart item subtotal from normalized math (size + province)
-------------------------------------------------------------------------- */
add_filter('woocommerce_cart_item_subtotal', function ($subtotal_html, $cart_item, $cart_item_key) {
    if (empty($cart_item['data']) || !($cart_item['data'] instanceof WC_Product)) {
        return $subtotal_html;
    }

    $product  = $cart_item['data'];
    $qty      = isset($cart_item['quantity']) ? (int) $cart_item['quantity'] : 1;
    $base     = ba_get_unadjusted_base_price($product);
    if ($base <= 0) return $subtotal_html;

    $adjusted = check_product_pa_size($base, $product);
    $tax      = get_curent_tax_province($product);
    $final    = ($tax > 0) ? calculate_price_based_on_type($adjusted, $tax) : $adjusted;

    return wc_price($final * $qty);
}, 999, 3);

/* --------------------------------------------------------------------------
   Variation price filters (catalog context only)
-------------------------------------------------------------------------- */
add_filter('woocommerce_product_variation_get_price',         'd_adjust_variation_price', 10, 2);
add_filter('woocommerce_product_variation_get_regular_price', 'd_adjust_variation_regular_price', 10, 2);
add_filter('woocommerce_product_variation_get_sale_price',    'd_adjust_variation_sale_price', 10, 2);
add_filter('woocommerce_variation_prices_price',              'd_adjust_variation_price', 10, 2);
add_filter('woocommerce_variation_prices_regular_price',      'd_adjust_variation_regular_price', 10, 2);
add_filter('woocommerce_variation_prices_sale_price',         'd_adjust_variation_sale_price', 10, 2);

function ba_is_checkout_ajax() {
    return (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['wc-ajax'])
        && in_array($_REQUEST['wc-ajax'], array('update_order_review','checkout'), true));
}

function d_adjust_variation_price($price, $variation) {
    if ((is_admin() && !wp_doing_ajax()) || ba_is_cart_or_checkout_request()) return $price;
    if (!$variation || !is_a($variation, 'WC_Product_Variation')) return $price;
    return check_product_pa_size($price, $variation);
}
function d_adjust_variation_regular_price($price, $variation) {
    if ((is_admin() && !wp_doing_ajax()) || ba_is_cart_or_checkout_request()) return $price;
    return check_product_pa_size($price, $variation);
}
function d_adjust_variation_sale_price($price, $variation) {
    if ($price === '' || $price === null) return $price;
    if ((is_admin() && !wp_doing_ajax()) || ba_is_cart_or_checkout_request()) return $price;
    return check_product_pa_size($price, $variation);
}

/**
 * Return the unadjusted base price for a product/variation (sale if active, else regular).
 * Reads raw meta to avoid any previously filtered/modified price on the object.
 * WITH CACHING for performance
 */
function ba_get_unadjusted_base_price( WC_Product $product ) {
    $pid = $product->get_id();
    
    // Check cache first
    $cache_key = 'ba_raw_price_' . $pid;
    $cached = wp_cache_get($cache_key, 'ba_product_prices');
    
    if ($cached !== false) {
        return (float) $cached;
    }
    
    $price = get_post_meta( $pid, '_price', true ); // sale price if active, else regular
    if ($price === '' || $price === null) {
        $sale = get_post_meta( $pid, '_sale_price', true );
        $reg  = get_post_meta( $pid, '_regular_price', true );
        $price = ($sale !== '' && $sale !== null) ? $sale : $reg;
    }
    
    $result = (float) ba_parse_number($price, 0.0);
    wp_cache_set($cache_key, $result, 'ba_product_prices', HOUR_IN_SECONDS);
    
    return $result;
}

/* --------------------------------------------------------------------------
   Reset cart item objects to RAW DB price when loaded from session
   (prevents compounding adjustments / "double tax" on first paint)
-------------------------------------------------------------------------- */
add_filter('woocommerce_get_cart_item_from_session', function ($cart_item, $cart_item_key) {
    if (! empty($cart_item['data']) && $cart_item['data'] instanceof WC_Product) {
        $raw = ba_get_unadjusted_base_price($cart_item['data']); // sale if active, else regular
        if ($raw > 0) {
            $cart_item['data']->set_price( wc_format_decimal($raw) );
        }
    }
    return $cart_item;
}, 5, 2);

/* --------------------------------------------------------------------------
   Cart/Checkout math - FIXED VERSION with proper error handling
   Ensures we always use the original database price as base
-------------------------------------------------------------------------- */
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !wp_doing_ajax()) return;
    if (!$cart || !is_a($cart, 'WC_Cart')) return;

    // Prevent infinite loops with proper cleanup
    static $running = false;
    if ($running) return;
    
    try {
        $running = true;

        foreach ($cart->get_cart() as $item) {
            if (empty($item['data']) || !($item['data'] instanceof WC_Product)) {
                continue;
            }

            $product = $item['data'];
            
            // CRITICAL: Get the ORIGINAL price from database, not filtered
            $product_id = $product->get_id();
            
            // Use caching for better performance
            $cache_key = 'ba_cart_price_' . $product_id;
            $base = wp_cache_get($cache_key, 'ba_cart_prices');
            
            if ($base === false) {
                // For variations, get the variation price
                if ($product instanceof WC_Product_Variation || $product->is_type('variation')) {
                    $base = (float) get_post_meta($product_id, '_price', true);
                    if (!$base || $base <= 0) {
                        $base = (float) get_post_meta($product_id, '_regular_price', true);
                    }
                } else {
                    // For simple products
                    $base = (float) get_post_meta($product_id, '_price', true);
                    if (!$base || $base <= 0) {
                        $base = (float) get_post_meta($product_id, '_regular_price', true);
                    }
                }
                
                wp_cache_set($cache_key, $base, 'ba_cart_prices', 300); // 5 min cache
            }
            
            if ($base <= 0) {
                continue;
            }

            // 1) Apply size-based adjustment if applicable
            $adjusted = check_product_pa_size($base, $product);

            // 2) Apply province-level tax
            $tax = get_curent_tax_province($product);
            $final = ($tax > 0) ? calculate_price_based_on_type($adjusted, $tax) : $adjusted;

            // Set the calculated price
            $product->set_price(wc_format_decimal($final));
        }
        
    } catch (Exception $e) {
        ba_log('Error calculating cart totals: ' . $e->getMessage(), 'error');
    } finally {
        $running = false;
    }
}, 999); // Very high priority to run last

/* --------------------------------------------------------------------------
   Force AJAX requests to recognize province parameter
-------------------------------------------------------------------------- */
add_action('init', function() {
    // Make province available in AJAX context - improved handling
    if (wp_doing_ajax()) {
        $ajax_province = null;
        
        if (isset($_REQUEST['province'])) {
            $ajax_province = sanitize_text_field($_REQUEST['province']);
        } elseif (isset($_POST['province'])) {
            $ajax_province = sanitize_text_field($_POST['province']);
        }
        
        if ($ajax_province && ba_is_valid_province_code($ajax_province)) {
            $_GET['province'] = $ajax_province;
        }
    }
}, 0);

/* --------------------------------------------------------------------------
   Add province to AJAX URLs via JavaScript - FIXED XSS
-------------------------------------------------------------------------- */
add_action('wp_footer', function() {
    $province = ba_get_current_province();
    if (!$province) return;
    ?>
    <script>
    jQuery(function($) {
        // Store current province safely
        window.baCurrentProvince = <?php echo wp_json_encode(esc_js($province)); ?>;
        
        // Hook into WooCommerce AJAX events to add province
        $(document).on('wc-ajax-request.ba', function(e, data) {
            if (data && data.url && data.url.indexOf('province=') === -1) {
                data.url += (data.url.indexOf('?') === -1 ? '?' : '&') + 'province=' + encodeURIComponent(window.baCurrentProvince);
            }
        });
        
        // Modify AJAX settings before send
        $(document).ajaxSend(function(event, xhr, settings) {
            if (settings.url && settings.url.indexOf('wc-ajax=') !== -1) {
                if (settings.url.indexOf('province=') === -1) {
                    settings.url += (settings.url.indexOf('?') === -1 ? '?' : '&') + 'province=' + encodeURIComponent(window.baCurrentProvince);
                }
            }
        });
        
        // Clear fragments when province changes
        var storedProvince = sessionStorage.getItem('ba_province');
        if (storedProvince !== window.baCurrentProvince) {
            sessionStorage.setItem('ba_province', window.baCurrentProvince);
            
            // Clear all WC fragments
            try {
                Object.keys(sessionStorage).forEach(function(key) {
                    if (key.indexOf('wc_fragments') === 0 || key.indexOf('wc_cart_hash') === 0) {
                        sessionStorage.removeItem(key);
                    }
                });
            } catch(e) {
                console.error('Could not clear session storage:', e);
            }
            
            // Force fragments refresh if cart exists
            if (typeof wc_cart_fragments_params !== 'undefined') {
                $(document.body).trigger('wc_fragment_refresh');
            }
        }
    });
    </script>
    <?php
}, 100); // Late priority to ensure jQuery is loaded

/* --------------------------------------------------------------------------
   Clear cart session when province changes
-------------------------------------------------------------------------- */
add_action('init', function() {
    if (isset($_GET['province'])) {
        $new_province = strtoupper(sanitize_text_field($_GET['province']));
        $old_province = isset($_COOKIE['province']) ? strtoupper(sanitize_text_field($_COOKIE['province'])) : '';
        
        if ($new_province !== $old_province && function_exists('WC') && WC()->session) {
            // Force cart recalculation
            WC()->session->set('cart_totals', null);
            
            // Trigger recalculation on next request
            if (WC()->cart) {
                WC()->cart->calculate_totals();
            }
        }
    }
}, 2);

// Hook specifically for CheckoutWC AJAX
add_action('wp_ajax_cfw_update_cart', function() {
    $province = ba_get_current_province();
    if ($province) {
        $_GET['province'] = $province;
    }
}, 0);

add_action('wp_ajax_nopriv_cfw_update_cart', function() {
    $province = ba_get_current_province();
    if ($province) {
        $_GET['province'] = $province;
    }
}, 0);

/* Keep the chosen province across checkout updates (CheckoutWC/Woo Ajax) */
add_action('woocommerce_checkout_update_order_review', function () {
    $state = '';
    if (isset($_POST['shipping_state']) && $_POST['shipping_state'] !== '') {
        $state = sanitize_text_field(wp_unslash($_POST['shipping_state']));
    } elseif (isset($_POST['state']) && $_POST['state'] !== '') {
        $state = sanitize_text_field(wp_unslash($_POST['state']));
    }

    if ($state && function_exists('WC') && WC()->session) {
        WC()->session->set('ba_checkout_province', strtoupper($state));
    }
});

/* Ensure ba_get_current_province() sees the checkout-selected province early */
add_action('woocommerce_before_calculate_totals', function () {
    if (function_exists('WC') && WC()->session) {
        $p = WC()->session->get('ba_checkout_province');
        if ($p && ba_is_valid_province_code($p)) {
            $_GET['province'] = $p; // temporary request override
        }
    }
}, 5);

/* Ensure price caches are province-aware */
add_filter('woocommerce_get_prices_hash', function ($hash, $product, $for_display) {
    $prov = ba_get_current_province();
    $hash['province'] = $prov ? $prov : 'none';
    // Also hash on calculation types to avoid stale caches when admin switches modes
    $hash['calc_type'] = get_option('wc_tax_calculation_type', 'percentage');
    $hash['size_calc'] = get_option('wc_tax_calculation_size_type', 'percentage');
    return $hash;
}, 10, 3);

add_filter('woocommerce_get_variation_prices_hash', function ($hash, $product, $for_display) {
    $prov = ba_get_current_province();
    $hash['province'] = $prov ? $prov : 'none';
    $hash['calc_type'] = get_option('wc_tax_calculation_type', 'percentage');
    $hash['size_calc'] = get_option('wc_tax_calculation_size_type', 'percentage');
    return $hash;
}, 10, 3);

/* --------------------------------------------------------------------------
   Visibility (archives, search, purchasability) using ?province=XX
-------------------------------------------------------------------------- */
add_filter('dgwt/wcas/search_query/args', function ($args) {
    $province_code = ba_get_current_province();
    if (!$province_code) return $args;

    $exclude_ids = get_option('wc_province_hide_product_' . $province_code, array());
    if (!empty($exclude_ids)) $args['post__not_in'] = array_map('intval', (array)$exclude_ids);

    $selected_categories = get_option('wc_province_product_hide_category_' . $province_code, array());
    if (!empty($selected_categories)) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => array_map('intval', (array)$selected_categories),
            'operator' => 'NOT IN',
        );
    }
    return $args;
});

add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || is_checkout()) return;

    $province_code = ba_get_current_province();
    if (!$province_code) return;

    if (is_post_type_archive('product') || is_tax('product_cat') || is_tax('product_tag')) {
        $selected_products   = array_map('intval', (array)get_option('wc_province_hide_product_' . $province_code, array()));
        $selected_categories = (array)get_option('wc_province_product_hide_category_' . $province_code, array());

        if (!empty($selected_products)) {
            $query->set('post__not_in', $selected_products);
        }
        if (!empty($selected_categories)) {
            $query->set('tax_query', array(array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => (array)$selected_categories,
                'operator' => 'NOT IN',
            )));
        }
    }
});

add_filter('woocommerce_is_purchasable', function ($purchasable, $product) {
    if (!is_product()) return $purchasable;

    $province_code = ba_get_current_province();
    if (!$province_code) return $purchasable;

    $selected_products   = array_map('intval', (array)get_option('wc_province_hide_product_' . $province_code, array()));
    $selected_categories = (array)get_option('wc_province_product_hide_category_' . $province_code, array());

    $product_id = $product->get_id();
    if (in_array($product_id, $selected_products, true)) return false;
    if (!empty($selected_categories) && has_term($selected_categories, 'product_cat', $product_id)) return false;

    return $purchasable;
}, 10, 2);

add_filter('woocommerce_variation_is_purchasable', function ($purchasable, $variation) {
    if (!is_product()) return $purchasable;

    $province_code = ba_get_current_province();
    if (!$province_code) return $purchasable;

    $selected_products   = array_map('intval', (array)get_option('wc_province_hide_product_' . $province_code, array()));
    $selected_categories = array_map('intval', (array)get_option('wc_province_product_hide_category_' . $province_code, array()));

    $product_id = $variation->get_id();
    $parent_id  = $variation->get_parent_id();

    if (in_array($product_id, $selected_products, true) || in_array($parent_id, $selected_products, true)) {
        return false;
    }
    return $purchasable;
}, 10, 2);

add_filter('woocommerce_shortcode_products_query', function ($query_args, $attributes, $type) {
    if ($type !== 'products') return $query_args;

    $province_code = ba_get_current_province();
    if (!$province_code) return $query_args;

    $selected_products   = array_map('intval', (array)get_option('wc_province_hide_product_' . $province_code, array()));
    $selected_categories = (array)get_option('wc_province_product_hide_category_' . $province_code, array());

    if (isset($query_args['post__in'])) {
        $query_args['post__in'] = array_diff((array)$query_args['post__in'], $selected_products);
    } elseif (!empty($selected_products)) {
        $query_args['post__not_in'] = $selected_products;
    }

    if (!empty($selected_categories)) {
        $query_args['tax_query'] = array(array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => (array)$selected_categories,
            'operator' => 'NOT IN',
        ));
    }

    return $query_args;
}, 10, 3);

/* --------------------------------------------------------------------------
   Checkout validation for province
-------------------------------------------------------------------------- */
add_action('woocommerce_checkout_process', function () {
    $selected_province   = (function_exists('WC') && WC()->customer) ? WC()->customer->get_shipping_state() : ba_get_current_province();
    $selected_products   = array_map('intval', (array)get_option('wc_province_hide_product_' . $selected_province, array()));
    $selected_categories = array_map('intval', (array)get_option('wc_province_product_hide_category_' . $selected_province, array()));

    $cart = (function_exists('WC') && WC()->cart) ? WC()->cart->get_cart() : array();
    $unavailable_items = array();

    foreach ($cart as $cart_item) {
        $product_id = $cart_item['product_id'];
        $product_categories = wc_get_product_term_ids($product_id, 'product_cat');

        if (in_array($product_id, $selected_products, true)) {
            $unavailable_items[] = $cart_item['data']->get_name();
        }
        if (array_intersect($selected_categories, $product_categories)) {
            $unavailable_items[] = $cart_item['data']->get_name();
        }
    }

    if (!empty($unavailable_items)) {
        remove_action('woocommerce_before_cart_item_quantity_zero', 'woocommerce_remove_cart_item');
        wc_add_notice(
            sprintf(
                'The items "%s" are not available for the selected province, please remove them or reselect the previous province.',
                implode(', ', array_unique($unavailable_items))
            ),
            'error'
        );
    }
});

// === Province Settings tab (WooCommerce) ===
function wc_add_custom_settings($settings) {
    require_once __DIR__ . '/WC_Settings_Custom.php';
    $settings[] = new WC_Settings_Custom();
    return $settings;
}
add_filter('woocommerce_get_settings_pages', 'wc_add_custom_settings');

// --- Force-add ?province=XX to dynamic links (e.g., FiboSearch suggestions) ---
// Replace your existing block with this one.
add_action('wp_footer', function () {
    $prov = ba_get_current_province();
    if (!$prov || ba_is_agegate_context()) return;
    ?>
    <script>
    (function(){
      // Current, validated province (server-injected)
      var prov = <?php echo wp_json_encode($prov); ?>;

      // Ensure URL is same-origin to avoid rewriting external links
      function sameOrigin(u){
        try { return (new URL(u, location.href)).origin === location.origin; }
        catch(e){ return false; }
      }

      // Guarantee province param in a given URL string
      function ensureProvinceOnUrl(urlStr){
        try {
          var url = new URL(urlStr, location.href);
          if (!sameOrigin(url)) return urlStr;
          if (!url.searchParams.has('province')) url.searchParams.set('province', prov);
          return url.toString();
        } catch(e){
          return urlStr;
        }
      }

      // Patch a single anchor: href and potential data-href used by some libraries
      function patchAnchor(a){
        if (!a) return;
        var href = a.getAttribute('href');
        if (href && href[0] !== '#' && !href.startsWith('mailto:') && !href.startsWith('tel:')) {
          var fixed = ensureProvinceOnUrl(href);
          if (fixed !== href) a.setAttribute('href', fixed);
        }
        var dh = a.getAttribute('data-href');
        if (dh) {
          var fixedDH = ensureProvinceOnUrl(dh);
          if (fixedDH !== dh) a.setAttribute('data-href', fixedDH);
        }
      }

      // Patch all anchors and forms within a root node
      function patchAll(root){
        (root || document).querySelectorAll('a').forEach(patchAnchor);

        // Forms: fix action and include hidden province input as a fallback
        (root || document).querySelectorAll('form').forEach(function(f){
          try {
            var action = f.getAttribute('action') || '';
            if (action) {
              var fixed = ensureProvinceOnUrl(action);
              if (fixed !== action) f.setAttribute('action', fixed);
            }
            if (!f.querySelector('input[name="province"]')) {
              var i = document.createElement('input');
              i.type = 'hidden'; i.name = 'province'; i.value = prov;
              f.appendChild(i);
            }
          } catch(e){}
        });
      }

      // 1) Patch what already exists
      patchAll(document);

      // 2) Observe dynamic DOM (covers FiboSearch suggestions and any async content)
      var mo = new MutationObserver(function(muts){
        muts.forEach(function(m){
          m.addedNodes && m.addedNodes.forEach(function(n){
            if (n.nodeType !== 1) return;
            patchAll(n);
          });
        });
      });
      mo.observe(document.body, {childList:true, subtree:true});

      // 3) Last-chance patch right before navigating (captures late href rewrites)
      function preNavFixFromEvent(e){
        var a = e && e.target && e.target.closest ? e.target.closest('a') : null;
        if (!a) return;
        patchAnchor(a);
      }
      document.addEventListener('click', preNavFixFromEvent, true);      // left click
      document.addEventListener('auxclick', preNavFixFromEvent, true);   // middle click
      document.addEventListener('mousedown', preNavFixFromEvent, true);  // some UIs navigate on mousedown
      document.addEventListener('keydown', function(e){                   // keyboard Enter
        if (e.key === 'Enter') preNavFixFromEvent(e);
      }, true);

      // 4) Ensure province on form submit (fallback if navigation is via GET submit)
      document.addEventListener('submit', function(e){
        var f = e.target;
        if (!f || !f.action) return;
        try {
          var fixed = ensureProvinceOnUrl(f.action);
          if (fixed !== f.action) f.action = fixed;
          if (!f.querySelector('input[name="province"]')) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = 'province'; i.value = prov;
            f.appendChild(i);
          }
        } catch(_){}
      }, true);

      // 5) FiboSearch-specific containers: patch immediately and when they re-render
      function patchFiboNow(){
        document
          .querySelectorAll('.dgwt-wcas-suggestions-wrapp,.dgwt-wcas-search-wrapp,.dgwt-wcas-content')
          .forEach(patchAll);
      }
      patchFiboNow();

      // Listen for common FiboSearch lifecycle events (varies by theme/integration)
      ['dgwt-wcas-render-suggestions','dgwt-wcas-open','dgwt-wcas-close','dgwt-wcas-results']
        .forEach(function(ev){ document.addEventListener(ev, patchFiboNow, true); });
    })();
    </script>
    <?php
}, 99);

/* --------------------------------------------------------------------------
   SEO hardening for province param (Yoast SEO present)
   - Canonical: strip `province` from canonical URLs
   - Open Graph: strip `province` from og:url
   - Optional: noindex,follow for pages with `?province=`
-------------------------------------------------------------------------- */

/** Utility: remove the `province` query arg from a URL. */
function ba_seo_strip_province_from_url( $url ) {
    if ( empty( $url ) ) { return $url; }
    return remove_query_arg( 'province', $url );
}

/** Yoast: force canonical without `province`. */
add_filter( 'wpseo_canonical', function( $canonical ) {
    if ( isset( $_GET['province'] ) ) {
        return ba_seo_strip_province_from_url( $canonical );
    }
    return $canonical;
} );

/** Yoast: normalize Open Graph og:url without `province`. */
add_filter( 'wpseo_opengraph_url', function( $og_url ) {
    if ( isset( $_GET['province'] ) ) {
        return ba_seo_strip_province_from_url( $og_url );
    }
    return $og_url;
} );

/** Optional noindex (modern Yoast: robots ARRAY API). */
add_filter( 'wpseo_robots_array', function( $robots ) {
    if ( ! isset( $_GET['province'] ) ) { return $robots; }
    if ( ! apply_filters( 'ba_seo_province_noindex', false ) ) { return $robots; }
    $robots['index']  = false;
    $robots['follow'] = true;
    return $robots;
}, 10 );

/** Optional noindex (legacy Yoast: robots STRING API). */
add_filter( 'wpseo_robots', function( $robots ) {
    if ( ! isset( $_GET['province'] ) ) { return $robots; }
    if ( ! apply_filters( 'ba_seo_province_noindex', false ) ) { return $robots; }

    $directives = array_filter( array_map( 'trim', explode( ',', strtolower( $robots ) ) ) );
    $directives = array_diff( $directives, array( 'index', 'noindex', 'follow', 'nofollow' ) );
    $directives[] = 'noindex';
    $directives[] = 'follow';

    return implode( ', ', array_unique( $directives ) );
}, 10 );

// Make the cart hash province-aware so wc-cart-fragments don't reuse HTML across provinces
add_filter('woocommerce_cart_hash', function ($hash) {
    $prov = ba_get_current_province();
    $suffix = $prov ? $prov : 'none';
    return md5($hash . '|' . $suffix);
}, 10, 1);

add_filter('woocommerce_enqueue_cart_fragments', '__return_true', 99);
add_action('wp_enqueue_scripts', function () {
    if (wp_script_is('wc-cart-fragments', 'registered') && !wp_script_is('wc-cart-fragments', 'enqueued')) {
        wp_enqueue_script('wc-cart-fragments');
    }
}, 100);

/* --------------------------------------------------------------------------
   Hide sidecart until fresh fragments land; never reveal on 'added_to_cart'
-------------------------------------------------------------------------- */
add_action('wp_head', function () { ?>
  <script>
  (function () {
    try {
      var ls = window.localStorage;
      if (ls) {
        // Clear any stale Woo fragment keys so we fetch fresh ones
        Object.keys(ls).forEach(function(k){
          if (/^(wc_fragments|wc_cart_hash|woocommerce_cart_hash|cfw[_-])/.test(k)) ls.removeItem(k);
        });
      }
    } catch(e){}

    // Hide sidecart containers until fragments are applied
    try { document.documentElement.classList.add('ba-waiting-fragments'); } catch(e){}
  })();
  </script>
<?php }, 1);

add_action('wp_head', function () { ?>
  <style>
    html.ba-waiting-fragments .cfw-side-cart,
    html.ba-waiting-fragments .widget_shopping_cart,
    html.ba-waiting-fragments .widget_shopping_cart_content {
      visibility: hidden;
    }
  </style>
<?php }, 2);

add_action('wp_footer', function () { ?>
  <script>
  jQuery(function($){
    var reveal = function(){ document.documentElement.classList.remove('ba-waiting-fragments'); };

    // Only reveal when Woo fragments are loaded or refreshed
    $(document.body).on('wc_fragments_loaded wc_fragments_refreshed', reveal);

    // Keep hidden while adding to cart (prevents showing pre-refresh DOM)
    $(document.body).on('adding_to_cart', function(){
      document.documentElement.classList.add('ba-waiting-fragments');
    });

    // Safety valve in case events are missed
    setTimeout(reveal, 6000);
  });
  </script>
<?php }, 5);
