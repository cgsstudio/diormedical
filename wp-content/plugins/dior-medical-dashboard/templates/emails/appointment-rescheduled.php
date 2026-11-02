<?php
/** Appointment rescheduled email body. */
if (!defined('ABSPATH')) { exit; }
?>
<p>Dear <strong><?php echo esc_html($patient_name ?? 'Patient'); ?></strong>,</p>
<p>Your appointment with <strong><?php echo esc_html($doctor_name ?? 'your provider'); ?></strong> has been rescheduled.</p>
<p><strong>Previous:</strong> <?php echo esc_html(($old_appointment_date ?? '') . ' ' . ($old_appointment_time ?? '')); ?></p>
<p><strong>New schedule:</strong> <?php echo esc_html(($new_appointment_date ?? '') . ' ' . ($new_appointment_time ?? '')); ?></p>
<p>Reference: <strong><?php echo esc_html($appointment_reference ?? ''); ?></strong></p>
