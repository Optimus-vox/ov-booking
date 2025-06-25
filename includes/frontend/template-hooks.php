<?php
defined('ABSPATH') || exit;



// woocommerce disable default 
remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);

remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
add_action('woocommerce_single_product_summary', 'customizing_single_product_summary_hooks', 2  );
function customizing_single_product_summary_hooks(){
    remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
    remove_action('woocommerce_single_product_summary','woocommerce_template_single_price',10  );
}
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);

remove_action('woocommerce_checkout_billing', 'woocommerce_checkout_billing', 10);
remove_action('woocommerce_checkout_shipping', 'woocommerce_checkout_shipping', 10);
remove_action('woocommerce_checkout_order_review', 'woocommerce_order_review', 10);
remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);


// Uklanja WooCommerce defaultnu galeriju ispod slike
remove_action( 'woocommerce_product_thumbnails', 'woocommerce_show_product_thumbnails', 20 );


// remove product short description from single product
function remove_short_description() {
    remove_meta_box( 'postexcerpt', 'product', 'normal');
}

add_action('add_meta_boxes', 'remove_short_description', 999);

// Unified template override za Single, Cart, Checkout, Thank You
add_filter('template_include', function($template) {
    if (is_singular('product')) {
        $tpl = OV_BOOKING_PATH . 'templates/woocommerce/ov-single-product.php';
        if (file_exists($tpl)) return $tpl;
    }
    if (function_exists('is_cart') && is_cart()) {
        $tpl = OV_BOOKING_PATH . 'templates/woocommerce/ov-cart.php';
        if (file_exists($tpl)) return $tpl;
    }
    if (function_exists('is_checkout') && is_checkout()) {
        $tpl = OV_BOOKING_PATH . 'templates/woocommerce/ov-checkout.php';
        if (file_exists($tpl)) return $tpl;
    }
    if (function_exists('is_order_received') && is_order_received()) {
        $tpl = OV_BOOKING_PATH . 'templates/woocommerce/ov-thank-you.php';
        if (file_exists($tpl)) return $tpl;
    }
    return $template;
}, 99);

