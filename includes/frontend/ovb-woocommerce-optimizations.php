<?php
defined('ABSPATH') || exit;

/**
 * ===============================================
 *  OV BOOKING WOOCOMMERCE OPTIMIZATIONS
 * ===============================================
 * 
 * Optimizuje WooCommerce za booking use case:
 * - Disable shipping
 * - Single quantity only
 * - Book Now instead of Add to Cart
 * - Booking-specific functionality
 */

/**
 * Initialize WooCommerce optimizations
 */
add_action('init', 'ovb_optimize_woocommerce_for_bookings', 10);
function ovb_optimize_woocommerce_for_bookings() {
    
    // Disable shipping completely for all products
    add_filter('woocommerce_cart_needs_shipping', '__return_false');
    add_filter('woocommerce_cart_needs_shipping_address', '__return_false');
    
    // Single quantity only - force individual sale
    add_filter('woocommerce_is_sold_individually', function($individually, $product) {
        // Apply to all products or just specific types
        return true; // Force single quantity for all products
    }, 10, 2);
    
    // Set max quantity to 1
    add_filter('woocommerce_quantity_input_max', function($max, $product) {
        return 1;
    }, 10, 2);
    
    // Set min quantity to 1
    add_filter('woocommerce_quantity_input_min', function($min, $product) {
        return 1;
    }, 10, 2);
    
    // Set default quantity to 1
    add_filter('woocommerce_quantity_input_args', function($args, $product) {
        $args['input_value'] = 1;
        $args['max_value'] = 1;
        $args['min_value'] = 1;
        return $args;
    }, 10, 2);
    
    // Change "Add to Cart" to "Book Now" on product pages
    add_filter('woocommerce_product_single_add_to_cart_text', function($text, $product) {
        return __('Book Now', 'ov-booking');
    }, 10, 2);
    
    // Change "Add to Cart" to "Book Now" on shop pages
    add_filter('woocommerce_product_add_to_cart_text', function($text, $product) {
        return __('Book Now', 'ov-booking');
    }, 10, 2);
    
    // Rename cart to "Booking Details"
    add_filter('woocommerce_cart_item_name', function($name, $cart_item, $cart_item_key) {
        // You can customize this further
        return $name;
    }, 10, 3);
    
    // Disable stock management for booking products (optional)
    add_filter('woocommerce_product_is_in_stock', function($is_in_stock, $product) {
        // Always show as in stock for booking products
        return true;
    }, 10, 2);
    
    // Remove quantity selector from cart page
    add_filter('woocommerce_cart_item_quantity', function($product_quantity, $cart_item_key, $cart_item) {
        return $cart_item['quantity']; // Just show quantity, no input
    }, 10, 3);
    
    // Disable coupon functionality (optional - remove if you want coupons)
    // remove_action('woocommerce_cart_contents', 'woocommerce_cart_totals', 50);
    // add_filter('woocommerce_coupons_enabled', '__return_false');
}

/**
 * Additional booking-specific customizations
 */
add_action('woocommerce_init', 'ovb_additional_booking_customizations');
function ovb_additional_booking_customizations() {
    
    // Remove "Add to cart" message and redirect to cart
    // add_filter('woocommerce_add_to_cart_redirect', function($url) {
    //     // Stay on product page instead of redirecting to cart
    //     return false;
    // });
    
    // Custom success message for bookings
    add_filter('woocommerce_add_to_cart_message_html', function($message, $products) {
        $message = sprintf(
            '<div class="woocommerce-message" role="alert">%s <a href="%s" class="button wc-forward">%s</a></div>',
            __('Booking added successfully!', 'ov-booking'),
            esc_url(wc_get_checkout_url()),
            __('Complete Booking', 'ov-booking')
        );
        return $message;
    }, 10, 2);
    
    // Remove cross-sells and up-sells
    remove_action('woocommerce_cart_contents', 'woocommerce_cross_sell_display');
    remove_action('woocommerce_single_product_summary', 'woocommerce_output_related_products', 20);
    
    // Disable reviews for booking products (optional)
    add_filter('woocommerce_product_tabs', function($tabs) {
        unset($tabs['reviews']);
        return $tabs;
    });
}

/**
 * Checkout optimizations for bookings
 */
add_filter('woocommerce_cart_needs_shipping', '__return_false', 10);
add_filter('woocommerce_cart_needs_shipping_address', '__return_false', 10); 
// ili ovo ispod - testiraj
// add_filter('woocommerce_checkout_fields', function($fields) {
//     $fields['billing']  = []; // nema default billing polja
//     $fields['shipping'] = []; // potpuno gasimo shipping UI
//     return $fields;
// }, 9999);
/**
 * Email customizations for bookings
 */
add_action('init', 'ovb_booking_email_customizations');
function ovb_booking_email_customizations() {
    
    // Change email subject lines
    add_filter('woocommerce_email_subject_new_order', function($subject, $order) {
        return sprintf(__('New Booking Received - Order #%s', 'ov-booking'), $order->get_order_number());
    }, 10, 2);
    
    add_filter('woocommerce_email_subject_customer_processing_order', function($subject, $order) {
        return sprintf(__('Your Booking is Confirmed - Order #%s', 'ov-booking'), $order->get_order_number());
    }, 10, 2);
    
    // Change order status labels
    add_filter('wc_order_statuses', function($statuses) {
        $statuses['wc-processing'] = __('Booking Confirmed', 'ov-booking');
        $statuses['wc-completed'] = __('Booking Completed', 'ov-booking');
        return $statuses;
    });
}

/**
 * Admin customizations for bookings
 */
add_action('admin_init', function() {
    add_action('admin_notices', function() {
        $screen = get_current_screen();
        if ( ! $screen ) return;

        // Classic edit screen + HPOS wc-orders (lista i edit)
        $targets = ['shop_order', 'woocommerce_page_wc-orders', 'woocommerce_page_wc-orders--edit'];
        if ( in_array($screen->id, $targets, true) ) {
            echo '<div class="notice notice-info"><p>'
               . esc_html__('This is a booking order. Handle with special care regarding dates and customer communication.', 'ov-booking')
               . '</p></div>';
        }
    });
});

/**
 * Performance optimizations
 */
add_action('wp', 'ovb_performance_optimizations_for_bookings');
function ovb_performance_optimizations_for_bookings() {
    
    // Disable WooCommerce widgets on booking pages
    if (is_singular('product') || is_cart() || is_checkout()) {
        add_filter('woocommerce_widget_cart_is_hidden', '__return_true');
    }
    
    // Reduce WooCommerce script loading on non-shop pages
    if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) {
        add_action('wp_enqueue_scripts', function() {
            wp_dequeue_script('wc-cart-fragments');
            wp_dequeue_script('woocommerce');
            wp_dequeue_style('woocommerce-layout');
            wp_dequeue_style('woocommerce-smallscreen');
            wp_dequeue_style('woocommerce-general');
        }, 99);
    }
}

/**
 * Debug helper - remove in production
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function() {
        if (is_singular('product') || is_cart() || is_checkout()) {
            echo '<!-- OVB: WooCommerce booking optimizations active -->';
        }
    });
}