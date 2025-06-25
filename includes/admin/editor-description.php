<?php
defined('ABSPATH') || exit;
require_once dirname(__DIR__) . '/helpers/logger.php';


// function ov_dodaj_custom_editor_metabox() {
//     add_meta_box(
//         'ov_custom_text_editor',
//         'Product description editor',
//         'ov_prikazi_custom_editor',
//         ['product'],
//         'normal',
//         'default'
//     );
// }
// add_action('add_meta_boxes', 'ov_dodaj_custom_editor_metabox');

function ov_prikazi_custom_editor($post) {
    wp_nonce_field('ov_sacuvaj_custom_editor', 'ov_custom_editor_nonce');
    $vrednost = get_post_meta($post->ID, '_ov_custom_editor', true);
    wp_editor($vrednost, 'ov_custom_editor', [
        'textarea_name' => 'ov_custom_editor',
        'textarea_rows' => 6,
    ]);
}

function ov_sacuvaj_custom_editor($post_id) {
    if (!isset($_POST['ov_custom_editor_nonce']) || !wp_verify_nonce($_POST['ov_custom_editor_nonce'], 'ov_sacuvaj_custom_editor')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['ov_custom_editor'])) {
        update_post_meta($post_id, '_ov_custom_editor', wp_kses_post($_POST['ov_custom_editor']));
    }
}
add_action('save_post', 'ov_sacuvaj_custom_editor');
