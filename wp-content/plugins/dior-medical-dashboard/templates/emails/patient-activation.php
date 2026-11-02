<?php
/** Patient account activation email body. */
if (!defined('ABSPATH')) { exit; }
?>
<p>Dear <strong><?php echo esc_html($patient_name ?? 'Patient'); ?></strong>,</p>
<p>Your Dior Medical patient portal account is ready.</p>
<p>Use the secure link below to access your dashboard and complete your patient profile.</p>
<p><a href="<?php echo esc_url($dashboard_url ?? home_url('/patient-dashboard/')); ?>" style="display:inline-block;padding:12px 22px;background:#007eb1;color:#fff;text-decoration:none;border-radius:5px">Access Patient Dashboard</a></p>
