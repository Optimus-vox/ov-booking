<?php
/**
 * Admin AJAX handlers for OV Booking (hardened, drop-in)
 */
defined('ABSPATH') || exit;

/**
 * ---------------------------------------------------------------------
 * Utilities (logging helper ovb_log_error() postoji u tvom pluginu)
 * ---------------------------------------------------------------------
 */

/**
 * Safe JSON decode sa WP standardnim unslash-om.
 *
 * @param string $json
 * @param mixed  $default
 * @return array|mixed
 */
function ovb_safe_json_decode($json, $default = []) {
    if ($json === '' || $json === null) {
        return $default;
    }
    $json = wp_unslash($json); // umesto stripslashes
    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        ovb_log_error('JSON decode error: ' . json_last_error_msg(), 'admin-calendar');
        return $default;
    }
    return $decoded;
}

/**
 * Boolean normalizer (0/1/"0"/"1"/true/false).
 */
function ovb_boolval($v) {
    return filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? !empty($v);
}

/**
 * Deep sanitizacija dnevnih podataka iz kalendara.
 */
function ovb_sanitize_calendar_data(array $calendar_data) {
    $sanitized = [];

    foreach ($calendar_data as $date => $data) {
        // YYYY-MM-DD
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            ovb_log_error('Invalid date key: ' . print_r($date, true), 'admin-calendar');
            continue;
        }

        $day = [
            'status'   => sanitize_text_field($data['status'] ?? 'available'),
            'isPast'   => ovb_boolval($data['isPast'] ?? false),
            'clients'  => [],
        ];

        if (isset($data['price']) && $data['price'] !== '') {
            $price = is_numeric($data['price']) ? (float) $data['price'] : null;
            if ($price !== null) {
                $day['price'] = $price;
            }
        }

        if (isset($data['priceType']) && is_string($data['priceType'])) {
            $day['priceType'] = sanitize_text_field($data['priceType']);
        }

        if (!empty($data['clients']) && is_array($data['clients'])) {
            foreach ($data['clients'] as $client) {
                if (!is_array($client)) { continue; }
                $c = [
                    'bookingId'  => sanitize_text_field($client['bookingId'] ?? ''),
                    'firstName'  => sanitize_text_field($client['firstName'] ?? ''),
                    'lastName'   => sanitize_text_field($client['lastName'] ?? ''),
                    'email'      => sanitize_email($client['email'] ?? ''),
                    'phone'      => sanitize_text_field($client['phone'] ?? ''),
                    'guests'     => absint($client['guests'] ?? 1),
                    'rangeStart' => sanitize_text_field($client['rangeStart'] ?? ''),
                    'rangeEnd'   => sanitize_text_field($client['rangeEnd'] ?? ''),
                    'isCheckin'  => ovb_boolval($client['isCheckin'] ?? false),
                    'isCheckout' => ovb_boolval($client['isCheckout'] ?? false),
                ];
                if (isset($client['order_id'])) {
                    $c['order_id'] = absint($client['order_id']);
                }
                $day['clients'][] = $c;
            }
        }

        $sanitized[$date] = $day;
    }

    return $sanitized;
}

/**
 * Spajanje i sanitizacija price types sa podrazumevanim vrednostima.
 */
function ovb_sanitize_price_types($price_types) {
    $defaults = ['regular'=>0,'weekend'=>0,'discount'=>0,'custom'=>0];
    $price_types = is_array($price_types) ? array_merge($defaults, $price_types) : $defaults;
    foreach ($price_types as $k => $v) {
        $price_types[$k] = is_numeric($v) ? (float) $v : 0;
    }
    return $price_types;
}

/**
 * update_post_meta normalizacija: tretiraj "no change" kao success.
 */
function ovb_update_post_meta_strict($post_id, $meta_key, $new_value) {
    $prev = get_post_meta($post_id, $meta_key, true);
    $updated = update_post_meta($post_id, $meta_key, $new_value);

    if ($updated === false) {
        $identical = (is_array($prev) || is_object($prev))
            ? wp_json_encode($prev) === wp_json_encode($new_value)
            : $prev == $new_value;
        if ($identical) {
            return true;
        }
        if (!metadata_exists('post', $post_id, $meta_key)) {
            return (bool) add_post_meta($post_id, $meta_key, $new_value, true);
        }
        return false;
    }
    return (bool) $updated;
}

/**
 * Pronađi WC narudžbinu po _ovb_booking_id (ako ne znamo order_id).
 */
function ovb_find_order_by_booking_id($booking_id) {
    if (!$booking_id || !function_exists('wc_get_orders')) {
        return false;
    }
    $orders = wc_get_orders([
        'limit'      => 1,
        'status'     => array_keys(wc_get_order_statuses()),
        'meta_key'   => '_ovb_booking_id',
        'meta_value' => $booking_id,
        'return'     => 'objects',
    ]);
    return (!empty($orders) && $orders[0] instanceof WC_Order) ? $orders[0] : false;
}

/**
 * Centralizovan guard (cap + nonce).
 */
function ovb_require_cap_and_nonce($capability, $nonce_action, $nonce_field = 'nonce') {
    if (!current_user_can($capability)) {
        wp_send_json_error(['message' => __('Unauthorized', 'ov-booking')], 403);
    }
    $nonce = isset($_POST[$nonce_field]) ? $_POST[$nonce_field] : '';
    if (!wp_verify_nonce($nonce, $nonce_action)) {
        wp_send_json_error(['message' => __('Invalid nonce', 'ov-booking')], 403);
    }
}

/* ============================================================================
 * 1) SAVE CALENDAR DATA
 * ==========================================================================*/
add_action('wp_ajax_ovb_save_calendar_data', 'ovb_save_calendar_data');
function ovb_save_calendar_data() {
    ovb_log_error('AJAX payload received: ' . print_r($_POST, true), 'admin-calendar');

    // ✅ Security
    ovb_require_cap_and_nonce('edit_products', 'ovb_nonce', 'nonce');

    $product_id = absint($_POST['product_id'] ?? 0);
    if (!$product_id) {
        ovb_log_error('Invalid product ID', 'admin-calendar');
        wp_send_json_error(['message' => __('Invalid product ID', 'ov-booking')], 400);
    }

    // Decode payloads
    $calendar_in    = ovb_safe_json_decode($_POST['calendar_data'] ?? '', []);
    $price_types_in = ovb_safe_json_decode($_POST['price_types']    ?? '', []);

    ovb_log_error('Product ID: ' . $product_id, 'admin-calendar');
    ovb_log_error('Calendar keys received: ' . (is_array($calendar_in) ? count($calendar_in) : 'not array'), 'admin-calendar');

    // Sanitize
    $sanitized_calendar = ovb_sanitize_calendar_data(is_array($calendar_in) ? $calendar_in : []);
    $price_types        = ovb_sanitize_price_types($price_types_in);

    ovb_log_error('Sanitized calendar count: ' . count($sanitized_calendar), 'admin-calendar');
    if (!empty($sanitized_calendar)) {
        $keys = array_slice(array_keys($sanitized_calendar), 0, 2);
        $sample = array_intersect_key($sanitized_calendar, array_flip($keys));
        ovb_log_error('Sample sanitized: ' . print_r($sample, true), 'admin-calendar');
    }

    try {
        // Persist (tretiramo "no change" kao success)
        $cal_ok   = ovb_update_post_meta_strict($product_id, '_ovb_calendar_data', $sanitized_calendar);
        $price_ok = ovb_update_post_meta_strict($product_id, '_ovb_price_types',   $price_types);

        ovb_log_error('Calendar data save result (normalized): ' . ($cal_ok ? 'success' : 'failed'), 'admin-calendar');
        ovb_log_error('Price types save result (normalized): ' . ($price_ok ? 'success' : 'failed'), 'admin-calendar');

        // Verify
        $saved_calendar = get_post_meta($product_id, '_ovb_calendar_data', true);
        $saved_prices   = get_post_meta($product_id, '_ovb_price_types',   true);

        ovb_log_error('Verification - saved calendar count: ' . (is_array($saved_calendar) ? count($saved_calendar) : 'not array'), 'admin-calendar');
        ovb_log_error('Verification - saved prices: ' . print_r($saved_prices, true), 'admin-calendar');

        wp_send_json_success([
            'message'        => __('Data saved successfully', 'ov-booking'),
            'calendar_count' => is_array($saved_calendar) ? count($saved_calendar) : 0,
            'price_types'    => $saved_prices,
        ]);
    } catch (Exception $e) {
        ovb_log_error('Exception during save: ' . $e->getMessage(), 'admin-calendar');
        wp_send_json_error(['message' => __('Failed to save data: ', 'ov-booking') . $e->getMessage()], 500);
    }
}

/* ============================================================================
 * 2) DELETE BOOKING + (optional) TRASH ORDER
 * ==========================================================================*/
add_action('wp_ajax_ovb_delete_booking_and_order', function () {
    // ✅ Centralizovan guard (CSRF + cap) — tražimo jaču rolu jer brišemo porudžbinu
    ovb_require_cap_and_nonce('manage_woocommerce', 'ovb_nonce', 'nonce');

    $booking_id = isset($_POST['booking_id']) ? sanitize_text_field(wp_unslash($_POST['booking_id'])) : '';
    if (!$booking_id) {
        wp_send_json_error(['message' => __('Missing booking_id', 'ov-booking')], 400);
    }

    // opciono stiže order_id; ako ne stigne, naći ćemo ga preko booking_id
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $order = $order_id ? wc_get_order($order_id) : ovb_find_order_by_booking_id($booking_id);

    if ($order instanceof WC_Order) {
        $order->trash();
        $order_id = $order->get_id();
    } else {
        $order_id = 0; // nastavićemo samo čišćenje kalendara
    }

    // Produkti povezani sa narudžbinom (ako je imamo)
    $product_ids = [];
    if ($order_id) {
        foreach ($order->get_items() as $item) {
            $pid = $item->get_product_id();
            if ($pid) { $product_ids[$pid] = true; }
        }
        $product_ids = array_keys($product_ids);
    }

    // Fallback: ako ne znamo produkte, probaj da pretražiš sve koji imaju _ovb_calendar_data
    if (empty($product_ids)) {
        $q = new WP_Query([
            'post_type'      => 'product',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'meta_query'     => [
                ['key' => '_ovb_calendar_data', 'compare' => 'EXISTS'],
            ],
        ]);
        if ($q->have_posts()) { $product_ids = $q->posts; }
        wp_reset_postdata();
    }

    foreach ($product_ids as $pid) {
        $calendar_data = get_post_meta($pid, '_ovb_calendar_data', true);
        $calendar = is_array($calendar_data)
            ? $calendar_data
            : ($calendar_data ? json_decode($calendar_data, true) : []);

        if (!is_array($calendar) || empty($calendar)) { continue; }

        $changed = false;
        foreach ($calendar as $date => &$day) {
            if (empty($day['clients']) || !is_array($day['clients'])) { continue; }

            $before = count($day['clients']);
            $day['clients'] = array_values(array_filter($day['clients'], function ($cl) use ($booking_id) {
                return !isset($cl['bookingId']) || $cl['bookingId'] !== $booking_id;
            }));

            if (count($day['clients']) !== $before) {
                $changed = true;

                if (empty($day['clients'])) {
                    $day['clients'] = [];
                    $day['status']  = (!empty($day['price']) && is_numeric($day['price']) && $day['price'] > 0)
                        ? 'available' : 'unavailable';
                }
            }
        }
        unset($day);

        if ($changed) {
            ovb_update_post_meta_strict($pid, '_ovb_calendar_data', $calendar);
        }
    }

    wp_send_json_success([
        'message'  => __('Order trashed & booking removed', 'ov-booking'),
        'order_id' => $order_id,
    ]);
});

/* ============================================================================
 * 3) SAVE CHECK-IN/CHECK-OUT TIMES
 * ==========================================================================*/
add_action('wp_ajax_ovb_save_checkin_checkout', function () {
    // ✅ Centralizovan guard
    ovb_require_cap_and_nonce('edit_products', 'ovb_nonce', 'nonce');

    $product_id    = absint($_POST['product_id'] ?? 0);
    $checkin_time  = sanitize_text_field($_POST['checkin_time']  ?? '');
    $checkout_time = sanitize_text_field($_POST['checkout_time'] ?? '');

    if (!$product_id || $checkin_time === '' || $checkout_time === '') {
        wp_send_json_error(__('Missing required fields', 'ov-booking'), 400);
    }

    $additional_info = get_post_meta($product_id, '_apartment_additional_info', true);
    if (!is_array($additional_info)) { $additional_info = []; }

    $additional_info['checkin_time']  = $checkin_time;
    $additional_info['checkout_time'] = $checkout_time;

    ovb_update_post_meta_strict($product_id, '_apartment_additional_info', $additional_info);

    wp_send_json_success(__('Check-in i check-out vreme sačuvano.', 'ov-booking'));
});

/* ============================================================================
 * 4) DEBUG META (samo admin/editor proizvoda, CSRF zaštićeno)
 * ==========================================================================*/
add_action('wp_ajax_ovb_debug_meta', 'ovb_debug_meta_data');
function ovb_debug_meta_data() {
    // ✅ Centralizovan guard
    ovb_require_cap_and_nonce('edit_products', 'ovb_nonce', 'nonce');

    $product_id = absint($_POST['product_id'] ?? 0);
    if (!$product_id) {
        wp_send_json_error(__('Missing product ID', 'ov-booking'), 400);
    }

    $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
    $price_types   = get_post_meta($product_id, '_ovb_price_types',   true);

    $all_meta = get_post_meta($product_id);
    $relevant_keys = array_filter(array_keys($all_meta), function ($key) {
        return strpos($key, '_ovb_') === 0;
    });

    wp_send_json_success([
        'product_id'            => $product_id,
        'calendar_data_type'    => gettype($calendar_data),
        'calendar_data_count'   => is_array($calendar_data) ? count($calendar_data) : 'not array',
        'calendar_data_sample'  => is_array($calendar_data) ? array_slice($calendar_data, 0, 2, true) : $calendar_data,
        'price_types'           => $price_types,
        'relevant_meta_keys'    => $relevant_keys,
        'all_ovb_meta'          => array_intersect_key($all_meta, array_flip($relevant_keys)),
    ]);
}

/* ============================================================================
 * 5) CREATE MANUAL BOOKING (WC Order + Calendar link)
 * ==========================================================================*/
add_action('wp_ajax_ovb_admin_create_manual_order', 'ovb_admin_create_manual_order');
function ovb_admin_create_manual_order() {
    // ✅ Security (isti nonce koji šalje admin JS: ovbAdminCalendar.nonce)
    ovb_require_cap_and_nonce('edit_products', 'ovb_nonce', 'nonce');

    $product_id = absint($_POST['product_id'] ?? 0);
    if (!$product_id) {
        wp_send_json_error(['message' => __('Missing product_id', 'ov-booking')], 400);
    }

    // client_data može doći kao array ili JSON string
    $client_raw = $_POST['client_data'] ?? '';
    $client_arr = is_array($client_raw) ? $client_raw : ovb_safe_json_decode($client_raw, []);
    if (!is_array($client_arr)) {
        wp_send_json_error(['message' => __('Invalid client data format', 'ov-booking')], 400);
    }

    // Required polja
    foreach (['firstName','lastName','email','rangeStart','rangeEnd'] as $f) {
        if (empty($client_arr[$f])) {
            wp_send_json_error(['message' => sprintf(__('Missing required field: %s', 'ov-booking'), $f)], 400);
        }
    }

    $start = $client_arr['rangeStart'];
    $end   = $client_arr['rangeEnd'];
    $d1 = DateTime::createFromFormat('Y-m-d', $start);
    $d2 = DateTime::createFromFormat('Y-m-d', $end);
    if (!$d1 || !$d2) {
        wp_send_json_error(['message' => __('Invalid date format. Expected YYYY-MM-DD', 'ov-booking')], 400);
    }
    if ($start > $end) {
        wp_send_json_error(['message' => __('Start date cannot be after end date', 'ov-booking')], 400);
    }

    // Učitaj postojeći kalendar
    $existing_calendar = get_post_meta($product_id, '_ovb_calendar_data', true);
    if (is_string($existing_calendar)) { $existing_calendar = json_decode($existing_calendar, true); }
    if (!is_array($existing_calendar)) { $existing_calendar = []; }

    // Izračun cene
    $dates = [];
    $current_ts  = strtotime($start);
    $end_ts      = strtotime($end);
    $total_price = 0;

    do {
        $date = date('Y-m-d', $current_ts);
        $dates[] = $date;
        $total_price += isset($existing_calendar[$date]['price']) ? (float) $existing_calendar[$date]['price'] : 0;
        $current_ts = strtotime('+1 day', $current_ts);
    } while ($current_ts <= $end_ts);

    $booking_id = sanitize_text_field($client_arr['bookingId'] ?? (time() . '_' . wp_rand(1000, 9999)));

    try {
        // Kreiraj WC Order
        $order = wc_create_order();
        if (is_wp_error($order)) { throw new Exception('Failed to create WooCommerce order'); }

        $product = wc_get_product($product_id);
        if (!$product) { throw new Exception('Product not found'); }

        $item_id = $order->add_product($product, 1, ['subtotal' => $total_price, 'total' => $total_price]);
        if (!$item_id) { throw new Exception('Failed to add product to order'); }

        // Item meta
        $item = $order->get_item($item_id);
        if ($item) {
            $item->add_meta_data('booking_dates', implode(',', $dates));
            $item->add_meta_data('first_name', $client_arr['firstName']);
            $item->add_meta_data('last_name',  $client_arr['lastName']);
            $item->add_meta_data('email',      $client_arr['email']);
            $item->add_meta_data('phone',      $client_arr['phone'] ?? '');
            $item->add_meta_data('guests',     absint($client_arr['guests'] ?? 1));
            $item->add_meta_data('rangeStart', $start);
            $item->add_meta_data('rangeEnd',   $end);
            $item->add_meta_data('booking_id', $booking_id);
            $item->save();
        }

        // Order meta + billing
        $order->update_meta_data('first_name',         $client_arr['firstName']);
        $order->update_meta_data('last_name',          $client_arr['lastName']);
        $order->update_meta_data('email',              $client_arr['email']);
        $order->update_meta_data('phone',              $client_arr['phone'] ?? '');
        $order->update_meta_data('guests',             absint($client_arr['guests'] ?? 1));
        $order->update_meta_data('start_date',         $start);
        $order->update_meta_data('end_date',           $end);
        $order->update_meta_data('booking_id',         $booking_id);
        $order->update_meta_data('_ovb_booking_id',    $booking_id);
        $order->update_meta_data('all_dates',          implode(',', $dates));
        $order->update_meta_data('ovb_check_in_date',  $start);
        $order->update_meta_data('ovb_check_out_date', $end);
        $order->update_meta_data('_ovb_guests_num',    absint($client_arr['guests'] ?? 1));

        $order->set_billing_first_name($client_arr['firstName']);
        $order->set_billing_last_name ($client_arr['lastName']);
        $order->set_billing_email     ($client_arr['email']);
        $order->set_billing_phone     ($client_arr['phone'] ?? '');

        $order->set_total($total_price);
        $order->save();
        $order->update_status('completed', 'Manual booking created via admin calendar');

        // Update calendar (last day available, ostali booked)
        foreach ($dates as $i => $date) {
            if (!isset($existing_calendar[$date]) || !is_array($existing_calendar[$date])) {
                $existing_calendar[$date] = [
                    'status'   => 'available',
                    'isPast'   => (strtotime($date) < strtotime(date('Y-m-d'))),
                    'price'    => isset($existing_calendar[$date]['price']) ? (float) $existing_calendar[$date]['price'] : 0,
                    'priceType'=> $existing_calendar[$date]['priceType'] ?? '',
                    'clients'  => [],
                ];
            }
            if (!isset($existing_calendar[$date]['clients']) || !is_array($existing_calendar[$date]['clients'])) {
                $existing_calendar[$date]['clients'] = [];
            }

            // Ukloni duplikat klijenta istog bookingId
            $existing_calendar[$date]['clients'] = array_values(array_filter(
                $existing_calendar[$date]['clients'],
                function ($c) use ($booking_id) {
                    return !isset($c['bookingId']) || $c['bookingId'] !== $booking_id;
                }
            ));

            $existing_calendar[$date]['clients'][] = [
                'bookingId'  => $booking_id,
                'firstName'  => sanitize_text_field($client_arr['firstName']),
                'lastName'   => sanitize_text_field($client_arr['lastName']),
                'email'      => sanitize_email($client_arr['email']),
                'phone'      => sanitize_text_field($client_arr['phone'] ?? ''),
                'guests'     => absint($client_arr['guests'] ?? 1),
                'rangeStart' => $start,
                'rangeEnd'   => $end,
                'isCheckin'  => ($i === 0),
                'isCheckout' => ($i === count($dates) - 1),
                'order_id'   => $order->get_id(),
            ];

            $existing_calendar[$date]['status'] = ($i === count($dates) - 1) ? 'available' : 'booked';
        }

        ovb_update_post_meta_strict($product_id, '_ovb_calendar_data', $existing_calendar);

        ovb_log_error('Manual order created successfully: Order ID ' . $order->get_id() . ', Booking ID: ' . $booking_id, 'admin-calendar');

        wp_send_json_success([
            'message'      => __('Reservation created successfully!', 'ov-booking'),
            'order_id'     => $order->get_id(),
            'booking_id'   => $booking_id,
            'total'        => wc_price($total_price),
            'calendarData' => $existing_calendar, // front odmah rerenderuje
            'dates'        => $dates,
            'client'       => [
                'firstName' => $client_arr['firstName'],
                'lastName'  => $client_arr['lastName'],
                'email'     => $client_arr['email'],
                'guests'    => absint($client_arr['guests'] ?? 1),
            ],
        ]);
    } catch (Exception $e) {
        ovb_log_error('Manual order creation failed: ' . $e->getMessage(), 'admin-calendar');
        wp_send_json_error(['message' => 'Failed to create booking: ' . $e->getMessage()], 500);
    }
}