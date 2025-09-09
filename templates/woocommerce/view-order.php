<?php
defined('ABSPATH') || exit;

/**
 * OVB - View Order template (hardened)
 * - Bez short tagova
 * - Guard za nepostojeću narudžbinu
 * - Bez closing PHP taga na kraju fajla
 */

// Woo obično prosleđuje $order_id u callbacku, ali za svaki slučaj uzimamo iz endpointa:
$order_id = 0;

// 1) Ako Woo prosleđuje parametar kroz shortcode handler:
if (!empty($order_id_from_cb ?? null) && is_numeric($order_id_from_cb)) {
    $order_id = absint($order_id_from_cb);
}
// 2) Standardni Woo endpoint /my-account/view-order/123/
if (!$order_id) {
    $order_id = absint(get_query_var('view-order'));
}

$order = $order_id ? wc_get_order($order_id) : false;

error_log('[DEBUG] $order ID: ' . ($order ? $order->get_id() : 'N/A'));

if (!$order) {
    // Fail-safe: tiha izlazna tačka da ne bismo trošili hookove / ili polomili layout
    return;
}

do_action('woocommerce_before_view_order', $order->get_id());
?>

<h2><?php esc_html_e('Order details', 'woocommerce'); ?></h2>

<?php
// Prikaz stavki/metа: robustan include
$meta_tpl = trailingslashit(WP_PLUGIN_DIR) . 'ov-booking/includes/frontend/order-meta-display.php';
if (is_readable($meta_tpl)) {
    include $meta_tpl;
}
?>

<section class="woocommerce-order-details">
    <?php do_action('woocommerce_order_details_before_order_table', $order); ?>

    <h3><?php esc_html_e('Order summary', 'woocommerce'); ?></h3>

    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
        <thead>
            <tr>
                <th class="woocommerce-table__product-name"><?php esc_html_e('Product', 'woocommerce'); ?></th>
                <th class="woocommerce-table__product-table"><?php esc_html_e('Total', 'woocommerce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order->get_items() as $item_id => $item): ?>
            <?php $product = $item->get_product(); // može biti null (npr. obrisan proizvod) ?>
            <tr
                class="<?php echo esc_attr(apply_filters('woocommerce_order_item_class', 'woocommerce-table__line-item', $item, $order)); ?>">
                <td class="woocommerce-table__product-name">
                    <?php echo esc_html($item->get_name()); ?>
                    <?php do_action('woocommerce_order_item_meta_start', $item_id, $item, $order, false); ?>
                    <?php // wc_display_item_meta($item); ?>
                    <?php do_action('woocommerce_order_item_meta_end', $item_id, $item, $order, false); ?>
                </td>
                <td class="woocommerce-table__product-total">
                    <?php
                        // get_formatted_line_subtotal vrati HTML (sigurni output od Woo)
                        echo $order->get_formatted_line_subtotal($item);
                        ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php foreach ($order->get_order_item_totals() as $key => $total): ?>
            <tr>
                <th scope="row"><?php echo esc_html($total['label']); ?></th>
                <td><?php echo wp_kses_post($total['value']); ?></td>
            </tr>
            <?php endforeach; ?>

            <?php
            // Total bez poreza
            $total_excl_tax = (float)$order->get_total() - (float)$order->get_total_tax();
            ?>
            <tr>
                <th scope="row"><?php esc_html_e('Total excl. taxes:', 'ov-booking'); ?></th>
                <td><?php echo wc_price($total_excl_tax); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('VAT:', 'ov-booking'); ?></th>
                <td><?php echo wc_price($order->get_total_tax()); ?></td>
            </tr>
        </tfoot>
    </table>

    <?php do_action('woocommerce_order_details_after_order_table', $order); ?>
</section>
<?php
// Fallback ako shop strana nije setovana
$shop_url = wc_get_page_permalink('shop');
if (empty($shop_url)) {
    $shop_url = home_url('/shop/'); // promeni slug ako koristiš drugi
}
?>
<p class="form-row" style="margin-top:1rem;">
    <a class="button alt" href="<?php echo esc_url($shop_url); ?>">
        <?php esc_html_e('Order again', 'ov-booking'); ?>
    </a>
</p>

<?php
do_action('woocommerce_after_view_order', $order->get_id());

/**
 * Ako želiš dodatni proračun PDV-a nezavisno od Woo total-a,
 * drži ovo kao PHP-komentar (NE u HTML komentaru), da ne ostanu "naga" tokenizovana slova.
 *
 * // $order_total = (float) $order->get_total();
 * // $vat_amount  = $order_total * 0.20;
 * // echo '<div class="ovb-order-vat" style="margin-top:1rem;">';
 * // echo '<p><strong>' . esc_html__('VAT (20%)', 'ov-booking') . ':</strong> ' . wc_price($vat_amount) . '</p>';
 * // echo '</div>';
 */