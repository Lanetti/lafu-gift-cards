<?php
/**
 * How a gift card is used: online, in the shop or at an appointment. One
 * place for the main button and the How to use it steps, so the gift card
 * page and the gift card email always say the same thing.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Redemption settings, the texts they lead to and the test email.
 */
class Lafu_Gift_Cards_Redemption {

	const MODES       = array( 'online', 'in_person', 'appointment' );
	const OPT_MODE    = 'lafu_gift_cards_redemption';
	const OPT_HIDE    = 'lafu_gift_cards_button_hide';
	const OPT_LABEL   = 'lafu_gift_cards_button_label';
	const OPT_URL     = 'lafu_gift_cards_button_url';
	const OPT_HEADING = 'lafu_gift_cards_howto_heading';
	const OPT_TEXT    = 'lafu_gift_cards_howto_text';
	const OPT_GIFT    = 'lafu_gift_cards_pay_new_with_existing';
	const TEST_ACTION = 'lafu_gc_test_email';
	const TEST_WAIT   = 60;
	const MAX_STEPS   = 8;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPT_MODE, array( __CLASS__, 'mode' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPT_LABEL, array( __CLASS__, 'clean_line' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPT_HEADING, array( __CLASS__, 'clean_line' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPT_URL, array( __CLASS__, 'clean_url' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPT_TEXT, array( __CLASS__, 'clean_text' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPT_GIFT, array( __CLASS__, 'clean_yes_no' ) );
		if ( is_admin() ) {
			add_action( 'admin_post_' . self::TEST_ACTION, array( __CLASS__, 'action_test_email' ) );
			add_action( 'woocommerce_admin_field_lafu_gc_test_email', array( __CLASS__, 'test_email_field' ) );
			add_action( 'woocommerce_admin_field_lafu_gc_pay_new_with_existing', array( __CLASS__, 'pay_new_with_existing_field' ) );
			add_action( 'admin_notices', array( __CLASS__, 'test_email_notice' ) );
		}
	}

	/**
	 * A known redemption mode, online when unknown.
	 *
	 * @param mixed $mode Mode key.
	 * @return string
	 */
	public static function mode( $mode = null ) {
		if ( null === $mode ) {
			$mode = get_option( self::OPT_MODE, 'online' );
		}
		return in_array( $mode, self::MODES, true ) ? $mode : 'online';
	}

	/**
	 * Whether a gift card may pay for gift card lines (new cards and
	 * top-ups). Off by default: an old card could otherwise buy a new one
	 * and reset its expiry date. Off, cards pay only the other lines.
	 *
	 * @return bool
	 */
	public static function pays_new_cards() {
		return 'yes' === get_option( self::OPT_GIFT, 'no' );
	}

	/**
	 * Mode names for the settings select.
	 *
	 * @return array
	 */
	public static function mode_labels() {
		return array(
			'online'      => __( 'Online, at checkout in the web shop', 'lafu-gift-cards' ),
			'in_person'   => __( 'In person, at the counter', 'lafu-gift-cards' ),
			'appointment' => __( 'At an appointment or booking', 'lafu-gift-cards' ),
		);
	}

	/**
	 * One line of text: no tags, no line breaks.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function clean_line( $value ) {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * A checkbox value: yes when ticked, no otherwise.
	 *
	 * @param mixed $value Raw value (null when the box was not ticked).
	 * @return string
	 */
	public static function clean_yes_no( $value ) {
		return 'yes' === $value || '1' === (string) $value ? 'yes' : 'no';
	}

	/**
	 * A web address for the button, http or https only.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function clean_url( $value ) {
		return esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
	}

	/**
	 * How to use it steps: simple HTML, one step per line.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function clean_text( $value ) {
		return trim( wp_kses_post( (string) $value ) );
	}

	/**
	 * A saved text in the visitor's language (WPML filters the option, Polylang through its strings).
	 *
	 * @param string $option Option name.
	 * @return string
	 */
	private static function saved( $option ) {
		return trim( Lafu_Gift_Cards_I18n::phrase( trim( (string) get_option( $option, '' ) ) ) );
	}

	/**
	 * Shop texts Polylang should offer for translation.
	 *
	 * @return array name => text.
	 */
	public static function strings() {
		$out = array();
		foreach ( array( self::OPT_LABEL, self::OPT_URL, self::OPT_HEADING, self::OPT_TEXT ) as $option ) {
			$value = trim( (string) get_option( $option, '' ) );
			if ( '' !== $value ) {
				$out[ $option ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Default button and steps for a mode.
	 *
	 * @param string $mode Mode key.
	 * @param string $shop Shop name.
	 * @return array label, url, heading, steps (plain text).
	 */
	public static function defaults( $mode, $shop ) {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$keep = __( 'What you do not spend stays on the card for next time.', 'lafu-gift-cards' );
		if ( 'appointment' === $mode ) {
			return array(
				'label'   => __( 'Book your appointment', 'lafu-gift-cards' ),
				'url'     => home_url( '/' ),
				'heading' => __( 'How to use it', 'lafu-gift-cards' ),
				'steps'   => array(
					/* translators: %s: shop name */
					sprintf( __( 'Book a time with %s.', 'lafu-gift-cards' ), $shop ),
					__( 'Show this card at your appointment: the QR code or the code.', 'lafu-gift-cards' ),
					$keep,
				),
			);
		}
		if ( 'in_person' === $mode ) {
			return array(
				'label'   => __( 'Visit our website', 'lafu-gift-cards' ),
				'url'     => home_url( '/' ),
				'heading' => __( 'How to use it', 'lafu-gift-cards' ),
				'steps'   => array(
					/* translators: %s: shop name */
					sprintf( __( 'Visit %s and show this card at the counter: the QR code or the code.', 'lafu-gift-cards' ), $shop ),
					$keep,
				),
			);
		}
		return array(
			'label'   => __( 'Shop with your gift card', 'lafu-gift-cards' ),
			'url'     => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ),
			'heading' => __( 'How to use it', 'lafu-gift-cards' ),
			'steps'   => array(
				/* translators: %s: shop address */
				sprintf( __( 'Online: enter the code at checkout on %s. What you do not spend stays on the card for next time.', 'lafu-gift-cards' ), $host ),
				__( 'In the shop: show the QR code at the counter.', 'lafu-gift-cards' ),
			),
		);
	}

	/**
	 * The button and steps for one card, as the gift card page and the email show them.
	 *
	 * @param object $card Card row (or sample).
	 * @return array mode, button (bool), label, url, heading, steps (safe HTML), custom_steps (bool).
	 */
	public static function texts( $card ) {
		$mode     = self::mode();
		$shop     = (string) Lafu_Gift_Cards_Designs::card_texts( $card )['shop'];
		$defaults = self::defaults( $mode, $shop );
		$label    = self::saved( self::OPT_LABEL );
		$url      = self::clean_url( self::saved( self::OPT_URL ) );
		$heading  = self::saved( self::OPT_HEADING );
		$custom   = self::steps( self::saved( self::OPT_TEXT ), $card, $shop );
		$texts    = array(
			'mode'         => $mode,
			'button'       => 'yes' !== get_option( self::OPT_HIDE, 'no' ),
			'label'        => '' !== $label ? $label : $defaults['label'],
			'url'          => '' !== $url ? $url : $defaults['url'],
			'heading'      => '' !== $heading ? $heading : $defaults['heading'],
			'steps'        => $custom ? $custom : array_map( 'esc_html', $defaults['steps'] ),
			'custom_steps' => (bool) $custom,
		);

		/**
		 * Main button and How to use it steps of a card, on the gift card page and in the email.
		 *
		 * @param array  $texts mode, button, label, url, heading, steps (HTML, escaped), custom_steps.
		 * @param object $card  Card row.
		 */
		$filtered = apply_filters( 'lafu_gift_cards_redemption_texts', $texts, $card );
		$filtered = is_array( $filtered ) ? array_merge( $texts, $filtered ) : $texts;

		$filtered['label']   = self::clean_line( $filtered['label'] );
		$filtered['heading'] = self::clean_line( $filtered['heading'] );
		$filtered['url']     = self::clean_url( $filtered['url'] );
		$filtered['button']  = (bool) $filtered['button'] && '' !== $filtered['url'] && '' !== $filtered['label'];
		$filtered['steps']   = array_values( array_filter( array_map( 'wp_kses_post', (array) $filtered['steps'] ), 'strlen' ) );
		return $filtered;
	}

	/**
	 * The shop's own steps: one per line, placeholders filled, simple HTML kept.
	 *
	 * @param string $text Saved text.
	 * @param object $card Card row.
	 * @param string $shop Shop name.
	 * @return array Safe HTML per step.
	 */
	public static function steps( $text, $card, $shop ) {
		if ( '' === trim( $text ) ) {
			return array();
		}
		$balance = lafu_gift_cards_plain_price( isset( $card->balance ) ? $card->balance : 0, array( 'currency' => isset( $card->currency ) ? $card->currency : '' ) );
		$text    = str_replace( array( '{shop}', '{balance}' ), array( esc_html( $shop ), esc_html( $balance ) ), $text );
		$steps   = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( wp_kses_post( $line ) );
			if ( '' !== trim( wp_strip_all_tags( $line ) ) ) {
				$steps[] = $line;
			}
		}
		return array_slice( $steps, 0, self::MAX_STEPS );
	}

	/**
	 * Settings rows under WooCommerce > Settings > Products > Gift cards.
	 *
	 * @return array
	 */
	public static function settings_fields() {
		return array(
			array(
				'title' => __( 'How the card is used', 'lafu-gift-cards' ),
				'type'  => 'title',
				'desc'  => __( 'The main button and the How to use it steps on the gift card page and in the gift card email. Leave a text empty to use the default for the choice below.', 'lafu-gift-cards' ),
				'id'    => 'lafu_gift_cards_redemption_options',
			),
			array(
				'title'   => __( 'Customers use the card', 'lafu-gift-cards' ),
				'desc'    => __( 'Online: Shop with your gift card, to the shop page. In person: Visit our website. Appointment: Book your appointment, to the booking page below.', 'lafu-gift-cards' ),
				'id'      => self::OPT_MODE,
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'online',
				'options' => self::mode_labels(),
			),
			array(
				'title'   => __( 'Allow paying for new gift cards with existing ones', 'lafu-gift-cards' ),
				'id'      => self::OPT_GIFT,
				'type'    => 'lafu_gc_pay_new_with_existing',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Hide the button', 'lafu-gift-cards' ),
				'desc'    => __( 'Show no main button on the gift card page and in the email.', 'lafu-gift-cards' ),
				'id'      => self::OPT_HIDE,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'       => __( 'Button text', 'lafu-gift-cards' ),
				'desc'        => __( 'For example Book your appointment. Empty uses the default.', 'lafu-gift-cards' ),
				'id'          => self::OPT_LABEL,
				'type'        => 'text',
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => self::defaults( self::mode(), get_bloginfo( 'name' ) )['label'],
			),
			array(
				'title'       => __( 'Button link', 'lafu-gift-cards' ),
				'desc'        => __( 'Your booking page or any web address. Empty uses the shop page (online) or the front page.', 'lafu-gift-cards' ),
				'id'          => self::OPT_URL,
				'type'        => 'url',
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => 'https://',
			),
			array(
				'title'       => __( 'How to use it heading', 'lafu-gift-cards' ),
				'id'          => self::OPT_HEADING,
				'type'        => 'text',
				'default'     => '',
				'placeholder' => __( 'How to use it', 'lafu-gift-cards' ),
			),
			array(
				'title'             => __( 'How to use it steps', 'lafu-gift-cards' ),
				'desc'              => __( 'One step per line. Links and bold text are allowed. {shop} is the shop name and {balance} the card balance. Empty uses the default steps.', 'lafu-gift-cards' ),
				'id'                => self::OPT_TEXT,
				'type'              => 'textarea',
				'default'           => '',
				'css'               => 'min-height:110px;',
				'placeholder'       => implode( "\n", self::defaults( self::mode(), get_bloginfo( 'name' ) )['steps'] ),
				'custom_attributes' => array( 'rows' => 5 ),
			),
			array(
				'title' => __( 'Test email', 'lafu-gift-cards' ),
				'id'    => 'lafu_gift_cards_test_email',
				'type'  => 'lafu_gc_test_email',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'lafu_gift_cards_redemption_options',
			),
		);
	}

	/**
	 * The Allow paying for new gift cards with existing ones row: a checkbox
	 * with the WooCommerce help tip (keyboard focusable) that explains the
	 * risk, also read out with the checkbox through aria-describedby.
	 *
	 * @param array $field Field.
	 */
	public static function pay_new_with_existing_field( $field ) {
		$help = __( 'Off is safer. If on, an old gift card can buy a new card or top one up, which resets its expiry date.', 'lafu-gift-cards' );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
				<?php echo wc_help_tip( $help ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip() escapes the text. ?>
			</th>
			<td class="forminp forminp-checkbox">
				<input type="checkbox" name="<?php echo esc_attr( $field['id'] ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>" value="yes" aria-describedby="<?php echo esc_attr( $field['id'] . '_help' ); ?>" <?php checked( self::pays_new_cards() ); ?>>
				<span id="<?php echo esc_attr( $field['id'] . '_help' ); ?>" class="screen-reader-text"><?php echo esc_html( $help ); ?></span>
			</td>
		</tr>
		<?php
	}

	/**
	 * The Send me a test gift card email row. A link, because the settings
	 * page is one form already.
	 *
	 * @param array $field Field.
	 */
	public static function test_email_field( $field ) {
		$user = wp_get_current_user();
		$url  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::TEST_ACTION ), self::TEST_ACTION );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( $field['title'] ); ?></th>
			<td class="forminp">
				<a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Send me a test gift card email', 'lafu-gift-cards' ); ?></a>
				<?php /* translators: %s: email address of the logged-in user */ ?>
				<p class="description"><?php echo esc_html( sprintf( __( 'A sample card goes to %s with the saved settings. Save your changes first.', 'lafu-gift-cards' ), $user->user_email ) ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Send the gift card email with a sample card to the logged-in shop manager.
	 */
	public static function action_test_email() {
		if ( ! current_user_can( Lafu_Gift_Cards_Admin::CAPABILITY ) || ! check_admin_referer( self::TEST_ACTION ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'lafu-gift-cards' ), '', array( 'response' => 403 ) );
		}
		$back = admin_url( 'admin.php?page=wc-settings&tab=products&section=' . Lafu_Gift_Cards_Settings::SECTION );
		$user = wp_get_current_user();
		$wait = 'lafu_gc_test_mail_' . $user->ID;
		if ( get_transient( $wait ) ) {
			/* translators: %d: seconds */
			self::back( $back, 'error', sprintf( __( 'A test email was sent a moment ago. Try again in %d seconds.', 'lafu-gift-cards' ), self::TEST_WAIT ) );
		}
		if ( ! is_email( $user->user_email ) ) {
			self::back( $back, 'error', __( 'Your user account has no valid email address.', 'lafu-gift-cards' ) );
		}
		set_transient( $wait, 1, self::TEST_WAIT );
		$sent = self::send_test( $user->user_email );
		if ( $sent ) {
			/* translators: %s: email address */
			self::back( $back, 'success', sprintf( __( 'Test gift card email sent to %s.', 'lafu-gift-cards' ), $user->user_email ) );
		}
		self::back( $back, 'error', __( 'The email could not be sent. Check that the gift card email is turned on under WooCommerce > Settings > Emails.', 'lafu-gift-cards' ) );
	}

	/**
	 * Send the real gift card email with a sample card. Nothing is saved.
	 *
	 * @param string $to Email address.
	 * @return bool
	 */
	public static function send_test( $to ) {
		$emails = WC()->mailer()->get_emails();
		if ( empty( $emails['Lafu_Gift_Cards_Email'] ) ) {
			return false;
		}
		$card                  = Lafu_Gift_Cards_Email::sample_card();
		$card->recipient_email = $to;
		$prefix                = static function ( $subject ) {
			/* translators: %s: email subject */
			return sprintf( __( '[Test] %s', 'lafu-gift-cards' ), $subject );
		};
		add_filter( 'woocommerce_email_subject_lafu_gift_card', $prefix );
		$sent = $emails['Lafu_Gift_Cards_Email']->trigger( $card );
		remove_filter( 'woocommerce_email_subject_lafu_gift_card', $prefix );
		return (bool) $sent;
	}

	/**
	 * Redirect back with a one-time notice.
	 *
	 * @param string $url     Target.
	 * @param string $type    success or error.
	 * @param string $message Text.
	 */
	private static function back( $url, $type, $message ) {
		set_transient(
			'lafu_gc_test_flash_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Show and clear the test email notice.
	 */
	public static function test_email_notice() {
		$key   = 'lafu_gc_test_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( ! is_array( $flash ) || ! isset( $flash['type'], $flash['message'] ) ) {
			return;
		}
		delete_transient( $key );
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( 'success' === $flash['type'] ? 'success' : 'error' ), esc_html( $flash['message'] ) );
	}
}
