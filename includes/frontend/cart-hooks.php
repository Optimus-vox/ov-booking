<?php
defined('ABSPATH') || exit;


// Snimi booking podatke prilikom dodavanja u korpu
add_filter('woocommerce_add_cart_item_data','ovb_save_all_booking_data',10,3);
function ovb_save_all_booking_data($data,$product_id,$variation_id){
    if(!empty($_POST['all_dates'])){
        $data['start_date']   = sanitize_text_field($_POST['start_date'] ?? '');
        $data['end_date']     = sanitize_text_field($_POST['end_date'] ?? '');
        $data['all_dates']    = sanitize_text_field($_POST['all_dates']);
        $data['guests']       = isset($_POST['guests']) ? intval($_POST['guests']) : 1;
        $data['ov_all_dates'] = $data['all_dates']; // kompatibilnost
        $data['unique_key']   = md5(microtime().rand());

         // izračunaj broj noći
         $dates = explode(',', $data['all_dates']);
         $data['nights'] = max(0, count($dates) - 1);
    }
    return $data;
}
// Vraćanje iz sesije
add_filter('woocommerce_get_cart_item_from_session', function($item, $values) {
    foreach (['start_date', 'end_date', 'all_dates', 'guests', 'ov_all_dates', 'unique_key','nights'] as $key) {
        if (isset($values[$key])) {
            $item[$key] = $values[$key];
        }
    }
    return $item;
}, 20, 2);

// Ukloni duplikate po datumima
add_action('woocommerce_before_cart', function() {
    if (!WC()->cart) return;
    $seen = [];
    foreach (WC()->cart->get_cart() as $key => $item) {
        if (empty($item['ov_all_dates'])) {
            WC()->cart->remove_cart_item($key);
            continue;
        }
        if (in_array($item['ov_all_dates'], $seen, true)) {
            WC()->cart->remove_cart_item($key);
        } else {
            $seen[] = $item['ov_all_dates'];
        }
    }
});

// Promeni cenu na osnovu noći (nights) iz cart-item meta
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && ! defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $item) {
        if (empty($item['ov_all_dates'])) {
            continue;
        }

        // explode svih datuma
        $dates    = explode(',', $item['ov_all_dates']);
        // meta kalendar
        $cal      = get_post_meta($item['product_id'], '_ov_calendar_data', true) ?: [];
        // broj noći koji smo već sačuvali
        $nights   = isset($item['nights'])
                    ? intval($item['nights'])
                    : max(0, count($dates) - 1);
        // uzmi samo prvih $nights datuma (bez checkout dana)
        $billable = array_slice($dates, 0, $nights);

        $total = 0;
        foreach ($billable as $d) {
            if (isset($cal[$d]['price'])) {
                $total += floatval($cal[$d]['price']);
            }
        }

        // postavi WC cenu na sumu samo po noćenjima
        $item['data']->set_price($total);
    }
}, 10, 1);

// Prikaz datuma i cena u korpi
add_filter('woocommerce_get_item_data', function($data, $item) {
    if (empty($item['ov_all_dates'])) return $data;

    $dates = explode(',', sanitize_text_field($item['ov_all_dates']));
    $cal   = get_post_meta($item['product_id'], '_ov_calendar_data', true) ?: [];

    foreach ($dates as $d) {
        $pretty = date_i18n('d.m.Y', strtotime($d));
        $price  = isset($cal[$d]['price']) ? number_format_i18n(floatval($cal[$d]['price']), 2) . ' €' : __('N/A', 'ov-booking');
        $data[] = ['key' => esc_html($pretty), 'value' => esc_html($price)];
    }

    return $data;
}, 10, 2);

// Sakrij "× qty" iz imena proizvoda
add_filter('woocommerce_cart_item_name', function($name, $item) {
    if (!empty($item['ov_all_dates'])) {
        return "<span class='ovb-cart-product-name'>{$name}</span>";
    }
    return $name;
}, 10, 3);

// Validacija pre dodavanja u korpu
add_filter('woocommerce_add_to_cart_validation', function($passed, $product_id, $qty) {
    // if (empty($_POST['all_dates'])) {
    //     wc_add_notice(__('Molim Vas, izaberite datume pre nego što dodate u korpu.', 'ov-booking'), 'error');
    //     return false;
    // }
    if (WC()->cart && WC()->cart->get_cart_contents_count() > 0) {
        WC()->cart->empty_cart(); // automatski ukloni prethodni proizvod
    }
    return $passed;
}, 10, 3);

// add_filter('woocommerce_add_to_cart_validation', function($passed, $product_id, $qty) {
//     if (empty($_POST['all_dates'])) {
//         wc_add_notice(__('Molim Vas, izaberite datume pre nego što dodate u korpu.', 'ov-booking'), 'error');
//         if (function_exists('ov_log_error')) {
//             ov_log_error('🚫 Validacija pala: all_dates nije prosleđen', 'cart');
//         }
//         return false;
//     }

//     if (WC()->cart && WC()->cart->get_cart_contents_count() > 0) {
//         WC()->cart->empty_cart(); // automatski ukloni prethodni proizvod
//     }

//     if (function_exists('ov_log_error')) {
//         ov_log_error('✅ Validacija prošla za proizvod ID: ' . $product_id, 'cart');
//     }

//     return $passed;
// }, 10, 3);


// Redirect na cart nakon add-to-cart (samo za ne-AJAX)
add_filter('woocommerce_add_to_cart_redirect', function($url) {
    return wc_get_cart_url();
});

// AJAX hook za potvrdu dodavanja (opciono: možeš ovde slati dodatne podatke ako treba)
add_action('woocommerce_ajax_added_to_cart', function() {
    if (function_exists('ov_log_error')) {
        ov_log_error('✅ AJAX proizvod dodat u korpu', 'cart');
    }
    return true;
});