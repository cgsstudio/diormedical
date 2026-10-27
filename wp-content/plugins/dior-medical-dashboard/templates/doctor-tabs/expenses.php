<section class="dior-tab-panel" id="tab-doc-accounts-expenses" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; padding: 0 5px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Expenses</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house"></i></a></li>
                <li>/</li>
                <li><a href="#">Accounts</a></li>
                <li>/</li>
                <li class="active"><span>Expenses</span></li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-bill-list" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-bill-list');">
            <i class="fas fa-receipt"></i> Bill List
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-add-bill" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-add-bill');">
            <i class="fas fa-file-invoice-dollar"></i> Add Bill
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-income" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-income');">
            <i class="fas fa-arrow-trend-up"></i> Income
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-accounts-expenses" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-expenses');">
            <i class="fas fa-arrow-trend-down"></i> Expenses
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-income-report" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-income-report');">
            <i class="fas fa-chart-pie"></i> Income Report
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-invoice" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-invoice');">
            <i class="fas fa-file-invoice"></i> Invoice
        </button>
    </div>

    <div class="view-appointment-card">
        <div class="va-header-container">
            <div class="va-title-box">
                <h2><i class="fas fa-arrow-trend-down text-primary me-2"></i>Expenses</h2>
                <div class="va-title-line"></div>
            </div>

            <div class="va-actions-box">
                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                    <input type="text" class="va-search-input form-control border-start-0 ps-0"
                        placeholder="Search records...">
                </div>

                <button class="va-action-btn va-btn-primary btn" aria-label="Add new record">
                    <i class="fas fa-plus"></i>
                </button>
                <button class="va-action-btn va-btn-success btn" aria-label="Export to Excel">
                    <i class="fas fa-file-arrow-down"></i>
                </button>
                <button class="va-action-btn va-btn-info btn" aria-label="Refresh data">
                    <i class="fas fa-rotate-right"></i>
                </button>
            </div>
        </div>

        <div class="va-table-wrapper">
            <table class="va-table table table-hover">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;"><input type="checkbox"
                                class="va-checkbox form-check-input"></th>
                        <th>EXPENSE ID <i class="fas fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>CATEGORY <i class="fas fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>DESCRIPTION <i class="fas fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>AMOUNT <i class="fas fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>DATE <i class="fas fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>VENDOR <i class="fas fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>PAYMENT METHOD <i class="fas fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>DEPARTMENT <i class="fas fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>APPROVAL <i class="fas fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>PAYMENT <i class="fas fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Row 1 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox form-check-input">
                        </td>
                        <td>001</td>
                        <td>Medical Supplies</td>
                        <td>Bandages, gloves</td>
                        <td>500</td>
                        <td><i class="far fa-calendar va-icon-blue"></i> Nov 1, 2024</td>
                        <td>MedSupplyCo</td>
                        <td>Bank Transfer</td>
                        <td>ICU</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete btn"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 2 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox form-check-input">
                        </td>
                        <td>002</td>
                        <td>Staff Salaries</td>
                        <td>Doctor's Salary (Oct)</td>
                        <td>5000</td>
                        <td><i class="far fa-calendar va-icon-blue"></i> Nov 3, 2024</td>
                        <td>N/A</td>
                        <td>Bank Transfer</td>
                        <td>General Medicine</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete btn"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 3 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox form-check-input">
                        </td>
                        <td>003</td>
                        <td>Utilities</td>
                        <td>Water bill for Nov</td>
                        <td>150</td>
                        <td><i class="far fa-calendar va-icon-blue"></i> Nov 5, 2024</td>
                        <td>CityWater Corp</td>
                        <td>Cheque</td>
                        <td>Facilities</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete btn"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 4 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox form-check-input">
                        </td>
                        <td>004</td>
                        <td>Pharmaceuticals</td>
                        <td>Antibiotic medication stock</td>
                        <td>1200</td>
                        <td><i class="far fa-calendar va-icon-blue"></i> Nov 7, 2024</td>
                        <td>PharmaPlus</td>
                        <td>Bank Transfer</td>
                        <td>Pharmacy</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete btn"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 5 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox form-check-input">
                        </td>
                        <td>005</td>
                        <td>Equipment</td>
                        <td>Ultrasound machine repair</td>
                        <td>2500</td>
                        <td><i class="far fa-calendar va-icon-blue"></i> Nov 9, 2024</td>
                        <td>MedTech Repairs</td>
                        <td>Credit Card</td>
                        <td>Radiology</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge" style="background-color:transparent; color:#4F46E5;">Partially
                                Paid</span></td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete btn"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 6 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox form-check-input">
                        </td>
                        <td>006</td>
                        <td>Administrative</td>
                        <td>Office supplies purchase</td>
                        <td>250</td>
                        <td><i class="far fa-calendar va-icon-blue"></i> Nov 10, 2024</td>
                        <td>OfficeMart</td>
                        <td>Bank Transfer</td>
                        <td>Administration</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete btn"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 7 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox form-check-input">
                        </td>
                        <td>007</td>
                        <td>Medical Supplies</td>
                        <td>Surgical gloves and masks</td>
                        <td>450</td>
                        <td><i class="far fa-calendar va-icon-blue"></i> Nov 12, 2024</td>
                        <td>HealthEquip</td>
                        <td>Bank Transfer</td>
                        <td>Surgery</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete btn"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 8 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox form-check-input">
                        </td>
                        <td>008</td>
                        <td>Consulting Services</td>
                        <td>Consulting fee for IT system</td>
                        <td>3500</td>
                        <td><i class="far fa-calendar va-icon-blue"></i> Nov 14, 2024</td>
                        <td>TechConsult</td>
                        <td>Bank Transfer</td>
                        <td>IT</td>
                        <td><span class="va-badge va-badge-pending">Pending</span></td>
                        <td><span class="va-badge va-badge-pending">Pending</span></td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete btn"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 9 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox form-check-input">
                        </td>
                        <td>009</td>
                        <td>Maintenance</td>
                        <td>HVAC system repair</td>
                        <td>2000</td>
                        <td><i class="far fa-calendar va-icon-blue"></i> Nov 15, 2024</td>
                        <td>AirFlow Repairs</td>
                        <td>Bank Transfer</td>
                        <td>Facilities</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete btn"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Row 10 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox form-check-input">
                        </td>
                        <td>010</td>
                        <td>Staff Salaries</td>
                        <td>Nurse salary (Oct)</td>
                        <td>3000</td>
                        <td><i class="far fa-calendar va-icon-blue"></i> Nov 17, 2024</td>
                        <td>N/A</td>
                        <td>Bank Transfer</td>
                        <td>Nursing</td>
                        <td><span class="va-badge va-badge-completed">Approved</span></td>
                        <td><span class="va-badge va-badge-completed">Paid</span></td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit btn"><i class="fas fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete btn"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</section>