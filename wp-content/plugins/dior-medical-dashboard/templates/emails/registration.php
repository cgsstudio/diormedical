<?php
/**
 * Registration / new-patient welcome email.
 * Uses the supplied Dior Medical HTML design reference, with a real site logo URL.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$site_name         = isset( $site_name ) && $site_name ? (string) $site_name : ( get_bloginfo( 'name' ) ?: 'DIOR MEDICAL' );
$site_url          = isset( $site_url ) && $site_url ? $site_url : home_url( '/' );
$headline          = isset( $headline ) && $headline ? (string) $headline : 'Welcome to Dior Medical Telehealth';
$preheader         = isset( $preheader ) ? (string) $preheader : 'Your secure patient portal account is ready.';
$email_event_label = 'Account confirmation';
$year              = isset( $year ) ? (string) $year : gmdate( 'Y' );
$upload_dir        = wp_upload_dir();
$custom_logo_id    = (int) get_theme_mod( 'custom_logo' );
$custom_logo_url   = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
$logo_url          = $custom_logo_url ? $custom_logo_url : trailingslashit( $upload_dir['baseurl'] ) . '2026/08/diormedical-logo.png';

$dashboard_url = ! empty( $button['url'] ) ? $button['url'] : home_url( '/patient-dashboard/' );
$button_label  = ! empty( $button['label'] ) ? $button['label'] : 'Access Patient Dashboard';
?>
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title><?php echo esc_html( $headline ); ?></title>
<style>
@media screen and (max-width: 640px) {
    .dior-welcome-outer { padding: 12px 8px !important; }
    .dior-welcome-card { width: 100% !important; }
    .dior-welcome-header { padding: 18px 18px !important; }
    .dior-welcome-logo-cell { display:block !important; width:100% !important; }
    .dior-welcome-tagline { display:block !important; padding:12px 0 0 !important; text-align:left !important; font-size:17px !important; }
    .dior-welcome-content { padding:25px 22px !important; }
    .dior-welcome-title { font-size:27px !important; line-height:1.25 !important; }
    .dior-welcome-copy { font-size:17px !important; line-height:1.55 !important; }
    .dior-welcome-icon-cell { width:64px !important; }
    .dior-welcome-icon { width:52px !important; height:52px !important; font-size:27px !important; }
    .dior-welcome-feature { font-size:16px !important; line-height:1.45 !important; padding-left:10px !important; }
    .dior-welcome-cta { padding:15px 20px !important; font-size:16px !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#142c59;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;"><?php echo esc_html( $preheader ); ?></div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#f4f8fc;border-collapse:collapse;">
<tr><td class="dior-welcome-outer" align="center" style="padding:25px 12px;">
<table role="presentation" class="dior-welcome-card" width="900" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:900px;background:#ffffff;border:1px solid #dce6f1;border-collapse:collapse;">
<tr><td style="height:8px;background:#1765ad;font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td class="dior-welcome-header" style="background:#1765ad;padding:24px 30px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">
<tr>
<td class="dior-welcome-logo-cell" width="240" valign="middle" style="width:240px;">
<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="210" style="display:block;width:100%;max-width:210px;height:auto;border:0;outline:none;text-decoration:none;">
</td>
<td class="dior-welcome-tagline" valign="middle" style="padding-left:25px;color:#ffffff;font-size:21px;line-height:1.5;">Concierge Telehealth &amp;<br>Clinical Medicine</td>
</tr>
</table>
</td></tr>
<tr><td style="padding:13px 26px;background:#eff6ff;border-bottom:1px solid #dbeafe;color:#1d4ed8;font-size:11px;line-height:16px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;"><?php echo esc_html( $email_event_label ); ?></td></tr>
<tr><td class="dior-welcome-content" style="padding:36px 32px 34px;background:#ffffff;">
<h1 class="dior-welcome-title" style="margin:0 0 25px;font-size:34px;line-height:1.25;color:#102653;font-weight:700;"><?php echo esc_html( $headline ); ?></h1>
<div class="dior-welcome-copy" style="font-size:19px;line-height:1.55;color:#142c59;">
<?php echo wp_kses_post( $content_html ); ?>
</div>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 0;border-collapse:separate;">
<tr><td align="center" bgcolor="#176bb8" style="border-radius:6px;background:#176bb8;">
<a class="dior-welcome-cta" href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" style="display:inline-block;padding:17px 28px;color:#ffffff;text-decoration:none;font-size:18px;font-weight:bold;line-height:1.3;border-radius:6px;"><?php echo esc_html( $button_label ); ?> &rarr;</a>
</td></tr>
</table>
</td></tr>
<tr><td style="border-top:1px solid #cfdeed;padding:22px 26px;background:#f8fafc;color:#6683b3;font-size:12px;line-height:1.7;">
<div style="font-size:13px;line-height:19px;color:#24496f;font-weight:700;"><?php echo esc_html( $site_name ); ?> Telehealth Practice <span style="font-weight:400;color:#6683b3;">· Secure Digital Health Platform</span></div>
<div style="padding-top:6px;">This is an automated notification about your healthcare portal activity. For assistance, sign in to your dashboard or contact the clinic.</div>
<div style="padding-top:7px;font-size:11px;color:#6683b3;">&copy; <?php echo esc_html( $year ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved. · <a href="<?php echo esc_url( $site_url ); ?>" style="color:#075bb5;text-decoration:underline;">Visit Website</a></div>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
