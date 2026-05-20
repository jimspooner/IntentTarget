<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. DATABASE SCHEMA SETUP ROUTINE
// =========================================================================
function lee_dev_setup_search_insights_table_9301() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cit_search_feedback';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        search_query varchar(255) NOT NULL,
        found_result varchar(50) NOT NULL,
        feedback_notes text NOT NULL,
        submitted_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// =========================================================================
// 2. ENQUEUE STYLES FOR SHORTCODE
// =========================================================================
add_action('wp_enqueue_scripts', 'lee_dev_enqueue_custom_styles_4921');
function lee_dev_enqueue_custom_styles_4921() {
    if (!function_exists('lee_dev_is_ready_8293') || !lee_dev_is_ready_8293()) return;

    $should_load = false;
    global $post;

    if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'cit_user_preferences' ) || has_shortcode( $post->post_content, 'cit_preferences_dashboard' ) ) ) {
        $should_load = true;
    }

    if ( function_exists( 'is_account_page' ) && is_account_page() ) {
        $should_load = true;
    }

    if ( $should_load ) {
        wp_enqueue_style(
            'cit-custom-interests', 
            plugin_dir_url(dirname(__FILE__)) . 'custom-interests.css', 
            array(), 
            '2.0.0'
        );
    }
}

// =========================================================================
// 3. INJECT DYNAMIC PALETTE CSS VARIABLES INTO THE FRONTEND HEAD
// =========================================================================
add_action('wp_head', 'lee_dev_inject_customiser_css_overrides_5174', 100);
function lee_dev_inject_customiser_css_overrides_5174() {
    $colors = get_option('cit_design_settings', []);
    
    $c_heading     = esc_attr($colors['heading'] ?? '#074e85');
    $c_recommended = esc_attr($colors['recommended'] ?? '#e1ad01');
    $c_button      = esc_attr($colors['button'] ?? '#074e85');
    $c_btn_text    = esc_attr($colors['button_text'] ?? '#ffffff');
    ?>
    <style id="cit-design-customiser-overrides">
        /* Target headers, popups, and recommendation blocks */
        #intent-slidein h4, 
        .cit-dashboard-offer h3,
        .uk-text-spot1 {
            color: <?php echo $c_heading; ?> !important;
        }

        /* Target tags and recommended badges */
        #intent-slidein .uk-label,
        .cit-dashboard-offer .uk-label,
        .cit-preferences-wrap .uk-label {
            background-color: <?php echo $c_recommended; ?> !important;
            color: #ffffff !important;
        }

        /* Target custom interaction buttons natively */
        .uk-button-spot1,
        #intent-slidein .uk-button-spot1,
        .cit-dashboard-offer .uk-button-spot1,
        .cit-preferences-wrap .uk-button-spot1 {
            background-color: <?php echo $c_button; ?> !important;
            color: <?php echo $c_btn_text; ?> !important;
            border: none !important;
        }
        
        .uk-button-spot1:hover {
            opacity: 0.9;
            color: <?php echo $c_btn_text; ?> !important;
        }
    </style>
    <?php
}

// =========================================================================
// 4. WOOCOMMERCE FALLBACK (Optional Boost on Purchase)
// =========================================================================
add_action('woocommerce_order_status_completed', 'lee_dev_boost_interests_on_purchase_2718', 10, 1);
function lee_dev_boost_interests_on_purchase_2718($order_id) {
    if ( ! class_exists( 'WooCommerce' ) ) return; 

    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    if (!$user_id) return;
    $user_data = get_userdata($user_id);
    if (!in_array($user_data->user_email, CIT_ALLOWED_EMAILS, true)) return;

    $interest_map = get_user_meta($user_id, 'user_interest_map', true) ?: [];

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $terms = get_the_terms($product_id, 'product_cat');

        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $slug = $term->slug;
                if (isset($interest_map[$slug]) && is_array($interest_map[$slug])) {
                    $interest_map[$slug]['score'] += 10;
                    $interest_map[$slug]['last_seen'] = time();
                } else {
                    $interest_map[$slug] = ['score' => 10, 'last_seen' => time()];
                }
            }
        }
    }

    $expiry_limit = strtotime('-6 months');
    foreach ($interest_map as $key => $data) {
        if (isset($data['last_seen']) && $data['last_seen'] < $expiry_limit) unset($interest_map[$key]);
    }

    uasort($interest_map, function($a, $b) { return $b['score'] <=> $a['score']; });
    update_user_meta($user_id, 'user_interest_map', array_slice($interest_map, 0, 15, true));
}

// =========================================================================
// 5. AUTOMATIC BACKGROUND SEARCH CAPTURE ENGINE
// =========================================================================
add_action('template_redirect', 'lee_dev_intercept_search_requests_3958');
function lee_dev_intercept_search_requests_3958() {
    if ( ! function_exists('lee_dev_is_ready_8293') || ! lee_dev_is_ready_8293() ) {
        return;
    }

    global $wp_query;

    if ( is_search() || isset($wp_query->query_vars['s']) ) {
        $search_query = get_search_query();
        if ( empty($search_query) && isset($wp_query->query_vars['s']) ) {
            $search_query = $wp_query->query_vars['s'];
        }

        if ( ! empty($search_query) ) {
            $user_id = get_current_user_id();
            if ( ! $user_id ) {
                return;
            }

            $term = strtolower(sanitize_text_field($search_query));
            $now = time();
            $expiry_limit = strtotime('-6 months');

            $user_history = get_user_meta($user_id, 'user_search_history', true) ?: array();
            if ( ! is_array($user_history) ) { 
                $user_history = array(); 
            }

            if ( isset($user_history[$term]) ) {
                $user_history[$term]['count']++;
                $user_history[$term]['last_searched'] = $now;
            } else {
                $user_history[$term] = array('count' => 1, 'last_searched' => $now);
            }

            foreach ( $user_history as $query => $data ) {
                if ( isset($data['last_searched']) && $data['last_searched'] < $expiry_limit ) {
                    unset($user_history[$query]);
                }
            }
            uasort($user_history, function($a, $b) { return $b['count'] <=> $a['count']; });
            update_user_meta($user_id, 'user_search_history', array_slice($user_history, 0, 20, true));

            global $wpdb;
            $table_name = $wpdb->prefix . 'cit_search_feedback';
            
            $time_buffer = date('Y-m-d H:i:s', strtotime('-5 seconds'));
            $duplicate_check = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND search_query = %s AND submitted_at >= %s",
                $user_id,
                $search_query,
                $time_buffer
            ));

            if ( (int) $duplicate_check === 0 ) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'user_id'        => $user_id,
                        'search_query'   => $search_query,
                        'found_result'   => 'implicit',
                        'feedback_notes' => '',
                        'submitted_at'   => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s', '%s')
                );
            }
        }
    }
}
