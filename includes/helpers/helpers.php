<?php
defined('ABSPATH') || exit;

require_once dirname(__DIR__) . '/helpers/logger.php';

// 🧩 Admin Calendar meta box
add_action('add_meta_boxes', 'add_calendar_meta_box');
function add_calendar_meta_box()
{
    if (current_user_can('manage_woocommerce')) {
        add_meta_box(
            'product_calendar_meta_box',
            'Calendar Settings',
            'render_calendar_meta_box',
            'product',
            'normal',
            'high'
        );
    }
}


// 🗺️ Google Maps meta box
add_action('add_meta_boxes', 'google_maps_iframe_meta_box');
function google_maps_iframe_meta_box()
{
    if (current_user_can('manage_woocommerce')) {
        add_meta_box(
            'google_maps_iframe_box',
            'Google Maps Iframe',
            'google_maps_iframe_meta_box_callback',
            'product',
            'normal',
            'default'
        );
    }
}


add_action('wp_ajax_ov_save_calendar_data', 'ov_save_calendar_data');
function ov_save_calendar_data() {
    if (! current_user_can('edit_products')) {
        wp_send_json_error('Unauthorized');
    }

    $product_id    = intval( $_POST['product_id'] ?? 0 );
    $calendar_data = json_decode( stripslashes( $_POST['calendar_data'] ?? '' ), true ); // promeni stripslashes u json_decode
    $price_types   = $_POST['price_types'] ?? [];

    if ( ! $product_id || ! is_array( $calendar_data ) ) {
        wp_send_json_error('Invalid data');
    }

    // Ako ima bar jednog datuma -> snimi calendar_data u meta
    if ( is_array( $calendar_data ) && count( $calendar_data ) > 0 ) {
        ov_log_error( 'Saving calendar data: ' . print_r( $calendar_data, true ) );
        update_post_meta( $product_id, '_ov_calendar_data', $calendar_data );
    } else {
        // NE sme da prepiše praznim nizom!
        ov_log_error( 'Skipping calendar_data update because it is empty' );
        // Nemojte pozivati update_post_meta ovde
    }

    // Ovde snimamo samo price_types (uvek—čak i ako je calendar_data prazan)
    if ( is_array( $price_types ) ) {
        ov_log_error( 'Saving price types: ' . print_r( $price_types, true ) );
        update_post_meta( $product_id, '_ov_price_types', $price_types );
    }

    wp_send_json_success('Data saved');
}
// helper za datume (edit iz cart u single)
function ovb_generate_all_dates($start, $end)
{
    $arr = [];
    $current = strtotime($start);
    $end_ts = strtotime($end);
    while ($current <= $end_ts) {
        $arr[] = date('YYYY-MM-DD' === 'YYYY-MM-DD' ? 'Y-m-d' : 'Y-m-d', $current);
        $current = strtotime('+1 day', $current);
    }
    return $arr;
}

// 💾 Čuvanje cena prilikom ručnog snimanja proizvoda
add_action('save_post_product', 'ov_save_price_types_meta_box_data');
function ov_save_price_types_meta_box_data($post_id)
{
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $types = [
        'regular_price',
        'weekend_price',
        'discount_price',
        'custom_price',
    ];

    $stored_types = [];

    foreach ($types as $field) {
        if (isset($_POST[$field])) {
            $stored_types[str_replace('_price', '', $field)] = floatval($_POST[$field]);
        }
    }

    if (!empty($stored_types)) {
        update_post_meta($post_id, '_ov_price_types', $stored_types);
    }
}


// 💾 Čuvanje statusa dana kada se klikne "Update" dugme na proizvodu
add_action('save_post_product', 'ov_save_bulk_status_rule');
function ov_save_bulk_status_rule($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    $bulk_status = sanitize_text_field($_POST['ov_bulk_status'] ?? '');
    $apply_rule = sanitize_text_field($_POST['ov_status_apply_rule'] ?? '');
    $daterange = sanitize_text_field($_POST['ov_status_daterange'] ?? '');

    if (!$bulk_status || !$apply_rule)
        return;

    $calendar = get_post_meta($post_id, '_ov_calendar_data', true);
    if (!is_array($calendar))
        $calendar = [];

    $year = date('Y');
    $month = date('m');
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    for ($i = 1; $i <= $daysInMonth; $i++) {
        $date = sprintf('%s-%02d-%02d', $year, $month, $i);
        $dow = date('w', strtotime($date));

        if (
            ($apply_rule === 'weekdays' && $dow >= 1 && $dow <= 5) ||
            ($apply_rule === 'weekends' && ($dow == 0 || $dow == 6)) ||
            $apply_rule === 'full_month'
        ) {
            $calendar[$date]['status'] = $bulk_status;
        }

        if ($apply_rule === 'custom' && $daterange) {
            [$start, $end] = explode(' - ', $daterange);
            $ts = strtotime($date);
            if ($ts >= strtotime($start) && $ts <= strtotime($end)) {
                $calendar[$date]['status'] = $bulk_status;
            }
        }
    }

    update_post_meta($post_id, '_ov_calendar_data', $calendar);
}

// Manual from admin
// Bulletproof: Kreiranje manuelnog Woo ordera iz admin kalendara (AJAX)
add_action('wp_ajax_ovb_admin_create_manual_order', 'ovb_admin_create_manual_order');
function ovb_admin_create_manual_order() {
    // Log za debug
    error_log('🔥 POZVAN ADMIN MANUAL ORDER HANDLER');

    // 1. Security
    if (!current_user_can('edit_products')) {
        error_log('❌ Unauthorized user tried to create manual order');
        wp_send_json_error('Unauthorized');
        return;
    }
    if (isset($_POST['security']) && !wp_verify_nonce($_POST['security'], 'ov_calendar_nonce')) {
        error_log('❌ Security check (nonce) failed');
        wp_send_json_error('Security check failed');
        return;
    }

    // 2. Input parsing
    $product_id = intval($_POST['product_id'] ?? 0);
    $client_data_input = $_POST['client_data'] ?? '';
if (is_array($client_data_input)) {
    $client_data = $client_data_input;
} else {
    $client_data = json_decode(stripslashes($client_data_input), true);
}
error_log('🔥 client_data array: ' . print_r($client_data, true));
    error_log('🔥 product_id: ' . $product_id);
    

    if (!$product_id || !is_array($client_data)) {
        error_log('❌ Invalid data: product_id or client_data');
        wp_send_json_error('Invalid data provided');
        return;
    }

    // 3. Validacija polja
    $required_fields = ['rangeStart', 'rangeEnd', 'firstName', 'lastName', 'email'];
    foreach ($required_fields as $field) {
        if (empty($client_data[$field])) {
            error_log("❌ Missing required field: $field");
            wp_send_json_error("Missing required field: $field");
            return;
        }
    }

    $start = sanitize_text_field($client_data['rangeStart']);
    $end = sanitize_text_field($client_data['rangeEnd']);

    // 4. Validacija datuma
    if (!DateTime::createFromFormat('Y-m-d', $start) || !DateTime::createFromFormat('Y-m-d', $end)) {
        error_log('❌ Invalid date format');
        wp_send_json_error('Invalid date format. Use YYYY-MM-DD');
        return;
    }
    if (strtotime($start) > strtotime($end)) {
        error_log('❌ Start date after end date');
        wp_send_json_error('Start date cannot be after end date');
        return;
    }

    // 5. Preuzmi i validiraj kalendar
    $calendar_data = get_post_meta($product_id, '_ov_calendar_data', true);
    if (!is_array($calendar_data)) $calendar_data = [];

    // 6. Kreiraj niz datuma, računaj total cenu, i proveri duplikate
    $dates = [];
    $total_price = 0;
    $current = strtotime($start);
    $end_ts = strtotime($end);

    do {
        $date = date('Y-m-d', $current);
        $dates[] = $date;

        // Provera duplog bookinga po bookingId
        if (!empty($calendar_data[$date]['clients'])) {
            foreach ($calendar_data[$date]['clients'] as $cl) {
                if (isset($cl['bookingId']) && $cl['bookingId'] == ($client_data['bookingId'] ?? '')) {
                    error_log("❌ Date $date already booked for this bookingId");
                    wp_send_json_error("Date $date is already booked for this client");
                    return;
                }
            }
        }
        $price = isset($calendar_data[$date]['price']) ? floatval($calendar_data[$date]['price']) : 0;
        $total_price += $price;
        $current = strtotime('+1 day', $current);
    } while ($current <= $end_ts);

    if (empty($dates)) {
        error_log('❌ No valid dates in range');
        wp_send_json_error('No valid dates found in range');
        return;
    }

    $booking_id = sanitize_text_field($client_data['bookingId'] ?? (time() . '_' . rand(1000, 9999)));
    $guests = intval($client_data['guests'] ?? 1);
    if ($guests < 1) $guests = 1;

    // 7. Kreiraj order sa try/catch za debug
    try {
        $order = wc_create_order();
        if (!$order || is_wp_error($order)) {
            error_log('❌ Failed to create order: ' . print_r($order, true));
            throw new Exception('Failed to create WooCommerce order');
        }
        error_log("✅ Order kreiran: " . $order->get_id());
        $item_id = $order->add_product(wc_get_product($product_id), 1, [
            'subtotal' => $total_price,
            'total' => $total_price,
        ]);
        if (!$item_id) throw new Exception('Failed to add product to order');

        // Order item meta sa prefiksima (za future-proofing)
        $order_item = $order->get_item($item_id);
        if ($order_item) {
            $order_item->add_meta_data('_ovb_booking_dates', implode(',', $dates));
            $order_item->add_meta_data('_ovb_first_name', sanitize_text_field($client_data['firstName']));
            $order_item->add_meta_data('_ovb_last_name', sanitize_text_field($client_data['lastName']));
            $order_item->add_meta_data('_ovb_email', sanitize_email($client_data['email']));
            $order_item->add_meta_data('_ovb_phone', sanitize_text_field($client_data['phone'] ?? ''));
            $order_item->add_meta_data('_ovb_guests', $guests);
            $order_item->add_meta_data('_ovb_range_start', $start);
            $order_item->add_meta_data('_ovb_range_end', $end);
            $order_item->add_meta_data('_ovb_booking_id', $booking_id);
            $order_item->save();
        }

        // Order meta
        $order->update_meta_data('_ovb_first_name', sanitize_text_field($client_data['firstName']));
        $order->update_meta_data('_ovb_last_name', sanitize_text_field($client_data['lastName']));
        $order->update_meta_data('_ovb_email', sanitize_email($client_data['email']));
        $order->update_meta_data('_ovb_phone', sanitize_text_field($client_data['phone'] ?? ''));
        $order->update_meta_data('_ovb_guests', $guests);
        $order->update_meta_data('_ovb_start_date', $start);
        $order->update_meta_data('_ovb_end_date', $end);
        $order->update_meta_data('_ovb_booking_id', $booking_id);

        $order->set_total($total_price);
        $order->save();

        // Automatski status na "completed"
        $order->update_status('completed');

        // 8. Update kalendara (za svaki dan u nizu)
        foreach ($dates as $date) {
            if (!isset($calendar_data[$date]) || !is_array($calendar_data[$date])) {
                $calendar_data[$date] = [
                    'status' => 'available',
                    'isPast' => (strtotime($date) < strtotime(date('Y-m-d'))),
                    'price' => 0,
                    'priceType' => '',
                    'clients' => [],
                ];
            }
            if (!isset($calendar_data[$date]['clients']) || !is_array($calendar_data[$date]['clients'])) {
                $calendar_data[$date]['clients'] = [];
            }
            // Remove duplicate bookingId
            $calendar_data[$date]['clients'] = array_values(array_filter(
                $calendar_data[$date]['clients'],
                function($c) use ($booking_id) {
                    return !isset($c['bookingId']) || $c['bookingId'] !== $booking_id;
                }
            ));
            // Upisi novog klijenta
            $calendar_data[$date]['clients'][] = [
                'bookingId'   => $booking_id,
                'firstName'   => sanitize_text_field($client_data['firstName']),
                'lastName'    => sanitize_text_field($client_data['lastName']),
                'email'       => sanitize_email($client_data['email']),
                'phone'       => sanitize_text_field($client_data['phone'] ?? ''),
                'guests'      => $guests,
                'rangeStart'  => $start,
                'rangeEnd'    => $end,
                'isCheckin'   => $date === $start,
                'isCheckout'  => $date === $end,
                'order_id'    => $order->get_id(),
            ];
            $calendar_data[$date]['status'] = 'booked';
        }
        // Checkout dan = available (sve prazno)
        $checkout_date = date('Y-m-d', strtotime($end . ' +1 day'));
        if (!isset($calendar_data[$checkout_date]) || !is_array($calendar_data[$checkout_date])) {
            $calendar_data[$checkout_date] = [
                'status' => 'available',
                'clients' => [],
                'isPast' => (strtotime($checkout_date) < strtotime(date('Y-m-d'))),
                'price' => 0,
                'priceType' => '',
            ];
        } else {
            $calendar_data[$checkout_date]['status'] = 'available';
            $calendar_data[$checkout_date]['clients'] = [];
        }
        update_post_meta($product_id, '_ov_calendar_data', $calendar_data);

        // Success log
        error_log("✅ Manual booking created - Order ID: {$order->get_id()}, Booking ID: $booking_id");

        wp_send_json_success([
            'order_id'   => $order->get_id(),
            'order_status' => $order->get_status(),
            'first_name' => $client_data['firstName'],
            'last_name'  => $client_data['lastName'],
            'booking_id' => $booking_id,
            'total'      => $total_price,
            'dates'      => $dates,
            'calendar_saved' => !empty($calendar_data[$dates[0]]),
            'message'    => 'Booking created successfully'
        ]);

    } catch (Exception $e) {
        error_log("❌ Manual booking creation failed: " . $e->getMessage());
        wp_send_json_error('Failed to create booking: ' . $e->getMessage());
    }
}


// Manual from admin dodavanje nove kolone "Guest Name i surname"
add_filter('manage_edit-shop_order_columns', function($columns) {
    // Ubaci odmah posle order id
    $new_columns = [];
    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        if ($key === 'order_number') {
            $new_columns['guest_name'] = __('Guest', 'ov-booking');
        }
    }
    return $new_columns;
});

// Prikazivanje imena i prezimenja u koloni kad se doda order iz admina
add_action('manage_shop_order_posts_custom_column', function($column, $post_id) {
    if ($column === 'guest_name') {
        $first = get_post_meta($post_id, 'first_name', true);
        $last = get_post_meta($post_id, 'last_name', true);
        echo esc_html(trim("$first $last"));
    }
}, 10, 2);



//remove price from shop page

add_action('wp', function () {
    if (function_exists('is_shop') && is_shop()) {
        remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
    }
});

// checkout fix
function ovb_get_checkout_url()
{
    $page_id = wc_get_page_id('checkout');
    if ($page_id && get_post_status($page_id) === 'publish') {
        return get_permalink($page_id);
    }

    // Fallback: pokušaj da pronađeš ručno po slug-u
    $page = get_page_by_path('checkout');
    if ($page && get_post_status($page->ID) === 'publish') {
        return get_permalink($page->ID);
    }

    // Total fallback
    return home_url('/checkout/');
}

//remove woo temp button - kasnije razraditi ovo
function ovb_reset_woocommerce_pages()
{
    foreach ([
        'woocommerce_cart_page_id',
        'woocommerce_checkout_page_id',
        'woocommerce_myaccount_page_id',
        'woocommerce_shop_page_id',
    ] as $key) {
        delete_option($key);
    }

    if (function_exists('ov_log_error')) {
        ov_log_error('🧹 WooCommerce stranice resetovane ručno iz admina.', 'general');
    }
}

function ovb_sanitize_calendar_data($calendar_data) {
    foreach ($calendar_data as $date => &$data) {
        if (!isset($data['clients']) || !is_array($data['clients'])) {
            $data['clients'] = [];
        }
        if (empty($data['clients'])) {
            $data['status'] = 'available';
        }
    }
    unset($data);
}

// vremena za checkin/out | helper za kalendar
function ovb_get_checkin_time($product_id) {
    $info = get_post_meta($product_id, '_apartment_additional_info', true);
    return !empty($info['checkin_time']) ? $info['checkin_time'] : '14:00';
}
function ovb_get_checkout_time($product_id) {
    $info = get_post_meta($product_id, '_apartment_additional_info', true);
    return !empty($info['checkout_time']) ? $info['checkout_time'] : '10:00';
}


