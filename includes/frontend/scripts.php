<?php
defined('ABSPATH') || exit;

/**
 * ======================================
 *  OV Booking Frontend Scripts & Assets
 * ======================================
 */

// Loader za logger ako treba
if (file_exists(dirname(__DIR__) . '/helpers/logger.php')) {
    require_once dirname(__DIR__) . '/helpers/logger.php';
}

/**
 * GLOBAL OPTIMIZATIONS
 */

// Prevent WordPress update checks na frontend-u
add_action('init', 'ovb_disable_frontend_updates', 1);
function ovb_disable_frontend_updates() {
    if (is_admin()) return;
    add_filter('pre_site_transient_update_core', '__return_null');
    add_filter('pre_site_transient_update_plugins', '__return_null');
    add_filter('pre_site_transient_update_themes', '__return_null');
    remove_action('init', 'wp_version_check');
    remove_action('init', 'wp_update_plugins');
    remove_action('init', 'wp_update_themes');
}

// Global WP/Elementor assets & config
add_action('wp_enqueue_scripts', 'ovb_enqueue_global_assets', 1);
function ovb_enqueue_global_assets() {
    wp_enqueue_style('dashicons');
    if (ovb_is_woo_page()) {
        wp_enqueue_script('jquery');
        add_action('wp_head', function() {
            echo '<script>window.elementorFrontendConfig = window.elementorFrontendConfig || {};</script>';
        }, 1);
    }
}

/**
 * MAIN PLUGIN ASSETS
 */
add_action('wp_enqueue_scripts', 'ovb_enqueue_main_assets', 20);
function ovb_enqueue_main_assets() {
    $main_css_file = OVB_BOOKING_PATH . 'assets/css/main.css';
    $main_js_file  = OVB_BOOKING_PATH . 'assets/js/main.js';

    if (file_exists($main_css_file)) {
        wp_enqueue_style(
            'ovb-main-style',
            OVB_BOOKING_URL . 'assets/css/main.css',
            [],
            filemtime($main_css_file)
        );
    }
    if (file_exists($main_js_file)) {
        wp_enqueue_script(
            'ovb-main-js',
            OVB_BOOKING_URL . 'assets/js/main.js',
            ['jquery'],
            filemtime($main_js_file),
            false // head for faster init
        );
        wp_script_add_data('ovb-main-js', 'type', 'module');
    }

    if (is_product()) {
        ovb_enqueue_product_assets();
    }
}

/**
 * PRODUCT PAGE ASSETS (frontend)
 */
function ovb_enqueue_product_assets() {
    global $post;
    $product_id = $post->ID;

    ovb_enqueue_calendar_core();
    ovb_enqueue_daterange_picker();
    ovb_enqueue_gallery_assets();
    ovb_enqueue_slider_assets();
    ovb_enqueue_product_scripts($product_id);
}

// Calendar core libraries (Moment, Daterangepicker)
function ovb_enqueue_calendar_core() {
    if (!wp_script_is('moment-js', 'enqueued')) {
        wp_enqueue_script('moment-js', 'https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js', [], '2.29.4', false);
    }
    add_action('wp_footer', function() {
        ?>
        <script>
        if(typeof moment === "undefined") {
          var s = document.createElement('script');
          s.src = '<?php echo OVB_BOOKING_URL . "assets/utils/js/moment-local.min.js"; ?>';
          document.body.appendChild(s);
        }
        </script>
        <?php
    }, 1000);

    if (!wp_script_is('daterangepicker-js', 'enqueued')) {
        wp_enqueue_style('daterangepicker-css', 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css', [], '3.1.0');
        wp_enqueue_script(
            'daterangepicker-js',
            'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js',
            ['jquery', 'moment-js'],
            '3.1.0',
            false
        );
    }
}

function ovb_enqueue_daterange_picker() {
    $css_file = OVB_BOOKING_PATH . 'assets/utils/css/ov-date.range.css';
    $js_file  = OVB_BOOKING_PATH . 'assets/utils/js/ov-date.range.js';
    if (file_exists($css_file) && !wp_style_is('ovb-daterange-css', 'enqueued')) {
        wp_enqueue_style('ovb-daterange-css', OVB_BOOKING_URL . 'assets/utils/css/ov-date.range.css', ['daterangepicker-css'], filemtime($css_file));
    }
    if (file_exists($js_file) && !wp_script_is('ovb-daterange-js', 'enqueued')) {
        wp_enqueue_script('ovb-daterange-js', OVB_BOOKING_URL . 'assets/utils/js/ov-date.range.js', ['jquery', 'moment-js', 'daterangepicker-js'], filemtime($js_file), true);
    }
}

function ovb_enqueue_gallery_assets() {
    add_action('wp_head', function() {
        echo '<link rel="dns-prefetch" href="//cdn.jsdelivr.net">';
        echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>';
    });
    if (!wp_script_is('lightgallery-js', 'enqueued')) {
        wp_enqueue_style('lightgallery-css', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/css/lightgallery-bundle.min.css', [], '2.7.1');
        wp_enqueue_script('lightgallery-js', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/lightgallery.min.js', ['jquery'], '2.7.1', true);
        wp_enqueue_script('lightgallery-thumbnail', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/plugins/thumbnail/lg-thumbnail.min.js', ['lightgallery-js'], '2.7.1', true);
        wp_enqueue_script('lightgallery-zoom', 'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/plugins/zoom/lg-zoom.min.js', ['lightgallery-js'], '2.7.1', true);
    }
}

function ovb_enqueue_slider_assets() {
    $owl_css    = OVB_BOOKING_PATH . 'assets/utils/css/owl.carousel.min.css';
    $owl_theme  = OVB_BOOKING_PATH . 'assets/utils/css/owl.theme.default.min.css';
    $slider_css = OVB_BOOKING_PATH . 'assets/utils/css/ov.slider.css';
    $owl_js     = OVB_BOOKING_PATH . 'assets/utils/js/owl.carousel.min.js';
    $slider_js  = OVB_BOOKING_PATH . 'assets/utils/js/ov.slider.js';

    if (file_exists($owl_css)    && !wp_style_is('owl-carousel', 'enqueued')) wp_enqueue_style('owl-carousel', OVB_BOOKING_URL . 'assets/utils/css/owl.carousel.min.css');
    if (file_exists($owl_theme)  && !wp_style_is('owl-theme', 'enqueued'))   wp_enqueue_style('owl-theme', OVB_BOOKING_URL . 'assets/utils/css/owl.theme.default.min.css');
    if (file_exists($slider_css) && !wp_style_is('ovb-slider', 'enqueued'))  wp_enqueue_style('ovb-slider', OVB_BOOKING_URL . 'assets/utils/css/ov.slider.css');
    if (file_exists($owl_js)     && !wp_script_is('owl-carousel-js', 'enqueued')) wp_enqueue_script('owl-carousel-js', OVB_BOOKING_URL . 'assets/utils/js/owl.carousel.min.js', ['jquery'], '', true);
    if (file_exists($slider_js)  && !wp_script_is('ovb-slider-js', 'enqueued'))   wp_enqueue_script('ovb-slider-js', OVB_BOOKING_URL . 'assets/utils/js/ov.slider.js', ['jquery', 'owl-carousel-js'], '', true);
}

function ovb_enqueue_product_scripts($product_id) {
    $single_css = OVB_BOOKING_PATH . 'assets/css/ov-single.css';
    $single_js  = OVB_BOOKING_PATH . 'assets/js/ov-single.js';
    if (file_exists($single_css) && !wp_style_is('ovb-single-style', 'enqueued')) {
        wp_enqueue_style('ovb-single-style', OVB_BOOKING_URL . 'assets/css/ov-single.css', [], filemtime($single_css));
    }
    if (file_exists($single_js) && !wp_script_is('ovb-single-script', 'enqueued')) {
        wp_enqueue_script('ovb-single-script', OVB_BOOKING_URL . 'assets/js/ov-single.js', ['jquery', 'daterangepicker-js', 'wc-add-to-cart'], filemtime($single_js), true);
    }
    foreach (['wc-add-to-cart', 'woocommerce', 'wc-single-product', 'wc-cart-fragments'] as $script) {
        if (!wp_script_is($script, 'enqueued')) wp_enqueue_script($script);
    }
    ovb_localize_product_scripts($product_id);
}

function ovb_localize_product_scripts($product_id) {
    $calendar_data = ovb_get_clean_calendar_data($product_id);
    $price_types = get_post_meta($product_id, '_ovb_price_types', true) ?: [];
    wp_localize_script('ovb-single-script', 'ovbProductVars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ovb_nonce'),
        'product_id' => $product_id,
        'calendar_data' => $calendar_data,
        'price_types' => $price_types,
        'checkout_url' => wc_get_checkout_url(),
        'cart_url' => wc_get_cart_url(),
        'is_user_logged_in' => is_user_logged_in(),
        'start_date' => sanitize_text_field($_GET['ovb_start_date'] ?? ''),
        'end_date'   => sanitize_text_field($_GET['ovb_end_date'] ?? ''),
        'i18n' => [
            'select_end_date' => __('Select end date', 'ov-booking'),
            'select_dates'    => __('Please select dates', 'ov-booking'),
            'loading'         => __('Loading...', 'ov-booking'),
        ],
    ]);
}

/**
 * CART PAGE ASSETS
 */
add_action('wp_enqueue_scripts', 'ovb_enqueue_cart_assets');
function ovb_enqueue_cart_assets() {
    if (!is_cart()) return;
    $cart_css = OVB_BOOKING_PATH . 'assets/css/ov-cart.css';
    $cart_js  = OVB_BOOKING_PATH . 'assets/js/ov-cart.js';
    if (file_exists($cart_css) && !wp_style_is('ovb-cart-style', 'enqueued')) {
        wp_enqueue_style('ovb-cart-style', OVB_BOOKING_URL . 'assets/css/ov-cart.css', ['woocommerce-layout'], filemtime($cart_css));
    }
    if (file_exists($cart_js) && !wp_script_is('ovb-cart-script', 'enqueued')) {
        wp_enqueue_script('ovb-cart-script', OVB_BOOKING_URL . 'assets/js/ov-cart.js', ['jquery', 'wc-cart'], filemtime($cart_js), true);
        wp_localize_script('ovb-cart-script', 'ovbCartVars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ovb_nonce'),
            'empty_cart_confirm' => __('Are you sure you want to empty your cart?', 'ov-booking'),
            'checkout_url' => wc_get_checkout_url(),
            'is_user_logged_in' => is_user_logged_in(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
        ]);
    }
}

/**
 * CHECKOUT PAGE ASSETS
 */
add_action('wp_enqueue_scripts', 'ovb_enqueue_checkout_assets');
function ovb_enqueue_checkout_assets() {
    if (!is_checkout() || is_order_received_page()) return;
    foreach (['wc-checkout', 'wc-payment-form', 'wc-stripe', 'stripe-js'] as $script) {
        if (!wp_script_is($script, 'enqueued')) wp_enqueue_script($script);
    }
    ovb_enqueue_calendar_core();
    $checkout_css = OVB_BOOKING_PATH . 'assets/css/ov-checkout.css';
    $checkout_js  = OVB_BOOKING_PATH . 'assets/js/ov-checkout.js';
    if (file_exists($checkout_css) && !wp_style_is('ovb-checkout-style', 'enqueued')) {
        wp_enqueue_style('ovb-checkout-style', OVB_BOOKING_URL . 'assets/css/ov-checkout.css', [], filemtime($checkout_css));
    }
    if (file_exists($checkout_js) && !wp_script_is('ovb-checkout-script', 'enqueued')) {
        wp_enqueue_script('ovb-checkout-script', OVB_BOOKING_URL . 'assets/js/ov-checkout.js', ['jquery', 'wc-checkout'], filemtime($checkout_js), false);
        wp_localize_script('ovb-checkout-script', 'ovbCheckoutVars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ovb_nonce'),
            'payment_ready_timeout' => 25000,
            'wc_ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
            'i18n' => [
                'loading_payment' => __('Loading payment methods...', 'ov-booking'),
                'payment_error'   => __('Payment initialization failed', 'ov-booking'),
            ],
        ]);
    }
    add_action('wp_head', 'ovb_add_payment_resource_hints');
}

function ovb_add_payment_resource_hints() {
    if (!is_checkout()) return;
    foreach (['js.stripe.com','www.paypalobjects.com','x.klarnacdn.net'] as $domain) {
        echo '<link rel="dns-prefetch" href="//' . esc_attr($domain) . '">';
    }
    echo '<link rel="preconnect" href="https://js.stripe.com" crossorigin>';
}

/**
 * THANK YOU PAGE ASSETS
 */
add_action('wp_enqueue_scripts', 'ovb_enqueue_thankyou_assets');
function ovb_enqueue_thankyou_assets() {
    $is_thankyou = is_order_received_page() || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) || isset($_GET['order-received']);
    if (!$is_thankyou) return;
    $thankyou_css = OVB_BOOKING_PATH . 'assets/css/ov-thank-you.css';
    $thankyou_js  = OVB_BOOKING_PATH . 'assets/js/ov-thank-you.js';
    if (file_exists($thankyou_css) && !wp_style_is('ovb-thankyou-style', 'enqueued')) {
        wp_enqueue_style('ovb-thankyou-style', OVB_BOOKING_URL . 'assets/css/ov-thank-you.css', [], filemtime($thankyou_css));
    }
    if (file_exists($thankyou_js) && !wp_script_is('ovb-thankyou-script', 'enqueued')) {
        wp_enqueue_script('ovb-thankyou-script', OVB_BOOKING_URL . 'assets/js/ov-thank-you.js', ['jquery'], filemtime($thankyou_js), true);
    }
}

/**
 * MY ACCOUNT PAGE ASSETS
 */
add_action('wp_enqueue_scripts', 'ovb_enqueue_account_assets');
function ovb_enqueue_account_assets() {
    if (!is_account_page()) return;
    $account_css = OVB_BOOKING_PATH . 'assets/css/ov-my-account.css';
    if (file_exists($account_css) && !wp_style_is('ovb-account-style', 'enqueued')) {
        wp_enqueue_style('ovb-account-style', OVB_BOOKING_URL . 'assets/css/ov-my-account.css', [], filemtime($account_css));
    }
}

/**
 * THEME-SPECIFIC OVERRIDES
 */
add_action('wp_enqueue_scripts', 'ovb_enqueue_theme_overrides', 99);
function ovb_enqueue_theme_overrides() {
    $theme = wp_get_theme();
    $theme_name = $theme->get('Name');
    $theme_template = $theme->get('Template');
    $custom_themes = [
        'Astra' => 'ovb-astra-overrides',
        'GeneratePress' => 'ovb-generatepress-overrides',
        'Kadence' => 'ovb-kadence-overrides',
    ];
    foreach ($custom_themes as $needle => $handle) {
        if (stripos($theme_name, $needle) !== false || stripos($theme_template, strtolower($needle)) !== false) {
            $css_file = OVB_BOOKING_PATH . "assets/css/{$handle}.css";
            if (file_exists($css_file) && !wp_style_is($handle, 'enqueued')) {
                wp_enqueue_style($handle, OVB_BOOKING_URL . "assets/css/{$handle}.css", [], filemtime($css_file));
            }
        }
    }
}

/**
 * ASSET OPTIMIZATION AND PRELOADS
 */
add_action('wp_enqueue_scripts', 'ovb_optimize_woo_page_assets', 1000);
function ovb_optimize_woo_page_assets() {
    if (!ovb_is_woo_page()) return;
    $keep_assets = apply_filters('ovb_keep_theme_assets', [
        'elementor-frontend', 'elementor-icons', 'elementor-kit', 'astra-theme-css',
        'generate-style', 'kadence-global'
    ]);
    $payment_assets = [
        'stripe-js','wc-stripe-payment-request','wc-stripe-elements','wc-stripe',
        'paypal-checkout-js','wc-paypal-payments','klarna-payments',
        'wc-checkout','wc-payment-form'
    ];
    $removable_assets = [
        'wp-block-library','wp-block-library-theme','wp-embed',
        'wc-blocks-style','wc-blocks-checkout-style','wc-checkout-block',
        'flexslider','photoswipe','photoswipe-ui-default',
        'elementor-post','e-animations','elementor-waypoints',
        'jquery-migrate'
    ];
    foreach ($removable_assets as $handle) {
        if (!in_array($handle, $keep_assets) && !in_array($handle, $payment_assets)) {
            wp_dequeue_script($handle);
            wp_dequeue_style($handle);
        }
    }
}

// Defer/async for non-critical JS
add_filter('script_loader_tag', 'ovb_optimize_script_loading', 10, 2);
function ovb_optimize_script_loading($tag, $handle) {
    $defer_scripts = ['ovb-slider-js', 'owl-carousel-js', 'lightgallery-thumbnail', 'lightgallery-zoom'];
    $async_scripts = ['lightgallery-js'];
    if (in_array($handle, $defer_scripts)) return str_replace('<script', '<script defer', $tag);
    if (in_array($handle, $async_scripts)) return str_replace('<script', '<script async', $tag);
    return $tag;
}

// Preload critical resources
add_action('wp_head', 'ovb_preload_critical_resources', 1);
function ovb_preload_critical_resources() {
    if (is_checkout()) {
        echo '<link rel="preload" href="' . OVB_BOOKING_URL . 'assets/css/ov-checkout.css" as="style">';
        echo '<link rel="preload" href="' . OVB_BOOKING_URL . 'assets/js/ov-checkout.js" as="script">';
        echo '<link rel="preload" href="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js" as="script">';
    }
    if (is_product()) {
        echo '<link rel="preload" href="' . OVB_BOOKING_URL . 'assets/css/ov-single.css" as="style">';
        echo '<link rel="preload" href="' . OVB_BOOKING_URL . 'assets/js/ov-single.js" as="script">';
    }
}

/**
 * TEMPLATE OVERRIDES
 */
add_filter('woocommerce_locate_template', 'ovb_locate_woo_templates', 99, 3);
function ovb_locate_woo_templates($template, $template_name, $template_path) {
    $custom_templates = [
        'checkout/cart.php' => 'ov-cart.php',
        'checkout/form-checkout.php' => 'ov-checkout.php',
        'checkout/thankyou.php' => 'ov-thank-you.php',
    ];
    if (isset($custom_templates[$template_name])) {
        $custom_file = OVB_BOOKING_PATH . 'templates/woocommerce/' . $custom_templates[$template_name];
        if (file_exists($custom_file)) {
            if ($template_name === 'checkout/cart.php' && is_cart()) return $custom_file;
            if ($template_name === 'checkout/form-checkout.php' && is_checkout() && !is_order_received_page()) return $custom_file;
            if ($template_name === 'checkout/thankyou.php' && is_order_received_page()) return $custom_file;
        }
    }
    return $template;
}

/**
 * AJAX HANDLERS (frontend only)
 */
add_action('wp_ajax_ovb_empty_cart', 'ovb_ajax_empty_cart');
add_action('wp_ajax_nopriv_ovb_empty_cart', 'ovb_ajax_empty_cart');
function ovb_ajax_empty_cart() {
    check_ajax_referer('ovb_nonce', 'nonce');
    if (WC()->cart) {
        WC()->cart->empty_cart();
        wp_send_json_success(['message' => __('Cart emptied successfully', 'ov-booking')]);
    }
    wp_send_json_error(['message' => __('Failed to empty cart', 'ov-booking')]);
}

/**
 * CHECKOUT OPTIMIZATION (payment loader + JS fallback)
 */
add_action('wp_footer', 'ovb_checkout_optimization');
function ovb_checkout_optimization() {
    if (!is_checkout() || is_order_received_page()) return;
    ?>
    <script>
    (function($) {
        'use strict';
        function createMiniLoader() {
            return $('<div class="ovb-payment-loader" style="display:flex;justify-content:center;align-items:center;height:50px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;margin-bottom:15px;border-radius:4px;font-weight:500;gap:10px;"><span class="ovb-spinner" style="width:20px;height:20px;border:2px solid rgba(255,255,255,0.3);border-top:2px solid white;border-radius:50%;animation:spin 1s linear infinite;"></span><span>Payment methods loading...</span></div>');
        }
        function isPaymentUIReady() {
            return $('.StripeElement.is-ready, .apple-pay-button, .google-pay-button, .wcpay-express-checkout-button, .paypal-buttons').length > 0;
        }
        function showPaymentLoader() {
            const $wrapper = $('.wcpay-express-checkout-wrapper, .wc-payment-form');
            if ($wrapper.length && !$wrapper.find('.ovb-payment-loader').length) {
                $wrapper.prepend(createMiniLoader());
            }
        }
        function hidePaymentLoader() {
            $('.ovb-payment-loader').fadeOut(300, function() { $(this).remove(); });
        }
        // Initial load
        $(document).ready(function() {
            showPaymentLoader();
            const checkPaymentReady = setInterval(function() {
                if (isPaymentUIReady()) {
                    hidePaymentLoader();
                    clearInterval(checkPaymentReady);
                }
            }, 200);
            setTimeout(function() {
                hidePaymentLoader();
                clearInterval(checkPaymentReady);
            }, 15000);
        });
        // Re-check on checkout updates
        $(document.body).on('updated_checkout payment_method_selected', function() {
            if (!isPaymentUIReady()) {
                showPaymentLoader();
                const recheckPayment = setInterval(function() {
                    if (isPaymentUIReady()) {
                        hidePaymentLoader();
                        clearInterval(recheckPayment);
                    }
                }, 200);
                setTimeout(function() {
                    hidePaymentLoader();
                    clearInterval(recheckPayment);
                }, 10000);
            }
        });
        setTimeout(function() {
            if (!isPaymentUIReady()) {
                $('.payment_methods, .wcpay-express-checkout-wrapper').prepend('<div style="padding:12px;background:#fff3cd;color:#856404;border-radius:4px;margin-bottom:10px;font-size:15px;font-weight:500;"><?php echo esc_js(__('Payment methods could not be loaded. Please refresh the page or contact support.', 'ov-booking')); ?></div>');
            }
        }, 20000);
    })(jQuery);
    </script>
    <style>
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .ovb-payment-loader { box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: all 0.3s ease; }
    </style>
    <?php
}

/**
 * UTILITY FUNCTIONS
 */
function ovb_is_woo_page() {
    return is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url('order-received') || is_product() || is_shop() || is_product_category() || is_product_tag();
}
function ovb_get_clean_calendar_data($product_id) {
    $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
    if (empty($calendar_data)) return [];
    if (is_string($calendar_data)) $calendar_data = json_decode($calendar_data, true);
    return is_array($calendar_data) ? $calendar_data : [];
}
