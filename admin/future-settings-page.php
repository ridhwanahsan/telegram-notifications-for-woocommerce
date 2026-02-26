<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Telegram Notifications — Pro / Future Features', 'telegram-notifications-for-woocommerce' ); ?></h1>
	<?php
		$onft_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';
		$onft_tab   = 'advanced';
		if ( '' !== $onft_nonce && wp_verify_nonce( $onft_nonce, 'onft_pro_tabs' ) ) {
			$onft_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'advanced';
		}
		$onft_tabs = array(
			'advanced' => __( 'Advanced Notifications', 'telegram-notifications-for-woocommerce' ),
			'filters'  => __( 'Filters & Conditions', 'telegram-notifications-for-woocommerce' ),
			'rich'     => __( 'Rich Message / Buttons', 'telegram-notifications-for-woocommerce' ),
			'team'     => __( 'Team / Multi-Bot', 'telegram-notifications-for-woocommerce' ),
			'logs'     => __( 'Logs & Analytics', 'telegram-notifications-for-woocommerce' ),
			'ai'       => __( 'AI Message Generator', 'telegram-notifications-for-woocommerce' ),
		);
		$onft_sections_for_tab = array(
			'advanced' => array( 'onft_pro_advanced' ),
			'filters'  => array( 'onft_pro_filters' ),
			'rich'     => array( 'onft_pro_rich' ),
			'team'     => array( 'onft_pro_team' ),
			'logs'     => array( 'onft_pro_logs' ),
			'ai'       => array( 'onft_pro_ai' ),
		);
		if ( ! isset( $onft_tabs[ $onft_tab ] ) ) {
			$onft_tab = 'advanced';
		}
	?>

	<h2 class="nav-tab-wrapper" style="margin-bottom: 12px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=onft-telegram-notifications' ) ); ?>" class="nav-tab"><?php esc_html_e( 'General Settings', 'telegram-notifications-for-woocommerce' ); ?></a>
		<?php foreach ( $onft_tabs as $onft_key => $onft_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'onft-telegram-pro', 'tab' => $onft_key, '_wpnonce' => wp_create_nonce( 'onft_pro_tabs' ) ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $onft_tab === $onft_key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $onft_label ); ?></a>
		<?php endforeach; ?>
	</h2>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'onft_pro_settings_group' );

		global $wp_settings_sections, $wp_settings_fields;
		$onft_page = 'onft_pro_settings_page';
		if ( isset( $wp_settings_sections[ $onft_page ] ) ) {
			foreach ( (array) $wp_settings_sections[ $onft_page ] as $onft_section_id => $onft_section ) {
				if ( ! in_array( $onft_section_id, (array) $onft_sections_for_tab[ $onft_tab ], true ) ) {
					continue;
				}
				if ( $onft_section['title'] ) {
					echo '<h2>' . esc_html( $onft_section['title'] ) . '</h2>';
				}
				if ( $onft_section['callback'] ) {
					call_user_func( $onft_section['callback'], $onft_section );
				}
				echo '<table class="form-table" role="presentation"><tbody>';
				do_settings_fields( $onft_page, $onft_section_id );
				echo '</tbody></table>';
			}
		}

		submit_button( __( 'Save Pro Settings', 'telegram-notifications-for-woocommerce' ) );
		?>
	</form>

	<hr />
	<h2><?php esc_html_e( 'Feature Tests', 'telegram-notifications-for-woocommerce' ); ?></h2>
	<div style="display:flex; gap:24px; flex-wrap:wrap;">
		<div style="flex:1; min-width:280px;">
			<h3><?php esc_html_e( 'Rich Message / Buttons', 'telegram-notifications-for-woocommerce' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="onft_test_rich_message" />
				<?php wp_nonce_field( 'onft_test_rich_message' ); ?>
				<?php submit_button( __( 'Send Rich Test', 'telegram-notifications-for-woocommerce' ), 'secondary' ); ?>
			</form>
		</div>
		<div style="flex:1; min-width:280px;">
			<h3><?php esc_html_e( 'Multiple Bots', 'telegram-notifications-for-woocommerce' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="onft_test_multi_bot" />
				<?php wp_nonce_field( 'onft_test_multi_bot' ); ?>
				<?php submit_button( __( 'Send Multi-Bot Test', 'telegram-notifications-for-woocommerce' ), 'secondary' ); ?>
			</form>
		</div>
		<div style="flex:1; min-width:280px;">
			<h3><?php esc_html_e( 'AI Message Generator', 'telegram-notifications-for-woocommerce' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="onft_test_ai_message" />
				<?php wp_nonce_field( 'onft_test_ai_message' ); ?>
				<?php submit_button( __( 'Send AI Test', 'telegram-notifications-for-woocommerce' ), 'secondary' ); ?>
			</form>
		</div>
	</div>
</div>
