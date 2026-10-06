<?php
/**
 * Dior Medical - Demo Data Seeder
 * Creates one doctor and multiple patients plus realistic relational demo data.
 */
if (!defined('ABSPATH')) exit;

class Dior_Demo_Data {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_dior_seed_demo_data', [__CLASS__, 'seed']);
        add_action('admin_post_dior_clear_demo_data', [__CLASS__, 'clear']);
    }

    public static function admin_menu() {
        add_management_page(
            'Dior Medical Demo Data',
            'Dior Demo Data',
            'manage_options',
            'dior-demo-data',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;
        $doctor = self::get_demo_doctor();
        $patients = self::get_demo_patients();
        ?>
        <div class="wrap">
            <h1>Dior Medical — Demo Data</h1>
            <p>Create a safe development dataset with <strong>1 doctor + multiple patients</strong>. Existing non-demo data is not deleted.</p>
            <p><strong>Demo doctor:</strong> <?php echo esc_html($doctor ? $doctor->user_email : 'Not created'); ?></p>
            <p><strong>Demo patients:</strong> <?php echo esc_html(count($patients)); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dior_seed_demo_data'), 'dior_seed_demo_data')); ?>">Create / Refresh Demo Data</a>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dior_clear_demo_data'), 'dior_clear_demo_data')); ?>" style="margin-left:8px">Delete Demo Data</a>
            </p>
            <p><em>Demo records are marked internally with <code>dior_demo_record = 1</code>, so cleanup only targets seeded records.</em></p>
        </div>
        <?php
    }

    private static function user($login, $email, $role, $first, $last, $phone, $dob, $gender, $blood, $city, $address, $is_demo = true) {
        $user = get_user_by('login', $login);
        if (!$user) $user = get_user_by('email', $email);
        if (!$user) {
            $id = wp_insert_user([
                'user_login' => $login,
                'user_email' => $email,
                'user_pass' => 'DiorDemo!2026',
                'first_name' => $first,
                'last_name' => $last,
                'display_name' => $first . ' ' . $last,
                'role' => $role,
            ]);
            if (is_wp_error($id)) return null;
            $user = get_userdata($id);
        } else {
            $user->set_role($role);
            wp_update_user(['ID' => $user->ID, 'first_name' => $first, 'last_name' => $last, 'display_name' => $first . ' ' . $last]);
        }
        $meta = [
            'phone' => $phone, 'billing_phone' => $phone, 'dob' => $dob, 'date_of_birth' => $dob,
            'gender' => $gender, 'blood_group' => $blood, 'address' => $address,
            'city' => $city, 'country' => 'United States', 'patient_id' => 'DM-' . (10000 + ($user->ID % 90000)),
            'dior_demo_record' => $is_demo ? 1 : 0,
        ];
        foreach ($meta as $k => $v) update_user_meta($user->ID, $k, $v);
        return get_userdata($user->ID);
    }

    public static function seed() {
        if (!current_user_can('manage_options') || !check_admin_referer('dior_seed_demo_data')) wp_die('Unauthorized request.');
        if (class_exists('Dior_DB')) Dior_DB::install_schema();
        if (!get_role('doctor')) add_role('doctor', 'Doctor / Provider', ['read' => true]);
        global $wpdb;
        $doctor = self::user('dior_demo_doctor', 'doctor.demo@dior-medical.test', 'doctor', 'James', 'Chen', '+1 555 100 1001', '1982-04-12', 'Male', 'O+', 'Los Angeles', '100 Medical Plaza');
        if (!$doctor) wp_die('Unable to create demo doctor.');

        $patients = [
            ['dior_demo_patient_01','patient01@dior-medical.test','Olivia','Martin','+1 555 200 1001','1990-02-14','Female','A+','Los Angeles','201 Sunset Blvd'],
            ['dior_demo_patient_02','patient02@dior-medical.test','Noah','Williams','+1 555 200 1002','1988-07-23','Male','O+','San Diego','22 Harbor Way'],
            ['dior_demo_patient_03','patient03@dior-medical.test','Emma','Davis','+1 555 200 1003','1994-11-03','Female','B+','Irvine','84 Orchard Ave'],
            ['dior_demo_patient_04','patient04@dior-medical.test','Liam','Wilson','+1 555 200 1004','1985-05-19','Male','AB+','Pasadena','19 Oak Street'],
            ['dior_demo_patient_05','patient05@dior-medical.test','Sophia','Taylor','+1 555 200 1005','1992-09-28','Female','O-','Long Beach','55 Ocean Drive'],
            ['dior_demo_patient_06','patient06@dior-medical.test','Ethan','Brown','+1 555 200 1006','1991-01-10','Male','A-','Glendale','71 Central Ave'],
            ['dior_demo_patient_07','patient07@dior-medical.test','Ava','Anderson','+1 555 200 1007','1987-12-07','Female','B-','Santa Monica','12 Pacific View'],
            ['dior_demo_patient_08','patient08@dior-medical.test','Lucas','Thomas','+1 555 200 1008','1995-06-21','Male','O+','Burbank','33 Magnolia Road'],
        ];
        $patient_users = [];
        foreach ($patients as $p) {
            $u = self::user($p[0],$p[1],'subscriber',$p[2],$p[3],$p[4],$p[5],$p[6],$p[7],$p[8],$p[9]);
            if ($u) {
                $patient_users[] = $u;
                if (class_exists('Dior_Patient_Portal_Data')) Dior_Patient_Portal_Data::sync_relational_patient($u->ID);
            }
        }

        $now = current_time('mysql');
        $appointments = $wpdb->prefix . 'dior_appointments';
        $encounters = $wpdb->prefix . 'dior_encounters';
        $docs = $wpdb->prefix . 'dior_documents';
        $audit = $wpdb->prefix . 'dior_audit_logs';
        $history = $wpdb->prefix . 'dior_appointment_history';
        $notifs = $wpdb->prefix . 'dior_notifications';
        $prescriptions = $wpdb->prefix . 'dior_prescriptions';
        $payments = $wpdb->prefix . 'dior_payments';
        $invoices = $wpdb->prefix . 'dior_invoices';

        foreach ($patient_users as $i => $patient) {
            $appt_uid = 'DEMO-APT-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
            $date = date('Y-m-d', strtotime('+' . ($i % 6 + 1) . ' days'));
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $appointments WHERE appt_uid=%s", $appt_uid));
            if (!$exists) {
                $wpdb->insert($appointments, [
                    'appt_uid'=>$appt_uid,'patient_id'=>$patient->ID,'doctor_id'=>$doctor->ID,'condition_name'=>['General Consultation','Follow-up','Urgent Care','Medication Review'][$i%4],
                    'visit_type'=>'Video Visit (HD)','status'=>($i < 5 ? 'Confirmed' : 'Completed'),'appt_date'=>$date,'appt_time'=>['09:00 AM','10:30 AM','11:30 AM','01:00 PM','02:30 PM','04:00 PM'][$i%6],
                    'join_url'=>'https://zoom.us/j/000000000','payment_status'=>'Paid','notes'=>'Demo appointment for dynamic dashboard testing.','created_at'=>$now,'updated_at'=>$now,'is_deleted'=>0
                ]);
                $history_table = $history;
                if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $history_table)) === $history_table) {
                    $wpdb->insert($history_table,['appointment_id'=>$wpdb->insert_id,'appt_uid'=>$appt_uid,'patient_id'=>$patient->ID,'doctor_id'=>$doctor->ID,'action'=>'created','old_status'=>'','new_status'=>($i < 5 ? 'Confirmed' : 'Completed'),'changed_by'=>$doctor->ID,'notes'=>'Demo seed','created_at'=>$now]);
                }
            }

            $notif_id = 'DEMO-NOTIF-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $notifs WHERE notification_uid=%s", $notif_id)) === null) {
                $wpdb->insert($notifs,['notification_uid'=>$notif_id,'user_id'=>$patient->ID,'patient_id'=>$patient->ID,'doctor_id'=>$doctor->ID,'type'=>'appointment','title'=>'Appointment Confirmed','message'=>'Your appointment with Dr. James Chen is confirmed for '.$date.'.','action_url'=>'#tab=appointments','icon'=>'fa-calendar-check','is_read'=>($i%3===0?1:0),'created_at'=>$now]);
            }

            $rx_uid = 'DEMO-RX-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $prescriptions WHERE prescription_uid=%s", $rx_uid)) === null) {
                $wpdb->insert($prescriptions,['prescription_uid'=>$rx_uid,'patient_id'=>$patient->ID,'doctor_id'=>$doctor->ID,'appointment_uid'=>$appt_uid,'medication'=>'Amoxicillin 500 mg','instructions'=>'Take one capsule three times daily after meals.','quantity'=>'21','refills'=>0,'status'=>'Active','created_at'=>$now,'updated_at'=>$now]);
            }

            $inv_uid = 'DEMO-INV-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $invoices WHERE invoice_uid=%s", $inv_uid)) === null) {
                $wpdb->insert($invoices,['invoice_uid'=>$inv_uid,'patient_id'=>$patient->ID,'doctor_id'=>$doctor->ID,'appointment_uid'=>$appt_uid,'amount'=>'99.00','currency'=>'USD','status'=>'Paid','description'=>'Telehealth consultation','issued_at'=>$now,'due_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
                $wpdb->insert($payments,['payment_uid'=>'DEMO-PAY-' . str_pad((string)($i+1),4,'0',STR_PAD_LEFT),'patient_id'=>$patient->ID,'doctor_id'=>$doctor->ID,'appointment_uid'=>$appt_uid,'invoice_uid'=>$inv_uid,'amount'=>'99.00','currency'=>'USD','payment_method'=>'Card','transaction_id'=>'demo_tx_'.$patient->ID,'status'=>'Paid','paid_at'=>$now,'created_at'=>$now]);
            }

            $enc_uid = 'DEMO-ENC-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $encounters WHERE encounter_uid=%s", $enc_uid)) === null) {
                $wpdb->insert($encounters,['encounter_uid'=>$enc_uid,'patient_id'=>$patient->ID,'doctor_id'=>$doctor->ID,'appointment_id'=>$appt_uid,'encounter_type'=>'Telehealth Consultation','status'=>'finalized','subjective'=>'Demo patient reports mild symptoms.','objective'=>'Vitals stable.','assessment'=>'Demo clinical assessment.','plan'=>'Continue monitoring and follow up as needed.','diagnosis'=>'Routine consultation','follow_up'=>'7 days','vitals_bp'=>'120/80','vitals_hr'=>'72','vitals_temp'=>'98.6 F','vitals_weight'=>'70 kg','attestation_hash'=>hash('sha256',$enc_uid),'attestation_author'=>'Dr. James Chen','attestation_timestamp'=>$now,'created_at'=>$now,'updated_at'=>$now,'is_deleted'=>0]);
            }
        }

        // Seed one doctor-facing notification and audit event.
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $notifs WHERE notification_uid=%s", 'DEMO-DOC-NOTIF-01')) === null) {
            $wpdb->insert($notifs,['notification_uid'=>'DEMO-DOC-NOTIF-01','user_id'=>$doctor->ID,'patient_id'=>$patient_users[0]->ID ?? 0,'doctor_id'=>$doctor->ID,'type'=>'appointment','title'=>'New Appointment','message'=>'Olivia Martin has a new scheduled consultation.','action_url'=>'#doc-appointments','icon'=>'fa-calendar-plus','is_read'=>0,'created_at'=>$now]);
        }

        wp_safe_redirect(add_query_arg(['page'=>'dior-demo-data','seeded'=>'1'], admin_url('tools.php')));
        exit;
    }

    public static function clear() {
        if (!current_user_can('manage_options') || !check_admin_referer('dior_clear_demo_data')) wp_die('Unauthorized request.');
        global $wpdb;
        $users = get_users(['meta_key'=>'dior_demo_record','meta_value'=>1,'fields'=>'ID']);
        $tables = ['dior_notifications','dior_prescriptions','dior_payments','dior_invoices','dior_appointment_history','dior_encounters','dior_documents','dior_appointments','dior_audit_logs'];
        foreach ($users as $uid) {
            foreach ($tables as $suffix) {
                $table = $wpdb->prefix.$suffix;
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table)) === $table) {
                    if ($suffix === 'dior_notifications') $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE user_id=%d",$uid));
                    elseif (in_array($suffix,['dior_appointments','dior_encounters','dior_documents','dior_prescriptions','dior_payments','dior_invoices','dior_appointment_history'])) $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE patient_id=%d OR doctor_id=%d",$uid,$uid));
                    elseif ($suffix === 'dior_audit_logs') $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE user_id=%d OR patient_id=%d",$uid,$uid));
                }
            }
            require_once ABSPATH.'wp-admin/includes/user.php';
            wp_delete_user($uid);
        }
        wp_safe_redirect(add_query_arg(['page'=>'dior-demo-data','cleared'=>'1'], admin_url('tools.php')));
        exit;
    }

    private static function get_demo_doctor() {
        return get_user_by('email','doctor.demo@dior-medical.test');
    }
    private static function get_demo_patients() {
        return get_users(['meta_key'=>'dior_demo_record','meta_value'=>1,'role__not_in'=>['doctor','administrator']]);
    }
}
