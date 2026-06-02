<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. CALCULATE TOP ALERT BANNER (Dedicated Guest vs. Sales Separation)
// =========================================================================
function lee_dev_get_dynamic_alert_content_3841() {
    $advert = lee_dev_get_global_priority_advert_7136();

    return sprintf(
        '%1$s — <a href="%2$s" style="color:inherit; font-weight:bold; text-decoration:underline;">%3$s</a>',
        esc_html($advert['title']),
        esc_url($advert['url']),
        esc_html($advert['btn'])
    );
}

function lee_dev_get_global_priority_advert_catalogue_7136() {
    $catalogue = array(
        'generating_leads' => array(
            'title' => 'Start a Conversation',
            'desc'  => 'Invite visitors to make an enquiry and move qualified prospects into your sales pipeline.',
            'url'   => '/contact/',
            'btn'   => 'Make an Enquiry',
        ),
        'educating_audiences' => array(
            'title' => 'Explore Helpful Guidance',
            'desc'  => 'Share useful insight, explain your expertise, and help visitors understand their next step.',
            'url'   => '/blog/',
            'btn'   => 'Read Guidance',
        ),
        'customer_support' => array(
            'title' => 'Need Support?',
            'desc'  => 'Direct visitors towards helpful resources and support information before they need to ask.',
            'url'   => '/support/',
            'btn'   => 'Get Support',
        ),
        'driving_sales' => array(
            'title' => 'View Recommended Products',
            'desc'  => 'Guide engaged visitors towards the products most likely to match their current intent.',
            'url'   => '/shop/',
            'btn'   => 'Shop Now',
        ),
    );

    return apply_filters( 'lee_dev_global_priority_advert_catalogue_7136', $catalogue );
}

function lee_dev_get_global_priority_order_7136() {
    $valid_intents = function_exists( 'lee_dev_get_valid_site_intents_6048' ) ? lee_dev_get_valid_site_intents_6048() : array(
        'generating_leads'    => 'Generating Leads',
        'educating_audiences' => 'Educating Audiences',
        'customer_support'    => 'Customer Support',
    );
    $priority = get_option( 'itp_global_priority', array_keys( $valid_intents ) );
    if ( ! is_array( $priority ) ) {
        $priority = array_keys( $valid_intents );
    }

    $priority = array_values( array_unique( array_filter( $priority, function( $intent_key ) use ( $valid_intents ) {
        return isset( $valid_intents[$intent_key] );
    } ) ) );

    foreach ( array_keys( $valid_intents ) as $intent_key ) {
        if ( ! in_array( $intent_key, $priority, true ) ) {
            $priority[] = $intent_key;
        }
    }

    return $priority;
}

function lee_dev_get_global_priority_advert_7136( $excluded_intents = array(), $user_id = null ) {
    $catalogue = lee_dev_get_global_priority_advert_catalogue_7136();

    if ( $user_id === null ) {
        $user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
    }
    $user_id = absint( $user_id );

    if ( $user_id > 0 && function_exists( 'lee_dev_resolve_user_intent_priority_4762' ) ) {
        $priority = lee_dev_resolve_user_intent_priority_4762( $user_id );
    } else {
        $priority = lee_dev_get_global_priority_order_7136();
    }

    $excluded_intents = is_array( $excluded_intents ) ? $excluded_intents : array();

    foreach ( $priority as $intent_key ) {
        if ( in_array( $intent_key, $excluded_intents, true ) ) {
            continue;
        }

        if ( $intent_key === 'driving_sales' && ! class_exists( 'WooCommerce' ) ) {
            continue;
        }

        $advert = isset( $catalogue[$intent_key] ) && is_array( $catalogue[$intent_key] ) ? $catalogue[$intent_key] : array();
        $title  = isset( $advert['title'] ) ? trim( (string) $advert['title'] ) : '';
        $url    = isset( $advert['url'] ) ? trim( (string) $advert['url'] ) : '';

        if ( $title === '' || $url === '' ) {
            continue;
        }

        $advert['intent'] = $intent_key;
        $advert['desc']   = isset( $advert['desc'] ) ? $advert['desc'] : '';
        $advert['btn']    = isset( $advert['btn'] ) && trim( (string) $advert['btn'] ) !== '' ? $advert['btn'] : 'Find Out More';

        return $advert;
    }

    return array(
        'intent' => 'fallback',
        'title'  => 'Discover our latest recommendations',
        'desc'   => 'Find useful next steps selected for this website.',
        'url'    => '/',
        'btn'    => 'Find Out More',
    );
}

// =========================================================================
// 2. GET UNIQUE DYNAMIC INTENT ADVERT DATA OBJECT
// =========================================================================
function lee_dev_get_intent_based_product_9384() {
    // Advert display only requires an authorised licence. The tracking
    // allow-list (lee_dev_is_ready_8293) governs *who gets tracked*, not
    // *who sees adverts*, so it must not gate this lookup or the guest
    // fallback below would be unreachable.
    if ( ! function_exists( 'lee_dev_has_authorised_licence_7365' ) || ! lee_dev_has_authorised_licence_7365() ) {
        return null;
    }

    $user_id = get_current_user_id();
    $is_dashboard = ( function_exists( 'is_account_page' ) && function_exists( 'is_wc_endpoint_url' ) && is_account_page() && ! is_wc_endpoint_url() );
    $primary_data = lee_dev_get_global_priority_advert_7136();
    $secondary_data = lee_dev_get_global_priority_advert_7136( array( $primary_data['intent'] ?? '' ) );

    if ( $user_id > 0 && get_user_meta( $user_id, 'itp_disable_tracking', true ) ) {
        return $is_dashboard ? ['primary' => $primary_data, 'secondary' => $secondary_data] : $primary_data;
    }

    if ( ! is_user_logged_in() ) {
        $guest_ad = [
            'title' => 'Join the Community',
            'desc'  => 'Sign up for a free account',
            'url'   => '/my-account/',
            'btn'   => 'Create Free Account',
            'is_guest' => true
        ];
        return $is_dashboard ? ['primary' => $guest_ad, 'secondary' => $primary_data] : $guest_ad;
    }

    return $is_dashboard ? ['primary' => $primary_data, 'secondary' => $secondary_data] : $primary_data;
}

// =========================================================================
// 2b. CACHE-SAFE BOOTSTRAP REST ENDPOINT
// =========================================================================
// Returns the per-visitor data (advert pick, greeting, fresh nonces, dashboard adverts) that
// would otherwise have been baked into cached HTML by the slide-in / dashboard / preferences
// renderers. The endpoint is called by assets/js/itp-frontend-ui.js on every page load and is
// served with Cache-Control: no-store so neither WP Rocket / LiteSpeed / Varnish nor Cloudflare
// will ever cache it. Per-visitor data therefore stays accurate even when the surrounding HTML
// has been cached for 24h+.
add_action( 'rest_api_init', 'lee_dev_register_frontend_bootstrap_rest_4920' );
function lee_dev_register_frontend_bootstrap_rest_4920() {
    register_rest_route( 'intenttarget/v1', '/bootstrap', array(
        'methods'             => 'GET',
        'callback'            => 'lee_dev_handle_frontend_bootstrap_rest_4920',
        'permission_callback' => '__return_true',
        'args'                => array(
            'ctx' => array(
                'description'       => 'Full URL of the page the visitor is currently viewing.',
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'esc_url_raw',
            ),
        ),
    ) );
}

function lee_dev_handle_frontend_bootstrap_rest_4920( $request ) {
    // Guarantee no caching layer stores this response.
    nocache_headers();
    header( 'Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0' );

    $payload = array(
        'licence_active'  => false,
        'popup'           => null,
        'dashboard'       => null,
        'nonces'          => array(),
        'show_branding'   => true,
        'pro_roi_enabled' => false,
    );

    if ( ! function_exists( 'lee_dev_has_authorised_licence_7365' ) || ! lee_dev_has_authorised_licence_7365() ) {
        return rest_ensure_response( $payload );
    }

    $payload['licence_active'] = true;
    $payload['show_branding']  = (bool) apply_filters( 'itp_show_popup_branding', true );

    // Mint fresh nonces against the live visitor's session. These never live inside cached HTML.
    $payload['nonces'] = array(
        'search_feedback' => wp_create_nonce( 'itp_ajax_nonce' ),
        'high_engagement' => wp_create_nonce( 'itp_high_engagement_nonce' ),
        'user_interests'  => wp_create_nonce( 'itp_save_user_interests' ),
    );

    // ------------------------------------------------------------------
    // Derive the visitor's page context from the supplied URL. The REST request itself has no
    // is_search() / is_account_page() context, so we reconstruct it from the ctx parameter.
    // ------------------------------------------------------------------
    $ctx_url   = (string) $request->get_param( 'ctx' );
    $parsed    = $ctx_url !== '' ? wp_parse_url( $ctx_url ) : array();
    $path      = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';
    $query_arr = array();
    if ( ! empty( $parsed['query'] ) ) {
        wp_parse_str( $parsed['query'], $query_arr );
    }

    $is_search_page = ! empty( $query_arr['s'] );
    $search_query   = $is_search_page ? sanitize_text_field( (string) $query_arr['s'] ) : '';

    $is_checkout_page = false;
    $is_account_page  = false;
    if ( $path !== '' && class_exists( 'WooCommerce' ) ) {
        $checkout_slug = 'checkout';
        $account_slug  = 'my-account';
        if ( function_exists( 'wc_get_page_id' ) ) {
            $checkout_id = (int) wc_get_page_id( 'checkout' );
            $account_id  = (int) wc_get_page_id( 'myaccount' );
            if ( $checkout_id > 0 ) {
                $name = get_post_field( 'post_name', $checkout_id );
                if ( is_string( $name ) && $name !== '' ) { $checkout_slug = $name; }
            }
            if ( $account_id > 0 ) {
                $name = get_post_field( 'post_name', $account_id );
                if ( is_string( $name ) && $name !== '' ) { $account_slug = $name; }
            }
        }
        $is_checkout_page = ( strpos( $path, '/' . $checkout_slug ) !== false );
        $is_account_page  = ( strpos( $path, '/' . $account_slug ) !== false );
    }

    // ------------------------------------------------------------------
    // Slide-in popup data — null when the page is checkout/account, or when no advert applies.
    // ------------------------------------------------------------------
    if ( ! $is_checkout_page && ! $is_account_page ) {
        $popup = array(
            'is_search_page' => (bool) $is_search_page,
            'search_query'   => $search_query,
            'greeting'       => '',
            'advert'         => null,
        );
        $current_user = wp_get_current_user();
        if ( $current_user && $current_user->exists() && (int) $current_user->ID > 0 ) {
            $popup['greeting'] = (string) $current_user->display_name;
        }
        if ( ! $is_search_page ) {
            $featured = lee_dev_get_intent_based_product_9384();
            // lee_dev_get_intent_based_product_9384 returns the flat advert when not on the
            // dashboard, which is exactly what we want here.
            if ( is_array( $featured ) && ! isset( $featured['primary'] ) ) {
                $popup['advert'] = array(
                    'title'  => isset( $featured['title'] ) ? (string) $featured['title'] : '',
                    'desc'   => isset( $featured['desc'] ) ? (string) $featured['desc'] : '',
                    'url'    => isset( $featured['url'] ) ? esc_url_raw( (string) $featured['url'] ) : '',
                    'btn'    => isset( $featured['btn'] ) ? (string) $featured['btn'] : '',
                    'intent' => isset( $featured['intent'] ) ? sanitize_key( (string) $featured['intent'] ) : '',
                );
            }
        }
        // Only expose the popup when there is something to show, so the JS can hide the shell.
        if ( $popup['is_search_page'] || $popup['advert'] ) {
            $payload['popup'] = $popup;
        }
    }

    // ------------------------------------------------------------------
    // My Account dashboard adverts (primary + secondary)
    // ------------------------------------------------------------------
    if ( $is_account_page && class_exists( 'WooCommerce' ) && function_exists( 'lee_dev_get_global_priority_advert_7136' ) ) {
        $primary = lee_dev_get_global_priority_advert_7136();
        if ( is_array( $primary ) && ! empty( $primary['title'] ) ) {
            $secondary = lee_dev_get_global_priority_advert_7136( array( isset( $primary['intent'] ) ? $primary['intent'] : '' ) );
            $current_month = (int) date( 'n' );
            $payload['dashboard'] = array(
                'show_seasonal_badge' => ( $current_month >= 1 && $current_month <= 4 ),
                'primary'             => array(
                    'title'  => (string) $primary['title'],
                    'desc'   => isset( $primary['desc'] ) ? (string) $primary['desc'] : '',
                    'url'    => isset( $primary['url'] ) ? esc_url_raw( (string) $primary['url'] ) : '',
                    'btn'    => isset( $primary['btn'] ) ? (string) $primary['btn'] : '',
                    'intent' => isset( $primary['intent'] ) ? sanitize_key( (string) $primary['intent'] ) : '',
                ),
            );
            if ( is_array( $secondary ) && ! empty( $secondary['title'] ) ) {
                $payload['dashboard']['secondary'] = array(
                    'title'  => (string) $secondary['title'],
                    'desc'   => isset( $secondary['desc'] ) ? (string) $secondary['desc'] : '',
                    'url'    => isset( $secondary['url'] ) ? esc_url_raw( (string) $secondary['url'] ) : '',
                    'btn'    => isset( $secondary['btn'] ) ? (string) $secondary['btn'] : '',
                    'intent' => isset( $secondary['intent'] ) ? sanitize_key( (string) $secondary['intent'] ) : '',
                );
            }
        }
    }

    /**
     * Filter the cache-safe bootstrap payload returned to the front-end runtime.
     *
     * Add-ons (e.g. IntentTarget Pro) hook in here to inject their own nonces and feature flags
     * — for example the ROI click tracking nonce. The filter runs only when the core licence is
     * authorised so callers can safely assume a valid install.
     *
     * @param array            $payload The payload that will be JSON-encoded back to the JS runtime.
     * @param WP_REST_Request  $request The original request object.
     */
    $payload = apply_filters( 'lee_dev_frontend_bootstrap_payload_4920', $payload, $request );

    return rest_ensure_response( $payload );
}

// =========================================================================
// 3. FOOTER INTENT FORM SLIDE-OUT POPUP MODULE (cache-safe shell + CSS only)
// =========================================================================
// IMPORTANT — Cache-safety contract:
// This renderer emits ONLY the static styling and an empty container shell. Every per-visitor
// value (advert, greeting, nonces, search query, user ID) is fetched at runtime by the cache-safe
// JS runtime from the /wp-json/intenttarget/v1/bootstrap endpoint. Do NOT add inline scripts or
// PHP-injected dynamic data to this output — it will end up in cached HTML and break.
add_action('wp_footer', 'lee_dev_render_intent_popup_2841');
function lee_dev_render_intent_popup_2841() {
    if ( ! function_exists( 'lee_dev_has_authorised_licence_7365' ) || ! lee_dev_has_authorised_licence_7365() ) {
        return;
    }

    // Server-side suppression on Woo's checkout / account pages keeps the shell off those URLs
    // entirely. The licence option flips infrequently and admin actions clear the cache, so this
    // gate is cache-safe.
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        return;
    }
    if ( function_exists( 'is_account_page' ) && is_account_page() ) {
        return;
    }

    // --- FETCH CUSTOM DESIGN SETTINGS (site-wide, identical for every visitor) ---
    $colors = get_option('itp_design_settings', []);
    $c_heading     = esc_attr($colors['heading'] ?? '#1d2327');
    $c_recommended = esc_attr($colors['recommended'] ?? '#e1ad01');
    $c_button      = esc_attr($colors['button'] ?? '#2271b1');
    $c_button_text = esc_attr($colors['button_text'] ?? '#ffffff');
    ?>
    <style id="itp-popup-styles">
        #intent-slidein {
            position: fixed;
            bottom: 20px;
            right: 24px;
            z-index: 99990;
            width: 320px;
            max-width: calc(100vw - 32px);
            box-sizing: border-box;
            padding: 20px 20px 22px;
            background: #ffffff;
            color: #1d2327;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18), 0 2px 6px rgba(15, 23, 42, 0.08);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            font-size: 14px;
            line-height: 1.4;
            opacity: 0;
            transform: translateX(120%);
            transition: transform 0.5s cubic-bezier(0.25, 0.8, 0.25, 1), opacity 0.4s ease;
            pointer-events: none;
        }
        #intent-slidein.itp-popup-visible {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
        }
        #intent-slidein.itp-popup-hidden { display: none !important; }
        #intent-slidein .itp-popup-close {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 24px;
            height: 24px;
            background: transparent;
            border: 0;
            border-radius: 50%;
            color: #5b6066;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            font-weight: 400;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #intent-slidein .itp-popup-close:hover { background: #f0f1f3; color: #111; }
        #intent-slidein .itp-popup-label {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 10px;
            background: <?php echo $c_recommended; ?>;
            color: #1d2327;
            padding: 3px 10px;
            border-radius: 3px;
        }
        #intent-slidein .itp-popup-greet { margin: 4px 0 6px; font-size: 13px; color: #5b6066; }
        #intent-slidein .itp-popup-title {
            margin: 4px 0 8px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.5px;
            line-height: 1.25;
            color: <?php echo $c_heading; ?>;
            text-decoration: none;
            display: block;
        }
        #intent-slidein a.itp-popup-title:hover { text-decoration: underline; }
        #intent-slidein .itp-popup-desc {
            margin: 0 0 16px;
            font-size: 13px;
            color: #5b6066;
        }
        #intent-slidein .itp-popup-btn {
            display: block;
            width: 100%;
            box-sizing: border-box;
            text-align: center;
            padding: 9px 14px;
            background: <?php echo $c_button; ?>;
            color: <?php echo $c_button_text; ?>;
            border: 0;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: filter 0.15s ease;
        }
        /* Uses a brightness filter so dynamic colours automatically look good on hover */
        #intent-slidein .itp-popup-btn:hover { filter: brightness(90%); color: <?php echo $c_button_text; ?>; }
        
        #intent-slidein .itp-popup-btn-secondary {
            background: #f0f1f3;
            color: #1d2327;
        }
        #intent-slidein .itp-popup-btn-secondary:hover { background: #e0e2e6; filter: none; }
        #intent-slidein .itp-popup-btn-row { display: flex; gap: 10px; margin-bottom: 6px; }
        #intent-slidein .itp-popup-btn-row .itp-popup-btn { flex: 1; }
        #intent-slidein .itp-popup-feedback-extra {
            margin-top: 12px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        #intent-slidein .itp-popup-feedback-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #1d2327;
        }
        #intent-slidein .itp-popup-feedback-extra textarea {
            width: 100%;
            box-sizing: border-box;
            font-size: 12px;
            padding: 6px 8px;
            border: 1px solid #c3c4c7;
            border-radius: 3px;
            font-family: inherit;
            resize: vertical;
        }
        #intent-slidein .itp-popup-thanks {
            font-size: 13px;
            font-weight: 700;
            color: #46b450;
            text-align: center;
            margin: 20px 0;
        }
        .itp-branding-link {
            font-size:11px;
            text-align:left;
            padding:3px;
            margin-top:5px;
        }
    </style>
    <div id="intent-slidein" class="itp-popup-hidden" role="dialog" aria-live="polite" hidden></div>
    <?php
}

// =========================================================================
// 4. WOOCOMMERCE MY ACCOUNT PROMO BOXES (cache-safe shell + CSS only)
// =========================================================================
// Cache-safety contract: this renderer emits ONLY static CSS and an empty container shell. The
// per-visitor advert pick (which is computed against the logged-in user's interest profile) is
// hydrated at runtime by assets/js/itp-frontend-ui.js via the bootstrap REST endpoint. Although
// most page caches exclude /my-account/ by default, this surface is now safe even when site
// owners do cache logged-in pages.
add_action('woocommerce_account_dashboard', 'lee_dev_add_dashboard_recommendation_5824');
function lee_dev_add_dashboard_recommendation_5824() {
    if ( ! function_exists( 'lee_dev_has_authorised_licence_7365' ) || ! lee_dev_has_authorised_licence_7365() ) return;
    if ( ! class_exists( 'WooCommerce' ) ) return;

    // --- FETCH CUSTOM DESIGN SETTINGS (site-wide, identical for every visitor) ---
    $colors = get_option('itp_design_settings', []);
    $c_heading     = esc_attr($colors['heading'] ?? '#1d2327');
    $c_recommended = esc_attr($colors['recommended'] ?? '#e1ad01');
    $c_button      = esc_attr($colors['button'] ?? '#2271b1');
    $c_button_text = esc_attr($colors['button_text'] ?? '#ffffff');
    ?>
    <style id="itp-dashboard-card-styles">
        .itp-dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 30px;
        }
        @media (min-width: 768px) {
            .itp-dashboard-grid { grid-template-columns: 1fr 1fr; }
        }
        .itp-dashboard-offer {
            padding: 20px;
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            background: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        .itp-dashboard-offer .itp-dashboard-label {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 12px;
            background: <?php echo $c_recommended; ?>;
            color: #1d2327;
            padding: 3px 10px;
            border-radius: 3px;
        }
        .itp-dashboard-offer .itp-dashboard-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 2px 8px;
            margin-right: 8px;
            border-radius: 999px;
            vertical-align: middle;
        }
        .itp-dashboard-offer .itp-dashboard-title {
            margin: 0 0 10px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.4px;
            color: <?php echo $c_heading; ?>;
            line-height: 1.25;
        }
        .itp-dashboard-offer .itp-dashboard-desc {
            margin: 0 0 18px;
            color: #5b6066;
            font-size: 13px;
            line-height: 1.5;
        }
        .itp-dashboard-offer .itp-dashboard-cta {
            display: inline-block;
            padding: 9px 18px;
            background: <?php echo $c_button; ?>;
            color: <?php echo $c_button_text; ?>;
            border: 0;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: filter 0.15s ease;
        }
        .itp-dashboard-offer .itp-dashboard-cta:hover { 
            filter: brightness(90%); 
            color: <?php echo $c_button_text; ?>; 
        }
        .itp-dashboard-divider {
            border: 0;
            border-top: 1px solid #e5e5e5;
            margin: 28px 0;
        }
         .itp-branding-link {
            font-size:11px;
            text-align:left;
            padding:3px;
            margin-top:5px;
        }
    </style>
    <div id="itp-dashboard-recommendations" data-itp-shell="account-dashboard"></div>
    <?php
}

// =========================================================================
// 5. NATIVE UX PAGE VIEWS AUTO-TRACKER
// =========================================================================
add_action('wp_footer', 'lee_dev_auto_track_native_field_7482', 5);
function lee_dev_auto_track_native_field_7482() {
    $allowed_types = ['post', 'product', 'projects', 'seminars_events', 'our_people', 'community_support', 'page'];
    if (!is_singular($allowed_types)) return;
    if (!function_exists('lee_dev_is_ready_8293') || !lee_dev_is_ready_8293()) {
        lee_dev_debug_log_event_6158( 'page_view_tracking.skipped_not_ready', array(
            'post_id' => get_the_ID(),
        ) );
        return;
    }

    $sectors_to_track = get_post_meta(get_the_ID(), '_itp_tracking_labels', true);
    if (empty($sectors_to_track) || !is_array($sectors_to_track)) {
        lee_dev_debug_log_event_6158( 'page_view_tracking.skipped_no_labels', array(
            'post_id' => get_the_ID(),
        ) );
        return;
    }

    $consent_cookie = $_COOKIE['cookieyes-consent'] ?? '';
    if ( $consent_cookie !== '' && strpos($consent_cookie, 'functional:yes') === false && strpos($consent_cookie, 'analytics:yes') === false ) {
        lee_dev_debug_log_event_6158( 'page_view_tracking.skipped_cookie_consent', array(
            'post_id' => get_the_ID(),
        ) );
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        lee_dev_debug_log_event_6158( 'page_view_tracking.skipped_no_user', array(
            'post_id' => get_the_ID(),
        ) );
        return;
    }

    $interest_map = get_user_meta($user_id, 'user_interest_map', true) ?: [];
    $data_changed = false;

    foreach ($sectors_to_track as $raw_slug) {
        $slug = sanitize_title($raw_slug);
        if (isset($interest_map[$slug]) && is_array($interest_map[$slug])) {
            $interest_map[$slug]['score']++; 
            $interest_map[$slug]['last_seen'] = time();
        } else {
            $interest_map[$slug] = ['score' => 1, 'last_seen' => time()];
        }
        $data_changed = true;
    }

    if ($data_changed) {
        $expiry_limit = strtotime('-6 months');
        foreach ($interest_map as $key => $data) {
            if (isset($data['last_seen']) && $data['last_seen'] < $expiry_limit) unset($interest_map[$key]);
        }
        uasort($interest_map, function($a, $b) { return $b['score'] <=> $a['score']; });
        update_user_meta($user_id, 'user_interest_map', array_slice($interest_map, 0, 15, true));
        lee_dev_debug_log_event_6158( 'page_view_tracking.updated', array(
            'post_id' => get_the_ID(),
            'user_id' => $user_id,
            'labels'  => $sectors_to_track,
        ) );
    }
}

// =========================================================================
// 6. FRONTEND UX SHORTCODE INTERFACE
// =========================================================================
function lee_dev_preferences_shortcode_6382() {
    if ( ! function_exists('lee_dev_is_ready_8293') || ! lee_dev_is_ready_8293() ) {
        return '<p class="uk-text-danger">Access restricted to authorised accounts only.</p>';
    }

    $user_id = get_current_user_id();
    ob_start();

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_interests_nonce']) ) {
        if ( wp_verify_nonce($_POST['user_interests_nonce'], 'itp_save_user_interests') ) {
            if ( isset($_POST['itp_disable_tracking']) ) {
                update_user_meta($user_id, 'itp_disable_tracking', 1);
                delete_user_meta($user_id, 'user_interest_map');
                delete_user_meta($user_id, 'user_search_history');
            } else {
                delete_user_meta($user_id, 'itp_disable_tracking');
            }
            $selected_interests = isset($_POST['user_interests']) ? array_map('sanitize_text_field', $_POST['user_interests']) : [];
            update_user_meta($user_id, 'manual_interests', $selected_interests);
            echo '<div style="background:#e7f6ec; color:#2271b1; padding:15px; margin-bottom:20px; border-radius:4px; font-weight:500;">Preferences updated successfully.</div>';
        }

        if (isset($_POST['itp_clear_data'])) {
            delete_user_meta($user_id, 'user_interest_map');
            delete_user_meta($user_id, 'user_search_history');
            delete_user_meta($user_id, 'manual_interests');
            echo '<div style="background:#fbeae5; color:#c62828; padding:15px; margin-bottom:20px; border-radius:4px; font-weight:500;">All custom interest analytics data has been wiped.</div>';
        }
    }

    $dictionary = get_option('itp_dynamic_keyword_dictionary', []);
    $labels_map = function_exists('lee_dev_get_active_categories_5921') ? lee_dev_get_active_categories_5921() : [];

    $is_opted_out = (bool) get_user_meta($user_id, 'itp_disable_tracking', true);
    $saved_manual = get_user_meta($user_id, 'manual_interests', true) ?: [];
    ?>
    <div class="itp-preferences-wrap">
        <h3>Personalise Your Experience</h3>
        <p style="margin-bottom: 20px;">Select the sectors or services you are interested in.</p>
        
        <form method="post" action="">
            <?php /*
             * Cache-safety: the nonce value is left empty in the cached HTML and hydrated at
             * runtime by assets/js/itp-frontend-ui.js (look for data-itp-hydrate). A page cached
             * for 24h+ therefore never carries a stale nonce — the JS rewrites this input with
             * a fresh value fetched from the bootstrap REST endpoint before the user submits.
             */ ?>
            <input type="hidden" name="user_interests_nonce" value="" data-itp-hydrate="user_interests_nonce">
            <ul class="uk-remove-before custom-accordion">
                <?php 
                $count = 0;
                foreach ($dictionary as $group_key => $slugs_array) : 
                    if ( empty($slugs_array) ) continue;
                    
                    $group_title = isset($labels_map[$group_key]) ? $labels_map[$group_key] : ucfirst($group_key);
                    $group_id = 'group_' . $count;
                    $count++;
                ?>
                    <li style="border-bottom: 1px solid #eee; padding-left:0; margin-top:0px;" class="itp-accordion-item">
    <div class="itp-accordion-header" style="display: flex; align-items: center; justify-content: space-between; padding: 15px 0; cursor: pointer;">
        <span style="font-size: 1.1rem; font-weight: 600; color: #333;"><?php echo esc_html($group_title); ?></span>
        <div style="display: flex; align-items: center; gap: 20px;">
            <!-- Your Select All label with stopPropagation to prevent opening/closing the accordion -->
            <label class="itp-interest-item" style="margin-bottom: 0; padding-left: 25px; display: flex; align-items: center;" onclick="event.stopPropagation();">
                <input type="checkbox" class="itp-select-all" data-target="<?php echo $group_id; ?>">
                <span class="itp-checkmark"></span>
                <span style="font-size: 11px; color: #999; text-transform: uppercase; font-weight: bold; margin-left: 5px;">Select All</span>
            </label>
            <span class="itp-accordion-icon"></span>
        </div>
    </div>

    <!-- Added 'itp-accordion-content' class for JS/CSS targeting -->
    <div class="itp-accordion-content" id="<?php echo $group_id; ?>" style="margin-top: 0;">
        <!-- Moved padding-bottom: 20px here to prevent animation jumping -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; padding-left: 20px; padding-bottom: 20px;">
            <?php foreach ($slugs_array as $slug) : 
                $display_name = esc_html(ucwords(str_replace('-', ' ', $slug)));
            ?>
                <label class="itp-interest-item">
                    <input type="checkbox" name="user_interests[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $saved_manual)); ?>>
                    <span class="itp-checkmark"></span>
                    <span style="font-size: 14px;"><?php echo $display_name; ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
</li>
                <?php endforeach; ?>
            </ul>

            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                <h3 style="font-size: 1.2em; margin-bottom: 15px;">Advertising & Privacy Preferences</h3>
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <label class="itp-interest-item" style="margin-bottom: 0; flex-shrink: 0; position: relative;">
                        <input type="checkbox" name="itp_disable_tracking" value="1" <?php checked($is_opted_out, true); ?> />
                        <span class="itp-checkmark"></span>
                    </label>
                    <div style="flex: 1; line-height: 1.4;">
                        <strong style="font-size: 14px; color: #333; display: block; margin-bottom: 4px;">Show generic announcements instead of tailored recommendations</strong>
                        <p style="margin: 0; font-size: 0.9em; color: #666;">Checking this stops us from analysing your search terms and page views to predict your interests. Turning this on will automatically delete your existing automated profiles.</p>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 15px; border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px;">
                <button type="submit" name="itp_save_prefs" class="uk-button-spot1">Save Preferences</button>
                <button type="submit" name="itp_clear_data" class="uk-button-spot2" onclick="return confirm('Erase all data?')">Clear My Data</button>
            </div>
        </form>
    </div>

    <?php
    // Note: accordion + select-all behaviour is handled by assets/js/itp-frontend-ui.js
    // (initPreferencesAccordion). No inline JS lives here so JS-combine plugins cannot break it.
    return ob_get_clean();
}
