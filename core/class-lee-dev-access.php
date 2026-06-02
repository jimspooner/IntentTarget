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

// =========================================================================
// CACHE PURGE HELPER — fires on every licence status change
// =========================================================================
add_action( 'update_option', 'lee_dev_purge_page_caches_on_licence_change_7381', 10, 3 );
function lee_dev_purge_page_caches_on_licence_change_7381( $option, $old_value, $value ) {
    $licence_options = array( 'lee_dev_licence_status', 'lee_dev_pro_licence_status' );
    if ( ! in_array( $option, $licence_options, true ) ) {
        return;
    }
    if ( $old_value === $value ) {
        return;
    }

    // WP Rocket
    if ( function_exists( 'rocket_clean_domain' ) ) {
        rocket_clean_domain();
    }

    // W3 Total Cache
    if ( function_exists( 'w3tc_flush_all' ) ) {
        w3tc_flush_all();
    }

    // WP Super Cache
    if ( function_exists( 'wp_cache_clear_cache' ) ) {
        wp_cache_clear_cache();
    }

    // LiteSpeed Cache
    if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
        LiteSpeed_Cache_API::purge_all();
    }

    // WP Fastest Cache
    if ( function_exists( 'wpfc_clear_all_cache' ) ) {
        wpfc_clear_all_cache();
    }

    // Hummingbird
    if ( function_exists( 'wp_hummingbird_cache_clear' ) ) {
        wp_hummingbird_cache_clear();
    }

    // SG Optimiser
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
        sg_cachepress_purge_cache();
    }

    // Cloudflare (via WP Cloudflare Super Page Cache or similar)
    if ( function_exists( 'swcfpc_purge_all' ) ) {
        swcfpc_purge_all();
    }

    // WP Engine
    if ( class_exists( 'WPEngine\Cache\Purge' ) ) {
        $wpe_purge = new \WPEngine\Cache\Purge();
        if ( method_exists( $wpe_purge, 'purge_all' ) ) {
            $wpe_purge->purge_all();
        }
    }

    // Kinsta
    if ( class_exists( 'Kinsta\Cache' ) ) {
        $kinsta_cache = new \Kinsta\Cache();
        if ( method_exists( $kinsta_cache, 'purge_complete_cache' ) ) {
            $kinsta_cache->purge_complete_cache();
        }
    }

    if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
        lee_dev_debug_log_event_6158( 'cache.purge.licence_change', array(
            'option'    => $option,
            'old_value' => $old_value,
            'new_value' => $value,
        ) );
    }
}


