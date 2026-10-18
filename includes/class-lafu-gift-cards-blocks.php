<?php
/**
 * Gift cards in the cart and checkout blocks.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes applied and saved cards on the Store API cart and takes apply
 * and remove requests from the block script, using the same code paths as
 * the classic pages.
 */
class Lafu_Gift_Cards_Blocks {

	const NAMESPACE_ID = 'lafu-gift-cards';

	/**
	 * Hook the Store API and block registration.
	 */
	public static function register() {
		// WooCommerce loads its blocks while plugins load, before this plugin boots.
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::store_api();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'store_api' ) );
		}
		add_action( 'woocommerce_blocks_cart_block_registration', array( __CLASS__, 'integration' ) );
		add_action( 'woocommerce_blocks_checkout_block_registration', array( __CLASS__, 'integration' ) );
	}

	/**
	 * Cart data and the update callback.
	 */
	public static function store_api() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
				'namespace'       => self::NAMESPACE_ID,
				'data_callback'   => array( __CLASS__, 'cart_data' ),
				'schema_callback' => array( __CLASS__, 'cart_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => self::NAMESPACE_ID,
				'callback'  => array( __CLASS__, 'update' ),
			)
		);
	}

	/**
	 * Applied cards with the amount each pays, and the customer's saved
	 * cards. Codes are masked.
	 *
	 * @return array
	 */
	public static function cart_data() {
		$applied = array();
		foreach ( Lafu_Gift_Cards_Redeem::plan() as $card_id => $amount ) {
			$card = Lafu_Gift_Cards_Store::get( (int) $card_id );
			if ( $card ) {
				$applied[] = array(
					'id'     => (int) $card_id,
					'label'  => lafu_gift_cards_card_title( $card ),
					'amount' => lafu_gift_cards_plain_price( -1 * (float) $amount ),
				);
			}
		}
		return array(
			'applied' => $applied,
			'saved'   => Lafu_Gift_Cards_Redeem::saved_cards(),
		);
	}

	/**
	 * Schema of cart_data().
	 *
	 * @return array
	 */
	public static function cart_schema() {
		return array(
			'applied' => array(
				'description' => __( 'Gift cards paying for this cart.', 'lafu-gift-cards' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'saved'   => array(
				'description' => __( 'Gift cards saved to the customer account.', 'lafu-gift-cards' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);
	}

	/**
	 * Apply or remove a card from the block script.
	 *
	 * @param array $data action (apply, saved, remove), code, card.
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When the card cannot be used.
	 */
	public static function update( $data ) {
		$action = isset( $data['action'] ) ? sanitize_key( $data['action'] ) : '';
		if ( 'remove' === $action ) {
			Lafu_Gift_Cards_Redeem::remove( isset( $data['card'] ) ? absint( $data['card'] ) : 0 );
			return;
		}
		if ( 'saved' === $action ) {
			$result = Lafu_Gift_Cards_Redeem::apply_saved( isset( $data['card'] ) ? absint( $data['card'] ) : 0 );
		} else {
			$result = Lafu_Gift_Cards_Redeem::apply_code( isset( $data['code'] ) ? sanitize_text_field( (string) $data['code'] ) : '' );
		}
		if ( is_wp_error( $result ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'lafu_gift_cards_' . esc_html( $result->get_error_code() ), esc_html( $result->get_error_message() ), 400 );
		}
		Lafu_Gift_Cards_Redeem::recalculate();
	}

	/**
	 * Register the block script with the cart or checkout block.
	 *
	 * @param object $registry Integration registry.
	 */
	public static function integration( $registry ) {
		if ( ! interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
			return;
		}
		require_once LAFU_GIFT_CARDS_DIR . '/includes/class-lafu-gift-cards-blocks-integration.php';
		if ( ! $registry->is_registered( self::NAMESPACE_ID ) ) {
			$registry->register( new Lafu_Gift_Cards_Blocks_Integration() );
		}
	}
}
