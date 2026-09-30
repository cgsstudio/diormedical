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
        if (!$appt) return false;

        $pid = (int)$appt['patient_id'];
        $did = (int)$appt['doctor_id'];

        $patient = get_userdata($pid);
        $doctor  = $did ? get_userdata($did) : null;

        $pname = $patient ? trim($patient->first_name . ' ' . $patient->last_name) ?: $patient->display_name : 'Patient';
        $dname = $doctor ? 'Dr. ' . (trim($doctor->first_name . ' ' . $doctor->last_name) ?: $doctor->display_name) : 'Attending Physician';

        $date = $appt['appt_date'];
        $time = $appt['appt_time'];
        $condition = $appt['condition_name'] ?: 'Urgent Care Telehealth';
        $join_url = $appt['join_url'] ?: home_url('/patient-dashboard/#tab=appointments');

        // 1. Patient Notification
        self::add_dashboard_notification(
            $pid,
            'Consultation Confirmed: ' . $date,
            'Your consultation with ' . $dname . ' for ' . $condition . ' on ' . $date . ' at ' . $time . ' is confirmed.',
            '#tab=appointments',
            'fa-calendar-check'
        );

        if ($patient && $patient->user_email) {
            $p_content = '
            <p>Dear <strong>' . esc_html($pname) . '</strong>,</p>
            <p>Your telehealth consultation has been successfully booked and confirmed with <strong>' . esc_html($dname) . '</strong>.</p>
            
            <div style="background-color: #F1F5F9; border-radius: 8px; padding: 18px 20px; margin: 20px 0; border: 1px solid #E2E8F0;">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size: 14px;">
                    <tr><td style="padding: 5px 0; color: #64748B; width: 130px;">Appointment ID:</td><td style="padding: 5px 0; font-weight: 600; color: #1E293B;">#' . esc_html($appt['appt_uid']) . '</td></tr>
                    <tr><td style="padding: 5px 0; color: #64748B;">Date &amp; Time:</td><td style="padding: 5px 0; font-weight: 600; color: #1E293B;">' . esc_html($date . ' at ' . $time) . '</td></tr>
                    <tr><td style="padding: 5px 0; color: #64748B;">Consultation Type:</td><td style="padding: 5px 0; font-weight: 600; color: #1E293B;">' . esc_html($appt['visit_type']) . '</td></tr>
                    <tr><td style="padding: 5px 0; color: #64748B;">Specialty / Reason:</td><td style="padding: 5px 0; font-weight: 600; color: #1E293B;">' . esc_html($condition) . '</td></tr>
                    <tr><td style="padding: 5px 0; color: #64748B;">Provider:</td><td style="padding: 5px 0; font-weight: 600; color: #1E293B;">' . esc_html($dname) . '</td></tr>
                </table>
            </div>

            <p>Please log in 5 minutes prior to your scheduled time. Ensure your camera and microphone are connected and functioning.</p>';

            $p_html = self::render_email_template(
                'Appointment Confirmed',
                'Your consultation is scheduled for ' . $date . ' at ' . $time,
                $p_content,
                ['label' => 'View in Patient Dashboard', 'url' => home_url('/patient-dashboard/#tab=appointments')]
            );
            self::send_email_safe($patient->user_email, 'Appointment Confirmation: ' . $date . ' with ' . $dname, $p_html);
        }

        // 2. Doctor Notification
        if ($did && $doctor) {
            self::add_dashboard_notification(
                $did,
                'New Appointment Scheduled (' . $pname . ')',
                $pname . ' scheduled a visit for ' . $date . ' at ' . $time . ' (' . $condition . ').',
                '#tab=doc-appointments',
                'fa-calendar-plus',
                true
            );

            if ($doctor->user_email) {
                $d_content = '
                <p>Hello <strong>' . esc_html($dname) . '</strong>,</p>
                <p>A new consultation has been booked with you by patient <strong>' . esc_html($pname) . '</strong>.</p>
                <div style="background-color: #F1F5F9; border-radius: 8px; padding: 18px 20px; margin: 20px 0; border: 1px solid #E2E8F0;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size: 14px;">
                        <tr><td style="padding: 5px 0; color: #64748B; width: 130px;">Patient Name:</td><td style="padding: 5px 0; font-weight: 600; color: #1E293B;">' . esc_html($pname) . '</td></tr>
                        <tr><td style="padding: 5px 0; color: #64748B;">Date &amp; Time:</td><td style="padding: 5px 0; font-weight: 600; color: #1E293B;">' . esc_html($date . ' at ' . $time) . '</td></tr>
                        <tr><td style="padding: 5px 0; color: #64748B;">Condition:</td><td style="padding: 5px 0; font-weight: 600; color: #1E293B;">' . esc_html($condition) . '</td></tr>
                        <tr><td style="padding: 5px 0; color: #64748B;">Notes:</td><td style="padding: 5px 0; font-weight: 600; color: #1E293B;">' . esc_html($appt['notes'] ?: 'None provided') . '</td></tr>
                    </table>
                </div>
                <p>You can review clinical intake questionnaires and start the session directly from your provider portal.</p>';

                $d_html = self::render_email_template(
                    'New Appointment Scheduled',
                    'Patient ' . $pname . ' scheduled for ' . $date . ' at ' . $time,
                    $d_content,
                    ['label' => 'Open Doctor Dashboard', 'url' => home_url('/doctor-dashboard/#tab=doc-appointments')]
                );
                self::send_email_safe($doctor->user_email, 'New Patient Appointment: ' . $pname . ' (' . $date . ')', $d_html);
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

        $patient = get_userdata($pid);
        $doctor  = $did ? get_userdata($did) : null;

        $pname = $patient ? trim($patient->first_name . ' ' . $patient->last_name) ?: $patient->display_name : 'Patient';
        $dname = $doctor ? 'Dr. ' . (trim($doctor->first_name . ' ' . $doctor->last_name) ?: $doctor->display_name) : 'Attending Physician';

        // Patient Dashboard Notification
        self::add_dashboard_notification(
            $pid,
            'Appointment Rescheduled',
            'Your consultation with ' . $dname . ' has been rescheduled to ' . $new_date . ' at ' . $new_time . '.',
            '#tab=appointments',
            'fa-calendar-days'
        );

        // Patient Email
        if ($patient && $patient->user_email) {
            $p_content = '
            <p>Dear <strong>' . esc_html($pname) . '</strong>,</p>
            <p>Your appointment has been updated to a new time slot.</p>
            <div style="background-color: #EFF6FF; border-left: 4px solid #2C6CB1; border-radius: 4px; padding: 16px 20px; margin: 20px 0;">
                <p style="margin: 0 0 6px 0; color: #1E40AF; font-weight: 600;">NEW Schedule:</p>
                <p style="margin: 0; font-size: 16px; font-weight: 700; color: #1E3A8A;">' . esc_html($new_date . ' at ' . $new_time) . '</p>
                <p style="margin: 10px 0 0 0; font-size: 13px; color: #64748B;">Previous Time: <strike>' . esc_html($old_date . ' ' . $old_time) . '</strike></p>
            </div>
            <p>Provider: <strong>' . esc_html($dname) . '</strong> &bull; Reason: <strong>' . esc_html($appt['condition_name'] ?: 'Urgent Care') . '</strong></p>
            <p>If this new time does not work for you, you may adjust it at any time in your patient portal.</p>';

            $p_html = self::render_email_template(
                'Appointment Schedule Changed',
                'Your visit has been rescheduled to ' . $new_date . ' at ' . $new_time,
                $p_content,
                ['label' => 'View Updated Appointment', 'url' => home_url('/patient-dashboard/#tab=appointments')]
            );
            self::send_email_safe($patient->user_email, 'Appointment Rescheduled: ' . $new_date . ' at ' . $new_time, $p_html);
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
        if (!$appt) return false;

        $pid = (int)$appt['patient_id'];
        $did = (int)$appt['doctor_id'];

        $patient = get_userdata($pid);
        $doctor  = $did ? get_userdata($did) : null;

        $pname = $patient ? trim($patient->first_name . ' ' . $patient->last_name) ?: $patient->display_name : 'Patient';
        $dname = $doctor ? 'Dr. ' . (trim($doctor->first_name . ' ' . $doctor->last_name) ?: $doctor->display_name) : 'Attending Physician';

        $date = $appt['appt_date'];
        $time = $appt['appt_time'];
        $reason_text = $reason ?: 'Not specified';

        // Patient In-App
        self::add_dashboard_notification(
            $pid,
            'Consultation Cancelled: #' . $appt['appt_uid'],
            'Your appointment scheduled for ' . $date . ' at ' . $time . ' has been cancelled (' . $reason_text . ').',
            '#tab=appointments',
            'fa-calendar-xmark'
        );

        // Patient Email
        if ($patient && $patient->user_email) {
            $p_content = '
            <p>Dear <strong>' . esc_html($pname) . '</strong>,</p>
            <p>This email confirms that your telehealth consultation scheduled for <strong>' . esc_html($date . ' at ' . $time) . '</strong> with <strong>' . esc_html($dname) . '</strong> has been cancelled.</p>
            <div style="background-color: #FEF2F2; border-left: 4px solid #EF4444; border-radius: 4px; padding: 14px 18px; margin: 18px 0; color: #991B1B;">
                <strong>Reason:</strong> ' . esc_html($reason_text) . '
            </div>
            <p>If you still require medical attention, you are welcome to schedule a new consultation at your convenience.</p>';

            $p_html = self::render_email_template(
                'Appointment Cancelled',
                'Your consultation on ' . $date . ' has been cancelled.',
                $p_content,
                ['label' => 'Book Another Visit', 'url' => home_url('/patient-dashboard/#tab=questionnaire')]
            );
            self::send_email_safe($patient->user_email, 'Appointment Cancelled: #' . $appt['appt_uid'], $p_html);
        }

        // Doctor In-App
        if ($did && $doctor) {
            self::add_dashboard_notification(
                $did,
                'Appointment Cancelled (' . $pname . ')',
                $pname . ' cancelled their appointment for ' . $date . ' at ' . $time . ' (Reason: ' . $reason_text . ').',
                '#tab=doc-appointments',
                'fa-calendar-xmark',
                true
            );

            // Doctor Email
            if ($doctor->user_email) {
                $d_content = '
                <p>Hello <strong>' . esc_html($dname) . '</strong>,</p>
                <p>The appointment with patient <strong>' . esc_html($pname) . '</strong> scheduled for <strong>' . esc_html($date . ' at ' . $time) . '</strong> has been cancelled.</p>
                <div style="background-color: #FEF2F2; border-left: 4px solid #EF4444; border-radius: 4px; padding: 14px 18px; margin: 18px 0; color: #991B1B;">
                    <strong>Reason:</strong> ' . esc_html($reason_text) . '
                </div>
                <p>This time slot has been freed up on your calendar.</p>';

                $d_html = self::render_email_template(
                    'Appointment Cancelled',
                    'Appointment with ' . $pname . ' on ' . $date . ' has been cancelled.',
                    $d_content,
                    ['label' => 'View Doctor Dashboard', 'url' => home_url('/doctor-dashboard/#tab=doc-appointments')]
                );
                self::send_email_safe($doctor->user_email, 'Cancellation Notice: ' . $pname . ' (' . $date . ')', $d_html);
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

        // 2. Patient Email Reminder
        if ($p_send_email && $patient && $patient->user_email) {
            $p_content = '
            <p>Dear <strong>' . esc_html($pname) . '</strong>,</p>
            <p>This is a friendly reminder for your upcoming telehealth consultation with <strong>' . esc_html($dname) . '</strong>.</p>
            <div style="background-color: #FEF3C7; border-left: 4px solid #D97706; border-radius: 4px; padding: 16px 20px; margin: 20px 0; color: #92400E;">
                <p style="margin: 0 0 6px 0; font-size: 15px; font-weight: 700;">Scheduled Consultation:</p>
                <p style="margin: 0; font-size: 16px; font-weight: 700; color: #78350F;">' . esc_html($date . ' at ' . $time) . '</p>
                <p style="margin: 6px 0 0 0; font-size: 13px;">Reason: ' . esc_html($cond) . '</p>
            </div>
            <p><strong>Preparation Checklist:</strong></p>
            <ul style="padding-left: 20px; margin: 10px 0; color: #475569;">
                <li>Ensure you are in a quiet, well-lit private space</li>
                <li>Verify your internet, camera, and microphone before the visit</li>
                <li>Have a list of your current medications and symptoms ready</li>
            </ul>
            <p>Click below to join the video room when your consultation time arrives:</p>';

            $p_html = self::render_email_template(
                'Upcoming Consultation Reminder',
                'Reminder: Your appointment is on ' . $date . ' at ' . $time,
                $p_content,
                ['label' => 'Join Video Room', 'url' => $join_url]
            );
            self::send_email_safe($patient->user_email, 'Appointment Reminder: ' . $date . ' at ' . $time . ' with ' . $dname, $p_html);
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
        $today = current_time('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day', current_time('timestamp')));

        $upcoming = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE (appt_date = %s OR appt_date = %s) 
             AND status IN ('Confirmed', 'Scheduled', 'In-Queue') 
             AND (notes NOT LIKE '%[Auto-Reminder sent%') 
             AND is_deleted = 0",
            $today, $tomorrow
        ), ARRAY_A);

        if (empty($upcoming)) {
            return 0;
        }

        $count = 0;
        foreach ($upcoming as $apt) {
            self::send_appointment_reminder($apt['appt_uid'], 'system');

            $tag = " [Auto-Reminder sent on " . current_time('Y-m-d H:i:s') . "]";
            $new_notes = trim(($apt['notes'] ?? '') . $tag);
            $wpdb->update($table, ['notes' => $new_notes], ['appt_uid' => $apt['appt_uid']]);
            $count++;
        }

        return $count;
    }
}
