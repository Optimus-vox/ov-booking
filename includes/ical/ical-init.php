<?php
defined('ABSPATH') || exit;

// use OVB_iCal_Service;
// use OVB_iCal_Meta;

/**
 * iCal Integration Module
 * -----------------------
 * Ovaj fajl registruje:
 * - Rewrite endpoint za /ical/
 * - Cron import svakih 30 minuta
 * - Hookove za automatski uvoz
 * - Inicijalizaciju iCal klasa
 */

// 1) Registruj query var
add_filter('query_vars', function($vars) {
    $vars[] = 'ical';
    return $vars;
});

// 2) Rewrite rule za /ical/ na proizvodima
add_action('init', function() {
    add_rewrite_rule('^(.+?)/ical/?$', 'index.php?ical=1', 'top');
});

// 3) Cron interval 30 minuta
add_filter('cron_schedules', function($schedules) {
    $schedules['thirty_minutes'] = [
        'interval' => 30 * 60,
        'display'  => __('Every 30 Minutes', 'ov-booking'),
    ];
    return $schedules;
});

// 4) Aktivacija plugina: flush + zakazivanje
register_activation_hook(OV_BOOKING_PATH . 'ov-booking.php', function() {
    add_rewrite_endpoint('ical', EP_PERMALINK);
    flush_rewrite_rules();
    if (!wp_next_scheduled('ovb_ical_import')) {
        wp_schedule_event(time(), 'thirty_minutes', 'ovb_ical_import');
    }
});

// 5) Deaktivacija plugina: flush + čišćenje hooka
register_deactivation_hook(OV_BOOKING_PATH . 'ov-booking.php', function() {
    flush_rewrite_rules();
    wp_clear_scheduled_hook('ovb_ical_import');
});

// 6) Cron hook za import
// add_action('ovb_ical_import', [OVB_iCal_Service::class, 'fetch_and_import']);
add_action('ovb_ical_import', function() {
    if (class_exists('OVB_iCal_Service')) {
        OVB_iCal_Service::fetch_and_import();
    }
});

// 7) Init iCal cl ako postoje
add_action('init', function() {
    if (class_exists('OVB_iCal_Service')) {
        OVB_iCal_Service::init();
    }
    if (class_exists('OVB_iCal_Meta')) {
        OVB_iCal_Meta::init();
    }
});
