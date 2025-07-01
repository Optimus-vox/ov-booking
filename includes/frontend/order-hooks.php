<?php
defined('ABSPATH') || exit;

require_once OV_BOOKING_PATH . 'includes/class-ical-service.php';


// Snimanje podataka iz korpe u narudžbinu (order meta)
add_action('woocommerce_checkout_update_order_meta', function($order_id, $data) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    // Uzmi podatke iz prve stavke korpe koja ima rezervacione datume
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
            break; // koristi samo prvi item sa podacima
        }
    }
    $order->save();
}, 10, 2);

// Dodavanje rezervacionih meta podataka na svaku stavku u narudžbini
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['ov_all_dates'])) {
        $item->add_meta_data('ov_all_dates', sanitize_text_field($values['ov_all_dates']), true);
    }
    if (!empty($values['guests'])) {
        $item->add_meta_data('ov_guest_count', intval($values['guests']), true);
    }
}, 10, 4);

// Slanje .ics fajla kada narudžbina pređe u completed status
add_action('woocommerce_order_status_completed', 'ovb_send_ical_attachment_to_customer');
function ovb_send_ical_attachment_to_customer($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    // Provera da li postoji barem jedna stavka sa rezervacionim datumima
    $has_booking = false;
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('ov_all_dates')) {
            $has_booking = true;
            break;
        }
    }
    if (!$has_booking) return;

    // Generisanje ICS sadržaja
    $ics_content = OVB_iCal_Service::generate_ics_string($order);

    // Snimanje privremenog ICS fajla
    $upload_dir = wp_upload_dir();
    $file_path = trailingslashit($upload_dir['basedir']) . "booking-{$order_id}.ics";
    if (false === file_put_contents($file_path, $ics_content)) {
        error_log("OVB: Neuspešno snimanje ICS fajla za order {$order_id}");
        return;
    }

    // Slanje mejla sa ICS prilogom
    $to = $order->get_billing_email();
    $subject = '📅 Booking Calendar File';
    $message = 'Thank you for your reservation. Attached is your calendar file (.ics) you can import.';
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $attachments = [$file_path];

    wp_mail($to, $subject, $message, $headers, $attachments);

    // Brisanje privremenog fajla nakon slanja
    register_shutdown_function(function() use ($file_path) {
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    });
}

// AUTOMATSKO UPISIVANJE REZERVACIJE U ADMIN KALENDAR (po statusu completed)
add_action('woocommerce_order_status_completed', 'ovb_admin_calendar_add_reservation', 20);
function ovb_admin_calendar_add_reservation($order_id) {

    $order = wc_get_order($order_id);
    if (!$order) return;

    // Prođi kroz sve stavke narudžbine (može biti više proizvoda)
    foreach ($order->get_items() as $item) {
        $prod_id = $item->get_product_id();
        if (!$prod_id) continue;

        $guest_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $dates_meta = $item->get_meta('ov_all_dates');
        if (empty($dates_meta)) continue;

        // Pretvori string datuma u niz, očekuje format "YYYY-MM-DD,YYYY-MM-DD,..."
        $dates = array_filter(array_map('trim', explode(',', $dates_meta)));
        if (empty($dates)) continue;

        // Učitaj postojeće evente
        $events = get_post_meta($prod_id, '_ovb_calendar_events', true);
        if (!is_array($events)) {
            $events = [];
        }

        // Dodaj rezervaciju po datumu u kalendar
        foreach ($dates as $date) {
            if (!isset($events[$date])) {
                $events[$date] = [];
            }
            // Da ne dupliraš iste rezervacije (možeš po order_id i guest_name proveriti)
            $already_exists = false;
            foreach ($events[$date] as $event) {
                if ($event['order_id'] == $order_id) {
                    $already_exists = true;
                    break;
                }
            }
            if (!$already_exists) {
                $events[$date][] = [
                    'order_id'   => $order_id,
                    'guest_name' => $guest_name,
                    'link'       => $order->get_view_order_url(),
                ];
            }
        }

        // Snimi nazad u meta polje
        update_post_meta($prod_id, '_ovb_calendar_events', $events);
    } 
}
