<?php
/**
 * Admin list of gift cards.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Searchable, paged list of cards with a status filter.
 */
class Lafu_Gift_Cards_List_Table extends WP_List_Table {

	/**
	 * Set up the table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'gift-card',
				'plural'   => 'gift-cards',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'code'      => __( 'Code', 'lafu-gift-cards' ),
			'balance'   => __( 'Balance', 'lafu-gift-cards' ),
			'status'    => __( 'Status', 'lafu-gift-cards' ),
			'recipient' => __( 'Recipient', 'lafu-gift-cards' ),
			'order'     => __( 'Order', 'lafu-gift-cards' ),
			'expires'   => __( 'Valid until', 'lafu-gift-cards' ),
			'created'   => __( 'Created', 'lafu-gift-cards' ),
		);
	}

	/**
	 * Load the current page of cards.
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $status, array( Lafu_Gift_Cards_Store::STATUS_ACTIVE, Lafu_Gift_Cards_Store::STATUS_VOID, Lafu_Gift_Cards_Store::STATUS_EXPIRED, Lafu_Gift_Cards_Store::STATUS_INACTIVE ), true ) ) {
			$status = '';
		}
		$per_page = 20;
		$result   = Lafu_Gift_Cards_Store::query(
			array(
				'search'   => $search,
				'status'   => $status,
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = $result['rows'];
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Status filter links.
	 *
	 * @return array
	 */
	protected function get_views() {
		$current = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$base    = Lafu_Gift_Cards_Admin::page_url();
		$views   = array(
			''                                     => __( 'All', 'lafu-gift-cards' ),
			Lafu_Gift_Cards_Store::STATUS_ACTIVE   => __( 'Active', 'lafu-gift-cards' ),
			Lafu_Gift_Cards_Store::STATUS_EXPIRED  => __( 'Expired', 'lafu-gift-cards' ),
			Lafu_Gift_Cards_Store::STATUS_VOID     => __( 'Cancelled', 'lafu-gift-cards' ),
			Lafu_Gift_Cards_Store::STATUS_INACTIVE => __( 'Not activated', 'lafu-gift-cards' ),
		);
		$out     = array();
		foreach ( $views as $key => $label ) {
			$url         = '' === $key ? $base : add_query_arg( 'status', $key, $base );
			$out[ $key ] = sprintf( '<a href="%1$s"%2$s>%3$s</a>', esc_url( $url ), $current === $key ? ' class="current" aria-current="page"' : '', esc_html( $label ) );
		}
		return $out;
	}

	/**
	 * Code with a link to the card.
	 *
	 * @param object $item Card row.
	 * @return string
	 */
	public function column_code( $item ) {
		return sprintf( '<a href="%1$s"><code>%2$s</code></a>', esc_url( Lafu_Gift_Cards_Admin::card_url( (int) $item->id ) ), esc_html( $item->code ) );
	}

	/**
	 * Balance of the original amount.
	 *
	 * @param object $item Card row.
	 * @return string
	 */
	public function column_balance( $item ) {
		$args = array( 'currency' => $item->currency );
		return wp_kses_post( wc_price( $item->balance, $args ) ) . ' <span class="lafu-gc-muted">/ ' . wp_kses_post( wc_price( $item->initial_amount, $args ) ) . '</span>';
	}

	/**
	 * Status.
	 *
	 * @param object $item Card row.
	 * @return string
	 */
	public function column_status( $item ) {
		return '<span class="lafu-gc-status lafu-gc-status-' . esc_attr( Lafu_Gift_Cards_Account::status_key( $item ) ) . '">' . esc_html( Lafu_Gift_Cards_Account::status_label( $item ) ) . '</span>';
	}

	/**
	 * Recipient.
	 *
	 * @param object $item Card row.
	 * @return string
	 */
	public function column_recipient( $item ) {
		if ( $item->physical && '' === (string) $item->recipient_email ) {
			return esc_html__( 'Printed card', 'lafu-gift-cards' );
		}
		return esc_html( trim( $item->recipient_name . ' ' . $item->recipient_email ) );
	}

	/**
	 * Order link.
	 *
	 * @param object $item Card row.
	 * @return string
	 */
	public function column_order( $item ) {
		$order = $item->order_id ? wc_get_order( (int) $item->order_id ) : null;
		if ( ! $order ) {
			return '&ndash;';
		}
		return sprintf( '<a href="%1$s">#%2$s</a>', esc_url( $order->get_edit_order_url() ), esc_html( $order->get_order_number() ) );
	}

	/**
	 * Expiry.
	 *
	 * @param object $item Card row.
	 * @return string
	 */
	public function column_expires( $item ) {
		return esc_html( Lafu_Gift_Cards_Account::expiry_label( $item ) );
	}

	/**
	 * Created date.
	 *
	 * @param object $item Card row.
	 * @return string
	 */
	public function column_created( $item ) {
		return esc_html( wp_date( get_option( 'date_format' ), strtotime( $item->created_at . ' UTC' ) ) );
	}

	/**
	 * Empty list text.
	 */
	public function no_items() {
		esc_html_e( 'No gift cards found.', 'lafu-gift-cards' );
	}
}
