<?php
/**
 * Issue cards from paid orders, deliver them and cancel them with the order.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns paid gift card order lines into cards.
 */
class Lafu_Gift_Cards_Issuer {

	const DELIVER_HOOK = 'lafu_gift_cards_deliver';
	const CARDS_META   = '_lafu_gc_cards';

	/**
	 * Hook order events and delivery.
	 */
	public static function register() {
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'issue_for_order' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'issue_for_order' ) );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'cancel_for_order' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'cancel_for_order' ) );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'claw_refund' ), 20, 2 );
		// A paid order that fails afterwards, is disputed (paid to on-hold) or
		// is trashed or deleted: unused cards stop working, used ones are
		// flagged for the shop.
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'hold_on_status' ), 20, 3 );
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'hold_on_trash' ) );
		add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'hold_on_trash' ) );
		add_action( 'wp_trash_post', array( __CLASS__, 'hold_on_trash_post' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'hold_on_trash_post' ) );

		add_action( self::DELIVER_HOOK, array( __CLASS__, 'deliver' ) );
		add_action( Lafu_Gift_Cards_Install::DAILY_HOOK, array( __CLASS__, 'daily' ) );
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'email_class' ) );
		add_filter( 'woocommerce_prepare_email_for_preview', array( __CLASS__, 'email_preview' ) );

		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hidden_meta' ) );
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'admin_item_cards' ), 10, 2 );
		add_action( 'woocommerce_order_item_meta_end', array( __CLASS__, 'customer_item_cards' ), 10, 4 );
	}

	/**
	 * Create the cards of every paid gift card line that has none yet.
	 * Safe to call any number of times.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function issue_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() ) {
			return;
		}
		$lock = 'lafu_gc_issue_' . $order->get_id();
		if ( ! Lafu_Gift_Cards_Store::acquire_lock( $lock ) ) {
			return;
		}
		try {
			foreach ( $order->get_items() as $item ) {
				if ( $item instanceof WC_Order_Item_Product && lafu_gift_cards_meta_array( $item, Lafu_Gift_Cards_Product::ITEM_META ) ) {
					self::issue_for_item( $order, $item );
				}
			}
		} finally {
			Lafu_Gift_Cards_Store::release_lock( $lock );
		}
	}

	/**
	 * Create the missing cards of one order line.
	 *
	 * @param WC_Order              $order Order.
	 * @param WC_Order_Item_Product $item  Line.
	 */
	private static function issue_for_item( $order, $item ) {
		$item = new WC_Order_Item_Product( $item->get_id() );
		$data = lafu_gift_cards_meta_array( $item, Lafu_Gift_Cards_Product::ITEM_META );

		/**
		 * Handle a paid gift card line in another way, for example a top-up
		 * of an existing card. Return true when handled: no new card is made.
		 * Called on every paid status change, so handlers must be idempotent.
		 *
		 * @param bool                  $handled Handled already.
		 * @param WC_Order              $order   Order.
		 * @param WC_Order_Item_Product $item    Line.
		 * @param array                 $data    Card data from the buyer form.
		 */
		if ( apply_filters( 'lafu_gift_cards_pre_issue', false, $order, $item, $data ) ) {
			return;
		}
		$cards = array_values( array_filter( array_map( 'absint', lafu_gift_cards_meta_array( $item, self::CARDS_META ) ) ) );
		// Cards already refunded before the order was completed are not issued.
		$need = (int) $item->get_quantity() - absint( $order->get_qty_refunded_for_item( $item->get_id() ) ) - count( $cards );
		if ( $need <= 0 ) {
			return;
		}

		$emails  = ! empty( $data['recipient_emails'] ) ? array_values( (array) $data['recipient_emails'] ) : array();
		$buyer   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$deliver = self::deliver_at( isset( $data['deliver_date'] ) ? (string) $data['deliver_date'] : '' );
		$amount  = self::issue_amount( $order, $item, $data );
		if ( $amount <= 0 ) {
			/* translators: %s: product name */
			$order->add_order_note( sprintf( __( 'No gift card was created for %s: nothing was paid for this line. Check the order and create the card by hand if it is due.', 'lafu-gift-cards' ), $item->get_name() ) );
			return;
		}

		for ( $i = 0; $i < $need; $i++ ) {
			$index = count( $cards );
			$email = $emails ? $emails[ $index % count( $emails ) ] : $order->get_billing_email();
			/**
			 * Card fields for a card about to be issued from a paid order
			 * line, for example meta with product limits.
			 *
			 * @param array                 $fields Card fields for Lafu_Gift_Cards_Store::create().
			 * @param array                 $data   Card data from the buyer form.
			 * @param WC_Order              $order  Order.
			 * @param WC_Order_Item_Product $item   Order line.
			 */
			$fields  = apply_filters(
				'lafu_gift_cards_issue_data',
				array(
					'amount'          => $amount,
					'currency'        => $order->get_currency(),
					'design'          => isset( $data['design'] ) ? $data['design'] : '',
					'meta'            => array_merge(
						! empty( $data['colour'] ) && sanitize_hex_color( $data['colour'] ) ? array( 'colour' => sanitize_hex_color( $data['colour'] ) ) : array(),
						Lafu_Gift_Cards_Designs::clean_texts( isset( $data['card_texts'] ) && is_array( $data['card_texts'] ) ? $data['card_texts'] : array() )
					),
					'recipient_name'  => ! empty( $data['gift'] ) ? (string) $data['recipient_name'] : $buyer,
					'recipient_email' => (string) $email,
					'sender_name'     => isset( $data['sender_name'] ) ? (string) $data['sender_name'] : '',
					'message'         => isset( $data['message'] ) ? (string) $data['message'] : '',
					'physical'        => ! empty( $data['physical'] ),
					'deliver_at'      => $deliver,
					'expires_at'      => Lafu_Gift_Cards_Settings::default_expiry(),
					'buyer_id'        => $order->get_customer_id(),
					'order_id'        => $order->get_id(),
					'order_item_id'   => $item->get_id(),
				),
				$data,
				$order,
				$item
			);
			$card_id = Lafu_Gift_Cards_Store::create(
				$fields,
				'issue',
				/* translators: %s: order number */
				sprintf( __( 'Sold on order %s', 'lafu-gift-cards' ), $order->get_order_number() )
			);
			if ( is_wp_error( $card_id ) ) {
				/* translators: %s: error message */
				$order->add_order_note( sprintf( __( 'Gift card could not be created: %s', 'lafu-gift-cards' ), $card_id->get_error_message() ) );
				break;
			}
			$cards[] = $card_id;
			$item->update_meta_data( self::CARDS_META, $cards );
			$item->save();

			$card = Lafu_Gift_Cards_Store::get( $card_id );
			$order->add_order_note(
				sprintf(
					/* translators: 1: masked code, 2: amount, 3: email or "printed card" */
					__( 'Gift card %1$s (%2$s) created for %3$s.', 'lafu-gift-cards' ),
					Lafu_Gift_Cards_Codes::mask( $card->code ),
					lafu_gift_cards_plain_price( $card->initial_amount, array( 'currency' => $card->currency ) ),
					$card->physical ? __( 'a printed card', 'lafu-gift-cards' ) : $card->recipient_email
				)
			);

			/**
			 * A card was created from a paid order line.
			 *
			 * @param int                   $card_id Card ID.
			 * @param WC_Order              $order   Order.
			 * @param WC_Order_Item_Product $item    Line.
			 */
			do_action( 'lafu_gift_cards_issued', $card_id, $order, $item );

			self::schedule_delivery( $card );
		}
	}

	/**
	 * Value of each card of a line: the amount chosen in the buyer form, but
	 * never more than was actually paid per card. A discount that lowered the
	 * line (a coupon or a pricing plugin) would otherwise create money.
	 *
	 * @param WC_Order              $order Order.
	 * @param WC_Order_Item_Product $item  Line.
	 * @param array                 $data  Card data from the buyer form.
	 * @return float
	 */
	public static function issue_amount( $order, $item, $data ) {
		$face = Lafu_Gift_Cards_Store::money( isset( $data['amount'] ) ? $data['amount'] : 0 );
		$qty  = max( 1, (int) $item->get_quantity() );
		$paid = Lafu_Gift_Cards_Store::money( ( (float) $item->get_total() + (float) $item->get_total_tax() ) / $qty );
		$use  = $face;
		if ( $paid < $face - 0.01 ) {
			$use = $paid;
			$order->add_order_note(
				sprintf(
					/* translators: 1: product name, 2: amount chosen, 3: amount paid per card */
					__( 'Gift card line %1$s was discounted: %2$s was chosen but %3$s was paid per card, so each card was created with the amount paid.', 'lafu-gift-cards' ),
					$item->get_name(),
					lafu_gift_cards_plain_price( $face, array( 'currency' => $order->get_currency() ) ),
					lafu_gift_cards_plain_price( $paid, array( 'currency' => $order->get_currency() ) )
				)
			);
		}
		/**
		 * Value of each card issued from a paid order line. Defaults to the
		 * chosen amount, capped at what was paid per card.
		 *
		 * @param float                 $use   Amount to issue per card.
		 * @param float                 $face  Amount chosen in the buyer form.
		 * @param float                 $paid  Amount paid per card.
		 * @param WC_Order              $order Order.
		 * @param WC_Order_Item_Product $item  Line.
		 */
		return Lafu_Gift_Cards_Store::money( apply_filters( 'lafu_gift_cards_issue_amount', $use, $face, $paid, $order, $item ) );
	}

	/**
	 * When the purchase is cancelled or fully refunded, the cards stop
	 * working. Money already spent from a card cannot be taken back, so
	 * that case gets an order note for the shop.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function cancel_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( Lafu_Gift_Cards_Store::for_order( $order->get_id() ) as $card ) {
			if ( Lafu_Gift_Cards_Store::STATUS_ACTIVE !== $card->status ) {
				continue;
			}
			$used = (float) $card->initial_amount - (float) $card->balance;
			/* translators: %s: order number */
			$reason = sprintf( __( 'Order %s cancelled or refunded', 'lafu-gift-cards' ), $order->get_order_number() );
			if ( true !== Lafu_Gift_Cards_Store::void( (int) $card->id, $reason ) ) {
				continue;
			}
			if ( $used > 0 ) {
				$order->add_order_note(
					sprintf(
						/* translators: 1: masked code, 2: amount already used */
						__( 'Gift card %1$s cancelled. %2$s of it had already been used.', 'lafu-gift-cards' ),
						Lafu_Gift_Cards_Codes::mask( $card->code ),
						lafu_gift_cards_plain_price( $used, array( 'currency' => $card->currency ) )
					)
				);
			} else {
				/* translators: %s: masked code */
				$order->add_order_note( sprintf( __( 'Gift card %s cancelled.', 'lafu-gift-cards' ), Lafu_Gift_Cards_Codes::mask( $card->code ) ) );
			}
		}
	}

	/**
	 * A paid order moved to failed or on-hold (a chargeback or dispute
	 * usually does that), or to the trash.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $from     Old status.
	 * @param string $to       New status.
	 */
	public static function hold_on_status( $order_id, $from, $to ) {
		$paid = wc_get_is_paid_statuses();
		if ( in_array( $to, $paid, true ) ) {
			self::clear_review( $order_id );
			return;
		}
		if ( 'trash' === $to ) {
			self::hold_for_order( $order_id, ! in_array( $from, $paid, true ) );
		} elseif ( 'failed' === $to || ( 'on-hold' === $to && in_array( $from, $paid, true ) ) ) {
			self::hold_for_order( $order_id, true );
		}
	}

	/**
	 * The order is trashed or about to be deleted.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function hold_on_trash( $order_id ) {
		$order = $order_id ? wc_get_order( absint( $order_id ) ) : null;
		if ( ! $order || $order instanceof WC_Order_Refund ) {
			return;
		}
		$status = $order->get_status();
		if ( 'trash' === $status ) {
			$status = (string) $order->get_meta( '_wp_trash_meta_status' );
		}
		$status = 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
		// Deleting a paid order is not a refund: its cards are flagged for
		// the shop, never cancelled.
		self::hold_for_order( $order->get_id(), ! in_array( $status, wc_get_is_paid_statuses(), true ) );
	}

	/**
	 * The order is trashed or deleted as a post (orders stored as posts).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function hold_on_trash_post( $post_id ) {
		if ( in_array( get_post_type( $post_id ), wc_get_order_types(), true ) ) {
			self::hold_on_trash( absint( $post_id ) );
		}
	}

	/**
	 * Stop the cards bought by an order whose payment is no longer good.
	 * A card nobody has used yet is cancelled, and it is created again if
	 * the order is paid again (for example when the shop wins the dispute).
	 * A card that was already used stays active and is flagged for the shop,
	 * because the money spent cannot be taken back. A paid order that is
	 * only deleted flags its cards and cancels nothing.
	 *
	 * @param int  $order_id    Order ID.
	 * @param bool $void_unused Cancel cards nobody has used yet.
	 */
	public static function hold_for_order( $order_id, $void_unused = true ) {
		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order || $order instanceof WC_Order_Refund ) {
			return;
		}
		$cards = Lafu_Gift_Cards_Store::for_order( $order->get_id() );
		if ( ! $cards ) {
			return;
		}
		$lock = 'lafu_gc_issue_' . $order->get_id();
		if ( ! Lafu_Gift_Cards_Store::acquire_lock( $lock ) ) {
			return;
		}
		try {
			/* translators: %s: order number */
			$reason = sprintf( __( 'Payment of order %s is no longer valid (failed, disputed or deleted)', 'lafu-gift-cards' ), $order->get_order_number() );
			foreach ( $cards as $card ) {
				$card = Lafu_Gift_Cards_Store::get( (int) $card->id );
				if ( ! $card || Lafu_Gift_Cards_Store::STATUS_ACTIVE !== $card->status ) {
					continue;
				}
				$code = Lafu_Gift_Cards_Codes::mask( $card->code );
				if ( ! $void_unused ) {
					Lafu_Gift_Cards_Store::set_meta( (int) $card->id, array( 'review' => $reason ) );
					/* translators: %s: masked code */
					$order->add_order_note( sprintf( __( 'Check gift card %s: this paid order was deleted, but the card is still active.', 'lafu-gift-cards' ), $code ) );
					/** This action is documented below. */
					do_action( 'lafu_gift_cards_needs_review', (int) $card->id, $order );
					continue;
				}
				if ( Lafu_Gift_Cards_Store::money( $card->balance ) >= Lafu_Gift_Cards_Store::money( $card->initial_amount ) ) {
					if ( true === Lafu_Gift_Cards_Store::void( (int) $card->id, $reason ) ) {
						self::forget_card_on_line( $order, $card );
						/* translators: %s: masked code */
						$order->add_order_note( sprintf( __( 'Gift card %s cancelled because the payment is no longer valid. It was not used yet. If the order is paid again, a new card is created.', 'lafu-gift-cards' ), $code ) );
					}
					continue;
				}
				$used = Lafu_Gift_Cards_Store::money( (float) $card->initial_amount - (float) $card->balance );
				Lafu_Gift_Cards_Store::set_meta( (int) $card->id, array( 'review' => $reason ) );
				$order->add_order_note(
					sprintf(
						/* translators: 1: masked code, 2: amount already used */
						__( 'Check gift card %1$s: the payment of this order is no longer valid, but %2$s of the card was already used. The card is still active. Cancel it under WooCommerce > Gift cards if needed.', 'lafu-gift-cards' ),
						$code,
						lafu_gift_cards_plain_price( $used, array( 'currency' => $card->currency ) )
					)
				);
				/**
				 * A used card bought by an order whose payment failed, was
				 * disputed or deleted. The shop should check it.
				 *
				 * @param int      $card_id Card ID.
				 * @param WC_Order $order   Order.
				 */
				do_action( 'lafu_gift_cards_needs_review', (int) $card->id, $order );
			}
		} finally {
			Lafu_Gift_Cards_Store::release_lock( $lock );
		}
	}

	/**
	 * The order is paid again: the flag on its used cards goes away.
	 *
	 * @param int $order_id Order ID.
	 */
	private static function clear_review( $order_id ) {
		foreach ( Lafu_Gift_Cards_Store::for_order( absint( $order_id ) ) as $card ) {
			if ( ! empty( Lafu_Gift_Cards_Store::meta( $card )['review'] ) ) {
				Lafu_Gift_Cards_Store::set_meta( (int) $card->id, array( 'review' => null ) );
			}
		}
	}

	/**
	 * Take a cancelled card off its order line, so paying the order again
	 * creates a new one.
	 *
	 * @param WC_Order $order Order.
	 * @param object   $card  Card row.
	 */
	private static function forget_card_on_line( $order, $card ) {
		$item = $card->order_item_id ? $order->get_item( (int) $card->order_item_id ) : null;
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$ids  = array_values( array_filter( array_map( 'absint', lafu_gift_cards_meta_array( $item, self::CARDS_META ) ) ) );
		$keep = array_values( array_diff( $ids, array( (int) $card->id ) ) );
		if ( $keep === $ids ) {
			return;
		}
		$item->update_meta_data( self::CARDS_META, $keep );
		$item->save();
	}

	/**
	 * Send now, or at the chosen date. Printed cards are never emailed.
	 *
	 * @param object $card Card row.
	 */
	public static function schedule_delivery( $card ) {
		if ( ! $card || $card->physical || ! empty( $card->delivered_at ) ) {
			return;
		}
		if ( ! empty( $card->deliver_at ) && ! Lafu_Gift_Cards_Store::is_past( $card->deliver_at ) && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( strtotime( $card->deliver_at . ' UTC' ), self::DELIVER_HOOK, array( (int) $card->id ), 'lafu-gift-cards' );
			return;
		}
		self::deliver( (int) $card->id );
	}

	/**
	 * Email one card to its recipient, once.
	 *
	 * @param int  $card_id Card ID.
	 * @param bool $resend  Send again even if it was already sent.
	 * @return bool Sent.
	 */
	public static function deliver( $card_id, $resend = false ) {
		$card = Lafu_Gift_Cards_Store::get( absint( $card_id ) );
		if ( ! $card || $card->physical || Lafu_Gift_Cards_Store::STATUS_ACTIVE !== $card->status || ! is_email( $card->recipient_email ) ) {
			return false;
		}
		if ( ! empty( $card->delivered_at ) && ! $resend ) {
			return false;
		}

		/**
		 * A card is about to be emailed. Add-ons can finish its details here,
		 * for example the names on a group gift.
		 *
		 * @param object $card   Card row.
		 * @param bool   $resend Sent again on request.
		 */
		do_action( 'lafu_gift_cards_before_delivery', $card, $resend );
		$card   = Lafu_Gift_Cards_Store::get( (int) $card->id );
		$emails = WC()->mailer()->get_emails();
		if ( empty( $emails['Lafu_Gift_Cards_Email'] ) ) {
			return false;
		}
		$sent = $emails['Lafu_Gift_Cards_Email']->trigger( $card );
		if ( $sent ) {
			Lafu_Gift_Cards_Store::update( (int) $card->id, array( 'delivered_at' => current_time( 'mysql', true ) ) );
		}

		/**
		 * A card was delivered by email (or the email failed). Other
		 * channels, such as text messages, follow from here.
		 *
		 * @param object $card   Card row.
		 * @param bool   $sent   Email sent.
		 * @param bool   $resend Sent again on request.
		 */
		do_action( 'lafu_gift_cards_delivered', $card, $sent, $resend );
		return $sent;
	}

	/**
	 * Daily job: expire old cards and send any delivery that was missed.
	 */
	public static function daily() {
		Lafu_Gift_Cards_Store::expire_due();
		Lafu_Gift_Cards_Redeem::release_unpaid();
		foreach ( Lafu_Gift_Cards_Store::due_for_delivery() as $card ) {
			self::deliver( (int) $card->id );
		}
	}

	/**
	 * A partial refund of a sold gift card lowers that card. A full refund
	 * still cancels it through the refunded status. Money already spent
	 * cannot be taken back, so the shop gets a note for the difference.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public static function claw_refund( $order_id, $refund_id ) {
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );
		if ( ! $order || ! $refund || '' !== (string) $refund->get_meta( '_lafu_gc_clawed' ) ) {
			return;
		}
		foreach ( $refund->get_items( 'line_item' ) as $refund_item ) {
			$original = $order->get_item( (int) $refund_item->get_meta( '_refunded_item_id' ) );
			$ids      = $original ? array_values( array_filter( array_map( 'absint', lafu_gift_cards_meta_array( $original, self::CARDS_META ) ) ) ) : array();
			$amount   = Lafu_Gift_Cards_Store::money( abs( (float) $refund_item->get_total() + (float) $refund_item->get_total_tax() ) );
			if ( ! $ids || $amount <= 0 ) {
				continue;
			}
			/* translators: %s: order number */
			$reason  = sprintf( __( 'Order %s refunded in part', 'lafu-gift-cards' ), $order->get_order_number() );
			$running = 0.0;
			$last    = count( $ids ) - 1;
			foreach ( $ids as $index => $card_id ) {
				$part     = $index === $last
					? Lafu_Gift_Cards_Store::money( $amount - $running )
					: Lafu_Gift_Cards_Store::money( $amount / count( $ids ) );
				$running += $part;
				$card     = Lafu_Gift_Cards_Store::get( $card_id );
				if ( ! $card || Lafu_Gift_Cards_Store::STATUS_ACTIVE !== $card->status ) {
					continue;
				}
				$balance = (float) $card->balance;
				$money   = array( 'currency' => $card->currency );
				if ( $balance <= 0 || $part >= $balance ) {
					if ( $balance > 0 ) {
						Lafu_Gift_Cards_Store::void( $card_id, $reason );
					}
					$short = Lafu_Gift_Cards_Store::money( $part - $balance );
					if ( $short > 0 ) {
						$order->add_order_note(
							sprintf(
								/* translators: 1: masked code, 2: amount already used */
								__( 'Gift card %1$s cancelled. %2$s of it had already been used.', 'lafu-gift-cards' ),
								Lafu_Gift_Cards_Codes::mask( $card->code ),
								lafu_gift_cards_plain_price( $short, $money )
							)
						);
					} else {
						/* translators: %s: masked code */
						$order->add_order_note( sprintf( __( 'Gift card %s cancelled.', 'lafu-gift-cards' ), Lafu_Gift_Cards_Codes::mask( $card->code ) ) );
					}
					continue;
				}
				if ( true === Lafu_Gift_Cards_Store::debit( $card_id, $part, $order->get_id(), 'adjust', $reason ) ) {
					$order->add_order_note(
						sprintf(
							/* translators: 1: masked code, 2: amount */
							__( 'Gift card %1$s lowered by %2$s.', 'lafu-gift-cards' ),
							Lafu_Gift_Cards_Codes::mask( $card->code ),
							lafu_gift_cards_plain_price( $part, $money )
						)
					);
				}
			}
		}
		$refund->update_meta_data( '_lafu_gc_clawed', '1' );
		$refund->save();
	}

	/**
	 * Register the gift card email with WooCommerce, so shops can edit it
	 * under WooCommerce > Settings > Emails.
	 *
	 * @param array $emails Email classes.
	 * @return array
	 */
	public static function email_class( $emails ) {
		require_once LAFU_GIFT_CARDS_DIR . '/includes/class-lafu-gift-cards-email.php';
		$emails['Lafu_Gift_Cards_Email'] = new Lafu_Gift_Cards_Email();
		return $emails;
	}

	/**
	 * Give our email a sample card in WooCommerce's email preview.
	 *
	 * @param WC_Email $email Email being previewed.
	 * @return WC_Email
	 */
	public static function email_preview( $email ) {
		return class_exists( 'Lafu_Gift_Cards_Email' ) ? Lafu_Gift_Cards_Email::prepare_preview( $email ) : $email;
	}

	/**
	 * Keep internal line meta out of the order screen.
	 *
	 * @param array $keys Hidden keys.
	 * @return array
	 */
	public static function hidden_meta( $keys ) {
		$keys[] = Lafu_Gift_Cards_Product::ITEM_META;
		$keys[] = self::CARDS_META;
		return $keys;
	}

	/**
	 * Card codes under the line on the admin order screen.
	 *
	 * @param int           $item_id Item ID.
	 * @param WC_Order_Item $item    Item.
	 */
	public static function admin_item_cards( $item_id, $item ) {
		$ids = array_filter( lafu_gift_cards_meta_array( $item, self::CARDS_META ) );
		if ( ! $ids ) {
			return;
		}
		echo '<div class="lafu-gc-order-cards"><strong>' . esc_html__( 'Gift cards', 'lafu-gift-cards' ) . ':</strong> ';
		$links = array();
		foreach ( $ids as $id ) {
			$card = Lafu_Gift_Cards_Store::get( absint( $id ) );
			if ( $card ) {
				$links[] = '<a href="' . esc_url( Lafu_Gift_Cards_Admin::card_url( (int) $card->id ) ) . '"><code>' . esc_html( $card->code ) . '</code></a>';
			}
		}
		echo wp_kses_post( implode( ', ', $links ) ) . '</div>';
	}

	/**
	 * Under the line in the customer's order view and emails: the code for
	 * cards bought for oneself, a delivery line for gifts.
	 *
	 * @param int           $item_id    Item ID.
	 * @param WC_Order_Item $item       Item.
	 * @param WC_Order      $order      Order.
	 * @param bool          $plain_text Plain text email.
	 */
	public static function customer_item_cards( $item_id, $item, $order, $plain_text = false ) {
		$ids = array_filter( lafu_gift_cards_meta_array( $item, self::CARDS_META ) );
		if ( ! $ids ) {
			return;
		}
		$data  = lafu_gift_cards_meta_array( $item, Lafu_Gift_Cards_Product::ITEM_META );
		$lines = array();
		foreach ( $ids as $id ) {
			$card = Lafu_Gift_Cards_Store::get( absint( $id ) );
			if ( ! $card ) {
				continue;
			}
			if ( ! empty( $data['gift'] ) ) {
				$lines[] = $card->physical ? __( 'Printed card, sent by the shop', 'lafu-gift-cards' ) : sprintf(
					/* translators: 1: masked code, 2: email address */
					__( 'Gift card %1$s for %2$s', 'lafu-gift-cards' ),
					Lafu_Gift_Cards_Codes::mask( $card->code ),
					$card->recipient_email
				);
			} else {
				/* translators: %s: gift card code */
				$lines[] = sprintf( __( 'Gift card code: %s', 'lafu-gift-cards' ), $card->code );
			}
		}
		if ( $plain_text ) {
			echo "\n" . esc_html( implode( "\n", $lines ) );
			return;
		}
		echo '<p class="lafu-gc-item-cards" style="margin:.5em 0 0">' . wp_kses_post( implode( '<br>', array_map( 'esc_html', $lines ) ) ) . '</p>';
	}

	/**
	 * UTC delivery time for a chosen local date: 8:00 in the shop's time
	 * zone, or null when that time has already passed.
	 *
	 * @param string $date Y-m-d.
	 * @return string|null
	 */
	private static function deliver_at( $date ) {
		if ( '' === $date ) {
			return null;
		}
		$local = DateTime::createFromFormat( 'Y-m-d H:i:s', $date . ' 08:00:00', wp_timezone() );
		if ( ! $local || $local->getTimestamp() <= time() ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', $local->getTimestamp() );
	}
}
