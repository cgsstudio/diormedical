<?php
/**
 * View Appointment Tab — Dynamic, DB-connected
 * Actions: Edit (status/date/time), Delete (permanent), View Patient
 */
global $wpdb;
$doctor_id  = get_current_user_id();
$nonce      = wp_create_nonce('dior_doctor_nonce');
$ajax_url   = admin_url('admin-ajax.php');

// Fetch appointments from DB
$appointments = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT a.*, 
                p.first_name, p.last_name, p.phone, p.gender, p.avatar_url, p.user_id as patient_user_id,
                p.patient_uid
         FROM {$wpdb->prefix}dior_appointments a
         LEFT JOIN {$wpdb->prefix}dior_patients p ON p.user_id = a.patient_id
         WHERE a.is_deleted = 0
         ORDER BY a.appt_date DESC, a.created_at DESC
         LIMIT 200",
    ),
    ARRAY_A
);

// Fallback: build from usermeta if table is empty
if (empty($appointments)) {
    $patients = get_users(['role__in' => ['patient','subscriber','customer'], 'number' => 50]);
    $appointments = [];
    foreach ($patients as $u) {
        $raw = get_user_meta($u->ID, 'dior_appointments', true);
        $list = is_array($raw) ? $raw : (is_string($raw) ? maybe_unserialize($raw) : []);
        if (!is_array($list)) continue;
        foreach ($list as $a) {
            $appointments[] = array_merge($a, [
                'first_name'       => $u->first_name ?: $u->display_name,
                'last_name'        => $u->last_name,
                'phone'            => get_user_meta($u->ID, 'phone', true),
                'gender'           => get_user_meta($u->ID, 'gender', true),
                'avatar_url'       => get_user_meta($u->ID, 'dior_profile_image', true),
                'patient_uid'      => get_user_meta($u->ID, 'patient_id', true) ?: 'DM-'.(10000+$u->ID),
                'patient_user_id'  => $u->ID,
                'patient_id'       => $u->ID,
            ]);
        }
    }
}

$status_badges = [
    'Pending'     => 'va-badge-pending',
    'Confirmed'   => 'va-badge-confirmed',
    'In Progress' => 'va-badge-inprogress',
    'Completed'   => 'va-badge-completed',
    'Cancelled'   => 'va-badge-cancelled',
    'No Show'     => 'va-badge-cancelled',
];
?>

<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/view-appointment.css?v=' . time()); ?>">

<section class="dior-tab-panel" id="tab-doc-appointments-view" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">View Appointments</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li><a href="javascript:void(0)">Appointments</a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>View Appointments</span></li>
            </ul>
        </div>
    </div>

    <div class="section-body">
        <div class="view-appointment-card">
            <!-- Header -->
            <div class="va-header-container">
                <div class="va-title-box">
                    <h2>Appointments</h2>
                    <div class="va-title-line"></div>
                </div>
                <div class="va-actions-wrapper">
                    <div class="va-search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="va-appt-search" placeholder="Search appointments...">
                    </div>
                    <div class="va-actions-group">
                        <select id="va-status-filter" style="height:36px;border:1px solid #e2e8f0;border-radius:6px;padding:0 10px;font-size:13px;color:#475569;cursor:pointer;">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="No Show">No Show</option>
                        </select>
                        <button class="va-icon-btn va-btn-info" id="va-refresh-btn" aria-label="Refresh" title="Refresh"><i class="fa-solid fa-rotate-right"></i></button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="va-table-wrapper">
                <table class="va-table" id="va-appointments-table">
                    <thead>
                        <tr>
                            <th style="width:50px;" class="text-center"><input type="checkbox" class="va-checkbox" id="va-select-all"></th>
                            <th>Patient Name <i class="fa-solid fa-sort"></i></th>
                            <th>Condition <i class="fa-solid fa-sort"></i></th>
                            <th>Date <i class="fa-solid fa-sort"></i></th>
                            <th>Time <i class="fa-solid fa-sort"></i></th>
                            <th>Phone <i class="fa-solid fa-sort"></i></th>
                            <th>Status <i class="fa-solid fa-sort"></i></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="va-appointments-tbody">
                    <?php if (!empty($appointments)): ?>
                        <?php foreach ($appointments as $a):
                            $fname     = esc_html(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?: 'Unknown Patient';
                            $uid       = esc_attr($a['appt_uid'] ?? '');
                            $patient_id= esc_attr($a['patient_id'] ?? $a['patient_user_id'] ?? '');
                            $status    = esc_html($a['status'] ?? 'Pending');
                            $badge     = $status_badges[$status] ?? 'va-badge-pending';
                            $date_fmt  = !empty($a['appt_date']) ? date('M d, Y', strtotime($a['appt_date'])) : 'N/A';
                            $time      = esc_html($a['appt_time'] ?? '');
                            $phone     = esc_html($a['phone'] ?? '—');
                            $condition = esc_html($a['condition_name'] ?? $a['condition'] ?? '—');
                            $avatar    = esc_url($a['avatar_url'] ?? '');
                            $patient_uid = esc_attr($a['patient_uid'] ?? '');
                        ?>
                        <tr data-appt-uid="<?php echo $uid; ?>" data-patient-id="<?php echo $patient_id; ?>" data-patient-uid="<?php echo $patient_uid; ?>">
                            <td class="text-center"><input type="checkbox" class="va-checkbox va-row-check"></td>
                            <td>
                                <div class="va-user-profile">
                                    <img src="<?php echo $avatar ?: 'https://ui-avatars.com/api/?name='.urlencode($fname).'&background=random'; ?>"
                                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($fname); ?>&background=random'"
                                         class="va-user-avatar" alt="<?php echo $fname; ?>">
                                    <span class="va-user-name"><?php echo $fname; ?></span>
                                </div>
                            </td>
                            <td><?php echo $condition; ?></td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> <?php echo $date_fmt; ?></td>
                            <td><?php echo $time; ?></td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> <?php echo $phone; ?></td>
                            <td><span class="va-badge <?php echo $badge; ?>"><?php echo $status; ?></span></td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit va-edit-appt-btn"
                                            data-uid="<?php echo $uid; ?>"
                                            data-status="<?php echo esc_attr($status); ?>"
                                            data-date="<?php echo esc_attr($a['appt_date'] ?? ''); ?>"
                                            data-time="<?php echo esc_attr($time); ?>"
                                            title="Edit Appointment">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="va-action-btn-sm va-btn-delete va-delete-appt-btn"
                                            data-uid="<?php echo $uid; ?>"
                                            data-name="<?php echo $fname; ?>"
                                            title="Delete Appointment">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                    <button class="va-action-btn-sm va-btn-view va-view-patient-btn"
                                            data-patient-id="<?php echo $patient_id; ?>"
                                            title="View Patient Profile"
                                            style="background:#e0f2fe;color:#0284c7;border:1px solid #bae6fd;">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">No appointments found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="va-pagination-container" style="display:flex;justify-content:space-between;align-items:center;padding:16px 0 0;">
                <div id="va-showing-info" style="color:#64748b;font-size:13px;font-weight:500;">
                    <?php echo count($appointments); ?> appointment(s)
                </div>
                <div id="va-pagination" style="display:flex;gap:6px;"></div>
            </div>
        </div>
    </div>
</section>

<!-- Edit Appointment Modal -->
<div id="va-edit-appt-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:460px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h3 style="margin:0;font-size:18px;font-weight:700;color:#1e293b;"><i class="fa-solid fa-pen" style="color:#4f46e5;margin-right:8px;"></i>Edit Appointment</h3>
            <button onclick="document.getElementById('va-edit-appt-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#94a3b8;">&times;</button>
        </div>
        <input type="hidden" id="va-edit-uid">
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Status</label>
                <select id="va-edit-status" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;">
                    <option value="Pending">Pending</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="No Show">No Show</option>
                </select>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Appointment Date</label>
                <input type="date" id="va-edit-date" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;">
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Appointment Time</label>
                <input type="text" id="va-edit-time" placeholder="e.g. 10:00 AM" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;">
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:24px;justify-content:flex-end;">
            <button onclick="document.getElementById('va-edit-appt-modal').style.display='none'"
                    style="padding:10px 20px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;color:#64748b;font-size:14px;cursor:pointer;">
                Cancel
            </button>
            <button id="va-save-appt-btn"
                    style="padding:10px 24px;border:none;border-radius:8px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;font-size:14px;font-weight:600;cursor:pointer;">
                <i class="fa-solid fa-check" style="margin-right:6px;"></i>Save Changes
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    var ajaxUrl  = '<?php echo esc_js($ajax_url); ?>';
    var nonce    = '<?php echo esc_js($nonce); ?>';
    var perPage  = 10;
    var currentPage = 1;
    var allRows;

    function initViewAppt() {
        allRows = Array.from(document.querySelectorAll('#va-appointments-tbody tr[data-appt-uid]'));
        renderPage(1);
        setupSearch();
        setupStatusFilter();
        setupSelectAll();
        setupEditBtns();
        setupDeleteBtns();
        setupViewPatientBtns();
        document.getElementById('va-refresh-btn').addEventListener('click', function() {
            location.reload();
        });
    }

    function getVisibleRows() {
        return allRows.filter(function(r) { return r.style.display !== 'none'; });
    }

    function renderPage(page) {
        currentPage = page;
        var visible = getVisibleRows();
        var total = visible.length;
        var start = (page - 1) * perPage;
        allRows.forEach(function(r) { r.style.display = 'none'; });
        visible.slice(start, start + perPage).forEach(function(r) { r.style.display = ''; });
        document.getElementById('va-showing-info').textContent =
            'Showing ' + Math.min(start+1, total) + '–' + Math.min(start+perPage, total) + ' of ' + total + ' appointment(s)';
        renderPagination(total);
    }

    function renderPagination(total) {
        var pages = Math.ceil(total / perPage);
        var container = document.getElementById('va-pagination');
        container.innerHTML = '';
        if (pages <= 1) return;
        for (var i = 1; i <= pages; i++) {
            (function(p) {
                var btn = document.createElement('button');
                btn.textContent = p;
                btn.style.cssText = 'width:32px;height:32px;border-radius:6px;border:1px solid '+(p===currentPage?'#4f46e5':'#e2e8f0')+';background:'+(p===currentPage?'#4f46e5':'#fff')+';color:'+(p===currentPage?'#fff':'#475569')+';font-size:13px;cursor:pointer;';
                btn.addEventListener('click', function() { renderPage(p); });
                container.appendChild(btn);
            })(i);
        }
    }

    function applyFilters() {
        var search = (document.getElementById('va-appt-search').value || '').toLowerCase();
        var status = document.getElementById('va-status-filter').value;
        allRows.forEach(function(r) {
            var text = r.textContent.toLowerCase();
            var rowStatus = (r.querySelector('.va-badge') || {}).textContent || '';
            var show = (!search || text.indexOf(search) !== -1) && (!status || rowStatus.trim() === status);
            r.style.display = show ? '' : 'none';
        });
        renderPage(1);
    }

    function setupSearch() {
        document.getElementById('va-appt-search').addEventListener('input', applyFilters);
    }

    function setupStatusFilter() {
        document.getElementById('va-status-filter').addEventListener('change', applyFilters);
    }

    function setupSelectAll() {
        document.getElementById('va-select-all').addEventListener('change', function() {
            document.querySelectorAll('#va-appointments-tbody .va-row-check').forEach(function(cb) {
                cb.checked = this.checked;
            }, this);
        });
    }

    function setupEditBtns() {
        document.getElementById('va-appointments-tbody').addEventListener('click', function(e) {
            var btn = e.target.closest('.va-edit-appt-btn');
            if (!btn) return;
            document.getElementById('va-edit-uid').value   = btn.dataset.uid;
            document.getElementById('va-edit-status').value = btn.dataset.status;
            document.getElementById('va-edit-date').value   = btn.dataset.date;
            document.getElementById('va-edit-time').value   = btn.dataset.time;
            document.getElementById('va-edit-appt-modal').style.display = 'flex';
        });

        document.getElementById('va-save-appt-btn').addEventListener('click', function() {
            var btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i>Saving...';
            var data = new FormData();
            data.append('action',   'dior_doc_appointment_update');
            data.append('nonce',    nonce);
            data.append('appt_uid', document.getElementById('va-edit-uid').value);
            data.append('status',   document.getElementById('va-edit-status').value);
            data.append('date',     document.getElementById('va-edit-date').value);
            data.append('time',     document.getElementById('va-edit-time').value);
            fetch(ajaxUrl, {method:'POST',body:data})
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        document.getElementById('va-edit-appt-modal').style.display = 'none';
                        location.reload();
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Failed to update.');
                    }
                })
                .catch(function() { alert('Network error.'); })
                .finally(function() {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check" style="margin-right:6px;"></i>Save Changes';
                });
        });
    }

    function setupDeleteBtns() {
        document.getElementById('va-appointments-tbody').addEventListener('click', function(e) {
            var btn = e.target.closest('.va-delete-appt-btn');
            if (!btn) return;
            if (!confirm('Delete appointment for "' + btn.dataset.name + '"? This cannot be undone.')) return;
            var data = new FormData();
            data.append('action',   'dior_doc_appointment_delete');
            data.append('nonce',    nonce);
            data.append('appt_uid', btn.dataset.uid);
            fetch(ajaxUrl, {method:'POST',body:data})
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        var row = btn.closest('tr');
                        allRows = allRows.filter(function(r) { return r !== row; });
                        row.remove();
                        renderPage(currentPage);
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Failed to delete.');
                    }
                });
        });
    }

    function setupViewPatientBtns() {
        document.getElementById('va-appointments-tbody').addEventListener('click', function(e) {
            var btn = e.target.closest('.va-view-patient-btn');
            if (!btn) return;
            var pid = btn.dataset.patientId;
            // Store selected patient ID globally then switch to patient profile tab
            window.diorSelectedPatientId = pid;
            if (typeof window.diorSwitchTab === 'function') {
                window.diorSwitchTab('doc-patients-profile');
            } else if (typeof switchTab === 'function') {
                switchTab('doc-patients-profile');
            }
            // Trigger patient load
            if (typeof window.diorLoadPatientProfile === 'function') {
                window.diorLoadPatientProfile(pid);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initViewAppt);
    } else {
        initViewAppt();
    }
})();
</script>
