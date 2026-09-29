<?php
/**
 * Dior Medical - Appointment Engine & Scheduling State Machine
 * 
 * Manages appointment scheduling, doctor queues, valid lifecycle transitions,
 * double-booking prevention, and indexed database queries.
 * 
 * @package Dior Medical
 * @version 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dior_Appointment_Service
{
    /**
     * Valid state transitions map
     */
    private static $valid_transitions = [
        'Pending'     => ['Confirmed', 'Cancelled'],
        'Confirmed'   => ['In Progress', 'Completed', 'Cancelled', 'No Show'],
        'In Progress' => ['Completed', 'Cancelled'],
        'Completed'   => [],
        'Cancelled'   => [],
        'No Show'     => []
    ];

    /**
     * Book a new appointment
     */
    public static function book_appointment(array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_appointments';

        $patient_id = Dior_Auth_Service::validate_id($data['patient_id'] ?? 0);
        if (!$patient_id) {
            return new WP_Error('invalid_patient', 'Valid Patient ID is required.');
        }

        $current_user_id = Dior_Auth_Service::get_current_user_id();
        if ($current_user_id && !Dior_Auth_Service::can_access_patient($patient_id, $current_user_id)) {
            Dior_Audit_Service::log('appointment_book', 'appointment', 'unauthorized', $patient_id, 'denied');
            return new WP_Error('unauthorized', 'Unauthorized to book appointment for this patient.');
        }

        // Validate personal profile completion
        if (class_exists('Dior_Patient_Portal_Data') && !Dior_Patient_Portal_Data::is_profile_complete($patient_id)) {
            $missing = Dior_Patient_Portal_Data::get_missing_profile_fields($patient_id);
            return new WP_Error('incomplete_profile', 'Please complete your personal profile (' . implode(', ', $missing) . ') before booking an appointment.');
        }

        $doctor_id = (int)($data['doctor_id'] ?? 1);
        $condition = sanitize_text_field($data['condition'] ?? 'Urgent Care Telehealth');
        $visit_type = sanitize_text_field($data['visit_type'] ?? 'Video Visit (HD)');
        $appt_date = Dior_Auth_Service::validate_date($data['appt_date'] ?? date('Y-m-d'));
        if (empty($appt_date)) {
            $appt_date = date('Y-m-d');
        }
        $appt_time = sanitize_text_field($data['appt_time'] ?? 'Today - ASAP');
        $join_url = esc_url_raw($data['join_url'] ?? 'https://zoom.us/join');
        $payment_status = Dior_Auth_Service::validate_enum($data['payment_status'] ?? 'Paid', ['Pending', 'Paid', 'Refunded', 'Failed'], 'Paid');
        $notes = sanitize_textarea_field($data['notes'] ?? '');

        // Check for double booking race-condition
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE patient_id = %d AND appt_date = %s AND appt_time = %s AND status NOT IN ('Cancelled', 'No Show') AND is_deleted = 0",
            $patient_id, $appt_date, $appt_time
        ));

        if ($duplicate) {
            return new WP_Error('duplicate_booking', 'An active appointment is already scheduled for this time slot.');
        }

        $appt_uid = 'APT-' . rand(1000, 9999);
        $now = current_time('mysql');

        $inserted = $wpdb->insert($table, [
            'appt_uid'       => $appt_uid,
            'patient_id'     => $patient_id,
            'doctor_id'      => $doctor_id,
            'condition_name' => $condition,
            'visit_type'     => $visit_type,
            'status'         => 'Confirmed',
            'appt_date'      => $appt_date,
            'appt_time'      => $appt_time,
            'join_url'       => $join_url,
            'payment_status' => $payment_status,
            'notes'          => $notes,
            'created_at'     => $now,
            'updated_at'     => $now,
            'is_deleted'     => 0
        ]);

        if (!$inserted) {
            return new WP_Error('db_error', 'Failed to schedule appointment.');
        }

        Dior_Audit_Service::log('appointment_book', 'appointment', $appt_uid, $patient_id, 'success', [
            'doctor_id' => $doctor_id,
            'date'      => $appt_date,
            'time'      => $appt_time
        ]);

        self::sync_legacy_usermeta($patient_id);

        return self::get_appointment_by_uid($appt_uid);
    }

    /**
     * Get single appointment by numeric ID or UID
     */
    public static function get_appointment($id_or_uid)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_appointments';
        if (empty($id_or_uid)) {
            return null;
        }

        if (is_numeric($id_or_uid)) {
            $appt = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE (id = %d OR appt_uid = %s) AND is_deleted = 0",
                $id_or_uid, (string)$id_or_uid
            ), ARRAY_A);
        } else {
            $appt = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE appt_uid = %s AND is_deleted = 0",
                $id_or_uid
            ), ARRAY_A);
        }

        return $appt ?: null;
    }

    /**
     * Get single appointment by UID (alias for backwards compatibility)
     */
    public static function get_appointment_by_uid($appt_uid)
    {
        return self::get_appointment($appt_uid);
    }

    /**
     * Reschedule an appointment with double-booking prevention and multi-channel notifications
     */
    public static function reschedule_appointment($appt_uid, $new_date, $new_time, $notes = '', $rescheduled_by = 'patient')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_appointments';

        $appt = self::get_appointment($appt_uid);
        if (!$appt) {
            return new WP_Error('not_found', 'Appointment not found.');
        }

        $current_user_id = Dior_Auth_Service::get_current_user_id();
        $is_doc = Dior_Auth_Service::is_doctor($current_user_id);
        $is_pat = (int)$appt['patient_id'] === $current_user_id;

        if (!$is_doc && !$is_pat && !Dior_Auth_Service::is_admin($current_user_id)) {
            Dior_Audit_Service::log('appointment_reschedule', 'appointment', $appt['appt_uid'], $appt['patient_id'], 'denied');
            return new WP_Error('unauthorized', 'Unauthorized to reschedule this appointment.');
        }

        // Validate status: Only active appointments can be rescheduled
        $allowed_status = ['Confirmed', 'Pending', 'Scheduled', 'In-Queue'];
        if (!in_array($appt['status'], $allowed_status, true)) {
            return new WP_Error('invalid_status', "This appointment is {$appt['status']} and cannot be rescheduled.");
        }

        // Validate date (must not be in the past)
        $today = current_time('Y-m-d');
        $valid_date = Dior_Auth_Service::validate_date($new_date);
        if (empty($valid_date)) {
            return new WP_Error('invalid_date', 'Please provide a valid date for rescheduling.');
        }
        if ($valid_date < $today) {
            return new WP_Error('past_date', 'Reschedule date cannot be in the past. Please select today or a future date.');
        }

        $clean_time = sanitize_text_field($new_time);
        if (empty($clean_time)) {
            return new WP_Error('invalid_time', 'Please select a valid time slot.');
        }

        // Double-booking check: verify patient doesn't already have another active appointment at this slot
        $conflict = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE patient_id = %d AND appt_date = %s AND appt_time = %s AND appt_uid != %s AND status NOT IN ('Cancelled', 'No Show') AND is_deleted = 0",
            $appt['patient_id'], $valid_date, $clean_time, $appt['appt_uid']
        ));

        if ($conflict) {
            return new WP_Error('duplicate_booking', 'An active appointment is already scheduled for this date and time slot.');
        }

        $old_date = $appt['appt_date'];
        $old_time = $appt['appt_time'];
        $now = current_time('mysql');

        $resched_tag = " [Rescheduled to {$valid_date} {$clean_time} by " . ucfirst($rescheduled_by) . " on " . current_time('M j, Y g:i A') . "]";
        $updated_notes = trim(($appt['notes'] ?? '') . $resched_tag);

        $updated = $wpdb->update(
            $table,
            [
                'appt_date'  => $valid_date,
                'appt_time'  => $clean_time,
                'status'     => 'Confirmed',
                'notes'      => $updated_notes,
                'updated_at' => $now
            ],
            ['appt_uid' => $appt['appt_uid']]
        );

        if ($updated === false) {
            return new WP_Error('db_error', 'Failed to update appointment schedule in database.');
        }

        // Audit Logging
        Dior_Audit_Service::log('appointment_reschedule', 'appointment', $appt['appt_uid'], $appt['patient_id'], 'success', [
            'from' => $old_date . ' ' . $old_time,
            'to'   => $valid_date . ' ' . $clean_time,
            'by'   => $rescheduled_by
        ]);

        // Dual-write legacy meta
        self::sync_legacy_usermeta($appt['patient_id']);

        // Multi-Channel Notifications (Dashboard In-App + Luxury Email with Anti-Spam & Localhost fallback)
        if (class_exists('Dior_Notification_Service')) {
            Dior_Notification_Service::send_appointment_rescheduled(
                $appt['appt_uid'],
                $old_date,
                $old_time,
                $valid_date,
                $clean_time,
                $rescheduled_by
            );
        }

        return self::get_appointment($appt['appt_uid']);
    }

    /**
     * Cancel an appointment with reason tracking and multi-channel notifications
     */
    public static function cancel_appointment($appt_uid, $reason = '', $cancelled_by = 'patient')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_appointments';

        $appt = self::get_appointment($appt_uid);
        if (!$appt) {
            return new WP_Error('not_found', 'Appointment not found.');
        }

        $current_user_id = Dior_Auth_Service::get_current_user_id();
        $is_doc = Dior_Auth_Service::is_doctor($current_user_id);
        $is_pat = (int)$appt['patient_id'] === $current_user_id;

        if (!$is_doc && !$is_pat && !Dior_Auth_Service::is_admin($current_user_id)) {
            Dior_Audit_Service::log('appointment_cancel', 'appointment', $appt['appt_uid'], $appt['patient_id'], 'denied');
            return new WP_Error('unauthorized', 'Unauthorized to cancel this appointment.');
        }

        // Check if already cancelled or completed
        if ($appt['status'] === 'Cancelled') {
            return new WP_Error('already_cancelled', 'This appointment has already been cancelled.');
        }
        if ($appt['status'] === 'Completed') {
            return new WP_Error('already_completed', 'Completed consultations cannot be cancelled.');
        }

        $now = current_time('mysql');
        $clean_reason = sanitize_text_field($reason ?: 'Cancelled by ' . $cancelled_by);
        $cancel_tag = " [Cancelled by " . ucfirst($cancelled_by) . " on " . current_time('M j, Y g:i A') . " - Reason: {$clean_reason}]";
        $updated_notes = trim(($appt['notes'] ?? '') . $cancel_tag);

        $updated = $wpdb->update(
            $table,
            [
                'status'     => 'Cancelled',
                'notes'      => $updated_notes,
                'updated_at' => $now
            ],
            ['appt_uid' => $appt['appt_uid']]
        );

        if ($updated === false) {
            return new WP_Error('db_error', 'Failed to update appointment status in database.');
        }

        // Audit Logging
        Dior_Audit_Service::log('appointment_cancel', 'appointment', $appt['appt_uid'], $appt['patient_id'], 'success', [
            'reason' => $clean_reason,
            'by'     => $cancelled_by
        ]);

        // Dual-write legacy meta
        self::sync_legacy_usermeta($appt['patient_id']);

        // Multi-Channel Notifications (Dashboard In-App + Luxury Email with Anti-Spam & Localhost fallback)
        if (class_exists('Dior_Notification_Service')) {
            Dior_Notification_Service::send_appointment_cancelled(
                $appt['appt_uid'],
                $clean_reason,
                $cancelled_by
            );
        }

        return true;
    }

    /**
     * Send Appointment Reminder to both Patient and Doctor
     */
    public static function send_reminder($appt_uid, $sender = 'doctor')
    {
        $appt = self::get_appointment($appt_uid);
        if (!$appt) {
            return new WP_Error('not_found', 'Appointment not found.');
        }

        if (!class_exists('Dior_Notification_Service')) {
            return new WP_Error('service_unavailable', 'Notification service unavailable.');
        }

        $sent = Dior_Notification_Service::send_appointment_reminder($appt['appt_uid'], $sender);
        return $sent ? true : new WP_Error('reminder_failed', 'Failed to dispatch appointment reminder.');
    }

    /**
     * Transition appointment status with state machine validation
     */
    public static function update_status($appt_uid, $new_status, $reason = '')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_appointments';

        $appt = self::get_appointment($appt_uid);
        if (!$appt) {
            return new WP_Error('not_found', 'Appointment not found.');
        }

        if ($new_status === 'Cancelled') {
            $current_user_id = Dior_Auth_Service::get_current_user_id();
            $actor = Dior_Auth_Service::is_doctor($current_user_id) ? 'doctor' : 'patient';
            return self::cancel_appointment($appt['appt_uid'], $reason, $actor);
        }

        $current_user_id = Dior_Auth_Service::get_current_user_id();
        $is_doc = Dior_Auth_Service::is_doctor($current_user_id);
        $is_pat = (int)$appt['patient_id'] === $current_user_id;

        if (!$is_doc && !$is_pat && !Dior_Auth_Service::is_admin($current_user_id)) {
            Dior_Audit_Service::log('appointment_status_change', 'appointment', $appt['appt_uid'], $appt['patient_id'], 'denied');
            return new WP_Error('unauthorized', 'Unauthorized to modify this appointment.');
        }

        $curr_status = $appt['status'];
        $allowed_next = self::$valid_transitions[$curr_status] ?? [];

        if (!in_array($new_status, $allowed_next, true) && !Dior_Auth_Service::is_admin($current_user_id)) {
            return new WP_Error('invalid_transition', "Cannot transition appointment from '{$curr_status}' to '{$new_status}'.");
        }

        $now = current_time('mysql');
        $updated = $wpdb->update(
            $table,
            ['status' => $new_status, 'updated_at' => $now],
            ['appt_uid' => $appt['appt_uid']]
        );

        if ($updated !== false) {
            Dior_Audit_Service::log('appointment_status_change', 'appointment', $appt['appt_uid'], $appt['patient_id'], 'success', [
                'from_status' => $curr_status,
                'to_status'   => $new_status
            ]);

            self::sync_legacy_usermeta($appt['patient_id']);
            return true;
        }

        return false;
    }

    /**
     * Get appointments for a patient (Normalized for consistent portal access)
     */
    public static function get_patient_appointments($patient_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_appointments';
        $patient_id = (int)$patient_id;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE patient_id = %d AND is_deleted = 0 ORDER BY appt_date DESC, id DESC",
            $patient_id
        ), ARRAY_A);

        if (!$results) {
            return [];
        }

        foreach ($results as &$r) {
            $r['id'] = $r['appt_uid'];
            $r['date'] = $r['appt_date'];
            $r['time'] = $r['appt_time'];
            $r['condition'] = $r['condition_name'];
            $r['type'] = $r['visit_type'];

            $doc_id = (int)$r['doctor_id'];
            $doc_user = $doc_id ? get_userdata($doc_id) : null;
            $doc_name = $doc_user ? 'Dr. ' . (trim($doc_user->first_name . ' ' . $doc_user->last_name) ?: $doc_user->display_name) : 'Dr. Evelyn Vance, MD';
            $r['provider'] = $doc_name;
            $r['doctor_name'] = $doc_name;
            $r['provider_spec'] = 'Board Certified Urgent Care Physician';

            $status = $r['status'] ?? 'Confirmed';
            $r['can_reschedule'] = in_array($status, ['Confirmed', 'Pending', 'Scheduled', 'In-Queue'], true);
            $r['can_cancel'] = in_array($status, ['Confirmed', 'Pending', 'Scheduled', 'In-Queue'], true);
            $r['can_join'] = in_array($status, ['Confirmed', 'In Progress', 'Scheduled', 'In-Queue'], true);
        }

        return $results;
    }

    /**
     * Get all appointments for doctor dashboard (Optimized indexed SQL query)
     */
    public static function get_doctor_appointments($doctor_id = 0, $status = null, $date = null)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_appointments';

        $where = ['a.is_deleted = 0'];
        $params = [];

        $is_admin = Dior_Auth_Service::is_admin($doctor_id);
        $default_doc_id = class_exists('Dior_Doctor_Resolver') ? Dior_Doctor_Resolver::get_default_doctor_id() : 0;

        // Strict Doctor Filtering: Each doctor only sees their own appointments
        if (!$is_admin && $doctor_id) {
            $linked_post_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wpddb_doctor_user_id' AND meta_value = %s",
                (string)$doctor_id
            ));
            
            $allowed_ids = array_unique(array_filter(array_merge([(int)$doctor_id], array_map('intval', (array)$linked_post_ids))));
            
            // If primary clinic default doctor, allow general/unassigned appointments
            if ($doctor_id == $default_doc_id) {
                $allowed_ids[] = 0;
                $allowed_ids[] = 1;
                $allowed_ids = array_unique($allowed_ids);
            }

            $in_placeholders = implode(',', array_fill(0, count($allowed_ids), '%d'));
            $where[] = "a.doctor_id IN ($in_placeholders)";
            foreach ($allowed_ids as $aid) {
                $params[] = $aid;
            }
        }

        if (!empty($status)) {
            $where[] = 'a.status = %s';
            $params[] = $status;
        }

        if (!empty($date)) {
            $where[] = 'a.appt_date = %s';
            $params[] = $date;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT a.*, 
                       u.display_name as patient_display_name, 
                       u.user_email as patient_email
                FROM $table a
                LEFT JOIN {$wpdb->users} u ON a.patient_id = u.ID
                WHERE $where_sql
                ORDER BY a.appt_date DESC, a.id DESC";

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        if (!$results) return [];

        // Attach resolved metadata
        foreach ($results as &$r) {
            $pid = (int)$r['patient_id'];
            $fn = get_user_meta($pid, 'first_name', true);
            $ln = get_user_meta($pid, 'last_name', true);
            $fullName = trim($fn . ' ' . $ln);
            $r['user_id'] = $pid;
            $r['patient_user_id'] = $pid;
            $r['patient_name'] = $fullName ?: ($r['patient_display_name'] ?: 'Patient');
            $r['patient_id_meta'] = get_user_meta($pid, 'patient_id', true) ?: ('DM-' . (10000 + $pid));
            $r['phone'] = get_user_meta($pid, 'phone', true) ?: '—';
            $r['id'] = $r['appt_uid'];
            $r['date'] = $r['appt_date'];
            $r['time'] = $r['appt_time'];
            $r['condition'] = $r['condition_name'];
            $r['type'] = $r['visit_type'];
        }

        return $results;
    }

    /**
     * Dual-write / sync to legacy user meta for backwards compatibility
     */
    private static function sync_legacy_usermeta($patient_id)
    {
        $apts = self::get_patient_appointments($patient_id);
        $legacy = [];
        foreach ($apts as $a) {
            $legacy[] = [
                'id'             => $a['appt_uid'],
                'date'           => $a['appt_date'],
                'time'           => $a['appt_time'],
                'condition'      => $a['condition_name'],
                'provider'       => 'Dr. Evelyn Vance, MD',
                'provider_spec'  => 'Board Certified Urgent Care Physician',
                'type'           => $a['visit_type'],
                'status'         => $a['status'],
                'join_url'       => $a['join_url'],
                'payment_status' => $a['payment_status'],
                'notes'          => $a['notes'],
                'can_join'       => in_array($a['status'], ['Confirmed', 'In Progress'])
            ];
        }
        update_user_meta($patient_id, 'dior_appointments', $legacy);
    }
}
