<section class="dior-tab-panel dior-source-panel" id="tab-doc-settings" style="display: none; background-color: #f8fafc; padding: 20px; position: relative; overflow: hidden;">
    
    <!-- Watermark & Breadcrumb Font Size Fix -->
    <style>
    #tab-doc-settings .dior-page-watermark,
    #tab-doc-settings .page-watermark,
    #tab-doc-settings .dior-big-title,
    .dior-doctor-wrap #tab-doc-settings .dior-page-watermark,
    .dior-doctor-wrap #tab-doc-settings .page-watermark,
    .dior-doctor-wrap #tab-doc-settings .dior-big-title {
        display: none !important;
        opacity: 0 !important;
        visibility: hidden !important;
    }
    
    /* Prevent external theme .breadcrumb-item styles from inflating text size */
    #tab-doc-settings .breadcrumb-list,
    #tab-doc-settings .breadcrumb-list li,
    #tab-doc-settings .breadcrumb-list a,
    #tab-doc-settings .breadcrumb-list span,
    #tab-doc-settings .breadcrumb-list i {
        font-size: 12px !important;
        line-height: 1.2 !important;
    }
    #tab-doc-settings .breadcrumb-list li::before,
    #tab-doc-settings .breadcrumb-list li::after {
        display: none !important;
        content: none !important;
    }

    #tab-doc-settings img.profile-avatar-img {
        width: 90px !important;
        height: 90px !important;
        max-width: 90px !important;
        max-height: 90px !important;
        min-width: 90px !important;
        min-height: 90px !important;
        border-radius: 50% !important;
        border: 4px solid #ffffff !important;
        object-fit: cover !important;
        display: block !important;
    }

    #tab-doc-settings .profile-cover-gradient {
        height: 100px !important;
        background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 60%, #3b82f6 100%) !important;
        width: 100% !important;
        border-radius: 12px 12px 0 0 !important;
    }

    /* Sidebar nav pills text vertical stacking */
    #tab-doc-settings .custom-settings-pills .dior-doc-nav-item {
        background: transparent;
        color: #475569 !important;
        border-radius: 10px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        border: none;
        width: 100%;
        text-align: left;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
    }
    #tab-doc-settings .custom-settings-pills .dior-doc-nav-item:hover {
        background: #f8fafc !important;
        color: #1e293b !important;
    }
    #tab-doc-settings .custom-settings-pills .dior-doc-nav-item.active {
        background: #2563eb !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }
    #tab-doc-settings .custom-settings-pills .dior-doc-nav-item.active .tab-title,
    #tab-doc-settings .custom-settings-pills .dior-doc-nav-item.active .icon-tab {
        color: #ffffff !important;
    }
    #tab-doc-settings .custom-settings-pills .dior-doc-nav-item.active .tab-subtext {
        color: rgba(255, 255, 255, 0.85) !important;
    }

    /* Form Grid Layout */
    #tab-doc-settings .dior-settings-grid-row {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        width: 100%;
    }
    #tab-doc-settings .dior-settings-col-sidebar {
        flex: 0 0 calc(25% - 15px);
        max-width: calc(25% - 15px);
        width: calc(25% - 15px);
    }
    #tab-doc-settings .dior-settings-col-main {
        flex: 0 0 calc(75% - 5px);
        max-width: calc(75% - 5px);
        width: calc(75% - 5px);
    }
    @media (max-width: 992px) {
        #tab-doc-settings .dior-settings-col-sidebar,
        #tab-doc-settings .dior-settings-col-main {
            flex: 0 0 100% !important;
            max-width: 100% !important;
            width: 100% !important;
        }
    }

    #tab-doc-settings .dior-form-row-2 {
        display: flex;
        gap: 16px;
        margin-bottom: 16px;
    }
    #tab-doc-settings .dior-form-row-2 > .dior-form-group {
        flex: 1 1 50%;
        min-width: 0;
    }
    #tab-doc-settings .dior-form-row-3 {
        display: flex;
        gap: 16px;
        margin-bottom: 16px;
    }
    #tab-doc-settings .dior-form-row-3 > .dior-form-group {
        flex: 1 1 33.333%;
        min-width: 0;
    }
    @media (max-width: 768px) {
        #tab-doc-settings .dior-form-row-2,
        #tab-doc-settings .dior-form-row-3 {
            flex-direction: column;
            gap: 12px;
        }
    }

    #tab-doc-settings .search-input-group {
        display: flex;
        width: 100%;
    }
    #tab-doc-settings .search-input-group .input-group-text {
        background: #ffffff;
        border: 1px solid #dce4ec;
        border-right: none;
        color: #64748b;
        border-top-left-radius: 6px;
        border-bottom-left-radius: 6px;
        padding: 8px 12px;
        font-size: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #tab-doc-settings .search-input-group .form-control {
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
        border-top-right-radius: 6px !important;
        border-bottom-right-radius: 6px !important;
    }
    #tab-doc-settings .settings-form-card .form-control {
        width: 100%;
        min-height: 40px;
        border: 1px solid #dce4ec;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 13px;
        color: #1e293b;
        background-color: #ffffff;
    }

    /* Custom Switch Slider */
    #tab-doc-settings .dior-toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
        flex-shrink: 0;
    }
    #tab-doc-settings .dior-toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    #tab-doc-settings .dior-toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1;
        transition: .3s ease;
        border-radius: 24px;
    }
    #tab-doc-settings .dior-toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: #ffffff;
        transition: .3s ease;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    #tab-doc-settings .dior-toggle-switch input:checked + .dior-toggle-slider {
        background-color: #2563eb;
    }
    #tab-doc-settings .dior-toggle-switch input:checked + .dior-toggle-slider:before {
        transform: translateX(20px);
    }
    </style>

    <div class="dior-source-inner main-content" style="position: relative; z-index: 2;">
        
        <!-- Breadcrumb Header -->
        <div class="breadcrumb-main mb-3" style="position: relative; z-index: 5;">
            <div class="row align-items-center" style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin: 0;">
                <div style="flex: 0 0 auto;">
                    <div class="breadcrumb-title">
                        <h4 class="page-title d-flex align-items-center flex-wrap gap-2 m-0" style="font-size: 20px; font-weight: 700; color: #1e293b;">
                            <span>General Settings</span>
                        </h4>
                    </div>
                </div>
                <div style="flex: 0 0 auto;">
                    <ul class="breadcrumb-list" style="display: flex; justify-content: flex-end; align-items: center; gap: 8px; list-style: none; padding: 0; margin: 0; font-size: 12px !important; color: #94a3b8;">
                        <li class="dior-bc-item" style="font-size: 12px !important;">
                            <a href="javascript:void(0)" style="color: #2563eb !important; text-decoration: none !important; font-size: 12px !important;">
                                <i class="fa-solid fa-house" style="font-size: 12px !important;"></i>
                            </a>
                        </li>
                        <li style="color: #cbd5e1; font-size: 12px !important;">/</li>
                        <li class="dior-bc-item" style="font-size: 12px !important;">
                            <a href="javascript:void(0)" style="color: #64748b !important; text-decoration: none !important; font-size: 12px !important;">Settings</a>
                        </li>
                        <li style="color: #cbd5e1; font-size: 12px !important;">/</li>
                        <li class="dior-bc-item active" style="color: #475569 !important; font-weight: 600 !important; font-size: 12px !important;">
                            <span style="color: #475569 !important; font-weight: 600 !important; font-size: 12px !important;">General Settings</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="section-body">
            
            <!-- Top Profile Banner Card -->
            <div class="card profile-header-card mb-4 border-0 shadow-sm" style="border-radius: 12px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05); overflow: hidden; margin-bottom: 24px;">
                
                <!-- Blue Cover Banner -->
                <div class="profile-cover-gradient" style="height: 100px; background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 60%, #3b82f6 100%); width: 100%;"></div>
                
                <!-- Profile Details Body -->
                <div class="card-body" style="padding: 0 24px 20px 24px; position: relative; background: #ffffff;">
                    <div style="display: flex; align-items: flex-end; gap: 20px; margin-top: -45px; flex-wrap: wrap;">
                        
                        <!-- Avatar Wrapper -->
                        <div class="avatar-edit-wrapper" style="position: relative; width: 90px; height: 90px; flex-shrink: 0;">
                            <img alt="Doctor Avatar" class="profile-avatar-img" src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-1.png'); ?>">
                            <button type="button" title="Change Avatar" class="avatar-change-btn" style="position: absolute; bottom: 2px; right: 2px; background: #2563eb; color: #ffffff; border: 2px solid #ffffff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 5;">
                                <i class="fas fa-camera" style="font-size: 11px;"></i>
                            </button>
                        </div>
                        
                        <!-- User Information Text -->
                        <div style="flex: 1 1 0%; min-width: 250px; padding-bottom: 4px;">
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px;">
                                <h4 class="profile-user-name" style="font-weight: 700; color: #1e293b; font-size: 20px; margin: 0;">Dr. Sarah Smith</h4>
                                <span class="specialty-badge" style="background: #eff6ff; color: #2563eb; font-size: 11px; font-weight: 600; padding: 3px 12px; border-radius: 12px; display: inline-block;">Dermatology</span>
                                <span class="license-chip" style="background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 600; padding: 3px 12px; border-radius: 12px; display: inline-block;">#LIC-884920</span>
                                <span class="badge badge-emerald-pill" style="background: #dcfce7; color: #16a34a; font-size: 11px; font-weight: 600; padding: 3px 12px; border-radius: 12px; display: inline-flex; align-items: center;"><i class="fas fa-user-md" style="margin-right: 5px;"></i> Verified Specialist</span>
                            </div>
                            <div style="font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <span><i class="fas fa-graduation-cap" style="color: #2563eb !important; margin-right: 5px;"></i> MBBS, MD - Dermatology</span>
                                <span style="color: #cbd5e1;">&bull;</span>
                                <span><i class="far fa-envelope" style="color: #2563eb !important; margin-right: 5px;"></i> dr.sarah.smith@medidash.com</span>
                                <span style="color: #cbd5e1;">&bull;</span>
                                <span><i class="fas fa-money-bill-wave" style="color: #10b981 !important; margin-right: 5px;"></i> Consultation Fee: <strong style="color: #334155;">INR 500</strong></span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Two-Column Layout (Sidebar 25% + Main Form 75%) -->
            <div class="dior-settings-grid-row">
                
                <!-- Left Sidebar Nav -->
                <div class="dior-settings-col-sidebar">
                    <div class="card settings-nav-card border-0 shadow-sm" style="border-radius: 12px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05); padding: 14px;">
                        <div role="tablist" class="nav flex-column nav-pills custom-settings-pills" style="display: flex; flex-direction: column; gap: 8px;">
                            
                            <!-- Tab 1: Clinical Profile -->
                            <button type="button" class="nav-link text-start active dior-doc-nav-item" data-tab-pane="doc-tab-clinical" onclick="diorDocSelectSettingsTab(this, 'doc-tab-clinical')">
                                <i class="fas fa-user-md icon-tab text-primary" style="font-size: 18px; width: 24px; min-width: 24px; text-align: center; flex-shrink: 0; margin-right: 12px;"></i>
                                <div style="display: flex; flex-direction: column; flex: 1 1 auto; min-width: 0;">
                                    <span class="tab-title" style="font-weight: 700; font-size: 13px; display: block; line-height: 1.3;">Clinical Profile</span>
                                    <small class="tab-subtext" style="font-size: 11px; display: block; margin-top: 2px; line-height: 1.2;">Qualifications, fee &amp; location</small>
                                </div>
                            </button>

                            <!-- Tab 2: Security & Password -->
                            <button type="button" class="nav-link text-start dior-doc-nav-item" data-tab-pane="doc-tab-security" onclick="diorDocSelectSettingsTab(this, 'doc-tab-security')">
                                <i class="fas fa-shield-alt icon-tab text-danger" style="font-size: 18px; width: 24px; min-width: 24px; text-align: center; flex-shrink: 0; margin-right: 12px; color: #ef4444 !important;"></i>
                                <div style="display: flex; flex-direction: column; flex: 1 1 auto; min-width: 0;">
                                    <span class="tab-title" style="font-weight: 700; font-size: 13px; display: block; line-height: 1.3;">Security &amp; Password</span>
                                    <small class="tab-subtext" style="font-size: 11px; display: block; margin-top: 2px; line-height: 1.2;">Credentials, 2FA &amp; access</small>
                                </div>
                            </button>

                            <!-- Tab 3: Practice & Schedule -->
                            <button type="button" class="nav-link text-start dior-doc-nav-item" data-tab-pane="doc-tab-practice" onclick="diorDocSelectSettingsTab(this, 'doc-tab-practice')">
                                <i class="fas fa-calendar-check icon-tab text-purple" style="font-size: 18px; width: 24px; min-width: 24px; text-align: center; flex-shrink: 0; margin-right: 12px; color: #8b5cf6 !important;"></i>
                                <div style="display: flex; flex-direction: column; flex: 1 1 auto; min-width: 0;">
                                    <span class="tab-title" style="font-weight: 700; font-size: 13px; display: block; line-height: 1.3;">Practice &amp; Schedule</span>
                                    <small class="tab-subtext" style="font-size: 11px; display: block; margin-top: 2px; line-height: 1.2;">Slots, auto-confirm &amp; tele-visit</small>
                                </div>
                            </button>

                            <!-- Tab 4: Doctor Alerts -->
                            <button type="button" class="nav-link text-start dior-doc-nav-item" data-tab-pane="doc-tab-alerts" onclick="diorDocSelectSettingsTab(this, 'doc-tab-alerts')">
                                <i class="fas fa-bell icon-tab text-amber" style="font-size: 18px; width: 24px; min-width: 24px; text-align: center; flex-shrink: 0; margin-right: 12px; color: #f59e0b !important;"></i>
                                <div style="display: flex; flex-direction: column; flex: 1 1 auto; min-width: 0;">
                                    <span class="tab-title" style="font-weight: 700; font-size: 13px; display: block; line-height: 1.3;">Doctor Alerts</span>
                                    <small class="tab-subtext" style="font-size: 11px; display: block; margin-top: 2px; line-height: 1.2;">Patient booking &amp; EMR notifications</small>
                                </div>
                            </button>

                        </div>
                    </div>
                </div>

                <!-- Right Form Content Panes -->
                <div class="dior-settings-col-main">
                    
                    <!-- 1. Clinical Profile Tab Pane -->
                    <div class="card settings-form-card border-0 shadow-sm dior-doc-tab-pane" id="doc-tab-clinical" style="border-radius: 12px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05);">
                        <div class="card-header bg-transparent border-bottom py-3 px-4" style="border-bottom: 1px solid #edf1f5 !important; padding: 16px 20px;">
                            <h5 class="card-title-text mb-0" style="font-size: 15px; font-weight: 700; color: #172033; display: inline-flex; align-items: center; margin-right: 8px;">
                                <i class="fas fa-stethoscope text-primary" style="color: #2563eb !important; margin-right: 8px;"></i>Clinical Profile &amp; Practitioner Details
                            </h5>
                            <p class="card-subtitle-text mb-0" style="font-size: 12px; color: #64748b; display: inline-block;">Update medical credentials, consultation pricing, and clinic schedule</p>
                        </div>
                        <div class="card-body p-4" style="padding: 24px;">
                            <form novalidate onsubmit="event.preventDefault();">
                                
                                <div class="dior-form-row-2">
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">First Name <span class="text-danger" style="color: #ef4444;">*</span></label>
                                        <div class="input-group search-input-group">
                                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                                            <input type="text" value="Sarah" placeholder="First Name" class="form-control">
                                        </div>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Last Name <span class="text-danger" style="color: #ef4444;">*</span></label>
                                        <div class="input-group search-input-group">
                                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                                            <input type="text" value="Smith" placeholder="Last Name" class="form-control">
                                        </div>
                                    </div>
                                </div>

                                <div class="dior-form-row-2">
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Medical Qualifications <span class="text-danger" style="color: #ef4444;">*</span></label>
                                        <div class="input-group search-input-group">
                                            <span class="input-group-text"><i class="fas fa-graduation-cap text-muted"></i></span>
                                            <input type="text" value="MBBS, MD - Dermatology" placeholder="e.g. MBBS, MD - Dermatology" class="form-control">
                                        </div>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Primary Specialty <span class="text-danger" style="color: #ef4444;">*</span></label>
                                        <select class="form-select" style="width: 100%; min-height: 40px; border: 1px solid #dce4ec; border-radius: 6px; padding: 8px 12px; font-size: 13px; color: #1e293b; background: #ffffff;">
                                            <option value="Dermatology" selected>Dermatology</option>
                                            <option value="Dentistry">Dentistry</option>
                                            <option value="Orthopedics">Orthopedics</option>
                                            <option value="General Surgery">General Surgery</option>
                                            <option value="Cardiology">Cardiology</option>
                                            <option value="Pediatrics">Pediatrics</option>
                                            <option value="Neurology">Neurology</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="dior-form-row-3">
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Medical License No. <span class="text-danger" style="color: #ef4444;">*</span></label>
                                        <input type="text" value="LIC-884920" placeholder="LIC-XXXXXX" class="form-control" style="border-radius: 6px;">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Professional Email <span class="text-danger" style="color: #ef4444;">*</span></label>
                                        <div class="input-group search-input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                            <input type="email" value="dr.sarah.smith@medidash.com" placeholder="doctor@domain.com" class="form-control">
                                        </div>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Mobile Contact <span class="text-danger" style="color: #ef4444;">*</span></label>
                                        <div class="input-group search-input-group">
                                            <span class="input-group-text"><i class="fas fa-phone text-muted"></i></span>
                                            <input type="text" value="+1 (234) 987-6543" placeholder="+1 (234) 000-0000" class="form-control">
                                        </div>
                                    </div>
                                </div>

                                <div class="dior-form-row-2">
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Consultation Fee <span class="text-danger" style="color: #ef4444;">*</span></label>
                                        <div class="input-group search-input-group">
                                            <span class="input-group-text"><i class="fas fa-money-bill-wave text-success" style="color: #10b981 !important;"></i></span>
                                            <input type="text" value="INR 500" placeholder="INR 500" class="form-control">
                                        </div>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Available Duty Hours <span class="text-danger" style="color: #ef4444;">*</span></label>
                                        <div class="input-group search-input-group">
                                            <span class="input-group-text"><i class="far fa-clock text-primary" style="color: #2563eb !important;"></i></span>
                                            <input type="text" value="MON - SAT 10:00 AM - 8:00 PM" placeholder="MON - SAT 10:00 AM - 8:00 PM" class="form-control">
                                        </div>
                                    </div>
                                </div>

                                <div class="dior-form-row-3">
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">City</label>
                                        <input type="text" value="New York" placeholder="City" class="form-control" style="border-radius: 6px;">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Country</label>
                                        <input type="text" value="United States" placeholder="Country" class="form-control" style="border-radius: 6px;">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Clinic Address</label>
                                        <input type="text" value="Shanti Nagar Bldg No B 4, Sector No 6, Mira Road" placeholder="Clinic / Hospital location" class="form-control" style="border-radius: 6px;">
                                    </div>
                                </div>

                                <div class="dior-form-group" style="margin-bottom: 20px;">
                                    <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Clinical Experience &amp; Bio Summary</label>
                                    <textarea rows="3" placeholder="Brief summary of clinical expertise, specializations, and patient care philosophy" class="form-control" style="border-radius: 6px; resize: vertical; min-height: 80px;">Senior Dermatologist with 14+ years of clinical expertise in advanced skin treatments, laser therapy, and cosmetic dermatology.</textarea>
                                </div>

                                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 14px; border-top: 1px solid #edf1f5 !important;">
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 18px; font-weight: 600; font-size: 13px; cursor: pointer;">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-primary-gradient px-4" style="background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 8px 22px; font-weight: 600; font-size: 13px; box-shadow: 0 4px 10px rgba(37,99,235,0.25); cursor: pointer;">
                                        <i class="fas fa-save" style="margin-right: 5px;"></i> Save Clinical Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 2. Security & Password Tab Pane -->
                    <div class="card settings-form-card border-0 shadow-sm dior-doc-tab-pane" id="doc-tab-security" style="display: none; border-radius: 12px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05);">
                        <div class="card-header bg-transparent border-bottom py-3 px-4" style="border-bottom: 1px solid #edf1f5 !important; padding: 16px 20px;">
                            <h5 class="card-title-text mb-0" style="font-size: 15px; font-weight: 700; color: #172033; display: inline-flex; align-items: center; margin-right: 8px;">
                                <i class="fas fa-shield-alt text-danger" style="color: #ef4444 !important; margin-right: 8px;"></i>Security &amp; Password Settings
                            </h5>
                            <p class="card-subtitle-text mb-0" style="font-size: 12px; color: #64748b; display: inline-block;">Manage account login credentials, password updates &amp; authentication</p>
                        </div>
                        <div class="card-body p-4" style="padding: 24px;">
                            <form onsubmit="event.preventDefault();">
                                <div class="dior-form-group" style="margin-bottom: 16px;">
                                    <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Username</label>
                                    <div class="input-group search-input-group">
                                        <span class="input-group-text"><i class="fas fa-user-tag text-muted"></i></span>
                                        <input type="text" value="dr_sarah_smith" placeholder="Username" class="form-control">
                                    </div>
                                </div>
                                <div class="dior-form-row-2">
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Current Password</label>
                                        <div class="input-group search-input-group">
                                            <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                            <input type="password" placeholder="••••••••" class="form-control">
                                        </div>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">New Password</label>
                                        <div class="input-group search-input-group">
                                            <span class="input-group-text"><i class="fas fa-key text-muted"></i></span>
                                            <input type="password" placeholder="••••••••" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 14px; border-top: 1px solid #edf1f5 !important;">
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 18px; font-weight: 600; font-size: 13px;">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-primary-gradient px-4" style="background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 8px 22px; font-weight: 600; font-size: 13px;">Save Security Settings</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 3. Practice & Schedule Tab Pane -->
                    <div class="card settings-form-card border-0 shadow-sm dior-doc-tab-pane" id="doc-tab-practice" style="display: none; border-radius: 12px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05);">
                        <div class="card-header bg-transparent border-bottom py-3 px-4" style="border-bottom: 1px solid #edf1f5 !important; padding: 16px 20px;">
                            <h5 class="card-title-text mb-0" style="font-size: 15px; font-weight: 700; color: #172033; display: inline-flex; align-items: center; margin-right: 8px;">
                                <i class="fas fa-calendar-check text-purple" style="color: #8b5cf6 !important; margin-right: 8px;"></i>Practice &amp; Schedule Settings
                            </h5>
                            <p class="card-subtitle-text mb-0" style="font-size: 12px; color: #64748b; display: inline-block;">Configure appointment slots, auto-confirmation &amp; video visit preferences</p>
                        </div>
                        <div class="card-body p-4" style="padding: 24px;">
                            <form onsubmit="event.preventDefault();">
                                <div class="dior-form-row-2">
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Slot Duration</label>
                                        <select class="form-select" style="width: 100%; min-height: 40px; border: 1px solid #dce4ec; border-radius: 6px; padding: 8px 12px; font-size: 13px; color: #1e293b; background: #ffffff;">
                                            <option value="15">15 Minutes</option>
                                            <option value="30" selected>30 Minutes</option>
                                            <option value="45">45 Minutes</option>
                                            <option value="60">60 Minutes</option>
                                        </select>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #475569;">Auto-Confirm Bookings</label>
                                        <select class="form-select" style="width: 100%; min-height: 40px; border: 1px solid #dce4ec; border-radius: 6px; padding: 8px 12px; font-size: 13px; color: #1e293b; background: #ffffff;">
                                            <option value="enabled" selected>Enabled (Auto Accept)</option>
                                            <option value="disabled">Disabled (Manual Review Required)</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 14px; border-top: 1px solid #edf1f5 !important;">
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 18px; font-weight: 600; font-size: 13px;">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-primary-gradient px-4" style="background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 8px 22px; font-weight: 600; font-size: 13px;">Save Schedule Settings</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 4. Doctor Alerts Tab Pane -->
                    <div class="card settings-form-card border-0 shadow-sm dior-doc-tab-pane" id="doc-tab-alerts" style="display: none; border-radius: 12px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05);">
                        <div class="card-header bg-transparent border-bottom py-3 px-4" style="border-bottom: 1px solid #edf1f5 !important; padding: 16px 20px;">
                            <h5 class="card-title-text mb-0" style="font-size: 15px; font-weight: 700; color: #172033; display: inline-flex; align-items: center; margin-right: 8px;">
                                <i class="fas fa-bell text-amber" style="color: #f59e0b !important; margin-right: 8px;"></i>Doctor Notification Alerts
                            </h5>
                            <p class="card-subtitle-text mb-0" style="font-size: 12px; color: #64748b; display: inline-block;">Set alerts for new patient bookings, cancellations, and EMR records</p>
                        </div>
                        <div class="card-body p-4" style="padding: 24px;">
                            <form onsubmit="event.preventDefault();">
                                <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 20px;">
                                    
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;">
                                        <div>
                                            <h6 style="margin: 0 0 3px; font-weight: 700; color: #1e293b; font-size: 13px;">Email Notification for New Bookings</h6>
                                            <small style="color: #64748b; font-size: 11px;">Send email alerts when a patient schedules an appointment.</small>
                                        </div>
                                        <label class="dior-toggle-switch">
                                            <input type="checkbox" checked>
                                            <span class="dior-toggle-slider"></span>
                                        </label>
                                    </div>

                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;">
                                        <div>
                                            <h6 style="margin: 0 0 3px; font-weight: 700; color: #1e293b; font-size: 13px;">SMS Reminders for Emergency Consultations</h6>
                                            <small style="color: #64748b; font-size: 11px;">Send high-priority SMS notifications for instant telemedicine requests.</small>
                                        </div>
                                        <label class="dior-toggle-switch">
                                            <input type="checkbox" checked>
                                            <span class="dior-toggle-slider"></span>
                                        </label>
                                    </div>

                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 14px; border-top: 1px solid #edf1f5 !important;">
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 18px; font-weight: 600; font-size: 13px;">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-primary-gradient px-4" style="background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 8px 22px; font-weight: 600; font-size: 13px;">Save Alert Preferences</button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- Tab Switching Script -->
<script>
function diorDocSelectSettingsTab(btnElement, paneId) {
    const navItems = document.querySelectorAll('#tab-doc-settings .dior-doc-nav-item');
    navItems.forEach(item => item.classList.remove('active'));
    btnElement.classList.add('active');

    const panes = document.querySelectorAll('#tab-doc-settings .dior-doc-tab-pane');
    panes.forEach(pane => pane.style.display = 'none');

    const target = document.getElementById(paneId);
    if (target) {
        target.style.display = 'block';
    }
}
</script>
