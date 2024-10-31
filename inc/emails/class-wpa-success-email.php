<?php
/**
 * Generates and sends email to subscriber
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Classes/Emails
 */	

if ( ! defined('ABSPATH') ) exit;

require_once( WPA_DIR . '/inc/abstracts/abstract-class-wpa-email.php' );

if ( ! class_exists( 'WPA_Success_Email' )  ) :

	/**
	 * Success email class
	 */		
	class WPA_Success_Email extends WPA_Email {
		
		protected $unsubscribe_key;
		
		public function __construct( array $data ) {
			parent::__construct( $data );
			$this->unsubscribe_key = $data['pass'];
		}
		
		protected function get_option_key(){
			return 'success';
		}
		
		protected function search_replace_vars( $key ) {
			$vars = parent::search_replace_vars( $key );
			
			$url = add_query_arg( 
				array( 'email' => $this->email, 'pid' => $this->product->get_id(), 'WPA_unsubscribe' => $this->unsubscribe_key ), 
				$this->product->get_permalink() 
			);
			
			$vars['{unsubscribe_url}'] = esc_url( $url );
			
			return $vars;		
		}
	}

endif;