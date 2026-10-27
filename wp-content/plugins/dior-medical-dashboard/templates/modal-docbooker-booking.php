<?php
/**
 * Dior Medical - DocBooker Interactive Appointment Booking Modal
 * Clean, modern registration-style UI matching Dior Patient Portal design standards.
 * Supports Telehealth Video Visit & Phone Consultation scheduling
 * with live doctor availability and real-time dashboard updates.
 */
if (!defined('ABSPATH')) {
    exit;
}

$current_user_id = get_current_user_id();
$user_obj = $current_user_id ? get_userdata($current_user_id) : null;
$profile = ($current_user_id && class_exists('Dior_Patient_Portal_Data')) ? Dior_Patient_Portal_Data::get_patient_profile($current_user_id) : [];
$intake = $current_user_id ? get_user_meta($current_user_id, 'dior_hipaa_intake', true) : [];

$patient_name = !empty($profile['full_name']) ? $profile['full_name'] : ($user_obj ? $user_obj->display_name : '');
$patient_email = !empty($profile['email']) ? $profile['email'] : ($user_obj ? $user_obj->user_email : '');
$patient_phone = !empty($profile['phone']) ? $profile['phone'] : (!empty($intake['phone']) ? $intake['phone'] : '');
$patient_reason = !empty($intake['service_condition']) ? $intake['service_condition'] : (!empty($intake['symptoms']) ? $intake['symptoms'] : 'Telehealth Consultation');

// Doctor details (Primary attending doctor: dynamic lookup for live compatibility)
$doctor_user_id = class_exists('Dior_Doctor_Resolver') ? Dior_Doctor_Resolver::get_default_doctor_id() : 4;
$doctor_id = 1957; // Default CPT ID
$doctor_post = get_post($doctor_id);
if (!$doctor_post || $doctor_post->post_type !== 'wpddb_doctor') {
    $published_docs = get_posts([
        'post_type' => 'wpddb_doctor',
        'post_status' => 'publish',
        'posts_per_page' => 1,
    ]);
    if (!empty($published_docs)) {
        $doctor_post = $published_docs[0];
        $doctor_id = $doctor_post->ID;
    }
}
$doctor_name = $doctor_post ? $doctor_post->post_title : 'Dr. Marcus Sterling, DO';
$doctor_spec = $doctor_post ? (get_post_meta($doctor_id, 'wpddb_doctor_speciality', true) ?: 'General Practice & Internal Medicine') : 'General Practice & Internal Medicine';
$doctor_avatar = get_avatar_url($doctor_user_id, ['size' => 120]) ?: (DIOR_PORTAL_URL . 'assets/images/doctor-placeholder.jpg');

$today_ymd = current_time('Y-m-d');
?>

<!-- ── DIOR MEDICAL CLEAN REGISTRATION-STYLE APPOINTMENT MODAL ── -->
<div id="dior-docbooker-modal" class="dior-db-modal" aria-hidden="true" style="display: none;">
    <div class="dior-db-modal-overlay" onclick="diorCloseDocBookerModal()"></div>
    
    <div class="dior-db-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dior-db-modal-title">
        
        <!-- Modal Header (Clean Registration Header) -->
        <div class="dior-db-modal-header">
            <div class="dior-db-header-left">
                <h3 id="dior-db-modal-title" class="dior-db-title">Schedule Telehealth Appointment</h3>
                <p class="dior-db-subtitle">Complete your booking details below to schedule your consultation.</p>
            </div>
            <button type="button" class="dior-db-close-btn btn" onclick="diorCloseDocBookerModal()" aria-label="Close dialog">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Attending Physician Bar (Compact & Clean) -->
        <div class="dior-db-doctor-banner">
            <div class="dior-db-doc-avatar-wrap">
                <img src="<?php echo esc_url($doctor_avatar); ?>" alt="<?php echo esc_attr($doctor_name); ?>" class="dior-db-doc-img">
                <span class="dior-db-status-dot" title="Available"></span>
            </div>
            <div class="dior-db-doc-details">
                <span class="dior-db-doc-label">Attending Physician</span>
                <h4 class="dior-db-doc-name"><?php echo esc_html($doctor_name); ?></h4>
                <span class="dior-db-doc-spec"><?php echo esc_html($doctor_spec); ?> &bull; Beverly Hills Care</span>
            </div>
        </div>

        <!-- Modal Body / Form -->
        <div class="dior-db-modal-body" id="dior-db-modal-body">
            <?php
            $modal_initial_slots = class_exists('Dior_Medical_Dashboard')
                ? Dior_Medical_Dashboard::get_doctor_slots_for_date($doctor_id, $today_ymd)
                : [];
            $modal_first_slot_time = '';
            foreach ($modal_initial_slots as $st) {
                if (!empty($st['is_available'])) {
                    $modal_first_slot_time = $st['time'];
                    break;
                }
            }
            $modal_default_time = $modal_first_slot_time ?: '09:00 AM - 10:00 AM';
            ?>
            <form id="dior-docbooker-form" class="dior-clean-db-form" onsubmit="diorSubmitDocBookerBooking(event)">
                <input type="hidden" name="action" value="dior_patient_book_appointment">
                <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('dior_portal_nonce')); ?>">
                <input type="hidden" name="doctor_id" id="dior-db-doctor-id" value="<?php echo esc_attr($doctor_id); ?>">
                <input type="hidden" name="doctorId" value="<?php echo esc_attr($doctor_id); ?>">
                <input type="hidden" name="doctor_name" id="dior-db-doctor-name" value="<?php echo esc_attr($doctor_name); ?>">
                <input type="hidden" name="provider" value="<?php echo esc_attr($doctor_name); ?>">
                <input type="hidden" name="selected_time" id="dior-db-selected-time" value="<?php echo esc_attr($modal_default_time); ?>">
                <input type="hidden" name="time" id="dior-db-final-time" value="<?php echo esc_attr($modal_default_time); ?>">
                <input type="hidden" name="service_type" id="dior-db-service-type" value="Video Visit (HD)">
                <input type="hidden" name="type" id="dior-db-final-type" value="Video Visit (HD)">
                <input type="hidden" name="condition" id="dior-db-final-condition" value="<?php echo esc_attr($patient_reason); ?>">

                <!-- 1. CONSULTATION FORMAT (Clean registration style) -->
                <div class="dior-db-section">
                    <div class="dior-reg-section-title">1. Consultation Format</div>
                    <div class="dior-db-service-grid">
                        <!-- Video Option -->
                        <div class="dior-db-service-card active" data-service="Video Visit (HD)" onclick="diorSelectServiceType('Video Visit (HD)', this)">
                            <div class="dior-db-service-left">
                                <div class="dior-db-service-icon video-icon">
                                    <i class="fa-solid fa-video"></i>
                                </div>
                                <div class="dior-db-service-info">
                                    <h4 class="dior-db-service-title">Telehealth Video Visit (HD)</h4>
                                    <p class="dior-db-service-desc">Encrypted video consultation with physician</p>
                                </div>
                            </div>
                            <div class="dior-db-service-radio">
                                <span class="dior-db-radio-dot"></span>
                            </div>
                        </div>

                        <!-- Phone Option -->
                        <div class="dior-db-service-card" data-service="Phone Consultation" onclick="diorSelectServiceType('Phone Consultation', this)">
                            <div class="dior-db-service-left">
                                <div class="dior-db-service-icon phone-icon">
                                    <i class="fa-solid fa-phone"></i>
                                </div>
                                <div class="dior-db-service-info">
                                    <h4 class="dior-db-service-title">Phone Consultation</h4>
                                    <p class="dior-db-service-desc">Direct call to your registered phone number</p>
                                </div>
                            </div>
                            <div class="dior-db-service-radio">
                                <span class="dior-db-radio-dot"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. DATE & TIME SELECTION -->
                <div class="dior-db-section">
                    <div class="dior-reg-section-title">2. Date &amp; Time Selection</div>
                    
                    <div class="dior-form-group">
                        <label for="dior-db-date-input">Consultation Date *</label>
                        <input type="date" id="dior-db-date-input" name="date" class="dior-db-input form-control"
                               min="<?php echo esc_attr($today_ymd); ?>"
                               value="<?php echo esc_attr($today_ymd); ?>"
                               onchange="diorOnDateChanged(this.value)" required>
                    </div>

                    <!-- Available Slots Grid -->
                    <div class="dior-db-slots-wrapper">
                        <div class="dior-db-slots-header">
                            <label class="dior-db-slots-title">Available Time Slots *</label>
                            <span id="dior-db-slots-loading" class="dior-db-slots-spinner" style="display: none;">
                                <i class="fa-solid fa-circle-notch fa-spin"></i> Checking availability...
                            </span>
                        </div>
                        <div id="dior-db-slots-grid" class="dior-db-slots-grid">
                            <?php
                            $modal_has_available = false;
                            $modal_first_selected = false;
                            foreach ($modal_initial_slots as $s_item):
                                if (!empty($s_item['is_available'])):
                                    $modal_has_available = true;
                                    $is_active = !$modal_first_selected;
                                    if ($is_active) {
                                        $modal_first_selected = true;
                                    }
                            ?>
                                <button type="button" class="dior-db-slot-btn <?php echo $is_active ? 'active' : ''; ?> btn" data-time="<?php echo esc_attr($s_item['time']); ?>" onclick="diorSelectTimeSlot('<?php echo esc_js($s_item['time']); ?>', this)">
                                    <?php echo esc_html($s_item['time']); ?>
                                </button>
                            <?php
                                endif;
                            endforeach;
                            if (!$modal_has_available):
                            ?>
                                <div class="w-100 text-center py-3 px-2 text-muted small">
                                    <i class="fa-solid fa-clock me-1 text-warning"></i> No further consultation slots are available for today. Please select a future date to book an appointment.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 3. PATIENT DETAILS (Registration style 2-column fields) -->
                <div class="dior-db-section">
                    <div class="dior-reg-section-title">3. Patient Information</div>

                    <div class="dior-form-row-2">
                        <div class="dior-form-group">
                            <label for="dior-db-patient-name">Full Name *</label>
                            <input type="text" name="name" id="dior-db-patient-name" class="dior-db-input form-control" value="<?php echo esc_attr($patient_name); ?>" placeholder="Your Full Name" required>
                        </div>
                        <div class="dior-form-group">
                            <label for="dior-db-patient-email">Email Address *</label>
                            <input type="email" name="email" id="dior-db-patient-email" class="dior-db-input form-control" value="<?php echo esc_attr($patient_email); ?>" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="dior-form-row-2">
                        <div class="dior-form-group">
                            <label for="dior-db-patient-phone">Phone Number *</label>
                            <input type="tel" name="phone" id="dior-db-patient-phone" class="dior-db-input form-control" value="<?php echo esc_attr($patient_phone); ?>" placeholder="(555) 000-0000" required>
                        </div>
                        <div class="dior-form-group">
                            <label for="dior-db-patient-notes">Reason / Symptoms</label>
                            <input type="text" name="notes" id="dior-db-patient-notes" class="dior-db-input form-control" value="<?php echo esc_attr($patient_reason); ?>" placeholder="e.g. Follow-up consultation">
                        </div>
                    </div>
                </div>

                <!-- Status Feedback Message -->
                <div id="dior-db-status-box" class="dior-db-alert" style="display: none;"></div>

                <!-- Modal Actions (Clean Registration Action Button) -->
                <div class="dior-reg-submit-wrap">
                    <button type="submit" id="dior-db-submit-btn" class="dior-btn-reg-submit btn">
                        <span class="dior-db-btn-text"><i class="fa-solid fa-calendar-check me-2"></i> Confirm &amp; Book Appointment</span>
                        <span class="dior-db-btn-spinner" style="display: none;"><i class="fa-solid fa-circle-notch fa-spin me-2"></i> Scheduling Appointment...</span>
                    </button>
                    <button type="button" class="dior-db-cancel-link btn" onclick="diorCloseDocBookerModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Success Screen (Shown after booking completes) -->
        <div class="dior-db-modal-success" id="dior-db-modal-success" style="display: none;">
            <div class="dior-db-success-icon-wrap">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h3 class="dior-db-success-title">Appointment Confirmed!</h3>
            <p class="dior-db-success-subtitle">Your appointment has been successfully scheduled and synced with your patient portal.</p>

            <div class="dior-db-success-card">
                <div class="dior-db-success-row">
                    <span>Physician:</span>
                    <strong id="dior-success-doc-name"><?php echo esc_html($doctor_name); ?></strong>
                </div>
                <div class="dior-db-success-row">
                    <span>Format:</span>
                    <strong id="dior-success-service-type">Video Visit (HD)</strong>
                </div>
                <div class="dior-db-success-row">
                    <span>Date &amp; Time:</span>
                    <strong id="dior-success-datetime">Oct 14, 2026 at 09:00 AM</strong>
                </div>
                <div class="dior-db-success-row">
                    <span>Confirmation ID:</span>
                    <strong id="dior-success-uid" style="color:#1b4f8a;">APT-0000</strong>
                </div>
                <div class="dior-db-success-row">
                    <span>Status:</span>
                    <span class="dior-db-badge-confirmed">Confirmed</span>
                </div>
            </div>

            <div class="dior-reg-submit-wrap" style="margin-top: 20px;">
                <button type="button" class="dior-btn-reg-submit btn" onclick="diorOnBookingSuccessDone()">
                    <i class="fa-solid fa-arrow-right me-2"></i> View in Appointments Tab
                </button>
            </div>
        </div>

    </div>
</div>
