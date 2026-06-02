<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. WEEKLY BACKGROUND TELEMETRY TRACKER
// =========================================================================
add_action( 'itp_hourly_tracking_batch_event', 'lee_dev_maybe_send_telemetry_ping_3821' );
function lee_dev_maybe_send_telemetry_ping_3821() {
    $last_ping = (int) get_option( '_lee_dev_last_telemetry_time', 0 );
    $one_week_in_seconds = 604800; // 7 days * 24h * 60m * 60s
    
    if ( ( time() - $last_ping ) < $one_week_in_seconds ) {
        return; // Guardrail check: rate-limit pings to weekly to conserve memory
    }

    // Build the system telemetry payload
    $dictionary = get_option( 'itp_dynamic_keyword_dictionary', [] );
    $category_count = is_array( $dictionary ) ? count( $dictionary ) : 0;

    global $wpdb;
    $postmeta_table = $wpdb->postmeta;
    $tracked_posts_count = (int) $wpdb->get_var( "
        SELECT COUNT(DISTINCT post_id) 
        FROM {$postmeta_table} 
        WHERE meta_key = '_itp_tracking_labels'
    " );

    $payload = array(
        'domain'         => get_site_url(),
        'php_version'    => PHP_VERSION,
        'wp_version'     => get_bloginfo( 'version' ),
        'wc_active'      => class_exists( 'WooCommerce' ) ? 'yes' : 'no',
        'category_count' => $category_count,
        'posts_count'    => $tracked_posts_count,
        'licence_key'    => get_option( 'lee_dev_licence_key', '' ),
        'licence_status' => get_option( 'lee_dev_licence_status', 'unauthorised' ),
        'timestamp'      => time()
    );

    $endpoint = apply_filters( 'lee_dev_telemetry_endpoint_3821', 'https://telemetry.intenttargetpro.com/ping' );

    // Send payload safely in the background
    $response = wp_safe_remote_post( $endpoint, array(
        'method'      => 'POST',
        'timeout'     => 15,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array( 
            'Content-Type'          => 'application/json',
            'X-ITP-Telemetry-Token' => 'b8f4c2e9d1a3756b90f8d1e4a3b2c7f6e5d8a9b0c1d2e3f4a5b6c7d8e9f0a1b2' // Add this line
        ),
        'body'        => json_encode( $payload ),
        'cookies'     => array()
    ) );

    // Always update option even on failure to avoid looping queries and memory bloat
    update_option( '_lee_dev_last_telemetry_time', time() );
}

// =========================================================================
// 2. LICENCE CHECKING HELPER
// =========================================================================
function lee_dev_check_licence_status_9421() {
    $status = get_option( 'lee_dev_licence_status', 'unauthorised' );
    return ( $status === 'authorised' );
}

// =========================================================================
// 3. LICENCE ACTIVATION FORM HANDLER
// =========================================================================
add_action( 'admin_init', 'lee_dev_process_licence_activation_4812' );
function lee_dev_process_licence_activation_4812() {
    if ( ! isset( $_POST['itp_licence_submit'] ) ) {
        return;
    }

    if ( ! isset( $_POST['itp_licence_nonce'] ) || ! wp_verify_nonce( $_POST['itp_licence_nonce'], 'itp_licence_action' ) ) {
        wp_die( 'Security verification failed.' );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Insufficient permissions to perform this action.' );
    }

    $action = sanitize_text_field( $_POST['itp_licence_action_type'] ?? '' );

    if ( $action === 'activate' ) {
        $raw_key = sanitize_text_field( $_POST['itp_licence_key'] ?? '' );
        $clean_key = trim( $raw_key );
        lee_dev_debug_log_event_6158( 'licence.activation.started', array(
            'domain' => wp_parse_url( home_url(), PHP_URL_HOST ),
        ) );

        if ( empty( $clean_key ) ) {
            update_option( 'lee_dev_licence_status', 'unauthorised' );
            lee_dev_debug_log_event_6158( 'licence.activation.empty_code' );
            wp_safe_redirect( add_query_arg( array( 'page' => 'itp-search-dashboard', 'licence-updated' => 'empty' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $endpoint = apply_filters( 'lee_dev_licence_activation_endpoint_4812', 'https://intenttargetpro.com/wp-json/intenttarget-hub/v1/activate' );
        $response = wp_safe_remote_post( $endpoint, array(
            'timeout' => 15,
            'body'    => array(
                'licence_code'     => $clean_key,
                'activation_email' => get_option( 'admin_email' ),
                'domain'           => wp_parse_url( home_url(), PHP_URL_HOST ),
                'client_url'       => home_url(),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            update_option( 'lee_dev_licence_key', $clean_key );
            update_option( 'lee_dev_licence_status', 'unauthorised' );
            lee_dev_debug_log_event_6158( 'licence.activation.remote_error', array(
                'message' => $response->get_error_message(),
            ) );
            wp_safe_redirect( add_query_arg( array( 'page' => 'itp-search-dashboard', 'licence-updated' => 'invalid' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $response_code = (int) wp_remote_retrieve_response_code( $response );
        $body          = json_decode( wp_remote_retrieve_body( $response ), true );
        $remote_status = is_array( $body ) && isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : 'unauthorised';

        if ( $response_code === 200 && $remote_status === 'active' ) {
            update_option( 'lee_dev_licence_key', $clean_key );
            update_option( 'lee_dev_licence_status', 'authorised' );
            lee_dev_debug_log_event_6158( 'licence.activation.authorised', array(
                'response_code' => $response_code,
                'status'        => $remote_status,
            ) );
            wp_safe_redirect( add_query_arg( array( 'page' => 'itp-search-dashboard', 'licence-updated' => 'authorised' ), admin_url( 'admin.php' ) ) );
            exit;
        } else {
            update_option( 'lee_dev_licence_key', $clean_key );
            update_option( 'lee_dev_licence_status', 'unauthorised' );
            lee_dev_debug_log_event_6158( 'licence.activation.denied', array(
                'response_code' => $response_code,
                'status'        => $remote_status,
            ) );
            wp_safe_redirect( add_query_arg( array( 'page' => 'itp-search-dashboard', 'licence-updated' => 'invalid' ), admin_url( 'admin.php' ) ) );
            exit;
        }
    } elseif ( $action === 'deactivate' ) {
        // Ping Master Hub to release the Core licence remotely.
        $core_key = get_option( 'lee_dev_licence_key', '' );
        if ( ! empty( $core_key ) ) {
            $release_endpoint = apply_filters( 'lee_dev_telemetry_endpoint_3821', 'https://telemetry.intenttargetpro.com/ping' );
            // Wait, we need to send to the correct release endpoint:
            $endpoint = apply_filters( 'lee_dev_licence_release_endpoint', 'https://intenttargetpro.com/wp-json/intenttarget/v1/release' );
            wp_remote_post( $endpoint, array(
                'timeout'  => 10,
                'blocking' => false,
                'body'     => array( 'licence_code' => $core_key ),
            ) );
        }

        delete_option( 'lee_dev_licence_key' );
        update_option( 'lee_dev_licence_status', 'unauthorised' );
        lee_dev_debug_log_event_6158( 'licence.deactivated' );
        wp_safe_redirect( add_query_arg( array( 'page' => 'itp-search-dashboard', 'licence-updated' => 'deactivated' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
// =========================================================================
// 4. DAILY BACKGROUND LICENCE VERIFICATION
// =========================================================================

// Schedule the daily cron job if it isn't already set
add_action( 'init', 'lee_dev_schedule_daily_licence_check_1102' );
function lee_dev_schedule_daily_licence_check_1102() {
    if ( ! wp_next_scheduled( 'itp_daily_licence_verification_event' ) ) {
        wp_schedule_event( time(), 'daily', 'itp_daily_licence_verification_event' );
    }
}

// Hook the verification function to the scheduled event
add_action( 'itp_daily_licence_verification_event', 'lee_dev_verify_licence_remotely_5592' );
function lee_dev_verify_licence_remotely_5592() {
    $current_key    = get_option( 'lee_dev_licence_key', '' );
    $current_status = get_option( 'lee_dev_licence_status', 'unauthorised' );

    // If there is no key or it's already unauthorised locally, skip the remote check
    if ( empty( $current_key ) || $current_status !== 'authorised' ) {
        return;
    }

    $endpoint = apply_filters( 'lee_dev_licence_verification_endpoint_1102', 'https://intenttargetpro.com/wp-json/intenttarget/v1/verify' );
    
    // Send the verification payload
    $response = wp_safe_remote_post( $endpoint, array(
        'timeout'     => 15,
        'redirection' => 5,
        'blocking'    => true,
        'body'        => array(
            'licence_code'     => sanitize_text_field( $current_key ),
            'activation_email' => get_option( 'admin_email' ),
            'domain'           => wp_parse_url( home_url(), PHP_URL_HOST ),
            'client_url'       => home_url(),
        ),
    ) );

    // If the request fails due to a network error, do not deactivate. 
    // We only deactivate on a confirmed rejection from the Master Hub.
    if ( is_wp_error( $response ) ) {
        return; 
    }

    $response_code = (int) wp_remote_retrieve_response_code( $response );
    $body          = json_decode( wp_remote_retrieve_body( $response ), true );
    $remote_status = is_array( $body ) && isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : '';

    // If the Master Hub explicitly returns a 403 or states the licence is deactivated/unauthorised
    if ( $response_code === 403 || in_array( $remote_status, array( 'deactivated', 'unauthorised' ), true ) ) {
        update_option( 'lee_dev_licence_status', 'unauthorised' );
        
        // Optional: Log the event if your debugging function exists
        if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
            lee_dev_debug_log_event_6158( 'licence.remote_deactivation', array(
                'reason' => isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : 'Revoked by Master Hub'
            ) );
        }
    }
}

// Clear the scheduled cron job when the plugin is deactivated locally
register_deactivation_hook( __FILE__, 'lee_dev_clear_licence_cron_on_deactivation_9921' );
function lee_dev_clear_licence_cron_on_deactivation_9921() {
    $timestamp = wp_next_scheduled( 'itp_daily_licence_verification_event' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'itp_daily_licence_verification_event' );
    }
}
