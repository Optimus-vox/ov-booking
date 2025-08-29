<?php
defined('ABSPATH') || exit;

add_filter('body_class', function($classes){
    $theme = wp_get_theme();
    $is_astra = stripos($theme->get('Name'), 'astra') !== false;

    if (is_singular('product') && $is_astra) {
        $classes = array_filter($classes, fn($c) => !preg_match('/^(ast-|woocommerce)/', $c));
    }

    // Dodaj custom OVB klase za glavne stranice
    if (is_singular('product'))            $classes[] = 'ob-booking-active ovb-single-product';
    if (is_cart())                          $classes[] = 'ob-booking-active ovb-cart-page';
    if (is_checkout() && !is_order_received_page()) $classes[] = 'ob-booking-active ovb-checkout-page';
    if (is_account_page())                 $classes[] = 'ob-booking-active ovb-my-account-page';
    if (is_order_received_page())          $classes[] = 'ob-booking-active ovb-thank-you-page';

    return $classes;
});

add_filter('post_class', function($classes){
    $theme = wp_get_theme();
    $is_astra = stripos($theme->get('Name'), 'astra') !== false;

    if (is_singular('product') && $is_astra) {
        $classes = array_filter($classes, fn($c) => !preg_match('/^(ast-|woocommerce)/', $c));
    }

    return $classes;
}, 20, 2);