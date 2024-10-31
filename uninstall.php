<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link              https://dhrubokinfotech.com/woocommerce-price-alert/
 * @since             1.0.0
 * @package           woocommerce-price-alert
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || ! current_user_can( 'delete_plugins' ) ) {
	exit;
}

function WPA_uninstall() {
	//require_once( dirname( __FILE__ ) . '/wc-price-tracker.php' );
	$cleanup = get_option( 'WPA_uninstall_cleanup', false );
	if ( 'yes' !== $cleanup )
		return;
		
	global $wpdb;
	$table = $wpdb->prefix . 'WPA_subscriptions';
	$wpdb->query( "DROP TABLE IF EXISTS $table" );	
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'WPA_%'" );
}

if ( function_exists( 'is_multisite' ) && is_multisite() ) {
    global $wpdb;
    foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ) as $blog_id ) {
        switch_to_blog( $blog_id );
		WPA_uninstall();
        restore_current_blog();       
    }
} else {    
    WPA_uninstall();   
}