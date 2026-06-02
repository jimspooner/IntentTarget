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
// 0a. LOAD PRO MODULES
// =========================================================================
require_once plugin_dir_path( __FILE__ ) . 'includes/class-lee-dev-pro-roi-tracking.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-lee-dev-pro-access-control.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-lee-dev-pro-ai-faq.php';

// =========================================================================
// 0. PRO PLUGIN CONSTANTS
// =========================================================================
if ( ! defined( 'LEE_DEV_PRO_PLUGIN_SLUG' ) ) {
    define( 'LEE_DEV_PRO_PLUGIN_SLUG', 'pro' );
}
if ( ! defined( 'LEE_DEV_PRO_LICENCE_PREFIX' ) ) {
    define( 'LEE_DEV_PRO_LICENCE_PREFIX', 'ITPP-' );
}
if ( ! defined( 'LEE_DEV_PRO_ACTIVATION_ENDPOINT' ) ) {
    define( 'LEE_DEV_PRO_ACTIVATION_ENDPOINT', 'https://intenttargetpro.com/wp-json/intenttarget-hub/v1/activate' );
}
if ( ! defined( 'LEE_DEV_PRO_VERIFICATION_ENDPOINT' ) ) {
    define( 'LEE_DEV_PRO_VERIFICATION_ENDPOINT', 'https://intenttargetpro.com/wp-json/intenttarget/v1/verify' );
}

// =========================================================================
// 1. INITIALISE PRO FEATURES (gated behind the IntentTarget Pro licence)
// =========================================================================
add_action( 'plugins_loaded', 'lee_dev_pro_initialise_addon_4519' );

function lee_dev_pro_initialise_addon_4519() {
    // Check if the core plugin is active
    if ( ! function_exists( 'lee_dev_render_intent_popup_2841' ) ) {
        return;
    }

    // Always inject the Pro tab so the licence form is reachable from the dashboard (Visibility rule).
    add_action( 'itp_core_dashboard_tabs', 'lee_dev_pro_inject_design_tab_8826' );
    add_action( 'itp_core_dashboard_tab_content_design_pro', 'lee_dev_pro_render_design_tab_5097' );

    // Only activate functional Pro behaviour (branding removal, colour overrides) once licensed.
    if ( function_exists( 'lee_dev_is_addon_active_3812' ) && lee_dev_is_addon_active_3812( LEE_DEV_PRO_PLUGIN_SLUG ) ) {
        add_filter( 'itp_show_popup_branding', '__return_false' );
        add_filter( 'itp_core_design_colors', 'lee_dev_pro_apply_design_overrides_7263' );
    }
}

// =========================================================================
// 2. PRO LICENCE GATE HELPERS
// =========================================================================
function lee_dev_pro_has_authorised_licence_2576() {
    return ( get_option( 'lee_dev_pro_licence_status', 'unauthorised' ) === 'authorised' );
}

function lee_dev_pro_check_licence_status_9304() {
    return lee_dev_pro_has_authorised_licence_2576();
}

// =========================================================================
// 3. OVERRIDE CORE COLOURS
// =========================================================================
function lee_dev_pro_apply_design_overrides_7263( $default_colors ) {
    $saved_colors = get_option( 'itp_design_settings', array() );
    return wp_parse_args( $saved_colors, $default_colors );
}

// =========================================================================
// 4. SMART THEME COLOUR SCANNER
// =========================================================================
function lee_dev_pro_detect_theme_palette_3145() {
    $colors = [];

    // 1. Scan modern Block Themes (WordPress 5.8+)
    if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
        $settings = WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
        if ( isset( $settings['color']['palette']['theme'] ) ) {
            foreach ( $settings['color']['palette']['theme'] as $color ) {
                if ( ! empty( $color['color'] ) ) {
                    $colors[] = $color['color'];
                }
            }
        }
    }

    // 2. Scan Classic Themes (Customizer/Gutenberg Support)
    if ( empty( $colors ) && current_theme_supports( 'editor-color-palette' ) ) {
        $palette = get_theme_support( 'editor-color-palette' );
        if ( ! empty( $palette[0] ) ) {
            foreach ( $palette[0] as $color ) {
                if ( ! empty( $color['color'] ) ) {
                    $colors[] = $color['color'];
                }
            }
        }
    }

    // 3. Last Resort Fallbacks (Standard Background/Header)
    if ( empty( $colors ) ) {
        $bg = get_background_color();
        if ( $bg ) $colors[] = '#' . ltrim( $bg, '#' );
        
        $header = get_header_textcolor();
        if ( $header && $header !== 'blank' ) $colors[] = '#' . ltrim( $header, '#' );
    }

    // Clean up: ensure valid hex codes, remove duplicates, and limit to 8 colours for a clean UI
    $clean_colors = [];
    foreach ( $colors as $c ) {
        // Basic check to ensure it looks like a hex code or rgb/var string
        if ( is_string($c) && ! in_array($c, $clean_colors) ) {
            $clean_colors[] = $c;
        }
    }
    
    return array_slice( $clean_colors, 0, 8 );
}

// =========================================================================
// 5. PRO DASHBOARD TAB INJECTION
// =========================================================================

// Inject the physical tab into the Core navigation wrapper
function lee_dev_pro_inject_design_tab_8826( $active_tab ) {
    $is_active = ( $active_tab === 'design_pro' ) ? 'nav-tab-active' : '';
    echo '<a href="?page=itp-search-dashboard&tab=design_pro" class="nav-tab ' . esc_attr( $is_active ) . '">' . esc_html__( 'Advert Styling (Pro)', 'intenttarget-pro' ) . '</a>';
}

// Render the content when the custom tab is clicked
function lee_dev_pro_render_design_tab_5097() {
    // Always render the Pro licence banner first so the licence is reachable even when unauthorised.
    lee_dev_pro_render_licence_panel_4193();

    // Gate the design controls behind the Pro licence (Visibility rule: UI stays present but disabled).
    if ( ! lee_dev_pro_has_authorised_licence_2576() ) {
        ?>
        <div style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #d63638;padding:18px 22px;margin-top:20px;border-radius:4px;max-width:800px;">
            <h3 style="margin-top:0;"><?php echo esc_html__( 'IntentTarget Pro Add-On Licence Required', 'intenttarget-pro' ); ?></h3>
            <p><?php echo esc_html__( 'The advert styling controls are disabled until a valid IntentTarget Pro licence code (prefix ITPP-) is activated above. Visit your customer dashboard to purchase a Pro licence.', 'intenttarget-pro' ); ?></p>
            <p>
                <a href="https://intenttargetpro.co.uk/pro" target="_blank" rel="noopener" class="button button-primary"><?php echo esc_html__( 'Purchase IntentTarget Pro Add-On', 'intenttarget-pro' ); ?></a>
            </p>
        </div>
        <?php
        return;
    }

    // Handle form submission
    if ( isset( $_POST['itp_save_design'] ) && current_user_can( 'manage_options' ) ) {
        check_admin_referer( 'itp_save_design_action', 'itp_save_design_nonce' );

        $design = isset( $_POST['itp_design_settings'] ) ? array_map( 'sanitize_text_field', $_POST['itp_design_settings'] ) : array();
        update_option( 'itp_design_settings', $design );

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Visual interface colours saved successfully.', 'intenttarget-pro' ) . '</p></div>';
    }

    // Retrieve existing options
    $colors = get_option( 'itp_design_settings', array() );

    // Set form defaults
    $c_heading      = esc_attr( $colors['heading'] ?? '#1d2327' );
    $c_recommended  = esc_attr( $colors['recommended'] ?? '#e1ad01' );
    $c_button       = esc_attr( $colors['button'] ?? '#2271b1' );
    $c_button_text  = esc_attr( $colors['button_text'] ?? '#ffffff' );

    // Run the theme scanner
    $detected_palette = lee_dev_pro_detect_theme_palette_3145();
    ?>
    <form method="post" action="">
        <?php wp_nonce_field('itp_save_design_action', 'itp_save_design_nonce'); ?>
        <div style="background:#fff; padding:25px; border:1px solid #ccd0d4; border-radius:4px; margin-top: 15px; max-width: 800px;">
            <h3 style="margin-top: 0;">Interface Presentation Colour Profiles</h3>
            <p class="description">Control the dynamic palette rendered across pop-out boxes, top message alert bars, and client account recommendations.</p>
            
            <?php if ( ! empty( $detected_palette ) ) : ?>
            <div style="margin-top: 25px; padding: 15px; background: #f6f7f7; border-left: 4px solid #72aee6; border-radius: 3px;">
                <h4 style="margin: 0 0 10px 0; font-size: 13px;">🎨 Detected Theme Palette</h4>
                <p class="description" style="margin-bottom: 10px; font-size: 12px;">We scanned your active theme. Click a swatch below to easily copy its hex code, then paste it into your desired setting.</p>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php foreach ( $detected_palette as $hex ) : ?>
                        <button type="button" class="itp-swatch-btn" data-hex="<?php echo esc_attr( $hex ); ?>" title="<?php echo esc_attr( $hex ); ?>" style="width: 32px; height: 32px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.2); background-color: <?php echo esc_attr( $hex ); ?>; cursor: pointer; transition: transform 0.1s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <table class="form-table" style="margin-top: 25px;">
                <tr>
                    <th style="width: 250px;"><label>Heading & Offer Titles Colour</label></th>
                    <td>
                        <input type="color" id="itp_c_heading" name="itp_design_settings[heading]" value="<?php echo $c_heading; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" />
                        <input type="text" id="itp_t_heading" value="<?php echo $c_heading; ?>" class="small-text itp-hex-input" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" />
                        <p class="description" style="margin-top:5px;">Applies to recommend block titles and question prompts.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>"Recommended" Badge Background</label></th>
                    <td>
                        <input type="color" id="itp_c_recommended" name="itp_design_settings[recommended]" value="<?php echo $c_recommended; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" />
                        <input type="text" id="itp_t_recommended" value="<?php echo $c_recommended; ?>" class="small-text itp-hex-input" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" />
                        <p class="description" style="margin-top:5px;">Applies to the 'Recommended' chip backgrounds and search feedback labels.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Action Button Background</label></th>
                    <td>
                        <input type="color" id="itp_c_button" name="itp_design_settings[button]" value="<?php echo $c_button; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" />
                        <input type="text" id="itp_t_button" value="<?php echo $c_button; ?>" class="small-text itp-hex-input" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" />
                        <p class="description" style="margin-top:5px;">Applies to interaction links inside recommendations and forms.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Action Button Label Text Colour</label></th>
                    <td>
                        <input type="color" id="itp_c_button_text" name="itp_design_settings[button_text]" value="<?php echo $c_button_text; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" />
                        <input type="text" id="itp_t_button_text" value="<?php echo $c_button_text; ?>" class="small-text itp-hex-input" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" />
                    </td>
                </tr>
            </table>

            <p class="submit" style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;">
                <input type="submit" name="itp_save_design" class="button button-primary button-large" value="Save Interface Styles" />
            </p>
        </div>
    </form>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Sync colour picker with text input
            const colorInputs = document.querySelectorAll('input[type="color"]');
            colorInputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.nextElementSibling.value = this.value;
                });
            });

            // 2. Handle clicking a detected theme swatch
            const swatches = document.querySelectorAll('.itp-swatch-btn');
            swatches.forEach(swatch => {
                swatch.addEventListener('click', function() {
                    const hex = this.getAttribute('data-hex');
                    
                    // Copy to clipboard for easy pasting
                    navigator.clipboard.writeText(hex).then(function() {
                        // Visual feedback
                        const originalTransform = swatch.style.transform;
                        swatch.style.transform = 'scale(0.8)';
                        setTimeout(() => swatch.style.transform = originalTransform, 150);
                    });
                });
            });
        });
    </script>
    <?php
}

// Run this on plugin activation or a specific admin action
function lee_dev_pro_auto_populate_theme_colours_6821() {
    $current_settings = get_option( 'itp_design_settings', array() );

    // Only auto-populate if the user hasn't set anything yet
    if ( empty( $current_settings ) ) {
        $palette = lee_dev_pro_detect_theme_palette_3145();

        // Map the first 4 detected colours to your settings
        $new_settings = array(
            'heading'     => $palette[0] ?? '#1d2327',
            'recommended' => $palette[1] ?? '#e1ad01',
            'button'      => $palette[2] ?? '#2271b1',
            'button_text' => $palette[3] ?? '#ffffff',
        );

        update_option( 'itp_design_settings', $new_settings );
    }
}

// =========================================================================
// 6. PRO LICENCE ACTIVATION BANNER (rendered inside the Pro dashboard tab)
// =========================================================================
function lee_dev_pro_render_licence_panel_4193() {
    $licence_status = get_option( 'lee_dev_pro_licence_status', 'unauthorised' );
    $licence_key    = get_option( 'lee_dev_pro_licence_key', '' );

    if ( isset( $_GET['pro-licence-updated'] ) ) {
        $status_update = sanitize_text_field( wp_unslash( $_GET['pro-licence-updated'] ) );
        if ( $status_update === 'authorised' ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Pro Licence Activated:', 'intenttarget-pro' ) . '</strong> ' . esc_html__( 'The IntentTarget Pro add-on is now authorised on this site.', 'intenttarget-pro' ) . '</p></div>';
        } elseif ( $status_update === 'deactivated' ) {
            echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html__( 'Pro Licence Deactivated:', 'intenttarget-pro' ) . '</strong> ' . esc_html__( 'The Pro add-on has been deactivated locally.', 'intenttarget-pro' ) . '</p></div>';
        } elseif ( $status_update === 'invalid' ) {
            $pro_err_msg = isset( $_GET['err_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['err_msg'] ) ) : '';
            if ( ! empty( $pro_err_msg ) ) {
                echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Pro Licence Denied:', 'intenttarget-pro' ) . '</strong> ' . esc_html( $pro_err_msg ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Pro Licence Denied:', 'intenttarget-pro' ) . '</strong> ' . esc_html__( 'The key provided is invalid. Pro licence codes must use the ITPP- prefix.', 'intenttarget-pro' ) . '</p></div>';
            }
        } elseif ( $status_update === 'empty' ) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Empty Pro Licence Key:', 'intenttarget-pro' ) . '</strong> ' . esc_html__( 'Please provide a key for activation.', 'intenttarget-pro' ) . '</p></div>';
        }
    }

    $border_colour = ( $licence_status === 'authorised' ) ? '#46b450' : '#d63638';
    ?>
    <div style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid <?php echo esc_attr( $border_colour ); ?>;padding:15px 20px;margin:15px 0 20px 0;border-radius:4px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;">
        <div style="min-width:260px;">
            <h3 style="margin:0 0 5px 0;font-size:15px;font-weight:bold;color:#1d2327;">
                <?php echo esc_html__( 'IntentTarget Pro Licence Status:', 'intenttarget-pro' ); ?>
                <span style="color:<?php echo esc_attr( $border_colour ); ?>;text-transform:uppercase;">
                    <?php echo esc_html( $licence_status ); ?>
                </span>
            </h3>
            <p style="margin:0;font-size:13px;color:#646970;">
                <?php if ( $licence_status === 'authorised' ) : ?>
                    <?php echo esc_html__( 'The IntentTarget Pro add-on is fully authorised on this domain.', 'intenttarget-pro' ); ?>
                <?php else : ?>
                    <?php echo esc_html__( 'Pro features are disabled. Enter your Pro licence code (ITPP-XXXX-XXXX-XXXX) to activate.', 'intenttarget-pro' ); ?>
                <?php endif; ?>
            </p>
        </div>
        <form method="post" action="" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <?php wp_nonce_field( 'itp_pro_licence_action', 'itp_pro_licence_nonce' ); ?>
            <?php if ( $licence_status !== 'authorised' ) : ?>
                <input type="hidden" name="itp_pro_licence_action_type" value="activate" />
                <input type="text" name="itp_pro_licence_key" value="<?php echo esc_attr( $licence_key ); ?>" placeholder="ITPP-XXXX-XXXX-XXXX" style="padding:6px 10px;font-size:13px;width:240px;border:1px solid #8c8f94;border-radius:4px;" />
                <input type="submit" name="itp_pro_licence_submit" class="button button-primary" value="<?php echo esc_attr__( 'Activate Pro Licence', 'intenttarget-pro' ); ?>" />
            <?php else : ?>
                <input type="hidden" name="itp_pro_licence_action_type" value="deactivate" />
                <span style="font-family:monospace;font-size:13px;color:#646970;background:#f6f7f7;padding:6px 12px;border:1px solid #ccd0d4;border-radius:4px;">
                    <?php echo esc_html( substr( $licence_key, 0, 7 ) . '...' . substr( $licence_key, -4 ) ); ?>
                </span>
                <input type="submit" name="itp_pro_licence_submit" class="button button-secondary" value="<?php echo esc_attr__( 'Deactivate', 'intenttarget-pro' ); ?>" />
            <?php endif; ?>
        </form>
    </div>
    <?php
}

// =========================================================================
// 7. PRO LICENCE ACTIVATION FORM HANDLER
// =========================================================================
add_action( 'admin_init', 'lee_dev_pro_process_licence_activation_8167' );
function lee_dev_pro_process_licence_activation_8167() {
    if ( ! isset( $_POST['itp_pro_licence_submit'] ) ) {
        return;
    }

    if ( ! isset( $_POST['itp_pro_licence_nonce'] ) || ! wp_verify_nonce( $_POST['itp_pro_licence_nonce'], 'itp_pro_licence_action' ) ) {
        wp_die( esc_html__( 'Security verification failed.', 'intenttarget-pro' ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Insufficient permissions to perform this action.', 'intenttarget-pro' ) );
    }

    $action = sanitize_text_field( $_POST['itp_pro_licence_action_type'] ?? '' );

    if ( $action === 'activate' ) {
        $raw_key   = sanitize_text_field( $_POST['itp_pro_licence_key'] ?? '' );
        $clean_key = trim( $raw_key );

        if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
            lee_dev_debug_log_event_6158( 'pro_licence.activation.started', array(
                'domain' => wp_parse_url( home_url(), PHP_URL_HOST ),
            ) );
        }

        if ( empty( $clean_key ) ) {
            update_option( 'lee_dev_pro_licence_status', 'unauthorised' );
            lee_dev_pro_redirect_to_design_tab_3187( 'empty' );
        }

        if ( strpos( $clean_key, LEE_DEV_PRO_LICENCE_PREFIX ) !== 0 ) {
            update_option( 'lee_dev_pro_licence_key', $clean_key );
            update_option( 'lee_dev_pro_licence_status', 'unauthorised' );
            lee_dev_pro_redirect_to_design_tab_3187( 'invalid' );
        }

        $endpoint = apply_filters( 'lee_dev_pro_licence_activation_endpoint_8167', LEE_DEV_PRO_ACTIVATION_ENDPOINT );
        $response = wp_safe_remote_post( $endpoint, array(
            'timeout' => 15,
            'headers' => array(
                'x_intenttarget_client_auth' => 'ITP_SECURE_CLIENT_HANDSHAKE_2026',
            ),
            'body'    => array(
                'licence_code'     => $clean_key,
                'plugin_slug'      => LEE_DEV_PRO_PLUGIN_SLUG,
                'activation_email' => get_option( 'admin_email' ),
                'domain'           => wp_parse_url( home_url(), PHP_URL_HOST ),
                'client_url'       => home_url(),
                'client_auth'      => 'ITP_SECURE_CLIENT_HANDSHAKE_2026',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            update_option( 'lee_dev_pro_licence_key', $clean_key );
            update_option( 'lee_dev_pro_licence_status', 'unauthorised' );
            if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
                lee_dev_debug_log_event_6158( 'pro_licence.activation.remote_error', array(
                    'message' => $response->get_error_message(),
                ) );
            }
            lee_dev_pro_redirect_to_design_tab_3187( 'invalid' );
        }

        $response_code = (int) wp_remote_retrieve_response_code( $response );
        $body          = json_decode( wp_remote_retrieve_body( $response ), true );
        $remote_status = is_array( $body ) && isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : 'unauthorised';
        $remote_msg    = is_array( $body ) && isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : '';

        if ( $response_code === 200 && $remote_status === 'active' ) {
            update_option( 'lee_dev_pro_licence_key', $clean_key );
            update_option( 'lee_dev_pro_licence_status', 'authorised' );
            if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
                lee_dev_debug_log_event_6158( 'pro_licence.activation.authorised', array(
                    'response_code' => $response_code,
                    'status'        => $remote_status,
                ) );
            }
            lee_dev_pro_redirect_to_design_tab_3187( 'authorised' );
        } else {
            update_option( 'lee_dev_pro_licence_key', $clean_key );
            update_option( 'lee_dev_pro_licence_status', 'unauthorised' );
            if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
                lee_dev_debug_log_event_6158( 'pro_licence.activation.denied', array(
                    'response_code' => $response_code,
                    'status'        => $remote_status,
                    'message'       => $remote_msg,
                ) );
            }
            lee_dev_pro_redirect_to_design_tab_3187( 'invalid', $remote_msg );
        }
    } elseif ( $action === 'deactivate' ) {
        // Ping Master Hub to release the Pro licence remotely.
        $pro_key = get_option( 'lee_dev_pro_licence_key', '' );
        if ( ! empty( $pro_key ) ) {
            $endpoint = apply_filters( 'lee_dev_pro_licence_release_endpoint', 'https://intenttargetpro.com/wp-json/intenttarget/v1/release' );
            wp_remote_post( $endpoint, array(
                'timeout'  => 10,
                'blocking' => false,
                'body'     => array( 'licence_code' => $pro_key ),
            ) );
        }

        delete_option( 'lee_dev_pro_licence_key' );
        update_option( 'lee_dev_pro_licence_status', 'unauthorised' );
        if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
            lee_dev_debug_log_event_6158( 'pro_licence.deactivated' );
        }
        lee_dev_pro_redirect_to_design_tab_3187( 'deactivated' );
    }
}

function lee_dev_pro_redirect_to_design_tab_3187( $status, $error_message = '' ) {
    $args = array(
        'page'                => 'itp-search-dashboard',
        'tab'                 => 'design_pro',
        'pro-licence-updated' => sanitize_text_field( $status ),
    );
    if ( ! empty( $error_message ) ) {
        $args['err_msg'] = rawurlencode( $error_message );
    }
    wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
    exit;
}

// =========================================================================
// 8. DAILY BACKGROUND PRO LICENCE VERIFICATION
// =========================================================================
add_action( 'init', 'lee_dev_pro_schedule_daily_licence_check_3492' );
function lee_dev_pro_schedule_daily_licence_check_3492() {
    if ( ! wp_next_scheduled( 'itp_pro_daily_licence_verification_event' ) ) {
        wp_schedule_event( time(), 'daily', 'itp_pro_daily_licence_verification_event' );
    }
}

add_action( 'itp_pro_daily_licence_verification_event', 'lee_dev_pro_verify_licence_remotely_5708' );
function lee_dev_pro_verify_licence_remotely_5708() {
    $current_key    = get_option( 'lee_dev_pro_licence_key', '' );
    $current_status = get_option( 'lee_dev_pro_licence_status', 'unauthorised' );

    if ( empty( $current_key ) || $current_status !== 'authorised' ) {
        return;
    }

    $endpoint = apply_filters( 'lee_dev_pro_licence_verification_endpoint_5708', LEE_DEV_PRO_VERIFICATION_ENDPOINT );

    $response = wp_safe_remote_post( $endpoint, array(
        'timeout'     => 15,
        'redirection' => 5,
        'blocking'    => true,
        'body'        => array(
            'licence_code'     => sanitize_text_field( $current_key ),
            'plugin_slug'      => LEE_DEV_PRO_PLUGIN_SLUG,
            'activation_email' => get_option( 'admin_email' ),
            'domain'           => wp_parse_url( home_url(), PHP_URL_HOST ),
            'client_url'       => home_url(),
        ),
    ) );

    if ( is_wp_error( $response ) ) {
        return;
    }

    $response_code = (int) wp_remote_retrieve_response_code( $response );
    $body          = json_decode( wp_remote_retrieve_body( $response ), true );
    $remote_status = is_array( $body ) && isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : '';

    if ( $response_code === 403 || in_array( $remote_status, array( 'deactivated', 'unauthorised' ), true ) ) {
        update_option( 'lee_dev_pro_licence_status', 'unauthorised' );

        if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
            lee_dev_debug_log_event_6158( 'pro_licence.remote_deactivation', array(
                'reason' => isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : 'Revoked by Master Hub',
            ) );
        }
    }
}

// =========================================================================
// 9. PLUGIN LIFECYCLE HOOKS
// =========================================================================
register_activation_hook( __FILE__, 'lee_dev_pro_activate_plugin_lifecycle_6829' );
function lee_dev_pro_activate_plugin_lifecycle_6829() {
    if ( ! wp_next_scheduled( 'itp_pro_daily_licence_verification_event' ) ) {
        wp_schedule_event( time(), 'daily', 'itp_pro_daily_licence_verification_event' );
    }

    if ( ! get_option( 'lee_dev_pro_licence_status' ) ) {
        add_option( 'lee_dev_pro_licence_status', 'unauthorised' );
    }

    // Provision the ROI attribution table immediately on plugin activation.
    if ( function_exists( 'lee_dev_pro_install_roi_table_3956' ) ) {
        lee_dev_pro_install_roi_table_3956();
    }
}

register_deactivation_hook( __FILE__, 'lee_dev_pro_deactivate_plugin_lifecycle_9415' );
function lee_dev_pro_deactivate_plugin_lifecycle_9415() {
    $timestamp = wp_next_scheduled( 'itp_pro_daily_licence_verification_event' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'itp_pro_daily_licence_verification_event' );
    }
}