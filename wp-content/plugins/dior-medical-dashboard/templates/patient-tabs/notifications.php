<?php
/**
 * Patient Dashboard — Notifications Center
 * Matches the Doctor Dashboard Notifications System Design
 * Full dynamic data integration from Dior_Patient_Portal_Data & database
 */
$user_id = get_current_user_id();
$patient_notif_rows = Dior_Patient_Portal_Data::get_patient_notifications($user_id);
if (!is_array($patient_notif_rows)) {
    $patient_notif_rows = [];
}

$total_notifs = count($patient_notif_rows);
$unread_count = 0;
$priority_count = 0;

foreach ($patient_notif_rows as $item) {
    if (empty($item['is_read'])) {
        $unread_count++;
    }
    $t = strtolower($item['type'] ?? '');
    $title = strtolower($item['title'] ?? '');
    $msg = strtolower($item['message'] ?? '');
    if (
        in_array($t, ['urgent', 'emergency', 'security', 'alert', 'critical', 'intake'], true) ||
        strpos($title, 'urgent') !== false ||
        strpos($title, 'emergency') !== false ||
        strpos($title, 'alert') !== false ||
        strpos($title, 'reminder') !== false ||
        strpos($msg, 'urgent') !== false ||
        strpos($msg, 'important') !== false
    ) {
        $priority_count++;
    }
}
$read_count = max(0, $total_notifs - $unread_count);
?>
<section class="dior-tab-panel notifications-tab-panel" id="tab-notifications"
    style="display: none; background-color: #f8fafc; padding: 20px; position: relative; overflow: hidden;">

    <!-- Header & Breadcrumb -->
    <div class="mb-4"
        style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; position: relative; z-index: 3;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700; color: #1e293b;">Notifications Center
            </h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list"
                style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="javascript:void(0)"
                        onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('overview');"><i
                            class="fas fa-house" style="font-size: 14px; color: #2563eb;"></i></a></li>
                <li><span style="color: #cbd5e1;">/</span></li>
                <li><a href="javascript:void(0)"
                        onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('overview');"
                        style="color: #64748b; text-decoration: none; font-size: 13px;">Dashboard</a></li>
                <li><span style="color: #cbd5e1;">/</span></li>
                <li class="active"><span style="color: #1e293b; font-weight: 700; font-size: 13px;">Notifications</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="section-body" style="position: relative; z-index: 2;">

        <!-- Stat Cards (4-Column Grid) -->
        <div class="dior-notif-stats-row mb-4">

            <!-- 1. Total Notifications (Indigo) -->
            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-indigo">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2"
                            style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">Total Notifications</span>
                                <h3 class="stat-value text-indigo mt-1 mb-0" id="dior-patient-total-stat">
                                    <?php echo esc_html($total_notifs); ?></h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fas fa-bell"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center"
                            style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-indigo"><i class="fas fa-clock-rotate-left text-xs"></i>
                                Updates Log</span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">Real-time feed</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Unread Items (Amber) -->
            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-amber">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2"
                            style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">Unread Items</span>
                                <h3 class="stat-value text-amber mt-1 mb-0" id="dior-patient-unread-stat">
                                    <?php echo esc_html($unread_count); ?></h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fas fa-envelope-open-text"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center"
                            style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-amber"><i class="fas fa-hourglass-half text-xs"></i> Pending
                                Action</span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">Requires attention</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Important / Care Alerts (Rose) -->
            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-rose">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2"
                            style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">Important Alerts</span>
                                <h3 class="stat-value text-rose mt-1 mb-0" id="dior-patient-priority-stat">
                                    <?php echo esc_html($priority_count); ?></h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fas fa-shield-halved"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center"
                            style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-rose"><i class="fas fa-circle-exclamation text-xs"></i> Care
                                Stream</span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">Visits &amp; Health</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Portal Status (Emerald) -->
            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-emerald">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2"
                            style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">Portal Status</span>
                                <h3 class="stat-value text-emerald mt-1 mb-0">Active</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fas fa-shield-check"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center"
                            style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-emerald"><i class="fas fa-circle-check text-xs"></i> Verified
                                Patient</span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">All services active</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Main Feed Card -->
        <div class="row" style="margin: 0; width: 100%;">
            <div class="col-12" style="width: 100%; padding: 0;">
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
                                    style="color: #2563eb !important; margin-right: 6px;"></i>Notifications Activity
                                Feed
                            </h4>
                            <span class="unread-count-chip"
                                id="dior-patient-unread-chip"><?php echo esc_html($unread_count); ?> Unread</span>
                        </div>
                        <div class="d-flex align-items-center gap-2" style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-sm btn-outline-action"
                                onclick="diorMarkAllPatientNotifsRead()"><i class="fas fa-check-double me-1"
                                    style="margin-right: 4px;"></i> Mark All Read </button>
                            <button type="button" class="btn btn-sm btn-outline-action text-danger"
                                onclick="diorClearReadPatientNotifs()"><svg xmlns="http://www.w3.org/2000/svg"
                                    width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16"
                                    style="background:transparent; margin-right:4px;">
                                    <path
                                        d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z" />
                                    <path
                                        d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z" />
                                </svg> Clear Read </button>
                            <button type="button" class="btn btn-sm btn-outline-action"
                                onclick="diorRefreshPatientNotifs()" title="Refresh Notifications"><i
                                    class="fas fa-rotate-right"></i></button>
                        </div>
                    </div>

                    <!-- Filters Bar -->
                    <div class="filters-bar px-4 py-3 border-top"
                        style="padding: 14px 24px; background: #f8fafc; border-bottom: 1px solid #edf1f5;">
                        <div class="row align-items-center g-3"
                            style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; width: 100%; margin: 0;">
                            <div style="flex: 1 1 300px; min-width: 0;">
                                <div class="input-group input-group-sm dior-bs-search-group">

                                    <input type="text" id="dior-patient-notif-search"
                                        onkeyup="diorSearchPatientNotifications()"
                                        placeholder="Search notifications by title or message..."
                                        class="form-control border-start-0 ps-0">
                                </div>
                            </div>
                            <div style="flex: 0 0 auto;">
                                <div class="segmented-nav">
                                    <button type="button" class="segmented-tab active btn"
                                        onclick="diorFilterPatientNotif(this, 'all')"> All <span class="count-pill"
                                            id="dior-pill-all"><?php echo esc_html($total_notifs); ?></span></button>
                                    <button type="button" class="segmented-tab btn"
                                        onclick="diorFilterPatientNotif(this, 'unread')"> Unread <span
                                            class="count-pill"
                                            id="dior-pill-unread"><?php echo esc_html($unread_count); ?></span></button>
                                    <button type="button" class="segmented-tab btn"
                                        onclick="diorFilterPatientNotif(this, 'read')"> Read <span class="count-pill"
                                            id="dior-pill-read"><?php echo esc_html($read_count); ?></span></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Feed List -->
                    <div class="card-body p-0">
                        <div class="notifications-list-wrapper" id="dior-patient-notif-list">
                            <?php if (!empty($patient_notif_rows)): ?>
                                <?php
                                foreach ($patient_notif_rows as $n):
                                    $n_id = $n['id'] ?? '';
                                    $n_title = !empty($n['title']) ? $n['title'] : 'Notification';
                                    $n_msg = !empty($n['message']) ? $n['message'] : '';
                                    $n_is_read = !empty($n['is_read']);
                                    $read_state = $n_is_read ? 'read' : 'unread';

                                    // Date & Time formatting
                                    $n_date = '';
                                    if (!empty($n['created_at']) && $n['created_at'] !== '0000-00-00 00:00:00') {
                                        $ts = strtotime($n['created_at']);
                                        if ($ts)
                                            $n_date = wp_date('M j, Y', $ts);
                                    } elseif (!empty($n['timestamp'])) {
                                        $n_date = wp_date('M j, Y', (int) $n['timestamp']);
                                    }
                                    if (!$n_date) {
                                        $n_date = current_time('M j, Y');
                                    }
                                    $n_time = function_exists('dior_format_notification_time') ? dior_format_notification_time($n) : '';
                                    $full_timestamp = trim($n_date . ' ' . $n_time);

                                    // Contextual type & icon detection
                                    $t = strtolower($n['type'] ?? '');
                                    $title_l = strtolower($n_title);
                                    $msg_l = strtolower($n_msg);

                                    $type_class = 'notification-type-system';
                                    $type_icon = 'fa-bell';
                                    $priority_class = 'badge-priority-low';
                                    $priority_label = 'Standard';
                                    $action_tab = '';
                                    $action_text = '';

                                    if (strpos($title_l, 'appointment') !== false || strpos($title_l, 'booking') !== false || strpos($title_l, 'visit') !== false || strpos($msg_l, 'appointment') !== false || $t === 'appointment') {
                                        $type_class = 'notification-type-appointment';
                                        $type_icon = 'fa-calendar-check';
                                        $priority_class = 'badge-priority-medium';
                                        $priority_label = 'Appointment';
                                        $action_tab = 'appointments';
                                        $action_text = 'View Appointments';
                                    } elseif (strpos($title_l, 'intake') !== false || strpos($title_l, 'medical profile') !== false || $t === 'intake' || strpos($title_l, 'questionnaire') !== false) {
                                        $type_class = 'notification-type-user';
                                        $type_icon = 'fa-clipboard-check';
                                        $priority_class = 'badge-priority-high';
                                        $priority_label = 'Intake';
                                        $action_tab = 'questionnaire';
                                        $action_text = 'View Intake';
                                    } elseif (strpos($title_l, 'prescription') !== false || strpos($title_l, 'medication') !== false || strpos($title_l, 'pharmacy') !== false || strpos($msg_l, 'prescription') !== false || $t === 'rx' || $t === 'medication') {
                                        $type_class = 'notification-type-user';
                                        $type_icon = 'fa-prescription';
                                        $priority_class = 'badge-priority-medium';
                                        $priority_label = 'Prescription';
                                        $action_tab = 'docs_meds';
                                        $action_text = 'View Medications';
                                    } elseif (strpos($title_l, 'invoice') !== false || strpos($title_l, 'payment') !== false || strpos($title_l, 'bill') !== false || strpos($msg_l, 'invoice') !== false || $t === 'billing' || $t === 'payment') {
                                        $type_class = 'notification-type-billing';
                                        $type_icon = 'fa-receipt';
                                        $priority_class = 'badge-priority-medium';
                                        $priority_label = 'Billing';
                                        $action_tab = 'payments';
                                        $action_text = 'View Invoices';
                                    } elseif (strpos($title_l, 'document') !== false || strpos($title_l, 'record') !== false || strpos($title_l, 'file') !== false || $t === 'document') {
                                        $type_class = 'notification-type-update';
                                        $type_icon = 'fa-file-medical';
                                        $priority_class = 'badge-priority-low';
                                        $priority_label = 'Records';
                                        $action_tab = 'documents';
                                        $action_text = 'View Documents';
                                    } elseif (strpos($title_l, 'security') !== false || strpos($title_l, 'password') !== false || strpos($title_l, 'login') !== false || strpos($title_l, 'urgent') !== false || strpos($title_l, 'emergency') !== false) {
                                        $type_class = 'notification-type-security';
                                        $type_icon = 'fa-shield-halved';
                                        $priority_class = 'badge-priority-high';
                                        $priority_label = 'Important';
                                        $action_tab = 'emergency';
                                        $action_text = 'Review Notice';
                                    } else {
                                        $type_class = 'notification-type-system';
                                        $type_icon = !empty($n['icon']) && strpos($n['icon'], 'fa-') !== false ? $n['icon'] : 'fa-bell';
                                        $priority_class = $n_is_read ? 'badge-priority-low' : 'badge-priority-medium';
                                        $priority_label = $n_is_read ? 'Update' : 'New';
                                        if (!empty($n['action_url'])) {
                                            $action_tab = str_replace('#tab=', '', $n['action_url']);
                                            $action_text = 'Open Tab';
                                        }
                                    }
                                    ?>
                                    <div class="notification-item-row <?php echo !$n_is_read ? 'is-unread-row' : ''; ?> <?php echo esc_attr($type_class); ?>"
                                        data-read-state="<?php echo esc_attr($read_state); ?>"
                                        data-notif-id="<?php echo esc_attr($n_id); ?>">
                                        <?php if (!$n_is_read): ?>
                                            <div class="unread-strip"></div>
                                        <?php endif; ?>
                                        <div class="type-icon-wrapper"><i class="fas <?php echo esc_attr($type_icon); ?>"></i>
                                        </div>
                                        <div class="notification-body flex-grow-1 min-w-0 me-3"
                                            style="flex: 1 1 auto; min-width: 0;">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1"
                                                style="display: flex; justify-content: space-between; align-items: center;">
                                                <div class="d-flex align-items-center gap-2 flex-wrap"
                                                    style="display: flex; align-items: center; gap: 8px;">
                                                    <h6 class="notification-title mb-0"><?php echo esc_html($n_title); ?></h6>
                                                    <span class="badge-priority <?php echo esc_attr($priority_class); ?>"><span
                                                            class="status-dot"></span>
                                                        <?php echo esc_html($priority_label); ?></span>
                                                </div>
                                                <span class="timestamp-tag"><i class="far fa-clock me-1"></i>
                                                    <?php echo esc_html($full_timestamp); ?></span>
                                            </div>
                                            <p class="notification-text mb-2"><?php echo esc_html($n_msg); ?></p>
                                            <?php if (!empty($action_tab) && !empty($action_text)): ?>
                                                <div class="notification-action-bar">
                                                    <a class="btn btn-sm btn-inline-action" href="javascript:void(0)"
                                                        onclick="if(typeof diorSwitchTab==='function'){ diorSwitchTab('<?php echo esc_js($action_tab); ?>'); }"><i
                                                            class="fas fa-arrow-up-right-from-square me-1"
                                                            style="margin-right: 4px;"></i>
                                                        <?php echo esc_html($action_text); ?></a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-controls d-flex align-items-center gap-1"
                                            style="display: flex; gap: 4px;">
                                            <?php if (!$n_is_read): ?>
                                                <button type="button" title="Mark as Read"
                                                    onclick="diorMarkPatientRowAsRead(this, '<?php echo esc_js($n_id); ?>')"
                                                    class="btn btn-sm btn-icon-action"><i class="fas fa-check text-success"
                                                        style="color: #10b981;"></i></button>
                                            <?php endif; ?>
                                            <button type="button" title="Delete Notification"
                                                onclick="diorDeletePatientRow(this, '<?php echo esc_js($n_id); ?>')"
                                                class="btn btn-sm btn-icon-action text-danger"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444"
                                                    class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;">
                                                    <path
                                                        d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z" />
                                                    <path
                                                        d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z" />
                                                </svg></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Empty State -->
                            <div id="dior-patient-notif-empty" class="dior-empty-notifications"
                                style="<?php echo empty($patient_notif_rows) ? 'display:flex;' : 'display:none;'; ?> flex-direction:column; align-items:center; justify-content:center; padding:60px 20px; text-align:center;">
                                <div
                                    style="width:64px; height:64px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:26px; margin-bottom:16px;">
                                    <i class="far fa-bell-slash"></i>
                                </div>
                                <h5 style="font-size:16px; font-weight:700; color:#1e293b; margin-bottom:6px;">No
                                    Notifications Found</h5>
                                <p
                                    style="font-size:13.5px; color:#64748b; max-width:320px; margin:0 auto; line-height:1.5;">
                                    You are all caught up! New updates, appointments, and care alerts will appear here.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Custom Scoped Responsive CSS -->
<style>
    #tab-notifications .dior-notif-stats-row {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        width: 100%;
    }

    #tab-notifications .dior-notif-stat-col {
        flex: 1 1 calc(25% - 12px);
        min-width: 220px;
    }

    @media (max-width: 992px) {
        #tab-notifications .dior-notif-stat-col {
            flex: 1 1 calc(50% - 12px);
        }
    }

    @media (max-width: 576px) {
        #tab-notifications .dior-notif-stat-col {
            flex: 1 1 100%;
        }
    }

    #tab-notifications .dior-page-watermark,
    #tab-notifications .page-watermark,
    #tab-notifications .dior-big-title {
        display: none !important;
    }

    #tab-notifications .notification-item-row {
        transition: background-color 0.15s ease-in-out;
    }
</style>

<!-- Client JavaScript Logic for Filters, Search, Read, Delete & Real-Time Sync -->
<script>
    window.diorFilterPatientNotif = function (btn, filterState) {
        if (!btn) return;
        btn.parentElement.querySelectorAll('.segmented-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const rows = document.querySelectorAll('#dior-patient-notif-list .notification-item-row');
        let visibleCount = 0;
        rows.forEach(row => {
            if (filterState === 'all') {
                row.style.display = 'flex';
                visibleCount++;
            } else if (filterState === 'unread') {
                const isMatch = row.getAttribute('data-read-state') === 'unread';
                row.style.display = isMatch ? 'flex' : 'none';
                if (isMatch) visibleCount++;
            } else if (filterState === 'read') {
                const isMatch = row.getAttribute('data-read-state') === 'read';
                row.style.display = isMatch ? 'flex' : 'none';
                if (isMatch) visibleCount++;
            }
        });

        const emptyBox = document.getElementById('dior-patient-notif-empty');
        if (emptyBox) {
            emptyBox.style.display = (visibleCount === 0) ? 'flex' : 'none';
        }
    };

    window.diorSearchPatientNotifications = function () {
        const input = document.getElementById('dior-patient-notif-search');
        const val = input ? input.value.toLowerCase().trim() : '';
        const rows = document.querySelectorAll('#dior-patient-notif-list .notification-item-row');
        let visibleCount = 0;
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const matches = !val || text.includes(val);
            row.style.display = matches ? 'flex' : 'none';
            if (matches) visibleCount++;
        });

        const emptyBox = document.getElementById('dior-patient-notif-empty');
        if (emptyBox) {
            emptyBox.style.display = (visibleCount === 0) ? 'flex' : 'none';
        }
    };

    window.diorMarkPatientRowAsRead = function (btn, notifId) {
        const row = btn ? btn.closest('.notification-item-row') : document.querySelector(`#dior-patient-notif-list .notification-item-row[data-notif-id="${notifId}"]`);
        if (!row) return;

        // AJAX Call
        const ajaxUrl = (window.dior_patient_dashboard && window.dior_patient_dashboard.ajax_url) || (window.dior_vars && window.dior_vars.ajax_url) || '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
        const nonce = (window.dior_patient_dashboard && window.dior_patient_dashboard.portal_nonce) || (window.dior_vars && window.dior_vars.nonce) || '<?php echo esc_js(wp_create_nonce("dior_portal_nonce")); ?>';

        const body = new URLSearchParams({
            action: 'dior_patient_mark_notification_read',
            nonce: nonce,
            notif_id: notifId
        });

        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(r => r.json()).then(res => {
            // Update DOM
            row.classList.remove('is-unread-row');
            row.setAttribute('data-read-state', 'read');
            const strip = row.querySelector('.unread-strip');
            if (strip) strip.remove();
            if (btn) btn.remove();

            // Update Counter stats
            const unreadStat = document.getElementById('dior-patient-unread-stat');
            const unreadChip = document.getElementById('dior-patient-unread-chip');
            const pillUnread = document.getElementById('dior-pill-unread');
            const pillRead = document.getElementById('dior-pill-read');

            let curUnread = unreadStat ? Math.max(0, parseInt(unreadStat.textContent, 10) - 1) : 0;
            if (unreadStat) unreadStat.textContent = String(curUnread);
            if (unreadChip) unreadChip.textContent = curUnread + ' Unread';
            if (pillUnread) pillUnread.textContent = String(curUnread);

            if (pillRead) {
                let curRead = parseInt(pillRead.textContent, 10) + 1;
                pillRead.textContent = String(curRead);
            }

            // Update Topbar Bell badge
            const bellBadges = document.querySelectorAll('.unread-badge-count, .dior-notif-indicator');
            bellBadges.forEach(b => {
                if (curUnread <= 0) {
                    b.style.display = 'none';
                } else {
                    b.textContent = String(curUnread);
                }
            });
        }).catch(err => {
            console.warn('Error marking notification read:', err);
        });
    };

    window.diorMarkAllPatientNotifsRead = function () {
        const unreadRows = document.querySelectorAll('#dior-patient-notif-list .is-unread-row');
        if (unreadRows.length === 0) {
            if (window.Swal) {
                Swal.fire({ icon: 'info', title: 'All Read', text: 'All notifications are already marked as read.' });
            }
            return;
        }

        const ajaxUrl = (window.dior_patient_dashboard && window.dior_patient_dashboard.ajax_url) || (window.dior_vars && window.dior_vars.ajax_url) || '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
        const nonce = (window.dior_patient_dashboard && window.dior_patient_dashboard.portal_nonce) || (window.dior_vars && window.dior_vars.nonce) || '<?php echo esc_js(wp_create_nonce("dior_portal_nonce")); ?>';

        const body = new URLSearchParams({
            action: 'dior_patient_mark_all_notifications_read',
            nonce: nonce
        });

        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(r => r.json()).then(res => {
            unreadRows.forEach(row => {
                row.classList.remove('is-unread-row');
                row.setAttribute('data-read-state', 'read');
                const strip = row.querySelector('.unread-strip');
                if (strip) strip.remove();
                const checkBtn = row.querySelector('.notification-controls .btn-icon-action .fa-check');
                if (checkBtn) checkBtn.closest('button').remove();
            });

            const totalStat = document.getElementById('dior-patient-total-stat');
            const unreadStat = document.getElementById('dior-patient-unread-stat');
            const unreadChip = document.getElementById('dior-patient-unread-chip');
            const pillAll = document.getElementById('dior-pill-all');
            const pillUnread = document.getElementById('dior-pill-unread');
            const pillRead = document.getElementById('dior-pill-read');

            if (unreadStat) unreadStat.textContent = '0';
            if (unreadChip) unreadChip.textContent = '0 Unread';
            if (pillUnread) pillUnread.textContent = '0';
            if (pillRead && pillAll) pillRead.textContent = pillAll.textContent;

            const bellBadges = document.querySelectorAll('.unread-badge-count, .dior-notif-indicator');
            bellBadges.forEach(b => b.style.display = 'none');

            if (window.Swal) {
                Swal.fire({ position: 'top-end', icon: 'success', title: 'All marked as read', showConfirmButton: false, timer: 1500 });
            }
        }).catch(err => {
            console.warn('Error marking all notifications read:', err);
        });
    };

    window.diorDeletePatientRow = function (btn, notifId) {
        const row = btn ? btn.closest('.notification-item-row') : document.querySelector(`#dior-patient-notif-list .notification-item-row[data-notif-id="${notifId}"]`);
        if (!row) return;

        const executeDelete = () => {
            const ajaxUrl = (window.dior_patient_dashboard && window.dior_patient_dashboard.ajax_url) || (window.dior_vars && window.dior_vars.ajax_url) || '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
            const nonce = (window.dior_patient_dashboard && window.dior_patient_dashboard.portal_nonce) || (window.dior_vars && window.dior_vars.nonce) || '<?php echo esc_js(wp_create_nonce("dior_portal_nonce")); ?>';

            const body = new URLSearchParams({
                action: 'dior_patient_bulk_delete_notifications',
                nonce: nonce
            });
            body.append('notif_ids[]', notifId);

            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).then(r => r.json()).then(res => {
                const wasUnread = row.getAttribute('data-read-state') === 'unread';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                row.style.transition = 'all 0.25s ease';
                setTimeout(() => {
                    row.remove();

                    // Update counters
                    const totalStat = document.getElementById('dior-patient-total-stat');
                    const unreadStat = document.getElementById('dior-patient-unread-stat');
                    const unreadChip = document.getElementById('dior-patient-unread-chip');
                    const pillAll = document.getElementById('dior-pill-all');
                    const pillUnread = document.getElementById('dior-pill-unread');
                    const pillRead = document.getElementById('dior-pill-read');

                    if (totalStat) {
                        let curTot = Math.max(0, parseInt(totalStat.textContent, 10) - 1);
                        totalStat.textContent = String(curTot);
                        if (pillAll) pillAll.textContent = String(curTot);
                    }
                    if (wasUnread) {
                        if (unreadStat) {
                            let curUn = Math.max(0, parseInt(unreadStat.textContent, 10) - 1);
                            unreadStat.textContent = String(curUn);
                            if (unreadChip) unreadChip.textContent = curUn + ' Unread';
                            if (pillUnread) pillUnread.textContent = String(curUn);
                            const bellBadges = document.querySelectorAll('.unread-badge-count, .dior-notif-indicator');
                            bellBadges.forEach(b => {
                                if (curUn <= 0) b.style.display = 'none';
                                else b.textContent = String(curUn);
                            });
                        }
                    } else {
                        if (pillRead) {
                            let curRd = Math.max(0, parseInt(pillRead.textContent, 10) - 1);
                            pillRead.textContent = String(curRd);
                        }
                    }

                    // Check remaining
                    const remaining = document.querySelectorAll('#dior-patient-notif-list .notification-item-row');
                    if (remaining.length === 0) {
                        const emptyBox = document.getElementById('dior-patient-notif-empty');
                        if (emptyBox) emptyBox.style.display = 'flex';
                    }
                }, 250);

                if (window.Swal) {
                    Swal.fire({ position: 'top-end', icon: 'success', title: 'Notification deleted', showConfirmButton: false, timer: 1200 });
                }
            }).catch(err => {
                console.warn('Error deleting notification:', err);
            });
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Delete Notification?',
                text: 'Are you sure you want to delete this notification?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) {
                    executeDelete();
                }
            });
        } else {
            if (confirm('Are you sure you want to delete this notification?')) {
                executeDelete();
            }
        }
    };

    window.diorClearReadPatientNotifs = function () {
        const readRows = document.querySelectorAll('#dior-patient-notif-list .notification-item-row[data-read-state="read"]');
        if (readRows.length === 0) {
            if (window.Swal) {
                Swal.fire({ icon: 'info', title: 'No Read Notifications', text: 'There are no read notifications to clear.' });
            }
            return;
        }

        const ids = Array.from(readRows).map(r => r.getAttribute('data-notif-id')).filter(Boolean);

        const executeClear = () => {
            const ajaxUrl = (window.dior_patient_dashboard && window.dior_patient_dashboard.ajax_url) || (window.dior_vars && window.dior_vars.ajax_url) || '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
            const nonce = (window.dior_patient_dashboard && window.dior_patient_dashboard.portal_nonce) || (window.dior_vars && window.dior_vars.nonce) || '<?php echo esc_js(wp_create_nonce("dior_portal_nonce")); ?>';

            const body = new URLSearchParams({
                action: 'dior_patient_bulk_delete_notifications',
                nonce: nonce
            });
            ids.forEach(id => body.append('notif_ids[]', id));

            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).then(r => r.json()).then(res => {
                readRows.forEach(r => r.remove());

                const totalStat = document.getElementById('dior-patient-total-stat');
                const pillAll = document.getElementById('dior-pill-all');
                const pillRead = document.getElementById('dior-pill-read');

                const remaining = document.querySelectorAll('#dior-patient-notif-list .notification-item-row');
                if (totalStat) totalStat.textContent = String(remaining.length);
                if (pillAll) pillAll.textContent = String(remaining.length);
                if (pillRead) pillRead.textContent = '0';

                if (remaining.length === 0) {
                    const emptyBox = document.getElementById('dior-patient-notif-empty');
                    if (emptyBox) emptyBox.style.display = 'flex';
                }

                if (window.Swal) {
                    Swal.fire({ position: 'top-end', icon: 'success', title: 'Read notifications cleared', showConfirmButton: false, timer: 1500 });
                }
            }).catch(err => {
                console.warn('Error clearing read notifications:', err);
            });
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Clear Read Notifications?',
                text: `Are you sure you want to clear all ${readRows.length} read notifications?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, clear',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) {
                    executeClear();
                }
            });
        } else {
            if (confirm(`Are you sure you want to clear ${readRows.length} read notifications?`)) {
                executeClear();
            }
        }
    };

    window.diorRefreshPatientNotifs = function () {
        location.reload();
    };

    // Backwards compatibility aliases
    window.diorMarkAllNotificationsRead = window.diorMarkAllPatientNotifsRead;
    window.diorBulkDeleteNotifications = window.diorClearReadPatientNotifs;
    window.diorRefreshNotifications = window.diorRefreshPatientNotifs;
</script>