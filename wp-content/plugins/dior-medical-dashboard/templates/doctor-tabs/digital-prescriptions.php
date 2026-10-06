<?php
// Ensure this file is part of the plugin
if (!defined('ABSPATH')) {
    exit;
}
?>
<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/view-appointment.css?v=' . time()); ?>">

<section class="dior-tab-panel" id="tab-doc-e-prescriptions" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Digital Prescriptions</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span>Digital Prescriptions</span></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="section-body">
        <div class="view-appointment-card">
            
            <!-- Table Header -->
            <div class="va-header-container">
                <div class="va-title-box">
                    <h2>Digital Prescriptions</h2>
                    <div class="va-title-line"></div>
                </div>
                
                <div class="va-actions-wrapper">
                    <div class="va-search-box">
                        <i class="fas fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search records...">
                    </div>
                    <div class="va-actions-group">
                        <button class="va-icon-btn va-btn-primary" aria-label="Add new record" onclick="document.getElementById('modal-doc-add-prescription').style.display='flex'"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg></button>
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
                            <th>Prescription ID <i class="fas fa-sort"></i></th>
                            <th>Patient Name <i class="fas fa-sort"></i></th>
                            <th>Date <i class="fas fa-sort"></i></th>
                            <th>Medications <i class="fas fa-sort"></i></th>
                            <th>Dosage <i class="fas fa-sort"></i></th>
                            <th>Frequency <i class="fas fa-sort"></i></th>
                            <th>Duration <i class="fas fa-sort"></i></th>
                            <th>Doctor <i class="fas fa-sort"></i></th>
                            <th>Status <i class="fas fa-sort"></i></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        global $wpdb;
                        $prescriptions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dior_prescriptions ORDER BY id DESC");
                        
                        if ($prescriptions && count($prescriptions) > 0) {
                            foreach ($prescriptions as $p) {
                                $badge_color = '#059669'; $badge_class = 'col-green';
                                if (strtolower($p->status) === 'completed') {
                                    $badge_color = '#2563eb'; $badge_class = 'col-blue';
                                } elseif (strtolower($p->status) === 'cancelled') {
                                    $badge_color = '#dc2626'; $badge_class = 'col-red';
                                }
                                
                                echo '<tr>';
                                echo '<td class="text-center"><input type="checkbox" class="va-checkbox"></td>';
                                echo '<td>' . esc_html($p->prescription_id) . '</td>';
                                echo '<td>
                                        <div class="va-user-profile">
                                            <img src="assets/images/users/default.png" onerror="this.src=\'https://ui-avatars.com/api/?name='.urlencode($p->patient_name).'&background=random\'" class="va-user-avatar" alt="User">
                                            <span class="va-user-name">' . esc_html($p->patient_name) . '</span>
                                        </div>
                                      </td>';
                                echo '<td><i class="far fa-calendar va-icon-blue"></i> ' . esc_html(date('M d, Y', strtotime($p->prescription_date))) . '</td>';
                                echo '<td>' . esc_html($p->medications) . '</td>';
                                echo '<td>' . esc_html($p->dosage) . '</td>';
                                echo '<td>' . esc_html($p->frequency) . '</td>';
                                echo '<td>' . esc_html($p->duration) . '</td>';
                                echo '<td>' . esc_html($p->doctor_name) . '</td>';
                                echo '<td><span class="badge-solid ' . $badge_class . '" style="background-color: transparent; color: ' . $badge_color . '; padding: 6px 12px; border-radius: 6px; font-weight: 700;">' . esc_html($p->status) . '</span></td>';
                                echo '<td>
                                        <div class="va-row-actions">
                                            <button class="va-action-btn-sm va-btn-edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button>
                                            <button class="va-action-btn-sm va-btn-delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                        </div>
                                      </td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="11" class="text-center py-4">No digital prescriptions found. Click + to add one.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="va-footer">
                <div class="va-pagination-text">0 selected / 10 total</div>
            </div>

        </div>
    </div>
</section>

<!-- Add New Prescription Modal -->
<div class="dior-modal-overlay" id="modal-doc-add-prescription" style="display: none; align-items: center; justify-content: center; z-index: 9999;">
    <div class="modal-content" style="max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header editRowModal">
            <h4 id="modal-basic-title" class="modal-title" style="margin:0;">
                <div class="table-modal-header" style="display: flex; align-items: center; gap: 12px;">
                    <img alt="avatar" class="clickable-avatar" src="assets/images/users/default.png" style="width: 40px; border-radius: 50%;">
                    <div class="modal-about">
                        <div class="fw-bold p-t-10 font-17" style="font-weight: 600;">New Record</div>
                    </div>
                </div>
            </h4>
            <button type="button" aria-label="Close" class="close" onclick="document.getElementById('modal-doc-add-prescription').style.display='none'">
                <span aria-hidden="true"><i class="fas fa-xmark"></i></span>
            </button>
        </div>
        <div class="modal-body">
            <form novalidate class="register-form" id="dior-add-prescription-form">
                <div class="row" style="display: flex; flex-wrap: wrap; margin-left: -10px; margin-right: -10px;">
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xl-6 mb-3" style="padding: 0 10px; flex: 0 0 50%; max-width: 50%;">
                        <div>
                            <label>Prescription ID <span class="text-danger">*</span></label>
                            <input name="prescription_id" class="form-control" type="text" placeholder="Prescription ID" required>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xl-6 mb-3" style="padding: 0 10px; flex: 0 0 50%; max-width: 50%;">
                        <div>
                            <label>Patient Name <span class="text-danger">*</span></label>
                            <input name="patient_name" class="form-control" type="text" placeholder="Patient Name" required>
                        </div>
                    </div>
                </div>
                
                <div class="row" style="display: flex; flex-wrap: wrap; margin-left: -10px; margin-right: -10px;">
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xl-6 mb-3" style="padding: 0 10px; flex: 0 0 50%; max-width: 50%;">
                        <div>
                            <label>Patient ID <span class="text-danger">*</span></label>
                            <input name="patient_id" class="form-control" type="text" placeholder="Patient ID" required>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xl-6 mb-3" style="padding: 0 10px; flex: 0 0 50%; max-width: 50%;">
                        <div>
                            <label>Prescription Date <span class="text-danger">*</span></label>
                            <input name="prescription_date" class="form-control" type="date" required>
                        </div>
                    </div>
                </div>
                
                <div class="row" style="display: flex; flex-wrap: wrap; margin-left: -10px; margin-right: -10px;">
                    <div class="col-lg-12 col-md-12 col-sm-12 col-xl-12 mb-3" style="padding: 0 10px; flex: 0 0 100%; max-width: 100%;">
                        <div>
                            <label>Medications <span class="text-danger">*</span></label>
                            <textarea name="medications" class="form-control" placeholder="Medications" required style="min-height: 100px; resize: vertical;"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="row" style="display: flex; flex-wrap: wrap; margin-left: -10px; margin-right: -10px;">
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xl-6 mb-3" style="padding: 0 10px; flex: 0 0 50%; max-width: 50%;">
                        <div>
                            <label>Dosage <span class="text-danger">*</span></label>
                            <input name="dosage" class="form-control" type="text" placeholder="Dosage" required>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xl-6 mb-3" style="padding: 0 10px; flex: 0 0 50%; max-width: 50%;">
                        <div>
                            <label>Frequency <span class="text-danger">*</span></label>
                            <input name="frequency" class="form-control" type="text" placeholder="Frequency" required>
                        </div>
                    </div>
                </div>
                
                <div class="row" style="display: flex; flex-wrap: wrap; margin-left: -10px; margin-right: -10px;">
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xl-6 mb-3" style="padding: 0 10px; flex: 0 0 50%; max-width: 50%;">
                        <div>
                            <label>Duration <span class="text-danger">*</span></label>
                            <input name="duration" class="form-control" type="text" placeholder="Duration" required>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xl-6 mb-3" style="padding: 0 10px; flex: 0 0 50%; max-width: 50%;">
                        <div>
                            <label>Doctor <span class="text-danger">*</span></label>
                            <input name="doctor_name" class="form-control" type="text" placeholder="Doctor" required>
                        </div>
                    </div>
                </div>
                
                <div class="row" style="display: flex; flex-wrap: wrap; margin-left: -10px; margin-right: -10px;">
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xl-6 mb-3" style="padding: 0 10px; flex: 0 0 50%; max-width: 50%;">
                        <div>
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select form-control" required>
                                <option value="" selected disabled>Please select</option>
                                <option value="Active">Active</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" id="btn-save-prescription">Submit</button>
                    <button type="button" class="btn btn-light" onclick="document.getElementById('modal-doc-add-prescription').style.display='none'">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("dior-add-prescription-form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btn-save-prescription");
            btn.innerHTML = "<i class='fas fa-spinner fa-spin'></i> Saving...";
            btn.disabled = true;

            const formData = new FormData(form);
            formData.append("action", "dior_doctor_add_prescription");

            fetch(dior_ajax.ajax_url, {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                btn.innerHTML = "Submit";
                btn.disabled = false;
                if(res.success) {
                    alert("Prescription added successfully!");
                    document.getElementById('modal-doc-add-prescription').style.display = 'none';
                    form.reset();
                    // Optionally refresh the page or dynamically insert the row
                    window.location.reload();
                } else {
                    alert("Error: " + (res.data.message || "Unknown error"));
                }
            })
            .catch(err => {
                btn.innerHTML = "Submit";
                btn.disabled = false;
                alert("Request failed. Please try again.");
                console.error(err);
            });
        });
    }
});
</script>
