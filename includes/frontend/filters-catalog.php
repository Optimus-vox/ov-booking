<?php
defined('ABSPATH') || exit;

/**
 * OVB Catalog Filters – stroga AND logika, Elementor-friendly
 * - Datum filter → dostupnost u rasponu (status available/free + price>0)
 * - Ostali kriterijumi → meta/tax where moguće; fallback runtime provera iz _apartment_additional_info
 */

function ovb_meta_keys_map() : array {
    // Primarni _ovb_* ključevi i Woo _price; fallback textualno na _apartment_additional_info (u runtime-u)
    return apply_filters('ovb_meta_keys_map', [
        'street_name' => ['_ovb_street_name'], // TEXT
        'city'        => ['_ovb_city'],        // TEXT
        'country'     => ['_ovb_country'],     // TEXT
        'capacity'    => ['_ovb_max_guests'],  // NUMERIC
        'bedrooms'    => ['_ovb_bedrooms'],    // NUMERIC
        'beds'        => ['_ovb_beds'],        // NUMERIC
        'bathrooms'   => ['_ovb_bathrooms'],   // NUMERIC
        'price'       => ['_price'],           // Woo standard
        'type_meta'   => ['_ovb_accommodation_type'], // TEXT (kada nema tax)
    ]);
}

function ovb_build_numeric_or_strict(array $keys, $value, string $compare = '>=') : array {
    $or = ['relation' => 'OR'];
    foreach ($keys as $k) {
        $or[] = [
            'relation' => 'AND',
            ['key' => $k, 'compare' => 'EXISTS'],
            ['key' => $k, 'value' => $value, 'type' => 'NUMERIC', 'compare' => $compare],
        ];
    }
    return $or;
}

function ovb_build_text_or_strict(array $keys, string $value) : array {
    global $wpdb;
    $like = '%' . $wpdb->esc_like($value) . '%';
    $or = ['relation' => 'OR'];
    foreach ($keys as $k) {
        $or[] = [
            'relation' => 'AND',
            ['key' => $k, 'compare' => 'EXISTS'],
            ['key' => $k, 'value' => $like, 'compare' => 'LIKE'],
        ];
    }
    return $or;
}

function ovb_dates_list(string $start, string $end): array {
    $out = [];
    $ts  = strtotime($start);
    $te  = strtotime($end);
    if (!$ts || !$te || $ts >= $te) return $out;
    while ($ts < $te) {
        $out[] = date('Y-m-d', $ts);
        $ts    = strtotime('+1 day', $ts);
    }
    return $out;
}

function ovb_table_has_column(string $table, string $column): bool {
    global $wpdb;
    $col = $wpdb->get_var( $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column) );
    return !empty($col);
}

/** Fallback na meta kalendar – striktno (svi dani status OK + price>0). Ako nema kalendara, NE prolazi. */
function ovb_products_available_via_meta(array $product_ids, array $dates): array {
    if (empty($product_ids) || empty($dates)) return [];
    $ok = [];
    foreach ($product_ids as $pid) {
        $cal = get_post_meta($pid, '_ovb_calendar_data', true);
        if (is_string($cal)) $cal = json_decode($cal, true);
        if (!is_array($cal) || empty($cal)) continue;

        $pass = true;
        foreach ($dates as $d) {
            $day = $cal[$d] ?? null;
            if (!$day) { $pass = false; break; }
            $st = isset($day['status']) ? strtolower((string)$day['status']) : '';
            $pr = isset($day['price'])  ? floatval($day['price']) : 0;
            if (!in_array($st, ['available','free'], true) || $pr <= 0) { $pass = false; break; }
        }
        if ($pass) $ok[] = (int) $pid;
    }
    return $ok;
}

/** Glavni date-range filter preko wp_ovb_calendar; fallback na meta */
function ovb_get_products_available_between_strict(string $start, string $end): array {
    global $wpdb;

    if (!$start || !$end || $start >= $end) return [];

    $all_ids = $wpdb->get_col("
        SELECT ID FROM {$wpdb->posts}
        WHERE post_type = 'product' AND post_status = 'publish'
    ");
    if (empty($all_ids)) return [];

    $dates = ovb_dates_list($start, $end);
    $days  = count($dates);
    if ($days <= 0) return [];

    $table     = $wpdb->prefix . 'ovb_calendar';
    $has_table = (bool) $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table) );

    if ($has_table) {
        $in_ids_sql = implode(',', array_map('intval', $all_ids));
        $has_price  = ovb_table_has_column($table, 'price');

        if ($has_price) {
            $sql = $wpdb->prepare("
                SELECT t.product_id
                FROM {$table} t
                INNER JOIN {$wpdb->posts} p ON p.ID = t.product_id
                WHERE p.post_type = 'product'
                  AND p.post_status = 'publish'
                  AND t.product_id IN ($in_ids_sql)
                  AND t.date >= %s AND t.date < %s
                GROUP BY t.product_id
                HAVING SUM(CASE WHEN (t.status IN ('available','free') AND t.price IS NOT NULL AND t.price > 0) THEN 1 ELSE 0 END) >= %d
            ", $start, $end, $days);

            $ids = array_map('intval', (array) $wpdb->get_col($sql));
            if (!empty($ids)) return $ids;

            return ovb_products_available_via_meta($all_ids, $dates);
        }

        $sql = $wpdb->prepare("
            SELECT t.product_id
            FROM {$table} t
            INNER JOIN {$wpdb->posts} p ON p.ID = t.product_id
            WHERE p.post_type = 'product'
              AND p.post_status = 'publish'
              AND t.product_id IN ($in_ids_sql)
              AND t.date >= %s AND t.date < %s
            GROUP BY t.product_id
            HAVING SUM(CASE WHEN (t.status IN ('available','free')) THEN 1 ELSE 0 END) >= %d
        ", $start, $end, $days);

        $ids_status_ok = array_map('intval', (array) $wpdb->get_col($sql));
        return ovb_products_available_via_meta($ids_status_ok ?: $all_ids, $dates);
    }

    return ovb_products_available_via_meta($all_ids, $dates);
}

/**
 * Runtime proveravač (fallback) – strogo AND:
 * - čita pojedinačne _ovb_* mete; ako ih nema, čita _apartment_additional_info i proverava polja
 * - text: street/city/country/acc_type; numeric >=: capacity/bedrooms/beds/bathrooms
 */
function ovb_runtime_post_matches($post_id, array $need) : bool {
    $info = get_post_meta($post_id, '_apartment_additional_info', true);
    if (!is_array($info)) $info = [];

    // helper: prvo probaj _ovb_* meta, pa fallback na $info
    $read_text = static function(string $meta_key, string $info_key) use ($post_id, $info) {
        $v = get_post_meta($post_id, $meta_key, true);
        if ($v !== '' && $v !== null) return (string) $v;
        return isset($info[$info_key]) ? (string) $info[$info_key] : '';
    };
    $read_num = static function(string $meta_key, string $info_key) use ($post_id, $info) {
        $v = get_post_meta($post_id, $meta_key, true);
        if ($v !== '' && $v !== null) return floatval($v);
        return isset($info[$info_key]) ? floatval($info[$info_key]) : 0;
    };

    // TEXT
    if (!empty($need['street_name'])) {
        $val = $read_text('_ovb_street_name','street_name');
        if ($val === '' || stripos($val, $need['street_name']) === false) return false;
    }
    if (!empty($need['city'])) {
        $val = $read_text('_ovb_city','city');
        if ($val === '' || stripos($val, $need['city']) === false) return false;
    }
    if (!empty($need['country'])) {
        $val = $read_text('_ovb_country','country');
        if ($val === '' || stripos($val, $need['country']) === false) return false;
    }
    if (!empty($need['type_plain'])) { // accommodation_type kao plain meta/info
        $val = $read_text('_ovb_accommodation_type','accommodation_type');
        if ($val === '' || strtolower($val) !== strtolower($need['type_plain'])) return false;
    }

    // NUMERIC >=
    if (!empty($need['capacity']) && $need['capacity'] > 0) {
        if ($read_num('_ovb_max_guests','max_guests') < $need['capacity']) return false;
    }
    if (!empty($need['bedrooms']) && $need['bedrooms'] > 0) {
        if ($read_num('_ovb_bedrooms','bedrooms') < $need['bedrooms']) return false;
    }
    if (!empty($need['beds']) && $need['beds'] > 0) {
        if ($read_num('_ovb_beds','beds') < $need['beds']) return false;
    }
    if (!empty($need['bathrooms']) && $need['bathrooms'] > 0) {
        if ($read_num('_ovb_bathrooms','bathrooms') < $need['bathrooms']) return false;
    }

    return true;
}

/** Primena filtera (radi i za main i za Elementor/Woo sekundarne upite) */
function ovb_apply_shop_filters($q) {
    if (is_admin()) return;
    if (apply_filters('ovb_skip_filters_for_query', false, $q)) return;

    $is_product_query =
        ($q->get('post_type') === 'product') ||
        (function_exists('is_shop') && is_shop()) ||
        (function_exists('is_product_taxonomy') && is_product_taxonomy()) ||
        is_post_type_archive('product');

    if (!$is_product_query) return;

    $g = static function($key, $default = '') {
        return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : $default;
    };

    $ci       = $g('ci');  $co       = $g('co');
    $type     = $g('type');
    $street   = $g('street_name');
    $city     = $g('city'); $country  = $g('country');
    $capacity = (int) ($g('capacity') ?: 0);
    $bedrooms = (int) ($g('bedrooms') ?: 0);
    $beds     = (int) ($g('beds') ?: 0);
    $bathrooms= (int) ($g('bathrooms') ?: 0);
    $price_min= $g('min_price', $g('price_min',''));
    $price_max= $g('max_price', $g('price_max',''));

    $ci_ts = $ci ? strtotime($ci) : 0;
    $co_ts = $co ? strtotime($co) : 0;
    $ci_d  = $ci_ts ? date('Y-m-d', $ci_ts) : '';
    $co_d  = $co_ts ? date('Y-m-d', $co_ts) : '';

    $has_any_filter =
        ($ci_d && $co_d && $ci_d < $co_d) ||
        $type !== '' || $street !== '' || $city !== '' || $country !== '' ||
        $capacity > 0 || $bedrooms > 0 || $beds > 0 || $bathrooms > 0 ||
        $price_min !== '' || $price_max !== '';

    if (!$has_any_filter) return;

    $tax_query  = (array) $q->get('tax_query');
    $meta_query = (array) $q->get('meta_query');
    if (empty($meta_query) || !isset($meta_query['relation'])) {
        $meta_query = ['relation' => 'AND'];
    }
    $has_meta_filters = false;

    $keys = ovb_meta_keys_map();

    // 1) Type: taksonomija (ako postoji); inače meta/fallback
    $type_tax = function_exists('ovb_detect_type_taxonomy') ? ovb_detect_type_taxonomy() : '';
    $type_plain = '';
    if ($type !== '') {
        if ($type_tax) {
            $tax_query[] = [
                'taxonomy' => $type_tax,
                'field'    => 'slug',
                'terms'    => $type,
                'operator' => 'IN',
            ];
        } else {
            // pokušaćemo preko _ovb_accommodation_type (TEXT) …
            if (!empty($keys['type_meta'])) {
                $meta_query[] = ovb_build_text_or_strict($keys['type_meta'], $type);
                $has_meta_filters = true;
            }
            // … a dodatno runtime (strogo jednak) nad _apartment_additional_info (vidi ovb_runtime_post_matches)
            $type_plain = $type;
        }
    }

    // 2) Street/City/Country: prvo probaj takse (za city/country), pa meta LIKE; street isključivo meta/runtime
    $city_tax    = function_exists('ovb_detect_city_taxonomy') ? ovb_detect_city_taxonomy() : '';
    $country_tax = function_exists('ovb_detect_country_taxonomy') ? ovb_detect_country_taxonomy() : '';

    if ($country !== '') {
        if ($country_tax) {
            $tax_query[] = ['taxonomy'=>$country_tax, 'field'=>'name', 'terms'=>$country, 'operator'=>'IN'];
        } else {
            if (!empty($keys['country'])) {
                $meta_query[] = ovb_build_text_or_strict($keys['country'], $country);
                $has_meta_filters = true;
            }
        }
    }
    if ($city !== '') {
        if ($city_tax) {
            $tax_query[] = ['taxonomy'=>$city_tax, 'field'=>'name', 'terms'=>$city, 'operator'=>'IN'];
        } else {
            if (!empty($keys['city'])) {
                $meta_query[] = ovb_build_text_or_strict($keys['city'], $city);
                $has_meta_filters = true;
            }
        }
    }
    if ($street !== '') {
        if (!empty($keys['street_name'])) {
            $meta_query[] = ovb_build_text_or_strict($keys['street_name'], $street);
            $has_meta_filters = true;
        }
    }

    // 3) Numerički >= + EXISTS (capacity/bedrooms/beds/bathrooms)
    if ($capacity > 0)  { $meta_query[] = ovb_build_numeric_or_strict($keys['capacity'],  $capacity);  $has_meta_filters = true; }
    if ($bedrooms > 0)  { $meta_query[] = ovb_build_numeric_or_strict($keys['bedrooms'],  $bedrooms);  $has_meta_filters = true; }
    if ($beds > 0)      { $meta_query[] = ovb_build_numeric_or_strict($keys['beds'],      $beds);      $has_meta_filters = true; }
    if ($bathrooms > 0) { $meta_query[] = ovb_build_numeric_or_strict($keys['bathrooms'], $bathrooms); $has_meta_filters = true; }

    // 4) Woo price BETWEEN
    if ($price_min !== '' || $price_max !== '') {
        $min = ($price_min !== '') ? (float) $price_min : 0;
        $max = ($price_max !== '') ? (float) $price_max : PHP_FLOAT_MAX;
        $or = ['relation' => 'OR'];
        foreach ($keys['price'] as $k) {
            $or[] = ['key' => $k, 'value' => [$min, $max], 'type' => 'NUMERIC', 'compare' => 'BETWEEN'];
        }
        $meta_query[] = $or;
        $has_meta_filters = true;
    }

    // 5) Datumi → post__in presek
    if ($ci_d && $co_d && $ci_d < $co_d) {
        $available_ids = ovb_get_products_available_between_strict($ci_d, $co_d);
        $existing_in = (array) $q->get('post__in');
        if (!empty($existing_in)) {
            $available_ids = array_values(array_intersect($available_ids, array_map('intval', $existing_in)));
        }
        if (empty($available_ids)) $available_ids = [0];
        $q->set('post__in', $available_ids);
    }

    // 6) Upiti
    if (!empty($tax_query))  $q->set('tax_query',  $tax_query);
    if ($has_meta_filters)   $q->set('meta_query', $meta_query);

    // 7) Runtime fallback AND filter (kada nema _ovb_* metapodataka i/ili su polja u _apartment_additional_info)
    $need = [
        'street_name' => $street,
        'city'        => $city,
        'country'     => $country,
        'capacity'    => $capacity,
        'bedrooms'    => $bedrooms,
        'beds'        => $beds,
        'bathrooms'   => $bathrooms,
        'type_plain'  => $type_tax ? '' : $type, // koristi se samo ako nema taksa
    ];
    // Ako je barem jedno polje potencijalno samo u _apartment_additional_info, aktiviraj runtime clean
    if ($street !== '' || $city !== '' || $country !== '' || $capacity>0 || $bedrooms>0 || $beds>0 || $bathrooms>0 || (!$type_tax && $type!=='')) {
        $q->set('ovb_runtime_filters', $need);
        add_filter('the_posts','ovb_runtime_filter_posts',99,2);
    }
}

add_action('pre_get_posts',             'ovb_apply_shop_filters', 15);
add_action('woocommerce_product_query', 'ovb_apply_shop_filters', 15);

/**
 * Runtime filter koji strogo AND-uje rezultatski skup; radi na malom broju postova (već presečeno datumiem/tax/meta)
 */
function ovb_runtime_filter_posts(array $posts, WP_Query $q) : array {
    $need = $q->get('ovb_runtime_filters');
    if (empty($need) || !is_array($need)) return $posts;
    if (empty($posts)) return $posts;

    $filtered = [];
    foreach ($posts as $p) {
        if ($p instanceof WP_Post && $p->post_type === 'product') {
            if (ovb_runtime_post_matches($p->ID, $need)) {
                $filtered[] = $p;
            }
        } else {
            $filtered[] = $p; // ne diraj druge
        }
    }
    return $filtered;
}

/** Sortiranje po ceni */
add_filter('woocommerce_get_catalog_ordering_args', function ($args, $orderby, $order) {
    $orderby_param = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : '';
    switch ($orderby_param) {
        case 'price':
        case 'price-asc':
            $args['orderby']='meta_value_num'; $args['order']='asc';  $args['meta_key']='_price'; break;
        case 'price-desc':
            $args['orderby']='meta_value_num'; $args['order']='desc'; $args['meta_key']='_price'; break;
        default: break;
    }
    return $args;
}, 20, 3);


if (!function_exists('ovb_debug_log')) {
    function ovb_debug_log($msg){ if (defined('WP_DEBUG') && WP_DEBUG) error_log('[OVB] '.$msg); }
}

function ovb_runtime_filter_posts(array $posts, WP_Query $q) : array {
    $need = $q->get('ovb_runtime_filters');
    if (empty($need) || !is_array($need)) return $posts;

    ovb_debug_log('Runtime NEED='.wp_json_encode($need).' posts_in='.count($posts));
    $out = [];
    foreach ($posts as $p) {
        if ($p instanceof WP_Post && $p->post_type === 'product') {
            $pass = ovb_runtime_post_matches($p->ID, $need);
            ovb_debug_log("Check #{$p->ID} => ".($pass?'PASS':'FAIL'));
            if ($pass) $out[] = $p;
        } else {
            $out[] = $p;
        }
    }
    ovb_debug_log('Runtime OUT='.count($out));
    return $out;
}