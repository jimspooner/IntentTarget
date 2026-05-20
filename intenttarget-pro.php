<?php
/**
 * Plugin Name:       IntentTarget Pro
 * Plugin URI:        https://intenttargetpro.co.uk
 * Description:       Automates audience page interest tracking and dynamic keyphrase profiling via a resource-safe background cron batch execution engine.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Jim / Lee Dev
 * Author URI:        https://lee-dev.co.uk
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       intenttarget-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// 1. SAFE LOAD DECOUPLED COMPONENT SYSTEM
require_once plugin_dir_path( __FILE__ ) . 'core/class-lee-dev-transient.php';
require_once plugin_dir_path( __FILE__ ) . 'core/class-lee-dev-access.php';
require_once plugin_dir_path( __FILE__ ) . 'core/class-lee-dev-parser.php';
require_once plugin_dir_path( __FILE__ ) . 'core/class-lee-dev-cron.php';
require_once plugin_dir_path( __FILE__ ) . 'core/class-lee-dev-hooks.php';
require_once plugin_dir_path( __FILE__ ) . 'core/class-lee-dev-propensity.php';

require_once plugin_dir_path( __FILE__ ) . 'admin/class-lee-dev-menu.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/view-sidebar-box.php';

require_once plugin_dir_path( __FILE__ ) . 'telemetry/class-lee-dev-advertising.php';
require_once plugin_dir_path( __FILE__ ) . 'telemetry/class-lee-dev-ajax.php';
require_once plugin_dir_path( __FILE__ ) . 'telemetry/class-lee-dev-tracker.php';

// =========================================================================
// 2. SHORTCODES REGISTER BOOTSTRAP
// =========================================================================
add_shortcode('itp_preferences_dashboard', 'lee_dev_preferences_shortcode_6382');
add_shortcode('itp_user_preferences', 'lee_dev_preferences_shortcode_6382');

// =========================================================================
// 3. WOOCOMMERCE MY INTERESTS TAB INTEGRATION
// =========================================================================
add_action( 'plugins_loaded', 'lee_dev_init_wc_integration_1032' );
function lee_dev_init_wc_integration_1032() {
    if ( class_exists( 'WooCommerce' ) ) {
        add_filter('woocommerce_account_menu_items', 'lee_dev_add_wc_interests_menu_item_6472');
        add_action('init', 'lee_dev_add_wc_interests_endpoint_7584');
        add_action('woocommerce_account_interests_endpoint', 'lee_dev_render_wc_interests_tab_2947');
    }
}

if ( ! function_exists( 'lee_dev_add_wc_interests_menu_item_6472' ) ) {
    function lee_dev_add_wc_interests_menu_item_6472($items) {
        if (!function_exists('lee_dev_is_ready_8293') || !lee_dev_is_ready_8293()) return $items;
        
        $new_items = array();
        foreach ($items as $key => $item) {
            if ($key === 'customer-logout') {
                $new_items['interests'] = __('My Interests', 'intenttarget-pro');
            }
            $new_items[$key] = $item;
        }
        return $new_items;
    }
}

if ( ! function_exists( 'lee_dev_add_wc_interests_endpoint_7584' ) ) {
    function lee_dev_add_wc_interests_endpoint_7584() {
        add_rewrite_endpoint('interests', EP_PAGES);
    }
}

if ( ! function_exists( 'lee_dev_render_wc_interests_tab_2947' ) ) {
    function lee_dev_render_wc_interests_tab_2947() {
        if ( shortcode_exists( 'itp_preferences_dashboard' ) ) {
            echo do_shortcode('[itp_preferences_dashboard]');
        } elseif ( shortcode_exists( 'itp_user_preferences' ) ) {
            echo do_shortcode('[itp_user_preferences]');
        }
    }
}

// =========================================================================
// 4. PLUGIN ACTIVATION AND DEACTIVATION HANDLERS
// =========================================================================
register_activation_hook(__FILE__, 'lee_dev_activate_plugin_9481');
function lee_dev_activate_plugin_9481() {
    if ( function_exists('lee_dev_setup_search_insights_table_9301') ) {
        lee_dev_setup_search_insights_table_9301();
    }
    
    // Schedule background cron batch job hourly
    if ( ! wp_next_scheduled( 'lee_dev_cron_batch_scan_event_9201' ) ) {
        wp_schedule_event( time(), 'hourly', 'lee_dev_cron_batch_scan_event_9201' );
    }

    // Schedule background telemetry job hourly
    if ( ! wp_next_scheduled( 'itp_hourly_tracking_batch_event' ) ) {
        wp_schedule_event( time(), 'hourly', 'itp_hourly_tracking_batch_event' );
    }
}

register_deactivation_hook(__FILE__, 'lee_dev_deactivate_plugin_3821');
function lee_dev_deactivate_plugin_3821() {
    wp_clear_scheduled_hook( 'lee_dev_cron_batch_scan_event_9201' );
    wp_clear_scheduled_hook( 'itp_hourly_tracking_batch_event' );
}
