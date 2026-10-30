<?php
// Ensure this file is part of the plugin
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Fetch patient list for prescription assignment dropdown
$patients_query = $wpdb->get_results("SELECT ID, display_name, user_email FROM {$wpdb->users} ORDER BY display_name ASC LIMIT 100");
$patients_list = [];
foreach ($patients_query as $pq) {
    $puid = get_user_meta($pq->ID, 'patient_id', true) ?: ('DM-' . (10000 + $pq->ID));
    $patients_list[] = [
        'id' => $pq->ID,
        'uid' => $puid,
        'name' => $pq->display_name ?: $pq->user_email
    ];
}
?>

<script>
window.dior_ajax_url = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
window.dior_doctor_nonce = "<?php echo esc_js(wp_create_nonce('dior_doctor_nonce')); ?>";
</script>

<section class="dior-tab-panel" id="tab-doc-e-prescriptions" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Digital Prescriptions</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="color: #4F46E5;"></i></a></li>
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
                    <h2><i class="fas fa-prescription-bottle-medical text-primary me-2"></i>Digital Prescriptions</h2>
                    <div class="va-title-line"></div>
                </div>

                <div class="va-actions-wrapper">
                    <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">
                        <span class="input-group-text bg-white border-end-0 text-muted ps-2 pe-1"><i
                                class="fas fa-search"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" placeholder="Search records..."
                            onkeyup="diorFilterPrescriptions(this.value)">
                    </div>
                    <div class="va-actions-group">
                        <button type="button" class="va-icon-btn va-btn-primary btn" aria-label="Add new prescription"
                            title="Add Medication Prescription (FDA Approved)" onclick="diorOpenFdaPrescriptionModal()">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="va-icon-btn va-btn-success btn" aria-label="Export to Excel"
                            title="Export CSV" onclick="diorExportPrescriptionsCsv()">
                            <i class="fas fa-file-arrow-down"></i>
                        </button>
                        <button type="button" class="va-icon-btn va-btn-info btn" aria-label="Refresh data"
                            title="Refresh" onclick="window.location.reload()">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table Responsive Wrapper -->
            <div class="va-table-wrapper">
                <table class="va-table table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 50px;" class="text-center">
                                <input type="checkbox" class="va-checkbox form-check-input">
                            </th>
                            <th>Prescription ID <i class="fas fa-sort"></i></th>
                            <th>Title <i class="fas fa-sort"></i></th>
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
                        // Restrict prescription visibility to the current doctor's records; administrators may view all.
                        if (current_user_can('manage_options')) {
                            $prescriptions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dior_prescriptions ORDER BY id DESC");
                        } else {
                            $prescriptions = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}dior_prescriptions WHERE doctor_id = %d ORDER BY id DESC",
                                get_current_user_id()
                            ));
                        }

                        if ($prescriptions && count($prescriptions) > 0) {
                            foreach ($prescriptions as $p) {
                                $badge_color = '#059669';
                                $badge_class = 'col-green';
                                if (strtolower($p->status) === 'completed') {
                                    $badge_color = '#2563eb';
                                    $badge_class = 'col-blue';
                                } elseif (strtolower($p->status) === 'cancelled') {
                                    $badge_color = '#dc2626';
                                    $badge_class = 'col-red';
                                }

                                $view_json = htmlspecialchars(json_encode([
                                    'id' => $p->prescription_id ?: $p->prescription_uid,
                                    'title' => !empty($p->title) ? $p->title : 'General Care Prescription',
                                    'patient_name' => $p->patient_name,
                                    'date' => date('M d, Y', strtotime($p->prescription_date ?: $p->created_at)),
                                    'medications' => $p->medications ?: $p->medication,
                                    'dosage' => $p->dosage,
                                    'frequency' => $p->frequency,
                                    'duration' => $p->duration,
                                    'doctor_name' => $p->doctor_name ?: 'Dr. Marcus Sterling, DO',
                                    'status' => $p->status,
                                    'instructions' => $p->instructions ?: '',
                                    'quantity' => $p->quantity ?: 'As prescribed',
                                    'refills' => $p->refills ?: 0,
                                ]), ENT_QUOTES, 'UTF-8');

                                echo '<tr>';
                                echo '<td class="text-center"><input type="checkbox" class="va-checkbox form-check-input"></td>';
                                echo '<td><strong>' . esc_html($p->prescription_id ?: $p->prescription_uid) . '</strong></td>';
                                echo '<td>
                                        <span class="dior-rx-title-badge" style="font-weight:700; color:#0f172a; display:inline-flex; align-items:center; gap:6px;">
                                            <i class="fa-solid fa-file-waveform" style="color:#d4af37; font-size:12px;"></i> ' . esc_html(!empty($p->title) ? $p->title : 'General Care') . '
                                        </span>
                                      </td>';
                                echo '<td>
                                        <div class="va-user-profile">
                                            <span class="va-user-name">' . esc_html($p->patient_name) . '</span>
                                        </div>
                                      </td>';
                                echo '<td><i class="far fa-calendar va-icon-blue"></i> ' . esc_html(date('M d, Y', strtotime($p->prescription_date ?: $p->created_at))) . '</td>';

                                $med_raw = $p->medications ?: $p->medication;
                                $med_lines = preg_split('/;\s*|\r\n|\n/', trim($med_raw));
                                $med_lines = array_filter(array_map('trim', (array) $med_lines));
                                if (empty($med_lines)) {
                                    $med_lines = [$med_raw];
                                }
                                echo '<td><div style="display:flex; flex-direction:column; gap:3px; font-weight:700; color:#0f172a;">';
                                foreach ($med_lines as $m_line) {
                                    echo '<div style="white-space:normal;">' . esc_html($m_line) . '</div>';
                                }
                                echo '</div></td>';

                                $dose_lines = preg_split('/\|\s*|;\s*|\r\n|\n/', trim($p->dosage));
                                $dose_lines = array_filter(array_map('trim', (array) $dose_lines));
                                if (empty($dose_lines)) {
                                    $dose_lines = [$p->dosage];
                                }
                                echo '<td><div style="display:flex; flex-direction:column; gap:3px;">';
                                foreach ($dose_lines as $d_line) {
                                    echo '<div style="white-space:normal;">' . esc_html($d_line) . '</div>';
                                }
                                echo '</div></td>';

                                $freq_lines = preg_split('/\|\s*|;\s*|\r\n|\n/', trim($p->frequency));
                                $freq_lines = array_filter(array_map('trim', (array) $freq_lines));
                                if (empty($freq_lines)) {
                                    $freq_lines = [$p->frequency];
                                }
                                echo '<td><div style="display:flex; flex-direction:column; gap:3px;">';
                                foreach ($freq_lines as $f_line) {
                                    echo '<div style="white-space:normal;">' . esc_html($f_line) . '</div>';
                                }
                                echo '</div></td>';

                                $dur_lines = preg_split('/\|\s*|;\s*|\r\n|\n/', trim($p->duration));
                                $dur_lines = array_filter(array_map('trim', (array) $dur_lines));
                                if (empty($dur_lines)) {
                                    $dur_lines = [$p->duration];
                                }
                                echo '<td><div style="display:flex; flex-direction:column; gap:3px;">';
                                foreach ($dur_lines as $du_line) {
                                    echo '<div style="white-space:normal;">' . esc_html($du_line) . '</div>';
                                }
                                echo '</div></td>';
                                echo '<td>' . esc_html($p->doctor_name ?: 'Dr. Marcus Sterling, DO') . '</td>';
                                echo '<td><span class="badge-solid ' . $badge_class . '" style="background-color: transparent; color: ' . $badge_color . '; padding: 6px 12px; border-radius: 6px; font-weight: 700;">' . esc_html($p->status) . '</span></td>';
                                echo '<td>
                                        <div class="va-row-actions">
                                            <button type="button" class="va-action-btn-sm va-btn-view btn" title="View Prescription Details" onclick="diorOpenPrescriptionDetailModal(' . $view_json . ')"><i class="fas fa-eye"></i></button>
                                            <button type="button" class="va-action-btn-sm va-btn-edit btn" title="Edit Prescription" onclick="diorOpenFdaPrescriptionModal()"><i class="fas fa-pen-to-square"></i></button>
                                            <button type="button" class="va-action-btn-sm va-btn-delete btn" title="Delete Prescription" onclick="diorDeletePrescriptionRow(this)"><i class="fas fa-trash-can"></i></button>
                                        </div>
                                      </td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="12" class="text-center py-4" style="color:#64748b;">No digital prescriptions found. Click + to add one.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="va-footer">
                <div class="va-pagination-text"><?php echo count($prescriptions ?: []); ?> total prescriptions</div>
            </div>

        </div>
    </div>
</section>

<!-- ============================================================== -->
<!-- DIGITAL PRESCRIPTION BUILDER MODAL (100% BOOTSTRAP 5)         -->
<!-- ============================================================== -->
<div class="modal fade" id="dior-fda-prescription-modal" tabindex="-1" aria-labelledby="dior-fda-title-text" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <input type="hidden" id="dior-fda-nonce" value="<?php echo esc_attr(wp_create_nonce('dior_doctor_nonce')); ?>">

            <!-- Clean Modal Header -->
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fa-solid fa-prescription-bottle-medical fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="dior-fda-title-text">New Clinical Prescription</h5>
                        <small class="text-secondary">FDA Approved Clinical Prescription Builder &bull; Official Digital e-Rx</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="diorCloseFdaPrescriptionModal()"></button>
            </div>

            <!-- Clean Modal Body -->
            <div class="modal-body p-4 bg-white">
                <!-- SECTION 1: Patient & Prescription Information -->
                <div class="d-flex align-items-center gap-2 fw-bold text-dark fs-6 mb-3 pb-1 border-bottom">
                    <i class="fas fa-id-card-clip text-primary"></i> 1. Prescription &amp; Patient Information
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-presc-title">Prescription Title / Diagnosis <span class="text-danger">*</span></label>
                        <input type="text" id="dior-fda-presc-title" class="form-control rounded-3"
                            placeholder="e.g. Type 2 Diabetes Management, Hypertension Plan"
                            value="General Medical Prescription" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-patient-select">Prescribe For Patient <span class="text-danger">*</span></label>
                        <select id="dior-fda-patient-select" class="form-select rounded-3" required>
                            <?php
                            if (!empty($patients_list)) {
                                foreach ($patients_list as $pat) {
                                    echo '<option value="' . esc_attr($pat['id']) . '">' . esc_html($pat['name']) . ' (' . esc_html($pat['uid']) . ')</option>';
                                }
                            } else {
                                echo '<option value="1">Default Patient (DM-10001)</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-presc-date">Prescription Date <span class="text-danger">*</span></label>
                        <input type="date" id="dior-fda-presc-date" class="form-control rounded-3"
                            value="<?php echo current_time('Y-m-d'); ?>" required>
                    </div>
                </div>

                <!-- SECTION 2: Medication Details -->
                <div class="d-flex align-items-center gap-2 fw-bold text-dark fs-6 mb-3 pb-1 border-bottom">
                    <i class="fas fa-pills text-primary"></i> 2. Medication Details &amp; Dosage
                </div>

                <div class="mb-3 position-relative">
                    <label class="form-label fw-semibold small text-secondary" for="dior-fda-med-search">Medication Name / Search (FDA Catalog) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="fas fa-magnifying-glass"></i></span>
                        <input type="text" id="dior-fda-med-search"
                            class="form-control border-start-0"
                            placeholder="Type to search FDA medications (e.g. Metformin, Sporanox, Amoxicillin...) or enter custom drug"
                            autocomplete="off" oninput="diorOnFdaSearchInput(this.value)">
                        <span class="input-group-text bg-light text-success fw-semibold small"><i class="fas fa-shield-halved me-1"></i> openFDA</span>
                    </div>
                    <!-- Autocomplete Dropdown -->
                    <div id="dior-fda-autocomplete-dropdown" class="dior-fda-autocomplete-dropdown" style="display:none; position:absolute; left:0; right:0; z-index:1055; background:#fff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.1); max-height:260px; overflow-y:auto;"></div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-strength">Strength / Concentration <span class="text-danger">*</span></label>
                        <input type="text" id="dior-fda-strength" class="form-control rounded-3"
                            placeholder="e.g. 500 mg, 100 mg/5ml" value="500 mg">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-dose-form">Dosage Form <span class="text-danger">*</span></label>
                        <select class="form-select rounded-3" id="dior-fda-dose-form"
                            onchange="window.diorFdaState.doseForm=this.value;">
                            <option value="Tablet(s)" selected>Tablet(s)</option>
                            <option value="Capsule(s)">Capsule(s)</option>
                            <option value="Syrup">Syrup</option>
                            <option value="Suspension">Suspension</option>
                            <option value="Injection">Injection</option>
                            <option value="Inhaler">Inhaler</option>
                            <option value="Drops">Drops</option>
                            <option value="Cream">Cream</option>
                            <option value="Ointment">Ointment</option>
                            <option value="Patch">Patch</option>
                            <option value="Powder">Powder</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-route">Route of Administration <span class="text-danger">*</span></label>
                        <select class="form-select rounded-3" id="dior-fda-route"
                            onchange="window.diorFdaState.route=this.value;">
                            <option value="Orally" selected>Orally (By Mouth)</option>
                            <option value="Topical">Topical (Skin)</option>
                            <option value="Sublingual">Sublingual (Under Tongue)</option>
                            <option value="Inhalation">Inhalation</option>
                            <option value="Nasal">Nasal (Nose)</option>
                            <option value="Ophthalmic">Ophthalmic (Eye)</option>
                            <option value="Otic">Otic (Ear)</option>
                            <option value="Intravenous (IV)">Intravenous (IV)</option>
                            <option value="Intramuscular (IM)">Intramuscular (IM)</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-frequency">Frequency <span class="text-danger">*</span></label>
                        <select class="form-select rounded-3" id="dior-fda-frequency"
                            onchange="window.diorFdaState.frequency=this.value;">
                            <option value="Daily (Once a day)" selected>Once Daily (QD)</option>
                            <option value="Twice Daily (BID)">Twice Daily (BID)</option>
                            <option value="Three Times Daily (TID)">Three Times Daily (TID)</option>
                            <option value="Four Times Daily (QID)">Four Times Daily (QID)</option>
                            <option value="Every 8 Hours">Every 8 Hours</option>
                            <option value="Every 12 Hours">Every 12 Hours</option>
                            <option value="Once Weekly">Once Weekly</option>
                            <option value="As Needed (PRN)">As Needed (PRN)</option>
                            <option value="Alternate Days">Alternate Days</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-intake">Meal / Intake Timing <span class="text-danger">*</span></label>
                        <select class="form-select rounded-3" id="dior-fda-intake"
                            onchange="window.diorFdaState.intake=this.value;">
                            <option value="After Food" selected>After Food (Post Meal)</option>
                            <option value="Before Food">Before Food (Empty Stomach)</option>
                            <option value="With Meals">With Meals</option>
                            <option value="At Bedtime">At Bedtime (Night)</option>
                            <option value="In the Morning">In the Morning</option>
                            <option value="Not Applicable">Not Applicable</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-duration">Treatment Duration <span class="text-danger">*</span></label>
                        <select class="form-select rounded-3" id="dior-fda-duration">
                            <option value="3 Day(s)">3 Days</option>
                            <option value="5 Day(s)">5 Days</option>
                            <option value="7 Day(s)" selected>7 Days (1 Week)</option>
                            <option value="14 Day(s)">14 Days (2 Weeks)</option>
                            <option value="30 Day(s)">30 Days (1 Month)</option>
                            <option value="60 Day(s)">60 Days (2 Months)</option>
                            <option value="90 Day(s)">90 Days (3 Months)</option>
                            <option value="Continuous / Till Review">Continuous / Till Review</option>
                        </select>
                    </div>
                </div>

                <!-- SECTION 3: Instructions & Quantity -->
                <div class="d-flex align-items-center gap-2 fw-bold text-dark fs-6 mb-3 pb-1 border-bottom">
                    <i class="fas fa-clipboard-check text-primary"></i> 3. Instructions &amp; Quantity
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-quantity">Dispense Quantity</label>
                        <input type="text" id="dior-fda-quantity" class="form-control rounded-3"
                            placeholder="e.g. 30 Tablets" value="30 Tablets">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-refills">Refills Authorized</label>
                        <select class="form-select rounded-3" id="dior-fda-refills">
                            <option value="0" selected>0 Refills</option>
                            <option value="1">1 Refill</option>
                            <option value="2">2 Refills</option>
                            <option value="3">3 Refills</option>
                            <option value="5">5 Refills</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-secondary" for="dior-fda-custom-notes">Patient Sig / Doctor's Special Instructions</label>
                        <textarea id="dior-fda-custom-notes" class="form-control rounded-3" rows="2"
                            placeholder="e.g. Take 1 tablet daily with a full glass of water after meals. Complete full prescribed course."></textarea>
                    </div>
                </div>

                <!-- Add More Medication Action Strip -->
                <div class="card bg-light border-0 rounded-3 p-3 mb-3 d-flex flex-row align-items-center justify-content-between">
                    <div>
                        <strong class="d-block text-dark small fw-bold">Prescribing Multiple Medications?</strong>
                        <span class="text-secondary small">Add this medicine to the slip and enter details for the next drug.</span>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-3 fw-semibold px-3" onclick="diorFdaAddMedicineToList(event)">
                        <i class="fas fa-plus me-1"></i> Add Medication to Slip
                    </button>
                </div>

                <!-- Multi-Medication Cart / Added Items Section -->
                <div id="dior-fda-prescribed-list-wrap" class="card border rounded-3 p-3 mb-0" style="display:none;">
                    <div class="fw-bold text-dark small mb-2 d-flex align-items-center gap-2">
                        <i class="fas fa-list-check text-primary"></i> Medications Added to this Prescription (<span id="dior-fda-prescribed-count" class="text-primary fw-bold">0</span>):
                    </div>
                    <div id="dior-fda-prescribed-items" class="d-flex flex-column gap-2">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>

            </div>

            <!-- Clean Modal Footer -->
            <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-4 fw-semibold" data-bs-dismiss="modal" onclick="diorCloseFdaPrescriptionModal()">
                    Cancel
                </button>
                <button type="button" id="dior-fda-btn-submit" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm"
                    onclick="diorSubmitFdaPrescription(event)">
                    <i class="fas fa-file-prescription me-1"></i> Save &amp; Issue Prescription <span
                        id="dior-fda-rx-btn-count" style="display:none; font-weight:700;">(1)</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- DIOR MEDICAL - VIEW PRESCRIPTION DETAILS MODAL (BOOTSTRAP 5)  -->
<!-- ============================================================== -->
<div class="modal fade" id="dior-prescription-view-modal" tabindex="-1" aria-labelledby="dior-view-rx-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fa-solid fa-file-prescription fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small" id="dior-view-rx-id">RX-000000</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small text-uppercase" id="dior-view-rx-status">Active</span>
                        </div>
                        <h5 class="modal-title fw-bold text-dark fs-5 mt-1 mb-0" id="dior-view-rx-title">General Care Prescription</h5>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="diorClosePrescriptionDetailModal()" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-white">
                <!-- Metadata Highlights Bar -->
                <div class="row g-3 p-3 bg-light rounded-3 mb-4">
                    <div class="col-6 col-md-3">
                        <span class="d-block small text-secondary fw-semibold text-uppercase"><i class="fas fa-user-circle me-1"></i> Patient</span>
                        <strong class="d-block text-dark mt-1" id="dior-view-rx-patient">diormedical (DM-10001)</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="d-block small text-secondary fw-semibold text-uppercase"><i class="fas fa-user-doctor me-1"></i> Prescriber</span>
                        <strong class="d-block text-dark mt-1" id="dior-view-rx-doctor">Dr. Marcus Sterling, DO</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="d-block small text-secondary fw-semibold text-uppercase"><i class="far fa-calendar-check me-1"></i> Date</span>
                        <strong class="d-block text-dark mt-1" id="dior-view-rx-date">Oct 14, 2026</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="d-block small text-secondary fw-semibold text-uppercase"><i class="fas fa-hospital me-1"></i> Clinic</span>
                        <strong class="d-block text-dark mt-1">Dior Medical Beverly Hills</strong>
                    </div>
                </div>

                <!-- Medication List Section -->
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-pills text-primary me-2"></i>Prescribed Medications</h6>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 small">Verified Regimen</span>
                    </div>
                    <div id="dior-view-rx-meds-list" class="d-flex flex-column gap-2">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>

                <!-- Clinical Instructions / Notes -->
                <div class="card border rounded-3 p-3 bg-white">
                    <div class="fw-bold text-dark small mb-1"><i class="fa-solid fa-notes-medical text-primary me-2"></i>Attending Physician Instructions & Notes</div>
                    <p id="dior-view-rx-notes" class="text-secondary small mb-0">Take as directed with full glass of water.</p>
                </div>
            </div>

            <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-between align-items-center">
                <span class="small text-secondary"><i class="fas fa-lock text-success me-1"></i> Digitally Verified &amp; Signed Telehealth Record</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3 fw-semibold" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                    <button type="button" class="btn btn-primary rounded-3 px-4 fw-semibold" data-bs-dismiss="modal" onclick="diorClosePrescriptionDetailModal()">
                        <i class="fas fa-check me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Filter Prescriptions Table
    function diorFilterPrescriptions(val) {
        var filter = (val || '').toLowerCase();
        var rows = document.querySelectorAll('#tab-doc-e-prescriptions table.va-table tbody tr');
        rows.forEach(function (row) {
            var txt = (row.textContent || '').toLowerCase();
            row.style.display = txt.indexOf(filter) !== -1 ? '' : 'none';
        });
    }

    // Export Prescriptions to CSV
    function diorExportPrescriptionsCsv() {
        var rows = document.querySelectorAll('#tab-doc-e-prescriptions table.va-table tr');
        var csv = [];
        rows.forEach(function (row) {
            var cols = row.querySelectorAll('th, td');
            var rowData = [];
            cols.forEach(function (col, idx) {
                if (idx === 0 || idx === cols.length - 1) return; // skip checkbox and actions
                rowData.push('"' + col.innerText.replace(/"/g, '""').trim() + '"');
            });
            if (rowData.length) csv.push(rowData.join(','));
        });
        var blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'digital-prescriptions-' + new Date().toISOString().split('T')[0] + '.csv';
        a.click();
    }

    // Delete Prescription Row (Visual + Feedback)
    function diorDeletePrescriptionRow(btn) {
        Swal.fire({
            title: 'Delete Prescription?',
            text: 'Are you sure you want to delete this prescription record?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#64748B',
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            var tr = btn.closest('tr');
            if (tr) {
                tr.style.opacity = '0.4';
                setTimeout(function () { tr.remove(); }, 300);
                Swal.fire({ icon: 'success', title: 'Deleted', text: 'Prescription record removed.', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
            }
        });
    }
</script>