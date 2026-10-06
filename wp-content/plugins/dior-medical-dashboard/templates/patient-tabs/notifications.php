<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-notifications">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Notifications Center</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Notifications Center</span></li>
            </ul>
        </div>
    </div>

    <?php
    // Filter out demo/static notifications - only show real database notifications
    $patient_notif_rows = [];
    if (is_array($notifications ?? [])) {
        foreach ($notifications as $n) {
            // Skip notifications with DEMO prefix (from static design data)
            if (!empty($n['id']) && strpos($n['id'], 'DEMO-') === 0) {
                continue;
            }
            $patient_notif_rows[] = $n;
        }
    }
    $patient_unread = 0;
    foreach ($patient_notif_rows as $pn) { if (empty($pn['is_read'])) { $patient_unread++; } }
    ?>
    <div class="dior-patient-alerts-stats">
        <div class="dior-patient-alert-stat"><div class="dior-patient-alert-stat-icon"><i class="fa-solid fa-bell"></i></div><div><span>Total Notifications</span><strong><?php echo count($patient_notif_rows); ?></strong></div></div>
        <div class="dior-patient-alert-stat"><div class="dior-patient-alert-stat-icon unread"><i class="fa-solid fa-circle-exclamation"></i></div><div><span>Unread Alerts</span><strong><?php echo $patient_unread; ?></strong></div></div>
        <div class="dior-patient-alert-stat"><div class="dior-patient-alert-stat-icon success"><i class="fa-solid fa-check-double"></i></div><div><span>Read Notifications</span><strong><?php echo max(0, count($patient_notif_rows) - $patient_unread); ?></strong></div></div>
        <div class="dior-patient-alert-stat"><div class="dior-patient-alert-stat-icon info"><i class="fa-solid fa-shield-heart"></i></div><div><span>Portal Status</span><strong>Active</strong></div></div>
    </div>

    <div class="docs-card">
                <div class="docs-header-container">
                    <div class="docs-title-box">
                        <h2>Notifications Center</h2>
                        <div class="docs-title-line"></div>
                    </div>
                    <div class="docs-actions-wrapper">
                        <div class="docs-search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="dior-notif-search-input" placeholder="Search records..." aria-label="Search box" onkeyup="diorFilterNotif()">
                        </div>
                        <div class="docs-actions-group">
                            <button type="button" aria-label="Add new record" class="docs-icon-btn docs-btn-primary dior-ic-54410f9b78">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                            <button type="button" aria-label="Export to CSV" class="docs-icon-btn docs-btn-success" title="Export to CSV" onclick="diorDownloadNotifCSV()">
                                <i class="fa-solid fa-file-arrow-down"></i>
                            </button>
                            <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="docs-table-wrapper">
                    <table class="docs-table" id="dior-notif-table">
                        <thead>
                            <tr>
                                <th class="dior-ic-3fa4d8d717">
                                    <input type="checkbox" class="dior-ic-52ff4d551f">
                                </th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dior-notif-tbody">
                            <?php
                            foreach ($patient_notif_rows as $n):
                                $n_title = $n['title'] ?? 'Notification';
                                $n_message = $n['message'] ?? '';
                                $n_type = $n['type'] ?? 'Info';
                                $n_date = !empty($n['created_at']) ? wp_date('M j, Y', strtotime($n['created_at'])) : (!empty($n['timestamp']) ? wp_date('M j, Y', (int)$n['timestamp']) : '—');
                                $n_time = dior_format_notification_time($n);
                                $n_status = !empty($n['is_read']) ? 'Read' : 'Unread';
                                $n_type_class = $n_status === 'Unread' ? 'F59E0B' : '3B82F6';
                                $n_type_bg = $n_status === 'Unread' ? 'FEF3C7' : 'DBEAFE';
                                $n_status_color = $n_status === 'Unread' ? 'EF4444' : '10B981';
                                $n_status_bg = $n_status === 'Unread' ? 'FEE2E2' : 'D1FAE5';
                            ?>
                            <tr data-notif-id="<?php echo esc_attr($n['id'] ?? ''); ?>">
                                <td><input type="checkbox" class="dior-ic-52ff4d551f"></td>
                                <td><span class="cell-text dior-ic-794116ec9b"><?php echo esc_html($n_title); ?></span></td>
                                <td><span class="cell-text dior-ic-794116ec9b"><?php echo esc_html($n_message); ?></span></td>
                                <td><div class="cell-content"><div class="dior-dyn-status dior-dyn-bg-<?php echo esc_attr($n_type_bg); ?> dior-dyn-color-<?php echo esc_attr($n_type_class); ?>"><?php echo esc_html(ucfirst($n_type)); ?></div></div></td>
                                <td><div class="cell-content cell-icon-text"><i class="fa-regular fa-calendar cell-icon dior-ic-d53ea48df0"></i><span class="cell-text"><?php echo esc_html($n_date); ?></span></div></td>
                                <td><span class="cell-text dior-ic-794116ec9b"><?php echo esc_html($n_time); ?></span></td>
                                <td><div class="cell-content"><div class="dior-dyn-status dior-dyn-bg-<?php echo esc_attr($n_status_bg); ?> dior-dyn-color-<?php echo esc_attr($n_status_color); ?>"><?php echo esc_html($n_status); ?></div></div></td>
                                <td><div class="cell-actions">
                                    <?php if (empty($n['is_read']) && !empty($n['id'])): ?><button type="button" class="action-icon-btn edit-btn dior-mark-notification-read" title="Mark as Read" data-notif-id="<?php echo esc_attr($n['id']); ?>"><i class="fa-solid fa-check"></i></button><?php endif; ?>
                                    <button type="button" class="action-icon-btn delete-btn" title="Open"><i class="fa-regular fa-eye"></i></button>
                                </div></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($patient_notif_rows)): ?>
                                <tr><td colspan="8" class="dior-empty-state">No notifications yet.</td></tr>
                            <?php endif; ?>
</tbody>
                    </table>
                </div>
                <div class="docs-pagination-container">
                    <span class="docs-showing-text" id="dior-notif-page-count">0 selected / <?php echo count($patient_notif_rows); ?> total</span>
                    <div id="dior-notif-pagination"></div>
                </div>
    </div>
    
</section>
