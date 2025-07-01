<?php
defined('ABSPATH') || exit;

/**
 * iCal Integration Hooks
 *
 * - dugmad na Thank You stranici
 * - .ics prilog u emailu
 */

// Thank You page: prikaži „Add to calendar“ dugmiće
add_action(
    'woocommerce_thankyou',
    ['OVB_iCal_Service','render_calendar_buttons'],
    20
);

// Completed-order email: priloži .ics fajl
add_filter(
    'woocommerce_email_attachments',
    ['OVB_iCal_Service','attach_ics_to_email'],
    10,
    3
);
