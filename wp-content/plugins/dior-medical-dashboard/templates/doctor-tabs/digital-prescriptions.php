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
        'id'   => $pq->ID,
        'uid'  => $puid,
        'name' => $pq->display_name ?: $pq->user_email
    ];
}
?>
<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/view-appointment.css?v=' . time()); ?>">
<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/dior-fda-prescription-modal.css?v=' . time()); ?>">
<script src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/js/dior-fda-prescription-modal.js?v=' . time()); ?>"></script>

<style>
/* ── Digital Prescriptions Icon & Table Consistency ───────────────────────── */
#tab-doc-e-prescriptions .va-icon-btn {
    width: 40px !important;
    height: 40px !important;
    border-radius: 8px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 18px !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease !important;
    cursor: pointer !important;
    border: none !important;
}
#tab-doc-e-prescriptions .va-icon-btn i {
    font-size: 18px !important;
    line-height: 1 !important;
}
#tab-doc-e-prescriptions .va-btn-primary {
    background-color: #4F46E5 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25) !important;
}
#tab-doc-e-prescriptions .va-btn-primary:hover {
    background-color: #4338CA !important;
    transform: translateY(-2px) !important;
}
#tab-doc-e-prescriptions .va-btn-success {
    background-color: #10B981 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25) !important;
}
#tab-doc-e-prescriptions .va-btn-success:hover {
    background-color: #059669 !important;
    transform: translateY(-2px) !important;
}
#tab-doc-e-prescriptions .va-btn-info {
    background-color: #3B82F6 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25) !important;
}
#tab-doc-e-prescriptions .va-btn-info:hover {
    background-color: #2563EB !important;
    transform: translateY(-2px) !important;
}
#tab-doc-e-prescriptions .va-action-btn-sm {
    width: 34px !important;
    height: 34px !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 15px !important;
    border: none !important;
    cursor: pointer !important;
    transition: transform 0.15s ease, background-color 0.15s ease !important;
}
#tab-doc-e-prescriptions .va-action-btn-sm i {
    font-size: 15px !important;
    line-height: 1 !important;
}
#tab-doc-e-prescriptions .va-btn-edit {
    background: rgba(79, 70, 229, 0.1) !important;
    color: #4F46E5 !important;
}
#tab-doc-e-prescriptions .va-btn-edit:hover {
    background: #4F46E5 !important;
    color: #ffffff !important;
}
#tab-doc-e-prescriptions .va-btn-delete {
    background: rgba(239, 68, 68, 0.1) !important;
    color: #EF4444 !important;
}
#tab-doc-e-prescriptions .va-btn-delete:hover {
    background: #EF4444 !important;
    color: #ffffff !important;
}
#tab-doc-e-prescriptions .va-search-box i {
    font-size: 15px !important;
    color: #94A3B8 !important;
}
#tab-doc-e-prescriptions .va-breadcrumb-list i {
    font-size: 15px !important;
}
</style>

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
                    <h2>Digital Prescriptions</h2>
                    <div class="va-title-line"></div>
                </div>
                
                <div class="va-actions-wrapper">
                    <div class="va-search-box">
                        <i class="fas fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search records..." onkeyup="diorFilterPrescriptions(this.value)">
                    </div>
                    <div class="va-actions-group">
                        <button type="button" class="va-icon-btn va-btn-primary" aria-label="Add new prescription" title="Add Medication Prescription (FDA Approved)" onclick="diorOpenFdaPrescriptionModal()">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="va-icon-btn va-btn-success" aria-label="Export to Excel" title="Export CSV" onclick="diorExportPrescriptionsCsv()">
                            <i class="fas fa-file-arrow-down"></i>
                        </button>
                        <button type="button" class="va-icon-btn va-btn-info" aria-label="Refresh data" title="Refresh" onclick="window.location.reload()">
                            <i class="fas fa-rotate-right"></i>
                        </button>
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
                                $badge_color = '#059669'; $badge_class = 'col-green';
                                if (strtolower($p->status) === 'completed') {
                                    $badge_color = '#2563eb'; $badge_class = 'col-blue';
                                } elseif (strtolower($p->status) === 'cancelled') {
                                    $badge_color = '#dc2626'; $badge_class = 'col-red';
                                }
                                
                                echo '<tr>';
                                echo '<td class="text-center"><input type="checkbox" class="va-checkbox"></td>';
                                echo '<td><strong>' . esc_html($p->prescription_id ?: $p->prescription_uid) . '</strong></td>';
                                echo '<td>
                                        <div class="va-user-profile">
                                            <img src="https://ui-avatars.com/api/?name='.urlencode($p->patient_name).'&background=0c4a60&color=fff" class="va-user-avatar" alt="User">
                                            <span class="va-user-name">' . esc_html($p->patient_name) . '</span>
                                        </div>
                                      </td>';
                                echo '<td><i class="far fa-calendar va-icon-blue"></i> ' . esc_html(date('M d, Y', strtotime($p->prescription_date ?: $p->created_at))) . '</td>';
                                echo '<td><span style="font-weight:700; color:#0c4a60;">' . esc_html($p->medications ?: $p->medication) . '</span></td>';
                                echo '<td>' . esc_html($p->dosage) . '</td>';
                                echo '<td>' . esc_html($p->frequency) . '</td>';
                                echo '<td>' . esc_html($p->duration) . '</td>';
                                echo '<td>' . esc_html($p->doctor_name ?: 'Dr. Marcus Sterling, DO') . '</td>';
                                echo '<td><span class="badge-solid ' . $badge_class . '" style="background-color: transparent; color: ' . $badge_color . '; padding: 6px 12px; border-radius: 6px; font-weight: 700;">' . esc_html($p->status) . '</span></td>';
                                echo '<td>
                                        <div class="va-row-actions">
                                            <button type="button" class="va-action-btn-sm va-btn-edit" title="Edit Prescription" onclick="diorOpenFdaPrescriptionModal()"><i class="fas fa-pen-to-square"></i></button>
                                            <button type="button" class="va-action-btn-sm va-btn-delete" title="Delete Prescription" onclick="diorDeletePrescriptionRow(this)"><i class="fas fa-trash-can"></i></button>
                                        </div>
                                      </td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="11" class="text-center py-4" style="color:#64748b;">No digital prescriptions found. Click + to add one.</td></tr>';
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
<!-- FDA APPROVED DIGITAL PRESCRIPTION MODAL (REFERENCE DESIGN) -->
<!-- ============================================================== -->
<div class="dior-fda-modal-overlay" id="dior-fda-prescription-modal" role="dialog" aria-modal="true" aria-labelledby="dior-fda-title-text">
    <div class="dior-fda-dialog">
        <input type="hidden" id="dior-fda-nonce" value="<?php echo esc_attr(wp_create_nonce('dior_doctor_nonce')); ?>">

        <!-- 1. Header: 1. SPORANOX CAPSULE -->
        <div class="dior-fda-header">
            <div class="dior-fda-header-title-wrap">
                <h3 class="dior-fda-header-title" id="dior-fda-title-text" onclick="document.getElementById('dior-fda-med-search').focus()">1. SPORANOX CAPSULE</h3>
            </div>
            <button type="button" class="dior-fda-close-btn" onclick="diorCloseFdaPrescriptionModal()" aria-label="Close dialog">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <!-- 2. Scrollable Modal Body -->
        <div class="dior-fda-body">
            <!-- Patient & Date Selection Context -->
            <div class="dior-fda-context-row">
                <div class="dior-fda-context-field">
                    <label><i class="fas fa-user-check me-1"></i> Prescribe For Patient *</label>
                    <select id="dior-fda-patient-select" class="dior-fda-context-select" required>
                        <?php
                        if (!empty($patients_list)) {
                            foreach ($patients_list as $pat) {
                                echo '<option value="' . esc_attr($pat['id']) . '">' . esc_html($pat['name']) . ' (' . esc_html($pat['uid']) . ')</option>';
                            }
                        } else {
                            echo '<option value="2">Default Patient (DM-10002)</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="dior-fda-context-field" style="max-width: 170px;">
                    <label><i class="fas fa-calendar-day me-1"></i> Prescription Date</label>
                    <input type="date" id="dior-fda-presc-date" class="dior-fda-context-input" value="<?php echo current_time('Y-m-d'); ?>">
                </div>
            </div>

            <!-- Multi-Medication Cart / Added Items Section -->
            <div id="dior-fda-prescribed-list-wrap" class="dior-fda-prescribed-list-wrap" style="display:none;">
                <div class="dior-fda-prescribed-list-header">
                    <span><i class="fas fa-list-check me-1"></i> Medications In This Prescription (<strong id="dior-fda-prescribed-count">0</strong>):</span>
                    <span class="dior-fda-prescribed-hint">Add more below or click "Save &amp; Issue Prescription"</span>
                </div>
                <div id="dior-fda-prescribed-items" class="dior-fda-prescribed-items">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- FDA Live Medication Search Bar -->
            <div class="dior-fda-search-section">
                <div class="dior-fda-search-box-wrap">
                    <i class="fas fa-magnifying-glass dior-fda-search-icon"></i>
                    <input type="text" id="dior-fda-med-search" class="dior-fda-search-input" 
                           placeholder="Search FDA Approved Medications (e.g., Sporanox, Amoxicillin, Lipitor...)"
                           autocomplete="off" oninput="diorOnFdaSearchInput(this.value)">
                    <span class="dior-fda-badge-verify">
                        <i class="fas fa-shield-halved"></i> openFDA
                    </span>
                </div>
                <!-- Autocomplete Dropdown -->
                <div id="dior-fda-autocomplete-dropdown" class="dior-fda-autocomplete-dropdown"></div>
            </div>

            <!-- Generic Name Checkbox & Subtitle Row -->
            <div class="dior-fda-generic-box">
                <label class="dior-fda-checkbox-label">
                    <input type="checkbox" id="dior-fda-check-generic" checked onchange="window.diorFdaState.includeGeneric = this.checked; diorFdaUpdateAllUI();">
                    <span>Include generic medicine name in the prescription</span>
                </label>
                <div class="dior-fda-contains-row" id="dior-fda-generic-subrow">
                    <span class="dior-fda-contains-text" id="dior-fda-contains-display">Contains: ITRACONAZOLE (100 MG)</span>
                    <input type="text" id="dior-fda-contains-edit" class="dior-fda-contains-input" style="display:none;" onblur="diorToggleFreeText()" onkeydown="if(event.key==='Enter') diorToggleFreeText()">
                    <button type="button" class="dior-fda-btn-freetext" onclick="diorToggleFreeText()">FREE TEXT</button>
                </div>
            </div>

            <!-- Take / Apply & Dose Form & Route Row -->
            <div class="dior-fda-form-route-row">
                <div class="dior-fda-radio-group">
                    <label class="dior-fda-radio-label">
                        <input type="radio" name="dior_fda_take_apply" value="Take" checked onchange="diorSetTakeOrApply('Take')">
                        <span>Take</span>
                    </label>
                    <label class="dior-fda-radio-label">
                        <input type="radio" name="dior_fda_take_apply" value="Apply" onchange="diorSetTakeOrApply('Apply')">
                        <span>Apply</span>
                    </label>
                </div>

                <div class="dior-fda-field-wrap">
                    <label class="dior-fda-field-title" id="dior-fda-take-apply-label">Take: (Dose Form)</label>
                    <select class="dior-fda-select" id="dior-fda-dose-form" onchange="diorOnDoseFormChange(this.value)">
                        <option value="Capsule(s)" selected>Capsule(s)</option>
                        <option value="Tablet(s)">Tablet(s)</option>
                        <option value="Syrup">Syrup</option>
                        <option value="Injection">Injection</option>
                        <option value="Drops">Drops</option>
                        <option value="Cream">Cream</option>
                        <option value="Ointment">Ointment</option>
                        <option value="Inhaler">Inhaler</option>
                        <option value="Lotion">Lotion</option>
                        <option value="Patch">Patch</option>
                        <option value="Suspension">Suspension</option>
                        <option value="Powder">Powder</option>
                    </select>
                </div>

                <div class="dior-fda-field-wrap">
                    <label class="dior-fda-field-title">To be taken: (Route of Administration)</label>
                    <select class="dior-fda-select" id="dior-fda-route" onchange="diorOnRouteChange(this.value)">
                        <option value="Orally" selected>Orally</option>
                        <option value="Topical">Topical</option>
                        <option value="Sublingual">Sublingual</option>
                        <option value="Intravenous (IV)">Intravenous (IV)</option>
                        <option value="Intramuscular (IM)">Intramuscular (IM)</option>
                        <option value="Inhalation">Inhalation</option>
                        <option value="Nasal">Nasal</option>
                        <option value="Ophthalmic (Eye)">Ophthalmic (Eye)</option>
                        <option value="Otic (Ear)">Otic (Ear)</option>
                        <option value="Rectal">Rectal</option>
                    </select>
                </div>
            </div>

            <!-- For: (Duration) Row -->
            <div class="dior-fda-duration-row">
                <div style="font-size:12px;font-weight:700;color:#0c4a60;margin-bottom:6px;">For: (Duration)</div>
                <div class="dior-fda-duration-flex">
                    <div class="dior-fda-stepper">
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDuration(-1)">&minus;</button>
                        <input type="text" id="dior-fda-dur-val" class="dior-fda-step-val" value="1" readonly>
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDuration(1)">&#43;</button>
                    </div>

                    <label class="dior-fda-radio-label">
                        <input type="radio" name="dior_fda_duration_unit" value="Day(s)" onchange="diorSetDurationUnit('Day(s)')">
                        <span>Day(s)</span>
                    </label>
                    <label class="dior-fda-radio-label">
                        <input type="radio" name="dior_fda_duration_unit" value="Week(s)" checked onchange="diorSetDurationUnit('Week(s)')">
                        <span>Week(s)</span>
                    </label>
                    <label class="dior-fda-radio-label">
                        <input type="radio" name="dior_fda_duration_unit" value="Month(s)" onchange="diorSetDurationUnit('Month(s)')">
                        <span>Month(s)</span>
                    </label>
                    <label class="dior-fda-radio-label">
                        <input type="radio" name="dior_fda_duration_unit" value="Till Next Review" onchange="diorSetDurationUnit('Till Next Review')">
                        <span>Till Next Review</span>
                    </label>
                </div>
            </div>

            <!-- Frequency & Intake Row -->
            <div class="dior-fda-freq-intake-row">
                <div class="dior-fda-field-wrap">
                    <label class="dior-fda-field-title">Frequency</label>
                    <select class="dior-fda-select" id="dior-fda-frequency" onchange="diorOnFrequencyChange(this.value)">
                        <option value="Daily" selected>Daily</option>
                        <option value="Twice Daily (BID)">Twice Daily (BID)</option>
                        <option value="Thrice Daily (TID)">Thrice Daily (TID)</option>
                        <option value="Four Times Daily (QID)">Four Times Daily (QID)</option>
                        <option value="Every 8 Hours">Every 8 Hours</option>
                        <option value="Every 12 Hours">Every 12 Hours</option>
                        <option value="Once a Week">Once a Week</option>
                        <option value="As Needed (PRN)">As Needed (PRN)</option>
                        <option value="Alternate Days">Alternate Days</option>
                    </select>
                </div>

                <div class="dior-fda-field-wrap">
                    <label class="dior-fda-field-title">Intake</label>
                    <div style="display:flex;align-items:center;gap:14px;padding:8px 0;">
                        <label class="dior-fda-radio-label">
                            <input type="radio" name="dior_fda_intake" value="Before Food" onchange="diorSetIntake('Before Food')">
                            <span>Before Food</span>
                        </label>
                        <label class="dior-fda-radio-label">
                            <input type="radio" name="dior_fda_intake" value="After Food" checked onchange="diorSetIntake('After Food')">
                            <span>After Food</span>
                        </label>
                        <label class="dior-fda-radio-label">
                            <input type="radio" name="dior_fda_intake" value="Not Applicable" onchange="diorSetIntake('Not Applicable')">
                            <span>Not Applicable</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Dosage Breakdown Cards (Morning, Noon, Evening, Night, Daily) -->
            <div class="dior-fda-dosages-grid">
                <!-- Morning -->
                <div class="dior-fda-dose-card" id="dior-card-morning">
                    <span class="dior-fda-dose-label">Morning (Qty)</span>
                    <div class="dior-fda-stepper">
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDosage('morning', -1)">&minus;</button>
                        <input type="text" id="dior-fda-qty-morning" class="dior-fda-step-val" value="0" readonly>
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDosage('morning', 1)">&#43;</button>
                    </div>
                    <span class="dior-fda-dose-sub">capsule(s)</span>
                </div>

                <!-- Noon -->
                <div class="dior-fda-dose-card" id="dior-card-noon">
                    <span class="dior-fda-dose-label">Noon (Qty)</span>
                    <div class="dior-fda-stepper">
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDosage('noon', -1)">&minus;</button>
                        <input type="text" id="dior-fda-qty-noon" class="dior-fda-step-val" value="0" readonly>
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDosage('noon', 1)">&#43;</button>
                    </div>
                    <span class="dior-fda-dose-sub">capsule(s)</span>
                </div>

                <!-- Evening -->
                <div class="dior-fda-dose-card" id="dior-card-evening">
                    <span class="dior-fda-dose-label">Evening (Qty)</span>
                    <div class="dior-fda-stepper">
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDosage('evening', -1)">&minus;</button>
                        <input type="text" id="dior-fda-qty-evening" class="dior-fda-step-val" value="0" readonly>
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDosage('evening', 1)">&#43;</button>
                    </div>
                    <span class="dior-fda-dose-sub">capsule(s)</span>
                </div>

                <!-- Night (Active Highlighted as in Reference Image) -->
                <div class="dior-fda-dose-card has-qty" id="dior-card-night">
                    <span class="dior-fda-dose-label">Night (Qty)</span>
                    <div class="dior-fda-stepper">
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDosage('night', -1)">&minus;</button>
                        <input type="text" id="dior-fda-qty-night" class="dior-fda-step-val" value="1" readonly>
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDosage('night', 1)">&#43;</button>
                    </div>
                    <span class="dior-fda-dose-sub">capsule(s)</span>
                </div>

                <!-- Daily -->
                <div class="dior-fda-dose-card daily-card" id="dior-card-daily">
                    <span class="dior-fda-dose-label">Daily (Qty)</span>
                    <div class="dior-fda-stepper">
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDosage('daily', -1)">&minus;</button>
                        <input type="text" id="dior-fda-qty-daily" class="dior-fda-step-val" value="0" readonly>
                        <button type="button" class="dior-fda-step-btn" onclick="diorStepDosage('daily', 1)">&#43;</button>
                    </div>
                    <span class="dior-fda-dose-sub">capsule(s)</span>
                </div>
            </div>

            <!-- Dynamic Instructions Sentence -->
            <div class="dior-fda-instruction-preview-box">
                <div class="dior-fda-inst-line1" id="dior-fda-inst-line1">Take 1 capsule(s) in the night after food for 1 week</div>
                <div class="dior-fda-inst-line2" id="dior-fda-inst-line2">To be taken: Orally</div>
            </div>

            <!-- Add Instructions/Notes Button & Drawer -->
            <div>
                <button type="button" class="dior-fda-btn-addnotes" onclick="diorToggleNotesDrawer()">
                    <i class="fas fa-circle-plus"></i> ADD INSTRUCTIONS/NOTES
                </button>
                <div class="dior-fda-notes-drawer" id="dior-fda-notes-drawer">
                    <textarea id="dior-fda-custom-notes" placeholder="Special doctor instructions (e.g. Take with plenty of water, avoid dairy products, etc.)..."></textarea>
                </div>
            </div>
        </div>

        <!-- 3. Modal Footer -->
        <div class="dior-fda-footer">
            <div class="dior-fda-footer-left">
                <span class="dior-fda-total-calc" id="dior-fda-total-calc">Total 7 Capsules</span>
                <label class="dior-fda-freq-check-label">
                    <input type="checkbox" id="dior-fda-freq-used" onchange="window.diorFdaState.frequentlyUsed = this.checked;">
                    <span>Add as 'Frequently Used Medicine'</span>
                </label>
            </div>

            <div class="dior-fda-footer-actions">
                <button type="button" id="dior-fda-btn-add-item" class="dior-fda-btn-addmedicine" onclick="diorFdaAddMedicineToList(event)" title="Add this medicine and configure next">
                    <i class="fas fa-plus"></i> ADD MEDICINE
                </button>
                <button type="button" id="dior-fda-btn-submit" class="dior-fda-btn-saverx" onclick="diorSubmitFdaPrescription(event)" title="Save & Issue complete prescription">
                    <i class="fas fa-file-prescription"></i> SAVE &amp; ISSUE PRESCRIPTION <span id="dior-fda-rx-btn-count" style="display:none; font-weight:800;">(1)</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Filter Prescriptions Table
function diorFilterPrescriptions(val) {
    var filter = (val || '').toLowerCase();
    var rows = document.querySelectorAll('#tab-doc-e-prescriptions table.va-table tbody tr');
    rows.forEach(function(row) {
        var txt = (row.textContent || '').toLowerCase();
        row.style.display = txt.indexOf(filter) !== -1 ? '' : 'none';
    });
}

// Export Prescriptions to CSV
function diorExportPrescriptionsCsv() {
    var rows = document.querySelectorAll('#tab-doc-e-prescriptions table.va-table tr');
    var csv = [];
    rows.forEach(function(row) {
        var cols = row.querySelectorAll('th, td');
        var rowData = [];
        cols.forEach(function(col, idx) {
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
    if (confirm('Are you sure you want to delete this prescription record?')) {
        var tr = btn.closest('tr');
        if (tr) {
            tr.style.opacity = '0.4';
            setTimeout(function() { tr.remove(); }, 300);
        }
    }
}
</script>
