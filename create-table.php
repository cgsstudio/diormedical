<?php
require_once('wp-load.php');
global $wpdb;
$table_name = $wpdb->prefix . 'dior_prescriptions';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE $table_name (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    prescription_id varchar(50) NOT NULL,
    patient_name varchar(255) NOT NULL,
    patient_id varchar(50) NOT NULL,
    prescription_date date NOT NULL,
    medications text NOT NULL,
    dosage varchar(100) NOT NULL,
    frequency varchar(100) NOT NULL,
    duration varchar(100) NOT NULL,
    doctor_name varchar(255) NOT NULL,
    status varchar(50) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY  (id)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

echo "Table $table_name created or updated successfully.";
