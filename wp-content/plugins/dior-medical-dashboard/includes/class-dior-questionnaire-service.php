<?php
/**
 * Dior Medical - Patient Medical Questionnaire Service
 * 
 * Handles intake and questionnaire submissions from HIPAAtizer webhooks.
 * Parses condition-specific question modules (UTI, Allergy, Asthma, Refill,
 * Birth Control, COVID-19, Rash), symptoms, medical history, medications,
 * drug allergies, social history, pharmacy, and electronic consent.
 * 
 * @package Dior Medical
 * @version 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dior_Questionnaire_Service
{
    /**
     * Helper to safely extract field values matching multiple possible keys.
     * Supports both human-readable form labels and internal variable keys.
     */
    public static function extract_value(array $data, array $candidate_keys, $default = '')
    {
        foreach ($candidate_keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== '' && $data[$key] !== null) {
                $val = $data[$key];
                if (is_array($val)) {
                    return implode(', ', array_map('sanitize_text_field', $val));
                }
                return sanitize_text_field((string)$val);
            }
        }
        return $default;
    }

    /**
     * Process incoming HIPAAtizer medical questionnaire payload
     *
     * @param array $payload
     * @return array
     */
    public static function process_hipaatizer_questionnaire(array $payload)
    {
        global $wpdb;

        if (empty($payload)) {
            return [
                'success' => false,
                'message' => 'Empty questionnaire payload received'
            ];
        }

        // Normalize nested payload (some webhooks wrap under 'data' or 'fields')
        $raw = $payload;
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
        $fields = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : $data;

        // Helper shorthand
        $get = function(array $keys, $default = '') use ($fields) {
            return self::extract_value($fields, $keys, $default);
        };

        // 1. Patient Identifiers
        $email = sanitize_email($get([
            'Email Address', 'email', 'Email', 'email_address', 'patient_email', 'submitter_email'
        ]));
        $full_name = $get([
            'Full Name', 'full_name', 'name', 'patient_name'
        ]);
        $dob = $get([
            'Date of Birth', 'date_of_birth', 'dob', 'birth_date'
        ]);
        $sex_at_birth = $get([
            'Sex Assigned at Birth', 'Sex Assigned at Birth (Labels)', 'Sex Assigned at Birth (Values)', 'sex_at_birth', 'gender', 'sex'
        ]);
        $phone_number = $get([
            'Mobile Phone Number', 'phone_number', 'phone', 'Mobile', 'mobile_phone'
        ]);
        $preferred_language = $get([
            'Preferred Language', 'preferred_language', 'language'
        ], 'English');
        $is_adult = $get([
            'Are you 18 years of age or older?', 'Are you 18 years of age or older? (Labels)', 'Are you 18 years of age or older? (Values)', 'is_adult', 'is_18_plus'
        ]);
        $is_pregnant = $get([
            'Are you currently pregnant?', 'Are you currently pregnant? (Labels)', 'Are you currently pregnant? (Values)', 'is_pregnant'
        ]);

        // 2. Visit Details & Symptoms
        $selected_service = $get([
            'Selected Service / Condition', 'Selected Service / Condition (Labels)', 'Selected Service / Condition (Values)', 'selected_service', 'service_condition'
        ]);
        $consultation_type = $get([
            'Preferred Consultation Type', 'Preferred Consultation Type (Labels)', 'Preferred Consultation Type (Values)', 'consultation_type'
        ]);
        $referral_source = $get([
            'How did you hear about us?', 'How did you hear about us? (Labels)', 'How did you hear about us? (Values)', 'referral_source'
        ]);
        $reason_for_visit = $get([
            'Main Reason for Visit', 'reason_for_visit', 'chief_complaint'
        ]);
        $symptom_description = $get([
            'Describe Your Symptoms', 'symptom_description', 'symptoms_description', 'symptoms'
        ]);
        $symptom_duration = $get([
            'How long have you had these symptoms?', 'How long have you had these symptoms? (Labels)', 'How long have you had these symptoms? (Values)', 'symptom_duration'
        ]);
        $symptom_severity = $get([
            'Symptom Severity', 'Symptom Severity (Labels)', 'Symptom Severity (Values)', 'symptom_severity'
        ]);
        $has_fever = $get([
            'Do you currently have a fever?', 'Do you currently have a fever? (Labels)', 'Do you currently have a fever? (Values)', 'has_fever'
        ]);
        $tried_treatment = $get([
            'Have you tried any treatment for these symptoms?', 'Have you tried any treatment for these symptoms? (Labels)', 'Have you tried any treatment for these symptoms? (Values)', 'tried_treatment'
        ]);
        $treatment_tried_details = $get([
            'Treatment Tried', 'treatment_tried_details'
        ]);
        $red_flags = $get([
            'Do you have trouble breathing, chest pain, or confusion?', 'Do you have trouble breathing, chest pain, or confusion? (Labels)', 'Do you have trouble breathing, chest pain, or confusion? (Values)', 'red_flags'
        ]);

        // 3. Condition Specific: UTI
        $uti_burning = $get(['Burning or pain when urinating?', 'Burning or pain when urinating? (Labels)', 'Burning or pain when urinating? (Values)', 'uti_burning', 'uti_burning_pain']);
        $uti_frequency = $get(['Frequent or urgent need to urinate?', 'Frequent or urgent need to urinate? (Labels)', 'Frequent or urgent need to urinate? (Values)', 'uti_frequency']);
        $uti_blood = $get(['Blood in your urine?', 'Blood in your urine? (Labels)', 'Blood in your urine? (Values)', 'uti_blood']);
        $uti_flank_pain = $get(['Pain in your side or lower back?', 'Pain in your side or lower back? (Labels)', 'Pain in your side or lower back? (Values)', 'uti_flank_pain']);
        $uti_history = $get(['Have you had a UTI before?', 'Have you had a UTI before? (Labels)', 'Have you had a UTI before? (Values)', 'uti_history', 'uti_previous']);
        $uti_discharge = $get(['Any vaginal discharge or irritation?', 'Any vaginal discharge or irritation? (Labels)', 'Any vaginal discharge or irritation? (Values)', 'uti_discharge']);

        // 4. Condition Specific: Allergy
        $allergy_triggers = $get(['Known Triggers', 'allergy_triggers']);
        $allergy_main_symptom = $get(['Main Symptom', 'Main Symptom (Labels)', 'Main Symptom (Values)', 'allergy_main_symptom', 'allergy_symptoms']);
        $allergy_anaphylaxis = $get(['Have you ever had a severe allergic reaction (anaphylaxis)?', 'Have you ever had a severe allergic reaction (anaphylaxis)? (Labels)', 'Have you ever had a severe allergic reaction (anaphylaxis)? (Values)', 'allergy_anaphylaxis']);
        $allergy_epipen = $get(['Do you carry an epinephrine auto-injector?', 'Do you carry an epinephrine auto-injector? (Labels)', 'Do you carry an epinephrine auto-injector? (Values)', 'allergy_epipen']);

        // 5. Condition Specific: Asthma
        $asthma_rescue_use = $get(['How often do you use a rescue inhaler?', 'How often do you use a rescue inhaler? (Labels)', 'How often do you use a rescue inhaler? (Values)', 'asthma_rescue_use']);
        $asthma_night = $get(['Do symptoms wake you at night?', 'Do symptoms wake you at night? (Labels)', 'Do symptoms wake you at night? (Values)', 'asthma_night']);
        $asthma_er_visit = $get(['ER or hospital visit for asthma in the past year?', 'ER or hospital visit for asthma in the past year? (Labels)', 'ER or hospital visit for asthma in the past year? (Values)', 'asthma_er_visit']);
        $asthma_wheeze = $get(['Do you currently have a wheeze or shortness of breath?', 'Do you currently have a wheeze or shortness of breath? (Labels)', 'Do you currently have a wheeze or shortness of breath? (Values)', 'asthma_wheeze']);

        // 6. Condition Specific: Prescription Refill
        $refill_med_name = $get(['Medication Name', 'refill_med_name']);
        $refill_med_dose = $get(['Dose and How Often', 'refill_med_dose']);
        $refill_doctor = $get(['Prescribing Doctor', 'refill_doctor']);
        $refill_last_date = $get(['Approximate Date of Last Refill', 'refill_last_date']);

        // 7. Condition Specific: Birth Control
        $bc_current_method = $get(['Current Birth Control Method', 'Current Birth Control Method (Labels)', 'Current Birth Control Method (Values)', 'bc_current_method']);
        $bc_lmp = $get(['First Day of Last Menstrual Period', 'bc_lmp']);
        $bc_smoker_35 = $get(['Do you smoke and are you over 35?', 'Do you smoke and are you over 35? (Labels)', 'Do you smoke and are you over 35? (Values)', 'bc_smoker_35']);
        $bc_clot_history = $get(['History of blood clots, stroke or migraines with aura?', 'History of blood clots, stroke or migraines with aura? (Labels)', 'History of blood clots, stroke or migraines with aura? (Values)', 'bc_clot_history']);

        // 8. Condition Specific: COVID
        $covid_contact = $get(['Close contact with a confirmed case in the past 10 days?', 'Close contact with a confirmed case in the past 10 days? (Labels)', 'Close contact with a confirmed case in the past 10 days? (Values)', 'covid_contact']);
        $covid_test_result = $get(['Recent Test Result', 'Recent Test Result (Labels)', 'Recent Test Result (Values)', 'covid_test_result']);
        $covid_taste_smell = $get(['Loss of taste or smell?', 'Loss of taste or smell? (Labels)', 'Loss of taste or smell? (Values)', 'covid_taste_smell']);
        $covid_vaccinated = $get(['Vaccinated or boosted in the past year?', 'Vaccinated or boosted in the past year? (Labels)', 'Vaccinated or boosted in the past year? (Values)', 'covid_vaccinated']);

        // 9. Condition Specific: Rash
        $rash_location = $get(['Where on your body is the rash?', 'rash_location']);
        $rash_itchy = $get(['Is it itchy?', 'Is it itchy? (Labels)', 'Is it itchy? (Values)', 'rash_itchy']);
        $rash_new_exposure = $get(['Any new soap, medicine, plant or food exposure?', 'Any new soap, medicine, plant or food exposure? (Labels)', 'Any new soap, medicine, plant or food exposure? (Values)', 'rash_new_exposure']);
        $rash_spreading = $get(['Is the rash spreading quickly or blistering?', 'Is the rash spreading quickly or blistering? (Labels)', 'Is the rash spreading quickly or blistering? (Values)', 'rash_spreading']);

        // 10. Medical History: Chronic Conditions
        $hx_hypertension = $get(['High blood pressure?', 'High blood pressure? (Labels)', 'High blood pressure? (Values)', 'hx_hypertension']);
        $hx_diabetes = $get(['Diabetes?', 'Diabetes? (Labels)', 'Diabetes? (Values)', 'hx_diabetes']);
        $hx_heart = $get(['Heart disease?', 'Heart disease? (Labels)', 'Heart disease? (Values)', 'hx_heart']);
        $hx_lung = $get(['Asthma or lung disease?', 'Asthma or lung disease? (Labels)', 'Asthma or lung disease? (Values)', 'hx_lung']);
        $hx_kidney_liver = $get(['Kidney or liver disease?', 'Kidney or liver disease? (Labels)', 'Kidney or liver disease? (Values)', 'hx_kidney_liver']);
        $hx_cancer_immune = $get(['Cancer or immune system disorder?', 'Cancer or immune system disorder? (Labels)', 'Cancer or immune system disorder? (Values)', 'hx_cancer_immune']);
        $hx_other = $get(['Other Medical Conditions or Past Surgeries', 'hx_other']);

        // 11. Medications & Allergies
        $takes_medications = $get(['Are you currently taking any medications, vitamins or supplements?', 'Are you currently taking any medications, vitamins or supplements? (Labels)', 'Are you currently taking any medications, vitamins or supplements? (Values)', 'takes_medications']);
        $medication_list = $get(['Please List Your Medications', 'medication_list', 'medications_list']);
        $has_drug_allergy = $get(['Do you have any known drug allergies?', 'Do you have any known drug allergies? (Labels)', 'Do you have any known drug allergies? (Values)', 'has_drug_allergy']);
        $drug_allergy_list = $get(['Please List Allergies and Reactions', 'drug_allergy_list', 'drug_allergies_list']);

        // 12. Social History
        $tobacco_use = $get(['Tobacco / Vaping Use', 'Tobacco / Vaping Use (Labels)', 'Tobacco / Vaping Use (Values)', 'tobacco_use']);
        $alcohol_use = $get(['Alcohol Use', 'Alcohol Use (Labels)', 'Alcohol Use (Values)', 'alcohol_use']);

        // 13. Preferred Pharmacy
        $pharmacy_name = $get(['Pharmacy Name', 'pharmacy_name']);
        $pharmacy_location = $get(['Pharmacy City / ZIP Code', 'pharmacy_location']);

        // 14. Consent, Signature & Submission Metadata
        $consent_all = $get(['Consent & Acknowledgements', 'Consent & Acknowledgements (Labels)', 'Consent & Acknowledgements (Values)', 'consent_all']);
        $e_signature = $get(['Type Your Full Legal Name', 'e_signature']);
        $signature_date = $get(['Date', 'signature_date']);
        // Prefer immutable metadata returned by HIPAAtizer over generated fallbacks.
        $submission_id = $get(['Submission Id', 'submissionId', 'submission_id', 'id']);
        if ($submission_id === '' && !empty($payload['id']) && is_scalar($payload['id'])) {
            $submission_id = sanitize_text_field((string) $payload['id']);
        }
        $form_id = $get(['Form Id', 'workflowId', 'form_id']);
        if ($form_id === '' && !empty($payload['workflowId']) && is_scalar($payload['workflowId'])) {
            $form_id = sanitize_text_field((string) $payload['workflowId']);
        }
        $hipaatizer_created_at = $payload['createdAt'] ?? ($data['createdAt'] ?? ($fields['createdAt'] ?? ''));
        $submitted_at = '';
        if (is_string($hipaatizer_created_at) && $hipaatizer_created_at !== '') {
            $timestamp = strtotime($hipaatizer_created_at);
            if ($timestamp !== false) {
                $submitted_at = function_exists('wp_date') ? wp_date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s', $timestamp);
            }
        }
        if ($submitted_at === '') {
            $submitted_at = current_time('mysql');
        }
        $ip_address = $get(['ipAddress', 'ip_address', 'ip']);
        if ($ip_address === '' && !empty($payload['ipAddress']) && is_scalar($payload['ipAddress'])) {
            $ip_address = sanitize_text_field((string) $payload['ipAddress']);
        }

        // Build structured questionnaire object
        $questionnaire = [
            'submission_id'         => $submission_id ?: ('SUB-' . time()),
            'form_id'               => $form_id ?: 'medical_questionnaire',
            'submitted_at'          => $submitted_at,
            'ip_address'            => $ip_address,
            'view_submission_link'  => $view_submission_link,
            'download_pdf_link'     => $download_pdf_link,
            'view_pdf_link'         => $download_pdf_link ?: $view_submission_link,

            // Patient
            'full_name'             => $full_name,
            'email'                 => $email,
            'dob'                   => $dob,
            'gender'                => $sex_at_birth,
            'phone'                 => $phone_number,
            'preferred_language'    => $preferred_language,
            'is_adult'              => $is_adult,
            'is_pregnant'           => $is_pregnant,

            // Visit
            'selected_service'      => $selected_service,
            'consultation_type'     => $consultation_type,
            'referral_source'       => $referral_source,
            'reason_for_visit'      => $reason_for_visit,
            'symptom_description'   => $symptom_description,
            'symptom_duration'      => $symptom_duration,
            'symptom_severity'      => $symptom_severity,
            'has_fever'             => $has_fever,
            'tried_treatment'       => $tried_treatment,
            'treatment_tried_details'=> $treatment_tried_details,
            'red_flags'             => $red_flags,

            // Condition Modules
            'uti' => [
                'burning'    => $uti_burning,
                'frequency'  => $uti_frequency,
                'blood'      => $uti_blood,
                'flank_pain' => $uti_flank_pain,
                'history'    => $uti_history,
                'discharge'  => $uti_discharge,
            ],
            'allergy' => [
                'triggers'     => $allergy_triggers,
                'main_symptom' => $allergy_main_symptom,
                'anaphylaxis'  => $allergy_anaphylaxis,
                'epipen'       => $allergy_epipen,
            ],
            'asthma' => [
                'rescue_use'     => $asthma_rescue_use,
                'night_symptoms' => $asthma_night,
                'er_visit'       => $asthma_er_visit,
                'wheeze'         => $asthma_wheeze,
            ],
            'refill' => [
                'med_name'  => $refill_med_name,
                'med_dose'  => $refill_med_dose,
                'doctor'    => $refill_doctor,
                'last_date' => $refill_last_date,
            ],
            'birth_control' => [
                'current_method'  => $bc_current_method,
                'lmp'             => $bc_lmp,
                'smoker_over_35'  => $bc_smoker_35,
                'clot_history'    => $bc_clot_history,
            ],
            'covid' => [
                'contact'          => $covid_contact,
                'test_result'      => $covid_test_result,
                'loss_taste_smell' => $covid_taste_smell,
                'vaccinated'       => $covid_vaccinated,
            ],
            'rash' => [
                'location'             => $rash_location,
                'itchy'                => $rash_itchy,
                'new_exposure'         => $rash_new_exposure,
                'spreading_blistering' => $rash_spreading,
            ],

            // Medical History
            'medical_history' => [
                'hypertension'    => $hx_hypertension,
                'diabetes'        => $hx_diabetes,
                'heart_disease'   => $hx_heart,
                'lung_disease'    => $hx_lung,
                'kidney_liver'    => $hx_kidney_liver,
                'cancer_immune'   => $hx_cancer_immune,
                'other'           => $hx_other,
            ],

            // Meds & Allergies
            'medications' => [
                'takes_medications' => $takes_medications,
                'list'              => $medication_list,
            ],
            'allergies' => [
                'has_drug_allergy'  => $has_drug_allergy,
                'list'              => $drug_allergy_list,
            ],

            // Social & Pharmacy
            'social_history' => [
                'tobacco' => $tobacco_use,
                'alcohol' => $alcohol_use,
            ],
            'pharmacy' => [
                'name'     => $pharmacy_name,
                'location' => $pharmacy_location,
            ],

            // Consent & Sign
            'consent'        => $consent_all,
            'signature'      => $e_signature,
            'signature_date' => $signature_date,

            // Raw fields preserved
            'raw' => $fields,
        ];

        // Match patient
        $user_id = 0;
        $patient_row = null;
        $patient_table = $wpdb->prefix . 'dior_patients';

        if (!empty($email)) {
            // 1. Match from wp_dior_patients table
            $patient_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$patient_table} WHERE email = %s LIMIT 1", $email));
            if ($patient_row && !empty($patient_row->user_id)) {
                $user_id = (int)$patient_row->user_id;
            }

            // 2. Match from wp_users if not found in table
            if (!$user_id) {
                $user = get_user_by('email', $email);
                if ($user) {
                    $user_id = $user->ID;
                }
            }
        }

        // Fallback match by Full Name if email search produced no user
        if (!$user_id && !empty($full_name)) {
            $name_parts = preg_split('/\s+/', trim($full_name), 2);
            $fn = $name_parts[0] ?? '';
            $ln = $name_parts[1] ?? '';
            if ($fn && $ln) {
                $matched_by_name = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$patient_table} WHERE first_name = %s AND last_name = %s LIMIT 1",
                    $fn, $ln
                ));
                if ($matched_by_name) {
                    $patient_row = $matched_by_name;
                    $user_id = (int)$matched_by_name->user_id;
                }
            }
        }

        if ($user_id) {
            // Save latest questionnaire to user meta
            update_user_meta($user_id, 'dior_medical_questionnaire', $questionnaire);

            // Upsert history by immutable HIPAAtizer submission ID to avoid duplicate rows on retries.
            $history = get_user_meta($user_id, 'dior_medical_questionnaires', true);
            if (!is_array($history)) { $history = []; }
            $history = array_values(array_filter($history, static function ($entry) use ($questionnaire) {
                if (!is_array($entry)) { return false; }
                $existing_id = (string) ($entry['submission_id'] ?? '');
                $incoming_id = (string) ($questionnaire['submission_id'] ?? '');
                return $incoming_id === '' || $existing_id !== $incoming_id;
            }));
            array_unshift($history, $questionnaire);
            // Cap history at 20 submissions.
            $history = array_slice($history, 0, 20);
            update_user_meta($user_id, 'dior_medical_questionnaires', $history);

            // Also synchronize with dior_hipaa_intake so all legacy views get updated medical data
            $existing_intake = get_user_meta($user_id, 'dior_hipaa_intake', true);
            if (!is_array($existing_intake)) {
                $existing_intake = [];
            }

            // Synthesize condition summaries for existing intake
            $conds = [];
            if (in_array(strtolower($hx_hypertension), ['yes', '1', 'true'], true)) $conds[] = 'Hypertension';
            if (in_array(strtolower($hx_diabetes), ['yes', '1', 'true'], true)) $conds[] = 'Diabetes';
            if (in_array(strtolower($hx_heart), ['yes', '1', 'true'], true)) $conds[] = 'Heart Disease';
            if (in_array(strtolower($hx_lung), ['yes', '1', 'true'], true)) $conds[] = 'Asthma/Lung Disease';
            if (in_array(strtolower($hx_kidney_liver), ['yes', '1', 'true'], true)) $conds[] = 'Kidney/Liver Disease';
            if (in_array(strtolower($hx_cancer_immune), ['yes', '1', 'true'], true)) $conds[] = 'Cancer/Immune Disorder';
            if (!empty($hx_other)) $conds[] = $hx_other;
            $conditions_str = implode(', ', $conds);

            $merged_intake = array_merge($existing_intake, [
                'service_condition'    => $selected_service ?: ($existing_intake['service_condition'] ?? ''),
                'consultation_type'    => $consultation_type ?: ($existing_intake['consultation_type'] ?? ''),
                'referral_source'      => $referral_source ?: ($existing_intake['referral_source'] ?? ''),
                'symptoms_description' => $symptom_description ?: ($existing_intake['symptoms_description'] ?? ''),
                'symptoms'             => ($reason_for_visit ? $reason_for_visit . ' — ' : '') . $symptom_description,
                'symptom_duration'     => $symptom_duration ?: ($existing_intake['symptom_duration'] ?? ''),
                'symptom_severity'     => $symptom_severity ?: ($existing_intake['symptom_severity'] ?? ''),
                'diagnosed_conditions' => $conditions_str ?: ($existing_intake['diagnosed_conditions'] ?? ''),
                'other_conditions'     => $hx_other ?: ($existing_intake['other_conditions'] ?? ''),
                'medical_history'      => $conditions_str ?: ($existing_intake['medical_history'] ?? ''),
                'taking_medications'   => $takes_medications ?: ($existing_intake['taking_medications'] ?? ''),
                'medications_list'     => $medication_list ?: ($existing_intake['medications_list'] ?? ''),
                'medications'          => $medication_list ?: ($existing_intake['medications'] ?? ''),
                'drug_allergies'       => $has_drug_allergy ?: ($existing_intake['drug_allergies'] ?? ''),
                'drug_allergies_list'  => $drug_allergy_list ?: ($existing_intake['drug_allergies_list'] ?? ''),
                'allergies'            => $drug_allergy_list ?: ($existing_intake['allergies'] ?? ''),
                'is_pregnant'          => $is_pregnant ?: ($existing_intake['is_pregnant'] ?? ''),
                'is_18_plus'           => $is_adult ?: ($existing_intake['is_18_plus'] ?? ''),
                'last_updated'         => current_time('mysql'),
            ]);
            update_user_meta($user_id, 'dior_hipaa_intake', $merged_intake);
            update_user_meta($user_id, 'dior_hipaa_intake_submitted', '1');

            // Update core user profile fields if previously empty
            if (!empty($phone_number)) {
                update_user_meta($user_id, 'phone', $phone_number);
                update_user_meta($user_id, 'billing_phone', $phone_number);
            }
            if (!empty($dob)) {
                update_user_meta($user_id, 'dob', $dob);
            }
            if (!empty($sex_at_birth)) {
                update_user_meta($user_id, 'gender', $sex_at_birth);
            }
            if (!empty($pharmacy_name)) {
                update_user_meta($user_id, 'preferred_pharmacy_name', $pharmacy_name);
            }
            if (!empty($pharmacy_location)) {
                update_user_meta($user_id, 'preferred_pharmacy_address', $pharmacy_location);
            }
            if (!empty($is_pregnant)) {
                $pregnant = in_array(strtolower($is_pregnant), ['1', 'yes', 'true', 'y'], true);
                update_user_meta($user_id, 'pregnancy_status', $pregnant ? 'Pregnant' : 'Not Pregnant');
            }
            if (!empty($is_adult)) {
                $adult = in_array(strtolower($is_adult), ['1', 'yes', 'true', 'y'], true);
                update_user_meta($user_id, 'is_18_verified', $adult ? 'Verified (18+)' : 'Under 18');
            }

            // Sync relational patient row in wp_dior_patients
            if (class_exists('Dior_Patient_Portal_Data')) {
                Dior_Patient_Portal_Data::sync_relational_patient($user_id);
            }

            // Add notification for patient
            $notifications = get_user_meta($user_id, 'dior_notifications', true);
            if (!is_array($notifications)) $notifications = [];
            array_unshift($notifications, [
                'id'         => 'NOTIF-Q-' . time(),
                'type'       => 'questionnaire',
                'icon'       => 'fa-clipboard-check',
                'title'      => 'Medical Questionnaire Received',
                'message'    => 'Your medical questions have been submitted and attached to your clinical chart for your provider to review.',
                'time'       => 'Just now',
                'timestamp'  => time(),
                'created_at' => current_time('mysql'),
                'is_read'    => false,
                'action_url' => '#tab=questionnaire',
            ]);
            update_user_meta($user_id, 'dior_notifications', array_slice($notifications, 0, 30));

            // Notify Doctors & Administrators
            $display_patient_name = $full_name;
            if (empty($display_patient_name)) {
                $u_info = get_userdata($user_id);
                $display_patient_name = $u_info ? $u_info->display_name : ('Patient #' . $user_id);
            }
            $doctors = get_users(['role__in' => ['doctor', 'administrator']]);
            foreach ($doctors as $doc) {
                $doc_notifs = get_user_meta($doc->ID, 'dior_doctor_notifications', true);
                if (!is_array($doc_notifs)) $doc_notifs = [];
                array_unshift($doc_notifs, [
                    'id'         => 'DN-Q-' . time() . '-' . wp_rand(10, 99),
                    'type'       => 'questionnaire',
                    'icon'       => 'fa-clipboard-question',
                    'title'      => 'Medical Questionnaire: ' . $display_patient_name,
                    'message'    => $display_patient_name . ' submitted their clinical questionnaire (' . ($reason_for_visit ?: ($selected_service ?: 'Urgent Care')) . ').',
                    'time'       => 'Just now',
                    'timestamp'  => time(),
                    'created_at' => current_time('mysql'),
                    'is_read'    => false,
                    'action_url' => '#tab=doc-patients-profile',
                ]);
                update_user_meta($doc->ID, 'dior_doctor_notifications', array_slice($doc_notifs, 0, 50));
            }

            return [
                'success'       => true,
                'message'       => 'Medical questionnaire synchronized successfully',
                'user_id'       => $user_id,
                'patient_id'    => $patient_row ? $patient_row->id : 0,
                'questionnaire' => $questionnaire
            ];
        } else {
            // Patient not yet registered - store pending by email so it links upon registration
            if ($email) {
                $pending_key = 'dior_q_pending_' . md5($email);
                update_option($pending_key, $questionnaire, false);
            }

            return [
                'success' => true,
                'message' => 'Medical questionnaire received and stored pending patient registration.',
                'email'   => $email,
                'questionnaire' => $questionnaire
            ];
        }
    }

    /**
     * Get patient medical questionnaire data
     *
     * @param int $user_id
     * @return array
     */
    public static function get_patient_questionnaire($user_id)
    {
        $q = get_user_meta($user_id, 'dior_medical_questionnaire', true);
        return is_array($q) ? $q : [];
    }
}
