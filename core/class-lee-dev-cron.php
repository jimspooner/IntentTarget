<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Background cron batch scans and hooks
 */
function lee_dev_run_background_cron_batch_scan_9201() {
    // Record execution timestamp to balance frequencies
    update_option( '_cit_last_tracked_time', time() );

    global $wpdb;
    $posts_table = $wpdb->posts;
    $postmeta_table = $wpdb->postmeta;

    // Resource-safe: retrieve 50 posts at a time that either have not been processed
    // OR whose tracking data is older than their modification date.
    $query = "
        SELECT p.ID FROM {$posts_table} p
        LEFT JOIN {$postmeta_table} pm ON p.ID = pm.post_id AND pm.meta_key = '_cit_last_tracked_time'
        WHERE p.post_status = 'publish'
          AND p.post_type IN ('post', 'page', 'product')
          AND (pm.meta_value IS NULL OR pm.meta_value < UNIX_TIMESTAMP(p.post_modified))
        LIMIT 50
    ";
    $post_ids = $wpdb->get_col( $query );

    if ( ! empty( $post_ids ) ) {
        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( $post && function_exists( 'lee_dev_execute_combined_content_scan_1289' ) ) {
                lee_dev_execute_combined_content_scan_1289( $post_id, $post );
                update_post_meta( $post_id, '_cit_last_tracked_time', time() );
            }
        }
    }
}
add_action( 'lee_dev_cron_batch_scan_event_9201', 'lee_dev_run_background_cron_batch_scan_9201' );
