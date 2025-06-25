<?php
defined('ABSPATH') || exit;

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }

    $client_id     = get_option('ovb_google_client_id');
    $client_secret = get_option('ovb_google_client_secret');

    $screen = get_current_screen();
    if (!$screen) return;

    $allowed_screens = [
        'dashboard',
        'toplevel_page_ov-booking-settings'
    ];

    if (in_array($screen->id, $allowed_screens)) {
        if (empty($client_id) || empty($client_secret)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('Google OAuth nije podešen.', 'ov-booking') . '</strong> ';
            echo esc_html__('Molimo unesite Client ID i Secret u Booking Settings plugin-u.', 'ov-booking') . '</p>';
            echo '</div>';
        }
    }
});
