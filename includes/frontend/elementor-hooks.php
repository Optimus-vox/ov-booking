<?php
defined('ABSPATH') || exit;

// Isključi Elementor editor za 'product' post type
add_action('elementor/init', function () {
    add_filter('elementor/editor/active_post_types', function ($types) {
        return array_diff($types, ['product']);
    });

    add_filter('elementor/utils/is_post_type_support', function ($supports, $post_type) {
        return $post_type === 'product' ? false : $supports;
    }, 10, 2);
}, 20);

// Onemogući Elementor frontend output za single product
add_action('wp', function () {
    if (is_singular('product') && class_exists('\Elementor\Plugin')) {
        add_filter('elementor/theme/do_location', function ($do, $location) {
            return in_array($location, ['single', 'single-product'], true) ? false : $do;
        }, PHP_INT_MAX, 2);

        remove_action('wp_enqueue_scripts', [\Elementor\Plugin::instance()->frontend, 'enqueue_scripts'], 20);
        remove_action('wp_print_styles', [\Elementor\Plugin::instance()->frontend, 'enqueue_styles'], 10);
        remove_action('wp_head', [\Elementor\Plugin::instance()->frontend, 'print_google_fonts']);
    }
}, 5);
