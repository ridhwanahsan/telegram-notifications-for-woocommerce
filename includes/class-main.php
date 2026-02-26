<?php
 
defined( 'ABSPATH' ) || exit;
 
final class ONFT_Main {
	private static ?ONFT_Main $instance = null;
 
	private ONFT_Settings $settings;
	private ONFT_Telegram $telegram;
 
	public static function instance(): ONFT_Main {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
 
		return self::$instance;
	}
 
	private function __construct() {
		add_action( 'admin_notices', array( $this, 'maybe_show_woocommerce_notice' ) );
 
		if ( ! $this->is_woocommerce_active() ) {
			return;
		}
 
		$this->settings = new ONFT_Settings();
		$this->telegram  = new ONFT_Telegram( $this->settings );
 
		add_action( 'woocommerce_new_order', array( $this, 'handle_new_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_status_change' ), 10, 4 );
	}
 
	public function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_order' );
	}
 
	public function maybe_show_woocommerce_notice(): void {
		if ( ! is_admin() ) {
			return;
		}
 
		if ( $this->is_woocommerce_active() ) {
			return;
		}
 
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
 
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Order Notifications for WooCommerce Telegram requires WooCommerce to be installed and active.', 'telegram-notifications-for-woocommerce' ) . '</p></div>';
	}
 
	public function handle_new_order( $order_id ): void {
		$order_id = absint( $order_id );
		if ( 0 === $order_id ) {
			return;
		}
 
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
 
		$status = $order->get_status();
		if ( ! $this->settings->should_notify_status( $status ) ) {
			return;
		}
 
		$message = $this->settings->render_message_template( $order );
		$this->telegram->send_order_message( $message, $order_id );
	}
 
	public function handle_status_change( $order_id, $old_status, $new_status, $order ): void {
		$order_id = absint( $order_id );
		if ( 0 === $order_id ) {
			return;
		}
 
		if ( ! $order || ! is_object( $order ) ) {
			$order = wc_get_order( $order_id );
		}
 
		if ( ! $order ) {
			return;
		}
 
		$new_status = sanitize_key( (string) $new_status );
		if ( ! $this->settings->should_notify_status( $new_status ) ) {
			return;
		}
 
		$message = $this->settings->render_message_template( $order );
		$this->telegram->send_order_message( $message, $order_id );
	}
}
