# Dior Medical Email Templates

This directory holds reusable HTML email templates for the Dior Medical WordPress plugin.

## Files
- `email-header.php` — shared branded header and email container opening.
- `email-footer.php` — shared footer and container closing.
- `patient-activation.php` — patient account activation/welcome email.

## Template variables
Templates expect WordPress to be loaded and use these optional variables:
- `$patient_name` — recipient display name.
- `$dashboard_url` — patient dashboard URL.
- `$site_url` — website URL.
- `$site_name` — site/practice name.

All dynamic text is escaped with WordPress escaping functions. URLs are escaped with `esc_url()`.

## Important integration note
These files define the presentation layer only. The existing mail-sending function must load the relevant template and pass the variables before sending the email. Do not replace existing mail hooks or sending logic without checking the plugin's current mailer first. Keep email styles inline for compatibility with common email clients.
