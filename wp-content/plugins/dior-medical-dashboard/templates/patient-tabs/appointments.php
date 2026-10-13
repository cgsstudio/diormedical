<section class="dior-tab-panel" id="tab-appointments">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" id="dior-appt-main-title" style="font-size:20px;font-weight:700;">Book Appointment</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span id="dior-appt-breadcrumb-title">Book Appointment</span></li>
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
    foreach ($appointments as $idx => $apt) {
        $a_date = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
        $a_status = !empty($apt['status']) ? $apt['status'] : 'Scheduled';
        $status_lc = strtolower($a_status);
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
        $a_doc = !empty($apt['provider']) ? $apt['provider'] : (!empty($apt['doctor_name']) ? $apt['doctor_name'] : 'Provider');
        $a_spec = !empty($apt['provider_spec']) ? $apt['provider_spec'] : 'Telehealth';
        $a_date_raw = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
        $a_time = !empty($apt['time']) ? $apt['time'] : (!empty($apt['appt_time']) ? $apt['appt_time'] : '');
        $a_status = !empty($apt['status']) ? $apt['status'] : 'Scheduled';
        $a_type = !empty($apt['type']) ? $apt['type'] : (!empty($apt['visit_type']) ? $apt['visit_type'] : 'Video Visit');
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
        ];
    }
    $apt_cards_json = json_encode($apt_cards, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    ?>

    <!-- Appointment Content Area -->
    <div class="medidash-appt-wrap">

        <!-- 1. Book Appointment -->
        <div class="dior-subtab-panel dior-ic-44a70a0420" id="dior-subtab-book">
            <div class="medidash-page-header">
                <h2>Book Appointment</h2>
                <div class="medidash-breadcrumb">
                    <i class="fas fa-house"></i> / Appointments / <span>Book Appointment</span>
                </div>
            </div>

            <div class="medidash-card" style="border-radius: 16px; border: 1px solid #E2E8F0; background: #ffffff; box-shadow: 0 4px 20px rgba(15,23,42,0.05); overflow: hidden; margin-bottom: 24px;">
                <div class="medidash-card-header" style="padding: 20px 24px; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 class="medidash-card-title" style="margin: 0; font-size: 18px; font-weight: 700; color: #0F2B48; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-calendar-plus" style="color: #2C6CB1;"></i> Online Visit &amp; Medical Intake
                        </h3>
                        <p style="margin: 4px 0 0 0; font-size: 12.5px; color: #64748B;">Complete your secure, HIPAA-compliant questionnaire and book your telehealth appointment.</p>
                    </div>
                    <span style="background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-shield-halved" style="color: #3B82F6;"></i> HIPAA Verified
                    </span>
                </div>
                <div class="medidash-card-body" style="padding: 20px 24px;">
                    <div class="dior-hipaa-embed-container" style="width: 100%; max-width: 100%; min-height: 750px; background: #ffffff; border-radius: 12px; overflow: hidden;">
                        <?php if (shortcode_exists('hipaatizer')): ?>
                            <?php echo do_shortcode('[hipaatizer id="01a11515-b491-79dc-b59b-e4be2a345a49"]'); ?>
                        <?php else: ?>
                            <iframe id="dior-hipaatizer-tab-iframe"
                                src="https://app.hipaatizer.com/workflow/01a11515-b491-79dc-b59b-e4be2a345a49"
                                allow="microphone; camera; payment"
                                title="HIPAAtizer Clinical Intake &amp; Booking Form"
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
                                <div class="docs-search-box">
                                    <i class="fas fa-magnifying-glass"></i>
                                    <input type="text" id="dior-today-search-input" placeholder="Search records..."
                                        aria-label="Search box" onkeyup="diorFilterTodayAppointments()">
                                </div>
                                <div class="docs-actions-group">
                                    <button type="button" aria-label="Delete selected items" class="docs-icon-btn docs-btn-danger" title="Delete Selected" onclick="diorBulkDeleteStaticRows(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button type="button" aria-label="Export to CSV"
                                        class="docs-icon-btn docs-btn-success" title="Export to CSV"
                                        onclick="diorDownloadTodayAppointmentsCSV()">
                                        <i class="fas fa-file-arrow-down"></i>
                                    </button>
                                    <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info"
                                        title="Refresh Page" onclick="window.location.reload()">
                                        <i class="fas fa-rotate-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="docs-table-wrapper">
                            <table class="docs-table" id="dior-today-appointments-table">
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
                                    foreach ((array) $appointments as $apt_row) {
                                        $date_raw = $apt_row['date'] ?? ($apt_row['appt_date'] ?? '');
                                        $date_ymd = function_exists('dior_normalize_date') ? dior_normalize_date($date_raw) : $date_raw;
                                        $status = sanitize_text_field($apt_row['status'] ?? '');
                                        if ($date_ymd !== $today_ymd || in_array(strtolower($status), ['cancelled', 'canceled'], true)) continue;
                                        $today_mock[] = [
                                            $apt_row['provider'] ?? ($apt_row['doctor_name'] ?? '—'),
                                            $apt_row['provider_spec'] ?? '—',
                                            $date_ymd ? wp_date('M j, Y', strtotime($date_ymd)) : '—',
                                            $apt_row['time'] ?? ($apt_row['appt_time'] ?? '—'),
                                            $apt_row['condition'] ?? ($apt_row['type'] ?? ($apt_row['visit_type'] ?? '—')),
                                            $apt_row['phone'] ?? ($apt_row['contact'] ?? '—'),
                                            $status ?: 'Scheduled', ''
                                        ];
                                    };
                                    foreach ($today_mock as $tm):
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="cell-content cell-image-name">
                                                    <img src="<?php echo esc_url($tm[7]); ?>"
                                                        alt="<?php echo esc_attr($tm[0]); ?>" class="cell-avatar">
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
                        <div class="docs-pagination-container">
                            <span class="docs-showing-text" id="dior-today-page-count">0 selected /
                                <?php echo count($today_mock); ?> total</span>
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
                                <div class="docs-search-box">
                                    <i class="fas fa-magnifying-glass"></i>
                                    <input type="text" id="dior-upcoming-search-input" placeholder="Search records..."
                                        aria-label="Search box" onkeyup="diorFilterUpcomingAppointments()">
                                </div>
                                <div class="docs-actions-group">
                                    <button type="button" aria-label="Delete selected items"
                                        class="docs-icon-btn docs-btn-danger dior-ic-44a70a0420"
                                        id="dior-upcoming-bulk-delete-btn" title="Delete Selected"
                                        onclick="diorBulkDeleteUpcomingRows()">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button type="button" aria-label="Add new record"
                                        class="docs-icon-btn docs-btn-primary" title="Book Appointment"
                                        data-switch-tab="appointments">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg>
                                    </button>
                                    <button type="button" aria-label="Export to CSV"
                                        class="docs-icon-btn docs-btn-success" title="Export to CSV"
                                        onclick="diorDownloadUpcomingAppointmentsCSV()">
                                        <i class="fas fa-file-arrow-down"></i>
                                    </button>
                                    <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info"
                                        title="Refresh Page" onclick="window.location.reload()">
                                        <i class="fas fa-rotate-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="docs-table-wrapper">
                            <table class="docs-table" id="dior-upcoming-appointments-table">
                                <thead>
                                    <tr>
                                        <th class="dior-ic-5c9987d36c">
                                            <input type="checkbox" id="dior-select-all-upcoming" class="master-checkbox"
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
                                        if (!$date_ymd || $date_ymd < $today_ymd || in_array(strtolower($status), ['cancelled', 'canceled', 'completed', 'done'], true)) continue;
                                        $upcoming_mock[] = [
                                            $apt_row['provider'] ?? ($apt_row['doctor_name'] ?? '—'),
                                            wp_date('M j, Y', strtotime($date_ymd)),
                                            $apt_row['time'] ?? ($apt_row['appt_time'] ?? '—'),
                                            $apt_row['condition'] ?? ($apt_row['type'] ?? ($apt_row['visit_type'] ?? '—')),
                                            $status,
                                            $apt_row['notes'] ?? '—'
                                        ];
                                    };
                                    foreach ($upcoming_mock as $um):
                                        ?>
                                        <tr>
                                            <td class="dior-ic-d5c209e67c">
                                                <input type="checkbox" class="master-checkbox upcoming-row-checkbox"
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
                                                    <button type="button" class="action-icon-btn edit-btn"
                                                        title="Edit Record"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                                    <button type="button" class="action-icon-btn delete-btn"
                                                        title="Delete Record"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="docs-pagination-container">
                            <span class="docs-showing-text" id="dior-upcoming-page-count">0 selected /
                                <?php echo count($upcoming_mock); ?> total</span>
                            <div id="dior-upcoming-pagination"></div>
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
                                <div class="docs-search-box">
                                    <i class="fas fa-magnifying-glass"></i>
                                    <input type="text" id="dior-past-search-input" placeholder="Search records..."
                                        aria-label="Search box" onkeyup="diorFilterPastAppointments()">
                                </div>
                                <div class="docs-actions-group">
                                    <button type="button" aria-label="Delete selected items" class="docs-icon-btn docs-btn-danger" title="Delete Selected" onclick="diorBulkDeleteStaticRows(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button type="button" aria-label="Export to CSV"
                                        class="docs-icon-btn docs-btn-success" title="Export to CSV"
                                        onclick="diorDownloadPastAppointmentsCSV()">
                                        <i class="fas fa-file-arrow-down"></i>
                                    </button>
                                    <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info"
                                        title="Refresh Page" onclick="window.location.reload()">
                                        <i class="fas fa-rotate-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="docs-table-wrapper">
                            <table class="docs-table" id="dior-past-appointments-table">
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
                                    foreach ((array) $appointments as $apt_row) {
                                        $date_raw = $apt_row['date'] ?? ($apt_row['appt_date'] ?? '');
                                        $date_ymd = function_exists('dior_normalize_date') ? dior_normalize_date($date_raw) : $date_raw;
                                        $status = sanitize_text_field($apt_row['status'] ?? '');
                                        if (!$date_ymd || ($date_ymd >= $today_ymd && !in_array(strtolower($status), ['completed', 'done', 'past'], true))) continue;
                                        $past_mock[] = [
                                            $apt_row['provider'] ?? ($apt_row['doctor_name'] ?? '—'),
                                            wp_date('M j, Y', strtotime($date_ymd)),
                                            $apt_row['time'] ?? ($apt_row['appt_time'] ?? '—'),
                                            $apt_row['email'] ?? '—',
                                            $apt_row['phone'] ?? '—',
                                            $apt_row['condition'] ?? '—',
                                            $apt_row['type'] ?? ($apt_row['visit_type'] ?? 'Consultation'),
                                            '—', ''
                                        ];
                                    };
                                    foreach ($past_mock as $pm):
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="cell-content cell-image-name">
                                                    <img src="<?php echo esc_url($pm[8]); ?>"
                                                        alt="<?php echo esc_attr($pm[0]); ?>" class="cell-avatar">
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
                                            <td><span class="cell-text"><?php echo esc_html($pm[3]); ?></span></td>
                                            <td><span class="cell-text"><?php echo esc_html($pm[4]); ?></span></td>
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
                        <div class="docs-pagination-container">
                            <span class="docs-showing-text" id="dior-past-page-count">0 selected /
                                <?php echo count($past_mock); ?> total</span>
                            <div id="dior-past-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>




</section>
<!-- ============================================================== -->
<!-- 3. PAYMENTS & INVOICES TAB -->
<!-- ============================================================== -->
