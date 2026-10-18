<?php
/**
 * Gift card email (plain text).
 *
 * Override by copying to yourtheme/woocommerce/emails/plain/lafu-gift-card.php.
 *
 * @package Lafu_Gift_Cards
 *
 * @var object   $card               Card row.
 * @var string   $email_heading      Heading.
 * @var string   $additional_content Text from the email settings.
 * @var WC_Email $email              Email object.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

if ( '' !== (string) $card->sender_name ) {
	/* translators: 1: sender name, 2: shop name */
	echo esc_html( sprintf( __( '%1$s sent you a gift card to %2$s.', 'lafu-gift-cards' ), $card->sender_name, get_bloginfo( 'name' ) ) ) . "\n\n";
} else {
	/* translators: %s: shop name */
	echo esc_html( sprintf( __( 'You received a gift card to %s.', 'lafu-gift-cards' ), get_bloginfo( 'name' ) ) ) . "\n\n";
}

if ( '' !== (string) $card->message ) {
	echo esc_html( $card->message ) . "\n\n";
}

/* translators: %s: amount */
echo esc_html( sprintf( __( 'Amount: %s', 'lafu-gift-cards' ), lafu_gift_cards_plain_price( $card->initial_amount, array( 'currency' => $card->currency ) ) ) ) . "\n";
if ( Lafu_Gift_Cards_Account::email_shows_code() ) {
	/* translators: %s: gift card code */
	echo esc_html( sprintf( __( 'Code: %s', 'lafu-gift-cards' ), $card->code ) ) . "\n";
}
if ( ! empty( $card->expires_at ) ) {
	/* translators: %s: date */
	echo esc_html( sprintf( __( 'Valid until %s', 'lafu-gift-cards' ), wp_date( get_option( 'date_format' ), strtotime( $card->expires_at . ' UTC' ) ) ) ) . "\n";
}
echo "\n";

/* translators: %s: shop name */
echo esc_html( sprintf( __( 'Use the code at checkout on %s. Whatever you do not spend stays on the card for next time.', 'lafu-gift-cards' ), get_bloginfo( 'name' ) ) ) . "\n";
echo esc_url_raw( wc_get_page_permalink( 'shop' ) ) . "\n\n";

/* translators: %s: link to the gift card page */
echo esc_html( sprintf( __( 'Open your gift card: %s', 'lafu-gift-cards' ), Lafu_Gift_Cards_Account::print_url( $card ) ) ) . "\n";
/* translators: %s: link that opens the gift card ready to print */
echo esc_html( sprintf( __( 'Print or save as PDF: %s', 'lafu-gift-cards' ), Lafu_Gift_Cards_Account::print_now_url( $card ) ) ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook every email template must fire.
