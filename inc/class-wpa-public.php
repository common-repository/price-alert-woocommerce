<?php
/**
 * Subscibe and unsubscribe forms controller
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Public/Classes
 */

if ( ! defined('ABSPATH') ) die;

if ( ! class_exists( 'WPA_Public' ) ) {

	/**
	 * Forms class
	 */
	class WPA_Public implements WPA_Object {

		/**
		 * Wether do plugin loading or not
		 *
		 * @var        sting
		 * @since      1.0.0
		 * @access     protected
		 */
		protected $unsubscribe_popup;

		/**
		 * Hookup to wp
		 *
		 * @since      1.0.0
		 * @return     void
		 */
		public function hookup() {
			add_action( 'wp', array( $this, 'load_subscribe_form' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
			add_action( 'woocommerce_shop_loop', array( $this, 'shop_loop' ), 20 );
			add_shortcode( 'WPA_form', array( $this, 'form_shortcode' ) );
		}

		/**
		 * Loads form on single product pages
		 *
		 * @since      1.0.0
		 * @return     void
		 */
		public function load_subscribe_form() {

			if ( is_singular('product') && is_main_query() && 'no' !== WPA_Utils::get_option( 'form_position' ) ) {
				$product = wc_get_product( get_the_id() );
				if ( $product instanceof WC_Product ) {
					$form = new WPA_Subscribe_Form( $product, false );
					$form->hookup();
				}
			}

			if ( is_singular('product') && is_main_query() && isset( $_GET['WPA_unsubscribe'], $_GET['pid'], $_GET['email'] ) && WPA_Utils::is_absint( $_GET['pid'] ) && is_email( $_GET['email'] ) && is_string( $_GET['WPA_unsubscribe'] ) ) {

				global $wpdb;
				$table_name = $wpdb->prefix . 'WPA_subscriptions';
				$product = wc_get_product( get_the_id() );
				if ( $product->is_type( 'variable' ) ) {
					$check = in_array( (int) $_GET['pid'], $product->get_visible_children() );
				} else {
					$check = ( (int) $_GET['pid'] === get_the_id() );
				}

				if ( $check ) {
					$hash = $wpdb->get_var( $wpdb->prepare(
						"SELECT hash FROM $table_name WHERE product_id = %d AND email = %s AND status != 'sent'", (int) $_GET['pid'], $_GET['email']
					));

					if ( $hash && wp_check_password( $_GET['WPA_unsubscribe'], $hash ) ) {
						$r = $wpdb->query( $wpdb->prepare(
							"DELETE FROM $table_name WHERE product_id = %d AND email = %s AND status != 'sent'", (int) $_GET['pid'], $_GET['email']
						));
						$this->unsubscribe_popup = ( $r ) ? 'unsubscribe_success' : $this->unsubscribe_popup;
					} else {
						$this->unsubscribe_popup = 'unsubscribe_error';
					}
				} else {
					$this->unsubscribe_popup = 'unsubscribe_error';
				}

				add_action( 'wp_footer', array( $this, 'unsubscribe_popup_html' ) );
			}
		}

		/**
		 * Detect where whe should render trigger links and hooks to appropriate filters
		 *
		 * @since      1.0.0
		 * @return     void
		 */
		public function shop_loop() {

			$pos = WPA_Utils::get_option( 'WPA_form_position_shop' );
			if ( 'no' === $pos ) return;

			$priority = false !== strpos( $pos, 'before_' ) ? 99 : 1;
			add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'render_form' ), $priority, 2 );
		}

		/**
		 * Adds supscription form in products archives
		 *
		 * @since      1.0.0
		 * @return     void
		 */
		public function render_form( $html, $product ) {

			$priority = false !== strpos( WPA_Utils::get_option( 'WPA_form_position_shop' ), 'before_' ) ? 99 : 1;
			remove_filter( current_filter(), array( $this, 'render_form' ), $priority, 2 );

			$form_html = WPA_Utils::get_form( $product->get_id() );
			if ( ! $form_html ) return $html;

			if ( 99 === $priority )
				$html = sprintf( '<div class="wpa-inloop">%s</div>%s', $form_html, $html );
			else
				$html = sprintf( '%s<div class="wpa-inloop">%s</div>', $html, $form_html );

			return $html;
		}

		/**
		 * Registers scripts and styles
		 *
		 * @since      1.0.0
		 * @return     void
		 */
		public function register_assets() {
			wp_register_style( 'wpa', WPA_URL . '/assets/css/wpa.css', array(), WPA_VERSION );
			$this->maybe_add_inline_styles();
			wp_register_script( 'wpa', WPA_URL . '/assets/js/wpa.js', array( 'jquery' ), WPA_VERSION, true );
			wp_register_script( 'WPA_recaptcha2', '//www.google.com/recaptcha/api.js', array(), '', true );
			if ( in_array( $this->unsubscribe_popup, [ 'unsubscribe_success', 'unsubscribe_error' ] ) ) {
				wp_enqueue_style( 'wpa' );
				wp_enqueue_script( 'wpa' );
			}
		}

		/**
		 * Add some css adjustments based on popular active themes
		 *
		 * @since      1.0.0
		 * @return     void
		 */
		protected function maybe_add_inline_styles() {
			$theme = get_template();
			$css = '';
			if( 'storefront' === $theme ) {
				$css = "
					.wpa-input {
						padding: .6180469716em;
						background-color: #f2f2f2;
						color: #43454b;
						border: 0;
						-webkit-appearance: none;
						box-sizing: border-box;
						font-weight: 400;
						box-shadow: inset 0 1px 1px rgba(0,0,0,.125);
						outline: none!important;
					}
					select.wpa-input {
						-webkit-appearance: menulist;
					}
				";
			} elseif( 'twentynineteen' === $theme ) {
				$css = "
					.wpa-form {
						font-size: 0.75em;
					}
					input.wpa-input {
						padding: 0.25rem 0.5rem;
					}
					select.wpa-input {
						background: #fff;
						border: solid 1px #ccc;
						box-sizing: border-box;
						outline: none;
						padding: 0.4044rem 0.5rem;
						outline-offset: 0;
						border-radius: 0;
					}
					select.wpa-input:focus {
						border-color: #0073aa;
						outline: thin solid rgba(0, 115, 170, 0.15);
						outline-offset: -4px;
					}
					.wpa-field label {
						font-size: 0.9em;
					}
				";
			} elseif( 'twentyseventeen' === $theme ) {
				$css = "
					.wpa-form-head {
						line-height: 2em!important;
					}
					select.wpa-input {
						padding: 0.4044rem 0.5rem;
					}
				";
			} elseif( 'twentysixteen' === $theme ) {
				$css = "
					a.wpa-trigger {
						color: initial!important;
					}
					.wpa-form-head {
						line-height: 2.4em!important;
					}
					select.wpa-input {
						background: #f7f7f7;
						background-image: -webkit-linear-gradient(rgba(255, 255, 255, 0), rgba(255, 255, 255, 0));
						border: 1px solid #d1d1d1;
						border-radius: 2px;
						color: #686868;
						padding: 0.563em 0.4375em;
						width: 100%;
					}
					select.wpa-input:focus {
						background-color: #fff;
						border-color: #007acc;
						color: #1a1a1a;
						outline: 0;
					}
				";
			}

      //$css .= '.wpa-form-head { background-color: #3C4858 }';
			if ( $css )
				wp_add_inline_style( 'wpa', $css );
		}

		/**
		 * Render footer popup
		 *
		 * @since      1.0.0
		 * @return     void
		 */
		public function unsubscribe_popup_html() {
			if ( ! in_array( $this->unsubscribe_popup, [ 'unsubscribe_success', 'unsubscribe_error' ] ) )
				return;

			$message = WPA_Utils::get_option( 'messages[' . $this->unsubscribe_popup . ']' );
			if ( ! is_string( $message ) )
				return;

			$class = ( false !== strpos( $this->unsubscribe_popup, '_success' ) ) ? 'woocommerce-error' : 'woocommerce-message';
			$form_title = WPA_Utils::get_option( 'form[title]' );

			ob_start();
			include( WPA_DIR . '/templates/public/unsubscribe-popup.php' );
			ob_end_flush();
		}

		/**
		 * Registers WPA_form shortcode
		 *
		 * @since      1.0.0
		 * @param      array   $atts
		 * @return     string
		 */
		public function form_shortcode( $atts ) {

			$atts = shortcode_atts( array (
				'product_id' => '',
			), $atts, 'WPA_form' );

			if ( ! WPA_Utils::is_absint( $atts['product_id'] ) )
				return '';

			return WPA_Utils::get_form( intval( $atts['product_id'] ) );
		}
	}
}
