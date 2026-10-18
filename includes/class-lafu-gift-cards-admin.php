<?php
/**
 * Admin screens: card list, card details, in-store sale, import and export.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce > Gift cards.
 */
class Lafu_Gift_Cards_Admin {

	const PAGE       = 'lafu-gift-cards';
	const CAPABILITY = 'manage_woocommerce';
	const MAX_IMPORT = 5000;

	/**
	 * Hook menu, actions and notices.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		foreach ( array( 'issue', 'adjust', 'edit', 'resend', 'void', 'import', 'export', 'create_product', 'dismiss_setup' ) as $action ) {
			add_action( 'admin_post_lafu_gc_' . $action, array( __CLASS__, 'action_' . $action ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( LAFU_GIFT_CARDS_FILE ), array( __CLASS__, 'plugin_links' ) );
	}

	/**
	 * Gift cards under the WooCommerce menu.
	 */
	public static function menu() {
		add_submenu_page( 'woocommerce', __( 'Gift cards', 'lafu-gift-cards' ), __( 'Gift cards', 'lafu-gift-cards' ), self::CAPABILITY, self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Admin styles on the plugin page.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function assets( $hook ) {
		$order_screen = in_array( $hook, array( 'woocommerce_page_wc-orders', 'post.php' ), true );
		if ( 'woocommerce_page_' . self::PAGE !== $hook && ! $order_screen ) {
			return;
		}
		wp_enqueue_script( 'lafu-gift-cards-admin', plugins_url( 'assets/admin.js', LAFU_GIFT_CARDS_FILE ), array(), LAFU_GIFT_CARDS_VERSION, true );
		if ( ! $order_screen ) {
			lafu_gift_cards_register_assets();
			wp_enqueue_style( 'lafu-gift-cards-admin', plugins_url( 'assets/admin.css', LAFU_GIFT_CARDS_FILE ), array( 'lafu-gift-cards-card' ), LAFU_GIFT_CARDS_VERSION );
			wp_enqueue_script( 'lafu-gift-cards-colours', plugins_url( 'assets/admin-settings.js', LAFU_GIFT_CARDS_FILE ), array(), LAFU_GIFT_CARDS_VERSION, true );
		}
	}

	/**
	 * Links on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function plugin_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Gift cards', 'lafu-gift-cards' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=products&section=' . Lafu_Gift_Cards_Settings::SECTION ) ) . '">' . esc_html__( 'Settings', 'lafu-gift-cards' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Plugin page URL.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function page_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Card details URL.
	 *
	 * @param int $card_id Card ID.
	 * @return string
	 */
	public static function card_url( $card_id ) {
		return self::page_url(
			array(
				'view' => 'card',
				'card' => absint( $card_id ),
			)
		);
	}

	/**
	 * Offer to create the gift card product until one exists.
	 */
	public static function setup_notice() {
		if ( ! current_user_can( self::CAPABILITY ) || get_option( 'lafu_gift_cards_setup_dismissed' ) || self::card_product() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'woocommerce_page_' . self::PAGE, 'edit-product' ), true ) ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'LAFU Gift Cards is ready.', 'lafu-gift-cards' ); ?></strong> <?php esc_html_e( 'Create the gift card product your customers buy. You can change the amounts and designs before publishing it.', 'lafu-gift-cards' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lafu_gc_create_product' ), 'lafu_gc_create_product' ) ); ?>"><?php esc_html_e( 'Create gift card product', 'lafu-gift-cards' ); ?></a>
				<a class="button-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lafu_gc_dismiss_setup' ), 'lafu_gc_dismiss_setup' ) ); ?>"><?php esc_html_e( 'I will do it myself', 'lafu-gift-cards' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * The plugin page.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch.
		echo '<div class="wrap lafu-gc-admin">';
		self::header( $view );
		self::flash();
		if ( isset( self::extra_views()[ $view ] ) ) {
			/**
			 * Render an extra admin view added with lafu_gift_cards_admin_views.
			 */
			do_action( 'lafu_gift_cards_admin_view_' . $view );
		} elseif ( 'card' === $view ) {
			self::render_card();
		} elseif ( 'issue' === $view ) {
			self::counter();
		} elseif ( Lafu_Gift_Cards_Design_Page::VIEW === $view ) {
			Lafu_Gift_Cards_Design_Page::render();
		} elseif ( 'import' === $view ) {
			self::render_import();
		} else {
			self::render_list();
		}
		echo '</div>';
	}

	/**
	 * Extra admin views from extensions: key => label.
	 *
	 * @return array
	 */
	public static function extra_views() {
		/**
		 * Add sections to WooCommerce > Gift cards. Render them on the
		 * lafu_gift_cards_admin_view_{key} action.
		 *
		 * @param array $views Key => label.
		 */
		$views = (array) apply_filters( 'lafu_gift_cards_admin_views', array() );
		$clean = array();
		foreach ( $views as $key => $label ) {
			$key = sanitize_key( $key );
			if ( '' !== $key && ! in_array( $key, array( 'card', 'issue', 'import', Lafu_Gift_Cards_Design_Page::VIEW ), true ) ) {
				$clean[ $key ] = (string) $label;
			}
		}
		return $clean;
	}

	/**
	 * Views that live inside another section instead of having their own
	 * tab: view => parent view ('' is the card list). They are opened from
	 * buttons on the parent page, and the parent tab stays highlighted.
	 *
	 * @return array
	 */
	public static function parents() {
		/**
		 * Views shown under another tab: view => parent view.
		 *
		 * @param array $parents View => parent.
		 */
		$parents = (array) apply_filters(
			'lafu_gift_cards_admin_parents',
			array(
				'card'   => '',
				'import' => '',
			)
		);
		return array_map( 'sanitize_key', $parents );
	}

	/**
	 * Buttons on top of a section, for example Import on the card list.
	 * Add-ons add their own with the lafu_gift_cards_admin_actions filter.
	 *
	 * @param string $view    Section.
	 * @param array  $buttons Built-in buttons: label => URL.
	 * @return string Escaped HTML.
	 */
	public static function actions_html( $view, array $buttons ) {
		/**
		 * Buttons on top of a section: label => URL. The last one is the
		 * main button.
		 *
		 * @param array  $buttons Label => URL.
		 * @param string $view    Section.
		 */
		$buttons = (array) apply_filters( 'lafu_gift_cards_admin_actions', $buttons, $view );
		$html    = '';
		$last    = count( $buttons ) - 1;
		$i       = 0;
		foreach ( $buttons as $label => $url ) {
			$html .= '<a class="lafu-gc-btn' . ( $i === $last ? '' : ' lafu-gc-btn--ghost' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			++$i;
		}
		return $html;
	}

	/**
	 * Counter address that opens the Sell a card window again, after an error.
	 *
	 * @return string
	 */
	private static function sell_again_url() {
		return self::page_url(
			array(
				'view' => 'issue',
				'sell' => 1,
			)
		);
	}

	/**
	 * Brand bar with the plugin's sections.
	 *
	 * @param string $view Current view.
	 */
	private static function header( $view ) {
		$parents = self::parents();
		$items   = array(
			''                                => __( 'Cards', 'lafu-gift-cards' ),
			'issue'                           => __( 'Counter', 'lafu-gift-cards' ),
			Lafu_Gift_Cards_Design_Page::VIEW => __( 'Designer', 'lafu-gift-cards' ),
		);
		foreach ( self::extra_views() as $key => $label ) {
			if ( ! isset( $parents[ $key ] ) ) {
				$items[ $key ] = $label;
			}
		}
		$active = isset( $parents[ $view ] ) ? $parents[ $view ] : $view;
		/**
		 * View that holds the settings, for example a Pro settings page.
		 * Empty: the Settings link opens the WooCommerce settings section.
		 *
		 * @param string $view View key.
		 */
		$settings_view = sanitize_key( (string) apply_filters( 'lafu_gift_cards_settings_view', '' ) );
		$settings_url  = '' !== $settings_view ? self::page_url( array( 'view' => $settings_view ) ) : admin_url( 'admin.php?page=wc-settings&tab=products&section=' . Lafu_Gift_Cards_Settings::SECTION );
		$settings_on   = '' !== $settings_view && $settings_view === $view;
		?>
		<header class="lafu-gc-head">
			<a class="lafu-gc-brand" href="<?php echo esc_url( self::page_url() ); ?>">
				<img src="<?php echo esc_url( plugins_url( 'assets/lafu-logo.svg', LAFU_GIFT_CARDS_FILE ) ); ?>" alt="LAFU" width="74" height="22">
				<span class="lafu-gc-brand__name"><?php esc_html_e( 'Gift Cards', 'lafu-gift-cards' ); ?></span>
			</a>
			<nav class="lafu-gc-nav" aria-label="<?php esc_attr_e( 'Gift cards', 'lafu-gift-cards' ); ?>">
				<?php foreach ( $items as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::page_url( '' === $key ? array() : array( 'view' => $key ) ) ); ?>" class="<?php echo $active === $key ? 'is-active' : ''; ?>" <?php echo $active === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( $settings_url ); ?>" class="<?php echo $settings_on ? 'is-active' : ''; ?>" <?php echo $settings_on ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Settings', 'lafu-gift-cards' ); ?></a>
			</nav>
		</header>
		<hr class="wp-header-end">
		<?php
	}

	/**
	 * Card list with the key numbers on top.
	 */
	private static function render_list() {
		require_once LAFU_GIFT_CARDS_DIR . '/includes/class-lafu-gift-cards-list-table.php';
		$table = new Lafu_Gift_Cards_List_Table();
		$table->prepare_items();
		$stats = Lafu_Gift_Cards_Store::stats();
		$cur   = array( 'currency' => get_woocommerce_currency() );
		?>
		<div class="lafu-gc-titlebar">
			<h1><?php esc_html_e( 'Gift cards', 'lafu-gift-cards' ); ?></h1>
			<div class="lafu-gc-titlebar__actions">
				<?php
				$buttons = array(
					__( 'Export CSV', 'lafu-gift-cards' ) => wp_nonce_url( admin_url( 'admin-post.php?action=lafu_gc_export' ), 'lafu_gc_export' ),
					__( 'Import', 'lafu-gift-cards' )     => self::page_url( array( 'view' => 'import' ) ),
					__( 'Counter', 'lafu-gift-cards' )    => self::page_url( array( 'view' => 'issue' ) ),
				);
				echo self::actions_html( '', $buttons ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside actions_html().
				?>
			</div>
		</div>
		<div class="lafu-gc-stats">
			<div class="lafu-gc-stat lafu-gc-stat--main">
				<span><?php esc_html_e( 'Open balance', 'lafu-gift-cards' ); ?></span>
				<strong><?php echo wp_kses_post( wc_price( $stats['open'], $cur ) ); ?></strong>
				<small><?php esc_html_e( 'What customers can still spend', 'lafu-gift-cards' ); ?></small>
			</div>
			<div class="lafu-gc-stat">
				<span><?php esc_html_e( 'Active cards', 'lafu-gift-cards' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $stats['active'] ) ); ?></strong>
				<small>
					<?php
					/* translators: %s: number of cards */
					echo esc_html( sprintf( __( '%s expire in 30 days', 'lafu-gift-cards' ), number_format_i18n( $stats['expiring'] ) ) );
					?>
				</small>
			</div>
			<div class="lafu-gc-stat">
				<span><?php esc_html_e( 'Sold this month', 'lafu-gift-cards' ); ?></span>
				<strong><?php echo wp_kses_post( wc_price( $stats['sold_month'], $cur ) ); ?></strong>
				<small><?php esc_html_e( 'New cards, online and in the shop', 'lafu-gift-cards' ); ?></small>
			</div>
			<div class="lafu-gc-stat">
				<span><?php esc_html_e( 'Used this month', 'lafu-gift-cards' ); ?></span>
				<strong><?php echo wp_kses_post( wc_price( $stats['used_month'], $cur ) ); ?></strong>
				<small><?php esc_html_e( 'Paid with cards on orders', 'lafu-gift-cards' ); ?></small>
			</div>
		</div>
		<form method="get" class="lafu-gc-panel lafu-gc-panel--table">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
			<div class="lafu-gc-tablebar">
				<?php $table->views(); ?>
				<?php $table->search_box( __( 'Search cards', 'lafu-gift-cards' ), 'lafu-gc-search' ); ?>
			</div>
			<?php $table->display(); ?>
		</form>
		<?php
	}

	/**
	 * One card: the card, its balance, actions and full history.
	 */
	private static function render_card() {
		$card = Lafu_Gift_Cards_Store::get( isset( $_GET['card'] ) ? absint( wp_unslash( $_GET['card'] ) ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view.
		if ( ! $card ) {
			echo '<div class="lafu-gc-panel"><p>' . esc_html__( 'Gift card not found.', 'lafu-gift-cards' ) . '</p></div>';
			return;
		}
		$money  = array( 'currency' => $card->currency );
		$order  = $card->order_id ? wc_get_order( (int) $card->order_id ) : null;
		$active = Lafu_Gift_Cards_Store::STATUS_ACTIVE === $card->status;
		$used   = max( 0, (float) $card->initial_amount - (float) $card->balance );
		$share  = (float) $card->initial_amount > 0 ? min( 100, round( 100 * (float) $card->balance / (float) $card->initial_amount ) ) : 0;
		?>
		<p class="lafu-gc-back"><a href="<?php echo esc_url( self::page_url() ); ?>">&larr; <?php esc_html_e( 'All gift cards', 'lafu-gift-cards' ); ?></a></p>
		<section class="lafu-gc-hero">
			<div class="lafu-gc-hero__card">
				<?php echo Lafu_Gift_Cards_Designs::render( $card, 'web' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render(). ?>
			</div>
			<div class="lafu-gc-hero__info">
				<span class="lafu-gc-pill lafu-gc-pill--<?php echo esc_attr( Lafu_Gift_Cards_Account::status_key( $card ) ); ?>"><?php echo esc_html( Lafu_Gift_Cards_Account::status_label( $card ) ); ?></span>
				<h1 class="lafu-gc-code"><?php echo esc_html( $card->code ); ?></h1>
				<div class="lafu-gc-balance">
					<strong><?php echo wp_kses_post( wc_price( $card->balance, $money ) ); ?></strong>
					<span>
						<?php
						/* translators: %s: original amount */
						echo wp_kses_post( sprintf( __( 'left of %s', 'lafu-gift-cards' ), wc_price( $card->initial_amount, $money ) ) );
						?>
					</span>
				</div>
				<div class="lafu-gc-meter" role="img" aria-label="<?php echo esc_attr( $share . ' %' ); ?>"><span style="width:<?php echo esc_attr( $share ); ?>%"></span></div>
				<dl class="lafu-gc-facts">
					<div><dt><?php esc_html_e( 'Used', 'lafu-gift-cards' ); ?></dt><dd><?php echo wp_kses_post( wc_price( $used, $money ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Valid until', 'lafu-gift-cards' ); ?></dt><dd><?php echo esc_html( Lafu_Gift_Cards_Account::expiry_label( $card ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Recipient', 'lafu-gift-cards' ); ?></dt><dd><?php echo esc_html( trim( $card->recipient_name . ' ' . $card->recipient_email ) ? trim( $card->recipient_name . ' ' . $card->recipient_email ) : '–' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'From', 'lafu-gift-cards' ); ?></dt><dd><?php echo esc_html( '' !== (string) $card->sender_name ? $card->sender_name : '–' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Delivery', 'lafu-gift-cards' ); ?></dt><dd><?php echo esc_html( self::delivery_label( $card ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Order', 'lafu-gift-cards' ); ?></dt><dd>
						<?php if ( $order ) : ?>
							<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
						<?php else : ?>
							&ndash;
						<?php endif; ?>
					</dd></div>
				</dl>
				<div class="lafu-gc-actions">
					<a class="lafu-gc-btn lafu-gc-btn--ghost" href="<?php echo esc_url( Lafu_Gift_Cards_Account::print_url( $card ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print card', 'lafu-gift-cards' ); ?></a>
					<?php if ( $active && ! $card->physical && is_email( $card->recipient_email ) ) : ?>
						<?php self::action_button( 'resend', $card, __( 'Send email again', 'lafu-gift-cards' ) ); ?>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<div class="lafu-gc-grid">
			<section class="lafu-gc-panel">
				<h2><?php esc_html_e( 'Change balance', 'lafu-gift-cards' ); ?></h2>
				<p class="lafu-gc-hint"><?php esc_html_e( 'Add money with a plus amount, take money with a minus amount. The reason shows in the history.', 'lafu-gift-cards' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lafu-gc-form">
					<?php self::hidden_fields( 'adjust', $card ); ?>
					<label class="lafu-gc-field"><span><?php esc_html_e( 'Amount', 'lafu-gift-cards' ); ?></span><input type="text" name="amount" inputmode="decimal" required pattern="[-+]?[0-9]+([.,][0-9]+)?" placeholder="+10 / -5"></label>
					<label class="lafu-gc-field"><span><?php esc_html_e( 'Reason', 'lafu-gift-cards' ); ?></span><input type="text" name="note" maxlength="200" required></label>
					<button class="lafu-gc-btn"><?php esc_html_e( 'Save change', 'lafu-gift-cards' ); ?></button>
				</form>
			</section>

			<section class="lafu-gc-panel">
				<h2><?php esc_html_e( 'Details', 'lafu-gift-cards' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lafu-gc-form">
					<?php self::hidden_fields( 'edit', $card ); ?>
					<div class="lafu-gc-row">
						<label class="lafu-gc-field"><span><?php esc_html_e( 'Recipient name', 'lafu-gift-cards' ); ?></span><input type="text" name="recipient_name" maxlength="100" value="<?php echo esc_attr( $card->recipient_name ); ?>"></label>
						<label class="lafu-gc-field"><span><?php esc_html_e( 'Recipient email', 'lafu-gift-cards' ); ?></span><input type="email" name="recipient_email" value="<?php echo esc_attr( $card->recipient_email ); ?>"></label>
					</div>
					<label class="lafu-gc-field"><span><?php esc_html_e( 'Valid until', 'lafu-gift-cards' ); ?></span><input type="date" name="expires" value="<?php echo esc_attr( $card->expires_at ? wp_date( 'Y-m-d', strtotime( $card->expires_at . ' UTC' ) ) : '' ); ?>"></label>
					<label class="lafu-gc-field"><span><?php esc_html_e( 'Message', 'lafu-gift-cards' ); ?></span><textarea name="message" rows="2" maxlength="<?php echo esc_attr( Lafu_Gift_Cards_Product::MESSAGE_LIMIT ); ?>"><?php echo esc_textarea( $card->message ); ?></textarea></label>
					<button class="lafu-gc-btn lafu-gc-btn--ghost"><?php esc_html_e( 'Save details', 'lafu-gift-cards' ); ?></button>
				</form>
				<?php if ( Lafu_Gift_Cards_Store::STATUS_VOID !== $card->status ) : ?>
					<details class="lafu-gc-danger">
						<summary><?php esc_html_e( 'Cancel card', 'lafu-gift-cards' ); ?></summary>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lafu-gc-form" data-lafu-gc-confirm="<?php esc_attr_e( 'Cancel this gift card? The remaining balance can no longer be used.', 'lafu-gift-cards' ); ?>">
							<?php self::hidden_fields( 'void', $card ); ?>
							<p class="lafu-gc-hint"><?php esc_html_e( 'The remaining balance is written off and the card stops working. This cannot be undone.', 'lafu-gift-cards' ); ?></p>
							<label class="lafu-gc-field"><span><?php esc_html_e( 'Reason', 'lafu-gift-cards' ); ?></span><input type="text" name="note" maxlength="200" required></label>
							<button class="lafu-gc-btn lafu-gc-btn--danger"><?php esc_html_e( 'Cancel card', 'lafu-gift-cards' ); ?></button>
						</form>
					</details>
				<?php endif; ?>
			</section>
		</div>

		<?php
		/**
		 * Extra panels on the card page, above the history.
		 *
		 * @param object $card Card row.
		 */
		do_action( 'lafu_gift_cards_admin_card', $card );
		?>

		<section class="lafu-gc-panel lafu-gc-panel--table">
			<h2><?php esc_html_e( 'History', 'lafu-gift-cards' ); ?></h2>
			<table class="lafu-gc-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'lafu-gift-cards' ); ?></th>
						<th><?php esc_html_e( 'Event', 'lafu-gift-cards' ); ?></th>
						<th class="num"><?php esc_html_e( 'Amount', 'lafu-gift-cards' ); ?></th>
						<th class="num"><?php esc_html_e( 'Balance after', 'lafu-gift-cards' ); ?></th>
						<th><?php esc_html_e( 'Order', 'lafu-gift-cards' ); ?></th>
						<th><?php esc_html_e( 'Note', 'lafu-gift-cards' ); ?></th>
						<th><?php esc_html_e( 'By', 'lafu-gift-cards' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( Lafu_Gift_Cards_Store::ledger( (int) $card->id ) as $row ) : ?>
						<?php
						$row_order = $row->order_id ? wc_get_order( (int) $row->order_id ) : null;
						$actor     = $row->actor_id ? get_userdata( (int) $row->actor_id ) : null;
						?>
						<tr>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row->created_at . ' UTC' ) ) ); ?></td>
							<td><span class="lafu-gc-tag lafu-gc-tag--<?php echo esc_attr( $row->type ); ?>"><?php echo esc_html( self::type_label( $row->type ) ); ?></span></td>
							<td class="num <?php echo (float) $row->amount < 0 ? 'lafu-gc-neg' : 'lafu-gc-pos'; ?>"><?php echo wp_kses_post( ( (float) $row->amount > 0 ? '+' : '' ) . wc_price( $row->amount, $money ) ); ?></td>
							<td class="num"><?php echo wp_kses_post( wc_price( $row->balance_after, $money ) ); ?></td>
							<td>
								<?php if ( $row_order ) : ?>
									<a href="<?php echo esc_url( $row_order->get_edit_order_url() ); ?>">#<?php echo esc_html( $row_order->get_order_number() ); ?></a>
								<?php else : ?>
									&ndash;
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row->note ); ?></td>
							<td><?php echo esc_html( $actor ? $actor->display_name : __( 'System or customer', 'lafu-gift-cards' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
	}

	/**
	 * Counter: sell a card or give one away, with a live preview. Add-ons
	 * put their counter tools on top with lafu_gift_cards_counter_top.
	 */
	public static function counter() {
		$designs = Lafu_Gift_Cards_Designs::offered();
		$colours = Lafu_Gift_Cards_Designs::colours();
		$expiry  = Lafu_Gift_Cards_Settings::default_expiry();
		$first   = (string) key( $designs );
		$preview = (object) array(
			'design'         => $first,
			'initial_amount' => 50,
			'currency'       => get_woocommerce_currency(),
			'code'           => '',
			'recipient_name' => '',
			'sender_name'    => '',
			'expires_at'     => $expiry,
		);
		$palette = Lafu_Gift_Cards_Designs::preview_data( array_keys( $designs ) );

		$for_label  = Lafu_Gift_Cards_Designs::js_text( 'for' );
		$from_label = Lafu_Gift_Cards_Designs::js_text( 'from' );
		?>
		<div class="lafu-gc-titlebar">
			<h1><?php esc_html_e( 'Counter', 'lafu-gift-cards' ); ?></h1>
		</div>
		<?php
		/**
		 * Counter tools on top, for example finding and charging a card.
		 */
		do_action( 'lafu_gift_cards_counter_top' );

		/**
		 * More tiles next to Sell a card: key => label, text, url.
		 *
		 * @param array $tiles Tiles.
		 */
		$tiles = (array) apply_filters( 'lafu_gift_cards_counter_tiles', array() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reopens the sell window after an error.
		$reopen = ! empty( $_GET['sell'] );
		?>
		<div class="lafu-gc-postiles">
			<button type="button" class="lafu-gc-postiles__tile lafu-gc-postiles__tile--main" data-lafu-gc-open-sell>
				<span class="lafu-gc-postiles__icon" aria-hidden="true">+</span>
				<strong><?php esc_html_e( 'Sell a card', 'lafu-gift-cards' ); ?></strong>
				<span><?php esc_html_e( 'Amount, design and who it is for, paid in cash, by card or given free', 'lafu-gift-cards' ); ?></span>
			</button>
			<?php foreach ( $tiles as $tile ) : ?>
				<?php
				if ( empty( $tile['url'] ) || empty( $tile['label'] ) ) {
					continue;
				}
				?>
				<a class="lafu-gc-postiles__tile" href="<?php echo esc_url( $tile['url'] ); ?>">
					<strong><?php echo esc_html( $tile['label'] ); ?></strong>
					<span><?php echo esc_html( isset( $tile['text'] ) ? $tile['text'] : '' ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
		<dialog class="lafu-gc-modal" data-lafu-gc-sell-dialog aria-labelledby="lafu-gc-sell-title" <?php echo $reopen ? 'data-open' : ''; ?>>
			<div class="lafu-gc-modal__head">
				<h2 id="lafu-gc-sell-title"><?php esc_html_e( 'Sell a card', 'lafu-gift-cards' ); ?></h2>
				<button type="button" class="lafu-gc-modal__close" data-lafu-gc-close-sell aria-label="<?php esc_attr_e( 'Close', 'lafu-gift-cards' ); ?>">&times;</button>
			</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lafu-gc-sell" data-lafu-gc-sell data-money="<?php echo esc_attr( wp_json_encode( lafu_gift_cards_money_format() ) ); ?>" data-designs="<?php echo esc_attr( wp_json_encode( $palette ) ); ?>" data-for="<?php echo esc_attr( $for_label ); ?>" data-from="<?php echo esc_attr( $from_label ); ?>">
			<input type="hidden" name="action" value="lafu_gc_issue">
			<?php wp_nonce_field( 'lafu_gc_issue' ); ?>
			<div class="lafu-gc-sell__form">
				<section class="lafu-gc-panel">
					<h2><span class="lafu-gc-step">1</span><?php esc_html_e( 'Amount and payment', 'lafu-gift-cards' ); ?></h2>
					<div class="lafu-gc-chips" role="group" aria-label="<?php esc_attr_e( 'Amount', 'lafu-gift-cards' ); ?>">
						<?php foreach ( array( 25, 50, 100, 200 ) as $chip ) : ?>
							<button type="button" class="lafu-gc-chip" data-lafu-gc-chip="<?php echo esc_attr( $chip ); ?>"><?php echo esc_html( Lafu_Gift_Cards_Product::amount_label( $chip ) ); ?></button>
						<?php endforeach; ?>
					</div>
					<label class="lafu-gc-field lafu-gc-field--money"><span><?php esc_html_e( 'Amount', 'lafu-gift-cards' ); ?></span>
						<span class="lafu-gc-money"><input type="text" name="amount" value="50" inputmode="decimal" required pattern="[0-9]+([.,][0-9]+)?" data-lafu-gc-amount><em><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></em></span>
					</label>
					<fieldset class="lafu-gc-segment">
						<legend><?php esc_html_e( 'Paid with', 'lafu-gift-cards' ); ?></legend>
						<label><input type="radio" name="payment" value="cash" checked><span><?php esc_html_e( 'Cash', 'lafu-gift-cards' ); ?></span></label>
						<label><input type="radio" name="payment" value="card"><span><?php esc_html_e( 'Card', 'lafu-gift-cards' ); ?></span></label>
						<label><input type="radio" name="payment" value="free"><span><?php esc_html_e( 'Free (gift or compensation)', 'lafu-gift-cards' ); ?></span></label>
					</fieldset>
					<label class="lafu-gc-field"><span><?php esc_html_e( 'Buyer email for the receipt', 'lafu-gift-cards' ); ?> <i><?php esc_html_e( 'optional', 'lafu-gift-cards' ); ?></i></span><input type="email" name="buyer_email"></label>
				</section>

				<section class="lafu-gc-panel">
					<h2><span class="lafu-gc-step">2</span><?php esc_html_e( 'Who gets it', 'lafu-gift-cards' ); ?></h2>
					<div class="lafu-gc-row">
						<label class="lafu-gc-field"><span><?php esc_html_e( 'Recipient name', 'lafu-gift-cards' ); ?> <i><?php esc_html_e( 'optional', 'lafu-gift-cards' ); ?></i></span><input type="text" name="recipient_name" maxlength="100" data-lafu-gc-to></label>
						<label class="lafu-gc-field"><span><?php esc_html_e( 'From', 'lafu-gift-cards' ); ?> <i><?php esc_html_e( 'optional', 'lafu-gift-cards' ); ?></i></span><input type="text" name="sender_name" maxlength="100" data-lafu-gc-from></label>
					</div>
					<label class="lafu-gc-field"><span><?php esc_html_e( 'Text on the card', 'lafu-gift-cards' ); ?> <i><?php esc_html_e( 'optional', 'lafu-gift-cards' ); ?></i></span><input type="text" name="card_line" maxlength="60" placeholder="<?php echo esc_attr( Lafu_Gift_Cards_Designs::texts()['line'] ); ?>" data-lafu-gc-card-line></label>
					<label class="lafu-gc-field"><span><?php esc_html_e( 'Message', 'lafu-gift-cards' ); ?> <i><?php esc_html_e( 'optional', 'lafu-gift-cards' ); ?></i></span><textarea name="message" rows="2" maxlength="<?php echo esc_attr( Lafu_Gift_Cards_Product::MESSAGE_LIMIT ); ?>"></textarea></label>
					<label class="lafu-gc-switch"><input type="checkbox" name="send_email" value="1" data-lafu-gc-send><span><?php esc_html_e( 'Email the card to the recipient', 'lafu-gift-cards' ); ?></span></label>
					<label class="lafu-gc-field" data-lafu-gc-email hidden><span><?php esc_html_e( 'Recipient email', 'lafu-gift-cards' ); ?></span><input type="email" name="recipient_email"></label>
				</section>

				<section class="lafu-gc-panel">
					<h2><span class="lafu-gc-step">3</span><?php esc_html_e( 'Look and validity', 'lafu-gift-cards' ); ?></h2>
					<div class="lafu-gc-swatches" role="radiogroup" aria-label="<?php esc_attr_e( 'Design', 'lafu-gift-cards' ); ?>">
						<?php foreach ( $designs as $key => $design ) : ?>
							<label class="lafu-gc-swatch">
								<input type="radio" name="design" value="<?php echo esc_attr( $key ); ?>" <?php checked( $first, $key ); ?> data-lafu-gc-design>
								<span style="<?php echo esc_attr( Lafu_Gift_Cards_Designs::swatch_style( $key ) ); ?>"><?php echo esc_html( $design['label'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<?php if ( isset( $designs['colour'] ) ) : ?>
						<div class="lafu-gc-colours" role="radiogroup" aria-label="<?php esc_attr_e( 'Colour', 'lafu-gift-cards' ); ?>" data-lafu-gc-colours>
							<?php foreach ( $colours as $index => $hex ) : ?>
								<label class="lafu-gc-colour">
									<input type="radio" name="colour" value="<?php echo esc_attr( $hex ); ?>" <?php checked( 0, $index ); ?>>
									<span style="background:<?php echo esc_attr( $hex ); ?>">
										<?php
										/* translators: %d: number of the colour */
										echo '<span class="screen-reader-text">' . esc_html( sprintf( __( 'Colour %d', 'lafu-gift-cards' ), $index + 1 ) ) . '</span>';
										?>
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<label class="lafu-gc-field"><span><?php esc_html_e( 'Valid until', 'lafu-gift-cards' ); ?> <i><?php esc_html_e( 'empty = no expiry', 'lafu-gift-cards' ); ?></i></span><input type="date" name="expires" value="<?php echo esc_attr( $expiry ? substr( $expiry, 0, 10 ) : '' ); ?>"></label>
				</section>
			</div>

			<aside class="lafu-gc-sell__preview">
				<div class="lafu-gc-sticky">
					<p class="lafu-gc-eyebrow"><?php esc_html_e( 'Preview', 'lafu-gift-cards' ); ?></p>
					<div data-lafu-gc-preview>
						<?php echo Lafu_Gift_Cards_Designs::render( $preview, 'web' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render(). ?>
					</div>
					<p class="lafu-gc-hint"><?php esc_html_e( 'The code and QR code are created when you save. A paid sale is saved as a normal order, so it appears in your sales and bookkeeping.', 'lafu-gift-cards' ); ?></p>
					<button class="lafu-gc-btn lafu-gc-btn--block"><?php esc_html_e( 'Create card', 'lafu-gift-cards' ); ?></button>
				</div>
			</aside>
		</form>
		</dialog>
		<?php
	}

	/**
	 * Import existing cards from a CSV file.
	 */
	private static function render_import() {
		?>
		<div class="lafu-gc-titlebar">
			<h1><?php esc_html_e( 'Import gift cards', 'lafu-gift-cards' ); ?></h1>
			<div class="lafu-gc-titlebar__actions"><a class="lafu-gc-btn lafu-gc-btn--ghost" href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( 'Back to cards', 'lafu-gift-cards' ); ?></a></div>
		</div>
		<div class="lafu-gc-grid">
			<section class="lafu-gc-panel">
				<h2><span class="lafu-gc-step">1</span><?php esc_html_e( 'Make a CSV file', 'lafu-gift-cards' ); ?></h2>
				<p class="lafu-gc-hint"><?php esc_html_e( 'Bring in cards you already sold, for example paper cards. Customers keep using their own codes.', 'lafu-gift-cards' ); ?></p>
				<p><?php esc_html_e( 'Columns code and balance are required. initial, expires (YYYY-MM-DD or DD.MM.YYYY), email, name and note are optional. Comma or semicolon separated, with a header row.', 'lafu-gift-cards' ); ?></p>
				<pre class="lafu-gc-sample">code;balance;initial;expires;email;name;note
PAPER-0001;50;50;2027-12-31;;;Paper card
PAPER-0002;12,50;30;;anna@example.com;Anna;</pre>
			</section>
			<section class="lafu-gc-panel">
				<h2><span class="lafu-gc-step">2</span><?php esc_html_e( 'Upload it', 'lafu-gift-cards' ); ?></h2>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lafu-gc-form">
					<input type="hidden" name="action" value="lafu_gc_import">
					<?php wp_nonce_field( 'lafu_gc_import' ); ?>
					<label class="lafu-gc-drop">
						<input type="file" name="file" accept=".csv,text/csv" required>
						<strong><?php esc_html_e( 'Choose a CSV file', 'lafu-gift-cards' ); ?></strong>
						<span><?php esc_html_e( 'or drop it here', 'lafu-gift-cards' ); ?></span>
					</label>
					<p class="lafu-gc-hint"><?php esc_html_e( 'Rows with a code that already exists are skipped and listed after the import.', 'lafu-gift-cards' ); ?></p>
					<button class="lafu-gc-btn"><?php esc_html_e( 'Import cards', 'lafu-gift-cards' ); ?></button>
				</form>
			</section>
		</div>
		<?php
	}

	/**
	 * Sell or issue a card from the admin form.
	 */
	public static function action_issue() {
		self::guard( 'lafu_gc_issue' );
		$amount  = self::posted_amount( 'amount' );
		$payment = self::posted_text( 'payment' );
		$send    = ! empty( $_POST['send_email'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
		$email   = sanitize_email( self::posted_text( 'recipient_email' ) );
		if ( $amount <= 0 ) {
			self::back( self::sell_again_url(), 'error', __( 'The amount must be more than zero.', 'lafu-gift-cards' ) );
		}
		if ( $send && ! is_email( $email ) ) {
			self::back( self::sell_again_url(), 'error', __( 'Enter the recipient email address to send the card.', 'lafu-gift-cards' ) );
		}
		$expires = self::posted_date( 'expires' );
		$card    = array(
			'amount'          => $amount,
			'design'          => Lafu_Gift_Cards_Designs::resolve( sanitize_key( self::posted_text( 'design' ) ) ),
			'meta'            => array_merge(
				sanitize_hex_color( self::posted_text( 'colour' ) ) ? array( 'colour' => sanitize_hex_color( self::posted_text( 'colour' ) ) ) : array(),
				Lafu_Gift_Cards_Designs::clean_texts( array( 'line' => self::posted_text( 'card_line' ) ) )
			),
			'recipient_name'  => mb_substr( self::posted_text( 'recipient_name' ), 0, 100 ),
			'recipient_email' => $email,
			'sender_name'     => mb_substr( self::posted_text( 'sender_name' ), 0, 100 ),
			'message'         => mb_substr( isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '', 0, Lafu_Gift_Cards_Product::MESSAGE_LIMIT ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
			'physical'        => ! $send,
			'expires_at'      => $expires,
		);

		if ( 'free' === $payment ) {
			$card_id = Lafu_Gift_Cards_Store::create( $card, 'issue', __( 'Issued by the shop without payment', 'lafu-gift-cards' ) );
			if ( is_wp_error( $card_id ) ) {
				self::back( self::sell_again_url(), 'error', $card_id->get_error_message() );
			}
			if ( $send ) {
				Lafu_Gift_Cards_Issuer::deliver( $card_id );
			}
			self::back( self::card_url( $card_id ), 'success', __( 'Gift card created.', 'lafu-gift-cards' ) );
		}

		$card_id = self::sell_in_store( $card, 'card' === $payment ? __( 'Card in the shop', 'lafu-gift-cards' ) : __( 'Cash in the shop', 'lafu-gift-cards' ), sanitize_email( self::posted_text( 'buyer_email' ) ), $expires );
		if ( is_wp_error( $card_id ) ) {
			self::back( self::sell_again_url(), 'error', $card_id->get_error_message() );
		}
		self::back( self::card_url( $card_id ), 'success', __( 'Gift card sold and saved as an order.', 'lafu-gift-cards' ) );
	}

	/**
	 * Record an in-store sale as a paid order. The normal order flow then
	 * creates the card, so online and in-store sales work the same way.
	 *
	 * @param array       $card        Card fields.
	 * @param string      $paid_with   Payment method title.
	 * @param string      $buyer_email Receipt address or empty.
	 * @param string|null $expires     Expiry chosen in the form.
	 * @return int|WP_Error Card ID.
	 */
	private static function sell_in_store( array $card, $paid_with, $buyer_email, $expires ) {
		$order = wc_create_order( array( 'created_via' => 'lafu-gift-cards' ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$product = self::card_product();
		if ( $product ) {
			$item = new WC_Order_Item_Product();
			$item->set_product( $product );
		} else {
			$item = new WC_Order_Item_Product();
			$item->set_name( __( 'Gift card', 'lafu-gift-cards' ) );
		}
		$item->set_quantity( 1 );
		$item->set_subtotal( $card['amount'] );
		$item->set_total( $card['amount'] );
		$data = array(
			'amount'           => $card['amount'],
			'design'           => $card['design'],
			'gift'             => '' !== $card['recipient_name'] || '' !== $card['recipient_email'],
			'recipient_name'   => $card['recipient_name'],
			'recipient_emails' => $card['recipient_email'] ? array( $card['recipient_email'] ) : array(),
			'sender_name'      => $card['sender_name'],
			'anonymous'        => false,
			'message'          => $card['message'],
			'deliver_date'     => '',
			'physical'         => $card['physical'],
		);
		$item->add_meta_data( Lafu_Gift_Cards_Product::ITEM_META, $data, true );
		foreach ( Lafu_Gift_Cards_Product::describe( $data ) as $label => $value ) {
			$item->add_meta_data( $label, $value, true );
		}
		$order->add_item( $item );
		if ( is_email( $buyer_email ) ) {
			$order->set_billing_email( $buyer_email );
		}
		$order->set_payment_method_title( $paid_with );
		$order->calculate_totals( false );
		$order->add_order_note( __( 'Gift card sold in the shop.', 'lafu-gift-cards' ), 0, true );
		$order->save();

		add_filter( 'lafu_gift_cards_default_expiry', array( __CLASS__, 'form_expiry' ) );
		self::$form_expiry = $expires;
		$order->payment_complete();
		$order->update_status( 'completed' );
		remove_filter( 'lafu_gift_cards_default_expiry', array( __CLASS__, 'form_expiry' ) );

		$item = new WC_Order_Item_Product( $item->get_id() );
		$ids  = array_filter( lafu_gift_cards_meta_array( $item, Lafu_Gift_Cards_Issuer::CARDS_META ) );
		if ( ! $ids ) {
			return new WP_Error( 'lafu_gc_issue', __( 'The order was saved but the card was not created. See the order notes.', 'lafu-gift-cards' ) );
		}
		return (int) reset( $ids );
	}

	/**
	 * Expiry chosen in the in-store form.
	 *
	 * @var string|null
	 */
	private static $form_expiry = null;

	/**
	 * Use the expiry from the in-store form for the card the order creates.
	 *
	 * @return string|null
	 */
	public static function form_expiry() {
		return self::$form_expiry;
	}

	/**
	 * Add or take money from a card.
	 */
	public static function action_adjust() {
		$card   = self::guard( 'lafu_gc_adjust', true );
		$amount = self::posted_amount( 'amount', true );
		$note   = mb_substr( self::posted_text( 'note' ), 0, 200 );
		if ( 0.0 === $amount || '' === $note ) {
			self::back( self::card_url( (int) $card->id ), 'error', __( 'Enter an amount and a reason.', 'lafu-gift-cards' ) );
		}
		$result = $amount > 0
			? Lafu_Gift_Cards_Store::credit( (int) $card->id, $amount, 0, 'adjust', $note )
			: Lafu_Gift_Cards_Store::debit( (int) $card->id, -$amount, 0, 'adjust', $note );
		if ( is_wp_error( $result ) ) {
			self::back( self::card_url( (int) $card->id ), 'error', $result->get_error_message() );
		}
		self::back( self::card_url( (int) $card->id ), 'success', __( 'Balance changed.', 'lafu-gift-cards' ) );
	}

	/**
	 * Save recipient, expiry and message.
	 */
	public static function action_edit() {
		$card    = self::guard( 'lafu_gc_edit', true );
		$email   = sanitize_email( self::posted_text( 'recipient_email' ) );
		$expires = self::posted_date( 'expires' );
		$fields  = array(
			'recipient_name'  => mb_substr( self::posted_text( 'recipient_name' ), 0, 100 ),
			'recipient_email' => $email,
			'message'         => mb_substr( isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '', 0, Lafu_Gift_Cards_Product::MESSAGE_LIMIT ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
			'expires_at'      => $expires,
		);
		if ( Lafu_Gift_Cards_Store::STATUS_EXPIRED === $card->status && ( null === $expires || ! Lafu_Gift_Cards_Store::is_past( $expires ) ) ) {
			$fields['status'] = Lafu_Gift_Cards_Store::STATUS_ACTIVE;
		}
		Lafu_Gift_Cards_Store::update( (int) $card->id, $fields );
		self::back( self::card_url( (int) $card->id ), 'success', __( 'Details saved.', 'lafu-gift-cards' ) );
	}

	/**
	 * Email the card again.
	 */
	public static function action_resend() {
		$card = self::guard( 'lafu_gc_resend', true );
		if ( Lafu_Gift_Cards_Issuer::deliver( (int) $card->id, true ) ) {
			self::back( self::card_url( (int) $card->id ), 'success', __( 'Gift card email sent.', 'lafu-gift-cards' ) );
		}
		self::back( self::card_url( (int) $card->id ), 'error', __( 'The email could not be sent. Check that the gift card email is turned on under WooCommerce > Settings > Emails.', 'lafu-gift-cards' ) );
	}

	/**
	 * Cancel a card.
	 */
	public static function action_void() {
		$card   = self::guard( 'lafu_gc_void', true );
		$result = Lafu_Gift_Cards_Store::void( (int) $card->id, mb_substr( self::posted_text( 'note' ), 0, 200 ) );
		if ( is_wp_error( $result ) ) {
			self::back( self::card_url( (int) $card->id ), 'error', $result->get_error_message() );
		}
		self::back( self::card_url( (int) $card->id ), 'success', __( 'Gift card cancelled.', 'lafu-gift-cards' ) );
	}

	/**
	 * Import cards from an uploaded CSV file.
	 */
	public static function action_import() {
		self::guard( 'lafu_gc_import' );
		$back = self::page_url( array( 'view' => 'import' ) );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in guard().
		$tmp = isset( $_FILES['file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['file']['tmp_name'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			self::back( $back, 'error', __( 'Choose a CSV file to import.', 'lafu-gift-cards' ) );
		}
		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		$content = $wp_filesystem ? (string) $wp_filesystem->get_contents( $tmp ) : '';
		wp_delete_file( $tmp );

		$lines = preg_split( '/\r\n|\r|\n/', preg_replace( '/^\xEF\xBB\xBF/', '', $content ) );
		$lines = array_values( array_filter( $lines, 'strlen' ) );
		if ( count( $lines ) < 2 ) {
			self::back( $back, 'error', __( 'The file has no cards.', 'lafu-gift-cards' ) );
		}
		if ( count( $lines ) - 1 > self::MAX_IMPORT ) {
			/* translators: %d: row limit */
			self::back( $back, 'error', sprintf( __( 'Import at most %d cards at a time.', 'lafu-gift-cards' ), self::MAX_IMPORT ) );
		}
		$delimiter = substr_count( $lines[0], ';' ) > substr_count( $lines[0], ',' ) ? ';' : ',';
		$header    = array_map( 'sanitize_key', str_getcsv( array_shift( $lines ), $delimiter, '"', '\\' ) );
		if ( ! in_array( 'code', $header, true ) || ! in_array( 'balance', $header, true ) ) {
			self::back( $back, 'error', __( 'The header row needs the columns code and balance.', 'lafu-gift-cards' ) );
		}

		$done   = 0;
		$errors = array();
		foreach ( $lines as $index => $line ) {
			$values = str_getcsv( $line, $delimiter, '"', '\\' );
			$row    = array();
			foreach ( $header as $col => $key ) {
				$row[ $key ] = isset( $values[ $col ] ) ? sanitize_text_field( $values[ $col ] ) : '';
			}
			$result = self::import_row( $row );
			if ( is_wp_error( $result ) ) {
				/* translators: 1: line number, 2: error */
				$errors[] = sprintf( __( 'Line %1$d: %2$s', 'lafu-gift-cards' ), $index + 2, $result->get_error_message() );
			} else {
				++$done;
			}
		}

		/* translators: %d: number of cards */
		$message = sprintf( _n( '%d card imported.', '%d cards imported.', $done, 'lafu-gift-cards' ), $done );
		if ( $errors ) {
			/* translators: %d: number of skipped rows */
			$message .= ' ' . sprintf( _n( '%d row skipped:', '%d rows skipped:', count( $errors ), 'lafu-gift-cards' ), count( $errors ) ) . ' ' . implode( ' ', array_slice( $errors, 0, 20 ) );
		}
		self::back( self::page_url(), $errors ? 'warning' : 'success', $message );
	}

	/**
	 * Download every card as CSV.
	 */
	public static function action_export() {
		if ( ! current_user_can( self::CAPABILITY ) || ! check_admin_referer( 'lafu_gc_export' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'lafu-gift-cards' ), '', array( 'response' => 403 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=gift-cards-' . gmdate( 'Y-m-d' ) . '.csv' );
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UTF-8 byte order mark for spreadsheet apps.
		echo self::csv_line( array( 'code', 'status', 'currency', 'initial', 'balance', 'recipient_name', 'recipient_email', 'sender_name', 'order', 'expires', 'created' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, cells neutralised in csv_line().
		$after = 0;
		do {
			$rows = Lafu_Gift_Cards_Store::export_page( $after );
			foreach ( $rows as $card ) {
				$after = (int) $card->id;
				$line  = self::csv_line(
					array(
						$card->code,
						$card->status,
						$card->currency,
						wc_format_decimal( $card->initial_amount, wc_get_price_decimals() ),
						wc_format_decimal( $card->balance, wc_get_price_decimals() ),
						$card->recipient_name,
						$card->recipient_email,
						$card->sender_name,
						$card->order_id ? $card->order_id : '',
						$card->expires_at ? substr( $card->expires_at, 0, 10 ) : '',
						$card->created_at,
					)
				);
				echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, cells neutralised in csv_line().
			}
		} while ( $rows );
		exit;
	}

	/**
	 * A draft gift card product with ready amounts and one design.
	 *
	 * @param string $design Design key.
	 * @return int Product ID.
	 */
	public static function create_card_product( $design ) {
		$label = Lafu_Gift_Cards_Designs::get( $design )['label'];
		/* translators: %s: design name, for example Christmas */
		$name = 'colour' === $design ? __( 'Gift card', 'lafu-gift-cards' ) : sprintf( __( 'Gift card: %s', 'lafu-gift-cards' ), $label );

		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'draft' );
		$product->set_short_description( __( 'Give a gift that always fits. Choose the amount, and send the card by email right away or on the day you choose.', 'lafu-gift-cards' ) );
		$product->update_meta_data( Lafu_Gift_Cards_Product::FLAG, 'yes' );
		$product->update_meta_data( '_lafu_gc_amounts', '25, 50, 100' );
		$product->update_meta_data( '_lafu_gc_custom', 'yes' );
		$product->update_meta_data( '_lafu_gc_min', '10' );
		$product->update_meta_data( '_lafu_gc_max', '500' );
		$product->update_meta_data( '_lafu_gc_physical', 'no' );
		$product->update_meta_data( '_lafu_gc_design', $design );
		$shop_colours = Lafu_Gift_Cards_Designs::design_colours();
		$product->update_meta_data( '_lafu_gc_colour', isset( $shop_colours['colour'] ) ? $shop_colours['colour'] : Lafu_Gift_Cards_Designs::colours()[0] );
		Lafu_Gift_Cards_Product::apply_card_defaults( $product );
		return (int) $product->save();
	}

	/**
	 * Create a draft gift card product and open it in the editor.
	 */
	public static function action_create_product() {
		if ( ! current_user_can( self::CAPABILITY ) || ! check_admin_referer( 'lafu_gc_create_product' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'lafu-gift-cards' ), '', array( 'response' => 403 ) );
		}
		$id = self::create_card_product( Lafu_Gift_Cards_Designs::resolve( '' ) );
		update_option( 'lafu_gift_cards_product_id', $id, false );
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit&lafu_gc_image=1' ) );
		exit;
	}

	/**
	 * Hide the setup notice.
	 */
	public static function action_dismiss_setup() {
		if ( ! current_user_can( self::CAPABILITY ) || ! check_admin_referer( 'lafu_gc_dismiss_setup' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'lafu-gift-cards' ), '', array( 'response' => 403 ) );
		}
		update_option( 'lafu_gift_cards_setup_dismissed', 1, false );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * The shop's gift card product, if one exists.
	 *
	 * @return WC_Product|null
	 */
	public static function card_product() {
		$product = wc_get_product( (int) get_option( 'lafu_gift_cards_product_id' ) );
		return $product && 'trash' !== $product->get_status() && Lafu_Gift_Cards_Product::is_gift_card( $product ) ? $product : null;
	}

	/**
	 * Create one imported card.
	 *
	 * @param array $row Column => value.
	 * @return int|WP_Error
	 */
	private static function import_row( array $row ) {
		$balance = Lafu_Gift_Cards_Store::parse_amount( isset( $row['balance'] ) ? $row['balance'] : '' );
		$initial = isset( $row['initial'] ) && '' !== $row['initial'] ? Lafu_Gift_Cards_Store::parse_amount( $row['initial'] ) : $balance;
		if ( null === $balance || $balance < 0 || null === $initial ) {
			return new WP_Error( 'lafu_gc_amount', __( 'The balance is not a number.', 'lafu-gift-cards' ) );
		}
		$expires = null;
		if ( ! empty( $row['expires'] ) ) {
			$date = DateTime::createFromFormat( '!Y-m-d', $row['expires'], wp_timezone() );
			if ( ! $date ) {
				$date = DateTime::createFromFormat( '!d.m.Y', $row['expires'], wp_timezone() );
			}
			if ( ! $date ) {
				return new WP_Error( 'lafu_gc_date', __( 'The expiry date is not valid.', 'lafu-gift-cards' ) );
			}
			$expires = gmdate( 'Y-m-d H:i:s', $date->getTimestamp() + DAY_IN_SECONDS - 1 );
		}
		$email = isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '';
		return Lafu_Gift_Cards_Store::create(
			array(
				'code'            => isset( $row['code'] ) ? mb_substr( $row['code'], 0, 64 ) : '',
				'amount'          => $balance,
				'initial_amount'  => max( $initial, $balance ),
				'recipient_email' => $email,
				'recipient_name'  => isset( $row['name'] ) ? mb_substr( $row['name'], 0, 100 ) : '',
				'physical'        => true,
				'expires_at'      => $expires,
			),
			'import',
			isset( $row['note'] ) && '' !== $row['note'] ? mb_substr( $row['note'], 0, 200 ) : __( 'Imported', 'lafu-gift-cards' )
		);
	}

	/**
	 * Check capability and nonce of an admin action, and load the card.
	 *
	 * @param string $action   Nonce action.
	 * @param bool   $needs_card Load the card from the form.
	 * @return object|null Card row when $needs_card.
	 */
	private static function guard( $action, $needs_card = false ) {
		if ( ! current_user_can( self::CAPABILITY ) || ! check_admin_referer( $action ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'lafu-gift-cards' ), '', array( 'response' => 403 ) );
		}
		if ( ! $needs_card ) {
			return null;
		}
		$card = Lafu_Gift_Cards_Store::get( isset( $_POST['card'] ) ? absint( wp_unslash( $_POST['card'] ) ) : 0 );
		if ( ! $card ) {
			self::back( self::page_url(), 'error', __( 'Gift card not found.', 'lafu-gift-cards' ) );
		}
		return $card;
	}

	/**
	 * Hidden fields of a card action form.
	 *
	 * @param string $action Action name without prefix.
	 * @param object $card   Card row.
	 */
	private static function hidden_fields( $action, $card ) {
		echo '<input type="hidden" name="action" value="' . esc_attr( 'lafu_gc_' . $action ) . '">';
		echo '<input type="hidden" name="card" value="' . esc_attr( (int) $card->id ) . '">';
		wp_nonce_field( 'lafu_gc_' . $action );
	}

	/**
	 * One-button form for a card action.
	 *
	 * @param string $action Action name without prefix.
	 * @param object $card   Card row.
	 * @param string $label  Button text.
	 */
	private static function action_button( $action, $card, $label ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="lafu-gc-inline-form">';
		self::hidden_fields( $action, $card );
		echo '<button class="button">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Posted text field.
	 *
	 * @param string $key Field.
	 * @return string
	 */
	private static function posted_text( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Every caller runs guard() first.
	}

	/**
	 * Posted money amount.
	 *
	 * @param string $key    Field.
	 * @param bool   $signed Allow a minus sign.
	 * @return float
	 */
	private static function posted_amount( $key, $signed = false ) {
		$value = Lafu_Gift_Cards_Store::parse_amount( self::posted_text( $key ) );
		if ( null === $value || ( ! $signed && $value < 0 ) ) {
			return 0.0;
		}
		return $value;
	}

	/**
	 * Posted date (Y-m-d, shop time zone) as the end of that day in UTC.
	 *
	 * @param string $key Field.
	 * @return string|null
	 */
	private static function posted_date( $key ) {
		$value = self::posted_text( $key );
		$date  = '' !== $value ? DateTime::createFromFormat( '!Y-m-d', $value, wp_timezone() ) : false;
		return $date ? gmdate( 'Y-m-d H:i:s', $date->getTimestamp() + DAY_IN_SECONDS - 1 ) : null;
	}

	/**
	 * One CSV line. Cells that a spreadsheet would run as a formula get a
	 * leading apostrophe.
	 *
	 * @param array $cells Cells.
	 * @return string
	 */
	private static function csv_line( array $cells ) {
		$out = array();
		foreach ( $cells as $cell ) {
			$cell = (string) $cell;
			if ( '' !== $cell && in_array( $cell[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) && ! is_numeric( $cell ) ) {
				$cell = "'" . $cell;
			}
			$out[] = '"' . str_replace( '"', '""', $cell ) . '"';
		}
		return implode( ',', $out ) . "\r\n";
	}

	/**
	 * Ledger type in words.
	 *
	 * @param string $type Ledger type.
	 * @return string
	 */
	public static function type_label( $type ) {
		$labels = array(
			'issue'    => __( 'Created', 'lafu-gift-cards' ),
			'import'   => __( 'Imported', 'lafu-gift-cards' ),
			'redeem'   => __( 'Used on an order', 'lafu-gift-cards' ),
			'return'   => __( 'Returned from an order', 'lafu-gift-cards' ),
			'adjust'   => __( 'Changed by the shop', 'lafu-gift-cards' ),
			'void'     => __( 'Cancelled', 'lafu-gift-cards' ),
			'expire'   => __( 'Expired', 'lafu-gift-cards' ),
			'activate' => __( 'Activated', 'lafu-gift-cards' ),
		);
		/**
		 * Names of ledger event types in the card history.
		 *
		 * @param array $labels Type => label.
		 */
		$labels = (array) apply_filters( 'lafu_gift_cards_ledger_labels', $labels );
		return isset( $labels[ $type ] ) ? (string) $labels[ $type ] : $type;
	}

	/**
	 * Delivery state in words.
	 *
	 * @param object $card Card row.
	 * @return string
	 */
	private static function delivery_label( $card ) {
		if ( $card->physical ) {
			return __( 'Printed card', 'lafu-gift-cards' );
		}
		if ( $card->delivered_at ) {
			/* translators: %s: date and time */
			return sprintf( __( 'Emailed %s', 'lafu-gift-cards' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $card->delivered_at . ' UTC' ) ) );
		}
		if ( $card->deliver_at ) {
			/* translators: %s: date and time */
			return sprintf( __( 'Will be emailed %s', 'lafu-gift-cards' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $card->deliver_at . ' UTC' ) ) );
		}
		return __( 'Not sent', 'lafu-gift-cards' );
	}

	/**
	 * Redirect back with a one-time notice.
	 *
	 * @param string $url     Target.
	 * @param string $type    success, warning or error.
	 * @param string $message Text.
	 */
	private static function back( $url, $type, $message ) {
		set_transient(
			'lafu_gc_flash_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Show and clear the one-time notice.
	 */
	private static function flash() {
		$key   = 'lafu_gc_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( ! is_array( $flash ) ) {
			return;
		}
		delete_transient( $key );
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $flash['type'] ), esc_html( $flash['message'] ) );
	}
}
