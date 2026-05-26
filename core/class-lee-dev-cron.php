<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Background cron batch scans and hooks
 */
function lee_dev_run_background_cron_batch_scan_9201() {
    // Record execution timestamp to balance frequencies
    update_option( '_itp_last_tracked_time', time() );

    global $wpdb;
    $posts_table = $wpdb->posts;
    $postmeta_table = $wpdb->postmeta;

    $target_post_types = array( 'post', 'page' );
    if ( class_exists( 'WooCommerce' ) ) {
        $target_post_types[] = 'product';
    }
    $target_post_type_sql = "'" . implode( "','", array_map( 'esc_sql', $target_post_types ) ) . "'";

    // Resource-safe: retrieve 50 posts at a time that either have not been processed
    // OR whose tracking data is older than their modification date.
    $query = "
        SELECT p.ID FROM {$posts_table} p
        LEFT JOIN {$postmeta_table} pm ON p.ID = pm.post_id AND pm.meta_key = '_itp_last_tracked_time'
        WHERE p.post_status = 'publish'
          AND p.post_type IN ({$target_post_type_sql})
          AND (pm.meta_value IS NULL OR pm.meta_value < UNIX_TIMESTAMP(p.post_modified))
        LIMIT 50
    ";
    $post_ids = $wpdb->get_col( $query );

    if ( ! empty( $post_ids ) ) {
        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( $post && function_exists( 'lee_dev_execute_combined_content_scan_1289' ) ) {
                lee_dev_execute_combined_content_scan_1289( $post_id, $post );
                update_post_meta( $post_id, '_itp_last_tracked_time', time() );
            }
        }
    }
}
add_action( 'lee_dev_cron_batch_scan_event_9201', 'lee_dev_run_background_cron_batch_scan_9201' );

function lee_dev_normalise_content_body_4837( $raw_content ) {
    $content = is_string( $raw_content ) ? $raw_content : '';
    $content = preg_replace( '/<!--\s*\/?wp:[^>]*-->/', ' ', $content );
    $content = strip_shortcodes( $content );
    $content = wp_strip_all_tags( $content, true );
    $content = html_entity_decode( $content, ENT_QUOTES, get_bloginfo( 'charset' ) );
    $content = strtolower( $content );
    $content = str_replace( array( '&nbsp;', "\xc2\xa0", '-', '_' ), ' ', $content );
    $content = preg_replace( '/\s+/', ' ', $content );

    return trim( $content );
}

function lee_dev_build_keyword_group_matrix_6249() {
    $dictionary = get_option( 'itp_dynamic_keyword_dictionary', array() );
    if ( ! is_array( $dictionary ) || empty( $dictionary ) ) {
        return array();
    }

    $keyword_groups = array();
    foreach ( $dictionary as $group_key => $phrases ) {
        if ( ! is_array( $phrases ) ) {
            continue;
        }

        $clean_group_key = sanitize_title( $group_key );
        foreach ( $phrases as $phrase ) {
            $clean_phrase = strtolower( trim( wp_strip_all_tags( (string) $phrase ) ) );
            if ( $clean_phrase !== '' ) {
                $keyword_groups[$clean_group_key][] = $clean_phrase;
            }
        }

        if ( isset( $keyword_groups[$clean_group_key] ) ) {
            $keyword_groups[$clean_group_key] = array_values( array_unique( $keyword_groups[$clean_group_key] ) );
        }
    }

    return $keyword_groups;
}

function lee_dev_run_hourly_content_keyword_scan_7394() {
    $keyword_groups = lee_dev_build_keyword_group_matrix_6249();
    if ( empty( $keyword_groups ) ) {
        update_option( 'itp_content_scan_status_7394', array(
            'last_run'       => time(),
            'assets_scanned' => 0,
            'matches_found'  => 0,
            'message'        => 'No categorised keyphrases were available for the hourly content scan.'
        ) );
        return;
    }

    $offset = max( 0, (int) get_option( 'itp_content_scan_offset_7394', 0 ) );
    $scannable_post_types = array( 'post', 'page' );
    if ( class_exists( 'WooCommerce' ) ) {
        $scannable_post_types[] = 'product';
    }
    $query = new WP_Query( array(
        'post_type'              => $scannable_post_types,
        'post_status'            => 'publish',
        'posts_per_page'         => 50,
        'offset'                 => $offset,
        'orderby'                => 'ID',
        'order'                  => 'ASC',
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ) );

    $asset_ids = is_array( $query->posts ) ? $query->posts : array();
    if ( empty( $asset_ids ) && $offset > 0 ) {
        update_option( 'itp_content_scan_offset_7394', 0 );
        return;
    }

    $tracking_matrix = get_option( 'itp_category_asset_tracking_matrix', array() );
    if ( ! is_array( $tracking_matrix ) ) {
        $tracking_matrix = array();
    }

    $matches_found = 0;
    foreach ( $asset_ids as $asset_id ) {
        $post = get_post( $asset_id );
        if ( ! $post ) {
            continue;
        }

        $corpus = function_exists( 'lee_dev_build_asset_search_corpus_4216' )
            ? lee_dev_build_asset_search_corpus_4216( $post )
            : lee_dev_normalise_content_body_4837( $post->post_content );
        if ( $corpus === '' ) {
            continue;
        }

        $asset_matches = array();
        $asset_tracking_labels = array();
        foreach ( $keyword_groups as $group_key => $phrases ) {
            foreach ( $phrases as $phrase ) {
                if ( $phrase !== '' && stripos( $corpus, $phrase ) !== false ) {
                    $asset_matches[$group_key][] = $phrase;
                    $asset_tracking_labels[] = $phrase;
                }
            }
        }

        $asset_tracking_labels = array_values( array_unique( array_map( 'sanitize_text_field', $asset_tracking_labels ) ) );
        if ( function_exists( 'lee_dev_apply_manual_label_overrides_2937' ) ) {
            $asset_tracking_labels = lee_dev_apply_manual_label_overrides_2937( $asset_tracking_labels, $asset_id );
        }
        if ( ! empty( $asset_tracking_labels ) ) {
            update_post_meta( $asset_id, '_itp_tracking_labels', $asset_tracking_labels );
        } else {
            delete_post_meta( $asset_id, '_itp_tracking_labels' );
        }

        foreach ( $asset_matches as $group_key => $matched_phrases ) {
            $safe_group_key = sanitize_title( $group_key );
            if ( ! isset( $tracking_matrix[$safe_group_key] ) || ! is_array( $tracking_matrix[$safe_group_key] ) ) {
                $tracking_matrix[$safe_group_key] = array();
            }

            $tracking_matrix[$safe_group_key][absint( $asset_id )] = array(
                'asset_id'        => absint( $asset_id ),
                'matched_phrases' => array_values( array_unique( array_map( 'sanitize_text_field', $matched_phrases ) ) ),
                'last_seen'       => time(),
            );
            $matches_found++;
        }
    }

    update_option( 'itp_category_asset_tracking_matrix', $tracking_matrix, false );
    update_option( 'itp_content_scan_offset_7394', $offset + 50 );
    update_option( 'itp_content_scan_status_7394', array(
        'last_run'       => time(),
        'assets_scanned' => count( $asset_ids ),
        'matches_found'  => $matches_found,
        'message'        => 'Hourly content scan completed and categorised page and post assets were optimised safely.'
    ) );
}
add_action( 'itp_hourly_tracking_batch_event', 'lee_dev_run_hourly_content_keyword_scan_7394', 20 );
