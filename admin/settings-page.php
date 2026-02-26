<?php
 
defined( 'ABSPATH' ) || exit;
 
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Telegram Notifications', 'telegram-notifications-for-woocommerce' ); ?></h1>
 
	<form method="post" action="options.php">
		<?php
		settings_fields( 'onft_settings_group' );
		do_settings_sections( 'onft_settings_page' );
		submit_button( __( 'Save Settings', 'telegram-notifications-for-woocommerce' ) );
		?>
	</form>
 
	<hr />
 
	<h2><?php esc_html_e( 'Test Notification', 'telegram-notifications-for-woocommerce' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="onft_test_notification" />
		<?php wp_nonce_field( 'onft_test_notification' ); ?>
		<?php submit_button( __( 'Send Test Telegram Message', 'telegram-notifications-for-woocommerce' ), 'secondary' ); ?>
	</form>
</div>
