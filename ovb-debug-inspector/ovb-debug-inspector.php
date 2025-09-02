<?php
/**
 * Plugin Name: OVB Debug Inspector
 * Description: Ispis ispod shop liste sa svim relevantnim meta poljima (+_apartment_additional_info) za svaki prikazani product.
 * Version:     1.0.0
 * Author:      OVB
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Bezbedan esc helper za preformatirane blokove
 */
function ovbdi_h($v) {
    if (is_scalar($v) || $v === null) {
        return esc_html((string)$v);
    }
    return esc_html(print_r($v, true));
}

/**
 * Da li treba prikazati debug panel (samo shop/tax arhive za product)
 */
function ovbdi_should_render(): bool {
    if (is_admin()) return false;
    if (!(function_exists('is_shop') && is_shop()) && !is_post_type_archive('product') && !(function_exists('is_product_taxonomy') && is_product_taxonomy())) {
        return false;
    }
    // dozvoli ako si admin ili ako je dodat ?ovbdebug=1
    $by_param = isset($_GET['ovbdebug']) && $_GET['ovbdebug'] == '1';
    return current_user_can('manage_options') || $by_param;
}

/**
 * Izvuci vrednost iz _apartment_additional_info (može već biti unserialized od WP-a)
 */
function ovbdi_get_additional_info($product_id) : array {
    $raw = get_post_meta($product_id, '_apartment_additional_info', true);
    if (is_string($raw)) {
        // WP obično već unserijalizuje, ali ako nije:
        $maybe = @maybe_unserialize($raw);
        if (is_array($maybe)) return $maybe;
        return ['__raw' => $raw];
    }
    if (is_array($raw)) return $raw;
    return [];
}

/**
 * Render ispod shop liste
 */
function ovbdi_render_panel() {
    if (!ovbdi_should_render()) return;

    global $wp_query;

    // Uzmemo baš proizvode koji su sada prikazani (posle svih filtera)
    $posts = [];
    if ($wp_query instanceof WP_Query && !empty($wp_query->posts)) {
        foreach ($wp_query->posts as $p) {
            if ($p instanceof WP_Post && $p->post_type === 'product') {
                $posts[] = $p;
            }
        }
    }

    // Ako je prazno, ipak probaj do 50 proizvoda da ne bude mrtav ekran
    if (empty($posts)) {
        $q = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'no_found_rows'  => true,
        ]);
        $posts = $q->posts;
    }

    // Meta ključevi koje tvoj filter koristi + dodatni za lakši uvid
    $interesting_meta_keys = [
        '_ovb_street_name',
        '_ovb_city',
        '_ovb_country',
        '_ovb_max_guests',
        '_ovb_bedrooms',
        '_ovb_beds',
        '_ovb_bathrooms',
        '_price',
        '_ovb_accommodation_type',
        '_apartment_additional_info', // kompleksno polje sa accommodation_type unutra
    ];

    // Mala CSS stilizacija da bude čitljivo
    ?>
<div class="ovb-debug-inspector"
    style="margin:24px 0;border:2px dashed #ccc;padding:16px;border-radius:12px;background:#fff">
    <h3 style="margin:0 0 12px;">OVB Debug Inspector</h3>
    <p style="margin:0 0 16px;font-size:13px;line-height:1.5;">
        Prikazujem proizvode iz trenutne liste i njihove meta vrednosti. Panel vide samo admini ili kada dodaš
        <code>?ovbdebug=1</code> u URL. Ovo služi da proverimo da li postoji <code>_ovb_accommodation_type</code>
        i/ili vrednost <code>accommodation_type</code> unutar <code>_apartment_additional_info</code>.
    </p>
    <?php if (empty($posts)): ?>
    <p><strong>Nema proizvoda za prikaz.</strong> (Proveri da li filtar setuje <code>post__in</code> na [0] itd.)</p>
    <?php else: ?>
    <?php foreach ($posts as $p): ?>
    <?php
                $pid   = $p->ID;
                $title = get_the_title($pid);

                // pojedinačne meta vrednosti
                $meta_simple = [];
                foreach ($interesting_meta_keys as $mk) {
                    $meta_simple[$mk] = get_post_meta($pid, $mk, true);
                }

                // dodatni raspak za _apartment_additional_info
                $addi = ovbdi_get_additional_info($pid);
                $addi_type = is_array($addi) && isset($addi['accommodation_type']) ? $addi['accommodation_type'] : '';

                // vrednost iz “našeg” ključa (ako postoji)
                $ovb_type = isset($meta_simple['_ovb_accommodation_type']) ? $meta_simple['_ovb_accommodation_type'] : '';

                // pripremi i taksonomije (za svaki slučaj)
                $terms_out = [];
                $taxes = get_object_taxonomies('product', 'names');
                foreach ($taxes as $tax) {
                    $ts = get_the_terms($pid, $tax);
                    if (!is_wp_error($ts) && !empty($ts)) {
                        $terms_out[$tax] = wp_list_pluck($ts, 'slug');
                    }
                }
                ?>
    <details style="margin:10px 0;border:1px solid #eee;border-radius:8px;padding:10px;">
        <summary>
            <strong>#<?php echo (int)$pid; ?></strong>
            — <?php echo esc_html($title); ?>
            <?php if ($ovb_type || $addi_type): ?>
            <span style="opacity:.7"> | type:
                <?php echo esc_html($ovb_type ?: '(meta prazna)'); ?>
                <?php if ($addi_type): ?> / addi: <?php echo esc_html($addi_type); ?><?php endif; ?>
            </span>
            <?php endif; ?>
        </summary>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:10px;">
            <div style="border:1px solid #f0f0f0;border-radius:8px;padding:8px;">
                <div style="font-weight:600;margin-bottom:6px;">Meta (ključ → vrednost)</div>
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <tbody>
                        <?php foreach ($meta_simple as $k=>$v): ?>
                        <tr>
                            <td style="padding:4px 6px;border-bottom:1px solid #f6f6f6;white-space:nowrap;">
                                <code><?php echo esc_html($k); ?></code></td>
                            <td style="padding:4px 6px;border-bottom:1px solid #f6f6f6;">
                                <pre style="margin:0;white-space:pre-wrap;"><?php echo ovbdi_h($v); ?></pre>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="border:1px solid #f0f0f0;border-radius:8px;padding:8px;">
                <div style="font-weight:600;margin-bottom:6px;">_apartment_additional_info (raspakovano)</div>
                <pre style="margin:0;font-size:12px;white-space:pre-wrap;"><?php echo ovbdi_h($addi); ?></pre>
            </div>

            <div style="border:1px solid #f0f0f0;border-radius:8px;padding:8px;">
                <div style="font-weight:600;margin-bottom:6px;">Taksonomije → slugovi</div>
                <pre style="margin:0;font-size:12px;white-space:pre-wrap;"><?php echo ovbdi_h($terms_out); ?></pre>
            </div>
        </div>
    </details>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php
}
add_action('woocommerce_after_shop_loop', 'ovbdi_render_panel', 100);

/**
 * BONUS: shortcode [ovb_debug_inspector] ako želiš ručno da ga ubaciš u šablon ili blok
 */
function ovbdi_shortcode() {
    ob_start();
    ovbdi_render_panel();
    return ob_get_clean();
}
add_shortcode('ovb_debug_inspector', 'ovbdi_shortcode');