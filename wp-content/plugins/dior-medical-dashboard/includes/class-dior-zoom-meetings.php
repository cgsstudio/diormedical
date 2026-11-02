<?php
/**
 * Dior Medical - Zoom Meetings Server-to-Server OAuth integration.
 * Credentials are stored in WordPress options and never printed back into settings.
 */
if (!defined('ABSPATH')) { exit; }

if (!class_exists('Dior_Zoom_Meetings')) {
    final class Dior_Zoom_Meetings {
        const OPTION_KEY = 'dior_zoom_meetings_settings';
        const PAGE_SLUG = 'dior-zoom-meetings';

        public static function init() {
            add_action('admin_menu', [__CLASS__, 'admin_menu']);
            add_action('admin_post_dior_zoom_save_settings', [__CLASS__, 'save_settings']);
            add_action('admin_post_dior_zoom_test_connection', [__CLASS__, 'test_connection']);
        }

        /**
         * Resolve credentials saved by this integration or an earlier Zoom setup.
         * Legacy values are copied into the canonical option without deleting the source.
         */
        private static function get_settings() {
            $settings = get_option(self::OPTION_KEY, []);
            if (!is_array($settings)) { $settings = []; }

            $normalize = static function ($value) use (&$normalize) {
                $found = ['account_id' => '', 'client_id' => '', 'client_secret' => ''];
                if (!is_array($value)) { return $found; }
                foreach ($value as $key => $item) {
                    $key_normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key));
                    if (is_array($item)) {
                        $nested = $normalize($item);
                        foreach ($found as $field => $unused) {
                            if ($found[$field] === '' && $nested[$field] !== '') { $found[$field] = $nested[$field]; }
                        }
                        continue;
                    }
                    if (!is_scalar($item) || trim((string) $item) === '') { continue; }
                    $value_string = trim((string) $item);
                    if ($found['account_id'] === '' && in_array($key_normalized, ['accountid', 'zoomaccountid', 'diorzoomaccountid'], true)) {
                        $found['account_id'] = $value_string;
                    } elseif ($found['client_id'] === '' && in_array($key_normalized, ['clientid', 'zoomclientid', 'diorzoomclientid'], true)) {
                        $found['client_id'] = $value_string;
                    } elseif ($found['client_secret'] === '' && in_array($key_normalized, ['clientsecret', 'zoomclientsecret', 'diorzoomclientsecret'], true)) {
                        $found['client_secret'] = $value_string;
                    }
                }
                return $found;
            };

            $resolved = $normalize($settings);
            $legacy_option_names = [
                'dior_zoom_settings', 'dior_zoom_api_settings', 'dior_zoom_credentials',
                'zoom_settings', 'zoom_api_settings', 'zoom_credentials',
                'zoom_oauth_settings', 'zoom_api_credentials', 'zoom_meetings_settings',
            ];
            foreach ($legacy_option_names as $option_name) {
                if ($resolved['account_id'] && $resolved['client_id'] && $resolved['client_secret']) { break; }
                $legacy = get_option($option_name, []);
                $candidate = $normalize($legacy);
                foreach ($resolved as $field => $unused) {
                    if ($resolved[$field] === '' && $candidate[$field] !== '') { $resolved[$field] = $candidate[$field]; }
                }
            }

            // Also support legacy integrations that stored each credential as a separate option.
            $separate_options = [
                'account_id' => ['zoom_account_id', 'dior_zoom_account_id'],
                'client_id' => ['zoom_client_id', 'dior_zoom_client_id'],
                'client_secret' => ['zoom_client_secret', 'dior_zoom_client_secret'],
            ];
            foreach ($separate_options as $field => $names) {
                if ($resolved[$field] !== '') { continue; }
                foreach ($names as $option_name) {
                    $candidate = get_option($option_name, '');
                    if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                        $resolved[$field] = trim((string) $candidate);
                        break;
                    }
                }
            }

            // Discover non-autoloaded legacy options whose names contain "zoom".
            if (!$resolved['account_id'] || !$resolved['client_id'] || !$resolved['client_secret']) {
                global $wpdb;
                $option_names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%zoom%' LIMIT 100");
                if (is_array($option_names)) {
                    foreach ($option_names as $option_name) {
                        $candidate = $normalize(get_option($option_name, []));
                        foreach ($resolved as $field => $unused) {
                            if ($resolved[$field] === '' && $candidate[$field] !== '') { $resolved[$field] = $candidate[$field]; }
                        }
                        if ($resolved['account_id'] && $resolved['client_id'] && $resolved['client_secret']) { break; }
                    }
                }
            }

            // Migrate discovered values once, preserving every legacy option untouched.
            if ($resolved['account_id'] || $resolved['client_id'] || $resolved['client_secret']) {
                $merged = $settings;
                foreach ($resolved as $field => $value) {
                    if ($value !== '') { $merged[$field] = $value; }
                }
                if ($merged !== $settings) {
                    update_option(self::OPTION_KEY, $merged, false);
                }
                return $merged;
            }
            return $settings;
        }

        public static function is_configured() {
            $settings = self::get_settings();
            return !empty($settings['account_id']) && !empty($settings['client_id']) && !empty($settings['client_secret']);
        }

        public static function admin_menu() {
            add_options_page('Dior Zoom Meetings', 'Dior Zoom Meetings', 'manage_options', self::PAGE_SLUG, [__CLASS__, 'render_settings_page']);
        }

        public static function save_settings() {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You are not allowed to manage Zoom settings.', 'dior-medical-dashboard'), 403);
            }
            check_admin_referer('dior_zoom_save_settings');
            $current = self::get_settings();
            if (!is_array($current)) { $current = []; }
            $account_id = isset($_POST['account_id']) ? sanitize_text_field(wp_unslash($_POST['account_id'])) : '';
            $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
            $submitted_secret = isset($_POST['client_secret']) ? trim((string) wp_unslash($_POST['client_secret'])) : '';
            update_option(self::OPTION_KEY, [
                'account_id' => $account_id,
                'client_id' => $client_id,
                'client_secret' => $submitted_secret !== '' ? sanitize_text_field($submitted_secret) : (string) ($current['client_secret'] ?? ''),
            ], false);
            delete_transient('dior_zoom_oauth_access_token');
            wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'saved' => '1'], admin_url('options-general.php')));
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
            set_transient('dior_zoom_connection_test_result', ['status' => $status, 'message' => sanitize_text_field($message)], 90);
            wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'tested' => $status], admin_url('options-general.php')));
            exit;
        }

        private static function get_access_token() {
            if (!self::is_configured()) {
                return new WP_Error('dior_zoom_not_configured', 'Zoom credentials are not configured.');
            }
            $cached = get_transient('dior_zoom_oauth_access_token');
            if (is_string($cached) && $cached !== '') { return $cached; }
            $settings = self::get_settings();
            $response = wp_remote_post('https://zoom.us/oauth/token', [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($settings['client_id'] . ':' . $settings['client_secret']),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => ['grant_type' => 'account_credentials', 'account_id' => $settings['account_id']],
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

        /** Create a Zoom meeting and return its join URL. */
        public static function create_meeting_link($date, $time, $doctor_name = '') {
            $token = self::get_access_token();
            if (is_wp_error($token)) {
                error_log('[Dior Zoom] ' . $token->get_error_message());
                return false;
            }
            $timezone = wp_timezone();
            $date_value = trim((string) $date);
            $time_value = trim((string) $time);
            if (preg_match('/(\d{1,2}:\d{2}\s*[AP]\.?M\.?)/i', $time_value, $m)) {
                $time_value = strtoupper(preg_replace('/\s+/', ' ', str_replace('.', '', trim($m[1]))));
            } elseif (preg_match('/(\d{1,2}:\d{2})/', $time_value, $m)) {
                $time_value = $m[1];
            }
            $start = date_create_from_format('!Y-m-d g:i A', $date_value . ' ' . $time_value, $timezone);
            if (!$start) { $start = date_create_from_format('!Y-m-d H:i', $date_value . ' ' . $time_value, $timezone); }
            if (!$start) { $start = date_create($date_value . ' ' . $time_value, $timezone); }
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

        /**
         * Build a Zoom Web Client URL with the participant's display name prefilled.
         * Zoom's documented web-client pattern uses a Base64 encoded "un" parameter.
         * The native Zoom app URL cannot reliably prefill a guest display name.
         */
        public static function get_personalized_join_url($join_url, $display_name) {
            $join_url = esc_url_raw((string) $join_url);
            $display_name = trim(sanitize_text_field((string) $display_name));
            if (!$join_url || !$display_name || !wp_http_validate_url($join_url)) {
                return $join_url;
            }

            $parts = wp_parse_url($join_url);
            if (empty($parts['host']) || !preg_match('/(^|\.)zoom\.us$/i', $parts['host'])) {
                return $join_url;
            }

            $meeting_id = '';
            if (!empty($parts['path']) && preg_match('~/j/([0-9]+)(?:/|$)~', $parts['path'], $matches)) {
                $meeting_id = $matches[1];
            } elseif (!empty($parts['path']) && preg_match('~/wc/([0-9]+)/join~', $parts['path'], $matches)) {
                $meeting_id = $matches[1];
            }
            if (!$meeting_id) {
                return ''; // Do not expose Zoom's generic join landing page.
            }

            $query = [];
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
            }
            $url = 'https://zoom.us/wc/' . rawurlencode($meeting_id) . '/join?prefer=1&un=' . rawurlencode(base64_encode($display_name));
            if (!empty($query['pwd'])) {
                $url .= '&pwd=' . rawurlencode((string) $query['pwd']);
            }
            return esc_url_raw($url);
        }

        public static function render_settings_page() {
            if (!current_user_can('manage_options')) { return; }
            $settings = self::get_settings();
            if (!is_array($settings)) { $settings = []; }
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
                        <tr><th scope="row"><label for="dior-zoom-account-id">Account ID</label></th><td><input name="account_id" id="dior-zoom-account-id" type="text" class="regular-text" value="<?php echo esc_attr($settings['account_id'] ?? ''); ?>" autocomplete="off" required></td></tr>
                        <tr><th scope="row"><label for="dior-zoom-client-id">Client ID</label></th><td><input name="client_id" id="dior-zoom-client-id" type="text" class="regular-text" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>" autocomplete="off" required></td></tr>
                        <tr><th scope="row"><label for="dior-zoom-client-secret">Client Secret</label></th><td><input name="client_secret" id="dior-zoom-client-secret" type="password" class="regular-text" value="" autocomplete="new-password" <?php echo empty($settings['client_secret']) ? 'required' : ''; ?>><p class="description"><?php echo !empty($settings['client_secret']) ? esc_html__('A secret is already saved. Leave blank to keep it unchanged, or enter a new value to rotate it.', 'dior-medical-dashboard') : esc_html__('Enter the Client Secret from your Zoom Server-to-Server OAuth app.', 'dior-medical-dashboard'); ?></p></td></tr>
                    </table>
                    <?php submit_button(__('Save Zoom Settings', 'dior-medical-dashboard')); ?>
                </form>
                <hr><h2><?php echo esc_html__('Connection test', 'dior-medical-dashboard'); ?></h2>
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