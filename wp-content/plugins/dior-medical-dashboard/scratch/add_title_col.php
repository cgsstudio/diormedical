<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'dior_prescriptions';
$column = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'title'",
    DB_NAME, $table
));

if (empty($column)) {
    $res = $wpdb->query("ALTER TABLE $table ADD COLUMN title VARCHAR(255) DEFAULT '' AFTER prescription_id");
    echo "Added title column to $table: " . ($res !== false ? "SUCCESS" : "FAILED") . "\n";
} else {
    echo "title column already exists in $table\n";
}

$cols = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
foreach ($cols as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}
