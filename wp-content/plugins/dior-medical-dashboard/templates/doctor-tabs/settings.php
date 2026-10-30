<?php
if (!defined('ABSPATH')) {
    exit;
}

$doctor_id = get_current_user_id();
$doctor = class_exists('Dior_Doctor_Dashboard') ? Dior_Doctor_Dashboard::get_doctor_profile($doctor_id) : [];
$nonce = wp_create_nonce('dior_doctor_nonce');
$ajax_url = admin_url('admin-ajax.php');

$doc_avatar = !empty($doctor['avatar_url']) ? $doctor['avatar_url'] : ('https://ui-avatars.com/api/?name=' . urlencode($doctor['full_name'] ?? 'Doctor') . '&background=00346c&color=ffffff&bold=true');
?>

<section class="dior-tab-panel" id="tab-doc-settings" style="display: none;">

    <!-- Breadcrumb & Page Header (100% Native Bootstrap 5) -->
    <div class="row g-3 align-items-center justify-content-between mb-4">
        <div class="col-12 col-lg-auto">
            <div class="h6 mb-1 fw-bold text-dark">
                <i class="fas fa-user-gear text-primary me-2"></i>Doctor Settings &amp; Profile
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small text-muted">
                    <li class="breadcrumb-item"><i class="fas fa-house me-1"></i> Dashboard</li>
                    <li class="breadcrumb-item">Settings</li>
                    <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">General Settings
                    </li>
                </ol>
            </nav>
        </div>

    </div>

    <!-- Hidden file input for photo upload -->
    <input type="file" id="dior-doctor-avatar-file-input" accept="image/png, image/jpeg, image/jpg, image/webp"
        class="d-none" onchange="diorDoctorHandleFileChosen(this)">

    <!-- Top Profile Banner Card (100% Native Bootstrap 5) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden bg-white">
        <div
            class="p-3 p-md-4 bg-light border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="position-relative flex-shrink-0">
                    <img id="dior-doctor-avatar-display-img" src="<?php echo esc_url($doc_avatar); ?>" width="76"
                        height="76"
                        class="rounded-circle border border-3 border-white shadow-sm object-fit-cover bg-white"
                        alt="<?php echo esc_attr($doctor['full_name'] ?? 'Doctor'); ?>"
                        onerror="this.src='https://ui-avatars.com/api/?name=Doctor&background=00346c&color=ffffff&bold=true'">
                    <button type="button"
                        class="btn btn-primary btn-sm rounded-circle position-absolute bottom-0 end-0 p-0 shadow-sm d-inline-flex align-items-center justify-content-center"
                        style="width: 28px; height: 28px;" title="Change Photo"
                        onclick="diorTriggerDoctorAvatarUpload()">
                        <i class="fas fa-camera text-white small"></i>
                    </button>
                </div>
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <div class="fs-6 fw-bold text-dark mb-0 dior-doc-profile-display-name">
                            <?php echo esc_html($doctor['full_name'] ?? 'Dr. Provider'); ?>
                        </div>
                        <span
                            class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 small dior-doc-profile-display-specialty">
                            <?php echo esc_html($doctor['specialty'] ?? 'General Medicine'); ?>
                        </span>
                        <span
                            class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 small">
                            <i class="fas fa-certificate me-1"></i> Verified Practitioner
                        </span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3 text-secondary small">
                        <span><i class="fas fa-id-card text-primary me-1"></i> NPI:
                            <?php echo esc_html($doctor['npi'] ?: 'Not Assigned'); ?></span>
                        <span>&bull;</span>
                        <span><i class="fas fa-stamp text-primary me-1"></i> License:
                            <?php echo esc_html($doctor['license_no'] ?: '—'); ?></span>
                        <span>&bull;</span>
                        <span><i class="fas fa-envelope text-primary me-1"></i>
                            <?php echo esc_html($doctor['user_email'] ?? ''); ?></span>
                    </div>
                </div>
            </div>

            <!-- Avatar Actions -->
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary rounded-3 fw-semibold py-2 px-3 shadow-sm"
                    onclick="diorTriggerDoctorAvatarUpload()">
                    <i class="fas fa-cloud-arrow-up me-1"></i> Upload Photo
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-3 fw-semibold py-2 px-3"
                    onclick="diorDocOpenMediaLibrary()">
                    <i class="fas fa-images me-1"></i> Media Library
                </button>
                <?php if (!empty($doctor['avatar_url'])): ?>
                    <button type="button" id="dior-btn-doctor-avatar-remove"
                        class="btn btn-outline-danger rounded-3 fw-semibold py-2 px-3" onclick="diorDoctorRemoveAvatar()">
                        <i class="fas fa-trash me-1"></i> Remove
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Two-Column Layout (Responsive Bootstrap 5 Grid) -->
    <div class="row g-3 g-xl-4 align-items-start">

        <!-- Sidebar Navigation Buttons (100% Bootstrap 5) -->
        <div class="col-xl-3 col-lg-4 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex flex-column gap-2" role="tablist">
                    <button type="button"
                        class="btn text-start rounded-3 py-2 px-3 d-flex align-items-center gap-3 fw-semibold shadow-none border btn-primary text-white dior-settings-nav-btn active"
                        data-pane="doc-tab-clinical" onclick="diorSelectSettingsTab(this, 'doc-tab-clinical')">
                        <i class="fas fa-stethoscope fs-5"></i>
                        <div class="flex-grow-1">
                            <div>Clinical Profile</div>
                            <small class="opacity-75 fw-normal d-block">Credentials, fee &amp; location</small>
                        </div>
                    </button>

                    <button type="button"
                        class="btn text-start rounded-3 py-2 px-3 d-flex align-items-center gap-3 fw-semibold shadow-none border bg-light text-dark dior-settings-nav-btn"
                        data-pane="doc-tab-security" onclick="diorSelectSettingsTab(this, 'doc-tab-security')">
                        <i class="fas fa-shield-halved text-primary fs-5"></i>
                        <div class="flex-grow-1">
                            <div>Security &amp; Password</div>
                            <small class="text-secondary fw-normal d-block">Update account credentials</small>
                        </div>
                    </button>

                    <button type="button"
                        class="btn text-start rounded-3 py-2 px-3 d-flex align-items-center gap-3 fw-semibold shadow-none border bg-light text-dark dior-settings-nav-btn"
                        data-pane="doc-tab-practice" onclick="diorSelectSettingsTab(this, 'doc-tab-practice')">
                        <i class="fas fa-calendar-check text-primary fs-5"></i>
                        <div class="flex-grow-1">
                            <div>Practice &amp; Schedule</div>
                            <small class="text-secondary fw-normal d-block">Duty hours &amp; meeting links</small>
                        </div>
                    </button>

                    <button type="button"
                        class="btn text-start rounded-3 py-2 px-3 d-flex align-items-center gap-3 fw-semibold shadow-none border bg-light text-dark dior-settings-nav-btn"
                        data-pane="doc-tab-zoom" onclick="diorSelectSettingsTab(this, 'doc-tab-zoom')">
                        <i class="fas fa-video text-primary fs-5"></i>
                        <div class="flex-grow-1">
                            <div>Zoom Meetings</div>
                            <small class="text-secondary fw-normal d-block">API credentials &amp; connection</small>
                        </div>
                    </button>

                    <button type="button"
                        class="btn text-start rounded-3 py-2 px-3 d-flex align-items-center gap-3 fw-semibold shadow-none border bg-light text-dark dior-settings-nav-btn"
                        data-pane="doc-tab-alerts" onclick="diorSelectSettingsTab(this, 'doc-tab-alerts')">
                        <i class="fas fa-bell text-warning fs-5"></i>
                        <div class="flex-grow-1">
                            <div>Doctor Alerts</div>
                            <small class="text-secondary fw-normal d-block">Booking &amp; email notifications</small>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Form Panes -->
        <div class="col-xl-9 col-lg-8 col-12">

            <!-- 1. Clinical Profile Pane -->
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white dior-settings-pane" id="doc-tab-clinical">
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                    <div>
                        <div class="fs-6 fw-bold text-dark mb-0">
                            <i class="fas fa-user-md text-primary me-2"></i> Clinical Profile &amp; Practitioner Details
                        </div>
                        <div class="text-secondary small mt-1">Keep your professional credentials and practice details
                            accurate for patient records.</div>
                    </div>
                </div>

                <form id="dior-doc-profile-form" onsubmit="diorSaveDoctorProfile(event, this)">
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-first-name">
                                First Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control rounded-3 py-2" id="doc-first-name" name="first_name"
                                value="<?php echo esc_attr($doctor['first_name'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-6 col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-last-name">
                                Last Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control rounded-3 py-2" id="doc-last-name" name="last_name"
                                value="<?php echo esc_attr($doctor['last_name'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-6 col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-email">
                                Email Address <span class="text-muted">(Primary Account)</span>
                            </label>
                            <input type="email" class="form-control rounded-3 py-2 bg-light" id="doc-email"
                                value="<?php echo esc_attr($doctor['user_email'] ?? ''); ?>" readonly>
                        </div>

                        <div class="col-md-6 col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-phone">
                                Phone Number <span class="text-danger">*</span>
                            </label>
                            <input type="tel" class="form-control rounded-3 py-2" id="doc-phone" name="phone"
                                value="<?php echo esc_attr($doctor['phone'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-4 col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-specialty">
                                Specialty <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control rounded-3 py-2" id="doc-specialty"
                                name="doctor_specialty" value="<?php echo esc_attr($doctor['specialty'] ?? ''); ?>"
                                required>
                        </div>

                        <div class="col-md-4 col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-license">
                                Medical License #
                            </label>
                            <input type="text" class="form-control rounded-3 py-2" id="doc-license"
                                name="doctor_license" value="<?php echo esc_attr($doctor['license_no'] ?? ''); ?>"
                                placeholder="e.g. MD-884920">
                        </div>

                        <div class="col-md-4 col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-npi">
                                NPI (National Provider Identifier)
                            </label>
                            <input type="text" class="form-control rounded-3 py-2" id="doc-npi" name="doctor_npi"
                                value="<?php echo esc_attr($doctor['npi'] ?? ''); ?>" placeholder="10-digit NPI">
                        </div>

                        <div class="col-md-4 col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-qualifications">
                                Qualifications / Degrees
                            </label>
                            <input type="text" class="form-control rounded-3 py-2" id="doc-qualifications"
                                name="doctor_qualifications"
                                value="<?php echo esc_attr($doctor['qualifications'] ?? ''); ?>"
                                placeholder="e.g. MBBS, MD, DO">
                        </div>

                        <div class="col-md-4 col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-fee">
                                Consultation Fee
                            </label>
                            <input type="text" class="form-control rounded-3 py-2" id="doc-fee" name="doctor_fee"
                                value="<?php echo esc_attr($doctor['fee'] ?? ''); ?>"
                                placeholder="e.g. $150 or INR 1500">
                        </div>

                        <div class="col-md-4 col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-duty-hours">
                                Regular Duty Hours
                            </label>
                            <input type="text" class="form-control rounded-3 py-2" id="doc-duty-hours"
                                name="doctor_duty_hours" value="<?php echo esc_attr($doctor['duty_hours'] ?? ''); ?>"
                                placeholder="e.g. Mon-Sat 10:00 AM - 8:00 PM">
                        </div>

                        <div class="col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-zoom-link">
                                Telehealth Video Meeting URL (Zoom / Jitsi)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-video text-primary"></i></span>
                                <input type="url" class="form-control rounded-end-3 py-2" id="doc-zoom-link"
                                    name="doctor_zoom_link" value="<?php echo esc_attr($doctor['zoom_link'] ?? ''); ?>"
                                    placeholder="https://zoom.us/j/...">
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-address">
                                Clinic / Practice Address
                            </label>
                            <input type="text" class="form-control rounded-3 py-2" id="doc-address" name="address"
                                value="<?php echo esc_attr($doctor['address'] ?? ''); ?>" placeholder="Street address">
                        </div>

                        <div class="col-md-3 col-6">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-city">
                                City
                            </label>
                            <input type="text" class="form-control rounded-3 py-2" id="doc-city" name="city"
                                value="<?php echo esc_attr($doctor['city'] ?? ''); ?>" placeholder="City">
                        </div>

                        <div class="col-md-3 col-6">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-country">
                                Country
                            </label>
                            <input type="text" class="form-control rounded-3 py-2" id="doc-country" name="country"
                                value="<?php echo esc_attr($doctor['country'] ?? ''); ?>" placeholder="Country">
                        </div>

                        <div class="col-12">
                            <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                for="doc-bio">
                                Practitioner Biography &amp; Philosophy of Care
                            </label>
                            <textarea class="form-control rounded-3 py-2" id="doc-bio" name="doctor_bio" rows="4"
                                placeholder="Share your medical background, treatment philosophy, and clinical areas of focus..."><?php echo esc_textarea($doctor['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm"
                            id="dior-doc-profile-save-btn">
                            <i class="fas fa-check me-1"></i> Save Clinical Profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- 2. Security & Password Pane -->
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white dior-settings-pane" id="doc-tab-security"
                hidden>
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                    <div>
                        <div class="fs-6 fw-bold text-dark mb-0">
                            <i class="fas fa-shield-halved text-primary me-2"></i> Security &amp; Password Management
                        </div>
                        <div class="text-secondary small mt-1">Update your practitioner password to protect clinical
                            records and patient data confidentiality.</div>
                    </div>
                </div>

                <form id="dior-doc-password-form" onsubmit="diorSaveDoctorPassword(event, this)">
                    <?php $dior_password_user = wp_get_current_user(); ?>
                    <input type="text" name="username" autocomplete="username"
                        value="<?php echo esc_attr($dior_password_user->user_login); ?>"
                        class="visually-hidden" tabindex="-1" aria-hidden="true" readonly>
                    <div class="row g-4 align-items-start">
                        <!-- Left Column: Password Inputs Form -->
                        <div class="col-lg-7 col-12">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                        for="doc-current-password">
                                        Current Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control rounded-3 py-2 pe-5"
                                            id="doc-current-password" name="current_password" required
                                            placeholder="Enter current password" autocomplete="current-password">
                                        <span role="button" tabindex="0"
                                            class="position-absolute top-50 end-0 translate-middle-y me-3 text-secondary p-0 border-0 bg-transparent"
                                            onclick="diorTogglePasswordVisibility('doc-current-password', this)"
                                            aria-label="Toggle password visibility" style="cursor: pointer; z-index: 5;">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                        for="doc-new-password">
                                        New Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control rounded-3 py-2 pe-5"
                                            id="doc-new-password" name="new_password" required minlength="6"
                                            placeholder="Enter new password (min. 6 characters)"
                                            autocomplete="new-password">
                                        <span role="button" tabindex="0"
                                            class="position-absolute top-50 end-0 translate-middle-y me-3 text-secondary p-0 border-0 bg-transparent"
                                            onclick="diorTogglePasswordVisibility('doc-new-password', this)"
                                            aria-label="Toggle password visibility" style="cursor: pointer; z-index: 5;">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label small text-uppercase text-secondary fw-semibold mb-1"
                                        for="doc-confirm-password">
                                        Confirm New Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control rounded-3 py-2 pe-5"
                                            id="doc-confirm-password" name="confirm_password" required minlength="6"
                                            placeholder="Re-type new password" autocomplete="new-password">
                                        <span role="button" tabindex="0"
                                            class="position-absolute top-50 end-0 translate-middle-y me-3 text-secondary p-0 border-0 bg-transparent"
                                            onclick="diorTogglePasswordVisibility('doc-confirm-password', this)"
                                            aria-label="Toggle password visibility" style="cursor: pointer; z-index: 5;">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-start mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm"
                                    id="dior-doc-password-save-btn">
                                    <i class="fas fa-key me-1"></i> Update Password
                                </button>
                            </div>
                        </div>

                        <!-- Right Column: Security Guidance & Standards Card -->
                        <div class="col-lg-5 col-12">
                            <div
                                class="card border border-light-subtle bg-light rounded-4 p-3 p-md-4 h-100 shadow-none">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span
                                        class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3 p-2">
                                        <i class="fas fa-shield-halved fs-5"></i>
                                    </span>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0 fs-6">Security &amp; Compliance</h6>
                                        <small class="text-secondary">Practitioner account protection</small>
                                    </div>
                                </div>

                                <!-- <div
                                    class="d-flex align-items-center justify-content-between p-2 px-3 bg-white rounded-3 border border-light-subtle mb-3">
                                    <span class="small fw-semibold text-secondary">Account Status</span>
                                    <span
                                        class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small">
                                        <i class="fas fa-circle-check me-1"></i> Active &amp; Verified
                                    </span>
                                </div> -->

                                <div class="small text-secondary mb-3">
                                    <div class="fw-semibold text-dark mb-2">Password Best Practices:</div>
                                    <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                                        <li class="d-flex align-items-start gap-2">
                                            <i class="fas fa-check text-success mt-1 small"></i>
                                            <span>Minimum 6 characters in length</span>
                                        </li>
                                        <li class="d-flex align-items-start gap-2">
                                            <i class="fas fa-check text-success mt-1 small"></i>
                                            <span>Use a mix of letters, numbers &amp; symbols</span>
                                        </li>
                                        <li class="d-flex align-items-start gap-2">
                                            <i class="fas fa-check text-success mt-1 small"></i>
                                            <span>Avoid reusing passwords from other medical sites</span>
                                        </li>
                                    </ul>
                                </div>


                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 3. Practice & Schedule Settings Pane -->
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white dior-settings-pane" id="doc-tab-practice"
                hidden>
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                    <div>
                        <div class="fs-6 fw-bold text-dark mb-0">
                            <i class="fas fa-calendar-check text-primary me-2"></i> Practice &amp; Scheduling
                            Preferences
                        </div>
                        <div class="text-secondary small mt-1">Configure appointment modalities and clinical visit
                            defaults.</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6 col-12">
                        <label class="form-label small text-uppercase text-secondary fw-semibold mb-1">Default
                            Consultation Duration</label>
                        <select class="form-select rounded-3 py-2">
                            <option value="30">30 Minutes</option>
                            <option value="45">45 Minutes</option>
                            <option value="60" selected>60 Minutes (Standard)</option>
                        </select>
                    </div>

                    <div class="col-md-6 col-12">
                        <label class="form-label small text-uppercase text-secondary fw-semibold mb-1">Appointment
                            Buffer Time</label>
                        <select class="form-select rounded-3 py-2">
                            <option value="0">No buffer</option>
                            <option value="10" selected>10 Minutes between visits</option>
                            <option value="15">15 Minutes</option>
                        </select>
                    </div>

                    <div class="col-12 mt-4">
                        <div
                            class="form-check form-switch p-3 border rounded-3 d-flex justify-content-between align-items-center">
                            <div>
                                <label class="form-check-label fw-bold text-dark d-block" for="practice-auto-confirm">
                                    Instant Appointment Confirmation
                                </label>
                                <span class="small text-secondary">Automatically confirm incoming patient booking
                                    requests.</span>
                            </div>
                            <input class="form-check-input ms-3" type="checkbox" role="switch"
                                id="practice-auto-confirm" checked>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm"
                        onclick="Swal.fire({icon: 'success', title: 'Preferences Updated', toast: true, timer: 1800, position: 'top-end', showConfirmButton: false})">
                        <i class="fas fa-check me-1"></i> Save Practice Settings
                    </button>
                </div>
            </div>

            <!-- 4. Alerts & Notifications Settings Pane -->
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white dior-settings-pane" id="doc-tab-alerts"
                hidden>
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                    <div>
                        <div class="fs-6 fw-bold text-dark mb-0">
                            <i class="fas fa-bell text-warning me-2"></i> Doctor Alert &amp; Notification Preferences
                        </div>
                        <div class="text-secondary small mt-1">Control which clinical events trigger notifications and
                            delivery channels.</div>
                    </div>
                </div>

                <form id="dior-doc-alerts-form" onsubmit="diorSaveDoctorAlerts(event, this)">
                    <div class="d-flex flex-column gap-3">
                        <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small mb-1">Appointment Reminders (Email)</div>
                                <p class="small text-secondary mb-0">Receive email alerts when patients book,
                                    reschedule, or cancel consultations.</p>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch"
                                    name="optin_appointment_reminders" value="1" <?php checked($doctor['optin_appointment_reminders'] ?? '1', '1'); ?>>
                            </div>
                        </div>

                        <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small mb-1">SMS &amp; Urgent Clinical Alerts</div>
                                <p class="small text-secondary mb-0">Receive immediate SMS notifications for upcoming
                                    visits starting in under 30 minutes.</p>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch"
                                    name="optin_reminder_email" value="1" <?php checked($doctor['optin_reminder_email'] ?? '1', '1'); ?>>
                            </div>
                        </div>

                        <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small mb-1">Portal Activity Notifications</div>
                                <p class="small text-secondary mb-0">Display notifications in your Activity Feed for lab
                                    reports and patient messages.</p>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch"
                                    name="optin_reminder_dashboard" value="1" <?php checked($doctor['optin_reminder_dashboard'] ?? '1', '1'); ?>>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm"
                            id="dior-doc-alerts-save-btn">
                            <i class="fas fa-check me-1"></i> Save Notification Preferences
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <!-- Zoom Meetings integration status -->
    <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white dior-settings-pane" id="doc-tab-zoom" hidden>
        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3 gap-3">
            <div>
                <div class="fs-6 fw-bold text-dark mb-1"><i class="fas fa-video text-primary me-2"></i>Zoom Meetings API</div>
                <div class="small text-secondary">Generate Zoom links for video consultations when API credentials are configured.</div>
            </div>
            <span class="badge rounded-pill <?php echo (class_exists('Dior_Zoom_Meetings') && Dior_Zoom_Meetings::is_configured()) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'; ?>">
                <?php echo (class_exists('Dior_Zoom_Meetings') && Dior_Zoom_Meetings::is_configured()) ? 'Credentials Saved' : 'Setup Required'; ?>
            </span>
        </div>
        <div class="alert alert-info mb-0" role="status">
            A site administrator can configure the Zoom Account ID, Client ID, and Client Secret under <strong>WordPress Admin &rarr; Settings &rarr; Dior Zoom Meetings</strong>. Until valid credentials are saved, video bookings will not receive a generated meeting URL.
        </div>
    </div>

    <!-- JavaScript Controller -->
    <script>
        (function () {
            var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
            var nonce = '<?php echo esc_js($nonce); ?>';

            window.diorSelectSettingsTab = function (btn, paneId) {
                document.querySelectorAll('.dior-settings-nav-btn').forEach(function (b) {
                    b.classList.remove('active', 'btn-primary', 'text-white');
                    b.classList.add('bg-light', 'text-dark');
                    var icon = b.querySelector('i');
                    if (icon) icon.classList.remove('text-white');
                });
                if (btn) {
                    btn.classList.add('active', 'btn-primary', 'text-white');
                    btn.classList.remove('bg-light', 'text-dark');
                    var activeIcon = btn.querySelector('i');
                    if (activeIcon) activeIcon.classList.add('text-white');
                }

                document.querySelectorAll('.dior-settings-pane').forEach(function (pane) {
                    pane.hidden = true;
                });
                var activePane = document.getElementById(paneId);
                if (activePane) activePane.hidden = false;
            };

            window.diorTriggerDoctorAvatarUpload = function () {
                var fileInput = document.getElementById('dior-doctor-avatar-file-input');
                if (fileInput) fileInput.click();
            };

            window.diorDoctorHandleFileChosen = function (input) {
                if (!input || !input.files || !input.files[0]) return;
                var file = input.files[0];
                var fd = new FormData();
                fd.append('action', 'dior_doctor_upload_avatar');
                fd.append('nonce', nonce);
                fd.append('avatar', file);

                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success && res.data && res.data.avatar_url) {
                            var img = document.getElementById('dior-doctor-avatar-display-img');
                            if (img) img.src = res.data.avatar_url;
                            if (window.Swal) Swal.fire({ position: 'top-end', icon: 'success', title: 'Avatar Updated', toast: true, timer: 1800, showConfirmButton: false });
                        } else {
                            if (window.Swal) Swal.fire({ icon: 'error', title: 'Upload Failed', text: res.data && res.data.message ? res.data.message : 'Failed to upload photo.' });
                        }
                    })
                    .catch(function () {
                        if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' });
                    });
            };

            window.diorDoctorRemoveAvatar = function () {
                if (!confirm('Are you sure you want to remove your profile photo?')) return;
                var fd = new FormData();
                fd.append('action', 'dior_doctor_upload_avatar');
                fd.append('nonce', nonce);
                fd.append('remove_avatar', '1');

                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) {
                            var img = document.getElementById('dior-doctor-avatar-display-img');
                            if (img) img.src = 'https://ui-avatars.com/api/?name=Doctor&background=00346c&color=ffffff&bold=true';
                            var rmBtn = document.getElementById('dior-btn-doctor-avatar-remove');
                            if (rmBtn) rmBtn.classList.add('d-none');
                            if (window.Swal) Swal.fire({ position: 'top-end', icon: 'success', title: 'Avatar Removed', toast: true, timer: 1800, showConfirmButton: false });
                        }
                    });
            };

            window.diorDocOpenMediaLibrary = function () {
                if (typeof wp !== 'undefined' && wp.media) {
                    var frame = wp.media({
                        title: 'Select Profile Photo',
                        button: { text: 'Use as Profile Photo' },
                        multiple: false
                    });
                    frame.on('select', function () {
                        var attachment = frame.state().get('selection').first().toJSON();
                        if (attachment && attachment.url) {
                            var fd = new FormData();
                            fd.append('action', 'dior_doctor_upload_avatar');
                            fd.append('nonce', nonce);
                            fd.append('avatar_url', attachment.url);
                            fd.append('attachment_id', attachment.id || 0);
                            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                                .then(function (r) { return r.json(); })
                                .then(function (res) {
                                    if (res.success) {
                                        var img = document.getElementById('dior-doctor-avatar-display-img');
                                        if (img) img.src = attachment.url;
                                    }
                                });
                        }
                    });
                    frame.open();
                } else {
                    diorTriggerDoctorAvatarUpload();
                }
            };

            window.diorSaveDoctorProfile = function (e, form) {
                e.preventDefault();
                var btn = document.getElementById('dior-doc-profile-save-btn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Saving...';

                var fd = new FormData(form);
                fd.append('action', 'dior_doctor_save_profile');
                fd.append('nonce', nonce);

                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) {
                            var fname = fd.get('first_name');
                            var lname = fd.get('last_name');
                            var spec = fd.get('doctor_specialty');
                            var nameEl = document.querySelector('.dior-doc-profile-display-name');
                            if (nameEl) nameEl.textContent = 'Dr. ' + fname + ' ' + lname;
                            var specEl = document.querySelector('.dior-doc-profile-display-specialty');
                            if (specEl) specEl.textContent = spec;

                            if (window.Swal) {
                                Swal.fire({
                                    position: 'top-end',
                                    icon: 'success',
                                    title: 'Profile Saved',
                                    text: res.data && res.data.message ? res.data.message : 'Clinical profile details saved successfully.',
                                    showConfirmButton: false,
                                    timer: 2000
                                });
                            }
                        } else {
                            if (window.Swal) Swal.fire({ icon: 'error', title: 'Save Failed', text: res.data && res.data.message ? res.data.message : 'Failed to save.' });
                        }
                    })
                    .catch(function () {
                        if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' });
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check me-1"></i> Save Clinical Profile';
                    });
            };

            window.diorTogglePasswordVisibility = function (inputId, btn) {
                var input = document.getElementById(inputId);
                if (!input) return;
                var icon = btn ? btn.querySelector('i') : null;
                if (input.type === 'password') {
                    input.type = 'text';
                    if (icon) {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    }
                } else {
                    input.type = 'password';
                    if (icon) {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                }
            };

            window.diorSaveDoctorPassword = function (e, form) {
                e.preventDefault();
                var newPass = document.getElementById('doc-new-password') ? document.getElementById('doc-new-password').value : '';
                var confirmPass = document.getElementById('doc-confirm-password') ? document.getElementById('doc-confirm-password').value : '';
                if (newPass !== confirmPass) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Passwords Do Not Match',
                            text: 'New password and confirmation password do not match. Please re-enter them carefully.',
                            confirmButtonColor: '#2563eb'
                        });
                    } else {
                        alert('New password and confirmation password do not match.');
                    }
                    return;
                }

                var btn = document.getElementById('dior-doc-password-save-btn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Updating...';

                var fd = new FormData(form);
                fd.append('action', 'dior_doctor_change_password');
                fd.append('nonce', nonce);

                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) {
                            form.reset();
                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Password Changed',
                                    text: res.data && res.data.message ? res.data.message : 'Your password has been changed successfully.',
                                    confirmButtonColor: '#2563eb'
                                });
                            }
                        } else {
                            if (window.Swal) Swal.fire({ icon: 'error', title: 'Password Change Failed', text: res.data && res.data.message ? res.data.message : 'Current password incorrect or validation error.' });
                        }
                    })
                    .catch(function () {
                        if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' });
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-key me-1"></i> Update Password';
                    });
            };

            window.diorSaveDoctorAlerts = function (e, form) {
                e.preventDefault();
                var btn = document.getElementById('dior-doc-alerts-save-btn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Saving...';

                var fd = new FormData(form);
                fd.append('action', 'dior_doctor_save_profile');
                fd.append('nonce', nonce);
                // Default unchecked switches to 0
                if (!form.querySelector('input[name="optin_appointment_reminders"]:checked')) fd.append('optin_appointment_reminders', '0');
                if (!form.querySelector('input[name="optin_reminder_email"]:checked')) fd.append('optin_reminder_email', '0');
                if (!form.querySelector('input[name="optin_reminder_dashboard"]:checked')) fd.append('optin_reminder_dashboard', '0');

                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) {
                            if (window.Swal) Swal.fire({ position: 'top-end', icon: 'success', title: 'Preferences Saved', toast: true, timer: 1800, showConfirmButton: false });
                        } else {
                            if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: res.data && res.data.message ? res.data.message : 'Failed to save.' });
                        }
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check me-1"></i> Save Notification Preferences';
                    });
            };
        })();
    </script>
</section>
