<?php

defined('ABSPATH') || exit;
require_once dirname(__DIR__) . '/helpers/logger.php';


//Apartmant info metabox -> // Need update

function apartment_info_custom_fields_meta_box() {
    add_meta_box(
        'apartment_info_meta_box_id',
        'What this place offers',
        function($post) { show_custom_icons_fields($post, '_apartment_info_icons', 'sacuvaj_info_nonce'); },
        'product',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'apartment_info_custom_fields_meta_box');

function apartment_rules_custom_fields_meta_box() {
    add_meta_box(
        'apartment_rules_meta_box_id',
        'Things to know',
        function($post) { show_custom_icons_fields($post, '_apartment_rules_icons', 'sacuvaj_rules_nonce'); },
        'product',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'apartment_rules_custom_fields_meta_box');



function show_custom_icons_fields($post, $meta_key, $nonce_name) {
    wp_enqueue_media();
    $ikonice = get_post_meta($post->ID, $meta_key, true);
    wp_nonce_field($nonce_name, $nonce_name.'_nonce');

    static $script_loaded = false; 
    ?>
    
    <div class="ikonice-repeater" data-meta-key="<?php echo $meta_key; ?>">
        <?php if (!empty($ikonice)) : ?>
            <?php foreach ($ikonice as $index => $ik) : ?>
                <div class="ikonica-item">
                    <div class="image-preview">
                        <?php if(!empty($ik['ikona_url'])) : ?>
                            <img src="<?php echo esc_url($ik['ikona_url']); ?>" style="max-height: 50px;" />
                        <?php endif; ?>
                    </div>
                    <input type="text" name="<?php echo $meta_key; ?>[<?php echo $index; ?>][ikona_url]" 
                           value="<?php echo esc_attr($ik['ikona_url']); ?>" placeholder="Enter image URL or upload" class="ikona-url" />
                    <button type="button" class="upload-ikona-button" style="padding: 0 10px;">Choose image</button>
                    
                    <input type="text" name="<?php echo $meta_key; ?>[<?php echo $index; ?>][tekst]" 
                           value="<?php echo esc_attr($ik['tekst']); ?>" placeholder="Icon text" />
                    
                    <button type="button" class="remove-ikonica" style="padding: 0 10px; margin-left: 5px;">Remove</button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="dodaj-ikonicu" data-meta-key="<?php echo $meta_key; ?>">&#43; Add icon</button>

    <?php if(!$script_loaded) : // Loadujemo skriptu samo jednom ?>
    <script>
    jQuery(document).ready(function($) {
        // Dinamički dodaj event handler za svaki repeater
        $(document).on('click', '.dodaj-ikonicu', function() {
            const metaKey = $(this).data('meta-key');
            const container = $(this).prev('.ikonice-repeater');
            const index = container.find('.ikonica-item').length;
            
             const html = `
                <div class="ikonica-item">
                    <div class="image-preview"></div>
                    <input type="text" name="${metaKey}[${index}][ikona_url]" placeholder="Enter image URL or upload" class="ikona-url" />
                    <button type="button" class="upload-ikona-button">Choose image</button>
                    <input type="text" name="${metaKey}[${index}][tekst]" placeholder="Icon text" />
                    <button type="button" class="remove-ikonica">Remove</button>
                </div>
            `;
            container.append(html);
        });

        // Upload ikona
        $(document).on('click', '.upload-ikona-button', function(e) {
            e.preventDefault();
            const button = $(this);
            const custom_uploader = wp.media({
                title: 'Choose image',
                library: { type: 'image' },
                button: { text: 'Use this image' },
                multiple: false
            }).on('select', function() {
                const attachment = custom_uploader.state().get('selection').first().toJSON();
                button.siblings('.ikona-url').val(attachment.url);
                button.siblings('.image-preview').html(`<img src="${attachment.url}" style="max-height: 50px; display: inline-block; background: #1b203a;" />`);
            }).open();
        });

        // Ukloni item
        $(document).on('click', '.remove-ikonica', function() {
            $(this).closest('.ikonica-item').remove();
        });
    });
    </script>
    <?php 
        $script_loaded = true; // Označavamo da je skripta već učitana
        endif; 
    ?>

    <style>
        .ikonica-item { 
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            background: #fff;
            display: flex;
        }
        .ikonica-item input {
            min-width: 260px;
        }
        .image-preview { 
            width: 50px;
            height: 50px;
            /* margin-bottom: 10px; */
            display: inline-flex;
            background: #99a2d3;
            align-items: center;
            justify-content: center;
        }
        .dodaj-ikonicu{
            height: 50px;
            width: 120px;
            padding: 0 10px;
            cursor: pointer;
            font-size: 15px;
        }
        .upload-ikona-button {
            margin: 0 10px 0 5px;
            cursor: pointer;
        }
        .remove-ikonica{
            cursor: pointer;
        }
    </style>
    <?php
}


function save_custom_icons_fields($post_id) {
    // Snimi info ikone
    if (isset($_POST['sacuvaj_info_nonce_nonce']) && wp_verify_nonce($_POST['sacuvaj_info_nonce_nonce'], 'sacuvaj_info_nonce')) {
        process_icons_saving($post_id, '_apartment_info_icons');
    }

    // Snimi rules ikone
    if (isset($_POST['sacuvaj_rules_nonce_nonce']) && wp_verify_nonce($_POST['sacuvaj_rules_nonce_nonce'], 'sacuvaj_rules_nonce')) {
        process_icons_saving($post_id, '_apartment_rules_icons');
    }
}
add_action('save_post', 'save_custom_icons_fields');

function process_icons_saving($post_id, $meta_key) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $ikonice = isset($_POST[$meta_key]) ? array_values($_POST[$meta_key]) : [];
    $filtrirane_ikonice = [];

    foreach ($ikonice as $item) {
        $url = esc_url_raw(trim($item['ikona_url'] ?? ''));
        $tekst = sanitize_text_field(trim($item['tekst'] ?? ''));
        
        if (!empty($url) || !empty($tekst)) {
            $filtrirane_ikonice[] = [
                'ikona_url' => $url,
                'tekst'     => $tekst
            ];
        }
    }

    if (!empty($filtrirane_ikonice)) {
        update_post_meta($post_id, $meta_key, $filtrirane_ikonice);
    } else {
        delete_post_meta($post_id, $meta_key);
    }
}

// Show fields
function show_additional_apartment_info($post) {
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
    ?>
    
    <div class="apartment-additional-fields">
        <!-- Location Section -->
        <div class="section">
            <h4>Location Details</h4>
            <p>
                <label>Street Name:</label>
                <input type="text" name="additional_info[street_name]" 
                       value="<?php echo esc_attr($values['street_name'] ?? ''); ?>" 
                       placeholder="Enter street name" />
            </p>

        </div>

        <!-- Accommodation Type Section -->
        <div class="section">
            <h4>Accommodation Type</h4>
            <p>
                <label>Type:</label>
                <select name="additional_info[accommodation_type]">
                    <?php foreach($accommodation_types as $key => $type) : ?>
                        <option value="<?php echo esc_attr($key); ?>" 
                            <?php selected($values['accommodation_type'] ?? '', $key); ?>>
                            <?php echo esc_html($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label>City:</label>
                <input type="text" name="additional_info[city]" 
                       value="<?php echo esc_attr($values['city'] ?? ''); ?>" 
                       placeholder="Enter city" />
            </p>
            <p>
                <label>Country:</label>
                <input type="text" name="additional_info[country]" 
                       value="<?php echo esc_attr($values['country'] ?? ''); ?>" 
                       placeholder="Enter country" />
            </p>
        </div>

        <!-- Capacity Section -->
        <div class="section">
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
    </div>

    <style>
        .apartment-additional-fields .section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #eee;
        }
        .apartment-additional-fields label {
            display: inline-block;
            width: 150px;
        }
        .apartment-additional-fields input[type="text"],
        .apartment-additional-fields input[type="url"],
        .apartment-additional-fields input[type="number"],
        .apartment-additional-fields select {
            width: 300px;
            padding: 5px;
        }
    </style>
    <?php
}
