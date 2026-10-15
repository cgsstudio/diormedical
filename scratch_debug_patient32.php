<?php
require 'wp-load.php';
$patient_id = 32;
$appts = Dior_Appointment_Service::get_patient_appointments($patient_id);
echo "Total appointments for patient 32: " . count($appts) . "\n";
print_r($appts);
