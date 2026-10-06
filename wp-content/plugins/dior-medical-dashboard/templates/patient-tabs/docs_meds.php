<section class="dior-tab-panel" id="tab-docs_meds">
                    <div class="dior-page-header-box">
                        <div>
                            <h2>Medical Documents & Active Prescriptions</h2>
                        </div>
                        <div class="dior-header-action-group">
                            <button type="button" class="dior-btn-gold-secondary dior-ic-9a404bf6a0"
                                onclick="diorOpenModal('modal-change-pharmacy')">
                                <i class="fas fa-store"></i> Update Pharmacy Preference
                            </button>
                        </div>
                    </div>

                    <!-- Prescriptions Section -->
                    <!-- Prescriptions Section -->
                    <div class="master-table-wrapper">
                        <div class="master-table-container">
                            <div class="master-table-card">
                                <div class="master-table-header">
                                    <div class="header-content">
                                        <div class="table-title-section">
                                            <h2 class="table-title">Prescriptions</h2>
                                            <div class="title-accent"></div>
                                        </div>
                                        <div class="header-actions-group">
                                            <div class="search-container">
                                                <i class="fas fa-magnifying-glass search-icon"></i>
                                                <input type="text" id="dior-rx-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterRx()">
                                            </div>
                                            <div class="action-buttons">
                                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg>
                                                </button>
                                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadRxCSV()">
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
                                    <table class="master-modern-table" id="dior-rx-table">
                                        <thead>
                                            <tr>
                                                <th>ID <i class="fas fa-sort"></i></th>
                                                <th>TITLE <i class="fas fa-sort"></i></th>
                                                <th>CREATED BY <i class="fas fa-sort"></i></th>
                                                <th>DATE <i class="fas fa-sort"></i></th>
                                                <th>DISEASES <i class="fas fa-sort"></i></th>
                                                <th>ACTIONS <i class="fas fa-sort"></i></th>
                                            </tr>
                                        </thead>
                                        <tbody id="dior-rx-tbody">
                                            <?php
                                            $patient_rx_rows = is_array($prescriptions ?? []) ? $prescriptions : [];
                                            foreach ($patient_rx_rows as $rx):
                                                $rx_id = $rx['id'] ?? ($rx['order_id'] ?? '—');
                                                $rx_name = $rx['name'] ?? ($rx['medication'] ?? 'Prescription');
                                                $rx_doctor = $rx['prescribed_by'] ?? 'Attending Physician';
                                                $rx_date = $rx['date_prescribed'] ?? ($rx['date'] ?? '—');
                                                $rx_disease = $rx['condition'] ?? ($rx['diagnosis'] ?? ($rx['notes'] ?? '—'));
                                            ?>
                                            <tr>
                                                <td><span class="cell-text"><?php echo esc_html($rx_id); ?></span></td>
                                                <td><span class="cell-text"><?php echo esc_html($rx_name); ?></span></td>
                                                <td><span class="cell-text"><?php echo esc_html($rx_doctor); ?></span></td>
                                                <td><div class="cell-content cell-icon-text"><i class="far fa-calendar cell-icon"></i><span class="cell-text"><?php echo esc_html($rx_date); ?></span></div></td>
                                                <td><span class="cell-text"><?php echo esc_html($rx_disease); ?></span></td>
                                                <td><div class="cell-content action-cell">
                                                    <button type="button" class="action-btn action-btn-info" title="Download" onclick="diorDownloadRxPdf('<?php echo esc_js($rx_id); ?>')"><i class="fas fa-download"></i></button>
                                                </div></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($patient_rx_rows)): ?>
                                                <tr><td colspan="6" class="dior-empty-state">No prescriptions available.</td></tr>
                                            <?php endif; ?>
</tbody>
                                    </table>
                                </div>
                                <div class="master-table-footer">
                                    <span class="page-count" id="dior-rx-page-count">0 selected / <?php echo count($patient_rx_rows); ?> total</span>
                                    
                                    <div class="master-pagination" id="dior-rx-pagination">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                                            
                </section>
