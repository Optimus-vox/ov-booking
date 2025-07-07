<?php
defined('ABSPATH') || exit;

add_action('wp_ajax_ov_save_calendar_data', 'ovb_save_calendar_data_callback');

function ovb_save_calendar_data_callback() {
    // Provera nonce-a za sigurnost (nonce šalji sa JS strane)
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'ovb_nonce')) {
        error_log("ovb_save_calendar_data_callback: Invalid nonce");
        wp_send_json_error(['message' => 'Nevažeći zahtev (nonce).']);
        wp_die();
    }

    // Provera prava korisnika
    if (!current_user_can('manage_woocommerce')) {
        error_log("ovb_save_calendar_data_callback: No permission");
        wp_send_json_error(['message' => 'Nemate dozvolu za ovu akciju.']);
        wp_die();
    }

    // Uzimanje i validacija podataka iz POST-a
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $calendar_data_raw = $_POST['calendar_data'] ?? '{}';
    $price_types_raw = $_POST['price_types'] ?? '{}';

    // Provera i dekodiranje calendar_data
    if (is_string($calendar_data_raw)) {
        $calendar_data = json_decode(wp_unslash($calendar_data_raw), true);
    } elseif (is_array($calendar_data_raw)) {
        $calendar_data = $calendar_data_raw;
    } else {
        $calendar_data = [];
    }

    // Provera i dekodiranje price_types
    if (is_string($price_types_raw)) {
        $price_types = json_decode(wp_unslash($price_types_raw), true);
    } elseif (is_array($price_types_raw)) {
        $price_types = $price_types_raw;
    } else {
        $price_types = [];
    }

    if (!$product_id || !is_array($calendar_data)) { 
        error_log("ovb_save_calendar_data_callback: Invalid product ID or calendar data");
        wp_send_json_error(['message' => 'Neispravni podaci.']);
        wp_die();
    }

    // Snimanje u bazu (post meta i opcija)
    $update1 = update_post_meta($product_id, '_ov_calendar_data', $calendar_data);
    $update2 = update_option('ov_price_types', $price_types);

    if ($update1 === false) {
        error_log("ovb_save_calendar_data_callback: Failed to update calendar data for product $product_id");
    }
    if ($update2 === false && get_option('ov_price_types') !== $price_types) {
        error_log("ovb_save_calendar_data_callback: Failed to update price types option");
    }

    wp_send_json_success(['message' => 'Podaci uspešno sačuvani.']);
    wp_die();
}

add_action('wp_ajax_ovb_delete_booking_and_order', function() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'No permission']);
    }
    $booking_id = isset($_POST['booking_id']) ? sanitize_text_field($_POST['booking_id']) : '';
    if (!$booking_id) wp_send_json_error(['message' => 'Missing booking_id']);
    $order_id = intval(strtok($booking_id, '_'));
    if (!$order_id) wp_send_json_error(['message' => 'Missing order_id']);
    $order = wc_get_order($order_id);
    if ($order) {
        error_log(">> Brisem order_id: $order_id, post_type: " . get_post_type($order_id));
        // Prvo klasični WP način (ako je post_type shop_order)
        if ($order->get_type() === 'shop_order' && get_post_type($order_id) === 'shop_order') {
            wp_trash_post($order_id);
        } else {
            // HPOS: promeni status ordera u "trash"
            $order->update_status('trash');
        }
    }
    wp_send_json_success(['message' => 'Order trashed', 'order_id' => $order_id]);
});
