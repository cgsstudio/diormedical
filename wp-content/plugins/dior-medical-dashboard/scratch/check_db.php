<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'dior_prescriptions';
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
echo "Table exists: " . ($exists ? "YES" : "NO") . "\n";

if ($exists) {
    $cols = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
    print_r($cols);
} else {
    echo "wp_dior_prescriptions does not exist!\n";
}
