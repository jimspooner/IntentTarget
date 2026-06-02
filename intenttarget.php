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

// function lee_dev_initialise_plugin_autoloader_4827( $class_name ) {
//     if ( strpos( $class_name, 'Lee_Dev_' ) === false ) {
//         return;
//     }

//     $file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
//     $base_path = plugin_dir_path( __FILE__ );
//     $directories = array(
//         'core/',
//         'admin/',
//         'telemetry/',
//     );

//     foreach ( $directories as $directory ) {
//         $file_path = $base_path . $directory . $file_name;
//         if ( file_exists( $file_path ) ) {
//             require_once $file_path;
//             return;
//         }
//     }
// }
// spl_autoload_register( 'lee_dev_initialise_plugin_autoloader_4827' );


// 1. SAFE LOAD DECOUPLED COMPONENT SYSTEM
// Define your base path once
define( 'LEE_DEV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// 1. ALWAYS LOAD
require_once LEE_DEV_PLUGIN_DIR . 'core/class-lee-dev-transient.php';
require_once LEE_DEV_PLUGIN_DIR . 'core/class-lee-dev-access.php';
require_once LEE_DEV_PLUGIN_DIR . 'core/class-lee-dev-intents.php';
require_once LEE_DEV_PLUGIN_DIR . 'core/class-lee-dev-parser.php';
require_once LEE_DEV_PLUGIN_DIR . 'core/class-lee-dev-cron.php';
require_once LEE_DEV_PLUGIN_DIR . 'core/class-lee-dev-hooks.php';
require_once LEE_DEV_PLUGIN_DIR . 'core/class-lee-dev-propensity.php';

// 2. ONLY LOAD IN ADMIN DASHBOARD
if ( is_admin() ) {
    require_once LEE_DEV_PLUGIN_DIR . 'admin/class-lee-dev-menu.php';
    require_once LEE_DEV_PLUGIN_DIR . 'admin/view-sidebar-box.php';
    require_once LEE_DEV_PLUGIN_DIR . 'admin/class-lee-dev-feedback.php';
}

// 3. ONLY LOAD DURING TRACKING/AJAX
if ( wp_doing_ajax() ) {
    require_once LEE_DEV_PLUGIN_DIR . 'telemetry/class-lee-dev-ajax.php';
}
require_once LEE_DEV_PLUGIN_DIR . 'telemetry/class-lee-dev-advertising.php';
require_once LEE_DEV_PLUGIN_DIR . 'telemetry/class-lee-dev-tracker.php';

if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
    lee_dev_debug_log_event_6158( 'bootstrap.loaded', array(
        'licence_status' => get_option( 'lee_dev_licence_status', 'unauthorised' ),
        'admin_area'     => is_admin() ? 'yes' : 'no',
    ) );
}

if ( ! defined( 'LEE_DEV_PLUGIN_URL' ) ) {
    define( 'LEE_DEV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'LEE_DEV_FRONTEND_UI_VERSION' ) ) {
    // Bump when assets/js/itp-frontend-ui.js changes to invalidate browser caches.
    define( 'LEE_DEV_FRONTEND_UI_VERSION', '1.0.0' );
}

// =========================================================================
// CACHE-SAFE FRONT-END RUNTIME ENQUEUE
// =========================================================================
// Loads assets/js/itp-frontend-ui.js which hydrates every per-visitor surface (slide-in popup,
// My Account dashboard advert, search-feedback form, preferences nonce, Pro ROI click tracker)
// from the bootstrap REST endpoint at runtime. The inline localised payload contains only the
// REST URL and the admin-ajax URL — both site-wide constants and therefore cache-safe.
add_action( 'wp_enqueue_scripts', 'lee_dev_enqueue_frontend_ui_runtime_4920' );
function lee_dev_enqueue_frontend_ui_runtime_4920() {
    if ( ! function_exists( 'lee_dev_has_authorised_licence_7365' ) || ! lee_dev_has_authorised_licence_7365() ) {
        return;
    }

    $handle = 'itp-frontend-ui';
    wp_register_script(
        $handle,
        LEE_DEV_PLUGIN_URL . 'assets/js/itp-frontend-ui.js',
        array(),
        LEE_DEV_FRONTEND_UI_VERSION,
        true
    );
    wp_localize_script( $handle, 'itpFrontendBootstrap', array(
        'restUrl' => esc_url_raw( rest_url( 'intenttarget/v1/bootstrap' ) ),
        'ajaxUrl' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
        'version' => LEE_DEV_FRONTEND_UI_VERSION,
    ) );
    wp_enqueue_script( $handle );
}

// Ask third-party optimisers to leave the runtime script tag alone. The most common offenders
// (Cloudflare Rocket Loader, WP Rocket Combine/Delay JS, Autoptimize, LiteSpeed Cache JS Combine)
// all respect at least one of these attributes.
add_filter( 'script_loader_tag', 'lee_dev_protect_frontend_ui_runtime_tag_4920', 10, 3 );
function lee_dev_protect_frontend_ui_runtime_tag_4920( $tag, $handle, $src ) {
    if ( $handle !== 'itp-frontend-ui' ) {
        return $tag;
    }
    if ( strpos( $tag, 'data-no-optimize' ) !== false ) {
        return $tag;
    }
    return str_replace(
        '<script ',
        '<script data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-cfasync="false" ',
        $tag
    );
}

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
