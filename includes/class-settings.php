<?php
 
defined( 'ABSPATH' ) || exit;
 
final class ONFT_Settings {
	private const OPTION_NAME = 'onft_settings';
 
	private array $defaults = array(
		'enabled'   => 0,
		'bot_token' => '',
		'chat_ids'  => '',
		'statuses'  => array( 'processing', 'completed', 'cancelled', 'pending' ),
		'template'  => "New WooCommerce Order\n\nSite: {site_name}\nOrder ID: #{order_id}\nDate: {order_date}\nCustomer: {customer_name}\nPhone: {phone}\nTotal: {order_total}\nPayment Method: {payment_method}\nStatus: {order_status}\nView Order: {order_link}",
		// Pro/Future defaults.
		'per_status_templates'   => array(),
		'notify_admin'           => 1,
		'notify_customer'        => 0,
		'delay_minutes'          => 0,
		'min_order_amount'       => 0,
		'allow_countries'        => '',
		'payment_methods'        => '',
		'product_ids'            => '',
		'category_slugs'         => '',
		'rich_messages_enabled'  => 0,
		'parse_mode'             => 'Markdown',
		'include_products_list'  => 0,
		'include_extra_fields'   => 1,
		'additional_bots'        => array(),
		'role_mappings'          => '',
		'log_rotation_size_kb'   => 512,
		'log_rotation_keep'      => 3,
		'enable_analytics'       => 0,
		'enable_ai'              => 0,
	);
 
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_init', array( $this, 'register_pro_settings' ) );
			add_action( 'admin_post_onft_test_notification', array( $this, 'handle_test_notification' ) );
			add_action( 'admin_post_onft_test_rich_message', array( $this, 'handle_test_rich_message' ) );
			add_action( 'admin_post_onft_test_multi_bot', array( $this, 'handle_test_multi_bot' ) );
			add_action( 'admin_post_onft_test_ai_message', array( $this, 'handle_test_ai_message' ) );
			add_action( 'admin_notices', array( $this, 'maybe_show_test_notice' ) );
		}
	}
 
	public function get_settings(): array {
		$raw = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
 
		$settings = wp_parse_args( $raw, $this->defaults );
 
		$settings['enabled']   = ! empty( $settings['enabled'] ) ? 1 : 0;
		$settings['bot_token'] = is_string( $settings['bot_token'] ) ? $settings['bot_token'] : '';
		$settings['chat_ids']  = is_string( $settings['chat_ids'] ) ? $settings['chat_ids'] : '';
		$settings['template']  = is_string( $settings['template'] ) ? $settings['template'] : $this->defaults['template'];
 
		$settings['statuses'] = is_array( $settings['statuses'] ) ? array_values( $settings['statuses'] ) : array();
		$settings['statuses'] = array_filter(
			array_map(
				static function ( $status ): string {
					return sanitize_key( (string) $status );
				},
				$settings['statuses']
			)
		);
 
		return $settings;
	}
 
	public function is_enabled(): bool {
		$settings = $this->get_settings();
		return 1 === (int) $settings['enabled'];
	}
 
	public function get_bot_token(): string {
		$settings = $this->get_settings();
		$stored   = (string) $settings['bot_token'];
		$token    = $this->maybe_decrypt( $stored );
		return trim( $token );
	}
 
	public function get_chat_ids(): array {
		$settings = $this->get_settings();
		$raw      = (string) $settings['chat_ids'];
		$parts    = array_map( 'trim', explode( ',', $raw ) );
		$parts    = array_filter(
			$parts,
			static function ( $value ): bool {
				return (bool) preg_match( '/^-?\d+$/', $value );
			}
		);
 
		return array_values( array_unique( $parts ) );
	}
 
	public function get_enabled_statuses(): array {
		$settings = $this->get_settings();
		return $settings['statuses'];
	}
 
	public function get_parse_mode(): string {
		$settings = $this->get_settings();
		$mode     = isset( $settings['parse_mode'] ) ? (string) $settings['parse_mode'] : 'Markdown';
		return in_array( $mode, array( 'Markdown', 'MarkdownV2', 'HTML' ), true ) ? $mode : 'Markdown';
	}
 
	public function is_rich_messages_enabled(): bool {
		$settings = $this->get_settings();
		return ! empty( $settings['rich_messages_enabled'] );
	}
 
	public function get_additional_bots(): array {
		$settings = $this->get_settings();
		$list     = isset( $settings['additional_bots'] ) && is_array( $settings['additional_bots'] ) ? $settings['additional_bots'] : array();
		$out      = array();
		foreach ( $list as $bot ) {
			$label    = isset( $bot['label'] ) ? (string) $bot['label'] : '';
			$token    = isset( $bot['token'] ) ? (string) $bot['token'] : '';
			$token    = $this->maybe_decrypt( $token );
			$chat_ids = isset( $bot['chat_ids'] ) ? (string) $bot['chat_ids'] : '';
			if ( '' !== $token ) {
				$out[] = array(
					'label'    => $label,
					'token'    => $token,
					'chat_ids' => $chat_ids,
				);
			}
		}
		return $out;
	}
 
	public function should_notify_status( string $status ): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}
 
		$status   = sanitize_key( $status );
		$enabled  = $this->get_enabled_statuses();
		$enabled  = array_map( 'sanitize_key', $enabled );
 
		if ( empty( $enabled ) ) {
			return false;
		}
 
		return in_array( $status, $enabled, true );
	}
 
	public function render_message_template( WC_Order $order ): string {
		$settings = $this->get_settings();
		$template = (string) $settings['template'];
		$status   = (string) $order->get_status();
		if ( isset( $settings['per_status_templates'][ $status ] ) && '' !== trim( (string) $settings['per_status_templates'][ $status ] ) ) {
			$template = (string) $settings['per_status_templates'][ $status ];
		}
		if ( '' === trim( $template ) ) {
			$template = $this->defaults['template'];
		}
 
		$order_id = (int) $order->get_id();
		$status   = (string) $order->get_status();
 
		$order_date = '';
		$date       = $order->get_date_created();
		if ( $date instanceof WC_DateTime ) {
			$order_date = $date->date_i18n( 'Y-m-d H:i' );
		}
 
		$order_total = '';
		if ( method_exists( $order, 'get_formatted_order_total' ) ) {
			$order_total = html_entity_decode( wp_strip_all_tags( (string) $order->get_formatted_order_total() ) );
		}
 
		$products_list = '';
		if ( ! empty( $settings['include_products_list'] ) ) {
			$lines = array();
			foreach ( $order->get_items() as $item ) {
				$name = $item->get_name();
				$qty  = $item->get_quantity();
				$lines[] = '• ' . $name . ' × ' . $qty;
			}
			$products_list = implode( "\n", $lines );
		}
 
		$shipping_method = '';
		if ( method_exists( $order, 'get_shipping_method' ) ) {
			$shipping_method = (string) $order->get_shipping_method();
		}
 
		$billing_address = '';
		if ( ! empty( $settings['include_extra_fields'] ) ) {
			$billing_address = trim( preg_replace( '/\s+/', ' ', $order->get_formatted_billing_address() ?? '' ) );
		}
 
		$coupon_used = '';
		if ( ! empty( $settings['include_extra_fields'] ) ) {
			$coupons = method_exists( $order, 'get_coupon_codes' ) ? (array) $order->get_coupon_codes() : array();
			$coupon_used = implode( ',', $coupons );
		}
 
		$order_notes = '';
		if ( ! empty( $settings['include_extra_fields'] ) ) {
			$note = method_exists( $order, 'get_customer_note' ) ? (string) $order->get_customer_note() : '';
			$order_notes = $note;
		}
 
		$replacements = array(
			'{site_name}'      => get_bloginfo( 'name' ),
			'{order_id}'       => (string) $order_id,
			'{customer_name}'  => trim( $order->get_formatted_billing_full_name() ),
			'{phone}'          => (string) $order->get_billing_phone(),
			'{order_total}'    => $order_total,
			'{payment_method}' => (string) $order->get_payment_method_title(),
			'{order_status}'   => function_exists( 'wc_get_order_status_name' ) ? (string) wc_get_order_status_name( 'wc-' . $status ) : $status,
			'{order_date}'     => $order_date,
			'{order_link}'     => esc_url_raw( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ),
			'{products_list}'  => $products_list,
			'{quantity}'       => (string) $order->get_item_count(),
			'{shipping_method}' => $shipping_method,
			'{billing_address}' => $billing_address,
			'{coupon_used}'     => $coupon_used,
			'{order_notes}'     => $order_notes,
		);
 
		$message = strtr( $template, $replacements );
 
		return wp_strip_all_tags( $message );
	}
 
	public function register_menu(): void {
		add_menu_page(
			__( 'Telegram Notifications', 'telegram-notifications-for-woocommerce' ),
			__( 'Telegram Notify', 'telegram-notifications-for-woocommerce' ),
			'manage_woocommerce',
			'onft-telegram',
			array( $this, 'render_settings_page' ),
			'dashicons-megaphone'
		);
		add_submenu_page(
			'onft-telegram',
			__( 'General Settings', 'telegram-notifications-for-woocommerce' ),
			__( 'General Settings', 'telegram-notifications-for-woocommerce' ),
			'manage_woocommerce',
			'onft-telegram-notifications',
			array( $this, 'render_settings_page' )
		);
		add_submenu_page(
			'onft-telegram',
			__( 'Pro / Future Features', 'telegram-notifications-for-woocommerce' ),
			__( 'Pro / Future Features', 'telegram-notifications-for-woocommerce' ),
			'manage_woocommerce',
			'onft-telegram-pro',
			array( $this, 'render_pro_settings_page' )
		);
	}
 
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
 
		$settings = $this->get_settings();
		$statuses = $this->get_available_statuses();
 
		require ONFT_PLUGIN_DIR . 'admin/settings-page.php';
	}
 
	public function render_pro_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
 
		$settings = $this->get_settings();
		require ONFT_PLUGIN_DIR . 'admin/future-settings-page.php';
	}
 
	public function register_settings(): void {
		register_setting(
			'onft_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->defaults,
			)
		);
 
		add_settings_section(
			'onft_main_section',
			'',
			static function (): void {},
			'onft_settings_page'
		);
 
		add_settings_field(
			'onft_enabled',
			__( 'Enable Notifications', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_enabled' ),
			'onft_settings_page',
			'onft_main_section'
		);
 
		add_settings_field(
			'onft_bot_token',
			__( 'Telegram Bot Token', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_bot_token' ),
			'onft_settings_page',
			'onft_main_section'
		);
 
		add_settings_field(
			'onft_chat_ids',
			__( 'Telegram Chat ID(s)', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_chat_ids' ),
			'onft_settings_page',
			'onft_main_section'
		);
 
		add_settings_field(
			'onft_statuses',
			__( 'Select Order Status to Notify', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_statuses' ),
			'onft_settings_page',
			'onft_main_section'
		);
 
		add_settings_field(
			'onft_template',
			__( 'Custom Message Template', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_template' ),
			'onft_settings_page',
			'onft_main_section'
		);
	}
 
	public function register_pro_settings(): void {
		register_setting(
			'onft_pro_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->defaults,
			)
		);
 
		add_settings_section(
			'onft_pro_advanced',
			__( 'Advanced Notifications', 'telegram-notifications-for-woocommerce' ),
			static function (): void {},
			'onft_pro_settings_page'
		);
		add_settings_field(
			'onft_per_status_templates',
			__( 'Per-status custom templates', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_per_status_templates' ),
			'onft_pro_settings_page',
			'onft_pro_advanced'
		);
		add_settings_field(
			'onft_admin_customer_toggle',
			__( 'Admin vs Customer notifications', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_admin_customer' ),
			'onft_pro_settings_page',
			'onft_pro_advanced'
		);
		add_settings_field(
			'onft_delay_minutes',
			__( 'Delay notification (minutes)', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_delay_minutes' ),
			'onft_pro_settings_page',
			'onft_pro_advanced'
		);
 
		add_settings_section(
			'onft_pro_filters',
			__( 'Filters & Conditions', 'telegram-notifications-for-woocommerce' ),
			static function (): void {},
			'onft_pro_settings_page'
		);
		add_settings_field(
			'onft_min_amount',
			__( 'Minimum order amount', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_min_amount' ),
			'onft_pro_settings_page',
			'onft_pro_filters'
		);
		add_settings_field(
			'onft_country_methods',
			__( 'Country / Payment method filter', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_country_payment' ),
			'onft_pro_settings_page',
			'onft_pro_filters'
		);
		add_settings_field(
			'onft_product_category_filters',
			__( 'Product / Category specific', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_product_category' ),
			'onft_pro_settings_page',
			'onft_pro_filters'
		);
 
		add_settings_section(
			'onft_pro_rich',
			__( 'Rich Message / Buttons', 'telegram-notifications-for-woocommerce' ),
			static function (): void {},
			'onft_pro_settings_page'
		);
		add_settings_field(
			'onft_rich_messages',
			__( 'Enable rich messages (Markdown/HTML)', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_rich_messages' ),
			'onft_pro_settings_page',
			'onft_pro_rich'
		);
		add_settings_field(
			'onft_ordered_products_list',
			__( 'Include ordered products list', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_ordered_products' ),
			'onft_pro_settings_page',
			'onft_pro_rich'
		);
		add_settings_field(
			'onft_extra_placeholders',
			__( 'Include extra placeholders', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_extra_placeholders' ),
			'onft_pro_settings_page',
			'onft_pro_rich'
		);
 
		add_settings_section(
			'onft_pro_team',
			__( 'Team / Multi-Bot', 'telegram-notifications-for-woocommerce' ),
			static function (): void {},
			'onft_pro_settings_page'
		);
		add_settings_field(
			'onft_additional_bots',
			__( 'Multiple bots configuration', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_additional_bots' ),
			'onft_pro_settings_page',
			'onft_pro_team'
		);
		add_settings_field(
			'onft_role_mappings',
			__( 'Role based notifications', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_role_mappings' ),
			'onft_pro_settings_page',
			'onft_pro_team'
		);
 
		add_settings_section(
			'onft_pro_logs',
			__( 'Logs & Analytics', 'telegram-notifications-for-woocommerce' ),
			static function (): void {},
			'onft_pro_settings_page'
		);
		add_settings_field(
			'onft_log_rotation',
			__( 'Log rotation (size KB / keep files)', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_log_rotation' ),
			'onft_pro_settings_page',
			'onft_pro_logs'
		);
		add_settings_field(
			'onft_enable_analytics',
			__( 'Enable basic analytics', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_enable_analytics' ),
			'onft_pro_settings_page',
			'onft_pro_logs'
		);
 
		add_settings_section(
			'onft_pro_ai',
			__( 'AI Message Generator', 'telegram-notifications-for-woocommerce' ),
			static function (): void {},
			'onft_pro_settings_page'
		);
		add_settings_field(
			'onft_enable_ai',
			__( 'Enable AI message generation (placeholder)', 'telegram-notifications-for-woocommerce' ),
			array( $this, 'field_enable_ai' ),
			'onft_pro_settings_page',
			'onft_pro_ai'
		);
	}
 
	public function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
 
		$output = $this->get_settings();
 
		if ( array_key_exists( 'enabled', $input ) ) {
			$output['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
		}
 
		if ( array_key_exists( 'bot_token', $input ) ) {
			$bot_token_raw = (string) $input['bot_token'];
			$bot_token     = trim( sanitize_text_field( $bot_token_raw ) );
			$output['bot_token'] = '' === $bot_token ? '' : $this->encrypt( $bot_token );
		}
 
		if ( array_key_exists( 'chat_ids', $input ) ) {
			$chat_ids           = (string) $input['chat_ids'];
			$output['chat_ids'] = trim( sanitize_textarea_field( $chat_ids ) );
		}
 
		if ( array_key_exists( 'template', $input ) ) {
			$template           = (string) $input['template'];
			$output['template'] = trim( sanitize_textarea_field( $template ) );
		}
 
		$allowed_statuses  = array_keys( $this->get_available_statuses() );
		$allowed_statuses  = array_map(
			static function ( string $status ): string {
				return sanitize_key( $status );
			},
			$allowed_statuses
		);
 
		if ( array_key_exists( 'statuses', $input ) ) {
			$statuses = (array) $input['statuses'];
			$statuses = array_filter(
				array_map(
					static function ( $status ): string {
						return sanitize_key( (string) $status );
					},
					$statuses
				)
			);
			$output['statuses'] = array_values( array_intersect( $statuses, $allowed_statuses ) );
		}
 
		// Pro/Future inputs.
		if ( array_key_exists( 'notify_admin', $input ) ) {
			$output['notify_admin'] = ! empty( $input['notify_admin'] ) ? 1 : 0;
		}
		if ( array_key_exists( 'notify_customer', $input ) ) {
			$output['notify_customer'] = ! empty( $input['notify_customer'] ) ? 1 : 0;
		}
		if ( array_key_exists( 'delay_minutes', $input ) ) {
			$output['delay_minutes'] = max( 0, min( 5, (int) $input['delay_minutes'] ) );
		}
		if ( array_key_exists( 'min_order_amount', $input ) ) {
			$output['min_order_amount'] = max( 0.0, (float) $input['min_order_amount'] );
		}
		if ( array_key_exists( 'allow_countries', $input ) ) {
			$output['allow_countries'] = trim( sanitize_text_field( (string) $input['allow_countries'] ) );
		}
		if ( array_key_exists( 'payment_methods', $input ) ) {
			$output['payment_methods'] = trim( sanitize_text_field( (string) $input['payment_methods'] ) );
		}
		if ( array_key_exists( 'product_ids', $input ) ) {
			$output['product_ids'] = trim( sanitize_text_field( (string) $input['product_ids'] ) );
		}
		if ( array_key_exists( 'category_slugs', $input ) ) {
			$output['category_slugs'] = trim( sanitize_text_field( (string) $input['category_slugs'] ) );
		}
		if ( array_key_exists( 'rich_messages_enabled', $input ) ) {
			$output['rich_messages_enabled'] = ! empty( $input['rich_messages_enabled'] ) ? 1 : 0;
		}
		if ( array_key_exists( 'parse_mode', $input ) ) {
			$parse_mode = (string) $input['parse_mode'];
			$output['parse_mode'] = in_array( $parse_mode, array( 'Markdown', 'MarkdownV2', 'HTML' ), true ) ? $parse_mode : 'Markdown';
		}
		if ( array_key_exists( 'include_products_list', $input ) ) {
			$output['include_products_list'] = ! empty( $input['include_products_list'] ) ? 1 : 0;
		}
		if ( array_key_exists( 'include_extra_fields', $input ) ) {
			$output['include_extra_fields'] = ! empty( $input['include_extra_fields'] ) ? 1 : 0;
		}
		if ( array_key_exists( 'enable_analytics', $input ) ) {
			$output['enable_analytics'] = ! empty( $input['enable_analytics'] ) ? 1 : 0;
		}
		if ( array_key_exists( 'enable_ai', $input ) ) {
			$output['enable_ai'] = ! empty( $input['enable_ai'] ) ? 1 : 0;
		}
		if ( array_key_exists( 'log_rotation_size_kb', $input ) ) {
			$output['log_rotation_size_kb'] = max( 128, (int) $input['log_rotation_size_kb'] );
		}
		if ( array_key_exists( 'log_rotation_keep', $input ) ) {
			$output['log_rotation_keep'] = max( 1, (int) $input['log_rotation_keep'] );
		}
 
		// Per-status templates.
		if ( array_key_exists( 'per_status_templates', $input ) ) {
			$per = is_array( $input['per_status_templates'] ) ? $input['per_status_templates'] : array();
			$clean_per = array();
			foreach ( $per as $k => $v ) {
				$slug = sanitize_key( (string) $k );
				$tpl  = trim( sanitize_textarea_field( (string) $v ) );
				if ( '' !== $tpl ) {
					$clean_per[ $slug ] = $tpl;
				}
			}
			$output['per_status_templates'] = $clean_per;
		}
 
		// Additional bots from raw lines.
		if ( array_key_exists( 'additional_bots_raw', $input ) ) {
			$bots_raw = (string) $input['additional_bots_raw'];
			$lines    = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $bots_raw ) ) );
			$bots     = array();
			foreach ( $lines as $line ) {
				$parts = array_map( 'trim', explode( '|', $line ) );
				if ( count( $parts ) >= 3 ) {
					$label    = sanitize_text_field( $parts[0] );
					$token    = $this->encrypt( sanitize_text_field( $parts[1] ) );
					$chat_ids = sanitize_text_field( $parts[2] );
					$bots[]   = array(
						'label'    => $label,
						'token'    => $token,
						'chat_ids' => $chat_ids,
					);
				}
			}
			$output['additional_bots'] = $bots;
		}
 
		if ( array_key_exists( 'role_mappings', $input ) ) {
			$output['role_mappings'] = trim( sanitize_textarea_field( (string) $input['role_mappings'] ) );
		}
 
		return $output;
	}
 
	public function handle_test_notification(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'telegram-notifications-for-woocommerce' ) );
		}
 
		check_admin_referer( 'onft_test_notification' );
 
		$message  = sprintf(
			/* translators: 1: site name, 2: date */
			__( 'Test notification from %1$s at %2$s', 'telegram-notifications-for-woocommerce' ),
			get_bloginfo( 'name' ),
			wp_date( 'Y-m-d H:i:s' )
		);
 
		$telegram = new ONFT_Telegram( $this );
		$result   = $telegram->send_test_message( $message );
 
		$redirect = add_query_arg(
			array(
				'page'        => 'onft-telegram-notifications',
				'onft_test'    => 1,
				'onft_success' => $result['ok'] ? 1 : 0,
				'_wpnonce'     => wp_create_nonce( 'onft_test_notice' ),
			),
			admin_url( 'admin.php' )
		);
 
		if ( ! $result['ok'] ) {
			$redirect = add_query_arg( 'onft_error', rawurlencode( (string) $result['message'] ), $redirect );
		}
 
		wp_safe_redirect( $redirect );
		exit;
	}
 
	public function handle_test_rich_message(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'telegram-notifications-for-woocommerce' ) );
		}
 
		check_admin_referer( 'onft_test_rich_message' );
 
		$message  = sprintf( "*%s* — _Rich message test_\n`%s`", get_bloginfo( 'name' ), wp_date( 'Y-m-d H:i:s' ) );
		$telegram = new ONFT_Telegram( $this );
		$result   = $telegram->send_test_message_rich( $message );
 
		$redirect = add_query_arg(
			array(
				'page'         => 'onft-telegram-pro',
				'onft_test'     => 1,
				'onft_success'  => $result['ok'] ? 1 : 0,
				'onft_context'  => 'rich',
				'_wpnonce'      => wp_create_nonce( 'onft_test_notice' ),
			),
			admin_url( 'admin.php' )
		);
 
		if ( ! $result['ok'] ) {
			$redirect = add_query_arg( 'onft_error', rawurlencode( (string) $result['message'] ), $redirect );
		}
 
		wp_safe_redirect( $redirect );
		exit;
	}
 
	public function handle_test_multi_bot(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'telegram-notifications-for-woocommerce' ) );
		}
 
		check_admin_referer( 'onft_test_multi_bot' );
 
		$message  = sprintf( 'Multi-bot test from %s at %s', get_bloginfo( 'name' ), wp_date( 'Y-m-d H:i:s' ) );
		$telegram = new ONFT_Telegram( $this );
		$result   = $telegram->send_test_message_all_bots( $message );
 
		$redirect = add_query_arg(
			array(
				'page'         => 'onft-telegram-pro',
				'onft_test'     => 1,
				'onft_success'  => $result['ok'] ? 1 : 0,
				'onft_context'  => 'multi_bot',
				'_wpnonce'      => wp_create_nonce( 'onft_test_notice' ),
			),
			admin_url( 'admin.php' )
		);
 
		if ( ! $result['ok'] ) {
			$redirect = add_query_arg( 'onft_error', rawurlencode( (string) $result['message'] ), $redirect );
		}
 
		wp_safe_redirect( $redirect );
		exit;
	}
 
	public function handle_test_ai_message(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'telegram-notifications-for-woocommerce' ) );
		}
 
		check_admin_referer( 'onft_test_ai_message' );
 
		$message  = sprintf( 'AI message test for %s: Order #{order_id} total {order_total}', get_bloginfo( 'name' ) );
		$telegram = new ONFT_Telegram( $this );
		$result   = $telegram->send_test_message( $message );
 
		$redirect = add_query_arg(
			array(
				'page'         => 'onft-telegram-pro',
				'onft_test'     => 1,
				'onft_success'  => $result['ok'] ? 1 : 0,
				'onft_context'  => 'ai',
				'_wpnonce'      => wp_create_nonce( 'onft_test_notice' ),
			),
			admin_url( 'admin.php' )
		);
 
		if ( ! $result['ok'] ) {
			$redirect = add_query_arg( 'onft_error', rawurlencode( (string) $result['message'] ), $redirect );
		}
 
		wp_safe_redirect( $redirect );
		exit;
	}
 
	public function maybe_show_test_notice(): void {
		if ( ! is_admin() ) {
			return;
		}
 
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
 
		$id = (string) $screen->id;
		if ( false === strpos( $id, 'onft-telegram' ) ) {
			return;
		}
 
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'onft_test_notice' ) ) {
			return;
		}
 
		$test_flag = isset( $_GET['onft_test'] ) ? absint( wp_unslash( (string) $_GET['onft_test'] ) ) : 0;
		if ( 1 !== $test_flag ) {
			return;
		}
 
		$success = isset( $_GET['onft_success'] ) ? absint( wp_unslash( (string) $_GET['onft_success'] ) ) : 0;
		if ( $success ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Test notification sent successfully.', 'telegram-notifications-for-woocommerce' ) . '</p></div>';
			return;
		}
 
		$error = isset( $_GET['onft_error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['onft_error'] ) ) : __( 'Test notification failed.', 'telegram-notifications-for-woocommerce' );
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
	}
 
	public function field_enabled(): void {
		$settings = $this->get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( 1, (int) $settings['enabled'] ); ?> />
			<?php esc_html_e( 'Enable Telegram notifications', 'telegram-notifications-for-woocommerce' ); ?>
		</label>
		<?php
	}
 
	public function field_bot_token(): void {
		$settings = $this->get_settings();
		$display  = $this->maybe_decrypt( (string) $settings['bot_token'] );
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[bot_token]" value="<?php echo esc_attr( $display ); ?>" autocomplete="off" />
		<?php
	}
 
	public function field_chat_ids(): void {
		$settings = $this->get_settings();
		?>
		<textarea class="large-text" rows="2" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[chat_ids]"><?php echo esc_textarea( (string) $settings['chat_ids'] ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Comma separated Chat IDs (supports negative IDs for groups).', 'telegram-notifications-for-woocommerce' ); ?></p>
		<?php
	}
 
	public function field_statuses(): void {
		$settings = $this->get_settings();
		$enabled  = array_map( 'sanitize_key', (array) $settings['statuses'] );
		$statuses = $this->get_available_statuses();
		?>
		<fieldset>
			<?php foreach ( $statuses as $status_key => $status_label ) : ?>
				<label style="display:block; margin: 0 0 6px;">
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[statuses][]" value="<?php echo esc_attr( $status_key ); ?>" <?php checked( in_array( $status_key, $enabled, true ) ); ?> />
					<?php echo esc_html( $status_label ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}
 
	public function field_template(): void {
		$settings = $this->get_settings();
		?>
		<textarea class="large-text code" rows="10" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[template]"><?php echo esc_textarea( (string) $settings['template'] ); ?></textarea>
		<p class="description">
			<?php
			echo esc_html__( 'Available placeholders:', 'telegram-notifications-for-woocommerce' ) . ' ';
			echo esc_html( '{site_name} {order_id} {customer_name} {phone} {order_total} {payment_method} {order_status} {order_date} {order_link} {products_list} {quantity} {shipping_method} {billing_address} {coupon_used} {order_notes}' );
			?>
		</p>
		<?php
	}
 
	public function field_per_status_templates(): void {
		$settings = $this->get_settings();
		$map      = isset( $settings['per_status_templates'] ) && is_array( $settings['per_status_templates'] ) ? $settings['per_status_templates'] : array();
		$statuses = $this->get_available_statuses();
		?>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">
			<?php foreach ( $statuses as $slug => $label ) : 
				$template = isset( $map[ $slug ] ) ? (string) $map[ $slug ] : '';
				?>
				<div style="border:1px solid #ccd0d4;padding:10px;background:#fff;">
					<div style="font-weight:600;margin-bottom:6px;"><?php echo esc_html( $label ); ?></div>
					<textarea style="width:100%;" class="code" rows="4" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[per_status_templates][<?php echo esc_attr( $slug ); ?>]"><?php echo esc_textarea( $template ); ?></textarea>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="description" style="margin-top:8px;"><?php esc_html_e( 'Leave empty to use the general template.', 'telegram-notifications-for-woocommerce' ); ?></p>
		<?php
	}
 
	public function field_admin_customer(): void {
		$settings = $this->get_settings();
		$notify_admin    = ! empty( $settings['notify_admin'] );
		$notify_customer = ! empty( $settings['notify_customer'] );
		?>
		<label style="display:block; margin:6px 0;">
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[notify_admin]" value="1" <?php checked( $notify_admin ); ?> />
			<?php esc_html_e( 'Notify admins', 'telegram-notifications-for-woocommerce' ); ?>
		</label>
		<label style="display:block; margin:6px 0;">
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[notify_customer]" value="1" <?php checked( $notify_customer ); ?> />
			<?php esc_html_e( 'Notify customers', 'telegram-notifications-for-woocommerce' ); ?>
		</label>
		<?php
	}
 
	public function field_delay_minutes(): void {
		$settings = $this->get_settings();
		$delay    = isset( $settings['delay_minutes'] ) ? (int) $settings['delay_minutes'] : 0;
		?>
		<input type="number" min="0" max="5" step="1" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[delay_minutes]" value="<?php echo esc_attr( (string) $delay ); ?>" />
		<p class="description"><?php esc_html_e( 'Delay sending by up to 5 minutes using WP-Cron.', 'telegram-notifications-for-woocommerce' ); ?></p>
		<?php
	}
 
	public function field_min_amount(): void {
		$settings = $this->get_settings();
		$val      = isset( $settings['min_order_amount'] ) ? (float) $settings['min_order_amount'] : 0;
		?>
		<input type="number" min="0" step="0.01" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[min_order_amount]" value="<?php echo esc_attr( (string) $val ); ?>" />
		<?php
	}
 
	public function field_country_payment(): void {
		$settings = $this->get_settings();
		$countries = isset( $settings['allow_countries'] ) ? (string) $settings['allow_countries'] : '';
		$methods   = isset( $settings['payment_methods'] ) ? (string) $settings['payment_methods'] : '';
		?>
		<label style="display:block; margin:6px 0;">
			<?php esc_html_e( 'Allowed Countries (CSV of ISO codes, e.g., US,GB,BD)', 'telegram-notifications-for-woocommerce' ); ?><br/>
			<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[allow_countries]" value="<?php echo esc_attr( $countries ); ?>" />
		</label>
		<label style="display:block; margin:6px 0;">
			<?php esc_html_e( 'Allowed Payment Methods (CSV of IDs, e.g., cod,bacs,stripe)', 'telegram-notifications-for-woocommerce' ); ?><br/>
			<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[payment_methods]" value="<?php echo esc_attr( $methods ); ?>" />
		</label>
		<?php
	}
 
	public function field_product_category(): void {
		$settings = $this->get_settings();
		$product_ids = isset( $settings['product_ids'] ) ? (string) $settings['product_ids'] : '';
		$category_slugs = isset( $settings['category_slugs'] ) ? (string) $settings['category_slugs'] : '';
		?>
		<label style="display:block; margin:6px 0;">
			<?php esc_html_e( 'Product IDs (CSV)', 'telegram-notifications-for-woocommerce' ); ?><br/>
			<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[product_ids]" value="<?php echo esc_attr( $product_ids ); ?>" />
		</label>
		<label style="display:block; margin:6px 0;">
			<?php esc_html_e( 'Category slugs (CSV)', 'telegram-notifications-for-woocommerce' ); ?><br/>
			<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[category_slugs]" value="<?php echo esc_attr( $category_slugs ); ?>" />
		</label>
		<?php
	}
 
	public function field_rich_messages(): void {
		$settings  = $this->get_settings();
		$enabled   = ! empty( $settings['rich_messages_enabled'] );
		$parseMode = isset( $settings['parse_mode'] ) ? (string) $settings['parse_mode'] : 'Markdown';
		?>
		<label style="display:block; margin:6px 0;">
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[rich_messages_enabled]" value="1" <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Enable rich messages', 'telegram-notifications-for-woocommerce' ); ?>
		</label>
		<label style="display:block; margin:6px 0;">
			<?php esc_html_e( 'Parse mode', 'telegram-notifications-for-woocommerce' ); ?>
			<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[parse_mode]">
				<?php foreach ( array( 'Markdown', 'MarkdownV2', 'HTML' ) as $mode ) : ?>
					<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $parseMode, $mode ); ?>><?php echo esc_html( $mode ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php
	}
 
	public function field_ordered_products(): void {
		$settings = $this->get_settings();
		$enabled  = ! empty( $settings['include_products_list'] );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[include_products_list]" value="1" <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Include list of ordered products', 'telegram-notifications-for-woocommerce' ); ?>
		</label>
		<?php
	}
 
	public function field_extra_placeholders(): void {
		$settings = $this->get_settings();
		$enabled  = ! empty( $settings['include_extra_fields'] );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[include_extra_fields]" value="1" <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Include extra placeholders (qty, shipping, address, coupon, notes)', 'telegram-notifications-for-woocommerce' ); ?>
		</label>
		<?php
	}
 
	public function field_additional_bots(): void {
		$settings = $this->get_settings();
		$bots     = isset( $settings['additional_bots'] ) && is_array( $settings['additional_bots'] ) ? $settings['additional_bots'] : array();
		$lines    = array();
		foreach ( $bots as $bot ) {
			$label    = isset( $bot['label'] ) ? (string) $bot['label'] : '';
			$token    = isset( $bot['token'] ) ? (string) $bot['token'] : '';
			$chat_ids = isset( $bot['chat_ids'] ) ? (string) $bot['chat_ids'] : '';
			$lines[]  = $label . '|' . $token . '|' . $chat_ids;
		}
		?>
		<textarea class="large-text code" rows="5" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[additional_bots_raw]"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'One per line: Label|Token|ChatID1,ChatID2', 'telegram-notifications-for-woocommerce' ); ?></p>
		<?php
	}
 
	public function field_role_mappings(): void {
		$settings = $this->get_settings();
		$value    = isset( $settings['role_mappings'] ) ? (string) $settings['role_mappings'] : '';
		?>
		<textarea class="large-text code" rows="4" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[role_mappings]"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description"><?php esc_html_e( 'One per line: role|ChatID1,ChatID2', 'telegram-notifications-for-woocommerce' ); ?></p>
		<?php
	}
 
	public function field_log_rotation(): void {
		$settings = $this->get_settings();
		$size_kb  = isset( $settings['log_rotation_size_kb'] ) ? (int) $settings['log_rotation_size_kb'] : 512;
		$keep     = isset( $settings['log_rotation_keep'] ) ? (int) $settings['log_rotation_keep'] : 3;
		?>
		<label><?php esc_html_e( 'Max file size (KB)', 'telegram-notifications-for-woocommerce' ); ?>
			<input type="number" min="128" max="8192" step="64" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[log_rotation_size_kb]" value="<?php echo esc_attr( (string) $size_kb ); ?>" />
		</label>
		<label style="margin-left:12px;"><?php esc_html_e( 'Keep files', 'telegram-notifications-for-woocommerce' ); ?>
			<input type="number" min="1" max="10" step="1" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[log_rotation_keep]" value="<?php echo esc_attr( (string) $keep ); ?>" />
		</label>
		<?php
	}
 
	public function field_enable_analytics(): void {
		$settings = $this->get_settings();
		$enabled  = ! empty( $settings['enable_analytics'] );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_analytics]" value="1" <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Enable basic analytics (counts only)', 'telegram-notifications-for-woocommerce' ); ?>
		</label>
		<?php
	}
 
	public function field_enable_ai(): void {
		$settings = $this->get_settings();
		$enabled  = ! empty( $settings['enable_ai'] );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_ai]" value="1" <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Enable AI message generation (placeholder feature)', 'telegram-notifications-for-woocommerce' ); ?>
		</label>
		<?php
	}
 
	private function get_available_statuses(): array {
		$statuses = array();
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$all = wc_get_order_statuses();
			foreach ( $all as $key => $label ) {
				$key = (string) $key;
				if ( 0 === strpos( $key, 'wc-' ) ) {
					$slug = substr( $key, 3 );
				} else {
					$slug = $key;
				}
				$statuses[ sanitize_key( $slug ) ] = (string) $label;
			}
		}
 
		if ( empty( $statuses ) ) {
			$statuses = array(
				'pending'    => __( 'Pending payment', 'telegram-notifications-for-woocommerce' ),
				'processing' => __( 'Processing', 'telegram-notifications-for-woocommerce' ),
				'completed'  => __( 'Completed', 'telegram-notifications-for-woocommerce' ),
				'cancelled'  => __( 'Cancelled', 'telegram-notifications-for-woocommerce' ),
			);
		}
 
		return $statuses;
	}
 
	// Encryption helpers.
	private function key_material(): string {
		$base = '';
		if ( defined( 'AUTH_SALT' ) && AUTH_SALT ) {
			$base = AUTH_SALT;
		} elseif ( defined( 'SECURE_AUTH_KEY' ) && SECURE_AUTH_KEY ) {
			$base = SECURE_AUTH_KEY;
		} else {
			$base = wp_salt( 'auth' );
		}
		return hash( 'sha256', $base . 'onft', true );
	}
 
	private function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		$key = $this->key_material();
		$iv  = random_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return $plain;
		}
		return 'enc:' . base64_encode( $iv . $cipher );
	}
 
	private function maybe_decrypt( string $stored ): string {
		if ( '' === $stored ) {
			return '';
		}
		if ( 0 !== strpos( $stored, 'enc:' ) ) {
			return $stored;
		}
		$data = base64_decode( substr( $stored, 4 ), true );
		if ( ! $data || strlen( $data ) <= 16 ) {
			return '';
		}
		$iv     = substr( $data, 0, 16 );
		$cipher = substr( $data, 16 );
		$key    = $this->key_material();
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}
}
