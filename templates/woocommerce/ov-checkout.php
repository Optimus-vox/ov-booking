<?php
defined('ABSPATH') || exit;

// Ako smo na order-received endpoint-u, renderuj custom thank you i prekini dalje
if ( function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received') ) {
    // putanja do tvog thankyou template-a
    $thankyou = plugin_dir_path(__FILE__) . '../../templates/woocommerce/ov-thank-you.php';
    if ( file_exists( $thankyou ) ) {
        include $thankyou;
        return; // prekini ostatak ov-checkout.php
    }
}

// Prikaz WooCommerce notifikacija (greške prilikom checkout-a)
if ( function_exists('wc_print_notices') ) {
    echo '<div class="ov-checkout-notices">';
    wc_print_notices();
    echo '</div>';
}

// 1) Provera prazne korpe
if ( ! class_exists('WC_Cart') || ! WC()->cart || WC()->cart->is_empty() ) {
    echo '<div class="ov-cart page-cart">';
    echo '<p class="ov-cart-empty">' . esc_html__('Vaša korpa je prazna.', 'ov-booking') . '</p>';
    echo '</div>';
    return;
}

// 2) Provera da li je korisnik ulogovan
if ( ! is_user_logged_in() ) {
    echo '<div class="ov-cart page-cart">';
    echo '<p class="ov-cart-error">' . esc_html__('You must be logged in to make a booking.', 'ov-booking') . '</p>';
    echo '</div>';
    return;
}

// 3) Učitaj prvu stavku iz korpe
$items     = WC()->cart->get_cart();
$cart_item = reset( $items );

// 4) Validacija stavke
if ( ! $cart_item
     || empty( $cart_item['data'] )
     || ! ( $cart_item['data'] instanceof WC_Product )
) {
    echo '<div class="ov-cart page-cart">';
    echo '<p class="ov-cart-error">' . esc_html__('Greška pri učitavanju stavke iz korpe.', 'ov-booking') . '</p>';
    echo '</div>';
    return;
}

/** @var WC_Product $product */
$product         = $cart_item['data'];
$start_date      = ! empty( $cart_item['start_date'] ) ? sanitize_text_field( $cart_item['start_date'] ) : '';
$end_date        = ! empty( $cart_item['end_date']   ) ? sanitize_text_field( $cart_item['end_date']   ) : '';
// $all_dates       = ! empty( $cart_item['all_dates']  ) ? array_filter( explode( ',', sanitize_text_field( $cart_item['all_dates'] ) ) ) : [];
// $guests          = ! empty( $cart_item['guests']     ) ? intval( $cart_item['guests'] ) : 1;
// $nights          = max( 1, count( $all_dates ) );

$all_dates = ! empty( $cart_item['all_dates'] ) 
    ? array_filter( explode( ',', sanitize_text_field( $cart_item['all_dates'] ) ) ) 
    : [];
$guests    = ! empty( $cart_item['guests'] ) 
    ? intval( $cart_item['guests'] ) 
    : 1;

// Uzimamo nights iz cart‐item meta koju si već sačuvao u cart-hooks.php
if ( isset( $cart_item['nights'] ) ) {
    $nights = intval( $cart_item['nights'] );
} else {
    // fallback, ali ne bi trebalo da se desi
    $nights = max( 0, count( $all_dates ) - 1 );
}


$start_label     = $start_date ? date_i18n( get_option('date_format'), strtotime( $start_date ) ) : '';
$end_label       = $end_date   ? date_i18n( get_option('date_format'), strtotime( $end_date   ) ) : '';
$calendar_data   = get_post_meta( $product->get_id(), '_ov_calendar_data', true );
if ( ! is_array( $calendar_data ) ) {
    $calendar_data = [];
}
$breakdown_total = 0;

get_header();

// Uzmemo checkout objekat da bismo ga prosledili WC template-ima
$checkout = WC()->checkout();
?>

<div class="ov-checkout page-checkout">
    <div class="ov-checkout-container">

        <!-- HEADER -->
        <div class="ov-checkout-header">
            <a id="ov-back-btn" onclick="history.back()" class="ov-checkout-back">
                <img src="<?php echo esc_url( plugins_url( '../../assets/images/arrow-left-white.png', __FILE__ ) ); ?>"
                    alt="arrow left white">
            </a>
            <h1 class="ov-checkout-title"><?php esc_html_e( 'Request to book', 'ov-booking' ); ?></h1>
        </div>
        <form name="checkout" method="post" class="checkout ovb-checkout-form"
        action="<?php echo esc_url( wc_get_checkout_url() ); ?>">
        <div class="ov-checkout-content">
            <!-- LEVA STRANA -->
            <div class="ov-checkout-steps">
                <div class="ov-step ov-step-active">
                    <span class="ov-step-number">2.</span>
                    <span class="ov-step-label"><?php esc_html_e( 'Add a payment method', 'ov-booking' ); ?></span>
                </div>
                <div class="ov-step">
                    <span class="ov-step-number">3.</span>
                    <span class="ov-step-label"><?php esc_html_e( 'Review your request', 'ov-booking' ); ?></span>
                </div>

                <div class="checkout-form">
                  
                        <!-- CUSTOMER DETAILS -->
                        <div id="customer_details" class="ovb-customer-details-container">
                            <?php
                            // Billing polja
                            wc_get_template( 'checkout/form-billing.php', array( 'checkout' => $checkout ) );
                            // Shipping polja (ako koristiš shipping)
                            wc_get_template( 'checkout/form-shipping.php', array( 'checkout' => $checkout ) );
                            ?>
                        </div>

             
                            <?php
                               // ovde mogu da stoje payment metode
                                wc_get_template( 'checkout/payment.php', array( 'checkout' => $checkout ) );
                            ?>
                </div>
            </div>

            <!-- DESNA STRANA: WooCommerce Checkout Summary -->
            <div class="ov-checkout-summary">
                <div class="ov-summary-card">
                        <!-- REVIEW ORDER -->
                        <?php
                            $image_id = $product->get_image_id();
                            if ( $image_id ) :
                                echo '<div class="ovb-product-image">';
                                    echo wp_get_attachment_image( $image_id, '' );
                                echo '</div>';
                            endif;
                        ?>
                        <div class="ovb-trip-details-summary">
                            <h4 class="ovb-trip-details-title"><?php esc_html_e( 'Trip details', 'ov-booking' ); ?></h4>
                            <div class="ovb-trip-details">
                                <div class="ovb-trip-detail-item">
                                    <div class="trip-details-stay">
                                        <span class="ovb-trip-icon dashicons dashicons-calendar-alt">
                                        <!-- <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#fff" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zM5 20V9h14v11H5z"/>
                                            <circle cx="7.5" cy="12.5" r="1.5"/>
                                            <circle cx="12"  cy="12.5" r="1.5"/>
                                            <circle cx="16.5" cy="12.5" r="1.5"/>
                                            <circle cx="7.5" cy="16"   r="1.5"/>
                                            <circle cx="12"  cy="16"   r="1.5"/>
                                            <circle cx="16.5" cy="16"   r="1.5"/>
                                        </svg> -->
                                        </span>
                                        <span class="ovb-trip-detail-text">
                                            <?php echo esc_html( $start_label . ' – ' . $end_label ); ?>
                                        </span>
                                    </div>
                                    <div class="trip-details-accommodation-details">
                                        <div class="ovb-trip-detail-nights">
                                            <span class="ovb-trip-icon dashicons dashicons-admin-home">
                                            <!-- <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                                            </svg> -->
                                            </span>
                                            <span class="ovb-trip-detail-text">
                                                <?php echo esc_html( $nights . ' ' . _n( 'night', 'nights', $nights, 'ov-booking' ) ); ?>
                                            </span>
                                        </div>

                                        <div class="ovb-trip-detail-guests">
                                            <span class="ovb-trip-icon dashicons dashicons-groups">
                                             
                                            <!-- <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#fff" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                                            </svg> -->
                                            </span>
                                            <span class="ovb-trip-detail-text">
                                                <?php echo esc_html( $guests . ' ' . _n( 'guest', 'guests', $guests, 'ov-booking' ) ); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="order_review" class="ovb-order-review-container">

                            <table class="shop_table">
                                <thead>
                                    <tr>
                                        <th class="product-name"><?php esc_html_e( 'Product', 'ov-booking' ); ?></th>
                                        <th class="product-subtotal"><?php esc_html_e( 'Subtotal', 'ov-booking' ); ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) : 
                                        $product   = $cart_item['data'];
                                        $quantity  = $cart_item['quantity']; // ne treba
                                        // cena stavke (posle set_price u init.php)
                                        $line_total = $cart_item['line_total'];
                                    ?>
                                    <tr class="cart_item">
                                        <td class="product-name">
                                            <?php echo esc_html( $product->get_name() ); ?>
                                        </td>
                                        <td class="product-subtotal">
                                            <?php echo wc_price( $line_total ); ?>
                                        </td>
                                    </tr>

                                    <tr class="ovb-details-label">
                                        <td colspan="2">
                                            <strong><?php esc_html_e( 'Details:', 'ov-booking' ); ?></strong></td>
                                    </tr>
                                    <?php
                                        // ispišemo detalje po datumima:
                                        if ( ! empty( $cart_item['ov_all_dates'] ) ) {
                                            $dates      = explode( ',', $cart_item['ov_all_dates'] );
                                            // broj noći iz cart-item meta (fallback na count-1 ako ne postoji)
                                            $nights     = isset( $cart_item['nights'] )
                                                          ? intval( $cart_item['nights'] )
                                                          : max( 0, count( $dates ) - 1 );
                                            // kalendar sa cenama
                                            $calendar   = get_post_meta( $product->get_id(), '_ov_calendar_data', true ) ?: [];
                                            // uzmemo samo prvih $nights datuma
                                            $print_dates = array_slice( $dates, 0, $nights );
                                        
                                            foreach ( $print_dates as $date ) {
                                                $price = isset( $calendar[ $date ]['price'] )
                                                    ? floatval( $calendar[ $date ]['price'] )
                                                    : 0;
                                                ?>
                                                <tr class="ovb-detail-line">
                                                    <td class="ovb-detail-date">
                                                        <?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $date ) ) ); ?>
                                                    </td>
                                                    <td class="ovb-detail-price">
                                                        <?php echo wc_price( $price ); ?>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        }
                                    endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="order-total">
                                        <th><?php esc_html_e( 'Total', 'ov-booking' ); ?></th>
                                        <td class="product-total"><?php echo WC()->cart->get_total(); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                </div>
            </div>
        </div>
        </form>
    </div>
</div>

<?php
get_footer();