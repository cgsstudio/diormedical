<?php
/**
 * Dior Medical - Premium Appointment Booking Page
 * 100% Native Bootstrap 5 Implementation
 */
if (!defined('ABSPATH')) {
    exit;
}

// Fetch doctors: combine doctor users and wpddb_doctor posts
$doctor_cards = [];

// 1. Doctors from WP Users
$u_docs = get_users(['role__in' => ['doctor', 'dior_doctor', 'administrator']]);
foreach ($u_docs as $ud) {
    if (in_array('administrator', (array)$ud->roles, true) && !in_array('doctor', (array)$ud->roles, true)) {
        continue;
    }
    $u_avatar = get_user_meta($ud->ID, 'dior_profile_image', true)
        ?: get_user_meta($ud->ID, 'doctor_avatar', true)
        ?: get_user_meta($ud->ID, 'profile_picture', true)
        ?: home_url('/wp-content/uploads/2026/10/diormedical-experts-6.webp');

    $u_spec = get_user_meta($ud->ID, 'doctor_specialty', true)
        ?: get_user_meta($ud->ID, 'dior_specialty', true)
        ?: 'General Practice & Internal Medicine';

    $doctor_cards[] = [
        'id'        => $ud->ID,
        'name'      => trim($ud->first_name . ' ' . $ud->last_name) ?: $ud->display_name,
        'specialty' => $u_spec,
        'avatar'    => $u_avatar
    ];
}

// 2. Doctors from wpddb_doctor custom post type
$post_docs = get_posts(['post_type' => 'wpddb_doctor', 'numberposts' => -1]);
foreach ($post_docs as $pd) {
    $p_avatar = get_post_meta($pd->ID, '_wpddb_doctor_avatar', true)
        ?: get_post_meta($pd->ID, 'wpddb_doctor_avatar', true)
        ?: get_the_post_thumbnail_url($pd->ID, 'full')
        ?: home_url('/wp-content/uploads/2026/10/diormedical-experts-6.webp');

    $p_spec = get_post_meta($pd->ID, 'wpddb_doctor_speciality', true) ?: 'General Practice & Internal Medicine';

    $already = false;
    foreach ($doctor_cards as $dc) {
        if (stripos($dc['name'], $pd->post_title) !== false || stripos($pd->post_title, $dc['name']) !== false) {
            $already = true;
            break;
        }
    }
    if (!$already) {
        $doctor_cards[] = [
            'id'        => $pd->ID,
            'name'      => $pd->post_title,
            'specialty' => $p_spec,
            'avatar'    => $p_avatar
        ];
    }
}

// Ensure at least one primary doctor is available
if (empty($doctor_cards)) {
    $doctor_cards[] = [
        'id'        => 1957,
        'name'      => 'Dr. Marcus Sterling, DO',
        'specialty' => 'General Practice & Internal Medicine',
        'avatar'    => home_url('/wp-content/uploads/2026/10/diormedical-experts-6.webp')
    ];
}

$dashboard_url = home_url('/patient-dashboard/#tab=overview');
$current_patient = wp_get_current_user();
?>

<div class="bg-light min-vh-100 py-4 py-md-5">
    <div class="container" style="max-width: 900px;">

        <!-- Top Navigation / Back Bar -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <a href="<?php echo esc_url($dashboard_url); ?>" class="btn btn-outline-secondary rounded-3 fw-semibold py-2 px-3 shadow-sm d-inline-flex align-items-center gap-2" style="font-size: 14px !important; text-decoration: none;">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2 small" style="font-size: 13px !important;">
                    <i class="fas fa-shield-halved me-1"></i> 256-Bit Secure &amp; HIPAA Compliant
                </span>
            </div>
        </div>

        <!-- Main Booking Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
            
            <!-- Header Section -->
            <div class="border-bottom pb-4 mb-4">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 small mb-2">Telehealth Medical Care</span>
                <h1 class="h3 fw-bold text-dark mb-1">Schedule Your Telehealth Consultation</h1>
                <p class="text-secondary small mb-0">Select your preferred physician and secure your video or phone consultation in minutes.</p>
            </div>

            <!-- Appointment booking progress -->
            <style>
                .dior-booking-progress{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));width:100%;margin-bottom:1.5rem;overflow:hidden;border:1px solid #dfe7f1;border-radius:14px;background:#fff;box-shadow:0 5px 18px rgba(26,54,93,.045)}
                .dior-booking-step{position:relative;display:flex;align-items:center;min-width:0;padding:18px 20px;color:#718096;background:#fff;transition:background-color .2s ease,color .2s ease}
                .dior-booking-step:not(:last-child){border-right:1px solid #e2eaf3}
                .dior-booking-step-main{display:flex;align-items:center;gap:12px;min-width:0}
                .dior-step-number{display:inline-flex;flex:0 0 38px;align-items:center;justify-content:center;width:38px;height:38px;padding:0;border:1px solid #dfe6ef;border-radius:50%;background:#e8edf4;color:#60738c;font-size:14px;font-weight:700;box-shadow:none}
                .dior-step-copy{min-width:0}
                .dior-step-meta{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin-bottom:3px;color:#718096;font-size:10px;line-height:1.3;font-weight:700;letter-spacing:.065em}
                .dior-step-status{display:none;padding:3px 9px;border-radius:999px;background:#e5efff;color:#2865a5;font-size:10px;font-weight:700;letter-spacing:0}
                .dior-step-title{margin:0;color:#586a80;font-size:14px;line-height:1.35;font-weight:700}
                .dior-step-description{margin-top:2px;color:#8290a3;font-size:11px;line-height:1.4}
                .dior-booking-step.is-active .dior-step-number{border-color:#2865a5;background:#2865a5;color:#fff;box-shadow:0 3px 8px rgba(40,101,165,.18)}
                .dior-booking-step.is-active .dior-step-meta{color:#2865a5}
                .dior-booking-step.is-active .dior-step-status{display:inline-flex}
                .dior-booking-step.is-active .dior-step-title{color:#172b49}
                .dior-booking-step.is-complete .dior-step-number{border-color:#d4e8dc;background:#e5f5eb;color:#23804b}
                .dior-booking-step.is-complete .dior-step-title{color:#344b64}
                @media(max-width:767.98px){.dior-booking-progress{grid-template-columns:1fr}.dior-booking-step{padding:14px 16px}.dior-booking-step:not(:last-child){border-right:0;border-bottom:1px solid #e2eaf3}.dior-step-number{flex-basis:34px;width:34px;height:34px}.dior-step-title{font-size:13px}}
                @media(prefers-reduced-motion:reduce){.dior-booking-step{transition:none}}
            </style>
            <div class="dior-booking-progress" aria-label="Appointment booking progress">
                <div class="dior-booking-step is-active" id="step-nav-1" data-step="1" aria-current="step">
                    <div class="dior-booking-step-main">
                        <span class="dior-step-number badge">1</span>
                        <div class="dior-step-copy">
                            <div class="dior-step-meta">STEP 1 <span class="dior-step-status">Current</span></div>
                            <div class="dior-step-title">Choose Specialist</div>
                            <div class="dior-step-description">Select your preferred physician</div>
                        </div>
                    </div>
                </div>
                <div class="dior-booking-step" id="step-nav-2" data-step="2">
                    <div class="dior-booking-step-main">
                        <span class="dior-step-number badge">2</span>
                        <div class="dior-step-copy">
                            <div class="dior-step-meta">STEP 2 <span class="dior-step-status">Current</span></div>
                            <div class="dior-step-title">Date &amp; Time</div>
                            <div class="dior-step-description">Choose an available time slot</div>
                        </div>
                    </div>
                </div>
                <div class="dior-booking-step" id="step-nav-3" data-step="3">
                    <div class="dior-booking-step-main">
                        <span class="dior-step-number badge">3</span>
                        <div class="dior-step-copy">
                            <div class="dior-step-meta">STEP 3 <span class="dior-step-status">Current</span></div>
                            <div class="dior-step-title">Review &amp; Confirm</div>
                            <div class="dior-step-description">Verify your appointment details</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 1: Select Physician -->
            <div id="dior-step-1">
                <h4 class="h5 fw-bold text-dark mb-3"><i class="fas fa-user-doctor text-primary me-2"></i>Select an Available Physician</h4>
                <div class="row g-3 g-md-4 mb-4">
                    <?php foreach ($doctor_cards as $doc): ?>
                    <div class="col-12 col-md-6">
                        <div class="card border rounded-4 p-3 h-100 shadow-sm d-flex flex-column justify-content-between" style="cursor: pointer; transition: all 0.2s ease-in-out;" onclick="diorSelectDoctor(<?php echo esc_js($doc['id']); ?>, '<?php echo esc_js($doc['name']); ?>', '<?php echo esc_js($doc['specialty']); ?>', '<?php echo esc_js($doc['avatar']); ?>');" onmouseover="this.classList.add('border-primary', 'shadow');" onmouseout="this.classList.remove('border-primary', 'shadow');">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle overflow-hidden flex-shrink-0 border" style="width: 68px; height: 68px;">
                                    <img src="<?php echo esc_url($doc['avatar']); ?>" alt="<?php echo esc_attr($doc['name']); ?>" class="w-100 h-100 object-fit-cover" onerror="this.src='<?php echo esc_url(home_url('/wp-content/uploads/2026/10/diormedical-experts-6.webp')); ?>';">
                                </div>
                                <div class="min-w-0">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0 small mb-1">Available Online</span>
                                    <h5 class="fs-6 fw-bold text-dark mb-0 text-truncate"><?php echo esc_html($doc['name']); ?></h5>
                                    <div class="text-secondary small text-truncate"><?php echo esc_html($doc['specialty']); ?></div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary rounded-3 fw-semibold py-2 px-3 w-100" onclick="event.stopPropagation(); diorSelectDoctor(<?php echo esc_js($doc['id']); ?>, '<?php echo esc_js($doc['name']); ?>', '<?php echo esc_js($doc['specialty']); ?>', '<?php echo esc_js($doc['avatar']); ?>');">
                                Select Physician &rarr;
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- STEP 2: Select Date & Time -->
            <div id="dior-step-2" style="display: none;">
                <!-- Selected Doctor Header Pill -->
                <div class="card bg-light-subtle border rounded-4 p-3 mb-4 d-flex flex-row align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle overflow-hidden flex-shrink-0" style="width: 52px; height: 52px;">
                            <img id="summary-doc-img" src="" alt="Doctor" class="w-100 h-100 object-fit-cover">
                        </div>
                        <div>
                            <span class="text-secondary small">Consultation With</span>
                            <h5 class="fs-6 fw-bold text-dark mb-0" id="summary-doc-name">Doctor Name</h5>
                            <span class="text-secondary small" id="summary-doc-spec">Specialty</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" onclick="diorGoBackToStep1()">
                        Change Physician
                    </button>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark"><i class="far fa-calendar text-primary me-2"></i>Choose Date</label>
                        <input type="date" id="dior_appointment_date" class="form-control rounded-3 py-2 px-3" min="<?php echo current_time('Y-m-d'); ?>" value="<?php echo current_time('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark"><i class="far fa-clock text-primary me-2"></i>Choose Time Slot</label>
                        <div class="d-flex flex-wrap gap-2" id="dior-time-slots-container">
                            <!-- Time slots generated by JS -->
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-3 fw-semibold py-2 px-4" onclick="diorGoBackToStep1()">
                        &larr; Back
                    </button>
                    <button type="button" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm" onclick="diorProceedToStep3()">
                        Continue &rarr;
                    </button>
                </div>
            </div>

            <!-- STEP 3: Review & Confirm -->
            <div id="dior-step-3" style="display: none;">
                <h4 class="h5 fw-bold text-dark mb-3"><i class="far fa-circle-check text-primary me-2"></i>Review Appointment Details</h4>
                
                <div class="card bg-light-subtle border rounded-4 p-4 mb-4">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Attending Physician</span>
                            <span class="fw-bold text-dark fs-6" id="confirm-doc-name">---</span>
                        </div>
                        <div class="col-12 col-sm-6">
                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Consultation Type</span>
                            <span class="fw-bold text-dark fs-6">Telehealth Video Visit (HD)</span>
                        </div>
                        <div class="col-12 col-sm-6">
                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Scheduled Date</span>
                            <span class="fw-bold text-dark fs-6" id="confirm-date">---</span>
                        </div>
                        <div class="col-12 col-sm-6">
                            <span class="small text-uppercase text-secondary fw-semibold d-block mb-1">Scheduled Time</span>
                            <span class="fw-bold text-dark fs-6" id="confirm-time">---</span>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="dior_appointment_reason" class="form-label fw-bold text-dark">Reason for Visit / Symptoms (Optional)</label>
                    <textarea id="dior_appointment_reason" class="form-control rounded-3 p-3" rows="3" placeholder="Briefly describe any symptoms, medication refill requests, or concerns..."></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-3 fw-semibold py-2 px-4" onclick="diorGoBackToStep2()">
                        &larr; Back
                    </button>
                    <button type="button" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm d-inline-flex align-items-center gap-2" id="btn-confirm-booking" onclick="diorSubmitBooking()">
                        <i class="fas fa-check"></i>
                        <span>Confirm &amp; Book Appointment</span>
                    </button>
                </div>
            </div>

            <!-- SUCCESS STATE -->
            <div id="dior-step-success" style="display: none;" class="text-center py-5">
                <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center p-4 mb-3" style="width: 80px; height: 80px;">
                    <i class="fas fa-circle-check fs-1"></i>
                </div>
                <h2 class="h4 fw-bold text-dark mb-2">Appointment Confirmed!</h2>
                <p class="text-secondary small mb-4 mx-auto" style="max-width: 500px;">
                    Your telehealth consultation has been successfully scheduled. You can join the secure video room at your appointment time from your dashboard.
                </p>
                <a href="<?php echo esc_url($dashboard_url); ?>" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm">
                    Return to Patient Dashboard &rarr;
                </a>
            </div>

        </div>
    </div>
</div>

<script>
var diorSelectedDoctorId = null;
var diorSelectedDoctorName = '';
var diorSelectedDoctorSpec = '';
var diorSelectedDoctorAvatar = '';
var diorSelectedTime = null;

function diorUpdateBookingProgress(activeStep) {
    document.querySelectorAll('.dior-booking-step').forEach(function (step) {
        var stepNumber = parseInt(step.getAttribute('data-step'), 10);
        var status = step.querySelector('.dior-step-status');
        step.classList.toggle('is-active', stepNumber === activeStep);
        step.classList.toggle('is-complete', stepNumber < activeStep);
        if (stepNumber === activeStep) {
            step.setAttribute('aria-current', 'step');
            if (status) status.textContent = 'Current';
        } else {
            step.removeAttribute('aria-current');
            if (status) status.textContent = stepNumber < activeStep ? 'Complete' : 'Upcoming';
        }
    });
}

function diorSelectDoctor(id, name, specialty, avatar) {
    diorSelectedDoctorId = id;
    diorSelectedDoctorName = name;
    diorSelectedDoctorSpec = specialty;
    diorSelectedDoctorAvatar = avatar;

    document.getElementById('summary-doc-img').src = avatar;
    document.getElementById('summary-doc-name').textContent = name;
    document.getElementById('summary-doc-spec').textContent = specialty;
    document.getElementById('confirm-doc-name').textContent = name;

    diorUpdateBookingProgress(2);

    document.getElementById('dior-step-1').style.display = 'none';
    document.getElementById('dior-step-2').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });

    diorPopulateTimeSlots();
}

function diorGoBackToStep1() {
    diorUpdateBookingProgress(1);
    document.getElementById('dior-step-2').style.display = 'none';
    document.getElementById('dior-step-1').style.display = 'block';
}

function diorGoBackToStep2() {
    diorUpdateBookingProgress(2);
    document.getElementById('dior-step-3').style.display = 'none';
    document.getElementById('dior-step-2').style.display = 'block';
}

function diorPopulateTimeSlots() {
    var slots = ['09:00 AM - 10:00 AM', '10:30 AM - 11:30 AM', '01:00 PM - 02:00 PM', '02:30 PM - 03:30 PM', '04:00 PM - 05:00 PM', '05:30 PM - 06:30 PM'];
    var container = document.getElementById('dior-time-slots-container');
    if (!container) return;

    var html = '';
    slots.forEach(function(slot, idx) {
        var isSelected = (idx === 0);
        if (isSelected && !diorSelectedTime) diorSelectedTime = slot;
        var btnClass = isSelected ? 'btn btn-primary rounded-3 px-3 py-2 small fw-semibold' : 'btn btn-outline-primary rounded-3 px-3 py-2 small fw-semibold';
        html += '<button type="button" class="' + btnClass + ' slot-btn" onclick="diorSelectSlot(this, \'' + slot + '\');">' + slot + '</button>';
    });
    container.innerHTML = html;
}

function diorSelectSlot(el, slot) {
    document.querySelectorAll('#dior-time-slots-container .slot-btn').forEach(function(btn) {
        btn.className = 'btn btn-outline-primary rounded-3 px-3 py-2 small fw-semibold slot-btn';
    });
    el.className = 'btn btn-primary rounded-3 px-3 py-2 small fw-semibold slot-btn';
    diorSelectedTime = slot;
}

function diorProceedToStep3() {
    var dateInput = document.getElementById('dior_appointment_date');
    if (!dateInput || !dateInput.value) {
        alert('Please choose a consultation date.');
        return;
    }
    if (!diorSelectedTime) {
        alert('Please choose an appointment time slot.');
        return;
    }

    document.getElementById('confirm-date').textContent = dateInput.value;
    document.getElementById('confirm-time').textContent = diorSelectedTime;

    diorUpdateBookingProgress(3);

    document.getElementById('dior-step-2').style.display = 'none';
    document.getElementById('dior-step-3').style.display = 'block';
}

function diorSubmitBooking() {
    var btn = document.getElementById('btn-confirm-booking');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    }

    var postData = new FormData();
    postData.append('action', 'dior_book_appointment_from_page');
    postData.append('doctor_id', diorSelectedDoctorId || 1957);
    postData.append('date', document.getElementById('dior_appointment_date').value);
    postData.append('time', diorSelectedTime || '10:00 AM - 11:00 AM');
    postData.append('reason', document.getElementById('dior_appointment_reason').value);

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: postData
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('dior-step-3').style.display = 'none';
            document.getElementById('dior-step-success').style.display = 'block';
        } else {
            alert(data.data || 'Failed to complete booking. Please try again.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm &amp; Book Appointment';
            }
        }
    })
    .catch(function(err) {
        console.error(err);
        alert('Server error while booking. Please try again.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm &amp; Book Appointment';
        }
    });
}
</script>
