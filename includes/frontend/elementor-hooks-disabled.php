<?php
defined('ABSPATH') || exit;

/**
 * =======================================
 * ELEMENTOR KONTROL ZA OV BOOKING STRANICE
 * =======================================
 */

// Inicijalizacija Elementor kontrole
add_action('init', 'ovb_init_elementor_control', 1);
function ovb_init_elementor_control() {
    if (!class_exists('\Elementor\Plugin')) {
        return;
    }
    
    // Dodaj hook za kontrolu Elementor skriptova/stilova
    add_action('wp', 'ovb_control_elementor_assets', 5);
    add_action('elementor/init', 'ovb_disable_elementor_for_products', 20);
}

/**
 * ONEMOGUĆI ELEMENTOR EDITOR ZA PRODUCT POST TYPE
 */
function ovb_disable_elementor_for_products() {
    // Ukloni product iz supported post types
    add_filter('elementor/editor/active_post_types', function ($types) {
        return array_diff($types, ['product']);
    });

    // Onemogući post type support
    add_filter('elementor/utils/is_post_type_support', function ($supports, $post_type) {
        return $post_type === 'product' ? false : $supports;
    }, 10, 2);
}

/**
 * KOMPLETNO ONEMOGUĆAVANJE ELEMENTOR-a
 */
function ovb_disable_elementor_completely() {
    $elementor = \Elementor\Plugin::instance();
    
    // Ukloni theme locations
    add_filter('elementor/theme/do_location', function ($do, $location) {
        $restricted_locations = ['single', 'single-product', 'archive', 'checkout', 'cart'];
        return in_array($location, $restricted_locations, true) ? false : $do;
    }, PHP_INT_MAX, 2);
    
    // Onemogući frontend assets
    remove_action('wp_enqueue_scripts', [$elementor->frontend, 'enqueue_scripts'], 20);
    remove_action('wp_print_styles', [$elementor->frontend, 'enqueue_styles'], 10);
    remove_action('wp_head', [$elementor->frontend, 'print_google_fonts']);
    
    // Ukloni dodatne Elementor hooks
    remove_action('wp_enqueue_scripts', [$elementor->frontend, 'enqueue_frontend_scripts']);
    remove_action('wp_footer', [$elementor->frontend, 'wp_footer']);
    
    // Onemogući Elementor widgets
    add_filter('elementor/widgets/is_widget_supported', '__return_false');
        
    // Onemogući lazy loading conflicts
    remove_action('wp_head', [$elementor->frontend, 'print_head_attributes']);
    
    // Ukloni Elementor body classes
    add_filter('body_class', function($classes) {
        return array_filter($classes, function($class) {
            return strpos($class, 'elementor') === false;
        });
    }, 999);
}


