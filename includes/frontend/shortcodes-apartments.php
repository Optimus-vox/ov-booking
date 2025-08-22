<?php
defined('ABSPATH') || exit;

/**
 * OVB Shortcodes: [ovb_apartments] i [ovb_apartment_filter]
 */

// ============================= [ovb_apartments] =============================
add_shortcode('ovb_apartments', function ($atts = []) {
    $atts = shortcode_atts([
        'per_page'       => 12,
        'columns'        => 3,
        'show_min_price' => 0,
        'window_days'    => 365,
        'category'       => '',
        'city'           => '',
        'country'        => '',
        'guests'         => 0,
        'rooms'          => 0,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'date'           => '',
    ], $atts, 'ovb_apartments');

    $cols = max(1, min(6, (int)$atts['columns']));

    // KORISTI centralizovani helper → nema dupliranja:
    $args = function_exists('ovb_get_apartments_query_args')
        ? ovb_get_apartments_query_args($atts, 'shortcode')
        : [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, (int)$atts['per_page']),
            'no_found_rows'  => true,
        ];

    $q = new WP_Query($args);
    if (!$q->have_posts()) {
        return '<div class="ovb-empty">' . esc_html__('No apartments found.', 'ov-booking') . '</div>';
    }

    ob_start();
    echo '<ul class="products columns-' . esc_attr($cols) . ' ovb-apartments-grid">';
    while ($q->have_posts()) {
        $q->the_post();
        $product = wc_get_product(get_the_ID());
        if (!$product) continue; ?>
<li <?php wc_product_class('ovb-apartment-item', $product); ?>>
    <a class="woocommerce-LoopProduct-link woocommerce-loop-product__link"
        href="<?php echo esc_url(get_permalink()); ?>">
        <?php echo $product->get_image('woocommerce_thumbnail'); ?>
        <h2 class="woocommerce-loop-product__title"><?php echo esc_html(get_the_title()); ?></h2>
    </a>
    <!-- Po tvom zahtevu: bez Add to Cart i bez regularne cene -->
</li>
<?php }
    echo '</ul>';
    wp_reset_postdata();
    return ob_get_clean();
});

// ============================= [ovb_apartment_filter] =============================
/**
 * Primer: [ovb_apartment_filter show_country="0" redirect="" btn_label="Search" fields="date,guests,rooms,city,country"]
 */
add_shortcode('ovb_apartment_filter', function ($atts = []) {
    global $wpdb;

    $atts = shortcode_atts([
        'show_country'        => 0,
        'redirect'            => '',
        'btn_label'           => __('Search', 'ov-booking'),
        'placeholder_city'    => __('City', 'ov-booking'),
        'placeholder_country' => __('Country', 'ov-booking'),
        'placeholder_date'    => __('Arrival date', 'ov-booking'),
        'placeholder_guests'  => __('Guests', 'ov-booking'),
        'placeholder_rooms'   => __('Rooms', 'ov-booking'),
        'fields'              => 'date,guests,rooms,city,country',
    ], $atts, 'ovb_apartment_filter');

    $action       = $atts['redirect'] ? esc_url($atts['redirect']) : wc_get_page_permalink('shop');
    $show_country = (int)$atts['show_country'] === 1;

    // Distinct city/country (keširaj 10 min – optional)
    $cities = (array) $wpdb->get_col("
        SELECT DISTINCT pm.meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id AND p.post_type='product' AND p.post_status='publish'
        WHERE pm.meta_key='_ovb_city' AND pm.meta_value<>'' ORDER BY pm.meta_value ASC LIMIT 200
    ");
    $countries = $show_country ? (array) $wpdb->get_col("
        SELECT DISTINCT pm.meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id AND p.post_type='product' AND p.post_status='publish'
        WHERE pm.meta_key='_ovb_country' AND pm.meta_value<>'' ORDER BY pm.meta_value ASC LIMIT 200
    ") : [];

    $fields = array_values(array_intersect(
        array_map('trim', explode(',', strtolower($atts['fields']))),
        ['date','guests','rooms','city','country']
    ));
    if (!$fields) $fields = ['date','guests','rooms','city','country'];

    ob_start(); ?>
<form class="ovb-apartment-filter" action="<?php echo esc_url($action); ?>" method="get">
    <div class="ovb-filter-grid"
        style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
        <?php foreach ($fields as $f): ?>
        <?php if ($f === 'date'): ?>
        <div>
            <label class="screen-reader-text"><?php echo esc_html($atts['placeholder_date']); ?></label>
            <input type="date" name="ovb_date" value=""
                placeholder="<?php echo esc_attr($atts['placeholder_date']); ?>" />
        </div>
        <?php elseif ($f === 'guests'): ?>
        <div>
            <label class="screen-reader-text"><?php echo esc_html($atts['placeholder_guests']); ?></label>
            <input type="number" min="1" name="ovb_guests" value=""
                placeholder="<?php echo esc_attr($atts['placeholder_guests']); ?>" />
        </div>
        <?php elseif ($f === 'rooms'): ?>
        <div>
            <label class="screen-reader-text"><?php echo esc_html($atts['placeholder_rooms']); ?></label>
            <input type="number" min="0" name="ovb_rooms" value=""
                placeholder="<?php echo esc_attr($atts['placeholder_rooms']); ?>" />
        </div>
        <?php elseif ($f === 'city'): ?>
        <div>
            <label class="screen-reader-text"><?php echo esc_html($atts['placeholder_city']); ?></label>
            <input list="ovb-cities" name="ovb_city" placeholder="<?php echo esc_attr($atts['placeholder_city']); ?>" />
            <?php if (!empty($cities)): ?>
            <datalist id="ovb-cities">
                <?php foreach ($cities as $c): ?>
                <option value="<?php echo esc_attr($c); ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <?php endif; ?>
        </div>
        <?php elseif ($f === 'country' && $show_country): ?>
        <div>
            <label class="screen-reader-text"><?php echo esc_html($atts['placeholder_country']); ?></label>
            <input list="ovb-countries" name="ovb_country"
                placeholder="<?php echo esc_attr($atts['placeholder_country']); ?>" />
            <?php if (!empty($countries)): ?>
            <datalist id="ovb-countries">
                <?php foreach ($countries as $c): ?>
                <option value="<?php echo esc_attr($c); ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>

        <div>
            <button type="submit" class="button ovb-filter-submit"><?php echo esc_html($atts['btn_label']); ?></button>
        </div>
    </div>
    <input type="hidden" name="post_type" value="product" />
</form>
<?php
    return ob_get_clean();
});