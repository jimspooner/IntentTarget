<?php
/**
 * Plugin Name:       IntentTarget Master Hub
 * Plugin URI:        https://intenttargetpro.com
 * Description:       Standalone master server interface for IntentTarget Pro licence verification and client telemetry tracking.
 * Version:           1.0.0
 * Requires PHP:      8.0
 * Author:            Jim / Lee Dev
 * Author URI:        https://lee-dev.co.uk
 * License:           GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

register_activation_hook( __FILE__, 'lee_dev_install_master_hub_tables_2941' );
add_action( 'init', 'lee_dev_install_master_hub_tables_2941' );
add_action( 'admin_menu', 'lee_dev_register_master_hub_menu_5728' );
add_action( 'admin_post_itp_create_licence', 'lee_dev_process_master_hub_code_creation_6274' );
add_action( 'admin_post_itp_deactivate_licence', 'lee_dev_process_master_hub_deactivation_7634' );
add_action( 'rest_api_init', 'lee_dev_register_master_hub_api_endpoints_9381' );
add_action( 'admin_post_itp_reactivate_licence', 'lee_dev_process_master_hub_reactivation_8821' );

function lee_dev_get_master_hub_table_name_4826() {
    global $wpdb;
    return $wpdb->prefix . 'intenttarget_licenses';
}

/**
 * Central registry of the IntentTarget products the Master Hub is authorised to issue licences for.
 *
 * Each entry defines the persisted plugin slug, the customer-facing label, and the licence code
 * prefix used during code generation and inbound validation. Adding a new product here is the
 * single source of truth that propagates to the dashboard selector, code generator, and the
 * REST activation/verification handlers.
 *
 * @return array
 */
function lee_dev_get_master_hub_supported_plugins_8521() {
    $supported = array(
        'core' => array(
            'label'  => __( 'IntentTarget Core', 'intenttarget-pro' ),
            'prefix' => 'ITP-',
        ),
        'pro'  => array(
            'label'  => __( 'IntentTarget Pro Add-On', 'intenttarget-pro' ),
            'prefix' => 'ITPP-',
        ),
    );

    return apply_filters( 'lee_dev_master_hub_supported_plugins_8521', $supported );
}

/**
 * Resolves an incoming plugin slug to its supported configuration, falling back to 'core' so
 * historic licence rows and legacy client requests without an explicit slug remain compatible.
 *
 * @param string $plugin_slug Raw slug to resolve.
 * @return array Tuple containing the resolved slug and its configuration array.
 */
function lee_dev_resolve_master_hub_plugin_slug_7164( $plugin_slug ) {
    $supported = lee_dev_get_master_hub_supported_plugins_8521();
    $slug      = sanitize_key( (string) $plugin_slug );

    if ( ! isset( $supported[ $slug ] ) ) {
        $slug = 'core';
    }

    return array( $slug, $supported[ $slug ] );
}

function lee_dev_install_master_hub_tables_2941() {
    global $wpdb;

    $table_name      = lee_dev_get_master_hub_table_name_4826();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        licence_code varchar(100) NOT NULL,
        plugin_slug varchar(40) NOT NULL DEFAULT 'core',
        activation_email varchar(190) NOT NULL DEFAULT '',
        mapped_domain varchar(255) NOT NULL DEFAULT '',
        client_url varchar(255) NOT NULL DEFAULT '',
        status varchar(30) NOT NULL DEFAULT 'pending',
        activation_count int(11) unsigned NOT NULL DEFAULT 0,
        last_request_ip varchar(100) NOT NULL DEFAULT '',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        activated_at datetime DEFAULT NULL,
        deactivated_at datetime DEFAULT NULL,
        last_seen_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY licence_code (licence_code),
        KEY mapped_domain (mapped_domain),
        KEY activation_email (activation_email),
        KEY status (status),
        KEY plugin_slug (plugin_slug)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

function lee_dev_register_master_hub_menu_5728() {
    add_menu_page(
        'IntentTarget Master Hub',
        'IntentTarget Hub',
        'manage_options',
        'intenttarget-master-hub',
        'lee_dev_render_master_hub_dashboard_1109',
        'dashicons-shield-alt',
        58
    );
}

function lee_dev_generate_activation_code_4197( $plugin_slug = 'core' ) {
    list( , $config ) = lee_dev_resolve_master_hub_plugin_slug_7164( $plugin_slug );
    $prefix           = isset( $config['prefix'] ) ? $config['prefix'] : 'ITP-';

    global $wpdb;
    do {
        $code = $prefix . strtoupper( wp_generate_password( 4, false, false ) ) . '-' . strtoupper( wp_generate_password( 4, false, false ) ) . '-' . strtoupper( wp_generate_password( 4, false, false ) );
        $exists = $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM ' . lee_dev_get_master_hub_table_name_4826() . ' WHERE licence_code = %s LIMIT 1',
            $code
        ) );
    } while ( ! empty( $exists ) );

    return $code;
}

function lee_dev_render_master_hub_dashboard_1109() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not authorised to view this licence hub.', 'intenttarget-pro' ) );
    }

    global $wpdb;
    $table_name = lee_dev_get_master_hub_table_name_4826();

    if ( isset( $_GET['itp-status'] ) ) {
        $notice_status = sanitize_text_field( wp_unslash( $_GET['itp-status'] ) );
        if ( $notice_status === 'deactivated' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Licence status switched to deactivated.', 'intenttarget-pro' ) . '</p></div>';
        } elseif ( $notice_status === 'created' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'New licence code created and ready to issue to a customer.', 'intenttarget-pro' ) . '</p></div>';
        } elseif ( $notice_status === 'reactivated' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Licence status restored successfully.', 'intenttarget-pro' ) . '</p></div>';
        } elseif ( $notice_status === 'missing' ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The selected licence could not be found.', 'intenttarget-pro' ) . '</p></div>';
        }
    }

    $supported_plugins = lee_dev_get_master_hub_supported_plugins_8521();

    $filter_slug = isset( $_GET['plugin_slug'] ) ? sanitize_key( wp_unslash( $_GET['plugin_slug'] ) ) : '';
    if ( $filter_slug !== '' && ! isset( $supported_plugins[ $filter_slug ] ) ) {
        $filter_slug = '';
    }

    if ( $filter_slug !== '' ) {
        $licences = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, licence_code, plugin_slug, activation_email, mapped_domain, client_url, status, activation_count, created_at, activated_at, deactivated_at, last_seen_at FROM $table_name WHERE plugin_slug = %s ORDER BY last_seen_at DESC, created_at DESC LIMIT 200",
            $filter_slug
        ) );
    } else {
        $licences = $wpdb->get_results(
            "SELECT id, licence_code, plugin_slug, activation_email, mapped_domain, client_url, status, activation_count, created_at, activated_at, deactivated_at, last_seen_at FROM $table_name ORDER BY last_seen_at DESC, created_at DESC LIMIT 200"
        );
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'IntentTarget Licensing Master Hub', 'intenttarget-pro' ); ?></h1>
        <p><?php echo esc_html__( 'Review active licence codes, activation emails, mapped domains, and remote client status from one secure control panel. Codes can be issued separately for IntentTarget Core and the IntentTarget Pro add-on.', 'intenttarget-pro' ); ?></p>

        <div style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #2271b1;padding:16px 18px;margin:18px 0 22px 0;max-width:900px;">
            <h2 style="margin-top:0;"><?php echo esc_html__( 'Create Customer Licence Code', 'intenttarget-pro' ); ?></h2>
            <p><?php echo esc_html__( 'Generate a paid customer licence code here, then send the issued code to the customer for plugin activation. Choose the product the customer has purchased; the prefix will be applied automatically.', 'intenttarget-pro' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
                <input type="hidden" name="action" value="itp_create_licence" />
                <?php wp_nonce_field( 'itp_create_licence_code', 'itp_create_licence_nonce' ); ?>
                <label>
                    <span style="display:block;font-weight:600;margin-bottom:4px;"><?php echo esc_html__( 'Plugin / Product', 'intenttarget-pro' ); ?></span>
                    <select name="plugin_slug">
                        <?php foreach ( $supported_plugins as $slug => $config ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $config['label'] . ' (' . $config['prefix'] . 'XXXX-XXXX-XXXX)' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span style="display:block;font-weight:600;margin-bottom:4px;"><?php echo esc_html__( 'Customer Email', 'intenttarget-pro' ); ?></span>
                    <input type="email" name="activation_email" class="regular-text" placeholder="customer@example.com" />
                </label>
                <button type="submit" class="button button-primary"><?php echo esc_html__( 'Generate Licence Code', 'intenttarget-pro' ); ?></button>
            </form>
        </div>

        <form method="get" action="" style="margin-bottom:14px;display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="page" value="intenttarget-master-hub" />
            <label for="itp-filter-plugin-slug" style="font-weight:600;"><?php echo esc_html__( 'Filter by product:', 'intenttarget-pro' ); ?></label>
            <select id="itp-filter-plugin-slug" name="plugin_slug">
                <option value=""><?php echo esc_html__( 'All products', 'intenttarget-pro' ); ?></option>
                <?php foreach ( $supported_plugins as $slug => $config ) : ?>
                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filter_slug, $slug ); ?>><?php echo esc_html( $config['label'] ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button"><?php echo esc_html__( 'Apply', 'intenttarget-pro' ); ?></button>
        </form>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Licence Code', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Product', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Activation Email', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Mapped Domain', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Client URL', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Status', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Activations', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Last Seen', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Action', 'intenttarget-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $licences ) ) : ?>
                    <tr>
                        <td colspan="9"><?php echo esc_html__( 'No licence activations have been logged yet.', 'intenttarget-pro' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $licences as $licence ) :
                        $row_slug   = ! empty( $licence->plugin_slug ) ? $licence->plugin_slug : 'core';
                        $row_label  = isset( $supported_plugins[ $row_slug ]['label'] ) ? $supported_plugins[ $row_slug ]['label'] : ucfirst( $row_slug );
                    ?>
                        <tr>
                            <td><code><?php echo esc_html( $licence->licence_code ); ?></code></td>
                            <td><strong><?php echo esc_html( $row_label ); ?></strong></td>
                            <td><?php echo esc_html( $licence->activation_email ); ?></td>
                            <td><?php echo esc_html( $licence->mapped_domain ); ?></td>
                            <td><?php echo esc_html( $licence->client_url ); ?></td>
                            <td><strong><?php echo esc_html( ucfirst( $licence->status ) ); ?></strong></td>
                            <td><?php echo esc_html( (string) $licence->activation_count ); ?></td>
                            <td><?php echo esc_html( $licence->last_seen_at ?: 'Not recorded' ); ?></td>
                            <td>
                                <?php if ( $licence->status !== 'deactivated' ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <input type="hidden" name="action" value="itp_deactivate_licence" />
                                        <input type="hidden" name="licence_id" value="<?php echo esc_attr( (string) $licence->id ); ?>" />
                                        <?php wp_nonce_field( 'itp_deactivate_licence_' . $licence->id, 'itp_deactivate_nonce' ); ?>
                                        <button type="submit" class="button button-secondary"><?php echo esc_html__( 'Deactivate', 'intenttarget-pro' ); ?></button>
                                    </form>
                                <?php else : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="itp_reactivate_licence" />
        <input type="hidden" name="licence_id" value="<?php echo esc_attr( (string) $licence->id ); ?>" />
        <?php wp_nonce_field( 'itp_reactivate_licence_' . $licence->id, 'itp_reactivate_nonce' ); ?>
        <button type="submit" class="button button-primary"><?php echo esc_html__( 'Reactivate', 'intenttarget-pro' ); ?></button>
    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function lee_dev_process_master_hub_code_creation_6274() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not authorised to create licence codes.', 'intenttarget-pro' ) );
    }

    check_admin_referer( 'itp_create_licence_code', 'itp_create_licence_nonce' );

    $requested_slug          = isset( $_POST['plugin_slug'] ) ? sanitize_key( wp_unslash( $_POST['plugin_slug'] ) ) : 'core';
    list( $plugin_slug, )    = lee_dev_resolve_master_hub_plugin_slug_7164( $requested_slug );
    $activation_email        = isset( $_POST['activation_email'] ) ? sanitize_email( wp_unslash( $_POST['activation_email'] ) ) : '';

    global $wpdb;
    $table_name   = lee_dev_get_master_hub_table_name_4826();
    $licence_code = lee_dev_generate_activation_code_4197( $plugin_slug );

    $wpdb->insert(
        $table_name,
        array(
            'licence_code'     => $licence_code,
            'plugin_slug'      => $plugin_slug,
            'activation_email' => $activation_email,
            'status'           => 'pending',
            'created_at'       => current_time( 'mysql' ),
        ),
        array( '%s', '%s', '%s', '%s', '%s' )
    );

    wp_safe_redirect( add_query_arg( array(
        'page'        => 'intenttarget-master-hub',
        'itp-status'  => 'created',
        'plugin_slug' => $plugin_slug,
    ), admin_url( 'admin.php' ) ) );
    exit;
}

/**
 * Registers the highly secure licence activation endpoint inside the Master Hub.
 * @return void
 */
function lee_dev_register_secure_activation_endpoint_5521() {
    register_rest_route(
        'intenttarget-hub/v1',
        '/activate',
        array(
            'methods'             => 'POST',
            'callback'            => 'lee_dev_process_secure_activation_3991',
            'permission_callback' => 'lee_dev_verify_incoming_request_origin_1192',
        )
    );
}
add_action( 'rest_api_init', 'lee_dev_register_secure_activation_endpoint_5521' );

/**
 * Enforces rigid origin validation to block requests from outside real WordPress client sites.
 * @param WP_REST_Request $request Incoming API stream data.
 * @return bool|WP_Error Returns true if authorised, WP_Error if malicious.
 */
function lee_dev_verify_incoming_request_origin_1192( $request ) {
    // We require a custom header sent ONLY by our official client plugin
    $client_signature = $request->get_header( 'x_intenttarget_client_auth' );
    
    // If the header is missing or incorrect, it's a direct browser or malicious ping. Reject instantly.
    if ( empty( $client_signature ) || $client_signature !== 'ITP_SECURE_CLIENT_HANDSHAKE_2026' ) {
        return new WP_Error( 'rest_forbidden', 'Unauthorised origin detected. Access blocked.', array( 'status' => 401 ) );
    }
    
    return true; // Authorised WordPress client
}

/**
 * Processes the activation payload strictly to update an existing code.
 * @param WP_REST_Request $request Incoming API stream data.
 * @return WP_REST_Response
 */
function lee_dev_process_secure_activation_3991( $request ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'intenttarget_licenses';
    
    // Sanitise the incoming JSON payload from the client site
    $activation_code  = sanitize_text_field( $request->get_param( 'activation_code' ) );
    $activated_domain = sanitize_url( $request->get_param( 'domain' ) );
    
    if ( empty( $activation_code ) || empty( $activated_domain ) ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'Incomplete activation payload.' ), 400 );
    }
    
    // Check if the code exists in our Master Hub database
    $license = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE activation_code = %s", $activation_code ) );
    
    if ( ! $license ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'Invalid activation code.' ), 404 );
    }
    
    // Security Check: Has an admin manually killed this code?
    if ( $license->status === 'deactivated' ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'This licence has been revoked.', 'lock_client' => true ), 403 );
    }
    
    // Security Check: Is it already used by someone else?
    if ( $license->status === 'active' && $license->activated_domain !== $activated_domain ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'Code already active on another domain.' ), 409 );
    }
    
    // All checks passed! Update the database to lock the code to this specific domain.
    $wpdb->update(
        $table_name,
        array(
            'status'           => 'active',
            'activated_domain' => $activated_domain,
            'activated_at'     => current_time( 'mysql' )
        ),
        array( 'id' => $license->id )
    );
    
    // Return a successful response and a cryptographically signed auth token back to the client
    return new WP_REST_Response( array( 
        'success'     => true, 
        'message'     => 'Licence successfully activated.',
        'auth_token'  => wp_hash( $activation_code . $activated_domain ) 
    ), 200 );
}

function lee_dev_process_master_hub_deactivation_7634() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not authorised to deactivate licences.', 'intenttarget-pro' ) );
    }

    $licence_id = isset( $_POST['licence_id'] ) ? absint( $_POST['licence_id'] ) : 0;
    check_admin_referer( 'itp_deactivate_licence_' . $licence_id, 'itp_deactivate_nonce' );

    global $wpdb;
    $table_name = lee_dev_get_master_hub_table_name_4826();
    $updated    = $wpdb->update(
        $table_name,
        array(
            'status'         => 'deactivated',
            'deactivated_at' => current_time( 'mysql' ),
        ),
        array( 'id' => $licence_id ),
        array( '%s', '%s' ),
        array( '%d' )
    );

    $status = $updated ? 'deactivated' : 'missing';
    wp_safe_redirect( add_query_arg( array(
        'page'       => 'intenttarget-master-hub',
        'itp-status' => $status,
    ), admin_url( 'admin.php' ) ) );
    exit;
}

function lee_dev_register_master_hub_api_endpoints_9381() {
    register_rest_route( 'intenttarget-hub/v1', '/activate', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'lee_dev_handle_remote_activation_request_6842',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'intenttarget/v1', '/verify', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'lee_dev_handle_licence_verification_request_1842',
        'permission_callback' => '__return_true',
    ) );
}

function lee_dev_handle_remote_activation_request_6842( WP_REST_Request $request ) {
    global $wpdb;

    $params           = $request->get_params();
    $raw_code         = ! empty( $params['licence_code'] ) ? $params['licence_code'] : ( ! empty( $params['licence_key'] ) ? $params['licence_key'] : '' );
    $licence_code     = sanitize_text_field( $raw_code );
    $activation_email = sanitize_email( $params['activation_email'] ?? $params['email'] ?? '' );
    $mapped_domain    = sanitize_text_field( $params['domain'] ?? '' );
    $client_url       = esc_url_raw( $params['client_url'] ?? $params['site_url'] ?? $mapped_domain );
    $request_ip       = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

    list( $requested_slug, $requested_config ) = lee_dev_resolve_master_hub_plugin_slug_7164( $params['plugin_slug'] ?? 'core' );
    $expected_prefix                           = isset( $requested_config['prefix'] ) ? $requested_config['prefix'] : 'ITP-';

    if ( empty( $activation_email ) || empty( $mapped_domain ) || empty( $client_url ) ) {
        return new WP_REST_Response( array(
            'success' => false,
            'status'  => 'unauthorised',
            'message' => 'Activation email, domain, and client URL are required.',
        ), 400 );
    }

    if ( empty( $licence_code ) ) {
        return new WP_REST_Response( array(
            'success' => false,
            'status'  => 'unauthorised',
            'message' => 'A valid paid licence code is required for activation.',
        ), 400 );
    }

    if ( strpos( $licence_code, $expected_prefix ) !== 0 ) {
        return new WP_REST_Response( array(
            'success'     => false,
            'status'      => 'unauthorised',
            'plugin_slug' => $requested_slug,
            'message'     => sprintf( 'Licence code must use the %s activation prefix for this product.', $expected_prefix ),
        ), 403 );
    }

    $table_name = lee_dev_get_master_hub_table_name_4826();
    $existing   = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $table_name WHERE licence_code = %s LIMIT 1",
        $licence_code
    ) );

    // 1. Check if the code actually exists
    if ( ! $existing ) {
        return new WP_REST_Response( array(
            'success'      => false,
            'status'       => 'unauthorised',
            'licence_code' => $licence_code,
            'plugin_slug'  => $requested_slug,
            'message'      => 'This licence code was not issued by the master hub.',
        ), 403 );
    }

    // 1b. Cross-product mismatch: a Core licence cannot activate Pro, and vice versa.
    $existing_slug = ! empty( $existing->plugin_slug ) ? $existing->plugin_slug : 'core';
    if ( $existing_slug !== $requested_slug ) {
        return new WP_REST_Response( array(
            'success'         => false,
            'status'          => 'unauthorised',
            'licence_code'    => $licence_code,
            'plugin_slug'     => $requested_slug,
            'issued_for_slug' => $existing_slug,
            'message'         => 'This licence is issued for a different IntentTarget product and cannot activate this plugin.',
        ), 403 );
    }

    // 2. Check if an admin manually deactivated this code
    if ( $existing && $existing->status === 'deactivated' ) {
        $wpdb->update(
            $table_name,
            array(
                'client_url'      => $client_url,
                'mapped_domain'   => $mapped_domain,
                'last_request_ip' => $request_ip,
                'last_seen_at'    => current_time( 'mysql' ),
            ),
            array( 'id' => (int) $existing->id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        return new WP_REST_Response( array(
            'success'      => false,
            'status'       => 'deactivated',
            'licence_code' => $licence_code,
            'plugin_slug'  => $existing_slug,
            'message'      => 'This licence has been deactivated by the master hub.',
        ), 403 );
    }

    // 3. DOMAIN LOCK CHECK: If the code is already active, ensure the domains match!
    if ( $existing->status === 'active' && ! empty( $existing->mapped_domain ) ) {
        
        // Strip protocols and www to ensure a clean match just in case
        $saved_clean_domain = str_replace( array('http://', 'https://', 'www.'), '', $existing->mapped_domain );
        $incoming_clean_domain = str_replace( array('http://', 'https://', 'www.'), '', $mapped_domain );

        if ( rtrim( $saved_clean_domain, '/' ) !== rtrim( $incoming_clean_domain, '/' ) ) {
            
            // Log the unauthorised attempt so you can see it in the dashboard, but DO NOT overwrite the mapped domain
            $wpdb->update(
                $table_name,
                array(
                    'last_request_ip' => $request_ip,
                    'last_seen_at'    => current_time( 'mysql' ),
                ),
                array( 'id' => (int) $existing->id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            return new WP_REST_Response( array(
                'success'      => false,
                'status'       => 'unauthorised',
                'licence_code' => $licence_code,
                'plugin_slug'  => $existing_slug,
                'message'      => 'This licence is already locked to a different domain. Please purchase a new licence or contact support to transfer it.',
            ), 403 );
        }
    }

    // 4. First time activation OR subsequent verification from the CORRECT domain
    $wpdb->update(
        $table_name,
        array(
            'activation_email' => $activation_email,
            'mapped_domain'    => $mapped_domain,
            'client_url'       => $client_url,
            'status'           => 'active',
            'activation_count' => (int) $existing->activation_count + 1,
            'last_request_ip'  => $request_ip,
            'activated_at'     => empty( $existing->activated_at ) ? current_time( 'mysql' ) : $existing->activated_at,
            'last_seen_at'     => current_time( 'mysql' ),
        ),
        array( 'id' => (int) $existing->id ),
        array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ),
        array( '%d' )
    );

    return new WP_REST_Response( array(
        'success'      => true,
        'status'       => 'active',
        'licence_code' => $licence_code,
        'plugin_slug'  => $existing_slug,
        'message'      => 'Licence activated successfully.',
    ), 200 );
}

function lee_dev_handle_licence_verification_request_1842( WP_REST_Request $request ) {
    $response = lee_dev_handle_remote_activation_request_6842( $request );
    $data     = $response->get_data();

    if ( isset( $data['status'] ) && $data['status'] === 'active' ) {
        $data['status']  = 'authorised';
        $data['message'] = 'Licence is active and authorised.';
    }

    return new WP_REST_Response( $data, $response->get_status() );
}

function lee_dev_process_master_hub_reactivation_8821() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not authorised to reactivate licences.', 'intenttarget-pro' ) );
    }

    $licence_id = isset( $_POST['licence_id'] ) ? absint( $_POST['licence_id'] ) : 0;
    check_admin_referer( 'itp_reactivate_licence_' . $licence_id, 'itp_reactivate_nonce' );

    global $wpdb;
    $table_name = lee_dev_get_master_hub_table_name_4826();
    
    // Check if the licence has a mapped domain to determine correct restored status
    $existing = $wpdb->get_row( $wpdb->prepare( "SELECT mapped_domain FROM $table_name WHERE id = %d", $licence_id ) );
    
    if ( $existing ) {
        $new_status = ! empty( $existing->mapped_domain ) ? 'active' : 'pending';
        
        $updated = $wpdb->update(
            $table_name,
            array(
                'status'         => $new_status,
                'deactivated_at' => null // Clear the deactivation timestamp
            ),
            array( 'id' => $licence_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
        $status = $updated ? 'reactivated' : 'missing';
    } else {
        $status = 'missing';
    }

    wp_safe_redirect( add_query_arg( array(
        'page'       => 'intenttarget-master-hub',
        'itp-status' => $status,
    ), admin_url( 'admin.php' ) ) );
    exit;
}