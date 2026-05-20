<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. CALCULATE TOP ALERT BANNER (Dedicated Guest vs. Sales Separation)
// =========================================================================
function lee_dev_get_dynamic_alert_content_3841() {
    $opts = get_option('cit_advert_settings');
    if ( ! is_array( $opts ) ) {
        $opts = array();
    }
    
    // DEDICATED DEFAULT MESSAGE FOR GUESTS & LOW-SCORE SESSIONS
    $default_title = 'Our 2026 Seminar is now available online ';
    $default_url   = '/store/andersons-2026-seminar-online/';
    $default_btn   = 'Click here for more details';

    $default_banner = sprintf(
        '%1$s — <a href="%2$s" style="color:inherit; font-weight:bold; text-decoration:underline;">%3$s</a>',
        esc_html($default_title),
        esc_url($default_url),
        esc_html($default_btn)
    );

    if ( ! is_user_logged_in() ) {
        return $default_banner;
    }

    if ( function_exists('lee_dev_is_ready_8293') && lee_dev_is_ready_8293() ) {
        $user_id = get_current_user_id();
        
        $labels = function_exists('lee_dev_get_active_categories_5921') ? lee_dev_get_active_categories_5921() : [];
        $master_groups = array_keys($labels);
        $group_scores = [];

        foreach ($master_groups as $g) {
            $group_scores[$g] = function_exists('lee_dev_calculate_group_propensity_1289') ? lee_dev_calculate_group_propensity_1289($g, $user_id) : 0;
        }

        arsort($group_scores);
        $top_group = array_key_first($group_scores);
        $top_score = isset($group_scores[$top_group]) ? $group_scores[$top_group] : 0;

        if ($top_score > 25) {
            if ($top_group === 'news') {
                $latest_news = get_posts(['numberposts' => 1, 'category_name' => 'news', 'post_status' => 'publish']);
                if (!empty($latest_news)) {
                    $title = mb_strimwidth(get_the_title($latest_news[0]->ID), 0, 50, '...');
                    $link  = get_permalink($latest_news[0]->ID);
                    
                    return sprintf(
                        'Latest News: %1$s — <a href="%2$s" style="color:inherit; font-weight:bold; text-decoration:underline;">Read Article</a>',
                        esc_html($title),
                        esc_url($link)
                    );
                }
            } else {
                $title = ! empty($opts[$top_group]['main_title']) ? $opts[$top_group]['main_title'] : '';
                $url   = ! empty($opts[$top_group]['main_url'])   ? $opts[$top_group]['main_url']   : '';
                $btn   = ! empty($opts[$top_group]['main_btn'])   ? $opts[$top_group]['main_btn']   : 'View Details';

                if (!empty($title) && !empty($url)) {
                    return sprintf(
                        '%1$s — <a href="%2$s" style="color:inherit; font-weight:bold; text-decoration:underline;">%3$s</a>',
                        esc_html($title),
                        esc_url($url),
                        esc_html($btn)
                    );
                }
            }
        }
    }
    
    return $default_banner;
}

// =========================================================================
// 2. GET UNIQUE DYNAMIC INTENT ADVERT DATA OBJECT
// =========================================================================
function lee_dev_get_intent_based_product_9384() {
    if (!function_exists('lee_dev_is_ready_8293') || !lee_dev_is_ready_8293()) return null;

    $user_id = get_current_user_id();
    $opts = get_option('cit_advert_settings', []);

    $labels = function_exists('lee_dev_get_active_categories_5921') ? lee_dev_get_active_categories_5921() : [];
    $active_slugs = array_keys($labels);
    
    $sales_slug = in_array('sales', $active_slugs, true) ? 'sales' : ($active_slugs[0] ?? '');
    $news_slug = in_array('news', $active_slugs, true) ? 'news' : ($active_slugs[1] ?? ($active_slugs[0] ?? ''));

    $fallback_sales = [
        'title' => $opts[$sales_slug]['main_title'] ?? '2026 John Nix Pocketbook',
        'desc'  => $opts[$sales_slug]['main_desc'] ?? 'The most comprehensive source of business information.',
        'url'   => $opts[$sales_slug]['main_url'] ?? '/store/',
        'btn'   => $opts[$sales_slug]['main_btn'] ?? 'Get the Edition'
    ];

    $fallback_news = [
        'title' => $opts[$news_slug]['main_title'] ?? 'Key Farm Facts',
        'desc'  => $opts[$news_slug]['main_desc'] ?? 'Need a snapshot of the latest prices?',
        'url'   => $opts[$news_slug]['main_url'] ?? '/news/',
        'btn'   => $opts[$news_slug]['main_btn'] ?? 'Stay Ahead'
    ];

    $is_dashboard = (function_exists('is_account_page') && is_account_page() && !is_wc_endpoint_url());

    if (get_user_meta($user_id, 'cit_disable_tracking', true)) {
        return $is_dashboard ? ['primary' => $fallback_sales, 'secondary' => $fallback_news] : $fallback_sales;
    }

    if (!is_user_logged_in()) {
        $guest_ad = [
            'title' => 'Join the Community',
            'desc'  => 'Sign up for a free account to track your insights.',
            'url'   => '/my-account/',
            'btn'   => 'Create Free Account',
            'is_guest' => true
        ];
        return $is_dashboard ? ['primary' => $guest_ad, 'secondary' => $fallback_sales] : $guest_ad;
    }

    $master_groups = $active_slugs;
    $group_scores = [];
    $current_month = (int) date('n');
    
    $seminar_slug = apply_filters('lee_dev_seminar_slug_1289', 'seminars');
    $is_seminar_season = ($current_month >= 1 && $current_month <= 4);

    foreach ($master_groups as $g) {
        $score = function_exists('lee_dev_calculate_group_propensity_1289') ? lee_dev_calculate_group_propensity_1289($g, $user_id) : 0;
        if ($g === $seminar_slug && $is_seminar_season && $score > 0) $score *= 1.5; 
        $group_scores[$g] = $score;
    }

    arsort($group_scores);
    $ranked_keys = array_keys($group_scores);
    $top_group = $ranked_keys[0] ?? '';
    $second_group = $ranked_keys[1] ?? '';

    if (empty($top_group) || $group_scores[$top_group] < 25) {
        return $is_dashboard ? ['primary' => $fallback_sales, 'secondary' => $fallback_news] : $fallback_sales;
    }

    $product_logic = [];
    foreach($master_groups as $key) {
        $product_logic[$key] = [
            'id' => (int) ($opts[$key]['id'] ?? 0),
            'main' => [
                'title' => $opts[$key]['main_title'] ?? '',
                'desc'  => $opts[$key]['main_desc'] ?? '',
                'url'   => $opts[$key]['main_url'] ?? '',
                'btn'   => $opts[$key]['main_btn'] ?? ''
            ],
            'alt' => [
                'title' => $opts[$key]['alt_title'] ?? '',
                'desc'  => $opts[$key]['alt_desc'] ?? '',
                'url'   => $opts[$key]['alt_url'] ?? '',
                'btn'   => $opts[$key]['alt_btn'] ?? ''
            ]
        ];
    }

    $user_email = get_userdata($user_id)->user_email ?: '';
    
    $primary_data = $fallback_sales;
    if (isset($product_logic[$top_group])) {
        $top_ad = $product_logic[$top_group];
        $primary_data = (function_exists('wc_customer_bought_product') && wc_customer_bought_product($user_email, $user_id, $top_ad['id'])) ? $top_ad['alt'] : $top_ad['main'];
    }

    $secondary_data = $fallback_news;
    if (isset($product_logic[$second_group])) {
        $second_ad = $product_logic[$second_group];
        $secondary_data = (function_exists('wc_customer_bought_product') && wc_customer_bought_product($user_email, $user_id, $second_ad['id'])) ? $second_ad['alt'] : $second_ad['main'];
    }

    return $is_dashboard ? ['primary' => $primary_data, 'secondary' => $secondary_data] : $primary_data;
}

// =========================================================================
// 3. FOOTER INTENT FORM SLIDE-OUT POPUP MODULE
// =========================================================================
add_action('wp_footer', 'lee_dev_render_intent_popup_2841');
function lee_dev_render_intent_popup_2841() {
    if ( !function_exists('is_checkout') || is_checkout() || is_account_page() ) {
        return;
    }
    
    $is_search_page = is_search();
    $featured = null;

    if ( !$is_search_page ) {
        $featured = lee_dev_get_intent_based_product_9384(); 
        if (!$featured) return;
    }

    if ( $is_search_page && isset($_POST['cit_search_feedback_submit']) ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cit_search_feedback';
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

        setcookie('cit_popup_shown', 'true', time() + 2592000, '/');
        $_COOKIE['cit_popup_shown'] = 'true'; 
    }
    ?>
    <div id="intent-slidein" class="uk-card uk-card-default uk-card-body uk-border-rounded" uk-scrollspy="cls: uk-animation-slide-right; delay: 2000; repeat: false" style="position:fixed; bottom:20px; right:80px; z-index:1000; width:300px; display:none;padding:20px 20px 25px 20px;">
        <button class="uk-close-small uk-position-top-right" style="top:10px;right:10px;" type="button" uk-close></button>
        
        <?php if ( $is_search_page ) : ?>
            <span class="uk-label" style="font-size:11px; margin-bottom:10px;background-color:#e1ad01;padding:3px 10px;">Search Feedback</span>
            <?php if ( isset($_POST['cit_search_feedback_submit']) ) : ?>
                <p style="font-size:13px; font-weight:bold; color:#46b450; text-align:center; margin:20px 0;">Thank you for your feedback!</p>
                <script>setTimeout(function(){ document.getElementById('intent-slidein').style.display = 'none'; }, 2000);</script>
            <?php else : ?>
                <h4 class="uk-text-spot1 uk-text-bold" style="margin: 5px 0 10px 0; letter-spacing:-1px; line-height: 1.2;">Did you find what you were looking for?</h4>
                <form method="post" action="">
                    <input type="hidden" name="search_query" value="<?php echo esc_attr(get_search_query()); ?>">
                    <input type="hidden" name="cit_search_feedback_submit" value="1">
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <button type="submit" name="found_result" value="yes" class="uk-button-spot1" style="font-size:.8rem; flex: 1; padding: 5px; cursor: pointer;">Yes</button>
                        <button type="button" id="cit-search-no-toggle" class="uk-button-spot1" style="font-size:.8rem; flex: 1; padding: 5px; background: #666; cursor: pointer;">No</button>
                    </div>
                    <div id="cit-feedback-extra" style="display: none; margin-top: 12px; border-top: 1px solid #eee; padding-top: 10px;">
                        <input type="hidden" id="cit-found-result-hidden" name="found_result" value="no" disabled>
                        <label style="display: block; font-size: 11px; font-weight: bold;">What were you hunting for today?</label>
                        <textarea name="feedback_notes" rows="2" style="width: 100%; font-size: 12px;" placeholder="Tell us how we can help..."></textarea>
                        <button type="submit" onclick="document.getElementById('cit-found-result-hidden').disabled=false;" class="uk-button-spot1" style="margin-top: 8px; font-size:.75rem; width:100%; background: #333;">Submit Details</button>
                    </div>
                </form>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const btn = document.getElementById('cit-search-no-toggle');
                    if (btn) btn.addEventListener('click', function(e) { e.preventDefault(); document.getElementById('cit-feedback-extra').style.display = 'block'; });
                });
                </script>
            <?php endif; ?>
        <?php elseif (isset($featured)) : ?>
            <span class="uk-label" style="font-size:11px; margin-bottom:10px;background-color:#e1ad01;padding:3px 10px;">Recommended</span>
            <?php $cu = wp_get_current_user(); if (0 !== $cu->ID) echo '<p>Hi, ' . esc_html($cu->display_name).'</p>'; ?>
            <a href="<?php echo esc_url($featured['url']); ?>"><h4 class="uk-text-spot1 uk-text-bold" style="margin: 5px 0;letter-spacing:-1px;"><?php echo esc_html($featured['title']); ?></h4></a>
            <p class="uk-text-meta uk-margin-medium-bottom"><?php echo esc_html($featured['desc']); ?></p>
            <a href="<?php echo esc_url($featured['url']); ?>" class="uk-button-spot1 cit-button" style="font-size:.8rem;width:100%;"><?php echo esc_html($featured['btn']); ?></a>
        <?php endif; ?>
    </div>

    <script>
    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            const popup = document.getElementById('intent-slidein');
            if (!popup) return;
            
            const actionBtn = popup.querySelector('.cit-button');
            const closeBtn = popup.querySelector('.uk-close-small');
            const cookies = document.cookie;
            
            if (!cookies.includes('cit_popup_shown=') && !cookies.includes('cit_popup_interacted=')) {
                popup.style.display = 'block';
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.cookie = "cit_popup_shown=true; max-age=86400; path=/; samesite=strict";
                    popup.style.display = 'none';
                });
            }
            
            if (actionBtn && actionBtn.tagName === 'A') {
                actionBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = this.getAttribute('href');
                    document.cookie = "cit_popup_interacted=true; max-age=1209600; path=/; samesite=strict";
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'cit_mark_high_engagement', user_id: '<?php echo get_current_user_id(); ?>' })
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
    if ( ! class_exists( 'WooCommerce' ) ) return;

    $adverts = function_exists('lee_dev_get_intent_based_product_9384') ? lee_dev_get_intent_based_product_9384() : null;
    if (!$adverts || !isset($adverts['primary'])) return;

    $current_month = (int) date('n');
    $seasonal_badge = ($current_month >= 1 && $current_month <= 4) ? '<span class="uk-badge uk-margin-small-right" style="background:#e67e22;">Seasonal Priority</span>' : '';
    ?>
    <div class="uk-child-width-1-1 uk-child-width-1-2@m" uk-grid>
        <div>
            <div class="cit-dashboard-offer" style="margin-top: 30px; padding: 20px; border: 1px solid #e5e5e5; border-radius: 5px;">
                <span class="uk-label" style="font-size:11px; margin-bottom:10px;background-color:#e1ad01;padding:3px 10px;">Recommended</span>
                <h3 class="uk-text-spot1 uk-margin-remove-top"><?php echo $seasonal_badge; ?><?php echo esc_html($adverts['primary']['title']); ?></h3>
                <p class="uk-margin-medium-bottom" style="color:#999;"><?php echo esc_html($adverts['primary']['desc']); ?></p>
                <a href="<?php echo esc_url($adverts['primary']['url']); ?>" class="uk-button-spot1"><?php echo esc_html($adverts['primary']['btn']); ?></a>
            </div>
        </div>
        <?php if (isset($adverts['secondary'])) : ?>
            <div>
                <div class="cit-dashboard-offer" style="margin-top: 30px; padding: 20px; border: 1px solid #e5e5e5; border-radius: 5px;">
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
    if (!is_singular($allowed_types) || !function_exists('lee_dev_is_ready_8293') || !lee_dev_is_ready_8293()) return;

    $sectors_to_track = get_post_meta(get_the_ID(), '_cit_tracking_labels', true);
    if (empty($sectors_to_track) || !is_array($sectors_to_track)) return;

    $consent_cookie = $_COOKIE['cookieyes-consent'] ?? '';
    if (strpos($consent_cookie, 'functional:yes') === false && strpos($consent_cookie, 'analytics:yes') === false) return; 

    $user_id = get_current_user_id();
    if (!$user_id) return;

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
        if ( wp_verify_nonce($_POST['user_interests_nonce'], 'cit_save_user_interests') ) {
            if ( isset($_POST['cit_disable_tracking']) ) {
                update_user_meta($user_id, 'cit_disable_tracking', 1);
                delete_user_meta($user_id, 'user_interest_map');
                delete_user_meta($user_id, 'user_search_history');
            } else {
                delete_user_meta($user_id, 'cit_disable_tracking');
            }
            $selected_interests = isset($_POST['user_interests']) ? array_map('sanitize_text_field', $_POST['user_interests']) : [];
            update_user_meta($user_id, 'manual_interests', $selected_interests);
            echo '<div style="background:#e7f6ec; color:#2271b1; padding:15px; margin-bottom:20px; border-radius:4px; font-weight:500;">Preferences updated successfully.</div>';
        }

        if (isset($_POST['cit_clear_data'])) {
            delete_user_meta($user_id, 'user_interest_map');
            delete_user_meta($user_id, 'user_search_history');
            delete_user_meta($user_id, 'manual_interests');
            echo '<div style="background:#fbeae5; color:#c62828; padding:15px; margin-bottom:20px; border-radius:4px; font-weight:500;">All custom interest analytics data has been wiped.</div>';
        }
    }

    $dictionary = get_option('cit_dynamic_keyword_dictionary', []);
    $labels_map = function_exists('lee_dev_get_active_categories_5921') ? lee_dev_get_active_categories_5921() : [];

    $is_opted_out = (bool) get_user_meta($user_id, 'cit_disable_tracking', true);
    $saved_manual = get_user_meta($user_id, 'manual_interests', true) ?: [];
    ?>
    <div class="cit-preferences-wrap">
        <h3>Personalise Your Experience</h3>
        <p style="margin-bottom: 20px;">Select the sectors or services you are interested in.</p>
        
        <form method="post" action="">
            <input type="hidden" name="user_interests_nonce" value="<?php echo wp_create_nonce('cit_save_user_interests'); ?>">
            <ul uk-accordion="multiple: true" class="uk-accordion uk-remove-before">
                <?php 
                $count = 0;
                foreach ($dictionary as $group_key => $slugs_array) : 
                    if ( empty($slugs_array) ) continue;
                    
                    $group_title = isset($labels_map[$group_key]) ? $labels_map[$group_key] : ucfirst($group_key);
                    $group_id = 'group_' . $count;
                    $count++;
                ?>
                    <li style="border-bottom: 1px solid #eee; padding-left:0; margin-top:0px;">
                        <div class="cit-accordion-header" style="display: flex; align-items: center; justify-content: space-between; padding: 15px 0; cursor: pointer;">
                            <span style="font-size: 1.1rem; font-weight: 600; color: #333;"><?php echo esc_html($group_title); ?></span>
                            <div style="display: flex; align-items: center; gap: 20px;">
                                <label class="cit-interest-item" style="margin-bottom: 0; padding-left: 25px; display: flex; align-items: center;" onclick="event.stopPropagation();">
                                    <input type="checkbox" class="cit-select-all" data-target="<?php echo $group_id; ?>">
                                    <span class="cit-checkmark"></span>
                                    <span style="font-size: 11px; color: #999; text-transform: uppercase; font-weight: bold; margin-left: 5px;">Select All</span>
                                </label>
                                <span class="cit-accordion-icon"></span>
                            </div>
                        </div>

                        <div class="uk-accordion-content" id="<?php echo $group_id; ?>" style="margin-top: 0; padding-bottom: 20px;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; padding-left: 20px;">
                                <?php foreach ($slugs_array as $slug) : 
                                    $display_name = esc_html(ucwords(str_replace('-', ' ', $slug)));
                                ?>
                                    <label class="cit-interest-item">
                                        <input type="checkbox" name="user_interests[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $saved_manual)); ?>>
                                        <span class="cit-checkmark"></span>
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
                    <label class="cit-interest-item" style="margin-bottom: 0; flex-shrink: 0; position: relative;">
                        <input type="checkbox" name="cit_disable_tracking" value="1" <?php checked($is_opted_out, true); ?> />
                        <span class="cit-checkmark"></span>
                    </label>
                    <div style="flex: 1; line-height: 1.4;">
                        <strong style="font-size: 14px; color: #333; display: block; margin-bottom: 4px;">Show generic announcements instead of tailored recommendations</strong>
                        <p style="margin: 0; font-size: 0.9em; color: #666;">Checking this stops us from analysing your search terms and page views to predict your interests. Turning this on will automatically delete your existing automated profiles.</p>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 15px; border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px;">
                <button type="submit" name="cit_save_prefs" class="uk-button-spot1">Save Preferences</button>
                <button type="submit" name="cit_clear_data" class="uk-button-spot2" onclick="return confirm('Erase all data?')">Clear My Data</button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const headers = document.querySelectorAll('.cit-accordion-header');
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const li = this.closest('li');
                if(typeof UIkit !== 'undefined') {
                    UIkit.accordion(li.closest('.uk-accordion')).toggle(li);
                }
            });
        });

        function updateSelectAllState(container, selectAllBox) {
            const checkboxes = container.querySelectorAll('input[type="checkbox"]:not(.cit-select-all)');
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;

            if (checkedCount === 0) {
                selectAllBox.checked = false;
                selectAllBox.indeterminate = false;
                selectAllBox.closest('.cit-interest-item').classList.remove('cit-partial');
            } else if (checkedCount === checkboxes.length) {
                selectAllBox.checked = true;
                selectAllBox.indeterminate = false;
                selectAllBox.closest('.cit-interest-item').classList.remove('cit-partial');
            } else {
                selectAllBox.checked = false;
                selectAllBox.indeterminate = true;
                selectAllBox.closest('.cit-interest-item').classList.add('cit-partial');
            }
        }

        const selectAllBoxes = document.querySelectorAll('.cit-select-all');
        selectAllBoxes.forEach(box => {
            const targetId = box.getAttribute('data-target');
            const container = document.getElementById(targetId);
            if (container) {
                updateSelectAllState(container, box);
                container.querySelectorAll('input[type="checkbox"]').forEach(child => {
                    child.addEventListener('change', function() { updateSelectAllState(container, box); });
                });
            }
        });

        selectAllBoxes.forEach(box => {
            box.addEventListener('change', function() {
                const targetId = this.getAttribute('data-target');
                const container = document.getElementById(targetId);
                container.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = this.checked; });
                this.closest('.cit-interest-item').classList.remove('cit-partial');
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
