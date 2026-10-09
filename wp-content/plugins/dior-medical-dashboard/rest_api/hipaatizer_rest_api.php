<?php
/**
 * HIPAAtizer REST bridge for Dior Medical Dashboard.
 *
 * HIPAAtizer POST endpoint:
 *   /wp-json/dior/v1/hipaatizer
 *
 * The endpoint delegates to the same central save_hipaa_intake() method used
 * by the existing /hipaa-webhook endpoint, so there is only one data-mapping
 * path and the Patient Settings page stays synchronized.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {

    // HIPAAtizer -> Dior webhook receiver.
    register_rest_route('dior/v1', '/hipaatizer', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'dior_handle_hipaatizer_data',
        'permission_callback' => '__return_true',
    ]);

    // Optional authenticated dashboard API for reading a patient.
    register_rest_route('dior/v1', '/patient/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'dior_get_patient_data',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});

/**
 * Standalone fallback importer.
 *
 * This intentionally mirrors the canonical importer so the webhook still works
 * when an older cached/loaded Dior_Patient_Portal_Data class does not expose
 * save_hipaa_intake().
 */
if (!function_exists('dior_fallback_save_hipaa_intake')) {
    function dior_fallback_save_hipaa_intake($data)
    {
        if (empty($data))
            return;

        // --- DEBUG LOGGING ---
        $log_file = DIOR_PORTAL_PATH . 'webhook_debug.log';
        file_put_contents($log_file, "[" . current_time('mysql') . "] Webhook Received:\n" . print_r($data, true) . "\n", FILE_APPEND);
        // ---------------------

        // Unpack HIPAAtizer payload structure
        $raw_data = $data;
        if (isset($data['data'])) {
            $data = $data['data'];
        }
        if (isset($data['fields'])) {
            $data = $data['fields'];
        }
        $data['raw_data'] = $raw_data;

        file_put_contents($log_file, "[" . current_time('mysql') . "] Unpacked Data:\n" . print_r($data, true) . "\n", FILE_APPEND);

        // Try to find user by user_id first
        $user_id = 0;
        if (!empty($data['user_id'])) {
            $user_id = (int) $data['user_id'];
        } elseif (!empty($_POST['user_id'])) {
            $user_id = (int) $_POST['user_id'];
        } elseif (is_user_logged_in()) {
            $user_id = get_current_user_id();
        }

        // Try to find user by email from form data
        $email = '';
        $email_keys = ['email', 'Email', 'email_address', 'patient_email', 'submitter_email', 'Email Address'];
        foreach ($email_keys as $k) {
            if (!empty($data[$k])) {
                $email = sanitize_email($data[$k]);
                break;
            }
        }

        file_put_contents($log_file, "[" . current_time('mysql') . "] Extracted Email: " . $email . " | User ID: " . $user_id . "\n", FILE_APPEND);

        if (!$user_id && $email) {
            $user = get_user_by('email', $email);
            if ($user) {
                $user_id = $user->ID;
                file_put_contents($log_file, "[" . current_time('mysql') . "] Found WP User by Email. User ID: " . $user_id . "\n", FILE_APPEND);
            } else {
                // Fallback: match the normalized Dior patient table.
                global $wpdb;
                $patient_table = $wpdb->prefix . 'dior_patients';
                $matched_patient = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT user_id FROM {$patient_table} WHERE email = %s LIMIT 1",
                        $email
                    )
                );
                if ($matched_patient && !empty($matched_patient->user_id)) {
                    $user_id = (int) $matched_patient->user_id;
                    file_put_contents($log_file, "[" . current_time('mysql') . "] Found Dior patient by Email. User ID: " . $user_id . "\n", FILE_APPEND);
                } else {
                    file_put_contents($log_file, "[" . current_time('mysql') . "] User/patient not found for email: " . $email . "\n", FILE_APPEND);
                }
            }
        }

        if ($user_id && empty($email)) {
            $u_obj = get_userdata($user_id);
            if ($u_obj)
                $email = $u_obj->user_email;
        }

        // HIPAAtizer supports both internal variable keys (e.g. email) and
        // the exact inserted-variable labels configured in the webhook body
        // (e.g. Email Address, Full Name, Street Address). Read both forms.
        $dior_value = static function (array $source, array $keys, $default = '') {
            foreach ($keys as $key) {
                if (array_key_exists($key, $source) && $source[$key] !== '' && $source[$key] !== null) {
                    $value = $source[$key];
                    if (is_array($value)) {
                        return implode(', ', array_map('sanitize_text_field', $value));
                    }
                    return (string) $value;
                }
            }
            return $default;
        };

        // Exact HIPAAtizer inserted-variable names supplied by the form.
        $email = sanitize_email($dior_value($data, [
            'email', 'Email', 'email_address', 'patient_email', 'submitter_email', 'Email Address'
        ], $email));

        $full_name = sanitize_text_field($dior_value($data, [
            'full_name', 'Full Name'
        ]));

        $first_name = sanitize_text_field($dior_value($data, [
            'first_name', 'First Name', 'firstname'
        ]));
        $last_name = sanitize_text_field($dior_value($data, [
            'last_name', 'Last Name', 'lastname'
        ]));

        // If HIPAAtizer only sends Full Name, split it into WP first/last name.
        if ($full_name && !$first_name && !$last_name) {
            $name_parts = preg_split('/\s+/', trim($full_name), -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($name_parts)) {
                $first_name = array_shift($name_parts);
                $last_name = implode(' ', $name_parts);
            }
        }

        if ($user_id && empty($first_name)) {
            $first_name = get_user_meta($user_id, 'first_name', true) ?: '';
            $last_name = get_user_meta($user_id, 'last_name', true) ?: '';
        }

        // Build clean intake record with all 39 fields
        $intake = [
            'submitted_at' => current_time('Y-m-d H:i:s'),

            // Step 1: Service Selection
            'service_condition' => sanitize_text_field($dior_value($data, ['service_condition', 'service', 'selected_service', 'Selected Service / Condition', 'Which service or condition do you need care for today'])),
            'consultation_type' => sanitize_text_field($dior_value($data, ['consultation_type', 'Preferred Consultation Type', 'Preferred Consultation Type (Labels)', 'Preferred Consultation Type (Values)'])),
            'referral_source' => sanitize_text_field($dior_value($data, ['referral_source', 'How did you hear about us?', 'How did you hear about us? (Labels)', 'How did you hear about us? (Values)'])),

            // Step 2: Eligibility Checks
            'is_18_plus' => sanitize_text_field($data['is_18_plus'] ?? $data['Are you 18 years or older'] ?? ''),
            'is_pregnant' => sanitize_text_field($dior_value($data, ['is_pregnant', 'Are you currently pregnant?', 'Are you currently pregnant'])),

            // Step 3: Patient Information
            'first_name' => $first_name,
            'last_name' => $last_name,
            'dob' => sanitize_text_field($data['dob'] ?? $data['date_of_birth'] ?? $data['Date of Birth'] ?? ''),
            'gender' => sanitize_text_field($data['gender'] ?? $data['Gender'] ?? $data['biological_sex'] ?? $data['Biological Sex'] ?? $data['Sex'] ?? $data['sex'] ?? ''),
            'email' => $email,
            'phone' => sanitize_text_field($dior_value($data, ['phone', 'Phone', 'phone_number', 'Phone Number'])),
            'address' => sanitize_text_field($dior_value($data, ['address', 'Address', 'street_address', 'Street Address'])),
            'city' => sanitize_text_field($dior_value($data, ['city', 'City'])),
            'state' => sanitize_text_field($dior_value($data, ['state', 'State', 'service_state', 'State (Labels)', 'State (Values)'])),
            'zip' => sanitize_text_field($dior_value($data, ['zip', 'Zip', 'postal_code', 'ZIP Code', 'ZIP / PIN Code'])),
            'emergency_name' => sanitize_text_field($dior_value($data, ['emergency_name', 'emergency_contact_name', 'Emergency Contact Name'])),
            'emergency_relation' => sanitize_text_field($data['emergency_relation'] ?? $data['emergency_contact_relationship'] ?? $data['Emergency Contact Relationship'] ?? ''),
            'emergency_phone' => sanitize_text_field($dior_value($data, ['emergency_phone', 'emergency_contact_phone', 'Emergency Contact Phone'])),
            'insurance_provider' => sanitize_text_field($dior_value($data, ['insurance_provider', 'Insurance Provider Name'])),
            'insurance_member_id' => sanitize_text_field($dior_value($data, ['insurance_member_id', 'Insurance Member / Policy ID'])),

            // Step 4: Symptoms Description
            'symptoms_description' => sanitize_textarea_field($data['symptoms_description'] ?? $data['Describe your symptoms'] ?? ''),
            'symptom_duration' => sanitize_text_field($data['symptom_duration'] ?? $data['How long have you had these symptoms'] ?? ''),
            'symptom_severity' => sanitize_text_field($data['symptom_severity'] ?? $data['How severe are your symptoms'] ?? ''),

            // Step 5: Conditional Questions - UTI
            'uti_burning_pain' => sanitize_text_field($data['uti_burning_pain'] ?? $data['Do you have burning or pain when urinating'] ?? ''),
            'uti_frequency' => sanitize_text_field($data['uti_frequency'] ?? $data['Do you need to urinate more frequently or urgently'] ?? ''),
            'uti_appearance' => sanitize_text_field($data['uti_appearance'] ?? $data['Is your urine cloudy, dark or foul smelling'] ?? ''),
            'uti_blood' => sanitize_text_field($data['uti_blood'] ?? $data['Have you seen blood in your urine'] ?? ''),
            'uti_previous' => sanitize_text_field($data['uti_previous'] ?? $data['Have you had a urinary tract infection before'] ?? ''),
            'uti_last_date' => sanitize_text_field($data['uti_last_date'] ?? $data['When was your last urinary tract infection'] ?? ''),

            // Step 5: Conditional Questions - Allergies
            'allergy_symptoms' => is_array($data['allergy_symptoms'] ?? $data['Which allergy symptoms do you have']) ?
                implode(', ', array_map('sanitize_text_field', (array) ($data['allergy_symptoms'] ?? $data['Which allergy symptoms do you have']))) :
                sanitize_text_field($data['allergy_symptoms'] ?? $data['Which allergy symptoms do you have'] ?? ''),
            'allergy_seasonal' => sanitize_text_field($data['allergy_seasonal'] ?? $data['Are your allergy symptoms seasonal or year round'] ?? ''),
            'allergy_triggers' => sanitize_text_field($data['allergy_triggers'] ?? $data['What triggers your allergy symptoms'] ?? ''),
            'allergy_medication_taken' => sanitize_text_field($data['allergy_medication_taken'] ?? $data['Have you taken any allergy medication for this'] ?? ''),
            'allergy_medication_name' => sanitize_text_field($data['allergy_medication_name'] ?? $data['Which allergy medication did you take'] ?? ''),

            // Step 6: Medical History
            'diagnosed_conditions' => is_array($data['diagnosed_conditions'] ?? $data['Have you been diagnosed with any of the following']) ?
                implode(', ', array_map('sanitize_text_field', (array) ($data['diagnosed_conditions'] ?? $data['Have you been diagnosed with any of the following']))) :
                sanitize_text_field($data['diagnosed_conditions'] ?? $data['Have you been diagnosed with any of the following'] ?? ''),
            'other_conditions' => sanitize_textarea_field($data['other_conditions'] ?? $data['Other medical conditions'] ?? ''),
            'taking_medications' => sanitize_text_field($data['taking_medications'] ?? $data['Are you currently taking any medications'] ?? ''),
            'medications_list' => sanitize_textarea_field($data['medications_list'] ?? $data['List your current medications and dosage'] ?? ''),
            'drug_allergies' => sanitize_text_field($data['drug_allergies'] ?? $data['Do you have any drug allergies'] ?? ''),
            'drug_allergies_list' => sanitize_textarea_field($data['drug_allergies_list'] ?? $data['List your drug allergies and the reaction'] ?? ''),

            // Step 7: Documents & Appointment
            'photo_id' => sanitize_text_field($data['photo_id'] ?? $data['Photo identification'] ?? ''),
            'insurance_card' => sanitize_text_field($data['insurance_card'] ?? $data['Insurance card'] ?? ''),
            'affected_area_photo' => sanitize_text_field($data['affected_area_photo'] ?? $data['Photo of affected area'] ?? ''),
            'lab_results' => sanitize_text_field($data['lab_results'] ?? $data['Lab or test results'] ?? ''),
            'appointment_type' => sanitize_text_field($data['appointment_type'] ?? $data['Which type of appointment do you prefer'] ?? ''),
            'preferred_date' => sanitize_text_field($data['preferred_date'] ?? $data['Preferred Date'] ?? ''),
            'preferred_time' => sanitize_text_field($data['preferred_time'] ?? $data['Preferred Time'] ?? ''),

            // Step 8: Consent & Signature
            'telemedicine_consent' => sanitize_text_field($data['telemedicine_consent'] ?? $data['I consent to receive care through telemedicine'] ?? ''),
            'legal_acknowledgement' => sanitize_text_field($data['legal_acknowledgement'] ?? $data['I acknowledge and confirm the above'] ?? ''),
            'full_legal_name' => sanitize_text_field($data['full_legal_name'] ?? $data['Full Legal Name'] ?? ''),
            'signature' => sanitize_text_field($data['signature'] ?? $data['Signature'] ?? ''),
            'signature_date' => sanitize_text_field($data['signature_date'] ?? $data['Date'] ?? ''),

            // Legacy field names for compatibility
            'symptoms' => sanitize_textarea_field($data['symptoms_description'] ?? $data['symptoms'] ?? $data['Symptoms'] ?? $data['chief_complaint'] ?? $data['condition'] ?? ''),
            'allergies' => sanitize_text_field($data['allergy_symptoms'] ?? $data['allergies'] ?? $data['Allergies'] ?? ''),
            'medications' => sanitize_text_field($data['medications_list'] ?? $data['medications'] ?? $data['current_medications'] ?? $data['Medications'] ?? ''),
            'medical_history' => sanitize_textarea_field($data['other_conditions'] ?? $data['medical_history'] ?? $data['Medical History'] ?? ''),

            'raw_data' => $data, // keep full payload
        ];

        if ($user_id) {
            // Save to matched user
            update_user_meta($user_id, 'dior_hipaa_intake', $intake);
            update_user_meta($user_id, 'dior_hipaa_intake_submitted', '1');

            // Update user profile fields from HIPAAtizer data
            if (!empty($first_name)) {
                update_user_meta($user_id, 'first_name', $first_name);
            }
            if (!empty($last_name)) {
                update_user_meta($user_id, 'last_name', $last_name);
            }
            if (!empty($intake['phone'])) {
                update_user_meta($user_id, 'phone', $intake['phone']);
            }
            if (!empty($intake['gender'])) {
                update_user_meta($user_id, 'gender', $intake['gender']);
            }
            if (!empty($intake['dob'])) {
                update_user_meta($user_id, 'dob', $intake['dob']);
            }
            if (!empty($intake['address'])) {
                update_user_meta($user_id, 'address', $intake['address']);
                update_user_meta($user_id, 'billing_address_1', $intake['address']);
            }
            if (!empty($intake['city'])) {
                update_user_meta($user_id, 'city', $intake['city']);
                update_user_meta($user_id, 'billing_city', $intake['city']);
            }
            if (!empty($intake['state'])) {
                update_user_meta($user_id, 'state', $intake['state']);
            }
            if (!empty($intake['zip'])) {
                update_user_meta($user_id, 'zip', $intake['zip']);
                update_user_meta($user_id, 'zip_code', $intake['zip']);
            }
            if (!empty($intake['emergency_name'])) {
                update_user_meta($user_id, 'emergency_name', $intake['emergency_name']);
            }
            if (!empty($intake['emergency_relation'])) {
                update_user_meta($user_id, 'emergency_relation', $intake['emergency_relation']);
            }
            if (!empty($intake['emergency_phone'])) {
                update_user_meta($user_id, 'emergency_phone', $intake['emergency_phone']);
            }
            if ($intake['is_pregnant'] !== '') {
                $pregnant = in_array(strtolower((string) $intake['is_pregnant']), ['1', 'yes', 'true', 'y'], true);
                update_user_meta($user_id, 'pregnancy_status', $pregnant ? 'Pregnant' : 'Not Pregnant');
            }

            // Keep the complete mailing address for the dashboard while retaining
            // the original street address in its canonical field.
            if (!empty($intake['address']) || !empty($intake['city']) || !empty($intake['state']) || !empty($intake['zip'])) {
                $full_address = trim(
                    ($intake['address'] ?? '') . ', ' .
                    ($intake['city'] ?? '') . ', ' .
                    ($intake['state'] ?? '') . ' ' .
                    ($intake['zip'] ?? '')
                );
                update_user_meta($user_id, 'address', trim($full_address, " ,"));
            }

            // IMPORTANT: sync HIPAAtizer data into the normalized patient table
            // used by Doctor Dashboard and Patient Settings.
            if (class_exists('Dior_Patient_Portal_Data')) {
                Dior_Patient_Portal_Data::sync_relational_patient($user_id);
            }

            // Sync insurance when HIPAAtizer sends provider/member information.
            $insurance_provider = sanitize_text_field(
                $data['insurance_provider'] ?? $data['Insurance Provider'] ?? $data['Insurance Provider Name'] ?? $intake['insurance_provider'] ?? ''
            );
            $insurance_member_id = sanitize_text_field(
                $data['insurance_member_id'] ?? $data['insurance_member_number'] ?? $data['Insurance Member ID'] ?? $data['Insurance Member / Policy ID'] ?? $intake['insurance_member_id'] ?? ''
            );
            if ($insurance_provider !== '' || $insurance_member_id !== '') {
                global $wpdb;
                $insurance_table = $wpdb->prefix . 'dior_insurance';
                $patient_row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}dior_patients WHERE user_id = %d LIMIT 1",
                        $user_id
                    )
                );
                if ($patient_row) {
                    $existing_insurance = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM {$insurance_table} WHERE patient_id = %d LIMIT 1",
                            $patient_row->id
                        )
                    );
                    $insurance_data = [
                        'primary_insurance_provider' => $insurance_provider,
                        'primary_member_id' => $insurance_member_id,
                        'updated_at' => current_time('mysql'),
                    ];
                    if ($existing_insurance) {
                        $wpdb->update($insurance_table, $insurance_data, ['patient_id' => $patient_row->id]);
                    } else {
                        $insurance_data['patient_id'] = $patient_row->id;
                        $insurance_data['created_at'] = current_time('mysql');
                        $wpdb->insert($insurance_table, $insurance_data);
                    }
                }
            }

            // Create initial appointment from HIPAAtizer intake
            $appointments = get_user_meta($user_id, 'dior_appointments', true);
            if (!is_array($appointments)) {
                $appointments = [];
            }

            // Check if HIPAAtizer appointment already exists
            $has_hipaa_appt = false;
            foreach ($appointments as $appt) {
                if (isset($appt['from_hipaa']) && $appt['from_hipaa'] === true) {
                    $has_hipaa_appt = true;
                    break;
                }
            }

            // Only create appointment if no HIPAAtizer appointment exists
            if (!$has_hipaa_appt) {
                $new_appt = [
                    'id' => 'APT-HIPAA-' . time(),
                    'date' => current_time('Y-m-d'),
                    'time' => 'Pending',
                    'condition' => $intake['symptoms'] ?: 'Medical Consultation',
                    'provider' => 'Available Provider',
                    'provider_spec' => 'Board Certified Physician',
                    'type' => 'Video Visit (HD)',
                    'status' => 'Scheduled',
                    'join_url' => '',
                    'payment_status' => 'Pending',
                    'notes' => 'Appointment created from HIPAAtizer intake form.',
                    'can_join' => false,
                    'from_hipaa' => true
                ];
                $appointments[] = $new_appt;
                update_user_meta($user_id, 'dior_appointments', $appointments);
            }

            // Add a notification for Patient
            $notifications = get_user_meta($user_id, 'dior_notifications', true);
            if (!is_array($notifications))
                $notifications = [];
            array_unshift($notifications, [
                'id' => 'NOTIF-HIPAA-' . time(),
                'type' => 'intake',
                'icon' => 'fa-file-shield',
                'title' => 'Medical Intake Form Submitted',
                'message' => 'Your HIPAA medical intake form has been received and shared with your physician.',
                'time' => 'Just now',
                'timestamp' => time(),
                'created_at' => current_time('mysql'),
                'is_read' => false,
                'action_url' => '#tab=questionnaire',
            ]);
            update_user_meta($user_id, 'dior_notifications', $notifications);

            // Add notification to Doctors / Admin
            $patient_name = trim($first_name . ' ' . $last_name);
            if (empty($patient_name)) {
                $u_info = get_userdata($user_id);
                $patient_name = $u_info ? $u_info->display_name : 'Patient #' . $user_id;
            }
            $doctors = get_users(['role__in' => ['doctor', 'administrator']]);
            foreach ($doctors as $doc) {
                $doc_notifs = get_user_meta($doc->ID, 'dior_doctor_notifications', true);
                if (!is_array($doc_notifs))
                    $doc_notifs = [];
                array_unshift($doc_notifs, [
                    'id' => 'DN-INTAKE-' . time() . '-' . rand(10, 99),
                    'type' => 'intake',
                    'icon' => 'fa-file-shield',
                    'title' => 'New Medical Intake: ' . $patient_name,
                    'message' => $patient_name . ' submitted their HIPAA medical intake questionnaire.',
                    'time' => 'Just now',
                    'timestamp' => time(),
                    'created_at' => current_time('mysql'),
                    'is_read' => false,
                    'action_url' => '#tab=doc-intake',
                ]);
                update_user_meta($doc->ID, 'dior_doctor_notifications', $doc_notifs);
            }
        } elseif ($email) {
            // Store by email as key for later matching (when user logs in)
            $pending_key = 'dior_hipaa_pending_' . md5($email);
            update_option($pending_key, $intake, false);
        }
    }
}

/**
 * Receive a HIPAAtizer webhook and pass it through the canonical importer.
 */
function dior_handle_hipaatizer_data(WP_REST_Request $request) {

    // Validate the shared webhook token configured in HIPAAtizer.
    // Define DIOR_HIPAATIZER_WEBHOOK_SECRET in wp-config.php for production.
    $configured_secret = defined('DIOR_HIPAATIZER_WEBHOOK_SECRET')
        ? (string) DIOR_HIPAATIZER_WEBHOOK_SECRET
        : (string) get_option('dior_hipaatizer_webhook_secret', '');
    $received_token = (string) $request->get_param('token');

    if ($configured_secret !== '') {
        if ($received_token === '' || !hash_equals($configured_secret, $received_token)) {
            return new WP_Error(
                'invalid_webhook_token',
                'Invalid HIPAAtizer webhook token.',
                ['status' => 401]
            );
        }
    }

    $input = $request->get_json_params();

    if (empty($input) || !is_array($input)) {
        $input = $request->get_body_params();
    }

    if (empty($input) || !is_array($input)) {
        return new WP_Error(
            'invalid_json',
            'Invalid or empty HIPAAtizer payload.',
            ['status' => 400]
        );
    }

    // Prefer the canonical class method when the currently loaded plugin
    // version exposes it. If an older cached class is loaded, use the standalone
    // fallback importer instead of throwing a fatal error.
    if (class_exists('Dior_Patient_Portal_Data') && method_exists('Dior_Patient_Portal_Data', 'save_hipaa_intake')) {
        Dior_Patient_Portal_Data::save_hipaa_intake($input);
    } elseif (function_exists('dior_fallback_save_hipaa_intake')) {
        dior_fallback_save_hipaa_intake($input);
    } else {
        return new WP_Error(
            'dior_importer_missing',
            'Dior Medical HIPAAtizer importer is not available.',
            ['status' => 500]
        );
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => 'HIPAAtizer data received and synchronized.',
    ], 200);
}

/**
 * Read a patient's normalized DB record + dashboard profile.
 */
function dior_get_patient_data(WP_REST_Request $request) {

    $patient_id = absint($request->get_param('id'));

    if (!$patient_id) {
        return new WP_Error(
            'invalid_patient_id',
            'Invalid patient ID.',
            ['status' => 400]
        );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'dior_patients';

    $patient = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            $patient_id
        ),
        ARRAY_A
    );

    if (!$patient) {
        return new WP_Error(
            'patient_not_found',
            'Patient not found.',
            ['status' => 404]
        );
    }

    $user_id = (int) ($patient['user_id'] ?? 0);
    $profile = $user_id && class_exists('Dior_Patient_Portal_Data')
        ? Dior_Patient_Portal_Data::get_patient_profile($user_id)
        : [];

    $intake = $user_id
        ? get_user_meta($user_id, 'dior_hipaa_intake', true)
        : [];

    return new WP_REST_Response([
        'success' => true,
        'patient' => $patient,
        'profile'  => is_array($profile) ? $profile : [],
        'intake'   => is_array($intake) ? $intake : [],
    ], 200);
}
