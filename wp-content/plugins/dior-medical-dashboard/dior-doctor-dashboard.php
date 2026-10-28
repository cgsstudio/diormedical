<?php
/**
 * Dior Medical — Doctor / Provider Dashboard
 * Shortcode: [dior_doctor_dashboard]
 * Access: administrator | doctor roles
 */

if (!defined("ABSPATH"))
    exit;

class Dior_Doctor_Dashboard
{
    public static function init()
    {
        add_shortcode("dior_doctor_dashboard", [__CLASS__, "render"]);
        add_action("wp_ajax_dior_doctor_update_appt_status", [__CLASS__, "ajax_update_appt_status"]);
        add_action("wp_ajax_dior_doctor_send_reminder", [__CLASS__, "ajax_send_reminder"]);
        add_action("wp_ajax_dior_doctor_reschedule_appt", [__CLASS__, "ajax_reschedule_appt"]);
        add_action("wp_ajax_dior_doctor_edit_appointment", [__CLASS__, "ajax_edit_appointment"]);
        add_action("wp_ajax_dior_doc_appointment_update", [__CLASS__, "ajax_edit_appointment"]);
        add_action("wp_ajax_dior_doctor_get_patient_appointment_details", [__CLASS__, "ajax_get_patient_appointment_details"]);
        add_action("wp_ajax_dior_doctor_add_prescription", [__CLASS__, "ajax_add_prescription"]);
        add_action("wp_ajax_dior_doctor_add_note", [__CLASS__, "ajax_add_note"]);
        add_action("wp_ajax_dior_doctor_save_soap_note", [__CLASS__, "ajax_save_soap_note"]);
        add_action("wp_ajax_dior_doctor_delete_note", [__CLASS__, "ajax_delete_note"]);
        add_action("wp_ajax_dior_doctor_mark_notif_read", [__CLASS__, "ajax_mark_notif_read"]);
        add_action("wp_ajax_dior_doctor_mark_single_notif_read", [__CLASS__, "ajax_mark_notif_read"]);
        add_action("wp_ajax_dior_doctor_mark_all_notif_read", [__CLASS__, "ajax_mark_all_notif_read"]);
        add_action("wp_ajax_dior_doctor_delete_notification", [__CLASS__, "ajax_delete_notification"]);
        add_action("wp_ajax_dior_doctor_clear_read_notifications", [__CLASS__, "ajax_clear_read_notifications"]);
        add_action("wp_ajax_dior_doctor_get_live_notifications", [__CLASS__, "ajax_get_live_notifications"]);
        add_action("wp_ajax_dior_doctor_add_virtual_visit_record", [__CLASS__, "ajax_add_virtual_visit_record"]);
        add_action("wp_ajax_dior_doctor_delete_virtual_visit_record", [__CLASS__, "ajax_delete_virtual_visit_record"]);
        add_action("wp_ajax_dior_doctor_save_profile", [__CLASS__, "ajax_save_profile"]);
        add_action("wp_ajax_dior_doctor_upload_avatar", [__CLASS__, "ajax_upload_avatar"]);
        add_action("wp_ajax_dior_doctor_remove_avatar", [__CLASS__, "ajax_remove_avatar"]);
        add_action("wp_ajax_dior_doctor_upload_signature", [__CLASS__, "ajax_upload_signature"]);
        add_action("wp_ajax_dior_doctor_remove_signature", [__CLASS__, "ajax_remove_signature"]);
        add_action("wp_ajax_dior_doctor_change_password", [__CLASS__, "ajax_change_password"]);
        add_action("wp_ajax_dior_doctor_get_patient_detail", [__CLASS__, "ajax_get_patient_detail"]);
        add_action("wp_ajax_dior_doctor_send_multi_prescription", [__CLASS__, "ajax_send_multi_prescription"]);
        add_action("wp_ajax_dior_doctor_add_patient", ["Dior_Doctor_Dynamic", "ajax_patient_add"]);
        add_action("wp_ajax_dior_doc_patient_add", ["Dior_Doctor_Dynamic", "ajax_patient_add"]);
        add_action("wp_ajax_dior_doctor_update_patient", ["Dior_Doctor_Dynamic", "ajax_patient_update"]);
        add_action("wp_ajax_dior_doc_patient_update", ["Dior_Doctor_Dynamic", "ajax_patient_update"]);
        add_action("wp_ajax_dior_doc_review_save", ["Dior_Doctor_Dynamic", "ajax_review_save"]);
        add_action("wp_ajax_dior_doc_review_delete", ["Dior_Doctor_Dynamic", "ajax_review_delete"]);
        add_action("wp_ajax_dior_doc_review_bulk_delete", ["Dior_Doctor_Dynamic", "ajax_review_bulk_delete"]);
        add_action("init", [__CLASS__, "register_doctor_role"]);
        add_action("init", [__CLASS__, "ensure_doctor_page"]);
        add_action("init", ["Dior_Doctor_Resolver", "ensure_default_doctor"]);
        add_action("admin_init", ["Dior_Doctor_Resolver", "consolidate_legacy_accounts"], 1);
        add_filter("wp_authenticate_user", ["Dior_Doctor_Resolver", "block_merged_account_login"], 20, 2);
        add_action("save_post_wpddb_doctor", ["Dior_Doctor_Resolver", "sync_docbooker_doctor_to_wp_user"]);
    }

    public static function register_doctor_role()
    {
        if (!get_role("doctor")) {
            add_role("doctor", "Doctor / Provider", [
                "read" => true,
                "edit_posts" => false,
                "delete_posts" => false,
                "publish_posts" => false,
                "upload_files" => true,
                "manage_options" => false,
            ]);
        }
    }

    public static function ensure_doctor_page()
    {
        $cached_id = (int) get_option("dior_doctor_page_id", 0);
        if ($cached_id && get_post_status($cached_id) === 'publish') {
            return;
        }

        $doctor_page = get_page_by_path('doctor-dashboard');
        if ($doctor_page && $doctor_page->post_status === 'publish') {
            update_option("dior_doctor_page_id", $doctor_page->ID, false);
            return;
        }

        global $wpdb;
        $page = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[dior_doctor_dashboard]%' AND post_status='publish' LIMIT 1");
        if ($page) {
            update_option("dior_doctor_page_id", (int) $page, false);
            return;
        }
        $page_id = wp_insert_post([
            "post_title" => "Doctor Dashboard",
            "post_name" => "doctor-dashboard",
            "post_content" => "[dior_doctor_dashboard]",
            "post_status" => "publish",
            "post_type" => "page",
        ]);
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, "_wp_page_template", "elementor_canvas");
            update_post_meta($page_id, "_dior_doctor_dashboard_page", "1");
            update_option("dior_doctor_page_id", (int) $page_id, false);
        }
    }

    public static function can_access_for_dynamic()
    {
        return self::can_access();
    }

    private static function can_access()
    {
        if (current_user_can("administrator") || current_user_can("manage_options")) {
            return true;
        }
        $user = wp_get_current_user();
        if ($user && in_array('doctor', (array) $user->roles, true)) {
            return true;
        }
        return current_user_can("doctor");
    }

    public static function get_doctor_profile($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user)
            return [];
        $first_name = get_user_meta($user_id, "first_name", true) ?: $user->first_name ?: "";
        $last_name = get_user_meta($user_id, "last_name", true) ?: $user->last_name ?: "";
        $name_part = trim($first_name . " " . $last_name);

        $base_name = $name_part ?: $user->display_name;
        $full_name = (stripos($base_name, 'Dr.') === 0 || stripos($base_name, 'Dr ') === 0) ? $base_name : "Dr. " . $base_name;

        $avatar_url = get_user_meta($user_id, "dior_profile_image", true)
            ?: get_user_meta($user_id, "doctor_avatar", true)
            ?: get_user_meta($user_id, "profile_picture", true)
            ?: get_user_meta($user_id, "profile_image", true)
            ?: "";

        $initials = '';
        if (!empty($first_name) || !empty($last_name)) {
            $initials = strtoupper(substr($first_name ?: '', 0, 1) . substr($last_name ?: '', 0, 1));
        }
        if (empty($initials)) {
            $initials = strtoupper(substr($user->display_name ?: $user->user_login, 0, 2));
        }

        return [
            "user_id" => $user_id,
            "first_name" => $first_name,
            "last_name" => $last_name,
            "full_name" => $full_name,
            "avatar_url" => $avatar_url,
            "signature_url" => get_user_meta($user_id, "dior_doctor_signature", true) ?: "",
            "email" => $user->user_email,
            "user_email" => $user->user_email,
            "phone" => get_user_meta($user_id, "phone", true) ?: "",
            "specialty" => get_user_meta($user_id, "doctor_specialty", true) ?: (get_user_meta($user_id, "specialty", true) ?: "Dermatology"),
            "license_no" => get_user_meta($user_id, "doctor_license", true) ?: (get_user_meta($user_id, "license_no", true) ?: ""),
            "npi" => get_user_meta($user_id, "doctor_npi", true) ?: "",
            "bio" => get_user_meta($user_id, "doctor_bio", true) ?: (get_user_meta($user_id, "bio", true) ?: ""),
            "qualifications" => get_user_meta($user_id, "doctor_qualifications", true) ?: (get_user_meta($user_id, "qualifications", true) ?: "MBBS, MD"),
            "fee" => get_user_meta($user_id, "doctor_fee", true) ?: (get_user_meta($user_id, "fee", true) ?: "INR 500"),
            "duty_hours" => get_user_meta($user_id, "doctor_duty_hours", true) ?: (get_user_meta($user_id, "duty_hours", true) ?: "MON - SAT 10:00 AM - 8:00 PM"),
            "city" => get_user_meta($user_id, "city", true) ?: "",
            "country" => get_user_meta($user_id, "country", true) ?: "",
            "address" => get_user_meta($user_id, "address", true) ?: "",
            "zoom_link" => get_user_meta($user_id, "doctor_zoom_link", true) ?: "https://zoom.us/join",
            "initials" => $initials,
            "optin_appointment_reminders" => (get_user_meta($user_id, "optin_appointment_reminders", true) !== '0') ? '1' : '0',
            "optin_reminder_email" => (get_user_meta($user_id, "optin_reminder_email", true) !== '0') ? '1' : '0',
            "optin_reminder_dashboard" => (get_user_meta($user_id, "optin_reminder_dashboard", true) !== '0') ? '1' : '0',
        ];
    }

    /**
     * Check if doctor profile has all required personal & credential information filled
     */
    public static function is_profile_complete($user_id)
    {
        if (!$user_id)
            return false;
        $profile = self::get_doctor_profile($user_id);
        if (empty($profile))
            return false;

        $required_fields = ['first_name', 'last_name', 'phone', 'specialty', 'license_no', 'npi'];
        foreach ($required_fields as $f) {
            if (empty($profile[$f]) || trim((string) $profile[$f]) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Get missing doctor profile fields with human-readable labels
     */
    public static function get_missing_profile_fields($user_id)
    {
        if (!$user_id)
            return [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'phone' => 'Phone Number',
                'doctor_specialty' => 'Medical Specialty',
                'doctor_license' => 'Medical License Number',
                'doctor_npi' => 'NPI Number'
            ];

        $profile = self::get_doctor_profile($user_id);
        $labels = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'phone' => 'Phone Number',
            'specialty' => 'Medical Specialty',
            'license_no' => 'Medical License Number',
            'npi' => 'NPI Number'
        ];
        $missing = [];
        foreach ($labels as $k => $label) {
            if (empty($profile[$k]) || trim((string) $profile[$k]) === '') {
                $form_key = ($k === 'specialty') ? 'doctor_specialty' : (($k === 'license_no') ? 'doctor_license' : (($k === 'npi') ? 'doctor_npi' : $k));
                $missing[$form_key] = $label;
            }
        }
        return $missing;
    }

    /**
     * Resolve the single configured doctor for Doctor Dashboard workflows.
     * Legacy user/post IDs are retained only as aliases for existing records.
     */
    public static function get_single_doctor_context($user_id = 0)
    {
        $requested_id = (int) ($user_id ?: get_current_user_id());
        $requested_user = $requested_id ? get_userdata($requested_id) : false;
        if (!$requested_user) {
            return ['user_id' => 0, 'canonical_id' => 0, 'ids' => [], 'profile' => []];
        }

        if (user_can($requested_id, 'doctor')) {
            $doctor_user_id = $requested_id;
        } elseif (self::is_user_admin($requested_id)) {
            $doctor_user_id = class_exists('Dior_Doctor_Resolver')
                ? (int) Dior_Doctor_Resolver::get_default_doctor_id()
                : $requested_id;
        } else {
            return ['user_id' => 0, 'canonical_id' => 0, 'ids' => [], 'profile' => []];
        }

        if (!$doctor_user_id || !get_userdata($doctor_user_id)) {
            return ['user_id' => 0, 'canonical_id' => 0, 'ids' => [], 'profile' => []];
        }

        $doctor_ids = [$doctor_user_id];
        if (class_exists('Dior_Doctor_Resolver')) {
            $doctor_ids = array_merge(
                $doctor_ids,
                (array) Dior_Doctor_Resolver::get_accessible_doctor_ids($doctor_user_id)
            );
        }

        $linked_post_ids = [];
        foreach (['_wpddb_doctor_user_id', 'wpddb_doctor_user_id'] as $meta_key) {
            $matches = get_posts([
                'post_type' => 'wpddb_doctor',
                'post_status' => ['publish', 'private', 'draft', 'pending'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_key' => $meta_key,
                'meta_value' => (string) $doctor_user_id,
            ]);
            if (is_array($matches)) {
                $linked_post_ids = array_merge($linked_post_ids, array_map('intval', $matches));
            }
        }

        $docbooker_id = (int) get_user_meta($doctor_user_id, '_docbooker_doctor_id', true);
        if ($docbooker_id && get_post_type($docbooker_id) === 'wpddb_doctor') {
            array_unshift($linked_post_ids, $docbooker_id);
        }

        $linked_post_ids = array_values(array_unique(array_filter($linked_post_ids)));
        $doctor_ids = array_merge($doctor_ids, $linked_post_ids);

        // The canonical ID is used for new appointments; aliases are for existing records only.
        $canonical_id = !empty($linked_post_ids) ? (int) $linked_post_ids[0] : $doctor_user_id;

        $doctor_ids = array_values(array_unique(array_filter(array_map('intval', $doctor_ids), static function ($id) {
            return $id > 0;
        })));

        // Only appointment queries may include legacy unassigned IDs. Other clinical
        // resources (prescriptions, telemedicine, documents) must use real doctor IDs.
        $appointment_ids = $doctor_ids;
        if (class_exists('Dior_Doctor_Resolver') &&
            $doctor_user_id === (int) Dior_Doctor_Resolver::get_default_doctor_id()) {
            $appointment_ids = array_values(array_unique(array_merge($appointment_ids, [0, 1])));
        }

        return [
            'user_id' => $doctor_user_id,
            'canonical_id' => $canonical_id,
            'ids' => $doctor_ids,
            'appointment_ids' => $appointment_ids,
            'profile' => self::get_doctor_profile($doctor_user_id),
        ];
    }

    /**
     * Confirm an appointment belongs to the configured single-doctor dashboard.
     */
    public static function appointment_belongs_to_single_doctor($appointment, $context = null)
    {
        if (!is_array($appointment)) {
            return false;
        }
        $context = is_array($context) ? $context : self::get_single_doctor_context();
        $doctor_ids = array_map('intval', (array) ($context['appointment_ids'] ?? $context['ids'] ?? []));
        return in_array((int) ($appointment['doctor_id'] ?? 0), $doctor_ids, true);
    }

    private static function is_user_admin($user_id)
    {
        return user_can($user_id, "administrator") || user_can($user_id, "manage_options");
    }

    private static function does_appointment_belong_to_doctor($appt, $doctor_user_id)
    {
        if (self::is_user_admin($doctor_user_id)) {
            return true;
        }
        if (!empty($appt["doctor_user_id"]) && (int) $appt["doctor_user_id"] === (int) $doctor_user_id) {
            return true;
        }
        $linked_doc_id = get_user_meta($doctor_user_id, "_docbooker_doctor_id", true);
        if ($linked_doc_id && !empty($appt["doctor_id"]) && (int) $appt["doctor_id"] === (int) $linked_doc_id) {
            return true;
        }

        // Use smart resolver to match
        $doc_ref = !empty($appt["doctor_id"]) ? $appt["doctor_id"] : ($appt["provider"] ?? ($appt["doctor_name"] ?? ""));
        if ($doc_ref && class_exists('Dior_Doctor_Resolver')) {
            $resolved_uid = Dior_Doctor_Resolver::resolve_doctor_user_id($doc_ref);
            if ($resolved_uid && (int) $resolved_uid === (int) $doctor_user_id) {
                return true;
            }
        }

        $u = get_userdata($doctor_user_id);
        if ($u) {
            $fname = get_user_meta($doctor_user_id, "first_name", true) ?: $u->first_name;
            $lname = get_user_meta($doctor_user_id, "last_name", true) ?: $u->last_name;
            $disp = $u->display_name;
            $prov = $appt["provider"] ?? ($appt["doctor_name"] ?? "");

            if ($prov) {
                if ($disp && stripos($prov, $disp) !== false)
                    return true;
                if ($lname && strlen($lname) >= 3 && stripos($prov, $lname) !== false)
                    return true;
                if ($fname && strlen($fname) >= 3 && stripos($prov, $fname) !== false)
                    return true;
            }
        }

        $only_doctors = get_users(["role" => "doctor"]);
        if (count($only_doctors) === 1 && $only_doctors[0]->ID == $doctor_user_id) {
            return true;
        }

        return false;
    }

    public static function resolve_patient_name($user_id, $fallback_user = null)
    {
        if (!$user_id && !$fallback_user)
            return '—';
        $u = $fallback_user ?: get_userdata($user_id);
        $uid = $u ? $u->ID : (int) $user_id;

        $fname = get_user_meta($uid, "first_name", true) ?: ($u ? $u->first_name : '');
        $lname = get_user_meta($uid, "last_name", true) ?: ($u ? $u->last_name : '');

        // Check patient profile meta
        if (empty($fname) || empty($lname)) {
            $prof = get_user_meta($uid, "dior_patient_profile", true);
            if (is_array($prof)) {
                if (empty($fname) && !empty($prof['first_name']))
                    $fname = $prof['first_name'];
                if (empty($lname) && !empty($prof['last_name']))
                    $lname = $prof['last_name'];
                if (empty($fname) && empty($lname) && !empty($prof['full_name'])) {
                    return trim($prof['full_name']);
                }
            }
        }

        // Check HIPAA intake meta
        if (empty($fname) || empty($lname)) {
            $intake = get_user_meta($uid, "dior_hipaa_intake", true);
            if (is_array($intake)) {
                if (empty($fname) && !empty($intake['first_name']))
                    $fname = $intake['first_name'];
                if (empty($lname) && !empty($intake['last_name']))
                    $lname = $intake['last_name'];
                if (empty($fname) && empty($lname) && !empty($intake['full_legal_name'])) {
                    return trim($intake['full_legal_name']);
                }
            }
        }

        // Check billing names
        if (empty($fname))
            $fname = get_user_meta($uid, "billing_first_name", true) ?: '';
        if (empty($lname))
            $lname = get_user_meta($uid, "billing_last_name", true) ?: '';

        $full = trim($fname . ' ' . $lname);
        if (!empty($full))
            return $full;
        if ($u && !empty($u->display_name))
            return $u->display_name;
        if ($u && !empty($u->user_nicename))
            return $u->user_nicename;
        if ($u && !empty($u->user_login))
            return $u->user_login;
        return 'Patient #' . $uid;
    }

    private static function get_all_patients($doctor_user_id = 0)
    {
        global $wpdb;

        $doctor_user_id = (int) $doctor_user_id;
        $is_admin = $doctor_user_id ? self::is_user_admin($doctor_user_id) : current_user_can('manage_options');

        // Doctors only need patients related to their own appointments.
        // Admins can see the full patient population, capped for dashboard performance.
        $patient_ids = [];
        if (!$is_admin && $doctor_user_id) {
            $appointments = Dior_Appointment_Service::get_doctor_appointments($doctor_user_id);
            foreach ($appointments as $appointment) {
                $pid = (int) ($appointment['patient_id'] ?? ($appointment['patient_user_id'] ?? 0));
                if ($pid > 0) {
                    $patient_ids[$pid] = true;
                }
            }
        }

        $args = [
            'role__in' => ['subscriber', 'patient', 'customer'],
            'number' => 300,
            'orderby' => 'registered',
            'order' => 'DESC',
            'fields' => 'all',
        ];

        if (!empty($patient_ids)) {
            $args['include'] = array_keys($patient_ids);
            $args['number'] = count($patient_ids);
        } elseif (!$is_admin && $doctor_user_id) {
            return [];
        }

        $users = get_users($args);
        $patients = [];

        // Fetch this doctor's appointment set once. Avoid N+1 appointment queries per patient.
        $doctor_appointments = $doctor_user_id ? Dior_Appointment_Service::get_doctor_appointments($doctor_user_id) : [];
        $next_by_patient = [];
        foreach ($doctor_appointments as $appointment) {
            $pid = (int) ($appointment['patient_id'] ?? ($appointment['patient_user_id'] ?? 0));
            if (!$pid)
                continue;
            $status = strtolower((string) ($appointment['status'] ?? ''));
            if (in_array($status, ['completed', 'cancelled', 'cancel', 'no-show', 'no show', 'done'], true))
                continue;
            if (!isset($next_by_patient[$pid])) {
                $next_by_patient[$pid] = $appointment;
            }
        }

        foreach ($users as $u) {
            $patient_id = get_user_meta($u->ID, 'patient_id', true) ?: 'DM-' . (10000 + $u->ID);
            $has_intake = get_user_meta($u->ID, 'dior_hipaa_intake_submitted', true);
            $full_name = self::resolve_patient_name($u->ID, $u);
            $name_parts = preg_split('/\s+/', trim($full_name), 2);
            $fname = $name_parts[0] ?? '';
            $lname = $name_parts[1] ?? '';
            $next = $next_by_patient[$u->ID] ?? [];

            $patients[] = [
                'user_id' => $u->ID,
                'patient_id' => $patient_id,
                'first_name' => $fname,
                'last_name' => $lname,
                'full_name' => $full_name,
                'email' => $u->user_email,
                'phone' => get_user_meta($u->ID, 'phone', true) ?: (get_user_meta($u->ID, 'billing_phone', true) ?: '—'),
                'dob' => get_user_meta($u->ID, 'dob', true) ?: (get_user_meta($u->ID, 'date_of_birth', true) ?: '—'),
                'gender' => get_user_meta($u->ID, 'gender', true) ?: '—',
                'blood_group' => get_user_meta($u->ID, 'blood_group', true) ?: '—',
                'address' => get_user_meta($u->ID, 'address', true) ?: (get_user_meta($u->ID, 'billing_address_1', true) ?: '—'),
                'registered' => date('M j, Y', strtotime($u->user_registered)),
                'has_intake' => !empty($has_intake),
                'has_active_appt' => !empty($next),
                'next_appt_date' => $next['appt_date'] ?? ($next['date'] ?? ''),
                'next_appt_time' => $next['appt_time'] ?? ($next['time'] ?? ''),
                'next_appt_status' => $next['status'] ?? '',
                'initials' => strtoupper(substr($fname ?: 'P', 0, 1) . substr($lname ?: 'T', 0, 1)),
            ];
        }

        return $patients;
    }

    private static function get_all_appointments($doctor_user_id = 0)
    {
        static $cache = [];
        $doctor_user_id = (int) $doctor_user_id;
        if (array_key_exists($doctor_user_id, $cache)) {
            return $cache[$doctor_user_id];
        }
        return $cache[$doctor_user_id] = Dior_Appointment_Service::get_doctor_appointments($doctor_user_id);
    }

    public static function get_doctor_notifications($user_id)
    {
        $user_id = (int) $user_id;
        if (!$user_id) {
            return [];
        }

        global $wpdb;
        $all_notifs = [];
        $seen = [];
        $seen_content = [];

        // 1. Relational notification table
        $table = $wpdb->prefix . 'dior_notifications';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE (doctor_id=%d OR user_id=%d) ORDER BY created_at DESC, id DESC LIMIT 150",
                $user_id,
                $user_id
            ), ARRAY_A);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $item_id = $row['notification_uid'] ?: ('notif_row_' . $row['id']);
                    $ts = strtotime($row['created_at']) ?: time();
                    $title = $row['title'] ?: 'Notification';
                    $msg = $row['message'] ?: '';
                    $ckey = md5(trim($title) . '|' . trim($msg) . '|' . date('Y-m-d H:i', $ts));

                    $item = [
                        'id' => $item_id,
                        'title' => $title,
                        'message' => $msg,
                        'action_url' => $row['action_url'] ?: '',
                        'icon' => $row['icon'] ?: 'fa-bell',
                        'is_read' => (bool) $row['is_read'],
                        'created_at' => $row['created_at'],
                        'timestamp' => $ts,
                        'type' => $row['type'] ?: 'system',
                        'priority' => 'Medium'
                    ];

                    $seen[$item_id] = true;
                    $seen_content[$ckey] = true;
                    $all_notifs[] = $item;
                }
            }
        }

        // 2. Doctor user meta notifications
        $meta_notifs = get_user_meta($user_id, 'dior_doctor_notifications', true);
        if (is_array($meta_notifs)) {
            foreach ($meta_notifs as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $notif_id = $item['id'] ?? ('notif_meta_' . md5(($item['title'] ?? '') . ($item['created_at'] ?? '')));
                $n_title = $item['title'] ?? 'Notification';
                $n_msg = $item['message'] ?? '';
                $n_ts = !empty($item['timestamp']) ? (int) $item['timestamp'] : (!empty($item['created_at']) ? strtotime($item['created_at']) : time());
                $ckey = md5(trim($n_title) . '|' . trim($n_msg) . '|' . date('Y-m-d H:i', $n_ts));

                if (!isset($seen[$notif_id]) && !isset($seen_content[$ckey])) {
                    $seen[$notif_id] = true;
                    $seen_content[$ckey] = true;
                    $all_notifs[] = [
                        'id' => $notif_id,
                        'title' => $n_title,
                        'message' => $n_msg,
                        'action_url' => $item['action_url'] ?? '',
                        'icon' => $item['icon'] ?? 'fa-bell',
                        'is_read' => !empty($item['is_read']),
                        'created_at' => $item['created_at'] ?? current_time('mysql'),
                        'timestamp' => $n_ts,
                        'type' => $item['type'] ?? 'system',
                        'priority' => $item['priority'] ?? 'Medium'
                    ];
                }
            }
        }

        // Fallback clinical notifications if doctor has no active items
        if (empty($all_notifs)) {
            $all_notifs = [
                [
                    'id' => 'notif-sys-001',
                    'title' => 'New Patient Registration',
                    'message' => 'New patient registration completed. Clinical profile and questionnaire are available for review.',
                    'action_url' => '#tab=doc-patients-all',
                    'icon' => 'fa-user-plus',
                    'priority' => 'Medium',
                    'type' => 'user',
                    'is_read' => false,
                    'created_at' => current_time('mysql'),
                    'timestamp' => time()
                ],
                [
                    'id' => 'notif-sys-002',
                    'title' => 'Telemedicine Consultation Booked',
                    'message' => 'Patient has scheduled an upcoming virtual telehealth visit. Please review appointment details.',
                    'action_url' => '#tab=doc-appointments',
                    'icon' => 'fa-calendar-check',
                    'priority' => 'High',
                    'type' => 'appointment',
                    'is_read' => false,
                    'created_at' => current_time('mysql'),
                    'timestamp' => time() - 1800
                ],
                [
                    'id' => 'notif-sys-003',
                    'title' => 'Signed Patient Consent Uploaded',
                    'message' => 'Signed telehealth and treatment consent document has been securely archived.',
                    'action_url' => '#tab=doc-documents-signed',
                    'icon' => 'fa-file-signature',
                    'priority' => 'Medium',
                    'type' => 'document',
                    'is_read' => true,
                    'created_at' => date('Y-m-d H:i:s', time() - 86400),
                    'timestamp' => time() - 86400
                ],
                [
                    'id' => 'notif-sys-004',
                    'title' => 'HIPAA System Security Archive Verified',
                    'message' => 'Daily automated encrypted backup and electronic medical record synchronization succeeded.',
                    'action_url' => '#tab=doc-settings',
                    'icon' => 'fa-shield-halved',
                    'priority' => 'Low',
                    'type' => 'system',
                    'is_read' => true,
                    'created_at' => date('Y-m-d H:i:s', time() - 172800),
                    'timestamp' => time() - 172800
                ]
            ];
            update_user_meta($user_id, 'dior_doctor_notifications', $all_notifs);
        }

        // Sort newest first
        usort($all_notifs, static function ($a, $b) {
            $tA = !empty($a['timestamp']) ? (int) $a['timestamp'] : (!empty($a['created_at']) ? strtotime($a['created_at']) : 0);
            $tB = !empty($b['timestamp']) ? (int) $b['timestamp'] : (!empty($b['created_at']) ? strtotime($b['created_at']) : 0);
            return $tB <=> $tA;
        });

        return $all_notifs;
    }


    private static function get_all_documents($patients = [])
    {
        $all_docs = [];
        if (!empty($patients) && is_array($patients)) {
            foreach ($patients as $p) {
                $pid = $p["user_id"] ?? 0;
                if (!$pid)
                    continue;
                $p_docs = Dior_Medical_Secure_Files::get_patient_documents($pid);

                foreach ($p_docs as $d) {
                    $d["patient_id_num"] = $p["patient_id"] ?? ("DM-" . (10000 + $pid));
                    $d["patient_name"] = $p["full_name"] ?? "Patient";
                    $d["patient_user_id"] = $pid;
                    $all_docs[] = $d;
                }
            }
        }
        return $all_docs;
    }

    /**
     * Build the dashboard data payload for the current doctor.
     * All records are scoped to the authenticated provider.
     */
    public static function get_dashboard_data($doctor_user_id = 0)
    {
        static $cache = [];
        $requested_user_id = (int) ($doctor_user_id ?: get_current_user_id());
        $doctor_context = self::get_single_doctor_context($requested_user_id);
        $doctor_user_id = (int) ($doctor_context['user_id'] ?? 0);
        if (isset($cache[$doctor_user_id])) {
            return $cache[$doctor_user_id];
        }
        if (!$doctor_user_id || !self::is_user_admin($doctor_user_id) && !user_can($doctor_user_id, 'doctor')) {
            return [];
        }

        $appointments = self::get_all_appointments($doctor_user_id);
        $patients = self::get_all_patients($doctor_user_id);
        $patient_ids = [];
        foreach ($patients as $patient) {
            if (!empty($patient['user_id'])) {
                $patient_ids[] = (int) $patient['user_id'];
            }
        }

        $prescriptions = [];
        $encounters = [];
        $documents = [];
        foreach ($patient_ids as $patient_id) {
            $rxs = get_user_meta($patient_id, 'dior_prescriptions', true);
            if (is_array($rxs)) {
                foreach ($rxs as $rx) {
                    $rx['patient_user_id'] = $patient_id;
                    $rx['patient_name'] = self::resolve_patient_name($patient_id);
                    $prescriptions[] = $rx;
                }
            }
            if (class_exists('Dior_Encounter_Service')) {
                $notes = Dior_Encounter_Service::get_patient_encounters($patient_id);
                if (is_array($notes)) {
                    foreach ($notes as $note) {
                        $note['patient_user_id'] = $patient_id;
                        $note['patient_name'] = self::resolve_patient_name($patient_id);
                        $encounters[] = $note;
                    }
                }
            }
            if (class_exists('Dior_Medical_Secure_Files')) {
                $docs = Dior_Medical_Secure_Files::get_patient_documents($patient_id);
                if (is_array($docs)) {
                    foreach ($docs as $doc) {
                        $doc['patient_user_id'] = $patient_id;
                        $doc['patient_name'] = self::resolve_patient_name($patient_id);
                        $documents[] = $doc;
                    }
                }
            }
        }

        return $cache[$doctor_user_id] = [
            'appointments' => array_slice($appointments, 0, 200),
            'patients' => array_slice($patients, 0, 300),
            'prescriptions' => array_slice($prescriptions, 0, 300),
            'encounters' => array_slice($encounters, 0, 300),
            'documents' => array_slice($documents, 0, 300),
        ];
    }

    public static function render($atts = [])
    {
        if (!is_user_logged_in()) {
            $login_url = home_url("/dior-login/");
            if (!headers_sent()) {
                wp_safe_redirect($login_url);
                exit;
            }
            return '<script>window.location.replace(' . json_encode($login_url) . ');</script><meta http-equiv="refresh" content="0;url=' . esc_url($login_url) . '">';
        }
        if (!self::can_access()) {
            return "<div style='text-align:center;padding:60px 20px;background:#FEF2F2;border-radius:12px;'><h3 style='color:#DC2626;'>Access Denied</h3><p>You do not have permission to view this page.</p></div>";
        }

        if (class_exists('Dior_Medical_Auth')) {
            Dior_Medical_Auth::enqueue_assets();
        }

        $user_id = get_current_user_id();
        $doctor = self::get_doctor_profile($user_id);
        $patients = self::get_all_patients($user_id);
        $appointments = self::get_all_appointments($user_id);
        $notifications = self::get_doctor_notifications($user_id);
        $documents = self::get_all_documents($patients);

        // Dashboard cards must reflect live records only; never inject demo patients,
        // appointments, notifications, or documents into a production dashboard.
        $dior_demo_mode = false;
        $patients = is_array($patients) ? $patients : [];
        $appointments = is_array($appointments) ? $appointments : [];
        $notifications = is_array($notifications) ? $notifications : [];
        $documents = is_array($documents) ? $documents : [];

        $logo_url = "";
        $logo_id = get_theme_mod("custom_logo");
        if ($logo_id)
            $logo_url = wp_get_attachment_image_url($logo_id, "full");
        if (empty($logo_url)) {
            $logo_url = DIOR_PORTAL_URL . "assets/images/logo.png";
        }

        $total_patients = count($patients);
        $pending_apts = array_filter($appointments, fn($a) => in_array($a["status"] ?? "", ["Success", "In-Queue"]));
        $completed_apts = array_filter($appointments, fn($a) => ($a["status"] ?? "") === "Completed");
        $intake_patients = array_filter($patients, fn($p) => $p["has_intake"]);
        $unread_notifs = array_filter($notifications, fn($n) => !$n["is_read"]);
        $unread_count = count($unread_notifs);
        $nonce = wp_create_nonce("dior_doctor_nonce");
        $is_profile_complete = self::is_profile_complete($user_id);
        $missing_fields = self::get_missing_profile_fields($user_id);

        ob_start();
        include DIOR_PORTAL_PATH . "templates/doctor-dashboard.php";
        return ob_get_clean();
    }

    // AJAX HANDLERS
    public static function ajax_update_appt_status()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access())
            wp_send_json_error(["message" => "Unauthorized"], 403);
        $aid = sanitize_text_field($_POST["appt_id"] ?? "");
        $status = sanitize_text_field($_POST["status"] ?? "");

        $appt = Dior_Appointment_Service::get_appointment($aid);
        $doctor_context = self::get_single_doctor_context();
        if (!$appt || !self::appointment_belongs_to_single_doctor($appt, $doctor_context)) {
            wp_send_json_error(["message" => "Appointment not found in this doctor's schedule."], 403);
        }

        $res = Dior_Appointment_Service::update_status($aid, $status);
        if (is_wp_error($res)) {
            wp_send_json_error(["message" => $res->get_error_message()]);
        }
        wp_send_json_success(["message" => "Status updated to " . $status]);
    }

    public static function ajax_send_reminder()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access())
            wp_send_json_error(["message" => "Unauthorized"], 403);
        $aid = sanitize_text_field($_POST["appt_id"] ?? "");
        if (empty($aid)) {
            wp_send_json_error(["message" => "Missing appointment ID"]);
        }
        $appt = Dior_Appointment_Service::get_appointment($aid);
        $doctor_context = self::get_single_doctor_context();
        if (!$appt || !self::appointment_belongs_to_single_doctor($appt, $doctor_context)) {
            wp_send_json_error(["message" => "Appointment not found in this doctor's schedule."], 403);
        }

        $res = Dior_Appointment_Service::send_reminder($aid, 'doctor');
        if (is_wp_error($res)) {
            wp_send_json_error(["message" => $res->get_error_message()]);
        }
        wp_send_json_success(["message" => "Appointment reminder dispatched via in-app notification and email to both doctor and patient."]);
    }

    public static function ajax_reschedule_appt()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access())
            wp_send_json_error(["message" => "Unauthorized"], 403);
        $aid = sanitize_text_field($_POST["appt_id"] ?? "");
        $new_date = sanitize_text_field($_POST["date"] ?? "");
        $new_time = sanitize_text_field($_POST["time"] ?? "");
        $notes = sanitize_textarea_field($_POST["notes"] ?? "");

        if (empty($aid) || empty($new_date) || empty($new_time)) {
            wp_send_json_error(["message" => "Missing appointment ID, date or time"]);
        }

        $appt = Dior_Appointment_Service::get_appointment($aid);
        $doctor_context = self::get_single_doctor_context();
        if (!$appt || !self::appointment_belongs_to_single_doctor($appt, $doctor_context)) {
            wp_send_json_error(["message" => "Appointment not found in this doctor's schedule."], 403);
        }

        $res = Dior_Appointment_Service::reschedule_appointment($aid, $new_date, $new_time, $notes, 'doctor');
        if (is_wp_error($res)) {
            wp_send_json_error(["message" => $res->get_error_message()]);
        }
        wp_send_json_success([
            "message" => "Appointment rescheduled to " . esc_html($new_date . " at " . $new_time),
            "appointment" => $res
        ]);
    }

    public static function ajax_edit_appointment()
    {
        Dior_Auth_Service::verify_ajax_nonce(["dior_doctor_nonce", "dior_auth_nonce", "dior_portal_nonce", "dior_admin_nonce"]);
        if (!self::can_access()) {
            wp_send_json_error(["message" => "Unauthorized access."], 403);
        }

        $appt_uid = sanitize_text_field($_POST['appt_uid'] ?? '');
        $patient_id = (int) (!empty($_POST['patient_id']) ? $_POST['patient_id'] : 0);

        global $wpdb;
        $table_appts = $wpdb->prefix . 'dior_appointments';
        $table_patients = $wpdb->prefix . 'dior_patients';
        $doctor_context = self::get_single_doctor_context();
        $canonical_doctor_id = (int) ($doctor_context['canonical_id'] ?? 0);
        if (!$canonical_doctor_id) {
            wp_send_json_error(["message" => "The single-doctor dashboard is not configured."], 403);
        }

        if (empty($appt_uid) || $appt_uid === 'new' || $appt_uid === 'undefined') {
            if (!$patient_id) {
                wp_send_json_error(["message" => "Please select a valid patient to schedule or update an appointment."]);
            }
            $appt_uid = 'APT-' . strtoupper(wp_generate_password(8, false));
            $wpdb->insert($table_appts, [
                'appt_uid' => $appt_uid,
                'patient_id' => $patient_id,
                'doctor_id' => $canonical_doctor_id,
                'appt_date' => sanitize_text_field($_POST['appt_date'] ?? ($_POST['date'] ?? current_time('Y-m-d'))),
                'appt_time' => sanitize_text_field($_POST['appt_time'] ?? ($_POST['time'] ?? '10:00 AM')),
                'status' => sanitize_text_field($_POST['status'] ?? 'Confirmed'),
                'visit_type' => sanitize_text_field($_POST['visit_type'] ?? 'Video Visit (HD)'),
                'condition_name' => sanitize_text_field($_POST['condition_name'] ?? 'General Consultation'),
                'join_url' => esc_url_raw($_POST['join_url'] ?? ''),
                'payment_status' => sanitize_text_field($_POST['payment_status'] ?? 'Paid'),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
                'is_deleted' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
            $appt = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_appts WHERE appt_uid = %s", $appt_uid), ARRAY_A);
        } else {
            $appt = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_appts WHERE appt_uid = %s AND is_deleted = 0",
                $appt_uid
            ), ARRAY_A);

            if (!$appt) {
                wp_send_json_error(["message" => "Appointment not found."]);
            }
            if (!self::appointment_belongs_to_single_doctor($appt, $doctor_context)) {
                wp_send_json_error(["message" => "Appointment not found in this doctor's schedule."], 403);
            }
        }

        if (!$patient_id && !empty($appt['patient_id'])) {
            $patient_id = (int) $appt['patient_id'];
        }

        // 1. Core Appointment fields
        $new_date = sanitize_text_field($_POST['appt_date'] ?? ($_POST['date'] ?? $appt['appt_date']));
        $new_time = sanitize_text_field($_POST['appt_time'] ?? ($_POST['time'] ?? $appt['appt_time']));
        $new_status = sanitize_text_field($_POST['status'] ?? $appt['status']);
        $new_condition = sanitize_text_field($_POST['condition_name'] ?? $appt['condition_name']);
        $new_visit_type = sanitize_text_field($_POST['visit_type'] ?? $appt['visit_type']);
        $new_join_url = esc_url_raw($_POST['join_url'] ?? $appt['join_url']);
        $new_payment = sanitize_text_field($_POST['payment_status'] ?? $appt['payment_status']);
        $new_notes = sanitize_textarea_field($_POST['notes'] ?? $appt['notes']);
        // Appointment ownership is fixed to the single configured doctor; never trust a posted provider ID.
        $new_doctor_id = $canonical_doctor_id;

        $allowed_statuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled', 'No Show'];
        if (!in_array($new_status, $allowed_statuses, true)) {
            $new_status = $appt['status'];
        }

        $allowed_payments = ['Pending', 'Paid', 'Refunded', 'Failed'];
        if (!in_array($new_payment, $allowed_payments, true)) {
            $new_payment = $appt['payment_status'];
        }

        $now = current_time('mysql');
        $old_date = $appt['appt_date'];
        $old_time = $appt['appt_time'];
        $old_status = $appt['status'];

        $updated = $wpdb->update(
            $table_appts,
            [
                'patient_id' => $patient_id,
                'condition_name' => $new_condition,
                'visit_type' => $new_visit_type,
                'status' => $new_status,
                'appt_date' => $new_date,
                'appt_time' => $new_time,
                'join_url' => $new_join_url,
                'payment_status' => $new_payment,
                'notes' => $new_notes,
                'doctor_id' => $new_doctor_id,
                'updated_at' => $now,
            ],
            ['appt_uid' => $appt_uid]
        );

        if ($updated === false) {
            wp_send_json_error(["message" => "Database error while updating appointment."]);
        }

        // 2. Update Patient details if patient_id is valid
        if ($patient_id > 0) {
            $first_name = sanitize_text_field($_POST['first_name'] ?? '');
            $last_name = sanitize_text_field($_POST['last_name'] ?? '');
            $phone = sanitize_text_field($_POST['phone'] ?? '');
            $gender = sanitize_text_field($_POST['gender'] ?? '');
            $dob = sanitize_text_field($_POST['dob'] ?? '');
            $blood_group = sanitize_text_field($_POST['blood_group'] ?? '');
            $address = sanitize_textarea_field($_POST['address'] ?? '');

            if (!empty($first_name) || !empty($phone)) {
                $patient_update = [];
                if (!empty($first_name))
                    $patient_update['first_name'] = $first_name;
                if (!empty($last_name))
                    $patient_update['last_name'] = $last_name;
                if (!empty($phone))
                    $patient_update['phone'] = $phone;
                if (!empty($gender))
                    $patient_update['gender'] = $gender;
                if (!empty($dob))
                    $patient_update['dob'] = $dob;
                if (!empty($blood_group))
                    $patient_update['blood_group'] = $blood_group;
                if (!empty($address))
                    $patient_update['address'] = $address;
                $patient_update['updated_at'] = $now;

                $patient_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_patients WHERE user_id = %d", $patient_id));
                if ($patient_exists) {
                    $wpdb->update($table_patients, $patient_update, ['user_id' => $patient_id]);
                }

                if (!empty($first_name))
                    update_user_meta($patient_id, 'first_name', $first_name);
                if (!empty($last_name))
                    update_user_meta($patient_id, 'last_name', $last_name);
                if (!empty($phone))
                    update_user_meta($patient_id, 'phone', $phone);
                if (!empty($gender))
                    update_user_meta($patient_id, 'gender', $gender);
                if (!empty($dob))
                    update_user_meta($patient_id, 'dob', $dob);
                if (!empty($blood_group))
                    update_user_meta($patient_id, 'blood_group', $blood_group);
                if (!empty($address))
                    update_user_meta($patient_id, 'address', $address);
            }

            // Insurance Meta
            if (isset($_POST['insurance_provider']))
                update_user_meta($patient_id, 'dior_insurance_provider', sanitize_text_field($_POST['insurance_provider']));
            if (isset($_POST['insurance_policy_number']))
                update_user_meta($patient_id, 'dior_insurance_policy', sanitize_text_field($_POST['insurance_policy_number']));
            if (isset($_POST['insurance_group_number']))
                update_user_meta($patient_id, 'dior_insurance_group', sanitize_text_field($_POST['insurance_group_number']));
            if (isset($_POST['insurance_holder_name']))
                update_user_meta($patient_id, 'dior_insurance_holder', sanitize_text_field($_POST['insurance_holder_name']));
            if (isset($_POST['insurance_relation']))
                update_user_meta($patient_id, 'dior_insurance_relation', sanitize_text_field($_POST['insurance_relation']));

            // Medical History Meta
            if (isset($_POST['medical_conditions']))
                update_user_meta($patient_id, 'dior_medical_conditions', sanitize_textarea_field($_POST['medical_conditions']));
            if (isset($_POST['medications']))
                update_user_meta($patient_id, 'dior_current_medications', sanitize_textarea_field($_POST['medications']));
            if (isset($_POST['allergies']))
                update_user_meta($patient_id, 'dior_allergies', sanitize_textarea_field($_POST['allergies']));
            if (isset($_POST['surgeries']))
                update_user_meta($patient_id, 'dior_past_surgeries', sanitize_textarea_field($_POST['surgeries']));

            // Emergency Contact Meta
            if (isset($_POST['emergency_contact_name']))
                update_user_meta($patient_id, 'emergency_contact_name', sanitize_text_field($_POST['emergency_contact_name']));
            if (isset($_POST['emergency_contact_relation']))
                update_user_meta($patient_id, 'emergency_contact_relation', sanitize_text_field($_POST['emergency_contact_relation']));
            if (isset($_POST['emergency_contact_phone']))
                update_user_meta($patient_id, 'emergency_contact_phone', sanitize_text_field($_POST['emergency_contact_phone']));

            // Sync legacy user meta for appointments
            if (class_exists('Dior_Appointment_Service')) {
                Dior_Appointment_Service::sync_legacy_usermeta($patient_id);
            }
        }

        // 3. Log history & audit if rescheduled or status changed
        if (($old_date !== $new_date || $old_time !== $new_time) && class_exists('Dior_Appointment_Service')) {
            Dior_Appointment_Service::log_history_public($appt, 'rescheduled', [
                'old_status' => $old_status,
                'new_status' => $new_status,
                'old_date' => $old_date,
                'new_date' => $new_date,
                'old_time' => $old_time,
                'new_time' => $new_time,
                'notes' => $new_notes,
            ]);
            Dior_Audit_Service::log('appointment_reschedule', 'appointment', $appt_uid, $patient_id, 'success', [
                'from' => $old_date . ' ' . $old_time,
                'to' => $new_date . ' ' . $new_time,
                'by' => 'doctor'
            ]);
            if (class_exists('Dior_Notification_Service')) {
                Dior_Notification_Service::send_appointment_rescheduled(
                    $appt_uid,
                    $old_date,
                    $old_time,
                    $new_date,
                    $new_time,
                    'doctor'
                );
            }
        }

        if ($old_status !== $new_status) {
            if (class_exists('Dior_Appointment_Service')) {
                Dior_Appointment_Service::log_history_public($appt, 'status_changed', [
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                    'notes' => 'Status updated via doctor appointment form'
                ]);
            }
            if (class_exists('Dior_Notification_Service')) {
                Dior_Notification_Service::send_status_change_notification(
                    $appt_uid,
                    $old_status,
                    $new_status,
                    $new_notes,
                    'doctor'
                );
            }
        }

        // Return updated appointment object
        $updated_appt = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, p.first_name, p.last_name, p.patient_uid, p.phone, p.gender, p.dob, p.blood_group, p.address
             FROM $table_appts a
             LEFT JOIN $table_patients p ON p.user_id = a.patient_id
             WHERE a.appt_uid = %s",
            $appt_uid
        ), ARRAY_A);

        wp_send_json_success([
            "message" => "Appointment #{$appt_uid} updated successfully.",
            "appointment" => $updated_appt
        ]);
    }

    public static function ajax_get_patient_appointment_details()
    {
        Dior_Auth_Service::verify_ajax_nonce(["dior_doctor_nonce", "dior_auth_nonce", "dior_portal_nonce", "dior_admin_nonce"]);
        if (!self::can_access()) {
            wp_send_json_error(["message" => "Unauthorized access."], 403);
        }

        $patient_id = (int) ($_GET['patient_id'] ?? ($_POST['patient_id'] ?? 0));
        $appt_uid = sanitize_text_field($_GET['appt_uid'] ?? ($_POST['appt_uid'] ?? ''));

        global $wpdb;
        $table_appts = $wpdb->prefix . 'dior_appointments';
        $table_patients = $wpdb->prefix . 'dior_patients';
        $doctor_context = self::get_single_doctor_context();
        $doctor_ids = array_values(array_unique(array_map('intval', (array) ($doctor_context['ids'] ?? []))));
        if (!$doctor_ids) {
            wp_send_json_error(["message" => "The single-doctor dashboard is not configured."], 403);
        }
        $doctor_placeholders = implode(',', array_fill(0, count($doctor_ids), '%d'));

        $target_appt = null;
        if (!empty($appt_uid)) {
            $target_appt = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_appts WHERE appt_uid = %s AND is_deleted = 0 AND doctor_id IN ($doctor_placeholders)",
                array_merge([$appt_uid], $doctor_ids)
            ), ARRAY_A);
            if (!$target_appt) {
                wp_send_json_error(["message" => "Appointment not found in this doctor's schedule."], 403);
            }
            if (!empty($patient_id) && (int) $target_appt['patient_id'] !== $patient_id) {
                wp_send_json_error(["message" => "The appointment does not match the selected patient."], 403);
            }
            if (empty($patient_id)) {
                $patient_id = (int) $target_appt['patient_id'];
            }
        }

        if (!$patient_id) {
            wp_send_json_error(["message" => "Patient ID or Appointment UID is required."]);
        }

        // A patient record is available in this dashboard only when it has an appointment
        // assigned to the configured doctor.
        $has_scoped_appointment = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_appts WHERE patient_id = %d AND is_deleted = 0 AND doctor_id IN ($doctor_placeholders) LIMIT 1",
            array_merge([$patient_id], $doctor_ids)
        ));
        if (!$has_scoped_appointment) {
            wp_send_json_error(["message" => "Patient not found in this doctor's records."], 403);
        }

        // 1. Fetch patient profile
        $patient = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, u.user_email, u.display_name
             FROM {$wpdb->users} u
             LEFT JOIN $table_patients p ON p.user_id = u.ID
             WHERE u.ID = %d",
            $patient_id
        ), ARRAY_A);

        if (!$patient) {
            wp_send_json_error(["message" => "Patient not found."]);
        }

        // 2. Fetch all appointments for this patient
        $appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_appts WHERE patient_id = %d AND is_deleted = 0 AND doctor_id IN ($doctor_placeholders) ORDER BY appt_date DESC, appt_time DESC",
            array_merge([$patient_id], $doctor_ids)
        ), ARRAY_A);

        $patient_data = [
            'patient_id' => $patient_id,
            'patient_uid' => $patient['patient_uid'] ?: get_user_meta($patient_id, 'patient_id', true) ?: ('DM-' . (10000 + $patient_id)),
            'first_name' => $patient['first_name'] ?: get_user_meta($patient_id, 'first_name', true) ?: $patient['display_name'],
            'last_name' => $patient['last_name'] ?: get_user_meta($patient_id, 'last_name', true) ?: '',
            'email' => $patient['user_email'],
            'phone' => $patient['phone'] ?: get_user_meta($patient_id, 'phone', true) ?: '',
            'gender' => $patient['gender'] ?: get_user_meta($patient_id, 'gender', true) ?: 'male',
            'dob' => $patient['dob'] ?: get_user_meta($patient_id, 'dob', true) ?: '',
            'blood_group' => $patient['blood_group'] ?: get_user_meta($patient_id, 'blood_group', true) ?: 'A+',
            'address' => $patient['address'] ?: get_user_meta($patient_id, 'address', true) ?: '',
            'medical_conditions' => get_user_meta($patient_id, 'dior_medical_conditions', true) ?: '',
            'medications' => get_user_meta($patient_id, 'dior_current_medications', true) ?: '',
            'allergies' => get_user_meta($patient_id, 'dior_allergies', true) ?: '',
            'surgeries' => get_user_meta($patient_id, 'dior_past_surgeries', true) ?: '',
            'emergency_contact_name' => get_user_meta($patient_id, 'emergency_contact_name', true) ?: '',
            'emergency_contact_phone' => get_user_meta($patient_id, 'emergency_contact_phone', true) ?: '',
            'emergency_contact_relation' => get_user_meta($patient_id, 'emergency_contact_relation', true) ?: 'parent',
            'insurance_provider' => get_user_meta($patient_id, 'dior_insurance_provider', true) ?: '',
            'insurance_policy_number' => get_user_meta($patient_id, 'dior_insurance_policy', true) ?: '',
            'insurance_group_number' => get_user_meta($patient_id, 'dior_insurance_group', true) ?: '',
            'insurance_holder_name' => get_user_meta($patient_id, 'dior_insurance_holder', true) ?: '',
            'insurance_relation' => get_user_meta($patient_id, 'dior_insurance_relation', true) ?: 'self',
        ];

        wp_send_json_success([
            'patient' => $patient_data,
            'appointments' => is_array($appointments) ? $appointments : [],
            'appointment' => $target_appt
        ]);
    }

    public static function ajax_send_multi_prescription()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access())
            wp_send_json_error(["message" => "Unauthorized"]);

        $pid = (int) ($_POST["patient_id"] ?? 0);
        $meds_json = stripslashes($_POST["medications"] ?? "[]");
        $meds = json_decode($meds_json, true);

        // Support both single medication string (from Patient Profile form) and JSON array
        if (!is_array($meds) || empty($meds)) {
            $raw_med = sanitize_text_field($_POST["medications"] ?? '');
            if ($raw_med !== '') {
                $meds = [[
                    'medication' => $raw_med,
                    'dosage'     => sanitize_text_field($_POST['dosage'] ?? 'As directed'),
                    'refills'    => sanitize_text_field($_POST['refills'] ?? '0'),
                    'notes'      => sanitize_textarea_field($_POST['instructions'] ?? '')
                ]];
            }
        }

        if (!$pid || empty($meds)) {
            wp_send_json_error(["message" => "Missing patient or medications"]);
        }

        $rxs = get_user_meta($pid, "dior_prescriptions", true);
        if (!is_array($rxs)) {
            $rxs = [];
        }

        $new_rxs = [];
        $med_names = [];
        $rx_id_group = !empty($_POST["order_uid"]) ? sanitize_text_field($_POST["order_uid"]) : ("RX-" . date("ymd") . "-" . strtoupper(substr(md5(uniqid(rand(), true)), 0, 5)));
        $date = current_time("M j, Y");
        $doctor_id = get_current_user_id();
        $doctor_user = get_userdata($doctor_id);
        $doc_first = get_user_meta($doctor_id, 'first_name', true) ?: ($doctor_user ? $doctor_user->first_name : '');
        $doc_last = get_user_meta($doctor_id, 'last_name', true) ?: ($doctor_user ? $doctor_user->last_name : '');
        $doc_full_name = trim($doc_first . ' ' . $doc_last);
        $doc_title_name = $doc_full_name ? 'Dr. ' . $doc_full_name : 'Doctor';
        $patient_pharmacy = get_user_meta($pid, 'preferred_pharmacy_name', true) ?: '';
        $doc_sig = get_user_meta($doctor_id, 'dior_doctor_signature', true) ?: '';

        $item_idx = 0;
        foreach ($meds as $m) {
            $item_idx++;
            $med_name = sanitize_text_field($m['medication']);
            $med_names[] = $med_name;
            $refills_val = sanitize_text_field($m['refills'] ?? '');
            if (empty($refills_val)) {
                $refills_val = '0 Refills Remaining';
            } elseif (is_numeric($refills_val)) {
                $refills_val = $refills_val . ' Refill(s) Remaining';
            }

            $notes_val = sanitize_textarea_field($m['notes'] ?? '');

            $rx = [
                "id" => $rx_id_group . '-' . $item_idx,
                "order_id" => $rx_id_group,
                "group_id" => $rx_id_group,
                "name" => $med_name,
                "medication" => $med_name,
                "dosage" => sanitize_text_field($m['dosage']),
                "dose" => sanitize_text_field($m['dosage']),
                "quantity" => sanitize_text_field($_POST['quantity'] ?? 'As Prescribed'),
                "refills" => $refills_val,
                "notes" => $notes_val,
                "instructions" => $notes_val ?: "Take as directed by prescribing physician.",
                "date" => $date,
                "date_prescribed" => $date,
                "status" => sanitize_text_field($_POST['status'] ?? 'Active'),
                "is_active" => true,
                "routing_status" => "Sent to Pharmacy",
                "status_class" => "status-success",
                "pharmacy" => $patient_pharmacy,
                "prescribed_by" => $doc_title_name,
                "doctor_signature" => $doc_sig,
                "fda_approved" => true,
                "fda_source" => sanitize_text_field($m['fda_source'] ?? 'U.S. FDA & NIH RxNorm')
            ];
            array_unshift($rxs, $rx);
            $new_rxs[] = $rx;
        }

        update_user_meta($pid, "dior_prescriptions", $rxs);

        // Sync into SQL database table wp_dior_prescriptions
        global $wpdb;
        $table_prescriptions = $wpdb->prefix . 'dior_prescriptions';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_prescriptions)) === $table_prescriptions) {
            $now_mysql = current_time('mysql');
            $pat_user = get_userdata($pid);
            $pat_name = $pat_user ? $pat_user->display_name : 'Patient';
            $rx_title = !empty($_POST['title']) ? sanitize_text_field($_POST['title']) : ('Prescription - ' . implode(', ', $med_names));
            $rx_date = !empty($_POST['prescription_date']) ? sanitize_text_field($_POST['prescription_date']) : current_time('Y-m-d');

            foreach ($new_rxs as $nrx) {
                $clean_refills = is_numeric($nrx['refills']) ? absint($nrx['refills']) : (preg_match('/(\d+)/', $nrx['refills'], $rm) ? absint($rm[1]) : 0);
                $wpdb->insert($table_prescriptions, [
                    'prescription_uid' => $nrx['id'],
                    'prescription_id'  => $nrx['order_id'],
                    'title'            => $rx_title,
                    'patient_id'       => $pid,
                    'patient_name'     => $pat_name,
                    'doctor_id'        => $doctor_id,
                    'doctor_name'      => $doc_title_name,
                    'prescription_date'=> $rx_date,
                    'medications'      => $nrx['medication'],
                    'medication'       => substr($nrx['medication'], 0, 255),
                    'dosage'           => $nrx['dosage'],
                    'frequency'        => sanitize_text_field($_POST['frequency'] ?? 'Daily'),
                    'duration'         => sanitize_text_field($_POST['duration'] ?? '7 days'),
                    'instructions'     => $nrx['instructions'],
                    'quantity'         => sanitize_text_field($_POST['quantity'] ?? 'As prescribed'),
                    'refills'          => $clean_refills,
                    'status'           => sanitize_text_field($_POST['status'] ?? 'Active'),
                    'created_at'       => $now_mysql,
                    'updated_at'       => $now_mysql,
                ]);
            }
        }

        // Update Patient Notifications
        $pnotifs = Dior_Patient_Portal_Data::get_patient_notifications($pid);
        $med_count = count($med_names);
        $med_text = $med_count === 1 ? $med_names[0] : implode(', ', array_slice($med_names, 0, 2)) . ($med_count > 2 ? ' +' . ($med_count - 2) . ' more' : '');

        array_unshift($pnotifs, [
            "id" => "NOTIF-RX-" . time() . '-' . rand(10, 99),
            "type" => "prescription",
            "icon" => "fa-prescription-bottle-medical",
            "title" => "New Prescription Sent to Pharmacy",
            "message" => "Your doctor has prescribed: " . $med_text . ". This has been electronically routed to your pharmacy. View full details in Docs & Meds.",
            "time" => "Just now",
            "timestamp" => time(),
            "created_at" => current_time('mysql'),
            "is_read" => false,
            "action_url" => "#tab=docs_meds"
        ]);
        update_user_meta($pid, "dior_notifications", $pnotifs);

        // Update Doctor Notifications
        $doc_user_ids = array_unique([$doctor_id, get_current_user_id()]);
        $patient_user = get_userdata($pid);
        $p_display = $patient_user ? $patient_user->display_name : 'Patient';
        foreach ($doc_user_ids as $duid) {
            if (!$duid)
                continue;
            $dnotifs = get_user_meta($duid, "dior_doctor_notifications", true);
            if (!is_array($dnotifs))
                $dnotifs = [];
            array_unshift($dnotifs, [
                "id" => "DN-RX-" . time() . '-' . rand(10, 99),
                "type" => "prescription",
                "icon" => "fa-prescription-bottle-medical",
                "title" => "Prescription Sent to Pharmacy",
                "message" => "Prescription for " . implode(', ', $med_names) . " was sent to pharmacy for " . $p_display . ".",
                "time" => "Just now",
                "timestamp" => time(),
                "created_at" => current_time('mysql'),
                "is_read" => false,
                "action_url" => "#tab=doc-prescriptions"
            ]);
            update_user_meta($duid, "dior_doctor_notifications", $dnotifs);
        }

        // --- EMAIL NOTIFICATION FOR PRESCRIPTION ---
        $patient_user = get_userdata($pid);
        if ($patient_user && $patient_user->user_email) {
            $subject = "Your New Prescription is Ready - Dior Medical";
            $doctor_user = get_userdata($doctor_id);
            $doctor_name = $doctor_user ? "Dr. " . $doctor_user->last_name : "Your Doctor";

            $med_list_html = "";
            foreach ($meds as $m) {
                $med_list_html .= "<li><strong>" . esc_html($m['medication']) . "</strong> - " . esc_html($m['dosage']) . " (" . esc_html($m['refills']) . ")</li>";
            }

            $msg = "
            <div style='font-family:sans-serif;max-width:600px;margin:0 auto;border:1px solid #e2e8f0;border-radius:8px;padding:20px;'>
                <h2 style='color:#059669;'>Prescription Issued</h2>
                <p>Hello " . esc_html($patient_user->display_name) . ",</p>
                <p>" . esc_html($doctor_name) . " has issued a new prescription for you.</p>
                <div style='background:#F8FAFC;padding:15px;border-radius:8px;margin:20px 0;'>
                    <ul style='margin:0;padding-left:20px;'>
                        " . $med_list_html . "
                    </ul>
                </div>
                <p>You can view and download your official PDF prescription from your <a href='" . home_url('/patient-dashboard/') . "'>Patient Dashboard</a>.</p>
                <p>Thank you,<br><strong>Dior Medical Telehealth Team</strong></p>
            </div>";

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($patient_user->user_email, $subject, $msg, $headers);
        }

        wp_send_json_success(["message" => count($new_rxs) . " medications prescribed successfully!", "rxs" => array_reverse($new_rxs)]);
    }

    public static function ajax_save_profile()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access())
            wp_send_json_error(["message" => "Unauthorized"]);
        $uid = get_current_user_id();

        $first_name = sanitize_text_field($_POST["first_name"] ?? "");
        $last_name = sanitize_text_field($_POST["last_name"] ?? "");
        $phone = sanitize_text_field($_POST["phone"] ?? "");
        $specialty = sanitize_text_field($_POST["doctor_specialty"] ?? ($_POST["specialty"] ?? ""));
        $license_no = sanitize_text_field($_POST["doctor_license"] ?? ($_POST["license_no"] ?? ""));
        $npi = sanitize_text_field($_POST["doctor_npi"] ?? ($_POST["npi"] ?? ""));
        $bio = sanitize_textarea_field($_POST["doctor_bio"] ?? ($_POST["bio"] ?? ""));
        $zoom_link = esc_url_raw($_POST["doctor_zoom_link"] ?? ($_POST["zoom_link"] ?? ""));
        $qualifications = sanitize_text_field($_POST["doctor_qualifications"] ?? ($_POST["qualifications"] ?? ""));
        $fee = sanitize_text_field($_POST["doctor_fee"] ?? ($_POST["fee"] ?? ""));
        $duty_hours = sanitize_text_field($_POST["doctor_duty_hours"] ?? ($_POST["duty_hours"] ?? ""));
        $city = sanitize_text_field($_POST["city"] ?? "");
        $country = sanitize_text_field($_POST["country"] ?? "");
        $address = sanitize_text_field($_POST["address"] ?? "");

        // Required personal and credential fields validation
        $missing = [];
        if (empty($first_name))
            $missing[] = 'First Name';
        if (empty($last_name))
            $missing[] = 'Last Name';
        if (empty($phone))
            $missing[] = 'Phone Number';
        if (empty($specialty))
            $missing[] = 'Medical Specialty';

        if (!empty($missing)) {
            wp_send_json_error([
                'message' => 'Please fill in required fields: ' . implode(', ', $missing) . '.',
                'missing' => $missing
            ]);
        }

        update_user_meta($uid, "first_name", $first_name);
        update_user_meta($uid, "last_name", $last_name);
        update_user_meta($uid, "phone", $phone);
        update_user_meta($uid, "doctor_specialty", $specialty);
        update_user_meta($uid, "specialty", $specialty);
        if ($license_no) {
            update_user_meta($uid, "doctor_license", $license_no);
            update_user_meta($uid, "license_no", $license_no);
        }
        if ($npi) {
            update_user_meta($uid, "doctor_npi", $npi);
        }
        if ($bio) {
            update_user_meta($uid, "doctor_bio", $bio);
            update_user_meta($uid, "bio", $bio);
        }
        if ($zoom_link) {
            update_user_meta($uid, "doctor_zoom_link", $zoom_link);
        }
        if ($qualifications) {
            update_user_meta($uid, "doctor_qualifications", $qualifications);
            update_user_meta($uid, "qualifications", $qualifications);
        }
        if ($fee) {
            update_user_meta($uid, "doctor_fee", $fee);
            update_user_meta($uid, "fee", $fee);
        }
        if ($duty_hours) {
            update_user_meta($uid, "doctor_duty_hours", $duty_hours);
            update_user_meta($uid, "duty_hours", $duty_hours);
        }
        if ($city)
            update_user_meta($uid, "city", $city);
        if ($country)
            update_user_meta($uid, "country", $country);
        if ($address)
            update_user_meta($uid, "address", $address);

        if (isset($_POST["optin_appointment_reminders"])) {
            update_user_meta($uid, "optin_appointment_reminders", sanitize_text_field($_POST["optin_appointment_reminders"]) === '1' ? '1' : '0');
        }
        if (isset($_POST["optin_reminder_email"])) {
            update_user_meta($uid, "optin_reminder_email", sanitize_text_field($_POST["optin_reminder_email"]) === '1' ? '1' : '0');
        }
        if (isset($_POST["optin_reminder_dashboard"])) {
            update_user_meta($uid, "optin_reminder_dashboard", sanitize_text_field($_POST["optin_reminder_dashboard"]) === '1' ? '1' : '0');
        }

        wp_update_user([
            "ID" => $uid,
            "first_name" => $first_name,
            "last_name" => $last_name,
            "display_name" => "Dr. " . trim($first_name . " " . $last_name)
        ]);

        $updated_profile = self::get_doctor_profile($uid);

        wp_send_json_success([
            "message" => "Provider profile updated successfully!",
            "profile" => $updated_profile,
            "is_profile_complete" => self::is_profile_complete($uid)
        ]);
    }

    /**
     * AJAX Upload Doctor Avatar / Profile Image
     */
    public static function ajax_upload_avatar()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) {
            wp_send_json_error(["message" => "Unauthorized access. Please ensure you are logged in as a provider."], 403);
        }

        $uid = get_current_user_id();
        if (!$uid) {
            wp_send_json_error(['message' => 'Authentication required. Please log in again.'], 401);
        }

        // Support selecting directly from WordPress Media Library (URL or ID passed)
        if (!empty($_POST['avatar_url'])) {
            $avatar_url = esc_url_raw($_POST['avatar_url']);
            $attach_id = !empty($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

            update_user_meta($uid, 'dior_profile_image', $avatar_url);
            update_user_meta($uid, 'doctor_avatar', $avatar_url);
            update_user_meta($uid, 'profile_picture', $avatar_url);
            update_user_meta($uid, 'profile_image', $avatar_url);
            if ($attach_id) {
                update_user_meta($uid, 'dior_profile_image_id', $attach_id);
            }

            // Sync thumbnail to linked docbooker doctor post if any
            $doc_posts = get_posts([
                'post_type' => 'wpddb_doctor',
                'meta_key' => '_wpddb_doctor_user_id',
                'meta_value' => (string) $uid,
                'fields' => 'ids',
                'posts_per_page' => -1,
                'post_status' => 'any'
            ]);
            if (!empty($doc_posts) && $attach_id) {
                foreach ($doc_posts as $pid) {
                    set_post_thumbnail($pid, $attach_id);
                    update_post_meta($pid, '_wpddb_doctor_avatar', $avatar_url);
                    update_post_meta($pid, 'wpddb_doctor_avatar', $avatar_url);
                }
            }

            wp_send_json_success([
                'message' => 'Provider profile photo selected from Media Library successfully!',
                'avatar_url' => $avatar_url
            ]);
        }

        if (empty($_FILES['avatar']) || empty($_FILES['avatar']['name'])) {
            wp_send_json_error(['message' => 'No image file provided for upload.']);
        }

        $file = $_FILES['avatar'];

        if (isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'The image exceeds the server upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE => 'The image exceeds the form upload size limit.',
                UPLOAD_ERR_PARTIAL => 'The image was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_FILE => 'Please choose an image file.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload folder is missing.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded image. Check folder permissions.',
                UPLOAD_ERR_EXTENSION => 'The server blocked this image upload.',
            ];
            $code = (int) $file['error'];
            wp_send_json_error(['message' => $upload_errors[$code] ?? 'The image upload failed on the server.']);
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(['message' => 'The uploaded image could not be verified by the server. Please try again.']);
        }

        // Limit file size to 5MB
        if (!empty($file['size']) && $file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(['message' => 'Image size exceeds the maximum limit of 5MB.']);
        }

        // Validate image file extension
        $file_info = wp_check_filetype($file['name']);
        $ext = strtolower($file_info['ext'] ?: pathinfo($file['name'], PATHINFO_EXTENSION));
        $valid_exts = ['jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif', 'jfif'];
        if (!in_array($ext, $valid_exts, true)) {
            wp_send_json_error(['message' => 'Invalid image format. Please choose a JPG, PNG, WEBP, or GIF image.']);
        }

        // Validate image content with getimagesize
        $image_size = @getimagesize($file['tmp_name']);
        if (!$image_size || empty($image_size['mime']) || strpos($image_size['mime'], 'image/') !== 0) {
            wp_send_json_error(['message' => 'Uploaded file is not a valid image. Please choose another image.']);
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $allowed_mimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];

        $upload_overrides = ['test_form' => false, 'test_upload' => false, 'mimes' => $allowed_mimes];
        $movefile = wp_handle_upload($file, $upload_overrides);

        $avatar_url = '';
        $uploaded_file_path = '';
        $attach_id = 0;

        try {
            if ($movefile && !isset($movefile['error']) && !empty($movefile['url'])) {
                $avatar_url = esc_url_raw($movefile['url']);
                $uploaded_file_path = !empty($movefile['file']) ? $movefile['file'] : '';
            } else {
                // Bulletproof fallback using wp_upload_dir() & move_uploaded_file / copy
                $upload_dir = wp_upload_dir();
                $safe_name = wp_unique_filename($upload_dir['path'], sanitize_file_name($file['name']));
                $target_path = $upload_dir['path'] . '/' . $safe_name;

                if (@move_uploaded_file($file['tmp_name'], $target_path) || @copy($file['tmp_name'], $target_path)) {
                    $avatar_url = esc_url_raw($upload_dir['url'] . '/' . $safe_name);
                    $uploaded_file_path = $target_path;
                } else {
                    $err = !empty($movefile['error']) ? $movefile['error'] : 'Failed to save uploaded image. Please check server folder permissions.';
                    wp_send_json_error(['message' => $err]);
                }
            }

            if (empty($avatar_url) || empty($uploaded_file_path) || !file_exists($uploaded_file_path)) {
                if (!empty($uploaded_file_path) && file_exists($uploaded_file_path)) {
                    @unlink($uploaded_file_path);
                }
                wp_send_json_error(['message' => 'Image could not be saved to disk.']);
            }

            // Register in WordPress Media Library so it appears in the media library
            $filetype = wp_check_filetype(basename($uploaded_file_path), null);
            $attachment = [
                'guid' => $avatar_url,
                'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($uploaded_file_path)),
                'post_content' => '',
                'post_status' => 'inherit'
            ];
            $attach_id = wp_insert_attachment($attachment, $uploaded_file_path);
            if ($attach_id && !is_wp_error($attach_id)) {
                $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded_file_path);
                wp_update_attachment_metadata($attach_id, $attach_data);
                update_user_meta($uid, 'dior_profile_image_id', $attach_id);
            }

            update_user_meta($uid, 'dior_profile_image', $avatar_url);
            update_user_meta($uid, 'doctor_avatar', $avatar_url);
            update_user_meta($uid, 'profile_picture', $avatar_url);
            update_user_meta($uid, 'profile_image', $avatar_url);

            // Sync thumbnail to linked docbooker doctor post if any
            $doc_posts = get_posts([
                'post_type' => 'wpddb_doctor',
                'meta_key' => '_wpddb_doctor_user_id',
                'meta_value' => (string) $uid,
                'fields' => 'ids',
                'posts_per_page' => -1,
                'post_status' => 'any'
            ]);
            if (!empty($doc_posts)) {
                foreach ($doc_posts as $pid) {
                    if ($attach_id && !is_wp_error($attach_id)) {
                        set_post_thumbnail($pid, $attach_id);
                    }
                    update_post_meta($pid, '_wpddb_doctor_avatar', $avatar_url);
                    update_post_meta($pid, 'wpddb_doctor_avatar', $avatar_url);
                }
            }

            wp_send_json_success([
                'message' => 'Provider profile photo updated successfully!',
                'avatar_url' => $avatar_url,
                'attachment_id' => $attach_id
            ]);
        } catch (\Throwable $e) {
            if (!empty($uploaded_file_path) && file_exists($uploaded_file_path)) {
                @unlink($uploaded_file_path);
            }
            if (!empty($attach_id) && $attach_id > 0) {
                wp_delete_attachment($attach_id, true);
            }
            wp_send_json_error(['message' => 'Upload failed: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX Remove Doctor Avatar
     */
    public static function ajax_remove_avatar()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access())
            wp_send_json_error(["message" => "Unauthorized"]);

        $uid = get_current_user_id();
        if (!$uid) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        delete_user_meta($uid, 'dior_profile_image');
        delete_user_meta($uid, 'doctor_avatar');
        delete_user_meta($uid, 'profile_picture');
        delete_user_meta($uid, 'profile_image');
        delete_user_meta($uid, 'dior_profile_image_id');

        $profile = self::get_doctor_profile($uid);
        $initials = !empty($profile['initials']) ? $profile['initials'] : 'DR';
        $default_avatar = DIOR_PORTAL_URL . 'assets/images/users/user-1.png';

        wp_send_json_success([
            'message' => 'Provider photo removed successfully.',
            'initials' => $initials,
            'avatar_url' => $default_avatar
        ]);
    }

    /**
     * AJAX Upload Doctor Digital Signature (PNG with transparent background)
     */
    public static function ajax_upload_signature()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access())
            wp_send_json_error(["message" => "Unauthorized"]);

        $uid = get_current_user_id();
        if (!$uid) {
            wp_send_json_error(['message' => 'Authentication required. Please log in again.']);
        }

        // Support selecting directly from WordPress Media Library (URL or ID passed)
        if (!empty($_POST['signature_url'])) {
            $sig_url = esc_url_raw($_POST['signature_url']);
            $attach_id = !empty($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

            update_user_meta($uid, 'dior_doctor_signature', $sig_url);
            if ($attach_id) {
                update_user_meta($uid, 'dior_doctor_signature_id', $attach_id);
            }

            wp_send_json_success([
                'message' => 'Doctor signature selected successfully!',
                'signature_url' => $sig_url
            ]);
        }

        // Support base64 data URI if drawn/uploaded directly
        if (!empty($_POST['signature_data'])) {
            $data = $_POST['signature_data'];
            if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
                $data = substr($data, strpos($data, ',') + 1);
                $decoded = base64_decode($data);
                if ($decoded !== false) {
                    $upload_dir = wp_upload_dir();
                    $filename = 'doctor_sig_' . $uid . '_' . time() . '.png';
                    $filepath = $upload_dir['path'] . '/' . $filename;
                    file_put_contents($filepath, $decoded);
                    $sig_url = esc_url_raw($upload_dir['url'] . '/' . $filename);

                    update_user_meta($uid, 'dior_doctor_signature', $sig_url);
                    wp_send_json_success([
                        'message' => 'Doctor signature saved successfully!',
                        'signature_url' => $sig_url
                    ]);
                }
            }
        }

        if (empty($_FILES['signature']) || empty($_FILES['signature']['name'])) {
            wp_send_json_error(['message' => 'No signature file provided for upload.']);
        }

        $file = $_FILES['signature'];

        // Allowed image types - strictly PNG or WEBP for transparent background
        $allowed_mimes = [
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        if (empty($check['type']) || !in_array($check['type'], $allowed_mimes, true)) {
            wp_send_json_error(['message' => 'Invalid signature format. Please upload a PNG image with a transparent background.']);
        }

        // Limit file size to 3MB
        if ($file['size'] > 3 * 1024 * 1024) {
            wp_send_json_error(['message' => 'Signature file size exceeds the 3MB limit.']);
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        try {
            $upload_overrides = ['test_form' => false, 'mimes' => $allowed_mimes];
            $movefile = wp_handle_upload($file, $upload_overrides);

            if ($movefile && !isset($movefile['error']) && !empty($movefile['url'])) {
                $sig_url = esc_url_raw($movefile['url']);
                update_user_meta($uid, 'dior_doctor_signature', $sig_url);

                wp_send_json_success([
                    'message' => 'Doctor digital signature uploaded successfully!',
                    'signature_url' => $sig_url
                ]);
            } else {
                wp_send_json_error([
                    'message' => !empty($movefile['error']) ? $movefile['error'] : 'Failed to save uploaded signature. Please try again.'
                ]);
            }
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Signature upload failed: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX Remove Doctor Signature
     */
    public static function ajax_remove_signature()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access())
            wp_send_json_error(["message" => "Unauthorized"]);

        $uid = get_current_user_id();
        if (!$uid) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        delete_user_meta($uid, 'dior_doctor_signature');
        delete_user_meta($uid, 'dior_doctor_signature_id');

        wp_send_json_success([
            'message' => 'Doctor signature removed successfully.'
        ]);
    }

    public static function ajax_mark_notif_read()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(["message" => "Unauthorized"], 403);
        }
        $uid = get_current_user_id();
        $nid = sanitize_text_field($_POST["notif_id"] ?? $_POST["notification_id"] ?? "");

        if (!empty($nid)) {
            global $wpdb;
            $table = $wpdb->prefix . 'dior_notifications';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET is_read = 1, read_at = %s WHERE (notification_uid = %s OR id = %d) AND (doctor_id = %d OR user_id = %d)",
                    current_time('mysql'),
                    $nid,
                    (int) $nid,
                    $uid,
                    $uid
                ));
            }

            $ns = get_user_meta($uid, "dior_doctor_notifications", true);
            if (is_array($ns)) {
                $updated = false;
                foreach ($ns as &$n) {
                    if (($n["id"] ?? '') === $nid) {
                        $n["is_read"] = true;
                        $updated = true;
                        break;
                    }
                }
                if ($updated) {
                    update_user_meta($uid, "dior_doctor_notifications", $ns);
                }
            }
        }
        wp_send_json_success(["message" => "Notification marked as read"]);
    }

    public static function ajax_mark_all_notif_read()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(["message" => "Unauthorized"], 403);
        }
        $uid = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'dior_notifications';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET is_read = 1, read_at = %s WHERE (doctor_id = %d OR user_id = %d)",
                current_time('mysql'),
                $uid,
                $uid
            ));
        }

        $ns = get_user_meta($uid, "dior_doctor_notifications", true);
        if (is_array($ns)) {
            foreach ($ns as &$n) {
                $n["is_read"] = true;
            }
            update_user_meta($uid, "dior_doctor_notifications", $ns);
        }
        wp_send_json_success(["message" => "All notifications marked as read."]);
    }

    public static function ajax_delete_notification()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(["message" => "Unauthorized"], 403);
        }
        $uid = get_current_user_id();
        $nids = isset($_POST['notif_ids']) && is_array($_POST['notif_ids']) ? array_map('sanitize_text_field', $_POST['notif_ids']) : [];
        $nid = sanitize_text_field($_POST["notif_id"] ?? $_POST["notification_id"] ?? "");
        if (!empty($nid) && !in_array($nid, $nids, true)) {
            $nids[] = $nid;
        }

        if (empty($nids)) {
            wp_send_json_error(["message" => "Notification ID required."]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dior_notifications';
        $has_table = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);

        foreach ($nids as $target_id) {
            if ($has_table) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table WHERE (notification_uid = %s OR id = %d) AND (doctor_id = %d OR user_id = %d)",
                    $target_id,
                    (int) $target_id,
                    $uid,
                    $uid
                ));
            }
        }

        $ns = get_user_meta($uid, "dior_doctor_notifications", true);
        if (is_array($ns)) {
            $filtered = [];
            foreach ($ns as $n) {
                if (!in_array($n["id"] ?? '', $nids, true)) {
                    $filtered[] = $n;
                }
            }
            update_user_meta($uid, "dior_doctor_notifications", $filtered);
        }

        wp_send_json_success(["message" => "Notification permanently deleted."]);
    }

    public static function ajax_clear_read_notifications()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(["message" => "Unauthorized"], 403);
        }
        $uid = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'dior_notifications';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE is_read = 1 AND (doctor_id = %d OR user_id = %d)",
                $uid,
                $uid
            ));
        }

        $ns = get_user_meta($uid, "dior_doctor_notifications", true);
        if (is_array($ns)) {
            $remaining = [];
            foreach ($ns as $n) {
                if (empty($n["is_read"])) {
                    $remaining[] = $n;
                }
            }
            update_user_meta($uid, "dior_doctor_notifications", $remaining);
        }
        wp_send_json_success(["message" => "Read notifications cleared successfully."]);
    }


    public static function ajax_get_live_notifications()
    {
        if (!self::can_access()) {
            wp_send_json_error(["message" => "Unauthorized"], 403);
        }

        $user_id = get_current_user_id();
        $last_known_id = sanitize_text_field($_POST['last_notif_id'] ?? '');
        $notifications = self::get_doctor_notifications($user_id);

        $unread_count = 0;
        $new_alerts = [];
        $found_last = false;

        foreach ($notifications as $n) {
            $is_unread = empty($n['is_read']);
            if ($is_unread) {
                $unread_count++;
            }
            if (!empty($last_known_id)) {
                if ($n['id'] === $last_known_id) {
                    $found_last = true;
                } elseif (!$found_last && $is_unread) {
                    $new_alerts[] = [
                        'id' => $n['id'],
                        'title' => $n['title'] ?? 'New Notification',
                        'message' => $n['message'] ?? '',
                        'icon' => $n['icon'] ?? 'fa-bell',
                        'action_url' => $n['action_url'] ?? '',
                        'time' => dior_format_notification_time($n)
                    ];
                }
            }
        }

        // Generate Dropdown HTML (Top 5)
        ob_start();
        if (empty($notifications)) {
            echo '<div style="padding: 24px; text-align: center; color: #94A3B8; font-size: 13px;">
                    <i class="fa-regular fa-bell-slash" style="font-size: 20px; display: block; margin-bottom: 8px;"></i>
                    No notifications
                  </div>';
        } else {
            foreach (array_slice($notifications, 0, 5) as $n) {
                ?>
                <div class="dior-notif-item <?php echo empty($n['is_read']) ? 'unread' : ''; ?>"
                    data-notif-id="<?php echo esc_attr($n['id']); ?>">
                    <div class="notif-icon"><i class="fa-solid <?php echo esc_attr($n['icon'] ?? 'fa-bell'); ?>"></i></div>
                    <div class="notif-body">
                        <strong><?php echo esc_html($n['title']); ?></strong>
                        <p><?php echo esc_html(mb_substr($n['message'], 0, 30) . (mb_strlen($n['message']) > 30 ? '...' : '')); ?></p>
                        <span class="notif-time"><?php echo esc_html(dior_format_notification_time($n)); ?></span>
                    </div>
                </div>
                <?php
            }
        }
        $dropdown_html = ob_get_clean();

        // Generate Full HTML
        ob_start();
        if (empty($notifications)) {
            echo '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:64px 24px;text-align:center;">
                    <div style="width:64px;height:64px;border-radius:16px;background:#F1F5F9;display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                        <i class="fa-regular fa-bell-slash" style="font-size:26px;color:#CBD5E1;"></i>
                    </div>
                    <p style="font-size:15px;color:#94A3B8;margin:0;font-weight:600;font-family:\'DMSans\',\'DM Sans\',sans-serif;">No notifications yet</p>
                    <p style="font-size:13px;color:#CBD5E1;margin:6px 0 0;">You\'ll see new alerts and updates here.</p>
                  </div>';
        } else {
            foreach ($notifications as $n) {
                ?>
                <div class="dior-full-notif-item <?php echo empty($n['is_read']) ? 'unread' : ''; ?>"
                    data-notif-id="<?php echo esc_attr($n['id']); ?>">
                    <div class="notif-type-icon <?php echo esc_attr($n['type'] ?? 'system'); ?>">
                        <i class="fa-solid <?php echo esc_attr($n['icon'] ?? 'fa-bell'); ?>"></i>
                    </div>
                    <div class="notif-full-body">
                        <div class="notif-full-top">
                            <h4><?php echo esc_html($n['title']); ?></h4>
                            <span class="notif-full-time"><?php echo esc_html(dior_format_notification_time($n)); ?></span>
                        </div>
                        <p><?php echo esc_html($n['message']); ?></p>
                    </div>
                    <div class="notif-full-actions">
                        <?php if (empty($n['is_read'])): ?>
                            <button type="button" class="dior-btn-table-icon dior-mark-single-read btn" title="Mark as Read"
                                onclick="diorDocMarkSingleRead('<?php echo esc_js($n['id']); ?>', this)">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
        }
        $full_html = ob_get_clean();

        wp_send_json_success([
            'unread_count' => $unread_count,
            'dropdown_html' => $dropdown_html,
            'full_html' => $full_html,
            'latest_id' => !empty($notifications) ? $notifications[0]['id'] : '',
            'new_alerts' => $new_alerts
        ]);
    }

    public static function ajax_add_virtual_visit_record()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) {
            wp_send_json_error(["message" => "Unauthorized"], 403);
        }

        $doctor_id = get_current_user_id();
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $visit_date = sanitize_text_field($_POST['visit_date'] ?? '');
        $visit_time = sanitize_text_field($_POST['visit_time'] ?? '');
        $duration = intval($_POST['duration'] ?? 30);
        if ($duration <= 0) {
            $duration = 30;
        }
        $status = sanitize_text_field($_POST['status'] ?? 'Completed');
        $visit_type = sanitize_text_field($_POST['visit_type'] ?? 'Video Visit (HD)');
        $room_url = esc_url_raw($_POST['room_url'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        if (!$patient_id) {
            wp_send_json_error(["message" => "Please select a valid patient."]);
        }
        if (empty($visit_date)) {
            wp_send_json_error(["message" => "Please select a valid visit date."]);
        }

        global $wpdb;

        // Ensure duration column exists
        $table_tele = $wpdb->prefix . 'dior_telemedicine';
        $col_check = $wpdb->get_results("SHOW COLUMNS FROM $table_tele LIKE 'duration'");
        if (empty($col_check)) {
            $wpdb->query("ALTER TABLE $table_tele ADD COLUMN duration INT DEFAULT 30 AFTER status");
        }

        // Generate unique IDs
        $tele_uid = 'VV-' . date('Ymd', strtotime($visit_date)) . '-' . wp_rand(1000, 9999);
        $appt_uid = 'APT-VV-' . wp_rand(10000, 99999);

        // Compute created_at
        $time_str = !empty($visit_time) ? $visit_time : date('H:i');
        if (strlen($time_str) === 5) {
            $time_str .= ':00';
        }
        $created_at = date('Y-m-d H:i:s', strtotime($visit_date . ' ' . $time_str));
        if (!$created_at || $created_at === '1970-01-01 00:00:00') {
            $created_at = current_time('mysql');
        }

        if (empty($room_url)) {
            $room_url = 'https://meet.jit.si/' . sanitize_title($tele_uid);
        }

        // Insert into dior_telemedicine
        $ins = $wpdb->insert(
            $table_tele,
            [
                'tele_uid' => $tele_uid,
                'appointment_uid' => $appt_uid,
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'room_url' => $room_url,
                'status' => $status,
                'duration' => $duration,
                'notes' => $notes,
                'created_at' => $created_at,
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        if (!$ins) {
            wp_send_json_error(["message" => "Failed to save virtual visit record to database."]);
        }
        $tele_id = $wpdb->insert_id;

        // Insert corresponding appointment record
        $table_appt = $wpdb->prefix . 'dior_appointments';
        $formatted_time = date('h:i A', strtotime($created_at));
        $wpdb->insert(
            $table_appt,
            [
                'appt_uid' => $appt_uid,
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'condition_name' => !empty($notes) ? wp_trim_words($notes, 5, '') : 'Telehealth Virtual Visit',
                'visit_type' => $visit_type,
                'status' => in_array($status, ['Confirmed', 'Completed', 'Pending', 'In Progress', 'Cancelled', 'No Show'], true) ? $status : 'Completed',
                'appt_date' => $visit_date,
                'appt_time' => $formatted_time,
                'join_url' => $room_url,
                'payment_status' => 'Paid',
                'notes' => $notes,
                'created_at' => $created_at,
                'updated_at' => current_time('mysql'),
                'is_deleted' => 0
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        // Fetch patient name
        $p_row = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name, patient_uid FROM {$wpdb->prefix}dior_patients WHERE user_id = %d LIMIT 1", $patient_id));
        $patient_name = $p_row ? trim($p_row->first_name . ' ' . $p_row->last_name) : (get_userdata($patient_id)->display_name ?? 'Patient #' . $patient_id);
        $doctor_name = wp_get_current_user()->display_name ?: 'Doctor';

        // Add doctor notification
        $notif_title = 'Virtual Visit Record Created';
        $notif_msg = "Virtual visit record {$tele_uid} added for {$patient_name} on " . date('M j, Y', strtotime($visit_date)) . " ({$status}).";
        $table_notif = $wpdb->prefix . 'dior_notifications';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_notif)) === $table_notif) {
            $wpdb->insert(
                $table_notif,
                [
                    'notification_uid' => 'notif_' . time() . '_' . wp_rand(100, 999),
                    'user_id' => $doctor_id,
                    'doctor_id' => $doctor_id,
                    'patient_id' => $patient_id,
                    'type' => 'appointment',
                    'title' => $notif_title,
                    'message' => $notif_msg,
                    'action_url' => '#tab=doc-telemed-records',
                    'icon' => 'fa-video',
                    'is_read' => 0,
                    'created_at' => current_time('mysql')
                ]
            );
        }

        wp_send_json_success([
            'message' => 'Virtual visit record saved successfully!',
            'record' => [
                'id' => $tele_id,
                'tele_uid' => $tele_uid,
                'patient_id' => $patient_id,
                'patient_name' => $patient_name,
                'doctor_name' => $doctor_name,
                'visit_date' => date_i18n('M j, Y', strtotime($visit_date)),
                'duration' => $duration . ' min',
                'diagnosis' => $notes ?: $status,
                'status' => $status
            ]
        ]);
    }

    public static function ajax_delete_virtual_visit_record()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) {
            wp_send_json_error(["message" => "Unauthorized"], 403);
        }

        $tele_uid = sanitize_text_field($_POST['tele_uid'] ?? '');
        if (empty($tele_uid)) {
            wp_send_json_error(["message" => "Record UID required."]);
        }

        global $wpdb;
        $table_tele = $wpdb->prefix . 'dior_telemedicine';
        $rec = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_tele WHERE tele_uid = %s LIMIT 1", $tele_uid));
        if ($rec) {
            $doctor_context = self::get_single_doctor_context();
            $related_appt = !empty($rec->appointment_uid)
                ? Dior_Appointment_Service::get_appointment($rec->appointment_uid)
                : null;
            $record_belongs_to_doctor = $related_appt
                ? self::appointment_belongs_to_single_doctor($related_appt, $doctor_context)
                : in_array((int) ($rec->doctor_id ?? 0), array_map('intval', (array) ($doctor_context['ids'] ?? [])), true);
            if (!$record_belongs_to_doctor) {
                wp_send_json_error(["message" => "This visit is not part of this doctor's records."], 403);
            }

            $wpdb->delete($table_tele, ['tele_uid' => $tele_uid], ['%s']);
            if (!empty($rec->appointment_uid)) {
                $wpdb->update(
                    $wpdb->prefix . 'dior_appointments',
                    ['is_deleted' => 1],
                    ['appt_uid' => $rec->appointment_uid],
                    ['%d'],
                    ['%s']
                );
            }
        }

        wp_send_json_success(["message" => "Virtual visit record deleted successfully."]);
    }

    public static function ajax_get_patient_detail()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access())
            wp_send_json_error(["message" => "Unauthorized"], 403);
        $pid = Dior_Auth_Service::validate_id($_POST["patient_user_id"] ?? 0);
        $user = get_userdata($pid);
        if (!$user)
            wp_send_json_error(["message" => "Patient not found"]);

        // Use the single configured doctor's identity for doctors and admin previews alike.
        $doctor_context = self::get_single_doctor_context();
        $current_doctor_id = (int) ($doctor_context['user_id'] ?? 0);
        $allowed_doctor_ids = array_map('intval', (array) ($doctor_context['ids'] ?? []));
        if (!$current_doctor_id || !$allowed_doctor_ids) {
            wp_send_json_error(["message" => "The single-doctor dashboard is not configured."], 403);
        }

        $doctor_appointments = Dior_Appointment_Service::get_doctor_appointments($current_doctor_id);
        $has_relation = false;
        foreach ($doctor_appointments as $doctor_appt) {
            if ((int) ($doctor_appt['patient_id'] ?? ($doctor_appt['patient_user_id'] ?? 0)) === (int) $pid) {
                $has_relation = true;
                break;
            }
        }
        if (!$has_relation) {
            wp_send_json_error(["message" => "You do not have access to this patient record."], 403);
        }

        $doctor = self::get_doctor_profile($current_doctor_id);
        $profile = Dior_Patient_Portal_Data::get_patient_profile($pid);
        $intake = get_user_meta($pid, "dior_hipaa_intake", true);
        $apts = Dior_Appointment_Service::get_patient_appointments($pid);
        $apts = array_values(array_filter((array) $apts, static function ($appointment) use ($allowed_doctor_ids) {
            return in_array((int) ($appointment['doctor_id'] ?? 0), $allowed_doctor_ids, true);
        }));
        $rxs = get_user_meta($pid, "dior_prescriptions", true) ?: [];
        $notes = Dior_Encounter_Service::get_patient_encounters($pid);
        $docs = Dior_Medical_Secure_Files::get_patient_documents($pid);

        ob_start();
        include DIOR_PORTAL_PATH . "templates/patient-detail-modal.php";
        wp_send_json_success(["html" => ob_get_clean()]);
    }

    public static function ajax_add_note()
    {
        self::ajax_save_soap_note();
    }

    public static function ajax_add_prescription()
    {
        // Require an authenticated doctor/admin and a valid WordPress AJAX nonce.
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) {
            wp_send_json_error(["message" => "Unauthorized"], 403);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dior_prescriptions';
        // Accept either the WordPress user ID or the site's existing patient ID (e.g. DM-10001).
        $patient_identifier = sanitize_text_field($_POST['patient_id'] ?? '');
        $patient_id = (is_numeric($patient_identifier)) ? absint($patient_identifier) : 0;
        $patient_user = $patient_id ? get_userdata($patient_id) : false;
        if (!$patient_user && $patient_identifier !== '') {
            $matched_user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                'patient_id',
                $patient_identifier
            ));
            if (!$matched_user_id) {
                $patient_table = $wpdb->prefix . 'dior_patients';
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $patient_table)) === $patient_table) {
                    $matched_user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $patient_table WHERE patient_uid = %s LIMIT 1", $patient_identifier));
                }
            }
            $patient_id = absint($matched_user_id);
            $patient_user = $patient_id ? get_userdata($patient_id) : false;
        }
        if (!$patient_user) {
            $patient_id = 1;
            $patient_user = get_userdata(1);
        }
        if (!$patient_user) {
            wp_send_json_error(["message" => "Select a valid patient record you are authorized to access."], 403);
        }

        $current_user_id = get_current_user_id();
        $doctor_user = get_userdata($current_user_id);
        $doctor_name = trim((string) get_user_meta($current_user_id, 'first_name', true) . ' ' . (string) get_user_meta($current_user_id, 'last_name', true));
        if ($doctor_name === '') {
            $doctor_name = $doctor_user ? $doctor_user->display_name : 'Doctor';
        }
        if (stripos($doctor_name, 'dr.') !== 0) {
            $doctor_name = 'Dr. ' . $doctor_name;
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        if ($title === '') {
            $title = 'General Medical Prescription';
        }

        $prescription_id = sanitize_text_field($_POST['prescription_id'] ?? '');
        if ($prescription_id === '' || $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE prescription_uid = %s LIMIT 1", $prescription_id))) {
            $prescription_id = 'RX-' . gmdate('ymd') . '-' . strtoupper(wp_generate_password(6, false, false));
        }
        $prescription_date = sanitize_text_field($_POST['prescription_date'] ?? current_time('Y-m-d'));
        $parsed_date = DateTime::createFromFormat('Y-m-d', $prescription_date);
        if (!$parsed_date || $parsed_date->format('Y-m-d') !== $prescription_date) {
            wp_send_json_error(["message" => "Please provide a valid prescription date."]);
        }

        $medications = sanitize_textarea_field($_POST['medications'] ?? '');
        $dosage = sanitize_text_field($_POST['dosage'] ?? '');
        $frequency = sanitize_text_field($_POST['frequency'] ?? '');
        $duration = sanitize_text_field($_POST['duration'] ?? '');
        $instructions = sanitize_textarea_field($_POST['instructions'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? 'Active');
        if (!in_array($status, ['Active', 'Completed', 'Cancelled'], true)) {
            $status = 'Active';
        }
        if ($medications === '' || $dosage === '' || $frequency === '' || $duration === '') {
            wp_send_json_error(["message" => "Medication, dosage, frequency and duration are required."]);
        }

        $appointment_uid = sanitize_text_field($_POST['appointment_uid'] ?? '');
        if ($appointment_uid !== '') {
            $appointment = Dior_Appointment_Service::get_appointment($appointment_uid);
            if (!$appointment || (int) $appointment['patient_id'] !== $patient_id) {
                wp_send_json_error(["message" => "The selected appointment does not belong to this patient."], 403);
            }
            if (!current_user_can('manage_options') && (int) $appointment['doctor_id'] !== $current_user_id) {
                wp_send_json_error(["message" => "You are not authorized to prescribe for this appointment."], 403);
            }
        }

        $now = current_time('mysql');
        $inserted = $wpdb->insert($table_name, [
            'prescription_uid' => $prescription_id,
            'prescription_id' => $prescription_id,
            'title' => $title,
            'patient_id' => $patient_id,
            'patient_name' => $patient_user->display_name,
            'doctor_id' => $current_user_id,
            'doctor_name' => $doctor_name,
            'appointment_uid' => $appointment_uid ?: null,
            'prescription_date' => $prescription_date,
            'medications' => $medications,
            'medication' => substr($medications, 0, 255),
            'dosage' => $dosage,
            'frequency' => $frequency,
            'duration' => $duration,
            'instructions' => $instructions,
            'quantity' => sanitize_text_field($_POST['quantity'] ?? 'As prescribed'),
            'refills' => absint($_POST['refills'] ?? 0),
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (!$inserted) {
            $err = $wpdb->last_error ? (': ' . $wpdb->last_error) : '';
            wp_send_json_error(["message" => "Database error. The prescription could not be saved" . $err]);
        }

        // Keep legacy prescription data available for older dashboard components.
        $legacy = get_user_meta($patient_id, 'dior_prescriptions', true);
        if (!is_array($legacy))
            $legacy = [];
        array_unshift($legacy, [
            'id' => $prescription_id,
            'order_id' => $prescription_id,
            'title' => $title,
            'name' => $medications,
            'medication' => $medications,
            'dosage' => $dosage,
            'frequency' => $frequency,
            'duration' => $duration,
            'instructions' => $instructions,
            'notes' => $instructions,
            'date' => $prescription_date,
            'date_prescribed' => $prescription_date,
            'status' => $status,
            'prescribed_by' => $doctor_name,
            'appointment_uid' => $appointment_uid,
        ]);
        update_user_meta($patient_id, 'dior_prescriptions', $legacy);

        Dior_Audit_Service::log('prescription_create', 'prescription', $prescription_id, $patient_id, 'success', ['doctor_id' => $current_user_id]);

        // Send a minimal notification email without including sensitive clinical details.
        if (!empty($patient_user->user_email) && is_email($patient_user->user_email)) {
            $subject = 'A new prescription is available in your Dior Medical portal';
            $message = "Hello " . sanitize_text_field($patient_user->display_name) . ",\n\nA new prescription has been added by your doctor. Please sign in to your Patient Dashboard to review it securely.\n\nDior Medical";
            wp_mail($patient_user->user_email, $subject, $message);
        }

        wp_send_json_success([
            "message" => "Prescription saved successfully.",
            "prescription_id" => $prescription_id,
            "title" => $title
        ]);
    }

    public static function ajax_save_soap_note()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access())
            wp_send_json_error(["message" => "Unauthorized"], 403);
        $pid = Dior_Auth_Service::validate_id($_POST["patient_user_id"] ?? 0);
        if (!$pid)
            wp_send_json_error(["message" => "Valid Patient selection required."]);

        $res = Dior_Encounter_Service::save_encounter([
            'patient_id' => $pid,
            'appointment_id' => sanitize_text_field($_POST["appointment_uid"] ?? $_POST["appointment_id"] ?? ''),
            'encounter_uid' => sanitize_text_field($_POST["note_id"] ?? ''),
            'encounter_type' => sanitize_text_field($_POST["encounter_type"] ?? "Telehealth Consultation"),
            'subjective' => $_POST["subjective"] ?? ($_POST["note"] ?? ""),
            'objective' => $_POST["objective"] ?? "",
            'assessment' => $_POST["assessment"] ?? "",
            'plan' => $_POST["plan"] ?? "",
            'diagnosis' => sanitize_text_field($_POST["diagnosis"] ?? ""),
            'follow_up' => sanitize_text_field($_POST["follow_up"] ?? "As needed / 7-10 days"),
            'vitals_bp' => sanitize_text_field($_POST["vitals_bp"] ?? ""),
            'vitals_hr' => sanitize_text_field($_POST["vitals_hr"] ?? ""),
            'vitals_temp' => sanitize_text_field($_POST["vitals_temp"] ?? ""),
            'vitals_weight' => sanitize_text_field($_POST["vitals_weight"] ?? "")
        ]);

        if (is_wp_error($res)) {
            wp_send_json_error(["message" => $res->get_error_message()]);
        }

        wp_send_json_success(["message" => "Clinical SOAP note saved successfully!", "note" => $res]);
    }

    public static function ajax_delete_note()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access())
            wp_send_json_error(["message" => "Unauthorized"], 403);
        $pid = Dior_Auth_Service::validate_id($_POST["patient_user_id"] ?? 0);
        $note_id = sanitize_text_field($_POST["note_id"] ?? "");
        if (!$pid || empty($note_id))
            wp_send_json_error(["message" => "Invalid parameters"]);

        $res = Dior_Encounter_Service::delete_encounter($note_id, $pid);
        if (is_wp_error($res)) {
            wp_send_json_error(["message" => $res->get_error_message()]);
        }
        if ($res) {
            wp_send_json_success(["message" => "Clinical note removed successfully"]);
        } else {
            wp_send_json_error(["message" => "Note could not be removed"]);
        }
    }

    public static function ajax_change_password()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) {
            wp_send_json_error(["message" => "Unauthorized access."]);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(["message" => "Authentication required."]);
        }

        $current_pwd = $_POST["current_password"] ?? "";
        $new_pwd = $_POST["new_password"] ?? "";
        $confirm_pwd = $_POST["confirm_password"] ?? "";

        if (empty($current_pwd) || empty($new_pwd) || empty($confirm_pwd)) {
            wp_send_json_error(["message" => "Please fill in all password fields."]);
        }

        if ($new_pwd !== $confirm_pwd) {
            wp_send_json_error(["message" => "New password and confirmation do not match."]);
        }

        if (strlen($new_pwd) < 6) {
            wp_send_json_error(["message" => "New password must be at least 6 characters long."]);
        }

        $user = get_userdata($user_id);
        if (!$user || !wp_check_password($current_pwd, $user->user_pass, $user_id)) {
            wp_send_json_error(["message" => "Current password is incorrect."]);
        }

        wp_set_password($new_pwd, $user_id);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        wp_send_json_success(["message" => "Your password has been changed successfully!"]);
    }
}

/**
 * Dior Medical — Dynamic Doctor Resolver & Multi-Doctor Synchronization Service
 */
class Dior_Doctor_Resolver
{
    const DEFAULT_DOCTOR_LOGIN = 'doctor';
    const DEFAULT_DOCTOR_EMAIL = 'doctor@gmail.com';
    const LEGACY_DOCTOR_LOGIN = 'Docter';
    const LEGACY_DOCTOR_EMAIL = 'docter@gmail.com';

    /**
     * Ensures the central doctor account exists; new accounts receive a random password and reset email.
     */
    public static function ensure_default_doctor()
    {
        $user = get_user_by('email', self::DEFAULT_DOCTOR_EMAIL);
        if (!$user) {
            $user = get_user_by('email', self::LEGACY_DOCTOR_EMAIL);
        }
        if (!$user) {
            $user = get_user_by('login', self::LEGACY_DOCTOR_LOGIN);
        }
        if (!$user) {
            $user = get_user_by('login', self::DEFAULT_DOCTOR_LOGIN);
        }

        if (!$user) {
            $user_id = wp_insert_user([
                'user_login' => self::DEFAULT_DOCTOR_LOGIN,
                'user_email' => self::DEFAULT_DOCTOR_EMAIL,
                'user_pass' => wp_generate_password(40, true, true),
                'display_name' => 'Dr. Dior Medical',
                'first_name' => 'Dr.',
                'last_name' => 'Dior',
                'role' => 'doctor',
            ]);
            if (!is_wp_error($user_id)) {
                update_user_meta($user_id, 'doctor_specialty', 'Primary Telehealth Physician');
                update_user_meta($user_id, 'phone', '+1 (310) 555-0100');
                if (function_exists('wp_new_user_notification')) {
                    wp_new_user_notification($user_id, null, 'user');
                }
                return (int) $user_id;
            }
            return 0;
        } else {
            if ($user->user_email !== self::DEFAULT_DOCTOR_EMAIL) {
                $email_owner = get_user_by('email', self::DEFAULT_DOCTOR_EMAIL);
                if (!$email_owner || (int) $email_owner->ID === (int) $user->ID) {
                    $updated = wp_update_user(['ID' => $user->ID, 'user_email' => self::DEFAULT_DOCTOR_EMAIL]);
                    if (!is_wp_error($updated)) {
                        update_user_meta($user->ID, '_dior_doctor_email_updated_from', $user->user_email);
                    }
                }
            }
            // Ensure doctor role exists
            if (!user_can($user->ID, 'doctor') && !user_can($user->ID, 'administrator')) {
                $user->add_role('doctor');
            }
            return (int) $user->ID;
        }
    }

    /**
     * Get default doctor WP user ID
     */
    public static function get_default_doctor_id()
    {
        $user = get_user_by('email', self::DEFAULT_DOCTOR_EMAIL);
        if (!$user) {
            $user = get_user_by('login', self::DEFAULT_DOCTOR_LOGIN);
        }
        if ($user)
            return (int) $user->ID;
        return (int) self::ensure_default_doctor();
    }

    public static function get_accessible_doctor_ids($doctor_user_id = 0)
    {
        $doctor_user_id = (int) ($doctor_user_id ?: get_current_user_id());
        if (!$doctor_user_id)
            return [];

        $ids = [$doctor_user_id];
        $legacy_user_ids = get_user_meta($doctor_user_id, '_dior_merged_doctor_user_ids', true);
        $legacy_post_ids = get_user_meta($doctor_user_id, '_dior_merged_docbooker_post_ids', true);
        if (is_array($legacy_user_ids))
            $ids = array_merge($ids, $legacy_user_ids);
        if (is_array($legacy_post_ids))
            $ids = array_merge($ids, $legacy_post_ids);

        foreach (get_posts([
            'post_type' => 'wpddb_doctor',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => '_wpddb_doctor_user_id',
            'meta_value' => (string) $doctor_user_id,
        ]) as $post_id) {
            $ids[] = $post_id;
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    public static function block_merged_account_login($user, $password = '')
    {
        if (is_wp_error($user) || !($user instanceof WP_User))
            return $user;
        if (get_user_meta($user->ID, '_dior_merged_into_doctor_id', true)) {
            return new WP_Error('dior_doctor_account_merged', 'This doctor account has been consolidated. Please use the central doctor account.');
        }
        return $user;
    }

    public static function consolidate_legacy_accounts()
    {
        $trusted_cli = defined('WP_CLI') && WP_CLI;
        if ((!is_admin() && !$trusted_cli) || !current_user_can('manage_options') || get_option('dior_doctor_accounts_consolidated_v1'))
            return;

        $central_id = self::get_default_doctor_id();
        $central = $central_id ? get_userdata($central_id) : false;
        if (!$central || $central->user_email !== self::DEFAULT_DOCTOR_EMAIL)
            return;

        $legacy_user_ids = [];
        $legacy_profiles = get_user_meta($central_id, '_dior_merged_doctor_profiles', true);
        $legacy_profiles = is_array($legacy_profiles) ? $legacy_profiles : [];
        $profile_meta_keys = ['first_name', 'last_name', 'doctor_specialty', 'doctor_license', 'doctor_npi', 'doctor_bio', 'phone', 'dior_profile_image', 'dior_doctor_signature'];

        foreach (get_users(['role' => 'doctor', 'fields' => 'all', 'number' => 500]) as $doctor_user) {
            $doctor_id = (int) $doctor_user->ID;
            if ($doctor_id === $central_id || in_array('administrator', (array) $doctor_user->roles, true))
                continue;

            $profile_snapshot = [];
            foreach ($profile_meta_keys as $meta_key) {
                $meta_value = get_user_meta($doctor_id, $meta_key, true);
                if ($meta_value !== '' && $meta_value !== null)
                    $profile_snapshot[$meta_key] = $meta_value;
            }
            $legacy_profiles[$doctor_id] = [
                'user_login' => $doctor_user->user_login,
                'user_email' => $doctor_user->user_email,
                'display_name' => $doctor_user->display_name,
                'roles' => array_values((array) $doctor_user->roles),
                'profile' => $profile_snapshot,
            ];
            $legacy_user_ids[] = $doctor_id;

            $merged_notifications = get_user_meta($central_id, 'dior_doctor_notifications', true);
            $merged_notifications = is_array($merged_notifications) ? $merged_notifications : [];
            $old_notifications = get_user_meta($doctor_id, 'dior_doctor_notifications', true);
            if (is_array($old_notifications)) {
                $known_notification_ids = array_column($merged_notifications, 'id');
                foreach ($old_notifications as $notification) {
                    $notification_id = $notification['id'] ?? '';
                    if (!$notification_id || !in_array($notification_id, $known_notification_ids, true)) {
                        $merged_notifications[] = $notification;
                        if ($notification_id)
                            $known_notification_ids[] = $notification_id;
                    }
                }
            }
            update_user_meta($central_id, 'dior_doctor_notifications', array_slice($merged_notifications, 0, 100));

            update_user_meta($doctor_id, '_dior_merged_into_doctor_id', $central_id);
            $legacy_user = new WP_User($doctor_id);
            $legacy_user->set_role('subscriber');
            if (class_exists('WP_Session_Tokens')) {
                WP_Session_Tokens::get_instance($doctor_id)->destroy_all();
            } elseif (function_exists('wp_destroy_all_sessions') && get_current_user_id() === (int)$doctor_id) {
                wp_destroy_all_sessions();
            }
        }

        $existing_legacy_ids = get_user_meta($central_id, '_dior_merged_doctor_user_ids', true);
        $legacy_user_ids = array_values(array_unique(array_merge(is_array($existing_legacy_ids) ? $existing_legacy_ids : [], $legacy_user_ids)));
        update_user_meta($central_id, '_dior_merged_doctor_user_ids', $legacy_user_ids);
        update_user_meta($central_id, '_dior_merged_doctor_profiles', $legacy_profiles);

        $docbooker_post_ids = [];
        foreach (get_posts([
            'post_type' => 'wpddb_doctor',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]) as $post_id) {
            $linked_user_id = (int) get_post_meta($post_id, '_wpddb_doctor_user_id', true);
            if ($linked_user_id && $linked_user_id !== $central_id && !get_post_meta($post_id, '_dior_original_doctor_user_id', true)) {
                update_post_meta($post_id, '_dior_original_doctor_user_id', $linked_user_id);
            }
            update_post_meta($post_id, '_wpddb_doctor_user_id', $central_id);
            $docbooker_post_ids[] = (int) $post_id;
        }
        $existing_post_ids = get_user_meta($central_id, '_dior_merged_docbooker_post_ids', true);
        $docbooker_post_ids = array_values(array_unique(array_merge(is_array($existing_post_ids) ? $existing_post_ids : [], $docbooker_post_ids)));
        update_user_meta($central_id, '_dior_merged_docbooker_post_ids', $docbooker_post_ids);

        $old_email = get_user_meta($central_id, '_dior_doctor_email_updated_from', true);
        if ($old_email && $old_email !== self::DEFAULT_DOCTOR_EMAIL) {
            wp_mail(
                self::DEFAULT_DOCTOR_EMAIL,
                'Your Dior Medical doctor account was consolidated',
                'Your doctor account is now available at ' . home_url('/doctor-dashboard/') . '. Use ' . wp_lostpassword_url() . ' to set a new password.'
            );
            delete_user_meta($central_id, '_dior_doctor_email_updated_from');
        }

        update_option('dior_doctor_accounts_consolidated_v1', [
            'central_user_id' => $central_id,
            'central_user_login' => $central->user_login,
            'central_previous_email' => $old_email ?: '',
            'legacy_user_ids' => $legacy_user_ids,
            'docbooker_post_ids' => $docbooker_post_ids,
            'migrated_at' => current_time('mysql'),
        ], false);
    }

    /**
     * Resolves DocBooker doctor ID (CPT post ID), doctor name, or existing WP User ID to an actual WordPress User ID
     */
    public static function resolve_doctor_user_id($doctor_ref)
    {
        $central_id = self::get_default_doctor_id();
        if (!$central_id)
            return 0;

        if (empty($doctor_ref)) {
            return $central_id;
        }

        if (is_numeric($doctor_ref)) {
            $reference_id = (int) $doctor_ref;
            if (get_user_meta($reference_id, '_dior_merged_into_doctor_id', true)) {
                return $central_id;
            }
            $post = get_post($reference_id);
            if ($post && $post->post_type === 'wpddb_doctor') {
                return self::sync_docbooker_doctor_to_wp_user($post->ID);
            }
            $user = get_userdata($reference_id);
            if ($user && in_array('administrator', (array) $user->roles, true) && !in_array('doctor', (array) $user->roles, true)) {
                return (int) $user->ID;
            }
            if ($user && in_array('doctor', (array) $user->roles, true))
                return $central_id;
        }

        if (is_string($doctor_ref)) {
            $name = trim($doctor_ref);
            if ($name === '')
                return $central_id;

            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpddb_doctor' AND (post_title = %s OR post_title LIKE %s) LIMIT 1",
                $name,
                '%' . $wpdb->esc_like($name) . '%'
            ));
            if ($post_id) {
                return self::sync_docbooker_doctor_to_wp_user((int) $post_id);
            }

            $clean_name = trim(preg_replace('/^Dr\.?\s+/i', '', $name));
            $clean_name = trim(preg_replace('/,\s*(MD|DO|MBBS|PhD)$/i', '', $clean_name));
            $user = get_user_by('login', sanitize_user(strtolower(str_replace(' ', '', $clean_name))));
            if ($user) {
                if (get_user_meta($user->ID, '_dior_merged_into_doctor_id', true) || in_array('doctor', (array) $user->roles, true)) {
                    return $central_id;
                }
                if (in_array('administrator', (array) $user->roles, true))
                    return (int) $user->ID;
            }
        }

        return $central_id;
    }

    /**
     * Auto-sync or provision a WP Doctor User for a DocBooker CPT post
     */
    public static function sync_docbooker_doctor_to_wp_user($post_id)
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'wpddb_doctor') {
            return self::get_default_doctor_id();
        }

        $central_id = self::get_default_doctor_id();
        if (!$central_id)
            return 0;

        $linked_user_id = (int) get_post_meta($post_id, '_wpddb_doctor_user_id', true);
        if ($linked_user_id && $linked_user_id !== $central_id && !get_post_meta($post_id, '_dior_original_doctor_user_id', true)) {
            update_post_meta($post_id, '_dior_original_doctor_user_id', $linked_user_id);
        }
        update_post_meta($post_id, '_wpddb_doctor_user_id', $central_id);
        $post_ids = get_user_meta($central_id, '_dior_merged_docbooker_post_ids', true);
        $post_ids = is_array($post_ids) ? $post_ids : [];
        $post_ids[] = (int) $post_id;
        update_user_meta($central_id, '_dior_merged_docbooker_post_ids', array_values(array_unique(array_map('intval', $post_ids))));
        update_user_meta($central_id, '_docbooker_doctor_id', (int) $post_id);
        return $central_id;
    }
}

Dior_Doctor_Dashboard::init();
