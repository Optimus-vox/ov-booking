<?php
defined('ABSPATH') || exit;

/** ========================= Logger ========================= */
if (file_exists(dirname(__DIR__) . '/helpers/logger.php')) {
    require_once dirname(__DIR__) . '/helpers/logger.php';
}
if (!function_exists('ovb_log_error')) {
    function ovb_log_error($message, $context = 'filters') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[OVB][' . $context . '] ' . (is_string($message) ? $message : print_r($message, true)));
        }
    }
}

/** ========================= Helpers za čitanje podataka ========================= */

function ovb_meta_keys_map(): array {
    return [
        'street_name' => '_ovb_street_name', 'city' => '_ovb_city', 'country' => '_ovb_country',
        'capacity' => '_ovb_max_guests', 'bedrooms' => '_ovb_bedrooms', 'beds' => '_ovb_beds',
        'bathrooms' => '_ovb_bathrooms', 'price' => '_price', 'type_meta' => '_ovb_accommodation_type',
    ];
}

function ovb_aggregated_subkeys(): array {
    return [
        'street_name' => 'street_name', 'city' => 'city', 'country' => 'country',
        'capacity' => 'max_guests', 'bedrooms' => 'bedrooms', 'beds' => 'beds',
        'bathrooms' => 'bathrooms', 'type' => 'accommodation_type',
    ];
}

function ovb_get_aggregated_value(int $post_id, string $subkey) {
    $raw = get_post_meta($post_id, '_apartment_additional_info', true);
    if (!is_array($raw)) $raw = maybe_unserialize($raw);
    return (is_array($raw) && isset($raw[$subkey])) ? $raw[$subkey] : null;
}

function ovb_post_get_field(int $post_id, string $field) {
    $map = ovb_meta_keys_map();
    if (isset($map[$field])) {
        $val = get_post_meta($post_id, $map[$field], true);
        if ($val !== '' && $val !== null) return $val;
    }
    $agg_keys = ovb_aggregated_subkeys();
    if (isset($agg_keys[$field])) return ovb_get_aggregated_value($post_id, $agg_keys[$field]);
    if ($field === 'type') {
        $val = get_post_meta($post_id, '_ovb_accommodation_type', true);
        if ($val) return $val;
        return ovb_get_aggregated_value($post_id, 'accommodation_type');
    }
    return null;
}

/**
 * ===================================================================
 * Runtime Filter - Logika za proveru svakog posta pojedinačno
 * ===================================================================
 */
function ovb_runtime_post_matches(WP_Post $post, array $filters): bool {
    // Provera tekstualnih polja
    $check_text = function ($field_key, $filter_value) use ($post) {
        if (empty($filter_value)) return true;
        $post_value = ovb_post_get_field($post->ID, $field_key);
        return (is_string($post_value) && stripos($post_value, $filter_value) !== false);
    };

    // Provera numeričkih polja
    $check_numeric = function ($field_key, $filter_value) use ($post) {
        if (!is_numeric($filter_value) || $filter_value <= 0) return true;
        $post_value = ovb_post_get_field($post->ID, $field_key);
        return (is_numeric($post_value) && (int)$post_value >= (int)$filter_value);
    };

    // NOVI DEO: Provera cene po danu unutar odabranog opsega
    $check_price_in_range = function ($min_price, $max_price, $start_date, $end_date) use ($post) {
        // Ovaj filter se primenjuje samo ako su datumi i bar jedna cena uneti
        if (empty($start_date) || empty($end_date) || (empty($min_price) && empty($max_price))) {
            return true;
        }

        $calendar_data = get_post_meta($post->ID, '_ovb_calendar_data', true);
        if (!is_array($calendar_data)) $calendar_data = json_decode($calendar_data, true);
        if (!is_array($calendar_data)) return false; // Nema kalendara, ne može da prođe filter

        $min = is_numeric($min_price) ? (float)$min_price : 0;
        $max = is_numeric($max_price) ? (float)$max_price : PHP_FLOAT_MAX;

        try {
            $period = new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), new DateTime($end_date));
            foreach ($period as $date) {
                $date_str = $date->format('Y-m-d');
                $day_price = isset($calendar_data[$date_str]['price']) ? (float)$calendar_data[$date_str]['price'] : -1;

                // Ako za bilo koji dan cena nije definisana ili je van opsega, proizvod otpada
                if ($day_price < 0 || $day_price < $min || $day_price > $max) {
                    return false;
                }
            }
        } catch (Exception $e) {
            return false; // Greška u datumu, preskoči proizvod
        }

        return true; // Sve cene u opsegu su validne
    };

    // Primenjivanje svih filtera (sa ispravkom za PHP warnings)
    if (!$check_text('type', $filters['type'] ?? '')) return false;
    if (!$check_text('street_name', $filters['street_name'] ?? '')) return false;
    if (!$check_text('city', $filters['city'] ?? '')) return false;
    if (!$check_text('country', $filters['country'] ?? '')) return false;
    if (!$check_numeric('capacity', $filters['capacity'] ?? '')) return false;
    if (!$check_numeric('bedrooms', $filters['bedrooms'] ?? '')) return false;
    if (!$check_numeric('beds', $filters['beds'] ?? '')) return false;
    if (!$check_numeric('bathrooms', $filters['bathrooms'] ?? '')) return false;
    if (!$check_price_in_range($filters['min_price'] ?? '', $filters['max_price'] ?? '', $filters['ci'] ?? '', $filters['co'] ?? '')) return false;

    return true;
}

function ovb_runtime_filter_posts(array $posts, WP_Query $q): array {
    $runtime_filters = $q->get('ovb_runtime_filters');
    if (empty($runtime_filters)) return $posts;

    $filtered_posts = [];
    foreach ($posts as $post) {
        if ($post instanceof WP_Post && $post->post_type === 'product' && ovb_runtime_post_matches($post, $runtime_filters)) {
            $filtered_posts[] = $post;
        }
    }
    return $filtered_posts;
}
add_filter('the_posts', 'ovb_runtime_filter_posts', 10, 2);


/**
 * ===================================================================
 * Glavna funkcija koja modifikuje WP_Query
 * ===================================================================
 */
function ovb_apply_shop_filters(WP_Query $q) {
    if (is_admin() || !$q->is_main_query() || !(is_shop() || is_product_taxonomy())) return;

    $g = static fn($k, $d = '') => isset($_GET[$k]) ? sanitize_text_field(wp_unslash($_GET[$k])) : $d;

    $filters = [
        'ci' => $g('ci'), 'co' => $g('co'), 'type' => $g('type'), 'street_name' => $g('street_name'),
        'city' => $g('city'), 'country' => $g('country'), 'capacity' => $g('capacity'),
        'bedrooms' => $g('bedrooms'), 'beds' => $g('beds'), 'bathrooms' => $g('bathrooms'),
        'min_price' => $g('min_price'), 'max_price' => $g('max_price'),
    ];

    if (empty(array_filter($filters))) return;

    // Filter po datumu (dostupnost) i dalje radi preko post__in za osnovno sužavanje rezultata
    if (!empty($filters['ci']) && !empty($filters['co']) && strtotime($filters['ci']) < strtotime($filters['co'])) {
        if (function_exists('ovb_get_products_available_between_strict')) {
            $available_ids = ovb_get_products_available_between_strict($filters['ci'], $filters['co']);
            $q->set('post__in', !empty($available_ids) ? $available_ids : [0]);
        }
    }

    // Svi filteri (uključujući i cenu) se sada prosleđuju runtime logici
    $q->set('ovb_runtime_filters', array_filter($filters));
}
add_action('pre_get_posts', 'ovb_apply_shop_filters', 20);


/**
 * ===================================================================
 * NOVI DEO: Prilagođena poruka kada nema rezultata
 * ===================================================================
 */
function ovb_custom_no_products_found_message() {
    // Proveravamo da li je bar jedan od naših filtera aktivan
    $filter_keys = ['ci', 'co', 'type', 'street_name', 'city', 'country', 'capacity', 'bedrooms', 'beds', 'bathrooms', 'min_price', 'max_price'];
    $is_filter_active = false;
    foreach ($filter_keys as $key) {
        if (!empty($_GET[$key])) {
            $is_filter_active = true;
            break;
        }
    }

    // Ako jeste, prikaži našu poruku umesto WooCommerce default poruke
    if ($is_filter_active) {
        // Uklanjamo default WooCommerce poruku
        remove_action('woocommerce_no_products_found', 'wc_no_products_found');
        
        // Dodajemo našu poruku
        echo '<div class="woocommerce-info">';
        echo esc_html__('No accommodation found matching your selection. Please try different criteria.', 'ov-booking');
        echo '</div>';
    }
}
add_action('woocommerce_no_products_found', 'ovb_custom_no_products_found_message', 5);