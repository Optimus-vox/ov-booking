<?php
defined('ABSPATH') || exit;


add_action('template_redirect', function () {
    if (function_exists('is_checkout') && is_checkout() && ! is_wc_endpoint_url('order-received')) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (function_exists('wc_nocache_headers')) wc_nocache_headers();
        nocache_headers();
    }
}, 0);


/**
 * =========================
 *  OV Booking Order Hooks
 * =========================
 */

// Include iCal Service
if (file_exists(OVB_BOOKING_PATH . 'includes/class-ical-service.php')) {
    require_once OVB_BOOKING_PATH . 'includes/class-ical-service.php';
}

/**
 * =========================
 * CHECKOUT VALIDATION
 * =========================
 */

// Helper: truthy checkbox/value
if ( ! function_exists('ovb_truthy') ) {
    function ovb_truthy( $v ): bool {
        if (is_bool($v))   return $v;
        if (is_numeric($v))return (int)$v === 1;
        $v = is_string($v) ? strtolower(trim($v)) : '';
        return in_array($v, ['1','true','yes','on'], true);
    }
}

// Helper: dodaj grešku na WC_Error / WP_Error
if ( ! function_exists('ovb_add_error') ) {
    function ovb_add_error( &$errors, string $code, string $msg ) {
        if ( is_wp_error($errors) ) {
            $errors->add( $code, $msg, ['status' => 400] );
        } elseif ( is_object($errors) && method_exists($errors, 'add') ) {
            $errors->add( $code, $msg );
        }
    }
}

// Core validator (zajednički za Classic & Blocks)
// Core validator (zajednički za Classic & Blocks)
if ( ! function_exists('ovb_validate_checkout_payload') ) {
    function ovb_validate_checkout_payload( array $posted, &$errors ): void {

        // Getter: vrati prvi postojeći ključ (trimovan)
        $get = function($keys, $default = '') use ($posted) {
            foreach ( (array) $keys as $k ) {
                if ( array_key_exists($k, $posted) ) {
                    $v = $posted[$k];
                    if (is_array($v)) {
                        // uzmi prvu vrednost iz niza (npr. iz [] polja)
                        $v = reset($v);
                    }
                    return trim((string)$v);
                }
            }
            return $default;
        };

        /* 1) KONTAKT – uvek obavezno (fallback na billing_*) */
        $contact_first = $get(['ovb_contact_first_name','billing_first_name']);
        $contact_last  = $get(['ovb_contact_last_name','billing_last_name']);
        $contact_email = $get(['ovb_contact_email','billing_email']);
        $contact_phone = $get(['ovb_contact_phone','billing_phone']);

        if ($contact_first === '') ovb_add_error($errors,'ovb_contact_first_name',__('Unesi ime kontakt osobe','ov-booking'));
        if ($contact_last  === '') ovb_add_error($errors,'ovb_contact_last_name', __('Unesi prezime kontakt osobe','ov-booking'));
        if (!is_email($contact_email)) ovb_add_error($errors,'ovb_contact_email', __('Unesi ispravan email kontakt osobe','ov-booking'));
        if ($contact_phone === '') ovb_add_error($errors,'ovb_contact_phone', __('Unesi telefon kontakt osobe','ov-booking'));

        /* 2) BROJ GOSTIJU / OPCIJE */
        $total_guests = (int) ($get('ovb_guests_total', 1) ?: 1);
        $has_multiple_guests = $total_guests > 1;

        // Dodaj sve moguće nazive checkbox-a radi kompatibilnosti
        $is_company = ovb_truthy( $get(['ovb_is_company','ovb_business_checkout','billing_is_company']) );
        $is_other   = ovb_truthy( $get(['ovb_is_other','ovb_guest_different','ovb_paid_by_other']) );

        // Ako imamo više gostiju ili su čekirane dodatne opcije – pooštrena pravila
        $should_validate_all = $has_multiple_guests || $is_company || $is_other;

        /* 3) DODATNA LIČNA POLJA (kad treba sve) */
        if ($should_validate_all) {
            $dob       = $get(['ovb_dob', 'billing_dob']);
            $id_number = $get(['ovb_id_number', 'billing_id_number']);

            if ($dob === '')       ovb_add_error($errors,'ovb_dob',       __('Unesi datum rođenja','ov-booking'));
            if ($id_number === '') ovb_add_error($errors,'ovb_id_number', __('Unesi ID broj/broj pasoša','ov-booking'));
        }

        /* 4) FIRMA – obavezno ako je čekirano */
        if ($is_company) {
            // [lista mogućih ključeva], Label
            $required = [
                [ ['ovb_company_name','billing_company'],                                 __('Naziv firme','ov-booking') ],
                [ ['ovb_company_country','billing_country'],                             __('Država','ov-booking') ],
                [ ['ovb_company_city','billing_city'],                                   __('Grad','ov-booking') ],
                [ ['ovb_company_address','ovb_company_address_1','billing_address_1'],   __('Adresa firme','ov-booking') ],
                [ ['ovb_company_postcode','ovb_company_zip','billing_postcode'],         __('Poštanski broj','ov-booking') ],
            ];
            foreach ($required as [$keys, $label]) {
                $val = $get($keys);
                if ($val === '') {
                    ovb_add_error($errors, $keys[0], sprintf(__('Popunite: %s','ov-booking'), $label));
                }
            }

            // opciono: PIB/VAT i telefon firme – validacija formata ako su uneti
            $vat = $get(['ovb_company_pib','ovb_company_vat']);
            if ($vat !== '' && !preg_match('/^[0-9]{6,12}$/', $vat)) {
                ovb_add_error($errors, 'ovb_company_vat', __('PIB/VAT mora biti 6–12 cifara.','ov-booking'));
            }
            $cphone = $get(['ovb_company_phone','billing_phone_company']);
            if ($cphone !== '' && !preg_match('/^\+?[0-9\s\-()]{6,}$/', $cphone)) {
                ovb_add_error($errors, 'ovb_company_phone', __('Telefon firme nije ispravan.','ov-booking'));
            }
        }

        /* 5) PLAĆA DRUGA OSOBA – obavezno ako je čekirano */
        if ($is_other) {
            $of = $get(['ovb_other_first_name','other_first_name']);
            $ol = $get(['ovb_other_last_name','other_last_name']);
            $oe = $get(['ovb_other_email','other_email']);
            $op = $get(['ovb_other_phone','other_phone']);
            $od = $get(['ovb_other_dob','other_dob']); // ako postoji u formi

            if ($of === '') ovb_add_error($errors,'ovb_other_first_name',__('Unesi ime druge osobe','ov-booking'));
            if ($ol === '') ovb_add_error($errors,'ovb_other_last_name', __('Unesi prezime druge osobe','ov-booking'));

            // Bar JEDNO: email ili telefon
            if ($oe === '' && $op === '') {
                ovb_add_error($errors,'ovb_other_contact', __('Unesi email ili telefon druge osobe','ov-booking'));
            }
            if ($oe !== '' && !is_email($oe)) {
                ovb_add_error($errors,'ovb_other_email', __('Email druge osobe nije ispravan','ov-booking'));
            }
            if ($od === '' && array_key_exists('ovb_other_dob',$posted)) {
                ovb_add_error($errors,'ovb_other_dob', __('Unesi datum rođenja druge osobe','ov-booking'));
            }
        }

        /* 6) DODATNI GOSTI – validiraj samo stvarno unete; prazne redove ignoriši */
        if ($has_multiple_guests) {
            $base  = 1 + ($is_other ? 1 : 0); // glavni + druga osoba
            $needN = max(0, $total_guests - $base);

            $raw = isset($posted['ovb_guest']) && is_array($posted['ovb_guest']) ? $posted['ovb_guest'] : [];

            $guests = [];
            foreach ($raw as $idx => $g) {
                $fn = isset($g['first_name']) ? trim((string)$g['first_name']) : '';
                $ln = isset($g['last_name'])  ? trim((string)$g['last_name'])  : '';
                $gd = isset($g['gender'])     ? trim((string)$g['gender'])     : '';
                $db = isset($g['dob'])        ? trim((string)$g['dob'])        : '';
                $ph = isset($g['phone'])      ? trim((string)$g['phone'])      : '';
                $pp = isset($g['passport'])   ? trim((string)$g['passport'])   : '';
                $all_empty = ($fn==='' && $ln==='' && $gd==='' && $db==='' && $ph==='' && $pp==='');
                if (!$all_empty) {
                    $guests[] = ['first_name'=>$fn,'last_name'=>$ln,'gender'=>$gd,'dob'=>$db];
                }
            }

            if ($needN > 0 && count($guests) < $needN) {
                ovb_add_error($errors, 'ovb_guests_count', sprintf(__('Dodajte još %d gost(a).','ov-booking'), $needN - count($guests)));
            }

            foreach ($guests as $i => $g) {
                if ($g['first_name']==='' || $g['last_name']==='' || $g['gender']==='' || $g['dob']==='') {
                    ovb_add_error($errors, 'ovb_guest_'.($i+1), sprintf(__('Popunite obavezna polja za gosta #%d.','ov-booking'), $i+1));
                }
            }
        }
    }
}

// Classic checkout
add_action('woocommerce_after_checkout_validation', function($data, $errors){
    $posted = $_POST ?? [];
    ovb_validate_checkout_payload( is_array($posted) ? $posted : [], $errors );
}, 20, 2);

// Blocks checkout (Store API)
// Blocks checkout (Store API) – agregiraj poruke u jedan WP_Error
add_filter('rest_request_before_callbacks', function( $response, $handler, $request ){
    try {
        if ( ! ($request instanceof WP_REST_Request) ) return $response;
        if ( strtoupper($request->get_method()) !== 'POST' ) return $response;

        $route = (string) $request->get_route();
        if ( strpos($route, '/wc/store') === false || strpos($route, 'checkout') === false ) return $response;

        $wp_error = new WP_Error();
        $params   = $request->get_params();
        ovb_validate_checkout_payload( is_array($params) ? $params : [], $wp_error );

        if ( $wp_error->has_errors() ) {
            $msgs = [];
            foreach ($wp_error->errors as $err_code => $err_msgs) {
                foreach ((array)$err_msgs as $m) {
                    $m = trim((string)$m);
                    if ($m !== '') $msgs[] = $m;
                }
            }
            $msgs = array_values(array_unique($msgs));
            $joined = implode("\n", $msgs);

            return new WP_Error(
                'ovb_validation_failed',
                $joined !== '' ? $joined : __('Nedostaju obavezna polja.','ov-booking'),
                [ 'status' => 400, 'errors' => $msgs ]
            );
        }
    } catch (Throwable $e) {}
    return $response;
}, 9, 3);

/**
 * =========================
 * ORDER META HANDLING
 * =========================
 */

/**
 * Saves booking data and guest information to order meta during checkout
 * Handles dates, guest count, payer data, and compatibility meta
 */
// =========================
// ORDER META (save booking + payer + other + guests)
// =========================
add_action('woocommerce_checkout_update_order_meta', function($order_id, $data = []) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    // --- Booking iz cart-a (ostavljeno isto)
    foreach (WC()->cart->get_cart() as $item) {
        if (!empty($item['ovb_all_dates'])) {
            $order->update_meta_data('all_dates', sanitize_text_field($item['ovb_all_dates']));
            if (!empty($item['start_date'])) $order->update_meta_data('start_date', sanitize_text_field($item['start_date']));
            if (!empty($item['end_date']))   $order->update_meta_data('end_date',   sanitize_text_field($item['end_date']));
            if (isset($item['guests']))      $order->update_meta_data('guests', intval($item['guests']));
            break;
        }
    }

    // --- Payer (billing) – ostavljeno isto
    foreach (['first_name','last_name','email','phone'] as $f) {
        $v = isset($_POST['billing_' . $f]) ? sanitize_text_field($_POST['billing_' . $f]) : '';
        if ($v) {
            $order->update_meta_data('booking_client_' . $f, $v);
            $order->update_meta_data($f, $v);
        }
    }

    // --- Compat
    $order->update_meta_data('_ovb_start_date', $order->get_meta('start_date'));
    $order->update_meta_data('_ovb_end_date',   $order->get_meta('end_date'));
    $order->update_meta_data('_ovb_guests_num', $order->get_meta('guests'));

    // --- Druga osoba (other person / different payer-guest)
    $is_other = ovb_truthy($_POST['ovb_is_other'] ?? $_POST['ovb_paid_by_other'] ?? $_POST['ovb_guest_different'] ?? '');
    $order->update_meta_data('_ovb_paid_by_other', $is_other ? 'yes' : 'no');
    $order->update_meta_data('_ovb_is_other',      $is_other ? '1'   : '0');

    // canonical meta ključevi
    $op = [
        'first'    => sanitize_text_field($_POST['ovb_other_first_name'] ?? $_POST['other_first_name'] ?? ''),
        'last'     => sanitize_text_field($_POST['ovb_other_last_name']  ?? $_POST['other_last_name']  ?? ''),
        'email'    => sanitize_email     ($_POST['ovb_other_email']      ?? $_POST['other_email']      ?? ''),
        'phone'    => sanitize_text_field($_POST['ovb_other_phone']      ?? $_POST['other_phone']      ?? ''),
        'dob'      => sanitize_text_field($_POST['ovb_other_dob']        ?? $_POST['other_dob']        ?? ''),
        'id'       => sanitize_text_field($_POST['ovb_other_id_number']  ?? $_POST['other_id_number']  ?? ''),
        'address1' => sanitize_text_field($_POST['ovb_other_address1']   ?? ''),
        'city'     => sanitize_text_field($_POST['ovb_other_city']       ?? ''),
        'postcode' => sanitize_text_field($_POST['ovb_other_postcode']   ?? ''),
        'country'  => sanitize_text_field($_POST['ovb_other_country']    ?? ''),
    ];
    foreach ($op as $k => $v) {
        if ($v !== '') $order->update_meta_data('_ovb_other_' . $k, $v);
    }

    // --- Gosti (serijalizovani JSON)
    // očekujemo $_POST['ovb_guest'][i][first_name|last_name|gender|dob|birthdate|phone|passport|id_number]
    $raw = isset($_POST['ovb_guest']) && is_array($_POST['ovb_guest']) ? $_POST['ovb_guest'] : [];
    $guests = [];
    foreach ($raw as $g) {
        $fn = trim((string)($g['first_name'] ?? ''));
        $ln = trim((string)($g['last_name']  ?? ''));
        $gd = trim((string)($g['gender']     ?? ''));
        $db = trim((string)($g['dob']        ?? ($g['birthdate'] ?? '')));
        $ph = trim((string)($g['phone']      ?? ''));
        $pp = trim((string)($g['passport']   ?? ($g['id_number'] ?? '')));

        // ignoriši potpuno prazne redove
        if ($fn==='' && $ln==='' && $gd==='' && $db==='' && $ph==='' && $pp==='') continue;

        $guests[] = [
            'first_name' => sanitize_text_field($fn),
            'last_name'  => sanitize_text_field($ln),
            'gender'     => sanitize_text_field($gd),
            'dob'        => sanitize_text_field($db),
            'phone'      => sanitize_text_field($ph),
            'passport'   => sanitize_text_field($pp),
        ];
    }

    // total (ako ima hidden input / ili padaj na već postojeće)
    $total = intval($_POST['ovb_guests_total'] ?? $_POST['guests'] ?? $order->get_meta('_ovb_guests_num') ?? 1);
    $order->update_meta_data('_ovb_guests_total', $total);

    // snimi JSON samo ako nešto stvarno ima
    if (!empty($guests)) {
        $order->update_meta_data('_ovb_guests_json', wp_json_encode($guests, JSON_UNESCAPED_UNICODE));
    } else {
        // ako ništa nema – obriši da se ne vuče stara vrednost
        $order->delete_meta_data('_ovb_guests_json');
    }

    $order->save();
}, 10, 2);

/**
 * =========================
 * ORDER STATUS MANAGEMENT
 * =========================
 */

/**
 * Custom class to handle order status restoration from trash
 * Ensures orders are restored to their previous status (completed)
 */
if (!class_exists('OVB_OrderStatusManager')) {
    class OVB_OrderStatusManager {
        private static $suppress_emails = false;

        /**
         * Initialize status management hooks
         */
        public static function init() {
            // Store previous status before trashing
            add_action('woocommerce_before_trash_order', [__CLASS__, 'store_previous_status'], 10, 2);
            
            // CPT: Force status on untrash
            add_filter('wp_untrash_post_status', [__CLASS__, 'force_completed_cpt'], 20, 3);
            
            // HPOS: Ensure completed status after untrash
            add_action('woocommerce_untrash_order', [__CLASS__, 'force_completed_hpos'], 5, 2);
        }

        /**
         * Stores the order status before trashing
         */
        public static function store_previous_status($order_id, $order_obj = null) {
            $order = ($order_obj instanceof WC_Order) ? $order_obj : wc_get_order($order_id);
            if (!$order) return;

            $prev_status = $order->get_status();
            $order->update_meta_data('_ovb_prev_status', $prev_status);
            $order->save();
        }

        /**
         * Forces completed status for CPT orders on untrash
         */
        public static function force_completed_cpt($new_status, $post_id, $previous_status) {
            if (get_post_type($post_id) !== 'shop_order') {
                return $new_status;
            }

            $order = wc_get_order($post_id);
            if (!$order) return $new_status;

            $stored_status = $order->get_meta('_ovb_prev_status');
            $target_status = $stored_status ?: ($previous_status ? str_replace('wc-', '', $previous_status) : '');

            if (!$target_status || $target_status === 'completed') {
                return 'wc-completed';
            }

            return (strpos($target_status, 'wc-') === 0) ? $target_status : 'wc-' . $target_status;
        }

        /**
         * Forces completed status for HPOS orders on untrash
         */
        public static function force_completed_hpos($order_id, $previous_status = '') {
            $order = wc_get_order($order_id);
            if (!$order) return;

            $stored_status = $order->get_meta('_ovb_prev_status');
            $target_status = $stored_status ?: $previous_status;

            if (!$target_status || $target_status === 'completed') {
                $target_status = 'completed';
            }

            // Normalize status (remove wc- prefix if present)
            if (strpos($target_status, 'wc-') === 0) {
                $target_status = substr($target_status, 3);
            }

            if ($order->get_status() !== $target_status) {
                // Suppress emails during restoration if setting to completed
                if ($target_status === 'completed') {
                    self::$suppress_emails = true;
                    add_filter('woocommerce_email_enabled_customer_completed_order', [__CLASS__, 'disable_completed_email'], 10, 2);
                }

                $order->set_status($target_status, __('OVB: restored from Trash', 'ov-booking'));
                $order->save();

                // Re-enable emails
                if ($target_status === 'completed') {
                    remove_filter('woocommerce_email_enabled_customer_completed_order', [__CLASS__, 'disable_completed_email'], 10);
                    self::$suppress_emails = false;
                }
            }
        }

        /**
         * Temporarily disables completed order emails during restoration
         */
        public static function disable_completed_email($enabled, $order) {
            return self::$suppress_emails ? false : $enabled;
        }
    }

    OVB_OrderStatusManager::init();
}

/**
 * =========================
 * CALENDAR SYNCHRONIZATION
 * =========================
 */

/**
 * Synchronizes booking data with product calendar when order is completed
 * Handles both item meta and order meta fallbacks for robust data retrieval
 */
add_action('woocommerce_order_status_completed', 'ovb_calendar_sync_on_completed', 10, 1);
add_action('woocommerce_order_status_changed', function($order_id, $from, $to, $order) {
    if ($to === 'completed') {
        ovb_calendar_sync_on_completed($order_id);
    }
}, 10, 4);

function ovb_calendar_sync_on_completed($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        if (!$product_id) continue;

        // Try to get dates from item meta first
        $dates_meta = $item->get_meta('ovb_all_dates') ?: $item->get_meta('_ovb_calendar_data') ?: $item->get_meta('booking_dates');
        $dates = [];
        
        if (is_string($dates_meta) && trim($dates_meta) !== '') {
            $dates = array_values(array_filter(array_map('trim', explode(',', $dates_meta))));
        }

        // Fallback: use order meta (start/end) if item meta doesn't exist
        if (empty($dates)) {
            $start = $order->get_meta('ovb_check_in_date') ?: $order->get_meta('_ovb_start_date') ?: $order->get_meta('start_date');
            $end   = $order->get_meta('ovb_check_out_date') ?: $order->get_meta('_ovb_end_date') ?: $order->get_meta('end_date');
            
            if ($start && $end) {
                $dates = ovb_generate_date_range_safe($start, $end);
            }
        }

        if (empty($dates)) continue;

        // Get guest data
        $guest_first = $order->get_meta('booking_client_first_name') ?: $order->get_meta('first_name') ?: $order->get_billing_first_name();
        $guest_last  = $order->get_meta('booking_client_last_name') ?: $order->get_meta('last_name') ?: $order->get_billing_last_name();
        $guest_email = $order->get_meta('booking_client_email') ?: $order->get_meta('email') ?: $order->get_billing_email();
        $guest_phone = $order->get_meta('booking_client_phone') ?: $order->get_meta('phone') ?: $order->get_billing_phone();

        $booking_id = $order_id . '_' . $item->get_id();

        // Load and normalize calendar data
        $calendar_data = ovb_get_calendar_data($product_id);

        // Update calendar for each date (idempotent - remove existing booking first, then add)
        $total_dates = count($dates);
        foreach ($dates as $i => $date) {
            if (!isset($calendar_data[$date]) || !is_array($calendar_data[$date])) {
                $calendar_data[$date] = ['status' => 'available', 'isPast' => false, 'clients' => []];
            }
            
            $clients = isset($calendar_data[$date]['clients']) && is_array($calendar_data[$date]['clients'])
                ? $calendar_data[$date]['clients'] : [];

            // Remove existing booking with same ID
            $clients = array_values(array_filter($clients, function($cl) use ($booking_id) {
                return !isset($cl['bookingId']) || $cl['bookingId'] !== $booking_id;
            }));

            // Add new booking
            $clients[] = [
                'bookingId'  => $booking_id,
                'firstName'  => sanitize_text_field($guest_first),
                'lastName'   => sanitize_text_field($guest_last),
                'email'      => sanitize_email($guest_email),
                'phone'      => sanitize_text_field($guest_phone),
                'guests'     => (int) ($order->get_meta('guests') ?: $order->get_meta('_ovb_guests_num') ?: 1),
                'rangeStart' => $dates[0],
                'rangeEnd'   => $dates[$total_dates - 1],
                'isCheckin'  => ($i === 0),
                'isCheckout' => ($i === $total_dates - 1),
                'order_id'   => $order_id,
            ];

            $calendar_data[$date]['clients'] = $clients;
            $calendar_data[$date]['status']  = ($i === $total_dates - 1) ? 'available' : 'booked';
        }

        update_post_meta($product_id, '_ovb_calendar_data', $calendar_data);
    }
}

/**
 * Releases calendar dates when order is cancelled, refunded, or deleted
 * Removes booking entries from product calendars
 */
add_action('woocommerce_order_status_cancelled', 'ovb_release_calendar_dates_on_cancel', 20);
add_action('woocommerce_order_status_refunded', 'ovb_release_calendar_dates_on_cancel', 20);
add_action('woocommerce_before_trash_order', 'ovb_release_calendar_dates_on_cancel', 20);
add_action('woocommerce_before_delete_order', 'ovb_release_calendar_dates_on_cancel', 20);
add_action('before_delete_post', 'ovb_handle_order_deletion_cleanup', 10, 1);

function ovb_release_calendar_dates_on_cancel($order) {
    $order = is_numeric($order) ? wc_get_order($order) : $order;
    if (!$order) return;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        if (!$product_id) continue;

        $booking_id = $order->get_id() . '_' . $item->get_id();

        // Get booking dates
        $dates = ovb_get_booking_dates_from_order($order, $item);
        if (empty($dates)) continue;

        // Load and clean calendar data
        $calendar_data = ovb_get_calendar_data($product_id);

        foreach ($dates as $date) {
            if (empty($calendar_data[$date]) || empty($calendar_data[$date]['clients'])) continue;
            
            $calendar_data[$date]['clients'] = array_values(array_filter(
                (array)$calendar_data[$date]['clients'],
                function($cl) use($booking_id) { 
                    return !isset($cl['bookingId']) || $cl['bookingId'] !== $booking_id; 
                }
            ));
            
            if (empty($calendar_data[$date]['clients'])) {
                $calendar_data[$date]['status'] = (!empty($calendar_data[$date]['price']) && $calendar_data[$date]['price'] > 0) ? 'available' : 'unavailable';
            }
        }

        update_post_meta($product_id, '_ovb_calendar_data', $calendar_data);
    }
}

/**
 * Handles cleanup when order post is permanently deleted
 */
function ovb_handle_order_deletion_cleanup($post_id) {
    if (get_post_type($post_id) !== 'shop_order') return;
    
    $order = wc_get_order($post_id);
    if ($order) {
        ovb_release_calendar_dates_on_cancel($order);
    }
}

/**
 * =========================
 * EMAIL NOTIFICATIONS
 * =========================
 */

/**
 * Sends iCal file to customer when order is completed
 * Creates temporary .ics file and emails it as attachment
 */
add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || !class_exists('OVB_iCal_Service')) return;

    foreach ($order->get_items() as $item) {
        if ($item->get_meta('ovb_all_dates') || $item->get_meta('_ovb_calendar_data')) {
            $ics_content = OVB_iCal_Service::generate_ics_string($order);
            $upload_dir = wp_upload_dir();
            $file_path = trailingslashit($upload_dir['basedir']) . "booking-{$order_id}.ics";
            
            if (file_put_contents($file_path, $ics_content) !== false) {
                wp_mail(
                    $order->get_billing_email(),
                    __('📅 Booking Calendar File', 'ov-booking'),
                    __('Thank you for your reservation. Attached is your calendar file (.ics) you can import.', 'ov-booking'),
                    ['Content-Type: text/html; charset=UTF-8'],
                    [$file_path]
                );

                register_shutdown_function(function() use ($file_path) {
                    if (file_exists($file_path)) unlink($file_path);
                });
            }
            break;
        }
    }
}, 10, 1);

/**
 * =========================
 * ADMIN UI ENHANCEMENTS
 * =========================
 */

/**
 * Adds booking-related columns to orders list in admin
 * Works with both HPOS and legacy order storage
 */
add_filter('manage_edit-shop_order_columns', 'ovb_add_booking_columns', 20);
add_filter('manage_hpos_shop_order_columns', 'ovb_add_booking_columns', 20);
add_filter('manage_woocommerce_page_wc-orders_columns', 'ovb_add_booking_columns', 20);

function ovb_add_booking_columns($columns) {
    $insert = [
        'ovb_check_in'  => __('Check In', 'ov-booking'),
        'ovb_check_out' => __('Check Out', 'ov-booking'),
        'ovb_guests'    => __('Guests', 'ov-booking'),
    ];

    return array_slice($columns, 0, 1, true) + $insert + $columns;
}

/**
 * Renders content for custom booking columns in orders list
 */
add_action('manage_shop_order_posts_custom_column', 'ovb_render_booking_columns', 10, 2);
add_action('manage_hpos_shop_order_custom_column', 'ovb_render_booking_columns', 10, 2);
add_action('manage_woocommerce_page_wc-orders_custom_column', 'ovb_render_booking_columns', 10, 2);

function ovb_render_booking_columns($column, $order_id_or_obj) {
    $order = $order_id_or_obj instanceof WC_Order ? $order_id_or_obj : wc_get_order($order_id_or_obj);
    if (!$order) return;

    $booking_meta = ovb_get_order_booking_meta($order);

    switch ($column) {
        case 'ovb_check_in':
            echo ovb_format_date($booking_meta['check_in']);
            break;
        case 'ovb_check_out':
            echo ovb_format_date($booking_meta['check_out']);
            break;
        case 'ovb_guests':
            echo (null !== $booking_meta['guests'] ? (int) $booking_meta['guests'] : '—');
            break;
    }
}

//test
// ==== OVB: Company & Other person — wide section ispod zaglavlja porudžbine ====

if ( ! function_exists('ovb_admin_company_other_below') ) {
    function ovb_admin_company_other_below( $order ) {
        static $done = false; if ($done) return; $done = true; // iscrtati jednom
        if ( ! ($order instanceof WC_Order) ) return;

        // --- Company (isti izvori kao gore)
        $co = [
            'name'     => $order->get_meta('_ovb_company_name')     ?: $order->get_billing_company(),
            'vat'      => $order->get_meta('_ovb_company_pib')      ?: $order->get_meta('_ovb_company_vat'),
            'mb'       => $order->get_meta('_ovb_company_mb'),
            'contact'  => $order->get_meta('_ovb_company_contact'),
            'phone'    => $order->get_meta('_ovb_company_phone'),
            'address'  => $order->get_meta('_ovb_company_address')  ?: $order->get_billing_address_1(),
            'city'     => $order->get_meta('_ovb_company_city')     ?: $order->get_billing_city(),
            'postcode' => $order->get_meta('_ovb_company_postcode') ?: $order->get_billing_postcode(),
            'country'  => $order->get_meta('_ovb_company_country')  ?: $order->get_billing_country(),
        ];
        $is_company = ( $order->get_meta('_ovb_is_company') === '1' ) || !empty($co['name']);

        // --- Other person
        $ot = [
            'first'   => $order->get_meta('_ovb_other_first_name'),
            'last'    => $order->get_meta('_ovb_other_last_name'),
            'email'   => $order->get_meta('_ovb_other_email'),
            'phone'   => $order->get_meta('_ovb_other_phone'),
            'dob'     => $order->get_meta('_ovb_other_dob'),
            'id'      => $order->get_meta('_ovb_other_id_number'),
            'address' => $order->get_meta('_ovb_other_address1'),
            'city'    => $order->get_meta('_ovb_other_city'),
            'postcode'=> $order->get_meta('_ovb_other_postcode'),
            'country' => $order->get_meta('_ovb_other_country'),
        ];
        $is_other = ( $order->get_meta('_ovb_is_other') === '1' ) || ( $order->get_meta('_ovb_paid_by_other') === 'yes' )
                    || $ot['first'] || $ot['last'] || $ot['email'] || $ot['phone'];

        // Ako nema ništa za prikaz, izađi
        if ( ! $is_company && ! $is_other ) return;

        // --- UI (wide, kao tvoje postojeće box-eve)
        $wrap = 'margin:10px 0; padding:14px 16px; background:#f5f5fa; border:1px solid #dcdcde; border-radius:6px;';
        $h    = 'margin:0 0 8px; font-size:13px; font-weight:600;';
        $grid = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;';
        $row  = 'display:flex;gap:6px;align-items:baseline;margin:2px 0;';
        $lab  = 'min-width:140px;color:#111827;';
        $tag  = 'display:inline-block;margin-left:8px;padding:1px 6px;border:1px solid #e0e7ff;background:#eef2ff;border-radius:4px;font-size:11px;color:#3730a3;';

        echo '<div class="ovb-admin-wide" style="'.$wrap.'">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">';
        echo '<h3 style="'.$h.'">'.esc_html__('Additional party details','ov-booking').'</h3>';
        $badges = [];
        if ($is_company) $badges[] = __('Company invoice','ov-booking');
        if ($is_other)   $badges[] = __('Different payer/guest','ov-booking');
        if ($badges) {
            echo '<div>';
            foreach ($badges as $b) echo '<span style="'.$tag.'">'.esc_html($b).'</span>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div style="'.$grid.'">';

        // --- Company card
        if ( $is_company ) {
            echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:10px 12px;">';
            echo '<div style="font-weight:600;margin-bottom:6px;">'.esc_html__('Company billing','ov-booking').'</div>';
            echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Company','ov-booking').'</span><span>'.esc_html((string)$co['name']).'</span></div>';
            if (!empty($co['vat']))      echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('VAT/PIB','ov-booking').'</span><span>'.esc_html((string)$co['vat']).'</span></div>';
            if (!empty($co['mb']))       echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Registration no.','ov-booking').'</span><span>'.esc_html((string)$co['mb']).'</span></div>';
            if (!empty($co['contact']))  echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Contact','ov-booking').'</span><span>'.esc_html((string)$co['contact']).'</span></div>';
            if (!empty($co['phone'])) {
                $tel = preg_replace('/[^0-9+]/','',(string)$co['phone']);
                echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Phone','ov-booking').'</span><a href="tel:'.esc_attr($tel).'">'.esc_html((string)$co['phone']).'</a></div>';
            }
            if (!empty($co['address']))  echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Address','ov-booking').'</span><span>'.esc_html((string)$co['address']).'</span></div>';
            if (!empty($co['city']))     echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('City','ov-booking').'</span><span>'.esc_html((string)$co['city']).'</span></div>';
            if (!empty($co['postcode'])) echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Postcode','ov-booking').'</span><span>'.esc_html((string)$co['postcode']).'</span></div>';
            if (!empty($co['country']))  echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Country','ov-booking').'</span><span>'.esc_html((string)$co['country']).'</span></div>';
            echo '</div>';
        }

        // --- Other person card
        if ( $is_other ) {
            $name = trim((string)$ot['first'].' '.(string)$ot['last']);
            echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:10px 12px;">';
            echo '<div style="font-weight:600;margin-bottom:6px;">'.esc_html__('Other person','ov-booking').'</div>';
            if ($name)                   echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Name','ov-booking').'</span><span>'.esc_html($name).'</span></div>';
            if (!empty($ot['email']))    echo '<div style="'.$row.'"><span style="'.$lab.'">Email</span><a href="mailto:'.esc_attr(sanitize_email((string)$ot['email'])).'">'.esc_html((string)$ot['email']).'</a></div>';
            if (!empty($ot['phone'])) {
                $tel = preg_replace('/[^0-9+]/','',(string)$ot['phone']);
                echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Phone','ov-booking').'</span><a href="tel:'.esc_attr($tel).'">'.esc_html((string)$ot['phone']).'</a></div>';
            }
            if (!empty($ot['dob']))      echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Birth date','ov-booking').'</span><span>'.esc_html((string)$ot['dob']).'</span></div>';
            if (!empty($ot['id']))       echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('ID/Passport','ov-booking').'</span><span>'.esc_html((string)$ot['id']).'</span></div>';
            if (!empty($ot['address']))  echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Address','ov-booking').'</span><span>'.esc_html((string)$ot['address']).'</span></div>';
            if (!empty($ot['city']))     echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('City','ov-booking').'</span><span>'.esc_html((string)$ot['city']).'</span></div>';
            if (!empty($ot['postcode'])) echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Postcode','ov-booking').'</span><span>'.esc_html((string)$ot['postcode']).'</span></div>';
            if (!empty($ot['country']))  echo '<div style="'.$row.'"><span style="'.$lab.'">'.esc_html__('Country','ov-booking').'</span><span>'.esc_html((string)$ot['country']).'</span></div>';
            echo '</div>';
        }
        echo '</div>'; // grid
        echo '</div>'; // wrap
    }
}

// ✔ Pozicioniraj ispod “Order details” / iznad stavki (isto mesto kao tvoj postojeći boks)
// add_action('woocommerce_admin_order_item_headers', 'ovb_admin_company_other_below', 20);  -odve 
add_action('woocommerce_admin_order_item_headers', function($order){
    if (!$order instanceof WC_Order) return;
    ovb_display_payer_info($order);
    ovb_display_guest_info($order);
}, 8);

//test

/**
 * Reorders admin order columns for better layout
 * Ensures checkbox first, then custom columns in desired order
 */
add_filter('manage_woocommerce_page_wc-orders_columns', 'ovb_reorder_admin_order_columns', 99);
add_filter('manage_edit-shop_order_columns', 'ovb_reorder_admin_order_columns', 99);

function ovb_reorder_admin_order_columns($columns) {
    $ordered = [];
    $used = [];

    // Checkbox first
    if (isset($columns['cb'])) {
        $ordered['cb'] = $columns['cb'];
        $used['cb'] = true;
    }

    // Desired order
    $desired = [
        ['order', 'order_number', 'wc_order_number', 'order_title'],
        ['ovb_guests'],
        ['ovb_check_in'],
        ['ovb_check_out'],
        ['date', 'order_date'],
        ['status', 'order_status'],
        ['total', 'order_total'],
        ['origin', 'order_source', 'source', 'wc_order_source'],
    ];

    foreach ($desired as $choices) {
        foreach ($choices as $key) {
            if (isset($columns[$key]) && !isset($used[$key])) {
                $ordered[$key] = $columns[$key];
                $used[$key] = true;
                break;
            }
        }
    }

    // Add remaining columns (except actions)
    $actions_key = null;
    foreach ($columns as $key => $label) {
        if (isset($used[$key])) continue;

        if (in_array($key, ['wc_actions', 'order_actions'], true)) {
            $actions_key = $key;
            continue;
        }
        $ordered[$key] = $label;
    }

    // Actions last
    if ($actions_key && isset($columns[$actions_key])) {
        $ordered[$actions_key] = $columns[$actions_key];
    }

    return $ordered;
}

/**
 * Hides raw item meta keys from admin display
 */
add_filter('woocommerce_hidden_order_itemmeta', function($hidden) {
    return array_merge($hidden, [
        'rangeStart',
        'rangeEnd', 
        'guests',
        'booking_dates',
        'booking_id',
    ]);
}, 10, 1);

/**
 * Displays booking information badge below order items
 */
add_action('woocommerce_after_order_itemmeta', 'ovb_show_order_item_booking_badge', 10, 3);

function ovb_show_order_item_booking_badge($item_id, $item, $product) {
    $order = $item->get_order();
    if (!$order) return;

    $booking_meta = ovb_get_order_booking_meta($order);
    if (empty($booking_meta['check_in']) || empty($booking_meta['check_out'])) return;

    $nights = max(0, (int) floor((strtotime($booking_meta['check_out']) - strtotime($booking_meta['check_in'])) / DAY_IN_SECONDS));

    echo '<div class="ovb-badge" style="margin:8px 0 0; padding:8px 10px; background:#f5f5fa; border:1px solid #e5e7eb; border-radius:6px; font-size:12px; line-height:1.4;">'
        . '<strong>' . esc_html__('Booking', 'ov-booking') . ':</strong> '
        . esc_html(date_i18n(get_option('date_format'), strtotime($booking_meta['check_in']))) . ' → '
        . esc_html(date_i18n(get_option('date_format'), strtotime($booking_meta['check_out'])))
        . ($nights ? ' · ' . sprintf(_n('%d night', '%d nights', $nights, 'ov-booking'), $nights) : '')
        . (null !== $booking_meta['guests'] ? ' · ' . sprintf(_n('%d guest', '%d guests', (int)$booking_meta['guests'], 'ov-booking'), (int)$booking_meta['guests']) : '')
        . '</div>';
}

/**
 * =========================
 * UTILITY FUNCTIONS
 * =========================
 */

/**
 * Safely generates date range array
 */
function ovb_generate_date_range_safe($start, $end) {
    $dates = [];
    $start_time = strtotime($start);
    $end_time = strtotime($end);
    
    if ($start_time && $end_time && $start_time <= $end_time) {
        $current = $start_time;
        while ($current <= $end_time) {
            $dates[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }
    }
    
    return $dates;
}

/**
 * Gets booking dates from order and item
 */
function ovb_get_booking_dates_from_order($order, $item) {
    // Try item meta first
    $dates_meta = $item->get_meta('ovb_all_dates') ?: $item->get_meta('_ovb_calendar_data') ?: $item->get_meta('booking_dates');
    
    if (is_string($dates_meta) && trim($dates_meta) !== '') {
        return array_values(array_filter(array_map('trim', explode(',', $dates_meta))));
    }
    
    // Fallback to order meta
    $start = $order->get_meta('ovb_check_in_date') ?: $order->get_meta('_ovb_start_date') ?: $order->get_meta('start_date');
    $end   = $order->get_meta('ovb_check_out_date') ?: $order->get_meta('_ovb_end_date') ?: $order->get_meta('end_date');
    
    if ($start && $end) {
        return ovb_generate_date_range_safe($start, $end);
    }
    
    return [];
}


/**
 * Formats date for display (returns dash if empty)
 */
function ovb_format_date($date) {
    if (empty($date)) return '—';
    
    $timestamp = strtotime($date);
    return $timestamp ? date_i18n(get_option('date_format'), $timestamp) : '—';
}

/**
 * Displays payer information in order details
 */
function ovb_display_payer_info($order) {
    $first = trim((string) ($order->get_meta('booking_client_first_name') ?: $order->get_billing_first_name()));
    $last = trim((string) ($order->get_meta('booking_client_last_name') ?: $order->get_billing_last_name()));
    $email = trim((string) ($order->get_meta('booking_client_email') ?: $order->get_billing_email()));
    $phone = trim((string) ($order->get_meta('booking_client_phone') ?: $order->get_billing_phone()));
    $addr1 = trim((string) $order->get_billing_address_1());
    $addr2 = trim((string) $order->get_billing_address_2());
    $city = trim((string) $order->get_billing_city());
    $postcode = trim((string) $order->get_billing_postcode());
    $country_code = trim((string) $order->get_billing_country());

    $country_name = $country_code;
    if (function_exists('WC') && isset(WC()->countries)) {
        $countries = WC()->countries->get_countries();
        if (isset($countries[$country_code])) {
            $country_name = $countries[$country_code];
        }
    }

    $address = trim($addr1 . ($addr2 ? ' ' . $addr2 : ''));
    $li_style = 'display:flex;align-items:center;gap:6px;margin:2px 0;';

    echo '<div class="ovb-order-customer" style="margin:0 0 10px 0; padding:16px; background:#f5f5fa; border:1px solid #dcdcde; border-radius:6px;">';
    echo '<h3 style="margin:0 0 8px; font-size:13px;">' . esc_html__('Podaci o platiocu', 'ov-booking') . '</h3>';
    echo '<ul style="margin:0; padding-left:5px; list-style:none;">';
    
    if ($first) echo '<li style="' . esc_attr($li_style) . '"><strong>' . esc_html__('Ime', 'ov-booking') . ':</strong> ' . esc_html($first) . '</li>';
    if ($last) echo '<li style="' . esc_attr($li_style) . '"><strong>' . esc_html__('Prezime', 'ov-booking') . ':</strong> ' . esc_html($last) . '</li>';
    if ($email) echo '<li style="' . esc_attr($li_style) . '"><strong>' . esc_html__('Email', 'ov-booking') . ':</strong> <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></li>';
    if ($phone) {
        $tel = preg_replace('/[^0-9+]/', '', $phone);
        echo '<li style="' . esc_attr($li_style) . '"><strong>' . esc_html__('Telefon', 'ov-booking') . ':</strong> <a href="tel:' . esc_attr($tel) . '">' . esc_html($phone) . '</a></li>';
    }
    if ($address) echo '<li style="' . esc_attr($li_style) . '"><strong>' . esc_html__('Adresa', 'ov-booking') . ':</strong> ' . esc_html($address) . '</li>';
    if ($city) echo '<li style="' . esc_attr($li_style) . '"><strong>' . esc_html__('Grad', 'ov-booking') . ':</strong> ' . esc_html($city) . '</li>';
    if ($postcode) echo '<li style="' . esc_attr($li_style) . '"><strong>' . esc_html__('Poštanski broj', 'ov-booking') . ':</strong> ' . esc_html($postcode) . '</li>';
    if ($country_code) echo '<li style="' . esc_attr($li_style) . '"><strong>' . esc_html__('Država', 'ov-booking') . ':</strong> ' . esc_html($country_name) . '</li>';
    
    echo '</ul></div>';
}

function ovb_display_guest_info($order) {
    if (!$order instanceof WC_Order) return;

    $total   = (int) ($order->get_meta('_ovb_guests_total') ?: $order->get_meta('guests') ?: $order->get_meta('_ovb_guests_num') ?: 1);
    $is_other = ($order->get_meta('_ovb_paid_by_other') === 'yes') || ovb_truthy($order->get_meta('_ovb_is_other'));

    // decode JSON sa dodatnim gostima
    $extra = [];
    $json  = $order->get_meta('_ovb_guests_json');
    if (is_string($json) && $json !== '') {
        $tmp = json_decode($json, true);
        if (is_array($tmp)) {
            foreach ($tmp as $g) {
                $extra[] = [
                    'fn' => trim((string)($g['first_name'] ?? '')),
                    'ln' => trim((string)($g['last_name']  ?? '')),
                    'gd' => trim((string)($g['gender']     ?? '')),
                    'db' => trim((string)($g['dob']        ?? '')),
                    'ph' => trim((string)($g['phone']      ?? '')),
                    'pp' => trim((string)($g['passport']   ?? '')),
                ];
            }
        }
    }

    // Ako nema ništa da se prikaže — izađi
    if (!$is_other && empty($extra)) return;

    echo '<div class="ovb-guests" style="display:flex; flex-wrap:wrap; gap:16px; margin:12px 0;">';

    $open = function($title, $badge = '') {
        echo '<div style="flex:1 1 300px; background:#fff; border:1px solid #e5e7eb; border-radius:6px; padding:12px 14px;">';
        echo '<strong style="display:block; margin-bottom:8px;">'.$title.'</strong>';
        if ($badge) echo '<span style="font-size:12px; color:#7c3aed; display:block; margin:-4px 0 6px;">('.$badge.')</span>';
        echo '<ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:4px;">';
    };
    $close = function(){ echo '</ul></div>'; };

    // Guest #1 = Other person (ako je različit od platioca)
    if ($is_other) {
        $first = trim((string)$order->get_meta('_ovb_other_first_name'));
        $last  = trim((string)$order->get_meta('_ovb_other_last_name'));
        $name  = trim($first.' '.$last);

        $open(esc_html__('Gost #1','ov-booking').' <em style="font-weight:500;color:#6b7280;">Other person</em>', esc_html__('Različit od platioca','ov-booking'));
        if ($name) {
            echo '<li><strong>'.esc_html__('Ime i prezime','ov-booking').':</strong> '.esc_html($name).'</li>';
        }
        $email = trim((string)$order->get_meta('_ovb_other_email'));
        if ($email) {
            echo '<li><strong>Email:</strong> <a href="mailto:'.esc_attr(sanitize_email($email)).'">'.esc_html($email).'</a></li>';
        }
        $phone = trim((string)$order->get_meta('_ovb_other_phone'));
        if ($phone) {
            $tel = preg_replace('/[^0-9+]/','',$phone);
            echo '<li><strong>'.esc_html__('Telefon','ov-booking').':</strong> <a href="tel:'.esc_attr($tel).'">'.esc_html($phone).'</a></li>';
        }
        $dob = trim((string)$order->get_meta('_ovb_other_dob'));
        if ($dob) echo '<li><strong>'.esc_html__('Datum rođenja','ov-booking').':</strong> '.esc_html($dob).'</li>';
        $idn = trim((string)$order->get_meta('_ovb_other_id_number'));
        if ($idn) echo '<li><strong>'.esc_html__('Broj pasoša/lične karte','ov-booking').':</strong> '.esc_html($idn).'</li>';
        $close();
    }

    // Dodatni gosti
    if (!empty($extra)) {
        foreach ($extra as $i => $g) {
            $idx  = $i + 1 + ($is_other ? 1 : 0);
            $open( sprintf(esc_html__('Gost #%d','ov-booking'), $idx) );
            $full = trim($g['fn'].' '.$g['ln']);
            if ($full)        echo '<li><strong>'.esc_html__('Ime i prezime','ov-booking').':</strong> '.esc_html($full).'</li>';
            if ($g['gd']!=='')echo '<li><strong>'.esc_html__('Pol','ov-booking').':</strong> '.esc_html($g['gd']).'</li>';
            if ($g['db']!=='')echo '<li><strong>'.esc_html__('Datum rođenja','ov-booking').':</strong> '.esc_html($g['db']).'</li>';
            if ($g['ph']!=='')echo '<li><strong>'.esc_html__('Telefon','ov-booking').':</strong> '.esc_html($g['ph']).'</li>';
            if ($g['pp']!=='')echo '<li><strong>'.esc_html__('Broj pasoša/lične karte','ov-booking').':</strong> '.esc_html($g['pp']).'</li>';
            $close();
        }
    }

    echo '</div>';
}





// Istestiaraj 

// === OVB: Order action - Resend .ICS to customer ===
add_filter('woocommerce_order_actions', function($actions){
    $actions['ovb_send_ics_again'] = __('Send booking .ics to customer (OV Booking)', 'ov-booking');
    return $actions;
}, 10, 1);

add_action('woocommerce_order_action_ovb_send_ics_again', function($order){
    if ( ! $order instanceof WC_Order ) return;
    if ( ! class_exists('OVB_iCal_Service') ) {
        $order->add_order_note(__('OVB: iCal service not available; .ics not sent.', 'ov-booking'), false, true);
        return;
    }

    // isti mehanizam kao na "completed", samo ručno
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('ovb_all_dates') || $item->get_meta('_ovb_calendar_data')) {
            $ics_content = OVB_iCal_Service::generate_ics_string($order);
            $upload_dir  = wp_upload_dir();
            $file_path   = trailingslashit($upload_dir['basedir']) . "booking-{$order->get_id()}.ics";

            if ( file_put_contents($file_path, $ics_content) !== false ) {
                wp_mail(
                    $order->get_billing_email(),
                    __('📅 Booking Calendar File', 'ov-booking'),
                    __('We’re sending your calendar file (.ics) again. Thank you!', 'ov-booking'),
                    ['Content-Type: text/html; charset=UTF-8'],
                    [$file_path]
                );

                // očisti temp
                register_shutdown_function(function() use ($file_path) {
                    if (file_exists($file_path)) @unlink($file_path);
                });

                $order->add_order_note(__('OVB: .ics file re-sent to customer.', 'ov-booking'), false, true);
            } else {
                $order->add_order_note(__('OVB: failed to create .ics file; not sent.', 'ov-booking'), false, true);
            }
            break; // samo prvi item sa ovb meta
        }
    }
}, 10, 1);


// 3.1 Omogući COD i kad je korpa virtual-only (često za booking)
add_filter('woocommerce_cod_is_available', function ($available) {
    if (is_admin() && !defined('DOING_AJAX')) return $available;
    if (!WC()->cart) return $available;

    $needs_shipping = WC()->cart->needs_shipping();
    if (!$needs_shipping) return true; // sve virtuelno → dozvoli COD

    return $available;
}, 10, 1);

// 3.2 Ako su svi gateway-i filtrirani/ispali u toku update-a, vrati barem COD
add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    if (is_admin() && !defined('DOING_AJAX')) return $gateways;

    if (empty($gateways)) {
        $pm = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
        if (isset($pm['cod']) && $pm['cod']->is_available()) {
            $gateways['cod'] = $pm['cod'];
        }
    }
    return $gateways;
}, 20);


// 3.3 Default gateway (fallback) = COD, ako nijedan nije izabran
add_filter('woocommerce_default_gateway', function ($default) {
    return $default ?: 'cod';
});

add_filter('woocommerce_checkout_posted_data', function ($data) {
    if (is_admin() && !defined('DOING_AJAX')) return $data;

    $gateways = WC()->payment_gateways()->get_available_payment_gateways();
    if (empty($gateways) || !is_array($gateways)) return $data;

    $chosen = isset($data['payment_method']) ? sanitize_text_field($data['payment_method']) : '';
    if ($chosen === '' || !isset($gateways[$chosen])) {
        $fallback = isset($gateways['cod']) ? 'cod' : array_key_first($gateways);
        if ($fallback) {
            $data['payment_method'] = $fallback;
            if (WC()->session) {
                WC()->session->set('chosen_payment_method', $fallback);
            }
        }
    }
    return $data;
}, 9);

// 2.5 (Opc.) Activation minimal — bez diranja tuđih sesija
if (defined('OVB_BOOKING_FILE')) {
    register_activation_hook(OVB_BOOKING_FILE, function () {
        flush_rewrite_rules();
    });
}

//test nonce 
// === AJAX: Empty cart (za ovb_empty_cart) ===
add_action('wp_ajax_ovb_empty_cart', 'ovb_ajax_empty_cart');
add_action('wp_ajax_nopriv_ovb_empty_cart', 'ovb_ajax_empty_cart');
function ovb_ajax_empty_cart() {
    if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'ovb_nonce' ) ) {
        wp_send_json_error( __('Invalid nonce.', 'ov-booking') );
    }
    if ( ! function_exists('WC') || ! WC()->cart ) {
        wp_send_json_error( __('Cart unavailable.', 'ov-booking') );
    }

    // Isprazni korpu
    WC()->cart->empty_cart();

    // Po želji: očisti kupon(e), shipping, fees (ostavljeno osnovno)
    wp_send_json_success( ['message' => __('Cart emptied.', 'ov-booking')] );
}