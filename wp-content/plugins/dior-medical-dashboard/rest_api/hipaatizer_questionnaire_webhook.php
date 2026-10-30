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

// Set JSON headers and allow CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
        'message' => 'Error processing questionnaire: ' . $e->getMessage()
    ]);
}

exit;
