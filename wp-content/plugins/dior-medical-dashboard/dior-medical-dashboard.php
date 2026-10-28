<?php
/**
 * Plugin Name: Dior Medical - Patient Portal & Dashboard
 * Plugin URI:  https://vultureconcepts.com/
 * Author: Vulture Concepts
 * Author URI: https://vultureconcepts.com/
 * Description: Luxury, secure, state-of-the-art Patient Portal & Authentication System for Dior Medical Telehealth & Urgent Care.
 * Version: 17.2.28
 */

if (!defined('ABSPATH')) {
    exit;
}


define('DIOR_PORTAL_VERSION', '17.2.28');


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
require_once(DIOR_PORTAL_PATH . 'rest_api/hipaatizer_rest_api.php');
require_once(DIOR_PORTAL_PATH . 'rest_api/hipaatizer_api_sync.php');
if (!class_exists('Dior_Medical_Secure_Files')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-secure-files.php');
}
if (!class_exists('Dior_Demo_Data')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-demo-data.php');
}
if (!class_exists('Dior_Questionnaire_Service')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-questionnaire-service.php');
}
if (!class_exists('Dior_Doctor_Dynamic')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-doctor-dynamic.php');
}
if (!class_exists('Dior_Google_Meet')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-google-meet.php');
}
Dior_Google_Meet::init();

if (!class_exists('Dior_Prescription_Pdf_Service')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-prescription-pdf.php');
}

if (!class_exists('Dior_Invoice_Pdf_Service')) {
    require_once(DIOR_PORTAL_PATH . 'includes/class-dior-invoice-pdf.php');
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
    if (class_exists('Dior_Demo_Data')) {
        Dior_Demo_Data::init();
    }
});


// Preconnect Google Fonts for ultra-fast typography loading
add_action('wp_head', function () {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}, 0);

// Completely disable WordPress Admin Bar on all frontend portal pages
add_filter('show_admin_bar', '__return_false', 9999);
add_action('init', function () {
    if (!is_admin()) {
        show_admin_bar(false);
    }
}, 9999);
add_action('wp_head', function () {
    echo '<script id="dior-extension-error-shield">(function(){if(typeof window!=="undefined"&&window.addEventListener){window.addEventListener("unhandledrejection",function(e){var r=e?(e.reason||e):null;var m=r?(r.message||(typeof r==="string"?r:"")):"";if(typeof m==="string"&&(m.indexOf("Could not establish connection")!==-1||m.indexOf("Receiving end does not exist")!==-1||m.indexOf("message port closed")!==-1)){if(e.preventDefault)e.preventDefault();if(e.stopImmediatePropagation)e.stopImmediatePropagation();}});}})();</script>';
}, 1);
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
     * Keep the normalized patient table synchronized with WordPress profile data.
     * wp_users remains the authentication source; this table is the portal data source.
     */
    public static function sync_relational_patient($user_id)
    {
        global $wpdb;
        $user_id = (int) $user_id;
        $user = get_userdata($user_id);
        if (!$user)
            return false;

        $table = $wpdb->prefix . 'dior_patients';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table)
            return false;

        $dob = get_user_meta($user_id, 'dob', true) ?: get_user_meta($user_id, 'date_of_birth', true);
        $wpdb->replace($table, [
            'user_id' => $user_id,
            'patient_uid' => get_user_meta($user_id, 'patient_id', true) ?: ('DM-' . (10000 + ($user_id % 90000))),
            'first_name' => get_user_meta($user_id, 'first_name', true) ?: $user->first_name,
            'last_name' => get_user_meta($user_id, 'last_name', true) ?: $user->last_name,
            'email' => $user->user_email,
            'phone' => get_user_meta($user_id, 'phone', true) ?: get_user_meta($user_id, 'billing_phone', true),
            'dob' => $dob ? date('Y-m-d', strtotime($dob)) : null,
            'gender' => get_user_meta($user_id, 'gender', true),
            'blood_group' => get_user_meta($user_id, 'blood_group', true),
            'address' => get_user_meta($user_id, 'address', true) ?: get_user_meta($user_id, 'billing_address_1', true),
            'city' => get_user_meta($user_id, 'city', true),
            'state' => get_user_meta($user_id, 'state', true),
            'country' => get_user_meta($user_id, 'country', true) ?: (get_user_meta($user_id, 'billing_country', true) ?: 'United States of America'),
            'avatar_url' => get_user_meta($user_id, 'dior_profile_image', true) ?: get_user_meta($user_id, 'profile_image', true),
            'status' => 'active',
            'is_demo' => get_user_meta($user_id, 'dior_demo_record', true) ? 1 : 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        return true;
    }

    /**
     * Get and normalize HIPAAtizer intake data across all schema versions
     */
    public static function get_normalized_hipaa_intake($user_id)
    {
        $user_id = (int) $user_id;
        if (!$user_id)
            return [];

        $raw = get_user_meta($user_id, 'dior_hipaa_intake', true);
        $submitted_flag = get_user_meta($user_id, 'dior_hipaa_intake_submitted', true);

        if ((empty($raw) || !is_array($raw)) && !$submitted_flag) {
            return [];
        }

        if (!is_array($raw)) {
            $raw = [];
        }

        // Recursively flatten nested raw_data if present
        $flattened = $raw;
        if (!empty($raw['raw_data']) && is_array($raw['raw_data'])) {
            foreach ($raw['raw_data'] as $rk => $rv) {
                if (is_array($rv) && !empty($rv['data']) && is_array($rv['data'])) {
                    foreach ($rv['data'] as $k3 => $v3) {
                        if (!isset($flattened[$k3]) || $flattened[$k3] === '') {
                            $flattened[$k3] = $v3;
                        }
                    }
                } elseif (!isset($flattened[$rk]) || $flattened[$rk] === '') {
                    $flattened[$rk] = $rv;
                }
            }
        }

        // Helper to find first non-empty string across candidate keys
        $pick = function (...$keys) use ($flattened, $raw) {
            foreach ($keys as $k) {
                if (!empty($flattened[$k]) && is_string($flattened[$k]) && trim($flattened[$k]) !== '') {
                    return trim($flattened[$k]);
                }
                if (!empty($raw[$k]) && is_string($raw[$k]) && trim($raw[$k]) !== '') {
                    return trim($raw[$k]);
                }
            }
            return '';
        };

        $full_name = $pick('Full Name', 'full_name', 'full_legal_name', 'e_signature', 'Name');
        $first_name = $pick('first_name', 'First Name');
        $last_name = $pick('last_name', 'Last Name');
        if (empty($first_name) && !empty($full_name)) {
            $parts = explode(' ', $full_name, 2);
            $first_name = $parts[0];
            $last_name = $parts[1] ?? '';
        }

        $dob = $pick('dob', 'date_of_birth', 'Date of Birth', 'Date of Birth (MM/dd/yyyy)');
        $gender = $pick('gender', 'Gender', 'sex_at_birth', 'Sex', 'Gender (Labels)');
        $email = $pick('email', 'Email Address', 'Email', 'user_email');
        $phone = $pick('phone', 'phone_number', 'Mobile Phone Number', 'Cell Phone', 'Phone');

        $street = $pick('Street Address', 'address', 'address_line1', 'Mailing Address');
        $city = $pick('City', 'city');
        $state = $pick('State', 'state', 'State (Labels)');
        $zip = $pick('ZIP / PIN Code', 'zip', 'zip_code', 'Zip Code', 'postal_code');
        $country = $pick('Country', 'country') ?: 'United States of America';

        $full_address = trim($street . ($city ? ", $city" : '') . ($state ? ", $state" : '') . ($zip ? " $zip" : ''));
        if (empty($full_address) && !empty($street)) {
            $full_address = $street;
        }

        $service = $pick('selected_service', 'Selected Service / Condition', 'service_condition', 'Condition', 'Reason for Visit', 'reason_for_visit', 'Form Name');
        $symptoms = $pick('symptom_description', 'symptoms', 'Symptoms', 'reason_for_visit', 'Reason for Visit');
        $symptom_duration = $pick('symptom_duration', 'Symptom Duration');
        $symptom_severity = $pick('symptom_severity', 'Symptom Severity');

        $pharmacy_name = $pick('pharmacy_name', 'Preferred Pharmacy', 'Pharmacy Name');
        $pharmacy_address = $pick('pharmacy_location', 'preferred_pharmacy_address', 'Pharmacy Location');

        $emergency_name = $pick('Emergency Contact Name', 'emergency_name', 'emergency_contact_name');
        $emergency_phone = $pick('Emergency Contact Phone', 'emergency_phone', 'emergency_contact_phone');

        $insurance_provider = $pick('Insurance Provider Name', 'insurance_provider', 'Insurance Provider');
        $insurance_id = $pick('Insurance Member / Policy ID', 'insurance_member_id', 'Policy ID');

        $pdf_link = $pick('Download PDF Link', 'download_pdf_link', 'pdf_url', 'download_url');
        $submitted_at = $pick('submitted_at', 'Submission Created Date (MM/dd/yyyy)', 'Date Today (yyyy-MM-dd)');
        if (empty($submitted_at)) {
            $submitted_at = current_time('mysql');
        }

        return [
            'is_submitted' => true,
            'submitted_at' => $submitted_at,
            'full_name' => $full_name ?: trim("$first_name $last_name"),
            'first_name' => $first_name,
            'last_name' => $last_name,
            'dob' => $dob,
            'gender' => $gender,
            'email' => $email,
            'phone' => $phone,
            'street' => $street,
            'address' => $street,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'country' => $country,
            'full_address' => $full_address,
            'service_condition' => $service ?: 'General Clinical Consultation',
            'symptoms' => $symptoms,
            'symptoms_description' => $symptoms,
            'symptom_duration' => $symptom_duration,
            'symptom_severity' => $symptom_severity,
            'pharmacy_name' => $pharmacy_name,
            'pharmacy_address' => $pharmacy_address,
            'emergency_name' => $emergency_name,
            'emergency_phone' => $emergency_phone,
            'insurance_provider' => $insurance_provider,
            'insurance_member_id' => $insurance_id,
            'download_pdf_link' => $pdf_link,
            'pdf_url' => $pdf_link,
            'allergies' => $pick('drug_allergies_list', 'allergies', 'Allergies'),
            'medications' => $pick('medications_list', 'medications', 'Medications'),
            'medical_history' => $pick('medical_history', 'Medical History', 'hx_other'),
            'raw_data' => $flattened,
        ];
    }

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

        // Emergency contact
        $em_name = get_user_meta($user_id, 'emergency_name', true) ?: '';
        $em_rel = get_user_meta($user_id, 'emergency_relation', true) ?: '';
        $em_phone = get_user_meta($user_id, 'emergency_phone', true) ?: '';

        // Preferred Pharmacy
        $pharmacy_name = get_user_meta($user_id, 'preferred_pharmacy_name', true) ?: '';
        $pharmacy_address = get_user_meta($user_id, 'preferred_pharmacy_address', true) ?: '';
        $pharmacy_phone = get_user_meta($user_id, 'preferred_pharmacy_phone', true) ?: '';

        // Extract normalized HIPAAtizer intake data as fallback
        $hipaa_intake = self::get_normalized_hipaa_intake($user_id);
        if (!empty($hipaa_intake)) {
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
            if (empty($address) && !empty($hipaa_intake['full_address'])) {
                $address = $hipaa_intake['full_address'];
            } elseif (empty($address) && !empty($hipaa_intake['street'])) {
                $address = $hipaa_intake['street'];
            }
            if (empty($pharmacy_name) && !empty($hipaa_intake['pharmacy_name'])) {
                $pharmacy_name = $hipaa_intake['pharmacy_name'];
            }
            if (empty($pharmacy_address) && !empty($hipaa_intake['pharmacy_address'])) {
                $pharmacy_address = $hipaa_intake['pharmacy_address'];
            }
            if (empty($em_name) && !empty($hipaa_intake['emergency_name'])) {
                $em_name = $hipaa_intake['emergency_name'];
            }
            if (empty($em_phone) && !empty($hipaa_intake['emergency_phone'])) {
                $em_phone = $hipaa_intake['emergency_phone'];
            }
        }

        $patient_id = get_user_meta($user_id, 'patient_id', true);
        if (empty($patient_id)) {
            $patient_id = 'DM-' . (10000 + ($user_id % 90000));
            update_user_meta($user_id, 'patient_id', $patient_id);
        }
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
            'gender' => $gender ?: '',
            'blood_group' => get_user_meta($user_id, 'blood_group', true) ?: '',
            'height' => get_user_meta($user_id, 'height', true) ?: '',
            'weight' => get_user_meta($user_id, 'weight', true) ?: '',
            'city' => get_user_meta($user_id, 'city', true) ?: (get_user_meta($user_id, 'billing_city', true) ?: ''),
            'state' => get_user_meta($user_id, 'state', true) ?: (get_user_meta($user_id, 'billing_state', true) ?: ''),
            'zip' => get_user_meta($user_id, 'zip', true) ?: (get_user_meta($user_id, 'zip_code', true) ?: (get_user_meta($user_id, 'billing_postcode', true) ?: '')),
            'country' => get_user_meta($user_id, 'country', true) ?: (get_user_meta($user_id, 'billing_country', true) ?: 'United States of America'),
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

        // Keep the normalized patient record synchronized for dynamic reporting/API use.
        self::sync_relational_patient($user_id);
        return $profile;
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
     * Get the authenticated patient's clinical encounters from the relational records table.
     * Results are always scoped to the supplied patient user ID and exclude deleted encounters.
     */
    public static function get_patient_encounters($user_id)
    {
        global $wpdb;

        $user_id = absint($user_id);
        if (!$user_id) {
            return [];
        }

        $table = $wpdb->prefix . 'dior_encounters';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE patient_id = %d AND is_deleted = 0 AND status IN ('finalized', 'amended') ORDER BY created_at DESC LIMIT 500",
                $user_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
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
        global $wpdb;
        $table = $wpdb->prefix . 'dior_prescriptions';
        $results = [];

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE patient_id=%d AND status != 'Deleted' ORDER BY created_at DESC", (int) $user_id), ARRAY_A);
            if (!empty($rows)) {
                $results = array_map(static function ($row) use ($user_id) {
                    $pid = !empty($row['patient_id']) ? (int) $row['patient_id'] : (int) $user_id;
                    $rx_uid = !empty($row['prescription_uid']) ? $row['prescription_uid'] : (!empty($row['prescription_id']) ? $row['prescription_id'] : ('RX-' . $row['id']));
                    $title = !empty($row['title']) ? $row['title'] : 'General Medical Prescription';
                    $med_name = !empty($row['medications']) ? $row['medications'] : ($row['medication'] ?? 'Prescription Medication');

                    // Parse individual medications if multiple exist
                    $med_lines = preg_split('/;\s*(?:\r?\n)?|\r?\n/', trim((string) $med_name));
                    $dosage_parts = explode('|', (string) ($row['dosage'] ?? ''));
                    $freq_parts = explode('|', (string) ($row['frequency'] ?? ''));
                    $dur_parts = explode('|', (string) ($row['duration'] ?? ''));
                    $inst_parts = explode('||', (string) ($row['instructions'] ?? ''));

                    $items = [];
                    $idx = 0;
                    foreach ($med_lines as $m_line) {
                        $m_clean = trim(preg_replace('/^\d+\.\s*/', '', trim($m_line)));
                        if ($m_clean === '')
                            continue;
                        $d_clean = isset($dosage_parts[$idx]) ? trim(preg_replace('/^\d+\.\s*/', '', trim($dosage_parts[$idx]))) : ($row['dosage'] ?? 'As prescribed');
                        $f_clean = isset($freq_parts[$idx]) ? trim(preg_replace('/^\d+\.\s*/', '', trim($freq_parts[$idx]))) : ($row['frequency'] ?? '');
                        $du_clean = isset($dur_parts[$idx]) ? trim(preg_replace('/^\d+\.\s*/', '', trim($dur_parts[$idx]))) : ($row['duration'] ?? '');
                        $in_clean = isset($inst_parts[$idx]) ? trim(preg_replace('/^\d+\.\s*/', '', trim($inst_parts[$idx]))) : ($row['instructions'] ?? '');

                        $items[] = [
                            'id' => $rx_uid . '-' . ($idx + 1),
                            'medication' => $m_clean,
                            'dosage' => $d_clean ?: 'As prescribed',
                            'frequency' => $f_clean,
                            'duration' => $du_clean,
                            'instructions' => $in_clean ?: 'Take as directed by physician.',
                            'quantity' => $row['quantity'] ?? '1 Unit',
                            'refills' => $row['refills'] ?? 0,
                        ];
                        $idx++;
                    }

                    if (empty($items)) {
                        $items[] = [
                            'id' => $rx_uid,
                            'medication' => $row['medication'] ?? $med_name,
                            'dosage' => $row['dosage'] ?? 'As prescribed',
                            'frequency' => $row['frequency'] ?? '',
                            'duration' => $row['duration'] ?? '',
                            'instructions' => $row['instructions'] ?? 'Take as directed by physician.',
                            'quantity' => $row['quantity'] ?? '1 Unit',
                            'refills' => $row['refills'] ?? 0,
                        ];
                    }

                    $date_prescribed = !empty($row['prescription_date']) ? $row['prescription_date'] : (!empty($row['created_at']) ? substr($row['created_at'], 0, 10) : date('Y-m-d'));
                    $doctor_name = !empty($row['doctor_name']) ? $row['doctor_name'] : ('Dr. ' . ((get_userdata((int) ($row['doctor_id'] ?? 0))->display_name ?? 'Doctor')));

                    return [
                        'id' => $rx_uid,
                        'order_id' => $rx_uid,
                        'group_id' => $rx_uid,
                        'prescription_uid' => $rx_uid,
                        'title' => $title,
                        'condition' => $title,
                        'diagnosis' => $title,
                        'name' => $med_name,
                        'medication' => $med_name,
                        'dosage' => $row['dosage'] ?? '',
                        'frequency' => $row['frequency'] ?? '',
                        'duration' => $row['duration'] ?? '',
                        'quantity' => $row['quantity'] ?? '1 Unit',
                        'refills' => $row['refills'] ?? 0,
                        'instructions' => $row['instructions'] ?? '',
                        'notes' => $row['instructions'] ?? '',
                        'status' => $row['status'] ?? 'Active',
                        'is_active' => (strtolower($row['status'] ?? 'active') === 'active'),
                        'date_prescribed' => $date_prescribed,
                        'date' => $date_prescribed,
                        'prescribed_by' => $doctor_name,
                        'pharmacy' => get_user_meta($pid, 'preferred_pharmacy_name', true) ?: 'Dior Partner Pharmacy',
                        'items' => $items,
                    ];
                }, $rows);
            }
        }

        if (empty($results)) {
            $legacy_prescriptions = get_user_meta($user_id, 'dior_prescriptions', true);
            if (is_array($legacy_prescriptions) && !empty($legacy_prescriptions)) {
                $results = self::group_prescriptions($legacy_prescriptions);
            }
        }

        return $results;
    }

    /**
     * Get patient payments & invoices
     */
    public static function get_patient_payments($user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_payments';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE patient_id=%d ORDER BY created_at DESC", (int) $user_id), ARRAY_A);
            if (!empty($rows))
                return $rows;
        }
        $payments = get_user_meta($user_id, 'dior_payments', true);
        return is_array($payments) ? $payments : [];
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
        }

        // Also check if HIPAA intake was submitted and include it
        $hipaa = self::get_normalized_hipaa_intake($user_id);
        if (!empty($hipaa)) {
            $has_intake_entry = false;
            foreach ($questionnaires as $q) {
                if (!empty($q['type']) && $q['type'] === 'hipaa_intake') {
                    $has_intake_entry = true;
                    break;
                }
            }
            if (!$has_intake_entry) {
                array_unshift($questionnaires, [
                    'id' => 'HIPAA-INTAKE-' . $user_id,
                    'title' => 'Clinical Intake Questionnaire (' . (!empty($hipaa['service_condition']) ? $hipaa['service_condition'] : 'Urgent Care') . ')',
                    'status' => 'Submitted',
                    'type' => 'hipaa_intake',
                    'updated_at' => !empty($hipaa['submitted_at']) ? substr($hipaa['submitted_at'], 0, 10) : current_time('Y-m-d'),
                    'pdf_url' => $hipaa['download_pdf_link'] ?? '',
                ]);
            }
        }

        return $questionnaires;
    }

    /**
     * Get patient notifications
     */
    public static function get_patient_notifications($user_id)
    {
        $user_id = (int) $user_id;
        if (!$user_id) {
            return [];
        }

        global $wpdb;
        $all_notifs = [];
        $seen = [];
        $seen_content = [];

        // 1. Relational notification table
        $table = $wpdb->prefix . 'dior_notifications';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE (user_id=%d OR patient_id=%d) ORDER BY created_at DESC, id DESC LIMIT 100",
                $user_id,
                $user_id
            ), ARRAY_A);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $item = [
                        'id' => $row['notification_uid'] ?: ('notif_row_' . $row['id']),
                        'title' => $row['title'] ?: 'Notification',
                        'message' => $row['message'] ?: '',
                        'action_url' => $row['action_url'] ?: '',
                        'icon' => $row['icon'] ?: 'fa-bell',
                        'is_read' => (bool) $row['is_read'],
                        'created_at' => $row['created_at'],
                        'timestamp' => strtotime($row['created_at']) ?: time(),
                        'type' => $row['type'] ?: 'system'
                    ];
                    $c_key = md5(trim($item['title']) . '|' . trim($item['message']) . '|' . date('Y-m-d H:i', $item['timestamp']));
                    $seen[$item['id']] = true;
                    $seen_content[$c_key] = true;
                    $all_notifs[] = $item;
                }
            }
        }

        // 2. User meta notifications (from HIPAAtizer webhook, doctor notes, secure files)
        $meta_notifs = get_user_meta($user_id, 'dior_notifications', true);
        if (is_array($meta_notifs)) {
            foreach ($meta_notifs as $item) {
                if (!is_array($item))
                    continue;
                $notif_id = $item['id'] ?? ('notif_meta_' . md5(($item['title'] ?? '') . ($item['created_at'] ?? '')));
                $n_title = $item['title'] ?? 'Notification';
                $n_msg = $item['message'] ?? '';
                $n_ts = !empty($item['timestamp']) ? (int) $item['timestamp'] : (!empty($item['created_at']) ? strtotime($item['created_at']) : time());
                $c_key = md5(trim($n_title) . '|' . trim($n_msg) . '|' . date('Y-m-d H:i', $n_ts));

                if (!isset($seen[$notif_id]) && !isset($seen_content[$c_key])) {
                    $seen[$notif_id] = true;
                    $seen_content[$c_key] = true;
                    $all_notifs[] = [
                        'id' => $notif_id,
                        'title' => $n_title,
                        'message' => $n_msg,
                        'action_url' => $item['action_url'] ?? '',
                        'icon' => $item['icon'] ?? 'fa-bell',
                        'is_read' => !empty($item['is_read']),
                        'created_at' => $item['created_at'] ?? current_time('mysql'),
                        'timestamp' => $n_ts,
                        'type' => $item['type'] ?? 'system'
                    ];
                }
            }
        }

        // Sort by newest first
        usort($all_notifs, static function ($a, $b) {
            $tA = !empty($a['timestamp']) ? (int) $a['timestamp'] : (!empty($a['created_at']) ? strtotime($a['created_at']) : 0);
            $tB = !empty($b['timestamp']) ? (int) $b['timestamp'] : (!empty($b['created_at']) ? strtotime($b['created_at']) : 0);
            return $tB <=> $tA;
        });

        return $all_notifs;
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
        add_action('wp_ajax_dior_patient_submit_feedback', [__CLASS__, 'ajax_submit_feedback']);
        add_action('wp_ajax_dior_patient_update_feedback', [__CLASS__, 'ajax_update_feedback']);
        add_action('wp_ajax_dior_patient_delete_feedback', [__CLASS__, 'ajax_delete_feedback']);
        add_action('wp_ajax_dior_patient_bulk_delete_feedback', [__CLASS__, 'ajax_bulk_delete_feedback']);
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
        add_action('wp_ajax_dior_patient_bulk_delete_notifications', [__CLASS__, 'ajax_bulk_delete_notifications']);
        add_action('wp_ajax_dior_patient_get_live_notifications', [__CLASS__, 'ajax_get_live_notifications']);
        add_action('wp_ajax_dior_patient_update_pharmacy', [__CLASS__, 'ajax_update_pharmacy']);
        add_action('wp_ajax_dior_patient_check_intake_status', [__CLASS__, 'ajax_check_intake_status']);
        add_action('wp_ajax_dior_patient_save_emergency_contact', [__CLASS__, 'ajax_save_emergency_contact']);
        add_action('wp_ajax_dior_patient_delete_emergency_contact', [__CLASS__, 'ajax_delete_emergency_contact']);
        add_action('wp_ajax_dior_patient_add_health_goal', [__CLASS__, 'ajax_add_health_goal']);
        add_action('wp_ajax_dior_patient_delete_health_goal', [__CLASS__, 'ajax_delete_health_goal']);

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

        // REST API: HIPAAtizer Webhook & Doctor Live Slot Availability
        add_action('rest_api_init', function () {
            register_rest_route('dior/v1', '/hipaa-webhook', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'rest_hipaa_webhook'],
                'permission_callback' => '__return_true', // HIPAAtizer server posts here
            ]);
            register_rest_route('doctor-details-booking/v1', '/doctors/(?P<id>\d+)/availability', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'rest_doctor_availability'],
                'permission_callback' => '__return_true',
            ]);
            register_rest_route('dior/v1', '/availability', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'rest_doctor_availability'],
                'permission_callback' => '__return_true',
            ]);

            // Intercept DocBooker booking submission to enforce 15-min advance rule
            add_filter('rest_pre_dispatch', function ($result, $server, $request) {
                $route = $request->get_route();
                if ($request->get_method() === 'POST' && strpos($route, '/doctor-details-booking/v1/bookings') !== false) {
                    $params = $request->get_params();
                    $bDate = sanitize_text_field($params['bookingDate'] ?? '');
                    $bTime = sanitize_text_field($params['time'] ?? '');
                    if ($bDate && $bTime && !Dior_Medical_Dashboard::is_slot_bookable_15min($bDate, $bTime, 15)) {
                        return new \WP_REST_Response([
                            'error' => 'This appointment time slot is no longer available. Appointments must be scheduled at least 15 minutes prior to the start time.'
                        ], 400);
                    }
                }
                return $result;
            }, 10, 3);

            // Filter DocBooker availability response so expired slots are flagged
            add_filter('rest_post_dispatch', function ($response, $server, $request) {
                $route = $request->get_route();
                if ($request->get_method() === 'GET' && strpos($route, '/doctor-details-booking/v1/doctors/') !== false && strpos($route, '/availability') !== false) {
                    if ($response instanceof \WP_REST_Response) {
                        $data = $response->get_data();
                        $qDate = sanitize_text_field($request->get_param('date') ?: current_time('Y-m-d'));
                        if (!empty($data['clinics']) && is_array($data['clinics'])) {
                            foreach ($data['clinics'] as &$clinic) {
                                if (!empty($clinic['timings']) && is_array($clinic['timings'])) {
                                    foreach ($clinic['timings'] as &$timing) {
                                        if (!empty($timing['time'])) {
                                            if (!Dior_Medical_Dashboard::is_slot_bookable_15min($qDate, $timing['time'], 15)) {
                                                $timing['is_available'] = false;
                                                $timing['reason'] = 'expired';
                                            }
                                        }
                                    }
                                    unset($timing);
                                }
                            }
                            unset($clinic);
                            $response->set_data($data);
                        }
                    }
                }
                return $response;
            }, 10, 3);
        });

        add_action('wp_ajax_dior_get_doctor_availability', [__CLASS__, 'ajax_doctor_availability']);
        add_action('wp_ajax_nopriv_dior_get_doctor_availability', [__CLASS__, 'ajax_doctor_availability']);

        // Auto-create pages on init & admin_init
        add_action('init', [__CLASS__, 'ensure_pages_exist']);
        add_action('admin_init', [__CLASS__, 'ensure_pages_exist']);

        // Role-based Login Redirect (Doctors -> /doctor-dashboard/, Patients -> /patient-dashboard/)
        add_filter('login_redirect', [__CLASS__, 'custom_login_redirect'], 99, 3);

        // Lost Password URL Filter
        add_filter('lostpassword_url', [__CLASS__, 'custom_lostpassword_url'], 99, 2);

        // Template Canvas Override (Elementor Canvas mode)
        add_filter('template_include', [__CLASS__, 'override_page_template'], 99);

        // DocBooker Booking Modal in wp_footer
        add_action('wp_footer', [__CLASS__, 'render_docbooker_booking_modal'], 50);
    }

    /**
     * Enqueue CSS & JS
     */
    public static function enqueue_assets()
    {

        // Google Fonts: Montserrat & DM Sans for portal typography
        wp_enqueue_style(
            'dior-google-fonts',
            'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap',
            [],
            null
        );

        // Font Awesome for modern icons
        wp_enqueue_style(
            'font-awesome-6',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
            [],
            '6.5.1'
        );

        // Bootstrap Icons for native Bootstrap iconography (Static local files)
        $bi_css_path = DIOR_PORTAL_PATH . 'assets/bootstrap/css/bootstrap-icons.min.css';
        $bi_css_ver = file_exists($bi_css_path) ? filemtime($bi_css_path) : '1.11.3';
        wp_enqueue_style(
            'bootstrap-icons',
            DIOR_PORTAL_URL . 'assets/bootstrap/css/bootstrap-icons.min.css',
            [],
            $bi_css_ver
        );

        // ── 1. Bootstrap 5.3.3 Core Framework CSS (Static local files) ─────────
        $bs_css_path = file_exists(DIOR_PORTAL_PATH . 'assets/bootstrap/css/bootstrap.min.css')
            ? DIOR_PORTAL_PATH . 'assets/bootstrap/css/bootstrap.min.css'
            : DIOR_PORTAL_PATH . 'assets/bootstrap/bootstrap.min.css';
        $bs_css_url = file_exists(DIOR_PORTAL_PATH . 'assets/bootstrap/css/bootstrap.min.css')
            ? DIOR_PORTAL_URL . 'assets/bootstrap/css/bootstrap.min.css'
            : DIOR_PORTAL_URL . 'assets/bootstrap/bootstrap.min.css';
        $bs_css_ver = file_exists($bs_css_path) ? filemtime($bs_css_path) : '5.3.3';

        wp_enqueue_style(
            'dior-bootstrap-css',
            $bs_css_url,
            [],
            $bs_css_ver
        );

        // ── 2. Unified Custom Stylesheet (All Custom CSS Consolidated in 1 File)
        $custom_css_ver = file_exists(DIOR_PORTAL_PATH . 'assets/css/dior-custom.css') ? filemtime(DIOR_PORTAL_PATH . 'assets/css/dior-custom.css') : DIOR_PORTAL_VERSION;
        wp_enqueue_style(
            'dior-custom-css',
            DIOR_PORTAL_URL . 'assets/css/dior-custom.css',
            ['dior-bootstrap-css', 'dior-google-fonts'],
            $custom_css_ver
        );

        // Load brand color overrides after the consolidated stylesheet.
        $brand_css_path = DIOR_PORTAL_PATH . 'assets/css/dior-brand-overrides.css';
        if (file_exists($brand_css_path)) {
            wp_enqueue_style(
                'dior-brand-overrides-css',
                DIOR_PORTAL_URL . 'assets/css/dior-brand-overrides.css',
                ['dior-custom-css'],
                filemtime($brand_css_path)
            );
        }

        // Alias handles to dior-custom-css to prevent any extra duplicate CSS downloads
        wp_register_style('dior-auth-css', false, ['dior-custom-css']);
        wp_register_style('dior-bootstrap-unify-css', false, ['dior-custom-css']);
        wp_register_style('dior-patient-css', false, ['dior-custom-css']);
        wp_register_style('dior-doctor-css', false, ['dior-custom-css']);
        wp_register_style('dior-docbooker-modal-css', false, ['dior-custom-css']);
        wp_register_style('dior-fda-prescription-modal-css', false, ['dior-custom-css']);
        wp_register_style('dior-hipaatizer-popup-v17', false, ['dior-custom-css']);

        $bs_js_path = file_exists(DIOR_PORTAL_PATH . 'assets/bootstrap/js/bootstrap.bundle.min.js')
            ? DIOR_PORTAL_PATH . 'assets/bootstrap/js/bootstrap.bundle.min.js'
            : DIOR_PORTAL_PATH . 'assets/bootstrap/bootstrap.bundle.min.js';
        $bs_js_url = file_exists(DIOR_PORTAL_PATH . 'assets/bootstrap/js/bootstrap.bundle.min.js')
            ? DIOR_PORTAL_URL . 'assets/bootstrap/js/bootstrap.bundle.min.js'
            : DIOR_PORTAL_URL . 'assets/bootstrap/bootstrap.bundle.min.js';
        $bs_js_ver = file_exists($bs_js_path) ? filemtime($bs_js_path) : '5.3.3';

        wp_enqueue_script(
            'dior-bootstrap-js',
            $bs_js_url,
            ['jquery'],
            $bs_js_ver,
            true
        );

        // ── Native Bootstrap SweetAlert Engine (Zero external CSS or JS) ──────
        $bs_alert_ver = file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-bootstrap-alert.js') ? filemtime(DIOR_PORTAL_PATH . 'assets/js/dior-bootstrap-alert.js') : DIOR_PORTAL_VERSION;
        wp_enqueue_script(
            'sweetalert2',
            DIOR_PORTAL_URL . 'assets/js/dior-bootstrap-alert.js',
            ['dior-bootstrap-js'],
            $bs_alert_ver,
            true
        );
        wp_register_script(
            'dior-bootstrap-alert',
            DIOR_PORTAL_URL . 'assets/js/dior-bootstrap-alert.js',
            ['dior-bootstrap-js'],
            $bs_alert_ver,
            true
        );

        // Animation libraries removed for clean native browser scrolling

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

        // Patient navigation safety controller — no dependencies so sidebar tabs remain usable even if another widget fails.
        wp_enqueue_script(
            'dior-patient-navigation-js',
            DIOR_PORTAL_URL . 'assets/js/dior-patient-navigation.js',
            [],
            file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-patient-navigation.js') ? filemtime(DIOR_PORTAL_PATH . 'assets/js/dior-patient-navigation.js') : DIOR_PORTAL_VERSION,
            true
        );

        // Doctor navigation safety controller — zero dependencies so sidebar tabs always switch reliably.
        wp_enqueue_script(
            'dior-doctor-navigation-js',
            DIOR_PORTAL_URL . 'assets/js/dior-doctor-navigation.js',
            [],
            file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-doctor-navigation.js') ? filemtime(DIOR_PORTAL_PATH . 'assets/js/dior-doctor-navigation.js') : DIOR_PORTAL_VERSION,
            true
        );

        wp_enqueue_script(
            'dior-patient-live',
            DIOR_PORTAL_URL . 'assets/js/dior-patient-live.js',
            [],
            file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-patient-live.js') ? filemtime(DIOR_PORTAL_PATH . 'assets/js/dior-patient-live.js') : DIOR_PORTAL_VERSION,
            true
        );



        // Dashboard JS
        wp_enqueue_script(
            'dior-dashboard-js',
            DIOR_PORTAL_URL . 'assets/js/dior-dashboard.js',
            ['jquery', 'sweetalert2', 'html2pdf'],
            time(),
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

        // Page-specific dashboard behavior extracted from template inline scripts.
        wp_enqueue_script(
            'dior-patient-dashboard-page-js',
            DIOR_PORTAL_URL . 'assets/js/dior-patient-dashboard.js',
            ['dior-dashboard-js'],
            time(),
            true
        );
        wp_enqueue_script(
            'dior-patient-static-actions',
            DIOR_PORTAL_URL . 'assets/js/dior-patient-static-actions.js',
            ['dior-patient-dashboard-page-js'],
            file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-patient-static-actions.js') ? filemtime(DIOR_PORTAL_PATH . 'assets/js/dior-patient-static-actions.js') : DIOR_PORTAL_VERSION,
            true
        );
        wp_enqueue_script(
            'apexcharts',
            DIOR_PORTAL_URL . 'assets/js/apexcharts.min.js',
            [],
            '3.48.0',
            true
        );
        wp_enqueue_script(
            'dior-doctor-dashboard-page-js',
            DIOR_PORTAL_URL . 'assets/js/dior-doctor-dashboard-page.js',
            ['dior-doctor-js'],
            file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-doctor-dashboard-page.js') ? filemtime(DIOR_PORTAL_PATH . 'assets/js/dior-doctor-dashboard-page.js') : DIOR_PORTAL_VERSION,
            true
        );
        // Note: dior-static-table-interactions CSS is now merged into dior-patient.css and dior-doctor.css
        wp_enqueue_script(
            'dior-static-table-interactions',
            DIOR_PORTAL_URL . 'assets/js/dior-static-table-interactions.js',
            ['jquery'],
            file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-static-table-interactions.js') ? filemtime(DIOR_PORTAL_PATH . 'assets/js/dior-static-table-interactions.js') : DIOR_PORTAL_VERSION,
            true
        );

        // ── DocBooker Booking Modal Controller ─────────────────────────────
        $db_modal_js_ver = file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-docbooker-modal.js') ? filemtime(DIOR_PORTAL_PATH . 'assets/js/dior-docbooker-modal.js') : DIOR_PORTAL_VERSION;
        wp_enqueue_script(
            'dior-docbooker-modal-js',
            DIOR_PORTAL_URL . 'assets/js/dior-docbooker-modal.js',
            ['jquery', 'sweetalert2'],
            $db_modal_js_ver,
            true
        );

        // ── FDA Prescription Modal Controller ────────────────────────────────
        $fda_modal_js_ver = file_exists(DIOR_PORTAL_PATH . 'assets/js/dior-fda-prescription-modal.js') ? filemtime(DIOR_PORTAL_PATH . 'assets/js/dior-fda-prescription-modal.js') : DIOR_PORTAL_VERSION;
        wp_enqueue_script(
            'dior-fda-prescription-modal-js',
            DIOR_PORTAL_URL . 'assets/js/dior-fda-prescription-modal.js',
            ['jquery', 'sweetalert2'],
            $fda_modal_js_ver,
            true
        );
        wp_localize_script('dior-fda-prescription-modal-js', 'dior_fda_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dior_doctor_nonce'),
        ]);

        $portal_url = home_url('/patient-dashboard/');
        $login_url = home_url('/dior-login/');
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
        wp_localize_script('dior-patient-live', 'dior_patient_live_vars', [
            'ajax_url' => $localize_data['ajax_url'],
            'nonce' => $localize_data['nonce'],
        ]);

        $patient_page_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => esc_url_raw(rest_url()),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'portal_nonce' => wp_create_nonce('dior_portal_nonce'),
            'dashboard_url' => home_url('/patient-dashboard/'),
            'refactor_css_url' => DIOR_PORTAL_URL . 'assets/css/dior-custom.css',
            'patient' => [
                'name' => $profile_data['full_name'] ?? '',
                'email' => $current_user_id ? (wp_get_current_user()->user_email ?? '') : '',
                'phone' => $profile_data['phone'] ?? '',
            ],
            'appointments' => $current_user_id ? Dior_Patient_Portal_Data::get_patient_appointments($current_user_id) : [],
            'prescriptions' => $current_user_id ? Dior_Patient_Portal_Data::get_patient_prescriptions($current_user_id) : [],
        ];
        wp_localize_script('dior-patient-dashboard-page-js', 'dior_patient_dashboard', $patient_page_data);
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
                'doctor_profile' => $doc_profile ?: [],
                'dashboard_data' => ($is_doctor_user && class_exists('Dior_Doctor_Dashboard'))
                    ? Dior_Doctor_Dashboard::get_dashboard_data($current_user_id)
                    : [],
                'refactor_css_url' => DIOR_PORTAL_URL . 'assets/css/dior-custom.css'
            ]);
        }
    }

    /**
     * Ensure /dior-login/, /diro-registration/, /patient-dashboard/, and /book-visit/ pages exist
     */
    public static function ensure_pages_exist()
    {
        if (get_option('dior_portal_pages_ensured_v1', false)) {
            return;
        }

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

        // Login Page (/dior-login/)
        $login_page = get_page_by_path('dior-login');
        if (!$login_page) {
            $old_login = get_page_by_path('login');
            if ($old_login) {
                wp_update_post([
                    'ID' => $old_login->ID,
                    'post_name' => 'dior-login'
                ]);
                $login_id = $old_login->ID;
            } else {
                $login_id = wp_insert_post([
                    'post_title' => 'Patient Login',
                    'post_name' => 'dior-login',
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

        update_option('dior_portal_pages_ensured_v1', true, false);
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
        if (is_page(['dior-login', 'diro-registration', 'diro-lost-password', 'diro-reset-password', 'patient-portal-landing-page'])) {
            return $template;
        }

        global $post;
        if ($post && in_array($post->post_name, ['dior-login', 'diro-registration', 'diro-lost-password', 'diro-reset-password', 'patient-portal-landing-page'])) {
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
                    html = '<p>No services are currently available.</p>';
                }
                document.getElementById('treatments-list').innerHTML = html;
            })
            .catch(() => {
                document.getElementById('treatments-list').innerHTML = '<p>Services could not be loaded. Please try again later.</p>';
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
                    html = '<p>No providers are currently available for this service.</p>';
                }
                document.getElementById('doctors-list').innerHTML = html;
            })
            .catch(() => {
                document.getElementById('doctors-list').innerHTML = '<p>Providers could not be loaded. Please try again later.</p>';
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

        function renderNoAvailability(message) {
            const slots = document.getElementById('time-slots-list');
            if (slots) slots.innerHTML = '<p>' + message + '</p>';
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
                    renderNoAvailability('No available time slots were returned for this date. Please choose another date.');
                }
            })
            .catch(() => {
                renderNoAvailability('No available time slots were returned for this date. Please choose another date.');
            });
    }

    function diorSelectTime(clinicId, time) {
        diorBookingState.clinicId = clinicId;
        diorBookingState.time = time;

        document.getElementById('booking-summary').innerHTML = `
                                                                                                            <p><strong>Treatment:</strong> ${diorBookingState.departmentName || '—'}</p>
                                                                                                            <p><strong>Doctor:</strong> ${diorBookingState.doctorName || '—'}</p>
                                                                                                            <p><strong>Date:</strong> ${diorBookingState.date}</p>
                                                                                                            <p><strong>Time:</strong> ${diorBookingState.time}</p>
                                                                                                            <p><strong>Patient:</strong> ${diorBookingState.patient.name || '—'}</p>
                                                                                                        `;
        diorBookingGoToStep(4);
    }

    function diorSubmitBooking() {
        let btn = document.getElementById('btn-confirm-booking');
        let msg = document.getElementById('booking-message');
        if (!btn || !msg) return;
        if (!diorBookingState.doctorId || !diorBookingState.date || !diorBookingState.time) {
            msg.style.color = '#DC2626';
            msg.innerText = 'Please select an available provider, date, and time slot before booking.';
            return;
        }
        btn.disabled = true;
        btn.innerText = 'Booking Consultation...';

        const formData = new FormData();
        formData.append('action', 'dior_patient_book_appointment');
        formData.append('nonce', diorPortalNonce);
        formData.append('doctorId', diorBookingState.doctorId || '');
        formData.append('doctor_name', diorBookingState.doctorName || '');
        formData.append('date', diorBookingState.date || '');
        formData.append('time', diorBookingState.time || '');
        formData.append('condition', diorBookingState.departmentName || '');
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
                <a href="<?php echo esc_url($dash_url); ?>"
                    class="dior-btn-auth-primary"><?php echo esc_html($dash_label); ?></a>
                <a href="<?php echo esc_url(wp_logout_url(home_url('/dior-login/'))); ?>"
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
                onclick="Swal.fire({ icon: 'info', title: 'Google Login', text: 'Google Login will be implemented once Client ID is available.' });">
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

            <div class="dior-form-group" style="margin-top: 15px;">
                <label>Are you currently experiencing a medical emergency, severe symptoms, or a potentially
                    life-threatening condition?</label>
                <div style="display:flex; gap:15px; margin-top:5px;">
                    <label class="dior-reg-radio-label"><input type="radio" name="medical_emergency" value="yes"
                            class="dior-reg-radio-input"> <span>Yes</span></label>
                    <label class="dior-reg-radio-label"><input type="radio" name="medical_emergency" value="no" required
                            checked class="dior-reg-radio-input"> <span>No</span></label>
                </div>
            </div>

            <div class="dior-form-group" id="dior-reg-pregnancy-wrap" style="display:none; margin-top: 15px;">
                <label>Are you currently pregnant?</label>
                <div style="display:flex; gap:15px; margin-top:5px;">
                    <label class="dior-reg-radio-label"><input type="radio" name="is_pregnant" value="yes"
                            class="dior-reg-radio-input"> <span>Yes</span></label>
                    <label class="dior-reg-radio-label"><input type="radio" name="is_pregnant" value="no" checked
                            class="dior-reg-radio-input"> <span>No</span></label>
                </div>
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
            <p>Already have an account? <a href="<?php echo esc_url(home_url('/dior-login/')); ?>">Log in here</a></p>
        </div>

        <?php endif; ?>
    </div>
</div>

<style>
    .dior-hipaa-swal.swal2-popup {
        max-width: 950px !important;
        padding: 0 !important;
        border-radius: 12px !important;
        overflow: hidden;
    }

    .dior-hipaa-swal .swal2-title {
        padding: 15px 15px 10px !important;
        margin: 0 !important;
    }

    .dior-hipaa-swal .swal2-html-container {
        padding: 0 !important;
        margin: 0 !important;
    }

    .dior-hipaa-swal .swal2-close {
        background: transparent !important;
        background-color: transparent !important;
        color: #666 !important;
        font-size: 32px !important;
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
        width: 40px !important;
        height: 40px !important;
        border-radius: 50% !important;
        position: absolute !important;
        top: 5px !important;
        right: 5px !important;
    }

    .dior-hipaa-swal .swal2-close:hover {
        color: #d33 !important;
        background-color: transparent !important;
    }
</style>

<!-- Hidden Intake Form Content for Success Popup --><!-- Hidden Intake Form Overlay -->
<div id="dior-custom-hipaatizer-modal"
    style="display:none; position:fixed; inset:0; width:100vw; height:100vh; background:rgba(0,0,0,0.8); z-index:999999; justify-content:center; align-items:center;">
    <div
        style="width:min(950px, 95vw); height:90vh; background:#fff; border-radius:12px; position:relative; overflow:hidden;">
        <button type="button" onclick="diorCloseHipaaModal();" aria-label="Close HIPAAtizer form"
            style="position:absolute; right:15px; top:10px; font-size:32px; line-height:1; color:#666; background:#fff; border:none; cursor:pointer; z-index:10000;">&times;</button>
        <div id="hipaatizer-inject-target" style="width:100%; height:100%; padding:20px; box-sizing:border-box;">
            <iframe id="dior-hipaatizer-intake-frame" title="HIPAAtizer Clinical Intake & Questionnaire Form"
                src="about:blank" data-src="https://app.hipaatizer.com/workflow/01a11515-b491-79dc-b59b-e4be2a345a49"
                allow="microphone; camera; payment"
                style="display:block; width:100%; height:100%; min-height:700px; border:0; background:#fff;"
                loading="eager"></iframe>
        </div>
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
                <p>Remember your password? <a href="<?php echo esc_url(home_url('/dior-login/')); ?>">Back to Sign In
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
            <a href="<?php echo esc_url(home_url('/dior-login/')); ?>" class="dior-btn-auth-secondary"
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
                <a href="<?php echo esc_url(home_url('/dior-login/')); ?>"
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

        $login_url = home_url('/dior-login/');

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
    public static function render_patient_dashboard($atts = [])
    {
        // If not logged in, redirect directly to login page
        if (!is_user_logged_in()) {
            $login_url = home_url('/dior-login/');
            if (!headers_sent()) {
                wp_safe_redirect($login_url);
                exit;
            }
            return '<meta http-equiv="refresh" content="0;url=' . esc_url($login_url) . '">';
        }

        self::enqueue_assets();

        $logo_url = self::get_logo_url();
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $profile = Dior_Patient_Portal_Data::get_patient_profile($user_id);
        $appointments = Dior_Patient_Portal_Data::get_patient_appointments($user_id);
        $encounters = Dior_Patient_Portal_Data::get_patient_encounters($user_id);
        $prescriptions = Dior_Patient_Portal_Data::get_patient_prescriptions($user_id);
        $payments = Dior_Patient_Portal_Data::get_patient_payments($user_id);
        $documents = Dior_Patient_Portal_Data::get_patient_documents($user_id);
        $questionnaires = Dior_Patient_Portal_Data::get_patient_questionnaires($user_id);
        $notifications = Dior_Patient_Portal_Data::get_patient_notifications($user_id);
        $hipaa_intake = Dior_Patient_Portal_Data::get_normalized_hipaa_intake($user_id); // HIPAAtizer normalized submitted data

        // Normalize database-backed collections. Empty database results stay empty;
        // the dashboard must never present fabricated records as real patient data.
        $dior_demo_mode = false;
        $appointments = is_array($appointments) ? $appointments : [];
        $encounters = is_array($encounters) ? $encounters : [];
        $prescriptions = is_array($prescriptions) ? $prescriptions : [];
        $payments = is_array($payments) ? $payments : [];
        $documents = is_array($documents) ? $documents : [];
        $questionnaires = is_array($questionnaires) ? $questionnaires : [];
        $notifications = is_array($notifications) ? $notifications : [];

        $parse_appt_ts = function ($apt) {
            $raw_date = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
            $raw_time = !empty($apt['time']) ? $apt['time'] : (!empty($apt['appt_time']) ? $apt['appt_time'] : '');
            $ts = strtotime($raw_date);
            $ymd = $ts ? date('Y-m-d', $ts) : '';
            if (!$ymd)
                return 0;
            $time_part = '00:00:00';
            if (preg_match('/(\d{1,2}:\d{2}\s*(?:AM|PM)?)/i', $raw_time, $m)) {
                $t_ts = strtotime($m[1]);
                if ($t_ts)
                    $time_part = date('H:i:s', $t_ts);
            }
            return strtotime("$ymd $time_part");
        };

        // Calculate stats & separate upcoming vs past dynamically
        $today_ymd = current_time('Y-m-d');
        $now_ts = current_time('timestamp');
        $upcoming_list = [];
        $past_list = [];
        foreach ($appointments as $apt) {
            $raw_date = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
            $ts = strtotime($raw_date);
            $ymd = $ts ? date('Y-m-d', $ts) : $raw_date;
            $status = strtolower($apt['status'] ?? '');
            $appt_ts = $parse_appt_ts($apt);

            $is_past_status = in_array($status, ['completed', 'done', 'cancelled', 'no show', 'canceled', 'past'], true);
            $is_active_status = in_array($status, ['confirmed', 'scheduled', 'in-queue', 'pending', 'in progress', 'active'], true);

            // An appointment is past if it has a past status OR if its date/time has passed (unless currently 'in progress')
            $is_past = false;
            if ($is_past_status) {
                $is_past = true;
            } elseif ($status !== 'in progress') {
                if ($ymd && $ymd < $today_ymd) {
                    $is_past = true;
                } elseif ($appt_ts && $appt_ts < $now_ts) {
                    $is_past = true;
                }
            }

            if ($is_past) {
                $past_list[] = $apt;
            } elseif ($is_active_status) {
                $upcoming_list[] = $apt;
            } else {
                $past_list[] = $apt;
            }
        }

        // Sort upcoming chronologically ascending (nearest appointment in future first)
        usort($upcoming_list, function ($a, $b) use ($parse_appt_ts) {
            return $parse_appt_ts($a) <=> $parse_appt_ts($b);
        });

        // Sort past chronologically descending (most recent past consultation first)
        usort($past_list, function ($a, $b) use ($parse_appt_ts) {
            return $parse_appt_ts($b) <=> $parse_appt_ts($a);
        });

        $next_appointment = !empty($upcoming_list) ? $upcoming_list[0] : null;

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
<?php include DIOR_PORTAL_PATH . 'templates/patient-dashboard.php'; ?>
<?php
                return ob_get_clean();
    }
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
        } elseif (!empty($redirect_to) && strpos($redirect_to, 'dior-login') === false && strpos($redirect_to, 'diro-registration') === false && strpos($redirect_to, 'doctor-dashboard') === false) {
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

        // New patients must complete the HIPAAtizer intake before opening the dashboard.
        // The Start Your Visit page is created and managed manually by the site owner.
        wp_send_json_success([
            'message' => 'Account created successfully! Redirecting you to Start Your Visit...',
            'redirect' => home_url('/start-your-visit/')
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
                    <td style="padding: 32px 32px 20px; text-align: center; background: linear-gradient(135deg, #0B1030 0%, #00346C 100%);">
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
            'redirect' => home_url('/dior-login/?reset=success')
        ]);
    }

    /**
     * Handle Appointment Booking from the Standalone Page
     */
    public static function handle_book_appointment_from_page()
    {
        if (!isset($_POST['doctor_id']) || !isset($_POST['date']) || !isset($_POST['time'])) {
            wp_send_json_error('Missing required booking details.');
        }

        $doctor_id = intval($_POST['doctor_id']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : 'Telehealth Consultation';

        // Enforce 15-minute advance slot booking rule
        if (!self::is_slot_bookable_15min($date, $time, 15)) {
            wp_send_json_error('This appointment time slot is no longer available. Appointments must be scheduled at least 15 minutes prior to the start time.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dior_appointments';
        $patient_id = get_current_user_id() ?: 0;
        $appt_uid = 'APT-' . ($patient_id ?: '0') . rand(100, 999);
        $now = current_time('mysql');

        $inserted = $wpdb->insert($table, [
            'appt_uid' => $appt_uid,
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id ?: 1957,
            'condition_name' => $reason ?: 'Telehealth Consultation',
            'visit_type' => 'Video Visit (HD)',
            'status' => 'Pending',
            'appt_date' => $date,
            'appt_time' => $time,
            'join_url' => 'https://zoom.us/join',
            'payment_status' => 'Pending',
            'notes' => $reason,
            'created_at' => $now,
            'updated_at' => $now,
            'is_deleted' => 0
        ]);

        if ($inserted === false) {
            wp_send_json_error('Database error: Unable to record appointment.');
        }

        wp_send_json_success([
            'message' => 'Appointment successfully booked.',
            'appt_uid' => $appt_uid
        ]);
    }

    /**
     * AJAX Logout Handler
     */
    public static function handle_logout()
    {
        wp_logout();
        wp_send_json_success([
            'redirect' => home_url('/dior-login/')
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
        $blood_group = trim(sanitize_text_field($_POST['blood_group'] ?? ''));
        $city = trim(sanitize_text_field($_POST['city'] ?? ''));
        $state = trim(sanitize_text_field($_POST['state'] ?? ''));
        $zip = trim(sanitize_text_field($_POST['zip'] ?? ''));
        $country = trim(sanitize_text_field($_POST['country'] ?? 'United States of America'));

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
        update_user_meta($user_id, 'blood_group', $blood_group);
        update_user_meta($user_id, 'city', $city);
        update_user_meta($user_id, 'state', $state);
        update_user_meta($user_id, 'zip', $zip);
        update_user_meta($user_id, 'zip_code', $zip);
        update_user_meta($user_id, 'country', $country);

        // Height & Weight metrics
        if (isset($_POST['height'])) {
            update_user_meta($user_id, 'height', sanitize_text_field(trim($_POST['height'])));
        }
        if (isset($_POST['weight'])) {
            update_user_meta($user_id, 'weight', sanitize_text_field(trim($_POST['weight'])));
        }

        Dior_Patient_Portal_Data::sync_relational_patient($user_id);
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
     * Save patient feedback and notify the selected doctor.
     */
    public static function ajax_submit_feedback()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $patient_id = get_current_user_id();
        if (!$patient_id) {
            wp_send_json_error(['message' => 'Please sign in before submitting feedback.'], 401);
        }

        $subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
        $comment = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        $rating = absint($_POST['rating'] ?? 0);

        if ($subject === '' || $comment === '') {
            wp_send_json_error(['message' => 'Please enter a subject and feedback message.'], 400);
        }
        if ($rating < 1 || $rating > 5) {
            wp_send_json_error(['message' => 'Please choose a rating from 1 to 5 stars.'], 400);
        }

        $doctor_id = 0;
        $is_assigned_doctor = false;
        foreach (Dior_Appointment_Service::get_patient_appointments($patient_id) as $appointment) {
            $assigned_doctor_id = (int) ($appointment['doctor_user_id'] ?? 0);
            if ($assigned_doctor_id && get_userdata($assigned_doctor_id)) {
                $doctor_id = $assigned_doctor_id;
                $is_assigned_doctor = true;
                break;
            }
        }
        if (!$doctor_id) {
            if (class_exists('Dior_Doctor_Resolver')) {
                $doctor_id = Dior_Doctor_Resolver::get_default_doctor_id();
            }
            if (!$doctor_id) {
                $doctors = get_users(['role__in' => ['doctor', 'administrator'], 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1, 'fields' => 'ID']);
                $doctor_id = !empty($doctors) ? (int) $doctors[0] : 1;
            }
        }

        $doctor = $doctor_id ? get_userdata($doctor_id) : false;
        if (!$doctor) {
            $doctor = get_user_by('id', 1);
            $doctor_id = $doctor ? (int) $doctor->ID : 1;
        }
        if (!$doctor) {
            wp_send_json_error(['message' => 'No provider is linked to your account yet. Please contact support.'], 403);
        }

        global $wpdb;
        $reviews_table = $wpdb->prefix . 'dior_reviews';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $reviews_table)) !== $reviews_table) {
            wp_send_json_error(['message' => 'Feedback storage is temporarily unavailable. Please try again later.'], 500);
        }

        $now = current_time('mysql');
        $inserted = $wpdb->insert($reviews_table, [
            'review_uid' => 'REV-' . wp_generate_uuid4(),
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'subject' => $subject,
            'rating' => $rating,
            'comment' => $comment,
            'status' => 'Pending',
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s']);

        if (!$inserted) {
            wp_send_json_error(['message' => 'Your feedback could not be saved. Please try again.'], 500);
        }

        $patient = get_userdata($patient_id);
        $patient_name = $patient ? $patient->display_name : 'A patient';
        $doctor_name = trim($doctor->first_name . ' ' . $doctor->last_name) ?: $doctor->display_name;
        $doctor_name = preg_replace('/^(?:dr\.?\s*)+/i', '', $doctor_name);
        $doctor_name = 'Dr. ' . $doctor_name;
        if (class_exists('Dior_Notification_Service')) {
            Dior_Notification_Service::add_dashboard_notification(
                $doctor_id,
                'New Patient Feedback',
                $patient_name . ' submitted a ' . $rating . '-star review. Open Patient Reviews to view it.',
                home_url('/doctor-dashboard/#tab=doc-patient-review'),
                'fa-star',
                true
            );
        }

        wp_send_json_success([
            'message' => 'Thank you. Your feedback was sent to your doctor.',
            'review' => [
                'id' => (int) $wpdb->insert_id,
                'subject' => $subject,
                'doctor' => $doctor_name,
                'rating' => $rating,
                'message' => $comment,
                'date' => wp_date('M j, Y', current_time('timestamp')),
                'status' => 'Pending',
            ],
        ]);
    }

    /**
     * Update existing patient feedback
     */
    public static function ajax_update_feedback()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $patient_id = get_current_user_id();
        if (!$patient_id) {
            wp_send_json_error(['message' => 'Please sign in before updating feedback.'], 401);
        }

        $feedback_id = absint($_POST['feedback_id'] ?? 0);
        if (!$feedback_id) {
            wp_send_json_error(['message' => 'Invalid feedback ID.'], 400);
        }

        $subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
        $comment = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        $rating = absint($_POST['rating'] ?? 0);

        if ($subject === '' || $comment === '') {
            wp_send_json_error(['message' => 'Please enter a subject and feedback message.'], 400);
        }
        if ($rating < 1 || $rating > 5) {
            wp_send_json_error(['message' => 'Please choose a rating from 1 to 5 stars.'], 400);
        }

        global $wpdb;
        $reviews_table = $wpdb->prefix . 'dior_reviews';

        // Verify ownership
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$reviews_table} WHERE id = %d AND patient_id = %d",
            $feedback_id,
            $patient_id
        ));

        if (!$existing) {
            wp_send_json_error(['message' => 'Feedback not found or you do not have permission to edit it.'], 403);
        }

        $now = current_time('mysql');
        $updated = $wpdb->update($reviews_table, [
            'subject' => $subject,
            'rating' => $rating,
            'comment' => $comment,
            'updated_at' => $now,
        ], ['id' => $feedback_id, 'patient_id' => $patient_id], ['%s', '%d', '%s', '%s'], ['%d', '%d']);

        if ($updated === false) {
            wp_send_json_error(['message' => 'Your feedback could not be updated. Please try again.'], 500);
        }

        $doctor = get_userdata($existing->doctor_id);
        $doctor_name = trim($doctor->first_name . ' ' . $doctor->last_name) ?: $doctor->display_name;
        $doctor_name = preg_replace('/^(?:dr\.?\s*)+/i', '', $doctor_name);
        $doctor_name = 'Dr. ' . $doctor_name;

        wp_send_json_success([
            'message' => 'Feedback updated successfully.',
            'review' => [
                'id' => $feedback_id,
                'subject' => $subject,
                'doctor' => $doctor_name,
                'rating' => $rating,
                'message' => $comment,
                'date' => wp_date('M j, Y', strtotime($existing->created_at)),
                'status' => $existing->status,
            ],
        ]);
    }

    /**
     * Delete patient feedback
     */
    public static function ajax_delete_feedback()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $patient_id = get_current_user_id();
        if (!$patient_id) {
            wp_send_json_error(['message' => 'Please sign in before deleting feedback.'], 401);
        }

        $feedback_id = absint($_POST['feedback_id'] ?? 0);
        if (!$feedback_id) {
            wp_send_json_error(['message' => 'Invalid feedback ID.'], 400);
        }

        global $wpdb;
        $reviews_table = $wpdb->prefix . 'dior_reviews';

        // Verify ownership
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$reviews_table} WHERE id = %d AND patient_id = %d",
            $feedback_id,
            $patient_id
        ));

        if (!$existing) {
            wp_send_json_error(['message' => 'Feedback not found or you do not have permission to delete it.'], 403);
        }

        $deleted = $wpdb->delete($reviews_table, ['id' => $feedback_id, 'patient_id' => $patient_id], ['%d', '%d']);

        if ($deleted === false) {
            wp_send_json_error(['message' => 'Your feedback could not be deleted. Please try again.'], 500);
        }

        wp_send_json_success([
            'message' => 'Feedback deleted successfully.',
        ]);
    }

    /**
     * Bulk delete patient feedback
     */
    public static function ajax_bulk_delete_feedback()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $patient_id = get_current_user_id();
        if (!$patient_id) {
            wp_send_json_error(['message' => 'Please sign in before deleting feedback.'], 401);
        }

        $ids = array_map('intval', (array) ($_POST['feedback_ids'] ?? []));
        if (empty($ids)) {
            wp_send_json_error(['message' => 'No feedback records selected.'], 400);
        }

        global $wpdb;
        $reviews_table = $wpdb->prefix . 'dior_reviews';
        $deleted = 0;
        foreach ($ids as $id) {
            $ok = $wpdb->delete($reviews_table, ['id' => $id, 'patient_id' => $patient_id], ['%d', '%d']);
            if ($ok)
                $deleted++;
        }

        wp_send_json_success(['message' => "$deleted feedback record(s) deleted."]);
    }

    /**
     * Helper to synchronize relational patient record safely
     */
    public static function sync_relational_patient($user_id)
    {
        if (class_exists('Dior_Patient_Portal_Data') && method_exists('Dior_Patient_Portal_Data', 'sync_relational_patient')) {
            return Dior_Patient_Portal_Data::sync_relational_patient($user_id);
        }
        return false;
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

            if (empty($avatar_url)) {
                wp_send_json_error(['message' => 'Invalid image URL provided.']);
            }

            try {
                update_user_meta($user_id, 'dior_profile_image', $avatar_url);
                update_user_meta($user_id, 'profile_image', $avatar_url);
                if ($attach_id) {
                    update_user_meta($user_id, 'dior_profile_image_id', $attach_id);
                }

                global $wpdb;
                $patients_table = $wpdb->prefix . 'dior_patients';
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $patients_table)) === $patients_table) {
                    $wpdb->update(
                        $patients_table,
                        ['avatar_url' => $avatar_url, 'updated_at' => current_time('mysql')],
                        ['user_id' => $user_id],
                        ['%s', '%s'],
                        ['%d']
                    );
                }

                self::sync_relational_patient($user_id);

                wp_send_json_success([
                    'message' => 'Profile photo selected from Media Library successfully!',
                    'avatar_url' => $avatar_url,
                    'attachment_id' => $attach_id
                ]);
            } catch (\Throwable $e) {
                wp_send_json_error(['message' => 'Failed to save profile photo: ' . $e->getMessage()]);
            }
        }

        if (empty($_FILES['avatar']) || empty($_FILES['avatar']['name'])) {
            wp_send_json_error(['message' => 'No image file provided for upload.']);
        }

        $file = $_FILES['avatar'];

        // Return a clear JSON error for PHP upload-layer failures.
        if (isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'The image exceeds the server upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE => 'The image exceeds the form upload size limit.',
                UPLOAD_ERR_PARTIAL => 'The image was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_FILE => 'Please choose an image file.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload folder is missing.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded image. Check folder permissions.',
                UPLOAD_ERR_EXTENSION => 'The server blocked this image upload.',
            ];
            $code = (int) $file['error'];
            wp_send_json_error(['message' => $upload_errors[$code] ?? 'The image upload failed on the server.']);
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(['message' => 'The uploaded image could not be verified by the server. Please try again.']);
        }

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
        $uploaded_file_path = '';
        $attach_id = 0;

        try {
            if ($movefile && !isset($movefile['error']) && !empty($movefile['url'])) {
                $avatar_url = esc_url_raw($movefile['url']);
                $uploaded_file_path = !empty($movefile['file']) ? $movefile['file'] : '';
            } else {
                // Bulletproof fallback using wp_upload_dir() & move_uploaded_file / copy
                $upload_dir = wp_upload_dir();
                $safe_name = wp_unique_filename($upload_dir['path'], sanitize_file_name($file['name']));
                $target_path = $upload_dir['path'] . '/' . $safe_name;

                if (@move_uploaded_file($file['tmp_name'], $target_path) || @copy($file['tmp_name'], $target_path)) {
                    $avatar_url = esc_url_raw($upload_dir['url'] . '/' . $safe_name);
                    $uploaded_file_path = $target_path;
                } else {
                    $err = !empty($movefile['error']) ? $movefile['error'] : 'Failed to save uploaded image. Please check server folder permissions.';
                    wp_send_json_error(['message' => $err]);
                }
            }

            if (empty($avatar_url) || empty($uploaded_file_path) || !file_exists($uploaded_file_path)) {
                if (!empty($uploaded_file_path) && file_exists($uploaded_file_path)) {
                    @unlink($uploaded_file_path);
                }
                wp_send_json_error(['message' => 'Image could not be saved to disk.']);
            }

            // Store into WordPress Media Library as an attachment
            $wp_filetype = wp_check_filetype(basename($uploaded_file_path), null);
            $attachment = [
                'post_mime_type' => $wp_filetype['type'] ?: 'image/jpeg',
                'post_title' => sanitize_text_field(preg_replace('/\.[^.]+$/', '', basename($uploaded_file_path))),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_author' => $user_id
            ];
            $attach_id = wp_insert_attachment($attachment, $uploaded_file_path);
            if (!is_wp_error($attach_id) && $attach_id > 0) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded_file_path);
                wp_update_attachment_metadata($attach_id, $attach_data);
                update_user_meta($user_id, 'dior_profile_image_id', $attach_id);
            }

            update_user_meta($user_id, 'dior_profile_image', $avatar_url);
            update_user_meta($user_id, 'profile_image', $avatar_url);

            // Keep relational database table synchronized
            global $wpdb;
            $patients_table = $wpdb->prefix . 'dior_patients';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $patients_table)) === $patients_table) {
                $wpdb->update(
                    $patients_table,
                    ['avatar_url' => $avatar_url, 'updated_at' => current_time('mysql')],
                    ['user_id' => $user_id],
                    ['%s', '%s'],
                    ['%d']
                );
            }

            self::sync_relational_patient($user_id);

            wp_send_json_success([
                'message' => 'Profile photo updated successfully!',
                'avatar_url' => $avatar_url,
                'attachment_id' => $attach_id
            ]);
        } catch (\Throwable $e) {
            // Clean up uploaded file and attachment if an error occurred during processing
            if (!empty($uploaded_file_path) && file_exists($uploaded_file_path)) {
                @unlink($uploaded_file_path);
            }
            if (!empty($attach_id) && $attach_id > 0) {
                wp_delete_attachment($attach_id, true);
            }
            wp_send_json_error(['message' => 'Upload failed: ' . $e->getMessage()]);
        }
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

        try {
            // Support selecting directly from WordPress Media Library (URL or ID passed)
            if (!empty($_POST['signature_url'])) {
                $sig_url = esc_url_raw($_POST['signature_url']);
                $attach_id = !empty($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

                if (empty($sig_url)) {
                    wp_send_json_error(['message' => 'Invalid signature URL provided.']);
                }

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
                    if ($decoded !== false && strlen($decoded) > 0) {
                        $upload_dir = wp_upload_dir();
                        $filename = 'patient_sig_' . $user_id . '_' . time() . '.png';
                        $filepath = $upload_dir['path'] . '/' . $filename;
                        $bytes = @file_put_contents($filepath, $decoded);
                        if ($bytes === false || $bytes === 0 || !file_exists($filepath)) {
                            wp_send_json_error(['message' => 'Could not save signature image file to disk.']);
                        }
                        $sig_url = esc_url_raw($upload_dir['url'] . '/' . $filename);

                        update_user_meta($user_id, 'dior_patient_signature', $sig_url);
                        wp_send_json_success([
                            'message' => 'Patient signature saved successfully!',
                            'signature_url' => $sig_url
                        ]);
                    } else {
                        wp_send_json_error(['message' => 'Invalid signature image data received.']);
                    }
                } else {
                    wp_send_json_error(['message' => 'Invalid signature format.']);
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

            if ($movefile && !isset($movefile['error']) && !empty($movefile['url'])) {
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
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Signature upload failed: ' . $e->getMessage()]);
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
     * Parse slot start time from string (e.g. "09:00 AM - 10:00 AM", "02:00 PM", "14:00")
     */
    public static function parse_slot_start_time($time_str)
    {
        if (empty($time_str)) {
            return '';
        }
        $parts = preg_split('/\s*(-|–|to)\s*/i', trim($time_str));
        return trim($parts[0] ?? '');
    }

    /**
     * Check if an appointment slot is bookable with a minimum advance notice (default: 15 minutes)
     * - Past dates: blocked (false)
     * - Future dates: available (true)
     * - Today: slot start time must be >= $min_advance_minutes (15 mins) from current time
     */
    public static function is_slot_bookable_15min($date, $time, $min_advance_minutes = 15)
    {
        if (empty($date) || empty($time)) {
            return false;
        }

        $date = trim(sanitize_text_field($date));
        $today_ymd = current_time('Y-m-d');

        // Past dates are strictly unavailable
        if ($date < $today_ymd) {
            return false;
        }

        // Future dates (tomorrow and beyond) are always >= 15 minutes ahead
        if ($date > $today_ymd) {
            return true;
        }

        // Today: parse start time of the slot
        $start_time = self::parse_slot_start_time($time);
        if (empty($start_time)) {
            return false;
        }

        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(date_default_timezone_get());

        $slot_dt = \DateTimeImmutable::createFromFormat('Y-m-d h:i A', $date . ' ' . $start_time, $tz);
        if (!$slot_dt) {
            $slot_dt = \DateTimeImmutable::createFromFormat('Y-m-d g:i A', $date . ' ' . $start_time, $tz);
        }
        if (!$slot_dt) {
            $slot_dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $start_time, $tz);
        }
        if (!$slot_dt) {
            $parsed_ts = strtotime($date . ' ' . $start_time);
            if ($parsed_ts === false) {
                return false;
            }
            $slot_dt = (new \DateTimeImmutable())->setTimestamp($parsed_ts)->setTimezone($tz);
        }

        $now = new \DateTimeImmutable('now', $tz);
        $diff_seconds = $slot_dt->getTimestamp() - $now->getTimestamp();

        return ($diff_seconds >= ($min_advance_minutes * 60));
    }

    /**
     * Get doctor slots for given date, evaluating 15-minute advance booking and existing bookings
     */
    public static function get_doctor_slots_for_date($doctor_id = 1957, $date = '')
    {
        if (empty($date)) {
            $date = current_time('Y-m-d');
        }

        // Attempt reading schedule from DocBooker post meta
        $schedules = get_post_meta((int) $doctor_id, 'wpddb_doctor_schedule', true);
        $weekday = date('l', strtotime($date));
        $day_schedule = null;
        if (is_array($schedules)) {
            foreach ($schedules as $item) {
                if (!empty($item['day']) && strcasecmp($item['day'], $weekday) === 0 && !empty($item['available'])) {
                    $day_schedule = $item;
                    break;
                }
            }
        }

        $raw_slots = [];
        if (!empty($day_schedule['clinics']) && is_array($day_schedule['clinics'])) {
            foreach ($day_schedule['clinics'] as $clinic) {
                if (!empty($clinic['timings']) && is_array($clinic['timings'])) {
                    foreach ($clinic['timings'] as $timing) {
                        if (!empty($timing['time'])) {
                            $raw_slots[] = trim($timing['time']);
                        }
                    }
                }
            }
        }

        // Default telehealth slots if no custom schedule defined
        if (empty($raw_slots)) {
            $raw_slots = [
                '09:00 AM - 10:00 AM',
                '10:00 AM - 11:00 AM',
                '11:00 AM - 12:00 PM',
                '01:00 PM - 02:00 PM',
                '02:00 PM - 03:00 PM',
                '03:00 PM - 04:00 PM',
                '04:00 PM - 05:00 PM'
            ];
        }

        // Check booked slots from database
        global $wpdb;
        $booked_lookup = [];

        // 1. Check dior_appointments
        $dior_table = $wpdb->prefix . 'dior_appointments';
        if ($wpdb->get_var("SHOW TABLES LIKE '$dior_table'") === $dior_table) {
            $booked_dior = $wpdb->get_col($wpdb->prepare(
                "SELECT appt_time FROM $dior_table WHERE appt_date = %s AND status NOT IN ('Cancelled', 'No Show') AND is_deleted = 0",
                $date
            ));
            if (!empty($booked_dior)) {
                foreach ($booked_dior as $bt) {
                    $booked_lookup[trim($bt)] = true;
                }
            }
        }

        // 2. Check wpddb_bookings if table exists
        $wpddb_table = $wpdb->prefix . 'wpddb_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$wpddb_table'") === $wpddb_table) {
            $booked_wpddb = $wpdb->get_col($wpdb->prepare(
                "SELECT time FROM $wpddb_table WHERE booking_date = %s AND status = 'approved' AND booking_present_status = 'upcoming'",
                $date
            ));
            if (!empty($booked_wpddb)) {
                foreach ($booked_wpddb as $wt) {
                    $booked_lookup[trim($wt)] = true;
                }
            }
        }

        $slots = [];
        foreach ($raw_slots as $slot_time) {
            $slot_time = trim($slot_time);
            $is_bookable_15m = self::is_slot_bookable_15min($date, $slot_time, 15);
            $is_already_booked = isset($booked_lookup[$slot_time]);

            $is_available = $is_bookable_15m && !$is_already_booked;
            $reason = '';
            if (!$is_bookable_15m) {
                $reason = 'expired';
            } elseif ($is_already_booked) {
                $reason = 'booked';
            }

            $slots[] = [
                'time' => $slot_time,
                'is_available' => $is_available,
                'reason' => $reason
            ];
        }

        return $slots;
    }

    /**
     * REST Endpoint Callback for Doctor Availability
     */
    public static function rest_doctor_availability($request)
    {
        $doctor_id = intval($request->get_param('id') ?: 1957);
        $date = sanitize_text_field($request->get_param('date') ?: current_time('Y-m-d'));

        $slots = self::get_doctor_slots_for_date($doctor_id, $date);

        return new \WP_REST_Response([
            'date' => $date,
            'day' => date('l', strtotime($date)),
            'clinics' => [
                [
                    'id' => 1,
                    'name' => 'Beverly Hills Telehealth Clinic',
                    'timings' => $slots
                ]
            ]
        ], 200);
    }

    /**
     * AJAX Endpoint Callback for Doctor Availability
     */
    public static function ajax_doctor_availability()
    {
        $doctor_id = intval($_GET['doctor_id'] ?? ($_POST['doctor_id'] ?? 1957));
        $date = sanitize_text_field($_GET['date'] ?? ($_POST['date'] ?? current_time('Y-m-d')));

        $slots = self::get_doctor_slots_for_date($doctor_id, $date);

        wp_send_json_success([
            'date' => $date,
            'day' => date('l', strtotime($date)),
            'clinics' => [
                [
                    'id' => 1,
                    'name' => 'Beverly Hills Telehealth Clinic',
                    'timings' => $slots
                ]
            ]
        ]);
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

        // Auto-fill profile fields if provided in booking form so patient is never blocked
        if ($patient_name) {
            $name_parts = explode(' ', trim($patient_name), 2);
            if (!get_user_meta($user_id, 'first_name', true))
                update_user_meta($user_id, 'first_name', $name_parts[0]);
            if (!get_user_meta($user_id, 'last_name', true))
                update_user_meta($user_id, 'last_name', $name_parts[1] ?? '');
        }
        if ($patient_phone && !get_user_meta($user_id, 'phone', true)) {
            update_user_meta($user_id, 'phone', $patient_phone);
        }
        $existing_profile = Dior_Patient_Portal_Data::get_patient_profile($user_id);
        $user_intake = get_user_meta($user_id, 'dior_hipaa_intake', true);
        if (empty($existing_profile['dob']) && is_array($user_intake) && !empty($user_intake['dob'])) {
            update_user_meta($user_id, 'dob', sanitize_text_field($user_intake['dob']));
        }
        if (empty($existing_profile['gender']) && is_array($user_intake) && !empty($user_intake['gender'])) {
            update_user_meta($user_id, 'gender', sanitize_text_field($user_intake['gender']));
        }
        if (empty($existing_profile['address']) && is_array($user_intake) && !empty($user_intake['address'])) {
            update_user_meta($user_id, 'address', sanitize_text_field($user_intake['address']));
        }

        // Validate personal profile completion
        if (!Dior_Patient_Portal_Data::is_profile_complete($user_id)) {
            $missing = Dior_Patient_Portal_Data::get_missing_profile_fields($user_id);
            wp_send_json_error([
                'message' => 'Please complete your personal profile first (' . implode(', ', $missing) . ' required) before booking an appointment.',
                'code' => 'profile_incomplete',
                'missing_fields' => $missing
            ], 400);
        }

        // Mandatory HIPAAtizer Intake Enforcement: Patient must have submitted intake form
        if (!current_user_can('manage_options') && !Dior_Auth_Service::is_doctor()) {
            $user_intake_data = Dior_Patient_Portal_Data::get_normalized_hipaa_intake($user_id);
            $has_submitted_intake = !empty($user_intake_data) && (!empty($user_intake_data['is_submitted']) || !empty($user_intake_data['submitted_at']));
            if (!$has_submitted_intake) {
                wp_send_json_error([
                    'message' => 'Mandatory Step 1 Required: You must complete and submit the HIPAAtizer clinical intake questionnaire before booking an appointment.',
                    'code' => 'intake_required',
                    'step_required' => 1
                ], 400);
            }
        }

        $condition = sanitize_text_field(wp_unslash($_POST['condition'] ?? ($_POST['treatment'] ?? '')));
        $type = sanitize_text_field(wp_unslash($_POST['type'] ?? ($_POST['visit_type'] ?? '')));
        $provider_raw = sanitize_text_field(wp_unslash($_POST['provider'] ?? ($_POST['doctor'] ?? ($_POST['doctor_name'] ?? ''))));
        $doctor_id_raw = absint($_POST['doctorId'] ?? ($_POST['doctor_id'] ?? 0));
        $date = sanitize_text_field(wp_unslash($_POST['date'] ?? ($_POST['bookingDate'] ?? '')));
        $time = sanitize_text_field(wp_unslash($_POST['time'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ($_POST['patient_note'] ?? '')));

        if (!$doctor_id_raw || !$date || !$time || !$condition || !$type) {
            wp_send_json_error([
                'message' => 'Please select an available provider, service, date, time, and visit type before booking.',
                'code' => 'missing_booking_details'
            ], 400);
        }

        // Enforce 15-minute advance slot booking rule
        if (!self::is_slot_bookable_15min($date, $time, 15)) {
            wp_send_json_error([
                'message' => 'This appointment time slot is no longer available. Appointments must be scheduled at least 15 minutes prior to the start time.',
                'code' => 'slot_expired'
            ], 400);
        }

        // Resolve the selected provider against a published provider record or a real doctor account.
        $doctor_user_id = 0;
        $doctor_name = '';
        $doc_post = get_post($doctor_id_raw);
        if ($doc_post && $doc_post->post_type === 'wpddb_doctor' && $doc_post->post_status === 'publish') {
            $doctor_name = get_the_title($doc_post);
            if (class_exists('Dior_Doctor_Resolver')) {
                $doctor_user_id = (int) Dior_Doctor_Resolver::resolve_doctor_user_id($doctor_id_raw);
            }
        } else {
            $candidate_user = get_userdata($doctor_id_raw);
            if ($candidate_user && (in_array('doctor', (array) $candidate_user->roles, true) || in_array('administrator', (array) $candidate_user->roles, true))) {
                $doctor_user_id = (int) $candidate_user->ID;
                $doctor_name = $candidate_user->display_name;
            }
        }
        if (!$doctor_name) {
            wp_send_json_error([
                'message' => 'The selected provider could not be verified. Please choose a provider from the available list.',
                'code' => 'invalid_provider'
            ], 400);
        }

        if (stripos($type, 'phone') !== false) {
            $normalized_patient_phone = preg_replace('/[^0-9+]/', '', (string) $patient_phone);
            if (!$normalized_patient_phone) {
                wp_send_json_error(['message' => 'A valid phone number is required for a phone consultation.'], 400);
            }
            $join_url = 'tel:' . $normalized_patient_phone;
        } else {
            // Only save a meeting URL when the configured integration returns a real link.
            $google_meet_url = class_exists('Dior_Google_Meet') ? Dior_Google_Meet::create_meeting_link() : false;
            $join_url = is_string($google_meet_url) ? esc_url_raw($google_meet_url) : '';
        }

        $new_apt = Dior_Appointment_Service::book_appointment([
            'patient_id' => $user_id,
            'doctor_id' => $doctor_id_raw,
            'doctor_user_id' => $doctor_user_id,
            'provider' => $doctor_name,
            'condition' => $condition,
            'visit_type' => $type,
            'appt_date' => $date,
            'appt_time' => $time,
            'status' => 'Pending',
            'join_url' => $join_url,
            'payment_status' => 'Paid',
            'notes' => $notes
        ]);

        if (is_wp_error($new_apt)) {
            wp_send_json_error(['message' => $new_apt->get_error_message()]);
        }

        // Securely store the optional report uploaded with the appointment.
        if (!empty($_FILES['uploadFile']) && class_exists('Dior_Medical_Secure_Files')) {
            $report = Dior_Medical_Secure_Files::store_uploaded_document(
                $user_id,
                $_FILES['uploadFile'],
                'Appointment Report - ' . $condition,
                'Appointment Report',
                $user_id
            );
            if (is_wp_error($report)) {
                $new_apt['report_upload_warning'] = $report->get_error_message();
            } else {
                $new_apt['report'] = $report;
            }
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
                    'dob' => get_user_meta($user_id, 'dob', true) ?: '1990-01-01',
                    'patient_code' => 'DM-' . (10000 + ($user_id % 90000))
                ]);
                $docbooker_patient_id = $wpdb->insert_id;
            }
            $sync_doc_id = $doctor_id_raw;
            if (!$sync_doc_id) {
                $sync_doc_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpddb_doctor' AND post_status = 'publish' ORDER BY ID ASC LIMIT 1");
                $sync_doc_id = $sync_doc_id ?: 1957;
            }
            $sync_clinic_id = 1970;
            $table_clinics = $wpdb->prefix . 'wpddb_clinics';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_clinics'") == $table_clinics) {
                $found_clinic = $wpdb->get_var("SELECT id FROM $table_clinics LIMIT 1");
                if ($found_clinic)
                    $sync_clinic_id = $found_clinic;
            }

            $wpdb->insert($table_bookings, [
                'booking_id' => 'BK-' . rand(100000, 999999),
                'patient_id' => $docbooker_patient_id ?: $user_id,
                'doctor_id' => $sync_doc_id,
                'clinic_id' => $sync_clinic_id,
                'amount_paid' => 0.00,
                'payment_status' => 'Paid',
                'payment_by' => 'Direct',
                'booking_present_status' => 'upcoming',
                'booking_date' => $date,
                'day' => date('l', strtotime($date)),
                'time' => $time,
                'patient_note' => ($type . ' - ' . ($notes ?: $condition)),
                'status' => 'approved'
            ]);
        }

        // Multi-Channel Notifications: In-App Dashboard & Anti-Spam Luxury HTML Email (with localhost fallback)
        if (class_exists('Dior_Notification_Service') && !empty($new_apt['appt_uid'])) {
            Dior_Notification_Service::send_appointment_confirmation($new_apt['appt_uid']);
        } else {
            // Fallback patient notification if notification service is unavailable
            $notifications = Dior_Patient_Portal_Data::get_patient_notifications($user_id);
            array_unshift($notifications, [
                'id' => 'NOTIF-' . time() . '-' . rand(100, 999),
                'type' => 'appointment',
                'icon' => (stripos($type, 'phone') !== false ? 'fa-phone-volume' : 'fa-video'),
                'title' => 'Appointment Confirmed: ' . $doctor_name,
                'message' => 'Your ' . $type . ' with ' . $doctor_name . ' is confirmed for ' . $date . ' at ' . $time . '.',
                'time' => 'Just now',
                'timestamp' => time(),
                'created_at' => current_time('mysql'),
                'is_read' => false,
                'action_url' => '#tab=appointments'
            ]);
            update_user_meta($user_id, 'dior_notifications', $notifications);
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

        // 1. Add to Patient Appointments ledger. The relational table is canonical;
        // avoid creating a second record when the same booking was already captured by the dashboard form.
        $docbooker_created_appointment = false;
        if ($patient_user_id) {
            global $wpdb;
            $appt_table = $wpdb->prefix . 'dior_appointments';
            $existing_appt = $wpdb->get_var($wpdb->prepare(
                "SELECT appt_uid FROM $appt_table WHERE patient_id = %d AND appt_date = %s AND appt_time = %s AND status NOT IN ('Cancelled','No Show') AND is_deleted = 0 LIMIT 1",
                $patient_user_id,
                sanitize_text_field($date),
                sanitize_text_field($time)
            ));

            if (!$existing_appt) {
                $created_result = Dior_Appointment_Service::book_appointment([
                    'patient_id' => $patient_user_id,
                    'doctor_id' => $docbooker_doctor_id ?: $doctor_user_id,
                    'doctor_user_id' => $doctor_user_id,
                    'provider' => $doctor_name,
                    'condition' => $notes ?: 'General Telehealth Consultation',
                    'visit_type' => 'Video Visit (HD)',
                    'appt_date' => $date,
                    'appt_time' => $time,
                    'status' => 'Pending',
                    'join_url' => 'https://zoom.us/join',
                    'payment_status' => 'Paid',
                    'notes' => $notes,
                    'booking_id' => $booking_id
                ]);
                $docbooker_created_appointment = !is_wp_error($created_result);
            }

            // 2. Add Patient Notification only when this hook created the canonical record.
            if ($docbooker_created_appointment) {
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
        }

        // 3. Add Doctor Notification to the targeted Doctor
        $notify_doctor_ids = [];
        if ($docbooker_created_appointment && $doctor_user_id) {
            $notify_doctor_ids[] = $doctor_user_id;
        }
        $default_doc_id = class_exists('Dior_Doctor_Resolver') ? Dior_Doctor_Resolver::get_default_doctor_id() : 0;
        if ($docbooker_created_appointment && $default_doc_id && !in_array($default_doc_id, $notify_doctor_ids)) {
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
        global $wpdb;
        $table = $wpdb->prefix . 'dior_notifications';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET is_read = 1, read_at = %s WHERE notification_uid = %s AND (user_id = %d OR patient_id = %d)",
                current_time('mysql'),
                $notif_id,
                $user_id,
                $user_id
            ));
        }
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
        global $wpdb;
        $table = $wpdb->prefix . 'dior_notifications';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET is_read = 1, read_at = %s WHERE (user_id = %d OR patient_id = %d)",
                current_time('mysql'),
                $user_id,
                $user_id
            ));
        }
        wp_send_json_success(['message' => 'All notifications marked as read.']);
    }

    /**
     * AJAX Bulk Delete Notifications
     */
    public static function ajax_bulk_delete_notifications()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        $notif_ids = isset($_POST['notif_ids']) ? (array) $_POST['notif_ids'] : [];
        $notif_ids = array_filter(array_map('sanitize_text_field', $notif_ids));

        if (empty($notif_ids)) {
            wp_send_json_error(['message' => 'No notifications selected.']);
        }

        // 1. Delete from user meta
        $meta_notifs = get_user_meta($user_id, 'dior_notifications', true);
        if (is_array($meta_notifs)) {
            $meta_notifs = array_values(array_filter($meta_notifs, function ($n) use ($notif_ids) {
                return !in_array($n['id'] ?? '', $notif_ids, true);
            }));
            update_user_meta($user_id, 'dior_notifications', $meta_notifs);
        }

        // 2. Delete from relational table
        global $wpdb;
        $table = $wpdb->prefix . 'dior_notifications';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            foreach ($notif_ids as $nid) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table WHERE notification_uid = %s AND (user_id = %d OR patient_id = %d)",
                    $nid,
                    $user_id,
                    $user_id
                ));
            }
        }

        wp_send_json_success(['message' => count($notif_ids) . ' notification(s) deleted successfully.']);
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

        // Generate Full Tab HTML for master-modern-table tbody (#dior-notif-tbody)
        ob_start();
        if (empty($notifications)) {
            echo '<tr><td colspan="8" class="dior-empty-state">No notifications yet.</td></tr>';
        } else {
            foreach ($notifications as $n) {
                $n_title = $n['title'] ?? 'Notification';
                $n_message = $n['message'] ?? '';
                $n_type = $n['type'] ?? 'Info';
                $n_date = !empty($n['created_at']) ? wp_date('M j, Y', strtotime($n['created_at'])) : (!empty($n['timestamp']) ? wp_date('M j, Y', (int) $n['timestamp']) : '—');
                $n_time = dior_format_notification_time($n);
                $n_status = !empty($n['is_read']) ? 'Read' : 'Unread';
                $n_type_class = $n_status === 'Unread' ? 'F59E0B' : '3B82F6';
                $n_type_bg = $n_status === 'Unread' ? 'FEF3C7' : 'DBEAFE';
                $n_status_color = $n_status === 'Unread' ? 'EF4444' : '10B981';
                $n_status_bg = $n_status === 'Unread' ? 'FEE2E2' : 'D1FAE5';
                ?>
<tr data-notif-id="<?php echo esc_attr($n['id'] ?? ''); ?>">
    <td><input type="checkbox" class="dior-ic-52ff4d551f dior-notif-row-cb" onchange="diorUpdateNotifSelectionCount()">
    </td>
    <td class="notif-title-col"><span
            class="cell-text notif-title-text dior-ic-794116ec9b"><?php echo esc_html($n_title); ?></span></td>
    <td class="notif-message-col"><span
            class="cell-text notif-message-text dior-ic-794116ec9b"><?php echo esc_html($n_message); ?></span></td>
    <td>
        <div class="cell-content">
            <div
                class="dior-dyn-status dior-dyn-bg-<?php echo esc_attr($n_type_bg); ?> dior-dyn-color-<?php echo esc_attr($n_type_class); ?>">
                <?php echo esc_html(ucfirst($n_type)); ?>
            </div>
        </div>
    </td>
    <td>
        <div class="cell-content cell-icon-text"><i class="far fa-calendar cell-icon dior-ic-d53ea48df0"></i><span
                class="cell-text"><?php echo esc_html($n_date); ?></span></div>
    </td>
    <td><span class="cell-text dior-ic-794116ec9b"><?php echo esc_html($n_time); ?></span></td>
    <td>
        <div class="cell-content">
            <div
                class="dior-dyn-status dior-dyn-bg-<?php echo esc_attr($n_status_bg); ?> dior-dyn-color-<?php echo esc_attr($n_status_color); ?>">
                <?php echo esc_html($n_status); ?>
            </div>
        </div>
    </td>
    <td>
        <div class="cell-actions">
            <button type="button" class="action-icon-btn view-btn dior-view-notif-btn" title="View Details"
                data-notif-id="<?php echo esc_attr($n['id'] ?? ''); ?>"
                data-notif-title="<?php echo esc_attr($n_title); ?>"
                data-notif-message="<?php echo esc_attr($n_message); ?>"
                data-notif-date="<?php echo esc_attr($n_date . ' ' . $n_time); ?>"
                data-notif-type="<?php echo esc_attr(ucfirst($n_type)); ?>"
                data-notif-action="<?php echo esc_attr($n['action_url'] ?? ''); ?>"><i
                    class="fas fa-eye text-primary"></i></button>
            <?php if (empty($n['is_read']) && !empty($n['id'])): ?>
            <button type="button" class="action-icon-btn edit-btn dior-mark-notification-read" title="Mark as Read"
                data-notif-id="<?php echo esc_attr($n['id']); ?>"><i class="fas fa-check"></i></button>
            <?php else: ?>
            <div style="width: 34px; min-width: 34px;"></div>
            <?php endif; ?>
        </div>
    </td>
</tr>
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
     * AJAX Save Emergency Contact
     */
    public static function ajax_save_emergency_contact()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        $em_name = trim(sanitize_text_field($_POST['emergency_name'] ?? ''));
        $em_rel = trim(sanitize_text_field($_POST['emergency_relation'] ?? ''));
        $em_phone = trim(sanitize_text_field($_POST['emergency_phone'] ?? ''));
        $em_priority = trim(sanitize_text_field($_POST['emergency_priority'] ?? 'Emergency'));

        if (empty($em_name) || empty($em_phone)) {
            wp_send_json_error(['message' => 'Contact name and phone number are required.']);
        }

        $contacts = get_user_meta($user_id, 'dior_emergency_contacts_list', true);
        if (!is_array($contacts)) {
            $contacts = [];
            $existing_name = get_user_meta($user_id, 'emergency_name', true);
            $existing_phone = get_user_meta($user_id, 'emergency_phone', true);
            if (!empty($existing_name) || !empty($existing_phone)) {
                $contacts[] = [
                    'name' => $existing_name ?: 'Emergency Contact',
                    'relation' => get_user_meta($user_id, 'emergency_relation', true) ?: 'Spouse',
                    'phone' => $existing_phone ?: '',
                    'priority' => get_user_meta($user_id, 'emergency_priority', true) ?: 'Emergency',
                ];
            }
        }

        $index = isset($_POST['contact_index']) && $_POST['contact_index'] !== '' ? intval($_POST['contact_index']) : -1;
        $entry = [
            'name' => $em_name,
            'relation' => $em_rel ?: 'Emergency contact',
            'phone' => $em_phone,
            'priority' => $em_priority ?: 'Emergency',
        ];

        if ($index >= 0 && isset($contacts[$index])) {
            $contacts[$index] = $entry;
        } else {
            $contacts[] = $entry;
            $index = count($contacts) - 1;
        }

        update_user_meta($user_id, 'dior_emergency_contacts_list', $contacts);

        if ($index === 0 || $em_priority === 'Primary') {
            update_user_meta($user_id, 'emergency_name', $em_name);
            update_user_meta($user_id, 'emergency_relation', $em_rel);
            update_user_meta($user_id, 'emergency_phone', $em_phone);
            update_user_meta($user_id, 'emergency_priority', $em_priority);
        }

        wp_send_json_success([
            'message' => 'Emergency contact saved successfully!',
            'contact' => $entry,
            'index' => $index,
            'total' => count($contacts)
        ]);
    }

    /**
     * AJAX Delete Emergency Contact
     */
    public static function ajax_delete_emergency_contact()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        $index = isset($_POST['contact_index']) ? intval($_POST['contact_index']) : -1;
        $contacts = get_user_meta($user_id, 'dior_emergency_contacts_list', true);
        if (is_array($contacts) && isset($contacts[$index])) {
            array_splice($contacts, $index, 1);
            update_user_meta($user_id, 'dior_emergency_contacts_list', $contacts);
        }

        if (empty($contacts)) {
            delete_user_meta($user_id, 'emergency_name');
            delete_user_meta($user_id, 'emergency_relation');
            delete_user_meta($user_id, 'emergency_phone');
            delete_user_meta($user_id, 'emergency_priority');
        } else {
            update_user_meta($user_id, 'emergency_name', $contacts[0]['name'] ?? '');
            update_user_meta($user_id, 'emergency_relation', $contacts[0]['relation'] ?? '');
            update_user_meta($user_id, 'emergency_phone', $contacts[0]['phone'] ?? '');
            update_user_meta($user_id, 'emergency_priority', $contacts[0]['priority'] ?? 'Emergency');
        }

        wp_send_json_success([
            'message' => 'Emergency contact removed.',
            'total' => is_array($contacts) ? count($contacts) : 0
        ]);
    }

    /**
     * AJAX Add Health Goal
     */
    public static function ajax_add_health_goal()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required. Please log in.']);
        }

        $height_updated = false;
        $weight_updated = false;
        if (isset($_POST['height'])) {
            $height_val = sanitize_text_field(trim($_POST['height']));
            update_user_meta($user_id, 'height', $height_val);
            $height_updated = true;
        }
        if (isset($_POST['weight'])) {
            $weight_val = sanitize_text_field(trim($_POST['weight']));
            update_user_meta($user_id, 'weight', $weight_val);
            $weight_updated = true;
        }
        if ($height_updated || $weight_updated) {
            if (class_exists('Dior_Patient_Portal_Data')) {
                Dior_Patient_Portal_Data::sync_relational_patient($user_id);
            }
        }

        $goal_name = trim(sanitize_text_field($_POST['goal_name'] ?? ''));
        $goal_val = trim(sanitize_text_field($_POST['goal_val'] ?? ''));
        $goal_pct = isset($_POST['goal_pct']) ? min(100, max(0, intval($_POST['goal_pct']))) : 50;

        $goals = get_user_meta($user_id, 'dior_health_goals', true);
        if (!is_array($goals)) {
            $goals = [];
        }

        $new_goal = null;
        if (!empty($goal_name)) {
            if (empty($goal_val)) {
                wp_send_json_error(['message' => 'Target / current value is required when adding a goal.']);
            }

            $new_goal = [
                'name' => $goal_name,
                'val' => $goal_val,
                'pct' => $goal_pct,
                'created_at' => current_time('mysql'),
            ];

            $goals[] = $new_goal;
            update_user_meta($user_id, 'dior_health_goals', $goals);
        } elseif (!$height_updated && !$weight_updated) {
            wp_send_json_error(['message' => 'Please provide health vitals or a goal to save.']);
        }

        $cur_height = get_user_meta($user_id, 'height', true) ?: '';
        $cur_weight = get_user_meta($user_id, 'weight', true) ?: '';
        $h_display = $cur_height === '' ? '—' : ((is_numeric($cur_height)) ? $cur_height . ' cm' : $cur_height);
        $w_display = $cur_weight === '' ? '—' : ((is_numeric($cur_weight)) ? $cur_weight . ' kg' : $cur_weight);

        wp_send_json_success([
            'message' => 'Health vitals and goals updated successfully!',
            'goal' => $new_goal,
            'goals' => $goals,
            'height' => $cur_height,
            'weight' => $cur_weight,
            'height_display' => $h_display,
            'weight_display' => $w_display,
            'index' => count($goals) - 1,
        ]);
    }

    /**
     * AJAX Delete Health Goal
     */
    public static function ajax_delete_health_goal()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_portal_nonce', 'dior_auth_nonce']);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required.']);
        }

        $index = isset($_POST['goal_index']) ? intval($_POST['goal_index']) : -1;
        $goals = get_user_meta($user_id, 'dior_health_goals', true);
        if (is_array($goals) && isset($goals[$index])) {
            array_splice($goals, $index, 1);
            update_user_meta($user_id, 'dior_health_goals', $goals);
        }

        wp_send_json_success([
            'message' => 'Health goal removed.',
            'goals' => is_array($goals) ? array_values($goals) : [],
        ]);
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

        // Unpack HIPAAtizer payload structure
        $raw_data = $data;
        if (isset($data['data'])) {
            $data = $data['data'];
        }
        if (isset($data['fields'])) {
            $data = $data['fields'];
        }
        $data['raw_data'] = $raw_data;

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
        $email_keys = ['email', 'Email', 'email_address', 'patient_email', 'submitter_email', 'Email Address'];
        foreach ($email_keys as $k) {
            if (!empty($data[$k])) {
                $email = sanitize_email($data[$k]);
                break;
            }
        }

        if (!$user_id && $email) {
            $user = get_user_by('email', $email);
            if ($user) {
                $user_id = $user->ID;
            } else {
                // Fallback: match the normalized Dior patient table.
                global $wpdb;
                $patient_table = $wpdb->prefix . 'dior_patients';
                $matched_patient = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT user_id FROM {$patient_table} WHERE email = %s LIMIT 1",
                        $email
                    )
                );
                if ($matched_patient && !empty($matched_patient->user_id)) {
                    $user_id = (int) $matched_patient->user_id;
                } else {
                }
            }
        }

        if ($user_id && empty($email)) {
            $u_obj = get_userdata($user_id);
            if ($u_obj)
                $email = $u_obj->user_email;
        }

        // HIPAAtizer supports both internal variable keys (e.g. email) and
        // the exact inserted-variable labels configured in the webhook body
        // (e.g. Email Address, Full Name, Street Address). Read both forms.
        $dior_value = static function (array $source, array $keys, $default = '') {
            foreach ($keys as $key) {
                if (array_key_exists($key, $source) && $source[$key] !== '' && $source[$key] !== null) {
                    $value = $source[$key];
                    if (is_array($value)) {
                        return implode(', ', array_map('sanitize_text_field', $value));
                    }
                    return (string) $value;
                }
            }
            return $default;
        };

        // Exact HIPAAtizer inserted-variable names supplied by the form.
        $email = sanitize_email($dior_value($data, [
            'email',
            'Email',
            'email_address',
            'patient_email',
            'submitter_email',
            'Email Address'
        ], $email));

        $full_name = sanitize_text_field($dior_value($data, [
            'full_name',
            'Full Name'
        ]));

        $first_name = sanitize_text_field($dior_value($data, [
            'first_name',
            'First Name',
            'firstname'
        ]));
        $last_name = sanitize_text_field($dior_value($data, [
            'last_name',
            'Last Name',
            'lastname'
        ]));

        // If HIPAAtizer only sends Full Name, split it into WP first/last name.
        if ($full_name && !$first_name && !$last_name) {
            $name_parts = preg_split('/\s+/', trim($full_name), -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($name_parts)) {
                $first_name = array_shift($name_parts);
                $last_name = implode(' ', $name_parts);
            }
        }

        if ($user_id && empty($first_name)) {
            $first_name = get_user_meta($user_id, 'first_name', true) ?: '';
            $last_name = get_user_meta($user_id, 'last_name', true) ?: '';
        }

        // Build clean intake record with all 39 fields
        $intake = [
            'submitted_at' => current_time('Y-m-d H:i:s'),

            // Step 1: Service Selection
            'service_condition' => sanitize_text_field($dior_value($data, ['service_condition', 'service', 'selected_service', 'Selected Service / Condition', 'Which service or condition do you need care for today'])),
            'consultation_type' => sanitize_text_field($dior_value($data, ['consultation_type', 'Preferred Consultation Type', 'Preferred Consultation Type (Labels)', 'Preferred Consultation Type (Values)'])),
            'referral_source' => sanitize_text_field($dior_value($data, ['referral_source', 'How did you hear about us?', 'How did you hear about us? (Labels)', 'How did you hear about us? (Values)'])),

            // Step 2: Eligibility Checks
            'is_18_plus' => sanitize_text_field($data['is_18_plus'] ?? $data['Are you 18 years or older'] ?? ''),
            'is_pregnant' => sanitize_text_field($dior_value($data, ['is_pregnant', 'Are you currently pregnant?', 'Are you currently pregnant'])),

            // Step 3: Patient Information
            'first_name' => $first_name,
            'last_name' => $last_name,
            'dob' => sanitize_text_field($data['dob'] ?? $data['date_of_birth'] ?? $data['Date of Birth'] ?? ''),
            'gender' => sanitize_text_field($data['gender'] ?? $data['Gender'] ?? $data['biological_sex'] ?? $data['Biological Sex'] ?? $data['Sex'] ?? $data['sex'] ?? ''),
            'email' => $email,
            'phone' => sanitize_text_field($dior_value($data, ['phone', 'Phone', 'phone_number', 'Phone Number'])),
            'address' => sanitize_text_field($dior_value($data, ['address', 'Address', 'street_address', 'Street Address'])),
            'city' => sanitize_text_field($dior_value($data, ['city', 'City'])),
            'state' => sanitize_text_field($dior_value($data, ['state', 'State', 'service_state', 'State (Labels)', 'State (Values)'])),
            'zip' => sanitize_text_field($dior_value($data, ['zip', 'Zip', 'zip_code', 'Zip Code', 'ZIP Code', 'ZIP / PIN Code', 'ZIP / Postal code', 'ZIP / Postal Code', 'postal_code', 'postalCode', 'postal_code_value'])),
            'country' => sanitize_text_field($dior_value($data, ['country', 'Country', 'country_name', 'Country Name', 'country_of_residence', 'Country (Labels)', 'Country (Values)'])),
            'emergency_name' => sanitize_text_field($dior_value($data, ['emergency_name', 'emergency_contact_name', 'Emergency Contact Name'])),
            'emergency_relation' => sanitize_text_field($data['emergency_relation'] ?? $data['emergency_contact_relationship'] ?? $data['Emergency Contact Relationship'] ?? ''),
            'emergency_phone' => sanitize_text_field($dior_value($data, ['emergency_phone', 'emergency_contact_phone', 'Emergency Contact Phone'])),
            'insurance_provider' => sanitize_text_field($dior_value($data, ['insurance_provider', 'Insurance Provider Name'])),
            'insurance_member_id' => sanitize_text_field($dior_value($data, ['insurance_member_id', 'Insurance Member / Policy ID'])),

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
                update_user_meta($user_id, 'address', $intake['address']);
                update_user_meta($user_id, 'billing_address_1', $intake['address']);
            }
            if (!empty($intake['city'])) {
                update_user_meta($user_id, 'city', $intake['city']);
                update_user_meta($user_id, 'billing_city', $intake['city']);
            }
            if (!empty($intake['state'])) {
                update_user_meta($user_id, 'state', $intake['state']);
            }
            if (!empty($intake['zip'])) {
                update_user_meta($user_id, 'zip', $intake['zip']);
                update_user_meta($user_id, 'zip_code', $intake['zip']);
            }

            if (!empty($intake['country'])) {
                update_user_meta($user_id, 'country', $intake['country']);
                update_user_meta($user_id, 'billing_country', $intake['country']);
            }
            if (!empty($intake['emergency_name'])) {
                update_user_meta($user_id, 'emergency_name', $intake['emergency_name']);
            }
            if (!empty($intake['emergency_relation'])) {
                update_user_meta($user_id, 'emergency_relation', $intake['emergency_relation']);
            }
            if (!empty($intake['emergency_phone'])) {
                update_user_meta($user_id, 'emergency_phone', $intake['emergency_phone']);
            }
            if ($intake['is_pregnant'] !== '') {
                $pregnant = in_array(strtolower((string) $intake['is_pregnant']), ['1', 'yes', 'true', 'y'], true);
                update_user_meta($user_id, 'pregnancy_status', $pregnant ? 'Pregnant' : 'Not Pregnant');
            }

            // Keep the complete mailing address for the dashboard while retaining
            // the original street address in its canonical field.
            if (!empty($intake['address']) || !empty($intake['city']) || !empty($intake['state']) || !empty($intake['zip'])) {
                $full_address = trim(
                    ($intake['address'] ?? '') . ', ' .
                    ($intake['city'] ?? '') . ', ' .
                    ($intake['state'] ?? '') . ' ' .
                    ($intake['zip'] ?? '')
                );
                update_user_meta($user_id, 'address', trim($full_address, " ,"));
            }

            // IMPORTANT: sync HIPAAtizer data into the normalized patient table
            // used by Doctor Dashboard and Patient Settings.
            if (class_exists('Dior_Patient_Portal_Data')) {
                Dior_Patient_Portal_Data::sync_relational_patient($user_id);
            }

            // Sync insurance when HIPAAtizer sends provider/member information.
            $insurance_provider = sanitize_text_field(
                $data['insurance_provider'] ?? $data['Insurance Provider'] ?? $data['Insurance Provider Name'] ?? $intake['insurance_provider'] ?? ''
            );
            $insurance_member_id = sanitize_text_field(
                $data['insurance_member_id'] ?? $data['insurance_member_number'] ?? $data['Insurance Member ID'] ?? $data['Insurance Member / Policy ID'] ?? $intake['insurance_member_id'] ?? ''
            );
            if ($insurance_provider !== '' || $insurance_member_id !== '') {
                global $wpdb;
                $insurance_table = $wpdb->prefix . 'dior_insurance';
                $patient_row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}dior_patients WHERE user_id = %d LIMIT 1",
                        $user_id
                    )
                );
                if ($patient_row) {
                    $existing_insurance = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM {$insurance_table} WHERE patient_id = %d LIMIT 1",
                            $patient_row->id
                        )
                    );
                    $insurance_data = [
                        'primary_insurance_provider' => $insurance_provider,
                        'primary_member_id' => $insurance_member_id,
                        'updated_at' => current_time('mysql'),
                    ];
                    if ($existing_insurance) {
                        $wpdb->update($insurance_table, $insurance_data, ['patient_id' => $patient_row->id]);
                    } else {
                        $insurance_data['patient_id'] = $patient_row->id;
                        $insurance_data['created_at'] = current_time('mysql');
                        $wpdb->insert($insurance_table, $insurance_data);
                    }
                }
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

    /**
     * Render DocBooker booking modal template
     */
    public static function render_docbooker_booking_modal()
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $template = DIOR_PORTAL_PATH . 'templates/modal-docbooker-booking.php';
        if (file_exists($template)) {
            $rendered = true;
            include $template;
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
        'status' => 'Pending',
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
// Super Admin Dashboard is reserved for Phase 2 (backed up in _backup_super_admin_phase2)


if (!class_exists('Dior_Medical_Dashboard')) {
    class_alias('Dior_Medical_Auth', 'Dior_Medical_Dashboard');
}

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




/**
 * Dior HIPAAtizer Appointment Popup — v17 integration.
 * Uses the form shortcode from the HIPAAtizer WordPress integration.
 */
if (!class_exists('Dior_HIPAAtizer_Appointment_Popup_V17')) {
    final class Dior_HIPAAtizer_Appointment_Popup_V17
    {
        private const FORM_ID = '01a12050-38e5-74ba-967b-60dacc2bd7f7';

        public static function init()
        {
            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 30);
            add_action('wp_footer', [__CLASS__, 'render_form_source'], 100);
        }

        public static function enqueue_assets()
        {
            if (is_admin()) {
                return;
            }

            // Note: dior-hipaatizer-popup.css is already merged into dior-custom.css

            // SweetAlert2 is already enqueued by the dashboard; declare dependency.
            wp_enqueue_script(
                'dior-hipaatizer-popup-v17',
                DIOR_PORTAL_URL . 'assets/js/dior-hipaatizer-popup.js',
                ['sweetalert2'],
                DIOR_PORTAL_VERSION,
                true
            );
        }

        public static function render_form_source()
        {
            if (is_admin() || !shortcode_exists('hipaatizer')) {
                return;
            }

            echo '<div id="dior-hipaatizer-form-source-v17" hidden>';
            echo do_shortcode('[hipaatizer id="' . esc_attr(self::FORM_ID) . '"]');
            echo '</div>';
        }
    }

    Dior_HIPAAtizer_Appointment_Popup_V17::init();
}

/**
 * Super Admin overview dashboard is reserved for Phase 2.
 * All Phase 2 files and controller are backed up in _backup_super_admin_phase2.
 */

