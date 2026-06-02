<?php
/**
 * IntentTarget Core — Access Control & Licence Gating
 *
 * @package IntentTarget\Core
 */

namespace IntentTarget\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Access
 *
 * Centralises licence checks, readiness gates, debug logging,
 * and automatic cache purging on licence state changes.
 */
class Access {

	/**
	 * Allowed admin email addresses for unconditional access.
	 *
	 * @var string[]
	 */
	private static array $allowed_emails = array( 'jim@finedesign.co.uk' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'update_option', array( __CLASS__, 'purge_page_caches_on_licence_change' ), 10, 3 );
	}

	/**
	 * Check whether the current visitor is "ready" for tracking.
	 *
	 * Requires an authorised licence + logged-in user + allowed role/email.
	 *
	 * @return bool
	 */
	public static function is_ready(): bool {
		if ( ! self::has_authorised_licence() ) {
			return false;
		}

		$current_user = wp_get_current_user();
		if ( ! $current_user || ! $current_user->exists() ) {
			return false;
		}

		if ( in_array( $current_user->user_email, self::$allowed_emails, true ) ) {
			return true;
		}

		return (bool) apply_filters( 'lee_dev_is_ready_role_gate_5821', true, $current_user );
	}

	/**
	 * Check whether the Core licence is authorised locally.
	 *
	 * @return bool
	 */
	public static function has_authorised_licence(): bool {
		return ( get_option( 'lee_dev_licence_status', 'unauthorised' ) === 'authorised' );
	}

	/**
	 * Centralised add-on activation gate.
	 *
	 * @param string $addon_slug Add-on identifier, e.g. 'pro'.
	 * @return bool
	 */
	public static function is_addon_active( string $addon_slug ): bool {
		$slug = sanitize_key( $addon_slug );
		if ( $slug === '' ) {
			return false;
		}

		$option_map = apply_filters( 'lee_dev_addon_status_option_map_3812', array(
			'core' => 'lee_dev_licence_status',
			'pro'  => 'lee_dev_pro_licence_status',
		) );

		if ( ! isset( $option_map[ $slug ] ) ) {
			return false;
		}

		return ( get_option( $option_map[ $slug ], 'unauthorised' ) === 'authorised' );
	}

	/**
	 * Check whether debug logging is enabled.
	 *
	 * @return bool
	 */
	public static function is_debug_logging_enabled(): bool {
		return ( get_option( 'itp_debug_logging_enabled_2846', 'no' ) === 'yes' );
	}

	/**
	 * Write a debug event to the error log.
	 *
	 * @param string     $event_name Event identifier.
	 * @param array|null $context    Optional contextual data.
	 * @return void
	 */
	public static function debug_log_event( string $event_name, ?array $context = array() ): void {
		if ( ! self::is_debug_logging_enabled() ) {
			return;
		}

		$safe_event = sanitize_text_field( $event_name );
		$safe_ctx   = is_array( $context ) ? wp_json_encode( $context ) : '';

		if ( defined( 'ITP_DEV_DEBUG' ) && ITP_DEV_DEBUG ) {
			error_log( '[IntentTarget Pro] ' . $safe_event . ' ' . $safe_ctx );
		}
	}

	/**
	 * Purge all supported page caches when a licence option changes.
	 *
	 * Hooked to `update_option`.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 * @return void
	 */
	public static function purge_page_caches_on_licence_change( string $option, $old_value, $value ): void {
		$licence_options = array( 'lee_dev_licence_status', 'lee_dev_pro_licence_status' );
		if ( ! in_array( $option, $licence_options, true ) ) {
			return;
		}
		if ( $old_value === $value ) {
			return;
		}

		$purgers = array(
			'rocket_clean_domain',
			'w3tc_flush_all',
			'wp_cache_clear_cache',
			'wpfc_clear_all_cache',
			'wp_hummingbird_cache_clear',
			'sg_cachepress_purge_cache',
			'swcfpc_purge_all',
		);

		foreach ( $purgers as $fn ) {
			if ( function_exists( $fn ) ) {
				$fn();
			}
		}

		// LiteSpeed Cache
		if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
			LiteSpeed_Cache_API::purge_all();
		}

		// WP Engine
		if ( class_exists( 'WPEngine\Cache\Purge' ) ) {
			$wpe = new \WPEngine\Cache\Purge();
			if ( method_exists( $wpe, 'purge_all' ) ) {
				$wpe->purge_all();
			}
		}

		// Kinsta
		if ( class_exists( 'Kinsta\Cache' ) ) {
			$kinsta = new \Kinsta\Cache();
			if ( method_exists( $kinsta, 'purge_complete_cache' ) ) {
				$kinsta->purge_complete_cache();
			}
		}

		self::debug_log_event( 'cache.purge.licence_change', array(
			'option'    => $option,
			'old_value' => $old_value,
			'new_value' => $value,
		) );
	}
}
