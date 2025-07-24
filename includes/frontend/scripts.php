<?php
defined('ABSPATH') || exit;
require_once dirname(__DIR__) . '/helpers/logger.php';

// Register dashicons globally
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('dashicons');
});

/**
 * Main public assets - optimized loading
 */
function ov_enqueue_public_assets()
{
    // Global CSS/JS with cache busting
    $main_css_path = plugin_dir_path(__FILE__) . '../../assets/css/main.css';
    $main_js_path = plugin_dir_path(__FILE__) . '../../assets/js/main.js';
    
    wp_enqueue_style('ov-main-style', plugin_dir_url(__FILE__) . '../../assets/css/main.css', [], 
        file_exists($main_css_path) ? filemtime($main_css_path) : '1.0');
    wp_enqueue_script('ov-main-js', plugin_dir_url(__FILE__) . '../../assets/js/main.js', [], 
        file_exists($main_js_path) ? filemtime($main_js_path) : '1.0', false); // Moved to head for faster init
    wp_script_add_data('ov-main-js', 'type', 'module');

    // Product-specific assets
    if (is_singular('product')) {
        ov_enqueue_product_assets();
    }
}
add_action('wp_enqueue_scripts', 'ov_enqueue_public_assets', 20);

/**
 * Product page specific assets
 */
function ov_enqueue_product_assets()
{
    // Date range picker
    wp_enqueue_style('ov-date-range-picker-css', plugin_dir_url(__FILE__) . '../../assets/utils/css/ov-date.range.css');
    wp_enqueue_script('ov-date-range-picker-js', plugin_dir_url(__FILE__) . '../../assets/utils/js/ov-date.range.js', 
        ['jquery','moment'], filemtime(plugin_dir_path(__FILE__) . '../../assets/utils/js/ov-date.range.js'), true);

    // Lightgallery with preload
    wp_enqueue_style('lightgallery-css', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/css/lightgallery-bundle.min.css', [], '2.7.1');
    wp_enqueue_script('lightgallery-js', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/lightgallery.min.js', ['jquery'], '2.7.1', true);
    wp_enqueue_script('lightgallery-thumbnail', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/plugins/thumbnail/lg-thumbnail.min.js', ['lightgallery-js'], '2.7.1', true);
    wp_enqueue_script('lightgallery-zoom', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/plugins/zoom/lg-zoom.min.js', ['lightgallery-js'], '2.7.1', true);

    // Owl Carousel
    wp_enqueue_style('owl-carousel-style', plugin_dir_url(__FILE__) . '../../assets/utils/css/owl.carousel.min.css');
    wp_enqueue_style('owl-carousel-theme', plugin_dir_url(__FILE__) . '../../assets/utils/css/owl.theme.default.min.css');
    wp_enqueue_script('owl-carousel-js', plugin_dir_url(__FILE__) . '../../assets/utils/js/owl.carousel.min.js', [], '', true);

    // Slider assets
    wp_enqueue_style('ov-slider-css', plugin_dir_url(__FILE__) . '../../assets/utils/css/ov.slider.css');
    wp_enqueue_script('ov-slider-js', plugin_dir_url(__FILE__) . '../../assets/utils/js/ov.slider.js', [], '', true);
}

/**
 * Optimized WooCommerce pages asset cleanup
 */
add_action('wp_enqueue_scripts', 'ovb_minimize_theme_and_builder_assets', 1000);
function ovb_minimize_theme_and_builder_assets() {
    if (!ovb_is_woo_page()) return;

    // Nemoj nikad gasiti ove payment handle-ove!
    $payment_scripts = [
        'stripe-js', 'wc-stripe-payment-request', 'wc-stripe-elements', 'wc-stripe', 'stripe-v3', 'woocommerce-gateway-stripe',
        'paypal-checkout-js', 'wc-paypal-payments', 'klarna-payments', 'wc-checkout', 'wc-payment-form'
    ];

    $core = [
        'wp-block-library', 'wp-block-library-theme', 'admin-bar', 'wp-embed', 'wp-polyfill', 'wp-polyfill-inert', 'regenerator-runtime'
    ];

    $wc_blocks = [
        'wc-blocks-style', 'wc-blocks-checkout-style', 'wc-checkout-block', 'wc-blocks-checkout', 'wc-blocks-cart-style'
    ];

    // *** ELEMENTOR ***
    $elementor = [
        'elementor-frontend', 'elementor-post', 'elementor-icons', 'e-animations', 'elementor-global',
        'elementor-pro', 'elementor-kit', 'elementor-library', 'elementor-webpack-runtime', 'elementor-waypoints'
    ];

    // *** ASTRA ***
    $astra = [
        'astra-addon-css', 'astra-addon-js', 'astra-google-fonts', 'astra-fonts', 'astra-theme-css', 'astra-mobile-css'
    ];

    $fonts = [
        'font-awesome', 'wpb-fa', 'elementor-icons-shared-0', 'astra-google-fonts', 'astra-fonts'
    ];

    $woo_styles = [
        'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general'
    ];

    // Woo galerija/sliders
    $woo_gallery = [];
    if (is_checkout()) {
        $woo_gallery = ['flexslider', 'photoswipe', 'photoswipe-ui-default', 'photoswipe-default-skin'];
    }

    // jQuery migrate
    $other = ['jquery-migrate'];

    $to_dequeue = array_merge(
        $core, $wc_blocks, $elementor, $astra, $fonts, $woo_gallery, $woo_styles, $other
    );

    foreach ($to_dequeue as $handle) {
        if (!in_array($handle, $payment_scripts)) {
            wp_dequeue_script($handle); 
            wp_dequeue_style($handle);
        }
    }
}


function ovb_is_woo_page() {
    return is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url('order-received') || is_product();
}

/**
 * Core calendar libraries - shared between admin and frontend
 */
function ov_enqueue_calendar_core()
{
    wp_enqueue_style('daterangepicker-css', 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css');
    wp_enqueue_script('moment-js', 'https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js', [], null, false); // Head loading
    wp_enqueue_script('daterangepicker-js', 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js', 
        ['jquery', 'moment-js'], null, false); // Head loading for checkout
}

/**
 * Admin calendar assets
 */
function ov_enqueue_admin_calendar($hook)
{
    global $post;
    if (($hook === 'post.php' || $hook === 'post-new.php') && isset($post) && $post->post_type === 'product') {
        ov_enqueue_calendar_core();
        
        wp_enqueue_style('sweetalert2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.1/dist/sweetalert2.min.css', [], '11.22.1');
        wp_enqueue_script('sweetalert2-js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.1/dist/sweetalert2.all.min.js', [], '11.22.1', true);

        wp_enqueue_style('admin-calendar-style', OV_BOOKING_URL . 'includes/admin/admin-calendar/admin-calendar.css');
        wp_enqueue_script('admin-calendar-script', OV_BOOKING_URL . 'includes/admin/admin-calendar/admin-calendar.js', 
            ['jquery', 'daterangepicker-js'], null, true);

        $calendar_data = get_post_meta($post->ID, '_ov_calendar_data', true) ?: [];
        $price_defaults = get_post_meta($post->ID, '_ov_price_types', true) ?: [
            'regular' => '', 'weekend' => '', 'discount' => '', 'custom' => ''
        ];

        wp_localize_script('admin-calendar-script', 'ov_calendar_vars', [
            'nonce' => wp_create_nonce('ovb_nonce'), 
            'ajax_url' => admin_url('admin-ajax.php'),
            'product_id' => $post->ID,
            'priceTypes' => $price_defaults,
            'calendarData' => $calendar_data,
        ]);
    }
}
add_action('admin_enqueue_scripts', 'ov_enqueue_admin_calendar');

/**
 * Single product page assets
 */
function ov_enqueue_single_product()
{
    if (!is_product()) return;

    global $post;
    $product_id = $post->ID;

    ov_enqueue_calendar_core();

    wp_enqueue_style('ov-custom-single-style', OV_BOOKING_URL . 'assets/css/ov-single.css');
    wp_enqueue_script('ov-custom-single-script', OV_BOOKING_URL . 'assets/js/ov-single.js', 
        ['jquery', 'daterangepicker-js'], null, true);
    
    wp_localize_script('ov-single', 'ovBookingI18n', [
        'selectEndDate' => __('Select end date', 'ov-booking'),
    ]);

    // Essential WooCommerce scripts
    wp_enqueue_script('wc-add-to-cart');
    wp_enqueue_script('woocommerce');
    wp_enqueue_script('wc-single-product');
    wp_enqueue_script('wc-cart-fragments');

    // Calendar data
    $calendar_data = ov_get_calendar_data($product_id);
    $price_defaults = get_post_meta($product_id, '_ov_price_types', true) ?: [
        'regular' => '', 'weekend' => '', 'discount' => '', 'custom' => ''
    ];

    wp_localize_script('ov-custom-single-script', 'ov_calendar_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ovb_nonce'),
        'product_id' => $product_id,
        'calendarData' => $calendar_data,
        'priceTypes' => $price_defaults,
        'checkoutUrl' => ovb_get_checkout_url(),
        'cartUrl' => wc_get_cart_url(),
        'isUserLoggedIn' => is_user_logged_in(),
        'startDate' => isset($_GET['ov_start_date']) ? sanitize_text_field($_GET['ov_start_date']) : '',
        'endDate' => isset($_GET['ov_end_date']) ? sanitize_text_field($_GET['ov_end_date']) : '',
    ]);
}
add_action('wp_enqueue_scripts', 'ov_enqueue_single_product');

/**
 * Cart page assets
 */
function ov_enqueue_cart_assets()
{
    if (!is_cart()) return;

    wp_enqueue_style('ovb-cart-style', plugin_dir_url(__FILE__) . '../../assets/css/ov-cart.css');
    wp_enqueue_script('ovb-cart-script', plugin_dir_url(__FILE__) . '../../assets/js/ov-cart.js', ['jquery'], null, true);
    
    wp_localize_script('ovb-cart-script', 'ovCartVars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'emptyCartConfirmMsg' => __('Are you sure you want to empty your cart?', 'ov-booking'),
        'checkoutUrl' => ovb_get_checkout_url(),
        'isUserLoggedIn' => is_user_logged_in(),
    ]);
}
add_action('wp_enqueue_scripts', 'ov_enqueue_cart_assets');

/**
 * Checkout page assets with optimization
 */
function ov_enqueue_checkout_assets()
{
    if (!is_checkout()) return;

    // Prioritizuj payment gateway assets
    wp_enqueue_script('wc-checkout');
    wp_enqueue_script('wc-payment-form');
    
    // Calendar core u head-u za brže učitavanje
    ov_enqueue_calendar_core();

    wp_enqueue_style('ovb-checkout-style', OV_BOOKING_URL . 'assets/css/ov-checkout.css');
    wp_enqueue_script('ovb-checkout-script', OV_BOOKING_URL . 'assets/js/ov-checkout.js', 
        ['jquery', 'wc-checkout'], null, false); // Load in head for faster init

    wp_localize_script('ovb-checkout-script', 'ovCheckoutVars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'payment_ready_timeout' => 25000, // 25s timeout
    ]);

    // Resource hints za payment providers
    add_action('wp_head', function() {
        echo '<link rel="dns-prefetch" href="//js.stripe.com">';
        echo '<link rel="dns-prefetch" href="//www.paypalobjects.com">';
        echo '<link rel="dns-prefetch" href="//x.klarnacdn.net">';
        echo '<link rel="preconnect" href="https://js.stripe.com" crossorigin>';
    });
}
add_action('wp_enqueue_scripts', 'ov_enqueue_checkout_assets');

/**
 * Thank you page assets
 */
function ov_enqueue_thank_you_assets()
{
    $is_thankyou = (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) || isset($_GET['order-received']);
    
    if ($is_thankyou) {
        wp_enqueue_style('ovb-thank-you-style', OV_BOOKING_URL . 'assets/css/ov-thank-you.css');
        wp_enqueue_script('ovb-thank-you-script', OV_BOOKING_URL . 'assets/js/ov-thank-you.js', ['jquery'], '1.0', true);
    }
}
add_action('wp_enqueue_scripts', 'ov_enqueue_thank_you_assets');

/**
 * My Account page assets
 */
function ov_enqueue_myaccount_assets()
{
    if (is_account_page()) {
        wp_enqueue_style('ovb-myaccount-style', plugin_dir_url(__FILE__) . '../../assets/css/ov-my-account.css');
    }
}
add_action('wp_enqueue_scripts', 'ov_enqueue_myaccount_assets');

/**
 * Astra theme overrides
 */
function ov_enqueue_astra_overrides()
{
    $theme = wp_get_theme();
    if (strpos($theme->get('Name'), 'Astra') === false && strpos($theme->get('Template'), 'astra') === false) return;

    $css_file = plugin_dir_path(__FILE__) . '../../assets/css/ov-astra-overrides.css';
    if (file_exists($css_file)) {
        wp_enqueue_style('ovb-astra-overrides', plugin_dir_url(__FILE__) . '../../assets/css/ov-astra-overrides.css', 
            [], filemtime($css_file));
        
        if (function_exists('ov_log_error')) {
            ov_log_error('✅ Astra detected, OVB override stylesheet enqueued.', 'general');
        }
    }
}
add_action('wp_enqueue_scripts', 'ov_enqueue_astra_overrides', 99);

/**
 * Template overrides
 */
add_filter('woocommerce_locate_template', 'ovb_locate_template', 99, 3);
function ovb_locate_template($template, $template_name, $template_path)
{
    $plugin_path = OV_BOOKING_PATH . 'templates/woocommerce/';
    
    $custom_templates = [
        'checkout/cart.php' => 'ov-cart.php',
        'checkout/form-checkout.php' => 'ov-checkout.php', 
        'checkout/thankyou.php' => 'ov-thank-you.php'
    ];

    if (isset($custom_templates[$template_name])) {
        $custom_file = $plugin_path . $custom_templates[$template_name];
        if (file_exists($custom_file)) {
            // Additional page-specific checks
            if ($template_name === 'checkout/cart.php' && is_cart()) return $custom_file;
            if ($template_name === 'checkout/form-checkout.php' && is_checkout()) return $custom_file;
            if ($template_name === 'checkout/thankyou.php' && is_wc_endpoint_url('order-received')) return $custom_file;
        }
    }

    return $template;
}

/**
 * AJAX handlers
 */
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

/**
 * Optimized checkout payment initialization
 */
add_action('wp_footer', 'ovb_optimized_checkout_init');
function ovb_optimized_checkout_init()
{
    if (!is_checkout() || is_order_received_page()) return;
    ?>
    <script>
    (function($) {
        'use strict';

        // Mini loader za express dugmad
        function showMiniLoader() {
            var wrapper = document.querySelector('.wcpay-express-checkout-wrapper');
            if (!wrapper || wrapper.querySelector('.ovb-mini-loader')) return;

            var loader = document.createElement('div');
            loader.className = 'ovb-mini-loader';
            loader.style.cssText = "display:flex;justify-content:center;align-items:center;height:60px;background:#7C4DFF;border-radius:7px;font-size:1.06em;font-weight:500;color:#163260;margin-bottom:7px;transition:opacity 0.4s;gap:10px;";
            loader.innerHTML = '<span class="loader"></span> <span>Payment methods loading…</span>';
            wrapper.insertBefore(loader, wrapper.firstChild);
        }
        function removeMiniLoader() {
            var loader = document.querySelector('.wcpay-express-checkout-wrapper .ovb-mini-loader');
            if (loader) loader.remove();
        }

        // Animation for Dashicon spinner
        var style = document.createElement('style');
        style.innerHTML = '@keyframes spin{100%{transform:rotate(360deg);}}';
        document.head.appendChild(style);

        // Lista svih payment UI koje čekamo
        function paymentUiReady() {
            return (
                document.querySelector('.StripeElement.is-ready')
                || document.querySelector('.apple-pay-button')
                || document.querySelector('.google-pay-button')
                || document.querySelector('.wcpay-express-checkout-button') // WooPayments
                || document.querySelector('.paypal-buttons')
            );
        }

        $(document).ready(function() {
            // Skloni glavni loader čim checkout content postoji
            var contentCheck = setInterval(function() {
                // .ov-checkout-content je tvoj glavni content wraper!
                if (document.querySelector('.ov-checkout-content')) {
                    clearInterval(contentCheck);
                }
            }, 80);

            // Mini loader samo dok nema StripeElement dugmeta!
            showMiniLoader();
            var miniInterval = setInterval(function() {
                if (paymentUiReady()) {
                    removeMiniLoader();
                    clearInterval(miniInterval);
                }
            }, 200);
            // Ako posle 15s nema payment dugmeta, skloni loader (fail-safe)
            setTimeout(function() {
                removeMiniLoader();
                clearInterval(miniInterval);
            }, 15000);
        });

        // Ponovo pokreni loader na update_checkout
        $(document.body).on('updated_checkout payment_method_selected', function() {
            showMiniLoader();
            var miniInterval = setInterval(function() {
                if (paymentUiReady()) {
                    removeMiniLoader();
                    clearInterval(miniInterval);
                }
            }, 200);
            setTimeout(function() {
                removeMiniLoader();
                clearInterval(miniInterval);
            }, 15000);
        });

    })(jQuery);
    </script>
    <style>
    .ovb-mini-loader { 
        min-height: 38px; 
        letter-spacing: 0.02em;
        box-shadow: 0 3px 12px 0 rgba(17, 21, 39, 0.08);
    }
    </style>
    <?php
}






// Optimized script loading
add_filter('script_loader_tag', function($tag, $handle) {
    $defer_handles = ['ov-slider-js', 'ovb-cart-script'];
    $async_handles = ['lightgallery-js', 'owl-carousel-js'];
    
    if (in_array($handle, $defer_handles)) {
        return str_replace(' src', ' defer src', $tag);
    }
    
    if (in_array($handle, $async_handles)) {
        return str_replace(' src', ' async src', $tag);
    }
    
    // Critical checkout scripts load normally in head
    if (is_checkout() && in_array($handle, ['ovb-checkout-script', 'moment-js', 'daterangepicker-js'])) {
        return $tag;
    }
    
    return $tag;
}, 10, 2);

/**
 * Add preload hints for critical checkout resources
 */
add_action('wp_head', function() {
    if (!is_checkout()) return;
    
    echo '<link rel="preload" href="' . OV_BOOKING_URL . 'assets/css/ov-checkout.css" as="style">';
    echo '<link rel="preload" href="' . OV_BOOKING_URL . 'assets/js/ov-checkout.js" as="script">';
    echo '<link rel="preload" href="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js" as="script">';
}, 1);

/**
 * Disable WooCommerce scripts on non-essential pages
 */
add_action('wp_enqueue_scripts', function() {
    if (!ovb_is_woo_page() && !is_shop() && !is_product_category() && !is_product_tag()) {
        wp_dequeue_script('woocommerce');
        wp_dequeue_script('wc-cart-fragments');
        wp_dequeue_style('woocommerce-layout');
        wp_dequeue_style('woocommerce-smallscreen');
        wp_dequeue_style('woocommerce-general');
    }
}, 999);

/**
 * Helper function for calendar data
 */
function ov_get_calendar_data($product_id)
{
    $calendar_data_raw = get_post_meta($product_id, '_ov_calendar_data', true);
    if (empty($calendar_data_raw)) return [];
    
    if (is_string($calendar_data_raw)) {
        return json_decode($calendar_data_raw, true) ?: [];
    }
    
    return is_array($calendar_data_raw) ? $calendar_data_raw : [];
}

// Micro-cache Woo payment gateways during a single request (defense against heavy/slow filters/plugins)
add_filter('woocommerce_available_payment_gateways', function($gateways) {
    static $cached_gateways = null;
    if ($cached_gateways !== null) return $cached_gateways;
    $cached_gateways = $gateways;
    return $gateways;
}, 99);