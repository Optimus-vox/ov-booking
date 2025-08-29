<?php
defined('ABSPATH') || exit;
/** @var WC_Order $order */
/** @var WC_Order_Item_Product $item */

$fmt_date = function($date){
    if (function_exists('ovb_format_date')) return ovb_format_date($date);
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
?>
<table class="ovb-email-booking" cellspacing="0" cellpadding="0" style="margin:8px 0 0; border-collapse:collapse; font-size:13px; line-height:1.45; color:#111;">
  <tr>
    <td colspan="2" style="padding:6px 8px; background:#f5f5fa; border:1px solid #e5e7eb; border-bottom:none; font-weight:600;">
      <?php echo esc_html__('Booking details','ov-booking'); ?>
    </td>
  </tr>

  <?php if ($check_in || $check_out): ?>
  <tr>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none; width:140px; color:#6b7280;"><?php echo esc_html__('Dates','ov-booking'); ?></td>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none;">
      <?php
      echo esc_html( trim(($check_in?$fmt_date($check_in):'') . ' – ' . ($check_out?$fmt_date($check_out):'')) );
      if ($nights) echo ' · ' . esc_html( sprintf(_n('%d night','%d nights',$nights,'ov-booking'), $nights) );
      ?>
    </td>
  </tr>
  <?php endif; ?>

  <?php if ($first || $last): ?>
  <tr>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none; width:140px; color:#6b7280;"><?php echo esc_html__('Guest','ov-booking'); ?></td>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none;"><?php echo esc_html( trim($first.' '.$last) ); ?></td>
  </tr>
  <?php endif; ?>

  <?php if ($email): ?>
  <tr>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none; width:140px; color:#6b7280;">Email</td>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none;"><a href="mailto:<?php echo esc_attr( sanitize_email($email) ); ?>"><?php echo esc_html($email); ?></a></td>
  </tr>
  <?php endif; ?>

  <?php if ($phone): ?>
  <tr>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none; width:140px; color:#6b7280;"><?php echo esc_html__('Phone','ov-booking'); ?></td>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none;"><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/','',$phone)); ?>"><?php echo esc_html($phone); ?></a></td>
  </tr>
  <?php endif; ?>

  <tr>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none; width:140px; color:#6b7280;"><?php echo esc_html__('Guests','ov-booking'); ?></td>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none;"><?php echo esc_html((string)$guests); ?></td>
  </tr>

  <?php if ($booking_id): ?>
  <tr>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none; width:140px; color:#6b7280;">Booking&nbsp;ID</td>
    <td style="padding:6px 8px; border:1px solid #e5e7eb; border-top:none;"><?php echo esc_html($booking_id); ?></td>
  </tr>
  <?php endif; ?>
</table>
