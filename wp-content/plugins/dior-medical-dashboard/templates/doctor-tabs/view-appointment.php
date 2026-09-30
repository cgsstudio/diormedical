<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/view-appointment.css?v=' . time()); ?>">

<section class="dior-tab-panel" id="tab-doc-appointments-view" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">View Appointment</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)">Appointments</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span>View Appointment</span></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="section-body">
        <div class="view-appointment-card">
            
            <!-- Table Header -->
            <div class="va-header-container">
                <div class="va-title-box">
                    <h2>Appointments</h2>
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
                            <th>Patient Name <i class="fa-solid fa-sort"></i></th>
                            <th>Doctor <i class="fa-solid fa-sort"></i></th>
                            <th>Reason <i class="fa-solid fa-sort"></i></th>
                            <th>Gender <i class="fa-solid fa-sort"></i></th>
                            <th>Date <i class="fa-solid fa-sort"></i></th>
                            <th>Time <i class="fa-solid fa-sort"></i></th>
                            <th>Phone <i class="fa-solid fa-sort"></i></th>
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
                                    <img src="assets/images/users/user-1.png" onerror="this.src='https://ui-avatars.com/api/?name=Cara+Stevens&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Cara Stevens</span>
                                </div>
                            </td>
                            <td>Dr. Rajesh</td>
                            <td>Fever</td>
                            <td><i class="fa-solid fa-venus va-icon-pink"></i> female</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 18, 2026</td>
                            <td>09:00</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 1234567890</td>
                            <td><span class="va-badge va-badge-completed">Completed</span></td>
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
                                    <img src="assets/images/users/user-2.png" onerror="this.src='https://ui-avatars.com/api/?name=John+Doe&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">John Doe</span>
                                </div>
                            </td>
                            <td>Dr. Sarah Smith</td>
                            <td>Cold</td>
                            <td><i class="fa-solid fa-mars va-icon-blue"></i> male</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 22, 2026</td>
                            <td>11:30</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 9876543210</td>
                            <td><span class="va-badge va-badge-cancelled">Cancelled</span></td>
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
                                    <img src="assets/images/users/user-3.png" onerror="this.src='https://ui-avatars.com/api/?name=Alice+Johnson&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Alice Johnson</span>
                                </div>
                            </td>
                            <td>Dr. Jay Soni</td>
                            <td>Headache</td>
                            <td><i class="fa-solid fa-venus va-icon-pink"></i> female</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 14, 2026</td>
                            <td>09:45</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 2345678901</td>
                            <td><span class="va-badge va-badge-confirmed">Confirmed</span></td>
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
                                    <img src="assets/images/users/user-4.png" onerror="this.src='https://ui-avatars.com/api/?name=Bob+Brown&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Bob Brown</span>
                                </div>
                            </td>
                            <td>Dr. Pooja Patel</td>
                            <td>Back Pain</td>
                            <td><i class="fa-solid fa-mars va-icon-blue"></i> male</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 19, 2026</td>
                            <td>13:15</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 3456789012</td>
                            <td><span class="va-badge va-badge-cancelled">Cancelled</span></td>
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
                                    <img src="assets/images/users/user-5.png" onerror="this.src='https://ui-avatars.com/api/?name=Sara+Lee&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Sara Lee</span>
                                </div>
                            </td>
                            <td>Dr. Jayesh Shah</td>
                            <td>Flu</td>
                            <td><i class="fa-solid fa-venus va-icon-pink"></i> female</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 21, 2026</td>
                            <td>10:30</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 4567890123</td>
                            <td><span class="va-badge va-badge-completed">Completed</span></td>
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
                                    <img src="assets/images/users/user-6.png" onerror="this.src='https://ui-avatars.com/api/?name=Tom+Harris&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Tom Harris</span>
                                </div>
                            </td>
                            <td>Dr. Sarah Smith</td>
                            <td>Cough</td>
                            <td><i class="fa-solid fa-mars va-icon-blue"></i> male</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 13, 2026</td>
                            <td>14:15</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 5678901234</td>
                            <td><span class="va-badge va-badge-confirmed">Confirmed</span></td>
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
                                    <img src="assets/images/users/user-7.png" onerror="this.src='https://ui-avatars.com/api/?name=Emma+Wilson&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Emma Wilson</span>
                                </div>
                            </td>
                            <td>Dr. Rajesh</td>
                            <td>Allergies</td>
                            <td><i class="fa-solid fa-venus va-icon-pink"></i> female</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 18, 2026</td>
                            <td>16:45</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 6789012345</td>
                            <td><span class="va-badge va-badge-pending">Pending</span></td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                    <button class="va-action-btn-sm va-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 8 -->
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>
                                <div class="va-user-profile">
                                    <img src="assets/images/users/user-8.png" onerror="this.src='https://ui-avatars.com/api/?name=David+Lee&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">David Lee</span>
                                </div>
                            </td>
                            <td>Dr. Pooja Patel</td>
                            <td>Stomach Ache</td>
                            <td><i class="fa-solid fa-mars va-icon-blue"></i> male</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 16, 2026</td>
                            <td>11:45</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 7890123456</td>
                            <td><span class="va-badge va-badge-confirmed">Confirmed</span></td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                    <button class="va-action-btn-sm va-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 9 -->
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>
                                <div class="va-user-profile">
                                    <img src="assets/images/users/user-9.png" onerror="this.src='https://ui-avatars.com/api/?name=Sophia+Turner&background=random'" class="va-user-avatar" alt="User">
                                    <span class="va-user-name">Sophia Turner</span>
                                </div>
                            </td>
                            <td>Dr. Jay Soni</td>
                            <td>Joint Pain</td>
                            <td><i class="fa-solid fa-venus va-icon-pink"></i> female</td>
                            <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 19, 2026</td>
                            <td>15:45</td>
                            <td><i class="fa-solid fa-phone va-icon-blue"></i> 8901234567</td>
                            <td><span class="va-badge va-badge-cancelled">Cancelled</span></td>
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



        </div>
    </div>
</section>
