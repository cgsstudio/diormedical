<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/view-appointment.css?v=' . time()); ?>">

<!-- Medicine List Tab -->
<section class="dior-tab-panel" id="tab-doc-pharmacy-list" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Pharmacy</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span>Medicine List</span></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="section-body">
        <div class="view-appointment-card">
            
            <!-- Table Header -->
            <div class="va-header-container">
                <div class="va-title-box">
                    <h2>Medicine List</h2>
                    <div class="va-title-line"></div>
                </div>
                
                <div class="va-actions-wrapper">
                    <div class="va-search-box">
                        <i class="fas fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search records...">
                    </div>
                    <div class="va-actions-group">
                        <button class="va-icon-btn va-btn-primary" aria-label="Add new record"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg></button>
                        <button class="va-icon-btn va-btn-success" aria-label="Export to Excel"><i class="fas fa-file-arrow-down"></i></button>
                        <button class="va-icon-btn va-btn-info" aria-label="Refresh data"><i class="fas fa-rotate-right"></i></button>
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
                            <th>MEDICINE NO <i class="fas fa-sort"></i></th>
                            <th>MEDICINE NAME <i class="fas fa-sort"></i></th>
                            <th>CATEGORY <i class="fas fa-sort"></i></th>
                            <th>COMPANY <i class="fas fa-sort"></i></th>
                            <th>PURCHASE DATE <i class="fas fa-sort"></i></th>
                            <th>PRICE <i class="fas fa-sort"></i></th>
                            <th>EXPIRY DATE <i class="fas fa-sort"></i></th>
                            <th>STOCK <i class="fas fa-sort"></i></th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>1</td>
                            <td>Paracetamol</td>
                            <td>Tablet</td>
                            <td>Sky Pharma</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2021</td>
                            <td>$50</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2024</td>
                            <td>234</td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                    <button class="va-action-btn-sm va-btn-delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>2</td>
                            <td>Amoxicillin</td>
                            <td>Injectable</td>
                            <td>Mandud Pharma</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2021</td>
                            <td>$23</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2024</td>
                            <td>29</td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                    <button class="va-action-btn-sm va-btn-delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>3</td>
                            <td>Azithromycin</td>
                            <td>Tablet</td>
                            <td>Ajay Medicine</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2021</td>
                            <td>$43</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2024</td>
                            <td>26</td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                    <button class="va-action-btn-sm va-btn-delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>4</td>
                            <td>Amlodipine</td>
                            <td>Syrup</td>
                            <td>MedCare Pharma</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2021</td>
                            <td>$152</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2024</td>
                            <td>387</td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                    <button class="va-action-btn-sm va-btn-delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>5</td>
                            <td>Cyclobenzaprine</td>
                            <td>Injectable</td>
                            <td>PHL Pharma</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2021</td>
                            <td>$87</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2024</td>
                            <td>183</td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                    <button class="va-action-btn-sm va-btn-delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>6</td>
                            <td>Cephalexin</td>
                            <td>Tablet</td>
                            <td>Ajay Medicine</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2021</td>
                            <td>$38</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2024</td>
                            <td>72</td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                    <button class="va-action-btn-sm va-btn-delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>7</td>
                            <td>Hydrochlorothiazide</td>
                            <td>Tablet</td>
                            <td>Ajay Medicine</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2021</td>
                            <td>$10</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2024</td>
                            <td>82</td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                    <button class="va-action-btn-sm va-btn-delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-center"><input type="checkbox" class="va-checkbox"></td>
                            <td>8</td>
                            <td>Vitamin D</td>
                            <td>Syrup</td>
                            <td>MedCare Pharma</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2021</td>
                            <td>$57</td>
                            <td><i class="far fa-calendar va-icon-blue"></i> Feb 25, 2024</td>
                            <td>293</td>
                            <td>
                                <div class="va-row-actions">
                                    <button class="va-action-btn-sm va-btn-edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                    <button class="va-action-btn-sm va-btn-delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Add Medicine Tab -->
<section class="dior-tab-panel" id="tab-doc-pharmacy-add" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Pharmacy</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span>Add Medicine</span></li>
            </ul>
        </div>
    </div>

    <div class="section-body">
        <div class="view-appointment-card">
            <div class="va-header-container">
                <div class="va-title-box">
                    <h2>Add Medicine</h2>
                    <div class="va-title-line"></div>
                </div>
            </div>

            <div class="va-table-wrapper" style="padding: 20px;">
                <form>
                    <div style="display: flex; flex-wrap: wrap; margin-left: -15px; margin-right: -15px;">
                        <div style="width: 50%; padding: 0 15px; margin-bottom: 20px; box-sizing: border-box;">
                            <label style="display: block; margin-bottom: 8px; font-size: 14px; color: #1E293B;">No <span style="color: #ef4444;">*</span></label>
                            <input type="number" placeholder="No" style="width: 100%; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; box-sizing: border-box; font-size: 14px;">
                        </div>
                        <div style="width: 50%; padding: 0 15px; margin-bottom: 20px; box-sizing: border-box;">
                            <label style="display: block; margin-bottom: 8px; font-size: 14px; color: #1E293B;">Medicine Name <span style="color: #ef4444;">*</span></label>
                            <input type="text" placeholder="Medicine Name" style="width: 100%; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; box-sizing: border-box; font-size: 14px;">
                        </div>
                        <div style="width: 50%; padding: 0 15px; margin-bottom: 20px; box-sizing: border-box;">
                            <label style="display: block; margin-bottom: 8px; font-size: 14px; color: #1E293B;">Category <span style="color: #ef4444;">*</span></label>
                            <select style="width: 100%; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; box-sizing: border-box; font-size: 14px; background: #fff; appearance: auto;">
                                <option value="" disabled selected>Select Category</option>
                                <option value="Tablet">Tablet</option>
                                <option value="Syrup">Syrup</option>
                                <option value="Injectable">Injectable</option>
                            </select>
                        </div>
                        <div style="width: 50%; padding: 0 15px; margin-bottom: 20px; box-sizing: border-box;">
                            <label style="display: block; margin-bottom: 8px; font-size: 14px; color: #1E293B;">Company Name <span style="color: #ef4444;">*</span></label>
                            <input type="text" placeholder="Company Name" style="width: 100%; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; box-sizing: border-box; font-size: 14px;">
                        </div>
                        <div style="width: 50%; padding: 0 15px; margin-bottom: 20px; box-sizing: border-box;">
                            <label style="display: block; margin-bottom: 8px; font-size: 14px; color: #1E293B;">Purchase Date <span style="color: #ef4444;">*</span></label>
                            <input type="date" style="width: 100%; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; box-sizing: border-box; font-size: 14px;">
                        </div>
                        <div style="width: 50%; padding: 0 15px; margin-bottom: 20px; box-sizing: border-box;">
                            <label style="display: block; margin-bottom: 8px; font-size: 14px; color: #1E293B;">Price <span style="color: #ef4444;">*</span></label>
                            <input type="number" placeholder="Price" style="width: 100%; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; box-sizing: border-box; font-size: 14px;">
                        </div>
                        <div style="width: 50%; padding: 0 15px; margin-bottom: 20px; box-sizing: border-box;">
                            <label style="display: block; margin-bottom: 8px; font-size: 14px; color: #1E293B;">Expired Date <span style="color: #ef4444;">*</span></label>
                            <input type="date" style="width: 100%; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; box-sizing: border-box; font-size: 14px;">
                        </div>
                        <div style="width: 50%; padding: 0 15px; margin-bottom: 20px; box-sizing: border-box;">
                            <label style="display: block; margin-bottom: 8px; font-size: 14px; color: #1E293B;">Stock <span style="color: #ef4444;">*</span></label>
                            <input type="number" placeholder="Stock" style="width: 100%; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; box-sizing: border-box; font-size: 14px;">
                        </div>
                    </div>
                    <div style="margin-top: 10px;">
                        <button type="button" class="btn btn-primary" style="background: #94a3b8; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: not-allowed; margin-right: 10px; font-size: 14px;">Save</button>
                        <button type="button" class="btn btn-light" style="background: #fff; color: #475569; border: 1px solid #e2e8f0; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-size: 14px;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
