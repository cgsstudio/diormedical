<?php
/**
 * Doctor Dashboard — Documents & Consent
 * Tab: Upload Documents (#tab-doc-documents-upload)
 */
global $wpdb;
$doc_table = $wpdb->prefix . 'dior_documents';
$patients_table = $wpdb->prefix . 'dior_patients';
$users_table = $wpdb->users;

$doctor_doc_nonce = wp_create_nonce('dior_doctor_nonce');
$doctor_context = class_exists('Dior_Doctor_Dashboard')
    ? Dior_Doctor_Dashboard::get_single_doctor_context(get_current_user_id())
    : ['user_id' => get_current_user_id(), 'ids' => [get_current_user_id()], 'appointment_ids' => [get_current_user_id()], 'profile' => []];
$doctor_user_id = (int) ($doctor_context['user_id'] ?? 0);
$doctor_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($doctor_context['ids'] ?? [$doctor_user_id])), static function ($id) { return $id > 0; })));
$appointment_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($doctor_context['appointment_ids'] ?? $doctor_ids)), static function ($id) { return $id > 0; })));
$doctor_display_name = (string) (($doctor_context['profile']['full_name'] ?? '') ?: (wp_get_current_user()->display_name ?: 'Doctor'));
$doc_placeholders = $doctor_ids ? implode(',', array_fill(0, count($doctor_ids), '%d')) : '';
$appt_placeholders = $appointment_ids ? implode(',', array_fill(0, count($appointment_ids), '%d')) : '';

// 1. Fetch real documents authored by this doctor or belonging to this doctor's patients.
$doc_records = [];
$signed_consents_records = [];
if ($doctor_ids && $appointment_ids) {
    $documents_sql = "
        SELECT d.*,
               COALESCE(p.patient_uid, CONCAT('DM-', (10000 + d.patient_id))) AS patient_code,
               COALESCE(NULLIF(CONCAT(p.first_name, ' ', p.last_name), ' '), u.display_name, 'Patient') AS patient_name,
               p.avatar_url AS patient_avatar,
               u.user_email AS patient_email,
               COALESCE(author.display_name, 'Staff') AS author_name
        FROM $doc_table d
        LEFT JOIN $patients_table p ON p.user_id = d.patient_id
        LEFT JOIN $users_table u ON u.ID = d.patient_id
        LEFT JOIN $users_table author ON author.ID = d.author_id
        WHERE d.is_deleted = 0
          AND (d.author_id IN ($doc_placeholders)
               OR d.patient_id IN (SELECT a.patient_id FROM {$wpdb->prefix}dior_appointments a
                                   WHERE a.is_deleted = 0 AND a.doctor_id IN ($appt_placeholders)))
        ORDER BY d.created_at DESC
        LIMIT 300";
    $doc_records = $wpdb->get_results(
        $wpdb->prepare($documents_sql, array_merge($doctor_ids, $appointment_ids)),
        ARRAY_A
    );

    $consents_sql = "
        SELECT d.*,
               COALESCE(p.patient_uid, CONCAT('DM-', (10000 + d.patient_id))) AS patient_code,
               COALESCE(NULLIF(CONCAT(p.first_name, ' ', p.last_name), ' '), u.display_name, 'Patient') AS patient_name,
               p.avatar_url AS patient_avatar,
               u.user_email AS patient_email
        FROM $doc_table d
        LEFT JOIN $patients_table p ON p.user_id = d.patient_id
        LEFT JOIN $users_table u ON u.ID = d.patient_id
        WHERE d.is_deleted = 0
          AND (d.category LIKE '%Consent%' OR d.title LIKE '%Consent%')
          AND (d.author_id IN ($doc_placeholders)
               OR d.patient_id IN (SELECT a.patient_id FROM {$wpdb->prefix}dior_appointments a
                                   WHERE a.is_deleted = 0 AND a.doctor_id IN ($appt_placeholders)))
        ORDER BY d.created_at DESC
        LIMIT 200";
    $signed_consents_records = $wpdb->get_results(
        $wpdb->prepare($consents_sql, array_merge($doctor_ids, $appointment_ids)),
        ARRAY_A
    );
}
if (!is_array($doc_records)) {
    $doc_records = [];
}
if (!is_array($signed_consents_records)) {
    $signed_consents_records = [];
}

// 2. Fetch all registered patients for the upload modal dropdown
$dp_map = [];
$dp_list = $wpdb->get_results("SELECT user_id, patient_uid, first_name, last_name, email FROM $patients_table WHERE status <> 'deleted'", ARRAY_A);
if (!empty($dp_list)) {
    foreach ($dp_list as $dp) {
        $dp_map[(int)$dp['user_id']] = $dp;
    }
}

$patient_subscribers = get_users([
    'role__in' => ['patient', 'subscriber', 'customer'],
    'orderby'  => 'display_name',
    'order'    => 'ASC',
    'number'   => 500
]);

$doctor_patient_dropdown = [];
$seen_patients = [];
foreach ($patient_subscribers as $ps) {
    $p_meta = $dp_map[$ps->ID] ?? null;
    $p_code = $p_meta['patient_uid'] ?? ('DM-' . (10000 + $ps->ID));
    $p_name = !empty($p_meta['first_name']) ? trim($p_meta['first_name'] . ' ' . ($p_meta['last_name'] ?? '')) : $ps->display_name;
    $doctor_patient_dropdown[] = [
        'user_id' => $ps->ID,
        'code'    => $p_code,
        'name'    => $p_name,
        'email'   => $ps->user_email,
    ];
    $seen_patients[$ps->ID] = true;
}

if (!empty($dp_list)) {
    foreach ($dp_list as $dp) {
        $uid = (int)$dp['user_id'];
        if ($uid > 0 && empty($seen_patients[$uid])) {
            $doctor_patient_dropdown[] = [
                'user_id' => $uid,
                'code'    => $dp['patient_uid'] ?: ('DM-' . (10000 + $uid)),
                'name'    => trim($dp['first_name'] . ' ' . ($dp['last_name'] ?? '')),
                'email'   => $dp['email'] ?? '',
            ];
            $seen_patients[$uid] = true;
        }
    }
}
?>
<section class="dior-tab-panel" id="tab-doc-documents-upload" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Upload Documents</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list"
                style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="#"><i class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)"
                        style="color: #64748B; text-decoration: none; font-size: 14px;">Documents & Consent</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">Upload
                        Documents</span></li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-documents-upload" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-documents-upload');">
            <i class="fas fa-file-arrow-up"></i> Upload Documents
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-documents-templates" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-documents-templates');">
            <i class="fas fa-file-lines"></i> Consent Templates
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-documents-signed" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-documents-signed');">
            <i class="fas fa-file-signature"></i> Signed Consent
        </button>
    </div>

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2>Upload Documents</h2>
                <div class="docs-title-line"></div>
            </div>

            <div class="docs-actions-wrapper">
                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">
                    <input type="text" id="doc-upload-search-input" class="form-control border-start-0 ps-0" placeholder="Search records..." onkeyup="diorFilterDoctorUploadDocs(this.value)">
                </div>
                <div class="docs-actions-group">
                    <button type="button" class="docs-icon-btn docs-btn-primary btn" aria-label="Add new record" title="Upload Patient Document" onclick="diorOpenDoctorDocUploadModal()">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" class="docs-icon-btn docs-btn-success btn" aria-label="Export to Excel" title="Export CSV" onclick="diorExportDoctorDocsCSV()">
                        <i class="fas fa-file-arrow-down"></i>
                    </button>
                    <button type="button" class="docs-icon-btn docs-btn-info btn" aria-label="Refresh data" title="Refresh" onclick="window.location.reload()">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="docs-table-wrapper">
            <table class="docs-table table table-hover">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center"><input type="checkbox"
                                class="docs-checkbox form-check-input" onclick="diorToggleAllDoctorDocs(this)"></th>
                        <th>DOCUMENT ID <i class="fas fa-sort"></i></th>
                        <th>PATIENT <i class="fas fa-sort"></i></th>
                        <th>DOCUMENT TYPE <i class="fas fa-sort"></i></th>
                        <th>DOCUMENT NAME <i class="fas fa-sort"></i></th>
                        <th>UPLOAD DATE <i class="fas fa-sort"></i></th>
                        <th>UPLOADED BY <i class="fas fa-sort"></i></th>
                        <th>STATUS <i class="fas fa-sort"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="doc-uploaded-tbody">
                    <?php if (!empty($doc_records)): ?>
                        <?php foreach ($doc_records as $doc): ?>
                            <?php
                            $doc_uid = $doc['doc_uid'] ?: ('DOC-' . $doc['id']);
                            $p_id = (int)$doc['patient_id'];
                            $p_name = $doc['patient_name'] ?: 'Patient';
                            $p_avatar = $doc['patient_avatar'] ?: ('https://ui-avatars.com/api/?name=' . urlencode($p_name) . '&background=random');
                            $doc_date = date('M j, Y', strtotime($doc['created_at']));
                            $download_url = add_query_arg([
                                'dior_action' => 'download_doc',
                                'doc_id'      => $doc_uid,
                                'patient_id'  => $p_id
                            ], home_url('/'));
                            ?>
                            <tr id="doc-row-<?php echo esc_attr($doc_uid); ?>" data-patient-id="<?php echo esc_attr($p_id); ?>" data-doc-uid="<?php echo esc_attr($doc_uid); ?>">
                                <td class="text-center">
                                    <input type="checkbox" class="docs-checkbox form-check-input" value="<?php echo esc_attr($doc_uid); ?>">
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small"><?php echo esc_html($doc_uid); ?></span>
                                </td>
                                <td>
                                    <div class="docs-user-profile d-flex align-items-center gap-2">
                                        <img src="<?php echo esc_url($p_avatar); ?>" class="docs-user-avatar rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" alt="<?php echo esc_attr($p_name); ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($p_name); ?>&background=random'">
                                        <div>
                                            <span class="docs-user-name fw-semibold text-dark d-block"><?php echo esc_html($p_name); ?></span>
                                            <small class="text-muted"><?php echo esc_html($doc['patient_code'] ?: ('ID: ' . $p_id)); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html($doc['category'] ?: 'Clinical Record'); ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo esc_html($doc['title'] ?: 'Medical Document'); ?></div>
                                    <small class="text-muted"><?php echo esc_html(($doc['file_type'] ?: 'PDF') . ' • ' . ($doc['file_size_formatted'] ?: '150 KB')); ?></small>
                                </td>
                                <td><i class="far fa-calendar docs-icon-blue me-1"></i> <?php echo esc_html($doc_date); ?></td>
                                <td><?php echo esc_html($doc['author_name'] ?: 'Doctor'); ?></td>
                                <td><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small"><i class="fas fa-circle-check me-1"></i> Available</span></td>
                                <td>
                                    <div class="docs-row-actions d-flex gap-1">
                                        <a href="<?php echo esc_url($download_url); ?>" target="_blank" class="docs-action-btn-sm docs-btn-edit btn" title="Download Document" download>
                                            <i class="fas fa-file-arrow-down"></i>
                                        </a>
                                        <button type="button" class="docs-action-btn-sm docs-btn-delete btn" title="Delete Document" onclick="diorDoctorDeleteDoc(this, '<?php echo esc_attr($doc_uid); ?>', <?php echo $p_id; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="dior-doc-empty-row">
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="fas fa-folder-open fs-3 d-block mb-2 text-secondary opacity-50"></i>
                                No uploaded documents found. Click "+" to upload a patient document.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Doctor Document Upload Modal (Strict 100% Bootstrap 5 Standard) -->
    <div class="modal fade" id="diorDoctorDocUploadModal" tabindex="-1" aria-labelledby="diorDoctorDocUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                    <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="diorDoctorDocUploadModalLabel">
                        <i class="fas fa-file-arrow-up text-primary me-2"></i> Upload Patient Document
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="dior-doctor-doc-upload-form" enctype="multipart/form-data">
                    <div class="modal-body p-4 bg-white">
                        <p class="text-secondary small mb-3">
                            Select a patient and attach clinical records, lab results, prescriptions, or imaging scans. The document will immediately be accessible in the selected patient's dashboard.
                        </p>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="doctor-doc-upload-patient">
                                    Select Patient <span class="text-danger">*</span>
                                </label>
                                <select class="form-select rounded-3 py-2" id="doctor-doc-upload-patient" name="patient_id" required>
                                    <option value="">-- Choose Patient --</option>
                                    <?php foreach ($doctor_patient_dropdown as $popt): ?>
                                        <option value="<?php echo esc_attr($popt['user_id']); ?>">
                                            <?php echo esc_html($popt['name'] . ' (' . $popt['code'] . (!empty($popt['email']) ? ' - ' . $popt['email'] : '') . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="doctor-doc-upload-title">
                                    Document Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control rounded-3 py-2" id="doctor-doc-upload-title" name="doc_title" placeholder="e.g. Lab Results - Complete Blood Count" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="doctor-doc-upload-category">
                                    Document Category <span class="text-danger">*</span>
                                </label>
                                <select class="form-select rounded-3 py-2" id="doctor-doc-upload-category" name="doc_category" required>
                                    <option value="Lab Report">Lab Report</option>
                                    <option value="Clinical Record">Clinical Record</option>
                                    <option value="Diagnostic & Scans">Diagnostic &amp; Scans</option>
                                    <option value="Prescription">Prescription</option>
                                    <option value="Procedure">Procedure</option>
                                    <option value="ID Proof">ID Proof</option>
                                    <option value="Consent Form">Consent Form</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="doctor-doc-upload-file">
                                    Document File <span class="text-danger">*</span>
                                </label>
                                <input type="file" class="form-control rounded-3 py-2" id="doctor-doc-upload-file" name="doc_file" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" required>
                                <div class="form-text text-muted small mt-1">
                                    <i class="fas fa-circle-info text-primary"></i> Supported formats: PDF, PNG, JPG, JPEG, DOC, DOCX (Max 25MB)
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary rounded-3 px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm" id="doctor-doc-upload-submit-btn">
                            <i class="fas fa-cloud-arrow-up me-1"></i> Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    window.diorOpenDoctorDocUploadModal = function() {
        var modalEl = document.getElementById('diorDoctorDocUploadModal');
        if (!modalEl) return;
        var form = document.getElementById('dior-doctor-doc-upload-form');
        if (form) form.reset();
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
    };

    window.diorFilterDoctorUploadDocs = function(val) {
        var q = (val || '').toLowerCase().trim();
        var rows = document.querySelectorAll('#doc-uploaded-tbody tr');
        rows.forEach(function(r) {
            if (r.id === 'dior-doc-empty-row') return;
            var text = r.textContent.toLowerCase();
            r.style.display = text.indexOf(q) !== -1 ? '' : 'none';
        });
    };

    window.diorToggleAllDoctorDocs = function(masterCb) {
        var cbs = document.querySelectorAll('#doc-uploaded-tbody .docs-checkbox');
        cbs.forEach(function(cb) {
            cb.checked = masterCb.checked;
        });
    };

    window.diorDoctorDeleteDoc = function(btn, docUid, patientId) {
        var row = btn.closest('tr');
        var executeDelete = function() {
            var fd = new FormData();
            fd.append('action', 'dior_delete_document');
            var nonce = (window.dior_doctor_vars && window.dior_doctor_vars.nonce) ||
                        (window.dior_vars && window.dior_vars.nonce) ||
                        '<?php echo esc_js($doctor_doc_nonce); ?>';
            fd.append('nonce', nonce);
            fd.append('doc_id', docUid);
            fd.append('patient_id', patientId);
            
            var ajaxUrl = (window.dior_doctor_vars && window.dior_doctor_vars.ajax_url) ||
                          (window.dior_vars && window.dior_vars.ajax_url) ||
                          '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                          
            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (!res.success) throw new Error((res.data && res.data.message) || 'Failed to delete');
                if (row) row.remove();
                if (window.Swal) {
                    Swal.fire({position: 'top-end', icon: 'success', title: 'Document Removed', showConfirmButton: false, timer: 1400});
                }
            })
            .catch(function(err) {
                if (window.Swal) {
                    Swal.fire({icon: 'error', title: 'Delete Failed', text: err.message});
                } else {
                    alert('Delete failed: ' + err.message);
                }
            });
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Delete Document?',
                text: 'This document will be removed from clinic and patient records.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Delete'
            }).then(function(r) {
                if (r.isConfirmed) executeDelete();
            });
        } else {
            if (confirm('Are you sure you want to delete this document?')) {
                executeDelete();
            }
        }
    };

    window.diorExportDoctorDocsCSV = function() {
        var rows = document.querySelectorAll('#doc-uploaded-tbody tr');
        var csv = ['Document ID,Patient,Category,Title,Upload Date,Uploaded By,Status'];
        rows.forEach(function(r) {
            if (r.id === 'dior-doc-empty-row' || r.style.display === 'none') return;
            var cols = r.querySelectorAll('td');
            if (cols.length < 8) return;
            var docId = cols[1].innerText.trim();
            var patient = cols[2].querySelector('.docs-user-name') ? cols[2].querySelector('.docs-user-name').innerText.trim() : cols[2].innerText.trim();
            var category = cols[3].innerText.trim();
            var title = cols[4].querySelector('.fw-semibold') ? cols[4].querySelector('.fw-semibold').innerText.trim() : cols[4].innerText.trim();
            var date = cols[5].innerText.trim();
            var author = cols[6].innerText.trim();
            var status = cols[7].innerText.trim();
            csv.push(['"' + docId + '"', '"' + patient + '"', '"' + category + '"', '"' + title + '"', '"' + date + '"', '"' + author + '"', '"' + status + '"'].join(','));
        });
        var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.setAttribute('href', url);
        a.setAttribute('download', 'doctor_uploaded_documents.csv');
        document.body.appendChild(a);
        a.click();
        a.remove();
    };

    (function() {
        function initDocUploadHandler() {
            var form = document.getElementById('dior-doctor-doc-upload-form');
            if (!form || form.dataset.listenerBound) return;
            form.dataset.listenerBound = 'true';
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var submitBtn = document.getElementById('doctor-doc-upload-submit-btn');
                var patientSelect = document.getElementById('doctor-doc-upload-patient');
                var patientId = patientSelect ? patientSelect.value : '';
                if (!patientId) {
                    if (window.Swal) Swal.fire({icon: 'warning', title: 'Please select a patient'});
                    return;
                }
                var patientOptionText = patientSelect.options[patientSelect.selectedIndex].text;
                var patientName = patientOptionText.split('(')[0].trim();
                
                var fd = new FormData(form);
                fd.append('action', 'dior_upload_document');
                var nonce = (window.dior_doctor_vars && window.dior_doctor_vars.nonce) ||
                            (window.dior_vars && window.dior_vars.nonce) ||
                            '<?php echo esc_js($doctor_doc_nonce); ?>';
                fd.append('nonce', nonce);
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Uploading...';
                
                var ajaxUrl = (window.dior_doctor_vars && window.dior_doctor_vars.ajax_url) ||
                              (window.dior_vars && window.dior_vars.ajax_url) ||
                              '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                              
                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                })
                .then(function(res) { return res.json(); })
                .then(function(result) {
                    if (!result.success) {
                        throw new Error((result.data && result.data.message) || 'Upload failed.');
                    }
                    
                    var modalEl = document.getElementById('diorDoctorDocUploadModal');
                    if (modalEl && window.bootstrap && bootstrap.Modal) {
                        var inst = bootstrap.Modal.getInstance(modalEl);
                        if (inst) inst.hide();
                    }
                    
                    form.reset();
                    
                    if (window.Swal) {
                        Swal.fire({
                            position: 'top-end',
                            icon: 'success',
                            title: 'Document Uploaded Successfully',
                            text: 'The document is now available in patient records.',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }
                    
                    var tbody = document.getElementById('doc-uploaded-tbody');
                    if (tbody) {
                        var emptyRow = document.getElementById('dior-doc-empty-row');
                        if (emptyRow) emptyRow.remove();
                        
                        var doc = result.data.document || {};
                        var docUid = doc.id || ('DOC-' + Math.floor(Math.random() * 89999 + 10000));
                        var docTitle = doc.title || fd.get('doc_title');
                        var docCat = doc.category || fd.get('doc_category');
                        var docType = doc.file_type || 'PDF';
                        var docSize = doc.size || '150 KB';
                        var docDate = doc.date || 'Just now';
                        var downloadUrl = '<?php echo esc_js(home_url('/')); ?>?dior_action=download_doc&doc_id=' + encodeURIComponent(docUid) + '&patient_id=' + encodeURIComponent(patientId);
                        
                        var tr = document.createElement('tr');
                        tr.id = 'doc-row-' + docUid;
                        tr.setAttribute('data-patient-id', patientId);
                        tr.setAttribute('data-doc-uid', docUid);
                        tr.innerHTML = '<td class="text-center"><input type="checkbox" class="docs-checkbox form-check-input" value="' + docUid + '"></td>' +
                            '<td><span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">' + docUid + '</span></td>' +
                            '<td><div class="docs-user-profile d-flex align-items-center gap-2">' +
                            '<img src="https://ui-avatars.com/api/?name=' + encodeURIComponent(patientName) + '&background=random" class="docs-user-avatar rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" alt="' + patientName + '">' +
                            '<div><span class="docs-user-name fw-semibold text-dark d-block">' + patientName + '</span><small class="text-muted">ID: ' + patientId + '</small></div>' +
                            '</div></td>' +
                            '<td>' + docCat + '</td>' +
                            '<td><div class="fw-semibold text-dark">' + docTitle + '</div><small class="text-muted">' + docType + ' • ' + docSize + '</small></td>' +
                            '<td><i class="far fa-calendar docs-icon-blue me-1"></i> ' + docDate + '</td>' +
                            '<td><?php echo esc_js($doctor_display_name); ?></td>' +
                            '<td><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small"><i class="fas fa-circle-check me-1"></i> Available</span></td>' +
                            '<td><div class="docs-row-actions d-flex gap-1">' +
                            '<a href="' + downloadUrl + '" target="_blank" class="docs-action-btn-sm docs-btn-edit btn" title="Download Document" download><i class="fas fa-file-arrow-down"></i></a>' +
                            '<button type="button" class="docs-action-btn-sm docs-btn-delete btn" title="Delete Document" onclick="diorDoctorDeleteDoc(this, \'' + docUid + '\', ' + patientId + ')"><i class="fas fa-trash"></i></button>' +
                            '</div></td>';
                            
                        tbody.insertBefore(tr, tbody.firstChild);
                    }
                })
                .catch(function(err) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Failed',
                            text: err.message || 'An error occurred while uploading the document.'
                        });
                    } else {
                        alert('Upload failed: ' + err.message);
                    }
                })
                .finally(function() {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-cloud-arrow-up me-1"></i> Upload Document';
                });
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDocUploadHandler);
        } else {
            initDocUploadHandler();
        }
    })();
    </script>
</section>

<!-- ==============================================
     2) CONSENT TEMPLATES PANE
     ============================================== -->
<section class="dior-tab-panel" id="tab-doc-documents-templates" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Consent Templates</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list"
                style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="#"><i class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)"
                        style="color: #64748B; text-decoration: none; font-size: 14px;">Documents & Consent</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">Consent
                        Templates</span></li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-documents-upload" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-documents-upload');">
            <i class="fas fa-file-arrow-up"></i> Upload Documents
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-documents-templates" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-documents-templates');">
            <i class="fas fa-file-lines"></i> Consent Templates
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-documents-signed" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-documents-signed');">
            <i class="fas fa-file-signature"></i> Signed Consent
        </button>
    </div>

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2>Consent Templates</h2>
                <div class="docs-title-line"></div>
            </div>

            <div class="docs-actions-wrapper">
                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Search records...">
                </div>
                <div class="docs-actions-group">
                    <button class="docs-icon-btn docs-btn-primary btn" aria-label="Add new record"><i
                            class="fas fa-plus"></i></button>
                    <button class="docs-icon-btn docs-btn-success btn" aria-label="Export to Excel"><i
                            class="fas fa-file-arrow-down"></i></button>
                    <button class="docs-icon-btn docs-btn-info btn" aria-label="Refresh data"><i
                            class="fas fa-rotate-right"></i></button>
                </div>
            </div>
        </div>

        <div class="docs-table-wrapper">
            <table class="docs-table table table-hover">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center"><input type="checkbox"
                                class="docs-checkbox form-check-input"></th>
                        <th>TEMPLATE ID <i class="fas fa-sort"></i></th>
                        <th>TEMPLATE NAME <i class="fas fa-sort"></i></th>
                        <th>CATEGORY <i class="fas fa-sort"></i></th>
                        <th>DEPARTMENT <i class="fas fa-sort"></i></th>
                        <th>VERSION <i class="fas fa-sort"></i></th>
                        <th>CREATED DATE <i class="fas fa-sort"></i></th>
                        <th>STATUS <i class="fas fa-sort"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Row 1 -->
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox form-check-input"></td>
                        <td>TPL001</td>
                        <td>General Surgery Consent Form</td>
                        <td>Surgery Consent</td>
                        <td>General Surgery</td>
                        <td>v2.1</td>
                        <td><i class="far fa-calendar docs-icon-blue"></i> Jan 15, 2024</td>
                        <td><span class="docs-badge docs-badge-active">Active</span></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="docs-action-btn-sm docs-btn-delete btn"><i
                                        class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 2 -->
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox form-check-input"></td>
                        <td>TPL002</td>
                        <td>Anesthesia Consent Form</td>
                        <td>Anesthesia Consent</td>
                        <td>All Departments</td>
                        <td>v1.5</td>
                        <td><i class="far fa-calendar docs-icon-blue"></i> Feb 10, 2024</td>
                        <td><span class="docs-badge docs-badge-active">Active</span></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="docs-action-btn-sm docs-btn-delete btn"><i
                                        class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 3 -->
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox form-check-input"></td>
                        <td>TPL003</td>
                        <td>Blood Transfusion Consent</td>
                        <td>Blood Transfusion</td>
                        <td>All Departments</td>
                        <td>v1.3</td>
                        <td><i class="far fa-calendar docs-icon-blue"></i> Mar 5, 2024</td>
                        <td><span class="docs-badge docs-badge-active">Active</span></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="docs-action-btn-sm docs-btn-delete btn"><i
                                        class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 4 -->
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox form-check-input"></td>
                        <td>TPL004</td>
                        <td>Orthopedic Surgery Consent</td>
                        <td>Surgery Consent</td>
                        <td>Orthopedics</td>
                        <td>v2.0</td>
                        <td><i class="far fa-calendar docs-icon-blue"></i> Apr 12, 2024</td>
                        <td><span class="docs-badge docs-badge-active">Active</span></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="docs-action-btn-sm docs-btn-delete btn"><i
                                        class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 5 -->
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox form-check-input"></td>
                        <td>TPL005</td>
                        <td>Discharge Against Medical Advice (AMA)</td>
                        <td>Discharge AMA</td>
                        <td>All Departments</td>
                        <td>v1.2</td>
                        <td><i class="far fa-calendar docs-icon-blue"></i> May 20, 2024</td>
                        <td><span class="docs-badge docs-badge-active">Active</span></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="docs-action-btn-sm docs-btn-delete btn"><i
                                        class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 6 -->
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox form-check-input"></td>
                        <td>TPL006</td>
                        <td>Cardiac Procedure Consent</td>
                        <td>Surgery Consent</td>
                        <td>Cardiology</td>
                        <td>v1.8</td>
                        <td><i class="far fa-calendar docs-icon-blue"></i> Jun 15, 2024</td>
                        <td><span class="docs-badge docs-badge-active">Active</span></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="docs-action-btn-sm docs-btn-delete btn"><i
                                        class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 7 -->
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox form-check-input"></td>
                        <td>TPL007</td>
                        <td>Research Participation Consent</td>
                        <td>Research Participation</td>
                        <td>All Departments</td>
                        <td>v1.0</td>
                        <td><i class="far fa-calendar docs-icon-blue"></i> Jul 1, 2024</td>
                        <td><span class="docs-badge docs-badge-orange">Draft</span></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="docs-action-btn-sm docs-btn-delete btn"><i
                                        class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 8 -->
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox form-check-input"></td>
                        <td>TPL008</td>
                        <td>Photography and Media Consent</td>
                        <td>Photography</td>
                        <td>All Departments</td>
                        <td>v1.1</td>
                        <td><i class="far fa-calendar docs-icon-blue"></i> Aug 10, 2024</td>
                        <td><span class="docs-badge docs-badge-active">Active</span></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="docs-action-btn-sm docs-btn-delete btn"><i
                                        class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ==============================================
     3) SIGNED CONSENT PANE 
     ============================================== -->
<section class="dior-tab-panel documents-tab-panel" id="tab-doc-documents-signed" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Signed Consent Forms</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list"
                style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="#"><i class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)"
                        style="color: #64748B; text-decoration: none; font-size: 14px;">Documents & Consent</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">Signed
                        Consent</span></li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-documents-upload" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-documents-upload');">
            <i class="fas fa-file-arrow-up"></i> Upload Documents
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-documents-templates" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-documents-templates');">
            <i class="fas fa-file-lines"></i> Consent Templates
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-documents-signed" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-documents-signed');">
            <i class="fas fa-file-signature"></i> Signed Consent
        </button>
    </div>

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2>Signed Consent Forms</h2>
                <div class="docs-title-line"></div>
            </div>

            <div class="docs-actions-wrapper">
                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Search records...">
                </div>
                <div class="docs-actions-group">
                    <button class="docs-icon-btn docs-btn-primary btn" aria-label="Add new record"><i
                            class="fas fa-plus"></i></button>
                    <button class="docs-icon-btn docs-btn-success btn" aria-label="Export to Excel"><i
                            class="fas fa-file-arrow-down"></i></button>
                    <button class="docs-icon-btn docs-btn-info btn" aria-label="Refresh data"><i
                            class="fas fa-rotate-right"></i></button>
                </div>
            </div>
        </div>

        <div class="docs-table-wrapper">
            <table class="docs-table table table-hover">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center"><input type="checkbox"
                                class="docs-checkbox form-check-input"></th>
                        <th>CONSENT ID <i class="fas fa-sort"></i></th>
                        <th>PATIENT <i class="fas fa-sort"></i></th>
                        <th>CONSENT TYPE <i class="fas fa-sort"></i></th>
                        <th>PROCEDURE <i class="fas fa-sort"></i></th>
                        <th>SIGNED DATE <i class="fas fa-sort"></i></th>
                        <th>SIGNED BY <i class="fas fa-sort"></i></th>
                        <th>WITNESS <i class="fas fa-sort"></i></th>
                        <th>STATUS <i class="fas fa-sort"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="doc-signed-consents-tbody">
                    <?php if (!empty($signed_consents_records)): ?>
                        <?php foreach ($signed_consents_records as $sc): ?>
                            <?php
                            $sc_uid = $sc['doc_uid'] ?: ('CON-' . $sc['id']);
                            $p_id = (int)$sc['patient_id'];
                            $p_name = $sc['patient_name'] ?: 'Patient';
                            $p_avatar = $sc['patient_avatar'] ?: ('https://ui-avatars.com/api/?name=' . urlencode($p_name) . '&background=random');
                            $signed_date = date('M j, Y', strtotime($sc['created_at']));
                            $download_url = add_query_arg([
                                'dior_action' => 'download_doc',
                                'doc_id'      => $sc_uid,
                                'patient_id'  => $p_id
                            ], home_url('/'));
                            $doc_title = $sc['doc_title'] ?: 'Consent Form';
                            $doc_cat = $sc['doc_category'] ?: 'Surgery Consent';
                            ?>
                            <tr id="doc-sc-row-<?php echo esc_attr($sc_uid); ?>" data-patient-id="<?php echo esc_attr($p_id); ?>" data-doc-uid="<?php echo esc_attr($sc_uid); ?>">
                                <td class="text-center"><input type="checkbox" class="docs-checkbox form-check-input" value="<?php echo esc_attr($sc_uid); ?>"></td>
                                <td><span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small"><?php echo esc_html($sc_uid); ?></span></td>
                                <td>
                                    <div class="docs-user-profile d-flex align-items-center gap-2">
                                        <img src="<?php echo esc_url($p_avatar); ?>" class="docs-user-avatar rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" alt="<?php echo esc_attr($p_name); ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($p_name); ?>&background=random'">
                                        <div>
                                            <span class="docs-user-name fw-semibold text-dark d-block"><?php echo esc_html($p_name); ?></span>
                                            <small class="text-muted"><?php echo esc_html($sc['patient_code']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html($doc_cat); ?></td>
                                <td><div class="fw-semibold text-dark"><?php echo esc_html($doc_title); ?></div></td>
                                <td><i class="far fa-calendar docs-icon-blue me-1"></i> <?php echo esc_html($signed_date); ?></td>
                                <td><?php echo esc_html($p_name); ?></td>
                                <td>Clinical Staff</td>
                                <td><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small"><i class="fas fa-check-circle me-1"></i> Signed</span></td>
                                <td>
                                    <div class="docs-row-actions d-flex gap-1">
                                        <a href="<?php echo esc_url($download_url); ?>" target="_blank" class="docs-action-btn-sm docs-btn-edit btn" title="Download Consent" download><i class="fas fa-file-arrow-down"></i></a>
                                        <button type="button" class="docs-action-btn-sm docs-btn-delete btn" title="Delete Consent" onclick="diorDoctorDeleteDoc(this, '<?php echo esc_js($sc_uid); ?>', <?php echo (int)$p_id; ?>)"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="dior-sc-empty-row">
                            <td colspan="10" class="text-center py-5 text-secondary">
                                <i class="fas fa-file-signature fs-2 text-muted mb-2 d-block"></i>
                                <span class="fw-semibold d-block">No signed consent forms uploaded yet.</span>
                                <small class="text-muted">Uploaded consent forms will appear here in real-time.</small>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>