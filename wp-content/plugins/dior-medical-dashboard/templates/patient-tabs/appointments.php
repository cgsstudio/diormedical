<section class="dior-tab-panel" id="tab-appointments">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" id="dior-appt-main-title" style="font-size:20px;font-weight:700;">Appointment
                Calendar</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span id="dior-appt-breadcrumb-title">Appointment Calendar</span></li>
            </ul>
        </div>
    </div>

    <?php
    // Robust date normalizer: handles Y-m-d, d-m-Y, m-d-Y, d/m/Y etc.
    if (!function_exists('dior_normalize_date')) {
        function dior_normalize_date($raw)
        {
            if (empty($raw))
                return '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw))
                return $raw;
            foreach (['d-m-Y', 'd/m/Y', 'd.m.Y', 'm-d-Y', 'm/d/Y'] as $fmt) {
                $dt = DateTime::createFromFormat($fmt, $raw);
                if ($dt)
                    return $dt->format('Y-m-d');
            }
            $ts = strtotime($raw);
            return $ts ? date('Y-m-d', $ts) : '';
        }
    }


    // Prepare appointment data as JSON for JS calendar
    $cal_apt_dates = [];
    $apt_total = count($appointments);
    $apt_upcoming = 0;
    $apt_completed = 0;
    $apt_video = 0;
    $today_ymd = current_time('Y-m-d');
    foreach ($appointments as $idx => $apt) {
        $a_date = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
        $a_status = !empty($apt['status']) ? $apt['status'] : 'Scheduled';
        $a_type = !empty($apt['type']) ? $apt['type'] : (!empty($apt['visit_type']) ? $apt['visit_type'] : 'Video Visit');
        $status_lc = strtolower($a_status);
        $type_lc = strtolower($a_type);
        if (strpos($type_lc, 'video') !== false || strpos($type_lc, 'telehealth') !== false) {
            $apt_video++;
        }
        if (in_array($status_lc, ['confirmed', 'scheduled', 'in-queue', 'pending', 'active']))
            $apt_upcoming++;
        if (in_array($status_lc, ['completed', 'done', 'past']))
            $apt_completed++;
        if ($a_date) {
            $ymd = dior_normalize_date($a_date);
            if ($ymd)
                $cal_apt_dates[$ymd] = true;
        }
    }
    $cal_dates_json = json_encode(array_keys($cal_apt_dates));

    // Build appointment cards JSON for JS filtering
    $apt_cards = [];

    foreach ($appointments as $idx => $apt) {
        $a_id = !empty($apt['id']) ? $apt['id'] : (!empty($apt['appt_uid']) ? $apt['appt_uid'] : 'APT-' . (1000 + $idx));
        $a_doc = !empty($apt['provider']) ? $apt['provider'] : (!empty($apt['doctor_name']) ? $apt['doctor_name'] : 'Dr. Marcus Sterling, DO');
        $a_spec = !empty($apt['provider_spec']) ? $apt['provider_spec'] : 'Telehealth Urgent Care';
        $a_date_raw = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
        $a_time = !empty($apt['time']) ? $apt['time'] : (!empty($apt['appt_time']) ? $apt['appt_time'] : '');
        $a_status = !empty($apt['status']) ? $apt['status'] : 'Scheduled';
        $a_type = !empty($apt['type']) ? $apt['type'] : (!empty($apt['visit_type']) ? $apt['visit_type'] : 'Video Visit');
        $a_join = !empty($apt['join_url']) ? $apt['join_url'] : 'https://zoom.us/join';
        $a_notes = !empty($apt['notes']) ? $apt['notes'] : '';
        $a_condition = !empty($apt['condition']) ? $apt['condition'] : (!empty($apt['condition_name']) ? $apt['condition_name'] : 'Telehealth Consultation');
        $a_ymd = dior_normalize_date($a_date_raw);
        $apt_cards[] = [
            'id' => $a_id,
            'doc' => $a_doc,
            'spec' => $a_spec,
            'date' => $a_date_raw,
            'ymd' => $a_ymd,
            'time' => $a_time,
            'status' => $a_status,
            'type' => $a_type,
            'join_url' => $a_join,
            'notes' => $a_notes,
            'condition' => $a_condition,
        ];
    }
    $apt_cards_json = json_encode($apt_cards, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    ?>

    <!-- Appointment Content Area -->
    <div class="medidash-appt-wrap">

        <!-- 0. Appointment Calendar (Primary Dynamic Bootstrap 5 Calendar) -->
        <div class="dior-subtab-panel" id="dior-subtab-calendar" style="display: block;">

            <!-- Quick Stat KPI Bar (Bootstrap 5 Cards) -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-sm-6 col-12">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Total Consultations</span>
                                <h3 class="fw-bold mb-0 text-dark mt-1"><?php echo esc_html($apt_total); ?></h3>
                                <span class="badge bg-primary-subtle text-primary rounded-pill mt-2 px-2 py-1 small">
                                    <i class="fas fa-calendar-check me-1"></i> Patient Records
                                </span>
                            </div>
                            <div class="rounded-4 d-flex align-items-center justify-content-center"
                                style="width: 52px; height: 52px; background: rgba(79, 70, 229, 0.1); color: #4f46e5; font-size: 22px;">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 col-12">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Upcoming Visits</span>
                                <h3 class="fw-bold mb-0 text-dark mt-1"><?php echo esc_html($apt_upcoming); ?></h3>
                                <span class="badge bg-info-subtle text-info rounded-pill mt-2 px-2 py-1 small">
                                    <i class="fas fa-clock me-1"></i> Scheduled
                                </span>
                            </div>
                            <div class="rounded-4 d-flex align-items-center justify-content-center"
                                style="width: 52px; height: 52px; background: rgba(14, 165, 233, 0.1); color: #0ea5e9; font-size: 22px;">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 col-12">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Telehealth Video</span>
                                <h3 class="fw-bold mb-0 text-dark mt-1"><?php echo esc_html($apt_video); ?></h3>
                                <span class="badge bg-success-subtle text-success rounded-pill mt-2 px-2 py-1 small">
                                    <i class="fas fa-video me-1"></i> HD Live Room
                                </span>
                            </div>
                            <div class="rounded-4 d-flex align-items-center justify-content-center"
                                style="width: 52px; height: 52px; background: rgba(16, 185, 129, 0.1); color: #10b981; font-size: 22px;">
                                <i class="fas fa-video"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 col-12">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Completed Visits</span>
                                <h3 class="fw-bold mb-0 text-dark mt-1"><?php echo esc_html($apt_completed); ?></h3>
                                <span
                                    class="badge bg-secondary-subtle text-secondary rounded-pill mt-2 px-2 py-1 small">
                                    <i class="fas fa-circle-check me-1"></i> Finalized
                                </span>
                            </div>
                            <div class="rounded-4 d-flex align-items-center justify-content-center"
                                style="width: 52px; height: 52px; background: rgba(100, 116, 139, 0.1); color: #64748b; font-size: 22px;">
                                <i class="fas fa-circle-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar Container Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
                <!-- Toolbar Header -->
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                        <!-- Navigation (Prev, Month Title, Next, Today) -->
                        <div class="d-flex align-items-center gap-2">
                            <button type="button"
                                class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center"
                                id="dior-bs-cal-prev" style="width:36px;height:36px;" title="Previous Month">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <h3 class="mb-0 fw-bold text-dark fs-5 px-2 text-center" id="dior-bs-cal-title"
                                style="min-width: 170px;">
                                October 2026
                            </h3>
                            <button type="button"
                                class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center"
                                id="dior-bs-cal-next" style="width:36px;height:36px;" title="Next Month">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <button type="button"
                                class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold ms-1"
                                id="dior-bs-cal-today">
                                Today
                            </button>
                        </div>

                        <!-- Filters & Controls -->
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <!-- Type Filter -->
                            <div class="input-group input-group-sm" style="width: auto;">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i
                                        class="fas fa-filter"></i></span>
                                <select id="dior-bs-cal-type-filter"
                                    class="form-select form-select-sm border-start-0 ps-1" style="min-width: 125px;">
                                    <option value="all">All Visits</option>
                                    <option value="video">Video (HD)</option>
                                    <option value="phone">Phone Visit</option>
                                    <option value="clinic">In-Clinic</option>
                                </select>
                            </div>

                            <!-- Status Filter -->
                            <div class="input-group input-group-sm" style="width: auto;">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i
                                        class="fas fa-tag"></i></span>
                                <select id="dior-bs-cal-status-filter"
                                    class="form-select form-select-sm border-start-0 ps-1" style="min-width: 125px;">
                                    <option value="all">All Status</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                            <!-- View Mode Toggle (Month vs Agenda) -->
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-primary active fw-semibold"
                                    id="dior-bs-cal-view-month">
                                    <i class="fas fa-calendar-days me-1"></i> Month
                                </button>
                                <button type="button" class="btn btn-outline-secondary fw-semibold"
                                    id="dior-bs-cal-view-agenda">
                                    <i class="fas fa-list-ul me-1"></i> Agenda
                                </button>
                            </div>

                            <!-- Book Visit Button -->
                            <button type="button" onclick="diorOpenDocBookerModal();"
                                class="btn btn-primary btn-sm rounded-pill px-3 fw-bold d-inline-flex align-items-center gap-1 shadow-sm">
                                <i class="fas fa-calendar-plus"></i> Book Visit
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Main Content Area: 2 Columns Grid -->
                <div class="card-body p-3 p-lg-4">
                    <div class="row g-4">

                        <!-- Left Column: Calendar Grid / Agenda List (8 Cols) -->
                        <div class="col-lg-8 col-12">

                            <!-- 1. Month View (Bootstrap 5 Table Grid) -->
                            <div id="dior-bs-cal-month-wrap" class="dior-bs-calendar-container">
                                <div class="table-responsive rounded-3 border">
                                    <table class="table table-bordered mb-0 dior-bs-cal-table">
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
                                        <tbody id="dior-bs-cal-grid-body">
                                            <!-- Dynamically generated month days via JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 2. Agenda View (Initially Hidden) -->
                            <div id="dior-bs-cal-agenda-wrap" style="display: none;">
                                <div id="dior-bs-cal-agenda-list" class="d-flex flex-column gap-3">
                                    <!-- Dynamically generated agenda cards via JS -->
                                </div>
                            </div>

                        </div>

                        <!-- Right Column: Day Schedule & Quick Actions (4 Cols) -->
                        <div class="col-lg-4 col-12">
                            <div
                                class="card border border-light-subtle rounded-4 shadow-none bg-light h-100 d-flex flex-column">
                                <!-- Day Schedule Header -->
                                <div
                                    class="card-header bg-white border-bottom py-3 px-3 rounded-top-4 d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted small fw-bold text-uppercase"
                                            style="font-size: 11px;">Selected Date</span>
                                        <h5 class="mb-0 fw-bold text-dark fs-6 mt-1" id="dior-bs-cal-selected-header">
                                            Wednesday, Oct 14, 2026
                                        </h5>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-bold"
                                        id="dior-bs-cal-selected-count">
                                        0 Visits
                                    </span>
                                </div>

                                <!-- Day Schedule Body -->
                                <div class="card-body p-3 flex-grow-1 overflow-auto" id="dior-bs-cal-selected-body"
                                    style="max-height: 480px;">
                                    <!-- Populated dynamically via JS -->
                                </div>

                                <!-- Day Schedule Footer: Attending Doctor Banner & Legend -->
                                <div class="card-footer bg-white border-top py-3 px-3 rounded-bottom-4">
                                    <!-- Lead Physician Box -->
                                    <div class="p-2.5 rounded-3 mb-3"
                                        style="background: rgba(79, 70, 229, 0.05); border: 1px solid rgba(79, 70, 229, 0.15);">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                                style="width: 32px; height: 32px; background: #4f46e5; font-size: 12px;">
                                                MS
                                            </div>
                                            <div class="lh-sm">
                                                <div class="fw-bold text-dark" style="font-size: 13px;">Dr. Marcus
                                                    Sterling, DO</div>
                                                <small class="text-muted" style="font-size: 11px;">Lead Telehealth
                                                    Physician</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Legend Indicator -->
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 small text-muted"
                                        style="font-size: 11px;">
                                        <div class="d-flex align-items-center gap-1">
                                            <span
                                                style="width: 8px; height: 8px; border-radius: 50%; background: #4f46e5; display: inline-block;"></span>
                                            <span>Video</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-1">
                                            <span
                                                style="width: 8px; height: 8px; border-radius: 50%; background: #0ea5e9; display: inline-block;"></span>
                                            <span>Phone</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-1">
                                            <span
                                                style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
                                            <span>Confirmed</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-1">
                                            <span
                                                style="width: 8px; height: 8px; border-radius: 50%; background: #ef4444; display: inline-block;"></span>
                                            <span>Cancelled</span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </div>

        <!-- 1. Book Appointment -->
        <div class="dior-subtab-panel dior-ic-44a70a0420" id="dior-subtab-book">
            <!-- DocBooker Consultation Trigger Card -->
            <div class="dior-booking-banner-card"
                style="background: linear-gradient(135deg, #0b1120 0%, #1e293b 100%); border: 1px solid rgba(212, 175, 55, 0.4); border-radius: 12px; padding: 20px 24px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.25);">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div
                        style="width: 52px; height: 52px; border-radius: 12px; background: rgba(212, 175, 55, 0.15); border: 1px solid rgba(212, 175, 55, 0.45); display: flex; align-items: center; justify-content: center; font-size: 24px; color: #d4af37;">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span
                                style="display: inline-block; padding: 2px 8px; background: rgba(212, 175, 55, 0.2); color: #d4af37; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">DocBooker
                                Live</span>
                            <span
                                style="display: inline-block; padding: 2px 8px; background: rgba(16, 185, 129, 0.2); color: #10b981; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Single
                                Doctor: Dr. Marcus Sterling, DO</span>
                        </div>
                        <h4 style="margin: 0 0 4px 0; color: #ffffff; font-size: 17px; font-weight: 700;">Telehealth
                            Appointment Scheduler</h4>
                        <p style="margin: 0; color: #94a3b8; font-size: 13px;">Complete your HIPAA intake form below or
                            schedule your <strong>Video Visit (HD)</strong> or <strong>Phone Consultation</strong>
                            directly.</p>
                    </div>
                </div>
                <button type="button" onclick="diorOpenDocBookerModal();" class="dior-btn-gold-primary btn"
                    style="background: linear-gradient(135deg, #d4af37 0%, #b89628 100%); color: #0f172a; border: none; font-weight: 700; padding: 12px 22px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; box-shadow: 0 4px 14px rgba(212, 175, 55, 0.35); transition: all 0.2s ease;">
                    <i class="fa-solid fa-calendar-plus"></i> Schedule Appointment Now
                </button>
            </div>

            <div class="medidash-card">
                <div class="medidash-card-body">
                    <div class="dior-hipaa-embed-container">
                        <?php if (shortcode_exists('hipaatizer')): ?>
                            <?php echo do_shortcode('[hipaatizer id="01a12050-38e5-74ba-967b-60dacc2bd7f7"]'); ?>
                        <?php else: ?>
                            <iframe id="dior-hipaatizer-tab-iframe"
                                src="https://app.hipaatizer.com/workflow/01a12050-38e5-74ba-967b-60dacc2bd7f7"
                                allow="microphone; camera; payment" title="HIPAAtizer Clinical Intake &amp; Booking Form"
                                style="width: 100%; max-width: 100%; height: 850px; min-height: 750px; border: none; border-radius: 10px; display: block;"
                                loading="lazy"></iframe>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Today Appointments -->
        <div class="dior-subtab-panel dior-ic-44a70a0420" id="dior-subtab-today">
            <div class="va-table-wrapper">
                <div class="va-table-wrapper">
                    <div class="docs-card">
                        <div class="docs-header-container">
                            <div class="docs-title-box">
                                <h2 class="table-title">Today's Appointments</h2>
                                <div class="docs-title-line"></div>
                            </div>
                            <div class="docs-actions-wrapper">
                                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                                    <input type="text" id="dior-today-search-input"
                                        class="form-control border-start-0 ps-0" placeholder="Search records..."
                                        aria-label="Search box" onkeyup="diorFilterTodayAppointments()">
                                </div>
                                <div class="docs-actions-group">
                                    <button type="button" aria-label="Delete selected items"
                                        class="btn btn-sm btn-danger btn" title="Delete Selected"
                                        onclick="diorBulkDeleteStaticRows(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button type="button" aria-label="Export to CSV" class="btn btn-sm btn-success btn"
                                        title="Export to CSV" onclick="diorDownloadTodayAppointmentsCSV()">
                                        <i class="fas fa-file-arrow-down"></i>
                                    </button>
                                    <button type="button" aria-label="Refresh data" class="btn btn-sm btn-info btn"
                                        title="Refresh Page" onclick="window.location.reload()">
                                        <i class="fas fa-rotate-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="docs-table-wrapper">
                            <table class="docs-table table table-hover" id="dior-today-appointments-table">
                                <thead>
                                    <tr>
                                        <th>DOCTOR <i class="fas fa-sort"></i></th>
                                        <th>SPECIALIZATION <i class="fas fa-sort"></i></th>
                                        <th>DATE <i class="fas fa-sort"></i></th>
                                        <th>TIME <i class="fas fa-sort"></i></th>
                                        <th>TREATMENT <i class="fas fa-sort"></i></th>
                                        <th>CONTACT <i class="fas fa-sort"></i></th>
                                        <th>STATUS <i class="fas fa-sort"></i></th>
                                    </tr>
                                </thead>
                                <tbody id="dior-today-appointments-tbody">
                                    <?php
                                    $today_mock = [];
                                    $today_ymd = current_time('Y-m-d');
                                    $default_doc_img = site_url('/wp-content/uploads/2026/10/images-5.jpg');
                                    foreach ((array) $appointments as $apt_row) {
                                        $date_raw = $apt_row['date'] ?? ($apt_row['appt_date'] ?? '');
                                        $date_ymd = function_exists('dior_normalize_date') ? dior_normalize_date($date_raw) : $date_raw;
                                        $status = sanitize_text_field($apt_row['status'] ?? '');
                                        if ($date_ymd !== $today_ymd || in_array(strtolower($status), ['cancelled', 'canceled'], true))
                                            continue;

                                        // Resolve Doctor Avatar
                                        $doc_avatar = !empty($apt_row['avatar']) ? $apt_row['avatar'] : (!empty($apt_row['doctor_avatar']) ? $apt_row['doctor_avatar'] : '');
                                        $doc_id = (int) ($apt_row['doctor_id'] ?? 0);
                                        $doc_user_id = (int) ($apt_row['doctor_user_id'] ?? 0);

                                        if (empty($doc_avatar) && $doc_id) {
                                            $doc_avatar = get_post_meta($doc_id, '_wpddb_doctor_avatar', true)
                                                ?: get_post_meta($doc_id, 'wpddb_doctor_avatar', true)
                                                ?: get_the_post_thumbnail_url($doc_id, 'thumbnail');
                                        }
                                        if (empty($doc_avatar) && $doc_user_id) {
                                            $doc_avatar = get_user_meta($doc_user_id, 'dior_profile_image', true)
                                                ?: get_user_meta($doc_user_id, 'doctor_avatar', true)
                                                ?: get_user_meta($doc_user_id, 'profile_picture', true);
                                        }
                                        if (empty($doc_avatar)) {
                                            $doc_avatar = $default_doc_img;
                                        }

                                        // Resolve Doctor Contact
                                        $contact = !empty($apt_row['phone']) ? $apt_row['phone'] : (!empty($apt_row['contact']) ? $apt_row['contact'] : '');
                                        if (empty($contact) && $doc_id) {
                                            $contact = get_post_meta($doc_id, 'wpddb_doctor_phone', true) ?: get_post_meta($doc_id, 'wpddb_doctor_email', true);
                                        }
                                        if (empty($contact) && $doc_user_id) {
                                            $contact = get_user_meta($doc_user_id, 'phone', true)
                                                ?: get_user_meta($doc_user_id, 'doctor_phone', true)
                                                ?: get_user_meta($doc_user_id, 'billing_phone', true);
                                            if (empty($contact)) {
                                                $du = get_userdata($doc_user_id);
                                                if ($du && !empty($du->user_email))
                                                    $contact = $du->user_email;
                                            }
                                        }
                                        if (empty($contact) || $contact === '—') {
                                            $contact = '+1 (310) 555-0100';
                                        }

                                        $today_mock[] = [
                                            $apt_row['provider'] ?? ($apt_row['doctor_name'] ?? 'Dr. Marcus Sterling, DO'),
                                            $apt_row['provider_spec'] ?? 'General Practice & Internal Medicine',
                                            $date_ymd ? wp_date('M j, Y', strtotime($date_ymd)) : '—',
                                            $apt_row['time'] ?? ($apt_row['appt_time'] ?? '—'),
                                            $apt_row['condition'] ?? ($apt_row['type'] ?? ($apt_row['visit_type'] ?? 'Telehealth Consultation')),
                                            $contact,
                                            $status ?: 'Confirmed',
                                            $doc_avatar
                                        ];
                                    }

                                    foreach ($today_mock as $tm):
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="cell-content cell-image-name">
                                                    <img src="<?php echo esc_url($tm[7]); ?>"
                                                        alt="<?php echo esc_attr($tm[0]); ?>" class="cell-avatar"
                                                        onerror="this.onerror=null;this.src='<?php echo esc_url($default_doc_img); ?>';">
                                                    <span
                                                        class="cell-text doctor-name"><?php echo esc_html($tm[0]); ?></span>
                                                </div>
                                            </td>
                                            <td><span class="cell-text"><?php echo esc_html($tm[1]); ?></span></td>
                                            <td>
                                                <div class="cell-content cell-icon-text">
                                                    <i class="far fa-calendar cell-icon"></i>
                                                    <span class="cell-text"><?php echo esc_html($tm[2]); ?></span>
                                                </div>
                                            </td>
                                            <td><span class="cell-text"><?php echo esc_html($tm[3]); ?></span></td>
                                            <td><span class="cell-text"><?php echo esc_html($tm[4]); ?></span></td>
                                            <td><span class="cell-text"><?php echo esc_html($tm[5]); ?></span></td>
                                            <td>
                                                <?php
                                                $st_cls = 'status-confirm';
                                                if (strtolower($tm[6]) === 'cancelled')
                                                    $st_cls = 'status-cancelled';
                                                if (strtolower($tm[6]) === 'pending')
                                                    $st_cls = 'status-pending';
                                                ?>
                                                <span
                                                    class="status-cell-text <?php echo $st_cls; ?>"><?php echo esc_html($tm[6]); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>


        </div>

        <!-- 3. Upcoming Appointments -->
        <div class="dior-subtab-panel dior-ic-44a70a0420" id="dior-subtab-upcoming">
            <div class="va-table-wrapper">
                <div class="va-table-wrapper">
                    <div class="docs-card">
                        <div class="docs-header-container">
                            <div class="docs-title-box">
                                <h2 class="table-title">Upcoming Appointments</h2>
                                <div class="docs-title-line"></div>
                            </div>
                            <div class="docs-actions-wrapper">
                                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                                    <input type="text" id="dior-upcoming-search-input"
                                        class="form-control border-start-0 ps-0" placeholder="Search records..."
                                        aria-label="Search box" onkeyup="diorFilterUpcomingAppointments()">
                                </div>
                                <div class="docs-actions-group">
                                    <button type="button" aria-label="Delete selected items"
                                        class="btn btn-sm btn-danger dior-ic-44a70a0420 btn"
                                        id="dior-upcoming-bulk-delete-btn" title="Delete Selected"
                                        onclick="diorBulkDeleteUpcomingRows()">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button type="button" aria-label="Add new record" class="btn btn-sm btn-primary btn"
                                        title="Book Appointment" data-switch-tab="appointments">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                            fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16"
                                            style="background:transparent;">
                                            <path
                                                d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0" />
                                        </svg>
                                    </button>
                                    <button type="button" aria-label="Export to CSV" class="btn btn-sm btn-success btn"
                                        title="Export to CSV" onclick="diorDownloadUpcomingAppointmentsCSV()">
                                        <i class="fas fa-file-arrow-down"></i>
                                    </button>
                                    <button type="button" aria-label="Refresh data" class="btn btn-sm btn-info btn"
                                        title="Refresh Page" onclick="window.location.reload()">
                                        <i class="fas fa-rotate-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="docs-table-wrapper">
                            <table class="docs-table table table-hover" id="dior-upcoming-appointments-table">
                                <thead>
                                    <tr>
                                        <th class="dior-ic-5c9987d36c">
                                            <input type="checkbox" id="dior-select-all-upcoming"
                                                class="master-checkbox form-check-input"
                                                onclick="diorToggleSelectAllUpcoming(this)">
                                        </th>
                                        <th>DOCTOR <i class="fas fa-sort"></i></th>
                                        <th>DATE <i class="fas fa-sort"></i></th>
                                        <th>TIME <i class="fas fa-sort"></i></th>
                                        <th>INJURY <i class="fas fa-sort"></i></th>
                                        <th>STATUS <i class="fas fa-sort"></i></th>
                                        <th>NOTES <i class="fas fa-sort"></i></th>
                                        <th class="dior-ic-d5c209e67c">ACTIONS <i class="fas fa-sort"></i></th>
                                    </tr>
                                </thead>
                                <tbody id="dior-upcoming-appointments-tbody">
                                    <?php
                                    $upcoming_mock = [];
                                    $today_ymd = current_time('Y-m-d');
                                    foreach ((array) $appointments as $apt_row) {
                                        $date_raw = $apt_row['date'] ?? ($apt_row['appt_date'] ?? '');
                                        $date_ymd = function_exists('dior_normalize_date') ? dior_normalize_date($date_raw) : $date_raw;
                                        $status = sanitize_text_field($apt_row['status'] ?? 'Scheduled');
                                        if (!$date_ymd || $date_ymd < $today_ymd || in_array(strtolower($status), ['cancelled', 'canceled', 'completed', 'done'], true))
                                            continue;
                                        $upcoming_mock[] = [
                                            $apt_row['provider'] ?? ($apt_row['doctor_name'] ?? '—'),
                                            wp_date('M j, Y', strtotime($date_ymd)),
                                            $apt_row['time'] ?? ($apt_row['appt_time'] ?? '—'),
                                            $apt_row['condition'] ?? ($apt_row['type'] ?? ($apt_row['visit_type'] ?? '—')),
                                            $status,
                                            $apt_row['notes'] ?? '—'
                                        ];
                                    }
                                    ;
                                    foreach ($upcoming_mock as $um):
                                        ?>
                                        <tr>
                                            <td class="dior-ic-d5c209e67c">
                                                <input type="checkbox"
                                                    class="master-checkbox upcoming-row-checkbox form-check-input"
                                                    onclick="diorUpdateUpcomingSelectedCount()">
                                            </td>
                                            <td>
                                                <span class="cell-text doctor-name"><?php echo esc_html($um[0]); ?></span>
                                            </td>
                                            <td>
                                                <div class="cell-content cell-icon-text">
                                                    <i class="far fa-calendar cell-icon"></i>
                                                    <span class="cell-text"><?php echo esc_html($um[1]); ?></span>
                                                </div>
                                            </td>
                                            <td><span class="cell-text"><?php echo esc_html($um[2]); ?></span></td>
                                            <td><span class="cell-text"><?php echo esc_html($um[3]); ?></span></td>
                                            <td>
                                                <?php
                                                $st_cls = 'status-upcoming';
                                                if (strtolower($um[4]) === 'completed')
                                                    $st_cls = 'status-completed';
                                                if (strtolower($um[4]) === 'cancelled')
                                                    $st_cls = 'status-cancelled';
                                                ?>
                                                <span
                                                    class="status-cell-text <?php echo $st_cls; ?>"><?php echo esc_html($um[4]); ?></span>
                                            </td>
                                            <td><span class="cell-text notes-text"><?php echo esc_html($um[5]); ?></span>
                                            </td>
                                            <td class="dior-ic-d5c209e67c">
                                                <div class="cell-actions">
                                                    <button type="button" class="action-icon-btn edit-btn btn"
                                                        title="Edit Record"><svg xmlns="http://www.w3.org/2000/svg"
                                                            width="16" height="16" fill="currentColor"
                                                            class="bi bi-vector-pen" viewBox="0 0 16 16"
                                                            style="background:transparent;">
                                                            <path fill-rule="evenodd"
                                                                d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z" />
                                                            <path fill-rule="evenodd"
                                                                d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z" />
                                                        </svg></button>
                                                    <button type="button" class="action-icon-btn delete-btn btn"
                                                        title="Delete Record"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>


        </div>

        <!-- 4. Past Appointments -->
        <!-- 4. Past Appointments -->
        <div class="dior-subtab-panel dior-ic-44a70a0420" id="dior-subtab-past">
            <div class="va-table-wrapper">
                <div class="va-table-wrapper">
                    <div class="docs-card">
                        <div class="docs-header-container">
                            <div class="docs-title-box">
                                <h2 class="table-title">Past Appointments</h2>
                                <div class="docs-title-line"></div>
                            </div>
                            <div class="docs-actions-wrapper">
                                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                                    <input type="text" id="dior-past-search-input"
                                        class="form-control border-start-0 ps-0" placeholder="Search records..."
                                        aria-label="Search box" onkeyup="diorFilterPastAppointments()">
                                </div>
                                <div class="docs-actions-group">
                                    <button type="button" aria-label="Delete selected items"
                                        class="btn btn-sm btn-danger btn" title="Delete Selected"
                                        onclick="diorBulkDeleteStaticRows(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button type="button" aria-label="Export to CSV" class="btn btn-sm btn-success btn"
                                        title="Export to CSV" onclick="diorDownloadPastAppointmentsCSV()">
                                        <i class="fas fa-file-arrow-down"></i>
                                    </button>
                                    <button type="button" aria-label="Refresh data" class="btn btn-sm btn-info btn"
                                        title="Refresh Page" onclick="window.location.reload()">
                                        <i class="fas fa-rotate-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="docs-table-wrapper">
                            <table class="docs-table table table-hover" id="dior-past-appointments-table">
                                <thead>
                                    <tr>
                                        <th>DOCTOR <i class="fas fa-sort"></i></th>
                                        <th>DATE <i class="fas fa-sort"></i></th>
                                        <th>TIME <i class="fas fa-sort"></i></th>
                                        <th>EMAIL <i class="fas fa-sort"></i></th>
                                        <th>MOBILE <i class="fas fa-sort"></i></th>
                                        <th>INJURY <i class="fas fa-sort"></i></th>
                                        <th>TYPE <i class="fas fa-sort"></i></th>
                                        <th>NEXT APPOINTMENT <i class="fas fa-sort"></i></th>
                                    </tr>
                                </thead>
                                <tbody id="dior-past-appointments-tbody">
                                    <?php
                                    $past_mock = [];
                                    $today_ymd = current_time('Y-m-d');
                                    $default_doc_img = site_url('/wp-content/uploads/2026/10/images-5.jpg');

                                    // Next appointment date helper
                                    $next_appt_str = '—';
                                    if (!empty($next_appointment['date'])) {
                                        $next_appt_str = wp_date('M j, Y', strtotime($next_appointment['date']));
                                    } elseif (!empty($upcoming_list[0]['date'])) {
                                        $next_appt_str = wp_date('M j, Y', strtotime($upcoming_list[0]['date']));
                                    }

                                    foreach ((array) $appointments as $apt_row) {
                                        $date_raw = $apt_row['date'] ?? ($apt_row['appt_date'] ?? '');
                                        $date_ymd = function_exists('dior_normalize_date') ? dior_normalize_date($date_raw) : $date_raw;
                                        $status = sanitize_text_field($apt_row['status'] ?? '');
                                        if (!$date_ymd || ($date_ymd >= $today_ymd && !in_array(strtolower($status), ['completed', 'done', 'past'], true)))
                                            continue;

                                        $doc_name = $apt_row['provider'] ?? ($apt_row['doctor_name'] ?? 'Dr. Marcus Sterling, DO');
                                        $doc_id = (int) ($apt_row['doctor_id'] ?? 0);
                                        $doc_user_id = (int) ($apt_row['doctor_user_id'] ?? 0);

                                        // Lookup doctor post if doc_id not directly provided
                                        $doc_post_id = $doc_id;
                                        if (!$doc_post_id && !empty($doc_name) && $doc_name !== '—') {
                                            global $wpdb;
                                            $clean_title = trim(preg_replace('/^(?:dr\.?\s*)+/i', '', str_replace([', DO', ', MD', ' DO', ' MD'], '', $doc_name)));
                                            $found_pid = $wpdb->get_var($wpdb->prepare(
                                                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpddb_doctor' AND (post_title LIKE %s OR post_title LIKE %s) LIMIT 1",
                                                '%' . $wpdb->esc_like($clean_title) . '%',
                                                '%' . $wpdb->esc_like($doc_name) . '%'
                                            ));
                                            if ($found_pid) {
                                                $doc_post_id = (int) $found_pid;
                                            }
                                        }

                                        // 1. Resolve Doctor Avatar
                                        $doc_avatar = !empty($apt_row['avatar']) ? $apt_row['avatar'] : (!empty($apt_row['doctor_avatar']) ? $apt_row['doctor_avatar'] : '');
                                        if (empty($doc_avatar) && $doc_post_id) {
                                            $doc_avatar = get_post_meta($doc_post_id, '_wpddb_doctor_avatar', true)
                                                ?: get_post_meta($doc_post_id, 'wpddb_doctor_avatar', true)
                                                ?: get_the_post_thumbnail_url($doc_post_id, 'thumbnail');
                                        }
                                        if (empty($doc_avatar) && $doc_user_id) {
                                            $doc_avatar = get_user_meta($doc_user_id, 'dior_profile_image', true)
                                                ?: get_user_meta($doc_user_id, 'doctor_avatar', true)
                                                ?: get_user_meta($doc_user_id, 'profile_picture', true);
                                        }
                                        if (empty($doc_avatar)) {
                                            $doc_avatar = $default_doc_img;
                                        }

                                        // 2. Resolve Doctor Email
                                        $doc_email = !empty($apt_row['email']) ? $apt_row['email'] : (!empty($apt_row['doctor_email']) ? $apt_row['doctor_email'] : '');
                                        if (empty($doc_email) && $doc_post_id) {
                                            $doc_email = get_post_meta($doc_post_id, 'wpddb_doctor_email', true)
                                                ?: get_post_meta($doc_post_id, 'doctor_email', true);
                                        }
                                        if (empty($doc_email) && $doc_user_id) {
                                            $du = get_userdata($doc_user_id);
                                            if ($du && !empty($du->user_email)) {
                                                $doc_email = $du->user_email;
                                            }
                                        }
                                        if (empty($doc_email)) {
                                            $name_slug = strtolower(preg_replace('/[^a-zA-Z]/', '', str_replace(['Dr.', 'Dr', 'MD', 'DO', ','], '', $doc_name)));
                                            $doc_email = !empty($name_slug) ? $name_slug . '@diormedical.com' : 'care@diormedical.com';
                                        }

                                        // 3. Resolve Doctor Phone / Mobile
                                        $doc_phone = !empty($apt_row['phone']) ? $apt_row['phone'] : (!empty($apt_row['mobile']) ? $apt_row['mobile'] : (!empty($apt_row['contact']) ? $apt_row['contact'] : ''));
                                        if (empty($doc_phone) && $doc_post_id) {
                                            $doc_phone = get_post_meta($doc_post_id, 'wpddb_doctor_phone', true)
                                                ?: get_post_meta($doc_post_id, 'doctor_phone', true);
                                        }
                                        if (empty($doc_phone) && $doc_user_id) {
                                            $doc_phone = get_user_meta($doc_user_id, 'phone', true)
                                                ?: get_user_meta($doc_user_id, 'doctor_phone', true)
                                                ?: get_user_meta($doc_user_id, 'billing_phone', true);
                                        }
                                        if (empty($doc_phone) || $doc_phone === '—') {
                                            $doc_phone = '+1 (310) 555-0100';
                                        }

                                        $past_mock[] = [
                                            $doc_name,
                                            wp_date('M j, Y', strtotime($date_ymd)),
                                            $apt_row['time'] ?? ($apt_row['appt_time'] ?? '—'),
                                            $doc_email,
                                            $doc_phone,
                                            $apt_row['condition'] ?? ($apt_row['condition_name'] ?? 'Telehealth Consultation'),
                                            $apt_row['type'] ?? ($apt_row['visit_type'] ?? 'Video Visit (HD)'),
                                            $next_appt_str,
                                            $doc_avatar
                                        ];
                                    }

                                    // Fallback sample records if empty
                                    if (empty($past_mock)) {
                                        $past_mock = [
                                            ['Dr. Marcus Sterling, DO', 'Oct 13, 2026', '09:00 AM - 10:00 AM', 'marcussterling@diormedical.com', '+1 (310) 555-0100', 'Telehealth Consultation', 'Video Visit (HD)', $next_appt_str, $default_doc_img],
                                            ['Dr. Ethan Walker', 'Oct 10, 2026', '11:30 AM', 'ethanwalker@diormedical.com', '+1 (310) 555-0100', 'Blood Pressure Review', 'Clinic Visit', $next_appt_str, $default_doc_img],
                                        ];
                                    }

                                    foreach ($past_mock as $pm):
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="cell-content cell-image-name">
                                                    <img src="<?php echo esc_url($pm[8]); ?>"
                                                        alt="<?php echo esc_attr($pm[0]); ?>" class="cell-avatar"
                                                        onerror="this.onerror=null;this.src='<?php echo esc_url($default_doc_img); ?>';">
                                                    <span
                                                        class="cell-text doctor-name"><?php echo esc_html($pm[0]); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="cell-content cell-icon-text">
                                                    <i class="far fa-calendar cell-icon"></i>
                                                    <span class="cell-text"><?php echo esc_html($pm[1]); ?></span>
                                                </div>
                                            </td>
                                            <td><span class="cell-text"><?php echo esc_html($pm[2]); ?></span></td>
                                            <td><span class="cell-text text-primary"
                                                    style="font-size:13px;"><?php echo esc_html($pm[3]); ?></span></td>
                                            <td><span class="cell-text"
                                                    style="font-size:13px; color:#475569;"><?php echo esc_html($pm[4]); ?></span>
                                            </td>
                                            <td><span class="cell-text"><?php echo esc_html($pm[5]); ?></span></td>
                                            <td><span class="cell-text"><?php echo esc_html($pm[6]); ?></span></td>
                                            <td>
                                                <div class="cell-content cell-icon-text">
                                                    <i class="far fa-calendar cell-icon"></i>
                                                    <span class="cell-text"><?php echo esc_html($pm[7]); ?></span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <script>
        window.dior_inline_appts_data = <?php echo $apt_cards_json; ?>;
        if (typeof window.diorInitBootstrapCalendar === 'function') {
            window.diorInitBootstrapCalendar();
        }
    </script>

</section>
<!-- ============================================================== -->
<!-- 3. PAYMENTS & INVOICES TAB -->
<!-- ============================================================== -->