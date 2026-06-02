<?php
namespace IntentTarget\Core;
if ( ! defined( 'ABSPATH' ) ) exit;
class Parser {
	public static function get_active_categories(): array {
		return apply_filters( 'lee_dev_active_categories_5921', method_exists( Intents::class, 'get_categories' ) ? Intents::get_categories() : array() );
	}
	public static function execute_combined_content_scan( int $post_id, ?\WP_Post $post ): void {
		if ( ! $post ) return;
		do_action( 'lee_dev_before_post_scan_1289', $post_id, $post );
		$current_type = ! empty( $post->post_type ) ? (string) $post->post_type : 'post';
		$dictionary = get_option( 'itp_dynamic_keyword_dictionary', array() );
		if ( empty( $dictionary ) || ! is_array( $dictionary ) ) {
			$override_only = method_exists( Intents::class, 'apply_manual_label_overrides' ) ? Intents::apply_manual_label_overrides( array(), $post_id ) : array();
			if ( ! empty( $override_only ) ) update_post_meta( $post_id, '_itp_tracking_labels', array_values( $override_only ) );
			else delete_post_meta( $post_id, '_itp_tracking_labels' );
			do_action( 'lee_dev_after_post_scan_1289', $post_id, $override_only );
			return;
		}
		$default_types = array( 'post', 'page' );
		if ( class_exists( 'WooCommerce' ) ) $default_types[] = 'product';
		$scannable = apply_filters( 'lee_dev_scannable_post_types_1289', $default_types );
		if ( ! in_array( $current_type, $scannable, true ) ) {
			$override_only = method_exists( Intents::class, 'apply_manual_label_overrides' ) ? Intents::apply_manual_label_overrides( array(), $post_id ) : array();
			if ( ! empty( $override_only ) ) update_post_meta( $post_id, '_itp_tracking_labels', array_values( $override_only ) );
			else delete_post_meta( $post_id, '_itp_tracking_labels' );
			do_action( 'lee_dev_after_post_scan_1289', $post_id, $override_only );
			return;
		}
		$target_phrases = array();
		foreach ( $dictionary as $phrases_array ) {
			if ( ! is_array( $phrases_array ) ) continue;
			foreach ( $phrases_array as $phrase ) {
				$clean = strtolower( trim( (string) $phrase ) );
				if ( $clean !== '' ) $target_phrases[] = $clean;
			}
		}
		$target_phrases = array_values( array_unique( $target_phrases ) );
		usort( $target_phrases, function( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
		$master_string = method_exists( Intents::class, 'build_asset_search_corpus' ) ? Intents::build_asset_search_corpus( $post ) : '';
		$matched_labels = array();
		if ( $master_string !== '' ) {
			foreach ( $target_phrases as $phrase ) {
				if ( $phrase === '' || strpos( $master_string, $phrase ) === false ) continue;
				$already_covered = false;
				if ( strpos( $phrase, ' ' ) === false ) {
					foreach ( $matched_labels as $matched_phrase ) {
						if ( strpos( $matched_phrase, $phrase ) !== false ) { $already_covered = true; break; }
					}
				}
				if ( ! $already_covered ) $matched_labels[] = $phrase;
			}
		}
		$matched_labels = array_values( array_unique( array_filter( $matched_labels ) ) );
		$matched_labels = apply_filters( 'lee_dev_matched_labels_1289', $matched_labels, $post_id, $post );
		$matched_labels = method_exists( Intents::class, 'apply_manual_label_overrides' ) ? Intents::apply_manual_label_overrides( $matched_labels, $post_id ) : $matched_labels;
		if ( ! empty( $matched_labels ) ) update_post_meta( $post_id, '_itp_tracking_labels', array_values( $matched_labels ) );
		else delete_post_meta( $post_id, '_itp_tracking_labels' );
		do_action( 'lee_dev_after_post_scan_1289', $post_id, $matched_labels );
	}
}
