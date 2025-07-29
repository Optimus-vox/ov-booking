<?php
defined('ABSPATH') || exit;

$checkout = WC()->checkout();

if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
    $thankyou = plugin_dir_path(__FILE__) . '../../templates/woocommerce/ov-thank-you.php';
    if (file_exists($thankyou)) {
        include $thankyou;
        return;
    }
}

if (function_exists('wc_print_notices')) {
    echo '<div class="ov-checkout-notices">';
    wc_print_notices();
    echo '</div>';
}

if (!class_exists('WC_Cart') || !WC()->cart || WC()->cart->is_empty()) {
    echo '<div class="ov-cart page-cart">';
    echo '<p class="ov-cart-empty">' . esc_html__('Vaša korpa je prazna.', 'ov-booking') . '</p>';
    echo '</div>';
    return;
}

if (!is_user_logged_in()) {
    echo '<div class="ov-cart page-cart">';
    echo '<p class="ov-cart-error">' . esc_html__('You must be logged in to make a booking.', 'ov-booking') . '</p>';
    echo '</div>';
    return;
}

$items = WC()->cart->get_cart();
$cart_item = reset($items);

if (!$cart_item || empty($cart_item['data']) || !($cart_item['data'] instanceof WC_Product)) {
    echo '<div class="ov-cart page-cart">';
    echo '<p class="ov-cart-error">' . esc_html__('Greška pri učitavanju stavke iz korpe.', 'ov-booking') . '</p>';
    echo '</div>';
    return;
}

$product = $cart_item['data'];
$start_date = !empty($cart_item['start_date']) ? sanitize_text_field($cart_item['start_date']) : '';
$end_date = !empty($cart_item['end_date']) ? sanitize_text_field($cart_item['end_date']) : '';
$all_dates = !empty($cart_item['all_dates']) ? array_filter(explode(',', sanitize_text_field($cart_item['all_dates']))) : [];
$guests = !empty($cart_item['guests']) ? intval($cart_item['guests']) : 1;
$nights = isset($cart_item['nights']) ? intval($cart_item['nights']) : max(0, count($all_dates) - 1);

$start_label = $start_date ? date_i18n(get_option('date_format'), strtotime($start_date)) : '';
$end_label = $end_date ? date_i18n(get_option('date_format'), strtotime($end_date)) : '';
$calendar_data = get_post_meta($product->get_id(), '_ov_calendar_data', true);
if (!is_array($calendar_data)) {
    $calendar_data = [];
}

// Pripremi datume i cene po danu TEST !!!! obrisi ako ne radi
$dates_output = '';
$subtotal = 0;
foreach ($all_dates as $i => $date) {
    $timestamp = strtotime($date);
    $pretty_date = date_i18n('d.m.Y', $timestamp);

    // Poslednji dan je checkout → ispiši "Checkout"
    if ($i === count($all_dates) - 1) {
        $dates_output .= '<tr class="ovb-checkout-row"><td class="ovb-date-label">' . esc_html($pretty_date) . ':</td><td class="ovb-date-value ovb-checkout">' . esc_html__('Checkout', 'ov-booking') . '</td></tr>';
    } else {
        // Prikaži cenu za tu noć
        $day_price = !empty($calendar_data[$date]['price']) ? floatval($calendar_data[$date]['price']) : 0;
        $subtotal += $day_price;
        $dates_output .= '<tr class="ovb-checkout-row"><td class="ovb-date-label">' . esc_html($pretty_date) . ':</td><td class="ovb-date-value">' . wc_price($day_price) . '</td></tr>';
    }
}
// Pripremi datume i cene po danu
get_header();

if ( function_exists('is_checkout') && is_checkout() && ! is_wc_endpoint_url('order-received') ) {
    // Registruje i enqueue-uje sve skripte potrebne za Checkout logiku
    WC()->frontend_includes();
    wp_enqueue_script('wc-checkout');          // toggle payment methods
    wp_enqueue_script('wc-country-select');
    wp_enqueue_script('wc-address-i18n');
    wp_enqueue_script('wc-credit-card-form');  // Stripe/Klarna card fields
    
    wp_enqueue_style('woocommerce-general');
    wp_enqueue_style('woocommerce-layout');
    wp_enqueue_style('woocommerce-smallscreen');
}
include plugin_dir_path(__FILE__) . '../../includes/ov-checkout-full-template.php';


get_footer();