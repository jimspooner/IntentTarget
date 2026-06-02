<?php
/**
 * Plugin Name:       IntentTarget Pro
 * Plugin URI:        https://intenttargetpro.co.uk
 * Description:       pro Pack
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Lee Dev
 * Author URI:        https://intenttargetpro.co.uk
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       intenttarget-pro
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =========================================================================
// 0. PRO CLASS AUTOLOADER
// =========================================================================
spl_autoload_register( function ( $class_name ) {
    $prefix = 'IntentTarget\\Pro\\';
    $len    = strlen( $prefix );
    if ( strncmp( $prefix, $class_name, $len ) !== 0 ) return;
    $relative = substr( $class_name, $len );
    $relative = ltrim( $relative, '\\' );
    $parts    = explode( '\\', $relative );
    $class    = array_pop( $parts );
    $dir      = implode( '/', $parts );
    $file     = 'class-' . strtolower( $class ) . '.php';
    $path     = plugin_dir_path( __FILE__ ) . 'includes/Pro/' . ( $dir ? $dir . '/' : '' ) . $file;
    if ( file_exists( $path ) ) require_once $path;
} );

// =========================================================================
// 0a. LOAD PRO MODULES (legacy includes — still loaded for ROI, Access Control, AI FAQ)
// =========================================================================
require_once plugin_dir_path( __FILE__ ) . 'includes/class-lee-dev-pro-roi-tracking.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-lee-dev-pro-access-control.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-lee-dev-pro-ai-faq.php';

// =========================================================================
// 1. BOOTSTRAP THE OOP PRO PLUGIN
// =========================================================================
$intenttarget_pro_plugin = \IntentTarget\Pro\Plugin::instance();

// =========================================================================
// 2. ACTIVATION / DEACTIVATION
// =========================================================================
register_activation_hook( __FILE__, array( '\IntentTarget\Pro\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\IntentTarget\Pro\Plugin', 'deactivate' ) );

// =========================================================================
// 3. BACKWARD-COMPATIBILITY WRAPPERS (procedural => OOP)
// =========================================================================
if ( ! function_exists( 'lee_dev_pro_has_authorised_licence_2576' ) ) {
    function lee_dev_pro_has_authorised_licence_2576() {
        return \IntentTarget\Pro\Licence::has_authorised();
    }
}
if ( ! function_exists( 'lee_dev_pro_check_licence_status_9304' ) ) {
    function lee_dev_pro_check_licence_status_9304() {
        return \IntentTarget\Pro\Licence::check_status();
    }
}
if ( ! function_exists( 'lee_dev_pro_apply_design_overrides_7263' ) ) {
    function lee_dev_pro_apply_design_overrides_7263( $default_colors ) {
        return \IntentTarget\Pro\Styling::apply_design_overrides( $default_colors );
    }
}
if ( ! function_exists( 'lee_dev_pro_detect_theme_palette_3145' ) ) {
    function lee_dev_pro_detect_theme_palette_3145() {
        return \IntentTarget\Pro\Styling::detect_theme_palette();
    }
}
if ( ! function_exists( 'lee_dev_pro_inject_design_tab_8826' ) ) {
    function lee_dev_pro_inject_design_tab_8826( $active_tab ) {
        \IntentTarget\Pro\Plugin::instance()->inject_design_tab( $active_tab );
    }
}
if ( ! function_exists( 'lee_dev_pro_render_design_tab_5097' ) ) {
    function lee_dev_pro_render_design_tab_5097() {
        \IntentTarget\Pro\Plugin::instance()->render_design_tab();
    }
}
if ( ! function_exists( 'lee_dev_pro_auto_populate_theme_colours_6821' ) ) {
    function lee_dev_pro_auto_populate_theme_colours_6821() {
        \IntentTarget\Pro\Styling::auto_populate_theme_colours();
    }
}
if ( ! function_exists( 'lee_dev_pro_render_licence_panel_4193' ) ) {
    function lee_dev_pro_render_licence_panel_4193() {
        \IntentTarget\Pro\Licence::render_licence_panel();
    }
}
if ( ! function_exists( 'lee_dev_pro_process_licence_activation_8167' ) ) {
    function lee_dev_pro_process_licence_activation_8167() {
        \IntentTarget\Pro\Licence::process_activation();
    }
}
if ( ! function_exists( 'lee_dev_pro_redirect_to_design_tab_3187' ) ) {
    function lee_dev_pro_redirect_to_design_tab_3187( $status, $error_message = '' ) {
        \IntentTarget\Pro\Licence::redirect( $status, $error_message );
    }
}
if ( ! function_exists( 'lee_dev_pro_schedule_daily_licence_check_3492' ) ) {
    function lee_dev_pro_schedule_daily_licence_check_3492() {
        \IntentTarget\Pro\Licence::schedule_daily_check();
    }
}
if ( ! function_exists( 'lee_dev_pro_verify_licence_remotely_5708' ) ) {
    function lee_dev_pro_verify_licence_remotely_5708() {
        \IntentTarget\Pro\Licence::verify_remotely();
    }
}

// =========================================================================
// 4. HOOK REGISTRATION (delegated to OOP classes)
// =========================================================================
add_action( 'admin_init', 'lee_dev_pro_process_licence_activation_8167' );
add_action( 'init', 'lee_dev_pro_schedule_daily_licence_check_3492' );
add_action( 'itp_pro_daily_licence_verification_event', 'lee_dev_pro_verify_licence_remotely_5708' );