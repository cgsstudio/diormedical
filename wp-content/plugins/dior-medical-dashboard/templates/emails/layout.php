<?php
/**
 * Shared responsive email layout for event-specific Dior Medical email templates.
 *
 * Required variables: $headline, $preheader, $content_html, $button_html,
 * $site_name, $site_url, $year, $email_event_label.
 */
if (!defined('ABSPATH')) { exit; }
?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<title><?php echo esc_html($headline); ?></title>
</head>
<body style="margin:0;padding:0;width:100%;background:#F8FAFC;font-family:Arial,Helvetica,sans-serif;color:#1E293B;-webkit-text-size-adjust:100%;">
<div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;"><?php echo esc_html($preheader); ?></div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#F8FAFC;border-collapse:collapse;">
<tr><td align="center" style="padding:24px 10px;">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#FFFFFF;border:1px solid #E2E8F0;border-collapse:collapse;">
<tr><td style="padding:24px 28px;background:#205B98;color:#FFFFFF;">
<div style="font-size:20px;line-height:27px;font-weight:700;letter-spacing:.3px;"><?php echo esc_html($site_name); ?></div>
<div style="padding-top:4px;font-size:12px;line-height:18px;color:#E0F2FE;">Concierge Telehealth &amp; Clinical Medicine</div>
</td></tr>
<tr><td style="padding:12px 28px;background:#EFF6FF;border-bottom:1px solid #DBEAFE;color:#1D4ED8;font-size:11px;line-height:16px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;"><?php echo esc_html($email_event_label); ?></td></tr>
<tr><td style="padding:20px 28px 10px;border-bottom:1px solid #F1F5F9;"><h1 style="margin:0;font-size:20px;line-height:28px;font-weight:700;color:#1E293B;"><?php echo esc_html($headline); ?></h1></td></tr>
<tr><td style="padding:24px 28px 30px;font-size:14px;line-height:1.65;color:#334155;overflow-wrap:anywhere;"><?php echo wp_kses_post($content_html); ?><?php echo $button_html; ?></td></tr>
<tr><td style="padding:20px 28px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-size:12px;line-height:1.6;color:#64748B;">
<p style="margin:0 0 7px;"><strong><?php echo esc_html($site_name); ?> Telehealth Practice</strong> &bull; Secure Digital Health Platform</p>
<p style="margin:0 0 7px;color:#64748B;">This is an automated notification about your healthcare portal activity. For assistance, sign in to your dashboard or contact the clinic.</p>
<p style="margin:0;font-size:11px;color:#94A3B8;">&copy; <?php echo esc_html($year); ?> <?php echo esc_html($site_name); ?>. All rights reserved. &bull; <a href="<?php echo esc_url($site_url); ?>" style="color:#2C6CB1;text-decoration:none;">Visit Website</a></p>
</td></tr>
</table></td></tr></table>
</body></html>
