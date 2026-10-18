<?php
/**
 * Languages: bundled files, Polylang card texts, and the language of an order.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the right translation file and keeps card texts translatable.
 */
class Lafu_Gift_Cards_I18n {

	const ORDER_LOCALE = '_lafu_gc_locale';

	/**
	 * Locales shops use that should open a file we ship under another name.
	 *
	 * @return array
	 */
	public static function aliases() {
		return array(
			'fi_FI' => 'fi',
			'sv'    => 'sv_SE',
			'de'    => 'de_DE',
			'da'    => 'da_DK',
			'nb'    => 'nb_NO',
			'nn'    => 'nb_NO',
			'nl'    => 'nl_NL',
			'fr'    => 'fr_FR',
			'es'    => 'es_ES',
			'it'    => 'it_IT',
			'pt'    => 'pt_PT',
			'pl'    => 'pl_PL',
			'zh'    => 'zh_CN',
			'id'    => 'id_ID',
			'hi'    => 'hi_IN',
			'ar'    => 'ar',
			'ar_EG' => 'ar',
			'uk'    => 'uk',
			'tr'    => 'tr_TR',
			'ru'    => 'ru_RU',
			'ja'    => 'ja',
		);
	}

	/**
	 * Load one plugin's bundled translation for the current locale.
	 *
	 * @param string $domain Text domain.
	 * @param string $dir    Plugin directory.
	 */
	public static function load( $domain, $dir ) {
		$locale  = determine_locale();
		$aliases = self::aliases();
		if ( isset( $aliases[ $locale ] ) ) {
			$locale = $aliases[ $locale ];
		}
		$file = $dir . '/languages/' . $domain . '-' . $locale . '.l10n.php';
		if ( ! is_readable( $file ) ) {
			$short   = substr( $locale, 0, 2 );
			$matches = glob( $dir . '/languages/' . $domain . '-' . $short . '*.l10n.php' );
			if ( is_array( $matches ) && $matches ) {
				$file = $matches[0];
			}
		}
		if ( is_readable( $file ) ) {
			unload_textdomain( $domain );
			load_textdomain( $domain, $file, $locale );
		}
	}

	/**
	 * Register the shop's card texts with Polylang, when it is active.
	 */
	public static function register_polylang() {
		if ( ! function_exists( 'pll_register_string' ) ) {
			return;
		}
		$saved = get_option( 'lafu_gift_cards_card_texts', array() );
		if ( ! is_array( $saved ) ) {
			return;
		}
		foreach ( array( 'label', 'shop', 'line', 'for', 'from', 'until' ) as $key ) {
			$value = isset( $saved[ $key ] ) ? trim( (string) $saved[ $key ] ) : '';
			if ( '' !== $value ) {
				pll_register_string( 'lafu_gc_card_' . $key, $value, 'LAFU Gift Cards', false );
			}
		}
	}

	/**
	 * Polylang translation of a shop-written card text, or the text itself.
	 *
	 * @param string $text Card text.
	 * @return string
	 */
	public static function phrase( $text ) {
		if ( '' !== $text && function_exists( 'pll__' ) ) {
			return pll__( $text );
		}
		return $text;
	}

	/**
	 * Remember the checkout language on the order. WPML and Polylang already
	 * set the locale before this runs, so the gift card email can use it later.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function remember_order( $order ) {
		if ( $order instanceof WC_Order && '' === (string) $order->get_meta( self::ORDER_LOCALE ) ) {
			$order->update_meta_data( self::ORDER_LOCALE, determine_locale() );
		}
	}

	/**
	 * Language the gift card email should use for a card.
	 *
	 * @param object|null $card Card row.
	 * @return string
	 */
	public static function locale_for_card( $card ) {
		$order = ( $card && ! empty( $card->order_id ) ) ? wc_get_order( (int) $card->order_id ) : null;
		if ( ! $order ) {
			return '';
		}
		$locale = (string) $order->get_meta( self::ORDER_LOCALE );
		if ( '' === $locale ) {
			$lang = (string) $order->get_meta( 'wpml_language' );
			if ( '' !== $lang && has_filter( 'wpml_locale' ) ) {
				$locale = (string) apply_filters( 'wpml_locale', null, $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own filter.
			}
		}
		if ( '' === $locale && function_exists( 'pll_get_post_language' ) ) {
			$locale = (string) pll_get_post_language( $order->get_id(), 'locale' );
		}
		return $locale;
	}

	/**
	 * Switch to the card's order language and reload the plugin translations.
	 *
	 * @param object|null $card Card row.
	 * @return bool Whether the locale was switched.
	 */
	public static function switch_for_card( $card ) {
		$locale = self::locale_for_card( $card );
		if ( '' === $locale || determine_locale() === $locale ) {
			return false;
		}
		if ( ! switch_to_locale( $locale ) ) {
			return false;
		}
		self::load( 'lafu-gift-cards', LAFU_GIFT_CARDS_DIR );
		if ( defined( 'LAFU_GCP_DIR' ) ) {
			self::load( 'lafu-gift-cards-pro', LAFU_GCP_DIR );
		}
		return true;
	}

	/**
	 * Back to the previous language after an email.
	 */
	public static function restore() {
		restore_previous_locale();
		self::load( 'lafu-gift-cards', LAFU_GIFT_CARDS_DIR );
		if ( defined( 'LAFU_GCP_DIR' ) ) {
			self::load( 'lafu-gift-cards-pro', LAFU_GCP_DIR );
		}
	}

	/**
	 * WooCommerce screen where the shop edits one of our emails.
	 *
	 * WooCommerce keys the section by the lowercased email class name, not the email id.
	 *
	 * @param string $email_id Email id.
	 * @return string
	 */
	public static function email_settings_url( $email_id ) {
		$section = $email_id;
		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			foreach ( WC()->mailer()->get_emails() as $email ) {
				if ( $email instanceof WC_Email && $email->id === $email_id ) {
					$section = strtolower( get_class( $email ) );
					break;
				}
			}
		}
		return admin_url( 'admin.php?page=wc-settings&tab=email&section=' . rawurlencode( $section ) );
	}
}
