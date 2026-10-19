<?php
/**
 * Where shop owners learn that Pro exists, the way WordPress.org allows it:
 * a "Pro features" link on the plugin's row on the Plugins screen, and one
 * small card at the bottom of the plugin's own Gift cards page that can be
 * hidden for good. Nothing shows once Pro is active, nothing shows on other
 * admin pages, and no feature is locked.
 *
 * @package LAFU_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pro information in the free plugin.
 */
class Lafu_Gift_Cards_Pro_Info {

	const URL    = 'https://www.lanetti.fi/products/gift-cards';
	const HIDDEN = 'lafu_gc_pro_card_hidden';
	const ACTION = 'lafu_gc_hide_pro_card';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'hide' ) );
	}

	/**
	 * Whether Pro is active on this site.
	 *
	 * @return bool
	 */
	private static function pro_active() {
		return defined( 'LAFU_GCP_VERSION' );
	}

	/**
	 * "Pro features" next to "View details" on the Plugins screen.
	 *
	 * @param array  $links Row links.
	 * @param string $file  Plugin file.
	 * @return array
	 */
	public static function row_meta( $links, $file ) {
		if ( plugin_basename( LAFU_GIFT_CARDS_FILE ) !== $file || self::pro_active() ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( self::URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Pro features', 'lafu-gift-cards' ) . '</a>';
		return $links;
	}

	/**
	 * The card at the bottom of the Gift cards page, until it is hidden.
	 */
	public static function card() {
		if ( self::pro_active() || get_user_meta( get_current_user_id(), self::HIDDEN, true ) ) {
			return;
		}
		$hide = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
		?>
		<aside class="lafu-gc-pro-card" aria-labelledby="lafu-gc-pro-card-title">
			<div>
				<h2 id="lafu-gc-pro-card-title"><?php esc_html_e( 'More with LAFU Gift Cards Pro', 'lafu-gift-cards' ); ?></h2>
				<p><?php esc_html_e( 'A till for your counter, printed card batches, store credit, top-ups, group gifts, photo and brand designs, bonus cards, reports and more.', 'lafu-gift-cards' ); ?></p>
			</div>
			<p class="lafu-gc-pro-card__actions">
				<a class="lafu-gc-btn lafu-gc-btn--ghost" href="<?php echo esc_url( self::URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See what Pro adds', 'lafu-gift-cards' ); ?></a>
				<a class="button-link" href="<?php echo esc_url( $hide ); ?>"><?php esc_html_e( 'Hide', 'lafu-gift-cards' ); ?></a>
			</p>
		</aside>
		<?php
	}

	/**
	 * Hide the card for this user.
	 */
	public static function hide() {
		if ( ! current_user_can( Lafu_Gift_Cards_Admin::CAPABILITY ) || ! check_admin_referer( self::ACTION ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'lafu-gift-cards' ), '', array( 'response' => 403 ) );
		}
		update_user_meta( get_current_user_id(), self::HIDDEN, 1 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : Lafu_Gift_Cards_Admin::page_url() );
		exit;
	}
}
