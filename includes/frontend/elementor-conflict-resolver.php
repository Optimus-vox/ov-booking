<?php
defined('ABSPATH') || exit;

/**
 * ===============================================
 *  OV BOOKING JAVASCRIPT CONFLICT RESOLVER
 * ===============================================
 * 
 * Ovaj fajl rešava konflikte sa Elementor, lazy loading,
 * i drugim plugin-ima koji mogu da interferiraju sa 
 * OV Booking funkcionalnostima.
 */


global $ovb_elementor_fixed;
if (!isset($ovb_elementor_fixed)) {
    $ovb_elementor_fixed = false;
}
/**
 * INICIJALIZUJ CONFLICT RESOLVER
 */
add_action('init', 'ovb_init_conflict_resolver', 0);
function ovb_init_conflict_resolver() {
    if (!is_admin()) {
        add_action('wp_head', 'ovb_prevent_script_conflicts', 1);
        add_action('wp_footer', 'ovb_resolve_script_conflicts', 999);
    }
}

/**
 * SPREČAVANJE KONFLIKATA U HEAD-u
 */
function ovb_prevent_script_conflicts() {
    ?>
    <script>
    (function() {
        'use strict';
        
        // Global conflict prevention
        window.ovbConflictResolver = {
            lazyLoadFixed: false,
            elementorFixed: false,
            
            // Spreci duplikate lazy load observer-a
            fixLazyLoad: function() {
                if (this.lazyLoadFixed) return;
                
                if (typeof window.lazyloadRunObserver !== 'undefined') {
                    console.log('OVB: Preventing duplicate lazyloadRunObserver');
                    delete window.lazyloadRunObserver;
                }
                
                // Kreiraj safe verziju
                window.lazyloadRunObserver = function() {
                    console.log('OVB: Safe lazyload observer called');
                    // Implementiraj basic lazy loading ako je potrebno
                    if (typeof IntersectionObserver !== 'undefined') {
                        var lazyImages = document.querySelectorAll('img[data-src]:not([data-ovb-lazy])');
                        if (lazyImages.length > 0) {
                            var imageObserver = new IntersectionObserver(function(entries, observer) {
                                entries.forEach(function(entry) {
                                    if (entry.isIntersecting) {
                                        var img = entry.target;
                                        img.src = img.dataset.src;
                                        img.setAttribute('data-ovb-lazy', 'loaded');
                                        observer.unobserve(img);
                                    }
                                });
                            });
                            
                            lazyImages.forEach(function(img) {
                                imageObserver.observe(img);
                            });
                        }
                    }
                };
                
                this.lazyLoadFixed = true;
            },
            
            // Fiksaj Elementor config probleme
            fixElementor: function() {
                if (this.elementorFixed) return;
                
                if (typeof elementorFrontend !== 'undefined') {
                    // Ensure assets URL postoji
                    if (!elementorFrontend.config || !elementorFrontend.config.urls || !elementorFrontend.config.urls.assets) {
                        console.log('OVB: Fixing Elementor assets config');
                        
                        if (!elementorFrontend.config) elementorFrontend.config = {};
                        if (!elementorFrontend.config.urls) elementorFrontend.config.urls = {};
                        
                        // Postavi fallback assets URL
                        elementorFrontend.config.urls.assets = '<?php echo esc_js(plugins_url("assets/", ELEMENTOR__FILE__)); ?>';
                    }
                    
                    // Dodaj osnovne config-e ako ne postoje
                    if (!elementorFrontend.config.environmentMode) {
                        elementorFrontend.config.environmentMode = {
                            edit: false,
                            wpPreview: false,
                            isScriptDebug: false
                        };
                    }
                    
                    if (!elementorFrontend.config.breakpoints) {
                        elementorFrontend.config.breakpoints = {
                            xs: 0, sm: 480, md: 768, lg: 1025, xl: 1440, xxl: 1600
                        };
                    }
                }
                
                this.elementorFixed = true;
            },
            
            // Glavni resolver
            resolve: function() {
                this.fixLazyLoad();
                this.fixElementor();
            }
        };
        
        // Pokreni odmah
        window.ovbConflictResolver.resolve();
        
        // Pokreni ponovo kada se DOM učita
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                window.ovbConflictResolver.resolve();
            });
        } else {
            window.ovbConflictResolver.resolve();
        }
        
    })();
    </script>
    <?php
}

/**
 * REŠAVANJE KONFLIKATA U FOOTER-u
 */
function ovb_resolve_script_conflicts() {
    ?>
    <script>
    (function($) {
        'use strict';
        
        // Final cleanup kada se sve učita
        $(document).ready(function() {
            
            // 1. Lazy Load Final Fix
            if (typeof window.lazyloadRunObserver !== 'undefined') {
                // Ako postoji funkcija, ali pravi probleme, zameni je
                var originalLazyLoad = window.lazyloadRunObserver;
                window.lazyloadRunObserver = function() {
                    try {
                        // Pozovi originalnu funkciju samo jednom
                        if (!window.ovbLazyLoadExecuted) {
                            originalLazyLoad();
                            window.ovbLazyLoadExecuted = true;
                        }
                    } catch(e) {
                        console.warn('OVB: Lazy load error caught:', e);
                        // Fallback implementacija
                        $('img[data-src]').each(function() {
                            var $img = $(this);
                            if (!$img.attr('data-ovb-lazy')) {
                                $img.attr('src', $img.attr('data-src'));
                                $img.attr('data-ovb-lazy', 'loaded');
                            }
                        });
                    }
                };
            }
            
            // 2. Elementor Final Fix
            if (typeof elementorFrontend !== 'undefined') {
                // Proveri da li su svi potrebni config-i tu
                if (!elementorFrontend.config.urls || !elementorFrontend.config.urls.assets) {
                    console.warn('OVB: Final Elementor assets fix');
                    if (!elementorFrontend.config.urls) elementorFrontend.config.urls = {};
                    elementorFrontend.config.urls.assets = '<?php echo esc_js(plugins_url("assets/", ELEMENTOR__FILE__)); ?>';
                }
            }
            
            // 3. Shop Page Duplicate Fix
            if ($('body').hasClass('woocommerce-shop')) {
                // Ukloni duplikate proizvoda
                var seenProducts = {};
                $('.woocommerce ul.products li.product').each(function() {
                    var $product = $(this);
                    var productTitle = $product.find('.woocommerce-loop-product__title, h2.woocommerce-loop-product__title').text().trim();
                    
                    if (productTitle && seenProducts[productTitle]) {
                        console.log('OVB Shop: Removing duplicate product:', productTitle);
                        $product.remove();
                    } else if (productTitle) {
                        seenProducts[productTitle] = true;
                    }
                });
                
                // Ukloni prazne product grid-ove
                $('.woocommerce ul.products').each(function() {
                    var $grid = $(this);
                    if ($grid.find('li.product').length === 0) {
                        $grid.remove();
                    }
                });
            }
            
            // 4. Performance optimization - ukloni nekorišćene script handler-e
            if (typeof wp !== 'undefined' && wp.hooks) {
                // Clear neki hooks koji mogu da prave probleme
                try {
                    wp.hooks.removeAllFilters('elementor_frontend_handlers_menu_default');
                    wp.hooks.removeAllFilters('elementor/frontend/handlers/nav-menu/default');
                } catch(e) {
                    // Silent fail
                }
            }
            
            // 5. Memory cleanup
            setTimeout(function() {
                // Ukloni temporary variables
                if (window.ovbElementorBackup) {
                    delete window.ovbElementorBackup;
                }
                if (window.ovbLazyLoadExecuted) {
                    delete window.ovbLazyLoadExecuted;
                }
            }, 1000);
            
        });
        
        // Window load event za final cleanup
        $(window).on('load', function() {
            // Finalni cleanup nakon što se sve učita
            if (typeof window.lazyloadRunObserver !== 'undefined') {
                // Pokreni lazy load observer jednom kada se stranica učita
                try {
                    if (!window.ovbLazyLoadExecuted) {
                        window.lazyloadRunObserver();
                        window.ovbLazyLoadExecuted = true;
                    }
                } catch(e) {
                    console.warn('OVB: Final lazy load execution failed:', e);
                }
            }
        });
        
    })(jQuery);
    </script>
    <?php
}

/**
 * SPECIFIČNI CONFLICT RESOLVERS ZA RAZLIČITE STRANICE
 */

// Product Page Conflict Resolver
add_action('wp_footer', 'ovb_product_page_conflicts');
function ovb_product_page_conflicts() {
    if (!is_singular('product')) return;
    
    ?>
    <script>
    (function($) {
        'use strict';
        
        $(document).ready(function() {
            // Product-specific conflict resolution
            
            // 1. WooCommerce add to cart form fix
            if ($('.single_add_to_cart_button').length > 1) {
                $('.single_add_to_cart_button:not(:first)').remove();
            }
            
            // 2. Price display fix
            $('.price:empty').remove();
            
            // 3. Gallery fix
            if ($('.woocommerce-product-gallery').length > 1) {
                $('.woocommerce-product-gallery:not(:first)').remove();
            }
        });
        
    })(jQuery);
    </script>
    <?php
}

// Cart Page Conflict Resolver
add_action('wp_footer', 'ovb_cart_page_conflicts');
function ovb_cart_page_conflicts() {
    if (!function_exists('is_cart') || !is_cart()) return;
    
    ?>
    <script>
    (function($) {
        'use strict';
        
        $(document).ready(function() {
            // Cart-specific conflict resolution
            
            // 1. Multiple cart tables fix
            if ($('.woocommerce-cart-form').length > 1) {
                $('.woocommerce-cart-form:not(:first)').remove();
            }
            
            // 2. Cart totals fix
            if ($('.cart_totals').length > 1) {
                $('.cart_totals:not(:first)').remove();
            }
            
            // 3. Update cart button fix
            if ($('button[name="update_cart"]').length > 1) {
                $('button[name="update_cart"]:not(:first)').remove();
            }
        });
        
    })(jQuery);
    </script>
    <?php
}

// Checkout Page Conflict Resolver
add_action('wp_footer', 'ovb_checkout_page_conflicts');
function ovb_checkout_page_conflicts() {
    if (!function_exists('is_checkout') || !is_checkout()) return;
    
    ?>
    <script>
    (function($) {
        'use strict';
        
        $(document).ready(function() {
            // Checkout-specific conflict resolution
            
            // 1. Multiple checkout forms fix
            if ($('.woocommerce-checkout').length > 1) {
                $('.woocommerce-checkout:not(:first)').remove();
            }
            
            // 2. Payment methods fix
            if ($('#payment').length > 1) {
                $('#payment:not(:first)').remove();
            }
            
            // 3. Order review fix
            if ($('#order_review').length > 1) {
                $('#order_review:not(:first)').remove();
            }
        });
        
    })(jQuery);
    </script>
    <?php
}

// My Account Page Conflict Resolver
add_action('wp_footer', 'ovb_account_page_conflicts');
function ovb_account_page_conflicts() {
    if (!function_exists('is_account_page') || !is_account_page()) return;
    
    ?>
    <script>
    (function($) {
        'use strict';
        
        $(document).ready(function() {
            // My Account-specific conflict resolution
            
            // 1. Multiple account navigation fix
            if ($('.woocommerce-MyAccount-navigation').length > 1) {
                $('.woocommerce-MyAccount-navigation:not(:first)').remove();
            }
            
            // 2. Account content fix
            if ($('.woocommerce-MyAccount-content').length > 1) {
                $('.woocommerce-MyAccount-content:not(:first)').remove();
            }
        });
        
    })(jQuery);
    </script>
    <?php
}

/**
 * EMERGENCY SCRIPT DEQUEUE ZA PROBLEMATIČNE STRANICE
 */
add_action('wp_enqueue_scripts', 'ovb_emergency_script_dequeue', 999);
function ovb_emergency_script_dequeue() {
    $problematic_pages = [
        is_singular('product'),
        (function_exists('is_cart') && is_cart()),
        (function_exists('is_checkout') && is_checkout()),
        (function_exists('is_account_page') && is_account_page()),
        (function_exists('is_order_received_page') && is_order_received_page())
    ];
    
    if (!in_array(true, $problematic_pages)) {
        return;
    }
    
    // Lista problematičnih script-ova koji prave konflikte
    $problematic_scripts = [
        'elementor-lazyload',
        'elementor-waypoints',
        'rocket-lazyload',
        'wp-smushit-lazy-load',
        'autoptimize-lazyload'
    ];
    
    foreach ($problematic_scripts as $script) {
        if (wp_script_is($script, 'enqueued')) {
            wp_dequeue_script($script);
            wp_deregister_script($script);
        }
    }
}

/**
 * SAFE SCRIPT LOADING ZA OVB STRANICE
 */
add_action('wp_enqueue_scripts', 'ovb_safe_script_loading', 999);
function ovb_safe_script_loading() {
    if (is_singular('product') || 
        (function_exists('is_cart') && is_cart()) ||
        (function_exists('is_checkout') && is_checkout()) ||
        (function_exists('is_account_page') && is_account_page())) {
        
        // Dodaj naš safe conflict resolver script
        wp_add_inline_script('jquery', '
            window.ovbSafeMode = true;
            
            // Override problematične funkcije
            if (typeof window.lazyloadRunObserver !== "undefined") {
                window.lazyloadRunObserver = function() {
                    console.log("OVB Safe Mode: Lazy load observer disabled");
                };
            }
            
            // Safe jQuery ready wrapper
            jQuery(document).ready(function($) {
                if (window.ovbSafeMode) {
                    console.log("OVB Safe Mode: DOM ready");
                    
                    // Ukloni duplikate elemenata
                    $("[data-ovb-duplicate-check]").each(function() {
                        var selector = $(this).data("ovb-duplicate-check");
                        var elements = $(selector);
                        if (elements.length > 1) {
                            elements.not(":first").remove();
                        }
                    });
                }
            });
        ');
    }
}

/**
 * DEBUG HELPER - ukloni u produkciji
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', 'ovb_conflict_debug_info');
    function ovb_conflict_debug_info() {
        if (is_singular('product') || 
            (function_exists('is_cart') && is_cart()) ||
            (function_exists('is_checkout') && is_checkout()) ||
            (function_exists('is_account_page') && is_account_page())) {
            
            ?>
            <script>
            console.log('OVB Conflict Resolver Active');
            console.log('Page Type:', {
                isProduct: <?php echo is_singular('product') ? 'true' : 'false'; ?>,
                isCart: <?php echo (function_exists('is_cart') && is_cart()) ? 'true' : 'false'; ?>,
                isCheckout: <?php echo (function_exists('is_checkout') && is_checkout()) ? 'true' : 'false'; ?>,
                isAccount: <?php echo (function_exists('is_account_page') && is_account_page()) ? 'true' : 'false'; ?>
            });
            if (typeof elementorFrontend !== 'undefined') {
                console.log('Elementor Config:', elementorFrontend.config);
            }
            </script>
            <?php
        }
    }
}