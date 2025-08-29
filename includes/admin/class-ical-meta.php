<?php
defined('ABSPATH') || exit;
class OVB_iCal_Meta {
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_product', [__CLASS__, 'save_meta'], 10, 2);

    }

    public static function add_meta_box() {
        add_meta_box(
            'ovb-ical-urls',
            __('iCal Import URLs', 'ov-booking'),
            [__CLASS__, 'render_meta_box'],
            'product',
            'side'
        );
    }

    public static function render_meta_box($post) {
        wp_nonce_field('ovb_ical_meta', 'ovb_ical_meta_nonce');
        $data = get_post_meta($post->ID, '_ovb_ical_urls', true) ?: '';
        echo '<p>' . esc_html__('Insert external iCal feed URLs, one per line:', 'ov-booking') . '</p>';
        echo '<textarea style="width:100%;" rows="5" name="ovb_ical_urls">' . esc_textarea($data) . '</textarea>';
    }

    public static function save_meta($post_id) {
        if ('product' !== get_post_type($post_id)) return;
        
        if (!isset($_POST['ovb_ical_meta_nonce']) || !wp_verify_nonce($_POST['ovb_ical_meta_nonce'], 'ovb_ical_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if ('product' !== get_post_type($post_id)) {
            return;
        }
        $urls = isset($_POST['ovb_ical_urls']) ? sanitize_textarea_field($_POST['ovb_ical_urls']) : '';
        update_post_meta($post_id, '_ovb_ical_urls', $urls);
    }
}