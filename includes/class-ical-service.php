<?php
defined('ABSPATH') || exit;

class OVB_iCal_Service {
    
    public static function init() {
        // Export
        add_action('parse_request', [__CLASS__, 'handle_ical_request']);
        // Cron import
        add_action('ovb_ical_import', [__CLASS__, 'fetch_and_import']);
        // Manual import
        add_action('wp_ajax_ovb_sync_ical', [__CLASS__, 'ajax_sync_ical']);
    }

    // public static function handle_ical_request($wp) {   
    //     error_log('[OVB iCal] query_vars = ' . print_r($wp->query_vars, true));
    //         if (!isset($wp->query_vars['ical']) || intval($wp->query_vars['ical']) !== 1) {             return;        
    //         }       
    //         $slug = $wp->query_vars['name'] ?? '';       
    //         $post = get_page_by_path($slug, OBJECT, 'product');        
    //         if (!$post) {           
    //             wp_die('Invalid iCal request', 'iCal Error', ['response' => 400]);        
    //         }        
    //         $events = self::get_booking_events($post->ID);        
    //         header('Content-Type: text/calendar; charset=utf-8');        
    //         header('Content-Disposition: attachment; filename="product-' . $post->post_name . '.ics"');        echo self::build_ical($events);        
    //         exit;    
    // }

    public static function handle_ical_request($wp) {
        if (!isset($wp->query_vars['ical'])) {
            return;
        }
    
        error_log('[OVB iCal] query_vars = ' . print_r($wp->query_vars, true));
    
        global $wp_query;
        $post_id = $wp_query->queried_object_id ?? 0;
        $post = get_post($post_id);
    
        if (!$post || $post->post_type !== 'product') {
            // fallback: probaj da pročitaš slug ručno
            $slug_path = $wp->request ?? '';
            $slug_parts = explode('/', trim($slug_path, '/'));
            $slug = $slug_parts[count($slug_parts) - 2] ?? '';
            error_log("[OVB iCal] Fallback slug = $slug");
            $post = get_page_by_path($slug, OBJECT, 'product');
        }
    
        if (!$post || $post->post_type !== 'product') {
            error_log('[OVB iCal] Proizvod nije pronađen ili nije product.');
            wp_die('Invalid iCal request', 'iCal Error', ['response' => 400]);
        }
    
        error_log("[OVB iCal] Pronađen proizvod: ID={$post->ID}");
    
        $events = self::get_booking_events($post->ID);
    
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="product-' . $post->post_name . '.ics"');
        echo self::build_ical($events);
        exit;
    }
    
    


    public static function get_booking_events($product_id) {
        $events = [];
        $orders = wc_get_orders([
            'status' => ['processing', 'completed'],
            'limit'  => -1,
        ]);
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ((int)$item->get_product_id() !== $product_id) {
                    continue;
                }
                $dates_meta = $item->get_meta('ov_all_dates');
                if (empty($dates_meta)) {
                    continue;
                }
                $dates = array_filter(array_map('trim', explode(',', $dates_meta)));
                if (empty($dates)) {
                    continue;
                }
                sort($dates);

                $start_date = (new DateTime($dates[0]))->format('Ymd');
                $end_date_obj = new DateTime(end($dates));
                $end_date_obj->modify('+1 day');
                $end_date = $end_date_obj->format('Ymd');

                $events[] = [
                    'uid'         => sprintf('%s-%s@%s', $order->get_id(), $item->get_id(), parse_url(home_url(), PHP_URL_HOST)),
                    'dtstamp'     => gmdate('Ymd\THis\Z'),
                    'dtstart'     => $start_date,
                    'dtend'       => $end_date,
                    'summary'     => sprintf('Booking – %s', get_the_title($product_id)),
                    'description' => sprintf('Rezervacija %s (Order #%d)', get_the_title($product_id), $order->get_id()),
                    'X-GUEST-NAME'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'X-GUEST-EMAIL' => $order->get_billing_email(),
                    'X-GUEST-PHONE' => $order->get_billing_phone(),
                    'X-GUEST-COUNT' => $item->get_meta('ov_guest_count') ?: '',
                    'X-BOOKING-ID'  => $order->get_id(),
                    'X-PRODUCT-ID'  => $product_id,
                ];
            }
        }
        return $events;
    }

    public static function build_ical($events) {
        $ical  = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//OV Booking//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";

        foreach ($events as $e) {
            $ical .= "BEGIN:VEVENT\r\n";
            foreach ($e as $key => $value) {
                $ical .= strtoupper($key) . ':' . $value . "\r\n";
            }
            $ical .= "END:VEVENT\r\n";
        }

        $ical .= "END:VCALENDAR\r\n";
        return $ical;
    }

    public static function maybe_serve_ical() {
        add_action('parse_request', function ($wp) {
            error_log('[OVB iCal] parse_request triggered');
    
            if (!isset($wp->query_vars['ical']) || intval($wp->query_vars['ical']) !== 1) {
                error_log('[OVB iCal] query_var "ical" nije postavljen ili nije 1');
                return;
            }
    
            $slug = $wp->query_vars['name'] ?? '(not set)';
            error_log("[OVB iCal] Requested product slug = {$slug}");
    
            $post = get_page_by_path($wp->query_vars['name'], OBJECT, 'product');
            if (!$post) {
                error_log('[OVB iCal] Proizvod nije pronađen preko slug-a.');
                wp_die('Invalid iCal request', 'iCal Error', ['response' => 400]);
            }
    
            error_log("[OVB iCal] Pronađen proizvod: ID={$post->ID}");
    
            $events = OVB_iCal_Service::get_booking_events($post->ID);
    
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="product-' . $post->post_name . '.ics"');
    
            echo OVB_iCal_Service::build_ical($events);
            exit;
        });
    }
    
    public static function generate_product_ical($product_id) {
        $events = self::get_booking_events($product_id);
        return self::build_ical($events);
      }

    public static function fetch_and_import_for_product() {
        $products = get_posts(['post_type' => 'product', 'numberposts' => -1]);
        foreach ($products as $product) {
            $urls = get_post_meta($product->ID, '_ovb_ical_urls', true);
            if (empty($urls)) {
                continue;
            }
            $urls = preg_split('/[\r\n]+/', $urls);
            $all_dates = [];
            foreach ($urls as $url) {
                $url = esc_url_raw($url);
                if (empty($url)) {
                    continue;
                }
                $response = wp_safe_remote_get($url, ['timeout' => 20]);
                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                    continue;
                }
                $body = wp_remote_retrieve_body($response);
                try {
                    $vcal = \Sabre\VObject\Reader::read($body);
                   
                    foreach ($vcal->VEVENT as $vevent) {
                        $start = $vevent->DTSTART->getDateTime()->format('Y-m-d');
                        $all_dates[] = $start;
                    }
                } catch (Exception $e) {
                    // skip invalid feeds
                    continue;
                }
            }
            $all_dates = array_unique($all_dates);
            update_post_meta($product->ID, '_ovb_booked_dates', $all_dates);
        }
    }

    public static function ajax_sync_ical() {
        if ( ! check_ajax_referer('ovb_ical_meta','security', false) ) {
          wp_send_json_error();
        }
        $pid = absint( $_POST['product_id'] );
        self::fetch_and_import_for_product( $pid );
        wp_send_json_success();
      }
    
}