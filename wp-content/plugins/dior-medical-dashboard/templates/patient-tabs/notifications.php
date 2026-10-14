<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-notifications">
    <style>
    /* Notifications Center Table Line-Wrap & No Horizontal Scroll */
    #tab-notifications .master-table-card .table-content {
        width: 100% !important;
        overflow-x: auto !important;
    }
    #tab-notifications table#dior-notif-table {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        table-layout: fixed !important;
    }
    #tab-notifications table#dior-notif-table th:nth-child(1),
    #tab-notifications table#dior-notif-table td:nth-child(1) {
        width: 44px !important;
        max-width: 44px !important;
        text-align: center !important;
        white-space: nowrap !important;
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    #tab-notifications table#dior-notif-table th:nth-child(2),
    #tab-notifications table#dior-notif-table td:nth-child(2),
    #tab-notifications .notif-title-col {
        width: 20% !important;
        white-space: normal !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
    }
    #tab-notifications table#dior-notif-table th:nth-child(3),
    #tab-notifications table#dior-notif-table td:nth-child(3),
    #tab-notifications .notif-message-col {
        width: 38% !important;
        white-space: normal !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
    }
    #tab-notifications table#dior-notif-table td:nth-child(3) .cell-text,
    #tab-notifications .notif-message-text {
        white-space: normal !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
        display: block !important;
        line-height: 1.55 !important;
        font-size: 13.5px !important;
        color: #334155 !important;
    }
    #tab-notifications table#dior-notif-table td:nth-child(2) .cell-text,
    #tab-notifications .notif-title-text {
        white-space: normal !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
        display: block !important;
        line-height: 1.4 !important;
        font-weight: 600 !important;
        color: #1E293B !important;
    }
    #tab-notifications table#dior-notif-table th:nth-child(4),
    #tab-notifications table#dior-notif-table td:nth-child(4) {
        width: 78px !important;
        text-align: center !important;
        white-space: nowrap !important;
    }
    #tab-notifications table#dior-notif-table th:nth-child(5),
    #tab-notifications table#dior-notif-table td:nth-child(5) {
        width: 115px !important;
        white-space: nowrap !important;
    }
    #tab-notifications table#dior-notif-table th:nth-child(6),
    #tab-notifications table#dior-notif-table td:nth-child(6) {
        width: 90px !important;
        white-space: nowrap !important;
    }
    #tab-notifications table#dior-notif-table th:nth-child(7),
    #tab-notifications table#dior-notif-table td:nth-child(7) {
        width: 85px !important;
        text-align: center !important;
        white-space: nowrap !important;
    }
    #tab-notifications table#dior-notif-table th:nth-child(8),
    #tab-notifications table#dior-notif-table td:nth-child(8) {
        width: 85px !important;
        text-align: center !important;
        white-space: nowrap !important;
    }
    @media (max-width: 991px) {
        #tab-notifications table#dior-notif-table {
            min-width: 760px !important;
            table-layout: auto !important;
        }
    }
    </style>
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
                            <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" id="dior-notif-search-input" class="form-control border-start-0 ps-0" placeholder="Search records..." aria-label="Search box" onkeyup="diorFilterNotif()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Delete selected items" class="action-btn action-btn-danger btn" title="Delete Selected" onclick="diorBulkDeleteNotifications(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button type="button" aria-label="Mark all as read" class="action-btn action-btn-primary dior-mark-all-read btn" title="Mark All as Read" onclick="diorMarkAllNotificationsRead()">
                                    <i class="fas fa-check-double"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success btn" title="Export to CSV" onclick="diorDownloadNotifCSV()">
                                    <i class="fas fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data" class="action-btn action-btn-info btn" title="Refresh Notifications" onclick="diorRefreshNotifications()">
                                    <i class="fas fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="master-modern-table table table-hover" id="dior-notif-table">
                        <thead>
                            <tr>
                                <th class="dior-ic-3fa4d8d717">
                                    <input type="checkbox" id="dior-notif-select-all" class="dior-ic-52ff4d551f form-check-input" onchange="diorToggleSelectAllNotifs(this)">
                                </th>
                                <th onclick="diorSortNotifTable(1)" style="cursor:pointer;" title="Sort by Title">TITLE <i class="fas fa-sort"></i></th>
                                <th onclick="diorSortNotifTable(2)" style="cursor:pointer;" title="Sort by Message">MESSAGE <i class="fas fa-sort"></i></th>
                                <th onclick="diorSortNotifTable(3)" style="cursor:pointer;" title="Sort by Type">TYPE <i class="fas fa-sort"></i></th>
                                <th onclick="diorSortNotifTable(4)" style="cursor:pointer;" title="Sort by Date">DATE <i class="fas fa-sort"></i></th>
                                <th onclick="diorSortNotifTable(5)" style="cursor:pointer;" title="Sort by Time">TIME <i class="fas fa-sort"></i></th>
                                <th onclick="diorSortNotifTable(6)" style="cursor:pointer;" title="Sort by Status">STATUS <i class="fas fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-notif-tbody">
                            <?php
                            $user_id = get_current_user_id();
                            $patient_notif_rows = Dior_Patient_Portal_Data::get_patient_notifications($user_id);
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
                                <td><input type="checkbox" class="dior-ic-52ff4d551f dior-notif-row-cb form-check-input" onchange="diorUpdateNotifSelectionCount()"></td>
                                <td class="notif-title-col"><span class="cell-text notif-title-text dior-ic-794116ec9b"><?php echo esc_html($n_title); ?></span></td>
                                <td class="notif-message-col"><span class="cell-text notif-message-text dior-ic-794116ec9b"><?php echo esc_html($n_message); ?></span></td>
                                <td><div class="cell-content"><div class="dior-dyn-status dior-dyn-bg-<?php echo esc_attr($n_type_bg); ?> dior-dyn-color-<?php echo esc_attr($n_type_class); ?>"><?php echo esc_html(ucfirst($n_type)); ?></div></div></td>
                                <td><div class="cell-content cell-icon-text"><i class="far fa-calendar cell-icon dior-ic-d53ea48df0"></i><span class="cell-text"><?php echo esc_html($n_date); ?></span></div></td>
                                <td><span class="cell-text dior-ic-794116ec9b"><?php echo esc_html($n_time); ?></span></td>
                                <td><div class="cell-content"><div class="dior-dyn-status dior-dyn-bg-<?php echo esc_attr($n_status_bg); ?> dior-dyn-color-<?php echo esc_attr($n_status_color); ?>"><?php echo esc_html($n_status); ?></div></div></td>
                                <td><div class="cell-actions">
                                    <button type="button" class="action-icon-btn view-btn dior-view-notif-btn btn" title="View Details" data-notif-id="<?php echo esc_attr($n['id'] ?? ''); ?>" data-notif-title="<?php echo esc_attr($n_title); ?>" data-notif-message="<?php echo esc_attr($n_message); ?>" data-notif-date="<?php echo esc_attr($n_date . ' ' . $n_time); ?>" data-notif-type="<?php echo esc_attr(ucfirst($n_type)); ?>" data-notif-action="<?php echo esc_attr($n['action_url'] ?? ''); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3b82f6" class="bi bi-eye" viewBox="0 0 16 16" style="background:transparent;"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg></button>
                                    <?php if (empty($n['is_read']) && !empty($n['id'])): ?>
                                        <button type="button" class="action-icon-btn edit-btn dior-mark-notification-read btn" title="Mark as Read" data-notif-id="<?php echo esc_attr($n['id']); ?>"><i class="fas fa-check"></i></button>
                                    <?php else: ?>
                                        <div style="width: 34px; min-width: 34px;"></div>
                                    <?php endif; ?>
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
