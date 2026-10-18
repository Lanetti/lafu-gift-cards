<?php
/**
 * Plugin Name:       LAFU Gift Cards
 * Description:       Gift cards for WooCommerce. Sell them online and in store, redeem them at checkout and see every euro in a full history.
 * Version:           1.8.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Elsama
 * Author URI:        https://lafu.fi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lafu-gift-cards
 * Domain Path:       /languages
 * WC requires at least: 8.5
 * WC tested up to:   11.2
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

define( 'LAFU_GIFT_CARDS_VERSION', '1.8.1' );
define( 'LAFU_GIFT_CARDS_FILE', __FILE__ );
define( 'LAFU_GIFT_CARDS_DIR', __DIR__ );

require_once __DIR__ . '/includes/class-lafu-gift-cards-i18n.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-install.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-codes.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-store.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-qr.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-art.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-designs.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-product.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-issuer.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-redeem.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-blocks.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-account.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-gift-page.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-settings.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-privacy.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-admin.php';
require_once __DIR__ . '/includes/class-lafu-gift-cards-design-page.php';

register_activation_hook( __FILE__, array( 'Lafu_Gift_Cards_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Lafu_Gift_Cards_Install', 'deactivate' ) );

add_action( 'before_woocommerce_init', 'lafu_gift_cards_declare_compatibility' );
add_action( 'plugins_loaded', 'lafu_gift_cards_boot', 20 );

/**
 * Tell WooCommerce the plugin works with order tables (HPOS) and the
 * cart and checkout blocks.
 */
function lafu_gift_cards_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}

/**
 * Start the plugin once WooCommerce is loaded.
 */
function lafu_gift_cards_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	Lafu_Gift_Cards_Install::maybe_upgrade();
	Lafu_Gift_Cards_Product::register();
	Lafu_Gift_Cards_Issuer::register();
	Lafu_Gift_Cards_Redeem::register();
	Lafu_Gift_Cards_Blocks::register();
	Lafu_Gift_Cards_Account::register();
	Lafu_Gift_Cards_Gift_Page::register();
	Lafu_Gift_Cards_Settings::register();
	Lafu_Gift_Cards_Privacy::register();
	if ( is_admin() ) {
		Lafu_Gift_Cards_Admin::register();
		Lafu_Gift_Cards_Design_Page::register();
	}
	add_action( 'init', 'lafu_gift_cards_load_translations' );
	add_action( 'init', array( 'Lafu_Gift_Cards_I18n', 'register_polylang' ) );
	add_action( 'init', 'lafu_gift_cards_register_assets' );
}

/**
 * Load the bundled translation for the current locale, if one ships with
 * the plugin. WordPress.org language packs load on their own.
 */
function lafu_gift_cards_load_translations() {
	Lafu_Gift_Cards_I18n::load( 'lafu-gift-cards', __DIR__ );
}

/**
 * Register the shared styles: the card (print page, admin, account) and the
 * storefront styles that build on it. Safe to call more than once.
 */
function lafu_gift_cards_register_assets() {
	if ( wp_style_is( 'lafu-gift-cards-card', 'registered' ) ) {
		return;
	}
	wp_register_style( 'lafu-gift-cards-card', plugins_url( 'assets/card.css', LAFU_GIFT_CARDS_FILE ), array(), LAFU_GIFT_CARDS_VERSION );
	wp_register_style( 'lafu-gift-cards', plugins_url( 'assets/frontend.css', LAFU_GIFT_CARDS_FILE ), array( 'lafu-gift-cards-card' ), LAFU_GIFT_CARDS_VERSION );
}

/**
 * A price as plain text for notes, emails, labels and JSON: no markup and
 * no HTML entities (wc_price() returns &euro; and the like).
 *
 * @param float $amount Amount.
 * @param array $args   wc_price() arguments.
 * @return string
 */
function lafu_gift_cards_plain_price( $amount, $args = array() ) {
	return html_entity_decode( wp_strip_all_tags( wc_price( $amount, $args ) ), ENT_QUOTES, 'UTF-8' );
}

/**
 * The shop's WooCommerce price format for live card previews in the
 * browser, so a preview shows amounts exactly like the shop does.
 *
 * @return array
 */
function lafu_gift_cards_money_format() {
	return array(
		'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
		'format'   => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, 'UTF-8' ),
		'decimals' => wc_get_price_decimals(),
		'dec'      => wc_get_price_decimal_separator(),
		'thou'     => wc_get_price_thousand_separator(),
	);
}

/**
 * Name of a card as customers see it at checkout and in their account,
 * for example "Gift card ••••7KQ2".
 *
 * @param object $card Card row.
 * @return string
 */
function lafu_gift_cards_card_title( $card ) {
	/**
	 * Name of a card at checkout and in the account, for example a store
	 * credit balance instead of a gift card.
	 *
	 * @param string $title Default title with the masked code.
	 * @param object $card  Card row.
	 */
	return (string) apply_filters(
		'lafu_gift_cards_card_title',
		/* translators: %s: masked gift card code */
		sprintf( __( 'Gift card %s', 'lafu-gift-cards' ), Lafu_Gift_Cards_Codes::mask( $card->code ) ),
		$card
	);
}

/**
 * Array stored in object meta. Missing meta comes back from WooCommerce as
 * an empty string, which must not turn into a one-item array.
 *
 * @param WC_Data $data Product, order or order item.
 * @param string  $key  Meta key.
 * @return array
 */
function lafu_gift_cards_meta_array( $data, $key ) {
	$value = $data ? $data->get_meta( $key ) : array();
	return is_array( $value ) ? $value : array();
}
