<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

$users_with_appts = $wpdb->get_results("SELECT user_id, meta_value FROM wp_usermeta WHERE meta_key = 'dior_appointments'");
foreach ($users_with_appts as $row) {
    $val = maybe_unserialize($row->meta_value);
    echo "User " . $row->user_id . " has " . (is_array($val) ? count($val) : 0) . " appointments.\n";
    if (is_array($val) && count($val) > 0) {
        $first = $val[0];
        echo "  - Latest: " . ($first['provider'] ?? '') . " | " . ($first['date'] ?? '') . " " . ($first['time'] ?? '') . " | Status: " . ($first['status'] ?? '') . "\n";
    }
}
