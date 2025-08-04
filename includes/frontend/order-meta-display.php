<?php
defined('ABSPATH') || exit;

if (empty($order) || !is_a($order, 'WC_Order')) return;

// Ikonice (inline SVG)
$checkin_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" style="margin-right:4px;" viewBox="0 0 16 16"><path d="M13 3a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0V5.414L9.707 7.707a1 1 0 0 1-1.414-1.414L10.586 4H9a1 1 0 1 1 0-2h4z"/><path d="M3.5 3A1.5 1.5 0 0 0 2 4.5v7A1.5 1.5 0 0 0 3.5 13h7a1.5 1.5 0 0 0 1.5-1.5V9a1 1 0 0 1 2 0v2.5A3.5 3.5 0 0 1 10.5 15h-7A3.5 3.5 0 0 1 0 11.5v-7A3.5 3.5 0 0 1 3.5 1H6a1 1 0 0 1 0 2H3.5z"/></svg>';
$checkout_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" style="margin-right:4px;" viewBox="0 0 16 16"><path d="M10.5 3A1.5 1.5 0 0 1 12 4.5V7a1 1 0 0 1-2 0V5.414L9.707 5.707a1 1 0 1 1-1.414-1.414L9.586 3H10.5z"/><path d="M1 4a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2H3v9h7v-1.5a1 1 0 0 1 2 0V14a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4z"/></svg>';

foreach ($order->get_items() as $item) {
    $product = $item->get_product();

    $range_start    = $item->get_meta('rangeStart');
    $range_end      = $item->get_meta('rangeEnd');
    $guests         = $item->get_meta('guests');
    $booking_dates  = $item->get_meta('booking_dates');

    echo '<div class="ovb-order-meta">';
    echo '<h4 style="margin-top:1rem;">' . esc_html($product->get_name()) . '</h4>';


    if ($range_start) {
        echo '<p>' . $checkin_icon . '<strong>' . esc_html__('Check-in:', 'ov-booking') . '</strong> ' . esc_html($range_start) . '</p>';
    }

    if ($range_end) {
        echo '<p>' . $checkout_icon . '<strong>' . esc_html__('Check-out:', 'ov-booking') . '</strong> ' . esc_html($range_end) . '</p>';
    }



    if ($guests) {
     
        echo '<p><strong>' . esc_html__('Guests:', 'ov-booking') . '</strong>: ' . intval($guests) . '</p>';
    }

    if ($booking_dates) {
        echo '<p><strong>' . esc_html__('Booking Dates', 'ov-booking') . '</strong><br>' . esc_html($booking_dates) . '</p>';
    }


    echo '</div><hr>';
}


echo '</div>';
