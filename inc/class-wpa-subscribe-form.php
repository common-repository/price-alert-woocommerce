<?php
/**
 * Handles subscribe form rendering on public area
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Public/Classes
 */ 

if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists( 'WPA_Subscribe_Form' ) ) :

	/**
	 * Subscribe form class
	 */    
	class WPA_Subscribe_Form {
		
		/**
		 * Form position
		 *
		 * @var        string
		 * @since      1.0.0
		 * @access     protected
		 */			
		protected $position;
		
		/**
		 * Form visibility based on current user and product
		 *
		 * @var        bool
		 * @since      1.0.0
		 * @access     protected
		 */			
		protected $is_visible;
		
		/**
		 * A product instance to which form is attached
		 *
		 * @var        WC_Product
		 * @since      1.0.0
		 * @access     private
		 */			
		private $product;
		
		/**
		 * Whether object is called as shortcode or based on settings
		 *
		 * @var        bool
		 * @since      1.0.0
		 * @access     private
		 */
		private $is_shortcode;
		
		/**
		 * Array of products ids of rendered forms
		 *
		 * @var        array
		 * @since      1.0.0
		 * @access     private
		 */		
		private static $_rendered = array();
		
		/**
		 * Constructor
		 *
		 * @since      1.0.0
		 */			
		public function __construct( WC_Product $product, $is_shortcode = true ) {
			if ( $product->is_type('variation') )
				return;
			
			//echo '<pre>'; var_dump( $product ); echo '</pre>';
			$this->is_shortcode = (bool) $is_shortcode;
			$this->product = $product;			
			$this->position = WPA_Utils::get_option( 'form_position' );
			
			$visibility = WPA_Utils::get_option( 'visible_for' );			
			$hide_outofstock = WPA_Utils::get_option( 'hide_out_of_stock' );
			
			$this->is_visible = true;
			if ( ( 'customers' === $visibility && ! is_user_logged_in() ) || ( 'guests' === $visibility && is_user_logged_in() ) )
				$this->is_visible = false;
			elseif ( 'outofstock' === $product->get_stock_status() && 'yes' === $hide_outofstock )
				$this->is_visible = false;
        }

		/**
		 * Allow access to protected and private props
		 *
		 * @since        1.0.0		 
		 * @param string $prop
		 * @return       mixed
		 */			
		public function __get( $prop ) {
			if ( property_exists( $this, $prop ) )
				return $this->{$prop};
			
			return null;
		}
		
		/**
		 * Hookup to wp
		 *
		 * @since      1.0.0
		 * @return     void
		 */		
        public function hookup() {
			if ( ! $this->is_visible || apply_filters( 'WPA_prevent_form_rendering', false, $this ) )
				return;
			
			if ( ! wp_style_is( 'wpa' ) ) {
				if ( did_action( 'wp_enqueue_scripts' ) )	
					wp_enqueue_style( 'wpa' );
				else
					add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 20 );
			}
			
			if ( ! wp_script_is( 'wpa' ) ) {
				if ( did_action( 'wp_enqueue_scripts' ) ) {
					$this->localize_script();
					wp_enqueue_script( 'wpa' );					
				} else {
					add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );					
				}
			}			
			
			$outofstock = ( 'outofstock' === $this->product->get_stock_status() );
			
			if ( $this->is_shortcode ) {
				add_action( 'WPA_shortcode', array( $this, 'trigger_button' ), 10 );
			} elseif ( 'after_price' === $this->position || 'before_price' === $this->position || ( $outofstock && false !== strpos( $this->position, '_cart' ) ) ) {
				add_filter( 'woocommerce_get_price_html', array( $this, 'filter_html' ), 10, 1 );
			} elseif ( 'after_desc' === $this->position || 'before_desc' === $this->position ) {
				add_filter( 'woocommerce_short_description', array( $this, 'filter_html' ), 10, 1 );
			} elseif ( 'after_cart' === $this->position && ! $outofstock ) {
				add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'trigger_button' ), 10 );
			} elseif ( 'before_cart' === $this->position && ! $outofstock ) {
				add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'trigger_button' ), 10 );
			} elseif ( 'after_cart_button' === $this->position && ! $outofstock ) {
				add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'trigger_button' ), 10 );
			} elseif ( 'before_cart_button' === $this->position && ! $outofstock ) {
				add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'trigger_button' ), 10 );
			} elseif ( 'after_meta' === $this->position ) {
				add_action( 'woocommerce_product_meta_end', array( $this, 'trigger_button' ), 10 );
			} elseif ( 'before_meta' === $this->position ) {
				add_action( 'woocommerce_product_meta_start', array( $this, 'trigger_button' ), 10 );
			}
        }
		
		/**
		 * Prepends or appends button to woocommerce generated html
		 *
		 * @since        1.0.0		 
		 * @param string $prop
		 * @return       string
		 */			
		public function filter_html( $woo_html ) {
			remove_filter( 'woocommerce_get_price_html', array( $this, 'filter_html' ), 10, 1 );
			if ( false !== strpos( $this->position, 'after_' ) )
				return $woo_html.$this->get_button_html();
			else
				return $this->get_button_html().$woo_html;
		}

		/**
		 * Renders button html
		 *
		 * @since        1.0.0
		 * @return       string
		 */			
		public function trigger_button() {
			remove_action( current_action(), array( $this, 'trigger_button' ), 10 );
			echo $this->get_button_html();
		}
		
		/**
		 * Get button html
		 *
		 * @since        1.0.0
		 * @access       protected
		 * @return       string
		 */			
		protected function get_button_html() {			

			$label = (string) WPA_Utils::get_option( 'form[Alert_Label]' );
			$icon = '<span class="fa fa-bell"></span>';
			$html = '<a class="wpa-trigger" data-wpa-form="' . esc_attr( $this->product->get_id() ) . '">'.$icon.' '. esc_html( $label ) . '</a>';			
			
			$html = (string) apply_filters( 'WPA_trigger_button_html', $html, $this, $label );
			if ( $html )
				add_action( 'wp_footer', array( $this, 'subscribe_form' ), 1 );
			
			return $html;

		}

		/**
		 * Render form html
		 *
		 * @since        1.0.0
		 * @return       string
		 */			
		public function subscribe_form() {
			$pid = $this->product->get_id();
			if ( in_array( $pid, self::$_rendered ) )
				return;
			
			self::$_rendered[] = $pid;
			$labels = WPA_Utils::get_option( 'form' );
			
			$label = $labels['submit_label'];
			$form_title = $labels['title'];
			$lebel_show = $labels['lebel_show'];
			$email_title = $labels['email_title'];
			$pass_title = $labels['pass_title'];
			$email_palceholder = $labels['email_placeholder'];
			$price_title = $labels['price_title'];			
			$var_title = $labels['variations_title'];
			$var_def_option = $labels['variations_def_option'];
			$privacy_page_id = function_exists( 'wc_privacy_policy_page_id' ) ? wc_privacy_policy_page_id() : get_option( 'wp_page_for_privacy_policy', 0 );
			$privacy_url = is_int( $privacy_page_id ) && $privacy_page_id > 0 ? get_permalink( $privacy_page_id ) : '';
			$privacy_label = str_replace( '{terms_url}', esc_url( $privacy_url ), $labels['privacy_title'] );
			$privacy_label = wp_kses( $privacy_label, ['a' => [ 'href' => true, 'target' => true, 'class' => true, 'title' => true ] ], [ 'http' , 'https' ] );
			
			$force_pass = WPA_Utils::get_option( 'force_pass' );
			$max_price = ( $this->product->is_type( 'variable' ) || $this->product->is_type( 'grouped' ) ) ? '' : $this->product->get_price( 'edit' ) - 1;		

			//$variations = array();
			if ( $this->product->is_type( 'variable' ) || $this->product->is_type( 'grouped' ) ) {
				$var_options = $var_images = $placeholder_id ='';
				$rendered_images = array();
				$hide_outofstock = WPA_Utils::get_option( 'WPA_hide_out_of_stock' );				
				$method =  ( $this->product->is_type( 'variable' ) ) ? 'get_visible_children' : 'get_children';
				foreach( $this->product->{$method}() as $id ) {
					$variation = wc_get_product( $id );
					if ( 'yes' === $hide_outofstock && 'outofstock' === $variation->get_stock_status() )
						continue;
					//$variations[] = $variation;
					
					//$context ='edit' allow as to avoid returning image from parent product if child image not set
					$img_id = $variation->get_image_id( 'edit' );
					if ( ! WPA_Utils::is_absint( $img_id ) ) {
						if ( empty( $placeholder_id ) ) {
							$placeholder_id = get_option( 'woocommerce_placeholder_image', false );
							$placeholder_id = ! is_numeric( $placeholder_id ) ? 0 : $placeholder_id;
						}
						$img_id = $placeholder_id;
					}
					
					$price = $variation->get_price();
					$var_options .= sprintf( 
						'<option data-wpa-maxprice="%s" data-wpa-img="%s" value="%s">%s - %s</option>', 
						esc_attr( $price ), esc_attr( $img_id ), $id,
						esc_html( $variation->get_name() ), 
						wp_strip_all_tags( wc_price( $price ) )
					);
					
					$img_url = ( 0 === $img_id ) ? wc_placeholder_img_src() : wp_get_attachment_image_url( (int) $img_id );
					if ( ! in_array( $img_id, $rendered_images ) && $img_url ) {
						$rendered_images[] = $img_id;
						$var_images .= sprintf( 
							'<img role="presentation" src="%s" data-wpa-img="%s" class="wpa-img wcpt-hidden" />', 
							esc_url( $img_url ),
							esc_attr( $img_id )
						);
					}
				}
			}
			
			if ( ! wp_script_is( 'WPA_recaptcha2' ) ) {
				
				$captcha = WPA_Utils::get_option( 'WPA_recaptcha' );
				$render_captcha = ( 
					( 'all' === $captcha['enabled'] || ( 'guests' === $captcha['enabled'] && ! is_user_logged_in() ) )
					&& count( array_filter( $captcha ) ) >= 3 && count( array_filter( $captcha, 'is_string' ) ) >= 3
				);				
				
				if ( $render_captcha ) {
					add_filter( 'script_loader_tag', array( $this, 'add_defer_async_to_script_tag' ), 10, 2 );
					wp_enqueue_script( 'WPA_recaptcha2' );				
					printf(
						'<div id="wpa-recaptcha" class="g-recaptcha" data-sitekey="%s" data-callback="wpaCaptchaSubmit" data-size="invisible"></div>',
						esc_attr( $captcha['site_key'] )
					);
				}
			}			
			
			ob_start();
			//echo '<pre>'; var_dump( $variations ); echo '</pre>';			
			include( WPA_DIR . '/templates/public/subscribe-form.php' );
			ob_end_flush();
		}

		/**
		 * Adds defer and async attributes to script tag
		 *
		 * @since        1.0.0		 
		 * @param string $tag
		 * @param string $handle
		 * @return string
		 */		
		public function add_defer_async_to_script_tag( $tag, $handle ) {
			if ( 'WPA_recaptcha2' !== $handle )
				return $tag;
			
			remove_filter( 'script_loader_tag', array( $this, 'add_defer_async_to_script' ), 10, 2 );
			if ( false === strpos( $tag, ' async' ) )
				$tag = str_replace( ' src', ' async src', $tag );
			
			if ( false === strpos( $tag, ' defer' ) )
				$tag = str_replace( ' src', ' defer src', $tag );
			
			return $tag;
		}	

		public function enqueue_scripts() {
			$this->localize_script();
			wp_enqueue_script( 'wpa' );		
		}
		
		protected function localize_script() {
			wp_localize_script( 'wpa', 'WPA_l10n', array (
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			) );		
		}		

		public function enqueue_styles() {
			wp_enqueue_style( 'wpa' );
		}			

    }

endif;