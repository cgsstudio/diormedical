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
                <button type="button" class="dior-side-close" id="dior-side-close" onclick="diorCloseMobileDrawer()"
                    aria-label="Close sidebar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="dior-side-nav">
                <div class="grp">
                    <button type="button" class="dior-nav-btn active" data-tab="overview">
                        <i class="fa-solid fa-desktop"></i>
                        <span class="nav-label">Dashboard</span>
                    </button>
                    <div class="dior-nav-item-has-children" id="dior-appt-dropdown-wrap">
                        <button type="button" class="dior-nav-btn" onclick="var w=document.getElementById('dior-appt-dropdown-wrap');w.classList.toggle('open');this.querySelector('.fa-chevron-down').style.transform=w.classList.contains('open')?'rotate(180deg)':'rotate(0deg)';diorSwitchTab('appointments');diorSwitchApptSubTab('today');">
                            <i class="fa-regular fa-calendar-days"></i>
                            <span class="nav-label">Appointments</span>
                            <i class="fa-solid fa-chevron-down dior-ic-fdf55d6c0a"></i>
                        </button>
                        <div class="dior-nav-dropdown">
                            <a href="#" class="dior-nav-dropdown-item" data-appt-subtab="book"><i class="fa-solid fa-angle-right"></i> Book Appointment</a>
                            <a href="#" class="dior-nav-dropdown-item" data-appt-subtab="today"><i class="fa-solid fa-angle-right"></i> Today Appointments</a>
                            <a href="#" class="dior-nav-dropdown-item" data-appt-subtab="upcoming"><i class="fa-solid fa-angle-right"></i> Upcoming Appointments</a>
                            <a href="#" class="dior-nav-dropdown-item" data-appt-subtab="past"><i class="fa-solid fa-angle-right"></i> Past Appointments</a>
                        </div>
                    </div>
                    <button type="button" class="dior-nav-btn" data-tab="docs_meds">
                        <i class="fa-solid fa-file-prescription"></i>
                        <span class="nav-label">Prescriptions</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="telemedicine">
                        <i class="fa-solid fa-user-doctor"></i>
                        <span class="nav-label">Telemedicine</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="medical_record">
                        <i class="fa-solid fa-file-medical"></i>
                        <span class="nav-label">Medical Record</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="payments">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span class="nav-label">Billing &amp; Payment</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="insurance">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span class="nav-label">Insurance Claim</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="documents">
                        <i class="fa-regular fa-copy"></i>
                        <span class="nav-label">Documents &amp; Report</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="emergency">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span class="nav-label">Emergency support</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="feedback">
                        <i class="fa-regular fa-comment-dots"></i>
                        <span class="nav-label">Feedback &amp; Support</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="notifications">
                        <i class="fa-regular fa-bell"></i>
                        <span class="nav-label">Notification</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="consultation">
                        <i class="fa-solid fa-video"></i>
                        <span class="nav-label">Consultation Room</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="settings">
                        <i class="fa-solid fa-gear"></i>
                        <span class="nav-label">Settings</span>
                    </button>
                </div>
            </nav>

            <!-- Bottom Sidebar User Profile Card -->
            <div class="dior-sidebar-bottom-profile">
                <div class="dior-sidebar-avatar-wrap">
                    <?php if (!empty($profile['avatar_url'])): ?>
                    <img src="<?php echo esc_url($profile['avatar_url']); ?>" alt="<?php echo esc_attr($profile['full_name']); ?>">
                    <?php else: ?>
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=200" alt="Mia Song">
                    <?php endif; ?>
                </div>
                <div class="dior-sidebar-user-info">
                    <strong class="dior-sidebar-user-name"><?php echo esc_html(!empty($profile['full_name']) ? $profile['full_name'] : 'Mia Song'); ?></strong>
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
                    <button type="button" class="dior-mobile-menu-toggle" id="dior-mobile-menu-toggle"
                        onclick="diorOpenMobileDrawer()" aria-label="Open navigation menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <!-- Search Bar (Clean Reference UI) -->
                    <div class="dior-top-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search appointments, prescriptions, records. .">
                    </div>
                </div>

                <div class="dior-topbar-right">
                    <!-- Messages Icon -->
                    <button type="button" class="dior-notif-btn dior-ic-f2136e6b50" aria-label="Messages">
                        <i class="fa-regular fa-envelope"></i>
                    </button>

                    <!-- Notification Bell Dropdown -->
                    <div class="dior-notif-wrap" id="dior-notif-dropdown-wrap">
                        <button type="button" class="dior-notif-btn" onclick="diorToggleNotifDropdown(event)"
                            aria-label="Notifications">
                            <i class="fa-regular fa-bell"></i>
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
                                    <i class="fa-regular fa-bell-slash dior-ic-41fd079087"
                                       ></i>
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
                                            class="fa-solid <?php echo esc_attr($n['icon'] ?? 'fa-bell'); ?>"></i></div>
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
                        <button type="button" class="dior-user-pill-btn" onclick="diorToggleProfileDropdown(event)"
                            aria-label="User Profile">
                            <div class="dior-user-pill-card">
                                <div class="dior-pill-avatar" id="dior-top-pill-avatar">
                                    <?php if (!empty($profile['avatar_url'])): ?>
                                    <img src="<?php echo esc_url($profile['avatar_url']); ?>"
                                        alt="<?php echo esc_attr($profile['full_name']); ?>">
                                    <?php else: ?>
                                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=200" alt="Mia Song">
                                    <?php endif; ?>
                                    <span class="dior-online-dot"></span>
                                </div>
                                <div class="dior-pill-user-meta">
                                    <strong class="dior-pill-user-name"><?php echo esc_html(!empty($profile['full_name']) ? $profile['full_name'] : 'Mia Song'); ?></strong>
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
                                        <i class="fa-regular fa-user"></i> Profile
                                    </button>
                                </li>
                                <li>
                                    <button type="button" data-switch-tab="settings">
                                        <i class="fa-regular fa-envelope"></i> Email
                                    </button>
                                </li>
                                <li>
                                    <button type="button" data-switch-tab="settings">
                                        <i class="fa-solid fa-gear"></i> Settings
                                    </button>
                                </li>
                                <li>
                                    <a href="<?php echo esc_url(wp_logout_url(home_url('/diro-login/'))); ?>"
                                        class="dior-logout-text-btn">
                                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
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
                <div class="dior-profile-incomplete-alert dior-ic-c03948b3c3" id="dior-profile-incomplete-banner"
                   >
                    <div class="dior-ic-29bdac4ec4">
                        <div
                            class="dior-ic-3fb01cc77f">
                            <i class="fa-solid fa-user-pen"></i>
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
                    <button type="button" class="dior-btn-gold-primary dior-ic-4ee0445c2a" data-switch-tab="profile"
                       >
                        <i class="fa-solid fa-id-card"></i> Complete Profile Now
                    </button>
                </div>
                <?php endif; ?>

                <!-- ============================================================== -->
                <!-- 0. HOME / OVERVIEW TAB (Real Functional Patient Data + Clean UI) -->
                <!-- ============================================================== -->
                <?php include DIOR_PORTAL_PATH . 'templates/patient-tabs/overview.php'; ?>
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
        <div class="dior-modal-dialog dior-modal-lg dior-ic-ee0c6b0194"
           >
            <div class="dior-modal-header dior-ic-78529f928a">
                <div>
                    <h3 class="dior-ic-66a69ef8e4"><i class="fa-solid fa-calendar-plus"></i> Book New Visit</h3>
                    <span class="dior-ic-23927a389b"><i class="fa-solid fa-shield-halved dior-ic-236b5f0eb0"
                           ></i> HIPAA-Compliant Medical Intake &amp; Booking</span>
                </div>
                <button type="button" class="dior-modal-close"
                    onclick="diorCloseModal('modal-book-appointment')">&times;</button>
            </div>
            <div class="dior-modal-body dior-ic-1d15569872"
               >
                <iframe id="hipaatizer-book-visit-iframe"
                    src="https://app.hipaatizer.com/workflow/01a07aa2-d931-728d-bfe9-8d7b3bc0389d"
                   
                    allow="microphone; camera; payment" title="HIPAAtizer Booking Form" class="dior-ic-a52858ae73">
                </iframe>
                <div class="dior-ic-44a70a0420">
                    <?php echo do_shortcode('[hipaatizer id="01a07aa2-d931-728d-bfe9-8d7b3bc0389d"]'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. RESCHEDULE MODAL -->
    <div class="dior-modal-overlay" id="modal-reschedule-appointment">
        <div class="dior-modal-dialog">
            <div class="dior-modal-header">
                <h3><i class="fa-regular fa-calendar-days"></i> Reschedule Consultation</h3>
                <button type="button" class="dior-modal-close"
                    onclick="diorCloseModal('modal-reschedule-appointment')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-reschedule-appt">
                    <input type="hidden" id="resched_appt_id" name="appt_id" value="">

                    <div id="resched_appt_summary"
                        class="dior-ic-f8cd81dec3">
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
                            placeholder="e.g. Schedule conflict, personal travel"
                            class="dior-ic-3638e808c4">
                    </div>

                    <div class="dior-modal-footer dior-ic-78874ad7d4">
                        <button type="button" class="dior-btn-gold-secondary"
                            onclick="diorCloseModal('modal-reschedule-appointment')">Keep Current Time</button>
                        <button type="submit" id="dior-btn-submit-resched" class="dior-btn-gold-primary">
                            <i class="fa-solid fa-check"></i> Save New Time Slot
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
                <h3><i class="fa-solid fa-triangle-exclamation dior-ic-557c97e141"></i> Cancel Consultation</h3>
                <button type="button" class="dior-modal-close"
                    onclick="diorCloseModal('modal-cancel-appointment')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-cancel-appt">
                    <input type="hidden" id="cancel_appt_id" name="appt_id" value="">

                    <div id="cancel_appt_summary"
                        class="dior-ic-e7416a9b55">
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
                        <button type="button" class="dior-btn-gold-secondary"
                            onclick="diorCloseModal('modal-cancel-appointment')">Keep Appointment</button>
                        <button type="submit" id="dior-btn-submit-cancel" class="dior-btn-danger dior-ic-02e790ac8d"
                           >
                            <i class="fa-solid fa-xmark"></i> Confirm Cancellation
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
                <h3><i class="fa-regular fa-bell dior-ic-b2113a5eea"></i> Send Appointment Reminder</h3>
                <button type="button" class="dior-modal-close"
                    onclick="diorCloseModal('modal-appointment-reminder')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-send-reminder">
                    <input type="hidden" id="reminder_appt_id" name="appt_id" value="">

                    <div id="reminder_appt_summary"
                        class="dior-ic-d8b0d6eace">
                    </div>

                    <p class="dior-ic-e261ffabcb">
                        Send an urgent clinical consultation reminder with scheduled date/time, patient preparation
                        checklist, and direct video consultation room link.
                    </p>

                    <div
                        class="dior-ic-2895f37e6b">
                        <div class="dior-ic-55132245a6">
                            <i class="fa-solid fa-tower-broadcast dior-ic-1dc44a1b66"></i> Active
                            Notification Channels:
                        </div>
                        <div class="dior-ic-dce101ce6e">
                            <label class="dior-ic-8b80aded64">
                                <input type="checkbox" checked disabled class="dior-ic-ce15c82e4d">
                                <span><strong>Email Notification</strong> (Delivered to both patient & attending
                                    doctor)</span>
                            </label>
                            <label class="dior-ic-8b80aded64">
                                <input type="checkbox" checked disabled class="dior-ic-ce15c82e4d">
                                <span><strong>Dashboard In-App Alert</strong> (Added to notification inbox for patient &
                                    doctor)</span>
                            </label>
                        </div>
                    </div>

                    <div class="dior-modal-footer dior-ic-78874ad7d4">
                        <button type="button" class="dior-btn-gold-secondary"
                            onclick="diorCloseModal('modal-appointment-reminder')">Close</button>
                        <button type="submit" id="dior-btn-submit-reminder" class="dior-btn-gold-primary dior-ic-2e8ec92046"
                           >
                            <i class="fa-regular fa-bell"></i> Send Reminder Now
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
                <h3 id="modal-qn-title"><i class="fa-solid fa-clipboard-question"></i> Clinical Intake & E-Sign</h3>
                <button type="button" class="dior-modal-close"
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
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>Click to upload or drag and drop image (JPG, PNG, PDF up to 10MB)</span>
                            <input type="file" id="qn_file_upload" name="file_upload" accept="image/*,application/pdf"
                                class="dior-ic-44a70a0420">
                        </div>
                        <div id="dior-upload-preview"
                            class="dior-ic-7287548b65"></div>
                    </div>

                    <!-- HIPAA Telemedicine Consent & E-Sign Pad -->
                    <div class="dior-esign-section">
                        <h4><i class="fa-solid fa-signature"></i> Telemedicine & HIPAA Consent Agreement</h4>
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
                        <button type="button" class="dior-btn-gold-secondary"
                            onclick="diorCloseModal('modal-questionnaire-intake')">Cancel</button>
                        <button type="submit" class="dior-btn-gold-primary" id="dior-submit-intake-btn">
                            <i class="fa-solid fa-paper-plane"></i> Submit Clinical Intake to Doctor &rarr;
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
                <h3><i class="fa-solid fa-store"></i> Update Preferred Pharmacy</h3>
                <button type="button" class="dior-modal-close"
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
                        <button type="button" class="dior-btn-gold-secondary"
                            onclick="diorCloseModal('modal-change-pharmacy')">Cancel</button>
                        <button type="submit" class="dior-btn-gold-primary">Save Pharmacy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 6. VIEW RECEIPT MODAL -->
    <div class="dior-modal-overlay dior-ic-44a70a0420" id="modal-view-receipt">
        <div class="dior-modal-dialog dior-ic-3eca92ddc0"
           >
            <div class="dior-modal-header dior-ic-89e0058b3f"
               >
                <h3
                    class="dior-ic-b5eb34332b">
                    <i class="fa-solid fa-receipt dior-ic-5d59a9a58e"></i> Telehealth Itemized Receipt
                </h3>
                <button type="button" class="dior-modal-close dior-ic-926a99defe" onclick="diorCloseModal('modal-view-receipt')"
                   >&times;</button>
            </div>

            <div class="dior-modal-body dior-ic-95a72fdada" id="modal-receipt-content">
                <div class="receipt-printable-box dior-ic-1fb1e87e73"
                   >
                    <div class="receipt-logo dior-ic-edaece9d10">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Dior Medical"
                            class="dior-ic-a84efd2f1e">
                    </div>

                    <div class="receipt-title dior-ic-6ce126c779"
                       >
                        Official Medical Statement &amp; Receipt
                    </div>

                    <table class="receipt-info-table dior-ic-7bae9c829a"
                       >
                        <tr class="dior-ic-d81e5f9193">
                            <td
                                class="dior-ic-b78f557455">
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
                            <td
                                id="rec-amount" class="dior-ic-9a1fcd0c50">$49.00</td>
                        </tr>
                    </table>

                    <div class="receipt-footer-note dior-ic-8082556ec6"
                       >
                        <i class="fa-solid fa-shield-halved dior-ic-4418794cf4"></i>
                        <span>Dior Medical Telehealth &amp; Urgent Care &bull; Tax ID / NPI Verified &bull; Eligible for
                            HSA/FSA Reimbursement</span>
                    </div>
                </div>
            </div>

            <div class="dior-modal-footer dior-ic-e61c613459"
               >
                <button type="button" class="dior-btn-gold-secondary dior-ic-2cc983c6d0" onclick="diorCloseModal('modal-view-receipt')"
                   >Close</button>
                <button type="button" class="dior-btn-gold-secondary dior-ic-fd09076f26" onclick="diorPrintReceiptModal()"
                   >
                    <i class="fa-solid fa-print"></i> Print
                </button>
                <button type="button" class="dior-btn-gold-primary dior-ic-99a13c029f" onclick="diorDownloadReceiptPdf()"
                   >
                    <i class="fa-solid fa-file-arrow-down"></i> Download PDF
                </button>
            </div>
        </div>
    </div>

    <!-- 7. VIEW DOCUMENT MODAL -->
    <div class="dior-modal-overlay dior-ic-44a70a0420" id="modal-view-document">
        <div class="dior-modal-dialog dior-ic-c647d3a0d1"
           >
            <div class="dior-modal-header dior-ic-89e0058b3f"
               >
                <h3 id="doc-modal-header-title"
                    class="dior-ic-b5eb34332b">
                    <i class="fa-solid fa-file-shield dior-ic-5d59a9a58e"></i> Certified Medical Document
                </h3>
                <button type="button" class="dior-modal-close dior-ic-926a99defe" onclick="diorCloseModal('modal-view-document')"
                   >&times;</button>
            </div>

            <div class="dior-modal-body dior-ic-9f59805fdf">
                <div class="doc-preview-card dior-ic-d3b67ecf4f"
                   >

                    <div class="doc-icon-large dior-ic-623e027944">
                        <i class="fa-solid fa-file-pdf"></i>
                    </div>

                    <h4 id="doc-preview-title"
                        class="dior-ic-b59074b5a9">
                        Document Title</h4>

                    <div class="dior-ic-6a49e78bbe">
                        <span id="doc-preview-category"
                            class="dior-ic-0a03b45d9b">Category</span>
                    </div>

                    <div class="doc-details-grid dior-ic-bf2153c7ca"
                       >
                        <div>
                            <strong
                                class="dior-ic-b7ab6167a8">Document
                                ID:</strong>
                            <span id="doc-preview-id"
                                class="dior-ic-a6562b0806">DOC-88219</span>
                        </div>
                        <div>
                            <strong
                                class="dior-ic-b7ab6167a8">Date
                                Issued:</strong>
                            <span id="doc-preview-date" class="dior-ic-a6562b0806">Sept
                                4, 2026</span>
                        </div>
                        <div>
                            <strong
                                class="dior-ic-b7ab6167a8">Issued
                                By / Provider:</strong>
                            <span id="doc-preview-author"
                                class="dior-ic-a6562b0806">Dior Medical Clinical
                                Team</span>
                        </div>
                        <div>
                            <strong
                                class="dior-ic-b7ab6167a8">Patient:</strong>
                            <span id="doc-preview-patient"
                                class="dior-ic-a6562b0806"><?php echo esc_html($profile['full_name']); ?></span>
                        </div>
                    </div>

                    <div class="doc-seal dior-ic-3e0fc52c0f"
                       >
                        <i class="fa-solid fa-shield-halved dior-ic-e3f2780fa1"></i>
                        <span>Digitally Certified &amp; E-Signed by Dior Medical Telehealth System</span>
                    </div>
                </div>
            </div>

            <div class="dior-modal-footer dior-ic-e90aec0c99"
               >
                <button type="button" class="dior-btn-gold-secondary dior-ic-2cc983c6d0" onclick="diorCloseModal('modal-view-document')"
                   >Close</button>
                <button type="button" class="dior-btn-gold-primary dior-ic-99a13c029f" onclick="diorDownloadCurrentDocumentPdf()"
                   >
                    <i class="fa-solid fa-file-arrow-down"></i> Print / Download PDF
                </button>
            </div>
        </div>
    </div> <!-- /#modal-view-document -->

    <!-- 8. APPOINTMENT DETAIL MODAL (PATIENT PORTAL) -->
    <div class="dior-modal-overlay dior-ic-44a70a0420" id="modal-view-appointment-detail">
        <div class="dior-modal-dialog dior-ic-e9cfd8c2ad"
           >
            <div class="dior-modal-header dior-ic-38b9f470b9"
               >
                <h3
                    class="dior-ic-55d820b400">
                    <i class="fa-solid fa-calendar-check dior-ic-5d59a9a58e"></i> Appointment Details
                </h3>
                <button type="button" class="dior-modal-close dior-ic-f476a9f033" onclick="diorCloseModal('modal-view-appointment-detail')"
                   >&times;</button>
            </div>

            <div class="dior-modal-body dior-ic-82b34f2b62"
               >
                <!-- Hero Info Card -->
                <div
                    class="dior-ic-69035811a2">
                    <div
                        class="dior-ic-5d08ce7ec2">
                        <div>
                            <span id="pat-modal-appt-id"
                                class="dior-ic-808a4fa3c3">APPT-ID</span>
                            <h4 id="pat-modal-appt-provider"
                                class="dior-ic-28d0539f78">Attending Physician
                            </h4>
                            <span id="pat-modal-appt-spec" class="dior-ic-55709c3d5d">Telehealth Urgent
                                Care</span>
                        </div>
                        <span id="pat-modal-appt-status-badge" class="dior-st ok dior-ic-70c5533809"
                           >Confirmed</span>
                    </div>

                    <div
                        class="dior-ic-d9dafa9db0">
                        <div>
                            <strong
                                class="dior-ic-3a5e03a707">Date
                                &amp; Time</strong>
                            <span id="pat-modal-appt-datetime" class="dior-ic-035b2e7453">—</span>
                        </div>
                        <div>
                            <strong
                                class="dior-ic-3a5e03a707">Consultation
                                Mode</strong>
                            <span id="pat-modal-appt-mode" class="dior-ic-035b2e7453"><i
                                    class="fa-solid fa-video dior-ic-5d59a9a58e"></i> Video Visit</span>
                        </div>
                        <div class="dior-ic-b652b6f21e">
                            <strong
                                class="dior-ic-3a5e03a707">Service
                                / Clinical Reason</strong>
                            <span id="pat-modal-appt-condition" class="dior-ic-ad42b18349">Telehealth
                                Urgent Care</span>
                        </div>
                    </div>
                </div>

                <!-- Live Zoom Link Strip (if available) -->
                <div id="pat-modal-zoom-wrap"
                    class="dior-ic-5d5fe245c5">
                    <div class="dior-ic-63a4b96787">
                        <div
                            class="dior-ic-99e83aaa9f">
                            <i class="fa-solid fa-video"></i>
                        </div>
                        <div>
                            <strong class="dior-ic-08abd803b3">Live Virtual Consultation
                                Room</strong>
                            <span class="dior-ic-c243108e79">Join physician on secure Zoom Video</span>
                        </div>
                    </div>
                    <a id="pat-modal-zoom-link" href="#" target="_blank" rel="noopener noreferrer"
                        class="dior-ic-459d58fbd7">
                        <i class="fa-solid fa-video"></i> Join Video Call &rarr;
                    </a>
                </div>

                <!-- Patient & Clinical Notes Card -->
                <div
                    class="dior-ic-a9ce782a97">
                    <h5
                        class="dior-ic-8277d4959a">
                        <i class="fa-solid fa-notes-medical dior-ic-5d59a9a58e"></i> Clinical Notes &amp;
                        Instructions
                    </h5>
                    <p id="pat-modal-appt-notes"
                        class="dior-ic-d181656743">
                        Standard telehealth clinical consultation scheduled. All submitted medical intake questionnaires
                        are pre-verified.
                    </p>
                </div>

                <div
                    class="dior-ic-3e0fc52c0f">
                    <i class="fa-solid fa-shield-halved dior-ic-e3f2780fa1"></i>
                    <span>HIPAA Compliant &bull; Verified Dior Medical Telehealth Record</span>
                </div>
            </div>

            <div class="dior-modal-footer dior-ic-9b6b762386"
               >
                <button type="button" class="dior-btn-gold-secondary dior-ic-d8eb827f48"
                    onclick="diorCloseModal('modal-view-appointment-detail')"
                   >Close</button>
            </div>
        </div>
    </div> <!-- /#modal-view-appointment-detail -->

    <!-- 9. PRESCRIPTION DETAIL MODAL (PATIENT PORTAL) -->
    <div class="dior-modal-overlay dior-ic-9049d9cd09" id="modal-view-rx-detail"
       >
        <div class="dior-modal-dialog dior-ic-e270dbf307"
           >

            <!-- Modal Top Action Header -->
            <div class="dior-modal-header dior-ic-bf0665f2de"
               >
                <div class="dior-ic-2fa3fca1f7">
                    <div
                        class="dior-ic-20a4bac022">
                        <i class="fa-solid fa-file-prescription"></i>
                    </div>
                    <div>
                        <h3
                            class="dior-ic-5993936b7f">
                            Official Telehealth Prescription (e-Rx)
                        </h3>
                        <span class="dior-ic-25f3b27ac7">
                            DEA &bull; NPI Registered Practice &bull; Surescripts e-Rx Certified
                        </span>
                    </div>
                </div>
                <button type="button" class="dior-modal-close dior-ic-9305d040bf" onclick="diorCloseModal('modal-view-rx-detail')"
                   >&times;</button>
            </div>

            <!-- Modal Body: Letterhead Sheet (Image 2, 3, 4) -->
            <div class="dior-modal-body dior-ic-6714f5aeeb"
               >

                <div class="dior-rx-letterhead-sheet dior-ic-cc0172605f"
                   >

                    <!-- Top Clinic & Doctor Header (Images 2 & 3) -->
                    <div
                        class="dior-ic-f9030d0190">
                        <div>
                            <div class="dior-rx-doc-name dior-ic-3752d6b3fd" id="pat-modal-rx-doc-header"
                               >
                                <span id="pat-modal-rx-doc">Dr. Marcus Sterling, MD</span>
                            </div>
                            <div class="dior-rx-doc-creds dior-ic-fb6f89aea5"
                               >
                                QUALIFICATION: M.B.B.S, M.D. &bull; DEA: MV8492019 &bull; NPI: 1849201948
                            </div>
                            <div class="dior-rx-clinic-sub dior-ic-502f59757f"
                               >
                                <strong class="dior-ic-25e0cc2c47">DIOR MEDICAL TELEHEALTH &amp; URGENT CARE</strong><br>
                                Clinical Telemedicine Division &bull; Surescripts e-Rx Certified
                            </div>
                        </div>

                        <!-- Clinic Logo & Caduceus Symbol -->
                        <div
                            class="dior-ic-32c53658c7">
                            <?php
                            $disp_logo = !empty($logo_url) ? $logo_url : site_url('/wp-content/uploads/2026/08/logo.png');
                            ?>
                            <img src="<?php echo esc_url($disp_logo); ?>" alt="Dior Medical"
                                class="dior-ic-ac19460d69">
                            <div
                                class="dior-ic-17c0ef557f">
                                <i class="fa-solid fa-staff-snake dior-ic-2cc80aed32"></i>
                                <span class="dior-ic-03cc0d21b0">Digital Rx Service</span>
                            </div>
                            <span
                                class="dior-ic-4d08c3d882">
                                <i class="fa-solid fa-circle-check"></i> NCPDP Real-Time EDI Script
                            </span>
                        </div>
                    </div>

                    <!-- Barcode & Issuance Line (Image 3) -->
                    <div
                        class="dior-ic-7b94dc2c83">
                        <div class="dior-rx-barcode-wrap">
                            <div class="dior-rx-barcode-lines">
                                <span class="dior-ic-8c313dc613"></span><span
                                    class="dior-ic-5375fe8cf1"></span><span
                                    class="dior-ic-a4f6c77c37"></span><span class="dior-ic-0399ac9254"></span>
                                <span class="dior-ic-9dc00e0931"></span><span
                                    class="dior-ic-c7fe51ade1"></span><span
                                    class="dior-ic-8c313dc613"></span><span class="dior-ic-a4f6c77c37"></span>
                                <span class="dior-ic-0399ac9254"></span><span class="dior-ic-9dc00e0931"></span><span
                                    class="dior-ic-5375fe8cf1"></span><span class="dior-ic-a4f6c77c37"></span>
                                <span class="dior-ic-8c313dc613"></span><span class="dior-ic-9dc00e0931"></span><span
                                    class="dior-ic-0399ac9254"></span><span class="dior-ic-a4f6c77c37"></span>
                            </div>
                            <span class="dior-rx-barcode-txt" id="pat-modal-rx-barcode-txt">*RX-000000*</span>
                        </div>
                        <div class="dior-ic-5fd58752b8">
                            <div>Prescription Date: <span id="pat-modal-rx-date"
                                    class="dior-ic-01276c62f3">—</span></div>
                            <div class="dior-ic-5e92856bee">
                                Unique Rx UID: <strong id="pat-modal-rx-id"
                                    class="dior-ic-7b6e16631f">Rx
                                    #ORDER</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Patient Demographics: Classical Prescription Pad Underline Layout (Exact Match to User's Image 2) -->
                    <div class="dior-rx-patient-pad-box dior-ic-4638e80c10"
                       >
                        <!-- Row 1: Patient Name & Date -->
                        <div
                            class="dior-ic-06b21f47ad">
                            <div class="dior-ic-afcc30d60e">
                                <span
                                    class="dior-ic-0904e4e822">Patient
                                    Name:</span>
                                <span id="pat-modal-rx-pat-name"
                                    class="dior-ic-b252d41b17"><?php echo esc_html($profile['full_name']); ?></span>
                            </div>
                            <div class="dior-ic-d866ca125a">
                                <span
                                    class="dior-ic-0904e4e822">Date:</span>
                                <span id="pat-modal-rx-pad-date"
                                    class="dior-ic-b252d41b17">—</span>
                            </div>
                        </div>

                        <!-- Row 2: Age, Gender, Weight -->
                        <div
                            class="dior-ic-d11db2d048">
                            <div class="dior-ic-7ac7cda799">
                                <span
                                    class="dior-ic-0904e4e822">Age:</span>
                                <span id="pat-modal-rx-pat-age"
                                    class="dior-ic-b252d41b17"><?php
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
                            <div class="dior-ic-4a82ebc967">
                                <span
                                    class="dior-ic-0904e4e822">Gender:</span>
                                <span id="pat-modal-rx-pat-gender"
                                    class="dior-ic-b252d41b17"><?php echo esc_html($profile['gender'] ?? 'Female'); ?></span>
                            </div>
                            <div class="dior-ic-4a82ebc967">
                                <span
                                    class="dior-ic-0904e4e822">Weight:</span>
                                <span id="pat-modal-rx-pat-weight"
                                    class="dior-ic-b252d41b17"><?php echo esc_html($profile['weight'] ?? 'NKDA (Verified)'); ?></span>
                            </div>
                        </div>

                        <!-- Row 3: Diagnosis -->
                        <div class="dior-ic-395b20a3ba">
                            <span
                                class="dior-ic-0904e4e822">Diagnosis:</span>
                            <span id="pat-modal-rx-diagnosis"
                                class="dior-ic-956613932b">Clinical
                                Telehealth Consultation &bull; General Wellness</span>
                        </div>
                    </div>

                    <!-- Classical ℞ Prescription Order Bar (Image 3) -->
                    <div class="dior-rx-title-bar">
                        <div class="dior-ic-f725034276">
                            <span class="dior-rx-symbol">&#8478;</span>
                            <span
                                class="dior-ic-0a18e0c67b">
                                Official Prescription Directive
                            </span>
                        </div>
                        <span
                            class="dior-ic-9b6818a37f">
                            Schedule VI / Legend Non-Controlled
                        </span>
                    </div>

                    <!-- Clean Structured Medication Table (Images 3 & 4) -->
                    <table class="dior-rx-med-table">
                        <thead>
                            <tr>
                                <th class="dior-ic-5ad98bfc70">#</th>
                                <th class="dior-ic-d44a12258f">Medicine Name &amp; Formulation</th>
                                <th class="dior-ic-06e9f6de59">Dosage &amp; Usage Instructions (SIG)</th>
                                <th class="dior-ic-a13ed7aa9b">Dispense / Refills</th>
                            </tr>
                        </thead>
                        <tbody id="pat-modal-rx-table-body">
                            <!-- Populated dynamically via diorViewRxDetails() -->
                        </tbody>
                    </table>

                    <!-- Advice Given / Clinical Instructions (Image 3) -->
                    <div class="dior-rx-advice-card">
                        <div class="dior-rx-advice-title">
                            <i class="fa-solid fa-notes-medical"></i> Advice Given &amp; Clinical Instructions:
                        </div>
                        <div class="dior-rx-advice-body" id="pat-modal-rx-advice">
                            &bull; Take medication strictly as prescribed. Do not exceed the stated dose.<br>
                            &bull; Take with a full glass of water. Avoid alcohol and heavy unprescribed
                            interactions.<br>
                            &bull; If any hypersensitivity, rash, or adverse effect occurs, discontinue immediately and
                            contact the clinic.
                        </div>
                    </div>

                    <!-- Designated Pharmacy Routing EDI Box -->
                    <div class="dior-rx-pharmacy-line">
                        <div>
                            <strong
                                class="dior-ic-049494d350">
                                Designated Fulfillment Pharmacy:
                            </strong>
                            <span id="pat-modal-rx-pharm-name"
                                class="dior-ic-0aff6f1541">
                                <?php echo esc_html(!empty($profile['pharmacy_name']) ? $profile['pharmacy_name'] : 'Preferred Pharmacy on File'); ?>
                            </span>
                            <span id="pat-modal-rx-routing"
                                class="dior-ic-e3862574c4">
                                <i class="fa-solid fa-circle-check"></i> Routed Electronically via Surescripts NCPDP EDI
                            </span>
                        </div>
                        <span id="pat-modal-rx-status-badge"
                            class="dior-ic-ef921fe83b">
                            <i class="fa-solid fa-check"></i> Transmission Confirmed
                        </span>
                    </div>

                    <!-- Physician Digital Signature & Telehealth Legal Notice (Images 2 & 4) -->
                    <div class="dior-rx-sig-container">
                        <div class="dior-rx-legal-notice">
                            <strong class="dior-ic-03cc0d21b0">TELEHEALTH LEGAL CERTIFICATION:</strong><br>
                            This prescription was electronically reviewed, approved, and digitally signed by an
                            authorized healthcare provider pursuant to a valid telehealth encounter under DEA 21 CFR
                            Part 1311 and state medical practice acts.
                        </div>
                        <div class="dior-rx-sig-block">
                            <div class="dior-rx-sig-line">
                                <img id="pat-modal-rx-sig-img" src="" alt="Doctor Signature"
                                    class="dior-ic-3771746c5d">
                                <span id="pat-modal-rx-sig-name" class="dior-rx-sig-script">
                                    /s/ Dr. Marcus Sterling, MD
                                </span>
                            </div>
                            <div class="dior-rx-sig-lbl">
                                Authorized Physician Signature
                            </div>
                            <div id="pat-modal-rx-sig-date" class="dior-rx-sig-stamp">
                                SHA256 Encrypted &bull; Issued Sep 14, 2026
                            </div>
                        </div>
                    </div>

                    <!-- Letterhead Bottom Clinic Footer Details (Image 2) -->
                    <div class="dior-rx-footer-line">
                        <div>
                            <i class="fa-solid fa-location-dot dior-ic-9043ac3683"></i> 100
                            Medical Plaza, Suite 400 &bull; Dior Medical Telehealth Group
                        </div>
                        <div>
                            <i class="fa-solid fa-phone dior-ic-9043ac3683"></i> (800) 555-DIOR
                            &bull; concierge@diormedical.com
                        </div>
                    </div>

                </div> <!-- /dior-rx-letterhead-sheet -->

            </div> <!-- /dior-modal-body -->

            <!-- Modal Action Footer (Permanently Pinned, Full Visibility) -->
            <div class="dior-modal-footer dior-ic-003eb72b4a"
               >
                <button type="button" id="pat-modal-rx-refill-btn"
                    class="dior-ic-aef0d9cdb6">
                    <i class="fa-solid fa-arrows-rotate dior-ic-1a809d86ac"></i> Request Refill
                </button>
                <div class="dior-ic-6c5b3070b0">
                    <button type="button" id="pat-modal-rx-pdf-btn"
                        class="dior-ic-1d1379760d">
                        <i class="fa-solid fa-file-arrow-down"></i> Download Official PDF
                    </button>
                    <button type="button" onclick="diorCloseModal('modal-view-rx-detail')"
                        class="dior-ic-a340556f29">Close</button>
                </div>
            </div>
        </div>
    </div> <!-- /#modal-view-rx-detail -->

    
</div> <!-- /#dior-patient-portal-app -->
