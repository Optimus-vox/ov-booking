<?php
defined('ABSPATH') || exit;

require_once dirname(__DIR__) . '/helpers/logger.php';


// 🧩 Admin Calendar meta box
add_action('add_meta_boxes', 'add_calendar_meta_box');
function add_calendar_meta_box() {
    if (current_user_can('manage_woocommerce')) {
        add_meta_box(
            'product_calendar_meta_box',
            'Kalendar za cenu po danima',
            'render_calendar_meta_box',
            'product',
            'normal',
            'high'
        );
    }
}


// 🗺️ Google Maps meta box
add_action('add_meta_boxes', 'google_maps_iframe_meta_box');
function google_maps_iframe_meta_box() {
    if (current_user_can('manage_woocommerce')) {
        add_meta_box(
            'google_maps_iframe_box',
            'Google Maps Iframe',
            'google_maps_iframe_meta_box_callback',
            'product',
            'normal',
            'default'
        );
    }
}


// helper za datume (edit iz cart u single)
function ovb_generate_all_dates( $start, $end ) {
    $arr = [];
    $current = strtotime( $start );
    $end_ts  = strtotime( $end );
    while ( $current <= $end_ts ) {
        $arr[] = date( 'YYYY-MM-DD' === 'YYYY-MM-DD' ? 'Y-m-d' : 'Y-m-d', $current );
        $current = strtotime( '+1 day', $current );
    }
    return $arr;
}

// 💾 Čuvanje cena prilikom ručnog snimanja proizvoda
add_action('save_post_product', 'ov_save_price_types_meta_box_data');
function ov_save_price_types_meta_box_data($post_id) {
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $types = [
        'regular_price',
        'weekend_price',
        'discount_price',
        'custom_price',
    ];

    $stored_types = [];

    foreach ($types as $field) {
        if (isset($_POST[$field])) {
            $stored_types[str_replace('_price', '', $field)] = floatval($_POST[$field]);
        }
    }

    if (!empty($stored_types)) {
        update_post_meta($post_id, '_ov_price_types', $stored_types);
    }
}


// 💾 Čuvanje statusa dana kada se klikne "Update" dugme na proizvodu
add_action('save_post_product', 'ov_save_bulk_status_rule');
function ov_save_bulk_status_rule($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $bulk_status = sanitize_text_field($_POST['ov_bulk_status'] ?? '');
    $apply_rule = sanitize_text_field($_POST['ov_status_apply_rule'] ?? '');
    $daterange = sanitize_text_field($_POST['ov_status_daterange'] ?? '');

    if (!$bulk_status || !$apply_rule) return;

    $calendar = get_post_meta($post_id, '_ov_calendar_data', true);
    if (!is_array($calendar)) $calendar = [];

    $year = date('Y');
    $month = date('m');
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    for ($i = 1; $i <= $daysInMonth; $i++) {
        $date = sprintf('%s-%02d-%02d', $year, $month, $i);
        $dow = date('w', strtotime($date));

        if (
            ($apply_rule === 'weekdays' && $dow >= 1 && $dow <= 5) ||
            ($apply_rule === 'weekends' && ($dow == 0 || $dow == 6)) ||
            $apply_rule === 'full_month'
        ) {
            $calendar[$date]['status'] = $bulk_status;
        }

        if ($apply_rule === 'custom' && $daterange) {
            [$start, $end] = explode(' - ', $daterange);
            $ts = strtotime($date);
            if ($ts >= strtotime($start) && $ts <= strtotime($end)) {
                $calendar[$date]['status'] = $bulk_status;
            }
        }
    }

    update_post_meta($post_id, '_ov_calendar_data', $calendar);
}

//remove price from shop page

add_action( 'wp', function() {
    if ( function_exists( 'is_shop' ) && is_shop() ) {
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
    }
});

// checkout fix
function ovb_get_checkout_url() {
    $page_id = wc_get_page_id('checkout');
    if ($page_id && get_post_status($page_id) === 'publish') {
        return get_permalink($page_id);
    }

    // Fallback: pokušaj da pronađeš ručno po slug-u
    $page = get_page_by_path('checkout');
    if ($page && get_post_status($page->ID) === 'publish') {
        return get_permalink($page->ID);
    }

    // Total fallback
    return home_url('/checkout/');
}

//remove woo temp button - kasnije razraditi ovo
function ovb_reset_woocommerce_pages() {
    foreach ([
        'woocommerce_cart_page_id',
        'woocommerce_checkout_page_id',
        'woocommerce_myaccount_page_id',
        'woocommerce_shop_page_id',
    ] as $key) {
        delete_option($key);
    }

    if (function_exists('ov_log_error')) {
        ov_log_error('🧹 WooCommerce stranice resetovane ručno iz admina.', 'general');
    }
}

function ovb_sanitize_calendar_data($calendar_data) {
    foreach ($calendar_data as $date => &$data) {
        if (!isset($data['clients']) || !is_array($data['clients'])) {
            $data['clients'] = [];
        }
        if (empty($data['clients'])) {
            $data['status'] = 'available';
            if (isset($data['isLeaving'])) {
                unset($data['isLeaving']);
            }
        }
    }
    unset($data);
}

