<?php
/**
 * Paying with gift cards in the cart and checkout.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * A gift card is a way to pay, not a discount: it lowers the amount left
 * to pay and leaves product prices and VAT as they are. The cards are
 * charged when the order is placed. The money goes back if payment fails,
 * the order is cancelled or refunded, or a pending payment is left unfinished.
 */
class Lafu_Gift_Cards_Redeem {

	const SESSION_CARDS = 'lafu_gc_applied';
	const SESSION_PLAN  = 'lafu_gc_plan';
	const ORDER_META    = '_lafu_gc_redemptions';
	const NONCE         = 'lafu-gc-redeem';

	/**
	 * Hook cart, checkout and order events.
	 */
	public static function register() {
		add_filter( 'woocommerce_calculated_total', array( __CLASS__, 'calculated_total' ), 999, 2 );
		add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'forget' ) );

		add_action( 'woocommerce_after_cart_table', array( __CLASS__, 'form' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'form' ), 11 );
		add_action( 'woocommerce_cart_totals_before_order_total', array( __CLASS__, 'cart_rows' ) );
		add_action( 'woocommerce_review_order_before_order_total', array( __CLASS__, 'cart_rows' ) );
		add_action( 'wc_ajax_lafu_gc_apply', array( __CLASS__, 'ajax_apply' ) );
		add_action( 'wc_ajax_lafu_gc_remove', array( __CLASS__, 'ajax_remove' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'link_remove' ), 30 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_plan' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'attach_plan' ) );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'charge_classic' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'charge' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status' ), 10, 3 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'refund_share' ), 10, 2 );

		add_action( 'woocommerce_order_after_calculate_totals', array( __CLASS__, 'keep_order_total' ), 10, 2 );
		add_filter( 'woocommerce_get_order_item_totals', array( __CLASS__, 'order_rows' ), 10, 2 );
		add_action( 'woocommerce_admin_order_totals_after_tax', array( __CLASS__, 'admin_rows' ) );
		add_action( 'admin_post_lafu_gc_order_return', array( __CLASS__, 'admin_return' ) );
	}

	/**
	 * Add a card to the cart by code.
	 *
	 * @param string $code Code as typed.
	 * @return true|WP_Error
	 */
	public static function apply_code( $code ) {
		if ( Lafu_Gift_Cards_Codes::is_limited() ) {
			return new WP_Error( 'lafu_gc_limited', __( 'Too many attempts. Please wait a few minutes and try again.', 'lafu-gift-cards' ) );
		}
		$card = Lafu_Gift_Cards_Store::find_by_code( $code );
		if ( ! $card ) {
			Lafu_Gift_Cards_Codes::record_failure();
		}
		return self::apply_card( $card );
	}

	/**
	 * Add one of the customer's own saved cards.
	 *
	 * @param int $card_id Card ID.
	 * @return true|WP_Error
	 */
	public static function apply_saved( $card_id ) {
		$card = Lafu_Gift_Cards_Store::get( absint( $card_id ) );
		if ( ! Lafu_Gift_Cards_Account::owns( $card, get_current_user_id() ) ) {
			return new WP_Error( 'lafu_gc_unknown', __( 'This gift card code is not valid.', 'lafu-gift-cards' ) );
		}
		return self::apply_card( $card );
	}

	/**
	 * Take a card off the cart.
	 *
	 * @param int $card_id Card ID.
	 */
	public static function remove( $card_id ) {
		$ids = array_values( array_diff( self::applied_ids(), array( absint( $card_id ) ) ) );
		self::session_set( self::SESSION_CARDS, $ids );
		self::recalculate();
	}

	/**
	 * Card IDs applied to the current cart.
	 *
	 * @return int[]
	 */
	public static function applied_ids() {
		$ids = self::session_get( self::SESSION_CARDS );
		return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	}

	/**
	 * Amount taken from each applied card in the current cart:
	 * card ID => amount. Set on every cart calculation.
	 *
	 * @return array
	 */
	public static function plan() {
		$plan = self::session_get( self::SESSION_PLAN );
		return is_array( $plan ) ? $plan : array();
	}

	/**
	 * Lower the cart total by what the applied cards cover.
	 *
	 * @param float   $total Cart total with tax and shipping.
	 * @param WC_Cart $cart  Cart.
	 * @return float
	 */
	public static function calculated_total( $total, $cart ) {
		$ids = self::applied_ids();
		if ( ! $ids ) {
			self::session_set( self::SESSION_PLAN, array() );
			return $total;
		}
		$blocked = self::gift_product_total( $cart );
		$left    = Lafu_Gift_Cards_Store::money( max( 0, $total - $blocked ) );
		$plan    = array();
		$keep    = array();
		foreach ( $ids as $id ) {
			$card   = Lafu_Gift_Cards_Store::get( $id );
			$usable = Lafu_Gift_Cards_Store::usable( $card, get_woocommerce_currency() );
			if ( is_wp_error( $usable ) ) {
				self::notice( $usable->get_error_message(), 'error' );
				continue;
			}
			$keep[] = $id;
			/**
			 * Most this card may pay in this cart, for example only the
			 * products it is limited to. Defaults to everything left to pay.
			 *
			 * @param float   $cap  Amount left to pay.
			 * @param object  $card Card row.
			 * @param WC_Cart $cart Cart.
			 */
			$cap = (float) apply_filters( 'lafu_gift_cards_card_cap', $left, $card, WC()->cart );
			$use = Lafu_Gift_Cards_Store::money( max( 0, min( (float) $card->balance, $left, $cap ) ) );
			if ( $use > 0 ) {
				$plan[ $id ] = $use;
				$left        = Lafu_Gift_Cards_Store::money( $left - $use );
			}
		}
		if ( $keep !== $ids ) {
			self::session_set( self::SESSION_CARDS, $keep );
		}
		self::session_set( self::SESSION_PLAN, $plan );
		if ( $ids && ! $plan && $blocked > 0 && $left <= 0 ) {
			self::session_set( self::SESSION_CARDS, array() );
			self::notice( __( 'A gift card cannot pay for a gift card.', 'lafu-gift-cards' ), 'error' );
		}
		return Lafu_Gift_Cards_Store::money( max( 0, $total - array_sum( $plan ) ) );
	}

	/**
	 * Amount of the cart that is gift card products, including their tax.
	 * A gift card does not pay for those lines.
	 *
	 * @param WC_Cart|null $cart Cart.
	 * @return float
	 */
	private static function gift_product_total( $cart ) {
		$sum = 0.0;
		if ( ! $cart ) {
			return $sum;
		}
		foreach ( $cart->get_cart() as $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( $product instanceof WC_Product && Lafu_Gift_Cards_Product::is_gift_card( $product ) ) {
				$sum += (float) $item['line_total'] + (float) $item['line_tax'];
			}
		}
		return Lafu_Gift_Cards_Store::money( $sum );
	}

	/**
	 * Clear applied cards when the cart is emptied (after a paid order).
	 */
	public static function forget() {
		self::session_set( self::SESSION_CARDS, array() );
		self::session_set( self::SESSION_PLAN, array() );
	}

	/**
	 * Gift card box on the classic cart and checkout pages.
	 */
	public static function form() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		wp_enqueue_style( 'lafu-gift-cards' );
		wp_enqueue_script( 'lafu-gift-cards' );
		$saved = self::saved_cards();
		?>
		<div class="lafu-gc-redeem" data-lafu-gc-redeem>
			<p class="lafu-gc-redeem-title"><?php esc_html_e( 'Have a gift card?', 'lafu-gift-cards' ); ?></p>
			<div class="lafu-gc-redeem-row">
				<label class="screen-reader-text" for="lafu_gc_code"><?php esc_html_e( 'Gift card code', 'lafu-gift-cards' ); ?></label>
				<input type="text" id="lafu_gc_code" class="input-text" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e( 'Gift card code', 'lafu-gift-cards' ); ?>" data-lafu-gc-code>
				<button type="button" class="button wp-element-button" data-lafu-gc-apply><?php esc_html_e( 'Use card', 'lafu-gift-cards' ); ?></button>
			</div>
			<?php if ( $saved ) : ?>
				<p class="lafu-gc-saved">
					<?php esc_html_e( 'Your gift cards:', 'lafu-gift-cards' ); ?>
					<?php foreach ( $saved as $card ) : ?>
						<button type="button" class="button lafu-gc-saved-card" data-lafu-gc-saved="<?php echo esc_attr( $card['id'] ); ?>"><?php echo esc_html( $card['label'] . ' · ' . $card['balance'] ); ?></button>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
			<p class="lafu-gc-redeem-message" role="status" aria-live="polite" data-lafu-gc-message></p>
		</div>
		<?php
	}

	/**
	 * Applied cards as rows above the order total in classic templates.
	 */
	public static function cart_rows() {
		foreach ( self::plan() as $card_id => $amount ) {
			$card = Lafu_Gift_Cards_Store::get( (int) $card_id );
			if ( ! $card ) {
				continue;
			}
			$remove = wp_nonce_url( add_query_arg( 'lafu_gc_remove', (int) $card_id, wc_get_cart_url() ), self::NONCE );
			$label  = lafu_gift_cards_card_title( $card );
			?>
			<tr class="lafu-gc-total">
				<th><?php echo esc_html( $label ); ?></th>
				<td data-title="<?php echo esc_attr( $label ); ?>">
					<?php echo wp_kses_post( wc_price( -1 * (float) $amount ) ); ?>
					<a href="<?php echo esc_url( $remove ); ?>" class="lafu-gc-remove" data-lafu-gc-remove="<?php echo esc_attr( (int) $card_id ); ?>"><?php esc_html_e( '[Remove]', 'lafu-gift-cards' ); ?></a>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Apply a code from the classic cart or checkout box.
	 */
	public static function ajax_apply() {
		check_ajax_referer( self::NONCE, 'nonce' );
		$result = isset( $_POST['card'] )
			? self::apply_saved( absint( wp_unslash( $_POST['card'] ) ) )
			: self::apply_code( isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		self::recalculate();
		wp_send_json_success( array( 'message' => __( 'Gift card added.', 'lafu-gift-cards' ) ) );
	}

	/**
	 * Remove a card from the classic cart or checkout.
	 */
	public static function ajax_remove() {
		check_ajax_referer( self::NONCE, 'nonce' );
		self::remove( isset( $_POST['card'] ) ? absint( wp_unslash( $_POST['card'] ) ) : 0 );
		wp_send_json_success( array( 'message' => __( 'Gift card removed.', 'lafu-gift-cards' ) ) );
	}

	/**
	 * Remove link that works without JavaScript.
	 */
	public static function link_remove() {
		if ( ! isset( $_GET['lafu_gc_remove'], $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE ) ) {
			return;
		}
		self::remove( absint( wp_unslash( $_GET['lafu_gc_remove'] ) ) );
		wp_safe_redirect( remove_query_arg( array( 'lafu_gc_remove', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Script settings for the classic cart and checkout box.
	 */
	public static function assets() {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}
		wp_enqueue_style( 'lafu-gift-cards' );
		wp_enqueue_script( 'lafu-gift-cards' );
		wp_localize_script(
			'lafu-gift-cards',
			'lafuGiftCards',
			array(
				'endpoint' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'nonce'    => wp_create_nonce( self::NONCE ),
				'empty'    => __( 'Enter a gift card code.', 'lafu-gift-cards' ),
				'failed'   => __( 'Something went wrong. Please try again.', 'lafu-gift-cards' ),
			)
		);
	}

	/**
	 * Save the card plan on the order while it is created.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function attach_plan( $order ) {
		$plan  = self::plan();
		$saved = array();
		foreach ( $plan as $card_id => $amount ) {
			$card = Lafu_Gift_Cards_Store::get( (int) $card_id );
			if ( $card ) {
				$saved[] = array(
					'card_id' => (int) $card_id,
					'code'    => Lafu_Gift_Cards_Codes::mask( $card->code ),
					'amount'  => wc_format_decimal( $amount ),
				);
			}
		}
		if ( $saved ) {
			$order->update_meta_data( self::ORDER_META, $saved );
		} else {
			$order->delete_meta_data( self::ORDER_META );
		}
		Lafu_Gift_Cards_I18n::remember_order( $order );
		// The block checkout sets the order total before this runs, so take
		// it from the cart again: the cart total already has the cards off.
		if ( WC()->cart ) {
			$order->set_total( WC()->cart->get_total( 'edit' ) );
		}
	}

	/**
	 * Classic checkout: charge the cards once the order exists.
	 *
	 * @param int      $order_id Order ID.
	 * @param array    $posted   Posted data.
	 * @param WC_Order $order    Order.
	 * @throws Exception When a card can no longer pay.
	 */
	public static function charge_classic( $order_id, $posted, $order ) {
		unset( $order_id, $posted );
		self::charge( $order );
	}

	/**
	 * Charge the cards of an order. A retried checkout of the same order
	 * first refills what the previous attempt took, so nothing is charged
	 * twice.
	 *
	 * @param WC_Order $order Order.
	 * @throws Exception When a card can no longer pay.
	 */
	public static function charge( $order ) {
		$order_id = $order->get_id();
		self::refill( $order_id, __( 'Checkout retried', 'lafu-gift-cards' ) );

		$plan = lafu_gift_cards_meta_array( $order, self::ORDER_META );
		$done = array();
		foreach ( $plan as $row ) {
			/* translators: %s: order number */
			$note   = sprintf( __( 'Paid order %s', 'lafu-gift-cards' ), $order->get_order_number() );
			$result = Lafu_Gift_Cards_Store::debit( (int) $row['card_id'], (float) $row['amount'], $order_id, 'redeem', $note );
			if ( is_wp_error( $result ) ) {
				foreach ( $done as $card_id => $amount ) {
					Lafu_Gift_Cards_Store::credit( $card_id, $amount, $order_id, 'return', __( 'Checkout stopped', 'lafu-gift-cards' ) );
				}
				self::remove( (int) $row['card_id'] );
				self::fail(
					/* translators: %s: reason */
					sprintf( __( 'A gift card could not be charged: %s Please check the order total and try again.', 'lafu-gift-cards' ), $result->get_error_message() )
				);
			}
			$done[ (int) $row['card_id'] ] = (float) $row['amount'];
		}
		if ( $done ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: amount */
					__( '%s paid with gift cards.', 'lafu-gift-cards' ),
					lafu_gift_cards_plain_price( array_sum( $done ), array( 'currency' => $order->get_currency() ) )
				)
			);
		}
	}

	/**
	 * Release a gift card hold when the order will not be paid, and charge
	 * it again if that order is paid later.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $from     Old status.
	 * @param string $to       New status.
	 */
	public static function on_status( $order_id, $from, $to ) {
		$paid = array( 'processing', 'completed' );
		if ( in_array( $to, array( 'failed', 'cancelled', 'refunded', 'trash' ), true ) ) {
			self::refill( $order_id );
			return;
		}
		if ( in_array( $to, $paid, true ) && ! in_array( $from, $paid, true ) ) {
			self::ensure_charged( $order_id );
		}
	}

	/**
	 * Charge the saved plan when an order becomes paid and nothing is
	 * currently held. A retry after a failed payment uses this. An order
	 * that already holds the money, including after a partial return, is
	 * left alone.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function ensure_charged( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() || self::order_paid( $order ) <= 0 ) {
			return;
		}
		if ( Lafu_Gift_Cards_Store::order_spend( $order->get_id() ) ) {
			return;
		}
		// The payment already went through, so never stop it here. A card
		// that cannot be charged again leaves a note for the shop instead.
		$done  = 0.0;
		$short = 0.0;
		foreach ( lafu_gift_cards_meta_array( $order, self::ORDER_META ) as $row ) {
			$card_id = isset( $row['card_id'] ) ? (int) $row['card_id'] : 0;
			$amount  = isset( $row['amount'] ) ? (float) $row['amount'] : 0;
			if ( $card_id <= 0 || $amount <= 0 ) {
				continue;
			}
			/* translators: %s: order number */
			$note   = sprintf( __( 'Paid order %s', 'lafu-gift-cards' ), $order->get_order_number() );
			$result = Lafu_Gift_Cards_Store::debit( $card_id, $amount, $order->get_id(), 'redeem', $note );
			if ( true === $result ) {
				$done += $amount;
			} else {
				$short += $amount;
			}
		}
		$money = array( 'currency' => $order->get_currency() );
		if ( $done > 0 ) {
			/* translators: %s: amount */
			$order->add_order_note( sprintf( __( '%s paid with gift cards.', 'lafu-gift-cards' ), lafu_gift_cards_plain_price( $done, $money ) ) );
		}
		if ( $short > 0 ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: amount */
					__( 'The gift cards on this order could not be charged again after the payment. %s is still unpaid.', 'lafu-gift-cards' ),
					lafu_gift_cards_plain_price( $short, $money )
				)
			);
		}
	}

	/**
	 * Give back gift card money still held by pending or failed orders
	 * whose payment was never finished. Paid and on-hold orders are kept.
	 */
	public static function release_unpaid() {
		$minutes = (int) get_option( 'woocommerce_hold_stock_minutes' );
		if ( $minutes < 1 ) {
			$minutes = 60;
		}
		$cutoff = time() - ( $minutes * MINUTE_IN_SECONDS );
		foreach ( Lafu_Gift_Cards_Store::order_ids_with_spend( 100 ) as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order || ! $order->has_status( array( 'pending', 'failed' ) ) ) {
				continue;
			}
			$created = $order->get_date_created();
			if ( $created && $created->getTimestamp() > $cutoff ) {
				continue;
			}
			self::refill( (int) $order_id );
		}
	}

	/**
	 * When a refund names the products, return the gift cards' share of
	 * those lines. An amount-only refund is the card payment and is left
	 * for the shop to return with the link on the order.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public static function refund_share( $order_id, $refund_id ) {
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );
		if ( ! $order || ! $refund || '' !== (string) $refund->get_meta( '_lafu_gc_share_done' ) ) {
			return;
		}
		$lines = 0.0;
		foreach ( $refund->get_items( 'line_item' ) as $item ) {
			$lines += abs( (float) $item->get_total() + (float) $item->get_total_tax() );
		}
		$lines = Lafu_Gift_Cards_Store::money( $lines );
		$gc    = self::order_paid( $order );
		// The WooCommerce refund amount is the part already sent back
		// through the payment method. Only the rest of the lines belongs
		// to the gift cards, so the customer is not paid twice.
		$share = 0.0;
		if ( $lines > 0 && $gc > 0 ) {
			$share = Lafu_Gift_Cards_Store::money( max( 0, $lines - (float) $refund->get_amount() ) );
			$share = min( $share, $gc );
		}
		$spend = Lafu_Gift_Cards_Store::order_spend( $order->get_id() );
		$left  = Lafu_Gift_Cards_Store::money( array_sum( $spend ) );
		$share = min( $share, $left );
		$back  = 0.0;
		if ( $share > 0 && $left > 0 ) {
			/* translators: %s: order number */
			$note    = sprintf( __( 'Partial refund of order %s', 'lafu-gift-cards' ), $order->get_order_number() );
			$running = 0.0;
			$ids     = array_keys( $spend );
			$last    = count( $ids ) - 1;
			foreach ( $ids as $index => $card_id ) {
				$part     = $index === $last
					? Lafu_Gift_Cards_Store::money( $share - $running )
					: Lafu_Gift_Cards_Store::money( $share * $spend[ $card_id ] / $left );
				$running += $part;
				$part     = min( $part, $spend[ $card_id ] );
				if ( $part > 0 && true === Lafu_Gift_Cards_Store::credit( $card_id, $part, $order->get_id(), 'return', $note ) ) {
					$back += $part;
				}
			}
		}
		if ( $back > 0 ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: amount */
					__( '%s returned to gift cards.', 'lafu-gift-cards' ),
					lafu_gift_cards_plain_price( $back, array( 'currency' => $order->get_currency() ) )
				)
			);
		}
		$refund->update_meta_data( '_lafu_gc_share_done', '1' );
		$refund->save();
	}

	/**
	 * Put the money back on the cards of an order that will not be paid.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $note     Ledger note.
	 */
	public static function refill( $order_id, $note = '' ) {
		$spend = Lafu_Gift_Cards_Store::order_spend( absint( $order_id ) );
		if ( ! $spend ) {
			return;
		}
		$order    = wc_get_order( $order_id );
		$announce = '' === $note;
		if ( $announce && $order ) {
			if ( $order->has_status( array( 'failed', 'pending' ) ) ) {
				/* translators: %s: order number */
				$note = sprintf( __( 'Payment for order %s did not finish', 'lafu-gift-cards' ), $order->get_order_number() );
			} else {
				/* translators: %s: order number */
				$note = sprintf( __( 'Order %s cancelled or refunded', 'lafu-gift-cards' ), $order->get_order_number() );
			}
		}
		$total = 0;
		foreach ( $spend as $card_id => $amount ) {
			if ( true === Lafu_Gift_Cards_Store::credit( $card_id, $amount, absint( $order_id ), 'return', $note ) ) {
				$total += $amount;
			}
		}
		if ( $announce && $order && $total > 0 ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: amount */
					__( '%s returned to gift cards.', 'lafu-gift-cards' ),
					lafu_gift_cards_plain_price( $total, array( 'currency' => $order->get_currency() ) )
				)
			);
		}
	}

	/**
	 * When an order total is recalculated (for example in the admin), keep
	 * the gift card payment taken off it.
	 *
	 * @param bool     $and_taxes Taxes were recalculated.
	 * @param WC_Order $order     Order.
	 */
	public static function keep_order_total( $and_taxes, $order ) {
		unset( $and_taxes );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$paid = self::order_paid( $order );
		if ( $paid > 0 ) {
			$order->set_total( max( 0, Lafu_Gift_Cards_Store::money( (float) $order->get_total( 'edit' ) - $paid ) ) );
		}
	}

	/**
	 * Gift card rows in order totals: thank you page, My Account, emails.
	 *
	 * @param array    $rows  Total rows.
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function order_rows( $rows, $order ) {
		$plan = $order instanceof WC_Order ? lafu_gift_cards_meta_array( $order, self::ORDER_META ) : array();
		if ( ! $plan ) {
			return $rows;
		}
		$add = array();
		foreach ( $plan as $index => $row ) {
			$add[ 'lafu_gc_' . $index ] = array(
				/* translators: %s: masked gift card code */
				'label' => sprintf( __( 'Gift card %s:', 'lafu-gift-cards' ), isset( $row['code'] ) ? $row['code'] : '' ),
				'value' => wc_price( -1 * (float) $row['amount'], array( 'currency' => $order->get_currency() ) ),
			);
		}
		$out = array();
		foreach ( $rows as $key => $row ) {
			if ( 'order_total' === $key ) {
				$out += $add;
				$add  = array();
			}
			$out[ $key ] = $row;
		}
		return $out + $add;
	}

	/**
	 * Gift card rows on the admin order screen.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function admin_rows( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$spend = Lafu_Gift_Cards_Store::order_spend( $order->get_id() );
		$money = array( 'currency' => $order->get_currency() );
		foreach ( lafu_gift_cards_meta_array( $order, self::ORDER_META ) as $row ) {
			$card_id = isset( $row['card_id'] ) ? (int) $row['card_id'] : 0;
			$left    = isset( $spend[ $card_id ] ) ? $spend[ $card_id ] : 0;
			?>
			<tr>
				<td class="label">
					<?php
					/* translators: %s: masked gift card code */
					echo esc_html( sprintf( __( 'Gift card %s:', 'lafu-gift-cards' ), $row['code'] ) );
					?>
				</td>
				<td width="1%"></td>
				<td class="total">
					<?php echo wp_kses_post( wc_price( -1 * (float) $row['amount'], $money ) ); ?>
					<?php if ( $left > 0 && current_user_can( Lafu_Gift_Cards_Admin::CAPABILITY ) ) : ?>
						<br><a href="<?php echo esc_url( self::return_url( $order->get_id(), $card_id ) ); ?>" data-lafu-gc-confirm="<?php echo esc_attr( sprintf( /* translators: %s: amount */ __( 'Put %s back on this gift card?', 'lafu-gift-cards' ), lafu_gift_cards_plain_price( $left, $money ) ) ); ?>"><?php esc_html_e( 'Return to card', 'lafu-gift-cards' ); ?></a>
					<?php elseif ( (float) $row['amount'] > 0 && $card_id ) : ?>
						<br><small><?php esc_html_e( 'Returned to the card', 'lafu-gift-cards' ); ?></small>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Signed link that returns what a card paid on an order.
	 *
	 * @param int $order_id Order ID.
	 * @param int $card_id  Card ID.
	 * @return string
	 */
	public static function return_url( $order_id, $card_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'lafu_gc_order_return',
					'order'  => (int) $order_id,
					'card'   => (int) $card_id,
				),
				admin_url( 'admin-post.php' )
			),
			'lafu_gc_order_return_' . (int) $order_id . '_' . (int) $card_id
		);
	}

	/**
	 * Return to the card what it paid on an order, for example when the
	 * order is refunded in part. The amount comes from the card history,
	 * so it can never be more than the card paid.
	 */
	public static function admin_return() {
		$order_id = isset( $_GET['order'] ) ? absint( wp_unslash( $_GET['order'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked below with the order and card in the action.
		$card_id  = isset( $_GET['card'] ) ? absint( wp_unslash( $_GET['card'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked below with the order and card in the action.
		if ( ! current_user_can( Lafu_Gift_Cards_Admin::CAPABILITY ) || ! check_admin_referer( 'lafu_gc_order_return_' . $order_id . '_' . $card_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'lafu-gift-cards' ), '', array( 'response' => 403 ) );
		}
		$order = wc_get_order( $order_id );
		$spend = Lafu_Gift_Cards_Store::order_spend( $order_id );
		if ( $order && ! empty( $spend[ $card_id ] ) ) {
			/* translators: %s: order number */
			$note   = sprintf( __( 'Returned by the shop from order %s', 'lafu-gift-cards' ), $order->get_order_number() );
			$result = Lafu_Gift_Cards_Store::credit( $card_id, $spend[ $card_id ], $order_id, 'return', $note );
			if ( true === $result ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: amount */
						__( '%s returned to gift cards.', 'lafu-gift-cards' ),
						lafu_gift_cards_plain_price( $spend[ $card_id ], array( 'currency' => $order->get_currency() ) )
					),
					0,
					true
				);
			}
		}
		wp_safe_redirect( $order ? $order->get_edit_order_url() : admin_url() );
		exit;
	}

	/**
	 * Total paid with gift cards on an order.
	 *
	 * @param WC_Order $order Order.
	 * @return float
	 */
	public static function order_paid( $order ) {
		$sum = 0;
		foreach ( lafu_gift_cards_meta_array( $order, self::ORDER_META ) as $row ) {
			$sum += isset( $row['amount'] ) ? (float) $row['amount'] : 0;
		}
		return Lafu_Gift_Cards_Store::money( $sum );
	}

	/**
	 * The customer's saved cards that can pay now, for one-click use.
	 *
	 * @return array List of id, label, balance.
	 */
	public static function saved_cards() {
		$user = get_current_user_id();
		if ( ! $user ) {
			return array();
		}
		$out     = array();
		$applied = self::applied_ids();
		foreach ( Lafu_Gift_Cards_Store::for_user( $user ) as $card ) {
			if ( in_array( (int) $card->id, $applied, true ) || ! Lafu_Gift_Cards_Account::owns( $card, $user ) || true !== Lafu_Gift_Cards_Store::usable( $card, get_woocommerce_currency() ) ) {
				continue;
			}
			$out[] = array(
				'id'      => (int) $card->id,
				'label'   => lafu_gift_cards_card_title( $card ),
				'balance' => lafu_gift_cards_plain_price( $card->balance, array( 'currency' => $card->currency ) ),
			);
		}
		return $out;
	}

	/**
	 * Recalculate the cart after cards change.
	 */
	public static function recalculate() {
		if ( WC()->cart ) {
			WC()->cart->calculate_totals();
		}
	}

	/**
	 * Add a usable card to the session.
	 *
	 * @param object|null $card Card row.
	 * @return true|WP_Error
	 */
	private static function apply_card( $card ) {
		$usable = Lafu_Gift_Cards_Store::usable( $card, get_woocommerce_currency() );
		if ( is_wp_error( $usable ) ) {
			return $usable;
		}
		$ids = self::applied_ids();
		if ( in_array( (int) $card->id, $ids, true ) ) {
			return new WP_Error( 'lafu_gc_applied', __( 'This gift card is already in use on this order.', 'lafu-gift-cards' ) );
		}
		$ids[] = (int) $card->id;
		self::session_set( self::SESSION_CARDS, $ids );
		return true;
	}

	/**
	 * Stop checkout with a message the customer sees.
	 *
	 * @param string $message Message.
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException In the checkout block.
	 * @throws Exception On the classic checkout.
	 */
	private static function fail( $message ) {
		if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) && did_action( 'woocommerce_store_api_checkout_update_order_meta' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'lafu_gift_cards_charge', esc_html( $message ), 409 );
		}
		throw new Exception( esc_html( $message ) );
	}

	/**
	 * Show a notice on classic pages, once per message.
	 *
	 * @param string $message Message.
	 * @param string $type    Notice type.
	 */
	private static function notice( $message, $type ) {
		if ( function_exists( 'wc_add_notice' ) && ! wc_has_notice( $message, $type ) && ! WC()->is_rest_api_request() ) {
			wc_add_notice( $message, $type );
		}
	}

	/**
	 * Read from the WooCommerce session.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	private static function session_get( $key ) {
		return function_exists( 'WC' ) && WC()->session ? WC()->session->get( $key ) : null;
	}

	/**
	 * Write to the WooCommerce session.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	private static function session_set( $key, $value ) {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( $key, $value );
		}
	}
}
