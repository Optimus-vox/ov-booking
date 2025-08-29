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
// add_action('woocommerce_admin_order_item_headers', function($order){
//     if (!$order instanceof WC_Order) return;
//     ovb_display_payer_info($order);
//     ovb_display_guest_info($order);
// }, 8);

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
 * Gets calendar data for a product (handles both array and JSON formats)
 */
function ovb_get_calendar_data($product_id) {
    $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
    
    if (is_string($calendar_data)) {
        $decoded = json_decode($calendar_data, true);
        $calendar_data = is_array($decoded) ? $decoded : [];
    }
    
    if (!is_array($calendar_data)) {
        $calendar_data = [];
    }
    
    return $calendar_data;
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
 * Gets booking metadata from order in a standardized format
 */
function ovb_get_order_booking_meta($order) {
    $check_in = $order->get_meta('ovb_check_in_date') ?: $order->get_meta('_ovb_start_date') ?: $order->get_meta('start_date');
    $check_out = $order->get_meta('ovb_check_out_date') ?: $order->get_meta('_ovb_end_date') ?: $order->get_meta('end_date');
    $guests = $order->get_meta('guests') ?: $order->get_meta('_ovb_guests_num');
    
    return [
        'check_in' => $check_in,
        'check_out' => $check_out,
        'guests' => $guests
    ];
}

// === Date helper (koristi WP format) ===
if ( ! function_exists('ovb_frmt_date') ) {
    function ovb_frmt_date($raw, $format = null) {
        $format = $format ?: get_option('date_format');

        // WC / DateTime objekti
        if ($raw instanceof WC_DateTime) {
            return wc_format_datetime($raw, $format);
        }
        if ($raw instanceof DateTimeInterface) {
            return date_i18n($format, $raw->getTimestamp());
        }

        // Normalizuj string
        $raw = is_string($raw) ? trim($raw) : $raw;
        if ($raw === '' || $raw === null) return '';

        // Timestamp (sekunde ili milisekunde)
        if (is_numeric($raw)) {
            $ts = (int) $raw;
            if ($ts > 2000000000) { // verovatno ms
                $ts = (int) floor($ts / 1000);
            }
            return date_i18n($format, $ts);
        }

        // Najčešći formati koji stižu iz meta polja
        $formats = [
            'Y-m-d\TH:i:sP','Y-m-d\TH:i:s','Y-m-d H:i:s','Y-m-d',
            'd.m.Y','d/m/Y','m/d/Y','d-m-Y','d.m.Y H:i','d.m.Y H:i:s',
        ];
        foreach ($formats as $f) {
            $dt = DateTime::createFromFormat($f, $raw);
            if ($dt && $dt->getLastErrors()['error_count'] === 0 && $dt->getLastErrors()['warning_count'] === 0) {
                return date_i18n($format, $dt->getTimestamp());
            }
        }

        // Fallback: strtotime
        $ts = strtotime($raw);
        return $ts ? date_i18n($format, $ts) : $raw;
    }
}
if ( ! function_exists('ovb_frmt_date') && function_exists('ovb_fmt_date') ) {
    function ovb_frmt_date($raw, $format = null) { return ovb_fmt_date($raw, $format); }
}
// hide edit order default item table in bottom of screen
/**
 * Admin (edit order): sakrij tabelu stavki i totals; ostavi samo Refund.
 * Radi i na legacy 'shop_order' i na HPOS 'wc-orders' ekranu.
 */
add_action('current_screen', function($screen){
    if (empty($screen) || !isset($screen->id)) return;

    $id = (string) $screen->id;
    $is_legacy = ($id === 'shop_order');
    $is_hpos   = (strpos($id, 'wc-orders') !== false); // pokriva listu i edit

    if (!($is_legacy || $is_hpos)) return;

    // Umetni CSS samo na tom ekranu
    add_action('admin_print_styles', function () {
        echo '<style id="ovb-hide-order-items">
        /* POSTOJEĆI KOD - Sakrij header i sve TBODY-je sa stavkama (items/shipping/fees/coupons) */
        #woocommerce-order-items .woocommerce_order_items_wrapper table.woocommerce_order_items thead,
        #woocommerce-order-items .woocommerce_order_items_wrapper tbody#order_line_items,
        #woocommerce-order-items .woocommerce_order_items_wrapper tbody#order_shipping_line_items,
        #woocommerce-order-items .woocommerce_order_items_wrapper tbody#order_fee_line_items,
        #woocommerce-order-items .woocommerce_order_items_wrapper tbody#order_coupon_line_items {
            display: none !important;
        }

        /* Sakrij totals i kupon pregled (desno/dole) */
        #woocommerce-order-items .wc-order-totals-items,
        #woocommerce-order-items .wc-order-totals,
        #woocommerce-order-items .wc-used-coupons {
            display: none !important;
        }

        /* Sakrij sva dugmad u akcijama osim Refund */
        #woocommerce-order-items .wc-order-data-row .button:not(.refund-items) {
            display: none !important;
        }
        #woocommerce-order-items .wc-order-data-row .refund-items {
            display: inline-flex !important;
        }

        /* Malo zategni wrapper kad nema tabele */
        #woocommerce-order-items .woocommerce_order_items_wrapper {
            border: 0 !important; padding: 0 !important; margin: 0 !important;
        }

        /* Obezbedi da se refund panel normalno vidi kada se otvori */
        #woocommerce-order-items .wc-order-refund-items {
            display: block !important;
        }

        /* NOVI KOD - Sakrij billing i shipping polja */
        #order_data .order_data_column:nth-child(2),  /* Billing polja */
        #order_data .order_data_column:nth-child(3) { /* Shipping polja */
            display: none !important;
        }

        /* Centriranje i širina general dela */
        #order_data .order_data_column:first-child {  /* General polja */
            width: 640px !important;
            max-width: 640px !important;
            // margin: 0 auto !important;
            float: none !important;
        }

        /* Centriranje celog order_data kontejnera */
        #order_data {
            display: flex !important;
            flex-direction:column !important;
            justify-content: center !important;
            flex-wrap: wrap !important;
        }

        /* Osiguraj da se general kolona pravilno pozicionira */
        #order_data .order_data_column {
            box-sizing: border-box !important;
        }
        </style>';
    });
});

// hide edit order default item table in bottom of screen
// === Nightly prices helper (vrati niz cena po noći, ili prazan niz) ===
if ( ! function_exists('ovb_order_nightly_prices') ) {
    function ovb_order_nightly_prices(WC_Order $order): array {

        // 0) Izračunaj datume boravka (bez checkout dana)
        $bm        = ovb_get_order_booking_meta($order);
        $check_in  = $bm['check_in'];
        $check_out = $bm['check_out'];
        $stayDates = [];
        if ($check_in && $check_out) {
            $s = strtotime($check_in);
            $e = strtotime($check_out);
            if ($s && $e && $s < $e) {
                $t = $s;
                while ($t < $e) { // ekskluzivno checkout
                    $stayDates[] = date('Y-m-d', $t);
                    $t = strtotime('+1 day', $t);
                }
            }
        }

        // 1) pokušaj order/item meta raspada
        $keys = [
            '_ovb_nightly_prices','_ovb_daily_prices','_ovb_daily_rates_json',
            '_ovb_prices_json','_ovb_price_breakdown','_ovb_prices_per_night',
            '_ovb_nightly_rates',
        ];

        $extract_simple_list = function ($val): array {
            $out = [];
            if (is_array($val)) {
                foreach ($val as $v) {
                    if (is_numeric($v)) {
                        $out[] = (float)$v;
                    } elseif (is_string($v) && preg_match('/^\d+(?:[.,]\d+)?$/', trim($v))) {
                        $out[] = (float) str_replace(',', '.', trim($v));
                    }
                }
            } elseif (is_string($val) && ($val[0] ?? '') === '[') {
                $json = json_decode($val, true);
                if (is_array($json)) return $extract_simple_list($json);
            }
            return $out;
        };

        $arr = [];
        foreach ($keys as $k) {
            $m = $order->get_meta($k);
            if ($m) { $arr = $extract_simple_list($m); if ($arr) break; }
        }
        if (!$arr) {
            foreach ($order->get_items() as $item) {
                foreach ($keys as $k) {
                    $m = $item->get_meta($k, true);
                    if ($m) { $arr = $extract_simple_list($m); if ($arr) break 2; }
                }
            }
        }
        if ($arr) {
            return array_values(array_filter(array_map('floatval',$arr), fn($x)=>is_finite($x) && $x>=0));
        }

        // 2) fallback: pročitaj cene iz kalendara proizvoda za datume boravka
        if ($stayDates) {
            // U praksi imaš jedan booking item; uzmi prvi proizvod
            foreach ($order->get_items() as $it) {
                $pid = $it->get_product_id();
                if (!$pid) continue;

                $cal = ovb_get_calendar_data($pid);
                if (!is_array($cal) || !$cal) continue;

                $prices = [];
                foreach ($stayDates as $d) {
                    if (!isset($cal[$d]) || !is_array($cal[$d])) continue;

                    // najčešći ključevi za cenu u kalendaru
                    $v = null;
                    foreach (['price','rate','amount'] as $pk) {
                        if (isset($cal[$d][$pk])) { $v = $cal[$d][$pk]; break; }
                    }
                    if ($v === null && isset($cal[$d]['meta']) && is_array($cal[$d]['meta'])) {
                        foreach (['price','rate','amount'] as $pk) {
                            if (isset($cal[$d]['meta'][$pk])) { $v = $cal[$d]['meta'][$pk]; break; }
                        }
                    }

                    if ($v !== null) {
                        if (is_string($v)) { $v = str_replace(',', '.', preg_replace('/[^\d.,-]/','', $v)); }
                        $v = (float)$v;
                        if (is_finite($v) && $v >= 0) $prices[] = $v;
                    }
                }

                if ($prices) {
                    return array_values($prices);
                }
            }
        }

        // 3) ništa – vrati prazan niz (UI će pasti na prosek)
        return [];
    }
}

// novi render
// === OVB Admin Order — overview cards (Booking / Payer / Company / Guests / Summary) ===
add_action('woocommerce_admin_order_item_headers', function($order){
    if (!($order instanceof WC_Order)) return;

    static $done = false;
    if ($done) return;
    $done = true;

    // --- Booking meta
    $bm        = ovb_get_order_booking_meta($order);
    $check_in  = $bm['check_in'];
    $check_out = $bm['check_out'];
    $nights    = ($check_in && $check_out) ? max(0, (int) floor((strtotime($check_out) - strtotime($check_in)) / DAY_IN_SECONDS)) : 0;
    $guests_total = (int)($order->get_meta('_ovb_guests_total') ?: $order->get_meta('guests') ?: $order->get_meta('_ovb_guests_num') ?: 1);

    // --- Payer (canonical)
    $payer = [
        'first'    => trim((string)($order->get_meta('booking_client_first_name') ?: $order->get_billing_first_name())),
        'last'     => trim((string)($order->get_meta('booking_client_last_name')  ?: $order->get_billing_last_name())),
        'email'    => trim((string)($order->get_meta('booking_client_email')      ?: $order->get_billing_email())),
        'phone'    => trim((string)($order->get_meta('booking_client_phone')      ?: $order->get_billing_phone())),
        'address'  => trim((string)($order->get_billing_address_1() . ($order->get_billing_address_2() ? ' ' . $order->get_billing_address_2() : ''))),
        'city'     => trim((string)$order->get_billing_city()),
        'postcode' => trim((string)$order->get_billing_postcode()),
        'country'  => trim((string)$order->get_billing_country()),
        'company'  => trim((string)($order->get_meta('_ovb_company_name') ?: $order->get_billing_company())),
        'vat'      => trim((string)($order->get_meta('_ovb_company_pib')  ?: $order->get_meta('_ovb_company_vat'))),
    ];

    $is_company = ovb_truthy($order->get_meta('_ovb_is_company')) || ($payer['company'] !== '');

    // --- Company details
    $company = [
        'name'     => trim((string)($order->get_meta('_ovb_company_name')     ?: $payer['company'])),
        'vat'      => trim((string)($order->get_meta('_ovb_company_pib')      ?: $order->get_meta('_ovb_company_vat'))),
        'mb'       => trim((string)$order->get_meta('_ovb_company_mb')),
        'contact'  => trim((string)$order->get_meta('_ovb_company_contact')),
        'phone'    => trim((string)$order->get_meta('_ovb_company_phone')),
        'address'  => trim((string)$order->get_meta('_ovb_company_address')),
        'city'     => trim((string)$order->get_meta('_ovb_company_city')),
        'postcode' => trim((string)$order->get_meta('_ovb_company_postcode')),
        'country'  => trim((string)$order->get_meta('_ovb_company_country')),
    ];

    // --- Countries map (jednom)
    $countries = (function_exists('WC') && isset(WC()->countries)) ? WC()->countries->get_countries() : [];
    if ($payer['country']   && isset($countries[$payer['country']]))   $payer['country_name']    = $countries[$payer['country']];
    if ($company['country'] && isset($countries[$company['country']])) $company['country_label'] = $countries[$company['country']];

    // --- Other person (Guest #0 samo kad je „plaća drugi”)
    $is_other = ($order->get_meta('_ovb_paid_by_other') === 'yes') || ovb_truthy($order->get_meta('_ovb_is_other'));
    $other = [
        'first'   => trim((string)$order->get_meta('_ovb_other_first_name')),
        'last'    => trim((string)$order->get_meta('_ovb_other_last_name')),
        'email'   => trim((string)$order->get_meta('_ovb_other_email')),
        'phone'   => trim((string)$order->get_meta('_ovb_other_phone')),
        'dob'     => trim((string)$order->get_meta('_ovb_other_dob')),
        'id'      => trim((string)$order->get_meta('_ovb_other_id_number')),
        'address' => trim((string)$order->get_meta('_ovb_other_address1')),
        'city'    => trim((string)$order->get_meta('_ovb_other_city')),
        'postcode'=> trim((string)$order->get_meta('_ovb_other_postcode')),
        'country' => trim((string)$order->get_meta('_ovb_other_country')),
    ];
    // Guest #0 card iscrtavamo SAMO ako je stvarno "plaća drugi" i postoji makar nešto od podataka
    $g0_has_data = $is_other && (implode('', $other) !== '');

    // --- Additional guests (JSON)
    $extra = [];
    $json = $order->get_meta('_ovb_guests_json');
    if (is_string($json) && $json !== '') {
        $arr = json_decode($json, true);
        if (is_array($arr)) {
            foreach ($arr as $g) {
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
    $has_extra = !empty($extra);

    // --- Product summary (prvi item)
    $thumb   = '';
    $p_link  = '';
    $p_title = '';
    $first_item_total_inc = 0.0;
    foreach ($order->get_items() as $it) {
        $product = $it->get_product();
        if ($product) {
            $img_id = $product->get_image_id();
            $src   = $img_id ? ( wp_get_attachment_image_src($img_id, 'medium_large') ?: wp_get_attachment_image_src($img_id, 'medium') ) : null;
            $thumb = $src ? $src[0] : wc_placeholder_img_src('woocommerce_thumbnail');
            $p_link = get_edit_post_link($product->get_id());
            $p_title= $product->get_name();
        }
        $first_item_total_inc = (float)$it->get_total() + (float)$it->get_total_tax();
        break;
    }

    // --- CSS
   echo '<style>
:root{--ovb-accent:#6d28d9;--ovb-accent-weak:#eef2ff}

/* Layout */
.ovb-cards-wrap{margin:40px auto 14px; max-width:1440px}
.ovb-heading{margin:0;}
.ovb-heading h2{margin:0;font-size:42px;font-weight:600;color:#111827}

.ovb-grid{display:grid;gap:16px;grid-template-columns:repeat(2,minmax(280px,1fr))}
.ovb-grid--3{grid-template-columns:repeat(3,minmax(280px,1fr))}

.ovb-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 18px;box-shadow:0 1px 3px rgba(16,24,40,.08); margin-top:20px}
.ovb-card h3{margin:0 0 12px;font-weight:600;color:#111827}

/* Naslovi koji treba da su veći */
.ovb-title-lg{font-size:26px;font-weight:600}
.ovb-title-guest{font-size:21px;font-weight:600}

/* Lists */
.ovb-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
.ovb-row{display:flex;gap:10px;align-items:center;line-height:1.5}
.ovb-row a{font-size:18px; text-decoration:underline}
.ovb-lab{font-size:18px;min-width:180px;color:#111827;font-weight:600;display:flex;gap:8px;align-items:center}
.ovb-row>span:last-child{font-size:18px} /* “spanovi” vrednosti 18px */

/* Linkovi – ljubičasti i podvučeni */
.ovb-card a{color:var(--ovb-accent);text-decoration:underline;text-underline-offset:2px}
.ovb-card a:hover{text-decoration:underline}

/* Badges */
.ovb-badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.ovb-badge{display:inline-block;padding:6px 10px;border:1px solid #e0e7ff;background:var(--ovb-accent-weak);border-radius:999px;font-size:11px;color:var(--ovb-accent)}

/* Guests grid */
.ovb-guests-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}

/* Additional guests – bez bordera/kruga na karticama; pametan grid 2/3 kolone */
.ovb-ag{display:grid;gap:14px}
.ovb-ag--2{grid-template-columns:repeat(2,minmax(260px,1fr))}
.ovb-ag--3{grid-template-columns:repeat(3,minmax(0,1fr));justify-items:start}
.ovb-ag .ovb-card{border:none;box-shadow:none;border-radius:0;padding:12px}
.ovb-ag--3>.ovb-card{max-width:420px}

/* Booking summary layout + veći thumbnail */
.ovb-summary{display:grid;grid-template-columns:1.2fr .8fr;gap:20px}
.ovb-s-left{display:grid;grid-template-columns:250px 1fr;gap:18px}
.ovb-summary img{width:250px;height:250px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb}

/* Subtotal: jači font */
.ovb-subtitle h4{margin:0 0 10px;font-size:18px;font-weight:600}
.ovb-subtitle .ovb-row span{font-weight:600}

/* Icons */
.ovb-ico{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px}
.ovb-ico .dashicons{font-size:18px;width:18px;height:18px;line-height:18px}

/* Responsive */
@media (max-width:1200px){
  .ovb-grid,.ovb-grid--3{grid-template-columns:1fr}
  .ovb-summary{grid-template-columns:1fr}
  .ovb-s-left{grid-template-columns:250px 1fr}
  .ovb-ag{grid-template-columns:1fr !important}
}
</style>';


    // --- Heading
    $heading_title = 'Booking #'.$order->get_order_number();
    if ($p_title) $heading_title .= ' for '.$p_title;

    echo '<div class="ovb-cards-wrap">';
    echo '<div class="ovb-heading"><h2 style="font-size:42px;padding:0; font-weight:600">'.esc_html($heading_title).'</h2></div>';

    // === TOP GRID: Booking | Payer | (Company if any) ===
    $top_grid_class = $is_company ? 'ovb-grid ovb-grid--3' : 'ovb-grid';
    echo '<div class="'.esc_attr($top_grid_class).'">';

      // Booking information
      echo '<div class="ovb-card">';
      echo '<h3 style="font-size:21px;">'.esc_html__('Booking information','ov-booking').'</h3>';
      echo '<ul class="ovb-list">';
      if ($check_in)  echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M12 24V21.3333H21.3333V2.66667H12V0H21.3333C22.0667 0 22.6944 0.261111 23.2167 0.783333C23.7389 1.30556 24 1.93333 24 2.66667V21.3333C24 22.0667 23.7389 22.6944 23.2167 23.2167C22.6944 23.7389 22.0667 24 21.3333 24H12ZM9.33333 18.6667L7.5 16.7333L10.9 13.3333H0V10.6667H10.9L7.5 7.26667L9.33333 5.33333L16 12L9.33333 18.6667Z" fill="#091029"/>
</svg></span>'.esc_html__('Check-in','ov-booking').'</span><span>'.esc_html(date_i18n(get_option('date_format'), strtotime($check_in))).'</span></li>';
      if ($check_out) echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M2.66667 24C1.93333 24 1.30556 23.7389 0.783333 23.2167C0.261111 22.6944 0 22.0667 0 21.3333V2.66667C0 1.93333 0.261111 1.30556 0.783333 0.783333C1.30556 0.261111 1.93333 0 2.66667 0H12V2.66667H2.66667V21.3333H12V24H2.66667ZM17.3333 18.6667L15.5 16.7333L18.9 13.3333H8V10.6667H18.9L15.5 7.26667L17.3333 5.33333L24 12L17.3333 18.6667Z" fill="#091029"/>
</svg></span>'.esc_html__('Check-out','ov-booking').'</span><span>'.esc_html(date_i18n(get_option('date_format'), strtotime($check_out))).'</span></li>';
      if ($nights)    echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="30" height="21" viewBox="0 0 30 21" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M0.333008 20.3333V0.333252H2.99967V13.6666H13.6663V2.99992H24.333C25.7997 2.99992 27.0552 3.52214 28.0997 4.56659C29.1441 5.61103 29.6663 6.86659 29.6663 8.33325V20.3333H26.9997V16.3333H2.99967V20.3333H0.333008ZM8.33301 12.3333C7.2219 12.3333 6.27745 11.9444 5.49967 11.1666C4.7219 10.3888 4.33301 9.44436 4.33301 8.33325C4.33301 7.22214 4.7219 6.2777 5.49967 5.49992C6.27745 4.72214 7.2219 4.33325 8.33301 4.33325C9.44412 4.33325 10.3886 4.72214 11.1663 5.49992C11.9441 6.2777 12.333 7.22214 12.333 8.33325C12.333 9.44436 11.9441 10.3888 11.1663 11.1666C10.3886 11.9444 9.44412 12.3333 8.33301 12.3333ZM16.333 13.6666H26.9997V8.33325C26.9997 7.59992 26.7386 6.97214 26.2163 6.44992C25.6941 5.9277 25.0663 5.66659 24.333 5.66659H16.333V13.6666ZM8.33301 9.66659C8.71079 9.66659 9.02745 9.53881 9.28301 9.28325C9.53856 9.0277 9.66634 8.71103 9.66634 8.33325C9.66634 7.95547 9.53856 7.63881 9.28301 7.38325C9.02745 7.1277 8.71079 6.99992 8.33301 6.99992C7.95523 6.99992 7.63856 7.1277 7.38301 7.38325C7.12745 7.63881 6.99967 7.95547 6.99967 8.33325C6.99967 8.71103 7.12745 9.0277 7.38301 9.28325C7.63856 9.53881 7.95523 9.66659 8.33301 9.66659Z" fill="#091029"/>
</svg></span>'.esc_html__('Nights booked','ov-booking').'</span><span>'.esc_html($nights).'</span></li>';
      echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="32" height="16" viewBox="0 0 32 16" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M0 16V13.9C0 12.9444 0.488889 12.1667 1.46667 11.5667C2.44444 10.9667 3.73333 10.6667 5.33333 10.6667C5.62222 10.6667 5.9 10.6722 6.16667 10.6833C6.43333 10.6944 6.68889 10.7222 6.93333 10.7667C6.62222 11.2333 6.38889 11.7222 6.23333 12.2333C6.07778 12.7444 6 13.2778 6 13.8333V16H0ZM8 16V13.8333C8 13.1222 8.19444 12.4722 8.58333 11.8833C8.97222 11.2944 9.52222 10.7778 10.2333 10.3333C10.9444 9.88889 11.7944 9.55556 12.7833 9.33333C13.7722 9.11111 14.8444 9 16 9C17.1778 9 18.2611 9.11111 19.25 9.33333C20.2389 9.55556 21.0889 9.88889 21.8 10.3333C22.5111 10.7778 23.0556 11.2944 23.4333 11.8833C23.8111 12.4722 24 13.1222 24 13.8333V16H8ZM26 16V13.8333C26 13.2556 25.9278 12.7111 25.7833 12.2C25.6389 11.6889 25.4222 11.2111 25.1333 10.7667C25.3778 10.7222 25.6278 10.6944 25.8833 10.6833C26.1389 10.6722 26.4 10.6667 26.6667 10.6667C28.2667 10.6667 29.5556 10.9611 30.5333 11.55C31.5111 12.1389 32 12.9222 32 13.9V16H26ZM10.8333 13.3333H21.2C20.9778 12.8889 20.3611 12.5 19.35 12.1667C18.3389 11.8333 17.2222 11.6667 16 11.6667C14.7778 11.6667 13.6611 11.8333 12.65 12.1667C11.6389 12.5 11.0333 12.8889 10.8333 13.3333ZM5.33333 9.33333C4.6 9.33333 3.97222 9.07222 3.45 8.55C2.92778 8.02778 2.66667 7.4 2.66667 6.66667C2.66667 5.91111 2.92778 5.27778 3.45 4.76667C3.97222 4.25556 4.6 4 5.33333 4C6.08889 4 6.72222 4.25556 7.23333 4.76667C7.74444 5.27778 8 5.91111 8 6.66667C8 7.4 7.74444 8.02778 7.23333 8.55C6.72222 9.07222 6.08889 9.33333 5.33333 9.33333ZM26.6667 9.33333C25.9333 9.33333 25.3056 9.07222 24.7833 8.55C24.2611 8.02778 24 7.4 24 6.66667C24 5.91111 24.2611 5.27778 24.7833 4.76667C25.3056 4.25556 25.9333 4 26.6667 4C27.4222 4 28.0556 4.25556 28.5667 4.76667C29.0778 5.27778 29.3333 5.91111 29.3333 6.66667C29.3333 7.4 29.0778 8.02778 28.5667 8.55C28.0556 9.07222 27.4222 9.33333 26.6667 9.33333ZM16 8C14.8889 8 13.9444 7.61111 13.1667 6.83333C12.3889 6.05556 12 5.11111 12 4C12 2.86667 12.3889 1.91667 13.1667 1.15C13.9444 0.383333 14.8889 0 16 0C17.1333 0 18.0833 0.383333 18.85 1.15C19.6167 1.91667 20 2.86667 20 4C20 5.11111 19.6167 6.05556 18.85 6.83333C18.0833 7.61111 17.1333 8 16 8ZM16 5.33333C16.3778 5.33333 16.6944 5.20556 16.95 4.95C17.2056 4.69444 17.3333 4.37778 17.3333 4C17.3333 3.62222 17.2056 3.30556 16.95 3.05C16.6944 2.79444 16.3778 2.66667 16 2.66667C15.6222 2.66667 15.3056 2.79444 15.05 3.05C14.7944 3.30556 14.6667 3.62222 14.6667 4C14.6667 4.37778 14.7944 4.69444 15.05 4.95C15.3056 5.20556 15.6222 5.33333 16 5.33333Z" fill="#091029"/>
</svg>
</span>'.esc_html__('Number of guests','ov-booking').'</span><span>'.esc_html($guests_total).'</span></li>';
      echo '</ul>';

      $badges = [];
      if ($is_company) $badges[] = __('Company invoice','ov-booking');
      if ($is_other)   $badges[] = __('Different payer/guest','ov-booking');
      if ($badges) {
          echo '<div class="ovb-badges">';
          foreach ($badges as $b) echo '<span class="ovb-badge">'.esc_html($b).'</span>';
          echo '</div>';
      }
      echo '</div>';

      // Payer information
      echo '<div class="ovb-card">';
      echo '<h3 style="font-size:21px;">'.esc_html__('Payer information','ov-booking').'</h3>';
      echo '<ul class="ovb-list">';
      if ($payer['first']||$payer['last']) echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M10.9997 10.9999C9.53301 10.9999 8.27745 10.4777 7.23301 9.43325C6.18856 8.38881 5.66634 7.13325 5.66634 5.66659C5.66634 4.19992 6.18856 2.94436 7.23301 1.89992C8.27745 0.855474 9.53301 0.333252 10.9997 0.333252C12.4663 0.333252 13.7219 0.855474 14.7663 1.89992C15.8108 2.94436 16.333 4.19992 16.333 5.66659C16.333 7.13325 15.8108 8.38881 14.7663 9.43325C13.7219 10.4777 12.4663 10.9999 10.9997 10.9999ZM0.333008 21.6666V17.9333C0.333008 17.1777 0.527452 16.4833 0.916341 15.8499C1.30523 15.2166 1.8219 14.7333 2.46634 14.3999C3.84412 13.711 5.24412 13.1944 6.66634 12.8499C8.08856 12.5055 9.53301 12.3333 10.9997 12.3333C12.4663 12.3333 13.9108 12.5055 15.333 12.8499C16.7552 13.1944 18.1552 13.711 19.533 14.3999C20.1775 14.7333 20.6941 15.2166 21.083 15.8499C21.4719 16.4833 21.6663 17.1777 21.6663 17.9333V21.6666H0.333008ZM2.99967 18.9999H18.9997V17.9333C18.9997 17.6888 18.9386 17.4666 18.8163 17.2666C18.6941 17.0666 18.533 16.911 18.333 16.7999C17.133 16.1999 15.9219 15.7499 14.6997 15.4499C13.4775 15.1499 12.2441 14.9999 10.9997 14.9999C9.75523 14.9999 8.5219 15.1499 7.29967 15.4499C6.07745 15.7499 4.86634 16.1999 3.66634 16.7999C3.46634 16.911 3.30523 17.0666 3.18301 17.2666C3.06079 17.4666 2.99967 17.6888 2.99967 17.9333V18.9999ZM10.9997 8.33325C11.733 8.33325 12.3608 8.07214 12.883 7.54992C13.4052 7.0277 13.6663 6.39992 13.6663 5.66659C13.6663 4.93325 13.4052 4.30547 12.883 3.78325C12.3608 3.26103 11.733 2.99992 10.9997 2.99992C10.2663 2.99992 9.63856 3.26103 9.11634 3.78325C8.59412 4.30547 8.33301 4.93325 8.33301 5.66659C8.33301 6.39992 8.59412 7.0277 9.11634 7.54992C9.63856 8.07214 10.2663 8.33325 10.9997 8.33325Z" fill="#091029"/>
</svg>
</span>'.esc_html__('Customer name','ov-booking').'</span><span>'.esc_html(trim($payer['first'].' '.$payer['last'])).'</span></li>';
      if ($payer['email'])                 echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico"><svg width="28" height="22" viewBox="0 0 28 22" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M3.33366 21.6666C2.60033 21.6666 1.97255 21.4055 1.45033 20.8833C0.928103 20.361 0.666992 19.7333 0.666992 18.9999V2.99992C0.666992 2.26659 0.928103 1.63881 1.45033 1.11659C1.97255 0.594363 2.60033 0.333252 3.33366 0.333252H24.667C25.4003 0.333252 26.0281 0.594363 26.5503 1.11659C27.0725 1.63881 27.3337 2.26659 27.3337 2.99992V18.9999C27.3337 19.7333 27.0725 20.361 26.5503 20.8833C26.0281 21.4055 25.4003 21.6666 24.667 21.6666H3.33366ZM14.0003 12.3333L3.33366 5.66659V18.9999H24.667V5.66659L14.0003 12.3333ZM14.0003 9.66659L24.667 2.99992H3.33366L14.0003 9.66659ZM3.33366 5.66659V2.99992V18.9999V5.66659Z" fill="#091029"/>
</svg>
</span>Email</span><a href="mailto:'.esc_attr(sanitize_email($payer['email'])).'">'.esc_html($payer['email']).'</a></li>';
      if ($payer['phone']) { $tel = preg_replace('/[^0-9+]/','', $payer['phone']); echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M22.6 24C19.8222 24 17.0778 23.3944 14.3667 22.1833C11.6556 20.9722 9.18889 19.2556 6.96667 17.0333C4.74444 14.8111 3.02778 12.3444 1.81667 9.63333C0.605556 6.92222 0 4.17778 0 1.4C0 1 0.133333 0.666667 0.4 0.4C0.666667 0.133333 1 0 1.4 0H6.8C7.11111 0 7.38889 0.105556 7.63333 0.316667C7.87778 0.527778 8.02222 0.777778 8.06667 1.06667L8.93333 5.73333C8.97778 6.08889 8.96667 6.38889 8.9 6.63333C8.83333 6.87778 8.71111 7.08889 8.53333 7.26667L5.3 10.5333C5.74444 11.3556 6.27222 12.15 6.88333 12.9167C7.49444 13.6833 8.16667 14.4222 8.9 15.1333C9.58889 15.8222 10.3111 16.4611 11.0667 17.05C11.8222 17.6389 12.6222 18.1778 13.4667 18.6667L16.6 15.5333C16.8 15.3333 17.0611 15.1833 17.3833 15.0833C17.7056 14.9833 18.0222 14.9556 18.3333 15L22.9333 15.9333C23.2444 16.0222 23.5 16.1833 23.7 16.4167C23.9 16.65 24 16.9111 24 17.2V22.6C24 23 23.8667 23.3333 23.6 23.6C23.3333 23.8667 23 24 22.6 24ZM4.03333 8L6.23333 5.8L5.66667 2.66667H2.7C2.81111 3.57778 2.96667 4.47778 3.16667 5.36667C3.36667 6.25556 3.65556 7.13333 4.03333 8ZM15.9667 19.9333C16.8333 20.3111 17.7167 20.6111 18.6167 20.8333C19.5167 21.0556 20.4222 21.2 21.3333 21.2667V18.3333L18.2 17.7L15.9667 19.9333Z" fill="#091029"/>
</svg></span>'.esc_html__('Customer phone','ov-booking').'</span><a href="tel:'.esc_attr($tel).'">'.esc_html($payer['phone']).'</a></li>'; }
      if ($payer['address']) echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="28" height="24" viewBox="0 0 28 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M0.666992 24V8L11.3337 0L18.5003 5.4C17.9448 5.46667 17.4225 5.59444 16.9337 5.78333C16.4448 5.97222 15.9781 6.22222 15.5337 6.53333L11.3337 3.33333L3.33366 9.33333V21.3333H8.66699V24H0.666992ZM11.3337 24V21.4667C11.3337 21 11.4503 20.5611 11.6837 20.15C11.917 19.7389 12.2337 19.4111 12.6337 19.1667C13.6559 18.5667 14.7281 18.1111 15.8503 17.8C16.9725 17.4889 18.1337 17.3333 19.3337 17.3333C20.5337 17.3333 21.6948 17.4889 22.817 17.8C23.9392 18.1111 25.0114 18.5667 26.0337 19.1667C26.4337 19.4111 26.7503 19.7389 26.9837 20.15C27.217 20.5611 27.3337 21 27.3337 21.4667V24H11.3337ZM14.2003 21.3333H24.467C23.6892 20.8889 22.867 20.5556 22.0003 20.3333C21.1337 20.1111 20.2448 20 19.3337 20C18.4225 20 17.5337 20.1111 16.667 20.3333C15.8003 20.5556 14.9781 20.8889 14.2003 21.3333ZM19.3337 16C18.2225 16 17.2781 15.6111 16.5003 14.8333C15.7225 14.0556 15.3337 13.1111 15.3337 12C15.3337 10.8889 15.7225 9.94444 16.5003 9.16667C17.2781 8.38889 18.2225 8 19.3337 8C20.4448 8 21.3892 8.38889 22.167 9.16667C22.9448 9.94444 23.3337 10.8889 23.3337 12C23.3337 13.1111 22.9448 14.0556 22.167 14.8333C21.3892 15.6111 20.4448 16 19.3337 16ZM19.3337 13.3333C19.7114 13.3333 20.0281 13.2056 20.2837 12.95C20.5392 12.6944 20.667 12.3778 20.667 12C20.667 11.6222 20.5392 11.3056 20.2837 11.05C20.0281 10.7944 19.7114 10.6667 19.3337 10.6667C18.9559 10.6667 18.6392 10.7944 18.3837 11.05C18.1281 11.3056 18.0003 11.6222 18.0003 12C18.0003 12.3778 18.1281 12.6944 18.3837 12.95C18.6392 13.2056 18.9559 13.3333 19.3337 13.3333Z" fill="#091029"/>
</svg>
</span>'.esc_html__('Customer address','ov-booking').'</span><span>'.esc_html($payer['address']).'</span></li>';
      if ($payer['city'])    echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico"><span class="dashicons dashicons-building"></span></span>'.esc_html__('Customer city','ov-booking').'</span><span>'.esc_html($payer['city']).'</span></li>';
      if ($payer['postcode'])echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M6.00033 16.6667V11.3334H3.33366V24.6667H24.667V11.3334H11.3337V8.66675H24.667C25.4003 8.66675 26.0281 8.92786 26.5503 9.45008C27.0725 9.9723 27.3337 10.6001 27.3337 11.3334V24.6667C27.3337 25.4001 27.0725 26.0279 26.5503 26.5501C26.0281 27.0723 25.4003 27.3334 24.667 27.3334H3.33366C2.60033 27.3334 1.97255 27.0723 1.45033 26.5501C0.928103 26.0279 0.666992 25.4001 0.666992 24.6667V11.3334C0.666992 10.6001 0.928103 9.9723 1.45033 9.45008C1.97255 8.92786 2.60033 8.66675 3.33366 8.66675H6.00033V0.666748H16.667V6.00008H8.66699V16.6667H6.00033Z" fill="#091029"/>
</svg>
</span>'.esc_html__('Customer zipcode','ov-booking').'</span><span>'.esc_html($payer['postcode']).'</span></li>';
      if (!empty($payer['country_name'])) echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M6.00033 16.6667V11.3334H3.33366V24.6667H24.667V11.3334H11.3337V8.66675H24.667C25.4003 8.66675 26.0281 8.92786 26.5503 9.45008C27.0725 9.9723 27.3337 10.6001 27.3337 11.3334V24.6667C27.3337 25.4001 27.0725 26.0279 26.5503 26.5501C26.0281 27.0723 25.4003 27.3334 24.667 27.3334H3.33366C2.60033 27.3334 1.97255 27.0723 1.45033 26.5501C0.928103 26.0279 0.666992 25.4001 0.666992 24.6667V11.3334C0.666992 10.6001 0.928103 9.9723 1.45033 9.45008C1.97255 8.92786 2.60033 8.66675 3.33366 8.66675H6.00033V0.666748H16.667V6.00008H8.66699V16.6667H6.00033Z" fill="#091029"/>
</svg>
</span>'.esc_html__('Country','ov-booking').'</span><span>'.esc_html($payer['country_name']).'</span></li>';
      echo '</ul>';
      echo '</div>';

      // Company details (samo kad postoji)
      if ($is_company) {
          echo '<div class="ovb-card">';
          echo '<h3 style="font-size:21px;">'.esc_html__('Company details','ov-booking').'</h3>';
          echo '<ul class="ovb-list">';
          if ($company['name']   !== '') echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M10.9997 10.9999C9.53301 10.9999 8.27745 10.4777 7.23301 9.43325C6.18856 8.38881 5.66634 7.13325 5.66634 5.66659C5.66634 4.19992 6.18856 2.94436 7.23301 1.89992C8.27745 0.855474 9.53301 0.333252 10.9997 0.333252C12.4663 0.333252 13.7219 0.855474 14.7663 1.89992C15.8108 2.94436 16.333 4.19992 16.333 5.66659C16.333 7.13325 15.8108 8.38881 14.7663 9.43325C13.7219 10.4777 12.4663 10.9999 10.9997 10.9999ZM0.333008 21.6666V17.9333C0.333008 17.1777 0.527452 16.4833 0.916341 15.8499C1.30523 15.2166 1.8219 14.7333 2.46634 14.3999C3.84412 13.711 5.24412 13.1944 6.66634 12.8499C8.08856 12.5055 9.53301 12.3333 10.9997 12.3333C12.4663 12.3333 13.9108 12.5055 15.333 12.8499C16.7552 13.1944 18.1552 13.711 19.533 14.3999C20.1775 14.7333 20.6941 15.2166 21.083 15.8499C21.4719 16.4833 21.6663 17.1777 21.6663 17.9333V21.6666H0.333008ZM2.99967 18.9999H18.9997V17.9333C18.9997 17.6888 18.9386 17.4666 18.8163 17.2666C18.6941 17.0666 18.533 16.911 18.333 16.7999C17.133 16.1999 15.9219 15.7499 14.6997 15.4499C13.4775 15.1499 12.2441 14.9999 10.9997 14.9999C9.75523 14.9999 8.5219 15.1499 7.29967 15.4499C6.07745 15.7499 4.86634 16.1999 3.66634 16.7999C3.46634 16.911 3.30523 17.0666 3.18301 17.2666C3.06079 17.4666 2.99967 17.6888 2.99967 17.9333V18.9999ZM10.9997 8.33325C11.733 8.33325 12.3608 8.07214 12.883 7.54992C13.4052 7.0277 13.6663 6.39992 13.6663 5.66659C13.6663 4.93325 13.4052 4.30547 12.883 3.78325C12.3608 3.26103 11.733 2.99992 10.9997 2.99992C10.2663 2.99992 9.63856 3.26103 9.11634 3.78325C8.59412 4.30547 8.33301 4.93325 8.33301 5.66659C8.33301 6.39992 8.59412 7.0277 9.11634 7.54992C9.63856 8.07214 10.2663 8.33325 10.9997 8.33325Z" fill="#091029"/>
</svg></span>'.esc_html__('Company','ov-booking').'</span><span>'.esc_html($company['name']).'</span></li>';
          if ($company['vat']    !== '') echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="28" height="22" viewBox="0 0 28 22" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M16.667 12.3334H23.3337V9.66671H16.667V12.3334ZM16.667 8.33337H23.3337V5.66671H16.667V8.33337ZM4.66699 16.3334H15.3337V15.6C15.3337 14.6 14.8448 13.8056 13.867 13.2167C12.8892 12.6278 11.6003 12.3334 10.0003 12.3334C8.40032 12.3334 7.11144 12.6278 6.13366 13.2167C5.15588 13.8056 4.66699 14.6 4.66699 15.6V16.3334ZM10.0003 11C10.7337 11 11.3614 10.7389 11.8837 10.2167C12.4059 9.69449 12.667 9.06671 12.667 8.33337C12.667 7.60004 12.4059 6.97226 11.8837 6.45004C11.3614 5.92782 10.7337 5.66671 10.0003 5.66671C9.26699 5.66671 8.63921 5.92782 8.11699 6.45004C7.59477 6.97226 7.33366 7.60004 7.33366 8.33337C7.33366 9.06671 7.59477 9.69449 8.11699 10.2167C8.63921 10.7389 9.26699 11 10.0003 11ZM3.33366 21.6667C2.60033 21.6667 1.97255 21.4056 1.45033 20.8834C0.928103 20.3612 0.666992 19.7334 0.666992 19V3.00004C0.666992 2.26671 0.928103 1.63893 1.45033 1.11671C1.97255 0.594485 2.60033 0.333374 3.33366 0.333374H24.667C25.4003 0.333374 26.0281 0.594485 26.5503 1.11671C27.0725 1.63893 27.3337 2.26671 27.3337 3.00004V19C27.3337 19.7334 27.0725 20.3612 26.5503 20.8834C26.0281 21.4056 25.4003 21.6667 24.667 21.6667H3.33366ZM3.33366 19H24.667V3.00004H3.33366V19Z" fill="black"/>
</svg></span>'.esc_html__('VAT/PIB','ov-booking').'</span><span>'.esc_html($company['vat']).'</span></li>';
          if ($company['mb']     !== '') echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico"><span class="dashicons dashicons-media-spreadsheet"></span></span>'.esc_html__('Registration no.','ov-booking').'</span><span>'.esc_html($company['mb']).'</span></li>';
          if ($company['contact']!== '') echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M10.9997 10.9999C9.53301 10.9999 8.27745 10.4777 7.23301 9.43325C6.18856 8.38881 5.66634 7.13325 5.66634 5.66659C5.66634 4.19992 6.18856 2.94436 7.23301 1.89992C8.27745 0.855474 9.53301 0.333252 10.9997 0.333252C12.4663 0.333252 13.7219 0.855474 14.7663 1.89992C15.8108 2.94436 16.333 4.19992 16.333 5.66659C16.333 7.13325 15.8108 8.38881 14.7663 9.43325C13.7219 10.4777 12.4663 10.9999 10.9997 10.9999ZM0.333008 21.6666V17.9333C0.333008 17.1777 0.527452 16.4833 0.916341 15.8499C1.30523 15.2166 1.8219 14.7333 2.46634 14.3999C3.84412 13.711 5.24412 13.1944 6.66634 12.8499C8.08856 12.5055 9.53301 12.3333 10.9997 12.3333C12.4663 12.3333 13.9108 12.5055 15.333 12.8499C16.7552 13.1944 18.1552 13.711 19.533 14.3999C20.1775 14.7333 20.6941 15.2166 21.083 15.8499C21.4719 16.4833 21.6663 17.1777 21.6663 17.9333V21.6666H0.333008ZM2.99967 18.9999H18.9997V17.9333C18.9997 17.6888 18.9386 17.4666 18.8163 17.2666C18.6941 17.0666 18.533 16.911 18.333 16.7999C17.133 16.1999 15.9219 15.7499 14.6997 15.4499C13.4775 15.1499 12.2441 14.9999 10.9997 14.9999C9.75523 14.9999 8.5219 15.1499 7.29967 15.4499C6.07745 15.7499 4.86634 16.1999 3.66634 16.7999C3.46634 16.911 3.30523 17.0666 3.18301 17.2666C3.06079 17.4666 2.99967 17.6888 2.99967 17.9333V18.9999ZM10.9997 8.33325C11.733 8.33325 12.3608 8.07214 12.883 7.54992C13.4052 7.0277 13.6663 6.39992 13.6663 5.66659C13.6663 4.93325 13.4052 4.30547 12.883 3.78325C12.3608 3.26103 11.733 2.99992 10.9997 2.99992C10.2663 2.99992 9.63856 3.26103 9.11634 3.78325C8.59412 4.30547 8.33301 4.93325 8.33301 5.66659C8.33301 6.39992 8.59412 7.0277 9.11634 7.54992C9.63856 8.07214 10.2663 8.33325 10.9997 8.33325Z" fill="#091029"/>
</svg></span>'.esc_html__('Contact person','ov-booking').'</span><span>'.esc_html($company['contact']).'</span></li>';
          if ($company['phone']  !== '') { $ctel = preg_replace("/[^0-9+]/","",$company['phone']); echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M22.6 24C19.8222 24 17.0778 23.3944 14.3667 22.1833C11.6556 20.9722 9.18889 19.2556 6.96667 17.0333C4.74444 14.8111 3.02778 12.3444 1.81667 9.63333C0.605556 6.92222 0 4.17778 0 1.4C0 1 0.133333 0.666667 0.4 0.4C0.666667 0.133333 1 0 1.4 0H6.8C7.11111 0 7.38889 0.105556 7.63333 0.316667C7.87778 0.527778 8.02222 0.777778 8.06667 1.06667L8.93333 5.73333C8.97778 6.08889 8.96667 6.38889 8.9 6.63333C8.83333 6.87778 8.71111 7.08889 8.53333 7.26667L5.3 10.5333C5.74444 11.3556 6.27222 12.15 6.88333 12.9167C7.49444 13.6833 8.16667 14.4222 8.9 15.1333C9.58889 15.8222 10.3111 16.4611 11.0667 17.05C11.8222 17.6389 12.6222 18.1778 13.4667 18.6667L16.6 15.5333C16.8 15.3333 17.0611 15.1833 17.3833 15.0833C17.7056 14.9833 18.0222 14.9556 18.3333 15L22.9333 15.9333C23.2444 16.0222 23.5 16.1833 23.7 16.4167C23.9 16.65 24 16.9111 24 17.2V22.6C24 23 23.8667 23.3333 23.6 23.6C23.3333 23.8667 23 24 22.6 24ZM4.03333 8L6.23333 5.8L5.66667 2.66667H2.7C2.81111 3.57778 2.96667 4.47778 3.16667 5.36667C3.36667 6.25556 3.65556 7.13333 4.03333 8ZM15.9667 19.9333C16.8333 20.3111 17.7167 20.6111 18.6167 20.8333C19.5167 21.0556 20.4222 21.2 21.3333 21.2667V18.3333L18.2 17.7L15.9667 19.9333Z" fill="#091029"/>
</svg>
</span>'.esc_html__('Company phone','ov-booking').'</span><a href="tel:'.esc_attr($ctel).'">'.esc_html($company['phone']).'</a></li>'; }
          if ($company['address']!== '') echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico"><span class="dashicons dashicons-location"></span></span>'.esc_html__('Address','ov-booking').'</span><span>'.esc_html($company['address']).'</span></li>';
          if ($company['city']   !== '') echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico"><span class="dashicons dashicons-building"></span></span>'.esc_html__('City','ov-booking').'</span><span>'.esc_html($company['city']).'</span></li>';
          if ($company['postcode']!=='') echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M6.00033 16.6667V11.3334H3.33366V24.6667H24.667V11.3334H11.3337V8.66675H24.667C25.4003 8.66675 26.0281 8.92786 26.5503 9.45008C27.0725 9.9723 27.3337 10.6001 27.3337 11.3334V24.6667C27.3337 25.4001 27.0725 26.0279 26.5503 26.5501C26.0281 27.0723 25.4003 27.3334 24.667 27.3334H3.33366C2.60033 27.3334 1.97255 27.0723 1.45033 26.5501C0.928103 26.0279 0.666992 25.4001 0.666992 24.6667V11.3334C0.666992 10.6001 0.928103 9.9723 1.45033 9.45008C1.97255 8.92786 2.60033 8.66675 3.33366 8.66675H6.00033V0.666748H16.667V6.00008H8.66699V16.6667H6.00033Z" fill="#091029"/>
</svg>
</span>'.esc_html__('Postcode','ov-booking').'</span><span>'.esc_html($company['postcode']).'</span></li>';
          if ($company['country']!=='')  echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico"><span class="dashicons dashicons-admin-site"></span></span>'.esc_html__('Country','ov-booking').'</span><span>'.esc_html($company['country_label'] ?? $company['country']).'</span></li>';
          echo '</ul>';
          echo '</div>';
      }

    echo '</div>'; // /top grid

    // === GUESTS (nema spoljnog "Guest information" card-a) ===
    if ($g0_has_data || $has_extra) {
        $guests_row_class = ($g0_has_data && $has_extra) ? 'ovb-guests-row ovb-guests-row--2' : 'ovb-guests-row ovb-guests-row--1';
        echo '<div class="'.esc_attr($guests_row_class).'">';

          // Guest #0
          if ($g0_has_data) {
              $g0_name  = trim($other['first'].' '.$other['last']);
              echo '<div class="ovb-card">';
              echo '<h3 class="ovb-title-lg">'.esc_html__('Guest #0 information','ov-booking').'</h3>';
              echo '<ul class="ovb-list">';
              if ($g0_name)  echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M10.9997 10.9999C9.53301 10.9999 8.27745 10.4777 7.23301 9.43325C6.18856 8.38881 5.66634 7.13325 5.66634 5.66659C5.66634 4.19992 6.18856 2.94436 7.23301 1.89992C8.27745 0.855474 9.53301 0.333252 10.9997 0.333252C12.4663 0.333252 13.7219 0.855474 14.7663 1.89992C15.8108 2.94436 16.333 4.19992 16.333 5.66659C16.333 7.13325 15.8108 8.38881 14.7663 9.43325C13.7219 10.4777 12.4663 10.9999 10.9997 10.9999ZM0.333008 21.6666V17.9333C0.333008 17.1777 0.527452 16.4833 0.916341 15.8499C1.30523 15.2166 1.8219 14.7333 2.46634 14.3999C3.84412 13.711 5.24412 13.1944 6.66634 12.8499C8.08856 12.5055 9.53301 12.3333 10.9997 12.3333C12.4663 12.3333 13.9108 12.5055 15.333 12.8499C16.7552 13.1944 18.1552 13.711 19.533 14.3999C20.1775 14.7333 20.6941 15.2166 21.083 15.8499C21.4719 16.4833 21.6663 17.1777 21.6663 17.9333V21.6666H0.333008ZM2.99967 18.9999H18.9997V17.9333C18.9997 17.6888 18.9386 17.4666 18.8163 17.2666C18.6941 17.0666 18.533 16.911 18.333 16.7999C17.133 16.1999 15.9219 15.7499 14.6997 15.4499C13.4775 15.1499 12.2441 14.9999 10.9997 14.9999C9.75523 14.9999 8.5219 15.1499 7.29967 15.4499C6.07745 15.7499 4.86634 16.1999 3.66634 16.7999C3.46634 16.911 3.30523 17.0666 3.18301 17.2666C3.06079 17.4666 2.99967 17.6888 2.99967 17.9333V18.9999ZM10.9997 8.33325C11.733 8.33325 12.3608 8.07214 12.883 7.54992C13.4052 7.0277 13.6663 6.39992 13.6663 5.66659C13.6663 4.93325 13.4052 4.30547 12.883 3.78325C12.3608 3.26103 11.733 2.99992 10.9997 2.99992C10.2663 2.99992 9.63856 3.26103 9.11634 3.78325C8.59412 4.30547 8.33301 4.93325 8.33301 5.66659C8.33301 6.39992 8.59412 7.0277 9.11634 7.54992C9.63856 8.07214 10.2663 8.33325 10.9997 8.33325Z" fill="#091029"/>
</svg></span>'.esc_html__('Customer name','ov-booking').'</span><span>'.esc_html($g0_name).'</span></li>';
              if ($other['email']) echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="28" height="22" viewBox="0 0 28 22" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M3.33366 21.6666C2.60033 21.6666 1.97255 21.4055 1.45033 20.8833C0.928103 20.361 0.666992 19.7333 0.666992 18.9999V2.99992C0.666992 2.26659 0.928103 1.63881 1.45033 1.11659C1.97255 0.594363 2.60033 0.333252 3.33366 0.333252H24.667C25.4003 0.333252 26.0281 0.594363 26.5503 1.11659C27.0725 1.63881 27.3337 2.26659 27.3337 2.99992V18.9999C27.3337 19.7333 27.0725 20.361 26.5503 20.8833C26.0281 21.4055 25.4003 21.6666 24.667 21.6666H3.33366ZM14.0003 12.3333L3.33366 5.66659V18.9999H24.667V5.66659L14.0003 12.3333ZM14.0003 9.66659L24.667 2.99992H3.33366L14.0003 9.66659ZM3.33366 5.66659V2.99992V18.9999V5.66659Z" fill="#091029"/>
</svg></span>Email</span><a href="mailto:'.esc_attr(sanitize_email($other['email'])).'">'.esc_html($other['email']).'</a></li>';
              if ($other['phone']) { $tel0 = preg_replace("/[^0-9+]/","",$other['phone']); echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M22.6 24C19.8222 24 17.0778 23.3944 14.3667 22.1833C11.6556 20.9722 9.18889 19.2556 6.96667 17.0333C4.74444 14.8111 3.02778 12.3444 1.81667 9.63333C0.605556 6.92222 0 4.17778 0 1.4C0 1 0.133333 0.666667 0.4 0.4C0.666667 0.133333 1 0 1.4 0H6.8C7.11111 0 7.38889 0.105556 7.63333 0.316667C7.87778 0.527778 8.02222 0.777778 8.06667 1.06667L8.93333 5.73333C8.97778 6.08889 8.96667 6.38889 8.9 6.63333C8.83333 6.87778 8.71111 7.08889 8.53333 7.26667L5.3 10.5333C5.74444 11.3556 6.27222 12.15 6.88333 12.9167C7.49444 13.6833 8.16667 14.4222 8.9 15.1333C9.58889 15.8222 10.3111 16.4611 11.0667 17.05C11.8222 17.6389 12.6222 18.1778 13.4667 18.6667L16.6 15.5333C16.8 15.3333 17.0611 15.1833 17.3833 15.0833C17.7056 14.9833 18.0222 14.9556 18.3333 15L22.9333 15.9333C23.2444 16.0222 23.5 16.1833 23.7 16.4167C23.9 16.65 24 16.9111 24 17.2V22.6C24 23 23.8667 23.3333 23.6 23.6C23.3333 23.8667 23 24 22.6 24ZM4.03333 8L6.23333 5.8L5.66667 2.66667H2.7C2.81111 3.57778 2.96667 4.47778 3.16667 5.36667C3.36667 6.25556 3.65556 7.13333 4.03333 8ZM15.9667 19.9333C16.8333 20.3111 17.7167 20.6111 18.6167 20.8333C19.5167 21.0556 20.4222 21.2 21.3333 21.2667V18.3333L18.2 17.7L15.9667 19.9333Z" fill="#091029"/>
</svg></span>'.esc_html__('Customer phone','ov-booking').'</span><a href="tel:'.esc_attr($tel0).'">'.esc_html($other['phone']).'</a></li>'; }
              if ($other['address'])  echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="28" height="24" viewBox="0 0 28 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M0.666992 24V8L11.3337 0L18.5003 5.4C17.9448 5.46667 17.4225 5.59444 16.9337 5.78333C16.4448 5.97222 15.9781 6.22222 15.5337 6.53333L11.3337 3.33333L3.33366 9.33333V21.3333H8.66699V24H0.666992ZM11.3337 24V21.4667C11.3337 21 11.4503 20.5611 11.6837 20.15C11.917 19.7389 12.2337 19.4111 12.6337 19.1667C13.6559 18.5667 14.7281 18.1111 15.8503 17.8C16.9725 17.4889 18.1337 17.3333 19.3337 17.3333C20.5337 17.3333 21.6948 17.4889 22.817 17.8C23.9392 18.1111 25.0114 18.5667 26.0337 19.1667C26.4337 19.4111 26.7503 19.7389 26.9837 20.15C27.217 20.5611 27.3337 21 27.3337 21.4667V24H11.3337ZM14.2003 21.3333H24.467C23.6892 20.8889 22.867 20.5556 22.0003 20.3333C21.1337 20.1111 20.2448 20 19.3337 20C18.4225 20 17.5337 20.1111 16.667 20.3333C15.8003 20.5556 14.9781 20.8889 14.2003 21.3333ZM19.3337 16C18.2225 16 17.2781 15.6111 16.5003 14.8333C15.7225 14.0556 15.3337 13.1111 15.3337 12C15.3337 10.8889 15.7225 9.94444 16.5003 9.16667C17.2781 8.38889 18.2225 8 19.3337 8C20.4448 8 21.3892 8.38889 22.167 9.16667C22.9448 9.94444 23.3337 10.8889 23.3337 12C23.3337 13.1111 22.9448 14.0556 22.167 14.8333C21.3892 15.6111 20.4448 16 19.3337 16ZM19.3337 13.3333C19.7114 13.3333 20.0281 13.2056 20.2837 12.95C20.5392 12.6944 20.667 12.3778 20.667 12C20.667 11.6222 20.5392 11.3056 20.2837 11.05C20.0281 10.7944 19.7114 10.6667 19.3337 10.6667C18.9559 10.6667 18.6392 10.7944 18.3837 11.05C18.1281 11.3056 18.0003 11.6222 18.0003 12C18.0003 12.3778 18.1281 12.6944 18.3837 12.95C18.6392 13.2056 18.9559 13.3333 19.3337 13.3333Z" fill="#091029"/>
</svg></span>'.esc_html__('Address','ov-booking').'</span><span>'.esc_html($other['address']).'</span></li>';
              if ($other['city'])     echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico"><span class="dashicons dashicons-building"></span></span>'.esc_html__('City','ov-booking').'</span><span>'.esc_html($other['city']).'</span></li>';
              if ($other['postcode']) echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M6.00033 16.6667V11.3334H3.33366V24.6667H24.667V11.3334H11.3337V8.66675H24.667C25.4003 8.66675 26.0281 8.92786 26.5503 9.45008C27.0725 9.9723 27.3337 10.6001 27.3337 11.3334V24.6667C27.3337 25.4001 27.0725 26.0279 26.5503 26.5501C26.0281 27.0723 25.4003 27.3334 24.667 27.3334H3.33366C2.60033 27.3334 1.97255 27.0723 1.45033 26.5501C0.928103 26.0279 0.666992 25.4001 0.666992 24.6667V11.3334C0.666992 10.6001 0.928103 9.9723 1.45033 9.45008C1.97255 8.92786 2.60033 8.66675 3.33366 8.66675H6.00033V0.666748H16.667V6.00008H8.66699V16.6667H6.00033Z" fill="#091029"/>
</svg>
</span>'.esc_html__('Postcode','ov-booking').'</span><span>'.esc_html($other['postcode']).'</span></li>';
              if ($other['id'])       echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="28" height="22" viewBox="0 0 28 22" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M16.667 12.3334H23.3337V9.66671H16.667V12.3334ZM16.667 8.33337H23.3337V5.66671H16.667V8.33337ZM4.66699 16.3334H15.3337V15.6C15.3337 14.6 14.8448 13.8056 13.867 13.2167C12.8892 12.6278 11.6003 12.3334 10.0003 12.3334C8.40032 12.3334 7.11144 12.6278 6.13366 13.2167C5.15588 13.8056 4.66699 14.6 4.66699 15.6V16.3334ZM10.0003 11C10.7337 11 11.3614 10.7389 11.8837 10.2167C12.4059 9.69449 12.667 9.06671 12.667 8.33337C12.667 7.60004 12.4059 6.97226 11.8837 6.45004C11.3614 5.92782 10.7337 5.66671 10.0003 5.66671C9.26699 5.66671 8.63921 5.92782 8.11699 6.45004C7.59477 6.97226 7.33366 7.60004 7.33366 8.33337C7.33366 9.06671 7.59477 9.69449 8.11699 10.2167C8.63921 10.7389 9.26699 11 10.0003 11ZM3.33366 21.6667C2.60033 21.6667 1.97255 21.4056 1.45033 20.8834C0.928103 20.3612 0.666992 19.7334 0.666992 19V3.00004C0.666992 2.26671 0.928103 1.63893 1.45033 1.11671C1.97255 0.594485 2.60033 0.333374 3.33366 0.333374H24.667C25.4003 0.333374 26.0281 0.594485 26.5503 1.11671C27.0725 1.63893 27.3337 2.26671 27.3337 3.00004V19C27.3337 19.7334 27.0725 20.3612 26.5503 20.8834C26.0281 21.4056 25.4003 21.6667 24.667 21.6667H3.33366ZM3.33366 19H24.667V3.00004H3.33366V19Z" fill="black"/>
</svg></span>'.esc_html__('Passport/ID','ov-booking').'</span><span>'.esc_html($other['id']).'</span></li>';
              if ($other['dob'])      echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="27" height="30" viewBox="0 0 27 30" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M18.6667 26C17.5556 26 16.6111 25.6111 15.8333 24.8333C15.0556 24.0555 14.6667 23.1111 14.6667 22C14.6667 20.8888 15.0556 19.9444 15.8333 19.1666C16.6111 18.3888 17.5556 18 18.6667 18C19.7778 18 20.7222 18.3888 21.5 19.1666C22.2778 19.9444 22.6667 20.8888 22.6667 22C22.6667 23.1111 22.2778 24.0555 21.5 24.8333C20.7222 25.6111 19.7778 26 18.6667 26ZM17.6667 16.6666V14H19.6667V16.6666H17.6667ZM17.6667 30V27.3333H19.6667V30H17.6667ZM23.1667 18.9L21.7333 17.5L23.6333 15.6L25.0333 17.0333L23.1667 18.9ZM13.7 28.3666L12.3 26.9666L14.2 25.0666L15.6 26.4666L13.7 28.3666ZM24 23V21H26.6667V23H24ZM10.6667 23V21H13.3333V23H10.6667ZM23.6333 28.3666L21.7667 26.4666L23.1667 25.0666L25.0667 26.9333L23.6333 28.3666ZM14.1667 18.9333L12.3 17.0333L13.7 15.6333L15.6 17.5L14.1667 18.9333ZM2.66667 27.3333C1.93333 27.3333 1.30556 27.0722 0.783333 26.55C0.261111 26.0277 0 25.4 0 24.6666V5.99996C0 5.26663 0.261111 4.63885 0.783333 4.11663C1.30556 3.5944 1.93333 3.33329 2.66667 3.33329H4V0.666626H6.66667V3.33329H17.3333V0.666626H20V3.33329H21.3333C22.0667 3.33329 22.6944 3.5944 23.2167 4.11663C23.7389 4.63885 24 5.26663 24 5.99996V11.3333H2.66667V24.6666H8V27.3333H2.66667ZM2.66667 8.66663H21.3333V5.99996H2.66667V8.66663Z" fill="black"/>
</svg>
</span>'.esc_html__('Date of birth','ov-booking').'</span><span>'.esc_html(ovb_frmt_date($other['dob'])).'</span></li>';

              echo '</ul>';
              echo '</div>';
          }

          // Additional guests
          if ($has_extra) {
              $extra_count = count($extra);
              $use3 = ($extra_count % 3) === 0 && $extra_count >= 3; // 3,6,9...
              $ag_class = 'ovb-ag '.($use3 ? 'ovb-ag--3' : 'ovb-ag--2');
              if ($extra_count === 1) $ag_class .= ' ovb-ag--single';

              echo '<div class="ovb-card">';
             echo '<h3 class="ovb-title-lg">'.esc_html__('Additional guests','ov-booking').'</h3>';

            //   if ($extra_count > 1) {
            //         echo '<h3>'.esc_html__('Additional guests','ov-booking').'</h3>';
            //     }
              echo '<div class="'.esc_attr($ag_class).'">';

              foreach ($extra as $i => $g) {
                  $name = trim(($g['fn'] ?? '').' '.($g['ln'] ?? ''));
                  echo '<div class="ovb-guest">';
                 echo '<h3 class="ovb-title-guest">'.sprintf(esc_html__('Guest #%d','ov-booking'), ($i+1)).'</h3>';

                  echo '<ul class="ovb-list">';
                  if ($name)               echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M10.9997 10.9999C9.53301 10.9999 8.27745 10.4777 7.23301 9.43325C6.18856 8.38881 5.66634 7.13325 5.66634 5.66659C5.66634 4.19992 6.18856 2.94436 7.23301 1.89992C8.27745 0.855474 9.53301 0.333252 10.9997 0.333252C12.4663 0.333252 13.7219 0.855474 14.7663 1.89992C15.8108 2.94436 16.333 4.19992 16.333 5.66659C16.333 7.13325 15.8108 8.38881 14.7663 9.43325C13.7219 10.4777 12.4663 10.9999 10.9997 10.9999ZM0.333008 21.6666V17.9333C0.333008 17.1777 0.527452 16.4833 0.916341 15.8499C1.30523 15.2166 1.8219 14.7333 2.46634 14.3999C3.84412 13.711 5.24412 13.1944 6.66634 12.8499C8.08856 12.5055 9.53301 12.3333 10.9997 12.3333C12.4663 12.3333 13.9108 12.5055 15.333 12.8499C16.7552 13.1944 18.1552 13.711 19.533 14.3999C20.1775 14.7333 20.6941 15.2166 21.083 15.8499C21.4719 16.4833 21.6663 17.1777 21.6663 17.9333V21.6666H0.333008ZM2.99967 18.9999H18.9997V17.9333C18.9997 17.6888 18.9386 17.4666 18.8163 17.2666C18.6941 17.0666 18.533 16.911 18.333 16.7999C17.133 16.1999 15.9219 15.7499 14.6997 15.4499C13.4775 15.1499 12.2441 14.9999 10.9997 14.9999C9.75523 14.9999 8.5219 15.1499 7.29967 15.4499C6.07745 15.7499 4.86634 16.1999 3.66634 16.7999C3.46634 16.911 3.30523 17.0666 3.18301 17.2666C3.06079 17.4666 2.99967 17.6888 2.99967 17.9333V18.9999ZM10.9997 8.33325C11.733 8.33325 12.3608 8.07214 12.883 7.54992C13.4052 7.0277 13.6663 6.39992 13.6663 5.66659C13.6663 4.93325 13.4052 4.30547 12.883 3.78325C12.3608 3.26103 11.733 2.99992 10.9997 2.99992C10.2663 2.99992 9.63856 3.26103 9.11634 3.78325C8.59412 4.30547 8.33301 4.93325 8.33301 5.66659C8.33301 6.39992 8.59412 7.0277 9.11634 7.54992C9.63856 8.07214 10.2663 8.33325 10.9997 8.33325Z" fill="#091029"/>
</svg></span>'.esc_html__('Customer name','ov-booking').'</span><span>'.esc_html($name).'</span></li>';
                  if (($g['gd']??'')!=='') echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M10.9997 10.9999C9.53301 10.9999 8.27745 10.4777 7.23301 9.43325C6.18856 8.38881 5.66634 7.13325 5.66634 5.66659C5.66634 4.19992 6.18856 2.94436 7.23301 1.89992C8.27745 0.855474 9.53301 0.333252 10.9997 0.333252C12.4663 0.333252 13.7219 0.855474 14.7663 1.89992C15.8108 2.94436 16.333 4.19992 16.333 5.66659C16.333 7.13325 15.8108 8.38881 14.7663 9.43325C13.7219 10.4777 12.4663 10.9999 10.9997 10.9999ZM0.333008 21.6666V17.9333C0.333008 17.1777 0.527452 16.4833 0.916341 15.8499C1.30523 15.2166 1.8219 14.7333 2.46634 14.3999C3.84412 13.711 5.24412 13.1944 6.66634 12.8499C8.08856 12.5055 9.53301 12.3333 10.9997 12.3333C12.4663 12.3333 13.9108 12.5055 15.333 12.8499C16.7552 13.1944 18.1552 13.711 19.533 14.3999C20.1775 14.7333 20.6941 15.2166 21.083 15.8499C21.4719 16.4833 21.6663 17.1777 21.6663 17.9333V21.6666H0.333008ZM2.99967 18.9999H18.9997V17.9333C18.9997 17.6888 18.9386 17.4666 18.8163 17.2666C18.6941 17.0666 18.533 16.911 18.333 16.7999C17.133 16.1999 15.9219 15.7499 14.6997 15.4499C13.4775 15.1499 12.2441 14.9999 10.9997 14.9999C9.75523 14.9999 8.5219 15.1499 7.29967 15.4499C6.07745 15.7499 4.86634 16.1999 3.66634 16.7999C3.46634 16.911 3.30523 17.0666 3.18301 17.2666C3.06079 17.4666 2.99967 17.6888 2.99967 17.9333V18.9999ZM10.9997 8.33325C11.733 8.33325 12.3608 8.07214 12.883 7.54992C13.4052 7.0277 13.6663 6.39992 13.6663 5.66659C13.6663 4.93325 13.4052 4.30547 12.883 3.78325C12.3608 3.26103 11.733 2.99992 10.9997 2.99992C10.2663 2.99992 9.63856 3.26103 9.11634 3.78325C8.59412 4.30547 8.33301 4.93325 8.33301 5.66659C8.33301 6.39992 8.59412 7.0277 9.11634 7.54992C9.63856 8.07214 10.2663 8.33325 10.9997 8.33325Z" fill="#091029"/>
</svg></span>'.esc_html__('Gender','ov-booking').'</span><span>'.esc_html($g['gd']).'</span></li>';
                  if (($g['db']??'')!=='') echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="27" height="30" viewBox="0 0 27 30" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M18.6667 26C17.5556 26 16.6111 25.6111 15.8333 24.8333C15.0556 24.0555 14.6667 23.1111 14.6667 22C14.6667 20.8888 15.0556 19.9444 15.8333 19.1666C16.6111 18.3888 17.5556 18 18.6667 18C19.7778 18 20.7222 18.3888 21.5 19.1666C22.2778 19.9444 22.6667 20.8888 22.6667 22C22.6667 23.1111 22.2778 24.0555 21.5 24.8333C20.7222 25.6111 19.7778 26 18.6667 26ZM17.6667 16.6666V14H19.6667V16.6666H17.6667ZM17.6667 30V27.3333H19.6667V30H17.6667ZM23.1667 18.9L21.7333 17.5L23.6333 15.6L25.0333 17.0333L23.1667 18.9ZM13.7 28.3666L12.3 26.9666L14.2 25.0666L15.6 26.4666L13.7 28.3666ZM24 23V21H26.6667V23H24ZM10.6667 23V21H13.3333V23H10.6667ZM23.6333 28.3666L21.7667 26.4666L23.1667 25.0666L25.0667 26.9333L23.6333 28.3666ZM14.1667 18.9333L12.3 17.0333L13.7 15.6333L15.6 17.5L14.1667 18.9333ZM2.66667 27.3333C1.93333 27.3333 1.30556 27.0722 0.783333 26.55C0.261111 26.0277 0 25.4 0 24.6666V5.99996C0 5.26663 0.261111 4.63885 0.783333 4.11663C1.30556 3.5944 1.93333 3.33329 2.66667 3.33329H4V0.666626H6.66667V3.33329H17.3333V0.666626H20V3.33329H21.3333C22.0667 3.33329 22.6944 3.5944 23.2167 4.11663C23.7389 4.63885 24 5.26663 24 5.99996V11.3333H2.66667V24.6666H8V27.3333H2.66667ZM2.66667 8.66663H21.3333V5.99996H2.66667V8.66663Z" fill="black"/>
</svg>
</span>'.esc_html__('Date of birth','ov-booking').'</span><span>'.esc_html(ovb_frmt_date($g['db'])).'</span></li>';
                 if (!empty($g['ph'])) {
                    $raw_ph = (string) $g['ph'];
                        $tel_ph = preg_replace('/[^0-9+]/', '', $raw_ph); // očisti za tel:
                        echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M22.6 24C19.8222 24 17.0778 23.3944 14.3667 22.1833C11.6556 20.9722 9.18889 19.2556 6.96667 17.0333C4.74444 14.8111 3.02778 12.3444 1.81667 9.63333C0.605556 6.92222 0 4.17778 0 1.4C0 1 0.133333 0.666667 0.4 0.4C0.666667 0.133333 1 0 1.4 0H6.8C7.11111 0 7.38889 0.105556 7.63333 0.316667C7.87778 0.527778 8.02222 0.777778 8.06667 1.06667L8.93333 5.73333C8.97778 6.08889 8.96667 6.38889 8.9 6.63333C8.83333 6.87778 8.71111 7.08889 8.53333 7.26667L5.3 10.5333C5.74444 11.3556 6.27222 12.15 6.88333 12.9167C7.49444 13.6833 8.16667 14.4222 8.9 15.1333C9.58889 15.8222 10.3111 16.4611 11.0667 17.05C11.8222 17.6389 12.6222 18.1778 13.4667 18.6667L16.6 15.5333C16.8 15.3333 17.0611 15.1833 17.3833 15.0833C17.7056 14.9833 18.0222 14.9556 18.3333 15L22.9333 15.9333C23.2444 16.0222 23.5 16.1833 23.7 16.4167C23.9 16.65 24 16.9111 24 17.2V22.6C24 23 23.8667 23.3333 23.6 23.6C23.3333 23.8667 23 24 22.6 24ZM4.03333 8L6.23333 5.8L5.66667 2.66667H2.7C2.81111 3.57778 2.96667 4.47778 3.16667 5.36667C3.36667 6.25556 3.65556 7.13333 4.03333 8ZM15.9667 19.9333C16.8333 20.3111 17.7167 20.6111 18.6167 20.8333C19.5167 21.0556 20.4222 21.2 21.3333 21.2667V18.3333L18.2 17.7L15.9667 19.9333Z" fill="#091029"/>
</svg></span>'
                            . esc_html__('Phone','ov-booking')
                            . '</span><a href="tel:' . esc_attr($tel_ph) . '">'
                            . esc_html($raw_ph)
                            . '</a></li>';
                    }
                  if (($g['pp']??'')!=='') echo '<li class="ovb-row"><span class="ovb-lab">
<svg width="28" height="22" viewBox="0 0 28 22" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M16.667 12.3334H23.3337V9.66671H16.667V12.3334ZM16.667 8.33337H23.3337V5.66671H16.667V8.33337ZM4.66699 16.3334H15.3337V15.6C15.3337 14.6 14.8448 13.8056 13.867 13.2167C12.8892 12.6278 11.6003 12.3334 10.0003 12.3334C8.40032 12.3334 7.11144 12.6278 6.13366 13.2167C5.15588 13.8056 4.66699 14.6 4.66699 15.6V16.3334ZM10.0003 11C10.7337 11 11.3614 10.7389 11.8837 10.2167C12.4059 9.69449 12.667 9.06671 12.667 8.33337C12.667 7.60004 12.4059 6.97226 11.8837 6.45004C11.3614 5.92782 10.7337 5.66671 10.0003 5.66671C9.26699 5.66671 8.63921 5.92782 8.11699 6.45004C7.59477 6.97226 7.33366 7.60004 7.33366 8.33337C7.33366 9.06671 7.59477 9.69449 8.11699 10.2167C8.63921 10.7389 9.26699 11 10.0003 11ZM3.33366 21.6667C2.60033 21.6667 1.97255 21.4056 1.45033 20.8834C0.928103 20.3612 0.666992 19.7334 0.666992 19V3.00004C0.666992 2.26671 0.928103 1.63893 1.45033 1.11671C1.97255 0.594485 2.60033 0.333374 3.33366 0.333374H24.667C25.4003 0.333374 26.0281 0.594485 26.5503 1.11671C27.0725 1.63893 27.3337 2.26671 27.3337 3.00004V19C27.3337 19.7334 27.0725 20.3612 26.5503 20.8834C26.0281 21.4056 25.4003 21.6667 24.667 21.6667H3.33366ZM3.33366 19H24.667V3.00004H3.33366V19Z" fill="black"/>
</svg>
</span>'.esc_html__('Passport/ID','ov-booking').'</span><span>'.esc_html($g['pp']).'</span></li>';
                  echo '</ul>';
                  echo '</div>';
              }

              echo '</div>'; // /.ovb-ag
              echo '</div>'; // /Additional guests card
          }

        echo '</div>'; // /.ovb-guests-row
    }

    // === BOOKING SUMMARY — UVEK ZASEBNA KARTICA ===
    echo '<div class="ovb-card" style="margin-top:20px">';
    echo '<h3 style="font-size:26px">'.esc_html__('Booking summary','ov-booking').'</h3>';
    echo '<div class="ovb-summary">';

      // Levo
      echo '<div class="ovb-s-left">';
      if ($thumb) echo '<img src="'.esc_url($thumb).'" alt="">';
      echo '<div>';
      if ($p_title) {
          $title_html = $p_link ? '<a href="'.esc_url($p_link).'" style="color:var(--ovb-accent);font-size:24px; text-decoration:underline;">'.esc_html($p_title).'</a>' : esc_html($p_title);
          echo '<div style="margin:0 0 8px;font-weight:700;">'. $title_html . '</div>';
      }
      echo '<ul class="ovb-list">';
      if ($check_in)  echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="27" height="30" viewBox="0 0 27 30" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M18.6667 26C17.5556 26 16.6111 25.6111 15.8333 24.8333C15.0556 24.0555 14.6667 23.1111 14.6667 22C14.6667 20.8888 15.0556 19.9444 15.8333 19.1666C16.6111 18.3888 17.5556 18 18.6667 18C19.7778 18 20.7222 18.3888 21.5 19.1666C22.2778 19.9444 22.6667 20.8888 22.6667 22C22.6667 23.1111 22.2778 24.0555 21.5 24.8333C20.7222 25.6111 19.7778 26 18.6667 26ZM17.6667 16.6666V14H19.6667V16.6666H17.6667ZM17.6667 30V27.3333H19.6667V30H17.6667ZM23.1667 18.9L21.7333 17.5L23.6333 15.6L25.0333 17.0333L23.1667 18.9ZM13.7 28.3666L12.3 26.9666L14.2 25.0666L15.6 26.4666L13.7 28.3666ZM24 23V21H26.6667V23H24ZM10.6667 23V21H13.3333V23H10.6667ZM23.6333 28.3666L21.7667 26.4666L23.1667 25.0666L25.0667 26.9333L23.6333 28.3666ZM14.1667 18.9333L12.3 17.0333L13.7 15.6333L15.6 17.5L14.1667 18.9333ZM2.66667 27.3333C1.93333 27.3333 1.30556 27.0722 0.783333 26.55C0.261111 26.0277 0 25.4 0 24.6666V5.99996C0 5.26663 0.261111 4.63885 0.783333 4.11663C1.30556 3.5944 1.93333 3.33329 2.66667 3.33329H4V0.666626H6.66667V3.33329H17.3333V0.666626H20V3.33329H21.3333C22.0667 3.33329 22.6944 3.5944 23.2167 4.11663C23.7389 4.63885 24 5.26663 24 5.99996V11.3333H2.66667V24.6666H8V27.3333H2.66667ZM2.66667 8.66663H21.3333V5.99996H2.66667V8.66663Z" fill="black"/>
</svg>
</span>'.esc_html__('Monday check-in','ov-booking').'</span><span>'.esc_html(date_i18n(get_option('date_format'), strtotime($check_in))).'</span></li>';
      if ($check_out) echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="27" height="30" viewBox="0 0 27 30" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M18.6667 26C17.5556 26 16.6111 25.6111 15.8333 24.8333C15.0556 24.0555 14.6667 23.1111 14.6667 22C14.6667 20.8888 15.0556 19.9444 15.8333 19.1666C16.6111 18.3888 17.5556 18 18.6667 18C19.7778 18 20.7222 18.3888 21.5 19.1666C22.2778 19.9444 22.6667 20.8888 22.6667 22C22.6667 23.1111 22.2778 24.0555 21.5 24.8333C20.7222 25.6111 19.7778 26 18.6667 26ZM17.6667 16.6666V14H19.6667V16.6666H17.6667ZM17.6667 30V27.3333H19.6667V30H17.6667ZM23.1667 18.9L21.7333 17.5L23.6333 15.6L25.0333 17.0333L23.1667 18.9ZM13.7 28.3666L12.3 26.9666L14.2 25.0666L15.6 26.4666L13.7 28.3666ZM24 23V21H26.6667V23H24ZM10.6667 23V21H13.3333V23H10.6667ZM23.6333 28.3666L21.7667 26.4666L23.1667 25.0666L25.0667 26.9333L23.6333 28.3666ZM14.1667 18.9333L12.3 17.0333L13.7 15.6333L15.6 17.5L14.1667 18.9333ZM2.66667 27.3333C1.93333 27.3333 1.30556 27.0722 0.783333 26.55C0.261111 26.0277 0 25.4 0 24.6666V5.99996C0 5.26663 0.261111 4.63885 0.783333 4.11663C1.30556 3.5944 1.93333 3.33329 2.66667 3.33329H4V0.666626H6.66667V3.33329H17.3333V0.666626H20V3.33329H21.3333C22.0667 3.33329 22.6944 3.5944 23.2167 4.11663C23.7389 4.63885 24 5.26663 24 5.99996V11.3333H2.66667V24.6666H8V27.3333H2.66667ZM2.66667 8.66663H21.3333V5.99996H2.66667V8.66663Z" fill="black"/>
</svg>
</span>'.esc_html__('Friday check-out','ov-booking').'</span><span>'.esc_html(date_i18n(get_option('date_format'), strtotime($check_out))).'</span></li>';
      if ($nights)    echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="30" height="21" viewBox="0 0 30 21" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M0.333008 20.3333V0.333252H2.99967V13.6666H13.6663V2.99992H24.333C25.7997 2.99992 27.0552 3.52214 28.0997 4.56659C29.1441 5.61103 29.6663 6.86659 29.6663 8.33325V20.3333H26.9997V16.3333H2.99967V20.3333H0.333008ZM8.33301 12.3333C7.2219 12.3333 6.27745 11.9444 5.49967 11.1666C4.7219 10.3888 4.33301 9.44436 4.33301 8.33325C4.33301 7.22214 4.7219 6.2777 5.49967 5.49992C6.27745 4.72214 7.2219 4.33325 8.33301 4.33325C9.44412 4.33325 10.3886 4.72214 11.1663 5.49992C11.9441 6.2777 12.333 7.22214 12.333 8.33325C12.333 9.44436 11.9441 10.3888 11.1663 11.1666C10.3886 11.9444 9.44412 12.3333 8.33301 12.3333ZM16.333 13.6666H26.9997V8.33325C26.9997 7.59992 26.7386 6.97214 26.2163 6.44992C25.6941 5.9277 25.0663 5.66659 24.333 5.66659H16.333V13.6666ZM8.33301 9.66659C8.71079 9.66659 9.02745 9.53881 9.28301 9.28325C9.53856 9.0277 9.66634 8.71103 9.66634 8.33325C9.66634 7.95547 9.53856 7.63881 9.28301 7.38325C9.02745 7.1277 8.71079 6.99992 8.33301 6.99992C7.95523 6.99992 7.63856 7.1277 7.38301 7.38325C7.12745 7.63881 6.99967 7.95547 6.99967 8.33325C6.99967 8.71103 7.12745 9.0277 7.38301 9.28325C7.63856 9.53881 7.95523 9.66659 8.33301 9.66659Z" fill="#091029"/>
</svg>
</span>'.esc_html__('Nights booked','ov-booking').'</span><span>'.esc_html($nights).'</span></li>';
      echo '<li class="ovb-row"><span class="ovb-lab"><span class="ovb-ico">
<svg width="32" height="16" viewBox="0 0 32 16" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M0 16V13.9C0 12.9444 0.488889 12.1667 1.46667 11.5667C2.44444 10.9667 3.73333 10.6667 5.33333 10.6667C5.62222 10.6667 5.9 10.6722 6.16667 10.6833C6.43333 10.6944 6.68889 10.7222 6.93333 10.7667C6.62222 11.2333 6.38889 11.7222 6.23333 12.2333C6.07778 12.7444 6 13.2778 6 13.8333V16H0ZM8 16V13.8333C8 13.1222 8.19444 12.4722 8.58333 11.8833C8.97222 11.2944 9.52222 10.7778 10.2333 10.3333C10.9444 9.88889 11.7944 9.55556 12.7833 9.33333C13.7722 9.11111 14.8444 9 16 9C17.1778 9 18.2611 9.11111 19.25 9.33333C20.2389 9.55556 21.0889 9.88889 21.8 10.3333C22.5111 10.7778 23.0556 11.2944 23.4333 11.8833C23.8111 12.4722 24 13.1222 24 13.8333V16H8ZM26 16V13.8333C26 13.2556 25.9278 12.7111 25.7833 12.2C25.6389 11.6889 25.4222 11.2111 25.1333 10.7667C25.3778 10.7222 25.6278 10.6944 25.8833 10.6833C26.1389 10.6722 26.4 10.6667 26.6667 10.6667C28.2667 10.6667 29.5556 10.9611 30.5333 11.55C31.5111 12.1389 32 12.9222 32 13.9V16H26ZM10.8333 13.3333H21.2C20.9778 12.8889 20.3611 12.5 19.35 12.1667C18.3389 11.8333 17.2222 11.6667 16 11.6667C14.7778 11.6667 13.6611 11.8333 12.65 12.1667C11.6389 12.5 11.0333 12.8889 10.8333 13.3333ZM5.33333 9.33333C4.6 9.33333 3.97222 9.07222 3.45 8.55C2.92778 8.02778 2.66667 7.4 2.66667 6.66667C2.66667 5.91111 2.92778 5.27778 3.45 4.76667C3.97222 4.25556 4.6 4 5.33333 4C6.08889 4 6.72222 4.25556 7.23333 4.76667C7.74444 5.27778 8 5.91111 8 6.66667C8 7.4 7.74444 8.02778 7.23333 8.55C6.72222 9.07222 6.08889 9.33333 5.33333 9.33333ZM26.6667 9.33333C25.9333 9.33333 25.3056 9.07222 24.7833 8.55C24.2611 8.02778 24 7.4 24 6.66667C24 5.91111 24.2611 5.27778 24.7833 4.76667C25.3056 4.25556 25.9333 4 26.6667 4C27.4222 4 28.0556 4.25556 28.5667 4.76667C29.0778 5.27778 29.3333 5.91111 29.3333 6.66667C29.3333 7.4 29.0778 8.02778 28.5667 8.55C28.0556 9.07222 27.4222 9.33333 26.6667 9.33333ZM16 8C14.8889 8 13.9444 7.61111 13.1667 6.83333C12.3889 6.05556 12 5.11111 12 4C12 2.86667 12.3889 1.91667 13.1667 1.15C13.9444 0.383333 14.8889 0 16 0C17.1333 0 18.0833 0.383333 18.85 1.15C19.6167 1.91667 20 2.86667 20 4C20 5.11111 19.6167 6.05556 18.85 6.83333C18.0833 7.61111 17.1333 8 16 8ZM16 5.33333C16.3778 5.33333 16.6944 5.20556 16.95 4.95C17.2056 4.69444 17.3333 4.37778 17.3333 4C17.3333 3.62222 17.2056 3.30556 16.95 3.05C16.6944 2.79444 16.3778 2.66667 16 2.66667C15.6222 2.66667 15.3056 2.79444 15.05 3.05C14.7944 3.30556 14.6667 3.62222 14.6667 4C14.6667 4.37778 14.7944 4.69444 15.05 4.95C15.3056 5.20556 15.6222 5.33333 16 5.33333Z" fill="#091029"/>
</svg></span>'.esc_html__('Number of guests','ov-booking').'</span><span>'.esc_html($guests_total).'</span></li>';
      echo '</ul>';
      echo '</div></div>'; // /left

      // Desno (Subtotal)
      echo '<div class="ovb-subtitle">';
      echo '<h4>'.esc_html__('Subtotal','ov-booking').'</h4>';
      echo '<ul class="ovb-list">';
    // Price per night – prikaži listu ako su cene po noći različite
if ($nights > 0) {

    $nightly = ovb_order_nightly_prices($order);

    if (!empty($nightly)) {
        // Prikaži jedinstvene vrednosti (npr. 10,00 €, 20,00 €)
        $uniq = array_values(array_unique(array_map(function($v){
            return number_format((float)$v, 2, '.', '');
        }, $nightly)));

        if (count($uniq) === 1) {
            $ppn = (float) $uniq[0];
            echo '<li class="ovb-row"><span class="ovb-lab">'.esc_html__('Price per night','ov-booking').'</span><span>'.wc_price($ppn, ['currency'=>$order->get_currency()]).'</span></li>';
        } else {
            $formatted = [];
            foreach ($uniq as $v) {
                $formatted[] = wc_price((float)$v, ['currency'=>$order->get_currency()]);
            }
            echo '<li class="ovb-row"><span class="ovb-lab">'.esc_html__('Price per night','ov-booking').'</span><span>'.implode(', ', $formatted).'</span></li>';
        }

    } else {
        // Fallback: stari prosek ako nema raspada po noćima u metama
        $ppn = $first_item_total_inc > 0 ? ($first_item_total_inc / $nights) : ($order->get_total() / $nights);
        echo '<li class="ovb-row"><span class="ovb-lab">'.esc_html__('Price per night','ov-booking').'</span><span>'.wc_price($ppn, ['currency' => $order->get_currency()]).'</span></li>';
    }

    echo '<li class="ovb-row"><span class="ovb-lab">'.esc_html__('Nights booked','ov-booking').'</span><span>'.esc_html($nights).'</span></li>';
}
      $vat_amount = (float)$order->get_total_tax();
      echo '<li class="ovb-row"><span class="ovb-lab">VAT</span><span>'.wc_price($vat_amount, ['currency' => $order->get_currency()]).'</span></li>';
      echo '<li class="ovb-row"><span class="ovb-lab">'.esc_html__('Total','ov-booking').'</span><span>'.wp_kses_post($order->get_formatted_order_total()).'</span></li>';
      echo '</ul>';
      echo '</div>'; // /right

    echo '</div>'; // /summary grid
    echo '</div>'; // /summary card

    echo '</div>'; // /.ovb-cards-wrap
}, 8);

// novi render

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