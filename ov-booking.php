<?php
/**
 * Plugin Name:       OV Booking Platform
 * Description:       Optimus Vox booking system with WooCommerce integration. Provides calendar-based booking, product-level pricing, availability, iCal sync, and more.
 * Version:           9.0.0
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

/** Core konstante */
define('OVB_BOOKING_FILE', __FILE__);
define('OVB_BOOKING_PATH', plugin_dir_path(__FILE__));
define('OVB_BOOKING_URL',  plugin_dir_url(__FILE__));

/** (Opc.) flag za blokiranje WP.org update checkova na frontu */
if (!defined('OVB_BLOCK_WPORG_UPDATES')) {
    define('OVB_BLOCK_WPORG_UPDATES', false);
}
if (OVB_BLOCK_WPORG_UPDATES) {
    add_filter('pre_http_request', function ($preempt, $args, $url) {
        if (!is_admin()) {
            $patterns = [
                'api.wordpress.org/plugins/update-check',
                'api.wordpress.org/themes/update-check',
                'api.wordpress.org/core/version-check',
            ];
            foreach ($patterns as $p) {
                if (strpos($url, $p) !== false) {
                    return [
                        'headers'  => [],
                        'body'     => 'false',
                        'response' => ['code' => 200, 'message' => 'OK'],
                    ];
                }
            }
        }
        return $preempt;
    }, 10, 3);
}

/** I18n – DRŽI U `init.php` (ovde brišemo duplikat) */
// load_plugin_textdomain('ov-booking', false, dirname(plugin_basename(__FILE__)) . '/languages');

/** WooCommerce dependency check (koristi se na aktivaciji) */
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

/** Aktivacija */
register_activation_hook(__FILE__, function () {
    flush_rewrite_rules();
    ovb_check_woocommerce_dependency();

    if (function_exists('ovb_force_all_products_to_simple'))  ovb_force_all_products_to_simple();
    if (function_exists('ovb_create_woocommerce_pages'))      ovb_create_woocommerce_pages();
    if (function_exists('ovb_reset_all_product_prices'))      ovb_reset_all_product_prices();
    if (function_exists('disable_woocommerce_shipping_on_activation')) disable_woocommerce_shipping_on_activation();

    if (defined('OVB_DEV_WIPE_ON_ACTIVATE') && OVB_DEV_WIPE_ON_ACTIVATE) {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ovb_%' OR option_name LIKE '_site_transient_ovb_%'");
    }
});

/** Deaktivacija */
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
    // primer: očisti Woo cron
    wp_clear_scheduled_hook('woocommerce_cancel_unpaid_orders');
});

/** Učitaj glavni init (on unutra vuče sve ostale fajlove modularno) */
require_once OVB_BOOKING_PATH . 'includes/init.php';
// Aktivacija
register_activation_hook(__FILE__, function () {
    // 1) Rewrites
    flush_rewrite_rules();

    // 2) Woo dependency (ako imaš ovb_check_woocommerce_dependency, pozovi je; inače preskoči)
    if (function_exists('ovb_check_woocommerce_dependency')) {
        ovb_check_woocommerce_dependency();
    }

    // 3) Kreiraj uploads/ovb-booking/ folder
    $upload = wp_upload_dir();
    if (!empty($upload['basedir'])) {
        $dir = trailingslashit($upload['basedir']) . 'ovb-booking';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
    }

    // 4) (opciono) inicijalni setup ako te funkcije postoje
    if (function_exists('ovb_force_all_products_to_simple'))  ovb_force_all_products_to_simple();
    if (function_exists('ovb_create_woocommerce_pages'))      ovb_create_woocommerce_pages();
    if (function_exists('ovb_reset_all_product_prices'))      ovb_reset_all_product_prices();
    if (function_exists('disable_woocommerce_shipping_on_activation')) disable_woocommerce_shipping_on_activation();

    // 5) Log (samo ako je helper već tu)
    if (function_exists('ovb_handle_error')) {
        ovb_handle_error('OV Booking activated', 'lifecycle');
    }
});

// Deaktivacija
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
    // (opciono) očisti cron ili svoje schedulere
    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook('woocommerce_cancel_unpaid_orders'); // ako si ga ikad menjao/planirao
    }
    if (function_exists('ovb_handle_error')) {
        ovb_handle_error('OV Booking deactivated', 'lifecycle');
    }
});