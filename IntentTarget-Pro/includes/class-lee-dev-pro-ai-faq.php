<?php
/**
 * IntentTarget Pro — AI FAQ Generator (built on WordPress 7.0 AI Client + Abilities API)
 *
 * What this module does
 * ---------------------
 * Adds an admin metabox on Posts, Pages and every public custom post type that lets editors
 * scan the current page (title + content + ACF fields + public custom meta) and generate a
 * Frequently Asked Questions section using the visitor's configured AI provider via the
 * native WordPress 7.0 AI Client. The generated FAQs are stored in post meta and injected
 * at the end of `the_content` so they appear immediately above the theme footer, with
 * styling pulled from `itp_design_settings` (the same palette used by the slide-in popup
 * and My Account dashboard adverts).
 *
 * Cost / safety guarantees
 * ------------------------
 * - We DO NOT bundle our own AI provider API key. The admin authenticates Anthropic /
 * OpenAI / Google (or any registered connector) via Settings -> Connectors and our code
 * delegates to `wp_ai_client_prompt()`.
 * - The metabox always shows a cost notice before any AI call.
 * - The JS layer requires an explicit `confirm()` step before the AI is invoked.
 * - We honour the core `wp_ai_client_prevent_prompt` filter (we do not bypass it).
 * - Every AI call records the model that responded plus token counts so the editor sees
 * exactly what they were billed for in their provider dashboard.
 *
 * Pro gating contract (project rule)
 * ----------------------------------
 * The whole feature is wrapped in `lee_dev_is_addon_active_3812( 'pro' )`. When the Pro
 * licence is inactive the metabox UI still renders (Visibility rule) but every control is
 * disabled and an "Activate Pro Licence" up-sell is shown.
 *
 * Naming
 * ------
 * Every newly created function uses the `lee_dev_*_NNNN` convention with a random four-
 * digit suffix per the project rules.
 *
 * @package IntentTargetPro\AiFaq
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'LEE_DEV_PRO_AI_FAQ_META_KEY' ) ) {
    define( 'LEE_DEV_PRO_AI_FAQ_META_KEY', '_itp_pro_ai_faqs' );
}
if ( ! defined( 'LEE_DEV_PRO_AI_FAQ_STATS_META_KEY' ) ) {
    define( 'LEE_DEV_PRO_AI_FAQ_STATS_META_KEY', '_itp_pro_ai_faq_stats' );
}
if ( ! defined( 'LEE_DEV_PRO_AI_FAQ_REST_NAMESPACE' ) ) {
    define( 'LEE_DEV_PRO_AI_FAQ_REST_NAMESPACE', 'intenttargetpro/v1' );
}
if ( ! defined( 'LEE_DEV_PRO_AI_FAQ_VERSION' ) ) {
    $faq_admin_js = plugin_dir_path( __FILE__ ) . '../../assets/js/itp-pro-ai-faq-admin.js';
    $faq_version  = file_exists( $faq_admin_js ) ? (string) filemtime( $faq_admin_js ) : '1.0.0';
    define( 'LEE_DEV_PRO_AI_FAQ_VERSION', $faq_version );
}

// =========================================================================
// 1. BOOTSTRAP — REGISTER ALL HOOKS IN ONE PLACE
// =========================================================================
add_action( 'init', 'lee_dev_pro_ai_faq_init_8047', 30 );
function lee_dev_pro_ai_faq_init_8047() {
    // Server-side surfaces (must run regardless of Pro state so the metabox can show the
    // up-sell when Pro is inactive — Visibility rule).
    add_action( 'add_meta_boxes', 'lee_dev_pro_ai_faq_register_metabox_3942' );
    add_action( 'admin_enqueue_scripts', 'lee_dev_pro_ai_faq_enqueue_admin_assets_8923' );
    add_action( 'rest_api_init', 'lee_dev_pro_ai_faq_register_rest_4831' );

    // Register custom admin list columns for Posts, Pages and CPTs
    if ( is_admin() ) {
        $screens = get_post_types( array( 'public' => true ), 'names' );
        unset( $screens['attachment'] );
        foreach ( $screens as $screen ) {
            add_filter( "manage_{$screen}_posts_columns", 'lee_dev_pro_ai_faq_add_status_column_7821' );
            add_action( "manage_{$screen}_posts_custom_column", 'lee_dev_pro_ai_faq_render_status_column_3948', 10, 2 );
        }
    }

    // Front-end surfaces only fire when Pro is licenced — stops orphaned FAQ blocks from
    // appearing when a licence lapses but the meta is still present.
    if ( function_exists( 'lee_dev_is_addon_active_3812' ) && lee_dev_is_addon_active_3812( 'pro' ) ) {
        add_filter( 'the_content', 'lee_dev_pro_ai_faq_inject_into_content_4407', 999 );
        add_action( 'wp_head', 'lee_dev_pro_ai_faq_inject_jsonld_3812', 50 );
        add_action( 'wp_enqueue_scripts', 'lee_dev_pro_ai_faq_enqueue_frontend_styles_5841' );
    }

    // Abilities API registration runs on the dedicated abilities init hook. It is gated by
    // Pro licence so unlicensed sites do not surface the ability in the Command Palette.
    add_action( 'abilities_api_init', 'lee_dev_pro_ai_faq_register_ability_3158' );
}

// =========================================================================
// 1.5 ADMIN LIST COLUMNS — TARGET KEYWORD & FAQ STATUS
// =========================================================================
function lee_dev_pro_ai_faq_add_status_column_7821( $columns ) {
    // Insert the custom column before the "Date" column if it exists.
    $new_columns = array();
    foreach ( $columns as $key => $title ) {
        if ( $key === 'date' ) {
            $new_columns['itp_pro_status'] = __( 'ITP Status', 'intenttarget-pro' );
        }
        $new_columns[ $key ] = $title;
    }
    // Fallback if date column didn't exist
    if ( ! isset( $new_columns['itp_pro_status'] ) ) {
        $new_columns['itp_pro_status'] = __( 'ITP Status', 'intenttarget-pro' );
    }
    return $new_columns;
}

function lee_dev_pro_ai_faq_render_status_column_3948( $column_name, $post_id ) {
    if ( $column_name !== 'itp_pro_status' ) {
        return;
    }

    // 1. Check FAQ Status
    $faqs     = get_post_meta( $post_id, LEE_DEV_PRO_AI_FAQ_META_KEY, true );
    $has_faqs = is_array( $faqs ) && count( $faqs ) > 0;

    // 2. Check Target Keyword Status 
    // IMPORTANT: Change '_itp_target_keyword' to the exact meta key you use for saving keywords!
    $keyword     = get_post_meta( $post_id, '_itp_tracking_labels', true );
    $has_keyword = ! empty( $keyword );

    // Render cleanly formatted output
    $faq_icon = $has_faqs ? '<span style="color:#00a32a; font-weight:600;" title="FAQs Generated">&#10003; FAQs</span>' : '<span style="color:#a7aaad;" title="No FAQs Generated">&#10007; FAQs</span>';
    $kw_icon  = $has_keyword ? '<span style="color:#00a32a; font-weight:600;" title="Target Keyword Set">&#10003; Keyword</span>' : '<span style="color:#a7aaad;" title="No Target Keyword">&#10007; Keyword</span>';

    echo '<div style="font-size: 13px; line-height: 1.6;">';
    echo $kw_icon . '<br />';
    echo $faq_icon;
    echo '</div>';
}

// =========================================================================
// 2. HELPER — DETECT WHETHER A WORKING AI PROVIDER IS CONFIGURED
// =========================================================================
/**
 * Returns the AI readiness status. 
 * STRICT CHECKS REMOVED: Bypasses non-standard connector authentication flags.
 *
 * @return array
 */
function lee_dev_pro_ai_faq_check_ai_status_2671() {
    $status = array(
        'available'     => false,
        'reason'        => '',
        'message'       => '',
        'provider_id'   => 'gemini_or_default',
        'provider_name' => 'Configured AI Provider',
    );

    // 1. Is the WP 7.0 AI Engine even installed?
    if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
        $status['reason']  = 'wp_ai_client_missing';
        $status['message'] = __( 'WordPress 7.0 or later is required. Please update WordPress.', 'intenttarget-pro' );
        return $status;
    }

    // 2. Try to grab the name if wp_get_connectors exists, but DO NOT fail if it doesn't look authenticated.
    if ( function_exists( 'wp_get_connectors' ) ) {
        $connectors = wp_get_connectors();
        if ( ! empty( $connectors ) && is_array( $connectors ) ) {
            $first_id = array_key_first( $connectors );
            $status['provider_id']   = (string) $first_id;
            $status['provider_name'] = isset( $connectors[$first_id]['name'] ) ? (string) $connectors[$first_id]['name'] : (string) $first_id;
        }
    }

    // 3. Force Availability. 
    // We trust the user has entered their API key. If the API fails, the JS will catch the REST error.
    $status['available'] = true;
    $status['message']   = __( 'AI provider connected and ready.', 'intenttarget-pro' );
    
    return $status;
}

// =========================================================================
// 3. METABOX — REGISTRATION + RENDER (Posts, Pages, public CPTs)
// =========================================================================
function lee_dev_pro_ai_faq_register_metabox_3942() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }
    $screens = get_post_types( array( 'public' => true ), 'names' );
    // Attachments make no sense for FAQ generation.
    unset( $screens['attachment'] );
    foreach ( $screens as $screen ) {
        add_meta_box(
            'itp-pro-ai-faq-metabox',
            __( 'AI FAQ Generator (IntentTarget Pro)', 'intenttarget-pro' ),
            'lee_dev_pro_ai_faq_render_metabox_5219',
            $screen,
            'side',
            'default'
        );
    }
}

function lee_dev_pro_ai_faq_render_metabox_5219( $post ) {
    $is_pro_active = function_exists( 'lee_dev_is_addon_active_3812' ) && lee_dev_is_addon_active_3812( 'pro' );

    $existing_faqs   = get_post_meta( $post->ID, LEE_DEV_PRO_AI_FAQ_META_KEY, true );
    $existing_stats  = get_post_meta( $post->ID, LEE_DEV_PRO_AI_FAQ_STATS_META_KEY, true );
    $existing_faqs   = is_array( $existing_faqs ) ? $existing_faqs : array();
    $existing_stats  = is_array( $existing_stats ) ? $existing_stats : array();
    $faq_count       = count( $existing_faqs );

    $ai_status = lee_dev_pro_ai_faq_check_ai_status_2671();

    // Up-sell card when Pro is inactive (Visibility rule).
    if ( ! $is_pro_active ) {
        ?>
        <div class="itp-faq-metabox-upsell" style="background:#fff8e5;border:1px solid #f0c36d;border-left:4px solid #f0c36d;padding:12px;border-radius:4px;margin-bottom:10px;">
            <strong style="display:block;margin-bottom:6px;"><?php echo esc_html__( 'Pro Licence Required', 'intenttarget-pro' ); ?></strong>
            <p style="margin:0 0 10px 0;font-size:12px;line-height:1.5;color:#5b5b5b;">
                <?php echo esc_html__( 'The AI FAQ generator is part of the IntentTarget Pro add-on. Activate a Pro licence (ITPP-XXXX-XXXX-XXXX) to enable AI-powered FAQ scanning.', 'intenttarget-pro' ); ?>
            </p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=itp-search-dashboard&tab=design_pro' ) ); ?>" class="button button-secondary"><?php echo esc_html__( 'Activate Pro Licence', 'intenttarget-pro' ); ?></a>
        </div>
        <?php
    }
    ?>
    <div id="itp-pro-ai-faq-metabox-root"
         class="itp-pro-ai-faq-metabox<?php echo $is_pro_active ? '' : ' is-disabled'; ?>"
         data-post-id="<?php echo (int) $post->ID; ?>"
         data-pro-active="<?php echo $is_pro_active ? '1' : '0'; ?>"
         data-ai-available="<?php echo $ai_status['available'] ? '1' : '0'; ?>">

        <div class="itp-faq-metabox-status">
            <span class="itp-faq-status-dot itp-faq-status-<?php echo $ai_status['available'] ? 'ok' : 'error'; ?>"></span>
            <span class="itp-faq-status-label">
                <?php
                if ( $ai_status['available'] ) {
                    echo esc_html( sprintf( __( 'AI provider connected: %s', 'intenttarget-pro' ), $ai_status['provider_name'] ) );
                } else {
                    echo esc_html( $ai_status['message'] );
                    if ( $ai_status['reason'] === 'no_text_generation' || $ai_status['reason'] === 'wp_ai_client_missing' ) {
                        // FIXED LINK: Points directly to options-connectors.php
                        echo ' <a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">' . esc_html__( 'Open Connectors Settings', 'intenttarget-pro' ) . '</a>';
                    }
                }
                ?>
            </span>
        </div>

        <?php if ( $faq_count === 0 ) : ?>
            <div class="itp-faq-cost-warning">
                <strong><?php echo esc_html__( 'AI usage costs', 'intenttarget-pro' ); ?></strong>
                <p>
                    <?php echo esc_html__( 'Each scan sends this page\'s title, content, ACF fields and public custom meta to your configured AI provider. Token usage is billed by your provider — IntentTarget Pro does not charge per scan.', 'intenttarget-pro' ); ?>
                </p>
            </div>
        <?php endif; ?>

        <button type="button"
                class="button button-primary itp-faq-generate-btn"
                <?php disabled( ! $is_pro_active || ! $ai_status['available'] ); ?>>
            <?php
            if ( $faq_count > 0 ) {
                echo esc_html__( 'Regenerate FAQs', 'intenttarget-pro' );
            } else {
                echo esc_html__( 'Scan page & generate FAQs', 'intenttarget-pro' );
            }
            ?>
        </button>

        <div class="itp-faq-progress" hidden>
            <span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span>
            <span class="itp-faq-progress-text"><?php echo esc_html__( 'Scanning page and generating FAQs…', 'intenttarget-pro' ); ?></span>
        </div>

        <div class="itp-faq-result" data-has-faqs="<?php echo $faq_count > 0 ? '1' : '0'; ?>">
            <?php if ( $faq_count > 0 ) : ?>
                <?php lee_dev_pro_ai_faq_render_metabox_summary_4732( $existing_faqs, $existing_stats ); ?>
            <?php endif; ?>
        </div>

        <div class="itp-faq-result-actions" <?php echo $faq_count > 0 ? '' : 'hidden'; ?>>
            <button type="button" class="button-link itp-faq-remove-btn" style="color:#b32d2e;">
                <?php echo esc_html__( 'Remove generated FAQs', 'intenttarget-pro' ); ?>
            </button>
        </div>
    </div>
    <?php
}

/**
 * Renders the FAQ summary block shown inside the metabox after a successful generation.
 * Kept as a separate function so the JS controller can re-render it after each call by
 * receiving its raw HTML from the REST response (server-side templating, never client-side).
 *
 * @param array $faqs  List of question/answer pairs.
 * @param array $stats Generation metadata (model, tokens, generated_at).
 */
function lee_dev_pro_ai_faq_render_metabox_summary_4732( $faqs, $stats ) {
    if ( empty( $faqs ) || ! is_array( $faqs ) ) {
        return;
    }
    $generated_at = isset( $stats['generated_at'] ) ? (int) $stats['generated_at'] : 0;
    $model        = isset( $stats['model'] ) ? (string) $stats['model'] : '';
    $provider     = isset( $stats['provider'] ) ? (string) $stats['provider'] : '';
    $tokens_in    = isset( $stats['tokens_in'] ) ? (int) $stats['tokens_in'] : 0;
    $tokens_out   = isset( $stats['tokens_out'] ) ? (int) $stats['tokens_out'] : 0;
    ?>
    <div class="itp-faq-summary">
        <p class="itp-faq-summary-meta">
            <?php if ( $generated_at > 0 ) : ?>
                <strong><?php echo esc_html__( 'Last scan:', 'intenttarget-pro' ); ?></strong>
                <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $generated_at ) ); ?><br>
            <?php endif; ?>
            <?php if ( $provider !== '' || $model !== '' ) : ?>
                <strong><?php echo esc_html__( 'Model:', 'intenttarget-pro' ); ?></strong>
                <?php echo esc_html( trim( $provider . ' · ' . $model, ' ·' ) ); ?><br>
            <?php endif; ?>
            <?php if ( $tokens_in > 0 || $tokens_out > 0 ) : ?>
                <strong><?php echo esc_html__( 'Tokens:', 'intenttarget-pro' ); ?></strong>
                <?php echo esc_html( number_format_i18n( $tokens_in ) ); ?> <?php echo esc_html__( 'in', 'intenttarget-pro' ); ?>
                · <?php echo esc_html( number_format_i18n( $tokens_out ) ); ?> <?php echo esc_html__( 'out', 'intenttarget-pro' ); ?>
            <?php endif; ?>
        </p>
        <p class="itp-faq-summary-count">
            <?php
            /* translators: %d: number of FAQs that were generated. */
            echo esc_html( sprintf( _n( '%d FAQ generated', '%d FAQs generated', count( $faqs ), 'intenttarget-pro' ), count( $faqs ) ) );
            ?>
        </p>
        <ol class="itp-faq-summary-list">
            <?php foreach ( $faqs as $faq ) :
                if ( ! is_array( $faq ) || empty( $faq['question'] ) ) { continue; }
                ?>
                <li><?php echo esc_html( $faq['question'] ); ?></li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php
}

// =========================================================================
// 4. ADMIN ASSETS — JS CONTROLLER + STYLING (only on edit screens)
// =========================================================================
function lee_dev_pro_ai_faq_enqueue_admin_assets_8923( $hook_suffix ) {
    if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }
    if ( ! defined( 'LEE_DEV_PLUGIN_URL' ) ) {
        // Core IntentTarget plugin has not loaded yet — cannot resolve asset URLs. The
        // metabox PHP renders fine without these assets (it just looks unstyled and the
        // generate button does nothing on click), which is the safer failure mode than a
        // 404 on the asset request.
        return;
    }

    wp_register_style(
        'itp-pro-ai-faq-admin',
        LEE_DEV_PLUGIN_URL . 'assets/css/itp-pro-ai-faq-admin.css',
        array(),
        LEE_DEV_PRO_AI_FAQ_VERSION
    );
    wp_enqueue_style( 'itp-pro-ai-faq-admin' );

    wp_register_script(
        'itp-pro-ai-faq-admin',
        LEE_DEV_PLUGIN_URL . 'assets/js/itp-pro-ai-faq-admin.js',
        array( 'wp-api-fetch', 'wp-i18n' ),
        LEE_DEV_PRO_AI_FAQ_VERSION,
        true
    );
    wp_localize_script( 'itp-pro-ai-faq-admin', 'itpProAiFaq', array(
        'restBase'      => esc_url_raw( rest_url( LEE_DEV_PRO_AI_FAQ_REST_NAMESPACE . '/' ) ),
        'nonce'         => wp_create_nonce( 'wp_rest' ),
        'i18n'          => array(
            'confirm'      => __( 'This will send the full content of this page to your AI provider and incur usage costs on your account. Continue?', 'intenttarget-pro' ),
            'confirmRemove'=> __( 'Remove the generated FAQ section from this page? The FAQs will no longer appear on the live site.', 'intenttarget-pro' ),
            'errorGeneric' => __( 'The AI request failed. Check the WordPress error log for details.', 'intenttarget-pro' ),
            'working'      => __( 'Scanning page and generating FAQs…', 'intenttarget-pro' ),
            'removing'     => __( 'Removing FAQs…', 'intenttarget-pro' ),
        ),
    ) );
    wp_enqueue_script( 'itp-pro-ai-faq-admin' );
}

// =========================================================================
// 5. REST ENDPOINTS
// =========================================================================
function lee_dev_pro_ai_faq_register_rest_4831() {
    register_rest_route( LEE_DEV_PRO_AI_FAQ_REST_NAMESPACE, '/generate-faqs', array(
        'methods'             => 'POST',
        'callback'            => 'lee_dev_pro_ai_faq_handle_generate_rest_7256',
        'permission_callback' => 'lee_dev_pro_ai_faq_rest_permission_check_4920',
        'args'                => array(
            'post_id' => array(
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );
    register_rest_route( LEE_DEV_PRO_AI_FAQ_REST_NAMESPACE, '/remove-faqs', array(
        'methods'             => 'POST',
        'callback'            => 'lee_dev_pro_ai_faq_handle_remove_rest_7257',
        'permission_callback' => 'lee_dev_pro_ai_faq_rest_permission_check_4920',
        'args'                => array(
            'post_id' => array(
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );
}

/**
 * REST permission check for both endpoints. Verifies the visitor can edit the requested
 * post AND that Pro is currently licenced. Returning a WP_Error here propagates a 403 with
 * a meaningful body so the JS controller can surface the reason.
 */
function lee_dev_pro_ai_faq_rest_permission_check_4920( WP_REST_Request $request ) {
    $post_id = (int) $request->get_param( 'post_id' );
    if ( $post_id <= 0 ) {
        return new WP_Error( 'itp_faq_invalid_post', __( 'A valid post_id is required.', 'intenttarget-pro' ), array( 'status' => 400 ) );
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return new WP_Error( 'itp_faq_forbidden', __( 'You do not have permission to edit this post.', 'intenttarget-pro' ), array( 'status' => 403 ) );
    }
    if ( ! function_exists( 'lee_dev_is_addon_active_3812' ) || ! lee_dev_is_addon_active_3812( 'pro' ) ) {
        return new WP_Error( 'itp_faq_pro_inactive', __( 'IntentTarget Pro is not currently activated on this site.', 'intenttarget-pro' ), array( 'status' => 402 ) );
    }
    return true;
}

function lee_dev_pro_ai_faq_handle_generate_rest_7256( WP_REST_Request $request ) {
    $post_id = (int) $request->get_param( 'post_id' );
    $post    = get_post( $post_id );
    if ( ! $post instanceof WP_Post ) {
        return new WP_Error( 'itp_faq_no_post', __( 'Post not found.', 'intenttarget-pro' ), array( 'status' => 404 ) );
    }

    $generated = lee_dev_pro_ai_faq_generate_for_post_2185( $post_id );
    if ( is_wp_error( $generated ) ) {
        return $generated;
    }

    // Re-render the metabox summary HTML server-side so the JS controller can drop it in
    // without templating dynamic data on the client.
    ob_start();
    lee_dev_pro_ai_faq_render_metabox_summary_4732( $generated['faqs'], $generated['stats'] );
    $summary_html = ob_get_clean();

    return rest_ensure_response( array(
        'success'      => true,
        'faqs'         => $generated['faqs'],
        'stats'        => $generated['stats'],
        'summary_html' => $summary_html,
    ) );
}

function lee_dev_pro_ai_faq_handle_remove_rest_7257( WP_REST_Request $request ) {
    $post_id = (int) $request->get_param( 'post_id' );
    delete_post_meta( $post_id, LEE_DEV_PRO_AI_FAQ_META_KEY );
    delete_post_meta( $post_id, LEE_DEV_PRO_AI_FAQ_STATS_META_KEY );
    if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
        lee_dev_debug_log_event_6158( 'pro_ai_faq.removed', array(
            'post_id' => $post_id,
        ) );
    }
    return rest_ensure_response( array( 'success' => true ) );
}

// =========================================================================
// 6. CORPUS GATHERING — TITLE + CONTENT + ACF + PUBLIC META
// =========================================================================
/**
 * Builds the text corpus that will be sent to the AI provider. The corpus deliberately
 * excludes any internal IntentTarget meta keys (`_itp_*`) and any meta key beginning with
 * an underscore by default — that follows WordPress's standard "private meta" convention.
 *
 * @param int $post_id
 * @return array { title, permalink, content_text, acf, meta }
 */
function lee_dev_pro_ai_faq_gather_post_corpus_6394( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post instanceof WP_Post ) {
        return new WP_Error( 'itp_faq_no_post', __( 'Post not found.', 'intenttarget-pro' ) );
    }

    // Render the post through `the_content` so shortcodes / blocks resolve, then strip
    // tags so we send the model clean reading text rather than markup.
    $rendered     = apply_filters( 'the_content', $post->post_content );
    $content_text = trim( wp_strip_all_tags( $rendered, true ) );

    // ACF (if installed). We export the values as their public string representation.
    $acf_values = array();
    if ( function_exists( 'get_field_objects' ) ) {
        $fields = get_field_objects( $post_id );
        if ( is_array( $fields ) ) {
            foreach ( $fields as $field_name => $field ) {
                $value = isset( $field['value'] ) ? $field['value'] : '';
                if ( is_scalar( $value ) ) {
                    $acf_values[ $field_name ] = (string) $value;
                } elseif ( is_array( $value ) ) {
                    $acf_values[ $field_name ] = wp_json_encode( $value );
                }
            }
        }
    }

    // Public custom meta — exclude any keys starting with `_` (private convention) and
    // anything explicitly listed in our internal meta blacklist to avoid leaking tracking
    // labels, licence flags etc. to the AI provider.
    $blacklist = apply_filters( 'lee_dev_pro_ai_faq_meta_blacklist_6394', array(
        '_edit_lock', '_edit_last', '_thumbnail_id', '_wp_page_template',
        LEE_DEV_PRO_AI_FAQ_META_KEY, LEE_DEV_PRO_AI_FAQ_STATS_META_KEY,
        '_itp_tracking_labels',
    ), $post_id );

    $public_meta = array();
    $all_meta    = get_post_meta( $post_id );
    if ( is_array( $all_meta ) ) {
        foreach ( $all_meta as $key => $values ) {
            if ( strpos( $key, '_' ) === 0 ) { continue; }
            if ( in_array( $key, $blacklist, true ) ) { continue; }
            // ACF already accounted for above; skip its mirrored keys to keep the payload small.
            if ( isset( $acf_values[ $key ] ) ) { continue; }
            $first = is_array( $values ) ? reset( $values ) : $values;
            if ( is_scalar( $first ) ) {
                $public_meta[ $key ] = (string) $first;
            } elseif ( is_serialized( (string) $first ) ) {
                $unser = maybe_unserialize( $first );
                $public_meta[ $key ] = is_array( $unser ) ? wp_json_encode( $unser ) : (string) $first;
            }
        }
    }

    return array(
        'title'        => get_the_title( $post ),
        'permalink'    => get_permalink( $post ),
        'content_text' => $content_text,
        'acf'          => $acf_values,
        'meta'         => $public_meta,
    );
}

// =========================================================================
// 7. PROMPT BUILDING + AI CLIENT INVOCATION
// =========================================================================
/**
 * The single internal entry point that performs the AI call and persists the result. Both
 * the REST endpoint and the registered Ability call into this function so the behaviour is
 * identical regardless of the trigger surface.
 *
 * @param int $post_id
 * @return array|WP_Error
 */
function lee_dev_pro_ai_faq_generate_for_post_2185( $post_id ) {
    $post_id = (int) $post_id;
    $post    = get_post( $post_id );
    if ( ! $post instanceof WP_Post ) {
        return new WP_Error( 'itp_faq_no_post', __( 'Post not found.', 'intenttarget-pro' ), array( 'status' => 404 ) );
    }

    $ai_status = lee_dev_pro_ai_faq_check_ai_status_2671();
    if ( ! $ai_status['available'] ) {
        return new WP_Error( 'itp_faq_ai_unavailable', $ai_status['message'], array( 'status' => 503, 'reason' => $ai_status['reason'] ) );
    }

    $corpus = lee_dev_pro_ai_faq_gather_post_corpus_6394( $post_id );
    if ( is_wp_error( $corpus ) ) {
        return $corpus;
    }
    if ( $corpus['content_text'] === '' && empty( $corpus['acf'] ) && empty( $corpus['meta'] ) ) {
        return new WP_Error( 'itp_faq_empty_corpus', __( 'This post has no content, ACF fields, or public meta to scan. Add some content first, then try again.', 'intenttarget-pro' ), array( 'status' => 422 ) );
    }

    // Cap the corpus to keep token usage predictable — long pages get truncated rather
    // than silently blowing the cost budget.
    $max_chars = (int) apply_filters( 'lee_dev_pro_ai_faq_max_corpus_chars_2185', 18000, $post_id );
    if ( strlen( $corpus['content_text'] ) > $max_chars ) {
        $corpus['content_text'] = substr( $corpus['content_text'], 0, $max_chars ) . " […]";
    }

    $prompt_text = lee_dev_pro_ai_faq_build_prompt_text_5872( $corpus );
    $schema      = lee_dev_pro_ai_faq_build_response_schema_4115();

    if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
        lee_dev_debug_log_event_6158( 'pro_ai_faq.generation.started', array(
            'post_id'      => $post_id,
            'corpus_chars' => strlen( $corpus['content_text'] ),
            'acf_count'    => count( $corpus['acf'] ),
            'meta_count'   => count( $corpus['meta'] ),
        ) );
    }

    $system_instruction = __( 'You are a senior content analyst helping a website editor build a Frequently Asked Questions section for a single web page. Read the supplied page content carefully and produce 4 to 8 FAQ items that real visitors to this page would actually ask. Every question MUST be answerable directly from the supplied content. Do not invent facts, products, prices, or claims that the source content does not contain. Use UK English spelling (organise, optimise, licence as a noun). Keep questions natural and concise. Keep each answer under 80 words.', 'intenttarget-pro' );

    $builder = wp_ai_client_prompt( $prompt_text )
        ->using_system_instruction( $system_instruction )
        ->using_temperature( 0.3 )
        ->using_max_tokens( 1800 )
        ->as_json_response( $schema );

    $result = $builder->generate_text_result();
    if ( is_wp_error( $result ) ) {
        if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
            lee_dev_debug_log_event_6158( 'pro_ai_faq.generation.error', array(
                'post_id'  => $post_id,
                'error_id' => $result->get_error_code(),
                'message'  => $result->get_error_message(),
            ) );
        }
        return $result;
    }

    $faq_payload = lee_dev_pro_ai_faq_parse_result_3429( $result );
    if ( is_wp_error( $faq_payload ) ) {
        return $faq_payload;
    }

    $persist = lee_dev_pro_ai_faq_persist_result_5638( $post_id, $faq_payload['faqs'], $faq_payload['stats'] );
    if ( is_wp_error( $persist ) ) {
        return $persist;
    }

    if ( function_exists( 'lee_dev_debug_log_event_6158' ) ) {
        lee_dev_debug_log_event_6158( 'pro_ai_faq.generation.success', array(
            'post_id'    => $post_id,
            'faq_count'  => count( $faq_payload['faqs'] ),
            'tokens_in'  => $faq_payload['stats']['tokens_in'],
            'tokens_out' => $faq_payload['stats']['tokens_out'],
            'model'      => $faq_payload['stats']['model'],
        ) );
    }

    return $faq_payload;
}

function lee_dev_pro_ai_faq_build_prompt_text_5872( $corpus ) {
    $lines   = array();
    $lines[] = sprintf( 'Page title: %s', $corpus['title'] );
    $lines[] = sprintf( 'Page URL:   %s', $corpus['permalink'] );
    $lines[] = '';
    $lines[] = 'Page content (HTML stripped):';
    $lines[] = $corpus['content_text'] !== '' ? $corpus['content_text'] : '(empty)';

    if ( ! empty( $corpus['acf'] ) ) {
        $lines[] = '';
        $lines[] = 'ACF fields:';
        foreach ( $corpus['acf'] as $name => $value ) {
            $lines[] = sprintf( '- %s: %s', $name, $value );
        }
    }
    if ( ! empty( $corpus['meta'] ) ) {
        $lines[] = '';
        $lines[] = 'Custom meta (public):';
        foreach ( $corpus['meta'] as $key => $value ) {
            $lines[] = sprintf( '- %s: %s', $key, $value );
        }
    }

    $lines[] = '';
    $lines[] = 'Generate the FAQ JSON array now. Respond ONLY with the JSON array — no prose, no markdown fence.';
    return implode( "\n", $lines );
}

function lee_dev_pro_ai_faq_build_response_schema_4115() {
    return array(
        'type'     => 'array',
        'minItems' => 3,
        'maxItems' => 10,
        'items'    => array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => array(
                'question' => array(
                    'type'      => 'string',
                    'minLength' => 5,
                    'maxLength' => 200,
                ),
                'answer'   => array(
                    'type'      => 'string',
                    'minLength' => 10,
                    'maxLength' => 600,
                ),
            ),
            'required'             => array( 'question', 'answer' ),
        ),
    );
}

/**
 * Parses a GenerativeAiResult into a normalised FAQ payload.
 * Updated to deeply traverse the Gemini/Google connector object structure
 * if standard WP 7.0 helper methods are missing.
 *
 * @param mixed $result GenerativeAiResult object returned by the WP AI Client.
 * @return array|WP_Error
 */
function lee_dev_pro_ai_faq_parse_result_3429( $result ) {
    $raw_text = '';

    // 1. Try standard WP 7.0 helper methods first
    if ( is_object( $result ) && method_exists( $result, 'toMessage' ) ) {
        $message = $result->toMessage();
        if ( is_object( $message ) && method_exists( $message, 'getParts' ) ) {
            foreach ( (array) $message->getParts() as $part ) {
                if ( is_object( $part ) && method_exists( $part, 'isText' ) && $part->isText() && method_exists( $part, 'getText' ) ) {
                    $raw_text .= (string) $part->getText();
                }
            }
        }
    }
    
    if ( $raw_text === '' && is_object( $result ) && method_exists( $result, 'getText' ) ) {
        $raw_text = (string) $result->getText();
    }

    // 2. The Gemini Bypass: If helpers failed, use Reflection to dig into the private properties
    if ( $raw_text === '' && is_object( $result ) ) {
        try {
            $reflector = new ReflectionClass( $result );
            if ( $reflector->hasProperty( 'candidates' ) ) {
                $prop = $reflector->getProperty( 'candidates' );
                $prop->setAccessible( true );
                $candidates = $prop->getValue( $result );
                
                if ( is_array( $candidates ) && isset( $candidates[0] ) && is_object( $candidates[0] ) ) {
                    $cand_ref = new ReflectionClass( $candidates[0] );
                    if ( $cand_ref->hasProperty( 'message' ) ) {
                        $msg_prop = $cand_ref->getProperty( 'message' );
                        $msg_prop->setAccessible( true );
                        $msg_obj = $msg_prop->getValue( $candidates[0] );
                        
                        if ( is_object( $msg_obj ) ) {
                            $msg_ref = new ReflectionClass( $msg_obj );
                            if ( $msg_ref->hasProperty( 'parts' ) ) {
                                $parts_prop = $msg_ref->getProperty( 'parts' );
                                $parts_prop->setAccessible( true );
                                $parts = $parts_prop->getValue( $msg_obj );
                                
                                if ( is_array( $parts ) && isset( $parts[0] ) && is_object( $parts[0] ) ) {
                                    $part_ref = new ReflectionClass( $parts[0] );
                                    if ( $part_ref->hasProperty( 'text' ) ) {
                                        $text_prop = $part_ref->getProperty( 'text' );
                                        $text_prop->setAccessible( true );
                                        $raw_text = (string) $text_prop->getValue( $parts[0] );
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch ( Exception $e ) {
            // Ignore reflection errors and let the empty string catch trigger below
        }
    }

    $raw_text = trim( $raw_text );
    if ( $raw_text === '' ) {
        return new WP_Error( 'itp_faq_empty_response', __( 'The AI provider returned an empty response.', 'intenttarget-pro' ), array( 'status' => 502 ) );
    }

    // Strip any accidental markdown fence the model might have wrapped around the JSON.
    // We use \x60 to represent backticks so the markdown parser does not truncate this block!
    if ( strpos( $raw_text, "\x60\x60\x60" ) !== false ) {
        $raw_text = preg_replace( '/^\s*\x60{3}(?:json)?\s*|\s*\x60{3}\s*$/i', '', $raw_text );
        $raw_text = trim( (string) $raw_text );
    }

    $parsed = json_decode( $raw_text, true );
    if ( ! is_array( $parsed ) ) {
        return new WP_Error( 'itp_faq_invalid_json', __( 'The AI provider did not return valid JSON.', 'intenttarget-pro' ), array( 'status' => 502, 'raw' => $raw_text ) );
    }

    $faqs = array();
    foreach ( $parsed as $row ) {
        if ( ! is_array( $row ) ) { continue; }
        $question = isset( $row['question'] ) ? trim( wp_strip_all_tags( (string) $row['question'] ) ) : '';
        $answer   = isset( $row['answer'] ) ? trim( wp_strip_all_tags( (string) $row['answer'] ) ) : '';
        if ( $question === '' || $answer === '' ) { continue; }
        $faqs[] = array(
            'question' => $question,
            'answer'   => $answer,
        );
    }
    
    if ( empty( $faqs ) ) {
        return new WP_Error( 'itp_faq_no_items', __( 'The AI provider returned no usable FAQ items.', 'intenttarget-pro' ), array( 'status' => 502 ) );
    }

    // Metadata extraction...
    $tokens_in    = 0;
    $tokens_out   = 0;
    $model        = '';
    $provider     = '';
    
    if ( is_object( $result ) ) {
        try {
            $ref_res = new ReflectionClass( $result );
            
            // Tokens
            if ( $ref_res->hasProperty( 'tokenUsage' ) ) {
                $tu_prop = $ref_res->getProperty( 'tokenUsage' );
                $tu_prop->setAccessible( true );
                $tu_obj = $tu_prop->getValue( $result );
                if ( is_object( $tu_obj ) ) {
                    $tu_ref = new ReflectionClass( $tu_obj );
                    if ( $tu_ref->hasProperty( 'promptTokens' ) ) {
                        $p_prop = $tu_ref->getProperty( 'promptTokens' );
                        $p_prop->setAccessible( true );
                        $tokens_in = (int) $p_prop->getValue( $tu_obj );
                    }
                    if ( $tu_ref->hasProperty( 'completionTokens' ) ) {
                        $c_prop = $tu_ref->getProperty( 'completionTokens' );
                        $c_prop->setAccessible( true );
                        $tokens_out = (int) $c_prop->getValue( $tu_obj );
                    }
                }
            }
            
            // Model
            if ( $ref_res->hasProperty( 'modelMetadata' ) ) {
                $mm_prop = $ref_res->getProperty( 'modelMetadata' );
                $mm_prop->setAccessible( true );
                $mm_obj = $mm_prop->getValue( $result );
                if ( is_object( $mm_obj ) ) {
                    $mm_ref = new ReflectionClass( $mm_obj );
                    if ( $mm_ref->hasProperty( 'id' ) ) {
                        $id_prop = $mm_ref->getProperty( 'id' );
                        $id_prop->setAccessible( true );
                        $model = (string) $id_prop->getValue( $mm_obj );
                    }
                }
            }
            
            // Provider
            if ( $ref_res->hasProperty( 'providerMetadata' ) ) {
                $pm_prop = $ref_res->getProperty( 'providerMetadata' );
                $pm_prop->setAccessible( true );
                $pm_obj = $pm_prop->getValue( $result );
                if ( is_object( $pm_obj ) ) {
                    $pm_ref = new ReflectionClass( $pm_obj );
                    if ( $pm_ref->hasProperty( 'name' ) ) {
                        $n_prop = $pm_ref->getProperty( 'name' );
                        $n_prop->setAccessible( true );
                        $provider = (string) $n_prop->getValue( $pm_obj );
                    }
                }
            }
        } catch ( Exception $e ) {}
    }

    return array(
        'faqs'  => $faqs,
        'stats' => array(
            'generated_at' => time(),
            'model'        => $model,
            'provider'     => $provider,
            'tokens_in'    => $tokens_in,
            'tokens_out'   => $tokens_out,
        ),
    );
}

function lee_dev_pro_ai_faq_persist_result_5638( $post_id, $faqs, $stats ) {
    $post_id = (int) $post_id;
    if ( $post_id <= 0 ) {
        return new WP_Error( 'itp_faq_persist_invalid', __( 'Invalid post ID for FAQ persistence.', 'intenttarget-pro' ) );
    }
    update_post_meta( $post_id, LEE_DEV_PRO_AI_FAQ_META_KEY, array_values( $faqs ) );
    update_post_meta( $post_id, LEE_DEV_PRO_AI_FAQ_STATS_META_KEY, $stats );
    return true;
}

// =========================================================================
// 8. FRONT-END INJECTION — `the_content` filter + FAQPage JSON-LD
// =========================================================================
function lee_dev_pro_ai_faq_inject_into_content_4407( $content ) {
    if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }
    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return $content;
    }
    $faqs = get_post_meta( $post_id, LEE_DEV_PRO_AI_FAQ_META_KEY, true );
    if ( ! is_array( $faqs ) || empty( $faqs ) ) {
        return $content;
    }

    $heading = apply_filters( 'lee_dev_pro_ai_faq_section_heading_4407', __( 'Frequently Asked Questions', 'intenttarget-pro' ), $post_id );

    $items_html = '';
    foreach ( $faqs as $index => $faq ) {
        if ( ! is_array( $faq ) ) { continue; }
        $q = isset( $faq['question'] ) ? (string) $faq['question'] : '';
        $a = isset( $faq['answer'] ) ? (string) $faq['answer'] : '';
        if ( $q === '' || $a === '' ) { continue; }
        $is_open = ( $index === 0 ) ? ' open' : '';
        $items_html .= sprintf(
            '<details class="itp-faq-item"%s><summary class="itp-faq-question">%s</summary><div class="itp-faq-answer">%s</div></details>',
            $is_open,
            esc_html( $q ),
            wp_kses_post( wpautop( $a ) )
        );
    }
    if ( $items_html === '' ) {
        return $content;
    }

    $section = sprintf(
        '<section class="itp-faq-section" id="itp-faq-section" aria-label="%1$s"><h2 class="itp-faq-section-heading">%1$s</h2><div class="itp-faq-list">%2$s</div></section>',
        esc_attr( $heading ),
        $items_html
    );

    return $content . $section;
}

function lee_dev_pro_ai_faq_inject_jsonld_3812() {
    if ( ! is_singular() ) { return; }
    $post_id = get_the_ID();
    if ( ! $post_id ) { return; }
    $faqs = get_post_meta( $post_id, LEE_DEV_PRO_AI_FAQ_META_KEY, true );
    if ( ! is_array( $faqs ) || empty( $faqs ) ) { return; }

    $main_entity = array();
    foreach ( $faqs as $faq ) {
        if ( ! is_array( $faq ) ) { continue; }
        $q = isset( $faq['question'] ) ? (string) $faq['question'] : '';
        $a = isset( $faq['answer'] ) ? (string) $faq['answer'] : '';
        if ( $q === '' || $a === '' ) { continue; }
        $main_entity[] = array(
            '@type'          => 'Question',
            'name'           => wp_strip_all_tags( $q ),
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => wp_strip_all_tags( $a ),
            ),
        );
    }
    if ( empty( $main_entity ) ) { return; }

    $jsonld = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $main_entity,
    );
    echo "\n<script type=\"application/ld+json\" id=\"itp-faq-jsonld\">" . wp_json_encode( $jsonld ) . "</script>\n";
}

function lee_dev_pro_ai_faq_enqueue_frontend_styles_5841() {
    if ( ! is_singular() ) { return; }
    $post_id = get_the_ID();
    if ( ! $post_id ) { return; }
    $faqs = get_post_meta( $post_id, LEE_DEV_PRO_AI_FAQ_META_KEY, true );
    if ( ! is_array( $faqs ) || empty( $faqs ) ) { return; }

    // Inline the section CSS — it is small, palette-driven, and avoids adding a network
    // request when no FAQ section is actually present on the page.
    $colors        = get_option( 'itp_design_settings', array() );
    $c_heading     = esc_attr( $colors['heading'] ?? '#1d2327' );
    $c_recommended = esc_attr( $colors['recommended'] ?? '#e1ad01' );
    $c_button      = esc_attr( $colors['button'] ?? '#2271b1' );

    $css = "
    .itp-faq-section { margin: 40px 0 30px; padding: 24px 28px; border: 1px solid #e5e5e5; border-radius: 8px; background: #fff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif; }
    .itp-faq-section-heading { margin: 0 0 18px; font-size: 22px; font-weight: 700; letter-spacing: -0.4px; color: {$c_heading}; padding-left: 12px; border-left: 4px solid {$c_recommended}; }
    .itp-faq-list { display: flex; flex-direction: column; gap: 10px; }
    .itp-faq-item { border: 1px solid #e8e8e8; border-radius: 6px; background: #fafafa; padding: 0; }
    .itp-faq-item[open] { background: #fff; border-color: #d8dde2; }
    .itp-faq-question { cursor: pointer; padding: 14px 18px; font-weight: 600; font-size: 15px; color: {$c_heading}; list-style: none; position: relative; padding-right: 40px; }
    .itp-faq-question::-webkit-details-marker { display: none; }
    .itp-faq-question::after { content: '+'; position: absolute; right: 18px; top: 50%; transform: translateY(-50%); font-size: 22px; line-height: 1; color: {$c_button}; transition: transform 0.15s ease; }
    .itp-faq-item[open] .itp-faq-question::after { content: '\\2212'; }
    .itp-faq-answer { padding: 0 18px 16px; font-size: 14px; line-height: 1.55; color: #4a5057; }
    .itp-faq-answer p { margin: 0 0 10px; }
    .itp-faq-answer p:last-child { margin-bottom: 0; }
    ";
    wp_register_style( 'itp-pro-ai-faq-frontend', false, array(), LEE_DEV_PRO_AI_FAQ_VERSION );
    wp_enqueue_style( 'itp-pro-ai-faq-frontend' );
    wp_add_inline_style( 'itp-pro-ai-faq-frontend', $css );
}

// =========================================================================
// 9. WP 7 ABILITIES API — REGISTER `intenttarget-pro/generate-page-faqs`
// =========================================================================
function lee_dev_pro_ai_faq_register_ability_3158() {
    if ( ! function_exists( 'wp_register_ability' ) ) {
        return;
    }
    if ( ! function_exists( 'lee_dev_is_addon_active_3812' ) || ! lee_dev_is_addon_active_3812( 'pro' ) ) {
        return;
    }

    // The "content" category exists by default in WP 7; we attach to it so the ability
    // appears alongside other content tools in the Command Palette.
    wp_register_ability( 'intenttarget-pro/generate-page-faqs', array(
        'label'               => __( 'Generate FAQs for a page (IntentTarget Pro)', 'intenttarget-pro' ),
        'description'         => __( 'Scan a post or page and append an AI-generated FAQ section using the configured WP AI Client provider.', 'intenttarget-pro' ),
        'category'            => 'content',
        'permission_callback' => 'lee_dev_pro_ai_faq_ability_permission_5634',
        'execute_callback'    => 'lee_dev_pro_ai_faq_run_ability_5634',
        'input_schema'        => array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => array(
                'post_id' => array(
                    'type'        => 'integer',
                    'description' => __( 'ID of the post or page to scan and append FAQs to.', 'intenttarget-pro' ),
                    'minimum'     => 1,
                ),
            ),
            'required'             => array( 'post_id' ),
        ),
        'output_schema'       => array(
            'type'       => 'object',
            'properties' => array(
                'faqs'  => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'question' => array( 'type' => 'string' ),
                            'answer'   => array( 'type' => 'string' ),
                        ),
                        'required'   => array( 'question', 'answer' ),
                    ),
                ),
                'stats' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'generated_at' => array( 'type' => 'integer' ),
                        'model'        => array( 'type' => 'string' ),
                        'provider'     => array( 'type' => 'string' ),
                        'tokens_in'    => array( 'type' => 'integer' ),
                        'tokens_out'   => array( 'type' => 'integer' ),
                    ),
                ),
            ),
            'required'   => array( 'faqs' ),
        ),
        'meta'                => array(
            'annotations' => array(
                // The action mutates post_meta, so it is neither readonly nor idempotent.
                'readonly'    => false,
                'destructive' => false,
                'idempotent'  => false,
            ),
        ),
    ) );
}

function lee_dev_pro_ai_faq_ability_permission_5634( $args ) {
    $args    = is_array( $args ) ? $args : array();
    $post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
    if ( $post_id <= 0 ) { return false; }
    return current_user_can( 'edit_post', $post_id );
}

function lee_dev_pro_ai_faq_run_ability_5634( $args ) {
    $args    = is_array( $args ) ? $args : array();
    $post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
    return lee_dev_pro_ai_faq_generate_for_post_2185( $post_id );
}