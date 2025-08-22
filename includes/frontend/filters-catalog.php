<?php
defined('ABSPATH') || exit;

/**
 * Primeni GET filtere (ovb_date, ovb_guests, ovb_rooms, ovb_city, ovb_country, category)
 * nad WooCommerce katalogom (shop, product taxonomy) kroz pre_get_posts.
 */
add_action('pre_get_posts', function ($q) {
    if (is_admin() || ! $q->is_main_query()) return;

    // Shop i product taxonomy
    if ( ! (function_exists('is_shop') && (is_shop() || is_product_taxonomy())) ) return;

    // Radimo isključivo nad proizvodima
    $pt = $q->get('post_type');
    if ( ! $pt ) { $q->set('post_type', 'product'); }
    elseif (is_array($pt) && !in_array('product', $pt, true)) { return; }
    elseif (is_string($pt) && $pt !== 'product') { return; }

    if ( ! function_exists('ovb_get_apartments_query_args') ) return;

    // $_GET → canonical params → WP_Query args
    $params = array_map('wp_unslash', $_GET ?? []);
    $args   = ovb_get_apartments_query_args($params, 'catalog');

    foreach (['meta_query','tax_query','orderby','order','posts_per_page','post__in'] as $k) {
        if (array_key_exists($k, $args) && $args[$k] !== null) {
            $q->set($k, $args[$k]);
        }
    }
}, 20);