<section class="dior-tab-panel" id="tab-docs_meds">

<style>
/* Step 3A: Responsive Bootstrap-inspired prescription page styling. */
#tab-docs_meds{color:#334155}
#tab-docs_meds h4{color:#142b4a!important;font-size:22px!important;font-weight:700!important;letter-spacing:-.025em}
#tab-docs_meds .va-table-wrapper{width:100%;min-width:0}
#tab-docs_meds .docs-card{border:1px solid #e7edf5!important;border-radius:18px!important;background:#fff!important;box-shadow:0 8px 28px rgba(22,52,91,.06)!important;overflow:hidden}
#tab-docs_meds .docs-header-container{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;padding:20px 22px!important;border-bottom:1px solid #edf1f7}
#tab-docs_meds .docs-title-box,#tab-docs_meds .docs-actions-wrapper,#tab-docs_meds .docs-actions-group{display:flex;align-items:center;flex-wrap:wrap;gap:9px}
#tab-docs_meds .docs-table-wrapper{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
#tab-docs_meds #dior-rx-table{min-width:720px;border-collapse:separate!important;border-spacing:0;margin-bottom:0}
#tab-docs_meds #dior-rx-table thead th{background:#f6f9fd!important;color:#63748b!important;border-top:1px solid #e7edf5!important;border-bottom:1px solid #e7edf5!important;padding:14px 13px!important;font-size:10px!important;font-weight:700!important;letter-spacing:.05em;text-transform:uppercase}
#tab-docs_meds #dior-rx-table tbody td{padding:14px 13px!important;border-bottom:1px solid #edf1f6!important;color:#4a5c73!important;vertical-align:middle!important;font-size:12px!important;background:#fff!important}
#tab-docs_meds #dior-rx-table tbody tr:hover td{background:#f8fbff!important}
#tab-docs_meds #dior-rx-search-input{min-height:38px;border:1px solid #e2e8f0!important;border-radius:10px!important;background:#f8fafc!important;box-shadow:none!important;font-size:13px}
#tab-docs_meds .btn{border-radius:9px!important;font-weight:600}
#tab-docs_meds .docs-icon-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;min-height:34px}
#tab-docs_meds .status-cell-text{display:inline-flex;align-items:center;border-radius:7px;padding:5px 9px;font-weight:600}
@media(max-width:767.98px){#tab-docs_meds h4{font-size:19px!important}#tab-docs_meds .docs-card{border-radius:14px!important}#tab-docs_meds .docs-header-container{padding:15px!important;align-items:stretch}#tab-docs_meds .docs-title-box,#tab-docs_meds .docs-actions-wrapper{width:100%}#tab-docs_meds .docs-actions-wrapper{justify-content:flex-start}#tab-docs_meds .docs-actions-group{gap:7px}#tab-docs_meds #dior-rx-search-input{width:100%;max-width:100%}}

/* Patient Prescriptions — mobile-only Bootstrap card layout. */
@media (max-width:767.98px) {
  #dior-patient-portal-app #tab-docs_meds {
    min-width:0;
    width:100%;
    padding:0 0 24px;
    color:#334155;
  }
  #dior-patient-portal-app #tab-docs_meds,
  #dior-patient-portal-app #tab-docs_meds * { box-sizing:border-box; }
  #dior-patient-portal-app #tab-docs_meds > .mb-4 {
    display:flex !important;
    flex-direction:column !important;
    align-items:stretch !important;
    gap:10px !important;
    margin:0 0 14px !important;
    padding:0 !important;
  }
  #dior-patient-portal-app #tab-docs_meds > .mb-4 > div:last-child {
    display:flex !important;
    flex-direction:column !important;
    align-items:stretch !important;
    gap:8px !important;
  }
  #dior-patient-portal-app #tab-docs_meds > .mb-4 .va-breadcrumb-list { display:none !important; }
  #dior-patient-portal-app #tab-docs_meds > .mb-4 button {
    width:100% !important;
    min-height:40px !important;
    border-radius:10px !important;
    font-size:12px !important;
  }
  #dior-patient-portal-app #tab-docs_meds .dior-subtab-nav-bar {
    display:grid !important;
    grid-template-columns:repeat(2,minmax(0,1fr)) !important;
    gap:8px !important;
    width:100% !important;
    margin:0 0 14px !important;
    padding:0 !important;
  }
  #dior-patient-portal-app #tab-docs_meds .dior-subtab-pill {
    display:flex !important;
    align-items:center !important;
    justify-content:flex-start !important;
    gap:7px !important;
    min-width:0 !important;
    min-height:43px !important;
    padding:8px 9px !important;
    border:1px solid #e2eaf3 !important;
    border-radius:10px !important;
    background:#fff !important;
    color:#0b1030 !important;
    font-size:10.5px !important;
    font-weight:700 !important;
    line-height:1.25 !important;
    text-align:left !important;
    white-space:normal !important;
  }
  #dior-patient-portal-app #tab-docs_meds .dior-subtab-pill i { flex:0 0 auto; font-size:13px !important; }
  #dior-patient-portal-app #tab-docs_meds .dior-subtab-pill.active {
    background:#2865a5 !important;
    border-color:#2865a5 !important;
    color:#fff !important;
    box-shadow:0 4px 12px rgba(40,101,165,.18);
  }
  #dior-patient-portal-app #tab-docs_meds .va-table-wrapper { width:100%; min-width:0; }
  #dior-patient-portal-app #tab-docs_meds .docs-card {
    border:1px solid #e2eaf3 !important;
    border-radius:15px !important;
    box-shadow:0 4px 14px rgba(15,45,78,.05) !important;
    overflow:hidden !important;
  }
  #dior-patient-portal-app #tab-docs_meds .docs-header-container {
    display:flex !important;
    flex-direction:column !important;
    align-items:stretch !important;
    gap:11px !important;
    padding:14px !important;
  }
  #dior-patient-portal-app #tab-docs_meds .docs-title-box { width:100%; }
  #dior-patient-portal-app #tab-docs_meds .table-title {
    margin:0 !important;
    color:#0b1030 !important;
    font-size:16px !important;
    font-weight:800 !important;
    line-height:1.3 !important;
  }
  #dior-patient-portal-app #tab-docs_meds .docs-title-line { display:none !important; }
  #dior-patient-portal-app #tab-docs_meds .docs-actions-wrapper {
    display:grid !important;
    grid-template-columns:minmax(0,1fr) repeat(3,40px) !important;
    align-items:center !important;
    gap:7px !important;
    width:100% !important;
  }
  #dior-patient-portal-app #tab-docs_meds .docs-actions-wrapper > .input-group {
    width:100% !important;
    min-width:0 !important;
    grid-column:1 / -1 !important;
    margin:0 !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-search-input {
    width:100% !important;
    min-width:0 !important;
    min-height:42px !important;
    padding:9px 12px !important;
    border:1px solid #d8e2ed !important;
    border-radius:10px !important;
    background:#fff !important;
    font-size:16px !important;
  }
  #dior-patient-portal-app #tab-docs_meds .docs-actions-group {
    display:contents !important;
  }
  #dior-patient-portal-app #tab-docs_meds .docs-actions-group .docs-icon-btn {
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    width:40px !important;
    min-width:40px !important;
    height:40px !important;
    min-height:40px !important;
    padding:0 !important;
    border-radius:9px !important;
  }
  #dior-patient-portal-app #tab-docs_meds .docs-actions-group .docs-icon-btn i { font-size:14px !important; }
  #dior-patient-portal-app #tab-docs_meds .docs-table-wrapper {
    width:100% !important;
    padding:0 12px 12px !important;
    overflow:visible !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table,
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table tbody {
    display:block !important;
    width:100% !important;
    min-width:0 !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table thead { display:none !important; }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table tbody tr:not(:has(.dior-empty-state)) {
    display:grid !important;
    grid-template-columns:repeat(2,minmax(0,1fr)) !important;
    gap:11px 12px !important;
    width:100% !important;
    min-width:0 !important;
    margin:0 0 11px !important;
    padding:13px !important;
    border:1px solid #e2eaf3 !important;
    border-radius:13px !important;
    background:#fff !important;
    box-shadow:0 3px 10px rgba(13,43,75,.045) !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table tbody tr:not(:has(.dior-empty-state)) > td {
    display:flex !important;
    flex-direction:column !important;
    align-items:flex-start !important;
    justify-content:flex-start !important;
    gap:4px !important;
    width:auto !important;
    min-width:0 !important;
    padding:0 !important;
    border:0 !important;
    color:#344b63 !important;
    font-size:12px !important;
    line-height:1.4 !important;
    overflow-wrap:anywhere !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table tbody tr > td::before {
    content:attr(data-label);
    display:block;
    color:#718096;
    font-size:9px;
    font-weight:800;
    letter-spacing:.05em;
    line-height:1.3;
    text-transform:uppercase;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table tbody tr > td:first-child {
    grid-column:1 / -1 !important;
    flex-direction:row !important;
    align-items:center !important;
    justify-content:flex-end !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table tbody tr > td:first-child::before { content:"Select prescription"; margin-right:auto; }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table tbody tr > td:nth-child(3) { grid-column:1 / -1 !important; }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table .cell-text,
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table .doctor-name { max-width:100%; overflow-wrap:anywhere; }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table .status-cell-text {
    display:inline-flex !important;
    align-items:center !important;
    max-width:100%;
    padding:5px 9px !important;
    border-radius:20px !important;
    font-size:10px !important;
    font-weight:700 !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table .action-cell {
    display:flex !important;
    flex-direction:row !important;
    align-items:center !important;
    gap:7px !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table .action-icon-btn {
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    width:36px !important;
    min-width:36px !important;
    height:36px !important;
    min-height:36px !important;
    padding:0 !important;
    border:1px solid #e2eaf3 !important;
    border-radius:9px !important;
    background:#f8fbff !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table .action-icon-btn i { font-size:13px !important; }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table tr:has(.dior-empty-state) {
    display:block !important;
    width:100% !important;
    margin:0 !important;
    padding:0 !important;
    border:0 !important;
    box-shadow:none !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table .dior-empty-state {
    display:block !important;
    width:100% !important;
    padding:0 !important;
    border:0 !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table .dior-empty-state > div {
    padding:28px 12px !important;
  }
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table .dior-empty-state i { font-size:30px !important; }
}
@media (min-width:768px) {
  #dior-patient-portal-app #tab-docs_meds #dior-rx-table tbody tr > td::before { content:none !important; }
}

</style>

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
                                    <td style="width:36px;" data-label="Select">
                                        <input type="checkbox" class="master-checkbox rx-row-checkbox form-check-input"
                                            onclick="diorUpdateRxSelectedCount()">
                                    </td>
                                    <td data-label="Prescription ID">
                                        <span class="cell-text"
                                            style="font-family:monospace;font-size:12px;color:#6366f1;"><?php echo esc_html($rx_id); ?></span>
                                    </td>
                                    <td data-label="Medication">
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
                                    <td data-label="Prescribed By"><span class="cell-text"><?php echo esc_html($rx_doctor); ?></span></td>
                                    <td data-label="Date Prescribed">
                                        <div class="cell-content cell-icon-text">
                                            <i class="far fa-calendar cell-icon"></i>
                                            <span class="cell-text"><?php echo esc_html($rx_date); ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Condition"><span class="cell-text"><?php echo esc_html($rx_disease); ?></span></td>
                                    <td data-label="Status">
                                        <span
                                            class="status-cell-text <?php echo esc_attr($rx_st_cls); ?>"><?php echo esc_html(ucfirst($rx_status)); ?></span>
                                    </td>
                                    <td data-label="Actions">
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
                <div id="dior-rx-pagination" class="dior-mobile-card-pagination mx-3 mb-3" aria-label="Prescription pages"></div>
            </div>
        </div>
    </div>

    <script>
        /* Prescription search, selection and five-record pagination. */
        (function () {
            var currentPage = 1;
            var perPage = 5;
            var tbody = document.getElementById('dior-rx-tbody');
            var search = document.getElementById('dior-rx-search-input');
            var pager = document.getElementById('dior-rx-pagination');
            if (!tbody || !pager) return;

            function getRows() {
                return Array.prototype.slice.call(tbody.querySelectorAll('tr')).filter(function (row) {
                    return !row.querySelector('.dior-empty-state');
                });
            }

            function render() {
                var allRows = getRows();
                var query = search ? String(search.value || '').trim().toLowerCase() : '';
                var filtered = allRows.filter(function (row) {
                    return !query || row.textContent.toLowerCase().indexOf(query) !== -1;
                });
                var totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
                currentPage = Math.min(Math.max(1, currentPage), totalPages);
                allRows.forEach(function (row) { row.style.display = 'none'; });
                filtered.slice((currentPage - 1) * perPage, currentPage * perPage).forEach(function (row) {
                    row.style.display = '';
                });
                var emptyRow = tbody.querySelector('tr .dior-empty-state');
                if (emptyRow) emptyRow.closest('tr').style.display = allRows.length ? 'none' : '';
                var start = filtered.length ? ((currentPage - 1) * perPage) + 1 : 0;
                var finish = Math.min(currentPage * perPage, filtered.length);
                pager.innerHTML = filtered.length > perPage
                    ? '<span class="dior-mobile-page-count">Showing ' + start + '–' + finish + ' of ' + filtered.length + '</span>' +
                      '<div class="dior-mobile-page-controls"><button type="button" data-page="' + (currentPage - 1) + '" ' + (currentPage === 1 ? 'disabled' : '') + ' aria-label="Previous page"><i class="fas fa-chevron-left"></i></button><span>' + currentPage + ' / ' + totalPages + '</span><button type="button" data-page="' + (currentPage + 1) + '" ' + (currentPage === totalPages ? 'disabled' : '') + ' aria-label="Next page"><i class="fas fa-chevron-right"></i></button></div>'
                    : '';
                var count = document.getElementById('dior-rx-page-count');
                if (count) count.textContent = document.querySelectorAll('.rx-row-checkbox:checked').length + ' selected / ' + filtered.length + ' total';
            }

            window.diorFilterRxTable = function () { currentPage = 1; render(); };
            window.diorToggleSelectAllRx = function (masterCb) {
                document.querySelectorAll('.rx-row-checkbox').forEach(function (cb) { cb.checked = masterCb.checked; });
                render();
            };
            window.diorUpdateRxSelectedCount = render;
            window.diorBulkDeleteRxRows = function () {
                var selected = document.querySelectorAll('.rx-row-checkbox:checked');
                if (!selected.length) {
                    if (window.Swal) Swal.fire({ icon: 'info', title: 'Selection Required', text: 'Please select at least one row to delete.' });
                    return;
                }
                if (!window.Swal) return;
                Swal.fire({
                    title: 'Delete Prescriptions?',
                    text: 'Are you sure you want to delete ' + selected.length + ' selected record(s)?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#EF4444',
                    cancelButtonColor: '#64748B',
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    Array.prototype.slice.call(document.querySelectorAll('.rx-row-checkbox:checked')).forEach(function (cb) {
                        var row = cb.closest('tr');
                        if (row) row.remove();
                    });
                    render();
                    Swal.fire({ icon: 'success', title: 'Deleted', text: 'Selected prescriptions removed.', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
                });
            };
            pager.addEventListener('click', function (event) {
                var button = event.target.closest('button[data-page]');
                if (!button || button.disabled) return;
                currentPage = parseInt(button.getAttribute('data-page'), 10) || 1;
                render();
            });
            render();
        })();
    </script>

</section>