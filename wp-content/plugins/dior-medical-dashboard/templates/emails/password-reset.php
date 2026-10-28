<?php
/**
 * Responsive Dior Medical password reset email.
 *
 * Expected variables: $user_name, $site_name, $site_url, $reset_url, $logo_url, $year.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$user_name = isset( $user_name ) ? (string) $user_name : '';
$site_name = isset( $site_name ) && $site_name ? (string) $site_name : 'DIOR MEDICAL';
$site_url  = isset( $site_url ) && $site_url ? (string) $site_url : home_url( '/' );
$reset_url = isset( $reset_url ) ? (string) $reset_url : '';
$year      = isset( $year ) ? (string) $year : gmdate( 'Y' );

if ( empty( $logo_url ) ) {
    $custom_logo_id = (int) get_theme_mod( 'custom_logo' );
    $upload_dir     = wp_upload_dir();
    $custom_logo_url = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
    $logo_url       = $custom_logo_url ? $custom_logo_url : trailingslashit( $upload_dir['baseurl'] ) . '2026/08/diormedical-logo.png';
}
?>
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title><?php echo esc_html( $site_name . ' Password Reset' ); ?></title>
<style>
@media screen and (max-width: 620px) {
    .dior-reset-outer { padding: 12px 8px !important; }
    .dior-reset-card { width: 100% !important; }
    .dior-reset-header { padding: 20px 20px !important; }
    .dior-reset-logo { max-width: 155px !important; }
    .dior-reset-content { padding: 26px 22px !important; }
    .dior-reset-title { font-size: 25px !important; line-height: 1.3 !important; }
    .dior-reset-copy { font-size: 15px !important; line-height: 1.65 !important; }
    .dior-reset-cta { display: block !important; text-align: center !important; padding: 15px 18px !important; }
    .dior-reset-footer { padding: 18px 22px !important; }
}
</style>
</head>
<body style="margin:0;padding:0;width:100%;background:#F3F6FA;font-family:Arial,Helvetica,sans-serif;color:#172B4D;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">Use the secure link to reset your Dior Medical account password.</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#F3F6FA;border-collapse:collapse;">
<tr><td class="dior-reset-outer" align="center" style="padding:28px 14px 32px;">
<table role="presentation" class="dior-reset-card" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#FFFFFF;border:1px solid #DCE5EF;border-collapse:collapse;">
<tr><td style="height:7px;background:#2865A5;font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td class="dior-reset-header" style="padding:24px 30px;background:#1765AD;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">
<tr>
<td valign="middle" style="font-family:Arial,Helvetica,sans-serif;">
<?php if ( ! empty( $logo_url ) ) : ?>
<a href="<?php echo esc_url( $site_url ); ?>" style="text-decoration:none;">
<img class="dior-reset-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="190" style="display:block;width:100%;max-width:190px;height:auto;border:0;outline:none;text-decoration:none;">
</a>
<?php else : ?>
<div style="font-size:22px;line-height:29px;font-weight:700;letter-spacing:.4px;color:#FFFFFF;"><?php echo esc_html( $site_name ); ?></div>
<?php endif; ?>
<div style="padding-top:8px;font-size:13px;line-height:19px;color:#FFFFFF;">Concierge Telehealth &amp; Clinical Medicine</div>
</td>
</tr>
</table>
</td></tr>
<tr><td style="padding:13px 30px;background:#EFF6FF;border-bottom:1px solid #DBEAFE;color:#1D4ED8;font-size:11px;line-height:16px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;">Account Security</td></tr>
<tr><td class="dior-reset-content" style="padding:34px 36px 36px;background:#FFFFFF;">
<h1 class="dior-reset-title" style="margin:0 0 22px;font-family:Arial,Helvetica,sans-serif;font-size:30px;line-height:1.25;font-weight:700;color:#102653;">Reset Your Password</h1>
<div style="width:42px;height:3px;background:#2865A5;margin:0 0 24px;font-size:0;line-height:0;">&nbsp;</div>
<div class="dior-reset-copy" style="font-size:16px;line-height:1.7;color:#334155;">
<p style="margin:0 0 18px;">Hello <strong style="color:#12345A;"><?php echo esc_html( $user_name ); ?></strong>,</p>
<p style="margin:0 0 18px;">We received a request to reset the password for your Dior Medical account. Click the button below to choose a new password.</p>
</div>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:26px 0 22px;border-collapse:separate;">
<tr><td align="center" bgcolor="#2865A5" style="background:#2865A5;border-radius:6px;">
<a class="dior-reset-cta" href="<?php echo esc_url( $reset_url ); ?>" target="_blank" style="display:inline-block;padding:15px 28px;color:#FFFFFF;text-decoration:none;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;line-height:1.3;border-radius:6px;">Reset Password &rarr;</a>
</td></tr>
</table>
<p class="dior-reset-copy" style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.65;color:#475569;">If the button doesn't work, copy and paste this link into your browser:</p>
<p style="margin:0 0 24px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;overflow-wrap:anywhere;word-break:break-word;"><a href="<?php echo esc_url( $reset_url ); ?>" style="color:#1765AD;text-decoration:underline;"><?php echo esc_html( $reset_url ); ?></a></p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">
<tr><td style="padding:16px 18px;background:#F8FAFC;border:1px solid #E2E8F0;color:#64748B;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.65;">
<strong style="color:#24496F;">Security notice:</strong> This reset link expires in 24 hours and can only be used once. If you didn't request a password reset, you can safely ignore this email. Your password will not change unless you use the link to set a new one.
</td></tr>
</table>
</td></tr>
<tr><td class="dior-reset-footer" style="padding:22px 30px 24px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.7;color:#64748B;">
<p style="margin:0 0 6px;font-size:13px;line-height:19px;font-weight:700;color:#24496F;"><?php echo esc_html( $site_name ); ?> <span style="font-weight:400;color:#64748B;">· Secure Digital Health Platform</span></p>
<p style="margin:0 0 8px;">This is an automated account-security email. For assistance, sign in to your portal or contact the clinic.</p>
<p style="margin:0;">&copy; <?php echo esc_html( $year ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved. &middot; <a href="<?php echo esc_url( $site_url ); ?>" style="color:#1765AD;text-decoration:underline;">Visit Website</a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
