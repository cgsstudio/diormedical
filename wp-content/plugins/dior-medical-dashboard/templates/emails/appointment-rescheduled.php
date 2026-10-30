<?php
/**
 * Dynamic appointment rescheduled email for the patient.
 *
 * Expected variables:
 * $patient_name, $doctor_name, $doctor_specialty,
 * $old_appointment_date, $old_appointment_time,
 * $new_appointment_date, $new_appointment_time,
 * $appointment_timezone, $appointment_type,
 * $appointment_reference, $appointment_status,
 * $updated_appointment_url, $site_url, $site_name.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$patient_name            = isset( $patient_name ) ? (string) $patient_name : 'Patient';
$doctor_name             = isset( $doctor_name ) ? (string) $doctor_name : 'Attending Physician';
$doctor_specialty        = isset( $doctor_specialty ) ? (string) $doctor_specialty : '';
$old_appointment_date    = isset( $old_appointment_date ) ? (string) $old_appointment_date : '';
$old_appointment_time    = isset( $old_appointment_time ) ? (string) $old_appointment_time : '';
$new_appointment_date    = isset( $new_appointment_date ) ? (string) $new_appointment_date : '';
$new_appointment_time    = isset( $new_appointment_time ) ? (string) $new_appointment_time : '';
$appointment_timezone    = isset( $appointment_timezone ) ? (string) $appointment_timezone : '';
$appointment_type        = isset( $appointment_type ) ? (string) $appointment_type : 'Consultation';
$appointment_reference   = isset( $appointment_reference ) ? (string) $appointment_reference : '';
$appointment_status      = isset( $appointment_status ) ? (string) $appointment_status : 'Rescheduled';
$updated_appointment_url = ! empty( $updated_appointment_url ) ? $updated_appointment_url : home_url( '/patient-dashboard/#tab=appointments' );
$site_url                = ! empty( $site_url ) ? $site_url : home_url( '/' );
$site_name               = ! empty( $site_name ) ? $site_name : 'DIOR MEDICAL';

$logo_id  = (int) get_theme_mod( 'custom_logo' );
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
$display_timezone = $appointment_timezone ? ' (' . $appointment_timezone . ')' : '';
$display_old_date = $old_appointment_date ?: 'Previous date unavailable';
$display_old_time = $old_appointment_time ?: 'Previous time unavailable';
$display_new_date = $new_appointment_date ?: 'To be confirmed';
$display_new_time = $new_appointment_time ?: 'To be confirmed';
$display_status   = ucwords( str_replace( array( '-', '_' ), ' ', $appointment_status ?: 'Rescheduled' ) );
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<title><?php echo esc_html( $site_name ); ?> | Appointment Rescheduled</title>
</head>
<body style="margin:0;padding:0;background:#f1f6fc;font-family:Arial,Helvetica,sans-serif;color:#102b60;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;color:#f1f6fc;">
    Your appointment has been rescheduled. View your updated appointment details.
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#f1f6fc;">
<tr><td align="center" style="padding:20px 10px;">
<table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #e0eaf5;">
<tr><td style="padding:22px 26px;background-color:#205b99;background-image:linear-gradient(110deg,#164f8d,#2477c8);">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td width="43%" valign="middle" style="padding-right:16px;">
<a href="<?php echo esc_url( $site_url ); ?>" style="text-decoration:none;color:#ffffff;">
<?php if ( $logo_url ) : ?>
<img src="<?php echo esc_url( $logo_url ); ?>" width="220" alt="<?php echo esc_attr( $site_name ); ?>" style="display:block;width:100%;max-width:220px;height:auto;border:0;">
<?php else : ?>
<strong style="font-size:22px;line-height:1.4;color:#ffffff;"><?php echo esc_html( $site_name ); ?></strong>
<?php endif; ?>
</a>
</td>
<td width="57%" valign="middle" style="border-left:1px solid #7da8d1;padding-left:18px;color:#ffffff;font-size:15px;line-height:1.6;">
<strong style="font-size:16px;"><?php echo esc_html( $site_name ); ?></strong><br>
Concierge Telehealth &amp;<br>Clinical Medicine
</td>
</tr></table>
</td></tr>
<tr><td align="center" style="padding:28px 24px 18px;">
<p style="margin:0 0 12px;font-size:12px;letter-spacing:3px;font-weight:bold;color:#1768c0;">APPOINTMENT RESCHEDULED</p>
<h1 style="margin:0 0 14px;font-family:Georgia,'Times New Roman',serif;font-size:30px;line-height:1.25;color:#102b60;">Your Appointment Has Been Rescheduled</h1>
<p style="margin:0;font-size:15px;line-height:1.7;color:#536b90;">Dear <strong><?php echo esc_html( $patient_name ); ?></strong>, your appointment has been successfully rescheduled. Please review the updated details below.</p>
<p style="margin:14px 0 0;font-size:24px;line-height:1;color:#1768c0;">&#9825;</p>
</td></tr>
<tr><td style="padding:0 24px 18px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5faff;border:1px solid #e1edfa;"><tr>
<td width="50%" valign="middle" style="padding:16px 12px;">
<p style="margin:0 0 6px;font-size:11px;letter-spacing:1.5px;font-weight:bold;color:#17376c;">PATIENT NAME</p>
<p style="margin:0;font-size:16px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $patient_name ); ?></p>
</td>
<td width="50%" valign="middle" style="padding:16px 12px;border-left:1px solid #d4e3f5;">
<p style="margin:0 0 6px;font-size:11px;letter-spacing:1.5px;font-weight:bold;color:#17376c;">DOCTOR NAME</p>
<p style="margin:0;font-size:16px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $doctor_name ); ?></p>
<?php if ( $doctor_specialty !== '' ) : ?><p style="margin:2px 0 0;font-size:13px;color:#667d9f;"><?php echo esc_html( $doctor_specialty ); ?></p><?php endif; ?>
</td>
</tr></table>
</td></tr>
<tr><td style="padding:0 24px 18px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td width="48%" valign="top" style="padding:16px 12px;background:#f3f8fd;border:1px solid #e1edfa;">
<p style="margin:0 0 16px;font-size:11px;letter-spacing:1.2px;font-weight:bold;color:#17376c;">PREVIOUS APPOINTMENT</p>
<p style="margin:0 0 4px;font-size:12px;color:#647da0;">Date</p>
<p style="margin:0 0 14px;font-size:14px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $display_old_date ); ?></p>
<p style="margin:0 0 4px;font-size:12px;color:#647da0;">Time</p>
<p style="margin:0;font-size:14px;line-height:1.5;font-weight:bold;color:#102b60;"><span style="text-decoration:line-through;"><?php echo esc_html( $display_old_time . $display_timezone ); ?></span></p>
</td>
<td width="4%" style="font-size:0;line-height:0;">&nbsp;</td>
<td width="48%" valign="top" style="padding:16px 12px;background:#effbf5;border:1px solid #d5f0e1;">
<p style="margin:0 0 16px;font-size:11px;letter-spacing:1.2px;font-weight:bold;color:#168346;">NEW APPOINTMENT</p>
<p style="margin:0 0 4px;font-size:12px;color:#647da0;">Date</p>
<p style="margin:0 0 14px;font-size:14px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $display_new_date ); ?></p>
<p style="margin:0 0 4px;font-size:12px;color:#647da0;">Time</p>
<p style="margin:0;font-size:14px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $display_new_time . $display_timezone ); ?></p>
</td>
</tr></table>
</td></tr>
<tr><td style="padding:0 24px 18px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5faff;border:1px solid #e1edfa;">
<tr><td style="padding:13px 16px;border-bottom:1px solid #dbe8f8;">
<p style="margin:0 0 5px;font-size:11px;letter-spacing:1.3px;font-weight:bold;color:#17376c;">APPOINTMENT TYPE</p>
<p style="margin:0;font-size:14px;line-height:1.6;font-weight:bold;color:#102b60;"><?php echo esc_html( $appointment_type ); ?></p>
</td></tr>
<?php if ( $appointment_reference !== '' ) : ?><tr><td style="padding:13px 16px;border-bottom:1px solid #dbe8f8;">
<p style="margin:0 0 5px;font-size:11px;letter-spacing:1.3px;font-weight:bold;color:#17376c;">APPOINTMENT REFERENCE ID</p>
<p style="margin:0;font-size:14px;line-height:1.6;font-weight:bold;color:#102b60;"><?php echo esc_html( $appointment_reference ); ?></p>
</td></tr><?php endif; ?>
<tr><td style="padding:13px 16px;">
<p style="margin:0 0 8px;font-size:11px;letter-spacing:1.3px;font-weight:bold;color:#17376c;">UPDATED STATUS</p>
<span style="display:inline-block;padding:7px 14px;background:#d6f5e5;border-radius:20px;color:#147b46;font-size:13px;font-weight:bold;">&#10003; &nbsp;<?php echo esc_html( $display_status ); ?></span>
</td></tr>
</table>
</td></tr>
<tr><td align="center" style="padding:0 24px 12px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" bgcolor="#0967c8" style="background:#0967c8;border-radius:6px;">
<a href="<?php echo esc_url( $updated_appointment_url ); ?>" target="_blank" style="display:inline-block;padding:15px 28px;font-size:15px;line-height:1.5;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:6px;">View Updated Appointment &rarr;</a>
</td></tr></table>
</td></tr>
<tr><td align="center" style="padding:0 30px 22px;"><p style="margin:0;font-size:13px;line-height:1.7;color:#536b90;">Use the button above to view your updated appointment details or review the appointment in your patient portal.</p></td></tr>
<tr><td style="padding:0 24px 20px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fff9eb;border:1px solid #f5e9c9;"><tr><td style="padding:17px 18px;">
<p style="margin:0 0 10px;font-family:Georgia,'Times New Roman',serif;font-size:17px;font-weight:bold;color:#102b60;">Important Information</p>
<ul style="margin:0;padding-left:18px;color:#536b90;font-size:13px;line-height:1.8;">
<li>Please make a note of your new appointment date and time.</li>
<li>If you need additional changes, use your patient portal.</li>
<li>For assistance, sign in to your patient portal or contact the support team.</li>
</ul>
</td></tr></table>
</td></tr>
<tr><td align="center" style="padding:20px 24px;background:#edf6ff;border-top:1px solid #e0ebf8;">
<p style="margin:0 0 9px;font-size:12px;line-height:1.7;color:#456b9e;"><strong style="color:#104d9c;"><?php echo esc_html( $site_name ); ?></strong><br>Telehealth Practice &bull; Secure Digital Health Platform</p>
<p style="margin:0 0 13px;font-size:11px;line-height:1.7;color:#647da0;">This is an automated notification regarding your healthcare appointment. Please do not reply directly to this email. For assistance, sign in to your patient dashboard.</p>
<p style="margin:0;font-size:11px;line-height:1.8;color:#647da0;">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved. &nbsp;&bull;&nbsp; <a href="<?php echo esc_url( $site_url ); ?>" style="color:#075fc1;text-decoration:underline;">Visit Website</a></p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
