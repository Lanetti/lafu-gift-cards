<?php
/**
 * Personal data export and erase (Tools > Export / Erase Personal Data).
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gift cards hold names, email addresses and messages. They can be
 * exported and erased like the rest of a shop's personal data. Codes and
 * balances stay, because they are money the card holder owns.
 */
class Lafu_Gift_Cards_Privacy {

	const PER_PAGE = 50;

	/**
	 * Hook the WordPress privacy tools.
	 */
	public static function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function exporters( $exporters ) {
		$exporters['lafu-gift-cards'] = array(
			'exporter_friendly_name' => __( 'Gift cards', 'lafu-gift-cards' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function erasers( $erasers ) {
		$erasers['lafu-gift-cards'] = array(
			'eraser_friendly_name' => __( 'Gift cards', 'lafu-gift-cards' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export the gift cards a person received, bought or saved.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, from 1.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		$cards = Lafu_Gift_Cards_Store::for_person( $email, $page, self::PER_PAGE );
		$items = array();
		foreach ( $cards as $card ) {
			$money   = array( 'currency' => $card->currency );
			$items[] = array(
				'group_id'    => 'lafu-gift-cards',
				'group_label' => __( 'Gift cards', 'lafu-gift-cards' ),
				'item_id'     => 'lafu-gift-card-' . $card->id,
				'data'        => array(
					array(
						'name'  => __( 'Code', 'lafu-gift-cards' ),
						'value' => $card->code,
					),
					array(
						'name'  => __( 'Balance', 'lafu-gift-cards' ),
						'value' => lafu_gift_cards_plain_price( $card->balance, $money ),
					),
					array(
						'name'  => __( 'Amount', 'lafu-gift-cards' ),
						'value' => lafu_gift_cards_plain_price( $card->initial_amount, $money ),
					),
					array(
						'name'  => __( 'Status', 'lafu-gift-cards' ),
						'value' => Lafu_Gift_Cards_Account::status_label( $card ),
					),
					array(
						'name'  => __( 'Recipient name', 'lafu-gift-cards' ),
						'value' => $card->recipient_name,
					),
					array(
						'name'  => __( 'Recipient email', 'lafu-gift-cards' ),
						'value' => $card->recipient_email,
					),
					array(
						'name'  => __( 'From', 'lafu-gift-cards' ),
						'value' => $card->sender_name,
					),
					array(
						'name'  => __( 'Message', 'lafu-gift-cards' ),
						'value' => $card->message,
					),
					array(
						'name'  => __( 'Created', 'lafu-gift-cards' ),
						'value' => $card->created_at,
					),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => count( $cards ) < self::PER_PAGE,
		);
	}

	/**
	 * Erase names, emails and messages from a person's gift cards. The
	 * card, its code and balance stay so the money is not lost.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, from 1.
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) {
		unset( $page );
		$cards   = Lafu_Gift_Cards_Store::for_person( $email, 1, self::PER_PAGE );
		$removed = false;
		$kept    = false;
		foreach ( $cards as $card ) {
			$removed = Lafu_Gift_Cards_Store::update(
				(int) $card->id,
				array(
					'recipient_name'  => '',
					'recipient_email' => '',
					'sender_name'     => '',
					'message'         => '',
					'user_id'         => 0,
				)
			) || $removed;
			$kept    = $kept || (float) $card->balance > 0;
		}
		Lafu_Gift_Cards_Store::forget_buyer( $email );
		return array(
			'items_removed'  => $removed,
			'items_retained' => $kept,
			'messages'       => $kept ? array( __( 'Gift card codes and balances were kept, because the remaining balance belongs to whoever holds the card. Names, email addresses and messages were removed.', 'lafu-gift-cards' ) ) : array(),
			'done'           => count( $cards ) < self::PER_PAGE,
		);
	}

	/**
	 * Suggested text for the site's privacy policy.
	 */
	public static function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text for shops that sell gift cards:', 'lafu-gift-cards' ) . '</p>';
		$text .= '<p>' . esc_html__( 'When you buy a gift card for someone, we store the name and email address of the recipient, your name if you choose to show it, and your message, so that we can deliver the card and show who it is from. We keep the gift card code, its balance and its history for as long as the card can be used and as long as accounting law requires. The recipient\'s details are used only to deliver the card.', 'lafu-gift-cards' ) . '</p>';
		wp_add_privacy_policy_content( __( 'LAFU Gift Cards', 'lafu-gift-cards' ), wp_kses_post( $text ) );
	}
}
