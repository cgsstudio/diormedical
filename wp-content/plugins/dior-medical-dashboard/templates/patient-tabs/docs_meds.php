<section class="dior-tab-panel" id="tab-docs_meds">

    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Prescriptions</h4>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <button type="button" class="btn btn-sm btn-outline-primary"
                onclick="diorOpenModal('modal-change-pharmacy')">
                <i class="fas fa-store"></i> Update Pharmacy
            </button>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Prescriptions</span></li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-3 px-md-4 mb-4">
        <button type="button" class="dior-subtab-pill btn active" data-patient-tab="docs_meds" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('docs_meds');">
            <i class="fas fa-prescription-bottle-medical"></i> Prescriptions
        </button>
        <button type="button" class="dior-subtab-pill btn" data-patient-tab="documents" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('documents');">
            <i class="fas fa-file-lines"></i> Documents & Report
        </button>
    </div>

    <!-- Prescriptions Table (same design as Appointments) -->
    <div class="va-table-wrapper">
        <div class="va-table-wrapper">
            <div class="docs-card">
                <div class="docs-header-container">
                    <div class="docs-title-box">
                        <h2 class="table-title">Prescriptions</h2>
                        <div class="docs-title-line"></div>
                    </div>
                    <div class="docs-actions-wrapper">
                        <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                            <input type="text" id="dior-rx-search-input" class="form-control border-start-0 ps-0"
                                placeholder="Search records..." aria-label="Search box" onkeyup="diorFilterRxTable()">
                        </div>
                        <div class="docs-actions-group">
                            <button type="button" aria-label="Delete selected items" class="docs-icon-btn docs-btn-danger btn"
                                title="Delete Selected" onclick="diorBulkDeleteRxRows()">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button type="button" aria-label="Export to CSV" class="docs-icon-btn docs-btn-success btn"
                                title="Export to CSV" onclick="diorDownloadRxCSV()">
                                <i class="fas fa-file-arrow-down"></i>
                            </button>
                            <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info btn"
                                title="Refresh Page" onclick="window.location.reload()">
                                <i class="fas fa-rotate-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="docs-table-wrapper">
                    <table class="docs-table table table-hover" id="dior-rx-table">
                        <thead>
                            <tr>
                                <th style="width:36px;">
                                    <input type="checkbox" id="dior-select-all-rx"
                                        class="master-checkbox form-check-input" onclick="diorToggleSelectAllRx(this)">
                                </th>
                                <th>ID <i class="fas fa-sort"></i></th>
                                <th>MEDICATION <i class="fas fa-sort"></i></th>
                                <th>PRESCRIBED BY <i class="fas fa-sort"></i></th>
                                <th>DATE <i class="fas fa-sort"></i></th>
                                <th>CONDITION <i class="fas fa-sort"></i></th>
                                <th>STATUS <i class="fas fa-sort"></i></th>
                                <th>ACTIONS <i class="fas fa-sort"></i></th>
                            </tr>
                        </thead>
                        <tbody id="dior-rx-tbody">
                            <?php
                            $patient_rx_rows = is_array($prescriptions ?? []) ? $prescriptions : [];
                            foreach ($patient_rx_rows as $rx):
                                $rx_id = $rx['id'] ?? ($rx['order_id'] ?? '—');
                                $rx_name = $rx['name'] ?? ($rx['medication'] ?? '');
                                $rx_doctor = $rx['prescribed_by'] ?? 'Provider not recorded';
                                $rx_date = $rx['date_prescribed'] ?? ($rx['date'] ?? '—');
                                $rx_title = !empty($rx['title']) ? $rx['title'] : '';
                                $rx_disease = !empty($rx['condition']) ? $rx['condition'] : (!empty($rx['diagnosis']) ? $rx['diagnosis'] : (!empty($rx['title']) ? $rx['title'] : 'Condition not recorded'));
                                $rx_status = $rx['status'] ?? 'Status unavailable';
                                $rx_status_lc = strtolower($rx_status);
                                if ($rx_status_lc === 'active') {
                                    $rx_st_cls = 'status-confirm';
                                } elseif ($rx_status_lc === 'inactive' || $rx_status_lc === 'expired') {
                                    $rx_st_cls = 'status-cancelled';
                                } else {
                                    $rx_st_cls = 'status-pending';
                                }
                                ?>
                                <tr>
                                    <td style="width:36px;">
                                        <input type="checkbox" class="master-checkbox rx-row-checkbox form-check-input"
                                            onclick="diorUpdateRxSelectedCount()">
                                    </td>
                                    <td>
                                        <span class="cell-text"
                                            style="font-family:monospace;font-size:12px;color:#6366f1;"><?php echo esc_html($rx_id); ?></span>
                                    </td>
                                    <td>
                                        <div class="cell-content cell-image-name" style="align-items: flex-start;">

                                            <div style="display:flex;flex-direction:column;gap:3px;">
                                                <?php if ($rx_title !== '' && $rx_title !== $rx_name && $rx_title !== 'General Medical Prescription'): ?>
                                                    <strong class="cell-text"
                                                        style="font-weight:600;color:#0F172A;font-size:13px;"><?php echo esc_html($rx_title); ?></strong>
                                                <?php endif; ?>
                                                <?php
                                                $med_lines = preg_split('/;\s*|\r\n|\n/', trim($rx_name));
                                                $med_lines = array_filter(array_map('trim', (array) $med_lines));
                                                if (empty($med_lines)) {
                                                    $med_lines = [$rx_name];
                                                }
                                                ?>
                                                <div class="cell-text doctor-name"
                                                    style="font-size:12.5px;color:#334155;line-height:1.45;display:flex;flex-direction:column;gap:3px;">
                                                    <?php foreach ($med_lines as $m_line): ?>
                                                        <div style="white-space: normal;"><?php echo esc_html($m_line); ?></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="cell-text"><?php echo esc_html($rx_doctor); ?></span></td>
                                    <td>
                                        <div class="cell-content cell-icon-text">
                                            <i class="far fa-calendar cell-icon"></i>
                                            <span class="cell-text"><?php echo esc_html($rx_date); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="cell-text"><?php echo esc_html($rx_disease); ?></span></td>
                                    <td>
                                        <span
                                            class="status-cell-text <?php echo esc_attr($rx_st_cls); ?>"><?php echo esc_html(ucfirst($rx_status)); ?></span>
                                    </td>
                                    <td>
                                        <div class="cell-content action-cell" style="gap:8px;">
                                            <button type="button" class="action-icon-btn edit-btn btn"
                                                title="View Prescription Details"
                                                onclick="diorViewRxDetails('<?php echo esc_js($rx_id); ?>', '<?php echo esc_js($rx_name); ?>', '<?php echo esc_js($rx['instructions'] ?? ''); ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="action-icon-btn btn" title="Download"
                                                onclick="diorDownloadRxPdf('<?php echo esc_js($rx_id); ?>')">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($patient_rx_rows)): ?>
                                <tr>
                                    <td colspan="8" class="dior-empty-state">
                                        <div style="padding:40px;text-align:center;">
                                            <i class="fas fa-prescription-bottle-medical"
                                                style="font-size:40px;color:#CBD5E1;margin-bottom:12px;display:block;"></i>
                                            <p style="color:#94A3B8;margin:0;">No prescriptions available
                                                yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        /* ── Prescriptions Table JS ── */
        (function () {

            // Search/Filter
            window.diorFilterRxTable = function () {
                var input = document.getElementById('dior-rx-search-input');
                if (!input) return;
                var filter = input.value.toLowerCase();
                var rows = document.querySelectorAll('#dior-rx-tbody tr');
                rows.forEach(function (row) {
                    var text = row.innerText.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            };

            // Select All
            window.diorToggleSelectAllRx = function (masterCb) {
                var checkboxes = document.querySelectorAll('.rx-row-checkbox');
                checkboxes.forEach(function (cb) { cb.checked = masterCb.checked; });
                diorUpdateRxSelectedCount();
            };

            // Update count
            window.diorUpdateRxSelectedCount = function () {
                var total = document.querySelectorAll('#dior-rx-tbody tr').length;
                var selected = document.querySelectorAll('.rx-row-checkbox:checked').length;
                var el = document.getElementById('dior-rx-page-count');
                if (el) el.textContent = selected + ' selected / ' + total + ' total';
            };

            // Bulk delete
            window.diorBulkDeleteRxRows = function () {
                var checkboxes = document.querySelectorAll('.rx-row-checkbox:checked');
                if (!checkboxes.length) {
                    Swal.fire({ icon: 'info', title: 'Selection Required', text: 'Please select at least one row to delete.' });
                    return;
                }
                Swal.fire({
                    title: 'Delete Prescriptions?',
                    text: 'Are you sure you want to delete ' + checkboxes.length + ' selected record(s)?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#EF4444',
                    cancelButtonColor: '#64748B',
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    checkboxes.forEach(function (cb) { cb.closest('tr').remove(); });
                    diorUpdateRxSelectedCount();
                    Swal.fire({ icon: 'success', title: 'Deleted', text: 'Selected prescriptions removed.', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
                });
            };

        })();
    </script>

</section>