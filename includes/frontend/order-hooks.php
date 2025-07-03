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
                $order->update_meta_data('start_date', sanitize_text_field($item['start_date'] ?? ''));
            }
            if (!empty($item['end_date'])) {
                $order->update_meta_data('end_date', sanitize_text_field($item['end_date'] ?? ''));
            }
            if (isset($item['guests'])) {
                $order->update_meta_data('guests', intval($item['guests'] ?? 1));
            }
            break; // koristi samo prvi item sa podacima
        }
    }

    // Sačuvaj korisničke podatke u order meta
    $billing_fields = ['first_name', 'last_name', 'email', 'phone'];
    foreach ($billing_fields as $field) {
        $value = isset($_POST['billing_' . $field]) ? sanitize_text_field($_POST['billing_' . $field]) : '';
        if ($value) {
            $order->update_meta_data('booking_client_' . $field, $value);
        }
    }

    $order->save();
    error_log("Order meta saved for order {$order_id}: " . print_r($order->get_meta(), true));
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

// Slanje ICS fajla kada narudžbina pređe u completed status
add_action('woocommerce_order_status_completed', 'ovb_send_ical_attachment_to_customer');
function ovb_send_ical_attachment_to_customer($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $has_booking = false;
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('ov_all_dates')) {
            $has_booking = true;
            break;
        }
    }
    if (!$has_booking) return;

    $ics_content = OVB_iCal_Service::generate_ics_string($order);

    $upload_dir = wp_upload_dir();
    $file_path = trailingslashit($upload_dir['basedir']) . "booking-{$order_id}.ics";

    if (false === file_put_contents($file_path, $ics_content)) {
        error_log("OVB: Neuspešno snimanje ICS fajla za order {$order_id}");
        return;
    }

    wp_mail(
        $order->get_billing_email(),
        '📅 Booking Calendar File',
        'Thank you for your reservation. Attached is your calendar file (.ics) you can import.',
        ['Content-Type: text/html; charset=UTF-8'],
        [$file_path]
    );

    register_shutdown_function(function() use ($file_path) {
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    });
}

// Upis rezervacija u admin kalendar sa zaštitom od rekurzije
add_action('woocommerce_order_status_completed', 'ovb_admin_calendar_add_reservation', 20);
function ovb_admin_calendar_add_reservation($order_id) {
    static $running = false;
    if ($running) {
        return;
    }
    $running = true;

    $order = wc_get_order($order_id);
    if (!$order) {
        $running = false;
        return;
    }

    $guest_first = $order->get_meta('booking_client_first_name');
    $guest_last = $order->get_meta('booking_client_last_name');
    $guest_name = trim($guest_first . ' ' . $guest_last);

    foreach ($order->get_items() as $item) {
        $prod_id = $item->get_product_id();
        if (!$prod_id) continue;

        $dates_meta = $item->get_meta('ov_all_dates');
        if (empty($dates_meta) || !is_string($dates_meta)) continue;

        $dates = array_filter(array_map('trim', explode(',', $dates_meta)));
        if (empty($dates)) continue;

        $events = get_post_meta($prod_id, '_ov_calendar_events', true);
        if (!is_array($events)) $events = [];

        // VAŽNO: učitamo calendar_data *jednom* za ceo proizvod
        $calendar_data = get_post_meta($prod_id, '_ov_calendar_data', true);
        if (!is_array($calendar_data)) $calendar_data = [];

        $client_data = [
            'firstName' => $guest_first,
            'lastName'  => $guest_last,
            'email'     => $order->get_meta('booking_client_email'),
            'phone'     => $order->get_meta('booking_client_phone'),
            'guests'    => $order->get_meta('guests'),
            'rangeStart' => $dates[0] ?? '',
            'rangeEnd'   => end($dates) ?: '',
        ];

        $bookingId = $order_id . '_' . $item->get_id();
        $last_date = end($dates);

        foreach ($dates as $date) {
            if (!isset($events[$date])) $events[$date] = [];

            $already_exists = false;
            foreach ($events[$date] as $event) {
                if (isset($event['order_id']) && $event['order_id'] == $order_id) {
                    $already_exists = true;
                    break;
                }
            }
            if (!$already_exists) {
                $events[$date][] = [
                    'order_id'   => $order_id,
                    'guest_name' => $guest_name,
                    'link'       => $order->get_view_order_url(),
                    'client'     => $client_data,
                ];
            }

            if (!isset($calendar_data[$date]) || !is_array($calendar_data[$date])) {
                $calendar_data[$date] = [];
            }

            $existing_clients = $calendar_data[$date]['clients'] ?? [];
            if (!is_array($existing_clients)) $existing_clients = [];

            $exists = false;
            foreach ($existing_clients as $client) {
                if (isset($client['bookingId']) && $client['bookingId'] === $bookingId) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $existing_clients[] = array_merge($client_data, ['bookingId' => $bookingId]);
            }

            $current_data = $calendar_data[$date];
            if (!is_array($current_data)) {
                $current_data = [];
            }

            if ($date === $last_date) {
                $calendar_data[$date] = array_merge($current_data, [
                    'status' => $current_data['status'] ?? 'available',
                    'price' => $current_data['price'] ?? null,
                    'priceType' => $current_data['priceType'] ?? null,
                    'clients' => $existing_clients,
                    'isLeaving' => true,
                ]);
            } else {
                $calendar_data[$date] = array_merge($current_data, [
                    'status' => 'booked',
                    'price' => $current_data['price'] ?? null,
                    'priceType' => $current_data['priceType'] ?? null,
                    'clients' => $existing_clients,
                ]);
            }
        }

        update_post_meta($prod_id, '_ov_calendar_events', $events);
        update_post_meta($prod_id, '_ov_calendar_data', $calendar_data);
    }

    $running = false;
}


add_action('woocommerce_order_status_completed', 'ovb_update_calendar_on_order_complete', 10, 1);
function ovb_update_calendar_on_order_complete($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $start_date = $order->get_meta('start_date');
    $end_date = $order->get_meta('end_date');

    if (!$start_date || !$end_date) return;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();

        $calendar_data = get_post_meta($product_id, '_ov_calendar_data', true);
        if (!is_array($calendar_data)) {
            $calendar_data = [];
        }

        $period_start = new DateTime($start_date);
        $period_end = new DateTime($end_date);

        $last_date_key = $period_end->format('Y-m-d');

        while ($period_start <= $period_end) {
            $date_key = $period_start->format('Y-m-d');

            $current_status = $calendar_data[$date_key]['status'] ?? 'available';

            if ($date_key === $last_date_key) {
                $calendar_data[$date_key] = [
                    'status' => $current_status,
                    'price' => $calendar_data[$date_key]['price'] ?? null,
                    'priceType' => $calendar_data[$date_key]['priceType'] ?? null,
                    'clients' => $calendar_data[$date_key]['clients'] ?? [],
                    'isLeaving' => true,
                ];
            } else {
                $calendar_data[$date_key] = [
                    'status' => 'booked',
                    'price' => $calendar_data[$date_key]['price'] ?? null,
                    'priceType' => $calendar_data[$date_key]['priceType'] ?? null,
                    'clients' => $calendar_data[$date_key]['clients'] ?? [],
                ];
            }

            $period_start->modify('+1 day');
        }

        update_post_meta($product_id, '_ov_calendar_data', $calendar_data);
    }
}
