<?php
defined('ABSPATH') || exit;

// Admin: Booking Information box na single order ekranu (posle billing/ shipping)
// Admin: Booking Information box na single order ekranu (posle billing/shipping)
add_action('woocommerce_admin_order_data_after_shipping_address', function( $order ){
    $m = function_exists('ovb_get_order_booking_meta') ? ovb_get_order_booking_meta( $order ) : null;
    if ( ! $m || ( empty($m['check_in']) && empty($m['check_out']) && is_null($m['guests']) ) ) { return; }

    //CSS styles
    $li_style = 'display:flex; align-items:center; gap:6px; margin:2px 0 5px;';

    echo '<div class="ovb-booking-details" style="margin-top:12px; padding:12px 14px; background:#f8f9fa; border:1px solid #dcdcde; border-radius:6px;">';
    echo '<h3 style="margin:0 0 8px; font-size:13px;">' . esc_html__('Booking Information', 'ov-booking') . '</h3>';
    echo '<ul style="margin:0; padding-left:5px; list-style:none;">';

    if ( ! empty($m['check_in']) ) {
        echo '<li style="' . esc_attr($li_style) . ' margin-left:-2px; ">'
            . '<svg xmlns="http://www.w3.org/2000/svg" title="Check-in" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-in"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg> <strong>'. esc_html__('Check-in','ov-booking') .':</strong> ' . ovb_fmt_date($m['check_in'])
            . '</li>';
    }

    if ( ! empty($m['check_out']) ) {
        echo '<li style="' . esc_attr($li_style) . '">'
            // tvoj SVG ostaje, samo je sve u flex-u pa je centrirano vertikalno
            . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>'
            . '<strong>'. esc_html__('Check-out','ov-booking') .':</strong> ' . ovb_fmt_date($m['check_out'])
            . '</li>';
    }

    if ( ! is_null($m['guests']) ) {
        echo '<li style="' . esc_attr($li_style) . '">'
            . '<strong>'. esc_html__('Guests','ov-booking') .':</strong> ' . (int) $m['guests']
            . '</li>';
    }

    echo '</ul></div>';
}, 20);
