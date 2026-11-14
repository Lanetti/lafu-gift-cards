<?php
/**
 * Gift card email (plain text). Same texts as the gift card page
 * (Lafu_Gift_Cards_Gift_Page::view()) and the HTML email.
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

$lafu_gift_cards_view = Lafu_Gift_Cards_Gift_Page::view( $card );
$lafu_gift_cards_use  = $lafu_gift_cards_view['use'];
$lafu_gift_cards_text = array( '= ' . $email_heading . ' =', '', $lafu_gift_cards_view['eyebrow'], $lafu_gift_cards_view['headline'], '', $lafu_gift_cards_view['intro'], '' );

if ( '' !== $lafu_gift_cards_view['message'] ) {
	$lafu_gift_cards_text[] = $lafu_gift_cards_view['message'];
	if ( '' !== $lafu_gift_cards_view['from'] ) {
		$lafu_gift_cards_text[] = $lafu_gift_cards_view['from'];
	}
	$lafu_gift_cards_text[] = '';
}

/* translators: %s: amount */
$lafu_gift_cards_text[] = sprintf( __( 'Amount: %s', 'lafu-gift-cards' ), lafu_gift_cards_plain_price( $card->initial_amount, array( 'currency' => $card->currency ) ) );
if ( Lafu_Gift_Cards_Account::email_shows_code() ) {
	/* translators: %s: gift card code */
	$lafu_gift_cards_text[] = sprintf( __( 'Code: %s', 'lafu-gift-cards' ), $card->code );
}
$lafu_gift_cards_text[] = __( 'Balance', 'lafu-gift-cards' ) . ': ' . $lafu_gift_cards_view['balance'];
$lafu_gift_cards_text[] = '';

/* translators: %s: link to the gift card page */
$lafu_gift_cards_text[] = sprintf( __( 'Open your gift card: %s', 'lafu-gift-cards' ), Lafu_Gift_Cards_Account::print_url( $card ) );
if ( $lafu_gift_cards_use['button'] ) {
	$lafu_gift_cards_text[] = $lafu_gift_cards_use['label'] . ': ' . $lafu_gift_cards_use['url'];
}
/* translators: %s: link that opens the gift card ready to print */
$lafu_gift_cards_text[] = sprintf( __( 'Print or save as PDF: %s', 'lafu-gift-cards' ), Lafu_Gift_Cards_Account::print_now_url( $card ) );
$lafu_gift_cards_text[] = '';

$lafu_gift_cards_text[] = $lafu_gift_cards_use['heading'];
foreach ( $lafu_gift_cards_use['steps'] as $lafu_gift_cards_number => $lafu_gift_cards_step ) {
	$lafu_gift_cards_text[] = ( $lafu_gift_cards_number + 1 ) . '. ' . $lafu_gift_cards_step;
}
$lafu_gift_cards_text[] = '';

if ( $additional_content ) {
	$lafu_gift_cards_text[] = wptexturize( $additional_content );
	$lafu_gift_cards_text[] = '';
}

$lafu_gift_cards_text[] = apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook every email template must fire.

// Plain text email: no HTML context, so tags are stripped and entities decoded instead of escaped.
foreach ( $lafu_gift_cards_text as $lafu_gift_cards_line ) {
	echo html_entity_decode( wp_strip_all_tags( (string) $lafu_gift_cards_line ), ENT_QUOTES, 'UTF-8' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain email body, tags stripped.
}
