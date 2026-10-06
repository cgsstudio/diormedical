<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-notifications">
    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">Notifications Center</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fas fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-notif-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterNotif()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary dior-ic-54410f9b78">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadNotifCSV()">
                                    <i class="fas fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                    <i class="fas fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="master-modern-table" id="dior-notif-table">
                        <thead>
                            <tr>
                                <th class="dior-ic-3fa4d8d717">
                                    <input type="checkbox" class="dior-ic-52ff4d551f">
                                </th>
                                <th>TITLE <i class="fas fa-sort"></i></th>
                                <th>MESSAGE <i class="fas fa-sort"></i></th>
                                <th>TYPE <i class="fas fa-sort"></i></th>
                                <th>DATE <i class="fas fa-sort"></i></th>
                                <th>TIME <i class="fas fa-sort"></i></th>
                                <th>STATUS <i class="fas fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-notif-tbody">
                            <?php
                            $patient_notif_rows = is_array($notifications ?? []) ? $notifications : [];
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
                                <td><div class="cell-content cell-icon-text"><i class="far fa-calendar cell-icon dior-ic-d53ea48df0"></i><span class="cell-text"><?php echo esc_html($n_date); ?></span></div></td>
                                <td><span class="cell-text dior-ic-794116ec9b"><?php echo esc_html($n_time); ?></span></td>
                                <td><div class="cell-content"><div class="dior-dyn-status dior-dyn-bg-<?php echo esc_attr($n_status_bg); ?> dior-dyn-color-<?php echo esc_attr($n_status_color); ?>"><?php echo esc_html($n_status); ?></div></div></td>
                                <td><div class="cell-actions">
                                    <?php if (empty($n['is_read']) && !empty($n['id'])): ?><button type="button" class="action-icon-btn edit-btn dior-mark-notification-read" title="Mark as Read" data-notif-id="<?php echo esc_attr($n['id']); ?>"><i class="fas fa-check"></i></button><?php endif; ?>
                                    <button type="button" class="action-icon-btn delete-btn" title="Open"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3b82f6" class="bi bi-eye" viewBox="0 0 16 16" style="background:transparent;"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg></button>
                                </div></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($patient_notif_rows)): ?>
                                <tr><td colspan="8" class="dior-empty-state">No notifications yet.</td></tr>
                            <?php endif; ?>
</tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-notif-page-count">0 selected / <?php echo count($patient_notif_rows); ?> total</span>
                    
                    <div class="master-pagination" id="dior-notif-pagination">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</section>
