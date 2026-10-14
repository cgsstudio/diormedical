<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'dior_prescriptions';
// Check last error from mysql
echo "Last error: " . $wpdb->last_error . "\n";

// Let's test what happened during ajax_add_prescription
// Let's check existing rows in wp_dior_prescriptions
$rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 5", ARRAY_A);
echo "Recent rows count: " . count($rows) . "\n";
print_r($rows);
