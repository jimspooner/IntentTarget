<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'lee_dev_get_filtered_taxonomy_groups_7316' ) ) {
    /**
     * Compatibility wrapper.
     *
     * Returns the hardcoded five-bucket intent matrix as the 'groups' payload
     * along with the centralised stop-word blacklist used by the scanner. The
     * function name is preserved to avoid touching every caller, but the
     * behaviour now reflects the IntentTarget Pro fixed intent matrix.
     */
    function lee_dev_get_filtered_taxonomy_groups_7316() {
        $intent_groups = function_exists( 'lee_dev_get_intent_categories_3812' )
            ? lee_dev_get_intent_categories_3812()
            : array();

        $blacklist = function_exists( 'lee_dev_get_intent_scanner_blacklist_9447' )
            ? lee_dev_get_intent_scanner_blacklist_9447()
            : array();

        return array(
            'groups'    => $intent_groups,
            'blacklist' => $blacklist,
        );
    }
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
?>
<div class="wrap">
    <?php
    // =========================================================================
    // LICENCE ACTIVATION MANAGER COMPONENT
    // =========================================================================
    $licence_status = get_option( 'lee_dev_licence_status', 'unauthorised' );
    $licence_key    = get_option( 'lee_dev_licence_key', '' );
    
    if ( isset( $_GET['licence-updated'] ) ) {
        $status_update = sanitize_text_field( $_GET['licence-updated'] );
        if ( $status_update === 'authorised' ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Licence Activated:</strong> This website is now fully authorised for audience interest profile analysis.</p></div>';
        } elseif ( $status_update === 'deactivated' ) {
            echo '<div class="notice notice-info is-dismissible"><p><strong>Licence Deactivated:</strong> Access has been deactivated.</p></div>';
        } elseif ( $status_update === 'invalid' ) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Licence Denied:</strong> The key provided is invalid. Please supply a valid 10-character key.</p></div>';
        } elseif ( $status_update === 'empty' ) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>Empty Licence Key:</strong> Please provide a key for activation.</p></div>';
        }
    }

    if ( isset( $_GET['debug-updated'] ) && $_GET['debug-updated'] === 'true' ) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>Debug logging updated:</strong> Diagnostic tracing preference saved.</p></div>';
    }

    $debug_logging_enabled = function_exists( 'lee_dev_debug_logging_is_enabled_2846' ) && lee_dev_debug_logging_is_enabled_2846();
    ?>
    <div style="background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid <?php echo ( $licence_status === 'authorised' ) ? '#46b450' : '#d63638'; ?>; padding: 15px 20px; margin-bottom: 20px; border-radius: 4px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h3 style="margin: 0 0 5px 0; font-size: 15px; font-weight: bold; color: #1d2327;">
                Licence Verification Status: 
                <span style="color: <?php echo ( $licence_status === 'authorised' ) ? '#46b450' : '#d63638'; ?>; text-transform: uppercase;">
                    <?php echo esc_html( $licence_status ); ?>
                </span>
            </h3>
            <p style="margin: 0; font-size: 13px; color: #646970;">
                <?php if ( $licence_status === 'authorised' ) : ?>
                    Your website is active and authorised to track and display target marketing promotions.
                <?php else : ?>
                    Tracking is running in sandbox mode. Please enter an authorised licence key to enable full production tracking.
                <?php endif; ?>
            </p>
        </div>
        <form method="post" action="" style="display: flex; gap: 10px; align-items: center;">
            <?php wp_nonce_field( 'itp_licence_action', 'itp_licence_nonce' ); ?>
            <?php if ( $licence_status !== 'authorised' ) : ?>
                <input type="hidden" name="itp_licence_action_type" value="activate" />
                <input type="text" name="itp_licence_key" value="<?php echo esc_attr( $licence_key ); ?>" placeholder="ITP-XXXX-XXXX-XXXX" style="padding: 6px 10px; font-size: 13px; width: 220px; border: 1px solid #8c8f94; border-radius: 4px;" />
                <input type="submit" name="itp_licence_submit" class="button button-primary" value="Activate Licence" />
            <?php else : ?>
                <input type="hidden" name="itp_licence_action_type" value="deactivate" />
                <span style="font-family: monospace; font-size: 13px; color: #646970; background: #f6f7f7; padding: 6px 12px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <?php echo esc_html( substr( $licence_key, 0, 6 ) . '...' . substr( $licence_key, -4 ) ); ?>
                </span>
                <input type="submit" name="itp_licence_submit" class="button button-secondary" value="Deactivate" />
            <?php endif; ?>
        </form>
    </div>

    <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px 20px; margin-bottom: 20px; border-radius: 4px;">
        <form method="post" action="" style="display:flex; align-items:center; justify-content:space-between; gap:15px;">
            <?php wp_nonce_field( 'itp_debug_logging_toggle_nonce' ); ?>
            <div>
                <h3 style="margin: 0 0 5px 0; font-size: 15px; font-weight: bold; color: #1d2327;">Debug Logging</h3>
                <p style="margin: 0; font-size: 13px; color: #646970;">Write IntentTarget Pro diagnostic trace entries to the WordPress debug log.</p>
            </div>
            <label style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="itp_debug_logging_enabled" value="yes" <?php checked( $debug_logging_enabled ); ?> />
                <span><?php echo esc_html( $debug_logging_enabled ? 'Debug logging is on' : 'Debug logging is off' ); ?></span>
            </label>
            <input type="submit" name="itp_debug_logging_toggle" class="button button-secondary" value="Update Debug Logging" />
        </form>
    </div>

    <?php if ( $licence_status !== 'authorised' ) : ?>
        <div class="notice notice-warning" style="margin-top: 20px;">
            <p><strong><?php echo esc_html__( 'Licence required:', 'intenttarget-pro' ); ?></strong> <?php echo esc_html__( 'IntentTarget Pro content, tracking, adverts, scans, and settings are disabled until a valid licence code is activated.', 'intenttarget-pro' ); ?></p>
        </div>
    </div>
    <?php return; ?>
    <?php endif; ?>

    <h1>IntentTarget - Core Management Suite</h1>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=itp-search-dashboard&tab=keyword_scanner" class="nav-tab <?php echo ($active_tab === 'keyword_scanner') ? 'nav-tab-active' : ''; ?>">Scanner & Dictionary</a>
        <a href="?page=itp-search-dashboard&tab=popout_adverts" class="nav-tab <?php echo ($active_tab === 'popout_adverts') ? 'nav-tab-active' : ''; ?>">Dynamic Adverts</a>
        <a href="?page=itp-search-dashboard&tab=dashboard" class="nav-tab <?php echo ($active_tab === 'dashboard') ? 'nav-tab-active' : ''; ?>">Dashboard & Search Log</a>
        <a href="?page=itp-search-dashboard&tab=access_control" class="nav-tab <?php echo ($active_tab === 'access_control') ? 'nav-tab-active' : ''; ?>">Access Control</a>
        <?php 
        // Allow Pro add-ons to inject their own tabs here
        do_action( 'itp_core_dashboard_tabs', $active_tab ); 
        ?>
    </nav>

    <?php 
    // =========================================================================
    // TAB 1: DASHBOARD & SEARCH LOGS AUDIT
    // =========================================================================
    if ( $active_tab === 'dashboard' ) : 
        global $wpdb;
        $table_name = $wpdb->prefix . 'itp_search_feedback';
        
        // Handle search logs purge request
        if ( isset($_POST['itp_purge_logs']) && current_user_can('manage_options') ) {
            check_admin_referer('itp_purge_logs_action', 'itp_purge_logs_nonce');
            $wpdb->query("TRUNCATE TABLE $table_name");
            echo '<div class="notice notice-success is-dismissible"><p>Search history database logs successfully purged.</p></div>';
        }

        $search_logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submitted_at DESC LIMIT 250");
        ?>
        <div style="background: #fff; padding: 25px; margin-top: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h2 style="margin:0;">Live Audience Keyword Feedback Log</h2>
                <form method="post" action="" onsubmit="return confirm('Purge search feedback?');">
                    <?php wp_nonce_field('itp_purge_logs_action', 'itp_purge_logs_nonce'); ?>
                    <input type="submit" name="itp_purge_logs" class="button button-link-delete" value="Purge Audit Log Data" style="color:#d63638; text-decoration:none;" />
                </form>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 20%;">Date / Time</th>
                        <th style="width: 20%;">User Profile</th>
                        <th style="width: 30%;">User Search Input Query</th>
                        <th style="width: 15%;">Result Verified</th>
                        <th style="width: 15%;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty($search_logs) ) : foreach ( $search_logs as $log ) : 
                        $user = get_userdata($log->user_id);
                        $user_display = $user ? esc_html($user->display_name . ' (' . $user->user_email . ')') : 'Anonymous Session';
                        $status_color = ($log->found_result === 'yes') ? '#46b450' : (($log->found_result === 'no') ? '#dc3232' : '#999');
                    ?>
                        <tr>
                            <td><?php echo esc_html($log->submitted_at); ?></td>
                            <td><?php echo $user_display; ?></td>
                            <td><strong>"<?php echo esc_html($log->search_query); ?>"</strong></td>
                            <td><span style="font-weight:bold; color:<?php echo $status_color; ?>;"><?php echo esc_html(ucfirst($log->found_result)); ?></span></td>
                            <td><small><?php echo esc_html($log->feedback_notes); ?></small></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="5">No searches have been logged yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php 
    // =========================================================================
    // TAB 2: DYNAMIC ADVERTS CONFIGURATOR
    // =========================================================================
    elseif ( $active_tab === 'popout_adverts' ) : 
        if ( isset( $_GET['priority-updated'] ) && $_GET['priority-updated'] === 'true' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Global site purpose priority saved.</p></div>';
        }

        $valid_intents = function_exists( 'lee_dev_get_valid_site_intents_6048' ) ? lee_dev_get_valid_site_intents_6048() : array();
        $saved_priority = get_option( 'itp_global_priority', array_keys( $valid_intents ) );
        if ( ! is_array( $saved_priority ) ) {
            $saved_priority = array_keys( $valid_intents );
        }
        $saved_priority = array_values( array_unique( array_filter( $saved_priority, function( $intent_key ) use ( $valid_intents ) {
            return isset( $valid_intents[$intent_key] );
        } ) ) );
        foreach ( array_keys( $valid_intents ) as $intent_key ) {
            if ( ! in_array( $intent_key, $saved_priority, true ) ) {
                $saved_priority[] = $intent_key;
            }
        }
        ?>
        <div style="background:#fff; padding:25px; border:1px solid #ccd0d4; margin-top:15px; border-radius:4px;">
            <h2 style="margin-top:0;">Global Site Purpose</h2>
            <p class="description">Rank the main purpose of this site. IntentTarget Pro will attempt to deliver the first available advert, then waterfall to the next priority if no suitable advert can be delivered.</p>

            <form method="post" action="">
                <?php wp_nonce_field( 'itp_save_global_priority_action', 'itp_save_global_priority_nonce' ); ?>
                <table class="form-table">
                    <?php foreach ( array_keys( $valid_intents ) as $priority_index => $intent_key ) : ?>
                        <tr>
                            <th scope="row"><label for="itp_global_priority_<?php echo esc_attr( $priority_index ); ?>">Priority <?php echo esc_html( $priority_index + 1 ); ?></label></th>
                            <td>
                                <select id="itp_global_priority_<?php echo esc_attr( $priority_index ); ?>" name="itp_global_priority[]" class="regular-text">
                                    <?php foreach ( $valid_intents as $option_key => $option_label ) : ?>
                                        <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $saved_priority[$priority_index] ?? '', $option_key ); ?>>
                                            <?php echo esc_html( $option_label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p class="submit" style="padding-top:10px;"><input type="submit" name="itp_save_global_priority" class="button button-primary button-large" value="Save Global Site Purpose" /></p>
            </form>
        </div>

    <?php 
    // =========================================================================
    // TAB 3: AUTOMATED KEYPHRASE SCANNER
    // =========================================================================
    elseif ( $active_tab === 'keyword_scanner' ) : 
        $taxonomy_groups = lee_dev_get_filtered_taxonomy_groups_7316();
        $master_groups = $taxonomy_groups['groups'];
        $taxonomy_blacklist = $taxonomy_groups['blacklist'];

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_options') ) {
            
            if ( isset($_POST['itp_trigger_scan']) ) {
                check_admin_referer('itp_scanner_action', 'itp_scanner_nonce');

                $blacklist = $taxonomy_blacklist;
                $candidate_pool = array();

                $batch_limit = function_exists( 'lee_dev_get_intent_dictionary_capacity_5083' )
                    ? lee_dev_get_intent_dictionary_capacity_5083()
                    : 50;

                $assets_scanned = array(
                    'product' => 0,
                    'post'    => 0,
                    'page'    => 0,
                );

                /**
                 * Prong 1 — WooCommerce products: title + product taxonomies + content (incl. excerpt + short description).
                 */
                if ( class_exists( 'WooCommerce' ) ) {
                    $product_query = new WP_Query( array(
                        'post_type'              => 'product',
                        'post_status'            => 'publish',
                        'posts_per_page'         => $batch_limit,
                        'orderby'                => 'modified',
                        'order'                  => 'DESC',
                        'no_found_rows'          => true,
                        'update_post_term_cache' => false,
                        'update_post_meta_cache' => false,
                    ) );
                    if ( ! is_wp_error( $product_query ) && ! empty( $product_query->posts ) ) {
                        foreach ( $product_query->posts as $product_post ) {
                            $assets_scanned['product']++;
                            $candidate_pool = array_merge(
                                $candidate_pool,
                                lee_dev_extract_candidate_phrases_from_text_7506( $product_post->post_title, $blacklist ),
                                lee_dev_extract_candidate_phrases_from_text_7506( $product_post->post_content, $blacklist ),
                                lee_dev_extract_candidate_phrases_from_text_7506( $product_post->post_excerpt, $blacklist )
                            );
                            if ( function_exists( 'wc_get_product' ) ) {
                                $wc_product = wc_get_product( $product_post->ID );
                                if ( $wc_product ) {
                                    $candidate_pool = array_merge(
                                        $candidate_pool,
                                        lee_dev_extract_candidate_phrases_from_text_7506( $wc_product->get_short_description(), $blacklist )
                                    );
                                }
                            }
                            $taxonomy_phrases = lee_dev_collect_asset_taxonomy_phrases_5912( $product_post->ID, 'product' );
                            foreach ( $taxonomy_phrases as $tax_phrase ) {
                                $candidate_pool = array_merge(
                                    $candidate_pool,
                                    lee_dev_extract_candidate_phrases_from_text_7506( $tax_phrase, $blacklist )
                                );
                            }
                        }
                        wp_reset_postdata();
                    }
                }

                /**
                 * Prong 2 — Posts: title + assigned taxonomy terms only (no body).
                 */
                $post_query = new WP_Query( array(
                    'post_type'              => 'post',
                    'post_status'            => 'publish',
                    'posts_per_page'         => $batch_limit,
                    'orderby'                => 'modified',
                    'order'                  => 'DESC',
                    'no_found_rows'          => true,
                    'update_post_term_cache' => false,
                    'update_post_meta_cache' => false,
                ) );
                if ( ! is_wp_error( $post_query ) && ! empty( $post_query->posts ) ) {
                    foreach ( $post_query->posts as $blog_post ) {
                        $assets_scanned['post']++;
                        $candidate_pool = array_merge(
                            $candidate_pool,
                            lee_dev_extract_candidate_phrases_from_text_7506( $blog_post->post_title, $blacklist )
                        );
                        $taxonomy_phrases = lee_dev_collect_asset_taxonomy_phrases_5912( $blog_post->ID, 'post' );
                        foreach ( $taxonomy_phrases as $tax_phrase ) {
                            $candidate_pool = array_merge(
                                $candidate_pool,
                                lee_dev_extract_candidate_phrases_from_text_7506( $tax_phrase, $blacklist )
                            );
                        }
                    }
                    wp_reset_postdata();
                }

                /**
                 * Prong 3 — Pages: title + content + public meta (incl. ACF fields).
                 */
                $page_query = new WP_Query( array(
                    'post_type'              => 'page',
                    'post_status'            => 'publish',
                    'posts_per_page'         => $batch_limit,
                    'orderby'                => 'modified',
                    'order'                  => 'DESC',
                    'no_found_rows'          => true,
                    'update_post_term_cache' => false,
                    'update_post_meta_cache' => false,
                ) );
                if ( ! is_wp_error( $page_query ) && ! empty( $page_query->posts ) ) {
                    foreach ( $page_query->posts as $page_post ) {
                        $assets_scanned['page']++;
                        $candidate_pool = array_merge(
                            $candidate_pool,
                            lee_dev_extract_candidate_phrases_from_text_7506( $page_post->post_title, $blacklist ),
                            lee_dev_extract_candidate_phrases_from_text_7506( $page_post->post_content, $blacklist )
                        );
                        $meta_phrases = lee_dev_collect_asset_public_meta_phrases_8137( $page_post->ID );
                        foreach ( $meta_phrases as $meta_phrase ) {
                            $candidate_pool = array_merge(
                                $candidate_pool,
                                lee_dev_extract_candidate_phrases_from_text_7506( $meta_phrase, $blacklist )
                            );
                        }
                    }
                    wp_reset_postdata();
                }

                $candidate_pool = array_values( array_unique( array_filter( $candidate_pool ) ) );
                $clean_rebuilt_dict = lee_dev_bucket_candidates_into_intents_6024( $candidate_pool );

                update_option('itp_dynamic_keyword_dictionary', $clean_rebuilt_dict);

                $bucket_summary = array();
                foreach ( $master_groups as $intent_slug => $intent_label ) {
                    $bucket_summary[] = sprintf( '%s: %d', $intent_label, isset( $clean_rebuilt_dict[$intent_slug] ) ? count( $clean_rebuilt_dict[$intent_slug] ) : 0 );
                }

                printf(
                    '<div class="notice notice-success is-dismissible"><p><strong>Three-pronged intent scan complete.</strong> Products: %d, Posts: %d, Pages: %d. Phrases distributed — %s.</p></div>',
                    (int) $assets_scanned['product'],
                    (int) $assets_scanned['post'],
                    (int) $assets_scanned['page'],
                    esc_html( implode( ' • ', $bucket_summary ) )
                );
            
            } elseif ( isset($_POST['itp_save_dictionary']) ) {
                check_admin_referer('itp_scanner_action', 'itp_scanner_nonce');

                $raw_inputs = isset($_POST['itp_dict']) ? (array) $_POST['itp_dict'] : array();
                $cap = function_exists( 'lee_dev_get_intent_dictionary_capacity_5083' )
                    ? lee_dev_get_intent_dictionary_capacity_5083()
                    : 50;
                $new_dict = array();

                foreach ( $master_groups as $key => $label ) {
                    $raw_value = isset( $raw_inputs[$key] ) ? wp_unslash( $raw_inputs[$key] ) : '';
                    if ( ! is_string( $raw_value ) || $raw_value === '' ) {
                        $new_dict[$key] = array();
                        continue;
                    }

                    $phrases = explode( ',', $raw_value );
                    $clean_phrases = array_map( function( $p ) {
                        return strtolower( trim( wp_strip_all_tags( (string) $p ) ) );
                    }, $phrases );
                    $raw_unique = array_values( array_unique( array_filter( $clean_phrases ) ) );

                    $filtered_phrases = array();
                    foreach ( $raw_unique as $phrase ) {
                        $is_duplicate_word = false;
                        if ( strpos( $phrase, ' ' ) === false ) {
                            foreach ( $raw_unique as $comparison_phrase ) {
                                if ( $phrase !== $comparison_phrase && strpos( $comparison_phrase, $phrase ) !== false ) {
                                    $is_duplicate_word = true;
                                    break;
                                }
                            }
                        }
                        if ( ! $is_duplicate_word ) {
                            $filtered_phrases[] = $phrase;
                        }
                    }

                    $new_dict[$key] = array_values( array_slice( $filtered_phrases, 0, $cap ) );
                }

                // Guarantee every hardcoded intent key exists even if user blanked a textarea.
                foreach ( array_keys( $master_groups ) as $intent_slug ) {
                    if ( ! isset( $new_dict[$intent_slug] ) ) {
                        $new_dict[$intent_slug] = array();
                    }
                }

                update_option( 'itp_dynamic_keyword_dictionary', $new_dict );
                echo '<div class="notice notice-success is-dismissible"><p>Intent keyphrase dictionaries updated. Each bucket capped at ' . (int) $cap . ' phrases.</p></div>';
            }
        }

        $dictionary = get_option('itp_dynamic_keyword_dictionary', []);
        if ( ! is_array($dictionary) ) {
            $dictionary = [];
        }
        $content_scan_status = get_option( 'itp_content_scan_status_7394', array() );
        if ( ! is_array( $content_scan_status ) ) {
            $content_scan_status = array();
        }
        ?>
        <?php
        $intent_descriptions = array(
            'transactional-intent' => 'Buyer signals — buy, order, price, quote, checkout, deal, discount, hire, book, subscribe.',
            'informational-intent' => 'Researcher signals — how, what, why, guide, tutorial, learn, tips, definition, examples.',
            'engagement-intent'    => 'Community signals — contact, support, follow, share, join, members, events, webinars.',
            'commercial-intent'    => 'Evaluation signals — best, top, vs, review, compare, alternatives, recommended, ranked.',
            'specialist-intent'    => 'Niche / business-specific catch-all for terms that do not match the upstream lexicons.',
        );
        ?>
        <div style="background:#fff; padding:25px; border:1px solid #ccd0d4; border-radius:4px; margin-top:15px;">
            <h3>🤖 Hardcoded Intent Dictionary Matrix</h3>
            <p class="description">This panel governs the exact tracking keyphrases mapped to the five hardcoded intent buckets. Each bucket holds up to <?php echo (int) ( function_exists( 'lee_dev_get_intent_dictionary_capacity_5083' ) ? lee_dev_get_intent_dictionary_capacity_5083() : 50 ); ?> phrases.</p>
            <div style="background:#f6f7f7; padding:15px; border-left:4px solid #46b450; border-radius:3px; margin-top:15px;">
                <strong>Hourly Content Tracking Status</strong><br/>
                <span class="description">
                    <?php if ( ! empty( $content_scan_status['last_run'] ) ) : ?>
                        Last authorised background scan: <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $content_scan_status['last_run'] ) ); ?>.
                        Assets scanned this cycle: <?php echo esc_html( absint( $content_scan_status['assets_scanned'] ?? 0 ) ); ?>.
                        Categorised matches: <?php echo esc_html( absint( $content_scan_status['matches_found'] ?? 0 ) ); ?>.
                        <?php echo esc_html( $content_scan_status['message'] ?? '' ); ?>
                    <?php else : ?>
                        The hourly content tracking scan is queued and will process up to 50 published pages and posts per cycle.
                    <?php endif; ?>
                </span>
            </div>
            
            <form method="post" action="" style="margin:20px 0;">
                <?php wp_nonce_field('itp_scanner_action', 'itp_scanner_nonce'); ?>
                <div style="background:#f0f6fa; padding:15px; border-left:4px solid #2271b1; border-radius:3px; display:flex; align-items:center; justify-content:space-between;">
                    <div style="max-width:70%;">
                        <strong>Run Three-Pronged Intent Phrase Scan</strong><br/>
                        <span class="description">Products are scanned across title, taxonomies and content. Posts are scanned across title and taxonomies. Pages are scanned across title, content and public meta (including ACF fields). Phrases are then classified into the five hardcoded intent buckets.</span>
                    </div>
                    <input type="submit" name="itp_trigger_scan" class="button button-secondary" value="Scan Website Structure Now" />
                </div>
            </form>

            <form method="post" action="">
                <?php wp_nonce_field('itp_scanner_action', 'itp_scanner_nonce'); ?>
                <table class="form-table" style="margin-top:20px;">
                    <?php foreach ( $master_groups as $key => $label ) :
                        $current_phrases = isset($dictionary[$key]) && is_array( $dictionary[$key] ) ? implode(', ', $dictionary[$key]) : '';
                        $current_count   = isset($dictionary[$key]) && is_array( $dictionary[$key] ) ? count( $dictionary[$key] ) : 0;
                        $cap_value       = (int) ( function_exists( 'lee_dev_get_intent_dictionary_capacity_5083' ) ? lee_dev_get_intent_dictionary_capacity_5083() : 50 );
                        $intent_blurb    = isset( $intent_descriptions[$key] ) ? $intent_descriptions[$key] : '';
                    ?>
                        <tr style="border-top:1px solid #eee;">
                            <th style="width:240px; padding:20px 0; vertical-align:top;">
                                <strong><?php echo esc_html($label); ?></strong><br/>
                                <span class="description">Intent Key: <code><?php echo esc_html($key); ?></code></span><br/>
                                <span class="description" style="font-size:11px; color:#646970;"><?php echo esc_html( $current_count ); ?> / <?php echo esc_html( $cap_value ); ?> phrases stored.</span>
                            </th>
                            <td style="padding:15px 0;">
                                <textarea name="itp_dict[<?php echo esc_attr($key); ?>]" rows="3" class="large-text" style="font-family:monospace; font-size:13px;" placeholder="comma separated intent keyphrases"><?php echo esc_textarea($current_phrases); ?></textarea>
                                <p class="description" style="margin-top:5px;"><?php echo esc_html( $intent_blurb ); ?> Separate keyphrases with commas. Cap of <?php echo (int) $cap_value; ?> per bucket is enforced on save.</p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                
                <p class="submit" style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
                    <input type="submit" name="itp_save_dictionary" class="button button-primary button-large" value="Save Keyphrase Dictionaries" />
                </p>
            </form>
        </div>

    <?php 
    // =========================================================================
    // TAB 4: ACCESS CONFIGURATION
    // =========================================================================
    elseif ( $active_tab === 'access_control' ) : 
        if ( function_exists('lee_dev_render_access_control_tab_content_9281') ) {
            lee_dev_render_access_control_tab_content_9281();
        } else {
            // Decoupled Access Rendering Logic directly inside the view
            $wp_roles = wp_roles()->get_names();
            $allowed_roles = get_option( 'itp_allowed_tracking_roles', array() );
            
            if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
                echo '<div class="updated notice is-dismissible" style="margin: 15px 0 0 0;"><p>Access configurations updated.</p></div>';
            }
            ?>
            <div style="background: #fff; padding: 25px; margin-top: 15px; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 650px;">
                <h3 style="margin-top:0;">Authorised Tracking User Roles</h3>
                <p class="description" style="margin-bottom: 25px;">Tick which local site user roles will activate the broader analytics engine execution.</p>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'itp_save_access_settings_nonce' ); ?>
                    <table class="form-table" style="margin-bottom: 20px;">
                        <tr>
                            <td style="padding:0;">
                                <?php foreach ( $wp_roles as $role_slug => $role_name ) : ?>
                                    <div style="margin-bottom: 14px;">
                                        <label style="font-size:14px; display:inline-flex; align-items:center; cursor:pointer;">
                                            <input type="checkbox" name="itp_roles[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $allowed_roles, true ) ); ?> style="margin-right:10px;" />
                                            <?php echo esc_html( $role_name ); ?> 
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                    <p class="submit" style="margin: 0;"><input type="submit" name="itp_save_access_settings" class="button button-primary button-large" value="Update Roles" /></p>
                </form>
            </div>
            <?php
        }

    else : 
        do_action( 'itp_core_dashboard_tab_content_' . $active_tab );
    endif; 
    ?>
</div>
</div>