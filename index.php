<?php

/**
 * @link              https://dhrubokinfotech.com/woocommerce-price-alert/
 * @version           1.0.3
 * @package           woocommerce-price-alert
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Price Alert
 * Plugin URI:        https://dhrubokinfotech.com/
 * Description:       Boost woocommerce based shops sales by letting customers to subscribe on product cheapening
 * Version:           1.0.4
 * Author:            Dhrubok Infotech
 * Author URI:        https://dhrubokinfotech.com/
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       woocommerce-price-alert
 * Domain Path:       /languages
 */

if ( ! defined('ABSPATH') ) die;

//consts
define( 'WPA_VERSION', '1.0.4' );
define( 'WPA_DIR', dirname( __FILE__ ) );
define( 'WPA_URL', plugins_url( '', __FILE__ ) );
define( 'WPA_BASENAME', plugin_basename( __FILE__ ) );

require_once WPA_DIR . '/inc/class-wpa-activator.php';
register_activation_hook( __FILE__, array( 'WPA_Activator', 'activate' ) );

require_once WPA_DIR . '/inc/class-wpa-deactivator.php';
register_deactivation_hook( __FILE__, array( 'WPA_Deactivator', 'deactivate' ) );

/**
 * This method allow global access to shared plugin objects without singletons or global variables
 * and also makes easier to unhook anything hooked by plugin
 *
 * @return WPA_Registry
 */
function wpa() {

	static $registry = null;

	if ( is_null( $registry ) ) {
		require_once WPA_DIR . '/inc/class-wpa-registry.php';
		$registry = new WPA_Registry();
	}

	return $registry;
}
require_once( WPA_DIR . '/inc/interfaces/interface-wpa-object.php' );
require_once WPA_DIR . '/inc/class-wpa-loader.php';
wpa()->add( 'loader', new WPA_Loader() );


function next_menus_development() {
	add_submenu_page("wpa-subscriptions", "Price Alert Setting", "Price Alert Setting", "manage_options", "wc-settings&tab=wpa", " ");
}
add_action("admin_menu", "next_menus_development");

function wpa_enqueue_admin_script() {
	wp_enqueue_style( 'admin-css', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', array(), WPA_VERSION );
}
add_action( 'admin_enqueue_scripts', 'wpa_enqueue_admin_script' );
