<?php
/**
 * Hook to woocommerce settings
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Classes
 */  

if ( ! defined('ABSPATH') ) die;

if ( ! class_exists( 'WPA_Settings_Page' ) ) :

	/**
	 * Woocommerce settings page class
	 */    
	class WPA_Settings_Page extends WC_Settings_Page implements WPA_Object {

        public function __construct() {
            $this->id = 'wpa';
						$this->label = 'Price Alert';
        }

		/**
		 * Hookup to wp
		 *
		 * @since      1.0.0
		 * @return     void
		 */	        
		public function hookup() {
            add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 50 );
            add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
            add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output') );
            add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
			add_filter( 'woocommerce_admin_settings_sanitize_option' , array( $this, 'sanitize_option' ), 10, 3 );       
		}		

		/**
		 * Alter how woocommerce handle sanitization some of our settings
		 *
		 * @since         1.0.0
		 * @param mixed   $value
		 * @param array   $option
		 * @param mixed   $raw_value
		 * @return mixed
		 */	
		public function sanitize_option( $value, $option, $raw_value ) {
			//not our case
			if ( 0 !== strpos( $option['id'], 'WPA_' ) || 'title' === $option['type'] || 'sectionend' === $option['type'] )
				return $value;
			
			$atts = isset( $option['custom_attributes'] ) && is_array( $option['custom_attributes'] ) ? $option['custom_attributes'] : [];
			
			if ( 'number' === $option['type'] ) {				
				if ( ! is_numeric( $value ) ) {
					$value = '';
				} else {
					$value = ( isset( $atts['min'] ) && is_numeric( $atts['min'] ) && $value < $atts['min'] ) ? $atts['min'] : $value;
					$value = ( isset( $atts['max'] ) && is_numeric( $atts['max'] ) && $value > $atts['max'] ) ? $atts['max'] : $value;					
				}
			}
			
			//update scheduled sent subscriptions deletion event on option update
			if ( 'WPA_sent_subscriptions_lifetime' === $option['id'] ) {
				if ( $value && WPA_Utils::is_absint( $value ) ) {
					
					$old_next_run = wp_next_scheduled( wpa()->get('emailer')->cron_hook_identifier . '_cleanup_sent' );

					if ( $old_next_run && ( $old_next_run - current_time( 'timestamp' ) ) > DAY_IN_SECONDS )
						wpa()->get('emailer')->reschedule_sent_subscriptions_cron( (int) $value );				
				}	
			}
			
			//required cannot be empty
			$value = empty( $value ) && 0 !== $value && '0' !== $value && isset( $atts['required'], $option['default'] ) ? $option['default'] : $value;
		
            return $value;
        }
		
		/**
		 * Get settings sections
		 *
		 * @since      1.0.0
		 * @return     array
		 */	
		public function get_sections() {

           ?>
				<div class="alert-admin">
					<?php

								$sections = array(
										''       => __( 'General', 'woo_alert' ),
										'labels' => __( 'Form labels', 'woo_alert' ),
										'emails' => __( 'Emails Setting', 'woo_alert' ),
								);

								return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
							?>
					</div>
				<?php
        }

		/**
		 * Output fields
		 *
		 * @since      1.0.0
		 * @return     string
		 */        
		public function output() {
            global $current_section;
            $settings = $this->get_settings( $current_section );
            WC_Admin_Settings::output_fields( $settings );
        }

		/**
		 * Save fields
		 *
		 * @since      1.0.0
		 * @return     void
		 */ 
        public function save() {           
			global $current_section;
            $settings = $this->get_settings( $current_section );
            WC_Admin_Settings::save_fields( $settings );       
		}

		/**
		 * Get settings
		 *
		 * @since      1.0.0
		 * @param string  $section
		 * @return void
		 */        
		public function get_settings( $section = '' ) {
			
			$defaults = WPA_Utils::settings_def();
			
			if ( '' === $section ) {
				
				$settings = array();
				
				$settings[] = array(
					//'name' => __( 'General', 'wpa' ),
					'type' => 'title',
					'id'   => 'WPA_form_visibility_section' 
				);					
				$settings[] = array(
					'name'    => __( 'Alert form available for:', 'wpa' ),
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'default' => $defaults['WPA_visible_for'],
					'options' => array (
						'all' => __( 'Guests and customers', 'wpa' ),
						'customers' => __( 'Only for customers', 'wpa' ),
						'guests' => __( 'Only for guests', 'wpa' ),
					),
					'id'   => 'WPA_visible_for' 
				);
				$settings[] = array(
					//'name' => __( 'Hide for outofstock products', 'wpa' ),
					'type' => 'checkbox',
					'default' => $defaults['WPA_hide_out_of_stock'],
					'desc' => __( 'Hide subscription form if product is out of stock', 'wpa' ),
					'id'   => 'WPA_hide_out_of_stock'
				);
				$settings[] = array(
					'name'     => __( 'position on product page', 'wpa' ),
					'type'     => 'select',
					'default' => $defaults['WPA_form_position'],
					'desc_tip' => __( 'Choose where modal subscription form trigger button will be rendered on product page. You can also use [WPA_form product_id=12345] shortcode to display form anywhere.', 'wpa' ),
					'class'   => 'wc-enhanced-select',						
					'options'  => array (
						'before_price' => __( 'Before product price', 'wpa' ),							
						'after_price'  => __( 'After product price', 'wpa' ),
						'before_cart_button' => __( 'Before ADD TO CART button', 'wpa' ),
						'after_cart_button'  => __( 'After ADD TO CART button', 'wpa' ),
						'before_cart'  => __( 'Before ADD TO CART form', 'wpa' ),	
						'after_cart'   => __( 'After ADD TO CART form', 'wpa' ),
						'before_desc'  => __( 'Before product description', 'wpa' ),	
						'after_desc'   => __( 'After product description', 'wpa' ),
						'before_meta'  => __( 'Before product meta', 'wpa' ),
						'after_meta'   => __( 'After product meta', 'wpa' ),
						'no'           => __( 'Hide for shortcode usage', 'wpa' ),
					),
					'id'   => 'WPA_form_position' 
				);
				$settings[] = array(
					'name'     => __( 'position on products archives', 'wpa' ),
					'type'     => 'select',
					'default' => $defaults['WPA_form_position_shop'],
					'desc_tip' => __( 'Choose where modal subscription form trigger button will be rendered on shop page and product archives.', 'wpa' ),
					'class'   => 'wc-enhanced-select',						
					'options'  => array(
						'no'                 => __( 'Hide on shop page', 'wpa' ),
						'before_cart_button' => __( 'Before ADD TO CART button', 'wpa' ),
						'after_cart_button'  => __( 'After ADD TO CART button', 'wpa' ),							
					),
					'id'   => 'WPA_form_position_shop' 
				);
				$settings[] = array(
					'name'    => __( 'Enable password check', 'wpa' ),
					'type'    => 'checkbox',
					'default' => $defaults['WPA_force_pass'],			
					'desc'    => __( 'Request a password if the submitter is logged out and the entered email address belongs to a registered customer.', 'wpa' ),
					'id'      => 'WPA_force_pass'
				);
				$settings[] = array(
					'name'     => __( 'Google reCAPTCHA verification', 'wpa' ),
					'type'     => 'select',
					'default' => $defaults['WPA_recaptcha']['enabled'],
					'desc_tip' => __( 'Set when to use Google reCAPTCHA to protect the form', 'wpa' ),
					'class'   => 'wc-enhanced-select',						
					'options'  => array (
						'no'           => __( 'Disabled', 'wpa' ),
						'guests'       => __( 'Enabled only for guests', 'wpa' ),							
						'all'          => __( 'Enabled for all', 'wpa' )
					),
					'id'   => 'WPA_recaptcha[enabled]' 
				);
				$settings[] = array(
					'name'    => __( 'Google reCAPTCHA site key', 'wpa' ),
					'type'    => 'text',
					'css'     =>  'min-width:398px;',
					'default' => $defaults['WPA_recaptcha']['site_key'],
					'desc'    => sprintf( '<br /><a href="https://www.google.com/recaptcha/">%s</a>', __( 'Get Google reCAPTCHA key', 'wpa' ) ),
					'id'	  => 'WPA_recaptcha[site_key]'
				);
				$settings[] = array(
					'name'    => __( 'Google reCAPTCHA secret key', 'wpa' ),
					'type'    => 'text',
					'css'     =>  'min-width:398px;',
					'default' => $defaults['WPA_recaptcha']['secret_key'],
					'desc'    => sprintf( '<br /><a href="https://www.google.com/recaptcha/">%s</a>', __( 'Get Google reCAPTCHA key', 'wpa' ) ),						
					'id'	  => 'WPA_recaptcha[secret_key]'
				);
				if ( current_user_can( 'delete_plugins' ) ) {
					$settings[] = array(
						'name'    => __( 'Delete plugin data during uninstall', 'wpa' ),
						'type'    => 'checkbox',
						'default' => $defaults['WPA_uninstall_cleanup'],
						'desc'    => __( 'Enable to delete all plugin data, including all subscriptions and all settings, during plugin uninstall.', 'wpa' ),
						'id'      => 'WPA_uninstall_cleanup',
						'autoload'=> false,
					);
				}				
				$settings[] = array( 'type' => 'sectionend', 'id' => 'WPA_form_visibility_section' );
				
			} elseif( 'labels' === $section ) {
				
				$settings = array (
					array(
						'name' => __( 'Field names', 'wpa' ),
						'type' => 'title',
						'desc' => __( 'Adjust labels and placeholders for the fields and the form itself', 'wpa' ),
						'id'   => 'WPA_fields_atts_section' 
					),
					
					array(
						'name'    => __( 'Form title', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'default' => $defaults['WPA_form']['title'],
						'id'	  => 'WPA_form[title]'
					),
					array(
						'name'    => __( 'Alert Label', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'default' => $defaults['Woo_Alerts_form']['Alert_Label'],
						'id'	  => 'Woo_Alerts_form[Alert_Label]'
					),
					array(
						'name'    => __( 'Label Field Show ?', 'wpa' ),
						'default' => $defaults['WPA_form']['lebel_show'],	
						'type'    => 'checkbox',
						'id'	  => 'WPA_form[lebel_show]',
						'desc'    => __( 'Label Filed Show?', 'wpa' ),
					),
					array(
						'name'    => __( 'Email field label', 'wpa' ),
						'default' => $defaults['WPA_form']['email_title'],	
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'id'	  => 'WPA_form[email_title]'
					),
					array(
						'name' => __( 'Email field placeholder', 'wpa' ),
						'type' => 'text',
						'css'     =>  'min-width:398px;',
						'default' => $defaults['WPA_form']['email_placeholder'],
						'id'	=> 'WPA_form[email_placeholder]'
					),
					array(
						'name'    => __( 'Title for variations dropdown', 'wpa' ),
						'default' => $defaults['WPA_form']['variations_title'],			
						'desc_tip' => __( 'This field is for variable and grouped products', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'id'	  => 'WPA_form[variations_title]'
					),
					array(
						'name'    => __( 'Default option for variations dropdown', 'wpa' ),
						'default' => $defaults['WPA_form']['variations_def_option'],
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'id'	  => 'WPA_form[variations_def_option]'
					),					
					array(
						'name'    => __( 'Price field label', 'wpa' ),
						'default' => $defaults['WPA_form']['price_title'],			
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'id'	  => 'WPA_form[price_title]'
					),
					array(
						'name'    => __( 'Password field label', 'wpa' ),
						'default' => $defaults['WPA_form']['pass_title'],	
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'id'	  => 'WPA_form[pass_title]'
					),
					array(
						'name'    => __( 'Privacy policy text', 'wpa' ),
						'default' => $defaults['WPA_form']['privacy_title'],	
						'desc_tip' => sprintf( __( 'This text will be rendered only if the Privacy Policy page in main woocommerce settings is set. Available placeholders: %s', 'wpa' ), '{terms_url}' ),						
						'css'     => 'min-height:100px;min-width:398px;',
						'type'    => 'textarea',
						'id'	  => 'WPA_form[privacy_title]'
					),					
					array(
						'name'    => __( 'Submit button label', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'default' => $defaults['WPA_form']['submit_label'],			
						'id'	  => 'WPA_form[submit_label]'
					),					
					array( 'type' => 'sectionend', 'id' => 'WPA_fields_atts_section' ),
					
					array(
						'name' => __( 'Submit messages', 'wpa' ),
						'type' => 'title',
						'desc' => __( 'Adjust success and error messages of subscription form', 'wpa' ),
						'id'   => 'WPA_form_meassages_section' 
					),
					array(
						'name'    => __( 'Subscription success message', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'default' => $defaults['WPA_messages']['success'],
						'id'	  => 'WPA_messages[success]',
						'autoload'=> false,
					),
					array(
						'name'    => __( 'Subscription updated message', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'default' => $defaults['WPA_messages']['updated'],
						'id'	  => 'WPA_messages[updated]',
						'autoload'=> false,
					),					
					array(
						'name'    => __( 'Invalid email message', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'default' => $defaults['WPA_messages']['invalid_email'],
						'id'	  => 'WPA_messages[invalid_email]',
						'autoload'=> false,
					),
					array(
						'name'    => __( 'Invalid password message', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'default' => $defaults['WPA_messages']['invalid_pass'],
						'id'	  => 'WPA_messages[invalid_pass]',
						'autoload'=> false,
					),					
					array(
						'name'    => __( 'Invalid price message', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'default' => $defaults['WPA_messages']['invalid_price'],
						/* translators: %s: placeholders list */
						'desc_tip'=> sprintf( __( 'Available placeholders: %s', 'wpa' ), '{current_price}' ),
						'id'	  => 'WPA_messages[invalid_price]',
						'autoload'=> false,
					),				
					array(
						'name'    => __( 'Unsubscribe success message', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'default' => $defaults['WPA_messages']['unsubscribe_success'],
						'id'	  => 'WPA_messages[unsubscribe_success]',
						'autoload'=> false,
					),
					array(
						'name'    => __( 'Unsubscribe error message', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						'default' => $defaults['WPA_messages']['unsubscribe_error'],
						'id'	  => 'WPA_messages[unsubscribe_error]',
						'autoload'=> false,
					),					
					array( 'type' => 'sectionend', 'id' => 'WPA_form_meassages_section' ),
				);
			} elseif( 'emails' === $section ) {
				
				$settings = array (					
					array(
						'name' => __( 'Subscription success email', 'wpa' ),
						'type' => 'title',
						'id'   => 'WPA_success_email_section'
					),					
					array(
						'name'    => __( 'Subject', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						/* translators: %s: placeholders list */
						'desc_tip'=> sprintf( __( 'Available placeholders: %s', 'wpa' ), '{shop_name}, {product_name}, {user_name}' ),
						'default' => $defaults['WPA_success_email']['subject'],
						'id'	  => 'WPA_success_email[subject]',
						'autoload'=> false,
					),
					array(
						'name'    => __( 'Title', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						/* translators: %s: placeholders list */
						'desc_tip'=> sprintf( __( 'Available placeholders: %s', 'wpa' ), '{shop_name}, {product_name}, {user_name}' ),			
						'default' => $defaults['WPA_success_email']['title'],
						'id'	  => 'WPA_success_email[title]',
						'autoload'=> false,
					),
					array(
						'name'    => __( 'Message', 'wpa' ),
						'type'    => 'textarea',
						'css'     => 'min-height:100px;min-width:398px;',
						/* translators: %s: placeholders list */
						'desc_tip'=> sprintf( __( 'Available placeholders: %s', 'wpa' ), '{shop_name}, {user_name}, {product_name}, {product_url}, {current_price}, {expected_price}, {unsubscribe_url}' ),			
						'default' => $defaults['WPA_success_email']['message'],
						'id'	  => 'WPA_success_email[message]',
						'autoload'=> false,
					),
					array(
						//'name'    => __( 'Enable admin notification', 'wpa' ),
						'type'    => 'checkbox',
						'default' => $defaults['WPA_success_notify_admin'],
						'desc'    => __( 'Notify administrator by email if new user is subscribed', 'wpa' ),
						'id'      => 'WPA_success_notify_admin',
						'autoload'=> false,
					),					
					array( 'type' => 'sectionend', 'id' => 'WPA_success_email_section' ),
					
					array (
						'name' => __( 'Price cheapening email', 'wpa' ),
						'type' => 'title',
						'id'   => 'WPA_cheapening_email_section',
						'autoload'=> false,
					),					
					array(
						'name'    => __( 'Subject', 'wpa' ),
						'type'    => 'text',
						'css'     => 'min-width:398px;',
						/* translators: %s: placeholders list */
						'desc_tip'=> sprintf( __( 'Available placeholders: %s', 'wpa' ), '{shop_name}, {product_name}, {user_name}' ),
						'default' => $defaults['WPA_cheapening_email']['subject'],
						'id'	  => 'WPA_cheapening_email[subject]',
						'autoload'=> false,
					),
					array(
						'name'    => __( 'Title', 'wpa' ),
						'type'    => 'text',
						'css'     =>  'min-width:398px;',
						/* translators: %s: placeholders list */
						'desc_tip'=> sprintf( __( 'Available placeholders: %s', 'wpa' ), '{shop_name}, {product_name}, {user_name}' ),			
						'default' => $defaults['WPA_cheapening_email']['title'],
						'id'	  => 'WPA_cheapening_email[title]',
						'autoload'=> false,
					),
					array(
						'name'    => __( 'Message', 'wpa' ),
						'type'    => 'textarea',
						'css'     => 'min-height:100px;min-width:398px;',
						/* translators: %s: placeholders list */
						'desc_tip'=> sprintf( __( 'Available placeholders: %s', 'wpa' ), '{shop_name}, {user_name}, {product_name}, {product_url}, {current_price}, {expected_price}, {cart_url}, {resubscribe_url}' ),			
						'default' => $defaults['WPA_cheapening_email']['message'],
						'id'	  => 'WPA_cheapening_email[message]',
						'autoload'=> false,
					),
					array (
						'name'              => __( 'Delete sent subscriptions after:', 'wpa' ),
						'type'              => 'number',
						'desc'              => __( 'day(s)', 'wpa' ),
						'desc_tip'          => __( 'After that period sent subscriptions will be automatically deleted. Don\'t fill this to disable automatic deletion', 'wpa' ),
						'default'           => $defaults['WPA_sent_subscriptions_lifetime'],
						'id'	            => 'WPA_sent_subscriptions_lifetime',
						'custom_attributes' => array( 'min' => '1' ),
						'autoload'          => false,
					),
					array(
						'name'              => __( 'Max sending limit', 'wpa' ),
						'type'              => 'number',
						'desc_tip'          => __( 'Limit the number of emails that can be sent by the plugin per minute. Don\'t fill this for unlimited speed', 'wpa' ),
						'desc'              => __( 'emails/minute', 'wpa' ),						
						'default'           => $defaults['WPA_emailing_limit'],
						'id'	            => 'WPA_emailing_limit',
						'custom_attributes' => array( 'min' => '1' ),
						'autoload'          => false,
					),					
					array( 'type' => 'sectionend', 'id' => 'WPA_cheapening_email_section' ),	
				);				
			} else {
				$settings = array();
			}
			
			//prevent woocommerce from storing "array like" options in database during wc instalation
			if ( defined( 'WC_INSTALLING' ) && 'yes' === get_transient( 'wc_installing' ) ) {
				foreach( $settings as $k=>$setting ) {
					if ( false === strpos( $setting['id'], '[' ) )
						continue;
					
					unset( $settings[ $k ] );
					list( $key, $subkey ) = explode( '[', trim( $setting['id'], ']' ) );
					
					$settings[ $key ]['id'] = $key;
					if ( isset( $setting['default'] ) )	
						$settings[ $key ]['default'][ $subkey ] = $setting['default'];
					if ( isset( $setting['autoload'] ) )
						$settings[ $key ]['autoload'] = $setting['autoload'];
				}
			}
        
			$settings = apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $section );
		
			return $settings;
		}

    }

endif;