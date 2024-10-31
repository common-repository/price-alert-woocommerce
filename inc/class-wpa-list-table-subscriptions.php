<?php
/**
 * Handle admin subscriptions list table
 *
 * @link       https://pricealert/price-alert-woocommerce/
 * @since      1.0.0
 * @package    MDWC_Price_Tracker/Admin/Classes
 */	

if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists( 'WPA_List_Table_Subscriptions' ) ) {

	require_once( WPA_DIR . '/inc/abstracts/abstract-class-wpa-list-table.php' );

	/**
	 * Displaying a list of subscriptions in an ajaxified HTML table.
	 */	
	class WPA_List_Table_Subscriptions extends WPA_List_Table {
		
		/**
		 * Array of WC_Product objects used on current page [ 'subscription_id' => $product, .... ]
		 *
		 * @var        array
		 * @since      1.0.0
		 * @access     protected
		 */			
		protected $_products = array();
		
		/**
		 * Array holds subscriptions count per status [ 'status_name' => 123, .... ]
		 *
		 * @var        array
		 * @since      1.0.0
		 * @access     protected
		 */			
		protected $counts; 
		
		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 */		
		public function __construct() {
			parent::__construct( array (
				'singular' => 'WPA_subscription',
				'plural' => 'WPA_subscriptions',
			) );
			
			global $wpdb;				
			$table = $wpdb->prefix . 'WPA_subscriptions';
			$this->counts = $wpdb->get_row(
				"SELECT SUM(status = 'active') AS active, SUM(status = 'queued') AS queued, SUM(status = 'sent') AS sent FROM $table",
				ARRAY_A
			);
			
			/** 
			 * The emailer queue can become empty very quickly if there is not much subscriptions. 
			 * This can lead to a situation where the user clicking on a link with several items, he can find an empty list after redirection.
			 * After it happens, to avoid confusion we simply redirect user to Sent subscriptions list
			 */
			if ( isset( $_GET['status'] ) && 'queued' === $_GET['status'] && ! $this->counts['queued'] && $this->counts['sent'] && 2 === count( $_GET ) ) {
				wp_redirect( self_admin_url( 'admin.php?page=wpa-subscriptions&status=sent' ) );
				exit;
			}
		}
		
		/**
		 * Prepares the list of items for displaying.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function prepare_items() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'WPA_subscriptions';
			
			$per_page_option = get_current_screen()->get_option('per_page');
			$per_page = (int) get_user_meta( get_current_user_id(), $per_page_option['option'], true ) ? :  $per_page_option['default'];
			$per_page = $per_page > 0 && $per_page <= 50  ? $per_page : 10;
			
			$this->_column_headers = $this->get_column_info();
			// process bulk action if any
			$this->process_bulk_action();
			// will be used in pagination settings
			$status = ( isset( $_GET['status'] ) && in_array( $_GET['status'], ['active', 'queued', 'sent'] ) ) ? $_GET['status'] : 'active';		
			// prepare query params, as usual current page, order by and order direction
			$paged = isset( $_GET['paged'] ) ? max( 0, intval( $_GET['paged'] - 1 ) * $per_page ) : 0;
			$orderby = ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], array_keys( $this->get_sortable_columns() ) ) ) 
				? $_GET['orderby'] : 'created';
			$order = ( isset( $_GET['order'] ) && in_array( $_GET['order'], array('asc', 'desc') ) ) ? $_GET['order'] : 'asc';
			$order = ( ! isset( $_GET['orderby'] ) && ! isset( $_GET['order'] ) ) ? 'desc' : $order;

			
			$join_clause = "";
			
			$where_clause = $wpdb->prepare( 
				" WHERE s.status = %s", $status
			);			
			
			if ( isset( $_GET['s'] ) && is_string( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
				if ( is_email( $_GET['s'] ) ) {
					$where_clause .= $wpdb->prepare( " AND s.email = %s", $_GET['s'] );
				} elseif ( WPA_Utils::is_absint( $_GET['s'] ) ) {
					$where_clause .= $wpdb->prepare( " AND s.product_id = %s", $_GET['s'] );
				} else {
					$join_clause .= " LEFT JOIN $wpdb->posts AS p ON p.ID = s.product_id LEFT JOIN $wpdb->users AS u ON u.user_email = s.email";
					$s = '%' . $wpdb->esc_like( $_GET['s'] ) . '%';
					$where_clause .= $wpdb->prepare( " AND ( p.post_title like %s OR u.display_name like %s )", $s, $s );				
				}		
			}
		
			// get $items
			$this->items = (array) $wpdb->get_results( $wpdb->prepare (
				"SELECT s.id, s.product_id as product, s.email, s.price as expected_price, s.created, s.updated
				FROM $table_name AS s $join_clause$where_clause ORDER BY s.$orderby $order LIMIT %d OFFSET %d", 
			$per_page, $paged ), ARRAY_A );
			//echo '<pre>'; var_dump( $this->items ); echo '</pre>';
			foreach( $this->items as $k=>$item ) {
				$this->_products[ $item['id'] ] = wc_get_product( $item[ 'product' ] );
			}			 
			// configure pagination
			$this->set_pagination_args( array(
				'total_items' => $this->counts[ $status ], // total items defined above
				'per_page' => $per_page, // per page constant defined at top of method
				'total_pages' => ceil( $this->counts[ $status ] / $per_page ) // calculate pages count
			));
		} 
		
		/**
		 * Default column display method used if 'column_$name' method is not undefined
		 *
		 * @since 1.0.0
		 * @return string
		 */		
		public function column_default($item, $column_name){
			return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
		}
		
		/**
		 * Add columns to grid view
		 *
		 * @since 1.0.0
		 * @return array
		 */
		public function get_columns(){
			$columns = array(		
				'cb'         	=> '<input type="checkbox" />',
				'product' 	    => __( 'Product', 'wpa' ),
				'current_price' => __( 'Current price', 'wpa' ),			
				'expected_price'=> __( 'Expected price', 'wpa' ),
				'email'      	=> __( 'User', 'wpa' ),
				'created'	    => __( 'Created', 'wpa' ),
				'updated'	    => __( 'Updated', 'wpa' ),
			);
			if ( isset( $_GET['status'] ) && 'sent' === $_GET['status'] ) {
				$columns['updated'] = __( 'Sent', 'wpa' );
			} elseif ( isset( $_GET['status'] ) && 'queued' === $_GET['status'] ) {
				$columns['updated'] = __( 'Queued', 'wpa' );
			}
			return $columns;
		}
		
		/**
		 * Get a list of sortable columns.
		 *
		 * @since 1.0.0
		 * @return array
		 */		
		public function get_sortable_columns() {
			return array(
				//'product'      => array( 'product', false ),
				'created'	     => array( 'created', false ),
				'updated'        => array( 'updated', false ),
				'expected_price' => array( 'price', false ),
				//'email'        => array( 'email', false ),
			);
		}
		
		/**
		 * Display checkbox column
		 *
		 * @since 1.0.0
		 * @return string
		 */			
		public function column_cb( $item ) {
			return sprintf(
				'<input type="checkbox" name="id[]" value="%s" />',
				$item['id']
			);
		}
		
		/**
		 * Display subscription product column
		 *
		 * @since 1.0.0
		 * @return string
		 */			
		public function column_product( $item ) {		
			
			$edit_url = $this->_products[ $item['id'] ]->is_type('variation')
				? get_edit_post_link( $this->_products[ $item['id'] ]->get_parent_id() )
				: get_edit_post_link( $item['product'] ); 
			
			$status = isset( $_GET['status'] ) && in_array( $_GET['status'], ['sent', 'queued'] ) ? '&status=' . $_GET['status'] : '';
			$paged = $this->get_pagenum() > 1 ? '&paged=' . $this->get_pagenum() : '';
			$actions = array(
				'edit'   => sprintf('<a target="_blank" href="%s">%s</a>', esc_url( $edit_url ), __('Edit product', 'wpa')),
				'delete' => sprintf(
					'<a href="?page=%s%s%s&action=delete&id[]=%s&_wpnonce=%s">%s</a>', 
					$_GET['page'], 
					$status,
					$paged,
					$item['id'],
					wp_create_nonce( 'bulk-' . $this->_args['plural'] ),
					__('Delete subscription', 'wpa')
				),
			);		
						
			$variation = $this->_products[ $item['id'] ]->is_type('variation') ? sprintf( '<small> (%s)</small>', __( 'variation', 'wpa' ) ) : '';
				
			return sprintf(
				'<a title="%s" target="_blank" href="%s">%s</a>%s%s',
				__('View product', 'wpa'),
				esc_url( get_permalink( $item['product'] ) ),
				esc_html( $this->_products[ $item['id'] ]->get_name() ),
				$variation,
				$this->row_actions( $actions )
			);
		}
		
		/**
		 * Display subscription requested price column
		 *
		 * @since 1.0.0
		 * @return string
		 */			
		public function column_expected_price( $item ) {
			
			if ( isset( $_GET['status'] ) )
				return wc_price( $item['expected_price'] );
			
			$current_price = $this->_products[ $item['id'] ]->get_price();
			$requested_price = $item['expected_price'];		
			$discount = 100 - ( ( $requested_price * 100 ) / $current_price );
			
			return sprintf( '%s <small>(-%s%%)</small>', wc_price( $item['expected_price'] ), round( $discount, 2 ) );
		}
		
		/**
		 * Display subscription current price column
		 *
		 * @since 1.0.0
		 * @return string
		 */			
		public function column_current_price( $item ) {
			$price = wc_price( $this->_products[ $item['id'] ]->get_price() );
			$price .= $this->_products[ $item['id'] ]->is_on_sale() 
				? sprintf(' <small class="wpa-sale">[%s]</small>', __( 'now on sale', 'wpa' ) ) : '';

			if ( ! isset( $_GET['status'] ) ) {
				$paged = $this->get_pagenum() > 1 ? '&paged=' . $this->get_pagenum() : '';
				$actions = array(
					'reduce' => sprintf(
						'<a href="?page=%s%s&action=reduce&id[]=%s&_wpnonce=%s">%s &#10132;</a>', 
						$_GET['page'], 
						$paged,
						$item['id'], 
						wp_create_nonce( 'bulk-' . $this->_args['plural'] ),
						__('Reduce to expected', 'wpa')
					),
				);
				$price .= $this->row_actions( $actions );
			} else {						
				$requested_price = $item['expected_price'];		
				//$discount = 100 - ( ( $requested_price * 100 ) / $current_price );			
				//$price = sprintf( '%s <small>(%s%%)</small>', $price, round( $discount, 2 ) );			
			}	
			
			return $price;
		}
		
		/**
		 * Display subscription email column
		 *
		 * @since 1.0.0
		 * @return string
		 */			
		public function column_email( $item ) {		
			$user = get_user_by( 'email', $item['email'] );
			
			if ( ! $user instanceof WP_User )
				return esc_html( $item['email'] );
			
			$url = current_user_can( 'edit_user', $user->ID ) ? get_edit_user_link( $user->ID ) : get_author_posts_url( $user->ID, $user->get('user_nicename') );
			
			return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $user->get( 'display_name') ) );
		}		
		
		/**
		 * Display subscription date column
		 *
		 * @since 1.0.0
		 * @return string
		 */			
		protected function _column_date( $mysql_date ) {
			$date = mysql2date( get_option( 'date_format', 'd.m.Y' ), $mysql_date );
			$time = mysql2date( get_option( 'time_format', 'H:i' ), $mysql_date );
			return sprintf( '%s<br />%s', $date, $time );
		}	

		/**
		 * Display subscription create date column
		 *
		 * @since 1.0.0
		 * @return string
		 */			
		public function column_created( $item ) {
			return $this->_column_date( $item['created'] );
		}

		/**
		 * Display subscription updated date column
		 *
		 * @since 1.0.0
		 * @return string
		 */		
		public function column_updated( $item ) {
			return $this->_column_date( $item['updated'] );
		}
		
		/**
		 * Checks the current user's permissions
		 *
		 * @since 1.0.0
		 * @return bool
		 */
		public function ajax_user_can() {
			current_user_can('manage_woocommerce');
		}	
		
		/**
		 * Get an associative array ( id => link ) with the list
		 * of views available on this table.
		 *
		 * @since 1.0.0
		 * @return array
		 */		
		public function get_views() {		
			
			$links = array(
				'active' => __( 'Active', 'wpa' ),
				'queued' => __( 'Queued to be sent', 'wpa' ),
				'sent'   => __( 'Sent', 'wpa' )			
			);

			foreach( $links as $status => $label ) {
				
				if ( ! $this->counts[ $status ] && $status !== 'active' ) {
					unset( $links[ $status ] );
					continue;
				}
				
				if ( ( isset( $_GET['status'] ) && $_GET['status'] === $status ) || ( 'active' === $status && ! isset( $_GET['status'] ) ) ) {
					$url = '';
				} else {
					$url = ( 'active' === $status )
						? self_admin_url( 'admin.php?page=wpa-subscriptions' ) 
						: self_admin_url( 'admin.php?page=wpa-subscriptions&status=' . $status );
				}
				
				$atts = ! empty( $url ) ? sprintf( 'href="%s"', $url ) : 'class="current" aria-current="page"';
				$count = isset( $this->counts[ $status ] ) ? $this->counts[ $status ] : 0;
				
				$links[ $status ] = sprintf( '<a %s>%s <span class="count">(%d)</span></a>', $atts, $label, (int) $count );
			}
			
			return $links;
		}	
		
		/**
		 * Get an associative array ( option_name => option_title ) with the list
		 * of bulk actions available on this table.
		 *
		 * @since 1.0.0
		 * @return array
		 */
		public function get_bulk_actions() {		
			
			$actions = array( 'delete' => __( 'Delete subscriptions', 'wpa' ));
			
			if ( ! isset( $_GET['status'] ) )
				$actions['reduce'] = __( 'Reduce prices to expected', 'wpa' );
		
			return $actions;
		}
		
		/**
		 * Process bulk actions
		 *
		 * @since 1.0.0
		 * @access protected
		 * @return void
		 */		
		protected function process_bulk_action() {
			global $wpdb;	
			$action = $this->current_action();
			
			if ( $action )
				check_admin_referer( 'bulk-'  . $this->_args['plural'] );
			
			$table_name = $wpdb->prefix . 'WPA_subscriptions';
			$ids = isset( $_GET['id'] ) && is_array( $_GET['id'] ) ? array_map( 'intval', array_filter( $_GET['id'], 'ctype_digit' ) ) : array();			
			
			$ids = implode(',', $ids);
			if ( 'delete' === $action && ! empty( $ids ) ) {           			
				$count = (int) $wpdb->query( "DELETE FROM $table_name WHERE id IN($ids)" );
			} elseif ( 'reduce' === $action && ! empty( $ids ) ) {
				$items = $wpdb->get_results( "SELECT id, price, product_id FROM $table_name WHERE id IN($ids) AND status = 'active'", ARRAY_A );
				//products may be duplicated in single request beacause multiple users may subscribe on same product... 
				//so instead of process all of them we have to just select requests with lowest expected price discount
				$filtered = array();
				foreach( $items as $item ) {
					if ( ! isset( $filtered[ (int) $item['product_id'] ] ) || (int) $filtered[ (int) $item['product_id'] ]['price'] > (int) $item['price'] )
						$filtered[ (int) $item['product_id'] ] = $item;
				}
				$count = 0;
				foreach( $filtered as $item ) {
					$product = wc_get_product( (int) $item['product_id'] );
					$sale_price = $product->get_sale_price( 'edit' );
					if ( $product->is_on_sale() )
						$product->set_sale_price( (string) $item['price'] );
					else 
						$product->set_regular_price( (string) $item['price'] );

					$product->save();
					$count++;
				}
			}
			
			if ( isset( $count ) && $count > 0 ) {
				update_option( 'WPA_subscriptions_processed_' . $action, $count, false );
				$redirect_url = self_admin_url( 'admin.php?page=wpa-subscriptions&wpa-action=' . $action );
				if ( isset( $_GET['status'] ) && in_array( $_GET['status'], [ 'queued', 'sent' ] ) )
					$redirect_url = add_query_arg( [ 'status' => $_GET['status'] ], $redirect_url );
				if ( $this->get_pagenum() > 1 )
					$redirect_url = add_query_arg( [ 'paged' => $this->get_pagenum() ], $redirect_url );			
				wp_safe_redirect( esc_url_raw( $redirect_url ) );
				exit;
			}
		}	
	}
}