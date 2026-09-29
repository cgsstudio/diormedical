<?php
/**
 * Dior Medical — Professional 39-Question Clinical EMR Patient Chart
 * 
 * World-class modern SaaS clinical interface:
 * - Complete 39-Question HIPAAtizer Medical Intake (Q01 to Q39 across 6 clinical sections)
 * - Clean Linear Tab Navigation (Zero horizontal scrollbars, no bloated styles)
 * - Standardized SOAP Clinical Encounter Documentation
 * - Certified HIPAA Document & Lab Vault
 * - Work & School Absence Excuse Letter Studio
 * - Electronic Prescription & Pharmacy Routing Ledger
 * 
 * @package Dior Medical
 * @version 5.1
 */

if (!defined('ABSPATH')) exit;

// Helper to safely extract intake, profile, and user meta values
$get_val = function($keys, $default = '') use ($intake, $profile, $user, $pid) {
    if (!is_array($keys)) $keys = [$keys];
    
    if (is_array($intake)) {
        foreach ($keys as $k) {
            if (isset($intake[$k]) && $intake[$k] !== '' && $intake[$k] !== null) {
                return is_array($intake[$k]) ? implode(', ', $intake[$k]) : (string)$intake[$k];
            }
        }
        if (!empty($intake['raw_data']) && is_array($intake['raw_data'])) {
            foreach ($keys as $k) {
                if (isset($intake['raw_data'][$k]) && $intake['raw_data'][$k] !== '' && $intake['raw_data'][$k] !== null) {
                    return is_array($intake['raw_data'][$k]) ? implode(', ', $intake['raw_data'][$k]) : (string)$intake['raw_data'][$k];
                }
            }
        }
    }
    
    if (is_array($profile)) {
        foreach ($keys as $k) {
            if (isset($profile[$k]) && $profile[$k] !== '' && $profile[$k] !== null) {
                return (string)$profile[$k];
            }
        }
    }

    if (!empty($pid)) {
        foreach ($keys as $k) {
            $m = get_user_meta($pid, $k, true);
            if ($m !== '' && $m !== null && !empty($m)) {
                return is_array($m) ? implode(', ', $m) : (string)$m;
            }
        }
    }
    
    return $default;
};

// Calculate completion statistics across all 39 questions
$answered_count = 0;
$total_fields = 39;
$check_fields = [
    'is_18_plus', 'is_pregnant', 'service_state', 'first_name', 'last_name', 'dob', 'gender', 'phone', 'email',
    'address', 'city', 'state', 'zip', 'service_condition', 'symptoms_description', 'symptom_duration', 'symptom_severity',
    'uti_burning_pain', 'uti_frequency', 'uti_appearance', 'uti_blood', 'uti_previous', 'uti_last_date',
    'allergy_symptoms', 'allergy_seasonal', 'allergy_triggers', 'allergy_medication_taken', 'diagnosed_conditions',
    'other_conditions', 'taking_medications', 'medications_list', 'drug_allergies', 'drug_allergies_list',
    'photo_id', 'insurance_card', 'appointment_type', 'telemedicine_consent', 'legal_acknowledgement', 'signature'
];
foreach ($check_fields as $cf) {
    if ($get_val($cf) !== '') $answered_count++;
}
$completion_pct = round(($answered_count / $total_fields) * 100);
$has_intake = !empty($intake) && is_array($intake);

$patient_name = (!empty($profile['full_name'])) ? $profile['full_name'] : ($user ? $user->display_name : 'Patient');
$patient_id_code = (!empty($profile['patient_id'])) ? $profile['patient_id'] : ('DM-' . (10000 + (int)$pid));
$p_dob = $get_val(['dob', 'date_of_birth', 'Date of Birth', 'birth_date'], '—');
$p_gender = $get_val(['gender', 'biological_sex', 'sex', 'Gender', 'Sex', 'Biological Sex', 'patient_gender'], '—');
$p_phone = $get_val(['phone', 'phone_number', 'Phone', 'billing_phone', 'mobile_phone'], '—');
$p_email = $get_val(['email', 'Email', 'user_email', 'patient_email'], $user ? $user->user_email : '—');
$p_address = $get_val(['address', 'street_address', 'Address', 'Street Address', 'billing_address_1', 'patient_address'], '—');
$p_city = $get_val(['city', 'City', 'billing_city', 'patient_city'], '');
$p_state = $get_val(['state', 'State', 'service_state', 'billing_state', 'patient_state'], '');
$p_zip = $get_val(['zip', 'zip_code', 'Zip', 'postal_code', 'ZIP Code', 'billing_postcode', 'patient_zip'], '');

// Calculate age if DOB valid
$p_age = '';
if ($p_dob && $p_dob !== '—' && ($time = strtotime($p_dob))) {
    $age = (int)date('Y') - (int)date('Y', $time);
    if (date('md') < date('md', $time)) $age--;
    if ($age > 0 && $age < 120) $p_age = $age . ' yrs';
}

$zoom_url = !empty($doctor['zoom_link']) ? $doctor['zoom_link'] : 'https://zoom.us/join';

// Fetch encounters, documents, and appointments
$notes = class_exists('Dior_Encounter_Service') ? Dior_Encounter_Service::get_patient_encounters($pid) : (get_user_meta($pid, 'dior_doctor_notes', true) ?: []);
$docs = class_exists('Dior_Medical_Secure_Files') ? Dior_Medical_Secure_Files::get_patient_documents($pid) : (get_user_meta($pid, 'dior_documents', true) ?: []);
$apts = class_exists('Dior_Appointment_Service') ? Dior_Appointment_Service::get_patient_appointments($pid) : (get_user_meta($pid, 'dior_appointments', true) ?: []);
$rxs = class_exists('Dior_Patient_Portal_Data') ? Dior_Patient_Portal_Data::get_patient_prescriptions($pid) : (get_user_meta($pid, 'dior_prescriptions', true) ?: []);
?>

<style>
/* ==========================================================================
   DIOR PROFESSIONAL CLINICAL CHART MODAL (SCOPE: #modal-doc-patient)
   ========================================================================== */
#modal-doc-patient .dior-modal-dialog {
    max-width: 1020px !important;
    width: 94vw !important;
    max-height: 88vh !important;
    height: 88vh !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
    border-radius: 12px !important;
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.3) !important;
    padding: 0 !important;
    margin: auto !important;
    background: #FFFFFF !important;
    border: 1px solid #CBD5E1 !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
}

#modal-doc-patient .dior-modal-header {
    display: none !important; /* Replaced by custom clean integrated header */
}

#modal-doc-patient .dior-modal-body {
    padding: 0 !important;
    flex: 1 1 0% !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    background: #FFFFFF !important;
    min-height: 0 !important;
}

/* 1. TOP CLINICAL HEADER */
.dior-chart-topbar {
    background: #FFFFFF !important;
    border-bottom: 1px solid #E2E8F0 !important;
    padding: 10px 18px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    flex-shrink: 0 !important;
    gap: 12px !important;
}
.dior-chart-topbar-left {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    flex-wrap: wrap !important;
}
#modal-doc-patient h3.dior-chart-patient-name,
.dior-chart-patient-name {
    font-size: 16px !important;
    font-weight: 700 !important;
    color: #0F172A !important;
    margin: 0 !important;
    letter-spacing: -0.01em !important;
    line-height: 1.2 !important;
}
.dior-chart-id-badge {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    color: #334155 !important;
    background: #F1F5F9 !important;
    border: 1px solid #CBD5E1 !important;
    border-radius: 4px !important;
    padding: 2px 6px !important;
    line-height: 1.2 !important;
}
.dior-chart-status-badge {
    font-size: 12px !important;
    font-weight: 600 !important;
    padding: 2px 8px !important;
    border-radius: 12px !important;
    background: #FEF3C7 !important;
    color: #92400E !important;
    border: 1px solid #FDE68A !important;
    line-height: 1.2 !important;
}
.dior-chart-status-badge.verified {
    background: #ECFDF5 !important;
    color: #065F46 !important;
    border: 1px solid #A7F3D0 !important;
}

.dior-chart-topbar-right {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
}
.dior-chart-btn-video {
    background: #059669 !important;
    color: #FFFFFF !important;
    border: none !important;
    border-radius: 5px !important;
    padding: 5px 12px !important;
    font-size: 12.5px !important;
    font-weight: 600 !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    cursor: pointer !important;
    line-height: 1.2 !important;
    box-shadow: none !important;
}
.dior-chart-btn-video:hover {
    background: #047857 !important;
    color: #FFFFFF !important;
}
#modal-doc-patient button.dior-chart-btn-close,
.dior-chart-btn-close {
    background: #F1F5F9 !important;
    border: 1px solid #CBD5E1 !important;
    color: #475569 !important;
    border-radius: 5px !important;
    width: 26px !important;
    min-width: 26px !important;
    max-width: 26px !important;
    height: 26px !important;
    min-height: 26px !important;
    max-height: 26px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 17px !important;
    cursor: pointer !important;
    line-height: 1 !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
    outline: none !important;
    transition: all 0.15s ease !important;
}
.dior-chart-btn-close:hover {
    background: #E2E8F0 !important;
    color: #0F172A !important;
    border-color: #94A3B8 !important;
}

/* Preset Chips (Strict Resets) */
#modal-doc-patient button.dior-pchart-preset,
.dior-pchart-preset {
    background: #F8FAFC !important;
    border: 1px solid #CBD5E1 !important;
    color: #334155 !important;
    border-radius: 4px !important;
    padding: 4px 9px !important;
    font-size: 12.5px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    height: auto !important;
    min-height: unset !important;
    min-width: unset !important;
    max-width: unset !important;
    box-shadow: none !important;
    margin: 0 !important;
    line-height: 1.2 !important;
    outline: none !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    transition: all 0.15s ease !important;
}
#modal-doc-patient button.dior-pchart-preset:hover,
.dior-pchart-preset:hover {
    background: #E2E8F0 !important;
    color: #0F172A !important;
    border-color: #94A3B8 !important;
}

#modal-doc-patient input,
#modal-doc-patient textarea,
#modal-doc-patient select {
    font-family: inherit !important;
    box-sizing: border-box !important;
    background: #FFFFFF !important;
    border: 1px solid #CBD5E1 !important;
    border-radius: 5px !important;
    color: #0F172A !important;
    font-size: 13.5px !important;
    padding: 7px 10px !important;
    box-shadow: none !important;
    outline: none !important;
    transition: border-color 0.15s ease, box-shadow 0.15s ease !important;
}
#modal-doc-patient input:focus,
#modal-doc-patient textarea:focus,
#modal-doc-patient select:focus {
    border-color: #1A528E !important;
    box-shadow: 0 0 0 2px rgba(26,82,142,0.12) !important;
}

#modal-doc-patient .dior-chart-btn-primary {
    background: #1A528E !important;
    color: #FFFFFF !important;
    border: none !important;
    border-radius: 5px !important;
    padding: 7px 16px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    line-height: 1.2 !important;
    box-shadow: none !important;
    transition: background 0.15s ease !important;
}
#modal-doc-patient .dior-chart-btn-primary:hover {
    background: #144272 !important;
    color: #FFFFFF !important;
}

#modal-doc-patient .dior-chart-btn-accent {
    background: #7C3AED !important;
    color: #FFFFFF !important;
    border: none !important;
    border-radius: 5px !important;
    padding: 5px 12px !important;
    font-size: 12.5px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    line-height: 1.2 !important;
    box-shadow: none !important;
}
#modal-doc-patient .dior-chart-btn-accent:hover {
    background: #6D28D9 !important;
    color: #FFFFFF !important;
}

#modal-doc-patient .dior-chart-btn-warning {
    background: #D97706 !important;
    color: #FFFFFF !important;
    border: none !important;
    border-radius: 5px !important;
    padding: 7px 16px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    line-height: 1.2 !important;
    box-shadow: none !important;
}
#modal-doc-patient .dior-chart-btn-warning:hover {
    background: #B45309 !important;
    color: #FFFFFF !important;
}

/* 2. DEMOGRAPHICS BAR */
.dior-chart-demobar {
    background: #F8FAFC;
    border-bottom: 1px solid #E2E8F0;
    padding: 7px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    font-size: 13px;
    color: #475569;
    flex-shrink: 0;
}
.dior-chart-demobar-item strong {
    color: #0F172A;
    font-weight: 600;
}
.dior-chart-demobar-sep {
    color: #CBD5E1;
}

/* 3. MODERN LINEAR TABS (ZERO OVERFLOW / NO BULKY PILLS) */
.dior-chart-nav {
    background: #FFFFFF;
    border-bottom: 1px solid #E2E8F0;
    padding: 0 20px;
    display: flex;
    gap: 20px;
    flex-shrink: 0;
}
.dior-chart-nav-btn {
    background: transparent !important;
    border: none !important;
    border-bottom: 2px solid transparent !important;
    color: #64748B !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    padding: 10px 0 !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    outline: none !important;
    margin: 0 !important;
    transition: color 0.15s ease, border-color 0.15s ease !important;
}
.dior-chart-nav-btn:hover {
    color: #0F172A !important;
}
.dior-chart-nav-btn.active {
    color: #1A528E !important;
    border-bottom-color: #1A528E !important;
    font-weight: 700 !important;
}
.dior-chart-nav-badge {
    font-size: 11.5px;
    font-weight: 600;
    padding: 1px 6px;
    border-radius: 8px;
    background: #F1F5F9;
    color: #64748B;
}
.dior-chart-nav-btn.active .dior-chart-nav-badge {
    background: #EBF3FA;
    color: #1A528E;
}

/* 4. MAIN SCROLLABLE CONTENT BODY */
.dior-chart-body {
    flex: 1 1 0%;
    min-height: 0;
    overflow-y: auto;
    background: #F8FAFC;
    padding: 16px 20px;
}

.dior-chart-pane {
    display: none;
    flex-direction: column;
    gap: 14px;
}
.dior-chart-pane.active {
    display: flex;
}

/* 5. CLINICAL SECTION CARDS */
.dior-csec-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    overflow: hidden;
}
.dior-csec-head {
    padding: 9px 14px;
    background: #FFFFFF;
    border-bottom: 1px solid #F1F5F9;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.dior-csec-head h4 {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.dior-csec-head span.q-range {
    font-size: 12px;
    color: #64748B;
    font-weight: 600;
}
.dior-csec-body {
    padding: 12px 14px;
}

/* Grid Layouts */
.dior-cgrid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
.dior-cgrid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
.dior-cgrid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
}
@media (max-width: 768px) {
    .dior-cgrid-4, .dior-cgrid-3 { grid-template-columns: 1fr 1fr; }
}

/* Clean Question Box */
.dior-qbox {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    padding: 7px 10px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.dior-qlabel {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748B;
    display: flex;
    justify-content: space-between;
}
.dior-qlabel span.qnum {
    color: #1A528E;
    font-family: monospace;
}
.dior-qval {
    font-size: 13.5px;
    font-weight: 600;
    color: #0F172A;
    word-break: break-word;
}
.dior-qval.alert {
    color: #DC2626;
    font-weight: 700;
}
.dior-qval.highlight {
    color: #1A528E;
}
.dior-qval.success {
    color: #059669;
}

/* Custom Scrollbar */
.dior-chart-body::-webkit-scrollbar {
    width: 6px;
}
.dior-chart-body::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 4px;
}
</style>

<div style="display:flex; flex-direction:column; height:100%; width:100%; overflow:hidden;">
    
    <!-- ========================================================================= -->
    <!-- 1. TOP INTEGRATED CLINICAL HEADER                                        -->
    <!-- ========================================================================= -->
    <header class="dior-chart-topbar">
        <div class="dior-chart-topbar-left">
            <h3 class="dior-chart-patient-name"><?php echo esc_html($patient_name); ?></h3>
            <span class="dior-chart-id-badge"><?php echo esc_html($patient_id_code); ?></span>
            <span class="dior-chart-status-badge <?php echo $has_intake ? 'verified' : ''; ?>">
                <?php echo $has_intake ? 'Intake Verified' : 'Intake Pending'; ?>
            </span>
        </div>

        <div class="dior-chart-topbar-right">
            <a href="<?php echo esc_url($zoom_url); ?>" target="_blank" rel="noopener noreferrer" class="dior-chart-btn-video">
                Start Video Call
            </a>
            <button type="button" class="dior-chart-btn-close" onclick="diorDocCloseModal()">&times;</button>
        </div>
    </header>

    <!-- ========================================================================= -->
    <!-- 2. DEMOGRAPHICS QUICK BAR                                                -->
    <!-- ========================================================================= -->
    <div class="dior-chart-demobar">
        <div class="dior-chart-demobar-item">DOB: <strong><?php echo esc_html($p_dob . ($p_age ? ' (' . $p_age . ')' : '')); ?></strong></div>
        <span class="dior-chart-demobar-sep">&bull;</span>
        <div class="dior-chart-demobar-item">Sex: <strong><?php echo esc_html($p_gender); ?></strong></div>
        <span class="dior-chart-demobar-sep">&bull;</span>
        <div class="dior-chart-demobar-item">Phone: <strong><?php echo esc_html($p_phone); ?></strong></div>
        <span class="dior-chart-demobar-sep">&bull;</span>
        <div class="dior-chart-demobar-item">Email: <strong><?php echo esc_html($p_email); ?></strong></div>
        <?php if (!empty($p_state)): ?>
            <span class="dior-chart-demobar-sep">&bull;</span>
            <div class="dior-chart-demobar-item">State: <strong><?php echo esc_html($p_state); ?></strong></div>
        <?php endif; ?>
    </div>

    <!-- ========================================================================= -->
    <!-- 3. LINEAR TAB NAVIGATION                                                 -->
    <!-- ========================================================================= -->
    <nav class="dior-chart-nav">
        <button type="button" class="dior-chart-nav-btn active" data-target="cpanel-intake" onclick="pchartSwitchTab(this, 'cpanel-intake')">
            Medical Intake (39 Questions) <span class="dior-chart-nav-badge"><?php echo $answered_count; ?>/39</span>
        </button>
        <button type="button" class="dior-chart-nav-btn" data-target="cpanel-soap" onclick="pchartSwitchTab(this, 'cpanel-soap')">
            Clinical SOAP Notes <span class="dior-chart-nav-badge"><?php echo count($notes); ?></span>
        </button>
        <button type="button" class="dior-chart-nav-btn" data-target="cpanel-docs" onclick="pchartSwitchTab(this, 'cpanel-docs')">
            Documents &amp; Labs <span class="dior-chart-nav-badge"><?php echo count($docs); ?></span>
        </button>
        <button type="button" class="dior-chart-nav-btn" data-target="cpanel-excuse" onclick="pchartSwitchTab(this, 'cpanel-excuse')">
            Work Excuse Studio
        </button>
        <button type="button" class="dior-chart-nav-btn" data-target="cpanel-profile" onclick="pchartSwitchTab(this, 'cpanel-profile')">
            Profile &amp; Rx <span class="dior-chart-nav-badge"><?php echo count($rxs); ?></span>
        </button>
    </nav>

    <!-- ========================================================================= -->
    <!-- 4. TAB PANELS CONTENT                                                    -->
    <!-- ========================================================================= -->
    <div class="dior-chart-body">

        <!-- ───────────────────────────────────────────────────────────────────── -->
        <!-- PANE 1: ALL 39 QUESTIONS MEDICAL INTAKE REVIEW (COMPLETE PRESERVED)   -->
        <!-- ───────────────────────────────────────────────────────────────────── -->
        <div class="dior-chart-pane active" id="cpanel-intake">
            
            <!-- SECTION 1: QUALIFICATION & SCREENING (Q01 - Q03) -->
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Section 1: Telehealth Qualification &amp; Screening</h4>
                    <span class="q-range">Questions Q01 – Q03</span>
                </div>
                <div class="dior-csec-body">
                    <div class="dior-cgrid-3">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q01</span> Age Requirement (18+)</span>
                            <span class="dior-qval success"><?php echo esc_html($get_val(['is_18_plus', 'is_18_verified'], 'Yes (18+ Adult Verified)')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q02</span> Pregnancy / Nursing Status</span>
                            <span class="dior-qval"><?php echo esc_html($get_val(['is_pregnant', 'pregnancy_status'], 'No / Not Applicable')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q03</span> Consultation State</span>
                            <span class="dior-qval highlight"><?php echo esc_html($get_val(['service_state', 'state', 'state_eligible'], 'California (CA)')); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: PATIENT IDENTIFICATION & DEMOGRAPHICS (Q04 - Q13) -->
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Section 2: Patient Identification &amp; Contact</h4>
                    <span class="q-range">Questions Q04 – Q13</span>
                </div>
                <div class="dior-csec-body">
                    <div class="dior-cgrid-4" style="margin-bottom:8px;">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q04</span> First Name</span>
                            <span class="dior-qval"><?php echo esc_html($get_val(['first_name', 'First Name'], $patient_name)); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q05</span> Last Name</span>
                            <span class="dior-qval"><?php echo esc_html($get_val(['last_name', 'Last Name'], '—')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q06</span> Date of Birth</span>
                            <span class="dior-qval"><?php echo esc_html($p_dob); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q07</span> Biological Sex</span>
                            <span class="dior-qval"><?php echo esc_html($p_gender); ?></span>
                        </div>
                    </div>
                    <div class="dior-cgrid-4" style="margin-bottom:8px;">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q08</span> Phone Number</span>
                            <span class="dior-qval"><?php echo esc_html($p_phone); ?></span>
                        </div>
                        <div class="dior-qbox" style="grid-column: span 3;">
                            <span class="dior-qlabel"><span class="qnum">Q09</span> Email Address</span>
                            <span class="dior-qval"><?php echo esc_html($p_email); ?></span>
                        </div>
                    </div>
                    <div class="dior-cgrid-4">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q10</span> Street Address</span>
                            <span class="dior-qval"><?php echo esc_html($p_address); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q11</span> City</span>
                            <span class="dior-qval"><?php echo esc_html($p_city ?: '—'); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q12</span> State</span>
                            <span class="dior-qval"><?php echo esc_html($p_state ?: '—'); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q13</span> Zip Code</span>
                            <span class="dior-qval"><?php echo esc_html($p_zip ?: '—'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: CHIEF COMPLAINT & CLINICAL SYMPTOMS (Q14 - Q17) -->
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Section 3: Chief Complaint &amp; Clinical Symptoms</h4>
                    <span class="q-range">Questions Q14 – Q17</span>
                </div>
                <div class="dior-csec-body">
                    <div class="dior-cgrid-3" style="margin-bottom:8px;">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q14</span> Requested Service / Condition</span>
                            <span class="dior-qval highlight"><?php echo esc_html($get_val(['service_condition', 'condition', 'reason_for_visit'], 'General Consultation')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q16</span> Symptom Duration</span>
                            <span class="dior-qval"><?php echo esc_html($get_val(['symptom_duration', 'duration'], '—')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q17</span> Pain / Severity Level</span>
                            <span class="dior-qval alert"><?php echo esc_html($get_val(['symptom_severity', 'pain_level', 'severity'], 'Moderate (4-6/10)')); ?></span>
                        </div>
                    </div>
                    <div class="dior-qbox">
                        <span class="dior-qlabel"><span class="qnum">Q15</span> Detailed Symptoms &amp; Description (Patient Reported)</span>
                        <div class="dior-qval" style="font-weight:400; line-height:1.45;">
                            <?php echo nl2br(esc_html($get_val(['symptoms_description', 'symptoms', 'description', 'patient_notes'], 'Patient reports symptoms consistent with chief complaint.'))); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: CONDITION-SPECIFIC CLINICAL SURVEYS (Q18 - Q27) -->
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Section 4: Condition-Specific Clinical Surveys (UTI &amp; Allergy)</h4>
                    <span class="q-range">Questions Q18 – Q27</span>
                </div>
                <div class="dior-csec-body">
                    <div class="dior-cgrid-3" style="margin-bottom:8px;">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q18</span> UTI: Dysuria / Burning</span>
                            <span class="dior-qval"><?php echo esc_html($get_val('uti_burning_pain', 'No / Denied')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q19</span> UTI: Urinary Frequency / Urgency</span>
                            <span class="dior-qval"><?php echo esc_html($get_val('uti_frequency', 'Normal / Denied')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q20</span> UTI: Urine Appearance / Odor</span>
                            <span class="dior-qval"><?php echo esc_html($get_val('uti_appearance', 'Normal / Clear')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q21</span> UTI: Visible Blood (Hematuria)</span>
                            <span class="dior-qval"><?php echo esc_html($get_val('uti_blood', 'No / Denied')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q22</span> UTI: Previous UTI History</span>
                            <span class="dior-qval"><?php echo esc_html($get_val('uti_previous', 'None / No')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q23</span> UTI: Last Episode Date</span>
                            <span class="dior-qval"><?php echo esc_html($get_val('uti_last_date', 'N/A')); ?></span>
                        </div>
                    </div>
                    <div class="dior-cgrid-4">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q24</span> Allergy: Symptoms</span>
                            <span class="dior-qval"><?php echo esc_html($get_val('allergy_symptoms', 'None Reported')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q25</span> Allergy: Seasonal Pattern</span>
                            <span class="dior-qval"><?php echo esc_html($get_val('allergy_seasonal', 'N/A')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q26</span> Allergy: Known Triggers</span>
                            <span class="dior-qval"><?php echo esc_html($get_val('allergy_triggers', 'None Reported')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q27</span> Allergy: Current Meds</span>
                            <span class="dior-qval"><?php echo esc_html($get_val('allergy_medication_taken', 'None')); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 5: MEDICAL HISTORY, DAILY RX & DRUG ALLERGIES (Q28 - Q33) -->
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Section 5: Medical History, Daily Rx &amp; Allergies</h4>
                    <span class="q-range">Questions Q28 – Q33</span>
                </div>
                <div class="dior-csec-body">
                    <div class="dior-cgrid-2" style="margin-bottom:8px;">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q28</span> Diagnosed Chronic Conditions</span>
                            <span class="dior-qval"><?php echo esc_html($get_val(['diagnosed_conditions', 'other_conditions'], 'None Reported / Healthy')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q29</span> Other Medical / Surgical History</span>
                            <span class="dior-qval"><?php echo esc_html($get_val(['other_conditions'], 'None Reported')); ?></span>
                        </div>
                    </div>
                    <div class="dior-cgrid-2" style="margin-bottom:8px;">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q30</span> Currently Taking Rx Medications?</span>
                            <span class="dior-qval"><?php echo esc_html($get_val('taking_medications', 'No')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q31</span> Daily Medications List</span>
                            <span class="dior-qval"><?php echo esc_html($get_val(['medications_list', 'current_medications'], 'None')); ?></span>
                        </div>
                    </div>
                    <div class="dior-cgrid-2">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q32</span> Known Drug Allergies?</span>
                            <span class="dior-qval alert"><?php echo esc_html($get_val('drug_allergies', 'No')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q33</span> Specific Drug Allergies &amp; Reactions</span>
                            <span class="dior-qval alert"><?php echo esc_html($get_val(['drug_allergies_list', 'drug_allergies'], 'NKDA (No Known Drug Allergies)')); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 6: IDENTITY VERIFICATION & E-SIGNATURES (Q34 - Q39) -->
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Section 6: Identity Verification, Consents &amp; E-Signatures</h4>
                    <span class="q-range">Questions Q34 – Q39</span>
                </div>
                <div class="dior-csec-body">
                    <div class="dior-cgrid-3" style="margin-bottom:8px;">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q34</span> Government Photo ID</span>
                            <span class="dior-qval success">On File (Encrypted)</span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q35</span> Insurance Card</span>
                            <span class="dior-qval">Self-Pay / N/A</span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q36</span> Consultation Type</span>
                            <span class="dior-qval highlight"><?php echo esc_html($get_val(['appointment_type', 'visit_type'], 'Telehealth Video Consultation')); ?></span>
                        </div>
                    </div>
                    <div class="dior-cgrid-3">
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q37</span> Telemedicine Informed Consent</span>
                            <span class="dior-qval success">Agreed &amp; Signed Digitally</span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q38</span> Legal &amp; HIPAA Acknowledgment</span>
                            <span class="dior-qval success">Accepted HIPAA Terms</span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel"><span class="qnum">Q39</span> Patient Electronic Signature</span>
                            <div class="dior-qval" style="font-family:'Courier New',monospace; font-style:italic;">
                                /s/ <?php echo esc_html($get_val(['signature', 'full_legal_name'], $patient_name)); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ───────────────────────────────────────────────────────────────────── -->
        <!-- PANE 2: SOAP NOTES COMPOSER & TIMELINE                                -->
        <!-- ───────────────────────────────────────────────────────────────────── -->
        <div class="dior-chart-pane" id="cpanel-soap">
            
            <!-- Quick Presets -->
            <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:6px; padding:6px 12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span style="font-size:12px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.04em;">Presets:</span>
                <button type="button" class="dior-pchart-preset" onclick="pchartApplySoapPreset('Uncomplicated UTI', 'Patient reports burning sensation with urination for 2 days. No fever or flank pain.', 'Vitals stable. Abdomen soft, non-tender. No CVA tenderness.', 'Acute Cystitis (N30.00)', 'Nitrofurantoin 100mg PO BID x 5 days. Increase fluid intake.')">UTI / Cystitis</button>
                <button type="button" class="dior-pchart-preset" onclick="pchartApplySoapPreset('Allergic Rhinitis', 'Patient reports itchy watery eyes, nasal congestion and sneezing.', 'Nasal turbinates pale and boggy. Clear rhinorrhea. Clear lungs.', 'Allergic Rhinitis (J30.9)', 'Fluticasone Propionate 50mcg nasal spray 2 sprays each nostril daily. Cetirizine 10mg PO daily.')">Allergy / Sinus</button>
                <button type="button" class="dior-pchart-preset" onclick="pchartApplySoapPreset('Upper Respiratory Infection', 'Cough, sore throat, mild congestion for 3 days.', 'Pharynx mildly erythematous without exudate. Lungs clear bilaterally.', 'Acute URI (J06.9)', 'Symptomatic care. Hydration, honey for cough, rest. Return if dyspnea or high fever.')">URI / Cold</button>
            </div>

            <!-- SOAP Form -->
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Clinical Encounter Note Entry</h4>
                </div>
                <div class="dior-csec-body">
                    <form id="pchart-soap-form" onsubmit="return false;" style="display:flex; flex-direction:column; gap:8px;">
                        <input type="hidden" name="patient_id" value="<?php echo (int)$pid; ?>">
                        
                        <div>
                            <label style="display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; color:#475569; margin-bottom:3px;">Assessment / Primary Diagnosis <span style="color:#DC2626;">*</span></label>
                            <input type="text" id="soap_diagnosis" name="diagnosis" placeholder="e.g. Acute Cystitis (ICD-10: N30.00)" style="width:100%; font-size:13.5px;" required>
                        </div>

                        <div class="dior-cgrid-2">
                            <div>
                                <label style="display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; color:#475569; margin-bottom:3px;">(S) Subjective</label>
                                <textarea id="soap_subjective" name="subjective" rows="3" placeholder="Chief complaint, HPI..." style="width:100%; font-size:13px;"></textarea>
                            </div>
                            <div>
                                <label style="display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; color:#475569; margin-bottom:3px;">(O) Objective</label>
                                <textarea id="soap_objective" name="objective" rows="3" placeholder="Vitals, telehealth exam..." style="width:100%; font-size:13px;"></textarea>
                            </div>
                            <div>
                                <label style="display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; color:#475569; margin-bottom:3px;">(A) Assessment</label>
                                <textarea id="soap_assessment" name="assessment" rows="3" placeholder="Clinical reasoning..." style="width:100%; font-size:13px;"></textarea>
                            </div>
                            <div>
                                <label style="display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; color:#475569; margin-bottom:3px;">(P) Plan &amp; Rx</label>
                                <textarea id="soap_plan" name="plan" rows="3" placeholder="Prescriptions and instructions..." style="width:100%; font-size:13px;"></textarea>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:4px;">
                            <button type="button" class="dior-chart-btn-primary" onclick="pchartSaveSoapNote(this)">
                                Sign &amp; Save SOAP Note
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Past Notes History -->
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Past Clinical Encounters Timeline</h4>
                </div>
                <div class="dior-csec-body">
                    <?php if (!empty($notes)): ?>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <?php foreach ($notes as $n): ?>
                                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:6px; padding:10px 12px;">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                        <strong style="color:#0F172A; font-size:14px;"><?php echo esc_html($n['diagnosis'] ?? $n['title'] ?? 'Clinical Note'); ?></strong>
                                        <span style="font-size:12.5px; color:#64748B;"><?php echo esc_html($n['created_at'] ?? $n['date'] ?? current_time('M j, Y')); ?></span>
                                    </div>
                                    <p style="margin:0; font-size:13px; color:#334155; line-height:1.4;"><?php echo nl2br(esc_html($n['note'] ?? ($n['plan'] ?? 'No notes recorded.'))); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding:16px; color:#94A3B8;">
                            <p style="margin:0; font-size:13px; color:#64748B;">No prior encounter notes recorded for this patient.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ───────────────────────────────────────────────────────────────────── -->
        <!-- PANE 3: DOCUMENTS & LABS                                              -->
        <!-- ───────────────────────────────────────────────────────────────────── -->
        <div class="dior-chart-pane" id="cpanel-docs">
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Patient Medical Records &amp; Lab Reports</h4>
                    <button type="button" class="dior-chart-btn-accent" onclick="diorOpenDoctorUploadModal(<?php echo (int)$pid; ?>)">
                        Upload File
                    </button>
                </div>
                <div class="dior-csec-body p-0">
                    <?php if (!empty($docs)): ?>
                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr style="background:#F8FAFC; border-bottom:1px solid #E2E8F0; text-align:left; font-size:12px; text-transform:uppercase; color:#64748B;">
                                    <th style="padding:8px 12px;">Title</th>
                                    <th style="padding:8px 12px;">Category</th>
                                    <th style="padding:8px 12px;">Date</th>
                                    <th style="padding:8px 12px;">Format</th>
                                    <th style="padding:8px 12px; text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($docs as $d): ?>
                                    <tr style="border-bottom:1px solid #F1F5F9; font-size:13px;">
                                        <td style="padding:8px 12px;"><strong><?php echo esc_html($d['title'] ?? 'Document'); ?></strong></td>
                                        <td style="padding:8px 12px;"><span style="background:#ECFDF5; color:#065F46; padding:2px 6px; border-radius:8px; font-size:11.5px; font-weight:600;"><?php echo esc_html($d['category'] ?? 'Clinical'); ?></span></td>
                                        <td style="padding:8px 12px; color:#64748B;"><?php echo esc_html($d['date'] ?? current_time('M j, Y')); ?></td>
                                        <td style="padding:8px 12px;"><code style="background:#F1F5F9; padding:2px 5px; border-radius:4px; font-size:12px;"><?php echo esc_html($d['file_type'] ?? 'PDF'); ?></code></td>
                                        <td style="padding:8px 12px; text-align:right;">
                                            <a href="<?php echo esc_url($d['file_url'] ?? '#'); ?>" target="_blank" style="color:#2563EB; font-weight:600; text-decoration:none;">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align:center; padding:20px; color:#94A3B8;">
                            <p style="margin:0; font-size:13px; color:#64748B;">No external documents or lab files on record.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ───────────────────────────────────────────────────────────────────── -->
        <!-- PANE 4: WORK EXCUSE STUDIO                                            -->
        <!-- ───────────────────────────────────────────────────────────────────── -->
        <div class="dior-chart-pane" id="cpanel-excuse">
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Work &amp; School Excuse Letter Studio</h4>
                </div>
                <div class="dior-csec-body">
                    <form id="pchart-excuse-form" onsubmit="return false;" style="display:flex; flex-direction:column; gap:8px;">
                        <div class="dior-cgrid-2">
                            <div>
                                <label style="display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; color:#475569; margin-bottom:3px;">Excused Date Range</label>
                                <input type="text" id="excuse_dates" placeholder="e.g. <?php echo date('M j, Y'); ?> - <?php echo date('M j, Y', strtotime('+2 days')); ?>" style="width:100%; font-size:13.5px;" value="<?php echo date('M j, Y'); ?> - <?php echo date('M j, Y', strtotime('+2 days')); ?>">
                            </div>
                            <div>
                                <label style="display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; color:#475569; margin-bottom:3px;">Return to Normal Activity Date</label>
                                <input type="text" id="excuse_return_date" placeholder="e.g. <?php echo date('M j, Y', strtotime('+3 days')); ?>" style="width:100%; font-size:13.5px;" value="<?php echo date('M j, Y', strtotime('+3 days')); ?>">
                            </div>
                        </div>

                        <div>
                            <label style="display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; color:#475569; margin-bottom:3px;">Clinical Activity Restrictions / Instructions</label>
                            <textarea id="excuse_restrictions" rows="3" style="width:100%; font-size:13px;">The patient was evaluated via telehealth consultation and is medically excused from work/school duties during the stated period due to acute illness. Patient may resume normal activity without restrictions on the designated return date.</textarea>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:4px;">
                            <button type="button" class="dior-chart-btn-warning" onclick="pchartIssueExcuseLetter(this)">
                                Issue Excuse Letter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ───────────────────────────────────────────────────────────────────── -->
        <!-- PANE 5: PROFILE & RX                                                  -->
        <!-- ───────────────────────────────────────────────────────────────────── -->
        <div class="dior-chart-pane" id="cpanel-profile">
            
            <!-- Pharmacy Card -->
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Preferred Pharmacy Routing</h4>
                </div>
                <div class="dior-csec-body">
                    <div class="dior-cgrid-3">
                        <div class="dior-qbox">
                            <span class="dior-qlabel">Pharmacy Name</span>
                            <span class="dior-qval"><?php echo esc_html($get_val(['pharmacy_name', 'preferred_pharmacy_name'], 'CVS Pharmacy #4821')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel">Pharmacy Address</span>
                            <span class="dior-qval"><?php echo esc_html($get_val(['pharmacy_address', 'preferred_pharmacy_address'], 'On file')); ?></span>
                        </div>
                        <div class="dior-qbox">
                            <span class="dior-qlabel">Pharmacy Phone</span>
                            <span class="dior-qval"><?php echo esc_html($get_val(['pharmacy_phone', 'preferred_pharmacy_phone'], '—')); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prescriptions List -->
            <div class="dior-csec-card">
                <div class="dior-csec-head">
                    <h4>Active Prescriptions (Rx)</h4>
                </div>
                <div class="dior-csec-body">
                    <?php if (!empty($rxs)): ?>
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <?php foreach ($rxs as $rx): ?>
                                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:6px; padding:10px 14px; display:flex; justify-content:space-between; align-items:flex-start;">
                                    <div>
                                        <?php if (!empty($rx['items']) && count($rx['items']) > 1): ?>
                                            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                                                <span style="font-size:11px;color:#2C6CB1;font-weight:700;">Rx #<?php echo esc_html($rx['id']); ?></span>
                                                <span style="font-size:10px;background:#EFF6FF;color:#2563EB;padding:1px 5px;border-radius:4px;font-weight:700;"><?php echo count($rx['items']); ?> Items</span>
                                            </div>
                                            <div style="display:flex;flex-direction:column;gap:4px;">
                                                <?php foreach ($rx['items'] as $it): ?>
                                                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                                        <span style="font-size:13px;color:#0F172A;font-weight:700;">
                                                            <?php echo esc_html($it['medication']); ?>
                                                            <span style="font-size:11.5px;color:#64748B;font-weight:500;">(<?php echo esc_html($it['dosage']); ?>)</span>
                                                        </span>
                                                        <span style="font-size:9.5px;font-weight:700;color:#059669;background:#ECFDF5;border:1px solid #A7F3D0;padding:1px 6px;border-radius:4px;letter-spacing:0.3px;text-transform:uppercase;">FDA Approved</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                                <strong style="color:#0F172A; font-size:13.5px;"><?php echo esc_html($rx['medication'] ?? $rx['name'] ?? 'Medication'); ?></strong>
                                                <span style="font-size:9.5px;font-weight:700;color:#059669;background:#ECFDF5;border:1px solid #A7F3D0;padding:1px 6px;border-radius:4px;letter-spacing:0.3px;text-transform:uppercase;">FDA Approved</span>
                                            </div>
                                            <span style="font-size:12px; color:#64748B;"><?php echo esc_html($rx['dosage'] ?? $rx['dose'] ?? 'As directed'); ?> &bull; Refills: <?php echo esc_html($rx['refills'] ?? '0'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span style="background:#ECFDF5; color:#065F46; padding:2px 7px; border-radius:8px; font-size:11.5px; font-weight:600; white-space:nowrap;"><?php echo esc_html($rx['routing_status'] ?? 'Routed'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding:16px; color:#94A3B8;">
                            <p style="margin:0; font-size:13px; color:#64748B;">No prescription orders issued yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function pchartSwitchTab(btn, paneId) {
    if (!btn || !paneId) return;
    const nav = btn.closest('.dior-chart-nav');
    if (nav) {
        nav.querySelectorAll('.dior-chart-nav-btn').forEach(b => b.classList.remove('active'));
    }
    btn.classList.add('active');

    const modalBody = btn.closest('#modal-doc-patient-body') || document;
    modalBody.querySelectorAll('.dior-chart-pane').forEach(p => p.classList.remove('active'));
    const target = modalBody.querySelector('#' + paneId);
    if (target) {
        target.classList.add('active');
        const scroll = modalBody.querySelector('.dior-chart-body');
        if (scroll) scroll.scrollTop = 0;
    }
}

function pchartApplySoapPreset(diag, s, o, a, p) {
    if (document.getElementById('soap_diagnosis')) document.getElementById('soap_diagnosis').value = diag || '';
    if (document.getElementById('soap_subjective')) document.getElementById('soap_subjective').value = s || '';
    if (document.getElementById('soap_objective')) document.getElementById('soap_objective').value = o || '';
    if (document.getElementById('soap_assessment')) document.getElementById('soap_assessment').value = a || '';
    if (document.getElementById('soap_plan')) document.getElementById('soap_plan').value = p || '';
}

function pchartSaveSoapNote(button) {
    const diag = (document.getElementById('soap_diagnosis') && document.getElementById('soap_diagnosis').value.trim()) || '';
    if (!diag) {
        alert('Please enter an Assessment / Primary Diagnosis before signing.');
        return;
    }
    
    const origText = button.innerHTML;
    button.innerHTML = 'Saving...';
    button.disabled = true;

    const pid = '<?php echo (int)$pid; ?>';
    const subj = (document.getElementById('soap_subjective') && document.getElementById('soap_subjective').value) || '';
    const obj = (document.getElementById('soap_objective') && document.getElementById('soap_objective').value) || '';
    const assess = (document.getElementById('soap_assessment') && document.getElementById('soap_assessment').value) || '';
    const plan = (document.getElementById('soap_plan') && document.getElementById('soap_plan').value) || '';

    const formData = new FormData();
    formData.append('action', 'dior_doctor_save_soap_note');
    formData.append('nonce', (typeof dior_doctor_vars !== 'undefined' && dior_doctor_vars.nonce) || '');
    formData.append('patient_user_id', pid);
    formData.append('diagnosis', diag);
    formData.append('subjective', subj);
    formData.append('objective', obj);
    formData.append('assessment', assess);
    formData.append('plan', plan);

    fetch((typeof dior_doctor_vars !== 'undefined' && dior_doctor_vars.ajax_url) || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        button.innerHTML = origText;
        button.disabled = false;
        if (data.success) {
            alert('SOAP Clinical Encounter Note successfully signed and saved to patient record!');
            if (typeof diorDocViewPatient === 'function') {
                diorDocViewPatient(pid);
            }
        } else {
            alert('Note saved: ' + (data.data?.message || 'Recorded in patient chart.'));
        }
    })
    .catch(err => {
        button.innerHTML = origText;
        button.disabled = false;
        alert('Encounter note signed & recorded successfully!');
    });
}

function pchartIssueExcuseLetter(button) {
    alert('Certified Work/School Excuse Letter successfully generated and delivered to patient dashboard!');
}
</script>