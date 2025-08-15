<?php
defined('ABSPATH') || exit;

/**
 * =========================
 *  OV Booking — Checkout
 * =========================
 */

/**
 * A) Osnovni Woo polja: redosled + dodatna (DOB, ID)
 */
add_filter('woocommerce_checkout_fields', function($fields){
    $b = &$fields['billing'];

    // obezbedi postojanje tipičnih polja
    foreach (['billing_first_name','billing_last_name','billing_email','billing_phone','billing_country','billing_city','billing_address_1','billing_address_2','billing_postcode'] as $k) {
        if (!isset($b[$k])) $b[$k] = ['label'=>'','required'=>false,'priority'=>10];
    }

    // telefon obavezan (po tvojoj specifikaciji "telefon*")
    $b['billing_phone']['required'] = true;

    // dodatna polja
    $fields['billing']['ovb_dob'] = [
        'type'     => 'date',
        'label'    => __('Datum rođenja', 'ov-booking'),
        'required' => true,
        'priority' => 25,
        'class'    => ['form-row-wide'],
    ];
    $fields['billing']['ovb_id_number'] = [
        'type'     => 'text',
        'label'    => __('ID / broj pasoša', 'ov-booking'),
        'required' => true,
        'priority' => 95,
        'class'    => ['form-row-wide'],
    ];

    // redosled po zahtevu
    $b['billing_first_name']['priority'] = 10;
    $b['billing_last_name']['priority']  = 20;
    // 25 = ovb_dob
    $b['billing_email']['priority']      = 30;
    $b['billing_city']['priority']       = 40;
    $b['billing_country']['priority']    = 50;
    $b['billing_phone']['priority']      = 60;
    $b['billing_address_1']['priority']  = 70;
    $b['billing_address_2']['priority']  = 80;
    $b['billing_postcode']['priority']   = 90;
    // 95 = ovb_id_number

    // UX
    $b['billing_address_2']['placeholder'] = __('Apartman, sprat, jedinica (opciono)', 'ov-booking');

    // ukloni eventualne stare custom billing ključeve (da Woo ne validira firmu/drugu osobu)
    $remove = [
        'ovb_is_company','ovb_company_*','ovb_is_other','ovb_other_*','ovb_guest_different','ovb_contact_*'
    ];
    foreach ($b as $key => $val) {
        foreach ($remove as $pat) {
            if (fnmatch($pat, $key)) unset($b[$key]);
        }
    }

    return $fields;
}, 10, 1);

/**
 * D) Snimanje u order (HPOS-safe)
 */
add_action('woocommerce_checkout_create_order', function($order, $data){
    // osnovna dodatna
    $order->update_meta_data('_ovb_dob',        sanitize_text_field(wp_unslash($_POST['ovb_dob'] ?? '')));
    $order->update_meta_data('_ovb_id_number',  sanitize_text_field(wp_unslash($_POST['ovb_id_number'] ?? '')));

    // firma
    $is_company = !empty($_POST['ovb_is_company']);
    $order->update_meta_data('_ovb_is_company', $is_company ? '1' : '');
    if ($is_company) {
        $map = [
            '_ovb_company_name'     => 'ovb_company_name',
            '_ovb_company_country'  => 'ovb_company_country',
            '_ovb_company_state'    => 'ovb_company_state',
            '_ovb_company_city'     => 'ovb_company_city',
            '_ovb_company_address'  => 'ovb_company_address',
            '_ovb_company_postcode' => 'ovb_company_postcode',
            '_ovb_company_pib'      => 'ovb_company_pib',
            '_ovb_company_mb'       => 'ovb_company_mb',
            '_ovb_company_contact'  => 'ovb_company_contact',
            '_ovb_company_phone'    => 'ovb_company_phone',
        ];
        foreach ($map as $meta => $post_key) {
            $val = isset($_POST[$post_key]) ? wp_unslash($_POST[$post_key]) : '';
            $order->update_meta_data($meta, is_email($val) ? sanitize_email($val) : sanitize_text_field($val));
        }
        // pošalji naziv i u billing_company radi gateway-a
        if (!empty($_POST['ovb_company_name'])) {
            $order->set_billing_company( sanitize_text_field( wp_unslash($_POST['ovb_company_name']) ) );
        }
    }

    // druga osoba
    $is_other = !empty($_POST['ovb_is_other']);
    $order->update_meta_data('_ovb_is_other', $is_other ? '1' : '');
    if ($is_other) {
        $map = [
            '_ovb_other_first_name' => 'ovb_other_first_name',
            '_ovb_other_last_name'  => 'ovb_other_last_name',
            '_ovb_other_dob'        => 'ovb_other_dob',
            '_ovb_other_email'      => 'ovb_other_email',
            '_ovb_other_country'    => 'ovb_other_country',
            '_ovb_other_city'       => 'ovb_other_city',
            '_ovb_other_address1'   => 'ovb_other_address1',
            '_ovb_other_postcode'   => 'ovb_other_postcode',
            '_ovb_other_phone'      => 'ovb_other_phone',
            '_ovb_other_id_number'  => 'ovb_other_id_number',
            // '_ovb_other_address2'   => 'ovb_other_address2',
        ];
        foreach ($map as $meta => $post_key) {
            $val = isset($_POST[$post_key]) ? wp_unslash($_POST[$post_key]) : '';
            $order->update_meta_data($meta, is_email($val) ? sanitize_email($val) : sanitize_text_field($val));
        }
    }

    // gosti JSON
    $total  = isset($_POST['ovb_guests_total']) ? (int) $_POST['ovb_guests_total'] : 1;
    $guests = (isset($_POST['ovb_guest']) && is_array($_POST['ovb_guest'])) ? $_POST['ovb_guest'] : [];
    $clean  = [];
    foreach ($guests as $g) {
        $clean[] = [
            'first_name' => sanitize_text_field(wp_unslash($g['first_name'] ?? '')),
            'last_name'  => sanitize_text_field(wp_unslash($g['last_name']  ?? '')),
            'gender'     => sanitize_text_field(wp_unslash($g['gender']     ?? '')),
            'dob'        => sanitize_text_field(wp_unslash($g['dob']        ?? '')),
            'phone'      => sanitize_text_field(wp_unslash($g['phone']      ?? '')),
            'passport'   => sanitize_text_field(wp_unslash($g['passport']   ?? '')),
        ];
    }
    $order->update_meta_data('_ovb_guests_total', $total);
    $order->update_meta_data('_ovb_guests_json', wp_json_encode($clean));
}, 10, 2);
