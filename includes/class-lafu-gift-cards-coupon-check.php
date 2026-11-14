<?php
/**
 * WooCommerce's own coupon check on the full cart, for old WooCommerce.
 *
 * Loaded only from Lafu_Gift_Cards_Product::product_coupon_on_cards(),
 * when WooCommerce (and so WC_Discounts) is running.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs WC_Discounts::validate_coupon_excluded_items() on every cart line,
 * gift cards included, so a refusal has WooCommerce's own error text.
 */
class Lafu_Gift_Cards_Coupon_Check extends WC_Discounts {

	/**
	 * Every line, without the woocommerce_coupon_get_items_to_validate
	 * filter (which leaves gift card lines out on WooCommerce before 8.9).
	 *
	 * @return object[]
	 */
	public function get_items_to_validate() {
		return $this->get_items();
	}

	/**
	 * Throw WooCommerce's "not applicable to selected products" error when
	 * the coupon fits none of the lines.
	 *
	 * @param WC_Coupon $coupon Coupon.
	 * @param object[]  $items  Lines from the WC_Discounts being validated.
	 * @return bool
	 * @throws Exception When the coupon fits none of the lines.
	 */
	public static function excluded_items( $coupon, $items ) {
		$check = new self();
		$check->set_items( $items );
		return $check->validate_coupon_excluded_items( $coupon );
	}
}
