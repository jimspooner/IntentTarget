<?php
/**
 * Plugin Name:       IntentTarget Core
 * Plugin URI:        https://intenttargetpro.co.uk
 * Description:       Automates audience page interest tracking and dynamic keyphrase profiling via a resource-safe background cron batch execution engine.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Lee Dev
 * Author URI:        https://intenttargetpro.co.uk
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       intenttarget-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// =========================================================================
// 0. CLASS AUTOLOADER
// =========================================================================
spl_autoload_register( function ( $class_name ) {
    $prefix = 'IntentTarget\\';
    $len    = strlen( $prefix );

    if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
        return;
    }

    $relative = substr( $class_name, $len );
    $relative = ltrim( $relative, '\\' );
    $parts    = explode( '\\', $relative );
    $class    = array_pop( $parts );
    $dir      = implode( '/', $parts );

    // Flatten sub-namespace directories to match file layout.
    $dir = str_replace( 'Core/Admin', 'Admin', $dir );
    $dir = str_replace( 'Core/Telemetry', 'Telemetry', $dir );

    $file = 'class-' . strtolower( $class ) . '.php';
    $path = plugin_dir_path( __FILE__ ) . 'includes/' . ( $dir ? $dir . '/' : '' ) . $file;

    if ( file_exists( $path ) ) {
        require_once $path;
    }
} );

// =========================================================================
// 1. BOOTSTRAP THE OOP PLUGIN
// =========================================================================
$intenttarget_plugin = \IntentTarget\Core\Plugin::instance( __FILE__ );

// =========================================================================
// 2. PLUGIN ACTIVATION / DEACTIVATION
// =========================================================================
register_activation_hook( __FILE__, array( '\IntentTarget\Core\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\IntentTarget\Core\Plugin', 'deactivate' ) );

// =========================================================================
// 3. BACKWARD-COMPATIBILITY WRAPPERS (procedural => OOP)
// =========================================================================
if ( ! function_exists( 'lee_dev_has_authorised_licence_7365' ) ) {
    function lee_dev_has_authorised_licence_7365() {
        return \IntentTarget\Core\Access::has_authorised_licence();
    }
}
if ( ! function_exists( 'lee_dev_is_ready_8293' ) ) {
    function lee_dev_is_ready_8293() {
        return \IntentTarget\Core\Access::is_ready();
    }
}
if ( ! function_exists( 'lee_dev_is_addon_active_3812' ) ) {
    function lee_dev_is_addon_active_3812( $slug ) {
        return \IntentTarget\Core\Access::is_addon_active( $slug );
    }
}
if ( ! function_exists( 'lee_dev_debug_log_event_6158' ) ) {
    function lee_dev_debug_log_event_6158( $event_name, $context = array() ) {
        \IntentTarget\Core\Access::debug_log_event( $event_name, $context );
    }
}
if ( ! function_exists( 'lee_dev_get_intent_categories_3812' ) ) {
    function lee_dev_get_intent_categories_3812() {
        return \IntentTarget\Core\Intents::get_categories();
    }
}
if ( ! function_exists( 'lee_dev_get_intent_signal_lexicon_4275' ) ) {
    function lee_dev_get_intent_signal_lexicon_4275() {
        return \IntentTarget\Core\Intents::get_signal_lexicon();
    }
}
if ( ! function_exists( 'lee_dev_classify_phrase_into_intent_8264' ) ) {
    function lee_dev_classify_phrase_into_intent_8264( $phrase ) {
        return \IntentTarget\Core\Intents::classify_phrase( $phrase );
    }
}
if ( ! function_exists( 'lee_dev_get_intent_scanner_blacklist_9447' ) ) {
    function lee_dev_get_intent_scanner_blacklist_9447() {
        return \IntentTarget\Core\Intents::get_scanner_blacklist();
    }
}
if ( ! function_exists( 'lee_dev_normalise_intent_text_2754' ) ) {
    function lee_dev_normalise_intent_text_2754( $raw ) {
        return \IntentTarget\Core\Intents::normalise_text( $raw );
    }
}
if ( ! function_exists( 'lee_dev_get_max_phrase_word_count_5174' ) ) {
    function lee_dev_get_max_phrase_word_count_5174() {
        return \IntentTarget\Core\Intents::get_max_phrase_word_count();
    }
}
if ( ! function_exists( 'lee_dev_extract_candidate_phrases_from_text_7506' ) ) {
    function lee_dev_extract_candidate_phrases_from_text_7506( $raw, $blacklist = array() ) {
        return \IntentTarget\Core\Intents::extract_candidate_phrases( $raw, $blacklist );
    }
}
if ( ! function_exists( 'lee_dev_collect_asset_taxonomy_phrases_5912' ) ) {
    function lee_dev_collect_asset_taxonomy_phrases_5912( $post_id, $post_type ) {
        return \IntentTarget\Core\Intents::collect_taxonomy_phrases( $post_id, $post_type );
    }
}
if ( ! function_exists( 'lee_dev_collect_asset_public_meta_phrases_8137' ) ) {
    function lee_dev_collect_asset_public_meta_phrases_8137( $post_id ) {
        return \IntentTarget\Core\Intents::collect_public_meta_phrases( $post_id );
    }
}
if ( ! function_exists( 'lee_dev_build_asset_search_corpus_4216' ) ) {
    function lee_dev_build_asset_search_corpus_4216( $post ) {
        return \IntentTarget\Core\Intents::build_asset_search_corpus( $post );
    }
}
if ( ! function_exists( 'lee_dev_get_intent_to_site_purpose_map_5614' ) ) {
    function lee_dev_get_intent_to_site_purpose_map_5614() {
        return \IntentTarget\Core\Intents::get_intent_to_site_purpose_map();
    }
}
if ( ! function_exists( 'lee_dev_resolve_user_intent_priority_4762' ) ) {
    function lee_dev_resolve_user_intent_priority_4762( $user_id ) {
        return \IntentTarget\Core\Intents::resolve_user_intent_priority( $user_id );
    }
}
if ( ! function_exists( 'lee_dev_get_manual_label_overrides_4861' ) ) {
    function lee_dev_get_manual_label_overrides_4861( $post_id ) {
        return \IntentTarget\Core\Intents::get_manual_label_overrides( $post_id );
    }
}
if ( ! function_exists( 'lee_dev_save_manual_label_overrides_4861' ) ) {
    function lee_dev_save_manual_label_overrides_4861( $post_id, $overrides ) {
        \IntentTarget\Core\Intents::save_manual_label_overrides( $post_id, $overrides );
    }
}
if ( ! function_exists( 'lee_dev_apply_manual_label_overrides_2937' ) ) {
    function lee_dev_apply_manual_label_overrides_2937( $scanner_labels, $post_id ) {
        return \IntentTarget\Core\Intents::apply_manual_label_overrides( $scanner_labels, $post_id );
    }
}
if ( ! function_exists( 'lee_dev_bucket_candidates_into_intents_6024' ) ) {
    function lee_dev_bucket_candidates_into_intents_6024( $candidates ) {
        return \IntentTarget\Core\Intents::bucket_candidates( $candidates );
    }
}
if ( ! function_exists( 'lee_dev_sanitise_manual_label_phrase_6749' ) ) {
    function lee_dev_sanitise_manual_label_phrase_6749( $raw ) {
        return \IntentTarget\Core\Intents::sanitise_manual_label_phrase( $raw );
    }
}
if ( ! function_exists( 'lee_dev_get_active_categories_5921' ) ) {
    function lee_dev_get_active_categories_5921() {
        return \IntentTarget\Core\Parser::get_active_categories();
    }
}
if ( ! function_exists( 'lee_dev_execute_combined_content_scan_1289' ) ) {
    function lee_dev_execute_combined_content_scan_1289( $post_id, $post ) {
        \IntentTarget\Core\Parser::execute_combined_content_scan( $post_id, $post );
    }
}
if ( ! function_exists( 'lee_dev_calculate_group_propensity_1289' ) ) {
    function lee_dev_calculate_group_propensity_1289( $group_key, $user_id ) {
        return \IntentTarget\Core\Propensity::calculate_group_propensity( $group_key, $user_id );
    }
}
if ( ! function_exists( 'lee_dev_get_global_priority_order_7136' ) ) {
    function lee_dev_get_global_priority_order_7136() {
        return \IntentTarget\Core\Propensity::get_global_priority_order();
    }
}
if ( ! function_exists( 'lee_dev_get_valid_site_intents_6048' ) ) {
    function lee_dev_get_valid_site_intents_6048() {
        return \IntentTarget\Core\Propensity::get_valid_site_intents();
    }
}
if ( ! function_exists( 'lee_dev_run_background_cron_batch_scan_9201' ) ) {
    function lee_dev_run_background_cron_batch_scan_9201() {
        \IntentTarget\Core\Cron::run_batch_scan();
    }
}
if ( ! function_exists( 'lee_dev_normalise_content_body_4837' ) ) {
    function lee_dev_normalise_content_body_4837( $raw_content ) {
        return \IntentTarget\Core\Cron::normalise_content_body( $raw_content );
    }
}
if ( ! function_exists( 'lee_dev_build_keyword_group_matrix_6249' ) ) {
    function lee_dev_build_keyword_group_matrix_6249() {
        return \IntentTarget\Core\Cron::build_keyword_group_matrix();
    }
}
if ( ! function_exists( 'lee_dev_run_hourly_content_keyword_scan_7394' ) ) {
    function lee_dev_run_hourly_content_keyword_scan_7394() {
        \IntentTarget\Core\Cron::run_hourly_keyword_scan();
    }
}
if ( ! function_exists( 'lee_dev_setup_search_insights_table_9301' ) ) {
    function lee_dev_setup_search_insights_table_9301() {
        \IntentTarget\Core\Hooks::setup_search_insights_table();
    }
}
if ( ! function_exists( 'lee_dev_enqueue_custom_styles_4921' ) ) {
    function lee_dev_enqueue_custom_styles_4921() {
        \IntentTarget\Core\Hooks::enqueue_custom_styles();
    }
}
if ( ! function_exists( 'lee_dev_inject_customiser_css_overrides_5174' ) ) {
    function lee_dev_inject_customiser_css_overrides_5174() {
        \IntentTarget\Core\Hooks::inject_customiser_css_overrides();
    }
}
if ( ! function_exists( 'lee_dev_boost_interests_on_purchase_2718' ) ) {
    function lee_dev_boost_interests_on_purchase_2718( $order_id ) {
        \IntentTarget\Core\Hooks::boost_interests_on_purchase( $order_id );
    }
}
if ( ! function_exists( 'lee_dev_intercept_search_requests_3958' ) ) {
    function lee_dev_intercept_search_requests_3958() {
        \IntentTarget\Core\Hooks::intercept_search_requests();
    }
}
