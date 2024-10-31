<?php
/**
 * Generates and sends email to user
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Classes/Emails
 */	

if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists( 'WPA_Email' )  ) :

	/**
	 * Email class
	 */		
	abstract class WPA_Email {
		
		/**
		 * Product
		 *
		 * @since      1.0.0		 
		 * @var WC_Product
		 * @access protected
		 */			
		protected $product;
		
		/**
		 * User requested price
		 *
		 * @since      1.0.0
		 * @var integer
		 * @access protected
		 */			
		protected $requested_price;
		
		/**
		 * Email
		 *
		 * @since      1.0.0
		 * @var string
		 * @access protected
		 */			
		protected $email;
		
		/**
		 * User name (equals email for non-customers )
		 *
		 * @since      1.0.0
		 * @var string
		 * @access protected
		 */			
		protected $user_name;

		/**
		 * Shop name
		 *
		 * @since      1.0.0
		 * @var string
		 * @access protected
		 */			
		protected $shop_name;

		/**
		 * Email options
		 *
		 * @since      1.0.0
		 * @var array
		 * @access protected
		 */			
		protected $options;		
	
		/**
		 * Constructor.
		 *
		 * @since      1.0.0
		 */		
		public function __construct( array $data ) {
			$defaults = array (
				'product_id' => 0,
				'price'      => null,
				'email'      => null
			);			
			$data = wp_parse_args( $data, $defaults );
			
			//allow child classes override this
			if ( ! isset( $this->options ) || ! is_array( $this->options ) ) {
				$option_key = $this->get_option_key();
				$this->options = WPA_Utils::get_option( $option_key . '_email' );
			}
			
			$this->requested_price = is_numeric( $data['price'] ) ? (int) $data['price'] : null;
			$this->email = is_email( $data['email'] ) ? $data['email'] : null;			
			$product = wc_get_product( (int) $data['product_id'] );
			
			if ( $product instanceof WC_Product && ! $product->is_type('variable') && ! $product->is_type('grouped') ) {
				$this->product = $product;
				
				$user = get_user_by( 'email', $this->email );
				$this->user_name = $user instanceof WP_User ? $user->get( 'display_name' ) : $this->email;					
			
				$shop_name = wp_specialchars_decode( get_option( 'blogname', '' ), ENT_QUOTES );
				$this->shop_name = empty( $shop_name ) ? home_url() : $shop_name;			
			}			
		}
		
		/**
		 * A key to autogenerate database option key. See __construct() for details
		 *
		 * @since  1.0.0
		 * @return string
		 */			
		abstract protected function get_option_key();

		/**
		 * Generates and returns email html
		 *
		 * @since  1.0.0
		 * @return string
		 */			
		protected function get_message_html() {
			ob_start();
			
			$message = wp_kses_post( trim( $this->get( 'message' ) ) );
			$message = preg_replace( '!^<p>(.*?)</p>$!i', '$1', $message );
			
			if ( function_exists('wc_get_template') ) {
				wc_get_template( 'emails/email-header.php', array( 'email_heading' => $this->get( 'title' ) ) );
				printf( '<p>%s</p>', $message );
				wc_get_template( 'emails/email-footer.php' );
			} else {
				woocommerce_get_template( 'emails/email-header.php', array( 'email_heading' => $this->get( 'title' ) ) );
				printf( '<p>%s</p>', $message );
				woocommerce_get_template('emails/email-footer.php');
			}
			
			return ob_get_clean();
		}
	
		/**
		 * This method replace placeholders in dabase stored options and returns result
		 *
		 * @since      1.0.0
		 * @param  string  $key  possible values 'subject', 'message', 'title'
		 * @return string
		 */			
		protected function get( $key ) {
			if ( ! is_string( $key ) || is_null( $this->product ) || ! isset( $this->options[ $key ] ) || empty( $this->options[ $key ] ) )
				return '';
			
			$search_replace = $this->search_replace_vars( $key );
					
			return str_replace( 
				array_keys( $search_replace ), 
				array_values( $search_replace ), 
				$this->options[ $key ] 
			);
		}	
		
		/**
		 * This method generates array for replacement. See $this->get( $key ) method
		 *
		 * @since      1.0.0
		 * @param  string  $key  possible values 'subject', 'message', 'title'
		 * @return array
		 */			
		protected function search_replace_vars( $key ) {			
			if ( ! in_array( $key, ['subject', 'title', 'message'] ) )
				return array();
			
			$return = array(
				'{shop_name}'       => $this->shop_name, 
				'{user_name}'       => esc_html( $this->user_name ), 
				'{product_name}'    => mb_strtoupper( esc_html( $this->product->get_name() ) )
			);			
			
			if ( 'subject' === $key || 'title' === $key )
				return $return;
			
			return array_merge( $return, array (
					'{product_url}'     => esc_url( $this->product->get_permalink() ),
					'{current_price}'   => wc_price( $this->product->get_price() ),
					'{expected_price}'  => wc_price( $this->requested_price )
			));			
		}		

		/**
		 * Sends email using woocommerce emailer class
		 *
		 * @since      1.0.0
		 * @param  string  $key  possible values 'subject', 'message', 'title'
		 * @return array
		 */			
		public function send() {
			if ( ! isset( $this->email ) || ! isset( $this->product ) || ! isset( $this->requested_price ) )
				return false;
			
			$mailer = WC()->mailer();
			return $mailer->send( $this->email, wp_specialchars_decode( $this->get( 'subject' ) ), $this->get_message_html() );			
		}
	}

endif;