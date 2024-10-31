<?php
/**
 * Class responsible for plugin loading
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Classes
 */

if ( ! defined('ABSPATH') ) die;

if ( ! class_exists( 'WPA_Loader' ) ) { 
 
	/**
	 * Plugin loader class
	 */
	class WPA_Loader implements WPA_Object {		

		/**
		 * Whether to do full plugin loading or not
		 *
		 * @var        bool
		 * @since      1.0.0
		 * @access     private
		 */		
		private $do_loading;	
				
		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 */		
		public function __construct() {
			$this->do_loading = true;
			$this->maybe_upgarade();
		}

		/**
		 * Allow to get private and protected props
		 *
		 * @since 1.0.0		 
		 * @param string $prop
		 * @return mixed
		 */			
		public function __get( $prop ) {			
			if ( is_string( $prop ) && property_exists( $this, $prop ) )
				return $this->{$prop};
			
			return null;
		}
		
		/**
		 * Maybe run upgrade if version bumped
		 *
		 * @since      0.1.1
		 * @return     void
		 */		
		private function maybe_upgarade() {			
			$upgrade = false;
			$db_version = get_option( 'WPA_VERSION', false );
			if ( WPA_VERSION !== $db_version ) {
				$upgrade = true;
				update_option( 'WPA_VERSION', WPA_VERSION, true );
			}
		}		
	
		/**
		 * Load plugin textdomain
		 *
		 * @since      1.0.0
		 * @return     void
		 */		
		public function load_textdomain() {
			
			add_filter( 'plugin_locale', array( $this, 'determine_locale' ), 10, 2 );
			
			if ( ! load_plugin_textdomain( 'wpa', false, '/languages/' ) ) {

				if ( function_exists( 'determine_locale' ) ) {
					$locale = determine_locale();
				} elseif ( is_admin() && function_exists( 'get_user_locale' ) ) {
					$locale = get_user_locale();
				} else {
					$locale = get_locale();
				}
				
				$locale = apply_filters( 'plugin_locale', $locale, 'wpa' );
				$mofile = WPA_DIR . '/languages/wpa-' . $locale . '.mo';
				load_textdomain( 'wpa', $mofile );
			}

			remove_filter( 'plugin_locale', array( $this, 'determine_locale' ), 10, 2 );
		}

		/**
		 * Load correct locale for ajax requests from frontend
		 *
		 * @since 1.0.0
		 * @param string $locale
		 * @param string $domain
		 * @return string
		 */		
		public function determine_locale( $locale, $domain ) {
			
			if ( 'wpa' !== $domain )
				return $locale;
			
			$ref = wp_get_raw_referer();			
			if ( $ref && defined( 'DOING_AJAX' ) && DOING_AJAX && 0 !== strpos( $ref, admin_url() ) ) {
				$locale = get_locale();
			}
			
			return $locale;
		}		

		/**
		 * Displays notification if minimum requirements are not met.
		 *
		 * @since      1.0.0
		 * @return     void
		 */		
		public function admin_notices() {
			$msg = '';
			
			if ( version_compare( phpversion(), '5.5', '<' ) )
				$msg .= __( 'Price Alert for WooCommerce plugin requires PHP 5.5 or greater. Ask your host about PHP upgrade.', 'wpa' );
			
			if ( version_compare( get_bloginfo( 'version' ), '4.4', '<' ) )				
				$msg .= ' ' . __( 'Price Alert for WooCommerce plugin requires WordPress 4.4 or greater.', 'wpa' );	
			
			if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) && ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) )				
				$msg .= ' ' . __( 'Price Alert for WooCommerce plugin requires WooCommerce plugin to be installed and activated.', 'wpa' );
			
			$msg = trim( $msg );
			
			if ( $msg )	
				printf( '<div class="error"><p>%s</p></div>', $msg );
		}
		
		/**
		 * Hookup to wp
		 *
		 * @since      1.0.0
		 * @return     void
		 */	
		public function hookup() {
			
			require_once( ABSPATH.'wp-admin/includes/plugin.php' );
			
			add_action( 'init', array( $this, 'load_textdomain' ), 1 );
			
			if ( version_compare( get_bloginfo( 'version' ), '4.4', '<' ) || version_compare( phpversion(), '5.5', '<' ) || ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) && ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) ) {
				
				$this->do_loading = false;
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
				
			} else {				
				require_once( WPA_DIR . '/inc/class-wpa-utils.php' );
				require_once( WPA_DIR . '/inc/class-wpa-subscribe-form.php' );
				
				require_once( WPA_DIR . '/inc/class-wpa-background-emailer.php' );
				wpa()->add( 'emailer', new WPA_Background_Emailer() );				
				
				require_once WPA_DIR . '/inc/class-wpa-shared.php';
				wpa()->add( 'shared', new WPA_Shared() );
				
				if ( is_admin() ) {					
					require_once WPA_DIR . '/inc/class-wpa-admin.php';
					wpa()->add( 'admin', new WPA_Admin() );
					
					require_once WPA_DIR . '/inc/class-wpa-privacy.php';
					wpa()->add( 'privacy', new WPA_Privacy() );				
				} else {
					require_once WPA_DIR . '/inc/class-wpa-public.php';
					wpa()->add( 'public', new WPA_Public() );
				}			
			}			
		}		
	}
}
