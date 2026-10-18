<?php
/**
 * Card designs and the card renderer.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Three kinds of design:
 *
 * - Colour: one card in the shop's own colour, chosen on the product.
 * - Illustrated: flat vector art for an occasion (Lafu_Gift_Cards_Art),
 *   drawn so the text and QR code stay readable.
 * - Image: a picture with a position, a size and a veil, for example a
 *   brand design added by an extension.
 *
 * Everything is CSS and inline SVG, so the plugin ships no heavy images.
 * More designs plug in through the lafu_gift_cards_designs filter.
 */
class Lafu_Gift_Cards_Designs {

	/** Width / height of the card, also the viewBox of the art. */
	const ART_VIEWBOX = '0 0 1586 1000';

	/**
	 * Default card colours, used until the shop saves its own.
	 *
	 * @return array Hex colours.
	 */
	public static function default_colours() {
		return array( '#1d2b45', '#b23c2c', '#0f5132', '#5b2a86', '#c2185b', '#0e7490', '#b7791f', '#18181b' );
	}

	/**
	 * The shop's card colours.
	 *
	 * @return array Hex colours, at least one.
	 */
	public static function colours() {
		$saved   = get_option( 'lafu_gift_cards_colours', array() );
		$colours = array_values( array_filter( array_map( 'sanitize_hex_color', is_array( $saved ) ? $saved : array() ) ) );
		return $colours ? $colours : self::default_colours();
	}

	/**
	 * Card texts the shop can reword or translate. {name} and {date} are
	 * filled in on each card.
	 *
	 * @return array label, for, from, until.
	 */
	public static function texts() {
		$defaults = array(
			'label' => __( 'Gift card', 'lafu-gift-cards' ),
			'shop'  => get_bloginfo( 'name' ),
			'line'  => '',
			/* translators: {name}: recipient name, keep the braces */
			'for'   => __( 'For {name}', 'lafu-gift-cards' ),
			/* translators: {name}: sender name, keep the braces */
			'from'  => __( 'From {name}', 'lafu-gift-cards' ),
			/* translators: {date}: expiry date, keep the braces */
			'until' => __( 'Valid until {date}', 'lafu-gift-cards' ),
		);

		$saved = get_option( 'lafu_gift_cards_card_texts', array() );
		foreach ( $defaults as $key => $text ) {
			if ( is_array( $saved ) && isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] ) ) {
				$defaults[ $key ] = Lafu_Gift_Cards_I18n::phrase( (string) $saved[ $key ] );
			}
		}
		return $defaults;
	}

	/**
	 * Fill {name} or {date} into a card text; without the token the value
	 * goes after the text.
	 *
	 * @param string $text  Card text.
	 * @param string $token name or date.
	 * @param string $value Value.
	 * @return string
	 */
	public static function fill( $text, $token, $value ) {
		$tag = '{' . $token . '}';
		return false !== strpos( $text, $tag ) ? str_replace( $tag, $value, $text ) : trim( $text . ' ' . $value );
	}

	/**
	 * The texts of one card: the shop's texts, with the heading, name and
	 * line the card was sold with (from its product or the counter) on top.
	 *
	 * @param object $card Card row or preview object.
	 * @return array Same keys as texts().
	 */
	public static function card_texts( $card ) {
		$texts = self::texts();
		$meta  = isset( $card->meta ) ? Lafu_Gift_Cards_Store::meta( $card ) : array();
		foreach ( array( 'label', 'shop', 'line' ) as $key ) {
			if ( isset( $meta[ $key ] ) && '' !== trim( (string) $meta[ $key ] ) ) {
				$texts[ $key ] = Lafu_Gift_Cards_I18n::phrase( (string) $meta[ $key ] );
			}
		}
		return $texts;
	}

	/**
	 * Clean card text overrides for a card's meta: label, shop and line,
	 * empty ones left out.
	 *
	 * @param array $texts Raw texts.
	 * @return array
	 */
	public static function clean_texts( array $texts ) {
		$out = array();
		foreach ( array( 'label', 'shop', 'line' ) as $key ) {
			$value = isset( $texts[ $key ] ) ? trim( sanitize_text_field( (string) $texts[ $key ] ) ) : '';
			if ( '' !== $value ) {
				$out[ $key ] = mb_substr( $value, 0, 60 );
			}
		}
		return $out;
	}

	/**
	 * Every design, including older ones that only old cards use.
	 * Key => label, background, background2, text, accent, and optionally
	 * pattern (SVG), art (SVG), image (URL) with position, size and veil,
	 * colour (true for the colour card) and legacy (true: not offered).
	 *
	 * @return array
	 */
	public static function all() {
		$art     = Lafu_Gift_Cards_Art::all();
		$designs = array(
			'colour'    => array(
				'label'       => __( 'Colour', 'lafu-gift-cards' ),
				'background'  => '#1d2b45',
				'background2' => '#33476b',
				'text'        => '#ffffff',
				'accent'      => '#d6b25e',
				'colour'      => true,
			),
			'ribbon'    => array(
				'label'       => __( 'Gift ribbon', 'lafu-gift-cards' ),
				'background'  => '#17233b',
				'background2' => '#2c4068',
				'text'        => '#ffffff',
				'accent'      => '#e8c36a',
				'art'         => $art['ribbon'],
			),
			'christmas' => array(
				'label'       => __( 'Christmas', 'lafu-gift-cards' ),
				'background'  => '#7d1a1f',
				'background2' => '#b3282e',
				'text'        => '#ffffff',
				'accent'      => '#f3d27a',
				'art'         => $art['christmas'],
			),
			'classic'   => array(
				'label'       => __( 'Classic', 'lafu-gift-cards' ),
				'background'  => '#1d2b45',
				'background2' => '#33476b',
				'text'        => '#ffffff',
				'accent'      => '#d6b25e',
				'pattern'     => '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><path d="M0 40h80M40 0v80" stroke="#ffffff" stroke-opacity=".05"/></svg>',
				'legacy'      => true,
			),
			'warm'      => array(
				'label'       => __( 'Warm', 'lafu-gift-cards' ),
				'background'  => '#b23c2c',
				'background2' => '#c4502a',
				'text'        => '#ffffff',
				'accent'      => '#fff3e0',
				'pattern'     => '<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60"><circle cx="30" cy="30" r="18" fill="none" stroke="#ffffff" stroke-opacity=".12"/></svg>',
				'legacy'      => true,
			),
			'minimal'   => array(
				'label'       => __( 'Minimal', 'lafu-gift-cards' ),
				'background'  => '#ffffff',
				'background2' => '#f4f4f5',
				'text'        => '#18181b',
				'accent'      => '#18181b',
				'legacy'      => true,
			),
		);

		/**
		 * Add or change card designs.
		 *
		 * @param array $designs Key => label, background, background2, text, accent and
		 *                       optionally pattern, art, image, position, size, veil, legacy.
		 */
		return (array) apply_filters( 'lafu_gift_cards_designs', $designs );
	}

	/**
	 * Designs a shop can pick for new cards (all but the legacy ones).
	 *
	 * @return array Key => design.
	 */
	public static function offered() {
		return array_filter(
			self::all(),
			static function ( $design ) {
				return empty( $design['legacy'] );
			}
		);
	}

	/**
	 * One design, falling back to the first offered one.
	 *
	 * @param string $key Design key.
	 * @return array
	 */
	public static function get( $key ) {
		$all = self::all();
		return $all[ self::resolve( $key ) ];
	}

	/**
	 * Key of the design that get() uses.
	 *
	 * @param string $key Wanted design.
	 * @return string
	 */
	public static function resolve( $key ) {
		$all = self::all();
		if ( isset( $all[ $key ] ) ) {
			return $key;
		}
		$offered = self::offered();
		return (string) key( $offered ? $offered : $all );
	}

	/**
	 * A hex colour moved toward black (negative) or white (positive).
	 *
	 * @param string $hex    #rrggbb.
	 * @param float  $amount -1 to 1.
	 * @return string
	 */
	public static function shade( $hex, $amount ) {
		$hex = ltrim( (string) sanitize_hex_color( $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return '#000000';
		}
		$out = '#';
		foreach ( str_split( $hex, 2 ) as $part ) {
			$value = hexdec( $part );
			$value = $amount < 0 ? $value * ( 1 + $amount ) : $value + ( 255 - $value ) * $amount;
			$out  .= str_pad( dechex( (int) round( max( 0, min( 255, $value ) ) ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}

	/**
	 * Whether a colour is light, so the text on it should be dark.
	 *
	 * @param string $hex #rrggbb.
	 * @return bool
	 */
	public static function is_light( $hex ) {
		$hex = ltrim( (string) sanitize_hex_color( $hex ), '#' );
		if ( 6 !== strlen( $hex ) ) {
			return false;
		}
		list( $r, $g, $b ) = array_map( 'hexdec', str_split( $hex, 2 ) );
		return ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) > 170;
	}

	/**
	 * Shop colours chosen per design in the designer: design key => #rrggbb.
	 *
	 * @return array
	 */
	public static function design_colours() {
		$saved = get_option( 'lafu_gift_cards_design_colours', array() );
		$out   = array();
		foreach ( is_array( $saved ) ? $saved : array() as $key => $hex ) {
			$hex = sanitize_hex_color( (string) $hex );
			if ( $hex ) {
				$out[ sanitize_key( $key ) ] = $hex;
			}
		}
		return $out;
	}

	/**
	 * A design in the shop's colour: the base colour changes, the art stays.
	 * The Colour design takes the card's own colour first (chosen on the
	 * product), then the shop colour. Photo designs keep their picture.
	 *
	 * @param array  $design Design.
	 * @param string $key    Design key.
	 * @param string $chosen Colour chosen for this card (Colour design only).
	 * @return array
	 */
	public static function with_colour( array $design, $key, $chosen = '' ) {
		if ( ! empty( $design['image'] ) ) {
			return $design;
		}
		$colours = self::design_colours();
		$shop    = isset( $colours[ $key ] ) ? $colours[ $key ] : '';
		if ( ! empty( $design['colour'] ) ) {
			return array_merge( $design, self::colour_set( sanitize_hex_color( (string) $chosen ) ? $chosen : $shop ) );
		}
		return '' === $shop ? $design : array_merge( $design, self::colour_set( $shop ) );
	}

	/**
	 * Colours of the colour card for one base colour.
	 *
	 * @param string $base #rrggbb.
	 * @return array background, background2, text, accent.
	 */
	public static function colour_set( $base ) {
		$base  = sanitize_hex_color( $base ) ? sanitize_hex_color( $base ) : self::colours()[0];
		$light = self::is_light( $base );
		return array(
			'background'  => $base,
			'background2' => self::shade( $base, $light ? -0.12 : 0.18 ),
			'text'        => $light ? '#18181b' : '#ffffff',
			'accent'      => $light ? self::shade( $base, -0.6 ) : self::shade( $base, 0.7 ),
		);
	}

	/**
	 * The illustration as a CSS background layer.
	 *
	 * @param string $svg Art markup without the outer svg element.
	 * @return string
	 */
	public static function art_layer( $svg ) {
		$markup = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . self::ART_VIEWBOX . '" preserveAspectRatio="xMidYMid slice">' . $svg . '</svg>';
		return 'url("data:image/svg+xml,' . rawurlencode( $markup ) . '") center/cover no-repeat';
	}

	/**
	 * Everything needed to paint a card: colours and the CSS background.
	 *
	 * @param string      $key  Design key.
	 * @param object|null $card Card row or preview object (for its chosen colour).
	 * @return array background (CSS), art (the layers over the colour), solid, text, accent, light, colour (bool).
	 */
	public static function paint( $key, $card = null ) {
		$design = self::get( $key );
		$chosen = '';
		if ( ! empty( $design['colour'] ) ) {
			$meta   = $card && isset( $card->meta ) ? Lafu_Gift_Cards_Store::meta( $card ) : array();
			$chosen = isset( $meta['colour'] ) ? $meta['colour'] : ( isset( $card->colour ) ? $card->colour : '' );
		}
		$design = self::with_colour( $design, (string) $key, (string) $chosen );
		$layers = array();
		if ( ! empty( $design['image'] ) ) {
			$veil     = isset( $design['veil'] ) ? max( 0, min( 0.8, (float) $design['veil'] ) ) : 0.3;
			$size     = isset( $design['size'] ) ? preg_replace( '/[^0-9.% a-z]/', '', (string) $design['size'] ) : 'cover';
			$position = isset( $design['position'] ) ? preg_replace( '/[^0-9.% ]/', '', (string) $design['position'] ) : '50% 50%';
			$tint     = self::is_light( $design['text'] ) ? '0,0,0' : '255,255,255';
			$layers[] = 'linear-gradient(90deg,rgba(' . $tint . ',' . $veil . '),rgba(' . $tint . ',' . round( $veil * 0.35, 3 ) . '))';
			$layers[] = 'url("' . esc_url_raw( $design['image'] ) . '") ' . $position . '/' . $size . ' no-repeat';
		}
		if ( ! empty( $design['art'] ) ) {
			$layers[] = self::art_layer( $design['art'] );
		}
		if ( ! empty( $design['pattern'] ) ) {
			$layers[] = 'url("data:image/svg+xml,' . rawurlencode( $design['pattern'] ) . '")';
		}
		$art      = implode( ',', $layers );
		$layers[] = 'linear-gradient(135deg,' . $design['background'] . ',' . $design['background2'] . ')';
		return array(
			'background' => implode( ',', $layers ),
			'art'        => $art,
			'solid'      => $design['background'],
			'text'       => $design['text'],
			'accent'     => $design['accent'],
			'light'      => self::is_light( $design['background'] ) && empty( $design['image'] ),
			'colour'     => ! empty( $design['colour'] ),
		);
	}

	/**
	 * The card as one standalone SVG (1586 x 1000), for a product image made
	 * in the browser: drawn on a canvas and saved as JPEG. Same colours, art
	 * and text sizes as the CSS card, and nothing outside the SVG itself,
	 * because an SVG drawn as an image loads no other files.
	 *
	 * @param string $key    Design key.
	 * @param string $colour Chosen colour for the Colour design (hex) or "".
	 * @param array  $texts  label, shop, line and amount (plain text).
	 * @return string SVG markup.
	 */
	public static function card_svg( $key, $colour, array $texts ) {
		$design = self::with_colour( self::get( $key ), (string) $key, ! empty( self::get( $key )['colour'] ) ? (string) $colour : '' );
		$w      = 1586;
		$h      = 1000;
		$pad    = 110;
		$esc    = static function ( $value ) {
			return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
		};
		$colour = static function ( $value, $fallback ) {
			$hex = sanitize_hex_color( (string) $value );
			return $hex ? $hex : $fallback;
		};
		$bg1    = $colour( $design['background'], '#1d2b45' );
		$bg2    = $colour( $design['background2'], $bg1 );
		$text   = $colour( $design['text'], '#ffffff' );
		$accent = $colour( $design['accent'], $text );
		$font   = 'system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif';

		$svg   = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">';
		$svg  .= '<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $bg1 . '"/><stop offset="1" stop-color="' . $bg2 . '"/></linearGradient>';
		$svg  .= '<linearGradient id="shine" x1="0" y1="0" x2="1" y2=".45"><stop offset="0" stop-color="#ffffff" stop-opacity=".18"/><stop offset=".42" stop-color="#ffffff" stop-opacity="0"/></linearGradient></defs>';
		$svg  .= '<rect width="' . $w . '" height="' . $h . '" fill="url(#bg)"/>';
		$photo = ! empty( $design['image'] ) ? self::inline_image( $design['image'] ) : '';
		if ( '' !== $photo ) {
			$veil = isset( $design['veil'] ) ? max( 0, min( 0.8, (float) $design['veil'] ) ) : 0.3;
			$tint = self::is_light( $text ) ? '#000000' : '#ffffff';
			$svg .= '<image width="' . $w . '" height="' . $h . '" preserveAspectRatio="xMidYMid slice" href="' . $esc( $photo ) . '" xlink:href="' . $esc( $photo ) . '"/>';
			$svg .= '<defs><linearGradient id="veil" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="' . $tint . '" stop-opacity="' . $veil . '"/><stop offset="1" stop-color="' . $tint . '" stop-opacity="' . round( $veil * 0.35, 3 ) . '"/></linearGradient></defs>';
			$svg .= '<rect width="' . $w . '" height="' . $h . '" fill="url(#veil)"/>';
		}
		if ( ! empty( $design['art'] ) ) {
			$svg .= '<svg x="0" y="0" width="' . $w . '" height="' . $h . '" viewBox="' . self::ART_VIEWBOX . '" preserveAspectRatio="xMidYMid slice">' . $design['art'] . '</svg>';
		}
		$svg .= '<rect width="' . $w . '" height="' . $h . '" fill="url(#shine)"/>';

		$label = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( (string) $texts['label'] ) : strtoupper( (string) $texts['label'] );
		$y     = $pad + 37;
		$svg  .= '<text x="' . $pad . '" y="' . $y . '" fill="' . $accent . '" font-family="' . $font . '" font-size="37" font-weight="700" letter-spacing="5">' . $esc( $label ) . '</text>';
		$y    += 68;
		$svg  .= '<text x="' . $pad . '" y="' . $y . '" fill="' . $text . '" font-family="' . $font . '" font-size="52" font-weight="600">' . $esc( $texts['shop'] ) . '</text>';
		if ( '' !== trim( (string) $texts['line'] ) ) {
			$y   += 66;
			$svg .= '<text x="' . $pad . '" y="' . $y . '" fill="' . $text . '" fill-opacity=".9" font-family="' . $font . '" font-size="43">' . $esc( $texts['line'] ) . '</text>';
		}
		$svg .= '<text x="' . $pad . '" y="' . ( $h - $pad - 70 ) . '" fill="' . $text . '" font-family="' . $font . '" font-size="140" font-weight="800" letter-spacing="-1">' . $esc( $texts['amount'] ) . '</text>';
		return $svg . '</svg>';
	}

	/**
	 * A design picture from this site's uploads as a data URL, so it shows
	 * inside an SVG drawn as an image. Other addresses are left out.
	 *
	 * @param string $url Picture URL.
	 * @return string Data URL or "".
	 */
	private static function inline_image( $url ) {
		$uploads = wp_get_upload_dir();
		$base    = trailingslashit( $uploads['baseurl'] );
		$url     = (string) $url;
		if ( 0 !== strpos( set_url_scheme( $url, 'https' ), set_url_scheme( $base, 'https' ) ) ) {
			return '';
		}
		$relative = substr( set_url_scheme( $url, 'https' ), strlen( set_url_scheme( $base, 'https' ) ) );
		$path     = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . rawurldecode( $relative ) );
		$root     = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
		if ( 0 !== strpos( $path, $root ) || false !== strpos( $relative, '..' ) || ! is_readable( $path ) || filesize( $path ) > 8 * MB_IN_BYTES ) {
			return '';
		}
		$type = wp_check_filetype( $path );
		if ( ! in_array( $type['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return '';
		}
		return 'data:' . $type['type'] . ';base64,' . base64_encode( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- A local upload as a data URL for the card image.
	}

	/**
	 * The card as HTML. The email version uses only tables and solid
	 * colours, which every mail client shows; the web version adds the
	 * gradient, art and an SVG QR code (styled in assets/card.css).
	 *
	 * @param object $card    Card row (or a preview object with the same fields).
	 * @param string $context web or email.
	 * @return string Escaped HTML.
	 */
	public static function render( $card, $context = 'web' ) {
		$paint = self::paint( $card->design, $card );
		$texts = self::card_texts( $card );
		$shop  = $texts['shop'];
		/**
		 * Amount shown on the card (HTML).
		 *
		 * @param string $amount  Formatted amount.
		 * @param object $card    Card row.
		 */
		$amount  = apply_filters( 'lafu_gift_cards_card_amount_html', wc_price( $card->initial_amount, array( 'currency' => $card->currency ) ), $card );
		$line    = $texts['line'];
		$expires = empty( $card->expires_at ) ? '' : self::fill( $texts['until'], 'date', wp_date( get_option( 'date_format' ), strtotime( $card->expires_at . ' UTC' ) ) );
		$to      = '' !== (string) $card->recipient_name ? self::fill( $texts['for'], 'name', $card->recipient_name ) : '';
		$from    = '' !== (string) $card->sender_name ? self::fill( $texts['from'], 'name', $card->sender_name ) : '';

		$text   = $paint['text'];
		$accent = $paint['accent'];
		$code   = (string) $card->code;

		if ( 'email' === $context ) {
			$box = 'background-color:' . $paint['solid'] . ';color:' . $text . ';border-radius:14px;padding:26px;font-family:Helvetica,Arial,sans-serif;' . ( $paint['light'] ? 'border:1px solid #e4e4e7;' : '' );
			/** This filter is documented below. */
			$box = (string) apply_filters( 'lafu_gift_cards_card_style', $box, $card, 'email' );
			$qr  = '' !== $code ? Lafu_Gift_Cards_Qr::html_table( $code, 2 ) : '';
			ob_start();
			?>
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="lafu-gc-card" style="<?php echo esc_attr( $box ); ?>">
				<tr>
					<td style="padding:0 0 4px;color:<?php echo esc_attr( $accent ); ?>;font-size:12px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;"><?php echo esc_html( $texts['label'] ); ?></td>
				</tr>
				<tr>
					<td style="color:<?php echo esc_attr( $text ); ?>;font-size:17px;font-weight:600;padding:0 0 <?php echo '' !== $line ? '4' : '14'; ?>px;"><?php echo esc_html( $shop ); ?></td>
				</tr>
				<?php if ( '' !== $line ) : ?>
					<tr>
						<td style="color:<?php echo esc_attr( $text ); ?>;font-size:14px;padding:0 0 14px;"><?php echo esc_html( $line ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<td style="color:<?php echo esc_attr( $text ); ?>;font-size:38px;font-weight:800;line-height:1.1;padding:0 0 18px;"><?php echo wp_kses_post( $amount ); ?></td>
				</tr>
				<tr>
					<td style="padding:0;">
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:0;">
							<tr>
								<td valign="bottom" style="padding:0;color:<?php echo esc_attr( $text ); ?>;">
									<?php if ( $to || $from ) : ?>
										<div style="font-size:14px;padding-bottom:6px;"><?php echo esc_html( trim( $to . ( $to && $from ? ' · ' : '' ) . $from ) ); ?></div>
									<?php endif; ?>
									<div style="font-family:Menlo,Consolas,monospace;font-size:18px;font-weight:700;letter-spacing:.06em;"><?php echo esc_html( $code ); ?></div>
									<?php if ( $expires ) : ?>
										<div style="font-size:12px;padding-top:6px;"><?php echo esc_html( $expires ); ?></div>
									<?php endif; ?>
								</td>
								<?php if ( $qr ) : ?>
									<td valign="bottom" align="right" width="70" style="padding:0 0 0 12px;"><?php echo $qr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Table built from booleans in Lafu_Gift_Cards_Qr, no user data. ?></td>
								<?php endif; ?>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<?php
			return (string) ob_get_clean();
		}

		$box = '--lafu-gc-card-bg:' . $paint['solid'] . ';background:' . $paint['background'] . ';color:' . $text . ';' . ( $paint['light'] ? 'border:1px solid #e4e4e7;' : '' );

		/**
		 * Inline style of the web and print card, for example a photo
		 * background. Escaped with esc_attr on output.
		 *
		 * @param string $box     CSS declarations.
		 * @param object $card    Card row.
		 * @param string $context web or email.
		 */
		$box = (string) apply_filters( 'lafu_gift_cards_card_style', $box, $card, 'web' );
		$qr  = '' !== $code ? Lafu_Gift_Cards_Qr::svg( $code, 72 ) : '';

		ob_start();
		?>
		<div class="lafu-gc-card" style="<?php echo esc_attr( $box ); ?>">
			<div class="lafu-gc-card__top">
				<span class="lafu-gc-card__label" style="color:<?php echo esc_attr( $accent ); ?>"><?php echo esc_html( $texts['label'] ); ?></span>
				<span class="lafu-gc-card__shop"><?php echo esc_html( $shop ); ?></span>
				<span class="lafu-gc-card__line" data-lafu-gc-preview-line><?php echo esc_html( $line ); ?></span>
			</div>
			<div class="lafu-gc-card__amount" data-lafu-gc-preview-amount><?php echo wp_kses_post( $amount ); ?></div>
			<div class="lafu-gc-card__bottom">
				<div class="lafu-gc-card__meta">
					<div class="lafu-gc-card__people" data-lafu-gc-preview-people><?php echo esc_html( trim( $to . ( $to && $from ? ' · ' : '' ) . $from ) ); ?></div>
					<?php if ( '' !== $code ) : ?>
						<div class="lafu-gc-card__code"><?php echo esc_html( $code ); ?></div>
					<?php endif; ?>
					<?php if ( $expires ) : ?>
						<div class="lafu-gc-card__expires"><?php echo esc_html( $expires ); ?></div>
					<?php endif; ?>
				</div>
				<?php if ( $qr ) : ?>
					<div class="lafu-gc-card__qr"><?php echo $qr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built by Lafu_Gift_Cards_Qr with escaped attributes. ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Style of a small card thumbnail in a design picker.
	 *
	 * @param string $key Design key.
	 * @return string CSS.
	 */
	public static function swatch_style( $key ) {
		$paint = self::paint( $key );
		$style = 'background:' . $paint['background'] . ';color:' . $paint['text'] . ';';
		if ( $paint['light'] ) {
			$style .= 'border-color:#d4d4d8;';
		}
		return $style;
	}

	/**
	 * What the buyer form preview needs to repaint the card in the browser.
	 *
	 * @param array  $keys   Design keys.
	 * @param string $colour Colour of the Colour design.
	 * @return array Key => background, text, accent, light, colour.
	 */
	public static function preview_data( array $keys, $colour = '' ) {
		$out = array();
		foreach ( $keys as $key ) {
			$paint       = self::paint( $key, (object) array( 'colour' => $colour ) );
			$out[ $key ] = array(
				'background' => $paint['background'],
				'text'       => $paint['text'],
				'accent'     => $paint['accent'],
				'light'      => $paint['light'],
				'colour'     => $paint['colour'],
			);
		}
		return $out;
	}

	/**
	 * A card text for the browser preview, with %s where the value goes.
	 *
	 * @param string $key   for or from.
	 * @return string
	 */
	public static function js_text( $key ) {
		$text = self::texts()[ $key ];
		return false !== strpos( $text, '{name}' ) ? str_replace( '{name}', '%s', $text ) : $text . ' %s';
	}
}
