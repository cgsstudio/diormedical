<?php
/**
 * Dior Medical — Doctor Appointment Calendar (Doctor Dashboard Design Matching)
 * 100% Native Bootstrap 5 + Dynamic Realtime Appointment Engine
 */
if (!defined('ABSPATH')) { exit; }

global $wpdb;

// Resolve the one doctor configured for this dashboard. Legacy linked IDs are
// included only so appointments created before account consolidation remain visible.
$_cal_context = class_exists('Dior_Doctor_Dashboard')
    ? Dior_Doctor_Dashboard::get_single_doctor_context(get_current_user_id())
    : ['user_id' => get_current_user_id(), 'ids' => [get_current_user_id()]];
$_cal_doctor_ids = array_values(array_unique(array_map('intval', (array) ($_cal_context['appointment_ids'] ?? $_cal_context['ids'] ?? []))));
$_cal_doctor_ids = array_values(array_filter($_cal_doctor_ids, static function ($id) {
    return $id >= 0;
}));

// Never fall back to an unscoped appointment query in the Doctor Dashboard.
$raw_appts = [];
if (!empty($_cal_doctor_ids)) {
    $doctor_placeholders = implode(',', array_fill(0, count($_cal_doctor_ids), '%d'));
    $calendar_sql = "SELECT a.*,
            COALESCE(p.display_name, 'Patient') AS patient_name,
            COALESCE(p.user_email, '') AS patient_email,
            COALESCE(d.post_title, u.display_name, 'Dr. Attending Physician') AS doc_name
        FROM {$wpdb->prefix}dior_appointments a
        LEFT JOIN {$wpdb->users} p ON p.ID = a.patient_id
        LEFT JOIN {$wpdb->posts} d ON d.ID = a.doctor_id AND d.post_type = 'wpddb_doctor'
        LEFT JOIN {$wpdb->users} u ON u.ID = a.doctor_id
        WHERE a.is_deleted = 0 AND a.doctor_id IN ({$doctor_placeholders})
        ORDER BY a.appt_date ASC, a.appt_time ASC";
    $raw_appts = $wpdb->get_results($wpdb->prepare($calendar_sql, $_cal_doctor_ids), ARRAY_A);
}

if (!is_array($raw_appts)) {
    $raw_appts = [];
}

// Compute Dynamic KPIs
$today_ymd = current_time('Y-m-d');
$kpi_total = count($raw_appts);
$kpi_upcoming = 0;
$kpi_video = 0;
$kpi_completed = 0;

$calendar_cards = [];
foreach ($raw_appts as $idx => $app) {
    $status_raw = !empty($app['status']) ? $app['status'] : 'Confirmed';
    $status_lc = strtolower($status_raw);
    $pname = !empty($app['patient_name']) ? $app['patient_name'] : 'Patient';
    $pemail = !empty($app['patient_email']) ? $app['patient_email'] : '';
    $cond = !empty($app['condition_name']) ? $app['condition_name'] : 'Telehealth Consultation';
    $dname = !empty($app['doc_name']) ? $app['doc_name'] : 'Dr. Attending Physician';
    if (stripos($dname, 'Dr.') === false && stripos($dname, 'Dr ') === false) {
        $dname = 'Dr. ' . $dname;
    }
    $vtype = !empty($app['visit_type']) ? $app['visit_type'] : 'Video Visit (HD)';
    $time_raw = !empty($app['appt_time']) ? $app['appt_time'] : '09:00 AM';
    $date_raw = !empty($app['appt_date']) ? $app['appt_date'] : $today_ymd;
    $join_url = !empty($app['join_url']) ? $app['join_url'] : '';
    $notes = !empty($app['notes']) ? $app['notes'] : '';
    $doc_id = (int)($app['doctor_id'] ?? 0);
    $uid = !empty($app['appt_uid']) ? $app['appt_uid'] : ('APT-' . ($app['id'] ?? (1000 + $idx)));

    $is_video = (stripos($vtype, 'video') !== false || stripos($vtype, 'telehealth') !== false || !empty($join_url));
    if ($is_video) {
        $kpi_video++;
    }

    if (in_array($status_lc, ['confirmed', 'scheduled', 'in-queue', 'pending', 'in progress'])) {
        $kpi_upcoming++;
    } elseif (in_array($status_lc, ['completed', 'done', 'past'])) {
        $kpi_completed++;
    }

    $calendar_cards[] = [
        'id'           => $uid,
        'patient_name' => $pname,
        'patient_email'=> $pemail,
        'doctor_id'    => $doc_id,
        'doctor_name'  => $dname,
        'condition'    => $cond,
        'visit_type'   => $vtype,
        'time'         => $time_raw,
        'date'         => $date_raw,
        'status'       => ucfirst($status_raw),
        'join_url'     => $join_url,
        'notes'        => $notes,
        'is_video'     => $is_video
    ];
}

$calendar_json = json_encode($calendar_cards);
?>

<section class="dior-tab-panel" id="tab-doc-appointments" style="display: none;">

    <!-- Breadcrumb & Page Header (100% Bootstrap 5) -->
    <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="mb-1 text-dark fw-bold fs-4 d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <i class="fas fa-calendar-days fs-6"></i>
                </span>
                Appointment Calendar
            </h4>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" 
                onclick="if (typeof diorOpenDocBookerModal === 'function') { diorOpenDocBookerModal(); } else if (typeof diorAdminSwitchTab === 'function') { diorAdminSwitchTab('doc-appointments-book'); } else if (typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-appointments-book'); }"
                class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2">
                <i class="fas fa-plus"></i>
                <span>Book Appointment</span>
            </button>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn active" data-tab="doc-appointments" data-doc-tab="doc-appointments" onclick="if(typeof diorAdminSwitchTab==='function'){diorAdminSwitchTab('doc-appointments');}else if(typeof diorDocSwitchTab==='function'){diorDocSwitchTab('doc-appointments');}">
            <i class="fas fa-calendar-days"></i> Appointment Calendar
        </button>
        <button type="button" class="dior-subtab-pill btn" data-tab="doc-appointments-view" data-doc-tab="doc-appointments-view" onclick="if(typeof diorAdminSwitchTab==='function'){diorAdminSwitchTab('doc-appointments-view');}else if(typeof diorDocSwitchTab==='function'){diorDocSwitchTab('doc-appointments-view');}">
            <i class="fas fa-calendar-check"></i> View Appointment
        </button>
        <button type="button" class="dior-subtab-pill btn" data-tab="doc-appointments-book" data-doc-tab="doc-appointments-book" onclick="if(typeof diorAdminSwitchTab==='function'){diorAdminSwitchTab('doc-appointments-book');}else if(typeof diorDocSwitchTab==='function'){diorDocSwitchTab('doc-appointments-book');}">
            <i class="fas fa-calendar-plus"></i> Book Appointment
        </button>
        <button type="button" class="dior-subtab-pill btn" data-tab="doc-appointments-edit" data-doc-tab="doc-appointments-edit" onclick="if(typeof diorAdminSwitchTab==='function'){diorAdminSwitchTab('doc-appointments-edit');}else if(typeof diorDocSwitchTab==='function'){diorDocSwitchTab('doc-appointments-edit');}">
            <i class="fas fa-pen-to-square"></i> Edit Appointment
        </button>
    </div>

    <!-- Quick Stat KPI Bar (100% Bootstrap 5 Standard Cards) -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Total Bookings</span>
                        <div class="stat-num fs-1 fw-bold text-dark mt-1" id="dior-doc-kpi-total"><?php echo esc_html($kpi_total); ?></div>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-3 p-2.5">
                        <i class="fas fa-calendar-days fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="text-primary fw-semibold"><i class="fas fa-chart-line me-1"></i>Single-doctor schedule</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Upcoming Visits</span>
                        <div class="stat-num fs-1 fw-bold text-primary mt-1" id="dior-doc-kpi-upcoming"><?php echo esc_html($kpi_upcoming); ?></div>
                    </div>
                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-3 p-2.5">
                        <i class="fas fa-clock fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="text-info fw-semibold"><i class="fas fa-calendar-check me-1"></i>Active</span> scheduled visits
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Telehealth Video</span>
                        <div class="stat-num fs-1 fw-bold text-success mt-1" id="dior-doc-kpi-video"><?php echo esc_html($kpi_video); ?></div>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-3 p-2.5">
                        <i class="fas fa-video fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="text-success fw-semibold"><i class="fas fa-satellite-dish me-1"></i>HD Room</span> consultations
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Completed Visits</span>
                        <div class="stat-num fs-1 fw-bold text-secondary mt-1" id="dior-doc-kpi-completed"><?php echo esc_html($kpi_completed); ?></div>
                    </div>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-3 p-2.5">
                        <i class="fas fa-circle-check fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="text-secondary fw-semibold"><i class="fas fa-clipboard-check me-1"></i>Finalized</span> medical notes
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Container Card (100% Bootstrap 5) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        
        <!-- Toolbar Header -->
        <div class="card-header bg-white border-bottom py-3 px-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 dior-doc-cal-toolbar">

                <!-- Navigation Controls (Prev, Month Title, Next, Today) -->
                <div class="d-flex align-items-center gap-2">
                    <button type="button"
                        class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center"
                        id="dior-doc-cal-prev" style="width: 36px; height: 36px;" title="Previous Month">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h3 class="mb-0 fw-bold text-dark fs-5 px-2 text-center" id="dior-doc-cal-title"
                        style="min-width: 170px;">
                        October 2026
                    </h3>
                    <button type="button"
                        class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center"
                        id="dior-doc-cal-next" style="width: 36px; height: 36px;" title="Next Month">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button type="button"
                        class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold ms-1"
                        id="dior-doc-cal-today">
                        Today
                    </button>
                </div>

                <!-- Filters & View Toggles -->
                <div id="dior-doc-cal-toolbar-actions" class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                    <!-- Filter by Visit Type -->
                    <div class="d-none d-md-block">
                        <select class="form-select form-select-sm rounded-pill border-secondary-subtle"
                            id="dior-doc-cal-type-filter" style="min-width: 140px;">
                            <option value="all">All Visit Types</option>
                            <option value="video">Telehealth Video (HD)</option>
                            <option value="clinic">In-Clinic Visit</option>
                            <option value="phone">Phone Consultation</option>
                        </select>
                    </div>

                    <!-- Filter by Status -->
                    <div class="d-none d-md-block">
                        <select class="form-select form-select-sm rounded-pill border-secondary-subtle"
                            id="dior-doc-cal-status-filter" style="min-width: 130px;">
                            <option value="all">All Statuses</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="pending">In Queue / Pending</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- View Mode Toggle (Month vs Agenda) -->
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-primary active fw-semibold"
                            id="dior-doc-cal-view-month">
                            <i class="fas fa-calendar-days me-1"></i> Month
                        </button>
                        <button type="button" class="btn btn-outline-secondary fw-semibold"
                            id="dior-doc-cal-view-agenda">
                            <i class="fas fa-list-ul me-1"></i> Agenda
                        </button>
                    </div>

                    <!-- Add Event / Book Visit Button -->
                    <button type="button" onclick="if (typeof diorOpenDocBookerModal === 'function') { diorOpenDocBookerModal(); } else if (typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-appointments-book'); }"
                        class="btn btn-primary btn-sm rounded-pill px-3 fw-bold d-inline-flex align-items-center justify-content-center gap-1 shadow-sm ms-0" style="width:auto !important; flex:0 0 auto; white-space:nowrap;">
                        <i class="fas fa-plus"></i> New Event
                    </button>
                </div>

            </div>
        </div>

        <!-- Main Content Area: 2 Columns Grid -->
        <div class="card-body p-3 p-lg-4">
            <div class="row g-4 align-items-stretch">

                <!-- Left Column: Calendar Grid / Agenda List (8 Cols) -->
                <div class="col-lg-8 col-12">

                    <!-- 1. Month View (100% Bootstrap 5 Table Grid) -->
                    <div id="dior-doc-cal-month-wrap" class="dior-bs-calendar-container">
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-bordered mb-0 align-top">
                                <thead>
                                    <tr class="text-center text-uppercase text-secondary bg-light"
                                        style="font-size: 11px; letter-spacing: 0.8px;">
                                        <th style="width: 14.28%; padding: 10px 4px;">Sun</th>
                                        <th style="width: 14.28%; padding: 10px 4px;">Mon</th>
                                        <th style="width: 14.28%; padding: 10px 4px;">Tue</th>
                                        <th style="width: 14.28%; padding: 10px 4px;">Wed</th>
                                        <th style="width: 14.28%; padding: 10px 4px;">Thu</th>
                                        <th style="width: 14.28%; padding: 10px 4px;">Fri</th>
                                        <th style="width: 14.28%; padding: 10px 4px;">Sat</th>
                                    </tr>
                                </thead>
                                <tbody id="dior-doc-cal-grid-body">
                                    <!-- Populated dynamically via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 2. Agenda View (Initially Hidden) -->
                    <div id="dior-doc-cal-agenda-wrap" style="display: none;">
                        <div id="dior-doc-cal-agenda-list" class="d-flex flex-column gap-3">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>

                </div>

                <!-- Right Column: Selected Day Schedule Card (4 Cols) -->
                <div class="col-lg-4 col-12">
                    <div class="card border border-light-subtle rounded-4 shadow-none bg-light h-100 d-flex flex-column">
                        
                        <!-- Day Schedule Header -->
                        <div class="card-header bg-white border-bottom py-3 px-3 rounded-top-4 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-bold text-uppercase" style="font-size: 11px;">Selected Date</span>
                                <h5 class="mb-0 fw-bold text-dark fs-6 mt-1" id="dior-doc-cal-selected-header">
                                    Today's Schedule
                                </h5>
                            </div>
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-bold"
                                id="dior-doc-cal-selected-count">
                                0 Visits
                            </span>
                        </div>

                        <!-- Day Schedule Body -->
                        <div class="card-body p-3 flex-grow-1 overflow-auto" id="dior-doc-cal-selected-body"
                            style="max-height: 480px; min-height: 250px;">
                            <!-- Populated dynamically via JS -->
                        </div>

                        <!-- Day Schedule Footer: Legend & Actions -->
                        <div class="card-footer bg-white border-top py-3 px-3 rounded-bottom-4">
                            <!-- Legend -->
                            <div class="d-flex flex-wrap gap-2 text-muted mb-3" style="font-size: 11px;">
                                <span class="d-inline-flex align-items-center gap-1">
                                    <span class="rounded-circle d-inline-block" style="width: 8px; height: 8px; background: #2563eb;"></span>
                                    Confirmed
                                </span>
                                <span class="d-inline-flex align-items-center gap-1">
                                    <span class="rounded-circle d-inline-block" style="width: 8px; height: 8px; background: #0284c7;"></span>
                                    Video HD
                                </span>
                                <span class="d-inline-flex align-items-center gap-1">
                                    <span class="rounded-circle d-inline-block" style="width: 8px; height: 8px; background: #10b981;"></span>
                                    Completed
                                </span>
                                <span class="d-inline-flex align-items-center gap-1">
                                    <span class="rounded-circle d-inline-block" style="width: 8px; height: 8px; background: #f59e0b;"></span>
                                    In Queue
                                </span>
                            </div>

                            <button type="button" onclick="if (typeof diorOpenDocBookerModal === 'function') { diorOpenDocBookerModal(); } else if (typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-appointments-book'); }"
                                class="btn btn-outline-primary btn-sm w-100 rounded-3 fw-semibold py-2">
                                <i class="fas fa-calendar-plus me-1"></i> Add Appointment on this Date
                            </button>
                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>

</section>

<!-- Self-Contained Native Bootstrap 5 Doctor Calendar Script -->
<script>
window.dior_doc_calendar_data = <?php echo $calendar_json; ?>;

(function() {
    'use strict';

    var calDate = new Date();
    var selectedYmd = calDate.getFullYear() + '-' + String(calDate.getMonth() + 1).padStart(2, '0') + '-' + String(calDate.getDate()).padStart(2, '0');
    var viewMode = 'month';
    var typeFilter = 'all';
    var statusFilter = 'all';

    var monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    var dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    function getFilteredEvents() {
        var data = window.dior_doc_calendar_data || [];
        return data.filter(function(item) {
            if (typeFilter !== 'all') {
                var t = (item.visit_type || '').toLowerCase();
                if (typeFilter === 'video' && t.indexOf('video') === -1 && t.indexOf('telehealth') === -1) return false;
                if (typeFilter === 'clinic' && t.indexOf('clinic') === -1 && t.indexOf('in-person') === -1) return false;
                if (typeFilter === 'phone' && t.indexOf('phone') === -1) return false;
            }
            if (statusFilter !== 'all') {
                var s = (item.status || '').toLowerCase();
                if (statusFilter === 'confirmed' && s !== 'confirmed') return false;
                if (statusFilter === 'pending' && s !== 'pending' && s !== 'in queue') return false;
                if (statusFilter === 'completed' && s !== 'completed') return false;
                if (statusFilter === 'cancelled' && s !== 'cancelled' && s !== 'canceled') return false;
            }
            return true;
        });
    }

    function renderMonthGrid() {
        var tbody = document.getElementById('dior-doc-cal-grid-body');
        var titleEl = document.getElementById('dior-doc-cal-title');
        if (!tbody || !titleEl) return;

        var year = calDate.getFullYear();
        var month = calDate.getMonth();
        titleEl.textContent = monthNames[month] + ' ' + year;

        var firstDayIdx = new Date(year, month, 1).getDay();
        var totalDays = new Date(year, month + 1, 0).getDate();
        var prevMonthTotalDays = new Date(year, month, 0).getDate();

        var today = new Date();
        var todayYmd = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

        var events = getFilteredEvents();
        var eventsByDate = {};
        events.forEach(function(ev) {
            var d = ev.date;
            if (!eventsByDate[d]) eventsByDate[d] = [];
            eventsByDate[d].push(ev);
        });

        tbody.innerHTML = '';
        var dayCounter = 1;
        var nextMonthDay = 1;

        for (var row = 0; row < 6; row++) {
            var tr = document.createElement('tr');
            var rowHasDays = false;

            for (var col = 0; col < 7; col++) {
                var td = document.createElement('td');
                td.style.height = '85px';
                td.style.verticalAlign = 'top';
                td.style.padding = '6px';
                td.style.position = 'relative';
                td.style.cursor = 'pointer';

                var currentYmd = '';

                if (row === 0 && col < firstDayIdx) {
                    var prevDayNum = prevMonthTotalDays - (firstDayIdx - col - 1);
                    td.className = 'text-muted bg-light opacity-50';
                    td.innerHTML = '<span class="small fw-semibold">' + prevDayNum + '</span>';
                    td.style.cursor = 'default';
                } else if (dayCounter > totalDays) {
                    td.className = 'text-muted bg-light opacity-50';
                    td.innerHTML = '<span class="small fw-semibold">' + nextMonthDay + '</span>';
                    td.style.cursor = 'default';
                    nextMonthDay++;
                } else {
                    rowHasDays = true;
                    currentYmd = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(dayCounter).padStart(2, '0');
                    var isToday = (currentYmd === todayYmd);
                    var isSelected = (currentYmd === selectedYmd);

                    if (isSelected) {
                        td.className = 'bg-primary-subtle border-primary';
                    } else if (isToday) {
                        td.className = 'bg-light-subtle';
                    }

                    var dayEvents = eventsByDate[currentYmd] || [];
                    var badgeHtml = '';
                    if (dayEvents.length > 0) {
                        var pillClass = 'bg-primary';
                        if (dayEvents[0].is_video) pillClass = 'bg-info text-white';
                        badgeHtml = '<div class="mt-1 d-flex flex-column gap-1">' +
                            '<span class="badge ' + pillClass + ' rounded-pill px-1.5 py-0.5 text-truncate" style="font-size: 10px; max-width: 100%;">' +
                            dayEvents.length + ' Visit' + (dayEvents.length > 1 ? 's' : '') +
                            '</span></div>';
                    }

                    var dayHeaderBadge = isToday
                        ? '<span class="badge bg-primary text-white rounded-circle p-1" style="width: 22px; height: 22px; line-height: 14px;">' + dayCounter + '</span>'
                        : '<span class="fw-bold ' + (isSelected ? 'text-primary' : 'text-dark') + '" style="font-size: 13px;">' + dayCounter + '</span>';

                    td.innerHTML = '<div class="d-flex justify-content-between align-items-center">' +
                        dayHeaderBadge +
                        (dayEvents.length > 0 ? '<span class="rounded-circle d-inline-block bg-primary" style="width: 6px; height: 6px;"></span>' : '') +
                        '</div>' + badgeHtml;

                    (function(ymd) {
                        td.addEventListener('click', function() {
                            selectedYmd = ymd;
                            renderMonthGrid();
                            renderSelectedDaySchedule();
                        });
                    })(currentYmd);

                    dayCounter++;
                }

                tr.appendChild(td);
            }

            if (rowHasDays || row === 0) {
                tbody.appendChild(tr);
            }
        }

        renderSelectedDaySchedule();
    }

    function renderSelectedDaySchedule() {
        var headerEl = document.getElementById('dior-doc-cal-selected-header');
        var countEl = document.getElementById('dior-doc-cal-selected-count');
        var bodyEl = document.getElementById('dior-doc-cal-selected-body');
        if (!headerEl || !countEl || !bodyEl) return;

        var parts = selectedYmd.split('-');
        if (parts.length === 3) {
            var dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            var dayName = dayNames[dt.getDay()];
            var monthName = monthNames[dt.getMonth()];
            headerEl.textContent = dayName + ', ' + monthName + ' ' + parseInt(parts[2]) + ', ' + parts[0];
        }

        var events = getFilteredEvents().filter(function(item) {
            return item.date === selectedYmd;
        });

        countEl.textContent = events.length + ' Visit' + (events.length !== 1 ? 's' : '');

        if (events.length === 0) {
            bodyEl.innerHTML = '<div class="text-center py-5 text-secondary">' +
                '<i class="far fa-calendar-check fs-2 d-block mb-2 opacity-25"></i>' +
                '<div class="fw-semibold small">No appointments scheduled</div>' +
                '<div class="text-muted" style="font-size: 11px;">Select another date or click "Add Appointment" above.</div>' +
                '</div>';
            return;
        }

        var html = '<div class="d-flex flex-column gap-3">';
        events.forEach(function(ev) {
            var statusBadge = 'bg-primary-subtle text-primary border-primary-subtle';
            var s = ev.status.toLowerCase();
            if (s === 'completed') statusBadge = 'bg-success-subtle text-success border-success-subtle';
            else if (s === 'pending') statusBadge = 'bg-warning-subtle text-warning border-warning-subtle';
            else if (s === 'cancelled') statusBadge = 'bg-danger-subtle text-danger border-danger-subtle';

            var formatBadge = ev.is_video ? 'bg-info-subtle text-info border-info-subtle' : 'bg-light text-secondary border-light-subtle';

            var joinBtn = '';
            if (ev.is_video && ev.join_url) {
                joinBtn = '<a href="' + ev.join_url + '" target="_blank" class="btn btn-sm btn-primary rounded-3 fw-semibold py-1 px-2.5 shadow-sm d-inline-flex align-items-center gap-1.5 mt-2">' +
                    '<i class="fas fa-video"></i> Join HD Consultation' +
                    '</a>';
            }

            html += '<div class="card border border-light-subtle rounded-3 p-3 bg-white shadow-none">' +
                '<div class="d-flex align-items-start justify-content-between gap-2 mb-2">' +
                '<div>' +
                '<div class="fw-bold text-dark fs-6">' + ev.patient_name + '</div>' +
                '<div class="text-secondary small">' + ev.condition + '</div>' +
                '</div>' +
                '<span class="badge border rounded-pill px-2 py-0.5 small ' + statusBadge + '">' + ev.status + '</span>' +
                '</div>' +
                '<div class="d-flex align-items-center gap-2 small text-secondary mb-1">' +
                '<i class="far fa-clock text-primary"></i> <span>' + ev.time + '</span>' +
                '</div>' +
                '<div class="d-flex align-items-center gap-2 small text-secondary mb-2">' +
                '<i class="fas fa-user-doctor text-secondary"></i> <span>' + ev.doctor_name + '</span>' +
                '</div>' +
                '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">' +
                '<span class="badge border rounded-pill px-2 py-0.5 ' + formatBadge + '">' + ev.visit_type + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary rounded-3 py-0.5 px-2 small" onclick="if(typeof diorDocSwitchTab===\'function\'){diorDocSwitchTab(\'doc-appointments-view\');}">' +
                'View' +
                '</button>' +
                '</div>' +
                joinBtn +
                '</div>';
        });
        html += '</div>';

        bodyEl.innerHTML = html;
    }

    function renderAgendaList() {
        var agendaListEl = document.getElementById('dior-doc-cal-agenda-list');
        if (!agendaListEl) return;

        var events = getFilteredEvents();
        if (events.length === 0) {
            agendaListEl.innerHTML = '<div class="text-center py-5 text-secondary card border-0 bg-light rounded-4">' +
                '<i class="fas fa-calendar-xmark fs-2 d-block mb-2 opacity-50"></i>' +
                '<div class="fw-semibold">No appointments match the selected filters.</div>' +
                '</div>';
            return;
        }

        // Group by date
        var grouped = {};
        events.forEach(function(ev) {
            if (!grouped[ev.date]) grouped[ev.date] = [];
            grouped[ev.date].push(ev);
        });

        var dates = Object.keys(grouped).sort();
        var html = '';

        dates.forEach(function(d) {
            var parts = d.split('-');
            var dtStr = d;
            if (parts.length === 3) {
                var dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                dtStr = dayNames[dt.getDay()] + ', ' + monthNames[dt.getMonth()] + ' ' + parseInt(parts[2]) + ', ' + parts[0];
            }

            html += '<div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-2">' +
                '<div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">' +
                '<h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">' +
                '<i class="far fa-calendar text-primary"></i> ' + dtStr +
                '</h6>' +
                '<span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 small">' + grouped[d].length + ' Visits</span>' +
                '</div>' +
                '<div class="d-flex flex-column gap-2">';

            grouped[d].forEach(function(ev) {
                var statusBadge = 'bg-primary-subtle text-primary border-primary-subtle';
                var s = ev.status.toLowerCase();
                if (s === 'completed') statusBadge = 'bg-success-subtle text-success border-success-subtle';
                else if (s === 'pending') statusBadge = 'bg-warning-subtle text-warning border-warning-subtle';
                else if (s === 'cancelled') statusBadge = 'bg-danger-subtle text-danger border-danger-subtle';

                html += '<div class="d-flex align-items-center justify-content-between p-2.5 rounded-3 border bg-light-subtle flex-wrap gap-2">' +
                    '<div class="d-flex align-items-center gap-3">' +
                    '<div class="rounded-3 bg-white border p-2 text-center" style="min-width: 90px;">' +
                    '<div class="fw-bold text-dark small">' + ev.time + '</div>' +
                    '<div class="text-secondary opacity-75" style="font-size: 10px;">' + ev.visit_type + '</div>' +
                    '</div>' +
                    '<div>' +
                    '<div class="fw-bold text-dark">' + ev.patient_name + '</div>' +
                    '<div class="text-secondary small">' + ev.condition + ' • ' + ev.doctor_name + '</div>' +
                    '</div>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-2">' +
                    '<span class="badge border rounded-pill px-2 py-1 small ' + statusBadge + '">' + ev.status + '</span>' +
                    (ev.is_video && ev.join_url ? '<a href="' + ev.join_url + '" target="_blank" class="btn btn-sm btn-primary rounded-3 py-1 px-2.5"><i class="fas fa-video"></i></a>' : '') +
                    '</div>' +
                    '</div>';
            });

            html += '</div></div>';
        });

        agendaListEl.innerHTML = html;
    }

    function initDoctorCalendar() {
        var prevBtn = document.getElementById('dior-doc-cal-prev');
        if (prevBtn && !prevBtn.__diorBound) {
            prevBtn.__diorBound = true;
            prevBtn.addEventListener('click', function() {
                calDate.setMonth(calDate.getMonth() - 1);
                renderMonthGrid();
            });
        }

        var nextBtn = document.getElementById('dior-doc-cal-next');
        if (nextBtn && !nextBtn.__diorBound) {
            nextBtn.__diorBound = true;
            nextBtn.addEventListener('click', function() {
                calDate.setMonth(calDate.getMonth() + 1);
                renderMonthGrid();
            });
        }

        var todayBtn = document.getElementById('dior-doc-cal-today');
        if (todayBtn && !todayBtn.__diorBound) {
            todayBtn.__diorBound = true;
            todayBtn.addEventListener('click', function() {
                calDate = new Date();
                var today = new Date();
                selectedYmd = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
                renderMonthGrid();
            });
        }

        var typeSelect = document.getElementById('dior-doc-cal-type-filter');
        if (typeSelect && !typeSelect.__diorBound) {
            typeSelect.__diorBound = true;
            typeSelect.addEventListener('change', function() {
                typeFilter = this.value;
                if (viewMode === 'month') renderMonthGrid();
                else renderAgendaList();
            });
        }

        var statusSelect = document.getElementById('dior-doc-cal-status-filter');
        if (statusSelect && !statusSelect.__diorBound) {
            statusSelect.__diorBound = true;
            statusSelect.addEventListener('change', function() {
                statusFilter = this.value;
                if (viewMode === 'month') renderMonthGrid();
                else renderAgendaList();
            });
        }

        var viewMonthBtn = document.getElementById('dior-doc-cal-view-month');
        var viewAgendaBtn = document.getElementById('dior-doc-cal-view-agenda');
        var monthWrap = document.getElementById('dior-doc-cal-month-wrap');
        var agendaWrap = document.getElementById('dior-doc-cal-agenda-wrap');

        if (viewMonthBtn && !viewMonthBtn.__diorBound) {
            viewMonthBtn.__diorBound = true;
            viewMonthBtn.addEventListener('click', function() {
                viewMode = 'month';
                viewMonthBtn.classList.add('btn-primary', 'active');
                viewMonthBtn.classList.remove('btn-outline-secondary');
                if (viewAgendaBtn) {
                    viewAgendaBtn.classList.remove('btn-primary', 'active');
                    viewAgendaBtn.classList.add('btn-outline-secondary');
                }
                if (monthWrap) monthWrap.style.display = 'block';
                if (agendaWrap) agendaWrap.style.display = 'none';
                renderMonthGrid();
            });
        }

        if (viewAgendaBtn && !viewAgendaBtn.__diorBound) {
            viewAgendaBtn.__diorBound = true;
            viewAgendaBtn.addEventListener('click', function() {
                viewMode = 'agenda';
                viewAgendaBtn.classList.add('btn-primary', 'active');
                viewAgendaBtn.classList.remove('btn-outline-secondary');
                if (viewMonthBtn) {
                    viewMonthBtn.classList.remove('btn-primary', 'active');
                    viewMonthBtn.classList.add('btn-outline-secondary');
                }
                if (monthWrap) monthWrap.style.display = 'none';
                if (agendaWrap) agendaWrap.style.display = 'block';
                renderAgendaList();
            });
        }

        renderMonthGrid();
    }

    window.diorRenderDoctorCalendar = function() {
        initDoctorCalendar();
        if (viewMode === 'month') renderMonthGrid();
        else renderAgendaList();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDoctorCalendar);
    } else {
        setTimeout(initDoctorCalendar, 80);
    }
})();
</script>
