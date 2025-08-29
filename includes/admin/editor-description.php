<?php
defined('ABSPATH') || exit;
require_once dirname(__DIR__) . '/helpers/logger.php';

function ovb_prikazi_custom_editor($post) {
    wp_nonce_field('ovb_sacuvaj_custom_editor', 'ovb_custom_editor_nonce');
    $vrednost = get_post_meta($post->ID, '_ovb_custom_editor', true);
    wp_editor($vrednost, 'ovb_custom_editor', [
        'textarea_name' => 'ovb_custom_editor',
        'textarea_rows' => 6,
    ]);
}

function ovb_sacuvaj_custom_editor($post_id) {
    if (!isset($_POST['ovb_custom_editor_nonce']) || !wp_verify_nonce($_POST['ovb_custom_editor_nonce'], 'ovb_sacuvaj_custom_editor')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['ovb_custom_editor'])) {
        update_post_meta($post_id, '_ovb_custom_editor', wp_kses_post($_POST['ovb_custom_editor']));
    }
}
add_action('save_post', 'ovb_sacuvaj_custom_editor');