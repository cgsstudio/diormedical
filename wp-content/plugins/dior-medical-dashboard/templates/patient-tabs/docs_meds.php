<section class="dior-tab-panel" id="tab-docs_meds">
                    <div class="dior-page-header-box">
                        <div>
                            <h2>Medical Documents & Active Prescriptions</h2>
                        </div>
                        <div class="dior-header-action-group">
                            <button type="button" class="dior-btn-gold-secondary dior-ic-9a404bf6a0"
                                onclick="diorOpenModal('modal-change-pharmacy')">
                                <i class="fa-solid fa-store"></i> Update Pharmacy Preference
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
                                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                                <input type="text" id="dior-rx-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterRx()">
                                            </div>
                                            <div class="action-buttons">
                                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary">
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadRxCSV()">
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
                                    <table class="master-modern-table" id="dior-rx-table">
                                        <thead>
                                            <tr>
                                                <th>ID <i class="fa-solid fa-sort"></i></th>
                                                <th>TITLE <i class="fa-solid fa-sort"></i></th>
                                                <th>CREATED BY <i class="fa-solid fa-sort"></i></th>
                                                <th>DATE <i class="fa-solid fa-sort"></i></th>
                                                <th>DISEASES <i class="fa-solid fa-sort"></i></th>
                                                <th>ACTIONS <i class="fa-solid fa-sort"></i></th>
                                            </tr>
                                        </thead>
                                        <tbody id="dior-rx-tbody">
                                            <?php
                                            global $wpdb;
                                            $current_user = wp_get_current_user();
                                            $patient_rx_rows = $wpdb->get_results($wpdb->prepare(
                                                "SELECT * FROM {$wpdb->prefix}dior_prescriptions WHERE patient_id = %s OR patient_id = %d OR patient_name = %s ORDER BY id DESC",
                                                (string)$current_user->ID, $current_user->ID, $current_user->display_name
                                            ), ARRAY_A);
                                            
                                            foreach ($patient_rx_rows as $rx):
                                                $rx_id = $rx['prescription_id'] ?? '—';
                                                $rx_name = $rx['medications'] ?? 'Prescription';
                                                $rx_doctor = $rx['doctor_name'] ?? 'Attending Physician';
                                                $rx_date = $rx['prescription_date'] ?? '—';
                                                $rx_disease = $rx['dosage'] ?? '—';
                                            ?>
                                            <tr>
                                                <td><span class="cell-text"><?php echo esc_html($rx_id); ?></span></td>
                                                <td><span class="cell-text"><?php echo esc_html($rx_name); ?></span></td>
                                                <td><span class="cell-text"><?php echo esc_html($rx_doctor); ?></span></td>
                                                <td><div class="cell-content cell-icon-text"><i class="fa-regular fa-calendar cell-icon"></i><span class="cell-text"><?php echo esc_html($rx_date); ?></span></div></td>
                                                <td><span class="cell-text"><?php echo esc_html($rx_disease); ?></span></td>
                                                <td><div class="cell-content action-cell">
                                                    <button type="button" class="action-btn action-btn-info" title="Download" onclick="diorDownloadRxPdf('<?php echo esc_js($rx_id); ?>')"><i class="fa-solid fa-download"></i></button>
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
