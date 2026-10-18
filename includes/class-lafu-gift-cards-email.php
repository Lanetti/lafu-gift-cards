<?php
/**
 * The gift card email to the recipient.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * A normal WooCommerce email, so the shop's email styles, header image and
 * footer apply and it can be edited under WooCommerce > Settings > Emails.
 */
class Lafu_Gift_Cards_Email extends WC_Email {

	/**
	 * Card being sent.
	 *
	 * @var object|null
	 */
	public $card = null;

	/**
	 * Whether this email switched to the buyer's language.
	 *
	 * @var bool
	 */
	public $lafu_switched = false;

	/**
	 * Set up the email.
	 */
	public function __construct() {
		$this->id             = 'lafu_gift_card';
		$this->customer_email = true;
		$this->title          = __( 'Gift card', 'lafu-gift-cards' );
		$this->description    = __( 'Sent to the person who receives a gift card, right after payment or on the chosen delivery date.', 'lafu-gift-cards' );
		$this->template_base  = LAFU_GIFT_CARDS_DIR . '/templates/';
		$this->template_html  = 'emails/lafu-gift-card.php';
		$this->template_plain = 'emails/plain/lafu-gift-card.php';
		$this->placeholders   = array(
			'{amount}' => '',
			'{sender}' => '',
		);
		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'You received a {amount} gift card from {site_title}', 'lafu-gift-cards' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Your gift card', 'lafu-gift-cards' );
	}

	/**
	 * Default text under the card.
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return __( 'Enjoy your gift!', 'lafu-gift-cards' );
	}

	/**
	 * Send the card.
	 *
	 * @param object $card Card row.
	 * @return bool
	 */
	public function trigger( $card ) {
		$this->card = $card;
		$this->setup_locale();
		$this->recipient                = $card->recipient_email;
		$this->placeholders['{amount}'] = lafu_gift_cards_plain_price( $card->initial_amount, array( 'currency' => $card->currency ) );
		$this->placeholders['{sender}'] = $card->sender_name;

		$sent = false;
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
		return (bool) $sent;
	}

	/**
	 * Use the language the buyer checked out in, or the shop language.
	 */
	public function setup_locale() {
		$this->lafu_switched = Lafu_Gift_Cards_I18n::switch_for_card( $this->card );
		if ( ! $this->lafu_switched ) {
			parent::setup_locale();
		}
	}

	/**
	 * Restore the language after the email is built.
	 */
	public function restore_locale() {
		if ( $this->lafu_switched ) {
			$this->lafu_switched = false;
			Lafu_Gift_Cards_I18n::restore();
			return;
		}
		parent::restore_locale();
	}

	/**
	 * HTML body.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'card'               => $this->card,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Plain text body.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'card'               => $this->card,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
}
