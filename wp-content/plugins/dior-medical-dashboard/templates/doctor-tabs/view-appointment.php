<?php
/**
 * View Appointment Tab — Dynamic, DB-connected
 * 100% Native Bootstrap 5 Components & Utilities (Zero Custom CSS)
 */
if (!defined('ABSPATH')) { exit; }

global $wpdb;
$doctor_id = get_current_user_id();
$nonce = wp_create_nonce('dior_doctor_nonce');
$ajax_url = admin_url('admin-ajax.php');

// Fetch patient list for edit modal dropdown
$edit_patients = $wpdb->get_results(
    "SELECT user_id, first_name, last_name, patient_uid 
     FROM {$wpdb->prefix}dior_patients 
     WHERE status <> 'deleted' 
     ORDER BY first_name ASC 
     LIMIT 500", 
    ARRAY_A
);

// Fetch doctors list for filter and edit modal
$all_doc_users = get_users(['role__in' => ['doctor', 'dior_doctor', 'administrator'], 'orderby' => 'display_name', 'order' => 'ASC']);
$doctor_list = [];
foreach ($all_doc_users as $du) {
    if (in_array('administrator', (array)$du->roles, true) && !in_array('doctor', (array)$du->roles, true)) {
        continue;
    }
    $d_name = trim($du->first_name . ' ' . $du->last_name) ?: $du->display_name;
    if (stripos($d_name, 'Dr.') !== 0 && stripos($d_name, 'Dr ') !== 0) {
        $d_name = 'Dr. ' . $d_name;
    }
    $d_spec = get_user_meta($du->ID, 'doctor_specialty', true) ?: (get_user_meta($du->ID, 'dior_specialty', true) ?: 'General Practice & Internal Medicine');
    $d_dept = get_user_meta($du->ID, 'doctor_department', true) ?: 'Outpatient Care';
    $d_avatar = get_user_meta($du->ID, 'dior_profile_image', true) 
        ?: get_user_meta($du->ID, 'doctor_avatar', true)
        ?: ('https://ui-avatars.com/api/?name=' . urlencode($d_name) . '&background=00346c&color=ffffff&bold=true');
    
    $doctor_list[$du->ID] = [
        'id'         => $du->ID,
        'name'       => $d_name,
        'email'      => $du->user_email,
        'specialty'  => $d_spec,
        'department' => $d_dept,
        'avatar'     => $d_avatar,
    ];
}

// Fallback if doctor_list is empty
if (empty($doctor_list)) {
    $doctor_list[35] = [
        'id'         => 35,
        'name'       => 'Dr. Marcus Sterling',
        'email'      => 'marcus.sterling@diormedical.com',
        'specialty'  => 'Cardiology & Cardiovascular Surgery',
        'department' => 'Cardiology Department',
        'avatar'     => 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&w=400&q=80',
    ];
}

// Fetch appointments with full patient and doctor joins
$_va_is_admin = current_user_can('administrator') || current_user_can('manage_options');

if ($_va_is_admin) {
    $appointments = $wpdb->get_results(
        "SELECT a.*, 
                p.first_name, p.last_name, p.phone, p.gender, p.avatar_url, p.patient_uid, p.user_id as patient_user_id,
                u.display_name as user_display_name, u.user_email,
                doc.display_name as doctor_name, doc.user_email as doctor_email
         FROM {$wpdb->prefix}dior_appointments a
         LEFT JOIN {$wpdb->prefix}dior_patients p ON (p.user_id = a.patient_id OR p.id = a.patient_id)
         LEFT JOIN {$wpdb->users} u ON u.ID = a.patient_id
         LEFT JOIN {$wpdb->users} doc ON doc.ID = a.doctor_id
         WHERE a.is_deleted = 0
         ORDER BY a.appt_date DESC, a.created_at DESC
         LIMIT 250",
        ARRAY_A
    );
} else {
    $appointments = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, 
                p.first_name, p.last_name, p.phone, p.gender, p.avatar_url, p.patient_uid, p.user_id as patient_user_id,
                u.display_name as user_display_name, u.user_email,
                doc.display_name as doctor_name, doc.user_email as doctor_email
         FROM {$wpdb->prefix}dior_appointments a
         LEFT JOIN {$wpdb->prefix}dior_patients p ON (p.user_id = a.patient_id OR p.id = a.patient_id)
         LEFT JOIN {$wpdb->users} u ON u.ID = a.patient_id
         LEFT JOIN {$wpdb->users} doc ON doc.ID = a.doctor_id
         WHERE a.is_deleted = 0 AND a.doctor_id = %d
         ORDER BY a.appt_date DESC, a.created_at DESC
         LIMIT 250",
        $doctor_id
    ), ARRAY_A);
}

// Fallback: build from usermeta if table is empty, and migrate entries into DB so appt_uid is always set.
if (empty($appointments)) {
    $patients = get_users(['role__in' => ['patient', 'subscriber', 'customer'], 'number' => 50]);
    $appointments = [];
    $_va_appt_table = $wpdb->prefix . 'dior_appointments';
    foreach ($patients as $u) {
        $raw = get_user_meta($u->ID, 'dior_appointments', true);
        $list = is_array($raw) ? $raw : (is_string($raw) ? maybe_unserialize($raw) : []);
        if (!is_array($list)) continue;
        foreach ($list as $a) {
            // Build a stable UID from usermeta entry: migrate into DB if not yet there.
            $_meta_uid = !empty($a['id']) ? (string)$a['id'] : (!empty($a['appt_uid']) ? $a['appt_uid'] : '');
            $_db_uid   = '';
            if (!empty($_meta_uid)) {
                $_db_uid = $wpdb->get_var($wpdb->prepare(
                    "SELECT appt_uid FROM {$_va_appt_table} WHERE appt_uid = %s AND is_deleted = 0 LIMIT 1",
                    $_meta_uid
                ));
            }
            if (empty($_db_uid)) {
                // Insert into DB so actions (confirm/cancel) can find the row by appt_uid.
                $_new_uid  = 'APT-' . strtoupper(wp_generate_password(8, false, false));
                $_date_val = !empty($a['date']) ? $a['date'] : (!empty($a['appt_date']) ? $a['appt_date'] : current_time('Y-m-d'));
                $_time_val = !empty($a['time']) ? $a['time'] : (!empty($a['appt_time']) ? $a['appt_time'] : '10:00 AM');
                $_status_val = !empty($a['status']) ? $a['status'] : 'Pending';
                $wpdb->insert($_va_appt_table, [
                    'appt_uid'       => $_new_uid,
                    'patient_id'     => $u->ID,
                    'doctor_id'      => get_current_user_id(),
                    'condition_name' => !empty($a['condition']) ? $a['condition'] : 'General Consultation',
                    'visit_type'     => !empty($a['type']) ? $a['type'] : 'Video Visit (HD)',
                    'status'         => in_array($_status_val, ['Pending','Confirmed','In Progress','Completed','Cancelled','No Show'], true) ? $_status_val : 'Pending',
                    'appt_date'      => $_date_val,
                    'appt_time'      => $_time_val,
                    'join_url'       => !empty($a['join_url']) ? $a['join_url'] : '',
                    'payment_status' => !empty($a['payment_status']) ? $a['payment_status'] : 'Paid',
                    'notes'          => !empty($a['notes']) ? $a['notes'] : '',
                    'is_deleted'     => 0,
                    'created_at'     => current_time('mysql'),
                    'updated_at'     => current_time('mysql'),
                ]);
                $_db_uid = $_new_uid;
            }
            $a['appt_uid'] = $_db_uid;
            $appointments[] = array_merge($a, [
                'appt_uid'       => $_db_uid,
                'first_name'     => $u->first_name ?: $u->display_name,
                'last_name'      => $u->last_name,
                'phone'          => get_user_meta($u->ID, 'phone', true),
                'gender'         => get_user_meta($u->ID, 'gender', true),
                'avatar_url'     => get_user_meta($u->ID, 'dior_profile_image', true),
                'patient_uid'    => get_user_meta($u->ID, 'patient_id', true) ?: 'DM-' . (10000 + $u->ID),
                'patient_user_id'=> $u->ID,
                'patient_id'     => $u->ID,
                'doctor_id'      => get_current_user_id(),
                'doctor_name'    => 'Dr. Evelyn Vance, MD',
                'doctor_email'   => 'doctor@gmail.com',
            ]);
        }
    }
}

// Repair legacy appointment rows that have a database ID but no public appointment UID.
// Action handlers consistently identify appointments by appt_uid, so backfill a stable UID
// once and use it for all buttons instead of sending an empty ID.
if (!empty($appointments) && is_array($appointments)) {
    foreach ($appointments as $index => $appointment_row) {
        if (!empty($appointment_row['appt_uid'])) {
            continue;
        }
        $legacy_id = isset($appointment_row['id']) ? absint($appointment_row['id']) : 0;
        if (!$legacy_id) {
            continue;
        }

        $generated_uid = 'APT-' . strtoupper(wp_generate_password(8, false, false));
        $updated_uid = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}dior_appointments
             SET appt_uid = %s, updated_at = %s
             WHERE id = %d AND (appt_uid IS NULL OR appt_uid = '')",
            $generated_uid,
            current_time('mysql'),
            $legacy_id
        ));

        // Re-read the saved value if another request updated the same row concurrently.
        if ($updated_uid === false || $updated_uid === 0) {
            $saved_uid = $wpdb->get_var($wpdb->prepare(
                "SELECT appt_uid FROM {$wpdb->prefix}dior_appointments WHERE id = %d",
                $legacy_id
            ));
            if (!empty($saved_uid)) {
                $generated_uid = $saved_uid;
            } else {
                continue;
            }
        }
        $appointments[$index]['appt_uid'] = $generated_uid;
    }
}

// Compute KPI Stats dynamically
$total_appts = count($appointments);
$upcoming_appts = 0;
$completed_appts = 0;
$pending_appts = 0;
$today_str = current_time('Y-m-d');

foreach ($appointments as $item) {
    $st = strtolower($item['status'] ?? 'pending');
    $dt = $item['appt_date'] ?? '';
    if ($st === 'completed') {
        $completed_appts++;
    } elseif ($st === 'pending' || $st === 'in progress') {
        $pending_appts++;
    }
    if ($st !== 'cancelled' && $st !== 'completed' && $dt >= $today_str) {
        $upcoming_appts++;
    }
}

// 100% Native Bootstrap 5 status badges
$status_badges = [
    'Pending'     => 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 small fw-semibold',
    'Confirmed'   => 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold',
    'In Progress' => 'badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 small fw-semibold',
    'Completed'   => 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold',
    'Cancelled'   => 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 small fw-semibold',
    'No Show'     => 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 small fw-semibold',
];
?>

<section class="dior-tab-panel" id="tab-doc-appointments-view" style="display: none;">
    
    <!-- Hero Header (100% Native Bootstrap 5) -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="mb-1 text-dark fw-bold fs-5 d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="fas fa-calendar-check fs-6"></i>
                </span>
                View Appointments
            </h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small text-secondary">
                    <li class="breadcrumb-item"><i class="fas fa-house me-1"></i> Dashboard</li>
                    <li class="breadcrumb-item">Appointments</li>
                    <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">View Appointments</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" id="va-export-csv-btn">
                <i class="fas fa-file-export"></i> Export CSV
            </button>
            <button type="button" 
                onclick="if (typeof diorOpenDocBookerModal === 'function') { diorOpenDocBookerModal(); } else if (typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-appointments-book'); } else if (typeof diorAdminSwitchTab === 'function') { diorAdminSwitchTab('doc-appointments-book'); } else if (typeof diorSwitchTab === 'function') { diorSwitchTab('doc-appointments-book'); }"
                class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2">
                <i class="fas fa-plus"></i> Book Appointment
            </button>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-appointments" onclick="if(typeof diorAdminSwitchTab==='function') diorAdminSwitchTab('doc-appointments'); else if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments');">
            <i class="fas fa-calendar-days"></i> Appointment Calendar
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-appointments-view" onclick="if(typeof diorAdminSwitchTab==='function') diorAdminSwitchTab('doc-appointments-view'); else if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments-view');">
            <i class="fas fa-calendar-check"></i> View Appointment
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-appointments-book" onclick="if(typeof diorAdminSwitchTab==='function') diorAdminSwitchTab('doc-appointments-book'); else if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments-book');">
            <i class="fas fa-calendar-plus"></i> Book Appointment
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-appointments-edit" onclick="if(typeof diorAdminSwitchTab==='function') diorAdminSwitchTab('doc-appointments-edit'); else if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments-edit');">
            <i class="fas fa-pen-to-square"></i> Edit Appointment
        </button>
    </div>

    <!-- Quick Stat KPI Bar (100% Bootstrap 5 Cards) -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Total Bookings</span>
                        <div class="stat-num fs-1 fw-bold text-dark mt-1" id="va-kpi-total"><?php echo esc_html($total_appts); ?></div>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-3 p-2.5">
                        <i class="fas fa-calendar-days fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="text-primary fw-semibold"><i class="fas fa-chart-line me-1"></i>All records</span> across clinic
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Upcoming Visits</span>
                        <div class="stat-num fs-1 fw-bold text-primary mt-1" id="va-kpi-upcoming"><?php echo esc_html($upcoming_appts); ?></div>
                    </div>
                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-3 p-2.5">
                        <i class="fas fa-clock fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-0.5 fw-semibold">Scheduled ahead</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Completed</span>
                        <div class="stat-num fs-1 fw-bold text-success mt-1" id="va-kpi-completed"><?php echo esc_html($completed_appts); ?></div>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-3 p-2.5">
                        <i class="fas fa-circle-check fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 fw-semibold">Consultations done</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Pending Action</span>
                        <div class="stat-num fs-1 fw-bold text-warning mt-1" id="va-kpi-pending"><?php echo esc_html($pending_appts); ?></div>
                    </div>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-3 p-2.5">
                        <i class="fas fa-hourglass-half fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0.5 fw-semibold">Needs review</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Card (Standard View Appointment Card) -->
    <div class="view-appointment-card">
        
        <!-- Controls Header Bar -->
        <div class="va-header-container">
            <div class="va-title-box">
                <h2><i class="fas fa-calendar-check text-primary me-2"></i>Appointments Registry</h2>
                <div class="va-title-line"></div>
            </div>

            <!-- Bulk Selection & Action Toolbar -->
            <div class="va-actions-wrapper">
                <!-- Bulk Selection Toolbar (reveals when items checked) -->
                <div id="va-bulk-bar" class="d-none align-items-center gap-2">
                    <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 small fw-semibold" id="va-selected-count">0 Selected</span>
                    <button type="button" class="btn btn-outline-success btn-sm rounded-3 px-2.5 py-1 shadow-none fw-semibold" id="va-bulk-complete-btn" title="Mark as Completed">
                        <i class="fas fa-check me-1"></i>Complete
                    </button>
                </div>

                <!-- Search, Filters & Actions -->
                <div class="input-group input-group-sm dior-bs-search-group" style="width: 220px;">
                    <input type="text" id="va-appt-search" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search patient, doctor, UID...">
                </div>

                <!-- Doctor / Physician Filter -->
                <div style="width: 175px;">
                    <select id="va-doctor-filter" class="form-select form-select-sm shadow-none">
                        <option value="">All Doctors</option>
                        <?php foreach ($doctor_list as $doc_item): ?>
                            <option value="<?php echo esc_attr($doc_item['name']); ?>"><?php echo esc_html($doc_item['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div style="width: 140px;">
                    <select id="va-status-filter" class="form-select form-select-sm shadow-none">
                        <option value="">All Statuses</option>
                        <option value="Success">Success</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="No Show">No Show</option>
                    </select>
                </div>

                <!-- Date Range Filter -->
                <div style="width: 125px;">
                    <select id="va-date-filter" class="form-select form-select-sm shadow-none">
                        <option value="">All Dates</option>
                        <option value="today">Today</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="past">Past</option>
                    </select>
                </div>

                <div class="va-actions-group">
                    <button class="va-icon-btn va-btn-info btn" id="va-refresh-btn" aria-label="Refresh" title="Reset Filters & Refresh">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Responsive Table Wrapper -->
        <div class="va-table-wrapper">
            <table class="va-table table table-hover align-middle mb-0" id="va-appointments-table">
                <thead>
                    <tr>
                        <th style="width: 45px;" class="text-center">
                            <input type="checkbox" class="va-checkbox form-check-input" id="va-select-all" aria-label="Select all rows">
                        </th>
                        <th>PATIENT NAME <i class="fas fa-sort"></i></th>

                        <th>CONSULTATION / TYPE <i class="fas fa-sort"></i></th>
                        <th>DATE &amp; TIME <i class="fas fa-sort"></i></th>
                        <th>CONTACT <i class="fas fa-sort"></i></th>
                        <th>STATUS <i class="fas fa-sort"></i></th>
                        <th style="text-align: right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="va-appointments-tbody">
                    <?php if (!empty($appointments)): ?>
                        <?php foreach ($appointments as $a):
                            $fname = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
                            if (empty($fname)) {
                                $fname = !empty($a['user_display_name']) ? $a['user_display_name'] : 'Patient #' . ($a['patient_id'] ?? '');
                            }
                            $fname = esc_html($fname);
                            $uid = esc_attr($a['appt_uid'] ?? '');
                            $patient_id = !empty($a['patient_id']) ? (int)$a['patient_id'] : (!empty($a['patient_user_id']) ? (int)$a['patient_user_id'] : 0);
                            $status = esc_html($a['status'] ?? 'Pending');
                            $badge = $status_badges[$status] ?? 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                            $date_raw = !empty($a['appt_date']) ? $a['appt_date'] : (!empty($a['date']) ? $a['date'] : '');
                            // If still empty, fall back to created_at date
                            if (empty($date_raw) && !empty($a['created_at'])) {
                                $date_raw = date('Y-m-d', strtotime($a['created_at']));
                            }
                            $date_ts  = !empty($date_raw) ? strtotime($date_raw) : 0;
                            $date_fmt = $date_ts ? date('M d, Y', $date_ts) : 'N/A';
                            $time_raw = !empty($a['appt_time']) ? $a['appt_time'] : (!empty($a['time']) ? $a['time'] : '');
                            $time = esc_html($time_raw ?: 'Not set');
                            $phone = esc_html(!empty($a['phone']) ? $a['phone'] : (!empty($patient_id) ? get_user_meta($patient_id, 'phone', true) : '—')) ?: '—';
                            $email = esc_html(!empty($a['user_email']) ? $a['user_email'] : (!empty($patient_id) ? (get_userdata($patient_id)->user_email ?? '—') : '—')) ?: '—';
                            $condition = esc_html(!empty($a['condition_name']) ? $a['condition_name'] : (!empty($a['condition']) ? $a['condition'] : 'General Consultation'));
                            $visit_type = esc_html(!empty($a['visit_type']) ? $a['visit_type'] : 'Video Visit (HD)');
                            $notes = esc_attr($a['notes'] ?? '');
                            $avatar = esc_url(!empty($a['avatar_url']) ? $a['avatar_url'] : (!empty($patient_id) ? get_user_meta($patient_id, 'dior_profile_image', true) : ''));
                            $patient_uid = esc_attr(!empty($a['patient_uid']) ? $a['patient_uid'] : (!empty($patient_id) ? get_user_meta($patient_id, 'patient_id', true) : ('DM-' . (10000 + $patient_id))));
                            
                            $is_video = (stripos($visit_type, 'video') !== false || stripos($visit_type, 'telehealth') !== false);
                            $join_url = esc_url(!empty($a['join_url']) ? $a['join_url'] : 'https://zoom.us/join');
                            $payment_status = esc_html($a['payment_status'] ?? 'Paid');

                            // Doctor data resolution
                            $doc_id = (int)($a['doctor_id'] ?? 0);
                            // Prefer the appointment's own Zoom invite. If it is still the generic
                            // Zoom landing page, fall back to the assigned doctor's saved Zoom link.
                            if (rtrim($join_url, '/') === 'https://zoom.us/join') {
                                $doctor_saved_zoom = trim((string) get_user_meta($doc_id, 'doctor_zoom_link', true));
                                if (!empty($doctor_saved_zoom) && wp_http_validate_url($doctor_saved_zoom)) {
                                    $join_url = esc_url($doctor_saved_zoom);
                                }
                            }
                            $doc_info = $doctor_list[$doc_id] ?? null;
                            $doc_name = $doc_info['name'] ?? (!empty($a['doctor_name']) ? $a['doctor_name'] : 'Dr. Marcus Sterling');
                            if (stripos($doc_name, 'Dr.') !== 0 && stripos($doc_name, 'Dr ') !== 0) {
                                $doc_name = 'Dr. ' . $doc_name;
                            }
                            $doc_spec = $doc_info['specialty'] ?? (get_user_meta($doc_id, 'doctor_specialty', true) ?: 'General Medicine');
                            $doc_dept = $doc_info['department'] ?? (get_user_meta($doc_id, 'doctor_department', true) ?: 'Outpatient Care');
                            $doc_avatar = $doc_info['avatar'] ?? (get_user_meta($doc_id, 'dior_profile_image', true) ?: ('https://ui-avatars.com/api/?name=' . urlencode($doc_name) . '&background=00346c&color=ffffff&bold=true'));
                            $doc_email = $doc_info['email'] ?? ($a['doctor_email'] ?? 'doctor@diormedical.com');
                            ?>
                            <tr data-appt-uid="<?php echo $uid; ?>" 
                                data-patient-id="<?php echo $patient_id; ?>"
                                data-patient-uid="<?php echo $patient_uid; ?>"
                                data-patient-name="<?php echo $fname; ?>"
                                data-patient-avatar="<?php echo $avatar; ?>"
                                data-patient-phone="<?php echo esc_attr($phone); ?>"
                                data-patient-email="<?php echo esc_attr($email); ?>"
                                data-doctor-id="<?php echo $doc_id; ?>"
                                data-doctor-name="<?php echo esc_attr($doc_name); ?>"
                                data-doctor-specialty="<?php echo esc_attr($doc_spec); ?>"
                                data-doctor-department="<?php echo esc_attr($doc_dept); ?>"
                                data-doctor-avatar="<?php echo esc_url($doc_avatar); ?>"
                                data-doctor-email="<?php echo esc_attr($doc_email); ?>"
                                data-date="<?php echo esc_attr($date_raw); ?>"
                                data-date-fmt="<?php echo esc_attr($date_fmt); ?>"
                                data-time="<?php echo esc_attr($time); ?>"
                                data-status="<?php echo esc_attr($status); ?>"
                                data-condition="<?php echo esc_attr($condition); ?>"
                                data-visit-type="<?php echo esc_attr($visit_type); ?>"
                                data-join-url="<?php echo $join_url; ?>"
                                data-payment-status="<?php echo esc_attr($payment_status); ?>"
                                data-notes="<?php echo $notes; ?>"
                                data-phone="<?php echo esc_attr($phone); ?>">
                                <td class="text-center">
                                    <input type="checkbox" class="va-checkbox form-check-input va-row-check" aria-label="Select appointment">
                                </td>
                                <td>
                                    <div class="va-user-profile">
                                        <img src="<?php echo $avatar ?: 'https://ui-avatars.com/api/?name=' . urlencode($fname) . '&background=00346c&color=ffffff&bold=true'; ?>"
                                            onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($fname); ?>&background=00346c&color=ffffff&bold=true'"
                                            class="va-user-avatar" alt="<?php echo $fname; ?>">
                                        <div>
                                            <span class="va-user-name va-view-patient-btn d-block text-decoration-none role-button"
                                                role="button"
                                                data-patient-id="<?php echo $patient_id; ?>" title="View Patient Profile">
                                                <?php echo $fname; ?>
                                            </span>
                                            <div class="d-flex align-items-center gap-1.5" style="font-size: 11px;">
                                                <span class="text-secondary"><?php echo $patient_uid; ?></span>
                                                <span class="text-secondary opacity-50">&bull;</span>
                                                <a href="javascript:void(0)" class="va-view-details-btn text-primary fw-semibold text-decoration-none" data-uid="<?php echo $uid; ?>" title="View Appointment Details">
                                                    <?php echo $uid; ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="d-flex flex-column" style="max-width: 240px;">
                                        <span class="text-dark small fw-semibold text-truncate d-block" title="<?php echo esc_attr($condition); ?>"><?php echo $condition; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column va-datetime-cell text-nowrap">
                                        <span class="text-dark small fw-semibold va-cell-date">
                                            <i class="far fa-calendar text-primary me-1"></i><?php echo $date_fmt; ?>
                                        </span>
                                        <span class="text-secondary small va-cell-time" style="font-size: 11px;">
                                            <i class="far fa-clock me-1"></i><?php echo $time; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($phone !== '—'): ?>
                                        <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>" class="text-secondary small text-decoration-none text-nowrap">
                                            <i class="fas fa-phone text-success me-1"></i><?php echo $phone; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?php echo $badge; ?> va-badge text-nowrap"><?php echo $status; ?></span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill va-view-patient-btn shadow-none"
                                            data-patient-id="<?php echo $patient_id; ?>"
                                            title="View Patient Profile"
                                            style="padding: 4px 10px !important; font-size: 12px !important; font-weight: 600 !important; line-height: 1.3 !important;">
                                            <i class="fas fa-eye me-1"></i>View
                                        </button>
                                        <?php if (strtolower($status) === 'pending'): ?>
                                            <button type="button" class="btn btn-success btn-sm rounded-pill text-white va-confirm-appt-btn shadow-none"
                                                data-uid="<?php echo $uid; ?>"
                                                data-name="<?php echo esc_attr($fname); ?>"
                                                title="Confirm Appointment"
                                                style="padding: 4px 10px !important; font-size: 12px !important; font-weight: 600 !important; line-height: 1.3 !important;">
                                                <i class="fas fa-check me-1"></i>Confirm
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($is_video && in_array(strtolower($status), ['confirmed', 'success', 'scheduled', 'in progress', 'in-queue'], true)): ?>
                                            <button type="button" class="btn btn-primary btn-sm rounded-pill text-white va-start-appt-btn shadow-none"
                                                data-uid="<?php echo esc_attr($uid); ?>"
                                                data-name="<?php echo esc_attr($fname); ?>"
                                                title="<?php echo strtolower($status) === 'in progress' ? 'Consultation is already in progress' : 'Start Consultation'; ?>"
                                                <?php echo strtolower($status) === 'in progress' ? 'disabled' : ''; ?>
                                                style="padding: 4px 10px !important; font-size: 12px !important; font-weight: 600 !important; line-height: 1.3 !important;">
                                                <i class="fas fa-play me-1"></i><?php echo strtolower($status) === 'in progress' ? 'In Progress' : 'Start'; ?>
                                            </button>
                                            <a href="<?php echo esc_url($join_url); ?>" target="_blank" rel="noopener noreferrer"
                                                class="btn btn-outline-success btn-sm rounded-pill va-join-zoom-btn shadow-none"
                                                data-uid="<?php echo esc_attr($uid); ?>"
                                                title="Open the saved Zoom/telehealth meeting link"
                                                style="padding: 4px 10px !important; font-size: 12px !important; font-weight: 600 !important; line-height: 1.3 !important;">
                                                <i class="fas fa-video me-1"></i>Join Zoom
                                            </a>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill va-resched-appt-btn shadow-none"
                                            data-uid="<?php echo $uid; ?>" 
                                            data-name="<?php echo esc_attr($fname); ?>" 
                                            data-date="<?php echo esc_attr($date_raw); ?>" 
                                            data-time="<?php echo esc_attr($time); ?>"
                                            title="Reschedule Appointment"
                                            style="padding: 4px 10px !important; font-size: 12px !important; font-weight: 600 !important; line-height: 1.3 !important;">
                                            <i class="fas fa-calendar-alt me-1"></i>Reschedule
                                        </button>
                                        <?php if (strtolower($status) !== 'cancelled'): ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill va-cancel-appt-btn shadow-none"
                                                data-uid="<?php echo $uid; ?>" 
                                                data-name="<?php echo esc_attr($fname); ?>"
                                                title="Cancel Appointment"
                                                style="padding: 4px 10px !important; font-size: 12px !important; font-weight: 600 !important; line-height: 1.3 !important;">
                                                <i class="fas fa-ban me-1"></i>Cancel
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="va-empty-row">
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fas fa-calendar-xmark fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                <span class="fw-semibold">No appointments found.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination & Showing Info Footer -->
        <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-top bg-white">
            <div id="va-showing-info" class="text-secondary small fw-medium"></div>
            <div id="va-pagination" class="d-flex align-items-center gap-1"></div>
        </div>

    </div>
</section>

<!-- ========================================================================= -->
<!-- 1. VIEW APPOINTMENT DETAILS MODAL (100% Native Bootstrap 5 Minimalist Standard - Rule 3.F) -->
<!-- ========================================================================= -->
<div class="modal fade" id="diorAppointmentDetailsModal" tabindex="-1" aria-labelledby="diorAppointmentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fas fa-calendar-check"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="diorAppointmentDetailsModalLabel">Appointment Details</h5>
                        <div class="text-secondary small">UID: <span id="dtl-appt-uid" class="fw-bold text-primary font-monospace">—</span></div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span id="dtl-status-badge" class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1.5 small fw-semibold">Success</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!-- Modal Body (Clean Simple Layout - Rule 3.F) -->
            <div class="modal-body p-4 bg-white">
                
                <!-- Telehealth Video Alert (Clean, subtle banner) -->
                <div id="dtl-video-banner" class="alert alert-info border border-info-subtle rounded-3 p-3 mb-4 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-video text-info fs-5"></i>
                        <div>
                            <div class="fw-bold text-dark">Live Video Consultation Room</div>
                            <div class="text-secondary small">Telehealth consultation room is active for this visit.</div>
                        </div>
                    </div>
                    <a id="dtl-join-video-btn" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm rounded-pill px-3 py-1.5 fw-semibold shadow-sm">
                        <i class="fas fa-video me-1"></i>Join Video Call
                    </a>
                </div>

                <!-- Patient & Attending Doctor Info (Clean 2-Column Minimalist Layout) -->
                <div class="row g-4 pb-3 border-bottom mb-3">
                    <div class="col-md-6 col-12">
                        <div class="small text-uppercase text-secondary fw-semibold mb-1" style="font-size: 11px; letter-spacing: 0.5px;">
                            <i class="fas fa-hospital-user text-primary me-1"></i> Patient Information
                        </div>
                        <div class="fw-bold text-dark fs-6" id="dtl-patient-name">—</div>
                        <div class="text-secondary small mb-1" id="dtl-patient-uid">ID: —</div>
                        <div class="d-flex flex-wrap gap-2 text-secondary small">
                            <a href="#" id="dtl-patient-phone-link" class="text-decoration-none text-secondary">
                                <i class="fas fa-phone text-success me-1"></i><span id="dtl-patient-phone">—</span>
                            </a>
                            <span class="text-muted">&bull;</span>
                            <a href="#" id="dtl-patient-email-link" class="text-decoration-none text-secondary">
                                <i class="fas fa-envelope text-primary me-1"></i><span id="dtl-patient-email">—</span>
                            </a>
                        </div>
                    </div>

                    <div class="col-md-6 col-12">
                        <div class="small text-uppercase text-secondary fw-semibold mb-1" style="font-size: 11px; letter-spacing: 0.5px;">
                            <i class="fas fa-user-doctor text-primary me-1"></i> Attending Physician
                        </div>
                        <div class="fw-bold text-dark fs-6" id="dtl-doctor-name">—</div>
                        <div class="text-primary small fw-semibold" id="dtl-doctor-spec">—</div>
                        <div class="text-secondary small" id="dtl-doctor-dept">—</div>
                    </div>
                </div>

                <!-- Consultation & Scheduling Details (Clean 4-col Definition Grid) -->
                <div class="small text-uppercase text-secondary fw-semibold mb-2" style="font-size: 11px; letter-spacing: 0.5px;">
                    <i class="fas fa-notes-medical text-primary me-1"></i> Consultation &amp; Scheduling
                </div>
                <div class="row g-3 pb-3 border-bottom mb-3">
                    <div class="col-sm-3 col-6">
                        <div class="small text-uppercase text-secondary fw-semibold" style="font-size: 11px;">Appointment Date</div>
                        <div class="fw-bold text-dark mt-1" id="dtl-date">—</div>
                    </div>
                    <div class="col-sm-3 col-6">
                        <div class="small text-uppercase text-secondary fw-semibold" style="font-size: 11px;">Time Slot</div>
                        <div class="fw-bold text-dark mt-1" id="dtl-time">—</div>
                    </div>
                    <div class="col-sm-3 col-6">
                        <div class="small text-uppercase text-secondary fw-semibold" style="font-size: 11px;">Visit Mode</div>
                        <div class="fw-bold text-dark mt-1" id="dtl-visit-type">—</div>
                    </div>
                    <div class="col-sm-3 col-6">
                        <div class="small text-uppercase text-secondary fw-semibold" style="font-size: 11px;">Payment Status</div>
                        <div class="mt-1" id="dtl-payment-wrap">
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold" id="dtl-payment-status">Paid</span>
                        </div>
                    </div>
                </div>

                <!-- Reason & Clinical Notes (Clean Minimalist Text) -->
                <div class="row g-3">
                    <div class="col-12">
                        <div class="small text-uppercase text-secondary fw-semibold" style="font-size: 11px;">Reason / Chief Complaint</div>
                        <div class="text-dark fw-semibold mt-1" id="dtl-condition">—</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-uppercase text-secondary fw-semibold" style="font-size: 11px;">Clinical Notes &amp; Intake Remarks</div>
                        <div class="text-secondary small mt-1" id="dtl-notes">—</div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer (Clean Standard Single Close Button - Rule 3.F) -->
            <div class="modal-footer border-top py-3 px-4 bg-white d-flex align-items-center justify-content-end">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-4 py-2 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1.5 RESCHEDULE APPOINTMENT MODAL (100% Native Bootstrap 5 Minimalist Standard) -->
<!-- ========================================================================= -->
<div class="modal fade" id="diorRescheduleApptModal" tabindex="-1" aria-labelledby="diorRescheduleApptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="diorRescheduleApptModalLabel">
                    <i class="fas fa-calendar-alt text-primary me-2"></i>Reschedule Appointment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="va-reschedule-form">
                <div class="modal-body p-4 bg-white">
                    <input type="hidden" id="va-resched-uid">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="small text-uppercase text-secondary fw-semibold" style="font-size: 11px;">Patient</div>
                                    <div class="fw-bold text-dark fs-6" id="va-resched-patient-name">—</div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-uppercase text-secondary fw-semibold" style="font-size: 11px;">Current Schedule</div>
                                    <div class="badge bg-light text-secondary border rounded-pill px-2 py-1 small" id="va-resched-current-time">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small text-secondary fw-semibold mb-1" for="va-resched-date">New Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control rounded-3" id="va-resched-date" required min="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small text-secondary fw-semibold mb-1" for="va-resched-time">New Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control rounded-3" id="va-resched-time" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-secondary fw-semibold mb-1" for="va-resched-reason">Reschedule Note (Optional)</label>
                            <textarea class="form-control rounded-3" id="va-resched-reason" rows="2" placeholder="Reason for rescheduling or note for patient..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm" id="va-resched-submit-btn">
                        <i class="fas fa-check me-1"></i>Confirm Reschedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. EDIT APPOINTMENT MODAL (100% Native Bootstrap 5 Minimalist Standard) -->
<!-- ========================================================================= -->
<div class="modal fade" id="diorViewApptEditModal" tabindex="-1" aria-labelledby="diorViewApptEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="diorViewApptEditModalLabel">
                    <i class="fas fa-pen-to-square text-primary me-2"></i>Edit Appointment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <input type="hidden" id="va-edit-uid">
                <div class="row g-3">
                    <div class="col-md-6 col-12">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="va-edit-patient" class="form-label small fw-semibold text-secondary mb-0">Patient <span class="text-danger">*</span></label>
                            <button type="button" id="va-modal-jump-profile-btn" class="btn btn-link btn-sm p-0 text-decoration-none small text-primary fw-semibold">
                                <i class="fas fa-id-card me-1"></i>View Profile
                            </button>
                        </div>
                        <select id="va-edit-patient" class="form-select rounded-3">
                            <option value="">Select Patient...</option>
                            <?php foreach ((array) $edit_patients as $ep): ?>
                                <option value="<?php echo (int) $ep['user_id']; ?>">
                                    <?php echo esc_html(trim(($ep['first_name'] ?? '') . ' ' . ($ep['last_name'] ?? '')) . ' · ' . ($ep['patient_uid'] ?? 'ID: ' . $ep['user_id'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 col-12">
                        <label for="va-edit-doctor" class="form-label small fw-semibold text-secondary mb-1">Attending Doctor <span class="text-danger">*</span></label>
                        <select id="va-edit-doctor" class="form-select rounded-3">
                            <?php foreach ($doctor_list as $d_id => $doc_item): ?>
                                <option value="<?php echo (int) $d_id; ?>">
                                    <?php echo esc_html($doc_item['name'] . ' · ' . $doc_item['specialty']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 col-12">
                        <label for="va-edit-status" class="form-label small fw-semibold text-secondary mb-1">Appointment Status <span class="text-danger">*</span></label>
                        <select id="va-edit-status" class="form-select rounded-3">
                            <option value="Success">Success</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="No Show">No Show</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-12">
                        <label for="va-edit-visittype" class="form-label small fw-semibold text-secondary mb-1">Visit Type</label>
                        <select id="va-edit-visittype" class="form-select rounded-3">
                            <option value="Video Visit (HD)">Video Visit (HD)</option>
                            <option value="In-Clinic">In-Clinic</option>
                            <option value="Phone Consultation">Phone Consultation</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-12">
                        <label for="va-edit-date" class="form-label small fw-semibold text-secondary mb-1">Appointment Date <span class="text-danger">*</span></label>
                        <input type="date" id="va-edit-date" class="form-control rounded-3">
                    </div>
                    <div class="col-md-6 col-12">
                        <label for="va-edit-time" class="form-label small fw-semibold text-secondary mb-1">Appointment Time <span class="text-danger">*</span></label>
                        <input type="time" id="va-edit-time" class="form-control rounded-3">
                    </div>
                    <div class="col-12">
                        <label for="va-edit-condition" class="form-label small fw-semibold text-secondary mb-1">Condition / Purpose</label>
                        <input type="text" id="va-edit-condition" class="form-control rounded-3" placeholder="e.g. General Consultation">
                    </div>
                    <div class="col-12">
                        <label for="va-edit-notes" class="form-label small fw-semibold text-secondary mb-1">Clinical Notes</label>
                        <textarea id="va-edit-notes" class="form-control rounded-3" rows="3" placeholder="Enter notes or consultation summary..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-link text-primary p-0 fw-semibold text-decoration-none small" id="va-full-edit-link" onclick="var uid = document.getElementById('va-edit-uid').value; var m = bootstrap.Modal.getInstance(document.getElementById('diorViewApptEditModal')); if(m) m.hide(); if(typeof window.diorOpenFullAppointmentEdit === 'function') { window.diorOpenFullAppointmentEdit(uid); }">
                    <i class="fas fa-external-link-alt me-1"></i>Open Full Edit Page
                </button>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm" id="va-save-appt-btn">
                        <i class="fas fa-check me-1"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
        var nonce = '<?php echo esc_js($nonce); ?>';
        var todayStr = '<?php echo esc_js($today_str); ?>';
        var currentPage = 1;
        var perPage = 10;
        var allRows = [];
        var activeViewingRow = null;

        var statusBadgeMap = {
            'success': 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold',
            'confirmed': 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold',
            'in progress': 'badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 small fw-semibold',
            'completed': 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold',
            'cancelled': 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 small fw-semibold',
            'no show': 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 small fw-semibold'
        };

        function initViewAppt() {
            allRows = Array.from(document.querySelectorAll('#va-appointments-tbody tr:not(#va-empty-row)'));
            setupSearch();
            setupFilters();
            setupSelectAll();
            setupRowCheckboxes();
            setupBulkActions();
            setupAppointmentActionDelegation();

            setupRescheduleForm();
            setupEditBtns();
            setupDeleteBtns();
            setupViewPatientBtns();
            setupRefreshBtn();
            setupExportCsvBtn();
            updateKpiCounters();
            renderPage(1);

            // Check if URL specifies an appt_uid to auto-open details
            checkDeepLinkAppt();
        }

        function checkDeepLinkAppt() {
            try {
                var params = new URLSearchParams(window.location.search);
                var uid = params.get('appt_uid');
                if (!uid && window.location.hash) {
                    var hashQuery = window.location.hash.split('?')[1] || window.location.hash.substring(1);
                    var hashParams = new URLSearchParams(hashQuery);
                    uid = hashParams.get('appt_uid');
                }
                if (uid) {
                    setTimeout(function () {
                        openAppointmentDetailsModal(uid);
                    }, 200);
                }
            } catch (e) {}
        }

        function updateKpiCounters() {
            var total = allRows.length;
            var upcoming = 0;
            var completed = 0;
            var pending = 0;

            allRows.forEach(function (r) {
                var st = (r.dataset.status || '').toLowerCase().trim();
                var dt = r.dataset.date || '';
                if (st === 'completed') {
                    completed++;
                } else if (st === 'pending' || st === 'in progress') {
                    pending++;
                }
                if (st !== 'cancelled' && st !== 'completed' && dt >= todayStr) {
                    upcoming++;
                }
            });

            var elTotal = document.getElementById('va-kpi-total');
            var elUpcoming = document.getElementById('va-kpi-upcoming');
            var elCompleted = document.getElementById('va-kpi-completed');
            var elPending = document.getElementById('va-kpi-pending');
            var elBadge = document.getElementById('va-total-count-badge');

            if (elTotal) elTotal.textContent = total;
            if (elUpcoming) elUpcoming.textContent = upcoming;
            if (elCompleted) elCompleted.textContent = completed;
            if (elPending) elPending.textContent = pending;
            if (elBadge) elBadge.textContent = total + ' Records';
        }

        function setupRefreshBtn() {
            var btn = document.getElementById('va-refresh-btn');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var search = document.getElementById('va-appt-search');
                var doctorFilter = document.getElementById('va-doctor-filter');
                var status = document.getElementById('va-status-filter');
                var dateFilter = document.getElementById('va-date-filter');
                if (search) search.value = '';
                if (doctorFilter) doctorFilter.value = '';
                if (status) status.value = '';
                if (dateFilter) dateFilter.value = '';
                
                var selectAll = document.getElementById('va-select-all');
                if (selectAll) selectAll.checked = false;
                document.querySelectorAll('#va-appointments-tbody .va-row-check').forEach(function (cb) {
                    cb.checked = false;
                });
                updateBulkBar();

                renderPage(1);
            });
        }

        function getVisibleRows() {
            var search = (document.getElementById('va-appt-search').value || '').toLowerCase().trim();
            var docFilter = (document.getElementById('va-doctor-filter') ? document.getElementById('va-doctor-filter').value : '').toLowerCase().trim();
            var status = (document.getElementById('va-status-filter').value || '').toLowerCase().trim();
            var dateFilter = (document.getElementById('va-date-filter').value || '').toLowerCase().trim();

            return allRows.filter(function (r) {
                var text = r.textContent.toLowerCase();
                var rowStatus = (r.dataset.status || '').toLowerCase().trim();
                var rowDoc = (r.dataset.doctorName || '').toLowerCase().trim();
                var rowDate = r.dataset.date || '';

                var matchSearch = !search || text.indexOf(search) !== -1;
                var matchDoctor = !docFilter || rowDoc.indexOf(docFilter) !== -1;
                var matchStatus = !status || rowStatus === status;
                var matchDate = true;

                if (dateFilter === 'today') {
                    matchDate = (rowDate === todayStr);
                } else if (dateFilter === 'upcoming') {
                    matchDate = (rowDate >= todayStr && rowStatus !== 'cancelled' && rowStatus !== 'completed');
                } else if (dateFilter === 'past') {
                    matchDate = (rowDate < todayStr);
                }

                return matchSearch && matchDoctor && matchStatus && matchDate;
            });
        }

        function renderPage(page) {
            currentPage = page;
            var visible = getVisibleRows();
            var total = visible.length;
            var start = (page - 1) * perPage;

            allRows.forEach(function (r) { r.style.display = 'none'; });
            visible.slice(start, start + perPage).forEach(function (r) { r.style.display = ''; });

            var infoEl = document.getElementById('va-showing-info');
            if (infoEl) {
                if (total === 0) {
                    infoEl.textContent = 'No matching appointments found';
                } else {
                    infoEl.textContent = 'Showing ' + (start + 1) + '–' + Math.min(start + perPage, total) + ' of ' + total + ' appointment(s)';
                }
            }

            renderPagination(total);
        }

        function renderPagination(total) {
            var pages = Math.ceil(total / perPage);
            var container = document.getElementById('va-pagination');
            if (!container) return;
            container.innerHTML = '';
            if (pages <= 1) return;

            // Prev Button
            var prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'btn btn-sm btn-outline-secondary rounded-3 px-2.5 shadow-none' + (currentPage === 1 ? ' disabled' : '');
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.addEventListener('click', function () {
                if (currentPage > 1) renderPage(currentPage - 1);
            });
            container.appendChild(prevBtn);

            // Page numbers
            for (var i = 1; i <= pages; i++) {
                (function (p) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = p;
                    btn.className = 'btn btn-sm rounded-3 px-3 shadow-none fw-semibold ' + (p === currentPage ? 'btn-primary' : 'btn-outline-secondary');
                    btn.addEventListener('click', function () { renderPage(p); });
                    container.appendChild(btn);
                })(i);
            }

            // Next Button
            var nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'btn btn-sm btn-outline-secondary rounded-3 px-2.5 shadow-none' + (currentPage === pages ? ' disabled' : '');
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.addEventListener('click', function () {
                if (currentPage < pages) renderPage(currentPage + 1);
            });
            container.appendChild(nextBtn);
        }

        function applyFilters() {
            renderPage(1);
        }

        function setupSearch() {
            var input = document.getElementById('va-appt-search');
            if (input) {
                input.addEventListener('input', applyFilters);
                input.addEventListener('keyup', applyFilters);
            }
        }

        function setupFilters() {
            var df = document.getElementById('va-doctor-filter');
            var sf = document.getElementById('va-status-filter');
            var dateF = document.getElementById('va-date-filter');
            if (df) df.addEventListener('change', applyFilters);
            if (sf) sf.addEventListener('change', applyFilters);
            if (dateF) dateF.addEventListener('change', applyFilters);
        }

        function updateBulkBar() {
            var checked = document.querySelectorAll('#va-appointments-tbody .va-row-check:checked');
            var bulkBar = document.getElementById('va-bulk-bar');
            var countEl = document.getElementById('va-selected-count');
            if (!bulkBar) return;

            if (checked.length > 0) {
                bulkBar.classList.remove('d-none');
                bulkBar.classList.add('d-flex');
                if (countEl) countEl.textContent = checked.length + ' Selected';
            } else {
                bulkBar.classList.remove('d-flex');
                bulkBar.classList.add('d-none');
            }
        }

        function setupSelectAll() {
            var selectAll = document.getElementById('va-select-all');
            if (!selectAll) return;
            selectAll.addEventListener('change', function () {
                var checked = this.checked;
                var visible = getVisibleRows();
                visible.forEach(function (r) {
                    var cb = r.querySelector('.va-row-check');
                    if (cb) cb.checked = checked;
                });
                updateBulkBar();
            });
        }

        function setupRowCheckboxes() {
            var tbody = document.getElementById('va-appointments-tbody');
            if (!tbody) return;
            tbody.addEventListener('change', function (e) {
                if (e.target && e.target.classList.contains('va-row-check')) {
                    updateBulkBar();
                }
            });
        }

        function setupBulkActions() {
            // Bulk Delete
            var delBtn = document.getElementById('va-bulk-delete-btn');
            if (delBtn) {
                delBtn.addEventListener('click', function () {
                    var checkedRows = Array.from(document.querySelectorAll('#va-appointments-tbody .va-row-check:checked')).map(function (cb) {
                        return cb.closest('tr');
                    }).filter(Boolean);

                    if (checkedRows.length === 0) return;

                    Swal.fire({
                        title: 'Delete ' + checkedRows.length + ' Appointment(s)?',
                        text: 'Are you sure you want to delete these appointments? This cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#EF4444',
                        cancelButtonColor: '#64748B',
                        confirmButtonText: 'Yes, delete all',
                        cancelButtonText: 'Cancel'
                    }).then(function (result) {
                        if (!result.isConfirmed) return;

                        var promises = checkedRows.map(function (row) {
                            var uid = row.dataset.apptUid;
                            var data = new FormData();
                            data.append('action', 'dior_doc_appointment_delete');
                            data.append('nonce', nonce);
                            data.append('appt_uid', uid);
                            return fetch(ajaxUrl, { method: 'POST', body: data }).then(function (r) { return r.json(); });
                        });

                        Promise.all(promises).then(function () {
                            checkedRows.forEach(function (row) {
                                allRows = allRows.filter(function (r) { return r !== row; });
                                row.remove();
                            });
                            updateBulkBar();
                            updateKpiCounters();
                            renderPage(currentPage);
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted',
                                text: checkedRows.length + ' appointment(s) removed.',
                                toast: true,
                                position: 'top-end',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }).catch(function () {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to delete selected appointments.' });
                        });
                    });
                });
            }

            // Bulk Mark Complete
            var compBtn = document.getElementById('va-bulk-complete-btn');
            if (compBtn) {
                compBtn.addEventListener('click', function () {
                    var checkedRows = Array.from(document.querySelectorAll('#va-appointments-tbody .va-row-check:checked')).map(function (cb) {
                        return cb.closest('tr');
                    }).filter(Boolean);

                    if (checkedRows.length === 0) return;

                    var promises = checkedRows.map(function (row) {
                        var uid = row.dataset.apptUid;
                        var pid = row.dataset.patientId;
                        var date = row.dataset.date;
                        var data = new FormData();
                        data.append('action', 'dior_doc_appointment_update');
                        data.append('nonce', nonce);
                        data.append('appt_uid', uid);
                        data.append('patient_id', pid);
                        data.append('status', 'Completed');
                        data.append('date', date);
                        return fetch(ajaxUrl, { method: 'POST', body: data }).then(function (r) { return r.json(); });
                    });

                    Promise.all(promises).then(function () {
                        checkedRows.forEach(function (row) {
                            row.dataset.status = 'Completed';
                            var badge = row.querySelector('.va-badge');
                            if (badge) {
                                badge.className = 'va-badge ' + (statusBadgeMap['completed'] || 'badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 small fw-semibold');
                                badge.textContent = 'Completed';
                            }
                            var cb = row.querySelector('.va-row-check');
                            if (cb) cb.checked = false;
                        });
                        updateBulkBar();
                        updateKpiCounters();
                        renderPage(currentPage);
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated',
                            text: checkedRows.length + ' appointment(s) marked as Completed.',
                            toast: true,
                            position: 'top-end',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }).catch(function () {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to update selected appointments.' });
                    });
                });
            }
        }

        // =========================================================================
        // VIEW APPOINTMENT DETAILS MODAL LOGIC
        // =========================================================================
        function openAppointmentDetailsModal(uid) {
            var row = document.querySelector('tr[data-appt-uid="' + uid + '"]');
            if (!row) return;
            activeViewingRow = row;

            var ds = row.dataset;
            var apptUid = ds.apptUid || uid;
            var status = ds.status || 'Success';
            var patientName = ds.patientName || (row.querySelector('.va-user-name') ? row.querySelector('.va-user-name').textContent.trim() : 'Patient');
            var patientUid = ds.patientUid || 'ID: —';
            var patientPhone = ds.patientPhone || ds.phone || '—';
            var patientEmail = ds.patientEmail || '—';
            var patientAvatar = ds.patientAvatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(patientName) + '&background=00346c&color=ffffff&bold=true';

            var doctorName = ds.doctorName || 'Dr. Marcus Sterling';
            var doctorSpec = ds.doctorSpecialty || 'General Practice';
            var doctorDept = ds.doctorDepartment || 'Outpatient Care';
            var doctorAvatar = ds.doctorAvatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(doctorName) + '&background=00346c&color=ffffff&bold=true';

            var dateFmt = ds.dateFmt || ds.date || '—';
            var time = ds.time || '—';
            var condition = ds.condition || 'General Consultation';
            var visitType = ds.visitType || 'Video Visit (HD)';
            var joinUrl = ds.joinUrl || 'https://zoom.us/join';
            var paymentStatus = ds.paymentStatus || 'Paid';
            var notes = ds.notes || 'No pre-consultation notes provided. Standard intake questionnaire verified.';

            // Populate UID & Status
            var elUid = document.getElementById('dtl-appt-uid');
            if (elUid) elUid.textContent = apptUid;

            var elBadge = document.getElementById('dtl-status-badge');
            if (elBadge) {
                var badgeCls = statusBadgeMap[status.toLowerCase()] || 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1.5 small fw-semibold';
                elBadge.className = badgeCls;
                elBadge.textContent = status;
            }

            // Populate Patient
            var elPName = document.getElementById('dtl-patient-name');
            var elPUid = document.getElementById('dtl-patient-uid');
            var elPPhone = document.getElementById('dtl-patient-phone');
            var elPPhoneLink = document.getElementById('dtl-patient-phone-link');
            var elPEmail = document.getElementById('dtl-patient-email');
            var elPEmailLink = document.getElementById('dtl-patient-email-link');
            var elPAvatar = document.getElementById('dtl-patient-avatar');

            if (elPName) elPName.textContent = patientName;
            if (elPUid) elPUid.textContent = patientUid;
            if (elPPhone) elPPhone.textContent = patientPhone;
            if (elPPhoneLink) elPPhoneLink.href = (patientPhone !== '—' ? 'tel:' + patientPhone.replace(/[^0-9+]/g, '') : '#');
            if (elPEmail) elPEmail.textContent = patientEmail;
            if (elPEmailLink) elPEmailLink.href = (patientEmail !== '—' ? 'mailto:' + patientEmail : '#');
            if (elPAvatar) {
                elPAvatar.src = patientAvatar;
                elPAvatar.onerror = function () {
                    this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(patientName) + '&background=00346c&color=ffffff&bold=true';
                };
            }

            // Populate Doctor
            var elDName = document.getElementById('dtl-doctor-name');
            var elDSpec = document.getElementById('dtl-doctor-spec');
            var elDDept = document.getElementById('dtl-doctor-dept');
            var elDAvatar = document.getElementById('dtl-doctor-avatar');

            if (elDName) elDName.textContent = doctorName;
            if (elDSpec) elDSpec.textContent = doctorSpec;
            if (elDDept) elDDept.textContent = doctorDept;
            if (elDAvatar) {
                elDAvatar.src = doctorAvatar;
                elDAvatar.onerror = function () {
                    this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(doctorName) + '&background=00346c&color=ffffff&bold=true';
                };
            }

            // Populate Consultation & Scheduling Info
            var elDate = document.getElementById('dtl-date');
            var elTime = document.getElementById('dtl-time');
            var elVType = document.getElementById('dtl-visit-type');
            var elPayStatus = document.getElementById('dtl-payment-status');
            var elCond = document.getElementById('dtl-condition');
            var elNotes = document.getElementById('dtl-notes');

            if (elDate) elDate.textContent = dateFmt;
            if (elTime) elTime.textContent = time;
            if (elVType) {
                var isVid = (visitType.toLowerCase().indexOf('video') !== -1 || visitType.toLowerCase().indexOf('telehealth') !== -1);
                elVType.innerHTML = isVid 
                    ? '<span class="text-info"><i class="fas fa-video me-1"></i>' + visitType + '</span>'
                    : '<span class="text-secondary"><i class="fas fa-hospital me-1"></i>' + visitType + '</span>';
            }
            if (elPayStatus) {
                elPayStatus.textContent = paymentStatus;
                elPayStatus.className = 'badge rounded-pill px-2.5 py-1 small fw-semibold ' + 
                    (paymentStatus.toLowerCase() === 'paid' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle');
            }
            if (elCond) elCond.textContent = condition;
            if (elNotes) elNotes.textContent = notes || 'No special intake notes recorded.';

            // Telehealth Live Video Consultation Banner
            var isVideoVisit = (visitType.toLowerCase().indexOf('video') !== -1 || visitType.toLowerCase().indexOf('telehealth') !== -1);
            var videoBanner = document.getElementById('dtl-video-banner');
            var videoBtn = document.getElementById('dtl-join-video-btn');

            if (videoBanner) {
                if (isVideoVisit) {
                    videoBanner.classList.remove('d-none');
                    videoBanner.classList.add('d-flex');
                    if (videoBtn) videoBtn.href = joinUrl;
                } else {
                    videoBanner.classList.remove('d-flex');
                    videoBanner.classList.add('d-none');
                }
            }

            // Wire action buttons inside details modal
            var editBtn = document.getElementById('dtl-action-edit-btn');
            if (editBtn) {
                editBtn.onclick = function () {
                    var dtlModal = bootstrap.Modal.getInstance(document.getElementById('diorAppointmentDetailsModal'));
                    if (dtlModal) dtlModal.hide();
                    var apptUid = row.dataset.apptUid || '';
                    if (typeof window.diorOpenFullAppointmentEdit === 'function' && apptUid) {
                        window.diorOpenFullAppointmentEdit(apptUid);
                    } else {
                        setTimeout(function () {
                            populateAndOpenEditModal(row);
                        }, 200);
                    }
                };
            }

            var dtlConfirmBtn = document.getElementById('dtl-action-confirm-btn');
            if (dtlConfirmBtn) {
                if (status.toLowerCase() === 'confirmed') {
                    dtlConfirmBtn.disabled = true;
                    dtlConfirmBtn.classList.add('disabled');
                    dtlConfirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Confirmed';
                } else {
                    dtlConfirmBtn.disabled = false;
                    dtlConfirmBtn.classList.remove('disabled');
                    dtlConfirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Confirm';
                    dtlConfirmBtn.onclick = function () {
                        confirmAppointment(apptUid, patientName);
                    };
                }
            }

            var dtlReschedBtn = document.getElementById('dtl-action-resched-btn');
            if (dtlReschedBtn) {
                dtlReschedBtn.onclick = function () {
                    openRescheduleModal(apptUid, patientName, ds.date || '', ds.time || '');
                };
            }

            var dtlCancelBtn = document.getElementById('dtl-action-cancel-btn');
            if (dtlCancelBtn) {
                if (status.toLowerCase() === 'cancelled') {
                    dtlCancelBtn.disabled = true;
                    dtlCancelBtn.classList.add('disabled');
                    dtlCancelBtn.innerHTML = '<i class="fas fa-ban me-1"></i>Cancelled';
                } else {
                    dtlCancelBtn.disabled = false;
                    dtlCancelBtn.classList.remove('disabled');
                    dtlCancelBtn.innerHTML = '<i class="fas fa-ban me-1"></i>Cancel';
                    dtlCancelBtn.onclick = function () {
                        cancelAppointment(apptUid, patientName);
                    };
                }
            }

            var compBtn = document.getElementById('dtl-action-complete-btn');
            if (compBtn) {
                if (status.toLowerCase() === 'completed') {
                    compBtn.disabled = true;
                    compBtn.innerHTML = '<i class="fas fa-check me-1"></i>Already Completed';
                    compBtn.className = 'btn btn-outline-secondary rounded-3 px-3 py-2 fw-semibold disabled';
                } else {
                    compBtn.disabled = false;
                    compBtn.innerHTML = '<i class="fas fa-circle-check me-1"></i>Mark Completed';
                    compBtn.className = 'btn btn-outline-success rounded-3 px-3 py-2 fw-semibold';
                    compBtn.onclick = function () {
                        var btn = this;
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Updating...';

                        var data = new FormData();
                        data.append('action', 'dior_doc_appointment_update');
                        data.append('nonce', nonce);
                        data.append('appt_uid', apptUid);
                        data.append('patient_id', ds.patientId || '');
                        data.append('status', 'Completed');
                        data.append('date', ds.date || '');

                        fetch(ajaxUrl, { method: 'POST', body: data })
                            .then(function (r) { return r.json(); })
                            .then(function (res) {
                                if (res.success) {
                                    row.dataset.status = 'Completed';
                                    var badge = row.querySelector('.va-badge');
                                    if (badge) {
                                        badge.className = 'va-badge ' + (statusBadgeMap['completed'] || 'badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 small fw-semibold');
                                        badge.textContent = 'Completed';
                                    }
                                    if (elBadge) {
                                        elBadge.className = statusBadgeMap['completed'] || 'badge bg-primary-subtle text-primary rounded-pill px-3 py-1.5 small fw-semibold';
                                        elBadge.textContent = 'Completed';
                                    }
                                    btn.innerHTML = '<i class="fas fa-check me-1"></i>Already Completed';
                                    btn.className = 'btn btn-outline-secondary rounded-3 px-3 py-2 fw-semibold disabled';
                                    updateKpiCounters();
                                    renderPage(currentPage);

                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Completed',
                                        text: 'Appointment marked as Completed.',
                                        toast: true,
                                        position: 'top-end',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to complete appointment.' });
                                    btn.disabled = false;
                                    btn.innerHTML = '<i class="fas fa-circle-check me-1"></i>Mark Completed';
                                }
                            })
                            .catch(function () {
                                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' });
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-circle-check me-1"></i>Mark Completed';
                            });
                    };
                }
            }

            // Show Details Modal
            var modalEl = document.getElementById('diorAppointmentDetailsModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        // Global hook to open appointment details from anywhere (Calendar, Overview, etc.)
        window.diorOpenAppointmentDetails = function (uid) {
            openAppointmentDetailsModal(uid);
        };

        function timeTo12h(timeStr) {
            if (!timeStr) return '';
            if (/am|pm/i.test(timeStr)) return timeStr;
            var parts = timeStr.split(':');
            var h = parseInt(parts[0], 10);
            var m = parts[1] ? parts[1].substring(0, 2) : '00';
            var ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            h = h ? h : 12;
            var hStr = h < 10 ? '0' + h : '' + h;
            return hStr + ':' + m + ' ' + ampm;
        }

        function timeTo24h(timeStr) {
            if (!timeStr) return '';
            var match = timeStr.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)?$/i);
            if (!match) return timeStr;
            var h = parseInt(match[1], 10);
            var m = match[2];
            var ampm = (match[3] || '').toUpperCase();
            if (ampm === 'PM' && h < 12) h += 12;
            if (ampm === 'AM' && h === 12) h = 0;
            return (h < 10 ? '0' + h : '' + h) + ':' + m;
        }

        function confirmAppointment(uid, patientName, onComplete) {
            if (!uid) return;
            var row = document.querySelector('tr[data-appt-uid="' + uid + '"]');
            var data = new FormData();
            data.append('action', 'dior_doctor_update_appt_status');
            data.append('nonce', nonce);
            data.append('appt_uid', uid);
            data.append('status', 'Confirmed');

            fetch(ajaxUrl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        if (row) {
                            row.dataset.status = 'Confirmed';
                            var badge = row.querySelector('.va-badge');
                            if (badge) {
                                badge.className = 'va-badge ' + (statusBadgeMap['confirmed'] || 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold');
                                badge.textContent = 'Confirmed';
                            }
                        }
                        var dtlBadge = document.getElementById('dtl-status-badge');
                        if (dtlBadge) {
                            dtlBadge.className = statusBadgeMap['confirmed'] || 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1.5 small fw-semibold';
                            dtlBadge.textContent = 'Confirmed';
                        }
                        var dtlConfirmBtn = document.getElementById('dtl-action-confirm-btn');
                        if (dtlConfirmBtn) {
                            dtlConfirmBtn.disabled = true;
                            dtlConfirmBtn.classList.add('disabled');
                            dtlConfirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Confirmed';
                        }
                        updateKpiCounters();
                        renderPage(currentPage);
                        Swal.fire({
                            icon: 'success',
                            title: 'Confirmed',
                            text: 'Appointment for ' + (patientName || 'patient') + ' is now confirmed.',
                            toast: true,
                            position: 'top-end',
                            timer: 2500,
                            showConfirmButton: false
                        });
                        if (typeof onComplete === 'function') onComplete(true);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.data && res.data.message ? res.data.message : 'Failed to confirm appointment.' });
                        if (typeof onComplete === 'function') onComplete(false);
                    }
                })
                .catch(function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Network connection error.' });
                    if (typeof onComplete === 'function') onComplete(false);
                });
        }

        function cancelAppointment(uid, patientName, onComplete) {
            if (!uid) return;
            var row = document.querySelector('tr[data-appt-uid="' + uid + '"]');

            Swal.fire({
                title: 'Cancel Appointment?',
                text: 'Are you sure you want to cancel the appointment for ' + (patientName || 'this patient') + '?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Yes, cancel appointment',
                cancelButtonText: 'Keep Appointment'
            }).then(function (result) {
                if (!result.isConfirmed) {
                    if (typeof onComplete === 'function') onComplete(false);
                    return;
                }

                var data = new FormData();
                data.append('action', 'dior_doc_appointment_update');
                data.append('nonce', nonce);
                data.append('appt_uid', uid);
                data.append('status', 'Cancelled');

                fetch(ajaxUrl, { method: 'POST', body: data })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) {
                            if (row) {
                                row.dataset.status = 'Cancelled';
                                var badge = row.querySelector('.va-badge');
                                if (badge) {
                                    badge.className = 'va-badge ' + (statusBadgeMap['cancelled'] || 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 small fw-semibold');
                                    badge.textContent = 'Cancelled';
                                }
                            }
                            var dtlBadge = document.getElementById('dtl-status-badge');
                            if (dtlBadge) {
                                dtlBadge.className = statusBadgeMap['cancelled'] || 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1.5 small fw-semibold';
                                dtlBadge.textContent = 'Cancelled';
                            }
                            var dtlCancelBtn = document.getElementById('dtl-action-cancel-btn');
                            if (dtlCancelBtn) {
                                dtlCancelBtn.disabled = true;
                                dtlCancelBtn.classList.add('disabled');
                                dtlCancelBtn.innerHTML = '<i class="fas fa-ban me-1"></i>Cancelled';
                            }
                            updateKpiCounters();
                            renderPage(currentPage);
                            Swal.fire({
                                icon: 'success',
                                title: 'Cancelled',
                                text: 'Appointment for ' + (patientName || 'patient') + ' has been cancelled.',
                                toast: true,
                                position: 'top-end',
                                timer: 2500,
                                showConfirmButton: false
                            });
                            if (typeof onComplete === 'function') onComplete(true);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.data && res.data.message ? res.data.message : 'Failed to cancel appointment.' });
                            if (typeof onComplete === 'function') onComplete(false);
                        }
                    })
                    .catch(function () {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Network connection error.' });
                        if (typeof onComplete === 'function') onComplete(false);
                    });
            });
        }

        function openRescheduleModal(uid, patientName, date, time) {
            var row = document.querySelector('tr[data-appt-uid="' + uid + '"]');
            if (row) {
                if (!patientName) patientName = row.dataset.patientName || (row.querySelector('.va-user-name') ? row.querySelector('.va-user-name').textContent.trim() : 'Patient');
                if (!date) date = row.dataset.date || '';
                if (!time) time = row.dataset.time || '';
            }

            var uidField = document.getElementById('va-resched-uid');
            var nameEl = document.getElementById('va-resched-patient-name');
            var currSchedEl = document.getElementById('va-resched-current-time');
            var dateInput = document.getElementById('va-resched-date');
            var timeInput = document.getElementById('va-resched-time');
            var noteInput = document.getElementById('va-resched-reason');

            if (uidField) uidField.value = uid;
            if (nameEl) nameEl.textContent = patientName || 'Patient';
            if (currSchedEl) currSchedEl.textContent = (date || '—') + ' · ' + (time || '—');
            if (dateInput) dateInput.value = date || '';
            if (timeInput) timeInput.value = timeTo24h(time) || '10:00';
            if (noteInput) noteInput.value = '';

            var dtlModalEl = document.getElementById('diorAppointmentDetailsModal');
            if (dtlModalEl && dtlModalEl.classList.contains('show')) {
                var dtlModal = bootstrap.Modal.getInstance(dtlModalEl);
                if (dtlModal) dtlModal.hide();
            }

            var reschedModalEl = document.getElementById('diorRescheduleApptModal');
            if (reschedModalEl) {
                bootstrap.Modal.getOrCreateInstance(reschedModalEl).show();
            }
        }

        function setupRescheduleForm() {
            var form = document.getElementById('va-reschedule-form');
            if (!form) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var uid = document.getElementById('va-resched-uid').value;
                var date = document.getElementById('va-resched-date').value;
                var rawTime = document.getElementById('va-resched-time').value;
                var reason = document.getElementById('va-resched-reason').value;
                var btn = document.getElementById('va-resched-submit-btn');

                if (!uid || !date || !rawTime) {
                    Swal.fire({ icon: 'warning', title: 'Missing Info', text: 'Please select both date and time.' });
                    return;
                }

                var formattedTime = timeTo12h(rawTime);
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Rescheduling...';

                var data = new FormData();
                data.append('action', 'dior_doc_appointment_update');
                data.append('nonce', nonce);
                data.append('appt_uid', uid);
                data.append('date', date);
                data.append('time', formattedTime);
                data.append('status', 'Confirmed');
                if (reason) {
                    data.append('notes', reason);
                }

                fetch(ajaxUrl, { method: 'POST', body: data })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) {
                            var row = document.querySelector('tr[data-appt-uid="' + uid + '"]');
                            if (row) {
                                row.dataset.date = date;
                                row.dataset.time = formattedTime;
                                row.dataset.status = 'Confirmed';
                                if (reason) row.dataset.notes = reason;

                                var parts = date.split('-');
                                var dateObj = new Date(parts[0], parts[1] - 1, parts[2]);
                                var displayDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                                row.dataset.dateFmt = displayDate;

                                var dateSpan = row.querySelector('.va-cell-date');
                                if (dateSpan) {
                                    dateSpan.innerHTML = '<i class="far fa-calendar text-primary me-1"></i>' + displayDate;
                                }
                                var timeSpan = row.querySelector('.va-cell-time');
                                if (timeSpan) {
                                    timeSpan.innerHTML = '<i class="far fa-clock me-1"></i>' + formattedTime;
                                }

                                var badge = row.querySelector('.va-badge');
                                if (badge) {
                                    badge.className = 'va-badge ' + (statusBadgeMap['success'] || 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold');
                                    badge.textContent = 'Success';
                                }
                            }

                            var dtlDate = document.getElementById('dtl-date');
                            if (dtlDate) dtlDate.textContent = date;
                            var dtlTime = document.getElementById('dtl-time');
                            if (dtlTime) dtlTime.textContent = formattedTime;
                            var dtlBadge = document.getElementById('dtl-status-badge');
                            if (dtlBadge) {
                                dtlBadge.className = statusBadgeMap['success'] || 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1.5 small fw-semibold';
                                dtlBadge.textContent = 'Success';
                            }

                            var modalEl = document.getElementById('diorRescheduleApptModal');
                            if (modalEl) {
                                var modalInstance = bootstrap.Modal.getInstance(modalEl);
                                if (modalInstance) modalInstance.hide();
                            }

                            updateKpiCounters();
                            renderPage(currentPage);

                            Swal.fire({
                                icon: 'success',
                                title: 'Rescheduled',
                                text: 'Appointment rescheduled to ' + date + ' at ' + formattedTime + '.',
                                toast: true,
                                position: 'top-end',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.data && res.data.message ? res.data.message : 'Failed to reschedule appointment.' });
                        }
                    })
                    .catch(function () {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Network connection error.' });
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check me-1"></i>Confirm Reschedule';
                    });
            });
        }

        function startAppointment(uid, patientName, button) {
            if (!uid) {
                Swal.fire({ icon: 'error', title: 'Missing appointment ID', text: 'Refresh the page and try again.' });
                return;
            }

            Swal.fire({
                title: 'Start consultation?',
                text: 'A Zoom meeting will be created if one is not already saved, and the appointment will be marked In Progress.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Start & Join Zoom',
                cancelButtonText: 'Not yet',
                confirmButtonColor: '#0d6efd'
            }).then(function (choice) {
                if (!choice.isConfirmed) return;

                // Open a blank tab during the click gesture so the browser does not block
                // the meeting tab after the asynchronous AJAX response.
                var meetingWindow = window.open('about:blank', '_blank');
                if (meetingWindow) {
                    meetingWindow.document.title = 'Connecting to consultation…';
                    meetingWindow.document.body.innerHTML = '<p style="font-family:Arial,sans-serif;padding:24px">Connecting to your secure consultation…</p>';
                }

                var data = new FormData();
                data.append('action', 'dior_doctor_start_consultation');
                data.append('nonce', nonce);
                data.append('appt_uid', uid);

                if (button) {
                    button.disabled = true;
                    button.dataset.originalText = button.innerHTML;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Starting...';
                }

                fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (response) {
                        return response.json().catch(function () {
                            throw new Error('The server returned an invalid response. Check the WordPress PHP error log.');
                        }).then(function (res) {
                            if (!response.ok || !res.success) {
                                throw new Error(res.data && res.data.message ? res.data.message : 'Could not create or start the Zoom consultation.');
                            }
                            return res;
                        });
                    })
                    .then(function (res) {
                        var payload = res.data || {};
                        var joinUrl = payload.join_url || '';
                        if (!joinUrl || !/^https:\/\//i.test(joinUrl)) {
                            throw new Error('The server did not return a valid Zoom meeting URL.');
                        }

                        var row = document.querySelector('#va-appointments-tbody tr[data-appt-uid="' + uid.replace(/"/g, '\\"') + '"]');
                        if (row) {
                            row.dataset.status = 'In Progress';
                            var badge = row.querySelector('.va-badge');
                            if (badge) {
                                badge.textContent = 'In Progress';
                                badge.className = 'va-badge ' + (statusBadgeMap['in progress'] || 'badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 small fw-semibold');
                            }
                        }

                        if (meetingWindow && !meetingWindow.closed) {
                            meetingWindow.location.href = joinUrl;
                        } else {
                            window.location.href = joinUrl;
                        }

                        updateKpiCounters();
                        renderPage(currentPage);
                        Swal.fire({
                            icon: 'success',
                            title: 'Consultation started',
                            text: 'Opening the Zoom meeting for ' + (patientName || 'the patient') + '.',
                            toast: true,
                            position: 'top-end',
                            timer: 2500,
                            showConfirmButton: false
                        });
                    })
                    .catch(function (error) {
                        if (meetingWindow && !meetingWindow.closed) meetingWindow.close();
                        Swal.fire({ icon: 'error', title: 'Could not start Zoom meeting', text: error.message || 'Please try again.' });
                        if (button) {
                            button.disabled = false;
                            button.innerHTML = button.dataset.originalText || '<i class="fas fa-play me-1"></i>Start';
                        }
                    });
            });
        }

        // Use one delegated handler for row actions. Rows are re-rendered by search,
        // filters and pagination, so document-level delegation keeps actions reliable.
        function setupAppointmentActionDelegation() {
            if (document.documentElement.dataset.diorAppointmentActionsBound === '1') return;
            document.documentElement.dataset.diorAppointmentActionsBound = '1';

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('#va-appointments-tbody .va-view-details-btn, #va-appointments-tbody .va-confirm-appt-btn, #va-appointments-tbody .va-cancel-appt-btn, #va-appointments-tbody .va-resched-appt-btn, #va-appointments-tbody .va-start-appt-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();

                if (btn.classList.contains('va-view-details-btn')) {
                    var viewUid = btn.dataset.uid || (btn.closest('tr') && btn.closest('tr').dataset.apptUid);
                    if (viewUid) openAppointmentDetailsModal(viewUid);
                    return;
                }

                var uid = btn.dataset.uid || '';
                var name = btn.dataset.name || 'Patient';
                if (!uid) {
                    if (window.Swal) Swal.fire({icon:'error', title:'Missing appointment ID', text:'Please refresh the page and try again.'});
                    return;
                }

                if (btn.classList.contains('va-start-appt-btn')) {
                    startAppointment(uid, name, btn);
                } else if (btn.classList.contains('va-confirm-appt-btn')) {
                    confirmAppointment(uid, name);
                } else if (btn.classList.contains('va-cancel-appt-btn')) {
                    cancelAppointment(uid, name);
                } else if (btn.classList.contains('va-resched-appt-btn')) {
                    openRescheduleModal(uid, name, btn.dataset.date || '', btn.dataset.time || '');
                }
            }, true);
        }

        function setupConfirmBtns() {
            var tbody = document.getElementById('va-appointments-tbody');
            if (!tbody) return;
            tbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.va-confirm-appt-btn');
                if (!btn) return;
                var uid = btn.dataset.uid;
                var name = btn.dataset.name;
                confirmAppointment(uid, name);
            });
        }

        function setupCancelBtns() {
            var tbody = document.getElementById('va-appointments-tbody');
            if (!tbody) return;
            tbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.va-cancel-appt-btn');
                if (!btn) return;
                var uid = btn.dataset.uid;
                var name = btn.dataset.name;
                cancelAppointment(uid, name);
            });
        }

        function setupReschedBtns() {
            var tbody = document.getElementById('va-appointments-tbody');
            if (!tbody) return;
            tbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.va-resched-appt-btn');
                if (!btn) return;
                var uid = btn.dataset.uid;
                var name = btn.dataset.name;
                var date = btn.dataset.date;
                var time = btn.dataset.time;
                openRescheduleModal(uid, name, date, time);
            });
        }

        function setupViewDetailsBtns() {
            var tbody = document.getElementById('va-appointments-tbody');
            if (!tbody) return;

            tbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.va-view-details-btn');
                if (!btn) return;
                var uid = btn.dataset.uid;
                if (!uid) {
                    var row = btn.closest('tr');
                    uid = row ? row.dataset.apptUid : '';
                }
                if (uid) {
                    openAppointmentDetailsModal(uid);
                }
            });
        }

        function setupExportCsvBtn() {
            var btn = document.getElementById('va-export-csv-btn');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var visible = getVisibleRows();
                if (visible.length === 0) {
                    Swal.fire({ icon: 'info', title: 'No Data', text: 'No appointments available to export with current filters.' });
                    return;
                }

                var csv = ['Appointment UID,Patient Name,Patient ID,Doctor Name,Specialty,Condition,Visit Type,Date,Time,Phone,Status,Payment'];
                visible.forEach(function (r) {
                    var name = (r.querySelector('.va-user-name') || {}).textContent || '';
                    var uid = r.dataset.apptUid || '';
                    var puid = r.dataset.patientUid || '';
                    var doc = r.dataset.doctorName || '';
                    var spec = r.dataset.doctorSpecialty || '';
                    var condition = r.dataset.condition || '';
                    var vtype = r.dataset.visitType || '';
                    var date = r.dataset.date || '';
                    var time = r.dataset.time || '';
                    var phone = r.dataset.phone || '';
                    var status = r.dataset.status || '';
                    var pay = r.dataset.paymentStatus || 'Paid';

                    var line = [
                        '"' + uid.replace(/"/g, '""') + '"',
                        '"' + name.trim().replace(/"/g, '""') + '"',
                        '"' + puid.replace(/"/g, '""') + '"',
                        '"' + doc.replace(/"/g, '""') + '"',
                        '"' + spec.replace(/"/g, '""') + '"',
                        '"' + condition.replace(/"/g, '""') + '"',
                        '"' + vtype.replace(/"/g, '""') + '"',
                        '"' + date.replace(/"/g, '""') + '"',
                        '"' + time.trim().replace(/"/g, '""') + '"',
                        '"' + phone.replace(/"/g, '""') + '"',
                        '"' + status.replace(/"/g, '""') + '"',
                        '"' + pay.replace(/"/g, '""') + '"'
                    ];
                    csv.push(line.join(','));
                });

                var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'dior-appointments-export-' + todayStr + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        }

        function redirectToPatientProfile(pid) {
            if (!pid || pid === '0') {
                if (window.Swal) {
                    Swal.fire({ icon: 'warning', title: 'Patient Profile', text: 'No patient record ID found for this appointment.' });
                } else {
                    alert('No patient record ID found for this appointment.');
                }
                return;
            }

            window.diorSelectedPatientId = pid;

            try {
                history.replaceState(null, '', '#tab=doc-patients-profile&patient_id=' + encodeURIComponent(pid));
            } catch (e) {}

            if (typeof window.diorAdminSwitchTab === 'function') {
                window.diorAdminSwitchTab('doc-patients-profile');
            } else if (typeof window.diorDocSwitchTab === 'function') {
                window.diorDocSwitchTab('doc-patients-profile', true);
            } else if (typeof window.diorSwitchTab === 'function') {
                window.diorSwitchTab('doc-patients-profile');
            } else if (typeof switchTab === 'function') {
                switchTab('doc-patients-profile');
            }

            if (typeof window.diorLoadPatientProfile === 'function') {
                window.diorLoadPatientProfile(pid);
            } else {
                setTimeout(function () {
                    if (typeof window.diorLoadPatientProfile === 'function') {
                        window.diorLoadPatientProfile(pid);
                    }
                }, 120);
            }

            var targetPanel = document.getElementById('tab-doc-patients-profile') || document.getElementById('dior-admin-content') || document.getElementById('dior-doc-content');
            if (targetPanel) {
                targetPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function populateAndOpenEditModal(row) {
            if (!row) return;

            document.getElementById('va-edit-uid').value = row.dataset.apptUid || '';
            
            var patientSelect = document.getElementById('va-edit-patient');
            var pid = row.dataset.patientId || '';
            if (patientSelect && pid) {
                var opt = patientSelect.querySelector('option[value="' + pid + '"]');
                if (!opt) {
                    opt = document.createElement('option');
                    opt.value = pid;
                    var pName = row.querySelector('.va-user-name');
                    opt.textContent = (pName ? pName.textContent.trim() : ('Patient #' + pid));
                    patientSelect.appendChild(opt);
                }
                patientSelect.value = pid;
            }

            var docSelect = document.getElementById('va-edit-doctor');
            var docId = row.dataset.doctorId || '';
            if (docSelect && docId) {
                docSelect.value = docId;
            }
            
            var statusSelect = document.getElementById('va-edit-status');
            if (statusSelect) {
                var rawStatus = (row.dataset.status || '').trim().toLowerCase().replace(/-/g, ' ');
                var matched = false;
                for (var i = 0; i < statusSelect.options.length; i++) {
                    var optVal = statusSelect.options[i].value.trim().toLowerCase().replace(/-/g, ' ');
                    if (optVal === rawStatus) {
                        statusSelect.selectedIndex = i;
                        matched = true;
                        break;
                    }
                }
                if (!matched) {
                    if (rawStatus.indexOf('success') !== -1) {
                        statusSelect.value = 'Success';
                    } else if (rawStatus.indexOf('prog') !== -1) {
                        statusSelect.value = 'In Progress';
                    } else if (rawStatus.indexOf('comp') !== -1) {
                        statusSelect.value = 'Completed';
                    } else if (rawStatus.indexOf('cancel') !== -1) {
                        statusSelect.value = 'Cancelled';
                    } else if (rawStatus.indexOf('no') !== -1 && rawStatus.indexOf('show') !== -1) {
                        statusSelect.value = 'No Show';
                    } else {
                        statusSelect.value = 'Pending';
                    }
                }
            }
            
            document.getElementById('va-edit-date').value = row.dataset.date || '';
            
            // Parse time to HH:MM format if needed
            var rawTime = row.dataset.time || '';
            var timeInput = document.getElementById('va-edit-time');
            if (timeInput) {
                if (rawTime.match(/^\d{2}:\d{2}$/)) {
                    timeInput.value = rawTime;
                } else if (rawTime.match(/(\d+):(\d+)\s*(AM|PM)/i)) {
                    var m = rawTime.match(/(\d+):(\d+)\s*(AM|PM)/i);
                    var h = parseInt(m[1], 10);
                    var min = m[2];
                    var ampm = m[3].toUpperCase();
                    if (ampm === 'PM' && h < 12) h += 12;
                    if (ampm === 'AM' && h === 12) h = 0;
                    timeInput.value = (h < 10 ? '0' + h : h) + ':' + min;
                } else {
                    timeInput.value = '10:00';
                }
            }

            var condInput = document.getElementById('va-edit-condition');
            if (condInput) condInput.value = row.dataset.condition || '';

            var vtSelect = document.getElementById('va-edit-visittype');
            if (vtSelect) vtSelect.value = row.dataset.visitType || 'Video Visit (HD)';

            var notesInput = document.getElementById('va-edit-notes');
            if (notesInput) notesInput.value = row.dataset.notes || '';

            var jumpBtn = document.getElementById('va-modal-jump-profile-btn');
            if (jumpBtn) {
                jumpBtn.dataset.patientId = pid;
                jumpBtn.onclick = function () {
                    var currentModal = bootstrap.Modal.getInstance(document.getElementById('diorViewApptEditModal'));
                    if (currentModal) currentModal.hide();
                    redirectToPatientProfile(this.dataset.patientId || patientSelect.value);
                };
            }

            var modalEl = document.getElementById('diorViewApptEditModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        function setupEditBtns() {
            var tbody = document.getElementById('va-appointments-tbody');
            if (!tbody) return;

            tbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.va-edit-appt-btn');
                if (!btn) return;
                var row = btn.closest('tr');
                if (row) {
                    populateAndOpenEditModal(row);
                }
            });

            var saveBtn = document.getElementById('va-save-appt-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function () {
                    var btn = this;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving...';
                    
                    var apptUid = document.getElementById('va-edit-uid').value;
                    var patientId = document.getElementById('va-edit-patient').value;
                    var doctorId = document.getElementById('va-edit-doctor') ? document.getElementById('va-edit-doctor').value : '';
                    var status = document.getElementById('va-edit-status').value;
                    var date = document.getElementById('va-edit-date').value;
                    var time = document.getElementById('va-edit-time').value;
                    var condition = document.getElementById('va-edit-condition').value;
                    var visitType = document.getElementById('va-edit-visittype').value;
                    var notes = document.getElementById('va-edit-notes').value;

                    var data = new FormData();
                    data.append('action', 'dior_doc_appointment_update');
                    data.append('nonce', nonce);
                    data.append('appt_uid', apptUid);
                    data.append('patient_id', patientId);
                    if (doctorId) data.append('doctor_id', doctorId);
                    data.append('status', status);
                    data.append('date', date);
                    data.append('time', time);
                    data.append('condition_name', condition);
                    data.append('visit_type', visitType);
                    data.append('notes', notes);

                    fetch(ajaxUrl, { method: 'POST', body: data })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (res.success) {
                                var modalEl = document.getElementById('diorViewApptEditModal');
                                var modalInstance = bootstrap.Modal.getInstance(modalEl);
                                if (modalInstance) modalInstance.hide();

                                // Update row in DOM dynamically
                                var targetRow = document.querySelector('tr[data-appt-uid="' + apptUid + '"]');
                                if (targetRow) {
                                    targetRow.dataset.status = status;
                                    targetRow.dataset.date = date;
                                    targetRow.dataset.condition = condition;
                                    targetRow.dataset.visitType = visitType;
                                    targetRow.dataset.notes = notes;
                                    targetRow.dataset.time = time;
                                    if (doctorId) targetRow.dataset.doctorId = doctorId;
                                    
                                    var badge = targetRow.querySelector('.va-badge');
                                    if (badge) {
                                        var badgeClass = statusBadgeMap[status.toLowerCase()] || 'badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1 small fw-semibold';
                                        badge.className = 'va-badge ' + badgeClass;
                                        badge.textContent = status;
                                    }

                                    // Update doctor name/specialty in row if selected
                                    var docSelect = document.getElementById('va-edit-doctor');
                                    if (docSelect && docSelect.selectedOptions.length > 0) {
                                        var docLabel = docSelect.selectedOptions[0].text.split('·')[0].trim();
                                        targetRow.dataset.doctorName = docLabel;
                                    }

                                    updateKpiCounters();
                                    renderPage(currentPage);
                                }

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Saved',
                                    text: 'Appointment updated successfully.',
                                    toast: true,
                                    position: 'top-end',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Update Failed', text: res.data && res.data.message ? res.data.message : 'Failed to update.' });
                            }
                        })
                        .catch(function () { Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' }); })
                        .finally(function () {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-check me-1"></i>Save Changes';
                        });
                });
            }
        }

        function setupDeleteBtns() {
            var tbody = document.getElementById('va-appointments-tbody');
            if (!tbody) return;

            tbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.va-delete-appt-btn');
                if (!btn) return;
                var patientName = btn.dataset.name || 'this patient';
                var uid = btn.dataset.uid;

                Swal.fire({
                    title: 'Delete Appointment?',
                    text: 'Are you sure you want to delete appointment for "' + patientName + '"? This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#EF4444',
                    cancelButtonColor: '#64748B',
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    var data = new FormData();
                    data.append('action', 'dior_doc_appointment_delete');
                    data.append('nonce', nonce);
                    data.append('appt_uid', uid);
                    fetch(ajaxUrl, { method: 'POST', body: data })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (res.success) {
                                var row = btn.closest('tr');
                                allRows = allRows.filter(function (r) { return r !== row; });
                                row.remove();
                                updateKpiCounters();
                                renderPage(currentPage);
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted',
                                    text: 'Appointment deleted successfully.',
                                    toast: true,
                                    position: 'top-end',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Delete Failed',
                                    text: res.data && res.data.message ? res.data.message : 'Failed to delete appointment.'
                                });
                            }
                        })
                        .catch(function () {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' });
                        });
                });
            });
        }

        function setupViewPatientBtns() {
            var tbody = document.getElementById('va-appointments-tbody');
            if (!tbody) return;

            tbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.va-view-patient-btn');
                if (!btn) return;
                var pid = btn.dataset.patientId;
                if (!pid) {
                    var tr = btn.closest('tr');
                    pid = tr ? tr.dataset.patientId : '';
                }
                redirectToPatientProfile(pid);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initViewAppt);
        } else {
            initViewAppt();
        }
    })();
</script>