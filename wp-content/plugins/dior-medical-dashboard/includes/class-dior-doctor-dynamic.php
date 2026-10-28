<?php
/**
 * Dior Medical - Doctor Dashboard Dynamic Operations.
 * Production-oriented CRUD/AJAX layer for doctor-only dashboard actions.
 */
if (!defined('ABSPATH')) exit;

class Dior_Doctor_Dynamic {
    const NONCE = 'dior_doctor_nonce';

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'install_schema'], 20);
        $actions = [
            'appointment_delete','appointment_update','patient_status','patient_delete','patient_bulk_delete','patient_add','patient_update',
            'get_patient_profile','save_consultation','delete_consultation','document_save','document_delete','document_update_meta',
            'review_save','review_delete','review_bulk_delete','review_get','telemed_save','telemed_delete','notification_read',
            'notification_all_read','settings_save','chat_send','chat_get'
        ];
        foreach ($actions as $action) add_action('wp_ajax_dior_doc_' . $action, [__CLASS__, 'ajax_' . $action]);
        add_action('wp_ajax_dior_doctor_add_patient', [__CLASS__, 'ajax_patient_add']);
        add_action('wp_ajax_dior_doctor_update_patient', [__CLASS__, 'ajax_patient_update']);
        add_action('wp_ajax_dior_doctor_add_prescription', [__CLASS__, 'ajax_prescription_add']);
        add_action('wp_ajax_dior_doc_prescription_add', [__CLASS__, 'ajax_prescription_add']);
        add_action('wp_ajax_dior_doc_prescription_delete', [__CLASS__, 'ajax_prescription_delete']);
    }

    private static function auth() {
        if (!is_user_logged_in() || !Dior_Doctor_Dashboard::can_access_for_dynamic()) {
            wp_send_json_error(['message'=>'Unauthorized'], 403);
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', self::NONCE)) {
            wp_send_json_error(['message'=>'Security check failed.'], 403);
        }
    }
    private static function doctor_id() {
        if (class_exists('Dior_Doctor_Dashboard')) {
            $context = Dior_Doctor_Dashboard::get_single_doctor_context(get_current_user_id());
            return (int) ($context['user_id'] ?? 0);
        }
        return (int) get_current_user_id();
    }
    private static function doctor_scope_ids() {
        if (class_exists('Dior_Doctor_Dashboard')) {
            $context = Dior_Doctor_Dashboard::get_single_doctor_context(get_current_user_id());
            $ids = (array) ($context['ids'] ?? []);
        } else {
            $ids = class_exists('Dior_Doctor_Resolver')
                ? Dior_Doctor_Resolver::get_accessible_doctor_ids(self::doctor_id())
                : [self::doctor_id()];
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
    }
    private static function doctor_scope_placeholders() {
        return implode(',', array_fill(0, count(self::doctor_scope_ids()), '%d'));
    }
    private static function ensure_patient_row($pid) {
        global $wpdb; $t=self::table('patients'); $p=self::patient($pid); if(!$p) return; $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d",(int)$pid)); if(!$exists){$wpdb->insert($t,['user_id'=>(int)$pid,'patient_uid'=>$p['patient_uid'],'first_name'=>$p['first_name']??'','last_name'=>$p['last_name']??'','email'=>$p['email']??'','phone'=>$p['phone']??'','status'=>$p['status']??'active','avatar_url'=>$p['avatar_url']??'','created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')]);} }
    private static function doctor_can_manage_appointment($a) {
        if (!is_array($a) || !class_exists('Dior_Doctor_Dashboard')) {
            return false;
        }
        $context = Dior_Doctor_Dashboard::get_single_doctor_context(get_current_user_id());
        return Dior_Doctor_Dashboard::appointment_belongs_to_single_doctor($a, $context);
    }
    private static function table($name) { global $wpdb; return $wpdb->prefix . 'dior_' . $name; }

    public static function install_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();
        $tables = [];
        $tables[] = "CREATE TABLE " . self::table('reviews') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            review_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL,
            subject VARCHAR(190) NOT NULL DEFAULT '',
            rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
            comment TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Published',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY review_uid (review_uid),
            KEY patient_id (patient_id),
            KEY doctor_id (doctor_id),
            KEY status (status)
        ) $cc;";
        $tables[] = "CREATE TABLE " . self::table('telemedicine') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tele_uid VARCHAR(64) NOT NULL,
            appointment_uid VARCHAR(64) NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL,
            room_url VARCHAR(500) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Scheduled',
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tele_uid (tele_uid),
            KEY appointment_uid (appointment_uid),
            KEY patient_id (patient_id),
            KEY doctor_id (doctor_id),
            KEY status (status)
        ) $cc;";
        $tables[] = "CREATE TABLE " . self::table('chat_messages') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL,
            sender_user_id BIGINT UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY appointment_uid (appointment_uid),
            KEY patient_id (patient_id),
            KEY doctor_id (doctor_id),
            KEY created_at (created_at)
        ) $cc;";
        $tables[] = "CREATE TABLE " . self::table('doctor_settings') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY doctor_setting (doctor_id,setting_key)
        ) $cc;";
        foreach ($tables as $sql) dbDelta($sql);
    }

    public static function patients($doctor_id=0) {
        global $wpdb;
        $doctor_id = $doctor_id ?: self::doctor_id();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dior_patients WHERE status <> 'deleted' ORDER BY created_at DESC"), ARRAY_A);
        if (!$rows) {
            $users = get_users(['role__in'=>['patient','subscriber','customer'],'number'=>500]);
            foreach ($users as $u) {
                $pid = get_user_meta($u->ID,'patient_id',true) ?: 'DM-' . (10000+$u->ID);
                $rows[] = ['id'=>0,'user_id'=>$u->ID,'patient_uid'=>$pid,'first_name'=>$u->first_name,'last_name'=>$u->last_name,'email'=>$u->user_email,'phone'=>get_user_meta($u->ID,'phone',true),'status'=>'active','avatar_url'=>get_user_meta($u->ID,'dior_profile_image',true)];
            }
        }
        return $rows;
    }
    public static function patient($id_or_uid) {
        global $wpdb;
        $t=$wpdb->prefix.'dior_patients';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d OR patient_uid=%s LIMIT 1", (int)$id_or_uid, sanitize_text_field($id_or_uid)), ARRAY_A);
        if ($row) return $row;
        $u=get_userdata((int)$id_or_uid); if(!$u) return null;
        return ['id'=>0,'user_id'=>$u->ID,'patient_uid'=>get_user_meta($u->ID,'patient_id',true) ?: 'DM-'.(10000+$u->ID),'first_name'=>$u->first_name,'last_name'=>$u->last_name,'email'=>$u->user_email,'phone'=>get_user_meta($u->ID,'phone',true),'status'=>'active','avatar_url'=>get_user_meta($u->ID,'dior_profile_image',true)];
    }
    public static function patient_name($pid) { $p=self::patient($pid); return $p ? trim($p['first_name'].' '.$p['last_name']) : 'Patient'; }
    public static function patient_belongs_to_doctor($pid,$did) {
        if (current_user_can('manage_options')) return true;
        $user = get_userdata($did);
        if ($user && array_intersect(['doctor', 'dior_doctor', 'administrator'], (array)$user->roles)) return true;
        if (user_can($did, 'doctor') || user_can($did, 'administrator')) return true;
        if (class_exists('Dior_Auth_Service') && Dior_Auth_Service::can_access_patient($pid, $did)) return true;
        foreach (Dior_Appointment_Service::get_doctor_appointments($did) as $a) if ((int)($a['patient_id']??0)===(int)$pid) return true;
        return false;
    }

    public static function ajax_appointment_delete() {
        self::auth(); global $wpdb; $uid=sanitize_text_field($_POST['appt_uid']??''); $a=Dior_Appointment_Service::get_appointment($uid);
        if(!$a || !self::doctor_can_manage_appointment($a)) wp_send_json_error(['message'=>'Appointment not found or access denied.']);
        $ok=$wpdb->delete($wpdb->prefix.'dior_appointments',['appt_uid'=>$uid],['%s']);
        if($ok!==false){ if(class_exists('Dior_Audit_Service')) Dior_Audit_Service::log('appointment_delete','appointment',$uid,(int)$a['patient_id'],'success'); wp_send_json_success(['message'=>'Appointment permanently deleted.']); }
        wp_send_json_error(['message'=>'Unable to delete appointment.']);
    }
    public static function ajax_appointment_update() {
        self::auth(); global $wpdb; $uid=sanitize_text_field($_POST['appt_uid']??''); $a=Dior_Appointment_Service::get_appointment($uid); if(!$a) wp_send_json_error(['message'=>'Appointment not found.']);
        if(!self::doctor_can_manage_appointment($a)) wp_send_json_error(['message'=>'Access denied.'],403);
        $date=sanitize_text_field($_POST['date']??$a['appt_date']); $time=sanitize_text_field($_POST['time']??$a['appt_time']); $status=sanitize_text_field($_POST['status']??$a['status']);
        $patient_id=absint($_POST['patient_id']??$a['patient_id']);
        $allowed=['Pending','Confirmed','In Progress','Completed','Cancelled','No Show']; if(!in_array($status,$allowed,true)) wp_send_json_error(['message'=>'Invalid status.']);
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !preg_match('/^\d{1,2}:\d{2}(\s?[AP]M)?$/i',$time)) wp_send_json_error(['message'=>'Invalid appointment date or time.']);
        if(!$patient_id || !get_userdata($patient_id)) wp_send_json_error(['message'=>'Please select a valid patient.']);
        if(!current_user_can('manage_options') && !self::patient_belongs_to_doctor($patient_id,self::doctor_id())) wp_send_json_error(['message'=>'Selected patient is not assigned to your account.'],403);
        $conflict=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dior_appointments WHERE patient_id=%d AND appt_date=%s AND appt_time=%s AND appt_uid<>%s AND status NOT IN('Cancelled','No Show') AND is_deleted=0 LIMIT 1",$patient_id,$date,$time,$uid));
        if($conflict) wp_send_json_error(['message'=>'This patient already has an active appointment at that time.']);
        $old=$a; $updated=$wpdb->update($wpdb->prefix.'dior_appointments',['patient_id'=>$patient_id,'appt_date'=>$date,'appt_time'=>$time,'status'=>$status,'updated_at'=>current_time('mysql')],['appt_uid'=>$uid]);
        if($updated===false) wp_send_json_error(['message'=>'The appointment could not be saved. Please try again.'],500);
        if(method_exists('Dior_Appointment_Service','log_history_public')) Dior_Appointment_Service::log_history_public($old,'doctor_update',['new_status'=>$status,'new_date'=>$date,'new_time'=>$time,'new_patient_id'=>$patient_id]);
        if(class_exists('Dior_Notification_Service')) Dior_Notification_Service::send_appointment_rescheduled($uid,$old['appt_date'],$old['appt_time'],$date,$time,'doctor');
        wp_send_json_success(['message'=>'Appointment updated successfully.','appointment'=>Dior_Appointment_Service::get_appointment($uid)]);
    }

    public static function ajax_patient_status(){ self::auth(); global $wpdb; $pid=(int)($_POST['patient_id']??0); self::ensure_patient_row($pid); if(!self::patient_belongs_to_doctor($pid,self::doctor_id())) wp_send_json_error(['message'=>'Access denied.'],403); self::ensure_patient_row($pid); $status=sanitize_key($_POST['status']??'active'); if(!in_array($status,['active','inactive','blocked'],true)) wp_send_json_error(['message'=>'Invalid status.']); $wpdb->update(self::table('patients'),['status'=>$status,'updated_at'=>current_time('mysql')],['user_id'=>$pid]); update_user_meta($pid,'dior_patient_status',$status); wp_send_json_success(['message'=>'Patient status updated.']); }
    public static function ajax_patient_delete(){ self::auth(); global $wpdb; $pid=(int)($_POST['patient_id']??0); self::ensure_patient_row($pid); if(!self::patient_belongs_to_doctor($pid,self::doctor_id())) wp_send_json_error(['message'=>'Access denied.'],403); $wpdb->update(self::table('patients'),['status'=>'deleted','updated_at'=>current_time('mysql')],['user_id'=>$pid]); update_user_meta($pid,'dior_patient_status','deleted'); wp_send_json_success(['message'=>'Patient removed from the database.']); }
    public static function ajax_patient_bulk_delete(){ self::auth(); $ids=array_map('intval',(array)($_POST['patient_ids']??[])); if(!$ids) wp_send_json_error(['message'=>'Select at least one patient.']); foreach($ids as $id){ if(self::patient_belongs_to_doctor($id,self::doctor_id())){ $_POST['patient_id']=$id; self::delete_patient_quiet($id); } } wp_send_json_success(['message'=>'Selected patients deleted.']); }
    private static function delete_patient_quiet($id){ global $wpdb; $wpdb->update(self::table('patients'),['status'=>'deleted','updated_at'=>current_time('mysql')],['user_id'=>(int)$id]); update_user_meta($id,'dior_patient_status','deleted'); }

    public static function ajax_patient_add() {
        self::auth();
        global $wpdb;

        $first_name = trim(sanitize_text_field($_POST['first_name'] ?? ''));
        $last_name  = trim(sanitize_text_field($_POST['last_name'] ?? ''));
        $email      = trim(sanitize_email($_POST['email'] ?? ''));
        $phone      = trim(sanitize_text_field($_POST['phone'] ?? ''));
        $gender     = trim(sanitize_text_field($_POST['gender'] ?? ''));
        $dob        = trim(sanitize_text_field($_POST['dob'] ?? ''));
        $blood_group= trim(sanitize_text_field($_POST['blood_group'] ?? ''));
        $height     = trim(sanitize_text_field($_POST['height'] ?? ''));
        $weight     = trim(sanitize_text_field($_POST['weight'] ?? ''));
        $address    = trim(sanitize_text_field($_POST['address'] ?? ''));
        $city       = trim(sanitize_text_field($_POST['city'] ?? ''));
        $state      = trim(sanitize_text_field($_POST['state'] ?? ''));
        $zip        = trim(sanitize_text_field($_POST['zip'] ?? ''));
        $country    = trim(sanitize_text_field($_POST['country'] ?? 'United States of America')) ?: 'United States of America';

        // Emergency contact
        $em_name    = trim(sanitize_text_field($_POST['emergency_name'] ?? ''));
        $em_rel     = trim(sanitize_text_field($_POST['emergency_relation'] ?? ''));
        $em_phone   = trim(sanitize_text_field($_POST['emergency_phone'] ?? ''));

        // Pharmacy
        $pharm_name = trim(sanitize_text_field($_POST['preferred_pharmacy_name'] ?? ''));
        $pharm_addr = trim(sanitize_text_field($_POST['preferred_pharmacy_address'] ?? ''));
        $pharm_phone= trim(sanitize_text_field($_POST['preferred_pharmacy_phone'] ?? ''));

        // Password & options
        $custom_pass= trim($_POST['password'] ?? '');
        $password   = !empty($custom_pass) ? $custom_pass : wp_generate_password(12, true, false);
        $send_email = !empty($_POST['send_welcome_email']) ? 1 : 0;

        // Validation - Required fields matching Patient Dashboard
        $missing = [];
        if (empty($first_name)) $missing[] = 'First Name';
        if (empty($last_name))  $missing[] = 'Last Name';
        if (empty($email))      $missing[] = 'Email Address';
        if (empty($phone))      $missing[] = 'Phone Number';
        if (empty($dob))        $missing[] = 'Date of Birth';
        if (empty($gender))     $missing[] = 'Gender';
        if (empty($address))    $missing[] = 'Street Address';
        if (empty($city))       $missing[] = 'City';
        if (empty($state))      $missing[] = 'State';
        if (empty($zip))        $missing[] = 'ZIP Code';

        if (!empty($missing)) {
            wp_send_json_error(['message' => 'Please fill in all required fields: ' . implode(', ', $missing) . '.']);
        }

        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Please provide a valid email address.']);
        }

        if (email_exists($email)) {
            wp_send_json_error(['message' => 'A patient account with this email (' . esc_html($email) . ') already exists.']);
        }

        // DOB & Age Verification (Must be 18+ and not in future)
        $dob_time = strtotime($dob);
        $now = time();
        if (!$dob_time || $dob_time > $now) {
            wp_send_json_error(['message' => 'Invalid Date of Birth: Birth date cannot be in the future.']);
        }

        $calc_age = (int) date('Y', $now) - (int) date('Y', $dob_time);
        if (date('md', $now) < date('md', $dob_time)) {
            $calc_age--;
        }

        if ($calc_age < 18) {
            wp_send_json_error(['message' => 'Eligibility Requirement: Patient must be at least 18 years of age (Calculated age: ' . max(0, $calc_age) . ' years).']);
        }

        // Create WordPress user
        $username = sanitize_user($email, true);
        if (username_exists($username)) {
            $username = strtolower($first_name . '.' . $last_name . '.' . wp_rand(100, 999));
        }

        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name),
            'role'         => 'subscriber',
        ]);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        // Generate Canonical Patient ID
        $patient_uid = 'DM-' . (10000 + ($user_id % 90000));
        update_user_meta($user_id, 'patient_id', $patient_uid);

        // Save All User Meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'phone', $phone);
        update_user_meta($user_id, 'billing_phone', $phone);
        update_user_meta($user_id, 'dob', date('Y-m-d', $dob_time));
        update_user_meta($user_id, 'date_of_birth', date('Y-m-d', $dob_time));
        update_user_meta($user_id, 'gender', $gender);
        update_user_meta($user_id, 'blood_group', $blood_group);
        update_user_meta($user_id, 'height', $height);
        update_user_meta($user_id, 'weight', $weight);
        update_user_meta($user_id, 'address', $address);
        update_user_meta($user_id, 'billing_address_1', $address);
        update_user_meta($user_id, 'city', $city);
        update_user_meta($user_id, 'billing_city', $city);
        update_user_meta($user_id, 'state', $state);
        update_user_meta($user_id, 'billing_state', $state);
        update_user_meta($user_id, 'zip', $zip);
        update_user_meta($user_id, 'zip_code', $zip);
        update_user_meta($user_id, 'billing_postcode', $zip);
        update_user_meta($user_id, 'country', $country);
        update_user_meta($user_id, 'billing_country', $country);
        update_user_meta($user_id, 'emergency_name', $em_name);
        update_user_meta($user_id, 'emergency_relation', $em_rel);
        update_user_meta($user_id, 'emergency_phone', $em_phone);
        update_user_meta($user_id, 'preferred_pharmacy_name', $pharm_name);
        update_user_meta($user_id, 'preferred_pharmacy_address', $pharm_addr);
        update_user_meta($user_id, 'preferred_pharmacy_phone', $pharm_phone);
        update_user_meta($user_id, 'dior_patient_status', 'active');
        update_user_meta($user_id, 'is_18_verified', 'Verified (18+)');
        update_user_meta($user_id, 'state_eligible', 'Active in State');

        // Sync with Relational Database
        if (class_exists('Dior_Patient_Portal_Data')) {
            Dior_Patient_Portal_Data::sync_relational_patient($user_id);
        } else {
            $table = $wpdb->prefix . 'dior_patients';
            $wpdb->replace($table, [
                'user_id'     => $user_id,
                'patient_uid' => $patient_uid,
                'first_name'  => $first_name,
                'last_name'   => $last_name,
                'email'       => $email,
                'phone'       => $phone,
                'dob'         => date('Y-m-d', $dob_time),
                'gender'      => $gender,
                'blood_group' => $blood_group,
                'address'     => $address,
                'city'        => $city,
                'state'       => $state,
                'country'     => $country,
                'status'      => 'active',
                'created_at'  => current_time('mysql'),
                'updated_at'  => current_time('mysql'),
            ]);
        }

        // Optional welcome email
        if ($send_email) {
            $subject = 'Welcome to Dior Medical Patient Portal';
            $message = "Hello {$first_name},\n\nYour patient account has been created by your physician.\n\n"
                     . "Patient ID: {$patient_uid}\n"
                     . "Login: {$email}\n"
                     . "Temporary Password: {$password}\n\n"
                     . "Access your dashboard at: " . home_url('/patient-dashboard/') . "\n\n"
                     . "Dior Medical Care Team";
            @wp_mail($email, $subject, $message);
        }

        if (class_exists('Dior_Audit_Service')) {
            Dior_Audit_Service::log('patient_add', 'patient', $patient_uid, $user_id, 'success');
        }

        wp_send_json_success([
            'message' => "Patient {$first_name} {$last_name} (#{$patient_uid}) was created successfully!",
            'patient_id' => $user_id,
            'patient_uid' => $patient_uid,
            'name' => trim($first_name . ' ' . $last_name),
            'email' => $email,
            'password' => $password
        ]);
    }

    public static function ajax_patient_update() {
        self::auth();
        global $wpdb;

        $patient_id = (int)($_POST['patient_id'] ?? 0);
        if (!$patient_id) {
            wp_send_json_error(['message' => 'Please select a valid patient to update.']);
        }

        $user = get_userdata($patient_id);
        if (!$user) {
            wp_send_json_error(['message' => 'Patient user account was not found.']);
        }

        if (!self::patient_belongs_to_doctor($patient_id, self::doctor_id())) {
            wp_send_json_error(['message' => 'Access denied: You do not have permission to edit this patient.']);
        }

        $first_name = sanitize_text_field(trim($_POST['first_name'] ?? ''));
        $last_name  = sanitize_text_field(trim($_POST['last_name'] ?? ''));
        $email      = sanitize_email(trim($_POST['email'] ?? ''));
        $phone      = sanitize_text_field(trim($_POST['phone'] ?? ''));
        $gender     = sanitize_text_field(trim($_POST['gender'] ?? ''));
        $dob        = sanitize_text_field(trim($_POST['dob'] ?? ''));

        if (empty($first_name) || empty($last_name)) {
            wp_send_json_error(['message' => 'First and Last Name are required.']);
        }

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => 'A valid email address is required.']);
        }

        $existing_user_id = email_exists($email);
        if ($existing_user_id && (int)$existing_user_id !== $patient_id) {
            wp_send_json_error(['message' => 'This email address is already assigned to another account.']);
        }

        if (!empty($dob)) {
            $dob_time = strtotime($dob);
            if (!$dob_time) {
                wp_send_json_error(['message' => 'Invalid Date of Birth format.']);
            }
            $age = (int)floor((time() - $dob_time) / (365.25 * 86400));
            if ($age < 18) {
                wp_send_json_error(['message' => 'Patient must be at least 18 years old to hold a portal account.']);
            }
            $dob = date('Y-m-d', $dob_time);
        }

        $blood_group = sanitize_text_field(trim($_POST['blood_group'] ?? ''));
        $height      = sanitize_text_field(trim($_POST['height'] ?? ''));
        $weight      = sanitize_text_field(trim($_POST['weight'] ?? ''));
        $address     = sanitize_text_field(trim($_POST['address'] ?? ''));
        $city        = sanitize_text_field(trim($_POST['city'] ?? ''));
        $state       = strtoupper(sanitize_text_field(trim($_POST['state'] ?? '')));
        $zip         = sanitize_text_field(trim($_POST['zip'] ?? ''));
        $country     = sanitize_text_field(trim($_POST['country'] ?? 'United States of America'));
        $status      = sanitize_text_field(trim($_POST['status'] ?? 'active'));
        if (!in_array($status, ['active', 'inactive', 'blocked'], true)) {
            $status = 'active';
        }

        $emergency_name     = sanitize_text_field(trim($_POST['emergency_name'] ?? ''));
        $emergency_relation = sanitize_text_field(trim($_POST['emergency_relation'] ?? ''));
        $emergency_phone    = sanitize_text_field(trim($_POST['emergency_phone'] ?? ''));

        $pharmacy_name    = sanitize_text_field(trim($_POST['preferred_pharmacy_name'] ?? ''));
        $pharmacy_address = sanitize_text_field(trim($_POST['preferred_pharmacy_address'] ?? ''));
        $pharmacy_phone   = sanitize_text_field(trim($_POST['preferred_pharmacy_phone'] ?? ''));

        $update_data = [
            'ID'           => $patient_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name),
            'user_email'   => $email,
        ];

        $new_password = trim($_POST['password'] ?? '');
        $password_updated = false;
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                wp_send_json_error(['message' => 'New password must be at least 6 characters.']);
            }
            $update_data['user_pass'] = $new_password;
            $password_updated = true;
        }

        $res = wp_update_user($update_data);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }

        update_user_meta($patient_id, 'first_name', $first_name);
        update_user_meta($patient_id, 'last_name', $last_name);
        update_user_meta($patient_id, 'phone', $phone);
        update_user_meta($patient_id, 'billing_phone', $phone);
        if (!empty($dob)) {
            update_user_meta($patient_id, 'dob', $dob);
            update_user_meta($patient_id, 'date_of_birth', $dob);
        }
        update_user_meta($patient_id, 'gender', $gender);
        update_user_meta($patient_id, 'blood_group', $blood_group);
        update_user_meta($patient_id, 'height', $height);
        update_user_meta($patient_id, 'weight', $weight);
        update_user_meta($patient_id, 'address', $address);
        update_user_meta($patient_id, 'billing_address_1', $address);
        update_user_meta($patient_id, 'city', $city);
        update_user_meta($patient_id, 'billing_city', $city);
        update_user_meta($patient_id, 'state', $state);
        update_user_meta($patient_id, 'billing_state', $state);
        update_user_meta($patient_id, 'zip', $zip);
        update_user_meta($patient_id, 'zip_code', $zip);
        update_user_meta($patient_id, 'billing_postcode', $zip);
        update_user_meta($patient_id, 'country', $country);
        update_user_meta($patient_id, 'emergency_name', $emergency_name);
        update_user_meta($patient_id, 'emergency_relation', $emergency_relation);
        update_user_meta($patient_id, 'emergency_phone', $emergency_phone);
        update_user_meta($patient_id, 'preferred_pharmacy_name', $pharmacy_name);
        update_user_meta($patient_id, 'preferred_pharmacy_address', $pharmacy_address);
        update_user_meta($patient_id, 'preferred_pharmacy_phone', $pharmacy_phone);
        update_user_meta($patient_id, 'dior_patient_status', $status);

        if (class_exists('Dior_Patient_Portal_Data')) {
            Dior_Patient_Portal_Data::sync_relational_patient($patient_id);
        }
        $wpdb->update(
            $wpdb->prefix . 'dior_patients',
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['user_id' => $patient_id]
        );

        if ($password_updated && !empty($_POST['send_password_email'])) {
            $site_name = get_bloginfo('name') ?: 'Dior Medical';
            $subject = "Your {$site_name} Portal Password Has Been Updated";
            $message = "Hello {$first_name},\n\nYour patient portal account credentials have been updated by your healthcare provider.\n\nUpdated Password: {$new_password}\nLogin URL: " . home_url('/patient-dashboard/') . "\n\nPlease keep this information secure.\n\nWarm regards,\n{$site_name} Clinical Team";
            @wp_mail($email, $subject, $message);
        }

        if (class_exists('Dior_Audit_Service')) {
            $uid = get_user_meta($patient_id, 'patient_id', true) ?: ('DM-' . (10000 + ($patient_id % 90000)));
            Dior_Audit_Service::log('patient_update', 'patient', $uid, $patient_id, 'success');
        }

        wp_send_json_success([
            'message'    => "Patient profile for {$first_name} {$last_name} was updated successfully!",
            'patient_id' => $patient_id,
            'name'       => trim($first_name . ' ' . $last_name),
            'email'      => $email,
            'status'     => $status
        ]);
    }

    public static function ajax_get_patient_profile(){
        self::auth();
        global $wpdb;
        $p = self::patient(sanitize_text_field($_POST['patient_id'] ?? ''));
        if (!$p || !self::patient_belongs_to_doctor($p['user_id'], self::doctor_id())) {
            wp_send_json_error(['message' => 'Patient not found or access denied.']);
        }
        $pid = (int) $p['user_id'];
        $doctor_context = Dior_Doctor_Dashboard::get_single_doctor_context(get_current_user_id());
        $doctor_ids = array_values(array_unique(array_map('intval', (array) ($doctor_context['ids'] ?? []))));
        $appointment_ids = array_values(array_unique(array_map('intval', (array) ($doctor_context['appointment_ids'] ?? $doctor_ids))));
        if (!$doctor_ids || !$appointment_ids) {
            wp_send_json_error(['message' => 'The single-doctor dashboard is not configured.'], 403);
        }
        $doctor_placeholders = implode(',', array_fill(0, count($doctor_ids), '%d'));
        $appointment_placeholders = implode(',', array_fill(0, count($appointment_ids), '%d'));
        $profile = class_exists('Dior_Patient_Portal_Data') ? Dior_Patient_Portal_Data::get_patient_profile($pid) : [];
        $intake = get_user_meta($pid, 'dior_hipaa_intake', true);
        $intake = is_array($intake) ? $intake : [];
        $questionnaire = get_user_meta($pid, 'dior_medical_questionnaire', true);
        $questionnaire = is_array($questionnaire) ? $questionnaire : [];
        $prescriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT medication, instructions, status, created_at, doctor_id FROM {$wpdb->prefix}dior_prescriptions
             WHERE patient_id = %d AND doctor_id IN ($doctor_placeholders)
             ORDER BY created_at DESC LIMIT 50",
            array_merge([$pid], $doctor_ids)
        ), ARRAY_A) ?: [];
        $legacy_rx = get_user_meta($pid, 'dior_prescriptions', true);
        foreach ((array) $legacy_rx as $rx) {
            if (!is_array($rx)) continue;
            if (!empty($rx['doctor_id']) && !in_array((int) $rx['doctor_id'], $doctor_ids, true)) continue;
            $prescriptions[] = [
                'medication' => $rx['medication'] ?? ($rx['name'] ?? ''),
                'instructions' => $rx['instructions'] ?? ($rx['notes'] ?? ''),
                'status' => $rx['status'] ?? 'Active',
                'created_at' => $rx['date'] ?? ($rx['date_prescribed'] ?? ''),
            ];
        }
        $visits = $wpdb->get_results($wpdb->prepare(
            "SELECT appt_date, doctor_id, condition_name, visit_type, status FROM {$wpdb->prefix}dior_appointments
             WHERE patient_id = %d AND is_deleted = 0 AND doctor_id IN ($appointment_placeholders)
             ORDER BY appt_date DESC LIMIT 50",
            array_merge([$pid], $appointment_ids)
        ), ARRAY_A) ?: [];
        foreach ($visits as &$visit) {
            $doctor = get_userdata((int) $visit['doctor_id']);
            $visit['doctor_name'] = $doctor ? $doctor->display_name : '';
        }
        unset($visit);
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT encounter_uid, subjective, diagnosis, assessment, plan, follow_up, created_at FROM {$wpdb->prefix}dior_encounters
             WHERE patient_id = %d AND is_deleted = 0 AND doctor_id IN ($doctor_placeholders)
             ORDER BY created_at DESC LIMIT 30",
            array_merge([$pid], $doctor_ids)
        ), ARRAY_A) ?: [];
        $documents = class_exists('Dior_Medical_Secure_Files') ? Dior_Medical_Secure_Files::get_patient_documents($pid) : [];
        $documents = array_values(array_filter((array) $documents, static function ($doc) use ($doctor_ids) {
            $author_id = (int) ($doc['author_id'] ?? $doc['doctor_id'] ?? 0);
            return !$author_id || in_array($author_id, $doctor_ids, true);
        }));
        $documents = array_map(static function ($doc) {
            return array_intersect_key($doc, array_flip(['id', 'title', 'category', 'date', 'author', 'file_type', 'size']));
        }, $documents);
        $p = array_merge($p, $profile, [
            'patient_uid' => $p['patient_uid'] ?? ($profile['patient_id'] ?? ''),
            'status' => get_user_meta($pid, 'dior_patient_status', true) ?: ($p['status'] ?? 'active'),
            'marital_status' => get_user_meta($pid, 'marital_status', true),
            'national_id' => get_user_meta($pid, 'national_id', true),
            'allergies' => $intake['drug_allergies_list'] ?? ($intake['allergies'] ?? ''),
            'conditions' => $intake['diagnosed_conditions'] ?? ($intake['other_conditions'] ?? ''),
            'medications' => $intake['medications_list'] ?? ($intake['medications'] ?? ''),
            'medical_history' => $intake['medical_history'] ?? ($intake['other_conditions'] ?? ''),
            'questionnaire' => $questionnaire,
            'prescriptions' => $prescriptions,
            'visits' => $visits,
            'consultations' => $notes,
            'documents' => $documents,
        ]);
        wp_send_json_success(['patient' => $p]);
    }

    public static function ajax_save_consultation(){ self::auth(); $pid=(int)($_POST['patient_id']??0); self::ensure_patient_row($pid); if(!self::patient_belongs_to_doctor($pid,self::doctor_id())) wp_send_json_error(['message'=>'Select a patient assigned to this doctor.']); $res=Dior_Encounter_Service::save_encounter(['patient_id'=>$pid,'encounter_uid'=>sanitize_text_field($_POST['note_id']??''),'encounter_type'=>'Consultation Note','subjective'=>sanitize_textarea_field($_POST['note']??''),'assessment'=>sanitize_textarea_field($_POST['assessment']??''),'plan'=>sanitize_textarea_field($_POST['plan']??''),'diagnosis'=>sanitize_text_field($_POST['diagnosis']??''),'follow_up'=>sanitize_text_field($_POST['follow_up']??'')]); if(is_wp_error($res)) wp_send_json_error(['message'=>$res->get_error_message()]); wp_send_json_success(['message'=>'Consultation note saved.','note'=>$res]); }
    public static function ajax_delete_consultation(){ self::auth(); $pid=(int)($_POST['patient_id']??0); $nid=sanitize_text_field($_POST['note_id']??''); if(!$pid||!$nid) wp_send_json_error(['message'=>'Invalid note.']); $res=Dior_Encounter_Service::delete_encounter($nid,$pid); if(is_wp_error($res)) wp_send_json_error(['message'=>$res->get_error_message()]); wp_send_json_success(['message'=>'Consultation note deleted.']); }

    public static function ajax_document_save()
    {
        self::auth();
        global $wpdb;
        $pid = (int) ($_POST['patient_id'] ?? 0);
        self::ensure_patient_row($pid);
        if (!$pid || !self::patient_belongs_to_doctor($pid, self::doctor_id())) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $id = absint($_POST['id'] ?? 0);
        $doctor_ids = self::doctor_scope_ids();
        $data = [
            'patient_id' => $pid,
            'author_id' => self::doctor_id(),
            'title' => sanitize_text_field($_POST['title'] ?? 'Document'),
            'category' => sanitize_text_field($_POST['category'] ?? 'Clinical Record'),
            'file_name' => sanitize_file_name($_POST['file_name'] ?? 'document.txt'),
            'file_path' => sanitize_text_field($_POST['file_path'] ?? ''),
            'file_type' => sanitize_text_field($_POST['file_type'] ?? 'TXT'),
            'content_html' => wp_kses_post($_POST['content_html'] ?? ''),
            'updated_at' => current_time('mysql'),
        ];

        if ($id) {
            $existing = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . self::table('documents') . ' WHERE id = %d AND is_deleted = 0',
                $id
            ), ARRAY_A);
            if (!$existing || (!empty($existing['author_id']) && !in_array((int) $existing['author_id'], $doctor_ids, true))) {
                wp_send_json_error(['message' => 'Document not found or access denied.'], 403);
            }
            $updated = $wpdb->update(self::table('documents'), $data, ['id' => $id]);
            if ($updated === false) {
                wp_send_json_error(['message' => 'Document could not be saved.'], 500);
            }
        } else {
            $data['doc_uid'] = 'DOC-' . wp_generate_uuid4();
            $data['created_at'] = current_time('mysql');
            $inserted = $wpdb->insert(self::table('documents'), $data);
            if (!$inserted) {
                wp_send_json_error(['message' => 'Document could not be saved.'], 500);
            }
            $id = (int) $wpdb->insert_id;
        }

        wp_send_json_success(['message' => 'Document saved.', 'id' => $id]);
    }

    public static function ajax_document_update_meta(){
        self::auth(); global $wpdb;
        $pid = (int) ($_POST['patient_id'] ?? 0);
        $doc_uid = sanitize_text_field($_POST['doc_uid'] ?? '');
        if (!$pid || !$doc_uid || !self::patient_belongs_to_doctor($pid, self::doctor_id())) wp_send_json_error(['message' => 'Access denied.'], 403);
        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table('documents') . ' WHERE doc_uid = %s AND patient_id = %d AND is_deleted = 0',
            $doc_uid,
            $pid
        ), ARRAY_A);
        if (!$existing || (!empty($existing['author_id']) && !in_array((int) $existing['author_id'], self::doctor_scope_ids(), true))) {
            wp_send_json_error(['message' => 'Document not found or access denied.'], 403);
        }
        $updated = $wpdb->update(self::table('documents'), [
            'title' => sanitize_text_field($_POST['title'] ?? 'Medical Document'),
            'category' => sanitize_text_field($_POST['category'] ?? 'Clinical Record'),
            'updated_at' => current_time('mysql'),
        ], ['doc_uid' => $doc_uid, 'patient_id' => $pid, 'is_deleted' => 0], ['%s', '%s', '%s'], ['%s', '%d', '%d']);
        if ($updated === false) wp_send_json_error(['message' => 'Document could not be updated.']);
        if (!$updated) wp_send_json_error(['message' => 'Document not found or no changes were made.']);
        wp_send_json_success(['message' => 'Document details updated.']);
    }
    public static function ajax_document_delete(){ self::auth(); global $wpdb; $id=(int)($_POST['id']??0); $doc=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('documents').' WHERE id=%d',$id),ARRAY_A); if(!$doc||!self::patient_belongs_to_doctor($doc['patient_id'],self::doctor_id())||(!empty($doc['author_id'])&&!in_array((int)$doc['author_id'],self::doctor_scope_ids(),true))) wp_send_json_error(['message'=>'Access denied.'],403); $wpdb->delete(self::table('documents'),['id'=>$id],['%d']); wp_send_json_success(['message'=>'Document deleted.']); }

    public static function ajax_review_save(){
        self::auth();
        global $wpdb;
        $id = (int)($_POST['id'] ?? 0);
        $pid = (int)($_POST['patient_id'] ?? 0);
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment = sanitize_textarea_field($_POST['comment'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? 'Published');

        if ($id) {
            $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('reviews') . ' WHERE id=%d', $id), ARRAY_A);
            if (!$r) wp_send_json_error(['message' => 'Review not found.']);
            if (!in_array((int)$r['doctor_id'], self::doctor_scope_ids(), true)) {
                wp_send_json_error(['message' => 'Access denied.'], 403);
            }
            $update_data = [
                'rating'     => $rating,
                'comment'    => $comment,
                'status'     => $status,
                'updated_at' => current_time('mysql'),
            ];
            if (!empty($_POST['subject'])) {
                $update_data['subject'] = sanitize_text_field($_POST['subject']);
            }
            $wpdb->update(self::table('reviews'), $update_data, ['id' => $id]);
            wp_send_json_success(['message' => 'Review saved successfully.', 'id' => $id]);
        } else {
            self::ensure_patient_row($pid);
            $data = [
                'review_uid' => 'REV-' . wp_generate_uuid4(),
                'patient_id' => $pid,
                'doctor_id'  => self::doctor_id(),
                'subject'    => sanitize_text_field($_POST['subject'] ?? 'Doctor Review'),
                'rating'     => $rating,
                'comment'    => $comment,
                'status'     => $status,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ];
            $wpdb->insert(self::table('reviews'), $data);
            wp_send_json_success(['message' => 'Review created successfully.', 'id' => $wpdb->insert_id]);
        }
    }
    public static function ajax_review_delete(){
        self::auth();
        global $wpdb;
        $id = (int)($_POST['id'] ?? 0);
        $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('reviews') . ' WHERE id=%d', $id), ARRAY_A);
        if (!$r || !in_array((int)$r['doctor_id'], self::doctor_scope_ids(), true)) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }
        $wpdb->delete(self::table('reviews'), ['id' => $id], ['%d']);
        wp_send_json_success(['message' => 'Review deleted.']);
    }
    public static function ajax_review_bulk_delete(){
        self::auth();
        global $wpdb;
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        if (empty($ids)) {
            wp_send_json_error(['message' => 'No reviews selected.']);
        }
        $deleted = 0;
        foreach ($ids as $id) {
            $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('reviews') . ' WHERE id=%d', $id), ARRAY_A);
            if ($r && in_array((int)$r['doctor_id'], self::doctor_scope_ids(), true)) {
                $wpdb->delete(self::table('reviews'), ['id' => $id], ['%d']);
                $deleted++;
            }
        }
        wp_send_json_success(['message' => "$deleted review(s) deleted."]);
    }
    public static function ajax_review_get(){ self::auth(); global $wpdb; $id=(int)($_POST['id']??0); $r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('reviews').' WHERE id=%d',$id),ARRAY_A); if(!$r || !in_array((int)$r['doctor_id'], self::doctor_scope_ids(), true))wp_send_json_error(['message'=>'Review not found or access denied.'],403); wp_send_json_success(['review'=>$r,'patient'=>self::patient($r['patient_id'])]); }

    public static function ajax_telemed_save(){ self::auth(); global $wpdb; $pid=(int)($_POST['patient_id']??0); $appt=sanitize_text_field($_POST['appointment_uid']??''); if(!$pid||!self::patient_belongs_to_doctor($pid,self::doctor_id()))wp_send_json_error(['message'=>'Access denied.']);$id=(int)($_POST['id']??0);$data=['appointment_uid'=>$appt,'patient_id'=>$pid,'doctor_id'=>self::doctor_id(),'room_url'=>esc_url_raw($_POST['room_url']??''),'status'=>sanitize_text_field($_POST['status']??'Scheduled'),'notes'=>sanitize_textarea_field($_POST['notes']??''),'updated_at'=>current_time('mysql')];if($id)$wpdb->update(self::table('telemedicine'),$data,['id'=>$id]);else{$data['tele_uid']='TEL-'.wp_generate_uuid4();$data['created_at']=current_time('mysql');$wpdb->insert(self::table('telemedicine'),$data);$id=$wpdb->insert_id;}wp_send_json_success(['message'=>'Telemedicine record saved.','id'=>$id]);}
    public static function ajax_telemed_delete(){self::auth();global $wpdb;$id=(int)($_POST['id']??0);$r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('telemedicine').' WHERE id=%d',$id),ARRAY_A);if(!$r||!in_array((int)$r['doctor_id'],self::doctor_scope_ids(),true))wp_send_json_error(['message'=>'Access denied.'],403);$wpdb->delete(self::table('telemedicine'),['id'=>$id],['%d']);wp_send_json_success(['message'=>'Telemedicine record deleted.']);}

    public static function ajax_notification_read(){self::auth();global $wpdb;$id=(int)($_POST['id']??0);$ids=self::doctor_scope_ids();$in=implode(',',array_fill(0,count($ids),'%d'));$args=array_merge([current_time('mysql'),$id],$ids);$wpdb->query($wpdb->prepare('UPDATE '.self::table('notifications')." SET is_read=1,read_at=%s WHERE id=%d AND doctor_id IN ($in)",...$args));wp_send_json_success(['message'=>'Notification marked as read.']);}
    public static function ajax_notification_all_read(){self::auth();global $wpdb;$ids=self::doctor_scope_ids();$in=implode(',',array_fill(0,count($ids),'%d'));$args=array_merge([current_time('mysql')],$ids);$wpdb->query($wpdb->prepare('UPDATE '.self::table('notifications')." SET is_read=1,read_at=%s WHERE doctor_id IN ($in)",...$args));wp_send_json_success(['message'=>'All notifications marked as read.']);}

    public static function ajax_settings_save(){self::auth();global $wpdb;$allowed=['clinic_name','timezone','consultation_duration','booking_buffer','email_new_booking','email_reminders','dashboard_alerts','sms_emergency'];foreach($allowed as $key){$val=isset($_POST[$key])?(is_array($_POST[$key])?wp_json_encode($_POST[$key]):sanitize_text_field($_POST[$key])):'';$wpdb->replace(self::table('doctor_settings'),['doctor_id'=>self::doctor_id(),'setting_key'=>$key,'setting_value'=>$val,'updated_at'=>current_time('mysql')],['%d','%s','%s','%s']);}wp_send_json_success(['message'=>'Settings saved successfully.']);}

    public static function ajax_chat_send(){self::auth();global $wpdb;$uid=sanitize_text_field($_POST['appointment_uid']??'');$a=Dior_Appointment_Service::get_appointment($uid);if(!$a||!self::patient_belongs_to_doctor($a['patient_id'],self::doctor_id())||!in_array((int)$a['doctor_id'],self::doctor_scope_ids(),true))wp_send_json_error(['message'=>'This consultation is not assigned to you.'],403);$msg=sanitize_textarea_field($_POST['message']??'');if(!$msg)wp_send_json_error(['message'=>'Message required.']);$wpdb->insert(self::table('chat_messages'),['appointment_uid'=>$uid,'patient_id'=>$a['patient_id'],'doctor_id'=>self::doctor_id(),'sender_user_id'=>self::doctor_id(),'message'=>$msg,'created_at'=>current_time('mysql')]);wp_send_json_success(['message'=>'Message sent.']);}
    public static function ajax_chat_get(){self::auth();global $wpdb;$uid=sanitize_text_field($_POST['appointment_uid']??'');$a=Dior_Appointment_Service::get_appointment($uid);if(!$a||!self::patient_belongs_to_doctor($a['patient_id'],self::doctor_id())||!in_array((int)$a['doctor_id'],self::doctor_scope_ids(),true))wp_send_json_error(['message'=>'Consultation access denied.'],403);$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('chat_messages').' WHERE appointment_uid=%s ORDER BY id ASC',$uid),ARRAY_A);wp_send_json_success(['messages'=>$rows,'patient'=>self::patient($a['patient_id']),'appointment'=>$a]);}

    public static function get_settings($doctor_id=0){global $wpdb;$doctor_id=$doctor_id?:self::doctor_id();$ids=class_exists('Dior_Doctor_Resolver')?Dior_Doctor_Resolver::get_accessible_doctor_ids($doctor_id):[$doctor_id];$in=implode(',',array_fill(0,count($ids),'%d'));$args=array_merge($ids,[$doctor_id]);$rows=$wpdb->get_results($wpdb->prepare('SELECT setting_key,setting_value FROM '.self::table('doctor_settings')." WHERE doctor_id IN ($in) ORDER BY (doctor_id=%d) ASC",...$args),ARRAY_A);$out=[];foreach($rows as $r)$out[$r['setting_key']]=$r['setting_value'];return $out;}
    public static function render_appointments($doctor_id=0){$doctor_id=$doctor_id?:self::doctor_id();$a=Dior_Appointment_Service::get_doctor_appointments($doctor_id);foreach($a as &$r){$r['patient_name']=self::patient_name($r['patient_id']??0);$p=self::patient($r['patient_id']??0);$r['patient_uid']=$p['patient_uid']??'';}return $a;}
    public static function render_documents(){global $wpdb;$ids=self::doctor_scope_ids();$in=implode(',',array_fill(0,count($ids),'%d'));$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('documents')." WHERE author_id IN ($in) AND is_deleted=0 ORDER BY id DESC",...$ids),ARRAY_A);foreach($rows as &$r)$r['patient_name']=self::patient_name($r['patient_id']);return $rows;}
    public static function render_reviews(){global $wpdb;$ids=self::doctor_scope_ids();$in=implode(',',array_fill(0,count($ids),'%d'));$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('reviews')." WHERE doctor_id IN ($in) ORDER BY id DESC",...$ids),ARRAY_A);foreach($rows as &$r)$r['patient_name']=self::patient_name($r['patient_id']);return $rows;}
    public static function render_telemedicine(){global $wpdb;$ids=self::doctor_scope_ids();$in=implode(',',array_fill(0,count($ids),'%d'));$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('telemedicine')." WHERE doctor_id IN ($in) ORDER BY id DESC",...$ids),ARRAY_A);foreach($rows as &$r)$r['patient_name']=self::patient_name($r['patient_id']);return $rows;}
    public static function render_notifications($type='all'){global $wpdb;$ids=self::doctor_scope_ids();$in=implode(',',array_fill(0,count($ids),'%d'));$where="doctor_id IN ($in)";$args=$ids;if($type==='alerts'){$where.=" AND type='appointment'";}elseif($type==='system'){$where.=" AND type<>'appointment'";}return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('notifications')." WHERE $where ORDER BY id DESC LIMIT 100",...$args),ARRAY_A);}

    public static function ajax_prescription_add() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized: Please log in.'], 401);
        }

        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, self::NONCE) && !wp_verify_nonce($nonce, 'dior_portal_nonce') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page.'], 403);
        }

        global $wpdb;

        $patient_id = (int) ($_POST['patient_id'] ?? 0);
        if (!$patient_id) {
            wp_send_json_error(['message' => 'Please select a valid patient.']);
        }

        $doctor_id = self::doctor_id();
        $doctor_user = get_userdata($doctor_id);
        $doctor_name = $doctor_user ? $doctor_user->display_name : 'Dr. Alexander Reynolds, MD';

        $title = sanitize_text_field($_POST['title'] ?? 'General Medical Prescription');
        $patient_name = sanitize_text_field($_POST['patient_name'] ?? '');
        if (!$patient_name) {
            $pat_user = get_userdata($patient_id);
            $patient_name = $pat_user ? $pat_user->display_name : 'Patient';
        }

        $rx_id = sanitize_text_field($_POST['prescription_id'] ?? '');
        if (!$rx_id) {
            $rx_id = 'RX-' . date('y') . mt_rand(100000, 999999);
        }

        $presc_date = sanitize_text_field($_POST['prescription_date'] ?? current_time('Y-m-d'));
        $medications = sanitize_textarea_field($_POST['medications'] ?? '');
        $dosage = sanitize_text_field($_POST['dosage'] ?? '');
        $frequency = sanitize_text_field($_POST['frequency'] ?? '');
        $duration = sanitize_text_field($_POST['duration'] ?? '');
        $instructions = sanitize_textarea_field($_POST['instructions'] ?? '');
        $quantity = sanitize_text_field($_POST['quantity'] ?? '1 Item(s)');
        $refills = (int) ($_POST['refills'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'Active');

        $table = $wpdb->prefix . 'dior_prescriptions';

        if (class_exists('Dior_DB')) {
            Dior_DB::install_schema();
        }

        $data = [
            'prescription_uid' => uniqid('rx_'),
            'prescription_id' => $rx_id,
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'title' => $title,
            'patient_name' => $patient_name,
            'prescription_date' => $presc_date,
            'medications' => $medications,
            'dosage' => $dosage,
            'frequency' => $frequency,
            'duration' => $duration,
            'doctor_name' => $doctor_name,
            'medication' => $medications,
            'instructions' => $instructions,
            'quantity' => $quantity,
            'refills' => $refills,
            'status' => $status,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $inserted = $wpdb->insert($table, $data);
        if ($inserted === false) {
            wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);
        }

        $user_rx = get_user_meta($patient_id, 'dior_prescriptions', true);
        if (!is_array($user_rx)) {
            $user_rx = [];
        }

        $entry = [
            'id' => $rx_id,
            'order_id' => $rx_id,
            'prescription_uid' => $data['prescription_uid'],
            'title' => $title,
            'medication' => $medications,
            'dosage' => $dosage,
            'frequency' => $frequency,
            'duration' => $duration,
            'quantity' => $quantity,
            'refills' => $refills,
            'date_prescribed' => $presc_date,
            'prescribed_by' => $doctor_name,
            'instructions' => $instructions,
            'status' => $status
        ];

        array_unshift($user_rx, $entry);
        update_user_meta($patient_id, 'dior_prescriptions', $user_rx);

        wp_send_json_success([
            'prescription_id' => $rx_id,
            'title' => $title,
            'patient_name' => $patient_name,
            'date' => date('M d, Y', strtotime($presc_date)),
            'message' => 'Prescription issued successfully'
        ]);
    }

    public static function ajax_prescription_delete() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized'], 401);
        }
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, self::NONCE) && !wp_verify_nonce($nonce, 'dior_portal_nonce') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }
        global $wpdb;
        $rx_id = sanitize_text_field($_POST['prescription_id'] ?? '');
        if (!$rx_id) {
            wp_send_json_error(['message' => 'Missing prescription ID.']);
        }
        $table = $wpdb->prefix . 'dior_prescriptions';
        $doctor_ids = self::doctor_scope_ids();
        if (!$doctor_ids) {
            wp_send_json_error(['message' => 'The single-doctor dashboard is not configured.'], 403);
        }
        $placeholders = implode(',', array_fill(0, count($doctor_ids), '%d'));
        $prescription = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE prescription_id = %s AND doctor_id IN ($placeholders) LIMIT 1",
            array_merge([$rx_id], $doctor_ids)
        ), ARRAY_A);
        if (!$prescription) {
            wp_send_json_error(['message' => 'Prescription not found or access denied.'], 403);
        }
        $wpdb->delete($table, ['id' => (int) $prescription['id']], ['%d']);
        wp_send_json_success(['message' => 'Prescription deleted.']);
    }
}
Dior_Doctor_Dynamic::init();