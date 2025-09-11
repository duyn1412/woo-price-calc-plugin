<?php
/**
 * GeneratePress Child Theme Functions
 */

// Enqueue child theme styles
function gp_child_enqueue_styles() {
    wp_enqueue_style('generatepress-child', get_stylesheet_directory_uri() . '/style.css', array('generatepress'), '1.0.0');
}
add_action('wp_enqueue_scripts', 'gp_child_enqueue_styles');

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
              ))); ?>">
            
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
?>