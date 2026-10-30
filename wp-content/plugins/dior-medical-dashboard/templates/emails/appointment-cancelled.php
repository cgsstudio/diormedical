<?php
/**
 * Dynamic appointment-cancelled email for the patient.
 *
 * Expected variables:
 * $patient_name, $doctor_name, $doctor_specialty, $appointment_date,
 * $appointment_time, $appointment_timezone, $appointment_type,
 * $appointment_reference, $appointment_status, $cancellation_reason,
 * $book_appointment_url, $site_url, $site_name.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$patient_name         = isset( $patient_name ) ? (string) $patient_name : 'Patient';
$doctor_name          = isset( $doctor_name ) ? (string) $doctor_name : 'Attending Physician';
$doctor_specialty     = isset( $doctor_specialty ) ? (string) $doctor_specialty : '';
$appointment_date     = isset( $appointment_date ) ? (string) $appointment_date : '';
$appointment_time     = isset( $appointment_time ) ? (string) $appointment_time : '';
$appointment_timezone = isset( $appointment_timezone ) ? (string) $appointment_timezone : '';
$appointment_type     = isset( $appointment_type ) ? (string) $appointment_type : 'Consultation';
$appointment_reference = isset( $appointment_reference ) ? (string) $appointment_reference : '';
$appointment_status  = isset( $appointment_status ) ? (string) $appointment_status : 'Cancelled';
$cancellation_reason = isset( $cancellation_reason ) ? (string) $cancellation_reason : '';
$book_appointment_url = isset( $book_appointment_url ) ? (string) $book_appointment_url : home_url( '/patient-dashboard/#tab=appointments' );
$site_url             = isset( $site_url ) ? (string) $site_url : home_url( '/' );
$site_name            = isset( $site_name ) ? (string) $site_name : 'DIOR MEDICAL';
$year                 = wp_date( 'Y' );

$logo_id  = (int) get_theme_mod( 'custom_logo' );
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<title><?php echo esc_html__( 'Appointment Cancelled | DIOR MEDICAL', 'dior-medical-dashboard' ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f1f6fc;font-family:Arial,Helvetica,sans-serif;color:#102b60;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">Your DIOR MEDICAL appointment has been cancelled. Review your appointment details and book another appointment.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#f1f6fc;">
<tr><td align="center" style="padding:20px 10px;">
<table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #e0eaf5;">
<tr><td style="padding:22px 26px;background-color:#205b99;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td width="43%" valign="middle" style="padding-right:16px;">
<a href="<?php echo esc_url( $site_url ); ?>" style="text-decoration:none;">
<?php if ( $logo_url ) : ?>
<img src="<?php echo esc_url( $logo_url ); ?>" width="220" alt="<?php echo esc_attr( $site_name ); ?>" style="display:block;width:100%;max-width:220px;height:auto;border:0;">
<?php else : ?>
<strong style="font-size:22px;color:#ffffff;"><?php echo esc_html( $site_name ); ?></strong>
<?php endif; ?>
</a>
</td>
<td width="57%" valign="middle" style="border-left:1px solid #7da8d1;padding-left:18px;color:#ffffff;font-size:15px;line-height:1.6;">
<strong style="font-size:16px;"><?php echo esc_html( $site_name ); ?></strong><br>Concierge Telehealth &amp;<br>Clinical Medicine
</td>
</tr></table>
</td></tr>
<tr><td align="center" style="padding:28px 24px 18px;">
<p style="margin:0 0 12px;font-size:12px;letter-spacing:3px;font-weight:bold;color:#1768c0;">APPOINTMENT CANCELLED</p>
<h1 style="margin:0 0 14px;font-family:Georgia,'Times New Roman',serif;font-size:30px;line-height:1.25;color:#102b60;">Your Appointment Has Been Cancelled</h1>
<p style="margin:0;font-size:15px;line-height:1.7;color:#536b90;">Your appointment with <?php echo esc_html( $site_name ); ?> has been cancelled.<br>Please find the details below.</p>
<p style="margin:14px 0 0;font-size:24px;line-height:1;color:#1768c0;">&#9825;</p>
</td></tr>
<tr><td style="padding:0 24px 14px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5faff;border:1px solid #e1edfa;">
<tr><td width="66" align="center" valign="top" style="padding:14px 8px 10px 12px;"><table role="presentation" width="42" height="42" cellpadding="0" cellspacing="0" border="0" style="width:42px;height:42px;background:#dceeff;border-radius:50%;"><tr><td align="center" valign="middle" style="font-size:24px;color:#1768c0;">&#128100;</td></tr></table></td>
<td valign="middle" style="padding:14px 14px 10px 4px;border-bottom:1px solid #dbe8f8;"><p style="margin:0 0 4px;font-size:11px;letter-spacing:1.5px;font-weight:bold;color:#17376c;">PATIENT NAME</p><p style="margin:0;font-size:16px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $patient_name ); ?></p></td></tr>
<tr><td width="66" align="center" valign="top" style="padding:12px 8px 10px 12px;"><table role="presentation" width="42" height="42" cellpadding="0" cellspacing="0" border="0" style="width:42px;height:42px;background:#dceeff;border-radius:50%;"><tr><td align="center" valign="middle" style="font-size:23px;color:#1768c0;">&#9877;</td></tr></table></td>
<td valign="middle" style="padding:12px 14px 10px 4px;border-bottom:1px solid #dbe8f8;"><p style="margin:0 0 4px;font-size:11px;letter-spacing:1.5px;font-weight:bold;color:#17376c;">DOCTOR NAME</p><p style="margin:0;font-size:16px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $doctor_name ); ?></p><?php if ( $doctor_specialty !== '' ) : ?><p style="margin:2px 0 0;font-size:13px;color:#667d9f;"><?php echo esc_html( $doctor_specialty ); ?></p><?php endif; ?></td></tr>
<tr><td width="66" align="center" valign="top" style="padding:12px 8px 10px 12px;"><table role="presentation" width="42" height="42" cellpadding="0" cellspacing="0" border="0" style="width:42px;height:42px;background:#dceeff;border-radius:50%;"><tr><td align="center" valign="middle" style="font-size:22px;color:#1768c0;">&#128197;</td></tr></table></td>
<td valign="middle" style="padding:12px 14px 10px 4px;border-bottom:1px solid #dbe8f8;"><p style="margin:0 0 4px;font-size:11px;letter-spacing:1.5px;font-weight:bold;color:#17376c;">APPOINTMENT DATE &amp; TIME</p><p style="margin:0;font-size:16px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $appointment_date ?: 'Not available' ); ?></p><p style="margin:2px 0 0;font-size:13px;color:#667d9f;"><?php echo esc_html( $appointment_time ?: 'Time not available' ); ?><?php if ( $appointment_timezone !== '' ) : ?> (<?php echo esc_html( $appointment_timezone ); ?>)<?php endif; ?></p></td></tr>
<tr><td width="66" align="center" valign="top" style="padding:12px 8px 10px 12px;"><table role="presentation" width="42" height="42" cellpadding="0" cellspacing="0" border="0" style="width:42px;height:42px;background:#dceeff;border-radius:50%;"><tr><td align="center" valign="middle" style="font-size:21px;color:#1768c0;">&#128249;</td></tr></table></td>
<td valign="middle" style="padding:12px 14px 10px 4px;border-bottom:1px solid #dbe8f8;"><p style="margin:0 0 4px;font-size:11px;letter-spacing:1.5px;font-weight:bold;color:#17376c;">APPOINTMENT TYPE</p><p style="margin:0;font-size:14px;line-height:1.6;font-weight:bold;color:#102b60;"><?php echo esc_html( $appointment_type ); ?></p></td></tr>
<tr><td width="66" align="center" valign="top" style="padding:12px 8px 10px 12px;"><table role="presentation" width="42" height="42" cellpadding="0" cellspacing="0" border="0" style="width:42px;height:42px;background:#dceeff;border-radius:50%;"><tr><td align="center" valign="middle" style="font-size:21px;color:#1768c0;">&#9776;</td></tr></table></td>
<td valign="middle" style="padding:12px 14px 10px 4px;border-bottom:1px solid #dbe8f8;"><p style="margin:0 0 4px;font-size:11px;letter-spacing:1.5px;font-weight:bold;color:#17376c;">APPOINTMENT REFERENCE ID</p><p style="margin:0;font-size:15px;line-height:1.6;font-weight:bold;color:#102b60;"><?php echo esc_html( $appointment_reference ?: 'Not available' ); ?></p></td></tr>
<tr><td width="66" align="center" valign="top" style="padding:12px 8px 14px 12px;"><table role="presentation" width="42" height="42" cellpadding="0" cellspacing="0" border="0" style="width:42px;height:42px;background:#e5edf5;border-radius:50%;"><tr><td align="center" valign="middle" style="font-size:25px;color:#52677e;">&#8722;</td></tr></table></td>
<td valign="middle" style="padding:12px 14px 14px 4px;"><p style="margin:0 0 7px;font-size:11px;letter-spacing:1.5px;font-weight:bold;color:#17376c;">APPOINTMENT STATUS</p><span style="display:inline-block;padding:7px 15px;background:#e3e9ef;border-radius:20px;color:#526170;font-size:13px;font-weight:bold;">&#8722; &nbsp;<?php echo esc_html( $appointment_status ); ?></span></td></tr>
</table>
</td></tr>
<tr><td style="padding:0 24px 14px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eff7ff;border:1px solid #dceafb;border-radius:6px;"><tr>
<td width="62" valign="top" align="center" style="padding:16px 6px 16px 10px;"><table role="presentation" width="42" height="42" cellpadding="0" cellspacing="0" border="0" style="width:42px;height:42px;background:#dceeff;border-radius:50%;"><tr><td align="center" valign="middle" style="font-size:22px;color:#1768c0;">&#128172;</td></tr></table></td>
<td valign="middle" style="padding:16px 14px 16px 4px;"><p style="margin:0 0 6px;font-size:11px;letter-spacing:1.5px;font-weight:bold;color:#17376c;">CANCELLATION REASON</p><p style="margin:0;font-size:14px;line-height:1.7;color:#102b60;"><?php echo esc_html( $cancellation_reason !== '' ? $cancellation_reason : 'No reason was provided.' ); ?></p></td>
</tr></table></td></tr>
<tr><td style="padding:0 24px 14px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eff7ff;border:1px solid #e1edfa;"><tr><td align="center" style="padding:22px 20px 10px;"><h2 style="margin:0 0 10px;font-family:Georgia,'Times New Roman',serif;font-size:22px;line-height:1.3;color:#102b60;">Book Another Appointment</h2><p style="margin:0;font-size:14px;line-height:1.7;color:#536b90;">Need to schedule a new appointment? You can easily book a new appointment from your patient portal at your convenience.</p></td></tr>
<tr><td align="center" style="padding:8px 15px 20px;"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" bgcolor="#0967c8" style="background:#0967c8;border-radius:6px;"><a href="<?php echo esc_url( $book_appointment_url ); ?>" target="_blank" style="display:inline-block;padding:14px 25px;font-size:15px;line-height:1.5;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:6px;">Book Another Appointment &nbsp; &rarr;</a></td></tr></table></td></tr></table></td></tr>
<tr><td style="padding:0 24px 20px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fff9eb;border:1px solid #f5e9c9;"><tr><td valign="top" style="padding:17px 18px;"><p style="margin:0 0 10px;font-family:Georgia,'Times New Roman',serif;font-size:17px;font-weight:bold;color:#102b60;">Important Information</p><ul style="margin:0;padding-left:18px;color:#536b90;font-size:13px;line-height:1.8;"><li>If you believe this cancellation was a mistake, please contact our support team.</li><li>You can book a new appointment at any time through your patient portal.</li><li>For urgent medical needs, please contact your office or seek immediate care.</li></ul></td></tr></table></td></tr>
<tr><td align="center" style="padding:20px 24px;background:#edf6ff;border-top:1px solid #e0ebf8;"><p style="margin:0 0 9px;font-size:12px;line-height:1.7;color:#456b9e;"><strong style="color:#104d9c;"><?php echo esc_html( $site_name ); ?></strong> Telehealth Practice &bull; Secure Digital Health Platform</p><p style="margin:0 0 13px;font-size:11px;line-height:1.7;color:#647da0;">This is an automated notification regarding your healthcare appointment. Please do not reply directly to this email.<br>For assistance, sign in to your patient dashboard or contact our support team.</p><p style="margin:0;font-size:11px;line-height:1.8;color:#647da0;">&copy; <?php echo esc_html( $year ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved. &nbsp;&bull;&nbsp; <a href="<?php echo esc_url( $site_url ); ?>" style="color:#075fc1;text-decoration:underline;">Visit Website</a></p></td></tr>
</table>
</td></tr></table>
</body>
</html>
