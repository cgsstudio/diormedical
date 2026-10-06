<section class="dior-tab-panel" id="tab-doc-accounts-expenses" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; padding: 0 5px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Expenses</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house"></i></a></li>
                <li>/</li>
                <li><a href="#">Accounts</a></li>
                <li>/</li>
                <li class="active"><span>Expenses</span></li>
            </ul>
        </div>
    </div>

    <div class="view-appointment-card">
        <div class="va-header-container">
            <div class="va-title-box">
                <h2>Expenses</h2>
                <div class="va-title-line"></div>
            </div>
            
            <div class="va-actions-box">
                <div class="va-search-container">
                    <i class="fa-solid fa-magnifying-glass va-search-icon"></i>
                    <input type="text" class="va-search-input" placeholder="Search records...">
                </div>
                
                <button class="va-action-btn va-btn-primary" aria-label="Add new record">
                    <i class="fa-solid fa-plus"></i>
                </button>
                <button class="va-action-btn va-btn-success" aria-label="Export to Excel">
                    <i class="fa-solid fa-file-arrow-down"></i>
                </button>
                <button class="va-action-btn va-btn-info" aria-label="Refresh data">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
            </div>
        </div>

        <div class="va-table-wrapper">
            <table class="va-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;"><input type="checkbox" class="va-checkbox"></th>
                        <th>EXPENSE ID <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>CATEGORY <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>DESCRIPTION <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>AMOUNT <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>DATE <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>VENDOR <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>PAYMENT METHOD <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>DEPARTMENT <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>APPROVAL <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>PAYMENT <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Row 1 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>001</td>
                        <td>Medical Supplies</td>
                        <td>Bandages, gloves</td>
                        <td>500</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 1, 2024</td>
                        <td>MedSupplyCo</td>
                        <td>Bank Transfer</td>
                        <td>ICU</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
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
                        <td>002</td>
                        <td>Staff Salaries</td>
                        <td>Doctor's Salary (Oct)</td>
                        <td>5000</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 3, 2024</td>
                        <td>N/A</td>
                        <td>Bank Transfer</td>
                        <td>General Medicine</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
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
                        <td>003</td>
                        <td>Utilities</td>
                        <td>Water bill for Nov</td>
                        <td>150</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 5, 2024</td>
                        <td>CityWater Corp</td>
                        <td>Cheque</td>
                        <td>Facilities</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
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
                        <td>004</td>
                        <td>Pharmaceuticals</td>
                        <td>Antibiotic medication stock</td>
                        <td>1200</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 7, 2024</td>
                        <td>PharmaPlus</td>
                        <td>Bank Transfer</td>
                        <td>Pharmacy</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
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
                        <td>005</td>
                        <td>Equipment</td>
                        <td>Ultrasound machine repair</td>
                        <td>2500</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 9, 2024</td>
                        <td>MedTech Repairs</td>
                        <td>Credit Card</td>
                        <td>Radiology</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge" style="background-color:transparent; color:#4F46E5;">Partially Paid</span></td>
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
                        <td>006</td>
                        <td>Administrative</td>
                        <td>Office supplies purchase</td>
                        <td>250</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 10, 2024</td>
                        <td>OfficeMart</td>
                        <td>Bank Transfer</td>
                        <td>Administration</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
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
                        <td>007</td>
                        <td>Medical Supplies</td>
                        <td>Surgical gloves and masks</td>
                        <td>450</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 12, 2024</td>
                        <td>HealthEquip</td>
                        <td>Bank Transfer</td>
                        <td>Surgery</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
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
                        <td>008</td>
                        <td>Consulting Services</td>
                        <td>Consulting fee for IT system</td>
                        <td>3500</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 14, 2024</td>
                        <td>TechConsult</td>
                        <td>Bank Transfer</td>
                        <td>IT</td>
                        <td><span class="va-badge va-badge-pending">Pending</span></td>
                        <td><span class="va-badge va-badge-pending">Pending</span></td>
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
                        <td>009</td>
                        <td>Maintenance</td>
                        <td>HVAC system repair</td>
                        <td>2000</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 15, 2024</td>
                        <td>AirFlow Repairs</td>
                        <td>Bank Transfer</td>
                        <td>Facilities</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
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
                        <td>010</td>
                        <td>Staff Salaries</td>
                        <td>Nurse salary (Oct)</td>
                        <td>3000</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 17, 2024</td>
                        <td>N/A</td>
                        <td>Bank Transfer</td>
                        <td>Nursing</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
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
