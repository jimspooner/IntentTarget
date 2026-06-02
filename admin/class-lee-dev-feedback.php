<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_enqueue_scripts', 'lee_dev_enqueue_deactivation_feedback_script_7182' );
function lee_dev_enqueue_deactivation_feedback_script_7182( $hook ) {
    if ( $hook !== 'plugins.php' ) return;

    wp_enqueue_style( 'wp-jquery-ui-dialog' );
    wp_enqueue_script( 'jquery-ui-dialog' );
}

add_action( 'admin_footer-plugins.php', 'lee_dev_render_deactivation_feedback_modal_8192' );
function lee_dev_render_deactivation_feedback_modal_8192() {
    $core_key = get_option( 'lee_dev_licence_key', '' );
    $pro_key  = get_option( 'lee_dev_pro_licence_key', '' );
    ?>
    <div id="itp-deactivate-feedback-modal" style="display:none;" title="Uninstall IntentTarget">
        <p>If you have a moment, please tell us why you are uninstalling the plugin. This helps us improve.</p>
        <div style="margin-bottom: 15px;">
            <label><input type="radio" name="itp_deactivate_reason" value="too_complex" /> It's too complex or hard to use</label><br>
            <label><input type="radio" name="itp_deactivate_reason" value="found_better" /> I found a better alternative</label><br>
            <label><input type="radio" name="itp_deactivate_reason" value="missing_features" /> It's missing features I need</label><br>
            <label><input type="radio" name="itp_deactivate_reason" value="buggy" /> It didn't work / was buggy</label><br>
            <label><input type="radio" name="itp_deactivate_reason" value="temporary" /> It's just a temporary deactivation</label><br>
            <label><input type="radio" name="itp_deactivate_reason" value="other" /> Other</label>
        </div>
        <textarea id="itp_deactivate_details" placeholder="Any extra details? (Optional)" style="width:100%; height:60px; margin-bottom:15px;"></textarea>
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <button class="button button-secondary" id="itp-deactivate-skip">Skip & Deactivate</button>
            <button class="button button-primary" id="itp-deactivate-submit">Submit & Deactivate</button>
        </div>
    </div>
    
    <style>
        .ui-dialog[aria-describedby="itp-deactivate-feedback-modal"] {
            z-index: 100000;
        }
        .ui-widget-overlay { z-index: 99999; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var deactivateLink = null;
        var coreKey = <?php echo wp_json_encode( $core_key ); ?>;
        var proKey  = <?php echo wp_json_encode( $pro_key ); ?>;
        var feedbackUrl = "https://intenttargetpro.com/wp-json/intenttarget/v1/feedback";
        var pluginSlug = '';

        $('#the-list').on('click', 'a[id*="deactivate-intenttarget"], a[href*="action=deactivate"][href*="intenttarget"]', function(e) {
            e.preventDefault();
            deactivateLink = $(this).attr('href');
            
            if (deactivateLink.indexOf('intenttarget-pro.php') !== -1) {
                pluginSlug = 'pro';
            } else {
                pluginSlug = 'core';
            }

            $('#itp-deactivate-feedback-modal').dialog({
                modal: true,
                width: 450,
                resizable: false,
                draggable: false,
                classes: {
                    "ui-dialog": "wp-dialog"
                }
            });
        });

        $('#itp-deactivate-skip').on('click', function(e) {
            e.preventDefault();
            window.location.href = deactivateLink;
        });

        $('#itp-deactivate-submit').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var reason = $('input[name="itp_deactivate_reason"]:checked').val() || '';
            var details = $('#itp_deactivate_details').val() || '';
            var licenceCode = (pluginSlug === 'pro') ? proKey : coreKey;

            if (!reason && !details) {
                window.location.href = deactivateLink;
                return;
            }

            $btn.text('Sending...').prop('disabled', true);

            // Send feedback silently
            $.ajax({
                url: feedbackUrl,
                method: 'POST',
                data: {
                    licence_code: licenceCode,
                    reason: reason,
                    details: details
                },
                complete: function() {
                    window.location.href = deactivateLink;
                }
            });
        });
    });
    </script>
    <?php
}
