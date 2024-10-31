<?php
/**
 * Price alert subscription form
 *
 * @package MDWC_Price_Tracker/Templates/Public
 * @since 1.0.0
 */

if ( ! defined('ABSPATH') ) exit;
?>

<div class="wpa-bg" id="wpa-form-<?php echo esc_attr( $pid );?>">
	<div class="wpa-form one">
		<i class="wpa-close"></i>
		<h3  class="wpa-form-head"><?php echo esc_html( $form_title );?></h3>
			<div class="logo">
				<i class="fa fa-bell"></i>
			</div>
			<h2 class="heading">
				please subscribe to get updates
			</h2>
		<?php if( ! is_user_logged_in() ) : ?>
			<div class="wpa-field wpa-field-email">
			<?php if($lebel_show == 'yes'):?>
				<label for="wpa-email-<?php echo esc_attr( $pid );?>"><?php echo esc_html( $email_title );?><span class="wpa-req">*</span></label>
			<?php endif;?>
				<input required type="email" name="wpa-email" id="wpa-email-<?php echo esc_attr( $pid );?>" class="wpa-input" placeholder="<?php echo esc_attr( $email_palceholder );?>" />
			</div>
			<?php if( 'yes' === $force_pass)  : ?>
				<div class="wpa-field wpa-field-pass wcpt-hidden">
					<?php if($lebel_show == 'yes'):?>
						<label for="wpa-pass-<?php echo esc_attr( $pid );?>"><?php echo esc_html( $pass_title );?></label>
					<?php endif;?>
					<input disabled required type="password" name="wpa-pass" id="wpa-pass-<?php echo esc_attr( $pid );?>" class="wpa-input" placeholder="<?php echo esc_html( $pass_title );?>"/>
				</div>
			<?php endif;?>
		<?php endif;?>
		<?php if( isset( $var_options ) && ! empty( $var_options ) ) : ?>
			<div class="wpa-field wpa-field-variation">
				<?php if($lebel_show == 'yes'):?>
					<label for="wpa-variation-<?php echo esc_attr( $pid );?>"><?php echo esc_html( $var_title );?><span class="wpa-req">*</span></label>
				<?php endif;?>
				<select required class="wpa-input" name="wpa-variation" id="wpa-variation-<?php echo esc_attr( $pid );?>">
					<option value=""><?php echo esc_html( $var_def_option );?></option>
					<?php echo $var_options;?>
				</select>
				<div class="wpa-gal"><?php echo $var_images;?></div>
			</div>
		<?php endif;?>
		<div class="wpa-field wpa-field-price">
			<?php if($lebel_show == 'yes'):?>
				<label for="wpa-price-<?php echo esc_attr( $pid );?>"><?php echo esc_html( $price_title );?><span class="wpa-req">*</span></label>
			<?php endif;?>
			<input required type="number" name="wpa-price" placeholder="<?php echo esc_html( $price_title );?>" id="wpa-price-<?php echo esc_attr( $pid );?>" class="wpa-input" min="1" max="<?php echo esc_attr( $max_price );?>" />
		</div>
		<?php if( $privacy_url ) : ?>
			<div class="wpa-field wpa-field-privacy">
				<small class="wpa-small"><?php echo $privacy_label;?></small>
			</div>
		<?php endif;?>
		<div class="wpa-field wpa-field-submit">
			<input type="hidden" name="wpa-pid" value="<?php echo esc_attr( $pid );?>" />
			<input type="hidden" name="wpa-key" value="<?php echo wp_create_nonce( 'wpa-subscription-' . $pid ) ;?>" />
			<input type="hidden" name="wpa-captcha" value="" />
			<button type="submit" class="btn" name="wpa-submit"><?php echo esc_html( $label );?></button>
		</div>
	</div>
</div>
