<?php
defined('ABSPATH') || exit;

if (!function_exists('ovb_elementor_has_location')) {
    function ovb_elementor_has_location(string $location): bool {
        if (!did_action('elementor/loaded')) return false;
        $plugin = \Elementor\Plugin::$instance ?? null;
        if (!$plugin || !isset($plugin->theme_builder)) return false;
        $docs = $plugin->theme_builder->get_conditions_manager()->get_documents_for_location($location);
        return !empty($docs);
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

    // Single Product → UVEK naš
    if ( is_singular('product') ) {
        $tpl = OVB_BOOKING_PATH . 'templates/woocommerce/ov-single-product.php';
        if ( file_exists($tpl) ) return $tpl;
        return $template;
    }

    // Shop / product archive → ako Elementor ima template, propusti ga
    if ( function_exists('is_shop') && (is_shop() || is_product_taxonomy()) ) {
        if ( ovb_elementor_has_location('product-archive') ) {
            return $template; // Elementor controla arhive
        }
        // Nema Elementor arhive → koristi temu (ili ovde dodaš svoj shop template ako ga imaš)
        return $template;
    }

    // Cart → naš
    if ( function_exists('is_cart') && is_cart() ) {
        $tpl = OVB_BOOKING_PATH . 'templates/woocommerce/ov-cart.php';
        if ( file_exists($tpl) ) return $tpl;
        return $template;
    }

    // Checkout (bez thank you) → naš
    if ( function_exists('is_checkout') && is_checkout() && ! is_order_received_page() ) {
        $tpl = OVB_BOOKING_PATH . 'templates/woocommerce/ov-checkout.php';
        if ( file_exists($tpl) ) return $tpl;
        return $template;
    }

    // Thank you → naš
    if ( function_exists('is_order_received_page') && is_order_received_page() ) {
        $tpl = OVB_BOOKING_PATH . 'templates/woocommerce/ov-thank-you.php';
        if ( file_exists($tpl) ) return $tpl;
        return $template;
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
 * =========================
 * OVB — SHOP GRID TWEAKS
 * - Ukloni Add to Cart iz shop/cat/tag listinga
 * - Sakrij standardnu Woo cenu u listingu
 * - Prikaži "min / night" iz _ovb_calendar_data
 * =========================
 */

// 1) Ukloni Add to Cart dugme u listingu (ne dira single)
add_action('wp', function () {
    if (is_admin()) return;
    remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
    // dodatne varijacije tema:
    remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 15);
    remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_add_to_cart', 10);
}, 20);

// 2) Sakrij standardni Woo price HTML samo na arhivama (shop/kategorije/tagovi)
add_filter('woocommerce_get_price_html', function ($price, $product) {
    if (is_admin() && !wp_doing_ajax()) return $price;
    if (is_product()) return $price; // ne diramo single
    if (is_shop() || is_product_taxonomy() || is_product_category() || is_product_tag()) {
        return ''; // nema standardne cene u gridu
    }
    return $price;
}, 20, 2);


// 4) Ubaci "min / night" ispod naslova u gridu
add_action('woocommerce_after_shop_loop_item_title', function () {
    if (is_product()) return; // ne na single
    $show_opt = get_option('ovb_shop_show_min_price', '1') === '1';
    $show = (bool) apply_filters('ovb_show_min_price_on_shop', $show_opt);
    if (!$show) return;

    global $product;
    if (!($product instanceof WC_Product)) return;

    $days = absint(get_option('ovb_shop_min_price_window_days', 365));
    if ($html !== '') {
        echo '<div class="price ovb-price">'.$html.'</div>';
    }
}, 7);