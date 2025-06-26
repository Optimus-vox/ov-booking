<?php
defined('ABSPATH') || exit;

// Shipping
//Onemogući shipping address u checkout-u
add_filter('woocommerce_cart_needs_shipping_address','__return_false');
//Sakrij shipping calculator
add_filter('woocommerce_shipping_calculator_enable','__return_false');
//Ukloni sve shipping metode
add_filter('woocommerce_package_rates','ov_hide_all_shipping_rates',10,2);
function ov_hide_all_shipping_rates($rates,$package){ return []; }
//Ukloni shipping polja iz checkout forme
add_filter('woocommerce_checkout_fields','ov_remove_shipping_fields');
function ov_remove_shipping_fields($fields){ $fields['shipping']=[]; return $fields; }

// Price i add to cart | Ukloni Add to cart + price na shop archive
add_action('wp','ov_remove_add_to_cart_and_price_on_shop');
function ov_remove_add_to_cart_and_price_on_shop(){
    if(is_shop()||is_product_taxonomy()){
        remove_action('woocommerce_after_shop_loop_item','woocommerce_template_loop_add_to_cart',10);
        remove_action('woocommerce_after_shop_loop_item_title','woocommerce_template_loop_price',10);
    }
}

// Subtotal | Uklanjanje Subtotal reda u cart tabeli
add_filter('woocommerce_cart_subtotal','__return_empty_string',10,3);
add_filter('woocommerce_cart_totals_subtotal_label','__return_false');
add_filter('woocommerce_cart_totals_subtotal_html','__return_empty_string');


// Redirect to cart after successful addition
add_filter('woocommerce_cart_redirect_after_add', '__return_true');