<?php
/**
 * Gift card email (HTML).
 *
 * Override by copying to yourtheme/woocommerce/emails/lafu-gift-card.php.
 *
 * @package Lafu_Gift_Cards
 *
 * @var object   $card               Card row.
 * @var string   $email_heading      Heading.
 * @var string   $additional_content Text from the email settings.
 * @var WC_Email $email              Email object.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook every email template must fire.
?>

<?php if ( '' !== (string) $card->recipient_name ) : ?>
	<p>
		<?php
		/* translators: %s: recipient name */
		echo esc_html( sprintf( __( 'Hi %s,', 'lafu-gift-cards' ), $card->recipient_name ) );
		?>
	</p>
<?php endif; ?>

<p>
	<?php
	if ( '' !== (string) $card->sender_name ) {
		/* translators: 1: sender name, 2: shop name */
		echo esc_html( sprintf( __( '%1$s sent you a gift card to %2$s.', 'lafu-gift-cards' ), $card->sender_name, get_bloginfo( 'name' ) ) );
	} else {
		/* translators: %s: shop name */
		echo esc_html( sprintf( __( 'You received a gift card to %s.', 'lafu-gift-cards' ), get_bloginfo( 'name' ) ) );
	}
	?>
</p>

<?php if ( '' !== (string) $card->message ) : ?>
	<blockquote style="margin:0 0 16px;padding:12px 16px;border-left:4px solid #e4e4e7;font-style:italic;"><?php echo nl2br( esc_html( $card->message ) ); ?></blockquote>
<?php endif; ?>

<div style="margin:0 0 20px;">
	<?php echo Lafu_Gift_Cards_Designs::render( Lafu_Gift_Cards_Account::email_card( $card ), 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render(). ?>
</div>

<p>
	<?php
	if ( Lafu_Gift_Cards_Account::email_shows_code() ) {
		/* translators: %s: shop name */
		echo esc_html( sprintf( __( 'Use the code at checkout on %s. Whatever you do not spend stays on the card for next time.', 'lafu-gift-cards' ), get_bloginfo( 'name' ) ) );
	} else {
		/* translators: %s: shop name */
		echo esc_html( sprintf( __( 'Open your gift card to see the code. Use it at checkout on %s or show it at the counter. Whatever you do not spend stays on the card for next time.', 'lafu-gift-cards' ), get_bloginfo( 'name' ) ) );
	}
	?>
</p>

<p style="margin:24px 0 8px;">
	<a href="<?php echo esc_url( Lafu_Gift_Cards_Account::print_url( $card ) ); ?>" style="display:inline-block;padding:14px 26px;border-radius:12px;background:#1f1a24;color:#ffffff;font-weight:700;text-decoration:none;"><?php esc_html_e( 'Open your gift card', 'lafu-gift-cards' ); ?></a>
	<a href="<?php echo esc_url( Lafu_Gift_Cards_Account::print_now_url( $card ) ); ?>" style="display:inline-block;margin-left:8px;padding:13px 22px;border-radius:12px;border:1px solid #1f1a24;color:#1f1a24;font-weight:700;text-decoration:none;"><?php esc_html_e( 'Print or save as PDF', 'lafu-gift-cards' ); ?></a>
</p>
<p>
	<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Go to the shop', 'lafu-gift-cards' ); ?></a>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook every email template must fire.
