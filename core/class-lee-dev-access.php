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

    /**
     * Role-based gating is owned by the IntentTarget Pro add-on. With no listener attached the
     * filter returns the default (true), meaning every authorised logged-in user passes the
     * readiness check. When IntentTarget Pro is active it hooks this filter and restricts the
     * answer to the admin-configured `itp_allowed_tracking_roles` list.
     */
    return (bool) apply_filters( 'lee_dev_is_ready_role_gate_5821', true, $current_user );
}

function lee_dev_has_authorised_licence_7365() {
    return ( get_option( 'lee_dev_licence_status', 'unauthorised' ) === 'authorised' );
}

/**
 * Centralised add-on activation gate used by every IntentTarget add-on (Pro, Webhooks, Quizzes, AI...).
 *
 * The function returns true ONLY when the Master Hub has confirmed a paid licence for the requested
 * add-on slug, with the verified status saved into wp_options. Add-ons must wrap all functional code
 * in this gate so unauthorised installs remain dormant whilst the admin UI stays visible per the
 * documented up-sell behaviour.
 *
 * Supported slugs:
 *  - 'core' : the base IntentTarget plugin (option 'lee_dev_licence_status')
 *  - 'pro'  : the IntentTarget Pro add-on    (option 'lee_dev_pro_licence_status')
 *
 * Additional add-ons can be registered via the `lee_dev_addon_status_option_map_3812` filter, which
 * receives the slug => option-name map.
 *
 * @param string $addon_slug Add-on identifier, e.g. 'pro'.
 * @return bool True when the add-on holds an authorised licence locally.
 */
function lee_dev_is_addon_active_3812( $addon_slug ) {
    $slug = sanitize_key( (string) $addon_slug );
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

function lee_dev_debug_logging_is_enabled_2846() {
    return ( get_option( 'itp_debug_logging_enabled_2846', 'no' ) === 'yes' );
}

function lee_dev_debug_log_event_6158( $event_name, $context = array() ) {
    if ( ! lee_dev_debug_logging_is_enabled_2846() ) {
        return;
    }

    $safe_event_name = sanitize_text_field( (string) $event_name );
    $safe_context    = is_array( $context ) ? wp_json_encode( $context ) : sanitize_text_field( (string) $context );

    if ( defined('ITP_DEV_DEBUG') && ITP_DEV_DEBUG ) {
    error_log( '[IntentTarget Pro] bootstrap.loaded ' . json_encode($status_array) );
}

    
    //error_log( '[IntentTarget Pro] ' . $safe_event_name . ' ' . $safe_context );
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


