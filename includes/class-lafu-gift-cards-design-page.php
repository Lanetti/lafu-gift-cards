<?php
/**
 * Designer: one place for everything about how the card looks. Pick a
 * design, see it large with the shop's texts, set the texts and brand
 * colours, and choose the gift card page the email opens.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Designs section of WooCommerce > Gift cards.
 */
class Lafu_Gift_Cards_Design_Page {

	const VIEW   = 'designs';
	const ACTION = 'lafu_gc_designs_save';

	/**
	 * Hook the section and its save action.
	 */
	public static function register() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_lafu_gc_design_product', array( __CLASS__, 'new_product' ) );
	}

	/**
	 * Sample card for a design preview.
	 *
	 * @param string $key Design key.
	 * @return object
	 */
	private static function sample( $key ) {
		return (object) array(
			'design'         => $key,
			'initial_amount' => 50,
			'currency'       => get_woocommerce_currency(),
			'code'           => '',
			'recipient_name' => '',
			'sender_name'    => '',
			'expires_at'     => null,
		);
	}

	/**
	 * The designer: pick a design on the left, see it large in the middle
	 * with the shop's texts, set the texts and colours on the right, and
	 * choose the gift card page under the preview.
	 */
	public static function render() {
		$texts   = Lafu_Gift_Cards_Designs::texts();
		$colours = Lafu_Gift_Cards_Designs::colours();
		$designs = Lafu_Gift_Cards_Designs::offered();
		$ready   = array_filter(
			$designs,
			static function ( $design ) {
				return empty( $design['image'] );
			}
		);
		$yours   = array_diff_key( $designs, $ready );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only choice of the design to show.
		$wanted = isset( $_GET['design'] ) ? sanitize_key( wp_unslash( $_GET['design'] ) ) : '';
		/**
		 * Design the designer opens with, for example a new photo design.
		 *
		 * @param string $selected Design key.
		 * @param string $wanted   Key asked for in the address.
		 */
		$selected = (string) apply_filters( 'lafu_gift_cards_designer_selected', isset( $designs[ $wanted ] ) ? $wanted : (string) key( $designs ), $wanted );
		$tints    = Lafu_Gift_Cards_Designs::design_colours();
		$page     = Lafu_Gift_Cards_Gift_Page::settings();
		$styles   = array(
			'celebration' => array( __( 'Celebration', 'lafu-gift-cards' ), __( 'A big greeting on the design colour, the card below it.', 'lafu-gift-cards' ) ),
			'card'        => array( __( 'Card', 'lafu-gift-cards' ), __( 'A calm page where the card is the hero.', 'lafu-gift-cards' ) ),
			'letter'      => array( __( 'Letter', 'lafu-gift-cards' ), __( 'The card comes out of an envelope and the message reads like a letter.', 'lafu-gift-cards' ) ),
		);
		?>
		<div class="lafu-gc-titlebar">
			<h1><?php esc_html_e( 'Designer', 'lafu-gift-cards' ); ?></h1>
			<div class="lafu-gc-titlebar__actions"><?php echo Lafu_Gift_Cards_Admin::actions_html( self::VIEW, array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside actions_html(). ?></div>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lafu-gc-designer" data-lafu-gc-live-texts data-lafu-gc-designer>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<?php wp_nonce_field( self::ACTION ); ?>

			<section class="lafu-gc-panel lafu-gc-designer__library" aria-labelledby="lafu-gc-designer-pick">
				<h2 id="lafu-gc-designer-pick"><span class="lafu-gc-step">1</span><?php esc_html_e( 'Design', 'lafu-gift-cards' ); ?></h2>
				<?php
				foreach ( array(
					array( __( 'Ready designs', 'lafu-gift-cards' ), $ready ),
					array( __( 'Your designs', 'lafu-gift-cards' ), $yours ),
				) as $group ) :
					if ( ! $group[1] ) {
						continue;
					}
					?>
					<p class="lafu-gc-designer__group"><?php echo esc_html( $group[0] ); ?></p>
					<div class="lafu-gc-designer__tiles">
						<?php foreach ( $group[1] as $key => $design ) : ?>
							<label class="lafu-gc-designer__tile">
								<input type="radio" name="designer_design" value="<?php echo esc_attr( $key ); ?>" <?php checked( $selected, $key ); ?> data-lafu-gc-pick>
								<span class="lafu-gc-designer__thumb" aria-hidden="true"><?php echo Lafu_Gift_Cards_Designs::render( self::sample( $key ), 'web' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render(). ?></span>
								<span><?php echo esc_html( $design['label'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
				<?php
				/**
				 * After the design list in the designer, for example a button to make a new design.
				 */
				do_action( 'lafu_gift_cards_designer_library' );
				?>
				<p class="lafu-gc-hint"><?php esc_html_e( 'Each gift card product shows one design. Pick it on the product, or make a new product from the design you see here.', 'lafu-gift-cards' ); ?></p>
			</section>

			<div class="lafu-gc-designer__main">
				<section class="lafu-gc-panel lafu-gc-designer__stage" aria-label="<?php esc_attr_e( 'Preview', 'lafu-gift-cards' ); ?>">
					<div class="lafu-gc-designer__preview lafu-gc-design-grid">
						<?php foreach ( $designs as $key => $design ) : ?>
							<?php
							/**
							 * Links under a design in the designer, for example Edit for a brand design: label => URL.
							 *
							 * @param array  $links Label => URL.
							 * @param string $key   Design key.
							 */
							$links = (array) apply_filters( 'lafu_gift_cards_design_links', array(), $key );
							?>
							<div class="lafu-gc-designer__big" data-lafu-gc-design="<?php echo esc_attr( $key ); ?>" data-colour="<?php echo empty( $design['colour'] ) ? '0' : '1'; ?>" data-photo="<?php echo empty( $design['image'] ) ? '0' : '1'; ?>" data-own="<?php echo esc_attr( implode( ',', array( $design['background'], $design['background2'], $design['text'], $design['accent'] ) ) ); ?>" data-art="<?php echo esc_attr( Lafu_Gift_Cards_Designs::paint( $key )['art'] ); ?>" <?php echo $selected === $key ? '' : 'hidden'; ?>>
								<?php echo Lafu_Gift_Cards_Designs::render( self::sample( $key ), 'web' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render(). ?>
								<div class="lafu-gc-designer__under">
									<strong><?php echo esc_html( $design['label'] ); ?></strong>
									<span class="lafu-gc-designer__links">
										<?php foreach ( $links as $link_label => $url ) : ?>
											<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $link_label ); ?></a>
										<?php endforeach; ?>
										<a class="lafu-gc-btn lafu-gc-btn--ghost lafu-gc-btn--small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lafu_gc_design_product&design=' . rawurlencode( $key ) ), 'lafu_gc_design_product' ) ); ?>"><?php esc_html_e( 'New product with this design', 'lafu-gift-cards' ); ?></a>
									</span>
								</div>
							</div>
						<?php endforeach; ?>
						<?php
						/**
						 * After the large previews in the designer, for example the stage of a photo design editor.
						 */
						do_action( 'lafu_gift_cards_designer_stage' );
						?>
					</div>
				</section>
			</div>

			<div class="lafu-gc-designer__side">
				<?php
				/**
				 * Top of the right column in the designer, for example the controls of a photo design.
				 */
				do_action( 'lafu_gift_cards_designer_panel' );
				?>
				<section class="lafu-gc-panel lafu-gc-designer__inspector" aria-labelledby="lafu-gc-designer-text">
					<h2 id="lafu-gc-designer-text"><span class="lafu-gc-step">2</span><?php esc_html_e( 'Text and colour', 'lafu-gift-cards' ); ?></h2>
					<label class="lafu-gc-field"><span><?php esc_html_e( 'Heading', 'lafu-gift-cards' ); ?></span><input type="text" name="texts[label]" maxlength="40" value="<?php echo esc_attr( $texts['label'] ); ?>" data-lafu-gc-live="label"></label>
					<label class="lafu-gc-field"><span><?php esc_html_e( 'Name on the card', 'lafu-gift-cards' ); ?></span><input type="text" name="texts[shop]" maxlength="60" value="<?php echo esc_attr( $texts['shop'] ); ?>" data-lafu-gc-live="shop"></label>
					<label class="lafu-gc-field"><span><?php esc_html_e( 'Text on the card', 'lafu-gift-cards' ); ?> <i><?php esc_html_e( 'optional', 'lafu-gift-cards' ); ?></i></span><input type="text" name="texts[line]" maxlength="60" value="<?php echo esc_attr( $texts['line'] ); ?>" placeholder="<?php esc_attr_e( 'For example Thank you for shopping with us', 'lafu-gift-cards' ); ?>" data-lafu-gc-live="line"></label>
					<details class="lafu-gc-more">
						<summary><?php esc_html_e( 'Recipient, sender and expiry texts', 'lafu-gift-cards' ); ?></summary>
						<p class="lafu-gc-hint"><?php esc_html_e( '{name} is the name and {date} the last day the card is valid.', 'lafu-gift-cards' ); ?></p>
						<label class="lafu-gc-field"><span><?php esc_html_e( 'Recipient', 'lafu-gift-cards' ); ?></span><input type="text" name="texts[for]" maxlength="60" value="<?php echo esc_attr( $texts['for'] ); ?>"></label>
						<label class="lafu-gc-field"><span><?php esc_html_e( 'Sender', 'lafu-gift-cards' ); ?></span><input type="text" name="texts[from]" maxlength="60" value="<?php echo esc_attr( $texts['from'] ); ?>"></label>
						<label class="lafu-gc-field"><span><?php esc_html_e( 'Valid until', 'lafu-gift-cards' ); ?></span><input type="text" name="texts[until]" maxlength="60" value="<?php echo esc_attr( $texts['until'] ); ?>"></label>
					</details>
					<div class="lafu-gc-field lafu-gc-designer__colour" data-lafu-gc-colour-field>
						<span><?php esc_html_e( 'Card colour', 'lafu-gift-cards' ); ?> <i><?php esc_html_e( 'for the design you see; the art stays', 'lafu-gift-cards' ); ?></i></span>
						<div class="lafu-gc-designer__picker">
							<input type="color" value="#d46aab" data-lafu-gc-picker aria-label="<?php esc_attr_e( 'Card colour', 'lafu-gift-cards' ); ?>">
							<button type="button" class="button-link" data-lafu-gc-picker-reset><?php esc_html_e( 'Use the design\'s own colour', 'lafu-gift-cards' ); ?></button>
						</div>
						<?php foreach ( $designs as $key => $design ) : ?>
							<?php if ( empty( $design['image'] ) ) : ?>
								<input type="hidden" name="design_colours[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( isset( $tints[ $key ] ) ? $tints[ $key ] : '' ); ?>" data-lafu-gc-tint="<?php echo esc_attr( $key ); ?>">
							<?php endif; ?>
						<?php endforeach; ?>
						<span class="lafu-gc-designer__swatch-label"><?php esc_html_e( 'Your brand colours', 'lafu-gift-cards' ); ?> <i><?php esc_html_e( 'one click puts a colour on the card; also quick picks at the counter', 'lafu-gift-cards' ); ?></i></span>
						<?php
						/* translators: %s: colour code like #d46aab */
						$use_label = __( 'Use %s', 'lafu-gift-cards' );
						?>
						<div class="lafu-gc-colour-list lafu-gc-designer__swatches" data-lafu-gc-swatches>
							<?php foreach ( $colours as $index => $hex ) : ?>
								<span class="lafu-gc-designer__swatch">
									<?php $label = sprintf( $use_label, $hex ); ?>
									<button type="button" class="lafu-gc-designer__dot" style="<?php echo esc_attr( 'background:' . $hex ); ?>" data-lafu-gc-swatch="<?php echo esc_attr( $hex ); ?>" aria-label="<?php echo esc_attr( $label ); ?>"></button>
									<input type="hidden" name="colours[]" value="<?php echo esc_attr( $hex ); ?>">
									<button type="button" class="lafu-gc-colour-remove" data-lafu-gc-swatch-remove aria-label="<?php esc_attr_e( 'Remove colour', 'lafu-gift-cards' ); ?>">&times;</button>
								</span>
							<?php endforeach; ?>
							<button type="button" class="lafu-gc-btn lafu-gc-btn--ghost lafu-gc-btn--small" data-lafu-gc-swatch-add data-label="<?php echo esc_attr( $use_label ); ?>" data-remove="<?php esc_attr_e( 'Remove colour', 'lafu-gift-cards' ); ?>"><?php esc_html_e( 'Save this colour', 'lafu-gift-cards' ); ?></button>
						</div>
					</div>
					<p class="lafu-gc-hint"><?php esc_html_e( 'Shop defaults for every card. A product can change them on its Gift card tab. Write them your way, or in your own language.', 'lafu-gift-cards' ); ?> <a href="<?php echo esc_url( Lafu_Gift_Cards_I18n::email_settings_url( 'lafu_gift_card' ) ); ?>"><?php esc_html_e( 'Edit email text', 'lafu-gift-cards' ); ?></a></p>
				</section>
				<section class="lafu-gc-panel" aria-labelledby="lafu-gc-designer-page">
					<h2 id="lafu-gc-designer-page"><span class="lafu-gc-step">3</span><?php esc_html_e( 'Gift card page', 'lafu-gift-cards' ); ?></h2>
					<p class="lafu-gc-hint"><?php esc_html_e( 'The page the gift card email opens. Point at a style to see it, click Preview for the full page.', 'lafu-gift-cards' ); ?></p>
					<div class="lafu-gc-page-styles">
						<?php foreach ( $styles as $style_key => $style ) : ?>
							<?php $preview_url = add_query_arg( Lafu_Gift_Cards_Gift_Page::PREVIEW_QUERY, $style_key, home_url( '/' ) ); ?>
							<div class="lafu-gc-page-style" data-lafu-gc-style-preview="<?php echo esc_url( $preview_url ); ?>">
								<label>
									<input type="radio" name="gift_page_style" value="<?php echo esc_attr( $style_key ); ?>" <?php checked( $page['style'], $style_key ); ?>>
									<span class="lafu-gc-page-style__thumb lafu-gc-page-style__thumb--<?php echo esc_attr( $style_key ); ?>" aria-hidden="true"><i></i><b></b></span>
									<span><strong><?php echo esc_html( $style[0] ); ?></strong><?php echo esc_html( $style[1] ); ?></span>
								</label>
								<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener" data-lafu-gc-style-link><?php esc_html_e( 'Preview', 'lafu-gift-cards' ); ?></a>
							</div>
						<?php endforeach; ?>
					</div>
					<label class="lafu-gc-switch"><input type="checkbox" name="gift_page_motion" value="1" <?php checked( $page['motion'] ); ?>><span><?php esc_html_e( 'Entrance animation: confetti, snow or hearts that fit the design fly in once. Visitors who turn off motion do not see it.', 'lafu-gift-cards' ); ?></span></label>
				</section>
			</div>

			<div class="lafu-gc-savebar">
				<button class="lafu-gc-btn"><?php esc_html_e( 'Save designs', 'lafu-gift-cards' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Save the card texts and the brand colours.
	 */
	public static function save() {
		if ( ! current_user_can( Lafu_Gift_Cards_Admin::CAPABILITY ) || ! check_admin_referer( self::ACTION ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'lafu-gift-cards' ), '', array( 'response' => 403 ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked above.
		$posted_texts = isset( $_POST['texts'] ) && is_array( $_POST['texts'] ) ? map_deep( wp_unslash( $_POST['texts'] ), 'sanitize_text_field' ) : array();
		$colours      = isset( $_POST['colours'] ) ? Lafu_Gift_Cards_Settings::sanitize_colours( null, array(), map_deep( (array) wp_unslash( $_POST['colours'] ), 'sanitize_text_field' ) ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$texts = array();
		foreach ( array( 'label', 'shop', 'line', 'for', 'from', 'until' ) as $key ) {
			$texts[ $key ] = isset( $posted_texts[ $key ] ) ? mb_substr( (string) $posted_texts[ $key ], 0, 60 ) : '';
		}
		update_option( 'lafu_gift_cards_card_texts', $texts, false );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked above.
		Lafu_Gift_Cards_Gift_Page::save_settings(
			isset( $_POST['gift_page_style'] ) ? sanitize_key( wp_unslash( $_POST['gift_page_style'] ) ) : 'celebration',
			! empty( $_POST['gift_page_motion'] )
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( $colours ) {
			update_option( 'lafu_gift_cards_colours', $colours, false );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked above.
		$posted_tints = isset( $_POST['design_colours'] ) && is_array( $_POST['design_colours'] ) ? map_deep( wp_unslash( $_POST['design_colours'] ), 'sanitize_text_field' ) : array();
		$tints        = array();
		foreach ( $posted_tints as $tint_key => $hex ) {
			$hex = sanitize_hex_color( (string) $hex );
			if ( $hex && isset( Lafu_Gift_Cards_Designs::all()[ sanitize_key( $tint_key ) ] ) ) {
				$tints[ sanitize_key( $tint_key ) ] = $hex;
			}
		}
		update_option( 'lafu_gift_cards_design_colours', $tints, false );

		/**
		 * The designer was saved (nonce and capability already checked). Add-ons save their own fields here.
		 */
		do_action( 'lafu_gift_cards_designer_save' );

		set_transient(
			'lafu_gc_flash_' . get_current_user_id(),
			array(
				'type'    => 'success',
				/**
				 * Message after saving the designer.
				 *
				 * @param string $message Message.
				 */
				'message' => (string) apply_filters( 'lafu_gift_cards_designer_saved_message', __( 'Designs saved.', 'lafu-gift-cards' ) ),
			),
			MINUTE_IN_SECONDS
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked above.
		$back_to = isset( $_POST['designer_design'] ) ? sanitize_key( wp_unslash( $_POST['designer_design'] ) ) : '';
		/**
		 * Design the designer shows after saving, for example a photo design that was just made.
		 *
		 * @param string $back_to Design key.
		 */
		$back_to = (string) apply_filters( 'lafu_gift_cards_designer_saved_design', $back_to );
		wp_safe_redirect(
			Lafu_Gift_Cards_Admin::page_url(
				array_filter(
					array(
						'view'   => self::VIEW,
						'design' => $back_to,
					)
				)
			)
		);
		exit;
	}

	/**
	 * New draft gift card product with the chosen design, opened in the editor.
	 */
	public static function new_product() {
		if ( ! current_user_can( Lafu_Gift_Cards_Admin::CAPABILITY ) || ! check_admin_referer( 'lafu_gc_design_product' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'lafu-gift-cards' ), '', array( 'response' => 403 ) );
		}
		$key = isset( $_GET['design'] ) ? sanitize_key( wp_unslash( $_GET['design'] ) ) : '';
		$id  = Lafu_Gift_Cards_Admin::create_card_product( Lafu_Gift_Cards_Designs::resolve( $key ) );
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit&lafu_gc_image=1' ) );
		exit;
	}
}
