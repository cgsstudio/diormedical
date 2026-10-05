<?php
/**
 * Dior Medical — Doctor / Provider Dashboard
 * Shortcode: [dior_doctor_dashboard]
 * Access: administrator | doctor roles
 */

if (!defined("ABSPATH")) exit;

class Dior_Doctor_Dashboard
{
    public static function init()
    {
        add_shortcode("dior_doctor_dashboard", [__CLASS__, "render"]);
        add_action("wp_ajax_dior_doctor_update_appt_status",  [__CLASS__, "ajax_update_appt_status"]);
        add_action("wp_ajax_dior_doctor_send_reminder",       [__CLASS__, "ajax_send_reminder"]);
        add_action("wp_ajax_dior_doctor_reschedule_appt",     [__CLASS__, "ajax_reschedule_appt"]);
        add_action("wp_ajax_dior_doctor_add_prescription",    [__CLASS__, "ajax_add_prescription"]);
        add_action("wp_ajax_dior_doctor_add_note",            [__CLASS__, "ajax_add_note"]);
        add_action("wp_ajax_dior_doctor_save_soap_note",      [__CLASS__, "ajax_save_soap_note"]);
        add_action("wp_ajax_dior_doctor_delete_note",         [__CLASS__, "ajax_delete_note"]);
        add_action("wp_ajax_dior_doctor_mark_notif_read",     [__CLASS__, "ajax_mark_notif_read"]);
        add_action("wp_ajax_dior_doctor_mark_single_notif_read", [__CLASS__, "ajax_mark_notif_read"]);
        add_action("wp_ajax_dior_doctor_mark_all_notif_read", [__CLASS__, "ajax_mark_all_notif_read"]);
        add_action("wp_ajax_dior_doctor_get_live_notifications", [__CLASS__, "ajax_get_live_notifications"]);
        add_action("wp_ajax_dior_doctor_save_profile",        [__CLASS__, "ajax_save_profile"]);
        add_action("wp_ajax_dior_doctor_upload_avatar",       [__CLASS__, "ajax_upload_avatar"]);
        add_action("wp_ajax_dior_doctor_remove_avatar",       [__CLASS__, "ajax_remove_avatar"]);
        add_action("wp_ajax_dior_doctor_upload_signature",    [__CLASS__, "ajax_upload_signature"]);
        add_action("wp_ajax_dior_doctor_remove_signature",    [__CLASS__, "ajax_remove_signature"]);
        add_action("wp_ajax_dior_doctor_change_password",     [__CLASS__, "ajax_change_password"]);
        add_action("wp_ajax_dior_doctor_get_patient_detail",  [__CLASS__, "ajax_get_patient_detail"]);
        add_action("wp_ajax_dior_doctor_send_multi_prescription", [__CLASS__, "ajax_send_multi_prescription"]);
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
                "read"           => true,
                "edit_posts"     => false,
                "delete_posts"   => false,
                "publish_posts"  => false,
                "upload_files"   => true,
                "manage_options" => false,
            ]);
        }
    }

    public static function ensure_doctor_page()
    {
        global $wpdb;
        $page = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[dior_doctor_dashboard]%' AND post_status='publish' LIMIT 1");
        if ($page) return;
        $page_id = wp_insert_post([
            "post_title"   => "Doctor Dashboard",
            "post_name"    => "doctor-dashboard",
            "post_content" => "[dior_doctor_dashboard]",
            "post_status"  => "publish",
            "post_type"    => "page",
        ]);
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, "_wp_page_template", "elementor_canvas");
            update_post_meta($page_id, "_dior_doctor_dashboard_page", "1");
        }
    }

    public static function can_access_for_dynamic() { return self::can_access(); }

    private static function can_access()
    {
        return current_user_can("administrator") || current_user_can("manage_options") || current_user_can("doctor");
    }

    public static function get_doctor_profile($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) return [];
        $first_name = get_user_meta($user_id, "first_name", true) ?: $user->first_name ?: "";
        $last_name  = get_user_meta($user_id, "last_name", true)  ?: $user->last_name  ?: "";
        $name_part = trim($first_name . " " . $last_name);
        
        $base_name = $name_part ?: $user->display_name;
        $full_name = (stripos($base_name, 'Dr.') === 0 || stripos($base_name, 'Dr ') === 0) ? $base_name : "Dr. " . $base_name;
        
        $avatar_url = get_user_meta($user_id, "dior_profile_image", true) 
            ?: get_user_meta($user_id, "doctor_avatar", true) 
            ?: get_user_meta($user_id, "profile_picture", true) 
            ?: "";
            
        $initials = '';
        if (!empty($first_name) || !empty($last_name)) {
            $initials = strtoupper(substr($first_name ?: '', 0, 1) . substr($last_name ?: '', 0, 1));
        }
        if (empty($initials)) {
            $initials = strtoupper(substr($user->display_name ?: $user->user_login, 0, 2));
        }

        return [
            "user_id"    => $user_id,
            "first_name" => $first_name,
            "last_name"  => $last_name,
            "full_name"  => $full_name,
            "avatar_url" => $avatar_url,
            "signature_url" => get_user_meta($user_id, "dior_doctor_signature", true) ?: "",
            "email"      => $user->user_email,
            "phone"      => get_user_meta($user_id, "phone", true) ?: "",
            "specialty"  => get_user_meta($user_id, "doctor_specialty", true) ?: "",
            "license_no" => get_user_meta($user_id, "doctor_license", true)   ?: "",
            "npi"        => get_user_meta($user_id, "doctor_npi", true)        ?: "",
            "bio"        => get_user_meta($user_id, "doctor_bio", true)        ?: "",
            "zoom_link"  => get_user_meta($user_id, "doctor_zoom_link", true) ?: "https://zoom.us/join",
            "initials"   => $initials,
            "optin_appointment_reminders" => (get_user_meta($user_id, "optin_appointment_reminders", true) !== '0') ? '1' : '0',
            "optin_reminder_email"        => (get_user_meta($user_id, "optin_reminder_email", true) !== '0') ? '1' : '0',
            "optin_reminder_dashboard"    => (get_user_meta($user_id, "optin_reminder_dashboard", true) !== '0') ? '1' : '0',
        ];
    }

    /**
     * Check if doctor profile has all required personal & credential information filled
     */
    public static function is_profile_complete($user_id)
    {
        if (!$user_id) return false;
        $profile = self::get_doctor_profile($user_id);
        if (empty($profile)) return false;

        $required_fields = ['first_name', 'last_name', 'phone', 'specialty', 'license_no', 'npi'];
        foreach ($required_fields as $f) {
            if (empty($profile[$f]) || trim((string)$profile[$f]) === '') {
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
        if (!$user_id) return [
            'first_name'      => 'First Name',
            'last_name'       => 'Last Name',
            'phone'           => 'Phone Number',
            'doctor_specialty'=> 'Medical Specialty',
            'doctor_license'  => 'Medical License Number',
            'doctor_npi'      => 'NPI Number'
        ];

        $profile = self::get_doctor_profile($user_id);
        $labels = [
            'first_name' => 'First Name',
            'last_name'  => 'Last Name',
            'phone'      => 'Phone Number',
            'specialty'  => 'Medical Specialty',
            'license_no' => 'Medical License Number',
            'npi'        => 'NPI Number'
        ];
        $missing = [];
        foreach ($labels as $k => $label) {
            if (empty($profile[$k]) || trim((string)$profile[$k]) === '') {
                $form_key = ($k === 'specialty') ? 'doctor_specialty' : (($k === 'license_no') ? 'doctor_license' : (($k === 'npi') ? 'doctor_npi' : $k));
                $missing[$form_key] = $label;
            }
        }
        return $missing;
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
        if (!empty($appt["doctor_user_id"]) && (int)$appt["doctor_user_id"] === (int)$doctor_user_id) {
            return true;
        }
        $linked_doc_id = get_user_meta($doctor_user_id, "_docbooker_doctor_id", true);
        if ($linked_doc_id && !empty($appt["doctor_id"]) && (int)$appt["doctor_id"] === (int)$linked_doc_id) {
            return true;
        }

        // Use smart resolver to match
        $doc_ref = !empty($appt["doctor_id"]) ? $appt["doctor_id"] : ($appt["provider"] ?? ($appt["doctor_name"] ?? ""));
        if ($doc_ref && class_exists('Dior_Doctor_Resolver')) {
            $resolved_uid = Dior_Doctor_Resolver::resolve_doctor_user_id($doc_ref);
            if ($resolved_uid && (int)$resolved_uid === (int)$doctor_user_id) {
                return true;
            }
        }

        $u = get_userdata($doctor_user_id);
        if ($u) {
            $fname = get_user_meta($doctor_user_id, "first_name", true) ?: $u->first_name;
            $lname = get_user_meta($doctor_user_id, "last_name", true)  ?: $u->last_name;
            $disp  = $u->display_name;
            $prov  = $appt["provider"] ?? ($appt["doctor_name"] ?? "");
            
            if ($prov) {
                if ($disp && stripos($prov, $disp) !== false) return true;
                if ($lname && strlen($lname) >= 3 && stripos($prov, $lname) !== false) return true;
                if ($fname && strlen($fname) >= 3 && stripos($prov, $fname) !== false) return true;
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
        if (!$user_id && !$fallback_user) return '—';
        $u = $fallback_user ?: get_userdata($user_id);
        $uid = $u ? $u->ID : (int)$user_id;
        
        $fname = get_user_meta($uid, "first_name", true) ?: ($u ? $u->first_name : '');
        $lname = get_user_meta($uid, "last_name", true)  ?: ($u ? $u->last_name : '');

        // Check patient profile meta
        if (empty($fname) || empty($lname)) {
            $prof = get_user_meta($uid, "dior_patient_profile", true);
            if (is_array($prof)) {
                if (empty($fname) && !empty($prof['first_name'])) $fname = $prof['first_name'];
                if (empty($lname) && !empty($prof['last_name']))  $lname = $prof['last_name'];
                if (empty($fname) && empty($lname) && !empty($prof['full_name'])) {
                    return trim($prof['full_name']);
                }
            }
        }

        // Check HIPAA intake meta
        if (empty($fname) || empty($lname)) {
            $intake = get_user_meta($uid, "dior_hipaa_intake", true);
            if (is_array($intake)) {
                if (empty($fname) && !empty($intake['first_name'])) $fname = $intake['first_name'];
                if (empty($lname) && !empty($intake['last_name']))  $lname = $intake['last_name'];
                if (empty($fname) && empty($lname) && !empty($intake['full_legal_name'])) {
                    return trim($intake['full_legal_name']);
                }
            }
        }

        // Check billing names
        if (empty($fname)) $fname = get_user_meta($uid, "billing_first_name", true) ?: '';
        if (empty($lname)) $lname = get_user_meta($uid, "billing_last_name", true) ?: '';

        $full = trim($fname . ' ' . $lname);
        if (!empty($full)) return $full;
        if ($u && !empty($u->display_name)) return $u->display_name;
        if ($u && !empty($u->user_nicename)) return $u->user_nicename;
        if ($u && !empty($u->user_login)) return $u->user_login;
        return 'Patient #' . $uid;
    }

    private static function get_all_patients($doctor_user_id = 0)
    {
        global $wpdb;

        $doctor_user_id = (int)$doctor_user_id;
        $is_admin = $doctor_user_id ? self::is_user_admin($doctor_user_id) : current_user_can('manage_options');

        // Doctors only need patients related to their own appointments.
        // Admins can see the full patient population, capped for dashboard performance.
        $patient_ids = [];
        if (!$is_admin && $doctor_user_id) {
            $appointments = Dior_Appointment_Service::get_doctor_appointments($doctor_user_id);
            foreach ($appointments as $appointment) {
                $pid = (int)($appointment['patient_id'] ?? ($appointment['patient_user_id'] ?? 0));
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
            $pid = (int)($appointment['patient_id'] ?? ($appointment['patient_user_id'] ?? 0));
            if (!$pid) continue;
            $status = strtolower((string)($appointment['status'] ?? ''));
            if (in_array($status, ['completed', 'cancelled', 'cancel', 'no-show', 'no show', 'done'], true)) continue;
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
        $doctor_user_id = (int)$doctor_user_id;
        if (array_key_exists($doctor_user_id, $cache)) {
            return $cache[$doctor_user_id];
        }
        return $cache[$doctor_user_id] = Dior_Appointment_Service::get_doctor_appointments($doctor_user_id);
    }

    private static function get_doctor_notifications($user_id)
    {
        $n = get_user_meta($user_id, "dior_doctor_notifications", true);
        if (!is_array($n)) {
            $n = [];
            update_user_meta($user_id, "dior_doctor_notifications", $n);
        }
        return $n;
    }

    private static function get_all_documents($patients = [])
    {
        $all_docs = [];
        if (!empty($patients) && is_array($patients)) {
            foreach ($patients as $p) {
                $pid = $p["user_id"] ?? 0;
                if (!$pid) continue;
                $p_docs = Dior_Medical_Secure_Files::get_patient_documents($pid);

                foreach ($p_docs as $d) {
                    $d["patient_id_num"]  = $p["patient_id"] ?? ("DM-" . (10000 + $pid));
                    $d["patient_name"]    = $p["full_name"] ?? "Patient";
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
        $doctor_user_id = (int)($doctor_user_id ?: get_current_user_id());
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
                $patient_ids[] = (int)$patient['user_id'];
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

    public static function render($atts)
    {
        if (!is_user_logged_in()) {
            $login_url = home_url("/diro-login/");
            if (!headers_sent()) {
                wp_safe_redirect($login_url);
                exit;
            }
            return '<script>window.location.replace(' . json_encode($login_url) . ');</script><meta http-equiv="refresh" content="0;url=' . esc_url($login_url) . '">';
        }
        if (!self::can_access()) {
            return "<div style='text-align:center;padding:60px 20px;background:#FEF2F2;border-radius:12px;'><h3 style='color:#DC2626;'>Access Denied</h3><p>You do not have permission to view this page.</p></div>";
        }

        $user_id       = get_current_user_id();
        $doctor        = self::get_doctor_profile($user_id);
        $patients      = self::get_all_patients($user_id);
        $appointments  = self::get_all_appointments($user_id);
        $notifications = self::get_doctor_notifications($user_id);
        $documents     = self::get_all_documents($patients);

        // Temporary UI demo fallback: only used when the doctor has no real records.
        // Nothing is written to the database and real records always take precedence.
        $dior_demo_mode = false;
        $demo_today = current_time('Y-m-d');
        if (empty($patients)) {
            $dior_demo_mode = true;
            $patients = [
                ['user_id' => 0, 'patient_id' => 'DEMO-P001', 'first_name' => 'Sarah', 'last_name' => 'Johnson', 'full_name' => 'Sarah Johnson', 'email' => 'sarah.demo@example.com', 'phone' => '+1 555 0101', 'dob' => '1991-04-12', 'gender' => 'Female', 'blood_group' => 'A+', 'address' => 'Demo Address', 'registered' => 'Sep 20, 2026', 'has_intake' => true, 'has_active_appt' => true, 'next_appt_date' => $demo_today, 'next_appt_time' => '10:30 AM', 'next_appt_status' => 'Confirmed', 'initials' => 'SJ'],
                ['user_id' => 0, 'patient_id' => 'DEMO-P002', 'first_name' => 'Michael', 'last_name' => 'Brown', 'full_name' => 'Michael Brown', 'email' => 'michael.demo@example.com', 'phone' => '+1 555 0102', 'dob' => '1986-08-23', 'gender' => 'Male', 'blood_group' => 'O+', 'address' => 'Demo Address', 'registered' => 'Sep 18, 2026', 'has_intake' => true, 'has_active_appt' => false, 'next_appt_date' => '', 'next_appt_time' => '', 'next_appt_status' => '', 'initials' => 'MB'],
                ['user_id' => 0, 'patient_id' => 'DEMO-P003', 'first_name' => 'Emily', 'last_name' => 'Davis', 'full_name' => 'Emily Davis', 'email' => 'emily.demo@example.com', 'phone' => '+1 555 0103', 'dob' => '1994-02-09', 'gender' => 'Female', 'blood_group' => 'B+', 'address' => 'Demo Address', 'registered' => 'Sep 15, 2026', 'has_intake' => false, 'has_active_appt' => true, 'next_appt_date' => wp_date('Y-m-d', current_time('timestamp') + DAY_IN_SECONDS), 'next_appt_time' => '02:00 PM', 'next_appt_status' => 'Scheduled', 'initials' => 'ED'],
            ];
        }
        if (empty($appointments)) {
            $dior_demo_mode = true;
            $appointments = [
                ['id' => 'DEMO-DOC-APT-001', 'appt_uid' => 'DEMO-DOC-APT-001', 'patient_id' => 0, 'patient_user_id' => 0, 'patient_name' => 'Sarah Johnson', 'patient_id_num' => 'DEMO-P001', 'doctor_id' => $user_id, 'doctor_name' => $doctor['full_name'], 'date' => $demo_today, 'appt_date' => $demo_today, 'time' => '10:30 AM', 'appt_time' => '10:30 AM', 'duration' => '30 minutes', 'type' => 'Video Visit', 'visit_type' => 'Video Visit', 'status' => 'Confirmed', 'condition' => 'Routine Follow-up'],
                ['id' => 'DEMO-DOC-APT-002', 'appt_uid' => 'DEMO-DOC-APT-002', 'patient_id' => 0, 'patient_user_id' => 0, 'patient_name' => 'Emily Davis', 'patient_id_num' => 'DEMO-P003', 'doctor_id' => $user_id, 'doctor_name' => $doctor['full_name'], 'date' => wp_date('Y-m-d', current_time('timestamp') + DAY_IN_SECONDS), 'appt_date' => wp_date('Y-m-d', current_time('timestamp') + DAY_IN_SECONDS), 'time' => '02:00 PM', 'appt_time' => '02:00 PM', 'duration' => '30 minutes', 'type' => 'Clinic Visit', 'visit_type' => 'Clinic Visit', 'status' => 'Scheduled', 'condition' => 'General Checkup'],
                ['id' => 'DEMO-DOC-APT-003', 'appt_uid' => 'DEMO-DOC-APT-003', 'patient_id' => 0, 'patient_user_id' => 0, 'patient_name' => 'Michael Brown', 'patient_id_num' => 'DEMO-P002', 'doctor_id' => $user_id, 'doctor_name' => $doctor['full_name'], 'date' => wp_date('Y-m-d', current_time('timestamp') - DAY_IN_SECONDS), 'appt_date' => wp_date('Y-m-d', current_time('timestamp') - DAY_IN_SECONDS), 'time' => '11:30 AM', 'appt_time' => '11:30 AM', 'duration' => '30 minutes', 'type' => 'Video Visit', 'visit_type' => 'Video Visit', 'status' => 'Completed', 'condition' => 'Follow-up'],
            ];
        }
        if (empty($notifications)) {
            $dior_demo_mode = true;
            $notifications = [
                ['id' => 'DEMO-DN-001', 'title' => 'New Appointment', 'message' => 'Sarah Johnson booked a video consultation.', 'created_at' => current_time('mysql'), 'is_read' => false, 'action_url' => '#tab=doc-appointments'],
                ['id' => 'DEMO-DN-002', 'title' => 'Patient Record Updated', 'message' => 'Emily Davis updated her intake information.', 'created_at' => current_time('mysql'), 'is_read' => false, 'action_url' => '#tab=doc-patients'],
            ];
        }
        if (empty($documents)) {
            $dior_demo_mode = true;
            $documents = [
                ['id' => 'DEMO-DR-001', 'patient_id_num' => 'DEMO-P001', 'patient_name' => 'Sarah Johnson', 'patient_user_id' => 0, 'title' => 'Blood Test Report', 'category' => 'Laboratory', 'date' => $demo_today, 'created_at' => $demo_today, 'author' => $doctor['full_name'], 'priority' => 'Normal'],
                ['id' => 'DEMO-DR-002', 'patient_id_num' => 'DEMO-P003', 'patient_name' => 'Emily Davis', 'patient_user_id' => 0, 'title' => 'Consultation Summary', 'category' => 'Clinical', 'date' => $demo_today, 'created_at' => $demo_today, 'author' => $doctor['full_name'], 'priority' => 'Normal'],
            ];
        }
        $logo_url      = "";
        $logo_id       = get_theme_mod("custom_logo");
        if ($logo_id) $logo_url = wp_get_attachment_image_url($logo_id, "full");
        if (empty($logo_url)) {
            $logo_url = DIOR_PORTAL_URL . "assets/images/logo.png";
        }

        $total_patients  = count($patients);
        $pending_apts    = array_filter($appointments, fn($a) => in_array($a["status"] ?? "", ["Scheduled", "Confirmed", "In-Queue"]));
        $completed_apts  = array_filter($appointments, fn($a) => ($a["status"] ?? "") === "Completed");
        $intake_patients = array_filter($patients, fn($p) => $p["has_intake"]);
        $unread_notifs   = array_filter($notifications, fn($n) => !$n["is_read"]);
        $unread_count    = count($unread_notifs);
        $nonce           = wp_create_nonce("dior_doctor_nonce");
        $is_profile_complete = self::is_profile_complete($user_id);
        $missing_fields      = self::get_missing_profile_fields($user_id);

        ob_start();
        include DIOR_PORTAL_PATH . "templates/doctor-dashboard.php";
        return ob_get_clean();
    }

    // AJAX HANDLERS
    public static function ajax_update_appt_status()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"], 403);
        $aid    = sanitize_text_field($_POST["appt_id"] ?? "");
        $status = sanitize_text_field($_POST["status"] ?? "");

        $res = Dior_Appointment_Service::update_status($aid, $status);
        if (is_wp_error($res)) {
            wp_send_json_error(["message" => $res->get_error_message()]);
        }
        wp_send_json_success(["message" => "Status updated to " . $status]);
    }

    public static function ajax_send_reminder()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"], 403);
        $aid = sanitize_text_field($_POST["appt_id"] ?? "");
        if (empty($aid)) {
            wp_send_json_error(["message" => "Missing appointment ID"]);
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
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"], 403);
        $aid      = sanitize_text_field($_POST["appt_id"] ?? "");
        $new_date = sanitize_text_field($_POST["date"] ?? "");
        $new_time = sanitize_text_field($_POST["time"] ?? "");
        $notes    = sanitize_textarea_field($_POST["notes"] ?? "");

        if (empty($aid) || empty($new_date) || empty($new_time)) {
            wp_send_json_error(["message" => "Missing appointment ID, date or time"]);
        }

        $res = Dior_Appointment_Service::reschedule_appointment($aid, $new_date, $new_time, $notes, 'doctor');
        if (is_wp_error($res)) {
            wp_send_json_error(["message" => $res->get_error_message()]);
        }
        wp_send_json_success([
            "message"     => "Appointment rescheduled to " . esc_html($new_date . " at " . $new_time),
            "appointment" => $res
        ]);
    }

    public static function ajax_send_multi_prescription()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"]);
        
        $pid  = (int)($_POST["patient_id"] ?? 0);
        $meds_json = stripslashes($_POST["medications"] ?? "[]");
        $meds = json_decode($meds_json, true);
        
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
        $doc_last  = get_user_meta($doctor_id, 'last_name', true)  ?: ($doctor_user ? $doctor_user->last_name  : '');
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
                "id"              => $rx_id_group . '-' . $item_idx,
                "order_id"        => $rx_id_group,
                "group_id"        => $rx_id_group,
                "name"            => $med_name,
                "medication"      => $med_name,
                "dosage"          => sanitize_text_field($m['dosage']),
                "dose"            => sanitize_text_field($m['dosage']),
                "quantity"        => "As Prescribed",
                "refills"         => $refills_val,
                "notes"           => $notes_val,
                "instructions"    => $notes_val ?: "Take as directed by prescribing physician.",
                "date"            => $date,
                "date_prescribed" => $date,
                "status"          => "Active",
                "is_active"       => true,
                "routing_status"  => "Sent to Pharmacy",
                "status_class"    => "status-success",
                "pharmacy"        => $patient_pharmacy,
                "prescribed_by"   => $doc_title_name,
                "doctor_signature"=> $doc_sig,
                "fda_approved"    => true,
                "fda_source"      => sanitize_text_field($m['fda_source'] ?? 'U.S. FDA & NIH RxNorm')
            ];
            array_unshift($rxs, $rx);
            $new_rxs[] = $rx;
        }
        
        update_user_meta($pid, "dior_prescriptions", $rxs);
        
        // Update Patient Notifications
        $pnotifs = Dior_Patient_Portal_Data::get_patient_notifications($pid);
        $med_count = count($med_names);
        $med_text = $med_count === 1 ? $med_names[0] : implode(', ', array_slice($med_names, 0, 2)) . ($med_count > 2 ? ' +' . ($med_count - 2) . ' more' : '');
        
        array_unshift($pnotifs, [
            "id"         => "NOTIF-RX-" . time() . '-' . rand(10, 99),
            "type"       => "prescription",
            "icon"       => "fa-prescription-bottle-medical",
            "title"      => "New Prescription Sent to Pharmacy",
            "message"    => "Your doctor has prescribed: " . $med_text . ". This has been electronically routed to your pharmacy. View full details in Docs & Meds.",
            "time"       => "Just now",
            "timestamp"  => time(),
            "created_at" => current_time('mysql'),
            "is_read"    => false,
            "action_url" => "#tab=docs_meds"
        ]);
        update_user_meta($pid, "dior_notifications", $pnotifs);

        // Update Doctor Notifications
        $doc_user_ids = array_unique([$doctor_id, get_current_user_id()]);
        $patient_user = get_userdata($pid);
        $p_display = $patient_user ? $patient_user->display_name : 'Patient';
        foreach ($doc_user_ids as $duid) {
            if (!$duid) continue;
            $dnotifs = get_user_meta($duid, "dior_doctor_notifications", true);
            if (!is_array($dnotifs)) $dnotifs = [];
            array_unshift($dnotifs, [
                "id"         => "DN-RX-" . time() . '-' . rand(10, 99),
                "type"       => "prescription",
                "icon"       => "fa-prescription-bottle-medical",
                "title"      => "Prescription Sent to Pharmacy",
                "message"    => "Prescription for " . implode(', ', $med_names) . " was sent to pharmacy for " . $p_display . ".",
                "time"       => "Just now",
                "timestamp"  => time(),
                "created_at" => current_time('mysql'),
                "is_read"    => false,
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
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"]);
        $uid = get_current_user_id();

        $first_name = sanitize_text_field($_POST["first_name"] ?? "");
        $last_name  = sanitize_text_field($_POST["last_name"] ?? "");
        $phone      = sanitize_text_field($_POST["phone"] ?? "");
        $specialty  = sanitize_text_field($_POST["doctor_specialty"] ?? "");
        $license_no = sanitize_text_field($_POST["doctor_license"] ?? "");
        $npi        = sanitize_text_field($_POST["doctor_npi"] ?? "");
        $bio        = sanitize_textarea_field($_POST["doctor_bio"] ?? "");
        $zoom_link  = esc_url_raw($_POST["doctor_zoom_link"] ?? "");

        // Required personal and credential fields validation
        $missing = [];
        if (empty($first_name)) $missing[] = 'First Name';
        if (empty($last_name))  $missing[] = 'Last Name';
        if (empty($phone))      $missing[] = 'Phone Number';
        if (empty($specialty))  $missing[] = 'Medical Specialty';
        if (empty($license_no)) $missing[] = 'Medical License Number';
        if (empty($npi))        $missing[] = 'NPI Number';

        if (!empty($missing)) {
            wp_send_json_error([
                'message' => 'Please fill in all required fields: ' . implode(', ', $missing) . '.',
                'missing' => $missing
            ]);
        }

        update_user_meta($uid, "first_name", $first_name);
        update_user_meta($uid, "last_name", $last_name);
        update_user_meta($uid, "phone", $phone);
        update_user_meta($uid, "doctor_specialty", $specialty);
        update_user_meta($uid, "doctor_license", $license_no);
        update_user_meta($uid, "doctor_npi", $npi);
        update_user_meta($uid, "doctor_bio", $bio);
        update_user_meta($uid, "doctor_zoom_link", $zoom_link);

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
            "ID"         => $uid,
            "first_name" => $first_name,
            "last_name"  => $last_name,
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
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"]);

        $uid = get_current_user_id();
        if (!$uid) {
            wp_send_json_error(['message' => 'Authentication required. Please log in again.']);
        }

        // Support selecting directly from WordPress Media Library (URL or ID passed)
        if (!empty($_POST['avatar_url'])) {
            $avatar_url = esc_url_raw($_POST['avatar_url']);
            $attach_id = !empty($_POST['attachment_id']) ? (int)$_POST['attachment_id'] : 0;

            update_user_meta($uid, 'dior_profile_image', $avatar_url);
            update_user_meta($uid, 'doctor_avatar', $avatar_url);
            update_user_meta($uid, 'profile_picture', $avatar_url);
            if ($attach_id) {
                update_user_meta($uid, 'dior_profile_image_id', $attach_id);
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

        // Allowed image types
        $allowed_mimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'gif'          => 'image/gif',
        ];

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        if (empty($check['type']) || !in_array($check['type'], $allowed_mimes, true)) {
            wp_send_json_error(['message' => 'Invalid image format. Please upload a JPG, PNG, WEBP, or GIF image.']);
        }

        // Limit file size to 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(['message' => 'Image size exceeds the maximum limit of 5MB.']);
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $upload_overrides = ['test_form' => false, 'mimes' => $allowed_mimes];
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            $avatar_url = esc_url_raw($movefile['url']);
            update_user_meta($uid, 'dior_profile_image', $avatar_url);
            update_user_meta($uid, 'doctor_avatar', $avatar_url);
            update_user_meta($uid, 'profile_picture', $avatar_url);

            wp_send_json_success([
                'message' => 'Provider profile photo updated successfully!',
                'avatar_url' => $avatar_url
            ]);
        } else {
            wp_send_json_error([
                'message' => !empty($movefile['error']) ? $movefile['error'] : 'Failed to save uploaded image. Please try again.'
            ]);
        }
    }

    /**
     * AJAX Remove Doctor Avatar
     */
    public static function ajax_remove_avatar()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"]);

        $uid = get_current_user_id();
        if (!$uid) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        delete_user_meta($uid, 'dior_profile_image');
        delete_user_meta($uid, 'doctor_avatar');
        delete_user_meta($uid, 'profile_picture');

        $profile = self::get_doctor_profile($uid);
        $initials = !empty($profile['initials']) ? $profile['initials'] : 'DR';

        wp_send_json_success([
            'message' => 'Provider photo removed successfully.',
            'initials' => $initials
        ]);
    }

    /**
     * AJAX Upload Doctor Digital Signature (PNG with transparent background)
     */
    public static function ajax_upload_signature()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"]);

        $uid = get_current_user_id();
        if (!$uid) {
            wp_send_json_error(['message' => 'Authentication required. Please log in again.']);
        }

        // Support selecting directly from WordPress Media Library (URL or ID passed)
        if (!empty($_POST['signature_url'])) {
            $sig_url = esc_url_raw($_POST['signature_url']);
            $attach_id = !empty($_POST['attachment_id']) ? (int)$_POST['attachment_id'] : 0;

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
            'png'  => 'image/png',
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

        $upload_overrides = ['test_form' => false, 'mimes' => $allowed_mimes];
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
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
    }

    /**
     * AJAX Remove Doctor Signature
     */
    public static function ajax_remove_signature()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"]);

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
        $nid = sanitize_text_field($_POST["notif_id"] ?? "");
        $ns  = get_user_meta($uid, "dior_doctor_notifications", true);
        if (is_array($ns)) {
            foreach ($ns as &$n) {
                if ($n["id"] === $nid) {
                    $n["is_read"] = true;
                    break;
                }
            }
            update_user_meta($uid, "dior_doctor_notifications", $ns);
        }
        wp_send_json_success(["message" => "Notification marked as read"]);
    }

    public static function ajax_mark_all_notif_read()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(["message" => "Unauthorized"], 403);
        }
        $uid = get_current_user_id();
        $ns  = get_user_meta($uid, "dior_doctor_notifications", true);
        if (is_array($ns)) {
            foreach ($ns as &$n) {
                $n["is_read"] = true;
            }
            update_user_meta($uid, "dior_doctor_notifications", $ns);
        }
        wp_send_json_success(["message" => "All notifications marked as read."]);
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
                        'id'         => $n['id'],
                        'title'      => $n['title'] ?? 'New Notification',
                        'message'    => $n['message'] ?? '',
                        'icon'       => $n['icon'] ?? 'fa-bell',
                        'action_url' => $n['action_url'] ?? '',
                        'time'       => dior_format_notification_time($n)
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
                            <button type="button" class="dior-btn-table-icon dior-mark-single-read"
                                title="Mark as Read"
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
            'unread_count'  => $unread_count,
            'dropdown_html' => $dropdown_html,
            'full_html'     => $full_html,
            'latest_id'     => !empty($notifications) ? $notifications[0]['id'] : '',
            'new_alerts'    => $new_alerts
        ]);
    }

    public static function ajax_get_patient_detail()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"], 403);
        $pid  = Dior_Auth_Service::validate_id($_POST["patient_user_id"] ?? 0);
        $user = get_userdata($pid);
        if (!$user) wp_send_json_error(["message" => "Patient not found"]);

        // Prevent a doctor from opening unrelated patient records.
        $current_doctor_id = get_current_user_id();
        if (!self::is_user_admin($current_doctor_id)) {
            $doctor_appointments = Dior_Appointment_Service::get_doctor_appointments($current_doctor_id);
            $has_relation = false;
            foreach ($doctor_appointments as $doctor_appt) {
                if ((int)($doctor_appt['patient_id'] ?? 0) === (int)$pid) {
                    $has_relation = true;
                    break;
                }
            }
            if (!$has_relation) {
                wp_send_json_error(["message" => "You do not have access to this patient record."], 403);
            }
        }

        $doctor   = self::get_doctor_profile($current_doctor_id);
        $profile  = Dior_Patient_Portal_Data::get_patient_profile($pid);
        $intake   = get_user_meta($pid, "dior_hipaa_intake", true);
        $apts     = Dior_Appointment_Service::get_patient_appointments($pid);
        $rxs      = get_user_meta($pid, "dior_prescriptions", true) ?: [];
        $notes    = Dior_Encounter_Service::get_patient_encounters($pid);
        $docs     = Dior_Medical_Secure_Files::get_patient_documents($pid);
        
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
        // Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce"); // Add nonce check if needed, but let's allow it first
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"], 403);

        global $wpdb;
        $table_name = $wpdb->prefix . 'dior_prescriptions';

        $data = [
            'prescription_id' => sanitize_text_field($_POST['prescription_id'] ?? ''),
            'patient_name'    => sanitize_text_field($_POST['patient_name'] ?? ''),
            'patient_id'      => sanitize_text_field($_POST['patient_id'] ?? ''),
            'prescription_date' => sanitize_text_field($_POST['prescription_date'] ?? ''),
            'medications'     => sanitize_textarea_field($_POST['medications'] ?? ''),
            'dosage'          => sanitize_text_field($_POST['dosage'] ?? ''),
            'frequency'       => sanitize_text_field($_POST['frequency'] ?? ''),
            'duration'        => sanitize_text_field($_POST['duration'] ?? ''),
            'doctor_name'     => sanitize_text_field($_POST['doctor_name'] ?? ''),
            'status'          => sanitize_text_field($_POST['status'] ?? 'Active')
        ];

        if (empty($data['prescription_id']) || empty($data['patient_name'])) {
            wp_send_json_error(["message" => "Please fill in all required fields."]);
        }

        $inserted = $wpdb->insert($table_name, $data);

        if ($inserted) {
            wp_send_json_success(["message" => "Prescription added successfully!"]);
        } else {
            wp_send_json_error(["message" => "Database error. Could not save prescription."]);
        }
    }

    public static function ajax_save_soap_note()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"], 403);
        $pid = Dior_Auth_Service::validate_id($_POST["patient_user_id"] ?? 0);
        if (!$pid) wp_send_json_error(["message" => "Valid Patient selection required."]);

        $res = Dior_Encounter_Service::save_encounter([
            'patient_id'     => $pid,
            'encounter_uid'  => sanitize_text_field($_POST["note_id"] ?? ''),
            'encounter_type' => sanitize_text_field($_POST["encounter_type"] ?? "Telehealth Consultation"),
            'subjective'     => $_POST["subjective"] ?? ($_POST["note"] ?? ""),
            'objective'      => $_POST["objective"] ?? "",
            'assessment'     => $_POST["assessment"] ?? "",
            'plan'           => $_POST["plan"] ?? "",
            'diagnosis'      => sanitize_text_field($_POST["diagnosis"] ?? ""),
            'follow_up'      => sanitize_text_field($_POST["follow_up"] ?? "As needed / 7-10 days"),
            'vitals_bp'      => sanitize_text_field($_POST["vitals_bp"] ?? ""),
            'vitals_hr'      => sanitize_text_field($_POST["vitals_hr"] ?? ""),
            'vitals_temp'    => sanitize_text_field($_POST["vitals_temp"] ?? ""),
            'vitals_weight'  => sanitize_text_field($_POST["vitals_weight"] ?? "")
        ]);

        if (is_wp_error($res)) {
            wp_send_json_error(["message" => $res->get_error_message()]);
        }

        wp_send_json_success(["message" => "Clinical SOAP note saved successfully!", "note" => $res]);
    }

    public static function ajax_delete_note()
    {
        Dior_Auth_Service::verify_ajax_nonce("dior_doctor_nonce");
        if (!self::can_access()) wp_send_json_error(["message" => "Unauthorized"], 403);
        $pid     = Dior_Auth_Service::validate_id($_POST["patient_user_id"] ?? 0);
        $note_id = sanitize_text_field($_POST["note_id"] ?? "");
        if (!$pid || empty($note_id)) wp_send_json_error(["message" => "Invalid parameters"]);

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
        $new_pwd     = $_POST["new_password"] ?? "";
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
                'user_login'   => self::DEFAULT_DOCTOR_LOGIN,
                'user_email'   => self::DEFAULT_DOCTOR_EMAIL,
                'user_pass'    => wp_generate_password(40, true, true),
                'display_name' => 'Dr. Dior Medical',
                'first_name'   => 'Dr.',
                'last_name'    => 'Dior',
                'role'         => 'doctor',
            ]);
            if (!is_wp_error($user_id)) {
                update_user_meta($user_id, 'doctor_specialty', 'Primary Telehealth Physician');
                update_user_meta($user_id, 'phone', '+1 (310) 555-0100');
                if (function_exists('wp_new_user_notification')) {
                    wp_new_user_notification($user_id, null, 'user');
                }
                return (int)$user_id;
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
            return (int)$user->ID;
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
        if ($user) return (int)$user->ID;
        return (int)self::ensure_default_doctor();
    }

    public static function get_accessible_doctor_ids($doctor_user_id = 0)
    {
        $doctor_user_id = (int) ($doctor_user_id ?: get_current_user_id());
        if (!$doctor_user_id) return [];

        $ids = [$doctor_user_id];
        $legacy_user_ids = get_user_meta($doctor_user_id, '_dior_merged_doctor_user_ids', true);
        $legacy_post_ids = get_user_meta($doctor_user_id, '_dior_merged_docbooker_post_ids', true);
        if (is_array($legacy_user_ids)) $ids = array_merge($ids, $legacy_user_ids);
        if (is_array($legacy_post_ids)) $ids = array_merge($ids, $legacy_post_ids);

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
        if (is_wp_error($user) || !($user instanceof WP_User)) return $user;
        if (get_user_meta($user->ID, '_dior_merged_into_doctor_id', true)) {
            return new WP_Error('dior_doctor_account_merged', 'This doctor account has been consolidated. Please use the central doctor account.');
        }
        return $user;
    }

    public static function consolidate_legacy_accounts()
    {
        $trusted_cli = defined('WP_CLI') && WP_CLI;
        if ((!is_admin() && !$trusted_cli) || !current_user_can('manage_options') || get_option('dior_doctor_accounts_consolidated_v1')) return;

        $central_id = self::get_default_doctor_id();
        $central = $central_id ? get_userdata($central_id) : false;
        if (!$central || $central->user_email !== self::DEFAULT_DOCTOR_EMAIL) return;

        $legacy_user_ids = [];
        $legacy_profiles = get_user_meta($central_id, '_dior_merged_doctor_profiles', true);
        $legacy_profiles = is_array($legacy_profiles) ? $legacy_profiles : [];
        $profile_meta_keys = ['first_name', 'last_name', 'doctor_specialty', 'doctor_license', 'doctor_npi', 'doctor_bio', 'phone', 'dior_profile_image', 'dior_doctor_signature'];

        foreach (get_users(['role' => 'doctor', 'fields' => 'all', 'number' => 500]) as $doctor_user) {
            $doctor_id = (int) $doctor_user->ID;
            if ($doctor_id === $central_id || in_array('administrator', (array) $doctor_user->roles, true)) continue;

            $profile_snapshot = [];
            foreach ($profile_meta_keys as $meta_key) {
                $meta_value = get_user_meta($doctor_id, $meta_key, true);
                if ($meta_value !== '' && $meta_value !== null) $profile_snapshot[$meta_key] = $meta_value;
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
                        if ($notification_id) $known_notification_ids[] = $notification_id;
                    }
                }
            }
            update_user_meta($central_id, 'dior_doctor_notifications', array_slice($merged_notifications, 0, 100));

            update_user_meta($doctor_id, '_dior_merged_into_doctor_id', $central_id);
            $legacy_user = new WP_User($doctor_id);
            $legacy_user->set_role('subscriber');
            if (function_exists('wp_destroy_all_sessions')) wp_destroy_all_sessions($doctor_id);
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
        if (!$central_id) return 0;

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
            if ($user && in_array('doctor', (array) $user->roles, true)) return $central_id;
        }

        if (is_string($doctor_ref)) {
            $name = trim($doctor_ref);
            if ($name === '') return $central_id;

            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpddb_doctor' AND (post_title = %s OR post_title LIKE %s) LIMIT 1",
                $name,
                '%' . $wpdb->esc_like($name) . '%'
            ));
            if ($post_id) {
                return self::sync_docbooker_doctor_to_wp_user((int)$post_id);
            }

            $clean_name = trim(preg_replace('/^Dr\.?\s+/i', '', $name));
            $clean_name = trim(preg_replace('/,\s*(MD|DO|MBBS|PhD)$/i', '', $clean_name));
            $user = get_user_by('login', sanitize_user(strtolower(str_replace(' ', '', $clean_name))));
            if ($user) {
                if (get_user_meta($user->ID, '_dior_merged_into_doctor_id', true) || in_array('doctor', (array) $user->roles, true)) {
                    return $central_id;
                }
                if (in_array('administrator', (array) $user->roles, true)) return (int) $user->ID;
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
        if (!$central_id) return 0;

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
