# Dior Medical Email Templates

This directory contains editable email presentation templates.

## Files
- `layout.php` — shared responsive branded layout used by the individual email templates.
- `patient-activation.php` — account activation/welcome email content.
- `email-header.php` and `email-footer.php` — legacy header/footer partials retained for compatibility with any existing mailer references.

The plugin's event-specific templates live in `wp-content/plugins/dior-medical-dashboard/templates/emails/` and use the same shared design. Keep the design consistent by updating the shared `layout.php` in both locations when changing global email styling.

## Template variables
- `$patient_name` — recipient display name.
- `$dashboard_url` — patient dashboard URL.
- `$site_url` — website URL.
- `$site_name` — site/practice name.

WordPress escaping functions are used for dynamic text and URLs. Email markup uses tables and inline styles for email-client compatibility.
