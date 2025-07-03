<?php
defined('ABSPATH') || exit;
require_once dirname(__DIR__) . '/helpers/logger.php';

//Register dashicons
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('dashicons');
});

/**
 * Frontend main assets
 */
function ov_enqueue_public_assets()
{
    wp_enqueue_style('ov-date-range-picker-css', plugin_dir_url(__FILE__) . '../../assets/utils/css/ov-date.range.css');
    wp_enqueue_script('ov-date-range-picker-js', plugin_dir_url(__FILE__) . '../../assets/utils/js/ov-date.range.js', [], null, true);
    // Owl Carousel CSS
    wp_enqueue_style('owl-carousel-style', plugin_dir_url(__FILE__) . '../../assets/utils/css/owl.carousel.min.css');
    wp_enqueue_style('owl-carousel-theme', plugin_dir_url(__FILE__) . '../../assets/utils/css/owl.theme.default.min.css');
    wp_enqueue_script('owl-carousel-js', plugin_dir_url(__FILE__) . '../../assets/utils/js/owl.carousel.min.js', array(), '', true);

    // Tvoj glavni CSS i JS fajlovi
    wp_enqueue_style('ov-slider-css', plugin_dir_url(__FILE__) . '../../assets/utils/css/ov.slider.css');
    wp_enqueue_script('ov-slider-js', plugin_dir_url(__FILE__) . '../../assets/utils/js/ov.slider.js', array(), '', true);

    wp_enqueue_style('ov-main-style', plugin_dir_url(__FILE__) . '../../assets/css/main.css');

    wp_enqueue_script('ov-main-js', plugin_dir_url(__FILE__) . '../../assets/js/main.js', [], null, true);
    wp_script_add_data('ov-main-js', 'type', 'module');

    wp_enqueue_style('lightgallery-css', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/css/lightgallery-bundle.min.css', [], '2.7.1');
    wp_enqueue_script('lightgallery-js', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/lightgallery.min.js', ['jquery'], '2.7.1', true);
    wp_enqueue_script('lightgallery-thumbnail', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/plugins/thumbnail/lg-thumbnail.min.js', ['lightgallery-js'], '2.7.1', true);
    wp_enqueue_script('lightgallery-zoom', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/plugins/zoom/lg-zoom.min.js', ['lightgallery-js'], '2.7.1', true);
}
add_action('wp_enqueue_scripts', 'ov_enqueue_public_assets');


/**
 * Shared calendar CSS + core JS libraries (moment + daterangepicker)
 */
function ov_enqueue_calendar_core_libraries()
{
    wp_enqueue_style('daterangepicker-css', 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css', [], null);
    wp_enqueue_script('moment-js', 'https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js', [], null, true);
    wp_enqueue_script('daterangepicker-js', 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js', ['jquery', 'moment-js'], null, true);
}


/**
 * Admin calendar enqueue
 */
// function enqueue_calendar_admin_assets($hook)
// {
//     global $post;
//     if (($hook === 'post.php' || $hook === 'post-new.php') && isset($post) && $post->post_type === 'product') {
//         ov_enqueue_calendar_core_libraries();
        
//         wp_enqueue_style('sweetalert2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.1/dist/sweetalert2.min.css', [], '2.7.1');
//         wp_enqueue_script('sweetalert2-js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.1/dist/sweetalert2.all.min.js', [], '11.22.1', true);


//         // Admin calendar style & script
//         wp_enqueue_style('admin-calendar-style', OV_BOOKING_URL . 'includes/admin/admin-calendar/admin-calendar.css');
//         wp_enqueue_script('admin-calendar-script', OV_BOOKING_URL . 'includes/admin/admin-calendar/admin-calendar.js', ['jquery', 'daterangepicker-js'], null, true);

//         $calendar_data = get_post_meta($post->ID, '_ov_calendar_events', true) ?: [];
//         $price_defaults = get_post_meta($post->ID, '_ov_price_types', true) ?: [
//             'regular' => '',
//             'weekend' => '',
//             'discount' => '',
//             'custom' => ''
//         ];

//         wp_localize_script('admin-calendar-script', 'ov_calendar_vars', [
//             'nonce'     => wp_create_nonce('ovb_nonce'), 
//             'ajax_url' => admin_url('admin-ajax.php'),
//             'product_id' => $post->ID,
//             'priceTypes' => $price_defaults,
//             'calendarData' => $calendar_data,
//         ]);
//     }
// }
// add_action('admin_enqueue_scripts', 'enqueue_calendar_admin_assets');

// /**
//  * Frontend calendar + product assets za single product stranicu
//  */
// function ov_enqueue_single_product_assets()
// {
//     if (!is_product()) return;

//         global $post;
//         $product_id = $post->ID;

//         $calendar_data_raw = get_post_meta($product_id, '_ov_calendar_data', true);
//         $calendar_data = [];

//         if (!empty($calendar_data_raw)) {
//             if (is_string($calendar_data_raw)) {
//                 $calendar_data = json_decode($calendar_data_raw, true);
//             } elseif (is_array($calendar_data_raw)) {
//                 $calendar_data = $calendar_data_raw;
//             }
//         }

//         ov_enqueue_calendar_core_libraries();

//         // CSS/JS for single-product calendar
//         wp_enqueue_style('ov-custom-single-style', OV_BOOKING_URL . 'assets/css/ov-single.css');
//         wp_enqueue_script('ov-custom-single-script', OV_BOOKING_URL . 'assets/js/ov-single.js', ['jquery', 'daterangepicker-js'], null, true);

//         // WooCommerce skripte za add-to-cart i fragmentaciju
//         wp_enqueue_script('wc-add-to-cart');
//         wp_enqueue_script('woocommerce');
//         wp_enqueue_script('wc-single-product');
//         wp_enqueue_script('wc-cart-fragments');

//         $calendar_data = get_post_meta($post->ID, '_ov_calendar_data', true) ?: [];
//         $price_defaults = get_post_meta($post->ID, '_ov_price_types', true) ?: [
//             'regular' => '',
//             'weekend' => '',
//             'discount' => '',
//             'custom' => ''
//         ];

//         wp_localize_script('ov-custom-single-script', 'ov_calendar_vars', [
//             'ajax_url'       => admin_url('admin-ajax.php'),
//             'nonce'          => wp_create_nonce('ovb_nonce'),
//             'product_id'     => $post->ID,
//             'calendarData'   => $calendar_data,
//             'priceTypes'     => $price_defaults,
//             'checkoutUrl'    => ovb_get_checkout_url(),
//             'cartUrl'        => wc_get_cart_url(),
//             'isUserLoggedIn' => is_user_logged_in(),
//             'startDate'      => isset($_GET['ov_start_date']) ? sanitize_text_field($_GET['ov_start_date']) : '',
//             'endDate'        => isset($_GET['ov_end_date']) ? sanitize_text_field($_GET['ov_end_date']) : '',
//         ]);
    
// }
// add_action('wp_enqueue_scripts', 'ov_enqueue_single_product_assets');

// Admin calendar enqueue
function enqueue_calendar_admin_assets($hook)
{
    global $post;
    if (($hook === 'post.php' || $hook === 'post-new.php') && isset($post) && $post->post_type === 'product') {
        ov_enqueue_calendar_core_libraries();
        
        wp_enqueue_style('sweetalert2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.1/dist/sweetalert2.min.css', [], '2.7.1');
        wp_enqueue_script('sweetalert2-js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.1/dist/sweetalert2.all.min.js', [], '11.22.1', true);

        // Admin calendar style & script
        wp_enqueue_style('admin-calendar-style', OV_BOOKING_URL . 'includes/admin/admin-calendar/admin-calendar.css');
        wp_enqueue_script('admin-calendar-script', OV_BOOKING_URL . 'includes/admin/admin-calendar/admin-calendar.js', ['jquery', 'daterangepicker-js'], null, true);

        $calendar_data = get_post_meta($post->ID, '_ov_calendar_data', true) ?: [];
        $price_defaults = get_post_meta($post->ID, '_ov_price_types', true) ?: [
            'regular' => '',
            'weekend' => '',
            'discount' => '',
            'custom' => ''
        ];

        wp_localize_script('admin-calendar-script', 'ov_calendar_vars', [
            'nonce'        => wp_create_nonce('ovb_nonce'), 
            'ajax_url'     => admin_url('admin-ajax.php'),
            'product_id'   => $post->ID,
            'priceTypes'   => $price_defaults,
            'calendarData' => $calendar_data,
        ]);
    }
}
add_action('admin_enqueue_scripts', 'enqueue_calendar_admin_assets');

/**
 * Frontend calendar + product assets za single product stranicu
 */
function ov_enqueue_single_product_assets()
{
    if (!is_product()) return;

    global $post;
    $product_id = $post->ID;

    ov_enqueue_calendar_core_libraries();

    // CSS/JS for single-product calendar
    wp_enqueue_style('ov-custom-single-style', OV_BOOKING_URL . 'assets/css/ov-single.css');
    wp_enqueue_script('ov-custom-single-script', OV_BOOKING_URL . 'assets/js/ov-single.js', ['jquery', 'daterangepicker-js'], null, true);

    // WooCommerce skripte za add-to-cart i fragmentaciju
    wp_enqueue_script('wc-add-to-cart');
    wp_enqueue_script('woocommerce');
    wp_enqueue_script('wc-single-product');
    wp_enqueue_script('wc-cart-fragments');

    $calendar_data_raw = get_post_meta($product_id, '_ov_calendar_data', true);
    $calendar_data = [];

    if (!empty($calendar_data_raw)) {
        if (is_string($calendar_data_raw)) {
            $calendar_data = json_decode($calendar_data_raw, true);
        } elseif (is_array($calendar_data_raw)) {
            $calendar_data = $calendar_data_raw;
        }
    }

    $price_defaults = get_post_meta($product_id, '_ov_price_types', true) ?: [
        'regular' => '',
        'weekend' => '',
        'discount' => '',
        'custom' => ''
    ];

    wp_localize_script('ov-custom-single-script', 'ov_calendar_vars', [
        'ajax_url'       => admin_url('admin-ajax.php'),
        'nonce'          => wp_create_nonce('ovb_nonce'),
        'product_id'     => $product_id,
        'calendarData'   => $calendar_data,
        'priceTypes'     => $price_defaults,
        'checkoutUrl'    => ovb_get_checkout_url(),
        'cartUrl'        => wc_get_cart_url(),
        'isUserLoggedIn' => is_user_logged_in(),
        'startDate'      => isset($_GET['ov_start_date']) ? sanitize_text_field($_GET['ov_start_date']) : '',
        'endDate'        => isset($_GET['ov_end_date']) ? sanitize_text_field($_GET['ov_end_date']) : '',
    ]);
}
add_action('wp_enqueue_scripts', 'ov_enqueue_single_product_assets');


//  ——————————————————————————————————————————————
// 1. Enqueue CSS/JS za cart i localize AJAX
add_action('wp_enqueue_scripts', 'ovb_enqueue_cart_assets');
function ovb_enqueue_cart_assets()
{
    if (function_exists('is_cart') && is_cart()) {
        // CSS za cart
        wp_enqueue_style(
            'ovb-cart-style',
            plugin_dir_url(__FILE__) . '../../assets/css/ov-cart.css',
            []
        );
        // JS za cart
        wp_enqueue_script(
            'ovb-cart-script',
            plugin_dir_url(__FILE__) . '../../assets/js/ov-cart.js',
            ['jquery'],
            true
        );
        // Checkout URL fallback
        // $checkout_page_id = wc_get_page_id('checkout');
        // $checkout_url = ($checkout_page_id && get_post_status($checkout_page_id) === 'publish')
        //     ? get_permalink($checkout_page_id)
        //     : home_url('/checkout/');

        // if (! filter_var($checkout_url, FILTER_VALIDATE_URL)) {
        //     $checkout_url = home_url('/checkout/');
        // }
        // Lokalizuj varijable
        wp_localize_script('ovb-cart-script', 'ovCartVars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'emptyCartConfirmMsg' => __('Are you sure you want to empty your cart?', 'ov-booking'),
            'checkoutUrl' => ovb_get_checkout_url(),
            'isUserLoggedIn' => is_user_logged_in(),
        ]);
    }
}

// 2. AJAX handler za praznjenje korpe
add_action('wp_ajax_ovb_empty_cart', 'ovb_empty_cart_callback');
add_action('wp_ajax_nopriv_ovb_empty_cart', 'ovb_empty_cart_callback');
function ovb_empty_cart_callback()
{
    if (class_exists('WC_Cart') && WC()->cart) {
        WC()->cart->empty_cart();
        wp_send_json_success();
    }
    wp_send_json_error();
}
//  ——————————————————————————————————————————————
// === Enqueue CSS i JS za Checkout stranicu ===
add_action('wp_enqueue_scripts', 'ovb_enqueue_checkout_assets');
function ovb_enqueue_checkout_assets()
{
    if (function_exists('is_checkout') && is_checkout()) {
        // Ako ti treba kalendar na Checkoutu (npr. da prikažeš iste podatke), pozovi:
        ov_enqueue_calendar_core_libraries();

        // CSS za Checkout
        wp_enqueue_style(
            'ovb-checkout-style',
            OV_BOOKING_URL . 'assets/css/ov-checkout.css',
            []
        );

        // JS za Checkout
        wp_enqueue_script(
            'ovb-checkout-script',
            OV_BOOKING_URL . 'assets/js/ov-checkout.js',
            ['jquery'],
            null,
            true
        );

        // Lokalizuj AJAX url (+ šta god ti još treba u JS-u)
        wp_localize_script(
            'ovb-checkout-script',
            'ovCheckoutVars',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                // po potrebi dodaš još varijabli ovde
            ]
        );
    }
}

add_action('wp_enqueue_scripts', 'ovb_enqueue_thank_you_assets');
function ovb_enqueue_thank_you_assets()
{
    // 1) Pretty permalink endpoint check
    $is_thankyou = function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received');
    // 2) Fallback: neki plugin ili querystring stil (?order-received=ID)
    $is_thankyou = $is_thankyou || isset($_GET['order-received']);

    if ($is_thankyou) {
        // Obavezno prilagodi OV_BOOKING_URL ili plugin_dir_url( __FILE__ ) putanju
        wp_enqueue_style(
            'ovb-thank-you-style',
            OV_BOOKING_URL . 'assets/css/ov-thank-you.css',
            []
        );
        wp_enqueue_script(
            'ovb-thank-you-script',
            OV_BOOKING_URL . 'assets/js/ov-thank-you.js',
            ['jquery'],
            '1.0',
            true
        );
    }
}

/**
 * Override WooCommerce checkout templates from our plugin folder
 */
add_filter('woocommerce_locate_template', 'ovb_override_wc_checkout_templates', 20, 3);
function ovb_override_wc_checkout_templates($template, $template_name, $template_path)
{
    // Ako je u pitanju template iz checkout foldera
    if (0 === strpos($template_name, 'checkout/')) {
        // tražimo u pluginu
        $plugin_template = OV_BOOKING_PATH . 'templates/woocommerce/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}


// Enqueue custom CSS samo na My Account i podstranicama
add_action('wp_enqueue_scripts', 'ovb_enqueue_myaccount_styles');
function ovb_enqueue_myaccount_styles()
{
    if (function_exists('is_account_page') && is_account_page()) {
        wp_enqueue_style(
            'ovb-myaccount-style',
            plugin_dir_url(__FILE__) . '../../assets/css/ov-my-account.css',
            []
        );
    }
}


// Ako je Astra tema ukljucena ubaci fajl 

add_action('wp_enqueue_scripts', 'ovb_enqueue_astra_overrides', 99);
function ovb_enqueue_astra_overrides()
{
    $theme = wp_get_theme();

    if (strpos($theme->get('Name'), 'Astra') !== false || strpos($theme->get('Template'), 'astra') !== false) {

     $css_file = plugin_dir_path(__FILE__) . '../../assets/css/ov-astra-overrides.css';

        if ( file_exists( $css_file ) ) {
            wp_enqueue_style(
                'ovb-astra-overrides',
                plugin_dir_url(__FILE__) . '../../assets/css/ov-astra-overrides.css',
                [],
                filemtime( $css_file )
            );
            if ( function_exists('ov_log_error') ) {
                ov_log_error('✅ Astra detected, OVB override stylesheet enqueued.', 'general');
            }
        }
    }
}
