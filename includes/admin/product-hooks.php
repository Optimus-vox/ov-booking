<?php
defined('ABSPATH') || exit;


// Force all products to be 'simple'
function ovb_force_all_products_to_simple() {
    $args = [
        'post_type'      => 'product',
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
    ];
    $product_ids = get_posts($args);

    foreach ($product_ids as $product_id) {
        wp_set_object_terms($product_id, 'simple', 'product_type', false);
    }
}

// Set all products to 'simple'
add_action('save_post_product', function($post_id) {
    if (get_post_type($post_id) === 'product') {
        wp_set_object_terms($post_id, 'simple', 'product_type', false);
    }
}, 20, 1);

// Create WooCommerce pages if they don't exist
function ovb_create_woocommerce_pages() {
    // Provera da li je WooCommerce aktiviran
    if (!class_exists('WooCommerce')) {
        if (function_exists('ovb_log_error')) {
            ovb_log_error("❌ WooCommerce nije aktiviran - preskačem kreiranje stranica", 'general');
        }
        return;
    }

    $pages = [
        'cart' => [
            'title' => 'Cart',
            'shortcode' => '[woocommerce_cart]',
            'tag' => 'woocommerce_cart'
        ],
        'checkout' => [
            'title' => 'Checkout',
            'shortcode' => '[woocommerce_checkout]',
            'tag' => 'woocommerce_checkout'
        ],
        'myaccount' => [
            'title' => 'My Account',
            'shortcode' => '[woocommerce_my_account]',
            'tag' => 'woocommerce_my_account'
        ],
        // 'shop' => [
        //     'title' => 'Shop',
        //     'shortcode' => '[products]',
        //     'tag' => 'products'
        // ],
    ];

    foreach ($pages as $key => $data) {
        // Provera da li postoji definisana stranica u WooCommerce postavkama
        $existing_id = wc_get_page_id($key);
        
        // Dobijanje post objekta ako postoji validan ID
        $existing_post = false;
        if ($existing_id > 0) {
            $existing_post = get_post($existing_id);
            
            // Provera da li je post zaista stranica i da je objavljena
            if (!$existing_post || $existing_post->post_type !== 'page' || $existing_post->post_status !== 'publish') {
                $existing_post = false;
            }
        }

        // Provera da li stranica ima shortcode
        $has_shortcode = false;
        if ($existing_post && !empty($existing_post->post_content)) {
            $has_shortcode = has_shortcode($existing_post->post_content, $data['tag']);
        }

        // Logika za kreiranje/ažuriranje
        if (!$existing_post) {
            // Provera da li postoji stranica sa istim slugom
            $page_slug = sanitize_title($data['title']);
            $page_exists = get_page_by_path($page_slug, OBJECT, 'page');
            
            if (!$page_exists) {
                // Kreiranje nove stranice
                $page_id = wp_insert_post([
                    'post_title'     => $data['title'],
                    'post_name'      => $page_slug,
                    'post_content'   => $data['shortcode'],
                    'post_status'    => 'publish',
                    'post_type'      => 'page',
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                ]);

                if (!is_wp_error($page_id) && $page_id > 0) {
                    // Ažuriranje WooCommerce postavki
                    update_option("woocommerce_{$key}_page_id", $page_id);
                    
                    if (function_exists('ovb_log_error')) {
                        ovb_log_error("✅ Kreirana WooCommerce stranica: {$key} (ID: {$page_id})", 'general');
                    }
                } elseif (function_exists('ovb_log_error')) {
                    ovb_log_error("❌ Greška pri kreiranju stranice {$key}: " . $page_id->get_error_message(), 'general');
                }
            } else {
                // Ako postoji stranica sa istim slugom ali nije u WooCommerce postavkama
                update_option("woocommerce_{$key}_page_id", $page_exists->ID);
                
                if (!$has_shortcode) {
                    wp_update_post([
                        'ID' => $page_exists->ID,
                        'post_content' => $data['shortcode']
                    ]);
                }
                
                if (function_exists('ovb_log_error')) {
                    ovb_log_error("🔗 Postojeća stranica povezana za {$key} (ID: {$page_exists->ID})", 'general');
                }
            }
        } elseif (!$has_shortcode) {
            // Ažuriranje postojeće stranice koja nema shortcode
            $update_result = wp_update_post([
                'ID' => $existing_post->ID,
                'post_content' => $data['shortcode']
            ]);
            
            if (!is_wp_error($update_result)) {
                if (function_exists('ovb_log_error')) {
                    ovb_log_error("🔧 Shortcode dodat na postojeću stranicu: {$key} (ID: {$existing_post->ID})", 'general');
                }
            } elseif (function_exists('ovb_log_error')) {
                ovb_log_error("⚠️ Greška pri ažuriranju stranice {$key}: " . $update_result->get_error_message(), 'general');
            }
        }
    }

    // Resetujemo permalinks nakon kreiranja stranica
    flush_rewrite_rules(false);
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

// Prikaz minimalne cene iz kalendara na single product (plug and play)
add_action('woocommerce_single_product_summary', function() {
    global $post;
    // Povuci calendarData iz post_meta
    $calendar_data = get_post_meta($post->ID, '_ovb_calendar_data', true);
    if (!$calendar_data || !is_array($calendar_data)) {
        // Ako nema custom cena, fallback na WC cenu
        global $product;
        if ($product) {
            echo '<div class="ovb-single-product-price" style="font-size:1.7em;font-weight:700;color:#363;margin-bottom:10px">';
            echo $product->get_price_html();
            echo '</div>';
        }
        return;
    }

    // Nađi najnižu dostupnu cenu za available dane
    $min_price = false;
    foreach ($calendar_data as $d) {
        if (
            isset($d['price'])
            && is_numeric($d['price'])
            && (!isset($d['status']) || $d['status'] === 'available')
        ) {
            if ($min_price === false || $d['price'] < $min_price) {
                $min_price = $d['price'];
            }
        }
    }
    if ($min_price !== false) {
        echo '<div class="ovb-single-product-price" style="font-size:1.7em;font-weight:700;color:#363;margin-bottom:10px">';
        echo 'Od <b>' . esc_html($min_price) . '€</b> po noći';
        echo '</div>';
    }
}, 8); // Pre naslova proizvoda
