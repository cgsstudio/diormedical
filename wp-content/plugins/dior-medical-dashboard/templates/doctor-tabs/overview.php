<?php
// Dashboard overview uses only live records passed by the dashboard controller.
global $wpdb;
$doctor_context = class_exists('Dior_Doctor_Dashboard')
    ? Dior_Doctor_Dashboard::get_single_doctor_context(get_current_user_id())
    : ['user_id' => get_current_user_id(), 'ids' => [get_current_user_id()]];
$overview_doctor_id = (int) ($doctor_context['user_id'] ?? 0);
$overview_doctor_ids = array_values(array_unique(array_filter(array_map(
    'intval',
    (array) ($doctor_context['ids'] ?? [$overview_doctor_id])
), static function ($id) { return $id > 0; })));
$overview_doctor_placeholders = $overview_doctor_ids
    ? implode(',', array_fill(0, count($overview_doctor_ids), '%d'))
    : '';
$today = current_time('Y-m-d');
$today_appointments = [];
$upcoming_appointments = [];
foreach ((array)$appointments as $appointment) {
    $date = (string)($appointment['appt_date'] ?? $appointment['date'] ?? '');
    $status = strtolower((string)($appointment['status'] ?? ''));
    if ($date === $today) $today_appointments[] = $appointment;
    if ($date >= $today && in_array($status, ['success', 'in-queue', 'in_queue'], true)) $upcoming_appointments[] = $appointment;
}
usort($today_appointments, static function($a, $b) {
    return strcmp((string)($a['appt_time'] ?? $a['time'] ?? ''), (string)($b['appt_time'] ?? $b['time'] ?? ''));
});
$completed_today = count(array_filter($today_appointments, static fn($a) => in_array(strtolower((string)($a['status'] ?? '')), ['completed','done'], true)));
$upcoming_today = count(array_filter($today_appointments, static fn($a) => in_array(strtolower((string)($a['status'] ?? '')), ['success','in-queue','in_queue'], true)));
$cancelled_today = count(array_filter($today_appointments, static fn($a) => in_array(strtolower((string)($a['status'] ?? '')), ['cancelled','canceled','cancel'], true)));
$current_month = current_time('Y-m');
$new_patients_this_month = 0;
foreach ((array)$patients as $patient) {
    $registered = strtotime((string)($patient['registered'] ?? ''));
    if ($registered && wp_date('Y-m', $registered) === $current_month) $new_patients_this_month++;
}
$recent_appointments = array_slice(array_reverse((array)$appointments), 0, 5);

// Fetch only reviews belonging to the configured single doctor.
$db_reviews = [];
if ($overview_doctor_ids) {
    $reviews_sql = "SELECT r.*,
        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))), ''), NULLIF(u.display_name, ''), 'Patient') AS patient_display_name,
        COALESCE(p.avatar_url, '') AS patient_avatar
        FROM {$wpdb->prefix}dior_reviews r
        LEFT JOIN {$wpdb->prefix}dior_patients p ON p.user_id = r.patient_id
        LEFT JOIN {$wpdb->users} u ON u.ID = r.patient_id
        WHERE r.doctor_id IN ({$overview_doctor_placeholders})
        ORDER BY r.created_at DESC LIMIT 10";
    $db_reviews = $wpdb->get_results($wpdb->prepare($reviews_sql, $overview_doctor_ids), ARRAY_A);
}
if (!is_array($db_reviews)) {
    $db_reviews = [];
}

// Fetch prescriptions and aggregate counts for this doctor only.
$db_prescriptions = [];
$rx_stats = [];
if ($overview_doctor_ids) {
    $prescription_sql = "SELECT * FROM {$wpdb->prefix}dior_prescriptions
        WHERE doctor_id IN ({$overview_doctor_placeholders})
        ORDER BY id DESC LIMIT 10";
    $db_prescriptions = $wpdb->get_results($wpdb->prepare($prescription_sql, $overview_doctor_ids), ARRAY_A);
    $stats_sql = "SELECT status, COUNT(*) as cnt FROM {$wpdb->prefix}dior_prescriptions
        WHERE doctor_id IN ({$overview_doctor_placeholders}) GROUP BY status";
    $rx_stats = $wpdb->get_results($wpdb->prepare($stats_sql, $overview_doctor_ids), ARRAY_A);
}
if (!is_array($db_prescriptions)) {
    $db_prescriptions = [];
}
if (!is_array($rx_stats)) {
    $rx_stats = [];
}

$rx_active_count = 0;
$rx_inactive_count = 0;
$rx_expired_count = 0;
foreach ($rx_stats as $st_row) {
    $st = strtolower(trim($st_row['status'] ?? ''));
    if (in_array($st, ['active', 'confirmed', 'in progress', 'in-progress'])) {
        $rx_active_count += (int) $st_row['cnt'];
    } elseif (in_array($st, ['expired', 'cancelled', 'canceled'])) {
        $rx_expired_count += (int) $st_row['cnt'];
    } else {
        $rx_inactive_count += (int) $st_row['cnt'];
    }
}

// Doctor identity is sourced from the configured profile, never demo credentials.
$overview_profile = (array) ($doctor_context['profile'] ?? []);
$doc_name = !empty($doctor['full_name']) ? $doctor['full_name'] : (!empty($overview_profile['full_name']) ? $overview_profile['full_name'] : 'Configured Doctor');
$doc_spec = !empty($doctor['specialty']) ? $doctor['specialty'] : (!empty($overview_profile['specialty']) ? $overview_profile['specialty'] : '');
$doc_email = !empty($doctor['email']) ? $doctor['email'] : (!empty($overview_profile['email']) ? $overview_profile['email'] : '');
$doc_phone = !empty($doctor['phone']) ? $doctor['phone'] : (!empty($overview_profile['phone']) ? $overview_profile['phone'] : '');
$doc_qual = !empty($doctor['qualifications']) ? $doctor['qualifications'] : (!empty($overview_profile['qualifications']) ? $overview_profile['qualifications'] : '');
$doc_avatar = !empty($doctor['avatar_url']) ? $doctor['avatar_url'] : (!empty($overview_profile['avatar_url']) ? $overview_profile['avatar_url'] : '');
?>


<section class="dior-tab-panel active" id="tab-doc-overview">

    <!-- Top Welcome Header matching Patient Dashboard standard -->
    <div class="content-head d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Welcome Back, <?php echo esc_html(strtoupper($doc_name)); ?></h1>
            <p class="text-secondary small mb-0">Overview of today's schedule, patient consultations, and clinical tasks.</p>
        </div>
        <button type="button" class="btn btn-primary rounded-3 fw-semibold py-2 px-3 shadow-sm d-inline-flex align-items-center gap-2" onclick="if(typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-appointments-book'); }">
            <i class="fas fa-calendar-plus"></i>
            <span>Book Appointment</span>
        </button>
    </div>

    <!-- Main Responsive Grid: Left col-12 col-xl-7 col-xxl-8 | Right col-12 col-xl-5 col-xxl-4 -->
    <div class="row g-3 g-xl-4 align-items-start">

        <!-- ============================================================ -->
        <!-- LEFT COLUMN                                                  -->
        <!-- ============================================================ -->
        <div class="col-12 col-xl-7 col-xxl-8">

            <!-- 1. Stats Row (4 Cards, responsive 2x2 on mobile, 4 across on md+) -->
            <div class="row g-2 g-sm-3 mb-3 mb-xl-4">
                <div class="col-6 col-md-3 col-xl-6 col-xxl-3">
                    <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="stat-num fs-1 fw-bold text-primary mb-0"><?php echo esc_html(number_format_i18n(count((array)$appointments))); ?></div>
                            <div class="text-primary bg-primary-subtle rounded-3 p-2 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;"><i class="far fa-calendar-days fs-5"></i></div>
                        </div>
                        <div class="stat-label text-dark fw-semibold small">Appointments</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-xl-6 col-xxl-3">
                    <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="stat-num fs-1 fw-bold text-primary mb-0"><?php echo esc_html(number_format_i18n(count($upcoming_appointments))); ?></div>
                            <div class="text-primary bg-primary-subtle rounded-3 p-2 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;"><i class="fas fa-clock-rotate-left fs-5"></i></div>
                        </div>
                        <div class="stat-label text-dark fw-semibold small">Upcoming</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-xl-6 col-xxl-3">
                    <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="stat-num fs-1 fw-bold text-primary mb-0"><?php echo esc_html(number_format_i18n($new_patients_this_month)); ?></div>
                            <div class="text-primary bg-primary-subtle rounded-3 p-2 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;"><i class="fas fa-user-doctor fs-5"></i></div>
                        </div>
                        <div class="stat-label text-dark fw-semibold small">New Patients</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-xl-6 col-xxl-3">
                    <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="stat-num fs-1 fw-bold text-primary mb-0">—</div>
                            <div class="text-primary bg-primary-subtle rounded-3 p-2 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;"><i class="fas fa-dollar-sign fs-5"></i></div>
                        </div>
                        <div class="stat-label text-dark fw-semibold small">Total Earning</div>
                    </div>
                </div>
            </div>

            <!-- 2. Today's Schedule Card -->
            <div class="card schedule-card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-3 mb-xl-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h2 class="schedule-title mb-0 fs-5 fw-bold text-dark"><i class="far fa-calendar-check text-primary me-2"></i>Today's Schedule</h2>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small"><?php echo esc_html($completed_today); ?> Completed</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 small"><?php echo esc_html($upcoming_today); ?> Upcoming</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 small"><?php echo esc_html($cancelled_today); ?> Cancel</span>
                    </div>
                </div>
                <div class="schedule-body dior-card-scrollable" style="max-height: 380px; overflow-y: auto; padding-right: 8px;">
                    <?php if (empty($today_appointments)): ?>
                        <div class="schedule-slot"><div class="schedule-appt"><div class="s-appt-left"><div><h3 class="s-patient-name">No appointments scheduled for today.</h3></div></div></div></div>
                    <?php else: foreach ($today_appointments as $appointment):
                        $patient_name = (string)($appointment['patient_name'] ?? 'Patient');
                        $patient_id = (string)($appointment['patient_id_num'] ?? '');
                        $time = (string)($appointment['appt_time'] ?? $appointment['time'] ?? '—');
                        $status = (string)($appointment['status'] ?? 'Scheduled');
                        $status_key = strtolower($status);
                        $status_class = in_array($status_key, ['completed','done'], true) ? 's-completed' : (in_array($status_key, ['cancelled','canceled','cancel'], true) ? 's-cancelled' : (in_array($status_key, ['in progress','in-progress','in-queue'], true) ? 's-progress' : 's-upcoming'));
                        $avatar = 'https://ui-avatars.com/api/?name=' . rawurlencode($patient_name) . '&background=eef2ff&color=4f46e5';
                    ?>
                    <div class="schedule-slot">
                        <div class="schedule-time"><?php echo esc_html($time); ?></div>
                        <div class="schedule-timeline"><div class="schedule-dot"></div></div>
                        <div class="schedule-appt">
                            <div class="s-appt-left"><img class="s-patient-img" src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($patient_name); ?>" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo rawurlencode($patient_name); ?>&background=eef2ff&color=4f46e5';"><div><h3 class="s-patient-name"><?php echo esc_html($patient_name); ?></h3><p class="s-patient-id"><?php echo esc_html($patient_id); ?></p></div></div>
                            <div class="s-appt-right"><span class="s-appt-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html(ucwords($status)); ?></span></div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- 3. Latest Appointments Table Card -->
            <div class="card table-card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-3 mb-xl-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="section-title mb-0 fs-5 fw-bold text-dark"><i class="fas fa-calendar-days text-primary me-2"></i>Latest Appointments</h3>
                    <a class="view-all text-primary text-decoration-none fw-semibold small" style="font-size: 13px !important;" href="#" onclick="if(typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-appointments-view'); } return false;">View All &rarr;</a>
                </div>
                <div class="table-responsive mb-0 dior-card-scrollable" style="max-height: 320px; overflow-y: auto;">
                    <table class="table align-middle text-nowrap mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th class="border-top-0 text-secondary small fw-semibold">Appointments Id</th>
                                <th class="border-top-0 text-secondary small fw-semibold">Patient Name</th>
                                <th class="border-top-0 text-secondary small fw-semibold">Date</th>
                                <th class="border-top-0 text-secondary small fw-semibold">Disease</th>
                                <th class="border-top-0 text-secondary small fw-semibold text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_appointments)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted small">No appointment records found.</td></tr>
                            <?php else: foreach ($recent_appointments as $appointment):
                                $appointment_id = (string)($appointment['appt_uid'] ?? $appointment['id'] ?? '');
                                $patient_name = (string)($appointment['patient_name'] ?? 'Patient');
                                $date = (string)($appointment['appt_date'] ?? $appointment['date'] ?? '');
                                $condition = (string)($appointment['condition'] ?? $appointment['reason'] ?? $appointment['visit_type'] ?? '—');
                            ?>
                                <tr>
                                    <td><span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small"><?php echo esc_html($appointment_id ?: '—'); ?></span></td>
                                    <td class="fw-semibold text-dark"><?php echo esc_html($patient_name); ?></td>
                                    <td class="text-secondary"><?php echo esc_html($date ? wp_date('M j, Y', strtotime($date)) : '—'); ?></td>
                                    <td class="text-secondary"><?php echo esc_html($condition); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary rounded-3 fw-semibold py-1 px-2 me-1" type="button" onclick="if(typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-prescriptions'); }">Prescription</button>
                                        <button class="btn btn-sm btn-primary rounded-3 fw-semibold py-1 px-2" type="button" onclick="if(typeof diorOpenFullAppointmentEdit === 'function') { diorOpenFullAppointmentEdit('<?php echo esc_js($appointment_id); ?>'); } else if(typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-appointments-edit'); }">Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 4. Prescriptions Overview Table Card -->
            <div class="card table-card prescriptions border-0 shadow-sm rounded-4 p-3 p-md-4 mb-3 mb-xl-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h3 class="section-title mb-0 fs-5 fw-bold text-dark"><i class="fas fa-prescription-bottle-medical text-primary me-2"></i>Prescriptions Overview</h3>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small"><?php echo esc_html($rx_active_count); ?> Active</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 small"><?php echo esc_html($rx_inactive_count); ?> Inactive</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 small"><?php echo esc_html($rx_expired_count); ?> Expired</span>
                    </div>
                </div>
                <div class="table-responsive mb-0 dior-card-scrollable" style="max-height: 320px; overflow-y: auto;">
                    <table class="table align-middle text-nowrap mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th class="border-top-0 text-secondary small fw-semibold">Prescriptions Id</th>
                                <th class="border-top-0 text-secondary small fw-semibold">Patient Name</th>
                                <th class="border-top-0 text-secondary small fw-semibold">Medication</th>
                                <th class="border-top-0 text-secondary small fw-semibold">Dosage</th>
                                <th class="border-top-0 text-secondary small fw-semibold text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($db_prescriptions)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted small">No prescription records found.</td></tr>
                            <?php else: foreach ($db_prescriptions as $p_item):
                                $rx_uid = (string)($p_item['prescription_id'] ?: $p_item['prescription_uid'] ?: ('RX-' . ($p_item['id'] ?? '')));
                                $rx_pname = (string)($p_item['patient_name'] ?? 'Patient');
                                $rx_med = (string)($p_item['medication'] ?: $p_item['medications'] ?: '—');
                                $rx_dose = (string)($p_item['dosage'] ?: '—');
                                $rx_stat = (string)($p_item['status'] ?: 'Active');
                                $rx_st_key = strtolower($rx_stat);
                                $rx_pill_class = in_array($rx_st_key, ['active', 'confirmed']) ? 'bg-success-subtle text-success border border-success-subtle' : (in_array($rx_st_key, ['expired', 'cancelled', 'canceled']) ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle');
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($rx_uid); ?></strong></td>
                                <td class="fw-semibold text-dark"><?php echo esc_html($rx_pname); ?></td>
                                <td class="text-secondary"><?php echo esc_html($rx_med); ?></td>
                                <td class="text-secondary"><?php echo esc_html($rx_dose); ?></td>
                                <td class="text-end"><span class="badge <?php echo esc_attr($rx_pill_class); ?> rounded-pill px-2 py-1 small"><?php echo esc_html(ucwords($rx_stat)); ?></span></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 5. Latest Lab Results Table Card -->
            <div class="card table-card lab border-0 shadow-sm rounded-4 p-3 p-md-4 mb-3 mb-xl-0">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="section-title mb-0 fs-5 fw-bold text-dark"><i class="fas fa-flask text-primary me-2"></i>Latest Lab Results</h3>
                    <a class="view-all text-primary text-decoration-none fw-semibold small" style="font-size: 13px !important;" href="#" onclick="if(typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-documents-upload'); } return false;">View All &rarr;</a>
                </div>
                <div class="table-responsive mb-0 dior-card-scrollable" style="max-height: 320px; overflow-y: auto;">
                    <table class="table align-middle text-nowrap mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th class="border-top-0 text-secondary small fw-semibold">Patient Id</th>
                                <th class="border-top-0 text-secondary small fw-semibold">Patient Name</th>
                                <th class="border-top-0 text-secondary small fw-semibold">Test Name</th>
                                <th class="border-top-0 text-secondary small fw-semibold">Date</th>
                                <th class="border-top-0 text-secondary small fw-semibold">Status</th>
                                <th class="border-top-0 text-secondary small fw-semibold text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">PAT00123</span></td>
                                <td class="fw-semibold text-dark">Mia Song</td>
                                <td class="text-secondary">Blood Work</td>
                                <td class="text-secondary">Sep 10, 2026</td>
                                <td><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small">Ready</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0 me-1" style="width: 30px; height: 30px;"><i class="fas fa-info"></i></button>
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;"><i class="fas fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">PAT00111</span></td>
                                <td class="fw-semibold text-dark">John Johnson</td>
                                <td class="text-secondary">Renal Function Test</td>
                                <td class="text-secondary">Sep 10, 2026</td>
                                <td><span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 small">Pending</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0 me-1" style="width: 30px; height: 30px;"><i class="fas fa-info"></i></button>
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;"><i class="fas fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">PAT00112</span></td>
                                <td class="fw-semibold text-dark">Elizabeth Brown</td>
                                <td class="text-secondary">Thyroid Function Test</td>
                                <td class="text-secondary">Sep 10, 2026</td>
                                <td><span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 small">In Progress</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0 me-1" style="width: 30px; height: 30px;"><i class="fas fa-info"></i></button>
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;"><i class="fas fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">PAT00112</span></td>
                                <td class="fw-semibold text-dark">Elizabeth Brown</td>
                                <td class="text-secondary">Vitamin D Test</td>
                                <td class="text-secondary">Sep 10, 2026</td>
                                <td><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small">Normal</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0 me-1" style="width: 30px; height: 30px;"><i class="fas fa-info"></i></button>
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;"><i class="fas fa-download"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- ============================================================ -->
        <!-- RIGHT COLUMN                                                 -->
        <!-- ============================================================ -->
        <div class="col-12 col-xl-5 col-xxl-4">
            <div class="row g-3">

                <!-- 1. Doctor Profile Card (col-12 col-md-6 col-xl-12) -->
                <div class="col-12 col-md-6 col-xl-12">
                    <div class="card profile border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img class="rounded-circle border border-primary-subtle flex-shrink-0" style="width: 72px; height: 72px; object-fit: cover;" src="<?php echo esc_url($doc_avatar); ?>"
                                    alt="<?php echo esc_attr($doc_name); ?>"
                                    onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo rawurlencode($doc_name); ?>&background=0b1120&color=d4af37';">
                                <div class="min-w-0">
                                    <h4 class="fs-6 fw-bold text-dark mb-1 text-truncate"><?php echo esc_html($doc_name); ?></h4>
                                    <div class="text-primary small fw-semibold text-truncate mb-1"><?php echo esc_html($doc_spec); ?></div>
                                    <div class="text-secondary small">
                                        <div><i class="fas fa-envelope text-primary me-1"></i><?php echo esc_html($doc_email); ?></div>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <span><i class="fas fa-phone text-primary me-1"></i><?php echo esc_html($doc_phone); ?></span>
                                            <span><i class="fas fa-graduation-cap text-primary me-1"></i><?php echo esc_html($doc_qual); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary rounded-3 fw-semibold py-2 px-3 shadow-sm w-100 mt-2" onclick="if(typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-settings'); }">Edit Profile</button>
                    </div>
                </div>

                <!-- 2. Pending Tasks Card (col-12 col-md-6 col-xl-12) -->
                <div class="col-12 col-md-6 col-xl-12">
                    <div class="card tasks border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column">
                        <h3 class="section-title mb-3 fs-5 fw-bold text-dark"><i class="fas fa-list-check text-primary me-2"></i>Pending Tasks</h3>
                        <div class="tasks-body dior-card-scrollable d-flex flex-column gap-2 flex-grow-1" style="max-height: 280px; overflow-y: auto; padding-right: 4px;">
                            <div class="d-flex align-items-start gap-2 p-2 border-bottom">
                                <i class="far fa-circle-check text-primary mt-1"></i>
                                <div>
                                    <b class="d-block small text-dark fw-bold">Review lab reports</b>
                                    <p class="mb-1 text-secondary small" style="font-size: 11.5px;">Check blood test results for 3 patients</p>
                                    <small class="text-muted" style="font-size: 11px;"><i class="far fa-calendar me-1"></i>Oct 2, 2026</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start gap-2 p-2 border-bottom">
                                <i class="far fa-circle-check text-primary mt-1"></i>
                                <div>
                                    <b class="d-block small text-dark fw-bold">Sign prescriptions</b>
                                    <p class="mb-1 text-secondary small" style="font-size: 11.5px;">5 prescriptions pending signature</p>
                                    <small class="text-muted" style="font-size: 11px;"><i class="far fa-calendar me-1"></i>Oct 2, 2026</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start gap-2 p-2 border-bottom">
                                <i class="far fa-circle-check text-primary mt-1"></i>
                                <div>
                                    <b class="d-block small text-dark fw-bold">Approve medical notes</b>
                                    <p class="mb-1 text-secondary small" style="font-size: 11.5px;">Review and approve consultation notes</p>
                                    <small class="text-muted" style="font-size: 11px;"><i class="far fa-calendar me-1"></i>Oct 2, 2026</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start gap-2 p-2">
                                <i class="far fa-circle-check text-primary mt-1"></i>
                                <div>
                                    <b class="d-block small text-dark fw-bold">Update patient records</b>
                                    <p class="mb-1 text-secondary small" style="font-size: 11.5px;">Complete EMR updates for recent visits</p>
                                    <small class="text-muted" style="font-size: 11px;"><i class="far fa-calendar me-1"></i>Oct 2, 2026</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Follow-up Reminders Card (col-12 col-md-6 col-xl-12) -->
                <div class="col-12 col-md-6 col-xl-12">
                    <div class="card follow border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column">
                        <h3 class="section-title mb-3 fs-5 fw-bold text-dark"><i class="fas fa-bell text-primary me-2"></i>Follow-up Reminders</h3>
                        <div class="follow-body dior-card-scrollable d-flex flex-column gap-2 flex-grow-1" style="max-height: 320px; overflow-y: auto; padding-right: 4px;">
                            <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <img class="rounded-circle flex-shrink-0" style="width: 38px; height: 38px;" src="https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff&color=4f46e5" alt="Mia Song" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff&color=4f46e5';">
                                    <div class="min-w-0">
                                        <b class="d-block small text-dark text-truncate">Mia Song | PAT00123</b>
                                        <small class="text-muted d-block" style="font-size: 11px;">Routine Checkup &bull; Oct 2, 2026</small>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;" type="button"><i class="far fa-envelope"></i></button>
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;" type="button"><i class="fas fa-phone"></i></button>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <img class="rounded-circle flex-shrink-0" style="width: 38px; height: 38px;" src="https://ui-avatars.com/api/?name=John+Johnson&background=f0fdf4&color=166534" alt="John Johnson" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=John+Johnson&background=f0fdf4&color=166534';">
                                    <div class="min-w-0">
                                        <b class="d-block small text-dark text-truncate">John Johnson | PAT00111</b>
                                        <small class="text-muted d-block" style="font-size: 11px;">Routine Checkup &bull; Oct 2, 2026</small>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;" type="button"><i class="far fa-envelope"></i></button>
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;" type="button"><i class="fas fa-phone"></i></button>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <img class="rounded-circle flex-shrink-0" style="width: 38px; height: 38px;" src="https://ui-avatars.com/api/?name=Richard+Davis&background=fff1f2&color=be123c" alt="Richard Davis" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Richard+Davis&background=fff1f2&color=be123c';">
                                    <div class="min-w-0">
                                        <b class="d-block small text-dark text-truncate">Richard Davis | PAT00238</b>
                                        <small class="text-muted d-block" style="font-size: 11px;">Routine Checkup &bull; Oct 2, 2026</small>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;" type="button"><i class="far fa-envelope"></i></button>
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;" type="button"><i class="fas fa-phone"></i></button>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between p-2">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <img class="rounded-circle flex-shrink-0" style="width: 38px; height: 38px;" src="https://ui-avatars.com/api/?name=Elizabeth+Brown&background=fef3c7&color=b45309" alt="Elizabeth Brown" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Elizabeth+Brown&background=fef3c7&color=b45309';">
                                    <div class="min-w-0">
                                        <b class="d-block small text-dark text-truncate">Elizabeth Brown | PAT00112</b>
                                        <small class="text-muted d-block" style="font-size: 11px;">Routine Checkup &bull; Oct 2, 2026</small>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;" type="button"><i class="far fa-envelope"></i></button>
                                    <button class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 30px; height: 30px;" type="button"><i class="fas fa-phone"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Patient Review Card (col-12 col-md-6 col-xl-12) -->
                <div class="col-12 col-md-6 col-xl-12">
                    <div class="card reviews border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column">
                        <h3 class="section-title mb-3 fs-5 fw-bold text-dark"><i class="fas fa-star text-warning me-2"></i>Patient Review</h3>
                        <div class="reviews-scroll dior-card-scrollable d-flex flex-column gap-3 flex-grow-1" style="max-height: 280px; overflow-y: auto; padding-right: 4px;">
                            <?php if (empty($db_reviews)): ?>
                                <div class="p-4 text-center text-secondary small">No patient reviews submitted yet.</div>
                            <?php else: foreach ($db_reviews as $rev):
                                $r_name = (string)($rev['patient_display_name'] ?? 'Patient');
                                $r_text = (string)($rev['comment'] ?: $rev['subject'] ?: 'Excellent doctor! Very professional and caring.');
                                $r_rating = (int)($rev['rating'] ?? 5);
                                if ($r_rating < 1) $r_rating = 5;
                                if ($r_rating > 5) $r_rating = 5;
                                $r_avatar = !empty($rev['patient_avatar']) ? $rev['patient_avatar'] : ('https://ui-avatars.com/api/?name=' . rawurlencode($r_name) . '&background=eef2ff&color=4f46e5');
                            ?>
                            <div class="d-flex align-items-start gap-2 p-2 border-bottom">
                                <img class="rounded-circle flex-shrink-0" style="width: 36px; height: 36px;" src="<?php echo esc_url($r_avatar); ?>"
                                    alt="<?php echo esc_attr($r_name); ?>"
                                    onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo rawurlencode($r_name); ?>&background=eef2ff&color=4f46e5';">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fw-semibold text-dark small"><?php echo esc_html($r_name); ?></span>
                                        <span class="text-warning small" style="font-size: 11px;">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <i class="<?php echo $s <= $r_rating ? 'fas fa-star' : 'far fa-star text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                    </div>
                                    <div class="text-secondary small" style="font-size: 12px; line-height: 1.4;"><?php echo esc_html($r_text); ?></div>
                                </div>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 5. Upcoming Sessions Card (col-12 col-md-6 col-xl-12) -->
                <div class="col-12 col-md-6 col-xl-12">
                    <div class="card sessions border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column">
                        <h3 class="section-title mb-3 fs-5 fw-bold text-dark"><i class="fas fa-video text-primary me-2"></i>Upcoming Sessions</h3>
                        <div class="sessions-scroll dior-card-scrollable d-flex flex-column gap-2 flex-grow-1" style="max-height: 280px; overflow-y: auto; padding-right: 4px;">
                            <?php if (empty($upcoming_appointments)): ?>
                                <div class="p-4 text-center text-secondary small">
                                    <i class="far fa-calendar-check fs-4 d-block mb-2 text-primary opacity-50"></i>
                                    No upcoming sessions scheduled.
                                </div>
                            <?php else: foreach ($upcoming_appointments as $sess):
                                $sess_pname = (string)($sess['patient_name'] ?? 'Patient');
                                $sess_pid = (string)($sess['patient_id_num'] ?? $sess['patient_id'] ?? '');
                                $sess_cond = (string)($sess['condition'] ?? $sess['visit_type'] ?? $sess['reason'] ?? 'General Consultation');
                                $sess_date = (string)($sess['appt_date'] ?? $sess['date'] ?? '');
                                $sess_time = (string)($sess['appt_time'] ?? $sess['time'] ?? '—');
                                $sess_uid = (string)($sess['appt_uid'] ?? $sess['id'] ?? '');
                                $sess_avatar = 'https://ui-avatars.com/api/?name=' . rawurlencode($sess_pname) . '&background=eef2ff&color=4f46e5';
                                $sess_join = (string)($sess['join_url'] ?? $doctor['zoom_link'] ?? 'https://zoom.us/join');
                            ?>
                            <div class="p-2 border-bottom">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <img class="rounded-circle flex-shrink-0" style="width: 36px; height: 36px;"
                                        src="<?php echo esc_url($sess_avatar); ?>"
                                        alt="<?php echo esc_attr($sess_pname); ?>"
                                        onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo rawurlencode($sess_pname); ?>&background=eef2ff&color=4f46e5';">
                                    <div class="min-w-0">
                                        <b class="d-block small text-dark text-truncate"><?php echo esc_html($sess_cond); ?></b>
                                        <span class="text-secondary small d-block" style="font-size: 11px;"><?php echo esc_html($sess_pname . ($sess_pid ? ' | ' . $sess_pid : '')); ?></span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between text-secondary small mb-2" style="font-size: 11px;">
                                    <span><i class="far fa-calendar me-1 text-primary"></i><?php echo esc_html($sess_date ? wp_date('M j, Y', strtotime($sess_date)) : '—'); ?></span>
                                    <span><i class="far fa-clock me-1 text-primary"></i><?php echo esc_html($sess_time); ?></span>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3 flex-fill py-1 small fw-semibold" onclick="if(typeof diorOpenFullAppointmentEdit === 'function') { diorOpenFullAppointmentEdit('<?php echo esc_js($sess_uid); ?>'); } else if(typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-appointments-edit'); }">Reschedule</button>
                                    <button type="button" class="btn btn-sm btn-primary rounded-3 flex-fill py-1 small fw-semibold shadow-sm" onclick="window.open('<?php echo esc_url($sess_join); ?>', '_blank')">Join Now</button>
                                </div>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

</section>
