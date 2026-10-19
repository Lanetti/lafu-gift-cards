/**
 * LAFU Gift Cards: a short first-run tour. Each step rings one tab in the
 * plugin header and explains it in a small card. Plays once per user (the
 * server remembers it), and again from the Take the tour link.
 */
( function () {
	'use strict';

	var config = window.lafuGcTour;
	var head = document.querySelector( '.lafu-gc-head' );
	if ( ! config || ! head || ! config.steps || ! config.steps.length ) {
		return;
	}
	var words = config.words || {};
	var steps = config.steps.filter( function ( step ) {
		return document.querySelector( step.target );
	} );
	if ( ! steps.length ) {
		return;
	}
	var index = 0;
	var card = null;
	var ringed = null;

	function format( text, a, b ) {
		return String( text || '' ).replace( '%1$d', a ).replace( '%2$d', b );
	}

	function remember() {
		var body = new window.FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		window.fetch( config.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).catch( function () {} );
	}

	function close( done ) {
		if ( card ) {
			card.remove();
			card = null;
		}
		if ( ringed ) {
			ringed.classList.remove( 'lafu-gc-tour-ring' );
			ringed = null;
		}
		document.removeEventListener( 'keydown', onKey );
		window.removeEventListener( 'resize', place );
		if ( done ) {
			remember();
		}
	}

	function onKey( event ) {
		if ( 'Escape' === event.key ) {
			close( true );
		} else if ( 'ArrowRight' === event.key ) {
			go( index + 1 );
		} else if ( 'ArrowLeft' === event.key ) {
			go( index - 1 );
		}
	}

	function place() {
		if ( ! card || ! ringed ) {
			return;
		}
		var box = ringed.getBoundingClientRect();
		var width = card.offsetWidth;
		var left = Math.max( 12, Math.min( window.innerWidth - width - 12, box.left + box.width / 2 - width / 2 ) );
		card.style.left = left + window.scrollX + 'px';
		card.style.top = box.bottom + window.scrollY + 14 + 'px';
		var arrow = card.querySelector( '.lafu-gc-tour__arrow' );
		if ( arrow ) {
			arrow.style.left = Math.max( 16, Math.min( width - 32, box.left + box.width / 2 - left - 8 ) ) + 'px';
		}
	}

	function button( label, className, onClick ) {
		var el = document.createElement( 'button' );
		el.type = 'button';
		el.className = className;
		el.textContent = label;
		el.addEventListener( 'click', onClick );
		return el;
	}

	function go( next ) {
		if ( next < 0 ) {
			return;
		}
		if ( next >= steps.length ) {
			close( true );
			return;
		}
		index = next;
		var step = steps[ index ];
		if ( ringed ) {
			ringed.classList.remove( 'lafu-gc-tour-ring' );
		}
		ringed = document.querySelector( step.target );
		ringed.classList.add( 'lafu-gc-tour-ring' );
		ringed.scrollIntoView( { block: 'nearest' } );

		if ( ! card ) {
			card = document.createElement( 'div' );
			card.className = 'lafu-gc-tour';
			card.setAttribute( 'role', 'dialog' );
			card.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( card );
			document.addEventListener( 'keydown', onKey );
			window.addEventListener( 'resize', place );
		}
		card.textContent = '';
		var arrow = document.createElement( 'span' );
		arrow.className = 'lafu-gc-tour__arrow';
		card.appendChild( arrow );
		var count = document.createElement( 'p' );
		count.className = 'lafu-gc-tour__count';
		count.textContent = format( words.step, index + 1, steps.length );
		card.appendChild( count );
		var title = document.createElement( 'h2' );
		title.className = 'lafu-gc-tour__title';
		title.id = 'lafu-gc-tour-title';
		title.textContent = step.title;
		card.appendChild( title );
		card.setAttribute( 'aria-labelledby', title.id );
		var text = document.createElement( 'p' );
		text.className = 'lafu-gc-tour__text';
		text.textContent = step.text || '';
		card.appendChild( text );
		if ( index === steps.length - 1 && config.guide ) {
			var guide = document.createElement( 'a' );
			guide.className = 'lafu-gc-tour__guide';
			guide.href = config.guide;
			guide.target = '_blank';
			guide.rel = 'noopener';
			guide.textContent = words.guide;
			card.appendChild( guide );
		}
		var bar = document.createElement( 'div' );
		bar.className = 'lafu-gc-tour__bar';
		bar.appendChild( button( words.skip, 'lafu-gc-tour__skip', function () {
			close( true );
		} ) );
		if ( index > 0 ) {
			bar.appendChild( button( words.back, 'lafu-gc-btn lafu-gc-btn--ghost lafu-gc-btn--small', function () {
				go( index - 1 );
			} ) );
		}
		var last = index === steps.length - 1;
		var primary = button( last ? words.done : words.next, 'lafu-gc-btn lafu-gc-btn--small', function () {
			go( index + 1 );
		} );
		bar.appendChild( primary );
		card.appendChild( bar );
		place();
		primary.focus( { preventScroll: true } );
	}

	// The Take the tour link in the header.
	var start = document.createElement( 'button' );
	start.type = 'button';
	start.className = 'lafu-gc-tour-start';
	start.textContent = words.start;
	start.addEventListener( 'click', function () {
		close( false );
		go( 0 );
	} );
	head.appendChild( start );

	if ( config.autoplay ) {
		window.setTimeout( function () {
			go( 0 );
		}, 400 );
	}
}() );
