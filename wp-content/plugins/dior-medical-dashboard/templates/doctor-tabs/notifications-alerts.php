<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/notifications.css?v=' . time()); ?>">

<section class="dior-tab-panel notifications-tab-panel" id="tab-doc-notifications-alerts" style="display: none;">
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Alerts & Announcements</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)" style="color: #64748B; text-decoration: none; font-size: 14px;">Notifications</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">Alerts & Announcements</span></li>
            </ul>
        </div>
    </div>
    
    <div class="section-body">
        <div class="row mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-3 mb-xl-0">
                <div class="stat-card stat-card-amber">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="stat-label">Total Broadcasts</span>
                                <h3 class="stat-value text-amber mt-1 mb-0">6</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-bell"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center">
                            <span class="stat-badge badge-amber"><i class="fa-solid fa-bullhorn text-xs"></i> Active Feed </span>
                            <span class="stat-subtext ms-2">System wide</span>
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
                                <span class="stat-label">Active System Alerts</span>
                                <h3 class="stat-value text-rose mt-1 mb-0">3</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center">
                            <span class="stat-badge badge-rose"><i class="fa-solid fa-circle-exclamation text-xs"></i> Live Alerts </span>
                            <span class="stat-subtext ms-2">Operational alerts</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-3 mb-xl-0">
                <div class="stat-card stat-card-indigo">
                    <div class="stat-glow"></div>
                    <div class="stat-inner">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="stat-label">Announcements</span>
                                <h3 class="stat-value text-indigo mt-1 mb-0">3</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-microphone"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center">
                            <span class="stat-badge badge-indigo"><i class="fa-solid fa-thumbtack text-xs"></i> Published Updates </span>
                            <span class="stat-subtext ms-2">General broadcasts</span>
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
                                <span class="stat-label">Target Audiences</span>
                                <h3 class="stat-value text-emerald mt-1 mb-0">6</h3>
                            </div>
                            <div class="stat-icon-wrapper"><i class="fa-solid fa-users"></i></div>
                        </div>
                        <div class="stat-footer d-flex align-items-center">
                            <span class="stat-badge badge-emerald"><i class="fa-solid fa-check-double text-xs"></i> 100% Reached </span>
                            <span class="stat-subtext ms-2">Staff &amp; patients</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card main-feed-card">
                    <div class="card-header border-0 pb-0 pt-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="segmented-nav">
                                <button type="button" class="segmented-tab active"><i class="fa-solid fa-triangle-exclamation me-2"></i> System Alerts <span class="count-pill">3</span></button>
                                <button type="button" class="segmented-tab"><i class="fa-solid fa-bullhorn me-2"></i> Announcements <span class="count-pill">3</span></button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary-gradient"><i class="fa-solid fa-plus text-xs me-1"></i> Create Broadcast </button>
                    </div>
                    
                    <div class="filters-bar px-4 py-3 border-top mt-3">
                        <div class="row align-items-center g-3">
                            <div class="col-md-7 col-sm-12">
                                <div class="input-group search-input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" placeholder="Search alerts or announcements by title or content..." class="form-control">
                                </div>
                            </div>
                            <div class="col-md-5 col-sm-12">
                                <select class="form-select filter-select">
                                    <option value="all">Filter by Status (All)</option>
                                    <option value="active">Active</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="expired">Expired</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <div class="alerts-feed-list">
                            <div class="alert-item-card alert-type-warning">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <h5 class="alert-item-title mb-0">System Maintenance Scheduled</h5>
                                        <span class="badge-priority badge-priority-high"><span class="status-dot"></span> High Priority </span>
                                        <span class="badge-status badge-status-active"><span class="status-dot"></span> Active </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" title="Edit Alert" class="btn btn-sm btn-icon-action"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" title="Delete Alert" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </div>
                                <p class="alert-item-message mb-3">The EMR system will undergo routine maintenance on Sunday from 2:00 AM - 4:00 AM. Please ensure all patient charts are saved prior to maintenance window.</p>
                                <div class="alert-item-footer d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
                                    <div class="d-flex flex-wrap align-items-center gap-3 text-xs text-muted">
                                        <span class="meta-tag"><i class="fa-regular fa-calendar me-1 text-primary"></i> 2025-11-27 to 2025-11-28</span>
                                        <span class="meta-tag"><i class="fa-regular fa-user me-1 text-primary"></i> System Admin</span>
                                        <span class="meta-tag"><i class="fa-solid fa-users me-1 text-primary"></i> Target: <strong>All Users</strong></span>
                                    </div>
                                    <span class="dismissible-chip text-xs"><i class="fa-solid fa-circle-check me-1"></i> User Dismissible </span>
                                </div>
                            </div>
                            
                            <div class="alert-item-card alert-type-success">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <h5 class="alert-item-title mb-0">New Online Appointment Feature Released</h5>
                                        <span class="badge-priority badge-priority-medium"><span class="status-dot"></span> Medium Priority </span>
                                        <span class="badge-status badge-status-active"><span class="status-dot"></span> Active </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" title="Edit Alert" class="btn btn-sm btn-icon-action"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" title="Delete Alert" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </div>
                                <p class="alert-item-message mb-3">Check out our new patient portal feature! Patients can now self-schedule follow-up visits directly online.</p>
                                <div class="alert-item-footer d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
                                    <div class="d-flex flex-wrap align-items-center gap-3 text-xs text-muted">
                                        <span class="meta-tag"><i class="fa-regular fa-calendar me-1 text-primary"></i> 2025-11-26 to 2025-12-26</span>
                                        <span class="meta-tag"><i class="fa-regular fa-user me-1 text-primary"></i> Product Team</span>
                                        <span class="meta-tag"><i class="fa-solid fa-users me-1 text-primary"></i> Target: <strong>Doctors, Staff</strong></span>
                                    </div>
                                    <span class="dismissible-chip text-xs"><i class="fa-solid fa-circle-check me-1"></i> User Dismissible </span>
                                </div>
                            </div>
                            
                            <div class="alert-item-card alert-type-danger">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <h5 class="alert-item-title mb-0">Mandatory Security Update Required</h5>
                                        <span class="badge-priority badge-priority-critical"><span class="status-dot"></span> Critical Priority </span>
                                        <span class="badge-status badge-status-active"><span class="status-dot"></span> Active </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" title="Edit Alert" class="btn btn-sm btn-icon-action"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" title="Delete Alert" class="btn btn-sm btn-icon-action text-danger"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </div>
                                <p class="alert-item-message mb-3">All medical and administrative staff must update their system credentials to comply with updated HIPAA security protocols.</p>
                                <div class="alert-item-footer d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
                                    <div class="d-flex flex-wrap align-items-center gap-3 text-xs text-muted">
                                        <span class="meta-tag"><i class="fa-regular fa-calendar me-1 text-primary"></i> 2025-11-20 to 2025-11-30</span>
                                        <span class="meta-tag"><i class="fa-regular fa-user me-1 text-primary"></i> Security Operations</span>
                                        <span class="meta-tag"><i class="fa-solid fa-users me-1 text-primary"></i> Target: <strong>All Users</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
