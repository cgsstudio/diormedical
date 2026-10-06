<section class="dior-tab-panel" id="tab-doc-accounts-bill-list" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; padding: 0 5px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Bill List</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house"></i></a></li>
                <li>/</li>
                <li><a href="javascript:void(0)">Accounts</a></li>
                <li>/</li>
                <li class="active"><span>Bill List</span></li>
            </ul>
        </div>
    </div>

    <div class="view-appointment-card">
        <div class="va-header-container">
            <div class="va-title-box">
                <h2>Bill List</h2>
                <div class="va-title-line"></div>
            </div>
            
            <div class="va-actions-wrapper">
                <div class="va-search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search records...">
                </div>
                
                <div class="va-actions-group">
                    <button class="va-icon-btn va-btn-primary"><i class="fa-solid fa-plus"></i></button>
                    <button class="va-icon-btn va-btn-success"><i class="fa-solid fa-download"></i></button>
                    <button class="va-icon-btn va-btn-info"><i class="fa-solid fa-rotate-right"></i></button>
                </div>
            </div>
        </div>

        <div class="va-table-wrapper">
            <table class="va-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">
                            <input type="checkbox" class="va-checkbox">
                        </th>
                        <th>Admission ID <i class="fa-solid fa-sort"></i></th>
                        <th>Patient Name <i class="fa-solid fa-sort"></i></th>
                        <th>Doctor <i class="fa-solid fa-sort"></i></th>
                        <th>Status <i class="fa-solid fa-sort"></i></th>
                        <th>Date <i class="fa-solid fa-sort"></i></th>
                        <th>Tax <i class="fa-solid fa-sort"></i></th>
                        <th>Discount <i class="fa-solid fa-sort"></i></th>
                        <th>Total <i class="fa-solid fa-sort"></i></th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Row 1 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>HGYU89</td>
                        <td>
                            <div class="va-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-1.png'); ?>" alt="User" class="va-user-avatar">
                                <span class="va-user-name">Sarah Smith</span>
                            </div>
                        </td>
                        <td>DR. Megha Trivedi</td>
                        <td><span class="va-badge va-badge-cancelled">Unpaid</span></td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Feb 12, 2018</td>
                        <td>8%</td>
                        <td>5%</td>
                        <td>$142</td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 2 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>HGYU99</td>
                        <td>
                            <div class="va-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-2.png'); ?>" alt="User" class="va-user-avatar">
                                <span class="va-user-name">Vishal Kumar</span>
                            </div>
                        </td>
                        <td>DR. John Deo</td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Mar 22, 2018</td>
                        <td>10%</td>
                        <td>5%</td>
                        <td>$126</td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 3 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>HGYU29</td>
                        <td>
                            <div class="va-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-3.png'); ?>" alt="User" class="va-user-avatar">
                                <span class="va-user-name">Rahul Malhotra</span>
                            </div>
                        </td>
                        <td>DR. Sarah Smith</td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Feb 2, 2018</td>
                        <td>9%</td>
                        <td>5%</td>
                        <td>$115</td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 4 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>HGYU36</td>
                        <td>
                            <div class="va-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-4.png'); ?>" alt="User" class="va-user-avatar">
                                <span class="va-user-name">Priya Jain</span>
                            </div>
                        </td>
                        <td>DR. Stive</td>
                        <td><span class="va-badge va-badge-cancelled">Unpaid</span></td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Mar 19, 2018</td>
                        <td>9%</td>
                        <td>5%</td>
                        <td>$142</td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 5 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>HGYU27</td>
                        <td>
                            <div class="va-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-5.png'); ?>" alt="User" class="va-user-avatar">
                                <span class="va-user-name">Radhika Patel</span>
                            </div>
                        </td>
                        <td>DR. Megha Trivedi</td>
                        <td><span class="va-badge va-badge-cancelled">Unpaid</span></td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> May 22, 2018</td>
                        <td>10%</td>
                        <td>5%</td>
                        <td>$126</td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 6 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>HGYU12</td>
                        <td>
                            <div class="va-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-6.png'); ?>" alt="User" class="va-user-avatar">
                                <span class="va-user-name">Sarah Smith</span>
                            </div>
                        </td>
                        <td>DR. Megha Trivedi</td>
                        <td><span class="va-badge va-badge-cancelled">Unpaid</span></td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> May 7, 2018</td>
                        <td>8%</td>
                        <td>5%</td>
                        <td>$115</td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 7 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>HGYU14</td>
                        <td>
                            <div class="va-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-7.png'); ?>" alt="User" class="va-user-avatar">
                                <span class="va-user-name">Vishal Kumar</span>
                            </div>
                        </td>
                        <td>DR. John Deo</td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Feb 20, 2018</td>
                        <td>10%</td>
                        <td>5%</td>
                        <td>$126</td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 8 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>HGYU09</td>
                        <td>
                            <div class="va-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-8.png'); ?>" alt="User" class="va-user-avatar">
                                <span class="va-user-name">Rahul Malhotra</span>
                            </div>
                        </td>
                        <td>DR. Sarah Smith</td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Mar 21, 2018</td>
                        <td>9%</td>
                        <td>5%</td>
                        <td>$142</td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 9 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>HGYU80</td>
                        <td>
                            <div class="va-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-9.png'); ?>" alt="User" class="va-user-avatar">
                                <span class="va-user-name">Priya Jain</span>
                            </div>
                        </td>
                        <td>DR. Stive</td>
                        <td><span class="va-badge va-badge-cancelled">Unpaid</span></td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Feb 11, 2018</td>
                        <td>9%</td>
                        <td>5%</td>
                        <td>$142</td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 10 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>HGYU38</td>
                        <td>
                            <div class="va-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-1.png'); ?>" alt="User" class="va-user-avatar">
                                <span class="va-user-name">Radhika Patel</span>
                            </div>
                        </td>
                        <td>DR. Megha Trivedi</td>
                        <td><span class="va-badge va-badge-cancelled">Unpaid</span></td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Mar 18, 2018</td>
                        <td>10%</td>
                        <td>5%</td>
                        <td>$126</td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>


    </div>
</section>
