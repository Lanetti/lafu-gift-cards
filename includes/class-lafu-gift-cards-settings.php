<?php
/**
 * Plugin settings under WooCommerce > Settings > Products > Gift cards.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * A short list of shop-wide settings. Everything per card type lives on
 * the gift card product.
 */
class Lafu_Gift_Cards_Settings {

	const SECTION = 'lafu_gift_cards';

	/**
	 * Hook the settings section.
	 */
	public static function register() {
		add_filter( 'woocommerce_get_sections_products', array( __CLASS__, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( __CLASS__, 'settings' ), 10, 2 );
	}

	/**
	 * Keep valid, unique colours, at most twelve.
	 *
	 * @param mixed $value     Posted value.
	 * @param array $option    Field.
	 * @param mixed $raw_value Raw value.
	 * @return array
	 */
	public static function sanitize_colours( $value, $option, $raw_value ) {
		unset( $option );
		$list = array_values( array_unique( array_filter( array_map( 'sanitize_hex_color', array_map( 'strtolower', (array) $raw_value ) ) ) ) );
		return array_slice( $list, 0, 12 );
	}

	/**
	 * Add the Gift cards section tab.
	 *
	 * @param array $sections Sections.
	 * @return array
	 */
	public static function add_section( $sections ) {
		$sections[ self::SECTION ] = __( 'Gift cards', 'lafu-gift-cards' );
		return $sections;
	}

	/**
	 * Fields of the section.
	 *
	 * @param array  $settings Settings of the current section.
	 * @param string $section  Current section.
	 * @return array
	 */
	public static function settings( $settings, $section ) {
		if ( self::SECTION !== $section ) {
			return $settings;
		}
		return array(
			array(
				'title' => __( 'Gift cards', 'lafu-gift-cards' ),
				'type'  => 'title',
				'desc'  => __( 'Card designs, colours, texts and the gift card page are under WooCommerce > Gift cards > Designer. Amounts and delivery are set on each gift card product.', 'lafu-gift-cards' ),
				'id'    => 'lafu_gift_cards_options',
			),
			array(
				'title'             => __( 'Code prefix', 'lafu-gift-cards' ),
				'desc'              => __( 'Optional letters at the start of new codes, for example the shop initials.', 'lafu-gift-cards' ),
				'id'                => 'lafu_gift_cards_code_prefix',
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'maxlength' => 6,
					'pattern'   => '[A-Za-z0-9]*',
				),
			),
			array(
				'title'             => __( 'Valid for (months)', 'lafu-gift-cards' ),
				'desc'              => __( 'How long a new card is valid. 0 means no expiry date.', 'lafu-gift-cards' ),
				'id'                => 'lafu_gift_cards_validity_months',
				'type'              => 'number',
				'default'           => '0',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min'  => 0,
					'max'  => 120,
					'step' => 1,
				),
			),
			array(
				'title'   => __( 'Code in the email', 'lafu-gift-cards' ),
				'desc'    => __( 'Show the gift card code and QR code in the gift card email. When off, the email shows the card with a button, and the code is on the private gift card page.', 'lafu-gift-cards' ),
				'id'      => 'lafu_gift_cards_email_code',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Remove data on uninstall', 'lafu-gift-cards' ),
				'desc'    => __( 'Delete all gift cards and their history when the plugin is deleted. Leave off unless you are sure: open cards are money your customers own.', 'lafu-gift-cards' ),
				'id'      => 'lafu_gift_cards_delete_data',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'lafu_gift_cards_options',
			),
		);
	}

	/**
	 * Code prefix, upper case letters and digits only.
	 *
	 * @return string
	 */
	public static function code_prefix() {
		return substr( Lafu_Gift_Cards_Codes::normalize( get_option( 'lafu_gift_cards_code_prefix', '' ) ), 0, 6 );
	}

	/**
	 * Expiry for a card issued now, as a UTC datetime, or null.
	 *
	 * @return string|null
	 */
	public static function default_expiry() {
		$months = min( 120, absint( get_option( 'lafu_gift_cards_validity_months', 0 ) ) );
		$expiry = $months ? gmdate( 'Y-m-d 23:59:59', strtotime( '+' . $months . ' months' ) ) : null;

		/**
		 * Expiry of a card being issued now.
		 *
		 * @param string|null $expiry UTC datetime or null for no expiry.
		 */
		return apply_filters( 'lafu_gift_cards_default_expiry', $expiry );
	}
}
