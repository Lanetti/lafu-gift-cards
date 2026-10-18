/**
 * Admin: confirm dialogs for risky actions, and the Sell a card form
 * (amount chips, email toggle and the live card preview).
 *
 * @package Lafu_Gift_Cards
 */
( function () {
	'use strict';

	// Forms and links that cancel a card or move money ask first.
	document.addEventListener( 'submit', function ( event ) {
		var target = event.target;
		if ( target.matches && target.matches( 'form[data-lafu-gc-confirm]' ) && ! window.confirm( target.getAttribute( 'data-lafu-gc-confirm' ) ) ) {
			event.preventDefault();
		}
	} );
	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( 'a[data-lafu-gc-confirm]' ) : null;
		if ( link && ! window.confirm( link.getAttribute( 'data-lafu-gc-confirm' ) ) ) {
			event.preventDefault();
		}
	} );

	var form = document.querySelector( '[data-lafu-gc-sell]' );
	if ( ! form ) {
		return;
	}

	var amount = form.querySelector( '[data-lafu-gc-amount]' );
	var chips = form.querySelectorAll( '[data-lafu-gc-chip]' );
	var preview = form.querySelector( '[data-lafu-gc-preview] .lafu-gc-card' );
	var designs;
	try {
		designs = JSON.parse( form.getAttribute( 'data-designs' ) || '{}' );
	} catch ( e ) {
		designs = {};
	}

	function money( value ) {
		var number = parseFloat( String( value ).replace( ',', '.' ) );
		if ( isNaN( number ) ) {
			number = 0;
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

	function people() {
		var to = form.querySelector( '[data-lafu-gc-to]' ).value.trim();
		var from = form.querySelector( '[data-lafu-gc-from]' ).value.trim();
		var parts = [];
		if ( to ) {
			parts.push( ( form.getAttribute( 'data-for' ) || '%s' ).replace( '%s', to ) );
		}
		if ( from ) {
			parts.push( ( form.getAttribute( 'data-from' ) || '%s' ).replace( '%s', from ) );
		}
		return parts.join( ' · ' );
	}

	// Same maths as Lafu_Gift_Cards_Designs::shade() and is_light().
	function shade( hex, value ) {
		var out = '#';
		for ( var i = 1; i < 7; i += 2 ) {
			var part = parseInt( hex.substr( i, 2 ), 16 );
			part = value < 0 ? part * ( 1 + value ) : part + ( 255 - part ) * value;
			out += ( '0' + Math.round( Math.max( 0, Math.min( 255, part ) ) ).toString( 16 ) ).slice( -2 );
		}
		return out;
	}

	function isLight( hex ) {
		return 0.299 * parseInt( hex.substr( 1, 2 ), 16 ) + 0.587 * parseInt( hex.substr( 3, 2 ), 16 ) + 0.114 * parseInt( hex.substr( 5, 2 ), 16 ) > 170;
	}

	function paint() {
		if ( ! preview ) {
			return;
		}
		preview.querySelector( '[data-lafu-gc-preview-amount]' ).textContent = money( amount.value );
		preview.querySelector( '[data-lafu-gc-preview-people]' ).textContent = people();
		var picked = form.querySelector( '[data-lafu-gc-design]:checked' );
		var design = picked && designs[ picked.value ];
		var colours = form.querySelector( '[data-lafu-gc-colours]' );
		if ( colours ) {
			colours.hidden = ! ( design && design.colour );
		}
		if ( design ) {
			var background = design.background;
			var text = design.text;
			var accent = design.accent;
			var light = design.light;
			var base = form.querySelector( 'input[name="colour"]:checked' );
			if ( design.colour && base ) {
				light = isLight( base.value );
				background = 'linear-gradient(135deg,' + base.value + ',' + shade( base.value, light ? -0.12 : 0.18 ) + ')';
				text = light ? '#18181b' : '#ffffff';
				accent = light ? shade( base.value, -0.6 ) : shade( base.value, 0.7 );
			}
			preview.style.background = background;
			preview.style.color = text;
			preview.style.border = light ? '1px solid #e4e4e7' : '0';
			preview.querySelector( '.lafu-gc-card__label' ).style.color = accent;
		var line = preview.querySelector( '[data-lafu-gc-preview-line]' );
		var typed = form.querySelector( '[data-lafu-gc-card-line]' );
		if ( line && typed ) {
			line.textContent = typed.value.trim() || typed.getAttribute( 'placeholder' ) || '';
		}
		}
		chips.forEach( function ( chip ) {
			chip.classList.toggle( 'is-active', parseFloat( chip.getAttribute( 'data-lafu-gc-chip' ) ) === parseFloat( String( amount.value ).replace( ',', '.' ) ) );
		} );
	}

	chips.forEach( function ( chip ) {
		chip.addEventListener( 'click', function () {
			amount.value = chip.getAttribute( 'data-lafu-gc-chip' );
			paint();
		} );
	} );

	var send = form.querySelector( '[data-lafu-gc-send]' );
	var email = form.querySelector( '[data-lafu-gc-email]' );
	function toggleEmail() {
		email.hidden = ! send.checked;
		email.querySelector( 'input' ).required = send.checked;
	}
	send.addEventListener( 'change', toggleEmail );

	form.addEventListener( 'input', paint );
	form.addEventListener( 'change', paint );
	toggleEmail();
	paint();
} )();

/**
 * Counter: the Sell a card window.
 */
( function () {
	'use strict';

	var dialog = document.querySelector( '[data-lafu-gc-sell-dialog]' );
	if ( ! dialog || 'function' !== typeof dialog.showModal ) {
		return;
	}
	function open() {
		dialog.showModal();
		var first = dialog.querySelector( '[data-lafu-gc-chip], input[name="amount"]' );
		if ( first ) {
			first.focus();
		}
	}
	document.querySelectorAll( '[data-lafu-gc-open-sell]' ).forEach( function ( button ) {
		button.addEventListener( 'click', open );
	} );
	dialog.querySelectorAll( '[data-lafu-gc-close-sell]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			dialog.close();
		} );
	} );
	// A click on the backdrop closes the window.
	dialog.addEventListener( 'click', function ( event ) {
		if ( event.target === dialog ) {
			dialog.close();
		}
	} );
	if ( dialog.hasAttribute( 'data-open' ) ) {
		var error = document.querySelector( '.notice-error' );
		if ( error ) {
			var copy = error.cloneNode( true );
			copy.classList.remove( 'is-dismissible' );
			copy.classList.add( 'lafu-gc-modal__error' );
			copy.setAttribute( 'role', 'alert' );
			dialog.insertBefore( copy, dialog.querySelector( 'form' ) );
		}
		open();
	}
}() );
