<?php
defined('ABSPATH') || exit;

// Ukloni shipping sekciju i Downloads
add_filter('woocommerce_my_account_get_addresses', function($addresses) {
    unset($addresses['shipping']);
    return $addresses;
});
// Brisanje shipping polja iz forme
add_filter('woocommerce_checkout_fields', function($fields) {
    unset($fields['shipping']);
    return $fields;
});
// Uklanjanje shipping sekcije iz My Account editacije adresa
add_filter('woocommerce_account_edit_address_endpoints', function($endpoints) {
    unset($endpoints['shipping']);
    return $endpoints;
});
// Redirekcija prilikom pokušaja pristupa shipping adresi
add_action('template_redirect', function() {
    if (is_wc_endpoint_url('edit-address') && isset($_GET['address']) && 'shipping' === $_GET['address']) {
        wp_safe_redirect(wc_get_account_endpoint_url('edit-address'));
        exit;
    }
});
// Uklanjanje shipping adrese iz korisničkih podataka
add_filter('woocommerce_customer_meta_fields', function($fields) {
    unset($fields['shipping']);
    return $fields;
});
// Onemogućavanje shipping adrese u REST API-ju
add_filter('woocommerce_rest_prepare_customer', function($response) {
    if (isset($response->data['shipping'])) {
        unset($response->data['shipping']);
    }
    return $response;
}, 10, 1);
// Sakrij shipping opcije u admin panelu
add_filter('woocommerce_get_sections_shipping', '__return_empty_array');
add_filter('woocommerce_get_settings_shipping', '__return_empty_array');
// Ukloni shipping iz admin toolbar-a
add_action('admin_bar_menu', function($wp_admin_bar) {
    $wp_admin_bar->remove_node('new-wc_shipping_zone');
}, 999);

// Onemogućavanje shipping adrese na checkoutu
add_filter('woocommerce_cart_needs_shipping_address', '__return_false');

// Ukloni Downloads iz my account menija
add_filter('woocommerce_account_menu_items', function($items) {
    unset($items['downloads']);
    return $items;
}, 999);
// Redirektuj pristup downloads endpointu
add_action('template_redirect', function() {
    if (is_wc_endpoint_url('downloads')) {
        wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
        exit;
    }
});
// Onemogući download funkcionalnost u potpunosti
add_filter('woocommerce_account_downloads_columns', '__return_empty_array');
add_filter('woocommerce_customer_get_downloadable_products', '__return_empty_array');

// Custom Dashboard
// add_action('after_setup_theme', function () {
//     if (class_exists('WooCommerce')) {
//         remove_action('woocommerce_account_dashboard', 'woocommerce_account_content', 10);
//         add_filter('woocommerce_account_content', 'ovb_account_content', 10);
//     }
// }, 20);


add_filter('woocommerce_account_content', function ($content) {
    if (is_account_page() && !is_wc_endpoint_url()) {
        if (!is_user_logged_in()) return $content;
        $user = wp_get_current_user();
        ob_start(); ?>
        <h1>TU SMOOOOO</h1>
        <div class="ovb-dashboard-welcome">
            <p><?php printf(
                esc_html__('Hello %1$s (not you? %2$s)', 'ov-booking'),
                '<strong>' . esc_html($user->display_name) . '</strong>',
                '<a href="' . esc_url(wc_logout_url()) . '">' . esc_html__('Log out', 'ov-booking') . '</a>'
            ); ?></p>
        </div>
        <?php return ob_get_clean();
    }
    return $content;
}, 20);

// Spreči pristup endpointima ako nije ulogovan
add_action('template_redirect', function () {
    if (is_account_page() && !is_user_logged_in() && is_wc_endpoint_url()) {
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }
});

// Override logout link
add_filter('woocommerce_get_endpoint_url', function ($url, $endpoint, $value, $permalink) {
    if ($endpoint === 'customer-logout') {
        return add_query_arg([
            'ovb_logout' => '1',
            '_wpnonce'   => wp_create_nonce('ovb_direct_logout'),
        ], home_url('/'));
    }
    return $url;
}, 10, 4);

add_action('init', function () {
    if (isset($_GET['ovb_logout'], $_GET['_wpnonce'])
        && '1' === $_GET['ovb_logout']
        && wp_verify_nonce($_GET['_wpnonce'], 'ovb_direct_logout')) {
        wp_logout();
        wp_safe_redirect(wp_login_url());
        exit;
    }
});

// Login redirect na wp login
add_filter( 'woocommerce_login_url', 'ovb_override_wc_login_url', 10, 2 );
function ovb_override_wc_login_url( $login_url, $redirect ) {
    // $redirect možeš promeniti ako hoćeš drugu stranicu nakon prijave
    return wp_login_url( $redirect );
}
