<?php
defined('ABSPATH') || exit;

/**
 * ======================================
 *  OV Booking Frontend Scripts & Assets
 * ======================================
 */

/**
 * OPTIONAL LOGGER LOADER
 */
if ( file_exists( dirname( __DIR__ ) . '/helpers/logger.php' ) ) {
    require_once dirname( __DIR__ ) . '/helpers/logger.php';
}

/**
 * GLOBAL OPTIMIZATIONS
 */
add_action( 'init', 'ovb_disable_frontend_updates', 1 );
function ovb_disable_frontend_updates() {
    if ( is_admin() ) {
        return;
    }
    add_filter( 'pre_site_transient_update_core',   '__return_null' );
    add_filter( 'pre_site_transient_update_plugins','__return_null' );
    add_filter( 'pre_site_transient_update_themes', '__return_null' );
    remove_action( 'init', 'wp_version_check' );
    remove_action( 'init', 'wp_update_plugins' );
    remove_action( 'init', 'wp_update_themes' );
}

/**
 * GLOBAL WP/Elementor ASSETS & CONFIG
 */
add_action( 'wp_enqueue_scripts', 'ovb_enqueue_global_assets', 1 );
function ovb_enqueue_global_assets() {
    wp_enqueue_style( 'dashicons' );
    if ( ovb_is_woo_page() ) {
        wp_enqueue_script( 'jquery' );
        add_action( 'wp_head', 'ovb_elementor_frontend_config', 1 );
    }
}
function ovb_elementor_frontend_config() {
    echo '<script>window.elementorFrontendConfig = window.elementorFrontendConfig || {};</script>';
}

/**
 * MAIN PLUGIN ASSETS
 */
add_action( 'wp_enqueue_scripts', 'ovb_enqueue_main_assets', 20 );
function ovb_enqueue_main_assets() {
    $main_css = OVB_BOOKING_PATH . 'assets/css/main.css';
    $main_js  = OVB_BOOKING_PATH . 'assets/js/main.js';

    if ( file_exists( $main_css ) ) {
        wp_enqueue_style(
            'ovb-main-style',
            OVB_BOOKING_URL . 'assets/css/main.css',
            [],
            filemtime( $main_css )
        );
    }
    if ( file_exists( $main_js ) ) {
        wp_enqueue_script(
            'ovb-main-js',
            OVB_BOOKING_URL . 'assets/js/main.js',
            [ 'jquery' ],
            filemtime( $main_js ),
            true
        );
        wp_script_add_data( 'ovb-main-js', 'type', 'module' );
    }

    if ( is_product() ) {
        ovb_enqueue_product_assets();
    }
}

/**
 * PRODUCT PAGE ASSETS
 */
function ovb_enqueue_product_assets() {
    global $post;
    $product_id = $post->ID;

    // 1) Calendar core (Moment + Daterangepicker)
    ovb_enqueue_calendar_core();

    // 2) Custom daterange picker
    ovb_enqueue_daterange_picker();

    // 3) Gallery & slider
    // ovb_enqueue_gallery_assets();
    ovb_enqueue_slider_assets();

    // 4) Single-product scripts & styles
    ovb_enqueue_product_scripts( $product_id );
}

/**
 * Calendar core (Moment.js + Daterangepicker)
 */
function ovb_enqueue_calendar_core() {
    if ( ! wp_script_is( 'moment-js', 'enqueued' ) ) {
        wp_enqueue_script(
            'moment-js',
            'https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js',
            [],
            '2.29.4',
            true
        );
    }
    add_action( 'wp_footer', 'ovb_moment_local_fallback', 1000 );

    if ( ! wp_script_is( 'daterangepicker-js', 'enqueued' ) ) {
        wp_enqueue_style(
            'daterangepicker-css',
            'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css',
            [],
            '3.1.0'
        );
        wp_enqueue_script(
            'daterangepicker-js',
            'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js',
            [ 'jquery', 'moment-js' ],
            '3.1.0',
            true
        );
    }
}
function ovb_moment_local_fallback() {
    ?>
    <script>
    if ( typeof moment === "undefined" ) {
        var s = document.createElement('script');
        s.src    = '<?php echo esc_js( OVB_BOOKING_URL . "assets/utils/js/moment-local.min.js" ); ?>';
        s.async  = true;
        document.head.appendChild(s);
    }
    </script>
    <?php
}

/**
 * Custom DateRange Picker
 */
function ovb_enqueue_daterange_picker() {
    $css = OVB_BOOKING_PATH . 'assets/utils/css/ov-date.range.css';
    $js  = OVB_BOOKING_PATH . 'assets/utils/js/ov-date.range.js';

    if ( file_exists( $css ) && ! wp_style_is( 'ovb-daterange-css', 'enqueued' ) ) {
        wp_enqueue_style(
            'ovb-daterange-css',
            OVB_BOOKING_URL . 'assets/utils/css/ov-date.range.css',
            [ 'daterangepicker-css' ],
            filemtime( $css )
        );
    }
    if ( file_exists( $js ) && ! wp_script_is( 'ovb-daterange-js', 'enqueued' ) ) {
        wp_enqueue_script(
            'ovb-daterange-js',
            OVB_BOOKING_URL . 'assets/utils/js/ov-date.range.js',
            [ 'jquery', 'moment-js', 'daterangepicker-js' ],
            filemtime( $js ),
            true
        );
    }
}

/**
 * Slider Assets
 */
function ovb_enqueue_slider_assets() {
    $owl_css    = OVB_BOOKING_PATH . 'assets/utils/css/owl.carousel.min.css';
    $owl_theme  = OVB_BOOKING_PATH . 'assets/utils/css/owl.theme.default.min.css';
    $slider_css = OVB_BOOKING_PATH . 'assets/utils/css/ov.slider.css';
    $owl_js     = OVB_BOOKING_PATH . 'assets/utils/js/owl.carousel.min.js';
    $slider_js  = OVB_BOOKING_PATH . 'assets/utils/js/ov.slider.js';

    if ( file_exists( $owl_css ) && ! wp_style_is( 'owl-carousel', 'enqueued' ) ) {
        wp_enqueue_style( 'owl-carousel', OVB_BOOKING_URL . 'assets/utils/css/owl.carousel.min.css' );
    }
    if ( file_exists( $owl_theme ) && ! wp_style_is( 'owl-theme', 'enqueued' ) ) {
        wp_enqueue_style( 'owl-theme',    OVB_BOOKING_URL . 'assets/utils/css/owl.theme.default.min.css' );
    }
    if ( file_exists( $slider_css ) && ! wp_style_is( 'ovb-slider', 'enqueued' ) ) {
        wp_enqueue_style( 'ovb-slider',   OVB_BOOKING_URL . 'assets/utils/css/ov.slider.css' );
    }
    if ( file_exists( $owl_js ) && ! wp_script_is( 'owl-carousel-js', 'enqueued' ) ) {
        wp_enqueue_script( 'owl-carousel-js', OVB_BOOKING_URL . 'assets/utils/js/owl.carousel.min.js', [ 'jquery' ], '', true );
    }
    if ( file_exists( $slider_js ) && ! wp_script_is( 'ovb-slider-js', 'enqueued' ) ) {
        wp_enqueue_script( 'ovb-slider-js',   OVB_BOOKING_URL . 'assets/utils/js/ov.slider.js', [ 'jquery', 'owl-carousel-js' ], '', true );
    }
}

/**
 * Single-Product Scripts & Styles
 */
function ovb_enqueue_product_scripts( $product_id ) {
    $single_css = OVB_BOOKING_PATH . 'assets/css/ov-single.css';
    $single_js  = OVB_BOOKING_PATH . 'assets/js/ov-single.js';

    if ( file_exists( $single_css ) && ! wp_style_is( 'ovb-single-style', 'enqueued' ) ) {
        wp_enqueue_style(
            'ovb-single-style',
            OVB_BOOKING_URL . 'assets/css/ov-single.css',
            [],
            filemtime( $single_css )
        );
    }
    if ( file_exists( $single_js ) && ! wp_script_is( 'ovb-single-script', 'enqueued' ) ) {
        wp_enqueue_script(
            'ovb-single-script',
            OVB_BOOKING_URL . 'assets/js/ov-single.js',
            [ 'jquery', 'daterangepicker-js', 'wc-add-to-cart' ],
            filemtime( $single_js ),
            true
        );
    }
    foreach ( [ 'wc-add-to-cart', 'woocommerce', 'wc-single-product', 'wc-cart-fragments' ] as $h ) {
        if ( ! wp_script_is( $h, 'enqueued' ) ) {
            wp_enqueue_script( $h );
        }
    }

    ovb_localize_product_scripts( $product_id );
}

/**
 * Localize Single-Product Data
 */
function ovb_localize_product_scripts( $product_id ) {
    if ( ! wp_script_is( 'ovb-single-script', 'enqueued' ) ) {
        return;
    }

    wp_localize_script(
        'ovb-single-script',
        'ovbProductVars',
        [
            'ajax_url'          => esc_url( admin_url( 'admin-ajax.php' ) ),
            'nonce'             => wp_create_nonce( 'ovb_nonce' ),
            'product_id'        => absint( $product_id ),
            'calendar_data'     => ovb_get_clean_calendar_data( $product_id ),
            'price_types'       => get_post_meta( $product_id, '_ovb_price_types', true ) ?: [],
            'checkout_url'      => esc_url( wc_get_checkout_url() ),
            'cart_url'          => esc_url( wc_get_cart_url() ),
            'is_user_logged_in' => is_user_logged_in(),
            'start_date'        => sanitize_text_field( $_GET['ovb_start_date'] ?? '' ),
            'end_date'          => sanitize_text_field( $_GET['ovb_end_date']   ?? '' ),
            'i18n' => [
                'select_end_date' => __( 'Select end date', 'ov-booking' ),
                'select_dates'    => __( 'Please select dates', 'ov-booking' ),
                'loading'         => __( 'Loading...',        'ov-booking' ),
            ],
        ]
    );
}

/**
 * PRELOAD CRITICAL CSS ONLY (no JS)
 */
add_action( 'wp_head', 'ovb_preload_critical_css', 99 );
function ovb_preload_critical_css() {
    if ( is_product() ) {
        echo sprintf(
            '<link rel="preload" href="%1$sassets/css/ov-single.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">',
            esc_url( OVB_BOOKING_URL )
        );
        echo sprintf(
            '<noscript><link rel="stylesheet" href="%1$sassets/css/ov-single.css"></noscript>',
            esc_url( OVB_BOOKING_URL )
        );
    }
}

/**
 * CART
 */
add_action( 'wp_enqueue_scripts', 'ov_enqueue_cart_assets' );
function ov_enqueue_cart_assets() {
    // samo na WooCommerce cart strani
    if ( ! is_cart() ) {
        return;
    }

    // CSS
    wp_enqueue_style(
        'ovb-cart-style',
        OVB_BOOKING_URL . 'assets/css/ov-cart.css',
        [],
        filemtime( OVB_BOOKING_PATH . 'assets/css/ov-cart.css' )
    );

    // JS
    wp_enqueue_script(
        'ovb-cart-script',
        OVB_BOOKING_URL . 'assets/js/ov-cart.js',
        [ 'jquery', 'wc-cart' ],
        filemtime( OVB_BOOKING_PATH . 'assets/js/ov-cart.js' ),
        true
    );

    // lokalizacija — IMENA MORAJU BITI KAKO JS OČEKUJE
    wp_localize_script(
        'ovb-cart-script',  // handle
        'ovCartVars',       // JS var: window.ovCartVars
        [
            'ajax_url'            => esc_url( admin_url( 'admin-ajax.php' ) ),  // ovCartVars.ajax_url
            'nonce'               => wp_create_nonce( 'ovb_nonce' ),           // ovCartVars.nonce
            'emptyCartConfirmMsg' => __( 'Are you sure you want to empty your cart?', 'ov-booking' ), // ovCartVars.emptyCartConfirmMsg
            'checkoutUrl'         => esc_url( wc_get_checkout_url() ),       // ovCartVars.checkoutUrl
        ]
    );
}

/**
 * UTILITY FUNCTIONS
 */
function ovb_is_woo_page() {
    return function_exists( 'is_woocommerce' ) && (
        is_cart() ||
        is_checkout() ||
        is_account_page() ||
        is_wc_endpoint_url( 'order-received' ) ||
        is_product() ||
        is_shop() ||
        is_product_category() ||
        is_product_tag()
    );
}

function ovb_get_clean_calendar_data( $product_id ) {
    $raw = get_post_meta( $product_id, '_ovb_calendar_data', true );
    if ( empty( $raw ) ) {
        return [];
    }
    if ( is_string( $raw ) ) {
        $raw = json_decode( $raw, true );
    }
    return is_array( $raw ) ? $raw : [];
}
