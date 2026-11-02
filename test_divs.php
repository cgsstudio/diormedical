<?php
function test_file_divs($f) {
    $content = file_get_contents($f);
    $clean = preg_replace('/<\?php.*?\?>/s', '', $content);
    $div_open = preg_match_all('/<div(?: [^>]*)?>/i', $clean);
    $div_close = preg_match_all('/<\/div>/i', $clean);
    echo "$f: open=$div_open, close=$div_close, diff=" . ($div_open - $div_close) . "\n";
}

test_file_divs('wp-content/plugins/dior-medical-dashboard/templates/doctor-tabs/overview.php');
test_file_divs('wp-content/plugins/dior-medical-dashboard/templates/doctor-tabs/appointments.php');
