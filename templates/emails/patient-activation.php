<?php
/**
 * Patient account activation email using the shared Dior Medical email design.
 *
 * Expected optional variables: $patient_name, $dashboard_url, $site_url, $site_name.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$patient_name  = isset( $patient_name ) && $patient_name ? (string) $patient_name : 'there';
$dashboard_url = isset( $dashboard_url ) && $dashboard_url ? $dashboard_url : home_url( '/patient-dashboard/' );
$site_url      = isset( $site_url ) && $site_url ? $site_url : home_url( '/' );
$site_name     = isset( $site_name ) && $site_name ? (string) $site_name : ( get_bloginfo( 'name' ) ?: 'DIOR MEDICAL' );

$headline = 'Welcome to Dior Medical Telehealth';
$preheader = 'Your patient portal account is active. Access your secure dashboard.';
$email_event_label = 'Account confirmation';
$year = gmdate( 'Y' );

$content_html = '
<p style="margin:0 0 14px;">Dear <strong>' . esc_html( $patient_name ) . '</strong>,</p>
<p style="margin:0 0 14px;">Welcome to <strong>' . esc_html( $site_name ) . '</strong>. Your secure patient portal account has been successfully activated.</p>
<p style="margin:0 0 10px;font-weight:600;color:#24496F;">Through your portal, you can:</p>
<ul style="margin:0 0 20px;padding-left:22px;color:#334155;">
<li style="padding:0 0 7px;">Book on-demand and scheduled telehealth video consultations</li>
<li style="padding:0 0 7px;">Manage, reschedule, and review upcoming appointments</li>
<li style="padding:0 0 7px;">Access digital e-prescriptions sent directly to your local pharmacy</li>
<li style="padding:0 0 7px;">Complete clinical intake questionnaires with certified electronic signature</li>
</ul>
<p style="margin:0 0 4px;">Please review and complete your personal profile to help ensure eligibility for your next appointment.</p>';

$button_html = '
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:22px 0 4px;border-collapse:separate;">
<tr><td align="center" bgcolor="#2865A5" style="border-radius:6px;background:#2865A5;">
<a href="' . esc_url( $dashboard_url ) . '" style="display:inline-block;padding:13px 22px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:20px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:6px;">Access Patient Dashboard &rarr;</a>
</td></tr>
</table>';

include __DIR__ . '/layout.php';
