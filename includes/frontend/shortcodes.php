<?php
defined('ABSPATH') || exit;

add_shortcode('ovb_shop_filters', function () {

    // Helper za čitanje i sanitizaciju GET parametara
    $g = static function($k, $d = '') {
        return isset($_GET[$k]) ? sanitize_text_field(wp_unslash($_GET[$k])) : $d;
    };

    // Čitanje vrednosti bez konverzije u int (0)
    $ci        = $g('ci');
    $co        = $g('co');
    $type_val  = $g('type');
    $city      = $g('city');
    $country   = $g('country');
    $street    = $g('street_name');
    $capacity  = $g('capacity');
    $bedrooms  = $g('bedrooms');
    $beds      = $g('beds');
    $bathrooms = $g('bathrooms');
    $min_price = $g('min_price', $g('price_min', ''));
    $max_price = $g('max_price', $g('price_max', ''));


    // Detect accommodation/type taxonomy (if any)
    $type_tax = '';
    if (function_exists('ovb_detect_type_taxonomy')) {
        $type_tax = (string) ovb_detect_type_taxonomy();
    }

    // Ako je detektovan Woo product_type (ne koristimo ga za ovo) ili nepostojeća taksonomija -> poništi
    if ($type_tax === 'product_type' || !taxonomy_exists($type_tax)) {
        $type_tax = '';
    }

    $types = [];
    if ($type_tax) {
        $terms = get_terms([
            'taxonomy'   => $type_tax,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);
        if (!is_wp_error($terms) && $terms) {
            $types = $terms;
        }
    }

    // Action ka shop arhivi
    $action = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : get_post_type_archive_link('product');
    $action = esc_url($action ?: home_url('/'));

    // statična lista za fallback type
    $fallback_types = [
        'apartment' => __('Apartment','ov-booking'),
        'house'     => __('House','ov-booking'),
        'villa'     => __('Villa','ov-booking'),
        'cottage'   => __('Cottage','ov-booking'),
        'studio'    => __('Studio','ov-booking'),
    ];

    ob_start(); ?>
<form class="ovb-shop-filters" method="get" action="<?php echo $action; ?>" style="display:grid; gap:12px;" novalidate>
    <div>
        <label for="ovb-ci"><?php echo esc_html__('Check-in', 'ov-booking'); ?></label>
        <input id="ovb-ci" type="date" name="ci" value="<?php echo esc_attr($ci); ?>" />
    </div>
    <div>
        <label for="ovb-co"><?php echo esc_html__('Check-out', 'ov-booking'); ?></label>
        <input id="ovb-co" type="date" name="co" value="<?php echo esc_attr($co); ?>" />
    </div>

    <div>
        <label for="ovb-type"><?php echo esc_html__('Accommodation Type', 'ov-booking'); ?></label>
        <?php if ($type_tax && !empty($types)) : ?>
        <select id="ovb-type" name="type">
            <option value=""><?php echo esc_html__('Any', 'ov-booking'); ?></option>
            <?php foreach ($types as $t): ?>
            <option value="<?php echo esc_attr($t->slug); ?>" <?php selected($type_val, $t->slug); ?>>
                <?php echo esc_html($t->name); ?></option>
            <?php endforeach; ?>
        </select>
        <?php else: ?>
        <select id="ovb-type" name="type">
            <option value=""><?php echo esc_html__('Any', 'ov-booking'); ?></option>
            <?php foreach ($fallback_types as $slug => $label): ?>
            <option value="<?php echo esc_attr($slug); ?>" <?php selected($type_val, $slug); ?>>
                <?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <div>
        <label for="ovb-street"><?php echo esc_html__('Street', 'ov-booking'); ?></label>
        <input id="ovb-street" type="text" name="street_name" value="<?php echo esc_attr($street); ?>"
            placeholder="<?php echo esc_attr__('Knez Mihailova', 'ov-booking'); ?>" />
    </div>
    <div>
        <label for="ovb-city"><?php echo esc_html__('City', 'ov-booking'); ?></label>
        <input id="ovb-city" type="text" name="city" value="<?php echo esc_attr($city); ?>"
            placeholder="<?php echo esc_attr__('Belgrade', 'ov-booking'); ?>" />
    </div>
    <div>
        <label for="ovb-country"><?php echo esc_html__('Country', 'ov-booking'); ?></label>
        <input id="ovb-country" type="text" name="country" value="<?php echo esc_attr($country); ?>"
            placeholder="<?php echo esc_attr__('Serbia', 'ov-booking'); ?>" />
    </div>

    <div>
        <label for="ovb-capacity"><?php echo esc_html__('Min capacity', 'ov-booking'); ?></label>
        <input id="ovb-capacity" type="number" min="1" step="1" name="capacity"
            value="<?php echo esc_attr($capacity); ?>" />
    </div>
    <div>
        <label for="ovb-bedrooms"><?php echo esc_html__('Min bedrooms', 'ov-booking'); ?></label>
        <input id="ovb-bedrooms" type="number" min="1" step="1" name="bedrooms"
            value="<?php echo esc_attr($bedrooms); ?>" />
    </div>
    <div>
        <label for="ovb-beds"><?php echo esc_html__('Min beds', 'ov-booking'); ?></label>
        <input id="ovb-beds" type="number" min="1" step="1" name="beds" value="<?php echo esc_attr($beds); ?>" />
    </div>
    <div>
        <label for="ovb-bathrooms"><?php echo esc_html__('Min bathrooms', 'ov-booking'); ?></label>
        <input id="ovb-bathrooms" type="number" min="1" step="1" name="bathrooms"
            value="<?php echo esc_attr($bathrooms); ?>" />
    </div>

    <div>
        <label for="ovb-min-price"><?php echo esc_html__('Min price', 'ov-booking'); ?></label>
        <input id="ovb-min-price" type="number" min="0" step="1" name="min_price"
            value="<?php echo esc_attr($min_price); ?>" />
    </div>
    <div>
        <label for="ovb-max-price"><?php echo esc_html__('Max price', 'ov-booking'); ?></label>
        <input id="ovb-max-price" type="number" min="0" step="1" name="max_price"
            value="<?php echo esc_attr($max_price); ?>" />
    </div>

    <div style="display:flex; gap:8px; align-items:center;">
        <button type="submit" class="button"><?php echo esc_html__('Filter', 'ov-booking'); ?></button>
        <button id="ovb-filter-reset" type="button"
            class="button"><?php echo esc_html__('Reset', 'ov-booking'); ?></button>
    </div>
</form>
<?php
    return ob_get_clean();
});

/**
 * ===========================================
 * [ovb_populated_filters] Shortcode
 * ===========================================
 */
function ovb_get_filter_data(): array {
    $tkey = 'ovb_filter_data_v2';
    $cached = get_transient($tkey);
    if ($cached) {
        ovb_log('Using cached filter data.', 'cache');
        return $cached;
    }

    ovb_log('Generating new filter data and caching.', 'cache');

    $query = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ]);

    $data = [
        'types'     => [],
        'capacities' => [],
        'bedrooms'  => [],
        'beds'      => [],
        'bathrooms' => [],
        'locations' => [],
        'min_price' => null,
        'max_price' => null,
    ];

    foreach ($query->posts as $post_id) {
        $info = get_post_meta($post_id, '_apartment_additional_info', true);
        if (!is_array($info)) continue;

        // Accommodation Type
        if (!empty($info['accommodation_type'])) {
            $data['types'][$info['accommodation_type']] = 1;
        }
        // Capacity
        if (!empty($info['max_guests'])) {
            $data['capacities'][(int)$info['max_guests']] = 1;
        }
        // Bedrooms
        if (!empty($info['bedrooms'])) {
            $data['bedrooms'][(int)$info['bedrooms']] = 1;
        }
        // Beds
        if (!empty($info['beds'])) {
            $data['beds'][(int)$info['beds']] = 1;
        }
        // Bathrooms
        if (!empty($info['bathrooms'])) {
            $data['bathrooms'][(int)$info['bathrooms']] = 1;
        }
        // Location (City, Country, Street)
        if (!empty($info['city']) && !empty($info['country'])) {
            $loc_key = sanitize_title($info['city']) . '-' . sanitize_title($info['country']);
            $data['locations'][$loc_key] = [
                'city' => $info['city'],
                'country' => $info['country']
            ];
        }
        // Price range
        $calendar_prices = ovb_get_product_calendar_data($post_id);
        foreach ($calendar_prices as $day_data) {
            if (isset($day_data['price']) && is_numeric($day_data['price'])) {
                $price = (float)$day_data['price'];
                if ($data['min_price'] === null || $price < $data['min_price']) {
                    $data['min_price'] = $price;
                }
                if ($data['max_price'] === null || $price > $data['max_price']) {
                    $data['max_price'] = $price;
                }
            }
        }
    }

    set_transient($tkey, $data, 10 * MINUTE_IN_SECONDS);
    return $data;
}

add_shortcode('ovb_populated_filters', function(){
    // Pomoćna funkcija za čitanje GET parametara i njihovih aliasa
    $g = static function($k, $d = '') {
        return isset($_GET[$k]) ? sanitize_text_field(wp_unslash($_GET[$k])) : $d;
    };
    // Funkcija za izgradnju URL-a sa filterima
    $build_url = static function($key, $value) {
        $current_url = remove_query_arg(['paged', 'price_min', 'price_max']);
        $all_params = $_GET;
        $all_params[$key] = $value;
        return esc_url(add_query_arg($all_params, $current_url));
    };

    $data = ovb_get_filter_data();
    $action = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : get_post_type_archive_link('product');
    $action = esc_url($action ?: home_url('/'));

    ob_start();
    ?>
<form class="ovb-shop-filters ovb-dynamic-filters" method="get" action="<?php echo $action; ?>"
    style="display:grid; gap:12px;" novalidate>
    <div>
        <label for="ovb-ci"><?php echo esc_html__('Check-in', 'ov-booking'); ?></label>
        <input id="ovb-ci" type="date" name="ci" value="<?php echo esc_attr($g('ci')); ?>" />
    </div>
    <div>
        <label for="ovb-co"><?php echo esc_html__('Check-out', 'ov-booking'); ?></label>
        <input id="ovb-co" type="date" name="co" value="<?php echo esc_attr($g('co')); ?>" />
    </div>

    <div class="ovb-filter-group">
        <h4><?php echo esc_html__('Property Types', 'ov-booking'); ?></h4>
        <div class="ovb-filter-buttons">
            <?php foreach (array_keys($data['types']) as $type): ?>
            <a href="<?php echo esc_url($build_url('type', $type)); ?>"
                class="ovb-filter-button <?php echo $g('type') === $type ? 'is-active' : ''; ?>"><?php echo esc_html(ucfirst($type)); ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ovb-filter-group">
        <h4><?php echo esc_html__('Min Bedrooms', 'ov-booking'); ?></h4>
        <div class="ovb-filter-buttons">
            <?php foreach (array_keys($data['bedrooms']) as $num): ?>
            <a href="<?php echo esc_url($build_url('bedrooms', $num)); ?>"
                class="ovb-filter-button <?php echo (int)$g('bedrooms') === $num ? 'is-active' : ''; ?>"><?php echo esc_html($num . ' bed'); ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ovb-filter-group">
        <h4><?php echo esc_html__('Min beds', 'ov-booking'); ?></h4>
        <div class="ovb-filter-buttons">
            <?php foreach (array_keys($data['beds']) as $num): ?>
            <a href="<?php echo esc_url($build_url('beds', $num)); ?>"
                class="ovb-filter-button <?php echo (int)$g('beds') === $num ? 'is-active' : ''; ?>"><?php echo esc_html($num . ' bed'); ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ovb-filter-group">
        <h4><?php echo esc_html__('Min Bathrooms', 'ov-booking'); ?></h4>
        <div class="ovb-filter-buttons">
            <?php foreach (array_keys($data['bathrooms']) as $num): ?>
            <a href="<?php echo esc_url($build_url('bathrooms', $num)); ?>"
                class="ovb-filter-button <?php echo (int)$g('bathrooms') === $num ? 'is-active' : ''; ?>"><?php echo esc_html($num . ' bath'); ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ovb-filter-group ovb-price-range">
        <h4><?php echo esc_html__('Price range', 'ov-booking'); ?></h4>
        <div style="display: flex; gap: 8px;">
            <input type="number" name="min_price" placeholder="From..."
                value="<?php echo esc_attr($g('min_price')); ?>" />
            -
            <input type="number" name="max_price" placeholder="To..."
                value="<?php echo esc_attr($g('max_price')); ?>" />
        </div>
        <p><small>Min: <?php echo esc_html(wc_price($data['min_price'])); ?> - Max:
                <?php echo esc_html(wc_price($data['max_price'])); ?></small></p>
    </div>

    <div class="ovb-filter-group">
        <h4><?php echo esc_html__('Location', 'ov-booking'); ?></h4>
        <select name="city" id="ovb-location-select">
            <option value=""><?php echo esc_html__('Select a location...', 'ov-booking'); ?></option>
            <?php foreach ($data['locations'] as $loc_key => $loc): ?>
            <option value="<?php echo esc_attr($loc['city']); ?>" <?php selected($g('city'), $loc['city']); ?>>
                <?php echo esc_html($loc['city'] . ', ' . $loc['country']); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display:flex; gap:8px; align-items:center;">
        <button type="submit" class="button"><?php echo esc_html__('Filter', 'ov-booking'); ?></button>
        <button id="ovb-filter-reset" type="button"
            class="button"><?php echo esc_html__('Reset', 'ov-booking'); ?></button>
    </div>
</form>
<?php
    return ob_get_clean();
});