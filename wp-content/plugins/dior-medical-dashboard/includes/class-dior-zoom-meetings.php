<?php
/**
 * Dior Medical - Zoom Meetings Server-to-Server OAuth integration.
 *
 * Credentials are stored in WordPress options and never printed back into the settings form.
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Dior_Zoom_Meetings')) {
    final class Dior_Zoom_Meetings {
        const OPTION_KEY = 'dior_zoom_meetings_settings';
        const PAGE_SLUG = 'dior-zoom-meetings';

        public static function init() {
            add_action('admin_menu', [__CLASS__, 'admin_menu']);
            add_action('admin_post_dior_zoom_save_settings', [__CLASS__, 'save_settings']);
            add_action('admin_post_dior_zoom_test_connection', [__CLASS__, 'test_connection']);
        }

        public static function is_configured() {
            $settings = get_option(self::OPTION_KEY, []);
            return is_array($settings)
                && !empty($settings['account_id'])
                && !empty($settings['client_id'])
                && !empty($settings['client_secret']);
        }

        public static function admin_menu() {
            add_options_page(
                'Dior Zoom Meetings',
                'Dior Zoom Meetings',
                'manage_options',
                self::PAGE_SLUG,
                [__CLASS__, 'render_settings_page']
            );
        }

        public static function save_settings() {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You are not allowed to manage Zoom settings.', 'dior-medical-dashboard'), 403);
            }
            check_admin_referer('dior_zoom_save_settings');

            $current = get_option(self::OPTION_KEY, []);
            if (!is_array($current)) {
                $current = [];
            }

            $account_id = isset($_POST['account_id']) ? sanitize_text_field(wp_unslash($_POST['account_id'])) : '';
            $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
            $submitted_secret = isset($_POST['client_secret']) ? trim((string) wp_unslash($_POST['client_secret'])) : '';

            $settings = [
                'account_id' => $account_id,
                'client_id' => $client_id,
                // An empty secret field means retain the saved secret, never erase it accidentally.
                'client_secret' => $submitted_secret !== '' ? sanitize_text_field($submitted_secret) : (string) ($current['client_secret'] ?? ''),
            ];

            update_option(self::OPTION_KEY, $settings, false);
            delete_transient('dior_zoom_oauth_access_token');

            wp_safe_redirect(add_query_arg(
                ['page' => self::PAGE_SLUG, 'saved' => '1'],
                admin_url('options-general.php')
            ));
            exit;
        }

        public static function test_connection() {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You are not allowed to test Zoom settings.', 'dior-medical-dashboard'), 403);
            }
            check_admin_referer('dior_zoom_test_connection');
            $token = self::get_access_token();
            $status = is_wp_error($token) ? 'failed' : 'success';
            $message = is_wp_error($token) ? $token->get_error_message() : 'Zoom OAuth connection successful.';
            set_transient('dior_zoom_connection_test_result', [
                'status' => $status,
                'message' => sanitize_text_field($message),
            ], 90);
            wp_safe_redirect(add_query_arg(
                ['page' => self::PAGE_SLUG, 'tested' => $status],
                admin_url('options-general.php')
            ));
            exit;
        }

        private static function get_access_token() {
            if (!self::is_configured()) {
                return new WP_Error('dior_zoom_not_configured', 'Zoom credentials are not configured.');
            }

            $cached = get_transient('dior_zoom_oauth_access_token');
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $settings = get_option(self::OPTION_KEY, []);
            $response = wp_remote_post('https://zoom.us/oauth/token', [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($settings['client_id'] . ':' . $settings['client_secret']),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'account_credentials',
                    'account_id' => $settings['account_id'],
                ],
            ]);

            if (is_wp_error($response)) {
                return new WP_Error('dior_zoom_oauth_request_failed', 'Could not reach Zoom OAuth: ' . $response->get_error_message());
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($status < 200 || $status >= 300 || empty($body['access_token'])) {
                $message = !empty($body['reason']) ? sanitize_text_field($body['reason']) : 'Zoom rejected the credentials or required OAuth scopes.';
                return new WP_Error('dior_zoom_oauth_failed', $message);
            }

            $expires_in = isset($body['expires_in']) ? max(120, (int) $body['expires_in'] - 90) : 3300;
            set_transient('dior_zoom_oauth_access_token', (string) $body['access_token'], $expires_in);
            return (string) $body['access_token'];
        }

        /**
         * Create a Zoom meeting and return its join URL. Returns false/WP_Error on failure.
         */
        public static function create_meeting_link($date, $time, $doctor_name = '') {
            $token = self::get_access_token();
            if (is_wp_error($token)) {
                error_log('[Dior Zoom] ' . $token->get_error_message());
                return false;
            }

            $timezone = wp_timezone();
            $date_value = trim((string) $date);
            $time_value = trim((string) $time);

            // Appointment rows may store a range such as "02:00 PM - 03:00 PM".
            // Zoom needs only the appointment's start time.
            if (preg_match('/(\d{1,2}:\d{2}\s*[AP]\\.?M\\.?)/i', $time_value, $time_match)) {
                $time_value = strtoupper(preg_replace('/\s+/', ' ', str_replace('.', '', trim($time_match[1]))));
            } elseif (preg_match('/(\d{1,2}:\d{2})/', $time_value, $time_match)) {
                $time_value = $time_match[1];
            }

            $start = date_create_from_format('!Y-m-d g:i A', $date_value . ' ' . $time_value, $timezone);
            if (!$start) {
                $start = date_create_from_format('!Y-m-d H:i', $date_value . ' ' . $time_value, $timezone);
            }
            if (!$start) {
                $start = date_create($date_value . ' ' . $time_value, $timezone);
            }
            if (!$start) {
                error_log('[Dior Zoom] Invalid appointment date/time; meeting was not created.');
                return false;
            }

            $payload = [
                'topic' => 'Dior Medical Consultation' . ($doctor_name !== '' ? ' - ' . sanitize_text_field($doctor_name) : ''),
                'type' => 2,
                'start_time' => $start->format(DateTimeInterface::ATOM),
                'duration' => 30,
                'timezone' => wp_timezone_string() ?: 'UTC',
                'agenda' => 'Private patient consultation. Please use the secure patient portal for appointment details.',
                'settings' => [
                    'join_before_host' => false,
                    'waiting_room' => true,
                    'approval_type' => 2,
                    'audio' => 'both',
                    'auto_recording' => 'none',
                ],
            ];

            $response = wp_remote_post('https://api.zoom.us/v2/users/me/meetings', [
                'timeout' => 25,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
            ]);

            if (is_wp_error($response)) {
                error_log('[Dior Zoom] Meeting creation request failed: ' . $response->get_error_message());
                return false;
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($status < 200 || $status >= 300 || empty($body['join_url']) || !wp_http_validate_url($body['join_url'])) {
                $reason = !empty($body['message']) ? sanitize_text_field($body['message']) : 'Zoom returned no valid join URL.';
                error_log('[Dior Zoom] Meeting creation failed (HTTP ' . $status . '): ' . $reason);
                return false;
            }

            return esc_url_raw($body['join_url']);
        }

        public static function render_settings_page() {
            if (!current_user_can('manage_options')) {
                return;
            }

            $settings = get_option(self::OPTION_KEY, []);
            if (!is_array($settings)) {
                $settings = [];
            }
            $saved = isset($_GET['saved']) && sanitize_text_field(wp_unslash($_GET['saved'])) === '1';
            $test_result = get_transient('dior_zoom_connection_test_result');
            delete_transient('dior_zoom_connection_test_result');
            ?>
            <div class="wrap">
                <h1><?php echo esc_html__('Dior Zoom Meetings', 'dior-medical-dashboard'); ?></h1>
                <?php if (is_array($test_result) && !empty($test_result['message'])) : ?>
                    <div class="notice <?php echo ($test_result['status'] ?? '') === 'success' ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html($test_result['message']); ?></p></div>
                <?php endif; ?>
                <?php if ($saved) : ?>
                    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Zoom settings saved. Use the Test Connection button to verify your credentials.', 'dior-medical-dashboard'); ?></p></div>
                <?php endif; ?>
                <p><?php echo esc_html__('Configure a Zoom Server-to-Server OAuth app with meeting creation permissions. The Client Secret is stored server-side and is never displayed after saving.', 'dior-medical-dashboard'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('dior_zoom_save_settings'); ?>
                    <input type="hidden" name="action" value="dior_zoom_save_settings">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="dior-zoom-account-id">Account ID</label></th>
                            <td><input name="account_id" id="dior-zoom-account-id" type="text" class="regular-text" value="<?php echo esc_attr($settings['account_id'] ?? ''); ?>" autocomplete="off" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="dior-zoom-client-id">Client ID</label></th>
                            <td><input name="client_id" id="dior-zoom-client-id" type="text" class="regular-text" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>" autocomplete="off" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="dior-zoom-client-secret">Client Secret</label></th>
                            <td>
                                <input name="client_secret" id="dior-zoom-client-secret" type="password" class="regular-text" value="" autocomplete="new-password" <?php echo empty($settings['client_secret']) ? 'required' : ''; ?>>
                                <p class="description"><?php echo !empty($settings['client_secret']) ? esc_html__('A secret is already saved. Leave blank to keep it unchanged, or enter a new value to rotate it.', 'dior-medical-dashboard') : esc_html__('Enter the Client Secret from your Zoom Server-to-Server OAuth app.', 'dior-medical-dashboard'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Zoom Settings', 'dior-medical-dashboard')); ?>
                </form>
                <hr>
                <h2><?php echo esc_html__('Connection test', 'dior-medical-dashboard'); ?></h2>
                <p><?php echo esc_html__('Save credentials first, then click Test Connection to verify Zoom OAuth and required permissions.', 'dior-medical-dashboard'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('dior_zoom_test_connection'); ?>
                    <input type="hidden" name="action" value="dior_zoom_test_connection">
                    <?php submit_button(__('Test Connection', 'dior-medical-dashboard'), 'secondary', 'submit', false); ?>
                </form>
            </div>
            <?php
        }
    }
}
