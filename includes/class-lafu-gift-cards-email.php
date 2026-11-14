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
	 * WooCommerce's email preview (Settings > Emails) never calls trigger(),
	 * so it gets a sample card instead of none.
	 *
	 * @param WC_Email $email Email being previewed.
	 * @return WC_Email
	 */
	public static function prepare_preview( $email ) {
		if ( $email instanceof self && ! $email->card ) {
			$email->card                     = self::sample_card();
			$email->placeholders['{amount}'] = lafu_gift_cards_plain_price( $email->card->initial_amount, array( 'currency' => $email->card->currency ) );
			$email->placeholders['{sender}'] = $email->card->sender_name;
		}
		return $email;
	}

	/**
	 * A card with sample details, for previews. It is never saved.
	 *
	 * @return object
	 */
	public static function sample_card() {
		$amount = 50;
		return (object) array(
			'id'              => 0,
			'code'            => 'SAMP-LE12-3456-7890',
			'lookup'          => 'SAMPLE1234567890',
			'status'          => Lafu_Gift_Cards_Store::STATUS_ACTIVE,
			'currency'        => get_woocommerce_currency(),
			'initial_amount'  => $amount,
			'balance'         => $amount,
			'design'          => Lafu_Gift_Cards_Designs::resolve( '' ),
			'recipient_name'  => 'Anna',
			'recipient_email' => 'anna@example.com',
			'sender_name'     => 'Sam',
			'message'         => __( 'Happy birthday!', 'lafu-gift-cards' ),
			'physical'        => 0,
			'deliver_at'      => null,
			'delivered_at'    => null,
			'expires_at'      => Lafu_Gift_Cards_Settings::default_expiry(),
			'buyer_id'        => 0,
			'user_id'         => 0,
			'order_id'        => 0,
			'order_item_id'   => 0,
			'meta'            => null,
			'created_at'      => current_time( 'mysql', true ),
			'updated_at'      => current_time( 'mysql', true ),
		);
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
				'card'               => $this->card ? $this->card : self::sample_card(),
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
				'card'               => $this->card ? $this->card : self::sample_card(),
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
