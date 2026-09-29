<?php
/**
 * Dior Medical - Database Schema & Data Migration Layer
 * 
 * Manages dedicated relational tables for clinical encounters, documents,
 * appointments, and audit logs. Provides seamless backward-compatible
 * migration from legacy wp_usermeta serialized storage.
 * 
 * @package Dior Medical
 * @version 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dior_DB
{
    const DB_VERSION = '3.0.0';
    const DB_VERSION_OPTION = 'dior_medical_db_version';

    /**
     * Initialize DB layer
     */
    public static function init()
    {
        add_action('plugins_loaded', [__CLASS__, 'check_and_update_schema']);
    }

    /**
     * Check if schema needs installation or update
     */
    public static function check_and_update_schema()
    {
        $current_version = get_option(self::DB_VERSION_OPTION, '0.0.0');
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::install_schema();
            self::migrate_legacy_data();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        }
    }

    /**
     * Create or update relational database tables via dbDelta()
     */
    public static function install_schema()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // 1. Clinical Encounters / SOAP Notes Table
        $table_encounters = $wpdb->prefix . 'dior_encounters';
        $sql_encounters = "CREATE TABLE $table_encounters (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            encounter_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL,
            appointment_id VARCHAR(64) NULL,
            encounter_type VARCHAR(100) NOT NULL DEFAULT 'Telehealth Consultation',
            status ENUM('draft', 'finalized', 'amended', 'archived') NOT NULL DEFAULT 'finalized',
            subjective LONGTEXT NULL,
            objective LONGTEXT NULL,
            assessment LONGTEXT NULL,
            plan LONGTEXT NULL,
            diagnosis VARCHAR(255) NULL,
            icd10_code VARCHAR(50) NULL,
            follow_up VARCHAR(255) NULL,
            vitals_bp VARCHAR(30) NULL,
            vitals_hr VARCHAR(30) NULL,
            vitals_temp VARCHAR(30) NULL,
            vitals_weight VARCHAR(30) NULL,
            attestation_hash CHAR(64) NOT NULL,
            attestation_author VARCHAR(150) NOT NULL,
            attestation_timestamp DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uk_encounter_uid (encounter_uid),
            KEY idx_patient_id (patient_id),
            KEY idx_doctor_id (doctor_id),
            KEY idx_appointment_id (appointment_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_encounters);

        // 2. Medical Documents & Letters Table
        $table_documents = $wpdb->prefix . 'dior_documents';
        $sql_documents = "CREATE TABLE $table_documents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doc_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            author_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'Clinical Record',
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(20) NOT NULL DEFAULT 'PDF',
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            file_size_formatted VARCHAR(30) NULL,
            content_html LONGTEXT NULL,
            letter_meta LONGTEXT NULL,
            is_letter TINYINT(1) NOT NULL DEFAULT 0,
            attestation_hash CHAR(64) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uk_doc_uid (doc_uid),
            KEY idx_patient_id (patient_id),
            KEY idx_author_id (author_id),
            KEY idx_category (category),
            KEY idx_is_letter (is_letter),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_documents);

        // 3. Appointments & Scheduling Table
        $table_appointments = $wpdb->prefix . 'dior_appointments';
        $sql_appointments = "CREATE TABLE $table_appointments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            appt_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            condition_name VARCHAR(255) NOT NULL,
            visit_type VARCHAR(100) NOT NULL DEFAULT 'Video Visit (HD)',
            status ENUM('Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled', 'No Show') NOT NULL DEFAULT 'Confirmed',
            appt_date DATE NOT NULL,
            appt_time VARCHAR(50) NOT NULL,
            join_url VARCHAR(500) NULL,
            payment_status ENUM('Pending', 'Paid', 'Refunded', 'Failed') NOT NULL DEFAULT 'Paid',
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uk_appt_uid (appt_uid),
            KEY idx_patient_id (patient_id),
            KEY idx_doctor_id (doctor_id),
            KEY idx_status (status),
            KEY idx_appt_date (appt_date),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_appointments);

        // 4. Compliance & Security Audit Logs Table
        $table_audit = $wpdb->prefix . 'dior_audit_logs';
        $sql_audit = "CREATE TABLE $table_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            user_role VARCHAR(50) NOT NULL DEFAULT 'guest',
            action VARCHAR(100) NOT NULL,
            resource_type VARCHAR(50) NOT NULL,
            resource_id VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NULL,
            status ENUM('success', 'failed', 'denied', 'error') NOT NULL DEFAULT 'success',
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NULL,
            metadata TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_action (action),
            KEY idx_resource_type (resource_type),
            KEY idx_resource_id (resource_id),
            KEY idx_patient_id (patient_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_audit);
    }

    /**
     * Idempotent migration from legacy wp_usermeta serialized arrays to relational tables
     */
    public static function migrate_legacy_data()
    {
        global $wpdb;

        // 1. Migrate Clinical Notes (dior_doctor_notes)
        $meta_notes = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'dior_doctor_notes' AND meta_value != ''");
        $tbl_encounters = $wpdb->prefix . 'dior_encounters';

        foreach ($meta_notes as $row) {
            $patient_id = (int)$row->user_id;
            $notes = maybe_unserialize($row->meta_value);
            if (!is_array($notes)) continue;

            foreach ($notes as $n) {
                $uid = $n['id'] ?? ('SOAP-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6)));
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl_encounters WHERE encounter_uid = %s", $uid));
                if ($exists) continue;

                $created_at = !empty($n['timestamp']) ? date('Y-m-d H:i:s', strtotime($n['timestamp'])) : current_time('mysql');
                $doc_id = (int)($n['doctor_id'] ?? ($n['doctor'] ?? 1));
                $doc_name = sanitize_text_field($n['doctor_name'] ?? 'Doctor');
                $hash = hash('sha256', $uid . '|' . $patient_id . '|' . $doc_id . '|' . $created_at);

                $wpdb->insert($tbl_encounters, [
                    'encounter_uid'        => $uid,
                    'patient_id'           => $patient_id,
                    'doctor_id'            => $doc_id,
                    'appointment_id'       => sanitize_text_field($n['appointment_id'] ?? ''),
                    'encounter_type'       => sanitize_text_field($n['encounter_type'] ?? 'Telehealth Consultation'),
                    'status'               => 'finalized',
                    'subjective'           => $n['subjective'] ?? ($n['note'] ?? ''),
                    'objective'            => $n['objective'] ?? '',
                    'assessment'           => $n['assessment'] ?? '',
                    'plan'                 => $n['plan'] ?? '',
                    'diagnosis'            => sanitize_text_field($n['diagnosis'] ?? ''),
                    'follow_up'            => sanitize_text_field($n['follow_up'] ?? 'As needed / 7-10 days'),
                    'vitals_bp'            => sanitize_text_field($n['vitals']['bp'] ?? ''),
                    'vitals_hr'            => sanitize_text_field($n['vitals']['hr'] ?? ''),
                    'vitals_temp'          => sanitize_text_field($n['vitals']['temp'] ?? ''),
                    'vitals_weight'        => sanitize_text_field($n['vitals']['weight'] ?? ''),
                    'attestation_hash'     => $hash,
                    'attestation_author'   => $doc_name,
                    'attestation_timestamp'=> $created_at,
                    'created_at'           => $created_at,
                    'updated_at'           => $created_at,
                    'is_deleted'           => 0
                ]);
            }
        }

        // 2. Migrate Medical Documents & Letters (dior_documents)
        $meta_docs = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'dior_documents' AND meta_value != ''");
        $tbl_docs = $wpdb->prefix . 'dior_documents';

        foreach ($meta_docs as $row) {
            $patient_id = (int)$row->user_id;
            $docs = maybe_unserialize($row->meta_value);
            if (!is_array($docs)) continue;

            foreach ($docs as $d) {
                $uid = $d['id'] ?? ('DOC-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 7)));
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl_docs WHERE doc_uid = %s", $uid));
                if ($exists) continue;

                $created_at = !empty($d['timestamp']) ? date('Y-m-d H:i:s', strtotime($d['timestamp'])) : current_time('mysql');
                $is_letter = !empty($d['is_letter']) ? 1 : 0;
                $letter_meta = !empty($d['letter_meta']) ? json_encode($d['letter_meta']) : null;
                $hash = hash('sha256', $uid . '|' . $patient_id . '|' . $created_at);

                $wpdb->insert($tbl_docs, [
                    'doc_uid'             => $uid,
                    'patient_id'          => $patient_id,
                    'author_id'           => (int)($d['author_id'] ?? 1),
                    'title'               => sanitize_text_field($d['title'] ?? 'Medical Document'),
                    'category'            => sanitize_text_field($d['category'] ?? 'Clinical Record'),
                    'file_name'           => sanitize_file_name($d['file_name'] ?? ($uid . '.pdf')),
                    'file_path'           => sanitize_text_field($d['file_path'] ?? ''),
                    'file_type'           => sanitize_text_field($d['file_type'] ?? 'PDF'),
                    'file_size'           => 0,
                    'file_size_formatted' => sanitize_text_field($d['size'] ?? '150 KB'),
                    'content_html'        => $d['content_html'] ?? null,
                    'letter_meta'         => $letter_meta,
                    'is_letter'           => $is_letter,
                    'attestation_hash'    => $hash,
                    'created_at'          => $created_at,
                    'updated_at'          => $created_at,
                    'is_deleted'          => 0
                ]);
            }
        }

        // 3. Migrate Appointments (dior_appointments)
        $meta_apts = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'dior_appointments' AND meta_value != ''");
        $tbl_apts = $wpdb->prefix . 'dior_appointments';

        foreach ($meta_apts as $row) {
            $patient_id = (int)$row->user_id;
            $apts = maybe_unserialize($row->meta_value);
            if (!is_array($apts)) continue;

            foreach ($apts as $a) {
                $uid = $a['id'] ?? ('APT-' . rand(1000, 9999));
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl_apts WHERE appt_uid = %s", $uid));
                if ($exists) continue;

                $appt_date = !empty($a['date']) ? date('Y-m-d', strtotime($a['date'])) : date('Y-m-d');
                $created_at = current_time('mysql');

                $wpdb->insert($tbl_apts, [
                    'appt_uid'       => $uid,
                    'patient_id'     => $patient_id,
                    'doctor_id'      => (int)($a['doctor_user_id'] ?? ($a['doctor_id'] ?? 1)),
                    'condition_name' => sanitize_text_field($a['condition'] ?? 'Telehealth Consultation'),
                    'visit_type'     => sanitize_text_field($a['type'] ?? 'Video Visit (HD)'),
                    'status'         => in_array($a['status'] ?? '', ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled', 'No Show']) ? $a['status'] : 'Confirmed',
                    'appt_date'      => $appt_date,
                    'appt_time'      => sanitize_text_field($a['time'] ?? '10:00 AM'),
                    'join_url'       => esc_url_raw($a['join_url'] ?? 'https://zoom.us/join'),
                    'payment_status' => sanitize_text_field($a['payment_status'] ?? 'Paid'),
                    'notes'          => sanitize_textarea_field($a['notes'] ?? ''),
                    'created_at'     => $created_at,
                    'updated_at'     => $created_at,
                    'is_deleted'     => 0
                ]);
            }
        }
    }
}
