<?php
defined('ABSPATH') || exit;
require_once dirname(__DIR__) . '/helpers/logger.php';

// Uklanja "Excerpt" metabox
add_action('add_meta_boxes', function() {
    remove_meta_box('postexcerpt', 'product', 'normal');
}, 999);

// uklanja short description
add_action('init', function() {
    remove_post_type_support('product', 'excerpt');
}, 20);

// Uklanja Custom Fields
add_action('admin_menu', function() {
    remove_meta_box('postcustom', 'product', 'normal');
});

//Ukloni Revision 
add_action('admin_init', function() {
    remove_post_type_support('product', 'revisions');
});