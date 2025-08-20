<?php
/**
 * Plugin Name: Custom Age Verifier and Tax Plugin
 * Description: A custom plugin to add an age verifier and apply taxes to specific product categories.
 * Version: 1.0
 * Author: Block Agency
 * Author URI: https://blockagency.co
 */

 if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

register_activation_hook(__FILE__, 'woo_price_calc_plugin_activate');

function woo_price_calc_plugin_activate() {
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        // Deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));

        // Throw an error in the WordPress admin console
        $error_message = __('This plugin requires WooCommerce to be activated', 'woo_price_calc_plugin');
        die($error_message);
    }

    add_option('woo_price_calc_plugin_do_activation_redirect', true);
}

// #1: Age verifier
function enqueue_age_verifier_script() {
    wp_enqueue_style('age-verifier', plugin_dir_url(__FILE__) . '/styles.css', array(), '1.0');
    wp_enqueue_script('age-verifier', plugin_dir_url(__FILE__) . 'js/age-verifier.js', array('jquery'), '1.0.0', true);
    
    // Check if the 'woocommerce-google-address.php' plugin is active
    //if (is_plugin_active('woocommerce-google-address/woocommerce-google-address.php')) {
       
        // Enqueue the new JavaScript file only if the plugin is active
       wp_enqueue_script('woocommerce-address-handler', plugin_dir_url(__FILE__) . 'js/woocommerce-address-handler.js', array('jquery'), '1.0.0', true);
    //}
}
add_action('wp_enqueue_scripts', 'enqueue_age_verifier_script');

// add popup
add_action('wp_head', 'age_verification_dialog');

function get_logo_url() {
    $custom_logo_id = get_theme_mod('custom_logo');
    $logo = wp_get_attachment_image_src($custom_logo_id, 'full');

    if (has_custom_logo()) {
        return $logo[0];
    } else {
        return false;
    }
}

function is_admin_simulating_customer_role() {
   // var_dump(VAA_API::is_view_active());
    // Check if the "View Admin As" plugin is active and a view is being simulated
    if ( VAA_API::is_view_active() ) {
        // Ensure the view_admin_as_role function exists before calling it
        return true;
    }
    return false;
}

function d_add_province_class($classes) {
    if (!isset($_COOKIE['province'])) {
        $classes[] = 'no-province';
    }
    return $classes;
}
add_filter('body_class', 'd_add_province_class');

function age_verification_dialog() {
    // PHP code to generate the HTML for the form
    // Check if the cookie exists
  
    if (isset($_COOKIE['province'])) {
        return;
    }
    //var_dump($_COOKIE['province']);
    ob_start(); // Start output buffering

    $province_group2 = array(
        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador',
        'NS' => 'Nova Scotia',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'NT' => 'Northwest Territories',
        'NU' => 'Nunavut',
        'YT' => 'Yukon Territory'
    );
   ?>
    <div id="custom-age-popup-wrapper">
		<div id="custom-age-popup-box">
		
				<center>
                    <?php
                    $logo_url = get_logo_url();
                    ?>
                    <img class="d-logo" src="<?php echo $logo_url; ?>" alt="Logo"><br>
                    <hr class="custom-age-saparater">
		<!-- <h2 class="custom-age-title">Age Verification</h2> -->
		<p>This site is intended for adults <span class="bold-txt"><span id="age-limit">19</span> years and older</span>. If you are not legally able to purchase tobacco products in your province, please do not enter this site.</p>
		</center>
		<div class="custom-age-btn-box">
        <form id="d-age-verification-form" action="<?php echo admin_url('admin-post.php'); ?>" method="post">
        <input type="hidden" name="action" value="d_age_verification_form">
        <?php wp_nonce_field('d_age_verification_form_nonce', 'davf_nonce'); ?>

        <div class="custom-age-btn-box-row" id="province-box">
      
			<div class="province_wrapper">
			
            <select id="province" name="province" required>
            <option value="" disabled selected>Select your province</option>
                <?php foreach ($province_group2 as $code => $name): ?>
                 

                    <option value="<?php echo $code; ?>">
                        <?php echo $name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
			</div>
		</div>


		<div id="dob-box">
		
		<p class="select-dob-label">Please select your birthdate to confirm you are at least 19 years of age.</p>
		<div class="custom-age-btn-box-row custom-birthdate">
                <div class="birth_month_wrapper">
                    <select id="month" name="month">
                        <?php 
                        $months = array(1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
                        for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo date('n') == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="birth_date_wrapper">
                    <select id="day" name="day">
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo date('j') == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="birth_year_wrapper">
                    <select id="year" name="year">
                        <?php for ($i = date('Y'); $i >= date('Y') - 100; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php === 2000) ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
		</div>
		


        <input type="submit" value="Enter" class="custom-btn-age yes">
        <a href="https://www.google.com/" id="" class="custom-btn-age no" class="custom-btn-age no">Exit</a>


		</div>
		</div>

        <p id="error-message" style="display: none; color: red;">Sorry! You are under age to visit website. Only 19+ age person can visit.</p>
		</form>
		</div>
	</div>
    <?php
     $output_string = ob_get_contents();
     ob_end_clean();
 
    echo $output_string;

}

add_action('send_headers', function() {
    if (in_array('no-province', get_body_class())) { // Check for the age verification class
        header('Cache-Control: no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
});
