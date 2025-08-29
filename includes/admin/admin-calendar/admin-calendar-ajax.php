<?php
defined('ABSPATH') || exit;

// ┏━ Security & capability checks for all our AJAX handlers ━━━━━━━━━━━━━━━━━━━
function ovb_manage_ajax_security($nonce_action, $nonce_field = 'nonce') {
    check_ajax_referer($nonce_action, $nonce_field);
    if (!current_user_can('edit_products')) {
        wp_send_json_error(__('Security check failed', 'ov-booking'));
    }
}

// ========== GLAVNA FUNKCIJA ZA ČUVANJE KALENDAR PODATAKA ==========
add_action('wp_ajax_ovb_save_calendar_data', 'ovb_save_calendar_data');
function ovb_save_calendar_data() {
    // Debug log početnih podataka
    ovb_log_error('AJAX payload received: ' . print_r($_POST, true), 'admin-calendar');

    // Security check
    if (!current_user_can('edit_products') || !wp_verify_nonce($_POST['nonce'] ?? '', 'ovb_nonce')) {
        ovb_log_error('Security check failed', 'admin-calendar');
        wp_send_json_error(__('Unauthorized access', 'ov-booking'));
        wp_die();
    }

    $product_id = absint($_POST['product_id'] ?? 0);
    
    // Sanitize and decode calendar data
    $calendar_json = stripslashes($_POST['calendar_data'] ?? '');
    $calendar_data = json_decode($calendar_json, true);
    
    // Sanitize and decode price types
    $price_types_json = stripslashes($_POST['price_types'] ?? '[]');
    $price_types = json_decode($price_types_json, true);

    ovb_log_error('Product ID: ' . $product_id, 'admin-calendar');
    ovb_log_error('Calendar data JSON length: ' . strlen($calendar_json), 'admin-calendar');
    ovb_log_error('Price types JSON: ' . $price_types_json, 'admin-calendar');

    // Validation
    if (!$product_id) {
        ovb_log_error('Invalid product ID', 'admin-calendar');
        wp_send_json_error(__('Invalid product ID', 'ov-booking'));
        wp_die();
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        ovb_log_error('JSON decode error: ' . json_last_error_msg(), 'admin-calendar');
        wp_send_json_error(__('Invalid JSON data', 'ov-booking'));
        wp_die();
    }

    if (!is_array($calendar_data)) {
        ovb_log_error('Calendar data is not an array after decode', 'admin-calendar');
        $calendar_data = [];
    }

    if (!is_array($price_types)) {
        ovb_log_error('Price types is not an array after decode', 'admin-calendar');
        $price_types = [];
    }

    // Ensure default price types exist
    $default_price_types = [
        'regular' => 0,
        'weekend' => 0,
        'discount' => 0,
        'custom' => 0
    ];
    $price_types = array_merge($default_price_types, $price_types);

    // Sanitize calendar data
    $sanitized_calendar = [];
    foreach ($calendar_data as $date => $data) {
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            ovb_log_error('Invalid date format: ' . $date, 'admin-calendar');
            continue;
        }

        $sanitized_data = [
            'status' => sanitize_text_field($data['status'] ?? 'available'),
            'isPast' => !empty($data['isPast']),
            'clients' => []
        ];

        // Sanitize price if exists
        if (isset($data['price']) && is_numeric($data['price'])) {
            $sanitized_data['price'] = floatval($data['price']);
        }

        // Sanitize price type if exists
        if (isset($data['priceType']) && is_string($data['priceType'])) {
            $sanitized_data['priceType'] = sanitize_text_field($data['priceType']);
        }

        // Sanitize clients array
        if (isset($data['clients']) && is_array($data['clients'])) {
            foreach ($data['clients'] as $client) {
                if (is_array($client)) {
                    $sanitized_client = [
                        'bookingId' => sanitize_text_field($client['bookingId'] ?? ''),
                        'firstName' => sanitize_text_field($client['firstName'] ?? ''),
                        'lastName' => sanitize_text_field($client['lastName'] ?? ''),
                        'email' => sanitize_email($client['email'] ?? ''),
                        'phone' => sanitize_text_field($client['phone'] ?? ''),
                        'guests' => absint($client['guests'] ?? 1),
                        'rangeStart' => sanitize_text_field($client['rangeStart'] ?? ''),
                        'rangeEnd' => sanitize_text_field($client['rangeEnd'] ?? ''),
                        'isCheckin' => !empty($client['isCheckin']),
                        'isCheckout' => !empty($client['isCheckout']),
                    ];
                    
                    if (isset($client['order_id'])) {
                        $sanitized_client['order_id'] = absint($client['order_id']);
                    }
                    
                    $sanitized_data['clients'][] = $sanitized_client;
                }
            }
        }

        $sanitized_calendar[$date] = $sanitized_data;
    }

    ovb_log_error('Sanitized calendar data count: ' . count($sanitized_calendar), 'admin-calendar');
    ovb_log_error('Sample sanitized data: ' . print_r(array_slice($sanitized_calendar, 0, 2, true), true), 'admin-calendar');

    try {
        // Save calendar data
        $calendar_result = update_post_meta($product_id, '_ovb_calendar_data', $sanitized_calendar);
        ovb_log_error('Calendar data save result: ' . ($calendar_result ? 'success' : 'failed'), 'admin-calendar');

        // Save price types
        $price_result = update_post_meta($product_id, '_ovb_price_types', $price_types);
        ovb_log_error('Price types save result: ' . ($price_result ? 'success' : 'failed'), 'admin-calendar');

        // Verify data was saved
        $saved_calendar = get_post_meta($product_id, '_ovb_calendar_data', true);
        $saved_prices = get_post_meta($product_id, '_ovb_price_types', true);
        
        ovb_log_error('Verification - saved calendar count: ' . (is_array($saved_calendar) ? count($saved_calendar) : 'not array'), 'admin-calendar');
        ovb_log_error('Verification - saved prices: ' . print_r($saved_prices, true), 'admin-calendar');

        wp_send_json_success([
            'message' => __('Data saved successfully', 'ov-booking'),
            'calendar_count' => count($sanitized_calendar),
            'price_types' => $price_types
        ]);

    } catch (Exception $e) {
        ovb_log_error('Exception during save: ' . $e->getMessage(), 'admin-calendar');
        wp_send_json_error(__('Failed to save data: ', 'ov-booking') . $e->getMessage());
    }

    wp_die();
}

// ========== BRISANJE BOOKING I ORDER ==========
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
        if ($order->get_type() === 'shop_order' && get_post_type($order_id) === 'shop_order') {
            // wp_trash_post($order_id);
            $order->trash();
        } else {
            $order->update_status('trash');
        }
    }

    // BRISI SAMO BOOKING iz kalendara, NE cene i statuse!
    if ($order) {
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (!$product_id) continue;

            $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
            $calendar = is_array($calendar_data) ? $calendar_data : ($calendar_data ? json_decode($calendar_data, true) : []);
            $changed = false;

            foreach ($calendar as $date => &$day) {
                if (!empty($day['clients'])) {
                    $before = count($day['clients']);
                    $day['clients'] = array_filter($day['clients'], function($cl) use($booking_id) {
                        return $cl['bookingId'] !== $booking_id;
                    });
                    
                    if (empty($day['clients'])) {
                        $day['clients'] = [];
                        $day['status'] = (!empty($day['price']) && $day['price'] > 0) ? 'available' : 'unavailable';
                        $changed = true;
                    } elseif (count($day['clients']) < $before) {
                        $changed = true;
                    }
                }
            }
            unset($day);

            if ($changed) {
                update_post_meta($product_id, '_ovb_calendar_data', $calendar);
            }
        }
    }

    wp_send_json_success(['message' => 'Order trashed & booking removed', 'order_id' => $order_id]);
});

// ========== CHECK-IN/CHECK-OUT VREMENA ==========
add_action('wp_ajax_ovb_save_checkin_checkout', function() {
    if (!current_user_can('edit_products')) {
        wp_send_json_error('Unauthorized');
    }

    $product_id = intval($_POST['product_id'] ?? 0);
    $checkin_time = sanitize_text_field($_POST['checkin_time'] ?? '');
    $checkout_time = sanitize_text_field($_POST['checkout_time'] ?? '');

    if (!$product_id || !$checkin_time || !$checkout_time) {
        wp_send_json_error('Missing required fields');
    }

    // Učitaj postojeći additional info, update vremena, snimi nazad
    $additional_info = get_post_meta($product_id, '_apartment_additional_info', true);
    if (!is_array($additional_info)) $additional_info = [];

    $additional_info['checkin_time'] = $checkin_time;
    $additional_info['checkout_time'] = $checkout_time;
    update_post_meta($product_id, '_apartment_additional_info', $additional_info);

    wp_send_json_success('Check-in i check-out vreme sačuvano.');
});

// ========== DEBUG META PODATAKA ==========
add_action('wp_ajax_ovb_debug_meta', 'ovb_debug_meta_data');
function ovb_debug_meta_data() {
    if (!current_user_can('edit_products')) {
        wp_send_json_error('Unauthorized');
    }
    
    $product_id = absint($_POST['product_id'] ?? 0);
    if (!$product_id) {
        wp_send_json_error('Missing product ID');
    }
    
    // Pročitaj trenutne meta podatke
    $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
    $price_types = get_post_meta($product_id, '_ovb_price_types', true);
    
    // Proveri i sve meta keys za ovaj product
    $all_meta = get_post_meta($product_id);
    $relevant_keys = array_filter(array_keys($all_meta), function($key) {
        return strpos($key, '_ovb_') === 0;
    });
    
    wp_send_json_success([
        'product_id' => $product_id,
        'calendar_data_type' => gettype($calendar_data),
        'calendar_data_count' => is_array($calendar_data) ? count($calendar_data) : 'not array',
        'calendar_data_sample' => is_array($calendar_data) ? array_slice($calendar_data, 0, 2, true) : $calendar_data,
        'price_types' => $price_types,
        'relevant_meta_keys' => $relevant_keys,
        'all_ovb_meta' => array_intersect_key($all_meta, array_flip($relevant_keys))
    ]);
}

// ========== GLAVNA FUNKCIJA ZA KREIRANJE MANUAL REZERVACIJE ==========
add_action('wp_ajax_ovb_admin_create_manual_order', 'ovb_admin_create_manual_order');
function ovb_admin_create_manual_order() {
    // 1) SIGURNOSNE PROVERE - ISPRAVLJEN NONCE!
    if (!current_user_can('edit_products')) {
        wp_send_json_error(['message' => 'Unauthorized access']);
    }
    
    // BITNO: Koristimo isti nonce kao u saveCalendarDataSafely()!
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ovb_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce - security check failed']);
    }

    $product_id = intval($_POST['product_id'] ?? 0);
    if (!$product_id) {
        wp_send_json_error(['message' => 'Missing product_id']);
    }

    // 2) UČITAVANJE I VALIDACIJA CLIENT DATA
    $client_raw = $_POST['client_data'] ?? '';
    if (is_array($client_raw)) {
        $client_raw = wp_json_encode($client_raw);
    }
    $client_data = json_decode(wp_unslash($client_raw), true);
    if (!is_array($client_data)) {
        wp_send_json_error(['message' => 'Invalid client data format']);
    }

    // 3) VALIDACIJA OBAVEZNIH POLJA
    $required_fields = ['firstName', 'lastName', 'email', 'rangeStart', 'rangeEnd'];
    foreach ($required_fields as $field) {
        if (empty($client_data[$field])) {
            wp_send_json_error(['message' => "Missing required field: {$field}"]);
        }
    }

    // 4) VALIDACIJA DATUMA
    $start = $client_data['rangeStart'];
    $end = $client_data['rangeEnd'];
    if (!DateTime::createFromFormat('Y-m-d', $start) || !DateTime::createFromFormat('Y-m-d', $end)) {
        wp_send_json_error(['message' => 'Invalid date format. Expected YYYY-MM-DD']);
    }
    if ($start > $end) {
        wp_send_json_error(['message' => 'Start date cannot be after end date']);
    }

    // 5) GENERISANJE DATUMA I RAČUNANJE CENE
    $dates = [];
    $current_ts = strtotime($start);
    $end_ts = strtotime($end);
    $total_price = 0;

    // Učitaj postojeće cene iz kalendara
    $existing_calendar = get_post_meta($product_id, '_ovb_calendar_data', true);
    if (is_string($existing_calendar)) {
        $existing_calendar = json_decode($existing_calendar, true);
    }
    if (!is_array($existing_calendar)) {
        $existing_calendar = [];
    }

    // Generiši sve datume i saberi cene
    do {
        $date = date('Y-m-d', $current_ts);
        $dates[] = $date;
        
        // Uzmi cenu za ovaj dan (ako postoji)
        $day_price = isset($existing_calendar[$date]['price']) 
            ? floatval($existing_calendar[$date]['price']) 
            : 0;
        $total_price += $day_price;
        
        $current_ts = strtotime('+1 day', $current_ts);
    } while ($current_ts <= $end_ts);

    // 6) GENERIŠI BOOKING ID
    $booking_id = $client_data['bookingId'] ?? (time() . '_' . wp_rand(1000, 9999));

    try {
        // 7) KREIRAJ WOOCOMMERCE ORDER
        $order = wc_create_order();
        if (is_wp_error($order)) {
            throw new Exception('Failed to create WooCommerce order');
        }

        // Dodaj product u order
        $product = wc_get_product($product_id);
        if (!$product) {
            throw new Exception('Product not found');
        }

        $item_id = $order->add_product($product, 1, [
            'subtotal' => $total_price,
            'total' => $total_price,
        ]);
        
        if (!$item_id) {
            throw new Exception('Failed to add product to order');
        }

        // 8) DODAJ META PODATKE NA ORDER ITEM
        $item = $order->get_item($item_id);
        if ($item) {
            $item->add_meta_data('booking_dates', implode(',', $dates));
            $item->add_meta_data('first_name', $client_data['firstName']);
            $item->add_meta_data('last_name', $client_data['lastName']);
            $item->add_meta_data('email', $client_data['email']);
            $item->add_meta_data('phone', $client_data['phone'] ?? '');
            $item->add_meta_data('guests', intval($client_data['guests'] ?? 1));
            $item->add_meta_data('rangeStart', $start);
            $item->add_meta_data('rangeEnd', $end);
            $item->add_meta_data('booking_id', $booking_id);
            $item->save();
        }

        // 9) DODAJ META PODATKE NA ORDER
        $meta_fields = [
            'first_name' => $client_data['firstName'],
            'last_name' => $client_data['lastName'],
            'email' => $client_data['email'],
            'phone' => $client_data['phone'] ?? '',
            'guests' => intval($client_data['guests'] ?? 1),
            'start_date' => $start,
            'end_date' => $end,
            'booking_id' => $booking_id,
            '_ovb_booking_id' => $booking_id,
            'all_dates' => implode(',', $dates),
        ];

        foreach ($meta_fields as $key => $value) {
            $order->update_meta_data($key, $value);
        }

        // 10) POSTAVI BILLING PODATKE
        $order->set_billing_first_name($client_data['firstName']);
        $order->set_billing_last_name($client_data['lastName']);
        $order->set_billing_email($client_data['email']);
        $order->set_billing_phone($client_data['phone'] ?? '');
        // Save Check-in and Check-out dates into the WC_Order object
        // $order->update_meta_data( 'ovb_check_in_date', sanitize_text_field( $client_data['rangeStart'] ) );
        // $order->update_meta_data( 'ovb_check_out_date', sanitize_text_field( $client_data['rangeEnd'] ) );
        $order->update_meta_data('ovb_check_in_date', $start);
        $order->update_meta_data('ovb_check_out_date', $end);
        $order->update_meta_data('_ovb_guests_num', $client_data['guests']);


        // 11) ZAVRŠI ORDER
        $order->set_total($total_price);
        $order->save();
        $order->update_status('completed', 'Manual booking created via admin calendar');

        // 12) AŽURIRAJ KALENDAR - SAMO DODAJ KLIJENTE, OČUVAJ SVE POSTOJEĆE PODATKE
        foreach ($dates as $i => $date) {
            // Inicijalizuj dan ako ne postoji
            if (!isset($existing_calendar[$date]) || !is_array($existing_calendar[$date])) {
                $existing_calendar[$date] = [
                    'status' => 'available',
                    'isPast' => (strtotime($date) < strtotime(date('Y-m-d'))),
                    'price' => 0,
                    'priceType' => '',
                    'clients' => [],
                ];
            }
            
            // Osiguraj da clients postoji kao array
            if (!isset($existing_calendar[$date]['clients']) || !is_array($existing_calendar[$date]['clients'])) {
                $existing_calendar[$date]['clients'] = [];
            }

            // Ukloni duplikate (ako postoje)
            $existing_calendar[$date]['clients'] = array_values(array_filter(
                $existing_calendar[$date]['clients'],
                function($client) use ($booking_id) {
                    return !isset($client['bookingId']) || $client['bookingId'] !== $booking_id;
                }
            ));

            // Dodaj novog klijenta
            $existing_calendar[$date]['clients'][] = [
                'bookingId' => $booking_id,
                'firstName' => $client_data['firstName'],
                'lastName' => $client_data['lastName'],
                'email' => $client_data['email'],
                'phone' => $client_data['phone'] ?? '',
                'guests' => intval($client_data['guests'] ?? 1),
                'rangeStart' => $start,
                'rangeEnd' => $end,
                'isCheckin' => ($i === 0), // Prvi dan
                'isCheckout' => ($i === count($dates) - 1), // Poslednji dan
                'order_id' => $order->get_id(),
            ];

            // Postavi status - zadnji dan ostaje available, ostali postaju booked
            $existing_calendar[$date]['status'] = ($i === count($dates) - 1) ? 'available' : 'booked';
        }

        // Sačuvaj ažurirani kalendar
        update_post_meta($product_id, '_ovb_calendar_data', $existing_calendar);

        // Log za debug
        ovb_log_error('Manual order created successfully: Order ID ' . $order->get_id() . ', Booking ID: ' . $booking_id, 'admin-calendar');

        // 13) VRATI USPEŠAN ODGOVOR SA AŽURIRANIM PODACIMA
        wp_send_json_success([
            'message' => 'Reservation created successfully!',
            'order_id' => $order->get_id(),
            'booking_id' => $booking_id,
            'total' => wc_price($total_price),
            'calendarData' => $existing_calendar, // ← KLJUČNO: vraćamo ažurirane podatke!
            'dates' => $dates,
            'client' => [
                'firstName' => $client_data['firstName'],
                'lastName' => $client_data['lastName'],
                'email' => $client_data['email'],
                'guests' => intval($client_data['guests'] ?? 1),
            ]
        ]);

    } catch (Exception $e) {
        ovb_log_error('Manual order creation failed: ' . $e->getMessage(), 'admin-calendar');
        wp_send_json_error([
            'message' => 'Failed to create booking: ' . $e->getMessage()
        ]);
    }
}