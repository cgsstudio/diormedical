<?php
require_once __DIR__ . '/wp-load.php';
wp_set_current_user(4);
$html = Dior_Doctor_Dashboard::render();

libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
libxml_clear_errors();

$editAppt = $doc->getElementById('tab-doc-appointments-edit');
if ($editAppt) {
    echo "Found #tab-doc-appointments-edit!\n";
    $curr = $editAppt;
    $path = [];
    while ($curr && $curr->parentNode) {
        $name = $curr->nodeName;
        if ($curr->hasAttributes() && $curr->getAttribute('id')) {
            $name .= '#' . $curr->getAttribute('id');
        } elseif ($curr->hasAttributes() && $curr->getAttribute('class')) {
            $name .= '.' . substr($curr->getAttribute('class'), 0, 20);
        }
        $path[] = $name;
        $curr = $curr->parentNode;
    }
    echo "Path: " . implode(' -> ', array_reverse($path)) . "\n";
} else {
    echo "#tab-doc-appointments-edit NOT found!\n";
}
