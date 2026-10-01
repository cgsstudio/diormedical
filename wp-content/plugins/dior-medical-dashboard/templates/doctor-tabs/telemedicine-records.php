<?php
$virtual_records = [
    (object)['id' => 'VV001', 'patient_name' => 'Ella Harris', 'patient_avatar' => 'user-6.png', 'doctor_name' => 'Dr. Lisa Martinez', 'date' => 'Nov 25, 2024', 'duration' => '35', 'diagnosis' => 'Upper respiratory tract infection'],
    (object)['id' => 'VV002', 'patient_name' => 'Liam Thomas', 'patient_avatar' => 'user-7.png', 'doctor_name' => 'Dr. Robert Williams', 'date' => 'Nov 24, 2024', 'duration' => '40', 'diagnosis' => 'Lumbar radiculopathy - L4-L5 disc h...'],
    (object)['id' => 'VV003', 'patient_name' => 'Charlotte Lee', 'patient_avatar' => 'user-8.png', 'doctor_name' => 'Dr. Sarah Johnson', 'date' => 'Nov 23, 2024', 'duration' => '25', 'diagnosis' => 'Tension headache, likely stress-rela...'],
    (object)['id' => 'VV004', 'patient_name' => 'Robert Anderson', 'patient_avatar' => 'user-9.png', 'doctor_name' => 'Dr. James Anderson', 'date' => 'Nov 22, 2024', 'duration' => '30', 'diagnosis' => 'Gastroesophageal reflux disease (GER...'],
    (object)['id' => 'VV005', 'patient_name' => 'Emma Wilson', 'patient_avatar' => 'user-10.png', 'doctor_name' => '', 'date' => 'Nov 27, 2024', 'duration' => '20', 'diagnosis' => 'Allergic dermatitis']
];
?>

<section class="dior-tab-panel documents-tab-panel" id="tab-doc-telemed-records" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Virtual Visit Records</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)" style="color: #64748B; text-decoration: none; font-size: 14px;">Telemedicine</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">Virtual Visit Records</span></li>
            </ul>
        </div>
    </div>

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2>Virtual Visit Records</h2>
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
                        <th>VISIT ID <i class="fa-solid fa-sort"></i></th>
                        <th>PATIENT <i class="fa-solid fa-sort"></i></th>
                        <th>DOCTOR <i class="fa-solid fa-sort"></i></th>
                        <th>VISIT DATE <i class="fa-solid fa-sort"></i></th>
                        <th>DURATION (MIN) <i class="fa-solid fa-sort"></i></th>
                        <th>DIAGNOSIS <i class="fa-solid fa-sort"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($virtual_records as $vr): ?>
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                        <td><?php echo esc_html($vr->id); ?></td>
                        <td>
                            <div class="docs-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/' . $vr->patient_avatar); ?>" class="docs-user-avatar" alt="<?php echo esc_attr($vr->patient_name); ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($vr->patient_name); ?>&background=random'">
                                <span class="docs-user-name"><?php echo esc_html($vr->patient_name); ?></span>
                            </div>
                        </td>
                        <td><?php echo esc_html($vr->doctor_name); ?></td>
                        <td><i class="regular fa-calendar docs-icon-blue"></i> <?php echo esc_html($vr->date); ?></td>
                        <td><?php echo esc_html($vr->duration); ?></td>
                        <td><?php echo esc_html($vr->diagnosis); ?></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit"><i class="fa-solid fa-pen"></i></button>
                                <button class="docs-action-btn-sm docs-btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="docs-pagination-container">
            <div class="docs-showing-text">0 selected / 5 total</div>
        </div>
    </div>
</section>
