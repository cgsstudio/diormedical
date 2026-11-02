<?php
/**
 * Dior Medical — Doctor Appointment Calendar (Doctor Dashboard Design Matching)
 * 100% Native Bootstrap 5 + FullCalendar Standard
 */
if (!defined('ABSPATH')) { exit; }

global $wpdb;

// Determine current user and whether they are an admin
$_cal_user_id  = get_current_user_id();
$_cal_is_admin = current_user_can('administrator') || current_user_can('manage_options');

// Fetch appointments: admins see all, doctors see only their own
if ($_cal_is_admin) {
    $raw_appts = $wpdb->get_results("SELECT a.*, u.display_name AS doc_name, u.user_email AS doc_email 
        FROM {$wpdb->prefix}dior_appointments a 
        LEFT JOIN {$wpdb->users} u ON u.ID = a.doctor_id 
        WHERE a.is_deleted = 0 
        ORDER BY a.appt_date ASC", ARRAY_A);
} else {
    $raw_appts = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, u.display_name AS doc_name, u.user_email AS doc_email 
        FROM {$wpdb->prefix}dior_appointments a 
        LEFT JOIN {$wpdb->users} u ON u.ID = a.doctor_id 
        WHERE a.is_deleted = 0 AND a.doctor_id = %d
        ORDER BY a.appt_date ASC",
        $_cal_user_id
    ), ARRAY_A);
}

if (empty($raw_appts)) {
    $raw_appts = [];
}

// Fetch active doctors for filter
$all_doctors = get_users(['role' => 'doctor', 'number' => 50, 'orderby' => 'ID', 'order' => 'ASC']);

// Format appointments into FullCalendar events
$events_for_js = [];
foreach ($raw_appts as $app) {
    $status_raw = $app['status'] ?? 'Success';
    $status = strtolower($status_raw);
    $pname = !empty($app['patient_name']) ? $app['patient_name'] : 'Patient';
    $cond = !empty($app['condition_name']) ? $app['condition_name'] : (!empty($app['department']) ? $app['department'] : 'Clinical Visit');
    $dname = !empty($app['doc_name']) ? $app['doc_name'] : 'Dr. Attending Physician';
    if (stripos($dname, 'Dr.') === false && stripos($dname, 'Dr ') === false) {
        $dname = 'Dr. ' . $dname;
    }
    $vtype = !empty($app['visit_type']) ? $app['visit_type'] : (!empty($app['type']) ? $app['type'] : 'In-Clinic');
    $time_raw = !empty($app['appt_time']) ? $app['appt_time'] : '09:00 AM';
    $date_raw = !empty($app['appt_date']) ? $app['appt_date'] : current_time('Y-m-d');
    $clean_time = trim(explode('-', $time_raw)[0]);
    $time_ts = strtotime($clean_time);
    $time_iso = $time_ts ? date('H:i:s', $time_ts) : '09:00:00';

    // Color code matching Doctor Dashboard palette
    $bg_color = '#2563EB'; // Blue (Success)
    if (stripos($vtype, 'video') !== false || stripos($vtype, 'telehealth') !== false) {
        $bg_color = '#0284C7'; // Cyan (Telehealth)
    } elseif ($status === 'completed') {
        $bg_color = '#10B981'; // Green (Completed)
    } elseif ($status === 'pending') {
        $bg_color = '#F59E0B'; // Amber (Pending)
    } elseif ($status === 'cancelled') {
        $bg_color = '#EF4444'; // Red (Cancelled)
    }

    // Category mapping for left calendar filter
    $cat = 'Work';
    $doc_id = (int)($app['doctor_id'] ?? 0);
    if ($doc_id === 36) {
        $cat = 'Important';
    } elseif ($doc_id === 37) {
        $cat = 'Travel';
    } elseif ($doc_id === 38) {
        $cat = 'Personal';
    } elseif (stripos($vtype, 'video') !== false) {
        $cat = 'Work';
    } else {
        $cat = 'Friends';
    }

    $events_for_js[] = [
        'id'              => $app['appt_uid'] ?? ($app['id'] ?? uniqid('APT-')),
        'title'           => $pname . ' - ' . $cond,
        'start'           => $date_raw . 'T' . $time_iso,
        'backgroundColor' => $bg_color,
        'borderColor'     => 'transparent',
        'textColor'       => '#ffffff',
        'extendedProps'   => [
            'doctor_id'    => $doc_id,
            'doctor_name'  => $dname,
            'patient_name' => $pname,
            'condition'    => $cond,
            'visit_type'   => $vtype,
            'time'         => $time_raw,
            'date'         => $date_raw,
            'status'       => ucfirst($status_raw),
            'category'     => $cat,
            'join_url'     => $app['join_url'] ?? '',
        ]
    ];
}

$events_json = json_encode($events_for_js);
?>

<section class="dior-tab-panel" id="tab-doc-appointments" style="display: none;">

    <!-- Breadcrumb & Page Header -->
    <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="mb-1 text-dark fw-bold fs-4 d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <i class="fas fa-calendar-days fs-6"></i>
                </span>
                Appointment Calendar
            </h4>
            <p class="text-secondary small mb-0">Clinical consultations, telemedicine visits, and daily schedule management.</p>
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

    <!-- Doctor Dashboard Calendar Structure -->
    <div class="section-body">
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white">
            <div class="row g-4 align-items-start m-0">

                <!-- Left Sidebar Controls (Doctor Dashboard Design) -->
                <div class="col-12 col-lg-3 col-xl-2 p-0 pe-lg-3">
                    
                    <button type="button" class="btn btn-primary w-100 fw-semibold rounded-3 py-2 px-3 shadow-sm d-inline-flex align-items-center justify-content-center gap-2 mb-4"
                        onclick="if (typeof diorOpenDocBookerModal === 'function') { diorOpenDocBookerModal(); } else if (typeof diorAdminSwitchTab === 'function') { diorAdminSwitchTab('doc-appointments-book'); } else if (typeof diorDocSwitchTab === 'function') { diorDocSwitchTab('doc-appointments-book'); }">
                        <i class="fas fa-plus"></i>
                        <span>Add Event</span>
                    </button>
                    
                    <h5 class="fs-6 fw-bold text-dark mb-3">My Calendars</h5>
                    
                    <div class="filter-container mb-4">
                        <ul class="filterCheck list-unstyled d-flex flex-column gap-2 p-0 m-0">
                            <li class="d-flex align-items-center gap-2 small text-secondary fw-semibold">
                                <input type="checkbox" checked value="Work" id="cal-filter-work" onchange="diorFilterCalEvents()">
                                <span>Work (Clinic Visits)</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 small text-secondary fw-semibold">
                                <input type="checkbox" checked value="Personal" id="cal-filter-personal" onchange="diorFilterCalEvents()">
                                <span>Personal</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 small text-secondary fw-semibold">
                                <input type="checkbox" checked value="Important" id="cal-filter-important" onchange="diorFilterCalEvents()">
                                <span>Important (Surgeries)</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 small text-secondary fw-semibold">
                                <input type="checkbox" checked value="Travel" id="cal-filter-travel" onchange="diorFilterCalEvents()">
                                <span>Travel (Consults)</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 small text-secondary fw-semibold">
                                <input type="checkbox" checked value="Friends" id="cal-filter-friends" onchange="diorFilterCalEvents()">
                                <span>Friends / Routine</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Filter by Doctor -->
                    <h5 class="fs-6 fw-bold text-dark mb-2">Filter Provider</h5>
                    <select class="form-select form-select-sm rounded-3 shadow-sm text-dark fw-semibold mb-4" id="dior-cal-doc-filter" onchange="diorFilterCalByDoctor(this.value)">
                        <option value="all">All Healthcare Providers</option>
                        <?php if (!empty($all_doctors)): ?>
                            <?php foreach ($all_doctors as $doc): ?>
                                <option value="<?php echo esc_attr($doc->ID); ?>">
                                    <?php 
                                    $n = $doc->display_name;
                                    if (stripos($n, 'Dr.') === false && stripos($n, 'Dr ') === false) $n = 'Dr. ' . $n;
                                    echo esc_html($n);
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <!-- Legend -->
                    <h5 class="fs-6 fw-bold text-dark mb-2">Status Legend</h5>
                    <div class="d-flex flex-column gap-2 small">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 10px; height: 10px; background: #2563EB;"></span>
                            <span class="text-secondary">Success</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 10px; height: 10px; background: #0284C7;"></span>
                            <span class="text-secondary">Telehealth (HD Video)</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 10px; height: 10px; background: #10B981;"></span>
                            <span class="text-secondary">Completed</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 10px; height: 10px; background: #F59E0B;"></span>
                            <span class="text-secondary">In Queue</span>
                        </div>
                    </div>

                </div>

                <!-- Right Calendar Content (Doctor Dashboard FullCalendar Matrix) -->
                <div class="col-12 col-lg-9 col-xl-10 p-0 ps-lg-3">
                    
                    <!-- Calendar Toolbar -->
                    <div class="cal-toolbar d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        
                        <!-- Left Navigation: Prev, Next, Today -->
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="cal-nav-btn btn" id="dior-cal-prev-btn" title="Previous Month">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button type="button" class="cal-nav-btn btn" id="dior-cal-next-btn" title="Next Month">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <button type="button" class="cal-today-btn btn fw-semibold" id="dior-cal-today-btn">today</button>
                        </div>

                        <!-- Center Month Title -->
                        <h3 class="mb-0 fw-bold text-dark fs-5 text-center" id="dior-cal-month-title">
                            October 2026
                        </h3>

                        <!-- Right View Toggles (month, week, day, list) -->
                        <div class="cal-view-toggles d-flex align-items-center gap-1">
                            <button type="button" class="cal-view-btn active btn fw-semibold" data-view="dayGridMonth">month</button>
                            <button type="button" class="cal-view-btn btn fw-semibold" data-view="timeGridWeek">week</button>
                            <button type="button" class="cal-view-btn btn fw-semibold" data-view="timeGridDay">day</button>
                            <button type="button" class="cal-view-btn btn fw-semibold" data-view="listWeek">list</button>
                        </div>

                    </div>

                    <!-- Calendar Grid Matrix -->
                    <div class="dior-cal-grid-wrapper rounded-3 overflow-hidden border bg-white shadow-sm">
                        <div id="dior-calendar-container" style="width: 100%; min-height: 600px;"></div>
                    </div>

                </div>

            </div>
        </div>
    </div>

</section>

<!-- FullCalendar Engine & Controllers -->
<script>
window.diorRawEvents = <?php echo $events_json; ?>;

(function() {
    'use strict';

    // Inject FullCalendar CSS if missing
    if (!document.getElementById('fullcalendar-css')) {
        var link = document.createElement('link');
        link.id = 'fullcalendar-css';
        link.rel = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css';
        document.head.appendChild(link);
    }

    // Load FullCalendar JS
    if (typeof FullCalendar === 'undefined' && !window.fcLoadingDior) {
        window.fcLoadingDior = true;
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js';
        script.onload = function() {
            initDiorCalendar();
        };
        document.head.appendChild(script);
    } else {
        initDiorCalendar();
    }

    function initDiorCalendar() {
        if (typeof FullCalendar === 'undefined') {
            setTimeout(initDiorCalendar, 100);
            return;
        }

        var calEl = document.getElementById('dior-calendar-container');
        if (!calEl || calEl.dataset.inited === 'true') return;
        calEl.dataset.inited = 'true';

        var calendar = new FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth',
            initialDate: '2026-10-21',
            firstDay: 0,
            dayMaxEvents: 3,
            headerToolbar: false,
            height: 'auto',
            eventTimeFormat: {
                hour: 'numeric',
                minute: '2-digit',
                meridiem: 'short'
            },
            events: window.diorRawEvents,
            datesSet: function(info) {
                var titleEl = document.getElementById('dior-cal-month-title');
                if (titleEl) {
                    titleEl.textContent = info.view.title;
                }
            },
            eventClick: function(info) {
                var p = info.event.extendedProps || {};
                var isVideo = (p.visit_type && p.visit_type.toLowerCase().indexOf('video') !== -1) || (p.join_url && p.join_url.length > 5);
                
                var joinBtnHtml = isVideo 
                    ? `<a href="${p.join_url || '#'}" target="_blank" class="btn btn-primary rounded-3 w-100 fw-semibold mt-3 py-2 shadow-sm"><i class="fas fa-video me-1"></i> Join Video Consultation</a>`
                    : '';

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: info.event.title,
                        html: `
                            <div class="text-start p-2">
                                <div class="mb-2"><strong class="text-secondary small text-uppercase">Patient:</strong> <span class="fw-bold text-dark">${p.patient_name || 'Patient'}</span></div>
                                <div class="mb-2"><strong class="text-secondary small text-uppercase">Attending Physician:</strong> <span class="fw-bold text-dark">${p.doctor_name || 'Doctor'}</span></div>
                                <div class="mb-2"><strong class="text-secondary small text-uppercase">Condition:</strong> <span class="fw-bold text-dark">${p.condition || 'General Visit'}</span></div>
                                <div class="mb-2"><strong class="text-secondary small text-uppercase">Date & Time:</strong> <span class="fw-bold text-dark">${p.date || ''} at ${p.time || ''}</span></div>
                                <div class="mb-2"><strong class="text-secondary small text-uppercase">Visit Format:</strong> <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">${p.visit_type || 'In-Clinic'}</span></div>
                                <div class="mb-2"><strong class="text-secondary small text-uppercase">Status:</strong> <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">${p.status || 'Success'}</span></div>
                                ${joinBtnHtml}
                            </div>
                        `,
                        showCloseButton: true,
                        confirmButtonText: 'Close',
                        confirmButtonColor: '#2D6EB4',
                        customClass: { popup: 'rounded-4 border-0 shadow-lg' }
                    });
                } else {
                    alert(`${info.event.title}\nDoctor: ${p.doctor_name}\nTime: ${p.time}\nStatus: ${p.status}`);
                }
            }
        });

        calendar.render();
        window.diorGlobalFullCalendar = calendar;

        // Toolbar Buttons
        var prevBtn = document.getElementById('dior-cal-prev-btn');
        if (prevBtn) {
            prevBtn.onclick = function() { calendar.prev(); };
        }

        var nextBtn = document.getElementById('dior-cal-next-btn');
        if (nextBtn) {
            nextBtn.onclick = function() { calendar.next(); };
        }

        var todayBtn = document.getElementById('dior-cal-today-btn');
        if (todayBtn) {
            todayBtn.onclick = function() { calendar.today(); };
        }

        // View Toggles
        var viewBtns = document.querySelectorAll('.cal-view-toggles .cal-view-btn');
        viewBtns.forEach(function(btn) {
            btn.onclick = function() {
                viewBtns.forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                var v = this.getAttribute('data-view') || 'dayGridMonth';
                calendar.changeView(v);
            };
        });

        // Resize on tab display change
        var tabPanels = [
            document.getElementById('tab-doc-appointments'),
            document.getElementById('tab-appointment-calendar')
        ];
        tabPanels.forEach(function(panel) {
            if (panel) {
                new MutationObserver(function(mutations) {
                    mutations.forEach(function(m) {
                        if (m.target.style.display !== 'none' && window.diorGlobalFullCalendar) {
                            setTimeout(function() {
                                window.diorGlobalFullCalendar.updateSize();
                            }, 60);
                        }
                    });
                }).observe(panel, { attributes: true, attributeFilter: ['style', 'class'] });
            }
        });
    }

    // Filter functions
    window.diorFilterCalEvents = function() {
        if (!window.diorGlobalFullCalendar) return;
        var checkedCats = [];
        if (document.getElementById('cal-filter-work')?.checked) checkedCats.push('Work');
        if (document.getElementById('cal-filter-personal')?.checked) checkedCats.push('Personal');
        if (document.getElementById('cal-filter-important')?.checked) checkedCats.push('Important');
        if (document.getElementById('cal-filter-travel')?.checked) checkedCats.push('Travel');
        if (document.getElementById('cal-filter-friends')?.checked) checkedCats.push('Friends');

        var docFilter = document.getElementById('dior-cal-doc-filter')?.value || 'all';

        var filtered = window.diorRawEvents.filter(function(ev) {
            var cat = (ev.extendedProps && ev.extendedProps.category) || 'Work';
            var catMatch = checkedCats.indexOf(cat) !== -1;
            var docId = (ev.extendedProps && ev.extendedProps.doctor_id) || 0;
            var docMatch = (docFilter === 'all') || (String(docId) === String(docFilter));
            return catMatch && docMatch;
        });

        window.diorGlobalFullCalendar.removeAllEvents();
        window.diorGlobalFullCalendar.addEventSource(filtered);
    };

    window.diorFilterCalByDoctor = function(docId) {
        window.diorFilterCalEvents();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDiorCalendar);
    } else {
        initDiorCalendar();
    }
})();
</script>
