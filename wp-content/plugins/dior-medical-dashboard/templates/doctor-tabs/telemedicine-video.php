<?php
$video_consultations = [
    (object)['id' => 'VC001', 'patient_name' => 'John Doe', 'patient_avatar' => 'user-1.png', 'doctor_name' => 'Dr. Sarah Johnson', 'date' => 'Nov 28, 2024', 'time' => '10:00', 'status' => 'Scheduled', 'status_class' => 'docs-badge-blue'],
    (object)['id' => 'VC002', 'patient_name' => 'Alice Smith', 'patient_avatar' => 'user-2.png', 'doctor_name' => 'Dr. Robert Williams', 'date' => 'Nov 27, 2024', 'time' => '14:00', 'status' => 'Completed', 'status_class' => 'docs-badge-green'],
    (object)['id' => 'VC003', 'patient_name' => 'David Johnson', 'patient_avatar' => 'user-3.png', 'doctor_name' => 'Dr. James Anderson', 'date' => 'Nov 27, 2024', 'time' => '11:00', 'status' => 'In Progress', 'status_class' => 'docs-badge-orange'],
    (object)['id' => 'VC004', 'patient_name' => 'Sophia Miller', 'patient_avatar' => 'user-4.png', 'doctor_name' => 'Dr. Sarah Johnson', 'date' => 'Nov 26, 2024', 'time' => '15:30', 'status' => 'Cancelled', 'status_class' => 'docs-badge-red'],
    (object)['id' => 'VC005', 'patient_name' => 'James Brown', 'patient_avatar' => 'user-5.png', 'doctor_name' => 'Dr. Lisa Martinez', 'date' => 'Nov 29, 2024', 'time' => '09:00', 'status' => 'Scheduled', 'status_class' => 'docs-badge-blue']
];
?>

<section class="dior-tab-panel documents-tab-panel" id="tab-doc-telemed-video" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Video Consultation</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="#"><i class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)" style="color: #64748B; text-decoration: none; font-size: 14px;">Telemedicine</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">Video Consultation</span></li>
            </ul>
        </div>
    </div>

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2>Video Consultation</h2>
                <div class="docs-title-line"></div>
            </div>
            
            <div class="docs-actions-wrapper">
                <div class="docs-search-box">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search records...">
                </div>
                <div class="docs-actions-group">
                    <button class="docs-icon-btn docs-btn-primary" aria-label="Add new record"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg></button>
                    <button class="docs-icon-btn docs-btn-success" aria-label="Export to Excel"><i class="fas fa-file-arrow-down"></i></button>
                    <button class="docs-icon-btn docs-btn-info" aria-label="Refresh data"><i class="fas fa-rotate-right"></i></button>
                </div>
            </div>
        </div>

        <div class="docs-table-wrapper">
            <table class="docs-table">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center"><input type="checkbox" class="docs-checkbox"></th>
                        <th>CONSULTATION ID <i class="fas fa-sort"></i></th>
                        <th>PATIENT <i class="fas fa-sort"></i></th>
                        <th>DOCTOR <i class="fas fa-sort"></i></th>
                        <th>DATE <i class="fas fa-sort"></i></th>
                        <th>TIME <i class="fas fa-sort"></i></th>
                        <th>STATUS <i class="fas fa-sort"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($video_consultations as $vc): ?>
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                        <td><?php echo esc_html($vc->id); ?></td>
                        <td>
                            <div class="docs-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/' . $vc->patient_avatar); ?>" class="docs-user-avatar" alt="<?php echo esc_attr($vc->patient_name); ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($vc->patient_name); ?>&background=random'">
                                <span class="docs-user-name"><?php echo esc_html($vc->patient_name); ?></span>
                            </div>
                        </td>
                        <td><?php echo esc_html($vc->doctor_name); ?></td>
                        <td><i class="regular fa-calendar docs-icon-blue"></i> <?php echo esc_html($vc->date); ?></td>
                        <td><?php echo esc_html($vc->time); ?></td>
                        <td><span class="docs-badge <?php echo esc_attr($vc->status_class); ?>"><?php echo esc_html($vc->status); ?></span></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                <button class="docs-action-btn-sm docs-btn-delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
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
