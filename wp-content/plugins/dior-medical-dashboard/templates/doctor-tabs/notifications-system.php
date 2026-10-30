<?php
/**
 * Dior Medical — Doctor Notifications Center (Matched 1:1 to Patient Dashboard Design)
 * 100% Bootstrap 5 Utilities & Scoped Component Standards
 */
if (!defined('ABSPATH')) {
    exit;
}

$doctor_id = get_current_user_id();
$doctor_nonce = wp_create_nonce('dior_doctor_nonce');
$ajax_url = admin_url('admin-ajax.php');

$doctor_notif_rows = [];
if (class_exists('Dior_Doctor_Dashboard') && method_exists('Dior_Doctor_Dashboard', 'get_doctor_notifications')) {
    $doctor_notif_rows = Dior_Doctor_Dashboard::get_doctor_notifications($doctor_id);
}
if (!is_array($doctor_notif_rows)) {
    $doctor_notif_rows = [];
}

// Compute notification metrics
$total_count = count($doctor_notif_rows);
$unread_count = 0;
$read_count = 0;
$clinical_count = 0;

foreach ($doctor_notif_rows as $row) {
    if (empty($row['is_read'])) {
        $unread_count++;
    } else {
        $read_count++;
    }
    $t = strtolower($row['type'] ?? '');
    if (in_array($t, ['appointment', 'intake', 'questionnaire', 'clinical', 'prescription', 'rx', 'patient', 'medical'], true)) {
        $clinical_count++;
    }
}
?>

<section class="dior-tab-panel notifications-tab-panel" id="tab-doc-notifications-system"
    style="display: none; background-color: #f8fafc; padding: 20px; position: relative; overflow: hidden;">

    <!-- Scoped Style Overrides for Notifications Panel -->
    <style>
        #tab-doc-notifications-system .dior-notif-stats-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            width: 100%;
        }

        #tab-doc-notifications-system .dior-notif-stat-col {
            flex: 1 1 calc(25% - 12px);
            min-width: 220px;
        }

        @media (max-width: 992px) {
            #tab-doc-notifications-system .dior-notif-stat-col {
                flex: 1 1 calc(50% - 12px);
            }
        }

        @media (max-width: 576px) {
            #tab-doc-notifications-system .dior-notif-stat-col {
                flex: 1 1 100%;
            }
        }

        #tab-doc-notifications-system .dior-page-watermark,
        #tab-doc-notifications-system .page-watermark,
        #tab-doc-notifications-system .dior-big-title {
            display: none !important;
        }
    </style>

    <!-- Header & Breadcrumb -->
    <div class="mb-4"
        style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; position: relative; z-index: 3;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700; color: #1e293b;">
                <i class="fas fa-bell text-primary me-2"></i>Doctor Notifications
            </h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list"
                style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li>
                    <a href="javascript:void(0)"
                        onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-overview')">
                        <i class="fas fa-house" style="font-size: 14px; color: #2563eb;"></i>
                    </a>
                </li>
                <li><span style="color: #cbd5e1;">/</span></li>
                <li><span style="color: #64748b; font-size: 13px;">Provider Portal</span></li>
                <li><span style="color: #cbd5e1;">/</span></li>
                <li class="active"><span style="color: #1e293b; font-weight: 700; font-size: 13px;">Notifications</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="section-body" style="position: relative; z-index: 2;">

        <!-- Stat Cards (4-Column Grid) -->
        <div class="dior-notif-stats-row mb-4">

            <!-- Stat 1: Total Notifications (Indigo) -->
            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-indigo">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2"
                            style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">Total Notifications</span>
                                <h3 class="stat-value text-indigo mt-1 mb-0" id="dior-sys-total-stat">
                                    <?php echo $total_count; ?>
                                </h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fas fa-bell"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center"
                            style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-indigo">
                                <i class="fas fa-clock-rotate-left text-xs me-1"></i> Live Stream
                            </span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">Real-time feed</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stat 2: Unread Items (Amber) -->
            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-amber">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2"
                            style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">Unread Items</span>
                                <h3 class="stat-value text-amber mt-1 mb-0" id="dior-sys-unread-stat">
                                    <?php echo $unread_count; ?>
                                </h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fas fa-envelope-open-text"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center"
                            style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-amber">
                                <i class="fas fa-hourglass-half text-xs me-1"></i> Pending Action
                            </span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">Requires review</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stat 3: Appointments & Clinical Care (Rose) -->
            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-rose">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2"
                            style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">Clinical &amp; Patients</span>
                                <h3 class="stat-value text-rose mt-1 mb-0" id="dior-sys-care-stat">
                                    <?php echo $clinical_count; ?>
                                </h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fas fa-heart-pulse"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center"
                            style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-rose">
                                <i class="fas fa-calendar-check text-xs me-1"></i> Care Stream
                            </span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">Visits &amp; consults</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stat 4: System Status (Emerald) -->
            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-emerald">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2"
                            style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">System Health</span>
                                <h3 class="stat-value text-emerald mt-1 mb-0">100%</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fas fa-shield-check"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center"
                            style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-emerald">
                                <i class="fas fa-circle-check text-xs me-1"></i> Active State
                            </span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">HIPAA Encrypted</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Main Feed Card -->
        <div class="row">
            <div class="col-12" style="width: 100%;">
                <div class="card main-feed-card"
                    style="border-radius: 14px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05); overflow: hidden;">

                    <!-- Card Header -->
                    <div class="card-header border-0 pb-0 pt-3 px-4"
                        style="padding: 20px 24px 14px 24px; border-bottom: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
                        <div class="d-flex align-items-center gap-2"
                            style="display: flex; align-items: center; gap: 10px;">
                            <h4 class="card-title-text mb-0"
                                style="font-size: 16px; font-weight: 700; color: #1e293b; margin: 0;">
                                <i class="fas fa-bell me-2 text-primary"
                                    style="color: #2563eb !important; margin-right: 6px;"></i>Notification Activity Feed
                            </h4>
                            <span class="unread-count-chip" id="dior-sys-unread-chip"><?php echo $unread_count; ?>
                                Unread</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap" style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-sm btn-outline-action"
                                onclick="diorMarkAllSystemNotificationsRead()">
                                <i class="fas fa-check-double me-1" style="margin-right: 4px;"></i> Mark All Read
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-action text-danger"
                                onclick="diorClearReadSystemNotifications()">
                                <i class="fas fa-trash me-1" style="margin-right: 4px;"></i> Clear Read
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-action"
                                onclick="diorDownloadDoctorNotifCSV()">
                                <i class="fas fa-file-arrow-down me-1" style="margin-right: 4px;"></i> Export CSV
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-action"
                                onclick="diorRefreshLiveDoctorNotifs()" title="Refresh Notifications">
                                <i class="fas fa-rotate-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Filters Bar -->
                    <div class="filters-bar px-4 py-3 border-top"
                        style="padding: 14px 24px; background: #f8fafc; border-bottom: 1px solid #edf1f5;">
                        <div class="row align-items-center g-3">
                            <div
                                style="flex: 0 0 auto; display: flex; justify-content: space-between; width: 100%; flex-wrap: wrap; gap: 12px;">
                                <div class="segmented-nav">
                                    <button type="button" class="segmented-tab active btn"
                                        onclick="diorFilterSystemNotif(this, 'all')">
                                        All <span class="count-pill"
                                            id="dior-pill-all"><?php echo $total_count; ?></span>
                                    </button>
                                    <button type="button" class="segmented-tab btn"
                                        onclick="diorFilterSystemNotif(this, 'unread')">
                                        Unread <span class="count-pill"
                                            id="dior-pill-unread"><?php echo $unread_count; ?></span>
                                    </button>
                                    <button type="button" class="segmented-tab btn"
                                        onclick="diorFilterSystemNotif(this, 'read')">
                                        Read <span class="count-pill"
                                            id="dior-pill-read"><?php echo $read_count; ?></span>
                                    </button>
                                </div>
                                <div class="input-group input-group-sm dior-bs-search-group" style="width: 260px;">

                                    <input type="text" id="dior-doc-notif-search"
                                        class="form-control border-start-0 ps-0" placeholder="Search notifications..."
                                        aria-label="Search notifications" onkeyup="diorSearchDoctorNotifications()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Feed Items List -->
                    <div class="card-body p-0">
                        <div class="notifications-list-wrapper" id="dior-system-notif-list">

                            <?php if (!empty($doctor_notif_rows)): ?>
                                <?php foreach ($doctor_notif_rows as $n):
                                    $n_id = $n['id'] ?? uniqid('notif_');
                                    $n_title = $n['title'] ?? 'Notification';
                                    $n_message = $n['message'] ?? '';
                                    $n_type_raw = strtolower($n['type'] ?? 'system');
                                    $is_unread = empty($n['is_read']);
                                    $read_state = $is_unread ? 'unread' : 'read';

                                    // Format timestamp
                                    $n_ts = !empty($n['timestamp']) ? (int) $n['timestamp'] : (!empty($n['created_at']) ? strtotime($n['created_at']) : time());
                                    $date_formatted = date('M d, Y', $n_ts);
                                    $time_formatted = date('h:i A', $n_ts);

                                    // Resolve Icon, Priority & Action Target based on doctor notification type
                                    $type_class = 'notification-type-system';
                                    $icon = 'fa-bell';
                                    $priority_badge = 'badge-priority-medium';
                                    $priority_text = 'System Alert';
                                    $action_label = 'View Details';
                                    $action_tab = 'doc-overview';

                                    if (strpos($n_type_raw, 'appointment') !== false || strpos($n_type_raw, 'consult') !== false || strpos($n_type_raw, 'visit') !== false) {
                                        $type_class = 'notification-type-appointment';
                                        $icon = 'fa-calendar-check';
                                        $priority_badge = 'badge-priority-high';
                                        $priority_text = 'Appointment';
                                        $action_label = 'View Appointments';
                                        $action_tab = 'doc-appointments';
                                    } elseif (strpos($n_type_raw, 'prescription') !== false || strpos($n_type_raw, 'rx') !== false || strpos($n_type_raw, 'pharmacy') !== false || strpos($n_type_raw, 'med') !== false) {
                                        $type_class = 'notification-type-user';
                                        $icon = 'fa-prescription-bottle-medical';
                                        $priority_badge = 'badge-priority-high';
                                        $priority_text = 'Prescription';
                                        $action_label = 'View Prescriptions';
                                        $action_tab = 'doc-e-prescriptions';
                                    } elseif (strpos($n_type_raw, 'patient') !== false || strpos($n_type_raw, 'user') !== false) {
                                        $type_class = 'notification-type-user';
                                        $icon = 'fa-user-doctor';
                                        $priority_badge = 'badge-priority-medium';
                                        $priority_text = 'Patient File';
                                        $action_label = 'View Patients';
                                        $action_tab = 'doc-patients-all';
                                    } elseif (strpos($n_type_raw, 'review') !== false || strpos($n_type_raw, 'feedback') !== false) {
                                        $type_class = 'notification-type-system';
                                        $icon = 'fa-star';
                                        $priority_badge = 'badge-priority-medium';
                                        $priority_text = 'Patient Review';
                                        $action_label = 'View Reviews';
                                        $action_tab = 'doc-patient-review';
                                    } elseif (strpos($n_type_raw, 'document') !== false || strpos($n_type_raw, 'signed') !== false || strpos($n_type_raw, 'consent') !== false) {
                                        $type_class = 'notification-type-update';
                                        $icon = 'fa-file-signature';
                                        $priority_badge = 'badge-priority-medium';
                                        $priority_text = 'Signed Document';
                                        $action_label = 'Signed Consents';
                                        $action_tab = 'doc-documents-signed';
                                    } elseif (strpos($n_type_raw, 'intake') !== false || strpos($n_type_raw, 'questionnaire') !== false || strpos($n_type_raw, 'medical') !== false) {
                                        $type_class = 'notification-type-update';
                                        $icon = 'fa-clipboard-list';
                                        $priority_badge = 'badge-priority-high';
                                        $priority_text = 'Clinical Intake';
                                        $action_label = 'Patient Records';
                                        $action_tab = 'doc-patients-records';
                                    } elseif (strpos($n_type_raw, 'billing') !== false || strpos($n_type_raw, 'bill') !== false || strpos($n_type_raw, 'invoice') !== false || strpos($n_type_raw, 'account') !== false) {
                                        $type_class = 'notification-type-billing';
                                        $icon = 'fa-receipt';
                                        $priority_badge = 'badge-priority-medium';
                                        $priority_text = 'Billing';
                                        $action_label = 'Billing & Accounts';
                                        $action_tab = 'doc-accounts-bill-list';
                                    } elseif (strpos($n_type_raw, 'security') !== false || strpos($n_type_raw, 'password') !== false || strpos($n_type_raw, 'auth') !== false) {
                                        $type_class = 'notification-type-security';
                                        $icon = 'fa-shield-halved';
                                        $priority_badge = 'badge-priority-critical';
                                        $priority_text = 'Security';
                                        $action_label = 'Security Settings';
                                        $action_tab = 'doc-settings';
                                    }

                                    // Action click logic
                                    $action_url = $n['action_url'] ?? '';
                                    if (!empty($action_url)) {
                                        if (strpos($action_url, '#tab=') !== false) {
                                            $parsed_tab = str_replace('#tab=', '', $action_url);
                                            $action_onclick = "if(typeof diorDocSwitchTab==='function'){diorDocSwitchTab('" . esc_js($parsed_tab) . "');}else{window.location.hash='#tab=" . esc_js($parsed_tab) . "';}";
                                        } elseif (strpos($action_url, 'http') === 0) {
                                            if (strpos($action_url, '#tab=') !== false) {
                                                $parts = explode('#tab=', $action_url);
                                                $parsed_tab = end($parts);
                                                $action_onclick = "if(typeof diorDocSwitchTab==='function'){diorDocSwitchTab('" . esc_js($parsed_tab) . "');}else{window.location.hash='#tab=" . esc_js($parsed_tab) . "';}";
                                            } else {
                                                $action_onclick = "window.open('" . esc_url($action_url) . "', '_blank');";
                                            }
                                        } else {
                                            $action_onclick = "if(typeof diorDocSwitchTab==='function'){diorDocSwitchTab('" . esc_js($action_tab) . "');}else{window.location.hash='#tab=" . esc_js($action_tab) . "';}";
                                        }
                                    } else {
                                        $action_onclick = "if(typeof diorDocSwitchTab==='function'){diorDocSwitchTab('" . esc_js($action_tab) . "');}else{window.location.hash='#tab=" . esc_js($action_tab) . "';}";
                                    }
                                    ?>
                                    <div class="notification-item-row <?php echo $is_unread ? 'is-unread-row' : ''; ?> <?php echo esc_attr($type_class); ?>"
                                        data-read-state="<?php echo esc_attr($read_state); ?>"
                                        data-notif-id="<?php echo esc_attr($n_id); ?>">

                                        <?php if ($is_unread): ?>
                                            <div class="unread-strip"></div>
                                        <?php endif; ?>

                                        <div class="type-icon-wrapper">
                                            <i class="fas <?php echo esc_attr($icon); ?>"></i>
                                        </div>

                                        <div class="notification-body flex-grow-1 min-w-0 me-3"
                                            style="flex: 1 1 auto; min-width: 0;">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1"
                                                style="display: flex; justify-content: space-between; align-items: center;">
                                                <div class="d-flex align-items-center gap-2 flex-wrap"
                                                    style="display: flex; align-items: center; gap: 8px;">
                                                    <h6 class="notification-title mb-0"><?php echo esc_html($n_title); ?></h6>
                                                    <span class="badge-priority <?php echo esc_attr($priority_badge); ?>">
                                                        <span class="status-dot"></span> <?php echo esc_html($priority_text); ?>
                                                    </span>
                                                </div>
                                                <span class="timestamp-tag">
                                                    <i class="far fa-clock me-1"></i>
                                                    <?php echo esc_html($date_formatted . ' ' . $time_formatted); ?>
                                                </span>
                                            </div>

                                            <p class="notification-text mb-2"><?php echo esc_html($n_message); ?></p>

                                            <div class="notification-action-bar">
                                                <a class="btn btn-sm btn-inline-action" href="javascript:void(0)"
                                                    onclick="<?php echo $action_onclick; ?>">
                                                    <i class="fas fa-arrow-up-right-from-square me-1"
                                                        style="margin-right: 4px;"></i> <?php echo esc_html($action_label); ?>
                                                </a>
                                            </div>
                                        </div>

                                        <div class="notification-controls d-flex align-items-center gap-1"
                                            style="display: flex; gap: 4px;">
                                            <?php if ($is_unread): ?>
                                                <button type="button" title="Mark as Read"
                                                    onclick="diorMarkRowAsRead(this, '<?php echo esc_js($n_id); ?>')"
                                                    class="btn btn-sm btn-icon-action">
                                                    <i class="fas fa-check text-success" style="color: #10b981;"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" title="Delete Notification"
                                                onclick="diorDeleteNotificationRow(this, '<?php echo esc_js($n_id); ?>')"
                                                class="btn btn-sm btn-icon-action text-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>

                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-5 text-center text-muted dior-empty-state" id="dior-empty-notif-box">
                                    <i class="fas fa-bell-slash fa-3x mb-3 text-secondary opacity-50"></i>
                                    <h5 class="fw-bold text-dark">No notifications right now</h5>
                                    <p class="text-secondary small mb-0">You're all caught up! New clinical updates,
                                        appointments, and patient messages will appear here.</p>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</section>

<!-- Notification Scripts for Doctor Dashboard -->
<script>
    function diorFilterSystemNotif(btn, filterState) {
        if (!btn || !btn.parentElement) return;
        btn.parentElement.querySelectorAll('.segmented-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const rows = document.querySelectorAll('#dior-system-notif-list .notification-item-row');
        let visibleCount = 0;
        rows.forEach(row => {
            if (filterState === 'all') {
                row.style.display = 'flex';
                visibleCount++;
            } else if (filterState === 'unread') {
                const match = row.dataset.readState === 'unread';
                row.style.display = match ? 'flex' : 'none';
                if (match) visibleCount++;
            } else if (filterState === 'read') {
                const match = row.dataset.readState === 'read';
                row.style.display = match ? 'flex' : 'none';
                if (match) visibleCount++;
            }
        });
    }

    function diorSearchDoctorNotifications() {
        const input = document.getElementById('dior-doc-notif-search');
        const val = input ? input.value.toLowerCase().trim() : '';
        const rows = document.querySelectorAll('#dior-system-notif-list .notification-item-row');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(val) ? 'flex' : 'none';
        });
    }

    function diorUpdateNotifBadgesAndStats() {
        const allRows = document.querySelectorAll('#dior-system-notif-list .notification-item-row');
        const unreadRows = document.querySelectorAll('#dior-system-notif-list .notification-item-row[data-read-state="unread"]');
        const readRows = document.querySelectorAll('#dior-system-notif-list .notification-item-row[data-read-state="read"]');

        const total = allRows.length;
        const unread = unreadRows.length;
        const read = readRows.length;

        const statTotal = document.getElementById('dior-sys-total-stat');
        if (statTotal) statTotal.textContent = total;

        const statUnread = document.getElementById('dior-sys-unread-stat');
        if (statUnread) statUnread.textContent = unread;

        const chip = document.getElementById('dior-sys-unread-chip');
        if (chip) chip.textContent = unread + ' Unread';

        const pillAll = document.getElementById('dior-pill-all');
        if (pillAll) pillAll.textContent = total;

        const pillUnread = document.getElementById('dior-pill-unread');
        if (pillUnread) pillUnread.textContent = unread;

        const pillRead = document.getElementById('dior-pill-read');
        if (pillRead) pillRead.textContent = read;

        // Update doctor topbar badge if present
        const unreadBadges = document.querySelectorAll('.doc-unread-badge, .dior-notif-indicator');
        unreadBadges.forEach(b => {
            if (unread > 0) {
                b.textContent = unread;
                b.style.display = '';
            } else {
                b.style.display = 'none';
            }
        });
    }

    function diorMarkRowAsRead(btn, notifId) {
        const row = btn.closest('.notification-item-row');
        if (row) {
            row.classList.remove('is-unread-row');
            row.dataset.readState = 'read';
            const strip = row.querySelector('.unread-strip');
            if (strip) strip.remove();
            btn.remove();
        }

        diorUpdateNotifBadgesAndStats();

        // Sync with WordPress backend
        const ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
        const nonce = '<?php echo esc_js($doctor_nonce); ?>';

        if (notifId) {
            const body = new URLSearchParams({
                action: 'dior_doctor_mark_notif_read',
                notif_id: notifId,
                nonce: nonce
            });
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).catch(() => { });
        }
    }

    function diorMarkAllSystemNotificationsRead() {
        const unreadRows = document.querySelectorAll('#dior-system-notif-list .is-unread-row');
        unreadRows.forEach(row => {
            row.classList.remove('is-unread-row');
            row.dataset.readState = 'read';
            const strip = row.querySelector('.unread-strip');
            if (strip) strip.remove();
            const checkBtn = row.querySelector('.notification-controls .btn-icon-action .fa-check');
            if (checkBtn) checkBtn.closest('button').remove();
        });

        diorUpdateNotifBadgesAndStats();

        // Sync with WordPress backend
        const ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
        const nonce = '<?php echo esc_js($doctor_nonce); ?>';

        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({
                action: 'dior_doctor_mark_all_notif_read',
                nonce: nonce
            }).toString()
        }).then(res => res.json()).then(data => {
            if (window.Swal) {
                Swal.fire({
                    position: 'top-end',
                    icon: 'success',
                    title: 'All notifications marked as read',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }).catch(() => { });
    }

    function diorDeleteNotificationRow(btn, notifId) {
        const row = btn.closest('.notification-item-row');
        if (!row) return;

        const doDelete = function () {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(20px)';
            setTimeout(() => {
                row.remove();
                diorUpdateNotifBadgesAndStats();
            }, 300);

            // Sync with WordPress backend
            const ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
            const nonce = '<?php echo esc_js($doctor_nonce); ?>';

            if (notifId) {
                const body = new URLSearchParams({
                    action: 'dior_doctor_delete_notification',
                    notif_id: notifId,
                    nonce: nonce
                });

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                }).catch(() => { });
            }
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Delete Notification?',
                text: 'This notification will be permanently removed.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete!'
            }).then(result => {
                if (result.isConfirmed) {
                    doDelete();
                }
            });
        } else {
            doDelete();
        }
    }

    function diorClearReadSystemNotifications() {
        const readRows = document.querySelectorAll('#dior-system-notif-list .notification-item-row[data-read-state="read"]');
        if (readRows.length === 0) {
            if (window.Swal) {
                Swal.fire({ icon: 'info', title: 'No Read Notifications', text: 'There are no read notifications to clear.' });
            }
            return;
        }

        const notifIds = Array.from(readRows).map(r => r.dataset.notifId).filter(Boolean);

        const doClear = function () {
            readRows.forEach(row => {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            });

            setTimeout(() => {
                diorUpdateNotifBadgesAndStats();
            }, 350);

            // Sync with backend
            const ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
            const nonce = '<?php echo esc_js($doctor_nonce); ?>';

            const body = new URLSearchParams({
                action: 'dior_doctor_clear_read_notifications',
                nonce: nonce
            });
            notifIds.forEach(id => body.append('notif_ids[]', id));

            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).then(res => res.json()).then(data => {
                if (window.Swal) {
                    Swal.fire({
                        position: 'top-end',
                        icon: 'success',
                        title: 'Read notifications cleared',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            }).catch(() => { });
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Clear Read Notifications?',
                text: `You are about to remove ${readRows.length} read notification(s).`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, clear read!'
            }).then(result => {
                if (result.isConfirmed) {
                    doClear();
                }
            });
        } else {
            doClear();
        }
    }

    function diorDownloadDoctorNotifCSV() {
        const rows = document.querySelectorAll('#dior-system-notif-list .notification-item-row');
        let csvContent = "data:text/csv;charset=utf-8,Title,Message,Date,Status\n";

        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const title = (row.querySelector('.notification-title')?.textContent || '').replace(/"/g, '""').trim();
                const text = (row.querySelector('.notification-text')?.textContent || '').replace(/"/g, '""').trim();
                const time = (row.querySelector('.timestamp-tag')?.textContent || '').replace(/"/g, '""').trim();
                const status = row.dataset.readState === 'unread' ? 'Unread' : 'Read';
                csvContent += `"${title}","${text}","${time}","${status}"\n`;
            }
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "doctor_notifications.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function diorRefreshLiveDoctorNotifs() {
        const icon = document.querySelector('.btn-outline-action .fa-rotate-right');
        if (icon) icon.classList.add('fa-spin');
        window.location.reload();
    }
</script>