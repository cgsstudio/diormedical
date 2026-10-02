<?php
require 'c:/xampp/htdocs/diormedical/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'dior_prescriptions';

$count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "CURRENT_RECORDS: $count\n";

if ($count < 10) {
    // Add demo records
    for ($i = $count + 1; $i <= 10; $i++) {
        $wpdb->insert($table, [
            'prescription_id' => 'RX-' . mt_rand(100000, 999999),
            'prescription_uid' => wp_generate_uuid4(), // ADDED THIS
            'prescription_date' => date('Y-m-d', strtotime('-' . mt_rand(1, 30) . ' days')),
            'patient_name' => 'Demo Patient ' . $i,
            'medication' => 'Medication ' . chr(64 + mt_rand(1, 26)) . ' ' . mt_rand(10, 500) . 'mg',
            'status' => ['Active', 'Completed', 'Pending'][mt_rand(0, 2)]
        ]);
    }
    echo "ADDED_DEMO_RECORDS\n";
}
