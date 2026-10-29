<?php
/**
 * Dior Medical - HIPAAtizer Medical Questionnaire Webhook Endpoint
 * 
 * Direct Webhook URL for HIPAAtizer:
 * https://bagheeraventures.com/diormedical/wp-content/plugins/dior-medical-dashboard/rest_api/hipaatizer_questionnaire_webhook.php
 * 
 * Method: POST
 * Accepts: JSON payload matching HIPAAtizer template variables
 * 
 * @package Dior Medical
 */

// Load WordPress core
$wp_root_path = dirname(dirname(dirname(dirname(__DIR__))));
if (file_exists($wp_root_path . '/wp-load.php')) {
    require_once($wp_root_path . '/wp-load.php');
} else {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'WordPress core not found']);
    exit;
}

// Ensure Dior_Questionnaire_Service is loaded
if (!class_exists('Dior_Questionnaire_Service')) {
    $service_file = dirname(__DIR__) . '/includes/class-dior-questionnaire-service.php';
    if (file_exists($service_file)) {
        require_once($service_file);
    }
}

// Set JSON headers. This is a server-to-server webhook; do not enable wildcard CORS.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

// Require a configured shared secret before accepting questionnaire/PHI data.
// Configure the secret in wp-config.php and use the same value in the HIPAAtizer webhook URL.
$configured_secret = defined('DIOR_HIPAATIZER_QUESTIONNAIRE_SECRET')
    ? trim((string) DIOR_HIPAATIZER_QUESTIONNAIRE_SECRET)
    : trim((string) get_option('dior_hipaatizer_questionnaire_secret', ''));
$provided_secret = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $provided_secret = trim((string) $_SERVER['HTTP_AUTHORIZATION']);
} elseif (function_exists('getallheaders')) {
    $request_headers = getallheaders();
    if (is_array($request_headers) && isset($request_headers['Authorization'])) {
        $provided_secret = trim((string) $request_headers['Authorization']);
    }
}
if (stripos($provided_secret, 'Bearer ') === 0) {
    $provided_secret = trim(substr($provided_secret, 7));
}
if ($configured_secret === '' || $provided_secret === '' || !hash_equals($configured_secret, $provided_secret)) {
    http_response_code(401);
    echo wp_json_encode(['success' => false, 'code' => 'invalid_webhook_token', 'message' => 'Invalid questionnaire webhook credentials.']);
    exit;
}

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method is allowed'
    ]);
    exit;
}

// Read raw request body
$raw_body = file_get_contents('php://input');
if (empty($raw_body)) {
    // Fallback to $_POST if sent as form-data
    $payload = !empty($_POST) ? $_POST : [];
} else {
    $payload = json_decode($raw_body, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON payload received'
        ]);
        exit;
    }
}

if (empty($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Empty request body received'
    ]);
    exit;
}

try {
    if (!class_exists('Dior_Questionnaire_Service')) {
        throw new Exception('Questionnaire service class not available.');
    }

    $result = Dior_Questionnaire_Service::process_hipaatizer_questionnaire($payload);

    http_response_code(200);
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('[Dior Medical Questionnaire Webhook Error] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error processing questionnaire submission.'
    ]);
}

exit;
