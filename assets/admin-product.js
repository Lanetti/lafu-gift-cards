/**
 * Product editor: show the Gift card tab while the Gift card box is ticked.
 *
 * @package Lafu_Gift_Cards
 */
/* global jQuery */
( function ( $ ) {
	'use strict';

	function sync() {
		var on = $( '#_lafu_gift_card' ).is( ':checked' ) && 'simple' === $( 'select#product-type' ).val();
		$( '.show_if_lafu_gift_card' ).toggle( on );
		if ( ! on && $( '.lafu_gift_card_options.active' ).length ) {
			$( '.general_options > a' ).trigger( 'click' );
		}
	}

	$( function () {
		$( '#_lafu_gift_card, select#product-type' ).on( 'change', sync );
		$( document.body ).on( 'woocommerce-product-type-change', sync );
		sync();
	} );
} )( jQuery );

/**
 * Product editor: the card preview on the Gift card tab follows the
 * design, colour and text fields.
 */
( function () {
	'use strict';

	var box = document.querySelector( '[data-lafu-gc-product-preview]' );
	if ( ! box ) {
		return;
	}
	var card = box.querySelector( '.lafu-gc-card' );
	var designs = JSON.parse( box.getAttribute( 'data-designs' ) || '{}' );
	var texts = JSON.parse( box.getAttribute( 'data-texts' ) || '{}' );
	var design = document.getElementById( '_lafu_gc_design' );
	var colour = document.getElementById( '_lafu_gc_colour' );

	// Same maths as Lafu_Gift_Cards_Designs::shade() and is_light().
	function shade( hex, amount ) {
		var out = '#';
		for ( var i = 1; i < 7; i += 2 ) {
			var value = parseInt( hex.substr( i, 2 ), 16 );
			value = amount < 0 ? value * ( 1 + amount ) : value + ( 255 - value ) * amount;
			out += ( '0' + Math.round( Math.max( 0, Math.min( 255, value ) ) ).toString( 16 ) ).slice( -2 );
		}
		return out;
	}

	function isLight( hex ) {
		return 0.299 * parseInt( hex.substr( 1, 2 ), 16 ) + 0.587 * parseInt( hex.substr( 3, 2 ), 16 ) + 0.114 * parseInt( hex.substr( 5, 2 ), 16 ) > 170;
	}

	var colourField = document.querySelector( '[data-lafu-gc-colour-field]' );

	function paint() {
		var picked = designs[ design ? design.value : '' ];
		if ( colourField ) {
			colourField.style.display = picked && picked.colour ? '' : 'none';
		}
		if ( picked ) {
			var background = picked.background;
			var text = picked.text;
			var accent = picked.accent;
			var light = picked.light;
			if ( picked.colour && colour && /^#[0-9a-f]{6}$/i.test( colour.value ) ) {
				light = isLight( colour.value );
				background = 'linear-gradient(135deg,' + colour.value + ',' + shade( colour.value, light ? -0.12 : 0.18 ) + ')';
				text = light ? '#18181b' : '#ffffff';
				accent = light ? shade( colour.value, -0.6 ) : shade( colour.value, 0.7 );
			}
			card.style.background = background;
			card.style.color = text;
			card.style.border = light ? '1px solid #e4e4e7' : '0';
			card.querySelector( '.lafu-gc-card__label' ).style.color = accent;
		}
		[ [ 'label', '.lafu-gc-card__label' ], [ 'shop', '.lafu-gc-card__shop' ], [ 'line', '[data-lafu-gc-preview-line]' ] ].forEach( function ( pair ) {
			var input = document.querySelector( '[data-lafu-gc-field="' + pair[ 0 ] + '"]' );
			var el = card.querySelector( pair[ 1 ] );
			if ( input && el ) {
				el.textContent = input.value.trim() || texts[ pair[ 0 ] ] || '';
			}
		} );
	}

	paint();
	[ design, colour ].concat( Array.prototype.slice.call( document.querySelectorAll( '[data-lafu-gc-field]' ) ) ).forEach( function ( el ) {
		if ( el ) {
			el.addEventListener( 'input', paint );
			el.addEventListener( 'change', paint );
		}
	} );
}() );

/**
 * Save the live card preview as the product's featured image.
 */
( function () {
	'use strict';

	var cfg = window.lafuGiftCardProduct;
	var button = document.querySelector( '[data-lafu-gc-product-image]' );
	if ( ! cfg || ! button ) {
		return;
	}
	var status = document.querySelector( '[data-lafu-gc-product-image-status]' );
	var busy = false;

	function text( selector ) {
		var el = document.querySelector( '[data-lafu-gc-product-preview] ' + selector );
		// innerText is the text as shown, without spaces from the markup.
		return el ? el.innerText.replace( /\s+/g, ' ' ).trim() : '';
	}

	function rounded( ctx, x, y, w, h, r ) {
		ctx.beginPath();
		ctx.moveTo( x + r, y );
		ctx.arcTo( x + w, y, x + w, y + h, r );
		ctx.arcTo( x + w, y + h, x, y + h, r );
		ctx.arcTo( x, y + h, x, y, r );
		ctx.arcTo( x, y, x + w, y, r );
		ctx.closePath();
	}

	// The server builds the card as one SVG (same colours, art and texts as
	// the preview); the browser draws it on a square, so shop grids that
	// crop product images to squares still show the whole card.
	function snap( done ) {
		var design = document.getElementById( '_lafu_gc_design' );
		var colour = document.getElementById( '_lafu_gc_colour' );
		var body = new window.FormData();
		body.append( 'action', 'lafu_gc_card_svg' );
		body.append( '_ajax_nonce', cfg.nonce );
		body.append( 'product_id', cfg.product );
		body.append( 'design', design ? design.value : '' );
		body.append( 'colour', colour ? colour.value : '' );
		body.append( 'label', text( '.lafu-gc-card__label' ) );
		body.append( 'shop', text( '.lafu-gc-card__shop' ) );
		body.append( 'line', text( '.lafu-gc-card__line' ) );
		body.append( 'amount', text( '.lafu-gc-card__amount' ) );
		window.fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					done( null );
					return;
				}
				var image = new Image();
				image.onload = function () {
					var size = 1200;
					var cw = 1080;
					var ch = Math.round( cw / 1.586 );
					var x = ( size - cw ) / 2;
					var y = ( size - ch ) / 2;
					var canvas = document.createElement( 'canvas' );
					canvas.width = size;
					canvas.height = size;
					var ctx = canvas.getContext( '2d' );
					ctx.fillStyle = '#f4f4f5';
					ctx.fillRect( 0, 0, size, size );
					ctx.save();
					ctx.shadowColor = 'rgba(24, 24, 27, .28)';
					ctx.shadowBlur = 60;
					ctx.shadowOffsetY = 28;
					rounded( ctx, x, y, cw, ch, 44 );
					ctx.fillStyle = '#ffffff';
					ctx.fill();
					ctx.restore();
					ctx.save();
					rounded( ctx, x, y, cw, ch, 44 );
					ctx.clip();
					ctx.drawImage( image, x, y, cw, ch );
					ctx.restore();
					try {
						canvas.toBlob( function ( blob ) {
							done( blob );
						}, 'image/jpeg', 0.9 );
					} catch ( error ) {
						done( null );
					}
				};
				image.onerror = function () {
					done( null );
				};
				image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent( json.data.svg );
			} )
			.catch( function () {
				done( null );
			} );
	}

	function save() {
		if ( busy || ! cfg.product ) {
			return;
		}
		busy = true;
		if ( status ) {
			status.textContent = '';
		}
		snap( function ( data ) {
			if ( ! data ) {
				busy = false;
				if ( status ) {
					status.textContent = cfg.failed;
				}
				return;
			}
			var body = new window.FormData();
			body.append( 'action', 'lafu_gc_product_image' );
			body.append( '_ajax_nonce', cfg.nonce );
			body.append( 'product_id', cfg.product );
			body.append( 'image', data, 'card.jpg' );
			window.fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					busy = false;
					var ok = json && json.success;
					if ( ok && json.data ) {
						var field = document.getElementById( '_thumbnail_id' );
						var box = document.querySelector( '#postimagediv .inside' );
						if ( field ) {
							field.value = String( json.data.id );
						}
						if ( box && json.data.html ) {
							box.innerHTML = json.data.html;
						}
					}
					if ( status ) {
						status.textContent = ok ? cfg.saved : cfg.failed;
					}
				} )
				.catch( function () {
					busy = false;
					if ( status ) {
						status.textContent = cfg.failed;
					}
				} );
		} );
	}

	button.addEventListener( 'click', save );
	if ( cfg.auto ) {
		window.setTimeout( save, 600 );
	}
}() );
