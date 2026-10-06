<section class="dior-tab-panel" id="tab-doc-accounts-income" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; padding: 0 5px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Income</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house"></i></a></li>
                <li>/</li>
                <li><a href="#">Accounts</a></li>
                <li>/</li>
                <li class="active"><span>Income</span></li>
            </ul>
        </div>
    </div>

    <div class="view-appointment-card">
        <div class="va-header-container">
            <div class="va-title-box">
                <h2>Income</h2>
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
                        <th>INCOME ID <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>PATIENT NAME <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>SERVICE TYPE <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>BILLED <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>PAID <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>METHOD <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>DATE <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>INVOICE # <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>STATUS <i class="fa-solid fa-sort" style="color: #ccc; margin-left:5px;"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Row 1 -->
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="va-checkbox"></td>
                        <td>1001</td>
                        <td>John Doe</td>
                        <td>Consultation</td>
                        <td>150</td>
                        <td>120</td>
                        <td>Credit Card</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 2, 2024</td>
                        <td>INV-20241101-1001</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
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
                        <td>1002</td>
                        <td>Alice Smith</td>
                        <td>Surgery</td>
                        <td>1200</td>
                        <td>1000</td>
                        <td>Cash</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Oct 26, 2024</td>
                        <td>INV-20241025-1002</td>
                        <td><span class="va-badge va-badge-pending">Pending</span></td>
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
                        <td>1003</td>
                        <td>David Lee</td>
                        <td>Consultation</td>
                        <td>180</td>
                        <td>180</td>
                        <td>Insurance</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Oct 30, 2024</td>
                        <td>INV-20241030-1003</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
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
                        <td>1004</td>
                        <td>Eve Williams</td>
                        <td>X-Ray</td>
                        <td>200</td>
                        <td>200</td>
                        <td>Credit Card</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 2, 2024</td>
                        <td>INV-20241102-1004</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
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
                        <td>1005</td>
                        <td>George Brown</td>
                        <td>Laboratory Test</td>
                        <td>100</td>
                        <td>90</td>
                        <td>Debit Card</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 3, 2024</td>
                        <td>INV-20241103-1005</td>
                        <td><span class="va-badge va-badge-pending">Pending</span></td>
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
                        <td>1006</td>
                        <td>Isabella Taylor</td>
                        <td>Consultation</td>
                        <td>250</td>
                        <td>250</td>
                        <td>Cash</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 5, 2024</td>
                        <td>INV-20241105-1006</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
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
                        <td>1007</td>
                        <td>James Scott</td>
                        <td>Surgery</td>
                        <td>3000</td>
                        <td>1500</td>
                        <td>Bank Transfer</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Oct 23, 2024</td>
                        <td>INV-20241022-1007</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
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
                        <td>1008</td>
                        <td>Oliver Harris</td>
                        <td>CT Scan</td>
                        <td>600</td>
                        <td>500</td>
                        <td>Credit Card</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 6, 2024</td>
                        <td>INV-20241106-1008</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
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
                        <td>1009</td>
                        <td>Sophia King</td>
                        <td>Consultation</td>
                        <td>130</td>
                        <td>130</td>
                        <td>Cash</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 8, 2024</td>
                        <td>INV-20241108-1009</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
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
                        <td>1010</td>
                        <td>Mason Clark</td>
                        <td>MRI</td>
                        <td>1000</td>
                        <td>1000</td>
                        <td>Debit Card</td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 9, 2024</td>
                        <td>INV-20241109-1010</td>
                        <td><span class="va-badge va-badge-pending">Pending</span></td>
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
