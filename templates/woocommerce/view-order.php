<?php
defined( 'ABSPATH' ) || exit;

// $order = wc_get_order( $order_id );
$order_id = absint( get_query_var( 'view-order' ) );
$order = wc_get_order( $order_id );




error_log('[DEBUG] $order ID: ' . ( $order ? $order->get_id() : 'N/A' ));
if ( ! $order ) return;

do_action( 'woocommerce_before_view_order', $order->get_id() );
?>

<h2><?php esc_html_e( 'Order details', 'woocommerce' ); ?></h2>

<?php
// Prikaz svih stavki narudžbine i booking meta
include WP_PLUGIN_DIR . '/ov-booking/includes/frontend/order-meta-display.php';
?>
<div>
    <!-- empty - without content -->
</div>
<section class="woocommerce-order-details">
    <?php do_action( 'woocommerce_order_details_before_order_table', $order ); ?>

    <h3><?php esc_html_e( 'Order summary', 'woocommerce' ); ?></h3>
    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
        <thead>
            <tr>
                <th class="woocommerce-table__product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
                <th class="woocommerce-table__product-table"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $order->get_items() as $item_id => $item ) :
                $product = $item->get_product(); ?>
                <tr class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'woocommerce-table__line-item', $item, $order ) ); ?>">
                    <td class="woocommerce-table__product-name">
                        <?php echo esc_html( $item->get_name() ); ?>
                        <?php do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, false ); ?>
                        <?php// wc_display_item_meta( $item ); ?>
                        <?php do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, false ); ?>
                    </td>
                    <td class="woocommerce-table__product-total">
                        <?php echo $order->get_formatted_line_subtotal( $item ); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
        <?php foreach ( $order->get_order_item_totals() as $key => $total ) : ?>
            <tr>
                <th scope="row"><?php echo esc_html( $total['label'] ); ?></th>
                <td><?php echo wp_kses_post( $total['value'] ); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php
        // Izračunamo total bez poreza
        $total_excl_tax = $order->get_total() - $order->get_total_tax();
        ?>
        <tr>
            <th scope="row"><?php esc_html_e( 'Total excl. taxes:', 'ov-booking' ); ?></th>
            <td><?php echo wc_price( $total_excl_tax ); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'VAT:', 'ov-booking' ); ?></th>
            <td><?php echo wc_price( $order->get_total_tax() ); ?></td>
        </tr>
    </tfoot>
    </table>

    <?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>
</section>

<?php do_action( 'woocommerce_after_view_order', $order->get_id() ); ?>


<!-- // Prikaz PDV (20%)
$order_total = (float) $order->get_total();
$vat_amount  = $order_total * 0.20;

echo '<div class="ovb-order-vat" style="margin-top: 1rem;">';
echo '<p><strong>' . esc_html__('VAT (20%)', 'ov-booking') . ':</strong> ' . wc_price($vat_amount) . '</p>'; -->