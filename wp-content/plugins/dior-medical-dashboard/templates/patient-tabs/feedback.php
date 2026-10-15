<section class="dior-tab-panel" id="tab-feedback">
    <?php
    global $wpdb;
    $feedback_patient_id = get_current_user_id();
    $reviews_table = $wpdb->prefix . 'dior_reviews';
    $patient_feedback = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, r.comment AS message, u.display_name AS doctor_display_name
         FROM {$reviews_table} r
         LEFT JOIN {$wpdb->users} u ON u.ID = r.doctor_id
         WHERE r.patient_id = %d
         ORDER BY r.created_at DESC
         LIMIT 100",
        $feedback_patient_id
    ), ARRAY_A) ?: [];

    $feedback_doctor_id = 0;
    $feedback_appointments = class_exists('Dior_Appointment_Service')
        ? Dior_Appointment_Service::get_patient_appointments($feedback_patient_id)
        : [];
    foreach ($feedback_appointments as $feedback_appointment) {
        $doctor_user_id = (int) ($feedback_appointment['doctor_user_id'] ?? 0);
        if ($doctor_user_id && get_userdata($doctor_user_id)) {
            $feedback_doctor_id = $doctor_user_id;
            break;
        }
    }
    if (!$feedback_doctor_id) {
        if (class_exists('Dior_Doctor_Resolver')) {
            $feedback_doctor_id = Dior_Doctor_Resolver::get_default_doctor_id();
        }
        if (!$feedback_doctor_id) {
            $feedback_doctors = get_users(['role__in' => ['doctor', 'administrator'], 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1, 'fields' => 'ID']);
            $feedback_doctor_id = !empty($feedback_doctors) ? (int) $feedback_doctors[0] : 1;
        }
    }
    $feedback_doctor = $feedback_doctor_id ? get_userdata($feedback_doctor_id) : false;
    if (!$feedback_doctor) {
        $feedback_doctor = get_user_by('id', 1);
        $feedback_doctor_id = $feedback_doctor ? (int) $feedback_doctor->ID : 1;
    }
    $feedback_doctor_name = $feedback_doctor ? trim($feedback_doctor->first_name . ' ' . $feedback_doctor->last_name) : '';
    if ($feedback_doctor && $feedback_doctor_name === '')
        $feedback_doctor_name = $feedback_doctor->display_name;
    $feedback_doctor_name = preg_replace('/^(?:dr\.?\s*)+/i', '', $feedback_doctor_name);
    if ($feedback_doctor_name !== '')
        $feedback_doctor_name = 'Dr. ' . $feedback_doctor_name;
    ?>
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Feedback &amp; Support</h4>
        </div>
        <ul class="va-breadcrumb-list">
            <li><a href="#"><i class="fas fa-house"></i></a></li>
            <li><span>/</span></li>
            <li class="active"><span>Feedback &amp; Support</span></li>
        </ul>
    </div>

    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title"><i class="fas fa-comments" style="margin-right:8px;"></i>Feedback
                                &amp; Support</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                                <input type="text" id="dior-feedback-search-input"
                                    class="form-control border-start-0 ps-0" placeholder="Search feedback..."
                                    aria-label="Search feedback" onkeyup="diorFilterFeedback(this.value)">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Delete selected items"
                                    class="action-btn action-btn-danger btn" title="Delete Selected"
                                    onclick="diorBulkDeleteFeedback()">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button type="button" class="action-btn action-btn-primary btn" title="Give Feedback"
                                    onclick="diorOpenFeedbackModal()"><i class="fas fa-plus"></i></button>
                                <button type="button" class="action-btn action-btn-success btn" title="Export CSV"
                                    onclick="diorDownloadFeedbackCSV()"><i class="fas fa-file-arrow-down"></i></button>
                                <button type="button" class="action-btn action-btn-info btn" title="Refresh"
                                    onclick="window.location.reload()"><i class="fas fa-rotate-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="va-table table table-hover" id="dior-feedback-table">
                        <thead>
                            <tr>
                                <th style="width:40px;" class="text-center"><input type="checkbox"
                                        class="va-checkbox form-check-input" id="dior-feedback-select-all"
                                        onchange="diorToggleAllFeedback(this)"></th>
                                <th>Subject</th>
                                <th>Doctor</th>
                                <th>Rating</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patient_feedback as $feedback):
                                $feedback_rating = (int) ($feedback['rating'] ?? 0);
                                $feedback_date = !empty($feedback['created_at']) ? wp_date('M j, Y', strtotime($feedback['created_at'])) : '—';
                                $feedback_status = $feedback['status'] ?? 'Pending';
                                $feedback_doctor_name = $feedback['doctor_display_name'] ?? 'Care team';
                                if ($feedback_doctor_name !== 'Care team') {
                                    $feedback_doctor_name = preg_replace('/^(?:dr\.?\s*)+/i', '', $feedback_doctor_name);
                                    $feedback_doctor_name = 'Dr. ' . $feedback_doctor_name;
                                }
                                $feedback_view_data = esc_attr(wp_json_encode([
                                    'subject' => $feedback['subject'] ?? 'Patient Feedback',
                                    'doctor' => $feedback_doctor_name,
                                    'rating' => $feedback_rating,
                                    'message' => $feedback['message'] ?? '',
                                    'status' => $feedback_status,
                                ]));
                                ?>
                                <tr data-feedback-id="<?php echo (int) ($feedback['id'] ?? 0); ?>">
                                    <td class="text-center">
                                        <input type="checkbox" class="va-checkbox dior-feedback-row-cb form-check-input"
                                            value="<?php echo (int) ($feedback['id'] ?? 0); ?>"
                                            onchange="diorUpdateFeedbackCheckState()">
                                    </td>
                                    <td><span
                                            class="cell-text"><?php echo esc_html($feedback['subject'] ?? 'Patient Feedback'); ?></span>
                                    </td>
                                    <td><span class="cell-text"><?php echo esc_html($feedback_doctor_name); ?></span></td>
                                    <td><span class="cell-text"><i class="fas fa-star" style="color:#F59E0B;"></i>
                                            <?php echo $feedback_rating; ?>/5</span></td>
                                    <td><span class="cell-text"><?php echo esc_html($feedback['message'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <div class="cell-content cell-icon-text"><i
                                                class="far fa-calendar cell-icon"></i><span
                                                class="cell-text"><?php echo esc_html($feedback_date); ?></span></div>
                                    </td>
                                    <td>
                                        <div class="cell-content">
                                            <div
                                                class="badge-solid <?php echo ($feedback_status === 'Published') ? 'col-green' : 'col-amber'; ?>">
                                                <?php echo esc_html($feedback_status); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="dior-feedback-view-action btn"
                                            aria-label="View feedback" title="View feedback"
                                            data-feedback="<?php echo $feedback_view_data; ?>"
                                            onclick="diorViewPatientFeedback(this)"><svg xmlns="http://www.w3.org/2000/svg"
                                                width="16" height="16" fill="#3b82f6" class="bi bi-eye" viewBox="0 0 16 16"
                                                style="background:transparent;">
                                                <path
                                                    d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z" />
                                                <path
                                                    d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0" />
                                            </svg></button>
                                        <button type="button" class="dior-feedback-edit-action btn"
                                            aria-label="Edit feedback" title="Edit feedback"
                                            data-feedback-id="<?php echo (int) ($feedback['id'] ?? 0); ?>"
                                            data-feedback="<?php echo $feedback_view_data; ?>"
                                            onclick="diorEditPatientFeedback(this)"><svg xmlns="http://www.w3.org/2000/svg"
                                                width="16" height="16" fill="currentColor" class="bi bi-vector-pen"
                                                viewBox="0 0 16 16" style="background:transparent;">
                                                <path fill-rule="evenodd"
                                                    d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z" />
                                                <path fill-rule="evenodd"
                                                    d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z" />
                                            </svg></button>
                                        <button type="button" class="dior-feedback-delete-action btn"
                                            aria-label="Delete feedback" title="Delete feedback"
                                            data-feedback-id="<?php echo (int) ($feedback['id'] ?? 0); ?>"
                                            onclick="diorDeletePatientFeedback(this)"><svg
                                                xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444"
                                                class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;">
                                                <path
                                                    d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z" />
                                                <path
                                                    d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z" />
                                            </svg></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($patient_feedback)): ?>
                                <tr>
                                    <td colspan="8" class="dior-empty-state">You have not submitted feedback yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- <div class="master-table-footer"><span class="page-count"
                        id="dior-feedback-total-count"><?php echo count($patient_feedback); ?> total feedback
                        records</span>
                    <div class="master-pagination"></div>
                </div> -->
            </div>
        </div>
    </div>

    <!-- Feedback popup -->
    <div class="dior-static-modal-backdrop" id="dior-feedback-modal" role="dialog" aria-modal="true"
        aria-labelledby="dior-feedback-modal-title">
        <div class="dior-static-modal dior-feedback-modal">
            <div class="dior-static-modal-header">
                <h3 id="dior-feedback-modal-title">Give Feedback</h3>
                <button type="button" class="dior-static-modal-close btn"
                    onclick="diorCloseStaticModal('dior-feedback-modal')" aria-label="Close modal"
                    style="width:34px !important; height:34px !important; min-width:34px !important; max-width:34px !important; min-height:34px !important; max-height:34px !important; padding:0 !important; margin:0 !important; border:1px solid #E2E8F0 !important; border-radius:8px !important; background:#ffffff !important; color:#64748B !important; display:inline-flex !important; align-items:center !important; justify-content:center !important; cursor:pointer !important; line-height:1 !important; box-sizing:border-box !important; flex-shrink:0 !important;">
                    <i class="fas fa-xmark"
                        style="display:inline-flex !important; align-items:center !important; justify-content:center !important; line-height:1 !important; font-size:15px !important; margin:0 !important; padding:0 !important;"></i>
                </button>
            </div>
            <form id="dior-feedback-form" onsubmit="return diorSaveFeedback(event)">
                <input type="hidden" name="feedback_id" id="dior-feedback-id" value="">
                <div class="dior-static-modal-body">
                    <p class="dior-feedback-intro">How was your experience today? Your feedback is sent privately to
                        your doctor.</p>
                    <div class="dior-feedback-recipient">
                        <i class="fas fa-user-doctor" aria-hidden="true"></i>
                        <span>Sending to</span>
                        <strong><?php echo esc_html($feedback_doctor_name ?: 'Care team'); ?></strong>
                    </div>
                    <label for="dior-feedback-subject">Subject</label>
                    <input name="subject" id="dior-feedback-subject" maxlength="190" required
                        placeholder="What is your feedback about?">
                    <span class="dior-feedback-rating-label">Your rating</span>
                    <div class="dior-feedback-rating-control" role="radiogroup"
                        aria-label="Choose your feedback rating">
                        <input type="radio" id="dior-feedback-rating-1" name="rating" value="1">
                        <label for="dior-feedback-rating-1"
                            title="Poor"><span>&#128542;</span><small>Poor</small></label>
                        <input type="radio" id="dior-feedback-rating-2" name="rating" value="2">
                        <label for="dior-feedback-rating-2"
                            title="Fair"><span>&#128577;</span><small>Fair</small></label>
                        <input type="radio" id="dior-feedback-rating-3" name="rating" value="3">
                        <label for="dior-feedback-rating-3"
                            title="Okay"><span>&#128528;</span><small>Okay</small></label>
                        <input type="radio" id="dior-feedback-rating-4" name="rating" value="4">
                        <label for="dior-feedback-rating-4"
                            title="Good"><span>&#128578;</span><small>Good</small></label>
                        <input type="radio" id="dior-feedback-rating-5" name="rating" value="5" checked required>
                        <label for="dior-feedback-rating-5"
                            title="Excellent"><span>&#128522;</span><small>Great</small></label>
                    </div>
                    <label for="dior-feedback-message">Message</label>
                    <textarea name="message" id="dior-feedback-message" rows="5" maxlength="3000" required
                        placeholder="Tell us about your experience..."></textarea>
                </div>
                <div class="dior-static-modal-footer">
                    <button type="button" onclick="diorCloseStaticModal('dior-feedback-modal')">Cancel</button>
                    <button type="submit" class="primary btn" id="dior-feedback-submit-btn"><i
                            class="fas fa-paper-plane"></i> Submit Feedback</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
    window.diorFilterFeedback = function (val) {
        var q = (val || '').toLowerCase().trim();
        var table = document.getElementById('dior-feedback-table');
        if (!table || !table.tBodies[0]) return;
        var rows = Array.from(table.tBodies[0].querySelectorAll('tr[data-feedback-id]'));
        var count = 0;
        rows.forEach(function (row) {
            var text = row.textContent.toLowerCase();
            var match = !q || text.indexOf(q) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) count++;
        });
        var totalLabel = document.getElementById('dior-feedback-total-count');
        if (totalLabel) {
            totalLabel.textContent = (q ? count + ' matching' : rows.length) + ' total feedback records';
        }
    };

    window.diorToggleAllFeedback = function (master) {
        document.querySelectorAll('.dior-feedback-row-cb').forEach(function (cb) {
            cb.checked = master.checked;
        });
    };

    window.diorUpdateFeedbackCheckState = function () {
        var all = document.querySelectorAll('.dior-feedback-row-cb');
        var checked = document.querySelectorAll('.dior-feedback-row-cb:checked');
        var master = document.getElementById('dior-feedback-select-all');
        if (master) {
            master.checked = all.length > 0 && all.length === checked.length;
        }
    };

    window.diorDownloadFeedbackCSV = function () {
        var table = document.getElementById('dior-feedback-table');
        if (!table || !table.tBodies[0]) return;
        var rows = Array.from(table.tBodies[0].querySelectorAll('tr[data-feedback-id]'));
        if (!rows.length) {
            Swal.fire({ icon: 'info', title: 'No Records', text: 'No feedback records available to export.' });
            return;
        }
        var csv = ['"Subject","Doctor","Rating","Message","Date","Status"'];
        rows.forEach(function (row) {
            if (row.style.display === 'none') return;
            var cells = row.querySelectorAll('td');
            if (cells.length < 8) return;
            var subject = (cells[1].innerText || '').trim().replace(/"/g, '""');
            var doctor = (cells[2].innerText || '').trim().replace(/"/g, '""');
            var rating = (cells[3].innerText || '').trim().replace(/"/g, '""');
            var message = (cells[4].innerText || '').trim().replace(/"/g, '""');
            var date = (cells[5].innerText || '').trim().replace(/"/g, '""');
            var status = (cells[6].innerText || '').trim().replace(/"/g, '""');
            csv.push('"' + subject + '","' + doctor + '","' + rating + '","' + message + '","' + date + '","' + status + '"');
        });
        var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'patient_feedback_' + new Date().toISOString().slice(0, 10) + '.csv';
        link.click();
    };

    window.diorBulkDeleteFeedback = function () {
        var checked = Array.from(document.querySelectorAll('.dior-feedback-row-cb:checked'));
        if (!checked.length) {
            Swal.fire({ icon: 'info', title: 'No Selection', text: 'Please select at least one feedback record to delete.' });
            return;
        }

        var doBulkDelete = function () {
            var cfg = window.dior_vars || window.dior_patient_dashboard || {};
            var ajaxUrl = cfg.ajax_url || '/wp-admin/admin-ajax.php';
            var nonce = cfg.nonce || cfg.portal_nonce || '';
            var ids = checked.map(function (cb) { return cb.value; });

            var fd = new FormData();
            fd.append('action', 'dior_patient_bulk_delete_feedback');
            fd.append('nonce', nonce);
            ids.forEach(function (id) { fd.append('feedback_ids[]', id); });

            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        checked.forEach(function (cb) {
                            var row = cb.closest('tr');
                            if (row) row.remove();
                        });
                        var master = document.getElementById('dior-feedback-select-all');
                        if (master) master.checked = false;
                        var totalLabel = document.getElementById('dior-feedback-total-count');
                        var tbody = document.querySelector('#dior-feedback-table tbody');
                        var count = tbody ? tbody.querySelectorAll('tr[data-feedback-id]').length : 0;
                        if (totalLabel) totalLabel.textContent = count + ' total feedback records';
                        if (count === 0 && tbody) {
                            tbody.innerHTML = '<tr><td colspan="8" class="dior-empty-state">You have not submitted feedback yet.</td></tr>';
                        }
                        Swal.fire({ position: 'top-end', icon: 'success', title: res.data.message || 'Records Deleted', showConfirmButton: false, timer: 1500 });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: (res.data && res.data.message) || 'Bulk delete failed.' });
                    }
                })
                .catch(function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
                });
        };

        Swal.fire({
            title: 'Delete ' + checked.length + ' feedback record(s)?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#DC2626',
            cancelButtonColor: '#94A3B8',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then(function (r) {
            if (r.isConfirmed) doBulkDelete();
        });
    };
</script>