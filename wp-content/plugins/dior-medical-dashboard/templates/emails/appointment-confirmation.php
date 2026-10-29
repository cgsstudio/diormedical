<?php
/**
 * Dynamic patient appointment confirmation email.
 *
 * Variables supplied by Dior_Notification_Service:
 * $patient_name, $doctor_name, $doctor_specialty, $appointment_date,
 * $appointment_time, $appointment_timezone, $appointment_type,
 * $appointment_reference, $appointment_status, $dashboard_url,
 * $site_url, $site_name.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$patient_name          = isset( $patient_name ) ? (string) $patient_name : '';
$doctor_name           = isset( $doctor_name ) ? (string) $doctor_name : '';
$doctor_specialty      = isset( $doctor_specialty ) ? (string) $doctor_specialty : '';
$appointment_date      = isset( $appointment_date ) ? (string) $appointment_date : '';
$appointment_time      = isset( $appointment_time ) ? (string) $appointment_time : '';
$appointment_timezone  = isset( $appointment_timezone ) ? (string) $appointment_timezone : '';
$appointment_type      = isset( $appointment_type ) ? (string) $appointment_type : '';
$appointment_reference = isset( $appointment_reference ) ? (string) $appointment_reference : '';
$appointment_status    = isset( $appointment_status ) ? (string) $appointment_status : '';
$dashboard_url         = ! empty( $dashboard_url ) ? $dashboard_url : home_url( '/patient-dashboard/#tab=appointments' );
$site_url              = ! empty( $site_url ) ? $site_url : home_url( '/' );
$site_name              = ! empty( $site_name ) ? $site_name : 'DIOR MEDICAL';

$logo_id  = (int) get_theme_mod( 'custom_logo' );
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
if ( ! $logo_url ) {
    $logo_url = '';
}
$display_status = $appointment_status ? ucwords( str_replace( array( '-', '_' ), ' ', $appointment_status ) ) : 'Confirmed';
$display_type   = $appointment_type ? $appointment_type : 'Consultation';
$display_date   = $appointment_date ? $appointment_date : 'To be confirmed';
$display_time   = $appointment_time ? $appointment_time : 'To be confirmed';
$display_tz     = $appointment_timezone ? $appointment_timezone : wp_timezone_string();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<title><?php echo esc_html( $site_name ); ?> | Appointment Confirmation</title>
</head>
<body style="margin:0;padding:0;background:#f1f6fc;font-family:Arial,Helvetica,sans-serif;color:#102b60;">
<div style="display:none;font-size:1px;color:#f1f6fc;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
    Your appointment with <?php echo esc_html( $doctor_name ?: 'your provider' ); ?> is scheduled for <?php echo esc_html( $display_date . ' at ' . $display_time ); ?>.
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#f1f6fc;">
<tr><td align="center" style="padding:20px 10px;">
<table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #e0eaf5;">
<tr>
<td style="padding:22px 26px;background-color:#14569a;background-image:linear-gradient(110deg,#164f8d,#2477c8);">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td width="50%" valign="middle" style="padding-right:16px;">
<a href="<?php echo esc_url( $site_url ); ?>" style="text-decoration:none;color:#ffffff;">
<?php if ( $logo_url ) : ?>
<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="220" style="display:block;width:100%;max-width:220px;height:auto;border:0;">
<?php else : ?>
<strong style="font-size:22px;line-height:1.4;color:#ffffff;"><?php echo esc_html( $site_name ); ?></strong>
<?php endif; ?>
</a>
</td>
<td width="50%" valign="middle" style="border-left:1px solid #7da8d1;padding-left:18px;color:#ffffff;font-size:14px;line-height:1.6;">
<strong style="font-size:16px;"><?php echo esc_html( $site_name ); ?></strong><br>
Concierge Telehealth &amp;<br>Clinical Medicine
</td>
</tr></table>
</td>
</tr>
<tr><td align="center" style="padding:28px 24px 18px;">
<p style="margin:0 0 12px;font-size:12px;letter-spacing:3px;font-weight:bold;color:#1768c0;">APPOINTMENT CONFIRMATION</p>
<h1 style="margin:0 0 14px;font-family:Georgia,'Times New Roman',serif;font-size:30px;line-height:1.2;color:#102b60;">Your Appointment Details</h1>
<p style="margin:0;font-size:14px;line-height:1.7;color:#536b90;">Dear <strong><?php echo esc_html( $patient_name ?: 'Patient' ); ?></strong>, your appointment information is below. Please keep this email for your reference.</p>
</td></tr>
<tr><td style="padding:0 24px 18px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5faff;border:1px solid #e1edfa;">
<?php
$details = array(
    'PATIENT NAME' => $patient_name ?: 'Patient',
    'DOCTOR NAME' => $doctor_name ?: 'Your provider',
);
if ( $doctor_specialty !== '' ) {
    $details['SPECIALTY'] = $doctor_specialty;
}
$details['APPOINTMENT DATE'] = $display_date;
$details['APPOINTMENT TIME'] = trim( $display_time . ( $display_tz ? ' (' . $display_tz . ')' : '' ) );
$details['APPOINTMENT TYPE'] = $display_type;
if ( $appointment_reference !== '' ) {
    $details['REFERENCE ID'] = $appointment_reference;
}
$details['STATUS'] = $display_status;
$detail_index = 0;
foreach ( $details as $label => $value ) :
    $detail_index++;
    $is_last = $detail_index === count( $details );
?>
<tr><td style="padding:14px 18px;border-bottom:<?php echo $is_last ? '0' : '1px solid #dbe8f8'; ?>;border-bottom-style:solid;border-bottom-color:#dbe8f8;">
<p style="margin:0 0 4px;font-size:10px;letter-spacing:1.4px;font-weight:bold;color:#17376c;"><?php echo esc_html( $label ); ?></p>
<p style="margin:0;font-size:15px;line-height:1.5;font-weight:bold;color:#102b60;"><?php echo esc_html( $value ); ?></p>
</td></tr>
<?php endforeach; ?>
</table>
</td></tr>
<tr><td align="center" style="padding:0 24px 24px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
<td align="center" style="background:#1768c0;border-radius:5px;">
<a href="<?php echo esc_url( $dashboard_url ); ?>" style="display:inline-block;padding:13px 22px;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;">View Appointment in Dashboard &rarr;</a>
</td>
</tr></table>
</td></tr>
<tr><td style="padding:0 24px 20px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fff9eb;border:1px solid #f5e9c9;"><tr>
<td style="padding:16px 18px;">
<p style="margin:0 0 7px;font-family:Georgia,'Times New Roman',serif;font-size:17px;font-weight:bold;color:#102b60;">Before Your Appointment</p>
<p style="margin:0;font-size:12px;line-height:1.8;color:#536b90;">For telehealth visits, please sign in a few minutes early and make sure your internet connection, camera, and microphone are working. If you need to review or manage your appointment, use the secure patient dashboard.</p>
</td></tr></table>
</td></tr>
<tr><td align="center" style="padding:20px 24px;background:#edf6ff;border-top:1px solid #e0ebf8;">
<p style="margin:0 0 10px;font-size:12px;line-height:1.7;color:#456b9e;"><strong style="color:#104d9c;"><?php echo esc_html( $site_name ); ?></strong><br>Telehealth Practice · Secure Digital Health Platform</p>
<p style="margin:0 0 12px;font-size:11px;line-height:1.7;color:#647da0;">This is an automated notification about your appointment. Please do not reply directly to this email. For assistance, sign in to your patient dashboard.</p>
<p style="margin:0;font-size:11px;line-height:1.8;color:#647da0;">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved. &nbsp;·&nbsp; <a href="<?php echo esc_url( $site_url ); ?>" style="color:#075fc1;text-decoration:underline;">Visit Website</a></p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
