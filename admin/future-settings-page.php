<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Telegram Notifications — Pro / Future Features', 'telegram-notifications-for-woocommerce' ); ?></h1>
	<?php
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'advanced';
		$tabs = array(
			'advanced' => __( 'Advanced Notifications', 'telegram-notifications-for-woocommerce' ),
			'filters'  => __( 'Filters & Conditions', 'telegram-notifications-for-woocommerce' ),
			'rich'     => __( 'Rich Message / Buttons', 'telegram-notifications-for-woocommerce' ),
			'team'     => __( 'Team / Multi-Bot', 'telegram-notifications-for-woocommerce' ),
			'logs'     => __( 'Logs & Analytics', 'telegram-notifications-for-woocommerce' ),
			'ai'       => __( 'AI Message Generator', 'telegram-notifications-for-woocommerce' ),
		);
		$sections_for_tab = array(
			'advanced' => array( 'onft_pro_advanced' ),
			'filters'  => array( 'onft_pro_filters' ),
			'rich'     => array( 'onft_pro_rich' ),
			'team'     => array( 'onft_pro_team' ),
			'logs'     => array( 'onft_pro_logs' ),
			'ai'       => array( 'onft_pro_ai' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'advanced';
		}
	?>

	<h2 class="nav-tab-wrapper" style="margin-bottom: 12px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=onft-telegram-notifications' ) ); ?>" class="nav-tab"><?php esc_html_e( 'General Settings', 'telegram-notifications-for-woocommerce' ); ?></a>
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'onft-telegram-pro', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</h2>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'onft_pro_settings_group' );

		global $wp_settings_sections, $wp_settings_fields;
		$page = 'onft_pro_settings_page';
		if ( isset( $wp_settings_sections[ $page ] ) ) {
			foreach ( (array) $wp_settings_sections[ $page ] as $section_id => $section ) {
				if ( ! in_array( $section_id, (array) $sections_for_tab[ $tab ], true ) ) {
					continue;
				}
				if ( $section['title'] ) {
					echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
				}
				if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
				}
				echo '<table class="form-table" role="presentation"><tbody>';
				do_settings_fields( $page, $section_id );
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
