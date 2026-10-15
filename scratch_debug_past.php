<?php
require 'wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'dior_appointments';
$all = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
echo "Total in db: " . count($all) . "\n";
foreach($all as $a) {
    echo "ID: {$a['id']} | Patient: {$a['patient_id']} | Date: {$a['appt_date']} | Time: {$a['appt_time']} | DocID: {$a['doctor_id']} | Cond: {$a['condition_name']} | Status: {$a['status']}\n";
}
