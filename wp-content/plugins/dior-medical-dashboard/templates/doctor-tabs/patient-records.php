<section class="dior-tab-panel" id="tab-doc-patients-records" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Patient Records</h4>
        </div>
        <div>
            <ul class="breadcrumb-list" style="display: flex; list-style: none; padding: 0; margin: 0; gap: 8px; align-items: center;">
                <li><a href="#"><i class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)" style="font-size: 14px; color: #64748B; text-decoration: none; font-weight: 500;">Patients</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span style="font-size: 14px; color: #1E293B; font-weight: 700;">Patient Records</span></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="view-appointment-card" style="margin-top: 10px;">
        <div class="va-header-container" style="border-bottom: 1px solid #E2E8F0; padding-bottom: 0;">
            <div class="va-title-container" style="position: relative; padding-bottom: 15px;">
                <h4 class="va-card-title">Patient Records</h4>
                <!-- Blue Accent Line -->
                <div style="position: absolute; bottom: -1px; left: 0; width: 40px; height: 3px; background-color: #4F46E5; border-radius: 3px 3px 0 0;"></div>
            </div>
            
            <div class="va-header-actions" style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                <div class="va-search-wrapper" style="position: relative;">
                    <i class="fas fa-magnifying-glass va-search-icon" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 14px;"></i>
                    <input type="text" class="va-search-input" placeholder="Search records..." style="padding-left: 36px; padding-right: 15px; height: 38px; border: 1px solid #E2E8F0; border-radius: 6px; font-size: 14px; outline: none; width: 220px;" onkeyup="diorDocFilterPatientRecords(this.value)">
                </div>
                
                <button type="button" class="btn btn-sm" id="dior-pr-bulk-delete" style="display: none; background-color: #EF4444; color: white; height: 38px; width: 38px; padding: 0; border-radius: 6px; border: none; display: none; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                </button>
                <button type="button" class="btn btn-sm" style="background-color: #4F46E5; color: white; height: 38px; width: 38px; padding: 0; border-radius: 6px; border: none; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg>
                </button>
                <button type="button" class="btn btn-sm" style="background-color: #10B981; color: white; height: 38px; width: 38px; padding: 0; border-radius: 6px; border: none; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-download"></i>
                </button>
                <button type="button" class="btn btn-sm" style="background-color: #3B82F6; color: white; height: 38px; width: 38px; padding: 0; border-radius: 6px; border: none; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-rotate-right"></i>
                </button>
            </div>
        </div>

        <div class="va-table-wrapper">
            <table class="va-table dior-doc-table" id="dior-patient-records-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="pr-select-all" class="dior-checkbox">
                        </th>
                        <th>PATIENT ID <i class="fas fa-sort" style="color: #CBD5E1; margin-left: 5px; font-size: 10px;"></i></th>
                        <th>FULL NAME <i class="fas fa-sort" style="color: #CBD5E1; margin-left: 5px; font-size: 10px;"></i></th>
                        <th>GENDER <i class="fas fa-sort" style="color: #CBD5E1; margin-left: 5px; font-size: 10px;"></i></th>
                        <th>ADMISSION DATE <i class="fas fa-sort" style="color: #CBD5E1; margin-left: 5px; font-size: 10px;"></i></th>
                        <th>DIAGNOSIS <i class="fas fa-sort" style="color: #CBD5E1; margin-left: 5px; font-size: 10px;"></i></th>
                        <th>NEXT FOLLOW-UP <i class="fas fa-sort" style="color: #CBD5E1; margin-left: 5px; font-size: 10px;"></i></th>
                        <th>STATUS <i class="fas fa-sort" style="color: #CBD5E1; margin-left: 5px; font-size: 10px;"></i></th>
                        <th style="text-align: center;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $records = [
                        ['P001', 'John Doe', 'Male', '#3B82F6', 'Oct 1, 2024', 'Hypertension', 'Nov 1, 2024', 'Active', 'col-green'],
                        ['P002', 'Jane Smith', 'Female', '#EC4899', 'Oct 5, 2024', 'Type 2 Diabetes', 'Nov 10, 2024', 'Discharged', 'col-blue'],
                        ['P003', 'David Johnson', 'Male', '#3B82F6', 'Sep 15, 2024', 'Chronic Migraine', 'Oct 30, 2024', 'Discharged', 'col-blue'],
                        ['P004', 'Emily Davis', 'Female', '#EC4899', 'Oct 3, 2024', 'Asthma', 'Nov 3, 2024', 'Active', 'col-green'],
                        ['P005', 'Michael Brown', 'Male', '#3B82F6', 'Sep 20, 2024', 'Osteoarthritis', 'Oct 25, 2024', 'Discharged', 'col-blue'],
                        ['P006', 'Sophia Wilson', 'Female', '#EC4899', 'Oct 7, 2024', 'Hyperthyroidism', 'Oct 28, 2024', 'Discharged', 'col-blue'],
                        ['P007', 'Lucas Harris', 'Male', '#3B82F6', 'Aug 25, 2024', 'Chronic Obstructive Pulmonary...', 'Sep 30, 2024', 'Active', 'col-green'],
                        ['P008', 'Charlotte Moore', 'Female', '#EC4899', 'Oct 2, 2024', 'Psoriasis', 'Nov 5, 2024', 'Discharged', 'col-blue'],
                        ['P009', 'Ethan Taylor', 'Male', '#3B82F6', 'Oct 8, 2024', 'Anxiety Disorder', 'Nov 8, 2024', 'Discharged', 'col-blue'],
                        ['P010', 'Megan Clark', 'Female', '#EC4899', 'Sep 30, 2024', 'Depression', 'Oct 30, 2024', 'Observation', 'col-indigo']
                    ];
                    
                    foreach ($records as $r):
                    ?>
                    <tr class="pr-row" data-search="<?php echo strtolower(esc_attr($r[1] . ' ' . $r[0] . ' ' . $r[5])); ?>">
                        <td style="text-align: center;">
                            <input type="checkbox" class="dior-checkbox pr-row-checkbox">
                        </td>
                        <td><?php echo esc_html($r[0]); ?></td>
                        <td style="font-weight: 500; color: #1E293B;"><?php echo esc_html($r[1]); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="<?php echo $r[2] === 'Male' ? 'fas fa-mars' : 'fas fa-venus'; ?>" style="color: <?php echo esc_attr($r[3]); ?>;"></i>
                                <span><?php echo esc_html($r[2]); ?></span>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="far fa-calendar" style="color: #3B82F6;"></i>
                                <span><?php echo esc_html($r[4]); ?></span>
                            </div>
                        </td>
                        <td><?php echo esc_html($r[5]); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="far fa-calendar" style="color: #3B82F6;"></i>
                                <span><?php echo esc_html($r[6]); ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="dior-st <?php echo esc_attr($r[8]); ?>"><?php echo esc_html($r[7]); ?></span>
                        </td>
                        <td>
                            <div class="va-row-actions" style="justify-content: center;">
                                <button class="va-action-btn-sm va-btn-edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                <button class="va-action-btn-sm va-btn-delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<style>
/* Custom status badges for this specific table */
.dior-st.col-green { background-color: #DCFCE7; color: #16A34A; }
.dior-st.col-blue { background-color: #DBEAFE; color: #2563EB; }
.dior-st.col-indigo { background-color: #E0E7FF; color: #4F46E5; }
.dior-st { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const prSelectAll = document.getElementById('pr-select-all');
    const prCheckboxes = document.querySelectorAll('.pr-row-checkbox');
    const prBulkDelete = document.getElementById('dior-pr-bulk-delete');

    function checkPRSelection() {
        if(!prBulkDelete) return;
        const anyChecked = document.querySelector('.pr-row-checkbox:checked');
        if (anyChecked) {
            prBulkDelete.style.display = 'flex';
        } else {
            prBulkDelete.style.display = 'none';
        }
        
        // Update master checkbox
        const allChecked = prCheckboxes.length > 0 && document.querySelectorAll('.pr-row-checkbox:checked').length === prCheckboxes.length;
        if(prSelectAll) prSelectAll.checked = allChecked;
    }

    if (prSelectAll) {
        prSelectAll.addEventListener('change', function() {
            prCheckboxes.forEach(cb => cb.checked = this.checked);
            checkPRSelection();
        });
    }

    prCheckboxes.forEach(cb => {
        cb.addEventListener('change', checkPRSelection);
    });
});

window.diorDocFilterPatientRecords = function(val) {
    val = val.toLowerCase().trim();
    const table = document.getElementById('dior-patient-records-table');
    if (!table) return;
    const rows = table.querySelectorAll('tbody tr.pr-row');
    rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        if (val === '' || text.includes(val)) {
            row.removeAttribute('data-filter-hidden');
        } else {
            row.setAttribute('data-filter-hidden', 'true');
        }
    });
    if (table._diorRenderPage) {
        table._diorRenderPage(1);
    }
};
</script>
