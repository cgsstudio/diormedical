<?php
/**
 * Dior Medical — Edit Appointment
 * Tab: Edit Appointment (#tab-doc-appointments-edit)
 * Strictly 100% Bootstrap 5 Utilities Only (No Custom CSS)
 */
global $wpdb;
$doctor_id = get_current_user_id();
$doctor_nonce = wp_create_nonce('dior_doctor_nonce');
$admin_nonce  = wp_create_nonce('dior_admin_nonce');
$table_appts = $wpdb->prefix . 'dior_appointments';
$table_patients = $wpdb->prefix . 'dior_patients';
$users_table = $wpdb->users;

// 1. Fetch all appointments with patient details from DB
$db_appointments = $wpdb->get_results("
    SELECT a.*,
           COALESCE(p.first_name, u.display_name, 'Patient') as patient_first_name,
           COALESCE(p.last_name, '') as patient_last_name,
           p.phone as patient_phone,
           p.gender as patient_gender,
           p.dob as patient_dob,
           p.blood_group as patient_blood_group,
           p.address as patient_address,
           p.city as patient_city,
           p.state as patient_state,
           p.country as patient_country,
           p.avatar_url as patient_avatar,
           COALESCE(p.patient_uid, CONCAT('DM-', (10000 + a.patient_id))) as patient_code,
           u.user_email as patient_email
    FROM $table_appts a
    LEFT JOIN $table_patients p ON p.user_id = a.patient_id
    LEFT JOIN $users_table u ON u.ID = a.patient_id
    WHERE a.is_deleted = 0
    ORDER BY a.appt_date DESC, a.created_at DESC
    LIMIT 300
", ARRAY_A);

// 2. Fetch doctors list for consulting doctor dropdown
$doctor_users = get_users([
    'role__in' => ['doctor', 'dior_doctor', 'administrator'],
    'orderby'  => 'display_name',
    'number'   => 50
]);

// 3. Fetch comprehensive patient list from both dior_patients and wp_users
$db_patients = $wpdb->get_results("
    SELECT user_id, first_name, last_name, patient_uid, phone, email, gender, dob, blood_group, address
    FROM {$wpdb->prefix}dior_patients
    WHERE status <> 'deleted'
", ARRAY_A);

$known_patient_uids = [];
$all_clinic_patients = [];
if (!empty($db_patients)) {
    foreach ($db_patients as $p) {
        $uid_key = (int)$p['user_id'];
        if ($uid_key > 0) {
            $known_patient_uids[$uid_key] = true;
        }
        $all_clinic_patients[] = $p;
    }
}

// Ensure every patient who has an existing appointment is also in the patient list
$appt_patient_ids = $wpdb->get_col("SELECT DISTINCT patient_id FROM {$wpdb->prefix}dior_appointments WHERE is_deleted = 0 AND patient_id > 0");
if (!empty($appt_patient_ids)) {
    foreach ($appt_patient_ids as $pid) {
        $pid = (int)$pid;
        if (!isset($known_patient_uids[$pid])) {
            $u = get_userdata($pid);
            if ($u) {
                $fname = get_user_meta($pid, 'first_name', true) ?: $u->first_name ?: $u->display_name;
                $lname = get_user_meta($pid, 'last_name', true) ?: $u->last_name;
                $p_code = get_user_meta($pid, 'patient_id', true) ?: ('DM-' . (10000 + $pid));
                $all_clinic_patients[] = [
                    'user_id'     => $pid,
                    'first_name'  => $fname,
                    'last_name'   => $lname,
                    'patient_uid' => $p_code,
                    'phone'       => get_user_meta($pid, 'phone', true) ?: '',
                    'email'       => $u->user_email,
                    'gender'      => get_user_meta($pid, 'gender', true) ?: 'male',
                    'dob'         => get_user_meta($pid, 'dob', true) ?: '',
                    'blood_group' => get_user_meta($pid, 'blood_group', true) ?: 'A+',
                    'address'     => get_user_meta($pid, 'address', true) ?: '',
                ];
                $known_patient_uids[$pid] = true;
            }
        }
    }
}

// Fallback to general subscribers if still empty
if (empty($all_clinic_patients)) {
    $raw_pat_users = get_users(['role__in' => ['patient', 'subscriber', 'customer'], 'number' => 100]);
    foreach ($raw_pat_users as $pu) {
        $all_clinic_patients[] = [
            'user_id'     => $pu->ID,
            'first_name'  => $pu->first_name ?: $pu->display_name,
            'last_name'   => $pu->last_name,
            'patient_uid' => get_user_meta($pu->ID, 'patient_id', true) ?: ('DM-' . (10000 + $pu->ID)),
            'phone'       => get_user_meta($pu->ID, 'phone', true) ?: '',
            'email'       => $pu->user_email,
            'gender'      => get_user_meta($pu->ID, 'gender', true) ?: 'male',
            'dob'         => get_user_meta($pu->ID, 'dob', true) ?: '',
            'blood_group' => get_user_meta($pu->ID, 'blood_group', true) ?: 'A+',
            'address'     => get_user_meta($pu->ID, 'address', true) ?: '',
        ];
    }
}

// Sort all patients alphabetically
usort($all_clinic_patients, function($a, $b) {
    return strcasecmp(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''), ($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
});

// 4. Construct appointment data map and lookup tables
$appts_data_map = [];
$patients_appts_lookup = [];
if (!empty($db_appointments)) {
    foreach ($db_appointments as $item) {
        $uid = $item['appt_uid'];
        $p_id = (int)$item['patient_id'];
        
        $emergency_name  = get_user_meta($p_id, 'emergency_contact_name', true) ?: '';
        $emergency_phone = get_user_meta($p_id, 'emergency_contact_phone', true) ?: '';
        $emergency_rel   = get_user_meta($p_id, 'emergency_contact_relation', true) ?: 'parent';
        
        $ins_provider = get_user_meta($p_id, 'dior_insurance_provider', true) ?: '';
        $ins_policy   = get_user_meta($p_id, 'dior_insurance_policy', true) ?: '';
        $ins_group    = get_user_meta($p_id, 'dior_insurance_group', true) ?: '';
        $ins_holder   = get_user_meta($p_id, 'dior_insurance_holder', true) ?: '';
        $ins_relation = get_user_meta($p_id, 'dior_insurance_relation', true) ?: 'self';
        
        $med_conds   = get_user_meta($p_id, 'dior_medical_conditions', true) ?: '';
        $medications = get_user_meta($p_id, 'dior_current_medications', true) ?: '';
        $allergies   = get_user_meta($p_id, 'dior_allergies', true) ?: '';
        $surgeries   = get_user_meta($p_id, 'dior_past_surgeries', true) ?: '';

        $appts_data_map[$uid] = [
            'id'                     => (int)$item['id'],
            'appt_uid'               => $uid,
            'patient_id'             => $p_id,
            'doctor_id'              => (int)($item['doctor_id'] ?: $doctor_id),
            'condition_name'         => $item['condition_name'] ?: 'General Consultation',
            'visit_type'             => $item['visit_type'] ?: 'Video Visit (HD)',
            'status'                 => $item['status'] ?: 'Success',
            'appt_date'              => $item['appt_date'] ?: current_time('Y-m-d'),
            'appt_time'              => $item['appt_time'] ?: '10:00 AM',
            'join_url'               => $item['join_url'] ?: '',
            'payment_status'         => $item['payment_status'] ?: 'Paid',
            'notes'                  => $item['notes'] ?: '',
            'first_name'             => $item['patient_first_name'],
            'last_name'              => $item['patient_last_name'],
            'full_name'              => trim($item['patient_first_name'] . ' ' . $item['patient_last_name']),
            'patient_uid'            => $item['patient_code'],
            'phone'                  => $item['patient_phone'] ?: '',
            'email'                  => $item['patient_email'] ?: '',
            'gender'                 => $item['patient_gender'] ?: 'male',
            'dob'                    => $item['patient_dob'] ?: '',
            'blood_group'            => $item['patient_blood_group'] ?: 'A+',
            'address'                => $item['patient_address'] ?: '',
            'emergency_contact_name' => $emergency_name,
            'emergency_contact_phone'=> $emergency_phone,
            'emergency_contact_relation' => $emergency_rel,
            'insurance_provider'     => $ins_provider,
            'insurance_policy_number'=> $ins_policy,
            'insurance_group_number' => $ins_group,
            'insurance_holder_name'  => $ins_holder,
            'insurance_relation'     => $ins_relation,
            'medical_conditions'     => $med_conds,
            'medications'            => $medications,
            'allergies'              => $allergies,
            'surgeries'              => $surgeries,
        ];

        if (!isset($patients_appts_lookup[$p_id])) {
            $patients_appts_lookup[$p_id] = [];
        }
        $patients_appts_lookup[$p_id][] = [
            'uid'            => $uid,
            'patient_name'   => trim($item['patient_first_name'] . ' ' . $item['patient_last_name']),
            'appt_date'      => $item['appt_date'],
            'appt_time'      => $item['appt_time'],
            'status'         => $item['status'] ?: 'Success',
            'visit_type'     => $item['visit_type'] ?: 'Video Visit (HD)',
            'condition_name' => $item['condition_name'] ?: 'General Consultation'
        ];
    }
}

// 5. Determine initially active appointment from URL parameter or fallback to first
$url_appt_param = sanitize_text_field($_GET['appt_uid'] ?? ($_GET['appt'] ?? ($_GET['uid'] ?? '')));
if (!empty($url_appt_param) && isset($appts_data_map[$url_appt_param])) {
    $first_appt_uid = $url_appt_param;
} elseif (!empty($db_appointments)) {
    $first_appt_uid = $db_appointments[0]['appt_uid'];
} else {
    $first_appt_uid = '';
}

$initial_patient_id = '';
if (!empty($first_appt_uid) && isset($appts_data_map[$first_appt_uid])) {
    $initial_patient_id = (int)$appts_data_map[$first_appt_uid]['patient_id'];
}
?>
<section class="dior-tab-panel appointment-form-wrapper" id="tab-doc-appointments-edit" style="display: none;">
    <!-- Breadcrumb & Header (100% Native Bootstrap 5) -->
    <div class="row g-3 align-items-center justify-content-between mb-4">
        <div class="col-12 col-lg-auto">
            <div class="h6 mb-1 fw-bold text-dark">
                <i class="fas fa-pen-to-square text-primary me-2"></i>Edit Appointment
            </div>
            <div class="text-secondary small">Modify appointment schedule, clinical details, patient demographics, and consultation notes.</div>
        </div>
        <div class="col-12 col-lg-auto">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small text-muted">
                    <li class="breadcrumb-item"><i class="fas fa-house me-1"></i> Dashboard</li>
                    <li class="breadcrumb-item">Appointments</li>
                    <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Edit Appointment</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-appointments" onclick="if(typeof diorAdminSwitchTab==='function') diorAdminSwitchTab('doc-appointments'); else if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments');">
            <i class="fas fa-calendar-days"></i> Appointment Calendar
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-appointments-view" onclick="if(typeof diorAdminSwitchTab==='function') diorAdminSwitchTab('doc-appointments-view'); else if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments-view');">
            <i class="fas fa-calendar-check"></i> View Appointment
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-appointments-book" onclick="if(typeof diorAdminSwitchTab==='function') diorAdminSwitchTab('doc-appointments-book'); else if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments-book');">
            <i class="fas fa-calendar-plus"></i> Book Appointment
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-appointments-edit" onclick="if(typeof diorAdminSwitchTab==='function') diorAdminSwitchTab('doc-appointments-edit'); else if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments-edit');">
            <i class="fas fa-pen-to-square"></i> Edit Appointment
        </button>
    </div>

    <!-- 1. Patient & Appointment Selector Header Card -->
    <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-light">
        <div class="row g-3 align-items-center">
            <div class="col-lg-5 col-md-6 col-12">
                <label class="form-label small text-uppercase text-secondary fw-bold mb-1" for="dior-edit-patient-selector">
                    <i class="fas fa-user-injured text-primary me-1"></i> Select Patient <span class="badge bg-primary-subtle text-primary ms-1 small fw-bold">Step 1</span>
                </label>
                <select class="form-select rounded-3 py-2 fw-semibold" id="dior-edit-patient-selector" onchange="diorOnHeaderPatientSelect(this.value)">
                    <option value="">-- All Patients (View All Appointments) --</option>
                    <?php foreach ($all_clinic_patients as $cp): 
                        $p_id = (int)$cp['user_id'];
                        $p_cnt = isset($patients_appts_lookup[$p_id]) ? count($patients_appts_lookup[$p_id]) : 0;
                    ?>
                        <option value="<?php echo $p_id; ?>" <?php selected($initial_patient_id, $p_id); ?>>
                            <?php echo esc_html(trim($cp['first_name'] . ' ' . $cp['last_name'])) . ' (' . esc_html($cp['patient_uid']) . ')' . ($p_cnt > 0 ? " — {$p_cnt} Appt" . ($p_cnt > 1 ? 's' : '') : ' — No Appts'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-5 col-md-6 col-12">
                <label class="form-label small text-uppercase text-secondary fw-bold mb-1" for="dior-edit-appt-selector">
                    <i class="fas fa-calendar-check text-primary me-1"></i> Select Appointment to Edit <span class="badge bg-info-subtle text-info ms-1 small fw-bold">Step 2</span>
                </label>
                <select class="form-select rounded-3 py-2 fw-semibold" id="dior-edit-appt-selector" onchange="diorOnHeaderApptSelect(this.value)">
                    <option value="">-- Choose an appointment from records --</option>
                    <?php if (!empty($db_appointments)): ?>
                        <?php foreach ($db_appointments as $a_opt): ?>
                            <option value="<?php echo esc_attr($a_opt['appt_uid']); ?>" data-patient-id="<?php echo esc_attr($a_opt['patient_id']); ?>" <?php selected($first_appt_uid, $a_opt['appt_uid']); ?>>
                                #<?php echo esc_html($a_opt['appt_uid']); ?> — <?php echo esc_html(trim($a_opt['patient_first_name'] . ' ' . $a_opt['patient_last_name'])); ?> | <?php echo esc_html(date('M d, Y', strtotime($a_opt['appt_date']))); ?> (<?php echo esc_html($a_opt['appt_time']); ?>) [<?php echo esc_html($a_opt['status']); ?>]
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>No appointments found in database</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-12 d-flex flex-wrap align-items-center justify-content-lg-end gap-2 pt-lg-3">
                <span id="dior-selected-appt-badge" class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 small fw-semibold">
                    <span id="dior-badge-appt-id"><?php echo !empty($first_appt_uid) ? '#' . esc_html($first_appt_uid) : 'No Appt Selected'; ?></span>
                </span>
                <span id="dior-selected-status-badge" class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2 small fw-semibold">
                    <span id="dior-badge-status-text">Ready</span>
                </span>
                <button type="button" class="btn btn-outline-primary btn-sm rounded-3 fw-semibold px-3 py-2" onclick="if(typeof diorAdminSwitchTab==='function') diorAdminSwitchTab('doc-appointments-view'); else if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments-view');">
                    <i class="fas fa-table-list me-1"></i> View All
                </button>
            </div>
        </div>
        <div id="dior-edit-patient-alert" class="alert alert-info border-info-subtle py-2 px-3 mt-3 mb-0 rounded-3 small d-none align-items-center justify-content-between">
            <div>
                <i class="fas fa-info-circle me-1 text-primary"></i> <span id="dior-edit-patient-alert-msg"></span>
            </div>
            <span class="badge bg-white text-primary border border-primary-subtle rounded-pill px-2.5 py-1">Auto-loaded</span>
        </div>
    </div>

    <!-- 2. Main Edit Form -->
    <form id="dior-appointment-edit-form" novalidate>
        <input type="hidden" id="edit-appt-uid" name="appt_uid" value="<?php echo esc_attr($first_appt_uid); ?>">
        <input type="hidden" id="edit-patient-id" name="patient_id" value="<?php echo esc_attr($initial_patient_id); ?>">
        <input type="hidden" name="nonce" value="<?php echo esc_attr($doctor_nonce); ?>">

        <!-- Section A: Appointment Scheduling & Logistics -->
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                <h5 class="fw-bold text-dark fs-5 mb-0">
                    <i class="fas fa-calendar-check text-primary me-2"></i> Appointment Details &amp; Scheduling
                </h5>
                <span class="badge bg-light text-secondary border rounded-pill px-3 py-1 small">
                    UID: <strong id="dior-display-appt-uid"><?php echo esc_html($first_appt_uid ?: '—'); ?></strong>
                </span>
            </div>

            <div class="row g-3">
                <div class="col-xl-3 col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-appt-date">
                        Appointment Date <span class="text-danger">*</span>
                    </label>
                    <input type="date" class="form-control rounded-3 py-2" id="edit-appt-date" name="appt_date" required>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-appt-status">
                        Appointment Status <span class="text-danger">*</span>
                    </label>
                    <select class="form-select rounded-3 py-2" id="edit-appt-status" name="status" required>
                        <option value="Success">Success</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="No Show">No Show</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-visit-type">
                        Visit Modality <span class="text-danger">*</span>
                    </label>
                    <select class="form-select rounded-3 py-2" id="edit-visit-type" name="visit_type" required>
                        <option value="Video Visit (HD)">Video Visit (HD)</option>
                        <option value="Phone Consultation">Phone Consultation</option>
                        <option value="In-Person Clinic Visit">In-Person Clinic Visit</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-payment-status">
                        Payment Status <span class="text-danger">*</span>
                    </label>
                    <select class="form-select rounded-3 py-2" id="edit-payment-status" name="payment_status" required>
                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>
                        <option value="Refunded">Refunded</option>
                        <option value="Failed">Failed</option>
                    </select>
                </div>

                <div class="col-xl-4 col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-consulting-doctor">
                        Consulting Doctor <span class="text-danger">*</span>
                    </label>
                    <select class="form-select rounded-3 py-2" id="edit-consulting-doctor" name="doctor_id">
                        <option value="<?php echo esc_attr($doctor_id); ?>">Dr. Marcus Sterling, DO (Current Provider)</option>
                        <?php if (!empty($doctor_users)): ?>
                            <?php foreach ($doctor_users as $doc_u): ?>
                                <?php if ($doc_u->ID != $doctor_id): ?>
                                    <option value="<?php echo esc_attr($doc_u->ID); ?>">
                                        <?php echo esc_html($doc_u->display_name); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-xl-4 col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-department">
                        Department / Specialty
                    </label>
                    <select class="form-select rounded-3 py-2" id="edit-department" name="department">
                        <option value="General Telehealth">General Telehealth</option>
                        <option value="Cardiology">Cardiology</option>
                        <option value="Dermatology">Dermatology</option>
                        <option value="Orthopedics">Orthopedics</option>
                        <option value="Gynecology">Gynecology</option>
                        <option value="Neurology">Neurology</option>
                        <option value="Pediatrics">Pediatrics</option>
                        <option value="Psychiatry">Psychiatry</option>
                        <option value="Oncology">Oncology</option>
                        <option value="Ophthalmology">Ophthalmology</option>
                        <option value="General Surgery">General Surgery</option>
                    </select>
                </div>

                <div class="col-xl-4 col-md-12">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-join-url">
                        Telehealth / Video Meeting URL
                    </label>
                    <div class="input-group">
                        <input type="url" class="form-control rounded-start-3 py-2" id="edit-join-url" name="join_url" placeholder="https://zoom.us/j/...">
                        <button type="button" class="btn btn-outline-secondary px-3" onclick="diorTestMeetingLink()" title="Open Link">
                            <i class="fas fa-arrow-up-right-from-square"></i>
                        </button>
                    </div>
                </div>

                <!-- Time Slot Section -->
                <div class="col-12 mt-3">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-2 d-block">
                        Time Of Appointment <span class="text-danger">*</span>
                    </label>
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="far fa-clock text-primary"></i></span>
                                <input type="text" class="form-control rounded-end-3 py-2" id="edit-appt-time-input" name="appt_time" placeholder="e.g. 10:00 AM - 11:00 AM" required>
                            </div>
                        </div>
                        <div class="col-md-8 text-secondary small">
                            <i class="fas fa-circle-info text-primary me-1"></i> Type a custom time slot above or select a preset slot below:
                        </div>
                    </div>

                    <!-- Preset Time Buttons -->
                    <div class="d-flex flex-wrap gap-2 pt-1" id="dior-time-preset-group">
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 dior-time-preset" onclick="diorSetTimePreset('09:00 AM - 10:00 AM', this)">09:00 AM</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 dior-time-preset" onclick="diorSetTimePreset('10:00 AM - 11:00 AM', this)">10:00 AM</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 dior-time-preset" onclick="diorSetTimePreset('11:00 AM - 12:00 PM', this)">11:00 AM</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 dior-time-preset" onclick="diorSetTimePreset('01:00 PM - 02:00 PM', this)">01:00 PM</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 dior-time-preset" onclick="diorSetTimePreset('02:00 PM - 03:00 PM', this)">02:00 PM</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 dior-time-preset" onclick="diorSetTimePreset('03:00 PM - 04:00 PM', this)">03:00 PM</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 dior-time-preset" onclick="diorSetTimePreset('04:00 PM - 05:00 PM', this)">04:00 PM</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 dior-time-preset" onclick="diorSetTimePreset('05:00 PM - 06:00 PM', this)">05:00 PM</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 dior-time-preset" onclick="diorSetTimePreset('06:00 PM - 07:00 PM', this)">06:00 PM</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section B: Clinical Details & Notes -->
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                <h5 class="fw-bold text-dark fs-5 mb-0">
                    <i class="fas fa-stethoscope text-primary me-2"></i> Clinical Details &amp; Symptoms
                </h5>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-condition-name">
                        Reason for Visit / Condition <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control rounded-3 py-2" id="edit-condition-name" name="condition_name" placeholder="e.g. Telehealth Follow-up, Routine Cardiology Checkup" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-symptoms">
                        Symptoms &amp; Patient Description
                    </label>
                    <input type="text" class="form-control rounded-3 py-2" id="edit-symptoms" name="symptoms" placeholder="e.g. Mild pain, recurring headaches, dizziness">
                </div>
                <div class="col-12">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-appt-notes">
                        Attending Physician Notes / Instructions
                    </label>
                    <textarea class="form-control rounded-3 py-2" id="edit-appt-notes" name="notes" rows="3" placeholder="Clinical notes, consultation outcomes, follow-up instructions..."></textarea>
                </div>
            </div>
        </div>

        <!-- Section C: Patient Information & Demographics -->
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                <h5 class="fw-bold text-dark fs-5 mb-0">
                    <i class="fas fa-user-injured text-primary me-2"></i> Patient Information
                </h5>
                <span class="badge bg-light text-secondary border rounded-pill px-3 py-1 small">
                    Patient ID: <strong id="dior-display-patient-code">—</strong>
                </span>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-patient-select">
                        <i class="fas fa-user-check text-primary me-1"></i> Patient Assignment <span class="text-danger">*</span>
                    </label>
                    <select class="form-select rounded-3 py-2 fw-semibold" id="edit-patient-select" onchange="diorOnPatientAssignedChange(this.value)">
                        <?php foreach ($all_clinic_patients as $cp): 
                            $p_id = (int)$cp['user_id'];
                        ?>
                            <option value="<?php echo $p_id; ?>"
                                data-code="<?php echo esc_attr($cp['patient_uid']); ?>"
                                data-firstname="<?php echo esc_attr($cp['first_name']); ?>"
                                data-lastname="<?php echo esc_attr($cp['last_name']); ?>"
                                data-phone="<?php echo esc_attr($cp['phone']); ?>"
                                data-email="<?php echo esc_attr($cp['email']); ?>"
                                data-gender="<?php echo esc_attr($cp['gender']); ?>"
                                data-dob="<?php echo esc_attr($cp['dob']); ?>"
                                data-blood="<?php echo esc_attr($cp['blood_group']); ?>"
                                data-address="<?php echo esc_attr($cp['address']); ?>"
                                <?php selected($initial_patient_id, $p_id); ?>>
                                <?php echo esc_html(trim($cp['first_name'] . ' ' . $cp['last_name'])) . ' · ' . esc_html($cp['patient_uid']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-patient-firstname">
                        First Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control rounded-3 py-2" id="edit-patient-firstname" name="first_name" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-patient-lastname">
                        Last Name
                    </label>
                    <input type="text" class="form-control rounded-3 py-2" id="edit-patient-lastname" name="last_name">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-patient-gender">
                        Gender <span class="text-danger">*</span>
                    </label>
                    <select class="form-select rounded-3 py-2" id="edit-patient-gender" name="gender">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-patient-phone">
                        Mobile Phone <span class="text-danger">*</span>
                    </label>
                    <input type="tel" class="form-control rounded-3 py-2" id="edit-patient-phone" name="phone" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-patient-email">
                        Email Address
                    </label>
                    <input type="email" class="form-control rounded-3 py-2" id="edit-patient-email" name="email" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-patient-dob">
                        Date of Birth
                    </label>
                    <input type="date" class="form-control rounded-3 py-2" id="edit-patient-dob" name="dob">
                </div>

                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-patient-blood">
                        Blood Group
                    </label>
                    <select class="form-select rounded-3 py-2" id="edit-patient-blood" name="blood_group">
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                        <option value="unknown">Unknown</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-patient-address">
                        Residential Address
                    </label>
                    <textarea class="form-control rounded-3 py-2" id="edit-patient-address" name="address" rows="1" placeholder="Street, City, State, ZIP"></textarea>
                </div>
            </div>
        </div>

        <!-- Section D: Medical History & Background -->
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                <h5 class="fw-bold text-dark fs-5 mb-0">
                    <i class="fas fa-notes-medical text-primary me-2"></i> Medical History &amp; Background
                </h5>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-medical-conditions">
                        Existing Medical Conditions
                    </label>
                    <textarea class="form-control rounded-3 py-2" id="edit-medical-conditions" name="medical_conditions" rows="2" placeholder="e.g. Hypertension, Type 2 Diabetes, Asthma"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-medications">
                        Current Medications
                    </label>
                    <textarea class="form-control rounded-3 py-2" id="edit-medications" name="medications" rows="2" placeholder="e.g. Metformin 500mg, Lisinopril 10mg"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-allergies">
                        Allergies
                    </label>
                    <textarea class="form-control rounded-3 py-2" id="edit-allergies" name="allergies" rows="2" placeholder="e.g. Penicillin, Sulfa, Peanuts"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-surgeries">
                        Past Surgeries &amp; Procedures
                    </label>
                    <textarea class="form-control rounded-3 py-2" id="edit-surgeries" name="surgeries" rows="2" placeholder="e.g. Appendectomy (2018), Knee Arthroscopy (2021)"></textarea>
                </div>
            </div>
        </div>

        <!-- Section E: Emergency Contact & Insurance Information -->
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                <h5 class="fw-bold text-dark fs-5 mb-0">
                    <i class="fas fa-shield-halved text-primary me-2"></i> Emergency Contact &amp; Insurance
                </h5>
            </div>
            <div class="row g-3">
                <!-- Emergency Contact -->
                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-emergency-name">
                        Emergency Contact Name
                    </label>
                    <input type="text" class="form-control rounded-3 py-2" id="edit-emergency-name" name="emergency_contact_name" placeholder="Contact Full Name">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-emergency-relation">
                        Relationship to Patient
                    </label>
                    <select class="form-select rounded-3 py-2" id="edit-emergency-relation" name="emergency_contact_relation">
                        <option value="spouse">Spouse</option>
                        <option value="parent">Parent</option>
                        <option value="child">Child</option>
                        <option value="sibling">Sibling</option>
                        <option value="friend">Friend</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-emergency-phone">
                        Emergency Contact Phone
                    </label>
                    <input type="tel" class="form-control rounded-3 py-2" id="edit-emergency-phone" name="emergency_contact_phone" placeholder="Phone Number">
                </div>

                <!-- Insurance -->
                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-insurance-provider">
                        Insurance Provider
                    </label>
                    <input type="text" class="form-control rounded-3 py-2" id="edit-insurance-provider" name="insurance_provider" placeholder="e.g. Blue Cross, Aetna, Cigna">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-insurance-policy">
                        Policy Number
                    </label>
                    <input type="text" class="form-control rounded-3 py-2" id="edit-insurance-policy" name="insurance_policy_number" placeholder="Policy ID">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-insurance-group">
                        Group Number
                    </label>
                    <input type="text" class="form-control rounded-3 py-2" id="edit-insurance-group" name="insurance_group_number" placeholder="Group ID">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-insurance-holder">
                        Insurance Holder Name
                    </label>
                    <input type="text" class="form-control rounded-3 py-2" id="edit-insurance-holder" name="insurance_holder_name" placeholder="Primary Insured Name">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="edit-insurance-relation">
                        Holder Relationship
                    </label>
                    <select class="form-select rounded-3 py-2" id="edit-insurance-relation" name="insurance_relation">
                        <option value="self">Self</option>
                        <option value="spouse">Spouse</option>
                        <option value="child">Child</option>
                        <option value="parent">Parent</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section F: Form Action Buttons -->
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-5">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm" id="dior-edit-appt-submit-btn">
                        <i class="fas fa-calendar-check me-2"></i> Save &amp; Update Appointment
                    </button>
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4 py-2 fw-semibold" onclick="diorResetEditApptForm()">
                        <i class="fas fa-rotate-left me-1"></i> Reset
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold" onclick="if(typeof diorAdminSwitchTab==='function') diorAdminSwitchTab('doc-appointments-view'); else if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments-view');">
                        <i class="fas fa-list me-1"></i> Return to Appointments List
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Embedded Appointment Data & Dynamic Controller Scripts -->
    <script>
    window.diorAllAppointmentsData = <?php echo json_encode($appts_data_map); ?>;
    window.diorPatientsApptsLookup = <?php echo json_encode($patients_appts_lookup); ?>;
    window.diorAllPatientsList     = <?php echo json_encode(array_values($all_clinic_patients)); ?>;
    window.diorCurrentActiveEditUid = '<?php echo esc_js($first_appt_uid); ?>';

    window.diorSetTimePreset = function(timeVal, btnEl) {
        var input = document.getElementById('edit-appt-time-input');
        if (input) input.value = timeVal;

        // Visual active state for time presets
        var presetGroup = document.getElementById('dior-time-preset-group');
        if (presetGroup) {
            presetGroup.querySelectorAll('.dior-time-preset').forEach(function(b) {
                b.classList.remove('btn-primary', 'text-white');
                b.classList.add('btn-outline-primary');
            });
        }
        if (btnEl) {
            btnEl.classList.remove('btn-outline-primary');
            btnEl.classList.add('btn-primary', 'text-white');
        }
    };

    window.diorTestMeetingLink = function() {
        var link = document.getElementById('edit-join-url').value;
        if (link && link.trim()) {
            window.open(link.trim(), '_blank');
        } else {
            if (window.Swal) Swal.fire({icon: 'info', title: 'No Meeting Link', text: 'Enter a valid meeting URL to test.'});
            else alert('Please enter a valid meeting URL.');
        }
    };

    window.diorResetEditApptForm = function() {
        if (window.diorCurrentActiveEditUid) {
            diorLoadAppointmentToEdit(window.diorCurrentActiveEditUid);
        }
    };

    // Build or refresh the appointment options in #dior-edit-appt-selector for a patient
    window.diorPopulateApptOptions = function(patientId, selectedUid) {
        var apptSelector = document.getElementById('dior-edit-appt-selector');
        if (!apptSelector) return;
        apptSelector.innerHTML = '';

        if (!patientId) {
            // All appointments across all patients
            var optDefault = document.createElement('option');
            optDefault.value = '';
            optDefault.textContent = '-- Choose an appointment from records (' + Object.keys(window.diorAllAppointmentsData).length + ' total) --';
            apptSelector.appendChild(optDefault);

            Object.keys(window.diorAllAppointmentsData).forEach(function(uid) {
                var a = window.diorAllAppointmentsData[uid];
                var opt = document.createElement('option');
                opt.value = uid;
                opt.setAttribute('data-patient-id', a.patient_id);
                opt.textContent = '#' + uid + ' — ' + a.full_name + ' | ' + a.appt_date + ' (' + a.appt_time + ') [' + a.status + ']';
                if (selectedUid && selectedUid === uid) opt.selected = true;
                apptSelector.appendChild(opt);
            });
        } else {
            // Appointments for this specific patient
            var patAppts = window.diorPatientsApptsLookup[patientId] || [];
            if (patAppts.length > 0) {
                var optDefault = document.createElement('option');
                optDefault.value = '';
                optDefault.textContent = '-- Choose an appointment for this patient (' + patAppts.length + ' found) --';
                apptSelector.appendChild(optDefault);

                patAppts.forEach(function(a) {
                    var opt = document.createElement('option');
                    opt.value = a.uid;
                    opt.setAttribute('data-patient-id', patientId);
                    opt.textContent = '#' + a.uid + ' — ' + a.appt_date + ' (' + a.appt_time + ') [' + a.status + '] - ' + a.visit_type;
                    if (selectedUid && selectedUid === a.uid) opt.selected = true;
                    apptSelector.appendChild(opt);
                });
            } else {
                var optNew = document.createElement('option');
                optNew.value = 'new';
                optNew.setAttribute('data-patient-id', patientId);
                optNew.textContent = '+ Schedule / Setup New Appointment for this Patient';
                optNew.selected = true;
                apptSelector.appendChild(optNew);
            }
        }
    };

    window.diorOnHeaderPatientSelect = function(patientId) {
        var alertBox = document.getElementById('dior-edit-patient-alert');
        var alertMsg = document.getElementById('dior-edit-patient-alert-msg');

        if (!patientId) {
            if (alertBox) alertBox.classList.add('d-none');
            window.diorPopulateApptOptions('', '');
            if (window.diorCurrentActiveEditUid && window.diorAllAppointmentsData[window.diorCurrentActiveEditUid]) {
                window.diorLoadAppointmentToEdit(window.diorCurrentActiveEditUid);
            }
            return;
        }

        // Sync Section C select
        var patAssignSelect = document.getElementById('edit-patient-select');
        if (patAssignSelect && patAssignSelect.value != patientId) {
            patAssignSelect.value = patientId;
        }

        var patAppts = window.diorPatientsApptsLookup[patientId] || [];
        if (patAppts.length > 0) {
            var latestUid = patAppts[0].uid;
            window.diorPopulateApptOptions(patientId, latestUid);
            var apptSelect = document.getElementById('dior-edit-appt-selector');
            if (apptSelect) apptSelect.value = latestUid;
            window.diorLoadAppointmentToEdit(latestUid);
            if (alertBox) {
                alertBox.classList.remove('d-none');
                if (alertMsg) alertMsg.textContent = 'Loaded appointment #' + latestUid + ' for selected patient. Choose a different appointment above if needed.';
            }
        } else {
            // Patient has no appointments yet: load patient profile data directly
            window.diorPopulateApptOptions(patientId, 'new');
            window.diorLoadPatientProfileOnly(patientId);
            if (alertBox) {
                alertBox.classList.remove('d-none');
                if (alertMsg) alertMsg.textContent = 'Patient demographics and clinical profile loaded. No prior appointments exist; configure date, time, and details below to schedule.';
            }
        }
    };

    window.diorOnHeaderApptSelect = function(uid) {
        if (!uid) return;
        if (uid === 'new') {
            var patId = document.getElementById('dior-edit-patient-selector').value || document.getElementById('edit-patient-id').value;
            if (patId) window.diorLoadPatientProfileOnly(patId);
            return;
        }
        window.diorLoadAppointmentToEdit(uid);
    };

    window.diorLoadPatientProfileOnly = function(patientId) {
        if (!patientId) return;
        document.getElementById('edit-appt-uid').value = 'new';
        document.getElementById('edit-patient-id').value = patientId;

        var badgeId = document.getElementById('dior-badge-appt-id');
        if (badgeId) badgeId.textContent = 'New Appt';
        var badgeStatus = document.getElementById('dior-badge-status-text');
        if (badgeStatus) badgeStatus.textContent = 'Scheduling';
        var dispUid = document.getElementById('dior-display-appt-uid');
        if (dispUid) dispUid.textContent = 'New Appointment';

        // Check if patient in all patients list
        var pObj = window.diorAllPatientsList.find(function(p) { return p.user_id == patientId; });
        if (pObj) {
            var dispPatCode = document.getElementById('dior-display-patient-code');
            if (dispPatCode) dispPatCode.textContent = pObj.patient_uid || ('ID: ' + patientId);

            document.getElementById('edit-patient-firstname').value = pObj.first_name || '';
            document.getElementById('edit-patient-lastname').value = pObj.last_name || '';
            document.getElementById('edit-patient-phone').value = pObj.phone || '';
            document.getElementById('edit-patient-email').value = pObj.email || '';
            document.getElementById('edit-patient-gender').value = (pObj.gender || 'male').toLowerCase();
            document.getElementById('edit-patient-dob').value = pObj.dob || '';
            document.getElementById('edit-patient-blood').value = pObj.blood_group || 'A+';
            document.getElementById('edit-patient-address').value = pObj.address || '';
        }

        // Fetch complete patient profile via AJAX for complete details
        var ajaxUrl = window.diorAdminAjaxUrl || (window.dior_doctor_vars && window.dior_doctor_vars.ajax_url) || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var nonce   = window.diorAdminNonce || (window.dior_doctor_vars && window.dior_doctor_vars.nonce) || '<?php echo esc_js($doctor_nonce); ?>';

        fetch(ajaxUrl + '?action=dior_doctor_get_patient_appointment_details&patient_id=' + patientId + '&nonce=' + nonce)
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (res.success && res.data && res.data.patient) {
                var dp = res.data.patient;
                var dispPatCode = document.getElementById('dior-display-patient-code');
                if (dispPatCode) dispPatCode.textContent = dp.patient_uid || ('ID: ' + patientId);

                document.getElementById('edit-patient-firstname').value = dp.first_name || '';
                document.getElementById('edit-patient-lastname').value = dp.last_name || '';
                document.getElementById('edit-patient-phone').value = dp.phone || '';
                document.getElementById('edit-patient-email').value = dp.email || '';
                document.getElementById('edit-patient-gender').value = (dp.gender || 'male').toLowerCase();
                document.getElementById('edit-patient-dob').value = dp.dob || '';
                document.getElementById('edit-patient-blood').value = dp.blood_group || 'A+';
                document.getElementById('edit-patient-address').value = dp.address || '';

                document.getElementById('edit-medical-conditions').value = dp.medical_conditions || '';
                document.getElementById('edit-medications').value = dp.medications || '';
                document.getElementById('edit-allergies').value = dp.allergies || '';
                document.getElementById('edit-surgeries').value = dp.surgeries || '';

                document.getElementById('edit-emergency-name').value = dp.emergency_contact_name || '';
                document.getElementById('edit-emergency-relation').value = (dp.emergency_contact_relation || 'parent').toLowerCase();
                document.getElementById('edit-emergency-phone').value = dp.emergency_contact_phone || '';

                document.getElementById('edit-insurance-provider').value = dp.insurance_provider || '';
                document.getElementById('edit-insurance-policy').value = dp.insurance_policy_number || '';
                document.getElementById('edit-insurance-group').value = dp.insurance_group_number || '';
                document.getElementById('edit-insurance-holder').value = dp.insurance_holder_name || '';
                document.getElementById('edit-insurance-relation').value = (dp.insurance_relation || 'self').toLowerCase();
            }
        })
        .catch(function(e) {
            console.log('Error loading patient details', e);
        });
    };

    window.diorOnPatientAssignedChange = function(patientId) {
        if (!patientId) return;
        var topFilter = document.getElementById('dior-edit-patient-selector');
        if (topFilter && topFilter.value != patientId) {
            topFilter.value = patientId;
        }
        window.diorOnHeaderPatientSelect(patientId);
    };

    window.diorLoadAppointmentToEdit = function(uid) {
        if (!uid) return;

        // If not in local data map, attempt live fetch via AJAX
        if (!window.diorAllAppointmentsData || !window.diorAllAppointmentsData[uid]) {
            var ajaxUrl = window.diorAdminAjaxUrl || (window.dior_doctor_vars && window.dior_doctor_vars.ajax_url) || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var nonce   = window.diorAdminNonce || (window.dior_doctor_vars && window.dior_doctor_vars.nonce) || '<?php echo esc_js($doctor_nonce); ?>';

            fetch(ajaxUrl + '?action=dior_doctor_get_patient_appointment_details&appt_uid=' + uid + '&nonce=' + nonce)
            .then(function(res) { return res.json(); })
            .then(function(resp) {
                if (resp.success && resp.data && resp.data.appointment) {
                    var a = resp.data.appointment;
                    var p = resp.data.patient || {};
                    window.diorAllAppointmentsData[uid] = Object.assign({}, p, a, {
                        full_name: (p.first_name || '') + ' ' + (p.last_name || ''),
                        patient_code: p.patient_uid || ('DM-' + (10000 + (a.patient_id || 0)))
                    });
                    diorApplyAppointmentDataToForm(window.diorAllAppointmentsData[uid]);
                }
            })
            .catch(function(err) {
                console.log('Error fetching live appointment details:', err);
            });
            return;
        }

        diorApplyAppointmentDataToForm(window.diorAllAppointmentsData[uid]);
    };

    function diorApplyAppointmentDataToForm(data) {
        if (!data || !data.appt_uid) return;
        var uid = data.appt_uid;
        window.diorCurrentActiveEditUid = uid;

        // Update selector dropdown if needed
        var selector = document.getElementById('dior-edit-appt-selector');
        if (selector && selector.value !== uid) {
            selector.value = uid;
        }

        // Sync patient dropdowns
        var patAssignSelect = document.getElementById('edit-patient-select');
        if (patAssignSelect && data.patient_id && patAssignSelect.value != data.patient_id) {
            patAssignSelect.value = data.patient_id;
        }
        var topPatientSelect = document.getElementById('dior-edit-patient-selector');
        if (topPatientSelect && data.patient_id && topPatientSelect.value != data.patient_id) {
            topPatientSelect.value = data.patient_id;
            window.diorPopulateApptOptions(data.patient_id, uid);
        }

        // Header Badges
        var badgeId = document.getElementById('dior-badge-appt-id');
        if (badgeId) badgeId.textContent = '#' + uid;
        var badgeStatus = document.getElementById('dior-badge-status-text');
        if (badgeStatus) badgeStatus.textContent = data.status || 'Success';
        var dispUid = document.getElementById('dior-display-appt-uid');
        if (dispUid) dispUid.textContent = uid;
        var dispPatCode = document.getElementById('dior-display-patient-code');
        if (dispPatCode) dispPatCode.textContent = data.patient_code || data.patient_uid || ('ID: ' + data.patient_id);

        // Hidden Inputs
        document.getElementById('edit-appt-uid').value = uid;
        document.getElementById('edit-patient-id').value = data.patient_id || '';

        // Scheduling fields
        document.getElementById('edit-appt-date').value = data.appt_date || '';
        document.getElementById('edit-appt-time-input').value = data.appt_time || '';
        document.getElementById('edit-appt-status').value = data.status || 'Success';
        document.getElementById('edit-visit-type').value = data.visit_type || 'Video Visit (HD)';
        document.getElementById('edit-payment-status').value = data.payment_status || 'Paid';
        document.getElementById('edit-join-url').value = data.join_url || '';

        if (document.getElementById('edit-consulting-doctor')) {
            document.getElementById('edit-consulting-doctor').value = data.doctor_id || '<?php echo esc_js($doctor_id); ?>';
        }

        // Clinical Details
        document.getElementById('edit-condition-name').value = data.condition_name || '';
        document.getElementById('edit-symptoms').value = data.symptoms || '';
        document.getElementById('edit-appt-notes').value = data.notes || '';

        // Patient Demographics
        document.getElementById('edit-patient-firstname').value = data.first_name || '';
        document.getElementById('edit-patient-lastname').value = data.last_name || '';
        document.getElementById('edit-patient-phone').value = data.phone || '';
        document.getElementById('edit-patient-email').value = data.email || '';
        document.getElementById('edit-patient-gender').value = (data.gender || 'male').toLowerCase();
        document.getElementById('edit-patient-dob').value = data.dob || '';
        document.getElementById('edit-patient-blood').value = data.blood_group || 'A+';
        document.getElementById('edit-patient-address').value = data.address || '';

        // Medical History
        document.getElementById('edit-medical-conditions').value = data.medical_conditions || '';
        document.getElementById('edit-medications').value = data.medications || '';
        document.getElementById('edit-allergies').value = data.allergies || '';
        document.getElementById('edit-surgeries').value = data.surgeries || '';

        // Emergency Contact
        document.getElementById('edit-emergency-name').value = data.emergency_contact_name || '';
        document.getElementById('edit-emergency-relation').value = (data.emergency_contact_relation || 'spouse').toLowerCase();
        document.getElementById('edit-emergency-phone').value = data.emergency_contact_phone || '';

        // Insurance
        document.getElementById('edit-insurance-provider').value = data.insurance_provider || '';
        document.getElementById('edit-insurance-policy').value = data.insurance_policy_number || '';
        document.getElementById('edit-insurance-group').value = data.insurance_group_number || '';
        document.getElementById('edit-insurance-holder').value = data.insurance_holder_name || '';
        document.getElementById('edit-insurance-relation').value = (data.insurance_relation || 'self').toLowerCase();
    }

    // Global navigation handler to switch to edit tab and populate record
    window.diorOpenFullAppointmentEdit = function(uid) {
        if (typeof diorAdminSwitchTab === 'function') {
            diorAdminSwitchTab('doc-appointments-edit');
        } else if (typeof diorDocSwitchTab === 'function') {
            diorDocSwitchTab('doc-appointments-edit');
        } else {
            var tabBtn = document.querySelector('.dior-nav-btn[data-tab="doc-appointments-edit"]');
            if (tabBtn) tabBtn.click();
        }
        setTimeout(function() {
            if (uid) {
                if (window.diorAllAppointmentsData && window.diorAllAppointmentsData[uid]) {
                    var apptObj = window.diorAllAppointmentsData[uid];
                    var topPatientSelect = document.getElementById('dior-edit-patient-selector');
                    if (topPatientSelect && apptObj.patient_id) {
                        topPatientSelect.value = apptObj.patient_id;
                        window.diorPopulateApptOptions(apptObj.patient_id, uid);
                    }
                }
                window.diorLoadAppointmentToEdit(uid);
            }
            var panel = document.getElementById('tab-doc-appointments-edit');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 120);
    };

    (function() {
        function initAppointmentEditForm() {
            var form = document.getElementById('dior-appointment-edit-form');
            if (!form || form.dataset.bound) return;
            form.dataset.bound = 'true';

            // Check URL parameters for explicit appt_uid
            var urlParams = new URLSearchParams(window.location.search);
            var paramApptUid = urlParams.get('appt_uid') || urlParams.get('appt') || urlParams.get('uid');
            var targetUid = paramApptUid || window.diorCurrentActiveEditUid;

            if (targetUid && window.diorAllAppointmentsData && window.diorAllAppointmentsData[targetUid]) {
                var targetAppt = window.diorAllAppointmentsData[targetUid];
                var topPat = document.getElementById('dior-edit-patient-selector');
                if (topPat && targetAppt.patient_id) {
                    topPat.value = targetAppt.patient_id;
                    window.diorPopulateApptOptions(targetAppt.patient_id, targetUid);
                }
                window.diorLoadAppointmentToEdit(targetUid);
            } else if (targetUid) {
                window.diorLoadAppointmentToEdit(targetUid);
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var apptUid = document.getElementById('edit-appt-uid').value;
                var patientId = document.getElementById('edit-patient-id').value;
                if (!apptUid && !patientId) {
                    if (window.Swal) Swal.fire({icon: 'warning', title: 'Selection Required', text: 'Please select a patient or an appointment to update.'});
                    else alert('Please select a patient or an appointment to update.');
                    return;
                }
                if (!apptUid) {
                    apptUid = 'new';
                    document.getElementById('edit-appt-uid').value = 'new';
                }

                var submitBtn = document.getElementById('dior-edit-appt-submit-btn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Saving Changes...';

                var fd = new FormData(form);
                fd.append('action', 'dior_doctor_edit_appointment');
                var nonce = window.diorAdminNonce ||
                            (window.dior_doctor_vars && window.dior_doctor_vars.nonce) ||
                            (window.dior_vars && window.dior_vars.nonce) ||
                            '<?php echo esc_js($doctor_nonce); ?>';
                fd.append('nonce', nonce);

                var ajaxUrl = window.diorAdminAjaxUrl ||
                              (window.dior_doctor_vars && window.dior_doctor_vars.ajax_url) ||
                              (window.dior_vars && window.dior_vars.ajax_url) ||
                              '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                })
                .then(function(res) { return res.json(); })
                .then(function(result) {
                    if (!result.success) {
                        throw new Error((result.data && result.data.message) || 'Failed to update appointment.');
                    }

                    // Update in-memory data
                    var updated = result.data.appointment || {};
                    var newUid = updated.appt_uid || apptUid;
                    window.diorCurrentActiveEditUid = newUid;
                    document.getElementById('edit-appt-uid').value = newUid;
                    var dispUid = document.getElementById('dior-display-appt-uid');
                    if (dispUid) dispUid.textContent = newUid;
                    var badgeId = document.getElementById('dior-badge-appt-id');
                    if (badgeId) badgeId.textContent = '#' + newUid;
                    var statusBadge = document.getElementById('dior-badge-status-text');
                    if (statusBadge) statusBadge.textContent = fd.get('status');

                    // If it was a new appointment, add it to lookup tables
                    if (!window.diorAllAppointmentsData[newUid]) {
                        window.diorAllAppointmentsData[newUid] = updated;
                        var patId = updated.patient_id;
                        if (!window.diorPatientsApptsLookup[patId]) window.diorPatientsApptsLookup[patId] = [];
                        window.diorPatientsApptsLookup[patId].unshift({
                            uid: newUid,
                            patient_name: (updated.first_name || '') + ' ' + (updated.last_name || ''),
                            appt_date: updated.appt_date,
                            appt_time: updated.appt_time,
                            status: updated.status,
                            visit_type: updated.visit_type,
                            condition_name: updated.condition_name
                        });
                        window.diorPopulateApptOptions(patId, newUid);
                    } else {
                        var target = window.diorAllAppointmentsData[newUid];
                        target.status = fd.get('status') || target.status;
                        target.appt_date = fd.get('appt_date') || target.appt_date;
                        target.appt_time = fd.get('appt_time') || target.appt_time;
                        target.condition_name = fd.get('condition_name') || target.condition_name;
                        target.visit_type = fd.get('visit_type') || target.visit_type;
                        target.payment_status = fd.get('payment_status') || target.payment_status;
                        target.notes = fd.get('notes') || target.notes;
                        target.first_name = fd.get('first_name') || target.first_name;
                        target.last_name = fd.get('last_name') || target.last_name;
                        target.phone = fd.get('phone') || target.phone;
                    }

                    if (window.Swal) {
                        Swal.fire({
                            position: 'top-end',
                            icon: 'success',
                            title: 'Appointment Updated',
                            text: 'Appointment #' + newUid + ' has been saved and updated successfully.',
                            showConfirmButton: false,
                            timer: 2200
                        });
                    } else {
                        alert('Appointment #' + newUid + ' has been saved and updated successfully.');
                    }
                })
                .catch(function(err) {
                    if (window.Swal) {
                        Swal.fire({icon: 'error', title: 'Update Failed', text: err.message});
                    } else {
                        alert('Update failed: ' + err.message);
                    }
                })
                .finally(function() {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-calendar-check me-2"></i> Save &amp; Update Appointment';
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAppointmentEditForm);
        } else {
            initAppointmentEditForm();
        }
    })();
    </script>
</section>
