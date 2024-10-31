<?php
/**
 * This class is loaded during plugin activation
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Classes
 */

if ( ! defined('ABSPATH') ) die;

/**
 * Pugin deactivation class
 */
class WPA_Deactivator {

	/**
	 * Handle plugin deactivation
	 *
	 * @since    1.0.0
	 */
	public static function deactivate( $network_wide ) {        

		if ( is_multisite() && $network_wide ) {
			global $wpdb;
			foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ) as $blog_id ) {
				switch_to_blog( (int) $blog_id );
				self::blog_deactivation();
				restore_current_blog();
			}
		} else {
			self::blog_deactivation();
		}
	}
	
	/**
	 * Handle plugin deactivation
	 *
	 * @since    1.0.0
	 */
	public static function blog_deactivation() {	
		wp_clear_scheduled_hook( wpa()->get('emailer')->cron_hook_identifier );
		wp_clear_scheduled_hook( wpa()->get('emailer')->cron_hook_identifier . '_cleanup_sent' );	
	}
}