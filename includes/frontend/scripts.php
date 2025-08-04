<?php
defined('ABSPATH') || exit;

/**
 * ======================================
 *  OV Booking Frontend Scripts & Assets
 * ======================================
 */

/** OPTIONAL LOGGER LOADER */
if ( file_exists( dirname( __DIR__ ) . '/helpers/logger.php' ) ) {
    require_once dirname( __DIR__ ) . '/helpers/logger.php';
}

/** GLOBAL OPTIMIZATIONS */
function ovb_disable_frontend_updates() {
    add_filter( 'pre_site_transient_update_core',   '__return_null' );
    add_filter( 'pre_site_transient_update_plugins','__return_null' );
    add_filter( 'pre_site_transient_update_themes', '__return_null' );
    remove_action( 'init', 'wp_version_check' );
    remove_action( 'init', 'wp_update_plugins' );
    remove_action( 'init', 'wp_update_themes' );
}
add_action( 'init', 'ovb_disable_frontend_updates', 1 );

/** SAFE ELEMENTOR CONFIG - only if Elementor is present */
if ( class_exists( \Elementor\Plugin::class ) ) {
    add_action( 'wp_head', 'ovb_safe_elementor_config', 5 );
}
if ( ! function_exists( 'ovb_safe_elementor_config' ) ) {
    function ovb_safe_elementor_config() {
        if ( ovb_is_woo_page() && ! ovb_is_elementor_disabled_page() ) {
            ?>
            <script>
            if (typeof window.elementorFrontendConfig === 'undefined') {
                window.elementorFrontendConfig = {
                    environmentMode: {
                        edit: false,
                        wpPreview: false,
                        isScriptDebug: false
                    },
                    i18n: {
                        shareOnFacebook: "Share on Facebook",
                        shareOnTwitter: "Share on Twitter",
                        pinIt: "Pin it",
                        download: "Download",
                        downloadImage: "Download image",
                        fullscreen: "Fullscreen",
                        zoom: "Zoom",
                        share: "Share",
                        playVideo: "Play Video",
                        previous: "Previous",
                        next: "Next",
                        close: "Close"
                    },
                    is_rtl: false,
                    breakpoints: { xs: 0, sm: 480, md: 768, lg: 1025, xl: 1440, xxl: 1600 },
                    responsive: {
                        breakpoints: {
                            mobile: { label: "Mobile", value: 767, direction: "max", is_enabled: true },
                            mobile_extra: { label: "Mobile Extra", value: 880, direction: "max", is_enabled: false },
                            tablet: { label: "Tablet", value: 1024, direction: "max", is_enabled: true },
                            tablet_extra: { label: "Tablet Extra", value: 1200, direction: "max", is_enabled: false },
                            laptop: { label: "Laptop", value: 1366, direction: "max", is_enabled: false },
                            widescreen: { label: "Widescreen", value: 2400, direction: "min", is_enabled: false }
                        }
                    },
                    version: "3.30.3",
                    is_static: false,
                    experimentalFeatures: {},
                    urls: { assets: "<?php echo esc_url( plugins_url( 'assets/', ELEMENTOR__FILE__ ) ); ?>" },
                    settings: { page: [], editorPreferences: [] },
                    kit: { active_breakpoints: ["viewport_mobile", "viewport_tablet"], global_image_lightbox: "yes" },
                    post: { id: <?php echo get_the_ID() ?: 0; ?>, title: "<?php echo esc_js( get_the_title() ); ?>", excerpt: "" }
                };
            }
            </script>
            <?php
        }
    }
}

/** HELPER - check if Elementor should be disabled on this page */
function ovb_is_elementor_disabled_page() {
    return is_singular('product')
        || ( function_exists('is_cart') && is_cart() )
        || ( function_exists('is_checkout') && is_checkout() )
        || ( function_exists('is_account_page') && is_account_page() )
        || ( function_exists('is_order_received_page') && is_order_received_page() );
}

/** GLOBAL WP ASSETS & CONFIG */
add_action( 'wp_enqueue_scripts', 'ovb_enqueue_global_assets', 1 );
function ovb_enqueue_global_assets() {
    wp_enqueue_style( 'dashicons' );
    if ( ovb_is_woo_page() ) {
        wp_enqueue_script( 'jquery' );
        // ensure admin-ajax is available
        wp_enqueue_script( 'wp-util' );
    }
}

/** MAIN PLUGIN ASSETS */
add_action( 'wp_enqueue_scripts', 'ovb_enqueue_main_assets', 20 );
function ovb_enqueue_main_assets() {
    $main_css = OVB_BOOKING_PATH . 'assets/css/main.css';
    $main_js  = OVB_BOOKING_PATH . 'assets/js/main.js';

    if ( file_exists( $main_css ) ) {
        wp_enqueue_style( 'ovb-main-style',
            OVB_BOOKING_URL . 'assets/css/main.css',
            [],
            filemtime( $main_css )
        );
    }
    if ( file_exists( $main_js ) ) {
        wp_enqueue_script( 'ovb-main-js',
            OVB_BOOKING_URL . 'assets/js/main.js',
            [ 'jquery', 'wp-util' ],
            filemtime( $main_js ),
            true
        );
        wp_script_add_data( 'ovb-main-js', 'type', 'module' );
    }

    if ( is_product() ) {
        ovb_enqueue_product_assets();
    }
}

/** PRODUCT PAGE ASSETS */
function ovb_enqueue_product_assets() {
    global $post;
    $product_id = $post->ID;

    // 1) Calendar core (Moment + Daterangepicker)
    ovb_enqueue_calendar_core();

    // 2) Custom daterange picker
    ovb_enqueue_daterange_picker();

    // 3) Slider assets
    ovb_enqueue_slider_assets();

    // 4) Single-product scripts & styles
    ovb_enqueue_product_scripts( $product_id );
    
    // 5) Conflict resolution
    add_action( 'wp_footer', 'ovb_resolve_product_conflicts', 999 );
}

/** CONFLICT RESOLUTION FOR PRODUCT PAGES */
function ovb_resolve_product_conflicts() {
    ?>
    <script>
    (function($) {
        'use strict';
        
        // Remove duplicate lazyload observer
        if (typeof window.lazyloadRunObserver !== 'undefined') {
            delete window.lazyloadRunObserver;
        }
        
        // Elementor assets fallback
        if (typeof elementorFrontend !== 'undefined' && elementorFrontend.config) {
            elementorFrontend.config.urls = elementorFrontend.config.urls || {};
            elementorFrontend.config.urls.assets = elementorFrontend.config.urls.assets || '<?php echo esc_js( plugins_url( "assets/", ELEMENTOR__FILE__ ) ); ?>';
        }

        // Prevent duplicate jQuery-ready handlers
        var originalReady = $.fn.ready, readyFired = false;
        $.fn.ready = function(fn) {
            if (readyFired) {
                fn.call(document, $);
                return this;
            }
            return originalReady.call(this, fn);
        };
        $(document).ready(function(){ readyFired = true; });
    })(jQuery);
    </script>
    <?php
}

/** Calendar core (Moment.js + Daterangepicker) */
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

/** Custom DateRange Picker */
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

/** Slider Assets */
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
        wp_enqueue_style( 'owl-theme', OVB_BOOKING_URL . 'assets/utils/css/owl.theme.default.min.css' );
    }
    if ( file_exists( $slider_css ) && ! wp_style_is( 'ovb-slider', 'enqueued' ) ) {
        wp_enqueue_style( 'ovb-slider', OVB_BOOKING_URL . 'assets/utils/css/ov.slider.css' );
    }
    if ( file_exists( $owl_js ) && ! wp_script_is( 'owl-carousel-js', 'enqueued' ) ) {
        wp_enqueue_script( 'owl-carousel-js', OVB_BOOKING_URL . 'assets/utils/js/owl.carousel.min.js', [ 'jquery' ], '', true );
    }
    if ( file_exists( $slider_js ) && ! wp_script_is( 'ovb-slider-js', 'enqueued' ) ) {
        wp_enqueue_script( 'ovb-slider-js', OVB_BOOKING_URL . 'assets/utils/js/ov.slider.js', [ 'jquery', 'owl-carousel-js' ], '', true );
    }
}

/** Single-Product Scripts & Styles */
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
    
    // Safely enqueue WooCommerce scripts
    $wc_scripts = [ 'wc-add-to-cart', 'woocommerce', 'wc-single-product', 'wc-cart-fragments' ];
    foreach ( $wc_scripts as $handle ) {
        if ( ! wp_script_is( $handle, 'enqueued' ) ) {
            wp_enqueue_script( $handle );
        }
    }

    // Localize for AJAX
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

/** PRELOAD CRITICAL CSS ONLY (no JS) */
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

/** CART ASSETS */
add_action( 'wp_enqueue_scripts', 'ov_enqueue_cart_assets' );
function ov_enqueue_cart_assets() {
    if ( ! is_cart() ) {
        return;
    }

    wp_enqueue_style(
        'ovb-cart-style',
        OVB_BOOKING_URL . 'assets/css/ov-cart.css',
        [],
        filemtime( OVB_BOOKING_PATH . 'assets/css/ov-cart.css' )
    );

    wp_enqueue_script(
        'ovb-cart-script',
        OVB_BOOKING_URL . 'assets/js/ov-cart.js',
        [ 'jquery', 'wc-cart' ],
        filemtime( OVB_BOOKING_PATH . 'assets/js/ov-cart.js' ),
        true
    );

    wp_localize_script(
        'ovb-cart-script',
        'ovCartVars',
        [
            'ajax_url'            => esc_url( admin_url( 'admin-ajax.php' ) ),
            'nonce'               => wp_create_nonce( 'ovb_nonce' ),
            'emptyCartConfirmMsg' => __( 'Are you sure you want to empty your cart?', 'ov-booking' ),
            'checkoutUrl'         => esc_url( wc_get_checkout_url() ),
        ]
    );
    
    // Cart conflict resolution
    add_action( 'wp_footer', 'ovb_resolve_cart_conflicts', 999 );
}

/** CART CONFLICT RESOLUTION */
function ovb_resolve_cart_conflicts() {
    ?>
    <script>
    (function($) {
        'use strict';
        if (typeof window.lazyloadRunObserver !== 'undefined') {
            delete window.lazyloadRunObserver;
        }
    })(jQuery);
    </script>
    <?php
}

/** CHECKOUT PAGE CSS */
add_action( 'wp_enqueue_scripts', 'ovb_enqueue_checkout_assets', 20 );
function ovb_enqueue_checkout_assets() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
        return;
    }

    $css_file = OVB_BOOKING_PATH . 'assets/css/ov-checkout.css';

    if ( file_exists( $css_file ) ) {
        wp_enqueue_style(
            'ovb-checkout-style',
            OVB_BOOKING_URL . 'assets/css/ov-checkout.css',
            [],
            filemtime( $css_file )
        );
    }
    
    // Checkout conflict resolution
    add_action( 'wp_footer', 'ovb_resolve_checkout_conflicts', 999 );
}

/** CHECKOUT CONFLICT RESOLUTION */
function ovb_resolve_checkout_conflicts() {
    ?>
    <script>
    (function($) {
        'use strict';
        if (typeof window.lazyloadRunObserver !== 'undefined') {
            delete window.lazyloadRunObserver;
        }
    })(jQuery);
    </script>
    <?php
}

/** MY ACCOUNT PAGE CSS */
add_action( 'wp_enqueue_scripts', 'ovb_enqueue_my_account_assets', 20 );
function ovb_enqueue_my_account_assets() {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
        return;
    }

    $css_file = OVB_BOOKING_PATH . 'assets/css/ov-my-account.css';

    if ( file_exists( $css_file ) ) {
        wp_enqueue_style(
            'ovb-my-account-style',
            OVB_BOOKING_URL . 'assets/css/ov-my-account.css',
            [],
            filemtime( $css_file )
        );
    }
    
    // My Account conflict resolution
    add_action( 'wp_footer', 'ovb_resolve_account_conflicts', 999 );
}

/** THANK YOU PAGE CSS */
add_action( 'wp_enqueue_scripts', 'ovb_enqueue_thankyou_assets', 20 );
function ovb_enqueue_thankyou_assets() {
    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
        $css_file = OVB_BOOKING_PATH . 'assets/css/ov-thankyou.css';
        if ( file_exists( $css_file ) ) {
            wp_enqueue_style(
                'ovb-thankyou-style',
                OVB_BOOKING_URL . 'assets/css/ov-thankyou.css',
                [], 
                filemtime( $css_file )
            );
        }
    }
}

/** MY ACCOUNT CONFLICT RESOLUTION */
function ovb_resolve_account_conflicts() {
    ?>
    <script>
    (function($) {
        'use strict';
        if (typeof window.lazyloadRunObserver !== 'undefined') {
            delete window.lazyloadRunObserver;
        }
    })(jQuery);
    </script>
    <?php
}

/** UTILITY FUNCTIONS */
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

/** SHOP PAGE SPECIFIC HANDLING */
add_action( 'wp_enqueue_scripts', 'ovb_handle_shop_page', 25 );
function ovb_handle_shop_page() {
    if ( ! function_exists( 'is_shop' ) || ! is_shop() ) {
        return;
    }
    
    add_action( 'wp_footer', function() {
        ?>
        <script>
        (function($) {
            'use strict';
            $(document).ready(function() {
                $('.elementor-widget-woocommerce-products').each(function(i){
                    if(i>0)$(this).remove();
                });
                $('.woocommerce ul.products li.product').each(function(){
                    var $t=$(this),
                        title=$t.find('.woocommerce-loop-product__title').text(),
                        dup=$('.woocommerce ul.products li.product').filter(function(){
                            return $(this).find('.woocommerce-loop-product__title').text()===title && this!==$t[0];
                        });
                    if(dup.length)dup.remove();
                });
            });
        })(jQuery);
        </script>
        <?php
    }, 999 );
}
