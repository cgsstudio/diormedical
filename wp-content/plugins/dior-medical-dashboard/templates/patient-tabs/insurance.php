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

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-3 px-md-4 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-patient-tab="payments" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('payments');">
            <i class="fas fa-wallet"></i> Billing & Payments
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-patient-tab="insurance" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('insurance');">
            <i class="fas fa-shield-heart"></i> Insurance Claims
        </button>
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
                            <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                                <input type="text" id="dior-bill-search-input" class="form-control border-start-0 ps-0"
                                    placeholder="Search records..." aria-label="Search box" onkeyup="diorFilterBill()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Delete selected items"
                                    class="docs-icon-btn docs-btn-danger btn" title="Delete Selected"
                                    onclick="diorBulkDeleteStaticRows(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button type="button" aria-label="Add new record"
                                    class="docs-icon-btn docs-btn-primary btn" title="Add Insurance Policy">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="docs-icon-btn docs-btn-success btn"
                                    title="Export to CSV" onclick="diorDownloadInsuranceCSV()">
                                    <i class="fas fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info btn"
                                    title="Refresh Page" onclick="window.location.reload()">
                                    <i class="fas fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="va-table table table-hover" id="dior-insurance-table">
                        <thead>
                            <tr>
                                <th class="dior-ic-3fa4d8d717">
                                    <input type="checkbox" class="dior-ic-52ff4d551f form-check-input">
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
                            $user_id = !empty($user_id) ? (int)$user_id : get_current_user_id();
                            $insurance_claims = get_user_meta($user_id, 'dior_insurance_claims', true);
                            $insurance_claims = is_array($insurance_claims) ? $insurance_claims : [];
                            ?>
                            <?php if (!empty($insurance_claims)): ?>
                                <?php foreach ($insurance_claims as $claim): ?>
                                    <tr>
                                        <td><input type="checkbox" class="dior-ic-52ff4d551f form-check-input"></td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['claim_id'] ?? '—'); ?></span>
                                        </td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['policy_id'] ?? '—'); ?></span>
                                        </td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['claim_date'] ?? '—'); ?></span>
                                        </td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['claim_type'] ?? '—'); ?></span>
                                        </td>
                                        <td><span
                                                class="cell-text"><?php echo esc_html($claim['claim_amount'] ?? '0'); ?></span>
                                        </td>
                                        <td><span
                                                class="cell-text"><?php echo esc_html($claim['approved_amount'] ?? '0'); ?></span>
                                        </td>
                                        <td><span class="cell-text"><?php echo esc_html($claim['submitted'] ?? '—'); ?></span>
                                        </td>
                                        <td>
                                            <div class="cell-content">
                                                <div class="badge-solid col-indigo">
                                                    <?php echo esc_html($claim['status'] ?? 'Under Review'); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cell-actions"><button type="button" class="action-icon-btn edit-btn btn"
                                                    title="View"><i class="fas fa-eye text-primary"></i></button></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="dior-empty-state">No insurance claims available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-insurance-page-count">0 selected /
                        <?php echo count($insurance_claims); ?> total</span>

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