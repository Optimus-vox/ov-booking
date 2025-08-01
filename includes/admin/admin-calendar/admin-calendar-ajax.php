<?php
defined('ABSPATH') || exit;

// ┏━ Security & capability checks for all our AJAX handlers ━━━━━━━━━━━━━━━━━━━
function ovb_manage_ajax_security( $nonce_action, $nonce_field = 'nonce' ) {
    check_ajax_referer( $nonce_action, $nonce_field );
    if ( ! current_user_can( 'edit_products' ) ) {
        wp_send_json_error( __( 'Security check failed', 'ov-booking' ) );
    }
}

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

//check in and check out
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

add_action('wp_ajax_ovb_admin_create_manual_order', 'ovb_admin_create_manual_order');

if ( ! function_exists( 'ovb_admin_create_manual_order' ) ) {
// function ovb_admin_create_manual_order() {

//     // Verify nonce + capability
//     ovb_manage_ajax_security( 'ovb_nonce' );

//     $product_id  = absint($_POST['product_id'] ?? 0);
//     $client_data_json = stripslashes($_POST['client_data'] ?? '');
//     $client_data = is_array($_POST['client_data'])
//         ? ovb_sanitize_client_data($_POST['client_data'])
//         : json_decode($client_data_json, true);

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

//     // 2. Upisi u kalendar (post meta product-a)
//     $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
//     $calendar_data = $calendar_data && is_array($calendar_data) ? $calendar_data : [];
//     // Generisi sve datume izmedju rangeStart i rangeEnd
//     $start = DateTime::createFromFormat('d.m.Y', trim($client_data['rangeStart']));
//     $end   = DateTime::createFromFormat('d.m.Y', trim($client_data['rangeEnd']));
//     if (!$start || !$end) {
//         wp_send_json_error(__('Invalid date format', 'ov-booking'));
//         wp_die();
//     }
//     $bookingId = !empty($client_data['bookingId']) ? $client_data['bookingId'] : (time() . '_' . rand(1000, 9999));
//     $current = clone $start;
//     while ($current <= $end) {
//         $date_key = $current->format('Y-m-d');
//         if (!isset($calendar_data[$date_key])) {
//             $calendar_data[$date_key] = [
//                 'status'  => 'booked',
//                 'clients' => [],
//                 'isPast'  => (strtotime($date_key) < strtotime(date('Y-m-d')))
//             ];
//         }
//         $calendar_data[$date_key]['status'] = 'booked';
//         if (!isset($calendar_data[$date_key]['clients']) || !is_array($calendar_data[$date_key]['clients'])) {
//             $calendar_data[$date_key]['clients'] = [];
//         }
//         $calendar_data[$date_key]['clients'][] = [
//             'bookingId'  => $bookingId,
//             'firstName'  => sanitize_text_field($client_data['firstName']),
//             'lastName'   => sanitize_text_field($client_data['lastName']),
//             'email'      => sanitize_email($client_data['email']),
//             'phone'      => sanitize_text_field($client_data['phone'] ?? ''),
//             'guests'     => absint($client_data['guests'] ?? 1),
//             'rangeStart' => $client_data['rangeStart'],
//             'rangeEnd'   => $client_data['rangeEnd'],
//             'isCheckin'  => ($current == $start),
//             'isCheckout' => ($current == $end)
//         ];
//         $current->modify('+1 day');
//     }
//     update_post_meta($product_id, '_ovb_calendar_data', $calendar_data);

//     // 3. Kreiraj WooCommerce order
//     try {
//         $order = wc_create_order();
//         if (is_wp_error($order)) {
//             throw new Exception(__('Failed to create order', 'ov-booking'));
//         }
//         $product = wc_get_product($product_id);
//         if (!$product) {
//             throw new Exception(__('Product not found', 'ov-booking'));
//         }
//         $item_id = $order->add_product($product, 1);
//         if (!$item_id) {
//             throw new Exception(__('Failed to add product to order', 'ov-booking'));
//         }
//         $order->set_billing_first_name(sanitize_text_field($client_data['firstName']));
//         $order->set_billing_last_name(sanitize_text_field($client_data['lastName']));
//         $order->set_billing_email(sanitize_email($client_data['email']));
//         $order->set_billing_phone(sanitize_text_field($client_data['phone'] ?? ''));
//         $order->update_meta_data('_ovb_booking_id', $bookingId);
//         $order->update_meta_data('_ovb_range_start', $client_data['rangeStart']);
//         $order->update_meta_data('_ovb_range_end', $client_data['rangeEnd']);
//         $order->update_meta_data('_ovb_guests', absint($client_data['guests'] ?? 1));
//         $order->set_status('completed');
//         $order->save();
//         ovb_log('Manual order created: ' . $order->get_id(), 'admin-calendar');
//         wp_send_json_success([
//             'message' => __('Order created successfully', 'ov-booking'),
//             'order_id' => $order->get_id()
//         ]);
//     } catch (Exception $e) {
//         ovb_log('Manual order creation failed: ' . $e->getMessage(), 'admin-calendar');
//         wp_send_json_error(__('Failed to create order: ', 'ov-booking') . $e->getMessage());
//     }
//     wp_die();
// }
// Hook i handler za kreiranje manual ordera
// add_action( 'wp_ajax_ovb_admin_create_manual_order', 'ovb_admin_create_manual_order' );
function ovb_admin_create_manual_order() {
    // 1) Security: nonce + capability
    check_ajax_referer( 'ovb_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_products' ) ) {
        wp_send_json_error( __( 'Security check failed', 'ov-booking' ) );
    }

    // 2) Podaci iz JS
    $product_id       = absint( $_POST['product_id'] ?? 0 );
    $client_data_json = wp_unslash( $_POST['client_data'] ?? '{}' );
    $client_data      = json_decode( $client_data_json, true );

    if ( ! $product_id || ! is_array( $client_data ) ) {
        wp_send_json_error( __( 'Invalid data provided', 'ov-booking' ) );
    }

    // 3) Obavezna polja
    foreach ( [ 'rangeStart', 'rangeEnd', 'firstName', 'lastName', 'email' ] as $field ) {
        if ( empty( $client_data[ $field ] ) ) {
            wp_send_json_error( sprintf( __( 'Missing required field: %s', 'ov-booking' ), $field ) );
        }
    }

    // 4) Kreiranje narudžbe & update kalendara
    try {
        $result = ovb_process_manual_booking( $product_id, $client_data );
        wp_send_json_success( $result );
    } catch ( Exception $e ) {
        ovb_log( 'Manual booking error: ' . $e->getMessage(), 'error' );
        wp_send_json_error( __( 'Failed to create booking: ', 'ov-booking' ) . $e->getMessage() );
    }

    wp_die();
}
}

// Dodaj ovu funkciju u admin-calendar-ajax.php za testiranje

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