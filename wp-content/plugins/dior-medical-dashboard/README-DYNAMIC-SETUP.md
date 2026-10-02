# Dior Medical — Dynamic Dashboard Setup

## Data model
The plugin now creates/updates these relational tables:

- `dior_encounters`
- `dior_documents`
- `dior_appointments`
- `dior_audit_logs`
- `dior_appointment_history`
- `dior_notifications`
- `dior_prescriptions`
- `dior_invoices`
- `dior_payments`
- `dior_patients`

WordPress `wp_users` remains the authentication source. `dior_patients` is the normalized patient portal profile/reporting table.

## Demo data
After activating/updating the plugin:

1. Go to **WP Admin → Tools → Dior Demo Data**.
2. Click **Create / Refresh Demo Data**.
3. The seed creates **1 doctor + 8 patients** and linked appointments, encounters, prescriptions, invoices, payments, notifications and appointment history.
4. To remove only the seeded users/records, click **Delete Demo Data**.

### Demo credentials
All seeded accounts use password:

`DiorDemo!2026`

Doctor:

`doctor.demo@dior-medical.test`

Patients:

`patient01@dior-medical.test` through `patient08@dior-medical.test`

## Dynamic behavior included

- Appointment booking writes to the appointment table.
- Appointment create/reschedule/cancel actions write lifecycle history.
- Appointment notifications are persisted in the notification table and remain compatible with existing user-meta notifications.
- Patient profile updates sync into the normalized patient table.
- Prescriptions and payments prefer relational records when available, with legacy user-meta fallback.
- Patient dashboard main-tab navigation has a dedicated stable controller so a legacy widget error cannot block tab switching.
- Settings profile form now submits through the existing secure AJAX endpoint.

## Production note
The seeded records are for development/testing only. Replace demo data with real clinic data only after verifying role permissions, email delivery, payment gateway configuration, secure file storage and the site's privacy/HIPAA operational requirements.
