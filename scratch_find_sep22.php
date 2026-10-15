<?php
require 'wp-load.php';
global $wpdb;
$meta = $wpdb->get_results("SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_value LIKE '%Sep 22%' OR meta_value LIKE '%Evelyn Vance%'", ARRAY_A);
echo "=== USERMETA ===\n";
foreach($meta as $m) {
    echo "User: {$m['user_id']} | Key: {$m['meta_key']}\n";
    print_r(unserialize($m['meta_value']) ?: $m['meta_value']);
    echo "\n";
}

$pmeta = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE '%Sep 22%' OR meta_value LIKE '%Evelyn Vance%'", ARRAY_A);
echo "=== POSTMETA ===\n";
foreach($pmeta as $pm) {
    echo "Post: {$pm['post_id']} | Key: {$pm['meta_key']}\n";
}
