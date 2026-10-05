<section class="dior-tab-panel" id="tab-appointments">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Book Appointment</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Book Appointment</span></li>
            </ul>
        </div>
    </div>

                <?php
                    // Robust date normalizer: handles Y-m-d, d-m-Y, m-d-Y, d/m/Y etc.
                    if (!function_exists('dior_normalize_date')) {
                        function dior_normalize_date($raw) {
                            if (empty($raw)) return '';
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
                            foreach (['d-m-Y','d/m/Y','d.m.Y','m-d-Y','m/d/Y'] as $fmt) {
                                $dt = DateTime::createFromFormat($fmt, $raw);
                                if ($dt) return $dt->format('Y-m-d');
                            }
                            $ts = strtotime($raw);
                            return $ts ? date('Y-m-d', $ts) : '';
                        }
                    }


                    // Prepare appointment data as JSON for JS calendar
                    $cal_apt_dates = [];
                    $apt_total     = count($appointments);
                    $apt_upcoming  = 0;
                    $apt_completed = 0;
                    foreach ($appointments as $idx => $apt) {
                        $a_date   = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
                        $a_status = !empty($apt['status']) ? $apt['status'] : 'Scheduled';
                        $status_lc = strtolower($a_status);
                        if (in_array($status_lc, ['confirmed','scheduled','in-queue','pending','active'])) $apt_upcoming++;
                        if (in_array($status_lc, ['completed','done','past'])) $apt_completed++;
                        if ($a_date) {
                            $ymd = dior_normalize_date($a_date);
                            if ($ymd) $cal_apt_dates[$ymd] = true;
                        }
                    }
                    $cal_dates_json = json_encode(array_keys($cal_apt_dates));

                    // Build appointment cards JSON for JS filtering
                    $apt_cards = [];

                    foreach ($appointments as $idx => $apt) {
                        $a_id     = !empty($apt['id']) ? $apt['id'] : (!empty($apt['appt_uid']) ? $apt['appt_uid'] : 'APT-' . (1000 + $idx));
                        $a_doc    = !empty($apt['provider']) ? $apt['provider'] : (!empty($apt['doctor_name']) ? $apt['doctor_name'] : 'Provider');
                        $a_spec   = !empty($apt['provider_spec']) ? $apt['provider_spec'] : 'Telehealth';
                        $a_date_raw = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
                        $a_time   = !empty($apt['time']) ? $apt['time'] : (!empty($apt['appt_time']) ? $apt['appt_time'] : '');
                        $a_status = !empty($apt['status']) ? $apt['status'] : 'Scheduled';
                        $a_type   = !empty($apt['type']) ? $apt['type'] : (!empty($apt['visit_type']) ? $apt['visit_type'] : 'Video Visit');
                        $a_ymd    = dior_normalize_date($a_date_raw);
                        $apt_cards[] = [
                            'id'     => $a_id,
                            'doc'    => $a_doc,
                            'spec'   => $a_spec,
                            'date'   => $a_date_raw,
                            'ymd'    => $a_ymd,
                            'time'   => $a_time,
                            'status' => $a_status,
                            'type'   => $a_type,
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
                                    <i class="fa-solid fa-house"></i> / Appointments / <span>Book Appointment</span>
                                </div>
                            </div>

                            <div class="medidash-card dior-patient-booking-form-card">
                                <div class="medidash-card-header">
                                    <h3 class="medidash-card-title">Patient &amp; Appointment Details</h3>
                                </div>
                                <div class="medidash-card-body">
                                    <form id="dior-patient-appointment-form" class="dior-patient-appointment-form" novalidate>
                                        <div class="dior-form-grid dior-form-grid-2">
                                            <div class="dior-form-field">
                                                <label for="dior-appt-first">First name <span class="dior-required">*</span></label>
                                                <input id="dior-appt-first" name="first" type="text" class="dior-form-control" placeholder="First name" value="<?php echo esc_attr($profile['first_name'] ?? ""); ?>" autocomplete="given-name" required>
                                            </div>
                                            <div class="dior-form-field">
                                                <label for="dior-appt-last">Last name</label>
                                                <input id="dior-appt-last" name="last" type="text" class="dior-form-control" placeholder="Last name" value="<?php echo esc_attr($profile['last_name'] ?? ""); ?>" autocomplete="family-name">
                                            </div>
                                        </div>

                                        <div class="dior-form-grid dior-form-grid-2">
                                            <div class="dior-form-field">
                                                <label for="dior-appt-gender">Gender <span class="dior-required">*</span></label>
                                                <select id="dior-appt-gender" name="gender" class="dior-form-control" required>
                                                    <option value="">Select Gender</option>
                                                    <option value="male">Male</option>
                                                    <option value="female">Female</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                            <div class="dior-form-field">
                                                <label for="dior-appt-mobile">Mobile <span class="dior-required">*</span></label>
                                                <input id="dior-appt-mobile" name="mobile" type="tel" class="dior-form-control" placeholder="Mobile" value="<?php echo esc_attr($profile['phone'] ?? ""); ?>" autocomplete="tel" required>
                                            </div>
                                        </div>

                                        <div class="dior-form-field">
                                            <label for="dior-appt-address">Address</label>
                                            <textarea id="dior-appt-address" name="address" class="dior-form-control" rows="3" placeholder="Address"><?php echo esc_textarea($profile['address'] ?? ""); ?></textarea>
                                        </div>

                                        <div class="dior-form-grid dior-form-grid-2">
                                            <div class="dior-form-field">
                                                <label for="dior-appt-email">Email <span class="dior-required">*</span></label>
                                                <input id="dior-appt-email" name="email" type="email" class="dior-form-control" placeholder="Email" value="<?php echo esc_attr($profile['email'] ?? ""); ?>" autocomplete="email" required>
                                            </div>
                                            <div class="dior-form-field">
                                                <label for="dior-appt-dob">Date Of Birth <span class="dior-required">*</span></label>
                                                <input id="dior-appt-dob" name="dob" type="date" value="<?php echo esc_attr($profile['dob'] ?? ""); ?>" class="dior-form-control" required>
                                            </div>
                                        </div>

                                        <div class="dior-form-section-title">Appointment Details</div>

                                        <div class="dior-form-grid dior-form-grid-2">
                                            <div class="dior-form-field">
                                                <label for="dior-appt-doctor">Consulting Doctor <span class="dior-required">*</span></label>
                                                <select id="dior-appt-doctor" name="doctor" class="dior-form-control" required>
                                                    <option value="">Select Doctor</option>
                                                    <?php
                                                    $booking_doctors = [];
                                                    if (post_type_exists('wpddb_doctor')) {
                                                        $doctor_posts = get_posts([
                                                            'post_type' => 'wpddb_doctor',
                                                            'post_status' => 'publish',
                                                            'posts_per_page' => 50,
                                                            'orderby' => 'title',
                                                            'order' => 'ASC',
                                                        ]);
                                                        foreach ($doctor_posts as $doctor_post) {
                                                            $booking_doctors[(string)$doctor_post->ID] = $doctor_post->post_title;
                                                        }
                                                    }
                                                    if (empty($booking_doctors)) {
                                                        $doctor_users = get_users(['role' => 'doctor', 'number' => 50, 'orderby' => 'display_name', 'order' => 'ASC']);
                                                        foreach ($doctor_users as $doctor_user) {
                                                            $booking_doctors[(string)$doctor_user->ID] = $doctor_user->display_name;
                                                        }
                                                    }
                                                    foreach ($booking_doctors as $booking_doctor_id => $booking_doctor_name):
                                                    ?>
                                                        <option value="<?php echo esc_attr($booking_doctor_id); ?>"><?php echo esc_html($booking_doctor_name); ?></option>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($booking_doctors)): ?>
                                                        <option value="">No doctors available</option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="dior-form-field">
                                                <label for="dior-appt-date">Date Of Appointment <span class="dior-required">*</span></label>
                                                <input id="dior-appt-date" name="doa" type="date" class="dior-form-control" min="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
                                            </div>
                                        </div>

                                        <div class="dior-form-field">
                                            <label class="dior-form-label">Time Of Appointment <span class="dior-required">*</span></label>
                                            <div class="dior-time-slot-grid" role="radiogroup" aria-label="Time Slot">
                                                <?php foreach ([
                                                    'slot1' => '10:30-11:00',
                                                    'slot2' => '11:00-11:30',
                                                    'slot3' => '11:30-12:00',
                                                    'slot4' => '12:00-12:30',
                                                    'slot5' => '12:30-01:00',
                                                ] as $slot_id => $slot_label): ?>
                                                    <input type="radio" name="timeSlot" id="<?php echo esc_attr($slot_id); ?>" value="<?php echo esc_attr($slot_label); ?>" class="dior-time-slot-input" required>
                                                    <label for="<?php echo esc_attr($slot_id); ?>" class="dior-time-slot-label"><?php echo esc_html($slot_label); ?></label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div class="dior-form-field">
                                            <label for="dior-appt-injury">Injury/Condition</label>
                                            <textarea id="dior-appt-injury" name="injury" class="dior-form-control" rows="3" placeholder="Describe your injury or condition"></textarea>
                                        </div>

                                        <div class="dior-form-field">
                                            <label for="dior-appt-note">Note</label>
                                            <textarea id="dior-appt-note" name="note" class="dior-form-control" rows="3" placeholder="Additional notes for the doctor"></textarea>
                                        </div>

                                        <div class="dior-form-field">
                                            <label for="dior-appt-reports" class="dior-form-label">Upload Reports</label>
                                            <input id="dior-appt-reports" name="uploadFile" type="file" class="dior-file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            <small class="dior-form-help">PDF, JPG, PNG or document files.</small>
                                        </div>

                                        <div id="dior-patient-appointment-message" class="dior-form-message" role="status" aria-live="polite"></div>
                                        <div class="dior-form-actions">
                                            <button type="submit" class="dior-btn-primary">Submit</button>
                                            <button type="reset" class="dior-btn-light">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="medidash-card">
                                <div class="medidash-card-header">
                                    <h3 class="medidash-card-title">Book Appointment</h3>
                                </div>
                                <div class="medidash-card-body">
                                    <div class="dior-hipaa-embed-wrap dior-ic-b10f1f5d37">
                                        <h3 class="dior-ic-c4184d3815"><i class="fa-solid fa-clipboard-question dior-ic-31d287ea95"></i> Step 1: Clinical Intake Questionnaire</h3>
                                        <?php if (shortcode_exists('hipaatizer')): ?>
                                            <?php echo do_shortcode('[hipaatizer id="01a07aa2-d931-728d-bfe9-8d7b3bc0389d"]'); ?>
                                        <?php else: ?>
                                            <iframe src="https://app.hipaatizer.com/workflow/01a07aa2-d931-728d-bfe9-8d7b3bc0389d" allow="microphone; camera; payment" title="HIPAAtizer Clinical Intake &amp; Questionnaire Form" class="dior-ic-091ab91f5b"></iframe>
                                        <?php endif; ?>
                                    </div>
                                    <div class="dior-docbooker-embed-wrap dior-ic-eae3fd5ca4">
                                        <h3 class="dior-ic-c4184d3815"><i class="fa-solid fa-calendar-check dior-ic-a7dc4358b8"></i> Step 2: Schedule Consultation</h3>
                                        <?php if (shortcode_exists('docbooker_appointments')): ?>
                                            <?php echo do_shortcode('[docbooker_appointments]'); ?>
                                        <?php elseif (shortcode_exists('docbooker_calendar')): ?>
                                            <?php echo do_shortcode('[docbooker_calendar]'); ?>
                                        <?php else: ?>
                                            <div class="dior-ic-34ca0e2aa0">
                                                <div class="dior-ic-60dd1f38dd">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                </div>
                                                <h4 class="dior-ic-a5275d9442">DocBooker Scheduling</h4>
                                                <p class="dior-ic-cc929401cd">The interactive appointment calendar will appear here.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Today Appointments -->
                        <div class="dior-subtab-panel dior-ic-44a70a0420" id="dior-subtab-today">
                            <div class="va-table-wrapper">
                                <div class="va-table-wrapper">
                                    <div class="master-table-card">
                                        <div class="master-table-header">
                                            <div class="header-content">
                                                <div class="table-title-section">
                                                    <h2 class="table-title">Today's Appointments</h2>
                                                    <div class="title-accent"></div>
                                                </div>
                                                <div class="header-actions-group">
                                                    <div class="search-container">
                                                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                                        <input type="text" id="dior-today-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterTodayAppointments()">
                                                    </div>
                                                    <div class="action-buttons">
                                                        <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadTodayAppointmentsCSV()">
                                                            <i class="fa-solid fa-file-arrow-down"></i>
                                                        </button>
                                                        <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                                            <i class="fa-solid fa-rotate-right"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-content">
                                            <table class="va-table" id="dior-today-appointments-table">
                                                <thead>
                                                    <tr>
                                                        <th>DOCTOR <i class="fa-solid fa-sort"></i></th>
                                                        <th>SPECIALIZATION <i class="fa-solid fa-sort"></i></th>
                                                        <th>DATE <i class="fa-solid fa-sort"></i></th>
                                                        <th>TIME <i class="fa-solid fa-sort"></i></th>
                                                        <th>TREATMENT <i class="fa-solid fa-sort"></i></th>
                                                        <th>CONTACT <i class="fa-solid fa-sort"></i></th>
                                                        <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="dior-today-appointments-tbody">
                                                    <?php
                                                    $today_mock = [
                                                        ['Dr.Cara Stevens', 'Radiologist', 'Jun 12, 2020', '09:00-10:00', 'CT scans', '+123 676545655', 'Confirm', 'https://randomuser.me/api/portraits/women/44.jpg'],
                                                        ['Dr.John Doe', 'Cardiologist', 'Jun 12, 2020', '11:00-11:30', 'heart checkup', '+123 434656764', 'Cancelled', 'https://randomuser.me/api/portraits/men/32.jpg'],
                                                        ['Dr.Airi Satou', 'Otolaryngologist', 'Jun 12, 2020', '09:15-10:15', 'Diseases Of The Ear', '+123 45345673', 'Confirm', 'https://randomuser.me/api/portraits/women/65.jpg'],
                                                        ['Dr.Angelica Ramos', 'Dentist', 'Jun 12, 2020', '11:00-12:00', 'Root Canal', '+123 87654533', 'Confirm', 'https://randomuser.me/api/portraits/women/68.jpg'],
                                                        ['Dr.Jens Brincker', 'Endocrinologist', 'Jun 12, 2020', '04:00-05:00', 'Diabetes', '+123 45678345', 'Cancelled', 'https://randomuser.me/api/portraits/men/75.jpg'],
                                                        ['Dr.Jamie Blair', 'Radiologist', 'Jun 12, 2020', '05:00-05:30', 'Diabetes', '+123 45678345', 'Confirm', 'https://randomuser.me/api/portraits/women/49.jpg'],
                                                        ['Dr.Nikki Barton', 'Endocrinologist', 'Jun 12, 2020', '06:00-07:00', 'X-Ray', '+123 45678345', 'Pending', 'https://randomuser.me/api/portraits/men/85.jpg']
                                                        ,['Dr.Olivia Carter', 'Dermatologist', 'Jun 12, 2020', '07:30-08:00', 'Skin review', '+123 45678346', 'Confirm', 'https://randomuser.me/api/portraits/women/50.jpg']
                                                        ,['Dr.Daniel Brooks', 'Neurologist', 'Jun 12, 2020', '08:00-08:30', 'Headache review', '+123 45678347', 'Pending', 'https://randomuser.me/api/portraits/men/51.jpg']
                                                        ,['Dr.Emily Wilson', 'Endocrinologist', 'Jun 12, 2020', '08:30-09:00', 'Lab review', '+123 45678348', 'Confirm', 'https://randomuser.me/api/portraits/women/52.jpg']
                                                    ];
                                                    foreach ($today_mock as $tm):
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="cell-content cell-image-name">
                                                                <img src="<?php echo esc_url($tm[7]); ?>" alt="<?php echo esc_attr($tm[0]); ?>" class="cell-avatar">
                                                                <span class="cell-text doctor-name"><?php echo esc_html($tm[0]); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><span class="cell-text"><?php echo esc_html($tm[1]); ?></span></td>
                                                        <td>
                                                            <div class="cell-content cell-icon-text">
                                                                <i class="fa-regular fa-calendar cell-icon"></i>
                                                                <span class="cell-text"><?php echo esc_html($tm[2]); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><span class="cell-text"><?php echo esc_html($tm[3]); ?></span></td>
                                                        <td><span class="cell-text"><?php echo esc_html($tm[4]); ?></span></td>
                                                        <td><span class="cell-text"><?php echo esc_html($tm[5]); ?></span></td>
                                                        <td>
                                                            <?php
                                                                $st_cls = 'status-confirm';
                                                                if (strtolower($tm[6]) === 'cancelled') $st_cls = 'status-cancelled';
                                                                if (strtolower($tm[6]) === 'pending') $st_cls = 'status-pending';
                                                            ?>
                                                            <span class="status-cell-text <?php echo $st_cls; ?>"><?php echo esc_html($tm[6]); ?></span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="master-table-footer">
                                            <span class="page-count" id="dior-today-page-count">0 selected / <?php echo count($today_mock); ?> total</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            
                        </div>

                        <!-- 3. Upcoming Appointments -->
                        <div class="dior-subtab-panel dior-ic-44a70a0420" id="dior-subtab-upcoming">
                            <div class="va-table-wrapper">
                                <div class="va-table-wrapper">
                                    <div class="master-table-card">
                                        <div class="master-table-header">
                                            <div class="header-content">
                                                <div class="table-title-section">
                                                    <h2 class="table-title">Upcoming Appointments</h2>
                                                    <div class="title-accent"></div>
                                                </div>
                                                <div class="header-actions-group">
                                                    <div class="search-container">
                                                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                                        <input type="text" id="dior-upcoming-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterUpcomingAppointments()">
                                                    </div>
                                                    <div class="action-buttons">
                                                        <button type="button" aria-label="Delete selected items" class="action-btn action-btn-danger dior-ic-44a70a0420" id="dior-upcoming-bulk-delete-btn" title="Delete Selected" onclick="diorBulkDeleteUpcomingRows()">
                                                            <i class="fa-regular fa-trash-can"></i>
                                                        </button>
                                                        <button type="button" aria-label="Add new record" class="action-btn action-btn-primary" title="Book Appointment" data-switch-tab="appointments">
                                                            <i class="fa-solid fa-plus"></i>
                                                        </button>
                                                        <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadUpcomingAppointmentsCSV()">
                                                            <i class="fa-solid fa-file-arrow-down"></i>
                                                        </button>
                                                        <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                                            <i class="fa-solid fa-rotate-right"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-content">
                                            <table class="va-table" id="dior-upcoming-appointments-table">
                                                <thead>
                                                    <tr>
                                                        <th class="dior-ic-5c9987d36c">
                                                            <input type="checkbox" id="dior-select-all-upcoming" class="master-checkbox" onclick="diorToggleSelectAllUpcoming(this)">
                                                        </th>
                                                        <th>DOCTOR <i class="fa-solid fa-sort"></i></th>
                                                        <th>DATE <i class="fa-solid fa-sort"></i></th>
                                                        <th>TIME <i class="fa-solid fa-sort"></i></th>
                                                        <th>INJURY <i class="fa-solid fa-sort"></i></th>
                                                        <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                                        <th>NOTES <i class="fa-solid fa-sort"></i></th>
                                                        <th class="dior-ic-d5c209e67c">ACTIONS <i class="fa-solid fa-sort"></i></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="dior-upcoming-appointments-tbody">
                                                    <?php
                                                    $upcoming_mock = [
                                                        ['Dr. Rajesh', 'Sep 25, 2024', '10:00 AM', 'Fever', 'Completed', 'Patient has a history of allergies.'],
                                                        ['Dr. Smith', 'Sep 26, 2024', '11:00 AM', 'Routine Checkup', 'Cancelled', 'Follow-up on blood work.'],
                                                        ['Dr. Lee', 'Sep 27, 2024', '09:30 AM', 'Back Pain', 'Upcoming', 'Recommended physiotherapy.'],
                                                        ['Dr. Patel', 'Sep 28, 2024', '01:00 PM', 'Headache', 'Upcoming', 'Check for migraines.'],
                                                        ['Dr. Kim', 'Sep 29, 2024', '02:30 PM', 'Skin Rash', 'Cancelled', 'Suspected allergic reaction.'],
                                                        ['Dr. Johnson', 'Sep 30, 2024', '03:45 PM', 'Chest Pain', 'Completed', 'Cardiac evaluation needed.'],
                                                        ['Dr. Brown', 'Oct 1, 2024', '10:15 AM', 'Joint Pain', 'Upcoming', 'Possible arthritis.'],
                                                        ['Dr. White', 'Oct 2, 2024', '12:00 PM', 'Anxiety', 'Cancelled', 'Initial consultation.'],
                                                        ['Dr. Taylor', 'Oct 3, 2024', '11:30 AM', 'Digestive Issues', 'Upcoming', 'Discuss dietary changes.'],
                                                        ['Dr. Martinez', 'Oct 4, 2024', '09:00 AM', 'Injury from Fall', 'Completed', 'X-ray needed.']
                                                    ];
                                                    foreach ($upcoming_mock as $um):
                                                    ?>
                                                    <tr>
                                                        <td class="dior-ic-d5c209e67c">
                                                            <input type="checkbox" class="master-checkbox upcoming-row-checkbox" onclick="diorUpdateUpcomingSelectedCount()">
                                                        </td>
                                                        <td>
                                                            <span class="cell-text doctor-name"><?php echo esc_html($um[0]); ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="cell-content cell-icon-text">
                                                                <i class="fa-regular fa-calendar cell-icon"></i>
                                                                <span class="cell-text"><?php echo esc_html($um[1]); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><span class="cell-text"><?php echo esc_html($um[2]); ?></span></td>
                                                        <td><span class="cell-text"><?php echo esc_html($um[3]); ?></span></td>
                                                        <td>
                                                            <?php
                                                                $st_cls = 'status-upcoming';
                                                                if (strtolower($um[4]) === 'completed') $st_cls = 'status-completed';
                                                                if (strtolower($um[4]) === 'cancelled') $st_cls = 'status-cancelled';
                                                            ?>
                                                            <span class="status-cell-text <?php echo $st_cls; ?>"><?php echo esc_html($um[4]); ?></span>
                                                        </td>
                                                        <td><span class="cell-text notes-text"><?php echo esc_html($um[5]); ?></span></td>
                                                        <td class="dior-ic-d5c209e67c">
                                                            <div class="cell-actions">
                                                                <button type="button" class="action-icon-btn edit-btn" title="Edit Record"><i class="fa-solid fa-pen"></i></button>
                                                                <button type="button" class="action-icon-btn delete-btn" title="Delete Record"><i class="fa-regular fa-trash-can"></i></button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="master-table-footer">
                                            <span class="page-count" id="dior-upcoming-page-count">0 selected / <?php echo count($upcoming_mock); ?> total</span>
                                            
                                            <div class="master-pagination" id="dior-upcoming-pagination">
                                            </div>
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
                                    <div class="master-table-card">
                                        <div class="master-table-header">
                                            <div class="header-content">
                                                <div class="table-title-section">
                                                    <h2 class="table-title">Past Appointments</h2>
                                                    <div class="title-accent"></div>
                                                </div>
                                                <div class="header-actions-group">
                                                    <div class="search-container">
                                                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                                        <input type="text" id="dior-past-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterPastAppointments()">
                                                    </div>
                                                    <div class="action-buttons">
                                                        <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadPastAppointmentsCSV()">
                                                            <i class="fa-solid fa-file-arrow-down"></i>
                                                        </button>
                                                        <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                                            <i class="fa-solid fa-rotate-right"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-content">
                                            <table class="va-table" id="dior-past-appointments-table">
                                                <thead>
                                                    <tr>
                                                        <th>DOCTOR <i class="fa-solid fa-sort"></i></th>
                                                        <th>DATE <i class="fa-solid fa-sort"></i></th>
                                                        <th>TIME <i class="fa-solid fa-sort"></i></th>
                                                        <th>EMAIL <i class="fa-solid fa-sort"></i></th>
                                                        <th>MOBILE <i class="fa-solid fa-sort"></i></th>
                                                        <th>INJURY <i class="fa-solid fa-sort"></i></th>
                                                        <th>TYPE <i class="fa-solid fa-sort"></i></th>
                                                        <th>NEXT APPOINTMENT <i class="fa-solid fa-sort"></i></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="dior-past-appointments-tbody">
                                                    <?php
                                                    $past_mock = [
                                                        ['Dr. Rajesh', 'Feb 15, 2023', '10:00', 'patient1@email.com', '1234567890', 'Fever', 'Consultation', 'Feb 22, 2023', 'https://randomuser.me/api/portraits/women/44.jpg'],
                                                        ['Dr. Smith', 'Feb 20, 2023', '11:30', 'patient2@email.com', '2345678901', 'Headache', 'Follow-up', 'Mar 20, 2023', 'https://randomuser.me/api/portraits/women/65.jpg'],
                                                        ['Dr. Lee', 'Mar 1, 2023', '14:00', 'patient3@email.com', '3456789012', 'Back Pain', 'Consultation', 'Apr 1, 2023', 'https://randomuser.me/api/portraits/men/32.jpg'],
                                                        ['Dr. Patel', 'Mar 10, 2023', '09:15', 'patient4@email.com', '4567890123', 'Allergy', 'Consultation', 'Apr 10, 2023', 'https://randomuser.me/api/portraits/men/75.jpg'],
                                                        ['Dr. Kim', 'Mar 15, 2023', '13:45', 'patient5@email.com', '5678901234', 'Cough', 'Consultation', 'Apr 15, 2023', 'https://randomuser.me/api/portraits/women/68.jpg'],
                                                        ['Dr. Zhang', 'Mar 20, 2023', '10:30', 'patient6@email.com', '6789012345', 'Joint Pain', 'Follow-up', 'May 20, 2023', 'https://randomuser.me/api/portraits/men/85.jpg'],
                                                        ['Dr. Gupta', 'Apr 1, 2023', '11:00', 'patient7@email.com', '7890123456', 'Flu', 'Consultation', 'Apr 15, 2023', 'https://randomuser.me/api/portraits/women/49.jpg'],
                                                        ['Dr. Brown', 'Apr 10, 2023', '15:30', 'patient8@email.com', '8901234567', 'Stomach Ache', 'Consultation', 'May 10, 2023', 'https://randomuser.me/api/portraits/men/22.jpg'],
                                                        ['Dr. White', 'Apr 20, 2023', '08:00', 'patient9@email.com', '9012345678', 'Skin Rash', 'Consultation', 'May 20, 2023', 'https://randomuser.me/api/portraits/women/33.jpg'],
                                                        ['Dr. Miller', 'May 1, 2023', '16:00', 'patient10@email.com', '0123456789', 'Knee Pain', 'Consultation', 'Jun 1, 2023', 'https://randomuser.me/api/portraits/men/11.jpg']
                                                    ];
                                                    foreach ($past_mock as $pm):
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="cell-content cell-image-name">
                                                                <img src="<?php echo esc_url($pm[8]); ?>" alt="<?php echo esc_attr($pm[0]); ?>" class="cell-avatar">
                                                                <span class="cell-text doctor-name"><?php echo esc_html($pm[0]); ?></span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="cell-content cell-icon-text">
                                                                <i class="fa-regular fa-calendar cell-icon"></i>
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
                                                                <i class="fa-regular fa-calendar cell-icon"></i>
                                                                <span class="cell-text"><?php echo esc_html($pm[7]); ?></span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="master-table-footer">
                                            <span class="page-count" id="dior-past-page-count">0 selected / <?php echo count($past_mock); ?> total</span>
                                            
                                            <div class="master-pagination" id="dior-past-pagination">
                                            </div>
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
