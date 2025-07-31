<?php
defined('ABSPATH') || exit;
global $post, $product;
setup_postdata($post);
$product = wc_get_product($post->ID);
$product_id = $post->ID;
$booked = get_post_meta($product_id, '_ovb_calendar_data', true);


// --------------------------------------------------
// 1) Ako URL ima ovb_start_date i ovb_end_date → obriši iz korpe
// --------------------------------------------------
if (isset($_GET['ovb_start_date'], $_GET['ovb_end_date'])) {
    if (class_exists('WC_Cart') && WC()->cart) {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if ($cart_item['product_id'] === get_the_ID()) {
                WC()->cart->remove_cart_item($cart_item_key);
            }
        }
    }
}

// --------------------------------------------------
// 2) Preuzmi GET parametre u PHP varijable za formu
// --------------------------------------------------
$ovb_start_date = isset($_GET['ovb_start_date'])
    ? sanitize_text_field(wp_unslash($_GET['ovb_start_date']))
    : '';
$ovb_end_date = isset($_GET['ovb_end_date'])
    ? sanitize_text_field(wp_unslash($_GET['ovb_end_date']))
    : '';
$ovb_guests = isset($_GET['ovb_guests'])
    ? intval($_GET['ovb_guests'])
    : 1;

    // build a comma-separated list of all dates between start and end
$all_dates = '';
if ( $ovb_start_date && $ovb_end_date ) {
    $current = strtotime( $ovb_start_date );
    $end_ts  = strtotime( $ovb_end_date );
    $dates   = [];
    while ( $current <= $end_ts ) {
        $dates[]  = date( 'Y-m-d', $current );
        $current  = strtotime( '+1 day', $current );
    }
    $all_dates = implode( ',', $dates );
}

// Učitaj dodatne informacije o apartmanu kako bismo dobili max_guests
$additional_info = get_post_meta($post->ID, '_apartment_additional_info', true) ?: [];
$max_guests = !empty($additional_info['max_guests'])
    ? absint($additional_info['max_guests'])
    : 1;

// --------------------------------------------------
// 3) Provjera da li je trenutni proizvod već u korpi
// --------------------------------------------------
$in_cart = false;
if (WC()->cart && !WC()->cart->is_empty()) {
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (intval($cart_item['product_id']) === intval($product->get_id())) {
            $in_cart = true;
            break;
        }
    }
}

// --------------------------------------------------
// 4) Provjera da li korpa već sadrži bilo koji proizvod
// --------------------------------------------------
$cart_not_empty = WC()->cart && WC()->cart->get_cart_contents_count() > 0;

$product_title_js = esc_js(get_the_title());

get_header();
?>
<main class="single-product-wrapper">
    <div class="container">
        <?php if (have_posts()):
            while (have_posts()):
                the_post(); ?>
                <div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <h1 class="custom-product-title"><?php the_title(); ?></h1>

                    <div class="product-hero">
                        <?php if ($product && is_a($product, 'WC_Product')):
                            $main_image_id = $product->get_image_id();
                            $main_image_url = wp_get_attachment_url($main_image_id);

                            $attachment_ids = $product->get_gallery_image_ids();
                            $total_imgs = count($attachment_ids);
                            $visible_count = min(6, $total_imgs);
                            $visible_ids = array_slice($attachment_ids, 0, $visible_count);
                            ?>
                            <div class="main-product-image">
                                <div class="lightgallery">
                                    <?php if ($main_image_url): ?>
                                        <a class="hreff-wrap" href="<?php echo esc_url($main_image_url); ?>" data-index="0">
                                            <img class="gallery-main-img" alt="Main image"
                                                src="<?php echo esc_url($main_image_url); ?>" />
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="product-gallery">
                                <div class="product-gallery-grid lightgallery images-<?php echo $visible_count; ?>">
                                    <?php
                                    $i = 1;
                                    foreach ($visible_ids as $attachment_id):
                                        $image_link = wp_get_attachment_url($attachment_id);
                                        ?>
                                        <a class="hreff-wrap" href="<?php echo esc_url($image_link); ?>"
                                            data-index="<?php echo $i++; ?>">
                                            <img class="gallery-product-img" src="<?php echo esc_url($image_link); ?>"
                                                alt="Gallery image" />
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>


                            <!-- Ovo je skriveni lightgallery wrapper za LightGallery -->
                            <div id="lightgallery-all" style="display:none;">
                                <?php if ($main_image_url): ?>
                                    <a href="<?php echo esc_url($main_image_url); ?>">
                                        <img src="<?php echo esc_url($main_image_url); ?>" alt="" />
                                    </a>
                                <?php endif; ?>
                                <?php foreach ($attachment_ids as $attachment_id):
                                    $image_link = wp_get_attachment_url($attachment_id);
                                    ?>
                                    <a href="<?php echo esc_url($image_link); ?>">
                                        <img src="<?php echo esc_url($image_link); ?>" alt="" />
                                    </a>
                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>
                    </div>
                    <div id="custom-slider-modal" class="custom-modal hidden">
                        <span class="custom-modal-close">&times;</span>

                        <div class="custom-slider-main">
                            <div class="custom-slider-arrow left-arrow">&#10094;</div>

                            <img id="custom-slider-image" src="" alt="Slider image">

                            <div class="custom-slider-arrow right-arrow">&#10095;</div>
                        </div>

                        <div class="custom-slider-thumbnails">
                            <!-- Thumbnails will be injected here -->
                        </div>
                    </div>

                    <div class="product-details">
                        <div class="product-details-left">
                            <?php
                            $additional_info = get_post_meta(get_the_ID(), '_apartment_additional_info', true);
                            if (!empty($additional_info)): ?>
                                <div class="apartment-details">
                                    <div class="accomodation-details">
                                        <p class="accomodation-type">
                                            <?php echo esc_html($additional_info['accommodation_type']); ?>
                                        </p>
                                        <span>&nbsp;in&nbsp;</span>
                                        <p>
                                            <?php echo esc_html($additional_info['city']); ?>,
                                        </p>
                                        <p>
                                            &nbsp;<?php echo esc_html($additional_info['country']); ?>
                                        </p>
                                    </div>
                                    <?php if (!empty($additional_info['street_name']) || !empty($additional_info['google_maps'])): ?>
                                        <div class="location-details-section">
                                            <?php if (!empty($additional_info['street_name'])): ?>
                                                <a class="street-name" href="#map">Street:
                                                    <?php echo esc_html($additional_info['street_name']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="capacity-details">
                                        <ul>
                                            <li><?php echo absint($additional_info['max_guests']); ?> guests</li>
                                            <span>•</span>
                                            <li><?php echo absint($additional_info['bedrooms']); ?> bedrooms</li>
                                            <span>•</span>
                                            <li><?php echo absint($additional_info['beds']); ?> beds</li>
                                            <span>•</span>
                                            <li><?php echo absint($additional_info['bathrooms']); ?> baths</li>
                                        </ul>
                                    </div>

                                    <div class="the-content">
                                        <div class="custom-excerpt">
                                            <?php the_content(); ?>
                                        </div>
                                        <div class="custom-full-text" style="display: none;">
                                            <?php // echo wpautop($extra_text); 
                                                        ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="product-details-things-to-know">
                                <?php
                                $rules_ikone = get_post_meta(get_the_ID(), '_apartment_rules_icons', true);
                                // Dohvati sirovo vreme iz baze (bez esc_html!)
                                $raw_checkin_time  = !empty($additional_info['checkin_time'])  ? $additional_info['checkin_time']  : '';
                                $raw_checkout_time = !empty($additional_info['checkout_time']) ? $additional_info['checkout_time'] : '';

                                // Pretvori u timestamp za danasnji dan (safety fallback na null)
                                $checkin_timestamp  = $raw_checkin_time  ? strtotime( date('Y-m-d') . ' ' . $raw_checkin_time )  : null;
                                $checkout_timestamp = $raw_checkout_time ? strtotime( date('Y-m-d') . ' ' . $raw_checkout_time ) : null;

                                // Formatiraj prema WP settings
                                $checkin_time  = $checkin_timestamp  ? date_i18n( get_option('time_format'), $checkin_timestamp )  : '';
                                $checkout_time = $checkout_timestamp ? date_i18n( get_option('time_format'), $checkout_timestamp ) : '';

                                if (!empty($rules_ikone)): ?>
                                    <div class="apartment-rules-section">
                                        <h3>Things to know</h3>
                                        <div class="apartment-rules-icons">

                                        <div class="icon-wrapper">
                                            <div class="icon-item">
                                                     <img src="<?php echo esc_url(plugins_url('assets/images/check-in.png', dirname(__DIR__))); ?>" alt="Check-in" class="rule-icon">
                                            </div>
                                            <span class="icon-text"> <?php echo esc_html__('Check-in', 'ov-booking') . ': ' . esc_html($checkin_time); ?></span>
                                        </div>
                                        <div class="icon-wrapper">
                                            <div class="icon-item">
                                                     <img src="<?php echo esc_url(plugins_url('assets/images/check-out.png', dirname(__DIR__))); ?>" alt="Check-out" class="rule-icon">
                                            </div>
                                            <span class="icon-text"> <?php echo esc_html__('Check-out', 'ov-booking') . ': ' . esc_html($checkout_time); ?></span>
                                        </div>


                                            <?php foreach ($rules_ikone as $ikona): ?>
                                                <?php if (!empty($ikona['ikona_url']) || !empty($ikona['tekst'])): ?>
                                                    <div class="icon-wrapper">
                                                        <?php if (!empty($ikona['ikona_url'])): ?>
                                                            <div class="icon-item">
                                                                <img src="<?php echo esc_url($ikona['ikona_url']); ?>"
                                                                    alt="<?php echo esc_attr($ikona['tekst']); ?>" class="rule-icon">
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($ikona['tekst'])): ?>
                                                            <span class="icon-text"><?php echo esc_html($ikona['tekst']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="product-details-offers">
                                <?php
                                $info_ikone = get_post_meta(get_the_ID(), '_apartment_info_icons', true);
                                if (!empty($info_ikone)): ?>
                                    <div class="apartment-info-section">
                                        <h3>What this place offers</h3>
                                        <div class="apartment-info-icons">
                                            <?php foreach ($info_ikone as $ikona): ?>
                                                <?php if (!empty($ikona['ikona_url']) || !empty($ikona['tekst'])): ?>
                                                    <div class="icon-wrapper">
                                                        <?php if (!empty($ikona['ikona_url'])): ?>
                                                            <div class="icon-item">
                                                                <img src="<?php echo esc_url($ikona['ikona_url']); ?>"
                                                                    alt="<?php echo esc_attr($ikona['tekst']); ?>" class="feature-icon">
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($ikona['tekst'])): ?>
                                                            <span class="icon-text"><?php echo esc_html($ikona['tekst']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="ov-booking-calendar-section">
                                <?php
                                if ($ovb_start_date && $ovb_end_date) {
                                    $start_label = date_i18n(get_option('date_format'), strtotime($ovb_start_date));
                                    $end_label = date_i18n(get_option('date_format'), strtotime($ovb_end_date));
                                    $ts_start = strtotime($ovb_start_date);
                                    $ts_end = strtotime($ovb_end_date);
                                    $nights = max(0, ($ts_end - $ts_start) / DAY_IN_SECONDS);
                                    ?>
                                    <h3>
                                        <?php
                                        echo esc_html(
                                            sprintf(
                                                _n('%1$d night in %2$s', '%1$d nights in %2$s', $nights, 'ov-booking'),
                                                $nights,
                                                get_the_title()
                                            )
                                        );
                                        ?>
                                    </h3>
                                    <span>
                                        <?php echo esc_html($start_label . ' – ' . $end_label); ?>
                                    </span>
                                <?php } else { ?>
                                    <h3>
                                        <?php
                                        echo esc_html(
                                            sprintf(
                                                _n('Make a reservation', '%1$d nights in %2$s', 1, 'ov-booking'),
                                                1,
                                                get_the_title()
                                            )
                                        );
                                        ?>
                                    </h3>
                                    <span><?php esc_html_e('Select your dates', 'ov-booking'); ?></span>
                                <?php } ?>
                                    <script>
                                        window.ovb_product_title = "<?php echo esc_js(get_the_title()); ?>";
                                    </script>
                                <div id="ov-booking_readonly_calendar" class="ov-booking_readonly_calendar"></div>
                            </div>
                        </div> <!-- product-details-left -->

                        <div class="product-details-right">
                            <section class="custom-product-summary">

                                <div class="custom-dates">
                                    <div class="custom-price" id="ovb_total_container"></div>
                                    <?php
                                    // Ako u korpi već postoji neki proizvod:
                                    if ($cart_not_empty || $in_cart):
                                        // 1) Ako je baš ovaj proizvod u korpi:
                                        if ($in_cart): ?>
                                            <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="button go-to-cart-button">
                                                <?php esc_html_e('Go to cart', 'ov-booking'); ?>
                                            </a>
                                        <?php else: ?>
                                            <p class="already-in-cart-notice">
                                                <?php esc_html_e('You already have an item in your cart.', 'ov-booking'); ?>
                                            </p>
                                            <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="button go-to-cart-button">
                                                <?php esc_html_e('Go to cart', 'ov-booking'); ?>
                                            </a>
                                        <?php endif;
                                    else:
                                        // Korpa je prazna → prikaži standardnu formu za “Book Now”
                                        ?>
                                        <span class="stay-duration"><?php esc_html_e('Stay Duration', 'ov-booking'); ?></span>
                                        <form class="cart ov-booking-form" method="post" enctype="multipart/form-data">
                                            <div id="date-range-picker" class="daterange-picker">
                                                <!-- VIDLJIVI picker input -->
                                                <input type="text" id="custom-daterange-input" class="daterange" readonly
                                                    placeholder="<?php esc_attr_e('DD/MM/YYYY – DD/MM/YYYY', 'ov-booking'); ?>" />
                                                <!-- SKRIVENA polja koja JS popunjava -->
                                                <input type="hidden" name="start_date" id="start_date"
                                                    value="<?php echo esc_attr( $ovb_start_date ); ?>" />
                                                <input type="hidden" name="end_date"   id="end_date"
                                                    value="<?php echo esc_attr( $ovb_end_date ); ?>" />


                                            </div>

                                            <input type="hidden" name="add-to-cart"
                                                value="<?php echo esc_attr($product->get_id()); ?>" />

                                            <div class="ov-guests-select">
                                                <label for="ov-guests"><?php esc_html_e('Guests', 'ov-booking'); ?></label>
                                                <select name="guests" id="ov-guests">
                                                    <?php for ($i = 1; $i <= $max_guests; $i++): ?>
                                                        <option value="<?php echo esc_attr($i); ?>" <?php selected($ovb_guests, $i); ?>>
                                                            <?php echo esc_html($i); ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>



                                            <button type="submit" class="single_add_to_cart_button button alt ov-add-to-cart">
                                                <?php esc_html_e('Book Now', 'ov-booking'); ?>
                                            </button>
                                        </form>

                                    <?php endif; ?>
                                </div>
                            </section>
                        </div> <!-- product-details-right -->

                    </div> <!-- product-details -->
                    <?php
                    // 1) Učitaj sve testimonijale iz post meta
                    $testimonials = get_post_meta(get_the_ID(), '_product_testimonials', true);
                    if (!empty($testimonials)): ?>
                        <div class="ov-testimonials-wrapper">
                            <h3><?php esc_html_e('What our customers say', 'ov-booking'); ?></h3>

                            <!-- Owl Carousel Wrapper -->
                            <div class="owl-carousel ov-testimonials-carousel">
                                <?php foreach ($testimonials as $t):
                                    $name = sanitize_text_field($t['name']);
                                    $rating = floatval($t['rating']);
                                    $text = sanitize_textarea_field($t['text']);
                                    // Generiši zvezdice za rating
                                    $full_stars = floor($rating);
                                    $half_star = ($rating - $full_stars) >= 0.5;
                                    ?>
                                    <div class="ov-testimonial">
                                        <div class="ov-testimonial-header">
                                            <strong class="ov-testimonial-name"><?php echo esc_html($name); ?></strong>
                                            <div class="ov-testimonial-rating">
                                                <?php
                                                for ($i = 0; $i < $full_stars; $i++) {
                                                    echo '<span style="color:#8B5DFF;">★</span>';
                                                }
                                                if ($half_star) {
                                                    echo '<span class="half-star" style="color:#8B5DFF;">★</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="ov-testimonial-text">
                                            <div class="ov-testimonial-text">
                                                <span class="big-quote open">“</span>
                                                <?php echo wp_kses_post(wpautop(sanitize_textarea_field($t['text']))); ?>
                                                <span class="big-quote close">”</span>
                                            </div>

                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>


                    <?php
                    $iframe = get_post_meta(get_the_ID(), '_google_maps_iframe', true);
                    if (!empty($iframe)):
                        ?>
                        <div class="google-maps" id="map">
                            <h3>Where you will be</h3>
                            <div class="map-wrap">
                                <?php
                                echo '<div class="google-maps-iframe">';
                                echo $iframe;
                                echo '</div>';
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="other-useful-things">
                        <h3>Other useful things</h3>
                        <div class="policies">
                            <div class="safety-and-property">
                                <h4>Safety & property</h4>
                                <span class="safety-info">Carbon monoxide alarm - active</span>
                                <span class="safety-info">Smoke alarm - active</span>
                                <a class="safety-info-more" href="">Show more</a>
                            </div>
                            <div class="cancelation-policy">
                                <h4>Cancellation policy</h4>
                                <span class="policy-info">Carbon monoxide alarm - active</span>
                                <span class="policy-info">Smoke alarm - active</span>
                                <a class="policy-info-more" href="">Show more</a>
                            </div>
                        </div>
                    </div>

                <?php endwhile;
        else: ?>
                <p><?php esc_html_e('Sorry, no posts matched your criteria.', 'ov-booking'); ?></p>
            <?php endif; ?>

        </div> <!-- .container -->
</main>

<?php get_footer(); ?>