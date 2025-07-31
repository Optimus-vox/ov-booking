<?php
defined('ABSPATH') || exit;

/**
 * OV Booking Admin Dashboard Customizations
 * - Optimizovani admin experience za Product CPT
 * - Disabluje Gutenberg/Elementor, čisti UI, ubacuje admin menu, widgete i footer
 */

// Loader za logger (ako treba)
if (file_exists(dirname(__DIR__) . '/helpers/logger.php')) {
    require_once dirname(__DIR__) . '/helpers/logger.php';
}

class OVB_Admin_Product_Editor {

    public function __construct() {
        add_action('init', [$this, 'init_editor_customizations'], 20);
        add_action('current_screen', [$this, 'handle_product_screen']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets'], 99); // <--- niži prioritet!
    }

    public function init_editor_customizations() {
        add_filter('use_block_editor_for_post_type', [$this, 'disable_gutenberg_for_products'], 10, 2);
        add_filter('wp_editor_settings', [$this, 'configure_classic_editor'], 10, 2);
        $this->disable_elementor_for_products();
        add_action('add_meta_boxes', [$this, 'remove_unwanted_metaboxes'], 999);
    }

    public function disable_gutenberg_for_products($use_block_editor, $post_type) {
        return ($post_type === 'product') ? false : $use_block_editor;
    }

    public function configure_classic_editor($settings, $editor_id) {
        if (!$this->is_product_edit_screen()) return $settings;
        $settings['tinymce'] = true;
        $settings['quicktags'] = true;
        $settings['media_buttons'] = true;
        $settings['drag_drop_upload'] = true;
        $settings['toolbar1'] = 'bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,link,unlink,|,spellchecker,fullscreen,|,formatselect';
        $settings['toolbar2'] = 'undo,redo,|,pastetext,pasteword,removeformat,|,charmap,|,outdent,indent,|,wp_more,|,wp_adv';
        return $settings;
    }

    private function disable_elementor_for_products() {
        add_filter('elementor/editor/should_load', function($should_load, $post_id) {
            return (get_post_type($post_id) === 'product') ? false : $should_load;
        }, 20, 2);
        add_filter('elementor/utils/is_post_type_allowed', function($allowed, $post_type) {
            return ($post_type === 'product') ? false : $allowed;
        }, 20, 2);
        add_action('init', function() {
            remove_post_type_support('product', 'elementor');
        }, 25);
    }

    public function remove_unwanted_metaboxes() {
        if (!$this->is_product_edit_screen()) return;
        $unwanted_metaboxes = [
            'elementor-editor' => 'normal',
            'elementor-editor-button' => 'side',
            'wpforms-form-selector' => 'side',
            'wpforms-insert-form' => 'side',
            'commentsdiv' => 'normal',
            'trackbacksdiv' => 'normal',
        ];
        foreach ($unwanted_metaboxes as $metabox_id => $context) {
            remove_meta_box($metabox_id, 'product', $context);
        }
    }

    public function handle_product_screen($current_screen) {
        if ($current_screen->post_type !== 'product') return;
        $this->cleanup_elementor_hooks();
        add_action('admin_notices', [$this, 'display_product_admin_notices']);
        if (function_exists('ovb_log')) ovb_log("Product edit screen accessed by user " . get_current_user_id(), 'admin');
    }

    private function cleanup_elementor_hooks() {
        remove_all_actions('elementor/editor/before_enqueue_scripts');
        remove_all_actions('elementor/editor/after_enqueue_scripts');
        remove_all_actions('elementor/editor/enqueue_scripts');
        add_action('admin_enqueue_scripts', function() {
            wp_dequeue_script('elementor-editor');
            wp_dequeue_style('elementor-editor');
            wp_dequeue_script('elementor-admin');
            wp_dequeue_style('elementor-admin');
        }, 999);
    }

    public function enqueue_admin_assets($hook) {
        if (!$this->is_product_edit_screen()) return;

        // Forsiraj učitavanje jQuery na product admin
        wp_enqueue_script('jquery');

        // Dodaj inline style i skriptu
        $this->add_admin_styles();
        $this->add_admin_scripts();
    }

    private function add_admin_styles() {
        ?>
        <style id='ovb-admin-product-styles'>
            .elementor-switch-mode-button, .elementor-editor-button, #elementor-editor,
            .elementor-switch-mode-off, .elementor-switch-mode-on, .elementor-panel, .elementor-switch-mode {
                display: none !important;
            }
            button.wpforms-insert-form-button, #wp-content-media-buttons .wpforms-insert-form-button, .wpforms-insert-form-button {
                display: none !important;
            }
            .elementor-button-spinner, .elementor-loader, #elementor-mode-switcher {
                display: none !important;
            }
            .wp-editor-wrap { border: 1px solid #ddd; border-radius: 4px; background: #fff; }
            .wp-editor-container { border: none; }
            #titlediv input[type='text'] { font-size: 1.4em; padding: 8px 12px; }
            #product_calendar_meta_box .inside { margin: 0; padding: 12px; }
            .ovb-admin-section { background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 4px; padding: 15px; margin: 10px 0; }
            .ovb-admin-section h3 { margin-top: 0; color: #23282d; border-bottom: 2px solid #0073aa; padding-bottom: 5px; }
            #wpseo_meta, #rankmath_metabox, #aioseo-post-general { display: none !important; }
        </style>
        <?php
    }

    private function add_admin_scripts() {
        ?>
        <script id="ovb-admin-product-scripts">
        // Obezbeđuje da se izvrši tek kada je jQuery dostupan
        (function(runWhenReady) {
            if (window.jQuery) {
                runWhenReady(jQuery);
            } else {
                var interval = setInterval(function() {
                    if (window.jQuery) {
                        clearInterval(interval);
                        runWhenReady(jQuery);
                    }
                }, 50);
            }
        })(function($) {
            setInterval(function() {
                $('.elementor-switch-mode-button, .elementor-editor-button').remove();
                $('#elementor-editor, #elementor-switch-mode').remove();
            }, 1000);
            if ($('#titlediv input[type="text"]').length) {
                $('#titlediv input[type="text"]').attr('placeholder', 'Enter apartment/property title...');
            }
            $('input[name="save"], input[name="publish"]').on('click', function(e) {
                if (!$('#title').val().trim()) {
                    alert('Please enter a title for this property before saving.');
                    e.preventDefault();
                    $('#title').focus();
                    return false;
                }
            });
            let editingTime = 0;
            setInterval(function() {
                editingTime += 30;
                if (editingTime >= 1800) {
                    if (window.wp && wp.heartbeat && wp.autosave) wp.autosave.server.triggerSave();
                    editingTime = 0;
                }
            }, 30000);
            if ($('#product_calendar_meta_box').length) {
                $('body').addClass('ovb-booking-product-edit');
            }
        });
        </script>
        <?php
    }

    public function display_product_admin_notices() {
        global $post;
        if (!$post || $post->post_type !== 'product') return;
        $has_calendar = !empty(get_post_meta($post->ID, '_ovb_calendar_data', true));
        if ($has_calendar) {
            $calendar_data = get_post_meta($post->ID, '_ovb_calendar_data', true);
            $booking_count = 0;
            if (is_array($calendar_data)) {
                foreach ($calendar_data as $date_data) {
                    if (isset($date_data['clients']) && is_array($date_data['clients'])) {
                        $booking_count += count($date_data['clients']);
                    }
                }
            }
            printf(
                '<div class="notice notice-info is-dismissible">
                    <p><strong>%s</strong> %s %s</p>
                </div>',
                __('Booking Product:', 'ov-booking'),
                sprintf(
                    _n('This property has %d active reservation.', 'This property has %d active reservations.', $booking_count, 'ov-booking'),
                    $booking_count
                ),
                $booking_count > 0 ? sprintf('<a href="#product_calendar_meta_box">%s</a>', __('View Calendar →', 'ov-booking')) : ''
            );
        }
        $required_meta = [
            '_apartment_additional_info' => __('Apartment Information', 'ov-booking'),
            '_ovb_price_types' => __('Price Settings', 'ov-booking'),
        ];
        $missing_fields = [];
        foreach ($required_meta as $meta_key => $field_name) {
            if (empty(get_post_meta($post->ID, $meta_key, true))) $missing_fields[] = $field_name;
        }
        if (!empty($missing_fields)) {
            printf(
                '<div class="notice notice-warning">
                    <p><strong>%s</strong> %s</p>
                </div>',
                __('Missing Information:', 'ov-booking'),
                sprintf(__('Please complete the following sections: %s', 'ov-booking'), implode(', ', $missing_fields))
            );
        }
    }

    private function is_product_edit_screen() {
        global $pagenow, $post;
        if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'product') return true;
        if ($pagenow === 'post.php' && isset($_GET['post']) && get_post_type($_GET['post']) === 'product') return true;
        if (isset($post) && $post->post_type === 'product') return true;
        return false;
    }
}
new OVB_Admin_Product_Editor();

/**
 * Admin menu: Booking Calendar i Reports
 */
add_action('admin_menu', 'ovb_customize_admin_menu');
function ovb_customize_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=product',
        __('Booking Calendar', 'ov-booking'),
        __('Calendar Overview', 'ov-booking'),
        'manage_woocommerce',
        'ovb-calendar-overview',
        'ovb_render_calendar_overview_page'
    );
    add_submenu_page(
        'edit.php?post_type=product',
        __('Booking Reports', 'ov-booking'),
        __('Reports', 'ov-booking'),
        'manage_woocommerce',
        'ovb-booking-reports',
        'ovb_render_booking_reports_page'
    );
}
function ovb_render_calendar_overview_page() {
    if (!current_user_can('manage_woocommerce')) wp_die(__('You do not have sufficient permissions to access this page.'));
    ?>
    <div class="wrap"><h1><?php _e('Booking Calendar Overview', 'ov-booking'); ?></h1>
    <div id="ovb-calendar-overview"><p><?php _e('Loading calendar overview...', 'ov-booking'); ?></p></div></div>
    <?php
}
function ovb_render_booking_reports_page() {
    if (!current_user_can('manage_woocommerce')) wp_die(__('You do not have sufficient permissions to access this page.'));
    ?>
    <div class="wrap"><h1><?php _e('Booking Reports', 'ov-booking'); ?></h1>
    <div id="ovb-booking-reports"><p><?php _e('Loading booking reports...', 'ov-booking'); ?></p></div></div>
    <?php
}

/**
 * Dashboard widget: Booking stats
 */
add_action('wp_dashboard_setup', 'ovb_add_dashboard_widgets');
function ovb_add_dashboard_widgets() {
    if (current_user_can('manage_woocommerce')) {
        wp_add_dashboard_widget(
            'ovb_booking_stats',
            __('Booking Statistics', 'ov-booking'),
            'ovb_render_booking_stats_widget'
        );
    }
}
function ovb_render_booking_stats_widget() {
    $stats = ovb_get_booking_statistics();
    ?>
    <div class="ovb-dashboard-stats">
        <div class="ovb-stat-item"><span class="ovb-stat-number"><?php echo esc_html($stats['total_bookings']); ?></span><span class="ovb-stat-label"><?php _e('Total Bookings', 'ov-booking'); ?></span></div>
        <div class="ovb-stat-item"><span class="ovb-stat-number"><?php echo esc_html($stats['this_month']); ?></span><span class="ovb-stat-label"><?php _e('This Month', 'ov-booking'); ?></span></div>
        <div class="ovb-stat-item"><span class="ovb-stat-number"><?php echo esc_html($stats['active_properties']); ?></span><span class="ovb-stat-label"><?php _e('Active Properties', 'ov-booking'); ?></span></div>
    </div>
    <style>
    .ovb-dashboard-stats { display: flex; justify-content: space-between; margin-top: 10px; }
    .ovb-stat-item { text-align: center; flex: 1; }
    .ovb-stat-number { display: block; font-size: 2em; font-weight: bold; color: #0073aa; }
    .ovb-stat-label { font-size: 0.9em; color: #666; }
    </style>
    <?php
}
function ovb_get_booking_statistics() {
    $stats = ['total_bookings' => 0, 'this_month' => 0, 'active_properties' => 0];
    $products = get_posts([
        'post_type' => 'product', 'posts_per_page' => -1,
        'meta_query' => [['key' => '_ovb_calendar_data', 'compare' => 'EXISTS']]
    ]);
    $stats['active_properties'] = count($products);
    $current_month = date('Y-m');
    foreach ($products as $product) {
        $calendar_data = get_post_meta($product->ID, '_ovb_calendar_data', true);
        if (is_array($calendar_data)) {
            foreach ($calendar_data as $date => $data) {
                if (isset($data['clients']) && is_array($data['clients'])) {
                    $stats['total_bookings'] += count($data['clients']);
                    if (strpos($date, $current_month) === 0) {
                        $stats['this_month'] += count($data['clients']);
                    }
                }
            }
        }
    }
    return $stats;
}

/**
 * Custom admin footer on product pages
 */
add_filter('admin_footer_text', 'ovb_custom_admin_footer');
function ovb_custom_admin_footer($footer_text) {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'product') {
        return sprintf(__('Thank you for using %s for your booking management.', 'ov-booking'), '<strong>OV Booking</strong>');
    }
    return $footer_text;
}
