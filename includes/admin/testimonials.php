<?php
defined('ABSPATH') || exit;
require_once dirname(__DIR__) . '/helpers/logger.php';




// ===== 1. META BOX ZA TESTIMONIALE =====
add_action('add_meta_boxes', 'add_product_testimonials_meta_box');
function add_product_testimonials_meta_box() {
    add_meta_box(
        'product_testimonials',
        'Customer Testimonials',
        'render_testimonials_meta_box',
        'product',
        'normal',
        'default'
    );
}

// ===== 2. PRIKAZ POLJA ZA UNOS =====
function render_testimonials_meta_box($post) {
    wp_nonce_field('testimonials_nonce_action', 'testimonials_nonce');
    $testimonials = get_post_meta($post->ID, '_product_testimonials', true) ?: array();
    
    echo '<div class="testimonials-container">';
    
    if (empty($testimonials)) {
        $testimonials[] = array('name' => '', 'rating' => 5, 'text' => '');
    }

    foreach ($testimonials as $i => $testimonial) {
        echo '<div class="testimonial-group" style="margin-bottom:20px;padding:15px;border:1px solid #ddd;">';
        echo '<p><strong>Name:</strong>';
        echo '<input type="text" name="testimonials['.$i.'][name]" value="'.esc_attr($testimonial['name']).'" style="width:100%" />';
        echo '</p>';
        
        echo '<p><strong>Rating:</strong>';
        echo '<select name="testimonials['.$i.'][rating]" style="width:100%">';
        foreach (range(0, 5, 0.5) as $rating) {
            $selected = selected($testimonial['rating'], $rating, false);
            echo '<option value="'.$rating.'" '.$selected.'>'.$rating.' ★</option>';
        }
        echo '</select></p>';
        
        echo '<p><strong>Review:</strong>';
        echo '<textarea name="testimonials['.$i.'][text]" style="width:100%;height:100px">'.esc_textarea($testimonial['text']).'</textarea>';
        echo '</p>';
        
        echo '<button type="button" class="button remove-testimonial">Remove</button>';
        echo '</div>';
    }
    
    echo '</div>';
    echo '<button type="button" id="add-testimonial" class="button">+ Add Testimonial</button>';
    
    // PURE JAVASCRIPT ZA DINAMIČKO DODAVANJE
    echo '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const container = document.querySelector(".testimonials-container");
        const addBtn = document.getElementById("add-testimonial");
        
        function createRatingOptions() {
            let options = "";
            for (let r = 0; r <= 5; r += 0.5) {
                options += `<option value="${r}">${r} ★</option>`;
            }
            return options;
        }
        
        addBtn.addEventListener("click", function() {
            const index = document.querySelectorAll(".testimonial-group").length;
            const html = `
                <div class="testimonial-group" style="margin-bottom:20px;padding:15px;border:1px solid #ddd">
                    <p><strong>Name:</strong>
                    <input type="text" name="testimonials[${index}][name]" style="width:100%" />
                    </p>
                    <p><strong>Rating:</strong>
                    <select name="testimonials[${index}][rating]" style="width:100%">
                        ${createRatingOptions()}
                    </select>
                    </p>
                    <p><strong>Review:</strong>
                    <textarea name="testimonials[${index}][text]" style="width:100%;height:100px"></textarea>
                    </p>
                    <button type="button" class="button remove-testimonial">Remove</button>
                </div>
            `;
            container.insertAdjacentHTML("beforeend", html);
        });
        
        container.addEventListener("click", function(e) {
            if (e.target.classList.contains("remove-testimonial")) {
                e.target.closest(".testimonial-group").remove();
            }
        });
    });
    </script>
    ';
}

// ===== 3. ČUVANJE PODATAKA =====
add_action('save_post_product', 'save_testimonials_meta_data');
function save_testimonials_meta_data($post_id) {
    if (!isset($_POST['testimonials_nonce']) || !wp_verify_nonce($_POST['testimonials_nonce'], 'testimonials_nonce_action')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    $testimonials = array();
    
    if (!empty($_POST['testimonials'])) {
        foreach ($_POST['testimonials'] as $data) {
            if (!empty($data['text'])) { // ISPRAVLJENA GREŠKA: ZATVORENA ZAGRADA
                $testimonials[] = array(
                    'name'   => sanitize_text_field($data['name']),
                    'rating' => floatval($data['rating']),
                    'text'   => sanitize_textarea_field($data['text'])
                );
            }
        }
    }
    
    update_post_meta($post_id, '_product_testimonials', $testimonials);
}
//testimonials
