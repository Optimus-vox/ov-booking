<?php
defined('ABSPATH') || exit;

//Snimi podatke iz korpe u narudžbinu
add_action('woocommerce_checkout_update_order_meta', function($order_id, $data) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    foreach (WC()->cart->get_cart() as $item) {
        if (!empty($item['ov_all_dates'])) {
            $order->update_meta_data('all_dates', sanitize_text_field($item['ov_all_dates']));
            if (!empty($item['start_date'])) {
                $order->update_meta_data('start_date', sanitize_text_field($item['start_date']));
            }
            if (!empty($item['end_date'])) {
                $order->update_meta_data('end_date', sanitize_text_field($item['end_date']));
            }
            if (isset($item['guests'])) {
                $order->update_meta_data('guests', intval($item['guests']));
            }
            break; // samo prvi item koristiš za ove podatke
        }
    }

    $order->save();
}, 10, 2);

// Dodaj meta podatke na svaku stavku
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['ov_all_dates'])) {
        $item->add_meta_data('ov_all_dates', $values['ov_all_dates'], true);
    }
    if (!empty($values['guests'])) {
        $item->add_meta_data('ov_guest_count', $values['guests'], true);
    }
}, 10, 4);


// Kada je narudžbina kompletna – pošalji .ics fajl // 

// TODO: Sredi slanje maila po narudžbini. 
// TODO: Sredi iCal za Outlook. 
// TODO: Sredi iCal da prima i salje podatke iz  AirBnb i Booking. 

add_action('woocommerce_order_status_completed', 'ovb_send_ical_attachment_to_customer');
function ovb_send_ical_attachment_to_customer($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    // Samo ako narudžbina ima ov_all_dates
    $found = false;
    foreach ( $order->get_items() as $item ) {
        if ( $item->get_meta('ov_all_dates') ) {
            $found = true;
            break;
        }
    }
    if ( ! $found ) return;

    // Generiši sadržaj .ics fajla
    $ics_content = ovb_generate_ics_content($order);

    // Snimi privremeno
    $upload_dir = wp_upload_dir();
    $file_path = trailingslashit($upload_dir['basedir']) . "booking-{$order_id}.ics";
    file_put_contents($file_path, $ics_content);

    // Pošalji mail sa prilogom
    $to      = $order->get_billing_email();
    $subject = '📅 Booking Calendar File';
    $message = 'Thank you for your reservation. Attached is your calendar file (.ics) you can import.';
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $attachments = [$file_path];

    wp_mail($to, $subject, $message, $headers, $attachments);

    // Opciono: obriši fajl posle slanja
    register_shutdown_function(function() use ($file_path) {
        if ( file_exists($file_path) ) {
            unlink($file_path);
        }
    });
}
 
