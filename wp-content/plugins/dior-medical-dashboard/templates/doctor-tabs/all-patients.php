<?php
/**
 * All Patients Tab — Dynamic, DB-connected
 * Actions: Edit Status (popup), Delete, Bulk Delete
 */
global $wpdb;
$nonce    = wp_create_nonce('dior_doctor_nonce');
$ajax_url = admin_url('admin-ajax.php');
$doctor_id = get_current_user_id();

// Fetch patients
$patients = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}dior_patients WHERE status <> 'deleted' ORDER BY created_at DESC LIMIT 200",
    ARRAY_A
);

// Fallback from wp_users if table empty
if (empty($patients)) {
    $users = get_users(['role__in' => ['patient','subscriber','customer'], 'number' => 100]);
    $patients = [];
    foreach ($users as $u) {
        $patients[] = [
            'user_id'     => $u->ID,
            'patient_uid' => get_user_meta($u->ID, 'patient_id', true) ?: 'DM-'.(10000+$u->ID),
            'first_name'  => $u->first_name ?: $u->display_name,
            'last_name'   => $u->last_name,
            'email'       => $u->user_email,
            'phone'       => get_user_meta($u->ID, 'phone', true),
            'gender'      => get_user_meta($u->ID, 'gender', true),
            'blood_group' => get_user_meta($u->ID, 'blood_group', true),
            'address'     => get_user_meta($u->ID, 'address', true),
            'status'      => get_user_meta($u->ID, 'dior_patient_status', true) ?: 'active',
            'avatar_url'  => get_user_meta($u->ID, 'dior_profile_image', true),
            'created_at'  => $u->user_registered,
        ];
    }
}

$status_badges = [
    'active'   => 'va-badge-completed',
    'inactive' => 'va-badge-pending',
    'blocked'  => 'va-badge-cancelled',
];
?>
<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/view-appointment.css?v=' . time()); ?>">

<section class="dior-tab-panel" id="tab-doc-patients-all" style="display:none;">
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;">
        <div><h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">All Patients</h4></div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li><a href="javascript:void(0)">Patients</a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>All Patients</span></li>
            </ul>
        </div>
    </div>

    <div class="section-body">
        <div class="view-appointment-card">
            <div class="va-header-container">
                <div class="va-title-box">
                    <h2>All Patients</h2>
                    <div class="va-title-line"></div>
                </div>
                <div class="va-actions-wrapper">
                    <div class="va-search-box">
                        <i class="fas fa-magnifying-glass"></i>
                        <input type="text" id="ap-search" placeholder="Search patients...">
                    </div>
                    <div class="va-actions-group">
                        <button class="va-icon-btn va-btn-danger" id="ap-bulk-delete-btn" title="Bulk Delete" style="display:none;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                        </button>
                        <button class="va-icon-btn va-btn-info" id="ap-refresh-btn" title="Refresh">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="va-table-wrapper">
                <table class="va-table" id="ap-patients-table">
                    <thead>
                        <tr>
                            <th style="width:50px;" class="text-center">
                                <input type="checkbox" class="va-checkbox" id="ap-select-all">
                            </th>
                            <th>Name <i class="fas fa-sort"></i></th>
                            <th>Patient ID</th>
                            <th>Phone <i class="fas fa-sort"></i></th>
                            <th>Gender <i class="fas fa-sort"></i></th>
                            <th>Blood Group</th>
                            <th>Address</th>
                            <th>Status <i class="fas fa-sort"></i></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ap-patients-tbody">
                    <?php if (!empty($patients)): ?>
                        <?php foreach ($patients as $p):
                            $name       = esc_html(trim(($p['first_name']??'') . ' ' . ($p['last_name']??''))) ?: 'Unknown';
                            $pid        = (int)($p['user_id'] ?? 0);
                            $puid       = esc_html($p['patient_uid'] ?? '');
                            $phone      = esc_html($p['phone'] ?? '—');
                            $gender     = esc_html(ucfirst($p['gender'] ?? '—'));
                            $blood      = esc_html($p['blood_group'] ?? '—');
                            $address    = esc_html($p['address'] ?? '—');
                            $status     = $p['status'] ?? 'active';
                            $badge      = $status_badges[$status] ?? 'va-badge-pending';
                            $avatar     = esc_url($p['avatar_url'] ?? '');
                        ?>
                        <tr data-patient-id="<?php echo $pid; ?>">
                            <td class="text-center"><input type="checkbox" class="va-checkbox ap-row-check" data-id="<?php echo $pid; ?>"></td>
                            <td>
                                <div class="va-user-profile">
                                    <img src="<?php echo $avatar ?: 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=random'; ?>"
                                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($name); ?>&background=random'"
                                         class="va-user-avatar" alt="<?php echo $name; ?>">
                                    <span class="va-user-name"><?php echo $name; ?></span>
                                </div>
                            </td>
                            <td><code style="font-size:12px;background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?php echo $puid; ?></code></td>
                            <td><i class="fas fa-phone va-icon-blue"></i> <?php echo $phone; ?></td>
                            <td><?php echo ($gender === 'Male' || $gender === 'male') ? '<i class="fas fa-mars va-icon-blue"></i> Male' : (($gender === 'Female' || $gender === 'female') ? '<i class="fas fa-venus va-icon-pink"></i> Female' : $gender); ?></td>
                            <td><?php echo $blood; ?></td>
                            <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($address); ?>"><?php echo $address; ?></td>
                            <td><span class="va-badge <?php echo $badge; ?> ap-status-badge"><?php echo ucfirst($status); ?></span></td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit ap-edit-status-btn"
                                            data-id="<?php echo $pid; ?>"
                                            data-name="<?php echo esc_attr($name); ?>"
                                            data-status="<?php echo esc_attr($status); ?>"
                                            title="Edit Status">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg>
                                    </button>
                                    <button class="va-action-btn-sm va-btn-delete ap-delete-btn"
                                            data-id="<?php echo $pid; ?>"
                                            data-name="<?php echo esc_attr($name); ?>"
                                            title="Delete Patient">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                                    </button>
                                    <button class="va-action-btn-sm"
                                            style="background:transparent; border:none;"
                                            data-patient-id="<?php echo $pid; ?>"
                                            onclick="window.diorSelectedPatientId='<?php echo $pid; ?>';if(typeof window.diorSwitchTab==='function')window.diorSwitchTab('doc-patients-profile');if(typeof window.diorLoadPatientProfile==='function')window.diorLoadPatientProfile('<?php echo $pid; ?>');"
                                            title="View Profile">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3b82f6" class="bi bi-eye" viewBox="0 0 16 16" style="background:transparent;"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" style="text-align:center;padding:40px;color:#94a3b8;">No patients found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 0 0;">
                <div id="ap-showing-info" style="color:#64748b;font-size:13px;font-weight:500;">
                    <?php echo count($patients); ?> patient(s) total
                </div>
                <div id="ap-pagination" style="display:flex;gap:6px;"></div>
            </div>
        </div>
    </div>
</section>

<!-- Edit Status Modal -->
<div id="ap-edit-status-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:380px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h3 style="margin:0;font-size:18px;font-weight:700;color:#1e293b;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg>Update Status</h3>
            <button onclick="document.getElementById('ap-edit-status-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#94a3b8;">&times;</button>
        </div>
        <p id="ap-edit-patient-name" style="color:#64748b;font-size:14px;margin:0 0 16px;"></p>
        <input type="hidden" id="ap-edit-patient-id">
        <div>
            <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">New Status</label>
            <select id="ap-edit-status-select" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="blocked">Blocked</option>
            </select>
        </div>
        <div style="display:flex;gap:10px;margin-top:24px;justify-content:flex-end;">
            <button onclick="document.getElementById('ap-edit-status-modal').style.display='none'"
                    style="padding:10px 20px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;color:#64748b;font-size:14px;cursor:pointer;">
                Cancel
            </button>
            <button id="ap-save-status-btn"
                    style="padding:10px 24px;border:none;border-radius:8px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;font-size:14px;font-weight:600;cursor:pointer;">
                <i class="fas fa-check" style="margin-right:6px;"></i>Save Status
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var nonce   = '<?php echo esc_js($nonce); ?>';
    var perPage = 10;
    var currentPage = 1;
    var allRows;

    function init() {
        allRows = Array.from(document.querySelectorAll('#ap-patients-tbody tr[data-patient-id]'));
        renderPage(1);
        // Search
        document.getElementById('ap-search').addEventListener('input', applyFilters);
        // Select all
        document.getElementById('ap-select-all').addEventListener('change', function() {
            document.querySelectorAll('.ap-row-check').forEach(function(cb) { cb.checked = this.checked; }, this);
            updateBulkBtn();
        });
        document.getElementById('ap-patients-tbody').addEventListener('change', function(e) {
            if (e.target.classList.contains('ap-row-check')) updateBulkBtn();
        });
        // Edit status
        document.getElementById('ap-patients-tbody').addEventListener('click', function(e) {
            var btn = e.target.closest('.ap-edit-status-btn');
            if (!btn) return;
            document.getElementById('ap-edit-patient-id').value = btn.dataset.id;
            document.getElementById('ap-edit-patient-name').textContent = 'Patient: ' + btn.dataset.name;
            document.getElementById('ap-edit-status-select').value = btn.dataset.status;
            document.getElementById('ap-edit-status-modal').style.display = 'flex';
        });
        // Save status
        document.getElementById('ap-save-status-btn').addEventListener('click', function() {
            var btn = this;
            btn.disabled = true;
            var data = new FormData();
            data.append('action', 'dior_doc_patient_status');
            data.append('nonce', nonce);
            data.append('patient_id', document.getElementById('ap-edit-patient-id').value);
            data.append('status', document.getElementById('ap-edit-status-select').value);
            fetch(ajaxUrl, {method:'POST',body:data})
                .then(function(r){return r.json();})
                .then(function(res) {
                    if (res.success) {
                        document.getElementById('ap-edit-status-modal').style.display = 'none';
                        location.reload();
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Update failed.');
                    }
                }).finally(function() { btn.disabled = false; });
        });
        // Delete
        document.getElementById('ap-patients-tbody').addEventListener('click', function(e) {
            var btn = e.target.closest('.ap-delete-btn');
            if (!btn) return;
            if (!confirm('Delete patient "' + btn.dataset.name + '"? This cannot be undone.')) return;
            var data = new FormData();
            data.append('action', 'dior_doc_patient_delete');
            data.append('nonce', nonce);
            data.append('patient_id', btn.dataset.id);
            fetch(ajaxUrl, {method:'POST',body:data})
                .then(function(r){return r.json();})
                .then(function(res) {
                    if (res.success) {
                        var row = btn.closest('tr');
                        allRows = allRows.filter(function(r){return r!==row;});
                        row.remove();
                        renderPage(currentPage);
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Delete failed.');
                    }
                });
        });
        // Bulk delete
        document.getElementById('ap-bulk-delete-btn').addEventListener('click', function() {
            var ids = Array.from(document.querySelectorAll('.ap-row-check:checked')).map(function(cb){return cb.dataset.id;});
            if (!ids.length) return;
            if (!confirm('Delete ' + ids.length + ' selected patient(s)? This cannot be undone.')) return;
            var data = new FormData();
            data.append('action', 'dior_doc_patient_bulk_delete');
            data.append('nonce', nonce);
            ids.forEach(function(id) { data.append('patient_ids[]', id); });
            fetch(ajaxUrl, {method:'POST',body:data})
                .then(function(r){return r.json();})
                .then(function(res) {
                    if (res.success) { location.reload(); }
                    else { alert(res.data && res.data.message ? res.data.message : 'Bulk delete failed.'); }
                });
        });
        // Refresh
        document.getElementById('ap-refresh-btn').addEventListener('click', function(){location.reload();});
    }

    function updateBulkBtn() {
        var any = Array.from(document.querySelectorAll('.ap-row-check')).some(function(cb){return cb.checked;});
        document.getElementById('ap-bulk-delete-btn').style.display = any ? 'flex' : 'none';
    }

    function applyFilters() {
        var search = document.getElementById('ap-search').value.toLowerCase();
        allRows.forEach(function(r) {
            r.style.display = (!search || r.textContent.toLowerCase().indexOf(search) !== -1) ? '' : 'none';
        });
        renderPage(1);
    }

    function getVisibleRows() { return allRows.filter(function(r){return r.style.display!=='none';}); }

    function renderPage(page) {
        currentPage = page;
        var visible = getVisibleRows();
        var total = visible.length;
        var start = (page-1)*perPage;
        allRows.forEach(function(r){r.style.display='none';});
        visible.slice(start, start+perPage).forEach(function(r){r.style.display='';});
        document.getElementById('ap-showing-info').textContent =
            'Showing ' + Math.min(start+1,total) + '–' + Math.min(start+perPage,total) + ' of ' + total + ' patient(s)';
        renderPagination(total);
    }

    function renderPagination(total) {
        var pages = Math.ceil(total/perPage);
        var container = document.getElementById('ap-pagination');
        container.innerHTML = '';
        if (pages <= 1) return;
        for (var i=1; i<=pages; i++) {
            (function(p) {
                var btn = document.createElement('button');
                btn.textContent = p;
                btn.style.cssText = 'width:32px;height:32px;border-radius:6px;border:1px solid '+(p===currentPage?'#4f46e5':'#e2e8f0')+';background:'+(p===currentPage?'#4f46e5':'#fff')+';color:'+(p===currentPage?'#fff':'#475569')+';font-size:13px;cursor:pointer;';
                btn.addEventListener('click', function(){renderPage(p);});
                container.appendChild(btn);
            })(i);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else { init(); }
})();
</script>
