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


// Blokiraj WP.org update-check zahteve na frontend-u 
add_filter( 'pre_http_request', 'ovb_block_wporg_update_checks', 10, 3 );
function ovb_block_wporg_update_checks( $preempt, $args, $url ) {
    // samo na javnom delu sajta
    if ( ! is_admin() ) {
        // update-check endpointi
        $patterns = [
            'api.wordpress.org/plugins/update-check',
            'api.wordpress.org/themes/update-check',
            'api.wordpress.org/core/version-check',
        ];
        foreach ( $patterns as $p ) {
            if ( strpos( $url, $p ) !== false ) {
                return [
                    'headers'  => [],
                    'body'     => 'false',
                    'response' => [ 'code' => 200, 'message' => 'OK' ],
                ];
            }
        }
    }
    return $preempt;
}

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

//test
/**
 * Očisti WooCommerce cron task prilikom deaktivacije plugina
 */
register_deactivation_hook( __FILE__, 'ovb_plugin_deactivate' );
function ovb_plugin_deactivate() {
    wp_clear_scheduled_hook( 'woocommerce_cancel_unpaid_orders' );
}
//test

// Load plugin logic
require_once OV_BOOKING_PATH . 'includes/init.php';