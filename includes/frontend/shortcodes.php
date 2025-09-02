<?php
defined('ABSPATH') || exit;

// ... (ostatak shortcode-a, ne treba ga menjati, samo početak)

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