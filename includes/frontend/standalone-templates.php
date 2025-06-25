<?php
defined('ABSPATH') || exit;

// Standalone prikaz za Single Product
add_action('template_redirect', function () {
    if (is_singular('product') && !is_admin()) {
        global $post, $product;
        setup_postdata($post);
        $product = wc_get_product($post->ID);

        ?><!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <?php wp_head(); ?>
        </head>
        <body <?php body_class('ov-single-product-body'); ?>>
            <header class="ovb-standalone-header">
                <?php wp_nav_menu([
                    'theme_location' => 'primary',
                    'container' => 'nav',
                    'container_class' => 'ovb-primary-nav',
                    'menu_class' => 'ovb-nav-list',
                    'fallback_cb' => false,
                ]); ?>
            </header>
            <!-- include __DIR__ . '/../templates/woocommerce/ov-single-product.php'; -->
            <?php include OV_BOOKING_PATH . 'templates/woocommerce/ov-single-product.php'; ?>

            <?php wp_footer(); ?>
        </body>
        </html><?php

        wp_reset_postdata();
        exit;
    }
}, 1);

// Standalone prikaz za Cart
add_action('template_redirect', function () {
    if (function_exists('is_cart') && is_cart() && !is_admin()) {
        WC()->cart;
        ?><!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <?php wp_head(); ?>
        </head>
        <body <?php body_class('ov-cart-page-body'); ?>>

        <?php
        if (file_exists(OV_BOOKING_PATH . 'templates/woocommerce/ov-cart.php')) {
            include OV_BOOKING_PATH . 'templates/woocommerce/ov-cart.php';
        }
        ?>

        <?php wp_footer(); ?>
        </body>
        </html><?php

        exit;
    }
}, 1);
