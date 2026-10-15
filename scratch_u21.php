<?php
require 'wp-load.php';
$u21 = get_user_meta(21, 'dior_appointments', true);
echo "User 21 dior_appointments count: " . count($u21) . "\n";
print_r($u21);
