<?php
/**
 * Shared responsive Dior Medical email header.
 * Expected: $email_title, $email_preheader, $site_name.
 */
if (!defined('ABSPATH')) { exit; }
$email_title = isset($email_title) ? $email_title : 'Dior Medical';
$email_preheader = isset($email_preheader) ? $email_preheader : '';
$site_name = isset($site_name) ? $site_name : (get_bloginfo('name') ?: 'Dior Medical');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo esc_html($email_title); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#243247;">
<div style="display:none!important;max-height:0;overflow:hidden;opacity:0"><?php echo esc_html($email_preheader); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb;padding:28px 12px"><tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#fff;border:1px solid #e5eaf1;border-radius:10px;overflow:hidden">
<tr><td style="padding:25px 30px;background:#002f6c;color:#fff"><div style="font-size:20px;font-weight:700"><?php echo esc_html($site_name); ?></div><div style="font-size:12px;margin-top:5px;color:#dceaf7">Secure Patient &amp; Clinical Portal</div></td></tr>
<tr><td style="padding:24px 30px 8px"><h1 style="margin:0;font-size:21px;line-height:1.35;color:#17324f"><?php echo esc_html($email_title); ?></h1></td></tr>
<tr><td style="padding:18px 30px 30px;font-size:14px;line-height:1.65;color:#334155">
