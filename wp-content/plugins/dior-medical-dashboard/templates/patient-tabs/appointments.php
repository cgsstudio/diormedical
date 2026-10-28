<section class="dior-tab-panel py-3 py-md-4 px-2 px-md-3 pb-5" id="tab-appointments">
    <style>
    /* Shared typography and spacing for Today, Upcoming and Past appointment tables. */
    #tab-appointments .dior-appointment-list-table {
        width: 100%;
        margin-bottom: 0;
        border-color: #e5ebf3;
        table-layout: auto;
        font-size: 12px;
        color: #334155;
    }
    #tab-appointments .dior-appointment-list-table th {
        padding: 12px 14px;
        color: #64748b;
        background: #fff;
        border-bottom: 1px solid #dfe7f1;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .045em;
        line-height: 1.35;
        white-space: nowrap;
        vertical-align: middle;
    }
    #tab-appointments .dior-appointment-list-table td {
        padding: 12px 14px;
        color: #334155;
        background: transparent;
        border-color: #edf1f6;
        font-size: 12px;
        line-height: 1.4;
        vertical-align: middle;
    }
    #tab-appointments .dior-appointment-list-table .dior-appointment-doctor-name {
        display: inline-block;
        color: #172b49;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.4;
        white-space: nowrap;
    }
    /* Keep table surfaces neutral; emphasize text with text color only. */
    #tab-appointments .dior-appointment-list-table tbody tr,
    #tab-appointments .dior-appointment-list-table tbody tr:hover,
    #tab-appointments .dior-appointment-list-table tbody tr:focus-within {
        background: transparent !important;
    }
    #tab-appointments .dior-appointment-list-table tbody tr > *,
    #tab-appointments .dior-appointment-list-table tbody tr:hover > * {
        background: transparent !important;
    }
    #tab-appointments .dior-appointment-list-table .badge,
    #tab-appointments .dior-appointment-list-table .rounded-pill {
        background: transparent !important;
        border-color: transparent !important;
        box-shadow: none !important;
    }
    /* Keep dates and time ranges on one line where space permits. */
    #tab-appointments #dior-today-appointments-table td:nth-child(4),
    #tab-appointments #dior-today-appointments-table td:nth-child(5),
    #tab-appointments #dior-upcoming-appointments-table td:nth-child(3),
    #tab-appointments #dior-upcoming-appointments-table td:nth-child(4),
    #tab-appointments #dior-past-appointments-table td:nth-child(3),
    #tab-appointments #dior-past-appointments-table td:nth-child(4) {
        white-space: nowrap;
    }
    @media (max-width: 767.98px) {
        #tab-appointments .dior-appointment-list-table th,
        #tab-appointments .dior-appointment-list-table td {
            padding: 10px 12px;
        }
    }
</style>


    <!-- Breadcrumb Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 px-3 px-md-4 pt-2">
        <div>
            <h4 class="mb-0 text-dark" id="dior-appt-main-title" style="font-size:20px;font-weight:700;">Telehealth Appointments</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span id="dior-appt-breadcrumb-title">Telehealth Appointments</span></li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-3 px-md-4 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-appt-subtab="book" onclick="diorSwitchApptSubTab('book')">
            <i class="fas fa-calendar-plus"></i> Book Appointment
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-appt-subtab="today" onclick="diorSwitchApptSubTab('today')">
            <i class="fas fa-calendar-check"></i> Today Appointments
        </button>
        <button type="button" class="dior-subtab-pill btn" data-appt-subtab="upcoming" onclick="diorSwitchApptSubTab('upcoming')">
            <i class="fas fa-clock"></i> Upcoming Appointments
        </button>
        <button type="button" class="dior-subtab-pill btn" data-appt-subtab="past" onclick="diorSwitchApptSubTab('past')">
            <i class="fas fa-history"></i> Past Appointments
        </button>
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

        <!-- 1. Book Appointment -->
        <div class="dior-subtab-panel" id="dior-subtab-book" style="display: none;">
            <?php
            $appt_user_id = !empty($user_id) ? (int)$user_id : get_current_user_id();
            $appt_hipaa = (!empty($hipaa_intake) && is_array($hipaa_intake)) ? $hipaa_intake : Dior_Patient_Portal_Data::get_normalized_hipaa_intake($appt_user_id);
            $appt_has_intake = !empty($appt_hipaa) && (!empty($appt_hipaa['is_submitted']) || !empty($appt_hipaa['submitted_at']));

            // Attending doctor lookup for Step 2
            $appt_doctor_user_id = class_exists('Dior_Doctor_Resolver') ? Dior_Doctor_Resolver::get_default_doctor_id() : 4;
            $appt_doctor_id = 1957;
            $appt_doctor_post = get_post($appt_doctor_id);
            if (!$appt_doctor_post || $appt_doctor_post->post_type !== 'wpddb_doctor') {
                $published_docs = get_posts([
                    'post_type' => 'wpddb_doctor',
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                ]);
                if (!empty($published_docs)) {
                    $appt_doctor_post = $published_docs[0];
                    $appt_doctor_id = $appt_doctor_post->ID;
                }
            }
            $appt_doctor_name = $appt_doctor_post ? $appt_doctor_post->post_title : 'Dr. Marcus Sterling, DO';
            $appt_doctor_spec = $appt_doctor_post ? (get_post_meta($appt_doctor_id, 'wpddb_doctor_speciality', true) ?: 'General Practice & Internal Medicine') : 'General Practice & Internal Medicine';
            $appt_doctor_avatar = get_avatar_url($appt_doctor_user_id, ['size' => 120]) ?: (DIOR_PORTAL_URL . 'assets/images/doctor-placeholder.jpg');

            $appt_patient_profile = ($appt_user_id && class_exists('Dior_Patient_Portal_Data')) ? Dior_Patient_Portal_Data::get_patient_profile($appt_user_id) : [];
            $appt_patient_name = !empty($appt_patient_profile['full_name']) ? $appt_patient_profile['full_name'] : '';
            $appt_patient_email = !empty($appt_patient_profile['email']) ? $appt_patient_profile['email'] : '';
            $appt_patient_phone = !empty($appt_patient_profile['phone']) ? $appt_patient_profile['phone'] : (!empty($appt_hipaa['phone']) ? $appt_hipaa['phone'] : '');
            $appt_patient_reason = !empty($appt_hipaa['service_condition']) ? $appt_hipaa['service_condition'] : (!empty($appt_hipaa['symptoms']) ? $appt_hipaa['symptoms'] : 'General Consultation');
            $appt_today_ymd = current_time('Y-m-d');
            ?>

            <!-- ── ELEGANT FULL-WIDTH 3-STEP TIMELINE ── -->
            <div class="card border-0 shadow-sm rounded-4 px-3 px-md-4 py-3 py-md-4 mb-4 mb-md-5 bg-white" id="dior-booking-stepper-card">
                <!-- Hidden DOM elements to maintain full JS backward compatibility -->
                <span id="dior-stepper-step-heading" class="d-none">STEP 1 OF 3</span>
                <span id="dior-stepper-est-time" class="d-none">Est. 4 mins remaining</span>
                <span id="dior-stepper-circle-text" class="d-none">1/3</span>
                <svg class="d-none"><circle id="dior-stepper-circle-svg"></circle></svg>

                <div class="row g-0 align-items-center my-0" id="dior-stepper-steps-row">
                    <!-- Step 1: Clinical Intake -->
                    <div class="col-12 col-md-4 pe-md-3">
                        <div id="dior-step-item-1" class="d-flex align-items-center gap-3 p-2 rounded-3" onclick="diorNavigateBookingStep(1)" style="cursor: pointer;">
                            <div id="dior-step-icon-1" class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white fw-bold shadow-sm flex-shrink-0" style="width: 38px; height: 38px; font-size: 14px; transition: all 0.25s ease;">
                                1
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-0.5">
                                    <span id="dior-step-tag-1" class="text-uppercase fw-bold text-primary" style="font-size: 10px; letter-spacing: 0.5px;">STEP 1</span>
                                    <span id="dior-step-badge-1" class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5" style="font-size: 9px; display: inline-block !important;">Current</span>
                                </div>
                                <div id="dior-step-title-1" class="fw-bold text-dark text-truncate" style="font-size: 14px; line-height: 1.3;">Clinical Intake</div>
                                <div class="text-secondary text-truncate" style="font-size: 11px !important; line-height: 1.3; font-weight: 500;">Health Questionnaire &amp; History</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Doctor & Time Slot -->
                    <div class="col-12 col-md-4 px-md-3 border-start-md" style="border-left: 1px solid #E2E8F0;">
                        <div id="dior-step-item-2" class="d-flex align-items-center gap-3 p-2 rounded-3" onclick="diorNavigateBookingStep(2)" style="cursor: pointer;">
                            <div id="dior-step-icon-2" class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 38px; height: 38px; font-size: 14px; background-color: #E2E8F0; color: #64748B; transition: all 0.25s ease;">
                                2
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-0.5">
                                    <span id="dior-step-tag-2" class="text-uppercase fw-semibold text-secondary" style="font-size: 10px; letter-spacing: 0.5px;">STEP 2</span>
                                    <span id="dior-step-badge-2" class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5" style="font-size: 9px; display: none !important;">Current</span>
                                </div>
                                <div id="dior-step-title-2" class="fw-bold text-secondary text-truncate" style="font-size: 14px; line-height: 1.3;">Provider &amp; Time Slot</div>
                                <div class="text-secondary text-truncate" style="font-size: 11px !important; line-height: 1.3; font-weight: 500;">Doctor &amp; Availability</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Confirmation -->
                    <div class="col-12 col-md-4 ps-md-3 border-start-md" style="border-left: 1px solid #E2E8F0;">
                        <div id="dior-step-item-3" class="d-flex align-items-center gap-3 p-2 rounded-3" onclick="diorNavigateBookingStep(3)" style="cursor: pointer;">
                            <div id="dior-step-icon-3" class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 38px; height: 38px; font-size: 14px; background-color: #E2E8F0; color: #64748B; transition: all 0.25s ease;">
                                3
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-0.5">
                                    <span id="dior-step-tag-3" class="text-uppercase fw-semibold text-secondary" style="font-size: 10px; letter-spacing: 0.5px;">STEP 3</span>
                                    <span id="dior-step-badge-3" class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5" style="font-size: 9px; display: none !important;">Current</span>
                                </div>
                                <div id="dior-step-title-3" class="fw-bold text-secondary text-truncate" style="font-size: 14px; line-height: 1.3;">Confirmation</div>
                                <div class="text-secondary text-truncate" style="font-size: 11px !important; line-height: 1.3; font-weight: 500;">Summary &amp; Video Link</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hidden line elements for JS backward compatibility -->
                <div class="d-none">
                    <div id="dior-step-line-1"></div>
                    <div id="dior-step-line-2"></div>
                </div>
            </div>

            <!-- ── STEP 1: CLINICAL INTAKE (100% Bootstrap 5 - Clean, Seamless, No Background Card) ── -->
            <div id="dior-step-pane-1" class="dior-step-pane">
                <!-- Mandatory Intake Reminder Alert (Appears only if user attempts to skip) -->
                <div id="dior-intake-mandatory-alert" class="alert alert-warning border-0 rounded-3 py-2 px-3 small d-none mb-3">
                    <i class="fas fa-circle-exclamation me-2"></i><strong>Clinical Intake Required:</strong> Please complete and submit the medical questionnaire below before selecting your provider and time slot.
                </div>

                <!-- Clinical Intake Form (BAA warning notice cropped out cleanly) -->
                <div class="w-100 mb-4 overflow-hidden position-relative" style="border-radius: 16px;">
                    <div style="margin-top: -105px;">
                        <?php if (shortcode_exists('hipaatizer')): ?>
                            <?php echo do_shortcode('[hipaatizer id="01a12050-38e5-74ba-967b-60dacc2bd7f7"]'); ?>
                        <?php else: ?>
                            <iframe id="dior-hipaatizer-tab-iframe"
                                src="https://app.hipaatizer.com/workflow/01a12050-38e5-74ba-967b-60dacc2bd7f7"
                                allow="microphone; camera; payment" title="Clinical Intake &amp; Booking Form"
                                style="width: 100%; max-width: 100%; min-width: 100%; height: 850px; min-height: 750px; border: none; display: block; background: transparent;"
                                loading="lazy"></iframe>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── STEP 2: DOCBOOKER APPOINTMENT SCHEDULING ── -->
            <div id="dior-step-pane-2" class="dior-step-pane d-none">
                <!-- Verified Status Strip -->
                <div class="card border-0 shadow-sm rounded-4 p-3 px-4 mb-4 bg-white d-flex flex-row align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            <i class="fas fa-check-circle me-1"></i> Intake Form Verified
                        </span>
                        <span class="text-secondary small fw-medium">Clinical questionnaire on file. Now choose your provider, format, and preferred time slot.</span>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1 small fw-semibold" onclick="diorGoToBookingStep(1);">
                        <i class="fas fa-pen me-1"></i> Edit Intake
                    </button>
                </div>

                <!-- Attending Physician Card -->
                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="position-relative">
                                <img src="<?php echo esc_url($appt_doctor_avatar); ?>" alt="<?php echo esc_attr($appt_doctor_name); ?>" class="rounded-circle border shadow-sm" style="width: 58px; height: 58px; object-fit: cover; flex-shrink: 0;">
                                <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-white border-2 rounded-circle" title="Available for Telehealth"></span>
                            </div>
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-0.5 small fw-semibold">Attending Physician</span>
                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 small"><i class="fas fa-certificate text-primary me-1"></i> Board Certified</span>
                                </div>
                                <h5 class="fw-bold text-dark mb-0 text-truncate"><?php echo esc_html($appt_doctor_name); ?></h5>
                                <div class="text-secondary small text-truncate mt-0.5"><?php echo esc_html($appt_doctor_spec); ?> &bull; Dior Medical Urgent Care</div>
                            </div>
                        </div>
                        <div class="text-end d-none d-md-block">
                            <span class="badge bg-light text-dark border rounded-pill px-3 py-1.5 small fw-semibold">
                                <i class="fas fa-video text-primary me-1"></i> Telehealth Verified
                            </span>
                        </div>
                    </div>
                </div>

                <!-- DocBooker Inline Form -->
                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white">
                    <?php
                    $step2_initial_slots = class_exists('Dior_Medical_Dashboard')
                        ? Dior_Medical_Dashboard::get_doctor_slots_for_date($appt_doctor_id, $appt_today_ymd)
                        : [];
                    $step2_first_slot_time = '';
                    foreach ($step2_initial_slots as $st) {
                        if (!empty($st['is_available'])) {
                            $step2_first_slot_time = $st['time'];
                            break;
                        }
                    }
                    $step2_default_time = $step2_first_slot_time ?: '09:00 AM - 10:00 AM';
                    ?>
                    <form id="dior-step2-docbooker-form" onsubmit="diorSubmitInlineBooking(event)">
                        <!-- Hidden meta -->
                        <input type="hidden" name="action" value="dior_patient_book_appointment">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('dior_portal_nonce')); ?>">
                        <input type="hidden" name="doctor_id" id="dior-step2-doctor-id" value="<?php echo esc_attr($appt_doctor_id); ?>">
                        <input type="hidden" name="doctorId" value="<?php echo esc_attr($appt_doctor_id); ?>">
                        <input type="hidden" name="doctor_name" id="dior-step2-doctor-name" value="<?php echo esc_attr($appt_doctor_name); ?>">
                        <input type="hidden" name="provider" value="<?php echo esc_attr($appt_doctor_name); ?>">
                        <input type="hidden" name="selected_time" id="dior-step2-selected-time" value="<?php echo esc_attr($step2_default_time); ?>">
                        <input type="hidden" name="time" id="dior-step2-final-time" value="<?php echo esc_attr($step2_default_time); ?>">
                        <input type="hidden" name="service_type" id="dior-step2-service-type" value="Video Visit (HD)">
                        <input type="hidden" name="type" id="dior-step2-final-type" value="Video Visit (HD)">
                        <input type="hidden" name="condition" id="dior-step2-final-condition" value="<?php echo esc_attr($appt_patient_reason); ?>">

                        <!-- 1. Consultation Format -->
                        <div class="mb-4">
                            <label class="small text-uppercase text-secondary fw-bold mb-2 d-block">1. Select Consultation Format</label>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="p-3 rounded-4 border border-2 border-primary bg-white shadow-sm d-flex align-items-center justify-content-between cursor-pointer dior-service-card-item active" id="dior-fmt-video" onclick="diorSelectInlineFormat('Video Visit (HD)', this)" style="cursor: pointer; transition: all 0.2s ease;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                                                <i class="fas fa-video"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark fs-6">Telehealth Video Visit (HD)</div>
                                                <div class="small text-secondary">Secure encrypted video consultation</div>
                                            </div>
                                        </div>
                                        <i class="fas fa-circle-check text-primary fs-5 dior-fmt-check"></i>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="p-3 rounded-4 border bg-white d-flex align-items-center justify-content-between cursor-pointer dior-service-card-item opacity-75" id="dior-fmt-phone" onclick="diorSelectInlineFormat('Phone Consultation', this)" style="cursor: pointer; transition: all 0.2s ease;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-light text-secondary border d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                                                <i class="fas fa-phone"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark fs-6">Phone Consultation</div>
                                                <div class="small text-secondary">Direct call to your registered phone</div>
                                            </div>
                                        </div>
                                        <i class="far fa-circle text-secondary fs-5 dior-fmt-check"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Date & Time Selection -->
                        <div class="mb-4">
                            <label class="small text-uppercase text-secondary fw-bold mb-2 d-block">2. Consultation Date &amp; Available Slot</label>
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-4">
                                    <label for="dior-step2-date-input" class="small fw-semibold text-dark mb-1">Consultation Date *</label>
                                    <input type="date" id="dior-step2-date-input" name="date" class="form-control rounded-3 py-2 px-3"
                                           min="<?php echo esc_attr($appt_today_ymd); ?>"
                                           value="<?php echo esc_attr($appt_today_ymd); ?>"
                                           onchange="diorOnStep2DateChanged(this.value)" required>
                                </div>
                                <div class="col-12 col-md-8">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="small fw-semibold text-dark">Available Time Slots *</span>
                                        <span id="dior-step2-slots-spinner" class="small text-muted d-none">
                                            <i class="fas fa-circle-notch fa-spin text-primary me-1"></i> Checking live slots...
                                        </span>
                                    </div>
                                    <div id="dior-step2-slots-grid" class="d-flex flex-wrap gap-2">
                                        <?php
                                        $step2_has_available = false;
                                        $step2_first_selected = false;
                                        foreach ($step2_initial_slots as $s_item):
                                            if (!empty($s_item['is_available'])):
                                                $step2_has_available = true;
                                                $is_active = !$step2_first_selected;
                                                if ($is_active) {
                                                    $step2_first_selected = true;
                                                }
                                        ?>
                                            <button type="button"
                                                    class="btn <?php echo $is_active ? 'btn-primary active' : 'btn-outline-primary'; ?> rounded-3 btn-sm py-2 px-3 dior-step2-slot-btn"
                                                    data-time="<?php echo esc_attr($s_item['time']); ?>"
                                                    onclick="diorSelectStep2Slot('<?php echo esc_js($s_item['time']); ?>', this)">
                                                <?php echo esc_html($s_item['time']); ?>
                                            </button>
                                        <?php
                                            endif;
                                        endforeach;
                                        if (!$step2_has_available):
                                        ?>
                                            <div class="w-100 text-center py-3 px-2 text-muted small">
                                                <i class="fas fa-clock me-1 text-warning"></i> No further consultation slots are available for today. Please select a future date to book an appointment.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Patient Details -->
                        <div class="mb-4">
                            <label class="small text-uppercase text-secondary fw-bold mb-2 d-block">3. Patient Contact &amp; Reason</label>
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label for="dior-step2-patient-name" class="small fw-semibold text-dark mb-1">Full Name *</label>
                                    <input type="text" name="name" id="dior-step2-patient-name" class="form-control rounded-3 py-2 px-3" value="<?php echo esc_attr($appt_patient_name); ?>" placeholder="Your Full Name" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="dior-step2-patient-email" class="small fw-semibold text-dark mb-1">Email Address *</label>
                                    <input type="email" name="email" id="dior-step2-patient-email" class="form-control rounded-3 py-2 px-3" value="<?php echo esc_attr($appt_patient_email); ?>" placeholder="you@example.com" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="dior-step2-patient-phone" class="small fw-semibold text-dark mb-1">Phone Number *</label>
                                    <input type="tel" name="phone" id="dior-step2-patient-phone" class="form-control rounded-3 py-2 px-3" value="<?php echo esc_attr($appt_patient_phone); ?>" placeholder="(555) 000-0000" required>
                                </div>
                                <div class="col-12">
                                    <label for="dior-step2-patient-notes" class="small fw-semibold text-dark mb-1">Chief Complaint / Symptoms</label>
                                    <input type="text" name="notes" id="dior-step2-patient-notes" class="form-control rounded-3 py-2 px-3" value="<?php echo esc_attr($appt_patient_reason); ?>" placeholder="e.g. Follow-up consultation or symptoms">
                                </div>
                            </div>
                        </div>

                        <!-- Feedback Box -->
                        <div id="dior-step2-status-box" class="alert alert-danger border-0 rounded-4 p-3 mb-3 d-none"></div>

                        <!-- Actions -->
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pt-2 border-top">
                            <button type="button" class="btn btn-outline-secondary rounded-3 fw-semibold py-2 px-4" onclick="diorGoToBookingStep(1);">
                                <i class="fas fa-arrow-left me-1"></i> Back to Step 1
                            </button>
                            <button type="submit" id="dior-step2-submit-btn" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm">
                                <span class="dior-step2-btn-text"><i class="fas fa-calendar-check me-2"></i> Confirm &amp; Schedule Appointment</span>
                                <span class="dior-step2-btn-spinner d-none"><i class="fas fa-circle-notch fa-spin me-2"></i> Scheduling Appointment...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── STEP 3: THANK YOU MESSAGE ── -->
            <div id="dior-step-pane-3" class="dior-step-pane d-none py-3 py-md-4 mb-5">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 my-2 mb-5 bg-white text-center">
                    <div class="mx-auto mb-3 rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center shadow-sm" style="width: 76px; height: 76px;">
                        <i class="fas fa-circle-check fs-1"></i>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1.5 small fw-semibold mb-3 mx-auto">
                        Step 3 Complete &bull; Appointment Confirmed
                    </span>
                    <h3 class="fw-bold text-dark mb-3">Thank You! Your Appointment is Confirmed</h3>
                    <p class="text-secondary small mb-4 mx-auto" style="max-width: 600px; line-height: 1.6;">
                        Your clinical intake form has been recorded, and your appointment has been successfully scheduled with your attending physician. A confirmation email and calendar invitation have been sent.
                    </p>

                    <!-- Appointment Summary Box (Clean Bootstrap 5 Card) -->
                    <div class="card border border-light-subtle rounded-4 p-4 p-md-4 mb-4 mx-auto bg-light text-start shadow-sm" style="max-width: 680px;">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <div class="small text-uppercase text-secondary fw-semibold">Attending Physician</div>
                                <div class="fw-bold text-dark fs-6" id="dior-ty-doctor"><?php echo esc_html($appt_doctor_name); ?></div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="small text-uppercase text-secondary fw-semibold">Consultation Format</div>
                                <div class="fw-bold text-dark fs-6" id="dior-ty-type">Video Visit (HD)</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="small text-uppercase text-secondary fw-semibold">Date &amp; Time</div>
                                <div class="fw-bold text-dark fs-6" id="dior-ty-datetime"><?php echo esc_html(date('M j, Y')); ?> at 09:00 AM</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="small text-uppercase text-secondary fw-semibold">Confirmation ID</div>
                                <div class="fw-bold text-primary fs-6" id="dior-ty-uid">APT-0000</div>
                            </div>
                            <div class="col-12 border-top pt-2">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="small text-uppercase text-secondary fw-semibold">Intake Status</div>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1.5 small fw-semibold">
                                        <i class="fas fa-check-circle me-1"></i> HIPAAtizer Intake Verified &amp; Attached
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap pt-2 pb-2">
                        <button type="button" class="btn btn-primary rounded-3 fw-semibold py-2.5 px-4 shadow-sm" onclick="diorSwitchApptSubTab('today');">
                            <i class="fas fa-calendar-days me-1"></i> View My Appointments
                        </button>
                        <button type="button" class="btn btn-outline-primary rounded-3 fw-semibold py-2.5 px-4" onclick="diorResetBookingFlow();">
                            <i class="fas fa-plus me-1"></i> Book Another Appointment
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Today Appointments -->
        <div class="dior-subtab-panel" id="dior-subtab-today" style="display: block;">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-5">
                <!-- Header & Actions -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-3 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold text-dark mb-0">Today's Appointments</h5>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            <?php echo count($today_mock ?? []); ?> Total
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="input-group input-group-sm rounded-3 overflow-hidden border" style="width: 240px;">
                            <span class="input-group-text bg-white border-0 text-muted ps-2.5"><i class="fas fa-search"></i></span>
                            <input type="text" id="dior-today-search-input"
                                class="form-control bg-white border-0 ps-1 shadow-none" placeholder="Search records..."
                                aria-label="Search box" onkeyup="diorFilterTodayAppointments()">
                        </div>
                        <button type="button" class="btn btn-sm btn-danger rounded-3 px-2.5 shadow-sm" title="Delete Selected"
                            onclick="diorBulkDeleteStaticRows(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-primary rounded-3 px-3 shadow-sm" title="Book Appointment"
                            onclick="diorSwitchApptSubTab('book')">
                            <i class="fas fa-plus me-1"></i> New
                        </button>
                        <button type="button" class="btn btn-sm btn-success rounded-3 px-2.5 shadow-sm" title="Export to CSV"
                            onclick="diorDownloadTodayAppointmentsCSV()">
                            <i class="fas fa-file-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-2.5" title="Refresh Page"
                            onclick="window.location.reload()">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Responsive Table Wrapper -->
                <div class="table-responsive mb-0">
                    <table class="table table-hover align-middle mb-0 dior-appointment-list-table" id="dior-today-appointments-table">
                        <thead class="table-light border-bottom text-uppercase small text-secondary fw-semibold">
                            <tr>
                                <th class="ps-3 py-3" style="width: 40px;"><input type="checkbox" class="form-check-input" onclick="diorBulkDeleteStaticRows(this)"></th>
                                <th class="py-3">Doctor <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Specialization <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Date <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Time <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Treatment <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Contact <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Status <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="text-end pe-3 py-3" style="min-width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dior-today-appointments-tbody" class="border-top-0">
                            <?php
                            $today_mock = [];
                            $today_ymd = current_time('Y-m-d');
                            foreach ((array) $appointments as $apt_row) {
                                $date_raw = $apt_row['date'] ?? ($apt_row['appt_date'] ?? '');
                                $date_ymd = function_exists('dior_normalize_date') ? dior_normalize_date($date_raw) : $date_raw;
                                $status = sanitize_text_field($apt_row['status'] ?? '');
                                if ($date_ymd !== $today_ymd || in_array(strtolower($status), ['cancelled', 'canceled'], true))
                                    continue;
                                $today_mock[] = [
                                    $apt_row['provider'] ?? ($apt_row['doctor_name'] ?? '—'),
                                    $apt_row['provider_spec'] ?? 'General Practice & Internal Medicine',
                                    $date_ymd ? wp_date('M j, Y', strtotime($date_ymd)) : '—',
                                    $apt_row['time'] ?? ($apt_row['appt_time'] ?? '—'),
                                    $apt_row['condition'] ?? ($apt_row['type'] ?? ($apt_row['visit_type'] ?? '—')),
                                    $apt_row['phone'] ?? ($apt_row['contact'] ?? '+1 (800) 555-0199'),
                                    $status ?: 'Scheduled',
                                    $apt_row['avatar'] ?? home_url('/wp-content/uploads/2026/10/diormedical-experts-6.webp')
                                ];
                            }
                            if (empty($today_mock)) {
                                $today_mock = [
                                    [
                                        'Dr. Marcus Sterling, DO',
                                        'General Practice & Internal Medicine',
                                        wp_date('M j, Y', strtotime($today_ymd)),
                                        '11:30 AM - 12:00 PM',
                                        !empty($appt_patient_reason) ? $appt_patient_reason : 'Asthma Consultation (Telehealth)',
                                        !empty($appt_patient_phone) ? $appt_patient_phone : '+1 (800) 555-0199',
                                        'Confirmed',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-6.webp')
                                    ],
                                    [
                                        'Dr. Sophia Reyes, MD',
                                        'Telehealth Urgent Care & Pulmonology',
                                        wp_date('M j, Y', strtotime($today_ymd)),
                                        '03:15 PM - 03:45 PM',
                                        'Clinical Follow-up & Vitals Review',
                                        !empty($appt_patient_phone) ? $appt_patient_phone : '+1 (800) 555-0199',
                                        'Scheduled',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-2.webp')
                                    ]
                                ];
                            }
                            ?>
                            <?php foreach ($today_mock as $tm): ?>
                            <tr>
                                <td class="ps-3 py-3"><input type="checkbox" class="form-check-input"></td>
                                <td class="py-3">
<span class="dior-appointment-doctor-name"><?php echo esc_html($tm[0]); ?></span>
                                </td>
                                <td class="py-3"><span class="text-secondary small fw-medium"><?php echo esc_html($tm[1]); ?></span></td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-calendar text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($tm[2]); ?></span>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-clock text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($tm[3]); ?></span>
                                    </div>
                                </td>
                                <td class="py-3"><span class="badge bg-light text-dark border rounded-pill px-2.5 py-1 small fw-medium"><?php echo esc_html($tm[4]); ?></span></td>
                                <td class="py-3"><span class="text-secondary small"><?php echo esc_html($tm[5]); ?></span></td>
                                <td class="py-3">
                                    <?php
                                    $st_cls = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($tm[6]) === 'cancelled')
                                        $st_cls = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($tm[6]) === 'pending')
                                        $st_cls = 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($tm[6]) === 'scheduled')
                                        $st_cls = 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    ?>
                                    <span class="<?php echo $st_cls; ?>"><?php echo esc_html($tm[6]); ?></span>
                                </td>
                                <td class="text-end pe-3 py-3">
                                    <div class="cell-actions d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                        <button type="button" class="action-icon-btn edit-btn btn" title="Join Video Consultation" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('consultation');">
                                            <i class="fas fa-video text-primary"></i>
                                        </button>
                                        <button type="button" class="action-icon-btn edit-btn btn" title="View Details" onclick="if(typeof diorOpenAppointmentDetail==='function') diorOpenAppointmentDetail('APT-100');">
                                            <i class="fas fa-eye text-secondary"></i>
                                        </button>
                                        <button type="button" class="action-icon-btn delete-btn btn" title="Cancel Appointment" onclick="this.closest('tr').remove();">
                                            <i class="fas fa-trash text-danger"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Summary with Pagination -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 pb-2 px-1 border-top mt-3">
                    <span class="text-secondary small fw-medium" id="dior-today-page-count">0 selected / <?php echo count($today_mock); ?> total</span>
                    <div class="master-pagination d-flex align-items-center gap-1" id="dior-today-pagination">
                        <button type="button" class="dior-page-btn btn" disabled aria-label="Previous page"><i class="fas fa-chevron-left fa-xs"></i></button>
                        <button type="button" class="dior-page-btn active btn">1</button>
                        <button type="button" class="dior-page-btn btn" disabled aria-label="Next page"><i class="fas fa-chevron-right fa-xs"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Upcoming Appointments -->
        <div class="dior-subtab-panel" id="dior-subtab-upcoming" style="display: none;">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-5">
                <!-- Header & Actions -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-3 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold text-dark mb-0">Upcoming Appointments</h5>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            Scheduled
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="input-group input-group-sm rounded-3 overflow-hidden border" style="width: 240px;">
                            <span class="input-group-text bg-white border-0 text-muted ps-2.5"><i class="fas fa-search"></i></span>
                            <input type="text" id="dior-upcoming-search-input"
                                class="form-control bg-white border-0 ps-1 shadow-none" placeholder="Search records..."
                                aria-label="Search box" onkeyup="diorFilterUpcomingAppointments()">
                        </div>
                        <button type="button" class="btn btn-sm btn-danger rounded-3 px-2.5 shadow-sm"
                            id="dior-upcoming-bulk-delete-btn" title="Delete Selected"
                            onclick="diorBulkDeleteUpcomingRows()">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-primary rounded-3 px-3 shadow-sm"
                            title="Book Appointment" onclick="diorSwitchApptSubTab('book')">
                            <i class="fas fa-plus me-1"></i> New
                        </button>
                        <button type="button" class="btn btn-sm btn-success rounded-3 px-2.5 shadow-sm"
                            title="Export to CSV" onclick="diorDownloadUpcomingAppointmentsCSV()">
                            <i class="fas fa-file-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-2.5"
                            title="Refresh Page" onclick="window.location.reload()">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Responsive Table Wrapper -->
                <div class="table-responsive mb-0">
                    <table class="table table-hover align-middle mb-0 dior-appointment-list-table" id="dior-upcoming-appointments-table">
                        <thead class="table-light border-bottom text-uppercase small text-secondary fw-semibold">
                            <tr>
                                <th class="ps-3 py-3" style="width: 40px;">
                                    <input type="checkbox" id="dior-select-all-upcoming"
                                        class="form-check-input"
                                        onclick="diorToggleSelectAllUpcoming(this)">
                                </th>
                                <th class="py-3">Doctor <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Date <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Time <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Condition <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Status <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Notes <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="text-end pe-3 py-3" style="min-width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dior-upcoming-appointments-tbody" class="border-top-0">
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
                                    $apt_row['notes'] ?? '—',
                                    $apt_row['avatar'] ?? home_url('/wp-content/uploads/2026/10/diormedical-experts-6.webp')
                                ];
                            }
                            if (empty($upcoming_mock)) {
                                $up_date_1 = date('Y-m-d', strtotime('+2 days'));
                                $up_date_2 = date('Y-m-d', strtotime('+5 days'));
                                $up_date_3 = date('Y-m-d', strtotime('+10 days'));
                                $upcoming_mock = [
                                    [
                                        'Dr. Marcus Sterling, DO',
                                        wp_date('M j, Y', strtotime($up_date_1)),
                                        '02:00 PM - 02:30 PM',
                                        !empty($appt_patient_reason) ? $appt_patient_reason : 'Asthma Maintenance Consultation',
                                        'Confirmed',
                                        'Routine 48-hour follow-up on prescribed therapy & inhaler usage',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-6.webp')
                                    ],
                                    [
                                        'Dr. Ethan Walker, MD',
                                        wp_date('M j, Y', strtotime($up_date_2)),
                                        '10:15 AM - 10:45 AM',
                                        'Allergy & Respiratory Review',
                                        'Scheduled',
                                        'Review respiratory panel test results and environmental allergy triggers',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-3.webp')
                                    ],
                                    [
                                        'Dr. Evelyn Vance, MD',
                                        wp_date('M j, Y', strtotime($up_date_3)),
                                        '04:00 PM - 04:30 PM',
                                        'Wellness & Telehealth Check-up',
                                        'Pending',
                                        'General telehealth assessment and treatment progress verification',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-4.webp')
                                    ],
                                    [
                                        'Dr. Sophia Reyes, MD',
                                        wp_date('M j, Y', strtotime('+14 days')),
                                        '11:00 AM - 11:30 AM',
                                        'Pulmonology Spirometry Follow-up',
                                        'Confirmed',
                                        'Evaluate peak flow meter readings and adjust bronchodilator regimen',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-2.webp')
                                    ],
                                    [
                                        'Dr. James Chen, MD',
                                        wp_date('M j, Y', strtotime('+20 days')),
                                        '01:30 PM - 02:00 PM',
                                        'Cardiopulmonary Assessment',
                                        'Scheduled',
                                        'Comprehensive exercise tolerance and cardiovascular risk evaluation',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-1.webp')
                                    ],
                                    [
                                        'Dr. Liam Bennett, MD',
                                        wp_date('M j, Y', strtotime('+28 days')),
                                        '09:00 AM - 09:30 AM',
                                        'Routine Preventive Health Check',
                                        'Scheduled',
                                        'Annual wellness consultation and metabolic blood panel review',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-5.webp')
                                    ]
                                ];
                            }
                            ?>
                            <?php foreach ($upcoming_mock as $um): ?>
                            <tr>
                                <td class="ps-3">
                                    <input type="checkbox"
                                        class="upcoming-row-checkbox form-check-input"
                                        onclick="diorUpdateUpcomingSelectedCount()">
                                </td>
                                <td>
<span class="dior-appointment-doctor-name"><?php echo esc_html($um[0]); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-calendar text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($um[1]); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-clock text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($um[2]); ?></span>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border rounded-pill px-2.5 py-1 small fw-medium"><?php echo esc_html($um[3]); ?></span></td>
                                <td>
                                    <?php
                                    $st_cls = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($um[4]) === 'cancelled')
                                        $st_cls = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($um[4]) === 'pending')
                                        $st_cls = 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($um[4]) === 'scheduled')
                                        $st_cls = 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    ?>
                                    <span class="<?php echo $st_cls; ?>"><?php echo esc_html($um[4]); ?></span>
                                </td>
                                <td><span class="text-secondary small text-truncate d-inline-block" style="max-width: 250px;"><?php echo esc_html($um[5]); ?></span></td>
                                <td class="text-end pe-3">
                                    <div class="cell-actions d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                        <button type="button" class="action-icon-btn edit-btn btn"
                                            title="Join Video Consultation" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('consultation');">
                                            <i class="fas fa-video text-primary"></i>
                                        </button>
                                        <button type="button" class="action-icon-btn edit-btn btn"
                                            title="Reschedule Appointment" onclick="if(typeof diorOpenRescheduleModal==='function') diorOpenRescheduleModal('APT-101', '<?php echo esc_js($um[0]); ?>');">
                                            <i class="fas fa-calendar-days text-info"></i>
                                        </button>
                                        <button type="button" class="action-icon-btn edit-btn btn"
                                            title="View Details" onclick="if(typeof diorOpenAppointmentDetail==='function') diorOpenAppointmentDetail('APT-101');">
                                            <i class="fas fa-eye text-secondary"></i>
                                        </button>
                                        <button type="button" class="action-icon-btn delete-btn btn"
                                            title="Cancel Appointment" onclick="this.closest('tr').remove(); if(typeof diorUpdateUpcomingSelectedCount==='function') diorUpdateUpcomingSelectedCount();">
                                            <i class="fas fa-trash text-danger"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Summary with Pagination -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 px-1 border-top mt-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-secondary small fw-medium" id="dior-upcoming-page-count">0 selected / <?php echo count($upcoming_mock); ?> total</span>
                        <span id="dior-upcoming-selected-counter" class="d-none">0 selected / <?php echo count($upcoming_mock); ?> total</span>
                    </div>
                    <div class="master-pagination d-flex align-items-center gap-1" id="dior-upcoming-pagination">
                        <button type="button" class="dior-page-btn btn" disabled aria-label="Previous page"><i class="fas fa-chevron-left fa-xs"></i></button>
                        <button type="button" class="dior-page-btn active btn" onclick="diorGoUpcomingPage(1)">1</button>
                        <button type="button" class="dior-page-btn btn" onclick="diorGoUpcomingPage(2)">2</button>
                        <button type="button" class="dior-page-btn btn" aria-label="Next page" onclick="diorGoUpcomingPage(2)"><i class="fas fa-chevron-right fa-xs"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Past Appointments -->
        <div class="dior-subtab-panel" id="dior-subtab-past" style="display: none;">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-5">
                <!-- Header & Actions -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-3 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold text-dark mb-0">Past Appointments</h5>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            History
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="input-group input-group-sm rounded-3 overflow-hidden border" style="width: 240px;">
                            <span class="input-group-text bg-white border-0 text-muted ps-2.5"><i class="fas fa-search"></i></span>
                            <input type="text" id="dior-past-search-input"
                                class="form-control bg-white border-0 ps-1 shadow-none" placeholder="Search records..."
                                aria-label="Search box" onkeyup="diorFilterPastAppointments()">
                        </div>
                        <button type="button" class="btn btn-sm btn-danger rounded-3 px-2.5 shadow-sm" title="Delete Selected"
                            onclick="diorBulkDeleteStaticRows(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-success rounded-3 px-2.5 shadow-sm"
                            title="Export to CSV" onclick="diorDownloadPastAppointmentsCSV()">
                            <i class="fas fa-file-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-2.5"
                            title="Refresh Page" onclick="window.location.reload()">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Responsive Table Wrapper -->
                <div class="table-responsive mb-0">
                    <table class="table table-hover align-middle mb-0 dior-appointment-list-table" id="dior-past-appointments-table">
                        <thead class="table-light border-bottom text-uppercase small text-secondary fw-semibold">
                            <tr>
                                <th class="ps-3 py-3" style="width: 40px;"><input type="checkbox" class="form-check-input" onclick="diorBulkDeleteStaticRows(this)"></th>
                                <th>Doctor <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Date <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Time <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Email <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Mobile <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Condition <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Format <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Follow-up <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="text-end pe-3" style="min-width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dior-past-appointments-tbody" class="border-top-0">
                            <?php
                            $past_mock = [];
                            $today_ymd = current_time('Y-m-d');
                            foreach ((array) $appointments as $apt_row) {
                                $date_raw = $apt_row['date'] ?? ($apt_row['appt_date'] ?? '');
                                $date_ymd = function_exists('dior_normalize_date') ? dior_normalize_date($date_raw) : $date_raw;
                                $status = sanitize_text_field($apt_row['status'] ?? '');
                                if (!$date_ymd || ($date_ymd >= $today_ymd && !in_array(strtolower($status), ['completed', 'done', 'past'], true)))
                                    continue;
                                $past_mock[] = [
                                    $apt_row['provider'] ?? ($apt_row['doctor_name'] ?? '—'),
                                    wp_date('M j, Y', strtotime($date_ymd)),
                                    $apt_row['time'] ?? ($apt_row['appt_time'] ?? '—'),
                                    $apt_row['email'] ?? '—',
                                    $apt_row['phone'] ?? '—',
                                    $apt_row['condition'] ?? '—',
                                    $apt_row['type'] ?? ($apt_row['visit_type'] ?? 'Consultation'),
                                    '—',
                                    $apt_row['avatar'] ?? home_url('/wp-content/uploads/2026/10/diormedical-experts-6.webp')
                                ];
                            }
                            if (empty($past_mock)) {
                                $past_date_1 = date('Y-m-d', strtotime('-7 days'));
                                $past_date_2 = date('Y-m-d', strtotime('-21 days'));
                                $past_date_3 = date('Y-m-d', strtotime('-35 days'));
                                $past_date_4 = date('Y-m-d', strtotime('-48 days'));
                                $past_date_5 = date('Y-m-d', strtotime('-60 days'));
                                $past_date_6 = date('Y-m-d', strtotime('-90 days'));
                                $past_mock = [
                                    [
                                        'Dr. Marcus Sterling, DO',
                                        wp_date('M j, Y', strtotime($past_date_1)),
                                        '09:30 AM - 10:00 AM',
                                        !empty($appt_patient_email) ? $appt_patient_email : 'patient@diormedical.com',
                                        !empty($appt_patient_phone) ? $appt_patient_phone : '+1 (800) 555-0199',
                                        'Initial Telehealth Consultation',
                                        'Video Visit (HD)',
                                        wp_date('M j, Y', strtotime('+2 days')),
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-6.webp')
                                    ],
                                    [
                                        'Dr. James Chen, MD',
                                        wp_date('M j, Y', strtotime($past_date_2)),
                                        '02:15 PM - 02:45 PM',
                                        !empty($appt_patient_email) ? $appt_patient_email : 'patient@diormedical.com',
                                        !empty($appt_patient_phone) ? $appt_patient_phone : '+1 (800) 555-0199',
                                        'Seasonal Respiratory Evaluation',
                                        'Phone Consultation',
                                        'Completed',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-1.webp')
                                    ],
                                    [
                                        'Dr. Sophia Reyes, MD',
                                        wp_date('M j, Y', strtotime($past_date_3)),
                                        '11:00 AM - 11:30 AM',
                                        !empty($appt_patient_email) ? $appt_patient_email : 'patient@diormedical.com',
                                        !empty($appt_patient_phone) ? $appt_patient_phone : '+1 (800) 555-0199',
                                        'Allergy Panel Discussion',
                                        'Video Visit (HD)',
                                        'Completed',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-2.webp')
                                    ],
                                    [
                                        'Dr. Ethan Walker, MD',
                                        wp_date('M j, Y', strtotime($past_date_4)),
                                        '03:45 PM - 04:15 PM',
                                        !empty($appt_patient_email) ? $appt_patient_email : 'patient@diormedical.com',
                                        !empty($appt_patient_phone) ? $appt_patient_phone : '+1 (800) 555-0199',
                                        'Bronchial Therapy Check',
                                        'Video Visit (HD)',
                                        'Completed',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-3.webp')
                                    ],
                                    [
                                        'Dr. Evelyn Vance, MD',
                                        wp_date('M j, Y', strtotime($past_date_5)),
                                        '10:30 AM - 11:00 AM',
                                        !empty($appt_patient_email) ? $appt_patient_email : 'patient@diormedical.com',
                                        !empty($appt_patient_phone) ? $appt_patient_phone : '+1 (800) 555-0199',
                                        'Annual Wellness Checkup',
                                        'Video Visit (HD)',
                                        'Completed',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-4.webp')
                                    ],
                                    [
                                        'Dr. Liam Bennett, MD',
                                        wp_date('M j, Y', strtotime($past_date_6)),
                                        '01:00 PM - 01:30 PM',
                                        !empty($appt_patient_email) ? $appt_patient_email : 'patient@diormedical.com',
                                        !empty($appt_patient_phone) ? $appt_patient_phone : '+1 (800) 555-0199',
                                        'Baseline Respiratory Panel',
                                        'In-Clinic Visit',
                                        'Completed',
                                        home_url('/wp-content/uploads/2026/10/diormedical-experts-5.webp')
                                    ]
                                ];
                            }
                            ?>
                            <?php foreach ($past_mock as $pm): ?>
                            <tr>
                                <td class="ps-3"><input type="checkbox" class="form-check-input"></td>
                                <td>
<span class="dior-appointment-doctor-name"><?php echo esc_html($pm[0]); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-calendar text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($pm[1]); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-clock text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($pm[2]); ?></span>
                                    </div>
                                </td>
                                <td><span class="text-secondary small"><?php echo esc_html($pm[3]); ?></span></td>
                                <td><span class="text-secondary small"><?php echo esc_html($pm[4]); ?></span></td>
                                <td><span class="badge bg-light text-dark border rounded-pill px-2.5 py-1 small fw-medium"><?php echo esc_html($pm[5]); ?></span></td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold"><?php echo esc_html($pm[6]); ?></span></td>
                                <td>
                                    <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 small"><?php echo esc_html($pm[7]); ?></span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="cell-actions d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                        <button type="button" class="action-icon-btn edit-btn btn" title="View Consultation Summary" onclick="if(typeof diorOpenAppointmentDetail==='function') diorOpenAppointmentDetail('APT-99');">
                                            <i class="fas fa-file-medical text-secondary"></i>
                                        </button>
                                        <button type="button" class="action-icon-btn edit-btn btn" title="Book Again" onclick="if(typeof diorSwitchSubtab==='function') diorSwitchSubtab('book');">
                                            <i class="fas fa-calendar-plus text-primary"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Summary with Pagination -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 pb-2 px-1 border-top mt-3">
                    <span class="text-secondary small fw-medium" id="dior-past-page-count">0 selected / <?php echo count($past_mock); ?> total</span>
                    <div class="master-pagination d-flex align-items-center gap-1" id="dior-past-pagination">
                        <button type="button" class="dior-page-btn btn" disabled aria-label="Previous page"><i class="fas fa-chevron-left fa-xs"></i></button>
                        <button type="button" class="dior-page-btn active btn" onclick="diorGoPastPage(1)">1</button>
                        <button type="button" class="dior-page-btn btn" onclick="diorGoPastPage(2)">2</button>
                        <button type="button" class="dior-page-btn btn" aria-label="Next page" onclick="diorGoPastPage(2)"><i class="fas fa-chevron-right fa-xs"></i></button>
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