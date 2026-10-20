<?php
/**
 * A short first-run tour of the Gift cards pages: a few steps that point at
 * the tabs and say what each one is for. Shown once per user, can be skipped
 * and played again from the Take the tour link in the header.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tour steps, assets and the "seen" flag.
 */
class Lafu_Gift_Cards_Tour {

	const META   = 'lafu_gift_cards_tour_seen';
	const ACTION = 'lafu_gc_tour_seen';

	/**
	 * Hook in.
	 */
	public static function register() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'seen' ) );
	}

	/**
	 * Steps: target (CSS selector in the plugin header), title and text.
	 *
	 * @return array
	 */
	public static function steps() {
		$steps = array(
			array(
				'target' => '.lafu-gc-nav a:first-child',
				'title'  => __( 'Sell your first gift card', 'lafu-gift-cards' ),
				'text'   => __( 'Add a gift card product and pick one of the ready designs and your colour on its Gift card tab. Every card you sell shows up here: search by code, name or email.', 'lafu-gift-cards' ),
			),
			array(
				'target' => '.lafu-gc-nav a[href*="view=issue"]',
				'title'  => __( 'Sell at the counter', 'lafu-gift-cards' ),
				'text'   => __( 'Sell a card in your shop in a few seconds. It is saved as a normal order, so your books stay right.', 'lafu-gift-cards' ),
			),
			array(
				'target' => '.lafu-gc-nav a:last-child',
				'title'  => __( 'Emails and settings', 'lafu-gift-cards' ),
				'text'   => __( 'Choose what the gift card email shows and how long cards are valid. The picture guide has every step.', 'lafu-gift-cards' ),
			),
		);
		/**
		 * Tour steps. Each step: target (CSS selector), title, text.
		 *
		 * @param array $steps Steps.
		 */
		return (array) apply_filters( 'lafu_gift_cards_tour_steps', $steps );
	}

	/**
	 * Script and texts on the plugin pages.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function assets( $hook ) {
		if ( 'woocommerce_page_' . Lafu_Gift_Cards_Admin::PAGE !== $hook || ! current_user_can( Lafu_Gift_Cards_Admin::CAPABILITY ) ) {
			return;
		}
		$steps = array_values(
			array_filter(
				self::steps(),
				static function ( $step ) {
					return is_array( $step ) && ! empty( $step['target'] ) && ! empty( $step['title'] );
				}
			)
		);
		wp_enqueue_script( 'lafu-gift-cards-tour', plugins_url( 'assets/tour.js', LAFU_GIFT_CARDS_FILE ), array(), LAFU_GIFT_CARDS_VERSION, true );
		wp_localize_script(
			'lafu-gift-cards-tour',
			'lafuGcTour',
			array(
				'steps'    => $steps,
				'autoplay' => ! get_user_meta( get_current_user_id(), self::META, true ),
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'action'   => self::ACTION,
				'nonce'    => wp_create_nonce( self::ACTION ),
				'guide'    => 'https://www.lanetti.fi/tutorials/gift-cards-getting-started',
				'words'    => array(
					'next'  => __( 'Next', 'lafu-gift-cards' ),
					'back'  => __( 'Back', 'lafu-gift-cards' ),
					'done'  => __( 'Done', 'lafu-gift-cards' ),
					'skip'  => __( 'Skip the tour', 'lafu-gift-cards' ),
					/* translators: 1: step number, 2: number of steps */
					'step'  => __( 'Step %1$d of %2$d', 'lafu-gift-cards' ),
					'guide' => __( 'Open the picture guide', 'lafu-gift-cards' ),
					'start' => __( 'Take the tour', 'lafu-gift-cards' ),
				),
			)
		);
	}

	/**
	 * The user finished or skipped the tour: do not start it again by itself.
	 */
	public static function seen() {
		check_ajax_referer( self::ACTION, 'nonce' );
		if ( ! current_user_can( Lafu_Gift_Cards_Admin::CAPABILITY ) ) {
			wp_send_json_error( null, 403 );
		}
		update_user_meta( get_current_user_id(), self::META, '1' );
		wp_send_json_success();
	}
}
