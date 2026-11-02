<?php
/** Appointment reminder email body. */
if (!defined('ABSPATH')) { exit; }
?>
<p>Dear <strong><?php echo esc_html($patient_name ?? 'Patient'); ?></strong>,</p>
<p>This is a reminder of your upcoming appointment with <strong><?php echo esc_html($doctor_name ?? 'your provider'); ?></strong>.</p>
<p style="padding:16px;background:#fff8e8;border-left:4px solid #d99a28"><strong><?php echo esc_html(($appointment_date ?? '') . ' at ' . ($appointment_time ?? '')); ?></strong></p>
<p>Please sign in a few minutes before your appointment and ensure your device is ready.</p>
