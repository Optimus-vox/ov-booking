<?php
defined( 'ABSPATH' ) || exit;

class OVB_iCal_Service {

    /**
     * Generate ICS content for a given order
     */
    public static function generate_ics_string( WC_Order $order ): string {
        // Get timezone from WordPress settings
        $timezone = new DateTimeZone( wp_timezone_string() );
        
        // Get check-in and check-out dates from order meta
        $start_meta = $order->get_meta( 'start_date' );
        $end_meta   = $order->get_meta( 'end_date' );
        $pid        = self::get_product_id( $order );

        // Get Check-in and Check-out times raw
        $checkin_time_raw = function_exists('ovb_get_checkin_time') ? ovb_get_checkin_time($pid) : '';
        $checkout_time_raw = function_exists('ovb_get_checkout_time') ? ovb_get_checkout_time($pid) : '';

        // Get Check-in and Check-out times 
        $checkin_time = preg_match('/^\d{1,2}:\d{2}$/', $checkin_time_raw) ? $checkin_time_raw : '14:00';
        $checkout_time = preg_match('/^\d{1,2}:\d{2}$/', $checkout_time_raw) ? $checkout_time_raw : '10:00';

        
        // Create DateTime objects with local timezone
        $start_obj = new DateTime( $start_meta . ' ' . $checkin_time, $timezone );
        $end_obj   = new DateTime( $end_meta   . ' ' . $checkout_time, $timezone );
        
        // Convert to UTC for ICS format
        $start_obj->setTimezone( new DateTimeZone( 'UTC' ) );
        $end_obj->setTimezone( new DateTimeZone( 'UTC' ) );
        
        $start_iso = $start_obj->format( 'Ymd\THis' );
        $end_iso   = $end_obj->format( 'Ymd\THis' );
        $dtstamp   = gmdate( 'Ymd\THis' ); // Current UTC time

        $summary  = self::get_event_summary( $order );
        $details  = self::build_ics_details( $order );
        $location = self::build_location_string( $order );

        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "METHOD:REQUEST\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "PRODID:-//OV Booking//EN\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:{$order->get_id()}@" . parse_url( home_url(), PHP_URL_HOST ) . "\r\n";
        $ics .= "DTSTAMP:{$dtstamp}\r\n";
        $ics .= "DTSTART:{$start_iso}\r\n";
        $ics .= "DTEND:{$end_iso}\r\n";
        $ics .= "SUMMARY:" . self::escape_ical_text($summary) . "\r\n";
        $ics .= "DESCRIPTION:" . $details . "\r\n";
        if ( $location ) {
            $ics .= "LOCATION:" . self::escape_ical_text($location) . "\r\n";
        }
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    /**
     * Build plain‐text details block for ICS file with proper escaping
     */
    private static function build_ics_details( WC_Order $order ): string {
        
        $pid        = self::get_product_id( $order );
        $apt_type   = self::get_accommodation_type( $pid );
        $apt_title  = get_the_title( $pid );
        $first_name = $order->get_billing_first_name();
        $last_name  = $order->get_billing_last_name();
        // Get timezone from WordPress settings
        $timezone = new DateTimeZone( wp_timezone_string() );
        
        // Get check-in and check-out dates from order meta
        $start_meta = $order->get_meta( 'start_date' );
        $end_meta   = $order->get_meta( 'end_date' );
       
        // Get Check-in and Check-out times raw
        $checkin_time_raw = function_exists('ovb_get_checkin_time') ? ovb_get_checkin_time($pid) : '';
        $checkout_time_raw = function_exists('ovb_get_checkout_time') ? ovb_get_checkout_time($pid) : '';
        
        $checkin_time = preg_match('/^\d{1,2}:\d{2}$/', $checkin_time_raw) ? $checkin_time_raw : '14:00';
        $checkout_time = preg_match('/^\d{1,2}:\d{2}$/', $checkout_time_raw) ? $checkout_time_raw : '10:00';
        
        // Create DateTime objects with local timezone
        $start_obj = new DateTime( $start_meta . ' ' . $checkin_time, $timezone );
        $end_obj   = new DateTime( $end_meta   . ' ' . $checkout_time, $timezone );


        $items      = $order->get_items();
        $first_item = reset( $items );
        $guests     = $first_item
                      ? ( $first_item->get_meta( 'ovb_guest_count' ) ?: '1' )
                      : '1';

        $address = self::build_location_string( $order );

        // Format date and time
        $date_fmt = get_option( 'date_format', 'd.m.Y' );
        $time_fmt = get_option( 'time_format', 'H:i' );

        // Contact email
        $contact = get_option( 'ovb_contact_email', get_option('admin_email') );

        $lines = [
            "🏠 {$apt_type}: {$apt_title}",
            "👤 Guest: {$first_name} {$last_name}",
            "📍 Address: {$address}",
            // "Check-in: " . $start_obj->format( 'd.m.Y H:i' ),
            // "Check-out: " . $end_obj->format( 'd.m.Y H:i' ),
            "🗓 Check-in: " . $start_obj->format( "{$date_fmt} {$time_fmt}" ),
            "🚪 Check-out: " . $end_obj->format( "{$date_fmt} {$time_fmt}" ),
            "👥 Guests: {$guests}",
            "🔗 Apartment page: " . get_permalink( $pid ),
            "📞 Contact: {$contact}",
            "🔐 Confirmation: #{$order->get_order_number()}",
            "💳 Payment method: " . $order->get_payment_method_title(),
        ];

        $inv_path = ABSPATH . "wp-content/uploads/invoices/{$order->get_id()}.pdf";
        if ( file_exists( $inv_path )) {
            $inv_url = home_url( "/wp-content/uploads/invoices/{$order->get_id()}.pdf" );
            $lines[] = "Invoice: {$inv_url}";
        }

        // Build description with proper line breaks
        $description = implode( "\\n", array_map( [self::class, 'escape_ical_text'], $lines ) );
        
        return $description;
    }

    /**
     * Properly escape text for iCalendar format
     * (RFC 5545 section 3.3.11)
     */
    private static function escape_ical_text( string $text ): string {
        $text = str_replace( '\\', '\\\\', $text );
        $text = str_replace( "\r\n", "\\n", $text );
        $text = str_replace( "\n", "\\n", $text );
        $text = str_replace( ',', '\\,', $text );
        $text = str_replace( ';', '\\;', $text );
        return $text;
    }

    /**
     * Save the ICS file to uploads and return full path
     */
    public static function save_ics_file( WC_Order $order ): string {
        $upload_dir = wp_upload_dir();
        $file_name  = "ovb-order-{$order->get_id()}.ics";
        $file_path  = trailingslashit( $upload_dir['basedir'] ) . $file_name;
        file_put_contents( $file_path, self::generate_ics_string( $order ) );
        return $file_path;
    }

    /**
     * Get public URL for download link
     */
    public static function get_ics_download_url( WC_Order $order ): string {
        $upload_dir = wp_upload_dir();
        return trailingslashit( $upload_dir['baseurl'] ) . "ovb-order-{$order->get_id()}.ics";
    }

    /**
     * Render buttons on Thank You page
     */
    public static function render_calendar_buttons( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $ics_url    = rest_url( "ov-booking/v1/order/{$order_id}/ics" );
        $google_url = self::get_google_calendar_url( $order );

        echo '<p class="ovb-add-to-calendar">';
        printf(
            '<a href="%s" target="_blank" class="button" download="booking-%d.ics">📥 Dodaj u kalendar</a> ',
            esc_url( $ics_url ),
            $order_id
        );
        printf(
            '<a href="%s" target="_blank" class="button">📅 Add to Google Calendar</a>',
            esc_url( $google_url )
        );
        echo '</p>';
    }

    /**
     * Attach ICS to completed order email
     */
    public static function attach_ics_to_email( $attachments, $email_id, WC_Order $order ) {
        if ( 'customer_completed_order' === $email_id ) {
            $attachments[] = self::save_ics_file( $order );
        }
        return $attachments;
    }

    /**
     * Build Google Calendar add-event URL
     */
    public static function get_google_calendar_url( WC_Order $order ): string {
        $pid = self::get_product_id( $order );

        // Get timezone from WordPress settings
        $timezone = new DateTimeZone( wp_timezone_string() );

        
        // Get check-in and check-out dates from order meta
        $start_meta = $order->get_meta( 'start_date' );
        $end_meta   = $order->get_meta( 'end_date' );
        
        // Get Check-in and Check-out times raw
        $checkin_time_raw = function_exists('ovb_get_checkin_time') ? ovb_get_checkin_time($pid) : '';
        $checkout_time_raw = function_exists('ovb_get_checkout_time') ? ovb_get_checkout_time($pid) : '';

        // Get Check-in and Check-out times
        $checkin_time = preg_match('/^\d{1,2}:\d{2}$/', $checkin_time_raw) ? $checkin_time_raw : '14:00';
        $checkout_time = preg_match('/^\d{1,2}:\d{2}$/', $checkout_time_raw) ? $checkout_time_raw : '10:00';
        // Create DateTime objects with local timezone
        $start_obj = new DateTime( $start_meta . ' ' . $checkin_time, $timezone );
        $end_obj   = new DateTime( $end_meta   . ' ' . $checkout_time, $timezone );

        
        // Convert to UTC for Google Calendar format
        $start_obj->setTimezone( new DateTimeZone( 'UTC' ) );
        $end_obj->setTimezone( new DateTimeZone( 'UTC' ) );
        
        $start_iso = $start_obj->format( 'Ymd\THis' );
        $end_iso   = $end_obj->format( 'Ymd\THis' );

        $summary  = self::get_event_summary( $order );
        $details  = self::build_gcal_details( $order );
        $location = self::build_location_string( $order );

        $params = [
            'action'   => 'TEMPLATE',
            'text'     => $summary,
            'dates'    => "{$start_iso}/{$end_iso}",
            'details'  => $details,
            'location' => $location,
            'sf'       => 'true',
            'output'   => 'xml',
        ];
        $query = http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );

        return 'https://calendar.google.com/calendar/render?' . $query;
    }

    /**
     * Build event summary ("Rezervacija – ApartmentName")
     */
    private static function get_event_summary( WC_Order $order ): string {
        $pid       = self::get_product_id( $order );
        $apt_type  = self::get_accommodation_type( $pid );
        $apt_title = get_the_title( $pid );
        return "Rezervacija – {$apt_type} {$apt_title}";
    }

    /**
     * Build the HTML‐br‐separated details block for Google Calendar
     */
    private static function build_gcal_details( WC_Order $order ): string {
        $pid        = self::get_product_id( $order );
        $apt_type   = self::get_accommodation_type( $pid );
        $apt_title  = get_the_title( $pid );
        $first_name = $order->get_billing_first_name();
        $last_name  = $order->get_billing_last_name();

        // Get timezone from WordPress settings
        $timezone = new DateTimeZone( wp_timezone_string() );

        
        
        // Get check-in and check-out dates from order meta
        $start_meta = $order->get_meta( 'start_date' );
        $end_meta   = $order->get_meta( 'end_date' );
       
        // Get Check-in and Check-out times raw
        $checkin_time_raw = function_exists('ovb_get_checkin_time') ? ovb_get_checkin_time($pid) : '';
        $checkout_time_raw = function_exists('ovb_get_checkout_time') ? ovb_get_checkout_time($pid) : '';

        $checkin_time = preg_match('/^\d{1,2}:\d{2}$/', $checkin_time_raw) ? $checkin_time_raw : '14:00';
        $checkout_time = preg_match('/^\d{1,2}:\d{2}$/', $checkout_time_raw) ? $checkout_time_raw : '10:00';
        // Create DateTime objects with local timezone
        $start_obj = new DateTime( $start_meta . ' ' . $checkin_time, $timezone );
        $end_obj   = new DateTime( $end_meta   . ' ' . $checkout_time, $timezone );


        $items      = $order->get_items();
        $first_item = reset( $items );
        $guests     = $first_item ? ( $first_item->get_meta( 'ovb_guest_count' ) ?: '1' ) : '1';

        $address = self::build_location_string( $order );

        // Format dates
        $date_fmt = get_option( 'date_format', 'd.m.Y' );
        $time_fmt = get_option( 'time_format', 'H:i' );

         // Contact email
        $contact = get_option( 'ovb_contact_email', get_option('admin_email') );

        $lines = [
            "🏠 {$apt_type}: {$apt_title}",
            "👤 Guest: {$first_name} {$last_name}",
            "📍 Address: {$address}",
            // "🗓 Check-in: " . $start_obj->format( 'd.m.Y H:i' ),
            // "🚪 Check-out: " . $end_obj->format( 'd.m.Y H:i' ),
            "🗓 Check-in: " . $start_obj->format( "{$date_fmt} {$time_fmt}" ),
            "🚪 Check-out: " . $end_obj->format( "{$date_fmt} {$time_fmt}" ),
            "👥 Guests: {$guests}",
            "🔗 Apartment page: " . get_permalink( $pid ),
            "📞 Contact: {$contact}",
            "🔐 Confirmation: #{$order->get_order_number()}",
            "💳 Payment method: " . $order->get_payment_method_title(),
        ];

        $inv_path = ABSPATH . "wp-content/uploads/invoices/{$order->get_id()}.pdf";
        if ( file_exists( $inv_path ) ) {
            $inv_url  = home_url( "/wp-content/uploads/invoices/{$order->get_id()}.pdf" );
            $lines[]  = "📄 Invoice: {$inv_url}";
        }

        return implode( '<br/>', $lines );
    }

    /**
     * Build the location string from additional_info meta
     */
    private static function build_location_string( WC_Order $order ): string {
        $pid  = self::get_product_id( $order );
        $info = get_post_meta( $pid, '_apartment_additional_info', true );
        if ( ! is_array( $info ) ) {
            return '';
        }
        $parts = [];
        foreach ( [ 'street_name', 'city', 'country' ] as $key ) {
            if ( ! empty( $info[ $key ] ) ) {
                $parts[] = sanitize_text_field( $info[ $key ] );
            }
        }
        return implode( ', ', $parts );
    }

    /**
     * Get first product ID from order
     */
    private static function get_product_id( WC_Order $order ): int {
        $items = $order->get_items();
        $first = reset( $items );
        return $first ? $first->get_product_id() : 0;
    }

    /**
     * Read accommodation type from product meta
     */
    private static function get_accommodation_type( int $product_id ): string {
        $type = get_post_meta( $product_id, 'accommodation_type', true );
        return $type ?: 'Apartment';
    }

    /**
     * REST callback: return inline .ics
     */
    public static function serve_order_ical( WP_REST_Request $request ) {
        $order_id = absint( $request->get_param( 'id' ) );
        $order    = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'not_found', 'Order not found', [ 'status' => 404 ] );
        }

        $ics = self::generate_ics_string( $order );

        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="booking-' . $order_id . '.ics"' );
        echo $ics;
        exit;
    }
} // end class

// Register REST route for .ics
add_action( 'rest_api_init', function() {
    register_rest_route(
        'ov-booking/v1',
        '/order/(?P<id>\d+)/ics',
        [
            'methods'             => 'GET',
            'callback'            => [ 'OVB_iCal_Service', 'serve_order_ical' ],
            'permission_callback' => '__return_true',
        ]
    );
} );