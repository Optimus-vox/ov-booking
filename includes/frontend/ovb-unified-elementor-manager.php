<?php
defined('ABSPATH') || exit;

/**
 * OVB BOOKING UNIFIED ELEMENTOR MANAGER (Safe v2)
 * - Hard disable: single product
 * - Light disable: cart / checkout / account / thankyou (zadrži Elementor CSS/JS i body klase)
 */

class OVB_Elementor_Manager {

    private static $instance = null;
    private $conflicts_resolved = false;
    private $disabled_pages = [];
    private $admin_disabled = false;

    public static function get_instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        if (!class_exists('\Elementor\Plugin')) return;
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
            'product'   => is_singular('product'),
            'cart'      => (function_exists('is_cart') && is_cart()),
            'checkout'  => (function_exists('is_checkout') && is_checkout()),
            'account'   => (function_exists('is_account_page') && is_account_page()),
            'thank_you' => (function_exists('is_order_received_page') && is_order_received_page()),
        ];
    }

    private function get_current_page_type() {
        foreach ($this->disabled_pages as $type => $active) if ($active) return $type;
        return false;
    }

    public function should_disable_elementor() {
        return in_array(true, $this->disabled_pages, true);
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
        if (!$this->should_disable_elementor()) return;

        $type = $this->get_current_page_type();

        // HARD mode samo za product
        if ($type === 'product') {
            $this->remove_elementor_assets(true);   // hard
            $this->prevent_elementor_rendering(true);
            $this->resolve_conflicts(true);
            return;
        }

        // LIGHT mode za Woo flow (checkout, cart, account, thank_you)
        if (in_array($type, ['checkout','cart','account','thank_you'], true)) {
            // Nemoj skidati Elementor CSS/JS niti body klase!
            $this->limit_elementor_theme_locations();
            $this->prevent_elementor_rendering(false); // ne diraj meta, samo zaštiti lokacije
            $this->resolve_conflicts(false);           // bez agresivnih JS hack-ova
            return;
        }
    }

    /** Limitiraj samo Theme Builder lokacije na Woo flow-u (Elementor da ne pregazi WC) */
    private function limit_elementor_theme_locations() {
        add_filter('elementor/theme/do_location', function ($do, $location) {
            // Blokiraj sve Woo-spec lokacije koje bi zamenile WC template
            $blocked = ['checkout', 'cart', 'my-account', 'woocommerce'];
            return in_array($location, $blocked, true) ? false : $do;
        }, 999, 2);
    }

    /** HARD: skini Elementor assete na product; LIGHT: ne diraj na Woo flow-u */
    private function remove_elementor_assets($hard = false) {
        if (!$hard) return; // samo product

        $elementor = \Elementor\Plugin::instance();

        add_filter('elementor/theme/do_location', function ($do, $location) {
            $restricted = ['single', 'single-product', 'archive'];
            return in_array($location, $restricted, true) ? false : $do;
        }, PHP_INT_MAX, 2);

        // Skidanje frontend asseta (SAMO u hard modu)
        remove_action('wp_enqueue_scripts', [$elementor->frontend, 'enqueue_scripts'], 20);
        remove_action('wp_print_styles', [$elementor->frontend, 'enqueue_styles'], 10);
        remove_action('wp_head', [$elementor->frontend, 'print_google_fonts']);
        remove_action('wp_enqueue_scripts', [$elementor->frontend, 'enqueue_frontend_scripts']);
        remove_action('wp_footer', [$elementor->frontend, 'wp_footer']);
        remove_action('wp_head', [$elementor->frontend, 'print_head_attributes']);

        // Ne globalno: ograniči na product
        add_filter('elementor/widgets/is_widget_supported', '__return_false');
        add_action('wp_print_styles', [$this, 'remove_elementor_styles'], 100);
        add_action('wp_print_scripts', [$this, 'remove_elementor_scripts'], 100);
        add_filter('elementor/frontend/should_enqueue_scripts', '__return_false');
        add_filter('elementor/frontend/should_enqueue_styles', '__return_false');
    }

    /** Spreči render – HARD: i meta; LIGHT: samo preko do_location guard-a */
    private function prevent_elementor_rendering($hard = false) {
        if (!is_singular()) return;
        if (!$hard) return; // u light modu ne diramo meta, jer mnoge stranice nose shortcode unutar Elementor page-a

        global $post;
        if (!$post) return;

        add_filter('get_post_metadata', function($value, $object_id, $meta_key) use ($post) {
            if ($object_id === $post->ID && $meta_key === '_elementor_edit_mode') {
                return false;
            }
            return $value;
        }, 10, 3);
    }

    /** Konflikti – HARD: uključuje JS fallback; LIGHT: bez toga (da ne kvari checkout) */
    private function resolve_conflicts($hard = false) {
        if ($this->conflicts_resolved) return;

        if ($hard) {
            add_action('wp_head', [$this, 'conflict_resolution_script'], 0);
        }
        add_action('wp_footer', [$this, 'duplicate_cleanup_script'], 999);

        $this->conflicts_resolved = true;
    }

    /** JS hack – samo u HARD modu (product). Ne ubrizgavaj na checkout! */
    public function conflict_resolution_script() {
        ?>
        <script>
        (function(){"use strict";
          if(window.ovbElementorManagerLoaded) return;
          window.ovbElementorManagerLoaded = true;

          // Safe elementorFrontendConfig (ako je potrebno)
          if(typeof window.elementorFrontendConfig === 'undefined'){
            window.elementorFrontendConfig = {
              environmentMode:{edit:false,wpPreview:false,isScriptDebug:false},
              i18n:{previous:"Previous",next:"Next",close:"Close"},
              is_rtl:false, version:"3.x", is_static:false,
              urls:{ assets:"" }, settings:{ page:[], editorPreferences:[] },
              kit:{ active_breakpoints:["viewport_mobile","viewport_tablet"] },
              post:{ id: <?php echo get_the_ID() ?: 0; ?>, title:"<?php echo esc_js(get_the_title()); ?>", excerpt:"" }
            };
          }

          // NE diramo lazyload / observer na checkout-u (ovaj script se ionako ne ubacuje tamo)
        })();
        </script>
        <?php
    }

    /** Ukloni duplikate elemenata posle rendera (bezbedno za sve stranice) */
    public function duplicate_cleanup_script() {
        $page_type = $this->get_current_page_type();
        if (!$page_type) return;
        ?>
        <script>
        (function($){
          'use strict';
          $(function(){
            <?php if ($page_type === 'product'): ?>
              if ($('.single_add_to_cart_button').length > 1) $('.single_add_to_cart_button:not(:first)').remove();
              if ($('.woocommerce-product-gallery').length > 1) $('.woocommerce-product-gallery:not(:first)').remove();
              $('.price:empty').remove();
            <?php elseif ($page_type === 'cart'): ?>
              if ($('.woocommerce-cart-form').length > 1) $('.woocommerce-cart-form:not(:first)').remove();
              if ($('.cart_totals').length > 1) $('.cart_totals:not(:first)').remove();
              if ($('button[name="update_cart"]').length > 1) $('button[name="update_cart"]:not(:first)').remove();
            <?php elseif ($page_type === 'checkout'): ?>
              if ($('.woocommerce-checkout').length > 1) $('.woocommerce-checkout:not(:first)').remove();
              if ($('#payment').length > 1) $('#payment:not(:first)').remove();
              if ($('#order_review').length > 1) $('#order_review:not(:first)').remove();
            <?php elseif ($page_type === 'account'): ?>
              if ($('.woocommerce-MyAccount-navigation').length > 1) $('.woocommerce-MyAccount-navigation:not(:first)').remove();
              if ($('.woocommerce-MyAccount-content').length > 1) $('.woocommerce-MyAccount-content:not(:first)').remove();
            <?php elseif ($page_type === 'thank_you'): ?>
              if ($('.woocommerce-order').length > 1) $('.woocommerce-order:not(:first)').remove();
            <?php endif; ?>
          });
        })(jQuery);
        </script>
        <?php
    }

    /** Shop page zaštite (ostavljeno kako je bilo) */
    public function handle_shop_page() {
        if (!function_exists('is_shop') || !is_shop()) return;

        add_filter('elementor/widget/render_content', [$this, 'limit_elementor_products'], 10, 2);

        add_action('wp_footer', function() {
            ?>
            <script>
            (function($){
              'use strict';
              $(function(){
                $('.elementor-widget-woocommerce-products').each(function(i){
                  if(i > 0) $(this).remove();
                });
                var seen={};
                $('.woocommerce ul.products li.product').each(function(){
                  var t=$(this).find('.woocommerce-loop-product__title').text().trim();
                  if(t && seen[t]) $(this).remove(); else if(t) seen[t]=true;
                });
              });
            })(jQuery);
            </script>
            <?php
        }, 999);
    }

    public function limit_elementor_products($content, $widget) {
        if (!is_shop()) return $content;
        $name = $widget->get_name();
        if (in_array($name, ['woocommerce-products', 'products'])) {
            static $count = 0;
            $count++;
            if ($count > 1) return '<!-- OVB: Duplicate products widget hidden -->';
        }
        return $content;
    }

    /** HARD only: deregistruj Elementor stilove/skripte */
    public function remove_elementor_styles() {
        $patterns = ['elementor-frontend*','elementor-post-*','elementor-global*','elementor-icons*','elementor-animations*','elementor-lazyload*'];
        global $wp_styles; if (!$wp_styles) return;
        foreach ($wp_styles->registered as $handle => $style) {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $handle)) { wp_dequeue_style($handle); wp_deregister_style($handle); break; }
            }
        }
    }

    public function remove_elementor_scripts() {
        $patterns = ['elementor-frontend*','elementor-waypoints*','elementor-core-js*','elementor-lazyload*'];
        global $wp_scripts; if (!$wp_scripts) return;
        foreach ($wp_scripts->registered as $handle => $script) {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $handle)) { wp_dequeue_script($handle); wp_deregister_script($handle); break; }
            }
        }
    }

    /** Očisti Elementor metа SAMO u hard modu (product). Ne diraj Woo flow! */
    public function cleanup_elementor_meta() {
        $type = $this->get_current_page_type();
        if ($type !== 'product' || !is_singular()) return;

        global $post; if (!$post) return;

        add_filter('get_post_metadata', function($value, $object_id, $meta_key) use ($post) {
            if ($object_id === $post->ID) {
                $elementor_meta_keys = ['_elementor_edit_mode','_elementor_template_type','_elementor_version','_elementor_pro_version','_elementor_data'];
                if (in_array($meta_key, $elementor_meta_keys, true)) return false;
            }
            return $value;
        }, 10, 3);
    }

    /** Agresivno čišćenje Elementor hookova – ISKLJUČENO na Woo flow-u */
    public function emergency_cleanup() {
        if (!$this->should_disable_elementor()) return;
        $type = $this->get_current_page_type();
        if (in_array($type, ['checkout','cart','account','thank_you'], true)) return;

        global $wp_filter;
        $elementor_hooks = [
            'elementor/frontend/before_render',
            'elementor/frontend/after_render',
            'elementor/widget/render_content',
            'elementor/element/before_parse_css',
            'elementor/theme/register_locations',
            'elementor/core/files/clear_cache',
        ];
        foreach ($elementor_hooks as $hook) {
            if (isset($wp_filter[$hook])) unset($wp_filter[$hook]);
        }
    }
}

// Init ako postoji Elementor
if (class_exists('\Elementor\Plugin')) {
    OVB_Elementor_Manager::get_instance();
}

// Debug marker
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function() {
        if (class_exists('OVB_Elementor_Manager')) {
            $manager = OVB_Elementor_Manager::get_instance();
            if (method_exists($manager, 'should_disable_elementor') && $manager->should_disable_elementor()) {
                echo '<!-- OVB: Elementor manager active (Safe v2) on ' . esc_html( get_post_type() ?: 'page' ) . ' -->';
            }
        }
    });
}
