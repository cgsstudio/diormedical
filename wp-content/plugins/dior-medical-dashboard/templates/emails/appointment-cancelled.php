<?php
/** Appointment cancellation email body. */
if (!defined('ABSPATH')) { exit; }
?>
<p>Dear <strong><?php echo esc_html($patient_name ?? 'Patient'); ?></strong>,</p>
<p>Your appointment with <strong><?php echo esc_html($doctor_name ?? 'your provider'); ?></strong> scheduled for <?php echo esc_html(($appointment_date ?? '') . ' ' . ($appointment_time ?? '')); ?> has been cancelled.</p>
<?php if (!empty($cancellation_reason)) : ?><p><strong>Reason:</strong> <?php echo esc_html($cancellation_reason); ?></p><?php endif; ?>
<p>You can visit your patient dashboard to review or book another appointment.</p>
