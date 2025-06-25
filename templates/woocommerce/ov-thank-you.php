<?php
defined( 'ABSPATH' ) || exit;

// Uzmemo order ID iz query var ili endpointa
$order_id = 0;
if ( function_exists( 'get_query_var' ) && get_query_var( 'order-received' ) ) {
    $order_id = absint( get_query_var( 'order-received' ) );
} elseif ( isset( $_GET['order-received'] ) ) {
    $order_id = absint( $_GET['order-received'] );
}

$order = wc_get_order( $order_id );

if ( ! $order ) {
    echo '<p>' . esc_html__( 'Order not found.', 'ov-booking' ) . '</p>';
    return;
}

// Priprema podataka za prikaz
$all_dates = $order->get_meta( 'all_dates' );

if ( is_array( $all_dates ) ) {
    $dates_arr = $all_dates;
} else {
    $dates_arr = ! empty( $all_dates ) 
        ? explode( ',', sanitize_text_field( $all_dates ) ) 
        : [];
}

$nights = count( $dates_arr );

$start     = $order->get_meta( 'start_date' );
$end       = $order->get_meta( 'end_date' );
$guests    = intval( $order->get_meta( 'guests' ) );

// Uključujemo header iz tvoje standalone logike
get_header();



?>
<div class="ov-thank-you-wrapper">


    <div class="ov-thankyou">
        
        <h2><?php

        
        esc_html_e( 'Thank you for your booking!', 'ov-booking' ); ?></h2>

        <ul class="ov-order-info">
            <li>
                <strong><?php esc_html_e( 'Order number:', 'ov-booking' ); ?></strong>
                <?php echo esc_html( $order->get_order_number() ); ?>
            </li>
            <li>
                <strong><?php esc_html_e( 'Order date:', 'ov-booking' ); ?></strong>
                <?php echo esc_html( date_i18n( get_option( 'date_format' ), $order->get_date_created()->getTimestamp() ) ); ?>
            </li>
            <li>
                <strong><?php esc_html_e( 'Order total:', 'ov-booking' ); ?></strong>
                <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
            </li>
            <li>
                <strong><?php esc_html_e( 'Payment method:', 'ov-booking' ); ?></strong>
                <?php echo esc_html( $order->get_payment_method_title() ); ?>
            </li>
        </ul>

        <h3><?php esc_html_e( 'Booking details', 'ov-booking' ); ?></h3>
        <ul class="ov-booking-summary">
            <li>
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php
        echo esc_html(
          date_i18n( 'd.m.Y', strtotime( $start ) ) .
          ' – ' .
          date_i18n( 'd.m.Y', strtotime( $end ) )
        );
      ?>
            </li>
            <li>
                <span class="dashicons dashicons-admin-home"></span>
                <?php
        echo esc_html(
          $nights . ' ' . _n( 'night', 'nights', $nights, 'ov-booking' )
        );
      ?>
            </li>
            <li>
                <span class="dashicons dashicons-groups"></span>
                <?php
        echo esc_html(
          $guests . ' ' . _n( 'guest', 'guests', $guests, 'ov-booking' )
        );
      ?>
            </li>
        </ul>
        <?php
          $product_id = 0;
          foreach ( $order->get_items() as $item ) {
              $product_id = $item->get_product_id();
              break;
          }
          
          $first_name = $order->get_billing_first_name();
          $last_name  = $order->get_billing_last_name();
          $product    = wc_get_product($product_id);
          $apartment  = get_the_title($product_id);
          
          // 1) Postavi check-in i check-out u 10:00
          $start_obj = new DateTime($start . ' 10:00');
          $end_obj   = new DateTime($end . ' 10:00');
          $start_date = $start_obj->format('Ymd\THis\Z');
          $end_date   = $end_obj->format('Ymd\THis\Z');
          
          // 2) Lokacija (iz custom field-a ako postoji)
          $address = get_post_meta($product_id, '_apartment_address', true);

        $summary = "Booking – {$apartment}";

          $additional_info = get_post_meta( $product_id, '_apartment_additional_info', true );
          if ( ! is_array($additional_info) ) {
                $additional_info = [];
            }

            $location_parts = [];

            $product_permalink = get_permalink($product_id);

            $ical_url = trailingslashit( get_permalink($product_id) ) . 'ical/';
            $webcal_url = str_replace('https://', 'webcal://', get_permalink($product_id)) . 'ical/';


            if ( ! empty( $additional_info['street_name'] ) ) {
                $location_parts[] = $additional_info['street_name'];
            }
            if ( ! empty( $additional_info['city'] ) ) {
                $location_parts[] = $additional_info['city'];
            }
            if ( ! empty( $additional_info['country'] ) ) {
                $location_parts[] = $additional_info['country'];
            }
            $location = implode(', ', $location_parts);
            
            // Sastavljanje opisa za Google Calendar
            $details = implode("<br/>", [
                '🏠 Apartment: ' . $apartment,
                '👤 Guest: ' . $first_name . ' ' . $last_name,
                '🗓 Check-in: ' . $start_obj->format('d.m.Y H:i'),
                '🚪 Check-out: ' . $end_obj->format('d.m.Y H:i'),
                '👥 Guests: ' . $guests,
                '📍 Address: ' . $location,
                '🔗 Apartment page: ' . get_permalink($product_id),
                '📞 Contact: info@example.com',
                '🔐 Confirmation: #' . $order->get_order_number(),
                '💳 Payment method: ' . $order->get_payment_method_title(),           
            ]);
            // Provera da li faktura postoji
            $file_path = ABSPATH . "wp-content/uploads/invoices/{$order_id}.pdf";
            $file_url  = home_url("/wp-content/uploads/invoices/{$order_id}.pdf");

            if ( file_exists( $file_path ) ) {
                $details[] = '📄 Invoice: ' . $file_url;
            }
            
            // Generisanje Google Calendar linka
            $google_url = 'https://www.google.com/calendar/render?action=TEMPLATE' .
                '&text=' . urlencode($summary) .
                '&dates=' . $start_date . '/' . $end_date .
                '&details=' . rawurlencode($details) .
                '&location=' . urlencode($location) .
                '&sf=true&output=xml';
        ?>
            <div class="ov-add-to-calendar">
            <a class="button button-primary" href="<?php echo esc_url($google_url); ?>" target="_blank" rel="noopener">
                📅 Add to Google Calendar
            </a>
            <a class="button" href="<?php echo esc_url($webcal_url); ?>" target="_blank" rel="noopener">
                📥 Add to iCloud / Outlook / Android
            </a>
        </div>


        <p class="ov-contact-info">
            <?php esc_html_e( 'If you have any questions, please contact us at', 'ov-booking' ); ?>
            <a href="mailto:info@example.com">info@example.com</a>
        </p>
    </div>
</div>
<?php
// Uključujemo footer iz tvoje standalone logike
get_footer();