<?php
/**
 * Generates and sends email to admin
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Classes/Emails
 */	

if ( ! defined('ABSPATH') ) die;

require_once( WPA_DIR . '/inc/abstracts/abstract-class-wpa-email.php' );

if ( ! class_exists( 'WPA_Success_Admin_Email' )  ) :

	/**
	 * Success admin email class
	 */		
	class WPA_Success_Admin_Email extends WPA_Email {		
	
		/**
		 * Array of emails to send admin notification
		 *
		 * @var        array
		 * @since      1.0.0
		 * @access     protected
		 */	
		protected $admin_emails = array();
		
		public function __construct( array $data ) {
			$this->options = array (
				'subject'  => sprintf( '{shop_name}: %s', __('price alert subscription', 'wpa' ) ),
				'title'    => __('New price alert subscription!', 'wpa' ),
				'message'  => sprintf( 
					'%s!<br/>%s: "{product_name}".<br/>%s <a href="{manage_subscriptions_url}">%s</a>',
					__( 'Hello' , 'wpa' ),
					__( 'New price Alert subscription for a product' , 'wpa' ),
					__( 'Full details can be found' , 'wpa' ),
					mb_strtoupper( __( 'Here', 'wpa' ) )
				),
			);
			parent::__construct( $data );
			
			//get shop managers and admin emails
			$emails = get_users(
				array ( 
					'fields'   => array( 'user_email' ),
					'role__in' => array( 'shop_manager', 'administrator' )
				)
			);
			
			foreach( $emails as $obj ) {
				
				if ( ! is_email( $obj->user_email ) ) continue;			
				$this->admin_emails[] = $obj->user_email;
			}
		}
		
		
		protected function get_option_key() {
			return '';
		}

		
		protected function search_replace_vars( $key ) {			
			$vars = parent::search_replace_vars( $key );
			
			if ( 'message' !== $key )
				return $vars;
			
			$product_var = $this->product instanceof WC_Product ? '&s=' . $this->product->get_id() : '';
			
			return array_merge( $vars, array(
				'{manage_subscriptions_url}' => self_admin_url( 'admin.php?page=wpa-subscriptions' . $product_var )
			));			
		}		
		
		
		public function send() {
			
			if ( ! isset( $this->email ) || ! isset( $this->product ) || ! isset( $this->requested_price ) )
				return false;
						
			$to = array_filter( (array) apply_filters( 'WPA_admin_email_adresses', $this->admin_emails ), 'is_email' );

			if ( $to ) {			
				$mailer = WC()->mailer();
				return $mailer->send( implode( ',', $to ), wp_specialchars_decode( $this->get( 'subject' ) ), $this->get_message_html() );			
			} else {
				return false;
			}
		}
	}

endif;