<?php
defined('ABSPATH') || exit;

/**
 * Generiše Google OAuth URL
 */
function ovb_get_google_oauth_url() {
    $client_id    = get_option('ovb_google_client_id');
    $redirect_uri = home_url('/wp-login.php?google_auth=1');
    $state        = wp_create_nonce('google_oauth_nonce');

    if (empty($client_id)) return '';

    $params = [
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'response_type' => 'code',
        'scope'         => 'email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];

    return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
}

/**
 * Renderuje Google login dugme ako su podaci podešeni i korisnik nije ulogovan
 */
function ovb_render_google_login_button() {
    if (is_user_logged_in()) return;

    $client_id     = get_option('ovb_google_client_id');
    $client_secret = get_option('ovb_google_client_secret');

    if (empty($client_id) || empty($client_secret)) return;

    $oauth_url = ovb_get_google_oauth_url();
    if (!$oauth_url) return;

    ?>
    <a href="<?php echo esc_url($oauth_url); ?>" class="google-login-button" style="display:inline-flex;align-items:center;gap:10px;background:#fff;color:#444;padding:10px 20px;border-radius:4px;font-weight:bold;border:1px solid #ddd;text-decoration:none;margin-bottom:1rem;">
        <img src="<?php echo esc_url(OVB_BOOKING_URL . 'assets/images/google-logo.png'); ?>" alt="Google Logo" style="width:20px; vertical-align:middle; margin-right:8px;">
        <?php esc_html_e('Continue with Google', 'ov-booking'); ?> <span class="helper"></span>
    </a>
    <?php
}

/**
 * Shortcode: [ovb_google_login_button]
 */
add_shortcode('ovb_google_login_button', function() {
    ob_start();
    ovb_render_google_login_button();
    return ob_get_clean();
});