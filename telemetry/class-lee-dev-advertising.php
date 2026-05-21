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

function lee_dev_get_global_priority_advert_7136( $excluded_intents = array() ) {
    $catalogue = lee_dev_get_global_priority_advert_catalogue_7136();
    $priority  = lee_dev_get_global_priority_order_7136();
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
    if (!function_exists('lee_dev_is_ready_8293') || !lee_dev_is_ready_8293()) return null;

    $user_id = get_current_user_id();
    $is_dashboard = (function_exists('is_account_page') && is_account_page() && !is_wc_endpoint_url());
    $primary_data = lee_dev_get_global_priority_advert_7136();
    $secondary_data = lee_dev_get_global_priority_advert_7136( array( $primary_data['intent'] ?? '' ) );

    if (get_user_meta($user_id, 'itp_disable_tracking', true)) {
        return $is_dashboard ? ['primary' => $primary_data, 'secondary' => $secondary_data] : $primary_data;
    }

    if (!is_user_logged_in()) {
        $guest_ad = [
            'title' => 'Join the Community',
            'desc'  => 'Sign up for a free account to track your insights.',
            'url'   => '/my-account/',
            'btn'   => 'Create Free Account',
            'is_guest' => true
        ];
        return $is_dashboard ? ['primary' => $guest_ad, 'secondary' => $primary_data] : $guest_ad;
    }

    return $is_dashboard ? ['primary' => $primary_data, 'secondary' => $secondary_data] : $primary_data;
}

// =========================================================================
// 3. FOOTER INTENT FORM SLIDE-OUT POPUP MODULE
// =========================================================================
add_action('wp_footer', 'lee_dev_render_intent_popup_2841');
function lee_dev_render_intent_popup_2841() {
    if ( ! function_exists( 'lee_dev_has_authorised_licence_7365' ) || ! lee_dev_has_authorised_licence_7365() ) {
        return;
    }

    if ( !function_exists('is_checkout') || is_checkout() || is_account_page() ) {
        return;
    }
    
    $is_search_page = is_search();
    $featured = null;

    if ( !$is_search_page ) {
        $featured = lee_dev_get_intent_based_product_9384(); 
        if (!$featured) return;
    }

    if ( $is_search_page && isset($_POST['itp_search_feedback_submit']) ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'itp_search_feedback';
        $user_id = get_current_user_id();
        $search_query = sanitize_text_field($_POST['search_query']);

        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND search_query = %s AND found_result = 'implicit' ORDER BY submitted_at DESC LIMIT 1",
            $user_id,
            $search_query
        ));

        if ( $existing_id ) {
            $wpdb->update(
                $table_name,
                array(
                    'found_result'   => sanitize_text_field($_POST['found_result']),
                    'feedback_notes' => isset($_POST['feedback_notes']) ? sanitize_textarea_field($_POST['feedback_notes']) : ''
                ),
                array('id' => $existing_id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id'        => $user_id,
                    'search_query'   => $search_query,
                    'found_result'   => sanitize_text_field($_POST['found_result']),
                    'feedback_notes' => isset($_POST['feedback_notes']) ? sanitize_textarea_field($_POST['feedback_notes']) : '',
                    'submitted_at'   => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
        }

        setcookie('itp_popup_shown', 'true', time() + 2592000, '/');
        $_COOKIE['itp_popup_shown'] = 'true'; 
    }
    ?>
    <div id="intent-slidein" class="uk-card uk-card-default uk-card-body uk-border-rounded" uk-scrollspy="cls: uk-animation-slide-right; delay: 2000; repeat: false" style="position:fixed; bottom:20px; right:80px; z-index:1000; width:300px; display:none;padding:20px 20px 25px 20px;">
        <button class="uk-close-small uk-position-top-right" style="top:10px;right:10px;" type="button" uk-close></button>
        
        <?php if ( $is_search_page ) : ?>
            <span class="uk-label" style="font-size:11px; margin-bottom:10px;background-color:#e1ad01;padding:3px 10px;">Search Feedback</span>
            <?php if ( isset($_POST['itp_search_feedback_submit']) ) : ?>
                <p style="font-size:13px; font-weight:bold; color:#46b450; text-align:center; margin:20px 0;">Thank you for your feedback!</p>
                <script>setTimeout(function(){ document.getElementById('intent-slidein').style.display = 'none'; }, 2000);</script>
            <?php else : ?>
                <h4 class="uk-text-spot1 uk-text-bold" style="margin: 5px 0 10px 0; letter-spacing:-1px; line-height: 1.2;">Did you find what you were looking for?</h4>
                <form method="post" action="">
                    <input type="hidden" name="search_query" value="<?php echo esc_attr(get_search_query()); ?>">
                    <input type="hidden" name="itp_search_feedback_submit" value="1">
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <button type="submit" name="found_result" value="yes" class="uk-button-spot1" style="font-size:.8rem; flex: 1; padding: 5px; cursor: pointer;">Yes</button>
                        <button type="button" id="itp-search-no-toggle" class="uk-button-spot1" style="font-size:.8rem; flex: 1; padding: 5px; background: #666; cursor: pointer;">No</button>
                    </div>
                    <div id="itp-feedback-extra" style="display: none; margin-top: 12px; border-top: 1px solid #eee; padding-top: 10px;">
                        <input type="hidden" id="itp-found-result-hidden" name="found_result" value="no" disabled>
                        <label style="display: block; font-size: 11px; font-weight: bold;">What were you hunting for today?</label>
                        <textarea name="feedback_notes" rows="2" style="width: 100%; font-size: 12px;" placeholder="Tell us how we can help..."></textarea>
                        <button type="submit" onclick="document.getElementById('itp-found-result-hidden').disabled=false;" class="uk-button-spot1" style="margin-top: 8px; font-size:.75rem; width:100%; background: #333;">Submit Details</button>
                    </div>
                </form>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const btn = document.getElementById('itp-search-no-toggle');
                    if (btn) btn.addEventListener('click', function(e) { e.preventDefault(); document.getElementById('itp-feedback-extra').style.display = 'block'; });
                });
                </script>
            <?php endif; ?>
        <?php elseif (isset($featured)) : ?>
            <span class="uk-label" style="font-size:11px; margin-bottom:10px;background-color:#e1ad01;padding:3px 10px;">Recommended</span>
            <?php $cu = wp_get_current_user(); if (0 !== $cu->ID) echo '<p>Hi, ' . esc_html($cu->display_name).'</p>'; ?>
            <a href="<?php echo esc_url($featured['url']); ?>"><h4 class="uk-text-spot1 uk-text-bold" style="margin: 5px 0;letter-spacing:-1px;"><?php echo esc_html($featured['title']); ?></h4></a>
            <p class="uk-text-meta uk-margin-medium-bottom"><?php echo esc_html($featured['desc']); ?></p>
            <a href="<?php echo esc_url($featured['url']); ?>" class="uk-button-spot1 itp-button" style="font-size:.8rem;width:100%;"><?php echo esc_html($featured['btn']); ?></a>
        <?php endif; ?>
    </div>

    <script>
    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            const popup = document.getElementById('intent-slidein');
            if (!popup) return;
            
            const actionBtn = popup.querySelector('.itp-button');
            const closeBtn = popup.querySelector('.uk-close-small');
            const cookies = document.cookie;
            
            if (!cookies.includes('itp_popup_shown=') && !cookies.includes('itp_popup_interacted=')) {
                popup.style.display = 'block';
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.cookie = "itp_popup_shown=true; max-age=86400; path=/; samesite=strict";
                    popup.style.display = 'none';
                });
            }
            
            if (actionBtn && actionBtn.tagName === 'A') {
                actionBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = this.getAttribute('href');
                    document.cookie = "itp_popup_interacted=true; max-age=1209600; path=/; samesite=strict";
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'itp_mark_high_engagement', user_id: '<?php echo get_current_user_id(); ?>' })
                    }).then(() => { window.location.href = url; }).catch(() => { window.location.href = url; });
                });
            }
        });
    })();
    </script>
    <?php
}

// =========================================================================
// 4. WOOCOMMERCE MY ACCOUNT PROMO BOXES
// =========================================================================
add_action('woocommerce_account_dashboard', 'lee_dev_add_dashboard_recommendation_5824');
function lee_dev_add_dashboard_recommendation_5824() {
    if ( ! function_exists( 'lee_dev_has_authorised_licence_7365' ) || ! lee_dev_has_authorised_licence_7365() ) return;
    if ( ! class_exists( 'WooCommerce' ) ) return;

    $adverts = function_exists('lee_dev_get_intent_based_product_9384') ? lee_dev_get_intent_based_product_9384() : null;
    if (!$adverts || !isset($adverts['primary'])) return;

    $current_month = (int) date('n');
    $seasonal_badge = ($current_month >= 1 && $current_month <= 4) ? '<span class="uk-badge uk-margin-small-right" style="background:#e67e22;">Seasonal Priority</span>' : '';
    ?>
    <div class="uk-child-width-1-1 uk-child-width-1-2@m" uk-grid>
        <div>
            <div class="itp-dashboard-offer" style="margin-top: 30px; padding: 20px; border: 1px solid #e5e5e5; border-radius: 5px;">
                <span class="uk-label" style="font-size:11px; margin-bottom:10px;background-color:#e1ad01;padding:3px 10px;">Recommended</span>
                <h3 class="uk-text-spot1 uk-margin-remove-top"><?php echo $seasonal_badge; ?><?php echo esc_html($adverts['primary']['title']); ?></h3>
                <p class="uk-margin-medium-bottom" style="color:#999;"><?php echo esc_html($adverts['primary']['desc']); ?></p>
                <a href="<?php echo esc_url($adverts['primary']['url']); ?>" class="uk-button-spot1"><?php echo esc_html($adverts['primary']['btn']); ?></a>
            </div>
        </div>
        <?php if (isset($adverts['secondary'])) : ?>
            <div>
                <div class="itp-dashboard-offer" style="margin-top: 30px; padding: 20px; border: 1px solid #e5e5e5; border-radius: 5px;">
                    <span class="uk-label" style="font-size:11px; margin-bottom:10px;background-color:#e1ad01;padding:3px 10px;">Recommended</span>
                    <h3 class="uk-text-spot1 uk-margin-remove-top"><?php echo esc_html($adverts['secondary']['title']); ?></h3>
                    <p class="uk-margin-medium-bottom" style="color:#999;"><?php echo esc_html($adverts['secondary']['desc']); ?></p>
                    <a href="<?php echo esc_url($adverts['secondary']['url']); ?>" class="uk-button-spot1"><?php echo esc_html($adverts['secondary']['btn']); ?></a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <hr class="uk-divider-icon">
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
            <input type="hidden" name="user_interests_nonce" value="<?php echo wp_create_nonce('itp_save_user_interests'); ?>">
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

    <script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. ACCORDION LOGIC (Single Open) ---
    document.addEventListener('click', function(e) {
        // Check if we clicked an accordion header
        const clickedHeader = e.target.closest('.itp-accordion-header');
        if (!clickedHeader) return;

        // NEW: Close all other open accordions first
        const allHeaders = document.querySelectorAll('.itp-accordion-header');
        allHeaders.forEach(function(header) {
            // If this header isn't the one we clicked, and it is currently active
            if (header !== clickedHeader && header.classList.contains('active')) {
                header.classList.remove('active'); // Remove the active class (resets icon)
                const content = header.nextElementSibling;
                if (content && content.classList.contains('itp-accordion-content')) {
                    content.style.maxHeight = null; // Collapse the content
                }
            }
        });

        // Toggle the active class for the clicked header
        clickedHeader.classList.toggle('active');

        // Get the content div for the clicked header
        const clickedContent = clickedHeader.nextElementSibling;

        // Toggle the height for the clicked header
        if (clickedContent && clickedContent.classList.contains('itp-accordion-content')) {
            if (clickedContent.style.maxHeight) {
                clickedContent.style.maxHeight = null;
            } else {
                clickedContent.style.maxHeight = clickedContent.scrollHeight + 'px';
            }
        }
    });

    // --- 2. SELECT ALL CHECKBOX LOGIC (Unchanged) ---
    function updateSelectAllState(container, selectAllBox) {
        const checkboxes = container.querySelectorAll('input[type="checkbox"]:not(.itp-select-all)');
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;

        if (checkedCount === 0) {
            selectAllBox.checked = false;
            selectAllBox.indeterminate = false;
            selectAllBox.closest('.itp-interest-item').classList.remove('itp-partial');
        } else if (checkedCount === checkboxes.length) {
            selectAllBox.checked = true;
            selectAllBox.indeterminate = false;
            selectAllBox.closest('.itp-interest-item').classList.remove('itp-partial');
        } else {
            selectAllBox.checked = false;
            selectAllBox.indeterminate = true;
            selectAllBox.closest('.itp-interest-item').classList.add('itp-partial');
        }
    }

    const selectAllBoxes = document.querySelectorAll('.itp-select-all');
    
    selectAllBoxes.forEach(box => {
        const targetId = box.getAttribute('data-target');
        const container = document.getElementById(targetId);
        if (container) {
            updateSelectAllState(container, box);
            container.querySelectorAll('input[type="checkbox"]').forEach(child => {
                child.addEventListener('change', function() { 
                    updateSelectAllState(container, box); 
                });
            });
        }
    });

    selectAllBoxes.forEach(box => {
        box.addEventListener('change', function() {
            const targetId = this.getAttribute('data-target');
            const container = document.getElementById(targetId);
            if (container) {
                container.querySelectorAll('input[type="checkbox"]').forEach(cb => { 
                    cb.checked = this.checked; 
                });
            }
            this.closest('.itp-interest-item').classList.remove('itp-partial');
        });
    });

});
</script>

    <?php
    return ob_get_clean();
}
