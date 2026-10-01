<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/notifications.css?v=' . time()); ?>">

<section class="dior-tab-panel notifications-tab-panel" id="tab-doc-notifications-system" style="display: none;">
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">System Notifications</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)" style="color: #64748B; text-decoration: none; font-size: 14px;">Notifications</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">System Notifications</span></li>
            </ul>
        </div>
    </div>
    
    <div class="section-body">
        <div class="row mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-3 mb-xl-0">
                <div class="stat-card stat-card-indigo">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="stat-label">Total Notifications</span>
                                <h3 class="stat-value text-indigo mt-1 mb-0">8</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-bell"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center">
                            <span class="stat-badge badge-indigo"><i class="fa-solid fa-clock-rotate-left text-xs"></i> System Audit </span>
                            <span class="stat-subtext ms-2">Real-time log</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-3 mb-xl-0">
                <div class="stat-card stat-card-amber">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="stat-label">Unread Items</span>
                                <h3 class="stat-value text-amber mt-1 mb-0">4</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-envelope-open-text"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center">
                            <span class="stat-badge badge-amber"><i class="fa-solid fa-hourglass-half text-xs"></i> Pending Action </span>
                            <span class="stat-subtext ms-2">Requires review</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-3 mb-xl-0">
                <div class="stat-card stat-card-rose">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="stat-label">High Priority Alerts</span>
                                <h3 class="stat-value text-rose mt-1 mb-0">2</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-shield-halved"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center">
                            <span class="stat-badge badge-rose"><i class="fa-solid fa-circle-exclamation text-xs"></i> Critical Stream </span>
                            <span class="stat-subtext ms-2">Security &amp; system</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-3 mb-xl-0">
                <div class="stat-card stat-card-emerald">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="stat-label">System Health</span>
                                <h3 class="stat-value text-emerald mt-1 mb-0">99.8%</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-shield-check"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center">
                            <span class="stat-badge badge-emerald"><i class="fa-solid fa-circle-check text-xs"></i> Nominal State </span>
                            <span class="stat-subtext ms-2">All services online</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card main-feed-card">
                    <div class="card-header border-0 pb-0 pt-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="card-title-text mb-0"><i class="fa-solid fa-bell me-2 text-primary"></i>System Notifications Activity Feed</h4>
                            <span class="unread-count-chip">4 Unread</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-action"><i class="fa-solid fa-check-double me-1"></i> Mark All Read </button>
                            <button type="button" class="btn btn-sm btn-outline-action text-danger"><i class="fa-solid fa-trash me-1"></i> Clear Read </button>
                        </div>
                    </div>
                    
                    <div class="filters-bar px-4 py-3 border-top mt-3">
                        <div class="row align-items-center g-3">
                            <div class="col-md-6 col-sm-12">
                                <div class="input-group search-input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" placeholder="Search notifications by title or message..." class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12 d-flex justify-content-md-end">
                                <div class="segmented-nav">
                                    <button type="button" class="segmented-tab active"> All <span class="count-pill">8</span></button>
                                    <button type="button" class="segmented-tab"> Unread <span class="count-pill">4</span></button>
                                    <button type="button" class="segmented-tab"> Read <span class="count-pill">4</span></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="notifications-list-wrapper">
                            
                            <div class="notification-item-row is-unread-row notification-type-user">
                                <div class="unread-strip"></div>
                                <div class="type-icon-wrapper"><i class="fa-solid fa-user-plus"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h6 class="notification-title mb-0">New Patient Registration</h6>
                                            <span class="badge-priority badge-priority-medium"><span class="status-dot"></span> Medium </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 26, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">John Doe has completed online registration and is awaiting medical profile approval.</p>
                                    <div class="notification-action-bar">
                                        <a class="btn btn-sm btn-inline-action" href="#/admin/patients"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Patient </a>
                                    </div>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1">
                                    <button type="button" title="Mark as Read" class="btn btn-sm btn-icon-action"><i class="fa-solid fa-check text-success"></i></button>
                                    <button type="button" title="Delete Notification" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            
                            <div class="notification-item-row is-unread-row notification-type-security">
                                <div class="unread-strip"></div>
                                <div class="type-icon-wrapper"><i class="fa-solid fa-shield-halved"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h6 class="notification-title mb-0">Security Alert: Failed Login Attempts</h6>
                                            <span class="badge-priority badge-priority-high"><span class="status-dot"></span> High </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 26, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">Multiple failed authentication attempts detected from IP address 192.168.1.100.</p>
                                    <div class="notification-action-bar">
                                        <a class="btn btn-sm btn-inline-action" href="#/admin/security"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Review Incident </a>
                                    </div>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1">
                                    <button type="button" title="Mark as Read" class="btn btn-sm btn-icon-action"><i class="fa-solid fa-check text-success"></i></button>
                                    <button type="button" title="Delete Notification" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            
                            <div class="notification-item-row notification-type-appointment">
                                <div class="type-icon-wrapper"><i class="fa-solid fa-calendar-check"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h6 class="notification-title mb-0">Daily Appointment Schedule Summary</h6>
                                            <span class="badge-priority badge-priority-medium"><span class="status-dot"></span> Medium </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 26, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">15 patient appointments scheduled for today. 3 patients have not confirmed attendance.</p>
                                    <div class="notification-action-bar">
                                        <a class="btn btn-sm btn-inline-action" href="#/admin/appointments"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Appointments </a>
                                    </div>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1">
                                    <button type="button" title="Delete Notification" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            
                            <div class="notification-item-row is-unread-row notification-type-system">
                                <div class="unread-strip"></div>
                                <div class="type-icon-wrapper"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h6 class="notification-title mb-0">Low Inventory Alert</h6>
                                            <span class="badge-priority badge-priority-high"><span class="status-dot"></span> High </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 26, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">Surgical mask reserves running low (10 units remaining in Central Pharmacy).</p>
                                    <div class="notification-action-bar">
                                        <a class="btn btn-sm btn-inline-action" href="#/admin/inventory"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Reorder Supplies </a>
                                    </div>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1">
                                    <button type="button" title="Mark as Read" class="btn btn-sm btn-icon-action"><i class="fa-solid fa-check text-success"></i></button>
                                    <button type="button" title="Delete Notification" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            
                            <div class="notification-item-row is-unread-row notification-type-system">
                                <div class="unread-strip"></div>
                                <div class="type-icon-wrapper"><i class="fa-solid fa-database"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h6 class="notification-title mb-0">System Backup Completed</h6>
                                            <span class="badge-priority badge-priority-low"><span class="status-dot"></span> Low </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 26, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">Automated daily database and EMR backup completed successfully at 2:00 AM without errors.</p>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1">
                                    <button type="button" title="Mark as Read" class="btn btn-sm btn-icon-action"><i class="fa-solid fa-check text-success"></i></button>
                                    <button type="button" title="Delete Notification" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            
                            <div class="notification-item-row notification-type-billing">
                                <div class="type-icon-wrapper"><i class="fa-solid fa-dollar-sign"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h6 class="notification-title mb-0">Payment Received</h6>
                                            <span class="badge-priority badge-priority-medium"><span class="status-dot"></span> Medium </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 25, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">Payment of $250.00 received from Sarah Johnson for Invoice #INV-1234.</p>
                                    <div class="notification-action-bar">
                                        <a class="btn btn-sm btn-inline-action" href="#/admin/billing"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Invoice </a>
                                    </div>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1">
                                    <button type="button" title="Delete Notification" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            
                            <div class="notification-item-row notification-type-update">
                                <div class="type-icon-wrapper"><i class="fa-solid fa-rotate"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h6 class="notification-title mb-0">Software Update Available</h6>
                                            <span class="badge-priority badge-priority-low"><span class="status-dot"></span> Low </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 25, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">MediDash v2.5.0 build is ready for deployment with performance enhancements &amp; security patches.</p>
                                    <div class="notification-action-bar">
                                        <a class="btn btn-sm btn-inline-action" href="#/admin/updates"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Update Now </a>
                                    </div>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1">
                                    <button type="button" title="Delete Notification" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            
                            <div class="notification-item-row notification-type-user">
                                <div class="type-icon-wrapper"><i class="fa-solid fa-user-doctor"></i></div>
                                <div class="notification-body flex-grow-1 min-w-0 me-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h6 class="notification-title mb-0">New Staff Member Added</h6>
                                            <span class="badge-priority badge-priority-low"><span class="status-dot"></span> Low </span>
                                        </div>
                                        <span class="timestamp-tag"><i class="fa-regular fa-clock me-1"></i> Nov 25, 2025 </span>
                                    </div>
                                    <p class="notification-text mb-2">Dr. Emily Chen has been onboarded as Senior Cardiologist in Cardiology Department.</p>
                                </div>
                                <div class="notification-controls d-flex align-items-center gap-1">
                                    <button type="button" title="Delete Notification" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
