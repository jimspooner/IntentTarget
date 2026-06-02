<?php
namespace IntentTarget\Pro;
if ( ! defined( 'ABSPATH' ) ) exit;
class Licence {
	public static function has_authorised(): bool {
		return ( get_option( 'lee_dev_pro_licence_status', 'unauthorised' ) === 'authorised' );
	}
	public static function check_status(): bool {
		return self::has_authorised();
	}
	public static function render_licence_panel(): void {
		$licence_status = get_option( 'lee_dev_pro_licence_status', 'unauthorised' );
		$licence_key    = get_option( 'lee_dev_pro_licence_key', '' );
		if ( isset( $_GET['pro-licence-updated'] ) ) {
			$status_update = sanitize_text_field( wp_unslash( $_GET['pro-licence-updated'] ) );
			if ( $status_update === 'authorised' ) {
				echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Pro Licence Activated:', 'intenttarget-pro' ) . '</strong> ' . esc_html__( 'The IntentTarget Pro add-on is now authorised on this site.', 'intenttarget-pro' ) . '</p></div>';
			} elseif ( $status_update === 'deactivated' ) {
				echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html__( 'Pro Licence Deactivated:', 'intenttarget-pro' ) . '</strong> ' . esc_html__( 'The Pro add-on has been deactivated locally.', 'intenttarget-pro' ) . '</p></div>';
			} elseif ( $status_update === 'invalid' ) {
				$pro_err_msg = isset( $_GET['err_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['err_msg'] ) ) : '';
				if ( ! empty( $pro_err_msg ) ) {
					echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Pro Licence Denied:', 'intenttarget-pro' ) . '</strong> ' . esc_html( $pro_err_msg ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Pro Licence Denied:', 'intenttarget-pro' ) . '</strong> ' . esc_html__( 'The key provided is invalid. Pro licence codes must use the ITPP- prefix.', 'intenttarget-pro' ) . '</p></div>';
				}
			} elseif ( $status_update === 'empty' ) {
				echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Empty Pro Licence Key:', 'intenttarget-pro' ) . '</strong> ' . esc_html__( 'Please provide a key for activation.', 'intenttarget-pro' ) . '</p></div>';
			}
		}
		$border_colour = ( $licence_status === 'authorised' ) ? '#46b450' : '#d63638';
		?>
		<div style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid <?php echo esc_attr( $border_colour ); ?>;padding:15px 20px;margin:15px 0 20px 0;border-radius:4px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;">
			<div style="min-width:260px;">
				<h3 style="margin:0 0 5px 0;font-size:15px;font-weight:bold;color:#1d2327;">
					<?php echo esc_html__( 'IntentTarget Pro Licence Status:', 'intenttarget-pro' ); ?>
					<span style="color:<?php echo esc_attr( $border_colour ); ?>;text-transform:uppercase;"><?php echo esc_html( $licence_status ); ?></span>
				</h3>
				<p style="margin:0;font-size:13px;color:#646970;">
					<?php if ( $licence_status === 'authorised' ) : ?>
						<?php echo esc_html__( 'The IntentTarget Pro add-on is fully authorised on this domain.', 'intenttarget-pro' ); ?>
					<?php else : ?>
						<?php echo esc_html__( 'Pro features are disabled. Enter your Pro licence code (ITPP-XXXX-XXXX-XXXX) to activate.', 'intenttarget-pro' ); ?>
					<?php endif; ?>
				</p>
			</div>
			<form method="post" action="" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
				<?php wp_nonce_field( 'itp_pro_licence_action', 'itp_pro_licence_nonce' ); ?>
				<?php if ( $licence_status !== 'authorised' ) : ?>
					<input type="hidden" name="itp_pro_licence_action_type" value="activate" />
					<input type="text" name="itp_pro_licence_key" value="<?php echo esc_attr( $licence_key ); ?>" placeholder="ITPP-XXXX-XXXX-XXXX" style="padding:6px 10px;font-size:13px;width:240px;border:1px solid #8c8f94;border-radius:4px;" />
					<input type="submit" name="itp_pro_licence_submit" class="button button-primary" value="<?php echo esc_attr__( 'Activate Pro Licence', 'intenttarget-pro' ); ?>" />
				<?php else : ?>
					<input type="hidden" name="itp_pro_licence_action_type" value="deactivate" />
					<span style="font-family:monospace;font-size:13px;color:#646970;background:#f6f7f7;padding:6px 12px;border:1px solid #ccd0d4;border-radius:4px;"><?php echo esc_html( substr( $licence_key, 0, 7 ) . '...' . substr( $licence_key, -4 ) ); ?></span>
					<input type="submit" name="itp_pro_licence_submit" class="button button-secondary" value="<?php echo esc_attr__( 'Deactivate', 'intenttarget-pro' ); ?>" />
				<?php endif; ?>
			</form>
		</div>
		<?php
	}
	public static function process_activation(): void {
		if ( ! isset( $_POST['itp_pro_licence_submit'] ) ) return;
		if ( ! isset( $_POST['itp_pro_licence_nonce'] ) || ! wp_verify_nonce( $_POST['itp_pro_licence_nonce'], 'itp_pro_licence_action' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'intenttarget-pro' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Insufficient permissions to perform this action.', 'intenttarget-pro' ) );
		$action = sanitize_text_field( $_POST['itp_pro_licence_action_type'] ?? '' );
		if ( $action === 'activate' ) {
			$raw_key = sanitize_text_field( $_POST['itp_pro_licence_key'] ?? '' );
			$clean_key = trim( $raw_key );
			if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
				lee_dev_debug_log_event_6158( 'pro_licence.activation.started', array( 'domain' => wp_parse_url( home_url(), PHP_URL_HOST ) ) );
			}
			if ( empty( $clean_key ) ) {
				update_option( 'lee_dev_pro_licence_status', 'unauthorised' );
				self::redirect( 'empty' );
			}
			if ( strpos( $clean_key, LEE_DEV_PRO_LICENCE_PREFIX ) !== 0 ) {
				update_option( 'lee_dev_pro_licence_key', $clean_key );
				update_option( 'lee_dev_pro_licence_status', 'unauthorised' );
				self::redirect( 'invalid' );
			}
			$endpoint = apply_filters( 'lee_dev_pro_licence_activation_endpoint_8167', LEE_DEV_PRO_ACTIVATION_ENDPOINT );
			$response = wp_safe_remote_post( $endpoint, array(
				'timeout' => 15,
				'headers' => array( 'x_intenttarget_client_auth' => 'ITP_SECURE_CLIENT_HANDSHAKE_2026' ),
				'body'    => array(
					'licence_code'     => $clean_key,
					'plugin_slug'      => LEE_DEV_PRO_PLUGIN_SLUG,
					'activation_email' => get_option( 'admin_email' ),
					'domain'           => wp_parse_url( home_url(), PHP_URL_HOST ),
					'client_url'       => home_url(),
					'client_auth'      => 'ITP_SECURE_CLIENT_HANDSHAKE_2026',
				),
			) );
			if ( is_wp_error( $response ) ) {
				update_option( 'lee_dev_pro_licence_key', $clean_key );
				update_option( 'lee_dev_pro_licence_status', 'unauthorised' );
				if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
					lee_dev_debug_log_event_6158( 'pro_licence.activation.remote_error', array( 'message' => $response->get_error_message() ) );
				}
				self::redirect( 'invalid' );
			}
			$response_code = (int) wp_remote_retrieve_response_code( $response );
			$body          = json_decode( wp_remote_retrieve_body( $response ), true );
			$remote_status = is_array( $body ) && isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : 'unauthorised';
			$remote_msg    = is_array( $body ) && isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : '';
			if ( $response_code === 200 && $remote_status === 'active' ) {
				update_option( 'lee_dev_pro_licence_key', $clean_key );
				update_option( 'lee_dev_pro_licence_status', 'authorised' );
				if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
					lee_dev_debug_log_event_6158( 'pro_licence.activation.authorised', array( 'response_code' => $response_code, 'status' => $remote_status ) );
				}
				self::redirect( 'authorised' );
			} else {
				update_option( 'lee_dev_pro_licence_key', $clean_key );
				update_option( 'lee_dev_pro_licence_status', 'unauthorised' );
				if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
					lee_dev_debug_log_event_6158( 'pro_licence.activation.denied', array( 'response_code' => $response_code, 'status' => $remote_status, 'message' => $remote_msg ) );
				}
				self::redirect( 'invalid', $remote_msg );
			}
		} elseif ( $action === 'deactivate' ) {
			$pro_key = get_option( 'lee_dev_pro_licence_key', '' );
			if ( ! empty( $pro_key ) ) {
				$endpoint = apply_filters( 'lee_dev_pro_licence_release_endpoint', 'https://intenttargetpro.com/wp-json/intenttarget/v1/release' );
				wp_remote_post( $endpoint, array( 'timeout' => 10, 'blocking' => false, 'body' => array( 'licence_code' => $pro_key ) ) );
			}
			delete_option( 'lee_dev_pro_licence_key' );
			update_option( 'lee_dev_pro_licence_status', 'unauthorised' );
			if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) lee_dev_debug_log_event_6158( 'pro_licence.deactivated' );
			self::redirect( 'deactivated' );
		}
	}
	public static function redirect( string $status, string $error_message = '' ): void {
		$args = array( 'page' => 'itp-search-dashboard', 'tab' => 'design_pro', 'pro-licence-updated' => sanitize_text_field( $status ) );
		if ( ! empty( $error_message ) ) $args['err_msg'] = rawurlencode( $error_message );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
	public static function schedule_daily_check(): void {
		if ( ! wp_next_scheduled( 'itp_pro_daily_licence_verification_event' ) ) {
			wp_schedule_event( time(), 'daily', 'itp_pro_daily_licence_verification_event' );
		}
	}
	public static function verify_remotely(): void {
		$current_key    = get_option( 'lee_dev_pro_licence_key', '' );
		$current_status = get_option( 'lee_dev_pro_licence_status', 'unauthorised' );
		if ( empty( $current_key ) || $current_status !== 'authorised' ) return;
		$endpoint = apply_filters( 'lee_dev_pro_licence_verification_endpoint_5708', LEE_DEV_PRO_VERIFICATION_ENDPOINT );
		$response = wp_safe_remote_post( $endpoint, array(
			'timeout'     => 15,
			'redirection' => 5,
			'blocking'    => true,
			'body'        => array(
				'licence_code'     => sanitize_text_field( $current_key ),
				'plugin_slug'      => LEE_DEV_PRO_PLUGIN_SLUG,
				'activation_email' => get_option( 'admin_email' ),
				'domain'           => wp_parse_url( home_url(), PHP_URL_HOST ),
				'client_url'       => home_url(),
			),
		) );
		if ( is_wp_error( $response ) ) return;
		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$body          = json_decode( wp_remote_retrieve_body( $response ), true );
		$remote_status = is_array( $body ) && isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : '';
		if ( $response_code === 403 || in_array( $remote_status, array( 'deactivated', 'unauthorised' ), true ) ) {
			update_option( 'lee_dev_pro_licence_status', 'unauthorised' );
			if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
				lee_dev_debug_log_event_6158( 'pro_licence.remote_deactivation', array( 'reason' => isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : 'Revoked by Master Hub' ) );
			}
		}
	}
}
