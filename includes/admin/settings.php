<?php
defined('ABSPATH') || exit;
require_once dirname(__DIR__) . '/helpers/logger.php';


// Dodaj admin meni
add_action('admin_menu', function() {
    add_menu_page(
        'OV Booking Settings',
        'Booking Settings',
        'manage_options',
        'ov-booking-settings',
        'ovb_booking_render_settings_page',
        OVB_BOOKING_URL . 'assets/ov-icon-dashboard.svg'
    );
});

//temp
add_action('admin_init', function () {
    if (current_user_can('manage_options') && isset($_POST['ovb_reset_wc_pages'])) {
        ovb_reset_woocommerce_pages();
        add_action('admin_notices', function () {
            echo '<div class="notice notice-success"><p>✅ WooCommerce stranice su resetovane.</p></div>';
        });
    }
});

// Render forma
function ovb_booking_render_settings_page() {
    $mode          = get_option('ovb_booking_display_mode', 'shortcode');
    $contact_email = get_option('ovb_contact_email', '');
    ?>
    <div class="wrap">
        <h1>OV Booking Display Settings</h1>
        <!-- <p class="description">Choose how the product page should be rendered by this plugin.</p> -->
        <form method="post" action="options.php">
            <?php settings_fields('ovb_booking_settings_group'); ?>
            <?php do_settings_sections('ov-booking-settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">Display mode</th>
                    <td>
                        <label><input type="radio" name="ovb_booking_display_mode" value="shortcode" <?php checked($mode, 'shortcode'); ?> /> Shortcode (default)</label><br>
                        <label><input type="radio" name="ovb_booking_display_mode" value="template" <?php checked($mode, 'template'); ?> /> Override single-product template</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Contact Email</th>
                    <td>
                        <input type="email" name="ovb_contact_email" class="regular-text"
                            value="<?php echo esc_attr($contact_email); ?>" placeholder="you@example.com" />
                        <p class="description" style="font-size: 12px"><?php esc_html_e('Enter contact email address(it will be used in emails).', 'ov-booking'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Google OAuth', 'ov-booking'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Google Client ID', 'ov-booking'); ?></th>
                    <td>
                        <input type="text" name="ovb_google_client_id" class="regular-text"
                            value="<?php echo esc_attr(get_option('ovb_google_client_id', '')); ?>" />
                        <?php
                        $client_id_ok     = get_option('ovb_google_client_id');
                        $client_secret_ok = get_option('ovb_google_client_secret');
                        if (!empty($client_id_ok) && !empty($client_secret_ok)) {
                            echo '<p style="color:green; margin-top:4px;">✅ ' . esc_html__('Google OAuth is configured', 'ov-booking') . '</p>';
                        } else {
                            echo '<p style="color:red; margin-top:4px;">❌ ' . esc_html__('Google OAuth isn\'t configured', 'ov-booking') . '</p>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Google Client Secret', 'ov-booking'); ?></th>
                    <td>
                        <input type="text" name="ovb_google_client_secret" class="regular-text"
                            value="<?php echo esc_attr(get_option('ovb_google_client_secret', '')); ?>" />
                    </td>
                </tr>
            </table>

            <!-- reset setting button -->
            <table class="form-table">
                <tr>
                    <th scope="row">Reset settings</th>
                    <td>
                        <?php submit_button('Reset WooCommerce pages', 'delete', 'ovb_reset_wc_pages'); ?>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Registruj opcije iz plugin settings
add_action('admin_init', function() {
    register_setting('ovb_booking_settings_group', 'ovb_booking_display_mode');
    register_setting('ovb_booking_settings_group', 'ovb_google_client_id');
    register_setting('ovb_booking_settings_group', 'ovb_google_client_secret');
    register_setting(
        'ovb_booking_settings_group',
        'ovb_contact_email',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => '',
        ]
    );
});