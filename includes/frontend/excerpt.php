<?php
defined('ABSPATH') || exit;
require_once dirname(__DIR__) . '/helpers/logger.php';


    // function custom_excerpt_read_more($content, $limit = 150, $read_more_text = 'Read more', $read_more_link = '') {
    //     $text = wp_strip_all_tags($content);
        
    //     if (mb_strlen($text, 'UTF-8') <= $limit) {
    //         return $text;
    //     }
    
    //     $trimmed_text = mb_substr($text, 0, $limit, 'UTF-8');
    //     $last_space = mb_strrpos($trimmed_text, ' ', 0, 'UTF-8');
    //     $excerpt = mb_substr($trimmed_text, 0, $last_space, 'UTF-8');
        
    //     $excerpt .= '...';
        
    //     if ($read_more_link) {
    //         $excerpt .= ' <a href="'.esc_url($read_more_link).'" class="read-more">'.esc_html($read_more_text).'</a>';
    //     }
        
    //     return $excerpt;
    // }
    
    // add_filter('the_excerpt', 'custom_excerpt_read_more');    



    // TODO: FIX and add custom length 



  
// Skrati sadržaj na 300 karaktera i dodaj dugme "Show more"
function ovb_trimmed_content_with_show_more($content) {
    if (is_singular('product')) {
        $text = wp_strip_all_tags($content);
        $limit = 300;

        // Ako je sadržaj kraći od limita
        if (mb_strlen($text) <= $limit) return '<div class="ov-excerpt-full">' . $content . '</div>';

        // Skrati bez sečenja reči
        $trimmed = mb_substr($text, 0, $limit);
        $last_dot = mb_strrpos($trimmed, '.');

        if ($last_dot !== false) {
            $excerpt = mb_substr($trimmed, 0, $last_dot + 1);
        } else {
            $last_space = mb_strrpos($trimmed, ' ');
            $excerpt = mb_substr($trimmed, 0, $last_space ?: $limit);
        }

        // $output  = '<div class="ov-excerpt-preview">';
        // $output .= '<p>' . esc_html($excerpt) . '</p>';
        // $output .= '<button class="ov-show-more-button">Show more</button>';
        // $output .= '</div>';

        // $output .= '<div class="ov-excerpt-full" style="display:none;">' . $content . '</div>';


        $output  = '<div class="ov-excerpt-preview">';
$output .= '<p>' . esc_html($excerpt) . '</p>';
$output .= '<button class="ov-show-more-button">Show more</button>';
$output .= '</div>';

$output .= '<div class="ov-excerpt-full" style="display:none;">' . $content;
$output .= '<br><button class="ov-collapse-button">Show less</button>';
$output .= '</div>';

        return $output;
    }

    return $content;
}
add_filter('the_content', 'ovb_trimmed_content_with_show_more');

// JS koji otvara ceo tekst
add_action('wp_footer', function() {
    if (!is_singular('product')) return;
    ?>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const showMoreBtn = document.querySelector(".ov-show-more-button");
            const collapseBtn = document.querySelector(".ov-collapse-button");
            const preview = document.querySelector(".ov-excerpt-preview");
            const full = document.querySelector(".ov-excerpt-full");

            if (showMoreBtn && collapseBtn && preview && full) {
                showMoreBtn.addEventListener("click", function() {
                    preview.style.display = "none";
                    full.style.display = "block";
                });

                collapseBtn.addEventListener("click", function() {
                    full.style.display = "none";
                    preview.style.display = "block";
                });
            }
        });
    </script>

<style>
    .ov-show-more-button,
    .ov-collapse-button {
        background-color: #0073aa;
        color: white;
        border: none;
        padding: 8px 16px;
        margin-top: 10px;
        cursor: pointer;
    }
    .ov-show-more-button:hover,
    .ov-collapse-button:hover {
        background-color: #005a87;
    }
</style>

    <?php
});