<?php
defined('ABSPATH') || exit;

/**
 * =======================================
 * ELEMENTOR KONTROL ZA OV BOOKING STRANICE
 * =======================================
 */

// Inicijalizacija Elementor kontrole
add_action('init', 'ovb_init_elementor_control', 1);
function ovb_init_elementor_control() {
    if (!class_exists('\Elementor\Plugin')) {
        return;
    }
    
    // Dodaj hook za kontrolu Elementor skriptova/stilova
    add_action('wp', 'ovb_control_elementor_assets', 5);
    add_action('elementor/init', 'ovb_disable_elementor_for_products', 20);
}

/**
 * ONEMOGUĆI ELEMENTOR EDITOR ZA PRODUCT POST TYPE
 */
function ovb_disable_elementor_for_products() {
    // Ukloni product iz supported post types
    add_filter('elementor/editor/active_post_types', function ($types) {
        return array_diff($types, ['product']);
    });

    // Onemogući post type support
    add_filter('elementor/utils/is_post_type_support', function ($supports, $post_type) {
        return $post_type === 'product' ? false : $supports;
    }, 10, 2);
}

/**
 * GLAVNA KONTROLA ELEMENTOR ASSETS-a
 */
function ovb_control_elementor_assets() {
    if (!class_exists('\Elementor\Plugin')) {
        return;
    }

    $should_disable_elementor = ovb_should_disable_elementor();
    
    if ($should_disable_elementor) {
        ovb_disable_elementor_completely();
    } else {
        // Na shop stranici samo ograniči duplikate
        if (function_exists('is_shop') && is_shop()) {
            ovb_fix_shop_duplicates();
        }
    }
}

/**
 * PROVERA DA LI TREBA ONEMOGUĆITI ELEMENTOR
 */
function ovb_should_disable_elementor() {
    // Onemogući na ovim stranicama
    $disable_pages = [
        'is_singular' => ['product'],
        'functions' => ['is_cart', 'is_checkout', 'is_account_page', 'is_order_received_page']
    ];
    
    // Proveri single post types
    foreach ($disable_pages['is_singular'] as $post_type) {
        if (is_singular($post_type)) {
            return true;
        }
    }
    
    // Proveri WooCommerce stranice
    foreach ($disable_pages['functions'] as $func) {
        if (function_exists($func) && call_user_func($func)) {
            return true;
        }
    }
    
    return false;
}

/**
 * KOMPLETNO ONEMOGUĆAVANJE ELEMENTOR-a
 */
function ovb_disable_elementor_completely() {
    $elementor = \Elementor\Plugin::instance();
    
    // Ukloni theme locations
    add_filter('elementor/theme/do_location', function ($do, $location) {
        $restricted_locations = ['single', 'single-product', 'archive', 'checkout', 'cart'];
        return in_array($location, $restricted_locations, true) ? false : $do;
    }, PHP_INT_MAX, 2);
    
    // Onemogući frontend assets
    remove_action('wp_enqueue_scripts', [$elementor->frontend, 'enqueue_scripts'], 20);
    remove_action('wp_print_styles', [$elementor->frontend, 'enqueue_styles'], 10);
    remove_action('wp_head', [$elementor->frontend, 'print_google_fonts']);
    
    // Ukloni dodatne Elementor hooks
    remove_action('wp_enqueue_scripts', [$elementor->frontend, 'enqueue_frontend_scripts']);
    remove_action('wp_footer', [$elementor->frontend, 'wp_footer']);
    
    // Onemogući Elementor widgets
    add_filter('elementor/widgets/is_widget_supported', '__return_false');
    
    // Spreci učitavanje Elementor CSS/JS fajlova
    add_action('wp_print_styles', 'ovb_remove_elementor_styles', 100);
    add_action('wp_print_scripts', 'ovb_remove_elementor_scripts', 100);
    
    // Onemogući lazy loading conflicts
    remove_action('wp_head', [$elementor->frontend, 'print_head_attributes']);
    
    // Ukloni Elementor body classes
    add_filter('body_class', function($classes) {
        return array_filter($classes, function($class) {
            return strpos($class, 'elementor') === false;
        });
    }, 999);
}

/**
 * UKLANJANJE ELEMENTOR STILOVA
 */
function ovb_remove_elementor_styles() {
    $elementor_handles = [
        'elementor-frontend',
        'elementor-post-*',
        'elementor-global',
        'elementor-icons',
        'elementor-animations',
        'elementor-lazyload'
    ];
    
    global $wp_styles;
    if (!$wp_styles) return;
    
    foreach ($wp_styles->registered as $handle => $style) {
        foreach ($elementor_handles as $pattern) {
            if (fnmatch($pattern, $handle)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
                break;
            }
        }
    }
}

/**
 * UKLANJANJE ELEMENTOR SKRIPTOVA
 */
function ovb_remove_elementor_scripts() {
    $elementor_handles = [
        'elementor-frontend',
        'elementor-frontend-modules',
        'elementor-waypoints',
        'elementor-core-js',
        'elementor-lazyload'
    ];
    
    global $wp_scripts;
    if (!$wp_scripts) return;
    
    foreach ($wp_scripts->registered as $handle => $script) {
        foreach ($elementor_handles as $pattern) {
            if (fnmatch($pattern, $handle)) {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
                break;
            }
        }
    }
}

/**
 * FIXOVANJE DUPLIKATA NA SHOP STRANICI
 */
function ovb_fix_shop_duplicates() {
    // Ograniči Elementor loop samo na glavnu loop
    add_action('woocommerce_before_shop_loop', function() {
        if (class_exists('\Elementor\Plugin')) {
            remove_action('woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10);
            remove_action('woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10);
            remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5);
        }
    }, 5);
    
    // Dequeue duplicated scripts on shop
    add_action('wp_enqueue_scripts', function() {
        if (wp_script_is('elementor-frontend-modules', 'enqueued')) {
            wp_dequeue_script('elementor-frontend-modules');
        }
    }, 999);
}

/**
 * SPREČAVANJE LAZY LOAD KONFLIKATA
 */
add_action('wp_head', 'ovb_prevent_lazyload_conflicts', 1);
function ovb_prevent_lazyload_conflicts() {
    if (ovb_should_disable_elementor()) {
        ?>
        <script>
        // Spreci duplikate lazy load observer-a
        if (typeof window.lazyloadRunObserver !== 'undefined') {
            delete window.lazyloadRunObserver;
        }
       if (typeof window.elementorFrontendConfig !== 'undefined') {
            window.ovbOriginalElementorConfig = window.elementorFrontendConfig;
            delete window.elementorFrontendConfig;
        }
        </script>
        <?php
    }
}

/**
 * ČIŠĆENJE ELEMENTOR META PODATAKA SA RESTRICTED STRANICA
 */
add_action('wp', function() {
    if (ovb_should_disable_elementor() && is_singular()) {
        global $post;
        if ($post) {
            // Temporary ukloni Elementor meta da se stranica ne renderuje kroz Elementor
            add_filter('get_post_metadata', function($value, $object_id, $meta_key) use ($post) {
                if ($object_id === $post->ID && $meta_key === '_elementor_edit_mode') {
                    return false;
                }
                return $value;
            }, 10, 3);
        }
    }
}, 20);

/**
 * EMERGENCY CLEANUP - ukloni sve Elementor hooks ako su se nekako učitali
 */
add_action('wp_loaded', function() {
    if (ovb_should_disable_elementor()) {
        // Ukloni sve Elementor actions/filters
        global $wp_filter;
        
        $elementor_hooks = [
            'elementor/frontend/before_render',
            'elementor/frontend/after_render',
            'elementor/widget/render_content',
            'elementor/element/before_parse_css'
        ];
        
        foreach ($elementor_hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                unset($wp_filter[$hook]);
            }
        }
    }
}, 999);

/**
 * DEBUGGING HELPER - ukloni u produkciji
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function() {
        if (ovb_should_disable_elementor()) {
            echo '<!-- OVB: Elementor disabled for this page -->';
        }
    });
}