<section class="dior-tab-panel" id="tab-doc-consultation-notes" style="display: none;">

    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; padding: 0 5px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Consultations Notes</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house"></i></a></li>
                <li>/</li>
                <li class="active"><span>Consultations Notes</span></li>
            </ul>
        </div>
    </div>

    <div class="view-appointment-card">
        <!-- Header -->
        <div class="va-header-container">
            <div class="va-title-box">
                <h2>Consultations Notes</h2>
                <div class="va-title-line"></div>
            </div>

            <div class="va-actions-box">
                <div class="va-search-container">
                    <i class="fa-solid fa-magnifying-glass va-search-icon"></i>
                    <input type="text" class="va-search-input" placeholder="Search records..." id="cons-notes-search">
                </div>
                <button class="va-action-btn va-btn-primary" aria-label="Add new record" title="Add New">
                    <i class="fa-solid fa-plus"></i>
                </button>
                <button class="va-action-btn va-btn-success" aria-label="Export to Excel" title="Export">
                    <i class="fa-solid fa-file-arrow-down"></i>
                </button>
                <button class="va-action-btn va-btn-info" aria-label="Refresh data" title="Refresh">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="va-table-wrapper">
            <table class="va-table" id="cons-notes-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">
                            <input type="checkbox" class="va-checkbox" id="cons-select-all">
                        </th>
                        <th>CONSULTATION ID <i class="fa-solid fa-sort" style="color:#ccc; margin-left:4px;"></i></th>
                        <th>PATIENT NAME <i class="fa-solid fa-sort" style="color:#ccc; margin-left:4px;"></i></th>
                        <th>DATE <i class="fa-solid fa-sort" style="color:#ccc; margin-left:4px;"></i></th>
                        <th>TIME <i class="fa-solid fa-sort" style="color:#ccc; margin-left:4px;"></i></th>
                        <th>CHIEF COMPLAINT <i class="fa-solid fa-sort" style="color:#ccc; margin-left:4px;"></i></th>
                        <th>DIAGNOSIS <i class="fa-solid fa-sort" style="color:#ccc; margin-left:4px;"></i></th>
                        <th>DOCTOR <i class="fa-solid fa-sort" style="color:#ccc; margin-left:4px;"></i></th>
                        <th>STATUS <i class="fa-solid fa-sort" style="color:#ccc; margin-left:4px;"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align:center;"><input type="checkbox" class="va-checkbox cons-row-check"></td>
                        <td>CONS001</td>
                        <td>
                            <div class="va-user-profile">
                                <div class="va-user-avatar" style="width:36px;height:36px;border-radius:50%;background:#E0E7FF;display:flex;align-items:center;justify-content:center;font-weight:700;color:#4F46E5;font-size:14px;flex-shrink:0;">S</div>
                                <span class="va-user-name">Sarah Johnson</span>
                            </div>
                        </td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 20, 2024</td>
                        <td><i class="fa-regular fa-clock" style="color:#673AB7;margin-right:6px;"></i> 10:00 AM</td>
                        <td>Persistent headache and dizziness</td>
                        <td>Migraine with aura</td>
                        <td>Dr. Smith</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm va-btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:center;"><input type="checkbox" class="va-checkbox cons-row-check"></td>
                        <td>CONS002</td>
                        <td>
                            <div class="va-user-profile">
                                <div class="va-user-avatar" style="width:36px;height:36px;border-radius:50%;background:#DCFCE7;display:flex;align-items:center;justify-content:center;font-weight:700;color:#16A34A;font-size:14px;flex-shrink:0;">M</div>
                                <span class="va-user-name">Michael Chen</span>
                            </div>
                        </td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 21, 2024</td>
                        <td><i class="fa-regular fa-clock" style="color:#673AB7;margin-right:6px;"></i> 11:30 AM</td>
                        <td>Chest pain and shortness of breath</td>
                        <td>Angina pectoris</td>
                        <td>Dr. Smith</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm va-btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:center;"><input type="checkbox" class="va-checkbox cons-row-check"></td>
                        <td>CONS003</td>
                        <td>
                            <div class="va-user-profile">
                                <div class="va-user-avatar" style="width:36px;height:36px;border-radius:50%;background:#FEE2E2;display:flex;align-items:center;justify-content:center;font-weight:700;color:#DC2626;font-size:14px;flex-shrink:0;">E</div>
                                <span class="va-user-name">Emily Rodriguez</span>
                            </div>
                        </td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 22, 2024</td>
                        <td><i class="fa-regular fa-clock" style="color:#673AB7;margin-right:6px;"></i> 02:00 PM</td>
                        <td>Fever and body aches</td>
                        <td>Viral infection</td>
                        <td>Dr. Smith</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm va-btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:center;"><input type="checkbox" class="va-checkbox cons-row-check"></td>
                        <td>CONS004</td>
                        <td>
                            <div class="va-user-profile">
                                <div class="va-user-avatar" style="width:36px;height:36px;border-radius:50%;background:#E0E7FF;display:flex;align-items:center;justify-content:center;font-weight:700;color:#4F46E5;font-size:14px;flex-shrink:0;">D</div>
                                <span class="va-user-name">David Williams</span>
                            </div>
                        </td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 23, 2024</td>
                        <td><i class="fa-regular fa-clock" style="color:#673AB7;margin-right:6px;"></i> 09:00 AM</td>
                        <td>Joint pain and stiffness</td>
                        <td>Osteoarthritis</td>
                        <td>Dr. Smith</td>
                        <td><span class="va-badge va-badge-confirmed">In Progress</span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm va-btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:center;"><input type="checkbox" class="va-checkbox cons-row-check"></td>
                        <td>CONS005</td>
                        <td>
                            <div class="va-user-profile">
                                <div class="va-user-avatar" style="width:36px;height:36px;border-radius:50%;background:#FEF3C7;display:flex;align-items:center;justify-content:center;font-weight:700;color:#D97706;font-size:14px;flex-shrink:0;">L</div>
                                <span class="va-user-name">Lisa Anderson</span>
                            </div>
                        </td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 24, 2024</td>
                        <td><i class="fa-regular fa-clock" style="color:#673AB7;margin-right:6px;"></i> 03:30 PM</td>
                        <td>Skin rash and itching</td>
                        <td>Allergic dermatitis</td>
                        <td>Dr. Smith</td>
                        <td><span class="va-badge va-badge-pending">Scheduled</span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm va-btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:center;"><input type="checkbox" class="va-checkbox cons-row-check"></td>
                        <td>CONS006</td>
                        <td>
                            <div class="va-user-profile">
                                <div class="va-user-avatar" style="width:36px;height:36px;border-radius:50%;background:#DCFCE7;display:flex;align-items:center;justify-content:center;font-weight:700;color:#16A34A;font-size:14px;flex-shrink:0;">J</div>
                                <span class="va-user-name">James Taylor</span>
                            </div>
                        </td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 19, 2024</td>
                        <td><i class="fa-regular fa-clock" style="color:#673AB7;margin-right:6px;"></i> 04:00 PM</td>
                        <td>Abdominal pain and nausea</td>
                        <td>Gastroenteritis</td>
                        <td>Dr. Smith</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm va-btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:center;"><input type="checkbox" class="va-checkbox cons-row-check"></td>
                        <td>CONS007</td>
                        <td>
                            <div class="va-user-profile">
                                <div class="va-user-avatar" style="width:36px;height:36px;border-radius:50%;background:#FCE7F3;display:flex;align-items:center;justify-content:center;font-weight:700;color:#DB2777;font-size:14px;flex-shrink:0;">M</div>
                                <span class="va-user-name">Maria Garcia</span>
                            </div>
                        </td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 18, 2024</td>
                        <td><i class="fa-regular fa-clock" style="color:#673AB7;margin-right:6px;"></i> 01:00 PM</td>
                        <td>Cough and sore throat</td>
                        <td>Upper respiratory infection</td>
                        <td>Dr. Smith</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm va-btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:center;"><input type="checkbox" class="va-checkbox cons-row-check"></td>
                        <td>CONS008</td>
                        <td>
                            <div class="va-user-profile">
                                <div class="va-user-avatar" style="width:36px;height:36px;border-radius:50%;background:#E0E7FF;display:flex;align-items:center;justify-content:center;font-weight:700;color:#4F46E5;font-size:14px;flex-shrink:0;">R</div>
                                <span class="va-user-name">Robert Brown</span>
                            </div>
                        </td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 17, 2024</td>
                        <td><i class="fa-regular fa-clock" style="color:#673AB7;margin-right:6px;"></i> 10:30 AM</td>
                        <td>Back pain radiating to leg</td>
                        <td>Sciatica</td>
                        <td>Dr. Smith</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm va-btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:center;"><input type="checkbox" class="va-checkbox cons-row-check"></td>
                        <td>CONS009</td>
                        <td>
                            <div class="va-user-profile">
                                <div class="va-user-avatar" style="width:36px;height:36px;border-radius:50%;background:#FEF3C7;display:flex;align-items:center;justify-content:center;font-weight:700;color:#D97706;font-size:14px;flex-shrink:0;">J</div>
                                <span class="va-user-name">Jennifer Martinez</span>
                            </div>
                        </td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 25, 2024</td>
                        <td><i class="fa-regular fa-clock" style="color:#673AB7;margin-right:6px;"></i> 11:00 AM</td>
                        <td>Anxiety and insomnia</td>
                        <td>Generalized anxiety disorder</td>
                        <td>Dr. Smith</td>
                        <td><span class="va-badge va-badge-pending">Scheduled</span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm va-btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:center;"><input type="checkbox" class="va-checkbox cons-row-check"></td>
                        <td>CONS010</td>
                        <td>
                            <div class="va-user-profile">
                                <div class="va-user-avatar" style="width:36px;height:36px;border-radius:50%;background:#DCFCE7;display:flex;align-items:center;justify-content:center;font-weight:700;color:#16A34A;font-size:14px;flex-shrink:0;">C</div>
                                <span class="va-user-name">Christopher Lee</span>
                            </div>
                        </td>
                        <td><i class="fa-regular fa-calendar va-icon-blue"></i> Nov 16, 2024</td>
                        <td><i class="fa-regular fa-clock" style="color:#673AB7;margin-right:6px;"></i> 02:30 PM</td>
                        <td>Fatigue and weight loss</td>
                        <td>Hypothyroidism</td>
                        <td>Dr. Smith</td>
                        <td><span class="va-badge va-badge-completed">Completed</span></td>
                        <td>
                            <div class="va-row-actions">
                                <button class="va-action-btn-sm va-btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="va-action-btn-sm va-btn-delete" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>


    </div>

</section>
