<?php
 
defined( 'ABSPATH' ) || exit;
 
final class ONFT_Telegram {
	private ONFT_Settings $settings;
 
	public function __construct( ONFT_Settings $settings ) {
		$this->settings = $settings;
	}
 
	public function send_order_message( string $message, int $order_id = 0 ): void {
		if ( ! $this->settings->is_enabled() ) {
			return;
		}
 
		$token   = $this->settings->get_bot_token();
		$chat_ids = $this->settings->get_chat_ids();
 
		if ( '' === $token || empty( $chat_ids ) ) {
			return;
		}
 
		foreach ( $chat_ids as $chat_id ) {
			$this->send_message_to_chat( $token, $chat_id, $message, $order_id );
		}
	}
 
	public function send_test_message( string $message ): array {
		$token    = $this->settings->get_bot_token();
		$chat_ids = $this->settings->get_chat_ids();
 
		if ( '' === $token ) {
			return array(
				'ok'      => false,
				'message' => __( 'Telegram Bot Token is required.', 'telegram-notifications-for-woocommerce' ),
			);
		}
 
		if ( empty( $chat_ids ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'At least one Telegram Chat ID is required.', 'telegram-notifications-for-woocommerce' ),
			);
		}
 
		$results = array();
		foreach ( $chat_ids as $chat_id ) {
			$results[] = $this->send_message_to_chat( $token, $chat_id, $message, 0 );
		}
 
		foreach ( $results as $result ) {
			if ( ! $result['ok'] ) {
				return $result;
			}
		}
 
		return array(
			'ok'      => true,
			'message' => __( 'Test notification sent.', 'telegram-notifications-for-woocommerce' ),
		);
	}
 
	private function send_message_to_chat( string $token, string $chat_id, string $message, int $order_id ): array {
		$url  = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage';
		$body = array(
			'chat_id' => $chat_id,
			'text'    => $message,
		);
 
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'body'    => $body,
			)
		);
 
		$result = array(
			'ok'      => true,
			'message' => '',
		);
 
		if ( is_wp_error( $response ) ) {
			$result['ok']      = false;
			$result['message'] = $response->get_error_message();
			$this->log_line(
				wp_json_encode(
					array(
						'type'     => 'telegram_error',
						'order_id'  => $order_id,
						'chat_id'   => $chat_id,
						'error'     => $result['message'],
					)
				)
			);
			return $result;
		}
 
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
 
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = array( 'raw' => $raw );
		}
 
		$this->log_line(
			wp_json_encode(
				array(
					'type'     => 'telegram_response',
					'order_id'  => $order_id,
					'chat_id'   => $chat_id,
					'code'      => $code,
					'response'  => $decoded,
				)
			)
		);
 
		if ( $code < 200 || $code >= 300 ) {
			$result['ok']      = false;
			$result['message'] = __( 'Telegram request failed.', 'telegram-notifications-for-woocommerce' );
			return $result;
		}
 
		if ( isset( $decoded['ok'] ) && false === $decoded['ok'] ) {
			$result['ok']      = false;
			$result['message'] = isset( $decoded['description'] ) ? (string) $decoded['description'] : __( 'Telegram request failed.', 'telegram-notifications-for-woocommerce' );
			return $result;
		}
 
		return $result;
	}
 
	private function log_line( string $line ): void {
		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['basedir'] ) ) {
			return;
		}
 
		$dir  = trailingslashit( $upload_dir['basedir'] ) . 'woo-telegram-notify';
		$file = trailingslashit( $dir ) . 'logs.txt';
 
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}
 
		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$entry     = '[' . $timestamp . '] ' . $line . "\n";
 
		@file_put_contents( $file, $entry, FILE_APPEND | LOCK_EX );
	}
}
