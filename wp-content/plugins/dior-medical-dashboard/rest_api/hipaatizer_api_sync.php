<?php
/**
 * HIPAAtizer API pull-sync for Dior Medical.
 * API secrets are stored server-side and never returned to the admin UI.
 */
if (!defined('ABSPATH')) { exit; }

final class Dior_HIPAAtizer_API_Sync {
    const OPTION_KEY = 'dior_hipaatizer_api_settings';
    const LAST_RUN_KEY = 'dior_hipaatizer_api_last_run';
    const SYNCED_KEY = 'dior_hipaatizer_synced_submission_ids_v2';
    const LOCK_KEY = 'dior_hipaatizer_api_sync_lock';
    const BASE_URL = 'https://app.hipaatizer.com';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_dior_hipaatizer_save_settings', [__CLASS__, 'save_settings']);
        add_action('admin_post_dior_hipaatizer_sync_now', [__CLASS__, 'sync_now']);
        add_filter('cron_schedules', [__CLASS__, 'cron_schedules']);
        add_action('init', [__CLASS__, 'schedule_cron']);
        add_action('dior_hipaatizer_auto_sync', [__CLASS__, 'run_sync']);
        // Secure, patient-scoped status polling for the post-HIPAAtizer redirect.
        add_action('wp_ajax_dior_visit_sync_status', [__CLASS__, 'ajax_visit_sync_status']);
        add_action('template_redirect', [__CLASS__, 'mark_start_visit_pending'], 1);
        add_action('wp_footer', [__CLASS__, 'render_visit_sync_overlay'], 99);
    }

    public static function cron_schedules($schedules) {
        if (!isset($schedules['dior_15_minutes'])) {
            $schedules['dior_15_minutes'] = ['interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Every 15 minutes (Dior HIPAAtizer)'];
        }
        return $schedules;
    }

    public static function schedule_cron() {
        if (!wp_next_scheduled('dior_hipaatizer_auto_sync')) {
            wp_schedule_event(time() + 120, 'dior_15_minutes', 'dior_hipaatizer_auto_sync');
        }
    }

    public static function admin_menu() {
        add_management_page('HIPAAtizer API Sync', 'HIPAAtizer API Sync', 'manage_options', 'dior-hipaatizer-api-sync', [__CLASS__, 'render_page']);
    }

    private static function settings() {
        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) { $saved = []; }
        return [
            'api_key' => defined('DIOR_HIPAATIZER_API_KEY') ? (string) DIOR_HIPAATIZER_API_KEY : (string) ($saved['api_key'] ?? ''),
            'workflow_id' => (string) ($saved['workflow_id'] ?? ''),
        ];
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You are not allowed to access this page.', 'dior-medical')); }
        $settings = self::settings();
        $last = get_option(self::LAST_RUN_KEY, []);
        $notice = isset($_GET['dior_sync_notice']) ? sanitize_key(wp_unslash($_GET['dior_sync_notice'])) : '';
        echo '<div class="wrap"><h1>HIPAAtizer API Sync</h1>';
        if ($notice === 'saved') { echo '<div class="notice notice-success"><p>Settings saved.</p></div>'; }
        if ($notice === 'complete') { echo '<div class="notice notice-success"><p>Sync completed. Review the summary below.</p></div>'; }
        if ($notice === 'error') { echo '<div class="notice notice-error"><p>Sync could not complete. Check the safe error summary below, API key, Workflow ID, and HIPAAtizer IP whitelist.</p></div>'; }
        echo '<p>Configure the HIPAAtizer REST API and sync submissions to existing WordPress patient accounts. Only submissions matching an existing patient email are imported.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="dior_hipaatizer_save_settings">';
        wp_nonce_field('dior_hipaatizer_save_settings');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="dior_hipaa_api_key">HIPAAtizer API Key</label></th><td><input type="password" autocomplete="new-password" class="regular-text" id="dior_hipaa_api_key" name="api_key" value="" placeholder="' . esc_attr($settings['api_key'] ? 'Saved — leave blank to keep current key' : 'Paste API key') . '"><p class="description">Key is stored server-side and never displayed after saving. If DIOR_HIPAATIZER_API_KEY is defined in wp-config.php, that constant takes precedence.</p></td></tr>';
        echo '<tr><th><label for="dior_hipaa_workflow_id">Workflow ID</label></th><td><input type="text" class="regular-text" id="dior_hipaa_workflow_id" name="workflow_id" value="' . esc_attr($settings['workflow_id']) . '" required><p class="description">Copy this from HIPAAtizer → Form Settings → Integrations → REST API Helper.</p></td></tr>';
        echo '</tbody></table><p><button class="button button-primary">Save Settings</button></p></form>';
        echo '<hr><h2>Manual Sync</h2><p>Fetch submissions and import only records whose email matches an existing WordPress patient. This process does not display patient data.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="dior_hipaatizer_sync_now">';
        wp_nonce_field('dior_hipaatizer_sync_now');
        echo '<button class="button button-secondary">Sync Now</button></form>';
        if (is_array($last) && !empty($last['time'])) {
            echo '<h2>Last Sync Summary</h2><table class="widefat striped" style="max-width:650px"><tbody>';
            echo '<tr><th>Time</th><td>' . esc_html($last['time']) . '</td></tr>';
            echo '<tr><th>Status</th><td>' . esc_html($last['status'] ?? 'unknown') . '</td></tr>';
            echo '<tr><th>Found</th><td>' . absint($last['found'] ?? 0) . '</td></tr>';
            echo '<tr><th>Imported</th><td>' . absint($last['imported'] ?? 0) . '</td></tr>';
            echo '<tr><th>Skipped / Already Synced</th><td>' . absint($last['skipped'] ?? 0) . '</td></tr>';
            if (!empty($last['message'])) { echo '<tr><th>Message</th><td>' . esc_html($last['message']) . '</td></tr>'; }
            echo '</tbody></table>';
        }
        echo '<p><strong>Network note:</strong> HIPAAtizer REST API requires the WordPress server public IP to be whitelisted. A local Postman test succeeding does not automatically whitelist the hosting server.</p>';
        echo '</div>';
    }

    public static function save_settings() {
        if (!current_user_can('manage_options')) { wp_die('Not allowed.', 403); }
        check_admin_referer('dior_hipaatizer_save_settings');
        $current = get_option(self::OPTION_KEY, []);
        if (!is_array($current)) { $current = []; }
        $workflow = isset($_POST['workflow_id']) ? sanitize_text_field(wp_unslash($_POST['workflow_id'])) : '';
        if (!preg_match('/^[a-zA-Z0-9-]{20,80}$/', $workflow)) {
            wp_safe_redirect(add_query_arg('dior_sync_notice', 'error', admin_url('tools.php?page=dior-hipaatizer-api-sync'))); exit;
        }
        $new_key = isset($_POST['api_key']) ? trim((string) wp_unslash($_POST['api_key'])) : '';
        $api_key = $new_key !== '' ? sanitize_text_field($new_key) : (string) ($current['api_key'] ?? '');
        if (!defined('DIOR_HIPAATIZER_API_KEY')) { update_option(self::OPTION_KEY, ['api_key' => $api_key, 'workflow_id' => $workflow], false); }
        else { update_option(self::OPTION_KEY, ['workflow_id' => $workflow], false); }
        wp_safe_redirect(add_query_arg('dior_sync_notice', 'saved', admin_url('tools.php?page=dior-hipaatizer-api-sync'))); exit;
    }

    public static function sync_now() {
        if (!current_user_can('manage_options')) { wp_die('Not allowed.', 403); }
        check_admin_referer('dior_hipaatizer_sync_now');
        $result = self::run_sync();
        $notice = (!empty($result['success'])) ? 'complete' : 'error';
        wp_safe_redirect(add_query_arg('dior_sync_notice', $notice, admin_url('tools.php?page=dior-hipaatizer-api-sync'))); exit;
    }

    private static function api_request($path, $method = 'GET', $body = null) {
        $settings = self::settings();
        if ($settings['api_key'] === '' || $settings['workflow_id'] === '') {
            return new WP_Error('dior_hipaa_config_missing', 'API key or Workflow ID is not configured.');
        }
        $args = [
            'method' => $method,
            'timeout' => 25,
            'redirection' => 0,
            'headers' => ['X-Api-Key' => $settings['api_key'], 'Accept' => 'application/json'],
        ];
        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }
        $response = wp_safe_remote_request(self::BASE_URL . $path, $args);
        if (is_wp_error($response)) { return new WP_Error('dior_hipaa_network', 'Could not connect to HIPAAtizer API. Check hosting outbound access and DNS.'); }
        $code = (int) wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            $message = $code === 401 ? 'HIPAAtizer rejected the API key (401).' : ($code === 403 ? 'HIPAAtizer denied the request (403). Check the hosting server IP whitelist.' : 'HIPAAtizer API returned HTTP ' . $code . '.');
            return new WP_Error('dior_hipaa_api_error', $message, ['status' => $code]);
        }
        return $json;
    }

    /**
     * Full-page wait screen shown only when HIPAAtizer redirects with
     * ?dior_visit_sync=1. The dashboard remains behind the overlay.
     */
    public static function render_visit_sync_overlay() {
        if (!is_user_logged_in() || !self::is_patient_dashboard_request()) {
            return;
        }
        $query_flag = isset($_GET['dior_visit_sync']) && sanitize_text_field(wp_unslash($_GET['dior_visit_sync'])) === '1';
        $pending_cookie = isset($_COOKIE['dior_visit_sync_pending']) && sanitize_text_field(wp_unslash($_COOKIE['dior_visit_sync_pending'])) === '1';
        if (!$query_flag && !$pending_cookie) {
            return;
        }

        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('dior_visit_sync_status');
        ?>
        <div id="dior-visit-sync-overlay" role="status" aria-live="polite">
            <div class="dior-visit-sync-card">
                <div class="dior-visit-sync-spinner" aria-hidden="true"></div>
                <h2>Preparing Your Patient Dashboard</h2>
                <p id="dior-visit-sync-message">Your visit details are being securely retrieved. Please wait…</p>
                <button type="button" id="dior-visit-sync-retry" hidden>Try Again</button>
            </div>
        </div>
        <style>
            #dior-visit-sync-overlay{position:fixed;inset:0;z-index:999999;background:rgba(247,250,255,.97);display:flex;align-items:center;justify-content:center;padding:24px;font-family:inherit}
            #dior-visit-sync-overlay .dior-visit-sync-card{width:min(100%,460px);padding:34px 28px;background:#fff;border:1px solid #e2eaf5;border-radius:16px;box-shadow:0 18px 55px rgba(20,50,90,.12);text-align:center}
            #dior-visit-sync-overlay h2{font-size:22px;margin:18px 0 8px;color:#172b4d}
            #dior-visit-sync-overlay p{margin:0;color:#5b6b82;line-height:1.6;font-size:14px}
            #dior-visit-sync-overlay .dior-visit-sync-spinner{width:44px;height:44px;margin:0 auto;border:4px solid #dce8f8;border-top-color:#2869c7;border-radius:50%;animation:diorVisitSpin .8s linear infinite}
            #dior-visit-sync-retry{margin-top:18px;padding:10px 20px;border:0;border-radius:7px;background:#2869c7;color:#fff;font-weight:600;cursor:pointer}
            @keyframes diorVisitSpin{to{transform:rotate(360deg)}}
            @media(prefers-reduced-motion:reduce){#dior-visit-sync-overlay .dior-visit-sync-spinner{animation-duration:2s}}
        </style>
        <script>
        (function(){
            'use strict';
            var overlay=document.getElementById('dior-visit-sync-overlay');
            if(!overlay)return;
            var message=document.getElementById('dior-visit-sync-message');
            var retry=document.getElementById('dior-visit-sync-retry');
            var started=Date.now(), timer=null, busy=false;
            var ajaxUrl=<?php echo wp_json_encode($ajax_url); ?>;
            var nonce=<?php echo wp_json_encode($nonce); ?>;
            function finish(){
                if(timer)window.clearInterval(timer);
                try{
                    var url=new URL(window.location.href);
                    url.searchParams.delete('dior_visit_sync');
                    // Reload once without the flag so server-rendered Patient Settings
                    // and the profile-completion banner reflect the newly saved data.
                    window.location.replace(url.toString());
                }catch(e){
                    overlay.remove();
                }
            }
            function checkStatus(){
                if(busy)return;
                busy=true;
                var body=new URLSearchParams();
                body.set('action','dior_visit_sync_status');
                body.set('nonce',nonce);
                fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
                .then(function(response){return response.json();})
                .then(function(result){
                    if(result&&result.success&&result.data&&result.data.ready){finish();return;}
                    if(Date.now()-started>120000){
                        message.textContent='We could not confirm your visit details yet. Please try again, or contact support if this continues.';
                        retry.hidden=false;
                        var spinner=overlay.querySelector('.dior-visit-sync-spinner');
                        if(spinner)spinner.style.display='none';
                        if(timer)window.clearInterval(timer);
                    }
                })
                .catch(function(){
                    if(Date.now()-started>120000){
                        message.textContent='We could not connect to verify your visit details. Please try again.';
                        retry.hidden=false;
                        var spinner=overlay.querySelector('.dior-visit-sync-spinner');
                        if(spinner)spinner.style.display='none';
                        if(timer)window.clearInterval(timer);
                    }
                })
                .finally(function(){busy=false;});
            }
            retry.addEventListener('click',function(){
                started=Date.now();retry.hidden=true;message.textContent='Retrying secure visit verification…';
                var spinner=overlay.querySelector('.dior-visit-sync-spinner');
                if(spinner)spinner.style.display='block';
                checkStatus();if(timer)window.clearInterval(timer);timer=window.setInterval(checkStatus,3000);
            });
            checkStatus();
            timer=window.setInterval(checkStatus,3000);
        })();
        </script>
        <?php
    }

    /**
     * AJAX status check used after a patient returns from HIPAAtizer.
     * Only the currently authenticated patient's own email is queried.
     */
    /**
     * Mark the current browser as completing the Start Your Visit flow.
     * This allows the loading overlay to work even if HIPAAtizer redirects to
     * the plain dashboard URL without the optional query parameter.
     */
    public static function mark_start_visit_pending() {
        if (!is_user_logged_in() || is_admin()) {
            return;
        }
        $is_start_page = function_exists('is_page') && is_page('start-your-visit');
        if (!$is_start_page) {
            $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
            $is_start_page = (bool) preg_match('~/start-your-visit(?:/|$|\\?)~i', $path);
        }
        if (!$is_start_page) {
            return;
        }
        $cookie_path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
        setcookie('dior_visit_sync_pending', '1', [
            'expires' => time() + (30 * MINUTE_IN_SECONDS),
            'path' => $cookie_path,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['dior_visit_sync_pending'] = '1';
    }

    /** True when this request is the patient dashboard, including custom rewrites. */
    private static function is_patient_dashboard_request() {
        if (function_exists('is_page') && is_page('patient-dashboard')) {
            return true;
        }
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        return (bool) preg_match('~/patient-dashboard(?:/|$|\\?)~i', $path);
    }

    /** Clear the visit-flow cookie once the server confirms the profile data is ready. */
    private static function clear_visit_pending_cookie() {
        $cookie_path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
        setcookie('dior_visit_sync_pending', '', [
            'expires' => time() - HOUR_IN_SECONDS,
            'path' => $cookie_path,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['dior_visit_sync_pending']);
    }

    public static function ajax_visit_sync_status() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please sign in again.'], 401);
        }
        check_ajax_referer('dior_visit_sync_status', 'nonce');

        $user_id = get_current_user_id();

        // Throttle upstream API calls; browser can poll more frequently.
        // The first request after redirect always checks HIPAAtizer, even if an older intake exists.
        $cooldown_key = 'dior_visit_sync_try_' . $user_id;
        if (!get_transient($cooldown_key)) {
            set_transient($cooldown_key, 1, 10);
            self::sync_user_submission($user_id);
        }

        $intake = get_user_meta($user_id, 'dior_hipaa_intake', true);
        if (is_array($intake) && !empty(get_user_meta($user_id, 'dior_hipaa_intake_submitted', true))) {
            self::clear_visit_pending_cookie();
            wp_send_json_success(['ready' => true]);
        }

        // Do not expose upstream errors or patient details to the browser.
        wp_send_json_success(['ready' => false, 'message' => 'We are securely retrieving your visit details.']);
    }

    /**
     * Fetch and import only the latest matching HIPAAtizer submission for one user.
     * Returns generic status only; never logs or returns the medical payload.
     */
    public static function sync_user_submission($user_id) {
        $user = get_userdata((int) $user_id);
        if (!$user || !is_email($user->user_email)) {
            return ['success' => false, 'message' => 'Patient account unavailable.'];
        }

        $body = [
            'searchSubmissionRequest' => [
                'searchBy' => 'Email Address',
                'searchFor' => $user->user_email,
                'searchByTag' => '',
                'additionalColumns' => [],
                'dates' => ['from' => '', 'to' => ''],
                'includeNotCompleted' => true,
                'sorting' => ['direction' => 'Desc', 'column' => 'CreatedAt', 'additionalColumn' => ''],
                'pagination' => ['page' => 1, 'limit' => 10],
            ],
        ];

        $search = self::api_request(
            '/api/v1/api_key/submissions/search?workflowId=' . rawurlencode(self::settings()['workflow_id']),
            'POST',
            $body
        );
        if (is_wp_error($search)) {
            return ['success' => false, 'message' => 'Submission lookup is temporarily unavailable.'];
        }

        $items = isset($search['data']) && is_array($search['data']) ? $search['data'] : [];
        foreach ($items as $item) {
            $id = sanitize_text_field((string) ($item['id'] ?? $item['submissionId'] ?? $item['submission_id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $detail = self::api_request('/api/v1/api_key/submissions/' . rawurlencode($id), 'GET');
            if (is_wp_error($detail)) {
                continue;
            }
            $data = $detail['data'] ?? [];
            if (!is_array($data)) {
                continue;
            }
            $fields = (isset($data['fields']) && is_array($data['fields'])) ? $data['fields'] : $data;
            $email_value = $fields['email'] ?? $fields['Email'] ?? $fields['email_address'] ?? $fields['patient_email'] ?? $fields['submitter_email'] ?? $fields['Email Address'] ?? '';
            $email = sanitize_email(is_scalar($email_value) ? (string) $email_value : '');

            // Never import a submission unless its email matches this signed-in account.
            if (!$email || strtolower($email) !== strtolower($user->user_email)) {
                continue;
            }

            $payload = ['data' => $fields];
            $payload['data']['user_id'] = (int) $user->ID;
            $payload['data']['email'] = $email;

            if (function_exists('dior_fallback_save_hipaa_intake')) {
                dior_fallback_save_hipaa_intake($payload);
            } elseif (class_exists('Dior_Patient_Portal_Data') && method_exists('Dior_Patient_Portal_Data', 'save_hipaa_intake')) {
                Dior_Patient_Portal_Data::save_hipaa_intake($payload);
            } else {
                return ['success' => false, 'message' => 'Patient importer unavailable.'];
            }

            // Record the ID for normal scheduled sync deduplication after the importer succeeds.
            $seen = get_option(self::SYNCED_KEY, []);
            if (!is_array($seen)) { $seen = []; }
            $seen[] = $id;
            update_option(self::SYNCED_KEY, array_slice(array_values(array_unique($seen)), -1000), false);

            $saved = get_user_meta($user->ID, 'dior_hipaa_intake', true);
            if (is_array($saved) && !empty(get_user_meta($user->ID, 'dior_hipaa_intake_submitted', true))) {
                return ['success' => true];
            }
        }

        return ['success' => false, 'message' => 'No matching completed submission found yet.'];
    }

    public static function run_sync() {
        if (get_transient(self::LOCK_KEY)) { return ['success' => false, 'message' => 'Another sync is already running.']; }
        set_transient(self::LOCK_KEY, 1, 4 * MINUTE_IN_SECONDS);
        $summary = ['success' => false, 'found' => 0, 'imported' => 0, 'skipped' => 0, 'status' => 'error', 'message' => ''];
        try {
            $settings = self::settings();
            if ($settings['api_key'] === '' || $settings['workflow_id'] === '') {
                $summary['message'] = 'Save API key and Workflow ID first.';
                return self::finish($summary);
            }
            $seen = get_option(self::SYNCED_KEY, []);
            if (!is_array($seen)) { $seen = []; }
            $seen_lookup = array_fill_keys(array_map('strval', $seen), true);
            $page = 1;
            $max_pages = 10; // Up to 100 submissions per run.
            $new_seen = $seen;
            for ($page = 1; $page <= $max_pages; $page++) {
                $body = [
                    'searchSubmissionRequest' => [
                        'searchBy' => 'Email Address',
                        'searchFor' => '',
                        'searchByTag' => '',
                        'additionalColumns' => [],
                        'dates' => ['from' => '', 'to' => ''],
                        'includeNotCompleted' => true,
                        'sorting' => ['direction' => 'Desc', 'column' => 'CreatedAt', 'additionalColumn' => ''],
                        'pagination' => ['page' => $page, 'limit' => 10],
                    ],
                ];
                $url = '/api/v1/api_key/submissions/search?workflowId=' . rawurlencode($settings['workflow_id']);
                $search = self::api_request($url, 'POST', $body);
                if (is_wp_error($search)) { $summary['message'] = $search->get_error_message(); return self::finish($summary); }
                $items = isset($search['data']) && is_array($search['data']) ? $search['data'] : [];
                if ($page === 1) { $summary['found'] = absint($search['pagination']['total'] ?? count($items)); }
                if (!$items) { break; }

                // Some API responses may repeat the same page even when pagination is requested.
                // Stop when a page contains no new submission IDs to avoid inflated skip counts.
                $new_ids_on_page = 0;
                foreach ($items as $item) {
                    $id = sanitize_text_field((string) ($item['id'] ?? $item['submissionId'] ?? $item['submission_id'] ?? ''));
                    if ($id === '') { continue; }
                    if (isset($seen_lookup[$id])) {
                        continue;
                    }
                    $new_ids_on_page++;

                    $detail = self::api_request('/api/v1/api_key/submissions/' . rawurlencode($id), 'GET');
                    if (is_wp_error($detail)) { $summary['message'] = $detail->get_error_message(); return self::finish($summary); }
                    $data = $detail['data'] ?? [];
                    if (!is_array($data)) { $summary['skipped']++; $seen_lookup[$id] = true; continue; }

                    // HIPAAtizer versions may place answer fields directly under data or under data.fields.
                    $fields = (isset($data['fields']) && is_array($data['fields'])) ? $data['fields'] : $data;
                    $email_value = $fields['email'] ?? $fields['Email'] ?? $fields['email_address'] ?? $fields['patient_email'] ?? $fields['submitter_email'] ?? $fields['Email Address'] ?? '';
                    $email = sanitize_email(is_scalar($email_value) ? (string) $email_value : '');
                    if (!$email) {
                        $summary['skipped']++;
                        $seen_lookup[$id] = true;
                        $new_seen[] = $id;
                        continue;
                    }
                    $user = get_user_by('email', $email);
                    if (!$user) {
                        // Also support installations where patient accounts are represented in the normalized patient table.
                        global $wpdb;
                        $patient_table = $wpdb->prefix . 'dior_patients';
                        $matched_patient = $wpdb->get_row($wpdb->prepare(
                            "SELECT user_id FROM {$patient_table} WHERE LOWER(email) = LOWER(%s) LIMIT 1",
                            $email
                        ));
                        if ($matched_patient && !empty($matched_patient->user_id)) {
                            $user = get_user_by('id', (int) $matched_patient->user_id);
                        }
                    }
                    if (!$user) {
                        $summary['skipped']++;
                        $seen_lookup[$id] = true;
                        $new_seen[] = $id;
                        continue;
                    }

                    // Flatten the fields for the importer and explicitly bind the matched patient account.
                    $detail_payload = ['data' => $fields];
                    $detail_payload['data']['user_id'] = (int) $user->ID;
                    $detail_payload['data']['email'] = $email;

                    if (function_exists('dior_fallback_save_hipaa_intake')) {
                        dior_fallback_save_hipaa_intake($detail_payload);
                    } elseif (class_exists('Dior_Patient_Portal_Data') && method_exists('Dior_Patient_Portal_Data', 'save_hipaa_intake')) {
                        Dior_Patient_Portal_Data::save_hipaa_intake($detail_payload);
                    } else {
                        $summary['message'] = 'Dior intake importer is unavailable.';
                        return self::finish($summary);
                    }
                    // Confirm that the importer actually persisted intake data for this patient.
                    // Do not mark the submission as synced if the save function returned early.
                    $saved_intake = get_user_meta((int) $user->ID, 'dior_hipaa_intake', true);
                    $saved_email = is_array($saved_intake) ? sanitize_email((string) ($saved_intake['email'] ?? '')) : '';
                    if (is_array($saved_intake) && $saved_email !== '' && strtolower($saved_email) === strtolower($email)) {
                        $new_seen[] = $id;
                        $seen_lookup[$id] = true;
                        $summary['imported']++;
                    } else {
                        $summary['skipped']++;
                        // Leave this submission unmarked so a later run can retry it.
                    }
                }

                if ($new_ids_on_page === 0) { break; }
                $total = absint($search['pagination']['total'] ?? 0);
                $limit = absint($search['pagination']['limit'] ?? 10);
                if (($total && $page * max(1, $limit) >= $total) || count($items) < 10) { break; }
            }
            // Bound storage while retaining enough recent IDs to prevent repeated imports.
            update_option(self::SYNCED_KEY, array_slice(array_values(array_unique($new_seen)), -1000), false);
            $summary['success'] = true;
            $summary['status'] = 'success';
            if ($summary['message'] === '') { $summary['message'] = 'Sync finished without exposing patient details.'; }
            return self::finish($summary);
        } finally {
            delete_transient(self::LOCK_KEY);
        }
    }

    private static function finish($summary) {
        $summary['time'] = current_time('mysql');
        if (empty($summary['status'])) { $summary['status'] = !empty($summary['success']) ? 'success' : 'error'; }
        update_option(self::LAST_RUN_KEY, $summary, false);
        return $summary;
    }
}
Dior_HIPAAtizer_API_Sync::init();
