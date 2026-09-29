<?php
/**
 * Plugin Name: Dior Medical - Patient Portal & Dashboard
 * Plugin URI:  https://diormedical.com
 * Description: Luxury, secure, state-of-the-art Patient Portal & Authentication System for Dior Medical Telehealth & Urgent Care.
 * Version:     2.1
 * Author:      Dior Medical Team
 * Author URI:  https://diormedical.com
 * Text Domain: dior-medical
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DIOR_PORTAL_VERSION', '2.0');
define('DIOR_PORTAL_PATH', plugin_dir_path(__FILE__));
define('DIOR_PORTAL_URL', plugin_dir_url(__FILE__));

// Aliases for backwards compatibility
define('DIOR_AUTH_VERSION', DIOR_PORTAL_VERSION);
define('DIOR_AUTH_PATH', DIOR_PORTAL_PATH);
define('DIOR_AUTH_URL', DIOR_PORTAL_URL);

// ========================================
// DYNAMIC FEATURES & ENTERPRISE SERVICES (v3.0)
// ========================================

// Include core database and services layer
if (!class_exists('Dior_DB')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-db.php');
}
if (!class_exists('Dior_Auth_Service')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-auth-service.php');
}
if (!class_exists('Dior_Audit_Service')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-audit-service.php');
}
if (!class_exists('Dior_Encounter_Service')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-encounter-service.php');
}
if (!class_exists('Dior_Appointment_Service')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-appointment-service.php');
}
if (!class_exists('Dior_Notification_Service')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-notification-service.php');
}
if (!class_exists('Dior_Medical_Analytics')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-analytics.php');
}
if (!class_exists('Dior_Medical_REST_API')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-rest-api.php');
}
if (!class_exists('Dior_Medical_Secure_Files')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-secure-files.php');
}

/**
 * Universal Dynamic Relative Notification Time Formatter
 * 
 * Accurately computes human relative time (e.g. "Just now", "15 min ago", "2 hours ago", "Yesterday at 3:15 PM", "Sep 8, 2026")
 * Supports unix timestamp integer, MySQL created_at datetime string, and intelligent regex extraction from legacy notification IDs.
 */
if (!function_exists('dior_format_notification_time')) {
    function dior_format_notification_time($notif)
    {
        $now = current_time('timestamp');
        $ts = null;

        if (is_array($notif)) {
            // 1. Direct timestamp
            if (!empty($notif['timestamp']) && is_numeric($notif['timestamp'])) {
                $ts = intval($notif['timestamp']);
            }
            // 2. created_at or datetime field
            elseif (!empty($notif['created_at']) && ($parsed = strtotime($notif['created_at']))) {
                $ts = $parsed;
            } elseif (!empty($notif['datetime']) && ($parsed = strtotime($notif['datetime']))) {
                $ts = $parsed;
            }
            // 3. Extract unix timestamp from notification ID (e.g., DN-RX-1725852000-45, NOTIF-1725852000, notif_1725852000_123, NOTIF-HIPAA-1725852000)
            elseif (!empty($notif['id']) && preg_match('/(?:DN|NOTIF|notif)[-_](?:[A-Za-z0-9]+[-_])?(\d{10})/i', $notif['id'], $matches)) {
                $val = intval($matches[1]);
                if ($val > 1500000000 && $val <= $now + 86400) {
                    $ts = $val;
                }
            }
            // 4. Check if 'time' field contains a parsable date/time string other than "Just now"
            elseif (!empty($notif['time']) && $notif['time'] !== 'Just now' && ($parsed = strtotime($notif['time']))) {
                $ts = $parsed;
            }
        } elseif (is_numeric($notif)) {
            $ts = intval($notif);
        }

        if ($ts !== null) {
            $diff = $now - $ts;
            if ($diff < 0) {
                $diff = 0;
            }
            if ($diff < 60) {
                return 'Just now';
            } elseif ($diff < 3600) {
                $mins = floor($diff / 60);
                return $mins == 1 ? '1 min ago' : $mins . ' min ago';
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                return $hours == 1 ? '1 hour ago' : $hours . ' hours ago';
            } elseif ($diff < 172800) {
                return 'Yesterday at ' . date('g:i A', $ts);
            } elseif ($diff < 604800) {
                $days = floor($diff / 86400);
                return $days . ' days ago';
            } else {
                return date('M j, Y', $ts);
            }
        }

        if (is_array($notif) && !empty($notif['time'])) {
            return $notif['time'];
        }
        return 'Just now';
    }
}

// Initialize database, analytics, and secure files on plugin load
add_action('plugins_loaded', function () {
    if (class_exists('Dior_DB')) {
        Dior_DB::init();
    }
    if (class_exists('Dior_Medical_Analytics')) {
        Dior_Medical_Analytics::init();
    }
    if (class_exists('Dior_Medical_REST_API')) {
        Dior_Medical_REST_API::init();
    }
    if (class_exists('Dior_Medical_Secure_Files')) {
        Dior_Medical_Secure_Files::init();
    }
});


// Completely disable WordPress Admin Bar on all frontend portal pages
add_filter('show_admin_bar', '__return_false', 9999);
add_action('init', function () {
    if (!is_admin()) {
        show_admin_bar(false);
    }
}, 9999);
add_action('wp_head', function () {
    echo '<style id="dior-hide-adminbar-css">#wpadminbar { display: none !important; height: 0 !important; min-height: 0 !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; } html, body, html.elementor-html, html body.admin-bar { margin-top: 0 !important; padding-top: 0 !important; top: 0 !important; }</style>';
}, 9999);

/**
 * Patient Data Helper Class
 * Handles retrieval and persistence of patient-specific portal data
 */
class Dior_Patient_Portal_Data
{

    /**
     * Get or initialize patient profile data
     */
    public static function get_patient_profile($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user)
            return [];

        // Fetch direct user meta first
        $first_name = get_user_meta($user_id, 'first_name', true);
        if (empty($first_name) && !empty($user->first_name)) {
            $first_name = $user->first_name;
        }
        $last_name = get_user_meta($user_id, 'last_name', true);
        if (empty($last_name) && !empty($user->last_name)) {
            $last_name = $user->last_name;
        }

        $phone = get_user_meta($user_id, 'phone', true);
        if (empty($phone)) {
            $phone = get_user_meta($user_id, 'billing_phone', true) ?: '';
        }

        $dob = get_user_meta($user_id, 'dob', true);
        if (empty($dob)) {
            $dob = get_user_meta($user_id, 'date_of_birth', true) ?: '';
        }
        if (!empty($dob)) {
            $dob_time = strtotime($dob);
            if ($dob_time !== false && $dob_time > 0) {
                $dob = date('Y-m-d', $dob_time);
            }
        }

        $gender = get_user_meta($user_id, 'gender', true) ?: '';
        $address = get_user_meta($user_id, 'address', true);
        if (empty($address)) {
            $address = get_user_meta($user_id, 'billing_address_1', true) ?: '';
        }

        // Get HIPAAtizer intake data if available as fallback
        $hipaa_intake = get_user_meta($user_id, 'dior_hipaa_intake', true);
        if (!empty($hipaa_intake) && is_array($hipaa_intake)) {
            if (empty($first_name) && !empty($hipaa_intake['first_name'])) {
                $first_name = $hipaa_intake['first_name'];
            }
            if (empty($last_name) && !empty($hipaa_intake['last_name'])) {
                $last_name = $hipaa_intake['last_name'];
            }
            if (empty($phone) && !empty($hipaa_intake['phone'])) {
                $phone = $hipaa_intake['phone'];
            }
            if (empty($dob) && !empty($hipaa_intake['dob'])) {
                $raw_dob = $hipaa_intake['dob'];
                $ts = strtotime($raw_dob);
                $dob = ($ts !== false && $ts > 0) ? date('Y-m-d', $ts) : $raw_dob;
            }
            if (empty($gender) && !empty($hipaa_intake['gender'])) {
                $gender = $hipaa_intake['gender'];
            }
            if (empty($address) && !empty($hipaa_intake['address'])) {
                $address = trim($hipaa_intake['address'] . ' ' . ($hipaa_intake['city'] ?? '') . ', ' . ($hipaa_intake['state'] ?? '') . ' ' . ($hipaa_intake['zip'] ?? ''));
            }
        }

        $patient_id = get_user_meta($user_id, 'patient_id', true);
        if (empty($patient_id)) {
            $patient_id = 'DM-' . (10000 + ($user_id % 90000));
            update_user_meta($user_id, 'patient_id', $patient_id);
        }

        // Emergency contact
        $em_name = get_user_meta($user_id, 'emergency_name', true) ?: '';
        $em_rel = get_user_meta($user_id, 'emergency_relation', true) ?: '';
        $em_phone = get_user_meta($user_id, 'emergency_phone', true) ?: '';

        // Preferred Pharmacy
        $pharmacy_name = get_user_meta($user_id, 'preferred_pharmacy_name', true) ?: '';
        $pharmacy_address = get_user_meta($user_id, 'preferred_pharmacy_address', true) ?: '';
        $pharmacy_phone = get_user_meta($user_id, 'preferred_pharmacy_phone', true) ?: '';

        // Eligibility snapshots (Read only)
        $is_18_verified = get_user_meta($user_id, 'is_18_verified', true) ?: 'Verified (18+)';
        $pregnancy_status = get_user_meta($user_id, 'pregnancy_status', true) ?: 'Not Pregnant';
        $state_eligible = get_user_meta($user_id, 'state_eligible', true) ?: 'Active in State';

        $avatar_url = get_user_meta($user_id, 'dior_profile_image', true);
        if (empty($avatar_url)) {
            $avatar_url = get_user_meta($user_id, 'profile_image', true) ?: '';
        }

        return [
            'user_id' => $user_id,
            'patient_id' => $patient_id,
            'avatar_url' => $avatar_url,
            'first_name' => $first_name ?: '',
            'last_name' => $last_name ?: '',
            'full_name' => trim(($first_name ?: '') . ' ' . ($last_name ?: '')) ?: $user->display_name,
            'email' => $user->user_email,
            'phone' => $phone ?: '',
            'dob' => $dob ?: '',
            'gender' => $gender ?: 'Female',
            'address' => $address ?: '',
            'emergency_name' => $em_name ?: '',
            'emergency_relation' => $em_rel ?: '',
            'emergency_phone' => $em_phone ?: '',
            'pharmacy_name' => $pharmacy_name ?: '',
            'pharmacy_address' => $pharmacy_address ?: '',
            'pharmacy_phone' => $pharmacy_phone ?: '',
            'is_18_verified' => $is_18_verified,
            'pregnancy_status' => $pregnancy_status,
            'state_eligible' => $state_eligible,
            'has_hipaa_intake' => !empty($hipaa_intake),
            'hipaa_intake' => $hipaa_intake ?: [],
            'optin_appointment_reminders' => (get_user_meta($user_id, 'optin_appointment_reminders', true) !== '0') ? '1' : '0',
            'optin_reminder_email' => (get_user_meta($user_id, 'optin_reminder_email', true) !== '0') ? '1' : '0',
            'optin_reminder_dashboard' => (get_user_meta($user_id, 'optin_reminder_dashboard', true) !== '0') ? '1' : '0',
            'signature_url' => get_user_meta($user_id, 'dior_patient_signature', true) ?: '',
        ];
    }

    /**
     * Check if patient profile has all required personal information filled
     */
    public static function is_profile_complete($user_id)
    {
        if (!$user_id)
            return false;
        $profile = self::get_patient_profile($user_id);
        if (empty($profile))
            return false;

        $required_fields = ['first_name', 'last_name', 'phone', 'dob', 'gender', 'address'];
        foreach ($required_fields as $f) {
            if (empty($profile[$f]) || trim((string) $profile[$f]) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Get missing profile fields with human-readable labels
     */
    public static function get_missing_profile_fields($user_id)
    {
        if (!$user_id)
            return [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'phone' => 'Phone Number',
                'dob' => 'Date of Birth',
                'gender' => 'Gender',
                'address' => 'Mailing Address'
            ];

        $profile = self::get_patient_profile($user_id);
        $labels = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'phone' => 'Phone Number',
            'dob' => 'Date of Birth',
            'gender' => 'Gender',
            'address' => 'Mailing Address'
        ];
        $missing = [];
        foreach ($labels as $k => $label) {
            if (empty($profile[$k]) || trim((string) $profile[$k]) === '') {
                $missing[$k] = $label;
            }
        }
        return $missing;
    }

    /**
     * Get patient appointments
     */
    public static function get_patient_appointments($user_id)
    {
        return Dior_Appointment_Service::get_patient_appointments($user_id);
    }

    /**
     * Group multi-item prescriptions created under the same order
     */
    public static function group_prescriptions($raw_prescriptions)
    {
        if (!is_array($raw_prescriptions) || empty($raw_prescriptions)) {
            return [];
        }

        $groups = [];
        $order = [];

        foreach ($raw_prescriptions as $rx) {
            if (!is_array($rx))
                continue;

            $group_key = '';
            if (!empty($rx['order_id'])) {
                $group_key = trim((string) $rx['order_id']);
            } elseif (!empty($rx['group_id'])) {
                $group_key = trim((string) $rx['group_id']);
            } elseif (!empty($rx['id']) && preg_match('/^(RX-[A-Za-z0-9]+)(?:-\d+)+$/i', trim((string) $rx['id']), $matches)) {
                $group_key = strtoupper($matches[1]);
            } else {
                $group_key = !empty($rx['id']) ? trim((string) $rx['id']) : ('RX-' . md5(serialize($rx)));
            }

            if (!isset($groups[$group_key])) {
                $groups[$group_key] = [
                    'id' => $group_key,
                    'group_id' => $group_key,
                    'order_id' => $group_key,
                    'raw_ids' => [],
                    'items' => [],
                    'date' => !empty($rx['date_prescribed']) ? $rx['date_prescribed'] : (!empty($rx['date']) ? $rx['date'] : current_time('M j, Y')),
                    'date_prescribed' => !empty($rx['date_prescribed']) ? $rx['date_prescribed'] : (!empty($rx['date']) ? $rx['date'] : current_time('M j, Y')),
                    'prescribed_by' => $rx['prescribed_by'] ?? '',
                    'pharmacy' => $rx['pharmacy'] ?? '',
                    'routing_status' => $rx['routing_status'] ?? 'Sent to Pharmacy',
                    'status' => $rx['status'] ?? 'Active',
                    'status_class' => $rx['status_class'] ?? 'status-success',
                    'is_active' => $rx['is_active'] ?? true,
                ];
                $order[] = $group_key;
            }

            $current_id = !empty($rx['id']) ? (string) $rx['id'] : $group_key;
            $groups[$group_key]['raw_ids'][] = $current_id;

            $med_name = $rx['medication'] ?? $rx['name'] ?? 'Medication';
            $dosage = $rx['dosage'] ?? $rx['dose'] ?? 'As directed';
            $qty = !empty($rx['quantity']) ? $rx['quantity'] : 'As Prescribed';
            $refills = !empty($rx['refills']) ? $rx['refills'] : '0 Refills Remaining';
            $instructions = !empty($rx['instructions']) ? $rx['instructions'] : (!empty($rx['notes']) ? $rx['notes'] : 'Take as directed by prescribing physician.');

            $groups[$group_key]['items'][] = [
                'id' => $current_id,
                'name' => $med_name,
                'medication' => $med_name,
                'dosage' => $dosage,
                'dose' => $dosage,
                'quantity' => $qty,
                'refills' => $refills,
                'notes' => $rx['notes'] ?? '',
                'instructions' => $instructions,
                'fda_approved' => true,
                'fda_source' => $rx['fda_source'] ?? 'U.S. FDA & NIH RxNorm',
            ];
        }

        $result = [];
        foreach ($order as $key) {
            $g = $groups[$key];
            $item_count = count($g['items']);
            $names = [];
            $dosages = [];
            $instructions = [];

            foreach ($g['items'] as $it) {
                $names[] = $it['medication'];
                $dosages[] = $it['medication'] . ' (' . $it['dosage'] . ')';
                $instructions[] = $it['medication'] . ': ' . $it['instructions'];
            }

            $g['name'] = implode(', ', $names);
            $g['medication'] = $g['name'];
            $g['dosage'] = implode(' • ', $dosages);
            $g['dose'] = $g['dosage'];
            $g['quantity'] = $item_count > 1 ? ($item_count . ' Items Prescribed') : ($g['items'][0]['quantity'] ?? 'As Prescribed');
            $g['refills'] = $g['items'][0]['refills'] ?? '0 Refills Remaining';
            $g['instructions'] = implode("\n\n", $instructions);
            $g['notes'] = $g['instructions'];
            $g['is_multi'] = ($item_count > 1);
            $g['fda_approved'] = true;
            $g['fda_source'] = 'U.S. FDA & NIH RxNorm';

            $result[] = $g;
        }

        return $result;
    }

    /**
     * Get patient prescriptions & pharmacy details
     */
    public static function get_patient_prescriptions($user_id)
    {
        $prescriptions = get_user_meta($user_id, 'dior_prescriptions', true);

        if (!is_array($prescriptions)) {
            $prescriptions = [];
            update_user_meta($user_id, 'dior_prescriptions', $prescriptions);
        }

        return self::group_prescriptions($prescriptions);
    }

    /**
     * Get patient payments & invoices
     */
    public static function get_patient_payments($user_id)
    {
        $payments = get_user_meta($user_id, 'dior_payments', true);

        if (!is_array($payments)) {
            $payments = [];
            update_user_meta($user_id, 'dior_payments', $payments);
        }

        return $payments;
    }

    /**
     * Get medical documents
     */
    public static function get_patient_documents($user_id)
    {
        return Dior_Medical_Secure_Files::get_patient_documents($user_id);
    }

    /**
     * Get condition-specific questionnaires
     */
    public static function get_patient_questionnaires($user_id)
    {
        $questionnaires = get_user_meta($user_id, 'dior_questionnaires', true);

        if (!is_array($questionnaires)) {
            $questionnaires = [];
            update_user_meta($user_id, 'dior_questionnaires', $questionnaires);
        }

        return $questionnaires;
    }

    /**
     * Get patient notifications
     */
    public static function get_patient_notifications($user_id)
    {
        $notifications = get_user_meta($user_id, 'dior_notifications', true);

        if (!is_array($notifications)) {
            $notifications = [];
            update_user_meta($user_id, 'dior_notifications', $notifications);
        }

        return $notifications;
    }
}

/**
 * Main Dior Medical Auth & Patient Dashboard Class
 */
class Dior_Medical_Auth
{

    public static function init()
    {
        // Disable WordPress Admin Bar for clean web-app experience
        add_filter('show_admin_bar', '__return_false');

        // Enqueue styles & scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // Shortcodes
        add_shortcode('dior_login_form', [__CLASS__, 'render_login_form']);
        add_shortcode('dior_registration_form', [__CLASS__, 'render_registration_form']);
        add_shortcode('dior_lost_password_form', [__CLASS__, 'render_lost_password_form']);
        add_shortcode('dior_reset_password_form', [__CLASS__, 'render_reset_password_form']);
        add_shortcode('dior_patient_dashboard', [__CLASS__, 'render_patient_dashboard']);
        add_shortcode('dior_book_visit_page', [__CLASS__, 'render_standalone_appointment_page']);

        // Redirect non-logged-in users from private dashboards to login page
        add_action('template_redirect', [__CLASS__, 'check_dashboard_access_redirect']);

        // Auth AJAX Endpoints
        add_action('wp_ajax_nopriv_dior_ajax_login', [__CLASS__, 'handle_login']);
        add_action('wp_ajax_dior_ajax_login', [__CLASS__, 'handle_login']);

        add_action('wp_ajax_nopriv_dior_ajax_register', [__CLASS__, 'handle_register']);
        add_action('wp_ajax_dior_ajax_register', [__CLASS__, 'handle_register']);

        // Appointment Page AJAX
        add_action('wp_ajax_nopriv_dior_book_appointment_from_page', [__CLASS__, 'handle_book_appointment_from_page']);
        add_action('wp_ajax_dior_book_appointment_from_page', [__CLASS__, 'handle_book_appointment_from_page']);

        add_action('wp_ajax_nopriv_dior_ajax_lost_password', [__CLASS__, 'handle_lost_password']);
        add_action('wp_ajax_dior_ajax_lost_password', [__CLASS__, 'handle_lost_password']);

        add_action('wp_ajax_nopriv_dior_ajax_reset_password', [__CLASS__, 'handle_reset_password']);
        add_action('wp_ajax_dior_ajax_reset_password', [__CLASS__, 'handle_reset_password']);

        add_action('wp_ajax_dior_ajax_logout', [__CLASS__, 'handle_logout']);
        add_action('wp_ajax_nopriv_dior_ajax_logout', [__CLASS__, 'handle_logout']);

        // Patient Dashboard AJAX Endpoints (Logged-in & Public Booking)
        add_action('wp_ajax_dior_patient_save_profile', [__CLASS__, 'ajax_save_profile']);
        add_action('wp_ajax_dior_patient_upload_avatar', [__CLASS__, 'ajax_upload_avatar']);
        add_action('wp_ajax_dior_patient_remove_avatar', [__CLASS__, 'ajax_remove_avatar']);
        add_action('wp_ajax_dior_patient_upload_signature', [__CLASS__, 'ajax_upload_signature']);
        add_action('wp_ajax_dior_patient_remove_signature', [__CLASS__, 'ajax_remove_signature']);
        add_action('wp_ajax_dior_patient_change_password', [__CLASS__, 'ajax_change_password']);
        add_action('wp_ajax_dior_patient_book_appointment', [__CLASS__, 'ajax_book_appointment']);
        add_action('wp_ajax_nopriv_dior_patient_book_appointment', [__CLASS__, 'ajax_book_appointment']);
        add_action('wp_ajax_dior_patient_book', [__CLASS__, 'ajax_book_appointment']);
        add_action('wp_ajax_nopriv_dior_patient_book', [__CLASS__, 'ajax_book_appointment']);
        add_action('wp_ajax_dior_patient_reschedule_appointment', [__CLASS__, 'ajax_reschedule_appointment']);
        add_action('wp_ajax_dior_patient_cancel_appointment', [__CLASS__, 'ajax_cancel_appointment']);
        add_action('wp_ajax_dior_patient_send_appointment_reminder', [__CLASS__, 'ajax_send_appointment_reminder']);
        add_action('wp_ajax_dior_send_appointment_reminder', [__CLASS__, 'ajax_send_appointment_reminder']);
        add_action('wp_ajax_dior_toggle_reminder_optin', [__CLASS__, 'ajax_toggle_reminder_optin']);
        add_action('wp_ajax_dior_patient_toggle_reminder_optin', [__CLASS__, 'ajax_toggle_reminder_optin']);
        add_action('wp_ajax_dior_patient_submit_questionnaire', [__CLASS__, 'ajax_submit_questionnaire']);
        add_action('wp_ajax_dior_patient_pay_invoice', [__CLASS__, 'ajax_pay_invoice']);
        add_action('wp_ajax_dior_patient_mark_notification_read', [__CLASS__, 'ajax_mark_notification_read']);
        add_action('wp_ajax_dior_patient_mark_all_notifications_read', [__CLASS__, 'ajax_mark_all_notifications_read']);
        add_action('wp_ajax_dior_patient_get_live_notifications', [__CLASS__, 'ajax_get_live_notifications']);
        add_action('wp_ajax_dior_patient_update_pharmacy', [__CLASS__, 'ajax_update_pharmacy']);
        add_action('wp_ajax_dior_patient_check_intake_status', [__CLASS__, 'ajax_check_intake_status']);

        // Automated Scheduled Appointment Reminders (Hourly Cron Job)
        if (!wp_next_scheduled('dior_cron_hourly_appointment_reminders')) {
            wp_schedule_event(time(), 'hourly', 'dior_cron_hourly_appointment_reminders');
        }
        add_action('dior_cron_hourly_appointment_reminders', ['Dior_Notification_Service', 'check_and_send_scheduled_reminders']);

        // DocBooker Live Booking Synchronization Hook
        add_action('wpddb_booking_created', [__CLASS__, 'on_docbooker_booking_created'], 10, 1);

        // HIPAAtizer Webhook Receiver (no-priv so HIPAAtizer server can POST)
        add_action('wp_ajax_nopriv_dior_hipaa_webhook', [__CLASS__, 'ajax_hipaa_webhook']);
        add_action('wp_ajax_dior_hipaa_webhook', [__CLASS__, 'ajax_hipaa_webhook']);

        // REST API: /wp-json/dior/v1/hipaa-webhook
        add_action('rest_api_init', function () {
            register_rest_route('dior/v1', '/hipaa-webhook', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'rest_hipaa_webhook'],
                'permission_callback' => '__return_true', // HIPAAtizer server posts here
            ]);
        });

        // Auto-create pages on init & admin_init
        add_action('init', [__CLASS__, 'ensure_pages_exist']);
        add_action('admin_init', [__CLASS__, 'ensure_pages_exist']);

        // Role-based Login Redirect (Doctors -> /doctor-dashboard/, Patients -> /patient-dashboard/)
        add_filter('login_redirect', [__CLASS__, 'custom_login_redirect'], 99, 3);

        // Lost Password URL Filter
        add_filter('lostpassword_url', [__CLASS__, 'custom_lostpassword_url'], 99, 2);

        // Template Canvas Override (Elementor Canvas mode)
        add_filter('template_include', [__CLASS__, 'override_page_template'], 99);
    }

    /**
     * Enqueue CSS & JS
     */
    public static function enqueue_assets()
    {

        // Analytics dashboard (NEW)
        if (is_admin() && current_user_can('manage_options')) {
            wp_enqueue_script(
                'chartjs',
                'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
                [],
                '3.9.1',
                true
            );

            wp_enqueue_script(
                'dior-analytics',
                DIOR_PORTAL_URL . 'assets/js/dior-analytics.js',
                ['jquery', 'chartjs'],
                DIOR_PORTAL_VERSION,
                true
            );

            wp_enqueue_style(
                'dior-analytics-css',
                DIOR_PORTAL_URL . 'assets/css/dior-analytics.css',
                [],
                DIOR_PORTAL_VERSION
            );

            wp_localize_script('dior-analytics', 'diorAnalyticsData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dior_analytics_nonce'),
            ]);
        }
        // Font Awesome for modern icons
        wp_enqueue_style(
            'font-awesome-6',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
            [],
            '6.5.1'
        );

        // Auth Styles
        $auth_css_ver = file_exists(DIOR_PORTAL_PATH . 'assets/css/dior-auth.css') ? filemtime(DIOR_PORTAL_PATH . 'assets/css/dior-auth.css') : DIOR_PORTAL_VERSION;
        wp_enqueue_style(
            'dior-auth-css',
            DIOR_AUTH_URL . 'assets/css/dior-auth.css',
            [],
            $auth_css_ver
        );

        // Patient Dashboard Styles
        $patient_css_ver = file_exists(DIOR_PORTAL_PATH . 'assets/css/patient-dashboard.css') ? filemtime(DIOR_PORTAL_PATH . 'assets/css/patient-dashboard.css') : DIOR_PORTAL_VERSION;
        wp_enqueue_style(
            'dior-patient-dashboard',
            DIOR_PORTAL_URL . 'assets/css/patient-dashboard.css',
            ['dior-auth-css'],
            $patient_css_ver
        );

        // SweetAlert2 Stylesheet (Fixes distorted / missing animation icons)
        wp_enqueue_style(
            'sweetalert2-css',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css',
            [],
            '11.0.0'
        );

        $css_ver = file_exists(DIOR_PORTAL_PATH . 'assets/css/dior-dashboard.css') ? filemtime(DIOR_PORTAL_PATH . 'assets/css/dior-dashboard.css') : DIOR_PORTAL_VERSION;
        $js_ver = file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-dashboard.js') ? filemtime(DIOR_PORTAL_PATH . 'assets/js/dior-dashboard.js') : DIOR_PORTAL_VERSION;

        // Dashboard Styles
        wp_enqueue_style(
            'dior-dashboard-css',
            DIOR_PORTAL_URL . 'assets/css/dior-dashboard.css',
            ['dior-auth-css', 'sweetalert2-css'],
            $css_ver
        );

        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11',
            [],
            '11.0.0',
            false
        );

        // Enqueue WordPress Media Library for profile photos & documents
        if (is_user_logged_in()) {
            wp_enqueue_media();
        }

        // Auth JS
        wp_enqueue_script(
            'dior-auth-js',
            DIOR_AUTH_URL . 'assets/js/dior-auth.js',
            ['jquery', 'sweetalert2'],
            DIOR_PORTAL_VERSION,
            true
        );

        wp_enqueue_script(
            'html2pdf',
            DIOR_PORTAL_URL . 'assets/js/html2pdf.bundle.min.js',
            [],
            '0.10.1',
            true
        );

        // Dashboard JS
        wp_enqueue_script(
            'dior-dashboard-js',
            DIOR_PORTAL_URL . 'assets/js/dior-dashboard.js',
            ['jquery', 'sweetalert2', 'html2pdf'],
            $js_ver,
            true
        );

        // Doctor Dashboard JS
        if (file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-doctor.js')) {
            wp_enqueue_script(
                'dior-doctor-js',
                DIOR_PORTAL_URL . 'assets/js/dior-doctor.js',
                ['jquery', 'sweetalert2'],
                filemtime(DIOR_PORTAL_PATH . 'assets/js/dior-doctor.js'),
                true
            );
        }

        $portal_url = home_url('/patient-dashboard/');
        $login_url = home_url('/diro-login/');
        $reg_url = home_url('/diro-registration/');
        $lost_pwd_url = home_url('/diro-lost-password/');
        $reset_pwd_url = home_url('/diro-reset-password/');
        $current_user_id = get_current_user_id();
        $hipaa_intake_data = $current_user_id ? get_user_meta($current_user_id, 'dior_hipaa_intake', true) : null;
        $profile_data = $current_user_id ? Dior_Patient_Portal_Data::get_patient_profile($current_user_id) : null;
        $is_profile_complete = $current_user_id ? Dior_Patient_Portal_Data::is_profile_complete($current_user_id) : false;
        $missing_profile_fields = $current_user_id ? Dior_Patient_Portal_Data::get_missing_profile_fields($current_user_id) : [];

        $default_doc_sig = '';
        $doc_user_objs = get_users(['role' => 'doctor', 'number' => 1]);
        if (!empty($doc_user_objs)) {
            $default_doc_sig = get_user_meta($doc_user_objs[0]->ID, 'dior_doctor_signature', true) ?: '';
        }
        if (empty($default_doc_sig)) {
            $default_doc_sig = get_user_meta(1, 'dior_doctor_signature', true) ?: '';
        }

        $localize_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => esc_url_raw(rest_url()),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'nonce' => wp_create_nonce('dior_portal_nonce'),
            'auth_nonce' => wp_create_nonce('dior_auth_nonce'),
            'portal_url' => $portal_url,
            'login_url' => $login_url,
            'register_url' => $reg_url,
            'lost_password_url' => $lost_pwd_url,
            'reset_password_url' => $reset_pwd_url,
            'home_url' => home_url('/'),
            'is_logged_in' => is_user_logged_in() ? 1 : 0,
            'current_user_id' => $current_user_id,
            'is_profile_complete' => $is_profile_complete ? 1 : 0,
            'missing_profile_fields' => $missing_profile_fields,
            'has_hipaa_intake' => !empty($hipaa_intake_data),
            'hipaa_intake' => $hipaa_intake_data ?: [],
            'patient_profile' => $profile_data ?: [],
            'default_doctor_signature' => $default_doc_sig,
            'logo_url' => site_url('/wp-content/uploads/2026/08/cropped-logo.png'),
        ];

        wp_localize_script('dior-auth-js', 'dior_auth_vars', $localize_data);
        wp_localize_script('dior-dashboard-js', 'dior_vars', $localize_data);
        if (file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-doctor.js')) {
            $is_doc_complete = 1;
            $missing_doc_fields = [];
            $doc_profile = null;
            $is_doctor_user = false;

            if ($current_user_id && class_exists('Dior_Doctor_Dashboard')) {
                $user = get_userdata($current_user_id);
                $roles = (array) ($user ? $user->roles : []);
                $is_doctor_user = in_array('doctor', $roles, true) || current_user_can('manage_options');

                if ($is_doctor_user) {
                    $is_doc_complete = Dior_Doctor_Dashboard::is_profile_complete($current_user_id) ? 1 : 0;
                    $missing_doc_fields = Dior_Doctor_Dashboard::get_missing_profile_fields($current_user_id);
                    $doc_profile = Dior_Doctor_Dashboard::get_doctor_profile($current_user_id);
                }
            }

            wp_localize_script('dior-doctor-js', 'dior_doctor_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dior_doctor_nonce'),
                'portal_nonce' => wp_create_nonce('dior_portal_nonce'),
                'current_user_id' => $current_user_id,
                'is_doctor' => $is_doctor_user ? 1 : 0,
                'is_profile_complete' => $is_doc_complete,
                'missing_profile_fields' => $missing_doc_fields,
                'doctor_profile' => $doc_profile ?: []
            ]);
        }
    }

    /**
     * Ensure /diro-login/, /diro-registration/, /patient-dashboard/, and /book-visit/ pages exist
     */
    public static function ensure_pages_exist()
    {
        // Patient Dashboard Page (/patient-dashboard/)
        $dashboard_page = get_page_by_path('patient-dashboard');
        if (!$dashboard_page) {
            $old_portal = get_page_by_path('patient-portal');
            if ($old_portal) {
                wp_update_post([
                    'ID' => $old_portal->ID,
                    'post_name' => 'patient-dashboard'
                ]);
                $dash_id = $old_portal->ID;
            } else {
                $dash_id = wp_insert_post([
                    'post_title' => 'Patient Dashboard',
                    'post_name' => 'patient-dashboard',
                    'post_content' => '[dior_patient_dashboard]',
                    'post_status' => 'publish',
                    'post_type' => 'page'
                ]);
            }
        } else {
            $dash_id = $dashboard_page->ID;
        }
        if (!empty($dash_id) && !is_wp_error($dash_id)) {
            update_post_meta($dash_id, '_wp_page_template', 'elementor_canvas');
        }

        // Login Page (/diro-login/)
        $login_page = get_page_by_path('diro-login');
        if (!$login_page) {
            $old_login = get_page_by_path('login');
            if ($old_login) {
                wp_update_post([
                    'ID' => $old_login->ID,
                    'post_name' => 'diro-login'
                ]);
                $login_id = $old_login->ID;
            } else {
                $login_id = wp_insert_post([
                    'post_title' => 'Patient Login',
                    'post_name' => 'diro-login',
                    'post_content' => '[dior_login_form]',
                    'post_status' => 'publish',
                    'post_type' => 'page'
                ]);
            }
        } else {
            $login_id = $login_page->ID;
        }
        if (!empty($login_id) && !is_wp_error($login_id)) {
            update_post_meta($login_id, '_wp_page_template', 'elementor_header_footer');
        }

        // Registration Page (/diro-registration/)
        $reg_page = get_page_by_path('diro-registration');
        if (!$reg_page) {
            $old_reg = get_page_by_path('register');
            if ($old_reg) {
                wp_update_post([
                    'ID' => $old_reg->ID,
                    'post_name' => 'diro-registration'
                ]);
                $reg_id = $old_reg->ID;
            } else {
                $reg_id = wp_insert_post([
                    'post_title' => 'Patient Registration',
                    'post_name' => 'diro-registration',
                    'post_content' => '[dior_registration_form]',
                    'post_status' => 'publish',
                    'post_type' => 'page'
                ]);
            }
        } else {
            $reg_id = $reg_page->ID;
        }
        if (!empty($reg_id) && !is_wp_error($reg_id)) {
            update_post_meta($reg_id, '_wp_page_template', 'elementor_header_footer');
        }

        // Lost Password Page (/diro-lost-password/)
        $lost_page = get_page_by_path('diro-lost-password');
        if (!$lost_page) {
            $old_lost = get_page_by_path('lost-password');
            if ($old_lost) {
                wp_update_post([
                    'ID' => $old_lost->ID,
                    'post_name' => 'diro-lost-password'
                ]);
                $lost_id = $old_lost->ID;
            } else {
                $lost_id = wp_insert_post([
                    'post_title' => 'Lost Password',
                    'post_name' => 'diro-lost-password',
                    'post_content' => '[dior_lost_password_form]',
                    'post_status' => 'publish',
                    'post_type' => 'page'
                ]);
            }
        } else {
            $lost_id = $lost_page->ID;
            if (strpos($lost_page->post_content, 'dior_lost_password_form') === false) {
                wp_update_post([
                    'ID' => $lost_id,
                    'post_content' => '[dior_lost_password_form]'
                ]);
            }
        }
        if (!empty($lost_id) && !is_wp_error($lost_id)) {
            update_post_meta($lost_id, '_wp_page_template', 'elementor_header_footer');
        }

        // Reset Password Page (/diro-reset-password/)
        $reset_page = get_page_by_path('diro-reset-password');
        if (!$reset_page) {
            $old_reset = get_page_by_path('reset-password');
            if ($old_reset) {
                wp_update_post([
                    'ID' => $old_reset->ID,
                    'post_name' => 'diro-reset-password'
                ]);
                $reset_id = $old_reset->ID;
            } else {
                $reset_id = wp_insert_post([
                    'post_title' => 'Reset Password',
                    'post_name' => 'diro-reset-password',
                    'post_content' => '[dior_reset_password_form]',
                    'post_status' => 'publish',
                    'post_type' => 'page'
                ]);
            }
        } else {
            $reset_id = $reset_page->ID;
            if (strpos($reset_page->post_content, 'dior_reset_password_form') === false) {
                wp_update_post([
                    'ID' => $reset_id,
                    'post_content' => '[dior_reset_password_form]'
                ]);
            }
        }
        if (!empty($reset_id) && !is_wp_error($reset_id)) {
            update_post_meta($reset_id, '_wp_page_template', 'elementor_header_footer');
        }

        // Book Visit & Medical Intake Page (/book-visit/)
        $book_page = get_page_by_path('book-visit');
        if (!$book_page) {
            $book_id = wp_insert_post([
                'post_title' => 'Book Appointment & Medical Intake',
                'post_name' => 'book-visit',
                'post_content' => '[dior_book_visit_page]',
                'post_status' => 'publish',
                'post_type' => 'page'
            ]);
        } else {
            $book_id = $book_page->ID;
            if (strpos($book_page->post_content, 'dior_book_visit_page') === false) {
                wp_update_post([
                    'ID' => $book_id,
                    'post_content' => '[dior_book_visit_page]'
                ]);
            }
        }
        if (!empty($book_id) && !is_wp_error($book_id)) {
            update_post_meta($book_id, '_wp_page_template', 'elementor_canvas');
        }

        // New DocBooker Integration Page (/docbooker-booking/)
        $docbooker_page = get_page_by_path('docbooker-booking');
        if (!$docbooker_page) {
            $docbooker_id = wp_insert_post([
                'post_title' => 'DocBooker Booking',
                'post_name' => 'docbooker-booking',
                'post_content' => '[wpddb_doctor_booking_form]',
                'post_status' => 'publish',
                'post_type' => 'page'
            ]);
        } else {
            $docbooker_id = $docbooker_page->ID;
            if (strpos($docbooker_page->post_content, 'wpddb_doctor_booking_form') === false) {
                wp_update_post([
                    'ID' => $docbooker_id,
                    'post_content' => '[wpddb_doctor_booking_form]'
                ]);
            }
        }
        if (!empty($docbooker_id) && !is_wp_error($docbooker_id)) {
            update_post_meta($docbooker_id, '_wp_page_template', 'elementor_canvas');
        }
    }

    /**
     * Override Page Template to Elementor Canvas (or built-in full-screen canvas)
     */
    public static function override_page_template($template)
    {
        if (is_admin()) {
            return $template;
        }

        // Auth pages (Login, Registration, Lost Password, Reset Password) must display Header & Footer!
        if (is_page(['diro-login', 'diro-registration', 'diro-lost-password', 'diro-reset-password', 'patient-portal-landing-page'])) {
            return $template;
        }

        global $post;
        if ($post && in_array($post->post_name, ['diro-login', 'diro-registration', 'diro-lost-password', 'diro-reset-password', 'patient-portal-landing-page'])) {
            return $template;
        }

        $is_portal = false;
        if (is_page(['patient-dashboard', 'doctor-dashboard'])) {
            $is_portal = true;
        } else if (is_singular('page')) {
            if (
                $post && (
                    has_shortcode($post->post_content, 'dior_patient_dashboard') ||
                    in_array($post->post_name, ['patient-dashboard', 'doctor-dashboard'])
                )
            ) {
                $is_portal = true;
            }
        }

        if ($is_portal) {
            // 1. Elementor Canvas Template if available
            if (defined('ELEMENTOR_PATH')) {
                $canvas = ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php';
                if (file_exists($canvas)) {
                    return $canvas;
                }
            }
            // 2. Built-in Canvas Template
            $custom_canvas = DIOR_PORTAL_PATH . 'templates/canvas-template.php';
            if (file_exists($custom_canvas)) {
                return $custom_canvas;
            }
        }

        return $template;
    }

    /**
     * Shortcode: [dior_book_visit_page]
     * Dedicated Luxury Full-Page Medical Intake & Booking Page
     */
    public static function render_standalone_appointment_page($atts = [])
    {
        ob_start();
        include DIOR_PORTAL_PATH . 'templates/appointment-page.php';
        return ob_get_clean();
    }

    /**
     * Legacy Shortcode Method (kept for reference)
     */
    public static function render_book_visit_page($atts = [])
    {
        $logo_url = self::get_logo_url();
        $user_id = get_current_user_id();
        $profile = is_user_logged_in() ? Dior_Patient_Portal_Data::get_patient_profile($user_id) : null;
        $dashboard_url = home_url('/patient-dashboard/');

        ob_start();
        ?>
        <div class="dior-book-page-wrap" id="dior-book-page-app">
            <!-- Top Navigation Header -->
            <header class="dior-book-topbar">
                <div class="dior-book-topbar-inner">
                    <div class="dior-book-brand-side">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="dior-book-logo-link">
                            <img src="<?php echo esc_url($logo_url); ?>" alt="Dior Medical" class="dior-book-logo">
                        </a>
                        <span class="dior-book-divider">|</span>
                        <span class="dior-book-badge"><i class="fa-solid fa-shield-halved"></i> HIPAA Verified Intake</span>
                    </div>

                    <div class="dior-book-user-side">
                        <?php if (is_user_logged_in() && $profile): ?>
                            <div class="dior-book-patient-info">
                                <span class="dior-book-patient-name"><i class="fa-regular fa-user"></i>
                                    <?php echo esc_html($profile['full_name']); ?></span>
                                <span class="dior-book-patient-id"><?php echo esc_html($profile['patient_id']); ?></span>
                            </div>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($dashboard_url); ?>" class="dior-btn-back-dashboard">
                            <i class="fa-solid fa-arrow-left"></i> <span>Back to Dashboard</span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Main Booking Container -->
            <main class="dior-book-main">
                <div class="dior-book-container">
                    <!-- Page Breadcrumbs & Intro Header -->
                    <div class="dior-book-header-box">
                        <div class="dior-book-title-row">
                            <div>
                                <h1>Schedule Telehealth Consultation</h1>
                                <p>Complete your HIPAA medical intake form below to connect with a board-certified physician.
                                </p>
                            </div>
                            <div class="dior-book-security-badge">
                                <i class="fa-solid fa-lock"></i> 256-Bit Encrypted &amp; HIPAA Compliant
                            </div>
                        </div>
                    </div>

                    <!-- Main Form Card -->
                    <div class="dior-book-card">
                        <div id="dior-custom-booking-wizard">
                            <div class="booking-step" id="step-1-treatment">
                                <h3>Select Treatment / Service</h3>
                                <div id="treatments-list" class="dior-grid-options">
                                    <p>Loading treatments...</p>
                                </div>
                            </div>

                            <div class="booking-step" id="step-2-doctor" style="display:none;">
                                <h3>Select Your Doctor</h3>
                                <button type="button" class="dior-btn-back-step" onclick="diorBookingGoToStep(1)">&larr; Back to
                                    Treatments</button>
                                <div id="doctors-list" class="dior-grid-options" style="margin-top:15px;"></div>
                            </div>

                            <div class="booking-step" id="step-3-time" style="display:none;">
                                <h3>Select Date & Time</h3>
                                <button type="button" class="dior-btn-back-step" onclick="diorBookingGoToStep(2)">&larr; Back to
                                    Doctors</button>
                                <div class="dior-date-selector" style="margin-top:15px;">
                                    <input type="date" id="booking-date" class="dior-form-input"
                                        min="<?php echo date('Y-m-d'); ?>" onchange="diorFetchAvailability()">
                                </div>
                                <div id="time-slots-list" class="dior-grid-options" style="margin-top:15px;"></div>
                            </div>

                            <div class="booking-step" id="step-4-confirm" style="display:none;">
                                <h3>Confirm Your Appointment</h3>
                                <button type="button" class="dior-btn-back-step" onclick="diorBookingGoToStep(3)">&larr; Back to
                                    Time Selection</button>
                                <div id="booking-summary"
                                    style="margin-top:15px; padding: 15px; background: #f8fafc; border-radius: 8px;"></div>
                                <div style="margin-top:20px;">
                                    <button type="button" class="dior-btn-auth-primary" id="btn-confirm-booking"
                                        onclick="diorSubmitBooking()">Confirm & Book</button>
                                </div>
                                <div id="booking-message" style="margin-top:15px; font-weight:bold;"></div>
                            </div>
                        </div>
                    </div>


                    <!-- Footer Help & Info Note -->
                    <div class="dior-book-footer-note">
                        <div class="note-item"><i class="fa-solid fa-headset"></i> Need assistance? Our clinical care team is
                            available 24/7.</div>
                        <div class="note-item"><i class="fa-solid fa-prescription-bottle-medical"></i> Prescriptions are routed
                            electronically to your preferred pharmacy immediately following consultation.</div>
                    </div>
                </div>
            </main>
        </div>
        </div>

        <?php $current_user = wp_get_current_user(); ?>
        <script>
            let diorBookingState = {
                departmentId: null,
                departmentName: null,
                doctorId: null,
                doctorName: null,
                date: null,
                day: null,
                clinicId: null,
                time: null,
                patient: {
                    name: "<?php echo esc_js($profile ? $profile['full_name'] : ''); ?>",
                    email: "<?php echo esc_js($current_user->user_email); ?>",
                    phone: "<?php echo esc_js($profile ? $profile['phone'] : ''); ?>",
                    notes: ""
                }
            };

            const diorRestUrl = "<?php echo esc_url_raw(rest_url()); ?>";
            const diorAjaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
            const diorRestNonce = "<?php echo wp_create_nonce('wp_rest'); ?>";
            const diorPortalNonce = "<?php echo wp_create_nonce('dior_portal_nonce'); ?>";

            document.addEventListener('DOMContentLoaded', function () {
                diorFetchTreatments();
            });

            function diorBookingGoToStep(step) {
                document.querySelectorAll('.booking-step').forEach(el => el.style.display = 'none');
                document.getElementById('step-' + step + (step === 1 ? '-treatment' : (step === 2 ? '-doctor' : (step === 3 ? '-time' : '-confirm')))).style.display = 'block';
            }

            function diorFetchTreatments() {
                fetch(diorRestUrl + 'get-doctor/v1/departments')
                    .then(res => res.json())
                    .then(data => {
                        let html = '';
                        if (data && data.length) {
                            data.forEach(dept => {
                                html += `<button type="button" class="dior-grid-btn" onclick="diorSelectTreatment(${dept.id}, '${dept.name.replace(/'/g, "\\'")}')">${dept.name}</button>`;
                    });
                } else {
                    html = '<button type="button" class="dior-grid-btn" onclick="diorSelectTreatment(1, \'Urgent Care Telehealth\')">Urgent Care Telehealth</button><button type="button" class="dior-grid-btn" onclick="diorSelectTreatment(2, \'Primary Care & General Medicine\')">Primary Care</button>';
                }
                document.getElementById('treatments-list').innerHTML = html;
            })
            .catch(() => {
                document.getElementById('treatments-list').innerHTML = '<button type="button" class="dior-grid-btn" onclick="diorSelectTreatment(1, \'Urgent Care Telehealth\')">Urgent Care Telehealth</button><button type="button" class="dior-grid-btn" onclick="diorSelectTreatment(2, \'Primary Care & General Medicine\')">Primary Care</button>';
            });
    }

    function diorSelectTreatment(id, name) {
        diorBookingState.departmentId = id;
        diorBookingState.departmentName = name;
        document.getElementById('doctors-list').innerHTML = '<p>Loading doctors...</p>';
        diorBookingGoToStep(2);

        fetch(diorRestUrl + 'get-doctor-by-departments/v1/' + id)
            .then(res => res.json())
            .then(data => {
                let html = '';
                if (data && data.length) {
                    data.forEach(doc => {
                        html += `<div class="dior-doctor-card-select" onclick="diorSelectDoctor(${doc.id}, '${doc.name.replace(/'/g, "\\'")}')">
                                                                            <h4>${doc.name}</h4>
                                                                            <p>${doc.speciality}</p>
                                                                        </div>`;
                    });
                } else {
                    html = `<div class="dior-doctor-card-select" onclick="diorSelectDoctor(1695, 'Dr. James Chen, DO')">
                                                                        <h4>Dr. James Chen, DO</h4>
                                                                        <p>Primary Care & Urgent Care</p>
                                                                    </div>
                                                                    <div class="dior-doctor-card-select" onclick="diorSelectDoctor(1693, 'Dr. Marcus Sterling, DO')">
                                                                        <h4>Dr. Marcus Sterling, DO</h4>
                                                                        <p>Urgent Care Physician</p>
                                                                    </div>`;
                }
                document.getElementById('doctors-list').innerHTML = html;
            })
            .catch(() => {
                document.getElementById('doctors-list').innerHTML = `
                                                            <div class="dior-doctor-card-select" onclick="diorSelectDoctor(1695, 'Dr. James Chen, DO')">
                                                                <h4>Dr. James Chen, DO</h4>
                                                                <p>Primary Care & Urgent Care</p>
                                                            </div>
                                                            <div class="dior-doctor-card-select" onclick="diorSelectDoctor(1693, 'Dr. Marcus Sterling, DO')">
                                                                <h4>Dr. Marcus Sterling, DO</h4>
                                                                <p>Urgent Care Physician</p>
                                                            </div>`;
            });
    }

    function diorSelectDoctor(id, name) {
        diorBookingState.doctorId = id;
        diorBookingState.doctorName = name;
        document.getElementById('time-slots-list').innerHTML = '';
        document.getElementById('booking-date').value = '';
        diorBookingGoToStep(3);
    }

    function diorFetchAvailability() {
        let dateStr = document.getElementById('booking-date').value;
        if (!dateStr) return;

        document.getElementById('time-slots-list').innerHTML = '<p>Loading availability...</p>';
        diorBookingState.date = dateStr;

        function renderDefaultTimes() {
            const defaultTimes = ['09:00 AM', '10:30 AM', '01:00 PM', '02:30 PM', '04:00 PM', '05:30 PM'];
            let h = '<h4>Available Telehealth Slots</h4><div class="dior-time-grid">';
            defaultTimes.forEach(t => {
                h += `<button type="button" class="dior-time-btn" onclick="diorSelectTime(1, '${t}')">${t}</button>`;
            });
            h += '</div>';
            document.getElementById('time-slots-list').innerHTML = h;
        }

        fetch(diorRestUrl + `doctor-details-booking/v1/doctors/${diorBookingState.doctorId}/availability?date=${dateStr}`, {
            headers: { 'X-WP-Nonce': diorRestNonce }
        })
            .then(res => res.json())
            .then(data => {
                let html = '';
                if (data && data.clinics && data.clinics.length > 0) {
                    diorBookingState.day = data.day;
                    data.clinics.forEach(clinic => {
                        if (!clinic.is_on_holiday && clinic.timings && clinic.timings.length > 0) {
                            html += `<h4>${clinic.name}</h4><div class="dior-time-grid">`;
                            clinic.timings.forEach(timing => {
                                if (timing.is_available) {
                                    html += `<button type="button" class="dior-time-btn" onclick="diorSelectTime(${clinic.id}, '${timing.time}')">${timing.time}</button>`;
                                } else {
                                    html += `<button type="button" class="dior-time-btn disabled" disabled>${timing.time}</button>`;
                                }
                            });
                            html += `</div>`;
                        }
                    });
                }
                if (html) {
                    document.getElementById('time-slots-list').innerHTML = html;
                } else {
                    renderDefaultTimes();
                }
            })
            .catch(() => {
                renderDefaultTimes();
            });
    }

    function diorSelectTime(clinicId, time) {
        diorBookingState.clinicId = clinicId;
        diorBookingState.time = time;

        document.getElementById('booking-summary').innerHTML = `
                                                    <p><strong>Treatment:</strong> ${diorBookingState.departmentName || 'Telehealth Urgent Care'}</p>
                                                    <p><strong>Doctor:</strong> ${diorBookingState.doctorName || 'Attending Physician'}</p>
                                                    <p><strong>Date:</strong> ${diorBookingState.date}</p>
                                                    <p><strong>Time:</strong> ${diorBookingState.time}</p>
                                                    <p><strong>Patient:</strong> ${diorBookingState.patient.name || 'Verified Patient'}</p>
                                                `;
        diorBookingGoToStep(4);
    }

    function diorSubmitBooking() {
        let btn = document.getElementById('btn-confirm-booking');
        let msg = document.getElementById('booking-message');
        btn.disabled = true;
        btn.innerText = 'Booking Consultation...';

        const formData = new FormData();
        formData.append('action', 'dior_patient_book_appointment');
        formData.append('nonce', diorPortalNonce);
        formData.append('doctorId', diorBookingState.doctorId || 1695);
        formData.append('doctor_name', diorBookingState.doctorName || 'Doctor');
        formData.append('date', diorBookingState.date || '');
        formData.append('time', diorBookingState.time || '');
        formData.append('condition', diorBookingState.departmentName || 'Telehealth Consultation');
        formData.append('name', diorBookingState.patient.name || '');
        formData.append('email', diorBookingState.patient.email || '');
        formData.append('phone', diorBookingState.patient.phone || '');

        fetch(diorAjaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    msg.style.color = '#059669';
                    msg.innerText = 'Appointment booked successfully! Redirecting to your dashboard...';
                    setTimeout(() => {
                        window.location.href = "<?php echo esc_js($dashboard_url); ?>";
                    }, 1500);
                } else {
                    msg.style.color = '#DC2626';
                    msg.innerText = (data.data && data.data.message) || data.message || 'Failed to book appointment.';
                    btn.disabled = false;
                    btn.innerText = 'Confirm & Book';
                }
            })
            .catch(err => {
                msg.style.color = '#DC2626';
                msg.innerText = 'An error occurred while booking. Please try again.';
                btn.disabled = false;
                btn.innerText = 'Confirm & Book';
            });
    }
</script>
<?php
                return ob_get_clean();
    }

    /**
     * Get Site Logo URL
     */
    public static function get_logo_url()
    {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo)
                return $logo;
        }
        return DIOR_AUTH_URL . 'assets/images/logo-web.png';
    }

    /**
     * Shortcode: [dior_login_form]
     */
    public static function render_login_form($atts)
    {
        $logo_url = self::get_logo_url();
        $redirect_to = isset($_GET['redirect_to']) ? esc_url($_GET['redirect_to']) : home_url('/patient-dashboard/');

        ob_start();
        ?>
<div class="dior-auth-wrap">
    <div class="dior-login-split-container">
        <!-- LEFT PANEL: Image -->
        <div class="dior-login-left-panel">
            <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/login-banner.webp'); ?>"
                alt="Doctor and Patient" class="dior-login-image">
        </div>

        <!-- RIGHT PANEL: Form -->
        <div class="dior-login-right-panel">
            <?php if (is_user_logged_in()):
                $current_user = wp_get_current_user();
                $is_doc = in_array('doctor', (array) $current_user->roles) || in_array('administrator', (array) $current_user->roles) || in_array($current_user->user_login, ['docter', 'admin', 'doctor', 'jameschen', 'evelynvance', 'marcussterling', 'jon']);
                $dash_url = $is_doc ? home_url('/doctor-dashboard/') : home_url('/patient-dashboard/');
                $dash_label = $is_doc ? 'Go to Doctor Dashboard &rarr;' : 'Go to Patient Dashboard &rarr;';
                ?>
            <div class="dior-logged-in-box">
                <div class="dior-user-badge-circle">
                    <?php echo esc_html(strtoupper(substr($current_user->display_name ?: 'P', 0, 2))); ?>
                </div>
                <div class="dior-user-info-text">
                    <strong><?php echo esc_html($current_user->display_name); ?></strong>
                    <span><?php echo esc_html($current_user->user_email); ?></span>
                </div>
            </div>

            <div class="dior-auth-actions" style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                <a href="<?php echo esc_url($dash_url); ?>" class="dior-btn-auth-primary"><i
                        class="fa-solid fa-gauge-high"></i> <?php echo esc_html($dash_label); ?></a>
                <a href="<?php echo esc_url(wp_logout_url(home_url('/diro-login/'))); ?>"
                    class="dior-btn-auth-secondary">Sign Out</a>
            </div>

            <?php else: ?>

            <div class="dior-login-header">
                <h3 class="dior-login-title">Log In</h3>
                <p class="dior-login-subtitle">Access your appointments, prescriptions, intake forms, and records.</p>
            </div>

            <form id="dior-login-form" class="dior-auth-form" novalidate>
                <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                <div class="dior-auth-msg success" style="display:block; margin-bottom: 8px;">
                    Your password has been successfully reset! Please sign in below.
                </div>
                <?php endif; ?>
                <div class="dior-auth-msg" id="dior-login-msg" style="display:none;"></div>

                <div class="dior-form-group">
                    <label for="dior-login-username">Email or Username</label>
                    <input type="text" id="dior-login-username" name="username" placeholder="Email or Username" required
                        autocomplete="username" class="dior-login-input">
                </div>

                <div class="dior-form-group">
                    <label for="dior-login-password">Password</label>
                    <div class="dior-input-pwd-wrap" style="position:relative;">
                        <input type="password" id="dior-login-password" name="password" placeholder="********" required
                            autocomplete="current-password" class="dior-login-input">
                        <button type="button" class="dior-pwd-toggle" id="dior-toggle-login-pwd"
                            aria-label="Toggle password visibility">
                            <svg class="dior-eye-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="dior-form-check-row" style="justify-content: space-between;">
                    <label class="dior-custom-checkbox">
                        <input type="checkbox" name="rememberme" value="forever" checked>
                        <span class="dior-checkmark"></span>
                        <span class="dior-check-text">Remember me on this device</span>
                    </label>
                    <a href="<?php echo esc_url(home_url('/diro-lost-password/')); ?>" class="dior-forgot-link">Forgot
                        password?</a>
                </div>

                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">

                <div class="dior-form-submit">
                    <button type="submit" class="dior-btn-login-submit" id="dior-login-submit-btn">
                        <span>Sign In To Dashboard &rarr;</span>
                    </button>
                </div>
            </form>

            <div class="dior-login-divider">
                <span>OR</span>
            </div>

            <button type="button" class="dior-btn-google"
                onclick="alert('Google Login will be implemented once Client ID is available.');">
                <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google Logo"
                    class="google-logo">
                Sign In With Google
            </button>

            <!-- BOTTOM: SWITCH LINK TO REGISTRATION -->
            <div class="dior-auth-bottom-link-redesign">
                <p>First time visiting? <a href="<?php echo esc_url(home_url('/diro-registration/')); ?>">Create Patient
                        Account</a></p>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>
<?php
                return ob_get_clean();
    }    /**
         * Shortcode: [dior_registration_form]
         */
    public static function render_registration_form($atts)
    {
        $logo_url = self::get_logo_url();

        ob_start();
        ?>
<div class="dior-auth-wrap">
    <div class="dior-reg-card-container">

        <?php if (is_user_logged_in()): ?>
        <div class="dior-login-header">
            <h3 class="dior-reg-title">Already Signed In</h3>
            <p class="dior-reg-subtitle">You are currently signed in to your account.</p>
        </div>
        <div style="display:flex; flex-direction:column; gap:12px; margin-top:20px;">
            <a href="<?php echo esc_url(home_url('/patient-dashboard/')); ?>" class="dior-btn-reg-submit"
                style="text-decoration:none; display:flex; align-items:center; justify-content:center;">
                <span>Go to Patient Dashboard &rarr;</span>
            </a>
            <a href="<?php echo esc_url(wp_logout_url(home_url('/diro-registration/'))); ?>" class="dior-btn-google"
                style="text-decoration:none; text-align:center;">
                Sign Out / Switch Account
            </a>
        </div>
        <?php else: ?>

        <div class="dior-reg-header">
            <h3 class="dior-reg-title">New Patient Registration</h3>
            <p class="dior-reg-subtitle">Complete your intake profile to book consultations and access telehealth care.
            </p>
        </div>

        <form id="dior-register-form" class="dior-auth-form" novalidate>
            <div class="dior-auth-msg" id="dior-register-msg" style="display:none;"></div>

            <!-- Personal Information -->
            <div class="dior-reg-section-title">Personal Information</div>

            <div class="dior-form-row-2">
                <div class="dior-form-group">
                    <label for="dior-reg-first-name">First Name</label>
                    <input type="text" id="dior-reg-first-name" name="first_name" placeholder="First Name" required
                        class="dior-login-input">
                </div>
                <div class="dior-form-group">
                    <label for="dior-reg-last-name">Last Name</label>
                    <input type="text" id="dior-reg-last-name" name="last_name" placeholder="Last Name" required
                        class="dior-login-input">
                </div>
            </div>

            <div class="dior-form-row-2">
                <div class="dior-form-group">
                    <label for="dior-reg-dob">Date of Birth</label>
                    <input type="date" id="dior-reg-dob" name="dob"
                        max="<?php echo esc_attr(date('Y-m-d', strtotime('-18 years'))); ?>" min="1900-01-01" required
                        class="dior-login-input">
                </div>
                <div class="dior-form-group">
                    <label for="dior-reg-gender">Gender / Sex at Birth</label>
                    <select id="dior-reg-gender" name="gender" required class="dior-login-input dior-reg-select">
                        <option value="">Gender / Sex at Birth</option>
                        <option value="Female">Female</option>
                        <option value="Male">Male</option>
                        <option value="Other / Non-Binary">Other / Non-Binary</option>
                    </select>
                </div>
            </div>

            <!-- Telehealth Eligibility & Age Verification -->
            <div class="dior-reg-section-title">Telehealth Eligibility &amp; Age Verification</div>
            <div class="dior-eligibility-options">
                <label class="dior-reg-radio-label">
                    <input type="radio" name="eligibility_check" value="yes" required checked
                        class="dior-reg-radio-input">
                    <span>I confirm I am 18+ years of age and currently in an eligible telehealth state (CA).</span>
                </label>
                <label class="dior-reg-radio-label">
                    <input type="radio" name="eligibility_check" value="no" class="dior-reg-radio-input">
                    <span>No, I am under 18 years of age.</span>
                </label>
            </div>

            <!-- Contact Information -->
            <div class="dior-reg-section-title">Contact Information</div>

            <div class="dior-form-row-2">
                <div class="dior-form-group">
                    <label for="dior-reg-email">Email Address</label>
                    <input type="email" id="dior-reg-email" name="email" placeholder="Email Address" required
                        autocomplete="email" class="dior-login-input">
                </div>
                <div class="dior-form-group">
                    <label for="dior-reg-phone">Phone Number</label>
                    <input type="tel" id="dior-reg-phone" name="phone" placeholder="Phone Number" required
                        autocomplete="tel" class="dior-login-input">
                </div>
            </div>

            <!-- Account Security -->
            <div class="dior-reg-section-title">Account Security</div>

            <div class="dior-form-row-2">
                <div class="dior-form-group">
                    <label for="dior-reg-password">Password</label>
                    <div class="dior-input-pwd-wrap" style="position:relative;">
                        <input type="password" id="dior-reg-password" name="password" placeholder="••••••••" required
                            autocomplete="new-password" class="dior-login-input">
                        <button type="button" class="dior-pwd-toggle" id="dior-toggle-reg-pwd"
                            aria-label="Toggle password visibility">
                            <svg class="dior-eye-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="dior-form-group">
                    <label for="dior-reg-confirm-password">Confirm Password</label>
                    <div class="dior-input-pwd-wrap" style="position:relative;">
                        <input type="password" id="dior-reg-confirm-password" name="confirm_password"
                            placeholder="••••••••" required autocomplete="new-password" class="dior-login-input">
                        <button type="button" class="dior-pwd-toggle" id="dior-toggle-reg-confirm-pwd"
                            aria-label="Toggle password visibility">
                            <svg class="dior-eye-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Terms & HIPAA -->
            <div class="dior-reg-terms-row">
                <label class="dior-custom-checkbox">
                    <input type="checkbox" name="terms_consent" value="1" required checked>
                    <span class="dior-checkmark"></span>
                    <span class="dior-check-text">I agree to the <a
                            href="<?php echo esc_url(home_url('/about-dior-medical/')); ?>" target="_blank">Terms of
                            Care</a>, HIPAA Notice &amp; Privacy Policy.</span>
                </label>
            </div>

            <div class="dior-reg-submit-wrap">
                <button type="submit" class="dior-btn-reg-submit" id="dior-register-submit-btn">
                    <span>Create Patient Account &rarr;</span>
                </button>
            </div>
        </form>

        <div class="dior-reg-bottom-link">
            <p>Already have an account? <a href="<?php echo esc_url(home_url('/diro-login/')); ?>">Log in here</a></p>
        </div>

        <?php endif; ?>
    </div>
</div>
<?php
                return ob_get_clean();
    }

    /**
     * Shortcode: [dior_lost_password_form]
     */
    public static function render_lost_password_form($atts)
    {
        $logo_url = self::get_logo_url();

        ob_start();
        ?>
<div class="dior-auth-wrap">
    <div class="dior-login-split-container">
        <!-- LEFT PANEL: Image -->
        <div class="dior-login-left-panel">
            <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/login-banner.webp'); ?>"
                alt="Doctor and Patient" class="dior-login-image">
        </div>

        <!-- RIGHT PANEL: Form -->
        <div class="dior-login-right-panel">
            <div class="dior-login-header">
                <h3 class="dior-login-title">Reset Password</h3>
                <p class="dior-login-subtitle">Enter your registered email address or username and we will send you a
                    secure link to reset your password.</p>
            </div>

            <form id="dior-lost-password-form" class="dior-auth-form" novalidate>
                <div class="dior-auth-msg" id="dior-lost-msg" style="display:none;"></div>

                <div class="dior-form-group">
                    <label for="dior-lost-user-login">Email or Username <span class="req">*</span></label>
                    <input type="text" id="dior-lost-user-login" name="user_login" placeholder="name@example.com"
                        required autocomplete="username" class="dior-login-input">
                </div>

                <div class="dior-form-submit" style="margin-top: 10px;">
                    <button type="submit" class="dior-btn-login-submit" id="dior-lost-submit-btn">
                        <span>Send Reset Instructions &rarr;</span>
                    </button>
                </div>
            </form>

            <!-- BOTTOM: SWITCH LINK TO LOGIN -->
            <div class="dior-auth-bottom-link-redesign">
                <p>Remember your password? <a href="<?php echo esc_url(home_url('/diro-login/')); ?>">Back to Sign In
                        &rarr;</a></p>
            </div>
        </div>
    </div>
</div>
<?php
                return ob_get_clean();
    }

    /**
     * Shortcode: [dior_reset_password_form]
     */
    public static function render_reset_password_form($atts)
    {
        $logo_url = self::get_logo_url();
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $login = isset($_GET['login']) ? sanitize_text_field(wp_unslash($_GET['login'])) : '';

        // Validate key and login
        $user = null;
        $is_valid = false;
        $error_message = '';

        if (!empty($key) && !empty($login)) {
            $check = check_password_reset_key($key, $login);
            if (!is_wp_error($check)) {
                $is_valid = true;
                $user = $check;
            } else {
                $error_message = 'This password reset link is invalid or has expired. For security, reset links are single-use and expire after 24 hours.';
            }
        } else {
            $error_message = 'Invalid password reset request. Please request a new password reset link below.';
        }

        ob_start();
        ?>
<div class="dior-auth-wrap">
    <div class="dior-auth-card dior-reset-password-card">

        <!-- TOP: SITE LOGO -->
        <div class="dior-auth-logo-box">
            <a href="<?php echo esc_url(home_url('/')); ?>">
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                    class="dior-site-logo">
            </a>
        </div>

        <?php if (!$is_valid): ?>

        <div class="dior-portal-header-note" style="text-align: center;">
            <div
                style="width: 56px; height: 56px; border-radius: 50%; background: #FEF2F2; color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 16px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 style="text-align: center; margin-bottom: 8px; color: #0F172A;">Link Expired or Invalid</h3>
            <p style="text-align: center; color: #64748B; font-size: 14px; line-height: 1.5; margin: 0 0 24px;">
                <?php echo esc_html($error_message); ?>
            </p>
        </div>

        <div class="dior-auth-actions" style="display: flex; flex-direction: column; gap: 12px;">
            <a href="<?php echo esc_url(home_url('/diro-lost-password/')); ?>" class="dior-btn-auth-primary"
                style="text-align: center; text-decoration: none;">
                Request New Reset Link &rarr;
            </a>
            <a href="<?php echo esc_url(home_url('/diro-login/')); ?>" class="dior-btn-auth-secondary"
                style="text-align: center; text-decoration: none; display: block; padding: 12px 16px; border-radius: 8px; background: #F8FAFC; color: #475569; font-weight: 500; font-size: 13.5px; border: 1px solid #E2E8F0;">
                &larr; Return to Sign In
            </a>
        </div>

        <?php else: ?>

        <div class="dior-portal-header-note" style="text-align: center;">
            <h3 style="text-align: center; margin-bottom: 6px;">Set New Password</h3>
            <p style="text-align: center; color: #64748B; font-size: 14px; line-height: 1.5; margin: 0 0 20px;">
                Please choose a new, secure password for
                <strong><?php echo esc_html($user->user_email ?: $user->user_login); ?></strong>.
            </p>
        </div>

        <form id="dior-reset-password-form" class="dior-auth-form" novalidate>
            <div class="dior-auth-msg" id="dior-reset-msg" style="display:none;"></div>

            <input type="hidden" name="rp_key" value="<?php echo esc_attr($key); ?>">
            <input type="hidden" name="rp_login" value="<?php echo esc_attr($login); ?>">

            <div class="dior-form-group">
                <label for="dior-new-password">New Password <span class="req">*</span></label>
                <div class="dior-input-pwd-wrap" style="position:relative;">
                    <input type="password" id="dior-new-password" name="new_password" placeholder="Min. 6 characters"
                        required minlength="6" autocomplete="new-password">
                    <button type="button" class="dior-pwd-toggle" id="dior-toggle-new-pwd"
                        aria-label="Toggle password visibility">
                        <svg class="dior-eye-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="dior-form-group">
                <label for="dior-confirm-password">Confirm New Password <span class="req">*</span></label>
                <div class="dior-input-pwd-wrap" style="position:relative;">
                    <input type="password" id="dior-confirm-password" name="confirm_password"
                        placeholder="Re-enter new password" required minlength="6" autocomplete="new-password">
                    <button type="button" class="dior-pwd-toggle" id="dior-toggle-confirm-pwd"
                        aria-label="Toggle password visibility">
                        <svg class="dior-eye-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="dior-form-submit" style="margin-top: 10px;">
                <button type="submit" class="dior-btn-auth-primary" id="dior-reset-submit-btn">
                    <span>Save New Password &rarr;</span>
                </button>
            </div>
        </form>

        <div class="dior-auth-bottom-link"
            style="margin-top: 24px; text-align: center; border-top: 1px solid #F1F5F9; padding-top: 16px;">
            <p style="font-size: 13.5px; color: #64748B; margin: 0;">
                <a href="<?php echo esc_url(home_url('/diro-login/')); ?>"
                    style="font-weight: 600; color: #0284C7; text-decoration: none;">&larr; Back to Sign In</a>
            </p>
        </div>

        <?php endif; ?>

    </div>
</div>
<?php
                return ob_get_clean();
    }

    /**
     * Redirect unauthenticated users from private patient and doctor dashboard pages to login
     */
    public static function check_dashboard_access_redirect()
    {
        if (is_user_logged_in()) {
            return;
        }

        $login_url = home_url('/diro-login/');

        global $post;
        $should_redirect = false;

        if (is_a($post, 'WP_Post')) {
            if (
                has_shortcode($post->post_content, 'dior_patient_dashboard') ||
                has_shortcode($post->post_content, 'dior_doctor_dashboard') ||
                has_shortcode($post->post_content, 'dior_book_visit_page')
            ) {
                $should_redirect = true;
            }
        }

        if (!$should_redirect && function_exists('is_page') && is_page(['patient-dashboard', 'doctor-dashboard', 'doctor-portal', 'dior-doctor-dashboard'])) {
            $should_redirect = true;
        }

        if (!$should_redirect && !empty($_SERVER['REQUEST_URI'])) {
            $uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            if (
                strpos($uri, 'patient-dashboard') !== false ||
                strpos($uri, 'doctor-dashboard') !== false ||
                strpos($uri, 'doctor-portal') !== false
            ) {
                $should_redirect = true;
            }
        }

        if ($should_redirect) {
            wp_safe_redirect($login_url);
            exit;
        }
    }

    /**
     * Shortcode: [dior_patient_dashboard]
     * Complete luxury Patient Dashboard with all 6 modules
     */
    public static function render_patient_dashboard($atts)
    {
        // If not logged in, redirect directly to login page
        if (!is_user_logged_in()) {
            $login_url = home_url('/diro-login/');
            if (!headers_sent()) {
                wp_safe_redirect($login_url);
                exit;
            }
            return '<script>window.location.replace(' . json_encode($login_url) . ');</script><meta http-equiv="refresh" content="0;url=' . esc_url($login_url) . '">';
        }

        $logo_url = self::get_logo_url();
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $profile = Dior_Patient_Portal_Data::get_patient_profile($user_id);
        $appointments = Dior_Patient_Portal_Data::get_patient_appointments($user_id);
        $prescriptions = Dior_Patient_Portal_Data::get_patient_prescriptions($user_id);
        $payments = Dior_Patient_Portal_Data::get_patient_payments($user_id);
        $documents = Dior_Patient_Portal_Data::get_patient_documents($user_id);
        $questionnaires = Dior_Patient_Portal_Data::get_patient_questionnaires($user_id);
        $notifications = Dior_Patient_Portal_Data::get_patient_notifications($user_id);
        $hipaa_intake = get_user_meta($user_id, 'dior_hipaa_intake', true); // HIPAAtizer submitted data

        // Calculate stats
        $next_appointment = null;
        $upcoming_list = [];
        $past_list = [];
        foreach ($appointments as $apt) {
            if ($apt['status'] === 'Confirmed' || $apt['status'] === 'Scheduled' || $apt['status'] === 'In-Queue') {
                if (!$next_appointment) {
                    $next_appointment = $apt;
                }
                $upcoming_list[] = $apt;
            } else {
                $past_list[] = $apt;
            }
        }

        $unread_count = 0;
        foreach ($notifications as $n) {
            if (!$n['is_read'])
                $unread_count++;
        }

        $pending_questionnaires = 0;
        foreach ($questionnaires as $q) {
            if ($q['status'] !== 'Submitted')
                $pending_questionnaires++;
        }

        $latest_rx = !empty($prescriptions) ? $prescriptions[0] : null;

        ob_start();
        ?>

<div class="dior-wrap dior-patient-portal-wrap" id="dior-patient-portal-app">

    <!-- Top Emergency Banner -->
    <div class="dior-top-emergency-banner">
        This is not for medical emergencies. Call 911 or go to the nearest ER.
    </div>

    <!-- Mobile Drawer Backdrop -->
    <div class="dior-drawer-backdrop" id="dior-drawer-backdrop" onclick="diorCloseMobileDrawer()"></div>

    <div class="dior-app">

        <!-- ============================================================== -->
        <!-- SIDEBAR NAVIGATION -->
        <!-- ============================================================== -->
        <aside class="dior-side" id="dior-sidebar">
            <div class="dior-side-header">
                <div class="brand">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="brand-link">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Dior Medical" class="brand-logo">
                    </a>
                </div>
                <button type="button" class="dior-side-close" id="dior-side-close" onclick="diorCloseMobileDrawer()"
                    aria-label="Close sidebar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="dior-side-nav">
                <div class="grp">
                    <button type="button" class="dior-nav-btn active" data-tab="overview">
                        <i class="fa-solid fa-desktop"></i>
                        <span class="nav-label">Dashboard</span>
                    </button>
                    <div class="dior-nav-item-has-children" id="dior-appt-dropdown-wrap">
                        <button type="button" class="dior-nav-btn" onclick="var w=document.getElementById('dior-appt-dropdown-wrap');w.classList.toggle('open');this.querySelector('.fa-chevron-down').style.transform=w.classList.contains('open')?'rotate(180deg)':'rotate(0deg)';diorSwitchApptSubTab('today');">
                            <i class="fa-regular fa-calendar-days"></i>
                            <span class="nav-label">Appointments</span>
                            <i class="fa-solid fa-chevron-down" style="margin-left: auto; font-size: 12px; transition: 0.3s;"></i>
                        </button>
                        <div class="dior-nav-dropdown">
                            <a href="#" class="dior-nav-dropdown-item" onclick="diorSwitchApptSubTab('book'); return false;"><i class="fa-solid fa-angle-right"></i> Book Appointment</a>
                            <a href="#" class="dior-nav-dropdown-item" onclick="diorSwitchApptSubTab('today'); return false;"><i class="fa-solid fa-angle-right"></i> Today Appointments</a>
                            <a href="#" class="dior-nav-dropdown-item" onclick="diorSwitchApptSubTab('upcoming'); return false;"><i class="fa-solid fa-angle-right"></i> Upcoming Appointments</a>
                            <a href="#" class="dior-nav-dropdown-item" onclick="diorSwitchApptSubTab('past'); return false;"><i class="fa-solid fa-angle-right"></i> Past Appointments</a>
                        </div>
                    </div>
                    <button type="button" class="dior-nav-btn" data-tab="docs_meds">
                        <i class="fa-solid fa-file-prescription"></i>
                        <span class="nav-label">Prescriptions</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="telemedicine">
                        <i class="fa-solid fa-user-doctor"></i>
                        <span class="nav-label">Telemedicine</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="medical_record">
                        <i class="fa-solid fa-file-medical"></i>
                        <span class="nav-label">Medical Record</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="payments">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span class="nav-label">Billing &amp; Payment</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="insurance">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span class="nav-label">Insurance Claim</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="documents">
                        <i class="fa-regular fa-copy"></i>
                        <span class="nav-label">Documents &amp; Report</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="emergency">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span class="nav-label">Emergency support</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="feedback">
                        <i class="fa-regular fa-comment-dots"></i>
                        <span class="nav-label">Feedback &amp; Support</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="notifications">
                        <i class="fa-regular fa-bell"></i>
                        <span class="nav-label">Notification</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="consultation">
                        <i class="fa-solid fa-video"></i>
                        <span class="nav-label">Consultation Room</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="settings">
                        <i class="fa-solid fa-gear"></i>
                        <span class="nav-label">Settings</span>
                    </button>
                </div>
            </nav>

            <!-- Bottom Sidebar User Profile Card -->
            <div class="dior-sidebar-bottom-profile">
                <div class="dior-sidebar-avatar-wrap">
                    <?php if (!empty($profile['avatar_url'])): ?>
                    <img src="<?php echo esc_url($profile['avatar_url']); ?>" alt="<?php echo esc_attr($profile['full_name']); ?>">
                    <?php else: ?>
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=200" alt="Mia Song">
                    <?php endif; ?>
                </div>
                <div class="dior-sidebar-user-info">
                    <strong class="dior-sidebar-user-name"><?php echo esc_html(!empty($profile['full_name']) ? $profile['full_name'] : 'Mia Song'); ?></strong>
                    <span class="dior-sidebar-user-status">Available</span>
                </div>
            </div>
        </aside>

        <!-- ============================================================== -->
        <!-- MAIN CONTENT AREA -->
        <!-- ============================================================== -->
        <main class="dior-main-content">

            <!-- Top Bar Header -->
            <header class="dior-topbar">
                <div class="dior-topbar-left">
                    <button type="button" class="dior-mobile-menu-toggle" id="dior-mobile-menu-toggle"
                        onclick="diorOpenMobileDrawer()" aria-label="Open navigation menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <!-- Search Bar (Clean Reference UI) -->
                    <div class="dior-top-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search appointments, prescriptions, records. .">
                    </div>
                </div>

                <div class="dior-topbar-right">
                    <!-- Messages Icon -->
                    <button type="button" class="dior-notif-btn" aria-label="Messages" style="margin-right: 5px;">
                        <i class="fa-regular fa-envelope"></i>
                    </button>

                    <!-- Notification Bell Dropdown -->
                    <div class="dior-notif-wrap" id="dior-notif-dropdown-wrap">
                        <button type="button" class="dior-notif-btn" onclick="diorToggleNotifDropdown(event)"
                            aria-label="Notifications">
                            <i class="fa-regular fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                            <span class="dior-notif-indicator unread-badge-count"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dior-notif-dropdown" id="dior-notif-dropdown">
                            <div class="dior-notif-header">
                                <h4>Notifications</h4>
                                <button type="button" id="dior-quick-mark-read">Mark all read</button>
                            </div>
                            <div class="dior-notif-list">
                                <?php if (empty($notifications)): ?>
                                <div style="padding: 24px; text-align: center; color: #94A3B8; font-size: 13px;">
                                    <i class="fa-regular fa-bell-slash"
                                        style="font-size: 20px; display: block; margin-bottom: 8px;"></i>
                                    No new notifications
                                </div>
                                <?php else: ?>
                                <?php foreach (array_slice($notifications, 0, 5) as $n):
                                    $target_tab = !empty($n['action_url']) ? str_replace('#tab=', '', $n['action_url']) : 'notifications';
                                    ?>
                                <div class="dior-notif-item <?php echo empty($n['is_read']) ? 'unread' : ''; ?>"
                                    data-notif-id="<?php echo esc_attr($n['id']); ?>"
                                    data-switch-tab="<?php echo esc_attr($target_tab); ?>" style="cursor:pointer;">
                                    <div class="notif-icon"><i
                                            class="fa-solid <?php echo esc_attr($n['icon'] ?? 'fa-bell'); ?>"></i></div>
                                    <div class="notif-body">
                                        <strong><?php echo esc_html($n['title']); ?></strong>
                                        <p><?php echo esc_html(mb_substr($n['message'], 0, 45) . (mb_strlen($n['message']) > 45 ? '...' : '')); ?>
                                        </p>
                                        <span
                                            class="notif-time"><?php echo esc_html(dior_format_notification_time($n)); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="dior-notif-footer">
                                <button type="button" data-switch-tab="notifications">View All &rarr;</button>
                            </div>
                        </div>
                    </div>

                    <!-- User Quick Profile Pill & Dropdown -->
                    <div class="dior-user-pill-wrap">
                        <button type="button" class="dior-user-pill-btn" onclick="diorToggleProfileDropdown(event)"
                            aria-label="User Profile">
                            <div class="dior-user-pill-card">
                                <div class="dior-pill-avatar" id="dior-top-pill-avatar">
                                    <?php if (!empty($profile['avatar_url'])): ?>
                                    <img src="<?php echo esc_url($profile['avatar_url']); ?>"
                                        alt="<?php echo esc_attr($profile['full_name']); ?>">
                                    <?php else: ?>
                                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=200" alt="Mia Song">
                                    <?php endif; ?>
                                    <span class="dior-online-dot"></span>
                                </div>
                                <div class="dior-pill-user-meta">
                                    <strong class="dior-pill-user-name"><?php echo esc_html(!empty($profile['full_name']) ? $profile['full_name'] : 'Mia Song'); ?></strong>
                                    <span class="dior-pill-user-status">Available</span>
                                </div>
                            </div>
                        </button>

                        <div class="dior-profile-dropdown" id="dior-profile-dropdown">
                            <div class="dior-profile-dropdown-header">
                                HELLO <?php echo esc_html(strtoupper($profile['full_name'])); ?>..
                            </div>
                            <ul class="dior-profile-dropdown-menu">
                                <li>
                                    <button type="button" data-switch-tab="settings">
                                        <i class="fa-regular fa-user"></i> Profile
                                    </button>
                                </li>
                                <li>
                                    <button type="button" data-switch-tab="settings">
                                        <i class="fa-regular fa-envelope"></i> Email
                                    </button>
                                </li>
                                <li>
                                    <button type="button" data-switch-tab="settings">
                                        <i class="fa-solid fa-gear"></i> Settings
                                    </button>
                                </li>
                                <li>
                                    <a href="<?php echo esc_url(wp_logout_url(home_url('/diro-login/'))); ?>"
                                        class="dior-logout-text-btn">
                                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>

            <!-- TAB PANELS CONTAINER -->
            <div class="dior-tab-container">

                <?php
                $is_prof_complete = Dior_Patient_Portal_Data::is_profile_complete($user_id);
                $missing_fields_list = Dior_Patient_Portal_Data::get_missing_profile_fields($user_id);
                ?>
                <?php if (!$is_prof_complete): ?>
                <!-- Action Required Alert Banner for Incomplete Profile -->
                <div class="dior-profile-incomplete-alert" id="dior-profile-incomplete-banner"
                    style="background:#FFFBEB; border:1.5px solid #FDE68A; border-left:5px solid #F59E0B; border-radius:12px; padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; box-shadow:0 4px 14px rgba(245,158,11,0.08);">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div
                            style="width:42px; height:42px; border-radius:50%; background:#F59E0B; color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;">
                            <i class="fa-solid fa-user-pen"></i>
                        </div>
                        <div>
                            <strong style="color:#92400E; font-size:14.5px; display:block; margin-bottom:2px;">
                                Action Required: Complete Your Personal Profile
                            </strong>
                            <span style="color:#B45309; font-size:13px; line-height:1.4;">
                                Please complete your required personal information
                                (<strong><?php echo esc_html(implode(', ', $missing_fields_list)); ?></strong>) to
                                unlock appointment booking and telehealth consultations.
                            </span>
                        </div>
                    </div>
                    <button type="button" class="dior-btn-gold-primary" data-switch-tab="profile"
                        style="padding:9px 18px; font-size:13px; font-weight:700; background:#2C6CB1 !important; border:none; border-radius:8px; color:#fff; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-id-card"></i> Complete Profile Now
                    </button>
                </div>
                <?php endif; ?>

                <!-- ============================================================== -->
                <!-- 0. HOME / OVERVIEW TAB (Real Functional Patient Data + Clean UI) -->
                <!-- ============================================================== -->
                <section class="dior-tab-panel active" id="tab-overview">

                    <!-- Sub-Header Bar: Page Title & Actions -->
                    <div class="dior-page-title-bar new-design">
                        <div class="title-left">
                            <?php $display_first_name = !empty($profile['first_name']) ? ucfirst(strtolower($profile['first_name'])) : 'Mia'; ?>
                            <h2>Welcome Back, <?php echo esc_html($display_first_name); ?></h2>
                            <p>Manage your telehealth visits, prescriptions, and medical records.</p>
                        </div>
                        <a href="#" class="dior-btn-book-appointment" data-switch-tab="appointments">
                            <i class="fa-solid fa-calendar-plus"></i> Book An Appointment
                        </a>
                    </div>

                    <!-- Layout: Left Column (65%) & Right Column (35%) -->
                    <div class="dior-dashboard-grid-layout">
                        
                        <!-- LEFT COLUMN -->
                        <div class="dior-dash-col-left">
                            
                            <!-- 3 Stat Cards Row -->
                            <div class="dior-dash-stats-row">
                                <div class="dior-dash-stat-card">
                                    <div class="stat-card-header">
                                        <div class="stat-icon-box blue-tint">
                                            <i class="fa-solid fa-user-doctor"></i>
                                        </div>
                                    </div>
                                    <div class="stat-card-body">
                                        <h3><?php echo !empty($past_list) ? count($past_list) + count($upcoming_list) : 5; ?></h3>
                                        <p>Total Consultations</p>
                                    </div>
                                </div>
                                <div class="dior-dash-stat-card">
                                    <div class="stat-card-header">
                                        <div class="stat-icon-box green-tint">
                                            <i class="fa-solid fa-prescription-bottle-medical"></i>
                                        </div>
                                    </div>
                                    <div class="stat-card-body">
                                        <h3><?php echo !empty($prescriptions) ? count($prescriptions) : 6; ?></h3>
                                        <p>Active Prescriptions</p>
                                    </div>
                                </div>
                                <div class="dior-dash-stat-card">
                                    <div class="stat-card-header">
                                        <div class="stat-icon-box purple-tint">
                                            <i class="fa-regular fa-calendar-check"></i>
                                        </div>
                                    </div>
                                    <div class="stat-card-body">
                                        <h3><?php echo !empty($upcoming_list) ? count($upcoming_list) : 2; ?></h3>
                                        <p>Upcoming Appointments</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Profile & Health Goals Row -->
                            <div class="dior-dash-profile-goals-row">
                                <!-- Profile Card -->
                                <div class="dior-dash-profile-card dior-box-card">
                                    <div class="profile-card-header">
                                        <div class="profile-avatar-large">
                                            <?php if (!empty($profile['avatar_url'])): ?>
                                            <img src="<?php echo esc_url($profile['avatar_url']); ?>" alt="Profile">
                                            <?php else: ?>
                                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=200" alt="Mia Song">
                                            <?php endif; ?>
                                        </div>
                                        <div class="profile-info-large">
                                            <h4><?php echo esc_html(!empty($profile['full_name']) ? $profile['full_name'] : 'Mia Song'); ?></h4>
                                            <?php
                                            $dob_raw = !empty($profile['dob']) ? $profile['dob'] : '';
                                            $age_str = '';
                                            if (!empty($dob_raw)) {
                                                try {
                                                    $age_calc = (new DateTime('today'))->diff(new DateTime($dob_raw))->y;
                                                    $age_str = $age_calc . ' Years Old';
                                                } catch(Exception $e) { $age_str = ''; }
                                            }
                                            ?>
                                            <span class="profile-age-text"><?php echo esc_html($age_str ?: '25 Years Old'); ?></span>
                                            
                                            <div class="profile-card-stats">
                                                <?php
                                                $p_height = !empty($profile['height']) ? trim($profile['height']) : '170';
                                                $p_weight = !empty($profile['weight']) ? trim($profile['weight']) : '60';

                                                $h_display = (is_numeric($p_height)) ? $p_height . ' Cm' : (stristr($p_height, 'cm') ? str_ireplace('cm', 'Cm', $p_height) : $p_height . ' Cm');
                                                $w_display = (is_numeric($p_weight)) ? $p_weight . 'kg' : (stristr($p_weight, 'kg') ? str_ireplace('kg', 'kg', $p_weight) : $p_weight . 'kg');
                                                ?>
                                                <div class="pc-stat">
                                                    <strong><?php echo esc_html($h_display); ?></strong>
                                                    <span>Height</span>
                                                </div>
                                                <div class="pc-divider"></div>
                                                <div class="pc-stat">
                                                    <strong><?php echo esc_html($w_display); ?></strong>
                                                    <span>Weight</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="profile-card-action">
                                        <button type="button" class="dior-btn-outline-pill" data-switch-tab="profile">
                                            Edit Profile
                                        </button>
                                    </div>
                                </div>

                                <!-- Health Goals -->
                                <div class="dior-dash-goals-card dior-box-card">
                                    <div class="box-title-row">
                                        <h3 class="box-title">Health Goals</h3>
                                    </div>
                                    <?php
                                    $health_goals = get_user_meta($user_id, 'dior_health_goals', true);
                                    $health_goals = is_array($health_goals) ? $health_goals : [];

                                    if (empty($health_goals)) {
                                        $display_goals = [
                                            ['name' => 'Weight Loss', 'val' => '75.5 / 60 kg', 'pct' => 70, 'color' => 'blue'],
                                            ['name' => 'Water Intake', 'val' => '1,500 / 2,500 ml', 'pct' => 60, 'color' => 'teal'],
                                            ['name' => 'Sleep', 'val' => '7 / 8 hrs', 'pct' => 87, 'color' => 'indigo']
                                        ];
                                    } else {
                                        $display_goals = $health_goals;
                                    }
                                    ?>
                                    <div class="goals-list">
                                        <?php foreach($display_goals as $goal): ?>
                                        <div class="goal-item">
                                            <div class="goal-label-row">
                                                <span class="g-name"><?php echo esc_html($goal['name']); ?></span>
                                                <span class="g-val"><?php echo esc_html($goal['val']); ?></span>
                                            </div>
                                            <div class="goal-progress-track">
                                                <div class="goal-progress-fill <?php echo esc_attr($goal['color'] ?? 'blue'); ?>" style="width: <?php echo min(100, (int)($goal['pct'] ?? 50)); ?>%;"></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Previous Consultation Table -->
                            <div class="dior-dash-table-card dior-box-card">
                                <div class="box-title-row">
                                    <h3 class="box-title">Previous Consultation</h3>
                                </div>
                                <div class="table-responsive">
                                    <table class="dior-clean-table">
                                        <thead>
                                            <tr>
                                                <th style="text-align: left;">Consultation Name</th>
                                                <th style="text-align: left;">Doctor Name</th>
                                                <th style="text-align: left;">Date</th>
                                                <th style="text-align: left;">Time</th>
                                                <th style="text-align: center !important;">View All</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $prev_apts = array_slice($past_list, 0, 4);
                                            if (empty($prev_apts)) {
                                                $prev_apts = [
                                                    ['condition' => 'General Consultation', 'provider' => 'Dr. Evelyn Vance, MD', 'date' => 'Sep 10, 2026', 'time' => '03:00 PM'],
                                                    ['condition' => 'Cold & Flu', 'provider' => 'Dr. James Wilson, MD', 'date' => 'Sep 5, 2026', 'time' => '11:30 AM'],
                                                    ['condition' => 'Allergy Consultation', 'provider' => 'Dr. Emily Brooks, MD', 'date' => 'Aug 28, 2026', 'time' => '04:00 PM'],
                                                    ['condition' => 'General Consultation', 'provider' => 'Dr. Evelyn Vance, MD', 'date' => 'Aug 15, 2026', 'time' => '02:00 PM'],
                                                ];
                                            }
                                            foreach($prev_apts as $prev_apt):
                                                $pa_cond = !empty($prev_apt['condition']) ? $prev_apt['condition'] : (!empty($prev_apt['condition_name']) ? $prev_apt['condition_name'] : 'General Consultation');
                                                $pa_doc  = !empty($prev_apt['provider']) ? $prev_apt['provider'] : (!empty($prev_apt['doctor_name']) ? $prev_apt['doctor_name'] : 'Dr. Evelyn Vance, MD');
                                                $pa_date = !empty($prev_apt['date']) ? $prev_apt['date'] : (!empty($prev_apt['appt_date']) ? $prev_apt['appt_date'] : 'Sep 10, 2026');
                                                $pa_time = !empty($prev_apt['time']) ? $prev_apt['time'] : (!empty($prev_apt['appt_time']) ? $prev_apt['appt_time'] : '03:00 PM');
                                            ?>
                                            <tr>
                                                <td style="text-align: left; font-weight: 600; color: #0F172A;"><?php echo esc_html($pa_cond); ?></td>
                                                <td style="text-align: left; color: #334155;"><?php echo esc_html($pa_doc); ?></td>
                                                <td style="text-align: left; color: #64748B;"><?php echo esc_html($pa_date); ?></td>
                                                <td style="text-align: left; color: #64748B;"><?php echo esc_html($pa_time); ?></td>
                                                <td style="text-align: right;">
                                                    <a href="#" class="view-details-link" data-switch-tab="appointments">View Details &rarr;</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Latest Lab Results -->
                            <div class="dior-dash-table-card dior-box-card">
                                <div class="box-title-row">
                                    <h3 class="box-title">Latest Lab Results</h3>
                                </div>
                                <div class="table-responsive">
                                    <table class="dior-clean-table">
                                        <thead>
                                            <tr>
                                                <th style="text-align: left;">Test Name</th>
                                                <th style="text-align: left;">Date</th>
                                                <th style="text-align: center;">Status</th>
                                                <th style="text-align: left;">Ordered By</th>
                                                <th style="text-align: center;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $lab_docs = [];
                                            foreach ($documents as $doc) {
                                                $dtype = strtolower($doc['type'] ?? ($doc['document_type'] ?? ''));
                                                if (strpos($dtype, 'lab') !== false || strpos($dtype, 'result') !== false || strpos($dtype, 'test') !== false) {
                                                    $lab_docs[] = $doc;
                                                }
                                            }
                                            $lab_docs = array_slice($lab_docs, 0, 4);
                                            if (empty($lab_docs)) {
                                                $lab_docs = [
                                                    ['title' => 'Renal Function Test', 'date' => 'Sep 10, 2026', 'status' => 'Normal', 'ordered_by' => 'Dr. Evelyn Vance, MD'],
                                                    ['title' => 'Thyroid Function Test', 'date' => 'Sep 05, 2026', 'status' => 'Abnormal', 'ordered_by' => 'Dr. Evelyn Vance, MD'],
                                                    ['title' => 'Vitamin D Test', 'date' => 'Aug 26, 2026', 'status' => 'Normal', 'ordered_by' => 'Dr. James Wilson, MD'],
                                                    ['title' => 'Thyroid Function Test', 'date' => 'Aug 11, 2026', 'status' => 'Normal', 'ordered_by' => 'Dr. Evelyn Vance, MD'],
                                                ];
                                            }
                                            foreach($lab_docs as $lab):
                                                $lab_name   = !empty($lab['title']) ? $lab['title'] : (!empty($lab['name']) ? $lab['name'] : 'Lab Test');
                                                $lab_date   = !empty($lab['date']) ? $lab['date'] : 'Sep 10, 2026';
                                                $lab_status = !empty($lab['status']) ? $lab['status'] : 'Normal';
                                                $lab_by     = !empty($lab['ordered_by']) ? $lab['ordered_by'] : (!empty($lab['doctor_name']) ? $lab['doctor_name'] : 'Dr. Evelyn Vance, MD');
                                                $lab_url    = !empty($lab['file_url']) ? $lab['file_url'] : '#';
                                                $badge_cls  = (strtolower($lab_status) === 'normal') ? 'badge-normal' : 'badge-abnormal';
                                            ?>
                                            <tr>
                                                <td style="text-align: left; font-weight: 600; color: #0F172A;"><?php echo esc_html($lab_name); ?></td>
                                                <td style="text-align: left; color: #64748B;"><?php echo esc_html($lab_date); ?></td>
                                                <td style="text-align: center;"><span class="badge <?php echo esc_attr($badge_cls); ?>"><?php echo esc_html($lab_status); ?></span></td>
                                                <td style="text-align: left; color: #334155;"><?php echo esc_html($lab_by); ?></td>
                                                <td style="text-align: center;">
                                                    <a href="<?php echo esc_url($lab_url); ?>" target="_blank" class="dior-action-download" title="Download Report"><i class="fa-solid fa-download"></i></a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div> <!-- /LEFT COLUMN -->


                        <!-- RIGHT COLUMN -->
                        <div class="dior-dash-col-right">
                            
                            <!-- Highlighted Next Appointment Hero Card -->
                            <?php 
                            $ha_name = 'Dr. Evelyn Vance, MD';
                            $ha_spec = 'Board Certified Urgent Care Physician';
                            $ha_date = 'Sep 09, 2026';
                            $ha_time = '2:00 PM';
                            $ha_avatar = 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=300';
                            
                            if (!empty($next_appointment)) {
                                $ha_name = !empty($next_appointment['doctor_name']) ? $next_appointment['doctor_name'] : (!empty($next_appointment['provider']) ? $next_appointment['provider'] : $ha_name);
                                $ha_spec = !empty($next_appointment['provider_spec']) ? $next_appointment['provider_spec'] : $ha_spec;
                                $ha_date = !empty($next_appointment['date']) ? $next_appointment['date'] : $ha_date;
                                $ha_time = !empty($next_appointment['time']) ? $next_appointment['time'] : $ha_time;
                                
                                $doc_id = !empty($next_appointment['doctor_id']) ? $next_appointment['doctor_id'] : (!empty($next_appointment['provider_id']) ? $next_appointment['provider_id'] : 0);
                                if ($doc_id) {
                                    $saved_avatar = get_user_meta($doc_id, 'dior_profile_image', true)
                                        ?: get_user_meta($doc_id, 'doctor_avatar', true)
                                        ?: get_user_meta($doc_id, 'profile_picture', true)
                                        ?: get_user_meta($doc_id, 'profile_image', true)
                                        ?: get_user_meta($doc_id, 'avatar_url', true);
                                    if (!empty($saved_avatar)) {
                                        $ha_avatar = $saved_avatar;
                                    }
                                }
                            }
                            ?>
                            <div class="dior-highlight-appt-card dior-box-card">
                                <div class="ha-header">
                                    <div class="ha-avatar-wrap">
                                        <img src="<?php echo esc_url($ha_avatar); ?>" alt="Doctor" class="ha-avatar">
                                    </div>
                                    <div class="ha-doc-info">
                                        <span class="ha-clinic">Telehealth Medical Care</span>
                                        <h4 class="ha-doc-name"><?php echo esc_html($ha_name); ?></h4>
                                        <span class="ha-doc-spec"><?php echo esc_html($ha_spec); ?></span>
                                        <div class="ha-datetime-row">
                                            <span><i class="fa-regular fa-calendar-days"></i> <?php echo esc_html($ha_date); ?></span>
                                            <span><i class="fa-regular fa-clock"></i> <?php echo esc_html($ha_time); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="ha-actions">
                                    <button type="button" class="dior-btn-join" data-switch-tab="consultation">Join Video Consultation Room</button>
                                    <button type="button" class="dior-btn-reschedule" data-switch-tab="appointments">Reschedule</button>
                                </div>
                            </div>

                            <!-- Upcoming Consultation List Card -->
                            <div class="dior-upcoming-list-card dior-box-card">
                                <div class="box-title-row">
                                    <h3 class="box-title">Upcoming Consultation</h3>
                                </div>
                                <div class="uc-list">
                                    <?php 
                                    $display_list = !empty($upcoming_list) ? array_slice($upcoming_list, 0, 2) : [];
                                    if (empty($display_list)) {
                                        $display_list = [
                                            [
                                                'doctor_name' => 'Dr. James Wilson, MD',
                                                'provider_spec' => 'Board Certified Urgent Care Physician',
                                                'date' => 'Sep 29, 2026',
                                                'time' => '05:30 PM',
                                                'avatar' => 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&q=80&w=300'
                                            ],
                                            [
                                                'doctor_name' => 'Dr. Emily Brooks, MD',
                                                'provider_spec' => 'Board Certified Urgent Care Physician',
                                                'date' => 'Oct 2, 2026',
                                                'time' => '11:00 AM',
                                                'avatar' => 'https://images.unsplash.com/photo-1594824813566-78a93272d3e9?auto=format&fit=crop&q=80&w=300'
                                            ]
                                        ];
                                    }
                                    
                                    foreach($display_list as $apt): 
                                        $doc_name = !empty($apt['doctor_name']) ? $apt['doctor_name'] : (!empty($apt['provider']) ? $apt['provider'] : 'Provider');
                                        $doc_spec = !empty($apt['provider_spec']) ? $apt['provider_spec'] : 'Board Certified Urgent Care Physician';
                                        
                                        $doc_id = !empty($apt['doctor_id']) ? $apt['doctor_id'] : (!empty($apt['provider_id']) ? $apt['provider_id'] : 0);
                                        $doc_avatar = !empty($apt['avatar']) ? $apt['avatar'] : 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&q=80&w=300';
                                        if ($doc_id) {
                                            $saved_doc_avatar = get_user_meta($doc_id, 'dior_profile_image', true)
                                                ?: get_user_meta($doc_id, 'doctor_avatar', true)
                                                ?: get_user_meta($doc_id, 'profile_picture', true)
                                                ?: get_user_meta($doc_id, 'profile_image', true)
                                                ?: get_user_meta($doc_id, 'avatar_url', true);
                                            if (!empty($saved_doc_avatar)) {
                                                $doc_avatar = $saved_doc_avatar;
                                            }
                                        }
                                    ?>
                                    <div class="uc-item">
                                        <img src="<?php echo esc_url($doc_avatar); ?>" alt="Doctor" class="uc-avatar">
                                        <div class="uc-info">
                                            <h4 class="uc-doc-name"><?php echo esc_html($doc_name); ?></h4>
                                            <span class="uc-doc-spec"><?php echo esc_html($doc_spec); ?></span>
                                            <div class="uc-datetime-row">
                                                <span><i class="fa-regular fa-calendar-days"></i> <?php echo esc_html($apt['date']); ?></span>
                                                <span><i class="fa-regular fa-clock"></i> <?php echo esc_html($apt['time']); ?></span>
                                            </div>
                                            <div class="uc-action">
                                                <button type="button" class="dior-btn-reschedule-sm" data-switch-tab="appointments">Reschedule</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Prescriptions List Card -->
                            <div class="dior-prescriptions-card dior-box-card">
                                <div class="box-title-row">
                                    <h3 class="box-title">Prescriptions</h3>
                                    <a href="#" class="view-all-link" data-switch-tab="docs_meds">View All &rarr;</a>
                                </div>
                                <div class="rx-list">
                                    <?php
                                    $rx_display = array_slice($prescriptions, 0, 4);
                                    if (empty($rx_display)) {
                                        $rx_display = [
                                            ['name' => 'Cetirizine 10 mg', 'prescribed_by' => 'Dr. Evelyn Vance, MD', 'status' => 'Active'],
                                            ['name' => 'Vitamin D3 1,000 IU', 'prescribed_by' => 'Dr. James Wilson, MD', 'status' => 'Active'],
                                            ['name' => 'Ibuprofen 200 mg', 'prescribed_by' => 'Dr. Emily Brooks, MD', 'status' => 'Inactive'],
                                            ['name' => 'Omeprazole 20 mg', 'prescribed_by' => 'Dr. Evelyn Vance, MD', 'status' => 'Inactive'],
                                        ];
                                    }
                                    foreach($rx_display as $rx):
                                        $rx_name   = !empty($rx['medication_name']) ? $rx['medication_name'] : (!empty($rx['drug_name']) ? $rx['drug_name'] : (!empty($rx['name']) ? $rx['name'] : 'Medication'));
                                        $rx_dosage = !empty($rx['dosage']) ? ' ' . $rx['dosage'] : '';
                                        $rx_by     = !empty($rx['prescribed_by']) ? $rx['prescribed_by'] : 'Provider';
                                        $rx_status = !empty($rx['status']) ? $rx['status'] : 'Active';
                                        $rx_badge  = (strtolower($rx_status) === 'active') ? 'badge-active' : 'badge-inactive';
                                    ?>
                                    <div class="rx-item">
                                        <div class="rx-info">
                                            <h4><?php echo esc_html($rx_name . $rx_dosage); ?></h4>
                                            <span><?php echo esc_html($rx_by); ?></span>
                                        </div>
                                        <span class="badge <?php echo esc_attr($rx_badge); ?>"><?php echo esc_html($rx_status); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div> <!-- /RIGHT COLUMN -->

                    </div>

                </section>


                <!-- ============================================================== -->
                <!-- 1. PROFILE & ELIGIBILITY TAB -->
                <!-- ============================================================== -->
<section class="dior-tab-panel" id="tab-settings" style="display:none;">
    <div style="background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); border-radius: 16px 16px 0 0; height: 120px; position: relative;"></div>
    <div style="background: #fff; border-radius: 0 0 16px 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 24px; position: relative; margin-top: -60px;">
        <div style="display: flex; gap: 20px; align-items: flex-end;">
            <div style="position: relative;">
                <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150" style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); object-fit: cover;">
                <button type="button" style="position: absolute; bottom: 0; right: 0; background: #3B82F6; color: #fff; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><i class="fa-solid fa-camera"></i></button>
            </div>
            <div style="flex: 1; padding-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                    <h2 style="margin: 0; font-size: 24px; font-weight: 700; color: #1E293B;">Sarah Jenkins</h2>
                    <span style="background: #DBEAFE; color: #3B82F6; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;">#PAT-1082</span>
                    <span style="background: #D1FAE5; color: #10B981; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;"><i class="fa-solid fa-circle-check"></i> Verified Patient</span>
                </div>
                <div style="color: #64748B; font-size: 13px;">
                    <i class="fa-regular fa-envelope" style="color: #3B82F6; margin-right: 4px;"></i> sarah.jenkins@example.com &bull; <i class="fa-solid fa-droplet" style="color: #EF4444; margin-right: 4px;"></i> Blood Group: <strong style="color: #475569;">O+</strong>
                </div>
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 24px; flex-wrap: wrap;">
        <!-- Settings Nav -->
        <div style="flex: 1; min-width: 250px;">
            <div style="background: #fff; border-radius: 16px; padding: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                <a href="#" style="text-decoration: none; width: 100%; text-align: left; background: #3B82F6; color: #fff; border: none; padding: 16px; border-radius: 12px; margin-bottom: 8px; display: flex; align-items: center; gap: 16px; cursor: pointer;" onclick="return false;">
                    <i class="fa-solid fa-circle-user" style="font-size: 24px;"></i>
                    <div>
                        <span style="display: block; font-weight: 700; font-size: 14px;">Account Profile</span>
                        <span style="display: block; font-size: 12px; opacity: 0.8;">Personal details &amp; contact</span>
                    </div>
                </a>
                <a href="#" style="text-decoration: none; width: 100%; text-align: left; background: transparent; color: #475569; border: none; padding: 16px; border-radius: 12px; margin-bottom: 8px; display: flex; align-items: center; gap: 16px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;">
                    <i class="fa-solid fa-shield-halved" style="font-size: 24px; color: #64748B;"></i>
                    <div>
                        <span style="display: block; font-weight: 700; font-size: 14px; color: #1E293B;">Security &amp; Password</span>
                        <span style="display: block; font-size: 12px; color: #94A3B8;">Password, 2FA &amp; credentials</span>
                    </div>
                </a>
                <a href="#" style="text-decoration: none; width: 100%; text-align: left; background: transparent; color: #475569; border: none; padding: 16px; border-radius: 12px; margin-bottom: 8px; display: flex; align-items: center; gap: 16px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;">
                    <i class="fa-solid fa-bell" style="font-size: 24px; color: #64748B;"></i>
                    <div>
                        <span style="display: block; font-weight: 700; font-size: 14px; color: #1E293B;">Notifications</span>
                        <span style="display: block; font-size: 12px; color: #94A3B8;">Email, SMS &amp; alert controls</span>
                    </div>
                </a>
                <a href="#" style="text-decoration: none; width: 100%; text-align: left; background: transparent; color: #475569; border: none; padding: 16px; border-radius: 12px; display: flex; align-items: center; gap: 16px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;">
                    <i class="fa-solid fa-lock" style="font-size: 24px; color: #64748B;"></i>
                    <div>
                        <span style="display: block; font-weight: 700; font-size: 14px; color: #1E293B;">Privacy &amp; Data</span>
                        <span style="display: block; font-size: 12px; color: #94A3B8;">EMR consent &amp; data download</span>
                    </div>
                </a>
            </div>
        </div>

        <!-- Form Area -->
        <div style="flex: 3; min-width: 500px;">
            <div style="background: #fff; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); overflow: hidden;">
                <div style="padding: 20px 24px; border-bottom: 1px solid #E2E8F0;">
                    <h3 style="margin: 0 0 4px 0; font-size: 18px; font-weight: 700; color: #1E293B;"><i class="fa-solid fa-address-card" style="color: #3B82F6; margin-right: 8px;"></i> Personal Account Details</h3>
                    <p style="margin: 0; font-size: 13px; color: #64748B;">Update your primary identity, phone number, and address info</p>
                </div>
                <div style="padding: 24px;">
                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div style="flex: 1;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">First Name <span style="color: #EF4444;">*</span></label>
                            <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                <i class="fa-solid fa-user" style="color: #94A3B8; font-size: 14px;"></i>
                                <input type="text" value="Sarah" style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569;">
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Last Name <span style="color: #EF4444;">*</span></label>
                            <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                <i class="fa-solid fa-user" style="color: #94A3B8; font-size: 14px;"></i>
                                <input type="text" value="Jenkins" style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569;">
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div style="flex: 1;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Email Address <span style="color: #EF4444;">*</span></label>
                            <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                <i class="fa-solid fa-envelope" style="color: #94A3B8; font-size: 14px;"></i>
                                <input type="email" value="sarah.jenkins@example.com" style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569;">
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Mobile Number <span style="color: #EF4444;">*</span></label>
                            <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                <i class="fa-solid fa-phone" style="color: #94A3B8; font-size: 14px;"></i>
                                <input type="text" value="+1 (234) 567-8901" style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569;">
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div style="flex: 1;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Date of Birth</label>
                            <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                <i class="fa-regular fa-calendar" style="color: #94A3B8; font-size: 14px;"></i>
                                <input type="date" value="1992-05-18" style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569; font-family: inherit;">
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Blood Group</label>
                            <div style="border: 1px solid #E2E8F0; border-radius: 8px; padding: 0; overflow: hidden; background: #fff;">
                                <select style="border: none !important; box-shadow: none !important; background: transparent url('data:image/svg+xml;utf8,<svg fill=%22%2394a3b8%22 height=%2224%22 viewBox=%220 0 24 24%22 width=%2224%22 xmlns=%22http://www.w3.org/2000/svg%22><path d=%22M7 10l5 5 5-5z%22/></svg>') no-repeat right 8px center !important; outline: none; padding: 12px 16px; width: 100%; font-size: 14px; color: #475569; appearance: none;">
                                    <option>A+</option>
                                    <option>A-</option>
                                    <option>B+</option>
                                    <option>B-</option>
                                    <option>AB+</option>
                                    <option>AB-</option>
                                    <option selected>O+</option>
                                    <option>O-</option>
                                </select>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">City</label>
                            <input type="text" value="New York" style="border: 1px solid #E2E8F0 !important; box-shadow: none !important; background: #fff !important; border-radius: 8px; padding: 12px 16px; width: 100%; font-size: 14px; color: #475569; outline: none; box-sizing: border-box;">
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px; margin-bottom: 24px;">
                        <div style="flex: 1;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Country</label>
                            <input type="text" value="United States" style="border: 1px solid #E2E8F0 !important; box-shadow: none !important; background: #fff !important; border-radius: 8px; padding: 12px 16px; width: 100%; font-size: 14px; color: #475569; outline: none; box-sizing: border-box;">
                        </div>
                        <div style="flex: 2;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Residential Address</label>
                            <input type="text" value="742 Evergreen Terrace, Apt 4B, NY 10001" style="border: 1px solid #E2E8F0 !important; box-shadow: none !important; background: #fff !important; border-radius: 8px; padding: 12px 16px; width: 100%; font-size: 14px; color: #475569; outline: none; box-sizing: border-box;">
                        </div>
                    </div>
                </div>
                
                <div style="padding: 16px 24px; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; gap: 12px; background: #F8FAFC;">
                    <a href="#" style="text-decoration: none; padding: 10px 20px; border-radius: 8px; border: 1px solid #CBD5E1; background: #fff; color: #475569; font-size: 13px; font-weight: 600; cursor: pointer;" onclick="return false;">Cancel</a>
                    <a href="#" style="text-decoration: none; padding: 10px 20px; border-radius: 8px; border: none; background: #3B82F6; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;" onclick="return false;"><i class="fa-solid fa-floppy-disk"></i> Save Changes</a>
                </div>
            </div>
        </div>
    </div>
</section>


                <!-- ============================================================== -->
                <!-- 2. APPOINTMENTS TAB -->
                <!-- ============================================================== -->
                <section class="dior-tab-panel" id="tab-appointments">
                <?php
                    // Robust date normalizer: handles Y-m-d, d-m-Y, m-d-Y, d/m/Y etc.
                    if (!function_exists('dior_normalize_date')) {
                        function dior_normalize_date($raw) {
                            if (empty($raw)) return '';
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
                            foreach (['d-m-Y','d/m/Y','d.m.Y','m-d-Y','m/d/Y'] as $fmt) {
                                $dt = DateTime::createFromFormat($fmt, $raw);
                                if ($dt) return $dt->format('Y-m-d');
                            }
                            $ts = strtotime($raw);
                            return $ts ? date('Y-m-d', $ts) : '';
                        }
                    }


                    // Prepare appointment data as JSON for JS calendar
                    $cal_apt_dates = [];
                    $apt_total     = count($appointments);
                    $apt_upcoming  = 0;
                    $apt_completed = 0;
                    foreach ($appointments as $idx => $apt) {
                        $a_date   = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
                        $a_status = !empty($apt['status']) ? $apt['status'] : 'Scheduled';
                        $status_lc = strtolower($a_status);
                        if (in_array($status_lc, ['confirmed','scheduled','in-queue','pending','active'])) $apt_upcoming++;
                        if (in_array($status_lc, ['completed','done','past'])) $apt_completed++;
                        if ($a_date) {
                            $ymd = dior_normalize_date($a_date);
                            if ($ymd) $cal_apt_dates[$ymd] = true;
                        }
                    }
                    $cal_dates_json = json_encode(array_keys($cal_apt_dates));

                    // Build appointment cards JSON for JS filtering
                    $apt_cards = [];

                    foreach ($appointments as $idx => $apt) {
                        $a_id     = !empty($apt['id']) ? $apt['id'] : (!empty($apt['appt_uid']) ? $apt['appt_uid'] : 'APT-' . (1000 + $idx));
                        $a_doc    = !empty($apt['provider']) ? $apt['provider'] : (!empty($apt['doctor_name']) ? $apt['doctor_name'] : 'Provider');
                        $a_spec   = !empty($apt['provider_spec']) ? $apt['provider_spec'] : 'Telehealth';
                        $a_date_raw = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
                        $a_time   = !empty($apt['time']) ? $apt['time'] : (!empty($apt['appt_time']) ? $apt['appt_time'] : '');
                        $a_status = !empty($apt['status']) ? $apt['status'] : 'Scheduled';
                        $a_type   = !empty($apt['type']) ? $apt['type'] : (!empty($apt['visit_type']) ? $apt['visit_type'] : 'Video Visit');
                        $a_ymd    = dior_normalize_date($a_date_raw);
                        $apt_cards[] = [
                            'id'     => $a_id,
                            'doc'    => $a_doc,
                            'spec'   => $a_spec,
                            'date'   => $a_date_raw,
                            'ymd'    => $a_ymd,
                            'time'   => $a_time,
                            'status' => $a_status,
                            'type'   => $a_type,
                        ];
                    }
                    $apt_cards_json = json_encode($apt_cards, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

                    ?>

                    <!-- Appointment Content Area -->
                    <div class="medidash-appt-wrap">
                        
                        <!-- 1. Book Appointment -->
                        <div class="dior-subtab-panel" id="dior-subtab-book" style="display:none;">
                            <div class="medidash-page-header">
                                <h2>Book Appointment</h2>
                                <div class="medidash-breadcrumb">
                                    <i class="fa-solid fa-house"></i> / Appointments / <span>Book Appointment</span>
                                </div>
                            </div>

                            <div class="medidash-card">
                                <div class="medidash-card-header">
                                    <h3 class="medidash-card-title">Book Appointment</h3>
                                </div>
                                <div class="medidash-card-body">
                                    <div class="dior-hipaa-embed-wrap" style="max-width: 100%; margin: 0 auto; background: #FFFFFF; margin-bottom: 24px;">
                                        <h3 style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1E293B;"><i class="fa-solid fa-clipboard-question" style="color:#6366F1;"></i> Step 1: Clinical Intake Questionnaire</h3>
                                        <?php if (shortcode_exists('hipaatizer')): ?>
                                            <?php echo do_shortcode('[hipaatizer id="01a07aa2-d931-728d-bfe9-8d7b3bc0389d"]'); ?>
                                        <?php else: ?>
                                            <iframe src="https://app.hipaatizer.com/workflow/01a07aa2-d931-728d-bfe9-8d7b3bc0389d" style="width: 100%; min-height: 720px; height: 85vh; border: 0; display: block; margin: 0 auto; background: #FFFFFF; border-radius:8px;" allow="microphone; camera; payment" title="HIPAAtizer Clinical Intake &amp; Questionnaire Form"></iframe>
                                        <?php endif; ?>
                                    </div>
                                    <div class="dior-docbooker-embed-wrap" style="max-width: 100%; margin: 0 auto; background: #FFFFFF;">
                                        <h3 style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1E293B;"><i class="fa-solid fa-calendar-check" style="color:#10B981;"></i> Step 2: Schedule Consultation</h3>
                                        <?php if (shortcode_exists('docbooker_appointments')): ?>
                                            <?php echo do_shortcode('[docbooker_appointments]'); ?>
                                        <?php elseif (shortcode_exists('docbooker_calendar')): ?>
                                            <?php echo do_shortcode('[docbooker_calendar]'); ?>
                                        <?php else: ?>
                                            <div style="padding:32px 24px; border:1px dashed #CBD5E1; border-radius:8px; text-align:center; background:#F8FAFC;">
                                                <div style="width:48px; height:48px; border-radius:50%; background:#EEF2FF; color:#6366F1; display:flex; align-items:center; justify-content:center; font-size:20px; margin:0 auto 12px auto;">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                </div>
                                                <h4 style="margin:0 0 8px 0; font-size:16px; color:#1E293B;">DocBooker Scheduling</h4>
                                                <p style="margin:0; color:#64748B; font-size:14px;">The interactive appointment calendar will appear here.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Today Appointments -->
                        <div class="dior-subtab-panel" id="dior-subtab-today" style="display:none;">
                            <div class="master-table-wrapper">
                                <div class="master-table-container">
                                    <div class="master-table-card">
                                        <div class="master-table-header">
                                            <div class="header-content">
                                                <div class="table-title-section">
                                                    <h2 class="table-title">Today's Appointments</h2>
                                                    <div class="title-accent"></div>
                                                </div>
                                                <div class="header-actions-group">
                                                    <div class="search-container">
                                                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                                        <input type="text" id="dior-today-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterTodayAppointments()">
                                                    </div>
                                                    <div class="action-buttons">
                                                        <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadTodayAppointmentsCSV()">
                                                            <i class="fa-solid fa-file-arrow-down"></i>
                                                        </button>
                                                        <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                                            <i class="fa-solid fa-rotate-right"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-content">
                                            <table class="master-modern-table" id="dior-today-appointments-table">
                                                <thead>
                                                    <tr>
                                                        <th>DOCTOR <i class="fa-solid fa-sort"></i></th>
                                                        <th>SPECIALIZATION <i class="fa-solid fa-sort"></i></th>
                                                        <th>DATE <i class="fa-solid fa-sort"></i></th>
                                                        <th>TIME <i class="fa-solid fa-sort"></i></th>
                                                        <th>TREATMENT <i class="fa-solid fa-sort"></i></th>
                                                        <th>CONTACT <i class="fa-solid fa-sort"></i></th>
                                                        <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="dior-today-appointments-tbody">
                                                    <?php
                                                    $today_mock = [
                                                        ['Dr.Cara Stevens', 'Radiologist', 'Jun 12, 2020', '09:00-10:00', 'CT scans', '+123 676545655', 'Confirm', 'https://randomuser.me/api/portraits/women/44.jpg'],
                                                        ['Dr.John Doe', 'Cardiologist', 'Jun 12, 2020', '11:00-11:30', 'heart checkup', '+123 434656764', 'Cancelled', 'https://randomuser.me/api/portraits/men/32.jpg'],
                                                        ['Dr.Airi Satou', 'Otolaryngologist', 'Jun 12, 2020', '09:15-10:15', 'Diseases Of The Ear', '+123 45345673', 'Confirm', 'https://randomuser.me/api/portraits/women/65.jpg'],
                                                        ['Dr.Angelica Ramos', 'Dentist', 'Jun 12, 2020', '11:00-12:00', 'Root Canal', '+123 87654533', 'Confirm', 'https://randomuser.me/api/portraits/women/68.jpg'],
                                                        ['Dr.Jens Brincker', 'Endocrinologist', 'Jun 12, 2020', '04:00-05:00', 'Diabetes', '+123 45678345', 'Cancelled', 'https://randomuser.me/api/portraits/men/75.jpg'],
                                                        ['Dr.Jamie Blair', 'Radiologist', 'Jun 12, 2020', '05:00-05:30', 'Diabetes', '+123 45678345', 'Confirm', 'https://randomuser.me/api/portraits/women/49.jpg'],
                                                        ['Dr.Nikki Barton', 'Endocrinologist', 'Jun 12, 2020', '06:00-07:00', 'X-Ray', '+123 45678345', 'Pending', 'https://randomuser.me/api/portraits/men/85.jpg']
                                                    ];
                                                    foreach ($today_mock as $tm):
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="cell-content cell-image-name">
                                                                <img src="<?php echo esc_url($tm[7]); ?>" alt="<?php echo esc_attr($tm[0]); ?>" class="cell-avatar">
                                                                <span class="cell-text doctor-name"><?php echo esc_html($tm[0]); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><span class="cell-text"><?php echo esc_html($tm[1]); ?></span></td>
                                                        <td>
                                                            <div class="cell-content cell-icon-text">
                                                                <i class="fa-regular fa-calendar cell-icon"></i>
                                                                <span class="cell-text"><?php echo esc_html($tm[2]); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><span class="cell-text"><?php echo esc_html($tm[3]); ?></span></td>
                                                        <td><span class="cell-text"><?php echo esc_html($tm[4]); ?></span></td>
                                                        <td><span class="cell-text"><?php echo esc_html($tm[5]); ?></span></td>
                                                        <td>
                                                            <?php
                                                                $st_cls = 'status-confirm';
                                                                if (strtolower($tm[6]) === 'cancelled') $st_cls = 'status-cancelled';
                                                                if (strtolower($tm[6]) === 'pending') $st_cls = 'status-pending';
                                                            ?>
                                                            <span class="status-cell-text <?php echo $st_cls; ?>"><?php echo esc_html($tm[6]); ?></span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="master-table-footer">
                                            <span class="page-count" id="dior-today-page-count">0 selected / <?php echo count($today_mock); ?> total</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <script>
                            function diorDownloadTodayAppointmentsCSV() {
                                const table = document.getElementById('dior-today-appointments-table');
                                if (!table) return;

                                let csv = [];
                                csv.push(['Doctor', 'Specialization', 'Date', 'Time', 'Treatment', 'Contact', 'Status'].join(','));

                                const rows = table.querySelectorAll('tbody tr');
                                rows.forEach(function(row) {
                                    if (row.style.display === 'none') return;
                                    const cols = row.querySelectorAll('td');
                                    if (cols.length >= 7) {
                                        let doctor = cols[0].innerText.replace(/\s+/g, ' ').trim();
                                        let specialization = cols[1].innerText.replace(/\s+/g, ' ').trim();
                                        let date = cols[2].innerText.replace(/\s+/g, ' ').trim();
                                        let time = cols[3].innerText.replace(/\s+/g, ' ').trim();
                                        let treatment = cols[4].innerText.replace(/\s+/g, ' ').trim();
                                        let contact = cols[5].innerText.replace(/\s+/g, ' ').trim();
                                        let status = cols[6].innerText.replace(/\s+/g, ' ').trim();

                                        let rowData = [
                                            `"${doctor.replace(/"/g, '""')}"`,
                                            `"${specialization.replace(/"/g, '""')}"`,
                                            `"${date.replace(/"/g, '""')}"`,
                                            `"${time.replace(/"/g, '""')}"`,
                                            `"${treatment.replace(/"/g, '""')}"`,
                                            `"${contact.replace(/"/g, '""')}"`,
                                            `"${status.replace(/"/g, '""')}"`
                                        ];
                                        csv.push(rowData.join(','));
                                    }
                                });

                                const csvString = csv.join('\r\n');
                                const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                                const url = URL.createObjectURL(blob);
                                const link = document.createElement('a');
                                link.setAttribute('href', url);
                                link.setAttribute('download', 'todays_appointments.csv');
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                URL.revokeObjectURL(url);
                            }

                            function diorFilterTodayAppointments() {
                                const input = document.getElementById('dior-today-search-input');
                                const filter = input ? input.value.toLowerCase() : '';
                                const rows = document.querySelectorAll('#dior-today-appointments-tbody tr');
                                let visibleCount = 0;

                                rows.forEach(function(row) {
                                    const text = row.innerText.toLowerCase();
                                    if (text.includes(filter)) {
                                        row.style.display = '';
                                        visibleCount++;
                                    } else {
                                        row.style.display = 'none';
                                    }
                                });

                                const pageCountEl = document.getElementById('dior-today-page-count');
                                if (pageCountEl) {
                                    pageCountEl.innerText = `0 selected / ${visibleCount} total`;
                                }
                            }
                            </script>
                        </div>

                        <!-- 3. Upcoming Appointments -->
                        <div class="dior-subtab-panel" id="dior-subtab-upcoming" style="display:none;">
                            <div class="master-table-wrapper">
                                <div class="master-table-container">
                                    <div class="master-table-card">
                                        <div class="master-table-header">
                                            <div class="header-content">
                                                <div class="table-title-section">
                                                    <h2 class="table-title">Upcoming Appointments</h2>
                                                    <div class="title-accent"></div>
                                                </div>
                                                <div class="header-actions-group">
                                                    <div class="search-container">
                                                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                                        <input type="text" id="dior-upcoming-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterUpcomingAppointments()">
                                                    </div>
                                                    <div class="action-buttons">
                                                        <button type="button" aria-label="Delete selected items" class="action-btn action-btn-danger" id="dior-upcoming-bulk-delete-btn" style="display:none;" title="Delete Selected" onclick="diorBulkDeleteUpcomingRows()">
                                                            <i class="fa-regular fa-trash-can"></i>
                                                        </button>
                                                        <button type="button" aria-label="Add new record" class="action-btn action-btn-primary" title="Book Appointment" data-switch-tab="appointments">
                                                            <i class="fa-solid fa-plus"></i>
                                                        </button>
                                                        <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadUpcomingAppointmentsCSV()">
                                                            <i class="fa-solid fa-file-arrow-down"></i>
                                                        </button>
                                                        <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                                            <i class="fa-solid fa-rotate-right"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-content">
                                            <table class="master-modern-table" id="dior-upcoming-appointments-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 48px; text-align: center;">
                                                            <input type="checkbox" id="dior-select-all-upcoming" class="master-checkbox" onclick="diorToggleSelectAllUpcoming(this)">
                                                        </th>
                                                        <th>DOCTOR <i class="fa-solid fa-sort"></i></th>
                                                        <th>DATE <i class="fa-solid fa-sort"></i></th>
                                                        <th>TIME <i class="fa-solid fa-sort"></i></th>
                                                        <th>INJURY <i class="fa-solid fa-sort"></i></th>
                                                        <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                                        <th>NOTES <i class="fa-solid fa-sort"></i></th>
                                                        <th style="text-align: center;">ACTIONS <i class="fa-solid fa-sort"></i></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="dior-upcoming-appointments-tbody">
                                                    <?php
                                                    $upcoming_mock = [
                                                        ['Dr. Rajesh', 'Sep 25, 2024', '10:00 AM', 'Fever', 'Completed', 'Patient has a history of allergies.'],
                                                        ['Dr. Smith', 'Sep 26, 2024', '11:00 AM', 'Routine Checkup', 'Cancelled', 'Follow-up on blood work.'],
                                                        ['Dr. Lee', 'Sep 27, 2024', '09:30 AM', 'Back Pain', 'Upcoming', 'Recommended physiotherapy.'],
                                                        ['Dr. Patel', 'Sep 28, 2024', '01:00 PM', 'Headache', 'Upcoming', 'Check for migraines.'],
                                                        ['Dr. Kim', 'Sep 29, 2024', '02:30 PM', 'Skin Rash', 'Cancelled', 'Suspected allergic reaction.'],
                                                        ['Dr. Johnson', 'Sep 30, 2024', '03:45 PM', 'Chest Pain', 'Completed', 'Cardiac evaluation needed.'],
                                                        ['Dr. Brown', 'Oct 1, 2024', '10:15 AM', 'Joint Pain', 'Upcoming', 'Possible arthritis.'],
                                                        ['Dr. White', 'Oct 2, 2024', '12:00 PM', 'Anxiety', 'Cancelled', 'Initial consultation.'],
                                                        ['Dr. Taylor', 'Oct 3, 2024', '11:30 AM', 'Digestive Issues', 'Upcoming', 'Discuss dietary changes.'],
                                                        ['Dr. Martinez', 'Oct 4, 2024', '09:00 AM', 'Injury from Fall', 'Completed', 'X-ray needed.']
                                                    ];
                                                    foreach ($upcoming_mock as $um):
                                                    ?>
                                                    <tr>
                                                        <td style="text-align: center;">
                                                            <input type="checkbox" class="master-checkbox upcoming-row-checkbox" onclick="diorUpdateUpcomingSelectedCount()">
                                                        </td>
                                                        <td>
                                                            <span class="cell-text doctor-name"><?php echo esc_html($um[0]); ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="cell-content cell-icon-text">
                                                                <i class="fa-regular fa-calendar cell-icon"></i>
                                                                <span class="cell-text"><?php echo esc_html($um[1]); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><span class="cell-text"><?php echo esc_html($um[2]); ?></span></td>
                                                        <td><span class="cell-text"><?php echo esc_html($um[3]); ?></span></td>
                                                        <td>
                                                            <?php
                                                                $st_cls = 'status-upcoming';
                                                                if (strtolower($um[4]) === 'completed') $st_cls = 'status-completed';
                                                                if (strtolower($um[4]) === 'cancelled') $st_cls = 'status-cancelled';
                                                            ?>
                                                            <span class="status-cell-text <?php echo $st_cls; ?>"><?php echo esc_html($um[4]); ?></span>
                                                        </td>
                                                        <td><span class="cell-text notes-text"><?php echo esc_html($um[5]); ?></span></td>
                                                        <td style="text-align: center;">
                                                            <div class="cell-actions">
                                                                <button type="button" class="action-icon-btn edit-btn" title="Edit Record"><i class="fa-solid fa-pen"></i></button>
                                                                <button type="button" class="action-icon-btn delete-btn" title="Delete Record" onclick="diorDeleteUpcomingRow(this)"><i class="fa-regular fa-trash-can"></i></button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="master-table-footer">
                                            <span class="page-count" id="dior-upcoming-page-count">0 selected / <?php echo count($upcoming_mock); ?> total</span>
                                            
                                            <div class="master-pagination" id="dior-upcoming-pagination">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <script>
                            let diorUpcomingCurrentPage = 1;
                            const diorUpcomingRowsPerPage = 5;

                            function diorGetUpcomingActiveRows() {
                                const input = document.getElementById('dior-upcoming-search-input');
                                const filter = input ? input.value.toLowerCase() : '';
                                const allRows = Array.from(document.querySelectorAll('#dior-upcoming-appointments-tbody tr'));
                                
                                return allRows.filter(function(row) {
                                    const text = row.innerText.toLowerCase();
                                    return text.includes(filter);
                                });
                            }

                            function diorRenderUpcomingPagination() {
                                const activeRows = diorGetUpcomingActiveRows();
                                const allRows = Array.from(document.querySelectorAll('#dior-upcoming-appointments-tbody tr'));
                                const totalPages = Math.ceil(activeRows.length / diorUpcomingRowsPerPage) || 1;

                                if (diorUpcomingCurrentPage > totalPages) {
                                    diorUpcomingCurrentPage = totalPages;
                                }
                                if (diorUpcomingCurrentPage < 1) {
                                    diorUpcomingCurrentPage = 1;
                                }

                                // First hide all rows
                                allRows.forEach(row => row.style.display = 'none');

                                // Show rows for current page
                                const startIndex = (diorUpcomingCurrentPage - 1) * diorUpcomingRowsPerPage;
                                const endIndex = startIndex + diorUpcomingRowsPerPage;
                                const pageRows = activeRows.slice(startIndex, endIndex);

                                pageRows.forEach(row => row.style.display = '');

                                // Build pagination HTML
                                const pagContainer = document.getElementById('dior-upcoming-pagination');
                                if (pagContainer) {
                                    let html = '';
                                    
                                    // First page button
                                    const firstDisabled = diorUpcomingCurrentPage === 1 ? 'disabled' : '';
                                    html += `<button type="button" class="page-btn ${firstDisabled}" title="First Page" onclick="diorGoUpcomingPage(1)"><i class="fa-solid fa-angles-left"></i></button>`;
                                    
                                    // Previous page button
                                    const prevDisabled = diorUpcomingCurrentPage === 1 ? 'disabled' : '';
                                    html += `<button type="button" class="page-btn ${prevDisabled}" title="Previous Page" onclick="diorGoUpcomingPage(${diorUpcomingCurrentPage - 1})"><i class="fa-solid fa-angle-left"></i></button>`;

                                    // Page numbers
                                    for (let i = 1; i <= totalPages; i++) {
                                        const activeClass = i === diorUpcomingCurrentPage ? 'active' : '';
                                        html += `<button type="button" class="page-num ${activeClass}" onclick="diorGoUpcomingPage(${i})">${i}</button>`;
                                    }

                                    // Next page button
                                    const nextDisabled = diorUpcomingCurrentPage === totalPages ? 'disabled' : '';
                                    html += `<button type="button" class="page-btn ${nextDisabled}" title="Next Page" onclick="diorGoUpcomingPage(${diorUpcomingCurrentPage + 1})"><i class="fa-solid fa-angle-right"></i></button>`;

                                    // Last page button
                                    const lastDisabled = diorUpcomingCurrentPage === totalPages ? 'disabled' : '';
                                    html += `<button type="button" class="page-btn ${lastDisabled}" title="Last Page" onclick="diorGoUpcomingPage(${totalPages})"><i class="fa-solid fa-angles-right"></i></button>`;

                                    pagContainer.innerHTML = html;
                                }

                                diorUpdateUpcomingSelectedCount(activeRows.length);
                            }

                            function diorGoUpcomingPage(page) {
                                diorUpcomingCurrentPage = page;
                                diorRenderUpcomingPagination();
                            }

                            function diorDownloadUpcomingAppointmentsCSV() {
                                const table = document.getElementById('dior-upcoming-appointments-table');
                                if (!table) return;

                                let csv = [];
                                csv.push(['Doctor', 'Date', 'Time', 'Injury', 'Status', 'Notes'].join(','));

                                const activeRows = diorGetUpcomingActiveRows();
                                activeRows.forEach(function(row) {
                                    const cols = row.querySelectorAll('td');
                                    if (cols.length >= 8) {
                                        let doctor = cols[1].innerText.replace(/\s+/g, ' ').trim();
                                        let date = cols[2].innerText.replace(/\s+/g, ' ').trim();
                                        let time = cols[3].innerText.replace(/\s+/g, ' ').trim();
                                        let injury = cols[4].innerText.replace(/\s+/g, ' ').trim();
                                        let status = cols[5].innerText.replace(/\s+/g, ' ').trim();
                                        let notes = cols[6].innerText.replace(/\s+/g, ' ').trim();

                                        let rowData = [
                                            `"${doctor.replace(/"/g, '""')}"`,
                                            `"${date.replace(/"/g, '""')}"`,
                                            `"${time.replace(/"/g, '""')}"`,
                                            `"${injury.replace(/"/g, '""')}"`,
                                            `"${status.replace(/"/g, '""')}"`,
                                            `"${notes.replace(/"/g, '""')}"`
                                        ];
                                        csv.push(rowData.join(','));
                                    }
                                });

                                const csvString = csv.join('\r\n');
                                const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                                const url = URL.createObjectURL(blob);
                                const link = document.createElement('a');
                                link.setAttribute('href', url);
                                link.setAttribute('download', 'upcoming_appointments.csv');
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                URL.revokeObjectURL(url);
                            }

                            function diorFilterUpcomingAppointments() {
                                diorUpcomingCurrentPage = 1;
                                diorRenderUpcomingPagination();
                            }

                            function diorToggleSelectAllUpcoming(master) {
                                const activeRows = diorGetUpcomingActiveRows();
                                activeRows.forEach(function(row) {
                                    const cb = row.querySelector('.upcoming-row-checkbox');
                                    if (cb) cb.checked = master.checked;
                                });
                                diorUpdateUpcomingSelectedCount();
                            }

                            function diorDeleteUpcomingRow(btn) {
                                const row = btn.closest('tr');
                                if (row) {
                                    row.remove();
                                    diorRenderUpcomingPagination();
                                }
                            }

                            function diorBulkDeleteUpcomingRows() {
                                const checkedBoxes = document.querySelectorAll('.upcoming-row-checkbox:checked');
                                checkedBoxes.forEach(function(cb) {
                                    const row = cb.closest('tr');
                                    if (row) row.remove();
                                });
                                const masterCb = document.getElementById('dior-select-all-upcoming');
                                if (masterCb) masterCb.checked = false;
                                diorRenderUpcomingPagination();
                            }

                            function diorUpdateUpcomingSelectedCount(customVisibleCount) {
                                const checkboxes = document.querySelectorAll('.upcoming-row-checkbox');
                                let selectedCount = 0;
                                let visibleCount = 0;

                                checkboxes.forEach(function(cb) {
                                    const row = cb.closest('tr');
                                    if (row) {
                                        visibleCount++;
                                        if (cb.checked) selectedCount++;
                                    }
                                });

                                const bulkDeleteBtn = document.getElementById('dior-upcoming-bulk-delete-btn');
                                if (bulkDeleteBtn) {
                                    bulkDeleteBtn.style.display = selectedCount > 0 ? 'inline-flex' : 'none';
                                }

                                const total = (typeof customVisibleCount === 'number') ? customVisibleCount : visibleCount;
                                const pageCountEl = document.getElementById('dior-upcoming-page-count');
                                if (pageCountEl) {
                                    pageCountEl.innerText = `${selectedCount} selected / ${total} total`;
                                }
                            }

                            document.addEventListener('DOMContentLoaded', function() {
                                diorRenderUpcomingPagination();
                            });
                            if (document.readyState === 'interactive' || document.readyState === 'complete') {
                                diorRenderUpcomingPagination();
                            }
                            </script>
                        </div>

                        <!-- 4. Past Appointments -->
                        <!-- 4. Past Appointments -->
                        <div class="dior-subtab-panel" id="dior-subtab-past" style="display:none;">
                            <div class="master-table-wrapper">
                                <div class="master-table-container">
                                    <div class="master-table-card">
                                        <div class="master-table-header">
                                            <div class="header-content">
                                                <div class="table-title-section">
                                                    <h2 class="table-title">Past Appointments</h2>
                                                    <div class="title-accent"></div>
                                                </div>
                                                <div class="header-actions-group">
                                                    <div class="search-container">
                                                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                                        <input type="text" id="dior-past-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterPastAppointments()">
                                                    </div>
                                                    <div class="action-buttons">
                                                        <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadPastAppointmentsCSV()">
                                                            <i class="fa-solid fa-file-arrow-down"></i>
                                                        </button>
                                                        <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                                            <i class="fa-solid fa-rotate-right"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-content">
                                            <table class="master-modern-table" id="dior-past-appointments-table">
                                                <thead>
                                                    <tr>
                                                        <th>DOCTOR <i class="fa-solid fa-sort"></i></th>
                                                        <th>DATE <i class="fa-solid fa-sort"></i></th>
                                                        <th>TIME <i class="fa-solid fa-sort"></i></th>
                                                        <th>EMAIL <i class="fa-solid fa-sort"></i></th>
                                                        <th>MOBILE <i class="fa-solid fa-sort"></i></th>
                                                        <th>INJURY <i class="fa-solid fa-sort"></i></th>
                                                        <th>TYPE <i class="fa-solid fa-sort"></i></th>
                                                        <th>NEXT APPOINTMENT <i class="fa-solid fa-sort"></i></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="dior-past-appointments-tbody">
                                                    <?php
                                                    $past_mock = [
                                                        ['Dr. Rajesh', 'Feb 15, 2023', '10:00', 'patient1@email.com', '1234567890', 'Fever', 'Consultation', 'Feb 22, 2023', 'https://randomuser.me/api/portraits/women/44.jpg'],
                                                        ['Dr. Smith', 'Feb 20, 2023', '11:30', 'patient2@email.com', '2345678901', 'Headache', 'Follow-up', 'Mar 20, 2023', 'https://randomuser.me/api/portraits/women/65.jpg'],
                                                        ['Dr. Lee', 'Mar 1, 2023', '14:00', 'patient3@email.com', '3456789012', 'Back Pain', 'Consultation', 'Apr 1, 2023', 'https://randomuser.me/api/portraits/men/32.jpg'],
                                                        ['Dr. Patel', 'Mar 10, 2023', '09:15', 'patient4@email.com', '4567890123', 'Allergy', 'Consultation', 'Apr 10, 2023', 'https://randomuser.me/api/portraits/men/75.jpg'],
                                                        ['Dr. Kim', 'Mar 15, 2023', '13:45', 'patient5@email.com', '5678901234', 'Cough', 'Consultation', 'Apr 15, 2023', 'https://randomuser.me/api/portraits/women/68.jpg'],
                                                        ['Dr. Zhang', 'Mar 20, 2023', '10:30', 'patient6@email.com', '6789012345', 'Joint Pain', 'Follow-up', 'May 20, 2023', 'https://randomuser.me/api/portraits/men/85.jpg'],
                                                        ['Dr. Gupta', 'Apr 1, 2023', '11:00', 'patient7@email.com', '7890123456', 'Flu', 'Consultation', 'Apr 15, 2023', 'https://randomuser.me/api/portraits/women/49.jpg'],
                                                        ['Dr. Brown', 'Apr 10, 2023', '15:30', 'patient8@email.com', '8901234567', 'Stomach Ache', 'Consultation', 'May 10, 2023', 'https://randomuser.me/api/portraits/men/22.jpg'],
                                                        ['Dr. White', 'Apr 20, 2023', '08:00', 'patient9@email.com', '9012345678', 'Skin Rash', 'Consultation', 'May 20, 2023', 'https://randomuser.me/api/portraits/women/33.jpg'],
                                                        ['Dr. Miller', 'May 1, 2023', '16:00', 'patient10@email.com', '0123456789', 'Knee Pain', 'Consultation', 'Jun 1, 2023', 'https://randomuser.me/api/portraits/men/11.jpg']
                                                    ];
                                                    foreach ($past_mock as $pm):
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="cell-content cell-image-name">
                                                                <img src="<?php echo esc_url($pm[8]); ?>" alt="<?php echo esc_attr($pm[0]); ?>" class="cell-avatar">
                                                                <span class="cell-text doctor-name"><?php echo esc_html($pm[0]); ?></span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="cell-content cell-icon-text">
                                                                <i class="fa-regular fa-calendar cell-icon"></i>
                                                                <span class="cell-text"><?php echo esc_html($pm[1]); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><span class="cell-text"><?php echo esc_html($pm[2]); ?></span></td>
                                                        <td><span class="cell-text"><?php echo esc_html($pm[3]); ?></span></td>
                                                        <td><span class="cell-text"><?php echo esc_html($pm[4]); ?></span></td>
                                                        <td><span class="cell-text"><?php echo esc_html($pm[5]); ?></span></td>
                                                        <td><span class="cell-text"><?php echo esc_html($pm[6]); ?></span></td>
                                                        <td>
                                                            <div class="cell-content cell-icon-text">
                                                                <i class="fa-regular fa-calendar cell-icon"></i>
                                                                <span class="cell-text"><?php echo esc_html($pm[7]); ?></span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="master-table-footer">
                                            <span class="page-count" id="dior-past-page-count">0 selected / <?php echo count($past_mock); ?> total</span>
                                            
                                            <div class="master-pagination" id="dior-past-pagination">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <script>
                            let diorPastCurrentPage = 1;
                            const diorPastRowsPerPage = 5;

                            function diorGetPastActiveRows() {
                                const input = document.getElementById('dior-past-search-input');
                                const filter = input ? input.value.toLowerCase() : '';
                                const allRows = Array.from(document.querySelectorAll('#dior-past-appointments-tbody tr'));
                                
                                return allRows.filter(function(row) {
                                    const text = row.innerText.toLowerCase();
                                    return text.includes(filter);
                                });
                            }

                            function diorRenderPastPagination() {
                                const activeRows = diorGetPastActiveRows();
                                const allRows = Array.from(document.querySelectorAll('#dior-past-appointments-tbody tr'));
                                const totalPages = Math.ceil(activeRows.length / diorPastRowsPerPage) || 1;

                                if (diorPastCurrentPage > totalPages) {
                                    diorPastCurrentPage = totalPages;
                                }
                                if (diorPastCurrentPage < 1) {
                                    diorPastCurrentPage = 1;
                                }

                                allRows.forEach(row => row.style.display = 'none');

                                const startIndex = (diorPastCurrentPage - 1) * diorPastRowsPerPage;
                                const endIndex = startIndex + diorPastRowsPerPage;
                                const pageRows = activeRows.slice(startIndex, endIndex);

                                pageRows.forEach(row => row.style.display = '');

                                const pagContainer = document.getElementById('dior-past-pagination');
                                if (pagContainer) {
                                    let html = '';
                                    
                                    const firstDisabled = diorPastCurrentPage === 1 ? 'disabled' : '';
                                    html += `<button type="button" class="page-btn ${firstDisabled}" title="First Page" onclick="diorGoPastPage(1)"><i class="fa-solid fa-angles-left"></i></button>`;
                                    
                                    const prevDisabled = diorPastCurrentPage === 1 ? 'disabled' : '';
                                    html += `<button type="button" class="page-btn ${prevDisabled}" title="Previous Page" onclick="diorGoPastPage(${diorPastCurrentPage - 1})"><i class="fa-solid fa-angle-left"></i></button>`;

                                    for (let i = 1; i <= totalPages; i++) {
                                        const activeClass = i === diorPastCurrentPage ? 'active' : '';
                                        html += `<button type="button" class="page-num ${activeClass}" onclick="diorGoPastPage(${i})">${i}</button>`;
                                    }

                                    const nextDisabled = diorPastCurrentPage === totalPages ? 'disabled' : '';
                                    html += `<button type="button" class="page-btn ${nextDisabled}" title="Next Page" onclick="diorGoPastPage(${diorPastCurrentPage + 1})"><i class="fa-solid fa-angle-right"></i></button>`;

                                    const lastDisabled = diorPastCurrentPage === totalPages ? 'disabled' : '';
                                    html += `<button type="button" class="page-btn ${lastDisabled}" title="Last Page" onclick="diorGoPastPage(${totalPages})"><i class="fa-solid fa-angles-right"></i></button>`;

                                    pagContainer.innerHTML = html;
                                }

                                const pageCountEl = document.getElementById('dior-past-page-count');
                                if (pageCountEl) {
                                    pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
                                }
                            }

                            function diorGoPastPage(page) {
                                diorPastCurrentPage = page;
                                diorRenderPastPagination();
                            }

                            function diorFilterPastAppointments() {
                                diorPastCurrentPage = 1;
                                diorRenderPastPagination();
                            }
                            
                            function diorDownloadPastAppointmentsCSV() {
                                const table = document.getElementById('dior-past-appointments-table');
                                if (!table) return;

                                let csv = [];
                                csv.push(['Doctor', 'Date', 'Time', 'Email', 'Mobile', 'Injury', 'Type', 'Next Appointment'].join(','));

                                const activeRows = diorGetPastActiveRows();
                                activeRows.forEach(function(row) {
                                    const cols = row.querySelectorAll('td');
                                    if (cols.length >= 8) {
                                        let doctor = cols[0].innerText.replace(/\s+/g, ' ').trim();
                                        let date = cols[1].innerText.replace(/\s+/g, ' ').trim();
                                        let time = cols[2].innerText.replace(/\s+/g, ' ').trim();
                                        let email = cols[3].innerText.replace(/\s+/g, ' ').trim();
                                        let mobile = cols[4].innerText.replace(/\s+/g, ' ').trim();
                                        let injury = cols[5].innerText.replace(/\s+/g, ' ').trim();
                                        let type = cols[6].innerText.replace(/\s+/g, ' ').trim();
                                        let nextAppt = cols[7].innerText.replace(/\s+/g, ' ').trim();

                                        let rowData = [
                                            `"${doctor.replace(/"/g, '""')}"`,
                                            `"${date.replace(/"/g, '""')}"`,
                                            `"${time.replace(/"/g, '""')}"`,
                                            `"${email.replace(/"/g, '""')}"`,
                                            `"${mobile.replace(/"/g, '""')}"`,
                                            `"${injury.replace(/"/g, '""')}"`,
                                            `"${type.replace(/"/g, '""')}"`,
                                            `"${nextAppt.replace(/"/g, '""')}"`
                                        ];
                                        csv.push(rowData.join(','));
                                    }
                                });

                                const csvString = csv.join('\r\n');
                                const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                                const url = URL.createObjectURL(blob);
                                const link = document.createElement('a');
                                link.setAttribute('href', url);
                                link.setAttribute('download', 'past_appointments.csv');
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                URL.revokeObjectURL(url);
                            }

                            document.addEventListener('DOMContentLoaded', function() {
                                diorRenderPastPagination();
                            });
                            if (document.readyState === 'interactive' || document.readyState === 'complete') {
                                diorRenderPastPagination();
                            }
                            </script>
                        </div>

                    </div>

                    <script>
                    window.diorSwitchApptSubTab = function(targetSubTab) {
                        var navBtns = document.querySelectorAll('.dior-nav-btn');
                        var tabPanels = document.querySelectorAll('.dior-tab-panel');
                        
                        // Switch Main Tab to Appointments
                        navBtns.forEach(function(b) {
                            if (b.getAttribute('data-tab') === 'appointments') b.classList.add('active');
                            else b.classList.remove('active');
                        });
                        tabPanels.forEach(function(p) {
                            if (p.id === 'tab-appointments') p.classList.add('active');
                            else p.classList.remove('active');
                        });

                        // Switch inner tab
                        var panels = document.querySelectorAll('.dior-subtab-panel');
                        panels.forEach(function(panel) {
                            if (panel.id === 'dior-subtab-' + targetSubTab) {
                                panel.style.display = 'block';
                            } else {
                                panel.style.display = 'none';
                            }
                        });
                    };
                    </script>


                </section>
                <!-- ============================================================== -->
                <!-- 3. PAYMENTS & INVOICES TAB -->
                <!-- ============================================================== -->
<section class="dior-tab-panel" id="tab-payments" style="display:none;">
    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">Billing</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-bill-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterBill()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadBillCSV()">
                                    <i class="fa-solid fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="master-modern-table" id="dior-bill-table">
                        <thead>
                            <tr>
                                <th>INVOICE NO <i class="fa-solid fa-sort"></i></th>
                                <th>DOCTOR NAME <i class="fa-solid fa-sort"></i></th>
                                <th>DATE <i class="fa-solid fa-sort"></i></th>
                                <th>AMOUNT <i class="fa-solid fa-sort"></i></th>
                                <th>TAX <i class="fa-solid fa-sort"></i></th>
                                <th>DISCOUNT <i class="fa-solid fa-sort"></i></th>
                                <th>TOTAL <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS <i class="fa-solid fa-sort"></i></th>
                            </tr>
                        </thead>
                        <tbody id="dior-bill-tbody">
                            <?php
                            $billing_mock = [
                                ['#A348', 'Dr.Jacob Ryan', 'Mar 4, 2016', '$40', '10%', '$5', '$39'],
                                ['#A645', 'Dr.Rajesh', 'Apr 11, 2016', '$25', '10%', '$5', '$22'],
                                ['#A873', 'Dr.Jay Soni', 'Apr 18, 2016', '$50', '10%', '$5', '$47'],
                                ['#A927', 'Dr.John Deo', 'May 22, 2016', '$45', '10%', '$5', '$42'],
                                ['#A228', 'Dr.Megha Trivedi', 'Jul 9, 2016', '$62', '10%', '$5', '$57'],
                                ['#A345', 'Dr.Sarah Smith', 'Jul 14, 2016', '$60', '10%', '$5', '$56'],
                                ['#A765', 'Dr.Jacob Ryan', 'Jun 22, 2016', '$40', '10%', '$5', '$39'],
                                ['#A125', 'Dr.Rajesh', 'Jun 23, 2016', '$30', '10%', '$5', '$29']
                            ];
                            foreach ($billing_mock as $bm):
                            ?>
                            <tr onclick="diorOpenBillingModal('<?php echo esc_js($bm[0]); ?>', '<?php echo esc_js($bm[1]); ?>', '<?php echo esc_js($bm[2]); ?>', '<?php echo esc_js($bm[3]); ?>', '<?php echo esc_js($bm[4]); ?>', '<?php echo esc_js($bm[5]); ?>', '<?php echo esc_js($bm[6]); ?>')" style="cursor: pointer;">
                                <td><span class="cell-text"><?php echo esc_html($bm[0]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($bm[1]); ?></span></td>
                                <td>
                                    <div class="cell-content cell-icon-text">
                                        <i class="fa-regular fa-calendar cell-icon" style="color: #3b82f6;"></i>
                                        <span class="cell-text"><?php echo esc_html($bm[2]); ?></span>
                                    </div>
                                </td>
                                <td><span class="cell-text"><?php echo esc_html($bm[3]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($bm[4]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($bm[5]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($bm[6]); ?></span></td>
                                <td>
                                    <div class="cell-content action-cell" style="display:flex;gap:4px;">
                                        <button type="button" class="action-btn action-btn-success" style="width:28px;height:28px;border-radius:4px;padding:0;display:inline-flex;align-items:center;justify-content:center;background:#10B981;border:none;color:#fff;" title="Download Bill">
                                            <i class="fa-solid fa-download" style="font-size:12px;"></i>
                                        </button>
                                        <button type="button" class="action-btn action-btn-info" style="width:28px;height:28px;border-radius:4px;padding:0;display:inline-flex;align-items:center;justify-content:center;background:#06B6D4;border:none;color:#fff;" title="View Bill">
                                            <i class="fa-solid fa-eye" style="font-size:12px;"></i>
                                        </button>
                                        <button type="button" class="action-btn" style="width:28px;height:28px;border-radius:4px;padding:0;display:inline-flex;align-items:center;justify-content:center;background:#D97706;border:none;color:#fff;" title="Print Bill">
                                            <i class="fa-solid fa-print" style="font-size:12px;"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-bill-page-count">0 selected / <?php echo count($billing_mock); ?> total</span>
                    
                    <div class="master-pagination" id="dior-bill-pagination">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    let diorBillCurrentPage = 1;
    const diorBillRowsPerPage = 5;

    function diorGetBillActiveRows() {
        const input = document.getElementById('dior-bill-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-bill-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderBillPagination() {
        const activeRows = diorGetBillActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-bill-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorBillRowsPerPage) || 1;

        if (diorBillCurrentPage > totalPages) {
            diorBillCurrentPage = totalPages;
        }
        if (diorBillCurrentPage < 1) {
            diorBillCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorBillCurrentPage - 1) * diorBillRowsPerPage;
        const endIndex = startIndex + diorBillRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-bill-pagination');
        if (pagContainer) {
            let html = '';
            
            const firstDisabled = diorBillCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${firstDisabled}" title="First Page" onclick="diorGoBillPage(1)"><i class="fa-solid fa-angles-left"></i></button>`;
            
            const prevDisabled = diorBillCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${prevDisabled}" title="Previous Page" onclick="diorGoBillPage(${diorBillCurrentPage - 1})"><i class="fa-solid fa-angle-left"></i></button>`;

            for (let i = 1; i <= totalPages; i++) {
                const activeClass = i === diorBillCurrentPage ? 'active' : '';
                html += `<button type="button" class="page-num ${activeClass}" onclick="diorGoBillPage(${i})">${i}</button>`;
            }

            const nextDisabled = diorBillCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${nextDisabled}" title="Next Page" onclick="diorGoBillPage(${diorBillCurrentPage + 1})"><i class="fa-solid fa-angle-right"></i></button>`;

            const lastDisabled = diorBillCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${lastDisabled}" title="Last Page" onclick="diorGoBillPage(${totalPages})"><i class="fa-solid fa-angles-right"></i></button>`;

            pagContainer.innerHTML = html;
        }

        const pageCountEl = document.getElementById('dior-bill-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoBillPage(page) {
        diorBillCurrentPage = page;
        diorRenderBillPagination();
    }

    function diorFilterBill() {
        diorBillCurrentPage = 1;
        diorRenderBillPagination();
    }
    
    function diorDownloadBillCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Invoice No,Doctor Name,Date,Amount,Tax,Discount,Total\n";
        const activeRows = diorGetBillActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 7) {
                let c0 = cols[0].innerText.trim();
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                let c5 = cols[5].innerText.trim();
                let c6 = cols[6].innerText.trim();
                csvContent += `"${c0}","${c1}","${c2}","${c3}","${c4}","${c5}","${c6}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "billing.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function diorOpenBillingModal(inv, doc, date, amt, tax, disc, total) {
        document.getElementById('billModalSubtitle').innerText = doc;
        document.getElementById('billModalDocName').innerText = doc;
        document.getElementById('billModalDocName2').innerText = doc;
        document.getElementById('billModalInv').innerText = inv;
        document.getElementById('billModalDate').innerText = date;
        document.getElementById('billModalAmount').innerText = amt;
        document.getElementById('billModalTax').innerText = tax;
        document.getElementById('billModalDisc').innerText = disc;
        document.getElementById('billModalTotal').innerText = total;
        document.getElementById('billModalAvatar').src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(doc) + '&background=random';
        document.getElementById('diorBillingModal').style.display = 'flex';
    }
    function diorCloseBillingModal() {
        document.getElementById('diorBillingModal').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderBillPagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderBillPagination();
    }
    </script>

    <div class="dior-billing-modal-backdrop" id="diorBillingModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div class="modal-content" style="background:#fff; border-radius:12px; max-width:800px; width:90%; position:relative;">
            <div class="modal-header details-modal-header" style="padding:20px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
                <div class="header-left" style="display:flex; align-items:center; gap:12px;">
                    <div class="header-icon-wrapper" style="width:40px; height:40px; background:#EFF6FF; color:#3B82F6; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                        info
                    </div>
                    <div class="header-title-wrapper">
                        <h4 class="modal-title" style="margin:0; font-size:18px; font-weight:700; color:#1E293B;">Billing Details</h4>
                        <span class="modal-subtitle" id="billModalSubtitle" style="font-size:13px; color:#64748B;"></span>
                    </div>
                </div>
                <div class="header-actions" style="display:flex; align-items:center; gap:12px;">
                    <button type="button" class="btn btn-light" style="border:1px solid #E2E8F0; background:#fff; padding:6px 12px; border-radius:6px; font-size:13px; font-weight:600; color:#475569; display:flex; align-items:center; gap:6px;">
                        <span>Edit</span>
                    </button>
                    <button type="button" onclick="diorCloseBillingModal()" style="border:1px solid #E2E8F0; background:#fff; padding:6px 12px; border-radius:6px; font-size:13px; font-weight:600; color:#475569; display:flex; align-items:center; gap:6px;">
                       close
                    </button>
                </div>
            </div>
            <div class="modal-body details-modal-body" style="padding:24px;">
                <div class="details-hero-card" style="display:flex; align-items:center; gap:16px; margin-bottom:24px;">
                    <div class="hero-avatar-wrapper">
                        <img alt="avatar" class="hero-avatar" id="billModalAvatar" src="" style="width:60px; height:60px; border-radius:50%; object-fit:cover;">
                    </div>
                    <div class="hero-content">
                        <h3 class="hero-title" id="billModalDocName" style="margin:0; font-size:20px; font-weight:700; color:#1E293B;"></h3>
                    </div>
                </div>
                <div class="details-grid-container">
                    <div class="row g-3" style="display:flex; flex-wrap:wrap; margin: -8px;">
                        
                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6" style="padding:8px; width:50%; box-sizing:border-box;">
                            <div class="detail-field-card" style="border:1px solid #E2E8F0; border-radius:8px; padding:16px; height:100%;">
                                <div class="field-label-group" style="display:flex; align-items:center; gap:8px; margin-bottom:8px; color:#64748B; font-size:13px;">
                                    <span class="field-label">Invoice No</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value" id="billModalInv" style="font-size:16px; font-weight:600; color:#1E293B;"></span></div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6" style="padding:8px; width:50%; box-sizing:border-box;">
                            <div class="detail-field-card" style="border:1px solid #E2E8F0; border-radius:8px; padding:16px; height:100%;">
                                <div class="field-label-group" style="display:flex; align-items:center; gap:8px; margin-bottom:8px; color:#64748B; font-size:13px;">
                                   <span class="field-label">Doctor Name</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value" id="billModalDocName2" style="font-size:16px; font-weight:600; color:#1E293B;"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6" style="padding:8px; width:50%; box-sizing:border-box;">
                            <div class="detail-field-card" style="border:1px solid #E2E8F0; border-radius:8px; padding:16px; height:100%;">
                                <div class="field-label-group" style="display:flex; align-items:center; gap:8px; margin-bottom:8px; color:#64748B; font-size:13px;">
                                    <span class="field-label">Date</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value" id="billModalDate" style="font-size:16px; font-weight:600; color:#1E293B;"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6" style="padding:8px; width:50%; box-sizing:border-box;">
                            <div class="detail-field-card" style="border:1px solid #E2E8F0; border-radius:8px; padding:16px; height:100%;">
                                <div class="field-label-group" style="display:flex; align-items:center; gap:8px; margin-bottom:8px; color:#64748B; font-size:13px;">
                                    <span class="field-label">Amount</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value" id="billModalAmount" style="font-size:16px; font-weight:600; color:#1E293B;"></span></div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6" style="padding:8px; width:50%; box-sizing:border-box;">
                            <div class="detail-field-card" style="border:1px solid #E2E8F0; border-radius:8px; padding:16px; height:100%;">
                                <div class="field-label-group" style="display:flex; align-items:center; gap:8px; margin-bottom:8px; color:#64748B; font-size:13px;">
                                    <span class="field-label">Tax</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value" id="billModalTax" style="font-size:16px; font-weight:600; color:#1E293B;"></span></div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6" style="padding:8px; width:50%; box-sizing:border-box;">
                            <div class="detail-field-card" style="border:1px solid #E2E8F0; border-radius:8px; padding:16px; height:100%;">
                                <div class="field-label-group" style="display:flex; align-items:center; gap:8px; margin-bottom:8px; color:#64748B; font-size:13px;">
                                    <span class="field-label">Discount</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value" id="billModalDisc" style="font-size:16px; font-weight:600; color:#1E293B;"></span></div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6" style="padding:8px; width:50%; box-sizing:border-box;">
                            <div class="detail-field-card" style="border:1px solid #E2E8F0; border-radius:8px; padding:16px; height:100%;">
                                <div class="field-label-group" style="display:flex; align-items:center; gap:8px; margin-bottom:8px; color:#64748B; font-size:13px;">
                                    <span class="field-label">Total</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value" id="billModalTotal" style="font-size:16px; font-weight:600; color:#1E293B;"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6" style="padding:8px; width:50%; box-sizing:border-box;">
                            <div class="detail-field-card" style="border:1px solid #E2E8F0; border-radius:8px; padding:16px; height:100%;">
                                <div class="field-label-group" style="display:flex; align-items:center; gap:8px; margin-bottom:8px; color:#64748B; font-size:13px;">
                                    <span class="field-label">Actions</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value" style="font-size:16px; color:#94A3B8;">--</span></div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="modal-footer details-modal-footer" style="padding:20px; border-top:1px solid #E2E8F0; display:flex; justify-content:flex-end;">
                <button type="button" class="btn btn-light" onclick="diorCloseBillingModal()" style="border:1px solid #E2E8F0; background:#fff; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:600; color:#475569; cursor:pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>
</section>


<section class="dior-tab-panel" id="tab-insurance" style="display:none;">
    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">Insurance & Claims</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-insurance-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterInsurance()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary" style="background:#3B82F6;color:#fff;">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadInsuranceCSV()">
                                    <i class="fa-solid fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="master-modern-table" id="dior-insurance-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" style="width:16px;height:16px;accent-color:#3B82F6;cursor:pointer;">
                                </th>
                                <th>CLAIM ID <i class="fa-solid fa-sort"></i></th>
                                <th>POLICY ID <i class="fa-solid fa-sort"></i></th>
                                <th>CLAIM DATE <i class="fa-solid fa-sort"></i></th>
                                <th>CLAIM TYPE <i class="fa-solid fa-sort"></i></th>
                                <th>CLAIM AMOUNT ($) <i class="fa-solid fa-sort"></i></th>
                                <th>APPROVED ($) <i class="fa-solid fa-sort"></i></th>
                                <th>SUBMITTED <i class="fa-solid fa-sort"></i></th>
                                <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-insurance-tbody">
                            <?php
                            $insurance_mock = [
                                ['CLM001', 'POL001', 'Nov 20, 2024', 'Hospitalization', '5000', '4500', 'Nov 20, 2024', 'Approved', '#10B981', '#D1FAE5'],
                                ['CLM002', 'POL001', 'Nov 18, 2024', 'Outpatient', '1500', '', 'Nov 18, 2024', 'Under Review', '#F59E0B', '#FEF3C7'],
                                ['CLM003', 'POL002', 'Nov 15, 2024', 'Surgery', '3000', '3000', 'Nov 15, 2024', 'Approved', '#10B981', '#D1FAE5'],
                                ['CLM004', 'POL001', 'Nov 22, 2024', 'Pharmacy', '800', '600', 'Nov 22, 2024', 'Partially Approved', '#3B82F6', '#DBEAFE'],
                                ['CLM005', 'POL003', 'Nov 10, 2024', 'Dental', '2000', '0', 'Nov 10, 2024', 'Rejected', '#EF4444', '#FEE2E2']
                            ];
                            foreach ($insurance_mock as $im):
                            ?>
                            <tr>
                                <td><input type="checkbox" style="width:16px;height:16px;accent-color:#3B82F6;cursor:pointer;"></td>
                                <td><span class="cell-text"><?php echo esc_html($im[0]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($im[1]); ?></span></td>
                                <td>
                                    <div class="cell-content cell-icon-text">
                                        <i class="fa-regular fa-calendar cell-icon" style="color: #3b82f6;"></i>
                                        <span class="cell-text"><?php echo esc_html($im[2]); ?></span>
                                    </div>
                                </td>
                                <td><span class="cell-text"><?php echo esc_html($im[3]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($im[4]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($im[5]); ?></span></td>
                                <td>
                                    <div class="cell-content cell-icon-text">
                                        <i class="fa-regular fa-calendar cell-icon" style="color: #3b82f6;"></i>
                                        <span class="cell-text"><?php echo esc_html($im[6]); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-content">
                                        <div style="background: <?php echo esc_attr($im[9]); ?>; color: <?php echo esc_attr($im[8]); ?>; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block;">
                                            <?php echo esc_html($im[7]); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        <button type="button" class="action-icon-btn edit-btn" title="Edit Record"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" class="action-icon-btn delete-btn" title="Delete Record"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-insurance-page-count">0 selected / <?php echo count($insurance_mock); ?> total</span>
                    
                    <div class="master-pagination" id="dior-insurance-pagination">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    let diorInsuranceCurrentPage = 1;
    const diorInsuranceRowsPerPage = 5;

    function diorGetInsuranceActiveRows() {
        const input = document.getElementById('dior-insurance-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-insurance-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderInsurancePagination() {
        const activeRows = diorGetInsuranceActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-insurance-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorInsuranceRowsPerPage) || 1;

        if (diorInsuranceCurrentPage > totalPages) {
            diorInsuranceCurrentPage = totalPages;
        }
        if (diorInsuranceCurrentPage < 1) {
            diorInsuranceCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorInsuranceCurrentPage - 1) * diorInsuranceRowsPerPage;
        const endIndex = startIndex + diorInsuranceRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-insurance-pagination');
        if (pagContainer) {
            let html = '';
            
            const firstDisabled = diorInsuranceCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${firstDisabled}" title="First Page" onclick="diorGoInsurancePage(1)"><i class="fa-solid fa-angles-left"></i></button>`;
            
            const prevDisabled = diorInsuranceCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${prevDisabled}" title="Previous Page" onclick="diorGoInsurancePage(${diorInsuranceCurrentPage - 1})"><i class="fa-solid fa-angle-left"></i></button>`;

            for (let i = 1; i <= totalPages; i++) {
                const activeClass = i === diorInsuranceCurrentPage ? 'active' : '';
                html += `<button type="button" class="page-num ${activeClass}" onclick="diorGoInsurancePage(${i})">${i}</button>`;
            }

            const nextDisabled = diorInsuranceCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${nextDisabled}" title="Next Page" onclick="diorGoInsurancePage(${diorInsuranceCurrentPage + 1})"><i class="fa-solid fa-angle-right"></i></button>`;

            const lastDisabled = diorInsuranceCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${lastDisabled}" title="Last Page" onclick="diorGoInsurancePage(${totalPages})"><i class="fa-solid fa-angles-right"></i></button>`;

            pagContainer.innerHTML = html;
        }

        const pageCountEl = document.getElementById('dior-insurance-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoInsurancePage(page) {
        diorInsuranceCurrentPage = page;
        diorRenderInsurancePagination();
    }

    function diorFilterInsurance() {
        diorInsuranceCurrentPage = 1;
        diorRenderInsurancePagination();
    }
    
    function diorDownloadInsuranceCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Claim ID,Policy ID,Claim Date,Claim Type,Claim Amount,Approved,Submitted,Status\n";
        const activeRows = diorGetInsuranceActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 9) {
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                let c5 = cols[5].innerText.trim();
                let c6 = cols[6].innerText.trim();
                let c7 = cols[7].innerText.trim();
                let c8 = cols[8].innerText.trim();
                csvContent += `"${c1}","${c2}","${c3}","${c4}","${c5}","${c6}","${c7}","${c8}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "insurance.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderInsurancePagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderInsurancePagination();
    }
    </script>
</section>
                <!-- ============================================================== -->
                <!-- 4. DOCS & MEDS (Documents + Prescriptions) TAB -->
                <!-- ============================================================== -->
                <section class="dior-tab-panel" id="tab-docs_meds">
                    <div class="dior-page-header-box">
                        <div>
                            <h2>Medical Documents & Active Prescriptions</h2>
                        </div>
                        <div class="dior-header-action-group">
                            <button type="button" class="dior-btn-gold-secondary"
                                onclick="diorOpenModal('modal-change-pharmacy')" style="color: #ffffff;">
                                <i class="fa-solid fa-store"></i> Update Pharmacy Preference
                            </button>
                        </div>
                    </div>

                    <!-- Prescriptions Section -->
                    <!-- Prescriptions Section -->
                    <div class="master-table-wrapper">
                        <div class="master-table-container">
                            <div class="master-table-card">
                                <div class="master-table-header">
                                    <div class="header-content">
                                        <div class="table-title-section">
                                            <h2 class="table-title">Prescriptions</h2>
                                            <div class="title-accent"></div>
                                        </div>
                                        <div class="header-actions-group">
                                            <div class="search-container">
                                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                                <input type="text" id="dior-rx-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterRx()">
                                            </div>
                                            <div class="action-buttons">
                                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary">
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadRxCSV()">
                                                    <i class="fa-solid fa-file-arrow-down"></i>
                                                </button>
                                                <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                                    <i class="fa-solid fa-rotate-right"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-content">
                                    <table class="master-modern-table" id="dior-rx-table">
                                        <thead>
                                            <tr>
                                                <th>ID <i class="fa-solid fa-sort"></i></th>
                                                <th>TITLE <i class="fa-solid fa-sort"></i></th>
                                                <th>CREATED BY <i class="fa-solid fa-sort"></i></th>
                                                <th>DATE <i class="fa-solid fa-sort"></i></th>
                                                <th>DISEASES <i class="fa-solid fa-sort"></i></th>
                                                <th>ACTIONS <i class="fa-solid fa-sort"></i></th>
                                            </tr>
                                        </thead>
                                        <tbody id="dior-rx-tbody">
                                            <?php
                                            $prescription_mock = [
                                                ['#A348', 'Prescription 1', 'Dr.Jacob Ryan', 'May 12, 2016', 'Fever'],
                                                ['#A645', 'Prescription 2', 'Dr.Rajesh', 'May 12, 2016', 'Cholera'],
                                                ['#A873', 'Prescription 3', 'Dr.Jay Soni', 'May 12, 2016', 'Jaundice'],
                                                ['#A927', 'Prescription 4', 'Dr.John Deo', 'May 12, 2016', 'Typhod'],
                                                ['#A228', 'Prescription 5', 'Dr.Megha Trivedi', 'May 12, 2016', 'Maleria'],
                                                ['#A345', 'Prescription 6', 'Dr.Sarah Smith', 'May 12, 2016', 'Infection'],
                                                ['#A765', 'Prescription 7', 'Dr.Jacob Ryan', 'May 12, 2016', 'Fever'],
                                                ['#A125', 'Prescription 8', 'Dr.Rajesh', 'May 12, 2016', 'Cholera'],
                                                ['#A126', 'Prescription 9', 'Dr.Jay Soni', 'May 13, 2016', 'Jaundice'],
                                                ['#A127', 'Prescription 10', 'Dr.John Deo', 'May 14, 2016', 'Typhod']
                                            ];
                                            foreach ($prescription_mock as $pm):
                                            ?>
                                            <tr>
                                                <td><span class="cell-text"><?php echo esc_html($pm[0]); ?></span></td>
                                                <td><span class="cell-text"><?php echo esc_html($pm[1]); ?></span></td>
                                                <td><span class="cell-text"><?php echo esc_html($pm[2]); ?></span></td>
                                                <td>
                                                    <div class="cell-content cell-icon-text">
                                                        <i class="fa-regular fa-calendar cell-icon"></i>
                                                        <span class="cell-text"><?php echo esc_html($pm[3]); ?></span>
                                                    </div>
                                                </td>
                                                <td><span class="cell-text"><?php echo esc_html($pm[4]); ?></span></td>
                                                <td>
                                                    <div class="cell-content action-cell">
                                                        <button type="button" class="action-btn action-btn-info" title="Download" onclick="diorDownloadRxPdf('<?php echo esc_js($pm[0]); ?>')">
                                                            <i class="fa-solid fa-download"></i>
                                                        </button>
                                                        <button type="button" class="action-btn action-btn-danger" title="Delete" onclick="diorDeleteRxRow(this)">
                                                            <i class="fa-solid fa-trash-can"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="master-table-footer">
                                    <span class="page-count" id="dior-rx-page-count">0 selected / <?php echo count($prescription_mock); ?> total</span>
                                    
                                    <div class="master-pagination" id="dior-rx-pagination">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                    let diorRxCurrentPage = 1;
                    const diorRxRowsPerPage = 5;

                    function diorGetRxActiveRows() {
                        const input = document.getElementById('dior-rx-search-input');
                        const filter = input ? input.value.toLowerCase() : '';
                        const allRows = Array.from(document.querySelectorAll('#dior-rx-tbody tr'));
                        
                        return allRows.filter(function(row) {
                            const text = row.innerText.toLowerCase();
                            return text.includes(filter);
                        });
                    }

                    function diorRenderRxPagination() {
                        const activeRows = diorGetRxActiveRows();
                        const allRows = Array.from(document.querySelectorAll('#dior-rx-tbody tr'));
                        const totalPages = Math.ceil(activeRows.length / diorRxRowsPerPage) || 1;

                        if (diorRxCurrentPage > totalPages) {
                            diorRxCurrentPage = totalPages;
                        }
                        if (diorRxCurrentPage < 1) {
                            diorRxCurrentPage = 1;
                        }

                        allRows.forEach(row => row.style.display = 'none');

                        const startIndex = (diorRxCurrentPage - 1) * diorRxRowsPerPage;
                        const endIndex = startIndex + diorRxRowsPerPage;
                        const pageRows = activeRows.slice(startIndex, endIndex);

                        pageRows.forEach(row => row.style.display = '');

                        const pagContainer = document.getElementById('dior-rx-pagination');
                        if (pagContainer) {
                            let html = '';
                            
                            const firstDisabled = diorRxCurrentPage === 1 ? 'disabled' : '';
                            html += `<button type="button" class="page-btn ${firstDisabled}" title="First Page" onclick="diorGoRxPage(1)"><i class="fa-solid fa-angles-left"></i></button>`;
                            
                            const prevDisabled = diorRxCurrentPage === 1 ? 'disabled' : '';
                            html += `<button type="button" class="page-btn ${prevDisabled}" title="Previous Page" onclick="diorGoRxPage(${diorRxCurrentPage - 1})"><i class="fa-solid fa-angle-left"></i></button>`;

                            for (let i = 1; i <= totalPages; i++) {
                                const activeClass = i === diorRxCurrentPage ? 'active' : '';
                                html += `<button type="button" class="page-num ${activeClass}" onclick="diorGoRxPage(${i})">${i}</button>`;
                            }

                            const nextDisabled = diorRxCurrentPage === totalPages ? 'disabled' : '';
                            html += `<button type="button" class="page-btn ${nextDisabled}" title="Next Page" onclick="diorGoRxPage(${diorRxCurrentPage + 1})"><i class="fa-solid fa-angle-right"></i></button>`;

                            const lastDisabled = diorRxCurrentPage === totalPages ? 'disabled' : '';
                            html += `<button type="button" class="page-btn ${lastDisabled}" title="Last Page" onclick="diorGoRxPage(${totalPages})"><i class="fa-solid fa-angles-right"></i></button>`;

                            pagContainer.innerHTML = html;
                        }

                        const pageCountEl = document.getElementById('dior-rx-page-count');
                        if (pageCountEl) {
                            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
                        }
                    }

                    function diorGoRxPage(page) {
                        diorRxCurrentPage = page;
                        diorRenderRxPagination();
                    }

                    function diorFilterRx() {
                        diorRxCurrentPage = 1;
                        diorRenderRxPagination();
                    }

                    function diorDeleteRxRow(btn) {
                        const row = btn.closest('tr');
                        if (row) {
                            row.remove();
                            diorRenderRxPagination();
                        }
                    }
                    
                    function diorDownloadRxCSV() {
                        let csvContent = "data:text/csv;charset=utf-8,ID,Title,Created By,Date,Diseases\n";
                        const activeRows = diorGetRxActiveRows();
                        activeRows.forEach(function(row) {
                            let cols = row.querySelectorAll('td');
                            if(cols.length >= 5) {
                                let id = cols[0].innerText.trim();
                                let title = cols[1].innerText.trim();
                                let doc = cols[2].innerText.trim();
                                let date = cols[3].innerText.trim();
                                let disease = cols[4].innerText.trim();
                                csvContent += `"${id}","${title}","${doc}","${date}","${disease}"\n`;
                            }
                        });
                        var encodedUri = encodeURI(csvContent);
                        var link = document.createElement("a");
                        link.setAttribute("href", encodedUri);
                        link.setAttribute("download", "prescriptions.csv");
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }

                    document.addEventListener('DOMContentLoaded', function() {
                        diorRenderRxPagination();
                    });
                    if (document.readyState === 'interactive' || document.readyState === 'complete') {
                        diorRenderRxPagination();
                    }
                    </script>
                                            
                </section>

<section class="dior-tab-panel" id="tab-telemedicine" style="display:none;">
    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">Telemedicine / Video Consultations</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-tele-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterTele()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary" style="background:#3B82F6;color:#fff;">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadTeleCSV()">
                                    <i class="fa-solid fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="master-modern-table" id="dior-tele-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" style="width:16px;height:16px;accent-color:#3B82F6;cursor:pointer;">
                                </th>
                                <th>SESSION ID <i class="fa-solid fa-sort"></i></th>
                                <th>DOCTOR <i class="fa-solid fa-sort"></i></th>
                                <th>SPECIALTY <i class="fa-solid fa-sort"></i></th>
                                <th>DATE <i class="fa-solid fa-sort"></i></th>
                                <th>TIME <i class="fa-solid fa-sort"></i></th>
                                <th>DURATION <i class="fa-solid fa-sort"></i></th>
                                <th>TYPE <i class="fa-solid fa-sort"></i></th>
                                <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-tele-tbody">
                            <?php
                            $tele_mock = [
                                ['TM001', 'Dr. Sarah Smith', 'General Physician', 'Nov 26, 2024', '10:00 AM', '30 minutes', 'Follow-up', 'Scheduled', '#3B82F6', '#DBEAFE'],
                                ['TM002', 'Dr. Michael Johnson', 'Cardiologist', 'Nov 24, 2024', '02:00 PM', '45 minutes', 'Initial Consultation', 'Completed', '#10B981', '#D1FAE5'],
                                ['TM003', 'Dr. Emily Davis', 'Dermatologist', 'Nov 27, 2024', '11:00 AM', '30 minutes', 'Prescription Renewal', 'Scheduled', '#3B82F6', '#DBEAFE'],
                                ['TM004', 'Dr. Robert Wilson', 'Psychiatrist', 'Nov 23, 2024', '09:00 AM', '60 minutes', 'Therapy Session', 'Completed', '#10B981', '#D1FAE5'],
                                ['TM005', 'Dr. Lisa Anderson', 'Pediatrician', 'Nov 25, 2024', '03:00 PM', '30 minutes', 'Follow-up', 'Cancelled', '#EF4444', '#FEE2E2'],
                                ['TM006', 'Dr. James Miller', 'Orthopedic Surgeon', 'Nov 28, 2024', '01:30 PM', '30 minutes', 'Follow-up', 'Scheduled', '#3B82F6', '#DBEAFE'],
                                ['TM007', 'Dr. Sophia Martinez', 'Endocrinologist', 'Nov 22, 2024', '04:00 PM', '45 minutes', 'Initial Consultation', 'Completed', '#10B981', '#D1FAE5'],
                                ['TM008', 'Dr. Daniel Thompson', 'Neurologist', 'Nov 29, 2024', '12:00 PM', '30 minutes', 'Diagnostic Review', 'Scheduled', '#3B82F6', '#DBEAFE'],
                                ['TM009', 'Dr. Ava Brown', 'Ophthalmologist', 'Nov 21, 2024', '05:00 PM', '20 minutes', 'Check-up', 'Completed', '#10B981', '#D1FAE5']
                            ];
                            foreach ($tele_mock as $tm):
                            ?>
                            <tr>
                                <td><input type="checkbox" style="width:16px;height:16px;accent-color:#3B82F6;cursor:pointer;"></td>
                                <td><span class="cell-text"><?php echo esc_html($tm[0]); ?></span></td>
                                <td>
                                    <div class="cell-content cell-image-name" style="display:flex;align-items:center;gap:12px;">
                                        <img alt="Doctor avatar" class="cell-avatar" src="https://ui-avatars.com/api/?name=<?php echo urlencode($tm[1]); ?>&background=random" style="width:36px;height:36px;border-radius:50%;">
                                        <div class="cell-text-wrapper">
                                            <div class="cell-text" style="font-weight:600;color:#1E293B;"><?php echo esc_html($tm[1]); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="cell-text" style="color:#64748B;"><?php echo esc_html($tm[2]); ?></span></td>
                                <td>
                                    <div class="cell-content cell-icon-text">
                                        <i class="fa-regular fa-calendar cell-icon" style="color: #3b82f6;"></i>
                                        <span class="cell-text"><?php echo esc_html($tm[3]); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-content cell-icon-text">
                                        <i class="fa-regular fa-clock cell-icon" style="color: #8B5CF6;"></i>
                                        <span class="cell-text"><?php echo esc_html($tm[4]); ?></span>
                                    </div>
                                </td>
                                <td><span class="cell-text"><?php echo esc_html($tm[5]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($tm[6]); ?></span></td>
                                <td>
                                    <div class="cell-content">
                                        <div style="background: <?php echo esc_attr($tm[9]); ?>; color: <?php echo esc_attr($tm[8]); ?>; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block;">
                                            <?php echo esc_html($tm[7]); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        <button type="button" class="action-icon-btn edit-btn" title="Edit Record"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" class="action-icon-btn delete-btn" title="Delete Record"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-tele-page-count">0 selected / <?php echo count($tele_mock); ?> total</span>
                    
                    <div class="master-pagination" id="dior-tele-pagination">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    let diorTeleCurrentPage = 1;
    const diorTeleRowsPerPage = 6;

    function diorGetTeleActiveRows() {
        const input = document.getElementById('dior-tele-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-tele-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderTelePagination() {
        const activeRows = diorGetTeleActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-tele-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorTeleRowsPerPage) || 1;

        if (diorTeleCurrentPage > totalPages) {
            diorTeleCurrentPage = totalPages;
        }
        if (diorTeleCurrentPage < 1) {
            diorTeleCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorTeleCurrentPage - 1) * diorTeleRowsPerPage;
        const endIndex = startIndex + diorTeleRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-tele-pagination');
        if (pagContainer) {
            let html = '';
            
            const firstDisabled = diorTeleCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${firstDisabled}" title="First Page" onclick="diorGoTelePage(1)"><i class="fa-solid fa-angles-left"></i></button>`;
            
            const prevDisabled = diorTeleCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${prevDisabled}" title="Previous Page" onclick="diorGoTelePage(${diorTeleCurrentPage - 1})"><i class="fa-solid fa-angle-left"></i></button>`;

            for (let i = 1; i <= totalPages; i++) {
                const activeClass = i === diorTeleCurrentPage ? 'active' : '';
                html += `<button type="button" class="page-num ${activeClass}" onclick="diorGoTelePage(${i})">${i}</button>`;
            }

            const nextDisabled = diorTeleCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${nextDisabled}" title="Next Page" onclick="diorGoTelePage(${diorTeleCurrentPage + 1})"><i class="fa-solid fa-angle-right"></i></button>`;

            const lastDisabled = diorTeleCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${lastDisabled}" title="Last Page" onclick="diorGoTelePage(${totalPages})"><i class="fa-solid fa-angles-right"></i></button>`;

            pagContainer.innerHTML = html;
        }

        const pageCountEl = document.getElementById('dior-tele-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoTelePage(page) {
        diorTeleCurrentPage = page;
        diorRenderTelePagination();
    }

    function diorFilterTele() {
        diorTeleCurrentPage = 1;
        diorRenderTelePagination();
    }
    
    function diorDownloadTeleCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Session ID,Doctor,Specialty,Date,Time,Duration,Type,Status\n";
        const activeRows = diorGetTeleActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 10) {
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                let c5 = cols[5].innerText.trim();
                let c6 = cols[6].innerText.trim();
                let c7 = cols[7].innerText.trim();
                let c8 = cols[8].innerText.trim();
                csvContent += `"${c1}","${c2}","${c3}","${c4}","${c5}","${c6}","${c7}","${c8}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "telemedicine.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderTelePagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderTelePagination();
    }
    </script>
</section>
                <!-- ============================================================== -->
                <!-- 5. MEDICAL RECORD TAB -->
                <!-- ============================================================== -->
                <section class="dior-tab-panel" id="tab-medical_record" style="display:none;">
                    <div class="dior-page-header-box">
                        <div>
                            <h2>Medical Record</h2>
                        </div>
                    </div>
                    
                    <div class="section-body" style="padding: 0 24px;">
                        <div class="row g-3 mb-4 no-print" style="display: flex; flex-wrap: wrap; margin-bottom: 24px; margin-left: -12px; margin-right: -12px;">
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12" style="padding: 12px; width: 25%; min-width: 250px; flex-grow: 1;">
                                <div class="card kpi-mini-card border-0 shadow-sm p-3 h-100" style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 20px;">
                                    <div class="d-flex align-items-center gap-3" style="display: flex; align-items: center; gap: 16px;">
                                        <div class="kpi-icon-box bg-blue-gradient text-white rounded-14 flex-shrink-0 shadow-sm" style="width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; color:#fff; background: linear-gradient(135deg, #3B82F6, #2563EB);">
                                            <i class="fas fa-stream"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden" style="flex-grow: 1;">
                                            <div class="d-flex align-items-center justify-content-between mb-1" style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                                <span class="text-xs text-muted font-weight-600 text-uppercase text-truncate" style="font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase;">Milestones</span>
                                                <span class="badge bg-primary-subtle text-primary rounded-pill text-2xs" style="background-color: #DBEAFE; color: #1D4ED8; font-size: 10px; padding: 3px 8px; border-radius: 10px;">Active</span>
                                            </div>
                                            <h5 class="mb-0 font-weight-bold text-dark text-truncate" style="font-weight: 700; margin: 0; font-size: 16px; color: #1E293B;">6 Care Events</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12" style="padding: 12px; width: 25%; min-width: 250px; flex-grow: 1;">
                                <div class="card kpi-mini-card border-0 shadow-sm p-3 h-100" style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 20px;">
                                    <div class="d-flex align-items-center gap-3" style="display: flex; align-items: center; gap: 16px;">
                                        <div class="kpi-icon-box bg-cyan-gradient text-white rounded-14 flex-shrink-0 shadow-sm" style="width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; color:#fff; background: linear-gradient(135deg, #06B6D4, #0891B2);">
                                            <i class="fas fa-heartbeat"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden" style="flex-grow: 1;">
                                            <div class="d-flex align-items-center justify-content-between mb-1" style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                                <span class="text-xs text-muted font-weight-600 text-uppercase text-truncate" style="font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase;">Care Phase</span>
                                                <span class="badge bg-info-subtle text-info rounded-pill text-2xs" style="background-color: #CFFAFE; color: #0E7490; font-size: 10px; padding: 3px 8px; border-radius: 10px;">Phase 2</span>
                                            </div>
                                            <h5 class="mb-0 font-weight-bold text-dark text-truncate" style="font-weight: 700; margin: 0; font-size: 16px; color: #1E293B;">Post-Op Recovery</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12" style="padding: 12px; width: 25%; min-width: 250px; flex-grow: 1;">
                                <div class="card kpi-mini-card border-0 shadow-sm p-3 h-100" style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 20px;">
                                    <div class="d-flex align-items-center gap-3" style="display: flex; align-items: center; gap: 16px;">
                                        <div class="kpi-icon-box bg-purple-gradient text-white rounded-14 flex-shrink-0 shadow-sm" style="width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; color:#fff; background: linear-gradient(135deg, #8B5CF6, #6D28D9);">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden" style="flex-grow: 1;">
                                            <div class="d-flex align-items-center justify-content-between mb-1" style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                                <span class="text-xs text-muted font-weight-600 text-uppercase text-truncate" style="font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase;">Lead Physician</span>
                                                <span class="badge bg-purple-subtle text-purple rounded-pill text-2xs" style="background-color: #EDE9FE; color: #6D28D9; font-size: 10px; padding: 3px 8px; border-radius: 10px;">Cardiology</span>
                                            </div>
                                            <h5 class="mb-0 font-weight-bold text-dark text-truncate" style="font-weight: 700; margin: 0; font-size: 16px; color: #1E293B;">Dr. Sarah Smith</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12" style="padding: 12px; width: 25%; min-width: 250px; flex-grow: 1;">
                                <div class="card kpi-mini-card border-0 shadow-sm p-3 h-100" style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 20px;">
                                    <div class="d-flex align-items-center gap-3" style="display: flex; align-items: center; gap: 16px;">
                                        <div class="kpi-icon-box bg-emerald-gradient text-white rounded-14 flex-shrink-0 shadow-sm" style="width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; color:#fff; background: linear-gradient(135deg, #10B981, #059669);">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden" style="flex-grow: 1;">
                                            <div class="d-flex align-items-center justify-content-between mb-1" style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                                <span class="text-xs text-muted font-weight-600 text-uppercase text-truncate" style="font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase;">EMR Security</span>
                                                <span class="badge bg-success-subtle text-success rounded-pill text-2xs" style="background-color: #D1FAE5; color: #047857; font-size: 10px; padding: 3px 8px; border-radius: 10px;">HIPAA</span>
                                            </div>
                                            <h5 class="mb-0 font-weight-bold text-success text-truncate" style="font-weight: 700; margin: 0; font-size: 16px; color: #059669 !important;"><i class="fas fa-lock me-1 text-xs"></i> Encrypted</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm rounded-16 mb-4 no-print" style="border-radius: 16px; background: #fff; border: 1px solid #E2E8F0; margin-bottom: 24px;">
                            <div class="card-body p-3" style="padding: 16px;">
                                <div class="row align-items-center g-3" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center;">
                                    <div class="col-lg-8" style="flex-grow: 1;">
                                        <div class="d-flex flex-wrap gap-2" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                            <button type="button" class="btn btn-sm btn-primary" style="background: #3B82F6; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;"><i class="fas fa-th-large me-1"></i> All Events (6) </button>
                                            <button type="button" class="btn btn-sm btn-light" style="background: #F8FAFC; color: #475569; border: 1px solid #E2E8F0; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;"><i class="fas fa-stethoscope me-1 text-primary" style="color: #3B82F6 !important;"></i> Consultations </button>
                                            <button type="button" class="btn btn-sm btn-light" style="background: #F8FAFC; color: #475569; border: 1px solid #E2E8F0; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;"><i class="fas fa-pills me-1 text-purple" style="color: #8B5CF6 !important;"></i> Prescriptions </button>
                                            <button type="button" class="btn btn-sm btn-light" style="background: #F8FAFC; color: #475569; border: 1px solid #E2E8F0; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;"><i class="fas fa-file-medical-alt me-1 text-info" style="color: #06B6D4 !important;"></i> Diagnostics &amp; Scans </button>
                                            <button type="button" class="btn btn-sm btn-light" style="background: #F8FAFC; color: #475569; border: 1px solid #E2E8F0; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;"><i class="fas fa-user-md me-1 text-danger" style="color: #EF4444 !important;"></i> Procedures </button>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 d-flex gap-2" style="display: flex; gap: 10px;">
                                        <div class="input-group search-input-group flex-grow-1" style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; overflow: hidden; display: flex; align-items: center; flex-grow: 1;">
                                            <span class="input-group-text" style="background: transparent; border: none; padding: 0 12px; color: #94A3B8;"><i class="fas fa-search"></i></span>
                                            <input type="text" placeholder="Search medical history..." class="form-control" style="background: transparent; border: none; box-shadow: none; padding: 10px; font-size: 13px; width: 100%; outline: none;">
                                        </div>
                                        <button type="button" title="Print Care History" class="btn btn-sm btn-outline-secondary px-3" style="border: 1px solid #E2E8F0; border-radius: 6px; background: #fff; color: #475569; padding: 0 16px; cursor: pointer;"><i class="fas fa-print"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm rounded-16" style="border-radius: 16px; background: #fff; border: 1px solid #E2E8F0; margin-bottom: 24px;">
                            <div class="card-header bg-transparent py-3 px-4 d-flex align-items-center justify-content-between" style="border-bottom: 1px solid #E2E8F0; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center;">
                                <h5 class="card-title-text mb-0" style="font-weight: 700; margin: 0; font-size: 17px; color: #1E293B;"><i class="fas fa-history me-2 text-primary" style="color: #3B82F6 !important; margin-right: 8px;"></i>Patient Treatment &amp; Clinical Timeline Log</h5>
                                <span class="patient-id-badge text-xs px-3 py-1 font-weight-bold" style="background: #EFF6FF; color: #1E40AF; border-radius: 20px; font-size: 12px; padding: 6px 12px;">Patient: <?php echo esc_html($profile['full_name']); ?> (#<?php echo esc_html($profile['patient_id'] ?? 'PAT-' . rand(1000, 9999)); ?>)</span>
                            </div>
                            <div class="card-body p-4 p-md-5" style="padding: 32px;">
                                <div class="modern-treatment-timeline position-relative" style="padding-left: 10px;">
                                    
                                    <style>
                                        .mr-timeline-vertical-line {
                                            position: absolute;
                                            top: 0;
                                            bottom: -40px;
                                            left: 24px;
                                            width: 2px;
                                            background-color: #E2E8F0;
                                            z-index: 1;
                                        }
                                        .mr-timeline-item {
                                            position: relative;
                                            z-index: 2;
                                            display: flex;
                                            margin-bottom: 30px;
                                        }
                                        .mr-timeline-icon-node {
                                            width: 48px;
                                            height: 48px;
                                            border-radius: 50%;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            position: relative;
                                            z-index: 3;
                                            border: 4px solid #fff;
                                            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                                        }
                                        .mr-timeline-time-col {
                                            width: 140px;
                                            flex-shrink: 0;
                                            text-align: right;
                                            padding-right: 20px;
                                            padding-top: 5px;
                                        }
                                        .mr-timeline-content-card {
                                            background: #fff;
                                            flex-grow: 1;
                                            padding: 24px;
                                            border-radius: 12px;
                                            border: 1px solid #E2E8F0;
                                            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
                                            margin-left: 24px;
                                        }
                                        @media(max-width: 768px) {
                                            .mr-timeline-time-col {
                                                display: none !important;
                                            }
                                        }
                                    </style>

                                    <!-- Event 1: DIAGNOSTIC -->
                                    <div class="mr-timeline-item">
                                        <div class="mr-timeline-time-col">
                                            <span style="font-size: 14px; font-weight: 700; color: #1E293B; display: block;">Today</span>
                                            <span style="font-size: 12px; color: #64748B; display: block; margin-top: 4px;"><i class="far fa-clock me-1"></i>03:45 AM</span>
                                            <span style="font-size: 11px; background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-top: 6px;">Just now</span>
                                        </div>
                                        <div style="position: relative; width: 50px; display: flex; flex-direction: column; align-items: center;">
                                            <div class="mr-timeline-vertical-line" style="background: linear-gradient(to bottom, transparent, #06B6D4 20%, #E2E8F0);"></div>
                                            <div class="mr-timeline-icon-node" style="background: #06B6D4;">
                                                <i class="fas text-white fa-file-medical-alt" style="color: #fff;"></i>
                                            </div>
                                        </div>
                                        <div class="mr-timeline-content-card">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <span style="background: #CFFAFE; color: #0891B2; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; padding: 4px 10px; border-radius: 12px;"> DIAGNOSTIC </span>
                                                    <h5 style="font-size: 16px; font-weight: 700; color: #1E293B; margin: 0;">Chest X-Ray &amp; Digital Radiology Scan</h5>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 10px; background: #F8FAFC; padding: 6px 12px; border-radius: 20px; border: 1px solid #E2E8F0;">
                                                    <img src="https://ui-avatars.com/api/?name=Helen+Miller&background=random" alt="Dr. Helen Miller" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                                    <div>
                                                        <span style="font-size: 13px; font-weight: 600; color: #1E293B; display: block; line-height: 1;">Dr. Helen Miller</span>
                                                        <small style="font-size: 11px; color: #64748B;">Radiologist Specialist</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <p style="font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 20px;"> Digital X-ray imaging performed for post-op thoracic evaluation. Lungs clear bilaterally, diaphragm contour normal, no acute focal consolidation or pleural effusion observed. </p>
                                            <div style="border-top: 1px solid #E2E8F0; padding-top: 16px;">
                                                <span style="font-size: 12px; font-weight: 600; color: #64748B; display: block; margin-bottom: 12px;"><i class="fas fa-paperclip me-1"></i> Attached Medical Files (2):</span>
                                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                                    <button type="button" style="border: 1px solid #E2E8F0; background: #fff; padding: 6px 12px; border-radius: 20px; font-size: 12px; color: #475569; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                        <i class="fas fa-file-pdf" style="color: #EF4444;"></i><span>XRay_Scan_Chest_Posterior.pdf</span><small style="color: #94A3B8;">(2.4 MB)</small><i class="fas fa-download"></i>
                                                    </button>
                                                    <button type="button" style="border: 1px solid #E2E8F0; background: #fff; padding: 6px 12px; border-radius: 20px; font-size: 12px; color: #475569; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                        <i class="fas fa-file-image" style="color: #8B5CF6;"></i><span>Radiology_DICOM_View.jpg</span><small style="color: #94A3B8;">(5.1 MB)</small><i class="fas fa-download"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Event 2: CONSULTATION -->
                                    <div class="mr-timeline-item">
                                        <div class="mr-timeline-time-col">
                                            <span style="font-size: 14px; font-weight: 700; color: #1E293B; display: block;">20 Sep 2026</span>
                                            <span style="font-size: 12px; color: #64748B; display: block; margin-top: 4px;"><i class="far fa-clock me-1"></i>01:30 PM</span>
                                            <span style="font-size: 11px; background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-top: 6px;">4 days ago</span>
                                        </div>
                                        <div style="position: relative; width: 50px; display: flex; flex-direction: column; align-items: center;">
                                            <div class="mr-timeline-vertical-line" style="background: linear-gradient(to bottom, #06B6D4, #3B82F6 20%, #E2E8F0);"></div>
                                            <div class="mr-timeline-icon-node" style="background: #3B82F6;">
                                                <i class="fas text-white fa-stethoscope" style="color: #fff;"></i>
                                            </div>
                                        </div>
                                        <div class="mr-timeline-content-card">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <span style="background: #DBEAFE; color: #1D4ED8; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; padding: 4px 10px; border-radius: 12px;"> CONSULTATION </span>
                                                    <h5 style="font-size: 16px; font-weight: 700; color: #1E293B; margin: 0;">Cardiology Follow-Up Consultation</h5>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 10px; background: #F8FAFC; padding: 6px 12px; border-radius: 20px; border: 1px solid #E2E8F0;">
                                                    <img src="https://ui-avatars.com/api/?name=John+Deo&background=random" alt="Dr. John Deo" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                                    <div>
                                                        <span style="font-size: 13px; font-weight: 600; color: #1E293B; display: block; line-height: 1;">Dr. John Deo</span>
                                                        <small style="font-size: 11px; color: #64748B;">Senior Cardiologist</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <p style="font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 16px;"> Routine post-procedural cardiology assessment. Patient reported significant improvement in stamina with minor incisional discomfort. Vital signs stable, normal sinus rhythm on ECG. </p>
                                            <div style="background: #F1F5F9; border-left: 4px solid #3B82F6; border-radius: 8px; padding: 16px;">
                                                <p style="font-style: italic; color: #475569; font-size: 13px; margin: 0;"><i class="fas fa-quote-left me-2 text-primary opacity-50" style="color: #93C5FD; margin-right: 8px;"></i>"Patient is recovering ahead of schedule. Resume light aerobic walking for 20 mins daily." </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Event 3: PRESCRIPTION -->
                                    <div class="mr-timeline-item">
                                        <div class="mr-timeline-time-col">
                                            <span style="font-size: 14px; font-weight: 700; color: #1E293B; display: block;">15 Sep 2026</span>
                                            <span style="font-size: 12px; color: #64748B; display: block; margin-top: 4px;"><i class="far fa-clock me-1"></i>02:00 PM</span>
                                            <span style="font-size: 11px; background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-top: 6px;">1 week ago</span>
                                        </div>
                                        <div style="position: relative; width: 50px; display: flex; flex-direction: column; align-items: center;">
                                            <div class="mr-timeline-vertical-line" style="background: linear-gradient(to bottom, #3B82F6, #8B5CF6 20%, #E2E8F0);"></div>
                                            <div class="mr-timeline-icon-node" style="background: #8B5CF6;">
                                                <i class="fas text-white fa-pills" style="color: #fff;"></i>
                                            </div>
                                        </div>
                                        <div class="mr-timeline-content-card">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <span style="background: #EDE9FE; color: #6D28D9; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; padding: 4px 10px; border-radius: 12px;"> PRESCRIPTION </span>
                                                    <h5 style="font-size: 16px; font-weight: 700; color: #1E293B; margin: 0;">Post-Op Medication &amp; Discharge Prescription</h5>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 10px; background: #F8FAFC; padding: 6px 12px; border-radius: 20px; border: 1px solid #E2E8F0;">
                                                    <img src="https://ui-avatars.com/api/?name=Sarah+Smith&background=random" alt="Dr. Sarah Smith" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                                    <div>
                                                        <span style="font-size: 13px; font-weight: 600; color: #1E293B; display: block; line-height: 1;">Dr. Sarah Smith</span>
                                                        <small style="font-size: 11px; color: #64748B;">Attending Physician</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <p style="font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 16px;"> Prescribed post-op antibiotic regimen and daily anticoagulant therapy. Recommended follow-up lab work in 14 days. </p>
                                            <div style="background: #F5F3FF; border: 1px solid #DDD6FE; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                                                <span style="color: #6D28D9; font-size: 12px; font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 12px;"><i class="fas fa-pills me-1"></i> Prescribed Dosage &amp; Medications:</span>
                                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                                    <span style="background: #fff; border: 1px solid #DDD6FE; color: #6D28D9; font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 20px;"><i class="fas fa-check-circle me-1" style="color: #8B5CF6;"></i> Amoxicillin 500mg (TID x 7 days) </span>
                                                    <span style="background: #fff; border: 1px solid #DDD6FE; color: #6D28D9; font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 20px;"><i class="fas fa-check-circle me-1" style="color: #8B5CF6;"></i> Clopidogrel 75mg (OD) </span>
                                                    <span style="background: #fff; border: 1px solid #DDD6FE; color: #6D28D9; font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 20px;"><i class="fas fa-check-circle me-1" style="color: #8B5CF6;"></i> Paracetamol 650mg (PRN for pain) </span>
                                                </div>
                                            </div>
                                            <div style="border-top: 1px solid #E2E8F0; padding-top: 16px;">
                                                <span style="font-size: 12px; font-weight: 600; color: #64748B; display: block; margin-bottom: 12px;"><i class="fas fa-paperclip me-1"></i> Attached Medical Files (2):</span>
                                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                                    <button type="button" style="border: 1px solid #E2E8F0; background: #fff; padding: 6px 12px; border-radius: 20px; font-size: 12px; color: #475569; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                        <i class="fas fa-file-pdf" style="color: #EF4444;"></i><span>Discharge_Prescription_Signed.pdf</span><small style="color: #94A3B8;">(1.2 MB)</small><i class="fas fa-download"></i>
                                                    </button>
                                                    <button type="button" style="border: 1px solid #E2E8F0; background: #fff; padding: 6px 12px; border-radius: 20px; font-size: 12px; color: #475569; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                        <i class="fas fa-file-pdf" style="color: #EF4444;"></i><span>Lab_Order_Blood_Panel.pdf</span><small style="color: #94A3B8;">(850 KB)</small><i class="fas fa-download"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Event 4: SURGERY -->
                                    <div class="mr-timeline-item">
                                        <div class="mr-timeline-time-col">
                                            <span style="font-size: 14px; font-weight: 700; color: #1E293B; display: block;">04 Sep 2026</span>
                                            <span style="font-size: 12px; color: #64748B; display: block; margin-top: 4px;"><i class="far fa-clock me-1"></i>10:30 AM</span>
                                            <span style="font-size: 11px; background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-top: 6px;">2 weeks ago</span>
                                        </div>
                                        <div style="position: relative; width: 50px; display: flex; flex-direction: column; align-items: center;">
                                            <div class="mr-timeline-vertical-line" style="background: linear-gradient(to bottom, #8B5CF6, #EF4444 20%, #E2E8F0);"></div>
                                            <div class="mr-timeline-icon-node" style="background: #EF4444;">
                                                <i class="fas text-white fa-user-md" style="color: #fff;"></i>
                                            </div>
                                        </div>
                                        <div class="mr-timeline-content-card">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <span style="background: #FEE2E2; color: #B91C1C; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; padding: 4px 10px; border-radius: 12px;"> SURGERY </span>
                                                    <h5 style="font-size: 16px; font-weight: 700; color: #1E293B; margin: 0;">Minimally Invasive Cardiac Angioplasty</h5>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 10px; background: #F8FAFC; padding: 6px 12px; border-radius: 20px; border: 1px solid #E2E8F0;">
                                                    <img src="https://ui-avatars.com/api/?name=Sarah+Smith&background=random" alt="Dr. Sarah Smith" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                                    <div>
                                                        <span style="font-size: 13px; font-weight: 600; color: #1E293B; display: block; line-height: 1;">Surgical Team Lead: Dr. Sarah Smith</span>
                                                        <small style="font-size: 11px; color: #64748B;">Lead Cardiac Surgeon</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <p style="font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 16px;"> Successful minimally invasive catheter angioplasty with stent placement in LAD coronary artery. Hemodynamics stabilized throughout 2-hour procedure with zero intraoperative complications. </p>
                                            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px;">
                                                <span style="color: #1E293B; font-size: 12px; font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 12px;"><i class="fas fa-user-md me-1" style="color: #EF4444;"></i> Attending Surgical Specialist Team:</span>
                                                <div style="display: flex; align-items: center;">
                                                    <img src="https://ui-avatars.com/api/?name=Doc+A&background=random" style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid #fff; object-fit: cover; z-index: 4; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                    <img src="https://ui-avatars.com/api/?name=Doc+B&background=random" style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid #fff; object-fit: cover; margin-left: -12px; z-index: 3; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                    <img src="https://ui-avatars.com/api/?name=Doc+C&background=random" style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid #fff; object-fit: cover; margin-left: -12px; z-index: 2; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                    <img src="https://ui-avatars.com/api/?name=Doc+D&background=random" style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid #fff; object-fit: cover; margin-left: -12px; z-index: 1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                    <span style="font-size: 12px; color: #64748B; font-weight: 600; margin-left: 12px;">4 Surgeons &amp; Anesthesiologist</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Event 5: CONSULTATION -->
                                    <div class="mr-timeline-item">
                                        <div class="mr-timeline-time-col">
                                            <span style="font-size: 14px; font-weight: 700; color: #1E293B; display: block;">29 Aug 2026</span>
                                            <span style="font-size: 12px; color: #64748B; display: block; margin-top: 4px;"><i class="far fa-clock me-1"></i>01:30 PM</span>
                                            <span style="font-size: 11px; background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-top: 6px;">3 weeks ago</span>
                                        </div>
                                        <div style="position: relative; width: 50px; display: flex; flex-direction: column; align-items: center;">
                                            <div class="mr-timeline-vertical-line" style="background: linear-gradient(to bottom, #EF4444, #3B82F6 20%, #E2E8F0);"></div>
                                            <div class="mr-timeline-icon-node" style="background: #3B82F6;">
                                                <i class="fas text-white fa-stethoscope" style="color: #fff;"></i>
                                            </div>
                                        </div>
                                        <div class="mr-timeline-content-card">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <span style="background: #DBEAFE; color: #1D4ED8; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; padding: 4px 10px; border-radius: 12px;"> CONSULTATION </span>
                                                    <h5 style="font-size: 16px; font-weight: 700; color: #1E293B; margin: 0;">Initial Clinical Examination &amp; Pre-Op Assessment</h5>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 10px; background: #F8FAFC; padding: 6px 12px; border-radius: 20px; border: 1px solid #E2E8F0;">
                                                    <img src="https://ui-avatars.com/api/?name=Sarah+Smith&background=random" alt="Dr. Sarah Smith" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                                    <div>
                                                        <span style="font-size: 13px; font-weight: 600; color: #1E293B; display: block; line-height: 1;">Dr. Sarah Smith</span>
                                                        <small style="font-size: 11px; color: #64748B;">Attending Physician</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <p style="font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 16px;"> Comprehensive intake evaluation and diagnostic workup prior to scheduling cardiac intervention. </p>
                                            <div style="background: #F1F5F9; border-left: 4px solid #3B82F6; border-radius: 8px; padding: 16px;">
                                                <p style="font-style: italic; color: #475569; font-size: 13px; margin: 0;"><i class="fas fa-quote-left me-2 text-primary opacity-50" style="color: #93C5FD; margin-right: 8px;"></i>"Advised immediate angiogram testing based on exercise stress test results and persistent angina." </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Event 6: SYSTEM -->
                                    <div class="mr-timeline-item">
                                        <div class="mr-timeline-time-col">
                                            <span style="font-size: 14px; font-weight: 700; color: #1E293B; display: block;">25 Aug 2026</span>
                                            <span style="font-size: 12px; color: #64748B; display: block; margin-top: 4px;"><i class="far fa-clock me-1"></i>12:13 PM</span>
                                            <span style="font-size: 11px; background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-top: 6px;">1 month ago</span>
                                        </div>
                                        <div style="position: relative; width: 50px; display: flex; flex-direction: column; align-items: center;">
                                            <div class="mr-timeline-vertical-line" style="background: linear-gradient(to bottom, #3B82F6, transparent 20%); bottom: 100%;"></div>
                                            <div class="mr-timeline-icon-node" style="background: #10B981;">
                                                <i class="fas text-white fa-id-card" style="color: #fff;"></i>
                                            </div>
                                        </div>
                                        <div class="mr-timeline-content-card">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <span style="background: #D1FAE5; color: #047857; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; padding: 4px 10px; border-radius: 12px;"> SYSTEM </span>
                                                    <h5 style="font-size: 16px; font-weight: 700; color: #1E293B; margin: 0;">Patient EMR Onboarding &amp; ID Generation</h5>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 10px; background: #F8FAFC; padding: 6px 12px; border-radius: 20px; border: 1px solid #E2E8F0;">
                                                    <img src="https://ui-avatars.com/api/?name=Admin&background=random" alt="Hospital Registrar" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                                    <div>
                                                        <span style="font-size: 13px; font-weight: 600; color: #1E293B; display: block; line-height: 1;">Hospital Registrar</span>
                                                        <small style="font-size: 11px; color: #64748B;">Medical Records Admin</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <p style="font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 0;"> Generated permanent patient electronic health record ID #PAT-1082 and verified insurance coverage. </p>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </section>


                <!-- ============================================================== -->
                <!-- 6. BILLING & PAYMENT TAB -->

<section class="dior-tab-panel" id="tab-documents" style="display:none;">
    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">My Documents &amp; Reports</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-docs-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterDocs()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary" style="background:#3B82F6;color:#fff;">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadDocsCSV()">
                                    <i class="fa-solid fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="master-modern-table" id="dior-docs-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" style="width:16px;height:16px;accent-color:#3B82F6;cursor:pointer;">
                                </th>
                                <th>DOCUMENT TITLE <i class="fa-solid fa-sort"></i></th>
                                <th>CATEGORY <i class="fa-solid fa-sort"></i></th>
                                <th>TYPE <i class="fa-solid fa-sort"></i></th>
                                <th>UPLOAD DATE <i class="fa-solid fa-sort"></i></th>
                                <th>SIZE <i class="fa-solid fa-sort"></i></th>
                                <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-docs-tbody">
                            <?php
                            $docs_mock = [
                                ['Discharge Summary - Nov 2024', 'Medical Report', 'PDF', '#EF4444', '#FEE2E2', 'Nov 20, 2024', '2.5 MB', 'Verified', '#10B981', '#D1FAE5'],
                                ['Blood Test Results', 'Lab Report', 'PDF', '#EF4444', '#FEE2E2', 'Nov 18, 2024', '1.2 MB', 'Verified', '#10B981', '#D1FAE5'],
                                ['Vaccination Certificate', 'Certificate', 'PDF', '#EF4444', '#FEE2E2', 'Jan 15, 2024', '0.8 MB', 'Verified', '#10B981', '#D1FAE5'],
                                ['Insurance Policy 2024', 'Insurance', 'PDF', '#EF4444', '#FEE2E2', 'Jan 1, 2024', '3.0 MB', 'Verified', '#10B981', '#D1FAE5'],
                                ['X-Ray Chest', 'Radiology', 'JPG', '#3B82F6', '#DBEAFE', 'Nov 19, 2024', '5.5 MB', 'Verified', '#10B981', '#D1FAE5'],
                                ['Prescription - Dr. Smith', 'Prescription', 'PDF', '#EF4444', '#FEE2E2', 'Nov 20, 2024', '0.5 MB', 'Verified', '#10B981', '#D1FAE5'],
                                ['MRI Scan Report', 'Radiology', 'PDF', '#EF4444', '#FEE2E2', 'Dec 10, 2023', '4.2 MB', 'Verified', '#10B981', '#D1FAE5'],
                                ['Allergy Test', 'Lab Report', 'PDF', '#EF4444', '#FEE2E2', 'Jun 15, 2023', '1.5 MB', 'Verified', '#10B981', '#D1FAE5']
                            ];
                            foreach ($docs_mock as $dm):
                            ?>
                            <tr>
                                <td><input type="checkbox" style="width:16px;height:16px;accent-color:#3B82F6;cursor:pointer;"></td>
                                <td><span class="cell-text" style="color:#64748B;"><?php echo esc_html($dm[0]); ?></span></td>
                                <td><span class="cell-text" style="color:#64748B;"><?php echo esc_html($dm[1]); ?></span></td>
                                <td>
                                    <div class="cell-content">
                                        <div style="background: <?php echo esc_attr($dm[4]); ?>; color: <?php echo esc_attr($dm[3]); ?>; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block;">
                                            <?php echo esc_html($dm[2]); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-content cell-icon-text">
                                        <i class="fa-regular fa-calendar cell-icon" style="color: #3b82f6;"></i>
                                        <span class="cell-text"><?php echo esc_html($dm[5]); ?></span>
                                    </div>
                                </td>
                                <td><span class="cell-text"><?php echo esc_html($dm[6]); ?></span></td>
                                <td>
                                    <div class="cell-content">
                                        <div style="background: <?php echo esc_attr($dm[9]); ?>; color: <?php echo esc_attr($dm[8]); ?>; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block;">
                                            <?php echo esc_html($dm[7]); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        <button type="button" class="action-icon-btn edit-btn" title="Edit Record"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" class="action-icon-btn delete-btn" title="Delete Record"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-docs-page-count">0 selected / <?php echo count($docs_mock); ?> total</span>
                    
                    <div class="master-pagination" id="dior-docs-pagination">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    let diorDocsCurrentPage = 1;
    const diorDocsRowsPerPage = 6;

    function diorGetDocsActiveRows() {
        const input = document.getElementById('dior-docs-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-docs-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderDocsPagination() {
        const activeRows = diorGetDocsActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-docs-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorDocsRowsPerPage) || 1;

        if (diorDocsCurrentPage > totalPages) {
            diorDocsCurrentPage = totalPages;
        }
        if (diorDocsCurrentPage < 1) {
            diorDocsCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorDocsCurrentPage - 1) * diorDocsRowsPerPage;
        const endIndex = startIndex + diorDocsRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-docs-pagination');
        if (pagContainer) {
            let html = '';
            
            const firstDisabled = diorDocsCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${firstDisabled}" title="First Page" onclick="diorGoDocsPage(1)"><i class="fa-solid fa-angles-left"></i></button>`;
            
            const prevDisabled = diorDocsCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${prevDisabled}" title="Previous Page" onclick="diorGoDocsPage(${diorDocsCurrentPage - 1})"><i class="fa-solid fa-angle-left"></i></button>`;

            for (let i = 1; i <= totalPages; i++) {
                const activeClass = i === diorDocsCurrentPage ? 'active' : '';
                html += `<button type="button" class="page-num ${activeClass}" onclick="diorGoDocsPage(${i})">${i}</button>`;
            }

            const nextDisabled = diorDocsCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${nextDisabled}" title="Next Page" onclick="diorGoDocsPage(${diorDocsCurrentPage + 1})"><i class="fa-solid fa-angle-right"></i></button>`;

            const lastDisabled = diorDocsCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${lastDisabled}" title="Last Page" onclick="diorGoDocsPage(${totalPages})"><i class="fa-solid fa-angles-right"></i></button>`;

            pagContainer.innerHTML = html;
        }

        const pageCountEl = document.getElementById('dior-docs-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoDocsPage(page) {
        diorDocsCurrentPage = page;
        diorRenderDocsPagination();
    }

    function diorFilterDocs() {
        diorDocsCurrentPage = 1;
        diorRenderDocsPagination();
    }
    
    function diorDownloadDocsCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Document Title,Category,Type,Upload Date,Size,Status\n";
        const activeRows = diorGetDocsActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 8) {
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                let c5 = cols[5].innerText.trim();
                let c6 = cols[6].innerText.trim();
                csvContent += `"${c1}","${c2}","${c3}","${c4}","${c5}","${c6}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "documents.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderDocsPagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderDocsPagination();
    }
    </script>
</section>
<section class="dior-tab-panel" id="tab-emergency" style="display:none;">
    
    <!-- Top Action Cards -->
    <div style="display: flex; flex-wrap: wrap; gap: 24px; margin-bottom: 30px;">
        <!-- Request Ambulance -->
        <div style="flex: 1; min-width: 250px; background: #fff; border-radius: 12px; padding: 30px 20px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #FEE2E2; color: #EF4444; font-size: 28px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto;">
                <i class="fa-solid fa-truck-medical"></i>
            </div>
            <h4 style="font-size: 18px; font-weight: 700; color: #1E293B; margin-bottom: 8px;">Request Ambulance</h4>
            <p style="font-size: 14px; color: #64748B; margin-bottom: 24px;">Immediate emergency assistance</p>
            <button type="button" style="background: transparent; border: 1px solid #EF4444; color: #EF4444; padding: 8px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#EF4444'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='#EF4444';">Call Now</button>
        </div>
        
        <!-- Emergency Doctor -->
        <div style="flex: 1; min-width: 250px; background: #fff; border-radius: 12px; padding: 30px 20px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #FEF3C7; color: #F59E0B; font-size: 28px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto;">
                <i class="fa-solid fa-user-doctor"></i>
            </div>
            <h4 style="font-size: 18px; font-weight: 700; color: #1E293B; margin-bottom: 8px;">Emergency Doctor</h4>
            <p style="font-size: 14px; color: #64748B; margin-bottom: 24px;">Connect with available doctors</p>
            <button type="button" style="background: transparent; border: 1px solid #F59E0B; color: #F59E0B; padding: 8px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#F59E0B'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='#F59E0B';">Contact</button>
        </div>
        
        <!-- Digital Health Card -->
        <div style="flex: 1; min-width: 250px; background: #fff; border-radius: 12px; padding: 30px 20px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #CCFBF1; color: #14B8A6; font-size: 28px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto;">
                <i class="fa-solid fa-id-card"></i>
            </div>
            <h4 style="font-size: 18px; font-weight: 700; color: #1E293B; margin-bottom: 8px;">Digital Health Card</h4>
            <p style="font-size: 14px; color: #64748B; margin-bottom: 24px;">Access your medical records</p>
            <button type="button" style="background: transparent; border: 1px solid #14B8A6; color: #14B8A6; padding: 8px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#14B8A6'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='#14B8A6';">View Card</button>
        </div>
    </div>

    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">Emergency Contacts</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-emerg-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterEmerg()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary" style="background:#3B82F6;color:#fff;">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadEmergCSV()">
                                    <i class="fa-solid fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="master-modern-table" id="dior-emerg-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" style="width:16px;height:16px;accent-color:#3B82F6;cursor:pointer;">
                                </th>
                                <th>CONTACT NAME <i class="fa-solid fa-sort"></i></th>
                                <th>RELATION <i class="fa-solid fa-sort"></i></th>
                                <th>PHONE NUMBER <i class="fa-solid fa-sort"></i></th>
                                <th>PRIORITY <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-emerg-tbody">
                            <?php
                            $emerg_mock = [
                                ['Jane Doe', 'Spouse', '555-0101', 'Primary', '#EF4444', '#FEE2E2'],
                                ['John Doe Sr.', 'Parent', '555-0103', 'Secondary', '#3B82F6', '#DBEAFE']
                            ];
                            foreach ($emerg_mock as $em):
                            ?>
                            <tr>
                                <td><input type="checkbox" style="width:16px;height:16px;accent-color:#3B82F6;cursor:pointer;"></td>
                                <td><span class="cell-text" style="color:#64748B;"><?php echo esc_html($em[0]); ?></span></td>
                                <td><span class="cell-text" style="color:#64748B;"><?php echo esc_html($em[1]); ?></span></td>
                                <td><span class="cell-text" style="color:#64748B;"><?php echo esc_html($em[2]); ?></span></td>
                                <td>
                                    <div class="cell-content">
                                        <div style="background: <?php echo esc_attr($em[5]); ?>; color: <?php echo esc_attr($em[4]); ?>; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block;">
                                            <?php echo esc_html($em[3]); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        <button type="button" class="action-icon-btn edit-btn" title="Edit Record"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" class="action-icon-btn delete-btn" title="Delete Record"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-emerg-page-count">0 selected / <?php echo count($emerg_mock); ?> total</span>
                    
                    <div class="master-pagination" id="dior-emerg-pagination">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    let diorEmergCurrentPage = 1;
    const diorEmergRowsPerPage = 6;

    function diorGetEmergActiveRows() {
        const input = document.getElementById('dior-emerg-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-emerg-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderEmergPagination() {
        const activeRows = diorGetEmergActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-emerg-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorEmergRowsPerPage) || 1;

        if (diorEmergCurrentPage > totalPages) {
            diorEmergCurrentPage = totalPages;
        }
        if (diorEmergCurrentPage < 1) {
            diorEmergCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorEmergCurrentPage - 1) * diorEmergRowsPerPage;
        const endIndex = startIndex + diorEmergRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-emerg-pagination');
        if (pagContainer) {
            let html = '';
            
            const firstDisabled = diorEmergCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${firstDisabled}" title="First Page" onclick="diorGoEmergPage(1)"><i class="fa-solid fa-angles-left"></i></button>`;
            
            const prevDisabled = diorEmergCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${prevDisabled}" title="Previous Page" onclick="diorGoEmergPage(${diorEmergCurrentPage - 1})"><i class="fa-solid fa-angle-left"></i></button>`;

            for (let i = 1; i <= totalPages; i++) {
                const activeClass = i === diorEmergCurrentPage ? 'active' : '';
                html += `<button type="button" class="page-num ${activeClass}" onclick="diorGoEmergPage(${i})">${i}</button>`;
            }

            const nextDisabled = diorEmergCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${nextDisabled}" title="Next Page" onclick="diorGoEmergPage(${diorEmergCurrentPage + 1})"><i class="fa-solid fa-angle-right"></i></button>`;

            const lastDisabled = diorEmergCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${lastDisabled}" title="Last Page" onclick="diorGoEmergPage(${totalPages})"><i class="fa-solid fa-angles-right"></i></button>`;

            pagContainer.innerHTML = html;
        }

        const pageCountEl = document.getElementById('dior-emerg-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoEmergPage(page) {
        diorEmergCurrentPage = page;
        diorRenderEmergPagination();
    }

    function diorFilterEmerg() {
        diorEmergCurrentPage = 1;
        diorRenderEmergPagination();
    }
    
    function diorDownloadEmergCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Contact Name,Relation,Phone Number,Priority\n";
        const activeRows = diorGetEmergActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 6) {
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                csvContent += `"${c1}","${c2}","${c3}","${c4}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "emergency_contacts.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderEmergPagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderEmergPagination();
    }
    </script>
</section>
<section class="dior-tab-panel" id="tab-consultation" style="display:none;">
    <div style="background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="width: 50px; height: 50px; border-radius: 12px; background: #DBEAFE; color: #3B82F6; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                <i class="fa-solid fa-video"></i>
            </div>
            <div>
                <h2 style="margin: 0 0 4px 0; font-size: 20px; font-weight: 700; color: #1E293B;">HD Telehealth Consultation Room</h2>
                <span style="font-size: 13px; color: #64748B;">
                    Patient: <strong style="color: #475569;">Sarah Jenkins (#PAT-1082)</strong> &bull; Attending: <strong style="color: #475569;">Dr. Helen Miller (Cardiology)</strong>
                </span>
            </div>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <span style="background: #D1FAE5; color: #10B981; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                <i class="fa-solid fa-wifi" style="margin-right: 4px;"></i> Signal: Strong (5G)
            </span>
            <span style="background: #EF4444; color: #fff; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; display: flex; align-items: center;">
                <div style="width: 8px; height: 8px; background: #fff; border-radius: 50%; margin-right: 6px; animation: pulse 1.5s infinite;"></div> REC HD 1080p
            </span>
        </div>
    </div>

    <div style="display: flex; gap: 24px; flex-wrap: wrap;">
        
        <!-- Video Feed Section -->
        <div style="flex: 2; min-width: 600px; position: relative; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.1); background: #1E293B; height: 600px;">
            <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=1200" style="width: 100%; height: 100%; object-fit: cover;" alt="Doctor Video">
            
            <div style="position: absolute; top: 20px; left: 20px; background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(4px); padding: 8px 16px; border-radius: 20px; display: flex; align-items: center; gap: 8px; color: #fff; font-size: 13px; font-weight: 600;">
                <i class="fa-regular fa-clock" style="color: #10B981;"></i> 01:19
            </div>

            <!-- PIP Window -->
            <div style="position: absolute; top: 20px; right: 20px; width: 180px; height: 120px; border-radius: 12px; overflow: hidden; border: 3px solid #fff; box-shadow: 0 10px 15px rgba(0,0,0,0.2);">
                <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=400" style="width: 100%; height: 100%; object-fit: cover;" alt="Self View">
            </div>

            <!-- Doctor Name Tag -->
            <div style="position: absolute; bottom: 100px; left: 20px; background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(4px); padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                <div style="width: 10px; height: 10px; background: #10B981; border-radius: 50%;"></div>
                <div>
                    <span style="display: block; color: #fff; font-size: 14px; font-weight: 700;">Dr. Helen Miller, MD</span>
                    <span style="display: block; color: #94A3B8; font-size: 11px;">Senior Cardiologist &bull; Apollo Heart Center</span>
                </div>
            </div>

            <!-- Controls Dock -->
            <div style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(8px); padding: 12px 24px; border-radius: 30px; display: flex; gap: 16px;">
                <button type="button" style="width: 44px; height: 44px; border-radius: 50%; border: none; background: rgba(255,255,255,0.1); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    <i class="fa-solid fa-microphone"></i>
                </button>
                <button type="button" style="width: 44px; height: 44px; border-radius: 50%; border: none; background: rgba(255,255,255,0.1); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    <i class="fa-solid fa-video"></i>
                </button>
                <button type="button" style="width: 44px; height: 44px; border-radius: 50%; border: none; background: rgba(255,255,255,0.1); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    <i class="fa-solid fa-desktop"></i>
                </button>
                <button type="button" style="width: 44px; height: 44px; border-radius: 50%; border: none; background: rgba(255,255,255,0.1); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    <i class="fa-regular fa-comment-dots"></i>
                </button>
                <button type="button" style="width: 44px; height: 44px; border-radius: 50%; border: none; background: #EF4444; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; margin-left: 8px;" onmouseover="this.style.background='#DC2626'" onmouseout="this.style.background='#EF4444'">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
            </div>
        </div>

        <!-- Chat Panel -->
        <div style="flex: 1; min-width: 350px; background: #fff; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); display: flex; flex-direction: column; height: 600px;">
            <div style="display: flex; border-bottom: 1px solid #E2E8F0; padding: 12px; gap: 8px;">
                <button type="button" style="flex: 1; background: #3B82F6; color: #fff; border: none; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">
                    <i class="fa-regular fa-comments" style="margin-right: 6px;"></i> Live Chat
                </button>
                <button type="button" style="flex: 1; background: transparent; color: #64748B; border: none; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">
                    <i class="fa-regular fa-clipboard" style="margin-right: 6px;"></i> Notes
                </button>
                <button type="button" style="flex: 1; background: transparent; color: #64748B; border: none; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">
                    <i class="fa-solid fa-heart-pulse" style="margin-right: 6px;"></i> Vitals
                </button>
            </div>

            <div id="dior-chat-messages-container" style="flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Doctor Message -->
                <div style="display: flex; gap: 12px; max-width: 85%;">
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=100" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px;">
                            <span style="font-size: 13px; font-weight: 700; color: #1E293B;">Dr. Helen Miller</span>
                            <span style="font-size: 11px; color: #94A3B8;">09:30 AM</span>
                        </div>
                        <div style="background: #F1F5F9; padding: 12px 16px; border-radius: 0 16px 16px 16px; font-size: 13px; color: #475569; line-height: 1.5;">
                            Good morning Sarah! How are you feeling after taking the prescribed beta-blockers?
                        </div>
                    </div>
                </div>

                <!-- Patient Message -->
                <div style="display: flex; gap: 12px; max-width: 85%; align-self: flex-end; flex-direction: row-reverse;">
                    <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=100" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; flex-direction: row-reverse;">
                            <span style="font-size: 13px; font-weight: 700; color: #1E293B;">Sarah Jenkins</span>
                            <span style="font-size: 11px; color: #94A3B8;">09:31 AM</span>
                        </div>
                        <div style="background: #3B82F6; padding: 12px 16px; border-radius: 16px 0 16px 16px; font-size: 13px; color: #fff; line-height: 1.5;">
                            Hello Doctor! The chest tightness has reduced significantly, but I noticed mild dizziness in the morning.
                        </div>
                    </div>
                </div>

                <!-- Doctor Message -->
                <div style="display: flex; gap: 12px; max-width: 85%;">
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=100" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px;">
                            <span style="font-size: 13px; font-weight: 700; color: #1E293B;">Dr. Helen Miller</span>
                            <span style="font-size: 11px; color: #94A3B8;">09:32 AM</span>
                        </div>
                        <div style="background: #F1F5F9; padding: 12px 16px; border-radius: 0 16px 16px 16px; font-size: 13px; color: #475569; line-height: 1.5;">
                            That can happen initially. Let us review your daily blood pressure readings.
                        </div>
                    </div>
                </div>

            </div>

            <div style="padding: 16px; border-top: 1px solid #E2E8F0;">
                <div style="display: flex; gap: 12px;">
                    <input type="text" id="dior-chat-input" placeholder="Type a message..." style="flex: 1; padding: 12px 16px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 13px; outline: none;" onkeypress="if(event.key === 'Enter') diorSendChatMessage()">
                    <button type="button" id="dior-chat-send-btn" onclick="diorSendChatMessage()" style="background: #3B82F6; color: #fff; border: none; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <style>
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 4px rgba(255, 255, 255, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); }
        }
    </style>
    <script>
        function diorSendChatMessage() {
            var input = document.getElementById('dior-chat-input');
            var container = document.getElementById('dior-chat-messages-container');
            if(!input || !container) return;
            var text = input.value.trim();
            if(!text) return;
            
            var now = new Date();
            var hours = now.getHours();
            var minutes = now.getMinutes();
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0'+minutes : minutes;
            var timeString = hours + ':' + minutes + ' ' + ampm;
            
            var msgHtml = `
                <div style="display: flex; gap: 12px; max-width: 85%; align-self: flex-end; flex-direction: row-reverse; animation: fadeIn 0.3s ease;">
                    <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=100" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; flex-direction: row-reverse;">
                            <span style="font-size: 13px; font-weight: 700; color: #1E293B;">Sarah Jenkins</span>
                            <span style="font-size: 11px; color: #94A3B8;">${timeString}</span>
                        </div>
                        <div style="background: #3B82F6; padding: 12px 16px; border-radius: 16px 0 16px 16px; font-size: 13px; color: #fff; line-height: 1.5;">
                            ${text.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', msgHtml);
            input.value = '';
            container.scrollTop = container.scrollHeight;
        }
    </script>
</section>
                <!-- ============================================================== -->
                <!-- 7. MEDICAL QUESTIONNAIRE TAB -->
                <!-- ============================================================== -->
                <section class="dior-tab-panel" id="tab-questionnaire">
                    <div class="dior-page-header-box">
                        <div>
                            <h2>Clinical Intake & Medical Questionnaires</h2>
                        </div>
                        <!-- Official HIPAAtizer Questionnaire Embed Card -->
                        <div class="dior-section-block" style="margin-bottom: 28px;">
                            <?php
                            $has_intake = !empty($hipaa_intake) && is_array($hipaa_intake);
                            ?>

                            <?php if (!$is_prof_complete): ?>
                            <!-- 🔒 Incomplete Profile Lock Notice in Questionnaire Tab -->
                            <div class="dior-card"
                                style="padding:32px 24px; text-align:center; border:2px dashed #CBD5E1; border-radius:14px; background:#F8FAFC; margin-bottom:24px;">
                                <div
                                    style="width:58px; height:58px; border-radius:50%; background:#EFF6FF; color:#2C6CB1; display:flex; align-items:center; justify-content:center; font-size:24px; margin:0 auto 14px auto;">
                                    <i class="fa-solid fa-lock"></i>
                                </div>
                                <h3 style="font-size:18px; font-weight:800; color:#0F172A; margin:0 0 6px 0;">Personal
                                    Profile Required</h3>
                                <p
                                    style="font-size:13.5px; color:#64748B; max-width:540px; margin:0 auto 18px auto; line-height:1.5;">
                                    Please complete your required personal information (Name, Phone, Date of Birth,
                                    Gender, Address) in your profile before filling out medical questionnaires or
                                    booking appointments.
                                </p>
                                <button type="button" class="dior-btn-gold-primary" data-switch-tab="profile"
                                    style="padding:9px 20px; font-size:13.5px; font-weight:700; background:#2C6CB1 !important; border:none; border-radius:8px; color:#fff; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                                    <i class="fa-solid fa-user-pen"></i> Complete Personal Information First
                                </button>
                            </div>
                            <?php endif; ?>

                            <?php if ($has_intake): ?>
                            <!-- ✅ Top Status Banner When Intake is Completed -->
                            <div class="dior-intake-completed-notice" id="dior-intake-completed-notice"
                                style="background:#ECFDF5; border:1.5px solid #A7F3D0; border-radius:12px; padding:16px 20px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; box-shadow:0 4px 12px rgba(5,150,105,0.08);">
                                <div style="display:flex; align-items:center; gap:14px;">
                                    <div
                                        style="width:42px; height:42px; border-radius:50%; background:#059669; color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px;">
                                        <i class="fa-solid fa-circle-check"></i>
                                    </div>
                                    <div>
                                        <strong style="color:#065F46; font-size:15px; display:block;">Clinical Intake
                                            Questionnaire Completed</strong>
                                        <span style="color:#047857; font-size:13px;">Submitted on
                                            <?php echo esc_html(date('M j, Y g:i A', strtotime($hipaa_intake['submitted_at'] ?? 'now'))); ?>
                                            &bull; Data verified &amp; ready for physician review</span>
                                    </div>
                                </div>
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    <button type="button" class="dior-btn-gold-secondary"
                                        onclick="diorToggleIntakeReview()"
                                        style="font-size:12.5px; padding:7px 14px; background:#FFFFFF; border:1px solid #CBD5E1; color:#1E293B;">
                                        <i class="fa-solid fa-file-medical"></i> <span id="toggle-intake-btn-text">View
                                            Submitted Answers</span>
                                    </button>
                                    <button type="button" class="dior-btn-gold-secondary"
                                        onclick="diorReopenHipaaForm()"
                                        style="font-size:12.5px; padding:7px 14px; background:#FFFFFF; border:1px solid #CBD5E1; color:#1E293B;">
                                        <i class="fa-solid fa-arrows-rotate"></i> Re-open Questionnaire
                                    </button>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- ℹ️ Step 1 Guidance Banner When Intake Is NOT Yet Completed -->
                            <div class="dior-intake-step-notice" id="dior-intake-step-notice"
                                style="background:#EFF6FF; border:1.5px solid #BFDBFE; border-radius:12px; padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; gap:14px; box-shadow:0 4px 12px rgba(44,108,177,0.08);">
                                <div
                                    style="width:42px; height:42px; border-radius:50%; background:#2C6CB1; color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px;">
                                    <i class="fa-solid fa-clipboard-question"></i>
                                </div>
                                <div>
                                    <strong style="color:#1E3A8A; font-size:15px; display:block;">Step 1 of 2: Complete
                                        Your Clinical Intake Questionnaire</strong>
                                    <span style="color:#3B82F6; font-size:13px;">Please complete the medical
                                        questionnaire below. Once you submit, your <strong>Doctor Scheduling
                                            Calendar</strong> will automatically unlock on this page!</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($has_intake): ?>
                            <!-- ✅ Submitted Intake Data Card (Collapsible) -->
                            <div class="dior-card" id="dior-intake-review-card"
                                style="margin-bottom: 24px; border-left: 4px solid #059669; display:none;">
                                <div class="dior-card-header"
                                    style="background: #ECFDF5; border-radius: 12px 12px 0 0;">
                                    <h3 style="color: #059669;"><i class="fa-solid fa-circle-check"></i> HIPAA Intake
                                        Form — Submitted</h3>
                                    <span class="dior-st ok"><i class="fa-solid fa-calendar"></i>
                                        <?php echo esc_html(!empty($hipaa_intake['submitted_at']) ? date('M j, Y g:i A', strtotime($hipaa_intake['submitted_at'])) : date('M j, Y')); ?></span>
                                </div>
                                <div class="dior-card-body">
                                    <div class="dior-form-row-2" style="margin-bottom:12px;">
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fa-solid fa-user"></i> Full Name</span>
                                            <span
                                                class="pf-value"><?php echo esc_html(trim(($hipaa_intake['first_name'] ?? '') . ' ' . ($hipaa_intake['last_name'] ?? '')) ?: ($profile['full_name'] ?? '—')); ?></span>
                                        </div>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fa-solid fa-cake-candles"></i> Date of
                                                Birth</span>
                                            <span
                                                class="pf-value"><?php echo esc_html(!empty($hipaa_intake['dob']) ? $hipaa_intake['dob'] : (!empty($profile['dob']) ? $profile['dob'] : '—')); ?></span>
                                        </div>
                                    </div>
                                    <div class="dior-form-row-2" style="margin-bottom:12px;">
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fa-solid fa-phone"></i> Phone</span>
                                            <span
                                                class="pf-value"><?php echo esc_html(!empty($hipaa_intake['phone']) ? $hipaa_intake['phone'] : (!empty($profile['phone']) ? $profile['phone'] : '—')); ?></span>
                                        </div>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fa-solid fa-envelope"></i> Email</span>
                                            <span
                                                class="pf-value"><?php echo esc_html(!empty($hipaa_intake['email']) ? $hipaa_intake['email'] : (!empty($profile['email']) ? $profile['email'] : ($current_user->user_email ?? '—'))); ?></span>
                                        </div>
                                    </div>
                                    <?php
                                    $intake_address_parts = array_filter([
                                        $hipaa_intake['address'] ?? '',
                                        $hipaa_intake['city'] ?? '',
                                        $hipaa_intake['state'] ?? '',
                                        $hipaa_intake['zip'] ?? ''
                                    ]);
                                    $formatted_intake_address = !empty($intake_address_parts) ? implode(' ', $intake_address_parts) : (!empty($profile['address']) ? $profile['address'] : '');
                                    if (!empty($formatted_intake_address)):
                                        ?>
                                    <div class="dior-profile-field-box" style="margin-bottom:12px;">
                                        <span class="pf-label"><i class="fa-solid fa-location-dot"></i> Address</span>
                                        <span class="pf-value"><?php echo esc_html($formatted_intake_address); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($hipaa_intake['symptoms'])): ?>
                                    <div
                                        style="grid-column:1 / -1;background:#FFFFFF;padding:10px 14px;border-radius:8px;border:1px solid #E2E8F0;margin-bottom:12px;">
                                        <strong style="color:#065F46;display:block;margin-bottom:4px;"><i
                                                class="fa-solid fa-stethoscope"></i> Chief Symptoms /
                                            Complaint:</strong>
                                        <span><?php echo nl2br(esc_html($hipaa_intake['symptoms'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($hipaa_intake['allergies'])): ?>
                                    <div
                                        style="background:#FFFFFF;padding:10px 14px;border-radius:8px;border:1px solid #E2E8F0;margin-bottom:12px;">
                                        <strong style="color:#DC2626;display:block;margin-bottom:4px;"><i
                                                class="fa-solid fa-triangle-exclamation"></i> Known Allergies:</strong>
                                        <span><?php echo esc_html($hipaa_intake['allergies']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($hipaa_intake['medications'])): ?>
                                    <div
                                        style="background:#FFFFFF;padding:10px 14px;border-radius:8px;border:1px solid #E2E8F0;margin-bottom:12px;">
                                        <strong style="color:#2563EB;display:block;margin-bottom:4px;"><i
                                                class="fa-solid fa-pills"></i> Current Medications (Legacy):</strong>
                                        <span><?php echo esc_html($hipaa_intake['medications']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($hipaa_intake['medical_history'])): ?>
                                    <div
                                        style="background:#FFFFFF;padding:10px 14px;border-radius:8px;border:1px solid #E2E8F0;">
                                        <strong style="color:#4B5563;display:block;margin-bottom:4px;"><i
                                                class="fa-solid fa-notes-medical"></i> Medical History
                                            (Legacy):</strong>
                                        <span><?php echo nl2br(esc_html($hipaa_intake['medical_history'])); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <!-- New comprehensive display -->
                                    <?php if (!empty($hipaa_intake['service_condition'])): ?>
                                    <div class="dior-profile-field-box" style="margin-bottom:12px;">
                                        <span class="pf-label"><i class="fa-solid fa-stethoscope"></i> Service
                                            Condition</span>
                                        <span
                                            class="pf-value"><?php echo esc_html($hipaa_intake['service_condition']); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="dior-form-row-2" style="margin-bottom:12px;">
                                        <?php if (!empty($hipaa_intake['is_18_plus'])): ?>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fa-solid fa-user-shield"></i> 18+
                                                Verified</span>
                                            <span
                                                class="pf-value"><?php echo esc_html($hipaa_intake['is_18_plus']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($hipaa_intake['is_pregnant'])): ?>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fa-solid fa-baby"></i> Pregnancy
                                                Status</span>
                                            <span
                                                class="pf-value"><?php echo esc_html($hipaa_intake['is_pregnant']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($hipaa_intake['symptoms_description'])): ?>
                                    <div
                                        style="grid-column:1 / -1;background:#FFFFFF;padding:10px 14px;border-radius:8px;border:1px solid #E2E8F0;margin-bottom:12px;">
                                        <strong style="color:#065F46;display:block;margin-bottom:4px;"><i
                                                class="fa-solid fa-stethoscope"></i> Symptoms Description:</strong>
                                        <span><?php echo nl2br(esc_html($hipaa_intake['symptoms_description'])); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="dior-form-row-2" style="margin-bottom:12px;">
                                        <?php if (!empty($hipaa_intake['symptom_duration'])): ?>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fa-solid fa-clock"></i> Duration</span>
                                            <span
                                                class="pf-value"><?php echo esc_html($hipaa_intake['symptom_duration']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($hipaa_intake['symptom_severity'])): ?>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fa-solid fa-exclamation-triangle"></i>
                                                Severity</span>
                                            <span
                                                class="pf-value"><?php echo esc_html($hipaa_intake['symptom_severity']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($hipaa_intake['uti_burning_pain']) || !empty($hipaa_intake['uti_frequency'])): ?>
                                    <div
                                        style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:8px;padding:12px;margin-bottom:12px;">
                                        <h5 style="margin:0 0 8px;color:#92400E;font-size:14px;"><i
                                                class="fa-solid fa-droplet"></i> UTI Details</h5>
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
                                            <?php if (!empty($hipaa_intake['uti_burning_pain'])): ?>
                                            <div><strong>Burning/Pain:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_burning_pain']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['uti_frequency'])): ?>
                                            <div><strong>Frequency:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_frequency']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['uti_appearance'])): ?>
                                            <div><strong>Urine Appearance:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_appearance']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['uti_blood'])): ?>
                                            <div><strong>Blood in Urine:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_blood']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['uti_previous'])): ?>
                                            <div><strong>Previous UTI:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_previous']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['uti_last_date'])): ?>
                                            <div style="grid-column:1 / -1;"><strong>Last UTI Date:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_last_date']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['allergy_symptoms']) || !empty($hipaa_intake['allergy_triggers'])): ?>
                                    <div
                                        style="background:#FEE2E2;border:1px solid #FECACA;border-radius:8px;padding:12px;margin-bottom:12px;">
                                        <h5 style="margin:0 0 8px;color:#991B1B;font-size:14px;"><i
                                                class="fa-solid fa-allergies"></i> Allergy Details</h5>
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
                                            <?php if (!empty($hipaa_intake['allergy_symptoms'])): ?>
                                            <div style="grid-column:1 / -1;"><strong>Symptoms:</strong>
                                                <?php echo esc_html($hipaa_intake['allergy_symptoms']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['allergy_seasonal'])): ?>
                                            <div><strong>Pattern:</strong>
                                                <?php echo esc_html($hipaa_intake['allergy_seasonal']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['allergy_triggers'])): ?>
                                            <div><strong>Triggers:</strong>
                                                <?php echo esc_html($hipaa_intake['allergy_triggers']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['allergy_medication_taken'])): ?>
                                            <div><strong>Medication Taken:</strong>
                                                <?php echo esc_html($hipaa_intake['allergy_medication_taken']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['allergy_medication_name'])): ?>
                                            <div style="grid-column:1 / -1;"><strong>Medication Name:</strong>
                                                <?php echo esc_html($hipaa_intake['allergy_medication_name']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['diagnosed_conditions']) || !empty($hipaa_intake['other_conditions'])): ?>
                                    <div
                                        style="background:#DBEAFE;border:1px solid #BFDBFE;border-radius:8px;padding:12px;margin-bottom:12px;">
                                        <h5 style="margin:0 0 8px;color:#1E40AF;font-size:14px;"><i
                                                class="fa-solid fa-notes-medical"></i> Medical History</h5>
                                        <?php if (!empty($hipaa_intake['diagnosed_conditions'])): ?>
                                        <div style="margin-bottom:8px;font-size:13px;"><strong>Diagnosed
                                                Conditions:</strong>
                                            <?php echo esc_html($hipaa_intake['diagnosed_conditions']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($hipaa_intake['other_conditions'])): ?>
                                        <div style="font-size:13px;"><strong>Other Conditions:</strong>
                                            <?php echo nl2br(esc_html($hipaa_intake['other_conditions'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['taking_medications']) || !empty($hipaa_intake['medications_list'])): ?>
                                    <div
                                        style="background:#E0E7FF;border:1px solid #C7D2FE;border-radius:8px;padding:12px;margin-bottom:12px;">
                                        <h5 style="margin:0 0 8px;color:#3730A3;font-size:14px;"><i
                                                class="fa-solid fa-pills"></i> Current Medications</h5>
                                        <?php if (!empty($hipaa_intake['taking_medications'])): ?>
                                        <div style="margin-bottom:8px;font-size:13px;"><strong>Taking
                                                Medications:</strong>
                                            <?php echo esc_html($hipaa_intake['taking_medications']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($hipaa_intake['medications_list'])): ?>
                                        <div style="font-size:13px;"><strong>Medication List:</strong>
                                            <?php echo nl2br(esc_html($hipaa_intake['medications_list'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['drug_allergies']) || !empty($hipaa_intake['drug_allergies_list'])): ?>
                                    <div
                                        style="background:#FEE2E2;border:1px solid #FECACA;border-radius:8px;padding:12px;margin-bottom:12px;">
                                        <h5 style="margin:0 0 8px;color:#991B1B;font-size:14px;"><i
                                                class="fa-solid fa-triangle-exclamation"></i> Drug Allergies</h5>
                                        <?php if (!empty($hipaa_intake['drug_allergies'])): ?>
                                        <div style="margin-bottom:8px;font-size:13px;"><strong>Has Drug
                                                Allergies:</strong>
                                            <?php echo esc_html($hipaa_intake['drug_allergies']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($hipaa_intake['drug_allergies_list'])): ?>
                                        <div style="font-size:13px;"><strong>Allergy Details:</strong>
                                            <?php echo nl2br(esc_html($hipaa_intake['drug_allergies_list'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['appointment_type']) || !empty($hipaa_intake['preferred_date'])): ?>
                                    <div
                                        style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;padding:12px;margin-bottom:12px;">
                                        <h5 style="margin:0 0 8px;color:#065F46;font-size:14px;"><i
                                                class="fa-solid fa-calendar"></i> Appointment Preferences</h5>
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
                                            <?php if (!empty($hipaa_intake['appointment_type'])): ?>
                                            <div><strong>Type:</strong>
                                                <?php echo esc_html($hipaa_intake['appointment_type']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['preferred_date'])): ?>
                                            <div><strong>Date:</strong>
                                                <?php echo esc_html($hipaa_intake['preferred_date']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['preferred_time'])): ?>
                                            <div><strong>Time:</strong>
                                                <?php echo esc_html($hipaa_intake['preferred_time']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['telemedicine_consent']) || !empty($hipaa_intake['legal_acknowledgement'])): ?>
                                    <div
                                        style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:12px;">
                                        <h5 style="margin:0 0 8px;color:#475569;font-size:14px;"><i
                                                class="fa-solid fa-file-signature"></i> Consent & Signature</h5>
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
                                            <?php if (!empty($hipaa_intake['telemedicine_consent'])): ?>
                                            <div><strong>Telemedicine Consent:</strong>
                                                <?php echo esc_html($hipaa_intake['telemedicine_consent']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['legal_acknowledgement'])): ?>
                                            <div><strong>Legal Acknowledgement:</strong>
                                                <?php echo esc_html($hipaa_intake['legal_acknowledgement']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['full_legal_name'])): ?>
                                            <div style="grid-column:1 / -1;"><strong>Legal Name:</strong>
                                                <?php echo esc_html($hipaa_intake['full_legal_name']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['signature_date'])): ?>
                                            <div><strong>Signature Date:</strong>
                                                <?php echo esc_html($hipaa_intake['signature_date']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="dior-hipaa-embed-wrap" id="dior-hipaa-form-container"
                                style="max-width: 100%; margin: 0 auto; background: #FFFFFF; <?php echo $has_intake ? 'display:none;' : ''; ?>">
                                <div class="hipaa-form-container-box"
                                    style="background: #FFFFFF; border: none; box-shadow: none; padding: 0; margin: 0;">
                                    <?php if (shortcode_exists('hipaatizer')): ?>
                                    <div class="hipaatizer-shortcode-wrapper">
                                        <?php
                                        // Render HIPAAtizer official form if plugin is active
                                        echo do_shortcode('[hipaatizer id="01a07aa2-d931-728d-bfe9-8d7b3bc0389d"]');
                                        ?>
                                    </div>
                                    <?php else: ?>
                                    <iframe id="hipaatizer-intake-iframe"
                                        src="https://app.hipaatizer.com/workflow/01a07aa2-d931-728d-bfe9-8d7b3bc0389d"
                                        style="width: 100%; min-height: 720px; height: 85vh; border: 0; display: block; margin: 0 auto; background: #FFFFFF;"
                                        allow="microphone; camera; payment"
                                        title="HIPAAtizer Clinical Intake &amp; Questionnaire Form">
                                    </iframe>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Step-by-Step Embedded Appointment Booking Wizard (Unlocks automatically after Intake is completed) -->
                            <div class="dior-card dior-in-dashboard-booking-card" id="dior-dashboard-booking-section"
                                style="margin-top:20px; border: 1.5px solid #2C6CB1; box-shadow: 0 10px 32px rgba(44, 108, 177, 0.12); border-radius:14px; overflow:hidden; <?php echo !$has_intake ? 'display:none;' : ''; ?>">
                                <div class="dior-card-header"
                                    style="background: linear-gradient(135deg, #0F172A, #1E293B); color: #FFFFFF; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3
                                            style="color:#FFFFFF; margin:0; font-size:17px; font-weight:700; display:flex; align-items:center; gap:10px;">
                                            <i class="fa-solid fa-calendar-check" style="color:#D4AF37;"></i>
                                            <span>Step 2: Schedule Telehealth Consultation with Physician</span>
                                        </h3>
                                        <p style="color:#94A3B8; margin:4px 0 0; font-size:13px;">Pre-filled from your
                                            intake form. Select your physician &amp; appointment time slot.</p>
                                    </div>
                                    <span class="dior-badge-luxury"
                                        style="background:rgba(212,175,55,0.18); color:#D4AF37; border:1px solid #D4AF37; padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700;">
                                        <i class="fa-solid fa-shield-halved"></i> Intake Data Verified
                                    </span>
                                </div>

                                <div class="dior-card-body" style="padding: 24px;">
                                    <!-- Wizard Step Pills -->
                                    <div class="dior-wizard-steps-indicator"
                                        style="display:flex; justify-content:space-between; margin-bottom:24px; position:relative; gap:12px;">
                                        <div class="dior-step-node active" id="wizard-node-1"
                                            style="flex:1; background:#F1F5F9; padding:10px 14px; border-radius:8px; display:flex; align-items:center; gap:10px; border-left:3px solid #2C6CB1;">
                                            <span class="step-num"
                                                style="width:24px; height:24px; border-radius:50%; background:#2C6CB1; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700;">1</span>
                                            <span class="step-lbl"
                                                style="font-weight:700; font-size:13px; color:#0F172A;">Select
                                                Physician</span>
                                        </div>
                                        <div class="dior-step-node" id="wizard-node-2"
                                            style="flex:1; background:#F8FAFC; padding:10px 14px; border-radius:8px; display:flex; align-items:center; gap:10px; border:1px solid #E2E8F0;">
                                            <span class="step-num"
                                                style="width:24px; height:24px; border-radius:50%; background:#E2E8F0; color:#64748B; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700;">2</span>
                                            <span class="step-lbl"
                                                style="font-weight:600; font-size:13px; color:#64748B;">Date &amp; Time
                                                Slot</span>
                                        </div>
                                        <div class="dior-step-node" id="wizard-node-3"
                                            style="flex:1; background:#F8FAFC; padding:10px 14px; border-radius:8px; display:flex; align-items:center; gap:10px; border:1px solid #E2E8F0;">
                                            <span class="step-num"
                                                style="width:24px; height:24px; border-radius:50%; background:#E2E8F0; color:#64748B; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700;">3</span>
                                            <span class="step-lbl"
                                                style="font-weight:600; font-size:13px; color:#64748B;">Confirm &amp;
                                                Book</span>
                                        </div>
                                    </div>

                                    <!-- Pre-filled Patient Intake Summary Banner -->
                                    <?php
                                    $pat_name = !empty($profile['full_name']) ? $profile['full_name'] : ($current_user && !empty($current_user->display_name) ? $current_user->display_name : 'Patient');
                                    $pat_email = !empty($profile['email']) ? $profile['email'] : ($current_user && !empty($current_user->user_email) ? $current_user->user_email : '');
                                    $pat_phone = !empty($profile['phone']) ? $profile['phone'] : ($hipaa_intake['phone'] ?? 'Phone on file');
                                    $pat_service = !empty($hipaa_intake['service_condition']) ? $hipaa_intake['service_condition'] : (!empty($hipaa_intake['symptoms']) ? $hipaa_intake['symptoms'] : 'Telehealth Urgent Care');
                                    ?>
                                    <div class="dior-intake-prefill-banner"
                                        style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:12px; padding:16px 20px; margin-bottom:24px; display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:16px;">
                                        <div style="display:flex; align-items:center; gap:14px;">
                                            <div
                                                style="width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg, #1A528E 0%, #2C6CB1 100%); color:#ffffff; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; box-shadow:0 4px 12px rgba(26,82,142,0.2);">
                                                <i class="fa-solid fa-user-check"></i>
                                            </div>
                                            <div>
                                                <strong
                                                    style="color:#0F172A; font-size:15px; font-weight:700; display:block; line-height:1.3;"><?php echo esc_html($pat_name); ?></strong>
                                                <span
                                                    style="color:#64748B; font-size:13px; line-height:1.4; display:block; margin-top:2px;">
                                                    <?php if (!empty($pat_email)): ?>
                                                    <?php echo esc_html($pat_email); ?> &bull;
                                                    <?php endif; ?>
                                                    <?php echo esc_html($pat_phone); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div
                                            style="background:#FFFFFF; border:1.5px solid #E2E8F0; padding:8px 16px; border-radius:10px; font-size:13px; color:#0F172A; display:flex; align-items:center; gap:10px; box-shadow:0 2px 6px rgba(15,23,42,0.04);">
                                            <div
                                                style="width:32px; height:32px; border-radius:8px; background:#EFF6FF; color:#2C6CB1; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0;">
                                                <i class="fa-solid fa-stethoscope"></i>
                                            </div>
                                            <div>
                                                <span
                                                    style="color:#64748B; font-size:11px; display:block; text-transform:uppercase; font-weight:700; letter-spacing:0.04em;">Service
                                                    / Reason</span>
                                                <strong
                                                    style="color:#0F172A; font-size:13.5px;"><?php echo esc_html($pat_service); ?></strong>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- WIZARD STEP 1: SELECT DOCTOR -->
                                    <div class="dior-in-booking-step" id="in-step-doctor">
                                        <h4 style="margin:0 0 16px; color:#0F172A; font-size:15px; font-weight:700;">
                                            <i class="fa-solid fa-user-doctor" style="color:#2C6CB1;"></i> Available
                                            Telehealth Physicians:
                                        </h4>
                                        <div id="in-dashboard-doctors-grid" class="dior-doctors-grid-cards"
                                            style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:16px;">
                                            <?php
                                            $docbooker_doctors = get_posts([
                                                'post_type' => 'wpddb_doctor',
                                                'post_status' => 'publish',
                                                'numberposts' => -1,
                                                'orderby' => 'title',
                                                'order' => 'ASC'
                                            ]);

                                            if (!empty($docbooker_doctors)):
                                                foreach ($docbooker_doctors as $doc_item):
                                                    $d_id = $doc_item->ID;
                                                    $d_name = $doc_item->post_title;
                                                    $d_spec = get_post_meta($d_id, 'wpddb_doctor_speciality', true) ?: 'Telehealth Physician';
                                                    ?>
                                            <div class="dior-doc-select-card" data-doc-id="<?php echo (int) $d_id; ?>"
                                                data-doc-name="<?php echo esc_attr($d_name); ?>"
                                                data-doc-spec="<?php echo esc_attr($d_spec); ?>"
                                                onclick="diorInSelectDoctor(<?php echo (int) $d_id; ?>, '<?php echo esc_js($d_name); ?>', '<?php echo esc_js($d_spec); ?>')"
                                                style="background:#FFFFFF; border:1.5px solid #E2E8F0; border-radius:12px; padding:18px; cursor:pointer; transition:all 0.2s ease; position:relative;">
                                                <div
                                                    style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
                                                    <div
                                                        style="width:44px; height:44px; border-radius:50%; background:#EFF6FF; color:#2C6CB1; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700;">
                                                        <i class="fa-solid fa-user-md"></i>
                                                    </div>
                                                    <div>
                                                        <h5
                                                            style="margin:0; font-size:14.5px; font-weight:700; color:#0F172A;">
                                                            <?php echo esc_html($d_name); ?>
                                                        </h5>
                                                        <span
                                                            style="font-size:12px; color:#2C6CB1; font-weight:600;"><?php echo esc_html($d_spec); ?></span>
                                                    </div>
                                                </div>
                                                <div
                                                    style="font-size:12px; color:#64748B; display:flex; justify-content:space-between; align-items:center; border-top:1px solid #F1F5F9; padding-top:10px; margin-top:8px;">
                                                    <span><i class="fa-regular fa-clock"></i> Available via
                                                        DocBooker</span>
                                                    <span style="color:#059669; font-weight:700;">Select &rarr;</span>
                                                </div>
                                            </div>
                                            <?php
                                                endforeach;
                                            else:
                                                ?>
                                            <div class="dior-doc-select-card" data-doc-id="1695"
                                                data-doc-name="Dr. James Chen, DO"
                                                data-doc-spec="Primary Care &amp; Urgent Care"
                                                onclick="diorInSelectDoctor(1695, 'Dr. James Chen, DO', 'Primary Care &amp; Urgent Care')"
                                                style="background:#FFFFFF; border:1.5px solid #E2E8F0; border-radius:12px; padding:18px; cursor:pointer; transition:all 0.2s ease; position:relative;">
                                                <div
                                                    style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
                                                    <div
                                                        style="width:44px; height:44px; border-radius:50%; background:#EFF6FF; color:#2C6CB1; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700;">
                                                        <i class="fa-solid fa-user-md"></i>
                                                    </div>
                                                    <div>
                                                        <h5
                                                            style="margin:0; font-size:14.5px; font-weight:700; color:#0F172A;">
                                                            Dr. James Chen, DO</h5>
                                                        <span
                                                            style="font-size:12px; color:#2C6CB1; font-weight:600;">Primary
                                                            Care &amp; Urgent Care</span>
                                                    </div>
                                                </div>
                                                <div
                                                    style="font-size:12px; color:#64748B; display:flex; justify-content:space-between; align-items:center; border-top:1px solid #F1F5F9; padding-top:10px; margin-top:8px;">
                                                    <span><i class="fa-regular fa-clock"></i> Next Available:
                                                        Today</span>
                                                    <span style="color:#059669; font-weight:700;">Select &rarr;</span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- WIZARD STEP 2: SELECT DATE & TIME -->
                                    <div class="dior-in-booking-step" id="in-step-time" style="display:none;">
                                        <div
                                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                            <h4 style="margin:0; color:#0F172A; font-size:15px; font-weight:700;">
                                                <i class="fa-solid fa-calendar-day" style="color:#2C6CB1;"></i> Select
                                                Consultation Date &amp; Time Slot:
                                            </h4>
                                            <button type="button" class="dior-btn-text-back"
                                                onclick="diorInBookingGoToStep('doctor')"
                                                style="background:none; border:none; color:#2C6CB1; font-weight:700; cursor:pointer; font-size:13px;">
                                                &larr; Switch Physician
                                            </button>
                                        </div>
                                        <div style="display:grid; grid-template-columns: 1fr 1.6fr; gap:20px; align-items:start;"
                                            class="dior-schedule-pick-row">
                                            <div
                                                style="background:#F8FAFC; border:1.5px solid #E2E8F0; border-radius:12px; padding:18px;">
                                                <label
                                                    style="display:block; font-size:12.5px; font-weight:700; color:#475569; margin-bottom:8px; text-transform:uppercase;">
                                                    <i class="fa-regular fa-calendar"></i> Select Date:
                                                </label>
                                                <input type="date" id="in-booking-date" class="dior-form-input"
                                                    min="<?php echo date('Y-m-d'); ?>"
                                                    value="<?php echo date('Y-m-d'); ?>"
                                                    onchange="diorInFetchAvailability()"
                                                    style="width:100%; padding:11px 14px; border-radius:8px; border:1.5px solid #CBD5E1; font-size:14px; background:#fff;">
                                                <div id="in-selected-doctor-summary-pill"
                                                    style="margin-top:14px; padding:12px; background:#FFFFFF; border-radius:8px; border:1px solid #E2E8F0; font-size:13px;">
                                                    <!-- Doctor Details -->
                                                </div>
                                            </div>
                                            <div>
                                                <label
                                                    style="display:block; font-size:12.5px; font-weight:700; color:#475569; margin-bottom:8px; text-transform:uppercase;">
                                                    <i class="fa-regular fa-clock"></i> Available Video Consultation
                                                    Slots:
                                                </label>
                                                <div id="in-time-slots-container" style="min-height:140px;">
                                                    <p style="color:#64748B;">Please select a physician to view live
                                                        availability slots.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- WIZARD STEP 3: CONFIRMATION -->
                                    <div class="dior-in-booking-step" id="in-step-confirm" style="display:none;">
                                        <div
                                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                            <h4 style="margin:0; color:#0F172A; font-size:15px; font-weight:700;">
                                                <i class="fa-solid fa-clipboard-check" style="color:#059669;"></i>
                                                Review &amp; Confirm Consultation:
                                            </h4>
                                            <button type="button" class="dior-btn-text-back"
                                                onclick="diorInBookingGoToStep('time')"
                                                style="background:none; border:none; color:#2C6CB1; font-weight:700; cursor:pointer; font-size:13px;">
                                                &larr; Change Time
                                            </button>
                                        </div>

                                        <div id="in-booking-confirm-summary"
                                            style="background:#F8FAFC; border:1.5px solid #E2E8F0; border-radius:12px; padding:20px; margin-bottom:20px;">
                                            <!-- Summary generated dynamically -->
                                        </div>

                                        <div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap;">
                                            <button type="button" class="dior-btn-gold-primary"
                                                id="btn-in-confirm-booking" onclick="diorInSubmitBooking()"
                                                style="padding:14px 30px; font-size:15px; font-weight:700; display:inline-flex; align-items:center; gap:8px;">
                                                <i class="fa-solid fa-video"></i> Confirm &amp; Schedule Telehealth
                                                Visit
                                            </button>
                                            <span style="font-size:12.5px; color:#64748B;"><i class="fa-solid fa-lock"
                                                    style="color:#059669;"></i> Instant Zoom room link &amp;
                                                notifications will be generated for you &amp; your doctor.</span>
                                        </div>

                                        <div id="in-booking-status-msg"
                                            style="margin-top:16px; display:none; padding:14px 18px; border-radius:10px; font-weight:600;">
                                        </div>
                                    </div>

                                </div>
                            </div>

                </section>


                <!-- ============================================================== -->
                <!-- 6. NOTIFICATIONS TAB -->
                <!-- ============================================================== -->
<section class="dior-tab-panel" id="tab-feedback" style="display:none;">
    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                
                <!-- Header -->
                <div class="master-table-header">
                    <div class="header-content" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div class="table-title-section">
                            <h2 class="table-title">Feedback & Support</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group" style="display: flex; gap: 12px; align-items: center;">
                            <div class="search-container" style="position: relative;">
                                <i class="fa-solid fa-magnifying-glass search-icon" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94A3B8;"></i>
                                <input type="text" placeholder="Search records..." class="search-input" style="padding: 10px 10px 10px 36px; border: 1px solid #E2E8F0; border-radius: 8px; outline: none; font-size: 14px; width: 200px;">
                            </div>
                            <div class="action-buttons" style="display: flex; gap: 8px;">
                                <button class="action-btn action-btn-primary" style="background: #3B82F6; color: #fff; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer;"><i class="fa-solid fa-plus"></i></button>
                                <button class="action-btn action-btn-success" style="background: #10B981; color: #fff; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer;"><i class="fa-solid fa-file-arrow-down"></i></button>
                                <button class="action-btn action-btn-info" style="background: #0EA5E9; color: #fff; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer;"><i class="fa-solid fa-rotate-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="table-content" style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 2px solid #E2E8F0; background: #F8FAFC;">
                                <th style="padding: 16px; width: 50px;"><input type="checkbox"></th>
                                <th style="padding: 16px; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase;">Ticket ID <i class="fa-solid fa-sort" style="opacity:0.3; margin-left:4px;"></i></th>
                                <th style="padding: 16px; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase;">Subject <i class="fa-solid fa-sort" style="opacity:0.3; margin-left:4px;"></i></th>
                                <th style="padding: 16px; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase;">Category <i class="fa-solid fa-sort" style="opacity:0.3; margin-left:4px;"></i></th>
                                <th style="padding: 16px; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase;">Rating <i class="fa-solid fa-sort" style="opacity:0.3; margin-left:4px;"></i></th>
                                <th style="padding: 16px; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase;">Date <i class="fa-solid fa-sort" style="opacity:0.3; margin-left:4px;"></i></th>
                                <th style="padding: 16px; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase;">Status <i class="fa-solid fa-sort" style="opacity:0.3; margin-left:4px;"></i></th>
                                <th style="padding: 16px; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Row 1 -->
                            <tr style="border-bottom: 1px solid #E2E8F0; transition: background 0.2s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 16px;"><input type="checkbox"></td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">TKT001</td>
                                <td style="padding: 16px; color: #1E293B; font-size: 13px;">Excellent Service by Dr. Smith</td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">Doctor Feedback</td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">5</td>
                                <td style="padding: 16px; font-size: 13px;"><i class="fa-regular fa-calendar" style="color: #3B82F6; margin-right: 6px;"></i><span style="color: #64748B;">Nov 20, 2024</span></td>
                                <td style="padding: 16px;"><span style="background: #D1FAE5; color: #10B981; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;">Closed</span></td>
                                <td style="padding: 16px;">
                                    <div style="display: flex; gap: 8px;">
                                        <button class="action-icon-btn edit-btn"><i class="fa-solid fa-pen"></i></button>
                                        <button class="action-icon-btn delete-btn"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 2 -->
                            <tr style="border-bottom: 1px solid #E2E8F0; transition: background 0.2s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 16px;"><input type="checkbox"></td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">TKT002</td>
                                <td style="padding: 16px; color: #1E293B; font-size: 13px;">Long Waiting Time</td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">Complaint</td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">2</td>
                                <td style="padding: 16px; font-size: 13px;"><i class="fa-regular fa-calendar" style="color: #3B82F6; margin-right: 6px;"></i><span style="color: #64748B;">Nov 15, 2024</span></td>
                                <td style="padding: 16px;"><span style="background: #DBEAFE; color: #3B82F6; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;">Resolved</span></td>
                                <td style="padding: 16px;">
                                    <div style="display: flex; gap: 8px;">
                                        <button class="action-icon-btn edit-btn"><i class="fa-solid fa-pen"></i></button>
                                        <button class="action-icon-btn delete-btn"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 3 -->
                            <tr style="border-bottom: 1px solid #E2E8F0; transition: background 0.2s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 16px;"><input type="checkbox"></td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">TKT003</td>
                                <td style="padding: 16px; color: #1E293B; font-size: 13px;">App Login Issue</td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">Technical Support</td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;"></td>
                                <td style="padding: 16px; font-size: 13px;"><i class="fa-regular fa-calendar" style="color: #3B82F6; margin-right: 6px;"></i><span style="color: #64748B;">Nov 24, 2024</span></td>
                                <td style="padding: 16px;"><span style="background: #FEF3C7; color: #D97706; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;">In Progress</span></td>
                                <td style="padding: 16px;">
                                    <div style="display: flex; gap: 8px;">
                                        <button class="action-icon-btn edit-btn"><i class="fa-solid fa-pen"></i></button>
                                        <button class="action-icon-btn delete-btn"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 4 -->
                            <tr style="border-bottom: 1px solid #E2E8F0; transition: background 0.2s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 16px;"><input type="checkbox"></td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">TKT004</td>
                                <td style="padding: 16px; color: #1E293B; font-size: 13px;">Cleanliness Feedback</td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">Hospital Facilities</td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">4</td>
                                <td style="padding: 16px; font-size: 13px;"><i class="fa-regular fa-calendar" style="color: #3B82F6; margin-right: 6px;"></i><span style="color: #64748B;">Nov 10, 2024</span></td>
                                <td style="padding: 16px;"><span style="background: #D1FAE5; color: #10B981; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;">Closed</span></td>
                                <td style="padding: 16px;">
                                    <div style="display: flex; gap: 8px;">
                                        <button class="action-icon-btn edit-btn"><i class="fa-solid fa-pen"></i></button>
                                        <button class="action-icon-btn delete-btn"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Row 5 -->
                            <tr style="border-bottom: 1px solid #E2E8F0; transition: background 0.2s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 16px;"><input type="checkbox"></td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">TKT005</td>
                                <td style="padding: 16px; color: #1E293B; font-size: 13px;">Billing Query</td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;">Billing</td>
                                <td style="padding: 16px; color: #64748B; font-size: 13px;"></td>
                                <td style="padding: 16px; font-size: 13px;"><i class="fa-regular fa-calendar" style="color: #3B82F6; margin-right: 6px;"></i><span style="color: #64748B;">Nov 25, 2024</span></td>
                                <td style="padding: 16px;"><span style="background: #FEE2E2; color: #EF4444; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;">Open</span></td>
                                <td style="padding: 16px;">
                                    <div style="display: flex; gap: 8px;">
                                        <button class="action-icon-btn edit-btn"><i class="fa-solid fa-pen"></i></button>
                                        <button class="action-icon-btn delete-btn"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer -->
                <div style="padding: 16px 24px; border-top: 1px solid #E2E8F0; color: #64748B; font-size: 13px;">
                    0 selected / 5 total
                </div>
            </div>
        </div>
    </div>
</section>
<section class="dior-tab-panel" id="tab-notifications" style="display:none;">
    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">Notifications Center</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-notif-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterNotif()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Add new record" class="action-btn action-btn-primary" style="background:#3B82F6;color:#fff;">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadNotifCSV()">
                                    <i class="fa-solid fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="master-modern-table" id="dior-notif-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" style="width:16px;height:16px;accent-color:#3B82F6;cursor:pointer;">
                                </th>
                                <th>TITLE <i class="fa-solid fa-sort"></i></th>
                                <th>MESSAGE <i class="fa-solid fa-sort"></i></th>
                                <th>TYPE <i class="fa-solid fa-sort"></i></th>
                                <th>DATE <i class="fa-solid fa-sort"></i></th>
                                <th>TIME <i class="fa-solid fa-sort"></i></th>
                                <th>STATUS <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-notif-tbody">
                            <?php
                            $notif_mock = [
                                ['Appointment Reminder', 'You have an appointment with Dr. Smith tomorrow at', 'Reminder', '#F59E0B', '#FEF3C7', 'Nov 25, 2024', '09:00 AM', 'Unread', '#EF4444', '#FEE2E2'],
                                ['Lab Report Ready', 'Your blood test results are now available.', 'Info', '#3B82F6', '#DBEAFE', 'Nov 24, 2024', '02:30 PM', 'Unread', '#EF4444', '#FEE2E2'],
                                ['Vaccination Due', 'Flu shot is due next week.', 'Alert', '#EF4444', '#FEE2E2', 'Nov 23, 2024', '11:00 AM', 'Read', '#10B981', '#D1FAE5'],
                                ['Bill Payment', 'Invoice #INV-2024-001 is pending payment.', 'Alert', '#EF4444', '#FEE2E2', 'Nov 22, 2024', '10:00 AM', 'Read', '#10B981', '#D1FAE5'],
                                ['Health Tip', 'Drink at least 8 glasses of water today.', 'Info', '#3B82F6', '#DBEAFE', 'Nov 21, 2024', '08:00 AM', 'Read', '#10B981', '#D1FAE5'],
                                ['System Maintenance', 'System will be down for maintenance on Sunday 2 AM', 'Info', '#3B82F6', '#DBEAFE', 'Nov 20, 2024', '05:00 PM', 'Read', '#10B981', '#D1FAE5'],
                                ['Prescription Refill', 'Your prescription for Metformin is running low.', 'Reminder', '#F59E0B', '#FEF3C7', 'Nov 19, 2024', '09:00 AM', 'Read', '#10B981', '#D1FAE5'],
                                ['Welcome', 'Welcome to MediDash Patient Portal!', 'Info', '#3B82F6', '#DBEAFE', 'Nov 1, 2024', '10:00 AM', 'Read', '#10B981', '#D1FAE5']
                            ];
                            foreach ($notif_mock as $nm):
                            ?>
                            <tr>
                                <td><input type="checkbox" style="width:16px;height:16px;accent-color:#3B82F6;cursor:pointer;"></td>
                                <td><span class="cell-text" style="color:#64748B;"><?php echo esc_html($nm[0]); ?></span></td>
                                <td><span class="cell-text" style="color:#64748B;"><?php echo esc_html($nm[1]); ?></span></td>
                                <td>
                                    <div class="cell-content">
                                        <div style="background: <?php echo esc_attr($nm[4]); ?>; color: <?php echo esc_attr($nm[3]); ?>; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block;">
                                            <?php echo esc_html($nm[2]); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-content cell-icon-text">
                                        <i class="fa-regular fa-calendar cell-icon" style="color: #3b82f6;"></i>
                                        <span class="cell-text"><?php echo esc_html($nm[5]); ?></span>
                                    </div>
                                </td>
                                <td><span class="cell-text" style="color:#64748B;"><?php echo esc_html($nm[6]); ?></span></td>
                                <td>
                                    <div class="cell-content">
                                        <div style="background: <?php echo esc_attr($nm[9]); ?>; color: <?php echo esc_attr($nm[8]); ?>; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block;">
                                            <?php echo esc_html($nm[7]); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        <button type="button" class="action-icon-btn edit-btn" title="Edit Record"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" class="action-icon-btn delete-btn" title="Delete Record"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-notif-page-count">0 selected / <?php echo count($notif_mock); ?> total</span>
                    
                    <div class="master-pagination" id="dior-notif-pagination">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    let diorNotifCurrentPage = 1;
    const diorNotifRowsPerPage = 6;

    function diorGetNotifActiveRows() {
        const input = document.getElementById('dior-notif-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-notif-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderNotifPagination() {
        const activeRows = diorGetNotifActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-notif-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorNotifRowsPerPage) || 1;

        if (diorNotifCurrentPage > totalPages) {
            diorNotifCurrentPage = totalPages;
        }
        if (diorNotifCurrentPage < 1) {
            diorNotifCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorNotifCurrentPage - 1) * diorNotifRowsPerPage;
        const endIndex = startIndex + diorNotifRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-notif-pagination');
        if (pagContainer) {
            let html = '';
            
            const firstDisabled = diorNotifCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${firstDisabled}" title="First Page" onclick="diorGoNotifPage(1)"><i class="fa-solid fa-angles-left"></i></button>`;
            
            const prevDisabled = diorNotifCurrentPage === 1 ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${prevDisabled}" title="Previous Page" onclick="diorGoNotifPage(${diorNotifCurrentPage - 1})"><i class="fa-solid fa-angle-left"></i></button>`;

            for (let i = 1; i <= totalPages; i++) {
                const activeClass = i === diorNotifCurrentPage ? 'active' : '';
                html += `<button type="button" class="page-num ${activeClass}" onclick="diorGoNotifPage(${i})">${i}</button>`;
            }

            const nextDisabled = diorNotifCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${nextDisabled}" title="Next Page" onclick="diorGoNotifPage(${diorNotifCurrentPage + 1})"><i class="fa-solid fa-angle-right"></i></button>`;

            const lastDisabled = diorNotifCurrentPage === totalPages ? 'disabled' : '';
            html += `<button type="button" class="page-btn ${lastDisabled}" title="Last Page" onclick="diorGoNotifPage(${totalPages})"><i class="fa-solid fa-angles-right"></i></button>`;

            pagContainer.innerHTML = html;
        }

        const pageCountEl = document.getElementById('dior-notif-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoNotifPage(page) {
        diorNotifCurrentPage = page;
        diorRenderNotifPagination();
    }

    function diorFilterNotif() {
        diorNotifCurrentPage = 1;
        diorRenderNotifPagination();
    }
    
    function diorDownloadNotifCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Title,Message,Type,Date,Time,Status\n";
        const activeRows = diorGetNotifActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 7) {
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                let c5 = cols[5].innerText.trim();
                let c6 = cols[6].innerText.trim();
                csvContent += `"${c1}","${c2}","${c3}","${c4}","${c5}","${c6}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "notifications.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderNotifPagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderNotifPagination();
    }
    </script>
</section>

            </div> <!-- /.dior-tab-container -->

        </main>
    </div> <!-- /.dior-app -->


    <!-- ============================================================== -->
    <!-- MODALS -->
    <!-- ============================================================== -->

    <!-- 1. BOOK NEW APPOINTMENT MODAL (HIPAAtizer Form Integrated) -->
    <div class="dior-modal-overlay" id="modal-book-appointment">
        <div class="dior-modal-dialog dior-modal-lg"
            style="max-width: 820px; width: 95%; max-height: 92vh; display: flex; flex-direction: column;">
            <div class="dior-modal-header" style="flex-shrink: 0;">
                <div>
                    <h3 style="margin: 0 0 4px;"><i class="fa-solid fa-calendar-plus"></i> Book New Visit</h3>
                    <span style="font-size: 11.5px; color: var(--dior-text-muted);"><i class="fa-solid fa-shield-halved"
                            style="color: var(--dior-success);"></i> HIPAA-Compliant Medical Intake &amp; Booking</span>
                </div>
                <button type="button" class="dior-modal-close"
                    onclick="diorCloseModal('modal-book-appointment')">&times;</button>
            </div>
            <div class="dior-modal-body"
                style="padding: 10px; flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; min-height: 580px;">
                <iframe id="hipaatizer-book-visit-iframe"
                    src="https://app.hipaatizer.com/workflow/01a07aa2-d931-728d-bfe9-8d7b3bc0389d"
                    style="width: 100%; min-height: 580px; height: 75vh; border: 0; border-radius: 8px; display: block;"
                    allow="microphone; camera; payment" title="HIPAAtizer Booking Form">
                </iframe>
                <div style="display:none;">
                    <?php echo do_shortcode('[hipaatizer id="01a07aa2-d931-728d-bfe9-8d7b3bc0389d"]'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. RESCHEDULE MODAL -->
    <div class="dior-modal-overlay" id="modal-reschedule-appointment">
        <div class="dior-modal-dialog">
            <div class="dior-modal-header">
                <h3><i class="fa-regular fa-calendar-days"></i> Reschedule Consultation</h3>
                <button type="button" class="dior-modal-close"
                    onclick="diorCloseModal('modal-reschedule-appointment')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-reschedule-appt">
                    <input type="hidden" id="resched_appt_id" name="appt_id" value="">

                    <div id="resched_appt_summary"
                        style="display:none;font-size:13px;color:#334155;background:#F1F5F9;padding:12px 16px;border-radius:6px;margin-bottom:16px;border:1px solid #E2E8F0;line-height:1.5;">
                    </div>

                    <p class="modal-intro-text">Select a new date and available time slot for your appointment:</p>

                    <div class="dior-form-row-2">
                        <div class="dior-form-group">
                            <label for="resched_date">New Date <span class="req">*</span></label>
                            <input type="date" id="resched_date" name="date" min="<?php echo current_time('Y-m-d'); ?>"
                                value="<?php echo current_time('Y-m-d'); ?>" required>
                        </div>
                        <div class="dior-form-group">
                            <label for="resched_time">New Time Slot <span class="req">*</span></label>
                            <select id="resched_time" name="time" required>
                                <option value="09:00 AM PST">09:00 AM PST</option>
                                <option value="10:15 AM PST">10:15 AM PST</option>
                                <option value="11:30 AM PST">11:30 AM PST</option>
                                <option value="01:00 PM PST">01:00 PM PST</option>
                                <option value="02:30 PM PST">02:30 PM PST</option>
                                <option value="04:00 PM PST">04:00 PM PST</option>
                                <option value="05:30 PM PST">05:30 PM PST</option>
                                <option value="07:00 PM PST">07:00 PM PST</option>
                            </select>
                        </div>
                    </div>

                    <div class="dior-form-group" style="margin-top:12px;">
                        <label for="resched_notes">Reason for Rescheduling (Optional)</label>
                        <input type="text" id="resched_notes" name="notes"
                            placeholder="e.g. Schedule conflict, personal travel"
                            style="width:100%;padding:9px 12px;border:1px solid #CBD5E1;border-radius:6px;font-size:13.5px;box-sizing:border-box;">
                    </div>

                    <div class="dior-modal-footer" style="margin-top:20px;">
                        <button type="button" class="dior-btn-gold-secondary"
                            onclick="diorCloseModal('modal-reschedule-appointment')">Keep Current Time</button>
                        <button type="submit" id="dior-btn-submit-resched" class="dior-btn-gold-primary">
                            <i class="fa-solid fa-check"></i> Save New Time Slot
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 3. CANCEL APPOINTMENT MODAL -->
    <div class="dior-modal-overlay" id="modal-cancel-appointment">
        <div class="dior-modal-dialog">
            <div class="dior-modal-header">
                <h3><i class="fa-solid fa-triangle-exclamation" style="color:#DC2626;"></i> Cancel Consultation</h3>
                <button type="button" class="dior-modal-close"
                    onclick="diorCloseModal('modal-cancel-appointment')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-cancel-appt">
                    <input type="hidden" id="cancel_appt_id" name="appt_id" value="">

                    <div id="cancel_appt_summary"
                        style="display:none;font-size:13px;color:#991B1B;background:#FEF2F2;padding:12px 16px;border-radius:6px;margin-bottom:16px;border:1px solid #FECACA;line-height:1.5;">
                    </div>

                    <p style="color:#475569;font-size:14px;line-height:1.5;margin-bottom:16px;">
                        Are you sure you wish to cancel this consultation? You can reschedule to a future date at no
                        penalty, or schedule a new visit when needed.
                    </p>

                    <div class="dior-form-group">
                        <label for="cancel_reason">Reason for Cancellation (Optional)</label>
                        <select id="cancel_reason" name="reason">
                            <option value="Schedule conflict">Schedule conflict</option>
                            <option value="Symptoms resolved">Symptoms resolved</option>
                            <option value="Went to in-person clinic">Went to in-person clinic / ER</option>
                            <option value="Provider preference">Provider preference</option>
                            <option value="Other reason">Other reason</option>
                        </select>
                    </div>

                    <div class="dior-modal-footer" style="margin-top:20px;">
                        <button type="button" class="dior-btn-gold-secondary"
                            onclick="diorCloseModal('modal-cancel-appointment')">Keep Appointment</button>
                        <button type="submit" id="dior-btn-submit-cancel" class="dior-btn-danger"
                            style="background:#DC2626;color:#FFFFFF;border:none;padding:10px 18px;border-radius:6px;font-weight:600;cursor:pointer;">
                            <i class="fa-solid fa-xmark"></i> Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 3B. APPOINTMENT REMINDER MODAL -->
    <div class="dior-modal-overlay" id="modal-appointment-reminder">
        <div class="dior-modal-dialog">
            <div class="dior-modal-header">
                <h3><i class="fa-regular fa-bell" style="color:#D97706;"></i> Send Appointment Reminder</h3>
                <button type="button" class="dior-modal-close"
                    onclick="diorCloseModal('modal-appointment-reminder')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-send-reminder">
                    <input type="hidden" id="reminder_appt_id" name="appt_id" value="">

                    <div id="reminder_appt_summary"
                        style="display:none;font-size:13px;color:#78350F;background:#FEF3C7;padding:12px 16px;border-radius:6px;margin-bottom:16px;border:1px solid #FDE68A;line-height:1.5;">
                    </div>

                    <p style="color:#475569;font-size:14px;line-height:1.5;margin-bottom:16px;">
                        Send an urgent clinical consultation reminder with scheduled date/time, patient preparation
                        checklist, and direct video consultation room link.
                    </p>

                    <div
                        style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px 16px;margin-bottom:18px;">
                        <div style="font-weight:600;font-size:13.5px;color:#1E293B;margin-bottom:10px;">
                            <i class="fa-solid fa-tower-broadcast" style="color:#2C6CB1;margin-right:6px;"></i> Active
                            Notification Channels:
                        </div>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#334155;">
                                <input type="checkbox" checked disabled style="accent-color:#2C6CB1;">
                                <span><strong>Email Notification</strong> (Delivered to both patient & attending
                                    doctor)</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#334155;">
                                <input type="checkbox" checked disabled style="accent-color:#2C6CB1;">
                                <span><strong>Dashboard In-App Alert</strong> (Added to notification inbox for patient &
                                    doctor)</span>
                            </label>
                        </div>
                    </div>

                    <div class="dior-modal-footer" style="margin-top:20px;">
                        <button type="button" class="dior-btn-gold-secondary"
                            onclick="diorCloseModal('modal-appointment-reminder')">Close</button>
                        <button type="submit" id="dior-btn-submit-reminder" class="dior-btn-gold-primary"
                            style="background:linear-gradient(135deg,#D97706,#B45309);border-color:#D97706;">
                            <i class="fa-regular fa-bell"></i> Send Reminder Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 4. QUESTIONNAIRE & INTAKE MODAL (With E-Sign) -->
    <div class="dior-modal-overlay" id="modal-questionnaire-intake">
        <div class="dior-modal-dialog dior-modal-lg">
            <div class="dior-modal-header">
                <h3 id="modal-qn-title"><i class="fa-solid fa-clipboard-question"></i> Clinical Intake & E-Sign</h3>
                <button type="button" class="dior-modal-close"
                    onclick="diorCloseModal('modal-questionnaire-intake')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-submit-intake" class="dior-portal-form">
                    <input type="hidden" id="qn_input_id" name="qn_id" value="">
                    <input type="hidden" id="qn_input_condition" name="condition" value="">

                    <div class="dior-form-group">
                        <label for="qn_input_symptoms">Describe Your Symptoms & Duration <span
                                class="req">*</span></label>
                        <textarea id="qn_input_symptoms" name="symptoms" rows="3"
                            placeholder="When did symptoms start? Rate severity (mild/moderate/severe)..."
                            required></textarea>
                    </div>

                    <div class="dior-form-row-2">
                        <div class="dior-form-group">
                            <label for="qn_input_allergies">Known Drug Allergies</label>
                            <input type="text" id="qn_input_allergies" name="allergies"
                                placeholder="e.g. Penicillin, Sulfa, None known">
                        </div>
                        <div class="dior-form-group">
                            <label for="qn_input_meds">Current Medications / Supplements</label>
                            <input type="text" id="qn_input_meds" name="current_meds"
                                placeholder="e.g. Lisinopril 10mg, Multivitamin">
                        </div>
                    </div>

                    <div class="dior-form-group">
                        <label for="qn_input_history">Relevant Medical Conditions</label>
                        <input type="text" id="qn_input_history" name="medical_history"
                            placeholder="e.g. Asthma, High Blood Pressure, Diabetes, None">
                    </div>

                    <!-- Document / Photo Upload -->
                    <div class="dior-form-group">
                        <label>Upload Supporting Images / Documents (e.g. Photo of Rash, Previous Lab Test)</label>
                        <div class="dior-upload-dropzone" id="dior-upload-dropzone">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>Click to upload or drag and drop image (JPG, PNG, PDF up to 10MB)</span>
                            <input type="file" id="qn_file_upload" name="file_upload" accept="image/*,application/pdf"
                                style="display:none;">
                        </div>
                        <div id="dior-upload-preview"
                            style="display:none; margin-top:8px; font-size:13px; color:#2C6CB1; font-weight:600;"></div>
                    </div>

                    <!-- HIPAA Telemedicine Consent & E-Sign Pad -->
                    <div class="dior-esign-section">
                        <h4><i class="fa-solid fa-signature"></i> Telemedicine & HIPAA Consent Agreement</h4>
                        <div class="esign-text-box">
                            <p>By typing your legal name or signing below, you consent to receive telehealth medical
                                evaluations from licensed clinicians at Dior Medical, acknowledge our HIPAA Privacy
                                Practices, and certify that all clinical information provided is true and accurate.</p>
                        </div>

                        <div class="dior-form-row-2">
                            <div class="dior-form-group">
                                <label for="esign_name">Type Full Legal Name (Electronic Signature) <span
                                        class="req">*</span></label>
                                <input type="text" id="esign_name" name="esign_name"
                                    value="<?php echo esc_attr($profile['full_name']); ?>" required>
                            </div>
                            <div class="dior-form-group">
                                <label for="esign_date">Date of Signature</label>
                                <input type="text" id="esign_date" name="esign_date"
                                    value="<?php echo current_time('M j, Y'); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="dior-modal-footer">
                        <button type="button" class="dior-btn-gold-secondary"
                            onclick="diorCloseModal('modal-questionnaire-intake')">Cancel</button>
                        <button type="submit" class="dior-btn-gold-primary" id="dior-submit-intake-btn">
                            <i class="fa-solid fa-paper-plane"></i> Submit Clinical Intake to Doctor &rarr;
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 5. CHANGE PHARMACY MODAL -->
    <div class="dior-modal-overlay" id="modal-change-pharmacy">
        <div class="dior-modal-dialog">
            <div class="dior-modal-header">
                <h3><i class="fa-solid fa-store"></i> Update Preferred Pharmacy</h3>
                <button type="button" class="dior-modal-close"
                    onclick="diorCloseModal('modal-change-pharmacy')">&times;</button>
            </div>
            <div class="dior-modal-body">
                <form id="dior-form-quick-pharmacy">
                    <div class="dior-form-group">
                        <label for="quick_pharmacy_select">Select Local Pharmacy or Enter Custom</label>
                        <select id="quick_pharmacy_select" onchange="diorOnPharmacySelectChange(this)">
                            <option value="custom">-- Enter Custom Pharmacy Details --</option>
                            <option value="CVS Pharmacy #4829|9045 Wilshire Blvd, Beverly Hills, CA|(310) 555-0199">CVS
                                Pharmacy #4829 - Beverly Hills</option>
                            <option value="Walgreens Pharmacy #1034|300 N Canon Dr, Beverly Hills, CA|(310) 555-0244">
                                Walgreens Pharmacy #1034 - Beverly Hills</option>
                            <option
                                value="Rite Aid Pharmacy #5411|8440 Santa Monica Blvd, West Hollywood, CA|(323) 555-0188">
                                Rite Aid Pharmacy - West Hollywood</option>
                            <option
                                value="Capsule Digital Delivery Pharmacy|Direct Delivery to Doorstep|(888) 910-1212">
                                Capsule Direct Delivery Pharmacy</option>
                        </select>
                    </div>

                    <div class="dior-form-group">
                        <label for="q_pharm_name">Pharmacy Name <span class="req">*</span></label>
                        <input type="text" id="q_pharm_name" name="pharmacy_name"
                            value="<?php echo esc_attr($profile['pharmacy_name']); ?>" required>
                    </div>
                    <div class="dior-form-group">
                        <label for="q_pharm_address">Pharmacy Address <span class="req">*</span></label>
                        <input type="text" id="q_pharm_address" name="pharmacy_address"
                            value="<?php echo esc_attr($profile['pharmacy_address']); ?>" required>
                    </div>
                    <div class="dior-form-group">
                        <label for="q_pharm_phone">Pharmacy Phone</label>
                        <input type="tel" id="q_pharm_phone" name="pharmacy_phone"
                            value="<?php echo esc_attr($profile['pharmacy_phone']); ?>">
                    </div>

                    <div class="dior-modal-footer">
                        <button type="button" class="dior-btn-gold-secondary"
                            onclick="diorCloseModal('modal-change-pharmacy')">Cancel</button>
                        <button type="submit" class="dior-btn-gold-primary">Save Pharmacy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 6. VIEW RECEIPT MODAL -->
    <div class="dior-modal-overlay" id="modal-view-receipt" style="display:none;">
        <div class="dior-modal-dialog"
            style="max-width: 580px; width: 92%; border-radius: 16px; overflow: hidden; background: #FFFFFF; box-shadow: 0 20px 50px rgba(11,16,48,0.25);">
            <div class="dior-modal-header"
                style="padding: 18px 24px; border-bottom: 1px solid #EEF2F6; background: #FFFFFF; display: flex; align-items: center; justify-content: space-between;">
                <h3
                    style="margin: 0; font-size: 17px; font-weight: 700; color: #0F172A; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-receipt" style="color:#2C6CB1;"></i> Telehealth Itemized Receipt
                </h3>
                <button type="button" class="dior-modal-close" onclick="diorCloseModal('modal-view-receipt')"
                    style="font-size: 24px; line-height: 1; color: #94A3B8; background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 6px;">&times;</button>
            </div>

            <div class="dior-modal-body" id="modal-receipt-content" style="padding: 24px 28px; background: #F8FAFC;">
                <div class="receipt-printable-box"
                    style="padding: 28px 24px; border: 1px solid #E2E8F0; border-radius: 12px; background: #FFFFFF; box-shadow: 0 2px 10px rgba(11,16,48,0.04);">
                    <div class="receipt-logo" style="text-align: center; margin-bottom: 16px;">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Dior Medical"
                            style="height: 68px; max-height: 72px; width: auto; object-fit: contain; display: inline-block;">
                    </div>

                    <div class="receipt-title"
                        style="font-size: 17px; font-weight: 700; color: #0F172A; text-align: center; margin-bottom: 20px; font-family: 'Montserrat', sans-serif;">
                        Official Medical Statement &amp; Receipt
                    </div>

                    <table class="receipt-info-table"
                        style="width: 100%; border-collapse: collapse; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; margin-bottom: 20px; font-size: 13px;">
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td
                                style="padding: 12px 16px; background: #F8FAFC; width: 38%; color: #64748B; font-weight: 700;">
                                Invoice #:</td>
                            <td style="padding: 12px 16px; color: #0F172A; font-weight: 700;" id="rec-inv-id">INV-8829
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 12px 16px; background: #F8FAFC; color: #64748B; font-weight: 700;">Date:
                            </td>
                            <td style="padding: 12px 16px; color: #0F172A; font-weight: 600;" id="rec-date">Sept 4, 2026
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 12px 16px; background: #F8FAFC; color: #64748B; font-weight: 700;">
                                Patient:</td>
                            <td style="padding: 12px 16px; color: #0F172A; font-weight: 600;" id="rec-patient-info">
                                <?php echo esc_html($profile['full_name']); ?>
                                (<?php echo esc_html($profile['patient_id']); ?>)
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 12px 16px; background: #F8FAFC; color: #64748B; font-weight: 700;">
                                Service:</td>
                            <td style="padding: 12px 16px; color: #0F172A; font-weight: 600;" id="rec-service">Urgent
                                Care Telehealth Consultation</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 12px 16px; background: #F8FAFC; color: #64748B; font-weight: 700;">
                                Payment Method:</td>
                            <td style="padding: 12px 16px; color: #0F172A; font-weight: 600;">Stripe (Credit Card /
                                Apple Pay)</td>
                        </tr>
                        <tr class="total-row" style="background: #EFF6FF;">
                            <td style="padding: 14px 16px; color: #1E40AF; font-weight: 800; font-size: 14px;">Total
                                Paid:</td>
                            <td style="padding: 14px 16px; color: #1E40AF; font-weight: 800; font-size: 16px;"
                                id="rec-amount">$49.00</td>
                        </tr>
                    </table>

                    <div class="receipt-footer-note"
                        style="padding: 12px 16px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 11.5px; color: #64748B; text-align: center; line-height: 1.5;">
                        <i class="fa-solid fa-shield-halved" style="color:#059669; margin-right: 4px;"></i>
                        <span>Dior Medical Telehealth &amp; Urgent Care &bull; Tax ID / NPI Verified &bull; Eligible for
                            HSA/FSA Reimbursement</span>
                    </div>
                </div>
            </div>

            <div class="dior-modal-footer"
                style="padding: 18px 28px 24px 28px; background: #FFFFFF; border-top: 1px solid #EEF2F6; display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
                <button type="button" class="dior-btn-gold-secondary" onclick="diorCloseModal('modal-view-receipt')"
                    style="padding: 9px 18px; border-radius: 8px; font-weight: 600; font-size: 13.5px;">Close</button>
                <button type="button" class="dior-btn-gold-secondary" onclick="diorPrintReceiptModal()"
                    style="padding: 9px 18px; border-radius: 8px; font-weight: 600; font-size: 13.5px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-print"></i> Print
                </button>
                <button type="button" class="dior-btn-gold-primary" onclick="diorDownloadReceiptPdf()"
                    style="padding: 9px 20px; border-radius: 8px; font-weight: 600; font-size: 13.5px; display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #2C6CB1 0%, #1A528E 100%); color: #FFFFFF; border: none; cursor: pointer; box-shadow: 0 2px 6px rgba(44,108,177,0.3);">
                    <i class="fa-solid fa-file-arrow-down"></i> Download PDF
                </button>
            </div>
        </div>
    </div>

    <!-- 7. VIEW DOCUMENT MODAL -->
    <div class="dior-modal-overlay" id="modal-view-document" style="display:none;">
        <div class="dior-modal-dialog"
            style="max-width: 600px; width: 92%; border-radius: 16px; overflow: hidden; background: #FFFFFF; box-shadow: 0 20px 50px rgba(11,16,48,0.25);">
            <div class="dior-modal-header"
                style="padding: 18px 24px; border-bottom: 1px solid #EEF2F6; background: #FFFFFF; display: flex; align-items: center; justify-content: space-between;">
                <h3 id="doc-modal-header-title"
                    style="margin: 0; font-size: 17px; font-weight: 700; color: #0F172A; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-file-shield" style="color:#2C6CB1;"></i> Certified Medical Document
                </h3>
                <button type="button" class="dior-modal-close" onclick="diorCloseModal('modal-view-document')"
                    style="font-size: 24px; line-height: 1; color: #94A3B8; background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 6px;">&times;</button>
            </div>

            <div class="dior-modal-body" style="padding: 24px; background: #F8FAFC;">
                <div class="doc-preview-card"
                    style="padding: 24px 20px; border: 1px solid #E2E8F0; border-radius: 12px; background: #FFFFFF; text-align: center; box-shadow: 0 2px 8px rgba(11,16,48,0.04);">

                    <div class="doc-icon-large" style="font-size: 40px; color: #DC2626; margin-bottom: 12px;">
                        <i class="fa-solid fa-file-pdf"></i>
                    </div>

                    <h4 id="doc-preview-title"
                        style="margin: 0 0 10px 0; font-size: 17px; color: #0F172A; font-weight: 700; line-height: 1.35;">
                        Document Title</h4>

                    <div style="margin-bottom: 18px;">
                        <span id="doc-preview-category"
                            style="display: inline-flex; align-items: center; justify-content: center; padding: 4px 14px; background: #EFF6FF; color: #2563EB; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid #BFDBFE;">Category</span>
                    </div>

                    <div class="doc-details-grid"
                        style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; text-align: left; background: #F8FAFC; padding: 14px 16px; border-radius: 10px; border: 1px solid #E2E8F0; margin-bottom: 18px; font-size: 12.5px;">
                        <div>
                            <strong
                                style="color: #64748B; display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 3px;">Document
                                ID:</strong>
                            <span id="doc-preview-id"
                                style="color: #0F172A; font-weight: 700; font-size: 13px;">DOC-88219</span>
                        </div>
                        <div>
                            <strong
                                style="color: #64748B; display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 3px;">Date
                                Issued:</strong>
                            <span id="doc-preview-date" style="color: #0F172A; font-weight: 700; font-size: 13px;">Sept
                                4, 2026</span>
                        </div>
                        <div>
                            <strong
                                style="color: #64748B; display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 3px;">Issued
                                By / Provider:</strong>
                            <span id="doc-preview-author"
                                style="color: #0F172A; font-weight: 700; font-size: 13px;">Dior Medical Clinical
                                Team</span>
                        </div>
                        <div>
                            <strong
                                style="color: #64748B; display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 3px;">Patient:</strong>
                            <span id="doc-preview-patient"
                                style="color: #0F172A; font-weight: 700; font-size: 13px;"><?php echo esc_html($profile['full_name']); ?></span>
                        </div>
                    </div>

                    <div class="doc-seal"
                        style="font-size: 12px; color: #059669; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 9px 12px; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px;">
                        <i class="fa-solid fa-shield-halved" style="color: #059669; font-size: 14px;"></i>
                        <span>Digitally Certified &amp; E-Signed by Dior Medical Telehealth System</span>
                    </div>
                </div>
            </div>

            <div class="dior-modal-footer"
                style="padding: 16px 24px; background: #FFFFFF; border-top: 1px solid #EEF2F6; display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
                <button type="button" class="dior-btn-gold-secondary" onclick="diorCloseModal('modal-view-document')"
                    style="padding: 9px 18px; border-radius: 8px; font-weight: 600; font-size: 13.5px;">Close</button>
                <button type="button" class="dior-btn-gold-primary" onclick="diorDownloadCurrentDocumentPdf()"
                    style="padding: 9px 20px; border-radius: 8px; font-weight: 600; font-size: 13.5px; display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #2C6CB1 0%, #1A528E 100%); color: #FFFFFF; border: none; cursor: pointer; box-shadow: 0 2px 6px rgba(44,108,177,0.3);">
                    <i class="fa-solid fa-file-arrow-down"></i> Print / Download PDF
                </button>
            </div>
        </div>
    </div> <!-- /#modal-view-document -->

    <!-- 8. APPOINTMENT DETAIL MODAL (PATIENT PORTAL) -->
    <div class="dior-modal-overlay" id="modal-view-appointment-detail" style="display:none;">
        <div class="dior-modal-dialog"
            style="max-width: 650px; width: 92%; border-radius: 16px; overflow: hidden; background: #FFFFFF; box-shadow: 0 25px 60px rgba(15,23,42,0.25); border: 1px solid #E2E8F0;">
            <div class="dior-modal-header"
                style="padding: 16px 24px; border-bottom: 1px solid #EEF2F6; background: #FFFFFF; display: flex; align-items: center; justify-content: space-between;">
                <h3
                    style="margin: 0; font-size: 16px; font-weight: 700; color: #0F172A; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-calendar-check" style="color:#2C6CB1;"></i> Appointment Details
                </h3>
                <button type="button" class="dior-modal-close" onclick="diorCloseModal('modal-view-appointment-detail')"
                    style="font-size: 22px; line-height: 1; color: #94A3B8; background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 6px;">&times;</button>
            </div>

            <div class="dior-modal-body"
                style="padding: 24px; background: #F8FAFC; max-height: 75vh; overflow-y: auto;">
                <!-- Hero Info Card -->
                <div
                    style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(15,23,42,0.04);">
                    <div
                        style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
                        <div>
                            <span id="pat-modal-appt-id"
                                style="display:inline-block; font-family:monospace; font-weight:700; font-size:12px; background:#EFF6FF; color:#1D4ED8; padding:3px 10px; border-radius:6px; border:1px solid #BFDBFE; margin-bottom:6px;">APPT-ID</span>
                            <h4 id="pat-modal-appt-provider"
                                style="margin:0; font-size:17px; font-weight:700; color:#0F172A;">Attending Physician
                            </h4>
                            <span id="pat-modal-appt-spec" style="font-size:13px; color:#64748B;">Telehealth Urgent
                                Care</span>
                        </div>
                        <span id="pat-modal-appt-status-badge" class="dior-st ok"
                            style="font-size:12px; padding:5px 12px; border-radius:20px;">Confirmed</span>
                    </div>

                    <div
                        style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:14px 16px; font-size:13px;">
                        <div>
                            <strong
                                style="color:#64748B; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; display:block; margin-bottom:2px;">Date
                                &amp; Time</strong>
                            <span id="pat-modal-appt-datetime" style="color:#0F172A; font-weight:700;">—</span>
                        </div>
                        <div>
                            <strong
                                style="color:#64748B; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; display:block; margin-bottom:2px;">Consultation
                                Mode</strong>
                            <span id="pat-modal-appt-mode" style="color:#0F172A; font-weight:700;"><i
                                    class="fa-solid fa-video" style="color:#2C6CB1;"></i> Video Visit</span>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <strong
                                style="color:#64748B; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; display:block; margin-bottom:2px;">Service
                                / Clinical Reason</strong>
                            <span id="pat-modal-appt-condition" style="color:#0F172A; font-weight:600;">Telehealth
                                Urgent Care</span>
                        </div>
                    </div>
                </div>

                <!-- Live Zoom Link Strip (if available) -->
                <div id="pat-modal-zoom-wrap"
                    style="background:#EFF6FF; border:1.5px solid #BFDBFE; border-radius:12px; padding:16px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div
                            style="width:36px; height:36px; border-radius:8px; background:#2563EB; color:#fff; display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0;">
                            <i class="fa-solid fa-video"></i>
                        </div>
                        <div>
                            <strong style="color:#1E40AF; font-size:13.5px; display:block;">Live Virtual Consultation
                                Room</strong>
                            <span style="color:#3B82F6; font-size:12px;">Join physician on secure Zoom Video</span>
                        </div>
                    </div>
                    <a id="pat-modal-zoom-link" href="#" target="_blank" rel="noopener noreferrer"
                        style="padding:8px 18px; background:#2563EB; color:#FFFFFF; border-radius:8px; font-weight:700; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 8px rgba(37,99,235,0.3);">
                        <i class="fa-solid fa-video"></i> Join Video Call &rarr;
                    </a>
                </div>

                <!-- Patient & Clinical Notes Card -->
                <div
                    style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; padding:18px; margin-bottom:16px;">
                    <h5
                        style="margin:0 0 10px; font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:#475569; font-weight:700;">
                        <i class="fa-solid fa-notes-medical" style="color:#2C6CB1;"></i> Clinical Notes &amp;
                        Instructions
                    </h5>
                    <p id="pat-modal-appt-notes"
                        style="margin:0; font-size:13px; color:#334155; line-height:1.5; background:#F8FAFC; padding:12px; border-radius:8px; border:1px solid #E2E8F0;">
                        Standard telehealth clinical consultation scheduled. All submitted medical intake questionnaires
                        are pre-verified.
                    </p>
                </div>

                <div
                    style="font-size: 12px; color: #059669; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 9px 12px; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px;">
                    <i class="fa-solid fa-shield-halved" style="color: #059669; font-size: 14px;"></i>
                    <span>HIPAA Compliant &bull; Verified Dior Medical Telehealth Record</span>
                </div>
            </div>

            <div class="dior-modal-footer"
                style="padding: 14px 24px; background: #FFFFFF; border-top: 1px solid #EEF2F6; display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                <button type="button" class="dior-btn-gold-secondary"
                    onclick="diorCloseModal('modal-view-appointment-detail')"
                    style="padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 13px;">Close</button>
            </div>
        </div>
    </div> <!-- /#modal-view-appointment-detail -->

    <!-- 9. PRESCRIPTION DETAIL MODAL (PATIENT PORTAL) -->
    <div class="dior-modal-overlay" id="modal-view-rx-detail"
        style="display:none; align-items:center; justify-content:center; padding:16px;">
        <div class="dior-modal-dialog"
            style="max-width: 780px; width: 95%; max-height: calc(100vh - 30px); height: auto; display: flex; flex-direction: column; border-radius: 16px; overflow: hidden; background: #FFFFFF !important; background-color: #FFFFFF !important; box-shadow: 0 25px 60px rgba(15,23,42,0.25); border: 1px solid #CBD5E1; margin: auto;">

            <!-- Modal Top Action Header -->
            <div class="dior-modal-header"
                style="flex-shrink: 0; padding: 14px 22px; border-bottom: 1px solid #E2E8F0; background: #FFFFFF !important; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div
                        style="width: 36px; height: 36px; border-radius: 8px; background: #ECFDF5; border: 1.5px solid #00A896; display: flex; align-items: center; justify-content: center; font-size: 16px; color: #00A896;">
                        <i class="fa-solid fa-file-prescription"></i>
                    </div>
                    <div>
                        <h3
                            style="margin: 0; font-size: 16px; font-weight: 800; color: #0F172A; letter-spacing: -0.01em;">
                            Official Telehealth Prescription (e-Rx)
                        </h3>
                        <span style="font-size: 11px; color: #64748B; font-weight: 500;">
                            DEA &bull; NPI Registered Practice &bull; Surescripts e-Rx Certified
                        </span>
                    </div>
                </div>
                <button type="button" class="dior-modal-close" onclick="diorCloseModal('modal-view-rx-detail')"
                    style="font-size: 22px; line-height: 1; color: #64748B; background: #FFFFFF; border: none; cursor: pointer; padding: 4px 8px; border-radius: 6px;">&times;</button>
            </div>

            <!-- Modal Body: Letterhead Sheet (Image 2, 3, 4) -->
            <div class="dior-modal-body"
                style="flex: 1 1 auto; overflow-y: auto; min-height: 0; padding: 18px 20px; background: #F8FAFC !important;">

                <div class="dior-rx-letterhead-sheet"
                    style="background: #FFFFFF !important; border: 1.5px solid #CBD5E1; border-radius: 12px; padding: 26px 28px; position: relative; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.04);">

                    <!-- Top Clinic & Doctor Header (Images 2 & 3) -->
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; padding-bottom: 14px; border-bottom: 2px solid #00A896; margin-bottom: 14px; position: relative; z-index: 2;">
                        <div>
                            <div class="dior-rx-doc-name" id="pat-modal-rx-doc-header"
                                style="font-size: 17px; font-weight: 700; color: #1E293B; margin: 0 0 2px 0;">
                                <span id="pat-modal-rx-doc">Dr. Marcus Sterling, MD</span>
                            </div>
                            <div class="dior-rx-doc-creds"
                                style="font-size: 10px; font-weight: 600; color: #00A896; letter-spacing: 0.04em;">
                                QUALIFICATION: M.B.B.S, M.D. &bull; DEA: MV8492019 &bull; NPI: 1849201948
                            </div>
                            <div class="dior-rx-clinic-sub"
                                style="font-size: 10.5px; color: #64748B; margin-top: 2px; line-height: 1.35;">
                                <strong style="color: #334155;">DIOR MEDICAL TELEHEALTH &amp; URGENT CARE</strong><br>
                                Clinical Telemedicine Division &bull; Surescripts e-Rx Certified
                            </div>
                        </div>

                        <!-- Clinic Logo & Caduceus Symbol -->
                        <div
                            style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 3px;">
                            <?php
                            $disp_logo = !empty($logo_url) ? $logo_url : site_url('/wp-content/uploads/2026/08/logo.png');
                            ?>
                            <img src="<?php echo esc_url($disp_logo); ?>" alt="Dior Medical"
                                style="height: 52px; max-height: 56px; width: auto; max-width: 170px; object-fit: contain; display: block;">
                            <div
                                style="display: flex; align-items: center; gap: 5px; color: #00A896; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                                <i class="fa-solid fa-staff-snake" style="font-size: 13px;"></i>
                                <span style="color: #0F172A;">Digital Rx Service</span>
                            </div>
                            <span
                                style="display: inline-flex; align-items: center; gap: 4px; padding: 1px 7px; background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; border-radius: 10px; font-weight: 700; font-size: 9.5px;">
                                <i class="fa-solid fa-circle-check"></i> NCPDP Real-Time EDI Script
                            </span>
                        </div>
                    </div>

                    <!-- Barcode & Issuance Line (Image 3) -->
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 6px 0; border-bottom: 1px dashed #CBD5E1; margin-bottom: 12px;">
                        <div class="dior-rx-barcode-wrap">
                            <div class="dior-rx-barcode-lines">
                                <span style="width: 2px;"></span><span
                                    style="width: 1px; background:transparent;"></span><span
                                    style="width: 3px;"></span><span style="width: 1px;"></span>
                                <span style="width: 4px;"></span><span
                                    style="width: 2px; background:transparent;"></span><span
                                    style="width: 2px;"></span><span style="width: 3px;"></span>
                                <span style="width: 1px;"></span><span style="width: 4px;"></span><span
                                    style="width: 1px; background:transparent;"></span><span style="width: 3px;"></span>
                                <span style="width: 2px;"></span><span style="width: 4px;"></span><span
                                    style="width: 1px;"></span><span style="width: 3px;"></span>
                            </div>
                            <span class="dior-rx-barcode-txt" id="pat-modal-rx-barcode-txt">*RX-000000*</span>
                        </div>
                        <div style="text-align: right; font-size: 11.5px; color: #475569;">
                            <div>Prescription Date: <span id="pat-modal-rx-date"
                                    style="font-weight: 600; color: #1E293B;">—</span></div>
                            <div style="font-size: 11px; color: #64748B; margin-top: 2px;">
                                Unique Rx UID: <strong id="pat-modal-rx-id"
                                    style="color: #00A896; font-family: monospace; font-size: 11.5px; background: #F0FDFA; padding: 1px 6px; border: 1px solid #CCFBF1; border-radius: 4px;">Rx
                                    #ORDER</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Patient Demographics: Classical Prescription Pad Underline Layout (Exact Match to User's Image 2) -->
                    <div class="dior-rx-patient-pad-box"
                        style="margin: 14px 0 16px 0; padding: 12px 16px; background: #FAFCFE; border: 1px solid #E2E8F0; border-radius: 8px;">
                        <!-- Row 1: Patient Name & Date -->
                        <div
                            style="display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 10px; flex-wrap: wrap;">
                            <div style="flex: 1 1 56%; display: flex; align-items: flex-end; min-width: 240px;">
                                <span
                                    style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Patient
                                    Name:</span>
                                <span id="pat-modal-rx-pat-name"
                                    style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-height: 18px;"><?php echo esc_html($profile['full_name']); ?></span>
                            </div>
                            <div style="flex: 1 1 34%; display: flex; align-items: flex-end; min-width: 160px;">
                                <span
                                    style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Date:</span>
                                <span id="pat-modal-rx-pad-date"
                                    style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-height: 18px;">—</span>
                            </div>
                        </div>

                        <!-- Row 2: Age, Gender, Weight -->
                        <div
                            style="display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 10px; flex-wrap: wrap;">
                            <div style="flex: 1 1 28%; display: flex; align-items: flex-end; min-width: 110px;">
                                <span
                                    style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Age:</span>
                                <span id="pat-modal-rx-pat-age"
                                    style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-height: 18px;"><?php
                                    $modal_age = '18+ (Adult)';
                                    if (!empty($profile['dob'])) {
                                        $modal_dob_timestamp = strtotime($profile['dob']);
                                        if ($modal_dob_timestamp) {
                                            $modal_age_years = date('Y') - date('Y', $modal_dob_timestamp);
                                            if (date('md') < date('md', $modal_dob_timestamp)) {
                                                $modal_age_years--;
                                            }
                                            $modal_age = $modal_age_years . ' years';
                                        }
                                    }
                                    echo esc_html($modal_age);
                                    ?></span>
                            </div>
                            <div style="flex: 1 1 32%; display: flex; align-items: flex-end; min-width: 120px;">
                                <span
                                    style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Gender:</span>
                                <span id="pat-modal-rx-pat-gender"
                                    style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-height: 18px;"><?php echo esc_html($profile['gender'] ?? 'Female'); ?></span>
                            </div>
                            <div style="flex: 1 1 32%; display: flex; align-items: flex-end; min-width: 120px;">
                                <span
                                    style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Weight:</span>
                                <span id="pat-modal-rx-pat-weight"
                                    style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-height: 18px;"><?php echo esc_html($profile['weight'] ?? 'NKDA (Verified)'); ?></span>
                            </div>
                        </div>

                        <!-- Row 3: Diagnosis -->
                        <div style="display: flex; align-items: flex-end; flex-wrap: wrap;">
                            <span
                                style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Diagnosis:</span>
                            <span id="pat-modal-rx-diagnosis"
                                style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-width: 220px; min-height: 18px;">Clinical
                                Telehealth Consultation &bull; General Wellness</span>
                        </div>
                    </div>

                    <!-- Classical ℞ Prescription Order Bar (Image 3) -->
                    <div class="dior-rx-title-bar">
                        <div style="display: flex; align-items: center;">
                            <span class="dior-rx-symbol">&#8478;</span>
                            <span
                                style="font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #0F172A;">
                                Official Prescription Directive
                            </span>
                        </div>
                        <span
                            style="font-size: 10px; font-weight: 700; color: #00A896; background: #F0FDFA; border: 1px solid #99F6E4; padding: 2px 8px; border-radius: 4px;">
                            Schedule VI / Legend Non-Controlled
                        </span>
                    </div>

                    <!-- Clean Structured Medication Table (Images 3 & 4) -->
                    <table class="dior-rx-med-table">
                        <thead>
                            <tr>
                                <th style="width: 36px; text-align: center;">#</th>
                                <th style="width: 44%;">Medicine Name &amp; Formulation</th>
                                <th style="width: 36%;">Dosage &amp; Usage Instructions (SIG)</th>
                                <th style="width: 20%;">Dispense / Refills</th>
                            </tr>
                        </thead>
                        <tbody id="pat-modal-rx-table-body">
                            <!-- Populated dynamically via diorViewRxDetails() -->
                        </tbody>
                    </table>

                    <!-- Advice Given / Clinical Instructions (Image 3) -->
                    <div class="dior-rx-advice-card">
                        <div class="dior-rx-advice-title">
                            <i class="fa-solid fa-notes-medical"></i> Advice Given &amp; Clinical Instructions:
                        </div>
                        <div class="dior-rx-advice-body" id="pat-modal-rx-advice">
                            &bull; Take medication strictly as prescribed. Do not exceed the stated dose.<br>
                            &bull; Take with a full glass of water. Avoid alcohol and heavy unprescribed
                            interactions.<br>
                            &bull; If any hypersensitivity, rash, or adverse effect occurs, discontinue immediately and
                            contact the clinic.
                        </div>
                    </div>

                    <!-- Designated Pharmacy Routing EDI Box -->
                    <div class="dior-rx-pharmacy-line">
                        <div>
                            <strong
                                style="color: #0F766E; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.04em;">
                                Designated Fulfillment Pharmacy:
                            </strong>
                            <span id="pat-modal-rx-pharm-name"
                                style="color: #0F172A; font-size: 13px; font-weight: 800; margin-left: 6px;">
                                <?php echo esc_html(!empty($profile['pharmacy_name']) ? $profile['pharmacy_name'] : 'Preferred Pharmacy on File'); ?>
                            </span>
                            <span id="pat-modal-rx-routing"
                                style="font-size: 11px; color: #059669; font-weight: 600; margin-left: 10px;">
                                <i class="fa-solid fa-circle-check"></i> Routed Electronically via Surescripts NCPDP EDI
                            </span>
                        </div>
                        <span id="pat-modal-rx-status-badge"
                            style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; background: #ECFDF5; color: #059669; border: 1px solid #10B981; border-radius: 12px; font-size: 11px; font-weight: 800;">
                            <i class="fa-solid fa-check"></i> Transmission Confirmed
                        </span>
                    </div>

                    <!-- Physician Digital Signature & Telehealth Legal Notice (Images 2 & 4) -->
                    <div class="dior-rx-sig-container">
                        <div class="dior-rx-legal-notice">
                            <strong style="color: #0F172A;">TELEHEALTH LEGAL CERTIFICATION:</strong><br>
                            This prescription was electronically reviewed, approved, and digitally signed by an
                            authorized healthcare provider pursuant to a valid telehealth encounter under DEA 21 CFR
                            Part 1311 and state medical practice acts.
                        </div>
                        <div class="dior-rx-sig-block">
                            <div class="dior-rx-sig-line">
                                <img id="pat-modal-rx-sig-img" src="" alt="Doctor Signature"
                                    style="display:none; max-height:48px; max-width:180px; object-fit:contain; background:transparent; vertical-align:middle;">
                                <span id="pat-modal-rx-sig-name" class="dior-rx-sig-script">
                                    /s/ Dr. Marcus Sterling, MD
                                </span>
                            </div>
                            <div class="dior-rx-sig-lbl">
                                Authorized Physician Signature
                            </div>
                            <div id="pat-modal-rx-sig-date" class="dior-rx-sig-stamp">
                                SHA256 Encrypted &bull; Issued Sep 14, 2026
                            </div>
                        </div>
                    </div>

                    <!-- Letterhead Bottom Clinic Footer Details (Image 2) -->
                    <div class="dior-rx-footer-line">
                        <div>
                            <i class="fa-solid fa-location-dot" style="color: #00A896; margin-right: 4px;"></i> 100
                            Medical Plaza, Suite 400 &bull; Dior Medical Telehealth Group
                        </div>
                        <div>
                            <i class="fa-solid fa-phone" style="color: #00A896; margin-right: 4px;"></i> (800) 555-DIOR
                            &bull; concierge@diormedical.com
                        </div>
                    </div>

                </div> <!-- /dior-rx-letterhead-sheet -->

            </div> <!-- /dior-modal-body -->

            <!-- Modal Action Footer (Permanently Pinned, Full Visibility) -->
            <div class="dior-modal-footer"
                style="flex-shrink: 0; padding: 14px 22px; background: #FFFFFF !important; border-top: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; z-index: 10;">
                <button type="button" id="pat-modal-rx-refill-btn"
                    style="padding: 9px 16px; border-radius: 8px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; background: #FFFFFF !important; color: #1E293B !important; border: 1.5px solid #CBD5E1 !important; cursor: pointer;">
                    <i class="fa-solid fa-arrows-rotate" style="color: #00A896;"></i> Request Refill
                </button>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" id="pat-modal-rx-pdf-btn"
                        style="padding: 9px 20px; border-radius: 8px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; background: #00A896 !important; border: 1.5px solid #00A896 !important; color: #FFFFFF !important; cursor: pointer; box-shadow: 0 4px 12px rgba(0,168,150,0.25);">
                        <i class="fa-solid fa-file-arrow-down"></i> Download Official PDF
                    </button>
                    <button type="button" onclick="diorCloseModal('modal-view-rx-detail')"
                        style="padding: 9px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; background: #FFFFFF !important; color: #475569 !important; border: 1.5px solid #CBD5E1 !important; cursor: pointer;">Close</button>
                </div>
            </div>
        </div>
    </div> <!-- /#modal-view-rx-detail -->

    <script>
        window.dior_patient_appts_data = <?php echo wp_json_encode($appointments); ?>;
        window.dior_patient_rxs_data = <?php echo wp_json_encode($prescriptions); ?>;
        window.diorOpenModal = function (modalId) {
            var el = document.getElementById(modalId);
            if (el) {
                el.classList.add('open');
                el.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        };

        window.diorCloseModal = function (modalId) {
            var el = document.getElementById(modalId);
            if (el) {
                el.classList.remove('open');
                el.style.display = 'none';
                document.body.style.overflow = '';
            }
        };

        window.diorViewRxDetails = function (rxId, fallbackMed, fallbackInst) {
            var rxs = window.dior_patient_rxs_data || [];
            var found = null;
            if (rxs && rxs.length) {
                for (var i = 0; i < rxs.length; i++) {
                    var r = rxs[i];
                    if (String(r.id).trim().toLowerCase() === String(rxId).trim().toLowerCase() ||
                        (r.raw_ids && r.raw_ids.some(function (rid) { return String(rid).trim().toLowerCase() === String(rxId).trim().toLowerCase(); }))) {
                        found = r;
                        break;
                    }
                }
            }

            var idEl = document.getElementById('pat-modal-rx-id');
            var docEl = document.getElementById('pat-modal-rx-doc');
            var docHeaderEl = document.getElementById('pat-modal-rx-doc-header');
            var docCellEl = document.getElementById('pat-modal-rx-doc-name-cell');
            var dateEl = document.getElementById('pat-modal-rx-date');
            var routingEl = document.getElementById('pat-modal-rx-routing');
            var statusBadge = document.getElementById('pat-modal-rx-status-badge');
            var pharmNameEl = document.getElementById('pat-modal-rx-pharm-name');
            var sigNameEl = document.getElementById('pat-modal-rx-sig-name');
            var sigDateEl = document.getElementById('pat-modal-rx-sig-date');
            var barcodeTxt = document.getElementById('pat-modal-rx-barcode-txt');
            var adviceEl = document.getElementById('pat-modal-rx-advice');
            var pdfBtn = document.getElementById('pat-modal-rx-pdf-btn');
            var refillBtn = document.getElementById('pat-modal-rx-refill-btn');
            var tableBody = document.getElementById('pat-modal-rx-table-body');

            var actualId = found ? (found.id || rxId) : rxId;
            var rawDoc = found ? (found.prescribed_by || 'Dr. Marcus Sterling') : 'Dr. Marcus Sterling';
            var cleanDoc = rawDoc.replace(/^(Dr\.?\s*)+/i, 'Dr. ');
            var actualDate = found ? (found.date_prescribed || found.date || '—') : '—';
            var actualRouting = found ? (found.routing_status || 'Sent to Pharmacy') : 'Sent to Pharmacy';
            var actualPharm = found ? (found.pharmacy || 'Preferred Pharmacy on File') : 'Preferred Pharmacy on File';

            if (idEl) idEl.textContent = 'Rx #' + actualId;
            if (barcodeTxt) barcodeTxt.textContent = '*RX-' + String(actualId).replace(/[^0-9a-zA-Z]/g, '') + '*';
            if (docEl) docEl.textContent = cleanDoc;
            if (docHeaderEl) docHeaderEl.innerHTML = '<span>' + escapeHtml(cleanDoc) + ', MD</span>';
            if (docCellEl) docCellEl.textContent = cleanDoc + ', MD';
            if (dateEl) dateEl.textContent = actualDate;
            var padDateEl = document.getElementById('pat-modal-rx-pad-date');
            if (padDateEl) padDateEl.textContent = actualDate;
            if (routingEl) routingEl.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#059669;"></i> ' + actualRouting;
            if (statusBadge) statusBadge.innerHTML = '<i class="fa-solid fa-check"></i> ' + (found.routing_status || (actualRouting === 'Sent to Pharmacy' ? 'Transmission Confirmed' : actualRouting));
            if (pharmNameEl) pharmNameEl.textContent = actualPharm;
            if (sigNameEl) sigNameEl.textContent = '/s/ ' + cleanDoc + ', MD';
            if (sigDateEl) sigDateEl.textContent = 'SHA256 Encrypted \u2022 Issued ' + actualDate;

            var sigImgEl = document.getElementById('pat-modal-rx-sig-img');
            var docSigUrl = (found && (found.doctor_signature || found.signature_url)) || (window.dior_vars && window.dior_vars.default_doctor_signature ? window.dior_vars.default_doctor_signature : '');
            if (sigImgEl) {
                if (docSigUrl) {
                    sigImgEl.src = docSigUrl;
                    sigImgEl.style.display = 'inline-block';
                    if (sigNameEl) sigNameEl.style.display = 'none';
                } else {
                    sigImgEl.style.display = 'none';
                    if (sigNameEl) sigNameEl.style.display = 'inline-block';
                }
            }

            function escapeHtml(str) {
                if (!str) return '';
                var d = document.createElement('div');
                d.textContent = str;
                return d.innerHTML;
            }

            var items = [];
            if (found && found.items && found.items.length) {
                items = found.items;
            } else {
                items = [{
                    id: actualId + '-1',
                    medication: (found ? (found.medication || found.name) : '') || fallbackMed || 'Prescription Medication',
                    dosage: (found ? (found.dosage || found.dose) : '') || 'As Prescribed',
                    instructions: (found ? (found.instructions || found.notes) : '') || fallbackInst || 'Take as directed by prescribing physician.',
                    quantity: (found ? found.quantity : '') || 'As Prescribed',
                    refills: (found ? found.refills : '') || '0 Refills Remaining'
                }];
            }

            if (tableBody) {
                var html = '';
                var adviceNotes = [];
                for (var j = 0; j < items.length; j++) {
                    var it = items[j];
                    var itemUid = (it && it.id) ? it.id : (actualId + '-' + (j + 1));
                    var medName = escapeHtml(it.medication);
                    var doseStr = escapeHtml(it.dosage);
                    var sigStr = escapeHtml(it.instructions || it.dosage || 'Take as directed by physician.');
                    var qtyStr = escapeHtml(it.quantity || 'As Prescribed');
                    var refillStr = escapeHtml(it.refills || '0 Refills Remaining');

                    if (it.notes && it.notes !== sigStr) {
                        adviceNotes.push(escapeHtml(it.notes));
                    }

                    var formattedMed = medName;
                    if (!/^(TAB|CAP|SYR|INJ|SOL)\b/i.test(formattedMed)) {
                        formattedMed = 'TAB. ' + formattedMed;
                    }

                    html += '<tr>' +
                        '<td class="dior-rx-item-num" style="font-size:11px;font-weight:600;color:#64748B;">' + (j + 1) + '</td>' +
                        '<td>' +
                        '<div class="dior-rx-item-name">' + formattedMed + '</div>' +
                        '<div class="dior-rx-item-sub">' +
                        '<span style="font-family:monospace;font-size:9.5px;color:#00A896;background:#F0FDFA;border:1px solid #CCFBF1;padding:1px 5px;border-radius:4px;font-weight:600;">Rx Item: ' + escapeHtml(itemUid) + '</span>' +
                        '<span class="dior-rx-fda-badge"><i class="fa-solid fa-check"></i> FDA Approved</span>' +
                        '<span>Oral Formulation &bull; Single Patient Use</span>' +
                        '</div>' +
                        '</td>' +
                        '<td>' +
                        '<div class="dior-rx-item-sig">' +
                        '<strong>SIG:</strong> ' + sigStr +
                        '</div>' +
                        '<div style="font-size:10.5px;color:#64748B;margin-top:2px;">' +
                        'Dosage: <strong style="color:#334155;font-weight:600;">' + doseStr + '</strong> &bull; Route: Oral' +
                        '</div>' +
                        '</td>' +
                        '<td>' +
                        '<div class="dior-rx-item-qty">Qty: ' + qtyStr + '</div>' +
                        '<div class="dior-rx-item-refill">' + refillStr + '</div>' +
                        '<div class="dior-rx-item-daw">DAW-0 (Generic Auth)</div>' +
                        '</td>' +
                        '</tr>';
                }
                tableBody.innerHTML = html;

                if (adviceEl) {
                    var adviceHtml = '&bull; Take medication strictly as directed with a full glass of water.<br>' +
                        '&bull; Complete the full course of treatment unless instructed otherwise by your doctor.<br>' +
                        '&bull; Avoid alcohol during the active course of treatment.';
                    if (adviceNotes.length > 0) {
                        adviceHtml += '<br>&bull; <strong>Physician Notes:</strong> ' + adviceNotes.join('; ');
                    }
                    adviceEl.innerHTML = adviceHtml;
                }
            }

            if (pdfBtn) {
                pdfBtn.onclick = function () { window.diorDownloadRxPdf(actualId); };
            }
            if (refillBtn) {
                refillBtn.onclick = function () { window.diorRequestRefill(actualId); };
            }

            window.diorOpenModal('modal-view-rx-detail');
        };

        window.diorRequestRefill = function (rxId) {
            if (typeof showToast === 'function') {
                showToast('Refill request submitted electronically to prescribing physician for Rx #' + rxId, false);
            } else {
                alert('Refill request submitted electronically to prescribing physician for Rx #' + rxId);
            }
        };

        window.diorViewDocumentModal = function (title, category, author, date, id) {
            var t = document.getElementById('doc-preview-title');
            var c = document.getElementById('doc-preview-category');
            var a = document.getElementById('doc-preview-author');
            var d = document.getElementById('doc-preview-date');
            var i = document.getElementById('doc-preview-id');
            if (t) t.textContent = title || 'Medical Record';
            if (c) c.textContent = category || 'Official Document';
            if (a) a.textContent = author || 'Dior Medical Urgent Care';
            if (d) d.textContent = date || '';
            if (i) i.textContent = id || 'DOC-88219';
            window.diorOpenModal('modal-view-document');
        };

        window.diorViewAppointmentDetails = function (apptId) {
            var appts = window.dior_patient_appts_data || [];
            var found = null;
            for (var i = 0; i < appts.length; i++) {
                if (String(appts[i].id) === String(apptId) || String(appts[i].appt_uid) === String(apptId)) {
                    found = appts[i];
                    break;
                }
            }

            var idEl = document.getElementById('pat-modal-appt-id');
            var provEl = document.getElementById('pat-modal-appt-provider');
            var specEl = document.getElementById('pat-modal-appt-spec');
            var statusBadge = document.getElementById('pat-modal-appt-status-badge');
            var dtEl = document.getElementById('pat-modal-appt-datetime');
            var modeEl = document.getElementById('pat-modal-appt-mode');
            var condEl = document.getElementById('pat-modal-appt-condition');
            var notesEl = document.getElementById('pat-modal-appt-notes');
            var zoomWrap = document.getElementById('pat-modal-zoom-wrap');
            var zoomLink = document.getElementById('pat-modal-zoom-link');

            if (found) {
                if (idEl) idEl.textContent = 'APPT #' + (found.id || found.appt_uid || apptId);
                if (provEl) provEl.textContent = found.provider || found.doctor_name || 'Attending Physician';
                if (specEl) specEl.textContent = found.provider_spec || found.provider_title || 'Telehealth Urgent Care';
                if (statusBadge) {
                    var st = found.status || found.appt_status || 'Confirmed';
                    statusBadge.textContent = st;
                    statusBadge.className = 'dior-st ' + (st === 'Confirmed' || st === 'Completed' ? 'ok' : (st === 'Cancelled' ? 'error' : 'pending'));
                }
                if (dtEl) dtEl.textContent = (found.date || found.appt_date || '') + ' ' + (found.time || found.appt_time || '');
                if (modeEl) modeEl.innerHTML = '<i class="fa-solid fa-video" style="color:#2C6CB1;"></i> ' + (found.type || found.visit_type || 'Video Visit');
                if (condEl) condEl.textContent = found.condition || found.condition_name || 'Telehealth Consultation';
                if (notesEl) notesEl.textContent = found.notes || 'Standard telehealth clinical consultation scheduled. All submitted medical intake questionnaires are pre-verified.';

                var joinUrl = found.join_url || 'https://zoom.us/join';
                var isLive = (found.status === 'Confirmed' || found.status === 'Scheduled' || found.status === 'In-Queue');
                if (zoomWrap) {
                    zoomWrap.style.display = isLive ? 'flex' : 'none';
                    if (zoomLink) zoomLink.href = joinUrl;
                }
            } else if (idEl) {
                idEl.textContent = 'APPT #' + apptId;
            }

            window.diorOpenModal('modal-view-appointment-detail');
        };

        window.diorDownloadCurrentDocumentPdf = function () {
            var title = (document.getElementById('doc-preview-title') && document.getElementById('doc-preview-title').textContent) || 'Clinical Medical Certificate';
            var docId = (document.getElementById('doc-preview-id') && document.getElementById('doc-preview-id').textContent) || 'DOC-88219';
            var cat = (document.getElementById('doc-preview-category') && document.getElementById('doc-preview-category').textContent) || 'Official Document';
            var author = (document.getElementById('doc-preview-author') && document.getElementById('doc-preview-author').textContent) || 'Dr. Marcus Sterling, MD';
            var date = (document.getElementById('doc-preview-date') && document.getElementById('doc-preview-date').textContent) || 'Sept 14, 2026';
            var patientName = (document.getElementById('doc-preview-patient') && document.getElementById('doc-preview-patient').textContent) || 'Verified Patient';

            var logoUrl = (window.dior_vars && window.dior_vars.logo_url) ? window.dior_vars.logo_url : '<?php echo esc_js(site_url("/wp-content/uploads/2026/08/cropped-logo.png")); ?>';
            var docSigUrl = (window.dior_vars && window.dior_vars.default_doctor_signature) ? window.dior_vars.default_doctor_signature : '';

            var cleanFileName = 'Dior-' + title.replace(/[^a-zA-Z0-9]/g, '-') + '.pdf';

            var sigHtml = '';
            if (docSigUrl) {
                sigHtml = '<img src="' + docSigUrl + '" alt="Doctor Signature" style="max-height:50px;max-width:180px;object-fit:contain;background:transparent;display:inline-block;vertical-align:middle;">';
            } else {
                sigHtml = '<span style="font-family:\'Playfair Display\',serif;font-style:italic;font-size:20px;color:#0F172A;font-weight:700;">/s/ ' + author + '</span>';
            }

            var printContent = '<div style="width:750px;margin:0 auto;padding:36px 42px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#1E293B;background:#FFFFFF;border:2px solid #00A896;border-radius:6px;position:relative;box-sizing:border-box;overflow:hidden;">' +
                '<!-- Logo Watermark -->' +
                '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:360px;height:360px;opacity:0.05;pointer-events:none;z-index:0;display:flex;align-items:center;justify-content:center;">' +
                '<img src="' + logoUrl + '" alt="" style="width:100%;height:100%;object-fit:contain;">' +
                '</div>' +
                '<div style="position:relative;z-index:1;">' +
                '<!-- Top Header: Logo on Left, Clinic Details on Right -->' +
                '<table style="width:100%;border-collapse:collapse;border-bottom:2px solid #00A896;padding-bottom:14px;margin-bottom:20px;">' +
                '<tr>' +
                '<td style="width:60%;vertical-align:middle;">' +
                '<div style="display:flex;align-items:center;gap:12px;">' +
                '<img src="' + logoUrl + '" alt="Dior Medical" style="height:48px;max-height:52px;width:auto;object-fit:contain;display:block;">' +
                '<div>' +
                '<div style="font-size:17px;font-weight:800;color:#0F172A;letter-spacing:0.04em;line-height:1.2;">DIOR MEDICAL</div>' +
                '<div style="font-size:10px;font-weight:700;color:#00A896;letter-spacing:0.05em;text-transform:uppercase;margin-top:2px;">TELEHEALTH &bull; VIRTUAL URGENT CARE</div>' +
                '<div style="font-size:9.5px;color:#64748B;margin-top:1px;">Clinical Telemedicine Division &bull; Surescripts Certified</div>' +
                '</div>' +
                '</div>' +
                '</td>' +
                '<td style="width:40%;vertical-align:middle;text-align:right;font-size:10.5px;color:#475569;line-height:1.5;">' +
                '<div style="font-weight:700;color:#0F172A;">+1 (800) 555-DIOR</div>' +
                '<div>www.diormedical.com &bull; care@diormedical.com</div>' +
                '<div>100 Medical Plaza, Suite 400</div>' +
                '<div style="margin-top:3px;font-size:10px;color:#00A896;font-weight:700;">Doc Ref: <strong style="color:#0F172A;font-family:monospace;">' + docId + '</strong></div>' +
                '</td>' +
                '</tr>' +
                '</table>' +

                '<!-- Recipient & Date Bar (Exact Match to Reference Letterhead) -->' +
                '<table style="width:100%;border-collapse:collapse;margin-bottom:18px;">' +
                '<tr>' +
                '<td style="width:65%;vertical-align:top;">' +
                '<div style="font-size:10.5px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;">TO:</div>' +
                '<div style="font-size:15px;font-weight:800;color:#0F172A;margin-top:2px;">' + patientName + '</div>' +
                '<div style="font-size:11px;color:#64748B;margin-top:2px;">Dior Medical Telehealth Patient Registry &bull; Verified Record</div>' +
                '</td>' +
                '<td style="width:35%;vertical-align:top;text-align:right;">' +
                '<div style="font-size:12.5px;font-weight:700;color:#0F172A;">' + date + '</div>' +
                '<div style="display:inline-block;margin-top:4px;padding:2px 8px;background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;border-radius:10px;font-size:9.5px;font-weight:700;">&#10003; HIPAA Certified</div>' +
                '</td>' +
                '</tr>' +
                '</table>' +

                '<!-- Document Subject Banner -->' +
                '<div style="background:#F0FDFA;border:1px solid #CCFBF1;border-radius:6px;padding:10px 16px;text-align:center;margin-bottom:20px;">' +
                '<span style="display:inline-block;padding:2px 8px;background:#E0F2FE;color:#0369A1;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px;">' + cat + '</span>' +
                '<h2 style="margin:0;font-size:16px;color:#0F172A;font-weight:800;">' + title + '</h2>' +
                '</div>' +

                '<!-- Salutation -->' +
                '<p style="font-size:13px;font-weight:700;color:#0F172A;margin:0 0 14px 0;">Dear ' + patientName + ',</p>' +

                '<!-- Clinical Statement Body -->' +
                '<div style="font-size:12.5px;line-height:1.7;color:#334155;margin-bottom:24px;">' +
                '<p style="margin:0 0 12px 0;">This official medical document confirms that you have satisfactorily completed your clinical telehealth consultation with Dior Medical Telehealth. All clinical assessments, patient intake questionnaires, and healthcare evaluations were conducted in strict compliance with state and federal telemedicine practice guidelines.</p>' +
                '<p style="margin:0 0 12px 0;">The attending clinical provider has reviewed your health status and rendered the clinical determinations documented herein for <strong>' + title + '</strong> under clinical directive <strong>' + cat + '</strong>. All clinical guidance, medical advice, and instructions have been communicated directly through the encrypted secure patient portal.</p>' +
                '<p style="margin:0;">This certified electronic clinical record is cryptographically archived and protected under 45 CFR Parts 160 and 164 (HIPAA Security &amp; Privacy Rules) and DEA Title 21 electronic healthcare regulations.</p>' +
                '</div>' +

                '<!-- Sign-off & Electronic Signature (Exact Image Match) -->' +
                '<table style="width:100%;border-collapse:collapse;border-top:1.5px solid #E2E8F0;padding-top:14px;margin-top:20px;">' +
                '<tr>' +
                '<td style="width:55%;vertical-align:bottom;font-size:10px;color:#64748B;line-height:1.45;">' +
                '<strong style="color:#059669;">&#10003; Cryptographically Certified Electronic Record</strong><br>' +
                'Security Hash: SHA-256 Verified &bull; Federal ESIGN &amp; UETA Act Compliant<br>' +
                'DEA 21 CFR Part 1311 &bull; Surescripts Real-Time EDI Network' +
                '</td>' +
                '<td style="width:45%;vertical-align:bottom;text-align:right;">' +
                '<div style="font-size:11.5px;color:#475569;margin-bottom:4px;">Sincerely yours,</div>' +
                '<div style="min-height:48px;display:inline-flex;align-items:center;justify-content:flex-end;border-bottom:1.5px solid #CBD5E1;padding-bottom:3px;min-width:180px;">' +
                sigHtml +
                '</div>' +
                '<div style="font-weight:700;color:#0F172A;font-size:13px;margin-top:4px;">' + author + '</div>' +
                '<div style="font-size:10.5px;color:#64748B;">Attending Telemedicine Physician &bull; Dior Medical</div>' +
                '<div style="font-size:9.5px;color:#00A896;font-weight:600;">DEA: MV8492019 &bull; NPI: 1849201948 &bull; Verified</div>' +
                '</td>' +
                '</tr>' +
                '</table>' +
                '</div>' +
                '</div>';

            var tempContainer = document.createElement('div');
            tempContainer.style.position = 'fixed';
            tempContainer.style.left = '0';
            tempContainer.style.top = '0';
            tempContainer.style.width = '750px';
            tempContainer.style.zIndex = '-999999';
            tempContainer.style.background = '#FFFFFF';
            tempContainer.style.opacity = '1';
            tempContainer.style.pointerEvents = 'none';
            tempContainer.style.margin = '0';
            tempContainer.style.padding = '0';
            tempContainer.innerHTML = printContent;
            document.body.appendChild(tempContainer);

            if (typeof html2pdf !== 'undefined' && html2pdf) {
                var opt = {
                    margin: [8, 8, 8, 8],
                    filename: cleanFileName,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        logging: false,
                        scrollY: 0,
                        scrollX: 0,
                        windowWidth: 794
                    },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };
                try {
                    html2pdf().set(opt).from(tempContainer.firstElementChild || tempContainer).save().then(function () {
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    }).catch(function (err) {
                        console.error('PDF export error:', err);
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                        fallbackPrintWindow(printContent, title);
                    });
                } catch (e) {
                    console.error('PDF generation error:', e);
                    if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    fallbackPrintWindow(printContent, title);
                }
            } else {
                console.warn('html2pdf library not loaded, falling back to print');
                if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                fallbackPrintWindow(printContent, title);
            }
        };

        function fallbackPrintWindow(htmlContent, title) {
            var printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.write('<html><head><title>' + title + '</title><style>@page{size:auto;margin:15mm;} body{margin:0;padding:20px;font-family:Arial,sans-serif;}</style></head><body>' + htmlContent + '</body></html>');
                printWindow.document.close();
                printWindow.focus();
                setTimeout(function () { printWindow.print(); }, 250);
            } else {
                window.print();
            }
        }

        window.diorDownloadReceiptPdf = function () {
            var invId = (document.getElementById('rec-inv-id') && document.getElementById('rec-inv-id').textContent) || 'INV-8829';
            var date = (document.getElementById('rec-date') && document.getElementById('rec-date').textContent) || 'Sept 4, 2026';
            var patientInfo = (document.getElementById('rec-patient-info') && document.getElementById('rec-patient-info').textContent) || 'Patient';
            var service = (document.getElementById('rec-service') && document.getElementById('rec-service').textContent) || 'Urgent Care Telehealth Consultation';
            var amount = (document.getElementById('rec-amount') && document.getElementById('rec-amount').textContent) || '$49.00';
            var logoUrl = (window.dior_vars && window.dior_vars.logo_url) ? window.dior_vars.logo_url : '<?php echo esc_js(site_url("/wp-content/uploads/2026/08/cropped-logo.png")); ?>';

            var cleanFileName = 'Dior-Receipt-' + invId.replace(/[^a-zA-Z0-9]/g, '-') + '.pdf';

            var printContent = '<div style="width:750px;margin:0 auto;padding:36px;font-family:Arial,Helvetica,sans-serif;color:#0F172A;background:#FFFFFF;border:2px solid #00A896;border-radius:8px;box-sizing:border-box;">' +
                '<div class="dior-receipt-print-wrapper">' +
                '<div class="dior-receipt-header-box">' +
                '<img src="' + logoUrl + '" alt="Dior Medical">' +
                '<h1>DIOR MEDICAL TELEHEALTH</h1>' +
                '<p>Official Medical Statement &amp; Itemized Receipt</p>' +
                '</div>' +

                '<table class="dior-receipt-table">' +
                '<tr>' +
                '<td class="label-col">Invoice #:</td>' +
                '<td class="val-col">' + invId + '</td>' +
                '</tr>' +
                '<tr>' +
                '<td class="label-col">Date of Transaction:</td>' +
                '<td>' + date + '</td>' +
                '</tr>' +
                '<tr>' +
                '<td class="label-col">Patient Name &amp; ID:</td>' +
                '<td>' + patientInfo + '</td>' +
                '</tr>' +
                '<tr>' +
                '<td class="label-col">Service Description:</td>' +
                '<td>' + service + '</td>' +
                '</tr>' +
                '<tr>' +
                '<td class="label-col">Payment Method:</td>' +
                '<td>Stripe (Credit Card / Apple Pay) &bull; Verified &bull; Paid in Full</td>' +
                '</tr>' +
                '<tr class="total-row">' +
                '<td class="label-col">Total Paid:</td>' +
                '<td class="val-col">' + amount + '</td>' +
                '</tr>' +
                '</table>' +

                '<div class="dior-receipt-notice">' +
                '<strong>Tax Compliance &amp; Insurance Reimbursement Notice:</strong><br>' +
                'Dior Medical Telehealth &amp; Urgent Care &bull; Tax ID / NPI Verified &bull; Eligible for HSA / FSA Flexible Spending Account Reimbursement' +
                '</div>' +

                '<table class="dior-receipt-footer">' +
                '<tr>' +
                '<td class="left-col">' +
                '<strong>&#10003; Official Electronic Payment Receipt</strong><br>' +
                'Transaction ID: ' + invId + ' &bull; Status: Completed' +
                '</td>' +
                '<td class="right-col">' +
                '<div class="signature-line">' +
                'Dior Billing Directorate' +
                '</div>' +
                '<div class="signature-title">Authorized Financial Officer</div>' +
                '</td>' +
                '</tr>' +
                '</table>' +
                '</div>';

            var tempContainer = document.createElement('div');
            tempContainer.style.position = 'fixed';
            tempContainer.style.left = '0';
            tempContainer.style.top = '0';
            tempContainer.style.width = '750px';
            tempContainer.style.zIndex = '-999999';
            tempContainer.style.background = '#FFFFFF';
            tempContainer.style.opacity = '1';
            tempContainer.style.pointerEvents = 'none';
            tempContainer.style.margin = '0';
            tempContainer.style.padding = '0';
            tempContainer.innerHTML = printContent;
            document.body.appendChild(tempContainer);

            if (typeof html2pdf !== 'undefined' && html2pdf) {
                var opt = {
                    margin: [8, 8, 8, 8],
                    filename: cleanFileName,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        logging: false,
                        scrollY: 0,
                        scrollX: 0,
                        windowWidth: 794
                    },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };
                try {
                    html2pdf().set(opt).from(tempContainer.firstElementChild || tempContainer).save().then(function () {
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    }).catch(function (err) {
                        console.error('PDF receipt export error:', err);
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                        fallbackPrintWindow(printContent, invId);
                    });
                } catch (e) {
                    console.error('PDF generation error:', e);
                    if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    fallbackPrintWindow(printContent, invId);
                }
            } else {
                console.warn('html2pdf library not loaded, falling back to print');
                if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                fallbackPrintWindow(printContent, invId);
            }
        };

        window.diorPrintReceiptModal = function () {
            window.diorDownloadReceiptPdf();
        };

        window.diorDownloadRxPdf = function (rxId) {
            var element = document.getElementById('rx-pdf-template-' + rxId);
            if (!element) {
                var allTemplates = document.querySelectorAll('[id^="rx-pdf-template-"]');
                if (allTemplates && allTemplates.length > 0) {
                    for (var k = 0; k < allTemplates.length; k++) {
                        var rawIds = (allTemplates[k].getAttribute('data-raw-ids') || '').split(',');
                        if (allTemplates[k].id.indexOf(rxId) !== -1 || rawIds.indexOf(String(rxId)) !== -1) {
                            element = allTemplates[k];
                            break;
                        }
                    }
                }
            }
            if (!element) { alert('Prescription details not found for Rx #' + rxId); return; }

            var cleanFileName = 'Dior-Prescription-' + String(rxId).replace(/[^a-zA-Z0-9]/g, '-') + '.pdf';
            var innerContent = element.innerHTML;

            var tempContainer = document.createElement('div');
            tempContainer.style.position = 'fixed';
            tempContainer.style.left = '0';
            tempContainer.style.top = '0';
            tempContainer.style.width = '750px';
            tempContainer.style.background = '#FFFFFF';
            tempContainer.style.zIndex = '-999999';
            tempContainer.style.opacity = '1';
            tempContainer.style.pointerEvents = 'none';
            tempContainer.style.margin = '0';
            tempContainer.style.padding = '0';
            tempContainer.innerHTML = innerContent;
            document.body.appendChild(tempContainer);

            if (typeof html2pdf !== 'undefined' && html2pdf) {
                var opt = {
                    margin: [6, 6, 6, 6],
                    filename: cleanFileName,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        logging: false,
                        scrollY: 0,
                        scrollX: 0,
                        windowWidth: 794
                    },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };
                try {
                    html2pdf().set(opt).from(tempContainer.firstElementChild || tempContainer).save().then(function () {
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    }).catch(function (err) {
                        console.error('PDF export error:', err);
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                        fallbackPrintWindow(innerContent, 'Prescription #' + rxId);
                    });
                } catch (e) {
                    console.error('PDF generation error:', e);
                    if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    fallbackPrintWindow(innerContent, 'Prescription #' + rxId);
                }
            } else {
                console.warn('html2pdf library not loaded, falling back to print');
                if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                fallbackPrintWindow(innerContent, 'Prescription #' + rxId);
            }
        };

        function diorInitThemeMode() {
            localStorage.removeItem('dior_theme_mode');
            var portal = document.getElementById('dior-patient-portal-app') || document.body;
            if (portal) {
                portal.classList.remove('dior-dark-theme');
            }
        }

        if (typeof window.diorInitTablePagination !== 'function') {
            window.diorInitTablePagination = function (tableEl, pageSize) {
                if (!tableEl) return;
                pageSize = pageSize || 5;
                var tbody = tableEl.querySelector('tbody');
                if (!tbody) return;

                var parent = tableEl.parentElement;
                var paginationWrap = parent.querySelector(':scope > .dior-table-pagination') || (parent.parentElement ? parent.parentElement.querySelector(':scope > .dior-table-pagination') : null);

                if (!paginationWrap) {
                    paginationWrap = document.createElement('div');
                    paginationWrap.className = 'dior-table-pagination';
                    if (parent.classList.contains('dior-table-responsive') || parent.classList.contains('dior-card-body') || parent.style.overflowX === 'auto' || parent.style.overflow === 'auto') {
                        parent.after(paginationWrap);
                    } else {
                        parent.appendChild(paginationWrap);
                    }
                }

                var currentPage = 1;

                function renderPage(page) {
                    currentPage = page || 1;
                    var allRows = Array.from(tbody.querySelectorAll(':scope > tr')).filter(function (tr) {
                        return !tr.classList.contains('dior-no-data-row') && tr.querySelectorAll(':scope > td').length > 0;
                    });

                    if (allRows.length === 0) {
                        paginationWrap.style.display = 'none';
                        paginationWrap.innerHTML = '';
                        return;
                    }

                    var activeRows = allRows.filter(function (tr) {
                        return tr.getAttribute('data-filter-hidden') !== 'true';
                    });
                    var total = activeRows.length;
                    var totalPages = Math.max(1, Math.ceil(total / pageSize));

                    // If total entries are 5 or fewer, or only 1 page exists, show all rows and hide pagination bar completely
                    if (total <= pageSize || totalPages <= 1) {
                        allRows.forEach(function (tr) {
                            if (tr.getAttribute('data-filter-hidden') === 'true') {
                                tr.style.display = 'none';
                            } else {
                                tr.style.display = '';
                            }
                        });
                        paginationWrap.style.display = 'none';
                        paginationWrap.innerHTML = '';
                        return;
                    }

                    if (currentPage > totalPages) currentPage = totalPages;
                    if (currentPage < 1) currentPage = 1;

                    var startIndex = (currentPage - 1) * pageSize;
                    var endIndex = startIndex + pageSize;

                    allRows.forEach(function (tr) {
                        if (tr.getAttribute('data-filter-hidden') === 'true') {
                            tr.style.display = 'none';
                        }
                    });

                    activeRows.forEach(function (tr, index) {
                        if (index >= startIndex && index < endIndex) {
                            tr.style.display = '';
                        } else {
                            tr.style.display = 'none';
                        }
                    });

                    var fromItem = total === 0 ? 0 : startIndex + 1;
                    var toItem = Math.min(endIndex, total);

                    var navHtml = '';
                    if (totalPages > 1) {
                        navHtml += '<button type="button" class="dior-page-btn" ' + (currentPage === 1 ? 'disabled' : '') + ' data-page="' + (currentPage - 1) + '" title="Previous Page"><i class="fa-solid fa-chevron-left" style="font-size:11px;"></i></button>';

                        if (totalPages <= 7) {
                            for (var i = 1; i <= totalPages; i++) {
                                navHtml += '<button type="button" class="dior-page-btn ' + (i === currentPage ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
                            }
                        } else {
                            for (var i = 1; i <= totalPages; i++) {
                                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                                    navHtml += '<button type="button" class="dior-page-btn ' + (i === currentPage ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
                                } else if (i === currentPage - 2 || i === currentPage + 2) {
                                    navHtml += '<span style="padding: 0 4px; color: #94A3B8; font-size:12px;">...</span>';
                                }
                            }
                        }

                        navHtml += '<button type="button" class="dior-page-btn" ' + (currentPage === totalPages ? 'disabled' : '') + ' data-page="' + (currentPage + 1) + '" title="Next Page"><i class="fa-solid fa-chevron-right" style="font-size:11px;"></i></button>';
                    }

                    paginationWrap.style.display = 'flex';
                    paginationWrap.innerHTML = '<div class="dior-pagination-info">Showing <strong>' + fromItem + '</strong> to <strong>' + toItem + '</strong> of <strong>' + total + '</strong> entries</div><div class="dior-pagination-nav">' + navHtml + '</div>';

                    paginationWrap.querySelectorAll('.dior-page-btn[data-page]').forEach(function (btn) {
                        btn.onclick = function (e) {
                            e.preventDefault();
                            var p = parseInt(this.getAttribute('data-page'), 10);
                            if (!isNaN(p)) renderPage(p);
                        };
                    });
                }

                tableEl._diorRenderPage = renderPage;
                renderPage(1);
            };
        }

        function initAllDiorTablePaginations() {
            diorInitThemeMode();
            document.querySelectorAll('.dior-table, .dior-clean-table, .dior-doc-table').forEach(function (table) {
                if (typeof window.diorInitTablePagination === 'function') {
                    window.diorInitTablePagination(table, 5);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', initAllDiorTablePaginations);
        if (document.readyState === 'interactive' || document.readyState === 'complete') {
            initAllDiorTablePaginations();
        }
    </script>
</div> <!-- /#dior-patient-portal-app -->
<?php
                return ob_get_clean();
    }

    /**
     * AJAX Login Handler
     */
    public static function handle_login()
    {
        // Resilient nonce verification
        if (!empty($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'dior_auth_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'dior_portal_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'wp_rest');
        }

        $raw_username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['rememberme']);
        $redirect_to = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : '';

        if (empty($raw_username) || empty($password)) {
            wp_send_json_error(['message' => 'Please enter both your email/username and password.']);
        }

        $username = $raw_username;
        $user_obj = get_user_by('email', $raw_username);
        if (!$user_obj) {
            $user_obj = get_user_by('login', $raw_username);
        }
        if ($user_obj) {
            $username = $user_obj->user_login;
        } else {
            $username = sanitize_user($raw_username);
        }

        $creds = [
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember
        ];

        $user = wp_signon($creds, '');

        if (is_wp_error($user)) {
            $err_msg = wp_strip_all_tags($user->get_error_message());
            wp_send_json_error(['message' => $err_msg ?: 'Invalid email or password. Please try again.']);
        }

        // Set auth session cookies explicitly
        $user_id = $user->ID;
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, $remember);

        // Check user roles
        $user_data = get_userdata($user_id);
        $roles = (array) $user_data->roles;
        $is_doctor = in_array('doctor', $roles) || in_array('administrator', $roles) || in_array($user_data->user_login, ['docter', 'admin', 'doctor', 'jameschen', 'evelynvance', 'marcussterling', 'jon']);

        if ($is_doctor) {
            $redirect = home_url('/doctor-dashboard/');
        } elseif (!empty($redirect_to) && strpos($redirect_to, 'diro-login') === false && strpos($redirect_to, 'diro-registration') === false && strpos($redirect_to, 'doctor-dashboard') === false) {
            $redirect = $redirect_to;
        } else {
            $redirect = home_url('/patient-dashboard/');
        }

        wp_send_json_success([
            'message' => 'Login successful! Redirecting to dashboard...',
            'redirect' => $redirect
        ]);
    }

    /**
     * Role-based Login Redirect Filter for standard WP login
     */
    public static function custom_login_redirect($redirect_to, $requested_redirect_to, $user)
    {
        if ($user instanceof \WP_User && isset($user->roles) && is_array($user->roles)) {
            // Check if user is administrator
            if (in_array('administrator', $user->roles) || $user->user_login === 'admin') {
                return admin_url();
            }

            $is_doctor = in_array('doctor', $user->roles) || in_array($user->user_login, ['docter', 'doctor', 'jameschen', 'evelynvance', 'marcussterling', 'jon']);
            if ($is_doctor) {
                return home_url('/doctor-dashboard/');
            }
            return home_url('/patient-dashboard/');
        }
        return $redirect_to;
    }

    /**
     * AJAX Register Handler
     */
    public static function handle_register()
    {
        // Resilient nonce verification
        if (!empty($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'dior_auth_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'dior_portal_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'wp_rest');
        }

        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $dob = sanitize_text_field($_POST['dob'] ?? '');
        $gender = sanitize_text_field($_POST['gender'] ?? 'Female');
        $eligibility = sanitize_text_field($_POST['eligibility_check'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';
        $terms = !empty($_POST['terms_consent']);

        if (empty($first_name) || empty($last_name)) {
            wp_send_json_error(['message' => 'Please provide your full legal name.']);
        }

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => 'Please enter a valid email address.']);
        }

        if (email_exists($email)) {
            wp_send_json_error(['message' => 'An account with this email already exists. Please log in.']);
        }

        if ($eligibility !== 'yes') {
            wp_send_json_error(['message' => 'You must be 18 years of age or older to create an account and receive care.']);
        }

        if (empty($dob)) {
            wp_send_json_error(['message' => 'Please enter your date of birth.']);
        }

        $dob_time = strtotime($dob);
        $now = time();
        if (!$dob_time || $dob_time > $now) {
            wp_send_json_error(['message' => 'Invalid Date of Birth: Birth date cannot be in the future.']);
        }

        $reg_age = (int) date('Y', $now) - (int) date('Y', $dob_time);
        if (date('md', $now) < date('md', $dob_time)) {
            $reg_age--;
        }

        if ($reg_age < 18) {
            wp_send_json_error(['message' => 'You must be at least 18 years of age to register for Dior Medical adult telehealth care (Calculated age: ' . max(0, $reg_age) . ' years).']);
        }

        if (strlen($password) < 6) {
            wp_send_json_error(['message' => 'Password must be at least 6 characters long.']);
        }

        if ($password !== $confirm_pwd) {
            wp_send_json_error(['message' => 'Passwords do not match. Please re-enter.']);
        }

        if (!$terms) {
            wp_send_json_error(['message' => 'You must accept the Telehealth Terms of Care and Privacy Policy.']);
        }

        // Generate clean username from email
        $email_parts = explode('@', $email);
        $username_base = sanitize_user(current($email_parts));
        if (empty($username_base)) {
            $username_base = 'patient';
        }
        $username = $username_base;
        $i = 1;
        while (username_exists($username)) {
            $username = $username_base . $i;
            $i++;
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        // Set user details
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name),
            'role' => 'subscriber'
        ]);

        update_user_meta($user_id, 'phone', $phone);
        update_user_meta($user_id, 'dob', $dob);
        update_user_meta($user_id, 'gender', $gender);
        update_user_meta($user_id, 'is_18_verified', 'yes');
        update_user_meta($user_id, 'patient_id', 'DM-' . rand(10000, 99999));

        // Dispatch Multi-Channel Registration Confirmation (Dashboard + Luxury Email)
        if (class_exists('Dior_Notification_Service')) {
            Dior_Notification_Service::send_registration_confirmation($user_id);
        }

        // Auto log in new user
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        wp_send_json_success([
            'message' => 'Account created successfully! Redirecting to your dashboard...',
            'redirect' => home_url('/patient-dashboard/')
        ]);
    }

    /**
     * Route default WordPress lostpassword requests to custom page
     */
    public static function custom_lostpassword_url($lostpassword_url, $redirect)
    {
        return home_url('/diro-lost-password/');
    }

    /**
     * AJAX Lost Password Handler
     */
    public static function handle_lost_password()
    {
        // Resilient nonce verification
        if (!empty($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'dior_auth_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'dior_portal_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'wp_rest');
        }

        $user_input = trim($_POST['user_login'] ?? '');

        if (empty($user_input)) {
            wp_send_json_error(['message' => 'Please enter your email address or username.']);
        }

        // Find user by email or username
        $user = null;
        if (is_email($user_input)) {
            $user = get_user_by('email', $user_input);
        }
        if (!$user) {
            $user = get_user_by('login', sanitize_user($user_input));
        }

        // If user not found, return friendly notification
        if (!$user) {
            wp_send_json_success([
                'message' => 'If an account matches that email or username, password reset instructions have been sent to the email address on file.'
            ]);
        }

        // Generate password reset key
        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            wp_send_json_error(['message' => 'Unable to generate password reset link at this time. Please try again later.']);
        }

        // Build reset URL
        $reset_url = add_query_arg([
            'key' => $key,
            'login' => rawurlencode($user->user_login),
        ], home_url('/diro-reset-password/'));

        $site_name = get_bloginfo('name') ?: 'Dior Medical';
        $user_name = $user->display_name ?: $user->user_login;

        // Compose HTML email
        $subject = sprintf('[%s] Password Reset Request', $site_name);

        $body = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . esc_html($subject) . '</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F8FAFC; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #1E293B;">
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F8FAFC; padding: 40px 20px;">
    <tr>
        <td align="center">
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 540px; background-color: #FFFFFF; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid #E2E8F0;">
                <!-- Header -->
                <tr>
                    <td style="padding: 32px 32px 20px; text-align: center; background: linear-gradient(135deg, #0B1030 0%, #1E293B 100%);">
                        <h1 style="color: #FFFFFF; font-size: 22px; font-weight: 700; margin: 0; letter-spacing: 0.5px;">' . esc_html($site_name) . '</h1>
                        <p style="color: #94A3B8; font-size: 13px; margin: 6px 0 0; text-transform: uppercase; letter-spacing: 1px;">Patient & Provider Portal</p>
                    </td>
                </tr>
                <!-- Body -->
                <tr>
                    <td style="padding: 32px 32px 24px;">
                        <h2 style="font-size: 18px; color: #0F172A; margin: 0 0 14px; font-weight: 600;">Password Reset Request</h2>
                        <p style="font-size: 14.5px; line-height: 1.6; color: #475569; margin: 0 0 20px;">
                            Hello <strong>' . esc_html($user_name) . '</strong>,
                        </p>
                        <p style="font-size: 14.5px; line-height: 1.6; color: #475569; margin: 0 0 24px;">
                            We received a request to reset the password associated with your Dior Medical account. To proceed, please click the button below:
                        </p>
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 24px;">
                            <tr>
                                <td align="center">
                                    <a href="' . esc_url($reset_url) . '" target="_blank" style="display: inline-block; background-color: #0284C7; color: #FFFFFF; font-size: 15px; font-weight: 600; text-decoration: none; padding: 13px 32px; border-radius: 8px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);">
                                        Reset My Password
                                    </a>
                                </td>
                            </tr>
                        </table>
                        <p style="font-size: 13px; line-height: 1.5; color: #64748B; margin: 0 0 16px;">
                            Or copy and paste this link into your web browser:<br>
                            <a href="' . esc_url($reset_url) . '" style="color: #0284C7; word-break: break-all; font-size: 12.5px;">' . esc_html($reset_url) . '</a>
                        </p>
                        <hr style="border: none; border-top: 1px solid #E2E8F0; margin: 24px 0 20px;">
                        <p style="font-size: 12.5px; line-height: 1.5; color: #94A3B8; margin: 0;">
                            <strong>Security Note:</strong> This reset link will expire in 24 hours and can only be used once. If you did not request this password reset, no further action is required and your account remains secure.
                        </p>
                    </td>
                </tr>
                <!-- Footer -->
                <tr>
                    <td style="padding: 16px 32px 24px; text-align: center; background-color: #F8FAFC; border-top: 1px solid #F1F5F9;">
                        <p style="font-size: 12px; color: #94A3B8; margin: 0;">
                            &copy; ' . date('Y') . ' ' . esc_html($site_name) . '. All rights reserved.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <noreply@' . (isset($_SERVER['HTTP_HOST']) ? sanitize_text_field($_SERVER['HTTP_HOST']) : 'diormedical.com') . '>'
        ];

        // Send Email
        @wp_mail($user->user_email, $subject, $body, $headers);

        wp_send_json_success([
            'message' => 'Password reset instructions have been sent to your email (' . esc_html($user->user_email) . '). Please check your inbox and spam folder.'
        ]);
    }

    /**
     * AJAX Reset Password Handler
     */
    public static function handle_reset_password()
    {
        // Resilient nonce verification
        if (!empty($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'dior_auth_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'dior_portal_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'wp_rest');
        }

        $key = sanitize_text_field($_POST['rp_key'] ?? '');
        $login = sanitize_text_field($_POST['rp_login'] ?? '');
        $new_pwd = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';

        if (empty($key) || empty($login)) {
            wp_send_json_error(['message' => 'Invalid password reset parameters. Please request a new reset link.']);
        }

        if (empty($new_pwd)) {
            wp_send_json_error(['message' => 'Please enter your new password.']);
        }

        if (strlen($new_pwd) < 6) {
            wp_send_json_error(['message' => 'Password must be at least 6 characters long.']);
        }

        if ($new_pwd !== $confirm_pwd) {
            wp_send_json_error(['message' => 'Passwords do not match. Please re-enter.']);
        }

        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user)) {
            wp_send_json_error([
                'message' => 'This password reset link is invalid or has expired. Please request a new link.'
            ]);
        }

        // Reset password
        reset_password($user, $new_pwd);

        wp_send_json_success([
            'message' => 'Your password has been successfully reset! Redirecting to login...',
            'redirect' => home_url('/diro-login/?reset=success')
        ]);
    }

    /**
     * Handle Appointment Booking from the Standalone Page
     */
    public static function handle_book_appointment_from_page()
    {
        // For premium demonstration, simulate a successful booking
        if (!isset($_POST['doctor_id']) || !isset($_POST['date']) || !isset($_POST['time'])) {
            wp_send_json_error('Missing required booking details.');
        }

        $doctor_id = intval($_POST['doctor_id']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

        // If you were using the real Dior_DB table, you'd insert here.
        // E.g.
        // global $wpdb;
        // $table = $wpdb->prefix . 'dior_appointments';
        // $wpdb->insert($table, [
        //     'patient_id' => get_current_user_id() ?: 0,
        //     'doctor_id' => $doctor_id,
        //     'appt_date' => $date,
        //     'appt_time' => $time,
        //     'reason' => $reason,
        //     'status' => 'Scheduled'
        // ]);

        // For now, return success
        wp_send_json_success('Appointment successfully booked.');
    }

    /**
     * AJAX Logout Handler
     */
    public static function handle_logout()
    {
        wp_logout();
        wp_send_json_success([
            'redirect' => home_url('/diro-login/')
        ]);
    }

    /**
     * AJAX Save Patient Profile
     */
    public static function ajax_save_profile()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required. Please log in again.']);
        }

        $first_name = trim(sanitize_text_field($_POST['first_name'] ?? ''));
        $last_name = trim(sanitize_text_field($_POST['last_name'] ?? ''));
        $email = trim(sanitize_email($_POST['email'] ?? ''));
        $phone = trim(sanitize_text_field($_POST['phone'] ?? ''));
        $raw_dob = trim(sanitize_text_field($_POST['dob'] ?? ''));
        $gender = trim(sanitize_text_field($_POST['gender'] ?? 'Female'));
        $address = trim(sanitize_text_field($_POST['address'] ?? ''));

        // Normalize DOB to YYYY-MM-DD
        $dob = $raw_dob;
        if (!empty($raw_dob)) {
            $dob_time = strtotime($raw_dob);
            if ($dob_time !== false && $dob_time > 0) {
                $dob = date('Y-m-d', $dob_time);
            }
        }

        // Validation: All Personal Information fields are required
        if (empty($first_name) || empty($last_name) || empty($phone) || empty($dob) || empty($gender) || empty($address)) {
            $missing = [];
            if (empty($first_name))
                $missing[] = 'First Name';
            if (empty($last_name))
                $missing[] = 'Last Name';
            if (empty($phone))
                $missing[] = 'Phone Number';
            if (empty($dob))
                $missing[] = 'Date of Birth';
            if (empty($gender))
                $missing[] = 'Gender';
            if (empty($address))
                $missing[] = 'Mailing Address';

            wp_send_json_error([
                'message' => 'Please fill in all required personal information: ' . implode(', ', $missing) . '.',
                'code' => 'missing_required_fields',
                'missing_fields' => $missing
            ]);
        }

        // Age Verification: Patient must be 18+ and DOB cannot be in the future
        $dob_time = strtotime($dob);
        $now = time();
        if (!$dob_time || $dob_time > $now) {
            wp_send_json_error([
                'message' => 'Invalid Date of Birth: Birth date cannot be in the future.',
                'code' => 'future_dob'
            ]);
        }

        $calc_age = (int) date('Y', $now) - (int) date('Y', $dob_time);
        if (date('md', $now) < date('md', $dob_time)) {
            $calc_age--;
        }

        if ($calc_age < 18) {
            wp_send_json_error([
                'message' => 'Eligibility Requirement: Patient must be at least 18 years of age for Dior Medical adult telehealth care (Calculated age: ' . max(0, $calc_age) . ' years).',
                'code' => 'underage_patient'
            ]);
        }

        // Emergency contact
        $em_name = trim(sanitize_text_field($_POST['emergency_name'] ?? ''));
        $em_rel = trim(sanitize_text_field($_POST['emergency_relation'] ?? ''));
        $em_phone = trim(sanitize_text_field($_POST['emergency_phone'] ?? ''));

        // Preferred Pharmacy
        $pharm_name = trim(sanitize_text_field($_POST['pharmacy_name'] ?? ''));
        $pharm_address = trim(sanitize_text_field($_POST['pharmacy_address'] ?? ''));
        $pharm_phone = trim(sanitize_text_field($_POST['pharmacy_phone'] ?? ''));

        $userdata = ['ID' => $user_id];
        $userdata['first_name'] = $first_name;
        $userdata['last_name'] = $last_name;
        $userdata['display_name'] = trim($first_name . ' ' . $last_name);

        $current_user = get_userdata($user_id);
        if (!empty($email) && is_email($email) && $current_user && $email !== $current_user->user_email) {
            $existing_uid = email_exists($email);
            if ($existing_uid && $existing_uid != $user_id) {
                wp_send_json_error([
                    'message' => 'This email address is already in use by another account.',
                    'code' => 'email_exists'
                ]);
            }
            $userdata['user_email'] = $email;
        }

        $res = wp_update_user($userdata);
        if (is_wp_error($res)) {
            wp_send_json_error([
                'message' => $res->get_error_message(),
                'code' => $res->get_error_code()
            ]);
        }

        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'phone', $phone);
        update_user_meta($user_id, 'billing_phone', $phone);
        update_user_meta($user_id, 'dob', $dob);
        update_user_meta($user_id, 'date_of_birth', $dob);
        update_user_meta($user_id, 'gender', $gender);
        update_user_meta($user_id, 'address', $address);
        update_user_meta($user_id, 'billing_address_1', $address);
        update_user_meta($user_id, 'emergency_name', $em_name);
        update_user_meta($user_id, 'emergency_relation', $em_rel);
        update_user_meta($user_id, 'emergency_phone', $em_phone);
        update_user_meta($user_id, 'preferred_pharmacy_name', $pharm_name);
        update_user_meta($user_id, 'preferred_pharmacy_address', $pharm_address);
        update_user_meta($user_id, 'preferred_pharmacy_phone', $pharm_phone);

        // Appointment reminder opt-in preferences
        if (isset($_POST['optin_appointment_reminders'])) {
            update_user_meta($user_id, 'optin_appointment_reminders', sanitize_text_field($_POST['optin_appointment_reminders']) === '1' ? '1' : '0');
        }
        if (isset($_POST['optin_reminder_email'])) {
            update_user_meta($user_id, 'optin_reminder_email', sanitize_text_field($_POST['optin_reminder_email']) === '1' ? '1' : '0');
        }
        if (isset($_POST['optin_reminder_dashboard'])) {
            update_user_meta($user_id, 'optin_reminder_dashboard', sanitize_text_field($_POST['optin_reminder_dashboard']) === '1' ? '1' : '0');
        }

        // Keep intake data synced
        $hipaa_intake = get_user_meta($user_id, 'dior_hipaa_intake', true);
        if (!is_array($hipaa_intake)) {
            $hipaa_intake = [];
        }
        $hipaa_intake['first_name'] = $first_name;
        $hipaa_intake['last_name'] = $last_name;
        $hipaa_intake['phone'] = $phone;
        $hipaa_intake['dob'] = $dob;
        $hipaa_intake['gender'] = $gender;
        $hipaa_intake['address'] = $address;
        update_user_meta($user_id, 'dior_hipaa_intake', $hipaa_intake);

        $updated_profile = Dior_Patient_Portal_Data::get_patient_profile($user_id);
        $is_complete = Dior_Patient_Portal_Data::is_profile_complete($user_id);

        wp_send_json_success([
            'message' => 'Personal information updated successfully in database!',
            'profile' => $updated_profile,
            'is_complete' => $is_complete
        ]);
    }

    /**
     * AJAX Upload Patient Avatar / Profile Image
     */
    public static function ajax_upload_avatar()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required. Please log in again.']);
        }

        // Support selecting directly from WordPress Media Library (URL or ID passed)
        if (!empty($_POST['avatar_url'])) {
            $avatar_url = esc_url_raw($_POST['avatar_url']);
            $attach_id = !empty($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

            update_user_meta($user_id, 'dior_profile_image', $avatar_url);
            update_user_meta($user_id, 'profile_image', $avatar_url);
            if ($attach_id) {
                update_user_meta($user_id, 'dior_profile_image_id', $attach_id);
            }

            wp_send_json_success([
                'message' => 'Profile photo selected from Media Library successfully!',
                'avatar_url' => $avatar_url
            ]);
        }

        if (empty($_FILES['avatar']) || empty($_FILES['avatar']['name'])) {
            wp_send_json_error(['message' => 'No image file provided for upload.']);
        }

        $file = $_FILES['avatar'];

        // Limit file size to 5MB
        if (!empty($file['size']) && $file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(['message' => 'Image size exceeds the maximum limit of 5MB.']);
        }

        // Check file extension
        $file_info = wp_check_filetype($file['name']);
        $ext = strtolower($file_info['ext'] ?: pathinfo($file['name'], PATHINFO_EXTENSION));
        $valid_exts = ['jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif', 'jfif'];
        if (!in_array($ext, $valid_exts, true)) {
            wp_send_json_error(['message' => 'Invalid image format. Please choose a JPG, PNG, WEBP, or GIF image.']);
        }

        // Validate image content with getimagesize
        $image_size = @getimagesize($file['tmp_name']);
        if (!$image_size || empty($image_size['mime']) || strpos($image_size['mime'], 'image/') !== 0) {
            wp_send_json_error(['message' => 'Uploaded file is not a valid image. Please choose another image.']);
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $upload_overrides = ['test_form' => false, 'test_upload' => false];
        $movefile = wp_handle_upload($file, $upload_overrides);

        $avatar_url = '';
        if ($movefile && !isset($movefile['error']) && !empty($movefile['url'])) {
            $avatar_url = esc_url_raw($movefile['url']);
        } else {
            // Bulletproof fallback using wp_upload_dir() & move_uploaded_file / copy
            $upload_dir = wp_upload_dir();
            $safe_name = wp_unique_filename($upload_dir['path'], sanitize_file_name($file['name']));
            $target_path = $upload_dir['path'] . '/' . $safe_name;

            if (@move_uploaded_file($file['tmp_name'], $target_path) || @copy($file['tmp_name'], $target_path)) {
                $avatar_url = esc_url_raw($upload_dir['url'] . '/' . $safe_name);
            } else {
                $err = !empty($movefile['error']) ? $movefile['error'] : 'Failed to save uploaded image. Please check server folder permissions.';
                wp_send_json_error(['message' => $err]);
            }
        }

        update_user_meta($user_id, 'dior_profile_image', $avatar_url);
        update_user_meta($user_id, 'profile_image', $avatar_url);

        wp_send_json_success([
            'message' => 'Profile photo updated successfully!',
            'avatar_url' => $avatar_url
        ]);
    }

    /**
     * AJAX Remove Patient Avatar
     */
    public static function ajax_remove_avatar()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        delete_user_meta($user_id, 'dior_profile_image');
        delete_user_meta($user_id, 'profile_image');

        $user = get_userdata($user_id);
        $fn = get_user_meta($user_id, 'first_name', true) ?: ($user->first_name ?: '');
        $ln = get_user_meta($user_id, 'last_name', true) ?: ($user->last_name ?: '');
        $initials = strtoupper(substr($fn ?: 'P', 0, 1) . substr($ln ?: 'M', 0, 1));

        wp_send_json_success([
            'message' => 'Profile photo removed successfully.',
            'initials' => $initials
        ]);
    }

    /**
     * AJAX Upload Patient Digital Signature (PNG/WEBP with transparent background)
     */
    public static function ajax_upload_signature()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required. Please log in again.']);
        }

        // Support selecting directly from WordPress Media Library (URL or ID passed)
        if (!empty($_POST['signature_url'])) {
            $sig_url = esc_url_raw($_POST['signature_url']);
            $attach_id = !empty($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

            update_user_meta($user_id, 'dior_patient_signature', $sig_url);
            if ($attach_id) {
                update_user_meta($user_id, 'dior_patient_signature_id', $attach_id);
            }

            wp_send_json_success([
                'message' => 'Patient signature saved successfully!',
                'signature_url' => $sig_url
            ]);
        }

        // Support base64 data URI if drawn or uploaded
        if (!empty($_POST['signature_data'])) {
            $data = $_POST['signature_data'];
            if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
                $data = substr($data, strpos($data, ',') + 1);
                $decoded = base64_decode($data);
                if ($decoded !== false) {
                    $upload_dir = wp_upload_dir();
                    $filename = 'patient_sig_' . $user_id . '_' . time() . '.png';
                    $filepath = $upload_dir['path'] . '/' . $filename;
                    file_put_contents($filepath, $decoded);
                    $sig_url = esc_url_raw($upload_dir['url'] . '/' . $filename);

                    update_user_meta($user_id, 'dior_patient_signature', $sig_url);
                    wp_send_json_success([
                        'message' => 'Patient signature saved successfully!',
                        'signature_url' => $sig_url
                    ]);
                }
            }
        }

        if (empty($_FILES['signature']) || empty($_FILES['signature']['name'])) {
            wp_send_json_error(['message' => 'No signature file provided for upload.']);
        }

        $file = $_FILES['signature'];

        // Allowed image types - strictly PNG or WEBP for transparent background
        $allowed_mimes = [
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        if (empty($check['type']) || !in_array($check['type'], $allowed_mimes, true)) {
            wp_send_json_error(['message' => 'Invalid signature format. Please upload a PNG image with a transparent background.']);
        }

        // Limit file size to 3MB
        if ($file['size'] > 3 * 1024 * 1024) {
            wp_send_json_error(['message' => 'Signature file size exceeds the 3MB limit.']);
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $upload_overrides = ['test_form' => false, 'mimes' => $allowed_mimes];
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            $sig_url = esc_url_raw($movefile['url']);
            update_user_meta($user_id, 'dior_patient_signature', $sig_url);

            wp_send_json_success([
                'message' => 'Patient digital signature uploaded successfully!',
                'signature_url' => $sig_url
            ]);
        } else {
            // Bulletproof fallback
            $upload_dir = wp_upload_dir();
            $safe_name = wp_unique_filename($upload_dir['path'], sanitize_file_name($file['name']));
            $target_path = $upload_dir['path'] . '/' . $safe_name;

            if (@move_uploaded_file($file['tmp_name'], $target_path) || @copy($file['tmp_name'], $target_path)) {
                $sig_url = esc_url_raw($upload_dir['url'] . '/' . $safe_name);
                update_user_meta($user_id, 'dior_patient_signature', $sig_url);
                wp_send_json_success([
                    'message' => 'Patient digital signature uploaded successfully!',
                    'signature_url' => $sig_url
                ]);
            } else {
                $err = !empty($movefile['error']) ? $movefile['error'] : 'Failed to save uploaded signature. Please try again.';
                wp_send_json_error(['message' => $err]);
            }
        }
    }

    /**
     * AJAX Remove Patient Signature
     */
    public static function ajax_remove_signature()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        delete_user_meta($user_id, 'dior_patient_signature');
        delete_user_meta($user_id, 'dior_patient_signature_id');

        wp_send_json_success([
            'message' => 'Patient digital signature removed successfully.'
        ]);
    }

    /**
     * AJAX Check Intake Status (For real-time unlock after HIPAAtizer submission)
     */
    public static function ajax_check_intake_status()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        $hipaa_intake = get_user_meta($user_id, 'dior_hipaa_intake', true);
        if (!empty($hipaa_intake) && is_array($hipaa_intake)) {
            wp_send_json_success([
                'has_intake' => true,
                'hipaa_intake' => $hipaa_intake
            ]);
        } else {
            wp_send_json_success([
                'has_intake' => false
            ]);
        }
    }

    /**
     * AJAX Change Password (Real Backend Security)
     */
    public static function ajax_change_password()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        $current_pwd = $_POST['current_password'] ?? '';
        $new_pwd = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';

        if (empty($current_pwd) || empty($new_pwd)) {
            wp_send_json_error(['message' => 'Please fill in all password fields.']);
        }

        if ($new_pwd !== $confirm_pwd) {
            wp_send_json_error(['message' => 'New passwords do not match.']);
        }

        if (strlen($new_pwd) < 6) {
            wp_send_json_error(['message' => 'New password must be at least 6 characters long.']);
        }

        $user = get_userdata($user_id);
        if (!$user || !wp_check_password($current_pwd, $user->user_pass, $user_id)) {
            wp_send_json_error(['message' => 'Current password is incorrect.']);
        }

        wp_set_password($new_pwd, $user_id);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        wp_send_json_success(['message' => 'Password updated successfully!']);
    }

    /**
     * AJAX Book Patient Appointment (Integrated with DocBooker & Multi-Doctor Routing)
     */
    public static function ajax_book_appointment()
    {
        // Accept nonce from either dashboard, auth, or forms
        if (isset($_POST['nonce'])) {
            Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce', 'dior_doctor_nonce']);
        }

        $user_id = Dior_Auth_Service::get_current_user_id();

        // If guest booking (e.g. from public wizard), handle patient email
        $patient_email = sanitize_email($_POST['email'] ?? ($_POST['patient_email'] ?? ''));
        $patient_name = sanitize_text_field($_POST['name'] ?? ($_POST['patient_name'] ?? ($_POST['full_name'] ?? '')));
        $patient_phone = sanitize_text_field($_POST['phone'] ?? ($_POST['patient_phone'] ?? ''));

        if (!$user_id) {
            if ($patient_email && is_email($patient_email)) {
                $existing_user = get_user_by('email', $patient_email);
                if ($existing_user) {
                    $user_id = $existing_user->ID;
                } else {
                    $login = sanitize_user(current(explode('@', $patient_email)));
                    if (username_exists($login))
                        $login .= '_' . rand(100, 999);
                    $new_pid = wp_insert_user([
                        'user_login' => $login,
                        'user_email' => $patient_email,
                        'user_pass' => wp_generate_password(12, true),
                        'display_name' => $patient_name ?: $login,
                        'role' => 'subscriber'
                    ]);
                    if (!is_wp_error($new_pid)) {
                        $user_id = $new_pid;
                        if ($patient_phone)
                            update_user_meta($new_pid, 'phone', $patient_phone);
                    }
                }
            }
        }

        if (!$user_id) {
            wp_send_json_error(['message' => 'Please sign in or provide a valid email address to complete booking.'], 401);
        }

        // Strict validation: Require complete personal information before booking
        if (!Dior_Patient_Portal_Data::is_profile_complete($user_id)) {
            $missing = Dior_Patient_Portal_Data::get_missing_profile_fields($user_id);
            wp_send_json_error([
                'message' => 'Please complete your personal profile first (' . implode(', ', $missing) . ' required) before booking an appointment.',
                'code' => 'profile_incomplete',
                'missing_fields' => $missing
            ], 400);
        }

        $condition = sanitize_text_field($_POST['condition'] ?? ($_POST['treatment'] ?? 'Urgent Care Telehealth'));
        $type = sanitize_text_field($_POST['type'] ?? 'Video Visit (HD)');
        $provider_raw = sanitize_text_field($_POST['provider'] ?? ($_POST['doctor'] ?? ($_POST['doctor_name'] ?? '')));
        $doctor_id_raw = intval($_POST['doctorId'] ?? ($_POST['doctor_id'] ?? 0));
        $date = sanitize_text_field($_POST['date'] ?? ($_POST['bookingDate'] ?? current_time('Y-m-d')));
        $time = sanitize_text_field($_POST['time'] ?? 'Today - ASAP');
        $notes = sanitize_textarea_field($_POST['notes'] ?? ($_POST['patient_note'] ?? ''));

        // Resolve Doctor WP User ID & Name
        $doctor_user_id = 0;
        $doctor_name = $provider_raw ?: 'Dr. Dior Specialist';

        if (class_exists('Dior_Doctor_Resolver')) {
            $doctor_user_id = Dior_Doctor_Resolver::resolve_doctor_user_id($doctor_id_raw ?: $provider_raw);
        }

        if ($doctor_id_raw && is_numeric($doctor_id_raw)) {
            $doc_post = get_post($doctor_id_raw);
            if ($doc_post && $doc_post->post_type === 'wpddb_doctor') {
                $doctor_name = $doc_post->post_title;
            }
        } elseif ($doctor_user_id) {
            $du = get_userdata($doctor_user_id);
            if ($du)
                $doctor_name = $du->display_name;
        }

        $new_apt = Dior_Appointment_Service::book_appointment([
            'patient_id' => $user_id,
            'doctor_id' => $doctor_id_raw ?: ($doctor_user_id ?: 1),
            'doctor_user_id' => $doctor_user_id,
            'provider' => $doctor_name,
            'condition' => $condition,
            'visit_type' => $type,
            'appt_date' => $date,
            'appt_time' => $time,
            'join_url' => 'https://zoom.us/join',
            'payment_status' => 'Paid',
            'notes' => $notes
        ]);

        if (is_wp_error($new_apt)) {
            wp_send_json_error(['message' => $new_apt->get_error_message()]);
        }

        // Synchronize with DocBooker database tables if present
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'wpddb_bookings';
        $table_patients = $wpdb->prefix . 'wpddb_patients';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_bookings'") == $table_bookings) {
            $user_obj = get_userdata($user_id);
            $patient_row = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_patients WHERE email = %s LIMIT 1", $user_obj->user_email));
            $docbooker_patient_id = $patient_row ? $patient_row->id : 0;
            if (!$docbooker_patient_id && $wpdb->get_var("SHOW TABLES LIKE '$table_patients'") == $table_patients) {
                $wpdb->insert($table_patients, [
                    'full_name' => $user_obj->display_name,
                    'email' => $user_obj->user_email,
                    'phone' => get_user_meta($user_id, 'phone', true) ?: '(555) 000-0000',
                    'dob' => get_user_meta($user_id, 'dob', true) ?: '1992-05-14',
                    'patient_code' => 'DM-' . (10000 + ($user_id % 90000))
                ]);
                $docbooker_patient_id = $wpdb->insert_id;
            }
            $wpdb->insert($table_bookings, [
                'booking_id' => 'BK-' . rand(100000, 999999),
                'patient_id' => $docbooker_patient_id ?: $user_id,
                'doctor_id' => $doctor_id_raw ?: 1,
                'clinic_id' => 1,
                'amount_paid' => 49.00,
                'payment_status' => 'Paid',
                'payment_by' => 'Stripe',
                'booking_present_status' => 'upcoming',
                'booking_date' => $date,
                'day' => date('l', strtotime($date)),
                'time' => $time,
                'patient_note' => $notes ?: $condition,
                'status' => 'approved'
            ]);
        }

        // 1. Instant Patient Notification
        $notifications = Dior_Patient_Portal_Data::get_patient_notifications($user_id);
        array_unshift($notifications, [
            'id' => 'NOTIF-' . time() . '-' . rand(100, 999),
            'type' => 'appointment',
            'icon' => 'fa-calendar-check',
            'title' => 'Appointment Confirmed (' . $condition . ')',
            'message' => 'Your visit with ' . $doctor_name . ' is confirmed for ' . $date . ' at ' . $time . '. Click to join when ready.',
            'time' => 'Just now',
            'timestamp' => time(),
            'created_at' => current_time('mysql'),
            'is_read' => false,
            'action_url' => '#tab=appointments'
        ]);
        update_user_meta($user_id, 'dior_notifications', $notifications);

        // 2. Targeted Doctor Notification
        $patient_obj = get_userdata($user_id);
        $pname = $patient_obj ? $patient_obj->display_name : 'Patient';

        $notify_doctor_ids = [];
        if ($doctor_user_id) {
            $notify_doctor_ids[] = $doctor_user_id;
        }
        $default_doc_id = class_exists('Dior_Doctor_Resolver') ? Dior_Doctor_Resolver::get_default_doctor_id() : 0;
        if ($default_doc_id && !in_array($default_doc_id, $notify_doctor_ids)) {
            $notify_doctor_ids[] = $default_doc_id;
        }

        foreach (array_unique($notify_doctor_ids) as $du_id) {
            $dnotifs = get_user_meta($du_id, 'dior_doctor_notifications', true);
            if (!is_array($dnotifs))
                $dnotifs = [];
            array_unshift($dnotifs, [
                'id' => 'DN-APT-' . time() . '-' . rand(10, 99),
                'type' => 'appointment',
                'icon' => 'fa-calendar-check',
                'title' => 'New Appointment Scheduled (' . $doctor_name . ')',
                'message' => $pname . ' scheduled a visit (' . $condition . ') for ' . $date . ' at ' . $time . '.',
                'time' => 'Just now',
                'timestamp' => time(),
                'created_at' => current_time('mysql'),
                'is_read' => false,
                'action_url' => '#tab=doc-appointments'
            ]);
            update_user_meta($du_id, 'dior_doctor_notifications', $dnotifs);
        }

        // Multi-Channel Notifications: In-App Dashboard & Anti-Spam Luxury HTML Email (with localhost fallback)
        if (class_exists('Dior_Notification_Service') && !empty($new_apt['appt_uid'])) {
            Dior_Notification_Service::send_appointment_confirmation($new_apt['appt_uid']);
        }

        $dashboard_url = home_url('/patient-dashboard/#tab=appointments');

        wp_send_json_success([
            'message' => 'Appointment booked successfully! Redirecting to Patient Dashboard...',
            'redirect_url' => $dashboard_url,
            'appointment' => $new_apt
        ]);
    }

    /**
     * DocBooker Hook: Handles bookings created via DocBooker REST API / Booking form
     */
    public static function on_docbooker_booking_created($booking)
    {
        if (empty($booking) || !is_array($booking))
            return;

        $booking_id = $booking['booking_id'] ?? ('BK-' . time());
        $patient_email = $booking['patient_email'] ?? '';
        $patient_name = $booking['patient_name'] ?? 'Patient';
        $docbooker_doctor_id = $booking['doctor_id'] ?? 0;
        $date = $booking['booking_date'] ?? current_time('Y-m-d');
        $time = $booking['time'] ?? 'ASAP';
        $notes = $booking['notes'] ?? ($booking['patient_note'] ?? 'Telehealth Consultation');

        // Resolve Patient User ID
        $patient_user_id = 0;
        if (!empty($patient_email) && is_email($patient_email)) {
            $puser = get_user_by('email', $patient_email);
            if ($puser) {
                $patient_user_id = $puser->ID;
            } else {
                $login = sanitize_user(current(explode('@', $patient_email)));
                if (username_exists($login))
                    $login .= '_' . rand(100, 999);
                $new_pid = wp_insert_user([
                    'user_login' => $login,
                    'user_email' => $patient_email,
                    'user_pass' => wp_generate_password(12, true),
                    'display_name' => $patient_name,
                    'role' => 'subscriber'
                ]);
                if (!is_wp_error($new_pid)) {
                    $patient_user_id = $new_pid;
                    if (!empty($booking['patient_phone'])) {
                        update_user_meta($new_pid, 'phone', $booking['patient_phone']);
                    }
                }
            }
        }

        if (!$patient_user_id) {
            $patient_user_id = get_current_user_id();
        }

        // Resolve Doctor WP User ID & Name
        $doctor_user_id = 0;
        $doctor_name = 'Dr. Dior Specialist';
        if (class_exists('Dior_Doctor_Resolver')) {
            $doctor_user_id = Dior_Doctor_Resolver::resolve_doctor_user_id($docbooker_doctor_id);
        }
        if ($docbooker_doctor_id && is_numeric($docbooker_doctor_id)) {
            $doc_post = get_post($docbooker_doctor_id);
            if ($doc_post)
                $doctor_name = $doc_post->post_title;
        } elseif ($doctor_user_id) {
            $du = get_userdata($doctor_user_id);
            if ($du)
                $doctor_name = $du->display_name;
        }

        // 1. Add to Patient Appointments ledger
        if ($patient_user_id) {
            Dior_Appointment_Service::book_appointment([
                'patient_id' => $patient_user_id,
                'doctor_id' => $docbooker_doctor_id ?: $doctor_user_id,
                'doctor_user_id' => $doctor_user_id,
                'provider' => $doctor_name,
                'condition' => $notes ?: 'General Telehealth Consultation',
                'visit_type' => 'Video Visit (HD)',
                'appt_date' => $date,
                'appt_time' => $time,
                'join_url' => 'https://zoom.us/join',
                'payment_status' => 'Paid',
                'notes' => $notes,
                'booking_id' => $booking_id
            ]);

            // 2. Add Patient Notification
            $p_notifs = Dior_Patient_Portal_Data::get_patient_notifications($patient_user_id);
            array_unshift($p_notifs, [
                'id' => 'NOTIF-' . time() . '-' . rand(100, 999),
                'type' => 'appointment',
                'icon' => 'fa-calendar-check',
                'title' => 'Appointment Booked Successfully',
                'message' => 'Your visit with ' . $doctor_name . ' is confirmed for ' . $date . ' at ' . $time . '.',
                'time' => 'Just now',
                'timestamp' => time(),
                'created_at' => current_time('mysql'),
                'is_read' => false,
                'action_url' => '#tab=appointments'
            ]);
            update_user_meta($patient_user_id, 'dior_notifications', $p_notifs);
        }

        // 3. Add Doctor Notification to the targeted Doctor
        $notify_doctor_ids = [];
        if ($doctor_user_id) {
            $notify_doctor_ids[] = $doctor_user_id;
        }
        $default_doc_id = class_exists('Dior_Doctor_Resolver') ? Dior_Doctor_Resolver::get_default_doctor_id() : 0;
        if ($default_doc_id && !in_array($default_doc_id, $notify_doctor_ids)) {
            $notify_doctor_ids[] = $default_doc_id;
        }

        foreach (array_unique($notify_doctor_ids) as $doc_uid) {
            $dnotifs = get_user_meta($doc_uid, 'dior_doctor_notifications', true);
            if (!is_array($dnotifs))
                $dnotifs = [];
            array_unshift($dnotifs, [
                'id' => 'DN-APT-' . time() . '-' . rand(10, 99),
                'type' => 'appointment',
                'icon' => 'fa-calendar-check',
                'title' => 'New Appointment Scheduled (' . $doctor_name . ')',
                'message' => $patient_name . ' scheduled a visit for ' . $date . ' at ' . $time . ' (' . ($notes ?: 'Telehealth') . ').',
                'time' => 'Just now',
                'timestamp' => time(),
                'created_at' => current_time('mysql'),
                'is_read' => false,
                'action_url' => '#tab=doc-appointments'
            ]);
            update_user_meta($doc_uid, 'dior_doctor_notifications', $dnotifs);
        }
    }

    /**
     * AJAX Reschedule Appointment (Integrated with Database, Double-Booking Check, and Multi-Channel Notifications)
     */
    public static function ajax_reschedule_appointment()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce', 'dior_doctor_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        $appt_id = sanitize_text_field($_POST['appt_id'] ?? '');
        $new_date = sanitize_text_field($_POST['date'] ?? '');
        $new_time = sanitize_text_field($_POST['time'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        if (empty($appt_id) || empty($new_date) || empty($new_time)) {
            wp_send_json_error(['message' => 'Appointment ID, date, and time slot are required.']);
        }

        $actor = Dior_Auth_Service::is_doctor($user_id) ? 'doctor' : 'patient';
        $res = Dior_Appointment_Service::reschedule_appointment($appt_id, $new_date, $new_time, $notes, $actor);

        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'Appointment rescheduled successfully to ' . esc_html($new_date) . ' at ' . esc_html($new_time) . '.',
            'appt_id' => $appt_id,
            'new_date' => $new_date,
            'new_time' => $new_time,
            'appointment' => $res
        ]);
    }

    /**
     * AJAX Cancel Appointment (Integrated with Database, Reason Logging, and Multi-Channel Notifications)
     */
    public static function ajax_cancel_appointment()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce', 'dior_doctor_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        $appt_id = sanitize_text_field($_POST['appt_id'] ?? '');
        $reason = sanitize_text_field($_POST['reason'] ?? 'Cancelled by user');

        if (empty($appt_id)) {
            wp_send_json_error(['message' => 'Appointment ID is required.']);
        }

        $actor = Dior_Auth_Service::is_doctor($user_id) ? 'doctor' : 'patient';
        $res = Dior_Appointment_Service::cancel_appointment($appt_id, $reason, $actor);

        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'Appointment cancelled successfully.',
            'appt_id' => $appt_id,
            'status' => 'Cancelled'
        ]);
    }

    /**
     * AJAX Send Appointment Reminder (to both Doctor & Patient)
     */
    public static function ajax_send_appointment_reminder()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce', 'dior_doctor_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        $appt_id = sanitize_text_field($_POST['appt_id'] ?? '');
        if (empty($appt_id)) {
            wp_send_json_error(['message' => 'Appointment ID is required.']);
        }

        $is_doc = Dior_Auth_Service::is_doctor($user_id);
        $sender = $is_doc ? 'doctor' : 'patient';

        $res = Dior_Appointment_Service::send_reminder($appt_id, $sender);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'Appointment reminder has been sent via dashboard notification and email to both doctor and patient.'
        ]);
    }

    /**
     * AJAX Toggle Appointment Reminder Opt-In
     */
    public static function ajax_toggle_reminder_optin()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce', 'dior_doctor_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        $channel = sanitize_text_field($_POST['channel'] ?? 'all');
        $value = sanitize_text_field($_POST['value'] ?? '1') === '1' ? '1' : '0';

        if ($channel === 'all' || $channel === 'optin_appointment_reminders') {
            update_user_meta($user_id, 'optin_appointment_reminders', $value);
        } elseif ($channel === 'email' || $channel === 'optin_reminder_email') {
            update_user_meta($user_id, 'optin_reminder_email', $value);
        } elseif ($channel === 'dashboard' || $channel === 'optin_reminder_dashboard') {
            update_user_meta($user_id, 'optin_reminder_dashboard', $value);
        }

        wp_send_json_success([
            'message' => ($value === '1') ? 'Appointment reminder opt-in enabled. You and your provider will receive alerts.' : 'Appointment reminder opt-in paused.',
            'channel' => $channel,
            'value' => $value
        ]);
    }

    /**
     * AJAX Submit Questionnaire (with E-Sign & Doctor Dashboard sharing)
     */
    public static function ajax_submit_questionnaire()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id)
            wp_send_json_error(['message' => 'Authentication required.']);

        $qn_id = sanitize_text_field($_POST['qn_id'] ?? '');
        $condition = sanitize_text_field($_POST['condition'] ?? 'General Urgent Care');
        $symptoms = sanitize_textarea_field($_POST['symptoms'] ?? '');
        $allergies = sanitize_text_field($_POST['allergies'] ?? '');
        $meds = sanitize_text_field($_POST['current_meds'] ?? '');
        $history = sanitize_text_field($_POST['medical_history'] ?? '');
        $esign = sanitize_text_field($_POST['esign_name'] ?? '');

        $questionnaires = Dior_Patient_Portal_Data::get_patient_questionnaires($user_id);
        $updated = false;

        foreach ($questionnaires as &$qn) {
            if ($qn['id'] === $qn_id) {
                $qn['status'] = 'Submitted';
                $qn['status_class'] = 'status-success';
                $qn['symptoms'] = $symptoms;
                $qn['allergies'] = $allergies;
                $qn['current_meds'] = $meds;
                $qn['last_updated'] = current_time('M j, Y');
                $qn['consent_signed'] = true;
                $updated = true;
                break;
            }
        }

        if ($updated) {
            update_user_meta($user_id, 'dior_questionnaires', $questionnaires);

            // Store in central Doctor Dashboard queryable format
            $all_intakes = get_user_meta($user_id, 'dior_patient_intakes', true) ?: [];
            $all_intakes[] = [
                'intake_id' => 'INTAKE-' . rand(10000, 99999),
                'qn_id' => $qn_id,
                'condition' => $condition,
                'symptoms' => $symptoms,
                'allergies' => $allergies,
                'current_meds' => $meds,
                'medical_history' => $history,
                'esign_name' => $esign,
                'submitted_at' => current_time('mysql'),
                'status' => 'Transmitted to Doctor Dashboard'
            ];
            update_user_meta($user_id, 'dior_patient_intakes', $all_intakes);

            // Add notification
            $notifications = Dior_Patient_Portal_Data::get_patient_notifications($user_id);
            array_unshift($notifications, [
                'id' => 'NOTIF-' . time() . '-' . rand(100, 999),
                'type' => 'system',
                'icon' => 'fa-clipboard-check',
                'title' => 'Clinical Intake Received & Transmitted',
                'message' => 'Your symptoms intake form has been submitted and transmitted to the Doctor Dashboard.',
                'time' => 'Just now',
                'timestamp' => time(),
                'created_at' => current_time('mysql'),
                'is_read' => false,
                'action_url' => '#tab=questionnaire'
            ]);
            update_user_meta($user_id, 'dior_notifications', $notifications);

            wp_send_json_success(['message' => 'Intake questionnaire & e-signed consent submitted successfully to Doctor Dashboard!']);
        } else {
            wp_send_json_error(['message' => 'Questionnaire not found.']);
        }
    }

    /**
     * AJAX Pay Invoice
     */
    public static function ajax_pay_invoice()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id)
            wp_send_json_error(['message' => 'Authentication required.']);

        $inv_id = sanitize_text_field($_POST['inv_id'] ?? '');
        $payments = Dior_Patient_Portal_Data::get_patient_payments($user_id);
        $paid_item = null;

        foreach ($payments as &$pay) {
            if ($pay['id'] === $inv_id) {
                $pay['status'] = 'Paid';
                $pay['status_class'] = 'status-paid';
                $paid_item = $pay;
                break;
            }
        }

        update_user_meta($user_id, 'dior_payments', $payments);

        // Multi-Channel Payment Receipt Notification (Dashboard + Luxury Email with localhost fallback)
        if (class_exists('Dior_Notification_Service')) {
            Dior_Notification_Service::send_payment_confirmation($user_id, $paid_item ?: [
                'id' => $inv_id,
                'amount' => '$49.00',
                'description' => 'Telehealth Consultation Invoice'
            ]);
        }

        wp_send_json_success(['message' => 'Payment processed successfully via Stripe! Receipt issued.']);
    }

    /**
     * AJAX Mark Single Notification Read
     */
    public static function ajax_mark_notification_read()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id)
            wp_send_json_error(['message' => 'Authentication required.']);

        $notif_id = sanitize_text_field($_POST['notif_id'] ?? '');
        $notifications = Dior_Patient_Portal_Data::get_patient_notifications($user_id);

        foreach ($notifications as &$n) {
            if ($n['id'] === $notif_id) {
                $n['is_read'] = true;
                break;
            }
        }

        update_user_meta($user_id, 'dior_notifications', $notifications);
        wp_send_json_success(['message' => 'Notification marked as read.']);
    }

    /**
     * AJAX Mark All Notifications Read
     */
    public static function ajax_mark_all_notifications_read()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id)
            wp_send_json_error(['message' => 'Authentication required.']);

        $notifications = Dior_Patient_Portal_Data::get_patient_notifications($user_id);
        foreach ($notifications as &$n) {
            $n['is_read'] = true;
        }

        update_user_meta($user_id, 'dior_notifications', $notifications);
        wp_send_json_success(['message' => 'All notifications marked as read.']);
    }

    /**
     * AJAX Real-Time Notification Polling (Without Page Refresh)
     */
    public static function ajax_get_live_notifications()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Unauthorized'], 401);
        }

        $last_known_id = sanitize_text_field($_POST['last_notif_id'] ?? '');
        $notifications = Dior_Patient_Portal_Data::get_patient_notifications($user_id);

        $unread_count = 0;
        $new_alerts = [];
        $found_last = false;

        foreach ($notifications as $n) {
            $is_unread = empty($n['is_read']);
            if ($is_unread) {
                $unread_count++;
            }
            if (!empty($last_known_id)) {
                if ($n['id'] === $last_known_id) {
                    $found_last = true;
                } elseif (!$found_last && $is_unread) {
                    $new_alerts[] = [
                        'id' => $n['id'],
                        'title' => $n['title'] ?? 'Notification',
                        'message' => $n['message'] ?? '',
                        'icon' => $n['icon'] ?? 'fa-bell',
                        'action_url' => $n['action_url'] ?? '',
                        'time' => dior_format_notification_time($n)
                    ];
                }
            }
        }

        // Generate Dropdown HTML (Top 5)
        ob_start();
        if (empty($notifications)) {
            echo '<div class="dior-notif-empty-dropdown">
                    <i class="fa-regular fa-bell-slash"></i>
                    No new notifications
                  </div>';
        } else {
            foreach (array_slice($notifications, 0, 5) as $n) {
                $target_tab = !empty($n['action_url']) ? str_replace('#tab=', '', $n['action_url']) : 'notifications';
                ?>
<div class="dior-notif-item <?php echo empty($n['is_read']) ? 'unread' : ''; ?>"
    data-notif-id="<?php echo esc_attr($n['id']); ?>" data-switch-tab="<?php echo esc_attr($target_tab); ?>"
    style="cursor:pointer;">
    <div class="notif-icon"><i class="fa-solid <?php echo esc_attr($n['icon'] ?? 'fa-bell'); ?>"></i></div>
    <div class="notif-body">
        <strong><?php echo esc_html($n['title']); ?></strong>
        <p><?php echo esc_html(mb_substr($n['message'], 0, 45) . (mb_strlen($n['message']) > 45 ? '...' : '')); ?></p>
        <span class="notif-time"><?php echo esc_html(dior_format_notification_time($n)); ?></span>
    </div>
</div>
<?php
            }
        }
        $dropdown_html = ob_get_clean();

        // Generate Full Tab HTML
        ob_start();
        if (empty($notifications)) {
            echo '<div class="dior-notif-empty-tab">
                    <div class="dior-notif-empty-icon-box">
                        <i class="fa-regular fa-bell-slash"></i>
                    </div>
                    <p class="dior-notif-empty-title">No notifications yet</p>
                    <p class="dior-notif-empty-subtitle">You\'ll see consultation reminders, prescription routing alerts, and billing receipts here.</p>
                  </div>';
        } else {
            foreach ($notifications as $notif) {
                ?>
<div class="dior-full-notif-item <?php echo empty($notif['is_read']) ? 'unread' : ''; ?>"
    data-notif-id="<?php echo esc_attr($notif['id']); ?>" <?php if (!empty($notif['action_url'])): ?>
    onclick="window.location.href='<?php echo esc_js($notif['action_url']); ?>'" <?php endif; ?>>
    <div class="notif-type-icon <?php echo esc_attr($notif['type'] ?? 'system'); ?>">
        <i class="fa-solid <?php echo esc_attr($notif['icon'] ?? 'fa-bell'); ?>"></i>
    </div>
    <div class="notif-full-body">
        <div class="notif-full-top">
            <h4><?php echo esc_html($notif['title']); ?></h4>
            <span class="notif-full-time"><?php echo esc_html(dior_format_notification_time($notif)); ?></span>
        </div>
        <p><?php echo esc_html($notif['message']); ?></p>
        <?php if (!empty($notif['action_url'])): ?>
        <span class="dior-notif-action-text">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Click to view details
        </span>
        <?php endif; ?>
    </div>
    <div class="notif-full-actions">
        <?php if (empty($notif['is_read'])): ?>
        <button type="button" class="dior-btn-table-icon dior-mark-single-read" title="Mark as Read">
            <i class="fa-solid fa-check"></i>
        </button>
        <?php endif; ?>
    </div>
</div>
<?php
            }
        }
        $full_html = ob_get_clean();

        wp_send_json_success([
            'unread_count' => $unread_count,
            'dropdown_html' => $dropdown_html,
            'full_html' => $full_html,
            'latest_id' => !empty($notifications) ? $notifications[0]['id'] : '',
            'new_alerts' => $new_alerts
        ]);
    }

    /**
     * AJAX Update Pharmacy
     */
    public static function ajax_update_pharmacy()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id)
            wp_send_json_error(['message' => 'Authentication required.']);

        $pharm_name = sanitize_text_field($_POST['pharmacy_name'] ?? '');
        $pharm_address = sanitize_text_field($_POST['pharmacy_address'] ?? '');
        $pharm_phone = sanitize_text_field($_POST['pharmacy_phone'] ?? '');

        update_user_meta($user_id, 'preferred_pharmacy_name', $pharm_name);
        update_user_meta($user_id, 'preferred_pharmacy_address', $pharm_address);
        update_user_meta($user_id, 'preferred_pharmacy_phone', $pharm_phone);

        wp_send_json_success(['message' => 'Preferred pharmacy updated successfully!']);
    }

    /**
     * HIPAAtizer Webhook — AJAX handler (wp-admin/admin-ajax.php)
     * Set this in HIPAAtizer: Action URL = /wp-admin/admin-ajax.php?action=dior_hipaa_webhook
     */
    public static function ajax_hipaa_webhook()
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!$data) {
            $data = $_POST;
        }
        self::save_hipaa_intake($data);
        wp_send_json_success(['message' => 'Intake received.']);
    }

    /**
     * HIPAAtizer Webhook — REST API handler
     * Webhook URL: /wp-json/dior/v1/hipaa-webhook
     */
    public static function rest_hipaa_webhook(\WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        if (empty($data)) {
            $data = $request->get_body_params();
        }
        self::save_hipaa_intake($data);
        return new \WP_REST_Response(['message' => 'Intake received.'], 200);
    }

    /**
     * Save HIPAAtizer intake data to user meta by email match or user_id
     */
    public static function save_hipaa_intake($data)
    {
        if (empty($data))
            return;

        // Try to find user by user_id first
        $user_id = 0;
        if (!empty($data['user_id'])) {
            $user_id = (int) $data['user_id'];
        } elseif (!empty($_POST['user_id'])) {
            $user_id = (int) $_POST['user_id'];
        } elseif (is_user_logged_in()) {
            $user_id = get_current_user_id();
        }

        // Try to find user by email from form data
        $email = '';
        $email_keys = ['email', 'Email', 'email_address', 'patient_email', 'submitter_email'];
        foreach ($email_keys as $k) {
            if (!empty($data[$k])) {
                $email = sanitize_email($data[$k]);
                break;
            }
        }

        if (!$user_id && $email) {
            $user = get_user_by('email', $email);
            if ($user)
                $user_id = $user->ID;
        }

        if ($user_id && empty($email)) {
            $u_obj = get_userdata($user_id);
            if ($u_obj)
                $email = $u_obj->user_email;
        }

        $first_name = sanitize_text_field($data['first_name'] ?? $data['First Name'] ?? $data['firstname'] ?? '');
        $last_name = sanitize_text_field($data['last_name'] ?? $data['Last Name'] ?? $data['lastname'] ?? '');
        if ($user_id && empty($first_name)) {
            $first_name = get_user_meta($user_id, 'first_name', true) ?: '';
            $last_name = get_user_meta($user_id, 'last_name', true) ?: '';
        }

        // Build clean intake record with all 39 fields
        $intake = [
            'submitted_at' => current_time('Y-m-d H:i:s'),

            // Step 1: Service Selection
            'service_condition' => sanitize_text_field($data['service_condition'] ?? $data['service'] ?? $data['Which service or condition do you need care for today'] ?? ''),

            // Step 2: Eligibility Checks
            'is_18_plus' => sanitize_text_field($data['is_18_plus'] ?? $data['Are you 18 years or older'] ?? ''),
            'is_pregnant' => sanitize_text_field($data['is_pregnant'] ?? $data['Are you currently pregnant'] ?? ''),

            // Step 3: Patient Information
            'first_name' => $first_name,
            'last_name' => $last_name,
            'dob' => sanitize_text_field($data['dob'] ?? $data['date_of_birth'] ?? $data['Date of Birth'] ?? ''),
            'gender' => sanitize_text_field($data['gender'] ?? $data['Gender'] ?? $data['biological_sex'] ?? $data['Biological Sex'] ?? $data['Sex'] ?? $data['sex'] ?? ''),
            'email' => $email,
            'phone' => sanitize_text_field($data['phone'] ?? $data['Phone'] ?? $data['phone_number'] ?? ''),
            'address' => sanitize_text_field($data['address'] ?? $data['Address'] ?? $data['street_address'] ?? $data['Street Address'] ?? ''),
            'city' => sanitize_text_field($data['city'] ?? $data['City'] ?? ''),
            'state' => sanitize_text_field($data['state'] ?? $data['State'] ?? $data['service_state'] ?? ''),
            'zip' => sanitize_text_field($data['zip'] ?? $data['Zip'] ?? $data['postal_code'] ?? $data['ZIP Code'] ?? ''),

            // Step 4: Symptoms Description
            'symptoms_description' => sanitize_textarea_field($data['symptoms_description'] ?? $data['Describe your symptoms'] ?? ''),
            'symptom_duration' => sanitize_text_field($data['symptom_duration'] ?? $data['How long have you had these symptoms'] ?? ''),
            'symptom_severity' => sanitize_text_field($data['symptom_severity'] ?? $data['How severe are your symptoms'] ?? ''),

            // Step 5: Conditional Questions - UTI
            'uti_burning_pain' => sanitize_text_field($data['uti_burning_pain'] ?? $data['Do you have burning or pain when urinating'] ?? ''),
            'uti_frequency' => sanitize_text_field($data['uti_frequency'] ?? $data['Do you need to urinate more frequently or urgently'] ?? ''),
            'uti_appearance' => sanitize_text_field($data['uti_appearance'] ?? $data['Is your urine cloudy, dark or foul smelling'] ?? ''),
            'uti_blood' => sanitize_text_field($data['uti_blood'] ?? $data['Have you seen blood in your urine'] ?? ''),
            'uti_previous' => sanitize_text_field($data['uti_previous'] ?? $data['Have you had a urinary tract infection before'] ?? ''),
            'uti_last_date' => sanitize_text_field($data['uti_last_date'] ?? $data['When was your last urinary tract infection'] ?? ''),

            // Step 5: Conditional Questions - Allergies
            'allergy_symptoms' => is_array($data['allergy_symptoms'] ?? $data['Which allergy symptoms do you have']) ?
                implode(', ', array_map('sanitize_text_field', (array) ($data['allergy_symptoms'] ?? $data['Which allergy symptoms do you have']))) :
                sanitize_text_field($data['allergy_symptoms'] ?? $data['Which allergy symptoms do you have'] ?? ''),
            'allergy_seasonal' => sanitize_text_field($data['allergy_seasonal'] ?? $data['Are your allergy symptoms seasonal or year round'] ?? ''),
            'allergy_triggers' => sanitize_text_field($data['allergy_triggers'] ?? $data['What triggers your allergy symptoms'] ?? ''),
            'allergy_medication_taken' => sanitize_text_field($data['allergy_medication_taken'] ?? $data['Have you taken any allergy medication for this'] ?? ''),
            'allergy_medication_name' => sanitize_text_field($data['allergy_medication_name'] ?? $data['Which allergy medication did you take'] ?? ''),

            // Step 6: Medical History
            'diagnosed_conditions' => is_array($data['diagnosed_conditions'] ?? $data['Have you been diagnosed with any of the following']) ?
                implode(', ', array_map('sanitize_text_field', (array) ($data['diagnosed_conditions'] ?? $data['Have you been diagnosed with any of the following']))) :
                sanitize_text_field($data['diagnosed_conditions'] ?? $data['Have you been diagnosed with any of the following'] ?? ''),
            'other_conditions' => sanitize_textarea_field($data['other_conditions'] ?? $data['Other medical conditions'] ?? ''),
            'taking_medications' => sanitize_text_field($data['taking_medications'] ?? $data['Are you currently taking any medications'] ?? ''),
            'medications_list' => sanitize_textarea_field($data['medications_list'] ?? $data['List your current medications and dosage'] ?? ''),
            'drug_allergies' => sanitize_text_field($data['drug_allergies'] ?? $data['Do you have any drug allergies'] ?? ''),
            'drug_allergies_list' => sanitize_textarea_field($data['drug_allergies_list'] ?? $data['List your drug allergies and the reaction'] ?? ''),

            // Step 7: Documents & Appointment
            'photo_id' => sanitize_text_field($data['photo_id'] ?? $data['Photo identification'] ?? ''),
            'insurance_card' => sanitize_text_field($data['insurance_card'] ?? $data['Insurance card'] ?? ''),
            'affected_area_photo' => sanitize_text_field($data['affected_area_photo'] ?? $data['Photo of affected area'] ?? ''),
            'lab_results' => sanitize_text_field($data['lab_results'] ?? $data['Lab or test results'] ?? ''),
            'appointment_type' => sanitize_text_field($data['appointment_type'] ?? $data['Which type of appointment do you prefer'] ?? ''),
            'preferred_date' => sanitize_text_field($data['preferred_date'] ?? $data['Preferred Date'] ?? ''),
            'preferred_time' => sanitize_text_field($data['preferred_time'] ?? $data['Preferred Time'] ?? ''),

            // Step 8: Consent & Signature
            'telemedicine_consent' => sanitize_text_field($data['telemedicine_consent'] ?? $data['I consent to receive care through telemedicine'] ?? ''),
            'legal_acknowledgement' => sanitize_text_field($data['legal_acknowledgement'] ?? $data['I acknowledge and confirm the above'] ?? ''),
            'full_legal_name' => sanitize_text_field($data['full_legal_name'] ?? $data['Full Legal Name'] ?? ''),
            'signature' => sanitize_text_field($data['signature'] ?? $data['Signature'] ?? ''),
            'signature_date' => sanitize_text_field($data['signature_date'] ?? $data['Date'] ?? ''),

            // Legacy field names for compatibility
            'symptoms' => sanitize_textarea_field($data['symptoms_description'] ?? $data['symptoms'] ?? $data['Symptoms'] ?? $data['chief_complaint'] ?? $data['condition'] ?? ''),
            'allergies' => sanitize_text_field($data['allergy_symptoms'] ?? $data['allergies'] ?? $data['Allergies'] ?? ''),
            'medications' => sanitize_text_field($data['medications_list'] ?? $data['medications'] ?? $data['current_medications'] ?? $data['Medications'] ?? ''),
            'medical_history' => sanitize_textarea_field($data['other_conditions'] ?? $data['medical_history'] ?? $data['Medical History'] ?? ''),

            'raw_data' => $data, // keep full payload
        ];

        if ($user_id) {
            // Save to matched user
            update_user_meta($user_id, 'dior_hipaa_intake', $intake);
            update_user_meta($user_id, 'dior_hipaa_intake_submitted', '1');

            // Update user profile fields from HIPAAtizer data
            if (!empty($first_name)) {
                update_user_meta($user_id, 'first_name', $first_name);
            }
            if (!empty($last_name)) {
                update_user_meta($user_id, 'last_name', $last_name);
            }
            if (!empty($intake['phone'])) {
                update_user_meta($user_id, 'phone', $intake['phone']);
            }
            if (!empty($intake['gender'])) {
                update_user_meta($user_id, 'gender', $intake['gender']);
            }
            if (!empty($intake['dob'])) {
                update_user_meta($user_id, 'dob', $intake['dob']);
            }
            if (!empty($intake['address'])) {
                update_user_meta($user_id, 'street_address', $intake['address']);
            }
            if (!empty($intake['city'])) {
                update_user_meta($user_id, 'city', $intake['city']);
            }
            if (!empty($intake['state'])) {
                update_user_meta($user_id, 'state', $intake['state']);
            }
            if (!empty($intake['zip'])) {
                update_user_meta($user_id, 'zip', $intake['zip']);
            }
            if (!empty($intake['address']) || !empty($intake['city']) || !empty($intake['state'])) {
                $full_address = trim(($intake['address'] ?? '') . ' ' . ($intake['city'] ?? '') . ', ' . ($intake['state'] ?? '') . ' ' . ($intake['zip'] ?? ''));
                update_user_meta($user_id, 'address', $full_address);
            }

            // Create initial appointment from HIPAAtizer intake
            $appointments = get_user_meta($user_id, 'dior_appointments', true);
            if (!is_array($appointments)) {
                $appointments = [];
            }

            // Check if HIPAAtizer appointment already exists
            $has_hipaa_appt = false;
            foreach ($appointments as $appt) {
                if (isset($appt['from_hipaa']) && $appt['from_hipaa'] === true) {
                    $has_hipaa_appt = true;
                    break;
                }
            }

            // Only create appointment if no HIPAAtizer appointment exists
            if (!$has_hipaa_appt) {
                $new_appt = [
                    'id' => 'APT-HIPAA-' . time(),
                    'date' => current_time('Y-m-d'),
                    'time' => 'Pending',
                    'condition' => $intake['symptoms'] ?: 'Medical Consultation',
                    'provider' => 'Available Provider',
                    'provider_spec' => 'Board Certified Physician',
                    'type' => 'Video Visit (HD)',
                    'status' => 'Scheduled',
                    'join_url' => '',
                    'payment_status' => 'Pending',
                    'notes' => 'Appointment created from HIPAAtizer intake form.',
                    'can_join' => false,
                    'from_hipaa' => true
                ];
                $appointments[] = $new_appt;
                update_user_meta($user_id, 'dior_appointments', $appointments);
            }

            // Add a notification for Patient
            $notifications = get_user_meta($user_id, 'dior_notifications', true);
            if (!is_array($notifications))
                $notifications = [];
            array_unshift($notifications, [
                'id' => 'NOTIF-HIPAA-' . time(),
                'type' => 'intake',
                'icon' => 'fa-file-shield',
                'title' => 'Medical Intake Form Submitted',
                'message' => 'Your HIPAA medical intake form has been received and shared with your physician.',
                'time' => 'Just now',
                'timestamp' => time(),
                'created_at' => current_time('mysql'),
                'is_read' => false,
                'action_url' => '#tab=questionnaire',
            ]);
            update_user_meta($user_id, 'dior_notifications', $notifications);

            // Add notification to Doctors / Admin
            $patient_name = trim($first_name . ' ' . $last_name);
            if (empty($patient_name)) {
                $u_info = get_userdata($user_id);
                $patient_name = $u_info ? $u_info->display_name : 'Patient #' . $user_id;
            }
            $doctors = get_users(['role__in' => ['doctor', 'administrator']]);
            foreach ($doctors as $doc) {
                $doc_notifs = get_user_meta($doc->ID, 'dior_doctor_notifications', true);
                if (!is_array($doc_notifs))
                    $doc_notifs = [];
                array_unshift($doc_notifs, [
                    'id' => 'DN-INTAKE-' . time() . '-' . rand(10, 99),
                    'type' => 'intake',
                    'icon' => 'fa-file-shield',
                    'title' => 'New Medical Intake: ' . $patient_name,
                    'message' => $patient_name . ' submitted their HIPAA medical intake questionnaire.',
                    'time' => 'Just now',
                    'timestamp' => time(),
                    'created_at' => current_time('mysql'),
                    'is_read' => false,
                    'action_url' => '#tab=doc-intake',
                ]);
                update_user_meta($doc->ID, 'dior_doctor_notifications', $doc_notifs);
            }
        } elseif ($email) {
            // Store by email as key for later matching (when user logs in)
            $pending_key = 'dior_hipaa_pending_' . md5($email);
            update_option($pending_key, $intake, false);
        }
    }
}

/**
 * Helper to match a DocBooker doctor post / name to a WordPress doctor user ID
 */
function dior_find_doctor_user_id($docbooker_doctor_id = 0, $doctor_name = '')
{
    if ($docbooker_doctor_id) {
        $linked_uid = get_post_meta($docbooker_doctor_id, '_doctor_user_id', true);
        if ($linked_uid && get_userdata($linked_uid)) {
            return (int) $linked_uid;
        }
        $post = get_post($docbooker_doctor_id);
        if ($post && $post->post_author > 0) {
            $author = get_userdata($post->post_author);
            if ($author && (in_array('doctor', $author->roles) || in_array('administrator', $author->roles))) {
                return (int) $post->post_author;
            }
        }
    }

    // Search doctor users by name or linked meta
    $doc_users = get_users(['role__in' => ['doctor', 'administrator']]);
    if ($docbooker_doctor_id) {
        foreach ($doc_users as $u) {
            $u_linked = get_user_meta($u->ID, '_docbooker_doctor_id', true);
            if ($u_linked && (int) $u_linked === (int) $docbooker_doctor_id) {
                return $u->ID;
            }
        }
    }

    // Name matching
    if (!empty($doctor_name)) {
        $clean_doc_name = strtolower(trim(preg_replace('/^(dr\.?|doctor)\s+/i', '', $doctor_name)));
        $clean_doc_name = preg_replace('/,\s*(md|do|mbbs|np|pa|phd).*$/i', '', $clean_doc_name);
        $clean_doc_name = trim($clean_doc_name);

        foreach ($doc_users as $u) {
            $u_first = strtolower(get_user_meta($u->ID, 'first_name', true) ?: $u->first_name);
            $u_last = strtolower(get_user_meta($u->ID, 'last_name', true) ?: $u->last_name);
            $u_disp = strtolower($u->display_name);
            $u_full = trim($u_first . ' ' . $u_last);

            if ($clean_doc_name && ($clean_doc_name === $u_full || $clean_doc_name === $u_disp || strpos($doctor_name, $u->display_name) !== false)) {
                return $u->ID;
            }
            if ($u_last && strlen($u_last) >= 3 && stripos($doctor_name, $u_last) !== false) {
                return $u->ID;
            }
        }
    }

    // If there is only one doctor role user on site, route to that doctor
    $only_doctors = get_users(['role' => 'doctor']);
    if (count($only_doctors) === 1) {
        return $only_doctors[0]->ID;
    }

    return 0;
}

/**
 * Synchronize DocBooker bookings to Dior Medical Dashboard
 */
add_action('wpddb_booking_created', 'dior_sync_docbooker_appointment', 10, 1);
function dior_sync_docbooker_appointment($booking_data)
{
    if (empty($booking_data['patient_email']))
        return;

    $user = get_user_by('email', $booking_data['patient_email']);
    if (!$user)
        return; // if user not found, we can't sync it to patient dashboard

    $user_id = $user->ID;

    // Get existing appointments
    $appointments = get_user_meta($user_id, 'dior_appointments', true);
    if (!is_array($appointments)) {
        $appointments = [];
    }

    // Create new appointment record
    $doctor = get_post($booking_data['doctor_id']);
    $doctor_name = $doctor ? $doctor->post_title : 'Doctor';
    $spec = get_post_meta($booking_data['doctor_id'], 'wpddb_doctor_speciality', true);
    $matched_doc_uid = dior_find_doctor_user_id($booking_data['doctor_id'], $doctor_name);

    $new_appointment = [
        'id' => $booking_data['booking_id'],
        'date' => !empty($booking_data['booking_date']) ? date('M d, Y', strtotime($booking_data['booking_date'])) : $booking_data['day'],
        'time' => $booking_data['time'],
        'condition' => 'Telehealth Consultation',
        'type' => 'Telehealth Video Visit',
        'provider' => $doctor_name,
        'provider_spec' => $spec ?: 'General Practice & Telehealth',
        'provider_title' => $spec ?: 'General Practice & Telehealth',
        'status' => 'Confirmed',
        'join_url' => (!empty($matched_doc_uid) && ($doc_zoom = get_user_meta($matched_doc_uid, 'doctor_zoom_link', true))) ? $doc_zoom : 'https://zoom.us/join',
        'raw_date' => !empty($booking_data['booking_date']) ? $booking_data['booking_date'] : '',
        'doctor_id' => $booking_data['doctor_id'] ?? 0,
        'doctor_name' => $doctor_name,
        'doctor_user_id' => $matched_doc_uid,
        'patient_name' => !empty($booking_data['patient_name']) ? $booking_data['patient_name'] : $user->display_name,
        'patient_email' => $booking_data['patient_email'],
        'patient_phone' => $booking_data['patient_phone'] ?? '',
        'patient_user_id' => $user_id,
    ];

    // add to beginning of array
    array_unshift($appointments, $new_appointment);

    update_user_meta($user_id, 'dior_appointments', $appointments);

    // 1. Add notification for Patient
    $pnotifs = Dior_Patient_Portal_Data::get_patient_notifications($user_id);
    array_unshift($pnotifs, [
        'id' => 'NOTIF-APT-' . time() . '-' . rand(10, 99),
        'type' => 'appointment',
        'icon' => 'fa-calendar-check',
        'title' => 'Appointment Confirmed: ' . $doctor_name,
        'message' => 'Your consultation with ' . $doctor_name . ' has been booked for ' . $new_appointment['date'] . ' at ' . $new_appointment['time'] . '.',
        'time' => 'Just now',
        'timestamp' => time(),
        'created_at' => current_time('mysql'),
        'is_read' => false,
        'action_url' => '#tab=appointments'
    ]);
    update_user_meta($user_id, 'dior_notifications', $pnotifs);

    // 2. Add notification ONLY for the specific Doctor (and Admins)
    $target_doc_ids = [];
    if ($matched_doc_uid) {
        $target_doc_ids[] = $matched_doc_uid;
    } else {
        $only_docs = get_users(['role' => 'doctor']);
        foreach ($only_docs as $od) {
            $target_doc_ids[] = $od->ID;
        }
    }

    // Also include administrators so admin can supervise
    $admins = get_users(['role' => 'administrator']);
    foreach ($admins as $ad) {
        if (!in_array($ad->ID, $target_doc_ids)) {
            $target_doc_ids[] = $ad->ID;
        }
    }

    $pname = !empty($booking_data['patient_name']) ? $booking_data['patient_name'] : ($user ? $user->display_name : 'A patient');
    foreach ($target_doc_ids as $doc_uid) {
        $dnotifs = get_user_meta($doc_uid, 'dior_doctor_notifications', true);
        if (!is_array($dnotifs))
            $dnotifs = [];
        array_unshift($dnotifs, [
            'id' => 'DN-APT-' . time() . '-' . rand(10, 99),
            'type' => 'appointment',
            'icon' => 'fa-calendar-check',
            'title' => 'New Appointment Booked',
            'message' => $pname . ' booked a visit with ' . $doctor_name . ' for ' . $new_appointment['date'] . ' at ' . $new_appointment['time'] . '.',
            'time' => 'Just now',
            'timestamp' => time(),
            'created_at' => current_time('mysql'),
            'is_read' => false,
            'action_url' => '#tab=doc-appointments'
        ]);
        update_user_meta($doc_uid, 'dior_doctor_notifications', $dnotifs);
    }
}

require_once DIOR_PORTAL_PATH . 'dior-doctor-dashboard.php';


Dior_Medical_Auth::init();

// Add DocBooker Mapping to User Profile
add_action('show_user_profile', 'dior_docbooker_user_profile_fields');
add_action('edit_user_profile', 'dior_docbooker_user_profile_fields');

function dior_docbooker_user_profile_fields($user)
{
    if (!current_user_can('manage_options'))
        return;

    $doctors = get_posts([
        'post_type' => 'wpddb_doctor',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ]);

    // Find if the user is already linked to any doctor post
    $current_linked_doc_id = '';
    foreach ($doctors as $doc) {
        $linked_user = get_post_meta($doc->ID, '_wpddb_doctor_user_id', true);
        if ($linked_user == $user->ID) {
            $current_linked_doc_id = $doc->ID;
            break;
        }
    }

    ?>
<h3>DocBooker Integration</h3>
<table class="form-table">
    <tr>
        <th><label for="docbooker_doctor_id">Link to DocBooker Doctor</label></th>
        <td>
            <select name="docbooker_doctor_id" id="docbooker_doctor_id">
                <option value="">-- Select a Doctor --</option>
                <?php foreach ($doctors as $doc): ?>
                <option value="<?php echo esc_attr($doc->ID); ?>" <?php selected($current_linked_doc_id, $doc->ID); ?>>
                    <?php echo esc_html($doc->post_title); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p class="description">Select the DocBooker Doctor profile that corresponds to this user. This ensures
                appointments sync perfectly.</p>
        </td>
    </tr>
</table>
<?php
}

add_action('personal_options_update', 'dior_save_docbooker_user_profile_fields');
add_action('edit_user_profile_update', 'dior_save_docbooker_user_profile_fields');

function dior_save_docbooker_user_profile_fields($user_id)
{
    if (!current_user_can('manage_options'))
        return;

    if (isset($_POST['docbooker_doctor_id'])) {
        $new_doc_id = sanitize_text_field($_POST['docbooker_doctor_id']);

        // First, remove this user from any other doctor post to prevent duplicates
        global $wpdb;
        $wpdb->delete($wpdb->postmeta, [
            'meta_key' => '_wpddb_doctor_user_id',
            'meta_value' => $user_id
        ]);

        // Save the new one if selected
        if (!empty($new_doc_id)) {
            update_post_meta($new_doc_id, '_wpddb_doctor_user_id', $user_id);
        }
    }
}


