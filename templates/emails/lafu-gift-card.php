<?php
/**
 * Gift card email (HTML).
 *
 * The same page the recipient opens from the button, in email form: the
 * texts, colours, buttons and steps come from Lafu_Gift_Cards_Gift_Page::view()
 * and the card from Lafu_Gift_Cards_Designs::render(), like the gift card page.
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

$lafu_gift_cards_view  = Lafu_Gift_Cards_Gift_Page::view( $card );
$lafu_gift_cards_use   = $lafu_gift_cards_view['use'];
$lafu_gift_cards_style = $lafu_gift_cards_view['style'];
$lafu_gift_cards_serif = 'font-family:Georgia,\'Times New Roman\',serif;';
$lafu_gift_cards_end   = is_rtl() ? 'left' : 'right';
$lafu_gift_cards_panel = 'celebration' === $lafu_gift_cards_style || 'card' === $lafu_gift_cards_style
	? 'background:#ffffff;border-radius:16px;padding:20px;'
	: 'background:transparent;border:1px dashed #c9b49a;border-radius:16px;padding:20px;';
$lafu_gift_cards_ghost = 'display:block;margin:10px 0 0;padding:13px 18px;border-radius:14px;border:1px solid ' . $lafu_gift_cards_view['ghost'] . ';color:' . $lafu_gift_cards_view['ghost_text'] . ';font-size:15px;font-weight:600;text-align:center;text-decoration:none;';

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook every email template must fire.
?>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="lafu-gc-email" bgcolor="<?php echo esc_attr( $lafu_gift_cards_view['page_bg'] ); ?>" style="<?php echo esc_attr( 'background-color:' . $lafu_gift_cards_view['page_bg'] . ';border-radius:18px;color:#1f1a24;margin:0 0 20px;' ); ?>">
	<?php if ( 'celebration' === $lafu_gift_cards_style ) : ?>
		<tr>
			<td align="center" bgcolor="<?php echo esc_attr( $lafu_gift_cards_view['accent'] ); ?>" style="<?php echo esc_attr( 'background-color:' . $lafu_gift_cards_view['accent'] . ';color:' . $lafu_gift_cards_view['on_accent'] . ';border-radius:18px 18px 0 0;padding:32px 24px 28px;text-align:center;' ); ?>">
				<p style="<?php echo esc_attr( 'margin:0;font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:' . $lafu_gift_cards_view['highlight'] . ';' ); ?>"><?php echo esc_html( $lafu_gift_cards_view['eyebrow'] ); ?></p>
				<p style="<?php echo esc_attr( $lafu_gift_cards_serif . 'margin:12px 0 0;font-size:32px;line-height:1.15;font-weight:700;text-align:center;color:' . $lafu_gift_cards_view['on_accent'] . ';' ); ?>"><?php echo esc_html( $lafu_gift_cards_view['headline'] ); ?></p>
				<p style="<?php echo esc_attr( 'margin:14px 0 0;font-size:16px;line-height:1.5;color:' . $lafu_gift_cards_view['on_accent'] . ';' ); ?>"><?php echo esc_html( $lafu_gift_cards_view['intro'] ); ?></p>
			</td>
		</tr>
	<?php endif; ?>
	<tr>
		<td style="padding:20px;">
			<?php if ( 'card' === $lafu_gift_cards_style ) : ?>
				<p style="<?php echo esc_attr( $lafu_gift_cards_panel . 'margin:0 0 20px;font-size:16px;line-height:1.5;text-align:center;' ); ?>"><?php echo esc_html( $lafu_gift_cards_view['intro'] ); ?></p>
			<?php endif; ?>

			<div style="margin:0 0 20px;">
				<?php echo Lafu_Gift_Cards_Designs::render( Lafu_Gift_Cards_Account::email_card( $card ), 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render(). ?>
			</div>

			<?php if ( 'letter' === $lafu_gift_cards_style ) : ?>
				<div style="<?php echo esc_attr( $lafu_gift_cards_serif . 'margin:0 0 20px;padding:24px;border-radius:4px;background:#fffdf8;font-style:italic;color:#4a3424;' ); ?>">
					<p style="margin:0 0 12px;font-size:21px;"><?php echo esc_html( $lafu_gift_cards_view['greeting'] ); ?></p>
					<p style="margin:0;font-size:17px;line-height:1.6;"><?php echo nl2br( esc_html( '' !== $lafu_gift_cards_view['message'] ? $lafu_gift_cards_view['message'] : $lafu_gift_cards_view['intro'] ) ); ?></p>
					<?php if ( '' !== $lafu_gift_cards_view['from'] ) : ?>
						<p style="<?php echo esc_attr( 'margin:16px 0 0;font-size:16px;text-align:' . $lafu_gift_cards_end . ';' ); ?>"><?php echo esc_html( $lafu_gift_cards_view['from'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php elseif ( '' !== $lafu_gift_cards_view['message'] ) : ?>
				<div style="<?php echo esc_attr( $lafu_gift_cards_panel . 'margin:0 0 20px;' ); ?>">
					<p style="<?php echo esc_attr( $lafu_gift_cards_serif . 'margin:0;font-size:19px;line-height:1.45;color:#2a2230;' ); ?>"><?php echo wp_kses_post( wptexturize( '"' . nl2br( esc_html( $lafu_gift_cards_view['message'] ) ) . '"' ) ); ?></p>
					<?php if ( '' !== $lafu_gift_cards_view['from'] ) : ?>
						<p style="margin:10px 0 0;font-size:14px;color:#5f5668;"><?php echo esc_html( $lafu_gift_cards_view['from'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div style="margin:0 0 20px;">
				<a href="<?php echo esc_url( Lafu_Gift_Cards_Account::print_url( $card ) ); ?>" style="<?php echo esc_attr( 'display:block;padding:15px 18px;border-radius:14px;background-color:' . $lafu_gift_cards_view['main_bg'] . ';color:' . $lafu_gift_cards_view['main_text'] . ';font-size:16px;font-weight:700;text-align:center;text-decoration:none;' ); ?>"><?php esc_html_e( 'Open your gift card', 'lafu-gift-cards' ); ?></a>
				<?php if ( $lafu_gift_cards_use['button'] ) : ?>
					<a href="<?php echo esc_url( $lafu_gift_cards_use['url'] ); ?>" style="<?php echo esc_attr( $lafu_gift_cards_ghost ); ?>"><?php echo esc_html( $lafu_gift_cards_use['label'] ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( Lafu_Gift_Cards_Account::print_now_url( $card ) ); ?>" style="<?php echo esc_attr( $lafu_gift_cards_ghost ); ?>"><?php esc_html_e( 'Save as PDF or print', 'lafu-gift-cards' ); ?></a>
			</div>

			<div style="<?php echo esc_attr( $lafu_gift_cards_panel ); ?>">
				<p style="margin:0 0 10px;font-size:16px;font-weight:700;color:#1f1a24;"><?php echo esc_html( $lafu_gift_cards_use['heading'] ); ?></p>
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:0;">
					<?php foreach ( $lafu_gift_cards_use['steps'] as $lafu_gift_cards_number => $lafu_gift_cards_step ) : ?>
						<tr>
							<td valign="top" width="22" style="<?php echo esc_attr( 'padding:0 0 8px;font-size:14px;line-height:1.5;font-weight:800;color:' . $lafu_gift_cards_view['step'] . ';' ); ?>"><?php echo esc_html( number_format_i18n( $lafu_gift_cards_number + 1 ) ); ?></td>
							<td valign="top" style="padding:0 0 8px;font-size:14px;line-height:1.5;color:#4a4251;"><?php echo wp_kses_post( $lafu_gift_cards_step ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:0;border-top:1px solid #efe8f3;margin-top:2px;">
					<tr>
						<td style="padding:10px 0 0;font-size:14px;color:#5f5668;"><?php esc_html_e( 'Balance', 'lafu-gift-cards' ); ?></td>
						<td align="<?php echo esc_attr( $lafu_gift_cards_end ); ?>" style="<?php echo esc_attr( 'padding:10px 0 0;font-size:14px;font-weight:700;color:#1f1a24;text-align:' . $lafu_gift_cards_end . ';' ); ?>"><?php echo esc_html( $lafu_gift_cards_view['balance'] ); ?></td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
</table>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook every email template must fire.
