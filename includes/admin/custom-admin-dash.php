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
         // Ovo Fixuj
        // add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets'], 99); 
                // ← OVDE dodajemo samo PHP hookove za Title polje:
        add_filter('enter_title_here', [$this, 'change_product_title_placeholder']);
        // add_action('admin_head', [$this, 'hide_product_title_label']);
    }

    public function init_editor_customizations() {
        add_filter('use_block_editor_for_post_type', [$this, 'disable_gutenberg_for_products'], 10, 2);
        add_filter('wp_editor_settings', [$this, 'configure_classic_editor'], 10, 2);
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

    // TODO URADI PROVERU AKO IMA WPFORMS AKO NEMA IZBACI
    public function remove_unwanted_metaboxes() {
        if (!$this->is_product_edit_screen()) return;
        $unwanted_metaboxes = [
            'wpforms-form-selector' => 'side',
            'wpforms-insert-form' => 'side',
            'commentsdiv' => 'normal',
            'trackbacksdiv' => 'normal',
        ];
        foreach ($unwanted_metaboxes as $metabox_id => $context) {
            remove_meta_box($metabox_id, 'product', $context);
        }
    }
        /**
     * 1) Menja placeholder u Title inputu
     */
    public function change_product_title_placeholder($placeholder) {
        if ($this->is_product_edit_screen()) {
            return __('Enter apartment/property title', 'ov-booking');
        }
        return $placeholder;
    }

    /**
     * 2) Sakriva default labelu iznad Title inputa
     */
    // public function hide_product_title_label() {
    //     if ($this->is_product_edit_screen()) {
    //         // echo '<style>#title-prompt-text { display: none !important; }</style>';
    //     }
    // }
    // TODO PREBACI U NOTICES
    public function handle_product_screen($current_screen) {
        // if ($current_screen->post_type !== 'product') return;
        // add_action('admin_notices', [$this, 'display_product_admin_notices']);
        // if (function_exists('ovb_log')) ovb_log("Product edit screen accessed by user " . get_current_user_id(), 'admin');
          // mora biti product CPT
    if ( $current_screen->post_type !== 'product' ) {
        return;
    }

    // dozvoljeno samo na 'post.php' (Edit) ili 'post-new.php' (Add New)
    if ( ! in_array( $current_screen->base, [ 'post', 'post-new' ], true ) ) {
        return;
    }

    // sada možemo da registrujemo notice
    add_action( 'admin_notices', [ $this, 'display_product_admin_notices' ] );
    }
    

    public function enqueue_admin_assets($hook) {
        if (!$this->is_product_edit_screen()) return;

        // Forsiraj učitavanje jQuery na product admin
        wp_enqueue_script('jquery');

        // Dodaj inline style i skriptu - POPRAVLJEN DEO!
        // $this->add_admin_styles();
        // $this->add_admin_scripts();
    }

    // TODO OCISTI STYLES U PRODUKCIJI
    private function add_admin_styles() {
        ?>
        <style id='ovb-admin-product-styles'>
            /* Osnovni admin styles */
            .wp-editor-wrap { 
                border: 1px solid #ddd; 
                border-radius: 4px; 
                background: #fff; 
            }
            .wp-editor-container { 
                border: none; 
            }
            #titlediv input[type='text'] { 
                font-size: 1.4em; 
                padding: 8px 12px; 
            }
            
            /* KALENDAR STYLES - KRITIČNO ZA ADMIN CALENDAR! */
            #product_calendar_meta_box .inside { 
                margin: 0; 
                padding: 12px; 
            }
            
            /* OVB Admin sections */
            .ovb-admin-section { 
                background: #f9f9f9; 
                border: 1px solid #e5e5e5; 
                border-radius: 4px; 
                padding: 15px; 
                margin: 10px 0; 
            }
            .ovb-admin-section h3 { 
                margin-top: 0; 
                color: #23282d; 
                border-bottom: 2px solid #0073aa; 
                padding-bottom: 5px; 
            }
            
            /* Hide SEO metaboxes */
            #wpseo_meta, #rankmath_metabox, #aioseo-post-general { 
                display: none !important; 
            }
            
            /* CALENDAR SPECIFIC STYLES */
            .ovb-calendar-container {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                padding: 20px;
                margin: 20px 0;
            }
            
            .ovb-calendar-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 1px solid #e1e1e1;
            }
            
            .ovb-calendar-nav {
                display: flex;
                gap: 10px;
            }
            
            .ovb-calendar-nav button {
                background: #0073aa;
                color: white;
                border: none;
                padding: 8px 15px;
                border-radius: 3px;
                cursor: pointer;
            }
            
            .ovb-calendar-nav button:hover {
                background: #005a87;
            }
            
            .ovb-calendar-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 1px;
                background: #e1e1e1;
                border: 1px solid #e1e1e1;
            }
            
            .ovb-calendar-day {
                background: #fff;
                min-height: 80px;
                padding: 8px;
                border: 1px solid transparent;
                position: relative;
            }
            
            .ovb-calendar-day:hover {
                background: #f0f6fc;
            }
            
            .ovb-calendar-day.has-booking {
                background: #e7f3ff;
                border-color: #0073aa;
            }
            
            .ovb-calendar-day-number {
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .ovb-booking-indicator {
                background: #0073aa;
                color: white;
                font-size: 11px;
                padding: 2px 6px;
                border-radius: 10px;
                display: inline-block;
                margin: 2px 0;
            }
            
            /* Calendar Settings Panel */
            .ovb-calendar-settings {
                background: #f9f9f9;
                border: 1px solid #e1e1e1;
                padding: 15px;
                margin-top: 20px;
                border-radius: 4px;
            }
            
            .ovb-calendar-settings h4 {
                margin-top: 0;
                color: #23282d;
            }
            
            .ovb-settings-row {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 15px;
            }
            
            .ovb-settings-row label {
                font-weight: 600;
                min-width: 120px;
            }
            
            .ovb-settings-row select,
            .ovb-settings-row input {
                flex: 1;
                max-width: 200px;
            }
        </style>
        <?php
    }
    // TODO OCISTI SKRIPTE
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
            
            // Title placeholder
            if ($('#titlediv input[type="text"]').length) {
                $('#titlediv input[type="text"]').attr('placeholder', 'Enter apartment/property title...');
            }
            
            // Title validation
            $('input[name="save"], input[name="publish"]').on('click', function(e) {
                if (!$('#title').val().trim()) {
                    alert('Please enter a title for this property before saving.');
                    e.preventDefault();
                    $('#title').focus();
                    return false;
                }
            });
            
            // Auto-save functionality
            let editingTime = 0;
            setInterval(function() {
                editingTime += 30;
                if (editingTime >= 1800) { // 30 minutes
                    if (window.wp && wp.heartbeat && wp.autosave) {
                        wp.autosave.server.triggerSave();
                    }
                    editingTime = 0;
                }
            }, 30000);
            
            // Calendar detection and body class
            if ($('#product_calendar_meta_box').length) {
                $('body').addClass('ovb-booking-product-edit');
                // console.log('OVB: Product calendar metabox detected');
            }
            
            // Calendar functionality enhancement
            $(document).on('click', '.ovb-calendar-day', function() {
                var $day = $(this);
                var date = $day.data('date');
                
                if (date) {
                    // Toggle day selection
                    $day.toggleClass('selected');
                    // console.log('OVB: Calendar day selected:', date);
                }
            });
            
            // Calendar navigation
            $(document).on('click', '.ovb-calendar-nav button', function(e) {
                e.preventDefault();
                var action = $(this).data('action');
                
                if (action === 'prev' || action === 'next') {
                    // Handle calendar navigation
                    // console.log('OVB: Calendar navigation:', action);
                    // This would trigger AJAX call to reload calendar
                }
            });
            
            // Settings panel toggle
            $(document).on('click', '.ovb-calendar-settings-toggle', function(e) {
                e.preventDefault();
                $('.ovb-calendar-settings').slideToggle();
            });
            
            // console.log('OVB Admin: Product editor scripts loaded successfully');
        });
        </script>
        <?php
    }

public function display_product_admin_notices() {
    global $post;
    if ( ! $post || 'product' !== $post->post_type ) {
        return;
    }

    $calendar_data = get_post_meta( $post->ID, '_ovb_calendar_data', true );
    if ( empty( $calendar_data ) || ! is_array( $calendar_data ) ) {
        return;
    }

    // Broj dana sa statusom booked
    $booking_days = 0;
    foreach ( $calendar_data as $date => $data ) {
        if ( isset( $data['status'] ) && 'booked' === $data['status'] ) {
            $booking_days++;
        }
    }

    // Ako nema nijedan booked dan, ne prikazujemo notice
    if ( 0 === $booking_days ) {
        return;
    }

    printf(
        '<div class="notice notice-info is-dismissible"><p><strong>%s</strong> %s %s</p></div>',
        esc_html__( 'Booking Info:', 'ov-booking' ),
        esc_html( sprintf(
            /* translators: 1: number of days */
            _n( 'This property has %1$d day with reservations.', 'This property has %1$d days with reservations.', $booking_days, 'ov-booking' ),
            $booking_days
        ) ),
        sprintf(
            '<a href="#product_calendar_meta_box">%s</a>',
            esc_html__( 'View Calendar →', 'ov-booking' )
        )
    );
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
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Booking Calendar Overview', 'ov-booking'); ?></h1>
        <div id="ovb-calendar-overview">
            <p><?php _e('Loading calendar overview...', 'ov-booking'); ?></p>
        </div>
    </div>
    <?php
}

function ovb_render_booking_reports_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Booking Reports', 'ov-booking'); ?></h1>
        <div id="ovb-booking-reports">
            <p><?php _e('Loading booking reports...', 'ov-booking'); ?></p>
        </div>
    </div>
    <?php
}

/**
 * Dashboard widget: Booking stats
 */
//TODO ovo obrisi pre produkcije
/// 1) Uverite se da widget ostaje na vrhu
remove_action( 'wp_dashboard_setup', 'ovb_add_dashboard_widgets' );
add_action( 'wp_dashboard_setup', 'ovb_add_dashboard_widgets', 1 );

/**
 * Dashboard widget: Booking stats
 */
function ovb_add_dashboard_widgets() {
    if ( current_user_can( 'manage_woocommerce' ) ) {
        wp_add_dashboard_widget(
            'ovb_booking_stats',
            __( 'Booking Statistics', 'ov-booking' ),
            'ovb_render_booking_stats_widget'
        );
    }
}

/**
 * Prikaz widget-a
 */
function ovb_render_booking_stats_widget() {
    $stats = ovb_get_booking_statistics();

    // Fallback vrednosti ako slučajno ne postoje ključevi
    $total  = isset($stats['total_booked_days'])      ? $stats['total_booked_days']      : 0;
    $month  = isset($stats['booked_days_this_month']) ? $stats['booked_days_this_month'] : 0;
    $active = isset($stats['active_properties'])      ? $stats['active_properties']      : 0;
    ?>
    <style>
    .ovb-dashboard-stats { display: flex; justify-content: space-between; margin-top: 10px; }
    .ovb-stat-item    { text-align: center; flex: 1; }
    .ovb-stat-number  { display: block; font-size: 2em; font-weight: bold; color: #0073aa; }
    .ovb-stat-label   { font-size: 0.9em; color: #666; }
    </style>
    <div class="ovb-dashboard-stats">
        <div class="ovb-stat-item">
            <span class="ovb-stat-number"><?php echo esc_html( $total ); ?></span>
            <span class="ovb-stat-label"><?php _e( 'Total Booked Days', 'ov-booking' ); ?></span>
        </div>
        <div class="ovb-stat-item">
            <span class="ovb-stat-number"><?php echo esc_html( $month ); ?></span>
            <span class="ovb-stat-label"><?php _e( 'Booked Days This Month', 'ov-booking' ); ?></span>
        </div>
        <div class="ovb-stat-item">
            <span class="ovb-stat-number"><?php echo esc_html( $active ); ?></span>
            <span class="ovb-stat-label"><?php _e( 'Active Properties', 'ov-booking' ); ?></span>
        </div>
    </div>
    <?php
}

/**
 * Prikupljanje statistike
 */
/**
 * Prikupljanje statistike (broj dana sa rezervacijama, bez checkout dana)
 */
function ovb_get_booking_statistics() {
    $stats = [
        'total_booked_days'      => 0,
        'booked_days_this_month' => 0,
        'active_properties'      => 0,
    ];

    $products = get_posts( [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'meta_query'     => [
            [ 'key' => '_ovb_calendar_data', 'compare' => 'EXISTS' ],
        ],
    ] );
    $stats['active_properties'] = count( $products );
    $current_month             = date( 'Y-m' ); // npr. "2025-08"

    foreach ( $products as $product ) {
        $calendar_data = get_post_meta( $product->ID, '_ovb_calendar_data', true );
        if ( ! is_array( $calendar_data ) ) {
            continue;
        }
        foreach ( $calendar_data as $date => $data ) {
            // mora postojati status i mora biti tačno 'booked'
            if ( empty( $data['status'] ) || $data['status'] !== 'booked' ) {
                continue;
            }
            $stats['total_booked_days']++;
            if ( 0 === strpos( $date, $current_month ) ) {
                $stats['booked_days_this_month']++;
            }
        }
    }

    return $stats;
}

/**
 * 2) Prikaz u "At a Glance" sekciji (glavni pregled) – vraća array, ne string!
 */
add_filter( 'dashboard_glance_items', 'ovb_add_booking_glance_items' );
function ovb_add_booking_glance_items( $items ) {
    if ( ! is_array( $items ) ) {
        $items = (array) $items;
    }
    $stats = ovb_get_booking_statistics();
    $month = isset( $stats['booked_days_this_month'] ) ? $stats['booked_days_this_month'] : 0;
    $total = isset( $stats['total_booked_days'] )      ? $stats['total_booked_days']      : 0;

    $items[] = sprintf(
        '<li class="ovb-booked-days">%s: <strong>%d</strong></li>',
        esc_html__( 'Booked Days This Month', 'ov-booking' ),
        intval( $month )
    );
    $items[] = sprintf(
        '<li class="ovb-total-bookings">%s: <strong>%d</strong></li>',
        esc_html__( 'Total Booked Days', 'ov-booking' ),
        intval( $total )
    );
    return $items;
}


//TODO - SREDI OVO
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