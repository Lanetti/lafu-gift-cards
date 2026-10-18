<?php
/**
 * Tables, schedules and the My Account endpoint.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the two plugin tables: cards and the ledger.
 */
class Lafu_Gift_Cards_Install {

	const DB_VERSION = '2';
	const DAILY_HOOK = 'lafu_gift_cards_daily';

	/**
	 * Activation: tables, daily job and rewrite rules for the account page.
	 */
	public static function activate() {
		self::create_tables();
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY_HOOK );
		}
		add_rewrite_endpoint( Lafu_Gift_Cards_Account::ENDPOINT, EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	/**
	 * Deactivation keeps every card and ledger row. Only the job stops.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::DAILY_HOOK );
		flush_rewrite_rules();
	}

	/**
	 * Run the table update after a plugin update without reactivation.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'lafu_gift_cards_db_version' ) !== self::DB_VERSION ) {
			self::create_tables();
		}
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY_HOOK );
		}
		if ( '3' !== get_option( 'lafu_gift_cards_designs_version' ) ) {
			add_action( 'init', array( __CLASS__, 'upgrade_designs' ), 20 );
		}
	}

	/**
	 * 1.4.0: a product shows one design, chosen by the shop. Products that
	 * kept a list of designs get the first one that is still offered.
	 */
	public static function upgrade_designs() {
		$ids     = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_lafu_gc_designs', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time upgrade.
			)
		);
		$offered = Lafu_Gift_Cards_Designs::offered();
		foreach ( $ids as $id ) {
			$list   = (array) get_post_meta( $id, '_lafu_gc_designs', true );
			$keep   = array_values( array_intersect( $list, array_keys( $offered ) ) );
			$design = $keep ? $keep[0] : (string) reset( $list );
			if ( '' === (string) get_post_meta( $id, '_lafu_gc_design', true ) ) {
				update_post_meta( $id, '_lafu_gc_design', Lafu_Gift_Cards_Designs::resolve( sanitize_key( $design ) ) );
			}
			delete_post_meta( $id, '_lafu_gc_designs' );
		}
		update_option( 'lafu_gift_cards_designs_version', '3', false );
	}

	/**
	 * Card table name.
	 *
	 * @return string
	 */
	public static function cards_table() {
		global $wpdb;
		return $wpdb->prefix . 'lafu_gc_cards';
	}

	/**
	 * Ledger table name.
	 *
	 * @return string
	 */
	public static function ledger_table() {
		global $wpdb;
		return $wpdb->prefix . 'lafu_gc_ledger';
	}

	/**
	 * Create or update both tables with dbDelta. Money is DECIMAL so no
	 * cent is lost to float rounding.
	 */
	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$cards   = self::cards_table();
		$ledger  = self::ledger_table();

		dbDelta(
			"CREATE TABLE {$cards} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				code varchar(64) NOT NULL,
				lookup varchar(64) NOT NULL,
				status varchar(16) NOT NULL DEFAULT 'active',
				currency char(3) NOT NULL,
				initial_amount decimal(19,4) NOT NULL DEFAULT 0,
				balance decimal(19,4) NOT NULL DEFAULT 0,
				design varchar(40) NOT NULL DEFAULT '',
				recipient_name varchar(190) NOT NULL DEFAULT '',
				recipient_email varchar(190) NOT NULL DEFAULT '',
				sender_name varchar(190) NOT NULL DEFAULT '',
				message text NULL,
				physical tinyint(1) NOT NULL DEFAULT 0,
				deliver_at datetime NULL,
				delivered_at datetime NULL,
				expires_at datetime NULL,
				buyer_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				order_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
				meta longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY lookup (lookup),
				KEY status (status),
				KEY order_id (order_id),
				KEY buyer_id (buyer_id),
				KEY user_id (user_id),
				KEY recipient_email (recipient_email(100)),
				KEY expires_at (expires_at)
			) {$charset};
			CREATE TABLE {$ledger} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				card_id bigint(20) unsigned NOT NULL,
				type varchar(16) NOT NULL,
				amount decimal(19,4) NOT NULL,
				balance_after decimal(19,4) NOT NULL,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				note varchar(255) NOT NULL DEFAULT '',
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY card_id (card_id),
				KEY order_id (order_id)
			) {$charset};"
		);
		update_option( 'lafu_gift_cards_db_version', self::DB_VERSION, false );
	}
}
