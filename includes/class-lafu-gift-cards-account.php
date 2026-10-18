<?php
/**
 * Customer side: My Account page, balance check and the printable card.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lets customers see their cards, save a card they received to their
 * account, check a balance and print a card.
 */
class Lafu_Gift_Cards_Account {

	const ENDPOINT    = 'gift-cards';
	const SAVE_NONCE  = 'lafu-gc-save';
	const PRINT_QUERY = 'lafu_gc_print';

	/**
	 * Result of a balance check in this request.
	 *
	 * @var object|WP_Error|null
	 */
	private static $balance_result = null;

	/**
	 * Hook the account page, shortcode and print page.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'endpoint' ) );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_var' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_item' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'page' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_save' ) );
		add_action( 'template_redirect', array( __CLASS__, 'print_page' ), 1 );
		add_shortcode( 'lafu_gift_card_balance', array( __CLASS__, 'balance_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'theme_icon' ) );
	}

	/**
	 * A gift icon for the Gift cards entry in themes that give every My Account
	 * entry an icon, so ours does not get the theme's generic one. Only for
	 * those themes: elsewhere entries have no icons and neither does ours.
	 */
	public static function theme_icon() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		$gift  = 'url("data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/></svg>' ) . '")';
		$mask  = 'background-color:currentColor;-webkit-mask:' . $gift . ' center/contain no-repeat;mask:' . $gift . ' center/contain no-repeat;';
		$link  = '.woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link--gift-cards a';
		$css   = array(
			// Font Awesome 5 is already loaded by Storefront: its own gift glyph.
			'storefront' => $link . '::before{content:"\f06b";}',
			'oceanwp'    => $link . ':before{content:"";width:16px;height:16px;margin-top:3px;' . $mask . '}',
			'blocksy'    => '.woocommerce-MyAccount-navigation-link--gift-cards a:before{content:"";height:16px;' . str_replace( 'center/contain', 'left center/16px 16px', $mask ) . '}',
			'woodmart'   => '.wd-nav-my-acc>li.wd-my-acc-gift-cards>a .wd-nav-icon:before{content:"";width:1em;height:1em;' . $mask . '}',
		);
		$theme = get_template();
		if ( ! isset( $css[ $theme ] ) ) {
			return;
		}
		wp_register_style( 'lafu-gift-cards-account-icon', false, array(), LAFU_GIFT_CARDS_VERSION );
		wp_enqueue_style( 'lafu-gift-cards-account-icon' );
		wp_add_inline_style( 'lafu-gift-cards-account-icon', $css[ $theme ] );
	}

	/**
	 * Register the My Account endpoint.
	 */
	public static function endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * Tell WooCommerce about the endpoint.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function query_var( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Menu entry before Log out.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public static function menu_item( $items ) {
		$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
		unset( $items['customer-logout'] );
		$items[ self::ENDPOINT ] = __( 'Gift cards', 'lafu-gift-cards' );
		if ( $logout ) {
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	public static function title() {
		return __( 'Gift cards', 'lafu-gift-cards' );
	}

	/**
	 * The My Account page.
	 */
	public static function page() {
		/**
		 * Cards listed on the account page.
		 *
		 * @param array $cards   Card rows.
		 * @param int   $user_id Customer.
		 */
		$cards = (array) apply_filters( 'lafu_gift_cards_account_cards', Lafu_Gift_Cards_Store::for_user( get_current_user_id() ), get_current_user_id() );

		/**
		 * Extra sections at the top of the account gift card page.
		 *
		 * @param int $user_id Customer.
		 */
		do_action( 'lafu_gift_cards_account_top', get_current_user_id() );
		?>
		<form method="post" class="lafu-gc-save woocommerce-form">
			<p><?php esc_html_e( 'Got a gift card? Save it here so you can use it with one click at checkout.', 'lafu-gift-cards' ); ?></p>
			<p class="lafu-gc-redeem-row">
				<label class="screen-reader-text" for="lafu_gc_save_code"><?php esc_html_e( 'Gift card code', 'lafu-gift-cards' ); ?></label>
				<input type="text" class="input-text" id="lafu_gc_save_code" name="lafu_gc_save_code" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e( 'Gift card code', 'lafu-gift-cards' ); ?>" required>
				<?php wp_nonce_field( self::SAVE_NONCE, 'lafu_gc_save_nonce' ); ?>
				<button type="submit" class="button wp-element-button"><?php esc_html_e( 'Save card', 'lafu-gift-cards' ); ?></button>
			</p>
		</form>

		<?php if ( ! $cards ) : ?>
			<p><?php esc_html_e( 'You have no gift cards yet.', 'lafu-gift-cards' ); ?></p>
			<?php
			return;
		endif;
		?>
		<table class="woocommerce-orders-table shop_table shop_table_responsive lafu-gc-account-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Code', 'lafu-gift-cards' ); ?></th>
					<th><?php esc_html_e( 'Balance', 'lafu-gift-cards' ); ?></th>
					<th><?php esc_html_e( 'Status', 'lafu-gift-cards' ); ?></th>
					<th><?php esc_html_e( 'Valid until', 'lafu-gift-cards' ); ?></th>
					<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'lafu-gift-cards' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $cards as $card ) : ?>
					<?php $own = self::owns( $card, get_current_user_id() ); ?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Code', 'lafu-gift-cards' ); ?>">
							<code><?php echo esc_html( $own ? $card->code : Lafu_Gift_Cards_Codes::mask( $card->code ) ); ?></code>
							<?php if ( ! $own ) : ?>
								<br><small>
								<?php
								/* translators: %s: recipient email */
								echo esc_html( sprintf( __( 'Gift to %s', 'lafu-gift-cards' ), $card->recipient_email ) );
								?>
								</small>
							<?php endif; ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Balance', 'lafu-gift-cards' ); ?>"><?php echo wp_kses_post( wc_price( $card->balance, array( 'currency' => $card->currency ) ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Status', 'lafu-gift-cards' ); ?>"><?php echo esc_html( self::status_label( $card ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Valid until', 'lafu-gift-cards' ); ?>"><?php echo esc_html( self::expiry_label( $card ) ); ?></td>
						<td>
							<?php if ( $own && Lafu_Gift_Cards_Store::STATUS_ACTIVE === $card->status ) : ?>
								<a href="<?php echo esc_url( self::print_url( $card ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print', 'lafu-gift-cards' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Save a received card to the logged-in customer's account.
	 */
	public static function handle_save() {
		if ( ! isset( $_POST['lafu_gc_save_code'], $_POST['lafu_gc_save_nonce'] ) || ! is_user_logged_in() ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['lafu_gc_save_nonce'] ) ), self::SAVE_NONCE ) ) {
			wc_add_notice( __( 'The page expired. Please try again.', 'lafu-gift-cards' ), 'error' );
			return;
		}
		if ( Lafu_Gift_Cards_Codes::is_limited() ) {
			wc_add_notice( __( 'Too many attempts. Please wait a few minutes and try again.', 'lafu-gift-cards' ), 'error' );
			return;
		}
		$card = Lafu_Gift_Cards_Store::find_by_code( sanitize_text_field( wp_unslash( $_POST['lafu_gc_save_code'] ) ) );
		$user = get_current_user_id();
		if ( ! $card || ( (int) $card->user_id && (int) $card->user_id !== $user ) || Lafu_Gift_Cards_Store::STATUS_VOID === $card->status ) {
			Lafu_Gift_Cards_Codes::record_failure();
			wc_add_notice( __( 'This gift card code is not valid.', 'lafu-gift-cards' ), 'error' );
			return;
		}
		Lafu_Gift_Cards_Store::update( (int) $card->id, array( 'user_id' => $user ) );
		wc_add_notice( __( 'Gift card saved to your account.', 'lafu-gift-cards' ) );
		wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
		exit;
	}

	/**
	 * [lafu_gift_card_balance]: a balance check form for any page.
	 *
	 * @return string
	 */
	public static function balance_shortcode() {
		wp_enqueue_style( 'lafu-gift-cards' );
		$result = self::check_balance();
		ob_start();
		?>
		<form method="post" class="lafu-gc-balance">
			<p class="lafu-gc-redeem-row">
				<label class="screen-reader-text" for="lafu_gc_balance_code"><?php esc_html_e( 'Gift card code', 'lafu-gift-cards' ); ?></label>
				<input type="text" class="input-text" id="lafu_gc_balance_code" name="lafu_gc_balance_code" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e( 'Gift card code', 'lafu-gift-cards' ); ?>" required>
				<button type="submit" class="button wp-element-button"><?php esc_html_e( 'Check balance', 'lafu-gift-cards' ); ?></button>
			</p>
			<?php if ( is_wp_error( $result ) ) : ?>
				<p class="lafu-gc-balance-result lafu-gc-redeem-message is-error" role="status"><?php echo esc_html( $result->get_error_message() ); ?></p>
			<?php elseif ( $result ) : ?>
				<p class="lafu-gc-balance-result" role="status">
					<?php
					/* translators: 1: masked code, 2: balance */
					echo wp_kses_post( sprintf( __( 'Gift card %1$s: %2$s left.', 'lafu-gift-cards' ), esc_html( Lafu_Gift_Cards_Codes::mask( $result->code ) ), wc_price( $result->balance, array( 'currency' => $result->currency ) ) ) );
					?>
					<?php echo esc_html( self::status_label( $result ) . ( $result->expires_at ? ' · ' . self::expiry_label( $result ) : '' ) ); ?>
				</p>
			<?php endif; ?>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Link to the printable card. The key proves the link came from us
	 * (email, account or admin), so card IDs cannot be walked.
	 *
	 * @param object $card Card row.
	 * @return string
	 */
	public static function print_url( $card ) {
		return add_query_arg(
			array(
				self::PRINT_QUERY => (int) $card->id,
				'key'             => self::print_key( $card ),
			),
			home_url( '/' )
		);
	}

	/**
	 * The gift card page that starts printing (or Save as PDF) as soon as it opens.
	 *
	 * @param object $card Card row.
	 * @return string
	 */
	public static function print_now_url( $card ) {
		return add_query_arg( 'print', '1', self::print_url( $card ) );
	}

	/**
	 * Whether gift card emails show the code and QR code. Off by default: the
	 * email shows the card and a button, the code is on the private gift card page.
	 *
	 * @return bool
	 */
	public static function email_shows_code() {
		return 'yes' === get_option( 'lafu_gift_cards_email_code', 'no' );
	}

	/**
	 * The card as an email shows it: without the code and QR code unless the shop turned that on.
	 *
	 * @param object $card Card row.
	 * @return object
	 */
	public static function email_card( $card ) {
		if ( self::email_shows_code() ) {
			return $card;
		}
		$shown       = clone $card;
		$shown->code = '';
		return $shown;
	}

	/**
	 * The recipient's gift card page (signed link from the email, account
	 * and admin): Lafu_Gift_Cards_Gift_Page.
	 */
	public static function print_page() {
		if ( ! isset( $_GET[ self::PRINT_QUERY ], $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed link checked below with hash_equals.
			return;
		}
		$card = Lafu_Gift_Cards_Store::get( absint( wp_unslash( $_GET[ self::PRINT_QUERY ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed link checked below with hash_equals.
		$key  = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed link checked below with hash_equals.
		if ( ! $card || ! hash_equals( self::print_key( $card ), $key ) ) {
			wp_die( esc_html__( 'This gift card link is not valid.', 'lafu-gift-cards' ), '', array( 'response' => 404 ) );
		}
		Lafu_Gift_Cards_Gift_Page::output( $card );
	}

	/**
	 * Whether a customer may spend a card from their account: cards saved
	 * to the account, and cards they bought for themselves. A card bought
	 * as a gift belongs to the recipient, not the buyer.
	 *
	 * @param object $card    Card row.
	 * @param int    $user_id User ID.
	 * @return bool
	 */
	public static function owns( $card, $user_id ) {
		if ( ! $card || ! $user_id ) {
			return false;
		}
		if ( (int) $card->user_id ) {
			return (int) $card->user_id === (int) $user_id;
		}
		if ( (int) $card->buyer_id !== (int) $user_id ) {
			return false;
		}
		$user = get_userdata( $user_id );
		return '' === (string) $card->recipient_email || ( $user && strtolower( $card->recipient_email ) === strtolower( $user->user_email ) );
	}

	/**
	 * Status for styling: active, used, expired or void.
	 *
	 * @param object $card Card row.
	 * @return string
	 */
	public static function status_key( $card ) {
		if ( Lafu_Gift_Cards_Store::STATUS_VOID === $card->status ) {
			return 'void';
		}
		if ( Lafu_Gift_Cards_Store::STATUS_INACTIVE === $card->status ) {
			return 'inactive';
		}
		if ( Lafu_Gift_Cards_Store::STATUS_EXPIRED === $card->status || Lafu_Gift_Cards_Store::is_past( $card->expires_at ) ) {
			return 'expired';
		}
		return (float) $card->balance <= 0 ? 'used' : 'active';
	}

	/**
	 * Status in words.
	 *
	 * @param object $card Card row.
	 * @return string
	 */
	public static function status_label( $card ) {
		if ( Lafu_Gift_Cards_Store::STATUS_VOID === $card->status ) {
			return __( 'Cancelled', 'lafu-gift-cards' );
		}
		if ( Lafu_Gift_Cards_Store::STATUS_INACTIVE === $card->status ) {
			return __( 'Not activated', 'lafu-gift-cards' );
		}
		if ( Lafu_Gift_Cards_Store::STATUS_EXPIRED === $card->status || Lafu_Gift_Cards_Store::is_past( $card->expires_at ) ) {
			return __( 'Expired', 'lafu-gift-cards' );
		}
		if ( (float) $card->balance <= 0 ) {
			return __( 'Used', 'lafu-gift-cards' );
		}
		return __( 'Active', 'lafu-gift-cards' );
	}

	/**
	 * Expiry date in words.
	 *
	 * @param object $card Card row.
	 * @return string
	 */
	public static function expiry_label( $card ) {
		if ( empty( $card->expires_at ) ) {
			return __( 'No expiry', 'lafu-gift-cards' );
		}
		return wp_date( get_option( 'date_format' ), strtotime( $card->expires_at . ' UTC' ) );
	}

	/**
	 * Look up the code posted to the balance form, once per request.
	 *
	 * @return object|WP_Error|null
	 */
	private static function check_balance() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Read-only lookup on pages that may be cached; limited per visitor instead.
		if ( null !== self::$balance_result || ! isset( $_POST['lafu_gc_balance_code'] ) ) {
			return self::$balance_result;
		}
		$code = sanitize_text_field( wp_unslash( $_POST['lafu_gc_balance_code'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( Lafu_Gift_Cards_Codes::is_limited() ) {
			self::$balance_result = new WP_Error( 'lafu_gc_limited', __( 'Too many attempts. Please wait a few minutes and try again.', 'lafu-gift-cards' ) );
			return self::$balance_result;
		}
		$card = Lafu_Gift_Cards_Store::find_by_code( $code );
		if ( ! $card ) {
			Lafu_Gift_Cards_Codes::record_failure();
			self::$balance_result = new WP_Error( 'lafu_gc_unknown', __( 'This gift card code is not valid.', 'lafu-gift-cards' ) );
			return self::$balance_result;
		}
		self::$balance_result = $card;
		return $card;
	}

	/**
	 * Signature of a card's print link.
	 *
	 * @param object $card Card row.
	 * @return string
	 */
	private static function print_key( $card ) {
		return substr( hash_hmac( 'sha256', $card->id . '|' . $card->lookup, wp_salt( 'auth' ) ), 0, 32 );
	}
}
