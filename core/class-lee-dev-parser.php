<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Return the active intent categories used by the propensity engine.
 *
 * Historically this returned dynamic WP/WooCommerce taxonomy terms. The plugin
 * now operates on the five hardcoded intent buckets defined in
 * lee_dev_get_intent_categories_3812(); this wrapper is preserved so that
 * existing callers in admin and telemetry layers continue to function without
 * modification.
 */
function lee_dev_get_active_categories_5921() {
    if ( function_exists( 'lee_dev_get_intent_categories_3812' ) ) {
        $categories = lee_dev_get_intent_categories_3812();
    } else {
        $categories = array();
    }

    return apply_filters( 'lee_dev_active_categories_5921', $categories );
}

/**
 * Master Text Sweeper Engine (3-Pronged Intent Corpus)
 *
 * The corpus is built per post type:
 *   - product : title + Woo taxonomies + content + excerpt + short description
 *   - post    : title + categories + tags
 *   - page    : title + content + public meta (incl. ACF fields)
 *
 * Discovered phrases that already exist in the dictionary are written to
 * _itp_tracking_labels for the propensity engine.
 */
function lee_dev_execute_combined_content_scan_1289( $post_id, $post ) {
    if ( ! $post ) return;

    do_action( 'lee_dev_before_post_scan_1289', $post_id, $post );

    $current_type = ! empty( $post->post_type ) ? (string) $post->post_type : 'post';
    $apply_overrides = function( $matches ) use ( $post_id ) {
        return function_exists( 'lee_dev_apply_manual_label_overrides_2937' )
            ? lee_dev_apply_manual_label_overrides_2937( $matches, $post_id )
            : (array) $matches;
    };

    $dictionary = get_option( 'itp_dynamic_keyword_dictionary', array() );
    if ( empty( $dictionary ) || ! is_array( $dictionary ) ) {
        $override_only = $apply_overrides( array() );
        if ( ! empty( $override_only ) ) {
            update_post_meta( $post_id, '_itp_tracking_labels', array_values( $override_only ) );
        } else {
            delete_post_meta( $post_id, '_itp_tracking_labels' );
        }
        do_action( 'lee_dev_after_post_scan_1289', $post_id, $override_only );
        return;
    }

    $default_scannable_post_types = array( 'post', 'page' );
    if ( class_exists( 'WooCommerce' ) ) {
        $default_scannable_post_types[] = 'product';
    }
    $scannable_post_types = apply_filters( 'lee_dev_scannable_post_types_1289', $default_scannable_post_types );
    if ( ! in_array( $current_type, $scannable_post_types, true ) ) {
        $override_only = $apply_overrides( array() );
        if ( ! empty( $override_only ) ) {
            update_post_meta( $post_id, '_itp_tracking_labels', array_values( $override_only ) );
        } else {
            delete_post_meta( $post_id, '_itp_tracking_labels' );
        }
        do_action( 'lee_dev_after_post_scan_1289', $post_id, $override_only );
        return;
    }

    $target_phrases = array();
    foreach ( $dictionary as $phrases_array ) {
        if ( ! is_array( $phrases_array ) ) {
            continue;
        }
        foreach ( $phrases_array as $phrase ) {
            $clean_phrase = strtolower( trim( (string) $phrase ) );
            if ( $clean_phrase !== '' ) {
                $target_phrases[] = $clean_phrase;
            }
        }
    }
    $target_phrases = array_values( array_unique( $target_phrases ) );
    usort( $target_phrases, function( $a, $b ) {
        return strlen( $b ) - strlen( $a );
    } );

    $master_string = function_exists( 'lee_dev_build_asset_search_corpus_4216' )
        ? lee_dev_build_asset_search_corpus_4216( $post )
        : '';

    $matched_labels = array();
    if ( $master_string !== '' ) {
        foreach ( $target_phrases as $phrase ) {
            if ( $phrase === '' ) {
                continue;
            }
            if ( strpos( $master_string, $phrase ) === false ) {
                continue;
            }
            $already_covered = false;
            if ( strpos( $phrase, ' ' ) === false ) {
                foreach ( $matched_labels as $matched_phrase ) {
                    if ( strpos( $matched_phrase, $phrase ) !== false ) {
                        $already_covered = true;
                        break;
                    }
                }
            }
            if ( ! $already_covered ) {
                $matched_labels[] = $phrase;
            }
        }
    }

    $matched_labels = array_values( array_unique( array_filter( $matched_labels ) ) );
    $matched_labels = apply_filters( 'lee_dev_matched_labels_1289', $matched_labels, $post_id, $post );

    $matched_labels = $apply_overrides( $matched_labels );

    if ( ! empty( $matched_labels ) ) {
        update_post_meta( $post_id, '_itp_tracking_labels', array_values( $matched_labels ) );
    } else {
        delete_post_meta( $post_id, '_itp_tracking_labels' );
    }

    do_action( 'lee_dev_after_post_scan_1289', $post_id, $matched_labels );
}
