<?php
/**
 * OVB – Custom WP Login (logo, stil, Google login)
 *
 * - Centrira login box i stilizuje formu
 * - Menja logo/link/title
 * - Dodaje "Login with Google" ispod forme (koristi tvoj OAuth, ima fallback)
 * - Bezbedno pomera language switcher odmah ispod forme
 *
 * Preporučeno mesto: includes/auth/login-customizations.php
 */
defined('ABSPATH') || exit;

/**
 * Helper: vrati URL logotipa za login (može se pregaziti filterom).
 * Ako postoje OVB_BOOKING_URL/OVB_BOOKING_PATH i fajl, koristi ih, inače fallback na $default.
 */
add_filter('ovb_login_logo_url', function ($default) {
    if (defined('OVB_BOOKING_URL') && defined('OVB_BOOKING_PATH')) {
        $rel  = 'assets/images/logo-transparent.png';
        $path = trailingslashit(OVB_BOOKING_PATH) . $rel;
        if (file_exists($path)) {
            return esc_url_raw(trailingslashit(OVB_BOOKING_URL) . $rel);
        }
    }
    return $default;
});

/**
 * Stilovi i layout (centriranje, dark tema, kartica, polja, dugmad).
 */
add_action('login_enqueue_scripts', function () {
    // Site Icon ili tema → potom filter iznad može pregaziti na logo iz plugina
    $logo_url = esc_url_raw(
        apply_filters('ovb_login_logo_url', get_site_icon_url() ?: get_theme_file_uri('/assets/images/company-logo.png'))
    );

    $css = "
    :root{
      --ovb-accent:#8B5DFF;
      --ovb-bg:#0f1224;
      --ovb-card:#1b203a;
      --ovb-text:#e6e8ef;
    }
    body.login{
      background: radial-gradient(1200px 800px at 20% -10%, rgba(139,93,255,.25), transparent 60%),
                  radial-gradient(1000px 700px at 120% 10%, rgba(23,162,184,.18), transparent 50%),
                  var(--ovb-bg);
      min-height: 100svh;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
    }
    #login{
      margin: 0 !important;
      padding: 40px 20px 20px;
      width: min(420px, 92vw);
    }
    #login_error, .login .message, .login .success{ margin: 0 0 16px 0; }
    .language-switcher{ margin-top: 12px; text-align: center; }

    .login form{
      background: var(--ovb-card);
      border: 1px solid rgba(139,93,255,.2);
      box-shadow: 0 20px 40px rgba(0,0,0,.25);
      color: var(--ovb-text);
    }
    .login form .input, .login input[type=text]{
      background:#0f1224; color:#fff; border:1px solid rgba(255,255,255,.18);
    }
    .login form .input:focus{
      border-color: var(--ovb-accent); box-shadow: 0 0 0 3px rgba(139,93,255,.25);
    }
    .button.wp-hide-pw{ color:#fff; }
    .login #backtoblog a, .login #nav a{ color:#d1d5db; }
    .login #nav a:hover, .login #backtoblog a:hover{ color:#fff; }
    .login .button-primary{
      background:var(--ovb-accent) !important; border:0; box-shadow:none; text-shadow:none;
    }
    .login .button-primary:hover{ filter:brightness(1.05); }

    /* Logo */
    .login h1 a{
      background-image:url('{$logo_url}');
      background-size: contain;
      background-position: center;
      width: 220px; height: 64px;
    }

    /* Google sekcija */
    .ovb-social-login{
      margin-top:16px;
      padding:14px 16px;
      background:var(--ovb-card);
      border-radius:8px;
      display:flex;
      flex-direction:column; 
      gap:16px;
      width:100%;
      color:#e5e7eb;
      box-sizing: border-box;
    }
    .ovb-social-login .ovb-sep{
      display:flex; align-items:center; gap:10px; color:#9ca3af; font-size:12px;
    }
    .ovb-social-login .ovb-sep:before,.ovb-social-login .ovb-sep:after{
      content:''; flex:1; height:1px; background:rgba(255,255,255,.1);
    }
    .ovb-google-btn{
      display:flex; align-items:center; justify-content:center; gap:10px;
      padding:10px 12px; width:100%;
      border-radius:8px; background:#fff; color:#111827; border:0; cursor:pointer;
      font-weight:600; text-decoration:none;
      box-sizing: border-box;
    }
    .ovb-google-btn:hover{ filter:brightness(.97); }
    .ovb-google-btn svg{ width:18px; height:18px; }
    ";
    // Core 'login' stil je već enqueued na login strani → dodaj inline CSS
    wp_add_inline_style('login', $css);
});

/** Logo link i title */
add_filter('login_headerurl', fn () => home_url('/'));
add_filter('login_headertext', fn () => get_bloginfo('name'));

/**
 * Helper: izgradi Google OAuth URL (preferiraš svoje helpere, ima fallback).
 */
function ovb_login_build_google_url(): string {
    $redirect = !empty($_REQUEST['redirect_to']) ? esc_url_raw($_REQUEST['redirect_to']) : admin_url();

    if (function_exists('ovb_get_google_oauth_url')) {
        return (string) ovb_get_google_oauth_url([
            'redirect_to' => $redirect,
            'context'     => 'wp-login',
        ]);
    }

    // Fallback ruta + nonce (bezbedno)
    return wp_nonce_url(
        add_query_arg([
            'ovb_google_login' => '1',
            'redirect_to'      => rawurlencode($redirect),
        ], wp_login_url()),
        'ovb_google_login_start',
        '_ovbgl'
    );
}

/**
 * Helper: HTML za Google dugme (kao string).
 */
function ovb_login_google_button_html(string $google_url): string {
    $g_icon = '<svg viewBox="0 0 533.5 544.3" xmlns="http://www.w3.org/2000/svg"><path d="M533.5 278.4c0-18.5-1.7-36.4-4.9-53.7H272v101.6h146.9c-6.3 34.1-25.4 63-54.2 82.3v68h87.7c51.3-47.3 80.1-117.1 80.1-198.2z" fill="#4285F4"/><path d="M272 544.3c72.8 0 134-24.1 178.7-65.7l-87.7-68c-24.4 16.3-55.7 26-91 26-69.9 0-129.1-47.2-150.3-110.6H31.8v69.6C76.3 486.8 169.2 544.3 272 544.3z" fill="#34A853"/><path d="M121.7 326c-10.2-30.4-10.2-63.3 0-93.7V162.7H31.8C-10.6 244.3-10.6 344 31.8 425.6l89.9-69.6z" fill="#FBBC05"/><path d="M272 107.7c39.6-.6 77.2 14.5 106.1 42.1l79.3-79.3C405.6 19.1 339.7-3 272 0 169.2 0 76.3 57.5 31.8 162.7l89.9 69.6C142.9 154.9 202.1 107.7 272 107.7z" fill="#EA4335"/></svg>';
    
    return '
    <div class="ovb-social-login">
      <div class="ovb-sep">or</div>
      <a id="ovb-google-btn" class="ovb-google-btn" href="' . esc_url($google_url) . '" rel="nofollow noopener noreferrer">
        ' . $g_icon . '
        <span>Login with Google</span>
      </a>
    </div>';
}

/**
 * Prikaži Google dugme ispod forme (unutar <form>).
 * 'login_form_bottom' je FILTER koji očekuje string.
 */
add_filter('login_form_bottom', function ($content, $args) {
    $google_url = ovb_login_build_google_url();
    return $content . ovb_login_google_button_html($google_url);
}, 10, 2);

/**
 * UX: premesti language switcher odmah ispod forme.
 * Takođe, ako neki drugi plugin ukloni filter, JS fallback će umetnuti Google dugme.
 */
add_action('login_footer', function () {
    $google_url = ovb_login_build_google_url();
    // echo do_shortcode('[ovb_google_login_button]');
    $btn_html   = ovb_login_google_button_html($google_url);
    ?>
<script>
(function() {
    var form = document.querySelector('#login form');
    if (!form) return;

    // Premesti language switcher tik ispod forme
    var ls = document.querySelector('.language-switcher');
    if (ls) {
        form.insertAdjacentElement('afterend', ls);
        ls.style.marginTop = '12px';
    }

    // Fallback: ako dugme nije renderovano preko PHP filtera, ubaci ga JS-om
    if (!document.getElementById('ovb-google-btn')) {
        var wrap = document.createElement('div');
        wrap.innerHTML = <?php echo wp_json_encode($btn_html); ?>;
        form.appendChild(wrap.firstElementChild);
    }
})();
</script>
<?php
});

/**
 * Fallback bootstrap za OAuth start (ako nemaš sopstveni ovb_google_oauth_start()).
 * Pokreće se na: /wp-login.php?ovb_google_login=1&_ovbgl=nonce
 */
add_action('init', function () {
    if (!is_user_logged_in() && isset($_GET['ovb_google_login']) && $_GET['ovb_google_login'] === '1') {
        if (!isset($_GET['_ovbgl']) || !wp_verify_nonce($_GET['_ovbgl'], 'ovb_google_login_start')) {
            wp_die(__('Invalid request.', 'ov-booking'));
        }
        $redirect = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : admin_url();

        if (function_exists('ovb_google_oauth_start')) {
            ovb_google_oauth_start([
                'redirect_to' => $redirect,
                'context'     => 'wp-login',
            ]);
            exit;
        }

        // Ako nemaš sopstveni handler – ne zapinji u loop
        wp_safe_redirect(wp_login_url());
        exit;
    }
});