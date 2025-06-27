<?php
defined('ABSPATH') || exit;

// Ako želite da prikazujete događaje WooCommerce poruka (npr. uspeh, greške)
if ( function_exists('wc_print_notices') ) {
    echo '<div class="ov-cart-notices">';
    wc_print_notices();
    echo '</div>';
}

// 1) Provera prazne korpe
if ( ! class_exists('WC_Cart') || ! WC()->cart || WC()->cart->is_empty() ) {
    get_header();
    echo '<div class="ov-cart page-cart"><div class="ov-cart-container empty">';
    echo '<p class="ov-cart-empty">' . esc_html__( 'Vaša korpa je prazna.', 'ov-booking' ) . '</p>';

    // Uzmi ID Shop stranice, pa dobij URL preko get_permalink()
    $shop_id  = wc_get_page_id( 'shop' );
    $shop_url = $shop_id ? get_permalink( $shop_id ) : home_url();

    echo '<button type="button" onclick="window.location.href=\'' 
         . esc_url( $shop_url ) 
         . '\';">Go Back</button>';

    echo '</div> </div>';
    get_footer();
    return;
}

// Ukupan broj proizvoda (suma količina svih stavki)
$total_items = WC()->cart->get_cart_contents_count();

// Broj različitih stavki u korpi (linija po linija)
$line_items = count( WC()->cart->get_cart() );

// Za potrebe debug-a 
// echo 'Ukupno proizvoda: ' . esc_html( $total_items );
// echo '<br>';
// echo 'Broj linija u korpi: ' . esc_html( $line_items );

/**
 * 2) Učitaj prvu stavku iz korpe
 *    Napomena: ovim kodom uvek prikazujemo samo prvu stavku, jer booking sistem
 *    dozvoljava samo jednu stavku u korpi istovremeno.
 */
$items     = WC()->cart->get_cart();
$cart_item = reset( $items );

if ( ! $cart_item
     || empty( $cart_item['data'] )
     || ! ( $cart_item['data'] instanceof WC_Product )
) {
    echo '<div class="ov-cart page-cart">';
    echo '<p class="ov-cart-error">' . esc_html__( 'Greška pri učitavanju stavke iz korpe.', 'ov-booking' ) . '</p>';
    echo '</div>';
    return;
}

/** @var WC_Product $product */
$product       = $cart_item['data'];
$start_date    = ! empty( $cart_item['start_date'] ) ? sanitize_text_field( $cart_item['start_date'] ) : '';
$end_date      = ! empty( $cart_item['end_date']   ) ? sanitize_text_field( $cart_item['end_date']   ) : '';
$guests        = ! empty( $cart_item['guests']     ) ? intval( $cart_item['guests'] ) : 1;

$start_label   = $start_date ? date_i18n( get_option('date_format'), strtotime( $start_date ) ) : '';
$end_label     = $end_date   ? date_i18n( get_option('date_format'), strtotime( $end_date   ) ) : '';
$calendar_data = get_post_meta( $product->get_id(), '_ov_calendar_data', true );

$product_url = add_query_arg( [
    'ov_start_date' => rawurlencode( $start_date ),
    'ov_end_date'   => rawurlencode( $end_date ),
    'ov_guests'     => intval( $guests ),
], get_permalink( $product->get_id() ) );

// Tačan broj noćenja (razlika end_date – start_date u danima)
if ( $start_date && $end_date ) {
    $ts_start = strtotime( $start_date );
    $ts_end   = strtotime( $end_date );
    $nights   = max( 0, ( $ts_end - $ts_start ) / DAY_IN_SECONDS );
} else {
    $nights = 1;
}

// Niz “plaćenih” datuma (bez check-out dana), za breakdown
$dates_for_breakdown = [];
if ( $start_date && $end_date ) {
    $curr = $ts_start;
    while ( $curr < $ts_end ) {
        $dates_for_breakdown[] = date( 'Y-m-d', $curr );
        $curr = strtotime( '+1 day', $curr );
    }
}

if ( ! is_array( $calendar_data ) ) {
    $calendar_data = [];
}

$breakdown_total = 0;



get_header();
?>


<div class="ov-cart page-cart">
    <div class="ov-cart-container">
        <!-- HEADER -->
        <div class="ov-cart-header">
            <a id="ov-back-btn" href="<?php echo esc_url( $product_url ); ?>" class="ov-cart-back">
                <img src="<?php echo esc_url( plugins_url( '../../assets/images/arrow-left-white.png', __FILE__ ) ); ?>"
                    alt="arrow left white">
            </a>

            <!-- Empty Cart dugme -->
            <h1 class="ov-cart-title"><?php esc_html_e( 'Request to book', 'ov-booking' ); ?></h1>
        </div>

        <!-- SADRŽAJ -->
        <div class="ov-cart-content">
            <!-- LEVA KOLONA: koraci -->
            <div class="ov-cart-steps">
                <div class="ov-step ov-step-active">
                    <span class="ov-step-number">1.</span>
                    <span class="ov-step-label"><?php esc_html_e( 'Log in or sign up', 'ov-booking' ); ?></span>
                    <?php if ( is_user_logged_in() ) : ?>
                    <button type="button" class="ov-step-button js-continue">
                        <?php esc_html_e( 'Continue', 'ov-booking' ); ?>
                    </button>
                    <?php else : ?>
                    <button type="button" class="ov-step-button js-open-login-modal">
                        <?php esc_html_e( 'Login', 'ov-booking' ); ?>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="ov-step">
                    <span class="ov-step-number">2.</span>
                    <span class="ov-step-label"><?php esc_html_e( 'Add a payment method', 'ov-booking' ); ?></span>
                </div>
                <div class="ov-step">
                    <span class="ov-step-number">3.</span>
                    <span class="ov-step-label"><?php esc_html_e( 'Review your request', 'ov-booking' ); ?></span>
                </div>
                <button type="button" class="ov-step-button ov-empty-cart-button js-empty-cart">
                    <?php esc_html_e( 'Empty cart', 'ov-booking' ); ?>
                </button>

            </div>

            <!-- DESNA KOLONA: rezime -->
            <div class="ov-cart-summary">
                <div class="ov-summary-card">
                    <!-- Slika proizvoda -->
                    <div class="ov-summary-image">
                        <?php
                        $image_id = $product->get_image_id();
                        if ( $image_id ) {
                            echo wp_get_attachment_image( $image_id, 'medium' );
                        }
                        ?>
                    </div>

                    <div class="ov-summary-details">
                        <h2 class="ov-summary-title"><?php echo esc_html( $product->get_name() ); ?></h2>
                        <p class="ov-summary-policy">
                            <?php esc_html_e( 'This reservation is non-refundable.', 'ov-booking' ); ?>
                            <a href="#"><?php esc_html_e( 'Full policy', 'ov-booking' ); ?></a>
                        </p>
                        <hr>

                        <!-- Trip details -->
                        <div class="ov-summary-row">
                            <div class="ov-summary-left">
                                <span class="ov-summary-label">
                                    <?php esc_html_e( 'Trip details', 'ov-booking' ); ?>
                                </span>
                                <div class="ov-summary-row-details">
                                    <span class="ov-summary-value">
                                        <?php
                                        echo esc_html( "{$start_label} – {$end_label}" );
                                        echo ' / ' . esc_html( $nights . ' ' . _n( 'night', 'nights', $nights, 'ov-booking' ) );
                                        echo ' / ' . esc_html( $guests . ' ' . _n( 'guest', 'guests', $guests, 'ov-booking' ) );
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="ov-summary-right">
                                <a href="<?php echo esc_url( $product_url ); ?>" class="ov-summary-change">
                                    <?php esc_html_e( 'Change', 'ov-booking' ); ?>
                                </a>
                            </div>
                        </div>
                        <hr>
                        <!-- Price details -->
                        <div class="ov-summary-row">
                            <div class="ov-summary-left">
                                <span
                                    class="ov-summary-label"><?php esc_html_e( 'Price details', 'ov-booking' ); ?></span>
                            </div>
                            <div class="ov-summary-right">
                                <details class="ov-price-details-accordion">
                                    <summary><?php esc_html_e( 'Price breakdown', 'ov-booking' ); ?></summary>
                                    <ul class="ov-price-breakdown-list">
                                        <?php foreach ( $dates_for_breakdown as $date ) :
                                            $day_price = isset( $calendar_data[ $date ]['price'] )
                                                         ? floatval( $calendar_data[ $date ]['price'] )
                                                         : 0;
                                            $breakdown_total += $day_price;
                                        ?>
                                        <li>
                                            <?php
                                                echo esc_html( date_i18n( get_option('date_format'), strtotime( $date ) ) )
                                                     . ': ' . wc_price( $day_price );
                                                ?>
                                        </li>
                                        <?php endforeach; ?>

                                        <li class="ov-breakdown-total">
                                            <strong><?php esc_html_e( 'Total', 'ov-booking' ); ?>:</strong>
                                            <?php echo wc_price( $breakdown_total ); ?>
                                        </li>
                                    </ul>
                                </details>
                            </div>
                        </div>
                        <hr>
                        <!-- Total -->
                        <div class="ov-summary-row ov-summary-total-row">
                            <span class="ov-summary-total-label"><?php esc_html_e( 'Total', 'ov-booking' ); ?>
                                (<?php echo esc_html( get_woocommerce_currency() ); ?>)
                            </span>
                            <span class="ov-summary-total-value"><?php echo wc_price( $breakdown_total ); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Login/Register Modal -->
<div id="ov-login-modal" class="ov-login-register-cart-modal">
  <div class="ov-modal-backdrop"></div>
  <div class="ov-modal-inner">
    <div class="ov-modal-inner-header">
      <h2 class="ov-modal-title">Welcome to IMG Traumimmobilien</h2>
      <button type="button" class="ov-modal-close">&times;</button>
    </div>
    <p class="ov-modal-subtitle">Welcome back! / Create your account</p>

    <?php if ( function_exists('ovb_render_google_login_button') ) : ?>
    <!-- Social login buttons -->
        <div class="ov-social-logins">
            <?php ovb_render_google_login_button(); ?>
        </div>

        <div class="ov-divider">
            <span>or</span>
        </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form name="loginform" id="ov_login-cart-form"
      action="<?php echo esc_url( wp_login_url( wc_get_cart_url() ) ); ?>" method="post">
      <input type="text" name="log" placeholder="E-Mail" required />
      <div class="ov-login-password">
        <input type="password" name="pwd" placeholder="Password" required />
        <button type="button" class="ov-toggle-password dashicons dashicons-visibility" aria-label="Show password"></button>
      </div>
      <div class="ov-forgot-password">
        <a href="#">Forgotten password?</a>
      </div>
      <div class="ov-login-actions">
        <button type="submit" class="ov-button login">Login</button>
        <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="ov-button register">Register</a>
      </div>
      <input type="hidden" name="redirect_to" value="<?php echo esc_url( wc_get_cart_url() ); ?>" />
    </form>
  </div>
</div>

<?php
get_footer();