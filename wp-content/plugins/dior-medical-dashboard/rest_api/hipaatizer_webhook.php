<?php
// File: wp-content/plugins/dior-medical-dashboard/rest_api/hipaatizer_webhook.php

// Define ABSPATH to load WordPress core
$wp_root_path = dirname(dirname(dirname(dirname(__DIR__))));
if (file_exists($wp_root_path . '/wp-load.php')) {
    require_once($wp_root_path . '/wp-load.php');
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'WordPress core not found']);
    exit;
}

// Allow CORS for testing in Postman
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed']);
    exit;
}

// Read the raw POST data
$inputJSON = file_get_contents('php://input');

// --- DEBUG LOGGING ---
// ---------------------

$input = json_decode($inputJSON, true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

global $wpdb;

/**
 * Extract data from Hipaatizer payload
 */
$data = isset($input['data']) ? $input['data'] : $input;
$fields = isset($data['fields']) ? $data['fields'] : $data;

// Extract values mapping to the form JSON names
$is_pregnant = isset($fields['is_pregnant']) ? sanitize_text_field($fields['is_pregnant']) : '';
$consultation_type = isset($fields['consultation_type']) ? sanitize_text_field($fields['consultation_type']) : '';
$referral_source = isset($fields['referral_source']) ? sanitize_text_field($fields['referral_source']) : '';
$street_address = isset($fields['street_address']) ? sanitize_text_field($fields['street_address']) : '';
$city = isset($fields['city']) ? sanitize_text_field($fields['city']) : '';
$state = isset($fields['state']) ? sanitize_text_field($fields['state']) : '';
$zip_code = isset($fields['zip_code']) ? sanitize_text_field($fields['zip_code']) : '';
$emergency_contact_name = isset($fields['emergency_contact_name']) ? sanitize_text_field($fields['emergency_contact_name']) : '';
$emergency_contact_phone = isset($fields['emergency_contact_phone']) ? sanitize_text_field($fields['emergency_contact_phone']) : '';
$insurance_provider = isset($fields['insurance_provider']) ? sanitize_text_field($fields['insurance_provider']) : '';
$insurance_member_id = isset($fields['insurance_member_id']) ? sanitize_text_field($fields['insurance_member_id']) : '';

// Identifier to find the patient (Email)
$patient_email = isset($fields['email']) ? sanitize_email($fields['email']) : '';

if (empty($patient_email)) {
    // If no email in payload, check URL params (Postman test)
    $patient_email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
}

// Find patient in database
if (!empty($patient_email)) {
    $patient_table = $wpdb->prefix . 'dior_patients';
    $patient = $wpdb->get_row($wpdb->prepare("SELECT * FROM $patient_table WHERE email = %s", $patient_email));

    if ($patient) {
        // Update patient profile
        $update_data = [
            'address_line_1' => $street_address,
            'city' => $city,
            'state' => $state,
            'zip_code' => $zip_code,
            'emergency_contact_name' => $emergency_contact_name,
            'emergency_contact_phone' => $emergency_contact_phone,
            'is_pregnant' => ($is_pregnant === '1' || strtolower($is_pregnant) === 'yes') ? 1 : 0
        ];

        // Clean empty fields so we don't overwrite existing data with blanks
        foreach ($update_data as $key => $val) {
            if (empty($val) && $val !== 0 && $val !== '0') {
                unset($update_data[$key]);
            }
        }

        if (!empty($update_data)) {
            $wpdb->update(
                $patient_table,
                $update_data,
                ['id' => $patient->id]
            );
            
            // Also update wp_usermeta so that frontend dashboard is fully dynamic
            update_user_meta($patient->user_id, 'dior_hipaa_intake', $update_data);
            if (!empty($street_address)) update_user_meta($patient->user_id, 'address', $street_address);
            if (!empty($city)) update_user_meta($patient->user_id, 'city', $city);
            if (!empty($state)) update_user_meta($patient->user_id, 'state', $state);
            if (!empty($zip_code)) update_user_meta($patient->user_id, 'zip_code', $zip_code);
            if (!empty($emergency_contact_name)) update_user_meta($patient->user_id, 'emergency_name', $emergency_contact_name);
            if (!empty($emergency_contact_phone)) update_user_meta($patient->user_id, 'emergency_phone', $emergency_contact_phone);
            if ($is_pregnant === '1' || strtolower($is_pregnant) === 'yes') {
                update_user_meta($patient->user_id, 'pregnancy_status', 'Pregnant');
            }
        }
        
        // Also update Insurance Table if data exists
        if (!empty($insurance_provider) && !empty($insurance_member_id)) {
             $insurance_table = $wpdb->prefix . 'dior_insurance';
             
             // check if exists
             $has_insurance = $wpdb->get_var($wpdb->prepare("SELECT id FROM $insurance_table WHERE patient_id = %d", $patient->id));
             if ($has_insurance) {
                 $wpdb->update($insurance_table, [
                     'primary_insurance_provider' => $insurance_provider,
                     'primary_member_id' => $insurance_member_id,
                     'updated_at' => current_time('mysql')
                 ], ['patient_id' => $patient->id]);
             } else {
                 $wpdb->insert($insurance_table, [
                     'patient_id' => $patient->id,
                     'primary_insurance_provider' => $insurance_provider,
                     'primary_member_id' => $insurance_member_id,
                     'created_at' => current_time('mysql'),
                     'updated_at' => current_time('mysql')
                 ]);
             }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Patient profile and insurance updated successfully',
            'patient_id' => $patient->id,
            'received_data' => $update_data
        ]);
        exit;
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Patient not found with email: ' . $patient_email
        ]);
        exit;
    }
}

// Fallback if no email provided
echo json_encode([
    'success' => true,
    'message' => 'Data received, but no patient identifier (email) was found to update profile.',
    'payload' => $input
]);
exit;
