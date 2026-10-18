<?php
/**
 * Block integration. Loaded only when WooCommerce Blocks is present.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the gift card panel script to the cart and checkout blocks.
 */
class Lafu_Gift_Cards_Blocks_Integration implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {

	/**
	 * Integration name.
	 *
	 * @return string
	 */
	public function get_name() {
		return Lafu_Gift_Cards_Blocks::NAMESPACE_ID;
	}

	/**
	 * Register the script and styles.
	 */
	public function initialize() {
		wp_register_script(
			'lafu-gift-cards-blocks',
			plugins_url( 'assets/blocks.js', LAFU_GIFT_CARDS_FILE ),
			array( 'wc-blocks-checkout', 'wc-settings', 'wp-plugins', 'wp-element' ),
			LAFU_GIFT_CARDS_VERSION,
			true
		);
		lafu_gift_cards_register_assets();
		wp_enqueue_style( 'lafu-gift-cards' );
	}

	/**
	 * Front end scripts.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'lafu-gift-cards-blocks' );
	}

	/**
	 * Editor scripts: none, the panel only shows on the live page.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * Translated strings for the script.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array(
			'title'  => __( 'Have a gift card?', 'lafu-gift-cards' ),
			'label'  => __( 'Gift card code', 'lafu-gift-cards' ),
			'apply'  => __( 'Use card', 'lafu-gift-cards' ),
			'remove' => __( 'Remove', 'lafu-gift-cards' ),
			'saved'  => __( 'Your gift cards:', 'lafu-gift-cards' ),
			'empty'  => __( 'Enter a gift card code.', 'lafu-gift-cards' ),
			'added'  => __( 'Gift card added.', 'lafu-gift-cards' ),
			'failed' => __( 'Something went wrong. Please try again.', 'lafu-gift-cards' ),
		);
	}
}
