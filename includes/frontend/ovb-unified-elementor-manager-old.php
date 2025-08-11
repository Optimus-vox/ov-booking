<?php
defined('ABSPATH') || exit;

/**
 * ===============================================
 *  OV BOOKING UNIFIED ELEMENTOR MANAGER
 * ===============================================
 * 
 * Kompletno rešenje za disable Elementor-a na:
 * - Single Product stranicama
 * - Cart stranici
 * - Checkout stranici  
 * - My Account stranici
 * - Thank You stranici
 * - Admin Product editor
 * 
 * + Konflikt resolution i performance optimizacije
 */

class OVB_Elementor_Manager {
    
    private static $instance = null;
    private $conflicts_resolved = false;
    private $disabled_pages = [];
    private $admin_disabled = false;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp', [$this, 'detect_pages'], 1);
        add_action('elementor/init', [$this, 'disable_elementor_admin'], 20);
        add_action('init', [$this, 'remove_product_elementor_support'], 25);
        add_action('wp', [$this, 'disable_elementor_frontend'], 5);
        add_action('wp', [$this, 'handle_shop_page'], 10);
        add_action('wp', [$this, 'cleanup_elementor_meta'], 20);
        add_action('wp_loaded', [$this, 'emergency_cleanup'], 999);
    }
    
    public function detect_pages() {
        $this->disabled_pages = [
            'product' => is_singular('product'),
            'cart' => (function_exists('is_cart') && is_cart()),
            'checkout' => (function_exists('is_checkout') && is_checkout()),
            'account' => (function_exists('is_account_page') && is_account_page()),
            'thank_you' => (function_exists('is_order_received_page') && is_order_received_page())
        ];
    }
    
    public function should_disable_elementor() {
        return in_array(true, $this->disabled_pages, true);
    }
    
    private function get_current_page_type() {
        foreach ($this->disabled_pages as $type => $active) {
            if ($active) return $type;
        }
        return false;
    }
    
    public function disable_elementor_admin() {
        if ($this->admin_disabled) return;
        
        add_filter('elementor/editor/active_post_types', function ($types) {
            return array_diff($types, ['product']);
        });
        
        add_filter('elementor/utils/is_post_type_support', function ($supports, $post_type) {
            return $post_type === 'product' ? false : $supports;
        }, 10, 2);
        
        add_filter('elementor/editor/should_load', function($should_load, $post_id) {
            return (get_post_type($post_id) === 'product') ? false : $should_load;
        }, 20, 2);
        
        $this->admin_disabled = true;
    }
    
    public function remove_product_elementor_support() {
        remove_post_type_support('product', 'elementor');
    }
    
    public function disable_elementor_frontend() {
        if (!$this->should_disable_elementor()) {
            return;
        }
        
        $this->remove_elementor_assets();
        $this->prevent_elementor_rendering();
        $this->resolve_conflicts();
    }
    
    private function remove_elementor_assets() {
        $elementor = \Elementor\Plugin::instance();
        
        add_filter('elementor/theme/do_location', function ($do, $location) {
            $restricted = ['single', 'single-product', 'archive', 'checkout', 'cart'];
            return in_array($location, $restricted, true) ? false : $do;
        }, PHP_INT_MAX, 2);
        
        remove_action('wp_enqueue_scripts', [$elementor->frontend, 'enqueue_scripts'], 20);
        remove_action('wp_print_styles', [$elementor->frontend, 'enqueue_styles'], 10);
        remove_action('wp_head', [$elementor->frontend, 'print_google_fonts']);
        remove_action('wp_enqueue_scripts', [$elementor->frontend, 'enqueue_frontend_scripts']);
        remove_action('wp_footer', [$elementor->frontend, 'wp_footer']);
        remove_action('wp_head', [$elementor->frontend, 'print_head_attributes']);
        
        add_filter('elementor/widgets/is_widget_supported', '__return_false');
        
        add_filter('body_class', function($classes) {
            return array_filter($classes, function($class) {
                return strpos($class, 'elementor') === false;
            });
        }, 999);
        
        add_action('wp_print_styles', [$this, 'remove_elementor_styles'], 100);
        add_action('wp_print_scripts', [$this, 'remove_elementor_scripts'], 100);
        
        add_filter('elementor/frontend/should_enqueue_scripts', '__return_false');
        add_filter('elementor/frontend/should_enqueue_styles', '__return_false');
        add_filter('elementor/editor/enqueue_scripts', '__return_false');
    }
    
    private function prevent_elementor_rendering() {
        if (!is_singular()) return;
        
        global $post;
        if (!$post) return;
        
        add_filter('get_post_metadata', function($value, $object_id, $meta_key) use ($post) {
            if ($object_id === $post->ID && $meta_key === '_elementor_edit_mode') {
                return false;
            }
            return $value;
        }, 10, 3);
    }
    
    private function resolve_conflicts() {
        if ($this->conflicts_resolved) return;
        
        // CRITICAL FIX: Resolve conflicts BEFORE other scripts load
        add_action('wp_head', [$this, 'conflict_resolution_script'], 0); // Priority 0!
        add_action('wp_footer', [$this, 'duplicate_cleanup_script'], 999);
        
        $this->conflicts_resolved = true;
    }
    
    public function conflict_resolution_script() {
        ?>
        <script>
        (function() {
            'use strict';
            
            // CRITICAL FIX: Prevent multiple executions
            if (window.ovbElementorManagerLoaded) return;
            window.ovbElementorManagerLoaded = true;
            
            // FIX 1: Create elementorFrontendConfig BEFORE Elementor scripts load
            if (typeof window.elementorFrontendConfig === 'undefined') {
                // console.log('OVB: Creating elementorFrontendConfig fallback');
                window.elementorFrontendConfig = {
                    environmentMode: {
                        edit: false,
                        wpPreview: false,
                        isScriptDebug: false
                    },
                    i18n: {
                        shareOnFacebook: "Share on Facebook",
                        shareOnTwitter: "Share on Twitter",
                        pinIt: "Pin it",
                        download: "Download",
                        downloadImage: "Download image",
                        fullscreen: "Fullscreen",
                        zoom: "Zoom",
                        share: "Share",
                        playVideo: "Play Video",
                        previous: "Previous",
                        next: "Next",
                        close: "Close"
                    },
                    is_rtl: false,
                    breakpoints: { xs: 0, sm: 480, md: 768, lg: 1025, xl: 1440, xxl: 1600 },
                    responsive: {
                        breakpoints: {
                            mobile: { label: "Mobile", value: 767, direction: "max", is_enabled: true },
                            mobile_extra: { label: "Mobile Extra", value: 880, direction: "max", is_enabled: false },
                            tablet: { label: "Tablet", value: 1024, direction: "max", is_enabled: true },
                            tablet_extra: { label: "Tablet Extra", value: 1200, direction: "max", is_enabled: false },
                            laptop: { label: "Laptop", value: 1366, direction: "max", is_enabled: false },
                            widescreen: { label: "Widescreen", value: 2400, direction: "min", is_enabled: false }
                        }
                    },
                    version: "3.30.4",
                    is_static: false,
                    experimentalFeatures: {},
                    urls: { assets: "<?php echo esc_js(plugins_url('assets/', ELEMENTOR__FILE__)); ?>" },
                    settings: { page: [], editorPreferences: [] },
                    kit: { active_breakpoints: ["viewport_mobile", "viewport_tablet"], global_image_lightbox: "yes" },
                    post: { id: <?php echo get_the_ID() ?: 0; ?>, title: "<?php echo esc_js(get_the_title()); ?>", excerpt: "" }
                };
            }
            
            // FIX 2: Handle lazyloadRunObserver safely
            // if (typeof window.lazyloadRunObserver !== 'undefined') {
            //     // console.log('OVB: lazyloadRunObserver already exists, overriding safely');
            // }
            
            // Create safe version that won't cause redeclaration error
            window.lazyloadRunObserver = function() {
                // console.log('OVB: Safe lazy load observer - disabled for booking pages');
                return false;
            };
            
            // FIX 3: Create safe elementorFrontend fallback
            if (typeof elementorFrontend !== 'undefined' && !elementorFrontend.config) {
                elementorFrontend.config = window.elementorFrontendConfig;
            }
            
            // console.log('OVB: Elementor conflicts resolved successfully');
            
        })();
        </script>
        <?php
    }
    
    public function duplicate_cleanup_script() {
        $page_type = $this->get_current_page_type();
        if (!$page_type) return;
        
        ?>
        <script>
        (function($) {
            'use strict';
            
            $(document).ready(function() {
                // Page-specific duplicate removal
                <?php if ($page_type === 'product'): ?>
                if ($('.single_add_to_cart_button').length > 1) {
                    $('.single_add_to_cart_button:not(:first)').remove();
                    // console.log('OVB: Removed duplicate add to cart buttons');
                }
                if ($('.woocommerce-product-gallery').length > 1) {
                    $('.woocommerce-product-gallery:not(:first)').remove();
                    // console.log('OVB: Removed duplicate product galleries');
                }
                $('.price:empty').remove();
                
                <?php elseif ($page_type === 'cart'): ?>
                if ($('.woocommerce-cart-form').length > 1) {
                    $('.woocommerce-cart-form:not(:first)').remove();
                    // console.log('OVB: Removed duplicate cart forms');
                }
                if ($('.cart_totals').length > 1) {
                    $('.cart_totals:not(:first)').remove();
                }
                if ($('button[name="update_cart"]').length > 1) {
                    $('button[name="update_cart"]:not(:first)').remove();
                }
                
                <?php elseif ($page_type === 'checkout'): ?>
                if ($('.woocommerce-checkout').length > 1) {
                    $('.woocommerce-checkout:not(:first)').remove();
                    // console.log('OVB: Removed duplicate checkout forms');
                }
                if ($('#payment').length > 1) {
                    $('#payment:not(:first)').remove();
                }
                if ($('#order_review').length > 1) {
                    $('#order_review:not(:first)').remove();
                }
                
                <?php elseif ($page_type === 'account'): ?>
                if ($('.woocommerce-MyAccount-navigation').length > 1) {
                    $('.woocommerce-MyAccount-navigation:not(:first)').remove();
                    // console.log('OVB: Removed duplicate account navigation');
                }
                if ($('.woocommerce-MyAccount-content').length > 1) {
                    $('.woocommerce-MyAccount-content:not(:first)').remove();
                }
                
                <?php elseif ($page_type === 'thank_you'): ?>
                if ($('.woocommerce-order').length > 1) {
                    $('.woocommerce-order:not(:first)').remove();
                }
                <?php endif; ?>
                
                // console.log('OVB: Page cleanup completed for <?php echo $page_type; ?>');
            });
            
        })(jQuery);
        </script>
        <?php
    }
    
    public function handle_shop_page() {
        if (!function_exists('is_shop') || !is_shop()) {
            return;
        }
        
        add_filter('elementor/widget/render_content', [$this, 'limit_elementor_products'], 10, 2);
        
        add_action('wp_footer', function() {
            ?>
            <script>
            (function($) {
                'use strict';
                $(document).ready(function() {
                    $('.elementor-widget-woocommerce-products').each(function(i){
                        if(i > 0) {
                            $(this).remove();
                            // console.log('OVB Shop: Removed duplicate Elementor products widget');
                        }
                    });
                    
                    var seenProducts = {};
                    $('.woocommerce ul.products li.product').each(function(){
                        var $product = $(this);
                        var title = $product.find('.woocommerce-loop-product__title').text().trim();
                        
                        if (title && seenProducts[title]) {
                            $product.remove();
                            // console.log('OVB Shop: Removed duplicate product:', title);
                        } else if (title) {
                            seenProducts[title] = true;
                        }
                    });
                });
            })(jQuery);
            </script>
            <?php
        }, 999);
    }
    
    public function limit_elementor_products($content, $widget) {
        if (!is_shop()) {
            return $content;
        }
        
        $name = $widget->get_name();
        if (in_array($name, ['woocommerce-products', 'products'])) {
            static $count = 0;
            $count++;
            if ($count > 1) {
                return '<!-- OVB: Duplicate products widget hidden -->';
            }
        }
        
        return $content;
    }
    
    public function remove_elementor_styles() {
        $patterns = [
            'elementor-frontend*',
            'elementor-post-*',
            'elementor-global*',
            'elementor-icons*',
            'elementor-animations*',
            'elementor-lazyload*'
        ];
        
        global $wp_styles;
        if (!$wp_styles) return;
        
        foreach ($wp_styles->registered as $handle => $style) {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $handle)) {
                    wp_dequeue_style($handle);
                    wp_deregister_style($handle);
                    break;
                }
            }
        }
    }
    
    public function remove_elementor_scripts() {
        $patterns = [
            'elementor-frontend*',
            'elementor-waypoints*',
            'elementor-core-js*',
            'elementor-lazyload*'
        ];
        
        global $wp_scripts;
        if (!$wp_scripts) return;
        
        foreach ($wp_scripts->registered as $handle => $script) {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $handle)) {
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                    break;
                }
            }
        }
    }
    
    public function cleanup_elementor_meta() {
        if (!$this->should_disable_elementor() || !is_singular()) {
            return;
        }
        
        global $post;
        if (!$post) return;
        
        add_filter('get_post_metadata', function($value, $object_id, $meta_key) use ($post) {
            if ($object_id === $post->ID) {
                $elementor_meta_keys = [
                    '_elementor_edit_mode',
                    '_elementor_template_type',
                    '_elementor_version',
                    '_elementor_pro_version',
                    '_elementor_data'
                ];
                
                if (in_array($meta_key, $elementor_meta_keys)) {
                    return false;
                }
            }
            return $value;
        }, 10, 3);
    }
    
    public function emergency_cleanup() {
        if (!$this->should_disable_elementor()) {
            return;
        }
        
        global $wp_filter;
        
        $elementor_hooks = [
            'elementor/frontend/before_render',
            'elementor/frontend/after_render',
            'elementor/widget/render_content',
            'elementor/element/before_parse_css',
            'elementor/theme/register_locations',
            'elementor/core/files/clear_cache'
        ];
        
        foreach ($elementor_hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                unset($wp_filter[$hook]);
            }
        }
    }
}

// Initialize only if Elementor exists
if (class_exists('\Elementor\Plugin')) {
    OVB_Elementor_Manager::get_instance();
}

// Debug helper
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function() {
        if (class_exists('OVB_Elementor_Manager')) {
            $manager = OVB_Elementor_Manager::get_instance();
            if (method_exists($manager, 'should_disable_elementor') && $manager->should_disable_elementor()) {
                echo '<!-- OVB: Elementor disabled for this page (v3.30.4 compatible) -->';
            }
        }
    });
}