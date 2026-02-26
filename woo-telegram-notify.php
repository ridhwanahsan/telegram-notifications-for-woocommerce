<?php
/**
 * Plugin Name: Order Notifications for WooCommerce Telegram
 * Description: Send Telegram notifications automatically when a WooCommerce order is created or its status changes.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Order Notifications for WooCommerce Telegram
 * Author URI: https://github.com/ridhwanahsan
 * Plugin URI: https://github.com/ridhwanahsan/woo-telegram-notify
 * Contributors: ridhwanahsann
 * GitHub: ridhwanahsan
 * License: GPLv2 or later
 * Text Domain: telegram-notifications-for-woocommerce
 *
 * @package ONFT
 */
 
defined( 'ABSPATH' ) || exit;
 
add_action(
	'activated_plugin',
	static function ( string $plugin ): void {
		if ( $plugin === plugin_basename( __FILE__ ) && ob_get_level() > 0 ) {
			@ob_clean();
		}
	},
	10,
	1
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( class_exists( 'ONFT_Main' ) ) {
			ONFT_Main::instance();
		}
	},
	20
);

if ( ! defined( 'ONFT_PLUGIN_FILE' ) ) {
	define( 'ONFT_PLUGIN_FILE', __FILE__ );
}
 
if ( ! defined( 'ONFT_PLUGIN_DIR' ) ) {
	define( 'ONFT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
 
require_once ONFT_PLUGIN_DIR . 'includes/class-main.php';
require_once ONFT_PLUGIN_DIR . 'includes/class-telegram.php';
require_once ONFT_PLUGIN_DIR . 'includes/class-settings.php';


