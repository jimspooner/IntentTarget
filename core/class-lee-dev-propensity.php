<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Calculate user interest propensity score for a given group/category
 */
function lee_dev_calculate_group_propensity_1289($group_name, $user_id) {
    if (get_user_meta($user_id, 'itp_disable_tracking', true)) {
        return -9999; 
    }

    $automated_data = get_user_meta($user_id, 'user_interest_map', true) ?: [];
    $search_data    = get_user_meta($user_id, 'user_search_history', true) ?: [];
    $manual_data    = get_user_meta($user_id, 'manual_interests', true) ?: [];

    $score = 0;
    
    $dictionary = get_option('itp_dynamic_keyword_dictionary', []);
    $keywords = isset($dictionary[$group_name]) && is_array($dictionary[$group_name]) ? $dictionary[$group_name] : [];
    $keywords[] = $group_name;
    $keywords = array_values(array_unique(array_map('sanitize_title', $keywords)));
    $manual_data_clean = is_array($manual_data) ? array_map('sanitize_title', $manual_data) : [];

    // 1. Manual User Preferences weight (+50 points)
    if (is_array($manual_data)) {
        foreach ($keywords as $word) {
            if (in_array($word, $manual_data_clean, true)) {
                $score += 50;
                break; 
            }
        }
    }

    // 2. Automated Category Hits from views (+5 points per occurrence)
    if (is_array($automated_data)) {
        foreach ($automated_data as $sector_slug => $stat) {
            if (in_array(sanitize_title($sector_slug), $keywords, true)) {
                $score += ($stat['score'] * 5);
            }
        }
    }

    // 3. Automated Search Queries weight (+10 points per matched search string)
    if (is_array($search_data)) {
        foreach ($search_data as $term => $stat) {
            $clean_term = strtolower($term);
            foreach ($keywords as $word) {
                if (strpos($clean_term, $word) !== false) {
                    $score += ($stat['count'] * 10);
                }
            }
        }
    }

    $seminar_slug = apply_filters('lee_dev_seminar_slug_1289', 'seminars');
    if ($group_name === $seminar_slug) {
        $current_month = (int) date('n');
        if ($current_month >= 1 && $current_month <= 4) {
            $score = round($score * 1.5);
        }
    }

    return apply_filters('lee_dev_calculated_propensity_1289', $score, $group_name, $user_id);
}
