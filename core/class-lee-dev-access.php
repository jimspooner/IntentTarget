<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'CIT_ALLOWED_EMAILS' ) ) {
    define( 'CIT_ALLOWED_EMAILS', array( 'jim@finedesign.co.uk', 'mhaverty@theandersonscentre.co.uk' ) );
}

function lee_dev_is_ready_8293() {
    $current_user = wp_get_current_user();
    if ( ! $current_user || ! $current_user->exists() ) {
        return false;
    }

    if ( in_array( $current_user->user_email, CIT_ALLOWED_EMAILS, true ) ) {
        return true;
    }

    $allowed_roles = get_option( 'cit_allowed_tracking_roles', array() );
    if ( empty( $allowed_roles ) ) return false;

    foreach ( $current_user->roles as $role ) {
        if ( in_array( $role, $allowed_roles, true ) ) return true;
    }

    return false;
}

add_action( 'admin_init', 'lee_dev_process_access_settings_submission_5821' );
function lee_dev_process_access_settings_submission_5821() {
    if ( isset( $_POST['cit_save_access_settings'] ) ) {
        check_admin_referer( 'cit_save_access_settings_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Insufficient permissions.' );

        $selected_roles = isset( $_POST['cit_roles'] ) && is_array( $_POST['cit_roles'] ) 
            ? array_map( 'sanitize_text_field', $_POST['cit_roles'] ) 
            : array();
            
        update_option( 'cit_allowed_tracking_roles', $selected_roles );
        wp_safe_redirect( add_query_arg( array( 'page' => 'cit-search-dashboard', 'tab' => 'access_control', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
