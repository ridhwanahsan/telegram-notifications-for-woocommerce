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
	);
 
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_post_onft_test_notification', array( $this, 'handle_test_notification' ) );
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
		return trim( (string) $settings['bot_token'] );
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
		);
 
		$message = strtr( $template, $replacements );
 
		return wp_strip_all_tags( $message );
	}
 
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Telegram Notifications', 'telegram-notifications-for-woocommerce' ),
			__( 'Telegram Notifications', 'telegram-notifications-for-woocommerce' ),
			'manage_woocommerce',
			'onft-telegram-notifications',
			array( $this, 'render_settings_page' )
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
 
	public function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
 
		$output = $this->defaults;
 
		$output['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
 
		$bot_token           = isset( $input['bot_token'] ) ? (string) $input['bot_token'] : '';
		$output['bot_token'] = trim( sanitize_text_field( $bot_token ) );
 
		$chat_ids           = isset( $input['chat_ids'] ) ? (string) $input['chat_ids'] : '';
		$output['chat_ids'] = trim( sanitize_textarea_field( $chat_ids ) );
 
		$template           = isset( $input['template'] ) ? (string) $input['template'] : $this->defaults['template'];
		$output['template'] = trim( sanitize_textarea_field( $template ) );
 
		$allowed_statuses  = array_keys( $this->get_available_statuses() );
		$allowed_statuses  = array_map(
			static function ( string $status ): string {
				return sanitize_key( $status );
			},
			$allowed_statuses
		);
 
		$statuses = isset( $input['statuses'] ) ? (array) $input['statuses'] : array();
		$statuses = array_filter(
			array_map(
				static function ( $status ): string {
					return sanitize_key( (string) $status );
				},
				$statuses
			)
		);
 
		$output['statuses'] = array_values( array_intersect( $statuses, $allowed_statuses ) );
 
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
 
	public function maybe_show_test_notice(): void {
		if ( ! is_admin() ) {
			return;
		}
 
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'woocommerce_page_onft-telegram-notifications' !== (string) $screen->id ) {
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
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[bot_token]" value="<?php echo esc_attr( (string) $settings['bot_token'] ); ?>" autocomplete="off" />
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
			echo esc_html( '{site_name} {order_id} {customer_name} {phone} {order_total} {payment_method} {order_status} {order_date} {order_link}' );
			?>
		</p>
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
}
