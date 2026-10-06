<?php
/**
 * Patient Review Tab — Dynamic, DB-connected
 * Actions: Edit, Delete, View (popup)
 */
global $wpdb;
$nonce     = wp_create_nonce('dior_doctor_nonce');
$ajax_url  = admin_url('admin-ajax.php');
$doctor_id = get_current_user_id();

// Keep doctor reviews scoped to the signed-in doctor; administrators can review all submissions.
$reviews_sql = "SELECT r.*, p.first_name, p.last_name, p.avatar_url
    FROM {$wpdb->prefix}dior_reviews r
    LEFT JOIN {$wpdb->prefix}dior_patients p ON p.user_id = r.patient_id";
if (!current_user_can('manage_options')) {
    $review_doctor_ids = Dior_Doctor_Resolver::get_accessible_doctor_ids($doctor_id);
    $review_placeholders = implode(',', array_fill(0, count($review_doctor_ids), '%d'));
    $reviews_sql .= $wpdb->prepare(" WHERE r.doctor_id IN ($review_placeholders)", ...$review_doctor_ids);
}
$reviews = $wpdb->get_results($reviews_sql . ' ORDER BY r.created_at DESC LIMIT 200', ARRAY_A);

// Fetch patients for dropdown in future
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

<section class="dior-tab-panel documents-tab-panel" id="tab-doc-patient-review" style="display:none;">
    <!-- Breadcrumb -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;">
        <div><h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Patient Reviews</h4></div>
        <div>
            <ul class="va-breadcrumb-list" style="display:flex;gap:8px;list-style:none;padding:0;margin:0;align-items:center;">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span style="color:#1E293B;font-weight:700;font-size:14px;">Patient Reviews</span></li>
            </ul>
        </div>
    </div>

    <div class="view-appointment-card">
        <!-- Header -->
        <div class="va-header-container">
            <div class="va-title-box">
                <h2>All Reviews</h2>
                <div class="va-title-line"></div>
            </div>
            <div class="va-actions-wrapper">
                <div class="va-search-box">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="rv-search" placeholder="Search reviews...">
                </div>
                <div class="va-actions-group">
                    <button class="va-icon-btn va-btn-info" id="rv-refresh-btn" title="Refresh">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="va-table-wrapper">
            <table class="va-table" id="rv-table">
                <thead>
                    <tr>
                        <th style="width:50px;" class="text-center"><input type="checkbox" class="va-checkbox" id="rv-select-all"></th>
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
                        $rid    = (int)($r['id'] ?? 0);
                        $pid    = (int)($r['patient_id'] ?? 0);
                        $fname  = esc_html(trim(($r['first_name']??'').' '.($r['last_name']??''))) ?: 'Unknown';
                        $avatar = esc_url($r['avatar_url'] ?? '');
                        $subject = esc_html($r['subject'] ?? '');
                        $rating = (int)($r['rating'] ?? 5);
                        $comment= esc_html($r['comment'] ?? '');
                        $status = esc_html($r['status'] ?? 'Published');
                        $date_f = !empty($r['created_at']) ? date('M d, Y', strtotime($r['created_at'])) : '—';
                        $badge  = ($status === 'Published') ? 'va-badge-completed' : 'va-badge-pending';
                        $enc    = esc_attr(wp_json_encode(['id'=>$rid,'patient_id'=>$pid,'subject'=>$r['subject']??'','rating'=>$rating,'comment'=>$r['comment']??'','status'=>$status]));
                    ?>
                    <tr data-id="<?php echo $rid; ?>">
                        <td class="text-center"><input type="checkbox" class="va-checkbox rv-row-check"></td>
                        <td>
                            <div class="va-user-profile">
                                <img src="<?php echo $avatar ?: 'https://ui-avatars.com/api/?name='.urlencode($fname).'&background=random'; ?>"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($fname); ?>&background=random'"
                                     class="va-user-avatar" alt="<?php echo $fname; ?>">
                                <span class="va-user-name"><?php echo $fname; ?></span>
                            </div>
                        </td>
                        <td><?php echo $subject !== '' ? $subject : 'Patient Review'; ?></td>
                        <td>
                            <div style="color:#F59E0B;font-size:14px;display:flex;gap:2px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-<?php echo $i <= $rating ? 'solid' : 'regular'; ?> fa-star" <?php if ($i > $rating) echo 'style="color:#CBD5E1;"'; ?>></i>
                                <?php endfor; ?>
                            </div>
                        </td>
                        <td><i class="far fa-calendar va-icon-blue"></i> <?php echo $date_f; ?></td>
                        <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo $comment; ?>"><?php echo $comment; ?></td>
                        <td><span class="va-badge <?php echo $badge; ?>"><?php echo $status; ?></span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm" style="background:transparent; border:none;" title="View Review"
                                        data-enc="<?php echo $enc; ?>" data-name="<?php echo esc_attr($fname); ?>"
                                        onclick="rvViewReview(this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3b82f6" class="bi bi-eye" viewBox="0 0 16 16" style="background:transparent;"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                                </button>
                                <button class="va-action-btn-sm va-btn-edit rv-edit-btn"
                                        data-enc="<?php echo $enc; ?>" title="Edit Review">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg>
                                </button>
                                <button class="va-action-btn-sm va-btn-delete rv-delete-btn"
                                        data-id="<?php echo $rid; ?>" title="Delete Review">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">No reviews found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</section>

<!-- View Review Modal -->
<div id="rv-view-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:440px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h3 style="margin:0;font-size:18px;font-weight:700;color:#1e293b;"><i class="fas fa-star" style="color:#f59e0b;margin-right:8px;"></i>Review Details</h3>
            <button onclick="document.getElementById('rv-view-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#94a3b8;">&times;</button>
        </div>
        <div id="rv-view-body"></div>
    </div>
</div>

<!-- Edit Review Modal -->
<div id="rv-edit-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:440px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h3 style="margin:0;font-size:18px;font-weight:700;color:#1e293b;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg>Edit Review</h3>
            <button onclick="document.getElementById('rv-edit-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#94a3b8;">&times;</button>
        </div>
        <input type="hidden" id="rv-edit-id">
        <input type="hidden" id="rv-edit-pid">
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:8px;">Rating</label>
                <div id="rv-star-editor" style="display:flex;gap:6px;font-size:24px;cursor:pointer;color:#CBD5E1;">
                    <i class="fas fa-star" data-val="1"></i>
                    <i class="fas fa-star" data-val="2"></i>
                    <i class="fas fa-star" data-val="3"></i>
                    <i class="fas fa-star" data-val="4"></i>
                    <i class="fas fa-star" data-val="5"></i>
                </div>
                <input type="hidden" id="rv-rating-val" value="5">
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Comment</label>
                <textarea id="rv-comment" rows="4" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;resize:vertical;"></textarea>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Status</label>
                <select id="rv-status" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;">
                    <option value="Published">Published</option>
                    <option value="Pending">Pending</option>
                </select>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:24px;justify-content:flex-end;">
            <button onclick="document.getElementById('rv-edit-modal').style.display='none'" style="padding:10px 20px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;color:#64748b;font-size:14px;cursor:pointer;">Cancel</button>
            <button id="rv-save-btn" style="padding:10px 24px;border:none;border-radius:8px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;font-size:14px;font-weight:600;cursor:pointer;">
                <i class="fas fa-check" style="margin-right:6px;"></i>Save Review
            </button>
        </div>
    </div>
</div>

<script>
window.rvViewReview = function(btn) {
    var enc = JSON.parse(btn.dataset.enc);
    var name = btn.dataset.name;
    var stars = '';
    for (var i=1; i<=5; i++) {
        stars += '<i class="fa-'+(i<=enc.rating?'solid':'regular')+' fa-star" style="color:'+(i<=enc.rating?'#F59E0B':'#CBD5E1')+'"></i>';
    }
    document.getElementById('rv-view-body').innerHTML =
        '<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">' +
        '<img src="https://ui-avatars.com/api/?name='+encodeURIComponent(name)+'&background=random" style="width:48px;height:48px;border-radius:50%;" alt="">' +
        '<div><div style="font-weight:700;color:#1e293b;">'+name+'</div><div style="font-size:13px;color:#64748b;">'+enc.status+'</div></div></div>' +
        '<div style="margin-bottom:16px;font-size:22px;display:flex;gap:4px;">'+stars+'</div>' +
        (enc.subject ? '<div style="margin-bottom:8px;font-weight:700;color:#1e293b;">'+enc.subject+'</div>' : '') +
        '<div style="background:#f8fafc;border-radius:8px;padding:16px;color:#475569;font-size:14px;line-height:1.6;">'+(enc.comment||'No comment provided.')+'</div>';
    document.getElementById('rv-view-modal').style.display = 'flex';
};

(function() {
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var nonce   = '<?php echo esc_js($nonce); ?>';
    var allRows;

    function init() {
        allRows = Array.from(document.querySelectorAll('#rv-tbody tr[data-id]'));
        document.getElementById('rv-select-all').addEventListener('change', function(){
            document.querySelectorAll('.rv-row-check').forEach(function(cb){cb.checked=this.checked;},this);
        });
        document.getElementById('rv-refresh-btn').addEventListener('click', function(){location.reload();});
        document.getElementById('rv-tbody').addEventListener('click', function(e) {
            var editBtn = e.target.closest('.rv-edit-btn');
            var delBtn  = e.target.closest('.rv-delete-btn');
            if (editBtn) openEdit(editBtn);
            if (delBtn)  deleteReview(delBtn);
        });
        setupStarEditor();
        document.getElementById('rv-save-btn').addEventListener('click', saveReview);
    }

    function setupStarEditor() {
        var stars = document.querySelectorAll('#rv-star-editor i');
        stars.forEach(function(s) {
            s.addEventListener('click', function() {
                var val = parseInt(this.dataset.val);
                document.getElementById('rv-rating-val').value = val;
                stars.forEach(function(st) {
                    st.style.color = parseInt(st.dataset.val) <= val ? '#F59E0B' : '#CBD5E1';
                });
            });
        });
    }

    function openEdit(btn) {
        var enc = JSON.parse(btn.dataset.enc);
        document.getElementById('rv-edit-id').value  = enc.id;
        document.getElementById('rv-edit-pid').value = enc.patient_id;
        document.getElementById('rv-rating-val').value = enc.rating;
        document.getElementById('rv-comment').value  = enc.comment;
        document.getElementById('rv-status').value   = enc.status;
        var stars = document.querySelectorAll('#rv-star-editor i');
        stars.forEach(function(s) {
            s.style.color = parseInt(s.dataset.val) <= enc.rating ? '#F59E0B' : '#CBD5E1';
        });
        document.getElementById('rv-edit-modal').style.display = 'flex';
    }

    function saveReview() {
        var btn = document.getElementById('rv-save-btn');
        btn.disabled = true;
        var data = new FormData();
        data.append('action',     'dior_doc_review_save');
        data.append('nonce',      nonce);
        data.append('id',         document.getElementById('rv-edit-id').value);
        data.append('patient_id', document.getElementById('rv-edit-pid').value);
        data.append('rating',     document.getElementById('rv-rating-val').value);
        data.append('comment',    document.getElementById('rv-comment').value);
        data.append('status',     document.getElementById('rv-status').value);
        fetch(ajaxUrl, {method:'POST',body:data})
            .then(function(r){return r.json();})
            .then(function(res) {
                if (res.success) { document.getElementById('rv-edit-modal').style.display='none'; location.reload(); }
                else { alert(res.data && res.data.message ? res.data.message : 'Save failed.'); }
            }).finally(function(){btn.disabled=false;});
    }

    function deleteReview(btn) {
        if (!confirm('Delete this review? This cannot be undone.')) return;
        var data = new FormData();
        data.append('action', 'dior_doc_review_delete');
        data.append('nonce',  nonce);
        data.append('id',     btn.dataset.id);
        fetch(ajaxUrl, {method:'POST',body:data})
            .then(function(r){return r.json();})
            .then(function(res) {
                if (res.success) {
                    var row = btn.closest('tr');
                    allRows = allRows.filter(function(r){return r!==row;}); row.remove();
                } else { alert(res.data && res.data.message ? res.data.message : 'Delete failed.'); }
            });
    }
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
</script>
