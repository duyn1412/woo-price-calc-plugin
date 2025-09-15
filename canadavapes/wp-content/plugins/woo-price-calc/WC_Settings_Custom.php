<?php
if ( ! class_exists( 'WC_Settings_Custom' ) ) :

class WC_Settings_Custom extends WC_Settings_Page {

    public function __construct() {
        $this->id    = 'custom_settings';
        $this->label = __( 'Province settings', 'woocommerce' );

        add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
        add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
        add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
        add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
    }

    public function get_sections() {
        $sections = array(
            ''         => __( 'Province Taxes', 'woocommerce' ),
            'section1' => __( 'Product Visibility', 'woocommerce' ),
            //'section2' => __( 'Section 2', 'woocommerce' )
        );

        return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
    }

    public function get_settings( $current_section = '' ) {
        $settings = array();

        switch ( $current_section ) {
           
            case 'section1':
                $settings = array(
                    array(
                        'title' => __( 'Product Visibility', 'woocommerce' ),
                        'type'  => 'title',
                        'id'    => 'section2_options'
                    ),
                   
                    
                );
                $provinces = $this->wc_get_province_args();
                $categories_array = $this->d_get_product_categories('slug');
                $product_args = $this->d_wc_get_product_dropdown_options();
                foreach ($provinces as $code => $name) {
                   
            
                    $settings[] = array(
                        'title'   => __($name, 'woocommerce'),
                        'desc'    => __('Select product to hide for ' . $name, 'woocommerce'),
                        'id'      => 'wc_province_hide_product_' . $code,
                        'type'    => 'multiselect',
                        'class'   => 'wc-enhanced-select',
                        'options' => $product_args,
                        'default' => '',
                    );
                    $settings[] = array(
                       // 'title'   => __('Product Category for ' . $name, 'woocommerce'),
                        'desc'    => __('Select category to hide for ' . $name, 'woocommerce'),
                        'id'      => 'wc_province_product_hide_category_' . $code,
                        'type'    => 'multiselect',
                        'class'   => 'wc-enhanced-select',
                        'options' => $categories_array,
                        'default' => '',
                    );
                    
            
                }
            
                $settings[] = array('type' => 'sectionend', 'id' => 'woocommerce_province_tax_rates_settings_end');
   
                
                break;

            default:
                $categories_array = $this->d_get_product_categories();
                $settings = array(
                    array(
                        'title' => __( 'Taxable Categories Settings', 'woocommerce' ),
                        'type' => 'title',
                        'desc' => __( 'This section allows you to select the categories for which tax should be applied.', 'woocommerce' ),
                        'id'   => 'woocommerce_taxable_categories_settings'
                    ),
                    array(
                        'title'    => __( 'Taxable Categories', 'woocommerce' ),
                        'desc'     => __( 'Select the categories for which tax should be applied.', 'woocommerce' ),
                        'id'       => 'woocommerce_taxable_categories',
                        'default'  => '',
                        'type'     => 'multiselect',
                        'class'    => 'wc-enhanced-select',
                        'css'      => 'min-width:300px;',
                        'desc_tip' => true,
                        'options'  => $categories_array,
                    ),
                    array('type' => 'sectionend', 'id' => 'woocommerce_taxable_categories_settings_end'),
                   
                    array(
                        'title' => __( 'Province Tax Rates Settings', 'woocommerce' ),
                        'type' => 'title',
                        'desc' => __( 'This section allows you to set the tax rates for each province.', 'woocommerce' ),
                        'id'   => 'woocommerce_province_tax_rates_settings'
                    ),
                    array(
                        'title'    => __( 'Calculation Type', 'woocommerce' ),
                        'desc'     => __( 'Select whether the tax rates are percentages or fixed amounts.', 'woocommerce' ),
                        'id'       => 'wc_tax_calculation_type',
                        'default'  => 'percentage',
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'desc_tip' => true,
                        'options'  => array(
                            'percentage' => __( 'Percentage', 'woocommerce' ),
                            'fixed'      => __( 'Fixed Amount', 'woocommerce' ),
                        ),
                    ),
                 );


                $provinces = $this->wc_get_province_args();
                foreach ($provinces as $code => $name) {
                    $settings[] = array(
                        'title'    => $name,
                        'desc'     => __( 'Enter the tax rate for ' . $name . '.', 'woocommerce' ),
                        'id'       => 'wc_tax_rate_' . $code,
                        'default'  => '',
                        'type'     => 'text',
                        'desc_tip' => true,
                    );                  
            
                }
            
                $settings[] = array('type' => 'sectionend', 'id' => 'woocommerce_province_tax_rates_settings_end');
                $categories_array = WC()->countries->get_states('CA');
                $settings[] = array(
                    'title' => __( 'Size Tax Rates Settings', 'woocommerce' ),
                    'type' => 'title',
                    'desc' => __( 'This section allows you to set the tax rates for each size and they would apply to provinces', 'woocommerce' ),
                    'id'   => 'woocommerce_province_tax_rates_settings'
                );

                $settings[] = array(
                    'title'    => __( 'Taxable Provinces', 'woocommerce' ),
                    'desc'     => __( 'Select the provinces for which tax should be applied.', 'woocommerce' ),
                    'id'       => 'woocommerce_taxable_provinces',
                    'default'  => '',
                    'type'     => 'multiselect',
                    'class'    => 'wc-enhanced-select',
                    'css'      => 'min-width:300px;',
                    'desc_tip' => true,
                    'options'  => $this->wc_get_province_args(),
                );

                $settings[] = array(
                    'title'    => __( 'Calculation Type', 'woocommerce' ),
                    'desc'     => __( 'Select whether the tax rates are percentages or fixed amounts.', 'woocommerce' ),
                    'id'       => 'wc_tax_calculation_size_type',
                    'default'  => 'fixed',
                    'type'     => 'select',
                    'class'    => 'wc-enhanced-select',
                    'desc_tip' => true,
                    'options'  => array(
                        'percentage' => __( 'Percentage', 'woocommerce' ),
                        'fixed'      => __( 'Fixed Amount', 'woocommerce' ),
                    ),
                );

                $settings[] = array(
                    'title'    => __( '60ml Tax', 'woocommerce' ),
                    'desc'     => __( 'Enter the tax amount for 60ml size', 'woocommerce' ),
                    'id'       => 'woocommerce_taxable_categories_60ml',
                    'default'  => '0',
                    'type'     => 'number',
                    'desc_tip' => true,
                );

                $settings[] = array(
                    'title'    => __( '120ml Tax', 'woocommerce' ),
                    'desc'     => __( 'Enter the tax amount for 120ml size', 'woocommerce' ),
                    'id'       => 'woocommerce_taxable_categories_120ml',
                    'default'  => '0',
                    'type'     => 'number',
                    'desc_tip' => true,
                );

                $settings[] = array('type' => 'sectionend', 'id' => 'woocommerce_province_tax_rates_settings_end');
               
                break;
        }

        return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
    }

    public function save() {
        global $current_section;
        $settings = $this->get_settings( $current_section );
        WC_Admin_Settings::save_fields( $settings );
    }
   
    public function d_get_product_categories($key_type = 'id') {
        $categories = get_terms( 'product_cat', array('hide_empty' => false) );
        $categories_array = array();
    
        if ( ! is_wp_error( $categories ) ) {
            foreach ( $categories as $category ) {
                $key = ($key_type == 'slug') ? $category->slug : $category->term_id;
                $categories_array[$key] = $category->name;
            }
        }
    
        return $categories_array;
    }

    public function d_wc_get_product_dropdown_options() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
        );

        $products = get_posts($args);
        $options = array();

        if (!empty($products)) {
            foreach ($products as $product) {
                $options[$product->ID] = $product->post_title;
            }
        }

        return $options;
    }
    public function wc_get_province_args(){
        $provinces = array(
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
            'YT' => 'Yukon'
        );

        return $provinces;
    }
    
    
}

endif;

