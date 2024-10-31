<?php
/**
 * Interface WPA_Object
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @version    1.0.0
 * @package    MDWC_Price_Tracker/Interfaces
 */

if ( ! defined('ABSPATH') ) die;

interface WPA_Object {
	
	/**
	 * All class hooks should be placed in this method
	 *
	 * @return void
	 */	
	public function hookup();
}