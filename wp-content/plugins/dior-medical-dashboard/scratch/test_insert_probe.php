<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

$table_name = $wpdb->prefix . 'dior_prescriptions';
$prescription_id = 'RX-TEST-' . time();
$now = current_time('mysql');

$data = [
    'prescription_uid' => $prescription_id,
    'prescription_id' => $prescription_id,
    'patient_id' => 1,
    'patient_name' => 'diormedical',
    'doctor_id' => 1,
    'doctor_name' => 'Dr. Admin',
    'appointment_uid' => null,
    'prescription_date' => date('Y-m-d'),
    'medications' => '1. Metformin (500 mg)',
    'medication' => '1. Metformin (500 mg)',
    'dosage' => '500 mg',
    'frequency' => 'Once daily',
    'duration' => '30 days',
    'instructions' => 'Take with food',
    'quantity' => '30 tablets',
    'refills' => 1,
    'status' => 'Active',
    'created_at' => $now,
    'updated_at' => $now,
];

$res = $wpdb->insert($table_name, $data);
echo "Insert result: " . ($res ? "SUCCESS" : "FAILED") . "\n";
if (!$res) {
    echo "DB Error: " . $wpdb->last_error . "\n";
} else {
    echo "Inserted ID: " . $wpdb->insert_id . "\n";
    // clean up test row
    $wpdb->delete($table_name, ['id' => $wpdb->insert_id]);
    echo "Cleaned up test row.\n";
}
