<<<<<<< HEAD
<?php
/**
 * Consultation Notes Tab — Dynamic, DB-connected
 * Actions: Add (popup with patient select), Edit, Delete
 */
global $wpdb;
$nonce     = wp_create_nonce('dior_doctor_nonce');
$ajax_url  = admin_url('admin-ajax.php');
$doctor_id = get_current_user_id();
$doc_name  = wp_get_current_user()->display_name;

// Fetch consultation notes (encounters)
$notes = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT e.*, 
                p.first_name, p.last_name, p.avatar_url, p.patient_uid, p.user_id as patient_user_id
         FROM {$wpdb->prefix}dior_encounters e
         LEFT JOIN {$wpdb->prefix}dior_patients p ON p.user_id = e.patient_id
         WHERE e.is_deleted = 0
         ORDER BY e.created_at DESC
         LIMIT 200"
    ),
    ARRAY_A
);

// Fetch patients for dropdown
$patients = $wpdb->get_results(
    "SELECT user_id, first_name, last_name, patient_uid FROM {$wpdb->prefix}dior_patients WHERE status <> 'deleted' ORDER BY first_name ASC LIMIT 300",
    ARRAY_A
);
if (empty($patients)) {
    $wp_users = get_users(['role__in' => ['patient','subscriber','customer'], 'number' => 100]);
    $patients = [];
    foreach ($wp_users as $u) {
        $patients[] = [
            'user_id'    => $u->ID,
            'first_name' => $u->first_name ?: $u->display_name,
            'last_name'  => $u->last_name,
            'patient_uid'=> get_user_meta($u->ID, 'patient_id', true) ?: 'DM-'.(10000+$u->ID),
        ];
    }
}
?>
<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/view-appointment.css?v=' . time()); ?>">

<section class="dior-tab-panel" id="tab-doc-consultation-notes" style="display:none;">
    <!-- Breadcrumb -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 5px;">
        <div><h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Consultation Notes</h4></div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house"></i></a></li>
                <li>/</li>
                <li class="active"><span>Consultation Notes</span></li>
            </ul>
        </div>
    </div>

    <div class="view-appointment-card">
        <!-- Header -->
        <div class="va-header-container">
            <div class="va-title-box">
                <h2>Consultation Notes</h2>
                <div class="va-title-line"></div>
            </div>
            <div class="va-actions-box" style="display:flex;align-items:center;gap:10px;">
                <div class="va-search-container" style="position:relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:13px;"></i>
                    <input type="text" id="cn-search" class="dior-search-input" placeholder="Search records..." style="padding:8px 12px 8px 36px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;width:220px;">
                </div>
                <button class="va-icon-btn va-btn-primary" id="cn-add-btn" title="Add New Consultation Note">
                    <i class="fa-solid fa-plus"></i>
                </button>
                <button class="va-icon-btn va-btn-info" id="cn-refresh-btn" title="Refresh">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="va-table-wrapper">
            <table class="va-table" id="cn-table">
                <thead>
                    <tr>
                        <th style="width:50px;text-align:center;"><input type="checkbox" class="va-checkbox" id="cn-select-all"></th>
                        <th>ID</th>
                        <th>PATIENT NAME <i class="fa-solid fa-sort" style="color:#ccc;"></i></th>
                        <th>DATE</th>
                        <th>CHIEF COMPLAINT</th>
                        <th>DIAGNOSIS</th>
                        <th>DOCTOR</th>
                        <th>STATUS <i class="fa-solid fa-sort" style="color:#ccc;"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="cn-tbody">
                <?php if (!empty($notes)): ?>
                    <?php foreach ($notes as $n):
                        $uid    = esc_html($n['encounter_uid'] ?? '');
                        $nid    = (int)($n['id'] ?? 0);
                        $pid    = (int)($n['patient_id'] ?? 0);
                        $fname  = esc_html(trim(($n['first_name']??'') . ' ' . ($n['last_name']??''))) ?: 'Unknown';
                        $init   = strtoupper(substr($fname, 0, 1));
                        $date_f = !empty($n['created_at']) ? date('M d, Y', strtotime($n['created_at'])) : '—';
                        $complaint = esc_html($n['subjective'] ?? '—');
                        $diagnosis = esc_html($n['diagnosis'] ?? '—');
                        $status = esc_html($n['status'] ?? 'finalized');
                        $badge = ($status === 'finalized') ? 'va-badge-completed' : (($status === 'draft') ? 'va-badge-pending' : 'va-badge-confirmed');
                        $note_enc = esc_attr(json_encode([
                            'id'=> $nid,'uid'=>$uid,'patient_id'=>$pid,'note'=>$n['subjective']??'',
                            'assessment'=>$n['assessment']??'','plan'=>$n['plan']??'','diagnosis'=>$n['diagnosis']??'',
                            'follow_up'=>$n['follow_up']??''
                        ]));
                    ?>
                    <tr data-id="<?php echo $nid; ?>" data-pid="<?php echo $pid; ?>">
                        <td style="text-align:center;"><input type="checkbox" class="va-checkbox cn-row-check"></td>
                        <td style="font-size:12px;color:#64748b;"><?php echo substr($uid, 0, 10); ?>…</td>
                        <td>
                            <div class="va-user-profile">
                                <div style="width:36px;height:36px;border-radius:50%;background:#E0E7FF;display:flex;align-items:center;justify-content:center;font-weight:700;color:#4F46E5;font-size:14px;flex-shrink:0;"><?php echo $init; ?></div>
                                <span class="va-user-name"><?php echo $fname; ?></span>
                            </div>
                        </td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> <?php echo $date_f; ?></td>
                        <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo $complaint; ?>"><?php echo $complaint; ?></td>
                        <td><?php echo $diagnosis; ?></td>
                        <td><?php echo esc_html($n['attestation_author'] ?? $doc_name); ?></td>
                        <td><span class="va-badge <?php echo $badge; ?>"><?php echo ucfirst($status); ?></span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm va-btn-edit cn-edit-btn"
                                        data-note="<?php echo $note_enc; ?>"
                                        title="Edit Note"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete cn-delete-btn"
                                        data-id="<?php echo $nid; ?>"
                                        data-uid="<?php echo esc_attr($uid); ?>"
                                        data-pid="<?php echo $pid; ?>"
                                        title="Delete Note"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="cn-empty-row"><td colspan="9" style="text-align:center;padding:40px;color:#94a3b8;">No consultation notes found. Click <strong>+</strong> to add one.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Add/Edit Consultation Note Modal -->
<div id="cn-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:560px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h3 id="cn-modal-title" style="margin:0;font-size:18px;font-weight:700;color:#1e293b;">
                <i class="fa-solid fa-notes-medical" style="color:#4f46e5;margin-right:8px;"></i>New Consultation Note
            </h3>
            <button id="cn-modal-close" style="background:none;border:none;font-size:20px;cursor:pointer;color:#94a3b8;">&times;</button>
        </div>
        <input type="hidden" id="cn-note-id" value="">
        <input type="hidden" id="cn-note-uid" value="">
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div id="cn-patient-select-wrap">
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Select Patient <span style="color:red;">*</span></label>
                <select id="cn-patient-select" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;">
                    <option value="">— Select Patient —</option>
                    <?php foreach ($patients as $p):
                        $pname = esc_html(trim(($p['first_name']??'').' '.($p['last_name']??'')));
                    ?>
                    <option value="<?php echo (int)$p['user_id']; ?>"><?php echo $pname; ?> (<?php echo esc_html($p['patient_uid']??''); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Chief Complaint / Notes</label>
                <textarea id="cn-note-text" rows="3" placeholder="Patient's chief complaint, symptoms..." style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;resize:vertical;"></textarea>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Diagnosis</label>
                <input type="text" id="cn-diagnosis" placeholder="e.g. Migraine, Type 2 Diabetes..." style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;">
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Assessment</label>
                <textarea id="cn-assessment" rows="2" placeholder="Clinical assessment..." style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;resize:vertical;"></textarea>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Plan</label>
                <textarea id="cn-plan" rows="2" placeholder="Treatment plan, medications..." style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;resize:vertical;"></textarea>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Follow-up</label>
                <input type="text" id="cn-followup" placeholder="e.g. 7-10 days, As needed..." style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;">
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:24px;justify-content:flex-end;">
            <button id="cn-modal-cancel" style="padding:10px 20px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;color:#64748b;font-size:14px;cursor:pointer;">Cancel</button>
            <button id="cn-save-btn" style="padding:10px 24px;border:none;border-radius:8px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;font-size:14px;font-weight:600;cursor:pointer;">
                <i class="fa-solid fa-check" style="margin-right:6px;"></i>Save Note
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var nonce   = '<?php echo esc_js($nonce); ?>';

    function init() {
        document.getElementById('cn-select-all').addEventListener('change', function() {
            document.querySelectorAll('.cn-row-check').forEach(function(cb){cb.checked=this.checked;},this);
        });
        document.getElementById('cn-add-btn').addEventListener('click', openAddModal);
        document.getElementById('cn-modal-close').addEventListener('click', closeModal);
        document.getElementById('cn-modal-cancel').addEventListener('click', closeModal);
        document.getElementById('cn-refresh-btn').addEventListener('click', function(){location.reload();});
        document.getElementById('cn-save-btn').addEventListener('click', saveNote);
        document.getElementById('cn-tbody').addEventListener('click', function(e) {
            var editBtn = e.target.closest('.cn-edit-btn');
            var delBtn  = e.target.closest('.cn-delete-btn');
            if (editBtn) openEditModal(editBtn);
            if (delBtn)  deleteNote(delBtn);
        });
    }

    function openAddModal() {
        document.getElementById('cn-modal-title').innerHTML = '<i class="fa-solid fa-notes-medical" style="color:#4f46e5;margin-right:8px;"></i>New Consultation Note';
        document.getElementById('cn-note-id').value  = '';
        document.getElementById('cn-note-uid').value = '';
        document.getElementById('cn-patient-select').value = '';
        document.getElementById('cn-patient-select-wrap').style.display = 'block';
        document.getElementById('cn-note-text').value  = '';
        document.getElementById('cn-diagnosis').value  = '';
        document.getElementById('cn-assessment').value = '';
        document.getElementById('cn-plan').value       = '';
        document.getElementById('cn-followup').value   = '';
        document.getElementById('cn-modal').style.display = 'flex';
    }

    function openEditModal(btn) {
        var note = JSON.parse(btn.dataset.note);
        document.getElementById('cn-modal-title').innerHTML = '<i class="fa-solid fa-pen" style="color:#4f46e5;margin-right:8px;"></i>Edit Consultation Note';
        document.getElementById('cn-note-id').value   = note.id;
        document.getElementById('cn-note-uid').value  = note.uid;
        document.getElementById('cn-patient-select').value = note.patient_id;
        document.getElementById('cn-patient-select-wrap').style.display = 'none';
        document.getElementById('cn-note-text').value   = note.note;
        document.getElementById('cn-diagnosis').value   = note.diagnosis;
        document.getElementById('cn-assessment').value  = note.assessment;
        document.getElementById('cn-plan').value        = note.plan;
        document.getElementById('cn-followup').value    = note.follow_up;
        document.getElementById('cn-modal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('cn-modal').style.display = 'none';
    }

    function saveNote() {
        var btn = document.getElementById('cn-save-btn');
        var pid = document.getElementById('cn-patient-select').value;
        var noteId = document.getElementById('cn-note-id').value;
        var noteUid = document.getElementById('cn-note-uid').value;
        if (!pid && !noteId) { alert('Please select a patient.'); return; }
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i>Saving...';
        var data = new FormData();
        data.append('action',     'dior_doc_save_consultation');
        data.append('nonce',      nonce);
        data.append('patient_id', pid || document.querySelector('#cn-tbody tr[data-id="'+noteId+'"]')?.dataset.pid || '');
        data.append('note_id',    noteUid);
        data.append('note',       document.getElementById('cn-note-text').value);
        data.append('diagnosis',  document.getElementById('cn-diagnosis').value);
        data.append('assessment', document.getElementById('cn-assessment').value);
        data.append('plan',       document.getElementById('cn-plan').value);
        data.append('follow_up',  document.getElementById('cn-followup').value);
        fetch(ajaxUrl, {method:'POST',body:data})
            .then(function(r){return r.json();})
            .then(function(res) {
                if (res.success) { closeModal(); location.reload(); }
                else { alert(res.data && res.data.message ? res.data.message : 'Save failed.'); }
            }).catch(function(){alert('Network error.');})
            .finally(function(){ btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-check" style="margin-right:6px;"></i>Save Note'; });
    }

    function deleteNote(btn) {
        if (!confirm('Delete this consultation note? This cannot be undone.')) return;
        var data = new FormData();
        data.append('action',    'dior_doc_delete_consultation');
        data.append('nonce',     nonce);
        data.append('note_id',   btn.dataset.uid);
        data.append('patient_id',btn.dataset.pid);
        fetch(ajaxUrl, {method:'POST',body:data})
            .then(function(r){return r.json();})
            .then(function(res) {
                if (res.success) {
                    var row = btn.closest('tr');
                    row.remove();
                    if (document.querySelectorAll('#cn-tbody tr[data-id]').length === 0) {
                        document.getElementById('cn-tbody').innerHTML = '<tr id="cn-empty-row"><td colspan="9" style="text-align:center;padding:40px;color:#94a3b8;">No consultation notes found. Click <strong>+</strong> to add one.</td></tr>';
                    }
                    if (window.diorRenderStaticTables) window.diorRenderStaticTables();
                } else { alert(res.data && res.data.message ? res.data.message : 'Delete failed.'); }
            });
    }

    if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',init);
    else init();
})();
</script>
=======
<?php defined('ABSPATH') || exit; ?>
<section class="dior-tab-panel dior-source-group" id="tab-doc-consultation-notes">
<div class="dior-content-pad">
<div class="dior-source-group-head"><div><div class="dior-source-kicker"><i class="fa-solid fa-notes-medical"></i> Doctor Workspace</div><h2>Consultations Notes</h2><p>Structured workspace using the same visual language as the Patient Dashboard.</p></div></div>
<div class="dior-source-subtabs" role="tablist"><button type="button" class="dior-source-subtab-btn active" data-source-target="consultation-notes"><i class="fa-solid fa-notes-medical"></i><span>Consultations Notes</span></button></div>
<div class="dior-source-subcontent"><?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/consultation-notes.php"; ?>
</div>
</div></section>
>>>>>>> fa0e02d91376b068a5cd18ba25d29811366c5101
