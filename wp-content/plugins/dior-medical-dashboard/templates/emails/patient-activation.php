<?php
/**
 * Patient account activation/welcome email.
 *
 * Expected optional variables:
 * $patient_name, $dashboard_url, $site_url, $site_name.
 * Uses a simple, clean email layout without the previous boxed header/footer design.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$patient_name  = isset( $patient_name ) && $patient_name ? $patient_name : 'there';
$dashboard_url = isset( $dashboard_url ) && $dashboard_url ? $dashboard_url : home_url( '/' );
$site_url      = isset( $site_url ) && $site_url ? $site_url : home_url( '/' );
$site_name     = isset( $site_name ) && $site_name ? $site_name : 'DIOR MEDICAL';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title><?php echo esc_html( $site_name ); ?> — Account Confirmation</title>
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#253247;-webkit-text-size-adjust:100%;">
    <div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">Your patient portal account is active.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;background:#ffffff;">
        <tr>
            <td align="center" style="padding:24px 16px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;border-collapse:collapse;">
                    <tr>
                        <td style="padding:0 0 24px;border-bottom:1px solid #e5eaf1;">
                            <div style="font-size:18px;line-height:26px;font-weight:700;color:#205b98;"><?php echo esc_html( $site_name ); ?></div>
                            <div style="padding-top:4px;font-size:12px;line-height:18px;color:#64748b;">Account confirmation</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 0 8px;">
                            <h1 style="margin:0;font-size:22px;line-height:30px;font-weight:700;color:#172b4d;">Welcome to Dior Medical Telehealth</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 0 28px;font-size:14px;line-height:1.7;color:#334155;overflow-wrap:anywhere;">
                            <p style="margin:0 0 14px;">Dear <?php echo esc_html( $patient_name ); ?>,</p>
                            <p style="margin:0 0 14px;">Welcome to <strong><?php echo esc_html( $site_name ); ?></strong>. Your secure patient portal account has been successfully activated.</p>
                            <p style="margin:0 0 10px;">Through your portal, you can:</p>
                            <ul style="margin:0 0 18px;padding-left:22px;">
                                <li style="padding:0 0 7px;">Book on-demand and scheduled telehealth video consultations</li>
                                <li style="padding:0 0 7px;">Manage, reschedule, and review upcoming appointments</li>
                                <li style="padding:0 0 7px;">Access digital e-prescriptions sent directly to your local pharmacy</li>
                                <li style="padding:0 0 7px;">Complete clinical intake questionnaires with certified electronic signature</li>
                            </ul>
                            <p style="margin:0 0 22px;">Please review and complete your personal profile to help ensure eligibility for your next appointment.</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="border-collapse:separate;">
                                <tr>
                                    <td align="center" bgcolor="#2c6db5" style="border-radius:5px;">
                                        <a href="<?php echo esc_url( $dashboard_url ); ?>" style="display:inline-block;padding:12px 20px;font-size:14px;line-height:20px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:5px;">Access Patient Dashboard &rarr;</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 0 0;border-top:1px solid #e5eaf1;font-size:12px;line-height:1.6;color:#64748b;">
                            <p style="margin:0 0 6px;"><strong><?php echo esc_html( $site_name ); ?> Telehealth Practice</strong> · Secure Digital Health Platform</p>
                            <p style="margin:0 0 6px;">This is an automated notification about your healthcare portal account. For assistance, sign in to your dashboard or contact the clinic.</p>
                            <p style="margin:0;font-size:11px;color:#94a3b8;">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved. · <a href="<?php echo esc_url( $site_url ); ?>" style="color:#2c6db5;text-decoration:none;">Visit Website</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
