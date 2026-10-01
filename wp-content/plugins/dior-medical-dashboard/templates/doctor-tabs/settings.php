<section class="dior-tab-panel" id="tab-doc-settings" style="display: none; background-color: #f8fafc; padding: 20px;">
    <!-- Breadcrumb -->
    <div class="breadcrumb-main mb-3">
        <div class="row align-items-center">
            <div class="col-6">
                <div class="breadcrumb-title">
                    <h4 class="page-title d-flex align-items-center flex-wrap gap-2 m-0" style="font-size: 18px; font-weight: 700; color: #1e293b;">
                        <span>General Settings</span>
                    </h4>
                </div>
            </div>
            <div class="col-6">
                <ul class="breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center; justify-content: flex-end; font-size: 12px;">
                    <li class="breadcrumb-item bcrumb-1">
                        <a href="javascript:void(0)" style="color: #3b82f6; text-decoration: none;">
                            <i class="fa-solid fa-house"></i>
                        </a>
                    </li>
                    <li style="color: #cbd5e1;">/</li>
                    <li class="breadcrumb-item"><a href="javascript:void(0)" style="color: #64748b; text-decoration: none;">Settings</a></li>
                    <li style="color: #cbd5e1;">/</li>
                    <li class="breadcrumb-item active" style="color: #475569; font-weight: 600;">General Settings</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="section-body">
        <!-- Profile Header Card -->
        <div class="card profile-header-card mb-4 border-0 shadow-sm overflow-hidden" style="border-radius: 12px; background: #ffffff;">
            <div class="profile-cover-gradient" style="height: 100px; background: linear-gradient(90deg, #1d4ed8 0%, #3b82f6 60%, #2563eb 100%);"></div>
            <div class="card-body p-4 pt-0 position-relative">
                <div class="row align-items-end g-3" style="margin-top: -35px;">
                    <div class="col-auto">
                        <div class="avatar-edit-wrapper position-relative" style="width: 84px; height: 84px;">
                            <img alt="Doctor Avatar" class="profile-avatar-img rounded-circle border border-4 border-white shadow-sm" src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-1.png'); ?>" style="width: 84px; height: 84px; object-fit: cover; background: #ffffff; border: 4px solid #ffffff !important;">
                            <button type="button" title="Change Avatar" class="avatar-change-btn rounded-circle" style="position: absolute; bottom: 2px; right: 2px; background: #3b82f6; color: #ffffff; border: 2px solid #ffffff; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.15);">
                                <i class="fas fa-camera" style="font-size: 10px;"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col pb-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h4 class="profile-user-name mb-0" style="font-weight: 700; color: #1e293b; font-size: 18px;">Dr. Sarah Smith</h4>
                            <span class="specialty-badge" style="background: #eff6ff; color: #3b82f6; font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 12px;">Dermatology</span>
                            <span class="license-chip" style="background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 12px;">#LIC-884920</span>
                            <span class="badge badge-emerald-pill" style="background: #dcfce7; color: #16a34a; font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 12px;"><i class="fas fa-user-md me-1"></i> Verified Specialist</span>
                        </div>
                        <p class="text-xs text-muted mb-0 mt-1" style="font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span><i class="fas fa-graduation-cap me-1 text-primary" style="color: #3b82f6 !important;"></i> MBBS, MD - Dermatology</span>
                            <span style="color: #cbd5e1;">&bull;</span>
                            <span><i class="far fa-envelope me-1 text-primary" style="color: #3b82f6 !important;"></i> dr.sarah.smith@medidash.com</span>
                            <span style="color: #cbd5e1;">&bull;</span>
                            <span><i class="fas fa-money-bill-wave me-1 text-success" style="color: #10b981 !important;"></i> Consultation Fee: <strong style="color: #334155;">INR 500</strong></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar + Form Layout -->
        <div class="row g-4">
            <!-- Sidebar Nav (3 Columns) -->
            <div class="col-xl-3 col-lg-4 col-md-12">
                <div class="card settings-nav-card border-0 shadow-sm p-3" style="border-radius: 12px; background: #ffffff;">
                    <div role="tablist" class="nav flex-column nav-pills custom-settings-pills">
                        
                        <button type="button" class="nav-link text-start active dior-doc-nav-item" data-tab-pane="doc-tab-clinical" onclick="diorDocSelectSettingsTab(this, 'doc-tab-clinical')">
                            <i class="fas fa-user-md me-3 icon-tab text-primary" style="font-size: 18px; width: 20px; text-align: center;"></i>
                            <div>
                                <span class="tab-title d-block" style="font-weight: 600; font-size: 13px;">Clinical Profile</span>
                                <small class="tab-subtext" style="font-size: 11px;">Qualifications, fee &amp; location</small>
                            </div>
                        </button>

                        <button type="button" class="nav-link text-start dior-doc-nav-item" data-tab-pane="doc-tab-security" onclick="diorDocSelectSettingsTab(this, 'doc-tab-security')">
                            <i class="fas fa-shield-alt me-3 icon-tab text-danger" style="font-size: 18px; width: 20px; text-align: center; color: #ef4444 !important;"></i>
                            <div>
                                <span class="tab-title d-block" style="font-weight: 600; font-size: 13px;">Security &amp; Password</span>
                                <small class="tab-subtext" style="font-size: 11px;">Credentials, 2FA &amp; access</small>
                            </div>
                        </button>

                        <button type="button" class="nav-link text-start dior-doc-nav-item" data-tab-pane="doc-tab-practice" onclick="diorDocSelectSettingsTab(this, 'doc-tab-practice')">
                            <i class="fas fa-calendar-check me-3 icon-tab text-purple" style="font-size: 18px; width: 20px; text-align: center; color: #8b5cf6 !important;"></i>
                            <div>
                                <span class="tab-title d-block" style="font-weight: 600; font-size: 13px;">Practice &amp; Schedule</span>
                                <small class="tab-subtext" style="font-size: 11px;">Slots, auto-confirm &amp; tele-visit</small>
                            </div>
                        </button>

                        <button type="button" class="nav-link text-start dior-doc-nav-item" data-tab-pane="doc-tab-alerts" onclick="diorDocSelectSettingsTab(this, 'doc-tab-alerts')">
                            <i class="fas fa-bell me-3 icon-tab text-amber" style="font-size: 18px; width: 20px; text-align: center; color: #f59e0b !important;"></i>
                            <div>
                                <span class="tab-title d-block" style="font-weight: 600; font-size: 13px;">Doctor Alerts</span>
                                <small class="tab-subtext" style="font-size: 11px;">Patient booking &amp; EMR notifications</small>
                            </div>
                        </button>

                    </div>
                </div>
            </div>

            <!-- Content Area (9 Columns) -->
            <div class="col-xl-9 col-lg-8 col-md-12">
                
                <!-- 1. Clinical Profile Tab Pane -->
                <div class="card settings-form-card border-0 shadow-sm dior-doc-tab-pane" id="doc-tab-clinical" style="border-radius: 12px; background: #ffffff;">
                    <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex align-items-baseline gap-2 flex-wrap" style="border-color: #f1f5f9 !important;">
                        <h5 class="card-title-text mb-0" style="font-size: 15px; font-weight: 700; color: #1e293b;">
                            <i class="fas fa-stethoscope me-2 text-primary" style="color: #3b82f6 !important;"></i>Clinical Profile &amp; Practitioner Details
                        </h5>
                        <p class="card-subtitle-text mb-0" style="font-size: 12px; color: #64748b;">Update medical credentials, consultation pricing, and clinic schedule</p>
                    </div>
                    <div class="card-body p-4">
                        <form novalidate class="ng-untouched ng-pristine ng-valid" onsubmit="event.preventDefault();">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">First Name <span class="text-danger">*</span></label>
                                    <div class="input-group search-input-group">
                                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                                        <input type="text" value="Sarah" placeholder="First Name" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Last Name <span class="text-danger">*</span></label>
                                    <div class="input-group search-input-group">
                                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                                        <input type="text" value="Smith" placeholder="Last Name" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Medical Qualifications <span class="text-danger">*</span></label>
                                    <div class="input-group search-input-group">
                                        <span class="input-group-text"><i class="fas fa-graduation-cap text-muted"></i></span>
                                        <input type="text" value="MBBS, MD - Dermatology" placeholder="e.g. MBBS, MD - Dermatology" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Primary Specialty <span class="text-danger">*</span></label>
                                    <select class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px; padding: 8px 12px;">
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

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Medical License No. <span class="text-danger">*</span></label>
                                    <input type="text" value="LIC-884920" placeholder="LIC-XXXXXX" class="form-control" style="border-radius: 6px;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Professional Email <span class="text-danger">*</span></label>
                                    <div class="input-group search-input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                        <input type="email" value="dr.sarah.smith@medidash.com" placeholder="doctor@domain.com" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Mobile Contact <span class="text-danger">*</span></label>
                                    <div class="input-group search-input-group">
                                        <span class="input-group-text"><i class="fas fa-phone text-muted"></i></span>
                                        <input type="text" value="+1 (234) 987-6543" placeholder="+1 (234) 000-0000" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Consultation Fee <span class="text-danger">*</span></label>
                                    <div class="input-group search-input-group">
                                        <span class="input-group-text"><i class="fas fa-money-bill-wave text-success" style="color: #10b981 !important;"></i></span>
                                        <input type="text" value="INR 500" placeholder="INR 500" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Available Duty Hours <span class="text-danger">*</span></label>
                                    <div class="input-group search-input-group">
                                        <span class="input-group-text"><i class="far fa-clock text-primary" style="color: #3b82f6 !important;"></i></span>
                                        <input type="text" value="MON - SAT 10:00 AM - 8:00 PM" placeholder="MON - SAT 10:00 AM - 8:00 PM" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">City</label>
                                    <input type="text" value="New York" placeholder="City" class="form-control" style="border-radius: 6px;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Country</label>
                                    <input type="text" value="United States" placeholder="Country" class="form-control" style="border-radius: 6px;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Clinic Address</label>
                                    <input type="text" value="Shanti Nagar Bldg No B 4, Sector No 6, Mira Road" placeholder="Clinic / Hospital location" class="form-control" style="border-radius: 6px;">
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-12">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Clinical Experience &amp; Bio Summary</label>
                                    <textarea rows="3" placeholder="Brief summary of clinical expertise, specializations, and patient care philosophy" class="form-control" style="border-radius: 6px; resize: vertical;">Senior Dermatologist with 14+ years of clinical expertise in advanced skin treatments, laser therapy, and cosmetic dermatology.</textarea>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 pt-2 border-top" style="border-color: #f1f5f9 !important;">
                                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 6px; font-weight: 600; font-size: 13px;">Cancel</button>
                                <button type="submit" class="btn btn-sm btn-primary-gradient px-4" style="background: #3b82f6; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; color: #ffffff; box-shadow: 0 4px 10px rgba(59,130,246,0.3);">
                                    <i class="fas fa-save me-1"></i> Save Clinical Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 2. Security & Password Tab Pane -->
                <div class="card settings-form-card border-0 shadow-sm dior-doc-tab-pane" id="doc-tab-security" style="display: none; border-radius: 12px; background: #ffffff;">
                    <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex align-items-baseline gap-2 flex-wrap" style="border-color: #f1f5f9 !important;">
                        <h5 class="card-title-text mb-0" style="font-size: 15px; font-weight: 700; color: #1e293b;">
                            <i class="fas fa-shield-alt me-2 text-danger" style="color: #ef4444 !important;"></i>Security &amp; Password Settings
                        </h5>
                        <p class="card-subtitle-text mb-0" style="font-size: 12px; color: #64748b;">Manage account login credentials, password updates &amp; authentication</p>
                    </div>
                    <div class="card-body p-4">
                        <form onsubmit="event.preventDefault();">
                            <div class="row g-3 mb-3">
                                <div class="col-md-12">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Username</label>
                                    <div class="input-group search-input-group">
                                        <span class="input-group-text"><i class="fas fa-user-tag text-muted"></i></span>
                                        <input type="text" value="dr_sarah_smith" placeholder="Username" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Current Password</label>
                                    <div class="input-group search-input-group">
                                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                        <input type="password" placeholder="••••••••" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">New Password</label>
                                    <div class="input-group search-input-group">
                                        <span class="input-group-text"><i class="fas fa-key text-muted"></i></span>
                                        <input type="password" placeholder="••••••••" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 pt-2 border-top" style="border-color: #f1f5f9 !important;">
                                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 6px; font-weight: 600; font-size: 13px;">Cancel</button>
                                <button type="submit" class="btn btn-sm btn-primary-gradient px-4" style="background: #3b82f6; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; color: #ffffff;">
                                    <i class="fas fa-save me-1"></i> Save Security Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 3. Practice & Schedule Tab Pane -->
                <div class="card settings-form-card border-0 shadow-sm dior-doc-tab-pane" id="doc-tab-practice" style="display: none; border-radius: 12px; background: #ffffff;">
                    <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex align-items-baseline gap-2 flex-wrap" style="border-color: #f1f5f9 !important;">
                        <h5 class="card-title-text mb-0" style="font-size: 15px; font-weight: 700; color: #1e293b;">
                            <i class="fas fa-calendar-check me-2 text-purple" style="color: #8b5cf6 !important;"></i>Practice &amp; Schedule Settings
                        </h5>
                        <p class="card-subtitle-text mb-0" style="font-size: 12px; color: #64748b;">Configure appointment slots, auto-confirmation &amp; video visit preferences</p>
                    </div>
                    <div class="card-body p-4">
                        <form onsubmit="event.preventDefault();">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Slot Duration</label>
                                    <select class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px; padding: 8px 12px;">
                                        <option value="15">15 Minutes</option>
                                        <option value="30" selected>30 Minutes</option>
                                        <option value="45">45 Minutes</option>
                                        <option value="60">60 Minutes</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-600 text-xs" style="font-size: 12px; font-weight: 600; color: #334155;">Auto-Confirm Bookings</label>
                                    <select class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px; padding: 8px 12px;">
                                        <option value="enabled" selected>Enabled (Auto Accept)</option>
                                        <option value="disabled">Disabled (Manual Review Required)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 pt-2 border-top" style="border-color: #f1f5f9 !important;">
                                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 6px; font-weight: 600; font-size: 13px;">Cancel</button>
                                <button type="submit" class="btn btn-sm btn-primary-gradient px-4" style="background: #3b82f6; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; color: #ffffff;">
                                    <i class="fas fa-save me-1"></i> Save Schedule Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 4. Doctor Alerts Tab Pane -->
                <div class="card settings-form-card border-0 shadow-sm dior-doc-tab-pane" id="doc-tab-alerts" style="display: none; border-radius: 12px; background: #ffffff;">
                    <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex align-items-baseline gap-2 flex-wrap" style="border-color: #f1f5f9 !important;">
                        <h5 class="card-title-text mb-0" style="font-size: 15px; font-weight: 700; color: #1e293b;">
                            <i class="fas fa-bell me-2 text-amber" style="color: #f59e0b !important;"></i>Doctor Notification Alerts
                        </h5>
                        <p class="card-subtitle-text mb-0" style="font-size: 12px; color: #64748b;">Set alerts for new patient bookings, cancellations, and EMR records</p>
                    </div>
                    <div class="card-body p-4">
                        <form onsubmit="event.preventDefault();">
                            <div class="row g-3 mb-4">
                                <div class="col-12">
                                    <div class="d-flex align-items-center justify-content-between p-3 rounded" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                        <div>
                                            <h6 class="mb-1" style="font-weight: 600; color: #1e293b; font-size: 13px;">Email Notification for New Bookings</h6>
                                            <small style="color: #64748b; font-size: 11px;">Send email alerts when a patient schedules an appointment.</small>
                                        </div>
                                        <input class="form-check-input" type="checkbox" checked style="width: 36px; height: 20px; cursor: pointer;">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-center justify-content-between p-3 rounded" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                        <div>
                                            <h6 class="mb-1" style="font-weight: 600; color: #1e293b; font-size: 13px;">SMS Reminders for Emergency Consultations</h6>
                                            <small style="color: #64748b; font-size: 11px;">Send high-priority SMS notifications for instant telemedicine requests.</small>
                                        </div>
                                        <input class="form-check-input" type="checkbox" checked style="width: 36px; height: 20px; cursor: pointer;">
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 pt-2 border-top" style="border-color: #f1f5f9 !important;">
                                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 6px; font-weight: 600; font-size: 13px;">Cancel</button>
                                <button type="submit" class="btn btn-sm btn-primary-gradient px-4" style="background: #3b82f6; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; color: #ffffff;">
                                    <i class="fas fa-save me-1"></i> Save Alert Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- Custom Styles matching user HTML & Screenshot -->
<style>
.custom-settings-pills .dior-doc-nav-item {
    background: transparent;
    color: #475569 !important;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 8px;
    display: flex;
    align-items: flex-start;
    border: none;
    width: 100%;
    transition: all 0.2s ease;
}
.custom-settings-pills .dior-doc-nav-item:hover {
    background: #f8fafc !important;
    color: #1e293b !important;
}
.custom-settings-pills .dior-doc-nav-item.active {
    background: #3b82f6 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.25);
}
.custom-settings-pills .dior-doc-nav-item.active .tab-title,
.custom-settings-pills .dior-doc-nav-item.active .icon-tab {
    color: #ffffff !important;
}
.custom-settings-pills .dior-doc-nav-item.active .tab-subtext {
    color: rgba(255, 255, 255, 0.85) !important;
}
.custom-settings-pills .dior-doc-nav-item .tab-subtext {
    color: #94a3b8;
}

/* Form Group & Input Styling */
.search-input-group {
    display: flex;
}
.search-input-group .input-group-text {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-right: none;
    color: #64748b;
    border-top-left-radius: 6px;
    border-bottom-left-radius: 6px;
    padding: 8px 12px;
    font-size: 13px;
}
.search-input-group .form-control {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    border-top-right-radius: 6px;
    border-bottom-right-radius: 6px;
}
.settings-form-card .form-control {
    border: 1px solid #cbd5e1;
    padding: 8px 12px;
    font-size: 13px;
    color: #1e293b;
    background-color: #ffffff;
}
.settings-form-card .form-control:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
</style>

<!-- JS for Tab Switching -->
<script>
function diorDocSelectSettingsTab(btnElement, paneId) {
    // Reset active buttons
    const navItems = document.querySelectorAll('.dior-doc-nav-item');
    navItems.forEach(item => item.classList.remove('active'));
    btnElement.classList.add('active');

    // Hide all tab panes
    const panes = document.querySelectorAll('.dior-doc-tab-pane');
    panes.forEach(pane => pane.style.display = 'none');

    // Show target pane
    const target = document.getElementById(paneId);
    if (target) {
        target.style.display = 'block';
    }
}
</script>
