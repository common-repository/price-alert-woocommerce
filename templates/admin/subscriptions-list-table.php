<?php
/**
 * Subscriptions list Table
 *
 * @package MDWC_Price_Tracker/Templates/Admin
 * @since 1.0.0
 */

if ( ! defined('ABSPATH') ) exit; 
?>

<div class="wrap">
	<h2><?php _e('Price Alert subscriptions', 'wpa')?></h2>
	<?php $this->table->views();?>
	<form id="wpa-subscriptions-table" method="GET">
		<?php $this->table->search_box( __( 'Search by product name or id, user name or email', 'wpa' ), 'subscription' );?>
		<?php if ( isset( $_REQUEST['status'] ) ) : ?>	
			<input type="hidden" name="status" value="<?php echo $_REQUEST['status'] ?>"/>
		<?php endif; ?>
		<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
		<?php $this->table->display() ?>
	</form>
</div>