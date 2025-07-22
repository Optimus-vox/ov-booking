<?php
defined( 'ABSPATH' ) || exit;

// 1) Disable Astra’s WooCommerce integration globally
add_filter( 'astra_enable_woocommerce_integration', '__return_false' );


// 2) On Cart / Checkout / Thank-You: disable Elementor assets only there
add_action( 'wp', 'ovb_disable_elementor_on_wc_pages', 1 );
function ovb_disable_elementor_on_wc_pages() {
    if ( is_cart() || is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
        add_filter( 'elementor/frontend/should_enqueue_scripts', '__return_false' );
        add_filter( 'elementor/frontend/should_enqueue_styles',  '__return_false' );
        add_filter( 'elementor/editor/enqueue_scripts',         '__return_false' );
    }
}