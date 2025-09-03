<?php
defined('ABSPATH') || exit;

/**
 * OVB Catalog Filters – hardened & regression-safe
 * - Datumi: ovb_get_products_available_between_strict() ili fallback nad kalendarom
 * - Cene: min/max iz _ovb_calendar_data/_ov_calendar_data; proizvodi bez cena se isključuju kad je min/max aktivan
 * - Tekst polja (street/city/country): case-insensitive contains; meta_query: (primary OR aggregate) za svako polje, AND između polja
 * - Type: exact (case-insensitive) preko meta_query + runtime potvrda
 * - Numerika: >= ; short-circuit ako je zahtev > globalnog maksimuma
 * - "No results" poruka za bilo koji aktivan filter (uključujući alias imena)
 * - Detaljni logovi (samo kad je WP_DEBUG)
 * - ISPRAVKE: Resetovanje rezultata kad nema match-a, validacija cena, pravilno prekidanje query-ja
 */

if (!function_exists('ovb_log_error')) {
    function ovb_log_error($message, $context = 'filters') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[OVB][' . $context . '] ' . (is_string($message) ? $message : print_r($message, true)));
        }
    }
}

// ========================= Meta mape =========================
function ovb_meta_keys_map(): array {
    return [
        'street_name' => '_ovb_street_name',
        'city'        => '_ovb_city',
        'country'     => '_ovb_country',
        'capacity'    => '_ovb_max_guests',
        'bedrooms'    => '_ovb_bedrooms',
        'beds'        => '_ovb_beds',
        'bathrooms'   => '_ovb_bathrooms',
        'price'       => '_price',
        'type_meta'   => '_ovb_accommodation_type',
    ];
}

// ========================= Aggregated (meta box) subkeys =========================
function ovb_aggregated_subkeys(): array {
    return [
        'street_name' => 'street_name',
        'city'        => 'city',
        'country'     => 'country',
        'capacity'    => 'max_guests',
        'bedrooms'    => 'bedrooms',
        'beds'        => 'beds',
        'bathrooms'   => 'bathrooms',
        'type'        => 'accommodation_type',
    ];
}

// ========================= Aliasi GET parametara =========================
function ovb_filter_aliases(): array {
    return [
        'type'        => ['type','accommodation_type','accommodation-type','acc_type'],
        'street_name' => ['street_name','street','address','ulica'],
        'city'        => ['city','grad'],
        'country'     => ['country','drzava','county'],
        'capacity'    => ['capacity'],
        'bedrooms'    => ['bedrooms','rooms'],
        'beds'        => ['beds'],
        'bathrooms'   => ['bathrooms','baths'],
        'min_price'   => ['min_price','price_min','min'],
        'max_price'   => ['max_price','price_max','max'],
        'ci'          => ['ci','check_in','checkin'],
        'co'          => ['co','check_out','checkout'],
    ];
}

// ========================= Podrazumevane vrednosti filtera =========================
function ovb_filters_defaults(): array {
    return [
        'ci' => '', 'co' => '',
        'type' => '',
        'street_name' => '', 'city' => '', 'country' => '',
        'capacity' => '', 'bedrooms' => '', 'beds' => '', 'bathrooms' => '',
        'min_price' => '', 'max_price' => '',
    ];
}

// Vrati prvu popunjenu vrednost iz GET prema listi aliasa
function ovb_get_request_value(array $names, $default = '') {
    foreach ($names as $n) {
        if (isset($_GET[$n]) && $_GET[$n] !== '') {
            return sanitize_text_field(wp_unslash($_GET[$n]));
        }
    }
    return $default;
}

// Normalizuj sve filtere (sa aliasima) i popuni defaulte
function ovb_normalize_filters_from_request(): array {
    $aliases = ovb_filter_aliases();
    $out = [];
    foreach ($aliases as $key => $keys) {
        $val = ovb_get_request_value($keys, '');
        $out[$key] = is_string($val) ? trim($val) : '';
    }
    // uvek popuni sve ključeve
    $out = wp_parse_args($out, ovb_filters_defaults());
    return $out;
}

// Vraća vrednost polja iz meta ili iz agregiranog meta boxa
function ovb_post_get_field(int $post_id, string $field) {
    $map = ovb_meta_keys_map();
    if (isset($map[$field])) {
        $val = get_post_meta($post_id, $map[$field], true);
        if ($val !== '' && $val !== null) return $val;
    }
    $sub = ovb_aggregated_subkeys();
    if (isset($sub[$field])) {
        $raw = get_post_meta($post_id, '_apartment_additional_info', true);
        if (!is_array($raw)) $raw = maybe_unserialize($raw);
        return (is_array($raw) && array_key_exists($sub[$field], $raw)) ? $raw[$sub[$field]] : null;
    }
    return null;
}

// ========================= Calendar helpers (fallback safe) =========================
if (!function_exists('ovb_get_calendar_data')) {
    function ovb_get_calendar_data(int $post_id): array {
        return _ovb_caldata_fallback($post_id);
    }
}

function _ovb_caldata_fallback(int $post_id): array {
    $keys = ['_ovb_calendar_data', '_ov_calendar_data'];
    foreach ($keys as $meta_key) {
        $raw = get_post_meta($post_id, $meta_key, true);
        if (empty($raw)) continue;

        if (is_array($raw)) return $raw;

        $maybe = @maybe_unserialize($raw);
        if (is_array($maybe)) return $maybe;

        $maybe = json_decode((string)$raw, true);
        if (is_array($maybe)) return $maybe;
    }
    return [];
}

// ========================= Availability fallback (kada helper ne postoji) =========================
function ovb_is_available_for_range(int $post_id, string $ci, string $co): bool {
    if ($ci === '' || $co === '') return true;
    try {
        $start = new DateTime($ci);
        $end   = new DateTime($co);
        if ($start >= $end) return false;
    } catch (Exception $e) {
        ovb_log_error('Invalid CI/CO: '.$e->getMessage(), 'avail');
        return false;
    }

    $data = ovb_get_calendar_data($post_id);
    if (empty($data)) return false;

    $period = new DatePeriod($start, new DateInterval('P1D'), $end); // [ci..co)
    foreach ($period as $d) {
        $day = $d->format('Y-m-d');
        if (!isset($data[$day]['status'])) return false;
        $status = strtolower((string)$data[$day]['status']);
        if ($status !== 'available') return false;
    }
    return true;
}

// ========================= GLOBAL price bounds (sa kešom) =========================
function ovb_get_global_price_bounds(): ?array {
    $tkey = 'ovb_price_bounds_v1';
    $cached = get_transient($tkey);
    if (is_array($cached) && array_key_exists('min',$cached) && array_key_exists('max',$cached)) {
        return $cached;
    }

    // prikupi sve product ID-eve
    $ids = get_posts([
        'post_type'           => 'product',
        'post_status'         => 'publish',
        'fields'              => 'ids',
        'nopaging'            => true,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'suppress_filters'    => true,
    ]);

    $gmin = null; $gmax = null;
    foreach ((array)$ids as $pid) {
        $data = ovb_get_calendar_data((int)$pid);
        if (empty($data) || !is_array($data)) continue;
        foreach ($data as $entry) {
            if (is_array($entry) && isset($entry['price']) && $entry['price'] !== '' && $entry['price'] !== null) {
                $p = (float)$entry['price'];
                if ($gmin === null || $p < $gmin) $gmin = $p;
                if ($gmax === null || $p > $gmax) $gmax = $p;
            }
        }
    }

    $res = ['min' => $gmin, 'max' => $gmax];
    set_transient($tkey, $res, 10 * MINUTE_IN_SECONDS);
    ovb_log_error(['bounds'=>$res], 'price-bounds');
    return $res;
}

// ========================= Pre-validacija unosa =========================
function ovb_get_global_max_for(string $field): ?int {
    global $wpdb;
    $map = ovb_meta_keys_map();
    if (!isset($map[$field])) return null;
    $meta_key = esc_sql($map[$field]);

    $sql = $wpdb->prepare(
        "SELECT MAX(CAST(pm.meta_value AS UNSIGNED))
         FROM {$wpdb->postmeta} pm
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = %s AND p.post_type = 'product' AND p.post_status = 'publish'",
         $meta_key
    );
    $max = $wpdb->get_var($sql);
    return is_null($max) ? null : (int)$max;
}

// ISPRAVKA: Ako korisnik traži > globalnog maksimuma – odmah prazan set
function ovb_validate_numeric_bounds(array $filters): ?array {
    foreach (['capacity','bedrooms','beds','bathrooms'] as $key) {
        $raw = $filters[$key] ?? '';
        if ($raw === '' || !is_numeric($raw)) continue;
        $wanted = (int) $raw;
        if ($wanted <= 0) continue;

        $max = ovb_get_global_max_for($key);
        if ($max !== null && $wanted > $max) {
            ovb_log_error("Validation fail: {$key}={$wanted} > global_max={$max}", 'validation');
            return ['empty_ids' => [0], 'reason' => $key.'_gt_global_max', 'max' => $max];
        }
    }
    return null;
}

// ISPRAVKA: Validacija price bounds-a prema GLOBALNIM cenama (iz kalendara)
function ovb_validate_price_bounds(array $filters): ?array {
    $has_min = ($filters['min_price'] !== '' && is_numeric($filters['min_price']));
    $has_max = ($filters['max_price'] !== '' && is_numeric($filters['max_price']));
    if (!$has_min && !$has_max) return null;

    $bounds = ovb_get_global_price_bounds();
    if (!is_array($bounds) || $bounds['min'] === null || $bounds['max'] === null) {
        // nema uopšte cena u sistemu – ništa ne short-circuit-ujemo
        return null;
    }

    $gmin = (float)$bounds['min'];
    $gmax = (float)$bounds['max'];
    $umin = $has_min ? (float)$filters['min_price'] : null;
    $umax = $has_max ? (float)$filters['max_price'] : null;

    // ISPRAVKA: neugodne granice -> prazan set
    if ($has_min && $umin > $gmax) {
        ovb_log_error("Price validation fail: min_price={$umin} > global_max={$gmax}", 'validation');
        return ['empty_ids' => [0], 'reason' => 'min_price_above_global_max', 'bounds' => $bounds];
    }
    if ($has_max && $umax < $gmin) {
        ovb_log_error("Price validation fail: max_price={$umax} < global_min={$gmin}", 'validation');
        return ['empty_ids' => [0], 'reason' => 'max_price_below_global_min', 'bounds' => $bounds];
    }
    if ($has_min && $has_max && $umin > $umax) {
        ovb_log_error("Price validation fail: min_price={$umin} > max_price={$umax}", 'validation');
        return ['empty_ids' => [0], 'reason' => 'price_range_invalid', 'bounds' => $bounds];
    }
    return null;
}

// ========================= Runtime matching (posle osnovnog WP_Query) =========================
function ovb_runtime_post_matches(WP_Post $post, array $filters, WP_Query $q): bool {
    // Popuni default vrednosti da NIKAD ne nedostaje ključ
    $filters = wp_parse_args($filters, ovb_filters_defaults());
    $post_id = (int) $post->ID;

    // Availability fallback (samo ako nema helpera)
    $need_avail_check = (bool) $q->get('ovb_check_availability_runtime');
    if ($need_avail_check && $filters['ci'] !== '' && $filters['co'] !== '') {
        if (!ovb_is_available_for_range($post_id, $filters['ci'], $filters['co'])) {
            ovb_log_error("{$post_id} rejected: availability fail for {$filters['ci']}..{$filters['co']}", 'avail');
            return false;
        }
    }

    // tekstualna: contains (case-insensitive)
    $check_text = function (string $field_key, $filter_value) use ($post_id): bool {
        if ($filter_value === '' || $filter_value === null) return true;
        $filter_value = (string) $filter_value;
        if ($filter_value === '') return true;
        $pv = ovb_post_get_field($post_id, $field_key);
        $ok = is_string($pv) && stripos($pv, $filter_value) !== false;
        if (!$ok) ovb_log_error("{$post_id} rejected: text '{$field_key}' !contains '{$filter_value}' (got: ".print_r($pv, true).")", 'text');
        return $ok;
    };

    // numerička: >=
    $check_numeric = function (string $field_key, $filter_value) use ($post_id): bool {
        if ($filter_value === '' || $filter_value === null) return true;
        if (!is_numeric($filter_value) || (int)$filter_value <= 0) return true;
        $pv = ovb_post_get_field($post_id, $field_key);
        $ok = is_numeric($pv) && (int)$pv >= (int)$filter_value;
        if (!$ok) ovb_log_error("{$post_id} rejected: numeric '{$field_key}' < wanted {$filter_value} (got: ".print_r($pv, true).")", 'numeric');
        return $ok;
    };

    // ISPRAVLJENA PRICE PROVERA
    $check_price = function ($min_price, $max_price, $start_date, $end_date) use ($post_id): bool {
        $has_min_filter = ($min_price !== '' && is_numeric($min_price));
        $has_max_filter = ($max_price !== '' && is_numeric($max_price));

        if (!$has_min_filter && !$has_max_filter) {
            return true; // Nema filtera za cenu, preskoči proveru
        }

        $data = ovb_get_calendar_data($post_id);
        if (empty($data)) {
            ovb_log_error("{$post_id} rejected: no calendar data but price filter present", 'price');
            return false; // Ima filter za cenu, ali proizvod nema kalendar
        }
        
        $allPrices = [];
        foreach ($data as $day => $entry) {
            if (is_array($entry) && isset($entry['price']) && is_numeric($entry['price'])) {
                $allPrices[$day] = (float)$entry['price'];
            }
        }

        if (empty($allPrices)) {
            ovb_log_error("{$post_id} rejected: calendar has no valid 'price' entries", 'price');
            return false; // Ima filter za cenu, ali kalendar nema cene
        }
        
        $min = $has_min_filter ? (float)$min_price : null;
        $max = $has_max_filter ? (float)$max_price : null;

        // Provera da li bilo koja cena u kalendaru zadovoljava uslov
        foreach ($allPrices as $p) {
            $passes = true;
            if ($min !== null && $p < $min) $passes = false;
            if ($max !== null && $p > $max) $passes = false;
            
            if ($passes) {
                return true; // Pronađena je bar jedna cena koja odgovara, proizvod je validan
            }
        }
        
        ovb_log_error("{$post_id} rejected: no day price found in range [{$min_price}, {$max_price}]", 'price');
        return false; // Nijedna cena ne odgovara
    };

    // TYPE (case-insensitive exact)
    $typeRaw = (string) ($filters['type'] ?? '');
    if ($typeRaw !== '') {
        $wanted = strtolower($typeRaw);
        $have   = ovb_post_get_field($post_id, 'type');
        $okType = (is_string($have) && strtolower(trim($have)) === $wanted);

        if (!$okType) {
            $metaType = get_post_meta($post_id, ovb_meta_keys_map()['type_meta'], true);
            if (is_string($metaType) && strtolower(trim($metaType)) === $wanted) {
                $okType = true;
            }
        }

        if (!$okType) {
            ovb_log_error("{$post_id} rejected: type mismatch (wanted='{$wanted}', got='".print_r($have ?? '(none)', true)."')", 'type');
            return false;
        }
    }

    if (!$check_text('street_name', $filters['street_name'])) return false;
    if (!$check_text('city',        $filters['city']))        return false;
    if (!$check_text('country',     $filters['country']))     return false;

    if (!$check_numeric('capacity',  $filters['capacity']))   return false;
    if (!$check_numeric('bedrooms',  $filters['bedrooms']))   return false;
    if (!$check_numeric('beds',      $filters['beds']))       return false;
    if (!$check_numeric('bathrooms', $filters['bathrooms']))  return false;

    if (!$check_price($filters['min_price'], $filters['max_price'], $filters['ci'], $filters['co'])) {
        return false;
    }

    return true;
}

// ISPRAVKA: Filtriraj dobijene postove runtime proverom i FORSIRAJ prazno kad nema rezultata
function ovb_runtime_filter_posts(array $posts, WP_Query $q): array {
    $filters = (array) $q->get('ovb_runtime_filters');
    $filters = wp_parse_args($filters, ovb_filters_defaults());
    if (empty(array_filter($filters, fn($v) => $v !== '' && $v !== null))) {
        return $posts;
    }

    $out = [];
    foreach ($posts as $post) {
        if ($post instanceof WP_Post && $post->post_type === 'product') {
            if (ovb_runtime_post_matches($post, $filters, $q)) {
                $out[] = $post;
            }
        } else {
            $out[] = $post;
        }
    }

    // ISPRAVKA: Kad nema rezultata, obeleži u query i osiguraj se da je prazan
    if (empty($out)) {
        $q->set('ovb_no_results_due_to_filters', true);
        $q->found_posts = 0;
        $q->max_num_pages = 0;
        ovb_log_error("Runtime filter produced empty results", 'runtime-empty');
    }

    return $out;
}

// ISPRAVKA: "Probe" za tekstualne filtere (spreči "ostaju stari itemi") - sa boljom validacijom
function ovb_probe_text_filters_have_match(array $filters): bool {
    $text_fields = ['street_name','city','country'];
    $clauses = ['relation' => 'AND'];
    $hasAny = false;
    
    foreach ($text_fields as $tf) {
        if ($filters[$tf] !== '') {
            $hasAny = true;
            $clauses[] = [
                'relation' => 'OR',
                [
                    'key'     => ovb_meta_keys_map()[$tf],
                    'value'   => $filters[$tf],
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => '_apartment_additional_info',
                    'value'   => $filters[$tf],
                    'compare' => 'LIKE',
                ],
            ];
        }
    }
    if (!$hasAny) return true;

    // Izbegni rekurziju pre_get_posts hook-a:
    remove_action('pre_get_posts', 'ovb_apply_shop_filters', 99);
    $probe = new WP_Query([
        'post_type'           => 'product',
        'post_status'         => 'publish',
        'fields'              => 'ids',
        'posts_per_page'      => 1,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'suppress_filters'    => true,
        'meta_query'          => $clauses,
    ]);
    add_action('pre_get_posts', 'ovb_apply_shop_filters', 99);

    $ok = $probe->have_posts();
    ovb_log_error(['text_filters'=>array_filter($filters, fn($v,$k) => in_array($k, $text_fields) && $v !== '', ARRAY_FILTER_USE_BOTH), 'has_match'=>$ok], 'text-probe');
    
    // ISPRAVKA: Kad nema match-a, loguj razlog
    if (!$ok) {
        ovb_log_error("Text probe failed - no matches for filters", 'text-probe-fail');
    }
    
    return $ok;
}

// ========================= Glavna: modifikuj WP_Query =========================
function ovb_apply_shop_filters(WP_Query $q) {
    if (is_admin() || !$q->is_main_query() || !(is_shop() || is_product_taxonomy())) return;

    // Normalizuj sve filtere (sa aliasima) + defaulti
    $filters = ovb_normalize_filters_from_request();

    // Ako nema nijednog našeg filtera – izlaz
    if (empty(array_filter($filters, fn($v) => $v !== '' && $v !== null))) {
        return;
    }

    ovb_log_error(['GET' => $_GET, 'normalized_filters' => $filters], 'apply');

    // 1) ISPRAVKA: Numerički short-circuit (capacity/bedrooms/beds/bathrooms > global max) - pre svega ostalog
    if ($fail = ovb_validate_numeric_bounds($filters)) {
        $q->set('post__in', $fail['empty_ids']);
        $q->set('ovb_validation_fail', $fail);
        $q->set('ovb_no_results_due_to_filters', true);
        ovb_log_error("Query short-circuited due to numeric validation fail: " . print_r($fail, true), 'short-circuit');
        return;
    }

    // 2) ISPRAVKA: PRICE short-circuit prema GLOBAL min/max iz kalendara
    if ($pfail = ovb_validate_price_bounds($filters)) {
        $q->set('post__in', $pfail['empty_ids']);
        $q->set('ovb_validation_fail', $pfail);
        $q->set('ovb_no_results_due_to_filters', true);
        ovb_log_error("Query short-circuited due to price validation fail: " . print_r($pfail, true), 'short-circuit');
        return;
    }

    // 3) TYPE – suzi već u WP_Query (primarni meta + fallback na agregat LIKE)
    if ($filters['type'] !== '') {
        $wanted = $filters['type'];
        $map    = ovb_meta_keys_map();

        $mq_type = [
            'relation' => 'OR',
            [
                'key'     => $map['type_meta'],
                'value'   => $wanted,
                'compare' => '=',
            ],
            [
                'key'     => '_apartment_additional_info',
                'value'   => $wanted,
                'compare' => 'LIKE',
            ],
        ];

        $existing_mq = (array) $q->get('meta_query');
        $existing_mq[] = $mq_type;
        $q->set('meta_query', $existing_mq);
        ovb_log_error(['type_wanted' => $wanted, 'meta_query' => $existing_mq], 'type-mq');
    }

    // 4) ISPRAVKA: Tekst polja – (primary LIKE OR aggregate LIKE) za svako aktivno, AND između polja - sa boljom validacijom
    $text_fields = ['street_name','city','country'];
    $group = ['relation' => 'AND'];
    $has_text_any = false;
    foreach ($text_fields as $tf) {
        if ($filters[$tf] !== '') {
            $has_text_any = true;
            $group[] = [
                'relation' => 'OR',
                [
                    'key'     => ovb_meta_keys_map()[$tf],
                    'value'   => $filters[$tf],
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => '_apartment_additional_info',
                    'value'   => $filters[$tf],
                    'compare' => 'LIKE',
                ],
            ];
        }
    }
    if ($has_text_any) {
        $existing_mq = (array) $q->get('meta_query');
        $existing_mq[] = $group;
        $q->set('meta_query', $existing_mq);
        ovb_log_error(['text_group_meta_query' => $existing_mq], 'text-mq');

        // ISPRAVKA: PROBE sa direktnim prekidom kada nema match-a
        if (!ovb_probe_text_filters_have_match($filters)) {
            $q->set('post__in', [0]);
            $q->set('ovb_validation_fail', ['reason' => 'text_no_match']);
            $q->set('ovb_no_results_due_to_filters', true);
            ovb_log_error("Query short-circuited due to text probe fail", 'short-circuit');
            return;
        }
    }

    // 5) Datumi
    $have_dates = ($filters['ci'] !== '' && $filters['co'] !== '' && strtotime($filters['ci']) < strtotime($filters['co']));
    if ($have_dates) {
        if (function_exists('ovb_get_products_available_between_strict')) {
            $ids = (array) ovb_get_products_available_between_strict($filters['ci'], $filters['co']);
            if (empty($ids)) {
                $q->set('post__in', [0]);
                $q->set('ovb_no_results_due_to_filters', true);
                ovb_log_error("Query short-circuited - no available products for date range", 'short-circuit');
                return;
            }
            $q->set('post__in', $ids);
        } else {
            $q->set('ovb_check_availability_runtime', true);
        }
    }

    // 6) Runtime filteri – prosleđujemo kompletan set normalizovanih vrednosti
    $q->set('ovb_runtime_filters', $filters);
}
add_action('pre_get_posts', 'ovb_apply_shop_filters', 99);

// ISPRAVKA: Primeni runtime filtriranje na već dobijene postove sa boljim handling-om praznih rezultata
function ovb_filter_the_posts(array $posts, WP_Query $q): array {
    return ovb_runtime_filter_posts($posts, $q);
}
add_filter('the_posts', 'ovb_filter_the_posts', 10, 2);

// ========================= ISPRAVKA: Poruka kada nema rezultata - bolje prepoznavanje aktivnih filtera =========================
function ovb_custom_no_products_found_message() {
    // ISPRAVKA: Proveravaj i normalizovane filtere i aliase
    $filters = ovb_normalize_filters_from_request();
    $active = !empty(array_filter($filters, fn($v) => $v !== '' && $v !== null));

    if ($active) {
        remove_action('woocommerce_no_products_found', 'wc_no_products_found');

        // Prevodiva poruka (možeš menjaš kroz filter ispod)
        $message = apply_filters(
            'ovb_no_results_message',
            __('No accommodation matches your criteria. Please adjust filters and try again.', 'ov-booking')
        );

        echo '<div class="woocommerce-info ovb-no-results">';
        echo esc_html($message);
        echo '</div>';

        ovb_log_error(['active_filters' => array_filter($filters), 'showing_no_results_message' => true], 'no-results-message');
    }
}
add_action('woocommerce_no_products_found', 'ovb_custom_no_products_found_message', 5);

// ISPRAVKA: Dodatno osiguravanje da se found_posts resetuje kad su filteri neuspešni
function ovb_reset_found_posts_on_filter_fail($found_posts, WP_Query $q) {
    if (!$q->is_main_query() || !(is_shop() || is_product_taxonomy())) {
        return $found_posts;
    }
    
    // Ako je query označen kao neuspešan zbog validacije ili filtera, forsiraj found_posts = 0
    if ($q->get('ovb_no_results_due_to_filters') || $q->get('ovb_validation_fail')) {
        ovb_log_error("Forcing found_posts = 0 due to filter validation fail", 'found-posts-reset');
        return 0;
    }
    
    return $found_posts;
}
add_filter('found_posts', 'ovb_reset_found_posts_on_filter_fail', 10, 2);

// ========================= ISPRAVKA: Hook za AJAX pozive =========================
function ovb_handle_ajax_filter_request() {
    // Detektuj AJAX poziv
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch') {
        // Ovo je AJAX poziv, dodaj header da se zna
        if (!headers_sent()) {
            header('X-OVB-AJAX: 1');
        }
    }
}
add_action('init', 'ovb_handle_ajax_filter_request');

// ========================= ISPRAVKA: Forsiraj prazan rezultat kad je potrebno =========================
function ovb_force_empty_when_no_results($posts, WP_Query $q) {
    if (!$q->is_main_query() || !(is_shop() || is_product_taxonomy())) {
        return $posts;
    }
    
    // Ako je označeno da nema rezultata zbog filtera, forsiraj prazan niz
    if ($q->get('ovb_no_results_due_to_filters') || $q->get('ovb_validation_fail')) {
        ovb_log_error("Forcing empty posts array due to filter validation fail", 'force-empty');
        $q->found_posts = 0;
        $q->max_num_pages = 0;
        return [];
    }
    
    return $posts;
}
add_filter('posts_results', 'ovb_force_empty_when_no_results', 10, 2);