<?php
defined( 'ABSPATH' ) || exit;

wc_get_template( 'checkout/payment.php', array( 'checkout' => WC()->checkout() ) );



?>

