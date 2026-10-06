<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-emergency">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Emergency Contacts</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Emergency Contacts</span></li>
            </ul>
        </div>
    </div>

    
    <!-- Top Action Cards -->
    <div class="dior-ic-597dc7a00c">
        <!-- Request Ambulance -->
        <div class="dior-ic-f5e657b8f6">
            <div class="dior-ic-050317ce29">
                <i class="fa-solid fa-truck-medical"></i>
            </div>
            <h4 class="dior-ic-4f9a9fe05d">Request Ambulance</h4>
            <p class="dior-ic-b4344ed8fa">Immediate emergency assistance</p>
            <button type="button" onmouseover="this.style.background='#EF4444'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='#EF4444';" class="dior-ic-7f1daa7fb4">Call Now</button>
        </div>
        
        <!-- Emergency Doctor -->
        <div class="dior-ic-f5e657b8f6">
            <div class="dior-ic-d1eca3c91b">
                <i class="fa-solid fa-user-doctor"></i>
            </div>
            <h4 class="dior-ic-4f9a9fe05d">Emergency Doctor</h4>
            <p class="dior-ic-b4344ed8fa">Connect with available doctors</p>
            <button type="button" onmouseover="this.style.background='#F59E0B'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='#F59E0B';" class="dior-ic-a0664572fd">Contact</button>
        </div>
        
        <!-- Digital Health Card -->
        <div class="dior-ic-f5e657b8f6">
            <div class="dior-ic-12cb12d607">
                <i class="fa-solid fa-id-card"></i>
            </div>
            <h4 class="dior-ic-4f9a9fe05d">Digital Health Card</h4>
            <p class="dior-ic-b4344ed8fa">Access your medical records</p>
            <button type="button" onmouseover="this.style.background='#14B8A6'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='#14B8A6';" class="dior-ic-6713853875">View Card</button>
        </div>
    </div>

    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">Emergency Contacts</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-emerg-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterEmerg()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary dior-ic-54410f9b78">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadEmergCSV()">
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
                    <table class="va-table" id="dior-emerg-table">
                        <thead>
                            <tr>
                                <th class="dior-ic-3fa4d8d717">
                                    <input type="checkbox" class="dior-ic-52ff4d551f">
                                </th>
                                <th>CONTACT NAME <i class="fa-solid fa-sort"></i></th>
                                <th>RELATION <i class="fa-solid fa-sort"></i></th>
                                <th>PHONE NUMBER <i class="fa-solid fa-sort"></i></th>
                                <th>PRIORITY <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-emerg-tbody">
                            <?php if (!empty($profile['emergency_name']) || !empty($profile['emergency_phone'])): ?>
                                <tr>
                                    <td><input type="checkbox" class="dior-ic-52ff4d551f"></td>
                                    <td><span class="cell-text"><?php echo esc_html($profile['emergency_name'] ?: 'Emergency Contact'); ?></span></td>
                                    <td><span class="cell-text"><?php echo esc_html($profile['emergency_relation'] ?: '—'); ?></span></td>
                                    <td><div class="cell-content cell-icon-text"><i class="material-icons-outlined cell-icon">phone</i><span class="cell-text"><?php echo esc_html($profile['emergency_phone'] ?: '—'); ?></span></div></td>
                                    <td><div class="cell-content"><div class="badge-solid col-red">Emergency</div></div></td>
                                    <td><div class="cell-actions"><button type="button" class="action-icon-btn edit-btn" title="Edit in Settings"><i class="fa-solid fa-pen"></i></button></div></td>
                                </tr>
                            <?php else: ?>
                                <tr><td colspan="6" class="dior-empty-state">No emergency contact has been added.</td></tr>
                            <?php endif; ?>
</tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-emerg-page-count">0 selected / <?php echo esc_html((!empty($profile['emergency_name']) || !empty($profile['emergency_phone'])) ? 1 : 0); ?> total</span>
                    
                    <div class="master-pagination" id="dior-emerg-pagination">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</section>
