<?php
defined('ABSPATH') || exit;

/**
 * =========================
 *  OV Booking Order Hooks
 * =========================
 */

// === iCal Service ===
if (file_exists(OVB_BOOKING_PATH . 'includes/class-ical-service.php')) {
    require_once OVB_BOOKING_PATH . 'includes/class-ical-service.php';
}

/**
 * CHECKOUT VALIDATION
 */
add_action('woocommerce_after_checkout_validation', function($data, $errors) {
    $guests = $_POST['ovb_guest'] ?? [];
    $has_guests = is_array($guests) && count($guests) > 0;

    if (!$has_guests) {
        $billing_phone = trim($_POST['billing_phone'] ?? '');
        if (empty($billing_phone)) {
            $errors->add('ovb_billing_phone_error', __("Enter phone number!", 'ov-booking'));
        }
    } else {
        $phones = array_filter(array_map(fn($g) => trim($g['phone'] ?? ''), $guests));
        if (empty($phones)) {
            $errors->add('ovb_guest_phone_error', __("At least one guest must have a phone number!", 'ov-booking'));
        }
        foreach ($guests as $i => $g) {
            foreach (['first_name', 'last_name', 'birthdate', 'gender'] as $field) {
                if (empty($g[$field])) {
                    $errors->add('ovb_guest_error', __("All guest fields #" . ($i + 1) . " must be filled!", 'ov-booking'));
                    break 2;
                }
            }
        }
    }
}, 10, 2);

/**
 * ORDER META - SAVE BOOKING AND GUEST DATA
 */
add_action('woocommerce_checkout_update_order_meta', function($order_id, $data = []) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    foreach (WC()->cart->get_cart() as $item) {
        if (!empty($item['ovb_all_dates'])) {
            $order->update_meta_data('all_dates', sanitize_text_field($item['ovb_all_dates']));
            if (!empty($item['start_date'])) {
                $order->update_meta_data('start_date', sanitize_text_field($item['start_date']));
            }
            if (!empty($item['end_date'])) {
                $order->update_meta_data('end_date', sanitize_text_field($item['end_date']));
            }
            if (isset($item['guests'])) {
                $order->update_meta_data('guests', intval($item['guests']));
            }
            break;
        }
    }

    // Guests
    $guests = isset($_POST['ovb_guest']) && is_array($_POST['ovb_guest']) ? $_POST['ovb_guest'] : [];
    $order->update_meta_data('_ovb_guests', $guests);

    // Payer (billing) data
    foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
        $value = isset($_POST['billing_' . $field]) ? sanitize_text_field($_POST['billing_' . $field]) : '';
        if ($value) {
            $order->update_meta_data('booking_client_' . $field, $value);
            $order->update_meta_data($field, $value);
        }
    }

    // _ovb_ meta for compatibility
    $order->update_meta_data('_ovb_start_date', $order->get_meta('start_date'));
    $order->update_meta_data('_ovb_end_date', $order->get_meta('end_date'));
    $order->update_meta_data('_ovb_guests_num', $order->get_meta('guests'));
    $order->update_meta_data('_ovb_paid_by_other', !empty($_POST['ovb_paid_by_other']) ? 'yes' : 'no');

    $order->save();
}, 10, 2);

/**
 * ORDER ITEM META - ADD BOOKING DETAILS TO EACH ITEM
 */
// add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
//     if (!empty($values['ovb_all_dates'])) {
//         $item->add_meta_data('_ovb_calendar_data', sanitize_text_field($values['ovb_all_dates']));
//         $item->add_meta_data('booking_dates', sanitize_text_field($values['ovb_all_dates']));
//         $item->add_meta_data('ovb_all_dates', sanitize_text_field($values['ovb_all_dates']));
//     }
//     if (!empty($values['guests'])) {
//         $item->add_meta_data('_ovb_guests', intval($values['guests']));
//         $item->add_meta_data('guests', intval($values['guests']));
//         $item->add_meta_data('ovb_guest_count', intval($values['guests']));
//     }
//     if (!empty($values['start_date'])) {
//         $item->add_meta_data('_ovb_range_start', sanitize_text_field($values['start_date']));
//         $item->add_meta_data('rangeStart', sanitize_text_field($values['start_date']));
//     }
//     if (!empty($values['end_date'])) {
//         $item->add_meta_data('_ovb_range_end', sanitize_text_field($values['end_date']));
//         $item->add_meta_data('rangeEnd', sanitize_text_field($values['end_date']));
//     }
// }, 10, 4);

/**
 * COPY FIRST CART ITEM BOOKING DATA TO ORDER (for WC < 7.4)
 */
add_action('woocommerce_checkout_create_order', function($order, $data){
    $items = WC()->cart->get_cart();
    $first = reset($items);
    if ($first) {
        if (!empty($first['start_date'])) {
            $order->update_meta_data('start_date', sanitize_text_field($first['start_date']));
            $order->update_meta_data('_ovb_start_date', sanitize_text_field($first['start_date']));
        }
        if (!empty($first['end_date'])) {
            $order->update_meta_data('end_date', sanitize_text_field($first['end_date']));
            $order->update_meta_data('_ovb_end_date', sanitize_text_field($first['end_date']));
        }
        if (isset($first['guests'])) {
            $order->update_meta_data('guests', absint($first['guests']));
            $order->update_meta_data('_ovb_guests_num', absint($first['guests']));
        }
    }
}, 10, 2);

/**
 * ICAL - EMAIL ICS FILE TO CUSTOMER ON COMPLETED ORDER
 */
add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    foreach ($order->get_items() as $item) {
        if ($item->get_meta('ovb_all_dates') || $item->get_meta('_ovb_calendar_data')) {
            $ics_content = OVB_iCal_Service::generate_ics_string($order);
            $upload_dir = wp_upload_dir();
            $file_path = trailingslashit($upload_dir['basedir']) . "booking-{$order_id}.ics";
            file_put_contents($file_path, $ics_content);

            wp_mail(
                $order->get_billing_email(),
                __('📅 Booking Calendar File', 'ov-booking'),
                __('Thank you for your reservation. Attached is your calendar file (.ics) you can import.', 'ov-booking'),
                ['Content-Type: text/html; charset=UTF-8'],
                [$file_path]
            );

            register_shutdown_function(function() use ($file_path) {
                if (file_exists($file_path)) unlink($file_path);
            });
            break;
        }
    }
}, 10, 1);

/**
 * CALENDAR UPDATE ON ORDER COMPLETED
 */
add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $guest_first = $order->get_meta('booking_client_first_name') ?: $order->get_meta('first_name') ?: $order->get_billing_first_name();
    $guest_last  = $order->get_meta('booking_client_last_name') ?: $order->get_meta('last_name') ?: $order->get_billing_last_name();
    $guest_email = $order->get_meta('booking_client_email') ?: $order->get_meta('email') ?: $order->get_billing_email();
    $guest_phone = $order->get_meta('booking_client_phone') ?: $order->get_meta('phone') ?: $order->get_billing_phone();

    foreach ($order->get_items() as $item) {
        $prod_id = $item->get_product_id();
        $item_id = $item->get_id();
        if (!$prod_id) continue;

        $booking_id = $order_id . '_' . $item_id;
        $dates_meta = $item->get_meta('ovb_all_dates') ?: $item->get_meta('_ovb_calendar_data') ?: $item->get_meta('booking_dates');
        if (empty($dates_meta) || !is_string($dates_meta)) continue;

        $dates = array_filter(array_map('trim', explode(',', $dates_meta)));
        if (empty($dates)) continue;

        $calendar_data = get_post_meta($prod_id, '_ovb_calendar_data', true);
        if (!is_array($calendar_data)) $calendar_data = [];

        $client_data = [
            'firstName'   => $guest_first,
            'lastName'    => $guest_last,
            'email'       => $guest_email,
            'phone'       => $guest_phone,
            'guests'      => $order->get_meta('guests') ?: $order->get_meta('_ovb_guests_num') ?: 1,
            'rangeStart'  => $dates[0] ?? '',
            'rangeEnd'    => end($dates) ?: '',
        ];

        $last_date = end($dates);

        foreach ($dates as $i => $date) {
            if (!isset($calendar_data[$date]) || !is_array($calendar_data[$date])) {
                $calendar_data[$date] = [];
            }
            $existing_clients = $calendar_data[$date]['clients'] ?? [];
            if (!is_array($existing_clients)) $existing_clients = [];
            $existing_clients = array_filter($existing_clients, fn($cl) => !isset($cl['bookingId']) || $cl['bookingId'] !== $booking_id);
            $existing_clients[] = array_merge($client_data, [
                'bookingId'   => $booking_id,
                'isCheckin'   => ($i === 0),
                'isCheckout'  => ($i === count($dates)-1),
            ]);
            $calendar_data[$date] = array_merge($calendar_data[$date], [
                'status' => ($i === count($dates)-1) ? ($calendar_data[$date]['status'] ?? 'available') : 'booked',
                'clients' => array_values($existing_clients),
            ]);
        }
        update_post_meta($prod_id, '_ovb_calendar_data', $calendar_data);
    }
}, 20, 1);

/**
 * RELEASE DATES ON CANCEL/REFUND/DELETE ORDER
 */
add_action('woocommerce_order_status_cancelled', 'ovb_release_calendar_dates_on_cancel', 20);
add_action('woocommerce_order_status_refunded', 'ovb_release_calendar_dates_on_cancel', 20);
add_action('woocommerce_before_trash_order', 'ovb_release_calendar_dates_on_cancel', 20);
add_action('woocommerce_before_delete_order', 'ovb_release_calendar_dates_on_cancel', 20);
add_action('untrashed_post', 'ovb_restore_calendar_dates_on_untrash', 10, 1);
/**
 * Uklanja rezervaciju iz kalendara kada se order obriše (trash)
 */
// function ovb_release_calendar_dates_on_cancel($order) {
//     $order = is_numeric($order) ? wc_get_order($order) : $order;
//     if (!$order) return;

//     foreach ($order->get_items() as $item) {
//         $product_id = $item->get_product_id();
//         if (!$product_id) continue;
        
//         $item_id = $item->get_id();
//         $booking_id = $order->get_id() . '_' . $item_id;
        
//         $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
//         if (!is_array($calendar_data)) $calendar_data = [];
        
//         // Proveri da li booking uopšte postoji u kalendaru
//         if (!ovb_booking_exists_in_calendar($calendar_data, $booking_id)) {
//             continue;
//         }
        
//         foreach ($calendar_data as $date => &$data) {
//             if (!isset($data['clients']) || !is_array($data['clients'])) continue;
            
//             // Ukloni samo ako postoji
//             $data['clients'] = array_values(array_filter($data['clients'], 
//                 fn($cl) => !isset($cl['bookingId']) || $cl['bookingId'] !== $booking_id
//             ));
            
//             if (empty($data['clients'])) {
//                 $data['status'] = 'available';
//             }
//         }
//         unset($data);
        
//         update_post_meta($product_id, '_ovb_calendar_data', $calendar_data);
//     }
// }

function ovb_release_calendar_dates_on_cancel($order) {
    $order = is_numeric($order) ? wc_get_order($order) : $order;
    if (!$order) return;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        if (!$product_id) continue;
        
        $item_id = $item->get_id();
        $booking_id = $order->get_id() . '_' . $item_id;
        
        $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
        if (!is_array($calendar_data)) $calendar_data = [];
        
        $dates_meta = $item->get_meta('ovb_all_dates') ?: $item->get_meta('_ovb_calendar_data') ?: $item->get_meta('booking_dates');
        if (empty($dates_meta) || !is_string($dates_meta)) continue;
        
        $dates = array_filter(array_map('trim', explode(',', $dates_meta)));
        
        foreach ($dates as $date) {
            if (!isset($calendar_data[$date])) continue;
            
            if (isset($calendar_data[$date]['clients']) && is_array($calendar_data[$date]['clients'])) {
                $calendar_data[$date]['clients'] = array_values(array_filter(
                    $calendar_data[$date]['clients'],
                    function($client) use ($booking_id) {
                        return !isset($client['bookingId']) || $client['bookingId'] !== $booking_id;
                    }
                ));
                
                // If no clients left, set status to available
                if (empty($calendar_data[$date]['clients'])) {
                    $calendar_data[$date]['status'] = 'available';
                }
            }
        }
        
        update_post_meta($product_id, '_ovb_calendar_data', $calendar_data);
    }
}
/**
 * Vraća rezervaciju u kalendar kada se order vrati iz traša
 */
// untrash
// function ovb_restore_calendar_dates_on_untrash($post_id) {
//     // Proveri da li je u pitanju WooCommerce order
//     if ('shop_order' !== get_post_type($post_id)) return;
    
//     $order = wc_get_order($post_id);
//     if (!$order) return;

//     // Proveri status - ako nije completed, ne treba da se prikazuje u kalendaru
//     if ('completed' !== $order->get_status()) return;

//     foreach ($order->get_items() as $item) {
//         $product_id = $item->get_product_id();
//         if (!$product_id) continue;
        
//         $item_id = $item->get_id();
//         $booking_id = $order->get_id() . '_' . $item_id;
        
//         // Uzmi sve datume iz order meta
//         $dates_meta = $item->get_meta('ovb_all_dates') ?: $item->get_meta('_ovb_calendar_data') ?: $item->get_meta('booking_dates');
//         if (empty($dates_meta) || !is_string($dates_meta)) continue;
        
//         $dates = array_filter(array_map('trim', explode(',', $dates_meta)));
//         if (empty($dates)) continue;
        
//         $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
//         if (!is_array($calendar_data)) $calendar_data = [];
        
//         // Proveri da li booking već postoji u kalendaru
//         if (ovb_booking_exists_in_calendar($calendar_data, $booking_id)) {
//             continue;
//         }
        
//         $guest_first = $order->get_meta('booking_client_first_name') ?: $order->get_billing_first_name();
//         $guest_last = $order->get_meta('booking_client_last_name') ?: $order->get_billing_last_name();
//         $guest_email = $order->get_meta('booking_client_email') ?: $order->get_billing_email();
//         $guest_phone = $order->get_meta('booking_client_phone') ?: $order->get_billing_phone();
        
//         $client_data = [
//             'firstName' => $guest_first,
//             'lastName' => $guest_last,
//             'email' => $guest_email,
//             'phone' => $guest_phone,
//             'guests' => $order->get_meta('guests') ?: 1,
//             'rangeStart' => $dates[0] ?? '',
//             'rangeEnd' => end($dates) ?: '',
//         ];
        
//         foreach ($dates as $i => $date) {
//             if (!isset($calendar_data[$date]) || !is_array($calendar_data[$date])) {
//                 $calendar_data[$date] = [];
//             }
            
//             $existing_clients = $calendar_data[$date]['clients'] ?? [];
//             if (!is_array($existing_clients)) $existing_clients = [];
            
//             $existing_clients[] = array_merge($client_data, [
//                 'bookingId' => $booking_id,
//                 'isCheckin' => ($i === 0),
//                 'isCheckout' => ($i === count($dates)-1),
//             ]);
            
//             $calendar_data[$date]['clients'] = array_values($existing_clients);
//             $calendar_data[$date]['status'] = ($i === count($dates)-1) ? 'available' : 'booked';
//         }
        
//         update_post_meta($product_id, '_ovb_calendar_data', $calendar_data);
//     }
// }
function ovb_restore_calendar_dates_on_untrash($post_id) {
    if ('shop_order' !== get_post_type($post_id)) return;
    
    $order = wc_get_order($post_id);
    if (!$order) return;

    // Only restore if order is being set to completed status
    if ('completed' !== $order->get_status()) return;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        if (!$product_id) continue;
        
        $item_id = $item->get_id();
        $booking_id = $order->get_id() . '_' . $item_id;
        
        $dates_meta = $item->get_meta('ovb_all_dates') ?: $item->get_meta('_ovb_calendar_data') ?: $item->get_meta('booking_dates');
        if (empty($dates_meta) || !is_string($dates_meta)) continue;
        
        $dates = array_filter(array_map('trim', explode(',', $dates_meta)));
        if (empty($dates)) continue;
        
        $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
        if (!is_array($calendar_data)) $calendar_data = [];
        
        // Check if booking already exists
        $booking_exists = false;
        foreach ($dates as $date) {
            if (isset($calendar_data[$date]['clients']) && is_array($calendar_data[$date]['clients'])) {
                foreach ($calendar_data[$date]['clients'] as $client) {
                    if (isset($client['bookingId']) && $client['bookingId'] === $booking_id) {
                        $booking_exists = true;
                        break 2;
                    }
                }
            }
        }
        
        if ($booking_exists) continue;
        
        $guest_first = $order->get_meta('booking_client_first_name') ?: $order->get_billing_first_name();
        $guest_last = $order->get_meta('booking_client_last_name') ?: $order->get_billing_last_name();
        $guest_email = $order->get_meta('booking_client_email') ?: $order->get_billing_email();
        $guest_phone = $order->get_meta('booking_client_phone') ?: $order->get_billing_phone();
        
        foreach ($dates as $i => $date) {
            if (!isset($calendar_data[$date]) || !is_array($calendar_data[$date])) {
                $calendar_data[$date] = [
                    'status' => 'available',
                    'isPast' => false,
                    'clients' => []
                ];
            }
            
            $calendar_data[$date]['clients'][] = [
                'bookingId' => $booking_id,
                'firstName' => sanitize_text_field($guest_first),
                'lastName' => sanitize_text_field($guest_last),
                'email' => sanitize_email($guest_email),
                'phone' => sanitize_text_field($guest_phone),
                'guests' => absint($item->get_meta('guests') ?: 1),
                'rangeStart' => $dates[0],
                'rangeEnd' => end($dates),
                'isCheckin' => ($i === 0),
                'isCheckout' => ($i === count($dates)-1),
                'order_id' => $order->get_id()
            ];
            
            $calendar_data[$date]['status'] = ($i === count($dates)-1) ? 'available' : 'booked';
        }
        
        update_post_meta($product_id, '_ovb_calendar_data', $calendar_data);
    }
}

//brisanje iz rodera i kalendara 
add_action('before_delete_post', function($post_id) {
    if (get_post_type($post_id) !== 'shop_order') return;

    $order = wc_get_order($post_id);
    if (!$order) return;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        if (!$product_id) continue;

        $booking_id = $order->get_id() . '_' . $item->get_id();

        // Ukloni klijenta sa tim bookingId iz svih datuma u kalendaru za taj proizvod
        $calendar_data = get_post_meta($product_id, '_ovb_calendar_data', true);
        if ($calendar_data) {
            $calendar = json_decode($calendar_data, true);
            foreach ($calendar as $date => &$day) {
                if (!empty($day['clients'])) {
                    $before = count($day['clients']);
                    $day['clients'] = array_filter($day['clients'], function($cl) use($booking_id) {
                        return $cl['bookingId'] !== $booking_id;
                    });
                    if ($before !== count($day['clients'])) {
                        // Update status
                        if (empty($day['clients'])) {
                            $day['clients'] = [];
                            $day['status'] = (!empty($day['price']) && $day['price'] > 0) ? 'available' : 'unavailable';
                        }
                    }
                }
            }
            update_post_meta($product_id, '_ovb_calendar_data', wp_json_encode($calendar));
        }
    }
});

/**
 * ADMIN: BOOKING DATA IN ORDER LIST & DETAILS
 */
add_filter('manage_woocommerce_page_wc-orders_columns', function($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'order_status') {
            $new['check-in-column'] = __('Check In', 'ov-booking');
            $new['check-out-column'] = __('Check Out', 'ov-booking');
            $new['guests-column'] = __('Total guests', 'ov-booking');
        }
    }
    return $new;
});
add_action('manage_woocommerce_page_wc-orders_custom_column', function($column, $order) {
    $df = get_option('date_format');
    switch ($column) {
        case 'check-in-column':
            $d = $order->get_meta('start_date') ?: $order->get_meta('_ovb_start_date');
            echo $d ? date_i18n($df, strtotime($d)) : '<em>(no date)</em>';
            break;
        case 'check-out-column':
            $d = $order->get_meta('end_date') ?: $order->get_meta('_ovb_end_date');
            echo $d ? date_i18n($df, strtotime($d)) : '<em>(no date)</em>';
            break;
        case 'guests-column':
            $g = $order->get_meta('guests') ?: $order->get_meta('_ovb_guests_num');
            echo $g ?: '<em>(no guests)</em>';
            break;
    }
}, 10, 2);

// ADMIN: booking info in order details (shipping address metabox)
// add_action('woocommerce_admin_order_data_after_shipping_address', function($order){
//     $start_date = $order->get_meta('start_date') ?: $order->get_meta('_ovb_start_date');
//     $end_date = $order->get_meta('end_date') ?: $order->get_meta('_ovb_end_date');
//     if (!$start_date || !$end_date) return;
//     $wp_date_format = get_option('date_format');
//     echo '<div class="ovb-booking-dates-wrapper" style="margin-top:20px; font-weight:bold;">';
//     echo '<h1 style="margin-bottom:15px">' . __('Period of Stay:', 'ov-booking') . '</h1>';
//     echo '<div class="ovb-booking-dates check-in" style="display:flex; align-items:center; gap:5px; margin-bottom:10px">';
//     echo '<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" ...></svg> ';
//     echo '<h2 style="margin:0">' . esc_html(date_i18n($wp_date_format, strtotime($start_date))) . '</h2>';
//     echo '</div>';
//     echo '<div class="ovb-booking-dates check-out" style="display:flex; align-items:center; gap:5px">';
//     echo '<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" ...></svg> ';
//     echo '<h2 style="margin:0">' . esc_html(date_i18n($wp_date_format, strtotime($end_date))) . '</h2>';
//     echo '</div>';
//     echo '</div>';
// });

add_action('woocommerce_admin_order_data_after_shipping_address', function($order){
    $start_date = $order->get_meta('start_date');
    $end_date   = $order->get_meta('end_date');
    if ($start_date || $end_date) {
        echo '<div class="ovb-booking-dates-wrapper" style="margin-top:20px; font-weight:bold;">';
        echo '<h3 style="margin-bottom:10px;">' . __('Booking Dates', 'ov-booking') . '</h3>';
        if ($start_date) {
            echo '<div style="margin-bottom:4px;">' . __('Check-in:', 'ov-booking') . ' <strong>' . date_i18n(get_option('date_format'), strtotime($start_date)) . '</strong></div>';
        }
        if ($end_date) {
            echo '<div>' . __('Check-out:', 'ov-booking') . ' <strong>' . date_i18n(get_option('date_format'), strtotime($end_date)) . '</strong></div>';
        }
        echo '</div>';
    }
});

// admin orders 

// Dodavanje kolone za gosta u order listu
add_filter('manage_edit-shop_order_columns', 'ovb_add_guest_column');
function ovb_add_guest_column($columns) {
    $new_columns = [];
    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        if ($key === 'order_number') {
            $new_columns['guest_name'] = __('Guest', 'ov-booking');
        }
    }
    return $new_columns;
}

// Prikaz imena gosta u admin order listi
add_action('manage_shop_order_posts_custom_column', 'ovb_display_guest_column', 10, 2);
function ovb_display_guest_column($column, $post_id) {
    if ($column === 'guest_name') {
        $first = get_post_meta($post_id, 'first_name', true);
        $last  = get_post_meta($post_id, 'last_name', true);
        echo $first || $last ? esc_html(trim("$first $last")) : '<em>' . __('No guest data', 'ov-booking') . '</em>';
    }
}

// 3) Prikaz gostiju u Edit Order ekranu
add_action( 'woocommerce_admin_order_data_after_billing_address', function( $order ) {
    echo ovb_render_guests_html( ovb_get_order_guests( $order ) );
}, 20 );
// admin orders end - ovde alolololo
/**
 * ADMIN: payer + guests info
 */
add_action('woocommerce_admin_order_item_headers', function($order){
    // Payer info
    echo '<div class="ovb-order-customer" style="margin:0 0 20px 0; padding:16px; background:#f5f5fa; border-radius:0;">';
    echo '<h3 style="margin-bottom:10px;">' . __('Details of the Payer:', 'ov-booking') . '</h3>';
    echo '<ul style="margin-left:0; padding-left:0; width:fit-content;">';
    $fields = [
        'Full Name' => ($order->get_meta('booking_client_first_name') ?: $order->get_billing_first_name()) . ' ' . ($order->get_meta('booking_client_last_name') ?: $order->get_billing_last_name()),
        'Email' => $order->get_meta('booking_client_email') ?: $order->get_billing_email(),
        'Phone' => $order->get_meta('booking_client_phone') ?: $order->get_billing_phone(),
        'Address' => $order->get_billing_address_1(),
        'City' => $order->get_billing_city(),
        'Country' => WC()->countries->countries[$order->get_billing_country()] ?? $order->get_billing_country(),
    ];
    foreach ($fields as $label => $val) {
        if ($val) {
            if ($label === 'Phone') {
                echo '<li><strong>' . esc_html($label) . ':</strong> <a href="tel:' . esc_attr($val) . '">' . esc_html($val) . '</a></li>';
            } elseif ($label === 'Email') {
                echo '<li><strong>' . esc_html($label) . ':</strong> <a href="mailto:' . esc_attr($val) . '">' . esc_html($val) . '</a></li>';
            } else {
                echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($val) . '</li>';
            }
        }
    }
    echo '</ul>';
    echo '</div>';

    // Guests info
    $guests = $order->get_meta('_ovb_guests');
    if (!is_array($guests) || empty($guests)) {
        $guests = [[
            'first_name' => $order->get_meta('booking_client_first_name') ?: $order->get_billing_first_name(),
            'last_name' => $order->get_meta('booking_client_last_name') ?: $order->get_billing_last_name(),
            'email' => $order->get_meta('booking_client_email') ?: $order->get_billing_email(),
            'phone' => $order->get_meta('booking_client_phone') ?: $order->get_billing_phone(),
            'birthdate' => $order->get_meta('birthdate') ?: '',
            'gender' => $order->get_meta('gender') ?: '',
            'id_number' => $order->get_meta('id_number') ?: '',
        ]];
    }
    $is_paid_by_other = $order->get_meta('_ovb_paid_by_other') === 'yes';

    if (!empty($guests)) {
        echo '<div style="display:flex; flex-wrap:wrap; gap:30px;">';
        foreach ($guests as $i => $guest) {
            echo '<div style="flex: 1 1 300px; background:#fff; padding:15px; border:1px solid #e5e5e5; border-radius:6px;">';
            $label = 'Guest #' . ($is_paid_by_other ? ($i + 1) : $i);
            if (!$is_paid_by_other && $i === 0) $label = 'Booking Person';
            echo '<strong style="display:block; margin-bottom:10px;">' . esc_html($label) . '</strong>';
            if ($is_paid_by_other && $i === 0) {
                echo '<span style="font-size:12px; color:#7c3aed; margin-top:4px; display:block;">(Different from payer)</span>';
            }
            echo '<ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:5px;">';
            $full_name = trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
            if ($full_name) {
                echo '<li><strong>Full Name:</strong> ' . esc_html($full_name) . '</li>';
            }
            if (!empty($guest['email'])) {
                echo '<li><strong>Email:</strong> <a href="mailto:' . esc_attr($guest['email']) . '">' . esc_html($guest['email']) . '</a></li>';
            }
            if (!empty($guest['phone'])) {
                echo '<li><strong>Phone:</strong> <a href="tel:' . esc_attr($guest['phone']) . '">' . esc_html($guest['phone']) . '</a></li>';
            }
            if (!empty($guest['birthdate'])) {
                $birth_ts = strtotime($guest['birthdate']);
                $birth_formatted = $birth_ts ? date_i18n(get_option('date_format'), $birth_ts) : $guest['birthdate'];
                echo '<li><strong>Date of Birth:</strong> ' . esc_html($birth_formatted) . '</li>';
            }
            if (!empty($guest['gender'])) {
                echo '<li><strong>Gender:</strong> ' . esc_html(ucfirst($guest['gender'])) . '</li>';
            }
            if (!empty($guest['id_number'])) {
                echo '<li><strong>ID Number:</strong> ' . esc_html($guest['id_number']) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        echo '</div>';
    }
});
