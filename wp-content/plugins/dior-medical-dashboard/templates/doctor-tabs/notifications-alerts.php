<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/notifications.css?v=' . time()); ?>">

<section class="dior-tab-panel notifications-tab-panel" id="tab-doc-notifications-alerts" style="display: none; background-color: #f8fafc; padding: 20px; position: relative; overflow: hidden;">
    
    <!-- Scoped Watermark & Design Overrides -->
    <style>
    #tab-doc-notifications-alerts .dior-page-watermark,
    #tab-doc-notifications-alerts .page-watermark,
    #tab-doc-notifications-alerts .dior-big-title,
    .dior-doctor-wrap #tab-doc-notifications-alerts .dior-page-watermark,
    .dior-doctor-wrap #tab-doc-notifications-alerts .page-watermark,
    .dior-doctor-wrap #tab-doc-notifications-alerts .dior-big-title {
        display: none !important;
        opacity: 0 !important;
        visibility: hidden !important;
    }

    #tab-doc-notifications-alerts .va-breadcrumb-list,
    #tab-doc-notifications-alerts .va-breadcrumb-list li,
    #tab-doc-notifications-alerts .va-breadcrumb-list a,
    #tab-doc-notifications-alerts .va-breadcrumb-list span,
    #tab-doc-notifications-alerts .va-breadcrumb-list i {
        font-size: 12px !important;
        line-height: 1.2 !important;
    }
    #tab-doc-notifications-alerts .va-breadcrumb-list li::before,
    #tab-doc-notifications-alerts .va-breadcrumb-list li::after {
        display: none !important;
        content: none !important;
    }

    /* Stat Cards Row */
    .dior-alerts-stats-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        width: 100%;
        margin-bottom: 24px;
    }
    .dior-alerts-stat-item {
        flex: 1 1 calc(25% - 12px);
        min-width: 220px;
    }
    @media (max-width: 992px) {
        .dior-alerts-stat-item {
            flex: 1 1 calc(50% - 12px);
        }
    }
    @media (max-width: 576px) {
        .dior-alerts-stat-item {
            flex: 1 1 100%;
        }
    }

    /* Individual Stat Card */
    .dior-stat-card-box {
        background: #ffffff;
        border-radius: 14px;
        padding: 20px;
        position: relative;
        border: 1px solid #e7edf4;
        box-shadow: 0 4px 18px rgba(15,23,42,.04);
        height: 100%;
        box-sizing: border-box;
    }

    /* Alert Feed Item Cards */
    .dior-alert-card-item {
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 16px;
        position: relative;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .dior-alert-card-warning {
        background: #fffdf5 !important;
        border-left: 5px solid #f59e0b !important;
    }
    .dior-alert-card-success {
        background: #f4fbf7 !important;
        border-left: 5px solid #10b981 !important;
    }
    .dior-alert-card-danger {
        background: #fef8f8 !important;
        border-left: 5px solid #ef4444 !important;
    }
    .dior-alert-card-announcement {
        background: #ffffff !important;
        border: 1px solid #3b82f6 !important;
    }

    /* Badges & Chips */
    .dior-badge-pill {
        padding: 3px 10px;
        border-radius: 16px;
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .dior-badge-amber-light { background: #ffedd5; color: #d97706; }
    .dior-badge-blue-light { background: #dbeafe; color: #2563eb; }
    .dior-badge-red-light { background: #fee2e2; color: #dc2626; }
    .dior-badge-green-light { background: #d1fae5; color: #059669; }
    .dior-badge-gray-light { background: #f1f5f9; color: #64748b; }
    .dior-chip-dismissible { background: #e0e7ff; color: #2563eb; padding: 4px 12px; border-radius: 16px; font-weight: 600; font-size: 11px; }

    /* Action Buttons */
    .dior-action-btn-sm {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s;
    }
    .dior-action-btn-sm:hover {
        background: #f1f5f9;
        color: #1e293b;
    }
    </style>

    <!-- Header & Breadcrumb -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; position: relative; z-index: 3;">
        <div>
            <h4 class="mb-0" style="font-size: 20px; font-weight: 700; color: #1e293b;">Alerts &amp; Announcements</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="javascript:void(0)"><i class="fa-solid fa-house" style="color: #2563eb;"></i></a></li>
                <li style="color: #cbd5e1;">/</li>
                <li><a href="javascript:void(0)" style="color: #64748b; text-decoration: none;">Notifications</a></li>
                <li style="color: #cbd5e1;">/</li>
                <li class="active"><span style="color: #1e293b; font-weight: 600;">Alerts &amp; Announcements</span></li>
            </ul>
        </div>
    </div>
    
    <div class="section-body" style="position: relative; z-index: 2;">
        
        <!-- 4 Top Stat Cards Grid -->
        <div class="dior-alerts-stats-grid">
            
            <!-- Card 1: Total Broadcasts -->
            <div class="dior-alerts-stat-item">
                <div class="dior-stat-card-box">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">TOTAL BROADCASTS</span>
                            <h3 style="font-size: 28px; font-weight: 800; color: #d97706; margin: 4px 0 0 0;">6</h3>
                        </div>
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 14px;">
                        <span style="background: #fef3c7; color: #b45309; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-bullhorn" style="font-size: 10px;"></i> Active Feed
                        </span>
                        <span style="font-size: 12px; color: #94a3b8; font-weight: 500;">System wide</span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Active System Alerts -->
            <div class="dior-alerts-stat-item">
                <div class="dior-stat-card-box">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">ACTIVE SYSTEM ALERTS</span>
                            <h3 style="font-size: 28px; font-weight: 800; color: #e11d48; margin: 4px 0 0 0;">3</h3>
                        </div>
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #ffe4e6; color: #e11d48; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 14px;">
                        <span style="background: #ffe4e6; color: #be123c; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-circle-exclamation" style="font-size: 10px;"></i> Live Alerts
                        </span>
                        <span style="font-size: 12px; color: #94a3b8; font-weight: 500;">Operational alerts</span>
                    </div>
                </div>
            </div>

            <!-- Card 3: Announcements -->
            <div class="dior-alerts-stat-item">
                <div class="dior-stat-card-box">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">ANNOUNCEMENTS</span>
                            <h3 style="font-size: 28px; font-weight: 800; color: #2563eb; margin: 4px 0 0 0;">3</h3>
                        </div>
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #e0e7ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                            <i class="fa-solid fa-microphone"></i>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 14px;">
                        <span style="background: #e0e7ff; color: #4338ca; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-thumbtack" style="font-size: 10px;"></i> Published Updates
                        </span>
                        <span style="font-size: 12px; color: #94a3b8; font-weight: 500;">General broadcasts</span>
                    </div>
                </div>
            </div>

            <!-- Card 4: Target Audiences -->
            <div class="dior-alerts-stat-item">
                <div class="dior-stat-card-box">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">TARGET AUDIENCES</span>
                            <h3 style="font-size: 28px; font-weight: 800; color: #059669; margin: 4px 0 0 0;">6</h3>
                        </div>
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #d1fae5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 14px;">
                        <span style="background: #d1fae5; color: #047857; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-check-double" style="font-size: 10px;"></i> 100% Reached
                        </span>
                        <span style="font-size: 12px; color: #94a3b8; font-weight: 500;">Staff &amp; patients</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Main Feed Card Box -->
        <div class="card" style="border-radius: 14px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05); overflow: hidden;">
            
            <!-- Controls Header Row -->
            <div style="padding: 18px 24px; border-bottom: 1px solid #edf1f5; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="segmented-nav" style="display: inline-flex; background: #f1f5f9; padding: 4px; border-radius: 20px; gap: 4px;">
                        <button type="button" class="segmented-tab active" id="tab-btn-warning" onclick="diorFilterAlertTab(this, 'warning')" style="border: none; background: #ffffff; padding: 6px 16px; border-radius: 16px; font-size: 13px; font-weight: 600; color: #2563eb; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                            <i class="fa-solid fa-triangle-exclamation"></i> System Alerts <span style="background: #e0e7ff; color: #2563eb; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">3</span>
                        </button>
                        <button type="button" class="segmented-tab" id="tab-btn-announcement" onclick="diorFilterAlertTab(this, 'announcement')" style="border: none; background: transparent; padding: 6px 16px; border-radius: 16px; font-size: 13px; font-weight: 600; color: #64748b; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <i class="fa-solid fa-bullhorn"></i> Announcements <span style="background: #e2e8f0; color: #475569; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">3</span>
                        </button>
                    </div>
                </div>
                <button type="button" style="background: #2563eb; color: #ffffff; border: none; border-radius: 8px; padding: 9px 20px; font-weight: 600; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
                    <i class="fa-solid fa-plus"></i> Create Broadcast
                </button>
            </div>
            
            <!-- Filters Bar -->
            <div style="padding: 14px 24px; background: #f8fafc; border-bottom: 1px solid #edf1f5;">
                <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between;">
                    <div style="flex: 1 1 350px; min-width: 0;">
                        <div class="search-input-group" style="background: #ffffff; border-radius: 8px; border: 1px solid #cbd5e1; display: flex; align-items: center; padding: 0 12px;">
                            <i class="fas fa-search" style="color: #94a3b8; font-size: 13px; margin-right: 8px;"></i>
                            <input type="text" id="dior-alerts-search-input" onkeyup="diorFilterAlertsSearch()" placeholder="Search alerts or announcements by title or content..." style="width: 100%; border: none; background: transparent; padding: 8px 0; font-size: 13px; outline: none; color: #1e293b;">
                        </div>
                    </div>
                    <div style="flex: 0 0 220px;">
                        <select id="dior-alerts-status-select" onchange="diorFilterAlertsStatus(this.value)" style="width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; color: #475569; padding: 8px 12px; background-color: #ffffff; outline: none;">
                            <option value="all">Filter by Status (All)</option>
                            <option value="active">Active</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="expired">Expired</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Feed List Container -->
            <div style="padding: 24px;">
                <div id="dior-alerts-feed-list" style="display: flex; flex-direction: column; gap: 16px;">
                    
                    <!-- ================= SYSTEM ALERTS FEED ================= -->
                    <div class="dior-feed-group" id="dior-feed-warning" style="display: flex; flex-direction: column; gap: 16px;">
                        
                        <!-- System Alert 1 -->
                        <div class="dior-alert-card-item dior-alert-card-warning" data-status="active">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <h5 style="font-weight: 700; color: #1e293b; font-size: 15px; margin: 0;">System Maintenance Scheduled</h5>
                                    <span class="dior-badge-pill dior-badge-amber-light"><i class="fas fa-circle" style="font-size: 6px;"></i> High Priority</span>
                                    <span class="dior-badge-pill dior-badge-green-light"><i class="fas fa-circle" style="font-size: 6px;"></i> Active</span>
                                </div>
                                <div style="display: flex; gap: 6px;">
                                    <button type="button" title="Edit Alert" class="dior-action-btn-sm"><i class="fa-solid fa-pen"></i></button>
                                    <button type="button" title="Delete Alert" onclick="this.closest('.dior-alert-card-item').remove()" class="dior-action-btn-sm" style="color: #ef4444;"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            <p style="font-size: 13.5px; color: #475569; margin: 0 0 14px 0; line-height: 1.5;">The EMR system will undergo routine maintenance on Sunday from 2:00 AM - 4:00 AM. Please ensure all patient charts are saved prior to maintenance window.</p>
                            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                                <div style="display: flex; align-items: center; gap: 16px; font-size: 12px; color: #64748b; flex-wrap: wrap;">
                                    <span><i class="fa-regular fa-calendar" style="color: #2563eb; margin-right: 5px;"></i> 2025-11-27 to 2025-11-28</span>
                                    <span><i class="fa-regular fa-user" style="color: #2563eb; margin-right: 5px;"></i> System Admin</span>
                                    <span><i class="fa-solid fa-users" style="color: #2563eb; margin-right: 5px;"></i> Target: <strong>All Users</strong></span>
                                </div>
                                <span class="dior-chip-dismissible"><i class="fa-solid fa-circle-check" style="margin-right: 4px;"></i> User Dismissible</span>
                            </div>
                        </div>

                        <!-- System Alert 2 -->
                        <div class="dior-alert-card-item dior-alert-card-success" data-status="active">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <h5 style="font-weight: 700; color: #1e293b; font-size: 15px; margin: 0;">New Online Appointment Feature Released</h5>
                                    <span class="dior-badge-pill dior-badge-blue-light"><i class="fas fa-circle" style="font-size: 6px;"></i> Medium Priority</span>
                                    <span class="dior-badge-pill dior-badge-green-light"><i class="fas fa-circle" style="font-size: 6px;"></i> Active</span>
                                </div>
                                <div style="display: flex; gap: 6px;">
                                    <button type="button" title="Edit Alert" class="dior-action-btn-sm"><i class="fa-solid fa-pen"></i></button>
                                    <button type="button" title="Delete Alert" onclick="this.closest('.dior-alert-card-item').remove()" class="dior-action-btn-sm" style="color: #ef4444;"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            <p style="font-size: 13.5px; color: #475569; margin: 0 0 14px 0; line-height: 1.5;">Check out our new patient portal feature! Patients can now self-schedule follow-up visits directly online.</p>
                            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                                <div style="display: flex; align-items: center; gap: 16px; font-size: 12px; color: #64748b; flex-wrap: wrap;">
                                    <span><i class="fa-regular fa-calendar" style="color: #2563eb; margin-right: 5px;"></i> 2025-11-26 to 2025-12-26</span>
                                    <span><i class="fa-regular fa-user" style="color: #2563eb; margin-right: 5px;"></i> Product Team</span>
                                    <span><i class="fa-solid fa-users" style="color: #2563eb; margin-right: 5px;"></i> Target: <strong>Doctors, Staff</strong></span>
                                </div>
                                <span class="dior-chip-dismissible"><i class="fa-solid fa-circle-check" style="margin-right: 4px;"></i> User Dismissible</span>
                            </div>
                        </div>

                        <!-- System Alert 3 -->
                        <div class="dior-alert-card-item dior-alert-card-danger" data-status="active">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <h5 style="font-weight: 700; color: #1e293b; font-size: 15px; margin: 0;">Mandatory Security Update Required</h5>
                                    <span class="dior-badge-pill dior-badge-red-light"><i class="fas fa-circle" style="font-size: 6px;"></i> Critical Priority</span>
                                    <span class="dior-badge-pill dior-badge-green-light"><i class="fas fa-circle" style="font-size: 6px;"></i> Active</span>
                                </div>
                                <div style="display: flex; gap: 6px;">
                                    <button type="button" title="Edit Alert" class="dior-action-btn-sm"><i class="fa-solid fa-pen"></i></button>
                                    <button type="button" title="Delete Alert" onclick="this.closest('.dior-alert-card-item').remove()" class="dior-action-btn-sm" style="color: #ef4444;"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            <p style="font-size: 13.5px; color: #475569; margin: 0 0 14px 0; line-height: 1.5;">All medical and administrative staff must update their system credentials to comply with updated HIPAA security protocols.</p>
                            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                                <div style="display: flex; align-items: center; gap: 16px; font-size: 12px; color: #64748b; flex-wrap: wrap;">
                                    <span><i class="fa-regular fa-calendar" style="color: #2563eb; margin-right: 5px;"></i> 2025-11-20 to 2025-11-30</span>
                                    <span><i class="fa-regular fa-user" style="color: #2563eb; margin-right: 5px;"></i> Security Operations</span>
                                    <span><i class="fa-solid fa-users" style="color: #2563eb; margin-right: 5px;"></i> Target: <strong>All Users</strong></span>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- ================= ANNOUNCEMENTS FEED ================= -->
                    <div class="dior-feed-group" id="dior-feed-announcement" style="display: none; flex-direction: column; gap: 16px;">
                        
                        <!-- Announcement 1: Holiday Operating Schedule -->
                        <div class="dior-alert-card-item dior-alert-card-announcement" data-status="active">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <h5 style="font-weight: 700; color: #1e293b; font-size: 15px; margin: 0;">Holiday Operating Schedule &amp; Duty Roster</h5>
                                    <span class="dior-badge-pill dior-badge-gray-light"><i class="fa-solid fa-tag" style="font-size: 10px;"></i> General</span>
                                    <span class="dior-badge-pill dior-badge-green-light"><i class="fas fa-circle" style="font-size: 6px;"></i> Published</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="background: #2563eb; color: #ffffff; padding: 4px 10px; border-radius: 14px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-thumbtack"></i> Pinned Broadcast
                                    </span>
                                    <button type="button" title="Pin" class="dior-action-btn-sm"><i class="fa-solid fa-thumbtack"></i></button>
                                    <button type="button" title="Edit" class="dior-action-btn-sm"><i class="fa-solid fa-pen"></i></button>
                                    <button type="button" title="Delete" onclick="this.closest('.dior-alert-card-item').remove()" class="dior-action-btn-sm" style="color: #ef4444;"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            <p style="font-size: 13.5px; color: #475569; margin: 0 0 14px 0; line-height: 1.5;">The outpatient clinic will operate with a revised schedule during the holiday season. Emergency &amp; ICU departments will remain fully staffed 24/7.</p>

                            <!-- Attachments Box -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 6px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 10px; font-size: 12px; color: #2563eb; font-weight: 500;">
                                    <i class="fa-solid fa-paperclip"></i> holiday-schedule.pdf
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 10px; font-size: 12px; color: #2563eb; font-weight: 500;">
                                    <i class="fa-solid fa-paperclip"></i> duty-roster.xlsx
                                </div>
                            </div>

                            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                                <div style="display: flex; align-items: center; gap: 16px; font-size: 12px; color: #64748b; flex-wrap: wrap;">
                                    <span><i class="fa-regular fa-calendar" style="color: #2563eb; margin-right: 5px;"></i> Published: 2025-11-25</span>
                                    <span><i class="fa-regular fa-user" style="color: #2563eb; margin-right: 5px;"></i> HR Department</span>
                                    <span><i class="fa-solid fa-users" style="color: #2563eb; margin-right: 5px;"></i> Audience: <strong>All Users</strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Announcement 2: Infection Control Guidelines -->
                        <div class="dior-alert-card-item dior-alert-card-announcement" data-status="active">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <h5 style="font-weight: 700; color: #1e293b; font-size: 15px; margin: 0;">Updated Hospital Infection Control Guidelines</h5>
                                    <span class="dior-badge-pill dior-badge-gray-light"><i class="fa-solid fa-tag" style="font-size: 10px;"></i> Health &amp; Safety</span>
                                    <span class="dior-badge-pill dior-badge-green-light"><i class="fas fa-circle" style="font-size: 6px;"></i> Published</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="background: #2563eb; color: #ffffff; padding: 4px 10px; border-radius: 14px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-thumbtack"></i> Pinned Broadcast
                                    </span>
                                    <button type="button" title="Pin" class="dior-action-btn-sm"><i class="fa-solid fa-thumbtack"></i></button>
                                    <button type="button" title="Edit" class="dior-action-btn-sm"><i class="fa-solid fa-pen"></i></button>
                                    <button type="button" title="Delete" onclick="this.closest('.dior-alert-card-item').remove()" class="dior-action-btn-sm" style="color: #ef4444;"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            <p style="font-size: 13.5px; color: #475569; margin: 0 0 14px 0; line-height: 1.5;">Revised infection control safety protocols are now in effect across all clinical wards. Mandatory hand hygiene &amp; personal protective equipment compliance checks will be conducted daily.</p>
                            
                            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                                <div style="display: flex; align-items: center; gap: 16px; font-size: 12px; color: #64748b; flex-wrap: wrap;">
                                    <span><i class="fa-regular fa-calendar" style="color: #2563eb; margin-right: 5px;"></i> Published: 2025-11-24</span>
                                    <span><i class="fa-regular fa-user" style="color: #2563eb; margin-right: 5px;"></i> Medical Director</span>
                                    <span><i class="fa-solid fa-users" style="color: #2563eb; margin-right: 5px;"></i> Audience: <strong>Doctors, Nurses, Staff</strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Announcement 3: Mandatory EMR Training Workshop -->
                        <div class="dior-alert-card-item dior-alert-card-announcement" data-status="active">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <h5 style="font-weight: 700; color: #1e293b; font-size: 15px; margin: 0;">Mandatory EMR Training Workshop</h5>
                                    <span class="dior-badge-pill dior-badge-gray-light"><i class="fa-solid fa-tag" style="font-size: 10px;"></i> Training</span>
                                    <span class="dior-badge-pill dior-badge-green-light"><i class="fas fa-circle" style="font-size: 6px;"></i> Published</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <button type="button" title="Pin" class="dior-action-btn-sm"><i class="fa-solid fa-thumbtack"></i></button>
                                    <button type="button" title="Edit" class="dior-action-btn-sm"><i class="fa-solid fa-pen"></i></button>
                                    <button type="button" title="Delete" onclick="this.closest('.dior-alert-card-item').remove()" class="dior-action-btn-sm" style="color: #ef4444;"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                            <p style="font-size: 13.5px; color: #475569; margin: 0 0 14px 0; line-height: 1.5;">A comprehensive training session on the new clinical workflow module is scheduled for next Tuesday in the main auditorium.</p>
                            
                            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                                <div style="display: flex; align-items: center; gap: 16px; font-size: 12px; color: #64748b; flex-wrap: wrap;">
                                    <span><i class="fa-regular fa-calendar" style="color: #2563eb; margin-right: 5px;"></i> Published: 2025-11-23</span>
                                    <span><i class="fa-regular fa-user" style="color: #2563eb; margin-right: 5px;"></i> Clinical Education</span>
                                    <span><i class="fa-solid fa-users" style="color: #2563eb; margin-right: 5px;"></i> Audience: <strong>All Users</strong></span>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<script>
function diorFilterAlertTab(btn, category) {
    const parent = btn.parentElement;
    parent.querySelectorAll('.segmented-tab').forEach(b => {
        b.style.background = 'transparent';
        b.style.color = '#64748b';
        b.style.boxShadow = 'none';
    });
    btn.style.background = '#ffffff';
    btn.style.color = '#2563eb';
    btn.style.boxShadow = '0 2px 6px rgba(0,0,0,0.06)';

    const feedWarning = document.getElementById('dior-feed-warning');
    const feedAnnouncement = document.getElementById('dior-feed-announcement');

    if (category === 'warning') {
        if (feedWarning) feedWarning.style.display = 'flex';
        if (feedAnnouncement) feedAnnouncement.style.display = 'none';
    } else if (category === 'announcement') {
        if (feedWarning) feedWarning.style.display = 'none';
        if (feedAnnouncement) feedAnnouncement.style.display = 'flex';
    }
}

function diorFilterAlertsSearch() {
    const val = document.getElementById('dior-alerts-search-input').value.toLowerCase();
    const items = document.querySelectorAll('#dior-alerts-feed-list .dior-alert-card-item');
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(val) ? 'block' : 'none';
    });
}

function diorFilterAlertsStatus(status) {
    const items = document.querySelectorAll('#dior-alerts-feed-list .dior-alert-card-item');
    items.forEach(item => {
        if (status === 'all' || item.dataset.status === status) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>
