<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/documents.css?v=' . time()); ?>">

<section class="dior-tab-panel" id="tab-doc-documents-upload" style="display: none;">
            <!-- Breadcrumb Header -->
            <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Upload Documents</h4>
                </div>
                <div>
                    <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                        <li><a href="#"><i class="fa-solid fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                        <li><span style="color: #94A3B8;">/</span></li>
                        <li><a href="javascript:void(0)" style="color: #64748B; text-decoration: none; font-size: 14px;">Documents & Consent</a></li>
                        <li><span style="color: #94A3B8;">/</span></li>
                        <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">Upload Documents</span></li>
                    </ul>
                </div>
            </div>

            <div class="docs-card">
                <div class="docs-header-container">
                    <div class="docs-title-box">
                        <h2>Upload Documents</h2>
                        <div class="docs-title-line"></div>
                    </div>
                    
                    <div class="docs-actions-wrapper">
                        <div class="docs-search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" placeholder="Search records...">
                        </div>
                        <div class="docs-actions-group">
                            <button class="docs-icon-btn docs-btn-primary" aria-label="Add new record"><i class="fa-solid fa-plus"></i></button>
                            <button class="docs-icon-btn docs-btn-success" aria-label="Export to Excel"><i class="fa-solid fa-file-arrow-down"></i></button>
                            <button class="docs-icon-btn docs-btn-info" aria-label="Refresh data"><i class="fa-solid fa-rotate-right"></i></button>
                        </div>
                    </div>
                </div>

                <div class="docs-table-wrapper">
                    <table class="docs-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;" class="text-center"><input type="checkbox" class="docs-checkbox"></th>
                                <th>DOCUMENT ID <i class="fa-solid fa-sort"></i></th>
                                <th>PATIENT <i class="fa-solid fa-sort"></i></th>
                                <th>DOCUMENT TYPE <i class="fa-solid fa-sort"></i></th>
                                <th>DOCUMENT NAME <i class="fa-solid fa-sort"></i></th>
                                <th>UPLOAD DATE <i class="fa-solid fa-sort"></i></th>
                                <th>UPLOADED BY <i class="fa-solid fa-sort"></i></th>
                                <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Row 1 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>DOC001</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-1.png" class="docs-user-avatar" alt="John Doe" onerror="this.src='https://ui-avatars.com/api/?name=John+Doe&background=random'">
                                        <span class="docs-user-name">John Doe</span>
                                    </div>
                                </td>
                                <td>Lab Report</td>
                                <td>Blood Test Report - Complete Blood Count</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 25, 2024</td>
                                <td>Dr. Sarah Johnson</td>
                                <td><span class="docs-badge docs-badge-green">Verified</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 2 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>DOC002</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-2.png" class="docs-user-avatar" alt="Alice Smith" onerror="this.src='https://ui-avatars.com/api/?name=Alice+Smith&background=random'">
                                        <span class="docs-user-name">Alice Smith</span>
                                    </div>
                                </td>
                                <td>X-Ray</td>
                                <td>Knee X-Ray - Right</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 24, 2024</td>
                                <td>Dr. Robert Williams</td>
                                <td><span class="docs-badge docs-badge-green">Verified</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 3 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>DOC003</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-3.png" class="docs-user-avatar" alt="David Johnson" onerror="this.src='https://ui-avatars.com/api/?name=David+Johnson&background=random'">
                                        <span class="docs-user-name">David Johnson</span>
                                    </div>
                                </td>
                                <td>MRI Scan</td>
                                <td>Cardiac MRI Scan</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 23, 2024</td>
                                <td>Dr. James Anderson</td>
                                <td><span class="docs-badge docs-badge-green">Verified</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 4 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>DOC004</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-4.png" class="docs-user-avatar" alt="Sophia Miller" onerror="this.src='https://ui-avatars.com/api/?name=Sophia+Miller&background=random'">
                                        <span class="docs-user-name">Sophia Miller</span>
                                    </div>
                                </td>
                                <td>CT Scan</td>
                                <td>Abdominal CT Scan</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 22, 2024</td>
                                <td>Dr. Sarah Johnson</td>
                                <td><span class="docs-badge docs-badge-green">Verified</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 5 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>DOC005</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-5.png" class="docs-user-avatar" alt="James Brown" onerror="this.src='https://ui-avatars.com/api/?name=James+Brown&background=random'">
                                        <span class="docs-user-name">James Brown</span>
                                    </div>
                                </td>
                                <td>Insurance Document</td>
                                <td>Insurance Pre-Authorization</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 26, 2024</td>
                                <td>Admin Staff</td>
                                <td><span class="docs-badge docs-badge-orange">Pending</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 6 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>DOC006</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-6.png" class="docs-user-avatar" alt="Ella Harris" onerror="this.src='https://ui-avatars.com/api/?name=Ella+Harris&background=random'">
                                        <span class="docs-user-name">Ella Harris</span>
                                    </div>
                                </td>
                                <td>Prescription</td>
                                <td>Discharge Prescription</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 25, 2024</td>
                                <td>Dr. Lisa Martinez</td>
                                <td><span class="docs-badge docs-badge-green">Verified</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 7 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>DOC007</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-7.png" class="docs-user-avatar" alt="Liam Thomas" onerror="this.src='https://ui-avatars.com/api/?name=Liam+Thomas&background=random'">
                                        <span class="docs-user-name">Liam Thomas</span>
                                    </div>
                                </td>
                                <td>Medical Report</td>
                                <td>Spinal Assessment Report</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 20, 2024</td>
                                <td>Dr. Robert Williams</td>
                                <td><span class="docs-badge docs-badge-green">Verified</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 8 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>DOC008</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-8.png" class="docs-user-avatar" alt="Charlotte Lee" onerror="this.src='https://ui-avatars.com/api/?name=Charlotte+Lee&background=random'">
                                        <span class="docs-user-name">Charlotte Lee</span>
                                    </div>
                                </td>
                                <td>ID Proof</td>
                                <td>Patient ID - Aadhar Card</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 27, 2024</td>
                                <td>Reception Staff</td>
                                <td><span class="docs-badge docs-badge-green">Verified</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="docs-footer">
                    <span class="docs-pagination-text">0 selected / 8 total</span>
                </div>
            </div>
</section>

<!-- ==============================================
     2) CONSENT TEMPLATES PANE
     ============================================== -->
<section class="dior-tab-panel" id="tab-doc-documents-templates" style="display: none;">
            <!-- Breadcrumb Header -->
            <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Consent Templates</h4>
                </div>
                <div>
                    <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                        <li><a href="#"><i class="fa-solid fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                        <li><span style="color: #94A3B8;">/</span></li>
                        <li><a href="javascript:void(0)" style="color: #64748B; text-decoration: none; font-size: 14px;">Documents & Consent</a></li>
                        <li><span style="color: #94A3B8;">/</span></li>
                        <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">Consent Templates</span></li>
                    </ul>
                </div>
            </div>

            <div class="docs-card">
                <div class="docs-header-container">
                    <div class="docs-title-box">
                        <h2>Consent Templates</h2>
                        <div class="docs-title-line"></div>
                    </div>
                    
                    <div class="docs-actions-wrapper">
                        <div class="docs-search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" placeholder="Search records...">
                        </div>
                        <div class="docs-actions-group">
                            <button class="docs-icon-btn docs-btn-primary" aria-label="Add new record"><i class="fa-solid fa-plus"></i></button>
                            <button class="docs-icon-btn docs-btn-success" aria-label="Export to Excel"><i class="fa-solid fa-file-arrow-down"></i></button>
                            <button class="docs-icon-btn docs-btn-info" aria-label="Refresh data"><i class="fa-solid fa-rotate-right"></i></button>
                        </div>
                    </div>
                </div>

                <div class="docs-table-wrapper">
                    <table class="docs-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;" class="text-center"><input type="checkbox" class="docs-checkbox"></th>
                                <th>TEMPLATE ID <i class="fa-solid fa-sort"></i></th>
                                <th>TEMPLATE NAME <i class="fa-solid fa-sort"></i></th>
                                <th>CATEGORY <i class="fa-solid fa-sort"></i></th>
                                <th>DEPARTMENT <i class="fa-solid fa-sort"></i></th>
                                <th>VERSION <i class="fa-solid fa-sort"></i></th>
                                <th>CREATED DATE <i class="fa-solid fa-sort"></i></th>
                                <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Row 1 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>TPL001</td>
                                <td>General Surgery Consent Form</td>
                                <td>Surgery Consent</td>
                                <td>General Surgery</td>
                                <td>v2.1</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Jan 15, 2024</td>
                                <td><span class="docs-badge docs-badge-active">Active</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 2 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>TPL002</td>
                                <td>Anesthesia Consent Form</td>
                                <td>Anesthesia Consent</td>
                                <td>All Departments</td>
                                <td>v1.5</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Feb 10, 2024</td>
                                <td><span class="docs-badge docs-badge-active">Active</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 3 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>TPL003</td>
                                <td>Blood Transfusion Consent</td>
                                <td>Blood Transfusion</td>
                                <td>All Departments</td>
                                <td>v1.3</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Mar 5, 2024</td>
                                <td><span class="docs-badge docs-badge-active">Active</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 4 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>TPL004</td>
                                <td>Orthopedic Surgery Consent</td>
                                <td>Surgery Consent</td>
                                <td>Orthopedics</td>
                                <td>v2.0</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Apr 12, 2024</td>
                                <td><span class="docs-badge docs-badge-active">Active</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 5 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>TPL005</td>
                                <td>Discharge Against Medical Advice (AMA)</td>
                                <td>Discharge AMA</td>
                                <td>All Departments</td>
                                <td>v1.2</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> May 20, 2024</td>
                                <td><span class="docs-badge docs-badge-active">Active</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 6 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>TPL006</td>
                                <td>Cardiac Procedure Consent</td>
                                <td>Surgery Consent</td>
                                <td>Cardiology</td>
                                <td>v1.8</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Jun 15, 2024</td>
                                <td><span class="docs-badge docs-badge-active">Active</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 7 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>TPL007</td>
                                <td>Research Participation Consent</td>
                                <td>Research Participation</td>
                                <td>All Departments</td>
                                <td>v1.0</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Jul 1, 2024</td>
                                <td><span class="docs-badge docs-badge-orange">Draft</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 8 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>TPL008</td>
                                <td>Photography and Media Consent</td>
                                <td>Photography</td>
                                <td>All Departments</td>
                                <td>v1.1</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Aug 10, 2024</td>
                                <td><span class="docs-badge docs-badge-active">Active</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="docs-footer">
                    <span class="docs-pagination-text">0 selected / 8 total</span>
                </div>
            </div>
</section>

<!-- ==============================================
     3) SIGNED CONSENT PANE 
     ============================================== -->
<section class="dior-tab-panel documents-tab-panel" id="tab-doc-documents-signed" style="display: none;">
            <!-- Breadcrumb Header -->
            <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Signed Consent Forms</h4>
                </div>
                <div>
                    <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                        <li><a href="#"><i class="fa-solid fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                        <li><span style="color: #94A3B8;">/</span></li>
                        <li><a href="javascript:void(0)" style="color: #64748B; text-decoration: none; font-size: 14px;">Documents & Consent</a></li>
                        <li><span style="color: #94A3B8;">/</span></li>
                        <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">Signed Consent</span></li>
                    </ul>
                </div>
            </div>

            <div class="docs-card">
                <div class="docs-header-container">
                    <div class="docs-title-box">
                        <h2>Signed Consent Forms</h2>
                        <div class="docs-title-line"></div>
                    </div>
                    
                    <div class="docs-actions-wrapper">
                        <div class="docs-search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" placeholder="Search records...">
                        </div>
                        <div class="docs-actions-group">
                            <button class="docs-icon-btn docs-btn-primary" aria-label="Add new record"><i class="fa-solid fa-plus"></i></button>
                            <button class="docs-icon-btn docs-btn-success" aria-label="Export to Excel"><i class="fa-solid fa-file-arrow-down"></i></button>
                            <button class="docs-icon-btn docs-btn-info" aria-label="Refresh data"><i class="fa-solid fa-rotate-right"></i></button>
                        </div>
                    </div>
                </div>

                <div class="docs-table-wrapper">
                    <table class="docs-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;" class="text-center"><input type="checkbox" class="docs-checkbox"></th>
                                <th>CONSENT ID <i class="fa-solid fa-sort"></i></th>
                                <th>PATIENT <i class="fa-solid fa-sort"></i></th>
                                <th>CONSENT TYPE <i class="fa-solid fa-sort"></i></th>
                                <th>PROCEDURE <i class="fa-solid fa-sort"></i></th>
                                <th>SIGNED DATE <i class="fa-solid fa-sort"></i></th>
                                <th>SIGNED BY <i class="fa-solid fa-sort"></i></th>
                                <th>WITNESS <i class="fa-solid fa-sort"></i></th>
                                <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Row 1 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>CON001</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-1.png" class="docs-user-avatar" alt="John Doe" onerror="this.src='https://ui-avatars.com/api/?name=John+Doe&background=random'">
                                        <span class="docs-user-name">John Doe</span>
                                    </div>
                                </td>
                                <td>Surgery Consent</td>
                                <td>Laparoscopic Appendectomy</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 25, 2024</td>
                                <td>John Doe</td>
                                <td>Nurse Jennifer Adams</td>
                                <td><span class="docs-badge docs-badge-green">Signed</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 2 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>CON002</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-2.png" class="docs-user-avatar" alt="Alice Smith" onerror="this.src='https://ui-avatars.com/api/?name=Alice+Smith&background=random'">
                                        <span class="docs-user-name">Alice Smith</span>
                                    </div>
                                </td>
                                <td>Surgery Consent</td>
                                <td>Total Knee Replacement</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 24, 2024</td>
                                <td>Alice Smith</td>
                                <td>Nurse Michael Thompson</td>
                                <td><span class="docs-badge docs-badge-green">Signed</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 3 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>CON003</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-3.png" class="docs-user-avatar" alt="David Johnson" onerror="this.src='https://ui-avatars.com/api/?name=David+Johnson&background=random'">
                                        <span class="docs-user-name">David Johnson</span>
                                    </div>
                                </td>
                                <td>Surgery Consent</td>
                                <td>Coronary Artery Bypass Grafting (CABG)</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 23, 2024</td>
                                <td>David Johnson</td>
                                <td>Dr. Lisa Martinez</td>
                                <td><span class="docs-badge docs-badge-green">Signed</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 4 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>CON004</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-4.png" class="docs-user-avatar" alt="Sophia Miller" onerror="this.src='https://ui-avatars.com/api/?name=Sophia+Miller&background=random'">
                                        <span class="docs-user-name">Sophia Miller</span>
                                    </div>
                                </td>
                                <td>Surgery Consent</td>
                                <td>Laparoscopic Cholecystectomy</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 22, 2024</td>
                                <td>Sophia Miller</td>
                                <td>Nurse Sarah Williams</td>
                                <td><span class="docs-badge docs-badge-green">Signed</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 5 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>CON005</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-5.png" class="docs-user-avatar" alt="James Brown" onerror="this.src='https://ui-avatars.com/api/?name=James+Brown&background=random'">
                                        <span class="docs-user-name">James Brown</span>
                                    </div>
                                </td>
                                <td>Anesthesia Consent</td>
                                <td>General Anesthesia for Hernia Repair</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 26, 2024</td>
                                <td>James Brown</td>
                                <td>Nurse Jennifer Adams</td>
                                <td><span class="docs-badge docs-badge-green">Signed</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 6 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>CON006</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-6.png" class="docs-user-avatar" alt="Ella Harris" onerror="this.src='https://ui-avatars.com/api/?name=Ella+Harris&background=random'">
                                        <span class="docs-user-name">Ella Harris</span>
                                    </div>
                                </td>
                                <td>Treatment Consent</td>
                                <td>Pediatric Treatment Plan</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 25, 2024</td>
                                <td>Robert Harris</td>
                                <td>Nurse Sarah Williams</td>
                                <td><span class="docs-badge docs-badge-green">Signed</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 7 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>CON007</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-7.png" class="docs-user-avatar" alt="Liam Thomas" onerror="this.src='https://ui-avatars.com/api/?name=Liam+Thomas&background=random'">
                                        <span class="docs-user-name">Liam Thomas</span>
                                    </div>
                                </td>
                                <td>Surgery Consent</td>
                                <td>Spinal Fusion Surgery</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 20, 2024</td>
                                <td>Liam Thomas</td>
                                <td>Dr. Sarah Johnson</td>
                                <td><span class="docs-badge docs-badge-green">Signed</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 8 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>CON008</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-8.png" class="docs-user-avatar" alt="Robert Anderson" onerror="this.src='https://ui-avatars.com/api/?name=Robert+Anderson&background=random'">
                                        <span class="docs-user-name">Robert Anderson</span>
                                    </div>
                                </td>
                                <td>Blood Transfusion</td>
                                <td>Blood Transfusion for Hip Replacement</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 17, 2024</td>
                                <td>Robert Anderson</td>
                                <td>Nurse Michael Thompson</td>
                                <td><span class="docs-badge docs-badge-green">Signed</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 9 -->
                            <tr>
                                <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                                <td>CON009</td>
                                <td>
                                    <div class="docs-user-profile">
                                        <img src="assets/images/users/user-9.png" class="docs-user-avatar" alt="Charlotte Lee" onerror="this.src='https://ui-avatars.com/api/?name=Charlotte+Lee&background=random'">
                                        <span class="docs-user-name">Charlotte Lee</span>
                                    </div>
                                </td>
                                <td>General Consent</td>
                                <td>Hospital Admission and Treatment</td>
                                <td><i class="fa-regular fa-calendar docs-icon-blue"></i> Nov 27, 2024</td>
                                <td>Charlotte Lee</td>
                                <td>Reception Staff</td>
                                <td><span class="docs-badge docs-badge-orange">Pending</span></td>
                                <td>
                                    <div class="docs-row-actions">
                                        <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                        <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="docs-footer">
                    <span class="docs-pagination-text">0 selected / 9 total</span>
                </div>
            </div>
</section>
