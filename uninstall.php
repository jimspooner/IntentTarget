<?php
// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'deactivate_plugins' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// 1. Deactivate Pro if it is currently active.
$pro_plugin_path = 'IntentTarget-Pro/intenttarget-pro.php';
if ( is_plugin_active( $pro_plugin_path ) ) {
    deactivate_plugins( $pro_plugin_path );
}

// 2. Ping Master Hub to release the Core licence.
$core_key = get_option( 'lee_dev_licence_key', '' );
if ( ! empty( $core_key ) ) {
    wp_remote_post( 'https://intenttargetpro.com/wp-json/intenttarget/v1/release', array(
        'timeout'  => 10,
        'blocking' => false,
        'body'     => array( 'licence_code' => $core_key ),
    ) );
}

// 3. Check if IntentTarget Pro is still installed (file exists).
$pro_installed = file_exists( WP_PLUGIN_DIR . '/' . $pro_plugin_path );

// 4. If Pro is NOT installed, we are safe to delete all data.
if ( ! $pro_installed ) {
    global $wpdb;

    // A. Delete custom tables
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}itp_search_feedback" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}itp_pro_advert_attributions" );

    // B. Deactivate licences locally but retain the keys
    update_option( 'lee_dev_licence_status', 'unauthorised' );
    update_option( 'lee_dev_pro_licence_status', 'unauthorised' );

    // C. Delete all options EXCEPT the licence keys and statuses (wildcard approach to catch everything)
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE (option_name LIKE 'itp\_%' OR option_name LIKE 'lee\_dev\_%' OR option_name LIKE '\_itp\_%' OR option_name LIKE '\_lee\_dev\_%') AND option_name NOT IN ('lee_dev_licence_key', 'lee_dev_pro_licence_key', 'lee_dev_licence_status', 'lee_dev_pro_licence_status')" );

    // D. Delete all post meta
    $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_itp_tracking_labels', '_itp_target_keyword', '_itp_last_tracked_time', '_itp_pro_ai_faqs', '_itp_pro_ai_faq_stats', '_itp_manual_label_overrides')" );

    // E. Delete all user meta
    $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('itp_high_engagement_flag', 'itp_last_interaction_date', 'user_interest_map', 'itp_disable_tracking', 'manual_interests', 'user_search_history')" );
}
