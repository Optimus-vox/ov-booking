<?php defined( 'ABSPATH' ) || exit;
 // preusmeri goste na WP login
  if ( ! is_user_logged_in() ) { wp_safe_redirect( wp_login_url( get_permalink() ) ); exit; } get_header(); 
  // trenutno aktivan endpoint
   $current_endpoint = WC()->query->get_current_endpoint(); ?> 
   <div class="ovb-myaccount-wrapper"> 
    <div class="ovb-myaccount-inner"> 
        <div class="ov-myaccount-menu"> 
            <ul class="woocommerce-MyAccount-navigation"> 
                <?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : 
                    // detektuj aktivnu stavku 
                 $is_active = ( 'dashboard' === $endpoint && ! is_wc_endpoint_url() ) || ( $endpoint === $current_endpoint ); 
                 // logout vs ostali linkovi 
                 if ( 'customer-logout' === $endpoint ) { 
                    $url = wp_logout_url( wc_get_page_permalink( 'myaccount' ) ); 
                    } else { 
                        $url = wc_get_account_endpoint_url( $endpoint ); 
                        } ?> 
                        <li class="woocommerce-MyAccount-navigation-link woocommerce-MyAccount-navigation-link--<?php echo esc_attr( $endpoint ); ?><?php echo $is_active ? ' is-active' : ''; ?>">
                             <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a> 
                        </li> <?php endforeach; ?> 
            </ul> 
        </div> 
        <div class="ovb-myaccount-content"> 
            <?php do_action( 'woocommerce_account_content' ); ?>            
        </div> 
    </div> 
</div> 
<?php get_footer(); ?>