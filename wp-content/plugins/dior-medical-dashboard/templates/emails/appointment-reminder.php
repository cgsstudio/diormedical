<?php
/**
 * Dynamic appointment reminder email for the patient.
 *
 * Expected variables supplied by Dior_Notification_Service:
 * $patient_name, $doctor_name, $doctor_specialty, $appointment_date,
 * $appointment_time, $appointment_timezone, $appointment_type,
 * $appointment_reference, $appointment_status, $appointment_url,
 * $manage_appointment_url, $instruction_heading, $appointment_instructions,
 * $site_url, $site_name.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$patient_name             = isset( $patient_name ) ? (string) $patient_name : 'Patient';
$doctor_name              = isset( $doctor_name ) ? (string) $doctor_name : 'Attending Physician';
$doctor_specialty         = isset( $doctor_specialty ) ? (string) $doctor_specialty : '';
$appointment_date         = isset( $appointment_date ) ? (string) $appointment_date : '';
$appointment_time         = isset( $appointment_time ) ? (string) $appointment_time : '';
$appointment_timezone     = isset( $appointment_timezone ) ? (string) $appointment_timezone : wp_timezone_string();
$appointment_type         = isset( $appointment_type ) ? (string) $appointment_type : 'Consultation';
$appointment_reference    = isset( $appointment_reference ) ? (string) $appointment_reference : '';
$appointment_status       = isset( $appointment_status ) ? (string) $appointment_status : 'Scheduled';
$appointment_url          = ! empty( $appointment_url ) ? $appointment_url : home_url( '/patient-dashboard/#tab=appointments' );
$manage_appointment_url   = ! empty( $manage_appointment_url ) ? $manage_appointment_url : home_url( '/patient-dashboard/#tab=appointments' );
$instruction_heading      = isset( $instruction_heading ) ? (string) $instruction_heading : 'Prepare for Your Appointment';
$appointment_instructions = isset( $appointment_instructions ) ? (string) $appointment_instructions : 'Please sign in to your patient portal before your appointment and review the appointment details.';
$site_url                 = ! empty( $site_url ) ? $site_url : home_url( '/' );
$site_name                = ! empty( $site_name ) ? $site_name : ( get_bloginfo( 'name' ) ?: 'DIOR MEDICAL' );
$year                     = wp_date( 'Y' );

$logo_id  = (int) get_theme_mod( 'custom_logo' );
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

$display_date = $appointment_date ?: 'To be confirmed';
$display_time = $appointment_time ?: 'To be confirmed';
$display_type = $appointment_type ?: 'Consultation';
$display_status = $appointment_status
    ? ucwords( str_replace( array( '-', '_' ), ' ', $appointment_status ) )
    : 'Scheduled';

$date_timestamp = $appointment_date ? strtotime( $appointment_date ) : false;
$weekday = $date_timestamp ? wp_date( 'l', $date_timestamp ) : '';
$day = $date_timestamp ? wp_date( 'd', $date_timestamp ) : '--';
$month_year = $date_timestamp ? wp_date( 'F Y', $date_timestamp ) : 'Date to be confirmed';

$normalized_type = strtolower( str_replace( array( '-', '_' ), ' ', $display_type ) );
$is_telehealth = ( strpos( $normalized_type, 'tele' ) !== false )
    || ( strpos( $normalized_type, 'video' ) !== false )
    || ( strpos( $normalized_type, 'virtual' ) !== false )
    || ( strpos( $normalized_type, 'online' ) !== false );

$preheader = sprintf(
    'Reminder: your appointment with %s is scheduled for %s at %s.',
    $doctor_name ?: 'your provider',
    $display_date,
    $display_time
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<title><?php echo esc_html( $site_name ); ?> | Appointment Reminder</title>
</head>
<body style="margin:0;padding:0;background:#f1f6fc;font-family:Arial,Helvetica,sans-serif;color:#102b60;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;color:#f1f6fc;">
<?php echo esc_html( $preheader ); ?>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#f1f6fc;">
<tr><td align="center" style="padding:20px 10px;">
<table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #e0eaf5;">

<!-- HEADER -->
<tr><td style="padding:22px 26px;background-color:#205b99;">
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

<!-- HEADING -->
<tr><td align="center" style="padding:28px 24px 18px;">
<p style="margin:0 0 12px;font-size:12px;letter-spacing:3px;font-weight:bold;color:#1768c0;">APPOINTMENT REMINDER</p>
<h1 style="margin:0 0 14px;font-family:Georgia,'Times New Roman',serif;font-size:32px;line-height:1.25;color:#102b60;">Your Appointment is Coming Up</h1>
<p style="margin:0;font-size:15px;line-height:1.7;color:#536b90;">Dear <strong><?php echo esc_html( $patient_name ); ?></strong>, this is a friendly reminder about your upcoming appointment with <?php echo esc_html( $site_name ); ?>. Please review the details below.</p>
<p style="margin:14px 0 0;font-size:24px;line-height:1;color:#1768c0;">&#9825;</p>
</td></tr>

<!-- APPOINTMENT SUMMARY -->
<tr><td style="padding:0 24px 14px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5faff;border:1px solid #e1edfa;">
<tr>
<td width="32%" valign="top" style="padding:16px 10px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#e4f2ff;">
<tr><td align="center" style="padding:24px 5px;">
<p style="margin:0 0 12px;font-size:12px;letter-spacing:2px;font-weight:bold;color:#1768c0;"><?php echo esc_html( $weekday ?: 'APPOINTMENT' ); ?></p>
<p style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:48px;line-height:1;font-weight:bold;color:#102b60;"><?php echo esc_html( $day ); ?></p>
<p style="margin:10px 0 0;font-size:14px;font-weight:bold;color:#17376c;"><?php echo esc_html( $month_year ); ?></p>
</td></tr></table>
</td>
<td width="68%" valign="top" style="padding:15px 12px 12px 8px;">
<p style="margin:0 0 5px;font-size:10px;letter-spacing:1.4px;font-weight:bold;color:#17376c;">PATIENT NAME</p>
<p style="margin:0 0 13px;font-size:15px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $patient_name ); ?></p>

<p style="margin:0 0 5px;font-size:10px;letter-spacing:1.4px;font-weight:bold;color:#17376c;">DOCTOR NAME</p>
<p style="margin:0 0 3px;font-size:15px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $doctor_name ); ?></p>
<?php if ( $doctor_specialty !== '' ) : ?><p style="margin:0 0 13px;font-size:12px;color:#667d9f;"><?php echo esc_html( $doctor_specialty ); ?></p><?php else : ?><p style="margin:0 0 13px;font-size:12px;color:#667d9f;">&nbsp;</p><?php endif; ?>

<p style="margin:0 0 5px;font-size:10px;letter-spacing:1.4px;font-weight:bold;color:#17376c;">APPOINTMENT TIME</p>
<p style="margin:0 0 13px;font-size:15px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $display_time ); ?><?php if ( $appointment_timezone !== '' ) : ?> (<?php echo esc_html( $appointment_timezone ); ?>)<?php endif; ?></p>

<p style="margin:0 0 5px;font-size:10px;letter-spacing:1.4px;font-weight:bold;color:#17376c;">APPOINTMENT TYPE</p>
<p style="margin:0 0 13px;font-size:13px;line-height:1.6;font-weight:bold;color:#102b60;"><?php echo esc_html( $display_type ); ?></p>

<p style="margin:0 0 5px;font-size:10px;letter-spacing:1.4px;font-weight:bold;color:#17376c;">APPOINTMENT REFERENCE ID</p>
<p style="margin:0 0 13px;font-size:13px;line-height:1.5;font-weight:bold;color:#102b60;word-break:break-word;"><?php echo esc_html( $appointment_reference ?: 'Available in your patient portal' ); ?></p>

<p style="margin:0 0 5px;font-size:10px;letter-spacing:1.4px;font-weight:bold;color:#17376c;">APPOINTMENT STATUS</p>
<p style="margin:0;font-size:13px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $display_status ); ?></p>
</td>
</tr></table>
</td></tr>

<!-- INSTRUCTIONS + CTA -->
<tr><td style="padding:0 24px 14px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eff7ff;border:1px solid #e1edfa;">
<tr><td align="center" style="padding:22px 20px 10px;">
<p style="margin:0 0 10px;font-family:Georgia,'Times New Roman',serif;font-size:21px;line-height:1.4;font-weight:bold;color:#102b60;"><?php echo esc_html( $instruction_heading ); ?></p>
<p style="margin:0;font-size:14px;line-height:1.7;color:#536b90;"><?php echo esc_html( $appointment_instructions ); ?></p>
</td></tr>
<tr><td align="center" style="padding:15px 15px 22px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
<td align="center" bgcolor="#0967c8" style="background:#0967c8;border-radius:6px;">
<a href="<?php echo esc_url( $appointment_url ); ?>" target="_blank" style="display:inline-block;padding:15px 36px;font-size:16px;line-height:1.5;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:6px;"><?php echo $is_telehealth ? 'View Appointment &amp; Join Visit' : 'View Appointment'; ?> &nbsp; &rarr;</a>
</td></tr></table>
</td></tr>

<!-- PREPARATION TIPS -->
<tr><td style="padding:0 12px 15px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;"><tr>
<td width="33%" valign="top" style="padding:12px 8px;border-right:1px solid #dbe8f8;">
<p style="margin:0 0 6px;font-size:13px;font-weight:bold;color:#102b60;">Stable Connection</p>
<p style="margin:0;font-size:12px;line-height:1.6;color:#536b90;">Use a reliable internet connection.</p>
</td>
<td width="33%" valign="top" style="padding:12px 8px;border-right:1px solid #dbe8f8;">
<p style="margin:0 0 6px;font-size:13px;font-weight:bold;color:#102b60;">Quiet Environment</p>
<p style="margin:0;font-size:12px;line-height:1.6;color:#536b90;">Choose a private, quiet space for your visit.</p>
</td>
<td width="34%" valign="top" style="padding:12px 8px;">
<p style="margin:0 0 6px;font-size:13px;font-weight:bold;color:#102b60;">Have Your Information</p>
<p style="margin:0;font-size:12px;line-height:1.6;color:#536b90;">Keep relevant medical information ready.</p>
</td>
</tr></table></td></tr>
</table>
</td></tr>

<!-- MANAGE APPOINTMENT -->
<tr><td style="padding:0 24px 20px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fff9eb;border:1px solid #f5e9c9;">
<tr><td valign="middle" style="padding:17px 16px;">
<p style="margin:0 0 7px;font-family:Georgia,'Times New Roman',serif;font-size:18px;font-weight:bold;color:#102b60;">Need to Make Changes?</p>
<p style="margin:0;font-size:13px;line-height:1.7;color:#536b90;">If you need to reschedule or cancel your appointment, please use your secure patient portal as soon as possible.</p>
</td></tr>
<tr><td style="padding:0 16px 17px;"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
<td align="center" style="border:1px solid #1768c0;border-radius:5px;">
<a href="<?php echo esc_url( $manage_appointment_url ); ?>" target="_blank" style="display:inline-block;padding:11px 20px;font-size:13px;font-weight:bold;color:#075fc1;text-decoration:none;border-radius:5px;">Manage Appointment</a>
</td></tr></table></td></tr>
</table>
</td></tr>

<!-- FOOTER -->
<tr><td align="center" style="padding:20px 24px;background:#edf6ff;border-top:1px solid #e0ebf8;">
<p style="margin:0 0 9px;font-size:12px;line-height:1.7;color:#456b9e;"><strong style="color:#104d9c;"><?php echo esc_html( $site_name ); ?></strong><br>Telehealth Practice &bull; Secure Digital Health Platform</p>
<p style="margin:0 0 13px;font-size:11px;line-height:1.7;color:#647da0;">This is an automated reminder for your upcoming healthcare appointment. Please do not reply directly to this email.<br>For assistance, sign in to your patient dashboard or contact our support team.</p>
<p style="margin:0;font-size:11px;line-height:1.8;color:#647da0;">&copy; <?php echo esc_html( $year ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved. &nbsp;&bull;&nbsp; <a href="<?php echo esc_url( $site_url ); ?>" style="color:#075fc1;text-decoration:underline;">Visit Website</a></p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
