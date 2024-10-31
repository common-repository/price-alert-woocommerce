"use strict";
var wpajQ = jQuery.noConflict();

wpajQ(document).on('click', '.wpa-close', function(){
	wpajQ(this).closest( '.wpa-bg' ).hide();
	wpajQ('#wpa-recaptcha').hide();
});

wpajQ(document).on('mousedown', '.wpa-bg', function( e ){
	if ( e.which === 1 && e.target === this ) {
        wpajQ(this).hide();
		wpajQ('#wpa-recaptcha').hide();
	}
});

wpajQ(document).on('click', '.wpa-trigger', function(){
	wpajQ('.wpa-bg[id="wpa-form-' + wpajQ(this).data('wpa-form') + '"]').show();
	wpajQ('#wpa-recaptcha').show();
});

wpajQ(document).on('focusin', '.wpa-form :input', function(){
	wpajQ(this).prev('.wpa-msg').hide('slow', function() { wpajQ(this).remove(); });
});

wpajQ(document).on('change', 'select[name="wpa-variation"]', function(){
	wpajQ(this).next('.wpa-gal').find('img:visible').hide('slow');
	var prc_input = wpajQ(this).closest('.wpa-form').find('input[name="wpa-price"]');
	prc_input.attr( 'max', '' );
	if ( wpajQ(this).val() !== '' ) {
		var img_id = wpajQ(this).find('option:selected').data('wpa-img');
		wpajQ(this).next('.wpa-gal').find('img[data-wpa-img="'+img_id+'"]').show('slow');

		var max_prc = wpajQ(this).find('option:selected').data('wpa-maxprice') - 1;
		prc_input.attr( 'max', max_prc );
		if ( prc_input.val() > max_prc ) {
			prc_input.val( max_prc ).attr( 'value', max_prc );
		}
	}
});

wpajQ(document).on('change', 'input[name="wpa-email"]', function(){
	//console.log( 'change' );
	if ( this.checkValidity() && wpajQ(this).val().split('@')[1].indexOf('.') > 0 ) {
		var form = wpajQ(this).closest('.wpa-form');
		form.find('button[name="wpa-submit"]').prop('disabled', true);
		var data = {
			'action' : 'WPA_email_exists',
			'key'    : form.find('input[name="wpa-key"]').val(),
			'pid'    : form.find('input[name="wpa-pid"]').val(),
			'email'  : wpajQ(this).val()
		};
		wpajQ.post( WPA_l10n.ajaxurl , data, function( resp ) {
			//console.log( resp );
			if ( 'success' in resp && 'data' in resp && resp.success ) {
				if ( resp.data === 'exists' ) {
					form.find('input[name="wpa-pass"]').prop('disabled', false).closest('.wpa-field').show('slow');
					form.find('input[name="wpa-terms"]').prop('disabled', true).closest('.wpa-field').hide('slow');
				} else {
					form.find('input[name="wpa-pass"]').val('').prop('disabled', true).closest('.wpa-field').hide('slow');
					form.find('input[name="wpa-terms"]').prop('disabled', false).closest('.wpa-field').show('slow');
				}
			} else {
				form.find('input[name="wpa-pass"]').val('').prop('disabled', true).closest('.wpa-field').hide('slow');
				form.find('input[name="wpa-terms"]').prop('disabled', false).closest('.wpa-field').show('slow');
			}
			form.find('button[name="wpa-submit"]').prop('disabled', false);
		});
	} else {
		wpajQ(this).closest('.wpa-form').find('input[name="wpa-pass"]').val('').prop('disabled', true).closest('.wpa-field').hide('slow');
		wpajQ(this).closest('.wpa-form').find('input[name="wpa-terms"]').prop('disabled', false).closest('.wpa-field').show('slow');
	}
});

/*wpajQ(document).on('focusin', 'input[name="wpa-email"]', function(){
	wpajQ(this).closest('.wpa-form').find('button[name="wpa-submit"]').prop('disabled', true);
}).on('blur', 'input[name="wpa-email"]', function(){
	if ( false === wpa['doing_email_check'] )
		wpajQ(this).closest('.wpa-form').find('button[name="wpa-submit"]').prop('disabled', false);
});*/

wpajQ(document).on('click', 'button[name="wpa-submit"]', function( e ){
	e.preventDefault();
	var data = getFormData( wpajQ( this ) );

	if ( ! data )
		return;

	if ( wpajQ('#wpa-recaptcha [name="g-recaptcha-response"]').length > 0 ) {
		wpajQ(this).prop('disabled', true);
		grecaptcha.execute();
	} else {
		wpaSubmit( wpajQ(this), data );
	}
});

function wpaCaptchaSubmit( token ) {
	wpajQ('.wpa-form input[name="wpa-captcha"]').val( token );
	var button = wpajQ('.wpa-bg:visible').find( 'button[name="wpa-submit"]' );
	if ( button.length > 0 ) {
		button.prop('disabled', false);
		wpaSubmit( button, getFormData( button ) );
	}
}

function getFormData( button ) {
	var valid = true, data = { 'action' : 'WPA_subscribe' }, valSupport = 'reportValidity' in HTMLFormElement.prototype;

	button.closest('.wpa-form').find(':input').not('[name="wpa-submit"]').each( function() {
		if ( valSupport && ! this.checkValidity() ) {
			this.reportValidity();
			valid = false;
			return false;
		} else {
			data[ wpajQ(this).attr('name').replace('wpa-', '') ] = wpajQ(this).val();
		}
	});

	return ( valid ) ? data : false;
}

function wpaSubmit( button, data ) {
	if ( data ) {
		//console.log( data );
		var form = button.closest('.wpa-form');
		var pass_state = form.find('input[name="wpa-pass"]').prop('disabled');
		form.find(':input').prop('disabled', true);
		form.find('.wpa-msg').remove();
		wpajQ.post( WPA_l10n.ajaxurl , data, function( resp ) {
			//console.log( resp );
			if ( 'success' in resp && 'data' in resp ) {
				if ( resp.success ) {
					form.find('.wpa-form-head').after(
						'<div class="wpa-field wpa-msg woocommerce-message">' + resp.data + '</div>'
					);
					form.find(':input[type="password"]').val('').attr('value','');
				} else {
					var top_error = '';
					wpajQ.each( resp.data, function( k, v ) {
						if ( form.find(':input[name="'+k+'"]').length > 0 ) {
							form.find( ':input[name="'+k+'"]').before( '<div class="wpa-msg woocommerce-error">' + v + '</div>' );
						} else {
							top_error += v + '<br />';
						}
					});
					if ( '' !== top_error ) {
						form.find('.wpa-form-head').after(
							'<div class="wpa-field wpa-msg woocommerce-error">' + top_error.substring(0,(top_error.length-6)) + '</div>'
						);
					}
				}
			}
			form.find(':input').prop('disabled', false);
			form.find('input[name="wpa-pass"]').prop('disabled', pass_state);

			if ( wpajQ('#wpa-recaptcha [name="g-recaptcha-response"]').length > 0 )
				grecaptcha.reset();
		});
	}
}

wpajQ(document).ready( function() {
	//maybe colorize base64 icon
	var img = wpajQ('.wpa-track-icon');
	if ( img.length > 0 && img.css('background-image').substr( 0, 31 ) === 'url("data:image/svg+xml;base64,' ) {
		var encoded = img.css('background-image').substr( 0, img.css('background-image').length - 2 ).substr( 31 );
		var wrapper = wpajQ('<div></div>');
		wrapper.append( atob(encoded) ).find('path').css('fill', img.parent().css( 'color' ) );
		img.css( 'background-image', 'url("data:image/svg+xml;base64,' + btoa( wrapper.html() ) + '")' );
	}
	wpajQ('.wpa-trigger > .wpa-track-icon').css('opacity', '0.7');

	//colorize popup header
	// wpajQ( '.wpa-form-head' ).css({
	// 	'background-color' : wpajQ('.wpa-form-head').closest('.wpa-form').find('button[name="wpa-submit"]').css('background-color'),
	// 	'color'            : wpajQ('.wpa-form-head').closest('.wpa-form').find('button[name="wpa-submit"]').css('color'),
	// });
	// wpajQ( '.wpa-close' ).css({
	// 	'color'            : wpajQ('.wpa-form-head').closest('.wpa-form').find('button[name="wpa-submit"]').css('color'),
	// });

	//open form by hash
	if ( location.hash.indexOf( '#wpa-form-' ) === 0 && wpajQ( location.hash ).length > 0 ) {
		wpajQ( location.hash ).show();
	}
});
