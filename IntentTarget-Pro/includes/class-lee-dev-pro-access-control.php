<?php
/**
 * IntentTarget Pro — Role-Based Access Control
 *
 * Owns the entire Access Control feature on behalf of the IntentTarget suite. Renders the
 * dashboard tab, handles the role-list save submission, and applies the role gate at runtime
 * through the `lee_dev_is_ready_role_gate_5821` filter exposed by Core.
 *
 * Behaviour summary:
 *  - Pro authorised   → admin can edit roles AND the role filter is enforced for tracking.
 *  - Pro unauthorised → tab still visible (Visibility rule) but content is the up-sell panel.
 *                       Core's `lee_dev_is_ready_8293()` lets every logged-in user through
 *                       (no role restriction) so the upgrade hook is clear.
 *
 * All entry points are wrapped in lee_dev_is_addon_active_3812( 'pro' ) so the module remains
 * completely dormant on unlicensed installs (Master Hub up-sell rule).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =========================================================================
// 1. TAB INJECTION & CONTENT RENDERING
// =========================================================================
add_action( 'itp_core_dashboard_tabs', 'lee_dev_pro_inject_access_control_tab_4732' );
add_action( 'itp_core_dashboard_tab_content_access_control', 'lee_dev_pro_render_access_control_tab_8194' );

function lee_dev_pro_inject_access_control_tab_4732( $active_tab ) {
    $is_active = ( $active_tab === 'access_control' ) ? 'nav-tab-active' : '';
    echo '<a href="?page=itp-search-dashboard&tab=access_control" class="nav-tab ' . esc_attr( $is_active ) . '">' . esc_html__( 'Access Control (Pro)', 'intenttarget-pro' ) . '</a>';
}

function lee_dev_pro_render_access_control_tab_8194() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Insufficient access privileges.', 'intenttarget-pro' ) );
    }

    $is_pro_active = function_exists( 'lee_dev_is_addon_active_3812' ) && lee_dev_is_addon_active_3812( 'pro' );

    if ( ! $is_pro_active ) {
        ?>
        <div style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #d63638;padding:18px 22px;margin-top:20px;border-radius:4px;max-width:800px;">
            <h3 style="margin-top:0;"><?php echo esc_html__( 'IntentTarget Pro Licence Required', 'intenttarget-pro' ); ?></h3>
            <p><?php echo esc_html__( 'Role-based Access Control is part of the IntentTarget Pro add-on. Without a Pro licence the tracking engine treats every authorised role identically. Activate a Pro licence (ITPP-XXXX-XXXX-XXXX) to lock tracking down to a specific set of user roles.', 'intenttarget-pro' ); ?></p>
            <p>
                <a href="?page=itp-search-dashboard&tab=design_pro" class="button button-primary"><?php echo esc_html__( 'Go to Pro Licence Activation', 'intenttarget-pro' ); ?></a>
                <a href="https://intenttargetpro.co.uk/pro" target="_blank" rel="noopener" class="button button-secondary"><?php echo esc_html__( 'Purchase IntentTarget Pro', 'intenttarget-pro' ); ?></a>
            </p>
        </div>

        <div style="background:#fff;padding:25px;margin-top:20px;border:1px solid #ccd0d4;border-radius:4px;max-width:650px;opacity:0.55;pointer-events:none;">
            <h3 style="margin-top:0;"><?php echo esc_html__( 'Authorised Tracking User Roles', 'intenttarget-pro' ); ?></h3>
            <p class="description" style="margin-bottom:20px;"><?php echo esc_html__( 'These controls are disabled until a Pro licence is activated.', 'intenttarget-pro' ); ?></p>
            <?php
            $wp_roles = wp_roles()->get_names();
            foreach ( $wp_roles as $role_slug => $role_name ) : ?>
                <div style="margin-bottom:14px;">
                    <label style="font-size:14px;display:inline-flex;align-items:center;">
                        <input type="checkbox" disabled style="margin-right:10px;" />
                        <?php echo esc_html( $role_name ); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return;
    }

    $wp_roles      = wp_roles()->get_names();
    $allowed_roles = get_option( 'itp_allowed_tracking_roles', array() );
    if ( ! is_array( $allowed_roles ) ) {
        $allowed_roles = array();
    }

    if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
        echo '<div class="updated notice is-dismissible" style="margin: 15px 0 0 0;"><p>' . esc_html__( 'Access configurations updated.', 'intenttarget-pro' ) . '</p></div>';
    }
    ?>
    <div style="background:#fff;padding:25px;margin-top:15px;border:1px solid #ccd0d4;border-radius:4px;max-width:650px;">
        <h3 style="margin-top:0;"><?php echo esc_html__( 'Authorised Tracking User Roles', 'intenttarget-pro' ); ?></h3>
        <p class="description" style="margin-bottom:25px;"><?php echo esc_html__( 'Tick which local site user roles activate the broader analytics engine execution. Roles left unticked will be excluded from all keyword, intent, and advert tracking.', 'intenttarget-pro' ); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field( 'itp_save_access_settings_nonce' ); ?>
            <table class="form-table" style="margin-bottom:20px;">
                <tr>
                    <td style="padding:0;">
                        <?php foreach ( $wp_roles as $role_slug => $role_name ) : ?>
                            <div style="margin-bottom:14px;">
                                <label style="font-size:14px;display:inline-flex;align-items:center;cursor:pointer;">
                                    <input type="checkbox" name="itp_roles[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $allowed_roles, true ) ); ?> style="margin-right:10px;" />
                                    <?php echo esc_html( $role_name ); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <p class="submit" style="margin:0;"><input type="submit" name="itp_save_access_settings" class="button button-primary button-large" value="<?php echo esc_attr__( 'Update Roles', 'intenttarget-pro' ); ?>" /></p>
        </form>
    </div>
    <?php
}

// =========================================================================
// 2. SAVE HANDLER — Pro-gated
// =========================================================================
add_action( 'admin_init', 'lee_dev_pro_process_access_settings_submission_3680' );
function lee_dev_pro_process_access_settings_submission_3680() {
    if ( ! isset( $_POST['itp_save_access_settings'] ) ) {
        return;
    }

    check_admin_referer( 'itp_save_access_settings_nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Insufficient permissions.', 'intenttarget-pro' ) );
    }

    // Hard gate: even if the request reaches us, refuse the write when Pro is unauthorised.
    if ( ! function_exists( 'lee_dev_is_addon_active_3812' ) || ! lee_dev_is_addon_active_3812( 'pro' ) ) {
        wp_die( esc_html__( 'Access Control requires an authorised IntentTarget Pro licence.', 'intenttarget-pro' ) );
    }

    $submitted = isset( $_POST['itp_roles'] ) && is_array( $_POST['itp_roles'] )
        ? array_map( 'sanitize_text_field', wp_unslash( $_POST['itp_roles'] ) )
        : array();

    // Restrict to roles that actually exist on this site.
    $known_roles    = array_keys( wp_roles()->get_names() );
    $selected_roles = array_values( array_unique( array_filter( $submitted, function ( $candidate ) use ( $known_roles ) {
        return in_array( $candidate, $known_roles, true );
    } ) ) );

    update_option( 'itp_allowed_tracking_roles', $selected_roles );

    if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
        lee_dev_debug_log_event_6158( 'pro_access.roles.updated', array( 'roles' => $selected_roles ) );
    }

    wp_safe_redirect( add_query_arg( array(
        'page'              => 'itp-search-dashboard',
        'tab'               => 'access_control',
        'settings-updated'  => 'true',
    ), admin_url( 'admin.php' ) ) );
    exit;
}

// =========================================================================
// 3. RUNTIME ROLE GATE — only enforced when Pro is authorised
// =========================================================================
add_filter( 'lee_dev_is_ready_role_gate_5821', 'lee_dev_pro_apply_role_gate_2901', 10, 2 );
function lee_dev_pro_apply_role_gate_2901( $is_ready, $current_user ) {
    if ( ! function_exists( 'lee_dev_is_addon_active_3812' ) || ! lee_dev_is_addon_active_3812( 'pro' ) ) {
        return $is_ready; // Pass through: Core decides without role filtering.
    }

    if ( ! ( $current_user instanceof WP_User ) || ! $current_user->exists() ) {
        return false;
    }

    $allowed_roles = get_option( 'itp_allowed_tracking_roles', array() );
    if ( ! is_array( $allowed_roles ) || empty( $allowed_roles ) ) {
        // Admin saved an empty list → explicitly opted into closed mode.
        return false;
    }

    foreach ( (array) $current_user->roles as $role ) {
        if ( in_array( $role, $allowed_roles, true ) ) {
            return true;
        }
    }
    return false;
}
