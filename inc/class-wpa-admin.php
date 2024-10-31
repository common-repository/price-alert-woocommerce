<?php
/**
 * Admin facing funccionality class
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Admin/Classes
 */	

if ( ! defined('ABSPATH') ) die;

if ( ! class_exists( 'WPA_Admin' ) ) {
	
	/**
	 * Hooks to admin area loading. Adds options panel to woocommerce tabs and display subscription list table 
	 */	
	class WPA_Admin implements WPA_Object {
		
		/**
		 * List table instance not null only when subscription list page loaded
		 *
		 * @var        WPA_List_Table_Subscriptions|null
		 * @since      1.0.0
		 * @access     protected
		 */	
		protected $table;
		
		/**
		 * Hookup to wp
		 *
		 * @since      1.0.0
		 * @return     void
		 */	
		public function hookup() {
			add_filter( 'plugin_action_links_' . WPA_BASENAME, array( $this, 'plugin_links' ), 10, 4 );
			add_filter( 'woocommerce_get_settings_pages', array( $this, 'init_settings' ) );
			add_action( 'admin_menu', array( $this, 'add_subscriptions_list_page') );
			add_filter( 'set-screen-option', array( $this, 'set_screen_options'), 10, 3 );
			//ajax handlers
			add_action( 'wp_ajax_WPA_subscribe', array( $this, 'subscribe_handler' ) );
			add_action( 'wp_ajax_nopriv_WPA_subscribe', array( $this, 'subscribe_handler') );
			add_action( 'wp_ajax_WPA_email_exists', array( $this, 'email_exists_handler') );
			add_action( 'wp_ajax_nopriv_WPA_email_exists', array( $this, 'email_exists_handler') );
			//wpmu
			$wpmu_action = version_compare( get_bloginfo( 'version' ), '5.1', '>=' ) ? 'wp_initialize_site' : 'new_blog_created';
			add_action( $wpmu_action, array( $this, 'new_blog_created' ) );
			add_filter( 'wpmu_drop_tables', array( $this, 'wpmu_drop_tables' ), 10, 2 );
		}

		/**
		 * Modify plugin action links on plugins list table
		 *
		 * @since      1.0.0
		 * @return     void
		 */			
		public function plugin_links( $links, $file, $data, $context ) { 
			$settings_link = sprintf( '<a href="admin.php?page=wc-settings&tab=wpa">%s</a>', __( 'Settings', 'wpa' ) ); 
			array_unshift( $links, $settings_link ); 
			return $links; 
		}		
		
		/**
		 * Adds tab to woocommerce settings panel
		 *
		 * @since      1.0.0
		 * @return     void
		 */		
		public function init_settings( $settings ) {
			require_once( WPA_DIR . '/inc/class-wpa-settings-page.php' );
			$WPA_settings = new WPA_Settings_Page();			
			//$WPA_settings->hookup();
			//TO-DO: check if is possible to unhook this if not revert to uncomented variant because it will be mostly useless
			wpa()->add( 'settings', $WPA_settings );
			$settings[] = $WPA_settings;
			return $settings;
		}

		/**
		 * Register subscriptions list to woocommerce submenu
		 *
		 * @since      1.0.0
		 * @return     void
		 */	
		public function add_subscriptions_list_page() {
		

			$hookname = add_menu_page( 
        __( 'Price Alert', 'wpa' ),
        __('Price Alert','wpa'),
        'manage_options',
        'wpa-subscriptions',
        array ( $this, 'subsriptions_page' )
		); 
		
			add_action( 'load-' . $hookname, array( $this, 'subscriptions_list_onload' ) );
		}

		/**
		 * Registers screen options and hooks to enqueue scripts
		 *
		 * @since      1.0.0
		 * @return     void
		 */			
		public function subscriptions_list_onload() {	
			
			add_screen_option( 'per_page', array(
				'label'		=>	__( 'Items Per Page', 'wpa' ),
				'default'	=>	10,
				'option'	=>	'WPA_per_page'
			));	
			
			require_once( WPA_DIR . '/inc/class-wpa-list-table-subscriptions.php' );
			$this->table = new WPA_List_Table_Subscriptions();
			$this->table->prepare_items();			
			
			add_action( 'admin_enqueue_scripts', array( $this, 'subscriptions_list_assets' ) );
			add_action( 'admin_notices', array( $this, 'subscriptions_list_notices') );
		}

		/**
		 * Enqueue assets to subscriptions list page
		 *
		 * @since      1.0.0
		 * @return     void
		 */		
		public function subscriptions_list_assets( $hookname ) {
			wp_enqueue_style( 'wpa-list-table',  WPA_URL . '/assets/css/wpa-list-table.css', array(), WPA_VERSION );
		}
		
		/**
		 * Handling validation of screen options
		 *
		 * @since      1.0.0
		 * @return     void
		 */			
		public function set_screen_options( $do, $option, $value ) {
			if ( 'WPA_per_page' === $option ) {
				return ( WPA_Utils::is_absint( $value ) && (int) $value > 0 && (int) $value <= 50 ) ? $value : $do;	
			}
			return $do;
		}		

		public function subscriptions_list_notices() {
			$message = '';
			if ( isset( $_REQUEST['wpa-action'] ) && is_string( $_REQUEST['wpa-action'] ) && $count = get_option( 'WPA_subscriptions_processed_' . $_REQUEST['wpa-action'] ) ) {
				$count = absint( $count );
				if ( 'delete' === $_REQUEST['wpa-action'] ) {
					/* translators: %s: subscriptions count */
					$message = sprintf( _n( '%s subscription successfully deleted', '%s subscriptions successfully deleted', $count, 'wpa' ), $count );
				} elseif ( 'reduce' === $_REQUEST['wpa-action'] ) {
					/* translators: %s: products count */
					$message = sprintf( _n( 'Price of %s product reduced to expected', 'Prices of %s products reduced to expected', $count, 'wpa' ), $count );
				} else {
					$message = (string) apply_filters( 'WPA_subscriptions_table_notice', $message, $_REQUEST['wpa-action'], $count );
				}
				delete_option( 'WPA_subscriptions_processed_' . $_REQUEST['wpa-action'] );
				$message = ( ! empty( $message ) ) ? '<div class="updated below-h2"><p>' . $message . '</p></div>' : '';
				echo $message;
			}
			
		}

		/**
		 * Renders subscriptions list admin page
		 *
		 * @since      1.0.0
		 */			
		public function subsriptions_page() {			
			ob_start();
			include( WPA_DIR . '/templates/admin/subscriptions-list-table.php' );
			ob_end_flush();		
		}

		/**
		 * Ajax handler for creation and updating subscriptions
		 *
		 * @since      1.0.0
		 */	
		public function subscribe_handler() {
			$seq_error = array (
				'sequrity' => __( 'Something wen\'t wrong, please try again', 'wpa' ),
			);
			
			if ( ( ! is_user_logged_in() && ! isset( $_POST['email'] ) ) || ! isset( $_POST['price'] ) )
				wp_send_json_error( $seq_error );
			
			$product_id = isset( $_POST['pid'] ) && WPA_Utils::is_absint( $_POST['pid'] ) ? (int) $_POST['pid'] : 0;	
			$product = wc_get_product( $product_id );
			if ( ! $product instanceof WC_Product || $product->is_type('variation') || ! check_ajax_referer( 'wpa-subscription-' . $product_id, 'key', false ) )
				wp_send_json_error( $seq_error );
			
			$form = new WPA_Subscribe_Form( $product, false );
			if ( ! $form->is_visible )
				wp_send_json_error( $seq_error );
			
			$captcha = WPA_Utils::get_option( 'WPA_recaptcha' );
			$captcha_rendered = ( 
				( 'all' === $captcha['enabled'] || ( 'guests' === $captcha['enabled'] && ! is_user_logged_in() ) )
				&& count( array_filter( $captcha ) ) >= 3 && count( array_filter( $captcha, 'is_string' ) ) >= 3
			);

			if ( $captcha_rendered ) {
				if ( ! isset( $_POST['captcha'] ) || ! is_string( $_POST['captcha'] ) )
					wp_send_json_error( $seq_error );
				
				$response = wp_remote_post(
					'https://www.google.com/recaptcha/api/siteverify',
					array (
						'body' => array (
							'secret' => $captcha['secret_key'],
							'response' => $_POST['captcha']
						)
					)
				);
				
				if ( ! $response || ! is_array( $response ) || ! isset( $response['body'] ) )
					wp_send_json_error( $seq_error );
				
				$decoded = json_decode( $response['body'] );
				if ( ! isset( $decoded->success ) || ! isset( $decoded->hostname ) || true !== $decoded->success || false === strpos( get_site_url(), $decoded->hostname ) )
					wp_send_json_error( $seq_error );			
			}			
			
			//checks for variable and groupped products
			if ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) {
				$method = $product->is_type( 'variable' ) ? 'get_visible_children' : 'get_children';
				if ( ! isset( $_POST['variation'] ) || ! WPA_Utils::is_absint( $_POST['variation'] ) || ! in_array( (int) $_POST['variation'], $product->{$method}() ) )
					wp_send_json_error( $seq_error );
				
				//switch for variation
				$product_id = (int) $_POST['variation'];
				$product = wc_get_product( $product_id );
			}
			
			$errors = array();
			$messages = WPA_Utils::get_option( 'messages' );
			
			if ( ! is_user_logged_in() && ! is_email( $_POST['email'] ) ) 
				$errors[ 'wpa-email' ] = $messages['invalid_email'];
			
			if ( ! is_user_logged_in() && 'yes' === WPA_Utils::get_option( 'force_pass' ) && $user = get_user_by( 'email', $_POST['email'] ) ) {
				if ( ! isset( $_POST['pass'] ) || ! wp_check_password( strval( $_POST['pass'] ), $user->get('user_pass') ) )
					$errors[ 'wpa-pass' ] = $messages['invalid_pass'];
				elseif ( isset( $errors[ 'wpa-terms' ] ) )
					unset( $errors[ 'wpa-terms' ] );//unset because logged-out user enters password, means he customer and alredy agreed with terms and conditions
			}
			
			if ( ! WPA_Utils::is_absint( $_POST['price'] ) || (int) $_POST['price'] >= $product->get_price( 'edit' ) || (int) $_POST['price'] < 1 )
				$errors[ 'wpa-price' ] = str_replace( '{current_price}',  $product->get_price(), $messages['invalid_price'] );
			
			if ( ! empty( $errors ) )
				wp_send_json_error( $errors );
			
			global $wpdb;
			
			$email = isset( $_POST['email'] ) && ! is_user_logged_in() ? $_POST['email'] : wp_get_current_user()->user_email;	
			$old = $wpdb->get_row( $wpdb->prepare( 
				"SELECT id, hash, price, created, queued, status 
				FROM {$wpdb->prefix}WPA_subscriptions 
				WHERE product_id = %d AND email = %s AND status != 'sent'",
				$product_id, $email
			), ARRAY_A );
			
			$data = $format = array();
			if ( is_array( $old ) && ! empty( $old ) ) {
				
				if ( isset( $old['price'] ) && $old['price'] === $_POST['price'] )			
					wp_send_json_success( $messages['updated'] );			
				
				$data['id'] = $old['id'];
				$format[] = '%d';		
			} else {
				$pass = wp_generate_password();
			}
			
			$data += array (
				'email'           => $email,
				'price'           => (int) $_POST['price'],
				'product_id'      => $product_id,
				'status'          => 'active',
				'hash'            => ( is_array( $old ) && ! empty( $old ) ) ? $old['hash'] : wp_hash_password( $pass ),
				'created'         => ( is_array( $old ) && ! empty( $old ) ) ? $old['created'] : current_time( 'mysql' ),
				'updated'         => current_time( 'mysql' ),
				'queued'          => ( is_array( $old ) && ! empty( $old ) ) ? $old['queued'] : '0000-00-00 00:00:00',
			);
			$format = array_merge( $format, array ( '%s', '%d', '%d', '%s', '%s', '%s', '%s' ) );
			$result = $wpdb->replace( $wpdb->prefix . 'WPA_subscriptions', $data, $format );
			
			if ( is_int( $result ) && $result > 0 ) {
				if ( is_array( $old ) && ! empty( $old ) )
					wp_send_json_success( $messages['updated'] );
				
				//it is new subscription so we need to send emails
				$data['pass'] = $pass;
				if ( WPA_Utils::send_email( $data, 'success' ) ) {
					//try to notify admin about new subscription
					if ( 'yes' === WPA_Utils::get_option( 'WPA_success_notify_admin' ) )	
						WPA_Utils::send_email( $data, 'success-admin' );
				} else {
					//put in the queue as fallback
					$table = $wpdb->prefix . 'WPA_subscriptions';
					$wpdb->query( $wpdb->prepare(
						"UPDATE $table SET queued = %s WHERE product_id = %d AND status = 'active' AND email = %s", 
						current_time( 'mysql' ), $product_id, $email
					) );			
					wpa()->get('emailer')->invoke();
				}	
				
				wp_send_json_success( $messages['success'] );
			}
			
			wp_send_json_error( $seq_error );
		}

		/**
		 * Ajax handler. Checks if user with submitted email is registered or not
		 *
		 * @since      1.0.0
		 */			
		public function email_exists_handler() {
			if ( is_user_logged_in() || ! isset( $_POST['email'] ) || ! isset( $_POST['pid'] ) || ! is_email( $_POST['email'] ) )
				wp_send_json_error( 'error' );	
			
			if ( ! check_ajax_referer( 'wpa-subscription-' . strval( $_POST['pid'] ), 'key', false ) )
				wp_send_json_error( 'error' );
			
			if ( email_exists( $_POST['email'] ) )
				wp_send_json_success( 'exists' );

			wp_send_json_success( 'not_exists' );
		}

		/**
		 * When new Blog is created in multisite run our installer if our plugin active for this blog
		 *
		 * @since  1.0.0
		 * @param  int|WP_Site $blog WordPress 5.1 passes a WP_Site object.
		 * @return void
		 */
		public function new_blog_created( $blog ) {
			if ( ! is_plugin_active( WPA_BASENAME ) )
				return;

			if ( ! is_int( $blog ) )
				$blog = $blog->id;
			
			require_once WPA_DIR . '/inc/class-wpa-activator.php';
			switch_to_blog( $blog );
			WPA_Activator::blog_activation();
			restore_current_blog();
		}		
		
		/**
		 * Drop our table when a mu site is deleted
		 *
		 * TO-DO: If plugin is not network active then it not will be loaded in network admin screen
		 * and it may cause our tables not deleted if administrators delete blogs thru this screen
		 * to avoid this maybe we need to create cron task to clear orphaned tables
		 *  
		 * @since  1.0.0
		 * @param  array $tables 
		 * @param  int   $blog_id 
		 * @return array 
		 */
		public function wpmu_drop_tables( $tables, $blog_id ) {

			global $wpdb;
			$tables[] = $wpdb->prefix . 'WPA_subscriptions';
			
			return $tables;
		}		
	}
}
