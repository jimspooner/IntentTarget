<?php
/**
 * Plugin Name:       IntentTarget Master Hub
 * Plugin URI:        https://intenttargetpro.com
 * Description:       Standalone master server interface for IntentTarget Pro licence verification and client telemetry tracking.
 * Version:           1.0.0
 * Requires PHP:      8.0
 * Author:            Jim / Lee Dev
 * Author URI:        https://lee-dev.co.uk
 * License:           GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// =========================================================================
// 1. DATABASE BUILDER FOR LICENCE AND TELEMETRY LOGS
// =========================================================================
register_activation_hook( __FILE__, 'lee_dev_install_master_hub_tables_2941' );
add_action( 'init', 'lee_dev_install_master_hub_tables_2941' );

function lee_dev_install_master_hub_tables_2941() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Table to track licence verifications and requests
    $table_licences = $wpdb->prefix . 'lee_dev_licence_verifications_2941';
    $sql_licences = "CREATE TABLE $table_licences (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        licence_key varchar(100) NOT NULL,
        domain varchar(255) NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'unauthorised',
        verified_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_licences );
}

// =========================================================================
// 2. REST API ROUTING REGISTRATION
// =========================================================================
add_action( 'rest_api_init', 'lee_dev_register_master_hub_api_endpoints_9381' );
function lee_dev_register_master_hub_api_endpoints_9381() {
    register_rest_route( 'intenttarget/v1', '/verify', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'lee_dev_handle_licence_verification_request_1842',
        'permission_callback' => '__return_true', // Authorisation checks occur within endpoint logic
    ) );
}

// =========================================================================
// 3. LICENCE VERIFICATION CALLBACK HANDLER
// =========================================================================
function lee_dev_handle_licence_verification_request_1842( WP_REST_Request $request ) {
    global $wpdb;
    $params = $request->get_params();

    // Accept licence_key or license_key to preserve UK spelling compliance while maintaining API flexibility
    $raw_key = ! empty( $params['licence_key'] ) ? $params['licence_key'] : ( ! empty( $params['license_key'] ) ? $params['license_key'] : '' );
    $licence_key = sanitize_text_field( $raw_key );
    $domain      = sanitize_text_field( $params['domain'] ?? '' );

    if ( empty( $licence_key ) || empty( $domain ) ) {
        return new WP_REST_Response( array(
            'success' => false,
            'status'  => 'unauthorised',
            'message' => 'Missing licence_key or domain parameters.'
        ), 400 );
    }

    // Mock validation rule: key must be at least 10 characters
    $is_authorised = ( strlen( $licence_key ) >= 10 );
    $status = $is_authorised ? 'authorised' : 'unauthorised';

    // Insert payload verification record into SQL database
    $table_licences = $wpdb->prefix . 'lee_dev_licence_verifications_2941';
    $wpdb->insert(
        $table_licences,
        array(
            'licence_key' => $licence_key,
            'domain'      => $domain,
            'status'      => $status,
            'verified_at' => current_time( 'mysql' )
        ),
        array( '%s', '%s', '%s', '%s' )
    );

    // Return the response payload matching standard communication protocols
    return new WP_REST_Response( array(
        'success' => true,
        'status'  => $status,
        'message' => $is_authorised ? 'Licence is active and authorised.' : 'Licence key is invalid or unauthorised.'
    ), 200 );
}
