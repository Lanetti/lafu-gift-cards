/**
 * Gift card panel for the WooCommerce cart and checkout blocks.
 *
 * @package Lafu_Gift_Cards
 */
( function ( wp, wc ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element || ! wc || ! wc.blocksCheckout ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var checkout = wc.blocksCheckout;
	var NS = 'lafu-gift-cards';
	var settings = wc.wcSettings && wc.wcSettings.getSetting ? wc.wcSettings.getSetting( NS + '_data', {} ) : {};

	function t( key ) {
		return settings[ key ] || '';
	}

	function Panel( props ) {
		var data = ( props.extensions && props.extensions[ NS ] ) || {};
		var applied = data.applied || [];
		var saved = data.saved || [];
		var code = useState( '' );
		var busy = useState( false );
		var note = useState( null );
		var open = useState( false );
		var expanded = open[ 0 ] || applied.length > 0;

		function send( payload ) {
			busy[ 1 ]( true );
			note[ 1 ]( null );
			return checkout
				.extensionCartUpdate( { namespace: NS, data: payload } )
				.then( function () {
					if ( 'remove' !== payload.action ) {
						code[ 1 ]( '' );
						note[ 1 ]( { text: t( 'added' ), error: false } );
					}
				} )
				.catch( function ( error ) {
					note[ 1 ]( { text: ( error && error.message ) || t( 'failed' ), error: true } );
				} )
				.then( function () {
					busy[ 1 ]( false );
				} );
		}

		function apply( event ) {
			if ( event ) {
				event.preventDefault();
			}
			if ( ! code[ 0 ].trim() ) {
				note[ 1 ]( { text: t( 'empty' ), error: true } );
				return;
			}
			send( { action: 'apply', code: code[ 0 ] } );
		}

		var rows = applied.map( function ( card ) {
			return el(
				'li',
				{ key: card.id, className: 'lafu-gc-block-applied' },
				el( 'span', null, card.label ),
				el( 'strong', null, card.amount ),
				el(
					'button',
					{
						type: 'button',
						className: 'lafu-gc-link',
						disabled: busy[ 0 ],
						onClick: function () {
							send( { action: 'remove', card: card.id } );
						},
					},
					t( 'remove' )
				)
			);
		} );

		var savedButtons = saved.map( function ( card ) {
			return el(
				'button',
				{
					key: card.id,
					type: 'button',
					className: 'lafu-gc-saved-card',
					disabled: busy[ 0 ],
					onClick: function () {
						send( { action: 'saved', card: card.id } );
					},
				},
				card.label + ' · ' + card.balance
			);
		} );

		return el(
			'div',
			{ className: 'wc-block-components-totals-wrapper lafu-gc-block' + ( expanded ? ' is-open' : '' ) },
			el(
				'button',
				{
					type: 'button',
					className: 'lafu-gc-block-toggle',
					'aria-expanded': expanded,
					onClick: function () {
						open[ 1 ]( ! expanded );
					},
				},
				el( 'span', null, t( 'title' ) ),
				el( 'span', { className: 'lafu-gc-chevron', 'aria-hidden': true } )
			),
			expanded
				? el(
						'div',
						{ className: 'lafu-gc-block-body' },
						el(
							'div',
							{ className: 'lafu-gc-redeem-row', role: 'group', 'aria-label': t( 'label' ) },
							el( 'input', {
								type: 'text',
								'aria-label': t( 'label' ),
								placeholder: t( 'label' ),
								autoComplete: 'off',
								spellCheck: false,
								value: code[ 0 ],
								onChange: function ( event ) {
									code[ 1 ]( event.target.value );
								},
								onKeyDown: function ( event ) {
									if ( 'Enter' === event.key ) {
										apply( event );
									}
								},
							} ),
							el( 'button', { type: 'button', className: 'lafu-gc-apply', disabled: busy[ 0 ], onClick: apply }, t( 'apply' ) )
						),
						savedButtons.length ? el( 'p', { className: 'lafu-gc-saved' }, t( 'saved' ), ' ', savedButtons ) : null,
						rows.length ? el( 'ul', { className: 'lafu-gc-block-list' }, rows ) : null,
						note[ 0 ]
							? el(
									'p',
									{ className: 'lafu-gc-redeem-message' + ( note[ 0 ].error ? ' is-error' : '' ), role: 'status' },
									note[ 0 ].text
							  )
							: null
				  )
				: null
		);
	}

	// The WooCommerce slot clones its child element with the cart and
	// extension data as props, so the panel goes in as an element.
	wp.plugins.registerPlugin( NS, {
		scope: 'woocommerce-checkout',
		render: function () {
			return el( checkout.ExperimentalOrderMeta, null, el( Panel ) );
		},
	} );
} )( window.wp, window.wc );
