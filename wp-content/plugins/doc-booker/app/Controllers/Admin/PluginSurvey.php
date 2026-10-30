<?php

namespace WpDreamers\WPDDB\Controllers\Admin;

use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

/**
 * Deactivation feedback survey.
 *
 * Renders a "Quick Feedback" dialog when the admin clicks Deactivate on the
 * plugins screen, then GET-appends the answer to the shared WpDreamers survey
 * Google Sheet. The remote handler (gb-plugin-survey → Api::append_to_sheet)
 * routes each row to a tab named after the `wpplugin` value, so this sends
 * `wpplugin=DocBooker` (a "DocBooker" tab must exist in the sheet) and a
 * `version` column carrying WPDDB_VERSION.
 *
 * UI follows the doc-booker "Style B" system: Stripe-purple gradient accent,
 * selectable-card radios, soft shadows, system font.
 */
class PluginSurvey {
    use SingletonTrait;

    /**
     * Text domain (also used as the unique DOM id suffix for the dialog).
     *
     * @var string
     */
    public string $textdomain = 'doc-booker';

    /**
     * Remote endpoint that appends the survey row to the Google Sheet.
     * Shared with the Gym Builder family; the tab is selected by `wpplugin`.
     *
     * @var string
     */
    public string $endpoint = 'https://wpdreamers.com/wp-json/GymBuilder/pluginSurvey/v1/Survey/appendToSheet';

    /**
     * Value that both authorises the request (server whitelist) and names the
     * sheet tab this plugin's rows land in.
     *
     * @var string
     */
    public string $wpplugin = 'DocBooker';

    private function __construct() {
        add_action( 'admin_footer', [ $this, 'deactivation_popup' ], 99 );
    }

    public function deactivation_popup() {
        global $pagenow;
        if ( 'plugins.php' !== $pagenow ) {
            return;
        }

        $td = esc_attr( $this->textdomain );

        $this->dialog_box_style();
        $this->deactivation_scripts();
        ?>
        <div id="deactivation-dialog-<?php echo $td; ?>" title="Quick Feedback">
            <div class="dbfs-header">
                <span class="dbfs-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </span>
                <div class="dbfs-head-text">
                    <h2 class="dbfs-title"><?php echo esc_html__( 'Before you go…', 'doc-booker' ); ?></h2>
                    <p class="dbfs-sub"><?php echo esc_html__( "Mind sharing why you're deactivating DocBooker? It helps us fix issues and improve.", 'doc-booker' ); ?></p>
                </div>
            </div>

            <div class="dbfs-body">
                <div id="feedback-form-body-<?php echo $td; ?>" class="dbfs-reasons">

                    <label class="dbfs-reason" for="feedback-deactivate-<?php echo $td; ?>-bug_issue_detected">
                        <input id="feedback-deactivate-<?php echo $td; ?>-bug_issue_detected" type="radio" name="reason_key" value="bug_issue_detected">
                        <span class="dbfs-radio" aria-hidden="true"></span>
                        <span class="dbfs-reason-text"><?php echo esc_html__( 'Bug or issue detected', 'doc-booker' ); ?></span>
                    </label>

                    <label class="dbfs-reason" for="feedback-deactivate-<?php echo $td; ?>-no_longer_needed">
                        <input id="feedback-deactivate-<?php echo $td; ?>-no_longer_needed" type="radio" name="reason_key" value="no_longer_needed">
                        <span class="dbfs-radio" aria-hidden="true"></span>
                        <span class="dbfs-reason-text"><?php echo esc_html__( 'I no longer need the plugin', 'doc-booker' ); ?></span>
                    </label>

                    <label class="dbfs-reason dbfs-reason--conditional" for="feedback-deactivate-<?php echo $td; ?>-found_a_better_plugin">
                        <input id="feedback-deactivate-<?php echo $td; ?>-found_a_better_plugin" type="radio" name="reason_key" value="found_a_better_plugin">
                        <span class="dbfs-radio" aria-hidden="true"></span>
                        <span class="dbfs-reason-text"><?php echo esc_html__( 'I found a better plugin', 'doc-booker' ); ?></span>
                        <input class="feedback-feedback-text" type="text" name="reason_found_a_better_plugin"
                               placeholder="<?php echo esc_attr__( 'Which plugin? (optional)', 'doc-booker' ); ?>">
                    </label>

                    <label class="dbfs-reason" for="feedback-deactivate-<?php echo $td; ?>-booking_form_issue">
                        <input id="feedback-deactivate-<?php echo $td; ?>-booking_form_issue" type="radio" name="reason_key" value="booking_form_issue">
                        <span class="dbfs-radio" aria-hidden="true"></span>
                        <span class="dbfs-reason-text"><?php echo esc_html__( "I couldn't set up the booking form / schedule", 'doc-booker' ); ?></span>
                    </label>

                    <label class="dbfs-reason" for="feedback-deactivate-<?php echo $td; ?>-missing_feature">
                        <input id="feedback-deactivate-<?php echo $td; ?>-missing_feature" type="radio" name="reason_key" value="missing_feature">
                        <span class="dbfs-radio" aria-hidden="true"></span>
                        <span class="dbfs-reason-text"><?php echo esc_html__( 'It is missing a feature I need', 'doc-booker' ); ?></span>
                    </label>

                    <label class="dbfs-reason" for="feedback-deactivate-<?php echo $td; ?>-temporary_deactivation">
                        <input id="feedback-deactivate-<?php echo $td; ?>-temporary_deactivation" type="radio" name="reason_key" value="temporary_deactivation">
                        <span class="dbfs-radio" aria-hidden="true"></span>
                        <span class="dbfs-reason-text"><?php echo esc_html__( "It's a temporary deactivation", 'doc-booker' ); ?></span>
                    </label>

                    <span class="dbfs-err"></span>
                </div>

                <div class="feedback-text-wrapper-<?php echo $td; ?> dbfs-comment">
                    <label class="dbfs-comment-label" for="deactivation-feedback-<?php echo $td; ?>"><?php echo esc_html__( 'Anything else? How can we improve DocBooker?', 'doc-booker' ); ?></label>
                    <textarea id="deactivation-feedback-<?php echo $td; ?>" rows="3"
                              placeholder="<?php echo esc_attr__( 'Write something here…', 'doc-booker' ); ?>"></textarea>
                    <span class="dbfs-err"></span>
                </div>
            </div>
        </div>
        <?php
    }

    public function dialog_box_style() {
        $td = esc_attr( $this->textdomain );
        ?>
        <style>
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"],
            #deactivation-dialog-<?php echo $td; ?> {
                --dbfs-primary: #635bff;
                --dbfs-grad: linear-gradient(135deg, #635bff 0%, #9333ea 100%);
                --dbfs-text: #0a2540;
                --dbfs-soft: #425466;
                --dbfs-muted: #64748b;
                --dbfs-border: #eef0f4;
                --dbfs-border-strong: #d6e0eb;
                --dbfs-bg-alt: #f7faff;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                box-sizing: border-box;
            }

            #deactivation-dialog-<?php echo $td; ?> *,
            #deactivation-dialog-<?php echo $td; ?> *::before,
            #deactivation-dialog-<?php echo $td; ?> *::after { box-sizing: border-box; }

            /* ---- overlay ---- */
            .ui-widget-overlay.ui-front {
                position: fixed;
                inset: 0;
                z-index: 100000;
                background: rgba(10, 37, 64, .45);
            }

            /* ---- dialog shell ---- */
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"] {
                padding: 0 !important;
                border: none !important;
                border-radius: 16px !important;
                background: #fff !important;
                box-shadow: 0 24px 64px rgba(10, 37, 64, .28), 0 4px 12px rgba(10, 37, 64, .10) !important;
                overflow: hidden;
                width: 500px !important;
                max-width: calc(100vw - 32px) !important;
                z-index: 100001 !important;
            }

            /* hide default titlebar + close — we render our own header */
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"] .ui-dialog-titlebar,
            .ui-dialog-titlebar-close {
                display: none !important;
            }

            /* ---- content ---- */
            #deactivation-dialog-<?php echo $td; ?> {
                display: none;
                padding: 0 !important;
                margin: 0;
                color: var(--dbfs-text);
                overflow: visible;
            }
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"] .ui-dialog-content {
                padding: 0 !important;
            }

            /* ---- header ---- */
            #deactivation-dialog-<?php echo $td; ?> .dbfs-header {
                display: flex;
                align-items: flex-start;
                gap: 14px;
                padding: 24px 24px 16px;
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-icon {
                flex: 0 0 auto;
                width: 44px;
                height: 44px;
                border-radius: 12px;
                background: var(--dbfs-grad);
                color: #fff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 6px 16px rgba(99, 91, 255, .32);
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-title {
                margin: 3px 0 4px;
                padding: 0;
                font-size: 18px;
                font-weight: 700;
                line-height: 1.2;
                letter-spacing: -.01em;
                color: var(--dbfs-text);
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-sub {
                margin: 0;
                font-size: 13px;
                line-height: 1.5;
                color: var(--dbfs-muted);
            }

            /* ---- body ---- */
            #deactivation-dialog-<?php echo $td; ?> .dbfs-body {
                padding: 4px 24px 22px;
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-reasons {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            /* ---- reason cards ---- */
            #deactivation-dialog-<?php echo $td; ?> .dbfs-reason {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
                margin: 0;
                padding: 12px 14px;
                border: 1.5px solid var(--dbfs-border);
                border-radius: 10px;
                background: #fff;
                cursor: pointer;
                transition: border-color .15s, background .15s, box-shadow .15s;
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-reason:hover {
                border-color: var(--dbfs-border-strong);
                background: var(--dbfs-bg-alt);
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-reason.is-selected {
                border-color: var(--dbfs-primary);
                background: rgba(99, 91, 255, .06);
                box-shadow: 0 0 0 3px rgba(99, 91, 255, .10);
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-reason input[type="radio"] {
                position: absolute;
                opacity: 0;
                width: 0;
                height: 0;
                margin: 0;
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-radio {
                flex: 0 0 auto;
                width: 18px;
                height: 18px;
                border-radius: 50%;
                border: 2px solid var(--dbfs-border-strong);
                background: #fff;
                position: relative;
                transition: border-color .15s;
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-reason.is-selected .dbfs-radio {
                border-color: var(--dbfs-primary);
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-reason.is-selected .dbfs-radio::after {
                content: "";
                position: absolute;
                inset: 3px;
                border-radius: 50%;
                background: var(--dbfs-primary);
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-reason-text {
                flex: 1;
                font-size: 14px;
                font-weight: 500;
                line-height: 1.35;
                color: var(--dbfs-text);
            }

            /* conditional "which plugin" input (hidden until selected, shown via JS) */
            #deactivation-dialog-<?php echo $td; ?> .feedback-feedback-text {
                display: none;
                flex: 0 0 100%;
                width: 100%;
                margin: 4px 0 2px 30px;
                max-width: calc(100% - 30px);
                border: 1.5px solid var(--dbfs-border-strong);
                border-radius: 8px;
                padding: 9px 12px;
                font-size: 13px;
                font-family: inherit;
                outline: none;
                transition: border-color .15s, box-shadow .15s;
            }
            #deactivation-dialog-<?php echo $td; ?> .feedback-feedback-text:focus {
                border-color: var(--dbfs-primary);
                box-shadow: 0 0 0 3px rgba(99, 91, 255, .12);
            }

            /* ---- comment ---- */
            #deactivation-dialog-<?php echo $td; ?> .dbfs-comment {
                margin-top: 16px;
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-comment-label {
                display: block;
                margin: 0 0 8px;
                font-size: 12px;
                font-weight: 600;
                color: var(--dbfs-soft);
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-comment textarea {
                width: 100%;
                min-height: 82px;
                border: 1.5px solid var(--dbfs-border-strong);
                border-radius: 10px;
                padding: 12px 14px;
                font-size: 13px;
                line-height: 1.5;
                font-family: inherit;
                resize: vertical;
                outline: none;
                color: var(--dbfs-text);
                transition: border-color .15s, box-shadow .15s;
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-comment textarea:focus {
                border-color: var(--dbfs-primary);
                box-shadow: 0 0 0 3px rgba(99, 91, 255, .12);
            }

            /* ---- inline errors ---- */
            #deactivation-dialog-<?php echo $td; ?> .dbfs-err {
                display: block;
                color: #d92d20;
                font-size: 12px;
                line-height: 1.4;
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-reasons .dbfs-err:empty,
            #deactivation-dialog-<?php echo $td; ?> .dbfs-comment .dbfs-err:empty {
                display: none;
            }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-reasons .dbfs-err { margin-top: 2px; }
            #deactivation-dialog-<?php echo $td; ?> .dbfs-comment .dbfs-err { margin-top: 6px; }

            /* ---- footer / buttons ---- */
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"] .ui-dialog-buttonpane {
                margin: 0;
                padding: 16px 24px;
                border: none;
                border-top: 1px solid var(--dbfs-border);
                background: #fbfcfe;
            }
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"] .ui-dialog-buttonset {
                float: none;
                display: flex;
                flex-direction: row-reverse;
                justify-content: space-between;
                align-items: center;
                width: 100%;
                gap: 10px;
                padding: 0;
                background: transparent;
                box-shadow: none;
            }
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"] .ui-dialog-buttonset button {
                margin: 0;
                min-width: auto;
                height: 42px;
                padding: 0 20px;
                border: none;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 600;
                line-height: 1;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: transform .15s, box-shadow .15s, background .15s, color .15s;
            }
            /* primary = Submit (first in DOM, shown on the right by row-reverse) */
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"] .ui-dialog-buttonset button:first-child {
                background: var(--dbfs-grad);
                color: #fff;
                box-shadow: 0 4px 14px rgba(99, 91, 255, .36);
            }
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"] .ui-dialog-buttonset button:first-child:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 18px rgba(99, 91, 255, .46);
            }
            /* Skip (last in DOM, shown on the left) — intentionally low-emphasis:
               plain text, no border/background, so it reads as a quiet "get me out"
               link and the primary Submit action carries the visual weight. */
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"] .ui-dialog-buttonset button:last-child {
                background: transparent;
                color: var(--dbfs-muted);
                border: none;
                box-shadow: none;
                font-weight: 500;
                padding: 0 6px;
            }
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"] .ui-dialog-buttonset button:last-child:hover {
                background: transparent;
                color: var(--dbfs-soft);
                text-decoration: underline;
            }
            .ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; ?>"] .ui-dialog-buttonset button:focus-visible {
                outline: 2px solid rgba(99, 91, 255, .5);
                outline-offset: 1px;
            }

            @media (max-width: 540px) {
                #deactivation-dialog-<?php echo $td; ?> .dbfs-header,
                #deactivation-dialog-<?php echo $td; ?> .dbfs-body { padding-left: 18px; padding-right: 18px; }
            }
        </style>
        <?php
    }

    public function deactivation_scripts() {
        wp_enqueue_script( 'jquery-ui-dialog' );

        // Plugin "install age" — whole days since the first-activation timestamp
        // recorded by Installation::plugin_activation_time(). Blank if unknown.
        $activation_time = (int) get_option( 'wpddb_plugin_activation_time' );
        $days_active     = $activation_time ? (int) floor( ( time() - $activation_time ) / DAY_IN_SECONDS ) : '';
        ?>
        <script>
            jQuery(document).ready(function ($) {
                var td = '<?php echo esc_js( $this->textdomain ); ?>';
                var dialogSel = '#deactivation-dialog-' + td;

                // Robust: target the deactivate link inside THIS plugin's row via its data-plugin
                // attribute, not the sanitized-name id (DocBooker's name doesn't sanitize to "doc-booker").
                var $deactivateLink = $('tr[data-plugin="<?php echo esc_js( WPDDB_BASENAME ); ?>"] .deactivate a');

                $deactivateLink.on('click', function (e) {
                    e.preventDefault();
                    var href = $(this).attr('href');

                    // reset state
                    $(dialogSel + ' .dbfs-reason').removeClass('is-selected');
                    $(dialogSel + ' input[type="radio"]').prop('checked', false);
                    $(dialogSel + ' .feedback-feedback-text').hide().val('');
                    $(dialogSel + ' .dbfs-err').text('');

                    var dialogbox = $(dialogSel).dialog({
                        modal: true,
                        width: 500,
                        resizable: false,
                        draggable: false,
                        show: { effect: "fadeIn", duration: 250 },
                        hide: { effect: "fadeOut", duration: 120 },
                        buttons: {
                            Submit: function () {
                                submitFeedback(href);
                            },
                            Cancel: function () {
                                $(this).dialog('close');
                                window.location.href = href;
                            }
                        }
                    });

                    // Selectable-card + conditional field behaviour.
                    dialogbox.on('change', 'input[type="radio"]', function () {
                        $(dialogSel + ' .dbfs-reason').removeClass('is-selected');
                        $(this).closest('.dbfs-reason').addClass('is-selected');

                        var reason = $(dialogSel + ' input[type="radio"]:checked').val();
                        if ('found_a_better_plugin' === reason) {
                            $(dialogSel + ' .feedback-feedback-text').show().trigger('focus');
                        } else {
                            $(dialogSel + ' .feedback-feedback-text').hide();
                        }
                        $(dialogSel + ' .dbfs-reasons .dbfs-err').text('');
                    });

                    // Clear the comment error while typing.
                    dialogbox.on('input', '#deactivation-feedback-' + td, function () {
                        $(dialogSel + ' .dbfs-comment .dbfs-err').text('');
                    });

                    // Close when clicking the backdrop.
                    $(document).on('click', '.ui-widget-overlay.ui-front', function (event) {
                        if ($(event.target).closest(dialogbox.parent()).length === 0) {
                            dialogbox.dialog('close');
                        }
                    });

                    $('.ui-dialog-buttonpane button:contains("Submit")').text('Submit & Deactivate');
                    $('.ui-dialog-buttonpane button:contains("Cancel")').text('Skip & Deactivate');
                });

                function submitFeedback(href) {
                    var reason = $(dialogSel + ' input[type="radio"]:checked').val();
                    var feedback = $('#deactivation-feedback-' + td).val();
                    var better_plugin = $(dialogSel + ' .feedback-feedback-text').val();

                    if (!reason) {
                        $(dialogSel + ' .dbfs-reasons .dbfs-err').text('Please choose a reason so we know what to fix.');
                        return;
                    }

                    if ('bug_issue_detected' === reason && !feedback) {
                        $(dialogSel + ' .dbfs-comment .dbfs-err').text('Please describe the issue so we can address it in a future update.');
                        $('#deactivation-feedback-' + td).trigger('focus');
                        return;
                    }

                    if ('temporary_deactivation' === reason && !feedback) {
                        window.location.href = href;
                        return;
                    }

                    $.ajax({
                        url: '<?php echo esc_url( $this->endpoint ); ?>',
                        method: 'GET',
                        dataType: 'json',
                        // Column order in the "DocBooker" sheet tab (A:H):
                        // Website | Reason | Better Plugin | Feedback | Version | Days Active | Plugin | Date
                        data: {
                            website: '<?php echo esc_url( home_url() ); ?>',
                            reasons: reason ? reason : '',
                            better_plugin: better_plugin,
                            feedback: feedback,
                            version: '<?php echo esc_js( WPDDB_VERSION ); ?>',
                            days_active: '<?php echo esc_js( $days_active ); ?>',
                            wpplugin: '<?php echo esc_js( $this->wpplugin ); ?>',
                            date: new Date().toISOString().split('T')[0]
                        },
                        error: function (xhr, status, error) {
                            console.error('DocBooker survey error', error);
                        },
                        complete: function () {
                            $(dialogSel).dialog('close');
                            window.location.href = href;
                        }
                    });
                }
            });
        </script>
        <?php
    }
}
