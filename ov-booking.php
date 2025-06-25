<?php
/**
 * Plugin Name:       OV Booking Platform
 * Description:       Optimus Vox booking system with WooCommerce integration. Provides calendar-based booking, product-level pricing, availability, iCal sync, and more.
 * Version:           1.0.0
 * Author:            Optimus Vox
 * Author URI:        https://optimusvox.com
 * Requires Plugins:  woocommerce
 * Requires at least: 5.9
 * Tested up to:      6.5
 * Requires PHP:      7.4
 * Text Domain:       ov-booking
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

// Define plugin paths
define('OV_BOOKING_PATH', plugin_dir_path(__FILE__));
define('OV_BOOKING_URL', plugin_dir_url(__FILE__));

// Load plugin translations
load_plugin_textdomain('ov-booking', false, dirname(plugin_basename(__FILE__)) . '/languages');

/**
 * WooCommerce dependency check on activation
 */
function ovb_check_woocommerce_dependency() {
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('❌ WooCommerce must be installed and active to use OV Booking.', 'ov-booking'),
            __('Plugin dependency check failed', 'ov-booking'),
            ['back_link' => true]
        );
    }
}

// Activation logic
register_activation_hook(__FILE__, function () {
    ovb_check_woocommerce_dependency();
    if (function_exists('ovb_force_all_products_to_simple')) {
        ovb_force_all_products_to_simple();
    }
    if (function_exists('ovb_create_woocommerce_pages')) {
        ovb_create_woocommerce_pages();
    }
    if (function_exists('ovb_reset_all_product_prices')) {
        ovb_reset_all_product_prices();
    }
    if (function_exists('disable_woocommerce_shipping_on_activation')) {
        disable_woocommerce_shipping_on_activation();
    }
});

// Load plugin logic
require_once OV_BOOKING_PATH . 'includes/init.php';
