<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/logger.php';
/**
 * =========================
 *  OV Booking Helper Functions
 * =========================
 */

/**
 * LOGGING
 * Enhanced logging function (with WP_DEBUG fallback)
 */
if (!function_exists('ovb_log')) {
    function ovb_log($message, $context = 'general') {
        if (function_exists('ovb_log_error')) {
            ovb_log_error($message, $context);
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("OVB [{$context}]: {$message}");
        }
    }
}

/**
 * DEFAULTS & UTILITIES
 */

// Default price types for all products
function ovb_get_default_price_types() {
    return [
        'regular'  => 0,
        'weekend'  => 0,
        'discount' => 0,
        'custom'   => 0,
    ];
}

// Fetch product price types with automatic fallback
function ovb_get_product_price_types($product_id) {
    $types = get_post_meta($product_id, '_ovb_price_types', true);
    $defaults = ovb_get_default_price_types();
    if (!is_array($types)) $types = [];
    return array_merge($defaults, $types);
}

// Get calendar data with fallback (always returns array)
function ovb_get_product_calendar_data($product_id) {
    $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
    return is_array($calendar_data) ? $calendar_data : [];
}

/**
 * ADMIN META BOXES
 */
// Calendar meta box
add_action('add_meta_boxes', 'ovb_add_calendar_meta_box');
function ovb_add_calendar_meta_box() {
    if (!current_user_can('manage_woocommerce')) return;
    add_meta_box(
        'product_calendar_meta_box',
        __('Calendar Settings', 'ov-booking'),
        'render_calendar_meta_box',
        'product',
        'normal',
        'high'
    );
}

// Google Maps meta box
add_action('add_meta_boxes', 'ovb_add_google_maps_meta_box');
function ovb_add_google_maps_meta_box() {
    if (!current_user_can('manage_woocommerce')) return;
    add_meta_box(
        'google_maps_iframe_box',
        __('Google Maps Iframe', 'ov-booking'),
        'google_maps_iframe_meta_box_callback',
        'product',
        'normal',
        'default'
    );
}


// Manual order from admin (security, fallback, pricing)
// add_action('wp_ajax_ovb_admin_create_manual_order', 'ovb_create_manual_order');
// function ovb_create_manual_order() {
//     if (!current_user_can('edit_products') || !wp_verify_nonce($_POST['nonce'] ?? '', 'ovb_calendar_nonce')) {
//         wp_send_json_error(__('Security check failed', 'ov-booking'));
//         wp_die();
//     }
//     $product_id  = absint($_POST['product_id'] ?? 0);
//     $client_data = ovb_sanitize_client_data($_POST['client_data'] ?? []);

//     if (!$product_id || empty($client_data)) {
//         wp_send_json_error(__('Invalid data provided', 'ov-booking'));
//         wp_die();
//     }
//     $required_fields = ['rangeStart', 'rangeEnd', 'firstName', 'lastName', 'email'];
//     foreach ($required_fields as $field) {
//         if (empty($client_data[$field])) {
//             wp_send_json_error(sprintf(__('Missing required field: %s', 'ov-booking'), $field));
//             wp_die();
//         }
//     }
//     try {
//         $result = ovb_process_manual_booking($product_id, $client_data);
//         wp_send_json_success($result);
//         wp_die();
//     } catch (Exception $e) {
//         ovb_log('Manual booking error: ' . $e->getMessage(), 'error');
//         wp_send_json_error(__('Failed to create booking: ', 'ov-booking') . $e->getMessage());
//         wp_die();
//     }
// }

// === MANUAL ORDER FROM ADMIN CALENDAR ===
// AJAX: POST { action: 'ovb_admin_create_manual_order', nonce, product_id, client_data (JSON) }
// add_action('wp_ajax_ovb_admin_create_manual_order', 'ovb_admin_create_manual_order');
// function ovb_admin_create_manual_order() {
//     check_ajax_referer('ovb_nonce', 'nonce');
//     if (!current_user_can('edit_products')) {
//         wp_send_json_error(__('Security check failed', 'ov-booking'));
//     }

//     $product_id = absint($_POST['product_id'] ?? 0);
//     // $client_data_json = stripslashes($_POST['client_data'] ?? '{}');
//        $client_data_json = wp_unslash( $_POST['client_data']  ?? '{}' );
//     $client_data = json_decode($client_data_json, true);

//     if (!$product_id || !is_array($client_data)) {
//         wp_send_json_error(__('Invalid data provided', 'ov-booking'));
//     }

//     // Validate required fields
//     $required_fields = ['rangeStart', 'rangeEnd', 'firstName', 'lastName', 'email'];
//     foreach ($required_fields as $field) {
//         if (empty($client_data[$field])) {
//             wp_send_json_error(sprintf(__('Missing required field: %s', 'ov-booking'), $field));
//         }
//     }

//     $start_date = $client_data['rangeStart'];
//     $end_date   = $client_data['rangeEnd'];
//     if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
//         wp_send_json_error(__('Invalid date format', 'ov-booking'));
//     }
//     if (strtotime($start_date) > strtotime($end_date)) {
//         wp_send_json_error(__('Start date cannot be after end date', 'ov-booking'));
//     }

//     $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
//     if (!is_array($calendar_data)) $calendar_data = [];

//     // Pravi niz svih datuma (uključujući i poslednji dan)
//     $all_dates = [];
//     $dt = new DateTime($start_date);
//     $dt_end = new DateTime($end_date);
//     while ($dt <= $dt_end) {
//         $all_dates[] = $dt->format('Y-m-d');
//         $dt->modify('+1 day');
//     }

//     // Izračunaj total price iz calendar_data (ako nema - 0)
//     $total_price = 0;
//     foreach ($all_dates as $date) {
//         $day_price = isset($calendar_data[$date]['price']) ? floatval($calendar_data[$date]['price']) : 0;
//         $total_price += $day_price;
//     }

//     // Ako nema cenu, koristi default proizvod cenu (fallback, ali realno treba da piše cenu po danima)
//     $product = wc_get_product($product_id);
//     if (!$product) {
//         wp_send_json_error(__('Product not found', 'ov-booking'));
//     }
//     if ($total_price <= 0) $total_price = floatval($product->get_price());

//     // Kreiraj Woo Order
//     $order = wc_create_order();
//     if (is_wp_error($order)) {
//         wp_send_json_error(__('Failed to create order', 'ov-booking'));
//     }

//     // Dodaj proizvod sa totalnom cenom (OVO JE KLJUČNO!)
//     $item_id = $order->add_product($product, 1, [
//         'subtotal' => $total_price,
//         'total'    => $total_price,
//     ]);
//     if (!$item_id) {
//         wp_send_json_error(__('Failed to add product to order', 'ov-booking'));
//     }

//     // Billing podaci
//     $order->set_billing_first_name(sanitize_text_field($client_data['firstName']));
//     $order->set_billing_last_name(sanitize_text_field($client_data['lastName']));
//     $order->set_billing_email(sanitize_email($client_data['email']));
//     $order->set_billing_phone(sanitize_text_field($client_data['phone'] ?? ''));

//     // Meta podaci
//     $dates_string = implode(',', $all_dates);
//     $booking_id = time() . '_' . rand(1000, 9999);
//     $order->update_meta_data('_ovb_booking_id', $booking_id);
//     $order->update_meta_data('start_date', $start_date);
//     $order->update_meta_data('end_date', $end_date);
//     $order->update_meta_data('guests', absint($client_data['guests'] ?? 1));
//     $order->update_meta_data('all_dates', $dates_string);
//     $order->update_meta_data('booking_client_first_name', sanitize_text_field($client_data['firstName']));
//     $order->update_meta_data('booking_client_last_name', sanitize_text_field($client_data['lastName']));
//     $order->update_meta_data('booking_client_email', sanitize_email($client_data['email']));
//     $order->update_meta_data('booking_client_phone', sanitize_text_field($client_data['phone'] ?? ''));

//     // Item meta podaci
//     $item = $order->get_item($item_id);
//     if ($item) {
//         $item->add_meta_data('ovb_all_dates', $dates_string);
//         $item->add_meta_data('booking_dates', $dates_string);
//         $item->add_meta_data('guests', absint($client_data['guests'] ?? 1));
//         $item->add_meta_data('_ovb_range_start', $start_date);
//         $item->add_meta_data('_ovb_range_end', $end_date);
//         $item->save();
//     }

//     // Upisuje cenu!
//     $order->set_total($total_price);
//     $order->save();
//     $order->set_status('completed', __('Manual booking created via admin calendar.', 'ov-booking'));

//     // Update calendar_data sa klijentom (kao do sada)
//     foreach ($all_dates as $i => $date_key) {
//         if (!isset($calendar_data[$date_key])) {
//             $calendar_data[$date_key] = [
//                 'status'  => 'available',
//                 'clients' => [],
//                 'isPast'  => (strtotime($date_key) < strtotime(date('Y-m-d')))
//             ];
//         }
//         if (!is_array($calendar_data[$date_key]['clients'])) {
//             $calendar_data[$date_key]['clients'] = [];
//         }
//         $calendar_data[$date_key]['clients'][] = [
//             'bookingId'  => $booking_id,
//             'firstName'  => sanitize_text_field($client_data['firstName']),
//             'lastName'   => sanitize_text_field($client_data['lastName']),
//             'email'      => sanitize_email($client_data['email']),
//             'phone'      => sanitize_text_field($client_data['phone'] ?? ''),
//             'guests'     => absint($client_data['guests'] ?? 1),
//             'rangeStart' => $start_date,
//             'rangeEnd'   => $end_date,
//             'isCheckin'  => ($i === 0),
//             'isCheckout' => ($i === count($all_dates) - 1),
//             'order_id'   => $order->get_id()
//         ];
//         $calendar_data[$date_key]['status'] = 'booked';
//     }
//     update_post_meta($product_id, '_ovb_calendar_data', $calendar_data);

//     wp_send_json_success([
//         'order_id'   => $order->get_id(),
//         'booking_id' => $booking_id,
//         'total'      => $total_price,
//         'message'    => __('Order created successfully', 'ov-booking'),
//     ]);
// }


/**
 * BOOKING PROCESSING FUNCTIONS
 */
// Sanitizacija podataka za booking
function ovb_sanitize_client_data($data) {
    if (is_string($data)) $data = json_decode(stripslashes($data), true);
    if (!is_array($data)) return [];
    return array_map(function($value) {
        return is_string($value) ? sanitize_text_field($value) : $value;
    }, $data);
}

// Glavna funkcija za ručno pravljenje bookinga (admin)
function ovb_process_manual_booking($product_id, $client_data) {
    $start = $client_data['rangeStart'];
    $end   = $client_data['rangeEnd'];
    if (!ovb_validate_date_range($start, $end)) {
        throw new Exception(__('Invalid date range', 'ov-booking'));
    }
    $dates         = ovb_generate_date_range($start, $end);
    $calendar_data = ovb_get_product_calendar_data($product_id);
    $price_types   = ovb_get_product_price_types($product_id);

    $total_price = 0;
    foreach ($dates as $date) {
        ovb_check_date_availability($calendar_data, $date, $client_data['bookingId'] ?? '');
        // Ako nema unetu cenu, koristi regular kao fallback
        $total_price += floatval($calendar_data[$date]['price'] ?? $price_types['regular']);
    }

    $order_data = ovb_prepare_order_data($client_data, $dates, $total_price);
    $order      = ovb_create_woocommerce_order($product_id, $order_data);

    ovb_update_calendar_with_booking($product_id, $dates, $order_data, $order->get_id());

    return [
        'order_id'   => $order->get_id(),
        'booking_id' => $order_data['booking_id'],
        'total'      => $total_price,
        'dates'      => $dates,
        'message'    => __('Booking created successfully', 'ov-booking')
    ];
}

/**
 * UTILITY FUNCTIONS
 */

// Provera da li je datum validan
function ovb_validate_date_range($start, $end) {
    $start_date = DateTime::createFromFormat('Y-m-d', $start);
    $end_date   = DateTime::createFromFormat('Y-m-d', $end);
    return $start_date && $end_date && $start_date <= $end_date;
}

// Generiši sve datume u intervalu (uključujući start/end)
function ovb_generate_date_range($start, $end) {
    $dates   = [];
    $current = strtotime($start);
    $end_ts  = strtotime($end);
    while ($current <= $end_ts) {
        $dates[] = date('Y-m-d', $current);
        $current = strtotime('+1 day', $current);
    }
    return $dates;
}

// Legacy kompatibilnost
function ovb_generate_all_dates($start, $end) {
    return ovb_generate_date_range($start, $end);
}

// Provera dostupnosti dana (da nema već tu rezervaciju)
function ovb_check_date_availability($calendar_data, $date, $booking_id) {
    if (!empty($calendar_data[$date]['clients'])) {
        foreach ($calendar_data[$date]['clients'] as $client) {
            if (isset($client['bookingId']) && $client['bookingId'] === $booking_id) {
                throw new Exception(sprintf(__('Date %s is already booked', 'ov-booking'), $date));
            }
        }
    }
}

// Priprema podataka za order
function ovb_prepare_order_data($client_data, $dates, $total_price) {
    $booking_id = $client_data['bookingId'] ?? (time() . '_' . wp_rand(1000, 9999));
    $guests     = max(1, absint($client_data['guests'] ?? 1));
    return [
        'booking_id'  => $booking_id,
        'guests'      => $guests,
        'total_price' => $total_price,
        'guest_data'  => [[
            'first_name' => $client_data['firstName'],
            'last_name'  => $client_data['lastName'],
            'email'      => sanitize_email($client_data['email']),
            'phone'      => $client_data['phone'] ?? '',
            'birthdate'  => $client_data['birthdate'] ?? '',
            'gender'     => $client_data['gender'] ?? '',
            'id_number'  => $client_data['id_number'] ?? '',
            'is_child'   => false,
        ]],
        'dates'      => $dates,
        'start_date' => $dates[0],
        'end_date'   => end($dates),
    ];
}

// Kreiranje WooCommerce ordera
function ovb_create_woocommerce_order($product_id, $order_data) {
    $order = wc_create_order();
    if (!$order || is_wp_error($order)) {
        throw new Exception(__('Failed to create WooCommerce order', 'ov-booking'));
    }
    $item_id = $order->add_product(wc_get_product($product_id), 1, [
        'subtotal' => $order_data['total_price'],
        'total'    => $order_data['total_price'],
    ]);
    if (!$item_id) {
        throw new Exception(__('Failed to add product to order', 'ov-booking'));
    }
    ovb_add_order_item_meta($order->get_item($item_id), $order_data);
    ovb_set_order_data($order, $order_data);
    $order->set_total($order_data['total_price']);
    $order->save();
    $order->update_status('completed');
    return $order;
}

// Dodavanje meta podataka na order item
function ovb_add_order_item_meta($order_item, $order_data) {
    if (!$order_item) return;
    $meta_data = [
        '_ovb_calendar_data' => implode(',', $order_data['dates']),
        '_ovb_booking_id'    => $order_data['booking_id'],
        '_ovb_range_start'   => $order_data['start_date'],
        '_ovb_range_end'     => $order_data['end_date'],
        '_ovb_guests'        => $order_data['guests'],
        'booking_dates'      => implode(',', $order_data['dates']),
        'booking_id'         => $order_data['booking_id'],
        'rangeStart'         => $order_data['start_date'],
        'rangeEnd'           => $order_data['end_date'],
        'guests'             => $order_data['guests'],
    ];
    foreach ($meta_data as $key => $value) {
        $order_item->add_meta_data($key, $value);
    }
    $order_item->save();
}

// Meta podaci za Woo order (billing i prikaz gostiju)
function ovb_set_order_data($order, $order_data) {
    $guest = $order_data['guest_data'][0];
    $meta_fields = [
        '_ovb_guests'      => $order_data['guest_data'],
        '_ovb_start_date'  => $order_data['start_date'],
        '_ovb_end_date'    => $order_data['end_date'],
        '_ovb_guests_num'  => $order_data['guests'],
        '_ovb_booking_id'  => $order_data['booking_id'],
        'start_date'       => $order_data['start_date'],
        'end_date'         => $order_data['end_date'],
        'guests'           => $order_data['guests'],
        'first_name'       => $guest['first_name'],
        'last_name'        => $guest['last_name'],
        'email'            => $guest['email'],
        'phone'            => $guest['phone'],
    ];
    $order->set_billing_first_name($guest['first_name']);
    $order->set_billing_last_name($guest['last_name']);
    $order->set_billing_email($guest['email']);
    $order->set_billing_phone($guest['phone']);
    foreach ($meta_fields as $key => $value) {
        $order->update_meta_data($key, $value);
    }
}

// Update kalendara sa novim bookingom
function ovb_update_calendar_with_booking($product_id, $dates, $order_data, $order_id) {
    $calendar_data = ovb_get_product_calendar_data($product_id);
    $guest         = $order_data['guest_data'][0];
    foreach ($dates as $i => $date) {
        if (!isset($calendar_data[$date]) || !is_array($calendar_data[$date])) {
            $calendar_data[$date] = [
                'status'    => 'available',
                'isPast'    => (strtotime($date) < strtotime(date('Y-m-d'))),
                'price'     => 0,
                'priceType' => '',
                'clients'   => [],
            ];
        }
        if (!isset($calendar_data[$date]['clients'])) $calendar_data[$date]['clients'] = [];
        $calendar_data[$date]['clients'] = array_values(array_filter(
            $calendar_data[$date]['clients'],
            function($c) use ($order_data) {
                return !isset($c['bookingId']) || $c['bookingId'] !== $order_data['booking_id'];
            }
        ));
        $calendar_data[$date]['clients'][] = [
            'bookingId'   => $order_data['booking_id'],
            'firstName'   => $guest['first_name'],
            'lastName'    => $guest['last_name'],
            'email'       => $guest['email'],
            'phone'       => $guest['phone'],
            'guests'      => $order_data['guests'],
            'rangeStart'  => $order_data['start_date'],
            'rangeEnd'    => $order_data['end_date'],
            'isCheckin'   => ($i === 0),
            'isCheckout'  => ($i === count($dates) - 1),
            'order_id'    => $order_id,
        ];
        $calendar_data[$date]['status'] = ($i === count($dates) - 1) ? 'available' : 'booked';
    }
    update_post_meta($product_id, '_ovb_calendar_data', $calendar_data);
}

/**
 * SAVE POST HOOKS
 */
// Snimanje price types na product save
add_action('save_post_product', 'ovb_save_price_types_meta', 10, 1);
function ovb_save_price_types_meta($post_id) {
    if (!current_user_can('edit_post', $post_id)) return;
    $price_types = ['regular_price', 'weekend_price', 'discount_price', 'custom_price'];
    $stored_types = [];
    foreach ($price_types as $field) {
        if (isset($_POST[$field])) {
            $stored_types[str_replace('_price', '', $field)] = floatval($_POST[$field]);
        }
    }
    // Uvek snimi sa fallback-om (nema praznih ključeva)
    update_post_meta($post_id, '_ovb_price_types', array_merge(ovb_get_default_price_types(), $stored_types));
}

// Snimanje bulk status rule na product save
add_action('save_post_product', 'ovb_save_bulk_status_rule', 10, 1);
function ovb_save_bulk_status_rule($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $bulk_status = sanitize_text_field($_POST['ovb_bulk_status'] ?? '');
    $apply_rule  = sanitize_text_field($_POST['ovb_status_apply_rule'] ?? '');
    $daterange   = sanitize_text_field($_POST['ovb_status_daterange'] ?? '');
    if (!$bulk_status || !$apply_rule) return;
    ovb_apply_bulk_status_rule($post_id, $bulk_status, $apply_rule, $daterange);
}

// Bulk rule za status dana
function ovb_apply_bulk_status_rule($product_id, $status, $rule, $daterange = '') {
    $calendar      = ovb_get_product_calendar_data($product_id);
    $year          = date('Y');
    $month         = date('m');
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    for ($i = 1; $i <= $days_in_month; $i++) {
        $date        = sprintf('%s-%02d-%02d', $year, $month, $i);
        $day_of_week = date('w', strtotime($date));
        $should_update = false;
        switch ($rule) {
            case 'weekdays':
                $should_update = ($day_of_week >= 1 && $day_of_week <= 5);
                break;
            case 'weekends':
                $should_update = ($day_of_week == 0 || $day_of_week == 6);
                break;
            case 'full_month':
                $should_update = true;
                break;
            case 'custom':
                if ($daterange && strpos($daterange, ' - ') !== false) {
                    list($start, $end) = explode(' - ', $daterange);
                    $date_ts = strtotime($date);
                    $should_update = ($date_ts >= strtotime($start) && $date_ts <= strtotime($end));
                }
                break;
        }
        if ($should_update) {
            if (!isset($calendar[$date])) $calendar[$date] = [];
            $calendar[$date]['status'] = $status;
        }
    }
    update_post_meta($product_id, '_ovb_calendar_data', $calendar);
}

// Provera da li postoji booking u kalendaru
function ovb_booking_exists_in_calendar($calendar_data, $booking_id) {
    if (!is_array($calendar_data)) return false;
    
    foreach ($calendar_data as $date => $data) {
        if (isset($data['clients']) && is_array($data['clients'])) {
            foreach ($data['clients'] as $client) {
                if (isset($client['bookingId']) && $client['bookingId'] === $booking_id) {
                    return true;
                }
            }
        }
    }
    
    return false;
}

/**
 * ADMIN COLUMNS
 */


/**
 * CHECKOUT & SHOP HELPER FUNCTIONS
 */
// Vraća checkout URL sa fallback-ovima
function ovb_get_checkout_url() {
    $page_id = wc_get_page_id('checkout');
    if ($page_id && get_post_status($page_id) === 'publish') return get_permalink($page_id);
    $page = get_page_by_path('checkout');
    if ($page && get_post_status($page->ID) === 'publish') return get_permalink($page->ID);
    return home_url('/checkout/');
}

// Helperi za checkin/checkout vreme
function ovb_get_checkin_time($product_id) {
    $info = get_post_meta($product_id, '_apartment_additional_info', true);
    return !empty($info['checkin_time']) ? $info['checkin_time'] : '14:00';
}
function ovb_get_checkout_time($product_id) {
    $info = get_post_meta($product_id, '_apartment_additional_info', true);
    return !empty($info['checkout_time']) ? $info['checkout_time'] : '10:00';
}

/**
 * SHOP PAGE OPTIMIZATION
 */
// Ukloni cenu sa shop strane (UI cleaner)
add_action('wp', function () {
    if (function_exists('is_shop') && is_shop()) {
        remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
    }
});

/**
 * WOOCOMMERCE UTILITY (admin only)
 */
// Reset WooCommerce pages (koristi samo kroz admin alatke!)
function ovb_reset_woocommerce_pages() {
    $woo_pages = [
        'woocommerce_cart_page_id',
        'woocommerce_checkout_page_id',
        'woocommerce_myaccount_page_id',
        'woocommerce_shop_page_id',
    ];
    foreach ($woo_pages as $page_option) {
        delete_option($page_option);
    }
    ovb_log('WooCommerce pages reset manually from admin', 'general');
}


/**
 * Debug funkcija za praćenje meta podataka
 */
function ovb_debug_product_meta($product_id) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) return;
    
    $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
    $price_types = get_post_meta($product_id, '_ovb_price_types', true);
    
    error_log("=== OVB DEBUG PRODUCT {$product_id} ===");
    error_log("Calendar data type: " . gettype($calendar_data));
    error_log("Calendar data count: " . (is_array($calendar_data) ? count($calendar_data) : 'not array'));
    error_log("Price types: " . print_r($price_types, true));
    error_log("=== END DEBUG ===");
}

// Hook za praćenje kada se product učitava
add_action('load-post.php', function() {
    if (isset($_GET['post']) && get_post_type($_GET['post']) === 'product') {
        ovb_debug_product_meta(intval($_GET['post']));
    }
});

// --------------------------------------------------
// Ukloni WooCommerce Product Data metabox
// --------------------------------------------------
// add_action( 'add_meta_boxes', 'ovb_remove_product_data_metabox', 25 );
// function ovb_remove_product_data_metabox() {
//     if ( get_post_type() !== 'product' ) {
//         return;
//     }
//     // ID metaboxa je 'woocommerce-product-data'
//     remove_meta_box( 'woocommerce-product-data', 'product', 'normal' );
// }

add_action('admin_init', function() {
    if (class_exists('WooCommerce')) {
        remove_meta_box('woocommerce-product-data', 'product', 'normal');
    }
});

add_action('admin_head', function() {
    if (get_post_type() === 'product') {
        echo '<style>#woocommerce-product-data { display: none !important; }</style>';
    }
});