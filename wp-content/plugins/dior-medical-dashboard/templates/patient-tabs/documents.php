<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-documents">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">My Documents &amp; Reports</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>My Documents &amp; Reports</span></li>
            </ul>
        </div>
    </div>

    <div class="docs-card">
                <div class="docs-header-container">
                    <div class="docs-title-box">
                        <h2>My Documents &amp; Reports</h2>
                        <div class="docs-title-line"></div>
                    </div>
                    <div class="docs-actions-wrapper">
                        <div class="docs-search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="dior-docs-search-input" placeholder="Search records..." aria-label="Search box" onkeyup="diorFilterDocs()">
                        </div>
                        <div class="docs-actions-group">
                            <button type="button" aria-label="Add new record" class="docs-icon-btn docs-btn-primary dior-ic-54410f9b78">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                            <button type="button" aria-label="Export to CSV" class="docs-icon-btn docs-btn-success" title="Export to CSV" onclick="diorDownloadDocsCSV()">
                                <i class="fa-solid fa-file-arrow-down"></i>
                            </button>
                            <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                <i class="fa-solid fa-rotate-right"></i>
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
                                <th>DOCUMENT TITLE <i class="fa-solid fa-sort"></i></th>
                                <th>CATEGORY <i class="fa-solid fa-sort"></i></th>
                                <th>TYPE <i class="fa-solid fa-sort"></i></th>
                                <th>UPLOAD DATE <i class="fa-solid fa-sort"></i></th>
                                <th>SIZE <i class="fa-solid fa-sort"></i></th>
                                <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-docs-tbody">
                            <?php if (!empty($documents)): ?>
                                <?php foreach ($documents as $doc): ?>
                                    <tr data-doc-id="<?php echo esc_attr($doc['id'] ?? ''); ?>">
                                        <td><input type="checkbox" class="dior-ic-52ff4d551f"></td>
                                        <td><span class="cell-text dior-ic-794116ec9b"><?php echo esc_html($doc['title'] ?? 'Medical Document'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($doc['category'] ?? 'Clinical Record'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($doc['file_type'] ?? 'PDF'); ?></span></td>
                                        <td><div class="cell-content cell-icon-text"><i class="fa-regular fa-calendar cell-icon"></i><span class="cell-text"><?php echo esc_html($doc['date'] ?? '—'); ?></span></div></td>
                                        <td><span class="cell-text"><?php echo esc_html($doc['size'] ?? '—'); ?></span></td>
                                        <td><div class="cell-content"><div class="badge-solid col-green">Available</div></div></td>
                                        <td><div class="cell-actions">
                                            <?php if (!empty($doc['id'])): ?>
                                                <button type="button" class="action-icon-btn edit-btn" title="Edit Document" onclick="diorEditDocument(this)"><i class="fa-solid fa-pen"></i></button>
                                                <button type="button" class="action-icon-btn delete-btn" title="Delete Document" onclick="diorDeleteStaticRow(this, 'document')"><i class="fa-solid fa-trash"></i></button>
                                            <?php endif; ?>
                                        </div></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="dior-empty-state">No medical documents available.</td></tr>
                            <?php endif; ?>
</tbody>
                    </table>
                </div>
                <div class="docs-pagination-container">
                    <span class="docs-showing-text" id="dior-docs-page-count">0 selected / <?php echo count($documents); ?> total</span>
                    <div id="dior-docs-pagination"></div>
                </div>
    </div>
    
</section>
