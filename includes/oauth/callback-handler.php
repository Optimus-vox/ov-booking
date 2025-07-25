<?php
defined('ABSPATH') || exit;

add_action('init', 'ovb_handle_google_oauth_callback');

function ovb_handle_google_oauth_callback() {
    if (!isset($_GET['google_auth']) || !isset($_GET['code'])) {
        return;
    }

    if (!isset($_GET['state']) || !wp_verify_nonce($_GET['state'], 'google_oauth_nonce')) {
        wc_add_notice(__('Neispravan sigurnosni token.', 'ov-booking'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }

    $client_id     = get_option('ovb_google_client_id');
    $client_secret = get_option('ovb_google_client_secret');
    $redirect_uri  = home_url('/wp-login.php?google_auth=1');

    if (!$client_id || !$client_secret) {
        wc_add_notice(__('Google OAuth nije ispravno podešen.', 'ov-booking'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }

    $code = sanitize_text_field($_GET['code']);

    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'body' => [
            'code'          => $code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ]
    ]);

    if (is_wp_error($response)) {
        wc_add_notice(__('Greška u komunikaciji sa Google serverom.', 'ov-booking'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['access_token'])) {
        wc_add_notice(__('Token od Google-a nije primljen.', 'ov-booking'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }

    $access_token = $body['access_token'];

    $user_info_response = wp_remote_get('https://www.googleapis.com/oauth2/v1/userinfo?alt=json', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
        ]
    ]);

    if (is_wp_error($user_info_response)) {
        wc_add_notice(__('Greška pri dobijanju korisničkih podataka.', 'ov-booking'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }

    $user_info = json_decode(wp_remote_retrieve_body($user_info_response), true);

    if (empty($user_info['email'])) {
        wc_add_notice(__('Google nalog nema email.', 'ov-booking'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }

    $user = get_user_by('email', $user_info['email']);

    if (!$user) {
        $user_id = wp_create_user(
            sanitize_user($user_info['email']),
            wp_generate_password(),
            $user_info['email']
        );

        if (is_wp_error($user_id)) {
            wc_add_notice(__('Kreiranje korisnika nije uspelo.', 'ov-booking'), 'error');
            wp_redirect(wc_get_cart_url());
            exit;
        }

        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $user_info['name'] ?? '',
            'first_name'   => $user_info['given_name'] ?? '',
            'last_name'    => $user_info['family_name'] ?? '',
        ]);

        $user = get_user_by('id', $user_id);
    }

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID);
    wp_redirect(wc_get_cart_url());
    exit;
}