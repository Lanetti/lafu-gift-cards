<?php
/**
 * The gift card product: product settings, the buyer form and the cart.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Any simple product becomes a gift card with one tick. The buyer picks
 * an amount, a design and who receives it; the cart charges that amount.
 */
class Lafu_Gift_Cards_Product {

	const FLAG           = '_lafu_gift_card';
	const ITEM_META      = '_lafu_gc';
	const MESSAGE_LIMIT  = 300;
	const MAX_RECIPIENTS = 20;

	/**
	 * Hook admin and storefront.
	 */
	public static function register() {
		add_filter( 'product_type_options', array( __CLASS__, 'type_option' ) );
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'data_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'wp_ajax_lafu_gc_product_image', array( __CLASS__, 'ajax_product_image' ) );
		add_action( 'wp_ajax_lafu_gc_card_svg', array( __CLASS__, 'ajax_card_svg' ) );

		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'buyer_form' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_assets' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item', array( __CLASS__, 'require_card_data' ), 5 );
		add_action( 'woocommerce_store_api_validate_add_to_cart', array( __CLASS__, 'store_api_guard' ) );
		// Express pay buttons on the product page skip the gift card form, so
		// gift card pages show only the form. The buttons stay at checkout.
		add_filter( 'wc_stripe_hide_payment_request_on_product_page', array( __CLASS__, 'hide_express_for_post' ), 10, 2 );
		add_filter( 'wcpay_payment_request_is_product_supported', array( __CLASS__, 'express_supported' ), 10, 2 );
		add_filter( 'wcpay_woopay_button_is_product_supported', array( __CLASS__, 'express_supported' ), 10, 2 );
		add_filter( 'wc_stripe_product_payment_methods', array( __CLASS__, 'express_gateways' ), 10, 2 );
		add_filter( 'wc_ppcp_product_payment_gateways', array( __CLASS__, 'express_gateways' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_quantity', array( __CLASS__, 'quantity' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'set_prices' ), 5 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'lock_quantity' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 3 );

		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 10, 2 );
		add_filter( 'woocommerce_product_get_tax_status', array( __CLASS__, 'tax_status' ), 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( __CLASS__, 'no_coupons' ), 10, 2 );
		add_filter( 'woocommerce_product_supports', array( __CLASS__, 'no_ajax_add' ), 10, 3 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'add_to_cart_url' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'add_to_cart_text' ), 10, 2 );
	}

	/**
	 * Whether a product is a gift card.
	 *
	 * @param WC_Product|int|null $product Product or ID.
	 * @return bool
	 */
	public static function is_gift_card( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
		return $product instanceof WC_Product && 'yes' === $product->get_meta( self::FLAG );
	}

	/**
	 * Amount and design options of a gift card product.
	 *
	 * @param WC_Product $product Product.
	 * @return array amounts, custom, min, max, physical, designs (the one
	 *               design the shop chose, as a list), colour, texts (the
	 *               product's own heading, name and line, empty ones left out).
	 */
	public static function options( $product ) {
		$amounts = array();
		foreach ( explode( ',', (string) $product->get_meta( '_lafu_gc_amounts' ) ) as $value ) {
			$value = Lafu_Gift_Cards_Store::money( wc_format_decimal( trim( $value ) ) );
			if ( $value > 0 ) {
				$amounts[] = $value;
			}
		}
		$amounts = array_values( array_unique( $amounts ) );
		sort( $amounts );

		$design = (string) $product->get_meta( '_lafu_gc_design' );
		$colour = sanitize_hex_color( (string) $product->get_meta( '_lafu_gc_colour' ) );

		$custom = 'yes' === $product->get_meta( '_lafu_gc_custom' );
		$min    = Lafu_Gift_Cards_Store::money( $product->get_meta( '_lafu_gc_min' ) );
		$max    = Lafu_Gift_Cards_Store::money( $product->get_meta( '_lafu_gc_max' ) );

		return array(
			'amounts'  => $amounts,
			'custom'   => $custom || ! $amounts,
			'min'      => $min > 0 ? $min : 1,
			'max'      => $max > 0 ? $max : 1000,
			'physical' => 'yes' === $product->get_meta( '_lafu_gc_physical' ),
			'designs'  => array( Lafu_Gift_Cards_Designs::resolve( $design ) ),
			'colour'   => $colour ? $colour : Lafu_Gift_Cards_Designs::colours()[0],
			'texts'    => Lafu_Gift_Cards_Designs::clean_texts(
				array(
					'label' => $product->get_meta( '_lafu_gc_label' ),
					'shop'  => $product->get_meta( '_lafu_gc_shop' ),
					'line'  => $product->get_meta( '_lafu_gc_line' ),
				)
			),
		);
	}

	/**
	 * Short amount for buttons: whole amounts without decimals, plain text.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function amount_label( $amount ) {
		$whole = abs( (float) $amount - round( (float) $amount ) ) < 0.005;
		return lafu_gift_cards_plain_price( $amount, $whole ? array( 'decimals' => 0 ) : array() );
	}

	/**
	 * "Gift card" tick box next to Virtual and Downloadable.
	 *
	 * @param array $options Product type options.
	 * @return array
	 */
	public static function type_option( $options ) {
		$options['lafu_gift_card'] = array(
			'id'            => self::FLAG,
			'wrapper_class' => 'show_if_simple',
			'label'         => __( 'Gift card', 'lafu-gift-cards' ),
			'description'   => __( 'Sell this product as a gift card. Buyers choose the amount and who gets it.', 'lafu-gift-cards' ),
			'default'       => 'no',
		);
		return $options;
	}

	/**
	 * Product data tab, shown while the tick box is on.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function data_tab( $tabs ) {
		$tabs['lafu_gift_card'] = array(
			'label'    => __( 'Gift card', 'lafu-gift-cards' ),
			'target'   => 'lafu_gift_card_data',
			'class'    => array( 'show_if_lafu_gift_card' ),
			'priority' => 15,
		);
		return $tabs;
	}

	/**
	 * Product data panel fields.
	 */
	public static function data_panel() {
		global $product_object;
		$product = $product_object instanceof WC_Product ? $product_object : null;
		$value   = static function ( $key, $fallback ) use ( $product ) {
			$stored = $product ? $product->get_meta( $key ) : '';
			return '' === $stored ? $fallback : $stored;
		};
		echo '<div id="lafu_gift_card_data" class="panel woocommerce_options_panel hidden"><div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'          => '_lafu_gc_amounts',
				'label'       => __( 'Amounts', 'lafu-gift-cards' ),
				'description' => __( 'Ready amounts separated by commas, for example 25, 50, 100.', 'lafu-gift-cards' ),
				'desc_tip'    => true,
				'value'       => $value( '_lafu_gc_amounts', '25, 50, 100' ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => '_lafu_gc_custom',
				'label'       => __( 'Own amount', 'lafu-gift-cards' ),
				'description' => __( 'Let the buyer type any amount between the limits below.', 'lafu-gift-cards' ),
				'value'       => $value( '_lafu_gc_custom', 'yes' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_lafu_gc_min',
				'label'             => __( 'Lowest amount', 'lafu-gift-cards' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => 'any',
				),
				'value'             => $value( '_lafu_gc_min', '10' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_lafu_gc_max',
				'label'             => __( 'Highest amount', 'lafu-gift-cards' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => 'any',
				),
				'value'             => $value( '_lafu_gc_max', '500' ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => '_lafu_gc_physical',
				'label'       => __( 'Printed card', 'lafu-gift-cards' ),
				'description' => __( 'The shop prints and ships the card. No email is sent and the order needs shipping.', 'lafu-gift-cards' ),
				'value'       => $value( '_lafu_gc_physical', 'no' ),
			)
		);
		echo '</div><div class="options_group">';
		$design_options = array();
		foreach ( Lafu_Gift_Cards_Designs::all() as $key => $design ) {
			if ( empty( $design['legacy'] ) || $value( '_lafu_gc_design', '' ) === $key ) {
				$design_options[ $key ] = $design['label'];
			}
		}
		woocommerce_wp_select(
			array(
				'id'          => '_lafu_gc_design',
				'label'       => __( 'Design', 'lafu-gift-cards' ),
				'description' => __( 'The card buyers get. For another occasion, make another gift card product with its own design.', 'lafu-gift-cards' ),
				'desc_tip'    => true,
				'options'     => $design_options,
				'value'       => Lafu_Gift_Cards_Designs::resolve( $value( '_lafu_gc_design', '' ) ),
			)
		);
		$colour = sanitize_hex_color( $value( '_lafu_gc_colour', '' ) );
		printf(
			'<p class="form-field" data-lafu-gc-colour-field><label for="_lafu_gc_colour">%1$s</label><input type="color" id="_lafu_gc_colour" name="_lafu_gc_colour" value="%2$s" style="width:60px;height:34px;padding:2px"> <span class="description">%3$s</span></p>',
			esc_html__( 'Colour', 'lafu-gift-cards' ),
			esc_attr( $colour ? $colour : Lafu_Gift_Cards_Designs::colours()[0] ),
			esc_html__( 'Used by the Colour design.', 'lafu-gift-cards' )
		);
		$shop_texts = Lafu_Gift_Cards_Designs::texts();
		foreach (
			array(
				'_lafu_gc_label' => array( __( 'Heading', 'lafu-gift-cards' ), $shop_texts['label'] ),
				'_lafu_gc_shop'  => array( __( 'Name on the card', 'lafu-gift-cards' ), $shop_texts['shop'] ),
				'_lafu_gc_line'  => array( __( 'Text on the card', 'lafu-gift-cards' ), '' !== $shop_texts['line'] ? $shop_texts['line'] : __( 'For example Happy holidays!', 'lafu-gift-cards' ) ),
			) as $key => $field
		) {
			woocommerce_wp_text_input(
				array(
					'id'                => $key,
					'label'             => $field[0],
					'placeholder'       => $field[1],
					'value'             => $value( $key, '' ),
					'custom_attributes' => array(
						'maxlength'          => 60,
						'data-lafu-gc-field' => substr( $key, 9 ),
					),
				)
			);
		}
		echo '<div class="lafu-gc-product-preview" style="padding:5px 20px 12px 162px;position:relative"><span style="position:absolute;left:12px;top:5px;font-weight:600">' . esc_html__( 'Preview', 'lafu-gift-cards' ) . '</span>';
		$preview_meta = Lafu_Gift_Cards_Designs::clean_texts(
			array(
				'label' => $value( '_lafu_gc_label', '' ),
				'shop'  => $value( '_lafu_gc_shop', '' ),
				'line'  => $value( '_lafu_gc_line', '' ),
			)
		);

		$preview_meta['colour'] = sanitize_hex_color( $value( '_lafu_gc_colour', '' ) );
		$preview_card           = (object) array(
			'design'         => Lafu_Gift_Cards_Designs::resolve( $value( '_lafu_gc_design', '' ) ),
			'meta'           => wp_json_encode( $preview_meta ),
			'initial_amount' => 50,
			'currency'       => get_woocommerce_currency(),
			'code'           => '',
			'recipient_name' => '',
			'sender_name'    => '',
			'expires_at'     => null,
		);
		printf(
			'<div class="lafu-gc-product-preview__card" style="max-width:360px;margin-bottom:8px" data-lafu-gc-product-preview data-designs="%1$s" data-texts="%2$s">%3$s</div>',
			esc_attr( wp_json_encode( Lafu_Gift_Cards_Designs::preview_data( array_keys( $design_options ) ) ) ),
			esc_attr( wp_json_encode( $shop_texts ) ),
			Lafu_Gift_Cards_Designs::render( $preview_card, 'web' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render().
		);
		echo '<span class="description">' . esc_html__( 'Empty fields use the texts on the Designs page.', 'lafu-gift-cards' ) . '</span> ';
		echo '<button type="button" class="button" data-lafu-gc-product-image>' . esc_html__( 'Use this card as the product image', 'lafu-gift-cards' ) . '</button>';
		echo '<span class="description" data-lafu-gc-product-image-status></span></div>';
		echo '</div>';
		/**
		 * Extra settings on the Gift card product tab.
		 *
		 * @param WC_Product|null $product Product being edited.
		 */
		do_action( 'lafu_gift_cards_product_panel', $product );
		echo '</div>';
	}

	/**
	 * Save the product settings. A gift card is never taxed when sold (VAT
	 * is due when the card is used) and has no shipping unless printed.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public static function save( $product ) {
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}
		$is_card = isset( $_POST[ self::FLAG ] ) && $product->is_type( 'simple' );
		$product->update_meta_data( self::FLAG, $is_card ? 'yes' : 'no' );
		if ( ! $is_card ) {
			return;
		}

		$amounts = isset( $_POST['_lafu_gc_amounts'] ) ? sanitize_text_field( wp_unslash( $_POST['_lafu_gc_amounts'] ) ) : '';
		$clean   = array();
		foreach ( explode( ',', $amounts ) as $amount ) {
			$amount = wc_format_decimal( trim( $amount ) );
			if ( '' !== $amount && (float) $amount > 0 ) {
				$clean[] = $amount;
			}
		}
		$design = isset( $_POST['_lafu_gc_design'] ) ? sanitize_key( wp_unslash( $_POST['_lafu_gc_design'] ) ) : '';
		$colour = isset( $_POST['_lafu_gc_colour'] ) ? sanitize_hex_color( strtolower( sanitize_text_field( wp_unslash( $_POST['_lafu_gc_colour'] ) ) ) ) : '';
		$min    = isset( $_POST['_lafu_gc_min'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_lafu_gc_min'] ) ) ) : '';
		$max    = isset( $_POST['_lafu_gc_max'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_lafu_gc_max'] ) ) ) : '';
		if ( '' !== $min && '' !== $max && (float) $max < (float) $min ) {
			$max = $min;
		}

		$product->update_meta_data( '_lafu_gc_amounts', implode( ', ', $clean ) );
		$product->update_meta_data( '_lafu_gc_custom', isset( $_POST['_lafu_gc_custom'] ) ? 'yes' : 'no' );
		$product->update_meta_data( '_lafu_gc_min', $min );
		$product->update_meta_data( '_lafu_gc_max', $max );
		$product->update_meta_data( '_lafu_gc_physical', isset( $_POST['_lafu_gc_physical'] ) ? 'yes' : 'no' );
		$product->update_meta_data( '_lafu_gc_design', Lafu_Gift_Cards_Designs::resolve( $design ) );
		$product->update_meta_data( '_lafu_gc_colour', (string) $colour );
		$product->delete_meta_data( '_lafu_gc_designs' );
		foreach ( array( '_lafu_gc_label', '_lafu_gc_shop', '_lafu_gc_line' ) as $key ) {
			$product->update_meta_data( $key, isset( $_POST[ $key ] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ), 0, 60 ) : '' );
		}

		self::apply_card_defaults( $product );
		update_option( 'lafu_gift_cards_product_id', $product->get_id(), false );

		/**
		 * Save extra gift card product settings. The WooCommerce product
		 * nonce has been checked.
		 *
		 * @param WC_Product $product Product being saved.
		 */
		do_action( 'lafu_gift_cards_product_save', $product );
	}

	/**
	 * Product properties every gift card needs: no tax at sale, virtual
	 * unless printed, and a base price so WooCommerce lets it be bought.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function apply_card_defaults( $product ) {
		$options = self::options( $product );
		$product->set_tax_status( 'none' );
		$product->set_virtual( ! $options['physical'] );
		$product->set_downloadable( false );
		$base = $options['amounts'] ? $options['amounts'][0] : $options['min'];
		$product->set_regular_price( wc_format_decimal( $base ) );
		$product->set_sale_price( '' );
	}

	/**
	 * Admin script that shows the Gift card tab with the tick box.
	 *
	 * @param string $hook Admin page.
	 */
	public static function admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || 'product' !== get_post_type() ) {
			return;
		}
		lafu_gift_cards_register_assets();
		wp_enqueue_style( 'lafu-gift-cards-card' );
		wp_enqueue_script( 'lafu-gift-cards-product', plugins_url( 'assets/admin-product.js', LAFU_GIFT_CARDS_FILE ), array( 'jquery' ), LAFU_GIFT_CARDS_VERSION, true );
		wp_localize_script(
			'lafu-gift-cards-product',
			'lafuGiftCardProduct',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'lafu_gc_product_image' ),
				'product' => get_the_ID(),
				'saved'   => __( 'The card is now the product image.', 'lafu-gift-cards' ),
				'failed'  => __( 'The product image could not be saved.', 'lafu-gift-cards' ),
				'auto'    => isset( $_GET['lafu_gc_image'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only.
			)
		);
	}

	/**
	 * The card on the Gift card tab as one SVG, which the browser draws
	 * into the JPEG for the product image.
	 */
	public static function ajax_card_svg() {
		check_ajax_referer( 'lafu_gc_product_image' );
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			wp_send_json_error();
		}
		$field  = static function ( $name ) {
			$value = isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked above.
			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 80 ) : substr( $value, 0, 80 );
		};
		$design = sanitize_key( $field( 'design' ) );
		if ( ! array_key_exists( $design, Lafu_Gift_Cards_Designs::all() ) ) {
			wp_send_json_error();
		}
		$colour = sanitize_hex_color( $field( 'colour' ) );
		wp_send_json_success(
			array(
				'svg' => Lafu_Gift_Cards_Designs::card_svg(
					$design,
					$colour ? $colour : '',
					array(
						'label'  => $field( 'label' ),
						'shop'   => $field( 'shop' ),
						'line'   => $field( 'line' ),
						'amount' => $field( 'amount' ),
					)
				),
			)
		);
	}

	/**
	 * Save a JPEG of the card preview as the product image.
	 */
	public static function ajax_product_image() {
		check_ajax_referer( 'lafu_gc_product_image' );
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! current_user_can( 'edit_post', $product_id ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error();
		}
		$product = wc_get_product( $product_id );
		$file    = isset( $_FILES['image'] ) && is_array( $_FILES['image'] ) ? $_FILES['image'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checked below and by media_handle_upload().
		$size    = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$tmp     = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( ! $product || ! self::is_gift_card( $product ) || $size < 100 || $size > 2 * MB_IN_BYTES || '' === $tmp ) {
			wp_send_json_error();
		}
		$type = wp_check_filetype_and_ext( $tmp, 'lafu-gift-card-' . $product_id . '.jpg' );
		if ( 'image/jpeg' !== $type['type'] ) {
			wp_send_json_error();
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$_FILES['image']['name'] = 'lafu-gift-card-' . $product_id . '.jpg';
		$id                      = media_handle_upload( 'image', $product_id, array( 'post_title' => $product->get_name() ) );
		if ( is_wp_error( $id ) ) {
			wp_send_json_error();
		}
		set_post_thumbnail( $product_id, $id );
		// The Product image box and its hidden field on the open edit screen
		// must show the new image, or the next Update would remove it again.
		wp_send_json_success(
			array(
				'id'   => (int) $id,
				'html' => _wp_post_thumbnail_html( $id, $product_id ),
			)
		);
	}

	/**
	 * Storefront styles and script on gift card product pages.
	 */
	public static function front_assets() {
		wp_register_script( 'lafu-gift-cards', plugins_url( 'assets/frontend.js', LAFU_GIFT_CARDS_FILE ), array(), LAFU_GIFT_CARDS_VERSION, true );
		if ( is_product() && self::is_gift_card( get_queried_object_id() ) ) {
			wp_enqueue_style( 'lafu-gift-cards' );
			wp_enqueue_script( 'lafu-gift-cards' );
		}
	}

	/**
	 * The buyer form above the Add to cart button.
	 */
	public static function buyer_form() {
		global $product;
		if ( ! self::is_gift_card( $product ) ) {
			return;
		}
		wp_enqueue_style( 'lafu-gift-cards' );
		wp_enqueue_script( 'lafu-gift-cards' );
		$options = self::options( $product );
		$posted  = self::posted();
		$current = '' !== $posted['amount'] ? $posted['amount'] : ( $options['amounts'] ? (string) $options['amounts'][0] : 'custom' );
		$symbol  = get_woocommerce_currency_symbol();
		$today   = wp_date( 'Y-m-d' );
		$preview = (object) array(
			'design'         => $options['designs'][0],
			'colour'         => $options['colour'],
			'meta'           => wp_json_encode( $options['texts'] ),
			'initial_amount' => 'custom' === $current ? $options['min'] : (float) $current,
			'currency'       => get_woocommerce_currency(),
			'code'           => '',
			'recipient_name' => '',
			'sender_name'    => '',
			'expires_at'     => Lafu_Gift_Cards_Settings::default_expiry(),
		);

		$for_label  = Lafu_Gift_Cards_Designs::js_text( 'for' );
		$from_label = Lafu_Gift_Cards_Designs::js_text( 'from' );
		?>
		<div class="lafu-gc-form" data-lafu-gc-product data-money="<?php echo esc_attr( wp_json_encode( lafu_gift_cards_money_format() ) ); ?>" data-for="<?php echo esc_attr( $for_label ); ?>" data-from="<?php echo esc_attr( $from_label ); ?>">
			<div class="lafu-gc-preview" aria-hidden="true">
				<?php echo Lafu_Gift_Cards_Designs::render( $preview, 'web' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render(). ?>
			</div>
			<fieldset class="lafu-gc-amounts">
				<legend><?php esc_html_e( 'Amount', 'lafu-gift-cards' ); ?></legend>
				<?php foreach ( $options['amounts'] as $amount ) : ?>
					<label class="lafu-gc-chip">
						<input type="radio" name="lafu_gc_amount" value="<?php echo esc_attr( $amount ); ?>" <?php checked( (string) $amount, (string) $current ); ?>>
						<span><?php echo esc_html( self::amount_label( $amount ) ); ?></span>
					</label>
				<?php endforeach; ?>
				<?php if ( $options['custom'] ) : ?>
					<label class="lafu-gc-chip">
						<input type="radio" name="lafu_gc_amount" value="custom" <?php checked( 'custom', $current ); ?>>
						<span><?php esc_html_e( 'Other amount', 'lafu-gift-cards' ); ?></span>
					</label>
					<p class="lafu-gc-custom" data-lafu-gc-custom>
						<label for="lafu_gc_custom_amount">
							<?php
							/* translators: 1: lowest amount, 2: highest amount */
							echo esc_html( sprintf( __( 'Amount (%1$s to %2$s)', 'lafu-gift-cards' ), lafu_gift_cards_plain_price( $options['min'] ), lafu_gift_cards_plain_price( $options['max'] ) ) );
							?>
						</label>
						<span class="lafu-gc-money">
							<input type="number" id="lafu_gc_custom_amount" name="lafu_gc_custom_amount" min="<?php echo esc_attr( $options['min'] ); ?>" max="<?php echo esc_attr( $options['max'] ); ?>" step="any" inputmode="decimal" value="<?php echo esc_attr( $posted['custom_amount'] ); ?>">
							<span><?php echo esc_html( $symbol ); ?></span>
						</span>
					</p>
				<?php endif; ?>
			</fieldset>

			<input type="hidden" name="lafu_gc_design" value="<?php echo esc_attr( $options['designs'][0] ); ?>">

			<fieldset class="lafu-gc-recipient">
				<legend><?php esc_html_e( 'Who is it for?', 'lafu-gift-cards' ); ?></legend>
				<?php if ( ! $options['physical'] ) : ?>
					<p>
						<label><input type="checkbox" name="lafu_gc_gift" value="1" data-lafu-gc-gift <?php checked( $posted['gift'] ); ?>> <?php esc_html_e( 'Send it to someone else', 'lafu-gift-cards' ); ?></label>
					</p>
				<?php endif; ?>
				<div class="lafu-gc-gift-fields" <?php echo $options['physical'] ? '' : 'data-lafu-gc-gift-fields'; ?>>
					<p>
						<label for="lafu_gc_recipient_name"><?php esc_html_e( 'Recipient name', 'lafu-gift-cards' ); ?></label>
						<input type="text" id="lafu_gc_recipient_name" name="lafu_gc_recipient_name" maxlength="100" value="<?php echo esc_attr( $posted['recipient_name'] ); ?>">
					</p>
					<?php if ( ! $options['physical'] ) : ?>
						<p>
							<label for="lafu_gc_recipient_email"><?php esc_html_e( 'Recipient email', 'lafu-gift-cards' ); ?></label>
							<input type="text" id="lafu_gc_recipient_email" name="lafu_gc_recipient_email" inputmode="email" autocomplete="off" value="<?php echo esc_attr( $posted['recipient_email'] ); ?>">
							<small><?php esc_html_e( 'Several people? Separate the addresses with commas. Each gets their own card.', 'lafu-gift-cards' ); ?></small>
						</p>
					<?php endif; ?>
					<p>
						<label for="lafu_gc_sender_name"><?php esc_html_e( 'Your name', 'lafu-gift-cards' ); ?></label>
						<input type="text" id="lafu_gc_sender_name" name="lafu_gc_sender_name" maxlength="100" value="<?php echo esc_attr( $posted['sender_name'] ); ?>">
						<label class="lafu-gc-inline"><input type="checkbox" name="lafu_gc_anonymous" value="1" <?php checked( $posted['anonymous'] ); ?>> <?php esc_html_e( 'Send anonymously', 'lafu-gift-cards' ); ?></label>
					</p>
					<p>
						<label for="lafu_gc_message"><?php esc_html_e( 'Message', 'lafu-gift-cards' ); ?></label>
						<textarea id="lafu_gc_message" name="lafu_gc_message" rows="3" maxlength="<?php echo esc_attr( self::MESSAGE_LIMIT ); ?>"><?php echo esc_textarea( $posted['message'] ); ?></textarea>
					</p>
					<?php if ( ! $options['physical'] ) : ?>
						<p>
							<label for="lafu_gc_deliver_date"><?php esc_html_e( 'Delivery date', 'lafu-gift-cards' ); ?></label>
							<input type="date" id="lafu_gc_deliver_date" name="lafu_gc_deliver_date" min="<?php echo esc_attr( $today ); ?>" max="<?php echo esc_attr( wp_date( 'Y-m-d', strtotime( '+1 year' ) ) ); ?>" value="<?php echo esc_attr( $posted['deliver_date'] ); ?>">
							<small><?php esc_html_e( 'Leave empty to send right after payment.', 'lafu-gift-cards' ); ?></small>
						</p>
					<?php endif; ?>
				</div>
				<?php
				/**
				 * Extra buyer fields at the end of the gift card form.
				 *
				 * @param WC_Product $product Gift card product.
				 * @param array      $options Product options.
				 */
				do_action( 'lafu_gift_cards_buyer_form_fields', $product, $options );
				?>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Check the buyer form before the card goes to the cart.
	 *
	 * @param bool $passed     Validation so far.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public static function validate( $passed, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $passed || ! self::is_gift_card( $product ) ) {
			return $passed;
		}
		$data = self::build( $product, self::posted() );
		if ( is_wp_error( $data ) ) {
			wc_add_notice( $data->get_error_message(), 'error' );
			return false;
		}
		return true;
	}

	/**
	 * A gift card enters the cart only through its product form, with an
	 * amount and the card details. Express pay buttons, quick add buttons and
	 * other plugins that add the product directly are refused with a notice,
	 * so no order line can exist without a card behind it.
	 *
	 * @param array $item Cart item.
	 * @return array
	 * @throws Exception When a gift card has no card details.
	 */
	public static function require_card_data( $item ) {
		if ( isset( $item['data'] ) && self::is_gift_card( $item['data'] ) && empty( $item[ self::ITEM_META ] ) ) {
			throw new Exception( esc_html( self::form_needed_message() ) );
		}
		return $item;
	}

	/**
	 * The block cart API (product blocks, some express buttons) cannot send
	 * the gift card form, so it may not add gift cards.
	 *
	 * @param WC_Product $product Product.
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException For gift cards.
	 */
	public static function store_api_guard( $product ) {
		if ( self::is_gift_card( $product ) && class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'lafu_gift_cards_form_needed', esc_html( self::form_needed_message() ), 400 );
		}
	}

	/**
	 * Stripe (WooCommerce Stripe Gateway): no payment request buttons on a
	 * gift card page.
	 *
	 * @param bool         $hide Hide.
	 * @param WP_Post|null $post Product post.
	 * @return bool
	 */
	public static function hide_express_for_post( $hide, $post = null ) {
		return $hide || ( $post && self::is_gift_card( (int) $post->ID ) );
	}

	/**
	 * WooPayments: express buttons do not support gift card products.
	 *
	 * @param bool       $supported Supported.
	 * @param WC_Product $product   Product.
	 * @return bool
	 */
	public static function express_supported( $supported, $product = null ) {
		return $supported && ! self::is_gift_card( $product );
	}

	/**
	 * Payment Plugins (Stripe and PayPal): no product page buttons on a gift
	 * card.
	 *
	 * @param array      $gateways Gateways shown on the product page.
	 * @param WC_Product $product  Product.
	 * @return array
	 */
	public static function express_gateways( $gateways, $product = null ) {
		return self::is_gift_card( $product ) ? array() : $gateways;
	}

	/**
	 * Notice when a gift card is added without its product form.
	 *
	 * @return string
	 */
	private static function form_needed_message() {
		return __( 'Choose the amount and who the gift card is for on the gift card page, then add it to the cart.', 'lafu-gift-cards' );
	}

	/**
	 * Store the card details on the cart line. Each card is its own line.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product ID.
	 * @return array
	 */
	public static function cart_item_data( $cart_item_data, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! self::is_gift_card( $product ) ) {
			return $cart_item_data;
		}
		$data = self::build( $product, self::posted() );
		if ( ! is_wp_error( $data ) ) {
			$data['line']                      = wp_generate_uuid4();
			$cart_item_data[ self::ITEM_META ] = $data;
		}
		return $cart_item_data;
	}

	/**
	 * One card per recipient: several addresses set the quantity.
	 *
	 * @param int $quantity   Quantity.
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function quantity( $quantity, $product_id ) {
		if ( ! self::is_gift_card( $product_id ) ) {
			return $quantity;
		}
		$posted = self::posted();
		$emails = $posted['gift'] ? self::parse_emails( $posted['recipient_email'] ) : array();
		return count( $emails ) > 1 ? count( $emails ) : $quantity;
	}

	/**
	 * Charge the chosen amount.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function set_prices( $cart ) {
		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item[ self::ITEM_META ]['amount'] ) && isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
				$item['data']->set_price( $item[ self::ITEM_META ]['amount'] );
			}
		}
	}

	/**
	 * Card details under the product name in the cart and checkout.
	 *
	 * @param array $data Item data.
	 * @param array $item Cart item.
	 * @return array
	 */
	public static function item_data( $data, $item ) {
		if ( empty( $item[ self::ITEM_META ] ) ) {
			return $data;
		}
		foreach ( self::describe( $item[ self::ITEM_META ] ) as $label => $value ) {
			$data[] = array(
				'key'   => $label,
				'value' => $value,
			);
		}
		return $data;
	}

	/**
	 * A line sent to several people keeps one card per address.
	 *
	 * @param string $html     Quantity field.
	 * @param string $key      Cart item key.
	 * @param array  $item     Cart item.
	 * @return string
	 */
	public static function lock_quantity( $html, $key, $item = array() ) {
		if ( isset( $item[ self::ITEM_META ]['recipient_emails'] ) && count( $item[ self::ITEM_META ]['recipient_emails'] ) > 1 ) {
			return '<span class="lafu-gc-qty">' . esc_html( (string) $item['quantity'] ) . '</span>';
		}
		return $html;
	}

	/**
	 * Copy the card details to the order line.
	 *
	 * @param WC_Order_Item_Product $item     Order line.
	 * @param string                $cart_key Cart item key.
	 * @param array                 $values   Cart item.
	 */
	public static function order_item_meta( $item, $cart_key, $values ) {
		if ( empty( $values[ self::ITEM_META ] ) ) {
			return;
		}
		$item->add_meta_data( self::ITEM_META, $values[ self::ITEM_META ], true );
		foreach ( self::describe( $values[ self::ITEM_META ] ) as $label => $value ) {
			$item->add_meta_data( $label, $value, true );
		}
	}

	/**
	 * Price range in shop lists, for example "25 € – 100 €".
	 *
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function price_html( $html, $product ) {
		if ( ! self::is_gift_card( $product ) ) {
			return $html;
		}
		$options = self::options( $product );
		$low     = $options['amounts'] ? $options['amounts'][0] : $options['min'];
		$high    = $options['custom'] ? max( $options['max'], $options['amounts'] ? end( $options['amounts'] ) : 0 ) : end( $options['amounts'] );
		return $low < $high ? wc_format_price_range( $low, $high ) : wc_price( $low );
	}

	/**
	 * Selling a gift card carries no VAT, whatever the product says.
	 *
	 * @param string     $status  Tax status.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function tax_status( $status, $product ) {
		return self::is_gift_card( $product ) ? 'none' : $status;
	}

	/**
	 * Coupons never discount gift cards: a card is money, not goods.
	 *
	 * @param bool       $valid   Valid.
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function no_coupons( $valid, $product ) {
		return self::is_gift_card( $product ) ? false : $valid;
	}

	/**
	 * Shop lists cannot add a gift card without its form.
	 *
	 * @param bool       $supports Supports.
	 * @param string     $feature  Feature.
	 * @param WC_Product $product  Product.
	 * @return bool
	 */
	public static function no_ajax_add( $supports, $feature, $product ) {
		return 'ajax_add_to_cart' === $feature && self::is_gift_card( $product ) ? false : $supports;
	}

	/**
	 * Shop list button opens the product page.
	 *
	 * @param string     $url     URL.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function add_to_cart_url( $url, $product ) {
		return self::is_gift_card( $product ) ? $product->get_permalink() : $url;
	}

	/**
	 * Shop list button text.
	 *
	 * @param string     $text    Text.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function add_to_cart_text( $text, $product ) {
		return self::is_gift_card( $product ) && ! is_product() ? __( 'Choose amount', 'lafu-gift-cards' ) : $text;
	}

	/**
	 * Card data from the buyer form, checked against the product.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $posted  Form values.
	 * @return array|WP_Error
	 */
	public static function build( $product, array $posted ) {
		$options = self::options( $product );

		if ( 'custom' === $posted['amount'] || ! $options['amounts'] ) {
			if ( ! $options['custom'] ) {
				return new WP_Error( 'lafu_gc_amount', __( 'Choose an amount.', 'lafu-gift-cards' ) );
			}
			$amount = (float) Lafu_Gift_Cards_Store::parse_amount( $posted['custom_amount'] );
			if ( $amount < $options['min'] || $amount > $options['max'] ) {
				return new WP_Error(
					'lafu_gc_amount',
					/* translators: 1: lowest amount, 2: highest amount */
					sprintf( __( 'Choose an amount between %1$s and %2$s.', 'lafu-gift-cards' ), lafu_gift_cards_plain_price( $options['min'] ), lafu_gift_cards_plain_price( $options['max'] ) )
				);
			}
		} else {
			$amount = (float) Lafu_Gift_Cards_Store::parse_amount( $posted['amount'] );
			if ( ! in_array( $amount, $options['amounts'], true ) ) {
				return new WP_Error( 'lafu_gc_amount', __( 'Choose an amount.', 'lafu-gift-cards' ) );
			}
		}

		$design = $options['designs'][0];
		$colour = empty( Lafu_Gift_Cards_Designs::get( $design )['colour'] ) ? '' : $options['colour'];

		$gift   = $options['physical'] || $posted['gift'];
		$emails = array();
		if ( $gift && ! $options['physical'] ) {
			$emails = self::parse_emails( $posted['recipient_email'] );
			if ( ! $emails ) {
				return new WP_Error( 'lafu_gc_email', __( 'Enter the recipient email address.', 'lafu-gift-cards' ) );
			}
			if ( count( $emails ) > self::MAX_RECIPIENTS ) {
				/* translators: %d: number of addresses */
				return new WP_Error( 'lafu_gc_email', sprintf( __( 'Send to at most %d people at a time.', 'lafu-gift-cards' ), self::MAX_RECIPIENTS ) );
			}
			foreach ( $emails as $email ) {
				if ( ! is_email( $email ) ) {
					/* translators: %s: email address */
					return new WP_Error( 'lafu_gc_email', sprintf( __( '%s is not a valid email address.', 'lafu-gift-cards' ), $email ) );
				}
			}
		}

		$deliver = '';
		if ( $gift && ! $options['physical'] && '' !== $posted['deliver_date'] ) {
			$date = DateTime::createFromFormat( '!Y-m-d', $posted['deliver_date'], wp_timezone() );
			if ( ! $date || $date->format( 'Y-m-d' ) < wp_date( 'Y-m-d' ) || $date->getTimestamp() > strtotime( '+1 year' ) ) {
				return new WP_Error( 'lafu_gc_date', __( 'Choose a delivery date between today and one year from now.', 'lafu-gift-cards' ) );
			}
			$deliver = $date->format( 'Y-m-d' );
		}

		$data = array(
			'amount'           => $amount,
			'design'           => $design,
			'colour'           => $colour,
			'card_texts'       => $options['texts'],
			'gift'             => $gift,
			'recipient_name'   => $gift ? mb_substr( $posted['recipient_name'], 0, 100 ) : '',
			'recipient_emails' => $emails,
			'sender_name'      => $gift && ! $posted['anonymous'] ? mb_substr( $posted['sender_name'], 0, 100 ) : '',
			'anonymous'        => $gift && $posted['anonymous'],
			'message'          => $gift ? mb_substr( $posted['message'], 0, self::MESSAGE_LIMIT ) : '',
			'deliver_date'     => $deliver,
			'physical'         => $options['physical'],
		);

		/**
		 * Card data from the buyer form, after the built-in checks. Return a
		 * WP_Error to refuse the add to cart with that message.
		 *
		 * @param array      $data    Card data stored on the cart line and order line.
		 * @param WC_Product $product Gift card product.
		 */
		return apply_filters( 'lafu_gift_cards_build', $data, $product );
	}

	/**
	 * Human readable card details: label => value.
	 *
	 * @param array $data Card data.
	 * @return array
	 */
	public static function describe( array $data ) {
		$out = array();
		if ( ! empty( $data['recipient_emails'] ) || ! empty( $data['recipient_name'] ) ) {
			$to = trim( (string) $data['recipient_name'] );
			if ( ! empty( $data['recipient_emails'] ) ) {
				$to = trim( $to . ' ' . implode( ', ', $data['recipient_emails'] ) );
			}
			$out[ __( 'To', 'lafu-gift-cards' ) ] = $to;
		}
		if ( ! empty( $data['anonymous'] ) ) {
			$out[ __( 'From', 'lafu-gift-cards' ) ] = __( 'Anonymous', 'lafu-gift-cards' );
		} elseif ( ! empty( $data['sender_name'] ) ) {
			$out[ __( 'From', 'lafu-gift-cards' ) ] = $data['sender_name'];
		}
		if ( count( Lafu_Gift_Cards_Designs::all() ) > 1 && ! empty( $data['design'] ) ) {
			$design                                   = Lafu_Gift_Cards_Designs::get( $data['design'] );
			$out[ __( 'Design', 'lafu-gift-cards' ) ] = $design['label'];
			if ( ! empty( $design['colour'] ) && ! empty( $data['colour'] ) ) {
				$out[ __( 'Colour', 'lafu-gift-cards' ) ] = strtoupper( (string) $data['colour'] );
			}
		}
		if ( ! empty( $data['deliver_date'] ) ) {
			$out[ __( 'Delivery date', 'lafu-gift-cards' ) ] = wp_date( get_option( 'date_format' ), strtotime( $data['deliver_date'] . ' 12:00:00' ) );
		}
		if ( ! empty( $data['message'] ) ) {
			$out[ __( 'Message', 'lafu-gift-cards' ) ] = $data['message'];
		}
		return $out;
	}

	/**
	 * Email addresses from a comma or space separated list.
	 *
	 * @param string $value Raw list.
	 * @return array
	 */
	public static function parse_emails( $value ) {
		$emails = preg_split( '/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_unique( array_map( 'sanitize_email', array_map( 'strtolower', $emails ) ) ) );
	}

	/**
	 * Buyer form values from the add to cart request.
	 *
	 * @return array
	 */
	private static function posted() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public add to cart form: WooCommerce add to cart has no nonce and product pages are page cached.
		$text = static function ( $key ) {
			return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		};
		$data = array(
			'amount'          => $text( 'lafu_gc_amount' ),
			'custom_amount'   => $text( 'lafu_gc_custom_amount' ),
			'design'          => sanitize_key( $text( 'lafu_gc_design' ) ),
			'gift'            => ! empty( $_POST['lafu_gc_gift'] ),
			'recipient_name'  => $text( 'lafu_gc_recipient_name' ),
			'recipient_email' => $text( 'lafu_gc_recipient_email' ),
			'sender_name'     => $text( 'lafu_gc_sender_name' ),
			'anonymous'       => ! empty( $_POST['lafu_gc_anonymous'] ),
			'message'         => isset( $_POST['lafu_gc_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lafu_gc_message'] ) ) : '',
			'deliver_date'    => $text( 'lafu_gc_deliver_date' ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $data;
	}
}
