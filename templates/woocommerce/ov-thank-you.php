<?php
defined( 'ABSPATH' ) || exit;

// 1) Uzimamo order ID iz query var ili GET parametra
$order_id = 0;
if ( function_exists( 'get_query_var' ) && get_query_var( 'order-received' ) ) {
    $order_id = absint( get_query_var( 'order-received' ) );
} elseif ( isset( $_GET['order-received'] ) ) {
    $order_id = absint( $_GET['order-received'] );
}

$order = wc_get_order( $order_id );

get_header();
?>

<div class="ov-thank-you-wrapper">
    <?php if ( ! $order ) : ?>
        <p><?php esc_html_e( 'Order not found.', 'ov-booking' ); ?></p>
    <?php else : ?>

        <div class="ov-thankyou">
            <h2><?php esc_html_e( 'Thank you for your booking!', 'ov-booking' ); ?></h2>

            <ul class="ov-order-info">
                <li><strong><?php esc_html_e( 'Order number:', 'ov-booking' ); ?></strong> <?php echo esc_html( $order->get_order_number() ); ?></li>
                <li><strong><?php esc_html_e( 'Order date:', 'ov-booking' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), $order->get_date_created()->getTimestamp() ) ); ?></li>
                <li><strong><?php esc_html_e( 'Order total:', 'ov-booking' ); ?></strong> <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></li>
                <li><strong><?php esc_html_e( 'Payment method:', 'ov-booking' ); ?></strong> <?php echo esc_html( $order->get_payment_method_title() ); ?></li>
            </ul>

            <h3><?php esc_html_e( 'Booking details', 'ov-booking' ); ?></h3>
            <ul class="ov-booking-summary">
                <?php
                // Preuzimamo datume i goste iz meta polja
                $dates_meta = $order->get_meta('ovb_all_dates');
                if (!$dates_meta) {
                    $dates_meta = $order->get_meta('all_dates');
                }
                $dates_arr = is_array($dates_meta) ? $dates_meta : ($dates_meta ? explode(',', sanitize_text_field($dates_meta)) : []);
                sort($dates_arr);

                $start  = $order->get_meta('start_date');
                $end    = $order->get_meta('end_date');
                $guests = intval($order->get_meta('guests'));

                // Pravilno računanje broja noćenja:
                if (count($dates_arr) > 1) {
                    $nights = count($dates_arr) - 1;
                } elseif ($start && $end) {
                    $ts_start = strtotime($start);
                    $ts_end   = strtotime($end);
                    $nights   = max(1, (int)round(($ts_end - $ts_start) / DAY_IN_SECONDS));
                } else {
                    $nights = 1;
                }
                ?>
                <li><span class="dashicons dashicons-calendar-alt"></span>
                    <?php
                    echo esc_html(
                        date_i18n('d.m.Y', strtotime($start)) .
                        ' – ' .
                        date_i18n('d.m.Y', strtotime($end))
                    );
                    ?>
                </li>
                <li><span class="dashicons dashicons-admin-home"></span>
                    <?php
                    printf(
                        '%s %s',
                        esc_html($nights),
                        esc_html(_n('night', 'nights', $nights, 'ov-booking'))
                    );
                    ?>
                </li>
                <li><span class="dashicons dashicons-groups"></span>
                    <?php
                    printf(
                        '%s %s',
                        esc_html($guests),
                        esc_html(_n('guest', 'guests', $guests, 'ov-booking'))
                    );
                    ?>
                </li>
            </ul>

            <?php
            // 2) Prikazujemo dugmad za kalendar koristeći naš servis
            if ( class_exists( 'OVB_iCal_Service' ) ) {
                OVB_iCal_Service::render_calendar_buttons( $order_id );
            }
            ?>
        </div>

    <?php endif; ?>
</div>

<?php
get_footer();