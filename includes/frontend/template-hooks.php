<?php
defined('ABSPATH') || exit;


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

//checkot form prosirenje

// add_action('woocommerce_after_checkout_billing_form', 'ovb_render_custom_checkout_blocks', 6);
// if ( ! function_exists('ovb_render_custom_checkout_blocks') ) {
//     function ovb_render_custom_checkout_blocks() {
//         $file = __DIR__ . '/custom-checkout-blocks.php'; // includes/frontend/custom-checkout-blocks.php
//         if ( file_exists($file) ) {
//             include $file;
//         }
//     }
// }
//test
// === Render unified sekcija (firma / druga osoba / gosti) ispod billing polja ===
add_action('woocommerce_after_checkout_billing_form', 'ovb_render_unified_checkout_sections', 6);
// Fallback ako tema/templejt ne puca prethodni hook:
add_action('woocommerce_checkout_after_customer_details', 'ovb_render_unified_checkout_sections', 6);

function ovb_render_unified_checkout_sections() {
    static $done = false;
    if ($done) return;

    // samo na glavnoj checkout stranici (ne na thankyou/pay endpointima)
    if (function_exists('is_checkout') && is_checkout()) {
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) return;
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) return;

        // Putanja do fajla
        $file = defined('OVB_BOOKING_PATH')
            ? trailingslashit(OVB_BOOKING_PATH) . 'templates/checkout/custom-checkout-blocks.php'
            : plugin_dir_path(__FILE__) . 'templates/checkout/custom-checkout-blocks.php';

        if (file_exists($file)) {
            $done = true;
            include $file;
        } else {
            if (function_exists('ovb_log_error')) {
                ovb_log_error('custom-checkout-blocks.php not found at: ' . $file, 'checkout');
            }
        }
    }
}
//test



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