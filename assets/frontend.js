/**
 * Gift card product form and the classic cart / checkout gift card box.
 *
 * @package Lafu_Gift_Cards
 */
( function () {
	'use strict';

	function each( selector, callback, root ) {
		Array.prototype.forEach.call( ( root || document ).querySelectorAll( selector ), callback );
	}

	// Product form: show the own amount field and the gift fields only when needed.
	function syncProductForm( form ) {
		var custom = form.querySelector( '[data-lafu-gc-custom]' );
		if ( custom ) {
			var picked = form.querySelector( 'input[name="lafu_gc_amount"]:checked' );
			var isCustom = ! picked || 'custom' === picked.value;
			custom.hidden = ! isCustom;
			var input = custom.querySelector( 'input' );
			if ( input ) {
				input.required = isCustom;
			}
		}
		var toggle = form.querySelector( '[data-lafu-gc-gift]' );
		var fields = form.querySelector( '[data-lafu-gc-gift-fields]' );
		if ( toggle && fields ) {
			fields.hidden = ! toggle.checked;
			var email = fields.querySelector( '#lafu_gc_recipient_email' );
			if ( email ) {
				email.required = toggle.checked;
			}
		}
	}

	// Live card preview on the product page.
	function money( form, value ) {
		var number = parseFloat( String( value ).replace( ',', '.' ) );
		if ( isNaN( number ) ) {
			return '';
		}
		var cfg;
		try {
			cfg = JSON.parse( form.getAttribute( 'data-money' ) || '{}' );
		} catch ( e ) {
			cfg = {};
		}
		var decimals = 'number' === typeof cfg.decimals ? cfg.decimals : 2;
		var parts = Math.abs( number ).toFixed( decimals ).split( '.' );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, undefined === cfg.thou ? ',' : cfg.thou );
		var amount = ( number < 0 ? '-' : '' ) + parts.join( undefined === cfg.dec ? '.' : cfg.dec );
		// Same order and spacing as WooCommerce: %1$s is the symbol, %2$s the amount.
		return ( cfg.format || '%1$s%2$s' ).replace( '%1$s', cfg.symbol || '' ).replace( '%2$s', amount );
	}

	function paintPreview( form ) {
		var card = form.querySelector( '.lafu-gc-preview .lafu-gc-card' );
		if ( ! card ) {
			return;
		}
		var picked = form.querySelector( 'input[name="lafu_gc_amount"]:checked' );
		var custom = form.querySelector( '#lafu_gc_custom_amount' );
		var value = picked && 'custom' !== picked.value ? picked.value : custom ? custom.value || custom.getAttribute( 'min' ) : '';
		var amount = money( form, value );
		if ( amount ) {
			card.querySelector( '[data-lafu-gc-preview-amount]' ).textContent = amount;
		}
		var gift = form.querySelector( '[data-lafu-gc-gift]' );
		var fields = form.querySelector( '[data-lafu-gc-gift-fields]' ) || form.querySelector( '.lafu-gc-gift-fields' );
		var show = ! gift || gift.checked;
		var to = show && form.querySelector( '#lafu_gc_recipient_name' ) ? form.querySelector( '#lafu_gc_recipient_name' ).value.trim() : '';
		var anonymous = form.querySelector( 'input[name="lafu_gc_anonymous"]' );
		var from = show && fields && form.querySelector( '#lafu_gc_sender_name' ) && ! ( anonymous && anonymous.checked ) ? form.querySelector( '#lafu_gc_sender_name' ).value.trim() : '';
		var parts = [];
		if ( to ) {
			parts.push( ( form.getAttribute( 'data-for' ) || '%s' ).replace( '%s', to ) );
		}
		if ( from ) {
			parts.push( ( form.getAttribute( 'data-from' ) || '%s' ).replace( '%s', from ) );
		}
		card.querySelector( '[data-lafu-gc-preview-people]' ).textContent = parts.join( ' · ' );
	}

	each( '.lafu-gc-form', function ( form ) {
		syncProductForm( form );
		paintPreview( form );
		form.addEventListener( 'change', function () {
			syncProductForm( form );
			paintPreview( form );
		} );
		form.addEventListener( 'input', function () {
			paintPreview( form );
		} );
	} );

	// Classic cart and checkout box.
	var config = window.lafuGiftCards;
	if ( ! config ) {
		return;
	}

	function refresh() {
		if ( document.querySelector( 'form.checkout' ) && window.jQuery ) {
			window.jQuery( document.body ).trigger( 'update_checkout' );
		} else {
			window.location.reload();
		}
	}

	function post( endpoint, fields ) {
		var body = new URLSearchParams( fields );
		body.append( 'nonce', config.nonce );
		return fetch( config.endpoint.replace( '%%endpoint%%', endpoint ), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function say( box, text, isError ) {
		var message = box.querySelector( '[data-lafu-gc-message]' );
		if ( message ) {
			message.textContent = text;
			message.classList.toggle( 'is-error', !! isError );
		}
	}

	function apply( box, fields ) {
		say( box, '' );
		post( 'lafu_gc_apply', fields )
			.then( function ( result ) {
				if ( result && result.success ) {
					say( box, result.data.message );
					refresh();
				} else {
					say( box, ( result && result.data && result.data.message ) || config.failed, true );
				}
			} )
			.catch( function () {
				say( box, config.failed, true );
			} );
	}

	each( '[data-lafu-gc-redeem]', function ( box ) {
		var input = box.querySelector( '[data-lafu-gc-code]' );
		var button = box.querySelector( '[data-lafu-gc-apply]' );
		function submit() {
			if ( ! input.value.trim() ) {
				say( box, config.empty, true );
				return;
			}
			apply( box, { code: input.value } );
		}
		button.addEventListener( 'click', submit );
		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				submit();
			}
		} );
		each(
			'[data-lafu-gc-saved]',
			function ( saved ) {
				saved.addEventListener( 'click', function () {
					apply( box, { card: saved.getAttribute( 'data-lafu-gc-saved' ) } );
				} );
			},
			box
		);
	} );

	// Remove links live inside totals that WooCommerce redraws, so listen on the document.
	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( '[data-lafu-gc-remove]' ) : null;
		if ( ! link ) {
			return;
		}
		event.preventDefault();
		post( 'lafu_gc_remove', { card: link.getAttribute( 'data-lafu-gc-remove' ) } ).then( refresh, refresh );
	} );
} )();
