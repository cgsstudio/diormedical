<?php
/**
 * Shared header for Dior Medical HTML emails.
 *
 * Expected optional variables: $site_name, $site_url.
 * This partial opens the outer email table and main content container.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_site_name = isset( $site_name ) && $site_name ? $site_name : 'DIOR MEDICAL';
$email_site_url  = isset( $site_url ) && $site_url ? $site_url : home_url( '/' );
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title><?php echo esc_html( $email_site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#253247;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f7fb;width:100%;">
    <tr>
        <td align="center" style="padding:14px 12px 28px;">
            <table role="presentation" width="540" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:540px;background-color:#ffffff;border:1px solid #edf0f4;">
                <tr>
                    <td style="padding:24px 28px;background-color:#2865a5;background-image:linear-gradient(110deg,#205b98,#2e70b7);color:#ffffff;">
                        <div style="font-size:20px;line-height:26px;font-weight:700;letter-spacing:.3px;"><?php echo esc_html( $email_site_name ); ?></div>
                        <div style="padding-top:4px;font-size:11px;line-height:17px;font-weight:600;color:#eef5ff;">Concierge Telehealth &amp; Clinical Medicine</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 28px 14px;border-bottom:1px solid #edf0f4;">
