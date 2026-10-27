<?php
/**
 * Patient Review Tab — Dynamic, DB-connected
 * Actions: Edit, Delete, View (popup)
 */
global $wpdb;
$nonce = wp_create_nonce('dior_doctor_nonce');
$ajax_url = admin_url('admin-ajax.php');
$doctor_id = get_current_user_id();

// Keep doctor reviews scoped to the signed-in doctor; administrators can review all submissions.
$reviews_sql = "SELECT r.*, 
    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))), ''), NULLIF(u.display_name, ''), u.user_login, 'Patient') AS patient_display_name,
    COALESCE(p.avatar_url, '') AS avatar_url
    FROM {$wpdb->prefix}dior_reviews r
    LEFT JOIN {$wpdb->prefix}dior_patients p ON p.user_id = r.patient_id
    LEFT JOIN {$wpdb->users} u ON u.ID = r.patient_id";
if (!current_user_can('manage_options')) {
    $review_doctor_ids = Dior_Doctor_Resolver::get_accessible_doctor_ids($doctor_id);
    if (!empty($review_doctor_ids)) {
        $review_placeholders = implode(',', array_fill(0, count($review_doctor_ids), '%d'));
        $reviews_sql .= $wpdb->prepare(" WHERE r.doctor_id IN ($review_placeholders)", ...$review_doctor_ids);
    }
}
$reviews = $wpdb->get_results($reviews_sql . ' ORDER BY r.created_at DESC LIMIT 200', ARRAY_A);

// Fetch patients for dropdown in future
$patients = $wpdb->get_results(
    "SELECT user_id, first_name, last_name, patient_uid FROM {$wpdb->prefix}dior_patients WHERE status <> 'deleted' ORDER BY first_name ASC LIMIT 300",
    ARRAY_A
);
if (empty($patients)) {
    $wp_users = get_users(['role__in' => ['patient', 'subscriber', 'customer'], 'number' => 100]);
    $patients = [];
    foreach ($wp_users as $u) {
        $patients[] = [
            'user_id' => $u->ID,
            'first_name' => $u->first_name ?: $u->display_name,
            'last_name' => $u->last_name,
            'patient_uid' => get_user_meta($u->ID, 'patient_id', true) ?: 'DM-' . (10000 + $u->ID),
        ];
    }
}
?>
<section class="dior-tab-panel documents-tab-panel" id="tab-doc-patient-review">
    <!-- Breadcrumb -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Feedback &amp; Reviews</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list"
                style="display:flex;gap:8px;list-style:none;padding:0;margin:0;align-items:center;">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span style="color:#1E293B;font-weight:700;font-size:14px;">Feedback &amp;
                        Reviews</span></li>
            </ul>
        </div>
    </div>

    <div class="view-appointment-card">
        <!-- Header -->
        <div class="va-header-container">
            <div class="va-title-box">
                <h2><i class="fas fa-star text-primary me-2"></i>All Reviews</h2>
                <div class="va-title-line"></div>
            </div>
            <div class="va-actions-wrapper">
                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                    <input type="text" id="rv-search" class="form-control border-start-0 ps-0"
                        placeholder="Search reviews...">
                </div>
                <div class="va-actions-group">
                    <button class="va-icon-btn va-btn-danger btn" id="rv-bulk-delete-btn" title="Delete Selected">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button class="va-icon-btn va-btn-success btn" id="rv-export-btn" title="Export CSV">
                        <i class="fas fa-file-arrow-down"></i>
                    </button>
                    <button class="va-icon-btn va-btn-info btn" id="rv-refresh-btn" title="Refresh">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="va-table-wrapper">
            <table class="va-table table table-hover" id="rv-table">
                <thead>
                    <tr>
                        <th style="width:50px;" class="text-center"><input type="checkbox"
                                class="va-checkbox form-check-input" id="rv-select-all"></th>
                        <th>PATIENT <i class="fas fa-sort"></i></th>
                        <th>SUBJECT</th>
                        <th>RATING <i class="fas fa-sort"></i></th>
                        <th>DATE <i class="fas fa-sort"></i></th>
                        <th>COMMENT</th>
                        <th>STATUS <i class="fas fa-sort"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="rv-tbody">
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $r):
                            $rid = (int) ($r['id'] ?? 0);
                            $pid = (int) ($r['patient_id'] ?? 0);
                            $fname = esc_html($r['patient_display_name'] ?? 'Patient');
                            $avatar = esc_url($r['avatar_url'] ?? '');
                            $subject = esc_html($r['subject'] ?? '');
                            $rating = (int) ($r['rating'] ?? 5);
                            $comment = esc_html($r['comment'] ?? '');
                            $status = esc_html($r['status'] ?? 'Published');
                            $date_f = !empty($r['created_at']) ? date('M d, Y', strtotime($r['created_at'])) : '—';
                            $badge = ($status === 'Published') ? 'va-badge-completed' : 'va-badge-pending';
                            $enc = esc_attr(wp_json_encode(['id' => $rid, 'patient_id' => $pid, 'subject' => $r['subject'] ?? '', 'rating' => $rating, 'comment' => $r['comment'] ?? '', 'status' => $status]));
                            ?>
                            <tr data-id="<?php echo $rid; ?>">
                                <td class="text-center"><input type="checkbox"
                                        class="va-checkbox rv-row-check form-check-input"></td>
                                <td>
                                    <div class="va-user-profile">
                                        <img src="<?php echo $avatar ?: 'https://ui-avatars.com/api/?name=' . urlencode($fname) . '&background=random'; ?>"
                                            onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($fname); ?>&background=random'"
                                            class="va-user-avatar" alt="<?php echo $fname; ?>">
                                        <span class="va-user-name"><?php echo $fname; ?></span>
                                    </div>
                                </td>
                                <td><?php echo $subject !== '' ? $subject : 'Patient Review'; ?></td>
                                <td>
                                    <div style="color:#F59E0B;font-size:14px;display:flex;gap:2px;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa-<?php echo $i <= $rating ? 'solid' : 'regular'; ?> fa-star" <?php if ($i > $rating)
                                                          echo 'style="color:#CBD5E1;"'; ?>></i>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td><i class="far fa-calendar va-icon-blue"></i> <?php echo $date_f; ?></td>
                                <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                    title="<?php echo $comment; ?>"><?php echo $comment; ?></td>
                                <td><span class="va-badge <?php echo $badge; ?>"><?php echo $status; ?></span></td>
                                <td>
                                    <div class="va-row-actions">
                                        <button type="button" class="va-action-btn-sm va-btn-view btn" title="View Review"
                                            data-enc="<?php echo $enc; ?>" data-name="<?php echo esc_attr($fname); ?>"
                                            onclick="rvOpenViewModal(this, event)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="va-action-btn-sm va-btn-edit rv-edit-btn btn"
                                            data-enc="<?php echo $enc; ?>" title="Edit Review"
                                            onclick="rvOpenEditModal(this, event)">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button type="button" class="va-action-btn-sm va-btn-delete rv-delete-btn btn"
                                            data-id="<?php echo $rid; ?>" title="Delete Review"
                                            onclick="rvDeleteDirect(this, event)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">No reviews found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</section>

<!-- Standard Bootstrap 5 View Review Modal -->
<div class="modal fade" id="diorReviewViewModal" tabindex="-1" aria-labelledby="diorReviewViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="diorReviewViewModalLabel">
                    <i class="fas fa-star text-warning me-2"></i>Review Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white" id="rv-view-body">
                <!-- Dynamically populated via JS -->
            </div>
            <div class="modal-footer border-top py-3 px-4 bg-white">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Standard Bootstrap 5 Edit Review Modal -->
<div class="modal fade" id="diorReviewEditModal" tabindex="-1" aria-labelledby="diorReviewEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="diorReviewEditModalLabel">
                    <i class="fas fa-pen text-primary me-2"></i>Edit Review
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <input type="hidden" id="rv-edit-id">
                <input type="hidden" id="rv-edit-pid">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small text-uppercase text-secondary fw-semibold mb-1">Rating <span class="text-danger">*</span></label>
                        <div id="rv-star-editor" class="d-flex gap-2 fs-4 text-warning" style="cursor: pointer;">
                            <i class="fas fa-star" data-val="1"></i>
                            <i class="fas fa-star" data-val="2"></i>
                            <i class="fas fa-star" data-val="3"></i>
                            <i class="fas fa-star" data-val="4"></i>
                            <i class="fas fa-star" data-val="5"></i>
                        </div>
                        <input type="hidden" id="rv-rating-val" value="5">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="rv-comment">Comment</label>
                        <textarea id="rv-comment" rows="4" class="form-control rounded-3 py-2" placeholder="Patient's review comments..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-uppercase text-secondary fw-semibold mb-1" for="rv-status">Review Status</label>
                        <select id="rv-status" class="form-select rounded-3 py-2">
                            <option value="Published">Published</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="rv-save-btn" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm">
                    <i class="fas fa-check me-1"></i> Save Review
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.rvOpenViewModal = function(btn, ev) {
        if (ev) { ev.preventDefault(); ev.stopPropagation(); }
        // Ensure edit modal is closed
        var editModalEl = document.getElementById('diorReviewEditModal');
        if (editModalEl && window.bootstrap && bootstrap.Modal) {
            var emInst = bootstrap.Modal.getInstance(editModalEl);
            if (emInst) emInst.hide();
        }

        var enc = JSON.parse(btn.dataset.enc || '{}');
        var name = btn.dataset.name || 'Patient';
        var stars = '';
        for (var i = 1; i <= 5; i++) {
            stars += '<i class="fa-' + (i <= enc.rating ? 'solid' : 'regular') + ' fa-star ' + (i <= enc.rating ? 'text-warning' : 'text-secondary-subtle') + '"></i> ';
        }
        document.getElementById('rv-view-body').innerHTML =
            '<div class="d-flex align-items-center gap-3 mb-3">' +
            '<img src="https://ui-avatars.com/api/?name=' + encodeURIComponent(name) + '&background=random" class="rounded-circle" style="width:48px;height:48px;object-fit:cover;" alt="">' +
            '<div><div class="fw-bold text-dark fs-6">' + name + '</div><span class="badge bg-light text-secondary border rounded-pill small">' + (enc.status || 'Published') + '</span></div></div>' +
            '<div class="mb-3 fs-5">' + stars + '</div>' +
            (enc.subject ? '<div class="fw-bold text-dark mb-2">' + enc.subject + '</div>' : '') +
            '<div class="p-3 bg-light rounded-3 text-secondary small lh-base">' + (enc.comment || 'No comment provided.') + '</div>';

        var viewModalEl = document.getElementById('diorReviewViewModal');
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(viewModalEl).show();
        }
    };

    window.rvOpenEditModal = function(btn, ev) {
        if (ev) { ev.preventDefault(); ev.stopPropagation(); }
        // Ensure view modal is closed
        var viewModalEl = document.getElementById('diorReviewViewModal');
        if (viewModalEl && window.bootstrap && bootstrap.Modal) {
            var vmInst = bootstrap.Modal.getInstance(viewModalEl);
            if (vmInst) vmInst.hide();
        }

        var enc = JSON.parse(btn.dataset.enc || '{}');
        document.getElementById('rv-edit-id').value = enc.id || '';
        document.getElementById('rv-edit-pid').value = enc.patient_id || '';
        document.getElementById('rv-rating-val').value = enc.rating || 5;
        document.getElementById('rv-comment').value = enc.comment || '';
        document.getElementById('rv-status').value = enc.status || 'Published';

        var stars = document.querySelectorAll('#rv-star-editor i');
        stars.forEach(function(s) {
            var sVal = parseInt(s.dataset.val, 10);
            s.className = (sVal <= (enc.rating || 5) ? 'fas fa-star text-warning' : 'far fa-star text-secondary-subtle');
        });

        var editModalEl = document.getElementById('diorReviewEditModal');
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(editModalEl).show();
        }
    };

    (function () {
        var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
        var nonce = '<?php echo esc_js($nonce); ?>';
        var allRows = [];

        function init() {
            allRows = Array.from(document.querySelectorAll('#rv-tbody tr[data-id]'));

            var selectAll = document.getElementById('rv-select-all');
            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    document.querySelectorAll('.rv-row-check').forEach(function (cb) { cb.checked = selectAll.checked; });
                });
            }

            var refreshBtn = document.getElementById('rv-refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function () { location.reload(); });
            }

            var searchInput = document.getElementById('rv-search');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    var q = this.value.toLowerCase().trim();
                    allRows.forEach(function (row) {
                        var text = row.textContent.toLowerCase();
                        row.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
                    });
                });
            }

            var exportBtn = document.getElementById('rv-export-btn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function () {
                    var rows = Array.from(document.querySelectorAll('#rv-tbody tr[data-id]'));
                    if (!rows.length) { Swal.fire({ icon: 'info', title: 'No Reviews', text: 'No reviews to export.' }); return; }
                    var csv = ['"Patient","Subject","Rating","Date","Comment","Status"'];
                    rows.forEach(function (row) {
                        if (row.style.display === 'none') return;
                        var name = (row.querySelector('.va-user-name') ? row.querySelector('.va-user-name').innerText : '').replace(/"/g, '""');
                        var cells = row.querySelectorAll('td');
                        var subject = (cells[2] ? cells[2].innerText.trim() : '').replace(/"/g, '""');
                        var rating = 5;
                        var editBtn = row.querySelector('.rv-edit-btn');
                        if (editBtn) {
                            try { rating = JSON.parse(editBtn.dataset.enc || '{}').rating || 5; } catch (e) { }
                        }
                        var date = (cells[4] ? cells[4].innerText.trim() : '').replace(/"/g, '""');
                        var comment = (cells[5] ? cells[5].innerText.trim() : '').replace(/"/g, '""');
                        var status = (cells[6] ? cells[6].innerText.trim() : '').replace(/"/g, '""');
                        csv.push('"' + name + '","' + subject + '","' + rating + '","' + date + '","' + comment + '","' + status + '"');
                    });
                    var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'patient_reviews_' + new Date().toISOString().slice(0, 10) + '.csv';
                    link.click();
                });
            }

            var bulkDelBtn = document.getElementById('rv-bulk-delete-btn');
            if (bulkDelBtn) {
                bulkDelBtn.addEventListener('click', function () {
                    var checked = Array.from(document.querySelectorAll('.rv-row-check:checked'));
                    if (!checked.length) { Swal.fire({ icon: 'info', title: 'Selection Required', text: 'Please select at least one review to delete.' }); return; }
                    Swal.fire({
                        title: 'Delete Selected Reviews?',
                        text: 'Are you sure you want to delete ' + checked.length + ' selected review(s)? This cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#EF4444',
                        cancelButtonColor: '#64748B',
                        confirmButtonText: 'Yes, delete',
                        cancelButtonText: 'Cancel'
                    }).then(function (result) {
                        if (!result.isConfirmed) return;
                        var ids = checked.map(function (cb) { return cb.closest('tr').dataset.id; });
                        var data = new FormData();
                        data.append('action', 'dior_doc_review_bulk_delete');
                        data.append('nonce', nonce);
                        ids.forEach(function (id) { data.append('ids[]', id); });
                        fetch(ajaxUrl, { method: 'POST', body: data })
                            .then(function (r) { return r.json(); })
                            .then(function (res) {
                                if (res.success) {
                                    checked.forEach(function (cb) {
                                        var row = cb.closest('tr');
                                        allRows = allRows.filter(function (r) { return r !== row; });
                                        row.remove();
                                    });
                                    if (selectAll) selectAll.checked = false;
                                    Swal.fire({ icon: 'success', title: 'Deleted', text: 'Reviews deleted successfully.', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Bulk Delete Failed', text: res.data && res.data.message ? res.data.message : 'Bulk delete failed.' });
                                }
                            });
                    });
                });
            }

            setupStarEditor();
            var saveBtn = document.getElementById('rv-save-btn');
            if (saveBtn) saveBtn.addEventListener('click', saveReview);
        }

        function setupStarEditor() {
            var stars = document.querySelectorAll('#rv-star-editor i');
            stars.forEach(function (s) {
                s.addEventListener('click', function () {
                    var val = parseInt(this.dataset.val, 10);
                    document.getElementById('rv-rating-val').value = val;
                    stars.forEach(function (st) {
                        var stVal = parseInt(st.dataset.val, 10);
                        st.className = (stVal <= val ? 'fas fa-star text-warning' : 'far fa-star text-secondary-subtle');
                    });
                });
            });
        }

        window.rvDeleteDirect = function(btn, ev) {
            if (ev) { ev.preventDefault(); ev.stopPropagation(); }
            deleteReview(btn);
        };

        function saveReview() {
            var btn = document.getElementById('rv-save-btn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Saving...';
            var data = new FormData();
            data.append('action', 'dior_doc_review_save');
            data.append('nonce', nonce);
            data.append('id', document.getElementById('rv-edit-id').value);
            data.append('patient_id', document.getElementById('rv-edit-pid').value);
            data.append('rating', document.getElementById('rv-rating-val').value);
            data.append('comment', document.getElementById('rv-comment').value);
            data.append('status', document.getElementById('rv-status').value);
            fetch(ajaxUrl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        var editModalEl = document.getElementById('diorReviewEditModal');
                        if (window.bootstrap && bootstrap.Modal) {
                            var emInst = bootstrap.Modal.getInstance(editModalEl);
                            if (emInst) emInst.hide();
                        }
                        location.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Save Failed', text: res.data && res.data.message ? res.data.message : 'Save failed.' });
                    }
                })
                .catch(function() {
                    Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not connect to server.' });
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check me-1"></i> Save Review';
                });
        }

        function deleteReview(btn) {
            Swal.fire({
                title: 'Delete Review?',
                text: 'Are you sure you want to delete this review? This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (!result.isConfirmed) return;
                var data = new FormData();
                data.append('action', 'dior_doc_review_delete');
                data.append('nonce', nonce);
                data.append('id', btn.dataset.id);
                fetch(ajaxUrl, { method: 'POST', body: data })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) {
                            var row = btn.closest('tr');
                            allRows = allRows.filter(function (r) { return r !== row; }); row.remove();
                            Swal.fire({ icon: 'success', title: 'Deleted', text: 'Review deleted successfully.', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
                        } else { Swal.fire({ icon: 'error', title: 'Delete Failed', text: res.data && res.data.message ? res.data.message : 'Delete failed.' }); }
                    });
            });
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    })();
</script>