<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @package ov-booking
 */

defined('ABSPATH') || exit;


// Spreči direktan pristup
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Uklanjanje svih custom meta podataka po potrebi
$meta_keys = [
    '_apartment_info_icons',
    '_apartment_rules_icons',
    '_ov_custom_editor',
    '_apartment_additional_info',
    '_product_testimonials',
    '_ov_calendar_data',
    '_ov_price_types'
];

// Uklanjanje meta za svaki proizvod
$args = [
    'post_type' => 'product',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids'
];
$products = get_posts($args);

foreach ($products as $post_id) {
    foreach ($meta_keys as $meta_key) {
        delete_post_meta($post_id, $meta_key);
    }
}

// (Opcionalno) Obriši i plugin opcije ako ih dodaš u future-u
delete_option('ov_booking_settings');
delete_option( 'ov_booking_display_mode' );
// delete_option('ov_booking_version');

