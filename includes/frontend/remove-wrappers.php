<?php
defined('ABSPATH') || exit;

/**
 * Cilj: zadržati Elementor/Astra header & footer, ali isključiti njihov uticaj na
 * sadržaj single product / cart / checkout / thank-you / my account.
 *
 * Strategija:
 * - NE gasimo Elementor globalno.
 * - Na "zaštićenim" Woo stranicama:
 *   (a) ostavljamo elementor-frontend/pro JS + globalne stilove,
 *   (b) uklanjamo post-specifične Elementor CSS-ove osim onih za header/footer,
 *   (c) uklanjamo Astra Woo CSS (ali ostavljamo osnovni Astra theme CSS).
 */

/** Helper: detekcija zaštićenih Woo strana */
if (!function_exists('ovb_is_protected_woo_page')) {
    function ovb_is_protected_woo_page(): bool {
        return ( is_singular('product')
            || ( function_exists('is_cart') && is_cart() )
            || ( function_exists('is_checkout') && is_checkout() )
            || ( function_exists('is_account_page') && is_account_page() )
            || ( function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received') )
        );
    }
}

/** 0) (Ostavi) — isključi samo Astra Woo integraciju, NE i bazni theme CSS */
add_filter('astra_enable_woocommerce_integration', '__return_false');

/**
 * 1) Izvuci aktivne Elementor header/footer template ID-eve (ako postoji Elementor Pro)
 */
function ovb_get_e_header_footer_ids(): array {
    $ids = [];
    if (did_action('elementor_pro/init') && class_exists('\ElementorPro\Modules\ThemeBuilder\Module')) {
        try {
            $module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
            $cm     = $module->get_conditions_manager();

            foreach (['header','footer'] as $loc) {
                $doc_ids = [];
                // Probaj obe varijante API-ja (verzije se razlikuju):
                if (method_exists($cm, 'get_document_ids_for_location')) {
                    $doc_ids = (array) $cm->get_document_ids_for_location($loc);
                } elseif (method_exists($cm, 'get_documents_for_location')) {
                    $docs    = (array) $cm->get_documents_for_location($loc);
                    foreach ($docs as $doc) {
                        if (is_object($doc) && method_exists($doc, 'get_main_id')) {
                            $doc_ids[] = (int) $doc->get_main_id();
                        }
                    }
                }
                foreach ($doc_ids as $id) {
                    if ($id) { $ids[] = (int) $id; }
                }
            }
        } catch (\Throwable $e) {
            // tiho – header/footer i dalje rade i bez ovog preciznog whitelista
        }
    }
    return array_values(array_unique(array_filter($ids)));
}

/**
 * 2) Dequeue nepotrebnih stilova na zaštićenim stranicama (kasna prioritizacija da pobedimo sve enqueue-e)
 */
add_action('wp_print_styles', function () {
    if (!ovb_is_protected_woo_page()) return;

    $keep_e_handles = [
        'elementor-frontend',          // globalni Elementor CSS
        'elementor-pro',               // globalni Elementor Pro CSS
        'elementor-icons',             // ikone
        'elementor-icons-fa-solid',
        'elementor-icons-fa-regular',
        'elementor-icons-fa-brands',
    ];

    // Zadrži post-CSS samo za header/footer templejte (elementor-post-123)
    $hf_ids = ovb_get_e_header_footer_ids();

    global $wp_styles;
    if ($wp_styles && !empty($wp_styles->queue)) {
        foreach ((array) $wp_styles->queue as $handle) {
            // Elementor post CSS
            if (preg_match('/^elementor-post-(\d+)$/', (string)$handle, $m)) {
                $post_id = (int) $m[1];
                if (!in_array($post_id, $hf_ids, true)) {
                    wp_dequeue_style($handle);
                    continue;
                }
            }

            // Dequeue Elementor kit/global ako ti kolje content
            // (po potrebi komentariši sledeću liniju ako želiš global kit promenljive)
            if ($handle === 'elementor-global') {
                wp_dequeue_style($handle);
                continue;
            }

            // Astra Woo CSS – uvek uklonimo
            if (in_array($handle, [
                'astra-woocommerce',
                'astra-addon-woocommerce',
                'astra-addon-wc',
                'astra-woocommerce-smallscreen',
            ], true)) {
                wp_dequeue_style($handle);
                continue;
            }

            // NE diramo osnovni Astra theme CSS (npr. 'astra-theme-css', 'astra-theme-dynamic-css')
            // NE diramo globalne Elementor CSS-ove iz $keep_e_handles
        }
    }
}, 999);

/**
 * 3) Dequeue nepotrebnih skripti na zaštićenim stranicama – ostavi samo ono što treba header/footer-u
 */
add_action('wp_print_scripts', function () {
    if (!ovb_is_protected_woo_page()) return;

    // Zadrži osnovne Elementor JS za header/footer:
    $keep = [
        'elementor-frontend',
        'elementor-pro-frontend',
        'elementor-waypoints',
        'imagesloaded',  // često zavisi
        'jquery-core',
        'jquery-migrate',
    ];

    global $wp_scripts;
    if ($wp_scripts && !empty($wp_scripts->queue)) {
        foreach ((array) $wp_scripts->queue as $handle) {
            // Preskoči ono što želimo da zadržimo
            if (in_array($handle, $keep, true)) continue;

            // Ako prepoznamo Elementor editor/admin ili dev skripte – ukloni
            if (preg_match('/^elementor-(editor|minimap|hotkeys|assets)/', (string)$handle)) {
                wp_dequeue_script($handle);
                continue;
            }

            // Ne diramo teme/globalno (header/footer može zavisiti od tema JS-a)
        }
    }
}, 999);

/**
 * 4) SKLONI bilo kakvo staro globalno gašenje Elementor-a na ovim stranicama.
 *    Ako u ovom projektu imaš nešto poput:
 *      add_filter('elementor/frontend/should_enqueue_scripts', '__return_false')
 *    na cart/checkout/thank-you – OBRIŠI te filtere.
 */