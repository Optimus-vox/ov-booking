<?php
defined('ABSPATH') || exit;
function google_maps_iframe_meta_box_callback($post) {
    $iframe = get_post_meta($post->ID, '_google_maps_iframe', true);
    wp_nonce_field('google_maps_iframe_nonce_action', 'google_maps_iframe_nonce');
    echo '<label for="google_maps_iframe">Google Maps iframe:</label>';
    echo '<textarea id="google_maps_iframe" name="google_maps_iframe" rows="4" style="width:100%;">' . esc_textarea($iframe) . '</textarea>';
}

function google_maps_iframe_save_meta_box($post_id) {
    // Proveravamo da li je nonce validan
    if (!isset($_POST['google_maps_iframe_nonce']) || 
        !wp_verify_nonce($_POST['google_maps_iframe_nonce'], 'google_maps_iframe_nonce_action')) {
        return;
    }

    // Provera autosave-a
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Provera ovlašćenja
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Čuvanje podataka - KLJUČNA ISPRAVKA OVDE
    if (isset($_POST['google_maps_iframe'])) {
        // Dozvoljeni HTML tagovi za iframe
        $allowed_iframe_tags = array(
            'iframe' => array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'frameborder' => array(),
                'style' => array(),
                'allowfullscreen' => array(),
                'loading' => array()
            )
        );

        update_post_meta(
            $post_id,
            '_google_maps_iframe',
            wp_kses($_POST['google_maps_iframe'], $allowed_iframe_tags) // Koristimo custom dozvoljene tagove
        );
    }
}
add_action('save_post_product', 'google_maps_iframe_save_meta_box'); 
