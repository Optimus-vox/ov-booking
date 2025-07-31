<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

// Define plugin path if not already set
if (!defined('OVB_BOOKING_PATH')) {
    define('OVB_BOOKING_PATH', plugin_dir_path(__FILE__));
}

// Require PHP 7.4+
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>OV Booking:</strong> Requires PHP 7.4 or higher.</p></div>';
    });
    return;
}

// I18n: Load plugin textdomain
add_action('init', function() {
    load_plugin_textdomain('ov-booking', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Check if WooCommerce is active
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>OV Booking:</strong> WooCommerce plugin must be active.</p></div>';
        });
        return;
    }
}, 1);

/**
 * Enhanced error logging for development
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    ini_set('error_log', OVB_BOOKING_PATH . '../logs/php-errors.log');
}

/**
 * Autoloader with proper error handling
 */
$autoload_path = OVB_BOOKING_PATH . 'vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>OV Booking:</strong> Missing dependencies. Please install via <code>composer install</code> before uploading plugin.</p></div>';
    });
}

/**
 * Load core files in dependency order
 */
$core_files = [
    OVB_BOOKING_PATH . 'includes/helpers/helpers.php',
    OVB_BOOKING_PATH . 'includes/helpers/logger.php',
    OVB_BOOKING_PATH . 'includes/class-ical-service.php',
    OVB_BOOKING_PATH . 'includes/admin/class-ical-meta.php',
    OVB_BOOKING_PATH . 'includes/admin/class-oauth-settings.php',
    OVB_BOOKING_PATH . 'includes/oauth/callback-handler.php',
    OVB_BOOKING_PATH . 'includes/admin/notices.php',
];
foreach ($core_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

// Frontend components
if (!is_admin() || wp_doing_ajax()) {
    $frontend_files = [
        OVB_BOOKING_PATH . 'includes/frontend/scripts.php',
        OVB_BOOKING_PATH . 'includes/frontend/template-hooks.php',
        OVB_BOOKING_PATH . 'includes/frontend/standalone-templates.php',
        OVB_BOOKING_PATH . 'includes/frontend/checkout-mods.php',
        OVB_BOOKING_PATH . 'includes/frontend/cart-hooks.php',
        OVB_BOOKING_PATH . 'includes/frontend/order-hooks.php',
        OVB_BOOKING_PATH . 'includes/frontend/shortcodes.php',
        OVB_BOOKING_PATH . 'includes/frontend/google-login.php',
        OVB_BOOKING_PATH . 'includes/frontend/account-hooks.php',
        OVB_BOOKING_PATH . 'includes/frontend/remove-wrappers.php',
        OVB_BOOKING_PATH . 'includes/frontend/body-classes.php',
        OVB_BOOKING_PATH . 'includes/frontend/myaccount-template-override.php',
        OVB_BOOKING_PATH . 'includes/frontend/elementor-hooks.php',
        OVB_BOOKING_PATH . 'includes/frontend/order-meta-display.php',
        OVB_BOOKING_PATH . 'includes/frontend/excerpt.php', 
    ];
    foreach ($frontend_files as $file) {
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

// Admin components
if (is_admin()) {
    $admin_files = [ 
        OVB_BOOKING_PATH . 'includes/admin/admin-scripts.php',
        OVB_BOOKING_PATH . 'includes/admin/custom-admin-dash.php',
        OVB_BOOKING_PATH . 'includes/admin/product-hooks.php',
        OVB_BOOKING_PATH . 'includes/admin/settings.php',
        OVB_BOOKING_PATH . 'includes/admin/metabox-cleanup.php',
        OVB_BOOKING_PATH . 'includes/admin/editor-description.php',
        OVB_BOOKING_PATH . 'includes/admin/google-maps.php',
        OVB_BOOKING_PATH . 'includes/admin/metabox-apartment-info.php',
        OVB_BOOKING_PATH . 'includes/admin/metabox-apartment-rules.php',
        OVB_BOOKING_PATH . 'includes/admin/testimonials.php',
        OVB_BOOKING_PATH . 'includes/admin/admin-calendar/admin-calendar-ajax.php',
        OVB_BOOKING_PATH . 'includes/admin/admin-calendar/admin-calendar.php',
    ];
    foreach ($admin_files as $file) {
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

// iCal module
require_once OVB_BOOKING_PATH . 'includes/ical/ical-init.php';

/**
 * Enhanced checkout script handling
 */
add_action('wp_enqueue_scripts', 'ovb_enqueue_checkout_scripts', 20);
function ovb_enqueue_checkout_scripts() {
    if (!is_checkout()) return;
    $checkout_scripts = [
        'wc-checkout',
        'wc-stripe',
        'wc-payment-form',
        'wc-cart-fragments'
    ];
    foreach ($checkout_scripts as $script) {
        wp_enqueue_script($script);
    }
    wp_localize_script('wc-checkout', 'ovb_wc_checkout_params', array_merge(
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'wc_ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
            'checkout_url' => wc_get_checkout_url(),
            'is_checkout' => 1,
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
        ],
        [
            'update_order_review_nonce' => wp_create_nonce('update-order-review'),
            'apply_coupon_nonce' => wp_create_nonce('apply-coupon'),
            'remove_coupon_nonce' => wp_create_nonce('remove-coupon'),
            'option_guest_checkout' => get_option('woocommerce_enable_guest_checkout'),
        ]
    ));
}

/**
 * Performance optimization hooks
 */
add_action('init', 'ovb_performance_optimizations', 1);
function ovb_performance_optimizations() {
    if (!is_admin()) {
        add_filter('pre_site_transient_update_core', '__return_null');
        add_filter('pre_site_transient_update_plugins', '__return_null');
        add_filter('pre_site_transient_update_themes', '__return_null');
        remove_action('init', 'wp_version_check');
        remove_action('init', 'wp_update_plugins');
        remove_action('init', 'wp_update_themes');
    }
    add_filter('woocommerce_available_payment_gateways', function($gateways) {
        static $cached_gateways = null;
        if ($cached_gateways !== null) return $cached_gateways;
        return $cached_gateways = $gateways;
    }, 99);
}

/**
 * Global error handler for OV Booking
 */
function ovb_handle_error($message, $context = 'general') {
    if (function_exists('ovb_log_error')) {
        ovb_log_error($message, $context);
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("OVB Error [{$context}]: {$message}");
    }
}

/**
 * Plugin activation/deactivation hooks
 */
register_activation_hook(__FILE__, 'ovb_activation_handler');
function ovb_activation_handler() {
    flush_rewrite_rules();
    $upload_dir = wp_upload_dir();
    $ovb_dir = $upload_dir['basedir'] . '/ovb-booking/';
    if (!file_exists($ovb_dir)) {
        wp_mkdir_p($ovb_dir);
    }
    ovb_handle_error('OV Booking plugin activated successfully');
}

register_deactivation_hook(__FILE__, 'ovb_deactivation_handler');
function ovb_deactivation_handler() {
    flush_rewrite_rules();
    ovb_handle_error('OV Booking plugin deactivated');
}
