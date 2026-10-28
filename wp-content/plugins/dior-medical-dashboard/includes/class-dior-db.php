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
    const DB_VERSION = '4.0.6';
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

        // One-time, validated sync for legacy appointment metadata created after
        // the original schema migration. Invalid/mock entries are intentionally skipped.
        $appointment_sync_version = get_option('dior_patient_appointment_sync_version', '0.0.0');
        if (version_compare($appointment_sync_version, '1.0.0', '<')) {
            self::migrate_legacy_appointments_safely();
            update_option('dior_patient_appointment_sync_version', '1.0.0');
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
            status ENUM('Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled', 'No Show') NOT NULL DEFAULT 'Pending',
            appt_date DATE NOT NULL,
            appt_time VARCHAR(50) NOT NULL,
            join_url VARCHAR(500) NULL,
            payment_status ENUM('Pending', 'Paid', 'Refunded', 'Failed') NOT NULL DEFAULT 'Pending',
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

        // 5. Appointment lifecycle/history table
        $table_history = $wpdb->prefix . 'dior_appointment_history';
        $sql_history = "CREATE TABLE $table_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_id BIGINT UNSIGNED NULL,
            appt_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            action VARCHAR(50) NOT NULL,
            old_status VARCHAR(50) NULL,
            new_status VARCHAR(50) NULL,
            old_date DATE NULL,
            new_date DATE NULL,
            old_time VARCHAR(50) NULL,
            new_time VARCHAR(50) NULL,
            changed_by BIGINT UNSIGNED NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_appt_uid (appt_uid),
            KEY idx_patient_id (patient_id),
            KEY idx_doctor_id (doctor_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_history);

        // 6. In-app notification table
        $table_notifications = $wpdb->prefix . 'dior_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            notification_uid VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            patient_id BIGINT UNSIGNED NULL,
            doctor_id BIGINT UNSIGNED NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'system',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            action_url VARCHAR(500) NULL,
            icon VARCHAR(100) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_notification_uid (notification_uid),
            KEY idx_user_id (user_id),
            KEY idx_patient_id (patient_id),
            KEY idx_doctor_id (doctor_id),
            KEY idx_is_read (is_read),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_notifications);

        // 7. Prescriptions table
        $table_prescriptions = $wpdb->prefix . 'dior_prescriptions';
        $sql_prescriptions = "CREATE TABLE $table_prescriptions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            prescription_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            appointment_uid VARCHAR(64) NULL,
            prescription_id VARCHAR(64) NULL,
            title VARCHAR(255) NULL,
            patient_name VARCHAR(255) NULL,
            prescription_date DATE NULL,
            medications TEXT NULL,
            dosage VARCHAR(255) NULL,
            frequency VARCHAR(255) NULL,
            duration VARCHAR(255) NULL,
            doctor_name VARCHAR(255) NULL,
            medication VARCHAR(255) NOT NULL,
            instructions TEXT NULL,
            quantity VARCHAR(100) NULL,
            refills INT NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'Active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_prescription_uid (prescription_uid),
            KEY idx_patient_id (patient_id),
            KEY idx_doctor_id (doctor_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_prescriptions);

        // 8. Invoices table
        $table_invoices = $wpdb->prefix . 'dior_invoices';
        $sql_invoices = "CREATE TABLE $table_invoices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            appointment_uid VARCHAR(64) NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            description VARCHAR(255) NULL,
            issued_at DATETIME NULL,
            due_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_invoice_uid (invoice_uid),
            KEY idx_patient_id (patient_id),
            KEY idx_doctor_id (doctor_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_invoices);

        // 9. Payments table
        $table_payments = $wpdb->prefix . 'dior_payments';
        $sql_payments = "CREATE TABLE $table_payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            payment_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            appointment_uid VARCHAR(64) NULL,
            invoice_uid VARCHAR(64) NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            payment_method VARCHAR(50) NULL,
            transaction_id VARCHAR(150) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_payment_uid (payment_uid),
            KEY idx_patient_id (patient_id),
            KEY idx_doctor_id (doctor_id),
            KEY idx_invoice_uid (invoice_uid),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_payments);
        // 10. Reviews / Feedback table
        $table_reviews = $wpdb->prefix . 'dior_reviews';
        $sql_reviews = "CREATE TABLE $table_reviews (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            review_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL,
            subject VARCHAR(255) NOT NULL,
            rating INT NOT NULL DEFAULT 5,
            comment TEXT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_review_uid (review_uid),
            KEY idx_patient_id (patient_id),
            KEY idx_doctor_id (doctor_id),
            KEY idx_status (status)
        ) $charset_collate;";
        dbDelta($sql_reviews);

        // 11. Normalized patient profile table. wp_users remains the authentication source.
        $table_patients = $wpdb->prefix . 'dior_patients';
        $sql_patients = "CREATE TABLE $table_patients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            patient_uid VARCHAR(64) NOT NULL,
            first_name VARCHAR(100) NOT NULL DEFAULT '',
            last_name VARCHAR(100) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL DEFAULT '',
            phone VARCHAR(50) NULL,
            dob DATE NULL,
            gender VARCHAR(50) NULL,
            blood_group VARCHAR(10) NULL,
            address VARCHAR(500) NULL,
            city VARCHAR(100) NULL,
            state VARCHAR(100) NULL,
            country VARCHAR(100) NULL,
            avatar_url VARCHAR(500) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            is_demo TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_user_id (user_id),
            UNIQUE KEY uk_patient_uid (patient_uid),
            KEY idx_email (email),
            KEY idx_status (status)
        ) $charset_collate;";
        dbDelta($sql_patients);
    }

    /**
     * Safely migrate valid legacy appointment records into the canonical relational table.
     * Intake-only placeholders and records without a real provider/date/time are ignored.
     */
    public static function migrate_legacy_appointments_safely()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'dior_appointments';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return;
        }

        // Hide previously generated intake placeholders that were never real bookings.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_deleted = 1, updated_at = %s WHERE is_deleted = 0 AND appt_uid LIKE %s AND notes = %s",
            current_time('mysql'),
            'APT-HIPAA-%',
            'Appointment created from HIPAAtizer intake form.'
        ));

        $rows = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'dior_appointments' AND meta_value != ''"
        );

        foreach ((array) $rows as $row) {
            $patient_id = absint($row->user_id);
            $appointments = maybe_unserialize($row->meta_value);
            if (!$patient_id || !is_array($appointments)) {
                continue;
            }

            $clean_appointments = array_values(array_filter($appointments, function ($item) {
                return !(is_array($item) && !empty($item['from_hipaa']));
            }));
            if (count($clean_appointments) !== count($appointments)) {
                update_user_meta($patient_id, 'dior_appointments', $clean_appointments);
            }
            $appointments = $clean_appointments;

            foreach ($appointments as $appointment) {
                if (!is_array($appointment) || !empty($appointment['from_hipaa'])) {
                    continue;
                }

                $uid = sanitize_text_field($appointment['appt_uid'] ?? ($appointment['id'] ?? ''));
                $raw_date = $appointment['raw_date'] ?? ($appointment['appt_date'] ?? ($appointment['date'] ?? ''));
                $raw_time = sanitize_text_field($appointment['appt_time'] ?? ($appointment['time'] ?? ''));
                $date_timestamp = $raw_date !== '' ? strtotime((string) $raw_date) : false;
                if ($uid === '' || !$date_timestamp || $raw_time === '' || in_array(strtolower($raw_time), ['pending', 'asap', '—', '-'], true)) {
                    continue;
                }

                $doctor_id = absint($appointment['doctor_id'] ?? ($appointment['doctor_user_id'] ?? 0));
                if (!$doctor_id) {
                    continue;
                }

                $doctor_post = get_post($doctor_id);
                $doctor_user = get_userdata($doctor_id);
                $is_provider_post = $doctor_post && $doctor_post->post_type === 'wpddb_doctor' && $doctor_post->post_status === 'publish';
                $is_provider_user = $doctor_user && (
                    in_array('doctor', (array) $doctor_user->roles, true)
                    || in_array('administrator', (array) $doctor_user->roles, true)
                );
                if (!$is_provider_post && !$is_provider_user) {
                    continue;
                }

                $existing_uid = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE appt_uid = %s LIMIT 1",
                    $uid
                ));
                if ($existing_uid) {
                    continue;
                }

                $appt_date = wp_date('Y-m-d', $date_timestamp);
                $existing_slot = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE patient_id = %d AND appt_date = %s AND appt_time = %s AND status NOT IN ('Cancelled', 'No Show') AND is_deleted = 0 LIMIT 1",
                    $patient_id,
                    $appt_date,
                    $raw_time
                ));
                if ($existing_slot) {
                    continue;
                }

                $allowed_statuses = ['Pending', 'Confirmed', 'Scheduled', 'Success', 'In Progress', 'Completed', 'Cancelled', 'No Show', 'In-Queue'];
                $status = sanitize_text_field($appointment['status'] ?? 'Pending');
                if (!in_array($status, $allowed_statuses, true)) {
                    $status = 'Pending';
                }

                $allowed_payment_statuses = ['Pending', 'Paid', 'Refunded', 'Failed'];
                $payment_status = sanitize_text_field($appointment['payment_status'] ?? 'Pending');
                if (!in_array($payment_status, $allowed_payment_statuses, true)) {
                    $payment_status = 'Pending';
                }

                $created_at = !empty($appointment['created_at']) && strtotime($appointment['created_at'])
                    ? wp_date('Y-m-d H:i:s', strtotime($appointment['created_at']))
                    : current_time('mysql');

                $wpdb->insert($table, [
                    'appt_uid' => $uid,
                    'patient_id' => $patient_id,
                    'doctor_id' => $doctor_id,
                    'condition_name' => sanitize_text_field($appointment['condition'] ?? 'Telehealth Consultation'),
                    'visit_type' => sanitize_text_field($appointment['type'] ?? ($appointment['visit_type'] ?? 'Video Visit')),
                    'status' => $status,
                    'appt_date' => $appt_date,
                    'appt_time' => $raw_time,
                    'join_url' => !empty($appointment['join_url']) ? esc_url_raw($appointment['join_url']) : '',
                    'payment_status' => $payment_status,
                    'notes' => sanitize_textarea_field($appointment['notes'] ?? ''),
                    'created_at' => $created_at,
                    'updated_at' => current_time('mysql'),
                    'is_deleted' => 0,
                ]);
            }
        }
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
            $patient_id = absint($row->user_id);
            $notes = maybe_unserialize($row->meta_value);
            if (!$patient_id || !is_array($notes)) {
                continue;
            }

            foreach ($notes as $note) {
                if (!is_array($note)) {
                    continue;
                }

                $uid = sanitize_text_field($note['encounter_uid'] ?? ($note['id'] ?? ''));
                $raw_created_at = $note['timestamp'] ?? ($note['created_at'] ?? ($note['date'] ?? ''));
                $created_ts = $raw_created_at !== '' ? strtotime((string) $raw_created_at) : false;
                $has_content = !empty($note['subjective']) || !empty($note['note']) || !empty($note['objective'])
                    || !empty($note['assessment']) || !empty($note['plan']) || !empty($note['diagnosis']);
                if ($uid === '' || !$created_ts || !$has_content) {
                    continue;
                }

                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl_encounters WHERE encounter_uid = %s", $uid));
                if ($exists) {
                    continue;
                }

                $created_at = wp_date('Y-m-d H:i:s', $created_ts);
                $doc_id = absint($note['doctor_id'] ?? 0);
                $doc_name = sanitize_text_field($note['doctor_name'] ?? 'Provider not recorded');
                $status = sanitize_text_field($note['status'] ?? 'finalized');
                if (!in_array($status, ['draft', 'finalized', 'amended', 'archived'], true)) {
                    $status = 'finalized';
                }
                $hash = hash('sha256', $uid . '|' . $patient_id . '|' . $doc_id . '|' . $created_at);

                $wpdb->insert($tbl_encounters, [
                    'encounter_uid'         => $uid,
                    'patient_id'            => $patient_id,
                    'doctor_id'             => $doc_id,
                    'appointment_id'        => sanitize_text_field($note['appointment_id'] ?? ''),
                    'encounter_type'        => sanitize_text_field($note['encounter_type'] ?? 'Telehealth Consultation'),
                    'status'                => $status,
                    'subjective'            => $note['subjective'] ?? ($note['note'] ?? ''),
                    'objective'             => $note['objective'] ?? '',
                    'assessment'            => $note['assessment'] ?? '',
                    'plan'                  => $note['plan'] ?? '',
                    'diagnosis'             => sanitize_text_field($note['diagnosis'] ?? ''),
                    'follow_up'             => sanitize_text_field($note['follow_up'] ?? ''),
                    'vitals_bp'             => sanitize_text_field($note['vitals']['bp'] ?? ''),
                    'vitals_hr'             => sanitize_text_field($note['vitals']['hr'] ?? ''),
                    'vitals_temp'           => sanitize_text_field($note['vitals']['temp'] ?? ''),
                    'vitals_weight'         => sanitize_text_field($note['vitals']['weight'] ?? ''),
                    'attestation_hash'      => $hash,
                    'attestation_author'    => $doc_name,
                    'attestation_timestamp' => $created_at,
                    'created_at'            => $created_at,
                    'updated_at'            => $created_at,
                    'is_deleted'            => 0
                ]);
            }
        }

        // 2. Migrate Medical Documents & Letters (dior_documents)
        $meta_docs = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'dior_documents' AND meta_value != ''");
        $tbl_docs = $wpdb->prefix . 'dior_documents';

        foreach ($meta_docs as $row) {
            $patient_id = absint($row->user_id);
            $docs = maybe_unserialize($row->meta_value);
            if (!$patient_id || !is_array($docs)) {
                continue;
            }

            foreach ($docs as $document) {
                if (!is_array($document)) {
                    continue;
                }

                $uid = sanitize_text_field($document['doc_uid'] ?? ($document['id'] ?? ''));
                $raw_created_at = $document['timestamp'] ?? ($document['created_at'] ?? ($document['date'] ?? ''));
                $created_ts = $raw_created_at !== '' ? strtotime((string) $raw_created_at) : false;
                $file_path = sanitize_text_field($document['file_path'] ?? ($document['file_url'] ?? ($document['url'] ?? '')));
                $content_html = $document['content_html'] ?? null;
                if ($uid === '' || !$created_ts || ($file_path === '' && empty($content_html))) {
                    continue;
                }

                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl_docs WHERE doc_uid = %s", $uid));
                if ($exists) {
                    continue;
                }

                $created_at = wp_date('Y-m-d H:i:s', $created_ts);
                $is_letter = !empty($document['is_letter']) ? 1 : 0;
                $letter_meta = !empty($document['letter_meta']) ? wp_json_encode($document['letter_meta']) : null;
                $hash = hash('sha256', $uid . '|' . $patient_id . '|' . $created_at);
                $file_name = sanitize_file_name($document['file_name'] ?? basename((string) parse_url($file_path, PHP_URL_PATH)));
                if ($file_name === '') {
                    $file_name = $uid . '.pdf';
                }

                $wpdb->insert($tbl_docs, [
                    'doc_uid'             => $uid,
                    'patient_id'          => $patient_id,
                    'author_id'            => absint($document['author_id'] ?? 0),
                    'title'               => sanitize_text_field($document['title'] ?? 'Medical Document'),
                    'category'            => sanitize_text_field($document['category'] ?? 'Clinical Record'),
                    'file_name'           => $file_name,
                    'file_path'           => $file_path,
                    'file_type'           => sanitize_text_field($document['file_type'] ?? ($document['type'] ?? 'Unknown')),
                    'file_size'           => absint($document['file_size'] ?? 0),
                    'file_size_formatted' => sanitize_text_field($document['size'] ?? ''),
                    'content_html'        => $content_html,
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
                if (!is_array($a) || !empty($a['from_hipaa'])) {
                    continue;
                }

                $uid = sanitize_text_field($a['appt_uid'] ?? ($a['id'] ?? ''));
                $raw_date = $a['raw_date'] ?? ($a['appt_date'] ?? ($a['date'] ?? ''));
                $date_timestamp = $raw_date !== '' ? strtotime((string) $raw_date) : false;
                $appt_time = sanitize_text_field($a['appt_time'] ?? ($a['time'] ?? ''));
                $doctor_id = absint($a['doctor_id'] ?? ($a['doctor_user_id'] ?? 0));

                if ($uid === '' || !$date_timestamp || $appt_time === '' || in_array(strtolower($appt_time), ['pending', 'asap', '—', '-'], true) || !$doctor_id) {
                    continue;
                }

                $doctor_post = get_post($doctor_id);
                $doctor_user = get_userdata($doctor_id);
                $is_provider_post = $doctor_post && $doctor_post->post_type === 'wpddb_doctor' && $doctor_post->post_status === 'publish';
                $is_provider_user = $doctor_user && (
                    in_array('doctor', (array) $doctor_user->roles, true)
                    || in_array('administrator', (array) $doctor_user->roles, true)
                );
                if (!$is_provider_post && !$is_provider_user) {
                    continue;
                }

                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl_apts WHERE appt_uid = %s", $uid));
                if ($exists) {
                    continue;
                }

                $appt_date = wp_date('Y-m-d', $date_timestamp);
                $created_at = !empty($a['created_at']) && strtotime($a['created_at'])
                    ? wp_date('Y-m-d H:i:s', strtotime($a['created_at']))
                    : current_time('mysql');
                $allowed_statuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled', 'No Show'];
                $status = sanitize_text_field($a['status'] ?? 'Pending');
                if (!in_array($status, $allowed_statuses, true)) {
                    $status = 'Pending';
                }
                $allowed_payment_statuses = ['Pending', 'Paid', 'Refunded', 'Failed'];
                $payment_status = sanitize_text_field($a['payment_status'] ?? 'Pending');
                if (!in_array($payment_status, $allowed_payment_statuses, true)) {
                    $payment_status = 'Pending';
                }

                $wpdb->insert($tbl_apts, [
                    'appt_uid'       => $uid,
                    'patient_id'     => $patient_id,
                    'doctor_id'      => $doctor_id,
                    'condition_name' => sanitize_text_field($a['condition'] ?? 'Telehealth Consultation'),
                    'visit_type'     => sanitize_text_field($a['type'] ?? ($a['visit_type'] ?? 'Video Visit')),
                    'status'         => $status,
                    'appt_date'      => $appt_date,
                    'appt_time'      => $appt_time,
                    'join_url'       => !empty($a['join_url']) ? esc_url_raw($a['join_url']) : '',
                    'payment_status' => $payment_status,
                    'notes'          => sanitize_textarea_field($a['notes'] ?? ''),
                    'created_at'     => $created_at,
                    'updated_at'     => current_time('mysql'),
                    'is_deleted'     => 0
                ]);
            }
        }
    }
}
