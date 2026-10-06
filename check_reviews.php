<?php
require 'c:/xampp/htdocs/diormedical/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'dior_reviews';

$count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "CURRENT_RECORDS: $count\n";

if ($count < 10) {
    // Add demo records
    for ($i = $count + 1; $i <= 10; $i++) {
        $wpdb->insert($table, [
            'patient_id' => mt_rand(1, 100),
            'review_uid' => wp_generate_uuid4(),
            'rating' => mt_rand(3, 5),
            'comment' => 'This is a demo review comment number ' . $i . '. Great service and very professional!',
            'status' => 'Published',
            'created_at' => current_time('mysql')
        ]);
    }
    echo "ADDED_DEMO_RECORDS\n";
}
