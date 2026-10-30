<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-documents">
    <?php
    $docs_user_id = get_current_user_id();
    $documents = Dior_Patient_Portal_Data::get_patient_documents($docs_user_id);
    if (!is_array($documents)) {
        $documents = [];
    }
    ?>
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">My Documents &amp; Reports</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>My Documents &amp; Reports</span></li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-3 px-md-4 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-patient-tab="docs_meds" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('docs_meds');">
            <i class="fas fa-prescription-bottle-medical"></i> Prescriptions
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-patient-tab="documents" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('documents');">
            <i class="fas fa-file-lines"></i> Documents & Report
        </button>
    </div>

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2 class="table-title">My Documents &amp; Reports</h2>
                <div class="docs-title-line"></div>
            </div>
            <div class="docs-actions-wrapper">
                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                    <input type="text" id="dior-docs-search-input" class="form-control border-start-0 ps-0"
                        placeholder="Search records..." aria-label="Search box" onkeyup="diorFilterDocs()">
                </div>
                <div class="docs-actions-group">
                    <button type="button" aria-label="Delete selected items" class="docs-icon-btn docs-btn-danger btn"
                        title="Delete Selected" onclick="diorBulkDeleteStaticRows(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button type="button" aria-label="Add new record"
                        class="docs-icon-btn docs-btn-primary btn" onclick="diorOpenDocumentUploadModal()"
                        title="Add Document">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" aria-label="Export to CSV" class="docs-icon-btn docs-btn-success btn"
                        title="Export to CSV" onclick="diorDownloadDocsCSV()">
                        <i class="fas fa-file-arrow-down"></i>
                    </button>
                    <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info btn" title="Refresh Page"
                        onclick="window.location.reload()">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Category Filter Pills -->
        <div class="px-3 pt-3 pb-2 border-bottom d-flex flex-wrap gap-2 align-items-center">
            <span class="small fw-bold text-secondary text-uppercase me-2"><i class="fas fa-filter text-primary me-1"></i> Filter:</span>
            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 dior-doc-cat-pill active" onclick="diorFilterDocsByCategory('all', this)">All</button>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 dior-doc-cat-pill" onclick="diorFilterDocsByCategory('Signed Consent', this)"><i class="fas fa-file-signature me-1"></i>Signed Consent Forms</button>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 dior-doc-cat-pill" onclick="diorFilterDocsByCategory('Clinical Record', this)">Clinical Records</button>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 dior-doc-cat-pill" onclick="diorFilterDocsByCategory('Laboratory', this)">Lab &amp; Diagnostics</button>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 dior-doc-cat-pill" onclick="diorFilterDocsByCategory('Prescription', this)">Prescriptions</button>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 dior-doc-cat-pill" onclick="diorFilterDocsByCategory('Other', this)">Other</button>
        </div>

        <div class="docs-table-wrapper">
            <table class="docs-table table table-hover" id="dior-docs-table">
                <thead>
                    <tr>
                        <th class="dior-ic-3fa4d8d717">
                            <input type="checkbox" class="dior-ic-52ff4d551f form-check-input">
                        </th>
                        <th>DOCUMENT TITLE <i class="fas fa-sort"></i></th>
                        <th>CATEGORY <i class="fas fa-sort"></i></th>
                        <th>TYPE <i class="fas fa-sort"></i></th>
                        <th>UPLOAD DATE <i class="fas fa-sort"></i></th>
                        <th>SIZE <i class="fas fa-sort"></i></th>
                        <th>STATUS <i class="fas fa-sort"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="dior-docs-tbody">
                    <?php if (!empty($documents)): ?>
                        <?php foreach ($documents as $doc): ?>
                            <tr data-doc-id="<?php echo esc_attr($doc['id'] ?? ''); ?>"
                                data-category="<?php echo esc_attr($doc['category'] ?? 'Clinical Record'); ?>"
                                data-doc-author-id="<?php echo esc_attr($doc['author_id'] ?? 0); ?>">
                                <td><input type="checkbox" class="dior-ic-52ff4d551f form-check-input"></td>
                                <td><span
                                        class="cell-text dior-ic-794116ec9b"><?php echo esc_html($doc['title'] ?? 'Medical Document'); ?></span>
                                </td>
                                <td><span
                                        class="cell-text"><?php echo esc_html($doc['category'] ?? 'Clinical Record'); ?></span>
                                </td>
                                <td><span class="cell-text"><?php echo esc_html($doc['file_type'] ?? 'PDF'); ?></span></td>
                                <td>
                                    <div class="cell-content cell-icon-text"><i class="far fa-calendar cell-icon"></i><span
                                            class="cell-text"><?php echo esc_html($doc['date'] ?? '—'); ?></span></div>
                                </td>
                                <td><span class="cell-text"><?php echo esc_html($doc['size'] ?? '—'); ?></span></td>
                                <td>
                                    <div class="cell-content">
                                        <div class="badge-solid col-green">Available</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        <?php if (!empty($doc['id'])): ?>
                                            <a href="<?php echo esc_url(add_query_arg(['dior_action' => 'download_doc', 'doc_id' => $doc['id'], 'patient_id' => $docs_user_id], home_url('/'))); ?>" 
                                               target="_blank" class="action-icon-btn btn" title="Download Document" download>
                                                <i class="fas fa-file-arrow-down text-primary"></i>
                                            </a>
                                            <button type="button" class="action-icon-btn edit-btn btn" title="Edit Document"
                                                data-doc-title="<?php echo esc_attr($doc['title'] ?? 'Medical Document'); ?>"
                                                data-doc-category="<?php echo esc_attr($doc['category'] ?? 'Clinical Record'); ?>"
                                                onclick="diorEditDocument(this)">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <button type="button" class="action-icon-btn delete-btn btn" title="Delete Document"
                                                onclick="diorDeleteStaticRow(this, 'document')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="dior-empty-state">No medical documents available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>


    <!-- Patient document upload/edit modal (Strict Bootstrap 5 Standard) -->
    <div class="modal fade" id="dior-document-modal" tabindex="-1" aria-labelledby="dior-document-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                    <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="dior-document-modal-title">
                        <i class="fas fa-file-medical text-primary me-2"></i> Add Medical Document
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="diorCloseDocumentModal()"></button>
                </div>
                <form id="dior-document-form" enctype="multipart/form-data">
                    <div class="modal-body p-4 bg-white">
                        <input type="hidden" id="dior-document-id" name="doc_id" value="">
                        <input type="hidden" id="dior-document-patient-id" name="patient_id" value="<?php echo esc_attr($docs_user_id); ?>">
                        
                        <p class="text-secondary small mb-3">Upload lab results, clinical records, signed consent forms, or medical scans securely to your portal.</p>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="dior-document-title" class="form-label small text-uppercase text-secondary fw-semibold mb-1">
                                    Document Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="dior-document-title" name="doc_title" required
                                    placeholder="e.g. Blood Test Report" class="form-control rounded-3 py-2">
                            </div>
                            <div class="col-md-5">
                                <label for="dior-document-category" class="form-label small text-uppercase text-secondary fw-semibold mb-1">
                                    Category <span class="text-danger">*</span>
                                </label>
                                <select id="dior-document-category" name="doc_category" class="form-select rounded-3 py-2">
                                    <option value="Clinical Record">Clinical Record</option>
                                    <option value="Signed Consent Form">Signed Consent Form</option>
                                    <option value="Laboratory">Laboratory</option>
                                    <option value="Diagnostic & Scans">Diagnostic &amp; Scans</option>
                                    <option value="Prescription">Prescription</option>
                                    <option value="Procedure">Procedure</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="col-12" id="dior-document-file-wrap">
                                <label for="dior-document-file" class="form-label small text-uppercase text-secondary fw-semibold mb-1">
                                    Document File <span class="text-danger">*</span>
                                </label>
                                <div class="border rounded-3 p-3 bg-light text-center">
                                    <input type="file" id="dior-document-file" name="doc_file"
                                        accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" class="form-control mb-2"
                                        onchange="diorUpdateDocFileName(this)">
                                    <div class="small text-muted d-flex align-items-center justify-content-center gap-1">
                                        <i class="fas fa-circle-info text-primary"></i> Supported files: PDF, PNG, JPG, DOC, DOCX (Max 25MB)
                                    </div>
                                    <div id="dior-doc-file-label" class="fw-semibold text-primary mt-1 small"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary rounded-3 px-4 fw-semibold" data-bs-dismiss="modal" onclick="diorCloseDocumentModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm" id="dior-document-save-btn">
                            <i class="fas fa-cloud-arrow-up me-1"></i> Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        var activeCategoryFilter = 'all';

        window.diorFilterDocsByCategory = function(cat, btn) {
            activeCategoryFilter = cat.toLowerCase();
            document.querySelectorAll('.dior-doc-cat-pill').forEach(function(b) {
                b.classList.remove('btn-primary', 'active');
                b.classList.add('btn-outline-primary');
            });
            if (btn) {
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-primary', 'active');
            }
            diorFilterDocs();
        };

        window.diorFilterDocs = function() {
            var searchInput = document.getElementById('dior-docs-search-input');
            var q = (searchInput ? searchInput.value : '').toLowerCase().trim();
            var rows = document.querySelectorAll('#dior-docs-tbody tr');
            rows.forEach(function(r) {
                if (r.querySelector('.dior-empty-state')) return;
                var text = r.textContent.toLowerCase();
                var cat = (r.getAttribute('data-category') || '').toLowerCase();
                var matchesCat = (activeCategoryFilter === 'all') || 
                                 (cat.indexOf(activeCategoryFilter) !== -1) || 
                                 (activeCategoryFilter.indexOf('signed') !== -1 && (cat.indexOf('consent') !== -1 || text.indexOf('consent') !== -1));
                var matchesQuery = !q || text.indexOf(q) !== -1;
                r.style.display = (matchesCat && matchesQuery) ? '' : 'none';
            });
        };

        window.diorUpdateDocFileName = function (input) {
            var label = document.getElementById('dior-doc-file-label');
            if (!label) return;
            if (input && input.files && input.files[0]) {
                label.innerHTML = '<i class="fas fa-circle-check text-success me-1"></i> Selected: <strong>' + input.files[0].name + '</strong> (' + Math.round(input.files[0].size / 1024) + ' KB)';
            } else {
                label.textContent = '';
            }
        };
    </script>

</section>