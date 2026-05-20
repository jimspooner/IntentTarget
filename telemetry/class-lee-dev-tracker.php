<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. WEEKLY BACKGROUND TELEMETRY TRACKER
// =========================================================================
add_action( 'cit_hourly_tracking_batch_event', 'lee_dev_maybe_send_telemetry_ping_3821' );
function lee_dev_maybe_send_telemetry_ping_3821() {
    $last_ping = (int) get_option( '_lee_dev_last_telemetry_time', 0 );
    $one_week_in_seconds = 604800; // 7 days * 24h * 60m * 60s
    
    if ( ( time() - $last_ping ) < $one_week_in_seconds ) {
        return; // Guardrail check: rate-limit pings to weekly to conserve memory
    }

    // Build the system telemetry payload
    $dictionary = get_option( 'cit_dynamic_keyword_dictionary', [] );
    $category_count = is_array( $dictionary ) ? count( $dictionary ) : 0;

    global $wpdb;
    $postmeta_table = $wpdb->postmeta;
    $tracked_posts_count = (int) $wpdb->get_var( "
        SELECT COUNT(DISTINCT post_id) 
        FROM {$postmeta_table} 
        WHERE meta_key = '_cit_tracking_labels'
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

    $endpoint = apply_filters( 'lee_dev_telemetry_endpoint_3821', 'https://telemetry.intenttargetpro.co.uk/ping' );

    // Send payload safely in the background
    $response = wp_safe_remote_post( $endpoint, array(
        'method'      => 'POST',
        'timeout'     => 15,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array( 'Content-Type' => 'application/json' ),
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
    if ( ! isset( $_POST['cit_licence_submit'] ) ) {
        return;
    }

    if ( ! isset( $_POST['cit_licence_nonce'] ) || ! wp_verify_nonce( $_POST['cit_licence_nonce'], 'cit_licence_action' ) ) {
        wp_die( 'Security verification failed.' );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Insufficient permissions to perform this action.' );
    }

    $action = sanitize_text_field( $_POST['cit_licence_action_type'] ?? '' );

    if ( $action === 'activate' ) {
        $raw_key = sanitize_text_field( $_POST['cit_licence_key'] ?? '' );
        $clean_key = trim( $raw_key );

        if ( empty( $clean_key ) ) {
            update_option( 'lee_dev_licence_status', 'unauthorised' );
            wp_safe_redirect( add_query_arg( array( 'page' => 'cit-search-dashboard', 'licence-updated' => 'empty' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        // Mock verification validation - key must be at least 10 characters
        if ( strlen( $clean_key ) >= 10 ) {
            update_option( 'lee_dev_licence_key', $clean_key );
            update_option( 'lee_dev_licence_status', 'authorised' );
            wp_safe_redirect( add_query_arg( array( 'page' => 'cit-search-dashboard', 'licence-updated' => 'authorised' ), admin_url( 'admin.php' ) ) );
            exit;
        } else {
            update_option( 'lee_dev_licence_key', $clean_key );
            update_option( 'lee_dev_licence_status', 'unauthorised' );
            wp_safe_redirect( add_query_arg( array( 'page' => 'cit-search-dashboard', 'licence-updated' => 'invalid' ), admin_url( 'admin.php' ) ) );
            exit;
        }
    } elseif ( $action === 'deactivate' ) {
        delete_option( 'lee_dev_licence_key' );
        update_option( 'lee_dev_licence_status', 'unauthorised' );
        wp_safe_redirect( add_query_arg( array( 'page' => 'cit-search-dashboard', 'licence-updated' => 'deactivated' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
