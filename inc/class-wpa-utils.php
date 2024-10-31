<?php
/**
 * Various utilities and helper methods
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Classes
 */

if ( ! defined('ABSPATH') ) die;

if ( ! class_exists( 'WPA_Utils' ) ) { 
 
	/**
	 * Utilities class
	 */
	class WPA_Utils {

		/**
		 * Get default settings value if $key is specified or array of all default values if not.
		 * If $key is specified but not exists it will reurn false
		 *
		 * @since 1.0.0		 
		 * @param string $key
		 * @return mixed
		 */
		public static function settings_def( $key = '' ) {
			
			if ( ! is_string( $key ) ) return false;
			
			$defaults = array(
				//main
				'WPA_visible_for'           => 'all', 
				'WPA_hide_out_of_stock'     => 'yes',
				'WPA_form_position'         => 'no',
				'WPA_form_position_shop'    => 'no',				
				'WPA_force_pass'            => 'yes',
				'WPA_uninstall_cleanup'     => 'no',
				//labels
				'WPA_form' => array(
					'title'                 => __( 'Price Alert', 'wpa' ),
					'Alert_Label'           => __( 'Price Alert', 'woo_alert' ),
					'lebel_show'            => 'yes',
					'email_title'           => __( 'Email address', 'wpa' ),
					'email_placeholder'     => __( 'user@domain.com', 'wpa' ),
					'price_title'           => __( 'Email me if the price is less or equals:', 'wpa' ),	
					'pass_title'            => __( 'Password (mandatory for registered users)', 'wpa' ),
					'variations_title'      => __( 'Select variation you like to track:', 'wpa' ),
					'variations_def_option' => __( 'Select variation', 'wpa' ),
					'privacy_title'         => sprintf( 
						/* translators: %s: privacy policy */
						__( 'Your personal data will be used according to our %s', 'wpa' ),
						sprintf( '<a href="{terms_url}" target="_blank">%s</a>', __( 'privacy policy', 'wpa') )
					),
					'submit_label'          => __( 'Price Alert', 'wpa' ),					
				),
				'WPA_recaptcha' => array(
					'enabled'       => 'no',
					'site_key'      => '',
					'secret_key'    => '',	
				),				
				//messages
				'WPA_messages' => array(
					'success'       => __( 'You are successfully subscribed. We email to you when product price will match to your request!', 'wpa' ),
					'updated'       => __( 'Your subscription succesfully updated!', 'wpa' ),		
					'invalid_email' => __( 'Please enter valid email address', 'wpa' ),
					'invalid_pass'  => __( 'Entered password is invalid!', 'wpa' ),		
					/* translators: %s: current price */
					'invalid_price' => sprintf( __( 'Please enter valid price: positive integer lower than %s', 'wpa' ), '{current_price}' ),
					'unsubscribe_success' => __( 'You are successfully unsubscribed!', 'wpa' ),
					'unsubscribe_error'   => __( 'Unsubscribe error - subscription not found!', 'wpa' ),
				),
				'WPA_emailing_limit' 		     => '10',
				'WPA_sent_subscriptions_lifetime' => '28',
				'WPA_success_notify_admin'        => 'yes',
				
				//email success
				'WPA_success_email' => array(
					/* translators: %s: name of the product */
					'title'    => sprintf( __( 'Price Alert subscription: %s product', 'wpa' ), '"{product_name}"' ),
					/* translators: %s: shop name */
					'subject'  => sprintf( __('%s: price alert', 'wpa' ), '{shop_name}' ),
					'message'  => sprintf( 
						'%1$s<br />%2$s %3$s<br />%4$s',				
						/* translators: %s: user disaplay name */
						sprintf( __( 'Hello %s!', 'wpa' ), '{user_name}' ),
						/* translators: %s: name of the product */
						sprintf( __( 'You are successfully subscribed to %s product price alert.', 'wpa' ), '"{product_name}"' ),
						/* translators: %s: expected price */
						sprintf( __( 'We will notify you when product price will be reduced to %s or less.', 'wpa' ), '{expected_price}' ),
						/* translators: %s: unsubscribe link */
						sprintf( __( 'To cancel subscription follow this %s.', 'wpa' ), sprintf( '<a href="{unsubscribe_url}">%s</a>', __( 'LINK', 'wpa' ) ) )				
					),
				),
				
				//email cheapening
				'WPA_cheapening_email' => array(
					/* translators: %s: name of the product */
					'title'   => sprintf( __( '%s product price reduced!', 'wpa' ), '"{product_name}"' ),
					/* translators: %s: shop name */
					'subject' => sprintf( __('%s: price alert', 'wpa' ), '{shop_name}' ),
					'message' => sprintf( 
						'%1$s<br />%2$s %3$s<br />%4$s',				
						/* translators: %s: user disaplay name */
						sprintf( __( 'Hello %s!', 'wpa' ), '{user_name}' ),	
						/* translators: 1: product link 2: current price */
						sprintf( __( 'Price of %1$s product is reduced to %2$s.', 'wpa' ), '<a href="{product_url}">"{product_name}"</a>', '{current_price}' ),	
						sprintf( 
							/* translators: %s: add to cart link */
							__( 'Hurry to %s because it may be a temporary offer!', 'wpa' ), 		
							sprintf( 
								'<a href="{cart_url}">%s</a>', mb_strtoupper( __( 'add this product to cart', 'wpa' ) )
							) 
						),
						/* translators: %s: resubscribe link */
						sprintf( __( 'To renew subscription follow this %s', 'wpa' ), sprintf( '<a href="{resubscribe_url}">%s</a>', __( 'LINK', 'wpa' ) ) )				
					),
				),
			);				

			if ( empty( $key ) )
				return $defaults;
			
			if ( isset( $defaults[ $key ] ) )
				return $defaults[ $key ];
			
			if ( strstr( $key, '[' ) ) {
				parse_str( $key, $key_array );
				$key  = current( array_keys( $key_array ) );
				$sub_key = key( $key_array[ $key ] );
				if ( isset( $defaults[ $key ][ $sub_key ] ) )
					return $defaults[ $key ][ $sub_key ];
			}

			return false;
		}

		/**
		 * Wrapper for get_option in conjunction with default settings
		 *
		 * @since 1.0.0		 
		 * @param string   $key
		 * @param bool     $force_def
		 * @return mixed
		 */		
		public static function get_option( $key, $force_def = true ) {
			
			if ( ! is_string( $key ) || ! is_bool( $force_def ) )
				return '';
			
			$key = ( 0 !== strpos( $key, 'WPA_' ) ) ?  'WPA_' . $key : $key;			
			$def = $force_def ? self::settings_def( $key ) : '';
			$value = WC_Admin_Settings::get_option( $key, $def );
			
			if ( is_array( $def ) ) {				
				foreach( $def as $k => $v ) {
					$def[ $k ] = ( isset( $value[ $k ] ) && is_scalar( $value[ $k ] ) && '' !== $value[ $k ] ) ? $value[ $k ] : $v;
				}
				return $def;
			}
			
			return ( '' === $value && $force_def && $def ) ? $def : $value;
		}

		/**
		 * Loads appropriate class and sends email
		 *
		 * @since 1.0.0		 
		 * @param array   $data
		 * @param string  $type
		 * @return bool
		 */			
		public static function send_email( array $data, $type = 'cheapening' ) {
			$class = 'WPA_' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $type ) ) ) . '_Email';
			
			if ( ! class_exists( $class ) ) {
				if ( file_exists( WPA_DIR . '/inc/emails/class-wpa-' . $type . '-email.php' ) ) { //core emails
					require( WPA_DIR . '/inc/emails/class-wpa-' . $type . '-email.php' );
				} else { //allow extensions				
					$file = apply_filters( 'WPA_email_class_file', false, $type );
					if ( is_string( $file ) && file_exists( $file ) )
						require( $file );
				}			
			}
			
			if ( class_exists( $class ) ) {
				$email = new $class( $data );
				if ( is_callable( array( $email, 'send' ) ) )	
					return $email->send();
			}
			
			return false;	
		}

		/**
		 * Get html of subscribe form
		 *
		 * @since 1.0.0
		 * @param int    $product_id
		 * @return string
		 */			
		public static function get_form( $product_id ) {
			$product = wc_get_product( (int) $product_id );
			if ( $product instanceof WC_Product && class_exists( 'WPA_Subscribe_Form' ) ) {
				$form = new WPA_Subscribe_Form( $product );
				$form->hookup();
				ob_start();
				do_action( 'WPA_shortcode', $form );
				return ob_get_clean();
			}
			return '';
		}

		/**
		 * Render subscribe form
		 *
		 * @since 1.0.0
		 * @param int    $product_id
		 * @return void
		 */	
		public static function form( $product_id ) {
			echo self::get_form( (int) $product_id );
		}

        /**
		 * Validate positive integer
		 *
		 * @since 0.1.2
		 * @param mixed  $value
		 * @return bool
		 */	
		public static function is_absint( $value ) {	
            return (bool) filter_var( $value, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
		}
	}
}
