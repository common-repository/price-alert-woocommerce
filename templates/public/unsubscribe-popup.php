<?php
/**
 * Unsubscribe form
 *
 * @package MDWC_Price_Tracker/Templates/Public
 * @since 1.0.0
 */

if ( ! defined('ABSPATH') ) exit; 
?>

<div class="wpa-bg wpa-popup">
	<div class="wpa-form">
		<i class="wpa-close"></i>
		<h3 class="wpa-form-head"><?php echo esc_html( $form_title );?></h3>
		<div class="wpa-field">
			<div class="wpa-msg <?php echo $class;?>">
				<?php echo esc_html( $message );?>
			</div>
		</div>
	</div>
</div>