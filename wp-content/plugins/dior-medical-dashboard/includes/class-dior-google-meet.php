<?php
/**
 * Google Meet OAuth configuration and meeting creation for Dior Medical.
 * Credentials are managed by WordPress administrators, never exposed to doctor users.
 */
if (!defined('ABSPATH')) { exit; }

if (!class_exists('Dior_Google_Meet')) {
final class Dior_Google_Meet {
    /** User who initiated the current OAuth round-trip, when recoverable. */
    private static $oauth_user_id = 0;
    const OPTION_KEY = 'dior_google_meet_settings';
    const PAGE_SLUG = 'dior-google-meet';
    const SCOPE = 'https://www.googleapis.com/auth/meetings.space.created';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_dior_google_meet_save', [__CLASS__, 'save_settings']);
        add_action('admin_post_dior_google_meet_connect', [__CLASS__, 'begin_oauth']);
        add_action('admin_post_dior_google_meet_oauth_callback', [__CLASS__, 'oauth_callback']);
        // Google may return to the callback after the browser drops the WP admin cookie.
        // The one-time OAuth state below still authenticates the initiating administrator.
        add_action('admin_post_nopriv_dior_google_meet_oauth_callback', [__CLASS__, 'oauth_callback']);
        add_action('admin_post_dior_google_meet_disconnect', [__CLASS__, 'disconnect']);
    }

    public static function admin_menu() {
        add_options_page(
            'Google Meet OAuth',
            'Google Meet OAuth',
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function settings() {
        $settings = get_option(self::OPTION_KEY, []);
        return is_array($settings) ? $settings : [];
    }

    public static function redirect_uri() {
        // Use an explicit WordPress admin-post action so WordPress dispatches the
        // OAuth callback directly instead of relying on admin_init interception.
        $callback_url = add_query_arg('action', 'dior_google_meet_oauth_callback', admin_url('admin-post.php'));
        return set_url_scheme($callback_url, 'https');
    }

    public static function save_settings() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You are not allowed to manage Google Meet settings.', 'dior-medical-dashboard'), 403); }
        check_admin_referer('dior_google_meet_save');
        $settings = self::settings();
        $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
        $client_secret = isset($_POST['client_secret']) ? trim((string) wp_unslash($_POST['client_secret'])) : '';
        if ($client_id !== '') { $settings['client_id'] = $client_id; }
        // An empty secret field means keep the previously stored secret.
        if ($client_secret !== '') { $settings['client_secret'] = sanitize_text_field($client_secret); }
        update_option(self::OPTION_KEY, $settings, false);
        wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'status' => 'saved'], admin_url('options-general.php')));
        exit;
    }

    public static function begin_oauth() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Not allowed.', 'dior-medical-dashboard'), 403); }
        check_admin_referer('dior_google_meet_connect');
        $settings = self::settings();
        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'status' => 'missing_credentials'], admin_url('options-general.php')));
            exit;
        }
        $state = wp_generate_password(32, false, false);
        set_transient('dior_google_meet_oauth_state_' . hash('sha256', $state), [
            'user_id' => get_current_user_id(),
            'created' => time(),
        ], 10 * MINUTE_IN_SECONDS);
        $url = add_query_arg([
            'client_id' => $settings['client_id'],
            'redirect_uri' => self::redirect_uri(),
            'response_type' => 'code',
            'scope' => 'openid email profile ' . self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], 'https://accounts.google.com/o/oauth2/v2/auth');
        wp_redirect($url);
        exit;
    }

    public static function oauth_callback() {
        // Validate the one-time state against the administrator who started the flow.
        // Do not depend solely on the WP login cookie: some browsers/cookie policies may
        // omit it on the Google-to-WordPress callback, which otherwise strands the OAuth flow.
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $state_key = 'dior_google_meet_oauth_state_' . hash('sha256', $state);
        $state_data = $state ? get_transient($state_key) : false;
        if ($state) { delete_transient($state_key); }
        $initiating_user_id = (int) ($state_data['user_id'] ?? 0);
        self::$oauth_user_id = $initiating_user_id;
        $initiating_user = $initiating_user_id ? get_userdata($initiating_user_id) : false;
        $state_is_valid = $state_data
            && $initiating_user
            && user_can($initiating_user_id, 'manage_options')
            && (time() - (int) ($state_data['created'] ?? 0)) <= 600;
        if (is_user_logged_in() && get_current_user_id() !== $initiating_user_id) {
            $state_is_valid = false;
        }
        if (!$state_is_valid) {
            self::redirect_status('oauth_state_error');
        }
        if (!empty($_GET['error'])) {
            $google_error = sanitize_text_field(wp_unslash($_GET['error']));
            self::redirect_status('oauth_denied', $google_error);
        }
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        if (!$code) { self::redirect_status('oauth_code_missing'); }
        $settings = self::settings();
        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            self::redirect_status('missing_credentials');
        }
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'code' => $code,
                'client_id' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'redirect_uri' => self::redirect_uri(),
                'grant_type' => 'authorization_code',
            ],
        ]);
        if (is_wp_error($response)) {
            self::redirect_status('token_exchange_error', $response->get_error_code());
        }
        $response_code = (int) wp_remote_retrieve_response_code($response);
        $token = json_decode(wp_remote_retrieve_body($response), true);
        if ($response_code < 200 || $response_code >= 300 || empty($token['access_token'])) {
            // Show the safe Google error code to the administrator, never token/secret data.
            $detail = is_array($token) ? (string) ($token['error'] ?? '') : '';
            if ($detail === '' && $response_code) { $detail = 'HTTP ' . $response_code; }
            self::redirect_status('token_exchange_error', $detail);
        }
        $settings['access_token'] = sanitize_text_field($token['access_token']);
        if (!empty($token['refresh_token'])) { $settings['refresh_token'] = sanitize_text_field($token['refresh_token']); }
        $settings['expires_at'] = time() + max(60, (int) ($token['expires_in'] ?? 3600)) - 60;
        $settings['connected_at'] = current_time('mysql');
        update_option(self::OPTION_KEY, $settings, false);
        self::redirect_status('connected');
    }

    private static function redirect_status($status, $detail = '') {
        // Persist the outcome because some WordPress/custom-login setups drop the
        // admin cookie on Google's cross-site callback and redirect the browser away.
        // The settings screen can then show the outcome after the administrator returns.
        $safe_detail = $detail !== '' ? sanitize_text_field((string) $detail) : '';
        update_option('dior_google_meet_oauth_last_result', [
            'status' => sanitize_key((string) $status),
            'detail' => $safe_detail,
            'user_id' => (int) self::$oauth_user_id,
            'created' => time(),
        ], false);

        $target = add_query_arg(['page' => self::PAGE_SLUG, 'status' => $status], admin_url('options-general.php'));
        if ($safe_detail !== '') {
            $target = add_query_arg('detail', $safe_detail, $target);
        }

        // If the callback arrived without a WordPress login cookie, go through the
        // normal WordPress login URL instead of letting custom frontend intake redirects
        // hijack the final admin-settings redirect. Tokens/outcome are already persisted.
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url($target));
        } else {
            wp_safe_redirect($target);
        }
        exit;
    }

    public static function disconnect() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Not allowed.', 'dior-medical-dashboard'), 403); }
        check_admin_referer('dior_google_meet_disconnect');
        $settings = self::settings();
        unset($settings['access_token'], $settings['refresh_token'], $settings['expires_at'], $settings['connected_at']);
        update_option(self::OPTION_KEY, $settings, false);
        self::redirect_status('disconnected');
    }

    private static function access_token() {
        $settings = self::settings();
        if (empty($settings['client_id']) || empty($settings['client_secret'])) { return false; }
        if (!empty($settings['access_token']) && !empty($settings['expires_at']) && (int) $settings['expires_at'] > time() + 30) {
            return $settings['access_token'];
        }
        if (empty($settings['refresh_token'])) { return false; }
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'client_id' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'refresh_token' => $settings['refresh_token'],
                'grant_type' => 'refresh_token',
            ],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300) { return false; }
        $token = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($token['access_token'])) { return false; }
        $settings['access_token'] = sanitize_text_field($token['access_token']);
        $settings['expires_at'] = time() + max(60, (int) ($token['expires_in'] ?? 3600)) - 60;
        update_option(self::OPTION_KEY, $settings, false);
        return $settings['access_token'];
    }

    /** Create a real Google Meet space and return its meeting URI, or false on failure. */
    public static function create_meeting_link() {
        $token = self::access_token();
        if (!$token) { return false; }
        $response = wp_remote_post('https://meet.googleapis.com/v2/spaces', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode(new stdClass()),
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300) { return false; }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $uri = isset($body['meetingUri']) ? esc_url_raw($body['meetingUri']) : '';
        return $uri && wp_http_validate_url($uri) ? $uri : false;
    }

    public static function is_connected() {
        $settings = self::settings();
        return !empty($settings['refresh_token']) || (!empty($settings['access_token']) && !empty($settings['expires_at']) && (int) $settings['expires_at'] > time());
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) { return; }
        $settings = self::settings();
        $connected = !empty($settings['refresh_token']) || (!empty($settings['access_token']) && !empty($settings['expires_at']) && (int) $settings['expires_at'] > time());
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $detail = isset($_GET['detail']) ? sanitize_text_field(wp_unslash($_GET['detail'])) : '';
        // Recover the most recent OAuth result if the host's login redirect stripped
        // status query args while returning from Google.
        if ($status === '') {
            $last_result = get_option('dior_google_meet_oauth_last_result', []);
            if (is_array($last_result)
                && !empty($last_result['status'])
                && (time() - (int) ($last_result['created'] ?? 0)) <= 900
                && (empty($last_result['user_id']) || (int) $last_result['user_id'] === get_current_user_id())) {
                $status = sanitize_key((string) $last_result['status']);
                $detail = sanitize_text_field((string) ($last_result['detail'] ?? ''));
            }
        }
        $messages = [
            'saved' => ['success', 'Google OAuth credentials saved.'],
            'connected' => ['success', 'Google account connected successfully.'],
            'disconnected' => ['success', 'Google account disconnected.'],
            'missing_credentials' => ['error', 'Enter both Client ID and Client Secret before connecting.'],
            'oauth_state_error' => ['error', 'OAuth verification failed or expired. Please connect again.'],
            'oauth_denied' => ['error', 'Google account connection was cancelled or denied.'],
            'oauth_code_missing' => ['error', 'Google did not return an authorization code.'],
            'token_exchange_error' => ['error', 'Google token exchange failed. Verify the Client ID, Secret, redirect URI, and OAuth configuration.'],
        ];
        echo '<div class="wrap"><h1>Google Meet OAuth</h1><p>Configure Google Meet for telehealth consultation links. Credentials are only visible to WordPress administrators.</p>';
        if (isset($messages[$status])) {
            echo '<div class="notice notice-' . esc_attr($messages[$status][0]) . ' is-dismissible"><p>' . esc_html($messages[$status][1]);
            if ($detail !== '' && in_array($status, ['token_exchange_error', 'oauth_denied'], true)) {
                echo ' <strong>Google error:</strong> ' . esc_html($detail);
            }
            echo '</p></div>';
        }
        echo '<div style="max-width:900px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:24px;margin-top:18px">';
        echo '<h2 style="margin-top:0">API Credentials</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('dior_google_meet_save');
        echo '<input type="hidden" name="action" value="dior_google_meet_save">';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="dior-google-client-id">OAuth Client ID</label></th><td><input id="dior-google-client-id" name="client_id" type="text" class="regular-text" autocomplete="off" value="' . esc_attr($settings['client_id'] ?? '') . '" required><p class="description">Paste the Client ID from Google Cloud Console.</p></td></tr>';
        echo '<tr><th scope="row"><label for="dior-google-client-secret">OAuth Client Secret</label></th><td><input id="dior-google-client-secret" name="client_secret" type="password" class="regular-text" autocomplete="new-password" value="" placeholder="' . (!empty($settings['client_secret']) ? 'Saved — leave blank to keep existing secret' : 'Paste Client Secret') . '" ' . (empty($settings['client_secret']) ? 'required' : '') . '><p class="description">The secret is stored server-side and never shown back in the form.</p></td></tr>';
        echo '<tr><th scope="row">Authorized redirect URI</th><td><code style="display:inline-block;max-width:100%;overflow-wrap:anywhere">' . esc_html(self::redirect_uri()) . '</code><p class="description">Copy this exact URI into your Google OAuth Client under Authorized redirect URIs.</p></td></tr>';
        echo '</tbody></table>';
        submit_button('Save OAuth Credentials');
        echo '</form><hr><h2>Google Account Connection</h2><p>Status: <strong>' . ($connected ? '<span style="color:#008a20">Connected</span>' : '<span style="color:#b32d2e">Not connected</span>') . '</strong></p>';
        if ($connected) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('dior_google_meet_disconnect');
            echo '<input type="hidden" name="action" value="dior_google_meet_disconnect">';
            submit_button('Disconnect Google Account', 'secondary', 'submit', false);
            echo '</form>';
        } else {
            echo '<p>Save credentials first, then connect the Google account that should create consultation meetings.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('dior_google_meet_connect');
            echo '<input type="hidden" name="action" value="dior_google_meet_connect">';
            submit_button('Connect Google Account', 'primary', 'submit', false);
            echo '</form>';
        }
        echo '</div></div>';
    }
}
}
