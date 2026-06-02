<?php
/**
 * IntentTarget Pro — ROI Attribution & Analytics
 *
 * Captures advert clicks from the slide-in popup and the My Account dashboard recommendation,
 * then attributes downstream conversions (form submissions, WooCommerce purchases, link visits)
 * back to the originating advert so the admin can quantify the impact of Core intent targeting.
 *
 * All entry points are gated by lee_dev_is_addon_active_3812( 'pro' ) so the module stays
 * completely dormant on unlicensed installs (Master Hub up-sell rule).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =========================================================================
// 1. CONSTANTS & TABLE SCHEMA
// =========================================================================
if ( ! defined( 'LEE_DEV_PRO_ROI_TABLE_KEY' ) ) {
    define( 'LEE_DEV_PRO_ROI_TABLE_KEY', 'itp_pro_advert_attributions' );
}
if ( ! defined( 'LEE_DEV_PRO_ROI_SESSION_COOKIE' ) ) {
    define( 'LEE_DEV_PRO_ROI_SESSION_COOKIE', 'itp_pro_session' );
}
if ( ! defined( 'LEE_DEV_PRO_ROI_ATTRIBUTION_WINDOW_DAYS' ) ) {
    define( 'LEE_DEV_PRO_ROI_ATTRIBUTION_WINDOW_DAYS', 14 );
}

function lee_dev_pro_get_roi_table_name_4521() {
    global $wpdb;
    return $wpdb->prefix . LEE_DEV_PRO_ROI_TABLE_KEY;
}

function lee_dev_pro_install_roi_table_3956() {
    global $wpdb;
    $table_name      = lee_dev_pro_get_roi_table_name_4521();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        session_token varchar(64) NOT NULL DEFAULT '',
        intent varchar(40) NOT NULL DEFAULT '',
        advert_source varchar(40) NOT NULL DEFAULT '',
        advert_url varchar(255) NOT NULL DEFAULT '',
        advert_btn_text varchar(190) NOT NULL DEFAULT '',
        page_url varchar(255) NOT NULL DEFAULT '',
        clicked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        converted_at datetime DEFAULT NULL,
        conversion_type varchar(40) NOT NULL DEFAULT '',
        conversion_value decimal(12,2) NOT NULL DEFAULT 0.00,
        conversion_meta text NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY session_token (session_token),
        KEY intent (intent),
        KEY clicked_at (clicked_at),
        KEY converted_at (converted_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// Ensure the table exists on the first request after a Core/Pro update. Cheap: only fires dbDelta
// when the stored schema version is below the current target.
add_action( 'init', 'lee_dev_pro_ensure_roi_schema_5102', 5 );
function lee_dev_pro_ensure_roi_schema_5102() {
    $target_version = '1.0';
    if ( get_option( 'lee_dev_pro_roi_schema_version_5102' ) === $target_version ) {
        return;
    }
    lee_dev_pro_install_roi_table_3956();
    update_option( 'lee_dev_pro_roi_schema_version_5102', $target_version, false );
}

// =========================================================================
// 2. CONFIGURED INTENT URL MAP (for form-submission attribution)
// =========================================================================

/**
 * Returns an associative map of intent_slug => normalised landing path (lower-case, leading & trailing slash).
 * The paths come from the global advert catalogue so the admin's customisations are honoured automatically.
 */
function lee_dev_pro_get_intent_url_map_8401() {
    if ( ! function_exists( 'lee_dev_get_global_priority_advert_catalogue_7136' ) ) {
        return array();
    }

    $map       = array();
    $catalogue = lee_dev_get_global_priority_advert_catalogue_7136();
    foreach ( $catalogue as $intent_slug => $advert ) {
        if ( ! is_array( $advert ) || empty( $advert['url'] ) ) {
            continue;
        }
        $path = wp_parse_url( $advert['url'], PHP_URL_PATH );
        if ( ! is_string( $path ) || $path === '' ) {
            continue;
        }
        $path                   = '/' . trim( strtolower( $path ), '/' ) . '/';
        $map[ $intent_slug ]    = $path;
    }
    return $map;
}

/**
 * Intents whose click ITSELF qualifies as a conversion (no follow-up needed).
 */
function lee_dev_pro_get_click_conversion_intents_4710() {
    return apply_filters( 'lee_dev_pro_click_conversion_intents_4710', array(
        'educating_audiences',
        'customer_support',
    ) );
}

// =========================================================================
// 3. GUEST SESSION TOKEN (cookie-based attribution for logged-out visitors)
// =========================================================================
function lee_dev_pro_resolve_visitor_session_2845() {
    $user_id = get_current_user_id();
    if ( $user_id > 0 ) {
        return array( 'user_id' => $user_id, 'session_token' => '' );
    }

    $token = isset( $_COOKIE[ LEE_DEV_PRO_ROI_SESSION_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ LEE_DEV_PRO_ROI_SESSION_COOKIE ] ) ) : '';
    if ( ! preg_match( '/^[a-f0-9]{32,64}$/', $token ) ) {
        $token = bin2hex( random_bytes( 32 ) );
        if ( ! headers_sent() ) {
            setcookie( LEE_DEV_PRO_ROI_SESSION_COOKIE, $token, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
        }
        $_COOKIE[ LEE_DEV_PRO_ROI_SESSION_COOKIE ] = $token;
    }

    return array( 'user_id' => 0, 'session_token' => $token );
}

// =========================================================================
// 4. AJAX CLICK CAPTURE ENDPOINT (Pro-gated)
// =========================================================================
add_action( 'wp_ajax_itp_pro_track_advert_click', 'lee_dev_pro_handle_track_click_ajax_6739' );
add_action( 'wp_ajax_nopriv_itp_pro_track_advert_click', 'lee_dev_pro_handle_track_click_ajax_6739' );

function lee_dev_pro_handle_track_click_ajax_6739() {
    if ( ! function_exists( 'lee_dev_is_addon_active_3812' ) || ! lee_dev_is_addon_active_3812( 'pro' ) ) {
        wp_send_json_success( array( 'gated' => true, 'tracked' => false ) );
    }

    if ( ! check_ajax_referer( 'itp_pro_track_click_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Invalid security token.' ), 403 );
    }

    $valid_intents = function_exists( 'lee_dev_get_valid_site_intents_6048' ) ? lee_dev_get_valid_site_intents_6048() : array();
    $intent        = isset( $_POST['intent'] ) ? sanitize_key( wp_unslash( $_POST['intent'] ) ) : '';
    $source        = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
    $advert_url    = isset( $_POST['advert_url'] ) ? esc_url_raw( wp_unslash( $_POST['advert_url'] ) ) : '';
    $btn_text      = isset( $_POST['btn_text'] ) ? sanitize_text_field( wp_unslash( $_POST['btn_text'] ) ) : '';
    $page_url      = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

    if ( $intent === '' || ! isset( $valid_intents[ $intent ] ) ) {
        wp_send_json_error( array( 'message' => 'Unknown intent slug.' ), 400 );
    }

    if ( ! in_array( $source, array( 'slidein', 'account_dashboard' ), true ) ) {
        $source = 'slidein';
    }

    $session = lee_dev_pro_resolve_visitor_session_2845();
    $row_id  = lee_dev_pro_record_advert_click_3197( array(
        'user_id'         => (int) $session['user_id'],
        'session_token'   => (string) $session['session_token'],
        'intent'          => $intent,
        'advert_source'   => $source,
        'advert_url'      => $advert_url,
        'advert_btn_text' => $btn_text,
        'page_url'        => $page_url,
    ) );

    wp_send_json_success( array(
        'tracked'         => true,
        'attribution_id'  => (int) $row_id,
        'instant_convert' => in_array( $intent, lee_dev_pro_get_click_conversion_intents_4710(), true ),
    ) );
}

/**
 * Persists a click attribution row. Auto-promotes intents whose click counts as a conversion.
 *
 * @param array $data
 * @return int Inserted row ID, or 0 on failure.
 */
function lee_dev_pro_record_advert_click_3197( $data ) {
    global $wpdb;
    $table_name = lee_dev_pro_get_roi_table_name_4521();
    $now        = current_time( 'mysql' );

    $row = array(
        'user_id'         => isset( $data['user_id'] ) ? (int) $data['user_id'] : 0,
        'session_token'   => isset( $data['session_token'] ) ? (string) $data['session_token'] : '',
        'intent'          => isset( $data['intent'] ) ? (string) $data['intent'] : '',
        'advert_source'   => isset( $data['advert_source'] ) ? (string) $data['advert_source'] : '',
        'advert_url'      => isset( $data['advert_url'] ) ? (string) $data['advert_url'] : '',
        'advert_btn_text' => isset( $data['advert_btn_text'] ) ? (string) $data['advert_btn_text'] : '',
        'page_url'        => isset( $data['page_url'] ) ? (string) $data['page_url'] : '',
        'clicked_at'      => $now,
        'converted_at'    => null,
        'conversion_type' => '',
        'conversion_value'=> 0,
        'conversion_meta' => '',
    );

    if ( in_array( $row['intent'], lee_dev_pro_get_click_conversion_intents_4710(), true ) ) {
        $row['converted_at']    = $now;
        $row['conversion_type'] = 'link_visit';
        $row['conversion_meta'] = wp_json_encode( array( 'auto_converted_on_click' => true ) );
    }

    $inserted = $wpdb->insert(
        $table_name,
        $row,
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s' )
    );

    if ( ! $inserted ) {
        return 0;
    }

    if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
        lee_dev_debug_log_event_6158( 'pro_roi.click_recorded', array(
            'attribution_id' => (int) $wpdb->insert_id,
            'intent'         => $row['intent'],
            'source'         => $row['advert_source'],
            'instant_convert'=> ( $row['converted_at'] !== null ),
        ) );
    }

    return (int) $wpdb->insert_id;
}

// =========================================================================
// 5. CONVERSION ATTRIBUTION — locate the pending click row to credit
// =========================================================================

/**
 * Find the most recent pending attribution row for a given user/session and intent within the
 * attribution window. Returns the row object or null when nothing pending exists.
 *
 * @param string $intent           Intent slug to credit.
 * @param int    $user_id          Logged-in user id, or 0 for guests.
 * @param string $session_token    Guest session token (used when user_id is 0).
 * @return object|null
 */
function lee_dev_pro_find_pending_attribution_5128( $intent, $user_id, $session_token = '' ) {
    global $wpdb;
    $table_name      = lee_dev_pro_get_roi_table_name_4521();
    $window_cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( '-' . (int) LEE_DEV_PRO_ROI_ATTRIBUTION_WINDOW_DAYS . ' days' ) );

    if ( $user_id > 0 ) {
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name
              WHERE user_id = %d AND intent = %s AND clicked_at >= %s
              ORDER BY clicked_at DESC LIMIT 1",
            $user_id,
            $intent,
            $window_cutoff
        ) );
    }

    if ( $session_token === '' ) {
        return null;
    }

    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $table_name
          WHERE session_token = %s AND intent = %s AND clicked_at >= %s
          ORDER BY clicked_at DESC LIMIT 1",
        $session_token,
        $intent,
        $window_cutoff
    ) );
}

/**
 * Mark a click row as converted. Will overwrite an existing 'link_visit' auto-conversion with a
 * richer conversion type (form_submission / purchase) so the analytics reflect deeper engagement.
 */
function lee_dev_pro_mark_attribution_converted_8264( $attribution_id, $conversion_type, $value = 0.0, $meta = array() ) {
    global $wpdb;
    $table_name = lee_dev_pro_get_roi_table_name_4521();

    $attribution_id = (int) $attribution_id;
    if ( $attribution_id <= 0 ) {
        return false;
    }

    $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, converted_at, conversion_type FROM $table_name WHERE id = %d LIMIT 1", $attribution_id ) );
    if ( ! $existing ) {
        return false;
    }

    // Refuse to downgrade a deeper conversion back to a weaker one.
    $rank = array( '' => 0, 'link_visit' => 1, 'form_submission' => 2, 'purchase' => 3 );
    $cur  = isset( $rank[ $existing->conversion_type ] ) ? $rank[ $existing->conversion_type ] : 0;
    $new  = isset( $rank[ $conversion_type ] ) ? $rank[ $conversion_type ] : 0;
    if ( $new < $cur ) {
        return false;
    }

    $wpdb->update(
        $table_name,
        array(
            'converted_at'     => current_time( 'mysql' ),
            'conversion_type'  => $conversion_type,
            'conversion_value' => (float) $value,
            'conversion_meta'  => wp_json_encode( is_array( $meta ) ? $meta : array() ),
        ),
        array( 'id' => $attribution_id ),
        array( '%s', '%s', '%f', '%s' ),
        array( '%d' )
    );

    if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
        lee_dev_debug_log_event_6158( 'pro_roi.conversion_recorded', array(
            'attribution_id'  => $attribution_id,
            'conversion_type' => $conversion_type,
            'conversion_value'=> (float) $value,
        ) );
    }
    return true;
}

// =========================================================================
// 6. WOOCOMMERCE PURCHASE → driving_sales CONVERSION
// =========================================================================
add_action( 'woocommerce_order_status_processing', 'lee_dev_pro_attribute_woocommerce_order_3057', 10, 2 );
add_action( 'woocommerce_order_status_completed', 'lee_dev_pro_attribute_woocommerce_order_3057', 10, 2 );

function lee_dev_pro_attribute_woocommerce_order_3057( $order_id, $order = null ) {
    if ( ! function_exists( 'lee_dev_is_addon_active_3812' ) || ! lee_dev_is_addon_active_3812( 'pro' ) ) {
        return;
    }
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    if ( ! $order instanceof WC_Order ) {
        $order = wc_get_order( $order_id );
    }
    if ( ! $order ) {
        return;
    }

    // Idempotency: skip if we already credited this order.
    if ( $order->get_meta( '_itp_pro_roi_credited', true ) ) {
        return;
    }

    $customer_id   = (int) $order->get_customer_id();
    $session_token = $order->get_meta( '_itp_pro_session_token', true );
    if ( $customer_id <= 0 && $session_token === '' ) {
        // Fall back to the live cookie for guest checkouts.
        $session_token = isset( $_COOKIE[ LEE_DEV_PRO_ROI_SESSION_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ LEE_DEV_PRO_ROI_SESSION_COOKIE ] ) ) : '';
    }

    $pending = lee_dev_pro_find_pending_attribution_5128( 'driving_sales', $customer_id, $session_token );
    if ( ! $pending ) {
        return;
    }

    lee_dev_pro_mark_attribution_converted_8264(
        (int) $pending->id,
        'purchase',
        (float) $order->get_total(),
        array(
            'order_id'       => (int) $order->get_id(),
            'order_currency' => $order->get_currency(),
            'order_status'   => $order->get_status(),
        )
    );

    $order->update_meta_data( '_itp_pro_roi_credited', (int) $pending->id );
    $order->save_meta_data();
}

// Tag the order at checkout so guests can be matched later when the cookie may have expired.
add_action( 'woocommerce_checkout_create_order', 'lee_dev_pro_attach_session_to_order_4912', 10, 2 );
function lee_dev_pro_attach_session_to_order_4912( $order, $data ) {
    if ( ! ( $order instanceof WC_Order ) ) {
        return;
    }
    $session_token = isset( $_COOKIE[ LEE_DEV_PRO_ROI_SESSION_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ LEE_DEV_PRO_ROI_SESSION_COOKIE ] ) ) : '';
    if ( $session_token !== '' ) {
        $order->update_meta_data( '_itp_pro_session_token', $session_token );
    }
}

// =========================================================================
// 7. FORM SUBMISSION → generating_leads & customer_support CONVERSION
// =========================================================================

/**
 * Centralised form-submission attribution helper. Looks up the latest pending click for the
 * provided intent slug and promotes its conversion_type to 'form_submission'.
 */
function lee_dev_pro_credit_form_submission_2683( $intent, $meta = array() ) {
    if ( ! function_exists( 'lee_dev_is_addon_active_3812' ) || ! lee_dev_is_addon_active_3812( 'pro' ) ) {
        return false;
    }

    $session       = lee_dev_pro_resolve_visitor_session_2845();
    $session_token = (string) $session['session_token'];
    $user_id       = (int) $session['user_id'];

    foreach ( array( $intent ) as $candidate_intent ) {
        $pending = lee_dev_pro_find_pending_attribution_5128( $candidate_intent, $user_id, $session_token );
        if ( $pending ) {
            return lee_dev_pro_mark_attribution_converted_8264(
                (int) $pending->id,
                'form_submission',
                0.0,
                array_merge( array( 'source' => 'form_hook' ), is_array( $meta ) ? $meta : array() )
            );
        }
    }
    return false;
}

/**
 * Generic POST detection on tracked intent landing pages. Runs on 'wp' so we know the resolved page.
 */
add_action( 'wp', 'lee_dev_pro_detect_form_submission_on_intent_page_6042', 20 );
function lee_dev_pro_detect_form_submission_on_intent_page_6042() {
    if ( ! function_exists( 'lee_dev_is_addon_active_3812' ) || ! lee_dev_is_addon_active_3812( 'pro' ) ) {
        return;
    }
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
        return;
    }
    if ( empty( $_SERVER['REQUEST_METHOD'] ) || strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'POST' ) {
        return;
    }

    $current_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
    if ( ! is_string( $current_path ) || $current_path === '' ) {
        return;
    }
    $normalised = '/' . trim( strtolower( $current_path ), '/' ) . '/';

    $map = lee_dev_pro_get_intent_url_map_8401();
    foreach ( $map as $intent_slug => $intent_path ) {
        if ( ! in_array( $intent_slug, array( 'generating_leads', 'customer_support' ), true ) ) {
            continue;
        }
        if ( $intent_path === $normalised ) {
            lee_dev_pro_credit_form_submission_2683( $intent_slug, array( 'detector' => 'generic_post', 'path' => $normalised ) );
            return;
        }
    }
}

// Contact Form 7
add_action( 'wpcf7_mail_sent', 'lee_dev_pro_credit_cf7_submission_8129' );
function lee_dev_pro_credit_cf7_submission_8129( $contact_form ) {
    $form_id = is_object( $contact_form ) && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
    foreach ( array( 'generating_leads', 'customer_support' ) as $intent_slug ) {
        if ( lee_dev_pro_credit_form_submission_2683( $intent_slug, array( 'detector' => 'cf7', 'form_id' => $form_id ) ) ) {
            return;
        }
    }
}

// WPForms
add_action( 'wpforms_process_complete', 'lee_dev_pro_credit_wpforms_submission_4715', 10, 4 );
function lee_dev_pro_credit_wpforms_submission_4715( $fields, $entry, $form_data, $entry_id ) {
    $form_id = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;
    foreach ( array( 'generating_leads', 'customer_support' ) as $intent_slug ) {
        if ( lee_dev_pro_credit_form_submission_2683( $intent_slug, array( 'detector' => 'wpforms', 'form_id' => $form_id, 'entry_id' => (int) $entry_id ) ) ) {
            return;
        }
    }
}

// Gravity Forms
add_action( 'gform_after_submission', 'lee_dev_pro_credit_gravity_submission_7390', 10, 2 );
function lee_dev_pro_credit_gravity_submission_7390( $entry, $form ) {
    $form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;
    foreach ( array( 'generating_leads', 'customer_support' ) as $intent_slug ) {
        if ( lee_dev_pro_credit_form_submission_2683( $intent_slug, array( 'detector' => 'gravity', 'form_id' => $form_id ) ) ) {
            return;
        }
    }
}

// =========================================================================
// 8. FRONT-END CLICK INTERCEPTOR (Pro-gated, cache-safe via bootstrap REST)
// =========================================================================
// Cache-safety contract: the previous design printed an inline <script> tag containing the ROI
// click nonce directly into the page. Any full-page cache (WP Rocket / LiteSpeed / Varnish)
// would freeze that nonce for 24h+, after which it failed the server-side check_ajax_referer
// and every attribution row was silently dropped — defeating the whole point of Pro analytics.
//
// The new design routes the nonce through the cache-safe bootstrap REST endpoint
// (intenttarget/v1/bootstrap) and the click-tracker JS lives in assets/js/itp-frontend-ui.js.
// We hook the bootstrap payload filter so the Pro nonce is minted fresh on every visitor's
// request, and the front-end runtime reads it from there.
add_filter( 'lee_dev_frontend_bootstrap_payload_4920', 'lee_dev_pro_inject_bootstrap_payload_5168', 10, 2 );
function lee_dev_pro_inject_bootstrap_payload_5168( $payload, $request ) {
    if ( ! function_exists( 'lee_dev_is_addon_active_3812' ) || ! lee_dev_is_addon_active_3812( 'pro' ) ) {
        return $payload;
    }

    $payload['pro_roi_enabled']     = true;
    if ( ! isset( $payload['nonces'] ) || ! is_array( $payload['nonces'] ) ) {
        $payload['nonces'] = array();
    }
    $payload['nonces']['roi_click'] = wp_create_nonce( 'itp_pro_track_click_nonce' );
    return $payload;
}

// =========================================================================
// 9. ANALYTICS TAB — registration + content rendering
// =========================================================================
add_action( 'itp_core_dashboard_tabs', 'lee_dev_pro_inject_roi_analytics_tab_6291' );
add_action( 'itp_core_dashboard_tab_content_roi_analytics', 'lee_dev_pro_render_roi_analytics_tab_8157' );

function lee_dev_pro_inject_roi_analytics_tab_6291( $active_tab ) {
    $is_active = ( $active_tab === 'roi_analytics' ) ? 'nav-tab-active' : '';
    echo '<a href="?page=itp-search-dashboard&tab=roi_analytics" class="nav-tab ' . esc_attr( $is_active ) . '">' . esc_html__( 'ROI Analytics (Pro)', 'intenttarget-pro' ) . '</a>';
}

function lee_dev_pro_render_roi_analytics_tab_8157() {
    if ( ! function_exists( 'lee_dev_is_addon_active_3812' ) || ! lee_dev_is_addon_active_3812( 'pro' ) ) {
        ?>
        <div style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #d63638;padding:18px 22px;margin-top:20px;border-radius:4px;max-width:800px;">
            <h3 style="margin-top:0;"><?php echo esc_html__( 'IntentTarget Pro Licence Required', 'intenttarget-pro' ); ?></h3>
            <p><?php echo esc_html__( 'ROI Analytics is part of the IntentTarget Pro add-on. Activate your Pro licence (ITPP-XXXX-XXXX-XXXX) from the Advert Styling (Pro) tab to start measuring the revenue and lead impact of intent targeting.', 'intenttarget-pro' ); ?></p>
            <p>
                <a href="?page=itp-search-dashboard&tab=design_pro" class="button button-primary"><?php echo esc_html__( 'Go to Pro Licence Activation', 'intenttarget-pro' ); ?></a>
                <a href="https://intenttargetpro.co.uk/pro" target="_blank" rel="noopener" class="button button-secondary"><?php echo esc_html__( 'Purchase IntentTarget Pro', 'intenttarget-pro' ); ?></a>
            </p>
        </div>
        <?php
        return;
    }

    $valid_ranges = array(
        '7'   => __( 'Last 7 days', 'intenttarget-pro' ),
        '30'  => __( 'Last 30 days', 'intenttarget-pro' ),
        '90'  => __( 'Last 90 days', 'intenttarget-pro' ),
        '365' => __( 'Last 12 months', 'intenttarget-pro' ),
    );
    $range_days = isset( $_GET['roi_range'] ) ? sanitize_key( wp_unslash( $_GET['roi_range'] ) ) : '30';
    if ( ! isset( $valid_ranges[ $range_days ] ) ) {
        $range_days = '30';
    }

    $stats   = lee_dev_pro_compute_roi_summary_4823( (int) $range_days );
    $recent  = lee_dev_pro_get_recent_attributions_2716( 50 );
    $intents = function_exists( 'lee_dev_get_valid_site_intents_6048' ) ? lee_dev_get_valid_site_intents_6048() : array();

    $totals = array(
        'clicks'      => 0,
        'conversions' => 0,
        'revenue'     => 0.0,
    );
    foreach ( $stats as $row ) {
        $totals['clicks']      += $row['clicks'];
        $totals['conversions'] += $row['conversions'];
        $totals['revenue']     += $row['revenue'];
    }
    $overall_rate = ( $totals['clicks'] > 0 ) ? ( $totals['conversions'] / $totals['clicks'] * 100 ) : 0;

    ?>
    <div style="background:#fff;padding:25px;border:1px solid #ccd0d4;margin-top:15px;border-radius:4px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px;">
            <div>
                <h2 style="margin:0 0 6px 0;"><?php echo esc_html__( 'ROI Analytics', 'intenttarget-pro' ); ?></h2>
                <p class="description" style="margin:0;max-width:720px;"><?php echo esc_html__( 'Measure the downstream impact of the slide-in popup and My Account recommendations. Conversion logic per intent: Generating Leads = form submission; Driving Sales = WooCommerce purchase; Educating Audiences = link click; Customer Support = link click or form submission.', 'intenttarget-pro' ); ?></p>
                <p class="description" style="margin:0;max-width:720px;">This plugin works with WooCommerce only for revenue tracking.</p>
            </div>
            <form method="get" action="" style="display:flex;align-items:center;gap:8px;">
                <input type="hidden" name="page" value="itp-search-dashboard" />
                <input type="hidden" name="tab" value="roi_analytics" />
                <label for="roi_range" style="font-weight:600;"><?php echo esc_html__( 'Date range:', 'intenttarget-pro' ); ?></label>
                <select name="roi_range" id="roi_range" onchange="this.form.submit()">
                    <?php foreach ( $valid_ranges as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $range_days, $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4, minmax(0,1fr));gap:12px;margin:20px 0;">
            <?php
            $cards = array(
                array( __( 'Advert Clicks', 'intenttarget-pro' ),      number_format_i18n( (int) $totals['clicks'] ),      '#2271b1' ),
                array( __( 'Conversions', 'intenttarget-pro' ),         number_format_i18n( (int) $totals['conversions'] ), '#46b450' ),
                array( __( 'Conversion Rate', 'intenttarget-pro' ),     number_format_i18n( $overall_rate, 1 ) . '%',       '#e1ad01' ),
                array( __( 'Attributed Revenue', 'intenttarget-pro' ),  function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() . number_format_i18n( (float) $totals['revenue'], 2 ) : number_format_i18n( (float) $totals['revenue'], 2 ), '#7b3ff2' ),
            );
            foreach ( $cards as $card ) : ?>
                <div style="border:1px solid #e5e5e5;border-radius:6px;padding:16px;background:#fafafa;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#646970;font-weight:700;margin-bottom:6px;"><?php echo esc_html( $card[0] ); ?></div>
                    <div style="font-size:22px;font-weight:700;color:<?php echo esc_attr( $card[2] ); ?>;"><?php echo esc_html( $card[1] ); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 style="margin-top:30px;"><?php echo esc_html__( 'Breakdown by Intent', 'intenttarget-pro' ); ?></h3>
        <table class="wp-list-table widefat fixed striped" style="margin-top:8px;">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Intent', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Clicks', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Conversions', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Conversion Rate', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Attributed Revenue', 'intenttarget-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $stats ) ) : ?>
                    <tr><td colspan="5"><?php echo esc_html__( 'No advert clicks have been recorded in this range yet.', 'intenttarget-pro' ); ?></td></tr>
                <?php else : foreach ( $stats as $intent_slug => $row ) :
                    $label = isset( $intents[ $intent_slug ] ) ? $intents[ $intent_slug ] : ucwords( str_replace( '_', ' ', $intent_slug ) );
                    $rate  = ( $row['clicks'] > 0 ) ? ( $row['conversions'] / $row['clicks'] * 100 ) : 0;
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $label ); ?></strong></td>
                        <td><?php echo esc_html( number_format_i18n( (int) $row['clicks'] ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( (int) $row['conversions'] ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $rate, 1 ) ); ?>%</td>
                        <td><?php
                            if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
                                echo esc_html( get_woocommerce_currency_symbol() . number_format_i18n( (float) $row['revenue'], 2 ) );
                            } else {
                                echo esc_html( number_format_i18n( (float) $row['revenue'], 2 ) );
                            }
                        ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <h3 style="margin-top:30px;"><?php echo esc_html__( 'Recent Attribution Activity', 'intenttarget-pro' ); ?></h3>
        <table class="wp-list-table widefat fixed striped" style="margin-top:8px;">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Clicked At', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'User / Session', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Intent', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Source', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Advert URL', 'intenttarget-pro' ); ?></th>
                    <th><?php echo esc_html__( 'Conversion', 'intenttarget-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $recent ) ) : ?>
                    <tr><td colspan="6"><?php echo esc_html__( 'No clicks have been captured yet.', 'intenttarget-pro' ); ?></td></tr>
                <?php else : foreach ( $recent as $row ) :
                    $user_display = 'Guest';
                    if ( (int) $row->user_id > 0 ) {
                        $user_obj     = get_userdata( (int) $row->user_id );
                        $user_display = $user_obj ? esc_html( $user_obj->display_name ) : 'User #' . (int) $row->user_id;
                    } elseif ( ! empty( $row->session_token ) ) {
                        $user_display = 'Guest (' . esc_html( substr( $row->session_token, 0, 8 ) ) . '…)';
                    }

                    $conversion_display = '—';
                    if ( ! empty( $row->converted_at ) ) {
                        $conversion_display = esc_html( ucwords( str_replace( '_', ' ', $row->conversion_type ) ) );
                        if ( (float) $row->conversion_value > 0 ) {
                            $symbol            = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
                            $conversion_display .= ' (' . esc_html( $symbol . number_format_i18n( (float) $row->conversion_value, 2 ) ) . ')';
                        }
                    }
                ?>
                    <tr>
                        <td><?php echo esc_html( $row->clicked_at ); ?></td>
                        <td><?php echo $user_display; ?></td>
                        <td><strong><?php echo esc_html( isset( $intents[ $row->intent ] ) ? $intents[ $row->intent ] : $row->intent ); ?></strong></td>
                        <td><?php echo esc_html( $row->advert_source ); ?></td>
                        <td><code style="font-size:11px;"><?php echo esc_html( $row->advert_url ); ?></code></td>
                        <td><?php echo $conversion_display; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Aggregate ROI summary keyed by intent slug for the date range.
 */
function lee_dev_pro_compute_roi_summary_4823( $days = 30 ) {
    global $wpdb;
    $table_name = lee_dev_pro_get_roi_table_name_4521();
    $days       = max( 1, (int) $days );
    $since      = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT intent,
                COUNT(*) AS clicks,
                SUM( CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END ) AS conversions,
                SUM( CASE WHEN converted_at IS NOT NULL THEN conversion_value ELSE 0 END ) AS revenue
           FROM $table_name
          WHERE clicked_at >= %s
       GROUP BY intent
       ORDER BY clicks DESC",
        $since
    ), ARRAY_A );

    $summary = array();
    foreach ( (array) $rows as $row ) {
        $summary[ $row['intent'] ] = array(
            'clicks'      => (int) $row['clicks'],
            'conversions' => (int) $row['conversions'],
            'revenue'     => (float) $row['revenue'],
        );
    }
    return $summary;
}

/**
 * Most recent attribution rows for the activity table.
 */
function lee_dev_pro_get_recent_attributions_2716( $limit = 50 ) {
    global $wpdb;
    $table_name = lee_dev_pro_get_roi_table_name_4521();
    $limit      = max( 1, (int) $limit );
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY clicked_at DESC LIMIT %d",
        $limit
    ) );
}
