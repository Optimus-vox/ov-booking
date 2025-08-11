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




// === Booking meta helpers (centralizovano) ===
if ( ! function_exists('ovb_get_order_booking_meta') ) {
    function ovb_get_order_booking_meta( $order_or_id ) {
        $order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );
        if ( ! $order ) {
            return ['check_in' => '', 'check_out' => '', 'guests' => null];
        }
        $check_in  = $order->get_meta('ovb_check_in_date') ?: $order->get_meta('_ovb_start_date') ?: $order->get_meta('start_date');
        $check_out = $order->get_meta('ovb_check_out_date') ?: $order->get_meta('_ovb_end_date')   ?: $order->get_meta('end_date');
        $guests    = $order->get_meta('_ovb_guests_num')     ?: $order->get_meta('guests');

        return [
            'check_in'  => $check_in ?: '',
            'check_out' => $check_out ?: '',
            'guests'    => ( '' !== (string) $guests ? (int) $guests : null ),
        ];
    }
}
if ( ! function_exists('ovb_fmt_date') ) {
    function ovb_fmt_date( $date ) {
        return $date ? esc_html( date_i18n( get_option('date_format'), strtotime( $date ) ) ) : '—';
    }
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

function ovb_set_order_data( WC_Order $order, array $order_data ) {

	// 1) Graceful fallback ako nema guest-ova
	$guest = $order_data['guest_data'][0] ?? [
		'first_name' => '',
		'last_name'  => '',
		'email'      => '',
		'phone'      => '',
	];

	// 2) Laka sanitizacija (wc_clean radi rekurzivno)
	$order_data = wc_clean( $order_data );
	$guest      = wc_clean( $guest );

	// 3) Meta polja (bez dupliranja _ovb_guests_num – već ga imamo ovde)
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
		// nove “admin friendly” ključeve
		'ovb_check_in_date'  => $order_data['start_date'],
		'ovb_check_out_date' => $order_data['end_date'],
	];

	// 4) Billing podaci
	$order->set_billing_first_name( $guest['first_name'] );
	$order->set_billing_last_name ( $guest['last_name'] );
	$order->set_billing_email     ( $guest['email'] );
	$order->set_billing_phone     ( $guest['phone'] );

	// 5) Bulk upis meta-podataka
	foreach ( $meta_fields as $key => $value ) {
		$order->update_meta_data( $key, $value );
	}

	// 6) Odmah persistujemo promene
	$order->save();
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