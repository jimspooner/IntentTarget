<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the Sidebar Meta Box View Container
 */
function lee_dev_register_automated_meta_sidebar_4812() {
    $screens = get_post_types( array( 'public' => true ) );
    foreach ($screens as $screens_key) {
        add_meta_box(
            'cit_automated_tracking_meta',
            'Page Interest Tracker',
            'lee_dev_render_automated_sidebar_content_9381',
            $screens_key,
            'side',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'lee_dev_register_automated_meta_sidebar_4812');

/**
 * Render the Found Keyphrase Badges inside the Sidebar Meta Box
 */
function lee_dev_render_automated_sidebar_content_9381($post) {
    $saved_keywords = get_post_meta($post->ID, '_cit_tracking_labels', true);

    echo '<div style="padding: 2px 0;">';
    echo '<p class="description" style="margin-top:0; margin-bottom:12px; line-height:1.4;">';
    echo 'The background parser scans your tracking arrays, matching strings directly against your active phrase dictionary.';
    echo '</p>';

    if ( ! empty($saved_keywords) && is_array($saved_keywords) ) {
        echo '<p style="font-weight:600; font-size:12px; margin-bottom:8px; color:#1d2327;">🎯 Actively Tracked Targets:</p>';
        echo '<div style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 180px; overflow-y: auto; padding: 2px;">';
        
        foreach ($saved_keywords as $phrase) {
            $readable_label = ucwords($phrase);
            echo '<span style="background: #edf8ff; color: #074e85; border: 1px solid #b4dcff; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block;">';
            echo esc_html($readable_label);
            echo '</span>';
        }
        
        echo '</div>';
    } else {
        echo '<div style="background: #f6f7f7; border-left: 4px solid #ccd0d4; padding: 12px; border-radius: 0 4px 4px 0;">';
        echo '<p style="margin: 0; font-size: 12px; font-style: italic; color: #646970;">No tracking targets matched yet. Update or save this page to execute the scanner.</p>';
        echo '</div>';
    }
    echo '</div>';
}

/**
 * Core Save Hook Interceptor
 */
function lee_dev_automatically_parse_page_keywords_3819($post_id, $post) {
    if ( ! $post ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) ) return;
    
    $current_type = ! empty($post->post_type) ? $post->post_type : 'post';
    if ( $current_type === 'product' ) {
        if ( ! current_user_can('edit_product', $post_id) ) return;
    } else {
        if ( ! current_user_can('edit_post', $post_id) ) return;
    }

    if ( function_exists('lee_dev_execute_combined_content_scan_1289') ) {
        lee_dev_execute_combined_content_scan_1289($post_id, $post);
    }
}
add_action('save_post', 'lee_dev_automatically_parse_page_keywords_3819', 10, 2);
