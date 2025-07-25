<?php
defined('ABSPATH') || exit;

// Enable logging during development
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
}


/** ----------------------------------------
 *  CORE 
 * ---------------------------------------- */
// require_once __DIR__ . '/../vendor/autoload.php';

//test
$autoload_path = plugin_dir_path(__FILE__) . '../vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>OV Booking:</strong> Missing dependencies. Please install via <code>composer install</code> before uploading plugin.</p></div>';
    });
}
//test

require_once __DIR__ . '/admin/class-ical-meta.php';
require_once OV_BOOKING_PATH . 'includes/admin/class-oauth-settings.php';
require_once OV_BOOKING_PATH . 'includes/oauth/callback-handler.php';
require_once OV_BOOKING_PATH . 'includes/admin/notices.php';

/** -----------------------------
 *  FRONTEND 
 * ----------------------------- */
require_once __DIR__ . '/frontend/scripts.php';
require_once __DIR__ . '/frontend/remove-wrappers.php';
require_once __DIR__ . '/frontend/checkout-mods.php';
require_once __DIR__ . '/frontend/body-classes.php';
require_once __DIR__ . '/frontend/myaccount-template-override.php';

require_once __DIR__ . '/frontend/shortcodes.php';
require_once __DIR__ . '/frontend/google-login.php';
require_once __DIR__ . '/frontend/template-hooks.php';
require_once __DIR__ . '/frontend/account-hooks.php';
require_once __DIR__ . '/frontend/cart-hooks.php';
require_once __DIR__ . '/frontend/order-hooks.php';
require_once __DIR__ . '/frontend/standalone-templates.php';
require_once __DIR__ . '/frontend/elementor-hooks.php';
require_once __DIR__ . '/frontend/order-meta-display.php';

/** -----------------------------
 *  ICAL MODUL
 * ----------------------------- */
require_once __DIR__ . '/class-ical-service.php';
require_once __DIR__ . '/ical/ical-init.php';

/** -----------------------------
 *  ADMIN - BACKEND
 * ----------------------------- */
if (is_admin()) require_once __DIR__ . '/admin/custom-admin-dash.php';

require_once __DIR__ . '/admin/product-hooks.php';
require_once __DIR__ . '/admin/settings.php';
require_once __DIR__ . '/admin/metabox-cleanup.php';

require_once __DIR__ . '/admin/editor-description.php';
require_once __DIR__ . '/admin/excerpt.php';
require_once __DIR__ . '/admin/google-maps.php';
require_once __DIR__ . '/admin/metabox-apartment-info.php';
require_once __DIR__ . '/admin/metabox-apartment-rules.php';
require_once __DIR__ . '/admin/testimonials.php';
require_once __DIR__ . '/admin/admin-calendar/admin-calendar-ajax.php';
require_once __DIR__ . '/admin/admin-calendar/admin-calendar.php';

/** -----------------------------
 *  HELPERS AND LOGGING
 * ----------------------------- */
require_once __DIR__ . '/helpers/helpers.php';
if(file_exists(__DIR__ . '/helpers/logger.php')){
    require_once __DIR__ . '/helpers/logger.php';
}


add_action('wp_enqueue_scripts', 'ovb_enqueue_checkout_scripts');
function ovb_enqueue_checkout_scripts() {
    if (is_checkout()) {
        // Osigurajte da su WooCommerce checkout skriptovi učitani
        wp_enqueue_script('wc-checkout');
        wp_enqueue_script('wc-stripe');
        
        // Lokalizacija za checkout
        wp_localize_script('wc-checkout', 'wc_checkout_params', array(
            'ajax_url'                => admin_url('admin-ajax.php'),
            'wc_ajax_url'            => WC_AJAX::get_endpoint('%%endpoint%%'),
            'update_order_review_nonce' => wp_create_nonce('update-order-review'),
            'apply_coupon_nonce'     => wp_create_nonce('apply-coupon'),
            'remove_coupon_nonce'    => wp_create_nonce('remove-coupon'),
            'option_guest_checkout'  => get_option('woocommerce_enable_guest_checkout'),
            'checkout_url'           => wc_get_checkout_url(),
            'is_checkout'            => 1,
            'debug_mode'             => defined('WP_DEBUG') && WP_DEBUG,
        ));
    }
}