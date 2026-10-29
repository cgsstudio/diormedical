<?php
/**
 * Dior Medical - Multi-Channel Notification Engine
 * 
 * Handles Dashboard In-App Notifications and Luxury HTML Email Notifications
 * for Patients and Providers across all key clinic lifecycle events.
 * 
 * Includes:
 * 1. Registration Confirmation
 * 2. Appointment Confirmation
 * 3. Appointment Changes (Rescheduling)
 * 4. Cancellation Notifications
 * 5. Payment Confirmation
 * 6. Appointment Reminders (to both Doctor & Patient)
 * 7. Consultation Reminders (Live video room readiness)
 * 
 * Fail-Safe: Never blocks or crashes execution if email server (e.g. XAMPP Localhost) fails.
 * Anti-Spam: RFC-compliant headers, clean inline HTML, legitimate clinic footer.
 * 
 * @package Dior Medical
 * @version 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dior_Notification_Service
{
    /**
     * Dispatch an in-app dashboard notification
     * 
     * @param int    $user_id
     * @param string $title
     * @param string $message
     * @param string $action_url
     * @param string $icon
     * @param bool   $is_doctor
     */
    public static function add_dashboard_notification($user_id, $title, $message, $action_url = '', $icon = 'fa-bell', $is_doctor = false)
    {
        $user_id = (int)$user_id;
        if (!$user_id) {
            return false;
        }

        $meta_key = $is_doctor ? 'dior_doctor_notifications' : 'dior_notifications';
        $notifs = get_user_meta($user_id, $meta_key, true);
        if (!is_array($notifs)) {
            $notifs = [];
        }

        $notif_id = 'notif_' . time() . '_' . rand(100, 999);
        $new_notif = [
            'id'         => $notif_id,
            'title'      => sanitize_text_field($title),
            'message'    => sanitize_text_field($message),
            'time'       => 'Just now',
            'timestamp'  => time(),
            'created_at' => current_time('mysql'),
            'is_read'    => false,
            'action_url' => $action_url ?: ($is_doctor ? '#tab=doc-appointments' : '#tab=appointments'),
            'icon'       => $icon
        ];

        array_unshift($notifs, $new_notif);
        // Keep latest 50 notifications
        if (count($notifs) > 50) {
            $notifs = array_slice($notifs, 0, 50);
        }

        update_user_meta($user_id, $meta_key, $notifs);

        // Persist the same event in the relational notification store.
        global $wpdb;
        $table = $wpdb->prefix . 'dior_notifications';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $uid = $notif_id;
            $wpdb->insert($table, [
                'notification_uid' => $uid,
                'user_id' => $user_id,
                'patient_id' => $is_doctor ? null : $user_id,
                'doctor_id' => $is_doctor ? $user_id : null,
                'type' => (stripos($title, 'appointment') !== false || stripos($title, 'reminder') !== false || stripos($action_url, 'appointments') !== false) ? 'appointment' : ($is_doctor ? 'system' : 'patient'),
                'title' => sanitize_text_field($title),
                'message' => sanitize_textarea_field($message),
                'action_url' => esc_url_raw($new_notif['action_url']),
                'icon' => sanitize_text_field($icon),
                'is_read' => 0,
                'created_at' => current_time('mysql'),
            ]);
        }
        return true;
    }

    /**
     * Send email with fail-safe error handling and anti-spam best practices.
     * Guaranteed to never halt or break script execution on localhost or live.
     * 
     * @param string $to
     * @param string $subject
     * @param string $html_body
     * @return bool
     */
    public static function send_email_safe($to, $subject, $html_body)
    {
        $to = sanitize_email($to);
        if (empty($to) || !is_email($to)) {
            return false;
        }

        try {
            $site_name = get_bloginfo('name') ?: 'Dior Medical';
            $domain = parse_url(home_url(), PHP_URL_HOST);
            if (empty($domain) || $domain === 'localhost' || $domain === '127.0.0.1') {
                $domain = 'diormedical.com';
            }

            $from_email = 'concierge@' . $domain;
            $admin_email = get_bloginfo('admin_email');
            if (!is_email($admin_email)) {
                $admin_email = $from_email;
            }

            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . esc_html($site_name) . ' <' . $from_email . '>',
                'Reply-To: ' . esc_html($site_name) . ' Support <' . $admin_email . '>',
                'X-Mailer: DiorMedical Telehealth System v3.0',
                'Auto-Submitted: auto-generated',
                'Precedence: bulk'
            ];

            // Wrap in try-catch and suppress warnings so localhost lacking mail/sendmail doesn't crash
            $result = @wp_mail($to, $subject, $html_body, $headers);
            return (bool)$result;
        } catch (\Throwable $e) {
            // Silently log and continue without breaking the application
            error_log('Dior_Notification_Service::send_email_safe exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Render luxury responsive HTML email layout with anti-spam footer
     * 
     * @param string      $headline
     * @param string      $preheader
     * @param string      $content_html
     * @param array|null  $button ['label' => '...', 'url' => '...']
     * @return string
     */
    public static function render_email_template($headline, $preheader, $content_html, $button = null)
    {
        $site_name = get_bloginfo('name') ?: 'Dior Medical';
        $site_url  = home_url();
        $year      = date('Y');

        $button_html = '';
        if (!empty($button) && !empty($button['url']) && !empty($button['label'])) {
            $button_html = '
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 28px 0 10px 0;">
                <tr>
                    <td align="left" style="border-radius: 6px; background-color: #2C6CB1;">
                        <a href="' . esc_url($button['url']) . '" target="_blank" style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 600; color: #FFFFFF; text-decoration: none; display: inline-block; padding: 13px 26px; border-radius: 6px; background-color: #2C6CB1; border: 1px solid #2C6CB1;">
                            ' . esc_html($button['label']) . ' &rarr;
                        </a>
                    </td>
                </tr>
            </table>';
        }

        return '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>' . esc_html($headline) . '</title>
<style type="text/css">
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
    table { border-collapse: collapse !important; }
    body { margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #F8FAFC; }
</style>
</head>
<body style="margin: 0; padding: 0; background-color: #F8FAFC; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; color: #1E293B;">
<div style="display: none; font-size: 1px; color: #F8FAFC; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
    ' . esc_html($preheader) . '
</div>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F8FAFC; padding: 30px 10px;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; background-color: #FFFFFF; border-radius: 8px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <!-- Header -->
                <tr>
                    <td style="padding: 24px 32px; background: linear-gradient(135deg, #1A528E 0%, #2C6CB1 100%); text-align: left;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td>
                                    <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #FFFFFF; letter-spacing: 0.5px;">
                                        ' . esc_html($site_name) . '
                                    </h1>
                                    <span style="font-size: 12px; color: #E0F2FE; font-weight: 400; letter-spacing: 0.3px;">
                                        Concierge Telehealth &amp; Clinical Medicine
                                    </span>
                                </td>
                                <td align="right">
                                    <span style="display: inline-block; padding: 4px 10px; background: rgba(255,255,255,0.15); border-radius: 12px; font-size: 11px; color: #FFFFFF; font-weight: 600;">
                                        HIPAA Compliant
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <!-- Headline Strip -->
                <tr>
                    <td style="padding: 20px 32px 10px; border-bottom: 1px solid #F1F5F9;">
                        <h2 style="margin: 0; font-size: 19px; font-weight: 700; color: #1E293B;">
                            ' . esc_html($headline) . '
                        </h2>
                    </td>
                </tr>
                <!-- Body Content -->
                <tr>
                    <td style="padding: 24px 32px 30px; font-size: 14.5px; line-height: 1.6; color: #334155;">
                        ' . $content_html . '
                        ' . $button_html . '
                    </td>
                </tr>
                <!-- Clinic Footer & Anti-Spam -->
                <tr>
                    <td style="padding: 20px 32px; background-color: #F8FAFC; border-top: 1px solid #E2E8F0; text-align: left; font-size: 12px; line-height: 1.5; color: #64748B;">
                        <p style="margin: 0 0 6px 0;">
                            <strong>' . esc_html($site_name) . ' Telehealth Practice</strong> &bull; Secure Digital Health Platform
                        </p>
                        <p style="margin: 0 0 8px 0; color: #94A3B8;">
                            This is an automated notification regarding your healthcare portal activity. Please do not reply directly to this automated email. For assistance, sign in to your dashboard.
                        </p>
                        <p style="margin: 0; color: #94A3B8; font-size: 11px;">
                            &copy; ' . esc_html($year) . ' ' . esc_html($site_name) . '. All rights reserved. &bull; <a href="' . esc_url($site_url) . '" style="color: #2C6CB1; text-decoration: none;">Visit Website</a>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>';
    }

    /**
     * 1. Registration Confirmation
     */
    public static function send_registration_confirmation($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) return false;

        $name = trim($user->first_name . ' ' . $user->last_name) ?: $user->display_name;
        $login_url = home_url('/patient-dashboard/');

        // In-App Notification
        self::add_dashboard_notification(
            $user_id,
            'Welcome to Dior Medical!',
            'Your patient portal account has been successfully created. Complete your medical profile to schedule consultations.',
            '#tab=profile',
            'fa-user-check'
        );

        // Luxury Email
        $content = '
        <p>Dear <strong>' . esc_html($name) . '</strong>,</p>
        <p>Welcome to <strong>' . esc_html(get_bloginfo('name') ?: 'Dior Medical') . '</strong>. Your secure patient portal account has been successfully activated.</p>
        <p>Through your portal, you can:</p>
        <ul style="padding-left: 20px; margin: 12px 0; color: #475569;">
            <li style="margin-bottom: 6px;">Book on-demand and scheduled Telehealth video consultations</li>
            <li style="margin-bottom: 6px;">Manage, reschedule, and review all upcoming appointments</li>
            <li style="margin-bottom: 6px;">Access digital e-prescriptions sent directly to your local pharmacy</li>
            <li style="margin-bottom: 6px;">Complete clinical intake questionnaires with certified electronic signature</li>
        </ul>
        <p style="margin-top: 16px;">Please take a moment to review and complete your personal profile to ensure eligibility for your next appointment.</p>';

        $html = self::render_email_template(
            'Welcome to Dior Medical Telehealth',
            'Your account is ready. Access your patient portal today.',
            $content,
            ['label' => 'Access Patient Dashboard', 'url' => $login_url]
        );

        return self::send_email_safe($user->user_email, 'Welcome to Dior Medical — Account Confirmation', $html);
    }

    /**
     * 2. Appointment Confirmation (New Booking)
     */
    public static function send_appointment_confirmation($appt_uid)
    {
        $appt = Dior_Appointment_Service::get_appointment($appt_uid);
        if (!$appt) {
            return false;
        }

        $pid = (int) ($appt['patient_id'] ?? 0);
        $did = (int) ($appt['doctor_id'] ?? 0);
        $patient = $pid ? get_userdata($pid) : false;

        $doctor_uid = !empty($appt['doctor_user_id']) ? (int) $appt['doctor_user_id'] : 0;
        if (!$doctor_uid && $did) {
            $doctor_uid = class_exists('Dior_Doctor_Resolver')
                ? (int) Dior_Doctor_Resolver::resolve_doctor_user_id($did)
                : $did;
        }
        $doctor = $doctor_uid ? get_userdata($doctor_uid) : false;

        $patient_full_name = $patient
            ? trim((string) $patient->first_name . ' ' . (string) $patient->last_name)
            : '';
        $patient_name = $patient_full_name !== '' ? $patient_full_name : ($patient ? $patient->display_name : 'Patient');

        $doctor_full_name = $doctor
            ? trim((string) $doctor->first_name . ' ' . (string) $doctor->last_name)
            : '';
        $doctor_name = !empty($appt['provider'])
            ? sanitize_text_field($appt['provider'])
            : (!empty($appt['doctor_name'])
                ? sanitize_text_field($appt['doctor_name'])
                : ($doctor ? (($doctor_full_name !== '' ? 'Dr. ' . $doctor_full_name : $doctor->display_name)) : 'Attending Physician'));

        $raw_date = !empty($appt['appt_date']) ? $appt['appt_date'] : (!empty($appt['date']) ? $appt['date'] : '');
        $raw_time = !empty($appt['appt_time']) ? $appt['appt_time'] : (!empty($appt['time']) ? $appt['time'] : '');
        $appointment_date = $raw_date;
        if ($raw_date !== '' && strtotime($raw_date) !== false) {
            $appointment_date = wp_date(get_option('date_format') ?: 'F j, Y', strtotime($raw_date));
        }
        $appointment_time = $raw_time;
        if ($raw_time !== '' && strtotime($raw_time) !== false) {
            $appointment_time = wp_date(get_option('time_format') ?: 'g:i a', strtotime($raw_time));
        }

        $appointment_type = !empty($appt['visit_type'])
            ? sanitize_text_field($appt['visit_type'])
            : (!empty($appt['type']) ? sanitize_text_field($appt['type']) : 'Consultation');
        $doctor_specialty = !empty($appt['doctor_specialty'])
            ? sanitize_text_field($appt['doctor_specialty'])
            : (!empty($appt['specialty']) ? sanitize_text_field($appt['specialty']) : '');
        $appointment_reference = !empty($appt['appt_uid']) ? sanitize_text_field($appt['appt_uid']) : (string) $appt_uid;
        $appointment_status = !empty($appt['status']) ? sanitize_text_field($appt['status']) : 'Confirmed';
        $appointment_timezone = wp_timezone_string();
        $site_name = get_bloginfo('name') ?: 'DIOR MEDICAL';
        $site_url = home_url('/');
        $dashboard_url = home_url('/patient-dashboard/#tab=appointments');

        // Patient dashboard notification.
        if ($pid) {
            self::add_dashboard_notification(
                $pid,
                'Appointment Confirmed: ' . $doctor_name,
                'Your appointment with ' . $doctor_name . ' is scheduled for ' . ($appointment_date ?: 'a date to be confirmed') . ($appointment_time ? ' at ' . $appointment_time : '') . '.',
                '#tab=appointments',
                'fa-calendar-check'
            );
        }

        // Render the branded patient email from the dedicated, data-driven template.
        if ($patient && !empty($patient->user_email)) {
            $template_path = defined('DIOR_PORTAL_PATH')
                ? DIOR_PORTAL_PATH . 'templates/emails/appointment-confirmation.php'
                : dirname(__DIR__) . '/templates/emails/appointment-confirmation.php';

            if (is_readable($template_path)) {
                ob_start();
                include $template_path;
                $patient_html = ob_get_clean();

                self::send_email_safe(
                    $patient->user_email,
                    'Appointment Confirmation: ' . ($appointment_date ?: 'Scheduled') . ' with ' . $doctor_name,
                    $patient_html
                );
            } else {
                error_log('Dior Medical: appointment confirmation email template is missing: ' . $template_path);
            }
        }

        // Notify the assigned doctor without exposing unnecessary clinical details in email.
        if ($doctor) {
            self::add_dashboard_notification(
                $doctor->ID,
                'New Appointment Scheduled (' . $patient_name . ')',
                $patient_name . ' has an appointment scheduled for ' . ($appointment_date ?: 'a date to be confirmed') . ($appointment_time ? ' at ' . $appointment_time : '') . '.',
                '#tab=doc-appointments',
                'fa-calendar-plus',
                true
            );

            if (!empty($doctor->user_email)) {
                $doctor_dashboard_url = home_url('/doctor-dashboard/#tab=doc-appointments');
                $doctor_content = '
                    <p>Hello <strong>' . esc_html($doctor_name) . '</strong>,</p>
                    <p>A new appointment has been scheduled with patient <strong>' . esc_html($patient_name) . '</strong>.</p>
                    <div style="background:#F1F5F9;border-radius:8px;padding:18px 20px;margin:20px 0;border:1px solid #E2E8F0;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px;">
                            <tr><td style="padding:6px 0;color:#64748B;width:130px;">Appointment ID:</td><td style="padding:6px 0;font-weight:600;color:#1E293B;">' . esc_html($appointment_reference) . '</td></tr>
                            <tr><td style="padding:6px 0;color:#64748B;">Patient:</td><td style="padding:6px 0;font-weight:600;color:#1E293B;">' . esc_html($patient_name) . '</td></tr>
                            <tr><td style="padding:6px 0;color:#64748B;">Date &amp; Time:</td><td style="padding:6px 0;font-weight:600;color:#1E293B;">' . esc_html(trim($appointment_date . ' ' . $appointment_time . ' ' . $appointment_timezone)) . '</td></tr>
                            <tr><td style="padding:6px 0;color:#64748B;">Appointment Type:</td><td style="padding:6px 0;font-weight:600;color:#1E293B;">' . esc_html($appointment_type) . '</td></tr>
                        </table>
                    </div>
                    <p>Sign in to the doctor dashboard to review the appointment details.</p>';

                $doctor_html = self::render_email_template(
                    'New Appointment Scheduled',
                    'A new appointment has been scheduled for ' . $appointment_date . ' ' . $appointment_time,
                    $doctor_content,
                    ['label' => 'Open Doctor Dashboard', 'url' => $doctor_dashboard_url]
                );
                self::send_email_safe(
                    $doctor->user_email,
                    'New Appointment: ' . $patient_name . ' (' . ($appointment_date ?: 'Scheduled') . ')',
                    $doctor_html
                );
            }
        }

        return true;
    }

    /**
     * 3. Appointment Changes (Rescheduled)
     */
    public static function send_appointment_rescheduled($appt_uid, $old_date, $old_time, $new_date, $new_time, $rescheduled_by = 'patient')
    {
        $appt = Dior_Appointment_Service::get_appointment($appt_uid);
        if (!$appt) return false;

        $pid = (int)$appt['patient_id'];
        $did = (int)$appt['doctor_id'];

        $patient = $pid ? get_userdata($pid) : false;

        $doctor_uid = !empty($appt['doctor_user_id']) ? (int) $appt['doctor_user_id'] : 0;
        if (!$doctor_uid && $did) {
            $doctor_uid = class_exists('Dior_Doctor_Resolver')
                ? (int) Dior_Doctor_Resolver::resolve_doctor_user_id($did)
                : $did;
        }
        $doctor = $doctor_uid ? get_userdata($doctor_uid) : false;

        $pname = $patient ? (trim((string) $patient->first_name . ' ' . (string) $patient->last_name) ?: $patient->display_name) : 'Patient';
        $dname = !empty($appt['provider'])
            ? sanitize_text_field($appt['provider'])
            : (!empty($appt['doctor_name'])
                ? sanitize_text_field($appt['doctor_name'])
                : ($doctor ? 'Dr. ' . (trim((string) $doctor->first_name . ' ' . (string) $doctor->last_name) ?: $doctor->display_name) : 'Attending Physician'));

        // Patient Dashboard Notification
        self::add_dashboard_notification(
            $pid,
            'Appointment Rescheduled',
            'Your consultation with ' . $dname . ' has been rescheduled to ' . $new_date . ' at ' . $new_time . '.',
            '#tab=appointments',
            'fa-calendar-days'
        );

        // Patient Email: render the dedicated dynamic reschedule design.
        if ($patient && $patient->user_email) {
            $format_date = static function ($value) {
                $value = (string) $value;
                $timestamp = $value !== '' ? strtotime($value) : false;
                return $timestamp ? wp_date(get_option('date_format') ?: 'F j, Y', $timestamp) : $value;
            };
            $format_time = static function ($value) {
                $value = (string) $value;
                $timestamp = $value !== '' ? strtotime($value) : false;
                return $timestamp ? wp_date(get_option('time_format') ?: 'g:i a', $timestamp) : $value;
            };

            $patient_full_name = trim((string) $patient->first_name . ' ' . (string) $patient->last_name);
            $patient_name = $patient_full_name !== '' ? $patient_full_name : $patient->display_name;
            $doctor_uid = !empty($appt['doctor_user_id']) ? (int) $appt['doctor_user_id'] : 0;
            if (!$doctor_uid && $did) {
                $doctor_uid = class_exists('Dior_Doctor_Resolver')
                    ? (int) Dior_Doctor_Resolver::resolve_doctor_user_id($did)
                    : $did;
            }
            $doctor_user = $doctor_uid ? get_userdata($doctor_uid) : false;
            $doctor_full_name = $doctor_user
                ? trim((string) $doctor_user->first_name . ' ' . (string) $doctor_user->last_name)
                : '';
            $doctor_name = !empty($appt['provider'])
                ? sanitize_text_field($appt['provider'])
                : (!empty($appt['doctor_name'])
                    ? sanitize_text_field($appt['doctor_name'])
                    : ($doctor_full_name !== '' ? 'Dr. ' . $doctor_full_name : $dname));

            $doctor_specialty = !empty($appt['doctor_specialty'])
                ? sanitize_text_field($appt['doctor_specialty'])
                : (!empty($appt['specialty']) ? sanitize_text_field($appt['specialty']) : '');
            $appointment_type = !empty($appt['visit_type'])
                ? sanitize_text_field($appt['visit_type'])
                : (!empty($appt['type']) ? sanitize_text_field($appt['type']) : 'Consultation');
            $appointment_reference = !empty($appt['appt_uid'])
                ? sanitize_text_field($appt['appt_uid'])
                : sanitize_text_field($appt_uid);
            $appointment_status = !empty($appt['status'])
                ? sanitize_text_field($appt['status'])
                : 'Rescheduled';
            $appointment_timezone = wp_timezone_string();
            $old_appointment_date = $format_date($old_date);
            $old_appointment_time = $format_time($old_time);
            $new_appointment_date = $format_date($new_date);
            $new_appointment_time = $format_time($new_time);
            $updated_appointment_url = home_url('/patient-dashboard/#tab=appointments');
            $site_url = home_url('/');
            $site_name = get_bloginfo('name') ?: 'DIOR MEDICAL';

            $template_path = defined('DIOR_PORTAL_PATH')
                ? DIOR_PORTAL_PATH . 'templates/emails/appointment-rescheduled.php'
                : dirname(__DIR__) . '/templates/emails/appointment-rescheduled.php';

            if (is_readable($template_path)) {
                ob_start();
                include $template_path;
                $patient_html = ob_get_clean();

                self::send_email_safe(
                    $patient->user_email,
                    'Appointment Rescheduled: ' . ($new_appointment_date ?: 'Updated Schedule') . ($new_appointment_time ? ' at ' . $new_appointment_time : ''),
                    $patient_html
                );
            } else {
                error_log('Dior Medical: appointment rescheduled email template is missing: ' . $template_path);
            }
        }

        // Doctor Dashboard Notification
        if ($did && $doctor) {
            self::add_dashboard_notification(
                $did,
                'Appointment Rescheduled (' . $pname . ')',
                $pname . ' updated their visit to ' . $new_date . ' at ' . $new_time . '.',
                '#tab=doc-appointments',
                'fa-calendar-days',
                true
            );

            if ($doctor->user_email) {
                $d_content = '
                <p>Hello <strong>' . esc_html($dname) . '</strong>,</p>
                <p>The appointment for patient <strong>' . esc_html($pname) . '</strong> has been rescheduled.</p>
                <div style="background-color: #EFF6FF; border-left: 4px solid #2C6CB1; border-radius: 4px; padding: 16px 20px; margin: 20px 0;">
                    <p style="margin: 0 0 6px 0; color: #1E40AF; font-weight: 600;">NEW Slot:</p>
                    <p style="margin: 0; font-size: 16px; font-weight: 700; color: #1E3A8A;">' . esc_html($new_date . ' at ' . $new_time) . '</p>
                    <p style="margin: 8px 0 0 0; font-size: 13px; color: #64748B;">Previous Slot: <strike>' . esc_html($old_date . ' ' . $old_time) . '</strike></p>
                </div>
                <p>Please check your schedule on your provider dashboard.</p>';

                $d_html = self::render_email_template(
                    'Appointment Rescheduled by Patient',
                    'Patient ' . $pname . ' moved to ' . $new_date . ' at ' . $new_time,
                    $d_content,
                    ['label' => 'View Doctor Schedule', 'url' => home_url('/doctor-dashboard/#tab=doc-appointments')]
                );
                self::send_email_safe($doctor->user_email, 'Schedule Update: ' . $pname . ' rescheduled to ' . $new_date, $d_html);
            }
        }

        return true;
    }

    /**
     * 4. Cancellation Notifications
     */
    public static function send_appointment_cancelled($appt_uid, $reason = '', $cancelled_by = 'patient')
    {
        $appt = Dior_Appointment_Service::get_appointment($appt_uid);
        if (!$appt) {
            return false;
        }

        $pid = (int) ($appt['patient_id'] ?? 0);
        $did = (int) ($appt['doctor_id'] ?? 0);
        $patient = $pid ? get_userdata($pid) : false;
        $doctor = $did ? get_userdata($did) : false;

        $patient_full_name = $patient ? trim((string) $patient->first_name . ' ' . (string) $patient->last_name) : '';
        $patient_name = $patient_full_name !== '' ? $patient_full_name : ($patient ? $patient->display_name : 'Patient');
        $doctor_full_name = $doctor ? trim((string) $doctor->first_name . ' ' . (string) $doctor->last_name) : '';
        $fallback_doctor_name = $doctor_full_name !== '' ? 'Dr. ' . $doctor_full_name : ($doctor ? $doctor->display_name : 'Attending Physician');

        $doctor_uid = !empty($appt['doctor_user_id']) ? (int) $appt['doctor_user_id'] : 0;
        if (!$doctor_uid && $did) {
            $doctor_uid = class_exists('Dior_Doctor_Resolver')
                ? (int) Dior_Doctor_Resolver::resolve_doctor_user_id($did)
                : $did;
        }
        $doctor_user = $doctor_uid ? get_userdata($doctor_uid) : false;
        $resolved_doctor_name = $doctor_user
            ? trim((string) $doctor_user->first_name . ' ' . (string) $doctor_user->last_name)
            : '';
        $doctor_name = !empty($appt['provider'])
            ? sanitize_text_field($appt['provider'])
            : (!empty($appt['doctor_name'])
                ? sanitize_text_field($appt['doctor_name'])
                : ($resolved_doctor_name !== '' ? 'Dr. ' . $resolved_doctor_name : $fallback_doctor_name));

        $raw_date = (string) ($appt['appt_date'] ?? $appt['date'] ?? '');
        $raw_time = (string) ($appt['appt_time'] ?? $appt['time'] ?? '');
        $format_date = static function ($value) {
            $value = (string) $value;
            $timestamp = $value !== '' ? strtotime($value) : false;
            return $timestamp ? wp_date(get_option('date_format') ?: 'F j, Y', $timestamp) : $value;
        };
        $format_time = static function ($value) {
            $value = (string) $value;
            $timestamp = $value !== '' ? strtotime($value) : false;
            return $timestamp ? wp_date(get_option('time_format') ?: 'g:i a', $timestamp) : $value;
        };

        $appointment_date = $format_date($raw_date);
        $appointment_time = $format_time($raw_time);
        $appointment_type = !empty($appt['visit_type'])
            ? sanitize_text_field($appt['visit_type'])
            : (!empty($appt['type']) ? sanitize_text_field($appt['type']) : 'Consultation');
        $doctor_specialty = !empty($appt['doctor_specialty'])
            ? sanitize_text_field($appt['doctor_specialty'])
            : (!empty($appt['specialty'])
                ? sanitize_text_field($appt['specialty'])
                : (!empty($appt['provider_spec']) ? sanitize_text_field($appt['provider_spec']) : ''));
        $appointment_reference = !empty($appt['appt_uid'])
            ? sanitize_text_field($appt['appt_uid'])
            : sanitize_text_field($appt_uid);
        // The cancellation event is authoritative even if a caller invokes this
        // notification before the appointment row's status has finished updating.
        $appointment_status = 'Cancelled';
        $cancellation_reason = sanitize_textarea_field((string) $reason);
        $appointment_timezone = wp_timezone_string();
        $site_name = get_bloginfo('name') ?: 'DIOR MEDICAL';
        $site_url = home_url('/');
        $book_appointment_url = home_url('/patient-dashboard/#tab=appointments');

        // Patient in-app notification.
        if ($pid) {
            self::add_dashboard_notification(
                $pid,
                'Appointment Cancelled: #' . $appointment_reference,
                'Your appointment scheduled for ' . ($appointment_date ?: 'the scheduled date') . ($appointment_time ? ' at ' . $appointment_time : '') . ' has been cancelled.',
                '#tab=appointments',
                'fa-calendar-xmark'
            );
        }

        // Patient email: use the dedicated branded, data-driven cancellation template.
        if ($patient && !empty($patient->user_email)) {
            $template_path = defined('DIOR_PORTAL_PATH')
                ? DIOR_PORTAL_PATH . 'templates/emails/appointment-cancelled.php'
                : dirname(__DIR__) . '/templates/emails/appointment-cancelled.php';

            if (is_readable($template_path)) {
                ob_start();
                include $template_path;
                $patient_html = ob_get_clean();

                self::send_email_safe(
                    $patient->user_email,
                    'Appointment Cancelled: ' . ($appointment_date ?: $appointment_reference),
                    $patient_html
                );
            } else {
                error_log('Dior Medical: appointment cancelled email template is missing: ' . $template_path);
            }
        }

        // Preserve the existing doctor dashboard notification and email workflow.
        if ($did && $doctor) {
            self::add_dashboard_notification(
                $did,
                'Appointment Cancelled (' . $patient_name . ')',
                $patient_name . ' has an appointment cancelled for ' . ($appointment_date ?: 'the scheduled date') . ($appointment_time ? ' at ' . $appointment_time : '') . '.',
                '#tab=doc-appointments',
                'fa-calendar-xmark',
                true
            );

            if (!empty($doctor->user_email)) {
                $reason_for_doctor = $cancellation_reason !== '' ? $cancellation_reason : 'Not specified';
                $doctor_content = '
                    <p>Hello <strong>' . esc_html($fallback_doctor_name) . '</strong>,</p>
                    <p>The appointment with patient <strong>' . esc_html($patient_name) . '</strong> scheduled for <strong>' . esc_html(trim($appointment_date . ' ' . $appointment_time)) . '</strong> has been cancelled.</p>
                    <div style="background:#F1F5F9;border-left:4px solid #64748B;border-radius:4px;padding:14px 18px;margin:18px 0;color:#334155;">
                        <strong>Reason:</strong> ' . esc_html($reason_for_doctor) . '
                    </div>
                    <p>This time slot has been freed up on your calendar.</p>';

                $doctor_html = self::render_email_template(
                    'Appointment Cancelled',
                    'Appointment with ' . $patient_name . ' on ' . $appointment_date . ' has been cancelled.',
                    $doctor_content,
                    ['label' => 'View Doctor Schedule', 'url' => home_url('/doctor-dashboard/#tab=doc-appointments')]
                );
                self::send_email_safe(
                    $doctor->user_email,
                    'Cancellation Notice: ' . $patient_name . ' (' . $appointment_date . ')',
                    $doctor_html
                );
            }
        }

        return true;
    }

    /**
     * 5. Payment Confirmation
     */
    public static function send_payment_confirmation($user_id, $payment_data = [])
    {
        $user = get_userdata($user_id);
        if (!$user) return false;

        $name = trim($user->first_name . ' ' . $user->last_name) ?: $user->display_name;
        $amount = $payment_data['amount'] ?? '$49.00';
        $desc   = $payment_data['description'] ?? 'Urgent Care Telehealth Consultation';
        $inv_id = $payment_data['id'] ?? ('INV-' . rand(1000, 9999));

        // In-App Notification
        self::add_dashboard_notification(
            $user_id,
            'Payment Receipt Issued: ' . $amount,
            'Your payment of ' . $amount . ' for ' . $desc . ' (' . $inv_id . ') was processed successfully via Stripe.',
            '#tab=payments',
            'fa-receipt'
        );

        // Email
        if ($user->user_email) {
            $content = '
            <p>Dear <strong>' . esc_html($name) . '</strong>,</p>
            <p>Thank you for your payment. Your transaction has been approved and processed securely.</p>
            <div style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px 20px; margin: 20px 0;">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size: 14px;">
                    <tr><td style="padding: 5px 0; color: #64748B;">Invoice Number:</td><td style="padding: 5px 0; font-weight: 600; color: #1E293B;">#' . esc_html($inv_id) . '</td></tr>
                    <tr><td style="padding: 5px 0; color: #64748B;">Service Description:</td><td style="padding: 5px 0; font-weight: 600; color: #1E293B;">' . esc_html($desc) . '</td></tr>
                    <tr><td style="padding: 5px 0; color: #64748B;">Amount Paid:</td><td style="padding: 5px 0; font-weight: 700; color: #059669; font-size: 16px;">' . esc_html($amount) . '</td></tr>
                    <tr><td style="padding: 5px 0; color: #64748B;">Status:</td><td style="padding: 5px 0; font-weight: 600; color: #059669;">Paid (Stripe 256-Bit SSL)</td></tr>
                </table>
            </div>
            <p>You can view and download itemized PDF receipts directly from your Billing &amp; Invoices tab.</p>';

            $html = self::render_email_template(
                'Payment Receipt Confirmed',
                'Your payment of ' . $amount . ' was successful.',
                $content,
                ['label' => 'View Invoices in Portal', 'url' => home_url('/patient-dashboard/#tab=payments')]
            );
            self::send_email_safe($user->user_email, 'Payment Receipt: ' . $amount . ' (' . $inv_id . ')', $html);
        }

        return true;
    }

    /**
     * 6. Appointment Reminders (Sent to BOTH Doctor & Patient)
     */
    public static function send_appointment_reminder($appt_uid, $triggered_by = 'system')
    {
        $appt = Dior_Appointment_Service::get_appointment($appt_uid);
        if (!$appt) return false;

        $pid = (int)$appt['patient_id'];
        $did = (int)$appt['doctor_id'];

        $patient = get_userdata($pid);
        $doctor  = $did ? get_userdata($did) : null;

        $pname = $patient ? trim($patient->first_name . ' ' . $patient->last_name) ?: $patient->display_name : 'Patient';
        $dname = $doctor ? 'Dr. ' . (trim($doctor->first_name . ' ' . $doctor->last_name) ?: $doctor->display_name) : 'Attending Physician';

        $date = $appt['appt_date'];
        $time = $appt['appt_time'];
        $cond = $appt['condition_name'] ?: 'Urgent Care Telehealth';
        $join_url = $appt['join_url'] ?: home_url('/patient-dashboard/#tab=appointments');

        // Check Patient Opt-In Preferences (Default to '1' / enabled)
        $p_optin_overall   = get_user_meta($pid, 'optin_appointment_reminders', true);
        $p_optin_email     = get_user_meta($pid, 'optin_reminder_email', true);
        $p_optin_dashboard = get_user_meta($pid, 'optin_reminder_dashboard', true);

        $p_send_dashboard = ($p_optin_overall !== '0' && $p_optin_dashboard !== '0');
        $p_send_email     = ($p_optin_overall !== '0' && $p_optin_email !== '0');

        // Check Doctor Opt-In Preferences (Default to '1' / enabled)
        $d_optin_overall   = $did ? get_user_meta($did, 'optin_appointment_reminders', true) : '1';
        $d_optin_email     = $did ? get_user_meta($did, 'optin_reminder_email', true) : '1';
        $d_optin_dashboard = $did ? get_user_meta($did, 'optin_reminder_dashboard', true) : '1';

        $d_send_dashboard = ($d_optin_overall !== '0' && $d_optin_dashboard !== '0');
        $d_send_email     = ($d_optin_overall !== '0' && $d_optin_email !== '0');

        // 1. Patient In-App Dashboard Reminder
        if ($p_send_dashboard) {
            self::add_dashboard_notification(
                $pid,
                'Appointment Reminder: ' . $date . ' at ' . $time,
                'Reminder: Your telehealth visit with ' . $dname . ' is scheduled for ' . $date . ' at ' . $time . '.',
                '#tab=appointments',
                'fa-bell'
            );
        }

        // 2. Patient Email Reminder (dedicated dynamic branded template)
        if ($p_send_email && $patient && $patient->user_email) {
            $raw_date = !empty($appt['appt_date']) ? (string) $appt['appt_date'] : (string) $date;
            $raw_time = !empty($appt['appt_time']) ? (string) $appt['appt_time'] : (string) $time;
            $date_timestamp = $raw_date !== '' ? strtotime($raw_date) : false;

            $appointment_date = ($date_timestamp !== false)
                ? wp_date(get_option('date_format') ?: 'F j, Y', $date_timestamp)
                : $raw_date;
            $time_timestamp = ($raw_date !== '' && $raw_time !== '')
                ? strtotime($raw_date . ' ' . $raw_time)
                : false;
            $appointment_time = ($time_timestamp !== false)
                ? wp_date(get_option('time_format') ?: 'g:i a', $time_timestamp)
                : $raw_time;

            $doctor_specialty = !empty($appt['provider_spec'])
                ? sanitize_text_field($appt['provider_spec'])
                : (!empty($appt['doctor_specialty'])
                    ? sanitize_text_field($appt['doctor_specialty'])
                    : (!empty($appt['specialty']) ? sanitize_text_field($appt['specialty']) : ''));

            $appointment_timezone = wp_timezone_string();
            $appointment_type = !empty($appt['visit_type'])
                ? sanitize_text_field($appt['visit_type'])
                : (!empty($appt['type']) ? sanitize_text_field($appt['type']) : 'Telehealth Consultation');
            $appointment_reference = !empty($appt['appt_uid'])
                ? sanitize_text_field($appt['appt_uid'])
                : sanitize_text_field($appt_uid);
            $appointment_status = !empty($appt['status'])
                ? sanitize_text_field($appt['status'])
                : 'Scheduled';

            $appointment_url = !empty($appt['join_url'])
                ? esc_url_raw($appt['join_url'])
                : home_url('/patient-dashboard/#tab=appointments');
            $manage_appointment_url = home_url('/patient-dashboard/#tab=appointments');
            $site_url = home_url('/');
            $site_name = get_bloginfo('name') ?: 'DIOR MEDICAL';

            $normalized_type = strtolower(str_replace(array('-', '_'), ' ', $appointment_type));
            $is_telehealth = (strpos($normalized_type, 'tele') !== false)
                || (strpos($normalized_type, 'video') !== false)
                || (strpos($normalized_type, 'virtual') !== false)
                || (strpos($normalized_type, 'online') !== false);

            $instruction_heading = $is_telehealth
                ? 'Prepare for Your Telehealth Visit'
                : 'Prepare for Your Appointment';
            $appointment_instructions = $is_telehealth
                ? 'Please sign in a few minutes early, choose a private and quiet space, and check your internet connection, camera, and microphone before your consultation.'
                : 'Please review your appointment details in the patient portal and arrive at the scheduled time. Contact the practice through your portal if you need assistance.';

            // The email template uses explicit patient/doctor variable names.
            $patient_name = $pname;
            $doctor_name = $dname;

            $template_path = defined('DIOR_PORTAL_PATH')
                ? DIOR_PORTAL_PATH . 'templates/emails/appointment-reminder.php'
                : dirname(__DIR__) . '/templates/emails/appointment-reminder.php';

            if (is_readable($template_path)) {
                ob_start();
                include $template_path;
                $patient_html = ob_get_clean();

                self::send_email_safe(
                    $patient->user_email,
                    'Appointment Reminder: ' . ($appointment_date ?: 'Upcoming Visit') . ' with ' . $dname,
                    $patient_html
                );
            } else {
                error_log('Dior Medical: appointment reminder email template is missing: ' . $template_path);
            }
        }

        // 3. Doctor In-App Dashboard Reminder
        if ($d_send_dashboard && $did && $doctor) {
            self::add_dashboard_notification(
                $did,
                'Upcoming Patient Reminder (' . $pname . ')',
                'Patient ' . $pname . ' is scheduled for ' . $date . ' at ' . $time . ' (' . $cond . ').',
                '#tab=doc-appointments',
                'fa-bell',
                true
            );
        }

        // 4. Doctor Email Reminder
        if ($d_send_email && $did && $doctor && $doctor->user_email) {
            $d_content = '
            <p>Hello <strong>' . esc_html($dname) . '</strong>,</p>
            <p>This is a reminder that you have a scheduled appointment with patient <strong>' . esc_html($pname) . '</strong>.</p>
            <div style="background-color: #FEF3C7; border-left: 4px solid #D97706; border-radius: 4px; padding: 16px 20px; margin: 20px 0; color: #92400E;">
                <p style="margin: 0 0 6px 0; font-size: 15px; font-weight: 700;">Appointment Time:</p>
                <p style="margin: 0; font-size: 16px; font-weight: 700; color: #78350F;">' . esc_html($date . ' at ' . $time) . '</p>
                <p style="margin: 6px 0 0 0; font-size: 13px;">Condition: ' . esc_html($cond) . '</p>
            </div>
            <p>Please ensure your video room is ready and patient chart notes are open.</p>';

            $d_html = self::render_email_template(
                'Provider Appointment Reminder',
                'Upcoming visit with patient ' . $pname . ' on ' . $date . ' at ' . $time,
                $d_content,
                ['label' => 'Open Doctor Dashboard', 'url' => home_url('/doctor-dashboard/#tab=doc-appointments')]
            );
            self::send_email_safe($doctor->user_email, 'Provider Reminder: Consultation with ' . $pname . ' (' . $date . ')', $d_html);
        }

        return true;
    }

    /**
     * 7. Consultation Reminders (Imminent / Live Call Readiness)
     */
    public static function send_consultation_reminder($appt_uid)
    {
        $appt = Dior_Appointment_Service::get_appointment($appt_uid);
        if (!$appt) return false;

        $pid = (int)$appt['patient_id'];
        $did = (int)$appt['doctor_id'];

        $patient = get_userdata($pid);
        $doctor  = $did ? get_userdata($did) : null;

        $pname = $patient ? trim($patient->first_name . ' ' . $patient->last_name) ?: $patient->display_name : 'Patient';
        $dname = $doctor ? 'Dr. ' . (trim($doctor->first_name . ' ' . $doctor->last_name) ?: $doctor->display_name) : 'Attending Physician';

        $join_url = $appt['join_url'] ?: home_url('/patient-dashboard/#tab=appointments');

        // Patient In-App Live Alert
        self::add_dashboard_notification(
            $pid,
            'Consultation Starting Soon!',
            $dname . ' is preparing your telehealth room. Click to enter and begin your visit.',
            $join_url,
            'fa-video'
        );

        // Patient Immediate Email
        if ($patient && $patient->user_email) {
            $p_content = '
            <p>Dear <strong>' . esc_html($pname) . '</strong>,</p>
            <p>Your telehealth consultation window with <strong>' . esc_html($dname) . '</strong> is now open.</p>
            <div style="background-color: #ECFDF5; border-left: 4px solid #10B981; border-radius: 4px; padding: 16px 20px; margin: 20px 0; color: #065F46;">
                <p style="margin: 0; font-size: 15px; font-weight: 700;">Live Video Consultation Ready</p>
                <p style="margin: 4px 0 0 0; font-size: 13px;">Please click below to connect with your provider.</p>
            </div>';

            $p_html = self::render_email_template(
                'Your Video Room is Ready',
                'Your consultation is starting now. Click to join.',
                $p_content,
                ['label' => 'Enter Video Consultation Now', 'url' => $join_url]
            );
            self::send_email_safe($patient->user_email, 'Live Call Ready: Connect with ' . $dname, $p_html);
        }

        // Doctor In-App
        if ($did && $doctor) {
            self::add_dashboard_notification(
                $did,
                'Call Window Active: ' . $pname,
                'Patient ' . $pname . ' consultation window is now active.',
                '#tab=doc-appointments',
                'fa-video',
                true
            );
        }

        return true;
    }

    /**
     * Automated WP-Cron Batch: Check and dispatch reminders for upcoming consultations
     * (Scheduled within the next 24 hours that haven't received an auto-reminder yet)
     * Dispatches to BOTH Doctor and Patient via Email and Dashboard Notification.
     * 
     * @return int Number of reminders dispatched
     */
    public static function check_and_send_scheduled_reminders()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_appointments';

        // Only inspect appointments scheduled for today or tomorrow in the WordPress site timezone.
        $today = current_time('Y-m-d');
        $tomorrow = wp_date('Y-m-d', current_datetime()->modify('+1 day')->getTimestamp());

        $upcoming = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
             WHERE appt_date IN (%s, %s)
             AND status IN ('Confirmed', 'Scheduled', 'In-Queue', 'Success')
             AND is_deleted = 0",
            $today,
            $tomorrow
        ), ARRAY_A);

        if (empty($upcoming)) {
            return 0;
        }

        $now = current_datetime();
        $window_end = $now->modify('+24 hours');
        $count = 0;

        foreach ($upcoming as $apt) {
            $appt_uid = sanitize_text_field($apt['appt_uid'] ?? '');
            $appt_date = sanitize_text_field($apt['appt_date'] ?? '');
            $appt_time = sanitize_text_field($apt['appt_time'] ?? '');
            // Respect legacy markers so deploying this fix does not resend reminders already sent.
            if (strpos((string) ($apt['notes'] ?? ''), '[Auto-Reminder sent') !== false) {
                continue;
            }
            if ($appt_uid === '' || $appt_date === '' || $appt_time === '') {
                continue;
            }

            try {
                $appointment_start = new DateTimeImmutable($appt_date . ' ' . $appt_time, wp_timezone());
            } catch (\Throwable $e) {
                error_log('[Dior Medical] Skipping reminder for invalid appointment date/time: ' . $appt_uid);
                continue;
            }

            // Send reminders only for future appointments within the next 24 hours.
            if ($appointment_start <= $now || $appointment_start > $window_end) {
                continue;
            }

            // Include the scheduled slot in the key: a rescheduled appointment can receive a
            // reminder for its new date/time, while repeated hourly cron runs cannot resend it.
            $reminder_key = 'dior_appt_reminder_sent_' . md5($appt_uid . '|' . $appt_date . '|' . $appt_time);
            if (get_option($reminder_key, false)) {
                continue;
            }

            // Atomic short lock prevents overlapping cron requests from sending duplicates.
            $lock_key = $reminder_key . '_lock';
            if (!add_option($lock_key, time(), '', false)) {
                $lock_time = (int) get_option($lock_key, 0);
                if ($lock_time > 0 && (time() - $lock_time) < 10 * MINUTE_IN_SECONDS) {
                    continue;
                }
                delete_option($lock_key);
                if (!add_option($lock_key, time(), '', false)) {
                    continue;
                }
            }

            try {
                self::send_appointment_reminder($appt_uid, 'system');
                // Keep a permanent idempotency marker for this exact appointment slot.
                update_option($reminder_key, current_time('mysql'), false);
                $count++;
            } catch (\Throwable $e) {
                error_log('[Dior Medical] Appointment reminder failed for ' . $appt_uid . ': ' . $e->getMessage());
            } finally {
                delete_option($lock_key);
            }
        }

        return $count;
    }

    /**
     * 8. Status Change Notifications (Multi-Channel: In-App Dashboard + Luxury Email)
     * Dispatches relevant notification to patient and doctor based on the new status.
     * 
     * @param string $appt_uid
     * @param string $old_status
     * @param string $new_status
     * @param string $reason
     * @param string $updated_by
     * @return bool
     */
    public static function send_status_change_notification($appt_uid, $old_status, $new_status, $reason = '', $updated_by = 'doctor')
    {
        if (empty($appt_uid)) return false;

        $appt = class_exists('Dior_Appointment_Service') ? Dior_Appointment_Service::get_appointment($appt_uid) : null;
        if (!$appt) return false;

        $pid = (int)($appt['patient_id'] ?? 0);
        $did = (int)($appt['doctor_id'] ?? 0);

        $patient = $pid ? get_userdata($pid) : null;
        $doctor_uid = !empty($appt['doctor_user_id']) ? (int)$appt['doctor_user_id'] : 0;
        if (!$doctor_uid && $did) {
            $doctor_uid = class_exists('Dior_Doctor_Resolver') ? Dior_Doctor_Resolver::resolve_doctor_user_id($did) : $did;
        }
        $doctor = $doctor_uid ? get_userdata($doctor_uid) : null;

        $pname = $patient ? trim($patient->first_name . ' ' . $patient->last_name) ?: $patient->display_name : 'Patient';
        $dname = !empty($appt['provider']) ? $appt['provider'] : (!empty($appt['doctor_name']) ? $appt['doctor_name'] : ($doctor ? 'Dr. ' . (trim($doctor->first_name . ' ' . $doctor->last_name) ?: $doctor->display_name) : 'Attending Physician'));

        $date = !empty($appt['appt_date']) ? $appt['appt_date'] : (!empty($appt['date']) ? $appt['date'] : current_time('Y-m-d'));
        $time = !empty($appt['appt_time']) ? $appt['appt_time'] : (!empty($appt['time']) ? $appt['time'] : '');
        $status_lc = strtolower(trim($new_status));
        $reason_text = $reason ?: 'Updated by ' . ucfirst($updated_by);

        switch ($status_lc) {
            case 'confirmed':
                // 1. Patient In-App & Email
                self::add_dashboard_notification(
                    $pid,
                    'Appointment Confirmed: #' . $appt['appt_uid'],
                    'Your consultation with ' . $dname . ' on ' . $date . ($time ? ' at ' . $time : '') . ' is confirmed.',
                    '#tab=appointments',
                    'fa-calendar-check'
                );

                if ($patient && $patient->user_email) {
                    $p_content = '
                    <p>Dear <strong>' . esc_html($pname) . '</strong>,</p>
                    <p>Your appointment has been <strong>Confirmed</strong> with <strong>' . esc_html($dname) . '</strong>.</p>
                    <div style="background-color: #ECFDF5; border-left: 4px solid #10B981; border-radius: 4px; padding: 16px 20px; margin: 20px 0; color: #065F46;">
                        <p style="margin: 0 0 6px 0; font-weight: 700;">Confirmed Appointment:</p>
                        <p style="margin: 0; font-size: 16px; font-weight: 700;">' . esc_html($date . ($time ? ' at ' . $time : '')) . '</p>
                        <p style="margin: 6px 0 0 0; font-size: 13px;">Provider: ' . esc_html($dname) . ' &bull; Type: ' . esc_html($appt['visit_type'] ?? 'Video Visit (HD)') . '</p>
                    </div>
                    <p>Please be ready 5 minutes before your scheduled start time.</p>';

                    $p_html = self::render_email_template(
                        'Appointment Confirmed',
                        'Your consultation on ' . $date . ' is confirmed.',
                        $p_content,
                        ['label' => 'View Appointment in Portal', 'url' => home_url('/patient-dashboard/#tab=appointments')]
                    );
                    self::send_email_safe($patient->user_email, 'Appointment Confirmed: ' . $date . ' with ' . $dname, $p_html);
                }

                // Doctor In-App
                if ($doctor) {
                    self::add_dashboard_notification(
                        $doctor->ID,
                        'Appointment Confirmed (' . $pname . ')',
                        'Appointment #' . $appt['appt_uid'] . ' with ' . $pname . ' for ' . $date . ' is confirmed.',
                        '#tab=doc-appointments',
                        'fa-calendar-check',
                        true
                    );
                }
                break;

            case 'cancelled':
                self::send_appointment_cancelled($appt_uid, $reason, $updated_by);
                break;

            case 'completed':
                // Patient In-App
                self::add_dashboard_notification(
                    $pid,
                    'Consultation Completed: #' . $appt['appt_uid'],
                    'Your consultation with ' . $dname . ' on ' . $date . ' has concluded. Clinical notes and prescriptions are available in your portal.',
                    '#tab=appointments',
                    'fa-circle-check'
                );

                // Patient Email
                if ($patient && $patient->user_email) {
                    $p_content = '
                    <p>Dear <strong>' . esc_html($pname) . '</strong>,</p>
                    <p>Your consultation with <strong>' . esc_html($dname) . '</strong> on <strong>' . esc_html($date . ($time ? ' at ' . $time : '')) . '</strong> has been marked as <strong>Completed</strong>.</p>
                    <div style="background-color: #ECFDF5; border-left: 4px solid #10B981; border-radius: 4px; padding: 14px 18px; margin: 18px 0; color: #065F46;">
                        <strong>Status:</strong> Consultation Concluded Successfully
                    </div>
                    <p>You can review your visit notes, clinical summary, and digital prescriptions anytime from your secure patient dashboard.</p>';

                    $p_html = self::render_email_template(
                        'Consultation Completed',
                        'Your visit summary is ready in your portal.',
                        $p_content,
                        ['label' => 'View Medical Record', 'url' => home_url('/patient-dashboard/#tab=medical_record')]
                    );
                    self::send_email_safe($patient->user_email, 'Consultation Concluded: ' . $date . ' with ' . $dname, $p_html);
                }

                // Doctor In-App
                if ($doctor) {
                    self::add_dashboard_notification(
                        $doctor->ID,
                        'Consultation Concluded (' . $pname . ')',
                        'Appointment #' . $appt['appt_uid'] . ' with ' . $pname . ' has been marked as Completed.',
                        '#tab=doc-appointments',
                        'fa-circle-check',
                        true
                    );
                }
                break;

            case 'in progress':
                self::send_consultation_reminder($appt_uid);
                break;

            case 'no show':
                // Patient In-App
                self::add_dashboard_notification(
                    $pid,
                    'Missed Appointment: #' . $appt['appt_uid'],
                    'Your appointment on ' . $date . ' was marked as Missed / No-Show. Please visit your portal if you wish to reschedule.',
                    '#tab=appointments',
                    'fa-calendar-times'
                );

                // Patient Email
                if ($patient && $patient->user_email) {
                    $p_content = '
                    <p>Dear <strong>' . esc_html($pname) . '</strong>,</p>
                    <p>Your scheduled appointment with <strong>' . esc_html($dname) . '</strong> on <strong>' . esc_html($date . ($time ? ' at ' . $time : '')) . '</strong> was recorded as Missed / No-Show.</p>
                    <p>If you still require medical consultation, you may easily reschedule a new appointment through your portal.</p>';

                    $p_html = self::render_email_template(
                        'Appointment Status: Missed Visit',
                        'Your appointment was marked as Missed / No-Show.',
                        $p_content,
                        ['label' => 'Reschedule Appointment', 'url' => home_url('/patient-dashboard/#tab=appointments&subtab=book')]
                    );
                    self::send_email_safe($patient->user_email, 'Appointment Status: Missed Consultation on ' . $date, $p_html);
                }
                break;

            default:
                // Generic status change notification
                self::add_dashboard_notification(
                    $pid,
                    'Appointment Status Updated: ' . $new_status,
                    'Your appointment #' . $appt['appt_uid'] . ' status has been updated to ' . $new_status . '.',
                    '#tab=appointments',
                    'fa-bell'
                );
                break;
        }

        return true;
    }
}
