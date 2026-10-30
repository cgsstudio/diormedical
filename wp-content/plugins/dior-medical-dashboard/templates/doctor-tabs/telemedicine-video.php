<?php
if (!defined('ABSPATH')) { exit; }
global $wpdb;
$doctor_id = get_current_user_id();
$doctor_nonce = wp_create_nonce('dior_doctor_nonce');
$doctor_ids = class_exists('Dior_Doctor_Resolver') ? Dior_Doctor_Resolver::get_accessible_doctor_ids($doctor_id) : [$doctor_id];
$doctor_ids = array_values(array_unique(array_filter(array_map('intval', (array) $doctor_ids))));
if (!$doctor_ids) { $doctor_ids = [$doctor_id]; }
$placeholders = implode(',', array_fill(0, count($doctor_ids), '%d'));

$sql = "SELECT t.*, p.first_name, p.last_name, p.avatar_url, p.patient_uid, a.appt_date, a.appt_time, u.display_name AS doctor_name
        FROM {$wpdb->prefix}dior_telemedicine t
        LEFT JOIN {$wpdb->prefix}dior_patients p ON p.user_id = t.patient_id
        LEFT JOIN {$wpdb->prefix}dior_appointments a ON a.appt_uid = t.appointment_uid
        LEFT JOIN {$wpdb->users} u ON u.ID = t.doctor_id
        WHERE t.doctor_id IN ($placeholders) ORDER BY t.updated_at DESC LIMIT 200";
$video_consultations = $wpdb->get_results($wpdb->prepare($sql, ...$doctor_ids));
foreach ((array) $video_consultations as $vc) {
    $vc->id = $vc->tele_uid;
    $vc->patient_name = trim(($vc->first_name ?? '') . ' ' . ($vc->last_name ?? '')) ?: (get_userdata((int) $vc->patient_id)->display_name ?? 'Patient');
    $vc->patient_avatar = $vc->avatar_url ?: '';
    $vc->doctor_name = $vc->doctor_name ?: 'Doctor';
    $vc->date = !empty($vc->appt_date) ? date_i18n('M j, Y', strtotime($vc->appt_date)) : date_i18n('M j, Y', strtotime($vc->created_at));
    $vc->time = !empty($vc->appt_time) ? $vc->appt_time : '—';
    $vc->status_class = strtolower($vc->status) === 'completed' ? 'badge bg-success-subtle text-success border border-success-subtle' : 
                        (strtolower($vc->status) === 'cancelled' ? 'badge bg-danger-subtle text-danger border border-danger-subtle' : 
                        (strtolower($vc->status) === 'in progress' ? 'badge bg-warning-subtle text-warning border border-warning-subtle' : 'badge bg-primary-subtle text-primary border border-primary-subtle'));
}

// Fetch all registered clinic patients for the selector
$all_patients = $wpdb->get_results("
    SELECT user_id, first_name, last_name, patient_uid, phone, email, avatar_url
    FROM {$wpdb->prefix}dior_patients
    WHERE status <> 'deleted'
    ORDER BY first_name ASC
    LIMIT 400
", ARRAY_A);
if (empty($all_patients)) {
    $raw_users = get_users(['role__in' => ['patient', 'subscriber', 'customer'], 'number' => 100]);
    $all_patients = [];
    foreach ($raw_users as $ru) {
        $all_patients[] = [
            'user_id' => $ru->ID,
            'first_name' => $ru->first_name ?: $ru->display_name,
            'last_name' => $ru->last_name,
            'patient_uid' => get_user_meta($ru->ID, 'patient_id', true) ?: ('DM-' . (10000 + $ru->ID)),
            'phone' => get_user_meta($ru->ID, 'phone', true) ?: '',
            'email' => $ru->user_email,
            'avatar_url' => get_user_meta($ru->ID, 'dior_profile_image', true) ?: '',
        ];
    }
}
$first_pid = !empty($all_patients) ? (int)$all_patients[0]['user_id'] : 0;
?>

<section class="dior-tab-panel documents-tab-panel" id="tab-doc-telemed-video" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="mb-0 text-dark fw-bold fs-4">Virtual Visit &amp; Telehealth Consultation</h4>
            <div class="text-secondary small">Conduct secure video visits, review patient medical records in real-time, and issue prescriptions.</div>
        </div>
        <div>
            <ul class="breadcrumb-list d-flex gap-2 list-unstyled p-0 m-0 align-items-center small">
                <li><a href="#" class="text-primary text-decoration-none"><i class="fas fa-house"></i></a></li>
                <li><span class="text-muted">/</span></li>
                <li><a href="javascript:void(0)" class="text-secondary text-decoration-none">Telemedicine</a></li>
                <li><span class="text-muted">/</span></li>
                <li class="active fw-bold text-dark">Virtual Visit</li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-telemed-video" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-telemed-video');">
            <i class="fas fa-video"></i> Video Consultation
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-telemed-records" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-telemed-records');">
            <i class="fas fa-clock-rotate-left"></i> Virtual Visit Records
        </button>
    </div>

    <!-- 1. Patient Selector & Quick Actions Card -->
    <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-light">
        <div class="row g-3 align-items-center">
            <div class="col-lg-6 col-md-12">
                <label class="form-label small text-uppercase text-secondary fw-bold mb-1" for="dior-tele-patient-select">
                    <i class="fas fa-user-check text-primary me-1"></i> Select Patient for Virtual Visit <span class="text-danger">*</span>
                </label>
                <div class="d-flex align-items-center gap-2">
                    <select id="dior-tele-patient-select" class="form-select rounded-3 py-2 fw-semibold" onchange="diorLoadTelemedicinePatient(this.value)">
                        <option value="">-- Choose a patient from records --</option>
                        <?php foreach ($all_patients as $ap): ?>
                            <option value="<?php echo (int)$ap['user_id']; ?>">
                                <?php echo esc_html(trim($ap['first_name'] . ' ' . $ap['last_name'])) . ' · ' . esc_html($ap['patient_uid']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold text-nowrap shadow-sm" onclick="diorTriggerAddPatientModal()" title="Add New Patient">
                        <i class="fas fa-user-plus me-1"></i> Add Patient
                    </button>
                </div>

            </div>
            <div class="col-lg-6 col-md-12 d-flex flex-wrap align-items-center justify-content-lg-end gap-2 pt-lg-3">
                <button type="button" class="btn btn-success rounded-3 fw-semibold py-2 px-3 shadow-sm" onclick="diorStartVirtualVisit()">
                    <i class="fas fa-video me-1"></i> Start Virtual Visit
                </button>
                <button type="button" class="btn btn-outline-primary rounded-3 fw-semibold py-2 px-3" onclick="diorOpenSelectedPatientProfile()">
                    <i class="fas fa-id-card me-1"></i> Open Full Chart
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Dynamic Patient Clinical Summary Panel -->
    <div id="dior-tele-patient-panel" class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white" style="display: none;">
        <!-- Patient Header Banner -->
        <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-3 mb-3 gap-3">
            <div class="d-flex align-items-center gap-3">
                <img id="tele-patient-avatar" src="https://ui-avatars.com/api/?name=Patient&background=random" 
                     class="rounded-circle shadow-sm border" style="width: 58px; height: 58px; object-fit: cover;" alt="Patient">
                <div>
                    <h5 class="fw-bold text-dark mb-0 fs-5" id="tele-patient-name">Selected Patient</h5>
                    <div class="d-flex flex-wrap gap-2 align-items-center mt-1">
                        <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small" id="tele-patient-code">DM-00000</span>
                        <span class="small text-muted" id="tele-patient-meta"><i class="fas fa-cake-candles me-1"></i> Age: —</span>
                        <span class="small text-muted" id="tele-patient-phone"><i class="fas fa-phone me-1"></i> —</span>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary rounded-3 fw-semibold" onclick="diorTeleWritePrescription()">
                    <i class="fas fa-prescription me-1"></i> Write Rx
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 fw-semibold" onclick="diorLoadTelemedicinePatient(window.diorActiveTelePatientId)">
                    <i class="fas fa-rotate-right me-1"></i> Refresh Data
                </button>
            </div>
        </div>

        <!-- Clinical Tabs / Overview Grid -->
        <div class="row g-3 mb-4">
            <!-- Medical History & Conditions -->
            <div class="col-lg-6 col-12">
                <div class="p-3 bg-light rounded-4 h-100">
                    <h6 class="fw-bold text-dark mb-2 d-flex align-items-center">
                        <i class="fas fa-notes-medical text-primary me-2"></i> Diagnosed Conditions &amp; Medical History
                    </h6>
                    <div class="small text-secondary mb-2" id="tele-patient-conditions">Loading medical conditions...</div>
                    <div class="small text-muted" id="tele-patient-history"></div>
                </div>
            </div>

            <!-- Allergies & Current Medications -->
            <div class="col-lg-6 col-12">
                <div class="p-3 bg-light rounded-4 h-100">
                    <h6 class="fw-bold text-dark mb-2 d-flex align-items-center">
                        <i class="fas fa-triangle-exclamation text-danger me-2"></i> Allergies &amp; Active Medications
                    </h6>
                    <div class="mb-2">
                        <span class="small fw-semibold text-danger d-block mb-1">Drug Allergies:</span>
                        <div class="small text-secondary" id="tele-patient-allergies">No known drug allergies reported.</div>
                    </div>
                    <div>
                        <span class="small fw-semibold text-dark d-block mb-1">Current Medications:</span>
                        <div class="small text-secondary" id="tele-patient-medications">None reported.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Previous Prescriptions Table -->
        <div class="mb-4">
            <h6 class="fw-bold text-dark mb-2 d-flex align-items-center">
                <i class="fas fa-pills text-success me-2"></i> Previous Prescriptions &amp; Therapy
            </h6>
            <div class="table-responsive border rounded-3">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Medication</th>
                            <th>Dosage &amp; Instructions</th>
                            <th>Status</th>
                            <th>Date Issued</th>
                        </tr>
                    </thead>
                    <tbody id="tele-prescriptions-tbody">
                        <tr><td colspan="4" class="text-center py-3 text-muted">No previous prescriptions on record.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Uploaded Documents & Records Table -->
        <div>
            <h6 class="fw-bold text-dark mb-2 d-flex align-items-center">
                <i class="fas fa-folder-open text-primary me-2"></i> Uploaded Documents &amp; Lab Records
            </h6>
            <div class="table-responsive border rounded-3">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Document Title</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tele-documents-tbody">
                        <tr><td colspan="4" class="text-center py-3 text-muted">No documents uploaded for this patient.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 3. Scheduled Virtual Consultations Card & Log -->
    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2><i class="fas fa-video text-primary me-2"></i>Scheduled Consultations</h2>
                <div class="docs-title-line"></div>
            </div>

            <div class="docs-actions-wrapper">
                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">
                    <input type="text" id="tele-search-input" class="form-control border-start-0 ps-0" placeholder="Search consultations..." onkeyup="diorFilterTeleConsultations(this.value)">
                </div>
                <div class="docs-actions-group">
                    <button class="docs-icon-btn docs-btn-info btn" aria-label="Refresh data" title="Refresh Page" onclick="window.location.reload()">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="docs-table-wrapper">
            <table class="docs-table table table-hover" id="tele-consultations-table">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center"><input type="checkbox" class="docs-checkbox form-check-input"></th>
                        <th>CONSULTATION ID <i class="fas fa-sort"></i></th>
                        <th>PATIENT <i class="fas fa-sort"></i></th>
                        <th>DOCTOR <i class="fas fa-sort"></i></th>
                        <th>DATE <i class="fas fa-sort"></i></th>
                        <th>TIME <i class="fas fa-sort"></i></th>
                        <th>STATUS <i class="fas fa-sort"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="tele-consultations-tbody">
                    <?php if (!empty($video_consultations)): ?>
                    <?php foreach ($video_consultations as $vc): ?>
                        <tr data-patient-id="<?php echo esc_attr($vc->patient_id); ?>">
                            <td class="text-center"><input type="checkbox" class="docs-checkbox form-check-input"></td>
                            <td><span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small"><?php echo esc_html($vc->id); ?></span></td>
                            <td>
                                <div class="docs-user-profile d-flex align-items-center gap-2">
                                    <img src="<?php echo esc_url($vc->patient_avatar ?: ('https://ui-avatars.com/api/?name=' . rawurlencode($vc->patient_name))); ?>"
                                        class="docs-user-avatar rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" alt="<?php echo esc_attr($vc->patient_name); ?>"
                                        onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($vc->patient_name); ?>&background=random'">
                                    <a href="javascript:void(0)" class="docs-user-name fw-semibold text-dark text-decoration-none" onclick="diorLoadTelemedicinePatient(<?php echo (int)$vc->patient_id; ?>)">
                                        <?php echo esc_html($vc->patient_name); ?>
                                    </a>
                                </div>
                            </td>
                            <td><?php echo esc_html($vc->doctor_name); ?></td>
                            <td><i class="far fa-calendar docs-icon-blue me-1"></i> <?php echo esc_html($vc->date); ?></td>
                            <td><?php echo esc_html($vc->time); ?></td>
                            <td><span class="<?php echo esc_attr($vc->status_class); ?> rounded-pill px-2 py-1 small"><?php echo esc_html($vc->status); ?></span></td>
                            <td>
                                <div class="docs-row-actions d-flex gap-1">
                                    <button type="button" class="docs-action-btn-sm docs-btn-primary btn" title="Review Patient Chart" onclick="diorLoadTelemedicinePatient(<?php echo (int)$vc->patient_id; ?>)">
                                        <i class="fas fa-chart-user"></i>
                                    </button>
                                    <button type="button" class="docs-action-btn-sm docs-btn-success btn" title="Join Video Call" onclick="diorStartVirtualVisit(<?php echo (int)$vc->patient_id; ?>)">
                                        <i class="fas fa-video"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-video-slash d-block fs-2 mb-2"></i>No virtual consultations found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Scripts for Virtual Visit dynamic controller -->
    <script>
    window.diorActiveTelePatientId = 0;

    window.diorLoadTelemedicinePatient = function(patientId) {
        if (!patientId) {
            var panel = document.getElementById('dior-tele-patient-panel');
            if (panel) panel.style.display = 'none';
            window.diorActiveTelePatientId = 0;
            return;
        }

        window.diorActiveTelePatientId = parseInt(patientId, 10);
        var select = document.getElementById('dior-tele-patient-select');
        if (select && select.value != patientId) {
            select.value = patientId;
        }

        var panel = document.getElementById('dior-tele-patient-panel');
        if (panel) panel.style.display = 'block';

        var ajaxUrl = (window.dior_doctor_vars && window.dior_doctor_vars.ajax_url) ||
                      (window.dior_vars && window.dior_vars.ajax_url) ||
                      '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var nonce = (window.dior_doctor_vars && window.dior_doctor_vars.nonce) ||
                    (window.dior_vars && window.dior_vars.nonce) ||
                    '<?php echo esc_js($doctor_nonce); ?>';

        var fd = new FormData();
        fd.append('action', 'dior_doctor_chart_data');
        fd.append('nonce', nonce);
        fd.append('patient_id', patientId);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.success || !res.data || !res.data.patient) {
                throw new Error((res.data && res.data.message) || 'Failed to load patient records.');
            }
            var p = res.data.patient;
            var fullName = (p.first_name || '') + ' ' + (p.last_name || '');
            if (!fullName.trim()) fullName = p.display_name || 'Patient';

            var nameEl = document.getElementById('tele-patient-name');
            if (nameEl) nameEl.textContent = fullName;
            var avatarEl = document.getElementById('tele-patient-avatar');
            if (avatarEl) avatarEl.src = p.avatar_url || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(fullName) + '&background=random');
            var codeEl = document.getElementById('tele-patient-code');
            if (codeEl) codeEl.textContent = p.patient_uid || ('ID: ' + p.user_id);
            var metaEl = document.getElementById('tele-patient-meta');
            if (metaEl) metaEl.innerHTML = '<i class="fas fa-cake-candles me-1"></i> DOB: ' + (p.dob || '—') + ' • Gender: ' + (p.gender || '—');
            var phoneEl = document.getElementById('tele-patient-phone');
            if (phoneEl) phoneEl.innerHTML = '<i class="fas fa-phone me-1"></i> ' + (p.phone || '—');

            // Conditions & History
            var condEl = document.getElementById('tele-patient-conditions');
            if (condEl) condEl.textContent = p.conditions || 'No major active conditions recorded.';
            var histEl = document.getElementById('tele-patient-history');
            if (histEl) histEl.textContent = p.medical_history ? ('History: ' + p.medical_history) : '';

            // Allergies & Medications
            var algEl = document.getElementById('tele-patient-allergies');
            if (algEl) algEl.textContent = p.allergies || 'No known drug allergies reported.';
            var medEl = document.getElementById('tele-patient-medications');
            if (medEl) medEl.textContent = p.medications || 'None reported.';

            // Prescriptions Table
            var rxTbody = document.getElementById('tele-prescriptions-tbody');
            if (rxTbody) {
                var rxs = p.prescriptions || [];
                if (rxs.length > 0) {
                    var html = '';
                    rxs.forEach(function(rx) {
                        html += '<tr>' +
                            '<td class="fw-semibold text-dark">' + (rx.medication || '—') + '</td>' +
                            '<td>' + (rx.instructions || 'As directed') + '</td>' +
                            '<td><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small">' + (rx.status || 'Active') + '</span></td>' +
                            '<td class="text-muted">' + (rx.created_at || '—') + '</td>' +
                            '</tr>';
                    });
                    rxTbody.innerHTML = html;
                } else {
                    rxTbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">No previous prescriptions on record.</td></tr>';
                }
            }

            // Documents Table
            var docTbody = document.getElementById('tele-documents-tbody');
            if (docTbody) {
                var docs = p.documents || [];
                if (docs.length > 0) {
                    var dHtml = '';
                    docs.forEach(function(d) {
                        var dUrl = '<?php echo esc_js(home_url('/')); ?>?dior_action=download_doc&doc_id=' + encodeURIComponent(d.id || '') + '&patient_id=' + encodeURIComponent(p.user_id);
                        dHtml += '<tr>' +
                            '<td class="fw-semibold text-dark">' + (d.title || 'Document') + '</td>' +
                            '<td>' + (d.category || 'Clinical Record') + '</td>' +
                            '<td class="text-muted">' + (d.date || '—') + '</td>' +
                            '<td><a href="' + dUrl + '" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2 rounded-2" download><i class="fas fa-file-arrow-down me-1"></i> Download</a></td>' +
                            '</tr>';
                    });
                    docTbody.innerHTML = dHtml;
                } else {
                    docTbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">No documents uploaded for this patient.</td></tr>';
                }
            }
        })
        .catch(function(err) {
            console.error('Error fetching patient chart:', err);
        });
    };

    window.diorStartVirtualVisit = function(patientId) {
        var pid = patientId || window.diorActiveTelePatientId || document.getElementById('dior-tele-patient-select').value;
        if (!pid) {
            if (window.Swal) Swal.fire({icon: 'warning', title: 'No Patient Selected', text: 'Please select a patient to start the virtual visit.'});
            return;
        }
        if (window.Swal) {
            Swal.fire({
                title: 'Start Virtual Visit',
                text: 'Launch secure HIPAA-compliant telehealth video session with this patient?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10B981',
                confirmButtonText: '<i class="fas fa-video me-1"></i> Launch Video Call',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    window.open('https://meet.jit.si/DiorMedicalConsultation_' + pid, '_blank');
                }
            });
        } else {
            window.open('https://meet.jit.si/DiorMedicalConsultation_' + pid, '_blank');
        }
    };

    window.diorOpenSelectedPatientProfile = function() {
        var pid = window.diorActiveTelePatientId || document.getElementById('dior-tele-patient-select').value;
        if (!pid) {
            if (window.Swal) Swal.fire({icon: 'warning', title: 'No Patient Selected', text: 'Please select a patient first.'});
            return;
        }
        window.diorSelectedPatientId = pid;
        if (typeof window.diorDocSwitchTab === 'function') {
            window.diorDocSwitchTab('doc-patients-profile');
        } else if (typeof window.diorSwitchTab === 'function') {
            window.diorSwitchTab('doc-patients-profile');
        }
        setTimeout(function() {
            if (typeof window.diorLoadPatientProfile === 'function') {
                window.diorLoadPatientProfile(pid);
            }
        }, 120);
    };

    window.diorTriggerAddPatientModal = function() {
        var patientModalEl = document.getElementById('diorPatientModal');
        if (patientModalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(patientModalEl).show();
        } else if (typeof window.diorDocSwitchTab === 'function') {
            window.diorDocSwitchTab('doc-patients-all');
        }
    };

    window.diorTeleWritePrescription = function() {
        if (typeof window.diorDocSwitchTab === 'function') {
            window.diorDocSwitchTab('doc-prescriptions');
        }
    };

    window.diorFilterTeleConsultations = function(query) {
        var q = (query || '').toLowerCase().trim();
        var rows = document.querySelectorAll('#tele-consultations-tbody tr');
        rows.forEach(function(r) {
            var text = r.textContent.toLowerCase();
            r.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
        });
    };
    </script>
</section>