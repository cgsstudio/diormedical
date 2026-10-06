<<<<<<< HEAD
<section class="dior-tab-panel" id="tab-feedback">
    <?php
    global $wpdb;
    $feedback_patient_id = get_current_user_id();
    $reviews_table = $wpdb->prefix . 'dior_reviews';
    $patient_feedback = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, r.comment AS message, u.display_name AS doctor_display_name
         FROM {$reviews_table} r
         LEFT JOIN {$wpdb->users} u ON u.ID = r.doctor_id
         WHERE r.patient_id = %d
         ORDER BY r.created_at DESC
         LIMIT 100",
        $feedback_patient_id
    ), ARRAY_A) ?: [];

    $feedback_doctor_id = 0;
    $feedback_appointments = class_exists('Dior_Appointment_Service')
        ? Dior_Appointment_Service::get_patient_appointments($feedback_patient_id)
        : [];
    foreach ($feedback_appointments as $feedback_appointment) {
        $doctor_user_id = (int) ($feedback_appointment['doctor_user_id'] ?? 0);
        if ($doctor_user_id && get_userdata($doctor_user_id)) {
            $feedback_doctor_id = $doctor_user_id;
            break;
        }
    }
    if (!$feedback_doctor_id) {
        $feedback_doctors = get_users(['role' => 'doctor', 'orderby' => 'display_name', 'order' => 'ASC', 'number' => 1, 'fields' => 'ID']);
        $feedback_doctor_id = !empty($feedback_doctors) ? (int) $feedback_doctors[0] : 0;
    }
    $feedback_doctor = $feedback_doctor_id ? get_userdata($feedback_doctor_id) : false;
    $feedback_doctor_name = $feedback_doctor ? trim($feedback_doctor->first_name . ' ' . $feedback_doctor->last_name) : '';
    if ($feedback_doctor && $feedback_doctor_name === '') $feedback_doctor_name = $feedback_doctor->display_name;
    $feedback_doctor_name = preg_replace('/^(?:dr\.?\s*)+/i', '', $feedback_doctor_name);
    if ($feedback_doctor_name !== '') $feedback_doctor_name = 'Dr. ' . $feedback_doctor_name;
    ?>
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div><h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Feedback &amp; Support</h4></div>
        <ul class="va-breadcrumb-list"><li><a href="#"><i class="fa-solid fa-house"></i></a></li><li><span>/</span></li><li class="active"><span>Feedback &amp; Support</span></li></ul>
    </div>

    <div class="master-table-wrapper"><div class="master-table-container"><div class="master-table-card">
        <div class="master-table-header">
            <div class="header-content">
                <div class="table-title-section"><h2 class="table-title"><i class="fa-solid fa-comments" style="margin-right:8px;"></i>Feedback &amp; Support</h2><div class="title-accent"></div></div>
                <div class="header-actions-group">
                    <div class="search-container"><i class="fa-solid fa-magnifying-glass search-icon"></i><input type="text" placeholder="Search feedback..." class="search-input" oninput="diorFilterStaticTable(this, 'dior-feedback-table')"></div>
                    <div class="action-buttons">
                        <button type="button" class="action-btn action-btn-primary" title="Give Feedback" onclick="diorOpenFeedbackModal()"><i class="fa-solid fa-plus"></i></button>
                        <button type="button" class="action-btn action-btn-success" title="Export"><i class="fa-solid fa-file-arrow-down"></i></button>
                        <button type="button" class="action-btn action-btn-info" title="Refresh" onclick="window.location.reload()"><i class="fa-solid fa-rotate-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-content">
            <table class="va-table" id="dior-feedback-table">
                <thead><tr><th>Subject</th><th>Doctor</th><th>Rating</th><th>Message</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($patient_feedback as $feedback):
                    $feedback_rating = (int) ($feedback['rating'] ?? 0);
                    $feedback_date = !empty($feedback['created_at']) ? wp_date('M j, Y', strtotime($feedback['created_at'])) : '—';
                    $feedback_status = $feedback['status'] ?? 'Pending';
                    $feedback_doctor_name = $feedback['doctor_display_name'] ?? 'Care team';
                    if ($feedback_doctor_name !== 'Care team') {
                        $feedback_doctor_name = preg_replace('/^(?:dr\.?\s*)+/i', '', $feedback_doctor_name);
                        $feedback_doctor_name = 'Dr. ' . $feedback_doctor_name;
                    }
                    $feedback_view_data = esc_attr(wp_json_encode([
                        'subject' => $feedback['subject'] ?? 'Patient Feedback',
                        'doctor' => $feedback_doctor_name,
                        'rating' => $feedback_rating,
                        'message' => $feedback['message'] ?? '',
                        'status' => $feedback_status,
                    ]));
                ?>
                    <tr data-feedback-id="<?php echo (int) ($feedback['id'] ?? 0); ?>">
                        <td><span class="cell-text"><?php echo esc_html($feedback['subject'] ?? 'Patient Feedback'); ?></span></td>
                        <td><span class="cell-text"><?php echo esc_html($feedback_doctor_name); ?></span></td>
                        <td><span class="cell-text"><i class="fa-solid fa-star" style="color:#F59E0B;"></i> <?php echo $feedback_rating; ?>/5</span></td>
                        <td><span class="cell-text"><?php echo esc_html($feedback['message'] ?? ''); ?></span></td>
                        <td><div class="cell-content cell-icon-text"><i class="fa-regular fa-calendar cell-icon"></i><span class="cell-text"><?php echo esc_html($feedback_date); ?></span></div></td>
                        <td><div class="cell-content"><div class="badge-solid <?php echo ($feedback_status === 'Published') ? 'col-green' : 'col-amber'; ?>"><?php echo esc_html($feedback_status); ?></div></div></td>
                        <td>
                            <button type="button" class="dior-feedback-view-action" aria-label="View feedback" title="View feedback" data-feedback="<?php echo $feedback_view_data; ?>" onclick="diorViewPatientFeedback(this)"><i class="fa-regular fa-eye"></i></button>
                            <button type="button" class="dior-feedback-edit-action" aria-label="Edit feedback" title="Edit feedback" data-feedback-id="<?php echo (int) ($feedback['id'] ?? 0); ?>" data-feedback="<?php echo $feedback_view_data; ?>" onclick="diorEditPatientFeedback(this)"><i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="dior-feedback-delete-action" aria-label="Delete feedback" title="Delete feedback" data-feedback-id="<?php echo (int) ($feedback['id'] ?? 0); ?>" onclick="diorDeletePatientFeedback(this)"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($patient_feedback)): ?>
                    <tr><td colspan="7" class="dior-empty-state">You have not submitted feedback yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="master-table-footer"><span class="page-count" id="dior-feedback-total-count"><?php echo count($patient_feedback); ?> total feedback records</span><div class="master-pagination"></div></div>
    </div></div></div>

    <!-- Feedback popup -->
    <div class="dior-static-modal-backdrop" id="dior-feedback-modal" role="dialog" aria-modal="true" aria-labelledby="dior-feedback-modal-title">
        <div class="dior-static-modal dior-feedback-modal">
            <div class="dior-static-modal-header"><h3 id="dior-feedback-modal-title">Give Feedback</h3><button type="button" class="dior-static-modal-close" onclick="diorCloseStaticModal('dior-feedback-modal')"><i class="fa-solid fa-xmark"></i></button></div>
            <form id="dior-feedback-form" onsubmit="return diorSaveFeedback(event)">
                <input type="hidden" name="feedback_id" id="dior-feedback-id" value="">
                <div class="dior-static-modal-body">
                    <p class="dior-feedback-intro">How was your experience today? Your feedback is sent privately to your doctor.</p>
                    <div class="dior-feedback-recipient">
                        <i class="fa-solid fa-user-doctor" aria-hidden="true"></i>
                        <span>Sending to</span>
                        <strong><?php echo esc_html($feedback_doctor_name ?: 'Doctor unavailable'); ?></strong>
                    </div>
                    <?php if (!$feedback_doctor_id): ?>
                        <p class="dior-feedback-help">No doctor is linked to your account yet. Contact support to submit feedback.</p>
                    <?php endif; ?>
                    <label for="dior-feedback-subject">Subject</label>
                    <input name="subject" id="dior-feedback-subject" maxlength="190" required placeholder="What is your feedback about?">
                    <span class="dior-feedback-rating-label">Your rating</span>
                    <div class="dior-feedback-rating-control" role="radiogroup" aria-label="Choose your feedback rating">
                        <input type="radio" id="dior-feedback-rating-1" name="rating" value="1">
                        <label for="dior-feedback-rating-1" title="Poor"><span>&#128542;</span><small>Poor</small></label>
                        <input type="radio" id="dior-feedback-rating-2" name="rating" value="2">
                        <label for="dior-feedback-rating-2" title="Fair"><span>&#128577;</span><small>Fair</small></label>
                        <input type="radio" id="dior-feedback-rating-3" name="rating" value="3">
                        <label for="dior-feedback-rating-3" title="Okay"><span>&#128528;</span><small>Okay</small></label>
                        <input type="radio" id="dior-feedback-rating-4" name="rating" value="4">
                        <label for="dior-feedback-rating-4" title="Good"><span>&#128578;</span><small>Good</small></label>
                        <input type="radio" id="dior-feedback-rating-5" name="rating" value="5" checked required>
                        <label for="dior-feedback-rating-5" title="Excellent"><span>&#128522;</span><small>Great</small></label>
                    </div>
                    <label for="dior-feedback-message">Message</label>
                    <textarea name="message" id="dior-feedback-message" rows="5" maxlength="3000" required placeholder="Tell us about your experience..."></textarea>
                </div>
                <div class="dior-static-modal-footer"><button type="button" onclick="diorCloseStaticModal('dior-feedback-modal')">Cancel</button><button type="submit" class="primary" id="dior-feedback-submit-btn" <?php disabled(!$feedback_doctor_id); ?>><i class="fa-solid fa-paper-plane"></i> Submit Feedback</button></div>
            </form>
        </div>
=======
<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-feedback">
    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                
                <!-- Header -->
                <div class="master-table-header">
                    <div class="header-content dior-ic-6a2286ff56">
                        <div class="table-title-section">
                            <h2 class="table-title">Feedback & Support</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group dior-ic-c047e12f84">
                            <div class="search-container dior-ic-7e521c6e89">
                                <i class="fa-solid fa-magnifying-glass search-icon dior-ic-bc24164497"></i>
                                <input type="text" placeholder="Search records..." class="search-input dior-ic-fc89dcce8b">
                            </div>
                            <div class="action-buttons dior-ic-6c10502845">
                                <button class="action-btn action-btn-primary dior-ic-e654e3916a"><i class="fa-solid fa-plus"></i></button>
                                <button class="action-btn action-btn-success dior-ic-cf7c00244f"><i class="fa-solid fa-file-arrow-down"></i></button>
                                <button class="action-btn action-btn-info dior-ic-85fdbe7fee"><i class="fa-solid fa-rotate-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="table-content dior-ic-cd60c2926b">
                    <table class="dior-ic-80816b495c">
                        <thead>
                            <tr class="dior-ic-2e693db070">
                                <th class="dior-ic-eae16ff263"><input type="checkbox"></th>
                                <th class="dior-ic-da19ee3bab">Ticket ID <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Subject <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Category <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Rating <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Date <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Status <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $patient_feedback = get_user_meta($user_id, 'dior_feedback', true);
                            $patient_feedback = is_array($patient_feedback) ? $patient_feedback : [];
                            if (empty($patient_feedback) && !empty($dior_demo_mode)) {
                                $patient_feedback = [['doctor_name'=>'Dr. Sarah Smith','rating'=>'5/5','comment'=>'Demo feedback for dashboard UI testing.','date'=>current_time('Y-m-d'),'status'=>'Published']];
                            }
                            ?>
                            <?php if (!empty($patient_feedback)): ?>
                                <?php foreach ($patient_feedback as $feedback): ?>
                                    <tr>
                                        <td><span class="cell-text"><?php echo esc_html($feedback['subject'] ?? ($feedback['title'] ?? 'Feedback')); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($feedback['doctor'] ?? 'Attending Physician'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($feedback['rating'] ?? '—'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($feedback['message'] ?? ($feedback['comment'] ?? '')); ?></span></td>
                                        <td><div class="cell-content cell-icon-text"><i class="fa-regular fa-calendar cell-icon"></i><span class="cell-text"><?php echo esc_html($feedback['date'] ?? '—'); ?></span></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="dior-empty-state">No feedback submitted yet.</td></tr>
                            <?php endif; ?>

                    </table>
                </div>

                <!-- Footer -->
                <div class="dior-ic-332750da74">
                    0 selected / 5 total
                </div>
            </div>
        </div>
>>>>>>> fa0e02d91376b068a5cd18ba25d29811366c5101
    </div>
</section>
