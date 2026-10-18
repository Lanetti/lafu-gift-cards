<?php
/**
 * Cards and the ledger. The only class that writes SQL.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom tables with row locks for money; this class is the plugin's only SQL.

/**
 * Every balance change is one ledger row written in the same transaction
 * as the balance, under a row lock, so money cannot be spent twice or lost.
 */
class Lafu_Gift_Cards_Store {

	const STATUS_ACTIVE   = 'active';
	const STATUS_VOID     = 'void';
	const STATUS_EXPIRED  = 'expired';
	const STATUS_INACTIVE = 'inactive';

	/**
	 * Ledger types that count as spending on an order and its return.
	 */
	const ORDER_TYPES = array( 'redeem', 'return' );

	/**
	 * Ledger types that load new money on a card (top-ups, shares of a
	 * group gift, store credit). They raise the card's total value too.
	 */
	const LOAD_TYPES = array( 'topup', 'group', 'credit' );

	/**
	 * Create a card and its opening ledger row.
	 *
	 * @param array  $data Card fields: amount, currency and optional code, design,
	 *                     recipient_name, recipient_email, sender_name, message,
	 *                     physical, deliver_at, expires_at, buyer_id, user_id,
	 *                     order_id, order_item_id, meta (array). With initial_amount
	 *                     the card can open with a lower balance (imported, partly
	 *                     used cards).
	 * @param string $type Opening ledger type: issue, import, or batch (a printed
	 *                     card that is not activated yet: status inactive, no money).
	 * @param string $note Ledger note.
	 * @return int|WP_Error Card ID.
	 */
	public static function create( array $data, $type = 'issue', $note = '' ) {
		global $wpdb;

		$amount = self::money( isset( $data['amount'] ) ? $data['amount'] : 0 );
		if ( 'batch' === $type ) {
			$amount = 0.0;
		} elseif ( $amount < 0 || ( 'issue' === $type && $amount <= 0 ) ) {
			return new WP_Error( 'lafu_gc_amount', __( 'The amount must be more than zero.', 'lafu-gift-cards' ) );
		}
		$initial = isset( $data['initial_amount'] ) ? self::money( $data['initial_amount'] ) : $amount;

		$code = isset( $data['code'] ) ? trim( (string) $data['code'] ) : '';
		if ( '' !== $code && strlen( Lafu_Gift_Cards_Codes::normalize( $code ) ) < 6 ) {
			return new WP_Error( 'lafu_gc_code', __( 'A gift card code needs at least 6 letters or digits.', 'lafu-gift-cards' ) );
		}

		$now = current_time( 'mysql', true );
		$row = array(
			'status'          => 'batch' === $type ? self::STATUS_INACTIVE : self::STATUS_ACTIVE,
			'currency'        => strtoupper( substr( isset( $data['currency'] ) ? (string) $data['currency'] : get_woocommerce_currency(), 0, 3 ) ),
			'initial_amount'  => max( $initial, $amount ),
			'balance'         => $amount,
			'design'          => isset( $data['design'] ) ? sanitize_key( $data['design'] ) : '',
			'recipient_name'  => isset( $data['recipient_name'] ) ? (string) $data['recipient_name'] : '',
			'recipient_email' => isset( $data['recipient_email'] ) ? (string) $data['recipient_email'] : '',
			'sender_name'     => isset( $data['sender_name'] ) ? (string) $data['sender_name'] : '',
			'message'         => isset( $data['message'] ) ? (string) $data['message'] : '',
			'physical'        => empty( $data['physical'] ) ? 0 : 1,
			'deliver_at'      => empty( $data['deliver_at'] ) ? null : $data['deliver_at'],
			'expires_at'      => empty( $data['expires_at'] ) ? null : $data['expires_at'],
			'buyer_id'        => isset( $data['buyer_id'] ) ? absint( $data['buyer_id'] ) : 0,
			'user_id'         => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0,
			'order_id'        => isset( $data['order_id'] ) ? absint( $data['order_id'] ) : 0,
			'order_item_id'   => isset( $data['order_item_id'] ) ? absint( $data['order_item_id'] ) : 0,
			'meta'            => ! empty( $data['meta'] ) && is_array( $data['meta'] ) ? wp_json_encode( $data['meta'] ) : null,
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		if ( '' !== $code && self::find_by_code( $code ) ) {
			return new WP_Error( 'lafu_gc_duplicate', __( 'A gift card with this code already exists.', 'lafu-gift-cards' ) );
		}

		$wpdb->query( 'START TRANSACTION' );
		$card_id = 0;
		for ( $attempt = 0; $attempt < 5 && ! $card_id; $attempt++ ) {
			$row['code']   = '' !== $code ? $code : Lafu_Gift_Cards_Codes::generate();
			$row['lookup'] = Lafu_Gift_Cards_Codes::normalize( $row['code'] );
			if ( false !== $wpdb->insert( Lafu_Gift_Cards_Install::cards_table(), $row ) ) {
				$card_id = (int) $wpdb->insert_id;
			} elseif ( '' !== $code ) {
				break;
			}
		}
		if ( ! $card_id || ! self::write_ledger( $card_id, $type, $amount, $amount, (int) $row['order_id'], $note ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'lafu_gc_db', __( 'The gift card could not be saved.', 'lafu-gift-cards' ) );
		}
		$wpdb->query( 'COMMIT' );

		/**
		 * A card was created.
		 *
		 * @param int    $card_id Card ID.
		 * @param string $type    Opening ledger type: issue, import or batch.
		 */
		do_action( 'lafu_gift_cards_created', $card_id, $type );
		return $card_id;
	}

	/**
	 * One card by ID.
	 *
	 * @param int $card_id Card ID.
	 * @return object|null
	 */
	public static function get( $card_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Lafu_Gift_Cards_Install::cards_table(), $card_id ) );
	}

	/**
	 * One card by code, in any format the customer typed it.
	 *
	 * @param string $code Code.
	 * @return object|null
	 */
	public static function find_by_code( $code ) {
		global $wpdb;
		$lookup = Lafu_Gift_Cards_Codes::normalize( $code );
		if ( '' === $lookup ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE lookup = %s', Lafu_Gift_Cards_Install::cards_table(), $lookup ) );
	}

	/**
	 * Whether a card can pay right now.
	 *
	 * @param object|null $card     Card row.
	 * @param string      $currency Currency of the cart or order.
	 * @return true|WP_Error
	 */
	public static function usable( $card, $currency ) {
		if ( ! $card ) {
			return new WP_Error( 'lafu_gc_unknown', __( 'This gift card code is not valid.', 'lafu-gift-cards' ) );
		}
		if ( self::STATUS_VOID === $card->status ) {
			return new WP_Error( 'lafu_gc_void', __( 'This gift card has been cancelled.', 'lafu-gift-cards' ) );
		}
		if ( self::STATUS_INACTIVE === $card->status ) {
			return new WP_Error( 'lafu_gc_inactive', __( 'This gift card has not been activated yet. Ask the shop to activate it.', 'lafu-gift-cards' ) );
		}
		if ( self::STATUS_EXPIRED === $card->status || self::is_past( $card->expires_at ) ) {
			return new WP_Error( 'lafu_gc_expired', __( 'This gift card has expired.', 'lafu-gift-cards' ) );
		}
		if ( strtoupper( (string) $currency ) !== $card->currency ) {
			return new WP_Error( 'lafu_gc_currency', __( 'This gift card is in a different currency.', 'lafu-gift-cards' ) );
		}
		if ( (float) $card->balance <= 0 ) {
			return new WP_Error( 'lafu_gc_empty', __( 'This gift card has no balance left.', 'lafu-gift-cards' ) );
		}
		return true;
	}

	/**
	 * Take money from a card. Fails without change if the card cannot
	 * cover the amount at this moment.
	 *
	 * @param int    $card_id  Card ID.
	 * @param float  $amount   Positive amount.
	 * @param int    $order_id Order the money is spent on, or 0.
	 * @param string $type     Ledger type: redeem, adjust, void or expire.
	 * @param string $note     Ledger note.
	 * @return true|WP_Error
	 */
	public static function debit( $card_id, $amount, $order_id = 0, $type = 'redeem', $note = '' ) {
		return self::change( $card_id, -self::money( $amount ), $order_id, $type, $note );
	}

	/**
	 * Put money back on a card.
	 *
	 * @param int    $card_id  Card ID.
	 * @param float  $amount   Positive amount.
	 * @param int    $order_id Related order, or 0.
	 * @param string $type     Ledger type: return or adjust.
	 * @param string $note     Ledger note.
	 * @return true|WP_Error
	 */
	public static function credit( $card_id, $amount, $order_id = 0, $type = 'return', $note = '' ) {
		return self::change( $card_id, self::money( $amount ), $order_id, $type, $note );
	}

	/**
	 * Cancel a card. The remaining balance is written off in the ledger.
	 *
	 * @param int    $card_id Card ID.
	 * @param string $note    Reason.
	 * @return true|WP_Error
	 */
	public static function void( $card_id, $note = '' ) {
		return self::close( $card_id, self::STATUS_VOID, 'void', $note );
	}

	/**
	 * Expire cards whose date has passed, in batches.
	 *
	 * @param int $limit Cards per run.
	 * @return int Cards expired.
	 */
	public static function expire_due( $limit = 200 ) {
		global $wpdb;
		$ids  = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE status = %s AND expires_at IS NOT NULL AND expires_at < %s LIMIT %d',
				Lafu_Gift_Cards_Install::cards_table(),
				self::STATUS_ACTIVE,
				current_time( 'mysql', true ),
				$limit
			)
		);
		$done = 0;
		foreach ( $ids as $id ) {
			if ( true === self::close( (int) $id, self::STATUS_EXPIRED, 'expire', __( 'Expired', 'lafu-gift-cards' ) ) ) {
				++$done;
			}
		}
		return $done;
	}

	/**
	 * Update descriptive fields. Never the balance: that only moves
	 * through the ledger.
	 *
	 * @param int   $card_id Card ID.
	 * @param array $fields  Allowed: recipient_name, recipient_email, sender_name,
	 *                       message, design, expires_at, deliver_at, delivered_at,
	 *                       user_id, status (expired back to active only).
	 * @return bool
	 */
	public static function update( $card_id, array $fields ) {
		global $wpdb;
		$allowed = array( 'recipient_name', 'recipient_email', 'sender_name', 'message', 'design', 'expires_at', 'deliver_at', 'delivered_at', 'user_id', 'status' );
		$fields  = array_intersect_key( $fields, array_flip( $allowed ) );
		if ( isset( $fields['status'] ) && self::STATUS_ACTIVE !== $fields['status'] ) {
			unset( $fields['status'] );
		}
		if ( ! $fields ) {
			return false;
		}
		$fields['updated_at'] = current_time( 'mysql', true );
		return false !== $wpdb->update( Lafu_Gift_Cards_Install::cards_table(), $fields, array( 'id' => absint( $card_id ) ) );
	}

	/**
	 * Ledger rows of one card, newest first.
	 *
	 * @param int $card_id Card ID.
	 * @return array
	 */
	public static function ledger( $card_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE card_id = %d ORDER BY id DESC', Lafu_Gift_Cards_Install::ledger_table(), $card_id ) );
	}

	/**
	 * Money still taken from each card for an order: redeemed minus
	 * returned. Card ID => positive amount.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function order_spend( $order_id ) {
		global $wpdb;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT card_id, SUM(amount) AS net FROM %i WHERE order_id = %d AND type IN (%s, %s) GROUP BY card_id',
				Lafu_Gift_Cards_Install::ledger_table(),
				$order_id,
				self::ORDER_TYPES[0],
				self::ORDER_TYPES[1]
			)
		);
		$spend = array();
		foreach ( $rows as $row ) {
			$net = self::money( -1 * (float) $row->net );
			if ( $net > 0 ) {
				$spend[ (int) $row->card_id ] = $net;
			}
		}
		return $spend;
	}

	/**
	 * Orders that still have gift card money held on them.
	 *
	 * @param int $limit Orders per run.
	 * @return int[]
	 */
	public static function order_ids_with_spend( $limit = 100 ) {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT order_id FROM %i WHERE order_id > 0 AND type IN (%s, %s) GROUP BY order_id HAVING SUM(amount) < 0 ORDER BY order_id DESC LIMIT %d',
				Lafu_Gift_Cards_Install::ledger_table(),
				self::ORDER_TYPES[0],
				self::ORDER_TYPES[1],
				$limit
			)
		);
		return array_map( 'absint', $ids );
	}

	/**
	 * Cards issued from one order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function for_order( $order_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d ORDER BY id', Lafu_Gift_Cards_Install::cards_table(), $order_id ) );
	}

	/**
	 * Cards a customer bought or saved to their account.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d OR buyer_id = %d ORDER BY id DESC LIMIT 200',
				Lafu_Gift_Cards_Install::cards_table(),
				$user_id,
				$user_id
			)
		);
	}

	/**
	 * Cards linked to a person: sent to their email address, saved to their
	 * account or bought by them. For the personal data export and eraser.
	 *
	 * @param string $email    Email address.
	 * @param int    $page     Page, from 1.
	 * @param int    $per_page Cards per page.
	 * @return array
	 */
	public static function for_person( $email, $page = 1, $per_page = 50 ) {
		global $wpdb;
		$email = strtolower( trim( (string) $email ) );
		$user  = get_user_by( 'email', $email );
		$uid   = $user ? (int) $user->ID : -1;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE recipient_email = %s OR user_id = %d OR buyer_id = %d ORDER BY id LIMIT %d OFFSET %d',
				Lafu_Gift_Cards_Install::cards_table(),
				$email,
				$uid,
				$uid,
				$per_page,
				max( 0, ( (int) $page - 1 ) * $per_page )
			)
		);
	}

	/**
	 * Unlink a buyer account from the cards it bought (personal data eraser).
	 *
	 * @param string $email Buyer email address.
	 */
	public static function forget_buyer( $email ) {
		global $wpdb;
		$user = get_user_by( 'email', strtolower( trim( (string) $email ) ) );
		if ( $user ) {
			$wpdb->update( Lafu_Gift_Cards_Install::cards_table(), array( 'buyer_id' => 0 ), array( 'buyer_id' => (int) $user->ID ) );
		}
	}

	/**
	 * Cards scheduled for email delivery that are due and not sent.
	 *
	 * @param int $limit Cards per run.
	 * @return array
	 */
	public static function due_for_delivery( $limit = 100 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s AND physical = 0 AND delivered_at IS NULL AND deliver_at IS NOT NULL AND deliver_at <= %s LIMIT %d',
				Lafu_Gift_Cards_Install::cards_table(),
				self::STATUS_ACTIVE,
				current_time( 'mysql', true ),
				$limit
			)
		);
	}

	/**
	 * Admin list query.
	 *
	 * @param array $args search, status, per_page, page.
	 * @return array { rows: array, total: int }
	 */
	public static function query( array $args ) {
		global $wpdb;
		$status = isset( $args['status'] ) ? (string) $args['status'] : '';
		$search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		$lookup = Lafu_Gift_Cards_Codes::normalize( $search );
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$code   = '%' . $wpdb->esc_like( '' !== $lookup ? $lookup : $search ) . '%';
		$order  = '' !== $search && ctype_digit( $search ) ? (int) $search : -1;

		$per_page = max( 1, absint( isset( $args['per_page'] ) ? $args['per_page'] : 20 ) );
		$page     = max( 1, absint( isset( $args['page'] ) ? $args['page'] : 1 ) );
		$table    = Lafu_Gift_Cards_Install::cards_table();

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE (%s = '' OR status = %s) AND (%s = '' OR lookup LIKE %s OR recipient_email LIKE %s OR recipient_name LIKE %s OR order_id = %d)",
				$table,
				$status,
				$status,
				$search,
				$code,
				$like,
				$like,
				$order
			)
		);
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE (%s = '' OR status = %s) AND (%s = '' OR lookup LIKE %s OR recipient_email LIKE %s OR recipient_name LIKE %s OR order_id = %d) ORDER BY id DESC LIMIT %d OFFSET %d",
				$table,
				$status,
				$status,
				$search,
				$code,
				$like,
				$like,
				$order,
				$per_page,
				( $page - 1 ) * $per_page
			)
		);

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Every card in ID order, a page at a time, for the CSV export.
	 *
	 * @param int $after_id Last ID of the previous page.
	 * @param int $limit    Page size.
	 * @return array
	 */
	public static function export_page( $after_id, $limit = 500 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE id > %d ORDER BY id LIMIT %d', Lafu_Gift_Cards_Install::cards_table(), $after_id, $limit ) );
	}

	/**
	 * Total open balance per currency: what the shop owes on gift cards.
	 *
	 * @return array Currency => amount.
	 */
	public static function outstanding() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT currency, SUM(balance) AS total FROM %i WHERE status = %s GROUP BY currency', Lafu_Gift_Cards_Install::cards_table(), self::STATUS_ACTIVE ) );
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ $row->currency ] = self::money( $row->total );
		}
		return $out;
	}

	/**
	 * Activate a printed card that was made in a batch: it gets its amount
	 * now, for example when it is sold at the till.
	 *
	 * @param int    $card_id  Card ID.
	 * @param float  $amount   Amount loaded on the card.
	 * @param int    $order_id Sale order, or 0.
	 * @param string $note     Ledger note.
	 * @return true|WP_Error
	 */
	public static function activate( $card_id, $amount, $order_id = 0, $note = '' ) {
		global $wpdb;
		$amount = self::money( $amount );
		if ( $amount <= 0 ) {
			return new WP_Error( 'lafu_gc_amount', __( 'The amount must be more than zero.', 'lafu-gift-cards' ) );
		}
		$wpdb->query( 'START TRANSACTION' );
		$card = self::lock( $card_id );
		if ( ! $card || self::STATUS_INACTIVE !== $card->status ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'lafu_gc_state', __( 'This gift card cannot be changed.', 'lafu-gift-cards' ) );
		}
		$ok = false !== $wpdb->update(
			Lafu_Gift_Cards_Install::cards_table(),
			array(
				'status'         => self::STATUS_ACTIVE,
				'initial_amount' => $amount,
				'balance'        => $amount,
				'order_id'       => absint( $order_id ) ? absint( $order_id ) : (int) $card->order_id,
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $card->id )
		) && self::write_ledger( (int) $card->id, 'activate', $amount, $amount, $order_id, $note );
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'lafu_gc_db', __( 'The gift card could not be saved.', 'lafu-gift-cards' ) );
		}
		$wpdb->query( 'COMMIT' );
		do_action( 'lafu_gift_cards_balance_changed', (int) $card->id, 'activate', $amount, (int) $order_id );
		return true;
	}

	/**
	 * Extra data stored on a card by extensions (JSON object).
	 *
	 * @param object $card Card row.
	 * @return array
	 */
	public static function meta( $card ) {
		$meta = isset( $card->meta ) && is_string( $card->meta ) ? json_decode( $card->meta, true ) : null;
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Merge extra data into a card's meta.
	 *
	 * @param int   $card_id Card ID.
	 * @param array $values  Keys to set; null removes a key.
	 * @return bool
	 */
	public static function set_meta( $card_id, array $values ) {
		global $wpdb;
		$card = self::get( $card_id );
		if ( ! $card ) {
			return false;
		}
		$meta = self::meta( $card );
		foreach ( $values as $key => $value ) {
			if ( null === $value ) {
				unset( $meta[ $key ] );
			} else {
				$meta[ $key ] = $value;
			}
		}
		return false !== $wpdb->update(
			Lafu_Gift_Cards_Install::cards_table(),
			array(
				'meta'       => $meta ? wp_json_encode( $meta ) : null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $card_id )
		);
	}

	/**
	 * Key numbers for the admin dashboard, in the shop currency: open
	 * balance, active cards, cards expiring within 30 days, and what was
	 * sold and used since the start of this month (shop time zone).
	 *
	 * @return array open, active, expiring, sold_month, used_month.
	 */
	public static function stats() {
		global $wpdb;
		$cards    = Lafu_Gift_Cards_Install::cards_table();
		$ledger   = Lafu_Gift_Cards_Install::ledger_table();
		$currency = get_woocommerce_currency();
		$now      = current_time( 'mysql', true );
		$month    = gmdate( 'Y-m-d H:i:s', ( new DateTime( 'first day of this month 00:00:00', wp_timezone() ) )->getTimestamp() );

		$open = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COUNT(*) AS active, COALESCE(SUM(balance), 0) AS open, COALESCE(SUM(expires_at IS NOT NULL AND expires_at < DATE_ADD(%s, INTERVAL 30 DAY)), 0) AS expiring FROM %i WHERE status = %s AND currency = %s',
				$now,
				$cards,
				self::STATUS_ACTIVE,
				$currency
			)
		);
		$flow = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CASE WHEN l.type = 'issue' THEN l.amount ELSE 0 END), 0) AS sold, COALESCE(-SUM(CASE WHEN l.type IN ('redeem', 'return') THEN l.amount ELSE 0 END), 0) AS used FROM %i l JOIN %i c ON c.id = l.card_id WHERE l.created_at >= %s AND c.currency = %s",
				$ledger,
				$cards,
				$month,
				$currency
			)
		);
		return array(
			'open'       => self::money( $open ? $open->open : 0 ),
			'active'     => (int) ( $open ? $open->active : 0 ),
			'expiring'   => (int) ( $open ? $open->expiring : 0 ),
			'sold_month' => self::money( $flow ? $flow->sold : 0 ),
			'used_month' => self::money( $flow ? $flow->used : 0 ),
		);
	}

	/**
	 * Take a named database lock so two requests never issue the same
	 * order's cards twice. Released with release_lock().
	 *
	 * @param string $name    Lock name.
	 * @param int    $timeout Seconds to wait.
	 * @return bool
	 */
	public static function acquire_lock( $name, $timeout = 10 ) {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $wpdb->prefix . $name, $timeout ) );
	}

	/**
	 * Release a lock from acquire_lock().
	 *
	 * @param string $name Lock name.
	 */
	public static function release_lock( $name ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $wpdb->prefix . $name ) );
	}

	/**
	 * Amount typed with a dot or a comma as the decimal sign.
	 *
	 * @param string $value Raw value.
	 * @return float|null
	 */
	public static function parse_amount( $value ) {
		$value = str_replace( array( ' ', "\xC2\xA0", '€' ), '', trim( (string) $value ) );
		if ( false === strpos( $value, '.' ) ) {
			$value = str_replace( ',', '.', $value );
		}
		if ( ! preg_match( '/^[-+]?\d+(\.\d+)?$/', $value ) ) {
			return null;
		}
		return self::money( $value );
	}

	/**
	 * Round to the shop's price decimals.
	 *
	 * @param mixed $value Amount.
	 * @return float
	 */
	public static function money( $value ) {
		return round( (float) $value, wc_get_price_decimals() );
	}

	/**
	 * Whether a UTC datetime string is in the past.
	 *
	 * @param string|null $datetime UTC MySQL datetime.
	 * @return bool
	 */
	public static function is_past( $datetime ) {
		return ! empty( $datetime ) && strtotime( $datetime . ' UTC' ) < time();
	}

	/**
	 * Move a card balance by a signed amount under a row lock.
	 *
	 * @param int    $card_id  Card ID.
	 * @param float  $delta    Signed amount.
	 * @param int    $order_id Related order.
	 * @param string $type     Ledger type.
	 * @param string $note     Ledger note.
	 * @return true|WP_Error
	 */
	private static function change( $card_id, $delta, $order_id, $type, $note ) {
		global $wpdb;
		if ( 0.0 === (float) $delta ) {
			return new WP_Error( 'lafu_gc_amount', __( 'The amount must be more than zero.', 'lafu-gift-cards' ) );
		}

		$wpdb->query( 'START TRANSACTION' );
		$card = self::lock( $card_id );
		if ( ! $card ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'lafu_gc_unknown', __( 'This gift card code is not valid.', 'lafu-gift-cards' ) );
		}
		if ( self::STATUS_VOID === $card->status ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'lafu_gc_void', __( 'This gift card has been cancelled.', 'lafu-gift-cards' ) );
		}
		if ( self::STATUS_INACTIVE === $card->status ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'lafu_gc_inactive', __( 'This gift card has not been activated yet. Ask the shop to activate it.', 'lafu-gift-cards' ) );
		}
		$balance = self::money( (float) $card->balance + $delta );
		if ( $balance < 0 ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'lafu_gc_balance', __( 'The gift card balance is too low.', 'lafu-gift-cards' ) );
		}
		if ( 'redeem' === $type && self::STATUS_ACTIVE !== $card->status ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'lafu_gc_expired', __( 'This gift card has expired.', 'lafu-gift-cards' ) );
		}

		$fields = array(
			'balance'    => $balance,
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( $delta > 0 && self::STATUS_EXPIRED === $card->status && 'adjust' === $type ) {
			$fields['status'] = self::STATUS_ACTIVE;
		}
		if ( $delta > 0 && in_array( $type, self::LOAD_TYPES, true ) ) {
			$fields['initial_amount'] = self::money( (float) $card->initial_amount + $delta );
		}
		$ok = false !== $wpdb->update( Lafu_Gift_Cards_Install::cards_table(), $fields, array( 'id' => (int) $card->id ) )
			&& self::write_ledger( (int) $card->id, $type, $delta, $balance, $order_id, $note );
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'lafu_gc_db', __( 'The gift card could not be saved.', 'lafu-gift-cards' ) );
		}
		$wpdb->query( 'COMMIT' );

		/**
		 * A card balance changed. Accounting integrations listen here.
		 *
		 * @param int    $card_id  Card ID.
		 * @param string $type     redeem, return, adjust.
		 * @param float  $delta    Signed amount.
		 * @param int    $order_id Related order or 0.
		 */
		do_action( 'lafu_gift_cards_balance_changed', (int) $card->id, $type, $delta, (int) $order_id );
		return true;
	}

	/**
	 * Set a final status and write off the balance.
	 *
	 * @param int    $card_id Card ID.
	 * @param string $status  void or expired.
	 * @param string $type    Ledger type: void or expire.
	 * @param string $note    Ledger note.
	 * @return true|WP_Error
	 */
	private static function close( $card_id, $status, $type, $note ) {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$card = self::lock( $card_id );
		if ( ! $card || self::STATUS_VOID === $card->status || $status === $card->status ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'lafu_gc_state', __( 'This gift card cannot be changed.', 'lafu-gift-cards' ) );
		}
		$written = self::money( $card->balance );
		$ok      = false !== $wpdb->update(
			Lafu_Gift_Cards_Install::cards_table(),
			array(
				'status'     => $status,
				'balance'    => 0,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $card->id )
		) && self::write_ledger( (int) $card->id, $type, -$written, 0, 0, $note );
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'lafu_gc_db', __( 'The gift card could not be saved.', 'lafu-gift-cards' ) );
		}
		$wpdb->query( 'COMMIT' );
		do_action( 'lafu_gift_cards_balance_changed', (int) $card->id, $type, -$written, 0 );
		return true;
	}

	/**
	 * Read a card and lock its row until the transaction ends.
	 *
	 * @param int $card_id Card ID.
	 * @return object|null
	 */
	private static function lock( $card_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d FOR UPDATE', Lafu_Gift_Cards_Install::cards_table(), $card_id ) );
	}

	/**
	 * Append one ledger row.
	 *
	 * @param int    $card_id  Card ID.
	 * @param string $type     Ledger type.
	 * @param float  $amount   Signed amount.
	 * @param float  $balance  Balance after the change.
	 * @param int    $order_id Related order.
	 * @param string $note     Note.
	 * @return bool
	 */
	private static function write_ledger( $card_id, $type, $amount, $balance, $order_id, $note ) {
		global $wpdb;
		return false !== $wpdb->insert(
			Lafu_Gift_Cards_Install::ledger_table(),
			array(
				'card_id'       => $card_id,
				'type'          => $type,
				'amount'        => $amount,
				'balance_after' => $balance,
				'order_id'      => absint( $order_id ),
				'note'          => mb_substr( (string) $note, 0, 255 ),
				'actor_id'      => get_current_user_id(),
				'created_at'    => current_time( 'mysql', true ),
			)
		);
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery
