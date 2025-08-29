<?php defined('ABSPATH') || exit; ?>
<?php $countries = new WC_Countries(); ?>

<div id="ovb-unified-checkout">
  <div class="ovb-toggle-line" style="margin:10px 0;">
    <label>
      <input type="checkbox" id="ovb_is_company" name="ovb_is_company" value="1"
        <?php echo !empty($_POST['ovb_is_company']) || WC()->checkout()->get_value('ovb_is_company') ? 'checked' : ''; ?> />
      <span><?php esc_html_e('Plaćam kao firma', 'ov-booking'); ?></span>
    </label>
  </div>

    <div class="ovb-company-fields-wrap" style="display:none;">
    <h4><?php esc_html_e('Podaci firme', 'ov-booking'); ?></h4>
    <div class="company-fields-grid">
      <?php
      woocommerce_form_field('ovb_company_name',     ['type'=>'text','label'=>__('Naziv firme','ov-booking'),        'required'=>false,'class'=>['form-row-wide'],  'custom_attributes'=>['data-required'=>'1']], '');
      woocommerce_form_field('ovb_company_country',  ['type'=>'select','label'=>__('Država','ov-booking'),            'required'=>false,'class'=>['form-row-first'], 'options'=>$countries->get_countries(),'custom_attributes'=>['data-required'=>'1']], $countries->get_base_country());
      woocommerce_form_field('ovb_company_state',    ['type'=>'text','label'=>__('Distrikt / Opština','ov-booking'),  'required'=>false,'class'=>['form-row-last'],  'custom_attributes'=>['data-required'=>'1']], '');
      woocommerce_form_field('ovb_company_city',     ['type'=>'text','label'=>__('Grad','ov-booking'),                'required'=>false,'class'=>['form-row-first'], 'custom_attributes'=>['data-required'=>'1']], '');
      woocommerce_form_field('ovb_company_postcode', ['type'=>'text','label'=>__('Poštanski broj','ov-booking'),      'required'=>false,'class'=>['form-row-last'],  'custom_attributes'=>['data-required'=>'1']], '');
      woocommerce_form_field('ovb_company_address',  ['type'=>'text','label'=>__('Adresa firme','ov-booking'),        'required'=>false,'class'=>['form-row-wide'],  'custom_attributes'=>['data-required'=>'1']], '');
      woocommerce_form_field('ovb_company_pib',      ['type'=>'text','label'=>__('PIB / poreski broj','ov-booking'),  'required'=>false,'class'=>['form-row-first'], 'custom_attributes'=>['data-required'=>'1']], '');
      woocommerce_form_field('ovb_company_mb',       ['type'=>'text','label'=>__('Matični broj (opciono)','ov-booking'),'required'=>false,'class'=>['form-row-last']], '');
      woocommerce_form_field('ovb_company_contact',  ['type'=>'text','label'=>__('Kontakt osoba u firmi','ov-booking'),'required'=>false,'class'=>['form-row-first'], 'custom_attributes'=>['data-required'=>'1']], '');
      woocommerce_form_field('ovb_company_phone',    ['type'=>'tel','label'=>__('Telefon firme','ov-booking'),        'required'=>false,'class'=>['form-row-last'],  'custom_attributes'=>['data-required'=>'1']], '');
      ?>
    </div>
  </div>

  <!-- === Druga osoba toggle === -->
  <div class="ovb-toggle-line" style="margin:18px 0 6px;">
    <label>
      <input type="checkbox" id="ovb_is_other" name="ovb_is_other" value="1"
        <?php echo !empty($_POST['ovb_is_other']) || WC()->checkout()->get_value('ovb_is_other') ? 'checked' : ''; ?> />
      <span><?php esc_html_e('Plaćam za drugu osobu', 'ov-booking'); ?></span>
    </label>
  </div>



  <div class="ovb-other-fields-wrap" style="display:none;">
    <h4><?php esc_html_e('Podaci osobe koja odseda', 'ov-booking'); ?></h4>
    <div class="other-fields-grid">
      <?php
      woocommerce_form_field('ovb_other_first_name', ['type'=>'text','label'=>__('Ime','ov-booking'),                   'required'=>false,'class'=>['form-row-first'], 'custom_attributes'=>['data-required'=>'1']], '' );
      woocommerce_form_field('ovb_other_last_name',  ['type'=>'text','label'=>__('Prezime','ov-booking'),               'required'=>false,'class'=>['form-row-last'],  'custom_attributes'=>['data-required'=>'1']], '' );
      woocommerce_form_field('ovb_other_dob',        ['type'=>'date','label'=>__('Datum rođenja','ov-booking'),         'required'=>false,'class'=>['form-row-first'], 'custom_attributes'=>['data-required'=>'1']], '' );
      woocommerce_form_field('ovb_other_email',      ['type'=>'email','label'=>__('Email','ov-booking'),                'required'=>false,'class'=>['form-row-last'],  'custom_attributes'=>['data-required'=>'1']], '' );
      woocommerce_form_field('ovb_other_country',    ['type'=>'select','label'=>__('Država','ov-booking'),              'required'=>false,'class'=>['form-row-first'], 'options'=>$countries->get_countries(),'custom_attributes'=>['data-required'=>'1']], $countries->get_base_country() );
      woocommerce_form_field('ovb_other_city',       ['type'=>'text','label'=>__('Grad','ov-booking'),                  'required'=>false,'class'=>['form-row-last'],  'custom_attributes'=>['data-required'=>'1']], '' );
      woocommerce_form_field('ovb_other_phone',      ['type'=>'tel','label'=>__('Telefon','ov-booking'),                'required'=>false,'class'=>['form-row-first'], 'custom_attributes'=>['data-required'=>'1']], '' );
      woocommerce_form_field('ovb_other_address1',   ['type'=>'text','label'=>__('Adresa','ov-booking'),                'required'=>false,'class'=>['form-row-last'],  'custom_attributes'=>['data-required'=>'1']], '' );
      woocommerce_form_field('ovb_other_address2',   ['type'=>'text','label'=>__('Apartman/sprat/jedinica (opciono)','ov-booking'),'required'=>false,'class'=>['form-row-first']], '' );
      woocommerce_form_field('ovb_other_postcode',   ['type'=>'text','label'=>__('Poštanski broj','ov-booking'),        'required'=>false,'class'=>['form-row-last'],  'custom_attributes'=>['data-required'=>'1']], '' );
      woocommerce_form_field('ovb_other_id_number',  ['type'=>'text','label'=>__('ID / broj pasoša','ov-booking'),      'required'=>false,'class'=>['form-row-first'], 'custom_attributes'=>['data-required'=>'1']], '' );
      ?>
    </div>
  </div>

  <!-- === Gosti === -->
  <!-- <div class="ovb-guests-wrap">
    <?php
    // woocommerce_form_field('ovb_guests_total', [
    //   'type'=>'number','label'=>__('Broj gostiju ukupno','ov-booking'),
    //   'required'=>false,'class'=>['form-row-wide'],'custom_attributes'=>['min'=>'1','step'=>'1']
    // ], WC()->checkout()->get_value('ovb_guests_total') ?: 1 );
    ?>
    <div id="ovb-guest-repeater"></div>
  </div> -->

</div>
