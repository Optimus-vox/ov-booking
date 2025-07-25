<?php
defined('ABSPATH') || exit;
require_once dirname(__DIR__) . '/helpers/logger.php';


// Ukloni Elementor za product stranice, ali zadrži klasični WordPress editor

// 1) Onemogući Gutenberg (block editor) samo za product CPT - vraća klasični editor
add_filter( 'use_block_editor_for_post_type', function( $use_block_editor, $post_type ) {
    if ( 'product' === $post_type ) {
        return false; // Ovo vraća klasični TinyMCE editor, ne uklanja editor potpuno
    }
    return $use_block_editor;
}, 10, 2 );

// 2) Onemogući Elementor editor samo za product CPT
add_action( 'plugins_loaded', function() {
    
    // a) Ne učitava Elementor editor za products
    add_filter( 'elementor/editor/should_load', function( $should_load, $post_id ) {
        if ( 'product' === get_post_type( $post_id ) ) {
            return false;
        }
        return $should_load;
    }, 20, 2 );
    
    // b) Uklanja "Edit with Elementor" dugme za products
    add_filter( 'elementor/utils/is_post_type_allowed', function( $allowed, $post_type ) {
        if ( 'product' === $post_type ) {
            return false;
        }
        return $allowed;
    }, 20, 2 );
    
    // c) Uklanja Elementor iz post type supports za products
    add_action( 'init', function() {
        remove_post_type_support( 'product', 'elementor' );
    }, 20 );
    
}, 20 );

// 3) Opciono: Forsiraj klasični editor za products (ako je potrebno)
add_filter( 'wp_editor_settings', function( $settings, $editor_id ) {
    // Ako je product edit screen
    if ( isset( $_GET['post'] ) ) {
        $post_id = intval( $_GET['post'] );
        if ( 'product' === get_post_type( $post_id ) ) {
            $settings['tinymce'] = true;
            $settings['quicktags'] = true;
            $settings['media_buttons'] = true;
        }
    }
    
    // Ili ako je novi product
    if ( isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'] ) {
        $settings['tinymce'] = true;
        $settings['quicktags'] = true;
        $settings['media_buttons'] = true;
    }
    
    return $settings;
}, 10, 2 );

// 4) Ukloni Elementor metaboxes iz product edit screen-a
add_action( 'add_meta_boxes', function() {
    global $post;
    if ( $post && 'product' === $post->post_type ) {
        remove_meta_box( 'elementor-editor', 'product', 'normal' );
        remove_meta_box( 'elementor-editor-button', 'product', 'side' );
    }
}, 999 );

// 5) Alternativno rešenje: Sakrij Elementor dugmad preko CSS-a (backup)
add_action( 'admin_head-post.php', function() {
    global $post;
    if ( $post && 'product' === $post->post_type ) {
        echo '<style>
            .elementor-switch-mode-button,
            .elementor-editor-button,
            #elementor-editor,
            .elementor-switch-mode-off,
            .elementor-switch-mode-on {
                display: none !important;
            }
        </style>';
    }
});

add_action( 'admin_head-post-new.php', function() {
    if ( isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'] ) {
        echo '<style>
            .elementor-switch-mode-button,
            .elementor-editor-button,
            #elementor-editor,
            .elementor-switch-mode-off,
            .elementor-switch-mode-on {
                display: none !important;
            }
        </style>';
    }
});

// 6) Opciono: Ako imaš probleme sa tema ili plugin konfliktima
// Forsiraj uklanjanje Elementor action-a za products
add_action( 'current_screen', function( $current_screen ) {
    if ( 'product' === $current_screen->post_type ) {
        // Ukloni Elementor hooks za product stranice
        remove_all_actions( 'elementor/editor/before_enqueue_scripts' );
        remove_all_actions( 'elementor/editor/after_enqueue_scripts' );
        
        // Deregister Elementor editor scripts/styles za products
        add_action( 'admin_enqueue_scripts', function() {
            wp_dequeue_script( 'elementor-editor' );
            wp_dequeue_style( 'elementor-editor' );
        }, 999 );
    }
});

// Sakrij WPForms "Add Form" dugme na product edit screen-u
add_action( 'admin_head-post.php', function() {
    global $post;
    if ( $post && 'product' === $post->post_type ) {
        echo '<style>
            button.wpforms-insert-form-button,
            #wp-content-media-buttons .wpforms-insert-form-button {
                display: none !important;
            }
        </style>';
    }
} );

add_action( 'admin_head-post-new.php', function() {
    if ( isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'] ) {
        echo '<style>
            button.wpforms-insert-form-button,
            #wp-content-media-buttons .wpforms-insert-form-button {
                display: none !important;
            }
        </style>';
    }
} );