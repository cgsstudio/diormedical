<<<<<<< HEAD
<section class="dior-tab-panel dior-source-panel" id="tab-settings" style="display: none; background-color: #f8fafc; padding: 20px; position: relative; overflow: hidden;">
    <?php
    $settings_patient_user = wp_get_current_user();
    $settings_patient_profile = Dior_Patient_Portal_Data::get_patient_profile($settings_patient_user->ID);
    $settings_patient_avatar = !empty($settings_patient_profile['avatar_url'])
        ? $settings_patient_profile['avatar_url']
        : get_avatar_url($settings_patient_user->ID, ['size' => 180]);
    ?>
    
    <!-- Watermark & Breadcrumb Font Size Fix -->
    <style>
    #tab-settings .dior-page-watermark,
    #tab-settings .page-watermark,
    #tab-settings .dior-big-title,
    .dior-patient-portal-wrap #tab-settings .dior-page-watermark,
    .dior-patient-portal-wrap #tab-settings .page-watermark,
    .dior-patient-portal-wrap #tab-settings .dior-big-title {
        display: none !important;
        opacity: 0 !important;
        visibility: hidden !important;
    }
    
    /* Prevent external theme .breadcrumb-item styles from inflating text size */
    #tab-settings .breadcrumb-list,
    #tab-settings .breadcrumb-list li,
    #tab-settings .breadcrumb-list a,
    #tab-settings .breadcrumb-list span,
    #tab-settings .breadcrumb-list i {
        font-size: 12px !important;
        line-height: 1.2 !important;
    }
    #tab-settings .breadcrumb-list li::before,
    #tab-settings .breadcrumb-list li::after {
        display: none !important;
        content: none !important;
    }

    #tab-settings img.profile-avatar-img {
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

    #tab-settings .profile-cover-gradient {
        height: 100px !important;
        background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 60%, #3b82f6 100%) !important;
        width: 100% !important;
        border-radius: 12px 12px 0 0 !important;
    }

    /* Sidebar nav pills text vertical stacking */
    #tab-settings .custom-settings-pills .dior-patient-nav-item {
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
    #tab-settings .custom-settings-pills .dior-patient-nav-item:hover {
        background: #f8fafc !important;
        color: #1e293b !important;
    }
    #tab-settings .custom-settings-pills .dior-patient-nav-item.active {
        background: #2563eb !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }
    #tab-settings .custom-settings-pills .dior-patient-nav-item.active .tab-title,
    #tab-settings .custom-settings-pills .dior-patient-nav-item.active .icon-tab {
        color: #ffffff !important;
    }
    #tab-settings .custom-settings-pills .dior-patient-nav-item.active .tab-subtext {
        color: rgba(255, 255, 255, 0.85) !important;
    }

    /* Form Grid Layout */
    #tab-settings .dior-settings-grid-row {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        width: 100%;
    }
    #tab-settings .dior-settings-col-sidebar {
        flex: 0 0 calc(25% - 15px);
        max-width: calc(25% - 15px);
        width: calc(25% - 15px);
    }
    #tab-settings .dior-settings-col-main {
        flex: 0 0 calc(75% - 5px);
        max-width: calc(75% - 5px);
        width: calc(75% - 5px);
    }
    @media (max-width: 992px) {
        #tab-settings .dior-settings-col-sidebar,
        #tab-settings .dior-settings-col-main {
            flex: 0 0 100% !important;
            max-width: 100% !important;
            width: 100% !important;
        }
    }

    #tab-settings .dior-form-row-2 {
        display: flex;
        gap: 16px;
        margin-bottom: 16px;
    }
    #tab-settings .dior-form-row-2 > .dior-form-group {
        flex: 1 1 50%;
        min-width: 0;
    }
    #tab-settings .dior-form-row-3 {
        display: flex;
        gap: 16px;
        margin-bottom: 16px;
    }
    #tab-settings .dior-form-row-3 > .dior-form-group {
        flex: 1 1 33.333%;
        min-width: 0;
    }
    @media (max-width: 768px) {
        #tab-settings .dior-form-row-2,
        #tab-settings .dior-form-row-3 {
            flex-direction: column;
            gap: 12px;
        }
    }

    #tab-settings .search-input-group {
        display: flex;
        width: 100%;
    }
    #tab-settings .search-input-group .input-group-text {
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
    #tab-settings .search-input-group .form-control {
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
        border-top-right-radius: 6px !important;
        border-bottom-right-radius: 6px !important;
    }
    #tab-settings .settings-form-card .form-control {
        width: 100%;
        min-height: 40px;
        border: 1px solid #dce4ec;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 13px;
        color: #1e293b;
        background-color: #ffffff;
    }
    #tab-settings .settings-form-card .form-label {
        display: block;
        margin-bottom: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
    }
    #tab-settings .settings-form-card .form-select {
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
    #tab-settings .dior-toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
        flex-shrink: 0;
    }
    #tab-settings .dior-toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    #tab-settings .dior-toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1;
        transition: .3s ease;
        border-radius: 24px;
    }
    #tab-settings .dior-toggle-slider:before {
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
    #tab-settings .dior-toggle-switch input:checked + .dior-toggle-slider {
        background-color: #2563eb;
    }
    #tab-settings .dior-toggle-switch input:checked + .dior-toggle-slider:before {
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
                            <span>Patient Settings</span>
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
                            <span style="color: #475569 !important; font-weight: 600 !important; font-size: 12px !important;">Patient Settings</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="section-body">
            
            <div class="card profile-header-card mb-4 border-0 shadow-sm" style="border-radius: 12px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05); overflow: hidden; margin-bottom: 24px;">
                <div class="profile-cover-gradient" style="height: 100px; background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 60%, #3b82f6 100%); width: 100%;"></div>
                <div class="card-body" style="padding: 0 24px 20px; position: relative; background: #ffffff;">
                    <div style="display: flex; align-items: flex-end; gap: 20px; margin-top: -45px; flex-wrap: wrap;">
                        <div class="avatar-edit-wrapper" style="position: relative; width: 90px; height: 90px; flex-shrink: 0;">
                            <img id="dior-avatar-display-img" alt="Your profile photo" class="profile-avatar-img" src="<?php echo esc_url($settings_patient_avatar); ?>">
                            <button type="button" title="Change profile photo" aria-label="Choose profile photo" class="avatar-change-btn" onclick="diorPatientOpenMediaLibrary()" style="position: absolute; bottom: 2px; right: 2px; background: #2563eb; color: #ffffff; border: 2px solid #ffffff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 5;">
                                <i class="fas fa-camera" style="font-size: 11px;"></i>
                            </button>
                        </div>
                        <div style="flex: 1 1 0%; min-width: 250px; padding-bottom: 4px;">
                            <h4 class="profile-user-name dior-settings-profile-name" style="font-weight: 700; color: #1e293b; font-size: 20px; margin: 0 0 8px;"><?php echo esc_html($settings_patient_profile['full_name'] ?? $settings_patient_user->display_name); ?></h4>
                            <div style="font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <span><i class="fas fa-id-card" style="color: #2563eb !important; margin-right: 5px;"></i> Patient ID: <?php echo esc_html($settings_patient_profile['patient_id'] ?? ''); ?></span>
                                <span><i class="far fa-envelope" style="color: #2563eb !important; margin-right: 5px;"></i> <span class="dior-settings-profile-email"><?php echo esc_html($settings_patient_profile['email'] ?? $settings_patient_user->user_email); ?></span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dior-settings-grid-row" style="display: block;">
                <div class="dior-settings-col-main" style="width: 100%; max-width: 100%; flex: 1 1 100%;">
                    
                    <div class="card settings-form-card border-0 shadow-sm" id="patient-profile-settings-card" style="border-radius: 12px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05);">
                        <div class="card-header bg-transparent border-bottom py-3 px-4" style="border-bottom: 1px solid #edf1f5 !important; padding: 16px 20px;">
                            <h5 class="card-title-text mb-0" style="font-size: 15px; font-weight: 700; color: #172033; display: inline-flex; align-items: center; margin-right: 8px;">
                                <i class="fas fa-user text-primary" style="color: #2563eb !important; margin-right: 8px;"></i>Personal Information
                            </h5>
                            <p class="card-subtitle-text mb-0" style="font-size: 12px; color: #64748b; display: inline-block;">Manage your contact, health, emergency contact, and pharmacy details.</p>
                        </div>
                        <div class="card-body p-4" style="padding: 24px;">
                            <form id="dior-profile-settings-form" novalidate>
                                <h6 style="margin: 0 0 16px; color: #2b70b8; font-size: 14px; font-weight: 700;">Personal Details</h6>
                                <div class="dior-form-row-2">
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-first-name">First name *</label>
                                        <input id="patient-settings-first-name" class="form-control" type="text" name="first_name" value="<?php echo esc_attr($settings_patient_profile['first_name'] ?? ''); ?>" autocomplete="given-name" required>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-last-name">Last name *</label>
                                        <input id="patient-settings-last-name" class="form-control" type="text" name="last_name" value="<?php echo esc_attr($settings_patient_profile['last_name'] ?? ''); ?>" autocomplete="family-name" required>
                                    </div>
                                </div>
                                <div class="dior-form-row-2">
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-email">Email address</label>
                                        <input id="patient-settings-email" class="form-control" type="email" name="email" value="<?php echo esc_attr($settings_patient_profile['email'] ?? $settings_patient_user->user_email); ?>" autocomplete="email">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-phone">Phone number *</label>
                                        <input id="patient-settings-phone" class="form-control" type="tel" name="phone" value="<?php echo esc_attr($settings_patient_profile['phone'] ?? ''); ?>" autocomplete="tel" required>
                                    </div>
                                </div>
                                <div class="dior-form-row-3">
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-dob">Date of birth *</label>
                                        <input id="patient-settings-dob" class="form-control" type="date" name="dob" value="<?php echo esc_attr($settings_patient_profile['dob'] ?? ''); ?>" required>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-gender">Gender *</label>
                                        <select id="patient-settings-gender" class="form-select" name="gender" required>
                                            <option value="">Select gender</option>
                                            <option value="Female" <?php selected($settings_patient_profile['gender'] ?? '', 'Female'); ?>>Female</option>
                                            <option value="Male" <?php selected($settings_patient_profile['gender'] ?? '', 'Male'); ?>>Male</option>
                                            <option value="Other" <?php selected($settings_patient_profile['gender'] ?? '', 'Other'); ?>>Other</option>
                                            <option value="Prefer not to say" <?php selected($settings_patient_profile['gender'] ?? '', 'Prefer not to say'); ?>>Prefer not to say</option>
                                        </select>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-blood-group">Blood group</label>
                                        <input id="patient-settings-blood-group" class="form-control" type="text" name="blood_group" value="<?php echo esc_attr($settings_patient_profile['blood_group'] ?? ''); ?>" placeholder="e.g. O+">
                                    </div>
                                </div>
                                <div class="dior-form-group" style="margin-bottom: 16px;">
                                    <label class="form-label" for="patient-settings-address">Mailing address *</label>
                                    <textarea id="patient-settings-address" class="form-control" name="address" rows="2" autocomplete="street-address" required><?php echo esc_textarea($settings_patient_profile['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="dior-form-row-2">
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-city">City</label>
                                        <input id="patient-settings-city" class="form-control" type="text" name="city" value="<?php echo esc_attr($settings_patient_profile['city'] ?? ''); ?>" autocomplete="address-level2">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-country">Country</label>
                                        <input id="patient-settings-country" class="form-control" type="text" name="country" value="<?php echo esc_attr($settings_patient_profile['country'] ?? ''); ?>" autocomplete="country-name">
                                    </div>
                                </div>

                                <h6 style="margin: 24px 0 16px; color: #2b70b8; font-size: 14px; font-weight: 700;">Emergency Contact</h6>
                                <div class="dior-form-row-3">
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-emergency-name">Contact name</label>
                                        <input id="patient-settings-emergency-name" class="form-control" type="text" name="emergency_name" value="<?php echo esc_attr($settings_patient_profile['emergency_name'] ?? ''); ?>">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-emergency-relation">Relationship</label>
                                        <input id="patient-settings-emergency-relation" class="form-control" type="text" name="emergency_relation" value="<?php echo esc_attr($settings_patient_profile['emergency_relation'] ?? ''); ?>">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-emergency-phone">Phone number</label>
                                        <input id="patient-settings-emergency-phone" class="form-control" type="tel" name="emergency_phone" value="<?php echo esc_attr($settings_patient_profile['emergency_phone'] ?? ''); ?>">
                                    </div>
                                </div>

                                <h6 style="margin: 24px 0 16px; color: #2b70b8; font-size: 14px; font-weight: 700;">Preferred Pharmacy</h6>
                                <div class="dior-form-row-2">
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-pharmacy-name">Pharmacy name</label>
                                        <input id="patient-settings-pharmacy-name" class="form-control" type="text" name="pharmacy_name" value="<?php echo esc_attr($settings_patient_profile['pharmacy_name'] ?? ''); ?>">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-pharmacy-phone">Pharmacy phone</label>
                                        <input id="patient-settings-pharmacy-phone" class="form-control" type="tel" name="pharmacy_phone" value="<?php echo esc_attr($settings_patient_profile['pharmacy_phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="dior-form-group" style="margin-bottom: 20px;">
                                    <label class="form-label" for="patient-settings-pharmacy-address">Pharmacy address</label>
                                    <input id="patient-settings-pharmacy-address" class="form-control" type="text" name="pharmacy_address" value="<?php echo esc_attr($settings_patient_profile['pharmacy_address'] ?? ''); ?>">
                                </div>

                                <div style="display: flex; justify-content: flex-end; padding-top: 16px; border-top: 1px solid #edf1f5;">
                                    <button type="submit" class="btn btn-sm btn-primary-gradient px-4" style="background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 8px 22px; font-weight: 600; font-size: 13px; cursor: pointer;">
                                        <i class="fas fa-save" style="margin-right: 5px;"></i> Save Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card settings-form-card border-0 shadow-sm" id="patient-password-reset-card" style="margin-top: 20px; border-radius: 12px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05);">
                        <div class="card-header bg-transparent border-bottom py-3 px-4" style="border-bottom: 1px solid #edf1f5 !important; padding: 16px 20px;">
                            <h5 class="card-title-text mb-0" style="font-size: 15px; font-weight: 700; color: #172033; display: inline-flex; align-items: center; margin-right: 8px;">
                                <i class="fas fa-lock" style="color: #2563eb !important; margin-right: 8px;"></i>Password &amp; Security
                            </h5>
                        </div>
                        <div class="card-body p-4" style="padding: 24px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                            <p style="margin: 0; color: #64748b; font-size: 13px;">Use the secure password reset page to create a new password for your account.</p>
                            <a class="btn btn-sm btn-primary-gradient px-4" href="<?php echo esc_url(wp_lostpassword_url()); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 9px 18px; font-weight: 600; font-size: 13px; line-height: 15px; text-decoration: none;">
                                <i class="fas fa-key"></i> Reset Password
                            </a>
                        </div>
                    </div>

                </div>
            </div>

=======
<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-settings">
    <div class="dior-ic-d8609c7118"></div>
    <div class="dior-ic-9617191b52">
        <div class="dior-ic-76db7de8db">
            <div class="dior-ic-7e521c6e89">
                <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150" class="dior-ic-7ec5fb6fb5">
                <button type="button" class="dior-ic-a7eae853cc"><i class="fa-solid fa-camera"></i></button>
            </div>
            <div class="dior-ic-afa5d6e3f7">
                <div class="dior-ic-3541347c41">
                    <h2 class="dior-ic-f1d74f4175"><?php echo esc_html($profile['full_name'] ?? ""); ?></h2>
                    <span class="dior-ic-e88fe40e66"><?php echo esc_html($profile['patient_id'] ?? ""); ?></span>
                    <span class="dior-ic-9cd0a88c9e"><i class="fa-solid fa-circle-check"></i> Verified Patient</span>
                </div>
                <div class="dior-ic-ec8976c154">
                    <i class="fa-regular fa-envelope dior-ic-cd3611ed55"></i> <?php echo esc_html($profile['email'] ?? ""); ?> &bull; <i class="fa-solid fa-droplet dior-ic-ccc79123cc"></i> Blood Group: <strong class="dior-ic-9e8ed370c5"><?php echo esc_html($profile['blood_group'] ?? "—"); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="dior-ic-8600368a08">
        <!-- Settings Nav -->
        <div class="dior-ic-fe46835042">
            <div class="dior-ic-668068c975">
                <a href="#" onclick="return false;" class="dior-ic-ef69a32719">
                    <i class="fa-solid fa-circle-user dior-ic-b842c7cb26"></i>
                    <div>
                        <span class="dior-ic-9fe27d5276">Account Profile</span>
                        <span class="dior-ic-cfbb453fcb">Personal details &amp; contact</span>
                    </div>
                </a>
                <a href="#" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;" class="dior-ic-b0831a707f">
                    <i class="fa-solid fa-shield-halved dior-ic-a546442247"></i>
                    <div>
                        <span class="dior-ic-cb8c321fbe">Security &amp; Password</span>
                        <span class="dior-ic-252d442b87">Password, 2FA &amp; credentials</span>
                    </div>
                </a>
                <a href="#" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;" class="dior-ic-b0831a707f">
                    <i class="fa-solid fa-bell dior-ic-a546442247"></i>
                    <div>
                        <span class="dior-ic-cb8c321fbe">Notifications</span>
                        <span class="dior-ic-252d442b87">Email, SMS &amp; alert controls</span>
                    </div>
                </a>
                <a href="#" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;" class="dior-ic-0eae8df1fa">
                    <i class="fa-solid fa-lock dior-ic-a546442247"></i>
                    <div>
                        <span class="dior-ic-cb8c321fbe">Privacy &amp; Data</span>
                        <span class="dior-ic-252d442b87">EMR consent &amp; data download</span>
                    </div>
                </a>
            </div>
        </div>

        <!-- Form Area -->
        <div class="dior-ic-957da3ee04">
            <div class="dior-ic-977cfd05ef">
                <div class="dior-ic-0de61c57b2">
                    <h3 class="dior-ic-e65d6db14a"><i class="fa-solid fa-address-card dior-ic-9b50cb0b81"></i> Personal Account Details</h3>
                    <p class="dior-ic-d3a1ecffb2">Update your primary identity, phone number, and address info</p>
                </div>
                <div class="dior-ic-269bafc628">
                    <div class="dior-ic-f5cff7aef3">
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">First Name <span class="dior-ic-bbfc66681c">*</span></label>
                            <div class="dior-ic-436662efb5">
                                <i class="fa-solid fa-user dior-ic-4af07ae930"></i>
                                <input type="text" value="<?php echo esc_attr($profile['first_name'] ?? ""); ?>" class="dior-ic-1834247b2e">
                            </div>
                        </div>
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Last Name <span class="dior-ic-bbfc66681c">*</span></label>
                            <div class="dior-ic-436662efb5">
                                <i class="fa-solid fa-user dior-ic-4af07ae930"></i>
                                <input type="text" value="<?php echo esc_attr($profile['last_name'] ?? ""); ?>" class="dior-ic-1834247b2e">
                            </div>
                        </div>
                    </div>

                    <div class="dior-ic-f5cff7aef3">
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Email Address <span class="dior-ic-bbfc66681c">*</span></label>
                            <div class="dior-ic-436662efb5">
                                <i class="fa-solid fa-envelope dior-ic-4af07ae930"></i>
                                <input type="email" value="<?php echo esc_html($profile['email'] ?? ""); ?>" class="dior-ic-1834247b2e">
                            </div>
                        </div>
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Mobile Number <span class="dior-ic-bbfc66681c">*</span></label>
                            <div class="dior-ic-436662efb5">
                                <i class="fa-solid fa-phone dior-ic-4af07ae930"></i>
                                <input type="text" value="<?php echo esc_attr($profile['phone'] ?? ""); ?>" class="dior-ic-1834247b2e">
                            </div>
                        </div>
                    </div>

                    <div class="dior-ic-f5cff7aef3">
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Date of Birth</label>
                            <div class="dior-ic-436662efb5">
                                <i class="fa-regular fa-calendar dior-ic-4af07ae930"></i>
                                <input type="date" value="<?php echo esc_attr($profile['dob'] ?? ""); ?>" class="dior-ic-1e1c917b91">
                            </div>
                        </div>
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Blood Group</label>
                            <div class="dior-ic-d42fb89708">
                                <select class="dior-ic-ab946d8c2a">
                                    <option>A+</option>
                                    <option>A-</option>
                                    <option>B+</option>
                                    <option>B-</option>
                                    <option>AB+</option>
                                    <option>AB-</option>
                                    <option selected>O+</option>
                                    <option>O-</option>
                                </select>
                            </div>
                        </div>
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">City</label>
                            <input type="text" value="<?php echo esc_attr($profile['city'] ?? ""); ?>" class="dior-ic-a53f7b6566">
                        </div>
                    </div>

                    <div class="dior-ic-1be953d431">
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Country</label>
                            <input type="text" value="<?php echo esc_attr($profile['country'] ?? ""); ?>" class="dior-ic-a53f7b6566">
                        </div>
                        <div class="dior-ic-900a3b0aa6">
                            <label class="dior-ic-b67d4ad657">Residential Address</label>
                            <input type="text" value="<?php echo esc_attr($profile['address'] ?? ""); ?>" class="dior-ic-a53f7b6566">
                        </div>
                    </div>
                </div>
                
                <div class="dior-ic-4674fd6006">
                    <a href="#" onclick="return false;" class="dior-ic-86feb638a3">Cancel</a>
                    <a href="#" onclick="return false;" class="dior-ic-ba8c65dd94"><i class="fa-solid fa-floppy-disk"></i> Save Changes</a>
                </div>
            </div>
>>>>>>> fa0e02d91376b068a5cd18ba25d29811366c5101
        </div>
    </div>
</section>

<<<<<<< HEAD
=======

                <!-- ============================================================== -->
                <!-- 2. APPOINTMENTS TAB -->
                <!-- ============================================================== -->
>>>>>>> fa0e02d91376b068a5cd18ba25d29811366c5101
