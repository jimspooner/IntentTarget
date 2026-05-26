<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the Sidebar Meta Box View Container
 */
function lee_dev_register_automated_meta_sidebar_4812() {
    $screens = get_post_types( array( 'public' => true ) );
    foreach ($screens as $screens_key) {
        add_meta_box(
            'itp_automated_tracking_meta',
            'Page Interest Tracker',
            'lee_dev_render_automated_sidebar_content_9381',
            $screens_key,
            'side',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'lee_dev_register_automated_meta_sidebar_4812');

/**
 * Render the Interactive Tracked-Keyword Sidebar.
 *
 * The list is editable: each chip carries a remove control and a dedicated
 * "Add" button lets the editor type new keyphrases. Both operations persist
 * immediately via AJAX into the manual override sidecar so they survive any
 * future scanner pass.
 */
function lee_dev_render_automated_sidebar_content_9381( $post ) {
    if ( ! $post || empty( $post->ID ) ) {
        return;
    }

    $post_id = (int) $post->ID;
    $saved_keywords = get_post_meta( $post_id, '_itp_tracking_labels', true );
    if ( ! is_array( $saved_keywords ) ) {
        $saved_keywords = array();
    }
    $saved_keywords = array_values( array_filter( array_map( function( $value ) {
        return strtolower( trim( (string) $value ) );
    }, $saved_keywords ) ) );

    $overrides = function_exists( 'lee_dev_get_manual_label_overrides_4861' )
        ? lee_dev_get_manual_label_overrides_4861( $post_id )
        : array( 'added' => array(), 'removed' => array() );

    $manual_added = isset( $overrides['added'] ) ? (array) $overrides['added'] : array();
    $max_words = function_exists( 'lee_dev_get_max_phrase_word_count_5174' )
        ? (int) lee_dev_get_max_phrase_word_count_5174()
        : 3;

    $nonce = wp_create_nonce( 'lee_dev_sidebar_labels_' . $post_id );
    ?>
    <div class="lee-dev-sidebar-tracker" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-max-words="<?php echo esc_attr( $max_words ); ?>" style="padding:2px 0;">
        <p class="description" style="margin-top:0; margin-bottom:12px; line-height:1.4;">
            The background parser auto-detects matching keyphrases. You can add or remove tracked keyphrases below — manual edits are protected from being overwritten when this page is resaved.
        </p>

        <p style="font-weight:600; font-size:12px; margin-bottom:8px; color:#1d2327;">🎯 Actively Tracked Targets:</p>

        <div class="lee-dev-sidebar-chip-list" style="display:flex; flex-wrap:wrap; gap:6px; max-height:180px; overflow-y:auto; padding:2px; margin-bottom:12px;">
            <?php if ( ! empty( $saved_keywords ) ) : ?>
                <?php foreach ( $saved_keywords as $phrase ) :
                    $is_manual = in_array( $phrase, $manual_added, true );
                    $chip_bg   = $is_manual ? '#fff4e0' : '#edf8ff';
                    $chip_bd   = $is_manual ? '#f0b849' : '#b4dcff';
                    $chip_fg   = $is_manual ? '#7a4a00' : '#074e85';
                ?>
                <span class="lee-dev-sidebar-chip" data-phrase="<?php echo esc_attr( $phrase ); ?>" style="background:<?php echo esc_attr( $chip_bg ); ?>; color:<?php echo esc_attr( $chip_fg ); ?>; border:1px solid <?php echo esc_attr( $chip_bd ); ?>; padding:4px 6px 4px 10px; border-radius:4px; font-size:11px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                    <?php if ( $is_manual ) : ?>
                        <span aria-hidden="true" title="Manually added" style="font-size:10px;">✎</span>
                    <?php endif; ?>
                    <span class="lee-dev-sidebar-chip-label"><?php echo esc_html( ucwords( $phrase ) ); ?></span>
                    <button type="button" class="lee-dev-sidebar-chip-remove" aria-label="Remove <?php echo esc_attr( $phrase ); ?>" style="background:transparent; border:0; cursor:pointer; padding:0; margin:0; color:inherit; font-size:14px; line-height:1; font-weight:700;">&times;</button>
                </span>
                <?php endforeach; ?>
            <?php endif; ?>
            <span class="lee-dev-sidebar-empty" style="font-size:12px; font-style:italic; color:#646970; <?php echo empty( $saved_keywords ) ? '' : 'display:none;'; ?>">No tracking targets yet. Add one below or save this page to run the scanner.</span>
        </div>

        <div class="lee-dev-sidebar-add-row" style="display:flex; gap:6px; align-items:stretch;">
            <input type="text" class="lee-dev-sidebar-add-input" placeholder="add a keyphrase" maxlength="80" style="flex:1; font-size:12px; padding:4px 8px; border:1px solid #c3c4c7; border-radius:3px;" />
            <button type="button" class="button button-secondary lee-dev-sidebar-add-button" style="font-size:12px; padding:0 10px; height:auto;">Add</button>
        </div>
        <p class="description lee-dev-sidebar-status" style="margin-top:8px; min-height:1.2em; font-size:11px;" aria-live="polite"></p>
        <p class="description" style="margin-top:4px; font-size:11px; color:#646970;">Max <?php echo (int) $max_words; ?> words per phrase. Lowercase letters and digits only.</p>
    </div>
    <?php
}

/**
 * Enqueue the sidebar interaction script on post edit screens only.
 */
add_action( 'admin_enqueue_scripts', 'lee_dev_enqueue_sidebar_tracker_assets_4129' );
function lee_dev_enqueue_sidebar_tracker_assets_4129( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    $handle = 'lee-dev-sidebar-tracker';
    wp_register_script( $handle, '', array(), null, true );
    wp_enqueue_script( $handle );

    $inline = <<<JS
( function() {
    if ( typeof document === 'undefined' ) { return; }
    var ajaxUrl = ( typeof ajaxurl !== 'undefined' ) ? ajaxurl : ( window.ajaxurl || '' );
    if ( ! ajaxUrl ) { return; }

    function init( container ) {
        if ( container.dataset.leeDevReady === '1' ) { return; }
        container.dataset.leeDevReady = '1';

        var postId    = container.getAttribute( 'data-post-id' ) || '0';
        var nonce     = container.getAttribute( 'data-nonce' )   || '';
        var maxWords  = parseInt( container.getAttribute( 'data-max-words' ) || '3', 10 );
        var chipList  = container.querySelector( '.lee-dev-sidebar-chip-list' );
        var emptyMsg  = container.querySelector( '.lee-dev-sidebar-empty' );
        var addInput  = container.querySelector( '.lee-dev-sidebar-add-input' );
        var addButton = container.querySelector( '.lee-dev-sidebar-add-button' );
        var statusEl  = container.querySelector( '.lee-dev-sidebar-status' );

        function setStatus( message, isError ) {
            if ( ! statusEl ) { return; }
            statusEl.textContent = message || '';
            statusEl.style.color = isError ? '#b32d2e' : '#646970';
        }

        function setBusy( busy ) {
            container.style.opacity = busy ? '0.6' : '1';
            if ( addButton ) { addButton.disabled = !!busy; }
            if ( addInput )  { addInput.disabled  = !!busy; }
            container.querySelectorAll( '.lee-dev-sidebar-chip-remove' ).forEach( function( btn ) {
                btn.disabled = !!busy;
            } );
        }

        function renderList( payload ) {
            if ( ! chipList ) { return; }
            var labels = ( payload && Array.isArray( payload.labels ) ) ? payload.labels : [];
            var added  = ( payload && Array.isArray( payload.added ) )  ? payload.added  : [];
            chipList.querySelectorAll( '.lee-dev-sidebar-chip' ).forEach( function( chip ) { chip.parentNode.removeChild( chip ); } );
            labels.forEach( function( phrase ) {
                var chip = document.createElement( 'span' );
                chip.className = 'lee-dev-sidebar-chip';
                chip.setAttribute( 'data-phrase', phrase );
                var isManual = added.indexOf( phrase ) !== -1;
                chip.style.background = isManual ? '#fff4e0' : '#edf8ff';
                chip.style.color      = isManual ? '#7a4a00' : '#074e85';
                chip.style.border     = '1px solid ' + ( isManual ? '#f0b849' : '#b4dcff' );
                chip.style.padding    = '4px 6px 4px 10px';
                chip.style.borderRadius = '4px';
                chip.style.fontSize   = '11px';
                chip.style.fontWeight = '600';
                chip.style.display    = 'inline-flex';
                chip.style.alignItems = 'center';
                chip.style.gap        = '6px';

                if ( isManual ) {
                    var pencil = document.createElement( 'span' );
                    pencil.setAttribute( 'aria-hidden', 'true' );
                    pencil.setAttribute( 'title', 'Manually added' );
                    pencil.style.fontSize = '10px';
                    pencil.textContent = '✎';
                    chip.appendChild( pencil );
                }

                var label = document.createElement( 'span' );
                label.className = 'lee-dev-sidebar-chip-label';
                label.textContent = phrase.replace( /\b\w/g, function( c ) { return c.toUpperCase(); } );
                chip.appendChild( label );

                var remove = document.createElement( 'button' );
                remove.type = 'button';
                remove.className = 'lee-dev-sidebar-chip-remove';
                remove.setAttribute( 'aria-label', 'Remove ' + phrase );
                remove.style.background = 'transparent';
                remove.style.border     = '0';
                remove.style.cursor     = 'pointer';
                remove.style.padding    = '0';
                remove.style.margin     = '0';
                remove.style.color      = 'inherit';
                remove.style.fontSize   = '14px';
                remove.style.lineHeight = '1';
                remove.style.fontWeight = '700';
                remove.textContent = '×';
                chip.appendChild( remove );
                chipList.insertBefore( chip, emptyMsg );
            } );
            if ( emptyMsg ) {
                emptyMsg.style.display = labels.length ? 'none' : '';
            }
        }

        function send( action, phrase ) {
            setBusy( true );
            var body = new URLSearchParams();
            body.set( 'action', action );
            body.set( 'post_id', postId );
            body.set( 'nonce', nonce );
            body.set( 'phrase', phrase );

            return fetch( ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            } ).then( function( res ) { return res.json(); } ).then( function( json ) {
                setBusy( false );
                if ( json && json.success ) {
                    renderList( json.data );
                    setStatus( json.data && json.data.message ? json.data.message : '' , false );
                } else {
                    setStatus( ( json && json.data && json.data.message ) ? json.data.message : 'Update failed.', true );
                }
            } ).catch( function() {
                setBusy( false );
                setStatus( 'Network error — please retry.', true );
            } );
        }

        if ( addButton ) {
            addButton.addEventListener( 'click', function() {
                var value = ( addInput && addInput.value ) ? addInput.value.trim() : '';
                if ( ! value ) {
                    setStatus( 'Type a keyphrase first.', true );
                    return;
                }
                var wordCount = value.split( /\s+/ ).length;
                if ( wordCount > maxWords ) {
                    setStatus( 'Phrase exceeds the ' + maxWords + '-word maximum.', true );
                    return;
                }
                send( 'lee_dev_sidebar_add_label_3158', value ).then( function() {
                    if ( addInput ) { addInput.value = ''; addInput.focus(); }
                } );
            } );
        }

        if ( addInput ) {
            addInput.addEventListener( 'keydown', function( ev ) {
                if ( ev.key === 'Enter' ) {
                    ev.preventDefault();
                    if ( addButton ) { addButton.click(); }
                }
            } );
        }

        if ( chipList ) {
            chipList.addEventListener( 'click', function( ev ) {
                var btn = ev.target.closest( '.lee-dev-sidebar-chip-remove' );
                if ( ! btn ) { return; }
                var chip = btn.closest( '.lee-dev-sidebar-chip' );
                if ( ! chip ) { return; }
                var phrase = chip.getAttribute( 'data-phrase' ) || '';
                if ( ! phrase ) { return; }
                send( 'lee_dev_sidebar_remove_label_7204', phrase );
            } );
        }
    }

    function bootstrap() {
        var containers = document.querySelectorAll( '.lee-dev-sidebar-tracker' );
        containers.forEach( init );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', bootstrap );
    } else {
        bootstrap();
    }
} )();
JS;
    wp_add_inline_script( $handle, $inline );
}

/**
 * Resolve the editor context, capability check and incoming phrase for an AJAX request.
 *
 * @return array|WP_Error { post_id, phrase, post }
 */
function lee_dev_resolve_sidebar_ajax_request_8462() {
    if ( ! is_user_logged_in() ) {
        return new WP_Error( 'lee_dev_sidebar_unauth', 'Authentication required.' );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( $post_id <= 0 ) {
        return new WP_Error( 'lee_dev_sidebar_invalid_post', 'Invalid post reference.' );
    }

    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'lee_dev_sidebar_labels_' . $post_id ) ) {
        return new WP_Error( 'lee_dev_sidebar_bad_nonce', 'Security token expired — please refresh the page.' );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        return new WP_Error( 'lee_dev_sidebar_missing_post', 'Post not found.' );
    }

    $required_cap = ( $post->post_type === 'product' ) ? 'edit_product' : 'edit_post';
    if ( ! current_user_can( $required_cap, $post_id ) ) {
        return new WP_Error( 'lee_dev_sidebar_forbidden', 'You do not have permission to edit this post.' );
    }

    $raw_phrase = isset( $_POST['phrase'] ) ? wp_unslash( $_POST['phrase'] ) : '';
    $phrase = function_exists( 'lee_dev_sanitise_manual_label_phrase_6749' )
        ? lee_dev_sanitise_manual_label_phrase_6749( $raw_phrase )
        : strtolower( trim( (string) $raw_phrase ) );

    return array(
        'post_id' => $post_id,
        'post'    => $post,
        'phrase'  => $phrase,
    );
}

/**
 * Build the JSON payload returned to the sidebar JS after an add/remove.
 */
function lee_dev_build_sidebar_ajax_payload_5921( $post_id, $message = '' ) {
    $labels = get_post_meta( (int) $post_id, '_itp_tracking_labels', true );
    if ( ! is_array( $labels ) ) {
        $labels = array();
    }
    $labels = array_values( array_filter( array_map( function( $value ) {
        return strtolower( trim( (string) $value ) );
    }, $labels ) ) );

    $overrides = function_exists( 'lee_dev_get_manual_label_overrides_4861' )
        ? lee_dev_get_manual_label_overrides_4861( $post_id )
        : array( 'added' => array(), 'removed' => array() );

    return array(
        'labels'  => $labels,
        'added'   => isset( $overrides['added'] ) ? array_values( $overrides['added'] ) : array(),
        'removed' => isset( $overrides['removed'] ) ? array_values( $overrides['removed'] ) : array(),
        'message' => (string) $message,
    );
}

/**
 * AJAX: add a manually-curated tracked keyphrase.
 */
add_action( 'wp_ajax_lee_dev_sidebar_add_label_3158', 'lee_dev_ajax_sidebar_add_label_3158' );
function lee_dev_ajax_sidebar_add_label_3158() {
    $resolved = lee_dev_resolve_sidebar_ajax_request_8462();
    if ( is_wp_error( $resolved ) ) {
        wp_send_json_error( array( 'message' => $resolved->get_error_message() ), 400 );
    }

    $post_id = $resolved['post_id'];
    $phrase  = $resolved['phrase'];

    if ( $phrase === '' ) {
        wp_send_json_error( array( 'message' => 'Keyphrase is empty or invalid.' ), 400 );
    }

    $overrides = lee_dev_get_manual_label_overrides_4861( $post_id );
    $overrides['removed'] = array_values( array_diff( $overrides['removed'], array( $phrase ) ) );
    if ( ! in_array( $phrase, $overrides['added'], true ) ) {
        $overrides['added'][] = $phrase;
    }
    lee_dev_save_manual_label_overrides_4861( $post_id, $overrides );

    $labels = get_post_meta( $post_id, '_itp_tracking_labels', true );
    if ( ! is_array( $labels ) ) {
        $labels = array();
    }
    if ( ! in_array( $phrase, $labels, true ) ) {
        $labels[] = $phrase;
    }
    $labels = array_values( array_unique( array_filter( array_map( function( $value ) {
        return strtolower( trim( (string) $value ) );
    }, $labels ) ) ) );
    update_post_meta( $post_id, '_itp_tracking_labels', $labels );

    wp_send_json_success( lee_dev_build_sidebar_ajax_payload_5921( $post_id, 'Added “' . $phrase . '”.' ) );
}

/**
 * AJAX: remove a tracked keyphrase (whether auto-detected or manual).
 */
add_action( 'wp_ajax_lee_dev_sidebar_remove_label_7204', 'lee_dev_ajax_sidebar_remove_label_7204' );
function lee_dev_ajax_sidebar_remove_label_7204() {
    $resolved = lee_dev_resolve_sidebar_ajax_request_8462();
    if ( is_wp_error( $resolved ) ) {
        wp_send_json_error( array( 'message' => $resolved->get_error_message() ), 400 );
    }

    $post_id = $resolved['post_id'];
    $phrase  = $resolved['phrase'];

    if ( $phrase === '' ) {
        wp_send_json_error( array( 'message' => 'Keyphrase is empty or invalid.' ), 400 );
    }

    $overrides = lee_dev_get_manual_label_overrides_4861( $post_id );
    $overrides['added'] = array_values( array_diff( $overrides['added'], array( $phrase ) ) );
    if ( ! in_array( $phrase, $overrides['removed'], true ) ) {
        $overrides['removed'][] = $phrase;
    }
    lee_dev_save_manual_label_overrides_4861( $post_id, $overrides );

    $labels = get_post_meta( $post_id, '_itp_tracking_labels', true );
    if ( ! is_array( $labels ) ) {
        $labels = array();
    }
    $labels = array_values( array_diff( $labels, array( $phrase ) ) );
    if ( empty( $labels ) ) {
        delete_post_meta( $post_id, '_itp_tracking_labels' );
    } else {
        update_post_meta( $post_id, '_itp_tracking_labels', $labels );
    }

    wp_send_json_success( lee_dev_build_sidebar_ajax_payload_5921( $post_id, 'Removed “' . $phrase . '”.' ) );
}

/**
 * Core Save Hook Interceptor
 */
function lee_dev_automatically_parse_page_keywords_3819($post_id, $post) {
    if ( ! $post ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) ) return;
    
    $current_type = ! empty($post->post_type) ? $post->post_type : 'post';
    if ( $current_type === 'product' ) {
        if ( ! current_user_can('edit_product', $post_id) ) return;
    } else {
        if ( ! current_user_can('edit_post', $post_id) ) return;
    }

    if ( function_exists('lee_dev_execute_combined_content_scan_1289') ) {
        lee_dev_execute_combined_content_scan_1289($post_id, $post);
    }
}
add_action('save_post', 'lee_dev_automatically_parse_page_keywords_3819', 10, 2);
