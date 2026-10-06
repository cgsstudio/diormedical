<?php
/**
 * Dior Medical - Premium Appointment Booking Page
 */
if (!defined('ABSPATH')) {
    exit;
}

// Fetch doctors
$doctors = get_users(['role' => 'dior_doctor']);
?>

<div class="dior-appointment-wrapper">
    <div class="dior-booking-container">
        
        <!-- Header -->
        <div class="dior-booking-header">
            <div class="dior-header-content">
                <h2><i class="fas fa-calendar-check" style="color: #D4AF37;"></i> Schedule Your Consultation</h2>
                <p>Select your preferred physician and secure your telehealth appointment instantly.</p>
            </div>
            <div class="dior-header-badge">
                <span><i class="fas fa-shield-halved"></i> Secure & Confidential</span>
            </div>
        </div>

        <!-- Booking Wizard -->
        <div class="dior-booking-wizard">
            
            <!-- Step 1: Select Physician -->
            <div class="dior-wizard-step active" id="dior-step-1">
                <div class="dior-step-indicator">
                    <span class="step-circle active">1</span>
                    <span class="step-title">Choose Specialist</span>
                </div>
                
                <div class="dior-doctors-grid">
                    <?php if (!empty($doctors)): ?>
                        <?php foreach ($doctors as $doctor): 
                            $specialty = get_user_meta($doctor->ID, 'dior_specialty', true) ?: 'Telehealth Physician';
                            $avatar = get_avatar_url($doctor->ID, ['size' => 150]);
                        ?>
                        <div class="dior-doctor-card" onclick="diorSelectDoctor(<?php echo esc_js($doctor->ID); ?>, '<?php echo esc_js($doctor->display_name); ?>', '<?php echo esc_js($specialty); ?>', '<?php echo esc_js($avatar); ?>')">
                            <div class="doctor-avatar">
                                <img src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($doctor->display_name); ?>">
                            </div>
                            <div class="doctor-info">
                                <h3>Dr. <?php echo esc_html($doctor->display_name); ?></h3>
                                <span><?php echo esc_html($specialty); ?></span>
                            </div>
                            <div class="doctor-select-btn">Select Physician</div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dior-no-doctors">
                            <p>No physicians currently available for online booking. Please contact support.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Step 2: Select Date & Time -->
            <div class="dior-wizard-step" id="dior-step-2" style="display:none;">
                <div class="dior-step-indicator">
                    <span class="step-circle active">2</span>
                    <span class="step-title">Select Date & Time</span>
                </div>

                <div class="dior-selected-doctor-summary">
                    <img id="summary-doc-img" src="" alt="Doctor">
                    <div class="summary-doc-details">
                        <span class="lbl">Consultation with</span>
                        <h4 id="summary-doc-name">Dr. Name</h4>
                        <span id="summary-doc-spec" class="spec">Specialty</span>
                    </div>
                    <button type="button" class="dior-btn-change-doc" onclick="diorGoBackToStep1()">Change</button>
                </div>

                <div class="dior-datetime-picker">
                    <div class="dior-date-col">
                        <h4><i class="far fa-calendar-days"></i> Choose Date</h4>
                        <input type="date" id="dior_appointment_date" class="dior-glass-input" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="dior-time-col">
                        <h4><i class="far fa-clock"></i> Choose Time Slot</h4>
                        <div class="dior-time-slots" id="dior-time-slots-container">
                            <span class="dior-slot-hint">Please select a date first</span>
                        </div>
                    </div>
                </div>

                <div class="dior-booking-actions">
                    <button type="button" class="dior-btn-secondary" onclick="diorGoBackToStep1()">Back</button>
                    <button type="button" class="dior-btn-primary" onclick="diorProceedToStep3()">Continue <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- Step 3: Confirmation -->
            <div class="dior-wizard-step" id="dior-step-3" style="display:none;">
                <div class="dior-step-indicator">
                    <span class="step-circle active">3</span>
                    <span class="step-title">Confirm Appointment</span>
                </div>

                <div class="dior-confirmation-card">
                    <div class="confirm-header">
                        <i class="far fa-circle-check"></i>
                        <h3>Review Your Details</h3>
                    </div>
                    
                    <div class="confirm-details">
                        <div class="detail-row">
                            <span>Physician</span>
                            <strong id="confirm-doc-name">---</strong>
                        </div>
                        <div class="detail-row">
                            <span>Date</span>
                            <strong id="confirm-date">---</strong>
                        </div>
                        <div class="detail-row">
                            <span>Time</span>
                            <strong id="confirm-time">---</strong>
                        </div>
                        <div class="detail-row">
                            <span>Consultation Type</span>
                            <strong>Telehealth Video Visit</strong>
                        </div>
                    </div>

                    <div class="dior-reason-field">
                        <label>Reason for Visit (Optional)</label>
                        <textarea id="dior_appointment_reason" class="dior-glass-input" rows="3" placeholder="Briefly describe your symptoms or request..."></textarea>
                    </div>

                    <div class="dior-booking-actions">
                        <button type="button" class="dior-btn-secondary" onclick="diorGoBackToStep2()">Back</button>
                        <button type="button" class="dior-btn-gold" id="btn-confirm-booking" onclick="diorSubmitBooking()">
                            <span>Confirm & Book Appointment</span>
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Success State -->
            <div class="dior-wizard-step" id="dior-step-success" style="display:none; text-align:center; padding: 40px 20px;">
                <div class="dior-success-icon">
                    <i class="fas fa-circle-check" style="font-size: 64px; color: #059669;"></i>
                </div>
                <h2 style="color: #0F172A; margin: 15px 0 10px;">Appointment Confirmed!</h2>
                <p style="color: #64748B; font-size: 15px; margin-bottom: 25px;">Your telehealth consultation has been successfully scheduled. An email confirmation has been sent.</p>
                <a href="<?php echo esc_url(get_permalink(get_option('dior_dashboard_page_id'))); ?>" class="dior-btn-primary" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                    Go to Patient Dashboard <i class="fas fa-arrow-right"></i>
                </a>
            </div>

        </div>
    </div>
</div>

<script>
let diorSelectedDoctorId = null;
let diorSelectedTime = null;

function diorSelectDoctor(id, name, specialty, avatar) {
    diorSelectedDoctorId = id;
    
    // Update summary UI
    document.getElementById('summary-doc-img').src = avatar;
    document.getElementById('summary-doc-name').innerText = 'Dr. ' + name;
    document.getElementById('summary-doc-spec').innerText = specialty;
    document.getElementById('confirm-doc-name').innerText = 'Dr. ' + name;

    // Transition
    document.getElementById('dior-step-1').style.display = 'none';
    document.getElementById('dior-step-2').style.display = 'block';
}

function diorGoBackToStep1() {
    document.getElementById('dior-step-2').style.display = 'none';
    document.getElementById('dior-step-1').style.display = 'block';
}

function diorGoBackToStep2() {
    document.getElementById('dior-step-3').style.display = 'none';
    document.getElementById('dior-step-2').style.display = 'block';
}

// Generate Mock Time Slots when Date is picked
document.getElementById('dior_appointment_date')?.addEventListener('change', function(e) {
    const val = e.target.value;
    const container = document.getElementById('dior-time-slots-container');
    
    if (!val) {
        container.innerHTML = '<span class="dior-slot-hint">Please select a date first</span>';
        return;
    }

    // Mock slots for premium UI demo
    const slots = ['09:00 AM', '10:30 AM', '01:00 PM', '02:30 PM', '04:00 PM'];
    let html = '';
    
    slots.forEach(slot => {
        html += `<div class="dior-time-slot" onclick="diorSelectTime(this, '${slot}')">${slot}</div>`;
    });
    
    container.innerHTML = html;
    diorSelectedTime = null;
});

function diorSelectTime(element, time) {
    document.querySelectorAll('.dior-time-slot').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    diorSelectedTime = time;
}

function diorProceedToStep3() {
    const date = document.getElementById('dior_appointment_date').value;
    
    if (!date) {
        alert("Please select a date.");
        return;
    }
    if (!diorSelectedTime) {
        alert("Please select a time slot.");
        return;
    }

    // Format date nicely
    const dateObj = new Date(date);
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    
    document.getElementById('confirm-date').innerText = dateObj.toLocaleDateString(undefined, options);
    document.getElementById('confirm-time').innerText = diorSelectedTime;

    document.getElementById('dior-step-2').style.display = 'none';
    document.getElementById('dior-step-3').style.display = 'block';
}

function diorSubmitBooking() {
    const btn = document.getElementById('btn-confirm-booking');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    btn.disabled = true;

    const data = {
        action: 'dior_book_appointment_from_page',
        doctor_id: diorSelectedDoctorId,
        date: document.getElementById('dior_appointment_date').value,
        time: diorSelectedTime,
        reason: document.getElementById('dior_appointment_reason').value,
        security: typeof dior_ajax_object !== 'undefined' ? dior_ajax_object.nonce : ''
    };

    jQuery.post(typeof dior_ajax_object !== 'undefined' ? dior_ajax_object.ajax_url : '/wp-admin/admin-ajax.php', data, function(response) {
        if (response.success) {
            document.getElementById('dior-step-3').style.display = 'none';
            document.getElementById('dior-step-success').style.display = 'block';
        } else {
            alert(response.data || "An error occurred while booking.");
            btn.innerHTML = '<span>Confirm & Book Appointment</span><i class="fas fa-check"></i>';
            btn.disabled = false;
        }
    }).fail(function() {
        alert("Server error. Please try again.");
        btn.innerHTML = '<span>Confirm & Book Appointment</span><i class="fas fa-check"></i>';
        btn.disabled = false;
    });
}
</script>
