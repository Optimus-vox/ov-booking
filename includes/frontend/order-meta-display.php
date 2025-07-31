<?php
defined('ABSPATH') || exit;

/**
 * Prikaz i obrada custom order meta podataka
 * - sakriva sirove meta ključeve iz wc-item-meta
 * - prikazuje formatirano ispod naziva proizvoda
 */

// Sakrij sirove meta podatke (default WC prikaz)
add_filter('woocommerce_hidden_order_itemmeta', function($hidden_meta_keys) {
    $hidden_meta_keys[] = 'ovb_all_dates';
    $hidden_meta_keys[] = 'ovb_guest_count';
    return $hidden_meta_keys;
});

// Prikaz formatiranih podataka iznad meta liste
add_action('woocommerce_order_item_meta_start', function($item_id, $item, $order, $plain_text) {
    $dates  = wc_get_order_item_meta($item_id, 'ovb_all_dates');
    $guests = wc_get_order_item_meta($item_id, 'ovb_guest_count');

    if ($dates) {
        $pretty_dates = array_map(function($d) {
            return date_i18n('d.m.Y', strtotime($d));
        }, explode(',', $dates));

        echo '<p class="ovb-order-dates"><strong>Datumi boravka:</strong> ' . esc_html(implode(', ', $pretty_dates)) . '</p>';
    }

    if ($guests) {
        echo '<p class="ovb-order-guests"><strong>Broj gostiju:</strong> ' . esc_html($guests) . '</p>';
    }
}, 10, 4);