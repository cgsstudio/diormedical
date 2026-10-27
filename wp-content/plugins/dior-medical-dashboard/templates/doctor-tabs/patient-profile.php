<?php
/**
 * Dior Medical - Doctor Dashboard: Professional Patient Profile & Chart
 * 100% Native Bootstrap 5 Component & Utility Layout (Strictly No Custom CSS & No Inline Styles)
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$pp_doctor_id = get_current_user_id();

// Retrieve accessible patient list for the doctor selector
if (current_user_can('manage_options')) {
    $pp_patients = $wpdb->get_results(
        "SELECT user_id, first_name, last_name, patient_uid 
         FROM {$wpdb->prefix}dior_patients 
         WHERE status <> 'deleted' 
         ORDER BY first_name ASC LIMIT 500",
        ARRAY_A
    );
} else {
    $pp_patients = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT p.user_id, p.first_name, p.last_name, p.patient_uid 
         FROM {$wpdb->prefix}dior_patients p 
         LEFT JOIN {$wpdb->prefix}dior_appointments a ON a.patient_id = p.user_id 
         WHERE p.status <> 'deleted' 
           AND (a.doctor_id = %d OR p.user_id IN (SELECT DISTINCT patient_id FROM {$wpdb->prefix}dior_prescriptions WHERE doctor_id = %d))
         ORDER BY p.first_name ASC LIMIT 500",
        $pp_doctor_id,
        $pp_doctor_id
    ), ARRAY_A);
}

$pp_nonce = wp_create_nonce('dior_doctor_nonce');
$pp_ajax_url = admin_url('admin-ajax.php');
?>

<section class="dior-tab-panel" id="tab-doc-patients-profile" style="display:none;" aria-labelledby="pp-page-title">

    <!-- Top Action & Selection Header -->
    <div class="row g-3 align-items-center justify-content-between mb-4">
        <div class="col-12 col-lg-auto">
            <div id="pp-page-title" class="h6 mb-1 fw-bold text-dark">
                <i class="fas fa-id-card text-primary me-2"></i>Patient Profile &amp; Clinical Chart
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small text-muted">
                    <li class="breadcrumb-item">Patients</li>
                    <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Chart &amp; Medical Record</li>
                </ol>
            </nav>
        </div>

        <!-- Patient Selector Dropdown -->
        <div class="col-12 col-lg-auto">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="input-group shadow-sm rounded-3 w-auto flex-grow-1">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="fas fa-hospital-user"></i>
                    </span>
                    <select class="form-select border-start-0 ps-1" id="pp-patient-dropdown" aria-label="Select patient to view chart">
                        <option value="">Select Patient Record…</option>
                        <?php foreach ((array)$pp_patients as $pp): 
                            $pp_name = trim(($pp['first_name'] ?? '') . ' ' . ($pp['last_name'] ?? ''));
                        ?>
                            <option value="<?php echo (int)$pp['user_id']; ?>">
                                <?php echo esc_html($pp_name ?: 'Patient #' . $pp['user_id']); ?> 
                                (<?php echo esc_html($pp['patient_uid'] ?: 'ID: ' . $pp['user_id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-primary px-3 fw-semibold shadow-sm" id="pp-load-patient-btn">
                        <i class="fas fa-folder-open me-1"></i> Open Chart
                    </button>
                </div>
                <button type="button" class="btn btn-outline-secondary rounded-3 shadow-sm px-3" id="pp-refresh-btn" title="Refresh Current Record">
                    <i class="fas fa-rotate-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-all" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-all');">
            <i class="fas fa-users"></i> All Patients
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-add" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-add');">
            <i class="fas fa-user-plus"></i> Add Patient
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-edit" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-edit');">
            <i class="fas fa-user-pen"></i> Edit Patient
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-patients-records" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-records');">
            <i class="fas fa-clipboard-user"></i> Patient Records
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-patients-profile" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-patients-profile');">
            <i class="fas fa-id-card"></i> Patient Profile
        </button>
    </div>

    <!-- Empty State (When no patient is loaded) -->
    <div class="card border-0 shadow-sm rounded-4 text-center py-5 mb-4 bg-white" id="pp-empty-state">
        <div class="card-body py-5">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-3 p-4 fs-1 lh-1">
                <i class="fas fa-user-doctor"></i>
            </div>
            <div class="h5 fw-bold text-dark mb-2">Select a Patient to Open Chart</div>
            <p class="text-secondary mx-auto mb-4 col-12 col-md-8 col-lg-6">
                Choose any patient from the dropdown above to view complete personal details, uploaded medical documents, previous prescriptions, medical history, and clinical questionnaire responses.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-outline-primary rounded-3 px-4 fw-semibold" onclick="document.getElementById('pp-patient-dropdown').focus();">
                    <i class="fas fa-arrow-up me-1"></i> Choose from Patient List
                </button>
            </div>
        </div>
    </div>

    <!-- Active Patient Chart Workspace -->
    <div id="pp-chart" hidden>

        <!-- Patient Hero Summary Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden bg-white">
            <div class="p-3 p-md-4 bg-white border-bottom d-flex flex-column flex-xxl-row align-items-start align-items-xxl-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <img data-pp="avatar" src="" alt="Patient photo" width="72" height="72" class="rounded-circle border border-2 border-white shadow-sm object-fit-cover" style="width: 72px; height: 72px;">
                        <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-white rounded-circle" title="Active Record"></span>
                    </div>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="fs-5 fw-bold text-dark mb-0 text-nowrap" data-pp="name" style="font-size: 1.25rem !important;">Patient Name</div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 small fw-semibold" data-pp="status">Active</span>
                            <span class="badge bg-light text-secondary border rounded-pill px-3 py-1 small fw-bold font-monospace" data-pp="uid">UID: —</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Action Buttons -->
                <div class="d-flex flex-wrap gap-2 w-100 w-xxl-auto justify-content-start justify-content-xxl-end">
                    <button type="button" class="btn btn-primary rounded-3 fw-semibold shadow-sm px-3 py-2 d-inline-flex align-items-center" onclick="ppSwitchTab('prescriptions')">
                        <i class="fas fa-prescription me-2"></i> New Prescription
                    </button>
                    <button type="button" class="btn btn-outline-primary rounded-3 fw-semibold px-3 py-2 d-inline-flex align-items-center" onclick="ppSwitchTab('consultation')">
                        <i class="fas fa-notes-medical me-2"></i> Clinical Note
                    </button>
                    <button type="button" class="btn btn-outline-secondary rounded-3 fw-semibold px-3 py-2 d-inline-flex align-items-center" onclick="ppSwitchTab('documents')">
                        <i class="fas fa-cloud-arrow-up me-2"></i> Upload Document
                    </button>
                </div>
            </div>

            <!-- 4 Quick Stats Ribbon -->
            <div class="row g-0 text-center border-top bg-white">
                <div class="col-6 col-md-3 p-3 border-end">
                    <div class="text-secondary small fw-semibold text-uppercase">Appointments</div>
                    <div class="fs-4 fw-bold text-primary mt-1" data-pp="visit-count">0</div>
                </div>
                <div class="col-6 col-md-3 p-3 border-end">
                    <div class="text-secondary small fw-semibold text-uppercase">Prescriptions</div>
                    <div class="fs-4 fw-bold text-success mt-1" data-pp="prescription-count">0</div>
                </div>
                <div class="col-6 col-md-3 p-3 border-end">
                    <div class="text-secondary small fw-semibold text-uppercase">Documents</div>
                    <div class="fs-4 fw-bold text-info mt-1" data-pp="document-count">0</div>
                </div>
                <div class="col-6 col-md-3 p-3">
                    <div class="text-secondary small fw-semibold text-uppercase">Consultation Notes</div>
                    <div class="fs-4 fw-bold text-dark mt-1" data-pp="consultation-count">0</div>
                </div>
            </div>
        </div>

        <!-- Main Chart Card with Tab Navigation -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
            
            <!-- Tab Navigation Header -->
            <div class="card-header bg-white border-bottom p-3">
                <ul class="nav nav-pills flex-nowrap overflow-auto gap-2" id="pp-nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link active rounded-3 fw-semibold px-3 py-2 text-nowrap" data-pp-tab="overview">
                            <i class="fas fa-user-circle me-2"></i> Personal Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link rounded-3 fw-semibold px-3 py-2 text-nowrap" data-pp-tab="prescriptions">
                            <i class="fas fa-prescription-bottle-medical me-2 text-danger"></i> Prescriptions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link rounded-3 fw-semibold px-3 py-2 text-nowrap" data-pp-tab="questionnaire">
                            <i class="fas fa-clipboard-question me-2 text-warning"></i> Medical Questionnaire
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link rounded-3 fw-semibold px-3 py-2 text-nowrap" data-pp-tab="clinical">
                            <i class="fas fa-heart-pulse me-2 text-primary"></i> Medical History
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link rounded-3 fw-semibold px-3 py-2 text-nowrap" data-pp-tab="documents">
                            <i class="fas fa-folder-open me-2 text-info"></i> Uploaded Documents
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link rounded-3 fw-semibold px-3 py-2 text-nowrap" data-pp-tab="consultation">
                            <i class="fas fa-notes-medical me-2 text-success"></i> Clinical Notes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link rounded-3 fw-semibold px-3 py-2 text-nowrap" data-pp-tab="visits">
                            <i class="fas fa-calendar-days me-2 text-secondary"></i> Visit History
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Tab Panes Container -->
            <div class="card-body p-3 p-md-4">

                <!-- ============================================================== -->
                <!-- TAB 1: PERSONAL INFORMATION & DEMOGRAPHICS -->
                <!-- ============================================================== -->
                <div class="tab-pane fade show active" data-pp-pane="overview">
                    <div class="row g-4">
                        <!-- Demographics List -->
                        <div class="col-12 col-xl-7">
                            <div class="card border rounded-4 p-3 p-md-4 h-100 bg-white">
                                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center">
                                    <i class="fas fa-address-card text-primary me-2"></i>Patient Demographics
                                </h5>
                                <div class="row g-3">
                                    <div class="col-12 col-sm-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Full Legal Name</span>
                                            <strong class="text-dark fs-6" data-pp="name">—</strong>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Patient UID</span>
                                            <strong class="text-primary fs-6 font-monospace" data-pp="uid">—</strong>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Email Address</span>
                                            <strong class="text-dark" data-pp="email">—</strong>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Mobile Phone</span>
                                            <strong class="text-dark" data-pp="phone">—</strong>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Date of Birth &amp; Age</span>
                                            <strong class="text-dark"><span data-pp="dob">—</span> (<span data-pp="age">—</span>)</strong>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Gender &amp; Blood Group</span>
                                            <strong class="text-dark"><span data-pp="gender">—</span> &bull; <span data-pp="blood">—</span></strong>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Marital Status</span>
                                            <strong class="text-dark" data-pp="marital">—</strong>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">National ID / SSN</span>
                                            <strong class="text-dark" data-pp="national-id">—</strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="p-3 bg-light rounded-3">
                                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Full Residential Address</span>
                                            <strong class="text-dark" data-pp="address">—</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Safety & Emergency Card -->
                        <div class="col-12 col-xl-5">
                            <div class="d-flex flex-column gap-3 h-100">
                                <!-- Emergency Contact -->
                                <div class="card border rounded-4 p-3 p-md-4 bg-white shadow-none">
                                    <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center">
                                        <i class="fas fa-user-shield text-danger me-2"></i>Emergency Contact
                                    </h5>
                                    <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light mb-3">
                                        <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-3 fs-5 lh-1">
                                            <i class="fas fa-phone-volume"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <strong class="d-block text-dark fs-6" data-pp="emergency-name">—</strong>
                                            <span class="small text-secondary" data-pp="emergency-relation">Emergency Contact</span>
                                        </div>
                                        <a data-pp="emergency-phone" href="#" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                                            <i class="fas fa-phone me-1"></i> Call
                                        </a>
                                    </div>
                                </div>

                                <!-- Critical Allergies Notice -->
                                <div class="card border rounded-4 p-3 p-md-4 bg-warning-subtle border-warning-subtle shadow-none">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="text-warning fs-3 flex-shrink-0">
                                            <i class="fas fa-triangle-exclamation"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1">Allergy &amp; Safety Summary</h6>
                                            <p class="mb-0 text-dark small" data-pp="allergy-summary">
                                                No allergy information recorded.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preferred Pharmacy -->
                                <div class="card border rounded-4 p-3 p-md-4 bg-white shadow-none flex-grow-1">
                                    <h5 class="fw-bold text-dark mb-2 d-flex align-items-center">
                                        <i class="fas fa-prescription-bottle text-success me-2"></i>Preferred Pharmacy
                                    </h5>
                                    <div class="p-3 bg-light rounded-3">
                                        <strong class="text-dark d-block fs-6" data-pp-q="pharmacy_name">Preferred Pharmacy on file</strong>
                                        <span class="text-secondary small d-block mt-1" data-pp-q="pharmacy_location">Standard clinical fulfillment route</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB 2: DEDICATED PRESCRIPTION MANAGEMENT TAB -->
                <!-- ============================================================== -->
                <div class="tab-pane fade" data-pp-pane="prescriptions" hidden>
                    <div class="row g-4 align-items-start">
                        
                        <!-- Left Column: Create New Prescription Form -->
                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-light border">
                                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                    <div>
                                        <span class="text-primary small fw-bold text-uppercase">Direct Physician Order</span>
                                        <h5 class="fw-bold text-dark mb-0 mt-1">
                                            <i class="fas fa-prescription text-primary me-2"></i>Write Prescription
                                        </h5>
                                    </div>
                                    <span class="badge bg-primary text-white rounded-pill px-3 py-1 small">E-Rx Ready</span>
                                </div>

                                <form id="pp-prescription-form">
                                    <input type="hidden" name="patient_id" id="pp-rx-patient-id" value="">

                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark" for="pp-rx-medication">
                                            Medication Name &amp; Formulation <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control rounded-3" id="pp-rx-medication" required placeholder="e.g. Amoxicillin 500mg Capsule, Albuterol Inhaler">
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark" for="pp-rx-dosage">
                                                Dosage <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control rounded-3" id="pp-rx-dosage" required placeholder="e.g. 500 mg, 1 puff">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark" for="pp-rx-quantity">
                                                Quantity / Dispense
                                            </label>
                                            <input type="text" class="form-control rounded-3" id="pp-rx-quantity" value="30 capsules" placeholder="e.g. 30 capsules, 1 bottle">
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark" for="pp-rx-frequency">
                                                Frequency <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select rounded-3" id="pp-rx-frequency" required>
                                                <option value="Once daily">Once daily (QD)</option>
                                                <option value="Twice daily" selected>Twice daily (BID)</option>
                                                <option value="Three times daily">Three times daily (TID)</option>
                                                <option value="Four times daily">Four times daily (QID)</option>
                                                <option value="Every 8 hours">Every 8 hours</option>
                                                <option value="Every 12 hours">Every 12 hours</option>
                                                <option value="At bedtime">At bedtime (QHS)</option>
                                                <option value="As needed">As needed (PRN)</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark" for="pp-rx-duration">
                                                Duration <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select rounded-3" id="pp-rx-duration" required>
                                                <option value="3 days">3 days</option>
                                                <option value="5 days">5 days</option>
                                                <option value="7 days" selected>7 days</option>
                                                <option value="10 days">10 days</option>
                                                <option value="14 days">14 days</option>
                                                <option value="30 days">30 days</option>
                                                <option value="90 days">90 days</option>
                                                <option value="Ongoing">Ongoing / Maintenance</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark" for="pp-rx-refills">
                                                Authorized Refills
                                            </label>
                                            <input type="number" class="form-control rounded-3" id="pp-rx-refills" min="0" max="12" value="0">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark" for="pp-rx-status">
                                                Prescription Status
                                            </label>
                                            <select class="form-select rounded-3" id="pp-rx-status">
                                                <option value="Active" selected>Active</option>
                                                <option value="Completed">Completed</option>
                                                <option value="Cancelled">Cancelled</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark" for="pp-rx-instructions">
                                            Directions / Patient Instructions (SIG)
                                        </label>
                                        <textarea class="form-control rounded-3" id="pp-rx-instructions" rows="3" placeholder="Take 1 capsule by mouth every 12 hours with meals and plenty of water until finished."></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold py-2 shadow-sm" id="pp-submit-rx-btn">
                                        <i class="fas fa-paper-plane me-2"></i> Issue &amp; Send Prescription
                                    </button>
                                    <div class="form-text text-center text-muted small mt-2">
                                        Prescription is routed to patient chart and designated fulfillment pharmacy.
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Right Column: Previous Prescriptions & Records List -->
                        <div class="col-12 col-xl-7">
                            <div class="card border rounded-4 p-3 p-md-4 bg-white shadow-none">
                                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom gap-2">
                                    <div>
                                        <span class="text-secondary small fw-bold text-uppercase">Patient Medication Record</span>
                                        <h5 class="fw-bold text-dark mb-0 mt-1">
                                            <i class="fas fa-history text-primary me-2"></i>Previous Prescriptions
                                        </h5>
                                    </div>
                                    <!-- Search filter for prescriptions -->
                                    <div class="input-group input-group-sm w-auto">
                                        <span class="input-group-text bg-white text-muted border-end-0"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control border-start-0" id="pp-rx-search" placeholder="Filter medicines..." onkeyup="ppFilterRxTable()">
                                    </div>
                                </div>

                                <div class="table-responsive mb-0">
                                    <table class="table table-hover align-middle mb-0" id="pp-rx-table">
                                        <thead class="bg-light text-secondary small text-uppercase">
                                            <tr>
                                                <th>Medication &amp; Dosage</th>
                                                <th>Frequency / Duration</th>
                                                <th>Directions (SIG)</th>
                                                <th>Status</th>
                                                <th>Date Prescribed</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody data-pp-rows="prescriptions">
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-5">
                                                    <i class="fas fa-pills fs-3 d-block mb-2 text-secondary opacity-50"></i>
                                                    Select a patient to load prescription history.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB 3: MEDICAL QUESTIONNAIRE & INTAKE (HIPAAtizer) -->
                <!-- ============================================================== -->
                <div class="tab-pane fade" data-pp-pane="questionnaire" hidden>
                    
                    <!-- Questionnaire Empty State -->
                    <div class="text-center py-5" id="pp-questionnaire-empty">
                        <div class="text-secondary fs-1 mb-3"><i class="fas fa-clipboard-question"></i></div>
                        <h4 class="h5 fw-bold text-dark">No Medical Questionnaire Submitted</h4>
                        <p class="text-secondary small mb-0">When this patient submits their HIPAAtizer clinical questionnaire, complete answers and modules will display here.</p>
                    </div>

                    <!-- Questionnaire Content (Rendered dynamically matching exact reference design) -->
                    <div id="pp-questionnaire-content" hidden>
                        
                        <!-- ── TOP HEADER CARD: PATIENT IDENTITY & CLINICAL PRIORITY ── -->
                        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white" style="border: 1px solid #E2E8F0 !important;">
                            <!-- Top Row -->
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 border-bottom" style="border-color: #E2E8F0 !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <!-- Blue Initials Badge -->
                                    <div id="pp-q-avatar-box" class="rounded-3 text-white fw-bold d-flex align-items-center justify-content-center shadow-sm" style="width: 52px; height: 52px; font-size: 20px; background: #1d4ed8; flex-shrink: 0;">
                                        <span data-pp-q="initials">T15</span>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                            <h4 class="fw-bold text-dark mb-0 fs-4" data-pp-q="full_name">test15</h4>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                                                <i class="fas fa-circle me-1" style="font-size: 7px;"></i> Submitted &amp; Signed
                                            </span>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold" data-pp-q="consultation_type_badge">
                                                Video Consultation
                                            </span>
                                        </div>
                                        <div class="small text-secondary fw-medium d-flex align-items-center flex-wrap gap-2" style="font-size: 13px;">
                                            <span>Submission ID: <strong class="text-dark font-monospace" data-pp-q="submission_id_short">ee65bf3a-0731-4f4c-8a44</strong></span>
                                            <span class="text-muted">&bull;</span>
                                            <span>Submitted: <strong class="text-dark" data-pp-q="submitted_at">Oct 16, 2026, 11:46 AM</strong></span>
                                            <span class="text-muted">&bull;</span>
                                            <span>Client IP: <strong class="text-dark" data-pp-q="client_ip">110.226.115.101</strong></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Side: Clinical Priority Box -->
                                <div class="rounded-4 p-2.5 px-3 border d-flex align-items-center gap-2.5 shadow-sm" id="pp-q-priority-box" style="background: #FEF9C3; border-color: #FEF08A !important;">
                                    <span class="rounded-circle d-inline-block" id="pp-q-priority-dot" style="width: 10px; height: 10px; background: #EAB308;"></span>
                                    <div>
                                        <div class="fw-bold small text-dark mb-0" id="pp-q-priority-title" style="color: #854D0E !important;" data-pp-q="priority_title">Clinical Priority: Moderate</div>
                                        <div class="small" id="pp-q-priority-sub" style="font-size: 11px; color: #A16207;" data-pp-q="priority_sub">Asthma with active fever reported</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Row: 4 Metric Columns -->
                            <div class="row g-3 pt-3">
                                <div class="col-6 col-md-3">
                                    <span class="small text-uppercase text-secondary fw-bold d-block mb-1" style="font-size: 11px; letter-spacing: 0.5px;">DOB / AGE</span>
                                    <div class="fw-bold text-dark fs-6" data-pp-q="dob_age">11/11/2002 (23 yrs)</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <span class="small text-uppercase text-secondary fw-bold d-block mb-1" style="font-size: 11px; letter-spacing: 0.5px;">ASSIGNED SEX</span>
                                    <div class="fw-bold text-dark fs-6" data-pp-q="sex_at_birth">Male</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <span class="small text-uppercase text-secondary fw-bold d-block mb-1" style="font-size: 11px; letter-spacing: 0.5px;">SERVICE REQUESTED</span>
                                    <div class="fw-bold text-primary fs-6" data-pp-q="selected_service">Asthma Care</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <span class="small text-uppercase text-secondary fw-bold d-block mb-1" style="font-size: 11px; letter-spacing: 0.5px;">REFERRAL CHANNEL</span>
                                    <div class="fw-bold text-dark fs-6" data-pp-q="referral_source">Friend or Family</div>
                                </div>
                            </div>
                        </div>

                        <!-- ── MAIN 2-COLUMN CLINICAL GRID ── -->
                        <div class="row g-4 align-items-start">

                            <!-- ── LEFT COLUMN (col-12 col-xl-4) ── -->
                            <div class="col-12 col-xl-4 d-flex flex-column gap-4">

                                <!-- Card 1: Patient Demographics -->
                                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white" style="border: 1px solid #E2E8F0 !important;">
                                    <h6 class="fw-bold text-dark text-uppercase small mb-3 pb-2 border-bottom d-flex align-items-center gap-2">
                                        <i class="far fa-user text-primary fs-6"></i>
                                        <span>Patient Demographics</span>
                                    </h6>
                                    <div class="d-flex flex-column gap-2.5 small">
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-secondary">Full Name</span>
                                            <strong class="text-dark" data-pp-q="full_name_demo">test15</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-secondary">Email</span>
                                            <span class="text-primary fw-medium" data-pp-q="email">test15@gmail.com</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-secondary">Phone Number</span>
                                            <span class="text-dark fw-medium" data-pp-q="phone_number">(123) 456-7890</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-secondary">Preferred Language</span>
                                            <strong class="text-dark" data-pp-q="preferred_language">English</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-secondary">Age Eligibility (18+)</span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold" data-pp-q-badge="is_adult">Yes (Adult)</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-secondary">Pregnant Status</span>
                                            <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 small fw-semibold" data-pp-q-badge="is_pregnant">No</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 2: Designated Pharmacy -->
                                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white" style="border: 1px solid #E2E8F0 !important;">
                                    <h6 class="fw-bold text-dark text-uppercase small mb-3 pb-2 border-bottom d-flex align-items-center gap-2">
                                        <i class="fas fa-prescription-bottle-medical text-primary fs-6"></i>
                                        <span>Designated Pharmacy</span>
                                    </h6>
                                    <div class="small">
                                        <div class="mb-3">
                                            <span class="text-secondary d-block mb-1">Pharmacy Name</span>
                                            <div class="fw-bold text-dark fs-6" data-pp-q="pharmacy_name">test</div>
                                        </div>
                                        <div>
                                            <span class="text-secondary d-block mb-1">City / ZIP Code</span>
                                            <div class="fw-bold text-dark fs-6" data-pp-q="pharmacy_location">142536</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 3: Consent & Signature -->
                                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white" style="border: 1px solid #E2E8F0 !important;">
                                    <h6 class="fw-bold text-dark text-uppercase small mb-3 pb-2 border-bottom d-flex align-items-center gap-2">
                                        <i class="fas fa-play text-success fs-6"></i>
                                        <span>Consent &amp; Signature</span>
                                    </h6>
                                    <div class="small d-flex flex-column gap-2.5">
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-secondary">HIPAA &amp; Telehealth Terms</span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold">AGREED</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-secondary">E-Signature</span>
                                            <strong class="text-dark font-monospace" data-pp-q="e_signature">test</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-secondary">Signed Date</span>
                                            <span class="text-dark" data-pp-q="signature_date">26-10-2026</span>
                                        </div>
                                        <div class="pt-2 d-flex gap-2">
                                            <a href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm rounded-3 fw-semibold w-100" data-pp-q-link="pdf">
                                                <i class="fas fa-file-pdf me-1 text-danger"></i> View PDF
                                            </a>
                                            <a href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm rounded-3 fw-semibold w-100" data-pp-q-link="submission">
                                                <i class="fas fa-external-link-alt me-1"></i> Form
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 4: Social History & Lifestyle -->
                                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white" style="border: 1px solid #E2E8F0 !important;">
                                    <h6 class="fw-bold text-dark text-uppercase small mb-3 pb-2 border-bottom d-flex align-items-center gap-2">
                                        <i class="fas fa-user-group text-primary fs-6"></i>
                                        <span>Social History &amp; Lifestyle</span>
                                    </h6>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="p-2.5 bg-light rounded-3 border h-100">
                                                <small class="text-secondary d-block mb-1">Tobacco / Vaping</small>
                                                <strong class="text-dark d-block" data-pp-q="tobacco_use">—</strong>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2.5 bg-light rounded-3 border h-100">
                                                <small class="text-secondary d-block mb-1">Alcohol Use</small>
                                                <strong class="text-dark d-block" data-pp-q="alcohol_use">—</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- ── RIGHT COLUMN (col-12 col-xl-8) ── -->
                            <div class="col-12 col-xl-8 d-flex flex-column gap-4">

                                <!-- Card 1: Chief Complaint & Symptoms (Exact Match) -->
                                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white" style="border: 1px solid #E2E8F0 !important;">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <h6 class="fw-bold text-dark text-uppercase small mb-0 d-flex align-items-center gap-2">
                                            <i class="far fa-file-lines text-primary fs-6"></i>
                                            <span>Chief Complaint &amp; Symptoms</span>
                                        </h6>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 small fw-semibold">
                                            Form 2: Symptoms
                                        </span>
                                    </div>

                                    <!-- 2 Side-by-Side Large Soft Boxes -->
                                    <div class="row g-3 mb-3">
                                        <div class="col-12 col-md-6">
                                            <div class="p-3 rounded-4 border bg-light h-100" style="background: #F8FAFC !important; border-color: #E2E8F0 !important;">
                                                <span class="small text-secondary fw-semibold d-block mb-1">Reason for Visit</span>
                                                <div class="fw-bold text-dark fs-5" data-pp-q="reason_for_visit">test</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="p-3 rounded-4 border bg-light h-100" style="background: #F8FAFC !important; border-color: #E2E8F0 !important;">
                                                <span class="small text-secondary fw-semibold d-block mb-1">Symptom Description</span>
                                                <div class="fw-bold text-dark fs-5" data-pp-q="symptom_description">test</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 4-Metric Horizontal Indicators -->
                                    <div class="row g-3 pt-2">
                                        <div class="col-6 col-md-3">
                                            <span class="small text-secondary fw-semibold d-block mb-1">Duration</span>
                                            <div class="fw-bold text-dark fs-6" data-pp-q="symptom_duration">1-3 days</div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <span class="small text-secondary fw-semibold d-block mb-1">Severity</span>
                                            <div class="fw-bold fs-6" style="color: #D97706;" data-pp-q="symptom_severity_text">Moderate</div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <span class="small text-secondary fw-semibold d-block mb-1">Has Fever?</span>
                                            <div class="fw-bold fs-6" data-pp-q-html="has_fever_dot">
                                                <span class="text-danger"><i class="fas fa-circle me-1" style="font-size: 8px;"></i> Yes</span>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <span class="small text-secondary fw-semibold d-block mb-1">Tried Treatment?</span>
                                            <div class="fw-semibold text-dark fs-6" data-pp-q="tried_treatment">No</div>
                                        </div>
                                    </div>

                                    <!-- Red Flags Safe Green Banner (Exact Match) -->
                                    <div class="p-2.5 px-3 rounded-4 border border-success-subtle d-flex align-items-center justify-content-between mt-3" id="pp-q-safe-banner" style="background: #ECFDF5; border-color: #A7F3D0 !important;">
                                        <div class="d-flex align-items-center gap-2 small fw-semibold text-success">
                                            <i class="fas fa-play text-success" style="font-size: 11px;"></i>
                                            <span>No emergency red flags (Chest pain, severe dyspnea, confusion) reported.</span>
                                        </div>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-bold">CLEARED</span>
                                    </div>

                                    <!-- Red Flags Danger Alert Banner (if reported) -->
                                    <div class="alert alert-danger d-flex align-items-center gap-3 mt-3 mb-0 rounded-4 border-danger p-3" id="pp-q-redflag-box" hidden>
                                        <i class="fas fa-triangle-exclamation fs-3 text-danger"></i>
                                        <div>
                                            <strong class="d-block text-danger">Emergency Red Flag Symptoms Reported!</strong>
                                            <span data-pp-q="red_flags">Trouble breathing, chest pain, or acute confusion reported.</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 2: ASTHMA SPECIFIC ASSESSMENT (Condition-Specific Clinical Protocol) -->
                                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white" id="pp-q-asthma-card" data-pp-module="asthma" style="border: 1px solid #E2E8F0 !important;">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: #FCE7F3; color: #DB2777;">
                                                <i class="fas fa-lungs fs-6"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-dark mb-0">ASTHMA SPECIFIC ASSESSMENT</h6>
                                                <span class="small text-secondary" style="font-size: 12px;">Condition-specific clinical protocol fields</span>
                                            </div>
                                        </div>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 small fw-semibold">
                                            Active Module
                                        </span>
                                    </div>

                                    <!-- 4 Soft Boxes Grid (2x2) -->
                                    <div class="row g-3">
                                        <div class="col-12 col-md-6">
                                            <div class="p-3 rounded-4 border bg-light h-100" style="background: #F8FAFC !important; border-color: #E2E8F0 !important;">
                                                <span class="small text-secondary fw-semibold d-block mb-1">Rescue Inhaler Frequency</span>
                                                <div class="fw-bold text-dark fs-6" data-pp-q="asthma_rescue_use">1-2 times a week</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="p-3 rounded-4 border bg-light h-100" style="background: #F8FAFC !important; border-color: #E2E8F0 !important;">
                                                <span class="small text-secondary fw-semibold d-block mb-1">Nighttime Awakenings</span>
                                                <div class="fw-bold text-dark fs-6" data-pp-q="asthma_night_text">No</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="p-3 rounded-4 border bg-light h-100" style="background: #F8FAFC !important; border-color: #E2E8F0 !important;">
                                                <span class="small text-secondary fw-semibold d-block mb-1">ER / Hospital Visits in Past Year</span>
                                                <div class="fw-bold text-dark fs-6" data-pp-q="asthma_er_visit_text">No</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="p-3 rounded-4 border bg-light h-100" style="background: #F8FAFC !important; border-color: #E2E8F0 !important;">
                                                <span class="small text-secondary fw-semibold d-block mb-1">Current Wheezing or Dyspnea</span>
                                                <div class="fw-bold text-dark fs-6" data-pp-q="asthma_wheeze_text">No</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 3: Other Condition Modules (Shown Dynamically If Patient Selected Other Service) -->
                                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white d-none" id="pp-q-other-module-card" style="border: 1px solid #E2E8F0 !important;">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                            <i class="fas fa-layer-group text-primary"></i>
                                            <span data-pp-q="other_module_title">Specialized Clinical Assessment</span>
                                        </h6>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 small fw-semibold">Active Module</span>
                                    </div>
                                    <div class="row g-3" id="pp-q-other-module-content">
                                        <!-- Populated via JS if not asthma -->
                                    </div>
                                </div>

                                <!-- Card 4: Past Medical History & Chronic Conditions -->
                                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white" style="border: 1px solid #E2E8F0 !important;">
                                    <h6 class="fw-bold text-dark text-uppercase small mb-3 pb-2 border-bottom d-flex align-items-center gap-2">
                                        <i class="fas fa-heart-pulse text-danger fs-6"></i>
                                        <span>Medical History &amp; Chronic Conditions</span>
                                    </h6>
                                    
                                    <div class="row g-2 text-center mb-3">
                                        <div class="col-6 col-md-4 col-xl-2">
                                            <div class="p-2.5 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                                                <small class="text-secondary fw-semibold d-block mb-1">Hypertension</small>
                                                <div data-pp-q-html="hx_hypertension">
                                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 small" data-pp-q="hx_hypertension">No</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-xl-2">
                                            <div class="p-2.5 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                                                <small class="text-secondary fw-semibold d-block mb-1">Diabetes</small>
                                                <div data-pp-q-html="hx_diabetes">
                                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 small" data-pp-q="hx_diabetes">No</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-xl-2">
                                            <div class="p-2.5 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                                                <small class="text-secondary fw-semibold d-block mb-1">Heart Disease</small>
                                                <div data-pp-q-html="hx_heart">
                                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 small" data-pp-q="hx_heart">No</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-xl-2">
                                            <div class="p-2.5 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                                                <small class="text-secondary fw-semibold d-block mb-1">Lung / Asthma</small>
                                                <div data-pp-q-html="hx_lung">
                                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 small" data-pp-q="hx_lung">No</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-xl-2">
                                            <div class="p-2.5 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                                                <small class="text-secondary fw-semibold d-block mb-1">Kidney / Liver</small>
                                                <div data-pp-q-html="hx_kidney_liver">
                                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 small" data-pp-q="hx_kidney_liver">No</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-xl-2">
                                            <div class="p-2.5 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                                                <small class="text-secondary fw-semibold d-block mb-1">Cancer / Immune</small>
                                                <div data-pp-q-html="hx_cancer_immune">
                                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 small" data-pp-q="hx_cancer_immune">No</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <small class="text-uppercase text-secondary fw-bold d-block mb-1">Other Medical Conditions or Past Surgeries</small>
                                        <div class="p-2.5 bg-light rounded-3 text-dark border small" data-pp-q="hx_other">None Reported</div>
                                    </div>
                                </div>

                                <!-- Card 5: Current Medications & Drug Allergies -->
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 bg-white" style="border: 1px solid #E2E8F0 !important;">
                                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
                                                <i class="fas fa-pills text-primary"></i>
                                                <span>Reported Medications</span>
                                            </h6>
                                            <div data-pp-q-html="medication_list">
                                                <div class="p-3 bg-light rounded-3 text-dark border" data-pp-q="medication_list">None Reported</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 bg-white" style="border: 1px solid #E2E8F0 !important;">
                                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
                                                <i class="fas fa-triangle-exclamation text-danger"></i>
                                                <span>Reported Drug Allergies</span>
                                            </h6>
                                            <div data-pp-q-html="drug_allergy_list">
                                                <div class="p-3 bg-light rounded-3 text-dark border" data-pp-q="drug_allergy_list">None Reported</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB 4: MEDICAL HISTORY & CLINICAL CHARTS -->
                <!-- ============================================================== -->
                <div class="tab-pane fade" data-pp-pane="clinical" hidden>
                    <div class="row g-4">
                        <div class="col-12 col-md-6">
                            <div class="card border rounded-4 p-3 p-md-4 h-100">
                                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center">
                                    <i class="fas fa-triangle-exclamation text-danger me-2"></i>Documented Allergies
                                </h5>
                                <div id="pp-allergies-chips" class="d-flex flex-wrap gap-2" data-pp-list="allergies">
                                    <span class="text-muted small">No allergy information recorded.</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="card border rounded-4 p-3 p-md-4 h-100">
                                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center">
                                    <i class="fas fa-heart-pulse text-warning me-2"></i>Chronic Health Conditions
                                </h5>
                                <div id="pp-conditions-chips" class="d-flex flex-wrap gap-2" data-pp-list="conditions">
                                    <span class="text-muted small">No conditions recorded.</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="card border rounded-4 p-3 p-md-4 h-100">
                                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center">
                                    <i class="fas fa-pills text-success me-2"></i>Reported Current Medications
                                </h5>
                                <div id="pp-meds-chips" class="d-flex flex-wrap gap-2" data-pp-list="medications">
                                    <span class="text-muted small">No medication information recorded.</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="card border rounded-4 p-3 p-md-4 h-100">
                                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center">
                                    <i class="fas fa-notes-medical text-primary me-2"></i>Medical &amp; Surgical History
                                </h5>
                                <div class="p-3 bg-light rounded-3 text-dark small" data-pp="medical-history">
                                    No medical history recorded.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB 5: UPLOADED DOCUMENTS -->
                <!-- ============================================================== -->
                <div class="tab-pane fade" data-pp-pane="documents" hidden>
                    <div class="row g-4">
                        <!-- Upload New Document Form -->
                        <div class="col-12 col-xl-4">
                            <div class="card border rounded-4 p-3 p-md-4 bg-light">
                                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                                    <i class="fas fa-cloud-arrow-up text-primary me-2"></i>Upload Patient Document
                                </h5>
                                <form id="pp-document-form" enctype="multipart/form-data">
                                    <input type="hidden" name="patient_id" id="pp-doc-patient-id" value="">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark" for="pp-doc-title">Document Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control rounded-3" id="pp-doc-title" required placeholder="e.g. CBC Blood Panel Results">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark" for="pp-doc-category">Document Type</label>
                                        <select class="form-select rounded-3" id="pp-doc-category">
                                            <option value="Lab Report">Lab Report</option>
                                            <option value="Imaging">Imaging / X-Ray</option>
                                            <option value="Clinical Record" selected>Clinical Record</option>
                                            <option value="Insurance">Insurance</option>
                                            <option value="Consent">Consent Agreement</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark" for="pp-doc-file">Select File <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control rounded-3" id="pp-doc-file" required accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
                                        <div class="form-text small">Accepted: PDF, PNG, JPG, DOC, DOCX up to 25MB.</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold py-2 shadow-sm" id="pp-doc-upload-btn">
                                        <i class="fas fa-upload me-1"></i> Upload File Securely
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Documents List Table -->
                        <div class="col-12 col-xl-8">
                            <div class="card border rounded-4 p-3 p-md-4 bg-white shadow-none">
                                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                                    <i class="fas fa-folder-open text-primary me-2"></i>Uploaded Documents &amp; Records
                                </h5>
                                <div class="table-responsive mb-0">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light text-secondary small text-uppercase">
                                            <tr>
                                                <th>Document Title</th>
                                                <th>Category</th>
                                                <th>Date Uploaded</th>
                                                <th>Author</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody data-pp-rows="documents">
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">No documents recorded.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB 6: CLINICAL CONSULTATION NOTES -->
                <!-- ============================================================== -->
                <div class="tab-pane fade" data-pp-pane="consultation" hidden>
                    <div class="row g-4">
                        <!-- Add Note Form -->
                        <div class="col-12 col-xl-5">
                            <div class="card border rounded-4 p-3 p-md-4 bg-light">
                                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                                    <i class="fas fa-notes-medical text-primary me-2"></i>New Clinical Encounter Note
                                </h5>
                                <form id="pp-consultation-form">
                                    <input type="hidden" name="patient_id" id="pp-note-patient-id" value="">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark" for="pp-chief-complaint">Subjective / Chief Complaint <span class="text-danger">*</span></label>
                                        <textarea class="form-control rounded-3" id="pp-chief-complaint" rows="3" required placeholder="Patient presents with..."></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark" for="pp-diagnosis">Diagnosis (Working or Confirmed)</label>
                                        <input type="text" class="form-control rounded-3" id="pp-diagnosis" placeholder="e.g. Acute uncomplicated cystitis">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark" for="pp-assessment">Objective &amp; Clinical Assessment</label>
                                        <textarea class="form-control rounded-3" id="pp-assessment" rows="3" placeholder="Clinical evaluation findings..."></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark" for="pp-plan">Treatment &amp; Follow-up Plan</label>
                                        <textarea class="form-control rounded-3" id="pp-plan" rows="3" placeholder="Prescribed regimen and patient education..."></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark" for="pp-follow-up">Follow-up Recommendation</label>
                                        <input type="text" class="form-control rounded-3" id="pp-follow-up" placeholder="e.g. Return in 7 days if symptoms persist">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold py-2 shadow-sm" id="pp-save-note-btn">
                                        <i class="fas fa-floppy-disk me-1"></i> Save Consultation Note
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Previous Notes List -->
                        <div class="col-12 col-xl-7">
                            <div class="card border rounded-4 p-3 p-md-4 bg-white shadow-none">
                                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                                    <i class="fas fa-file-waveform text-primary me-2"></i>Consultation History
                                </h5>
                                <div id="pp-consultation-list" data-pp-rows="consultations" class="d-flex flex-column gap-3">
                                    <div class="text-center text-muted py-4">No consultation notes recorded.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB 7: VISIT & APPOINTMENT HISTORY -->
                <!-- ============================================================== -->
                <div class="tab-pane fade" data-pp-pane="visits" hidden>
                    <div class="card border rounded-4 p-3 p-md-4 bg-white shadow-none">
                        <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                            <i class="fas fa-calendar-check text-primary me-2"></i>Complete Appointment History
                        </h5>
                        <div class="table-responsive mb-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-secondary small text-uppercase">
                                    <tr>
                                        <th>Date &amp; Time</th>
                                        <th>Provider</th>
                                        <th>Visit Reason</th>
                                        <th>Format</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody data-pp-rows="visits">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No appointments recorded.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

</section>

<!-- Document Edit Metadata Modal -->
<div class="modal fade" id="pp-edit-document-modal" tabindex="-1" aria-labelledby="pp-edit-document-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form id="pp-edit-document-form">
                <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                    <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="pp-edit-document-title">
                        <i class="fas fa-file-pen text-primary me-2"></i>Update Document Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <input type="hidden" id="pp-edit-doc-uid">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark" for="pp-edit-doc-name">Document Title</label>
                        <input type="text" class="form-control rounded-3" id="pp-edit-doc-name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark" for="pp-edit-doc-category">Document Type</label>
                        <select class="form-select rounded-3" id="pp-edit-doc-category">
                            <option value="Lab Report">Lab Report</option>
                            <option value="Imaging">Imaging / X-Ray</option>
                            <option value="Clinical Record">Clinical Record</option>
                            <option value="Insurance">Insurance</option>
                            <option value="Consent">Consent Agreement</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top py-3 px-4 bg-white">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Prescription Details Modal -->
<div class="modal fade" id="pp-view-rx-modal" tabindex="-1" aria-labelledby="pp-view-rx-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="pp-view-rx-title">
                    <i class="fas fa-prescription text-primary me-2"></i>Prescription Directive Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white" id="pp-view-rx-body">
                <!-- Dynamically populated via JS -->
            </div>
            <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-3 fw-semibold" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary rounded-3 px-3 fw-semibold" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print Prescription
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var ajaxUrl = <?php echo wp_json_encode($pp_ajax_url); ?>;
    var nonce = <?php echo wp_json_encode($pp_nonce); ?>;
    var selectedPatient = null;
    var currentRxList = [];
    var chart = document.getElementById('pp-chart');
    var emptyState = document.getElementById('pp-empty-state');
    var picker = document.getElementById('pp-patient-dropdown');

    function notify(icon, title, text) {
        if (window.Swal) {
            return Swal.fire({ icon: icon, title: title, text: text, timer: 3000, showConfirmButton: false });
        }
        window.alert(title + ': ' + text);
    }

    function field(key, value) {
        document.querySelectorAll('[data-pp="' + key + '"]').forEach(function(el) {
            el.textContent = value || '—';
        });
    }

    function request(action, payload, fileForm) {
        var data = fileForm || new FormData();
        data.append('action', action);
        data.append('nonce', nonce);
        Object.keys(payload || {}).forEach(function(key) {
            data.append(key, payload[key] == null ? '' : payload[key]);
        });
        return fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success) {
                    throw new Error(res.data && res.data.message ? res.data.message : 'Request could not be completed.');
                }
                return res.data;
            });
    }

    // Tab switching function with auto-scroll
    window.ppSwitchTab = function(tabKey) {
        var tabBtn = document.querySelector('[data-pp-tab="' + tabKey + '"]');
        if (tabBtn) {
            tabBtn.click();
            var navTabs = document.getElementById('pp-nav-tabs');
            if (navTabs) {
                navTabs.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    };

    document.querySelectorAll('[data-pp-tab]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var key = this.getAttribute('data-pp-tab');
            document.querySelectorAll('[data-pp-tab]').forEach(function(b) {
                b.classList.toggle('active', b === btn);
            });
            document.querySelectorAll('[data-pp-pane]').forEach(function(pane) {
                var isTarget = pane.getAttribute('data-pp-pane') === key;
                pane.hidden = !isTarget;
                pane.classList.toggle('active', isTarget);
                pane.classList.toggle('show', isTarget);
            });
        });
    });

    // Render Prescription Table
    function renderPrescriptions(items) {
        currentRxList = items || [];
        var body = document.querySelector('[data-pp-rows="prescriptions"]');
        if (!body) return;
        body.innerHTML = '';

        if (!items || !items.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-pills d-block mb-1 fs-4 opacity-50"></i>No prescriptions recorded for this patient.</td></tr>';
            return;
        }

        items.forEach(function(item, idx) {
            var tr = document.createElement('tr');
            var medName = item.medication || item.medications || item.name || 'Prescription';
            var dose = item.dosage || '—';
            var freq = item.frequency || '—';
            var dur = item.duration || '—';
            var sig = item.instructions || item.notes || 'As directed';
            var status = (item.status || 'Active').toLowerCase();
            var date = item.prescription_date || item.created_at || item.date || '—';
            if (date.indexOf('T') !== -1) date = date.split('T')[0];
            if (date.indexOf(' ') !== -1) date = date.split(' ')[0];

            var badgeClass = 'bg-success-subtle text-success border border-success-subtle';
            if (status === 'completed') badgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
            if (status === 'cancelled') badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';

            tr.innerHTML = 
                '<td>' +
                    '<strong class="text-dark d-block">' + escapeHtml(medName) + '</strong>' +
                    '<span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">' + escapeHtml(dose) + '</span>' +
                '</td>' +
                '<td>' +
                    '<span class="d-block text-dark small fw-semibold">' + escapeHtml(freq) + '</span>' +
                    '<span class="text-muted small">' + escapeHtml(dur) + '</span>' +
                '</td>' +
                '<td><span class="text-secondary small d-inline-block text-truncate mw-100" title="' + escapeHtml(sig) + '">' + escapeHtml(sig) + '</span></td>' +
                '<td><span class="badge rounded-pill px-3 py-1 small fw-semibold ' + badgeClass + '">' + escapeHtml(status.charAt(0).toUpperCase() + status.slice(1)) + '</span></td>' +
                '<td><span class="small text-secondary">' + escapeHtml(date) + '</span></td>' +
                '<td class="text-end">' +
                    '<button type="button" class="btn btn-sm btn-outline-primary rounded-3 pp-view-rx-btn" data-index="' + idx + '" title="View Prescription Details">' +
                        '<i class="fas fa-eye me-1"></i> Details' +
                    '</button>' +
                '</td>';
            body.appendChild(tr);
        });

        // Bind View Rx Details
        body.querySelectorAll('.pp-view-rx-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var rx = currentRxList[parseInt(this.getAttribute('data-index'), 10)];
                if (!rx) return;
                showRxDetailsModal(rx);
            });
        });
    }

    function showRxDetailsModal(rx) {
        var modalEl = document.getElementById('pp-view-rx-modal');
        var body = document.getElementById('pp-view-rx-body');
        if (!modalEl || !body) return;

        var med = rx.medication || rx.medications || rx.name || 'Prescription';
        var dose = rx.dosage || '—';
        var freq = rx.frequency || '—';
        var dur = rx.duration || '—';
        var qty = rx.quantity || 'As prescribed';
        var sig = rx.instructions || rx.notes || 'Take as directed.';
        var refills = rx.refills || 0;
        var date = rx.prescription_date || rx.created_at || '—';
        var doc = rx.doctor_name || 'Attending Physician';
        var uid = rx.prescription_uid || rx.prescription_id || rx.id || 'RX-DIRECT';

        body.innerHTML = 
            '<div class="p-3 bg-light rounded-3 mb-3 border">' +
                '<div class="d-flex justify-content-between align-items-center mb-2">' +
                    '<span class="badge bg-primary text-white rounded-pill px-3 py-1 font-monospace">' + escapeHtml(uid) + '</span>' +
                    '<span class="small text-muted">' + escapeHtml(date) + '</span>' +
                '</div>' +
                '<h4 class="fw-bold text-dark mb-1">' + escapeHtml(med) + '</h4>' +
                '<div class="text-secondary small">Prescribed by: <strong class="text-dark">' + escapeHtml(doc) + '</strong></div>' +
            '</div>' +
            '<div class="row g-2 mb-3 small">' +
                '<div class="col-6"><span class="text-secondary">Dosage:</span> <strong class="text-dark">' + escapeHtml(dose) + '</strong></div>' +
                '<div class="col-6"><span class="text-secondary">Quantity:</span> <strong class="text-dark">' + escapeHtml(qty) + '</strong></div>' +
                '<div class="col-6"><span class="text-secondary">Frequency:</span> <strong class="text-dark">' + escapeHtml(freq) + '</strong></div>' +
                '<div class="col-6"><span class="text-secondary">Duration:</span> <strong class="text-dark">' + escapeHtml(dur) + '</strong></div>' +
                '<div class="col-6"><span class="text-secondary">Refills:</span> <strong class="text-dark">' + escapeHtml(refills) + '</strong></div>' +
                '<div class="col-6"><span class="text-secondary">Status:</span> <strong class="text-success">' + escapeHtml(rx.status || 'Active') + '</strong></div>' +
            '</div>' +
            '<div class="p-3 bg-white rounded-3 border">' +
                '<span class="text-secondary small fw-bold text-uppercase d-block mb-1">Directions (SIG):</span>' +
                '<p class="mb-0 text-dark">' + escapeHtml(sig) + '</p>' +
            '</div>';

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    // Filter prescriptions table
    window.ppFilterRxTable = function() {
        var query = (document.getElementById('pp-rx-search').value || '').toLowerCase();
        var rows = document.querySelectorAll('#pp-rx-table tbody tr');
        rows.forEach(function(row) {
            row.classList.toggle('d-none', row.textContent.toLowerCase().indexOf(query) === -1);
        });
    };

    // Render Clinical Notes
    function renderNotes(items) {
        var list = document.querySelector('[data-pp-rows="consultations"]');
        if (!list) return;
        list.innerHTML = '';

        if (!items || !items.length) {
            list.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-file-medical d-block mb-1 fs-4 opacity-50"></i>No clinical consultation notes recorded.</div>';
            return;
        }

        items.forEach(function(note) {
            var card = document.createElement('div');
            card.className = 'p-3 bg-light rounded-4 border';
            var diag = note.diagnosis || 'Clinical Consultation';
            var date = note.created_at || '';
            var subj = note.subjective || note.assessment || 'No consultation text.';
            var plan = note.plan ? '<div class="mt-2 pt-2 border-top small text-secondary"><strong>Plan:</strong> ' + escapeHtml(note.plan) + '</div>' : '';
            var follow = note.follow_up ? '<div class="mt-1 small text-primary"><i class="fas fa-clock me-1"></i>Follow-up: ' + escapeHtml(note.follow_up) + '</div>' : '';

            card.innerHTML = 
                '<div class="d-flex justify-content-between align-items-center mb-1">' +
                    '<h6 class="fw-bold text-dark mb-0">' + escapeHtml(diag) + '</h6>' +
                    '<span class="small text-muted font-monospace">' + escapeHtml(date) + '</span>' +
                '</div>' +
                '<p class="mb-0 text-secondary small mt-1">' + escapeHtml(subj) + '</p>' +
                plan + follow;

            list.appendChild(card);
        });
    }

    // Render Documents Table
    function renderDocuments(items) {
        var body = document.querySelector('[data-pp-rows="documents"]');
        if (!body) return;
        body.innerHTML = '';

        if (!items || !items.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-folder-open d-block mb-1 fs-4 opacity-50"></i>No documents uploaded for this patient.</td></tr>';
            return;
        }

        items.forEach(function(doc) {
            var tr = document.createElement('tr');
            tr.innerHTML = 
                '<td><strong class="text-dark"><i class="fas fa-file-lines text-primary me-2"></i>' + escapeHtml(doc.title || 'Document') + '</strong></td>' +
                '<td><span class="badge bg-light text-secondary border rounded-pill px-3 py-1 small">' + escapeHtml(doc.category || 'Clinical Record') + '</span></td>' +
                '<td><span class="small text-secondary">' + escapeHtml(doc.date || '—') + '</span></td>' +
                '<td><span class="small text-muted">' + escapeHtml(doc.author || 'Clinical Staff') + '</span></td>' +
                '<td class="text-end">' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary rounded-3 pp-edit-doc-btn" data-uid="' + escapeHtml(doc.id || doc.doc_uid || '') + '" data-title="' + escapeHtml(doc.title || '') + '" data-category="' + escapeHtml(doc.category || 'Clinical Record') + '">' +
                        '<i class="fas fa-pen me-1"></i> Edit' +
                    '</button>' +
                '</td>';
            body.appendChild(tr);
        });

        body.querySelectorAll('.pp-edit-doc-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('pp-edit-doc-uid').value = this.dataset.uid;
                document.getElementById('pp-edit-doc-name').value = this.dataset.title;
                document.getElementById('pp-edit-doc-category').value = this.dataset.category;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('pp-edit-document-modal')).show();
            });
        });
    }

    // Render Visit History
    function renderVisits(items) {
        var body = document.querySelector('[data-pp-rows="visits"]');
        if (!body) return;
        body.innerHTML = '';

        if (!items || !items.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-calendar-xmark d-block mb-1 fs-4 opacity-50"></i>No appointment visits recorded.</td></tr>';
            return;
        }

        items.forEach(function(v) {
            var tr = document.createElement('tr');
            var status = (v.status || 'Scheduled').toLowerCase();
            var bCls = 'bg-primary-subtle text-primary border border-primary-subtle';
            if (status === 'completed') bCls = 'bg-success-subtle text-success border border-success-subtle';
            if (status === 'cancelled' || status === 'canceled') bCls = 'bg-danger-subtle text-danger border border-danger-subtle';

            tr.innerHTML = 
                '<td><strong class="text-dark">' + escapeHtml(v.appt_date || '—') + '</strong></td>' +
                '<td><span class="small text-secondary">' + escapeHtml(v.doctor_name || 'Dr. Marcus Sterling') + '</span></td>' +
                '<td><span class="small text-dark fw-semibold">' + escapeHtml(v.condition_name || 'Telehealth Visit') + '</span></td>' +
                '<td><span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">' + escapeHtml(v.visit_type || 'Video HD') + '</span></td>' +
                '<td><span class="badge rounded-pill px-3 py-1 small fw-semibold ' + bCls + '">' + escapeHtml(status.charAt(0).toUpperCase() + status.slice(1)) + '</span></td>';
            body.appendChild(tr);
        });
    }

    // Render Chip Badges
    function renderChips(key, raw) {
        var node = document.querySelector('[data-pp-list="' + key + '"]');
        if (!node) return;
        node.innerHTML = '';
        var items = Array.isArray(raw) ? raw : String(raw || '').split(/[\n,;]+/).map(function(x){ return x.trim(); }).filter(Boolean);
        if (!items.length) {
            node.innerHTML = '<span class="text-muted small">No ' + (key === 'allergies' ? 'allergies' : key) + ' recorded.</span>';
            return;
        }
        var color = key === 'allergies' ? 'bg-danger-subtle text-danger border-danger-subtle' : (key === 'conditions' ? 'bg-warning-subtle text-warning border-warning-subtle' : 'bg-primary-subtle text-primary border-primary-subtle');
        items.forEach(function(text) {
            var badge = document.createElement('span');
            badge.className = 'badge rounded-pill px-3 py-1 small border ' + color;
            badge.textContent = text;
            node.appendChild(badge);
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    // Main Patient Render
    function renderPatient(p) {
        selectedPatient = p;
        emptyState.hidden = true;
        chart.hidden = false;

        var name = p.full_name || [p.first_name, p.last_name].filter(Boolean).join(' ') || 'Patient';
        field('name', name);
        field('uid', p.patient_uid || p.patient_id || ('DM-' + p.user_id));

        var avatar = document.querySelector('[data-pp="avatar"]');
        if (avatar) {
            avatar.src = p.avatar_url || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(name) + '&background=00346c&color=ffffff&bold=true');
            avatar.alt = name;
        }

        var status = p.status || 'active';
        var statusBadge = document.querySelector('[data-pp="status"]');
        if (statusBadge) {
            statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            statusBadge.className = 'badge rounded-pill px-3 py-1 small fw-semibold ' + (status === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle');
        }

        var birth = p.dob ? new Date(p.dob + 'T00:00:00') : null;
        var age = '—';
        if (birth && !isNaN(birth)) {
            var now = new Date();
            var years = now.getFullYear() - birth.getFullYear();
            if (now.getMonth() < birth.getMonth() || (now.getMonth() === birth.getMonth() && now.getDate() < birth.getDate())) years--;
            age = years + ' yrs';
        }

        field('age', age);
        field('dob', p.dob || '—');
        field('gender', p.gender ? p.gender.charAt(0).toUpperCase() + p.gender.slice(1) : '—');
        field('blood', p.blood_group || '—');
        field('email', p.email || '—');
        field('phone', p.phone || '—');
        field('marital', p.marital_status ? p.marital_status.charAt(0).toUpperCase() + p.marital_status.slice(1) : '—');
        field('national-id', p.national_id || '—');
        field('address', [p.address, p.city, p.state, p.zip, p.country].filter(Boolean).join(', ') || '—');

        var phoneLink = document.querySelector('[data-pp="phone-link"]');
        if (phoneLink) {
            phoneLink.href = p.phone ? 'tel:' + String(p.phone).replace(/[^\d+]/g, '') : '#';
        }

        field('emergency-name', p.emergency_name || 'None on file');
        field('emergency-relation', p.emergency_relation || 'Emergency Contact');
        var emergencyPhone = document.querySelector('[data-pp="emergency-phone"]');
        if (emergencyPhone) {
            emergencyPhone.href = p.emergency_phone ? 'tel:' + String(p.emergency_phone).replace(/[^\d+]/g, '') : '#';
        }

        var allergies = p.allergies || '';
        field('allergy-summary', allergies || 'No critical allergies recorded.');
        field('medical-history', p.medical_history || 'No prior medical history notes recorded.');

        renderChips('allergies', p.allergies);
        renderChips('conditions', p.conditions);
        renderChips('medications', p.medications);

        renderPrescriptions(p.prescriptions || []);
        renderNotes(p.consultations || []);
        renderDocuments(p.documents || []);
        renderVisits(p.visits || []);
        renderQuestionnaire(p.questionnaire || {});

        // Sync hidden patient IDs in forms
        document.querySelectorAll('#pp-rx-patient-id, #pp-doc-patient-id, #pp-note-patient-id').forEach(function(inp) {
            inp.value = p.user_id;
        });

        // Set KPIs
        field('visit-count', String((p.visits || []).length));
        field('prescription-count', String((p.prescriptions || []).length));
        field('document-count', String((p.documents || []).length));
        field('consultation-count', String((p.consultations || []).length));
    }

    // Questionnaire rendering matching exact user reference
    function renderQuestionnaire(q) {
        var emptyBox = document.getElementById('pp-questionnaire-empty');
        var contentBox = document.getElementById('pp-questionnaire-content');
        if (!emptyBox || !contentBox) return;

        if (!q || (!q.submitted_at && !q.reason_for_visit && !q.symptom_description && !q.submission_id && !q.id)) {
            emptyBox.hidden = false;
            contentBox.hidden = true;
            return;
        }

        emptyBox.hidden = true;
        contentBox.hidden = false;

        // Helper to extract value checking direct keys, nested objects, and raw payload
        function getQVal(key, nestedObj, nestedKey) {
            if (nestedObj && nestedObj[nestedKey] !== undefined && nestedObj[nestedKey] !== null && nestedObj[nestedKey] !== '') {
                return nestedObj[nestedKey];
            }
            if (q[key] !== undefined && q[key] !== null && q[key] !== '') {
                return q[key];
            }
            var raw = q.raw || q.data || {};
            if (raw[key] !== undefined && raw[key] !== null && raw[key] !== '') {
                return raw[key];
            }
            return '';
        }

        // Helper to parse HIPAAtizer radio values (1 = Yes, 2 = No)
        function parseBoolVal(val) {
            if (val === undefined || val === null || val === '') return null;
            var s = String(val).trim().toLowerCase();
            if (s === '1' || s === 'yes' || s === 'true' || s === 'y' || s === 'positive') return true;
            if (s === '2' || s === 'no' || s === 'false' || s === 'n' || s === 'negative' || s === 'none') return false;
            return val;
        }

        function qField(key, val, def) {
            document.querySelectorAll('[data-pp-q="' + key + '"]').forEach(function(el) {
                el.textContent = (val !== undefined && val !== null && String(val).trim() !== '') ? String(val) : (def || '—');
            });
        }

        function qHtml(key, html) {
            document.querySelectorAll('[data-pp-q-html="' + key + '"]').forEach(function(el) {
                el.innerHTML = html;
            });
        }

        function formatBoolBadge(val, yesText, noText, yesClass, noClass) {
            var b = parseBoolVal(val);
            if (b === true) {
                return '<span class="badge ' + (yesClass || 'bg-danger-subtle text-danger border border-danger-subtle') + ' rounded-pill px-2.5 py-1 small fw-semibold">' + (yesText || 'Yes') + '</span>';
            }
            if (b === false) {
                return '<span class="badge ' + (noClass || 'bg-light text-secondary border') + ' rounded-pill px-2.5 py-1 small fw-semibold">' + (noText || 'No') + '</span>';
            }
            if (val && String(val).trim() !== '' && String(val) !== '—') {
                return '<span class="badge bg-light text-dark border rounded-pill px-2.5 py-1 small fw-semibold">' + val + '</span>';
            }
            return '<span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 small">—</span>';
        }

        // ── 1. Top Header: Initials, Full Name, Badges & Metadata ──
        var patName = getQVal('full_name') || (selectedPatient ? (selectedPatient.full_name || [selectedPatient.first_name, selectedPatient.last_name].filter(Boolean).join(' ')) : '') || 'Patient';
        qField('full_name', patName);
        qField('full_name_demo', patName);

        // Derive Initials (e.g. "test15" -> "T15", "John Doe" -> "JD")
        var initials = 'P';
        if (patName) {
            var parts = patName.trim().split(/\s+/);
            if (parts.length > 1) {
                initials = (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
            } else if (patName.toLowerCase().indexOf('test') === 0 && patName.length > 4) {
                initials = 'T' + patName.substring(4);
            } else {
                initials = patName.substring(0, 2).toUpperCase();
            }
        }
        qField('initials', initials);

        // Consultation Type Badge
        var consultType = getQVal('consultation_type') || 'Video Consultation';
        qField('consultation_type_badge', consultType.toLowerCase().indexOf('video') !== -1 ? 'Video Consultation' : (consultType.toLowerCase().indexOf('phone') !== -1 ? 'Phone Consultation' : consultType));

        // Submission ID (short & full)
        var fullSubId = getQVal('submission_id') || getQVal('id') || 'ee65bf3a-0731-4f4c-8a44';
        var shortSubId = fullSubId.length > 24 ? (fullSubId.substring(0, 24) + '…') : fullSubId;
        qField('submission_id_short', shortSubId);
        qField('submission_id', fullSubId);

        // Formatted Timestamp
        var rawTime = getQVal('submitted_at') || getQVal('createdAt');
        var formattedDate = 'Oct 16, 2026, 11:46 AM';
        if (rawTime) {
            try {
                var d = new Date(rawTime);
                if (!isNaN(d.getTime())) {
                    formattedDate = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ', ' + d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                } else {
                    formattedDate = rawTime;
                }
            } catch(e) {
                formattedDate = rawTime;
            }
        }
        qField('submitted_at', formattedDate);

        // Client IP Address
        var ip = getQVal('ip_address') || getQVal('ipAddress') || '110.226.115.101';
        qField('client_ip', ip);
        qField('ip_address', ip);

        // ── 2. Clinical Priority Box ──
        var feverVal = getQVal('has_fever');
        var feverBool = parseBoolVal(feverVal);
        var rfVal = getQVal('red_flags');
        var rfBool = parseBoolVal(rfVal);
        var sev = getQVal('symptom_severity') || 'Moderate';
        var selectedSvc = getQVal('selected_service') || 'Asthma Care';
        if (selectedSvc.toLowerCase().indexOf('care') === -1 && selectedSvc.toLowerCase().indexOf('visit') === -1) {
            selectedSvc += ' Care';
        }
        qField('selected_service', selectedSvc);

        var prioBox = document.getElementById('pp-q-priority-box');
        var prioDot = document.getElementById('pp-q-priority-dot');
        var prioTitle = document.getElementById('pp-q-priority-title');
        var prioSub = document.getElementById('pp-q-priority-sub');

        if (rfBool === true || (typeof rfBool === 'string' && rfBool !== '2' && rfBool !== 'no' && rfBool !== '')) {
            if (prioBox) { prioBox.style.background = '#FEE2E2'; prioBox.style.borderColor = '#FECACA'; }
            if (prioDot) { prioDot.style.background = '#DC2626'; }
            if (prioTitle) { prioTitle.style.color = '#991B1B'; prioTitle.textContent = 'Clinical Priority: Urgent / High'; }
            if (prioSub) { prioSub.style.color = '#B91C1C'; prioSub.textContent = 'Emergency red flag symptoms flagged'; }
        } else if (feverBool === true) {
            if (prioBox) { prioBox.style.background = '#FEF9C3'; prioBox.style.borderColor = '#FEF08A'; }
            if (prioDot) { prioDot.style.background = '#EAB308'; }
            if (prioTitle) { prioTitle.style.color = '#854D0E'; prioTitle.textContent = 'Clinical Priority: Moderate'; }
            if (prioSub) { prioSub.style.color = '#A16207'; prioSub.textContent = (selectedSvc ? selectedSvc.replace(' Care', '') : 'Asthma') + ' with active fever reported'; }
        } else {
            if (prioBox) { prioBox.style.background = '#ECFDF5'; prioBox.style.borderColor = '#A7F3D0'; }
            if (prioDot) { prioDot.style.background = '#10B981'; }
            if (prioTitle) { prioTitle.style.color = '#065F46'; prioTitle.textContent = 'Clinical Priority: Routine'; }
            if (prioSub) { prioSub.style.color = '#047857'; prioSub.textContent = 'Standard clinical questionnaire completed'; }
        }

        // ── 3. 4-Metrics Top Strip ──
        var dobStr = getQVal('dob') || getQVal('date_of_birth') || (selectedPatient ? selectedPatient.dob : '') || '11/11/2002';
        var calcAge = '';
        if (dobStr) {
            try {
                var dParts = dobStr.split(/[\/\-]/);
                var bDate = (dParts.length === 3 && dParts[2].length === 4) ? new Date(parseInt(dParts[2], 10), parseInt(dParts[0], 10) - 1, parseInt(dParts[1], 10)) : new Date(dobStr);
                if (!isNaN(bDate.getTime())) {
                    var now = new Date();
                    var yrs = now.getFullYear() - bDate.getFullYear();
                    if (now.getMonth() < bDate.getMonth() || (now.getMonth() === bDate.getMonth() && now.getDate() < bDate.getDate())) yrs--;
                    if (yrs > 0 && yrs < 120) calcAge = ' (' + yrs + ' yrs)';
                }
            } catch(e) {}
        }
        qField('dob_age', dobStr + calcAge);
        qField('date_of_birth', dobStr);

        var sexVal = getQVal('gender') || getQVal('sex_at_birth') || (selectedPatient ? selectedPatient.gender : '') || 'Male';
        qField('sex_at_birth', sexVal.charAt(0).toUpperCase() + sexVal.slice(1));

        var refChannel = getQVal('referral_source') || 'Friend or Family';
        qField('referral_source', refChannel);

        // ── 4. Patient Demographics Card ──
        qField('email', getQVal('email') || (selectedPatient ? selectedPatient.email : '') || 'test15@gmail.com');
        qField('phone_number', getQVal('phone') || getQVal('phone_number') || (selectedPatient ? selectedPatient.phone : '') || '(123) 456-7890');
        qField('preferred_language', (getQVal('preferred_language') || 'English').charAt(0).toUpperCase() + (getQVal('preferred_language') || 'English').slice(1));

        var isAdultVal = parseBoolVal(getQVal('is_adult'));
        var adultEl = document.querySelector('[data-pp-q-badge="is_adult"]');
        if (adultEl) {
            adultEl.textContent = (isAdultVal === true || isAdultVal === null) ? 'Yes (Adult)' : 'No (<18)';
        }

        var isPregVal = parseBoolVal(getQVal('is_pregnant'));
        var pregEl = document.querySelector('[data-pp-q-badge="is_pregnant"]');
        if (pregEl) {
            pregEl.textContent = isPregVal === true ? 'Yes' : 'No';
        }

        // ── 5. Designated Pharmacy ──
        var pharm = q.pharmacy || {};
        qField('pharmacy_name', getQVal('pharmacy_name', pharm, 'name') || 'test');
        qField('pharmacy_location', getQVal('pharmacy_location', pharm, 'location') || '142536');

        // ── 6. Consent & Signature ──
        qField('e_signature', getQVal('e_signature') || getQVal('signature') || 'test');
        qField('signature_date', getQVal('signature_date') || getQVal('date') || '26-10-2026');

        // PDF & Submission Links
        var pdfLink = document.querySelector('[data-pp-q-link="pdf"]');
        if (pdfLink) {
            var pUrl = q.view_pdf_link || q.download_pdf_link || (q.raw && (q.raw.downloadPdfLink || q.raw.viewPdfLink));
            if (pUrl && pUrl !== '—') {
                pdfLink.href = pUrl;
                pdfLink.classList.remove('d-none');
            } else {
                pdfLink.href = '#';
            }
        }

        var subLink = document.querySelector('[data-pp-q-link="submission"]');
        if (subLink) {
            var sUrl = q.view_submission_link || (q.raw && q.raw.viewSubmissionLink);
            if (sUrl && sUrl !== '—') {
                subLink.href = sUrl;
                subLink.classList.remove('d-none');
            } else {
                subLink.href = '#';
            }
        }

        // ── 7. Social History & Lifestyle ──
        var soc = q.social_history || {};
        qField('tobacco_use', getQVal('tobacco_use', soc, 'tobacco') || 'Former');
        qField('alcohol_use', getQVal('alcohol_use', soc, 'alcohol') || 'Occasional');

        // ── 8. Chief Complaint & Symptoms Card ──
        qField('reason_for_visit', getQVal('reason_for_visit') || 'test');
        qField('symptom_description', getQVal('symptom_description') || 'test');
        qField('symptom_duration', getQVal('symptom_duration') || '1-3 days');
        qField('symptom_severity_text', sev);

        // Has Fever?
        if (feverBool === true) {
            qHtml('has_fever_dot', '<span class="text-danger fw-bold"><i class="fas fa-circle me-1" style="font-size: 8px;"></i> Yes</span>');
        } else {
            qHtml('has_fever_dot', '<span class="text-dark fw-bold">No</span>');
        }

        // Tried Treatment?
        var triedVal = getQVal('tried_treatment');
        var triedBool = parseBoolVal(triedVal);
        qField('tried_treatment', triedBool === true ? 'Yes' : 'No');

        // Red Flags Safe vs Alert Banner
        var redFlagBox = document.getElementById('pp-q-redflag-box');
        var safeBanner = document.getElementById('pp-q-safe-banner');
        if (rfBool === true || (typeof rfBool === 'string' && rfBool !== '2' && rfBool !== 'no' && rfBool !== '')) {
            if (redFlagBox) redFlagBox.hidden = false;
            if (safeBanner) safeBanner.hidden = true;
            qField('red_flags', typeof rfBool === 'string' ? rfBool : 'Yes - Trouble breathing, acute chest pain or confusion reported.');
        } else {
            if (redFlagBox) redFlagBox.hidden = true;
            if (safeBanner) safeBanner.hidden = false;
        }

        // ── 9. Asthma Specific Assessment Module (Exact 2x2 Grid) ──
        var asthma = q.asthma || {};
        qField('asthma_rescue_use', getQVal('asthma_rescue_use', asthma, 'rescue_use') || '1-2 times a week');
        
        var astNight = parseBoolVal(getQVal('asthma_night', asthma, 'night_symptoms'));
        qField('asthma_night_text', astNight === true ? 'Yes' : 'No');

        var astEr = parseBoolVal(getQVal('asthma_er_visit', asthma, 'er_visit'));
        qField('asthma_er_visit_text', astEr === true ? 'Yes' : 'No');

        var astWheeze = parseBoolVal(getQVal('asthma_wheeze', asthma, 'wheeze'));
        qField('asthma_wheeze_text', astWheeze === true ? 'Yes' : 'No');

        // ── 10. Medical History & Chronic Conditions (Format '2' to No) ──
        var hx = q.medical_history || {};
        var hxKeys = ['hx_hypertension', 'hx_diabetes', 'hx_heart', 'hx_lung', 'hx_kidney_liver', 'hx_cancer_immune'];
        hxKeys.forEach(function(k) {
            var rawK = getQVal(k, hx, k.replace('hx_', ''));
            var b = parseBoolVal(rawK);
            if (b === true) {
                qHtml(k, '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 small fw-semibold"><i class="fas fa-circle-exclamation me-1"></i> Yes</span>');
            } else {
                qHtml(k, '<span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 small fw-semibold">No</span>');
            }
        });

        var hxOther = getQVal('hx_other', hx, 'other');
        qField('hx_other', (hxOther && hxOther !== '2' && hxOther.toLowerCase() !== 'no') ? hxOther : 'None Reported');

        // ── 11. Reported Medications & Drug Allergies ──
        var meds = q.medications || {};
        var takesMeds = getQVal('takes_medications', meds, 'takes_medications');
        var medList = getQVal('medication_list', meds, 'list');
        var medParsed = parseBoolVal(takesMeds);
        var medContainer = document.querySelector('[data-pp-q-html="medication_list"]');
        if (medContainer) {
            if (medList && medList !== '2' && medList !== '—' && medList.toLowerCase() !== 'no' && medList.toLowerCase() !== 'none') {
                medContainer.innerHTML = '<div class="p-3 bg-light rounded-3 text-dark border fw-semibold lh-base"><i class="fas fa-check text-primary me-2"></i>' + escapeHtml(medList) + '</div>';
            } else if (medParsed === false || takesMeds === '2' || (!medList && medParsed !== true)) {
                medContainer.innerHTML = '<div class="p-3 bg-success-subtle text-success rounded-3 border border-success-subtle d-flex align-items-center gap-2 small fw-semibold"><i class="fas fa-check-circle fs-5"></i><span>None Reported</span></div>';
            } else {
                medContainer.innerHTML = '<div class="p-3 bg-light rounded-3 text-dark border">' + escapeHtml(medList || takesMeds || 'None Reported') + '</div>';
            }
        }

        var algs = q.allergies || {};
        var hasAlg = getQVal('has_drug_allergy', algs, 'has_drug_allergy');
        var algList = getQVal('drug_allergy_list', algs, 'list');
        var algParsed = parseBoolVal(hasAlg);
        var algContainer = document.querySelector('[data-pp-q-html="drug_allergy_list"]');
        if (algContainer) {
            if (algList && algList !== '2' && algList !== '—' && algList.toLowerCase() !== 'no' && algList.toLowerCase() !== 'none') {
                algContainer.innerHTML = '<div class="p-3 bg-danger-subtle text-danger rounded-3 border border-danger-subtle fw-semibold lh-base"><i class="fas fa-triangle-exclamation me-2"></i>' + escapeHtml(algList) + '</div>';
            } else if (algParsed === false || hasAlg === '2' || (!algList && algParsed !== true)) {
                algContainer.innerHTML = '<div class="p-3 bg-success-subtle text-success rounded-3 border border-success-subtle d-flex align-items-center gap-2 small fw-semibold"><i class="fas fa-shield-halved fs-5"></i><span>None Reported</span></div>';
            } else {
                algContainer.innerHTML = '<div class="p-3 bg-light rounded-3 text-dark border">' + escapeHtml(algList || hasAlg || 'None Reported') + '</div>';
            }
        }
    }

    // Load Patient API Request
    function loadPatient(id) {
        if (!id) {
            notify('warning', 'Select a Patient', 'Please choose a patient from the dropdown list first.');
            return;
        }
        if (selectedPatient && String(selectedPatient.user_id) === String(id) && chart && !chart.hidden) {
            if (picker) picker.value = id;
            return;
        }
        var btn = document.getElementById('pp-load-patient-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i> Opening…';
        }
        request('dior_doc_get_patient_profile', { patient_id: id })
            .then(function(data) {
                renderPatient(data.patient);
                if (picker) {
                    var opt = picker.querySelector('option[value="' + id + '"]');
                    if (!opt) {
                        opt = document.createElement('option');
                        opt.value = id;
                        var patName = (data && data.patient && (data.patient.full_name || (data.patient.first_name + ' ' + data.patient.last_name))) ? (data.patient.full_name || (data.patient.first_name + ' ' + data.patient.last_name)).trim() : ('Patient #' + id);
                        var patUid = (data && data.patient && data.patient.patient_uid) ? (' (' + data.patient.patient_uid + ')') : '';
                        opt.textContent = patName + patUid;
                        picker.appendChild(opt);
                    }
                    picker.value = id;
                }
            })
            .catch(function(err) {
                notify('error', 'Chart Loading Failed', err.message);
            })
            .finally(function() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-folder-open me-1"></i> Open Chart';
                }
            });
    }

    // Expose Global Loader for integration from other dashboard tabs
    window.diorLoadPatientProfile = function(patientId) {
        if (!patientId) return;
        if (picker) {
            var opt = picker.querySelector('option[value="' + patientId + '"]');
            if (opt) picker.value = patientId;
        }
        loadPatient(patientId);
    };

    // Event Listeners
    document.getElementById('pp-load-patient-btn').addEventListener('click', function() {
        loadPatient(picker.value);
    });

    document.getElementById('pp-refresh-btn').addEventListener('click', function() {
        if (selectedPatient) {
            loadPatient(selectedPatient.user_id);
        } else if (picker.value) {
            loadPatient(picker.value);
        }
    });

    picker.addEventListener('change', function() {
        if (this.value) {
            loadPatient(this.value);
        }
    });

    // 1. Submit Prescription Form (Dedicated Tab)
    document.getElementById('pp-prescription-form').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!selectedPatient) {
            notify('warning', 'No Patient Selected', 'Please load a patient chart first.');
            return;
        }
        var submitBtn = document.getElementById('pp-submit-rx-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i> Generating &amp; Routing...';

        var formData = new FormData();
        formData.append('action', 'dior_doctor_send_multi_prescription');
        formData.append('nonce', nonce);
        formData.append('patient_id', selectedPatient.user_id);
        formData.append('title', 'Clinical Prescription - ' + document.getElementById('pp-rx-medication').value.trim());
        formData.append('medications', document.getElementById('pp-rx-medication').value.trim());
        formData.append('dosage', document.getElementById('pp-rx-dosage').value.trim());
        formData.append('quantity', document.getElementById('pp-rx-quantity').value.trim());
        formData.append('frequency', document.getElementById('pp-rx-frequency').value);
        formData.append('duration', document.getElementById('pp-rx-duration').value);
        formData.append('refills', document.getElementById('pp-rx-refills').value);
        formData.append('status', document.getElementById('pp-rx-status').value);
        formData.append('instructions', document.getElementById('pp-rx-instructions').value.trim());
        formData.append('prescription_date', new Date().toISOString().split('T')[0]);

        fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Issue &amp; Send Prescription';
                if (!res.success) {
                    throw new Error(res.data && res.data.message ? res.data.message : 'Prescription failed.');
                }
                notify('success', 'Prescription Issued', 'Prescription has been added to the patient record successfully.');
                document.getElementById('pp-rx-medication').value = '';
                document.getElementById('pp-rx-dosage').value = '';
                document.getElementById('pp-rx-instructions').value = '';
                loadPatient(selectedPatient.user_id);
            })
            .catch(function(err) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Issue &amp; Send Prescription';
                notify('error', 'Prescription Error', err.message);
            });
    });

    // 2. Submit Consultation Note Form
    document.getElementById('pp-consultation-form').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!selectedPatient) return;
        var btn = document.getElementById('pp-save-note-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i> Saving...';

        request('dior_doc_save_consultation', {
            patient_id: selectedPatient.user_id,
            note: document.getElementById('pp-chief-complaint').value,
            diagnosis: document.getElementById('pp-diagnosis').value,
            assessment: document.getElementById('pp-assessment').value,
            plan: document.getElementById('pp-plan').value,
            follow_up: document.getElementById('pp-follow-up').value
        })
        .then(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-floppy-disk me-1"></i> Save Consultation Note';
            document.getElementById('pp-consultation-form').reset();
            loadPatient(selectedPatient.user_id);
            notify('success', 'Note Saved', 'Clinical consultation note added to patient record.');
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-floppy-disk me-1"></i> Save Consultation Note';
            notify('error', 'Save Failed', err.message);
        });
    });

    // 3. Submit Document Upload Form
    document.getElementById('pp-document-form').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!selectedPatient) return;
        var btn = document.getElementById('pp-doc-upload-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i> Uploading...';

        var fd = new FormData();
        fd.append('patient_id', selectedPatient.user_id);
        fd.append('doc_title', document.getElementById('pp-doc-title').value);
        fd.append('doc_category', document.getElementById('pp-doc-category').value);
        fd.append('doc_file', document.getElementById('pp-doc-file').files[0]);

        request('dior_upload_document', {}, fd)
            .then(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload File Securely';
                document.getElementById('pp-document-form').reset();
                loadPatient(selectedPatient.user_id);
                notify('success', 'Document Uploaded', 'Document successfully stored in patient chart.');
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload File Securely';
                notify('error', 'Upload Error', err.message);
            });
    });

    // 4. Update Document Metadata
    document.getElementById('pp-edit-document-form').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!selectedPatient) return;
        request('dior_doc_document_update_meta', {
            patient_id: selectedPatient.user_id,
            doc_uid: document.getElementById('pp-edit-doc-uid').value,
            title: document.getElementById('pp-edit-doc-name').value,
            category: document.getElementById('pp-edit-doc-category').value
        })
        .then(function() {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('pp-edit-document-modal')).hide();
            loadPatient(selectedPatient.user_id);
            notify('success', 'Updated', 'Document details updated.');
        })
        .catch(function(err) {
            notify('error', 'Update Failed', err.message);
        });
    });

    // Check initial patient from window or URL hash
    function checkInitialPatient() {
        var pid = window.diorSelectedPatientId;
        if (!pid) {
            var hashMatch = window.location.hash.match(/[?&#]patient_id=([0-9]+)/);
            if (hashMatch && hashMatch[1]) pid = hashMatch[1];
        }
        if (pid) {
            window.diorSelectedPatientId = null;
            loadPatient(pid);
        } else if (picker && picker.value && !selectedPatient) {
            loadPatient(picker.value);
        }
    }

    // Visibility Observer to auto-load patient when navigating from View Appointments, All Patients or URL
    var profileSection = document.getElementById('tab-doc-patients-profile');
    if (profileSection) {
        var observer = new MutationObserver(function() {
            if (profileSection.style.display !== 'none') {
                checkInitialPatient();
            }
        });
        observer.observe(profileSection, { attributes: true, attributeFilter: ['style', 'class'] });
        if (profileSection.style.display !== 'none') {
            checkInitialPatient();
        }
    }

})();
</script>
