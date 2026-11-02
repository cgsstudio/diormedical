<?php
/**
 * Dedicated Bootstrap mobile layout for Doctor > View Appointments.
 * Uses the same authorized, database-backed $appointments collection as desktop.
 */
if (!defined('ABSPATH')) { exit; }
?>
<div class="va-mobile-card-stack">
    <?php if (!empty($appointments)): ?>
        <?php foreach ($appointments as $a):
            $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
            if ($name === '') { $name = $a['user_display_name'] ?? ('Patient #' . ($a['patient_id'] ?? '')); }
            $uid = '';
            foreach (['appt_uid', 'appointment_uid', 'appointment_id', 'uid', 'id'] as $key) {
                if (isset($a[$key]) && trim((string) $a[$key]) !== '') { $uid = trim((string) $a[$key]); break; }
            }
            $patient_id = (int) ($a['patient_id'] ?? ($a['patient_user_id'] ?? 0));
            $status = sanitize_text_field($a['status'] ?? 'Pending');
            $status_class = $status_badges[$status] ?? 'badge bg-secondary-subtle text-secondary border rounded-pill';
            $date = $a['appt_date'] ?? ($a['date'] ?? '');
            if (!$date && !empty($a['created_at'])) { $date = date('Y-m-d', strtotime($a['created_at'])); }
            $date_label = $date ? date_i18n('M j, Y', strtotime($date)) : 'Date not set';
            $time = sanitize_text_field($a['appt_time'] ?? ($a['time'] ?? 'Not set'));
            $phone = sanitize_text_field($a['phone'] ?? ($patient_id ? get_user_meta($patient_id, 'phone', true) : ''));
            $phone = $phone ?: '—';
            $condition = sanitize_text_field($a['condition_name'] ?? ($a['condition'] ?? 'General Consultation'));
            $visit_type = sanitize_text_field($a['visit_type'] ?? 'Video Visit (HD)');
            $avatar = esc_url($a['avatar_url'] ?? ($patient_id ? get_user_meta($patient_id, 'dior_profile_image', true) : ''));
            if (!$avatar) { $avatar = 'https://ui-avatars.com/api/?name=' . rawurlencode($name) . '&background=00346c&color=ffffff&bold=true'; }
            $patient_uid = sanitize_text_field($a['patient_uid'] ?? ($patient_id ? get_user_meta($patient_id, 'patient_id', true) : ''));
            $join_url = esc_url($a['start_url'] ?? ($a['join_url'] ?? ''));
            $is_video = stripos($visit_type, 'video') !== false || stripos($visit_type, 'telehealth') !== false;
            $is_closed = in_array(strtolower($status), ['completed', 'cancelled', 'no show'], true);
        ?>
        <article class="card va-mobile-appt-card border-0 shadow-sm" data-mobile-appt-uid="<?php echo esc_attr($uid); ?>">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <img src="<?php echo $avatar; ?>" alt="" width="48" height="48" class="rounded-circle object-fit-cover flex-shrink-0">
                        <div class="min-w-0">
                            <h3 class="h6 fw-bold text-dark mb-1 text-truncate"><?php echo esc_html($name); ?></h3>
                            <div class="small text-secondary"><?php echo esc_html($patient_uid ?: ('Patient #' . $patient_id)); ?></div>
                        </div>
                    </div>
                    <span class="<?php echo esc_attr($status_class); ?> flex-shrink-0"><?php echo esc_html($status); ?></span>
                </div>
                <div class="va-mobile-detail-grid mb-3">
                    <div><span>Consultation</span><strong><?php echo esc_html($condition); ?></strong></div>
                    <div><span>Date &amp; time</span><strong><?php echo esc_html($date_label . ' · ' . $time); ?></strong></div>
                    <div><span>Visit type</span><strong><?php echo esc_html($visit_type); ?></strong></div>
                    <div><span>Contact</span><strong><?php echo esc_html($phone); ?></strong></div>
                    <div class="va-mobile-id"><span>Appointment ID</span><strong><?php echo esc_html($uid ?: '—'); ?></strong></div>
                </div>
                <div class="d-flex flex-wrap gap-2 va-mobile-card-actions">
                    <?php if ($is_video && $join_url && !$is_closed): ?>
                        <a href="<?php echo $join_url; ?>" data-dior-meeting-join data-appt-uid="<?php echo esc_attr($uid); ?>"
                           data-meeting-nonce="<?php echo esc_attr(wp_create_nonce('dior_meeting_started')); ?>"
                           data-status-nonce="<?php echo esc_attr($nonce); ?>" target="_blank" rel="noopener noreferrer"
                           class="btn btn-success btn-sm"><i class="fas fa-video me-1"></i>Join Meeting</a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-primary btn-sm va-view-patient-btn" data-patient-id="<?php echo esc_attr($patient_id); ?>">
                        <i class="fas fa-user me-1"></i>Patient
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm va-view-details-btn" data-uid="<?php echo esc_attr($uid); ?>">
                        <i class="fas fa-eye me-1"></i>Details
                    </button>
                    <?php if (!$is_closed): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm va-resched-appt-btn"
                                data-uid="<?php echo esc_attr($uid); ?>" data-appt-uid="<?php echo esc_attr($uid); ?>"
                                data-name="<?php echo esc_attr($name); ?>" data-date="<?php echo esc_attr($date); ?>" data-time="<?php echo esc_attr($time); ?>">
                            <i class="fas fa-calendar-alt me-1"></i>Reschedule
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm va-cancel-appt-btn"
                                data-uid="<?php echo esc_attr($uid); ?>" data-name="<?php echo esc_attr($name); ?>">
                            <i class="fas fa-ban me-1"></i>Cancel
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card border-0 shadow-sm text-center p-4">
            <i class="fas fa-calendar-xmark text-secondary fs-2 mb-2"></i>
            <p class="mb-0 text-secondary">No appointments found.</p>
        </div>
    <?php endif; ?>
</div>
