<?php
/**
 * The gift card page the recipient opens from the email: the card, the
 * message, how to use it, a print or PDF view and an entrance animation
 * that fits the design.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Recipient page: Celebration, Card or Letter style, chosen on the Designs page.
 */
class Lafu_Gift_Cards_Gift_Page {

	const OPTION        = 'lafu_gift_cards_gift_page';
	const PREVIEW_QUERY = 'lafu_gc_gift_preview';
	const STYLES        = array( 'celebration', 'card', 'letter' );

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'template_redirect', array( __CLASS__, 'preview' ), 1 );
	}

	/**
	 * Page settings: style (celebration, card or letter) and motion (on or off).
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'style'  => isset( $saved['style'] ) ? self::style( $saved['style'] ) : 'celebration',
			'motion' => ! isset( $saved['motion'] ) || 'no' !== $saved['motion'],
		);
	}

	/**
	 * A known page style, celebration when unknown.
	 *
	 * @param mixed $style Style key.
	 * @return string
	 */
	public static function style( $style ) {
		return in_array( $style, self::STYLES, true ) ? $style : 'celebration';
	}

	/**
	 * Save the page settings from the Designs page form.
	 *
	 * @param string $style  celebration, card or letter.
	 * @param bool   $motion Entrance animation on.
	 */
	public static function save_settings( $style, $motion ) {
		update_option(
			self::OPTION,
			array(
				'style'  => self::style( $style ),
				'motion' => $motion ? 'yes' : 'no',
			),
			false
		);
	}

	/**
	 * Pieces that fly in when the page opens: shapes and colours for a
	 * design. Designs from other plugins add their own with the filter.
	 *
	 * @param string $key   Design key.
	 * @param array  $paint Lafu_Gift_Cards_Designs::paint() result.
	 * @return array shapes (rect, circle, heart, star, snow, leaf), colors.
	 */
	public static function motif( $key, array $paint ) {
		$motifs = array(
			'christmas' => array(
				'shapes' => array( 'snow', 'snow', 'circle', 'star' ),
				'colors' => array( '#ffffff', '#f3d27a', '#e8f1ff' ),
			),
			'ribbon'    => array(
				'shapes' => array( 'rect', 'circle', 'star' ),
				'colors' => array( '#e8c36a', '#f0cf7e', '#ffffff' ),
			),
		);
		$motif  = isset( $motifs[ $key ] ) ? $motifs[ $key ] : array(
			'shapes' => array( 'rect', 'circle', 'rect' ),
			'colors' => array( $paint['accent'], '#ffd166', '#ff8fab', '#7ee0d4' ),
		);
		/**
		 * Shapes and colours that fly in on the gift card page for a design.
		 *
		 * @param array  $motif shapes: rect, circle, heart, star, snow, leaf. colors: hex list.
		 * @param string $key   Design key.
		 */
		$motif  = (array) apply_filters( 'lafu_gift_cards_gift_page_motif', $motif, $key );
		$shapes = array_values( array_intersect( (array) ( isset( $motif['shapes'] ) ? $motif['shapes'] : array() ), array( 'rect', 'circle', 'heart', 'star', 'snow', 'leaf' ) ) );
		$colors = array_values( array_filter( array_map( 'sanitize_hex_color', (array) ( isset( $motif['colors'] ) ? $motif['colors'] : array() ) ) ) );
		return array(
			'shapes' => $shapes ? $shapes : array( 'rect', 'circle' ),
			'colors' => $colors ? $colors : array( '#ffd166' ),
		);
	}

	/**
	 * The flying pieces as markup: spread over the width with fixed
	 * pseudo random values from the card, so a reload looks the same.
	 *
	 * @param array  $motif motif() result.
	 * @param string $seed  Seed text.
	 * @return string
	 */
	private static function pieces( array $motif, $seed ) {
		$paths = array(
			'heart' => '<path d="M12 21s-7.5-4.6-9.6-9.3C.9 8.2 3 4.5 6.6 4.5c2.1 0 3.6 1.2 5.4 3.1 1.8-1.9 3.3-3.1 5.4-3.1 3.6 0 5.7 3.7 4.2 7.2C19.5 16.4 12 21 12 21z" fill="currentColor"/>',
			'star'  => '<path d="M12 2l2.9 6.6 7.1.7-5.4 4.8 1.6 7L12 17.4 5.8 21l1.6-7L2 9.3l7.1-.7z" fill="currentColor"/>',
			'snow'  => '<path d="M12 2v20M3.3 7l17.4 10M3.3 17L20.7 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" fill="none"/>',
			'leaf'  => '<path d="M5 19C5 9 11 4 20 4c0 9-5 15-15 15z" fill="currentColor"/>',
		);
		$html  = '';
		$hash  = md5( $seed );
		for ( $i = 0; $i < 36; $i++ ) {
			$n     = hexdec( substr( md5( $hash . $i ), 0, 8 ) );
			$shape = $motif['shapes'][ $n % count( $motif['shapes'] ) ];
			$color = $motif['colors'][ intdiv( $n, 7 ) % count( $motif['colors'] ) ];
			$left  = ( $i * 97 + $n % 23 ) % 100;
			$delay = ( $n % 900 ) / 1000;
			$time  = 2.2 + ( $n % 1300 ) / 1000;
			$size  = 8 + $n % 10;
			$spin  = ( $n % 2 ? '' : '-' ) . ( 180 + $n % 540 );
			$drift = ( $n % 3 - 1 ) * ( 20 + $n % 40 );
			$style = sprintf( '--x:%d%%;--d:%.2fs;--t:%.2fs;--s:%dpx;--r:%sdeg;--w:%dpx;color:%s', $left, $delay, $time, $size, $spin, $drift, $color );
			if ( isset( $paths[ $shape ] ) ) {
				$html .= '<svg class="lafu-gp-piece" viewBox="0 0 24 24" style="' . esc_attr( $style ) . '">' . $paths[ $shape ] . '</svg>';
			} else {
				$html .= '<i class="lafu-gp-piece lafu-gp-piece--' . esc_attr( $shape ) . '" style="' . esc_attr( $style ) . '"></i>';
			}
		}
		return '<div class="lafu-gp-burst" aria-hidden="true">' . $html . '</div>';
	}

	/**
	 * Admin preview with a sample card: ?lafu_gc_gift_preview=celebration|card|letter&design=key.
	 */
	public static function preview() {
		if ( ! isset( $_GET[ self::PREVIEW_QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview for shop managers.
			return;
		}
		if ( ! current_user_can( Lafu_Gift_Cards_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'lafu-gift-cards' ), '', array( 'response' => 403 ) );
		}
		$style   = sanitize_key( wp_unslash( $_GET[ self::PREVIEW_QUERY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview.
		$offered = Lafu_Gift_Cards_Designs::offered();
		$design  = isset( $_GET['design'] ) ? sanitize_key( wp_unslash( $_GET['design'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview.
		if ( ! isset( $offered[ $design ] ) ) {
			$design = isset( $offered['birthday'] ) ? 'birthday' : (string) key( $offered );
		}
		$card = (object) array(
			'id'              => 0,
			'code'            => 'ABCD-EFGH-JKLM-NPQR',
			'design'          => $design,
			'initial_amount'  => 50,
			'balance'         => 50,
			'currency'        => get_woocommerce_currency(),
			'status'          => Lafu_Gift_Cards_Store::STATUS_ACTIVE,
			'recipient_name'  => __( 'Anna', 'lafu-gift-cards' ),
			'recipient_email' => '',
			'sender_name'     => __( 'Mikko', 'lafu-gift-cards' ),
			'message'         => __( 'Happy birthday! Buy yourself something nice.', 'lafu-gift-cards' ),
			'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) ),
			'created_at'      => current_time( 'mysql', true ),
			'meta'            => '',
			'colour'          => Lafu_Gift_Cards_Designs::colours()[0],
		);
		self::output( $card, self::style( $style ) );
	}

	/**
	 * Send the page for a card and stop.
	 *
	 * @param object      $card  Card row (or sample).
	 * @param string|null $style celebration, card or letter; null = the shop's setting.
	 */
	public static function output( $card, $style = null ) {
		$settings = self::settings();
		$style    = null === $style ? $settings['style'] : self::style( $style );
		$texts    = Lafu_Gift_Cards_Designs::card_texts( $card );
		$paint    = Lafu_Gift_Cards_Designs::paint( $card->design, $card );
		$active   = Lafu_Gift_Cards_Store::STATUS_ACTIVE === $card->status && ! Lafu_Gift_Cards_Store::is_past( $card->expires_at );
		$festive  = $active && (float) $card->balance > 0;
		$money    = array( 'currency' => $card->currency );
		$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$host     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$name     = trim( (string) $card->recipient_name );
		$sender   = trim( (string) $card->sender_name );
		$headline = '' !== trim( (string) $texts['line'] ) ? $texts['line'] : $texts['label'];
		$intro    = '' !== $sender
			/* translators: 1: sender name, 2: shop name */
			? sprintf( __( '%1$s sent you a gift card to %2$s.', 'lafu-gift-cards' ), $sender, $texts['shop'] )
			/* translators: %s: shop name */
			: sprintf( __( 'You received a gift card to %s.', 'lafu-gift-cards' ), $texts['shop'] );
		$until   = empty( $card->expires_at ) ? __( 'No expiry', 'lafu-gift-cards' ) : Lafu_Gift_Cards_Designs::fill( $texts['until'], 'date', Lafu_Gift_Cards_Account::expiry_label( $card ) );
		$on_dark = Lafu_Gift_Cards_Designs::is_light( $paint['text'] );

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		lafu_gift_cards_register_assets();
		wp_register_style( 'lafu-gift-cards-gift-page', plugins_url( 'assets/gift-page.css', LAFU_GIFT_CARDS_FILE ), array( 'lafu-gift-cards-card' ), LAFU_GIFT_CARDS_VERSION );
		$classes = array( 'lafu-gp', 'lafu-gp--' . $style, $on_dark ? 'lafu-gp--on-dark' : 'lafu-gp--on-light' );
		if ( $settings['motion'] && $festive ) {
			$classes[] = 'lafu-gp--motion';
		}
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $texts['label'] . ' · ' . $texts['shop'] ); ?></title>
		<?php wp_print_styles( 'lafu-gift-cards-gift-page' ); ?>
</head>
<body class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( '--gp-accent:' . $paint['solid'] . ';--gp-on-accent:' . $paint['text'] . ';--gp-highlight:' . $paint['accent'] ); ?>">
		<?php
		if ( $settings['motion'] && $festive ) {
			echo self::pieces( self::motif( (string) $card->design, $paint ), $card->code . $card->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from fixed SVG paths and escaped styles.
		}
		?>
<main class="lafu-gp-page">
	<header class="lafu-gp-hero">
		<div class="lafu-gp-bar">
			<span class="lafu-gp-shop"><?php echo esc_html( $texts['shop'] ); ?></span>
			<?php if ( $active ) : ?>
				<a class="lafu-gp-bar-link" href="#lafu-gp-how"><?php esc_html_e( 'Check balance', 'lafu-gift-cards' ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( 'celebration' === $style ) : ?>
			<div class="lafu-gp-greeting">
				<p class="lafu-gp-eyebrow"><?php echo esc_html( '' !== $name ? Lafu_Gift_Cards_Designs::fill( $texts['for'], 'name', $name ) : __( 'A gift for you', 'lafu-gift-cards' ) ); ?></p>
				<h1><?php echo esc_html( $headline ); ?></h1>
				<p class="lafu-gp-intro"><?php echo esc_html( $intro ); ?></p>
			</div>
		<?php endif; ?>
	</header>

	<div class="lafu-gp-body">
		<?php if ( 'card' === $style || ( 'letter' === $style && ! $active ) ) : ?>
			<p class="lafu-gp-intro lafu-gp-intro--card"><?php echo esc_html( $intro ); ?></p>
		<?php endif; ?>

		<?php if ( ! $active ) : ?>
			<div class="lafu-gp-panel lafu-gp-status">
				<h1><?php echo esc_html( Lafu_Gift_Cards_Account::status_label( $card ) ); ?></h1>
				<?php /* translators: %s: shop address */ ?>
				<p><?php echo esc_html( sprintf( __( 'Questions? Contact %s.', 'lafu-gift-cards' ), $host ) ); ?></p>
			</div>
		<?php else : ?>
			<?php if ( 'letter' === $style ) : ?>
				<div class="lafu-gp-envelope">
					<div class="lafu-gp-card">
						<?php echo Lafu_Gift_Cards_Designs::render( $card, 'web' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render(). ?>
					</div>
					<span class="lafu-gp-envelope__front" aria-hidden="true"></span>
					<span class="lafu-gp-envelope__seal" aria-hidden="true"></span>
				</div>
				<figure class="lafu-gp-letter">
					<?php /* translators: %s: recipient name */ ?>
					<p class="lafu-gp-letter__to"><?php echo esc_html( '' !== $name ? sprintf( __( 'Dear %s,', 'lafu-gift-cards' ), $name ) : __( 'Hello,', 'lafu-gift-cards' ) ); ?></p>
					<blockquote><?php echo esc_html( '' !== trim( (string) $card->message ) ? $card->message : $intro ); ?></blockquote>
					<?php if ( '' !== $sender ) : ?>
						<figcaption><?php echo esc_html( Lafu_Gift_Cards_Designs::fill( $texts['from'], 'name', $sender ) ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php else : ?>
				<div class="lafu-gp-card">
					<?php echo Lafu_Gift_Cards_Designs::render( $card, 'web' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render(). ?>
				</div>
			<?php endif; ?>

			<?php if ( 'letter' !== $style && '' !== trim( (string) $card->message ) ) : ?>
				<figure class="lafu-gp-panel lafu-gp-message">
					<blockquote><?php echo esc_html( $card->message ); ?></blockquote>
					<?php if ( '' !== $sender ) : ?>
						<figcaption><?php echo esc_html( Lafu_Gift_Cards_Designs::fill( $texts['from'], 'name', $sender ) ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>

			<div class="lafu-gp-actions">
				<a class="lafu-gp-button lafu-gp-button--main" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Shop with your gift card', 'lafu-gift-cards' ); ?></a>
				<button type="button" class="lafu-gp-button lafu-gp-button--ghost" data-lafu-gp-print>
					<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
					<?php esc_html_e( 'Save as PDF or print', 'lafu-gift-cards' ); ?>
				</button>
			</div>

			<section id="lafu-gp-how" class="lafu-gp-panel lafu-gp-how">
				<h2><?php esc_html_e( 'How to use it', 'lafu-gift-cards' ); ?></h2>
				<?php /* translators: %s: shop address */ ?>
				<p><span>1</span><?php echo esc_html( sprintf( __( 'Online: enter the code at checkout on %s. What you do not spend stays on the card for next time.', 'lafu-gift-cards' ), $host ) ); ?></p>
				<p><span>2</span><?php esc_html_e( 'In the shop: show the QR code at the counter.', 'lafu-gift-cards' ); ?></p>
				<div class="lafu-gp-balance">
					<span><?php esc_html_e( 'Balance', 'lafu-gift-cards' ); ?></span>
					<strong><?php echo esc_html( lafu_gift_cards_plain_price( $card->balance, $money ) . ' · ' . $until ); ?></strong>
				</div>
			</section>
		<?php endif; ?>

		<p class="lafu-gp-foot"><?php echo esc_html( $texts['shop'] . ' · ' . $host ); ?></p>
	</div>
</main>
		<?php wp_print_inline_script_tag( 'var b=document.querySelector("[data-lafu-gp-print]");if(b){b.addEventListener("click",function(){window.print();});}if(b&&/[?&]print=1(&|$)/.test(location.search)){window.addEventListener("load",function(){setTimeout(function(){window.print();},700);});}' ); ?>
</body>
</html>
		<?php
		exit;
	}
}
