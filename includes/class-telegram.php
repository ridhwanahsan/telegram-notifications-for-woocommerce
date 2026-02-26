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
 
		$token    = $this->settings->get_bot_token();
		$chat_ids = $this->settings->get_chat_ids();
 
		if ( '' === $token || empty( $chat_ids ) ) {
			return;
		}
 
		$targets = array( array( 'token' => $token, 'chat_ids' => $chat_ids ) );
		$extra_bots = $this->settings->get_additional_bots();
		foreach ( $extra_bots as $bot ) {
			$ids = array_values( array_filter( array_map( 'trim', explode( ',', (string) $bot['chat_ids'] ) ) ) );
			if ( ! empty( $bot['token'] ) && ! empty( $ids ) ) {
				$targets[] = array( 'token' => (string) $bot['token'], 'chat_ids' => $ids );
			}
		}
 
		$args = array();
		if ( $this->settings->is_rich_messages_enabled() ) {
			$args['parse_mode'] = $this->settings->get_parse_mode();
		}
 
		foreach ( $targets as $t ) {
			foreach ( (array) $t['chat_ids'] as $chat_id ) {
				$this->send_message_to_chat( (string) $t['token'], (string) $chat_id, $message, $order_id, $args );
			}
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
		$args = array();
		if ( $this->settings->is_rich_messages_enabled() ) {
			$args['parse_mode'] = $this->settings->get_parse_mode();
		}
		foreach ( $chat_ids as $chat_id ) {
			$results[] = $this->send_message_to_chat( $token, $chat_id, $message, 0, $args );
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
 
	public function send_test_message_rich( string $message ): array {
		if ( ! $this->settings->is_rich_messages_enabled() ) {
			return array( 'ok' => false, 'message' => __( 'Enable rich messages first.', 'telegram-notifications-for-woocommerce' ) );
		}
		$token    = $this->settings->get_bot_token();
		$chat_ids = $this->settings->get_chat_ids();
		if ( '' === $token || empty( $chat_ids ) ) {
			return array( 'ok' => false, 'message' => __( 'Configure bot token and chat IDs.', 'telegram-notifications-for-woocommerce' ) );
		}
		$args = array(
			'parse_mode'   => $this->settings->get_parse_mode(),
			'reply_markup' => wp_json_encode(
				array(
					'inline_keyboard' => array(
						array(
							array( 'text' => __( 'View Orders', 'telegram-notifications-for-woocommerce' ), 'url' => admin_url( 'edit.php?post_type=shop_order' ) ),
						),
					),
				)
			),
		);
		$results = array();
		foreach ( $chat_ids as $chat_id ) {
			$results[] = $this->send_message_to_chat( $token, $chat_id, $message, 0, $args );
		}
		foreach ( $results as $r ) {
			if ( ! $r['ok'] ) {
				return $r;
			}
		}
		return array( 'ok' => true, 'message' => __( 'Rich test sent.', 'telegram-notifications-for-woocommerce' ) );
	}
 
	public function send_test_message_all_bots( string $message ): array {
		$targets = array();
		$token   = $this->settings->get_bot_token();
		$ids     = $this->settings->get_chat_ids();
		if ( '' !== $token && ! empty( $ids ) ) {
			$targets[] = array( 'token' => $token, 'chat_ids' => $ids );
		}
		foreach ( $this->settings->get_additional_bots() as $bot ) {
			$list = array_values( array_filter( array_map( 'trim', explode( ',', (string) $bot['chat_ids'] ) ) ) );
			if ( ! empty( $bot['token'] ) && ! empty( $list ) ) {
				$targets[] = array( 'token' => (string) $bot['token'], 'chat_ids' => $list );
			}
		}
		if ( empty( $targets ) ) {
			return array( 'ok' => false, 'message' => __( 'No bots configured.', 'telegram-notifications-for-woocommerce' ) );
		}
		foreach ( $targets as $t ) {
			foreach ( (array) $t['chat_ids'] as $chat_id ) {
				$r = $this->send_message_to_chat( (string) $t['token'], (string) $chat_id, $message, 0 );
				if ( ! $r['ok'] ) {
					return $r;
				}
			}
		}
		return array( 'ok' => true, 'message' => __( 'Multi-bot test sent.', 'telegram-notifications-for-woocommerce' ) );
	}
 
	private function send_message_to_chat( string $token, string $chat_id, string $message, int $order_id, array $args = array() ): array {
		$url  = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage';
		$body = array(
			'chat_id' => $chat_id,
			'text'    => $message,
		);
		if ( isset( $args['parse_mode'] ) ) {
			$body['parse_mode'] = $args['parse_mode'];
		}
		if ( isset( $args['reply_markup'] ) ) {
			$body['reply_markup'] = $args['reply_markup'];
		}
 
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
 
		$logged_resp = array(
			'ok'          => isset( $decoded['ok'] ) ? (bool) $decoded['ok'] : null,
			'description' => isset( $decoded['description'] ) ? (string) $decoded['description'] : '',
		);
		$this->log_line(
			wp_json_encode(
				array(
					'type'     => 'telegram_response',
					'order_id'  => $order_id,
					'chat_id'   => $chat_id,
					'code'      => $code,
					'response'  => $logged_resp,
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
 
		$size_kb = (int) $this->settings->get_settings()['log_rotation_size_kb'];
		$keep    = (int) $this->settings->get_settings()['log_rotation_keep'];
		if ( $size_kb > 0 && file_exists( $file ) && filesize( $file ) > ( $size_kb * 1024 ) ) {
			for ( $i = $keep - 1; $i >= 1; $i-- ) {
				$src = $file . '.' . $i;
				$dst = $file . '.' . ( $i + 1 );
				if ( file_exists( $src ) ) {
					@rename( $src, $dst );
				}
			}
			@rename( $file, $file . '.1' );
		}
	}
}
