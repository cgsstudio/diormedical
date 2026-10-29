<?php
/**
 * Dior Medical - Direct HIPAAtizer Webhook Receiver
 *
 * Purpose:
 * - Receives HIPAAtizer POST requests without going through /wp-json/ REST routing.
 * - Avoids server/WAF rules that may return 403 for WordPress REST requests.
 * - Uses the same validated HIPAAtizer importer as the plugin REST endpoint.
 *
 * URL:
 * https://YOUR-DOMAIN/diormedical/wp-content/plugins/dior-medical-dashboard/rest_api/hipaatizer_direct_webhook.php?token=YOUR_SECRET
 */

// Load WordPress so all plugin/database/user functions are available.
$wp_root_path = dirname(dirname(dirname(dirname(__DIR__))));
$wp_load = $wp_root_path . '/wp-load.php';

if (!file_exists($wp_load)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode([
        'success' => false,
        'code'    => 'wordpress_not_found',
        'message' => 'WordPress core could not be loaded.',
    ]);
    exit;
}

require_once $wp_load;

header('Content-Type: application/json; charset=utf-8');

// Handle CORS preflight for browser/Postman testing.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    echo wp_json_encode([
        'success' => false,
        'code'    => 'method_not_allowed',
        'message' => 'Only POST requests are accepted.',
    ]);
    exit;
}

// The plugin is normally loaded by WordPress and this file therefore already
// has access to dior_fallback_save_hipaa_intake(). Require the REST bridge only
// as a safe fallback for unusual WordPress/plugin loading situations.
if (!function_exists('dior_fallback_save_hipaa_intake')) {
    $bridge_file = __DIR__ . '/hipaatizer_rest_api.php';
    if (file_exists($bridge_file)) {
        require_once $bridge_file;
    }
}

// Require an explicitly configured secret. Never fall back to a source-controlled token.
// Add define('DIOR_HIPAATIZER_WEBHOOK_SECRET', 'a-long-random-secret'); to wp-config.php,
// or store the same secret in the dior_hipaatizer_webhook_secret option.
$configured_secret = defined('DIOR_HIPAATIZER_WEBHOOK_SECRET')
    ? trim((string) DIOR_HIPAATIZER_WEBHOOK_SECRET)
    : trim((string) get_option('dior_hipaatizer_webhook_secret', ''));

$received_token = isset($_GET['token']) ? (string) wp_unslash($_GET['token']) : '';

// Fail closed when no secret is configured; compare only against the configured secret.
$token_matches = ($configured_secret !== '' && $received_token !== '' && hash_equals($configured_secret, $received_token));

if (!$token_matches) {
    http_response_code(401);
    echo wp_json_encode([
        'success' => false,
        'code'    => 'invalid_webhook_token',
        'message' => 'Invalid HIPAAtizer webhook token.',
    ]);
    exit;
}

// Read and decode the JSON body sent by HIPAAtizer.
$raw_body = file_get_contents('php://input');
$payload  = json_decode($raw_body, true);

if (!is_array($payload) || empty($payload)) {
    http_response_code(400);
    echo wp_json_encode([
        'success' => false,
        'code'    => 'invalid_json',
        'message' => 'The webhook body is empty or is not valid JSON.',
    ]);
    exit;
}

try {
    if (!function_exists('dior_fallback_save_hipaa_intake')) {
        throw new RuntimeException('Dior Medical HIPAAtizer importer is not available.');
    }

    // Use the same importer that already passed the Postman test. It supports
    // both internal HIPAAtizer keys and exact inserted-variable labels.
    dior_fallback_save_hipaa_intake($payload);

    // Dual-environment sync: Mirror payload between Staging (bagheeraventures) and Live (diormedical)
    $is_already_forwarded = !empty($_SERVER['HTTP_X_DIOR_FORWARDED']);
    if (!$is_already_forwarded && function_exists('wp_remote_post')) {
        $current_host = strtolower($_SERVER['HTTP_HOST'] ?? '');
        $mirror_target = '';

        // Forward only when the destination host and a separate destination secret are configured.
        // This avoids embedding credentials in source control or URLs.
        $live_secret = defined('DIOR_HIPAATIZER_LIVE_WEBHOOK_SECRET')
            ? trim((string) DIOR_HIPAATIZER_LIVE_WEBHOOK_SECRET)
            : '';
        $staging_secret = defined('DIOR_HIPAATIZER_STAGING_WEBHOOK_SECRET')
            ? trim((string) DIOR_HIPAATIZER_STAGING_WEBHOOK_SECRET)
            : '';

        if (strpos($current_host, 'bagheeraventures.com') !== false && $live_secret !== '') {
            // Received on Staging -> Mirror to Live.
            $mirror_target = 'https://diormedical.com/wp-content/plugins/dior-medical-dashboard/rest_api/hipaatizer_direct_webhook.php';
            $forward_secret = $live_secret;
        } elseif (strpos($current_host, 'diormedical.com') !== false && $staging_secret !== '') {
            // Received on Live -> Mirror to Staging.
            $mirror_target = 'https://bagheeraventures.com/diormedical/wp-content/plugins/dior-medical-dashboard/rest_api/hipaatizer_direct_webhook.php';
            $forward_secret = $staging_secret;
        }

        if (!empty($mirror_target) && !empty($forward_secret)) {
            wp_remote_post(add_query_arg('token', rawurlencode($forward_secret), $mirror_target), [
                'method'    => 'POST',
                'timeout'   => 4,
                'blocking'  => false, // Non-blocking asynchronous forward
                'headers'   => [
                    'Content-Type'     => 'application/json; charset=utf-8',
                    'X-Dior-Forwarded' => '1',
                ],
                'body'      => $raw_body,
                'sslverify' => true,
            ]);
        }
    }

    http_response_code(200);
    echo wp_json_encode([
        'success' => true,
        'message' => 'HIPAAtizer data received and synchronized.',
    ]);
} catch (Throwable $e) {
    // Do not expose stack traces or sensitive PHI in the HTTP response.
    error_log('[Dior HIPAAtizer Direct Webhook] ' . $e->getMessage());

    http_response_code(500);
    echo wp_json_encode([
        'success' => false,
        'code'    => 'webhook_processing_error',
        'message' => 'HIPAAtizer data could not be synchronized.',
    ]);
}

exit;
