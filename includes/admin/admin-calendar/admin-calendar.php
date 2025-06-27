<?php
defined('ABSPATH') || exit;

require_once dirname(__DIR__) . '../../helpers/logger.php';

function render_calendar_meta_box($post)
{
    wp_nonce_field('sacuvaj_additional_info_nonce', 'additional_info_nonce');
    // Get existing values
    $values = get_post_meta($post->ID, '_apartment_additional_info', true);

    // Predefined accommodation types
    $accommodation_types = [
        'apartment' => 'Apartment',
        'house' => 'House',
        'villa' => 'Villa',
        'cottage' => 'Cottage',
        'studio' => 'Studio'
    ];
    ob_start(); ?>

    <div class="admin-calendar-container">
        <input type="hidden" id="ov_product_id" value="<?php echo esc_attr($post->ID); ?>">

        <div class="calendar-nav">
            <i class="prev-month ">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron-icon chevron-left">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>


            </i>
            <div class="month-year text-center">
                <h3></h3>
            </div>
            <i class="next-month ">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron-icon chevron-right">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>

            </i>
        </div>
        <div class="admin-table-wrapper">
            <table class="admin-table table table-bordered">
                <thead>
                    <tr>
                        <th>P</th>
                        <th>U</th>
                        <th>S</th>
                        <th>Č</th>
                        <th>P</th>
                        <th>S</th>
                        <th>N</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Calendar cells are dynamically rendered via JS -->
                </tbody>
            </table>
        </div>


        <div class="price-settings-tabs">

            <!-- TABS -->
            <ul class="subsubsub">
                <li class="tab-link current" data-tab="tab-type">Accommodation Type</li>
                <li class="tab-link" data-tab="tab-location">Location</li>
                <li class="tab-link" data-tab="tab-capacity">Capacity</li>
                <li class="tab-link" data-tab="tab-general">Price Types</li>
                <li class="tab-link" data-tab="tab-set-price">Set Prices</li>
                <li class="tab-link" data-tab="tab-calendar-status">Availability Updates</li>
            </ul>
            <div class="content">

                <!-- TAB 1: Accommodation Type -->
                <div class="tab-content" id="tab-type">
                    <h4>Accommodation Type</h4>
                    <p>
                        <label>Type:</label>
                        <select name="additional_info[accommodation_type]">
                            <?php foreach ($accommodation_types as $key => $type): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($values['accommodation_type'] ?? '', $key); ?>>
                                    <?php echo esc_html($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                </div>

                <!-- TAB 2: Location -->
                <div class="tab-content" id="tab-location" style="display: none;">
                    <h4>Location Details</h4>
                    <p>
                        <label>Street Name:</label>
                        <input type="text" name="additional_info[street_name]"
                            value="<?php echo esc_attr($values['street_name'] ?? ''); ?>" placeholder="Enter street name" />
                    </p>
                    <p>
                        <label>City:</label>
                        <input type="text" name="additional_info[city]"
                            value="<?php echo esc_attr($values['city'] ?? ''); ?>" placeholder="Enter city" />
                    </p>
                    <p>
                        <label>Country:</label>
                        <input type="text" name="additional_info[country]"
                            value="<?php echo esc_attr($values['country'] ?? ''); ?>" placeholder="Enter country" />
                    </p>
                </div>

                <!-- TAB 3: Capacity -->
                <div class="tab-content" id="tab-capacity" style="display: none;">
                    <h4>Capacity Details</h4>
                    <p>
                        <label>Max Guests:</label>
                        <input type="number" min="1" name="additional_info[max_guests]"
                            value="<?php echo absint($values['max_guests'] ?? 1); ?>" />
                    </p>
                    <p>
                        <label>Bedrooms:</label>
                        <input type="number" min="1" name="additional_info[bedrooms]"
                            value="<?php echo absint($values['bedrooms'] ?? 1); ?>" />
                    </p>
                    <p>
                        <label>Beds:</label>
                        <input type="number" min="1" name="additional_info[beds]"
                            value="<?php echo absint($values['beds'] ?? 1); ?>" />
                    </p>
                    <p>
                        <label>Bathrooms:</label>
                        <input type="number" min="1" name="additional_info[bathrooms]"
                            value="<?php echo absint($values['bathrooms'] ?? 1); ?>" />
                    </p>
                </div>

                <!-- TAB 4: Price Types -->
                <div class="tab-content" id="tab-general" style="display: none;">
                    <h4>Define Price Types</h4>
                    <table class="form-table">
                        <tr>
                            <th><label for="regular_price">Regular Price:</label></th>
                            <td><input type="number" id="regular_price" name="regular_price" class="price-input"
                                    placeholder="Enter regular price" /></td>
                        </tr>
                        <tr>
                            <th><label for="weekend_price">Weekend Price:</label></th>
                            <td><input type="number" id="weekend_price" name="weekend_price" class="price-input"
                                    placeholder="Enter weekend price" /></td>
                        </tr>
                        <tr>
                            <th><label for="discount_price">Discount Price:</label></th>
                            <td><input type="number" id="discount_price" name="discount_price" class="price-input"
                                    placeholder="Enter discount price" /></td>
                        </tr>
                        <tr>
                            <th><label for="custom_price">Custom Price:</label></th>
                            <td><input type="number" id="custom_price" name="custom_price" class="price-input"
                                    placeholder="Enter custom price" /></td>
                        </tr>
                    </table>
                    <button type="button" id="save_price_types" class="button button-secondary">Save Price Types</button>
                </div>

                <!-- TAB 5: Bulk Price Update -->
                <div class="tab-content" id="tab-set-price" style="display: none;">
                    <h4>Price Updates</h4>
                    <table class="form-table">
                        <tr>
                            <th><label for="price_type">Price Type:</label></th>
                            <td>
                                <select id="price_type" style="width: 120px;">
                                    <option value="regular">Regular</option>
                                    <option value="weekend">Weekend</option>
                                    <option value="discount">Discount</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="apply_rule">Apply to:</label></th>
                            <td>
                                <select id="apply_rule" style="width: 140px;">
                                    <option value="weekdays">Weekdays</option>
                                    <option value="weekends">Weekends</option>
                                    <option value="full_month">Full Month</option>
                                    <option value="custom">Custom Range</option>
                                </select>
                            </td>
                        </tr>
                        <tr id="daterange_row" style="display: none;">
                            <th><label for="daterange">Custom Dates:</label></th>
                            <td>
                                <input type="text" id="daterange" style="width: 180px;" placeholder="Pick a date range">
                            </td>
                        </tr>
                    </table>
                    <button id="apply_price" class="button button-secondary" style="margin-top: 10px;">Apply Price</button>
                </div>

                <!-- TAB 6: Calendar Bulk Status -->
                <div class="tab-content" id="tab-calendar-status" style="display: none;">
                    <h4>Availability Updates</h4>
                    <div style="margin-bottom: 10px;">
                        <label for="bulk_status">Status:</label>
                        <select id="bulk_status" style="width: 140px; margin-left: 10px;">
                            <option value="">-- No change --</option>
                            <option value="available">Available</option>
                            <option value="unavailable">Unavailable</option>
                            <option value="booked">Booked</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <label for="status_apply_rule">Apply to:</label>
                        <select id="status_apply_rule" style="width: 140px; margin-left: 10px;">
                            <option value="weekdays">Weekdays</option>
                            <option value="weekends">Weekends</option>
                            <option value="full_month">Full Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>

                    <div id="status_daterange_container" style="margin-bottom: 10px; display: none;">
                        <label for="status_daterange">Custom Dates:</label>
                        <input type="text" id="status_daterange" style="margin-left: 10px;" placeholder="Pick a date range">
                    </div>

                    <button id="apply_status" style="margin-top: 10px;" class="button button-secondary">Apply
                        Status</button>
                </div>
            </div>
        </div>



    </div>

    <hr>

    <input type="hidden" name="ov_bulk_status" id="ov_bulk_status_input" value="">
    <input type="hidden" name="ov_status_apply_rule" id="ov_status_apply_rule_input" value="">
    <input type="hidden" name="ov_status_daterange" id="ov_status_daterange_input" value="">

    <!-- add client modal -->
    <div id="client_modal_wrapper" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2001;">
        <div id="client_modal">
            <i class="close_modal" onclick="closeClientModal()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                    <!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                    <path fill="#fff"
                        d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z" />
                </svg>

            </i>
            <h3>Dodaj korisnika</h3>
            <div class="add-information">
                <input type="text" id="client_first_name" placeholder="First name" name="client_first_name">
                <input type="text" id="client_last_name" placeholder="Last name" name="client_last_name">
                <input type="email" id="client_email" placeholder="Email" name="client_email">
                <input type="text" id="client_phone" placeholder="Phone" name="client_phone">
                <input type="number" id="client_guests" placeholder="Number of guests" name="client_guests">
                <input type="text" id="client_date_range">
                <input type="hidden" id="client_modal_date_input">
            </div>
            <div class="buttons">
                <button id="client_modal_save">Sačuvaj</button>
                <button onclick="closeClientModal()">Otkaži</button>
            </div>
        </div>
    </div>
    <!-- add client modal -->

    <!-- edit single price day modal -->
    <div id="price_modal_wrapper" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2001;">
        <div id="price_modal">
            <i class="close_modal" onclick="jQuery('#price_modal_wrapper').hide()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                    <!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                    <path fill="#fff"
                        d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z" />
                </svg>
            </i>
            <h3>Unesi cenu za <span id="price_modal_date"></span></h3>
            <input type="hidden" id="price_modal_date_input" />
            <input type="number" id="price_modal_input" placeholder="Unesi cenu" style="width:100%; padding:10px;">
            <br><br>
            <div class="buttons">
                <button id="price_modal_save">Sačuvaj</button>
                <button onclick="jQuery('#price_modal_wrapper').hide()">Otkaži</button>
            </div>
        </div>
    </div>

    <!-- edit single price day modal -->

    <!-- remove client modal style="background:#fff; max-width:400px; margin:10% auto; padding:20px; border-radius:10px;"-->
    <div id="client_action_modal_wrapper"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2001;">
        <div id="client_action_modal">
            <i class="close_modal" onclick="jQuery('#client_action_modal_wrapper').hide()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                    <!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                    <path fill="#fff"
                        d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z" />
                </svg>
            </i>
            <div>
                <h3>Gost: <span id="client_action_name"></span></h3>
                <h3>Email: <span id="client_action_email"></span></h3>
                <h3>Telefon: <span id="client_action_phone"></span></h3>
                <h3>Broj Osoba: <span id="client_action_number_of_guests"></span></h3>
                <h3>Raspon dana: <span id="client_action_date_range"></span></h3>
                <h3>Datum: <span id="client_action_date"></span></h3>
                <input type="hidden" id="client_action_date_input" />
                <input type="hidden" id="client_action_email_input" />
            </div>
            <br>
            <div class="buttons">
                <button id="delete_client_single">Obriši samo ovaj dan</button>
                <button id="delete_client_all">Obriši celu rezervaciju</button>
                <button onclick="jQuery('#client_action_modal_wrapper').hide()">Otkaži</button>
            </div>
        </div>
    </div>
    <script>
        function closeClientModal() {
            jQuery('#client_first_name, #client_last_name, #client_email, #client_phone, #client_guests, #client_date_range, #client_modal_date_input').val('');
            jQuery("#client_modal_wrapper").hide();
        }
    </script>
    <!-- remove client modal -->

    <?php echo ob_get_clean();
}
function save_additional_apartment_info($post_id)
{
    if (
        !isset($_POST['additional_info_nonce']) ||
        !wp_verify_nonce($_POST['additional_info_nonce'], 'sacuvaj_additional_info_nonce')
    ) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    // Definišemo tipove smeštaja ovde
    $accommodation_types = [
        'apartment' => 'Apartment',
        'house' => 'House',
        'villa' => 'Villa',
        'cottage' => 'Cottage',
        'studio' => 'Studio'
    ];

    $data = $_POST['additional_info'] ?? [];
    $sanitized = [];

    // Sanitizacija svih polja
    $sanitized['street_name'] = sanitize_text_field($data['street_name'] ?? '');

    // Provera tipa smeštaja
    $sanitized['accommodation_type'] = isset($data['accommodation_type']) && array_key_exists($data['accommodation_type'], $accommodation_types)
        ? sanitize_key($data['accommodation_type'])
        : 'apartment';

    $sanitized['city'] = sanitize_text_field($data['city'] ?? '');
    $sanitized['country'] = sanitize_text_field($data['country'] ?? '');
    $sanitized['max_guests'] = !empty($data['max_guests']) ? absint($data['max_guests']) : 1;
    $sanitized['bedrooms'] = !empty($data['bedrooms']) ? absint($data['bedrooms']) : 1;
    $sanitized['beds'] = !empty($data['beds']) ? absint($data['beds']) : 1;
    $sanitized['bathrooms'] = !empty($data['bathrooms']) ? absint($data['bathrooms']) : 1;

    update_post_meta($post_id, '_apartment_additional_info', $sanitized);
}
add_action('save_post', 'save_additional_apartment_info');
