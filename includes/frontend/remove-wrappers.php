<?php
defined('ABSPATH') || exit;

add_action('init', function() {
    remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
    remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
    remove_action('astra_primary_content_top',        'astra_primary_content_top_markup');
    remove_action('astra_primary_content_bottom',     'astra_primary_content_bottom_markup');
    remove_action('woocommerce_before_main_content', 'astra_woocommerce_output_content_wrapper', 10);
    remove_action('woocommerce_after_main_content',  'astra_woocommerce_output_content_wrapper_end', 10);



   
    $theme = wp_get_theme();
    $is_astra = stripos($theme->get('Name'), 'astra') !== false;

    if ($is_astra) {
    
        remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
        remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
        remove_action('astra_primary_content_top',        'astra_primary_content_top_markup');
        remove_action('astra_primary_content_bottom',     'astra_primary_content_bottom_markup');
        remove_action('woocommerce_before_main_content', 'astra_woocommerce_output_content_wrapper', 10);
        remove_action('woocommerce_after_main_content',  'astra_woocommerce_output_content_wrapper_end', 10);
    }

    if (defined('ELEMENTOR_VERSION')) {
       // elementor remove wrapper hooks 
    }


}, 1);
