/**
 * LAFU Gift Cards designer: one colour picker for the design you see. The
 * card and its tile repaint as you pick; brand colours are one-click picks.
 */
( function () {
	'use strict';

	var form = document.querySelector( '[data-lafu-gc-designer]' );
	var field = form && form.querySelector( '[data-lafu-gc-colour-field]' );
	if ( ! field ) {
		return;
	}
	var picker = field.querySelector( '[data-lafu-gc-picker]' );
	var reset = field.querySelector( '[data-lafu-gc-picker-reset]' );
	var swatches = field.querySelector( '[data-lafu-gc-swatches]' );
	var add = swatches.querySelector( '[data-lafu-gc-swatch-add]' );
	var key = '';

	function hex( value ) {
		return /^#[0-9a-f]{6}$/i.test( value || '' ) ? value.toLowerCase() : '';
	}

	// Same maths as Lafu_Gift_Cards_Designs::shade() and colour_set().
	function shade( base, amount ) {
		var out = '#';
		for ( var i = 1; i < 7; i += 2 ) {
			var value = parseInt( base.substr( i, 2 ), 16 );
			value = amount < 0 ? value * ( 1 + amount ) : value + ( 255 - value ) * amount;
			out += ( '0' + Math.round( Math.max( 0, Math.min( 255, value ) ) ).toString( 16 ) ).slice( -2 );
		}
		return out;
	}

	function light( base ) {
		var r = parseInt( base.substr( 1, 2 ), 16 );
		var g = parseInt( base.substr( 3, 2 ), 16 );
		var b = parseInt( base.substr( 5, 2 ), 16 );
		return 0.299 * r + 0.587 * g + 0.114 * b > 170;
	}

	function set( base ) {
		var isLight = light( base );
		return [ base, shade( base, isLight ? -0.12 : 0.18 ), isLight ? '#18181b' : '#ffffff', isLight ? shade( base, -0.6 ) : shade( base, 0.7 ) ];
	}

	function big( name ) {
		return form.querySelector( '[data-lafu-gc-design="' + name + '"]' );
	}

	function tint( name ) {
		return field.querySelector( '[data-lafu-gc-tint="' + name + '"]' );
	}

	function paint( name ) {
		var box = big( name );
		var input = tint( name );
		if ( ! box || ! input ) {
			return;
		}
		var own = ( box.getAttribute( 'data-own' ) || '' ).split( ',' );
		var colours = hex( input.value ) ? set( hex( input.value ) ) : own;
		var art = box.getAttribute( 'data-art' ) || '';
		var tile = form.querySelector( '[data-lafu-gc-pick][value="' + name + '"]' );
		var cards = [ box.querySelector( '.lafu-gc-card' ), tile && tile.parentNode.querySelector( '.lafu-gc-card' ) ];
		cards.forEach( function ( card ) {
			if ( ! card ) {
				return;
			}
			card.style.setProperty( '--lafu-gc-card-bg', colours[ 0 ] );
			card.style.background = ( art ? art + ',' : '' ) + 'linear-gradient(135deg,' + colours[ 0 ] + ',' + colours[ 1 ] + ')';
			card.style.color = colours[ 2 ];
			card.style.border = light( colours[ 0 ] ) ? '1px solid #e4e4e7' : '';
			var label = card.querySelector( '.lafu-gc-card__label' );
			if ( label ) {
				label.style.color = colours[ 3 ];
			}
		} );
	}

	function show( name ) {
		key = name;
		var box = big( name );
		var input = tint( name );
		var photo = ! input || ( box && '1' === box.getAttribute( 'data-photo' ) );
		picker.parentNode.hidden = photo;
		if ( ! photo ) {
			picker.value = hex( input.value ) || ( box.getAttribute( 'data-own' ) || '#d46aab' ).split( ',' )[ 0 ];
		}
		reset.hidden = photo || ! hex( input && input.value );
	}

	function choose( value ) {
		var input = tint( key );
		if ( ! input || ! hex( value ) ) {
			return;
		}
		input.value = hex( value );
		picker.value = input.value;
		reset.hidden = false;
		paint( key );
	}

	function sync() {
		var items = swatches.querySelectorAll( '[data-lafu-gc-swatch-remove]' );
		items.forEach( function ( button ) {
			button.hidden = items.length < 2;
		} );
		add.hidden = items.length >= 12;
	}

	picker.addEventListener( 'input', function () {
		choose( picker.value );
	} );
	reset.addEventListener( 'click', function () {
		var input = tint( key );
		if ( input ) {
			input.value = '';
			paint( key );
			show( key );
		}
	} );
	swatches.addEventListener( 'click', function ( event ) {
		var remove = event.target.closest( '[data-lafu-gc-swatch-remove]' );
		if ( remove ) {
			remove.parentNode.remove();
			sync();
			return;
		}
		var dot = event.target.closest( '[data-lafu-gc-swatch]' );
		if ( dot ) {
			choose( dot.getAttribute( 'data-lafu-gc-swatch' ) );
			return;
		}
		if ( event.target === add && hex( picker.value ) ) {
			var value = hex( picker.value );
			var item = document.createElement( 'span' );
			item.className = 'lafu-gc-designer__swatch';
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'lafu-gc-designer__dot';
			button.style.background = value;
			button.setAttribute( 'data-lafu-gc-swatch', value );
			button.setAttribute( 'aria-label', ( add.getAttribute( 'data-label' ) || '%s' ).replace( '%s', value ) );
			var hidden = document.createElement( 'input' );
			hidden.type = 'hidden';
			hidden.name = 'colours[]';
			hidden.value = value;
			var cross = document.createElement( 'button' );
			cross.type = 'button';
			cross.className = 'lafu-gc-colour-remove';
			cross.setAttribute( 'data-lafu-gc-swatch-remove', '' );
			cross.setAttribute( 'aria-label', add.getAttribute( 'data-remove' ) || '' );
			cross.textContent = '×';
			item.appendChild( button );
			item.appendChild( hidden );
			item.appendChild( cross );
			swatches.insertBefore( item, add );
			sync();
		}
	} );
	form.addEventListener( 'change', function ( event ) {
		if ( event.target.matches( '[data-lafu-gc-pick]' ) ) {
			show( event.target.value );
		}
	} );
	var checked = form.querySelector( '[data-lafu-gc-pick]:checked' );
	show( checked ? checked.value : '' );
	sync();
}() );

/**
 * Designs page: every card shows the heading, name and text as you type.
 */
( function () {
	'use strict';

	var form = document.querySelector( '[data-lafu-gc-live-texts]' );
	if ( ! form ) {
		return;
	}
	var targets = {
		label: '.lafu-gc-card__label',
		shop: '.lafu-gc-card__shop',
		line: '[data-lafu-gc-preview-line]',
	};
	form.querySelectorAll( '[data-lafu-gc-live]' ).forEach( function ( input ) {
		var selector = targets[ input.getAttribute( 'data-lafu-gc-live' ) ];
		input.addEventListener( 'input', function () {
			form.querySelectorAll( '.lafu-gc-card ' + selector ).forEach( function ( el ) {
				el.textContent = input.value;
			} );
		} );
	} );
}() );

/**
 * Designer: the picked design shows large, the page previews use it, and
 * pointing at a page style shows a small live picture of that page.
 */
( function () {
	'use strict';

	var form = document.querySelector( '[data-lafu-gc-designer]' );
	if ( ! form ) {
		return;
	}
	var current = '';

	function withDesign( url ) {
		var link = new URL( url, window.location.href );
		if ( current ) {
			link.searchParams.set( 'design', current );
		}
		return link.toString();
	}

	function pick( key ) {
		current = key;
		form.querySelectorAll( '[data-lafu-gc-design]' ).forEach( function ( big ) {
			big.hidden = big.getAttribute( 'data-lafu-gc-design' ) !== key;
		} );
		form.querySelectorAll( '[data-lafu-gc-style-link]' ).forEach( function ( link ) {
			link.href = withDesign( link.closest( '[data-lafu-gc-style-preview]' ).getAttribute( 'data-lafu-gc-style-preview' ) );
		} );
		form.querySelectorAll( '.lafu-gc-page-style__peek' ).forEach( function ( peek ) {
			peek.remove();
		} );
	}

	form.addEventListener( 'change', function ( event ) {
		if ( event.target.matches( '[data-lafu-gc-pick]' ) ) {
			pick( event.target.value );
		}
	} );
	var checked = form.querySelector( '[data-lafu-gc-pick]:checked' );
	if ( checked ) {
		pick( checked.value );
	}

	form.querySelectorAll( '[data-lafu-gc-style-preview]' ).forEach( function ( box ) {
		var timer = 0;
		function show() {
			timer = window.setTimeout( function () {
				if ( box.querySelector( '.lafu-gc-page-style__peek' ) ) {
					return;
				}
				var peek = document.createElement( 'span' );
				peek.className = 'lafu-gc-page-style__peek';
				peek.setAttribute( 'aria-hidden', 'true' );
				var frame = document.createElement( 'iframe' );
				frame.src = withDesign( box.getAttribute( 'data-lafu-gc-style-preview' ) );
				frame.tabIndex = -1;
				frame.setAttribute( 'loading', 'lazy' );
				frame.title = '';
				peek.appendChild( frame );
				box.appendChild( peek );
			}, 180 );
		}
		function hide() {
			window.clearTimeout( timer );
			var peek = box.querySelector( '.lafu-gc-page-style__peek' );
			if ( peek ) {
				peek.remove();
			}
		}
		box.addEventListener( 'mouseenter', show );
		box.addEventListener( 'mouseleave', hide );
		box.addEventListener( 'focusin', show );
		box.addEventListener( 'focusout', hide );
	} );
}() );
