
<div class="dior-wrap dior-patient-portal-wrap" id="dior-patient-portal-app">

    <!-- Top Emergency Banner -->
    <div class="dior-top-emergency-banner">
        This is not for medical emergencies. Call 911 or go to the nearest ER.
    </div>

    <!-- Mobile Drawer Backdrop -->
    <div class="dior-drawer-backdrop" id="dior-drawer-backdrop" onclick="diorCloseMobileDrawer()"></div>

    <div class="dior-app">

        <!-- ============================================================== -->
        <!-- SIDEBAR NAVIGATION -->
        <!-- ============================================================== -->
        <aside class="dior-side" id="dior-sidebar">
            <div class="dior-side-header">
                <div class="brand">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="brand-link">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Dior Medical" class="brand-logo">
                    </a>
                </div>
                <!-- <button type="button" class="dior-side-close btn" id="dior-side-close" onclick="diorCloseMobileDrawer()"
                    aria-label="Close sidebar">
                    <i class="fas fa-xmark"></i>
                </button> -->
            </div>

            <!-- Navigation Links -->
            <nav class="dior-side-nav">
                <div class="grp">
                    <button type="button" class="dior-nav-btn active btn" data-tab="overview">
                        <i class="fas fa-gauge-high"></i>
                        <span class="nav-label">Dashboard</span>
                    </button>
                    <div class="dior-nav-item-has-children" id="dior-appt-dropdown-wrap">
                        <button type="button" class="dior-nav-btn btn"
                            onclick="var w=document.getElementById('dior-appt-dropdown-wrap');w.classList.toggle('open');this.querySelector('.fa-chevron-down').style.transform=w.classList.contains('open')?'rotate(180deg)':'rotate(0deg)';diorSwitchTab('appointments');diorSwitchApptSubTab('calendar');">
                            <i class="far fa-calendar-alt"></i>
                            <span class="nav-label">Appointment Calendar</span>
                            <i class="fas fa-chevron-down dior-ic-fdf55d6c0a"></i>
                        </button>
                        <div class="dior-nav-dropdown">
                            <a href="#" class="dior-nav-dropdown-item active" data-appt-subtab="calendar"><i
                                    class="fas fa-angle-right"></i> Appointment Calendar</a>
                            <a href="#" class="dior-nav-dropdown-item" data-appt-subtab="book"><i
                                    class="fas fa-angle-right"></i> Book Appointment</a>
                            <a href="#" class="dior-nav-dropdown-item" data-appt-subtab="today"><i
                                    class="fas fa-angle-right"></i> Today Appointments</a>
                            <a href="#" class="dior-nav-dropdown-item" data-appt-subtab="upcoming"><i
                                    class="fas fa-angle-right"></i> Upcoming Appointments</a>
                            <a href="#" class="dior-nav-dropdown-item" data-appt-subtab="past"><i
                                    class="fas fa-angle-right"></i> Past Appointments</a>
                        </div>
                    </div>
                    <button type="button" class="dior-nav-btn btn" data-tab="docs_meds">
                        <i class="fas fa-prescription-bottle-medical"></i>
                        <span class="nav-label">Prescriptions</span>
                    </button>
                    <button type="button" class="dior-nav-btn btn" data-tab="telemedicine">
                        <i class="fas fa-video"></i>
                        <span class="nav-label">Telemedicine</span>
                    </button>
                    <button type="button" class="dior-nav-btn btn" data-tab="medical_record">
                        <i class="fas fa-notes-medical"></i>
                        <span class="nav-label">Medical Record</span>
                    </button>
                    <button type="button" class="dior-nav-btn btn" data-tab="payments">
                        <i class="fas fa-wallet"></i>
                        <span class="nav-label">Billing &amp; Payment</span>
                    </button>
                    <button type="button" class="dior-nav-btn btn" data-tab="insurance">
                        <i class="fas fa-shield-heart"></i>
                        <span class="nav-label">Insurance Claim</span>
                    </button>
                    <button type="button" class="dior-nav-btn btn" data-tab="documents">
                        <i class="fas fa-file-lines"></i>
                        <span class="nav-label">Documents &amp; Report</span>
                    </button>
                    <button type="button" class="dior-nav-btn btn" data-tab="emergency">
                        <i class="fas fa-kit-medical"></i>
                        <span class="nav-label">Emergency support</span>
                    </button>
                    <button type="button" class="dior-nav-btn btn" data-tab="feedback">
                        <i class="fas fa-comments"></i>
                        <span class="nav-label">Feedback &amp; Support</span>
                    </button>
                    <button type="button" class="dior-nav-btn btn" data-tab="notifications">
                        <i class="fas fa-bell"></i>
                        <span class="nav-label">Notification</span>
                    </button>
                    <button type="button" class="dior-nav-btn btn" data-tab="consultation">
                        <i class="fas fa-door-open"></i>
                        <span class="nav-label">Consultation Room</span>
                    </button>
                    <button type="button" class="dior-nav-btn btn" data-tab="settings">
                        <i class="fas fa-gear"></i>
                        <span class="nav-label">Settings</span>
                    </button>
                </div>
            </nav>

            <!-- Bottom Sidebar User Profile Card -->
            <div class="dior-sidebar-bottom-profile">
                <div class="dior-sidebar-avatar-wrap" id="dior-sidebar-avatar-box">
                    <?php if (!empty($profile['avatar_url'])): ?>
                        <img id="dior-sidebar-avatar-img" src="<?php echo esc_url($profile['avatar_url']); ?>"
                            alt="<?php echo esc_attr($profile['full_name']); ?>">
                    <?php else: ?>
                        <img id="dior-sidebar-avatar-img" src="<?php echo esc_url(get_avatar_url(get_current_user_id(), ['size' => 120])); ?>"
                            alt="<?php echo esc_attr($profile['full_name'] ?? 'User'); ?>">
                    <?php endif; ?>
                </div>
                <div class="dior-sidebar-user-info">
                    <strong
                        class="dior-sidebar-user-name"><?php echo esc_html(!empty($profile['full_name']) ? $profile['full_name'] : 'Mia Song'); ?></strong>
                    <span class="dior-sidebar-user-status">Available</span>
                </div>
            </div>
        </aside>

        <!-- ============================================================== -->
        <!-- MAIN CONTENT AREA -->
        <!-- ============================================================== -->
        <main class="dior-main-content">

            <!-- Top Bar Header -->
            <header class="dior-topbar">
                <div class="dior-topbar-left">
                    <button type="button" class="dior-mobile-menu-toggle btn" id="dior-mobile-menu-toggle"
                        onclick="diorOpenMobileDrawer()" aria-label="Open navigation menu">
                        <i class="fas fa-bars"></i>
                    </button>

                    <!-- Search Bar (Clean Reference UI) -->
                    <div class="dior-top-search">
                        <i class="bi bi-search"></i>
                        <input type="text" placeholder="Search appointments, prescriptions, records. .">
                    </div>
                </div>

                <div class="dior-topbar-right">
                    <!-- Messages Icon -->
                    <button type="button" class="dior-msg-btn dior-ic-f2136e6b50 btn" aria-label="Messages">
                        <i class="far fa-envelope"></i>
                    </button>

                    <!-- Notification Bell Dropdown -->
                    <div class="dior-notif-wrap" id="dior-notif-dropdown-wrap">
                        <button type="button" class="dior-notif-btn btn" onclick="diorToggleNotifDropdown(event)"
                            aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="dior-notif-indicator unread-badge-count"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dior-notif-dropdown" id="dior-notif-dropdown">
                            <div class="dior-notif-header">
                                <h4>Notifications</h4>
                                <button type="button" id="dior-quick-mark-read">Mark all read</button>
                            </div>
                            <div class="dior-notif-list">
                                <?php if (empty($notifications)): ?>
                                    <div class="dior-ic-00e4601aa1">
                                        <i class="fas fa-bell-slash dior-ic-41fd079087"></i>
                                        No new notifications
                                    </div>
                                <?php else: ?>
                                    <?php foreach (array_slice($notifications, 0, 5) as $n):
                                        $target_tab = !empty($n['action_url']) ? str_replace('#tab=', '', $n['action_url']) : 'notifications';
                                        ?>
                                        <div class="dior-notif-item <?php echo empty($n['is_read']) ? 'unread' : ''; ?>"
                                            data-notif-id="<?php echo esc_attr($n['id']); ?>"
                                            data-switch-tab="<?php echo esc_attr($target_tab); ?>" class="dior-ic-7f6191e48d">
                                            <div class="notif-icon"><i
                                                    class="fas <?php echo esc_attr($n['icon'] ?? 'fa-bell'); ?>"></i></div>
                                            <div class="notif-body">
                                                <strong><?php echo esc_html($n['title']); ?></strong>
                                                <p><?php echo esc_html(mb_substr($n['message'], 0, 45) . (mb_strlen($n['message']) > 45 ? '...' : '')); ?>
                                                </p>
                                                <span
                                                    class="notif-time"><?php echo esc_html(dior_format_notification_time($n)); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="dior-notif-footer">
                                <button type="button" data-switch-tab="notifications">View All &rarr;</button>
                            </div>
                        </div>
                    </div>

                    <!-- User Quick Profile Pill & Dropdown -->
                    <div class="dior-user-pill-wrap">
                        <button type="button" class="dior-user-pill-btn btn" onclick="diorToggleProfileDropdown(event)"
                            aria-label="User Profile">
                            <div class="dior-user-pill-card">
                                <div class="dior-pill-avatar" id="dior-top-pill-avatar">
                                    <?php if (!empty($profile['avatar_url'])): ?>
                                        <img id="dior-topbar-avatar-img" src="<?php echo esc_url($profile['avatar_url']); ?>"
                                            alt="<?php echo esc_attr($profile['full_name']); ?>">
                                    <?php else: ?>
                                        <img id="dior-topbar-avatar-img" src="<?php echo esc_url(get_avatar_url(get_current_user_id(), ['size' => 120])); ?>"
                                            alt="<?php echo esc_attr($profile['full_name'] ?? 'User'); ?>">
                                    <?php endif; ?>
                                    <span class="dior-online-dot"></span>
                                </div>
                                <div class="dior-pill-user-meta">
                                    <strong
                                        class="dior-pill-user-name"><?php echo esc_html(!empty($profile['full_name']) ? $profile['full_name'] : 'Mia Song'); ?></strong>
                                    <span class="dior-pill-user-status">Available</span>
                                </div>
                            </div>
                        </button>

                        <div class="dior-profile-dropdown" id="dior-profile-dropdown">
                            <div class="dior-profile-dropdown-header">
                                HELLO <?php echo esc_html(strtoupper($profile['full_name'])); ?>..
                            </div>
                            <ul class="dior-profile-dropdown-menu">
                                <li>
                                    <button type="button" data-switch-tab="settings">
                                        <i class="far fa-user"></i> Profile
                                    </button>
                                </li>
                                <li>
                                    <button type="button" data-switch-tab="settings">
                                        <i class="far fa-envelope"></i> Email
                                    </button>
                                </li>
                                <li>
                                    <button type="button" data-switch-tab="settings">
                                        <i class="fas fa-gear"></i> Settings
                                    </button>
                                </li>
                                <li>
                                    <a href="<?php echo esc_url(wp_logout_url(home_url('/diro-login/'))); ?>"
                                        class="dior-logout-text-btn">
                                        <i class="fas fa-arrow-right-from-bracket"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>

            <!-- TAB PANELS CONTAINER -->
            <div class="dior-tab-container">

                <?php
                $is_prof_complete = Dior_Patient_Portal_Data::is_profile_complete($user_id);
                $missing_fields_list = Dior_Patient_Portal_Data::get_missing_profile_fields($user_id);
                ?>
                <?php if (!$is_prof_complete): ?>
                    <!-- Action Required Alert Banner for Incomplete Profile -->
                    <div class="dior-profile-incomplete-alert dior-ic-c03948b3c3" id="dior-profile-incomplete-banner">
                        <div class="dior-ic-29bdac4ec4">
                            <div class="dior-ic-3fb01cc77f">
                                <i class="fas fa-user-pen"></i>
                            </div>
                            <div>
                                <strong class="dior-ic-11190ca3a8">
                                    Action Required: Complete Your Personal Profile
                                </strong>
                                <span class="dior-ic-acb9b5ec47">
                                    Please complete your required personal information
                                    (<strong><?php echo esc_html(implode(', ', $missing_fields_list)); ?></strong>) to
                                    unlock appointment booking and telehealth consultations.
                                </span>
                            </div>
                        </div>
                        <button type="button" class="dior-btn-gold-primary dior-ic-4ee0445c2a btn" onclick="diorOpenHipaaPopup()">
                            <i class="fas fa-id-card"></i> Complete Profile Now
                        </button>
                    </div>
                <?php endif; ?>

                <!-- ============================================================== -->
                <!-- 0. HOME / OVERVIEW TAB (Real Functional Patient Data + Clean UI) -->
                <!-- ============================================================== -->
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/overview.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/_design-static-data.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/settings.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/appointments.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/payments.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/insurance.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/docs_meds.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/telemedicine.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/medical_record.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/documents.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/emergency.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/consultation.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/questionnaire.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/feedback.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/notifications.php'; ?>

            </div> <!-- /.dior-tab-container -->

        </main>
    </div> <!-- /.dior-app -->


    <!-- ============================================================== -->
    <!-- MODALS -->
    <!-- ============================================================== -->

    <!-- 1. BOOK NEW APPOINTMENT MODAL (HIPAAtizer Form Integrated) -->
    <div class="dior-modal-overlay" id="modal-book-appointment">
        <div class="dior-modal-dialog dior-modal-lg dior-ic-ee0c6b0194">
            <div class="dior-modal-header dior-ic-78529f928a">
                <div>
                    <h3 class="dior-ic-66a69ef8e4"><i class="fas fa-calendar-plus"></i> Book New Visit</h3>
                    <span class="dior-ic-23927a389b"><i class="fas fa-shield-heart dior-ic-236b5f0eb0"></i>
                        HIPAA-Compliant Medical Intake &amp; Booking</span>
                </div>
                <button type="button" class="dior-modal-close btn"
                    onclick="diorCloseModal('modal-book-appointment')">&times;</button>
            </div>
            <div class="dior-modal-body dior-ic-1d15569872">
                <iframe id="hipaatizer-book-visit-iframe"
                    src="https://app.hipaatizer.com/workflow/01a11515-b491-79dc-b59b-e4be2a345a49"
                    allow="microphone; camera; payment" title="HIPAAtizer Booking Form" class="dior-ic-a52858ae73">
                </iframe>
                <div class="dior-ic-44a70a0420">
                    <?php echo do_shortcode('[hipaatizer id="01a11515-b491-79dc-b59b-e4be2a345a49"]'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. RESCHEDULE MODAL -->
    <div class="dior-modal-overlay" id="modal-reschedule-appointment">
        <div class="dior-modal-dialog">
            <div class="dior-modal-header">
                <h3><i class="far fa-calendar"></i> Reschedule Consultation</h3>
                <button type="button" class="dior-modal-close btn"
                    onclick="diorCloseModal('modal-reschedule-appointment')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-reschedule-appt">
                    <input type="hidden" id="resched_appt_id" name="appt_id" value="">

                    <div id="resched_appt_summary" class="dior-ic-f8cd81dec3">
                    </div>

                    <p class="modal-intro-text">Select a new date and available time slot for your appointment:</p>

                    <div class="dior-form-row-2">
                        <div class="dior-form-group">
                            <label for="resched_date">New Date <span class="req">*</span></label>
                            <input type="date" id="resched_date" name="date" min="<?php echo current_time('Y-m-d'); ?>"
                                value="<?php echo current_time('Y-m-d'); ?>" required>
                        </div>
                        <div class="dior-form-group">
                            <label for="resched_time">New Time Slot <span class="req">*</span></label>
                            <select id="resched_time" name="time" required>
                                <option value="09:00 AM PST">09:00 AM PST</option>
                                <option value="10:15 AM PST">10:15 AM PST</option>
                                <option value="11:30 AM PST">11:30 AM PST</option>
                                <option value="01:00 PM PST">01:00 PM PST</option>
                                <option value="02:30 PM PST">02:30 PM PST</option>
                                <option value="04:00 PM PST">04:00 PM PST</option>
                                <option value="05:30 PM PST">05:30 PM PST</option>
                                <option value="07:00 PM PST">07:00 PM PST</option>
                            </select>
                        </div>
                    </div>

                    <div class="dior-form-group dior-ic-fb6bc20a7f">
                        <label for="resched_notes">Reason for Rescheduling (Optional)</label>
                        <input type="text" id="resched_notes" name="notes"
                            placeholder="e.g. Schedule conflict, personal travel" class="dior-ic-3638e808c4 form-control">
                    </div>

                    <div class="dior-modal-footer dior-ic-78874ad7d4">
                        <button type="button" class="dior-btn-gold-secondary btn"
                            onclick="diorCloseModal('modal-reschedule-appointment')">Keep Current Time</button>
                        <button type="submit" id="dior-btn-submit-resched" class="dior-btn-gold-primary btn">
                            <i class="fas fa-check"></i> Save New Time Slot
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 3. CANCEL APPOINTMENT MODAL -->
    <div class="dior-modal-overlay" id="modal-cancel-appointment">
        <div class="dior-modal-dialog">
            <div class="dior-modal-header">
                <h3><i class="fas fa-kit-medical dior-ic-557c97e141"></i> Cancel Consultation</h3>
                <button type="button" class="dior-modal-close btn"
                    onclick="diorCloseModal('modal-cancel-appointment')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-cancel-appt">
                    <input type="hidden" id="cancel_appt_id" name="appt_id" value="">

                    <div id="cancel_appt_summary" class="dior-ic-e7416a9b55">
                    </div>

                    <p class="dior-ic-e261ffabcb">
                        Are you sure you wish to cancel this consultation? You can reschedule to a future date at no
                        penalty, or schedule a new visit when needed.
                    </p>

                    <div class="dior-form-group">
                        <label for="cancel_reason">Reason for Cancellation (Optional)</label>
                        <select id="cancel_reason" name="reason">
                            <option value="Schedule conflict">Schedule conflict</option>
                            <option value="Symptoms resolved">Symptoms resolved</option>
                            <option value="Went to in-person clinic">Went to in-person clinic / ER</option>
                            <option value="Provider preference">Provider preference</option>
                            <option value="Other reason">Other reason</option>
                        </select>
                    </div>

                    <div class="dior-modal-footer dior-ic-78874ad7d4">
                        <button type="button" class="dior-btn-gold-secondary btn"
                            onclick="diorCloseModal('modal-cancel-appointment')">Keep Appointment</button>
                        <button type="submit" id="dior-btn-submit-cancel" class="dior-btn-danger dior-ic-02e790ac8d btn">
                            <i class="fas fa-xmark"></i> Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 3B. APPOINTMENT REMINDER MODAL -->
    <div class="dior-modal-overlay" id="modal-appointment-reminder">
        <div class="dior-modal-dialog">
            <div class="dior-modal-header">
                <h3><i class="fas fa-bell dior-ic-b2113a5eea"></i> Send Appointment Reminder</h3>
                <button type="button" class="dior-modal-close btn"
                    onclick="diorCloseModal('modal-appointment-reminder')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-send-reminder">
                    <input type="hidden" id="reminder_appt_id" name="appt_id" value="">

                    <div id="reminder_appt_summary" class="dior-ic-d8b0d6eace">
                    </div>

                    <p class="dior-ic-e261ffabcb">
                        Send an urgent clinical consultation reminder with scheduled date/time, patient preparation
                        checklist, and direct video consultation room link.
                    </p>

                    <div class="dior-ic-2895f37e6b">
                        <div class="dior-ic-55132245a6">
                            <i class="fas fa-tower-broadcast dior-ic-1dc44a1b66"></i> Active
                            Notification Channels:
                        </div>
                        <div class="dior-ic-dce101ce6e">
                            <label class="dior-ic-8b80aded64">
                                <input type="checkbox" checked disabled class="dior-ic-ce15c82e4d form-check-input">
                                <span><strong>Email Notification</strong> (Delivered to both patient & attending
                                    doctor)</span>
                            </label>
                            <label class="dior-ic-8b80aded64">
                                <input type="checkbox" checked disabled class="dior-ic-ce15c82e4d form-check-input">
                                <span><strong>Dashboard In-App Alert</strong> (Added to notification inbox for patient &
                                    doctor)</span>
                            </label>
                        </div>
                    </div>

                    <div class="dior-modal-footer dior-ic-78874ad7d4">
                        <button type="button" class="dior-btn-gold-secondary btn"
                            onclick="diorCloseModal('modal-appointment-reminder')">Close</button>
                        <button type="submit" id="dior-btn-submit-reminder"
                            class="dior-btn-gold-primary dior-ic-2e8ec92046 btn">
                            <i class="fas fa-bell"></i> Send Reminder Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 4. QUESTIONNAIRE & INTAKE MODAL (With E-Sign) -->
    <div class="dior-modal-overlay" id="modal-questionnaire-intake">
        <div class="dior-modal-dialog dior-modal-lg">
            <div class="dior-modal-header">
                <h3 id="modal-qn-title"><i class="fas fa-clipboard-question"></i> Clinical Intake & E-Sign</h3>
                <button type="button" class="dior-modal-close btn"
                    onclick="diorCloseModal('modal-questionnaire-intake')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-submit-intake" class="dior-portal-form">
                    <input type="hidden" id="qn_input_id" name="qn_id" value="">
                    <input type="hidden" id="qn_input_condition" name="condition" value="">

                    <div class="dior-form-group">
                        <label for="qn_input_symptoms">Describe Your Symptoms & Duration <span
                                class="req">*</span></label>
                        <textarea id="qn_input_symptoms" name="symptoms" rows="3"
                            placeholder="When did symptoms start? Rate severity (mild/moderate/severe)..."
                            required></textarea>
                    </div>

                    <div class="dior-form-row-2">
                        <div class="dior-form-group">
                            <label for="qn_input_allergies">Known Drug Allergies</label>
                            <input type="text" id="qn_input_allergies" name="allergies"
                                placeholder="e.g. Penicillin, Sulfa, None known">
                        </div>
                        <div class="dior-form-group">
                            <label for="qn_input_meds">Current Medications / Supplements</label>
                            <input type="text" id="qn_input_meds" name="current_meds"
                                placeholder="e.g. Lisinopril 10mg, Multivitamin">
                        </div>
                    </div>

                    <div class="dior-form-group">
                        <label for="qn_input_history">Relevant Medical Conditions</label>
                        <input type="text" id="qn_input_history" name="medical_history"
                            placeholder="e.g. Asthma, High Blood Pressure, Diabetes, None">
                    </div>

                    <!-- Document / Photo Upload -->
                    <div class="dior-form-group">
                        <label>Upload Supporting Images / Documents (e.g. Photo of Rash, Previous Lab Test)</label>
                        <div class="dior-upload-dropzone" id="dior-upload-dropzone">
                            <i class="fas fa-cloud-arrow-up"></i>
                            <span>Click to upload or drag and drop image (JPG, PNG, PDF up to 10MB)</span>
                            <input type="file" id="qn_file_upload" name="file_upload" accept="image/*,application/pdf"
                                class="dior-ic-44a70a0420 form-control">
                        </div>
                        <div id="dior-upload-preview" class="dior-ic-7287548b65"></div>
                    </div>

                    <!-- HIPAA Telemedicine Consent & E-Sign Pad -->
                    <div class="dior-esign-section">
                        <h4><i class="fas fa-signature"></i> Telemedicine & HIPAA Consent Agreement</h4>
                        <div class="esign-text-box">
                            <p>By typing your legal name or signing below, you consent to receive telehealth medical
                                evaluations from licensed clinicians at Dior Medical, acknowledge our HIPAA Privacy
                                Practices, and certify that all clinical information provided is true and accurate.</p>
                        </div>

                        <div class="dior-form-row-2">
                            <div class="dior-form-group">
                                <label for="esign_name">Type Full Legal Name (Electronic Signature) <span
                                        class="req">*</span></label>
                                <input type="text" id="esign_name" name="esign_name"
                                    value="<?php echo esc_attr($profile['full_name']); ?>" required>
                            </div>
                            <div class="dior-form-group">
                                <label for="esign_date">Date of Signature</label>
                                <input type="text" id="esign_date" name="esign_date"
                                    value="<?php echo current_time('M j, Y'); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="dior-modal-footer">
                        <button type="button" class="dior-btn-gold-secondary btn"
                            onclick="diorCloseModal('modal-questionnaire-intake')">Cancel</button>
                        <button type="submit" class="dior-btn-gold-primary btn" id="dior-submit-intake-btn">
                            <i class="fas fa-paper-plane"></i> Submit Clinical Intake to Doctor &rarr;
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 5. CHANGE PHARMACY MODAL -->
    <div class="dior-modal-overlay" id="modal-change-pharmacy">
        <div class="dior-modal-dialog">
            <div class="dior-modal-header">
                <h3><i class="fas fa-store"></i> Update Preferred Pharmacy</h3>
                <button type="button" class="dior-modal-close btn"
                    onclick="diorCloseModal('modal-change-pharmacy')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-quick-pharmacy">
                    <div class="dior-form-group">
                        <label for="quick_pharmacy_select">Select Local Pharmacy or Enter Custom</label>
                        <select id="quick_pharmacy_select" onchange="diorOnPharmacySelectChange(this)">
                            <option value="custom">-- Enter Custom Pharmacy Details --</option>
                            <option value="CVS Pharmacy #4829|9045 Wilshire Blvd, Beverly Hills, CA|(310) 555-0199">CVS
                                Pharmacy #4829 - Beverly Hills</option>
                            <option value="Walgreens Pharmacy #1034|300 N Canon Dr, Beverly Hills, CA|(310) 555-0244">
                                Walgreens Pharmacy #1034 - Beverly Hills</option>
                            <option
                                value="Rite Aid Pharmacy #5411|8440 Santa Monica Blvd, West Hollywood, CA|(323) 555-0188">
                                Rite Aid Pharmacy - West Hollywood</option>
                            <option
                                value="Capsule Digital Delivery Pharmacy|Direct Delivery to Doorstep|(888) 910-1212">
                                Capsule Direct Delivery Pharmacy</option>
                        </select>
                    </div>

                    <div class="dior-form-group">
                        <label for="q_pharm_name">Pharmacy Name <span class="req">*</span></label>
                        <input type="text" id="q_pharm_name" name="pharmacy_name"
                            value="<?php echo esc_attr($profile['pharmacy_name']); ?>" required>
                    </div>
                    <div class="dior-form-group">
                        <label for="q_pharm_address">Pharmacy Address <span class="req">*</span></label>
                        <input type="text" id="q_pharm_address" name="pharmacy_address"
                            value="<?php echo esc_attr($profile['pharmacy_address']); ?>" required>
                    </div>
                    <div class="dior-form-group">
                        <label for="q_pharm_phone">Pharmacy Phone</label>
                        <input type="tel" id="q_pharm_phone" name="pharmacy_phone"
                            value="<?php echo esc_attr($profile['pharmacy_phone']); ?>">
                    </div>

                    <div class="dior-modal-footer">
                        <button type="button" class="dior-btn-gold-secondary btn"
                            onclick="diorCloseModal('modal-change-pharmacy')">Cancel</button>
                        <button type="submit" class="dior-btn-gold-primary btn">Save Pharmacy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 6. VIEW RECEIPT MODAL -->
    <div class="dior-modal-overlay dior-ic-44a70a0420" id="modal-view-receipt">
        <div class="dior-modal-dialog dior-ic-3eca92ddc0">
            <div class="dior-modal-header dior-ic-89e0058b3f">
                <h3 class="dior-ic-b5eb34332b">
                    <i class="fas fa-receipt dior-ic-5d59a9a58e"></i> Telehealth Itemized Receipt
                </h3>
                <button type="button" class="dior-modal-close dior-ic-926a99defe btn"
                    onclick="diorCloseModal('modal-view-receipt')">&times;</button>
            </div>

            <div class="dior-modal-body dior-ic-95a72fdada" id="modal-receipt-content">
                <div class="receipt-printable-box dior-ic-1fb1e87e73">
                    <div class="receipt-logo dior-ic-edaece9d10">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Dior Medical" class="dior-ic-a84efd2f1e">
                    </div>

                    <div class="receipt-title dior-ic-6ce126c779">
                        Official Medical Statement &amp; Receipt
                    </div>

                    <table class="receipt-info-table dior-ic-7bae9c829a table">
                        <tr class="dior-ic-d81e5f9193">
                            <td class="dior-ic-b78f557455">
                                Invoice #:</td>
                            <td id="rec-inv-id" class="dior-ic-c39367f644">INV-8829
                            </td>
                        </tr>
                        <tr class="dior-ic-d81e5f9193">
                            <td class="dior-ic-e2ed1b531d">Date:
                            </td>
                            <td id="rec-date" class="dior-ic-20b442f466">Sept 4, 2026
                            </td>
                        </tr>
                        <tr class="dior-ic-d81e5f9193">
                            <td class="dior-ic-e2ed1b531d">
                                Patient:</td>
                            <td id="rec-patient-info" class="dior-ic-20b442f466">
                                <?php echo esc_html($profile['full_name']); ?>
                                (<?php echo esc_html($profile['patient_id']); ?>)
                            </td>
                        </tr>
                        <tr class="dior-ic-d81e5f9193">
                            <td class="dior-ic-e2ed1b531d">
                                Service:</td>
                            <td id="rec-service" class="dior-ic-20b442f466">Urgent
                                Care Telehealth Consultation</td>
                        </tr>
                        <tr class="dior-ic-d81e5f9193">
                            <td class="dior-ic-e2ed1b531d">
                                Payment Method:</td>
                            <td class="dior-ic-20b442f466">Stripe (Credit Card /
                                Apple Pay)</td>
                        </tr>
                        <tr class="total-row dior-ic-94cd1e6e94">
                            <td class="dior-ic-b9354c61ff">Total
                                Paid:</td>
                            <td id="rec-amount" class="dior-ic-9a1fcd0c50">$49.00</td>
                        </tr>
                    </table>

                    <div class="receipt-footer-note dior-ic-8082556ec6">
                        <i class="fas fa-shield-heart dior-ic-4418794cf4"></i>
                        <span>Dior Medical Telehealth &amp; Urgent Care &bull; Tax ID / NPI Verified &bull; Eligible for
                            HSA/FSA Reimbursement</span>
                    </div>
                </div>
            </div>

            <div class="dior-modal-footer dior-ic-e61c613459">
                <button type="button" class="dior-btn-gold-secondary dior-ic-2cc983c6d0 btn"
                    onclick="diorCloseModal('modal-view-receipt')">Close</button>
                <button type="button" class="dior-btn-gold-secondary dior-ic-fd09076f26 btn"
                    onclick="diorPrintReceiptModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16" style="background:transparent;"><path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/><path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1"/></svg> Print
                </button>
                <button type="button" class="dior-btn-gold-primary dior-ic-99a13c029f btn"
                    onclick="diorDownloadReceiptPdf()">
                    <i class="fas fa-file-arrow-down"></i> Download PDF
                </button>
            </div>
        </div>
    </div>

    <!-- 7. VIEW DOCUMENT MODAL -->
    <div class="dior-modal-overlay dior-ic-44a70a0420" id="modal-view-document">
        <div class="dior-modal-dialog dior-ic-c647d3a0d1">
            <div class="dior-modal-header dior-ic-89e0058b3f">
                <h3 id="doc-modal-header-title" class="dior-ic-b5eb34332b">
                    <i class="fas fa-file-shield dior-ic-5d59a9a58e"></i> Certified Medical Document
                </h3>
                <button type="button" class="dior-modal-close dior-ic-926a99defe btn"
                    onclick="diorCloseModal('modal-view-document')">&times;</button>
            </div>

            <div class="dior-modal-body dior-ic-9f59805fdf">
                <div class="doc-preview-card dior-ic-d3b67ecf4f">

                    <div class="doc-icon-large dior-ic-623e027944">
                        <i class="fas fa-file-pdf"></i>
                    </div>

                    <h4 id="doc-preview-title" class="dior-ic-b59074b5a9">
                        Document Title</h4>

                    <div class="dior-ic-6a49e78bbe">
                        <span id="doc-preview-category" class="dior-ic-0a03b45d9b">Category</span>
                    </div>

                    <div class="doc-details-grid dior-ic-bf2153c7ca">
                        <div>
                            <strong class="dior-ic-b7ab6167a8">Document
                                ID:</strong>
                            <span id="doc-preview-id" class="dior-ic-a6562b0806">DOC-88219</span>
                        </div>
                        <div>
                            <strong class="dior-ic-b7ab6167a8">Date
                                Issued:</strong>
                            <span id="doc-preview-date" class="dior-ic-a6562b0806">Sept
                                4, 2026</span>
                        </div>
                        <div>
                            <strong class="dior-ic-b7ab6167a8">Issued
                                By / Provider:</strong>
                            <span id="doc-preview-author" class="dior-ic-a6562b0806">Dior Medical Clinical
                                Team</span>
                        </div>
                        <div>
                            <strong class="dior-ic-b7ab6167a8">Patient:</strong>
                            <span id="doc-preview-patient"
                                class="dior-ic-a6562b0806"><?php echo esc_html($profile['full_name']); ?></span>
                        </div>
                    </div>

                    <div class="doc-seal dior-ic-3e0fc52c0f">
                        <i class="fas fa-shield-heart dior-ic-e3f2780fa1"></i>
                        <span>Digitally Certified &amp; E-Signed by Dior Medical Telehealth System</span>
                    </div>
                </div>
            </div>

            <div class="dior-modal-footer dior-ic-e90aec0c99">
                <button type="button" class="dior-btn-gold-secondary dior-ic-2cc983c6d0 btn"
                    onclick="diorCloseModal('modal-view-document')">Close</button>
                <button type="button" class="dior-btn-gold-primary dior-ic-99a13c029f btn"
                    onclick="diorDownloadCurrentDocumentPdf()">
                    <i class="fas fa-file-arrow-down"></i> Print / Download PDF
                </button>
            </div>
        </div>
    </div> <!-- /#modal-view-document -->

    <!-- 8. APPOINTMENT DETAIL MODAL (PATIENT PORTAL) -->
    <div class="dior-modal-overlay dior-ic-44a70a0420" id="modal-view-appointment-detail">
        <div class="dior-modal-dialog dior-ic-e9cfd8c2ad">
            <div class="dior-modal-header dior-ic-38b9f470b9">
                <h3 class="dior-ic-55d820b400">
                    <i class="fas fa-calendar-check dior-ic-5d59a9a58e"></i> Appointment Details
                </h3>
                <button type="button" class="dior-modal-close dior-ic-f476a9f033 btn"
                    onclick="diorCloseModal('modal-view-appointment-detail')">&times;</button>
            </div>

            <div class="dior-modal-body dior-ic-82b34f2b62">
                <!-- Hero Info Card -->
                <div class="dior-ic-69035811a2">
                    <div class="dior-ic-5d08ce7ec2">
                        <div>
                            <span id="pat-modal-appt-id" class="dior-ic-808a4fa3c3">APPT-ID</span>
                            <h4 id="pat-modal-appt-provider" class="dior-ic-28d0539f78">Attending Physician
                            </h4>
                            <span id="pat-modal-appt-spec" class="dior-ic-55709c3d5d">Telehealth Urgent
                                Care</span>
                        </div>
                        <span id="pat-modal-appt-status-badge" class="dior-st ok dior-ic-70c5533809">Confirmed</span>
                    </div>

                    <div class="dior-ic-d9dafa9db0">
                        <div>
                            <strong class="dior-ic-3a5e03a707">Date
                                &amp; Time</strong>
                            <span id="pat-modal-appt-datetime" class="dior-ic-035b2e7453">—</span>
                        </div>
                        <div>
                            <strong class="dior-ic-3a5e03a707">Consultation
                                Mode</strong>
                            <span id="pat-modal-appt-mode" class="dior-ic-035b2e7453"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#22c55e" class="bi bi-camera-reels" viewBox="0 0 16 16" style="background:transparent;"><path d="M6 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0M1 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M9 6h.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 7.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 16H2a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm6 8.73V7.27l-3.5 1.555v4.35zM1 8v6a1 1 0 0 0 1 1h7.5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1"/><path d="M9 6a3 3 0 1 0 0-6 3 3 0 0 0 0 6M7 3a2 2 0 1 1 4 0 2 2 0 0 1-4 0"/></svg> Video Visit</span>
                        </div>
                        <div class="dior-ic-b652b6f21e">
                            <strong class="dior-ic-3a5e03a707">Service
                                / Clinical Reason</strong>
                            <span id="pat-modal-appt-condition" class="dior-ic-ad42b18349">Telehealth
                                Urgent Care</span>
                        </div>
                    </div>
                </div>

                <!-- Live Zoom Link Strip (if available) -->
                <div id="pat-modal-zoom-wrap" class="dior-ic-5d5fe245c5">
                    <div class="dior-ic-63a4b96787">
                        <div class="dior-ic-99e83aaa9f">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#22c55e" class="bi bi-camera-reels" viewBox="0 0 16 16" style="background:transparent;"><path d="M6 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0M1 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M9 6h.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 7.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 16H2a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm6 8.73V7.27l-3.5 1.555v4.35zM1 8v6a1 1 0 0 0 1 1h7.5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1"/><path d="M9 6a3 3 0 1 0 0-6 3 3 0 0 0 0 6M7 3a2 2 0 1 1 4 0 2 2 0 0 1-4 0"/></svg>
                        </div>
                        <div>
                            <strong class="dior-ic-08abd803b3">Live Virtual Consultation
                                Room</strong>
                            <span class="dior-ic-c243108e79">Join physician on secure Zoom Video</span>
                        </div>
                    </div>
                    <a id="pat-modal-zoom-link" href="#" target="_blank" rel="noopener noreferrer"
                        class="dior-ic-459d58fbd7">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#22c55e" class="bi bi-camera-reels" viewBox="0 0 16 16" style="background:transparent;"><path d="M6 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0M1 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M9 6h.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 7.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 16H2a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm6 8.73V7.27l-3.5 1.555v4.35zM1 8v6a1 1 0 0 0 1 1h7.5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1"/><path d="M9 6a3 3 0 1 0 0-6 3 3 0 0 0 0 6M7 3a2 2 0 1 1 4 0 2 2 0 0 1-4 0"/></svg> Join Video Call &rarr;
                    </a>
                </div>

                <!-- Patient & Clinical Notes Card -->
                <div class="dior-ic-a9ce782a97">
                    <h5 class="dior-ic-8277d4959a">
                        <i class="fas fa-notes-medical dior-ic-5d59a9a58e"></i> Clinical Notes &amp;
                        Instructions
                    </h5>
                    <p id="pat-modal-appt-notes" class="dior-ic-d181656743">
                        Standard telehealth clinical consultation scheduled. All submitted medical intake questionnaires
                        are pre-verified.
                    </p>
                </div>

                <div class="dior-ic-3e0fc52c0f">
                    <i class="fas fa-shield-heart dior-ic-e3f2780fa1"></i>
                    <span>HIPAA Compliant &bull; Verified Dior Medical Telehealth Record</span>
                </div>
            </div>

            <div class="dior-modal-footer dior-ic-9b6b762386">
                <button type="button" class="dior-btn-gold-secondary dior-ic-d8eb827f48 btn"
                    onclick="diorCloseModal('modal-view-appointment-detail')">Close</button>
            </div>
        </div>
    </div> <!-- /#modal-view-appointment-detail -->

    <!-- 9. PRESCRIPTION DETAIL MODAL (PATIENT PORTAL) -->
    <div class="modal fade" id="modal-view-rx-detail" tabindex="-1" aria-labelledby="modal-view-rx-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">

                <!-- Modal Header -->
                <div class="modal-header border-bottom py-3 px-4 bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modal-view-rx-title">Official Telehealth Prescription</h5>
                        <div class="text-secondary small">DEA &bull; NPI Registered Practice &bull; Surescripts e-Rx Certified</div>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" onclick="diorCloseModal('modal-view-rx-detail')" aria-label="Close"></button>
                </div>

                <!-- Modal Body: Clean Printable Letterhead Sheet -->
                <div class="modal-body p-3 p-md-4 bg-light-subtle">
                    <div class="bg-white border rounded-3 p-4 shadow-sm" id="pat-modal-rx-sheet">

                        <!-- Clinic & Physician Header -->
                        <div class="row align-items-start pb-3 mb-3 border-bottom g-3">
                            <div class="col-sm-7 col-12">
                                <h5 class="fw-bold text-dark mb-1" id="pat-modal-rx-doc-header">
                                    <span id="pat-modal-rx-doc">Dr. Marcus Sterling, MD</span>
                                </h5>
                                <div class="text-secondary small mb-1">
                                    QUALIFICATION: M.B.B.S, M.D. &bull; DEA: MV8492019 &bull; NPI: 1849201948
                                </div>
                                <div class="fw-semibold text-dark small">DIOR MEDICAL TELEHEALTH &amp; URGENT CARE</div>
                                <div class="text-secondary small">Clinical Telemedicine Division &bull; Surescripts e-Rx Certified</div>
                            </div>
                            <div class="col-sm-5 col-12 text-sm-end">
                                <div class="fw-bold fs-5 text-primary mb-1">DIOR MEDICAL</div>
                                <div class="badge bg-light text-primary border mb-2">Digital Rx Service</div>
                                <div class="small text-secondary">
                                    <div>Prescription Date: <span class="fw-semibold text-dark" id="pat-modal-rx-date">—</span></div>
                                    <div>Unique Rx UID: <strong class="text-dark" id="pat-modal-rx-id">Rx #ORDER</strong></div>
                                    <div class="font-monospace text-muted small" id="pat-modal-rx-barcode-txt">*RX-000000*</div>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Demographics Box -->
                        <div class="border rounded-2 p-3 bg-light-subtle mb-3">
                            <div class="row g-2 small">
                                <div class="col-sm-6 col-12">
                                    <span class="text-secondary">Patient Name:</span>
                                    <strong class="text-dark ms-1" id="pat-modal-rx-pat-name"><?php echo esc_html($profile['full_name']); ?></strong>
                                </div>
                                <div class="col-sm-6 col-12 text-sm-end">
                                    <span class="text-secondary">Date:</span>
                                    <span class="text-dark fw-semibold ms-1" id="pat-modal-rx-pad-date">—</span>
                                </div>
                                <div class="col-4">
                                    <span class="text-secondary">Age:</span>
                                    <span class="text-dark fw-semibold ms-1" id="pat-modal-rx-pat-age"><?php
                                    $modal_age = '18+ (Adult)';
                                    if (!empty($profile['dob'])) {
                                        $modal_dob_timestamp = strtotime($profile['dob']);
                                        if ($modal_dob_timestamp) {
                                            $modal_age_years = date('Y') - date('Y', $modal_dob_timestamp);
                                            if (date('md') < date('md', $modal_dob_timestamp)) {
                                                $modal_age_years--;
                                            }
                                            $modal_age = $modal_age_years . ' years';
                                        }
                                    }
                                    echo esc_html($modal_age);
                                    ?></span>
                                </div>
                                <div class="col-4">
                                    <span class="text-secondary">Gender:</span>
                                    <span class="text-dark fw-semibold ms-1" id="pat-modal-rx-pat-gender"><?php echo esc_html($profile['gender'] ?? 'Female'); ?></span>
                                </div>
                                <div class="col-4">
                                    <span class="text-secondary">Weight:</span>
                                    <span class="text-dark fw-semibold ms-1" id="pat-modal-rx-pat-weight"><?php echo esc_html($profile['weight'] ?? 'NKDA (Verified)'); ?></span>
                                </div>
                                <div class="col-12 pt-2 mt-2 border-top">
                                    <span class="text-secondary">Diagnosis:</span>
                                    <span class="text-dark ms-1" id="pat-modal-rx-diagnosis">Clinical Telehealth Consultation &bull; General Wellness</span>
                                </div>
                            </div>
                        </div>

                        <!-- Prescription Directive Heading -->
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                            <div class="fw-bold text-dark small">OFFICIAL PRESCRIPTION DIRECTIVE (Rx)</div>
                            <span class="badge bg-light text-secondary border">Schedule VI / Legend Non-Controlled</span>
                        </div>

                        <!-- Clean Medication Table -->
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th class="text-center" style="width: 40px;">#</th>
                                        <th>Medicine Name &amp; Formulation</th>
                                        <th>Dosage &amp; Usage Instructions (SIG)</th>
                                        <th style="width: 150px;">Dispense / Refills</th>
                                    </tr>
                                </thead>
                                <tbody id="pat-modal-rx-table-body" class="small">
                                    <!-- Populated dynamically via diorViewRxDetails() -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Advice Given & Clinical Instructions -->
                        <div class="border rounded-2 p-3 bg-light-subtle mb-3">
                            <div class="fw-bold small text-dark mb-1">Advice Given &amp; Clinical Instructions:</div>
                            <div class="small text-secondary" id="pat-modal-rx-advice">
                                &bull; Take medication strictly as directed with a full glass of water.<br>
                                &bull; Complete the full course of treatment unless instructed otherwise by your doctor.<br>
                                &bull; Avoid alcohol during the active course of treatment.
                            </div>
                        </div>

                        <!-- Designated Fulfillment Pharmacy -->
                        <div class="border rounded-2 p-3 bg-white mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="small text-secondary">Designated Fulfillment Pharmacy:</span>
                                <strong class="small text-dark ms-1" id="pat-modal-rx-pharm-name">
                                    <?php echo esc_html(!empty($profile['pharmacy_name']) ? $profile['pharmacy_name'] : 'Preferred Pharmacy on File'); ?>
                                </strong>
                                <div class="small text-muted" id="pat-modal-rx-routing">Routed Electronically via Surescripts NCPDP EDI</div>
                            </div>
                            <span id="pat-modal-rx-status-badge" class="badge bg-success-subtle text-success border border-success-subtle">
                                Transmission Confirmed
                            </span>
                        </div>

                        <!-- Physician Digital Signature & Telehealth Certification -->
                        <div class="row g-3 align-items-end pt-3 border-top">
                            <div class="col-sm-7 col-12">
                                <div class="small text-muted">
                                    <strong class="text-dark">TELEHEALTH LEGAL CERTIFICATION:</strong><br>
                                    This prescription was electronically reviewed, approved, and digitally signed by an authorized healthcare provider pursuant to a valid telehealth encounter under DEA 21 CFR Part 1311 and state medical practice acts.
                                </div>
                            </div>
                            <div class="col-sm-5 col-12 text-sm-end">
                                <div class="border-bottom pb-1 d-inline-block text-center" style="min-width: 180px;">
                                    <img id="pat-modal-rx-sig-img" src="" alt="Doctor Signature" style="max-height: 40px; display: none;" class="mb-1">
                                    <div id="pat-modal-rx-sig-name" class="fw-bold text-dark font-monospace small">/s/ Dr. Marcus Sterling, MD</div>
                                </div>
                                <div class="small text-secondary mt-1">Authorized Physician Signature</div>
                                <div id="pat-modal-rx-sig-date" class="text-muted" style="font-size: 11px;">SHA256 Encrypted &bull; Issued Sep 14, 2026</div>
                            </div>
                        </div>

                        <!-- Letterhead Bottom Clinic Footer Details -->
                        <div class="mt-3 pt-2 border-top text-center text-muted" style="font-size: 11px;">
                            100 Medical Plaza, Suite 400 &bull; Dior Medical Telehealth Group &bull; (800) 555-DIOR &bull; concierge@diormedical.com
                        </div>

                    </div> <!-- /#pat-modal-rx-sheet -->
                </div> <!-- /.modal-body -->

                <!-- Modal Action Footer -->
                <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-between align-items-center">
                    <button type="button" id="pat-modal-rx-refill-btn" class="btn btn-outline-secondary btn-sm rounded-3 fw-semibold">
                        Request Refill
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" id="pat-modal-rx-pdf-btn" class="btn btn-primary btn-sm rounded-3 fw-semibold shadow-sm">
                            Download Official PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-bs-dismiss="modal" onclick="diorCloseModal('modal-view-rx-detail')">
                            Close
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div> <!-- /#modal-view-rx-detail -->

    <!-- 10. EMERGENCY CONTACT & ASSISTANCE MODAL (PATIENT PORTAL) -->
    <div class="modal fade" id="modal-emergency-contact" tabindex="-1" aria-labelledby="modal-emergency-contact-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">

                <!-- Modal Header -->
                <div class="modal-header border-bottom py-3 px-4 bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modal-emergency-contact-title">Emergency Assistance &amp; Contacts</h5>
                        <div class="text-secondary small">Direct Emergency Reachout &bull; Designated Contact Management</div>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" onclick="diorCloseModal('modal-emergency-contact')" aria-label="Close"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body p-3 p-md-4 bg-light-subtle">

                    <!-- Critical Notice Banner -->
                    <div class="alert alert-danger border-0 rounded-3 p-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <strong class="d-block mb-1">Life-Threatening Emergency Notice</strong>
                            <span class="small">If you or someone around you is in immediate medical danger, please call 911 immediately.</span>
                        </div>
                        <a href="tel:911" class="btn btn-danger btn-sm rounded-3 fw-bold px-3 shadow-sm text-nowrap">
                            Call 911 Now
                        </a>
                    </div>

                    <!-- Direct Emergency Dispatch / Contact Options -->
                    <div class="row g-3 mb-3">
                        <!-- 1. Ambulance Dispatch -->
                        <div class="col-md-4 col-12">
                            <div class="card border rounded-3 p-3 h-100 bg-white shadow-sm d-flex flex-column justify-content-between">
                                <div>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill mb-2">Ambulance</span>
                                    <div class="fw-bold text-dark small mb-1">Emergency Paramedics</div>
                                    <div class="text-secondary small mb-3">Instant ambulance dispatch for severe trauma or urgent hospital transport.</div>
                                </div>
                                <a href="tel:911" class="btn btn-outline-danger btn-sm rounded-3 fw-semibold w-100">
                                    Call 911 Dispatch
                                </a>
                            </div>
                        </div>

                        <!-- 2. On-Call Emergency Doctor -->
                        <div class="col-md-4 col-12">
                            <div class="card border rounded-3 p-3 h-100 bg-white shadow-sm d-flex flex-column justify-content-between">
                                <div>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill mb-2">On-Call MD</span>
                                    <div class="fw-bold text-dark small mb-1">Urgent Care Doctor</div>
                                    <div class="text-secondary small mb-3">Direct connection with Dior Medical 24/7 on-call telemedicine physician.</div>
                                </div>
                                <a href="tel:18005553467" class="btn btn-outline-warning text-dark btn-sm rounded-3 fw-semibold w-100">
                                    Call (800) 555-DIOR
                                </a>
                            </div>
                        </div>

                        <!-- 3. Designated Emergency Contact -->
                        <div class="col-md-4 col-12">
                            <div class="card border rounded-3 p-3 h-100 bg-white shadow-sm d-flex flex-column justify-content-between">
                                <div>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill mb-2">Personal Contact</span>
                                    <div class="fw-bold text-dark small mb-1" id="emerg-modal-card-name">
                                        <?php echo esc_html(!empty($profile['emergency_name']) ? $profile['emergency_name'] : 'No Contact on File'); ?>
                                    </div>
                                    <div class="text-secondary small mb-3" id="emerg-modal-card-desc">
                                        <?php 
                                        $rel_str = !empty($profile['emergency_relation']) ? $profile['emergency_relation'] : 'Designated Contact';
                                        $phone_str = !empty($profile['emergency_phone']) ? $profile['emergency_phone'] : 'No phone number';
                                        echo esc_html($rel_str . ' • ' . $phone_str);
                                        ?>
                                    </div>
                                </div>
                                <a id="emerg-modal-card-call-btn" href="<?php echo !empty($profile['emergency_phone']) ? 'tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $profile['emergency_phone'])) : '#'; ?>" class="btn btn-outline-primary btn-sm rounded-3 fw-semibold w-100 <?php echo empty($profile['emergency_phone']) ? 'disabled' : ''; ?>">
                                    Call Contact
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Add / Edit Emergency Contact Section -->
                    <div class="card border-0 rounded-3 p-3 bg-white shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Designated Emergency Contact Details</h6>
                                <span class="text-secondary small">Add or update who Dior Medical should contact in an emergency</span>
                            </div>
                            <span class="badge bg-light text-secondary border">Priority 1</span>
                        </div>

                        <form id="dior-form-emergency-contact" onsubmit="diorSaveEmergencyContact(event)">
                            <div class="row g-3">
                                <div class="col-sm-6 col-12">
                                    <label class="form-label small fw-semibold text-secondary mb-1" for="modal_emerg_name">
                                        Full Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control rounded-3" id="modal_emerg_name" name="emergency_name"
                                        value="<?php echo esc_attr($profile['emergency_name'] ?? ''); ?>"
                                        placeholder="e.g. Michael Johnson" required>
                                </div>
                                <div class="col-sm-6 col-12">
                                    <label class="form-label small fw-semibold text-secondary mb-1" for="modal_emerg_rel">
                                        Relationship <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select rounded-3" id="modal_emerg_rel" name="emergency_relation" required>
                                        <?php
                                        $user_em_rel = strtolower($profile['emergency_relation'] ?? '');
                                        $rel_options = ['Spouse', 'Parent', 'Child', 'Sibling', 'Partner', 'Relative', 'Friend', 'Legal Guardian', 'Caregiver', 'Other'];
                                        foreach ($rel_options as $opt):
                                            $selected = (strtolower($opt) === $user_em_rel) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($opt) . '" ' . $selected . '>' . esc_html($opt) . '</option>';
                                        endforeach;
                                        ?>
                                    </select>
                                </div>
                                <div class="col-sm-6 col-12">
                                    <label class="form-label small fw-semibold text-secondary mb-1" for="modal_emerg_phone">
                                        Phone Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="tel" class="form-control rounded-3" id="modal_emerg_phone" name="emergency_phone"
                                        value="<?php echo esc_attr($profile['emergency_phone'] ?? ''); ?>"
                                        placeholder="e.g. +1 (555) 014-2099" required>
                                </div>
                                <div class="col-sm-6 col-12">
                                    <label class="form-label small fw-semibold text-secondary mb-1" for="modal_emerg_priority">
                                        Priority Level
                                    </label>
                                    <select class="form-select rounded-3" id="modal_emerg_priority" name="emergency_priority">
                                        <option value="Emergency" selected>Emergency (Primary)</option>
                                        <option value="High Priority">High Priority</option>
                                        <option value="Secondary">Secondary Contact</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 pt-3 mt-3 border-top">
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-bs-dismiss="modal" onclick="diorCloseModal('modal-emergency-contact')">
                                    Cancel
                                </button>
                                <button type="submit" id="dior-emerg-submit-btn" class="btn btn-primary btn-sm rounded-3 fw-semibold shadow-sm px-3">
                                    Save Emergency Contact
                                </button>
                            </div>
                        </form>
                    </div>

                </div> <!-- /.modal-body -->

            </div>
        </div>
    </div> <!-- /#modal-emergency-contact -->


</div> <!-- /#dior-patient-portal-app -->
<script>
// Hipaatizer Popup logic
function diorLoadHipaaFrame() {
    const frame = document.getElementById('dior-hipaatizer-intake-frame');
    if (!frame) return;
    const target = frame.getAttribute('data-src');
    if (target && frame.getAttribute('src') === 'about:blank') {
        frame.setAttribute('src', target);
    }
}

function diorOpenHipaaPopup() {
    const hipaatizerModal = document.getElementById('dior-custom-hipaatizer-modal');
    if (!hipaatizerModal) {
        console.error('HIPAAtizer modal not found!');
        return;
    }
    hipaatizerModal.style.display = 'flex';
    // Start HIPAAtizer only after the modal is visible.
    requestAnimationFrame(function() {
        setTimeout(diorLoadHipaaFrame, 50);
    });
}

function diorCloseHipaaModal() {
    const modal = document.getElementById('dior-custom-hipaatizer-modal');
    if (modal) modal.style.display = 'none';
}

// Keep the global name used by the registration/dashboard scripts.
window.diorOpenHipaaPopup = diorOpenHipaaPopup;
window.diorCloseHipaaModal = diorCloseHipaaModal;
</script>

<style>
.dior-hipaa-swal.swal2-popup { 
    max-width: 950px !important;
    padding: 0 !important;
    border-radius: 12px !important;
    overflow: hidden;
}
.dior-hipaa-swal .swal2-title {
    padding: 15px 15px 10px !important;
    margin: 0 !important;
}
.dior-hipaa-swal .swal2-html-container {
    padding: 0 !important;
    margin: 0 !important;
}
.dior-hipaa-swal .swal2-close {
    background: transparent !important;
    background-color: transparent !important;
    color: #666 !important;
    font-size: 32px !important;
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
    width: 40px !important;
    height: 40px !important;
    border-radius: 50% !important;
    position: absolute !important;
    top: 5px !important;
    right: 5px !important;
}
.dior-hipaa-swal .swal2-close:hover {
    color: #d33 !important;
    background-color: transparent !important;
}
</style>

<!-- Hidden Intake Form Overlay -->
<div id="dior-custom-hipaatizer-modal" style="display:none; position:fixed; inset:0; width:100vw; height:100vh; background:rgba(0,0,0,0.8); z-index:999999; justify-content:center; align-items:center;">
    <div style="width:min(950px, 95vw); height:90vh; background:#fff; border-radius:12px; position:relative; overflow:hidden;">
        <button type="button" onclick="diorCloseHipaaModal();" aria-label="Close HIPAAtizer form" style="position:absolute; right:15px; top:10px; font-size:32px; line-height:1; color:#666; background:#fff; border:none; cursor:pointer; z-index:10000;">&times;</button>
        <div id="hipaatizer-inject-target" style="width:100%; height:100%; padding:20px; box-sizing:border-box;">
            <!-- Load HIPAAtizer only after the dashboard modal becomes visible. This avoids the
                 HIPAAtizer loader getting initialized while its parent is display:none. -->
            <iframe
                id="dior-hipaatizer-intake-frame"
                title="HIPAAtizer Clinical Intake & Questionnaire Form"
                src="about:blank"
                data-src="https://app.hipaatizer.com/workflow/01a11515-b491-79dc-b59b-e4be2a345a49"
                allow="microphone; camera; payment"
                style="display:block; width:100%; height:100%; min-height:700px; border:0; background:#fff;"
                loading="eager"></iframe>
        </div>
    </div>
</div>
<!-- Hidden Intake Form Content for Dashboard Popup -->
<?php Dior_Medical_Auth::render_docbooker_booking_modal(); ?>
