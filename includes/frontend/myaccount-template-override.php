<?php
defined('ABSPATH') || exit;

// My Account custom template
add_action('template_redirect','ovb_template_redirect_my_account',0);
function ovb_template_redirect_my_account(){
    if(is_admin() || !is_main_query() || wp_doing_ajax()) return;

    $id = wc_get_page_id('myaccount');
    if ((function_exists('is_account_page') && is_account_page()) || is_page($id)) {
        $tpl = OVB_BOOKING_PATH . 'templates/woocommerce/ov-my-account.php';

        if (file_exists($tpl)) {
            load_template($tpl, true);
            exit;
        }
    }
}
