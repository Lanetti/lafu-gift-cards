<?php
/**
 * Uninstall. Gift cards are money customers own, so cards and their
 * history are kept unless the shop turned on "Remove data on uninstall".
 *
 * @package Lafu_Gift_Cards
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'lafu_gift_cards_daily' );

if ( 'yes' === get_option( 'lafu_gift_cards_delete_data' ) ) {
	global $wpdb;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping the plugin's own tables on uninstall, as the shop asked.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'lafu_gc_ledger' ) );
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'lafu_gc_cards' ) );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	foreach ( array( 'lafu_gift_cards_db_version', 'lafu_gift_cards_code_prefix', 'lafu_gift_cards_validity_months', 'lafu_gift_cards_delete_data', 'lafu_gift_cards_product_id', 'lafu_gift_cards_setup_dismissed', 'lafu_gift_cards_colours', 'lafu_gift_cards_card_texts', 'lafu_gift_cards_designs_version', 'lafu_gift_cards_design_settings', 'lafu_gift_cards_any_colour', 'lafu_gift_cards_redemption', 'lafu_gift_cards_button_hide', 'lafu_gift_cards_button_label', 'lafu_gift_cards_button_url', 'lafu_gift_cards_howto_heading', 'lafu_gift_cards_howto_text', 'lafu_gift_cards_pay_new_with_existing' ) as $lafu_gift_cards_option ) {
		delete_option( $lafu_gift_cards_option );
	}
}
