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

namespace IntentTarget\MasterHub;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Config
 * Central configuration and utility methods.
 */
class Config {
    
    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'intenttarget_licenses';
    }

    public static function get_supported_plugins(): array {
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

        return apply_filters( 'intenttarget_master_hub_supported_plugins', $supported );
    }

    public static function resolve_plugin_slug( $plugin_slug ): array {
        $supported = self::get_supported_plugins();
        $slug      = sanitize_key( (string) $plugin_slug );

        if ( ! isset( $supported[ $slug ] ) ) {
            $slug = 'core';
        }

        return array( $slug, $supported[ $slug ] );
    }

    public static function generate_activation_code( $plugin_slug = 'core' ): string {
        list( , $config ) = self::resolve_plugin_slug( $plugin_slug );
        $prefix           = isset( $config['prefix'] ) ? $config['prefix'] : 'ITP-';

        global $wpdb;
        $table_name = self::get_table_name();
        
        do {
            $code = $prefix . strtoupper( wp_generate_password( 4, false, false ) ) . '-' . strtoupper( wp_generate_password( 4, false, false ) ) . '-' . strtoupper( wp_generate_password( 4, false, false ) );
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE licence_code = %s LIMIT 1",
                $code
            ) );
        } while ( ! empty( $exists ) );

        return $code;
    }
}

/**
 * Class Database
 * Handles database creation and updates.
 */
class Database {
    
    public static function install_tables() {
        global $wpdb;

        $table_name      = Config::get_table_name();
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
            expires_at datetime DEFAULT NULL,
            deactivated_at datetime DEFAULT NULL,
            last_seen_at datetime DEFAULT NULL,
            feedback_reason varchar(100) DEFAULT NULL,
            feedback_text text DEFAULT NULL,
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
}

/**
 * Class Admin
 * Handles dashboard rendering and form submissions.
 */
class Admin {

    public function init() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_post_itp_create_licence', [ $this, 'process_code_creation' ] );
        add_action( 'admin_post_itp_deactivate_licence', [ $this, 'process_deactivation' ] );
        add_action( 'admin_post_itp_reactivate_licence', [ $this, 'process_reactivation' ] );
    }

    public function register_menu() {
        add_menu_page(
            'IntentTarget Master Hub',
            'IntentTarget Hub',
            'manage_options',
            'intenttarget-master-hub',
            [ $this, 'render_dashboard' ],
            'dashicons-shield-alt',
            58
        );
    }

    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not authorised to view this licence hub.', 'intenttarget-pro' ) );
        }

        global $wpdb;
        $table_name = Config::get_table_name();

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

        $supported_plugins = Config::get_supported_plugins();
        $filter_slug       = isset( $_GET['plugin_slug'] ) ? sanitize_key( wp_unslash( $_GET['plugin_slug'] ) ) : '';
        
        if ( $filter_slug !== '' && ! isset( $supported_plugins[ $filter_slug ] ) ) {
            $filter_slug = '';
        }

        if ( $filter_slug !== '' ) {
            $licences = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $table_name WHERE plugin_slug = %s ORDER BY last_seen_at DESC, created_at DESC LIMIT 200",
                $filter_slug
            ) );
        } else {
            $licences = $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY last_seen_at DESC, created_at DESC LIMIT 200"
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
                        <th><?php echo esc_html__( 'Expires', 'intenttarget-pro' ); ?></th>
                        <th><?php echo esc_html__( 'Activations', 'intenttarget-pro' ); ?></th>
                        <th><?php echo esc_html__( 'Last Seen', 'intenttarget-pro' ); ?></th>
                        <th><?php echo esc_html__( 'Feedback', 'intenttarget-pro' ); ?></th>
                        <th><?php echo esc_html__( 'Action', 'intenttarget-pro' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $licences ) ) : ?>
                        <tr>
                            <td colspan="10"><?php echo esc_html__( 'No licence activations have been logged yet.', 'intenttarget-pro' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $licences as $licence ) :
                            $row_slug  = ! empty( $licence->plugin_slug ) ? $licence->plugin_slug : 'core';
                            $row_label = isset( $supported_plugins[ $row_slug ]['label'] ) ? $supported_plugins[ $row_slug ]['label'] : ucfirst( $row_slug );
                        ?>
                            <tr>
                                <td><code><?php echo esc_html( $licence->licence_code ); ?></code></td>
                                <td><strong><?php echo esc_html( $row_label ); ?></strong></td>
                                <td><?php echo esc_html( $licence->activation_email ); ?></td>
                                <td><?php echo esc_html( $licence->mapped_domain ); ?></td>
                                <td><?php echo esc_html( $licence->client_url ); ?></td>
                                <td><strong><?php echo esc_html( ucfirst( $licence->status ) ); ?></strong></td>
                                <td>
                                    <?php 
                                        if ( ! empty( $licence->expires_at ) ) {
                                            $is_expired = ( strtotime( $licence->expires_at ) < current_time( 'timestamp' ) );
                                            echo '<span style="color:' . ( $is_expired ? '#d63638' : '#00a32a' ) . ';">' . esc_html( date( 'j M Y', strtotime( $licence->expires_at ) ) ) . '</span>';
                                        } else {
                                            echo '<span style="color:#a7aaad;">-</span>';
                                        }
                                    ?>
                                </td>
                                <td><?php echo esc_html( (string) $licence->activation_count ); ?></td>
                                <td><?php echo esc_html( $licence->last_seen_at ?: 'Not recorded' ); ?></td>
                                <td style="max-width: 150px; font-size: 12px; line-height: 1.4;">
                                    <?php if ( ! empty( $licence->feedback_reason ) ) : ?>
                                        <strong style="display:block;"><?php echo esc_html( $licence->feedback_reason ); ?></strong>
                                        <?php if ( ! empty( $licence->feedback_text ) ) : ?>
                                            <span style="color:#646970;"><?php echo esc_html( wp_trim_words( $licence->feedback_text, 10, '...' ) ); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#a7aaad;">-</span>
                                    <?php endif; ?>
                                </td>
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

    public function process_code_creation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not authorised to create licence codes.', 'intenttarget-pro' ) );
        }

        check_admin_referer( 'itp_create_licence_code', 'itp_create_licence_nonce' );

        $requested_slug         = isset( $_POST['plugin_slug'] ) ? sanitize_key( wp_unslash( $_POST['plugin_slug'] ) ) : 'core';
        list( $plugin_slug, )   = Config::resolve_plugin_slug( $requested_slug );
        $activation_email       = isset( $_POST['activation_email'] ) ? sanitize_email( wp_unslash( $_POST['activation_email'] ) ) : '';

        global $wpdb;
        $table_name   = Config::get_table_name();
        $licence_code = Config::generate_activation_code( $plugin_slug );

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

    public function process_deactivation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not authorised to deactivate licences.', 'intenttarget-pro' ) );
        }

        $licence_id = isset( $_POST['licence_id'] ) ? absint( $_POST['licence_id'] ) : 0;
        check_admin_referer( 'itp_deactivate_licence_' . $licence_id, 'itp_deactivate_nonce' );

        global $wpdb;
        $table_name = Config::get_table_name();
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

    public function process_reactivation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not authorised to reactivate licences.', 'intenttarget-pro' ) );
        }

        $licence_id = isset( $_POST['licence_id'] ) ? absint( $_POST['licence_id'] ) : 0;
        check_admin_referer( 'itp_reactivate_licence_' . $licence_id, 'itp_reactivate_nonce' );

        global $wpdb;
        $table_name = Config::get_table_name();
        
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT mapped_domain FROM $table_name WHERE id = %d", $licence_id ) );
        
        if ( $existing ) {
            $new_status = ! empty( $existing->mapped_domain ) ? 'active' : 'pending';
            
            $updated = $wpdb->update(
                $table_name,
                array(
                    'status'         => $new_status,
                    'deactivated_at' => null
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
}

/**
 * Class API
 * Handles REST API endpoints for secure and standard activations.
 */
class API {

    public function init() {
        add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
    }

    public function register_endpoints() {
        // Secure Handshake Endpoint
        register_rest_route(
            'intenttarget-hub/v1',
            '/activate',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'process_secure_activation' ],
                'permission_callback' => [ $this, 'verify_incoming_request_origin' ],
            )
        );

        // Standard Verify Endpoint
        register_rest_route( 'intenttarget/v1', '/verify', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_licence_verification_request' ],
            'permission_callback' => '__return_true',
        ) );

        // Remote Release/Uninstall Endpoint
        register_rest_route( 'intenttarget/v1', '/release', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_remote_release_request' ],
            'permission_callback' => '__return_true',
        ) );

        // Deactivation Feedback Endpoint
        register_rest_route( 'intenttarget/v1', '/feedback', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_feedback_request' ],
            'permission_callback' => '__return_true',
        ) );
    }

    public function verify_incoming_request_origin( \WP_REST_Request $request ) {
        $client_signature = $request->get_header( 'x_intenttarget_client_auth' );
        
        // Fallback to body param — custom headers are often stripped by proxies/CDNs on live hosts.
        if ( empty( $client_signature ) ) {
            $client_signature = sanitize_text_field( $request->get_param( 'client_auth' ) ?? '' );
        }
        
        if ( empty( $client_signature ) || $client_signature !== 'ITP_SECURE_CLIENT_HANDSHAKE_2026' ) {
            return new \WP_Error( 'rest_forbidden', 'Unauthorised origin detected. Access blocked.', array( 'status' => 401 ) );
        }
        
        return true; 
    }

    public function process_secure_activation( \WP_REST_Request $request ) {
        // If the request contains legacy params OR the new standard params, route it to the handler
        if ( $request->get_param('plugin_slug') || $request->get_param('email') || $request->get_param('activation_email') ) {
             return $this->handle_remote_activation_request( $request );
        }

        global $wpdb;
        $table_name = Config::get_table_name();
        
        $activation_code  = sanitize_text_field( $request->get_param( 'activation_code' ) );
        $activated_domain = sanitize_url( $request->get_param( 'domain' ) );
        
        if ( empty( $activation_code ) || empty( $activated_domain ) ) {
            return new \WP_REST_Response( array( 'success' => false, 'message' => 'Incomplete activation payload.' ), 400 );
        }
        
        $license = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE licence_code = %s", $activation_code ) );
        
        if ( ! $license ) {
            return new \WP_REST_Response( array( 'success' => false, 'message' => 'Invalid activation code.' ), 404 );
        }
        
        if ( $license->status === 'deactivated' ) {
            $saved_clean_domain    = str_replace( array('http://', 'https://', 'www.'), '', $license->mapped_domain );
            $incoming_clean_domain = str_replace( array('http://', 'https://', 'www.'), '', $activated_domain );

            if ( ! empty( $license->mapped_domain ) && rtrim( $saved_clean_domain, '/' ) === rtrim( $incoming_clean_domain, '/' ) ) {
                // Allow to proceed
            } else {
                return new \WP_REST_Response( array( 'success' => false, 'message' => 'This licence has been revoked or is bound to another domain.', 'lock_client' => true ), 403 );
            }
        }
        
        if ( $license->status === 'active' && $license->mapped_domain !== $activated_domain ) {
            $saved_clean_domain    = str_replace( array('http://', 'https://', 'www.'), '', $license->mapped_domain );
            $incoming_clean_domain = str_replace( array('http://', 'https://', 'www.'), '', $activated_domain );
            
            if ( rtrim( $saved_clean_domain, '/' ) !== rtrim( $incoming_clean_domain, '/' ) ) {
                return new \WP_REST_Response( array( 'success' => false, 'message' => 'Code already active on another domain.' ), 409 );
            }
        }

        $now        = current_time( 'mysql' );
        $expires_at = empty( $license->expires_at ) ? gmdate( 'Y-m-d H:i:s', strtotime( '+1 year', current_time( 'timestamp' ) ) ) : $license->expires_at;
        
        $wpdb->update(
            $table_name,
            array(
                'status'        => 'active',
                'mapped_domain' => $activated_domain,
                'activated_at'  => empty( $license->activated_at ) ? $now : $license->activated_at,
                'expires_at'    => $expires_at,
                'last_seen_at'  => $now
            ),
            array( 'id' => $license->id )
        );
        
        return new \WP_REST_Response( array( 
            'success'    => true, 
            'message'    => 'Licence successfully activated.',
            'auth_token' => wp_hash( $activation_code . $activated_domain ) 
        ), 200 );
    }

    public function handle_remote_activation_request( \WP_REST_Request $request ) {
        global $wpdb;

        $params           = $request->get_params();
        $raw_code         = ! empty( $params['licence_code'] ) ? $params['licence_code'] : ( ! empty( $params['licence_key'] ) ? $params['licence_key'] : '' );
        $licence_code     = sanitize_text_field( $raw_code );
        $activation_email = sanitize_email( $params['activation_email'] ?? $params['email'] ?? '' );
        $mapped_domain    = sanitize_text_field( $params['domain'] ?? '' );
        $client_url       = esc_url_raw( $params['client_url'] ?? $params['site_url'] ?? $mapped_domain );
        $request_ip       = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

        list( $requested_slug, $requested_config ) = Config::resolve_plugin_slug( $params['plugin_slug'] ?? 'core' );
        $expected_prefix                           = isset( $requested_config['prefix'] ) ? $requested_config['prefix'] : 'ITP-';

        if ( empty( $activation_email ) || empty( $mapped_domain ) || empty( $client_url ) ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'status'  => 'unauthorised',
                'message' => 'Activation email, domain, and client URL are required.',
            ), 400 );
        }

        if ( empty( $licence_code ) ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'status'  => 'unauthorised',
                'message' => 'A valid paid licence code is required for activation.',
            ), 400 );
        }

        if ( strpos( $licence_code, $expected_prefix ) !== 0 ) {
            return new \WP_REST_Response( array(
                'success'     => false,
                'status'      => 'unauthorised',
                'plugin_slug' => $requested_slug,
                'message'     => sprintf( 'Licence code must use the %s activation prefix for this product.', $expected_prefix ),
            ), 403 );
        }

        $table_name = Config::get_table_name();
        $existing   = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE licence_code = %s LIMIT 1",
            $licence_code
        ) );

        if ( ! $existing ) {
            return new \WP_REST_Response( array(
                'success'      => false,
                'status'       => 'unauthorised',
                'licence_code' => $licence_code,
                'plugin_slug'  => $requested_slug,
                'message'      => 'This licence code was not issued by the master hub.',
            ), 403 );
        }

        $existing_slug = ! empty( $existing->plugin_slug ) ? $existing->plugin_slug : 'core';
        if ( $existing_slug !== $requested_slug ) {
            return new \WP_REST_Response( array(
                'success'         => false,
                'status'          => 'unauthorised',
                'licence_code'    => $licence_code,
                'plugin_slug'     => $requested_slug,
                'issued_for_slug' => $existing_slug,
                'message'         => 'This licence is issued for a different IntentTarget product and cannot activate this plugin.',
            ), 403 );
        }

        if ( $existing && $existing->status === 'deactivated' ) {
            $saved_clean_domain    = str_replace( array('http://', 'https://', 'www.'), '', $existing->mapped_domain );
            $incoming_clean_domain = str_replace( array('http://', 'https://', 'www.'), '', $mapped_domain );

            if ( ! empty( $existing->mapped_domain ) && rtrim( $saved_clean_domain, '/' ) === rtrim( $incoming_clean_domain, '/' ) ) {
                // Allow reactivation because it matches the original domain!
            } else {
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

                return new \WP_REST_Response( array(
                    'success'      => false,
                    'status'       => 'deactivated',
                    'licence_code' => $licence_code,
                    'plugin_slug'  => $existing_slug,
                    'message'      => 'This licence has been deactivated by the master hub and cannot be used on a new domain.',
                ), 403 );
            }
        }

        if ( $existing && $existing->status === 'active' && ! empty( $existing->mapped_domain ) ) {
            $saved_clean_domain    = str_replace( array('http://', 'https://', 'www.'), '', $existing->mapped_domain );
            $incoming_clean_domain = str_replace( array('http://', 'https://', 'www.'), '', $mapped_domain );

            if ( rtrim( $saved_clean_domain, '/' ) !== rtrim( $incoming_clean_domain, '/' ) ) {
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

                return new \WP_REST_Response( array(
                    'success'      => false,
                    'status'       => 'unauthorised',
                    'licence_code' => $licence_code,
                    'plugin_slug'  => $existing_slug,
                    'message'      => 'This licence is already locked to a different domain. Please purchase a new licence or contact support to transfer it.',
                ), 403 );
            }
        }

        $now        = current_time( 'mysql' );
        $expires_at = empty( $existing->expires_at ) ? gmdate( 'Y-m-d H:i:s', strtotime( '+1 year', current_time( 'timestamp' ) ) ) : $existing->expires_at;

        $wpdb->update(
            $table_name,
            array(
                'activation_email' => $activation_email,
                'mapped_domain'    => $mapped_domain,
                'client_url'       => $client_url,
                'status'           => 'active',
                'activation_count' => (int) $existing->activation_count + 1,
                'last_request_ip'  => $request_ip,
                'activated_at'     => empty( $existing->activated_at ) ? $now : $existing->activated_at,
                'expires_at'       => $expires_at,
                'last_seen_at'     => $now,
            ),
            array( 'id' => (int) $existing->id ),
            array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        return new \WP_REST_Response( array(
            'success'      => true,
            'status'       => 'active',
            'licence_code' => $licence_code,
            'plugin_slug'  => $existing_slug,
            'message'      => 'Licence activated successfully.',
        ), 200 );
    }

    public function handle_licence_verification_request( \WP_REST_Request $request ) {
        $response = $this->handle_remote_activation_request( $request );
        $data     = $response->get_data();

        if ( isset( $data['status'] ) && $data['status'] === 'active' ) {
            $data['status']  = 'authorised';
            $data['message'] = 'Licence is active and authorised.';
        }

        return new \WP_REST_Response( $data, $response->get_status() );
    }

    public function handle_remote_release_request( \WP_REST_Request $request ) {
        global $wpdb;
        $params       = $request->get_params();
        $licence_code = sanitize_text_field( $params['licence_code'] ?? '' );
        
        if ( empty( $licence_code ) ) {
            return new \WP_REST_Response( array( 'success' => false, 'message' => 'Missing licence code.' ), 400 );
        }

        $table_name = Config::get_table_name();
        $existing   = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status FROM $table_name WHERE licence_code = %s LIMIT 1",
            $licence_code
        ) );

        if ( ! $existing ) {
            return new \WP_REST_Response( array( 'success' => false, 'message' => 'Licence not found.' ), 404 );
        }

        // Only release if it's currently active so we don't overwrite a 'deactivated' revoked state.
        if ( $existing->status === 'active' ) {
            $wpdb->update(
                $table_name,
                array(
                    'status'         => 'deactivated',
                    'deactivated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => (int) $existing->id ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        }

        return new \WP_REST_Response( array( 'success' => true, 'message' => 'Licence deactivated successfully.' ), 200 );
    }

    public function handle_feedback_request( \WP_REST_Request $request ) {
        global $wpdb;
        $params       = $request->get_params();
        $licence_code = sanitize_text_field( $params['licence_code'] ?? '' );
        $reason       = sanitize_text_field( $params['reason'] ?? '' );
        $details      = sanitize_textarea_field( $params['details'] ?? '' );
        
        if ( empty( $licence_code ) ) {
            return new \WP_REST_Response( array( 'success' => false, 'message' => 'Missing licence code.' ), 400 );
        }

        $table_name = Config::get_table_name();
        $existing   = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $table_name WHERE licence_code = %s LIMIT 1",
            $licence_code
        ) );

        if ( $existing ) {
            // Check if columns exist first (since we just added them to dbDelta)
            // dbDelta might not have run if plugin isn't reactivated. So we fail gracefully.
            $wpdb->suppress_errors = true;
            $wpdb->update(
                $table_name,
                array(
                    'status'          => 'deactivated',
                    'deactivated_at'  => current_time( 'mysql' ),
                    'feedback_reason' => $reason,
                    'feedback_text'   => $details,
                ),
                array( 'id' => (int) $existing->id ),
                array( '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
            $wpdb->suppress_errors = false;
        }

        return new \WP_REST_Response( array( 'success' => true, 'message' => 'Feedback saved.' ), 200 );
    }
}

/**
 * Class Core
 * Bootstraps the plugin components.
 */
class Core {

    public function __construct() {
        register_activation_hook( __FILE__, [ Database::class, 'install_tables' ] );
        register_deactivation_hook( __FILE__, [ $this, 'clear_scheduled_hooks' ] );
        
        add_action( 'init', [ $this, 'init_components' ], 1 );
    }

    public function init_components() {
        // Table installation on init if needed (matching original code behavior)
        Database::install_tables();

        $admin = new Admin();
        $admin->init();

        $api = new API();
        $api->init();

        require_once plugin_dir_path( __FILE__ ) . 'class-intenttarget-cron.php';
        $cron = new Cron();
        $cron->init();
    }

    public function clear_scheduled_hooks() {
        wp_clear_scheduled_hook( 'itp_hub_daily_expiration_check' );
    }
}

// Instantiate the core plugin class
new Core();