<?php
/**
 * Patient account activation/welcome email.
 *
 * Expected optional variables:
 * $patient_name, $dashboard_url, $site_url, $site_name.
 * Include this file from the existing mailer after defining the variables.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$patient_name  = isset( $patient_name ) && $patient_name ? $patient_name : 'there';
$dashboard_url = isset( $dashboard_url ) && $dashboard_url ? $dashboard_url : home_url( '/' );
$site_url      = isset( $site_url ) && $site_url ? $site_url : home_url( '/' );
$site_name     = isset( $site_name ) && $site_name ? $site_name : 'DIOR MEDICAL';

include __DIR__ . '/email-header.php';
?>
                        <h1 style="margin:0;font-size:17px;line-height:24px;font-weight:700;color:#253247;">Welcome to Dior Medical Telehealth</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 28px 28px;font-size:13px;line-height:21px;color:#34445d;">
                        <p style="margin:0 0 14px;">Dear <?php echo esc_html( $patient_name ); ?>,</p>
                        <p style="margin:0 0 14px;">Welcome to <strong><?php echo esc_html( $site_name ); ?></strong>. Your secure patient portal account has been successfully activated.</p>
                        <p style="margin:0 0 10px;">Through your portal, you can:</p>
                        <ul style="margin:0 0 14px;padding-left:20px;">
                            <li style="padding:0 0 6px;">Book on-demand and scheduled telehealth video consultations</li>
                            <li style="padding:0 0 6px;">Manage, reschedule, and review upcoming appointments</li>
                            <li style="padding:0 0 6px;">Access digital e-prescriptions sent directly to your local pharmacy</li>
                            <li style="padding:0 0 6px;">Complete clinical intake questionnaires with certified electronic signature</li>
                        </ul>
                        <p style="margin:0 0 24px;">Please take a moment to review and complete your personal profile to help ensure eligibility for your next appointment.</p>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td align="center" bgcolor="#2c6db5" style="border-radius:5px;">
                                    <a href="<?php echo esc_url( $dashboard_url ); ?>" style="display:inline-block;padding:13px 23px;font-size:13px;line-height:18px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:5px;">Access Patient Dashboard &rarr;</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
<?php include __DIR__ . '/email-footer.php'; ?>
