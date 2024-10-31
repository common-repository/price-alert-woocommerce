<?php
/**
 * Desription
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Classes
 */

if ( ! defined('ABSPATH') ) die;

if ( ! class_exists( 'WPA_Shared' ) ) {
 
	/**
	 * Shared class
	 */	
	class WPA_Shared implements WPA_Object {
		
		/**
		 * Email of user currently being deleted
		 *
		 * @var        string
		 * @since      1.0.0
		 * @access     private
		 */		
		private $email_to_delete;
		
		/**
		 * Hookup to wp
		 *
		 * @since      1.0.0
		 * @return     void
		 */	
		public function hookup() {			
			
			add_action( 'updated_post_meta', array( $this, 'update_price' ), 10, 4 );
			
			add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 10, 3 );
			/**
			 * Sometimes woocommerce update products directly thru $wpdb in that case 'transition_post_status' not fired
			 * We will use next two actions to cover this case
			 */
			add_action( 'woocommerce_update_product', array( $this, 'after_update_product' ) );
			add_action( 'woocommerce_update_product_variation', array( $this, 'after_update_product' ) );
            
			add_action( 'before_delete_post', array( $this, 'delete_product_subscriptions' ) );
			add_action( 'delete_user', array( $this, 'before_delete_user' ) );
		}		
		
		/**
		 * Prepare to delete user subscriptions if his account being deleted
		 *
		 * @since          1.0.0
		 * @param string   $user_id 
		 * @return void
		 */		
		public function before_delete_user( $user_id ) {
			$user = get_user_by( 'id', $user_id );
			$this->email_to_delete = $user->get( 'user_email' );
			add_action( 'deleted_user', array( $this, 'delete_user_subscriptions' ) );
		}	

		/**
		 * Delete user subscriptions if his account being deleted
		 *
		 * @since          1.0.0
		 * @param string   $user_id 
		 * @return void
		 */		
		public function delete_user_subscriptions( $user_id ) {
			if ( ! is_email( $this->email_to_delete ) )
				return;
			
			global $wpdb;
			$table_name = $wpdb->prefix . 'WPA_subscriptions';
			$wpdb->query( "DELETE FROM $table_name WHERE email = '$this->email_to_delete'" );
		}		
		
		/**
		 * Delete subscriptions of product if post is deleted
		 *
		 * @since          1.0.0
		 * @param string   $pid
		 * @return void
		 */		
		public function delete_product_subscriptions( $pid ) {
			$post = get_post( $pid );
			if ( ! $post instanceof WP_Post || 'product' !== $post->post_type )
				return;
			
			global $wpdb;
			$table_name = $wpdb->prefix . 'WPA_subscriptions';
			$wpdb->query( "DELETE FROM $table_name WHERE product_id = $pid" );
		}	
		
		/**
		 * Just wrapper for $this->maybe_update_product_subscriptions()
		 *
		 * @since          1.0.0
		 * @param string   $new_status
		 * @param string   $old_status
		 * @param WP_Post  $post
		 * @return void
		 */			
		public function transition_post_status( $new_status, $old_status, $post ) {
			if ( 'publish' !== $new_status || $post->post_type !== 'product' )
				return;
			
			$product = wc_get_product( $post->ID );
			if ( ! $product instanceof WC_Product || $product->is_type( ['variable', 'grouped'] ) )
				return;
			
			$price = $product->get_price( 'edit' );
			if ( is_numeric( $price ) )	
				$this->maybe_update_product_subscriptions( $post->ID, $price );
		}

		/**
		 * Just wrapper for $this->maybe_update_product_subscriptions()
		 *
		 * @since            1.0.0
		 * @param int        $pid
		 * @return void
		 */			
		public function after_update_product( $pid ) {			
			$product = wc_get_product( $pid );
			if ( ! $product instanceof WC_Product )
				return;
			
			if ( $product->is_type( ['variable', 'grouped'] ) || 'publish' !== $product->get_status( 'edit' ) )
				return;
			
			$price = $product->get_price( 'edit' );
			if ( is_numeric( $price ) )	
				$this->maybe_update_product_subscriptions( $pid, $price );
		}		
		
		/**
		 * Just wrapper for $this->maybe_update_product_subscriptions()
		 *
		 * @since      1.0.0
		 * @param string $meta_id
		 * @param int    $pid
		 * @param string $meta_key
		 * @param mixed  $value
		 * @return void
		 */			
		public function update_price( $meta_id, $pid, $meta_key, $value ) {
			if ( '_price' !== $meta_key || ! is_numeric( $value ) )
				return;
			
			$product = wc_get_product( $pid );
			if ( ! $product instanceof WC_Product || $product->is_type( ['variable', 'grouped'] ) || 'publish' !== $product->get_status() )
				return;
			
			$this->maybe_update_product_subscriptions( $pid, $value );
		}

		/**
		 * Switch subscriptions status to queued if price reduced to expected and trigger emailer
		 *
		 * @since      1.0.0
		 * @param int    $pid
		 * @param string $price
		 * @return void
		 */		
		private function maybe_update_product_subscriptions( $pid, $price ) {
			
			global $wpdb;
			$table_name = $wpdb->prefix . 'WPA_subscriptions';
			
			//if the price has increased back and letter is still in the queue we must set status back to active
			$wpdb->query( $wpdb->prepare(
				"UPDATE $table_name SET status = 'active', queued = '0000-00-00 00:00:00', updated = %s 
				WHERE product_id = %d AND status = 'queued' AND price < %f", 
				current_time( 'mysql' ), $pid, (float) $price
			));	
			
			$count = (int) $wpdb->query( $wpdb->prepare(
				"UPDATE $table_name SET status = 'queued', queued = %s, updated = %s WHERE product_id = %d AND status = 'active' AND price >= %f", 
				current_time( 'mysql' ), current_time( 'mysql' ), $pid, (float) $price
			));
			
			if ( $count > 0 ) {
				wpa()->get( 'emailer' )->invoke();
			}			
		}
	}
}
