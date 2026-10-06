<div class="dior-wrap dior-doctor-wrap" id="dior-doctor-app">

    <div class="dior-drawer-backdrop" id="dior-doc-backdrop" onclick="diorDocCloseMobile()"></div>

    <div class="dior-app">

        <!-- ============================================================ -->
        <!-- DOCTOR SIDEBAR                                               -->
        <!-- ============================================================ -->
        <aside class="dior-side" id="dior-doc-sidebar">
            <div class="dior-side-header">
                <div class="brand">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="brand-link">
                        <?php if (!empty($logo_url)): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="Dior Medical" class="brand-logo">
                        <?php else: ?>
                            <span class="dior-ic-6b69feee17">Dior
                                Medical</span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- Doctor Identity Card Removed -->

            <!-- Nav -->
            <style>
            .dior-nav-item.has-submenu { display: flex; flex-direction: column; width: 100%; }
            .submenu-icon { margin-left: auto !important; font-size: 12px; }
            .dior-submenu { display: none; flex-direction: column; padding-left: 20px; }
            .dior-nav-item.active .dior-submenu { display: flex; }
            .dior-nav-item.active > .dior-nav-btn .submenu-icon::before { content: "\f068"; /* fa-minus */ }
            .dior-nav-item:not(.active) > .dior-nav-btn .submenu-icon::before { content: "\f067"; /* fa-plus */ }
            .dior-submenu .submenu-btn {
                font-size: 14px;
                padding: 10px 16px;
                color: #64748B;
                background: transparent;
                border: none;
                text-align: left;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .dior-submenu .submenu-btn i { font-size: 10px; width: 12px; }
            .dior-submenu .submenu-btn:hover, .dior-submenu .submenu-btn.active { color: #4F46E5; font-weight: 600; }
            </style>
            <nav class="dior-side-nav">
                <div class="grp">
                    <span class="grp-title">PROVIDER MENU</span>

                    <button type="button" class="dior-nav-btn active" data-tab="doc-overview"><i
                            class="fa-solid fa-gauge-high"></i><span class="nav-label">Dashboard</span></button>
                    
                    <div class="dior-nav-item has-submenu" id="appointments-menu-item">
                        <button type="button" class="dior-nav-btn" onclick="diorToggleSubmenu(event, 'appointments-menu-item')">
                            <i class="fa-regular fa-calendar"></i><span class="nav-label">Appointments</span>
                            <i class="fa-solid fa-minus submenu-icon"></i>
                        </button>
                        <div class="dior-submenu">
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-appointments">
                                <i class="fa-solid fa-chevron-right"></i> Appointment Calendar
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-appointments-view">
                                <i class="fa-solid fa-chevron-right"></i> View Appointment
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-appointments-book">
                                <i class="fa-solid fa-chevron-right"></i> Book Appointment
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-appointments-edit">
                                <i class="fa-solid fa-chevron-right"></i> Edit Appointment
                            </button>
                        </div>
                    </div>
                    <div class="dior-nav-item has-submenu" id="patients-menu-item">
                        <button type="button" class="dior-nav-btn" onclick="diorToggleSubmenu(event, 'patients-menu-item')">
                            <i class="fa-solid fa-users"></i><span class="nav-label">Patients</span>
                            <i class="fa-solid fa-minus submenu-icon"></i>
                        </button>
                        <div class="dior-submenu">
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-patients-all">
                                <i class="fa-solid fa-chevron-right"></i> All Patients
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-patients-add">
                                <i class="fa-solid fa-chevron-right"></i> Add Patient
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-patients-edit">
                                <i class="fa-solid fa-chevron-right"></i> Edit Patient
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-patients-records">
                                <i class="fa-solid fa-chevron-right"></i> Patient Records
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-patients-profile">
                                <i class="fa-solid fa-chevron-right"></i> Patient Profile
                            </button>
                        </div>
                    </div>
                    <button type="button" class="dior-nav-btn" data-tab="doc-analytics"><i
                            class="fa-solid fa-chart-line"></i><span class="nav-label">Analytics</span></button>
                    <div class="dior-nav-item has-submenu" id="accounts-menu-item">
                        <button type="button" class="dior-nav-btn" onclick="diorToggleSubmenu(event, 'accounts-menu-item')">
                            <i class="fa-solid fa-wallet"></i><span class="nav-label">Accounts</span>
                            <i class="fa-solid fa-minus submenu-icon"></i>
                        </button>
                        <div class="dior-submenu">
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-accounts-bill-list">
                                <i class="fa-solid fa-chevron-right"></i> Bill List
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-accounts-add-bill">
                                <i class="fa-solid fa-chevron-right"></i> Add Bill
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-accounts-income">
                                <i class="fa-solid fa-chevron-right"></i> Income
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-accounts-expenses">
                                <i class="fa-solid fa-chevron-right"></i> Expenses
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-accounts-income-report">
                                <i class="fa-solid fa-chevron-right"></i> Income Report
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-accounts-invoice">
                                <i class="fa-solid fa-chevron-right"></i> Invoice
                            </button>
                        </div>
                    </div>
                    <button type="button" class="dior-nav-btn" data-tab="doc-consultation-notes"><i
                            class="fa-solid fa-notes-medical"></i><span class="nav-label">Consultations
                            Notes</span></button>
                    <button type="button" class="dior-nav-btn" data-tab="doc-e-prescriptions"><i
                            class="fa-solid fa-prescription-bottle-medical"></i><span class="nav-label">E -
                            Prescriptions</span></button>
                    <div class="dior-nav-item has-submenu" id="pharmacy-menu-item">
                        <button type="button" class="dior-nav-btn" onclick="diorToggleSubmenu(event, 'pharmacy-menu-item')">
                            <i class="fa-solid fa-pills"></i><span class="nav-label">Pharmacy</span>
                            <i class="fa-solid fa-minus submenu-icon"></i>
                        </button>
                        <div class="dior-submenu">
                            <button type="button" class="dior-nav-btn submenu-btn active" data-tab="doc-pharmacy-list">
                                <i class="fa-solid fa-chevron-right"></i> Medicine List
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-pharmacy-add">
                                <i class="fa-solid fa-chevron-right"></i> Add Medicine
                            </button>
                        </div>
                    </div>
                    <div class="dior-nav-item has-submenu" id="documents-menu-item">
                        <button type="button" class="dior-nav-btn" onclick="diorToggleSubmenu(event, 'documents-menu-item')">
                            <i class="fa-solid fa-file-medical"></i><span class="nav-label">Documents & Report</span>
                            <i class="fa-solid fa-minus submenu-icon"></i>
                        </button>
                        <div class="dior-submenu">
                            <button type="button" class="dior-nav-btn submenu-btn active" data-tab="doc-documents-upload">
                                <i class="fa-solid fa-chevron-right"></i> Upload Documents
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-documents-templates">
                                <i class="fa-solid fa-chevron-right"></i> Consent Templates
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-documents-signed">
                                <i class="fa-solid fa-chevron-right"></i> Signed Consent
                            </button>
                        </div>
                    </div>
                    <div class="dior-nav-item has-submenu" id="telemedicine-menu-item">
                        <button type="button" class="dior-nav-btn" onclick="diorToggleSubmenu(event, 'telemedicine-menu-item')">
                            <i class="fa-solid fa-video"></i><span class="nav-label">Telemedicine</span>
                            <i class="fa-solid fa-minus submenu-icon"></i>
                        </button>
                        <div class="dior-submenu">
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-telemed-video">
                                <i class="fa-solid fa-chevron-right"></i> Video Consultation
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-telemed-records">
                                <i class="fa-solid fa-chevron-right"></i> Virtual Visit Records
                            </button>
                        </div>
                    </div>
                    <button type="button" class="dior-nav-btn" data-tab="doc-patient-review"><i
                            class="fa-solid fa-star"></i><span class="nav-label">Patient Review</span></button>
                    <div class="dior-nav-item has-submenu" id="notifications-menu-item">
                        <button type="button" class="dior-nav-btn" onclick="diorToggleSubmenu(event, 'notifications-menu-item')">
                            <i class="fa-solid fa-bell"></i><span class="nav-label">Notification</span>
                            <i class="fa-solid fa-minus submenu-icon"></i>
                        </button>
                        <div class="dior-submenu">
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-notifications-alerts">
                                <i class="fa-solid fa-chevron-right"></i> Alerts & Announcements
                            </button>
                            <button type="button" class="dior-nav-btn submenu-btn" data-tab="doc-notifications-system">
                                <i class="fa-solid fa-chevron-right"></i> System Notifications
                            </button>
                        </div>
                    </div>
                    <button type="button" class="dior-nav-btn" data-tab="doc-consultation"><i
                            class="fa-solid fa-door-open"></i><span class="nav-label">Consultation Room</span></button>
                    <button type="button" class="dior-nav-btn" data-tab="doc-settings"><i
                            class="fa-solid fa-gear"></i><span class="nav-label">Settings</span></button>
                </div>
            </nav>
            <script>
            function diorToggleSubmenu(e, menuId) {
                if (e) e.preventDefault();
                document.querySelectorAll('.dior-nav-item.has-submenu').forEach(item => {
                    if (item.id !== menuId) {
                        item.classList.remove('active');
                    }
                });
                document.getElementById(menuId).classList.toggle('active');
            }
            </script>
        </aside>

        <!-- ============================================================ -->
        <!-- MAIN CONTENT                                                  -->
        <!-- ============================================================ -->
        <main class="dior-main-content">

            <!-- Topbar -->
            <header class="dior-topbar">
                <div class="dior-topbar-left">
                    <button type="button" class="dior-mobile-menu-toggle" onclick="diorDocOpenMobile()">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="dior-top-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="dior-doc-search" placeholder="Search patients, appointments..."
                            oninput="diorDocSearch(this.value)">

                    </div>
                </div>
                <div class="dior-topbar-right">
                    <button type="button" class="dior-msg-btn dior-ic-fa44d4cb47">
                        <i class="fa-regular fa-envelope"></i>
                    </button>
                    <div class="dior-notif-wrap">
                        <button type="button" class="dior-notif-btn" onclick="diorDocToggleNotif(event)">
                            <i class="fa-regular fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="dior-notif-indicator doc-unread-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dior-notif-dropdown" id="dior-doc-notif-dd">
                            <div class="dior-notif-header">
                                <h4>Notifications</h4>
                                <button type="button" onclick="diorDocMarkAllRead()">Mark all read</button>
                            </div>
                            <div class="dior-notif-list">
                                <?php foreach (array_slice($notifications, 0, 5) as $n): ?>
                                    <div class="dior-notif-item <?php echo !$n['is_read'] ? 'unread' : ''; ?>"
                                        data-notif-id="<?php echo esc_attr($n['id']); ?>">
                                        <div class="notif-icon"><i class="fa-solid <?php echo esc_attr($n['icon']); ?>"></i>
                                        </div>
                                        <div class="notif-body">
                                            <strong><?php echo esc_html($n['title']); ?></strong>
                                            <p><?php echo esc_html(mb_substr($n['message'], 0, 30) . (mb_strlen($n['message']) > 30 ? '...' : '')); ?>
                                            </p>
                                            <span
                                                class="notif-time"><?php echo esc_html(dior_format_notification_time($n)); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="dior-notif-footer">
                                <button type="button" onclick="diorDocSwitchTab('doc-notifications-alerts'); document.getElementById('notifications-menu-item').classList.add('active');">View All
                                    &rarr;</button>
                            </div>
                        </div>
                    </div>
                    <!-- User Quick Profile Pill & Dropdown -->
                    <div class="dior-user-pill-wrap">
                        <button type="button" class="dior-user-pill-btn" onclick="diorToggleProfileDropdown(event)"
                            aria-label="Provider Profile">
                            <div class="dior-pill-avatar dior-ic-fc0f6bfbac" id="dior-doctor-top-avatar">
                                <?php if (!empty($doctor['avatar_url'])): ?>
                                    <img src="<?php echo esc_url($doctor['avatar_url']); ?>"
                                        alt="<?php echo esc_attr($doctor['full_name']); ?>" class="dior-ic-9698adf4ac">
                                <?php else: ?>
                                    <i class="fa-solid fa-user-doctor dior-ic-8dfc28cda3"></i>
                                <?php endif; ?>
                            </div>
                        </button>

                        <div class="dior-profile-dropdown" id="dior-profile-dropdown">
                            <div class="dior-profile-dropdown-header">
                                HELLO <?php echo esc_html(strtoupper($doctor['full_name'])); ?>..
                            </div>
                            <ul class="dior-profile-dropdown-menu">
                                <li>
                                    <button type="button" onclick="diorDocSwitchTab('doc-settings')">
                                        <i class="fa-solid fa-gear"></i> Settings
                                    </button>
                                </li>
                                <li>
                                    <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"
                                        class="dior-logout-text-btn">
                                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ======================================================== -->
            <!-- TAB PANELS                                                -->
            <!-- ======================================================== -->
            <div class="dior-tab-content" id="dior-doc-content">

                <!-- -- OVERVIEW -->
                <!-- ── OVERVIEW ── -->
                <!-- ── OVERVIEW ── -->
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/overview.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/appointments.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/edit-appointment.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/view-appointment.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/book-appointment.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/all-patients.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/add-patient.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/edit-patient.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/patient-records.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/patient-profile.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/analytics.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/bill-list.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/add-bill.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/income.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/expenses.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/income-report.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/invoice.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/consultation-notes.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/pharmacy.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/digital-prescriptions.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/documents.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/telemedicine-video.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/telemedicine-records.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/patient-review.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/notifications-alerts.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/notifications-system.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/consultation-room.php'; ?>
                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/settings.php'; ?>
            </div><!-- /tab-content -->
        </main>
    </div><!-- /dior-app -->
</div><!-- /dior-doctor-wrap -->

<!-- Patient Detail Modal -->
<div class="dior-modal-overlay" id="modal-doc-patient">
    <div class="dior-modal-dialog dior-modal-lg dior-ic-c1484e0c1a">
        <div class="dior-modal-header dior-ic-28da7fda03">
            <h3 id="modal-doc-patient-title" class="dior-ic-be828fc992">Patient Detail</h3>
            <button type="button" class="dior-modal-close" onclick="diorDocCloseModal()">&times;</button>
        </div>
        <div class="dior-modal-body" id="modal-doc-patient-body">
            <div class="dior-ic-36e7e62c1c">
                <i class="fa-solid fa-spinner fa-spin fa-2x dior-ic-d97e7c74af"></i>
            </div>
        </div>
    </div>
</div>

<!-- Secure Document Upload Modal -->
<div class="dior-modal-overlay" id="modal-doc-upload">
    <div class="dior-modal-dialog dior-ic-c1484e0c1a dior-ic-318c9238d5">
        <div class="dior-modal-header dior-ic-70f1e92085">
            <h3 class="dior-ic-0ae9980015">
                <i class="fa-solid fa-cloud-arrow-up dior-ic-1b148c2304"></i> Upload Clinical Document
            </h3>
            <button type="button" class="dior-modal-close dior-ic-244c10381e"
                onclick="diorDocCloseUploadModal()">&times;</button>
        </div>
        <div class="dior-modal-body dior-ic-1fcfc7bdca">
            <form id="dior-doc-upload-form" onsubmit="diorDocSubmitUpload(event)">
                <div class="dior-ic-a70f3b9fde">
                    <label class="dior-ic-a1f28435ce">Select
                        Patient <span class="dior-ic-f336360291">*</span></label>
                    <select name="patient_id" required class="dior-ic-52d2e4f754">
                        <option value="">-- Select Patient --</option>
                        <?php if (!empty($patients)): ?>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo (int) $p['user_id']; ?>">
                                    <?php echo esc_html($p['full_name']); ?> (<?php echo esc_html($p['patient_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="dior-ic-1f22e00e7f">
                    <div>
                        <label class="dior-ic-a1f28435ce">Category
                            <span class="dior-ic-f336360291">*</span></label>
                        <select name="doc_category" required class="dior-ic-52d2e4f754">
                            <option value="Lab Report">Lab Report</option>
                            <option value="Imaging / Radiology">Imaging / Radiology</option>
                            <option value="Medical Record">Medical Record</option>
                            <option value="Doctor Excuse Note">Doctor Excuse Note</option>
                            <option value="Signed Consent (HIPAAtizer)">Signed Consent (HIPAA)</option>
                            <option value="Prescription / Rx">Prescription / Rx</option>
                            <option value="Referral Letter">Referral Letter</option>
                            <option value="Other">Other Clinical Document</option>
                        </select>
                    </div>
                    <div>
                        <label class="dior-ic-a1f28435ce">Document
                            Title <span class="dior-ic-f336360291">*</span></label>
                        <input type="text" name="doc_title" required placeholder="e.g. CBC & Metabolic Lab Panel"
                            class="dior-ic-0f28e8b5ba">
                    </div>
                </div>

                <div class="dior-ic-a70f3b9fde">
                    <label class="dior-ic-a1f28435ce">Select
                        File (PDF, PNG, JPG, DOCX) <span class="dior-ic-f336360291">*</span></label>
                    <div class="dior-ic-bd2577932d">
                        <input type="file" name="doc_file" id="doc_file_input" required
                            accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" class="dior-ic-dac75c1e6b">
                        <span class="dior-ic-ed2d0b4047">Max file size:
                            25MB. Files are automatically stored in the secure protected vault.</span>
                    </div>
                </div>

                <div class="dior-ic-7fb1dfbd8b">
                    <label class="dior-ic-a1f28435ce">Clinical
                        Notes / Remarks (Optional)</label>
                    <textarea name="doc_notes" rows="2"
                        placeholder="Add confidential clinical context or review notes..."
                        class="dior-ic-a34cd74157"></textarea>
                </div>

                <div class="dior-ic-f4f4b02bb7">
                    <button type="button" class="dior-btn-sm dior-btn-ghost dior-ic-0d30ad5105"
                        onclick="diorDocCloseUploadModal()">
                        Cancel
                    </button>
                    <button type="submit" id="doc-upload-submit-btn"
                        class="dior-btn-sm dior-btn-primary dior-ic-242205d8db">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload & Secure File
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Video Call Date/Status Alert Modal -->
<div class="dior-modal-overlay dior-ic-bfefd52f66" id="dior-call-alert-modal">
    <div class="dior-modal-dialog dior-ic-c1484e0c1a dior-ic-3627f2adcb">
        <div class="dior-ic-2b0eae5150">
            <h3 id="dior-call-alert-title" class="dior-ic-76c82d37bf"><i class="fa-solid fa-video"></i> Video
                Consultation</h3>
            <button type="button" onclick="diorDocCloseCallAlert()" class="dior-ic-5e552e6636">&times;</button>
        </div>
        <div class="dior-ic-b7c3fa3e2e">
            <div id="dior-call-alert-icon-wrap" class="dior-ic-a70f3b9fde">
                <i class="fa-solid fa-calendar-check dior-ic-5131d831f6"></i>
            </div>
            <div id="dior-call-alert-msg" class="dior-ic-20fb3a3573"></div>
            <div class="dior-ic-ad9b37d7f7">
                <button type="button" onclick="diorDocCloseCallAlert()" class="dior-ic-c1e95b8cb0">
                    Close
                </button>
                <button type="button" id="dior-call-alert-override" class="dior-ic-50a199bacc">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Launch Room Anyway
                </button>
            </div>
        </div>
    </div>
    <!-- Modal: Doctor Prescription Review & Confirmation Preview Before Transmitting -->
    <div class="dior-modal-overlay dior-ic-31d66a4530" id="modal-doc-rx-confirm-preview">
        <div class="dior-modal-dialog dior-ic-c1484e0c1a dior-ic-b95b3a7563">

            <!-- Modal Header -->
            <div class="dior-ic-eeb8bc1996">
                <div class="dior-ic-cc0cdde55c">
                    <div class="dior-ic-113a0252fb">
                        <i class="fa-solid fa-prescription"></i>
                    </div>
                    <div>
                        <h3 class="dior-ic-1bb2e9335c">
                            Review &amp; Confirm e-Prescription</h3>
                        <p class="dior-ic-027eaa9977">One-time physician confirmation
                            before transmitting to patient portal and pharmacy network.</p>
                    </div>
                </div>
                <button type="button" onclick="diorDocCloseRxConfirmModal()" title="Close Preview"
                    class="dior-ic-99328beefc">&times;</button>
            </div>

            <!-- Verification Banner -->
            <div class="dior-ic-55cb7d6baa">
                <i class="fa-solid fa-shield-halved dior-ic-2a23b8a4de"></i>
                <span><strong>Clinical Review Check:</strong> Please verify all medications, dosage frequencies, and SIG
                    directions below. Click <em>Confirm &amp; Transmit e-Rx</em> to issue.</span>
            </div>

            <!-- Scrollable Letterhead Preview Body -->
            <div class="dior-ic-5fa26213e3">

                <div class="dior-ic-301c065322">

                    <!-- Caduceus Background Watermark -->
                    <div class="dior-ic-a9c7b2a3c6">
                        <svg viewBox="0 0 24 24" fill="#00A896" class="dior-ic-beff7b8eb3">
                            <path
                                d="M12 2C11.45 2 11 2.45 11 3V4.07C8.5 4.3 6.64 6.22 6.64 8.65C6.64 10.42 7.6 11.95 9.04 12.78C8.38 13.56 8 14.57 8 15.68C8 17.5 9.21 19.06 10.89 19.57L10 21H8V22H16V21H14L13.11 19.57C14.79 19.06 16 17.5 16 15.68C16 14.57 15.62 13.56 14.96 12.78C16.4 11.95 17.36 10.42 17.36 8.65C17.36 6.22 15.5 4.3 13 4.07V3C13 2.45 12.55 2 12 2Z" />
                        </svg>
                    </div>

                    <div class="dior-ic-56b839afae">

                        <!-- Clinic & Doctor Header Line -->
                        <div class="dior-ic-e032b9287b">
                            <div class="dior-ic-fd8a72ae77">
                                <img src="<?php echo esc_url(!empty($logo_url) ? $logo_url : DIOR_PORTAL_URL . 'assets/images/logo.png'); ?>"
                                    alt="Dior Medical" class="dior-ic-b4bbc07589">
                                <div>
                                    <h2 class="dior-ic-779fa12a1e">
                                        DIOR MEDICAL TELEHEALTH</h2>
                                    <p class="dior-ic-6f9deabb06">
                                        Prescribing Provider: <strong
                                            class="dior-ic-a4e0cae076"><?php echo esc_html($doctor['full_name']); ?></strong>
                                        &bull; NPI:
                                        <?php echo esc_html(!empty($doctor['npi']) ? $doctor['npi'] : 'Verified'); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="dior-ic-5de43a68dc">
                                <span class="dior-ic-971ac4d905">
                                    <i class="fa-solid fa-circle-check"></i> Surescripts Real-Time EDI
                                </span>
                                <div class="dior-ic-e8c07aa65e">
                                    Date: <strong id="doc-confirm-rx-date"
                                        class="dior-ic-981fa4e196"><?php echo date('M j, Y'); ?></strong> &bull; UID:
                                    <strong id="doc-confirm-rx-uid" class="dior-ic-c4e8e87e57">RX-PENDING</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Demographics: Classical Prescription Pad Underline Layout (Exact Match to User's Image 2) -->
                        <div class="dior-rx-patient-pad-box dior-ic-6b602f0348">
                            <!-- Row 1: Patient Name & Date -->
                            <div class="dior-ic-7920be03f1">
                                <div class="dior-ic-39d60a05dd">
                                    <span class="dior-ic-98d30f4ce8">Patient
                                        Name:</span>
                                    <span id="doc-confirm-rx-patient-name" class="dior-ic-948bf282f5">�</span>
                                </div>
                                <div class="dior-ic-86e367ca6d">
                                    <span class="dior-ic-98d30f4ce8">Date:</span>
                                    <span id="doc-confirm-rx-pad-date"
                                        class="dior-ic-948bf282f5"><?php echo date('M j, Y'); ?></span>
                                </div>
                            </div>

                            <!-- Row 2: Age, Gender, Weight -->
                            <div class="dior-ic-673394b48e">
                                <div class="dior-ic-b9bf91f417">
                                    <span class="dior-ic-98d30f4ce8">Age:</span>
                                    <span id="doc-confirm-rx-patient-dob" class="dior-ic-948bf282f5">�</span>
                                </div>
                                <div class="dior-ic-0ec881ebe7">
                                    <span class="dior-ic-98d30f4ce8">Gender:</span>
                                    <span id="doc-confirm-rx-patient-gender" class="dior-ic-948bf282f5">Female</span>
                                </div>
                                <div class="dior-ic-0ec881ebe7">
                                    <span class="dior-ic-98d30f4ce8">Weight:</span>
                                    <span id="doc-confirm-rx-patient-weight" class="dior-ic-948bf282f5">NKDA
                                        (Verified)</span>
                                </div>
                            </div>

                            <!-- Row 3: Diagnosis -->
                            <div class="dior-ic-46db622208">
                                <span class="dior-ic-98d30f4ce8">Diagnosis:</span>
                                <span id="doc-confirm-rx-diagnosis" class="dior-ic-c97b5f0bc5">Clinical
                                    Telehealth Consultation &bull; General Wellness</span>
                            </div>
                        </div>

                        <!-- Prescribed Medications Heading (?) -->
                        <div class="dior-ic-2d874fdefb">
                            <div class="dior-ic-5dbede5739">
                                <span class="dior-ic-236b3ce539">?</span>
                                <span class="dior-ic-ded7fd0b2b">Medication
                                    Schedule</span>
                            </div>
                            <span id="doc-confirm-rx-med-count" class="dior-ic-ddc3ea8d3e">0
                                Medications</span>
                        </div>

                        <!-- Medications Clinical Table -->
                        <div class="dior-ic-42532a74dc">
                            <table class="dior-ic-c96d2fb209">
                                <thead>
                                    <tr class="dior-ic-4aab391456">
                                        <th class="dior-ic-a3416f48a6">#</th>
                                        <th class="dior-ic-4980ae41fc">Medication Name &amp; Formulation</th>
                                        <th class="dior-ic-4980ae41fc">Dosage &amp; Usage Instructions (SIG)</th>
                                        <th class="dior-ic-1662ddb0a8">Refills /
                                            Dispense</th>
                                    </tr>
                                </thead>
                                <tbody id="doc-confirm-rx-tbody">
                                    <!-- Populated dynamically by JS -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Clinical Advice & Remarks Block -->
                        <div id="doc-confirm-rx-notes-wrap" class="dior-ic-f5e672d704">
                            <strong>Clinical Instructions / Advice:</strong>
                            <div id="doc-confirm-rx-notes-text" class="dior-ic-8025c13ed4"></div>
                        </div>

                        <!-- Sign-off & Electronic Signature Block -->
                        <div class="dior-ic-af5f7bac20">
                            <div>
                                <span class="dior-ic-099a17e3cc">Prescription
                                    Security Protocol</span>
                                <span class="dior-ic-4936e22a0a">DEA 21 CFR Part 1311 &bull; Electronic
                                    Prescriptions for Controlled &amp; Legend Substances</span>
                            </div>
                            <div class="dior-ic-5de43a68dc">
                                <div id="doc-confirm-rx-sig-wrap" class="dior-ic-6ac867a2a8">
                                    <?php if (!empty($doctor['signature_url'])): ?>
                                        <img id="doc-confirm-rx-sig-img"
                                            src="<?php echo esc_url($doctor['signature_url']); ?>"
                                            alt="Doctor Digital Signature" class="dior-ic-389eaeb7db">
                                    <?php else: ?>
                                        <span id="doc-confirm-rx-sig-text" class="dior-ic-47c4debfe2">/s/
                                            <?php echo esc_html($doctor['full_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="dior-ic-5924bebf66">
                                    <?php echo esc_html($doctor['full_name']); ?>
                                </div>
                                <div class="dior-ic-b82e3640a2">
                                    <?php echo esc_html(!empty($doctor['specialty']) ? $doctor['specialty'] : 'Licensed Healthcare Provider'); ?>
                                    &bull; License
                                    #<?php echo esc_html(!empty($doctor['license_no']) ? $doctor['license_no'] : 'Verified'); ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Modal Action Footer -->
            <div class="dior-ic-2c5e2ef6ce">
                <button type="button" class="dior-btn-sm dior-btn-ghost dior-ic-17c1cfabc5"
                    onclick="diorDocCloseRxConfirmModal()">
                    <i class="fa-solid fa-arrow-left"></i> Back to Edit
                </button>
                <button type="button" id="btn-doc-confirm-transmit-rx" onclick="diorDocExecutePrescriptionTransmit()"
                    class="dior-ic-7d8eddec80">
                    <i class="fa-solid fa-paper-plane"></i> Confirm &amp; Transmit e-Rx
                </button>
            </div>

        </div>
    </div>