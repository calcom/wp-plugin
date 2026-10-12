<?php

namespace CalCom\Admin;

defined( 'ABSPATH' ) || exit;

class ApiSettings {

	public function hooks() {
		add_action( 'in_admin_header', array( $this, 'clear_unwanted_notices' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_calcom_save_api_key', array( $this, 'save_api_key' ) );
	}

	public function clear_unwanted_notices() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading admin page slug.
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading admin page slug.
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		if ( strpos( $page, 'calcom' ) === 0 ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}

	public function menu() {
		add_submenu_page(
			'calcom',
			__( 'Cal.com Settings', 'cal-com' ),
			__( 'Settings', 'cal-com' ),
			'manage_options',
			'calcom-settings',
			array( $this, 'render' )
		);
	}

	public function save_api_key() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Nonce verified below.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'calcom_save_api_key' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'cal-com' ) );
		}

		if ( isset( $_POST['calcom_api_key'] ) ) {
			$api_key = sanitize_text_field( wp_unslash( $_POST['calcom_api_key'] ) );
		} else {
			$api_key = '';
		}

		$credentials = new \CalCom\Credentials();
		$credentials->save( $api_key );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect after save.
		wp_safe_redirect( add_query_arg( 'calcom_updated', 'settings-saved', wp_get_referer() ) );
		exit;
	}

	public function render() {
		wp_enqueue_style( 'calcom-customizer-css' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cal.com Settings', 'cal-com' ); ?></h1>

            <?php // @phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of query arg.
			if ( isset( $_GET['calcom_updated'] ) && $_GET['calcom_updated'] === 'settings-saved' ) :
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'cal-com' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="calcom-settings">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'calcom_save_api_key', '_wpnonce' ); ?>
					<input type="hidden" name="action" value="calcom_save_api_key">

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="calcom_api_key"><?php esc_html_e( 'Cal.com API Key', 'cal-com' ); ?></label>
							</th>
							<td>
								<?php
								$credentials = new \CalCom\Credentials();
								$current_key = $credentials->get();
								?>
								<input type="text" id="calcom_api_key" name="calcom_api_key" value="<?php echo esc_attr( $current_key ? '***' : '' ); ?>" class="regular-text" autocomplete="off">
								<?php if ( $current_key ) : ?>
									<p class="description"><?php esc_html_e( 'API key is set. Delete it and save to update.', 'cal-com' ); ?></p>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'Enter your Cal.com personal API key.', 'cal-com' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Settings', 'cal-com' ), 'primary', 'submit' ); ?>
				</form>
			</div>
		</div>
		<?php
	}
}
