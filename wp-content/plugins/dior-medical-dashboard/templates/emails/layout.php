<?php
/**
 * Shared branded, responsive HTML email layout for Dior Medical.
 *
 * Required variables: $headline, $preheader, $content_html, $button_html,
 * $site_name, $site_url, $year, $email_event_label.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$headline         = isset( $headline ) ? (string) $headline : 'Dior Medical Notification';
$preheader        = isset( $preheader ) ? (string) $preheader : '';
$content_html     = isset( $content_html ) ? (string) $content_html : '';
$button_html      = isset( $button_html ) ? (string) $button_html : '';
$site_name        = isset( $site_name ) && $site_name ? (string) $site_name : 'DIOR MEDICAL';
$site_url         = isset( $site_url ) && $site_url ? (string) $site_url : home_url( '/' );
$year             = isset( $year ) ? (string) $year : gmdate( 'Y' );
$email_event_label = isset( $email_event_label ) && $email_event_label ? (string) $email_event_label : 'Secure notification';

$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
$logo_url       = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
?>
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title><?php echo esc_html( $headline ); ?></title>
<style>
@media screen and (max-width: 620px) {
    .dior-email-outer { padding: 12px 8px !important; }
    .dior-email-card { width: 100% !important; }
    .dior-email-pad { padding-left: 20px !important; padding-right: 20px !important; }
    .dior-email-title { font-size: 22px !important; line-height: 29px !important; }
    .dior-email-content { font-size: 15px !important; line-height: 1.65 !important; }
    .dior-email-logo { max-width: 150px !important; max-height: 48px !important; }
}
</style>
</head>
<body style="margin:0;padding:0;width:100%;background:#F3F6FA;font-family:Arial,Helvetica,sans-serif;color:#172B4D;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;"><?php echo esc_html( $preheader ); ?></div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#F3F6FA;border-collapse:collapse;">
<tr><td class="dior-email-outer" align="center" style="padding:28px 14px 32px;">
<table role="presentation" class="dior-email-card" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#FFFFFF;border:1px solid #DCE5EF;border-collapse:separate;border-spacing:0;border-radius:10px;overflow:hidden;">
<tr><td style="height:6px;background:#2865A5;font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td class="dior-email-pad" style="padding:24px 30px;background:#FFFFFF;border-bottom:1px solid #E7EDF5;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
<tr>
<td valign="middle" style="font-family:Arial,Helvetica,sans-serif;">
<?php if ( $logo_url ) : ?>
<img class="dior-email-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" style="display:block;width:auto;max-width:180px;max-height:56px;border:0;outline:none;text-decoration:none;">
<?php else : ?>
<div style="font-size:21px;line-height:27px;font-weight:700;letter-spacing:.4px;color:#174A7E;"><?php echo esc_html( $site_name ); ?></div>
<?php endif; ?>
<div style="padding-top:5px;font-size:12px;line-height:18px;color:#64748B;">Telehealth &amp; Clinical Care</div>
</td>
<td align="right" valign="middle" style="font-size:11px;line-height:16px;color:#2865A5;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">Secure Email</td>
</tr>
</table>
</td></tr>
<tr><td class="dior-email-pad" style="padding:15px 30px;background:#EFF6FF;border-bottom:1px solid #DBEAFE;color:#1D4ED8;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:16px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;"><?php echo esc_html( $email_event_label ); ?></td></tr>
<tr><td class="dior-email-pad" style="padding:25px 30px 10px;background:#FFFFFF;">
<h1 class="dior-email-title" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:32px;font-weight:700;color:#12345A;"><?php echo esc_html( $headline ); ?></h1>
<div style="margin-top:13px;width:42px;height:3px;background:#2865A5;border-radius:2px;font-size:0;line-height:0;">&nbsp;</div>
</td></tr>
<tr><td class="dior-email-pad dior-email-content" style="padding:17px 30px 30px;background:#FFFFFF;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.7;color:#334155;overflow-wrap:anywhere;word-break:normal;">
<?php echo wp_kses_post( $content_html ); ?>
<?php echo $button_html; ?>
</td></tr>
<tr><td class="dior-email-pad" style="padding:21px 30px 24px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.65;color:#64748B;">
<p style="margin:0 0 7px;font-size:13px;line-height:19px;font-weight:700;color:#24496F;"><?php echo esc_html( $site_name ); ?> <span style="font-weight:400;color:#64748B;">· Secure Digital Health Platform</span></p>
<p style="margin:0 0 8px;">This is an automated notification about your healthcare portal activity. For assistance, sign in to your dashboard or contact the clinic.</p>
<p style="margin:0;font-size:11px;line-height:17px;color:#94A3B8;">&copy; <?php echo esc_html( $year ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved. &nbsp;·&nbsp; <a href="<?php echo esc_url( $site_url ); ?>" style="color:#2865A5;text-decoration:none;font-weight:600;">Visit Website</a></p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
