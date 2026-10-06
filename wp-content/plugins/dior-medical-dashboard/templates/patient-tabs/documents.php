<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-documents">
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

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2 class="table-title">My Documents &amp; Reports</h2>
                <div class="docs-title-line"></div>
            </div>
            <div class="docs-actions-wrapper">
                <div class="docs-search-box">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="dior-docs-search-input" placeholder="Search records..."
                        aria-label="Search box" onkeyup="diorFilterDocs()">
                </div>
                <div class="docs-actions-group">
                    <button type="button" aria-label="Add new record"
                        class="docs-icon-btn docs-btn-primary dior-ic-54410f9b78" onclick="diorOpenDocumentUploadModal()" title="Add Document">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg>
                    </button>
                    <button type="button" aria-label="Export to CSV" class="docs-icon-btn docs-btn-success"
                        title="Export to CSV" onclick="diorDownloadDocsCSV()">
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
            <table class="docs-table" id="dior-docs-table">
                <thead>
                    <tr>
                        <th class="dior-ic-3fa4d8d717">
                            <input type="checkbox" class="dior-ic-52ff4d551f">
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
                            <tr data-doc-id="<?php echo esc_attr($doc['id'] ?? ''); ?>" data-doc-author-id="<?php echo esc_attr($doc['author_id'] ?? 0); ?>">
                                <td><input type="checkbox" class="dior-ic-52ff4d551f"></td>
                                <td><span
                                        class="cell-text dior-ic-794116ec9b"><?php echo esc_html($doc['title'] ?? 'Medical Document'); ?></span>
                                </td>
                                <td><span
                                        class="cell-text"><?php echo esc_html($doc['category'] ?? 'Clinical Record'); ?></span>
                                </td>
                                <td><span class="cell-text"><?php echo esc_html($doc['file_type'] ?? 'PDF'); ?></span></td>
                                <td>
                                    <div class="cell-content cell-icon-text"><i
                                            class="far fa-calendar cell-icon"></i><span
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
                                            <button type="button" class="action-icon-btn edit-btn" title="Edit Document"
                                                data-doc-title="<?php echo esc_attr($doc['title'] ?? 'Medical Document'); ?>" data-doc-category="<?php echo esc_attr($doc['category'] ?? 'Clinical Record'); ?>" onclick="diorEditDocument(this)"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                            <button type="button" class="action-icon-btn delete-btn" title="Delete Document"
                                                onclick="diorDeleteStaticRow(this, 'document')"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
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
        <div class="docs-pagination-container">
            <span class="docs-showing-text" id="dior-docs-page-count">0 selected / <?php echo count($documents); ?>
                total</span>
            <div id="dior-docs-pagination"></div>
        </div>
    </div>


<!-- Patient document upload/edit modal -->
<div id="dior-document-modal" class="dior-static-modal-backdrop" aria-hidden="true">
    <div class="dior-document-modal" role="dialog" aria-modal="true" aria-labelledby="dior-document-modal-title">
        <button type="button" class="dior-document-modal-close" onclick="diorCloseDocumentModal()" aria-label="Close">&times;</button>
        <h3 id="dior-document-modal-title">Add Medical Document</h3>
        <form id="dior-document-form" enctype="multipart/form-data">
            <input type="hidden" id="dior-document-id" name="doc_id" value="">
            <div class="dior-document-form-grid">
                <label>Document Title<input type="text" id="dior-document-title" name="doc_title" required placeholder="e.g. Blood Test Report"></label>
                <label>Category<select id="dior-document-category" name="doc_category">
                    <option value="Clinical Record">Clinical Record</option>
                    <option value="Laboratory">Laboratory</option>
                    <option value="Diagnostic & Scans">Diagnostic &amp; Scans</option>
                    <option value="Prescription">Prescription</option>
                    <option value="Procedure">Procedure</option>
                    <option value="Other">Other</option>
                </select></label>
            </div>
            <div id="dior-document-file-wrap">
                <label>Document File<input type="file" id="dior-document-file" name="doc_file" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx"></label>
                <small>Allowed: PDF, PNG, JPG, DOC, DOCX. Maximum 25MB.</small>
            </div>
            <div class="dior-document-modal-actions">
                <button type="button" class="btn btn-light" onclick="diorCloseDocumentModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="dior-document-save-btn"><i class="fas fa-cloud-arrow-up"></i> Upload Document</button>
            </div>
        </form>
    </div>
</div>

</section>