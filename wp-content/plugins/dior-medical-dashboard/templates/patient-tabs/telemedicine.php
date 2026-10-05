<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-telemedicine">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Telemedicine</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Telemedicine</span></li>
            </ul>
        </div>
    </div>

    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">Telemedicine / Video Consultations</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-tele-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterTele()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary dior-ic-54410f9b78">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadTeleCSV()">
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
                    <table class="va-table" id="dior-tele-table">
                        <thead>
                            <tr>
                                <th class="dior-ic-3fa4d8d717">
                                    <input type="checkbox" class="dior-ic-52ff4d551f">
                                </th>
                                <th>SESSION ID <i class="fa-solid fa-sort"></i></th>
                                <th>DOCTOR <i class="fa-solid fa-sort"></i></th>
                                <th>SPECIALTY <i class="fa-solid fa-sort"></i></th>
                                <th>DATE <i class="fa-solid fa-sort"></i></th>
                                <th>TIME <i class="fa-solid fa-sort"></i></th>
                                <th>DURATION <i class="fa-solid fa-sort"></i></th>
                                <th>TYPE <i class="fa-solid fa-sort"></i></th>
                                <th>STATUS <i class="fa-solid fa-sort"></i></th>
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
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="dior-ic-52ff4d551f"></td>
                                        <td><span class="cell-text"><?php echo esc_html($tele_id ?: '—'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($apt['provider'] ?? ($apt['doctor_name'] ?? 'Attending Physician')); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($apt['provider_spec'] ?? 'Telehealth Physician'); ?></span></td>
                                        <td><div class="cell-content cell-icon-text"><i class="material-icons-outlined cell-icon">calendar_today</i><span class="cell-text"><?php echo esc_html($tele_date); ?></span></div></td>
                                        <td><span class="cell-text"><?php echo esc_html($tele_time); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($apt['duration'] ?? '30 minutes'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($apt['type'] ?? ($apt['visit_type'] ?? 'Video Visit')); ?></span></td>
                                        <td><div class="cell-content"><div class="badge-solid <?php echo esc_attr($tele_class); ?>"><?php echo esc_html($tele_status); ?></div></div></td>
                                        <td><div class="cell-actions">
                                            <button type="button" class="action-icon-btn edit-btn" title="View Session" onclick="diorViewTelemedicine(this)"><i class="fa-regular fa-eye"></i></button>
                                            <?php if (!in_array(strtolower($tele_status), ['completed','cancelled','no show'], true)): ?>
                                                <?php if (!empty($apt['join_url'])): ?><a class="action-icon-btn" title="Join Consultation" href="<?php echo esc_url($apt['join_url']); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-video"></i></a><?php else: ?><button type="button" class="action-icon-btn" title="Join Consultation" onclick="diorViewTelemedicine(this)"><i class="fa-solid fa-video"></i></button><?php endif; ?>
                                            <?php endif; ?>
                                            <button type="button" class="action-icon-btn delete-btn" title="Cancel Session" onclick="diorDeleteStaticRow(this, 'telemedicine')"><i class="fa-solid fa-xmark"></i></button>
                                        </div></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="dior-empty-state">No telemedicine sessions available.</td></tr>
                            <?php endif; ?>
</tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-tele-page-count">0 selected / <?php echo count($appointments); ?> total</span>
                    
                    <div class="master-pagination" id="dior-tele-pagination">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</section>
                <!-- ============================================================== -->
                <!-- 5. MEDICAL RECORD TAB -->
                <!-- ============================================================== -->
