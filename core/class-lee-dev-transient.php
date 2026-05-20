<?php 
/**
 * Intercepts WordPress update checks and polls the Master Hub for new IntentTarget Pro versions.
 * * @param object $transient The WordPress plugin update transient object.
 * @return object Modified transient with our custom update payload if available.
 */
function lee_dev_poll_master_hub_for_updates_8812( $transient ) {
    // Bail early if WordPress isn't actually checking for updates right now
    if ( empty( $transient->checked ) ) {
        return $transient;
    }

    // Capture the exact folder and file name of the client plugin
    $plugin_slug = plugin_basename( __FILE__ ); 
    
    // Define the current version of THIS client installation
    $current_version = '1.0.0'; // Update this whenever you release a new version

    // The secure Master Hub API endpoint that returns version data
    $master_hub_url = 'https://intenttargetpro.com/wp-json/intenttarget-hub/v1/version-check';

    // Ping the Master Hub API
    $response = wp_remote_get( $master_hub_url, array(
        'timeout' => 10,
        'headers' => array( 'Accept' => 'application/json' )
    ) );

    // If the Master Hub is offline or returning an error, safely abort
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $transient;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ) );

    // Compare versions: If the Master Hub version is higher, inject the update!
    if ( $body && isset( $body->new_version ) && version_compare( $current_version, $body->new_version, '<' ) ) {
        $update = new stdClass();
        $update->slug        = $plugin_slug;
        $update->plugin      = $plugin_slug;
        $update->new_version = sanitize_text_field( $body->new_version );
        $update->url         = esc_url( $body->url );
        $update->package     = esc_url( $body->package ); // The secure .zip download link

        // Push our custom update into the native WordPress update queue
        $transient->response[ $plugin_slug ] = $update;
    }

    return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'lee_dev_poll_master_hub_for_updates_8812' );