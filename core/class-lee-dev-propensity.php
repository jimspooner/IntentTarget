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
    if ( is_array( $search_data ) ) {
        foreach ( $search_data as $term => $stat ) {
            $clean_term = strtolower( $term );
            foreach ( $keywords as $word ) {
                if ( strpos( $clean_term, $word ) !== false ) {
                    
                    // Calculate days since the search occurred
                    $days_old = ( time() - $stat['last_seen'] ) / DAY_IN_SECONDS;
                    
                    // Apply multiplier: 1.0 if today, degrading to 0.1 if very old
                    $decay_multiplier = max( 0.1, 1 - ( $days_old * 0.05 ) ); 
                    
                    $score += ( $stat['count'] * 10 * $decay_multiplier );
                }
            }
        }
    }

    // 4. Active WooCommerce Cart Items (+30 points)
    if ( class_exists( 'WooCommerce' ) && ! is_admin() && WC()->cart ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product_id = $cart_item['product_id'];
            // Fetch the tracking labels assigned to this product by the crawler
            $product_labels = get_post_meta( $product_id, '_cit_tracking_labels', true );
            
            if ( is_array( $product_labels ) ) {
                foreach ( $keywords as $word ) {
                    if ( in_array( $word, $product_labels, true ) ) {
                        $score += 30; 
                    }
                }
            }
        }
    }

    // 5. Slide-Out/UI Interactions (+20 points)
    if ( is_array( $interaction_data ) ) {
        foreach ( $interaction_data as $action_type => $stat ) {
            // Example: User clicked a 'Corporate Solutions' link inside a slide-out
            if ( in_array( $stat['intent_category'], $keywords, true ) ) {
                $score += 20;
            }
        }
    }

    // 6. Active Campaign/UTM Source (+15 points)
    // Assuming you captured $_GET['utm_campaign'] into user meta on landing
    $utm_campaign = get_user_meta( $user_id, '_cit_last_utm_campaign', true );
    
    if ( ! empty( $utm_campaign ) ) {
        $clean_utm = strtolower( $utm_campaign );
        foreach ( $keywords as $word ) {
            if ( strpos( $clean_utm, $word ) !== false ) {
                $score += 15;
                break;
            }
        }
    }

    return apply_filters('lee_dev_calculated_propensity_1289', $score, $group_name, $user_id);
}
