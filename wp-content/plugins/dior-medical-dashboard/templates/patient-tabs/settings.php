<section class="dior-tab-panel dior-source-panel" id="tab-settings"
    style="display: none; background-color: #f8fafc; padding: 20px; position: relative; overflow: hidden;">
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
            width: 96px !important;
            height: 96px !important;
            max-width: 96px !important;
            max-height: 96px !important;
            min-width: 96px !important;
            min-height: 96px !important;
            border-radius: 50% !important;
            border: 4px solid #ffffff !important;
            box-shadow: 0 4px 16px rgba(15, 43, 72, 0.16) !important;
            object-fit: cover !important;
            display: block !important;
            background-color: #eaf3fb !important;
        }

        #tab-settings .profile-cover-gradient {
            height: 110px !important;
            background: linear-gradient(135deg, #0d2b52 0%, #173f6c 50%, #225a92 100%) !important;
            width: 100% !important;
            border-radius: 16px 16px 0 0 !important;
            position: relative !important;
        }

        #tab-settings .profile-header-card {
            border-radius: 16px !important;
            border: 0.72px solid #2C6CB166 !important;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06) !important;
            overflow: hidden !important;
            background: #ffffff !important;
            margin-bottom: 24px !important;
            transition: border 0.2s ease, box-shadow 0.2s ease !important;
        }

        #tab-settings .profile-header-card:hover {
            border: 0.99px solid #2C6CB166 !important;
        }

        #tab-settings .avatar-change-btn:hover {
            background: #173f6c !important;
            transform: scale(1.08) !important;
        }

        #tab-settings .dior-change-photo-btn:hover {
            background: #225a92 !important;
            color: #ffffff !important;
            border-color: #225a92 !important;
            box-shadow: 0 4px 12px rgba(34, 90, 146, 0.22) !important;
        }

        #tab-settings .dior-change-photo-btn:hover i {
            color: #ffffff !important;
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
            color: #00346C !important;
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

        #tab-settings .dior-form-row-2>.dior-form-group {
            flex: 1 1 50%;
            min-width: 0;
        }

        #tab-settings .dior-form-row-3 {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }

        #tab-settings .dior-form-row-3>.dior-form-group {
            flex: 1 1 33.333%;
            min-width: 0;
        }

        /* Responsive Settings Grid: 2-column on screens < 1536px, converts to 3-column on >= 1536px */
        #tab-settings .dior-form-grid-2col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }

        #tab-settings .dior-pharmacy-grid-row {
            display: flex;
            flex-direction: column;
        }

        #tab-settings .dior-pharmacy-fields-top {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }

        #tab-settings .dior-pharmacy-fields-top > .dior-form-group {
            flex: 1 1 50%;
            min-width: 0;
        }

        @media (min-width: 1536px) {
            #tab-settings .dior-form-grid-2col {
                grid-template-columns: repeat(3, 1fr);
            }

            #tab-settings .dior-pharmacy-grid-row {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
                margin-bottom: 20px;
            }

            #tab-settings .dior-pharmacy-fields-top {
                display: contents;
            }

            #tab-settings .dior-pharmacy-grid-row .dior-form-group {
                margin-bottom: 0 !important;
            }
        }

        @media (max-width: 768px) {

            #tab-settings .dior-form-row-2,
            #tab-settings .dior-form-row-3 {
                flex-direction: column;
                gap: 12px;
            }

            #tab-settings .dior-form-grid-2col {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            #tab-settings .dior-pharmacy-fields-top {
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
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        #tab-settings .dior-toggle-switch input:checked+.dior-toggle-slider {
            background-color: #2563eb;
        }

        #tab-settings .dior-toggle-switch input:checked+.dior-toggle-slider:before {
            transform: translateX(20px);
        }
    </style>

    <div class="dior-source-inner main-content" style="position: relative; z-index: 2;">

        <!-- Breadcrumb Header -->
        <div class="breadcrumb-main mb-3" style="position: relative; z-index: 5;">
            <div class="row align-items-center"
                style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin: 0;">
                <div style="flex: 0 0 auto;">
                    <div class="breadcrumb-title">
                        <h4 class="page-title d-flex align-items-center flex-wrap gap-2 m-0"
                            style="font-size: 20px; font-weight: 700; color: #1e293b;">
                            <span>Patient Settings</span>
                        </h4>
                    </div>
                </div>
                <div style="flex: 0 0 auto;">
                    <ul class="breadcrumb-list"
                        style="display: flex; justify-content: flex-end; align-items: center; gap: 8px; list-style: none; padding: 0; margin: 0; font-size: 12px !important; color: #94a3b8;">
                        <li class="dior-bc-item" style="font-size: 12px !important;">
                            <a href="javascript:void(0)"
                                style="color: #2563eb !important; text-decoration: none !important; font-size: 12px !important;">
                                <i class="fas fa-house" style="font-size: 12px !important;"></i>
                            </a>
                        </li>
                        <li style="color: #cbd5e1; font-size: 12px !important;">/</li>
                        <li class="dior-bc-item" style="font-size: 12px !important;">
                            <a href="javascript:void(0)"
                                style="color: #64748b !important; text-decoration: none !important; font-size: 12px !important;">Settings</a>
                        </li>
                        <li style="color: #cbd5e1; font-size: 12px !important;">/</li>
                        <li class="dior-bc-item active"
                            style="color: #475569 !important; font-weight: 600 !important; font-size: 12px !important;">
                            <span
                                style="color: #475569 !important; font-weight: 600 !important; font-size: 12px !important;">Patient
                                Settings</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="section-body">

            <!-- Hidden file input for direct local device photo upload -->
            <input type="file" id="dior-patient-avatar-file-input"
                accept="image/png, image/jpeg, image/jpg, image/webp, image/gif" style="display: none !important;"
                onchange="diorPatientHandleFileChosen(this)">

            <div class="card profile-header-card mb-4 border-0 shadow-sm"
                style="border-radius: 16px; overflow: hidden; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);">
                <div class="profile-cover-gradient"
                    style="height: 100px; background: linear-gradient(135deg, #0d2b52 0%, #173f6c 50%, #225a92 100%); width: 100%; position: relative;">
                </div>
                <div class="card-body" style="padding: 0 28px 22px; position: relative; background: #ffffff;">
                    <div
                        style="display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-top: -46px; flex-wrap: wrap;">
                        <div
                            style="display: flex; align-items: flex-end; gap: 20px; flex-wrap: wrap; flex: 1 1 auto; min-width: 260px;">

                            <!-- Profile Avatar with Camera Button Overlay -->
                            <div class="avatar-edit-wrapper"
                                style="position: relative; width: 96px; height: 96px; flex-shrink: 0;">
                                <img id="dior-avatar-display-img" alt="Your profile photo" class="profile-avatar-img"
                                    src="<?php echo esc_url($settings_patient_avatar); ?>"
                                    style="width: 96px; height: 96px; border-radius: 50%; object-fit: cover; border: 4px solid #ffffff; box-shadow: 0 4px 16px rgba(15,43,72,0.16); background-color: #f1f5f9; display: block;">

                                <div id="dior-avatar-upload-spinner"
                                    style="display: none; position: absolute; inset: 0; background: rgba(15, 23, 42, 0.6); border-radius: 50%; align-items: center; justify-content: center; z-index: 10;">
                                    <i class="fas fa-circle-notch fa-spin" style="color: #ffffff; font-size: 22px;"></i>
                                </div>

                                <button type="button" title="Upload new photo from your device"
                                    aria-label="Upload photo" class="avatar-change-btn btn"
                                    onclick="diorTriggerPatientAvatarUpload()"
                                    style="position: absolute; bottom: 2px; right: 2px; background: #225a92; color: #ffffff; border: 2.5px solid #ffffff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 3px 10px rgba(0,0,0,0.25); z-index: 8; transition: all 0.2s ease;">
                                    <i class="fas fa-camera" style="font-size: 12px;"></i>
                                </button>
                            </div>

                            <div style="flex: 1 1 0%; min-width: 240px; padding-bottom: 4px;">
                                <div
                                    style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px;">
                                    <h4 class="profile-user-name dior-settings-profile-name"
                                        style="font-weight: 700; color: #0f2b48; font-size: 22px; margin: 0; line-height: 1.2; font-family: 'DM Sans', 'Montserrat', sans-serif;">
                                        <?php echo esc_html($settings_patient_profile['full_name'] ?? $settings_patient_user->display_name); ?>
                                    </h4>
                                    <span
                                        style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 14px; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-circle-check" style="font-size: 9px;"></i> Active
                                    </span>
                                </div>
                                <div
                                    style="font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <?php if (!empty($settings_patient_profile['patient_id'])): ?>
                                        <span
                                            style="background: #f0f7ff; color: #1e5a96; border: 1px solid #d3e5f8; padding: 4px 11px; border-radius: 8px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                                            <i class="fas fa-id-card" style="color: #225a92;"></i> Patient ID:
                                            <?php echo esc_html($settings_patient_profile['patient_id']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span
                                        style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 4px 11px; border-radius: 8px; font-weight: 500; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="far fa-envelope" style="color: #225a92;"></i> <span
                                            class="dior-settings-profile-email"><?php echo esc_html($settings_patient_profile['email'] ?? $settings_patient_user->user_email); ?></span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Button to upload directly from device files -->
                        <div
                            style="padding-bottom: 4px; flex-shrink: 0; display: flex; align-items: center; gap: 10px;">
                            <button type="button" id="dior-change-photo-action-btn" class="dior-change-photo-btn btn"
                                onclick="diorTriggerPatientAvatarUpload()"
                                style="background: #f0f7ff; color: #1e5a96; border: 1px solid #cce1f7; padding: 8px 18px; border-radius: 20px; font-weight: 600; font-size: 12.5px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(34,90,146,0.06);">
                                <i class="fas fa-cloud-arrow-up" style="color: #225a92; font-size: 13px;"></i>
                                <span id="dior-change-photo-text">Upload Photo</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dior-settings-grid-row" style="display: block;">
                <div class="dior-settings-col-main" style="width: 100%; max-width: 100%; flex: 1 1 100%;">

                    <div class="card settings-form-card border-0 shadow-sm" id="patient-profile-settings-card"
                        style="border-radius: 12px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05);">
                        <div class="card-header bg-transparent border-bottom py-3 px-4"
                            style="border-bottom: 1px solid #edf1f5 !important; padding: 16px 20px;">
                            <h5 class="card-title-text mb-0"
                                style="font-size: 15px; font-weight: 700; color: #172033; display: inline-flex; align-items: center; margin-right: 8px;">
                                <i class="fas fa-user text-primary"
                                    style="color: #2563eb !important; margin-right: 8px;"></i>Personal Information
                            </h5>
                            <p class="card-subtitle-text mb-0" style="color: #64748b; display: inline-block;">Manage
                                your contact, health, emergency contact, and pharmacy details.</p>
                        </div>
                        <div class="card-body p-4" style="padding: 24px;">
                            <form id="dior-profile-settings-form" novalidate>
                                <h6 style="margin: 0 0 16px; color: #2b70b8; font-size: 14px; font-weight: 700;">
                                    Personal Details</h6>
                                <div class="dior-form-grid-2col">
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-first-name">First name *</label>
                                        <input id="patient-settings-first-name" class="form-control" type="text"
                                            name="first_name"
                                            value="<?php echo esc_attr($settings_patient_profile['first_name'] ?? ''); ?>"
                                            autocomplete="given-name" required>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-last-name">Last name *</label>
                                        <input id="patient-settings-last-name" class="form-control" type="text"
                                            name="last_name"
                                            value="<?php echo esc_attr($settings_patient_profile['last_name'] ?? ''); ?>"
                                            autocomplete="family-name" required>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-email">Email address</label>
                                        <input id="patient-settings-email" class="form-control" type="email"
                                            name="email"
                                            value="<?php echo esc_attr($settings_patient_profile['email'] ?? $settings_patient_user->user_email); ?>"
                                            autocomplete="email">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-phone">Phone number *</label>
                                        <input id="patient-settings-phone" class="form-control" type="tel" name="phone"
                                            value="<?php echo esc_attr($settings_patient_profile['phone'] ?? ''); ?>"
                                            autocomplete="tel" required>
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-dob">Date of birth *</label>
                                        <input id="patient-settings-dob" class="form-control" type="date" name="dob"
                                            value="<?php echo esc_attr($settings_patient_profile['dob'] ?? ''); ?>"
                                            required>
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
                                </div>
                                <div class="dior-form-row-3">
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-height">Height</label>
                                        <input id="patient-settings-height" class="form-control" type="text" name="height"
                                            placeholder="e.g. 175 cm or 5'9&quot;"
                                            value="<?php echo esc_attr($settings_patient_profile['height'] ?? ''); ?>">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-weight">Weight</label>
                                        <input id="patient-settings-weight" class="form-control" type="text" name="weight"
                                            placeholder="e.g. 70 kg or 154 lbs"
                                            value="<?php echo esc_attr($settings_patient_profile['weight'] ?? ''); ?>">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-blood-group">Blood group</label>
                                        <input id="patient-settings-blood-group" class="form-control" type="text" name="blood_group"
                                            placeholder="e.g. O+, A+, B+"
                                            value="<?php echo esc_attr($settings_patient_profile['blood_group'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="dior-form-group" style="margin-bottom: 16px;">
                                    <label class="form-label" for="patient-settings-address">Mailing address *</label>
                                    <textarea id="patient-settings-address" class="form-control" name="address" rows="2"
                                        autocomplete="street-address"
                                        required><?php echo esc_textarea($settings_patient_profile['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="dior-form-grid-2col">
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-city">City</label>
                                        <input id="patient-settings-city" class="form-control" type="text" name="city"
                                            value="<?php echo esc_attr($settings_patient_profile['city'] ?? ''); ?>"
                                            autocomplete="address-level2">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-state">State</label>
                                        <input id="patient-settings-state" class="form-control" type="text" name="state"
                                            value="<?php echo esc_attr($settings_patient_profile['state'] ?? ''); ?>"
                                            autocomplete="address-level1">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-zip">ZIP / Postal code</label>
                                        <input id="patient-settings-zip" class="form-control" type="text" name="zip"
                                            value="<?php echo esc_attr($settings_patient_profile['zip'] ?? ''); ?>"
                                            autocomplete="postal-code">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-country">Country</label>
                                        <input id="patient-settings-country" class="form-control" type="text"
                                            name="country"
                                            value="<?php echo esc_attr(!empty($settings_patient_profile['country']) ? $settings_patient_profile['country'] : 'United States of America'); ?>"
                                            autocomplete="country-name">
                                    </div>
                                </div>

                                <h6 style="margin: 24px 0 16px; color: #2b70b8; font-size: 14px; font-weight: 700;">
                                    Emergency Contact</h6>
                                <div class="dior-form-row-3">
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-emergency-name">Contact
                                            name</label>
                                        <input id="patient-settings-emergency-name" class="form-control" type="text"
                                            name="emergency_name"
                                            value="<?php echo esc_attr($settings_patient_profile['emergency_name'] ?? ''); ?>">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label"
                                            for="patient-settings-emergency-relation">Relationship</label>
                                        <input id="patient-settings-emergency-relation" class="form-control" type="text"
                                            name="emergency_relation"
                                            value="<?php echo esc_attr($settings_patient_profile['emergency_relation'] ?? ''); ?>">
                                    </div>
                                    <div class="dior-form-group">
                                        <label class="form-label" for="patient-settings-emergency-phone">Phone
                                            number</label>
                                        <input id="patient-settings-emergency-phone" class="form-control" type="tel"
                                            name="emergency_phone"
                                            value="<?php echo esc_attr($settings_patient_profile['emergency_phone'] ?? ''); ?>">
                                    </div>
                                </div>

                                <h6 style="margin: 24px 0 16px; color: #2b70b8; font-size: 14px; font-weight: 700;">
                                    Preferred Pharmacy</h6>
                                <div class="dior-pharmacy-grid-row">
                                    <div class="dior-pharmacy-fields-top">
                                        <div class="dior-form-group">
                                            <label class="form-label" for="patient-settings-pharmacy-name">Pharmacy
                                                name</label>
                                            <input id="patient-settings-pharmacy-name" class="form-control" type="text"
                                                name="pharmacy_name"
                                                value="<?php echo esc_attr($settings_patient_profile['pharmacy_name'] ?? ''); ?>">
                                        </div>
                                        <div class="dior-form-group">
                                            <label class="form-label" for="patient-settings-pharmacy-phone">Pharmacy
                                                phone</label>
                                            <input id="patient-settings-pharmacy-phone" class="form-control" type="tel"
                                                name="pharmacy_phone"
                                                value="<?php echo esc_attr($settings_patient_profile['pharmacy_phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="dior-form-group dior-pharmacy-addr-group" style="margin-bottom: 20px;">
                                        <label class="form-label" for="patient-settings-pharmacy-address">Pharmacy
                                            address</label>
                                        <input id="patient-settings-pharmacy-address" class="form-control" type="text"
                                            name="pharmacy_address"
                                            value="<?php echo esc_attr($settings_patient_profile['pharmacy_address'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div
                                    style="display: flex; justify-content: flex-end; padding-top: 16px; border-top: 1px solid #edf1f5;">
                                    <button type="submit" class="btn btn-sm btn-primary-gradient px-4"
                                        style="background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 8px 22px; font-weight: 600; font-size: 13px; cursor: pointer;">
                                        <i class="fas fa-save" style="margin-right: 5px;"></i> Save Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card settings-form-card border-0 shadow-sm" id="patient-password-reset-card"
                        style="margin-top: 20px; border-radius: 12px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05);">
                        <div class="card-header bg-transparent border-bottom py-3 px-4"
                            style="border-bottom: 1px solid #edf1f5 !important; padding: 16px 20px;">
                            <h5 class="card-title-text mb-0"
                                style="font-size: 15px; font-weight: 700; color: #172033; display: inline-flex; align-items: center; margin-right: 8px;">
                                <i class="fas fa-lock"
                                    style="color: #2563eb !important; margin-right: 8px;"></i>Password &amp; Security
                            </h5>
                        </div>
                        <div class="card-body p-4"
                            style="padding: 24px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                            <p style="margin: 0; color: #64748b;">Use the secure password reset page to create a new
                                password for your account.</p>
                            <a class="btn btn-sm btn-primary-gradient px-4"
                                href="<?php echo esc_url(wp_lostpassword_url()); ?>"
                                style="display: inline-flex; align-items: center; gap: 8px; background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 9px 18px; font-weight: 600; font-size: 13px; line-height: 15px; text-decoration: none;">
                                <i class="fas fa-key"></i> Reset Password
                            </a>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<script>
    (function () {
        // Trigger direct local device file picker
        window.diorTriggerPatientAvatarUpload = function () {
            var fileInput = document.getElementById('dior-patient-avatar-file-input');
            if (fileInput) {
                fileInput.value = '';
                fileInput.click();
            }
        };

        // Update patient image everywhere on the dashboard
        window.diorUpdateAllPatientAvatars = function (avatarUrl) {
            if (!avatarUrl) return;

            // 1. Settings page avatar
            var settingsImg = document.getElementById('dior-avatar-display-img');
            if (settingsImg) settingsImg.src = avatarUrl;

            // 2. Sidebar bottom avatar
            var sidebarImg = document.getElementById('dior-sidebar-avatar-img');
            if (sidebarImg) {
                sidebarImg.src = avatarUrl;
            } else {
                var sidebarBox = document.getElementById('dior-sidebar-avatar-box') || document.querySelector('.dior-sidebar-avatar-wrap');
                if (sidebarBox) {
                    sidebarBox.innerHTML = '<img id="dior-sidebar-avatar-img" src="' + avatarUrl + '" alt="Profile Photo" style="width:100%;height:100%;object-fit:cover;">';
                }
            }

            // 3. Topbar header pill avatar
            var topbarImg = document.getElementById('dior-topbar-avatar-img');
            if (topbarImg) {
                topbarImg.src = avatarUrl;
            } else {
                var topbarBox = document.getElementById('dior-top-pill-avatar');
                if (topbarBox) {
                    topbarBox.innerHTML = '<img id="dior-topbar-avatar-img" src="' + avatarUrl + '" alt="Profile Photo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><span class="dior-online-dot"></span>';
                }
            }

            // 4. Overview tab patient card photo
            var overviewImg = document.getElementById('dior-overview-patient-img');
            if (overviewImg) {
                overviewImg.src = avatarUrl;
            } else {
                var overviewBox = document.getElementById('dior-patient-photo-box') || document.querySelector('.patient-photo');
                if (overviewBox) {
                    overviewBox.innerHTML = '<img id="dior-overview-patient-img" src="' + avatarUrl + '" alt="Profile Photo" style="width:100%;height:100%;object-fit:cover;">';
                }
            }

            // 5. Catch any remaining patient avatar imgs across the portal
            document.querySelectorAll('.dior-sidebar-avatar-wrap img, .dior-pill-avatar img, .patient-photo img, .dior-patient-avatar-sync').forEach(function (img) {
                img.src = avatarUrl;
            });

            // 6. Update global dior_vars profile object if present
            if (window.dior_vars && window.dior_vars.patient_profile) {
                window.dior_vars.patient_profile.avatar_url = avatarUrl;
            }
        };

        // Handle chosen file from local file dialog
        window.diorPatientHandleFileChosen = function (input) {
            if (!input || !input.files || !input.files[0]) return;
            var file = input.files[0];

            // Validate max size 5MB
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Too Large',
                    text: 'The selected photo exceeds the 5MB file size limit. Please choose a smaller image.',
                    confirmButtonColor: '#2C6CB1'
                });
                input.value = '';
                return;
            }

            var settingsImg = document.getElementById('dior-avatar-display-img');
            var originalAvatarSrc = settingsImg ? settingsImg.src : '';

            // UI Loading State
            var spinner = document.getElementById('dior-avatar-upload-spinner');
            if (spinner) spinner.style.display = 'flex';

            var actionBtn = document.getElementById('dior-change-photo-action-btn');
            var actionText = document.getElementById('dior-change-photo-text');
            var origText = actionText ? actionText.innerText : 'Upload Photo';
            if (actionBtn) {
                actionBtn.disabled = true;
                if (actionText) actionText.innerText = 'Uploading...';
            }

            // AJAX POST to dior_patient_upload_avatar
            var formData = new FormData();
            formData.append('action', 'dior_patient_upload_avatar');
            formData.append('nonce', (window.dior_vars && window.dior_vars.nonce) ? window.dior_vars.nonce : '');
            formData.append('avatar', file);

            var ajaxUrl = (window.dior_vars && window.dior_vars.ajax_url) ? window.dior_vars.ajax_url : '/wp-admin/admin-ajax.php';

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (res) { return res.text(); })
                .then(function (text) {
                    if (spinner) spinner.style.display = 'none';
                    if (actionBtn) {
                        actionBtn.disabled = false;
                        if (actionText) actionText.innerText = origText;
                    }

                    var response;
                    try {
                        response = JSON.parse(text);
                    } catch (e) {
                        // Surface a safe, short response excerpt to help identify PHP/server errors.
                        var responseExcerpt = (text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 240);
                        response = {
                            success: false,
                            data: {
                                message: responseExcerpt
                                    ? 'Server returned a non-JSON response: ' + responseExcerpt
                                    : 'Server returned an empty response. Check the WordPress/PHP error log and upload limits.'
                            }
                        };
                    }

                    if (response && response.success && response.data && response.data.avatar_url) {
                        var newUrl = response.data.avatar_url;
                        window.diorUpdateAllPatientAvatars(newUrl);
                        input.value = '';

                        Swal.fire({
                            position: 'top-end',
                            icon: 'success',
                            title: 'Profile Photo Updated!',
                            text: 'Saved directly to Media Library & updated across dashboard.',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    } else {
                        // Ensure displayed image remains strictly the original image
                        if (settingsImg && originalAvatarSrc) {
                            settingsImg.src = originalAvatarSrc;
                        }
                        input.value = '';

                        var errMsg = (response && response.data && response.data.message) ? response.data.message : 'Failed to upload photo.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Failed',
                            text: errMsg,
                            confirmButtonColor: '#2C6CB1'
                        });
                    }
                })
                .catch(function (err) {
                    console.error('[Patient Avatar Upload Error]', err);
                    if (spinner) spinner.style.display = 'none';
                    if (actionBtn) {
                        actionBtn.disabled = false;
                        if (actionText) actionText.innerText = origText;
                    }
                    if (settingsImg && originalAvatarSrc) {
                        settingsImg.src = originalAvatarSrc;
                    }
                    input.value = '';

                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Error',
                        text: 'A connection error occurred while uploading. Please try again.',
                        confirmButtonColor: '#2C6CB1'
                    });
                });
        };
    })();
</script>