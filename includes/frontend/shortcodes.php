<?php
defined('ABSPATH') || exit;

// =================================================================
// [ovb_shop_filters] - Statički filteri
// =================================================================
add_shortcode('ovb_shop_filters', function () {
    // Helper za čitanje i sanitizaciju GET parametara
    $g = static function($k, $d = '') {
        return isset($_GET[$k]) ? sanitize_text_field(wp_unslash($_GET[$k])) : $d;
    };

    // Čitanje vrednosti
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

    // Detekcija taksonomije
    $type_tax = function_exists('ovb_detect_type_taxonomy') ? (string) ovb_detect_type_taxonomy() : '';
    if ($type_tax === 'product_type' || !taxonomy_exists($type_tax)) {
        $type_tax = '';
    }

    $types = [];
    if ($type_tax) {
        $terms = get_terms(['taxonomy' => $type_tax, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
        if (!is_wp_error($terms) && $terms) $types = $terms;
    }

    $action = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : get_post_type_archive_link('product');
    $fallback_types = ['apartment' => __('Apartment','ov-booking'), 'house' => __('House','ov-booking'), 'villa' => __('Villa','ov-booking'), 'cottage' => __('Cottage','ov-booking'), 'studio' => __('Studio','ov-booking')];

    ob_start(); ?>
<form class="ovb-shop-filters" method="get" action="<?php echo esc_url($action); ?>" style="display:grid; gap:12px;"
    novalidate>
    <div><label for="ovb-ci"><?php esc_html_e('Check-in', 'ov-booking'); ?></label><input id="ovb-ci" type="date"
            name="ci" value="<?php echo esc_attr($ci); ?>" /></div>
    <div><label for="ovb-co"><?php esc_html_e('Check-out', 'ov-booking'); ?></label><input id="ovb-co" type="date"
            name="co" value="<?php echo esc_attr($co); ?>" /></div>
    <div><label
            for="ovb-type"><?php esc_html_e('Accommodation Type', 'ov-booking'); ?></label><?php if ($type_tax && !empty($types)) : ?><select
            id="ovb-type" name="type">
            <option value=""><?php esc_html_e('Any', 'ov-booking'); ?></option><?php foreach ($types as $t): ?><option
                value="<?php echo esc_attr($t->slug); ?>" <?php selected($type_val, $t->slug); ?>>
                <?php echo esc_html($t->name); ?></option><?php endforeach; ?>
        </select><?php else: ?><select id="ovb-type" name="type">
            <option value=""><?php esc_html_e('Any', 'ov-booking'); ?></option>
            <?php foreach ($fallback_types as $slug => $label): ?><option value="<?php echo esc_attr($slug); ?>"
                <?php selected($type_val, $slug); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
        </select><?php endif; ?></div>
    <div><label for="ovb-street"><?php esc_html_e('Street', 'ov-booking'); ?></label><input id="ovb-street" type="text"
            name="street_name" value="<?php echo esc_attr($street); ?>"
            placeholder="<?php esc_attr_e('Knez Mihailova', 'ov-booking'); ?>" /></div>
    <div><label for="ovb-city"><?php esc_html_e('City', 'ov-booking'); ?></label><input id="ovb-city" type="text"
            name="city" value="<?php echo esc_attr($city); ?>"
            placeholder="<?php esc_attr_e('Belgrade', 'ov-booking'); ?>" /></div>
    <div><label for="ovb-country"><?php esc_html_e('Country', 'ov-booking'); ?></label><input id="ovb-country"
            type="text" name="country" value="<?php echo esc_attr($country); ?>"
            placeholder="<?php esc_attr_e('Serbia', 'ov-booking'); ?>" /></div>
    <div><label for="ovb-capacity"><?php esc_html_e('Min capacity', 'ov-booking'); ?></label><input id="ovb-capacity"
            type="number" min="1" step="1" name="capacity" value="<?php echo esc_attr($capacity); ?>" /></div>
    <div><label for="ovb-bedrooms"><?php esc_html_e('Min bedrooms', 'ov-booking'); ?></label><input id="ovb-bedrooms"
            type="number" min="1" step="1" name="bedrooms" value="<?php echo esc_attr($bedrooms); ?>" /></div>
    <div><label for="ovb-beds"><?php esc_html_e('Min beds', 'ov-booking'); ?></label><input id="ovb-beds" type="number"
            min="1" step="1" name="beds" value="<?php echo esc_attr($beds); ?>" /></div>
    <div><label for="ovb-bathrooms"><?php esc_html_e('Min bathrooms', 'ov-booking'); ?></label><input id="ovb-bathrooms"
            type="number" min="1" step="1" name="bathrooms" value="<?php echo esc_attr($bathrooms); ?>" /></div>
    <div><label for="ovb-min-price"><?php esc_html_e('Min price', 'ov-booking'); ?></label><input id="ovb-min-price"
            type="number" min="0" step="1" name="min_price" value="<?php echo esc_attr($min_price); ?>" /></div>
    <div><label for="ovb-max-price"><?php esc_html_e('Max price', 'ov-booking'); ?></label><input id="ovb-max-price"
            type="number" min="0" step="1" name="max_price" value="<?php echo esc_attr($max_price); ?>" /></div>
    <div style="display:flex; gap:8px; align-items:center;"><button type="submit"
            class="button"><?php esc_html_e('Filter', 'ov-booking'); ?></button><button id="ovb-filter-reset"
            type="button" class="button"><?php esc_html_e('Reset', 'ov-booking'); ?></button></div>
</form>
<?php
    return ob_get_clean();
});

// =================================================================
// [ovb_populated_filters] - Dinamički popunjeni filteri (ISPRAVLJENO)
// =================================================================

define('OVB_FILTERS_TRANSIENT_KEY', 'ovb_available_filters_data_v3'); // Verzija 3 zbog izmene strukture

function ovb_get_cached_filter_data(): array {
    $cached_data = get_transient(OVB_FILTERS_TRANSIENT_KEY);
    if (is_array($cached_data)) {
        return $cached_data;
    }

    $product_ids = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids']);
    $results = ['prices' => ['min' => null, 'max' => null], 'bedrooms' => [], 'beds' => [], 'bathrooms' => [], 'types' => [], 'cities' => []];

    if (empty($product_ids)) {
        set_transient(OVB_FILTERS_TRANSIENT_KEY, $results, 12 * HOUR_IN_SECONDS);
        return $results;
    }

    foreach ($product_ids as $pid) {
        if ($bedrooms = (int) get_post_meta($pid, '_ovb_bedrooms', true)) $results['bedrooms'][] = $bedrooms;
        if ($beds = (int) get_post_meta($pid, '_ovb_beds', true)) $results['beds'][] = $beds;
        if ($bathrooms = (int) get_post_meta($pid, '_ovb_bathrooms', true)) $results['bathrooms'][] = $bathrooms;
        if ($type = get_post_meta($pid, '_ovb_accommodation_type', true)) $results['types'][] = ucfirst($type);
        if ($city = get_post_meta($pid, '_ovb_city', true)) $results['cities'][] = $city;

        if (is_array($calendar_data = get_post_meta($pid, '_ovb_calendar_data', true))) {
            foreach ($calendar_data as $day) {
                if (isset($day['price']) && is_numeric($day['price']) && $day['price'] > 0) {
                    $price = (float) $day['price'];
                    if (is_null($results['prices']['min']) || $price < $results['prices']['min']) $results['prices']['min'] = $price;
                    if (is_null($results['prices']['max']) || $price > $results['prices']['max']) $results['prices']['max'] = $price;
                }
            }
        }
    }

    foreach (['bedrooms', 'beds', 'bathrooms', 'types', 'cities'] as $key) {
        $results[$key] = array_values(array_unique($results[$key]));
        sort($results[$key], in_array($key, ['bedrooms', 'beds', 'bathrooms']) ? SORT_NUMERIC : SORT_STRING);
    }

    if (!is_null($results['prices']['min'])) $results['prices']['min'] = floor($results['prices']['min']);
    if (!is_null($results['prices']['max'])) $results['prices']['max'] = ceil($results['prices']['max']);
    
    set_transient(OVB_FILTERS_TRANSIENT_KEY, $results, 12 * HOUR_IN_SECONDS);
    return $results;
}

function ovb_invalidate_filter_cache() {
    delete_transient(OVB_FILTERS_TRANSIENT_KEY);
}
add_action('save_post_product', 'ovb_invalidate_filter_cache');

add_shortcode('ovb_populated_filters', function($atts) {
    $data = ovb_get_cached_filter_data();
    $current_filters = function_exists('ovb_normalize_filters_from_request') ? ovb_normalize_filters_from_request() : [];
    $action_url = get_permalink(wc_get_page_id('shop'));

    ob_start(); ?>
<div class="ovb-populated-filters-wrapper">
    <form class="ovb-shop-filters" action="<?php echo esc_url($action_url); ?>" method="GET">
        <div class="filter-group date-filters">
            <h4>Dates</h4>
            <div class="date-inputs"><input type="date" name="ci"
                    value="<?php echo esc_attr($current_filters['ci'] ?? ''); ?>" placeholder="Check-in"><input
                    type="date" name="co" value="<?php echo esc_attr($current_filters['co'] ?? ''); ?>"
                    placeholder="Check-out"></div>
        </div>
        <?php if (!empty($data['bedrooms'])) : ?>
        <div class="filter-group">
            <h4>Min Bedrooms</h4>
            <div class="ovb-filter-buttons"><?php foreach ($data['bedrooms'] as $count) : ?><button type="button"
                    class="ovb-filter-button <?php echo (($current_filters['bedrooms'] ?? '') == $count) ? 'active' : ''; ?>"
                    data-key="bedrooms" data-value="<?php echo esc_attr($count); ?>"><?php echo esc_html($count); ?>
                    bed</button><?php endforeach; ?></div>
            <input type="hidden" name="bedrooms" value="<?php echo esc_attr($current_filters['bedrooms'] ?? ''); ?>">
        </div>
        <?php endif; ?>
        <?php if (!empty($data['beds'])) : ?>
        <div class="filter-group">
            <h4>Min Beds</h4>
            <div class="ovb-filter-buttons"><?php foreach ($data['beds'] as $count) : ?><button type="button"
                    class="ovb-filter-button <?php echo (($current_filters['beds'] ?? '') == $count) ? 'active' : ''; ?>"
                    data-key="beds" data-value="<?php echo esc_attr($count); ?>"><?php echo esc_html($count); ?>
                    beds</button><?php endforeach; ?></div>
            <input type="hidden" name="beds" value="<?php echo esc_attr($current_filters['beds'] ?? ''); ?>">
        </div>
        <?php endif; ?>
        <?php if (!is_null($data['prices']['min'])) : ?>
        <div class="filter-group">
            <h4>Price range</h4>
            <div class="price-inputs"><input type="number" name="min_price"
                    value="<?php echo esc_attr($current_filters['min_price'] ?? ''); ?>"
                    placeholder="From (<?php echo esc_attr($data['prices']['min']); ?>€)"><span
                    class="price-separator">-</span><input type="number" name="max_price"
                    value="<?php echo esc_attr($current_filters['max_price'] ?? ''); ?>"
                    placeholder="To (<?php echo esc_attr($data['prices']['max']); ?>€)"></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($data['bathrooms'])) : ?>
        <div class="filter-group">
            <h4>Min Bathrooms</h4>
            <div class="ovb-filter-buttons"><?php foreach ($data['bathrooms'] as $count) : ?><button type="button"
                    class="ovb-filter-button <?php echo (($current_filters['bathrooms'] ?? '') == $count) ? 'active' : ''; ?>"
                    data-key="bathrooms" data-value="<?php echo esc_attr($count); ?>"><?php echo esc_html($count); ?>
                    bath</button><?php endforeach; ?></div>
            <input type="hidden" name="bathrooms" value="<?php echo esc_attr($current_filters['bathrooms'] ?? ''); ?>">
        </div>
        <?php endif; ?>
        <?php if (!empty($data['types'])) : ?>
        <div class="filter-group">
            <h4>Property Types</h4>
            <div class="ovb-filter-buttons"><?php foreach ($data['types'] as $type) : ?><button type="button"
                    class="ovb-filter-button <?php echo (strtolower($current_filters['type'] ?? '') == strtolower($type)) ? 'active' : ''; ?>"
                    data-key="type"
                    data-value="<?php echo esc_attr(strtolower($type)); ?>"><?php echo esc_html($type); ?></button><?php endforeach; ?>
            </div>
            <input type="hidden" name="type" value="<?php echo esc_attr($current_filters['type'] ?? ''); ?>">
        </div>
        <?php endif; ?>
        <?php if (!empty($data['cities'])) : ?>
        <div class="filter-group">
            <h4>Location</h4><select name="city">
                <option value="">Select a location...</option><?php foreach ($data['cities'] as $city) : ?><option
                    value="<?php echo esc_attr($city); ?>" <?php selected($current_filters['city'] ?? '', $city); ?>>
                    <?php echo esc_html($city); ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="filter-actions"><button type="submit" class="button primary-button">Apply Filters</button><a
                href="<?php echo esc_url($action_url); ?>" id="ovb-filter-reset" class="clear-filters">Clear filters</a>
        </div>
    </form>
</div>
<?php return ob_get_clean();
});

add_action('wp_footer', function() {
    // STARA LINIJA KOJA NE RADI SA ELEMENTOROM
    // if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ovb_populated_filters')) {

    // NOVA LINIJA KOJA RADI UVEK NA SHOP STRANICI
    if (function_exists('is_shop') && is_shop()) {
        ?>
<script id="ovb-populated-filters-final-script">
document.addEventListener('click', function(e) {
    const filterButton = e.target.closest('.ovb-populated-filters-wrapper button.ovb-filter-button');

    if (!filterButton) {
        return;
    }

    e.preventDefault();
    e.stopPropagation();

    const wrapper = filterButton.closest('.ovb-populated-filters-wrapper');
    const form = wrapper.querySelector('form.ovb-shop-filters');
    if (!form) return;

    const key = filterButton.dataset.key;
    const value = filterButton.dataset.value;
    const hiddenInput = form.querySelector(`input[name="${key}"]`);
    if (!hiddenInput) return;

    const groupButtons = filterButton.closest('.ovb-filter-buttons').querySelectorAll(
        'button.ovb-filter-button');

    if (filterButton.classList.contains('active')) {
        filterButton.classList.remove('active');
        hiddenInput.value = '';
    } else {
        groupButtons.forEach(btn => btn.classList.remove('active'));
        filterButton.classList.add('active');
        hiddenInput.value = value;
    }

    const submitter = form.querySelector('button[type="submit"]');
    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(submitter);
    } else {
        form.submit();
    }
});
</script>
<?php
    }
});