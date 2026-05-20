<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. SEARCH FEEDBACK AJAX LOGGING INTERCEPTOR
// =========================================================================
add_action('wp_ajax_cit_submit_search_feedback', 'lee_dev_handle_search_feedback_ajax_8271');
function lee_dev_handle_search_feedback_ajax_8271() {
    check_ajax_referer('cit_ajax_nonce', 'security');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cit_search_feedback';
    $user_id = get_current_user_id();
    $search_query = sanitize_text_field($_POST['search_query'] ?? '');
    $found_result = sanitize_text_field($_POST['found_result'] ?? '');
    $feedback_notes = sanitize_textarea_field($_POST['feedback_notes'] ?? '');

    if ( empty($search_query) ) {
        wp_send_json_error('Missing search query data.');
    }

    $existing_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d AND search_query = %s AND found_result = 'implicit' ORDER BY submitted_at DESC LIMIT 1",
        $user_id,
        $search_query
    ));

    if ( $existing_id ) {
        $wpdb->update(
            $table_name,
            array(
                'found_result'   => $found_result,
                'feedback_notes' => $feedback_notes
            ),
            array('id' => $existing_id),
            array('%s', '%s'),
            array('id' => $existing_id)
        );
    } else {
        $wpdb->insert(
            $table_name,
            array(
                'user_id'        => $user_id,
                'search_query'   => $search_query,
                'found_result'   => $found_result,
                'feedback_notes' => $feedback_notes,
                'submitted_at'   => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }

    wp_send_json_success('Feedback successfully saved.');
}

// =========================================================================
// 2. HIGH ENGAGEMENT AJAX CALLBACK REGISTER
// =========================================================================
add_action('wp_ajax_cit_mark_high_engagement', 'lee_dev_mark_high_engagement_ajax_9421');
add_action('wp_ajax_nopriv_cit_mark_high_engagement', 'lee_dev_mark_high_engagement_ajax_9421');
function lee_dev_mark_high_engagement_ajax_9421() {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
    if ( $user_id > 0 ) {
        update_user_meta($user_id, 'cit_high_engagement_flag', 'yes');
        update_user_meta($user_id, 'cit_last_interaction_date', current_time('mysql'));
        wp_send_json_success();
    }
    wp_send_json_error();
}
