<?php
/**
 * GeneratePress Child Theme Functions
 */


// [acf_rating field="rating" id="123" max="5" show_text="true"]
add_shortcode('acf_rating', function($atts) {
    $a = shortcode_atts([
        'field'     => 'rating',   // ACF field name (number)
        'id'        => '',         // Optional post ID (defaults to current)
        'max'       => 5,          // Max stars
        'show_text' => 'true',     // "true" | "false" to show 3.5/5 text
    ], $atts, 'acf_rating');

    $post_id = $a['id'] !== '' ? intval($a['id']) : get_the_ID();
    $max     = max(1, intval($a['max']));

    // Get rating value from ACF (fallback to post meta if ACF not active)
    if (function_exists('get_field')) {
        $value = get_field($a['field'], $post_id);
    } else {
        $value = get_post_meta($post_id, $a['field'], true);
    }

    if ($value === '' || $value === null) return ''; // nothing to show

    $value = floatval($value);
    $value = max(0, min($value, $max)); // Clamp rating

    $percent = ($value / $max) * 100.0;
    $display_value = rtrim(rtrim(number_format($value, 1), '0'), '.'); // e.g. 3.5
    $label_vis     = $display_value . '/<strong>' . intval($max) . '</strong>'; // 3.5/<strong>5</strong>
    $label_aria    = sprintf('%s out of %s', $display_value, $max);

    // SVG star (color controlled via CSS "currentColor")
    $star_svg = '
<svg xmlns="http://www.w3.org/2000/svg" width="19" height="17" viewBox="0 0 19 17" fill="none" aria-hidden="true" focusable="false">
  <path d="M9.24494 0L11.8641 5.63991L18.0374 6.38809L13.4829 10.6219L14.679 16.7243L9.24494 13.701L3.8109 16.7243L5.00697 10.6219L0.452479 6.38809L6.62573 5.63991L9.24494 0Z" fill="currentColor"/>
</svg>';

    ob_start(); ?>
    <span class="acf-rating" role="img" aria-label="<?php echo esc_attr($label_aria); ?>">
      <span class="acf-rating__wrap">
        <span class="acf-rating__bg">
          <?php for ($i = 0; $i < $max; $i++) echo '<span class="acf-rating__star">'.$star_svg.'</span>'; ?>
        </span>
        <span class="acf-rating__fg" style="width: <?php echo esc_attr($percent); ?>%;">
          <?php for ($i = 0; $i < $max; $i++) echo '<span class="acf-rating__star">'.$star_svg.'</span>'; ?>
        </span>
      </span>

      <?php if (filter_var($a['show_text'], FILTER_VALIDATE_BOOLEAN)) : ?>
        <span class="acf-rating__text">
          <?php echo wp_kses($label_vis, ['strong' => []]); ?>
        </span>
      <?php endif; ?>
    </span>

    <?php
    return ob_get_clean();
});




/** 
 * Show custom styles in backend 
 */
add_filter( 'block_editor_settings_all', function( $editor_settings ) {
    $css = wp_get_custom_css_post()->post_content;
    $editor_settings['styles'][] = array( 'css' => $css );

    return $editor_settings;
} );

function enqueue_elliptical_slider_assets() {
    wp_enqueue_style(
        'elliptical-slider-css',
        get_stylesheet_directory_uri() . '/css/elliptical-slider.css',
        array(),
        '1.0.0'
    );

    wp_enqueue_script(
        'elliptical-slider-js',
        get_stylesheet_directory_uri() . '/js/elliptical-slider.js',
        array('jquery'), // add dependencies if needed
        '1.0.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'enqueue_elliptical_slider_assets');




/** 
 * Primary menu shortcode 
 */
add_shortcode( 'gp_nav', 'tct_gp_nav' );
function tct_gp_nav( $atts ) {
    ob_start();
    generate_navigation_position();
    return ob_get_clean();
}



// functions.php in GeneratePress child theme
function gp_child_enqueue_owl_assets() {
    // Owl Carousel CSS
    wp_enqueue_style(
        'owl-carousel-css', 
        get_stylesheet_directory_uri() . '/css/owl.carousel.css',
        array(),
        '2.3.4'
    );
    
    wp_enqueue_style(
        'owl-theme-ddefault', 
        get_stylesheet_directory_uri() . '/css/owl.theme.default.css',
        array('owl-carousel'),
        '2.3.4'
    );

      // SwiperJS CSS (Local)
      wp_enqueue_style(
        'swiper-css', 
            get_stylesheet_directory_uri() . '/css/swiper-bundle.min.css',
        array(),
        '11.0.0'
    );
    
    // SwiperJS JavaScript (Local)
    wp_enqueue_script(
        'swiper-js', 
        get_stylesheet_directory_uri() . '/js/swiper-bundle.min.js',
        array(),
        '11.0.0',
        true
    );



    // Owl Carousel JS
    wp_enqueue_script(
        'owl-carousel', 
        get_stylesheet_directory_uri() . '/js/owl.carousel.min.js',
        array('jquery'),
        '2.3.4',
        true
    );

    // Custom init (optional)
    wp_enqueue_script(
        'owl-init',
        get_stylesheet_directory_uri() . '/js/owl-init.js',
        array('owl-carousel'),
        null,
        true
    );
}
add_action('wp_enqueue_scripts', 'gp_child_enqueue_owl_assets');




/**
 * Secondary menu shortcode
 */
add_shortcode( 'gp_secondary_nav', 'tct_gp_secondary_nav' );
function tct_gp_secondary_nav( $atts ) {
    ob_start();
    generate_secondary_navigation_position();
    return ob_get_clean();
}
// 1. Add Meta Box for Subtitle
function add_post_subtitle_meta_box() {
    add_meta_box(
        'post_subtitle_meta_box',        // ID
        'Post Subtitle',                 // Title
        'render_post_subtitle_field',    // Callback function
        'post',                          // Post type
        'normal',                        // Context
        'default'                        // Priority
    );
}
add_action('add_meta_boxes', 'add_post_subtitle_meta_box');

// 2. Render Input Field
function render_post_subtitle_field($post) {
    $subtitle = get_post_meta($post->ID, 'subtitle', true); // <-- NO underscore
    ?>
    <label for="post_subtitle">Subtitle:</label><br>
    <input type="text" id="post_subtitle" name="post_subtitle" value="<?php echo esc_attr($subtitle); ?>" style="width:100%;" />
    <?php
}

// 3. Save Field Data
function save_post_subtitle_field($post_id) {
    if (isset($_POST['post_subtitle'])) {
        update_post_meta(
            $post_id,
            'subtitle', // <-- NO underscore
            sanitize_text_field($_POST['post_subtitle'])
        );
    }
}
add_action('save_post', 'save_post_subtitle_field');


function calculate_reading_time_shortcode($atts, $content = null) {
    global $post;

    // Set words per minute (average reading speed)
    $wpm = 200;

    // Use post content if none is passed
    if (!$content) {
        $content = $post->post_content;
    }

    // Count words
    $word_count = str_word_count(strip_tags($content));

    // Calculate time
    $reading_time = ceil($word_count / $wpm);

    return $reading_time . ' min read';
}
add_shortcode('reading_time', 'calculate_reading_time_shortcode');


// Shortcode: [blog_pagination]
function blog_pagination_shortcode( $atts ) {
    global $wp_query;

    $atts = shortcode_atts( array(
        'mid_size'  => 2,
        'prev_text' => '« Previous',
        'next_text' => 'Next »',
    ), $atts, 'blog_pagination' );

    $total   = $wp_query->max_num_pages;
    $current = max( 1, get_query_var( 'paged' ) );

    if ( $total <= 1 ) {
        return ''; // Không cần pagination nếu chỉ có 1 trang
    }

    // Page numbers
    $links = paginate_links( array(
        'mid_size'  => (int) $atts['mid_size'],
        'total'     => $total,
        'current'   => $current,
        'type'      => 'array',
        'prev_next'  => false,
    ) );

    $output = '<nav class="blog-pagination"><ul class="pagination">';

    // Prev button
    if ( $current > 1 ) {
        $output .= '<li class="page-item prev"><a href="' . esc_url( get_pagenum_link( $current - 1 ) ) . '" class="page-numbers">' . esc_html( $atts['prev_text'] ) . '</a></li>';
    } else {
        $output .= '<li class="page-item prev disabled"><span class="page-numbers">' . esc_html( $atts['prev_text'] ) . '</span></li>';
    }

    // Page numbers
    if ( ! empty( $links ) ) {
        foreach ( $links as $link ) {
            $output .= '<li class="page-item">' . $link . '</li>';
        }
    }

    // Next button
    if ( $current < $total ) {
        $output .= '<li class="page-item next"><a href="' . esc_url( get_pagenum_link( $current + 1 ) ) . '" class="page-numbers">' . esc_html( $atts['next_text'] ) . '</a></li>';
    } else {
        $output .= '<li class="page-item next disabled"><span class="page-numbers">' . esc_html( $atts['next_text'] ) . '</span></li>';
    }

    $output .= '</ul></nav>';

    return $output;
}
add_shortcode( 'blog_pagination', 'blog_pagination_shortcode' );

function my_top_blog_shortcode() {
    ob_start();

    // Current category
    $current_cat = get_queried_object();
    $current_cat_id = ( $current_cat && isset($current_cat->term_id) ) ? $current_cat->term_id : 0;

    // Current order
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
    ?>
    <div class="top-blog-wrap" style="display:flex; gap:30px;">
        
        <!-- Left: Categories -->
        <div class="top-blog-cats">
            <ul>
                <li>
                    <a href="<?php echo esc_url( get_post_type_archive_link('post') ); ?>" 
                       class="<?php echo !is_category() ? 'current' : ''; ?>">
                        Show All
                    </a>
                </li>
                <?php
                $cats = get_categories();
                foreach ( $cats as $cat ) {
                    $class = ( $current_cat_id == $cat->term_id ) ? 'current' : '';
                    echo '<li><a class="'. esc_attr($class) .'" href="'. esc_url( get_category_link($cat->term_id) ) .'">'. esc_html($cat->name) .'</a></li>';
                }
                ?>
            </ul>
        </div>

        <!-- Right: Order Form -->
        <div class="top-blog-order">
            <?php 
$orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
?>

<form method="get" id="blog-order-form">
    <input type="hidden" name="loop" value="blog">

    <select name="orderby" onchange="this.form.submit()">
        <option value="date" <?php selected( $orderby, 'date' ); ?>>Most Recent</option>
        <option value="title" <?php selected( $orderby, 'title' ); ?>>Title (A → Z)</option>
        <option value="comment_count" <?php selected( $orderby, 'comment_count' ); ?>>Most Commented</option>
    </select>

    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="20" viewBox="0 0 21 20" fill="none">
      <path d="M5.5 7.5L10.5 12.5L15.5 7.5" stroke="#94969C" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</form>

        </div>

    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('top_blog', 'my_top_blog_shortcode');

// functions.php (child theme)
add_filter( 'generateblocks_query_loop_args', function( $args, $attributes, $block ) {

    // (Tuỳ chọn) Chỉ áp dụng cho form "blog"
    $only_for_loop = isset($_GET['loop']) && $_GET['loop'] === 'blog';
    if ( ! $only_for_loop ) {
        return $args;
    }

    // Nhận orderby từ URL, mặc định 'date'
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';

    // Whitelist & mapping
    switch ( $orderby ) {
        case 'title':
            $args['orderby'] = 'title';
            $args['order']   = 'ASC';
            break;

        case 'comment_count':
            // WP_Query hỗ trợ trực tiếp 'comment_count'
            $args['orderby'] = 'comment_count';
            $args['order']   = 'DESC';
            break;

        case 'rand':
            $args['orderby'] = 'rand';
            // 'rand' không cần 'order'
            unset( $args['order'] );
            break;

        case 'date':
        default:
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
            break;
    }

    return $args;
}, 10, 3 );



add_filter( 'paginate_links_output', function( $links, $args ) {
    // Lấy current page
    $current = isset( $args['current'] )
        ? (int) $args['current']
        : max( 1, (int) get_query_var( 'paged', 1 ) );

    // Lấy total pages
    if ( isset( $args['total'] ) ) {
        $total = (int) $args['total'];
    } else {
        global $wp_query;
        $total = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 0;
    }

    if ( $total < 1 ) {
        return $links;
    }

    $info = sprintf(
        '<span class="gb-page-info" aria-live="polite">Page %d of %d</span>',
        $current,
        $total
    );

    // Chèn trước dãy số trang
    return $info . $links;
}, 10, 2 );


add_filter( 'paginate_links_output', function( $links, $args ) {
    // Nếu có tham số orderby thì thêm nó vào mọi link
    if ( isset($_GET['orderby']) ) {
        $orderby = sanitize_text_field($_GET['orderby']);
        $links = str_replace(
            array('&amp;orderby=','&orderby='), // tránh duplicate
            '',
            $links
        );
        $links = preg_replace(
            '/href="([^"]+)"/',
            'href="$1&orderby=' . esc_attr($orderby) . '"',
            $links
        );
    }

    // Giữ luôn param loop
    if ( isset($_GET['loop']) ) {
        $loop = sanitize_text_field($_GET['loop']);
        $links = str_replace(
            array('&amp;loop=','&loop='),
            '',
            $links
        );
        $links = preg_replace(
            '/href="([^"]+)"/',
            'href="$1&loop=' . esc_attr($loop) . '"',
            $links
        );
    }

    return $links;
}, 10, 2 );






add_filter( 'render_block', function( $block_content, $block ) {
    static $count = 0;

    // Only apply on the Blog page
    if ( ! is_home() && ! is_post_type_archive( 'post' ) ) {
        return $block_content;
    }

    // Only count GenerateBlocks Query Loop items
    if ( isset( $block['blockName'] ) && $block['blockName'] === 'generateblocks/loop-item' ) {
        $count++;
    
        // Insert custom HTML before the 6th item
        if ( $count === 6 ) {
            $shortcode_output = do_shortcode('[wpforms id="2111"]');

            $custom = '<div class="gb-query-loop-item custom-html"><div class="gb-inside-container d-form-sub">
				<div class="d-sub-header">
						  <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 28 28" fill="none">
						  <path d="M12.2498 15.7501L24.4998 3.50008M12.3986 16.1328L15.4647 24.0172C15.7349 24.7117 15.8699 25.059 16.0645 25.1604C16.2332 25.2483 16.4342 25.2484 16.603 25.1607C16.7977 25.0596 16.9332 24.7124 17.2041 24.0182L24.8928 4.31581C25.1374 3.6891 25.2597 3.37574 25.1928 3.17551C25.1347 3.00162 24.9982 2.86516 24.8243 2.80707C24.6241 2.74018 24.3107 2.86246 23.684 3.10703L3.98167 10.7958C3.28741 11.0667 2.94029 11.2022 2.83913 11.3969C2.75143 11.5657 2.75155 11.7666 2.83944 11.9353C2.94083 12.1299 3.28812 12.265 3.98269 12.5351L11.867 15.6012C12.008 15.6561 12.0785 15.6835 12.1379 15.7258C12.1905 15.7633 12.2365 15.8094 12.274 15.862C12.3164 15.9213 12.3438 15.9918 12.3986 16.1328Z" stroke="#333741" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						  </svg>
						  <h5>Weekly newsletter</h5>
						  <p>No spam. Just the latest releases and tips, interesting articles, and exclusive interviews in your inbox every week.</p>

						</div>
                    ' . $shortcode_output . '
                   
                </div>
            </div>';

            return $custom . $block_content;
        }
    }

    return $block_content;
}, 10, 2 );


add_action( 'acf/init', 'set_acf_settings' );
function set_acf_settings() {
    acf_update_setting( 'enable_shortcode', true );
}

// Shortcode bridge: [call_action name="d_video_post_single"]
add_action('init', function () {
    add_shortcode('call_action', function ($atts) {
        $atts = shortcode_atts(['name' => ''], $atts, 'call_action');
        if (!$atts['name']) return '';

        // Safety: allow only your custom hook
        if ($atts['name'] !== 'd_video_post_single') return '';

        ob_start();
        do_action($atts['name']); // fire your action
        return ob_get_clean();
    });
});
function my_block_editor_admin_css() {
    echo '<style>
       body :where(.editor-styles-wrapper), :root :where(.editor-styles-wrapper){
	background:var(--body-background) !important;
}

    </style>';
}
add_action('enqueue_block_editor_assets', 'my_block_editor_admin_css');




// ===== CPT: modules =====
add_action('init', function () {
    register_post_type('modules', array(
        'labels' => array(
            'name'               => 'Modules',
            'singular_name'      => 'Module',
            'add_new_item'       => 'Add New Module',
            'edit_item'          => 'Edit Module',
            'new_item'           => 'New Module',
            'view_item'          => 'View Module',
            'search_items'       => 'Search Modules',
            'not_found'          => 'No modules found',
            'not_found_in_trash' => 'No modules found in Trash',
        ),
        'public'             => true,
        'publicly_queryable' => true, // Allow public queries
        'has_archive'        => true,
        'rewrite'            => array('slug' => 'modules'),
        'menu_icon'          => 'dashicons-grid-view',
        'show_in_rest'       => true, // enable Gutenberg/REST API
        'rest_base'          => 'modules', // REST API base
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'supports'           => array('title', 'editor', 'thumbnail', 'excerpt','author'),
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
    ));
});

// Flush rewrite rules after registering post type
add_action('init', function() {
    if (!get_option('modules_rewrite_flushed')) {
        flush_rewrite_rules();
        update_option('modules_rewrite_flushed', true);
    }
}, 20);

// Allow REST API access for modules post type
add_filter('rest_prepare_modules', function($response, $post, $request) {
    return $response;
}, 10, 3);

// Ensure modules are accessible via REST API
add_filter('rest_modules_query', function($args, $request) {
    return $args;
}, 10, 2);

// Debug: Check if modules post type is registered
add_action('init', function() {
    if (post_type_exists('modules')) {
        error_log('✅ Modules post type exists');
        $post_type_obj = get_post_type_object('modules');
        if ($post_type_obj) {
            error_log('✅ Modules post type object: ' . print_r($post_type_obj, true));
        }
    } else {
        error_log('❌ Modules post type does not exist');
    }
}, 30);

// ===== Taxonomy: brand (for modules) =====
add_action('init', function () {
    register_taxonomy('brand', array('modules'), array(
        'labels' => array(
            'name'          => 'Brands',
            'singular_name' => 'Brand',
            'search_items'  => 'Search Brands',
            'all_items'     => 'All Brands',
            'edit_item'     => 'Edit Brand',
            'update_item'   => 'Update Brand',
            'add_new_item'  => 'Add New Brand',
            'new_item_name' => 'New Brand Name',
            'menu_name'     => 'Brand',
        ),
        'public'            => true,
        'hierarchical'      => true,               // behave like tags; set true if you need brand groups
        'show_admin_column' => true,                // show a column in the posts list table
        'show_in_rest'      => true,                // enable Gutenberg/REST API
        'rewrite'           => array('slug' => 'brand'),
    ));
});

// ===== Enable default "Tags" taxonomy for CPT modules =====
add_action('init', function () {
    register_taxonomy_for_object_type('post_tag', 'modules');
});

// ===== Taxonomy: industry (recommended: a single taxonomy) =====
add_action('init', function () {
    register_taxonomy('industry', array('modules'), array(
        'label'             => 'Industry',
        'hierarchical'      => true,                // set false if you prefer tag-like
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => 'industry', 'hierarchical' => true),
    ));

    // Seed the 4 terms (idempotent: safe to run multiple times)
    $terms = array(
        array('name' => 'Automotive',                 'slug' => 'automotive'),
        array('name' => 'Automotive and Service',     'slug' => 'automotive-and-service'),
        array('name' => 'Healthcare',                 'slug' => 'healthcare'),
        array('name' => 'Healthcare & Insurance',     'slug' => 'healthcare-insurance'),
    );
    foreach ($terms as $t) {
        if (!term_exists($t['name'], 'industry') && !term_exists($t['slug'], 'industry')) {
            wp_insert_term($t['name'], 'industry', array('slug' => $t['slug']));
        }
    }
});




// Enqueue modules query filters script
function gp_enqueue_modules_filters_script() {
    if (is_page() || is_home() || is_front_page()) {
        $script_url = get_stylesheet_directory_uri() . '/js/modules-query-filters.js';
        $version = time(); // Disable cache
        wp_enqueue_script('modules-query-filters', $script_url, array('wp-api-fetch'), $version, true);
    }
}
add_action('wp_enqueue_scripts', 'gp_enqueue_modules_filters_script');

// Modules filters shortcode
function gp_modules_filters_shortcode($atts) {
    $atts = shortcode_atts(array(
        'taxonomy' => 'industry',
        'post_type' => 'modules',
        'per_page' => 8,
        'orderby' => 'date',
        'order' => 'desc',
        'anchor' => 'modules-loop'
    ), $atts);
    
    $form_id = 'modules-filters-' . wp_generate_password(8, false);
    
    ob_start();
    ?>
    <div class="modules-filters-wrapper">
        <form id="<?php echo esc_attr($form_id); ?>" class="modules-filters" 
              data-anchor="<?php echo esc_attr($atts['anchor']); ?>"
              data-taxonomy="<?php echo esc_attr($atts['taxonomy']); ?>"
              data-post-type="<?php echo esc_attr($atts['post_type']); ?>"
              data-per-page="<?php echo esc_attr($atts['per_page']); ?>"
              data-orderby="<?php echo esc_attr($atts['orderby']); ?>"
              data-order="<?php echo esc_attr($atts['order']); ?>"
              data-prefill="<?php echo esc_attr(json_encode(array(
                  'tax_terms' => '',
                  's' => '',
                  'orderby' => '',
                  'order' => ''
              ))); ?>"
              data-brand-taxonomy="brand">
            
            <div class="filter-group">
                <div class="filter-group-header">
                    <h3 class="filter-group-title">Industry</h3>
                    <button type="button" class="clear-button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M9.99935 18.3337C14.5827 18.3337 18.3327 14.5837 18.3327 10.0003C18.3327 5.41699 14.5827 1.66699 9.99935 1.66699C5.41602 1.66699 1.66602 5.41699 1.66602 10.0003C1.66602 14.5837 5.41602 18.3337 9.99935 18.3337Z" stroke="white" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M7.64258 12.3583L12.3592 7.6416" stroke="white" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12.3592 12.3583L7.64258 7.6416" stroke="white" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Clear
                    </button>
                </div>
                <div class="filter-group-content">
                    <?php gp_render_tax_checklist_tree($atts['taxonomy']); ?>
                </div>
            </div>
        </form>
        
        <div class="modules-results-container">
            <h3>All Modules</h3>
            <p class="description">Explore modular AI components available today in the Automotive vertical.</p>
            
            <!-- Loading state (hidden by default) -->
            <div class="loading-container" style="display: none;">
                <div class="loading-spinner"></div>
                <p class="loading-text">Loading modules...</p>
            </div>
            
            <div id="<?php echo esc_attr($atts['anchor']); ?>">
                <!-- Results will be loaded here -->
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('modules_filters', 'gp_modules_filters_shortcode');

// Render taxonomy checklist tree
function gp_render_tax_checklist_tree($taxonomy) {
    $parents = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
        'parent'     => 0,
    ]);
    
    if (is_wp_error($parents) || empty($parents)) {
        echo '<p>No terms found.</p>';
        return;
    }

    echo '<ul class="tax-checklist tax-' . esc_attr($taxonomy) . '">';

    $is_first = true;
    foreach ($parents as $parent) {
        $pid = (int) $parent->term_id;
        echo '<div class="tax-item">';
        
        // Parent term with accordion toggle - first one open, others closed
        $parent_class = $is_first ? 'tax-item-parent' : 'tax-item-parent collapsed';
        echo '<div class="' . $parent_class . '">';
        echo '<span>' . esc_html($parent->name) . '</span>';

        $children = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'parent'     => $pid,
        ]);
        
        if (!is_wp_error($children) && $children) {
            echo '<div class="tax-item-children">';
            foreach ($children as $child) {
                $cid = (int) $child->term_id;
                echo '<div class="tax-item-child">';
                echo '<label><input type="checkbox" name="tax_terms[]" value="' . $cid . '"> ' . esc_html($child->name) . '</label>';
                echo '</div>';
            }
            echo '</div>';
        }

        echo '</div>'; // Close tax-item-parent
        echo '</div>'; // Close tax-item
        $is_first = false;
    }

    echo '</ul>';
}

// Add ACF rating field to REST API response
add_action('rest_api_init', function() {
    register_rest_field('modules', 'acf_rating', array(
        'get_callback' => function($post) {
            $post_id = $post['id'];
            $field = 'rating';
            $max = 5;
            $show_text = true;
            
            // Get rating value from ACF (fallback to post meta if ACF not active)
            if (function_exists('get_field')) {
                $value = get_field($field, $post_id);
            } else {
                $value = get_post_meta($post_id, $field, true);
            }
            
            if ($value === '' || $value === null) return ''; // nothing to show
            
            $value = floatval($value);
            $value = max(0, min($value, $max)); // Clamp rating
            
            $percent = ($value / $max) * 100.0;
            $display_value = rtrim(rtrim(number_format($value, 1), '0'), '.'); // e.g. 3.5
            $label_vis = $display_value . '/<strong>' . intval($max) . '</strong>'; // 3.5/<strong>5</strong>
            $label_aria = sprintf('%s out of %s', $display_value, $max);
            
            // SVG star (color controlled via CSS "currentColor")
            $star_svg = '
<svg xmlns="http://www.w3.org/2000/svg" width="19" height="17" viewBox="0 0 19 17" fill="none" aria-hidden="true" focusable="false">
  <path d="M9.24494 0L11.8641 5.63991L18.0374 6.38809L13.4829 10.6219L14.679 16.7243L9.24494 13.701L3.8109 16.7243L5.00697 10.6219L0.452479 6.38809L6.62573 5.63991L9.24494 0Z" fill="currentColor"/>
</svg>';
            
            ob_start(); ?>
            <span class="acf-rating" role="img" aria-label="<?php echo esc_attr($label_aria); ?>">
              <span class="acf-rating__wrap">
                <span class="acf-rating__bg">
                  <?php for ($i = 0; $i < $max; $i++) echo '<span class="acf-rating__star">'.$star_svg.'</span>'; ?>
                </span>
                <span class="acf-rating__fg" style="width: <?php echo esc_attr($percent); ?>%;">
                  <?php for ($i = 0; $i < $max; $i++) echo '<span class="acf-rating__star">'.$star_svg.'</span>'; ?>
                </span>
              </span>
              
              <?php if ($show_text) : ?>
                <span class="acf-rating__text">
                  <?php echo wp_kses($label_vis, ['strong' => []]); ?>
                </span>
              <?php endif; ?>
            </span>
            
            <?php
            return ob_get_clean();
        },
        'schema' => array(
            'description' => 'ACF rating field with HTML output',
            'type' => 'string'
        )
    ));
});

// Register modules post type
function register_modules_post_type() {
    register_post_type('modules', array(
        'public' => true,
        'show_in_rest' => true, // Enable REST API
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'author'),
        'labels' => array(
            'name' => 'Modules',
            'singular_name' => 'Module',
            'add_new' => 'Add New Module',
            'add_new_item' => 'Add New Module',
            'edit_item' => 'Edit Module',
            'new_item' => 'New Module',
            'view_item' => 'View Module',
            'search_items' => 'Search Modules',
            'not_found' => 'No modules found',
            'not_found_in_trash' => 'No modules found in trash'
        ),
        'menu_icon' => 'dashicons-admin-tools',
        'has_archive' => true,
        'rewrite' => array('slug' => 'modules')
    ));
}
add_action('init', 'register_modules_post_type');
?>