<?php
defined('ABSPATH') || exit;

class OVB_OAuth_Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Google OAuth Settings', 'ov-booking'),
            __('Booking OAuth', 'ov-booking'),
            'manage_options',
            'ovb-oauth-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('ovb_oauth_settings_group', 'ovb_google_client_id');
        register_setting('ovb_oauth_settings_group', 'ovb_google_client_secret');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Google OAuth podešavanja', 'ov-booking'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('ovb_oauth_settings_group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Google Client ID', 'ov-booking'); ?></th>
                        <td><input type="text" name="ovb_google_client_id" value="<?php echo esc_attr(get_option('ovb_google_client_id')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Google Client Secret', 'ov-booking'); ?></th>
                        <td><input type="text" name="ovb_google_client_secret" value="<?php echo esc_attr(get_option('ovb_google_client_secret')); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(__('Sačuvaj podešavanja', 'ov-booking')); ?>
            </form>
        </div>
        <?php
    }
}

new OVB_OAuth_Settings();
