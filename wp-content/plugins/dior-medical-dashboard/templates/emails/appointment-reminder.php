<?php
/**
 * Dynamic appointment reminder email.
 * Uses the compact DIOR MEDICAL password-reset email visual style.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$patient_name             = isset( $patient_name ) ? (string) $patient_name : 'Patient';
$doctor_name              = isset( $doctor_name ) ? (string) $doctor_name : 'Attending Physician';
$doctor_specialty         = isset( $doctor_specialty ) ? (string) $doctor_specialty : '';
$appointment_date         = isset( $appointment_date ) ? (string) $appointment_date : 'To be confirmed';
$appointment_time         = isset( $appointment_time ) ? (string) $appointment_time : 'To be confirmed';
$appointment_timezone     = isset( $appointment_timezone ) ? (string) $appointment_timezone : wp_timezone_string();
$appointment_type         = isset( $appointment_type ) ? (string) $appointment_type : 'Consultation';
$appointment_reference    = isset( $appointment_reference ) ? (string) $appointment_reference : '';
$appointment_status       = isset( $appointment_status ) ? (string) $appointment_status : 'Scheduled';
$appointment_url          = ! empty( $appointment_url ) ? $appointment_url : home_url( '/patient-dashboard/#tab=appointments' );
$manage_appointment_url   = ! empty( $manage_appointment_url ) ? $manage_appointment_url : home_url( '/patient-dashboard/#tab=appointments' );
$instruction_heading      = isset( $instruction_heading ) ? (string) $instruction_heading : 'Before Your Appointment';
$appointment_instructions = isset( $appointment_instructions ) ? (string) $appointment_instructions : 'Please review your appointment details in your secure patient portal.';
$site_url                 = ! empty( $site_url ) ? $site_url : home_url( '/' );
$site_name                = ! empty( $site_name ) ? $site_name : 'DIOR MEDICAL';
$year                      = wp_date( 'Y' );
$logo_id                   = (int) get_theme_mod( 'custom_logo' );
$logo_url                  = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
$display_timezone          = $appointment_timezone !== '' ? ' (' . $appointment_timezone . ')' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<title><?php echo esc_html( $site_name ); ?> - Appointment Reminder</title>
</head>
<body style="margin:0;padding:0;background:#f5f8fc;font-family:Arial,Helvetica,sans-serif;color:#17233f;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
Reminder: your appointment with <?php echo esc_html( $doctor_name ); ?> is scheduled for <?php echo esc_html( $appointment_date . ' at ' . $appointment_time ); ?>.
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#f5f8fc;">
<tr><td align="center" style="padding:36px 12px;">
<table role="presentation" width="486" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:486px;background:#ffffff;border:1px solid #dce5f0;border-radius:12px;overflow:hidden;">

<tr><td align="center" style="padding:30px 24px 20px;background:#082f63;background-image:linear-gradient(110deg,#0c1438,#073b70);">
<?php if ( $logo_url ) : ?>
<a href="<?php echo esc_url( $site_url ); ?>" style="text-decoration:none;"><img src="<?php echo esc_url( $logo_url ); ?>" width="180" alt="<?php echo esc_attr( $site_name ); ?>" style="display:block;width:100%;max-width:180px;height:auto;border:0;margin:0 auto 10px;"></a>
<?php else : ?>
<p style="margin:0 0 7px;font-size:22px;line-height:1.3;font-weight:bold;letter-spacing:.3px;color:#ffffff;"><?php echo esc_html( $site_name ); ?></p>
<?php endif; ?>
<p style="margin:0;font-size:12px;line-height:1.6;letter-spacing:1px;color:#b9c9df;">PATIENT &amp; PROVIDER PORTAL</p>
</td></tr>

<tr><td style="padding:30px 29px 24px;">
<p style="margin:0 0 22px;font-size:16px;line-height:1.5;font-weight:bold;color:#17233f;">Appointment Reminder</p>
<p style="margin:0 0 18px;font-size:14px;line-height:1.8;color:#536681;">Hello <strong style="color:#263b59;"><?php echo esc_html( $patient_name ); ?></strong>,</p>
<p style="margin:0 0 20px;font-size:14px;line-height:1.8;color:#536681;">This is a friendly reminder about your upcoming appointment with DIOR MEDICAL. Please review the details below.</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 20px;background:#f7faff;border:1px solid #dce7f4;border-radius:7px;">
<tr><td style="padding:14px 15px;border-bottom:1px solid #dce7f4;">
<p style="margin:0 0 5px;font-size:10px;line-height:1.4;letter-spacing:1.2px;font-weight:bold;color:#527095;">APPOINTMENT DATE &amp; TIME</p>
<p style="margin:0;font-size:14px;line-height:1.7;font-weight:bold;color:#17345b;"><?php echo esc_html( $appointment_date ); ?><br><?php echo esc_html( $appointment_time . $display_timezone ); ?></p>
</td></tr>
<tr><td style="padding:13px 15px;border-bottom:1px solid #dce7f4;">
<p style="margin:0 0 5px;font-size:10px;line-height:1.4;letter-spacing:1.2px;font-weight:bold;color:#527095;">PATIENT NAME</p>
<p style="margin:0;font-size:14px;line-height:1.6;color:#263b59;"><?php echo esc_html( $patient_name ); ?></p>
</td></tr>
<tr><td style="padding:13px 15px;border-bottom:1px solid #dce7f4;">
<p style="margin:0 0 5px;font-size:10px;line-height:1.4;letter-spacing:1.2px;font-weight:bold;color:#527095;">DOCTOR NAME</p>
<p style="margin:0;font-size:14px;line-height:1.6;color:#263b59;"><?php echo esc_html( $doctor_name ); ?></p>
<?php if ( $doctor_specialty !== '' ) : ?><p style="margin:3px 0 0;font-size:12px;line-height:1.6;color:#71839d;"><?php echo esc_html( $doctor_specialty ); ?></p><?php endif; ?>
</td></tr>
<tr><td style="padding:13px 15px;border-bottom:1px solid #dce7f4;">
<p style="margin:0 0 5px;font-size:10px;line-height:1.4;letter-spacing:1.2px;font-weight:bold;color:#527095;">APPOINTMENT TYPE</p>
<p style="margin:0;font-size:14px;line-height:1.6;color:#263b59;"><?php echo esc_html( $appointment_type ); ?></p>
</td></tr>
<?php if ( $appointment_reference !== '' ) : ?>
<tr><td style="padding:13px 15px;border-bottom:1px solid #dce7f4;">
<p style="margin:0 0 5px;font-size:10px;line-height:1.4;letter-spacing:1.2px;font-weight:bold;color:#527095;">REFERENCE ID</p>
<p style="margin:0;font-size:14px;line-height:1.6;color:#263b59;word-break:break-word;"><?php echo esc_html( $appointment_reference ); ?></p>
</td></tr>
<?php endif; ?>
<tr><td style="padding:13px 15px;">
<p style="margin:0 0 5px;font-size:10px;line-height:1.4;letter-spacing:1.2px;font-weight:bold;color:#527095;">STATUS</p>
<p style="margin:0;font-size:14px;line-height:1.6;color:#263b59;"><?php echo esc_html( ucwords( str_replace( array( '-', '_' ), ' ', $appointment_status ) ) ); ?></p>
</td></tr>
</table>

<p style="margin:0 0 12px;font-size:14px;line-height:1.6;font-weight:bold;color:#263b59;"><?php echo esc_html( $instruction_heading ); ?></p>
<p style="margin:0 0 20px;font-size:13px;line-height:1.8;color:#536681;"><?php echo esc_html( $appointment_instructions ); ?></p>

<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 18px;"><tr>
<td align="center" bgcolor="#0088c9" style="background:#0088c9;border-radius:7px;">
<a href="<?php echo esc_url( $appointment_url ); ?>" target="_blank" style="display:inline-block;padding:13px 28px;font-size:14px;line-height:1.5;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:7px;">View Appointment</a>
</td></tr></table>

<p style="margin:0 0 8px;font-size:12px;line-height:1.8;color:#71839d;">Need to reschedule or cancel? Use the secure patient portal below.</p>
<p style="margin:0;font-size:12px;line-height:1.8;"><a href="<?php echo esc_url( $manage_appointment_url ); ?>" style="color:#0088c9;text-decoration:underline;word-break:break-word;">Manage Appointment</a></p>
</td></tr>

<tr><td align="center" style="padding:17px 22px;background:#f7f9fc;border-top:1px solid #edf1f6;">
<p style="margin:0;font-size:11px;line-height:1.8;color:#8a9bb3;">&copy; <?php echo esc_html( $year ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved.</p>
<p style="margin:5px 0 0;font-size:11px;line-height:1.8;color:#8a9bb3;"><a href="<?php echo esc_url( $site_url ); ?>" style="color:#0088c9;text-decoration:underline;">Visit Website</a></p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
