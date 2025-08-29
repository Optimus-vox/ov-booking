<?php
defined('ABSPATH') || exit;

/**
 * ======================================
 *  OV Booking Frontend Scripts & Assets
 * ======================================
 */

/** OPTIONAL LOGGER LOADER */
if ( file_exists( dirname( __DIR__ ) . '/helpers/logger.php' ) ) {
    require_once dirname( __DIR__ ) . '/helpers/logger.php';
}

/** HELPER - check if Elementor should be disabled on this page */
function ovb_is_elementor_disabled_page() {
    return is_singular('product')
        || ( function_exists('is_cart') && is_cart() )
        || ( function_exists('is_checkout') && is_checkout() )
        || ( function_exists('is_account_page') && is_account_page() )
        || ( function_exists('is_order_received_page') && is_order_received_page() );
}

/** GLOBAL WP ASSETS & CONFIG */
add_action( 'wp_enqueue_scripts', 'ovb_enqueue_global_assets', 1 );
function ovb_enqueue_global_assets() {
    wp_enqueue_style( 'dashicons' );
    if ( ovb_is_woo_page() ) {
        wp_enqueue_script( 'jquery' );
        // ensure admin-ajax is available
        wp_enqueue_script( 'wp-util' );
    }
}

/** MAIN PLUGIN ASSETS */
add_action( 'wp_enqueue_scripts', 'ovb_enqueue_main_assets', 20 );
function ovb_enqueue_main_assets() {
    $main_css = OVB_BOOKING_PATH . 'assets/css/main.css';
    $main_js  = OVB_BOOKING_PATH . 'assets/js/main.js';

    if ( file_exists( $main_css ) ) {
        wp_enqueue_style( 'ovb-main-style',
            OVB_BOOKING_URL . 'assets/css/main.css',
            [],
            filemtime( $main_css )
        );
    }
    if ( file_exists( $main_js ) ) {
        wp_enqueue_script( 'ovb-main-js',
            OVB_BOOKING_URL . 'assets/js/main.js',
            [ 'jquery', 'wp-util' ],
            filemtime( $main_js ),
            true
        );
        wp_script_add_data( 'ovb-main-js', 'type', 'module' );
    }

    if ( is_product() ) {
        ovb_enqueue_product_assets();
    }
}

/** PRODUCT PAGE ASSETS */
function ovb_enqueue_product_assets() {
    global $post;
    $product_id = $post->ID;

    // 1) Calendar core (Moment + Daterangepicker)
    ovb_enqueue_calendar_core();

    // 2) Custom daterange picker
    ovb_enqueue_daterange_picker();

    // 3) Slider assets
    ovb_enqueue_slider_assets();

    // 4) Single-product scripts & styles
    ovb_enqueue_product_scripts( $product_id );
}

/** Calendar core (Moment.js + Daterangepicker) */
function ovb_enqueue_calendar_core() {
    if ( ! wp_script_is( 'moment-js', 'enqueued' ) ) {
        wp_enqueue_script(
            'moment-js',
            'https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js',
            [],
            '2.29.4',
            true
        );
    }
    add_action( 'wp_footer', 'ovb_moment_local_fallback', 1000 );

    if ( ! wp_script_is( 'daterangepicker-js', 'enqueued' ) ) {
        wp_enqueue_style(
            'daterangepicker-css',
            'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css',
            [],
            '3.1.0'
        );
        wp_enqueue_script(
            'daterangepicker-js',
            'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js',
            [ 'jquery', 'moment-js' ],
            '3.1.0',
            true
        );
    }
}
function ovb_moment_local_fallback() {
    ?>
<script>
if (typeof moment === "undefined") {
    var s = document.createElement('script');
    s.src = '<?php echo esc_js( OVB_BOOKING_URL . "assets/utils/js/moment-local.min.js" ); ?>';
    s.async = true;
    document.head.appendChild(s);
}
</script>
<?php
}

/** Custom DateRange Picker */
function ovb_enqueue_daterange_picker() {
    $css = OVB_BOOKING_PATH . 'assets/utils/css/ov-date.range.css';
    $js  = OVB_BOOKING_PATH . 'assets/utils/js/ov-date.range.js';

    if ( file_exists( $css ) && ! wp_style_is( 'ovb-daterange-css', 'enqueued' ) ) {
        wp_enqueue_style(
            'ovb-daterange-css',
            OVB_BOOKING_URL . 'assets/utils/css/ov-date.range.css',
            [ 'daterangepicker-css' ],
            filemtime( $css )
        );
    }
    if ( file_exists( $js ) && ! wp_script_is( 'ovb-daterange-js', 'enqueued' ) ) {
        wp_enqueue_script(
            'ovb-daterange-js',
            OVB_BOOKING_URL . 'assets/utils/js/ov-date.range.js',
            [ 'jquery', 'moment-js', 'daterangepicker-js' ],
            filemtime( $js ),
            true
        );
    }
}

/** Slider Assets */
function ovb_enqueue_slider_assets() {
    $owl_css    = OVB_BOOKING_PATH . 'assets/utils/css/owl.carousel.min.css';
    $owl_theme  = OVB_BOOKING_PATH . 'assets/utils/css/owl.theme.default.min.css';
    $slider_css = OVB_BOOKING_PATH . 'assets/utils/css/ov.slider.css';
    $owl_js     = OVB_BOOKING_PATH . 'assets/utils/js/owl.carousel.min.js';
    $slider_js  = OVB_BOOKING_PATH . 'assets/utils/js/ov.slider.js';

    if ( file_exists( $owl_css ) && ! wp_style_is( 'owl-carousel', 'enqueued' ) ) {
        wp_enqueue_style( 'owl-carousel', OVB_BOOKING_URL . 'assets/utils/css/owl.carousel.min.css' );
    }
    if ( file_exists( $owl_theme ) && ! wp_style_is( 'owl-theme', 'enqueued' ) ) {
        wp_enqueue_style( 'owl-theme', OVB_BOOKING_URL . 'assets/utils/css/owl.theme.default.min.css' );
    }
    if ( file_exists( $slider_css ) && ! wp_style_is( 'ovb-slider', 'enqueued' ) ) {
        wp_enqueue_style( 'ovb-slider', OVB_BOOKING_URL . 'assets/utils/css/ov.slider.css' );
    }
    if ( file_exists( $owl_js ) && ! wp_script_is( 'owl-carousel-js', 'enqueued' ) ) {
        wp_enqueue_script( 'owl-carousel-js', OVB_BOOKING_URL . 'assets/utils/js/owl.carousel.min.js', [ 'jquery' ], '', true );
    }
    if ( file_exists( $slider_js ) && ! wp_script_is( 'ovb-slider-js', 'enqueued' ) ) {
        wp_enqueue_script( 'ovb-slider-js', OVB_BOOKING_URL . 'assets/utils/js/ov.slider.js', [ 'jquery', 'owl-carousel-js' ], '', true );
    }
}

/** Single-Product Scripts & Styles */
function ovb_enqueue_product_scripts( $product_id ) {
    $single_css = OVB_BOOKING_PATH . 'assets/css/ov-single.css';
    $single_js  = OVB_BOOKING_PATH . 'assets/js/ov-single.js';

    if ( file_exists( $single_css ) && ! wp_style_is( 'ovb-single-style', 'enqueued' ) ) {
        wp_enqueue_style(
            'ovb-single-style',
            OVB_BOOKING_URL . 'assets/css/ov-single.css',
            [],
            filemtime( $single_css )
        );
    }
    if ( file_exists( $single_js ) && ! wp_script_is( 'ovb-single-script', 'enqueued' ) ) {
        wp_enqueue_script(
            'ovb-single-script',
            OVB_BOOKING_URL . 'assets/js/ov-single.js',
            [ 'jquery', 'daterangepicker-js', 'wc-add-to-cart' ],
            filemtime( $single_js ),
            true
        );
    }
    
    // Safely enqueue WooCommerce scripts
    $wc_scripts = [ 'wc-add-to-cart', 'woocommerce', 'wc-single-product', 'wc-cart-fragments' ];
    foreach ( $wc_scripts as $handle ) {
        if ( ! wp_script_is( $handle, 'enqueued' ) ) {
            wp_enqueue_script( $handle );
        }
    }

    // Localize for AJAX
    wp_localize_script(
        'ovb-single-script',
        'ovbProductVars',
        [
            'ajax_url'          => esc_url( admin_url( 'admin-ajax.php' ) ),
            'nonce'             => wp_create_nonce( 'ovb_nonce' ),
            'product_id'        => absint( $product_id ),
            'calendar_data'     => ovb_get_clean_calendar_data( $product_id ),
            'price_types'       => get_post_meta( $product_id, '_ovb_price_types', true ) ?: [],
            'checkout_url'      => esc_url( wc_get_checkout_url() ),
            'cart_url'          => esc_url( wc_get_cart_url() ),
            'is_user_logged_in' => is_user_logged_in(),
            'start_date'        => sanitize_text_field( $_GET['ovb_start_date'] ?? '' ),
            'end_date'          => sanitize_text_field( $_GET['ovb_end_date']   ?? '' ),
            'i18n' => [
                'select_end_date' => __( 'Select end date', 'ov-booking' ),
                'select_dates'    => __( 'Please select dates', 'ov-booking' ),
                'loading'         => __( 'Loading...',        'ov-booking' ),
            ],
        ]
    );
}

/** PRELOAD CRITICAL CSS ONLY (no JS) */
add_action( 'wp_head', 'ovb_preload_critical_css', 99 );
function ovb_preload_critical_css() {
    if ( is_product() ) {
        echo sprintf(
            '<link rel="preload" href="%1$sassets/css/ov-single.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">',
            esc_url( OVB_BOOKING_URL )
        );
        echo sprintf(
            '<noscript><link rel="stylesheet" href="%1$sassets/css/ov-single.css"></noscript>',
            esc_url( OVB_BOOKING_URL )
        );
    }
}

/** CART ASSETS */
add_action( 'wp_enqueue_scripts', 'ov_enqueue_cart_assets' );
function ov_enqueue_cart_assets() {
    if ( ! is_cart() ) {
        return;
    }

    wp_enqueue_style(
        'ovb-cart-style',
        OVB_BOOKING_URL . 'assets/css/ov-cart.css',
        [],
        filemtime( OVB_BOOKING_PATH . 'assets/css/ov-cart.css' )
    );

    wp_enqueue_script(
        'ovb-cart-script',
        OVB_BOOKING_URL . 'assets/js/ov-cart.js',
        [ 'jquery', 'wc-cart' ],
        filemtime( OVB_BOOKING_PATH . 'assets/js/ov-cart.js' ),
        true
    );

    wp_localize_script(
        'ovb-cart-script',
        'ovCartVars',
        [
            'ajax_url'            => esc_url( admin_url( 'admin-ajax.php' ) ),
            'nonce'               => wp_create_nonce( 'ovb_nonce' ),
            'emptyCartConfirmMsg' => __( 'Are you sure you want to empty your cart?', 'ov-booking' ),
            'checkoutUrl'         => esc_url( wc_get_checkout_url() ),
            'emptyCartTitle'       => __('Empty cart?', 'ov-booking'),
            'emptyCartConfirmMsg'  => __('This will remove all items from your cart.', 'ov-booking'),
            'confirmText'          => __('Yes, empty it', 'ov-booking'),
            'cancelText'           => __('Cancel', 'ov-booking'),
            'emptySuccess'         => __('Cart emptied.', 'ov-booking'),
        ]
    );

}

/**********************
 * CHECKOUT (UNIFIED) *
 *********************/
add_action('wp_enqueue_scripts', 'ovb_enqueue_checkout_assets', 9999);
function ovb_enqueue_checkout_assets() {
    if ( ! function_exists('is_checkout') || ! is_checkout() ) return;
    if ( function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received') ) return;

    $css_file = OVB_BOOKING_PATH . 'assets/css/ov-checkout.css';
    $js_file  = OVB_BOOKING_PATH . 'assets/js/ov-checkout.js';

    /* -------------------------------
     *  STYLES (vezani za Woo/Blocks)
     * ------------------------------- */
    $style_deps = [];
    foreach (['woocommerce-general','woocommerce-layout','woocommerce-smallscreen','wc-blocks-style'] as $h) {
        if ( wp_style_is($h, 'registered') ) { $style_deps[] = $h; }
    }

    if ( file_exists($css_file) ) {
        wp_register_style(
            'ovb-checkout-style',
            OVB_BOOKING_URL . 'assets/css/ov-checkout.css',
            $style_deps,
            filemtime($css_file)
        );
        wp_enqueue_style('ovb-checkout-style');

  
    }

    // Ako Woo ima SelectWoo/Select2 stilove – obezbedi da postoje pre naših inicijalizacija
    if ( wp_style_is('select2', 'registered') ) {
        wp_enqueue_style('select2');
    }

    /* -------------------------------
     *  SCRIPTS (classic + blocks deps)
     * ------------------------------- */
    $script_deps = array_filter([
        'jquery',
        wp_script_is('wc-checkout', 'registered') ? 'wc-checkout' : null,
        wp_script_is('wc-blocks-checkout', 'registered') ? 'wc-blocks-checkout' : null,
        wp_script_is('wc-address-i18n', 'registered') ? 'wc-address-i18n' : null,
        wp_script_is('wc-country-select', 'registered') ? 'wc-country-select' : null,
    ]);

    // Ako postoji SelectWoo/Select2 – učitaj pre našeg inline-a
    if ( wp_script_is('selectWoo', 'registered') ) {
        $script_deps[] = 'selectWoo';
    } elseif ( wp_script_is('select2', 'registered') ) {
        $script_deps[] = 'select2';
    }

    // Uvek registruj handle (i kad nema fajla) da inline pouzdano radi
    $js_src = file_exists($js_file) ? OVB_BOOKING_URL . 'assets/js/ov-checkout.js' : '';
    $js_ver = file_exists($js_file) ? filemtime($js_file) : null;
    wp_register_script('ovb-checkout-script', $js_src, $script_deps, $js_ver, true);
    wp_enqueue_script('ovb-checkout-script');

    /* -------------------------------
     *  INLINE: toggles + repeater + SelectWoo/Select2 (sa dropdownParent)
     * ------------------------------- */
    $inline = <<<'JS'
jQuery(function($){
  if (window.__ovbUnifiedInit) return; window.__ovbUnifiedInit = true;

  // Selektori (tvoji) + fallback na starije klase
  var $isCo      = $('#ovb_is_company');
  var $wrapCo    = $('.ovb-company-fields-wrap').length ? $('.ovb-company-fields-wrap') : $('.ovb-company-fields');
  var $isOther   = $('#ovb_is_other').length ? $('#ovb_is_other') : $('#ovb_guest_different');
  var $wrapOther = $('.ovb-other-fields-wrap').length ? $('.ovb-other-fields-wrap') : $('.ovb-guest-fields');

  var $rep   = $('#ovb-guest-repeater');
  var $total = $('#ovb_guests_total');

  function setRequired($wrap, on){
    if(!$wrap || !$wrap.length) return;
    $wrap.find('[data-required]').each(function(){
      if(on){ $(this).attr('required','required'); }
      else  { $(this).removeAttr('required'); }
    });
  }
  function toggleWrap($cb, $wrap){
    if(!$cb || !$cb.length || !$wrap || !$wrap.length) return;
    var on = $cb.is(':checked');
    $wrap.toggle(on);
    setRequired($wrap, on);
    if (window.ovbEnhanceSelects) window.ovbEnhanceSelects($wrap);
  }

  function toggleCompany(){ toggleWrap($isCo, $wrapCo); }
  function toggleOther(){   toggleWrap($isOther, $wrapOther); }

  // Guests repeater (bez <18 logike)
  function guestRowTpl(i){
    return '' +
      '<div class="ovb-guest-row" data-index="'+i+'">' +
        '<input type="text"  name="ovb_guest['+i+'][first_name]" placeholder="Ime" required>' +
        '<input type="text"  name="ovb_guest['+i+'][last_name]"  placeholder="Prezime" required>' +
        '<select name="ovb_guest['+i+'][gender]" required data-no-search="1">' +
          '<option value="">Pol...</option>' +
          '<option value="M">Muški</option>' +
          '<option value="F">Ženski</option>' +
        '</select>' +
        '<input type="date" name="ovb_guest['+i+'][dob]" placeholder="Datum rođenja" required>' +
        '<input type="tel"  name="ovb_guest['+i+'][phone]" placeholder="Telefon">' +
        '<input type="text" name="ovb_guest['+i+'][passport]" placeholder="Broj pasoša/lične karte (opciono)">' +
      '</div>';
  }

  function ensureRows(n){
    if(!$rep.length) return;
    var have = $rep.find('.ovb-guest-row').length;
    if(have < n){
      for(var i=have;i<n;i++){
        $rep.append( guestRowTpl(i) );
        if (window.ovbEnhanceSelects) {
          window.ovbEnhanceSelects($rep.find('.ovb-guest-row').last());
        }
      }
    } else if(have > n){
      $rep.find('.ovb-guest-row').slice(n).remove();
    }
  }

  function baseGuests(){
    var otherOn = $isOther && $isOther.length ? $isOther.is(':checked') : false;
    return 1 + (otherOn ? 1 : 0);
  }

  function syncGuests(){
    if(!$total || !$total.length) return;
    var t = parseInt($total.val() || '1', 10);
    if(!isFinite(t) || t < 1) t = 1;
    var need = Math.max(0, t - baseGuests());
    ensureRows(need);
  }

  // --- SelectWoo / Select2 UX (jedinstvena inicijalizacija + dropdownParent)
  if (!window.__ovbSelectsInit) {
    window.__ovbSelectsInit = true;

    window.ovbEnhanceSelects = function($ctx){
      var $scope = $ctx && $ctx.length ? $ctx : $(document);
      var $sels = $scope.find('.ovb-company-fields-wrap select, .ovb-other-fields-wrap select, #ovb-guest-repeater select');

      $sels.each(function(){
        var $el = $(this);
        if ($el.hasClass('select2-hidden-accessible')) return; // već init

        // dropdownParent: veži za lokalni kontejner da ne razvuče layout
        var $parent = $el.closest('.ovb-company-fields-wrap, .ovb-other-fields-wrap, .ovb-checkout-section, .ovb-checkout-content, .woocommerce-checkout');
        if (!$parent.length) $parent = $(document.body);

        var searchable = $el.find('option').length > 7 && !$el.is('[data-no-search]');
        var opts = {
          width: '100%',
          minimumResultsForSearch: searchable ? 0 : Infinity,
          dropdownParent: $parent
        };

        if ($.fn.selectWoo)      { $el.selectWoo(opts); }
        else if ($.fn.select2)   { $el.select2(opts); }
      });
    };

    // prvi run
    window.ovbEnhanceSelects($(document));

    // Woo klasični eventi i fragmenti
    $(document.body).on('updated_checkout updated_wc_div country_to_state_changed wc_fragments_loaded', function(){
      window.ovbEnhanceSelects($(document));
    });

    // WC Blocks i ostali DOM update-i
    if ('MutationObserver' in window) {
      var obs = new MutationObserver(function(){ window.ovbEnhanceSelects($(document)); });
      obs.observe(document.body, {childList:true, subtree:true});
    }
  }

  // INIT UI
  toggleCompany();
  toggleOther();
  syncGuests();

  // LISTENERS
  $(document).on('change', '#ovb_is_company', function(){ toggleCompany(); syncGuests(); });
  $(document).on('change', '#ovb_is_other, #ovb_guest_different', function(){ toggleOther(); syncGuests(); });
  $(document).on('change', '#ovb_guests_total', syncGuests);
});
JS;
    wp_add_inline_script('ovb-checkout-script', $inline, 'after');
}

/**
 * (Opcionalno) Elementor: obezbedi da naš CSS bude poslednji
 */
// add_action('elementor/frontend/after_enqueue_styles', function(){
//     if ( function_exists('is_checkout') && is_checkout() && wp_style_is('ovb-checkout-style', 'registered') ) {
//         wp_dequeue_style('ovb-checkout-style');
//         wp_enqueue_style('ovb-checkout-style');
//     }
// }, 99);


/** THANK YOU PAGE CSS */
function ov_enqueue_thank_you_assets()
{
    $is_thankyou = (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) || isset($_GET['order-received']);
    
    if ($is_thankyou) {
        wp_enqueue_style('ovb-thank-you-style', OVB_BOOKING_URL . 'assets/css/ov-thank-you.css');
        wp_enqueue_script('ovb-thank-you-script', OVB_BOOKING_URL . 'assets/js/ov-thank-you.js', ['jquery'], '1.0', true);
    }
}
add_action('wp_enqueue_scripts', 'ov_enqueue_thank_you_assets');

/** MY ACCOUNT PAGE CSS */
add_action( 'wp_enqueue_scripts', 'ovb_enqueue_my_account_assets', 20 );
function ovb_enqueue_my_account_assets() {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
        return;
    }

    $css_file = OVB_BOOKING_PATH . 'assets/css/ov-my-account.css';

    if ( file_exists( $css_file ) ) {
        wp_enqueue_style(
            'ovb-my-account-style',
            OVB_BOOKING_URL . 'assets/css/ov-my-account.css',
            [],
            filemtime( $css_file )
        );
    }
    
    // My Account conflict resolution
    // add_action( 'wp_footer', 'ovb_resolve_account_conflicts', 999 );
}

/** UTILITY FUNCTIONS */
function ovb_is_woo_page() {
    return function_exists( 'is_woocommerce' ) && (
        is_cart() ||
        is_checkout() ||
        is_account_page() ||
        is_wc_endpoint_url( 'order-received' ) ||
        is_product() ||
        is_shop() ||
        is_product_category() ||
        is_product_tag()
    );
}

function ovb_get_clean_calendar_data( $product_id ) {
    $raw = get_post_meta( $product_id, '_ovb_calendar_data', true );
    if ( empty( $raw ) ) {
        return [];
    }
    if ( is_string( $raw ) ) {
        $raw = json_decode( $raw, true );
    }
    return is_array( $raw ) ? $raw : [];
}