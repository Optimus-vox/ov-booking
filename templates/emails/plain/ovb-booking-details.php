<?php
defined('ABSPATH') || exit;
/** @var WC_Order $order */
/** @var WC_Order_Item_Product $item */

$fmt_date = function($date){
    $ts = strtotime($date);
    return $ts ? date_i18n(get_option('date_format'), $ts) : '—';
};

$get_dates = function($order, $item){
    if (function_exists('ovb_get_booking_dates_from_order')) {
        return (array) ovb_get_booking_dates_from_order($order, $item);
    }
    $raw = $item->get_meta('booking_dates', true);
    if (is_string($raw) && trim($raw) !== '') {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
    $start = $order->get_meta('_ovb_start_date') ?: $order->get_meta('start_date');
    $end   = $order->get_meta('_ovb_end_date')   ?: $order->get_meta('end_date');
    $out = [];
    if ($start && $end) {
        $s = strtotime($start); $e = strtotime($end);
        if ($s && $e && $s <= $e) {
            for ($t=$s; $t <= $e; $t += DAY_IN_SECONDS) $out[] = date('Y-m-d', $t);
        }
    }
    return $out;
};

$dates     = $get_dates($order, $item);
$check_in  = $dates ? reset($dates) : ($order->get_meta('_ovb_start_date') ?: $order->get_meta('start_date'));
$check_out = $dates ? end($dates)   : ($order->get_meta('_ovb_end_date')   ?: $order->get_meta('end_date'));
$nights    = ($check_in && $check_out) ? max(0, (int) floor((strtotime($check_out)-strtotime($check_in))/DAY_IN_SECONDS)) : 0;

$first = $order->get_meta('booking_client_first_name') ?: $order->get_billing_first_name() ?: $item->get_meta('first_name', true);
$last  = $order->get_meta('booking_client_last_name')  ?: $order->get_billing_last_name()  ?: $item->get_meta('last_name', true);
$email = $order->get_meta('booking_client_email')      ?: $order->get_billing_email()      ?: $item->get_meta('email', true);
$phone = $order->get_meta('booking_client_phone')      ?: $order->get_billing_phone()      ?: $item->get_meta('phone', true);

$guests = (int) (
    $order->get_meta('_ovb_guests_total')
    ?: $order->get_meta('_ovb_guests_num')
    ?: $order->get_meta('guests')
    ?: $item->get_meta('guests', true)
    ?: 1
);

$booking_id = $item->get_meta('booking_id', true);

echo "\n";
echo "— " . __('Booking details','ov-booking') . " —\n";
if ($check_in || $check_out) {
    echo sprintf("%s: %s",
        __('Dates','ov-booking'),
        trim(($check_in?$fmt_date($check_in):'') . ' – ' . ($check_out?$fmt_date($check_out):''))
    );
    if ($nights) echo " (" . sprintf(_n('%d night','%d nights',$nights,'ov-booking'), $nights) . ")";
    echo "\n";
}
if ($first || $last) echo __('Guest','ov-booking') . ': ' . trim($first.' '.$last) . "\n";
if ($email)          echo 'Email: ' . $email . "\n";
if ($phone)          echo __('Phone','ov-booking') . ': ' . $phone . "\n";
echo __('Guests','ov-booking') . ': ' . $guests . "\n";
if ($booking_id)     echo 'Booking ID: ' . $booking_id . "\n";
echo "\n";
