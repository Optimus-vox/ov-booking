<?php defined('ABSPATH') || exit; ?>

<div class="ov-checkout page-checkout">
    <div class="ov-checkout-container">

        <!-- HEADER -->
        <div class="ov-checkout-header">
            <a id="ov-back-btn" onclick="history.back()" class="ov-checkout-back">
                <img src="<?php echo esc_url(plugins_url('../assets/images/arrow-left-white.png', __FILE__)); ?>" alt="arrow left white">
            </a>
            <h1 class="ov-checkout-title"><?php esc_html_e('Request to book', 'ov-booking'); ?></h1>
        </div>

        <?php
        // KRUCIJALNO: Pozovi pre form-e
        do_action('woocommerce_before_checkout_form', $checkout);
        ?>

        <!-- VAŽNO: Dodaj proper form attributes za Klarna -->
        <form name="checkout" id="checkout" method="post" class="checkout ovb-checkout-form woocommerce-checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data" novalidate>
            
            <!-- DODAJ NONCE FIELDS -->
            <?php wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce'); ?>
            
            <div class="ov-checkout-content">

                <!-- LEVA STRANA -->
                <div class="ov-checkout-steps">
                    <div class="ov-step ov-step-active">
                        <span class="ov-step-number">2.</span>
                        <span class="ov-step-label"><?php esc_html_e('Add a payment method', 'ov-booking'); ?></span>
                    </div>
                    <div class="ov-step">
                        <span class="ov-step-number">3.</span>
                        <span class="ov-step-label"><?php esc_html_e('Review your request', 'ov-booking'); ?></span>
                    </div>

                    <div class="checkout-form">
                        <div id="customer_details" class="ovb-customer-details-container">
                            <?php 
                            // BILLING FIELDS - VAŽNO za Klarna
                            do_action('woocommerce_checkout_before_customer_details'); 
                            ?>
                            
                            <div class="woocommerce-billing-fields">
                                <?php
                                $fields = $checkout->get_checkout_fields('billing');
                                foreach ($fields as $key => $field) {
                                    woocommerce_form_field($key, $field, $checkout->get_value($key));
                                }
                                ?>
                            </div>

                            <?php if ($guests > 0): ?>
                                <div id="ovb-guests-section" class="ovb-guests-section">
                                    <h4>Podaci o gostima</h4>
                                    <label class="ovb-checkbox-label" style="margin-bottom:10px;">
                                        <input type="checkbox" id="ovb-different-payer-checkbox" name="ovb_different_payer" value="1">
                                        <span>Druga osoba plaća rezervaciju?</span>
                                        <span class="ovb-help-text" style="display:block; font-size:12px; color:#b1b1b1; margin-left:28px;">
                                            (Npr. roditelj, firma, posrednik. Ako ste vi gost i plaćate, ostavite prazno.)
                                        </span>
                                    </label>
                                    <div id="ovb-guests-wrapper"></div>
                                </div>

                                <script>
                                document.addEventListener("DOMContentLoaded", function () {
                                    const guests = <?php echo intval($guests); ?>;
                                    const wrapper = document.getElementById('ovb-guests-wrapper');
                                    const payerCheckbox = document.getElementById('ovb-different-payer-checkbox');

                                    function renderGuestFields(count) {
                                        wrapper.innerHTML = '';
                                        for (let i = 1; i <= count; i++) {
                                            let phoneLabel = 'Telefon';
                                            if ((count >= 2 && i === 1) || count === 2) {
                                                phoneLabel += ' <span class="required">*</span>';
                                            }

                                            wrapper.innerHTML += `
                                                <div class="ov-guest-row">
                                                    <h3 style="margin-bottom:20px;">Gost ${i}</h3>
                                                    <div class="ov-form-group">
                                                        <label for="ovb_guest[${i}][first_name]">Ime <span class="required">*</span></label>
                                                        <input type="text" class="ov-input-regular" name="ovb_guest[${i}][first_name]" required>
                                                    </div>
                                                    <div class="ov-form-group">
                                                        <label for="ovb_guest[${i}][last_name]">Prezime <span class="required">*</span></label>
                                                        <input type="text" class="ov-input-regular" name="ovb_guest[${i}][last_name]" required>
                                                    </div>
                                                    <div class="ov-form-group">
                                                        <label for="ovb_guest[${i}][gender]">Pol <span class="required">*</span></label>
                                                        <select class="ov-select-regular" name="ovb_guest[${i}][gender]" required>
                                                            <option value="">Izaberi...</option>
                                                            <option value="male">Muški</option>
                                                            <option value="female">Ženski</option>
                                                            <option value="diverse">Drugo</option>
                                                        </select>
                                                    </div>
                                                    <div class="ov-form-group">
                                                        <label for="ovb_guest[${i}][birthdate]">Datum rođenja <span class="required">*</span></label>
                                                        <div class="ovb-date-picker-wrap">
                                                            <input type="date" class="ov-input-regular ovb-date-input" name="ovb_guest[${i}][birthdate]" required>
                                                            <span class="ovb-date-calendar-icon"></span>
                                                        </div>
                                                    </div>
                                                    <div class="ov-form-group">
                                                        <label for="ovb_guest[${i}][phone]">${phoneLabel}</label>
                                                        <input type="text" class="ov-input-regular ovb-guest-phone" name="ovb_guest[${i}][phone]" data-guest-idx="${i}" autocomplete="tel">
                                                    </div>
                                                    <div class="ov-form-group">
                                                        <label for="ovb_guest[${i}][id_number]">Broj pasoša/lične karte (opciono)</label>
                                                        <input type="text" class="ov-input-regular" name="ovb_guest[${i}][id_number]">
                                                    </div>
                                                    <div class="ov-form-group" style="margin-top:6px;">
                                                        <label for="is_child_${i}" style="display:inline;">Gost je dete (ispod 18)</label>
                                                        <input type="checkbox" name="ovb_guest[${i}][is_child]" value="1" id="is_child_${i}">
                                                    </div>
                                                </div>
                                            `;
                                        }
                                    }

                                    function updateGuests() {
                                        const isDifferentPayer = payerCheckbox && payerCheckbox.checked;
                                        renderGuestFields(isDifferentPayer ? guests : Math.max(guests - 1, 0));
                                        
                                        // VAŽNO: Trigger checkout update after guest changes
                                        if (typeof jQuery !== 'undefined') {
                                            jQuery('body').trigger('update_checkout');
                                        }
                                    }

                                    if (payerCheckbox) {
                                        payerCheckbox.addEventListener('change', updateGuests);
                                    }
                                    updateGuests();
                                });
                                </script>
                            <?php endif; ?>

                            <?php 
                            // SHIPPING FIELDS (ako su potrebni)
                            if (WC()->cart->needs_shipping_address()) {
                                ?>
                                <div class="woocommerce-shipping-fields">
                                    <?php
                                    $fields = $checkout->get_checkout_fields('shipping');
                                    foreach ($fields as $key => $field) {
                                        woocommerce_form_field($key, $field, $checkout->get_value($key));
                                    }
                                    ?>
                                </div>
                                <?php
                            }
                            do_action('woocommerce_checkout_after_customer_details'); 
                            ?>

                        </div> <!-- /.ovb-customer-details-container -->
                    </div> <!-- /.checkout-form -->
                </div> <!-- /.ov-checkout-steps -->

                <!-- DESNA STRANA: Order Summary -->
                <div class="ov-checkout-summary">
                    <div class="ov-summary-card">
                        
                        <?php
                        if (isset($product) && $product) {
                            $image_id = $product->get_image_id();
                            if ($image_id) {
                                echo '<div class="ovb-product-image">';
                                echo wp_get_attachment_image($image_id, 'medium');
                                echo '</div>';
                            }
                        }
                        ?>
                        
                        <?php if (isset($start_label, $end_label, $nights, $guests)): ?>
                        <div class="ovb-trip-details-summary">
                            <h4 class="ovb-trip-details-title"><?php esc_html_e('Trip details', 'ov-booking'); ?></h4>
                            <div class="ovb-trip-details">
                                <div class="ovb-trip-detail-item">
                                    <div class="trip-details-stay">
                                        <span class="ovb-trip-icon dashicons dashicons-calendar-alt"></span>
                                        <span class="ovb-trip-detail-text">
                                            <?php echo esc_html($start_label . ' – ' . $end_label); ?>
                                        </span>
                                    </div>
                                    <div class="trip-details-accommodation-details">
                                        <div class="ovb-trip-detail-nights">
                                            <span class="ovb-trip-icon dashicons dashicons-admin-home"></span>
                                            <span class="ovb-trip-detail-text">
                                                <?php echo esc_html($nights . ' ' . _n('night', 'nights', $nights, 'ov-booking')); ?>
                                            </span>
                                        </div>
                                        <div class="ovb-trip-detail-guests">
                                            <span class="ovb-trip-icon dashicons dashicons-groups"></span>
                                            <span class="ovb-trip-detail-text">
                                                <?php echo esc_html($guests . ' ' . _n('guest', 'guests', $guests, 'ov-booking')); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ORDER REVIEW I PAYMENT -->
                        <?php 
                        // KRUCIJALNO: Ovo mora biti pre order review tabele
                        do_action('woocommerce_checkout_before_order_review'); 
                        ?>
                        
                        <div id="order_review" class="woocommerce-checkout-review-order">
                            <!-- ORDER SUMMARY TABELA -->
                            <?php wc_get_template('checkout/review-order.php', array('checkout' => $checkout)); ?>

                            <!-- PAYMENT METHODS - KLARNA TREBA OVO -->
                            <?php if (WC()->cart->needs_payment()) : ?>
                                <div id="payment" class="woocommerce-checkout-payment">
                                    <?php 
                                    do_action('woocommerce_review_order_before_payment');
                                    
                                    $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
                                    WC()->payment_gateways()->set_current_gateway($available_gateways);
                                    
                                    if (!empty($available_gateways)) {
                                        ?>
                                        <ul class="wc_payment_methods payment_methods methods">
                                            <?php
                                            foreach ($available_gateways as $gateway) {
                                                wc_get_template('checkout/payment-method.php', array('gateway' => $gateway, 'checkout' => $checkout));
                                            }
                                            ?>
                                        </ul>
                                        <?php
                                    } else {
                                        echo '<p>' . __('Sorry, it seems that there are no available payment methods for your location. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce') . '</p>';
                                    }
                                    
                                    do_action('woocommerce_review_order_after_payment');
                                    ?>
                                    
                                    <div class="form-row place-order">
                                        <noscript>
                                            <?php esc_html_e('Since your browser does not support JavaScript, or it is disabled, please ensure you click the <em>Update Totals</em> button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce'); ?>
                                            <br/><button type="submit" class="button alt" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e('Update totals', 'woocommerce'); ?>"><?php esc_html_e('Update totals', 'woocommerce'); ?></button>
                                        </noscript>

                                        <?php wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce'); ?>
                                        <?php wp_nonce_field('woocommerce_update_order_review', '_wpnonce'); ?>
                                        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr(wp_unslash($_SERVER['REQUEST_URI'])); ?>" />

                                        <button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="<?php esc_attr_e('Place order', 'woocommerce'); ?>" data-value="<?php esc_attr_e('Place order', 'woocommerce'); ?>"><?php esc_html_e('Place order', 'woocommerce'); ?></button>

                                        <?php do_action('woocommerce_review_order_after_submit'); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div> <!-- /.ov-checkout-summary -->

            </div> <!-- /.ov-checkout-content -->

        </form>
        
        <?php
        // KRUCIJALNO: Pozovi posle form-e - OVO INICIJALIZUJE CHECKOUT JS
        do_action('woocommerce_after_checkout_form', $checkout);
        ?>

    </div> <!-- /.ov-checkout-container -->
</div>

<script>
jQuery(document).ready(function($) {
    // Debugging za Klarna
    console.log('Checkout initialized');
    
    // Check for Klarna
    setTimeout(function() {
        var klarnaElements = $('.payment_method_klarna_payments');
        console.log('Klarna elements found:', klarnaElements.length);
        
        if (klarnaElements.length === 0) {
            console.log('Klarna not found. Available payment methods:');
            $('.payment_methods li').each(function() {
                console.log('- ' + $(this).attr('class'));
            });
        }
    }, 2000);
    
    // Force update checkout on country/postcode change for Klarna
    $(document).on('change', '#billing_country, #billing_postcode', function() {
        console.log('Country/postcode changed, updating checkout for Klarna');
        $('body').trigger('update_checkout');
    });
});
</script>