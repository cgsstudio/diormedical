<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-telemedicine">
    <style>
    /* Scoped alignment for Telemedicine Actions column */
    #tab-telemedicine #dior-tele-table th:last-child,
    #tab-telemedicine #dior-tele-table td:last-child {
        text-align: center !important;
        vertical-align: middle !important;
        width: 146px !important;
        min-width: 146px !important;
    }
    #tab-telemedicine #dior-tele-table .cell-actions,
    .dior-patient-portal-wrap #tab-telemedicine #dior-tele-table .cell-actions {
        display: inline-grid !important;
        grid-template-columns: 34px 34px 34px !important;
        grid-gap: 8px !important;
        gap: 8px !important;
        justify-content: center !important;
        align-items: center !important;
        margin: 0 auto !important;
        width: max-content !important;
        vertical-align: middle !important;
    }
    #tab-telemedicine #dior-tele-table .cell-actions .edit-btn {
        grid-column: 1 !important;
    }
    #tab-telemedicine #dior-tele-table .cell-actions .tele-join-btn,
    #tab-telemedicine #dior-tele-table .cell-actions a.action-icon-btn,
    #tab-telemedicine #dior-tele-table .cell-actions button:not(.edit-btn):not(.delete-btn) {
        grid-column: 2 !important;
    }
    #tab-telemedicine #dior-tele-table .cell-actions .delete-btn {
        grid-column: 3 !important;
    }
    #tab-telemedicine #dior-tele-table .action-icon-btn {
        width: 34px !important;
        min-width: 34px !important;
        max-width: 34px !important;
        height: 34px !important;
        min-height: 34px !important;
        max-height: 34px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
        margin: 0 !important;
        border: 1px solid #DCE5EF !important;
        border-radius: 8px !important;
        background: #fff !important;
        box-sizing: border-box !important;
        color: #4F46E5 !important;
        line-height: 1 !important;
        text-decoration: none !important;
        cursor: pointer !important;
    }
    #tab-telemedicine #dior-tele-table .action-icon-btn:hover {
        background: #EEF2FF !important;
        border-color: #6366F1 !important;
        color: #4338CA !important;
    }
    #tab-telemedicine #dior-tele-table .action-icon-btn svg {
        display: block !important;
        margin: auto !important;
        width: 16px !important;
        height: 16px !important;
        max-width: 16px !important;
        max-height: 16px !important;
        flex: 0 0 16px !important;
        fill: currentColor !important;
    }
    #tab-telemedicine #dior-tele-table .action-icon-btn i {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin: auto !important;
        font-size: 14px !important;
        line-height: 1 !important;
        color: inherit !important;
    }
    #tab-telemedicine #dior-tele-table .action-icon-btn.delete-btn {
        color: #4F46E5 !important;
    }
    #tab-telemedicine #dior-tele-table .action-icon-btn.delete-btn:hover {
        color: #DC2626 !important;
        border-color: #FCA5A5 !important;
        background: #FEF2F2 !important;
    }
    #tab-telemedicine #dior-tele-table .action-icon-btn.is-disabled {
        opacity: 0.3 !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
        border-color: #E2E8F0 !important;
        background: #F8FAFC !important;
        color: #94A3B8 !important;
    }
    </style>
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Telemedicine</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Telemedicine</span></li>
            </ul>
        </div>
    </div>

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2 class="table-title">Telemedicine / Video Consultations</h2>
                <div class="docs-title-line"></div>
            </div>
            <div class="docs-actions-wrapper">
                <div class="docs-search-box">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="dior-tele-search-input" placeholder="Search records..."
                        aria-label="Search box" onkeyup="diorFilterTele()">
                </div>
                <div class="docs-actions-group">
                    <button type="button" aria-label="Delete selected items" class="docs-icon-btn docs-btn-danger" title="Delete Selected" onclick="diorBulkDeleteStaticRows(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button type="button" aria-label="Add new record"
                        class="docs-icon-btn docs-btn-primary dior-ic-54410f9b78">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg>
                    </button>
                    <button type="button" aria-label="Export to CSV" class="docs-icon-btn docs-btn-success"
                        title="Export to CSV" onclick="diorDownloadTeleCSV()">
                        <i class="fas fa-file-arrow-down"></i>
                    </button>
                    <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info"
                        title="Refresh Page" onclick="window.location.reload()">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="docs-table-wrapper">
            <table class="docs-table" id="dior-tele-table">
                <thead>
                    <tr>
                        <th class="dior-ic-3fa4d8d717">
                            <input type="checkbox" class="dior-ic-52ff4d551f">
                        </th>
                        <th>SESSION ID <i class="fas fa-sort"></i></th>
                        <th>DOCTOR <i class="fas fa-sort"></i></th>
                        <th>SPECIALTY <i class="fas fa-sort"></i></th>
                        <th>DATE <i class="fas fa-sort"></i></th>
                        <th>TIME <i class="fas fa-sort"></i></th>
                        <th>DURATION <i class="fas fa-sort"></i></th>
                        <th>TYPE <i class="fas fa-sort"></i></th>
                        <th>STATUS <i class="fas fa-sort"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="dior-tele-tbody">
                    <?php if (!empty($appointments)): ?>
                        <?php foreach ($appointments as $apt): ?>
                            <?php
                            $tele_id = $apt['id'] ?? ($apt['appt_uid'] ?? '');
                            $tele_date = $apt['date'] ?? ($apt['appt_date'] ?? '—');
                            $tele_time = $apt['time'] ?? ($apt['appt_time'] ?? '—');
                            $tele_status = $apt['status'] ?? 'Confirmed';
                            $tele_class = strtolower($tele_status) === 'completed' ? 'col-green' : (strtolower($tele_status) === 'cancelled' ? 'col-red' : 'col-indigo');
                            ?>
                            <tr>
                                <td><input type="checkbox" class="dior-ic-52ff4d551f"></td>
                                <td><span class="cell-text"><?php echo esc_html($tele_id ?: '—'); ?></span></td>
                                <td><span
                                        class="cell-text"><?php echo esc_html($apt['provider'] ?? ($apt['doctor_name'] ?? 'Attending Physician')); ?></span>
                                </td>
                                <td><span
                                        class="cell-text"><?php echo esc_html($apt['provider_spec'] ?? 'Telehealth Physician'); ?></span>
                                </td>
                                <td>
                                    <div class="cell-content cell-icon-text"><i class="material-icons-outlined cell-icon">
                                        </i><span class="cell-text"><?php echo esc_html($tele_date); ?></span></div>
                                </td>
                                <td><span class="cell-text"><?php echo esc_html($tele_time); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($apt['duration'] ?? '30 minutes'); ?></span>
                                </td>
                                <td><span
                                        class="cell-text"><?php echo esc_html($apt['type'] ?? ($apt['visit_type'] ?? 'Video Visit')); ?></span>
                                </td>
                                <td>
                                    <div class="cell-content">
                                        <div class="badge-solid <?php echo esc_attr($tele_class); ?>">
                                            <?php echo esc_html($tele_status); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        <button type="button" class="action-icon-btn edit-btn" title="View Session"
                                            onclick="diorViewTelemedicine(this)"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16" style="background:transparent;"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg></button>
                                        <?php if (!in_array(strtolower($tele_status), ['completed', 'cancelled', 'no show'], true)): ?>
                                            <?php if (!empty($apt['join_url'])): ?><a class="action-icon-btn tele-join-btn"
                                                    title="Join Consultation" href="<?php echo esc_url($apt['join_url']); ?>"
                                                    target="_blank" rel="noopener"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-camera-reels" viewBox="0 0 16 16" style="background:transparent;"><path d="M6 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0M1 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M9 6h.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 7.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 16H2a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm6 8.73V7.27l-3.5 1.555v4.35zM1 8v6a1 1 0 0 0 1 1h7.5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1"/><path d="M9 6a3 3 0 1 0 0-6 3 3 0 0 0 0 6M7 3a2 2 0 1 1 4 0 2 2 0 0 1-4 0"/></svg></a><?php else: ?><button type="button"
                                                    class="action-icon-btn tele-join-btn" title="Join Consultation"
                                                    onclick="diorViewTelemedicine(this)"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-camera-reels" viewBox="0 0 16 16" style="background:transparent;"><path d="M6 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0M1 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M9 6h.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 7.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 16H2a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm6 8.73V7.27l-3.5 1.555v4.35zM1 8v6a1 1 0 0 0 1 1h7.5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1"/><path d="M9 6a3 3 0 1 0 0-6 3 3 0 0 0 0 6M7 3a2 2 0 1 1 4 0 2 2 0 0 1-4 0"/></svg></button><?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="action-icon-btn tele-join-btn is-disabled" title="Call Not Available" disabled><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-camera-reels" viewBox="0 0 16 16" style="background:transparent;"><path d="M6 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0M1 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M9 6h.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 7.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 16H2a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm6 8.73V7.27l-3.5 1.555v4.35zM1 8v6a1 1 0 0 0 1 1h7.5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1"/><path d="M9 6a3 3 0 1 0 0-6 3 3 0 0 0 0 6M7 3a2 2 0 1 1 4 0 2 2 0 0 1-4 0"/></svg></button>
                                        <?php endif; ?>
                                        <button type="button" class="action-icon-btn delete-btn" title="Cancel Session"
                                            onclick="diorDeleteStaticRow(this, 'telemedicine')"><i
                                                class="fas fa-xmark"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="dior-empty-state">No telemedicine sessions available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="docs-pagination-container">
            <span class="docs-showing-text" id="dior-tele-page-count">0 selected / <?php echo count($appointments); ?>
                total</span>
            <div id="dior-tele-pagination"></div>
        </div>
    </div>

</section>
<!-- ============================================================== -->
<!-- 5. MEDICAL RECORD TAB -->
<!-- ============================================================== -->