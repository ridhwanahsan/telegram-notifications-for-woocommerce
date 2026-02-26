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
		add_action( 'onft_send_delayed_order_message', array( $this, 'send_order_now' ), 10, 1 );
		add_action( 'onft_retry_send', array( $this->telegram, 'handle_retry' ), 10, 5 );
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
 
		if ( ! $this->passes_filters( $order ) ) {
			return;
		}
 
		$settings = $this->settings->get_settings();
		$delay    = isset( $settings['delay_minutes'] ) ? (int) $settings['delay_minutes'] : 0;
		if ( $delay > 0 ) {
			if ( ! wp_next_scheduled( 'onft_send_delayed_order_message', array( $order_id ) ) ) {
				wp_schedule_single_event( time() + ( $delay * 60 ), 'onft_send_delayed_order_message', array( $order_id ) );
			}
			return;
		}
 
		$this->send_order_now( $order_id );
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
 
		if ( ! $this->passes_filters( $order ) ) {
			return;
		}
 
		$settings = $this->settings->get_settings();
		$delay    = isset( $settings['delay_minutes'] ) ? (int) $settings['delay_minutes'] : 0;
		if ( $delay > 0 ) {
			if ( ! wp_next_scheduled( 'onft_send_delayed_order_message', array( $order_id ) ) ) {
				wp_schedule_single_event( time() + ( $delay * 60 ), 'onft_send_delayed_order_message', array( $order_id ) );
			}
			return;
		}
 
		$this->send_order_now( $order_id );
	}
 
	public function send_order_now( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$message = $this->settings->render_message_template( $order );
		$this->telegram->send_order_message( $message, $order_id );
	}
 
	private function passes_filters( WC_Order $order ): bool {
		$s = $this->settings->get_settings();
		$min = isset( $s['min_order_amount'] ) ? (float) $s['min_order_amount'] : 0.0;
		if ( $min > 0 && (float) $order->get_total() < $min ) {
			return false;
		}
		$countries = array_filter( array_map( 'trim', explode( ',', (string) ( $s['allow_countries'] ?? '' ) ) ) );
		if ( ! empty( $countries ) ) {
			$country = (string) $order->get_billing_country();
			if ( $country && ! in_array( strtoupper( $country ), array_map( 'strtoupper', $countries ), true ) ) {
				return false;
			}
		}
		$methods = array_filter( array_map( 'trim', explode( ',', (string) ( $s['payment_methods'] ?? '' ) ) ) );
		if ( ! empty( $methods ) ) {
			$m = (string) $order->get_payment_method();
			if ( $m && ! in_array( $m, $methods, true ) ) {
				return false;
			}
		}
		$product_filter = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) ( $s['product_ids'] ?? '' ) ) ) ) );
		if ( ! empty( $product_filter ) ) {
			$match = false;
			foreach ( $order->get_items() as $item ) {
				$pid = $item->get_variation_id() ?: $item->get_product_id();
				if ( in_array( (int) $pid, $product_filter, true ) ) {
					$match = true;
					break;
				}
			}
			if ( ! $match ) {
				return false;
			}
		}
		$category_filter = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', (string) ( $s['category_slugs'] ?? '' ) ) ) ) );
		if ( ! empty( $category_filter ) ) {
			$match = false;
			foreach ( $order->get_items() as $item ) {
				$pid  = $item->get_variation_id() ?: $item->get_product_id();
				$term_slugs = array();
				$terms = get_the_terms( $pid, 'product_cat' );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $t ) {
						$term_slugs[] = (string) $t->slug;
					}
				}
				if ( array_intersect( $category_filter, $term_slugs ) ) {
					$match = true;
					break;
				}
			}
			if ( ! $match ) {
				return false;
			}
		}
		return true;
	}
}
