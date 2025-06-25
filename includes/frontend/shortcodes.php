<?php
defined('ABSPATH') || exit;
require_once dirname(__DIR__) . '/helpers/logger.php';



add_shortcode('ov_booking_single', function() {
    $mode = get_option('ov_booking_display_mode', 'shortcode');
    if ($mode !== 'shortcode') return ''; // Ne prikazuj ako nije izabrano

    ob_start();
    include OV_BOOKING_PATH . 'templates/single-product.php';
    return ob_get_clean();
});
