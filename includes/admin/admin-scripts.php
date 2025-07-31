<?php
defined('ABSPATH') || exit;

/**
 * OV Booking Admin Scripts (ONLY enqueue admin booking/calendar scripts & styles)
 */

// Loader za logger ako treba
if (file_exists(dirname(__DIR__, 2) . '/helpers/logger.php')) {
    require_once dirname(__DIR__, 2) . '/helpers/logger.php';
}

add_action('admin_enqueue_scripts', 'ovb_admin_enqueue_calendar_assets');
function ovb_admin_enqueue_calendar_assets($hook) {
    // Samo na edit/add Product page!
    $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
    $post_obj = $post_id ? get_post($post_id) : null;
    if (!in_array($hook, ['post.php', 'post-new.php']) || !$post_obj || $post_obj->post_type !== 'product') return;

    // Core: daterangepicker + moment + sweetalert2 (CDN, dupe-protect)
    if (!wp_script_is('moment-js', 'enqueued')) {
        wp_enqueue_script('moment-js', 'https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js', [], '2.29.4', false);
    }
    if (!wp_script_is('daterangepicker-js', 'enqueued')) {
        wp_enqueue_script('daterangepicker-js', 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js', ['jquery', 'moment-js'], '3.1.0', false);
        wp_enqueue_style('daterangepicker-css', 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css', [], '3.1.0');
    }
    if (!wp_script_is('sweetalert2-js', 'enqueued')) {
        wp_enqueue_style('sweetalert2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.1/dist/sweetalert2.min.css', [], '11.22.1');
        wp_enqueue_script('sweetalert2-js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.1/dist/sweetalert2.all.min.js', [], '11.22.1', true);
    }

    // Custom admin kalendar (proveri path da odgovara strukturi)
    $admin_css = OVB_BOOKING_PATH . 'includes/admin/admin-calendar/admin-calendar.css';
    $admin_js  = OVB_BOOKING_PATH . 'includes/admin/admin-calendar/admin-calendar.js';
    if (file_exists($admin_css) && !wp_style_is('ovb-admin-calendar-style', 'enqueued')) {
        wp_enqueue_style('ovb-admin-calendar-style', OVB_BOOKING_URL . 'includes/admin/admin-calendar/admin-calendar.css', [], filemtime($admin_css) ?: time());
    }
    if (file_exists($admin_js) && !wp_script_is('ovb-admin-calendar-script', 'enqueued')) {
        wp_enqueue_script('ovb-admin-calendar-script', OVB_BOOKING_URL . 'includes/admin/admin-calendar/admin-calendar.js', ['jquery', 'daterangepicker-js', 'sweetalert2-js'], filemtime($admin_js) ?: time(), false);

        // 🔥 ISPRAVKA: Proper učitavanje podataka iz baze
        $calendar_data = get_post_meta($post_obj->ID, '_ovb_calendar_data', true);
        $price_types = get_post_meta($post_obj->ID, '_ovb_price_types', true);
        
        // Debug logiranje
        if (function_exists('ovb_log_error')) {
            ovb_log_error('Loading calendar data for product ' . $post_obj->ID . ': ' . (is_array($calendar_data) ? count($calendar_data) . ' entries' : 'not array'), 'admin-scripts');
            ovb_log_error('Loading raw price types: ' . print_r($price_types, true), 'admin-scripts');
        }
        
        // Ensure proper fallbacks
        if (!is_array($calendar_data)) {
            $calendar_data = [];
        }
        
        if (!is_array($price_types)) {
            $price_types = [
                'regular' => 0,
                'weekend' => 0,
                'discount' => 0,
                'custom' => 0
            ];
        } else {
            // Ensure all price types exist with proper defaults
            $default_price_types = [
                'regular' => 0,
                'weekend' => 0,
                'discount' => 0,
                'custom' => 0
            ];
            $price_types = array_merge($default_price_types, $price_types);
            
            // 🔥 KLJUČNA ISPRAVKA: Force numeric conversion for wp_localize_script
            foreach ($price_types as $key => $value) {
                if ($value === '' || $value === null || $value === false) {
                    $price_types[$key] = 0;
                } else {
                    $price_types[$key] = (float) $value;
                }
            }
        }

        // Debug final data before sending to JS
        if (function_exists('ovb_log_error')) {
            ovb_log_error('Final price types for JS: ' . print_r($price_types, true), 'admin-scripts');
        }

        // 🔥 DODATNA ISPRAVKA: Pass data as RAW values, not strings
        wp_localize_script('ovb-admin-calendar-script', 'ovbAdminCalendar', [
            'nonce' => wp_create_nonce('ovb_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'product_id' => $post_obj->ID,
            'priceTypes' => $price_types, // Notice: priceTypes, not price_types
            'calendarData' => $calendar_data, // Notice: calendarData, not calendar_data
            'i18n' => [
                'save_success'   => __('Calendar data saved successfully', 'ov-booking'),
                'save_error'     => __('Failed to save calendar data', 'ov-booking'),
                'loading'        => __('Loading...', 'ov-booking'),
                'confirm_delete' => __('Are you sure you want to delete this booking?', 'ov-booking'),
            ],
        ]);
    }
}

// Ukloni sve ove assete sa drugih admin stranica (admin cleaner)
// add_action('admin_enqueue_scripts', function($hook) {
//     if (!in_array($hook, ['post.php', 'post-new.php'])) {
//         foreach ([
//             'ovb-admin-calendar-script', 'ovb-admin-calendar-style',
//             'sweetalert2-js', 'sweetalert2-css',
//             'daterangepicker-js', 'daterangepicker-css',
//             'moment-js'
//         ] as $handle) {
//             wp_dequeue_script($handle);
//             wp_dequeue_style($handle);
//         }
//     }
// }, 1000);