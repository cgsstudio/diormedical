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
            border: 1px solid #cbdbea !important;
            border-radius: 8px !important;
            background: #fff !important;
            box-sizing: border-box !important;
            color: #2b70b8 !important;
            line-height: 1 !important;
            text-decoration: none !important;
            cursor: pointer !important;
        }

        #tab-telemedicine #dior-tele-table .action-icon-btn:hover {
            background: #f4f9fd !important;
            border-color: #2b70b8 !important;
            color: #1a528e !important;
        }

        #tab-telemedicine #dior-tele-table .action-icon-btn svg,
        #tab-telemedicine #dior-tele-table .action-icon-btn i {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 14px !important;
            line-height: 1 !important;
        }

        #tab-telemedicine #dior-tele-table .action-icon-btn.delete-btn {
            color: #ef4444 !important;
        }

        #tab-telemedicine #dior-tele-table .action-icon-btn.delete-btn:hover {
            color: #dc2626 !important;
            border-color: #ef4444 !important;
            background: #fef2f2 !important;
        }

        #tab-telemedicine #dior-tele-table .action-icon-btn.is-disabled {
            opacity: 0.3 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            border-color: #E2E8F0 !important;
            background: #F8FAFC !important;
            color: #94A3B8 !important;
        }
    
        /* Step 4: responsive Bootstrap-inspired telemedicine styling. */
        #tab-telemedicine { color:#334155; }
        #tab-telemedicine h4 { color:#142b4a!important; font-size:22px!important; font-weight:700!important; letter-spacing:-.025em; }
        #tab-telemedicine .docs-card { border:1px solid #e7edf5!important; border-radius:18px!important; background:#fff!important; box-shadow:0 8px 28px rgba(22,52,91,.06)!important; overflow:hidden; }
        #tab-telemedicine .docs-header-container { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; padding:20px 22px!important; border-bottom:1px solid #edf1f7; }
        #tab-telemedicine .docs-title-box { min-width:0; }
        #tab-telemedicine .table-title { color:#183657!important; font-size:16px!important; font-weight:700!important; margin:0 0 7px!important; }
        #tab-telemedicine .docs-actions-wrapper, #tab-telemedicine .docs-actions-group { display:flex; align-items:center; flex-wrap:wrap; gap:9px; }
        #tab-telemedicine .dior-bs-search-group { max-width:100%; }
        #tab-telemedicine #dior-tele-search-input { min-height:38px; border:1px solid #e2e8f0!important; border-radius:10px!important; background:#f8fafc!important; box-shadow:none!important; font-size:13px; }
        #tab-telemedicine .docs-table-wrapper { width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
        #tab-telemedicine #dior-tele-table { min-width:760px; border-collapse:separate!important; border-spacing:0; margin-bottom:0; }
        #tab-telemedicine #dior-tele-table thead th { background:#f6f9fd!important; color:#63748b!important; border-top:1px solid #e7edf5!important; border-bottom:1px solid #e7edf5!important; padding:14px 13px!important; font-size:10px!important; font-weight:700!important; letter-spacing:.05em; text-transform:uppercase; }
        #tab-telemedicine #dior-tele-table tbody td { padding:14px 13px!important; border-bottom:1px solid #edf1f6!important; color:#4a5c73!important; vertical-align:middle!important; font-size:12px!important; background:#fff!important; }
        #tab-telemedicine #dior-tele-table tbody tr:hover td { background:#f8fbff!important; }
        #tab-telemedicine .btn { border-radius:9px!important; font-weight:600; }
        #tab-telemedicine .dior-empty-state { border:1px dashed #d7e1ed!important; border-radius:14px!important; padding:28px 16px!important; background:#f8fbff!important; }
        #tab-telemedicine .modal-content { border:1px solid #e7edf5!important; border-radius:16px!important; box-shadow:0 18px 50px rgba(15,35,60,.16); overflow:hidden; }
        #tab-telemedicine .modal-header { border-bottom:1px solid #edf1f7!important; }
        @media(max-width:767.98px) {
            #tab-telemedicine h4 { font-size:19px!important; }
            #tab-telemedicine .docs-card { border-radius:14px!important; }
            #tab-telemedicine .docs-header-container { padding:15px!important; align-items:stretch; }
            #tab-telemedicine .docs-title-box, #tab-telemedicine .docs-actions-wrapper { width:100%; }
            #tab-telemedicine .docs-actions-wrapper { flex-direction:column; align-items:stretch; }
            #tab-telemedicine .dior-bs-search-group { width:100%!important; }
            #tab-telemedicine .docs-actions-group { gap:7px; }
            #tab-telemedicine .docs-table-wrapper { border-radius:0 0 14px 14px; }
            #tab-telemedicine .va-breadcrumb-list { flex-wrap:wrap; }
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
                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                    <input type="text" id="dior-tele-search-input" class="form-control border-start-0 ps-0"
                        placeholder="Search records..." aria-label="Search box" onkeyup="diorFilterTele()">
                </div>
                <div class="docs-actions-group">
                    <button type="button" aria-label="Delete selected items" class="docs-icon-btn docs-btn-danger btn"
                        title="Delete Selected" onclick="diorBulkDeleteStaticRows(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button type="button" aria-label="Add new record"
                        class="docs-icon-btn docs-btn-primary btn" title="Add New Consultation">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" aria-label="Export to CSV" class="docs-icon-btn docs-btn-success btn"
                        title="Export to CSV" onclick="diorDownloadTeleCSV()">
                        <i class="fas fa-file-arrow-down"></i>
                    </button>
                    <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info btn"
                        title="Refresh Page" onclick="window.location.reload()">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Separate mobile Telemedicine UI; desktop table remains intact. -->
        <div class="dior-tele-mobile-layout d-none">
            <div class="dior-tele-mobile-list" id="dior-tele-mobile-list" aria-live="polite"></div>
            <div class="dior-tele-mobile-pagination" id="dior-tele-mobile-pagination"></div>
        </div>
        <div class="docs-table-wrapper">
            <table class="docs-table table table-hover" id="dior-tele-table">
                <thead>
                    <tr>
                        <th class="dior-ic-3fa4d8d717">
                            <input type="checkbox" class="dior-ic-52ff4d551f form-check-input">
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
                            $tele_doc = $apt['provider'] ?? ($apt['doctor_name'] ?? 'Attending Physician');
                            $tele_spec = $apt['provider_spec'] ?? 'Telehealth Physician';
                            $tele_dur = $apt['duration'] ?? '30 minutes';
                            $tele_type = $apt['type'] ?? ($apt['visit_type'] ?? 'Video Visit');
                            $tele_join = $apt['join_url'] ?? '';
                            ?>
                            <tr data-tele-id="<?php echo esc_attr($tele_id ?: '—'); ?>"
                                data-doctor="<?php echo esc_attr($tele_doc); ?>"
                                data-specialty="<?php echo esc_attr($tele_spec); ?>"
                                data-date="<?php echo esc_attr($tele_date); ?>"
                                data-time="<?php echo esc_attr($tele_time); ?>"
                                data-duration="<?php echo esc_attr($tele_dur); ?>"
                                data-type="<?php echo esc_attr($tele_type); ?>"
                                data-status="<?php echo esc_attr($tele_status); ?>"
                                data-join-url="<?php echo esc_url($tele_join); ?>">
                                <td><input type="checkbox" class="dior-ic-52ff4d551f form-check-input"></td>
                                <td><span class="cell-text"><?php echo esc_html($tele_id ?: '—'); ?></span></td>
                                <td><span
                                        class="cell-text"><?php echo esc_html($tele_doc); ?></span>
                                </td>
                                <td><span
                                        class="cell-text"><?php echo esc_html($tele_spec); ?></span>
                                </td>
                                <td>
                                    <div class="cell-content cell-icon-text"><i class="far fa-calendar cell-icon">
                                        </i><span class="cell-text"><?php echo esc_html($tele_date); ?></span></div>
                                </td>
                                <td><span class="cell-text"><?php echo esc_html($tele_time); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($tele_dur); ?></span>
                                </td>
                                <td><span
                                        class="cell-text"><?php echo esc_html($tele_type); ?></span>
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
                                        <button type="button" class="action-icon-btn edit-btn btn" title="View Session"
                                            onclick="diorViewTelemedicine(this)">
                                            <i class="fas fa-eye text-primary"></i>
                                        </button>
                                        <?php if (!in_array(strtolower($tele_status), ['completed', 'cancelled', 'no show'], true)): ?>
                                            <?php if (!empty($tele_join)): ?><a class="action-icon-btn tele-join-btn btn"
                                                    title="Join Consultation" href="<?php echo esc_url($tele_join); ?>"
                                                    target="_blank" rel="noopener"><i class="fas fa-video text-success"></i></a><?php else: ?><button type="button"
                                                    class="action-icon-btn tele-join-btn btn" title="Join Consultation"
                                                    onclick="diorViewTelemedicine(this)"><i class="fas fa-video text-success"></i></button><?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="action-icon-btn tele-join-btn is-disabled btn"
                                                title="Call Not Available" disabled><i class="fas fa-video text-muted"></i></button>
                                        <?php endif; ?>
                                        <button type="button" class="action-icon-btn delete-btn btn" title="Cancel Session"
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
    </div>

    <!-- Telemedicine Session Details Modal (Strict Bootstrap 5 Rule 3.F Standard) -->
    <div class="modal fade" id="diorTelemedicineModal" tabindex="-1" aria-labelledby="diorTelemedicineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                    <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="diorTelemedicineModalLabel">
                        <i class="fas fa-video text-primary me-2"></i>Telemedicine Consultation
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="diorCloseTelemedicineModal()"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Session ID</div>
                            <div class="fw-bold text-dark fs-6" id="dior-tele-modal-session-id">—</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Status</div>
                            <div id="dior-tele-modal-status">
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold">Confirmed</span>
                            </div>
                        </div>
                        <div class="col-12"><hr class="my-1 border-secondary-subtle"></div>
                        <div class="col-sm-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Doctor / Attending Physician</div>
                            <div class="fw-bold text-dark fs-6" id="dior-tele-modal-doctor">—</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Medical Specialty</div>
                            <div class="fw-bold text-dark fs-6" id="dior-tele-modal-specialty">—</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Consultation Date</div>
                            <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                                <i class="far fa-calendar text-primary"></i> <span id="dior-tele-modal-date">—</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Time &amp; Duration</div>
                            <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                                <i class="far fa-clock text-primary"></i> <span id="dior-tele-modal-time">—</span> <span class="text-secondary small fw-normal" id="dior-tele-modal-duration">(30 mins)</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Consultation Type</div>
                            <div class="fw-bold text-dark fs-6" id="dior-tele-modal-type">Video Visit (HD)</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Secure Channel</div>
                            <div class="fw-bold text-success fs-6"><i class="fas fa-lock me-1"></i> HIPAA Encrypted Telehealth</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4 fw-semibold" data-bs-dismiss="modal" onclick="diorCloseTelemedicineModal()">Close</button>
                    <a href="#" id="dior-tele-modal-join-btn" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm" target="_blank" rel="noopener">
                        <i class="fas fa-video me-1"></i> Join Call
                    </a>
                </div>
            </div>
        </div>
    </div>

</section>
<!-- ============================================================== -->
<!-- 5. MEDICAL RECORD TAB -->
<!-- ============================================================== -->