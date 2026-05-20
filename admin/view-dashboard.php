<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'lee_dev_get_filtered_taxonomy_groups_7316' ) ) {
    function lee_dev_get_filtered_taxonomy_groups_7316() {
        $default_blacklist = array(
            'uncategorised',
            'uncategorized',
            'exclude-from-catalog',
            'exclude-from-search',
            'featured',
            'the',
            'and',
            'for',
            'with',
            'from',
            'this',
            'that',
            'your',
            'will',
            'have'
        );

        $filtered_blacklist = apply_filters( 'lee_dev_taxonomy_blacklist_7316', $default_blacklist );
        if ( ! is_array( $filtered_blacklist ) ) {
            $filtered_blacklist = $default_blacklist;
        }

        $blacklist = array_map( 'sanitize_title', $filtered_blacklist );
        $term_groups = array();
        $target_taxonomies = array( 'category' );
        if ( class_exists( 'WooCommerce' ) ) {
            $target_taxonomies[] = 'product_cat';
        }

        $terms = get_terms( array(
            'taxonomy'   => $target_taxonomies,
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return array(
                'groups'    => $term_groups,
                'blacklist' => $blacklist,
            );
        }

        foreach ( $terms as $term ) {
            if ( ! is_object( $term ) || empty( $term->slug ) ) {
                continue;
            }

            $term_slug = sanitize_title( $term->slug );
            if ( in_array( $term_slug, $blacklist, true ) ) {
                continue;
            }

            $term_groups[$term_slug] = $term->name . ( class_exists( 'WooCommerce' ) && $term->taxonomy === 'product_cat' ? ' (Product Category)' : ' (Category)' );
        }

        return array(
            'groups'    => $term_groups,
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

    <?php if ( $licence_status !== 'authorised' ) : ?>
        <div class="notice notice-warning" style="margin-top: 20px;">
            <p><strong><?php echo esc_html__( 'Licence required:', 'intenttarget-pro' ); ?></strong> <?php echo esc_html__( 'IntentTarget Pro content, tracking, adverts, scans, and settings are disabled until a valid licence code is activated.', 'intenttarget-pro' ); ?></p>
        </div>
    </div>
    <?php return; ?>
    <?php endif; ?>

    <h1>IntentTarget Pro - Core Management Suite</h1>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=itp-search-dashboard&tab=keyword_scanner" class="nav-tab <?php echo ($active_tab === 'keyword_scanner') ? 'nav-tab-active' : ''; ?>">Scanner & Dictionary</a>
        <a href="?page=itp-search-dashboard&tab=popout_adverts" class="nav-tab <?php echo ($active_tab === 'popout_adverts') ? 'nav-tab-active' : ''; ?>">Dynamic Adverts</a>
        <a href="?page=itp-search-dashboard&tab=dashboard" class="nav-tab <?php echo ($active_tab === 'dashboard') ? 'nav-tab-active' : ''; ?>">Dashboard & Search Log</a>
        <a href="?page=itp-search-dashboard&tab=design_customiser" class="nav-tab <?php echo ($active_tab === 'design_customiser') ? 'nav-tab-active' : ''; ?>">Advert Styling</a>
        <a href="?page=itp-search-dashboard&tab=access_control" class="nav-tab <?php echo ($active_tab === 'access_control') ? 'nav-tab-active' : ''; ?>">Access Control</a>
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
        if ( isset($_POST['itp_save_adverts']) && current_user_can('manage_options') ) {
            check_admin_referer('itp_save_adverts_action', 'itp_save_adverts_nonce');
            
            $settings = isset($_POST['itp_advert_settings']) ? $_POST['itp_advert_settings'] : [];
            update_option('itp_advert_settings', $settings);
            
            echo '<div class="notice notice-success is-dismissible"><p>Advert configurations successfully saved.</p></div>';
        }

        $opts = get_option('itp_advert_settings');
        if ( ! is_array($opts) ) {
            $opts = [];
        }
        
        $groups = function_exists('lee_dev_get_active_categories_5921') ? lee_dev_get_active_categories_5921() : [];

        $g_title = esc_attr($opts['global']['main_title'] ?? 'Discover personalised recommendations');
        $g_url   = esc_url($opts['global']['main_url'] ?? '/store/');
        $g_btn   = esc_attr($opts['global']['main_btn'] ?? 'Find Out More');
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('itp_save_adverts_action', 'itp_save_adverts_nonce'); ?>
            
            <!-- <div style="background:#fff; padding:25px; border:2px solid #e1ad01; margin: 15px 0 30px 0; border-radius:4px;">
                <h3 style="margin-top:0; color:#c49600;">Global Default Banner (Incognito & Guest Visitors)</h3>
                <p class="description">This fallback message and hyperlink display immediately to logged-out users, incognito sessions, or individuals with low target scores.</p>
                <table class="form-table" style="margin-top:10px;">
                    <tr>
                        <th style="width:200px;"><label>Default Title Text Statement</label></th>
                        <td><input type="text" name="itp_advert_settings[global][main_title]" value="<?php echo $g_title; ?>" class="large-text" /></td>
                    </tr>
                    <tr>
                        <th><label>Destination Hyperlink URL</label></th>
                        <td><input type="text" name="itp_advert_settings[global][main_url]" value="<?php echo $g_url; ?>" class="large-text" /></td>
                    </tr>
                    <tr>
                        <th><label>Link Call To Action Text</label></th>
                        <td><input type="text" name="itp_advert_settings[global][main_btn]" value="<?php echo $g_btn; ?>" class="large-text" style="max-width:250px;" /></td>
                    </tr>
                </table>
            </div> -->
            
            <h2>Targeted Marketing Segment Profiles</h2>
            <p class="description">Configure the promotional copy served dynamically when a logged-in user crosses an engagement propensity barrier (>25 score units).</p>
            <br/>
            
            <?php
            foreach ($groups as $slug => $label) {
                $id      = esc_attr($opts[$slug]['id'] ?? '');
                $m_title = esc_attr($opts[$slug]['main_title'] ?? '');
                $m_desc  = esc_textarea($opts[$slug]['main_desc'] ?? '');
                $m_url   = esc_url($opts[$slug]['main_url'] ?? '');
                $m_btn   = esc_attr($opts[$slug]['main_btn'] ?? '');
                
                $a_title = esc_attr($opts[$slug]['alt_title'] ?? '');
                $a_desc  = esc_textarea($opts[$slug]['alt_desc'] ?? '');
                $a_url   = esc_url($opts[$slug]['alt_url'] ?? '');
                $a_btn   = esc_attr($opts[$slug]['alt_btn'] ?? '');
                ?>
                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-bottom:20px; border-radius:4px;">
                    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px; color:#2271b1;"><?php echo esc_html($label); ?></h3>
                    <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                        <p><label><strong>WooCommerce Product ID (For Post-Purchase Upsell Suppression):</strong><br/>
                        <input type="number" name="itp_advert_settings[<?php echo esc_attr($slug); ?>][id]" value="<?php echo $id; ?>" style="width:100%; max-width:200px; margin-top:5px;" placeholder="e.g. 412" /></label></p>
                    <?php endif; ?>
                    
                    <div style="display:flex; gap:20px; margin-top:15px;">
                        <div style="flex:1; background:#f9f9f9; padding:15px; border:1px solid #eee; border-radius:3px;">
                            <h4 style="margin-top:0; color:#1d2327;">🎯 Primary Advert (Has Not Bought Product Yet)</h4>
                            <p><label>Title Text:<br/><input type="text" name="itp_advert_settings[<?php echo esc_attr($slug); ?>][main_title]" value="<?php echo $m_title; ?>" style="width:100%;" /></label></p>
                            <p><label>Description Text:<br/><textarea name="itp_advert_settings[<?php echo esc_attr($slug); ?>][main_desc]" rows="3" style="width:100%; margin-top:5px;"><?php echo $m_desc; ?></textarea></label></p>
                            <p><label>Destination URL Link:<br/><input type="text" name="itp_advert_settings[<?php echo esc_attr($slug); ?>][main_url]" value="<?php echo $m_url; ?>" style="width:100%;" /></label></p>
                            <p><label>Action Button Text:<br/><input type="text" name="itp_advert_settings[<?php echo esc_attr($slug); ?>][main_btn]" value="<?php echo $m_btn; ?>" style="width:100%;" /></label></p>
                        </div>
                        
                        <div style="flex:1; background:#f9f9f9; padding:15px; border:1px solid #eee; border-radius:3px;">
                            <h4 style="margin-top:0; color:#646970;">🔄 Alternative Ad Line<?php echo class_exists( 'WooCommerce' ) ? ' (Already Bought Product ID)' : ''; ?></h4>
                            <p><label>Alt Title Text:<br/><input type="text" name="itp_advert_settings[<?php echo esc_attr($slug); ?>][alt_title]" value="<?php echo $a_title; ?>" style="width:100%;" /></label></p>
                            <p><label>Alt Description Text:<br/><textarea name="itp_advert_settings[<?php echo esc_attr($slug); ?>][alt_desc]" rows="3" style="width:100%; margin-top:5px;"><?php echo $a_desc; ?></textarea></label></p>
                            <p><label>Alt Destination URL:<br/><input type="text" name="itp_advert_settings[<?php echo esc_attr($slug); ?>][alt_url]" value="<?php echo $a_url; ?>" style="width:100%;" /></label></p>
                            <p><label>Alt Action Button Text:<br/><input type="text" name="itp_advert_settings[<?php echo esc_attr($slug); ?>][alt_btn]" value="<?php echo $a_btn; ?>" style="width:100%;" /></label></p>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
            <p class="submit" style="padding-top:10px;"><input type="submit" name="itp_save_adverts" class="button button-primary button-large" value="Save All Advert Configurations" /></p>
        </form>

    <?php 
    // =========================================================================
    // TAB 3: DESIGN COLOR CUSTOMISER
    // =========================================================================
    elseif ( $active_tab === 'design_customiser' ) : 
        if ( isset($_POST['itp_save_design']) && current_user_can('manage_options') ) {
            check_admin_referer('itp_save_design_action', 'itp_save_design_nonce');
            
            $design = isset($_POST['itp_design_settings']) ? array_map('sanitize_text_field', $_POST['itp_design_settings']) : [];
            update_option('itp_design_settings', $design);
            
            echo '<div class="notice notice-success is-dismissible"><p>Visual interface colours saved successfully.</p></div>';
        }

        $colors = get_option('itp_design_settings', []);
        
        $c_heading      = esc_attr($colors['heading'] ?? '#074e85');
        $c_recommended  = esc_attr($colors['recommended'] ?? '#e1ad01');
        $c_button       = esc_attr($colors['button'] ?? '#074e85');
        $c_button_text  = esc_attr($colors['button_text'] ?? '#ffffff');
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('itp_save_design_action', 'itp_save_design_nonce'); ?>
            <div style="background:#fff; padding:25px; border:1px solid #ccd0d4; border-radius:4px; margin-top: 15px;">
                <h3 style="margin-top: 0;">Interface Presentation Colour Profiles</h3>
                <p class="description">Control the dynamic palette rendered across pop-out boxes, top message alert bars, and client account recommendations.</p>
                
                <table class="form-table" style="margin-top: 20px;">
                    <tr>
                        <th style="width: 250px;"><label>Heading & Offer Titles Color</label></th>
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
                        <th><label>Action Button Label Text Color</label></th>
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

    <?php 
    // =========================================================================
    // TAB 4: AUTOMATED KEYPHRASE SCANNER
    // =========================================================================
    elseif ( $active_tab === 'keyword_scanner' ) : 
        $taxonomy_groups = lee_dev_get_filtered_taxonomy_groups_7316();
        $master_groups = $taxonomy_groups['groups'];
        $taxonomy_blacklist = $taxonomy_groups['blacklist'];

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_options') ) {
            
            if ( isset($_POST['itp_trigger_scan']) ) {
                check_admin_referer('itp_scanner_action', 'itp_scanner_nonce');
                
                $scanned_items = [];
                
                $blacklist = array_values( array_unique( array_merge( $taxonomy_blacklist, [
                    'bishops', 'subdued', 'subdue', 'subside', 'subsided', 'subsiding', 
                    'subject', 'subjects', 'subjectof', 'subsequent', 'subsequently', 
                    'subordinate', 'subdivided', 'subdivide', 'subtraction', 'subtly', 
                    'subtle', 'substandard', 'republic', 'republicans', 'disorder', 
                    'uncategorised', 'uncategorized', 'sortorder', 'subsumed', 'subsuming',
                    'subsume', 'substations', 'sublease', 'sweatshop', 'the', 'and', 'for', 
                    'und', 'with', 'from', 'this', 'that', 'your', 'will', 'have'
                ] ) ) );

                global $wpdb;
                $posts_table = $wpdb->posts;

                $raw_posts = $wpdb->get_results("
                    SELECT ID, post_title, post_type 
                    FROM {$posts_table} 
                    WHERE post_status = 'publish' 
                    AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment', 'custom_css', 'customize_changeset')
                    LIMIT 400
                ");
                
                if ( ! empty($raw_posts) ) {
                    foreach ( $raw_posts as $post_obj ) {
                        $current_type = ! empty($post_obj->post_type) ? (string) $post_obj->post_type : 'post';
                        
                        if ( $current_type === 'post' || $current_type === 'page' ) {
                            continue;
                        }
                        
                        $assigned_cats = [];
                        $target_taxonomies = array( 'category' );
                        if ( class_exists( 'WooCommerce' ) ) {
                            $target_taxonomies[] = 'product_cat';
                        }
                        $post_terms = wp_get_object_terms( $post_obj->ID, $target_taxonomies );
                        if ( ! is_wp_error( $post_terms ) && ! empty( $post_terms ) ) {
                            foreach ( $post_terms as $term ) {
                                $assigned_cats[] = $term->slug;
                            }
                        }

                        if ( empty($assigned_cats) ) {
                            continue;
                        }

                        $title_clean = strtolower($post_obj->post_title);
                        $title_clean = str_replace(['&nbsp;', "\xc2\xa0", '-', '_'], ' ', $title_clean);
                        $title_clean = preg_replace('/[.,\/#!$%\^&\*;:{}=\-_`~()?"’‘“”\n\r]/', ' ', $title_clean);
                        $title_words = array_filter(array_map('trim', explode(' ', $title_clean)));

                        if ( empty($title_words) ) continue;
                        $full_title_phrase = implode(' ', $title_words);

                        foreach ( $assigned_cats as $cat_slug ) {
                            if ( ! isset($master_groups[$cat_slug]) ) continue;

                            if ( count($title_words) > 1 ) {
                                $scanned_items[$cat_slug][] = $full_title_phrase;
                            }
                            foreach ( $title_words as $tw ) {
                                if ( strlen($tw) >= 3 && ! in_array($tw, $blacklist, true) && preg_match('/^[a-z]+$/', $tw) ) {
                                    $scanned_items[$cat_slug][] = $tw;
                                }
                            }
                        }
                    }
                }

                $target_taxonomies = array( 'category' );
                if ( class_exists( 'WooCommerce' ) ) {
                    $target_taxonomies[] = 'product_cat';
                }
                $terms = get_terms(['taxonomy' => $target_taxonomies, 'hide_empty' => false]);
                
                if ( ! is_wp_error($terms) && ! empty($terms) ) {
                    foreach ( $master_groups as $cat_slug => $cat_label ) {
                        $scanned_items[$cat_slug][] = $cat_slug;
                        $scanned_items[$cat_slug][] = str_replace( array( '-', '_' ), ' ', $cat_slug );

                        foreach ( $terms as $term ) {
                            $term_slug = is_object($term) && isset($term->slug) ? strtolower($term->slug) : strtolower($term['slug']);
                            $clean_slug_phrase = trim(str_replace(['_', '-'], ' ', $term_slug));
                            if ( strlen($clean_slug_phrase) < 3 || in_array($term_slug, $blacklist, true) || in_array($clean_slug_phrase, $blacklist, true) ) continue;

                            if ( strpos($term_slug, $cat_slug) !== false || strpos($cat_slug, $term_slug) !== false ) {
                                $scanned_items[$cat_slug][] = $clean_slug_phrase;
                            }
                        }
                    }
                }

                $clean_rebuilt_dict = [];
                foreach ( $master_groups as $key => $label ) {
                    $dynamic_found = isset($scanned_items[$key]) ? $scanned_items[$key] : [];
                    $raw_unique = array_values(array_unique(array_filter($dynamic_found)));
                    
                    $filtered_phrases = [];
                    foreach ( $raw_unique as $phrase ) {
                        $is_duplicate_word = false;
                        if ( strpos($phrase, ' ') === false ) {
                            foreach ( $raw_unique as $comparison_phrase ) {
                                if ( $phrase !== $comparison_phrase && strpos($comparison_phrase, $phrase) !== false ) {
                                    $is_duplicate_word = true;
                                    break;
                                }
                            }
                        }
                        if ( ! $is_duplicate_word ) {
                            $filtered_phrases[] = $phrase;
                        }
                    }
                    $clean_rebuilt_dict[$key] = $filtered_phrases;
                }

                update_option('itp_dynamic_keyword_dictionary', $clean_rebuilt_dict);
                echo '<div class="notice notice-success is-dismissible"><p><strong>Website scan complete!</strong> Post processing successfully constrained to taxonomy layers with duplicate exclusions.</p></div>';
            
            } elseif ( isset($_POST['itp_save_dictionary']) ) {
                check_admin_referer('itp_scanner_action', 'itp_scanner_nonce');
                
                $raw_inputs = isset($_POST['itp_dict']) ? $_POST['itp_dict'] : [];
                $new_dict = [];
                
                foreach ( $master_groups as $key => $label ) {
                    if ( ! empty($raw_inputs[$key]) ) {
                        $phrases = explode(',', $raw_inputs[$key]);
                        $clean_phrases = array_map(function($p) {
                            return trim(strtolower($p)); 
                        }, $phrases);
                        $raw_unique = array_unique(array_filter($clean_phrases));
                        
                        $filtered_phrases = [];
                        foreach ( $raw_unique as $phrase ) {
                            $is_duplicate_word = false;
                            if ( strpos($phrase, ' ') === false ) {
                                foreach ( $raw_unique as $comparison_phrase ) {
                                    if ( $phrase !== $comparison_phrase && strpos($comparison_phrase, $phrase) !== false ) {
                                        $is_duplicate_word = true;
                                        break;
                                    }
                                }
                            }
                            if ( ! $is_duplicate_word ) {
                                    $filtered_phrases[] = $phrase;
                            }
                        }
                        $new_dict[$key] = $filtered_phrases;
                    } else {
                        $new_dict[$key] = [];
                    }
                }

                update_option('itp_dynamic_keyword_dictionary', $new_dict);
                echo '<div class="notice notice-success is-dismissible"><p>Custom keyphrase dictionaries updated successfully.</p></div>';
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
        <div style="background:#fff; padding:25px; border:1px solid #ccd0d4; border-radius:4px; margin-top:15px;">
            <h3>🤖 Dynamic Keyphrase Dictionary & Taxonomy Mapping</h3>
            <p class="description">This panel governs the exact tracking keywords and multi-word phrases mapped to each propensity score evaluation profile.</p>
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
                        <strong>Run Automated Website Phrase Scan</strong><br/>
                        <span class="description">Scans commercial product and event titles dynamically, while routing blog posts securely via custom taxonomies to prevent keyword noise.</span>
                    </div>
                    <input type="submit" name="itp_trigger_scan" class="button button-secondary" value="Scan Website Structure Now" />
                </div>
            </form>

            <form method="post" action="">
                <?php wp_nonce_field('itp_scanner_action', 'itp_scanner_nonce'); ?>
                <table class="form-table" style="margin-top:20px;">
                    <?php foreach ( $master_groups as $key => $label ) : 
                        $current_phrases = isset($dictionary[$key]) ? implode(', ', $dictionary[$key]) : '';
                    ?>
                        <tr style="border-top:1px solid #eee;">
                            <th style="width:220px; padding:20px 0; vertical-align:top;">
                                <strong><?php echo esc_html($label); ?></strong><br/>
                                <span class="description">Group Key: <code><?php echo esc_html($key); ?></code></span>
                            </th>
                            <td style="padding:15px 0;">
                                <textarea name="itp_dict[<?php echo esc_attr($key); ?>]" rows="3" class="large-text" style="font-family:monospace; font-size:13px;" placeholder="e.g. farm management pocketbook, nix, sfi schemes"><?php echo esc_textarea($current_phrases); ?></textarea>
                                <p class="description" style="margin-top:5px;">Separate evaluation target keyphrases with commas.</p>
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
    // TAB 5: ACCESS CONFIGURATION
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
    endif; 
    ?>
</div>
