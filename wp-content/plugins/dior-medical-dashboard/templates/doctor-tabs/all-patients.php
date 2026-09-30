<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/view-appointment.css?v=' . time()); ?>">
<!-- Using view-appointment.css to ensure identical table styling -->

<section class="dior-tab-panel" id="tab-doc-patients-all" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">All Patients</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)">Patients</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span>All Patients</span></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="section-body">
        <div class="view-appointment-card">
            
            <!-- Table Header -->
            <div class="va-header-container">
                <div class="va-title-box">
                    <h2>All Patients</h2>
                    <div class="va-title-line"></div>
                </div>
                
                <div class="va-actions-wrapper">
                    <div class="va-search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search records...">
                    </div>
                    <div class="va-actions-group">
                        <button class="va-icon-btn va-btn-primary" aria-label="Add new record"><i class="fa-solid fa-plus"></i></button>
                        <button class="va-icon-btn va-btn-success" aria-label="Export to Excel"><i class="fa-solid fa-file-arrow-down"></i></button>
                        <button class="va-icon-btn va-btn-info" aria-label="Refresh data"><i class="fa-solid fa-rotate-right"></i></button>
                    </div>
                </div>
            </div>

            <!-- Table Responsive Wrapper -->
            <div class="va-table-wrapper">
                <table class="va-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;" class="text-center">
                                <input type="checkbox" class="va-checkbox">
                            </th>
                            <th>Name <i class="fa-solid fa-sort"></i></th>
                            <th>Treatment <i class="fa-solid fa-sort"></i></th>
                            <th>Gender <i class="fa-solid fa-sort"></i></th>
                            <th>Phone <i class="fa-solid fa-sort"></i></th>
                            <th>Admission Date <i class="fa-solid fa-sort"></i></th>
                            <th>Blood Group <i class="fa-solid fa-sort"></i></th>
                            <th>Doctor <i class="fa-solid fa-sort"></i></th>
                            <th>Address <i class="fa-solid fa-sort"></i></th>
                            <th>Status <i class="fa-solid fa-sort"></i></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Row 1 -->
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>
                                <div class="va-user-profile">
                                    <img src="https://i.pravatar.cc/150?img=1" onerror="this.src='https://ui-avatars.com/api/?name=Ashton+Cox&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Ashton Cox</span>
                                </div>
                            </td>
                            <td>Malaria</td>
                            <td><i class="fa-solid fa-mars va-icon-blue"></i> male</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 1234567890</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Jan 15, 2024</td>
                            <td>B+</td>
                            <td>Dr. John Doe</td>
                            <td><i class="fa-solid fa-location-dot" style="color: #64748B; margin-right:6px;"></i> 11, Shyam Appt., Rajkot</td>
                            <td><span class="va-badge va-badge-completed">Recovered</span></td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                    <button class="va-action-btn-sm va-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 2 -->
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>
                                <div class="va-user-profile">
                                    <img src="https://i.pravatar.cc/150?img=5" onerror="this.src='https://ui-avatars.com/api/?name=Jessica+Williams&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Jessica Williams</span>
                                </div>
                            </td>
                            <td>Dengue</td>
                            <td><i class="fa-solid fa-venus va-icon-pink"></i> female</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 9876543210</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Feb 10, 2024</td>
                            <td>O+</td>
                            <td>Dr. Sarah Smith</td>
                            <td><i class="fa-solid fa-location-dot" style="color: #64748B; margin-right:6px;"></i> 23, Green Park, Surat</td>
                            <td><span class="va-badge va-badge-completed">Recovered</span></td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                    <button class="va-action-btn-sm va-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 3 -->
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>
                                <div class="va-user-profile">
                                    <img src="https://i.pravatar.cc/150?img=8" onerror="this.src='https://ui-avatars.com/api/?name=Oliver+Jones&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Oliver Jones</span>
                                </div>
                            </td>
                            <td>Flu</td>
                            <td><i class="fa-solid fa-mars va-icon-blue"></i> male</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 1122334455</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Mar 5, 2024</td>
                            <td>A+</td>
                            <td>Dr. Rajesh</td>
                            <td><i class="fa-solid fa-location-dot" style="color: #64748B; margin-right:6px;"></i> 45, Sunrise Villa, Ah...</td>
                            <td><span class="va-badge va-badge-confirmed">Under Treatment</span></td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                    <button class="va-action-btn-sm va-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 4 -->
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>
                                <div class="va-user-profile">
                                    <img src="https://i.pravatar.cc/150?img=12" onerror="this.src='https://ui-avatars.com/api/?name=Emily+Davis&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Emily Davis</span>
                                </div>
                            </td>
                            <td>Appendicitis</td>
                            <td><i class="fa-solid fa-venus va-icon-pink"></i> female</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 2233445566</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Mar 15, 2024</td>
                            <td>B-</td>
                            <td>Dr. Jay Soni</td>
                            <td><i class="fa-solid fa-location-dot" style="color: #64748B; margin-right:6px;"></i> 78, River Side, Baroda</td>
                            <td><span class="va-badge va-badge-completed">Recovered</span></td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                    <button class="va-action-btn-sm va-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 5 -->
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>
                                <div class="va-user-profile">
                                    <img src="https://i.pravatar.cc/150?img=33" onerror="this.src='https://ui-avatars.com/api/?name=Michael+Brown&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Michael Brown</span>
                                </div>
                            </td>
                            <td>Pneumonia</td>
                            <td><i class="fa-solid fa-mars va-icon-blue"></i> male</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 3344556677</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Apr 1, 2024</td>
                            <td>AB+</td>
                            <td>Dr. Emma Watson</td>
                            <td><i class="fa-solid fa-location-dot" style="color: #64748B; margin-right:6px;"></i> 90, Sunlight Plaza, S...</td>
                            <td><span class="va-badge va-badge-confirmed">Under Treatment</span></td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                    <button class="va-action-btn-sm va-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 6 -->
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>
                                <div class="va-user-profile">
                                    <img src="https://i.pravatar.cc/150?img=47" onerror="this.src='https://ui-avatars.com/api/?name=Sophia+Miller&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Sophia Miller</span>
                                </div>
                            </td>
                            <td>Chronic Cough</td>
                            <td><i class="fa-solid fa-venus va-icon-pink"></i> female</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 4455667788</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Apr 10, 2024</td>
                            <td>O-</td>
                            <td>Dr. James Moore</td>
                            <td><i class="fa-solid fa-location-dot" style="color: #64748B; margin-right:6px;"></i> 32, Hill View, Rajkot</td>
                            <td><span class="va-badge va-badge-pending">Under Observation</span></td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                    <button class="va-action-btn-sm va-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 7 -->
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>
                                <div class="va-user-profile">
                                    <img src="https://i.pravatar.cc/150?img=53" onerror="this.src='https://ui-avatars.com/api/?name=Liam+Wilson&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Liam Wilson</span>
                                </div>
                            </td>
                            <td>Fracture</td>
                            <td><i class="fa-solid fa-mars va-icon-blue"></i> male</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 5566778899</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Apr 15, 2024</td>
                            <td>A-</td>
                            <td>Dr. Rajesh</td>
                            <td><i class="fa-solid fa-location-dot" style="color: #64748B; margin-right:6px;"></i> 56, Park Avenue, Sur...</td>
                            <td><span class="va-badge va-badge-completed">Recovered</span></td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                    <button class="va-action-btn-sm va-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="va-footer">
                <div class="va-pagination-text">
                    <span>0 selected / 16 total</span>
                </div>
                
                <ul class="va-pagination-nav">
                    <li class="disabled"><a href="#" aria-label="First"><i class="fa-solid fa-backward-step"></i></a></li>
                    <li class="disabled"><a href="#" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></a></li>
                    <li class="active"><a href="#">1</a></li>
                    <li><a href="#">2</a></li>
                    <li><a href="#" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></a></li>
                    <li><a href="#" aria-label="Last"><i class="fa-solid fa-forward-step"></i></a></li>
                </ul>
            </div>

        </div>
    </div>
</section>
