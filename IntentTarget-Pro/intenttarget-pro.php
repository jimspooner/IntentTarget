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
// 1. INITIALISE PRO FEATURES
// =========================================================================
add_action( 'plugins_loaded', 'itp_pro_init' );

function itp_pro_init() {
    // Check if the core plugin is active by looking for its main slide-out function
    if ( ! function_exists( 'lee_dev_render_intent_popup_2841' ) ) {
        return; // Core plugin is missing or deactivated; exit silently.
    }

    // 1. Remove the "Powered by" branding from the slide-in
    add_filter( 'itp_show_popup_branding', '__return_false' );

    // 2. Intercept and override the core colours with our saved custom colours
    add_filter( 'itp_core_design_colors', 'itp_pro_override_colors' );

    // 3. Load the admin menu settings page (this ensures the tab only shows when Pro is active)
    add_action( 'admin_menu', 'itp_pro_register_design_menu' );
}

// =========================================================================
// 2. OVERRIDE CORE COLOURS
// =========================================================================
function itp_pro_override_colors( $default_colors ) {
    // Fetch the colours saved by this Pro settings page
    $saved_colors = get_option('itp_design_settings', []);

    // Merge them. If a user hasn't set a specific colour, it safely falls back to the core default.
    return wp_parse_args( $saved_colors, $default_colors );
}

// =========================================================================
// 3. PRO ADMIN MENU & SETTINGS PAGE
// =========================================================================
function itp_pro_register_design_menu() {
    // You can change 'options-general.php' to your main plugin's menu slug 
    // if you want this to appear as a sub-menu of your core plugin.
    add_options_page(
        'IntentTarget Pro Design', 
        'ITP Design (Pro)', 
        'manage_options', 
        'itp-pro-design-settings', 
        'itp_pro_design_settings_page'
    );
}

function itp_pro_design_settings_page() {
    // Handle form submission
    if ( isset($_POST['itp_save_design']) && current_user_can('manage_options') ) {
        check_admin_referer('itp_save_design_action', 'itp_save_design_nonce');
        
        $design = isset($_POST['itp_design_settings']) ? array_map('sanitize_text_field', $_POST['itp_design_settings']) : [];
        update_option('itp_design_settings', $design);
        
        echo '<div class="notice notice-success is-dismissible"><p>Visual interface colours saved successfully.</p></div>';
    }

    // Retrieve existing options to populate the form
    $colors = get_option('itp_design_settings', []);
    
    // Set form defaults so the inputs aren't blank on first load
    $c_heading      = esc_attr($colors['heading'] ?? '#1d2327');
    $c_recommended  = esc_attr($colors['recommended'] ?? '#e1ad01');
    $c_button       = esc_attr($colors['button'] ?? '#2271b1');
    $c_button_text  = esc_attr($colors['button_text'] ?? '#ffffff');
    ?>
    <div class="wrap">
        <h2>IntentTarget Pro - Interface Design</h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('itp_save_design_action', 'itp_save_design_nonce'); ?>
            <div style="background:#fff; padding:25px; border:1px solid #ccd0d4; border-radius:4px; margin-top: 15px; max-width: 800px;">
                <h3 style="margin-top: 0;">Interface Presentation Colour Profiles</h3>
                <p class="description">Control the dynamic palette rendered across pop-out boxes, top message alert bars, and client account recommendations.</p>
                
                <table class="form-table" style="margin-top: 20px;">
                    <tr>
                        <th style="width: 250px;"><label>Heading & Offer Titles Colour</label></th>
                        <td>
                            <input type="color" name="itp_design_settings[heading]" value="<?php echo $c_heading; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" />
                            <input type="text" value="<?php echo $c_heading; ?>" class="small-text" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" />
                            <p class="description" style="margin-top:5px;">Applies to recommend block titles and question prompts.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>"Recommended" Badge Background</label></th>
                        <td>
                            <input type="color" name="itp_design_settings[recommended]" value="<?php echo $c_recommended; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" />
                            <input type="text" value="<?php echo $c_recommended; ?>" class="small-text" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" />
                            <p class="description" style="margin-top:5px;">Applies to the 'Recommended' chip backgrounds and search feedback labels.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Action Button Background</label></th>
                        <td>
                            <input type="color" name="itp_design_settings[button]" value="<?php echo $c_button; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" />
                            <input type="text" value="<?php echo $c_button; ?>" class="small-text" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" />
                            <p class="description" style="margin-top:5px;">Applies to interaction links inside recommendations and forms.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Action Button Label Text Colour</label></th>
                        <td>
                            <input type="color" name="itp_design_settings[button_text]" value="<?php echo $c_button_text; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" />
                            <input type="text" value="<?php echo $c_button_text; ?>" class="small-text" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" />
                        </td>
                    </tr>
                </table>

                <p class="submit" style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;">
                    <input type="submit" name="itp_save_design" class="button button-primary button-large" value="Save Interface Styles" />
                </p>
            </div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const colorInputs = document.querySelectorAll('input[type="color"]');
            colorInputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.nextElementSibling.value = this.value;
                });
            });
        });
    </script>
    <?php
}