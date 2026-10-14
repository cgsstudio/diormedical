<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

$table_name = $wpdb->prefix . 'dior_prescriptions';
$title = 'Type 2 Diabetes Management';
$prescription_id = 'RX-' . gmdate('ymd') . '-' . strtoupper(wp_generate_password(6, false, false));
$prescription_date = current_time('Y-m-d');
$now = current_time('mysql');

$insert_data = [
    'prescription_uid' => $prescription_id,
    'prescription_id' => $prescription_id,
    'title' => $title,
    'patient_id' => 1,
    'patient_name' => 'diormedical',
    'doctor_id' => 1,
    'doctor_name' => 'Dr. Marcus Sterling, DO',
    'appointment_uid' => null,
    'prescription_date' => $prescription_date,
    'medications' => 'Metformin (500 mg)',
    'medication' => 'Metformin (500 mg)',
    'dosage' => '1 Tablet(s) Orally',
    'frequency' => 'Daily (After Food)',
    'duration' => '30 Day(s)',
    'instructions' => 'Take 1 tablet in the morning after food for 30 days',
    'quantity' => '1 Item(s)',
    'refills' => 1,
    'status' => 'Active',
    'created_at' => $now,
    'updated_at' => $now,
];

$inserted = $wpdb->insert($table_name, $insert_data);
echo "Insertion test with Title and Clean Medication Name:\n";
echo "Result: " . ($inserted ? "SUCCESS" : "FAILED") . "\n";
if (!$inserted) {
    echo "DB Error: " . $wpdb->last_error . "\n";
} else {
    echo "New Rx ID: " . $wpdb->insert_id . "\n";
    $row = $wpdb->get_row("SELECT * FROM $table_name WHERE id = " . $wpdb->insert_id, ARRAY_A);
    print_r($row);
}
