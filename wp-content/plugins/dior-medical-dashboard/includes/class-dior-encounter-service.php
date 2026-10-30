<?php
/**
 * Dior Medical - Clinical SOAP Encounter & Attestation Service
 * 
 * Manages clinical encounter notes using the standardized SOAP framework
 * (Subjective, Objective, Assessment, Plan), structured vitals, ICD-10
 * diagnostics, digital attestation hashing, and note finalization.
 * 
 * @package Dior Medical
 * @version 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dior_Encounter_Service
{
    /**
     * Create or update a clinical SOAP encounter note
     */
    public static function save_encounter(array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_encounters';

        $patient_id = Dior_Auth_Service::validate_id($data['patient_id'] ?? 0);
        if (!$patient_id) {
            return new WP_Error('invalid_patient', 'Valid Patient ID is required.');
        }

        $current_user_id = Dior_Auth_Service::get_current_user_id();
        if (!Dior_Auth_Service::can_modify_encounter($current_user_id)) {
            Dior_Audit_Service::log('soap_create', 'encounter', 'unauthorized', $patient_id, 'denied');
            return new WP_Error('unauthorized', 'Only authorized healthcare providers can record clinical encounters.');
        }

        $doctor_id = $current_user_id;
        $doctor_user = get_userdata($doctor_id);
        $doc_fn = get_user_meta($doctor_id, 'first_name', true) ?: ($doctor_user ? $doctor_user->first_name : '');
        $doc_ln = get_user_meta($doctor_id, 'last_name', true) ?: ($doctor_user ? $doctor_user->last_name : '');
        $doc_name = trim($doc_fn . ' ' . $doc_ln) ? 'Dr. ' . trim($doc_fn . ' ' . $doc_ln) : ($doctor_user ? $doctor_user->display_name : 'Physician');

        $encounter_uid = !empty($data['encounter_uid']) ? sanitize_text_field($data['encounter_uid']) : ('SOAP-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6)));
        $encounter_type = sanitize_text_field($data['encounter_type'] ?? 'Telehealth Consultation');
        $status = Dior_Auth_Service::validate_enum($data['status'] ?? 'finalized', ['draft', 'finalized', 'amended', 'archived'], 'finalized');
        
        $subjective = wp_kses_post($data['subjective'] ?? '');
        $objective  = wp_kses_post($data['objective'] ?? '');
        $assessment = wp_kses_post($data['assessment'] ?? '');
        $plan       = wp_kses_post($data['plan'] ?? '');
        
        $diagnosis  = sanitize_text_field($data['diagnosis'] ?? '');
        $icd10      = sanitize_text_field($data['icd10_code'] ?? '');
        $follow_up  = sanitize_text_field($data['follow_up'] ?? 'As needed / 7-10 days');
        
        $bp         = sanitize_text_field($data['vitals_bp'] ?? '');
        $hr         = sanitize_text_field($data['vitals_hr'] ?? '');
        $temp       = sanitize_text_field($data['vitals_temp'] ?? '');
        $weight     = sanitize_text_field($data['vitals_weight'] ?? '');
        $appt_id    = sanitize_text_field($data['appointment_id'] ?? '');

        $now = current_time('mysql');
        $salt = defined('NONCE_SALT') ? NONCE_SALT : 'dior_medical_secret';
        $attestation_hash = hash('sha256', $encounter_uid . '|' . $patient_id . '|' . $doctor_id . '|' . $now . '|' . $salt);

        // Check if existing record exists
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE encounter_uid = %s AND is_deleted = 0", $encounter_uid));

        if ($existing) {
            // If already finalized, do not allow silent modification without status change to amended
            if ($existing->status === 'finalized' && $status === 'finalized') {
                $status = 'amended';
            }

            $updated = $wpdb->update($table, [
                'encounter_type'        => $encounter_type,
                'status'                => $status,
                'subjective'            => $subjective,
                'objective'             => $objective,
                'assessment'            => $assessment,
                'plan'                  => $plan,
                'diagnosis'             => $diagnosis,
                'icd10_code'            => $icd10,
                'follow_up'             => $follow_up,
                'vitals_bp'             => $bp,
                'vitals_hr'             => $hr,
                'vitals_temp'           => $temp,
                'vitals_weight'         => $weight,
                'attestation_hash'      => $attestation_hash,
                'attestation_author'    => $doc_name,
                'attestation_timestamp' => $now,
                'updated_at'            => $now
            ], ['id' => $existing->id]);

            Dior_Audit_Service::log('soap_update', 'encounter', $encounter_uid, $patient_id, 'success', [
                'doctor_id' => $doctor_id,
                'status'    => $status
            ]);

            $record_id = $existing->id;
        } else {
            $inserted = $wpdb->insert($table, [
                'encounter_uid'        => $encounter_uid,
                'patient_id'           => $patient_id,
                'doctor_id'            => $doctor_id,
                'appointment_id'       => $appt_id,
                'encounter_type'       => $encounter_type,
                'status'               => $status,
                'subjective'           => $subjective,
                'objective'            => $objective,
                'assessment'           => $assessment,
                'plan'                 => $plan,
                'diagnosis'            => $diagnosis,
                'icd10_code'           => $icd10,
                'follow_up'            => $follow_up,
                'vitals_bp'            => $bp,
                'vitals_hr'            => $hr,
                'vitals_temp'          => $temp,
                'vitals_weight'        => $weight,
                'attestation_hash'     => $attestation_hash,
                'attestation_author'   => $doc_name,
                'attestation_timestamp'=> $now,
                'created_at'           => $now,
                'updated_at'           => $now,
                'is_deleted'           => 0
            ]);

            if (!$inserted) {
                return new WP_Error('db_error', 'Failed to save clinical encounter note.');
            }

            Dior_Audit_Service::log('soap_create', 'encounter', $encounter_uid, $patient_id, 'success', [
                'doctor_id' => $doctor_id,
                'type'      => $encounter_type
            ]);

            $record_id = $wpdb->insert_id;
        }

        // Maintain backward compatibility with legacy meta
        self::sync_legacy_usermeta($patient_id);

        return self::get_encounter_by_uid($encounter_uid);
    }

    /**
     * Get encounters for a patient
     */
    public static function get_patient_encounters($patient_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_encounters';
        $patient_id = (int)$patient_id;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE patient_id = %d AND is_deleted = 0 ORDER BY created_at DESC",
            $patient_id
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Get single encounter by UID
     */
    public static function get_encounter_by_uid($encounter_uid)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_encounters';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE encounter_uid = %s AND is_deleted = 0",
            $encounter_uid
        ), ARRAY_A);
    }

    /**
     * Soft delete an encounter
     */
    public static function delete_encounter($encounter_uid, $patient_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_encounters';

        $current_user_id = Dior_Auth_Service::get_current_user_id();
        if (!Dior_Auth_Service::can_modify_encounter($current_user_id)) {
            Dior_Audit_Service::log('soap_delete', 'encounter', $encounter_uid, $patient_id, 'denied');
            return new WP_Error('unauthorized', 'Unauthorized to delete clinical record.');
        }

        $updated = $wpdb->update(
            $table,
            ['is_deleted' => 1, 'updated_at' => current_time('mysql')],
            ['encounter_uid' => $encounter_uid, 'patient_id' => (int)$patient_id]
        );

        if ($updated) {
            Dior_Audit_Service::log('soap_delete', 'encounter', $encounter_uid, $patient_id, 'success');
            self::sync_legacy_usermeta($patient_id);
            return true;
        }

        return false;
    }

    /**
     * Dual-write / sync to legacy user meta for backwards compatibility
     */
    private static function sync_legacy_usermeta($patient_id)
    {
        $encounters = self::get_patient_encounters($patient_id);
        $legacy = [];
        foreach ($encounters as $e) {
            $legacy[] = [
                'id'             => $e['encounter_uid'],
                'date'           => date('M j, Y g:i A', strtotime($e['created_at'])),
                'timestamp'      => $e['created_at'],
                'doctor_id'      => $e['doctor_id'],
                'doctor'         => $e['doctor_id'],
                'doctor_name'    => $e['attestation_author'],
                'encounter_type' => $e['encounter_type'],
                'subjective'     => $e['subjective'],
                'objective'      => $e['objective'],
                'assessment'     => $e['assessment'],
                'plan'           => $e['plan'],
                'diagnosis'      => $e['diagnosis'],
                'note'           => $e['subjective'],
                'follow_up'      => $e['follow_up'],
                'vitals'         => [
                    'bp'     => $e['vitals_bp'],
                    'hr'     => $e['vitals_hr'],
                    'temp'   => $e['vitals_temp'],
                    'weight' => $e['vitals_weight'],
                ]
            ];
        }
        update_user_meta($patient_id, 'dior_doctor_notes', $legacy);
    }
}
