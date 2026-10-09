<?php
require('wp-load.php');
global $wpdb;
$cols = $wpdb->get_results('DESCRIBE ' . $wpdb->prefix . 'dior_patients');
echo json_encode($cols);
