<?php
/**
 * Slightly adapted registry pattern... act as container for main plugin objects
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Classes
 */	

if ( ! defined('ABSPATH') ) die;

if ( ! class_exists( 'WPA_Registry' ) ) { 
 
	/**
	 * Registry class
	 */	
	class WPA_Registry {

		/**
		 * Storage for objects
		 *
		 * @var        array
		 * @since      1.0.0
		 * @access     private
		 */		
		private $storage;
		
		public function __construct() {			
			$this->storage = array();
		}

		/**
		 * Adds object to storage if key not already there
		 *
		 * @since                1.0.0	 
		 * @param string         $id
		 * @param WPA_Object    $object
		 * @return null|WPA_Object
		 */	
		public function add( $id, WPA_Object $object ) {
			
			if ( is_string( $id ) && ! isset( $this->storage[$id] ) ) {
				$this->storage[$id] = $object;
				$this->storage[$id]->hookup();
				return $this->storage[$id];
			}
			
			return null;
		}

		/**
		 * Get WPA_Object
		 *
		 * @since                1.0.0	 
		 * @param string         $id
		 * @param WPA_Object    $object
		 * @return null|WPA_Object
		 */		
		public function get( $id ) {
			
			return is_string( $id ) && isset( $this->storage[$id] ) ? $this->storage[$id] : null;
		
		}
	}
}