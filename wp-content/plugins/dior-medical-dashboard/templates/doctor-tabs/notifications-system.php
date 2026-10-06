<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/notifications.css?v=' . time()); ?>">

<section class="dior-tab-panel notifications-tab-panel" id="tab-doc-notifications-system" style="display: none; background-color: #f8fafc; padding: 20px; position: relative; overflow: hidden;">
    
    <!-- Header & Breadcrumb -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; position: relative; z-index: 3;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700; color: #1e293b;">System Notifications</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="javascript:void(0)"><i class="fa-solid fa-house" style="font-size: 14px; color: #2563eb;"></i></a></li>
                <li><span style="color: #cbd5e1;">/</span></li>
                <li><a href="javascript:void(0)" style="color: #64748b; text-decoration: none; font-size: 13px;">Notifications</a></li>
                <li><span style="color: #cbd5e1;">/</span></li>
                <li class="active"><span style="color: #1e293b; font-weight: 700; font-size: 13px;">System Notifications</span></li>
            </ul>
        </div>
    </div>
    
    <div class="section-body" style="position: relative; z-index: 2;">
        
        <!-- Stat Cards (4-Column Grid) -->
        <div class="dior-notif-stats-row mb-4">
            
            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-indigo">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2" style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">Total Notifications</span>
                                <h3 class="stat-value text-indigo mt-1 mb-0">8</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-bell"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center" style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-indigo"><i class="fa-solid fa-clock-rotate-left text-xs"></i> System Audit </span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">Real-time log</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-amber">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2" style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">Unread Items</span>
                                <h3 class="stat-value text-amber mt-1 mb-0" id="dior-sys-unread-stat">4</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-envelope-open-text"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center" style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-amber"><i class="fa-solid fa-hourglass-half text-xs"></i> Pending Action </span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">Requires review</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-rose">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2" style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">High Priority Alerts</span>
                                <h3 class="stat-value text-rose mt-1 mb-0">2</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-shield-halved"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center" style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-rose"><i class="fa-solid fa-circle-exclamation text-xs"></i> Critical Stream </span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">Security &amp; system</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dior-notif-stat-col">
                <div class="stat-card stat-card-emerald">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2" style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span class="stat-label">System Health</span>
                                <h3 class="stat-value text-emerald mt-1 mb-0">99.8%</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-shield-check"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center" style="display: flex; align-items: center; margin-top: auto; padding-top: 12px;">
                            <span class="stat-badge badge-emerald"><i class="fa-solid fa-circle-check text-xs"></i> Nominal State </span>
                            <span class="stat-subtext ms-2" style="margin-left: 8px;">All services online</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Main Feed Card -->
        <div class="row">
            <div class="col-12" style="width: 100%;">
                <div class="card main-feed-card" style="border-radius: 14px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05); overflow: hidden;">
                    
                    <div class="card-header border-0 pb-0 pt-3 px-4" style="padding: 20px 24px 14px 24px; border-bottom: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
                        <div class="d-flex align-items-center gap-2" style="display: flex; align-items: center; gap: 10px;">
                            <h4 class="card-title-text mb-0" style="font-size: 16px; font-weight: 700; color: #1e293b; margin: 0;">
                                <i class="fa-solid fa-bell me-2 text-primary" style="color: #2563eb !important; margin-right: 6px;"></i>System Notifications Activity Feed
                            </h4>
                            <span class="unread-count-chip" id="dior-sys-unread-chip">4 Unread</span>
                        </div>
                        <div class="d-flex align-items-center gap-2" style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-sm btn-outline-action" onclick="diorMarkAllSystemNotificationsRead()"><i class="fa-solid fa-check-double me-1" style="margin-right: 4px;"></i> Mark All Read </button>
                            <button type="button" class="btn btn-sm btn-outline-action text-danger" onclick="diorClearReadSystemNotifications()"><i class="fa-solid fa-trash me-1" style="margin-right: 4px; color: #ef4444;"></i> Clear Read </button>
                        </div>
                    </div>
                    
                    <div class="filters-bar px-4 py-3 border-top" style="padding: 14px 24px; background: #f8fafc; border-bottom: 1px solid #edf1f5;">
                        <div class="row align-items-center g-3" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; width: 100%; margin: 0;">
                            <div style="flex: 1 1 300px; min-width: 0;">
                                <div class="search-input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" id="dior-sys-notif-search" onkeyup="diorSearchSystemNotifications()" placeholder="Search notifications by title or message..." class="form-control">
                                </div>
                            </div>
                            <div style="flex: 0 0 auto;">
                                <div class="segmented-nav">
                                    <button type="button" class="segmented-tab active" onclick="diorFilterSystemNotif(this, 'all')"> All <span class="count-pill">8</span></button>
                                    <button type="button" class="segmented-tab" onclick="diorFilterSystemNotif(this, 'unread')"> Unread <span class="count-pill">4</span></button>
                                    <button type="button" class="segmented-tab" onclick="diorFilterSystemNotif(this, 'read')"> Read <span class="count-pill">4</span></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="notifications-list-wrapper" id="dior-system-notif-list">
                            
                            <div class="notification-item-row is-unread-row notification-type-user" data-read-state="unread">
                                <div class="unread-strip"></div>
                                <div class="type-icon-wrapper"><i class="fa-solid fa-user-plus"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3" style="flex: 1 1 auto; min-width: 0;">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1" style="display: flex; justify-content: space-between; align-items: center;">
                                        <div class="d-flex align-items-center gap-2 flex-wrap" style="display: flex; align-items: center; gap: 8px;">
                                            <h6 class="notification-title mb-0">New Patient Registration</h6>
                                            <span class="badge-priority badge-priority-medium"><span class="status-dot"></span> Medium </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 26, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">John Doe has completed online registration and is awaiting medical profile approval.</p>
                                    <div class="notification-action-bar">
                                        <a class="btn btn-sm btn-inline-action" href="javascript:void(0)"><i class="fa-solid fa-arrow-up-right-from-square me-1" style="margin-right: 4px;"></i> View Patient </a>
                                    </div>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1" style="display: flex; gap: 4px;">
                                    <button type="button" title="Mark as Read" onclick="diorMarkRowAsRead(this)" class="btn btn-sm btn-icon-action"><i class="fa-solid fa-check text-success" style="color: #10b981;"></i></button>
                                    <button type="button" title="Delete Notification" onclick="this.closest('.notification-item-row').remove()" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash" style="color: #ef4444;"></i></button>
                                </div>
                            </div>
                            
                            <div class="notification-item-row is-unread-row notification-type-security" data-read-state="unread">
                                <div class="unread-strip"></div>
                                <div class="type-icon-wrapper"><i class="fa-solid fa-shield-halved"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3" style="flex: 1 1 auto; min-width: 0;">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1" style="display: flex; justify-content: space-between; align-items: center;">
                                        <div class="d-flex align-items-center gap-2 flex-wrap" style="display: flex; align-items: center; gap: 8px;">
                                            <h6 class="notification-title mb-0">Security Alert: Failed Login Attempts</h6>
                                            <span class="badge-priority badge-priority-high"><span class="status-dot"></span> High </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 26, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">Multiple failed authentication attempts detected from IP address 192.168.1.100.</p>
                                    <div class="notification-action-bar">
                                        <a class="btn btn-sm btn-inline-action" href="javascript:void(0)"><i class="fa-solid fa-arrow-up-right-from-square me-1" style="margin-right: 4px;"></i> Review Incident </a>
                                    </div>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1" style="display: flex; gap: 4px;">
                                    <button type="button" title="Mark as Read" onclick="diorMarkRowAsRead(this)" class="btn btn-sm btn-icon-action"><i class="fa-solid fa-check text-success" style="color: #10b981;"></i></button>
                                    <button type="button" title="Delete Notification" onclick="this.closest('.notification-item-row').remove()" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash" style="color: #ef4444;"></i></button>
                                </div>
                            </div>
                            
                            <div class="notification-item-row notification-type-appointment" data-read-state="read">
                                <div class="type-icon-wrapper"><i class="fa-solid fa-calendar-check"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3" style="flex: 1 1 auto; min-width: 0;">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1" style="display: flex; justify-content: space-between; align-items: center;">
                                        <div class="d-flex align-items-center gap-2 flex-wrap" style="display: flex; align-items: center; gap: 8px;">
                                            <h6 class="notification-title mb-0">Daily Appointment Schedule Summary</h6>
                                            <span class="badge-priority badge-priority-medium"><span class="status-dot"></span> Medium </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 26, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">15 patient appointments scheduled for today. 3 patients have not confirmed attendance.</p>
                                    <div class="notification-action-bar">
                                        <a class="btn btn-sm btn-inline-action" href="javascript:void(0)"><i class="fa-solid fa-arrow-up-right-from-square me-1" style="margin-right: 4px;"></i> View Appointments </a>
                                    </div>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1" style="display: flex; gap: 4px;">
                                    <button type="button" title="Delete Notification" onclick="this.closest('.notification-item-row').remove()" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash" style="color: #ef4444;"></i></button>
                                </div>
                            </div>
                            
                            <div class="notification-item-row is-unread-row notification-type-system" data-read-state="unread">
                                <div class="unread-strip"></div>
                                <div class="type-icon-wrapper"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3" style="flex: 1 1 auto; min-width: 0;">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1" style="display: flex; justify-content: space-between; align-items: center;">
                                        <div class="d-flex align-items-center gap-2 flex-wrap" style="display: flex; align-items: center; gap: 8px;">
                                            <h6 class="notification-title mb-0">Low Inventory Alert</h6>
                                            <span class="badge-priority badge-priority-high"><span class="status-dot"></span> High </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 26, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">Surgical mask reserves running low (10 units remaining in Central Pharmacy).</p>
                                    <div class="notification-action-bar">
                                        <a class="btn btn-sm btn-inline-action" href="javascript:void(0)"><i class="fa-solid fa-arrow-up-right-from-square me-1" style="margin-right: 4px;"></i> Reorder Supplies </a>
                                    </div>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1" style="display: flex; gap: 4px;">
                                    <button type="button" title="Mark as Read" onclick="diorMarkRowAsRead(this)" class="btn btn-sm btn-icon-action"><i class="fa-solid fa-check text-success" style="color: #10b981;"></i></button>
                                    <button type="button" title="Delete Notification" onclick="this.closest('.notification-item-row').remove()" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash" style="color: #ef4444;"></i></button>
                                </div>
                            </div>
                            
                            <div class="notification-item-row is-unread-row notification-type-system" data-read-state="unread">
                                <div class="unread-strip"></div>
                                <div class="type-icon-wrapper"><i class="fa-solid fa-database"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3" style="flex: 1 1 auto; min-width: 0;">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1" style="display: flex; justify-content: space-between; align-items: center;">
                                        <div class="d-flex align-items-center gap-2 flex-wrap" style="display: flex; align-items: center; gap: 8px;">
                                            <h6 class="notification-title mb-0">System Backup Completed</h6>
                                            <span class="badge-priority badge-priority-low"><span class="status-dot"></span> Low </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 26, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">Automated daily database and EMR backup completed successfully at 2:00 AM without errors.</p>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1" style="display: flex; gap: 4px;">
                                    <button type="button" title="Mark as Read" onclick="diorMarkRowAsRead(this)" class="btn btn-sm btn-icon-action"><i class="fa-solid fa-check text-success" style="color: #10b981;"></i></button>
                                    <button type="button" title="Delete Notification" onclick="this.closest('.notification-item-row').remove()" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash" style="color: #ef4444;"></i></button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Custom Scoped CSS -->
<style>
.dior-notif-stats-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    width: 100%;
}
.dior-notif-stat-col {
    flex: 1 1 calc(25% - 12px);
    min-width: 220px;
}
@media (max-width: 992px) {
    .dior-notif-stat-col {
        flex: 1 1 calc(50% - 12px);
    }
}
@media (max-width: 576px) {
    .dior-notif-stat-col {
        flex: 1 1 100%;
    }
}
.notifications-tab-panel .dior-page-watermark,
.notifications-tab-panel .page-watermark,
.notifications-tab-panel .dior-big-title {
    display: none !important;
}
</style>

<script>
function diorFilterSystemNotif(btn, filterState) {
    btn.parentElement.querySelectorAll('.segmented-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const rows = document.querySelectorAll('#dior-system-notif-list .notification-item-row');
    rows.forEach(row => {
        if (filterState === 'all') {
            row.style.display = 'flex';
        } else if (filterState === 'unread') {
            row.style.display = row.dataset.readState === 'unread' ? 'flex' : 'none';
        } else if (filterState === 'read') {
            row.style.display = row.dataset.readState === 'read' ? 'flex' : 'none';
        }
    });
}

function diorSearchSystemNotifications() {
    const val = document.getElementById('dior-sys-notif-search').value.toLowerCase();
    const rows = document.querySelectorAll('#dior-system-notif-list .notification-item-row');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(val) ? 'flex' : 'none';
    });
}

function diorMarkRowAsRead(btn) {
    const row = btn.closest('.notification-item-row');
    if (row) {
        row.classList.remove('is-unread-row');
        row.dataset.readState = 'read';
        const strip = row.querySelector('.unread-strip');
        if (strip) strip.remove();
        btn.remove();
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
    const chip = document.getElementById('dior-sys-unread-chip');
    if (chip) chip.textContent = '0 Unread';
    const stat = document.getElementById('dior-sys-unread-stat');
    if (stat) stat.textContent = '0';
}

function diorClearReadSystemNotifications() {
    const readRows = document.querySelectorAll('#dior-system-notif-list .notification-item-row[data-read-state="read"]');
    readRows.forEach(row => row.remove());
}
</script>
