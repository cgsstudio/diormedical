<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-documents">
    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">My Documents &amp; Reports</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-docs-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterDocs()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary dior-ic-54410f9b78">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadDocsCSV()">
                                    <i class="fa-solid fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="master-modern-table" id="dior-docs-table">
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
                                            <?php if (!empty($doc['id'])): ?><button type="button" class="action-icon-btn edit-btn" title="View"><i class="fa-regular fa-eye"></i></button><?php endif; ?>
                                        </div></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="dior-empty-state">No medical documents available.</td></tr>
                            <?php endif; ?>
</tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-docs-page-count">0 selected / <?php echo count($documents); ?> total</span>
                    
                    <div class="master-pagination" id="dior-docs-pagination">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</section>
