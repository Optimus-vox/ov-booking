<?php
/**
 * Uninstall OV Booking
 */
if ( ! defined('WP_UNINSTALL_PLUGIN') ) exit;

global $wpdb;

/** 1) Opcije & (site) transijenti sa prefixom ovb_ */
$like = $wpdb->esc_like('ovb_') . '%';
$wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like) );
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ovb_%' OR option_name LIKE '_site_transient_ovb_%'");

/** 2) Pojedinačne opcije (ako su ručno upisivane) */
foreach (['ovb_booking_settings','ovb_booking_display_mode'] as $opt) {
    delete_option($opt);
    delete_site_option($opt);
}

/** 3) Cron hookovi plugina */
foreach (['ovb_sync_ical','ovb_cleanup','ovb_send_reminders','ovb_refresh_cache'] as $hook) {
    while ( $ts = wp_next_scheduled($hook) ) { wp_unschedule_event($ts, $hook); }
    wp_clear_scheduled_hook($hook);
}

/** 4) Custom tabele (ako postoje) */
foreach ([$wpdb->prefix . 'ovb_bookings', $wpdb->prefix . 'ovb_ical_cache', $wpdb->prefix . 'ovb_logs'] as $tbl) {
    $wpdb->query("DROP TABLE IF EXISTS `{$tbl}`");
}

/** 5) Brisanje meta podataka */
/* 5.1: Svi _ovb_* ključevi iz postmeta/usermeta */
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_ovb\\_%'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\\_ovb\\_%'");

/* 5.2: Proizvod meta koje si koristio (safe jer su tvoje) */
$prod_meta_keys = [
    '_apartment_info_icons',
    '_apartment_rules_icons',
    '_ovb_custom_editor',
    '_apartment_additional_info',
    '_product_testimonials',
    '_ovb_calendar_data',
    '_ovb_price_types',
];
$in = implode("','", array_map('esc_sql', $prod_meta_keys));
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('{$in}')");

/* 5.3: (Opc.) Unprefixed ključevi koje si upisivao na porudžbine — uključi po želji */
if ( ! defined('OVB_KEEP_COMPAT_META') || ! OVB_KEEP_COMPAT_META ) {
    $compat = ['start_date','end_date','guests','all_dates','booking_client_first_name','booking_client_last_name','booking_client_email','booking_client_phone'];
    $in2 = implode("','", array_map('esc_sql', $compat));
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('{$in2}')");
}

/** 6) (Ne diramo globalne Woo sesije _wc_session_ zbog bezbednosti drugih korisnika) */

/** 7) Gotovo */
