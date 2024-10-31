<?php
/**
 * Background emailer
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Classes
 */	

if ( ! defined('ABSPATH') ) die;

if ( ! class_exists( 'WPA_Background_Emailer' ) ) {
	
	/**
	 * Class used to send queued emails to subsctibers in ajaxified background
	 */	
	class WPA_Background_Emailer implements WPA_Object {

		/**
		 * Identifier
		 *
		 * @since      1.0.0		 
		 * @var string
		 * @access protected
		 */	
		protected $identifier;

		/**
		 * Cron_hook_identifier
		 *
		 * @since      1.0.0
		 * @var string
		 * @access protected
		 */
		protected $cron_hook_identifier;

		/**
		 * Cron_interval_identifier
		 *
		 * @since      1.0.0
		 * @var string
		 * @access protected
		 */
		protected $cron_interval_identifier;

		/**
		 * Launched
		 *
		 * @since      1.0.0
		 * @var boolean
		 * @access protected
		 */
		protected static $launched;
		
		/**
		 * Start time of current process.
		 *
		 * @since      1.0.0
		 * @var int
		 * @access protected
		 */
		protected $start_time = 0;	
		
		/**
		 * Constructor.
		 *
		 * @since      1.0.0
		 */		
		public function __construct() {
			self::$launched = false;
			$this->identifier = 'WPA_background_emailer';
			$this->cron_hook_identifier     = $this->identifier . '_cron';
			$this->cron_interval_identifier = $this->identifier . '_cron_interval';
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
		 * Hookup to wp
		 *
		 * @since      1.0.0
		 * @return     void
		 */			
		public function hookup() {
			//add_action( 'WPA_email_queue_increased', array( $this, 'invoke' ) );
			add_action( 'wp_ajax_' . $this->identifier, array( $this, 'maybe_handle' ) );
			add_action( 'wp_ajax_nopriv_' . $this->identifier, array( $this, 'maybe_handle' ) );
			add_action( $this->cron_hook_identifier, array( $this, 'handle_cron_healthcheck' ) );
			add_action( $this->cron_hook_identifier . '_cleanup_sent', array( $this, 'handle_cleanup_sent_subscriptions' ) );
			add_filter( 'cron_schedules', array( $this, 'schedule_cron_healthcheck' ) );		
		}
		
		/**
		 * Trigger dispatcher on shutdown
		 *
		 * @since      1.0.0
		 * @return     void
		 */			
		public function invoke() {
			add_action( 'shutdown', array( $this, 'maybe_launch' ), 100 );
		}
		
		/**
		 * Maybe dispatch the async request
		 *
		 * @since      1.0.0		 
		 * @return void
		 */
		public function maybe_launch() {
			if ( self::$launched || ! doing_action( 'shutdown' ) )
				return;

			if ( session_id() )
				session_write_close();
			
			if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
				@set_time_limit( 0 );
			}
			
			// fastcgi_finish_request is the cleanest way to send the response and keep the script running, but not every server has it.
			if ( is_callable( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			} else {
				// Fallback: send headers and flush buffers.
				if ( ! headers_sent() ) {
					header( 'Connection: close' );
				}
				@ob_end_flush();
				flush();
			}

			$result = $this->dispatch();
			
			if ( ! is_wp_error( $result ) )
				self::$launched = true;
		}	
		
		/**
		 * Dispatch the async request
		 *
		 * @since      1.0.0		 
		 * @return array|WP_Error
		 */
		protected function dispatch() {
			//error_log( 'dispatch', 0 );
			if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
				wp_schedule_event( time() + 10, $this->cron_interval_identifier, $this->cron_hook_identifier );
			}
			
			$url = add_query_arg( 
				array( 'action' => $this->identifier, 'nonce' => wp_create_nonce( $this->identifier ) ), 
				admin_url( 'admin-ajax.php' ) 
			);
			
			$cookies = array();
			foreach ( $_COOKIE as $name => $value ) {
				if ( 'PHPSESSID' === $name ) {
					continue;
				}
				$cookies[] = new WP_Http_Cookie( array(
					'name'  => $name,
					'value' => $value,
				) );
			}

			return wp_remote_post( $url, array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'body'      => array(),
				'cookies'   => $cookies,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			));
		}
		
		/**
		 * Starts handler if limits not reached and queue not empty
		 *
		 * @since      1.0.0		 
		 * @return     void
		 */		
		public function maybe_handle() {
			//error_log( 'maybe_handle', 0 );
			// Don't lock up other requests while processing
			session_write_close();

			if ( $this->is_process_running() || $this->is_queue_empty() || $this->limits_exceeded() ) {			
				wp_die();
			}

			check_ajax_referer( $this->identifier, 'nonce' );
			$this->handle();

			wp_die();		
		}
		
		/**
		 * Pass each queue item to the task handler, while remaining
		 * within server memory and time limit constraints.
		 *
		 * @since      1.0.0
		 * @return     void	
		 */
		protected function handle() {

			$this->lock_process();
			global $wpdb;
			$table  = $wpdb->prefix . 'WPA_subscriptions';	
			
			do {
				$batch = $wpdb->get_results( "SELECT * FROM $table WHERE queued != '0000-00-00 00:00:00' AND status != 'sent' ORDER BY queued asc LIMIT 5", ARRAY_A );

				if ( empty( $batch ) ) {
					break;
				}

				$sent = $active = array();
				
				foreach ( $batch as $key => $data ) {
					
					//send emails about price reduction
					if ( 'queued' === $data['status'] && WPA_Utils::send_email( $data ) ) {
						
						$sent[] = $data['id'];
					
					} elseif ( 'active' === $data['status'] ) {
						//we must regenerate unsubscribe otp key because in database stored just hash and there is no way to unhash it
						$data['pass'] = wp_generate_password();
						
						if ( WPA_Utils::send_email( $data, 'success' ) ) {
							//store new otp hash in db
							$wpdb->query( $wpdb->prepare( 
								"UPDATE $table SET hash = %s WHERE id = %d", 
								wp_hash_password( $data['pass'] ), (int) $data['id']
							));
							
							if ( 'yes' === WPA_Utils::get_option( 'WPA_success_notify_admin' ) )	
								WPA_Utils::send_email( $data, 'success-admin' );//say hello to admin
							
							$active[] = $data['id'];
						}
					}

					if ( $this->time_exceeded() || $this->memory_exceeded() || $this->limits_exceeded() ) {
						// Batch limits reached.
						break;
					}
				}
				
				if ( ! empty( $sent ) ) {
					$sent = implode( ',', $sent );
					$wpdb->query( $wpdb->prepare( 
						"UPDATE $table SET status = 'sent', updated = %s WHERE id IN($sent)", 
						current_time('mysql') 
					));
					
					//maybe schedule delete sent emails cron
					$lifetime = WPA_Utils::get_option( 'sent_subscriptions_lifetime' );					
					if ( WPA_Utils::is_absint( $lifetime ) && ! wp_next_scheduled( $this->cron_hook_identifier . '_cleanup_sent' ) ) {					
						wp_schedule_event( time() + ( DAY_IN_SECONDS * $lifetime ), 'daily', $this->cron_hook_identifier . '_cleanup_sent' );
					}					
				}
				
				if ( ! empty( $active ) ) {
					$active = implode( ',', $active );
					$wpdb->query( $wpdb->prepare( 
						"UPDATE $table SET updated = %s, queued = '0000-00-00 00:00:00' WHERE id IN($active)", 
						current_time('mysql') 
					));				
				}			
				
			} while ( ! $this->time_exceeded() && ! $this->memory_exceeded() && ! $this->is_queue_empty() && ! $this->limits_exceeded() );

			$this->unlock_process();

			// Start next batch or complete process.
			if ( ! $this->is_queue_empty() ) {
				$this->dispatch();
			} else {
				$this->clear_scheduled_event();
			}
		}	
		
		/**
		 * Is queue empty
		 *
		 * @since      1.0.0
		 * @return bool
		 */
		protected function is_queue_empty() {
			
			global $wpdb;
			$table  = $wpdb->prefix . 'WPA_subscriptions';
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE queued != '0000-00-00 00:00:00' AND status != 'sent'" );

			return ( 0 === $count );
		}	
		
		/**
		 * Ensures the batch process never exceeds 80%
		 * of the maximum WordPress memory.
		 *
		 * @since      1.0.0
		 * @return bool
		 */
		protected function memory_exceeded() {
			
			if ( function_exists( 'ini_get' ) ) {
				$memory_limit = ini_get( 'memory_limit' );
			} else {
				$memory_limit = '128M';
			}

			if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
				// Unlimited, set to 32GB.
				$memory_limit = '32G';
			}
			
			$memory_limit   = wp_convert_hr_to_bytes( $memory_limit ) * 0.80; // 80% of max memory
			$current_memory = memory_get_usage( true );

			return ( $current_memory >= $memory_limit );
		}

		/**
		 * Ensures the batch never exceeds a sensible time limit.
		 * A timeout limit of 30s is common on shared hosting.
		 *
		 * @since      1.0.0
		 * @return bool
		 */
		protected function time_exceeded() {
			$finish = $this->start_time + 25; // 25 seconds
			return ( time() >= $finish );
		}
		
		/**
		 * Limits exceeded.
		 *
		 * @since      1.0.0
		 * @return bool
		 */
		protected function limits_exceeded() {
			$limit = (int) get_option( 'WPA_emailing_limit', false );
			if ( ! $limit )
				return false;
			
			global $wpdb;
			$table  = $wpdb->prefix . 'WPA_subscriptions';
			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = 'sent' AND updated >= %s",  
				date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 60 )
			));
			
			return ( $count >= (int) $limit );
		}

		/**
		 * Schedule cron healthcheck
		 *
		 * @since         1.0.0
		 * @param  array  $schedules Schedules.
		 * @return array
		 */
		public function schedule_cron_healthcheck( $schedules ) {

			// Adds every 5 minutes to the existing schedules.
			$schedules[ $this->identifier . '_cron_interval' ] = array(
				'interval' => MINUTE_IN_SECONDS * 5,
				'display'  => sprintf( __( 'Every %d Minutes' ), 5 ),
			);

			return $schedules;
		}

		/**
		 * Restart the background process if not already running
		 * and data exists in the queue.
		 *
		 * @since         1.0.0
		 * @return void		 
		 */
		public function handle_cron_healthcheck() {
			if ( $this->is_process_running() || $this->limits_exceeded() ) {
				exit;
			}

			if ( $this->is_queue_empty() ) {
				// No data to process.
				$this->clear_scheduled_event();
				exit;
			}

			$this->handle();

			exit;
		}
		
		/**
		 * Cleanup sent emails from database
		 *
		 * @since         1.0.0
		 * @return void		 
		 */
		public function handle_cleanup_sent_subscriptions() {
			$start = time();
			$lifetime = WPA_Utils::get_option( 'sent_subscriptions_lifetime' );
			
			if ( ! WPA_Utils::is_absint( $lifetime ) ) {
				wp_clear_scheduled_hook( $this->cron_hook_identifier . '_cleanup_sent' );
				return;
 			}
			
			global $wpdb;
			$table = $wpdb->prefix . 'WPA_subscriptions';
			$date = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( DAY_IN_SECONDS * (int) $lifetime ) );
			
			do {
			
				$count = (int) $wpdb->query( 
					"DELETE FROM $table WHERE status = 'sent' AND updated <= '$date' ORDER BY updated ASC LIMIT 10" 
				);

			} while ( ( time() - $start ) < 25 && ! $this->memory_exceeded() && $count > 9 );

			if ( $count < 10 )
				$this->reschedule_sent_subscriptions_cron( $lifetime );

		}

		/**
		 * Reschedule delete sent emails cron
		 *
		 * @since       1.0.0
		 * @param int   $lifetime
		 * @return void		 
		 */
		public function reschedule_sent_subscriptions_cron( $lifetime ) {
			$lifetime = (int) $lifetime;
			$identifier = $this->cron_hook_identifier . '_cleanup_sent';		
			wp_clear_scheduled_hook( $identifier );			
			global $wpdb;
			$table = $wpdb->prefix . 'WPA_subscriptions';
			
			$min_date = $wpdb->get_var( "SELECT MIN(updated) FROM $table WHERE status = 'sent'" );
			if ( ! is_string( $min_date ) )
				return;
			
			$min_date = mysql2date( 'U', $min_date );
			$new_next_run = $min_date + ( DAY_IN_SECONDS * $lifetime );
			
			if ( ( current_time( 'timestamp' ) + 10 ) < $new_next_run ) {
				wp_schedule_event( $new_next_run, 'daily', $identifier );
			} else {
				wp_schedule_event( current_time( 'timestamp' ) + 10, 'daily', $identifier );
			}
		}		
		
		/**
		 * Clear scheduled event
		 *
		 * @since         1.0.0
		 * @return void		 
		 */		 
		protected function clear_scheduled_event() {
			$timestamp = wp_next_scheduled( $this->cron_hook_identifier );

			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
			}
		}	
		
		/**
		 * Check whether the current process is already running
		 * in a background process.
		 *
		 * @since         1.0.0
		 * @return void		 
		 */		 
		protected function is_process_running() {
			return (bool) get_site_transient( $this->identifier . '_process_lock' );
		}

		/**
		 * Lock the process so that multiple instances can't run simultaneously.
		 * Duration should be greater than that defined in the time_exceeded() method.
		 *		 
		 * @since         1.0.0
		 * @return void		 
		 */
		protected function lock_process() {
			$this->start_time = time();
			set_site_transient( $this->identifier . '_process_lock', microtime(), 60 );
		}

		/**
		 * Unlock process
		 *		 
		 * @since         1.0.0
		 * @return void		 
		 */
		protected function unlock_process() {
			delete_site_transient( $this->identifier . '_process_lock' );
		}
	}
}