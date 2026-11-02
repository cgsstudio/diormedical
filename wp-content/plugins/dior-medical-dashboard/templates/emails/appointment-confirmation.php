<?php
/** Appointment confirmation email body. */
if (!defined('ABSPATH')) { exit; }
?>
<p>Dear <strong><?php echo esc_html($patient_name ?? 'Patient'); ?></strong>,</p>
<p>Your appointment has been confirmed.</p>
<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="background:#f0f5fa;border:1px solid #dce6f0;border-radius:8px">
<tr><td>Provider</td><td><strong><?php echo esc_html($doctor_name ?? ''); ?></strong></td></tr>
<tr><td>Date</td><td><strong><?php echo esc_html($appointment_date ?? ''); ?></strong></td></tr>
<tr><td>Time</td><td><strong><?php echo esc_html($appointment_time ?? ''); ?></strong></td></tr>
<tr><td>Reference</td><td><strong><?php echo esc_html($appointment_reference ?? ''); ?></strong></td></tr>
</table>
<p>Sign in to your patient dashboard to review your appointment details.</p>
