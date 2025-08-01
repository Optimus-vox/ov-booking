<?php
defined('ABSPATH') || exit;

/**
 * =========================
 *  OV Booking Cart Hooks
 * =========================
 */

// --- Helpers dependency (fallback na helpers.php) ---
if (!function_exists('ovb_get_product_calendar_data')) {
    require_once dirname(__DIR__) . '/helpers/helpers.php';
}

/**
 * Save booking data when adding to cart
 */
add_filter('woocommerce_add_cart_item_data', 'ovb_save_booking_data_to_cart', 10, 3);
function ovb_save_booking_data_to_cart($cart_item_data, $product_id, $variation_id) {
    if (empty($_POST['all_dates'])) return $cart_item_data;

    $booking_data = [
        'start_date'  => sanitize_text_field($_POST['start_date'] ?? ''),
        'end_date'    => sanitize_text_field($_POST['end_date'] ?? ''),
        'all_dates'   => sanitize_text_field($_POST['all_dates']),
        'guests'      => max(1, absint($_POST['guests'] ?? 1)),
        'unique_key'  => md5(microtime() . wp_rand()),
    ];
    $dates = explode(',', $booking_data['all_dates']);
    $booking_data['nights'] = max(0, count($dates) - 1);
    $booking_data['ovb_all_dates'] = $booking_data['all_dates']; // legacy compat

    ovb_log('Booking data added to cart: ' . json_encode($booking_data), 'cart');
    return array_merge($cart_item_data, $booking_data);
}

/**
 * Restore booking data from session
 */
add_filter('woocommerce_get_cart_item_from_session', 'ovb_restore_cart_booking_data', 20, 2);
function ovb_restore_cart_booking_data($cart_item, $values) {
    foreach (['start_date', 'end_date', 'all_dates', 'guests', 'ovb_all_dates', 'unique_key', 'nights'] as $key) {
        if (isset($values[$key])) $cart_item[$key] = $values[$key];
    }
    return $cart_item;
}

/**
 * Prevent multiple bookings and remove empty items from cart
 */
add_action('woocommerce_before_cart', 'ovb_clean_cart_items');
function ovb_clean_cart_items() {
    if (!WC()->cart) return;
    $seen = [];
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (empty($cart_item['ovb_all_dates'])) {
            WC()->cart->remove_cart_item($cart_item_key);
            ovb_log('Cart item removed: missing dates', 'cart');
            continue;
        }
        if (in_array($cart_item['ovb_all_dates'], $seen, true)) {
            WC()->cart->remove_cart_item($cart_item_key);
            ovb_log('Cart item removed: duplicate dates', 'cart');
        } else {
            $seen[] = $cart_item['ovb_all_dates'];
        }
    }
}

/**
 * Main price calculation for booking cart item (with fallback)
 */
add_action('woocommerce_before_calculate_totals', 'ovb_calculate_booking_totals', 10, 1);
function ovb_calculate_booking_totals($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $cart_item) {
        if (empty($cart_item['ovb_all_dates'])) continue;
        $cart_item['data']->set_price(ovb_calculate_cart_item_price($cart_item));
    }
}

function ovb_calculate_cart_item_price($cart_item) {
    $dates         = explode(',', $cart_item['ovb_all_dates']);
    $calendar_data = ovb_get_product_calendar_data($cart_item['product_id']);
    $price_types   = ovb_get_product_price_types($cart_item['product_id']);
    $nights        = isset($cart_item['nights']) ? intval($cart_item['nights']) : max(0, count($dates) - 1);
    $billable      = array_slice($dates, 0, $nights);
    $total = 0;
    foreach ($billable as $d) {
        $total += isset($calendar_data[$d]['price'])
            ? floatval($calendar_data[$d]['price'])
            : floatval($price_types['regular']); // fallback na regular
    }
    return $total;
}

/**
 * Display booking details (period, nights, guests, prices if enabled)
 */
add_filter('woocommerce_get_item_data', 'ovb_display_cart_booking_data', 10, 2);
function ovb_display_cart_booking_data($item_data, $cart_item) {
    if (empty($cart_item['ovb_all_dates'])) return $item_data;

    $dates         = explode(',', sanitize_text_field($cart_item['ovb_all_dates']));
    $calendar_data = ovb_get_product_calendar_data($cart_item['product_id']);
    $price_types   = ovb_get_product_price_types($cart_item['product_id']);

    // Summary info
    if (!empty($dates)) {
        $item_data[] = [
            'key'   => __('Booking Period', 'ov-booking'),
            'value' => date_i18n(get_option('date_format'), strtotime($dates[0])) . ' - ' . date_i18n(get_option('date_format'), strtotime(end($dates))),
        ];
        $item_data[] = [
            'key'   => __('Nights', 'ov-booking'),
            'value' => max(0, count($dates) - 1),
        ];
        if (!empty($cart_item['guests']) && $cart_item['guests'] > 1) {
            $item_data[] = [
                'key'   => __('Guests', 'ov-booking'),
                'value' => $cart_item['guests'],
            ];
        }
    }
    // Detailed per-night prices if enabled
    if (apply_filters('ovb_show_detailed_cart_prices', false)) {
        foreach ($dates as $d) {
            $pretty = date_i18n('d.m.Y', strtotime($d));
            $price = isset($calendar_data[$d]['price'])
                ? wc_price(floatval($calendar_data[$d]['price']))
                : wc_price(floatval($price_types['regular']));
            $item_data[] = [
                'key'   => esc_html($pretty),
                'value' => $price,
            ];
        }
    }
    return $item_data;
}

/**
 * Hide "× qty" and force booking quantity to 1 everywhere
 */
add_filter('woocommerce_cart_item_name', 'ovb_customize_cart_item_name', 10, 3);
function ovb_customize_cart_item_name($product_name, $cart_item, $cart_item_key) {
    if (!empty($cart_item['ovb_all_dates'])) {
        return '<span class="ovb-cart-product-name">' . esc_html($product_name) . '</span>';
    }
    return $product_name;
}

// Ukloni input za quantity (korpa, mini-cart, checkout)
add_filter('woocommerce_cart_item_quantity', function($qty, $cart_item_key, $cart_item) {
    if (!empty($cart_item['ovb_all_dates'])) return '<span class="ovb-fixed-quantity">1</span>';
    return $qty;
}, 10, 3);

// Onemogući izmene količine (update_cart, custom requests)
add_action('woocommerce_before_cart_update', function() {
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (!empty($cart_item['ovb_all_dates'])) {
            $_POST['cart'][$cart_item_key]['qty'] = 1;
        }
    }
});

/**
 * Validation: allow only one booking at a time, require at least one night
 */
add_filter('woocommerce_add_to_cart_validation', 'ovb_validate_add_to_cart', 10, 3);
function ovb_validate_add_to_cart($passed, $product_id, $qty) {
    // Samo jedan booking u korpi!
    if (WC()->cart && WC()->cart->get_cart_contents_count() > 0) {
        WC()->cart->empty_cart();
        ovb_log('Cart emptied before adding new booking', 'cart');
    }
    // Mora biti bar jedna noć (2 datuma)
    if (!empty($_POST['all_dates'])) {
        $dates = explode(',', sanitize_text_field($_POST['all_dates']));
        if (count($dates) < 2) {
            wc_add_notice(__('Please select at least one night for your booking.', 'ov-booking'), 'error');
            ovb_log('Add to cart failed: less than one night selected', 'cart');
            return false;
        }
    }
    return $passed;
}

/**
 * Redirect to cart after add-to-cart (non-AJAX only)
 */
add_filter('woocommerce_add_to_cart_redirect', function($url) {
    if (!empty($_POST['all_dates'])) {
        return wc_get_cart_url();
    }
    return $url;
});

/**
 * AJAX handler: Empty cart
 */
add_action('wp_ajax_ovb_empty_cart', 'ovb_handle_empty_cart');
add_action('wp_ajax_nopriv_ovb_empty_cart', 'ovb_handle_empty_cart');
function ovb_handle_empty_cart() {
    check_ajax_referer('ovb_nonce', 'nonce');
    if (WC()->cart) {
        WC()->cart->empty_cart();
        ovb_log('AJAX cart emptied', 'cart');
        wp_send_json_success(['message' => __('Cart emptied successfully', 'ov-booking')]);
    }
    wp_send_json_error(['message' => __('Failed to empty cart', 'ov-booking')]);
}

/**
 * Log AJAX add-to-cart event
 */
add_action('woocommerce_ajax_added_to_cart', function($product_id) {
    ovb_log('AJAX product added to cart: ' . $product_id, 'cart');
});

/**
 * Cart fragments for AJAX cart count (mini-cart)
 */
add_filter('woocommerce_add_to_cart_fragments', function($fragments) {
    ob_start();
    ?>
    <span class="ovb-cart-count"><?php echo WC()->cart->get_cart_contents_count(); ?></span>
    <?php
    $fragments['.ovb-cart-count'] = ob_get_clean();
    return $fragments;
});

/**
 * Booking info in totals area (below total)
 */
add_action('woocommerce_cart_totals_after_order_total', function() {
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (!empty($cart_item['ovb_all_dates'])) {
            echo '<tr class="ovb-booking-summary">';
            echo '<th colspan="2" style="text-align: center; padding-top: 20px; border-top: 2px solid #ddd;">';
            echo '<small>' . __('Booking charges are calculated per night', 'ov-booking') . '</small>';
            echo '</th></tr>';
            break;
        }
    }
});

// /**
//  * Enqueue cart-specific scripts and styles
//  */
// add_action('wp_enqueue_scripts', function() {
//     if (!is_cart()) return;
//     wp_enqueue_style(
//         'ovb-cart-style',
//         OVB_BOOKING_URL . 'assets/css/ov-cart.css',
//         [],
//         filemtime(OVB_BOOKING_PATH . 'assets/css/ov-cart.css')
//     );
//     wp_enqueue_script(
//         'ovb-cart-script',
//         OVB_BOOKING_URL . 'assets/js/ov-cart.js',
//         ['jquery', 'wc-cart'],
//         filemtime(OVB_BOOKING_PATH . 'assets/js/ov-cart.js'),
//         true
//     );
//     wp_localize_script('ovb-cart-script', 'ovbCartVars', [
//         'ajax_url'           => admin_url('admin-ajax.php'),
//         'nonce'              => wp_create_nonce('ovb_nonce'),
//         'empty_cart_confirm' => __('Are you sure you want to empty your cart?', 'ov-booking'),
//         'checkout_url'       => wc_get_checkout_url(),
//         'is_user_logged_in'  => is_user_logged_in(),
//         'currency_symbol'    => get_woocommerce_currency_symbol(),
//     ]);
// });
