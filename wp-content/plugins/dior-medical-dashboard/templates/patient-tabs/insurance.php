<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-insurance">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Insurance & Claims</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Insurance & Claims</span></li>
            </ul>
        </div>
    </div>

    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">Insurance & Claims</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fas fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-insurance-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterInsurance()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary dior-ic-54410f9b78">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadInsuranceCSV()">
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
                    <table class="va-table" id="dior-insurance-table">
                        <thead>
                            <tr>
                                <th class="dior-ic-3fa4d8d717">
                                    <input type="checkbox" class="dior-ic-52ff4d551f">
                                </th>
                                <th>CLAIM ID <i class="fas fa-sort"></i></th>
                                <th>POLICY ID <i class="fas fa-sort"></i></th>
                                <th>CLAIM DATE <i class="fas fa-sort"></i></th>
                                <th>CLAIM TYPE <i class="fas fa-sort"></i></th>
                                <th>CLAIM AMOUNT ($) <i class="fas fa-sort"></i></th>
                                <th>APPROVED ($) <i class="fas fa-sort"></i></th>
                                <th>SUBMITTED <i class="fas fa-sort"></i></th>
                                <th>STATUS <i class="fas fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-insurance-tbody">
                            <?php
                            if (empty($dior_patient_design_static)) {
                                $insurance_claims = get_user_meta($user_id, 'dior_insurance_claims', true);
                                $insurance_claims = is_array($insurance_claims) ? $insurance_claims : [];
                            }
                            ?>
                            <?php if (!empty($insurance_claims)): ?>
                                <?php foreach ($insurance_claims as $claim): ?>
                                    <tr>
                                        <td><input type="checkbox" class="dior-ic-52ff4d551f"></td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['claim_id'] ?? '—'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['policy_id'] ?? '—'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['claim_date'] ?? '—'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['claim_type'] ?? '—'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['claim_amount'] ?? '0'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['approved_amount'] ?? '0'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['submitted'] ?? '—'); ?></span></td>
                                        <td><div class="cell-content"><div class="badge-solid col-indigo"><?php echo esc_html($claim['status'] ?? 'Under Review'); ?></div></div></td>
                                        <td><div class="cell-actions"><button type="button" class="action-icon-btn edit-btn" title="View"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3b82f6" class="bi bi-eye" viewBox="0 0 16 16" style="background:transparent;"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg></button></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="dior-empty-state">No insurance claims available.</td></tr>
                            <?php endif; ?>
</tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-insurance-page-count">0 selected / <?php echo count($insurance_claims); ?> total</span>
                    
                    <div class="master-pagination" id="dior-insurance-pagination">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</section>
                <!-- ============================================================== -->
                <!-- 4. DOCS & MEDS (Documents + Prescriptions) TAB -->
                <!-- ============================================================== -->
