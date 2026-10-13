<?php
require_once __DIR__ . '/../../../../wp-load.php';

// Log in as user 2 (patient)
wp_set_current_user(2);

ob_start();
Dior_Medical_Auth::render_docbooker_booking_modal();
$out = ob_get_clean();

echo "Modal Length: " . strlen($out) . "\n";
echo "Contains dior-docbooker-modal: " . (strpos($out, 'dior-docbooker-modal') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Video Visit: " . (strpos($out, 'Video Visit (HD)') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Phone Consultation: " . (strpos($out, 'Phone Consultation') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Marcus Sterling: " . (strpos($out, 'Marcus Sterling') !== false ? 'YES' : 'NO') . "\n";
