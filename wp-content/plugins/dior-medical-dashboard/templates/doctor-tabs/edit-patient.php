<?php
/**
 * Edit Patient Tab — Dynamic & 100% Bootstrap 5 Only
 * Fully synced with Patient Dashboard requirements, database schema, and live profile data.
 */
if (!defined('ABSPATH')) { exit; }
global $wpdb;

$nonce = wp_create_nonce('dior_doctor_nonce');
$ajax_url = admin_url('admin-ajax.php');
$today = date('Y-m-d');
$max_dob = date('Y-m-d', strtotime('-18 years'));

// Fetch all registered patients from wp_dior_patients
$patients = $wpdb->get_results(
    "SELECT user_id, first_name, last_name, patient_uid, email, phone, status, avatar_url FROM {$wpdb->prefix}dior_patients WHERE status <> 'deleted' ORDER BY first_name ASC, last_name ASC LIMIT 500",
    ARRAY_A
);
if (empty($patients)) {
    $users = get_users(['role__in' => ['patient', 'subscriber', 'customer'], 'number' => 200]);
    $patients = [];
    foreach ($users as $u) {
        $patients[] = [
            'user_id' => $u->ID,
            'patient_uid' => get_user_meta($u->ID, 'patient_id', true) ?: ('DM-' . (10000 + $u->ID)),
            'first_name' => $u->first_name ?: $u->display_name,
            'last_name' => $u->last_name,
            'email' => $u->user_email,
            'phone' => get_user_meta($u->ID, 'phone', true),
            'status' => get_user_meta($u->ID, 'dior_patient_status', true) ?: 'active',
            'avatar_url' => get_user_meta($u->ID, 'dior_profile_image', true),
        ];
    }
}
?>
<section class="dior-tab-panel" id="tab-doc-patients-edit" style="display: none;">
    <!-- Breadcrumb & Page Header (100% Bootstrap 5) -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="mb-1 text-dark fw-bold fs-5">
                <i class="fas fa-user-pen text-primary me-2"></i>Edit Patient Profile
            </h4>
            <p class="text-secondary small mb-0">Modify patient clinical profile, contact info, emergency contacts, pharmacy, and portal credentials.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold shadow-sm" onclick="if(typeof diorDocSwitchTab==='function'){diorDocSwitchTab('doc-patients-all');}else if(typeof diorSwitchDocTab==='function'){diorSwitchDocTab('doc-patients-all');}">
                <i class="fas fa-arrow-left me-1"></i> View All Patients
            </button>
            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold shadow-sm" onclick="if(typeof diorDocSwitchTab==='function'){diorDocSwitchTab('doc-patients-add');}else if(typeof diorSwitchDocTab==='function'){diorSwitchDocTab('doc-patients-add');}">
                <i class="fas fa-user-plus me-1"></i> Add New Patient
            </button>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-all" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-all');">
            <i class="fas fa-users"></i> All Patients
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-add" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-add');">
            <i class="fas fa-user-plus"></i> Add Patient
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-patients-edit" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-edit');">
            <i class="fas fa-user-pen"></i> Edit Patient
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-records" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-records');">
            <i class="fas fa-clipboard-user"></i> Patient Records
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-profile" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-profile');">
            <i class="fas fa-id-card"></i> Patient Profile
        </button>
    </div>

    <!-- 1. PATIENT SELECTOR CARD (100% Bootstrap 5) -->
    <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-4">
        <div class="row align-items-center g-3">
            <div class="col-12 col-md-auto">
                <label for="ep-patient-dropdown" class="form-label small fw-bold text-dark mb-0">
                    <i class="fas fa-users-viewfinder text-primary me-2"></i>Select Patient:
                </label>
            </div>
            <div class="col-12 col-md-6 col-lg-5">
                <select id="ep-patient-dropdown" class="form-select rounded-3 shadow-sm">
                    <option value="">-- Choose Patient to Edit --</option>
                    <?php foreach ((array)$patients as $p):
                        $pname = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                    ?>
                        <option value="<?php echo (int)$p['user_id']; ?>" data-uid="<?php echo esc_attr($p['patient_uid'] ?? ''); ?>" data-name="<?php echo esc_attr($pname ?: 'Patient'); ?>">
                            <?php echo esc_html($pname ?: 'Patient'); ?> &bull; <?php echo esc_html($p['patient_uid'] ?? ''); ?> (<?php echo esc_html($p['email'] ?? ''); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="button" class="btn btn-primary rounded-3 fw-semibold px-3 shadow-sm" id="ep-load-btn">
                    <i class="fas fa-folder-open me-1"></i> Load Details
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-3 px-3" id="ep-reload-btn" title="Reload from database">
                    <i class="fas fa-rotate"></i>
                </button>
            </div>
            <div class="col-12 col-lg text-lg-end">
                <span class="text-secondary small" id="ep-loaded-indicator">
                    <i class="fas fa-circle-info text-info me-1"></i>Select a patient to load their chart.
                </span>
            </div>
        </div>
    </div>

    <!-- 2. EMPTY STATE (When no patient selected) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white text-center mb-4" id="ep-empty-state">
        <div class="py-4">
            <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px; font-size: 30px;">
                <i class="fas fa-user-pen"></i>
            </div>
            <h5 class="fw-bold text-dark">No Patient Selected</h5>
            <p class="text-secondary small mb-3 mx-auto" style="max-width: 460px;">
                Choose a patient from the dropdown above, or click "Edit" on any patient record in the All Patients table to modify their personal details, contact address, clinical metrics, and portal credentials.
            </p>
            <button type="button" class="btn btn-outline-primary rounded-pill px-4 fw-semibold" onclick="if(typeof diorDocSwitchTab==='function'){diorDocSwitchTab('doc-patients-all');}else if(typeof diorSwitchDocTab==='function'){diorSwitchDocTab('doc-patients-all');}">
                <i class="fas fa-users me-1"></i> Browse All Patients
            </button>
        </div>
    </div>

    <!-- 3. EDIT WORKSPACE CONTAINER (Hidden until patient selected) -->
    <div id="ep-workspace" style="display: none;">

        <!-- Hero Header Summary Card -->
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div id="ep-hero-avatar-box">
                        <img id="ep-hero-avatar" src="" alt="Patient Avatar" class="rounded-circle border shadow-sm object-fit-cover" style="width: 64px; height: 64px; display: none;">
                        <div id="ep-hero-avatar-placeholder" class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center shadow-sm" style="width: 64px; height: 64px; font-size: 24px;">
                            <span id="ep-hero-initials">PT</span>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h5 class="fw-bold text-dark mb-0 fs-5" id="ep-hero-name">Patient Name</h5>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small" id="ep-hero-status">Active</span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-3 text-secondary small">
                            <span class="badge bg-light text-secondary border rounded-pill px-2 py-1" id="ep-hero-uid">DM-00000</span>
                            <span id="ep-hero-email"><i class="fas fa-envelope text-primary me-1"></i>—</span>
                            <span id="ep-hero-phone"><i class="fas fa-phone text-primary me-1"></i>—</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-3 fw-semibold px-3" id="ep-hero-view-profile-btn">
                        <i class="fas fa-id-card me-1"></i> View Full Chart
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Edit Form Card -->
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-4">
            <div class="card-body p-0">
                <form id="dior-edit-patient-form" novalidate autocomplete="off">
                    <input type="hidden" name="action" value="dior_doc_patient_update">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                    <input type="hidden" name="patient_id" id="ep-form-patient-id" value="">

                    <!-- Alert Feedback Box -->
                    <div id="ep-alert" class="alert d-none mb-4 rounded-3 shadow-sm py-2 px-3 small" role="alert"></div>

                    <!-- 1. PERSONAL INFORMATION (Matches Patient Profile) -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                            <h6 class="text-uppercase text-secondary fw-bold small mb-0">
                                <i class="fas fa-id-card text-primary me-2"></i>1. Personal &amp; Demographic Information
                            </h6>
                            <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">Clinical Profile</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6 col-xl-4">
                                <label for="ep-first-name" class="form-label small fw-semibold text-dark">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3" id="ep-first-name" name="first_name" required>
                            </div>
                            <div class="col-12 col-md-6 col-xl-4">
                                <label for="ep-last-name" class="form-label small fw-semibold text-dark">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3" id="ep-last-name" name="last_name" required>
                            </div>
                            <div class="col-12 col-md-6 col-xl-4">
                                <label for="ep-gender" class="form-label small fw-semibold text-dark">Gender <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3" id="ep-gender" name="gender" required>
                                    <option value="" disabled>Select Gender</option>
                                    <option value="Female">Female</option>
                                    <option value="Male">Male</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6 col-xl-4">
                                <label for="ep-dob" class="form-label small fw-semibold text-dark d-flex align-items-center justify-content-between">
                                    <span>Date of Birth <span class="text-danger">*</span></span>
                                    <span id="ep-age-badge" class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">—</span>
                                </label>
                                <input type="date" class="form-control rounded-3" id="ep-dob" name="dob" max="<?php echo esc_attr($max_dob); ?>" required>
                            </div>
                            <div class="col-12 col-md-6 col-xl-4">
                                <label for="ep-blood-group" class="form-label small fw-semibold text-dark">Blood Group</label>
                                <select class="form-select rounded-3" id="ep-blood-group" name="blood_group">
                                    <option value="">Unknown / Select Blood Group</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-2">
                                <label for="ep-height" class="form-label small fw-semibold text-dark">Height</label>
                                <input type="text" class="form-control rounded-3" id="ep-height" name="height" placeholder="e.g. 5' 8&quot;">
                            </div>
                            <div class="col-12 col-md-6 col-xl-2">
                                <label for="ep-weight" class="form-label small fw-semibold text-dark">Weight</label>
                                <input type="text" class="form-control rounded-3" id="ep-weight" name="weight" placeholder="e.g. 155 lbs">
                            </div>

                            <div class="col-12 col-md-6 col-xl-4">
                                <label for="ep-status" class="form-label small fw-semibold text-dark">Patient Account Status <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3" id="ep-status" name="status" required>
                                    <option value="active">Active (Full portal access)</option>
                                    <option value="inactive">Inactive (Pending review)</option>
                                    <option value="blocked">Blocked (Access suspended)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 2. CONTACT & RESIDENTIAL ADDRESS -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                            <h6 class="text-uppercase text-secondary fw-bold small mb-0">
                                <i class="fas fa-location-dot text-primary me-2"></i>2. Contact &amp; Residential Address
                            </h6>
                            <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">Verification &amp; Telemed</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="ep-email" class="form-label small fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted rounded-start-3"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control rounded-end-3 border-start-0 ps-1" id="ep-email" name="email" required>
                                </div>
                                <div class="form-text small">Primary email used for portal authentication and clinical alerts.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="ep-phone" class="form-label small fw-semibold text-dark">Mobile Phone <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted rounded-start-3"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control rounded-end-3 border-start-0 ps-1" id="ep-phone" name="phone" required>
                                </div>
                                <div class="form-text small">Used for SMS telemedicine invites and appointment notifications.</div>
                            </div>

                            <div class="col-12 col-md-8">
                                <label for="ep-address" class="form-label small fw-semibold text-dark">Street Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3" id="ep-address" name="address" placeholder="e.g. 123 Ocean Drive, Suite 400" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="ep-city" class="form-label small fw-semibold text-dark">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3" id="ep-city" name="city" placeholder="e.g. Miami" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="ep-state" class="form-label small fw-semibold text-dark">State <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3" id="ep-state" name="state" required>
                                    <option value="" disabled>Select State</option>
                                    <option value="FL">Florida (FL)</option>
                                    <option value="CA">California (CA)</option>
                                    <option value="NY">New York (NY)</option>
                                    <option value="TX">Texas (TX)</option>
                                    <option value="AL">Alabama (AL)</option>
                                    <option value="AK">Alaska (AK)</option>
                                    <option value="AZ">Arizona (AZ)</option>
                                    <option value="AR">Arkansas (AR)</option>
                                    <option value="CO">Colorado (CO)</option>
                                    <option value="CT">Connecticut (CT)</option>
                                    <option value="DE">Delaware (DE)</option>
                                    <option value="GA">Georgia (GA)</option>
                                    <option value="HI">Hawaii (HI)</option>
                                    <option value="ID">Idaho (ID)</option>
                                    <option value="IL">Illinois (IL)</option>
                                    <option value="IN">Indiana (IN)</option>
                                    <option value="IA">Iowa (IA)</option>
                                    <option value="KS">Kansas (KS)</option>
                                    <option value="KY">Kentucky (KY)</option>
                                    <option value="LA">Louisiana (LA)</option>
                                    <option value="ME">Maine (ME)</option>
                                    <option value="MD">Maryland (MD)</option>
                                    <option value="MA">Massachusetts (MA)</option>
                                    <option value="MI">Michigan (MI)</option>
                                    <option value="MN">Minnesota (MN)</option>
                                    <option value="MS">Mississippi (MS)</option>
                                    <option value="MO">Missouri (MO)</option>
                                    <option value="MT">Montana (MT)</option>
                                    <option value="NE">Nebraska (NE)</option>
                                    <option value="NV">Nevada (NV)</option>
                                    <option value="NH">New Hampshire (NH)</option>
                                    <option value="NJ">New Jersey (NJ)</option>
                                    <option value="NM">New Mexico (NM)</option>
                                    <option value="NC">North Carolina (NC)</option>
                                    <option value="ND">North Dakota (ND)</option>
                                    <option value="OH">Ohio (OH)</option>
                                    <option value="OK">Oklahoma (OK)</option>
                                    <option value="OR">Oregon (OR)</option>
                                    <option value="PA">Pennsylvania (PA)</option>
                                    <option value="RI">Rhode Island (RI)</option>
                                    <option value="SC">South Carolina (SC)</option>
                                    <option value="SD">South Dakota (SD)</option>
                                    <option value="TN">Tennessee (TN)</option>
                                    <option value="UT">Utah (UT)</option>
                                    <option value="VT">Vermont (VT)</option>
                                    <option value="VA">Virginia (VA)</option>
                                    <option value="WA">Washington (WA)</option>
                                    <option value="WV">West Virginia (WV)</option>
                                    <option value="WI">Wisconsin (WI)</option>
                                    <option value="WY">Wyoming (WY)</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="ep-zip" class="form-label small fw-semibold text-dark">ZIP / Postal Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3" id="ep-zip" name="zip" placeholder="33139" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="ep-country" class="form-label small fw-semibold text-dark">Country</label>
                                <input type="text" class="form-control rounded-3 bg-light" id="ep-country" name="country" value="United States of America" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- 3. EMERGENCY CONTACT -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                            <h6 class="text-uppercase text-secondary fw-bold small mb-0">
                                <i class="fas fa-heart-pulse text-primary me-2"></i>3. Emergency Contact <span class="text-muted fw-normal text-none small">(Optional)</span>
                            </h6>
                            <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">Safety</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="ep-em-name" class="form-label small fw-semibold text-dark">Contact Full Name</label>
                                <input type="text" class="form-control rounded-3" id="ep-em-name" name="emergency_name" placeholder="e.g. Robert Johnson">
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="ep-em-rel" class="form-label small fw-semibold text-dark">Relationship</label>
                                <input type="text" class="form-control rounded-3" id="ep-em-rel" name="emergency_relation" placeholder="e.g. Spouse, Parent, Sibling">
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="ep-em-phone" class="form-label small fw-semibold text-dark">Emergency Contact Phone</label>
                                <input type="tel" class="form-control rounded-3" id="ep-em-phone" name="emergency_phone" placeholder="(555) 111-2222">
                            </div>
                        </div>
                    </div>

                    <!-- 4. PREFERRED PHARMACY -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                            <h6 class="text-uppercase text-secondary fw-bold small mb-0">
                                <i class="fas fa-prescription-bottle-medical text-primary me-2"></i>4. Preferred Pharmacy <span class="text-muted fw-normal text-none small">(Optional)</span>
                            </h6>
                            <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">E-Prescribing</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="ep-pharm-name" class="form-label small fw-semibold text-dark">Pharmacy Name</label>
                                <input type="text" class="form-control rounded-3" id="ep-pharm-name" name="preferred_pharmacy_name" placeholder="e.g. Walgreens #1204">
                            </div>
                            <div class="col-12 col-md-5">
                                <label for="ep-pharm-address" class="form-label small fw-semibold text-dark">Pharmacy Street Address</label>
                                <input type="text" class="form-control rounded-3" id="ep-pharm-address" name="preferred_pharmacy_address" placeholder="e.g. 500 Collins Ave, Miami Beach, FL">
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="ep-pharm-phone" class="form-label small fw-semibold text-dark">Pharmacy Phone</label>
                                <input type="tel" class="form-control rounded-3" id="ep-pharm-phone" name="preferred_pharmacy_phone" placeholder="(305) 538-1571">
                            </div>
                        </div>
                    </div>

                    <!-- 5. ACCOUNT SECURITY & PASSWORD RESET -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                            <h6 class="text-uppercase text-secondary fw-bold small mb-0">
                                <i class="fas fa-lock text-primary me-2"></i>5. Account Security &amp; Credentials
                            </h6>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 small">Leave blank to keep unchanged</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="ep-password" class="form-label small fw-semibold text-dark">Reset Password (Optional)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control rounded-start-3 font-monospace" id="ep-password" name="password" placeholder="Leave blank to keep current password">
                                    <button class="btn btn-outline-secondary" type="button" id="ep-gen-pass-btn" title="Generate New Secure Password">
                                        <i class="fas fa-arrows-rotate me-1"></i> Generate
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" id="ep-copy-pass-btn" title="Copy Password">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                <div class="form-text small">Only fill this if the patient requested a password reset or cannot log into their portal account.</div>
                            </div>

                            <div class="col-12 col-md-6 d-flex align-items-center">
                                <div class="form-check p-3 bg-light rounded-3 border w-100 mt-md-4">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" id="ep-send-password-email" name="send_password_email" value="1">
                                    <label class="form-check-label small fw-semibold text-dark" for="ep-send-password-email">
                                        <i class="fas fa-paper-plane text-primary me-1"></i> Send updated password via email to patient upon saving
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT BUTTONS & ACTIONS (100% Bootstrap 5) -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-3 border-top">
                        <div class="d-flex align-items-center gap-2">
                            <button type="submit" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm" id="ep-submit-btn">
                                <i class="fas fa-floppy-disk me-2"></i>Save Patient Changes
                            </button>
                            <button type="button" class="btn btn-outline-secondary rounded-3 fw-semibold py-2 px-3" id="ep-cancel-btn">
                                <i class="fas fa-xmark me-1"></i>Cancel
                            </button>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-3 fw-semibold px-3" id="ep-view-chart-bottom-btn">
                                <i class="fas fa-id-card me-1"></i>Open Medical Chart
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
(function() {
    'use strict';

    var ajaxUrl = <?php echo json_encode($ajax_url); ?>;
    var doctorNonce = <?php echo json_encode($nonce); ?>;
    var currentLoadedPatient = null;

    // Helper: calculate age from YYYY-MM-DD
    function calculateAge(dobStr) {
        if (!dobStr) return null;
        var dob = new Date(dobStr);
        if (isNaN(dob.getTime())) return null;
        var today = new Date();
        var age = today.getFullYear() - dob.getFullYear();
        var m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        return age;
    }

    // Helper: update age badge
    function updateAgeBadge() {
        var dobInput = document.getElementById('ep-dob');
        var badge = document.getElementById('ep-age-badge');
        if (!dobInput || !badge) return;
        var age = calculateAge(dobInput.value);
        if (age === null) {
            badge.className = 'badge bg-light text-secondary border rounded-pill px-2 py-1 small';
            badge.textContent = 'Must be 18+';
        } else if (age < 18) {
            badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 small';
            badge.textContent = age + ' yrs (Under 18 - Ineligible)';
        } else {
            badge.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small';
            badge.textContent = age + ' yrs (Verified 18+)';
        }
    }

    // Helper: generate secure password
    function generateSecurePassword() {
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        var pass = '';
        for (var i = 0; i < 10; i++) {
            pass += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return pass;
    }

    // Helper: display feedback alert
    function showAlert(type, message) {
        var alertEl = document.getElementById('ep-alert');
        if (!alertEl) return;
        alertEl.className = 'alert alert-' + type + ' rounded-3 shadow-sm py-2 px-3 small';
        alertEl.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation') + ' me-2"></i>' + message;
        alertEl.classList.remove('d-none');
        alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Helper: update hero summary badge
    function updateHeroSummary(p) {
        var name = (p.first_name || '') + ' ' + (p.last_name || '');
        name = name.trim() || p.full_name || 'Patient';
        var uid = p.patient_uid || p.patient_id || 'DM-00000';
        var status = (p.status || 'active').toLowerCase();

        var nameEl = document.getElementById('ep-hero-name');
        if (nameEl) nameEl.textContent = name;

        var uidEl = document.getElementById('ep-hero-uid');
        if (uidEl) uidEl.textContent = uid;

        var statusEl = document.getElementById('ep-hero-status');
        if (statusEl) {
            statusEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            if (status === 'active') {
                statusEl.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small';
            } else if (status === 'blocked') {
                statusEl.className = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 small';
            } else {
                statusEl.className = 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 small';
            }
        }

        var emailEl = document.getElementById('ep-hero-email');
        if (emailEl) emailEl.innerHTML = '<i class="fas fa-envelope text-primary me-1"></i>' + (p.email || '—');

        var phoneEl = document.getElementById('ep-hero-phone');
        if (phoneEl) phoneEl.innerHTML = '<i class="fas fa-phone text-primary me-1"></i>' + (p.phone || '—');

        var avatarImg = document.getElementById('ep-hero-avatar');
        var avatarPlc = document.getElementById('ep-hero-avatar-placeholder');
        var initialsEl = document.getElementById('ep-hero-initials');

        if (p.avatar_url && avatarImg) {
            avatarImg.src = p.avatar_url;
            avatarImg.style.display = 'block';
            if (avatarPlc) avatarPlc.style.display = 'none';
        } else {
            if (avatarImg) avatarImg.style.display = 'none';
            if (avatarPlc) {
                avatarPlc.style.display = 'flex';
                var inits = ((p.first_name ? p.first_name.charAt(0) : '') + (p.last_name ? p.last_name.charAt(0) : '')).toUpperCase() || 'PT';
                if (initialsEl) initialsEl.textContent = inits;
            }
        }
    }

    // Core: Load Patient Profile via AJAX
    window.diorLoadEditPatient = function(patientId, forceReload) {
        if (!patientId) {
            var emptyState = document.getElementById('ep-empty-state');
            var workspace = document.getElementById('ep-workspace');
            if (emptyState) emptyState.style.display = 'block';
            if (workspace) workspace.style.display = 'none';
            var ind = document.getElementById('ep-loaded-indicator');
            if (ind) ind.innerHTML = '<i class="fas fa-circle-info text-info me-1"></i>Select a patient to load their chart.';
            return;
        }

        var loadBtn = document.getElementById('ep-load-btn');
        if (loadBtn) {
            loadBtn.disabled = true;
            loadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Loading…';
        }

        var ind = document.getElementById('ep-loaded-indicator');
        if (ind) ind.innerHTML = '<span class="spinner-border spinner-border-sm text-primary me-1"></span> Fetching patient record…';

        var formData = new FormData();
        formData.append('action', 'dior_doc_get_patient_profile');
        formData.append('patient_id', patientId);
        formData.append('nonce', doctorNonce);

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (loadBtn) {
                loadBtn.disabled = false;
                loadBtn.innerHTML = '<i class="fas fa-folder-open me-1"></i> Load Details';
            }

            if (!res.success || !res.data || !res.data.patient) {
                var msg = (res.data && res.data.message) ? res.data.message : 'Unable to load patient details.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#4F46E5' });
                } else {
                    alert(msg);
                }
                if (ind) ind.innerHTML = '<i class="fas fa-triangle-exclamation text-danger me-1"></i>Failed to load record.';
                return;
            }

            var p = res.data.patient;
            currentLoadedPatient = p;

            // Show workspace, hide empty state
            var emptyState = document.getElementById('ep-empty-state');
            var workspace = document.getElementById('ep-workspace');
            if (emptyState) emptyState.style.display = 'none';
            if (workspace) workspace.style.display = 'block';

            // Set hidden patient ID
            var pidInput = document.getElementById('ep-form-patient-id');
            if (pidInput) pidInput.value = p.user_id || patientId;

            // Populate Form Fields
            var setVal = function(id, val) {
                var el = document.getElementById(id);
                if (el) el.value = (val !== null && val !== undefined) ? val : '';
            };

            setVal('ep-first-name', p.first_name);
            setVal('ep-last-name', p.last_name);
            setVal('ep-gender', p.gender);
            setVal('ep-dob', p.dob);
            setVal('ep-blood-group', p.blood_group);
            setVal('ep-height', p.height);
            setVal('ep-weight', p.weight);
            setVal('ep-status', p.status || 'active');

            setVal('ep-email', p.email);
            setVal('ep-phone', p.phone);
            setVal('ep-address', p.address);
            setVal('ep-city', p.city);
            setVal('ep-state', p.state);
            setVal('ep-zip', p.zip);
            setVal('ep-country', p.country || 'United States of America');

            setVal('ep-em-name', p.emergency_name);
            setVal('ep-em-rel', p.emergency_relation);
            setVal('ep-em-phone', p.emergency_phone);

            setVal('ep-pharm-name', p.pharmacy_name || p.preferred_pharmacy_name);
            setVal('ep-pharm-address', p.pharmacy_address || p.preferred_pharmacy_address);
            setVal('ep-pharm-phone', p.pharmacy_phone || p.preferred_pharmacy_phone);

            // Reset password field
            setVal('ep-password', '');
            var emailCheck = document.getElementById('ep-send-password-email');
            if (emailCheck) emailCheck.checked = false;

            // Clear any alerts
            var alertEl = document.getElementById('ep-alert');
            if (alertEl) alertEl.classList.add('d-none');

            // Update Header & Indicator
            updateHeroSummary(p);
            updateAgeBadge();

            // Sync Dropdown Selection
            var dd = document.getElementById('ep-patient-dropdown');
            if (dd && dd.value != patientId) {
                dd.value = patientId;
            }

            if (ind) {
                ind.innerHTML = '<i class="fas fa-circle-check text-success me-1"></i>Loaded <strong>' + (p.full_name || p.first_name || 'Patient') + '</strong> (' + (p.patient_uid || p.patient_id) + ')';
            }
        })
        .catch(function(err) {
            console.error('[Dior Edit Patient]', err);
            if (loadBtn) {
                loadBtn.disabled = false;
                loadBtn.innerHTML = '<i class="fas fa-folder-open me-1"></i> Load Details';
            }
            if (ind) ind.innerHTML = '<i class="fas fa-triangle-exclamation text-danger me-1"></i>Network error loading patient.';
        });
    };

    // Global hook for navigating directly to edit a patient
    window.diorEditPatient = function(pid) {
        window.diorSelectedPatientId = pid;
        if (typeof window.diorDocSwitchTab === 'function') {
            window.diorDocSwitchTab('doc-patients-edit');
        } else if (typeof window.diorSwitchDocTab === 'function') {
            window.diorSwitchDocTab('doc-patients-edit');
        } else if (typeof window.diorSwitchTab === 'function') {
            window.diorSwitchTab('doc-patients-edit');
        }

        var dd = document.getElementById('ep-patient-dropdown');
        if (dd) dd.value = pid;
        window.diorLoadEditPatient(pid);
    };

    // DOM Ready Initialization
    document.addEventListener('DOMContentLoaded', function() {
        var dropdown = document.getElementById('ep-patient-dropdown');
        var loadBtn = document.getElementById('ep-load-btn');
        var reloadBtn = document.getElementById('ep-reload-btn');
        var dobInput = document.getElementById('ep-dob');
        var form = document.getElementById('dior-edit-patient-form');
        var genPassBtn = document.getElementById('ep-gen-pass-btn');
        var copyPassBtn = document.getElementById('ep-copy-pass-btn');
        var cancelBtn = document.getElementById('ep-cancel-btn');
        var heroViewProfileBtn = document.getElementById('ep-hero-view-profile-btn');
        var viewChartBottomBtn = document.getElementById('ep-view-chart-bottom-btn');

        // Dropdown selection change
        if (dropdown) {
            dropdown.addEventListener('change', function() {
                var pid = this.value;
                window.diorSelectedPatientId = pid;
                window.diorLoadEditPatient(pid);
            });
        }

        // Manual Load button
        if (loadBtn && dropdown) {
            loadBtn.addEventListener('click', function() {
                var pid = dropdown.value;
                if (!pid) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'info', title: 'Choose Patient', text: 'Please select a patient from the dropdown list.', confirmButtonColor: '#4F46E5' });
                    } else {
                        alert('Please select a patient from the dropdown list.');
                    }
                    return;
                }
                window.diorLoadEditPatient(pid);
            });
        }

        // Reload button
        if (reloadBtn && dropdown) {
            reloadBtn.addEventListener('click', function() {
                var pid = dropdown.value || (currentLoadedPatient ? currentLoadedPatient.user_id : null);
                if (pid) {
                    window.diorLoadEditPatient(pid, true);
                }
            });
        }

        // DOB change updates age badge
        if (dobInput) {
            dobInput.addEventListener('input', updateAgeBadge);
            dobInput.addEventListener('change', updateAgeBadge);
        }

        // Generate password
        if (genPassBtn) {
            genPassBtn.addEventListener('click', function() {
                var passInput = document.getElementById('ep-password');
                if (passInput) {
                    var newPass = generateSecurePassword();
                    passInput.value = newPass;
                    passInput.focus();
                    var check = document.getElementById('ep-send-password-email');
                    if (check) check.checked = true;
                }
            });
        }

        // Copy password
        if (copyPassBtn) {
            copyPassBtn.addEventListener('click', function() {
                var passInput = document.getElementById('ep-password');
                if (passInput && passInput.value) {
                    navigator.clipboard.writeText(passInput.value).then(function() {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Password copied to clipboard',
                                showConfirmButton: false,
                                timer: 2000
                            });
                        } else {
                            alert('Password copied to clipboard');
                        }
                    });
                }
            });
        }

        // View Full Chart links
        function openFullChart() {
            var pid = currentLoadedPatient ? (currentLoadedPatient.user_id || currentLoadedPatient.patient_id) : (dropdown ? dropdown.value : null);
            if (!pid) return;
            window.diorSelectedPatientId = pid;
            if (typeof window.diorDocSwitchTab === 'function') {
                window.diorDocSwitchTab('doc-patients-profile');
            } else if (typeof window.diorSwitchDocTab === 'function') {
                window.diorSwitchDocTab('doc-patients-profile');
            } else if (typeof window.diorSwitchTab === 'function') {
                window.diorSwitchTab('doc-patients-profile');
            }
            if (typeof window.diorLoadPatientProfile === 'function') {
                window.diorLoadPatientProfile(pid);
            }
        }
        if (heroViewProfileBtn) heroViewProfileBtn.addEventListener('click', openFullChart);
        if (viewChartBottomBtn) viewChartBottomBtn.addEventListener('click', openFullChart);

        // Cancel button
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                if (typeof window.diorDocSwitchTab === 'function') {
                    window.diorDocSwitchTab('doc-patients-all');
                } else if (typeof window.diorSwitchDocTab === 'function') {
                    window.diorSwitchDocTab('doc-patients-all');
                }
            });
        }

        // Form Submit Handler
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                var submitBtn = document.getElementById('ep-submit-btn');
                var firstName = document.getElementById('ep-first-name').value.trim();
                var lastName = document.getElementById('ep-last-name').value.trim();
                var email = document.getElementById('ep-email').value.trim();
                var phone = document.getElementById('ep-phone').value.trim();
                var dob = document.getElementById('ep-dob').value.trim();
                var address = document.getElementById('ep-address').value.trim();
                var city = document.getElementById('ep-city').value.trim();
                var state = document.getElementById('ep-state').value;
                var zip = document.getElementById('ep-zip').value.trim();
                var newPass = document.getElementById('ep-password').value.trim();

                // Validation
                if (!firstName || !lastName) {
                    showAlert('danger', 'First and Last name are required.');
                    return;
                }
                if (!email || !/\S+@\S+\.\S+/.test(email)) {
                    showAlert('danger', 'Please enter a valid email address.');
                    return;
                }
                if (!phone) {
                    showAlert('danger', 'Mobile phone number is required.');
                    return;
                }
                if (!dob) {
                    showAlert('danger', 'Date of Birth is required.');
                    return;
                }
                var age = calculateAge(dob);
                if (age !== null && age < 18) {
                    showAlert('danger', 'Patient must be at least 18 years of age (Calculated age: ' + age + ' yrs).');
                    return;
                }
                if (!address || !city || !state || !zip) {
                    showAlert('danger', 'Complete residential street address, city, state, and ZIP code are required.');
                    return;
                }
                if (newPass && newPass.length < 6) {
                    showAlert('danger', 'New password must be at least 6 characters long.');
                    return;
                }

                // Loading State
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Saving Changes…';
                }

                var formData = new FormData(form);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-floppy-disk me-2"></i>Save Patient Changes';
                    }

                    if (!res.success) {
                        var msg = (res.data && res.data.message) ? res.data.message : 'Unable to update patient profile.';
                        showAlert('danger', msg);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Update Failed', text: msg, confirmButtonColor: '#4F46E5' });
                        }
                        return;
                    }

                    var msg = (res.data && res.data.message) ? res.data.message : 'Patient profile updated successfully!';
                    showAlert('success', msg);

                    // Update loaded state
                    if (currentLoadedPatient) {
                        currentLoadedPatient.first_name = firstName;
                        currentLoadedPatient.last_name = lastName;
                        currentLoadedPatient.full_name = firstName + ' ' + lastName;
                        currentLoadedPatient.email = email;
                        currentLoadedPatient.phone = phone;
                        currentLoadedPatient.dob = dob;
                        currentLoadedPatient.status = document.getElementById('ep-status').value;
                        updateHeroSummary(currentLoadedPatient);
                    }

                    // Update option in dropdown if exists
                    var pid = document.getElementById('ep-form-patient-id').value;
                    if (dropdown && pid) {
                        var opt = dropdown.querySelector('option[value="' + pid + '"]');
                        if (opt) {
                            var uid = opt.getAttribute('data-uid') || '';
                            opt.textContent = firstName + ' ' + lastName + ' • ' + uid + ' (' + email + ')';
                        }
                    }

                    // Clear password field
                    document.getElementById('ep-password').value = '';
                    var check = document.getElementById('ep-send-password-email');
                    if (check) check.checked = false;

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Profile Updated',
                            text: msg,
                            confirmButtonColor: '#4F46E5'
                        });
                    }
                })
                .catch(function(err) {
                    console.error('[Dior Edit Patient Update]', err);
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-floppy-disk me-2"></i>Save Patient Changes';
                    }
                    showAlert('danger', 'A network error occurred while updating the patient. Please try again.');
                });
            });
        }

        // Tab Switch Listener: Check if selected patient was set by another tab
        var tabObserver = new MutationObserver(function() {
            var tab = document.getElementById('tab-doc-patients-edit');
            if (tab && tab.style.display !== 'none') {
                if (window.diorSelectedPatientId) {
                    var curPid = document.getElementById('ep-form-patient-id');
                    if (!curPid || curPid.value != window.diorSelectedPatientId) {
                        var dd = document.getElementById('ep-patient-dropdown');
                        if (dd) dd.value = window.diorSelectedPatientId;
                        window.diorLoadEditPatient(window.diorSelectedPatientId);
                    }
                }
            }
        });
        var editTab = document.getElementById('tab-doc-patients-edit');
        if (editTab) {
            tabObserver.observe(editTab, { attributes: true, attributeFilter: ['style'] });
        }

        // Check URL parameters for patient_id
        try {
            var urlParams = new URLSearchParams(window.location.search);
            var urlPid = urlParams.get('patient_id') || urlParams.get('user_id');
            if (urlPid) {
                window.diorSelectedPatientId = urlPid;
                if (dropdown) dropdown.value = urlPid;
                window.diorLoadEditPatient(urlPid);
            }
        } catch(e) {}
    });
})();
</script>
