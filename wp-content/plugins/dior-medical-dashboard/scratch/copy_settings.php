<?php
$doc_settings = file_get_contents('c:\\xampp\\htdocs\\diormedical\\wp-content\\plugins\\dior-medical-dashboard\\templates\\doctor-tabs\\settings.php');

$replacements = [
    'id="tab-doc-settings"' => 'id="tab-settings"',
    '#tab-doc-settings' => '#tab-settings',
    '.dior-doctor-wrap' => '.dior-patient-portal-wrap',
    '$doc_profile' => '$profile',
    'diorDocSelectSettingsTab' => 'diorSelectSettingsTab',
    'dior-doc-nav-item' => 'dior-patient-nav-item',
    'dior-doc-tab-pane' => 'dior-patient-tab-pane',
    'doc-tab-profile' => 'patient-tab-profile',
    'doc-tab-clinic' => 'patient-tab-security',
    'doc-tab-schedule' => 'patient-tab-privacy',
    'doc-tab-alerts' => 'patient-tab-alerts',
    
    // Texts
    'Doctor Profile Settings' => 'Patient Profile Settings',
    'Doctor Information' => 'Patient Information',
    'Professional credentials &amp; background' => 'Personal details & contact',
    
    'Clinic Information' => 'Security & Password',
    'Manage clinic address and branding' => 'Password, 2FA & credentials',
    'Clinic Name' => 'Current Password',
    'Clinic Contact Number' => 'New Password',
    
    'Practice &amp; Schedule Settings' => 'Privacy & Data',
    'Configure appointment slots, auto-confirmation &amp; video visit preferences' => 'EMR consent & data download',
    
    'Doctor Notification Alerts' => 'Notification Alerts',
    'Set alerts for new patient bookings, cancellations, and EMR records' => 'Email, SMS & alert controls',
    'Save Doctor Profile' => 'Save Patient Profile',
    'Save Clinic Details' => 'Save Security Settings',
    'Save Schedule Settings' => 'Save Privacy Settings',
];

$pat_settings = str_replace(array_keys($replacements), array_values($replacements), $doc_settings);

// Need to update variables:
// $profile['title'] doesn't exist for patient, maybe first_name / last_name.
// Actually, it's easier to just overwrite and they can adjust if needed, but wait:
$pat_settings = str_replace("echo esc_html(!empty(\$profile['title']) ? \$profile['title'] . ' ' : '');", "", $pat_settings);
$pat_settings = str_replace("echo esc_html(\$profile['first_name'] ?? 'Sarah');", "echo esc_html(\$profile['first_name'] ?? 'Mia');", $pat_settings);
$pat_settings = str_replace("echo esc_html(\$profile['last_name'] ?? 'Connor');", "echo esc_html(\$profile['last_name'] ?? 'Song');", $pat_settings);
$pat_settings = str_replace("echo esc_html(\$profile['specialization'] ?? 'Senior Cardiologist');", "echo 'Verified Patient';", $pat_settings);

file_put_contents('c:\\xampp\\htdocs\\diormedical\\wp-content\\plugins\\dior-medical-dashboard\\templates\\patient-tabs\\settings.php', $pat_settings);
echo "Done";
