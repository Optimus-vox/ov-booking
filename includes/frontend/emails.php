<?php
defined('ABSPATH') || exit;

/**
 * OVB — Email tweaks: lep "Booking details" blok po stavci + footer link
 */

// 0) Putanja do /templates foldera u ovom pluginu
$__OVB_TPL_PATH = trailingslashit( dirname( dirname( __DIR__ ) ) ) . 'templates/';

// 1) U email kontekstu ukloni sirove meta ključeve (da Woo ne ispisuje ružne redove)
add_filter('woocommerce_order_item_get_formatted_meta_data', function ($formatted_meta, $item) {
    if (! did_action('woocommerce_email_before_order_table')) {
        return $formatted_meta; // samo u emailovima
    }

    $strip = [
        'booking_dates','first_name','last_name','email','phone','guests',
        'rangeStart','rangeEnd','booking_id',
    ];

    foreach ($formatted_meta as $mid => $meta_obj) {
        // radi i sa starijim/novijim WC
        $key = '';
        if (is_object($meta_obj)) {
            if (isset($meta_obj->key)) $key = (string)$meta_obj->key;
            if (!$key && isset($meta_obj->display_key)) $key = (string)$meta_obj->display_key;
        }
        if ($key !== '' && in_array($key, $strip, true)) {
            unset($formatted_meta[$mid]);
        }
    }
    return $formatted_meta;
}, 10, 2);

// 2) Ispod SVAKOG order item-a, u emailu, ubaci lep "Booking details" blok iz šablona
add_action('woocommerce_order_item_meta_end', function ($item_id, $item, $order, $plain_text) use ($__OVB_TPL_PATH) {
    if (! did_action('woocommerce_email_before_order_table')) return;

    $args = [
        'order'      => $order,
        'item'       => $item,
        'plain_text' => (bool) $plain_text,
    ];

    if ($plain_text) {
        wc_get_template('emails/plain/ovb-booking-details.php', $args, '', $__OVB_TPL_PATH);
    } else {
        wc_get_template('emails/ovb-booking-details.php',       $args, '', $__OVB_TPL_PATH);
    }
}, 10, 4);

// 3) Footer emaila: "Naziv sajta — https://tvojdomen"
add_filter('woocommerce_email_footer_text', function ($text) {
    $name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $url  = home_url('/');
    $host = parse_url($url, PHP_URL_HOST);

    return sprintf(
        '%s — <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_html($name),
        esc_url($url),
        esc_html($host ?: $url)
    );
});
