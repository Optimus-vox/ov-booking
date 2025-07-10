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
    ov_log_error("Order meta saved for order {$order_id}: " . print_r($order->get_meta(), true));
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
        ov_log_error("OVB: Neuspešno snimanje ICS fajla za order {$order_id}");
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

        // $booking_id = $order_id . '_' . $item->get_id();
        $item_id = $item->get_id();
        
        if (!$prod_id) continue;

        $booking_id = $order_id . '_' . $item_id;

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

        
        $last_date = end($dates);

        // foreach ($dates as $date) {
        //     if (!isset($events[$date])) $events[$date] = [];

        //     $already_exists = false;
        //     foreach ($events[$date] as $event) {
        //         if (isset($event['order_id']) && $event['order_id'] == $order_id) {
        //             $already_exists = true;
        //             break;
        //         }
        //     }
        //     if (!$already_exists) {
        //         $events[$date][] = [
        //             'order_id'   => $order_id,
        //             'guest_name' => $guest_name,
        //             'link'       => $order->get_view_order_url(),
        //             'client'     => $client_data,
        //         ];
        //     }

        //     if (!isset($calendar_data[$date]) || !is_array($calendar_data[$date])) {
        //         $calendar_data[$date] = [];
        //     }

        //     $existing_clients = $calendar_data[$date]['clients'] ?? [];
        //     if (!is_array($existing_clients)) $existing_clients = [];

        //     $exists = false;
        //     foreach ($existing_clients as $client) {
        //         if (isset($client['bookingId']) && $client['bookingId'] === $booking_id) {
        //             $exists = true;
        //             break;
        //         }
        //     }
        //     if (!$exists) {
        //         $existing_clients[] = array_merge($client_data, ['bookingId' => $booking_id]);
        //     }

        //     $current_data = $calendar_data[$date];
        //     if (!is_array($current_data)) {
        //         $current_data = [];
        //     }

        //     if ($date === $last_date) {
        //         $calendar_data[$date] = array_merge($current_data, [
        //             'status' => $current_data['status'] ?? 'available',
        //             'price' => $current_data['price'] ?? null,
        //             'priceType' => $current_data['priceType'] ?? null,
        //             'clients' => $existing_clients,
        //             'isLeaving' => true,
        //         ]);
        //     } else {
        //         $calendar_data[$date] = array_merge($current_data, [
        //             'status' => 'booked',
        //             'price' => $current_data['price'] ?? null,
        //             'priceType' => $current_data['priceType'] ?? null,
        //             'clients' => $existing_clients,
        //         ]);
        //     }
        // }

        foreach ($dates as $i => $date) {
            if (!isset($events[$date])) $events[$date] = [];
        
            // Da li je prvi dan ili poslednji
            $is_checkin  = ($i === 0);
            $is_checkout = ($i === count($dates) - 1);
        
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
        
            // Remove DUPLICATE for bookingId
            $existing_clients = array_filter($existing_clients, function($cl) use ($booking_id) {
                return !isset($cl['bookingId']) || $cl['bookingId'] !== $booking_id;
            });
        
            $existing_clients[] = array_merge($client_data, [
                'bookingId'   => $booking_id,
                'isCheckin'   => $is_checkin,
                'isCheckout'  => $is_checkout,
            ]);
        
            $current_data = $calendar_data[$date];
            if (!is_array($current_data)) {
                $current_data = [];
            }
        
            $calendar_data[$date] = array_merge($current_data, [
                'status' => $is_checkout ? ($current_data['status'] ?? 'available') : 'booked',
                'price' => $current_data['price'] ?? null,
                'priceType' => $current_data['priceType'] ?? null,
                'clients' => array_values($existing_clients),
            ]);
        }

        update_post_meta($prod_id, '_ov_calendar_events', $events);
        update_post_meta($prod_id, '_ov_calendar_data', $calendar_data);
    }

    $running = false;
}

// Update calendar on order complete
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

// Release calendar dates on order cancel
add_action('woocommerce_order_status_cancelled', 'ovb_release_calendar_dates_on_cancel', 20);
function ovb_release_calendar_dates_on_cancel($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        if (!$product_id) continue;

        $item_id = $item->get_id();
        $booking_id = $order_id . '_' . $item_id;

        // Učitaj postojeći calendar data
        $calendar_data = get_post_meta($product_id, '_ov_calendar_data', true);
        if (!is_array($calendar_data)) $calendar_data = [];

        // Izbaci klijenta sa tim booking_id iz svih dana
        foreach ($calendar_data as $date => &$data) {
            if (!isset($data['clients']) || !is_array($data['clients'])) continue;

            $before = count($data['clients']);
            $data['clients'] = array_values(array_filter($data['clients'], function($cl) use ($booking_id) {
                return $cl['bookingId'] !== $booking_id;
            }));

            if (count($data['clients']) !== $before) {
                // Ako više nema klijenata, status resetuj na "available"
                if (empty($data['clients'])) {
                    $data['clients'] = [];
                    $data['status'] = 'available';
                }
            }
        }
        unset($data);

        update_post_meta($product_id, '_ov_calendar_data', $calendar_data);

        // Isto uradi i za events, ako koristiš
        $events = get_post_meta($product_id, '_ov_calendar_events', true);
        if (!is_array($events)) $events = [];
        foreach ($events as $date => &$event_list) {
            $event_list = array_values(array_filter($event_list, function($event) use ($booking_id) {
                return !(isset($event['bookingId']) && $event['bookingId'] === $booking_id);
            }));
            if (empty($event_list)) unset($events[$date]);
        }
        unset($event_list);
        update_post_meta($product_id, '_ov_calendar_events', $events);
    }
}


// Adding check in and check out columns in Orders
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'add_wc_order_list_custom_column' );
function add_wc_order_list_custom_column( $columns ) {
    $reordered_columns = array();

    // Inserting columns to a specific location
    foreach( $columns as $key => $column){
        $reordered_columns[$key] = $column;

        if( $key ===  'order_status' ){
            // Inserting after "Status" column
            $reordered_columns['my-column1'] = __( 'Check In','theme_domain');
            $reordered_columns['my-column2'] = __( 'Check Out','theme_domain');
        }
    }
    return $reordered_columns;
}

add_action('manage_woocommerce_page_wc-orders_custom_column', 'display_wc_order_list_custom_column_content', 10, 2);
function display_wc_order_list_custom_column_content( $column, $order ){
    switch ( $column )
    {
        case 'my-column1' : // Check In
            $start_date = $order->get_meta('start_date');
            if (!empty($start_date)) {
                $timestamp = strtotime($start_date);
                $format = get_option('date_format');
                // echo esc_html(date_i18n($format, $timestamp));
                echo '<div class="ovb-booking-dates check-in" style="display:flex; align-items:center; gap:5px; margin-bottom:10px">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-in"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg> ';
                echo '<p style="margin:0">' . esc_html(date_i18n($format, $timestamp)) . '</p>';
                echo '</div>';
            } else {
                echo '<small><em>(no date)</em></small>';
            }
            break;

        case 'my-column2' : // Check Out
            $end_date = $order->get_meta('end_date');
            if (!empty($end_date)) {
                $timestamp = strtotime($end_date);
                $format = get_option('date_format');
                echo '<div class="ovb-booking-dates check-out" style="display:flex; align-items:center; gap:5px">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> ';
                echo '<p style="margin:0">' . esc_html(date_i18n($format, $timestamp)) . '</p>';
                echo '</div>';
            } else {
                echo '<small><em>(no date)</em></small>';
            }
            break;
    }
}

// Adding ceck in and check out dates in Edit Order
add_action('woocommerce_admin_order_data_after_shipping_address', function($order){
    $start_date = $order->get_meta('start_date'); // npr. "2025-07-14"
    $end_date = $order->get_meta('end_date');

    echo '<div class="ovb-booking-dates-wrapper" style="margin-top:30px; font-weight:bold;">';
    echo '<h3 style="margin-bottom:15px">' . __('Datumi boravka:', 'ov-booking') . '</h3>';

    if ($start_date && $end_date) {
        $start_timestamp = strtotime($start_date);
        $end_timestamp = strtotime($end_date);
        $wp_date_format = get_option('date_format');
        $start_formatted = date_i18n($wp_date_format, $start_timestamp);
        $end_formatted = date_i18n($wp_date_format, $end_timestamp);

        echo '<div class="ovb-booking-dates check-in" style="display:flex; align-items:center; gap:5px; margin-bottom:10px">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-in"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg> ';
        echo '<p style="margin:0">' . esc_html($start_formatted) . '</p>';
        echo '</div>';

        echo '<div class="ovb-booking-dates check-out" style="display:flex; align-items:center; gap:5px">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> ';
        echo '<p style="margin:0">' . esc_html($end_formatted) . '</p>';
        echo '</div>';

    } else {
        echo '<p>' . __('Nisu dostupni', 'ov-booking') . '</p>';
    }

    echo '</div>';
});

function ovb_remove_order_reservations($order) {
    $order_id   = $order instanceof WC_Order ? $order->get_id() : $order;
    if (!$order_id) return;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $item_id = $item->get_id();
        if (!$product_id) continue;

        $item_id = $item->get_id();
        $booking_id = $order_id . '_' . $item_id;

        ov_log_error("Trying to delete booking for bookingId: $booking_id (Order $order_id, Product $product_id)");

        $calendar_data = get_post_meta($product_id, '_ov_calendar_data', true);
        if (!is_array($calendar_data)) $calendar_data = [];

        // Prikazi sve datume gde postoji taj bookingId
        foreach ($calendar_data as $date => $data) {
            if (!isset($data['clients']) || !is_array($data['clients'])) continue;
            foreach ($data['clients'] as $client) {
                if (isset($client['bookingId']) && $client['bookingId'] === $booking_id) {
                    ov_log_error("  - BookingId FOUND for date $date");
                }
            }
        }

        // Brisanje: iz svih datuma izbacujemo tog klijenta
        foreach ($calendar_data as $date => &$data) {
            if (!isset($data['clients']) || !is_array($data['clients'])) continue;
            $pre_count = count($data['clients']);
            $data['clients'] = array_values(array_filter($data['clients'], function($client) use ($booking_id) {
                return $client['bookingId'] !== $booking_id;
            }));
            if (count($data['clients']) !== $pre_count) {
                ov_log_error("  - DELETED client with bookingId $booking_id on date $date");
            }
            if (empty($data['clients'])) {
                $data['clients'] = [];
                $data['status'] = 'available';
                ov_log_error("  - No more clients for $date, status reset to available");
            }
        }
        unset($data);

        update_post_meta($product_id, '_ov_calendar_data', $calendar_data);

        // Isto i za EVENTS
        $events = get_post_meta($product_id, '_ov_calendar_events', true);
        if (!is_array($events)) $events = [];
        foreach ($events as $date => &$event_list) {
            $pre_count = count($event_list);
            $event_list = array_values(array_filter($event_list, function($event) use ($booking_id) {
                return !(isset($event['bookingId']) && $event['bookingId'] === $booking_id);
            }));
            if (count($event_list) !== $pre_count) {
                ov_log_error("  - DELETED event with bookingId $booking_id on date $date");
            }
            if (empty($event_list)) unset($events[$date]);
        }
        unset($event_list);

        update_post_meta($product_id, '_ov_calendar_events', $events);
    }
}

add_action('woocommerce_before_trash_order', function($order) {
    $order_id = is_object($order) && method_exists($order, 'get_id')
        ? $order->get_id()
        : (is_numeric($order) ? $order : 'NO_ID');

    ov_log_error('HPOS TRASH: ' . $order_id, 'general');
    if ($order instanceof WC_Order) {
        ovb_remove_order_reservations($order);
    } elseif (is_numeric($order)) {
        $order_obj = wc_get_order($order);
        if ($order_obj) ovb_remove_order_reservations($order_obj);
    }
}, 10, 1);

add_action('woocommerce_before_delete_order', function($order) {
    $order_id = is_object($order) && method_exists($order, 'get_id')
        ? $order->get_id()
        : (is_numeric($order) ? $order : 'NO_ID');

    ov_log_error('HPOS DELETE: ' . $order_id, 'general');
    if ($order instanceof WC_Order) {
        ovb_remove_order_reservations($order);
    } elseif (is_numeric($order)) {
        $order_obj = wc_get_order($order);
        if ($order_obj) ovb_remove_order_reservations($order_obj);
    }
}, 10, 1);


function ovb_handle_order_deletion($post_id) {
    ov_log_error("🔥 POZVANA ovb_handle_order_deletion za ID: $post_id", 'general');
    ov_log_error("POKRENUTA: ovb_handle_order_deletion za post_id $post_id");
    $post = get_post($post_id);
    if (!$post) return;
    if ($post->post_type !== 'shop_order') return;

    $order = wc_get_order($post_id);
    if (!$order) return;
    ovb_remove_order_reservations($order);
}
