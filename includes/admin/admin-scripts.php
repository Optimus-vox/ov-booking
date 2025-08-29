<?php
/**
 * OV Booking Admin Scripts (enqueue admin booking/calendar scripts & styles)
 *
 * Swapped out DateRangePicker for custom ov-date.range.js
 */

defined('ABSPATH') || exit;

// Loader za logger ako treba\ nif (file_exists(dirname(__DIR__, 2) . '/helpers/logger.php')) {
    // require_once dirname(__DIR__, 2) . '/helpers/logger.php';


add_action('admin_enqueue_scripts', 'ovb_admin_enqueue_calendar_assets');
/**
 * Enqueue scripts/styles for product edit/add pages
 */
function ovb_admin_enqueue_calendar_assets($hook) {
    // Samo na edit/add Product stranici
    $post_id  = isset($_GET['post']) ? intval($_GET['post']) : 0;
    $post_obj = $post_id ? get_post($post_id) : null;
    if (!in_array($hook, ['post.php', 'post-new.php']) || ! $post_obj || $post_obj->post_type !== 'product') {
        return;
    }

    // Moment.js (CDN, dupe-protect)
    if (!wp_script_is('moment-js', 'enqueued')) {
        wp_enqueue_script(
            'moment-js',
            'https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js',
            [],
            '2.29.4',
            true
        );
    }

    // SweetAlert2 (CDN)
    if (!wp_script_is('sweetalert2-js', 'enqueued')) {
        wp_enqueue_style(
            'sweetalert2-css',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.1/dist/sweetalert2.min.css',
            [],
            '11.22.1'
        );
        wp_enqueue_script(
            'sweetalert2-js',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.1/dist/sweetalert2.all.min.js',
            [],
            '11.22.1',
            true
        );
    }

    // Custom date-range picker (ov-date.range.js)
    $date_range_js = OVB_BOOKING_PATH . 'assets/utils/js/ov-date.range.js';
    if (file_exists($date_range_js) && !wp_script_is('ov-date-range', 'enqueued')) {
        wp_enqueue_script(
            'ov-date-range',
            OVB_BOOKING_URL . 'assets/utils/js/ov-date.range.js',
            ['jquery'],
            filemtime($date_range_js),
            true
        );
    }

    // Custom date-range picker (ov-date.range.css)
    $date_range_css = OVB_BOOKING_PATH . 'assets/utils/css/ov-date.range.css';
    if ( file_exists( $date_range_css ) && ! wp_style_is( 'ov-date-range-style', 'enqueued' ) ) {
        wp_enqueue_style(
            'ov-date-range-style',
            OVB_BOOKING_URL . 'assets/utils/css/ov-date.range.css',
            [],
            filemtime( $date_range_css )
        );
    }

    // Admin calendar styles & script
    $admin_css = OVB_BOOKING_PATH . 'includes/admin/admin-calendar/admin-calendar.css';
    if (file_exists($admin_css) && !wp_style_is('ovb-admin-calendar-style', 'enqueued')) {
        wp_enqueue_style(
            'ovb-admin-calendar-style',
            OVB_BOOKING_URL . 'includes/admin/admin-calendar/admin-calendar.css',
            [],
            filemtime($admin_css)
        );
    }

    $admin_js = OVB_BOOKING_PATH . 'includes/admin/admin-calendar/admin-calendar.js';
    if (file_exists($admin_js) && !wp_script_is('ovb-admin-calendar-script', 'enqueued')) {
        wp_enqueue_script(
            'ovb-admin-calendar-script',
            OVB_BOOKING_URL . 'includes/admin/admin-calendar/admin-calendar.js',
            [ 'jquery', 'moment-js', 'ov-date-range', 'sweetalert2-js' ],
            filemtime($admin_js),
            true
        );

        // Učitavanje podataka iz baze
        $calendar_data = get_post_meta($post_obj->ID, '_ovb_calendar_data', true);
        $price_types   = get_post_meta($post_obj->ID, '_ovb_price_types', true);

        // Sanitizacija i fallback
        if (!is_array($calendar_data)) {
            $calendar_data = [];
        }

        $default_price_types = [
            'regular'  => 0,
            'weekend'  => 0,
            'discount' => 0,
            'custom'   => 0,
        ];
        if (!is_array($price_types)) {
            $price_types = $default_price_types;
        } else {
            $price_types = array_merge($default_price_types, $price_types);
            foreach ($price_types as $key => $value) {
                $price_types[$key] = is_numeric($value) ? (float) $value : 0;
            }
        }

        // Lokalizacija za JS
        wp_localize_script(
            'ovb-admin-calendar-script',
            'ovbAdminCalendar',
            [
                'nonce'        => wp_create_nonce('ovb_nonce'),
                'ajax_url'     => admin_url('admin-ajax.php'),
                'product_id'   => $post_obj->ID,
                'priceTypes'   => $price_types,
                'calendarData' => $calendar_data,
                'i18n'         => [
                    'save_success'   => __('Calendar data saved successfully', 'ov-booking'),
                    'save_error'     => __('Failed to save calendar data', 'ov-booking'),
                    'loading'        => __('Loading...', 'ov-booking'),
                    'confirm_delete' => __('Are you sure you want to delete this booking?', 'ov-booking'),
                ],
            ]
        );
    }
}
