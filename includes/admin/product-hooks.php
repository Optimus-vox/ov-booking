<?php
defined('ABSPATH') || exit;



// Create WooCommerce pages if they don't exist
function ovb_create_woocommerce_pages() {
    $pages = [
        'cart'     => ['title' => 'Cart',     'shortcode' => '[woocommerce_cart]'],
        'checkout' => ['title' => 'Checkout', 'shortcode' => '[woocommerce_checkout]'],
        'myaccount'=> ['title' => 'My Account','shortcode' => '[woocommerce_my_account]'],
        'shop'     => ['title' => 'Shop',     'shortcode' => ''],
    ];

    foreach ($pages as $key => $data) {
        $existing_id = wc_get_page_id($key);
        $existing_post = $existing_id > 0 ? get_post($existing_id) : false;

        if (!$existing_post || $existing_post->post_status !== 'publish') {
            $page_exists_by_slug = get_page_by_path(sanitize_title($data['title']));

            if (!$page_exists_by_slug) {
                $page_id = wp_insert_post([
                    'post_title'   => $data['title'],
                    'post_content' => $data['shortcode'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ]);

                if ($page_id && !is_wp_error($page_id)) {
                    update_option("woocommerce_{$key}_page_id", $page_id);
                    if (function_exists('ov_log_error')) {
                        ov_log_error("✅ Created WooCommerce page: {$key}", 'general');
                    }
                }
            }
        }
    }
}
// Reset all product prices on activation
function ovb_reset_all_product_prices() {
    $args = [
        'post_type'      => ['product','product_variation'],
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
    ];
    foreach (get_posts($args) as $product_id) {
        foreach (['_regular_price','_sale_price','_price','_min_variation_price','_max_variation_price','_min_price','_max_price'] as $meta) {
            update_post_meta($product_id, $meta, '0');
        }
    }
}
// Remove price column from product list
add_filter('manage_edit-product_columns', function($columns) {
    unset($columns['price']);
    return $columns;
}, 20);

// Disable shipping on activation
function disable_woocommerce_shipping_on_activation() {
    update_option('woocommerce_ship_to_countries','disabled');
    update_option('woocommerce_calc_shipping','no');
    update_option('woocommerce_ship_to_destination','billing_only');
    update_option('woocommerce_api_enable_shipping_zones','no');
}