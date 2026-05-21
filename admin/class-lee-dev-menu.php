<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. REGISTER THE UNIFIED DASHBOARD MENU
// =========================================================================
add_action('admin_menu', 'lee_dev_register_unified_search_dashboard_1120');
function lee_dev_register_unified_search_dashboard_1120() {
    add_menu_page(
        'User Interest Tracker',
        'Interest Tracker',
        'manage_options',
        'itp-search-dashboard',
        'lee_dev_render_unified_search_dashboard_4829',
        'dashicons-chart-area',
        56
    );
}

function lee_dev_render_unified_search_dashboard_4829() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Insufficient access privileges.');
    }
    
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
    
    // Load the dashboard view template
    $view_path = plugin_dir_path(__FILE__) . 'view-dashboard.php';
    if ( file_exists($view_path) ) {
        include $view_path;
    }
}

function lee_dev_get_valid_site_intents_6048() {
    $intents = array(
        'generating_leads'     => 'Generating Leads',
        'educating_audiences'  => 'Educating Audiences',
        'customer_support'     => 'Customer Support',
    );

    if ( class_exists( 'WooCommerce' ) ) {
        $intents['driving_sales'] = 'Driving Sales';
    }

    return $intents;
}

add_action( 'admin_init', 'lee_dev_process_global_priority_settings_6048' );
function lee_dev_process_global_priority_settings_6048() {
    if ( ! isset( $_POST['itp_save_global_priority'] ) ) {
        return;
    }

    check_admin_referer( 'itp_save_global_priority_action', 'itp_save_global_priority_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Insufficient access privileges.' );

    $valid_intents = lee_dev_get_valid_site_intents_6048();
    $submitted     = isset( $_POST['itp_global_priority'] ) && is_array( $_POST['itp_global_priority'] ) ? array_map( 'sanitize_text_field', $_POST['itp_global_priority'] ) : array();
    $priority      = array();

    foreach ( $submitted as $intent_key ) {
        if ( isset( $valid_intents[$intent_key] ) && ! in_array( $intent_key, $priority, true ) ) {
            $priority[] = $intent_key;
        }
    }

    foreach ( array_keys( $valid_intents ) as $intent_key ) {
        if ( ! in_array( $intent_key, $priority, true ) ) {
            $priority[] = $intent_key;
        }
    }

    update_option( 'itp_global_priority', $priority, false );
    if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
        lee_dev_debug_log_event_6158( 'global_priority.updated', array( 'priority' => $priority ) );
    }

    wp_safe_redirect( add_query_arg( array( 'page' => 'itp-search-dashboard', 'tab' => 'popout_adverts', 'priority-updated' => 'true' ), admin_url( 'admin.php' ) ) );
    exit;
}

// =========================================================================
// 2. ADMIN USER PROFILE INSIGHTS INJECTION
// =========================================================================
add_action('edit_user_profile', 'lee_dev_admin_display_interests_1938');
add_action('show_user_profile', 'lee_dev_admin_display_interests_1938');
function lee_dev_admin_display_interests_1938($user) {
    if (!function_exists('lee_dev_is_ready_8293') || !lee_dev_is_ready_8293()) return;

    $automated = get_user_meta($user->ID, 'user_interest_map', true) ?: [];
    $searches  = get_user_meta($user->ID, 'user_search_history', true) ?: [];
    $manual    = get_user_meta($user->ID, 'manual_interests', true) ?: [];
    $group_scores = []; 

    $labels = function_exists('lee_dev_get_active_categories_5921') ? lee_dev_get_active_categories_5921() : [];
    $master_groups = array_keys($labels);

    foreach ($master_groups as $g) {
        $group_scores[$g] = function_exists('lee_dev_calculate_group_propensity_1289') ? lee_dev_calculate_group_propensity_1289($g, $user->ID) : 0;
    }
    arsort($group_scores);
    
    if (is_array($automated)) uasort($automated, function($a, $b) { return $b['score'] <=> $a['score']; });
    ?>
    <div class="itp-intelligence-profile-wrapper">
        <hr /><h2 style="color: #2271b1;">Digital Intelligence & Sales Propensity</h2>
        <table class="form-table">
            <tr>
                <th>Sales Opportunity Rank</th>
                <td>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; max-width: 900px;">
                        <?php foreach ($group_scores as $slug => $score) : 
                            $bg = ($score > 15) ? '#edfaef' : (($score < 0) ? '#fcf0f1' : '#f6f7f7');
                            $border = ($score > 15) ? '#008a20' : (($score < 0) ? '#d63638' : '#ccd0d4');
                            $label = $labels[$slug] ?? ucfirst($slug);
                        ?>
                            <div style="padding: 12px 10px; border: 1px solid <?php echo $border; ?>; background: <?php echo $bg; ?>; border-radius: 4px; text-align: center;">
                                <small style="text-transform: uppercase; font-size: 10px; font-weight: bold; display: block; margin-bottom: 5px; color: #646970;"><?php echo esc_html($label); ?></small>
                                <span style="font-size: 1.6em; font-weight: 900; color: <?php echo ($score < 0) ? '#d63638' : '#1d2327'; ?>;"><?php echo $score; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th>User Stated Interests</th>
                <td>
                    <?php if (!empty($manual)) : ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                            <?php foreach ($manual as $item) : ?><span style="background: #e5e5e5; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php echo esc_html(ucfirst(str_replace('-', ' ', $item))); ?></span><?php endforeach; ?>
                        </div>
                    <?php else : echo '<em>None selected</em>'; endif; ?>
                </td>
            </tr>
            <tr>
                <th>Detailed Behaviour</th>
                <td>
                    <details>
                        <summary style="cursor: pointer; color: #2271b1; font-weight: 600;">Show Category Hits & Searches</summary>
                        <div style="margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <h4>Category Engagement</h4>
                                <?php if (!empty($automated)) : ?>
                                    <ul>
                                        <?php foreach ($automated as $sector => $data) : ?>
                                            <li><strong><?php echo ucfirst(str_replace('-', ' ', $sector)); ?>:</strong> <?php echo $data['score']; ?> hits</li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : echo 'No data'; endif; ?>
                            </div>
                            <div>
                                <h4>Recent Searches</h4>
                                <?php if (!empty($searches)) : ?>
                                    <ul>
                                        <?php foreach ($searches as $term => $data) : ?>
                                            <li>"<em><?php echo esc_html($term); ?></em>" (<?php echo $data['count']; ?> times)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : echo 'No history'; endif; ?>
                            </div>
                        </div>
                    </details>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

add_action('admin_footer-user-edit.php', 'lee_dev_reposition_profile_intelligence_panel_4721');
add_action('admin_footer-profile.php', 'lee_dev_reposition_profile_intelligence_panel_4721');
function lee_dev_reposition_profile_intelligence_panel_4721() {
    ?><script>jQuery(document).ready(function($) { var p = $('.itp-intelligence-profile-wrapper'); if(p.length) p.insertAfter('.wrap h1:first'); });</script><?php
}

add_action('show_user_profile', 'lee_dev_show_engagement_status_3847');
add_action('edit_user_profile', 'lee_dev_show_engagement_status_3847');
function lee_dev_show_engagement_status_3847($user) {
    $is_high_engaged = get_user_meta($user->ID, 'itp_high_engagement_flag', true);
    $last_interaction = get_user_meta($user->ID, 'itp_last_interaction_date', true);
    ?>
    <h2>Digital Intelligence Insights</h2>
    <table class="form-table">
        <tr><th>User Status</th><td>
            <?php if ($is_high_engaged === 'yes') : ?>
                <span style="background: #074e85; color: #fff; padding: 5px 12px; border-radius: 4px; font-weight: bold; font-size: 11px;">🔥 HIGH ENGAGEMENT</span>
                <p class="description">Clicked recommendation on: <strong><?php echo esc_html($last_interaction); ?></strong></p>
            <?php else : echo '<span style="background: #eee; color: #777; padding: 5px 12px; border-radius: 4px; font-weight: bold; font-size: 11px;">STANDARD LEAD</span>'; endif; ?>
        </td></tr>
    </table>
    <?php
}

// =========================================================================
// 3. CUSTOM LEAD COLUMNS FOR USER LIST
// =========================================================================
add_filter('manage_users_columns', 'lee_dev_add_lead_status_column_5829');
function lee_dev_add_lead_status_column_5829($columns) {
    $columns['itp_lead_status'] = 'Lead Status';
    return $columns;
}

add_filter('manage_users_custom_column', 'lee_dev_fill_lead_status_column_2940', 10, 3);
function lee_dev_fill_lead_status_column_2940($output, $column_name, $user_id) {
    if ($column_name !== 'itp_lead_status') return $output;
    $is_high_engaged = get_user_meta($user_id, 'itp_high_engagement_flag', true);
    $total_score = get_user_meta($user_id, 'itp_total_score', true) ?: 0;

    if ($is_high_engaged === 'yes') return '<span style="color: #fff; background: #d9534f; padding: 3px 8px; border-radius: 3px; font-weight: bold; font-size: 10px;">🔥 Hot Lead</span>';
    if ($total_score >= 100) return '<span style="color: #fff; background: #f0ad4e; padding: 3px 8px; border-radius: 3px; font-weight: bold; font-size: 10px;">Warm Lead</span>';
    return '<span style="color: #777; background: #eee; padding: 3px 8px; border-radius: 3px; font-weight: bold; font-size: 10px;">Standard</span>';
}

add_action('admin_head-users.php', 'lee_dev_style_user_table_columns_4812');
function lee_dev_style_user_table_columns_4812() {
    echo '<style>table.fixed { table-layout: auto !important; } .column-itp_lead_status { min-width: 150px !important; width: 150px; } .column-itp_lead_status span { display: inline-block; text-align: center; min-width: 100px; }</style>';
}

add_action('restrict_manage_users', 'lee_dev_add_lead_status_filter_9381');
function lee_dev_add_lead_status_filter_9381($which) {
    if ($which !== 'top') return;
    $selected = isset($_GET['itp_lead_filter']) ? $_GET['itp_lead_filter'] : '';
    ?>
    <select name="itp_lead_filter" style="float:none; margin-left:10px;">
        <option value="">All Lead Types</option>
        <option value="hot" <?php selected($selected, 'hot'); ?>>Hot Leads</option>
        <option value="warm" <?php selected($selected, 'warm'); ?>>Warm Leads</option>
        <option value="standard" <?php selected($selected, 'standard'); ?>>Standard</option>
    </select>
    <input type="submit" name="filter_action" class="button" value="Filter">
    <?php
}

add_action('pre_get_users', 'lee_dev_filter_users_by_lead_status_3819');
function lee_dev_filter_users_by_lead_status_3819($query) {
    if (!is_admin()) return;
    $filter_value = isset($_GET['itp_lead_filter']) ? $_GET['itp_lead_filter'] : '';
    if (empty($filter_value)) return;

    if ($filter_value === 'hot') {
        $query->set('meta_key', 'itp_high_engagement_flag');
        $query->set('meta_value', 'yes'); 
    } elseif ($filter_value === 'warm') {
        $query->set('meta_key', 'itp_total_score');
        $query->set('meta_value', '100');
        $query->set('meta_compare', '>=');
        $query->set('type', 'numeric');
    } elseif ($filter_value === 'standard') {
        $query->set('meta_query', array(
            'relation' => 'AND',
            array('key' => 'itp_high_engagement_flag', 'compare' => 'NOT EXISTS'),
            array(
                'relation' => 'OR',
                array('key' => 'itp_total_score', 'value' => '100', 'compare' => '<', 'type' => 'NUMERIC'),
                array('key' => 'itp_total_score', 'compare' => 'NOT EXISTS')
            )
        ));
    }
}
