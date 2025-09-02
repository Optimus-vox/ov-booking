<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Fallback konstante (primarno ih definiši u glavnom fajlu).
 */
if (!defined('OVB_BOOKING_PATH')) {
    define('OVB_BOOKING_PATH', plugin_dir_path(__FILE__));
}
if (!defined('OVB_BOOKING_FILE')) {
    // fallback: pretpostavi da je glavni fajl u korenu plugina
    define('OVB_BOOKING_FILE', dirname(__DIR__) . '/ov-booking.php');
}

/**
 * PHP verzija
 */
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>OV Booking:</strong> Requires PHP 7.4 or higher.</p></div>';
    });
    return;
}

/**
 * I18n
 * (koristi root /languages, ne includes/languages)
 */
add_action('init', function () {
    load_plugin_textdomain(
        'ov-booking',
        false,
        dirname(plugin_basename(OVB_BOOKING_FILE)) . '/languages'
    );
});

/**
 * WooCommerce dependency
 */
add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>OV Booking:</strong> WooCommerce plugin must be active.</p></div>';
        });
        define('OVB_WC_MISSING', true);
    }
}, 1);

/**
 * WP_DEBUG logging
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    // Ako želiš custom fajl, obezbedi da folder postoji; u suprotnom koristi WP default debug.log
    // ini_set('error_log', OVB_BOOKING_PATH . '../logs/php-errors.log');
}

/**
 * Composer autoload
 */
$autoload_path = OVB_BOOKING_PATH . 'vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>OV Booking:</strong> Missing dependencies. Run <code>composer install</code>.</p></div>';
    });
}

/**
 * Ako nema WooCommerce-a, prekini dalje učitavanje modula
 */
if (defined('OVB_WC_MISSING') && OVB_WC_MISSING) {
    return;
}

/**
 * Core moduli (redosled: helpers → services → admin notice utili)
 */
$core_files = [
    OVB_BOOKING_PATH . 'includes/helpers/flat-metas-sync.php',
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

/**
 * Frontend moduli
 */
if (!is_admin() || wp_doing_ajax()) {
    $frontend_files = [
        OVB_BOOKING_PATH . 'includes/frontend/scripts.php',
        OVB_BOOKING_PATH . 'includes/frontend/template-hooks.php',
        // OVB_BOOKING_PATH . 'includes/frontend/standalone-templates.php',

        OVB_BOOKING_PATH . 'includes/frontend/checkout-mods.php',
        OVB_BOOKING_PATH . 'includes/frontend/cart-hooks.php',
        OVB_BOOKING_PATH . 'includes/frontend/order-hooks.php',
        OVB_BOOKING_PATH . 'includes/frontend/shortcodes.php',
        OVB_BOOKING_PATH . 'includes/frontend/google-login.php',
        OVB_BOOKING_PATH . 'includes/frontend/account-hooks.php',
        OVB_BOOKING_PATH . 'includes/frontend/body-classes.php',
        OVB_BOOKING_PATH . 'includes/frontend/myaccount-template-override.php',
        OVB_BOOKING_PATH . 'includes/frontend/excerpt.php',

        // Woo optimizacije (trenutno ovde)
        OVB_BOOKING_PATH . 'includes/ovb-woocommerce-optimizations.php',

        // Emails i shortcodes
        OVB_BOOKING_PATH . 'includes/frontend/emails.php',
        OVB_BOOKING_PATH . 'includes/frontend/shortcodes-apartments.php',

        // Shop filteri (uskladiti sa stvarnom putanjom)
        OVB_BOOKING_PATH . 'includes/frontend/filters-catalog.php',
    ];

    // Ako želiš Elementor Manager:
    // if (class_exists('\Elementor\Plugin')) {
    //     require_once OVB_BOOKING_PATH . 'includes/frontend/ovb-unified-elementor-manager.php';
    // }

    foreach ($frontend_files as $file) {
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

/**
 * Admin moduli
 */
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

        // (ostavljeno kao u tvojoj verziji; duplikat nije štetan zbog require_once)
        OVB_BOOKING_PATH . 'includes/frontend/order-hooks.php',
        OVB_BOOKING_PATH . 'includes/frontend/order-meta-display.php',
    ];
    foreach ($admin_files as $file) {
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

/**
 * iCal init (ako modul postoji)
 */
if (file_exists(OVB_BOOKING_PATH . 'includes/ical/ical-init.php')) {
    require_once OVB_BOOKING_PATH . 'includes/ical/ical-init.php';
}

/**
 * Checkout skriptovi + lokalizacija
 */
add_action('wp_enqueue_scripts', 'ovb_enqueue_checkout_scripts', 20);
function ovb_enqueue_checkout_scripts(): void {
    if (!function_exists('is_checkout') || !is_checkout()) return;

    $checkout_scripts = [
        'wc-checkout',
        'wc-stripe',
        'wc-payment-form',
        'wc-cart-fragments',
    ];
    foreach ($checkout_scripts as $handle) {
        wp_enqueue_script($handle);
    }

    if (wp_script_is('wc-checkout', 'enqueued')) {
        wp_localize_script('wc-checkout', 'ovb_wc_checkout_params', array_merge(
            [
                'ajax_url'     => admin_url('admin-ajax.php'),
                'wc_ajax_url'  => WC_AJAX::get_endpoint('%%endpoint%%'),
                'checkout_url' => wc_get_checkout_url(),
                'is_checkout'  => 1,
                'debug_mode'   => (int) (defined('WP_DEBUG') && WP_DEBUG),
            ],
            [
                'update_order_review_nonce' => wp_create_nonce('update-order-review'),
                'apply_coupon_nonce'        => wp_create_nonce('apply-coupon'),
                'remove_coupon_nonce'       => wp_create_nonce('remove-coupon'),
                'option_guest_checkout'     => get_option('woocommerce_enable_guest_checkout'),
            ]
        ));
    }
}

/**
 * VARIJANTA A: samo cache za gateways (nema gašenja WP update checkova)
 */
add_action('init', function () {
    add_filter('woocommerce_available_payment_gateways', function ($gateways) {
        static $cached = null;
        if ($cached !== null) return $cached;
        return $cached = $gateways;
    }, 99);
}, 1);

/**
 * Globalni error handler helper
 */
function ovb_handle_error($message, $context = 'general'): void {
    if (function_exists('ovb_log_error')) {
        ovb_log_error($message, $context);
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("OVB Error [{$context}]: {$message}");
    }
}

/**
 * Sakrij "Edit with Elementor" link na product post type (admin)
 */
if (is_admin()) {
    add_filter('elementor/utils/show_edit_link', function ($show) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type === 'product') {
            return false;
        }
        return $show;
    }, 20);
}



if (defined('WP_DEBUG') && WP_DEBUG) {
    require_once OVB_BOOKING_PATH  . '/ovb-debug-inspector/ovb-debug-inspector.php';
}