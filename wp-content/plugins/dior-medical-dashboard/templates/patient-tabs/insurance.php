<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-insurance">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Insurance & Claims</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
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
                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-insurance-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterInsurance()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary dior-ic-54410f9b78">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadInsuranceCSV()">
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
                    <table class="va-table" id="dior-insurance-table">
                        <thead>
                            <tr>
                                <th class="dior-ic-3fa4d8d717">
                                    <input type="checkbox" class="dior-ic-52ff4d551f">
                                </th>
                                <th>CLAIM ID <i class="fa-solid fa-sort"></i></th>
                                <th>POLICY ID <i class="fa-solid fa-sort"></i></th>
                                <th>CLAIM DATE <i class="fa-solid fa-sort"></i></th>
                                <th>CLAIM TYPE <i class="fa-solid fa-sort"></i></th>
                                <th>CLAIM AMOUNT ($) <i class="fa-solid fa-sort"></i></th>
                                <th>APPROVED ($) <i class="fa-solid fa-sort"></i></th>
                                <th>SUBMITTED <i class="fa-solid fa-sort"></i></th>
                                <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-insurance-tbody">
                            <?php
                            $insurance_claims = get_user_meta($user_id, 'dior_insurance_claims', true);
                            $insurance_claims = is_array($insurance_claims) ? $insurance_claims : [];
                            if (empty($insurance_claims) && !empty($dior_demo_mode)) {
                                $insurance_claims = [['claim_id'=>'DEMO-CLM-001','policy_id'=>'DEMO-POL-001','claim_date'=>current_time('Y-m-d'),'claim_type'=>'Consultation','provider'=>'Dior Medical','submitted'=>'Submitted','status'=>'In Review','amount'=>'$120']];
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
                                        <td><div class="cell-actions"><button type="button" class="action-icon-btn edit-btn" title="View"><i class="fa-regular fa-eye"></i></button></div></td>
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
