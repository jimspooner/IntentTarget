<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'ITP_ALLOWED_EMAILS' ) ) {
    define( 'ITP_ALLOWED_EMAILS', array( 'jim@finedesign.co.uk' ) );
}

function lee_dev_is_ready_8293() {
    if ( ! function_exists( 'lee_dev_has_authorised_licence_7365' ) || ! lee_dev_has_authorised_licence_7365() ) {
        return false;
    }

    $current_user = wp_get_current_user();
    if ( ! $current_user || ! $current_user->exists() ) {
        return false;
    }

    if ( in_array( $current_user->user_email, ITP_ALLOWED_EMAILS, true ) ) {
        return true;
    }

    $allowed_roles = get_option( 'itp_allowed_tracking_roles', array() );
    if ( empty( $allowed_roles ) ) return false;

    foreach ( $current_user->roles as $role ) {
        if ( in_array( $role, $allowed_roles, true ) ) return true;
    }

    return false;
}

function lee_dev_has_authorised_licence_7365() {
    return ( get_option( 'lee_dev_licence_status', 'unauthorised' ) === 'authorised' );
}

function lee_dev_debug_logging_is_enabled_2846() {
    return ( get_option( 'itp_debug_logging_enabled_2846', 'no' ) === 'yes' );
}

function lee_dev_debug_log_event_6158( $event_name, $context = array() ) {
    if ( ! lee_dev_debug_logging_is_enabled_2846() ) {
        return;
    }

    $safe_event_name = sanitize_text_field( (string) $event_name );
    $safe_context    = is_array( $context ) ? wp_json_encode( $context ) : sanitize_text_field( (string) $context );

    error_log( '[IntentTarget Pro] ' . $safe_event_name . ' ' . $safe_context );
}

add_action( 'admin_init', 'lee_dev_process_debug_logging_toggle_2846' );
function lee_dev_process_debug_logging_toggle_2846() {
    if ( ! isset( $_POST['itp_debug_logging_toggle'] ) ) {
        return;
    }

    check_admin_referer( 'itp_debug_logging_toggle_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Insufficient permissions.' );

    $enabled = isset( $_POST['itp_debug_logging_enabled'] ) ? 'yes' : 'no';
    update_option( 'itp_debug_logging_enabled_2846', $enabled );
    lee_dev_debug_log_event_6158( 'debug.toggle.updated', array( 'enabled' => $enabled ) );

    wp_safe_redirect( add_query_arg( array( 'page' => 'itp-search-dashboard', 'debug-updated' => 'true' ), admin_url( 'admin.php' ) ) );
    exit;
}

add_action( 'admin_init', 'lee_dev_process_access_settings_submission_5821' );
function lee_dev_process_access_settings_submission_5821() {
    if ( isset( $_POST['itp_save_access_settings'] ) ) {
        check_admin_referer( 'itp_save_access_settings_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Insufficient permissions.' );

        $selected_roles = isset( $_POST['itp_roles'] ) && is_array( $_POST['itp_roles'] ) 
            ? array_map( 'sanitize_text_field', $_POST['itp_roles'] ) 
            : array();
            
        update_option( 'itp_allowed_tracking_roles', $selected_roles );
        lee_dev_debug_log_event_6158( 'access.roles.updated', array( 'roles' => $selected_roles ) );
        wp_safe_redirect( add_query_arg( array( 'page' => 'itp-search-dashboard', 'tab' => 'access_control', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
