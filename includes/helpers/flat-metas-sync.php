<?php
defined('ABSPATH') || exit;
if (file_exists(dirname(__DIR__) . '/helpers/logger.php')) {
    require_once dirname(__DIR__) . '/helpers/logger.php';
}
/**
 * Mapiranje polja iz _apartment_additional_info => pojedinačni _ovb_* meta ključevi
 */
function ovb_flat_meta_map(): array {
    return [
        'street_name'        => '_ovb_street_name',     // TEXT
        'city'               => '_ovb_city',            // TEXT
        'country'            => '_ovb_country',         // TEXT
        'max_guests'         => '_ovb_max_guests',      // NUMERIC
        'bedrooms'           => '_ovb_bedrooms',        // NUMERIC
        'beds'               => '_ovb_beds',            // NUMERIC
        'bathrooms'          => '_ovb_bathrooms',       // NUMERIC
        'accommodation_type' => '_ovb_accommodation_type', // TEXT (fallback kada nema takse)
        'checkin_time'       => '_ovb_checkin_time',    // TEXT (HH:MM)
        'checkout_time'      => '_ovb_checkout_time',   // TEXT (HH:MM)
    ];
}

/** update_post_meta koji briše meta ako je vrednost "prazna" */
function ovb_update_or_delete_meta(int $post_id, string $key, $val): void {
    $is_empty = ($val === '' || $val === null);
    if ($is_empty) {
        delete_post_meta($post_id, $key);
    } else {
        update_post_meta($post_id, $key, $val);
    }
}

/**
 * Sinhronizuje flat _ovb_* mete iz sanitizovanog niza _apartment_additional_info
 * $data je već PROVEREN i SANITIZOVAN niz (vidi save_additional_apartment_info).
 */
function ovb_sync_flat_metas_from_additional_info(int $post_id, array $data): void {
    $map = ovb_flat_meta_map();

    // TEXT
    foreach (['street_name','city','country','accommodation_type','checkin_time','checkout_time'] as $k) {
        if (array_key_exists($k, $data)) {
            ovb_update_or_delete_meta($post_id, $map[$k], is_string($data[$k]) ? $data[$k] : '');
        }
    }
    // NUMERIC
    foreach (['max_guests','bedrooms','beds','bathrooms'] as $k) {
        if (array_key_exists($k, $data)) {
            $num = is_numeric($data[$k]) ? (int) $data[$k] : 0;
            ovb_update_or_delete_meta($post_id, $map[$k], $num > 0 ? $num : '');
        }
    }
}

/**
 * Watcher: ako bilo ko update-uje _apartment_additional_info (npr. drugi AJAX), sinhronizuj flat mete.
 */
add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key !== '_apartment_additional_info') return;
    if (get_post_type($post_id) !== 'product') return;

    $arr = is_array($meta_value) ? $meta_value : maybe_unserialize($meta_value);
    if (!is_array($arr)) {
        $arr = get_post_meta($post_id, '_apartment_additional_info', true);
        if (!is_array($arr)) return;
    }

    // Minimalna "sanitizacija" u watcher-u (da ne zavisimo od eksternog callera)
    $safe = [
        'street_name'        => isset($arr['street_name']) ? sanitize_text_field($arr['street_name']) : '',
        'city'               => isset($arr['city']) ? sanitize_text_field($arr['city']) : '',
        'country'            => isset($arr['country']) ? sanitize_text_field($arr['country']) : '',
        'max_guests'         => isset($arr['max_guests']) ? absint($arr['max_guests']) : 0,
        'bedrooms'           => isset($arr['bedrooms']) ? absint($arr['bedrooms']) : 0,
        'beds'               => isset($arr['beds']) ? absint($arr['beds']) : 0,
        'bathrooms'          => isset($arr['bathrooms']) ? absint($arr['bathrooms']) : 0,
        'accommodation_type' => isset($arr['accommodation_type']) ? sanitize_key($arr['accommodation_type']) : '',
        'checkin_time'       => (isset($arr['checkin_time'])  && preg_match('/^\d{2}:\d{2}$/', $arr['checkin_time']))  ? $arr['checkin_time']  : '',
        'checkout_time'      => (isset($arr['checkout_time']) && preg_match('/^\d{2}:\d{2}$/', $arr['checkout_time'])) ? $arr['checkout_time'] : '',
    ];
    ovb_sync_flat_metas_from_additional_info($post_id, $safe);
}, 10, 4);

function save_additional_apartment_info($post_id)
{
    if (
        !isset($_POST['additional_info_nonce']) ||
        !wp_verify_nonce($_POST['additional_info_nonce'], 'sacuvaj_additional_info_nonce')
    ) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id))   return;
    if (get_post_type($post_id) !== 'product')      return;

    $accommodation_types = [
        'apartment' => 'Apartment',
        'house'     => 'House',
        'villa'     => 'Villa',
        'cottage'   => 'Cottage',
        'studio'    => 'Studio'
    ];

    $data = $_POST['additional_info'] ?? [];
    $sanitized = [];

    // TEXT
    $sanitized['street_name'] = sanitize_text_field($data['street_name'] ?? '');
    $sanitized['city']        = sanitize_text_field($data['city'] ?? '');
    $sanitized['country']     = sanitize_text_field($data['country'] ?? '');

    // NUMERIC
    $sanitized['max_guests'] = !empty($data['max_guests']) ? absint($data['max_guests']) : 1;
    $sanitized['bedrooms']   = !empty($data['bedrooms'])   ? absint($data['bedrooms'])   : 1;
    $sanitized['beds']       = !empty($data['beds'])       ? absint($data['beds'])       : 1;
    $sanitized['bathrooms']  = !empty($data['bathrooms'])  ? absint($data['bathrooms'])  : 1;

    // TYPE
    $sanitized['accommodation_type'] =
        (isset($data['accommodation_type']) && array_key_exists($data['accommodation_type'], $accommodation_types))
            ? sanitize_key($data['accommodation_type'])
            : 'apartment';

    // HH:MM
    $sanitized['checkin_time']  = preg_match('/^\d{2}:\d{2}$/', $data['checkin_time']  ?? '') ? sanitize_text_field($data['checkin_time'])  : '';
    $sanitized['checkout_time'] = preg_match('/^\d{2}:\d{2}$/', $data['checkout_time'] ?? '') ? sanitize_text_field($data['checkout_time']) : '';

    // 1) Sačuvaj glavni array
    update_post_meta($post_id, '_apartment_additional_info', $sanitized);

    // 2) Istovremeno sinhronizuj pojedinačne _ovb_* ključeve
    if (function_exists('ovb_sync_flat_metas_from_additional_info')) {
        ovb_sync_flat_metas_from_additional_info($post_id, $sanitized);
    }
}
add_action('save_post', 'save_additional_apartment_info');


if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('ovb backfill-flats', function($args, $assoc_args){
        $query = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $ids = $query->posts ?: [];
        $count = 0;

        foreach ($ids as $pid) {
            $info = get_post_meta($pid, '_apartment_additional_info', true);
            if (!is_array($info) || empty($info)) continue;

            $safe = [
                'street_name'        => sanitize_text_field($info['street_name'] ?? ''),
                'city'               => sanitize_text_field($info['city'] ?? ''),
                'country'            => sanitize_text_field($info['country'] ?? ''),
                'max_guests'         => isset($info['max_guests']) ? absint($info['max_guests']) : 0,
                'bedrooms'           => isset($info['bedrooms']) ? absint($info['bedrooms']) : 0,
                'beds'               => isset($info['beds']) ? absint($info['beds']) : 0,
                'bathrooms'          => isset($info['bathrooms']) ? absint($info['bathrooms']) : 0,
                'accommodation_type' => isset($info['accommodation_type']) ? sanitize_key($info['accommodation_type']) : '',
                'checkin_time'       => (isset($info['checkin_time'])  && preg_match('/^\d{2}:\d{2}$/', $info['checkin_time']))  ? $info['checkin_time']  : '',
                'checkout_time'      => (isset($info['checkout_time']) && preg_match('/^\d{2}:\d{2}$/', $info['checkout_time'])) ? $info['checkout_time'] : '',
            ];
            ovb_sync_flat_metas_from_additional_info($pid, $safe);
            $count++;
        }

        WP_CLI::success("OVB: backfill kompletiran za {$count} proizvoda.");
    });
}

add_action('admin_init', function() {
    if (!current_user_can('manage_options')) return;

    // Pokreće se samo jednom kada odeš u admin
    if (!get_option('ovb_backfill_done')) {
        $query = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        $count = 0;
        foreach ($query->posts as $pid) {
            $info = get_post_meta($pid, '_apartment_additional_info', true);
            if (!is_array($info) || empty($info)) continue;

            $safe = [
                'street_name'        => sanitize_text_field($info['street_name'] ?? ''),
                'city'               => sanitize_text_field($info['city'] ?? ''),
                'country'            => sanitize_text_field($info['country'] ?? ''),
                'max_guests'         => isset($info['max_guests']) ? absint($info['max_guests']) : 0,
                'bedrooms'           => isset($info['bedrooms']) ? absint($info['bedrooms']) : 0,
                'beds'               => isset($info['beds']) ? absint($info['beds']) : 0,
                'bathrooms'          => isset($info['bathrooms']) ? absint($info['bathrooms']) : 0,
                'accommodation_type' => isset($info['accommodation_type']) ? sanitize_key($info['accommodation_type']) : '',
                'checkin_time'       => (isset($info['checkin_time'])  && preg_match('/^\d{2}:\d{2}$/',$info['checkin_time']))  ? $info['checkin_time']  : '',
                'checkout_time'      => (isset($info['checkout_time']) && preg_match('/^\d{2}:\d{2}$/',$info['checkout_time'])) ? $info['checkout_time'] : '',
            ];
            ovb_sync_flat_metas_from_additional_info($pid, $safe);
            $count++;
        }

        update_option('ovb_backfill_done', 1, false);
        ovb_log_error("Backfill finished: $count products updated");
    }
});