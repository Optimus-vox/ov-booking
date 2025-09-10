<?php
/**
 * OVB — Header menu: "Login" (gost) / "My Account" (ulogovan)
 *
 * - Radi sa Elementor Nav Menu + Astra, jer presreće WP menije (wp_nav_menu_objects)
 * - Targetira stavku koja vodi na Woo "My account" ILI onu sa CSS klasom "menu-item-account"
 * - Gost: naslov -> "Login" (prevodiv), sklanja dropdown decu (Cart/Checkout...)
 * - Ulogovan: koristi postojeći naslov iz menija (lokalizovan), ili naslov Woo "My account" stranice u aktivnom jeziku
 * - I18n safe (WPML/Polylang), možeš pregaziti labele filterima
 */

defined('ABSPATH') || exit;

/** (Opcionalno) Ograniči na određene lokacije menija: npr. ['primary','astra-primary'] */
if (!function_exists('ovb_menu_switch_locations')) {
    function ovb_menu_switch_locations(): array {
        return []; // prazno = važi za sve menije
    }
}

/** Normalizuj URL: apsolutna putanja + trailing slash (radi stabilnog poređenja) */
if (!function_exists('ovb_menu_login_switch_normalize_url')) {
    function ovb_menu_login_switch_normalize_url(string $url): string {
        $parsed = wp_parse_url($url);
        $path   = isset($parsed['path']) ? trim($parsed['path'], '/') : '';
        return trailingslashit(home_url($path));
    }
}

/** URL koji predstavlja "My Account" destinaciju (fallback kad Woo nije aktivan) */
if (!function_exists('ovb_menu_get_account_url')) {
    function ovb_menu_get_account_url(): string {
        if (function_exists('wc_get_page_permalink')) {
            $u = wc_get_page_permalink('myaccount');
            if ($u && !is_wp_error($u)) return $u;
        }
        // Fallback: ulogovan -> profil; gost -> wp-login
        return is_user_logged_in()
            ? get_edit_profile_url(get_current_user_id())
            : wp_login_url();
    }
}

/** Lokalizovan naslov Woo "My account" stranice (sa WPML/Polylang podrškom) */
if (!function_exists('ovb_menu_get_account_title_localized')) {
    function ovb_menu_get_account_title_localized(): string {
        if (function_exists('wc_get_page_id')) {
            $acc_id = wc_get_page_id('myaccount');
            if ($acc_id && $acc_id > 0) {
                // WPML
                if (has_filter('wpml_object_id')) {
                    $maybe = apply_filters('wpml_object_id', $acc_id, 'page', true);
                    if (!empty($maybe)) $acc_id = $maybe;
                }
                // Polylang
                if (function_exists('pll_get_post')) {
                    $maybe = pll_get_post($acc_id);
                    if (!empty($maybe)) $acc_id = $maybe;
                }
                $t = get_the_title($acc_id);
                if (!empty($t)) return $t;
            }
        }
        // WooCommerce default msg (prevodiv preko Woo domena), fallback na generički "Account"
        return _x('My account', 'menu item', 'woocommerce') ?: __('Account', 'ov-booking');
    }
}

add_filter('wp_nav_menu_objects', function (array $items, $args) {
    if (empty($items)) return $items;

    // Ograničenje na određene lokacije (ako želiš)
    $limit_to = ovb_menu_switch_locations();
    if (!empty($limit_to)) {
        $loc = isset($args->theme_location) ? (string)$args->theme_location : '';
        if (!in_array($loc, $limit_to, true)) return $items;
    }

    $is_logged_in   = is_user_logged_in();
    $account_url    = ovb_menu_get_account_url();
    $account_norm   = ovb_menu_login_switch_normalize_url($account_url);
    $account_titleL = ovb_menu_get_account_title_localized();

    // Prevodive default labele + mogućnost override-ovanja filterima
    $login_label_default   = __('Login', 'ov-booking');
    $account_label_default = $account_titleL;

    $login_label   = apply_filters('ovb_menu_login_label',   $login_label_default,   $args);
    $account_label = apply_filters('ovb_menu_account_label', $account_label_default, $args);

    $parents_to_hide_children = [];

    foreach ($items as $i => $item) {
        $classes = is_array($item->classes) ? $item->classes : (array)$item->classes;

        $marked_by_class = in_array('menu-item-account', $classes, true) || in_array('ovb-account-toggle', $classes, true);
        $same_url        = ovb_menu_login_switch_normalize_url((string) $item->url) === $account_norm;

        if (!$marked_by_class && !$same_url) continue;

        if ($is_logged_in) {
            // Ulogovan: koristi postojeći naslov iz menija (već može biti preveden); ako je prazan, koristi lokalizovan naslov stranice
            $item->title = !empty($item->title) ? $item->title : $account_label;
            $item->url   = $account_url;
        } else {
            // Gost: "Login" (prevodiv), link vodi na My Account (Woo login forma / wp-login fallback)
            $item->title = $login_label;
            $item->url   = $account_url;
            $parents_to_hide_children[] = (int)$item->ID; // sakrij dropdown decu
        }

        $item->classes = $classes;
        $items[$i]     = $item;
    }

    // Ukloni decu označenih parenta kada je gost
    if (!$is_logged_in && $parents_to_hide_children) {
        foreach ($items as $idx => $it) {
            if (in_array((int)$it->menu_item_parent, $parents_to_hide_children, true)) {
                unset($items[$idx]);
            }
        }
        $items = array_values($items); // reindeksiraj
    }

    return $items;
}, 10, 2);