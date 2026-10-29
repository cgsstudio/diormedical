<?php
/**
 * Patient registration / account activation email.
 *
 * Expected variables:
 * $patient_name, $dashboard_url (or $patient_dashboard_url), $site_url, $site_name.
 * Included by the existing mailer. Keep registration and mail-sending logic unchanged.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$patient_name = isset( $patient_name ) && $patient_name ? $patient_name : 'there';
$dashboard_url = ! empty( $patient_dashboard_url ) ? $patient_dashboard_url : ( ! empty( $dashboard_url ) ? $dashboard_url : home_url( '/' ) );
$site_url = isset( $site_url ) && $site_url ? $site_url : home_url( '/' );
$site_name = isset( $site_name ) && $site_name ? $site_name : 'DIOR MEDICAL';

$custom_logo_id = get_theme_mod( 'custom_logo' );
$custom_logo = $custom_logo_id ? wp_get_attachment_image_src( $custom_logo_id, 'full' ) : false;
$logo_url = ( is_array( $custom_logo ) && ! empty( $custom_logo[0] ) ) ? $custom_logo[0] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <title>Welcome to <?php echo esc_html( $site_name ); ?> Telehealth</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#344766;">

  <!-- Hidden Preview Text -->
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Your <?php echo esc_html( $site_name ); ?> patient portal account has been successfully activated.
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background-color:#f4f7fb;">
    <tr>
      <td align="center" style="padding:20px 10px;">

        <!-- EMAIL CONTAINER -->
        <table role="presentation" width="540" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:540px;background-color:#ffffff;border:1px solid #e5ebf2;">

          <!-- HEADER -->
          <tr>
            <td style="padding:18px 28px;background-color:#245f9f;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td valign="middle" width="42%" style="padding-right:15px;">
                    <a href="<?php echo esc_url( $site_url ); ?>" style="text-decoration:none;">
                      <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="185" style="display:block;width:100%;max-width:185px;height:auto;border:0;">
                      <?php else : ?>
                        <span style="font-size:20px;font-weight:700;color:#ffffff;"><?php echo esc_html( $site_name ); ?></span>
                      <?php endif; ?>
                    </a>
                  </td>
                  <td valign="middle" width="58%" style="color:#ffffff;font-size:11px;line-height:1.6;">
                    <strong style="font-size:12px;"><?php echo esc_html( $site_name ); ?></strong><br>
                    Concierge Telehealth &amp;<br>Clinical Medicine
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- TITLE BAR -->
          <tr>
            <td style="padding:20px 28px;border-bottom:1px solid #edf0f4;">
              <h1 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:19px;line-height:1.4;font-weight:700;color:#253247;">Welcome to Dior Medical Telehealth</h1>
            </td>
          </tr>

          <!-- EMAIL BODY -->
          <tr>
            <td style="padding:24px 28px 28px;">
              <p style="margin:8px 0 14px;font-size:13px;line-height:1.7;color:#344766;">Dear <?php echo esc_html( $patient_name ); ?>,</p>

              <p style="margin:0 0 14px;font-size:13px;line-height:1.7;color:#344766;">
                Welcome to <strong>DIOR MEDICAL.</strong> Your secure patient portal account has been successfully activated.
              </p>

              <p style="margin:0 0 12px;font-size:13px;line-height:1.7;color:#344766;">Through your portal, you can:</p>

              <!-- FEATURES -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:8px;">
                <tr><td width="22" valign="top" style="padding:3px 0;font-size:13px;color:#456080;">&bull;</td><td style="padding:3px 0;font-size:13px;line-height:1.7;color:#344766;">Book on-demand and scheduled Telehealth video consultations</td></tr>
              </table>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:8px;">
                <tr><td width="22" valign="top" style="padding:3px 0;font-size:13px;color:#456080;">&bull;</td><td style="padding:3px 0;font-size:13px;line-height:1.7;color:#344766;">Manage, reschedule, and review all upcoming appointments</td></tr>
              </table>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:8px;">
                <tr><td width="22" valign="top" style="padding:3px 0;font-size:13px;color:#456080;">&bull;</td><td style="padding:3px 0;font-size:13px;line-height:1.7;color:#344766;">Access digital e-prescriptions sent directly to your local pharmacy</td></tr>
              </table>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:18px;">
                <tr><td width="22" valign="top" style="padding:3px 0;font-size:13px;color:#456080;">&bull;</td><td style="padding:3px 0;font-size:13px;line-height:1.7;color:#344766;">Complete clinical intake questionnaires with certified electronic signature</td></tr>
              </table>

              <p style="margin:0 0 24px;font-size:13px;line-height:1.7;color:#344766;">
                Please take a moment to review and complete your personal profile to ensure eligibility for your next appointment.
              </p>

              <!-- DASHBOARD BUTTON -->
              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td align="center" bgcolor="#2d70b9" style="border-radius:5px;">
                    <a href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" style="display:inline-block;padding:14px 24px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;font-weight:700;color:#ffffff;text-decoration:none;border-radius:5px;">Access Patient Dashboard &rarr;</a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- FOOTER -->
          <tr>
            <td style="padding:18px 28px 16px;background-color:#f7f9fc;border-top:1px solid #e5ebf2;">
              <p style="margin:0 0 6px;font-size:10px;line-height:1.7;color:#627795;">
                <strong>DIOR MEDICAL Telehealth Practice</strong> &bull; Secure Digital Health Platform
              </p>
              <p style="margin:0 0 7px;font-size:10px;line-height:1.7;color:#8a9db8;">
                This is an automated notification regarding your healthcare portal activity. Please do not reply directly to this automated email. For assistance, sign in to your dashboard.
              </p>
              <p style="margin:0;font-size:10px;line-height:1.7;color:#8a9db8;">
                &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved.
                &bull; <a href="<?php echo esc_url( $site_url ); ?>" style="color:#1769b0;text-decoration:none;">Visit Website</a>
              </p>
            </td>
          </tr>
        </table>
        <!-- END EMAIL CONTAINER -->
      </td>
    </tr>
  </table>
</body>
</html>
