<?php
if (!defined('ABSPATH')) {
    exit;
}
global $wpdb;
$doctor_id = get_current_user_id();
$doctor_nonce = wp_create_nonce('dior_doctor_nonce');
$ajax_url = admin_url('admin-ajax.php');

$doctor_ids = class_exists('Dior_Doctor_Resolver') ? Dior_Doctor_Resolver::get_accessible_doctor_ids($doctor_id) : [$doctor_id];
$doctor_ids = array_values(array_unique(array_filter(array_map('intval', (array) $doctor_ids))));
if (!$doctor_ids) {
    $doctor_ids = [$doctor_id];
}
$placeholders = implode(',', array_fill(0, count($doctor_ids), '%d'));

$sql = "SELECT t.*, p.first_name, p.last_name, a.appt_date, a.appt_time, u.display_name AS doctor_name 
        FROM {$wpdb->prefix}dior_telemedicine t 
        LEFT JOIN {$wpdb->prefix}dior_patients p ON p.user_id = t.patient_id 
        LEFT JOIN {$wpdb->prefix}dior_appointments a ON a.appt_uid = t.appointment_uid 
        LEFT JOIN {$wpdb->users} u ON u.ID = t.doctor_id 
        WHERE t.doctor_id IN ($placeholders) 
        ORDER BY t.created_at DESC, t.id DESC LIMIT 200";
$virtual_records = $wpdb->get_results($wpdb->prepare($sql, ...$doctor_ids), ARRAY_A);

// Fetch active patients for manual record modal
$all_patients = $wpdb->get_results("
    SELECT user_id, first_name, last_name, patient_uid
    FROM {$wpdb->prefix}dior_patients
    WHERE status <> 'deleted'
    ORDER BY first_name ASC
", ARRAY_A);
if (!is_array($all_patients)) {
    $all_patients = [];
}
?>

<section class="dior-tab-panel documents-tab-panel" id="tab-doc-telemed-records" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4"
        style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Virtual Visit Records</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list"
                style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="javascript:void(0)"
                        onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-overview')"><i
                            class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)"
                        onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-telemed-video')"
                        style="color: #64748B; text-decoration: none; font-size: 14px;">Telemedicine</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">Virtual Visit
                        Records</span></li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-telemed-video"
            onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-telemed-video');">
            <i class="fas fa-video"></i> Video Consultation
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-telemed-records"
            onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-telemed-records');">
            <i class="fas fa-clock-rotate-left"></i> Virtual Visit Records
        </button>
    </div>

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2><i class="fas fa-clock-rotate-left text-primary me-2"></i>Virtual Visit Records</h2>
                <div class="docs-title-line"></div>
            </div>

            <div class="docs-actions-wrapper">
                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                    <input type="text" id="dior-tele-records-search" class="form-control border-start-0 ps-0"
                        placeholder="Search records..." onkeyup="diorSearchTelemedRecords(this.value)">
                </div>
                <div class="docs-actions-group">
                    <button type="button" class="docs-icon-btn docs-btn-primary btn" aria-label="Add new record"
                        title="Add Virtual Visit Record" onclick="diorOpenAddVirtualVisitModal()">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" class="docs-icon-btn docs-btn-success btn" aria-label="Export to CSV"
                        title="Export to CSV" onclick="diorExportTelemedRecordsCSV()">
                        <i class="fas fa-file-arrow-down"></i>
                    </button>
                    <button type="button" class="docs-icon-btn docs-btn-info btn" aria-label="Refresh data"
                        title="Refresh records" onclick="window.location.reload()">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="docs-table-wrapper">
            <table class="docs-table table table-hover align-middle mb-0" id="dior-tele-records-table">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">
                            <input type="checkbox" class="docs-checkbox form-check-input"
                                onclick="diorToggleAllTeleCheckboxes(this)">
                        </th>
                        <th>VISIT ID</th>
                        <th>PATIENT</th>
                        <th>DOCTOR</th>
                        <th>VISIT DATE</th>
                        <th>DURATION (MIN)</th>
                        <th>DIAGNOSIS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="dior-tele-records-tbody">
                    <?php if (!empty($virtual_records)):
                        foreach ($virtual_records as $vr):
                            $status_class = strtolower($vr['status']) === 'completed' ? 'bg-success-subtle text-success border border-success-subtle' :
                                (strtolower($vr['status']) === 'cancelled' ? 'bg-danger-subtle text-danger border border-danger-subtle' :
                                    (strtolower($vr['status']) === 'in progress' ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle'));
                            ?>
                            <tr data-tele-uid="<?php echo esc_attr($vr['tele_uid']); ?>"
                                data-patient-id="<?php echo (int) $vr['patient_id']; ?>"
                                id="tele-row-<?php echo esc_attr($vr['tele_uid']); ?>">
                                <td class="text-center">
                                    <input type="checkbox" class="docs-checkbox form-check-input row-tele-chk">
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small fw-semibold">
                                        <?php echo esc_html($vr['tele_uid']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="docs-user-name fw-bold text-dark">
                                        <?php echo esc_html(trim(($vr['first_name'] ?? '') . ' ' . ($vr['last_name'] ?? '')) ?: (get_userdata((int) $vr['patient_id'])->display_name ?? 'Patient #' . $vr['patient_id'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-secondary small fw-semibold">
                                        <?php echo esc_html($vr['doctor_name'] ?: 'Doctor'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark small">
                                        <i class="far fa-calendar me-1 text-primary"></i>
                                        <?php echo esc_html(!empty($vr['appt_date']) ? date_i18n('M j, Y', strtotime($vr['appt_date'])) : date_i18n('M j, Y', strtotime($vr['created_at']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1 small">
                                        <?php echo !empty($vr['duration']) ? esc_html($vr['duration']) . ' min' : '30 min'; ?>
                                    </span>
                                </td>
                                <td style="max-width: 250px;">
                                    <span class="text-secondary small text-truncate d-inline-block" style="max-width: 240px;"
                                        title="<?php echo esc_attr($vr['notes'] ?: $vr['status']); ?>">
                                        <?php echo esc_html($vr['notes'] ?: $vr['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge <?php echo esc_attr($status_class); ?> rounded-pill px-2 py-1 small">
                                            <?php echo esc_html($vr['status']); ?>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1"
                                            title="Delete Record"
                                            onclick="diorDeleteTelemedRecord('<?php echo esc_js($vr['tele_uid']); ?>', this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                        <tr id="dior-empty-tele-row">
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-video-slash d-block fs-2 mb-2 opacity-50"></i>
                                <div class="fw-bold text-dark fs-6 mb-1">No virtual visit records available yet</div>
                                <p class="small text-muted mb-0">Click the plus (+) button above to manually record a
                                    virtual consultation.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Bootstrap 5 Modal for Adding Manual Virtual Visit Record (Strict Rule 3.F Compliant) -->
<div class="modal fade" id="diorAddVirtualVisitModal" tabindex="-1" aria-labelledby="diorAddVirtualVisitModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div
                class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="diorAddVirtualVisitModalLabel">
                    <i class="fas fa-video text-primary me-2"></i>Add Virtual Visit Record
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="diorAddVirtualVisitForm" onsubmit="diorSubmitVirtualVisit(event)">
                <div class="modal-body p-4 bg-white">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase text-secondary fw-bold mb-1">
                                Patient <span class="text-danger">*</span>
                            </label>
                            <select name="patient_id" class="form-select rounded-3 py-2 fw-semibold" required>
                                <option value="">-- Choose Patient --</option>
                                <?php foreach ($all_patients as $ap): ?>
                                    <option value="<?php echo (int) $ap['user_id']; ?>">
                                        <?php echo esc_html(trim($ap['first_name'] . ' ' . $ap['last_name'])) . ' · ' . esc_html($ap['patient_uid']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-uppercase text-secondary fw-bold mb-1">
                                Attending Doctor
                            </label>
                            <input type="text" class="form-control rounded-3 py-2 bg-light fw-semibold"
                                value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>" readonly>
                            <input type="hidden" name="doctor_id" value="<?php echo (int) $doctor_id; ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-uppercase text-secondary fw-bold mb-1">
                                Visit Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="visit_date" class="form-control rounded-3 py-2 fw-semibold"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-uppercase text-secondary fw-bold mb-1">
                                Visit Time <span class="text-danger">*</span>
                            </label>
                            <input type="time" name="visit_time" class="form-control rounded-3 py-2 fw-semibold"
                                value="<?php echo date('H:i'); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-uppercase text-secondary fw-bold mb-1">
                                Duration (Minutes) <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="duration" class="form-control rounded-3 py-2 fw-semibold" min="5"
                                max="240" step="5" value="30" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-uppercase text-secondary fw-bold mb-1">
                                Status <span class="text-danger">*</span>
                            </label>
                            <select name="status" class="form-select rounded-3 py-2 fw-semibold">
                                <option value="Completed" selected>Completed</option>
                                <option value="Confirmed">Confirmed</option>
                                <option value="Scheduled">Scheduled</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Cancelled">Cancelled</option>
                                <option value="No Show">No Show</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-uppercase text-secondary fw-bold mb-1">
                                Visit Modality
                            </label>
                            <select name="visit_type" class="form-select rounded-3 py-2 fw-semibold">
                                <option value="Video Visit (HD)" selected>Video Visit (HD)</option>
                                <option value="Phone Consultation">Phone Consultation</option>
                                <option value="Follow-up Video">Follow-up Video</option>
                                <option value="Urgent Telehealth">Urgent Telehealth</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small text-uppercase text-secondary fw-bold mb-1">
                                Meeting / Room URL (Optional)
                            </label>
                            <input type="url" name="room_url" class="form-control rounded-3 py-2"
                                placeholder="https://meet.jit.si/... or Zoom link">
                        </div>

                        <div class="col-12">
                            <label class="form-label small text-uppercase text-secondary fw-bold mb-1">
                                Diagnosis &amp; Clinical Summary <span class="text-danger">*</span>
                            </label>
                            <textarea name="notes" class="form-control rounded-3" rows="3"
                                placeholder="Enter patient diagnosis, consultation findings, or clinical summary..."
                                required></textarea>
                        </div>
                    </div>
                </div>
                <div
                    class="modal-footer border-top py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4 fw-semibold"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm"
                        id="dior-submit-tele-btn">
                        <i class="fas fa-check me-1"></i> Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Virtual Visit Records JavaScript Handlers -->
<script>
    function diorOpenAddVirtualVisitModal() {
        var modalEl = document.getElementById('diorAddVirtualVisitModal');
        if (!modalEl) return;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function diorSubmitVirtualVisit(e) {
        if (e) e.preventDefault();
        var form = document.getElementById('diorAddVirtualVisitForm');
        if (!form) return;

        var submitBtn = document.getElementById('dior-submit-tele-btn');
        var originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
        }

        var formData = new FormData(form);
        formData.append('action', 'dior_doctor_add_virtual_visit_record');
        formData.append('nonce', '<?php echo esc_js($doctor_nonce); ?>');

        fetch('<?php echo esc_js($ajax_url); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }

                if (data.success && data.data && data.data.record) {
                    var rec = data.data.record;
                    var tbody = document.getElementById('dior-tele-records-tbody');
                    var emptyRow = document.getElementById('dior-empty-tele-row');
                    if (emptyRow) emptyRow.remove();

                    var statusLower = (rec.status || '').toLowerCase();
                    var statusClass = statusLower === 'completed' ? 'bg-success-subtle text-success border border-success-subtle' :
                        (statusLower === 'cancelled' ? 'bg-danger-subtle text-danger border border-danger-subtle' :
                            (statusLower === 'in progress' ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle'));

                    var newRow = document.createElement('tr');
                    newRow.setAttribute('data-tele-uid', rec.tele_uid);
                    newRow.setAttribute('data-patient-id', rec.patient_id);
                    newRow.setAttribute('id', 'tele-row-' + rec.tele_uid);
                    newRow.style.transition = 'all 0.4s ease';
                    newRow.style.backgroundColor = '#ecfdf5';

                    newRow.innerHTML = `
                    <td class="text-center">
                        <input type="checkbox" class="docs-checkbox form-check-input row-tele-chk">
                    </td>
                    <td>
                        <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small fw-semibold">
                            ${rec.tele_uid}
                        </span>
                    </td>
                    <td>
                        <span class="docs-user-name fw-bold text-dark">
                            ${rec.patient_name}
                        </span>
                    </td>
                    <td>
                        <span class="text-secondary small fw-semibold">
                            ${rec.doctor_name}
                        </span>
                    </td>
                    <td>
                        <span class="text-dark small">
                            <i class="far fa-calendar me-1 text-primary"></i>
                            ${rec.visit_date}
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border px-2 py-1 small">
                            ${rec.duration}
                        </span>
                    </td>
                    <td style="max-width: 250px;">
                        <span class="text-secondary small text-truncate d-inline-block" style="max-width: 240px;" title="${rec.diagnosis}">
                            ${rec.diagnosis}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge ${statusClass} rounded-pill px-2 py-1 small">
                                ${rec.status}
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Record" onclick="diorDeleteTelemedRecord('${rec.tele_uid}', this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;

                    if (tbody.firstChild) {
                        tbody.insertBefore(newRow, tbody.firstChild);
                    } else {
                        tbody.appendChild(newRow);
                    }

                    setTimeout(function () {
                        newRow.style.backgroundColor = '';
                    }, 2000);

                    // Close Modal
                    var modalEl = document.getElementById('diorAddVirtualVisitModal');
                    if (modalEl) {
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    }
                    form.reset();

                    if (window.Swal) {
                        Swal.fire({
                            position: 'top-end',
                            icon: 'success',
                            title: 'Virtual Visit Record Saved!',
                            showConfirmButton: false,
                            timer: 1800
                        });
                    }
                } else {
                    var msg = (data.data && data.data.message) ? data.data.message : 'Failed to save record.';
                    if (window.Swal) {
                        Swal.fire({ icon: 'error', title: 'Error', text: msg });
                    } else {
                        alert(msg);
                    }
                }
            })
            .catch(function (err) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
                console.error('Error saving virtual visit record:', err);
            });
    }

    function diorDeleteTelemedRecord(teleUid, btn) {
        if (!teleUid) return;
        var row = document.getElementById('tele-row-' + teleUid) || (btn ? btn.closest('tr') : null);

        var doDelete = function () {
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                setTimeout(function () { row.remove(); }, 300);
            }

            var fd = new FormData();
            fd.append('action', 'dior_doctor_delete_virtual_visit_record');
            fd.append('nonce', '<?php echo esc_js($doctor_nonce); ?>');
            fd.append('tele_uid', teleUid);

            fetch('<?php echo esc_js($ajax_url); ?>', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (window.Swal) {
                    Swal.fire({
                        position: 'top-end',
                        icon: 'success',
                        title: 'Record Deleted',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            }).catch(function () { });
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Delete Virtual Visit Record?',
                text: 'This visit record will be permanently deleted.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete!'
            }).then(function (res) {
                if (res.isConfirmed) doDelete();
            });
        } else if (confirm('Are you sure you want to delete this virtual visit record?')) {
            doDelete();
        }
    }

    function diorSearchTelemedRecords(query) {
        var q = (query || '').toLowerCase().trim();
        var rows = document.querySelectorAll('#dior-tele-records-tbody tr');
        rows.forEach(function (row) {
            if (row.id === 'dior-empty-tele-row') return;
            var text = row.textContent.toLowerCase();
            row.style.display = (!q || text.includes(q)) ? '' : 'none';
        });
    }

    function diorToggleAllTeleCheckboxes(masterChk) {
        var chks = document.querySelectorAll('.row-tele-chk');
        chks.forEach(function (c) {
            c.checked = masterChk.checked;
        });
    }

    function diorExportTelemedRecordsCSV() {
        var rows = document.querySelectorAll('#dior-tele-records-tbody tr');
        var csvContent = "data:text/csv;charset=utf-8,Visit ID,Patient,Doctor,Visit Date,Duration,Diagnosis,Status\n";

        rows.forEach(function (row) {
            if (row.id === 'dior-empty-tele-row' || row.style.display === 'none') return;
            var cells = row.querySelectorAll('td');
            if (cells.length >= 8) {
                var uid = (cells[1].textContent || '').trim().replace(/"/g, '""');
                var patient = (cells[2].textContent || '').trim().replace(/"/g, '""');
                var doctor = (cells[3].textContent || '').trim().replace(/"/g, '""');
                var date = (cells[4].textContent || '').trim().replace(/"/g, '""');
                var duration = (cells[5].textContent || '').trim().replace(/"/g, '""');
                var diag = (cells[6].textContent || '').trim().replace(/"/g, '""');
                var status = (cells[7].querySelector('.badge')?.textContent || '').trim().replace(/"/g, '""');
                csvContent += `"${uid}","${patient}","${doctor}","${date}","${duration}","${diag}","${status}"\n`;
            }
        });

        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "virtual_visit_records.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>