<?php
/**
 * Add Patient Tab — Dynamic & 100% Bootstrap 5 Only
 * Fully synced with Patient Dashboard profile requirements and relational database
 */
$nonce = wp_create_nonce('dior_doctor_nonce');
$ajax_url = admin_url('admin-ajax.php');
$today = date('Y-m-d');
$max_dob = date('Y-m-d', strtotime('-18 years'));
?>
<section class="dior-tab-panel" id="tab-doc-patients-add" style="display: none;">
    <!-- Breadcrumb & Page Header (100% Bootstrap 5) -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="mb-1 text-dark fw-bold fs-5">
                <i class="fas fa-user-plus text-primary me-2"></i>Add New Patient
            </h4>
            <p class="text-secondary small mb-0">Create an official patient record and portal account synced with the Patient Dashboard.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold shadow-sm" onclick="if(typeof diorSwitchDocTab==='function'){diorSwitchDocTab('doc-patients-all');}else if(typeof diorSwitchTab==='function'){diorSwitchTab('doc-patients-all');}">
                <i class="fas fa-arrow-left me-1"></i> View All Patients
            </button>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-all" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-all');">
            <i class="fas fa-users"></i> All Patients
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-patients-add" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-add');">
            <i class="fas fa-user-plus"></i> Add Patient
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-edit" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-edit');">
            <i class="fas fa-user-pen"></i> Edit Patient
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-records" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-records');">
            <i class="fas fa-clipboard-user"></i> Patient Records
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-profile" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-profile');">
            <i class="fas fa-id-card"></i> Patient Profile
        </button>
    </div>

    <!-- Main Form Card (100% Bootstrap 5) -->
    <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-4">
        <div class="card-body p-0">
            <form id="dior-add-patient-form" novalidate autocomplete="off">
                <input type="hidden" name="action" value="dior_doc_patient_add">
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">

                <!-- Alert Feedback Box -->
                <div id="add-patient-alert" class="alert d-none mb-4 rounded-3 shadow-sm py-2 px-3 small" role="alert"></div>

                <!-- 1. PERSONAL INFORMATION (Matches Patient Dashboard Profile) -->
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                        <h6 class="text-uppercase text-secondary fw-bold small mb-0">
                            <i class="fas fa-id-card text-primary me-2"></i>1. Personal Information <span class="text-muted fw-normal text-none small">(Required for Clinical Profile)</span>
                        </h6>
                        <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">Patient Demographics</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-xl-4">
                            <label for="ap-first-name" class="form-label small fw-semibold text-dark">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="ap-first-name" name="first_name" placeholder="e.g. Sarah" required>
                        </div>
                        <div class="col-12 col-md-6 col-xl-4">
                            <label for="ap-last-name" class="form-label small fw-semibold text-dark">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="ap-last-name" name="last_name" placeholder="e.g. Johnson" required>
                        </div>
                        <div class="col-12 col-md-6 col-xl-4">
                            <label for="ap-gender" class="form-label small fw-semibold text-dark">Gender <span class="text-danger">*</span></label>
                            <select class="form-select rounded-3" id="ap-gender" name="gender" required>
                                <option value="" selected disabled>Select Gender</option>
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-xl-4">
                            <label for="ap-dob" class="form-label small fw-semibold text-dark d-flex align-items-center justify-content-between">
                                <span>Date of Birth <span class="text-danger">*</span></span>
                                <span id="ap-age-badge" class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">Must be 18+</span>
                            </label>
                            <input type="date" class="form-control rounded-3" id="ap-dob" name="dob" max="<?php echo esc_attr($max_dob); ?>" required>
                        </div>
                        <div class="col-12 col-md-6 col-xl-4">
                            <label for="ap-blood-group" class="form-label small fw-semibold text-dark">Blood Group</label>
                            <select class="form-select rounded-3" id="ap-blood-group" name="blood_group">
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
                            <label for="ap-height" class="form-label small fw-semibold text-dark">Height</label>
                            <input type="text" class="form-control rounded-3" id="ap-height" name="height" placeholder="e.g. 5' 8&quot;">
                        </div>
                        <div class="col-12 col-md-6 col-xl-2">
                            <label for="ap-weight" class="form-label small fw-semibold text-dark">Weight</label>
                            <input type="text" class="form-control rounded-3" id="ap-weight" name="weight" placeholder="e.g. 155 lbs">
                        </div>
                    </div>
                </div>

                <!-- 2. CONTACT & ADDRESS INFORMATION -->
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                        <h6 class="text-uppercase text-secondary fw-bold small mb-0">
                            <i class="fas fa-location-dot text-primary me-2"></i>2. Contact &amp; Residential Address <span class="text-muted fw-normal text-none small">(Required)</span>
                        </h6>
                        <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">Address Verification</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="ap-email" class="form-label small fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted rounded-start-3"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control rounded-end-3 border-start-0 ps-1" id="ap-email" name="email" placeholder="patient@example.com" required>
                            </div>
                            <div class="form-text small">Used for patient portal login and direct clinical communications.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="ap-phone" class="form-label small fw-semibold text-dark">Mobile Phone <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted rounded-start-3"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control rounded-end-3 border-start-0 ps-1" id="ap-phone" name="phone" placeholder="(555) 000-0000" required>
                            </div>
                            <div class="form-text small">Used for SMS appointment notifications and telemedicine invitations.</div>
                        </div>

                        <div class="col-12 col-md-8">
                            <label for="ap-address" class="form-label small fw-semibold text-dark">Street Address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="ap-address" name="address" placeholder="123 Ocean Drive, Suite 400" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="ap-city" class="form-label small fw-semibold text-dark">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="ap-city" name="city" placeholder="e.g. Miami" required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="ap-state" class="form-label small fw-semibold text-dark">State <span class="text-danger">*</span></label>
                            <select class="form-select rounded-3" id="ap-state" name="state" required>
                                <option value="" selected disabled>Select State</option>
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
                            <label for="ap-zip" class="form-label small fw-semibold text-dark">ZIP / Postal Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="ap-zip" name="zip" placeholder="33139" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="ap-country" class="form-label small fw-semibold text-dark">Country</label>
                            <input type="text" class="form-control rounded-3 bg-light" id="ap-country" name="country" value="United States of America" readonly>
                        </div>
                    </div>
                </div>

                <!-- 3. EMERGENCY CONTACT (Optional) -->
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                        <h6 class="text-uppercase text-secondary fw-bold small mb-0">
                            <i class="fas fa-heart-pulse text-primary me-2"></i>3. Emergency Contact <span class="text-muted fw-normal text-none small">(Optional)</span>
                        </h6>
                        <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">Safety</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="ap-em-name" class="form-label small fw-semibold text-dark">Contact Full Name</label>
                            <input type="text" class="form-control rounded-3" id="ap-em-name" name="emergency_name" placeholder="e.g. Robert Johnson">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="ap-em-rel" class="form-label small fw-semibold text-dark">Relationship</label>
                            <input type="text" class="form-control rounded-3" id="ap-em-rel" name="emergency_relation" placeholder="e.g. Spouse, Parent, Sibling">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="ap-em-phone" class="form-label small fw-semibold text-dark">Emergency Contact Phone</label>
                            <input type="tel" class="form-control rounded-3" id="ap-em-phone" name="emergency_phone" placeholder="(555) 111-2222">
                        </div>
                    </div>
                </div>

                <!-- 4. PREFERRED PHARMACY (Optional) -->
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                        <h6 class="text-uppercase text-secondary fw-bold small mb-0">
                            <i class="fas fa-prescription-bottle-medical text-primary me-2"></i>4. Preferred Pharmacy <span class="text-muted fw-normal text-none small">(Optional)</span>
                        </h6>
                        <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">E-Prescribing</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="ap-pharm-name" class="form-label small fw-semibold text-dark">Pharmacy Name</label>
                            <input type="text" class="form-control rounded-3" id="ap-pharm-name" name="preferred_pharmacy_name" placeholder="e.g. Walgreens #1204">
                        </div>
                        <div class="col-12 col-md-5">
                            <label for="ap-pharm-address" class="form-label small fw-semibold text-dark">Pharmacy Street Address</label>
                            <input type="text" class="form-control rounded-3" id="ap-pharm-address" name="preferred_pharmacy_address" placeholder="e.g. 500 Collins Ave, Miami Beach, FL">
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="ap-pharm-phone" class="form-label small fw-semibold text-dark">Pharmacy Phone</label>
                            <input type="tel" class="form-control rounded-3" id="ap-pharm-phone" name="preferred_pharmacy_phone" placeholder="(305) 538-1571">
                        </div>
                    </div>
                </div>

                <!-- 5. PORTAL ACCOUNT ACCESS & SECURITY -->
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                        <h6 class="text-uppercase text-secondary fw-bold small mb-0">
                            <i class="fas fa-lock text-primary me-2"></i>5. Patient Portal Access &amp; Credentials
                        </h6>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 small">Secure Authentication</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="ap-password" class="form-label small fw-semibold text-dark">Temporary Password</label>
                            <div class="input-group">
                                <input type="text" class="form-control rounded-start-3 font-monospace" id="ap-password" name="password" placeholder="Temporary password" required>
                                <button class="btn btn-outline-secondary" type="button" id="ap-gen-pass-btn" title="Generate New Secure Password">
                                    <i class="fas fa-arrows-rotate me-1"></i> Generate
                                </button>
                                <button class="btn btn-outline-secondary" type="button" id="ap-copy-pass-btn" title="Copy Password">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <div class="form-text small">A temporary password the patient will use to log into the Dior Medical Portal.</div>
                        </div>

                        <div class="col-12 col-md-6 d-flex align-items-center">
                            <div class="form-check p-3 bg-light rounded-3 border w-100 mt-md-4">
                                <input class="form-check-input ms-0 me-2" type="checkbox" id="ap-send-email" name="send_welcome_email" value="1" checked>
                                <label class="form-check-label small fw-semibold text-dark" for="ap-send-email">
                                    <i class="fas fa-paper-plane text-primary me-1"></i> Send welcome email with login credentials to patient
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SUBMIT ACTIONS (100% Native Bootstrap 5) -->
                <div class="d-flex flex-wrap align-items-center gap-3 pt-3 border-top">
                    <button type="submit" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm" id="dior-add-patient-submit-btn">
                        <i class="fas fa-user-plus me-2"></i>Create Patient Account
                    </button>
                    <button type="button" class="btn btn-outline-secondary rounded-3 fw-semibold py-2 px-4" id="dior-add-patient-reset-btn">
                        <i class="fas fa-rotate-left me-1"></i>Clear Form
                    </button>
                    <div id="ap-spinner" class="d-none align-items-center text-primary small fw-semibold">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Creating patient record &amp; syncing profile...
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
(function() {
    'use strict';

    function generateSecurePassword(length) {
        length = length || 12;
        var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
        var pwd = '';
        for (var i = 0; i < length; i++) {
            pwd += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return pwd;
    }

    function calculateAge(dobString) {
        if (!dobString) return null;
        var birthDate = new Date(dobString);
        var today = new Date();
        if (isNaN(birthDate.getTime())) return null;
        var age = today.getFullYear() - birthDate.getFullYear();
        var m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age;
    }

    function initAddPatientForm() {
        var form = document.getElementById('dior-add-patient-form');
        if (!form || form.dataset.initReady === '1') return;
        form.dataset.initReady = '1';

        var passInput = document.getElementById('ap-password');
        var genBtn = document.getElementById('ap-gen-pass-btn');
        var copyBtn = document.getElementById('ap-copy-pass-btn');
        var dobInput = document.getElementById('ap-dob');
        var ageBadge = document.getElementById('ap-age-badge');
        var submitBtn = document.getElementById('dior-add-patient-submit-btn');
        var resetBtn = document.getElementById('dior-add-patient-reset-btn');
        var alertBox = document.getElementById('add-patient-alert');
        var spinner = document.getElementById('ap-spinner');

        // Initial default password
        if (passInput && !passInput.value) {
            passInput.value = generateSecurePassword(12);
        }

        // Generate password button
        if (genBtn && passInput) {
            genBtn.addEventListener('click', function() {
                passInput.value = generateSecurePassword(12);
                passInput.focus();
            });
        }

        // Copy password button
        if (copyBtn && passInput) {
            copyBtn.addEventListener('click', function() {
                if (!passInput.value) return;
                navigator.clipboard.writeText(passInput.value).then(function() {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Password Copied',
                            text: 'Temporary password copied to clipboard.',
                            toast: true,
                            position: 'top-end',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                });
            });
        }

        // Real-time Age calculation from DOB
        if (dobInput && ageBadge) {
            dobInput.addEventListener('change', function() {
                var age = calculateAge(this.value);
                if (age === null || age < 0) {
                    ageBadge.className = 'badge bg-light text-secondary border rounded-pill px-2 py-1 small';
                    ageBadge.textContent = 'Must be 18+';
                } else if (age < 18) {
                    ageBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 small';
                    ageBadge.textContent = 'Age: ' + age + ' (Under 18 - Ineligible)';
                } else {
                    ageBadge.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small';
                    ageBadge.textContent = 'Age: ' + age + ' (Eligible 18+)';
                }
            });
        }

        // Reset button
        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                form.reset();
                passInput.value = generateSecurePassword(12);
                if (ageBadge) {
                    ageBadge.className = 'badge bg-light text-secondary border rounded-pill px-2 py-1 small';
                    ageBadge.textContent = 'Must be 18+';
                }
                if (alertBox) {
                    alertBox.className = 'alert d-none';
                    alertBox.textContent = '';
                }
            });
        }

        // Form Submit
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Client-side age check
            var age = calculateAge(dobInput.value);
            if (age !== null && age < 18) {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Underage Patient',
                        text: 'Dior Medical adult telehealth care requires patient to be at least 18 years of age.'
                    });
                }
                dobInput.focus();
                return;
            }

            // Disable button & show spinner
            submitBtn.disabled = true;
            if (spinner) {
                spinner.classList.remove('d-none');
                spinner.classList.add('d-flex');
            }
            if (alertBox) {
                alertBox.className = 'alert d-none';
                alertBox.textContent = '';
            }

            var formData = new FormData(form);

            fetch('<?php echo esc_url($ajax_url); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                submitBtn.disabled = false;
                if (spinner) {
                    spinner.classList.add('d-none');
                    spinner.classList.remove('d-flex');
                }

                if (res.success) {
                    var p = res.data;
                    var successMsg = p.message || 'Patient successfully created and synced with the portal!';

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Patient Created!',
                            html: '<div class="text-start small mt-2">' +
                                  '<p class="mb-2"><strong>Patient Name:</strong> ' + (p.name || '') + '</p>' +
                                  '<p class="mb-2"><strong>Patient ID:</strong> <span class="badge bg-light text-dark border">' + (p.patient_uid || '') + '</span></p>' +
                                  '<p class="mb-2"><strong>Login Email:</strong> ' + (p.email || '') + '</p>' +
                                  '<p class="mb-3"><strong>Temporary Password:</strong> <code class="bg-light px-2 py-1 border rounded">' + (p.password || '') + '</code></p>' +
                                  '<div class="alert alert-success py-2 px-3 small mb-0"><i class="fas fa-check-circle me-1"></i> Patient profile is fully initialized and accessible in both Doctor and Patient Dashboards.</div>' +
                                  '</div>',
                            showCancelButton: true,
                            confirmButtonText: '<i class="fas fa-users me-1"></i> View in All Patients',
                            cancelButtonText: '<i class="fas fa-user-plus me-1"></i> Add Another Patient',
                            confirmButtonColor: '#2C6CB1',
                            cancelButtonColor: '#64748B'
                        }).then(function(result) {
                            if (result.isConfirmed) {
                                if (typeof diorSwitchDocTab === 'function') {
                                    diorSwitchDocTab('doc-patients-all');
                                } else if (typeof diorSwitchTab === 'function') {
                                    diorSwitchTab('doc-patients-all');
                                }
                                location.reload();
                            } else {
                                form.reset();
                                passInput.value = generateSecurePassword(12);
                                if (ageBadge) {
                                    ageBadge.className = 'badge bg-light text-secondary border rounded-pill px-2 py-1 small';
                                    ageBadge.textContent = 'Must be 18+';
                                }
                            }
                        });
                    } else {
                        alert(successMsg);
                        form.reset();
                    }
                } else {
                    var errorMsg = (res.data && res.data.message) ? res.data.message : 'Unable to create patient. Please check required fields.';
                    if (alertBox) {
                        alertBox.className = 'alert alert-danger mb-4 rounded-3 shadow-sm py-2 px-3 small';
                        alertBox.innerHTML = '<i class="fas fa-triangle-exclamation me-1"></i> ' + errorMsg;
                    }
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error Creating Patient',
                            text: errorMsg,
                            confirmButtonColor: '#2C6CB1'
                        });
                    }
                }
            })
            .catch(function(err) {
                submitBtn.disabled = false;
                if (spinner) {
                    spinner.classList.add('d-none');
                    spinner.classList.remove('d-flex');
                }
                var errorText = 'Network or server error: ' + (err.message || 'Please try again.');
                if (alertBox) {
                    alertBox.className = 'alert alert-danger mb-4 rounded-3 shadow-sm py-2 px-3 small';
                    alertBox.innerHTML = '<i class="fas fa-triangle-exclamation me-1"></i> ' + errorText;
                }
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Request Failed',
                        text: errorText,
                        confirmButtonColor: '#2C6CB1'
                    });
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAddPatientForm);
    } else {
        initAddPatientForm();
    }

    // Also hook when tab is switched to doc-patients-add
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-tab="doc-patients-add"]');
        if (btn) {
            setTimeout(initAddPatientForm, 100);
        }
    });
})();
</script>
