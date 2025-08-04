<?php
defined('ABSPATH') || exit;

/**
 * ======================================
 *  TEMPLATE HOOKS & ELEMENTOR CONTROL
 * ======================================
 */

/**
 * ONEMOGUĆI WP UPDATE CHECKS NA FRONTEND-U
 */
add_action( 'init', 'ovb_disable_frontend_update_checks', 1 );
function ovb_disable_frontend_update_checks() {
    if ( ! is_admin() ) {
        add_filter( 'pre_site_transient_update_core',    '__return_null' );
        add_filter( 'pre_site_transient_update_plugins', '__return_null' );
        add_filter( 'pre_site_transient_update_themes',  '__return_null' );

        remove_action( 'init',             'wp_version_check' );
        remove_action( 'init',             'wp_update_plugins' );
        remove_action( 'init',             'wp_update_themes' );
        remove_action( 'load-plugins.php', 'wp_update_plugins' );
        remove_action( 'load-themes.php',  'wp_update_themes' );
    }
}

/**
 * UKLONI WOOCOMMERCE DEFAULT HOOKS
 */
remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);
remove_action( 'woocommerce_product_thumbnails', 'woocommerce_show_product_thumbnails', 20 );

/**
 * Customizing single product summary hooks
 */
add_action('woocommerce_single_product_summary', 'customizing_single_product_summary_hooks', 2);
function customizing_single_product_summary_hooks(){
    remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
    remove_action('woocommerce_single_product_summary','woocommerce_template_single_price',10);
}

/**
 * UKLONI PRODUCT SHORT DESCRIPTION META BOX
 */
add_action('add_meta_boxes', 'ovb_remove_short_description', 999);
function ovb_remove_short_description() {
    remove_meta_box( 'postexcerpt', 'product', 'normal');
}

/**
 * UNIFIED TEMPLATE OVERRIDE
 */
add_filter('template_include', 'ovb_override_templates', 99);
function ovb_override_templates($template) {
    if ( is_singular('product') ) {
        $tpl = OVB_BOOKING_PATH . 'templates/woocommerce/ov-single-product.php';
        if ( file_exists($tpl) ) return $tpl;
    }

    if ( function_exists('is_cart') && is_cart() ) {
        $tpl = OVB_BOOKING_PATH . 'templates/woocommerce/ov-cart.php';
        if ( file_exists($tpl) ) return $tpl;
    }

    if ( function_exists('is_checkout') && is_checkout() && ! is_order_received_page() ) {
        $tpl = OVB_BOOKING_PATH . 'templates/woocommerce/ov-checkout.php';
        if ( file_exists($tpl) ) return $tpl;
    }

    if ( function_exists('is_order_received_page') && is_order_received_page() ) {
        $tpl = OVB_BOOKING_PATH . 'templates/woocommerce/ov-thank-you.php';
        if ( file_exists($tpl) ) return $tpl;
    }

    return $template;
}

/**
 * MY ACCOUNT VIEW ORDER TEMPLATE OVERRIDE
 */
add_filter('woocommerce_locate_template', 'ovb_override_view_order_template', 10, 3);
function ovb_override_view_order_template($template, $template_name, $template_path) {
    if ($template_name === 'myaccount/view-order.php') {
        $plugin_template = OVB_BOOKING_PATH . 'templates/woocommerce/view-order.php';
        if ( file_exists($plugin_template) ) {
            return $plugin_template;
        }
    }
    return $template;
}

/**
 * SHOP PAGE ELEMENTOR DUPLICATE PREVENTION
 */
add_action('wp', 'ovb_prevent_shop_duplicates', 5);
function ovb_prevent_shop_duplicates() {
    if ( ! function_exists('is_shop') || ! is_shop() ) {
        return;
    }
    if ( class_exists('\Elementor\Plugin') ) {
        add_filter('elementor/query/query_results', 'ovb_filter_elementor_shop_query', 10, 2);
        add_filter('elementor/widget/render_content', 'ovb_limit_elementor_products', 10, 2);
    }
}

function ovb_filter_elementor_shop_query($query, $widget) {
    if ( is_shop() && isset($widget->get_settings()['posts_per_page']) ) {
        $per_page = wc_get_default_products_per_row() * wc_get_default_product_rows_per_page();
        $query->set('posts_per_page', $per_page);
    }
    return $query;
}

function ovb_limit_elementor_products($content, $widget) {
    if ( ! is_shop() ) {
        return $content;
    }
    $name = $widget->get_name();
    if ( in_array($name, ['woocommerce-products','products']) ) {
        static $count = 0;
        $count++;
        if ( $count > 1 ) {
            return '<!-- OVB: Duplicate products widget hidden -->';
        }
    }
    return $content;
}

/**
 * DODATNA SHOP PAGE OPTIMIZACIJA
 */
add_action('woocommerce_before_shop_loop', 'ovb_optimize_shop_loop', 5);
function ovb_optimize_shop_loop() {
    if ( ! wp_cache_get('ovb_shop_products_cached') ) {
        wp_cache_set('ovb_shop_products_cached', true, 'ovb', 300);
    }

    // *** FIXED: safe post_class filter ***
    add_filter('post_class', function($classes, $class, $post_id){
        if ( is_shop() && get_post_type($post_id) === 'product' ) {
            $classes[] = 'ovb-shop-product';
        }
        return $classes;
    }, 10, 3);
}

/**
 * EMERGENCY ELEMENTOR CLEANUP ZA PROBLEMATIČNE STRANICE
 */
add_action('wp_head', 'ovb_emergency_elementor_cleanup', 1);
function ovb_emergency_elementor_cleanup() {
    $pages = [
        'is_singular'=> ['product'],
        'is_cart', 'is_checkout', 'is_account_page', 'is_order_received_page'
    ];
    $cleanup = false;

    foreach ($pages['is_singular'] as $pt) {
        if ( is_singular($pt) ) $cleanup = true;
    }
    foreach (['is_cart','is_checkout','is_account_page','is_order_received_page'] as $fn) {
        if ( function_exists($fn) && call_user_func($fn) ) $cleanup = true;
    }

    if ( $cleanup && class_exists('\Elementor\Plugin') ) {
        ?>
        <script>
        (function(){
            'use strict';
            if ( typeof elementorFrontend !== 'undefined' ) {
                window.ovbElementorBackup = elementorFrontend.config || {};
                elementorFrontend.config = {
                    urls:{assets:'<?php echo esc_js( plugins_url("assets/", ELEMENTOR__FILE__) );?>'},
                    environmentMode:{edit:false,wpPreview:false},
                    i18n:{},is_rtl:false,
                    breakpoints:{xs:0,sm:480,md:768,lg:1025,xl:1440,xxl:1600},
                    version:'3.30.3',is_static:false
                };
            }
            if ( typeof window.lazyloadRunObserver !== 'undefined' ) {
                window.lazyloadRunObserver = function(){return;};
            }
        })();
        </script>
        <?php
    }
}

/**
 * POST-LOAD CLEANUP
 */
add_action('wp_footer', 'ovb_post_load_cleanup', 999);
function ovb_post_load_cleanup() {
    ?>
    <script>
    (function($){
        'use strict';
        $(document).ready(function(){
            if ( window.ovbElementorBackup && typeof elementorFrontend !== 'undefined' ) {
                elementorFrontend.config = $.extend({}, elementorFrontend.config, window.ovbElementorBackup);
                delete window.ovbElementorBackup;
            }
            if ( typeof window.lazyloadRunObserver !== 'undefined' ) {
                delete window.lazyloadRunObserver;
            }
        });
    })(jQuery);
    </script>
    <?php
}
