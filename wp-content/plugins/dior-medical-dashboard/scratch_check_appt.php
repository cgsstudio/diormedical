<?php
require_once dirname(__DIR__, 2) . '/../wp-load.php';
global $wpdb;
$t = $wpdb->prefix . 'dior_appointments';
$rows = $wpdb->get_results("SELECT patient_id, appt_uid, doctor_name, appt_date, appt_time, status, visit_type FROM $t LIMIT 10", ARRAY_A);
print_r($rows);

