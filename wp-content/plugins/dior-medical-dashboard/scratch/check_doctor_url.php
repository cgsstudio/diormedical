<?php
require_once __DIR__ . '/../../../../wp-load.php';
$pages = get_posts([
    'post_type' => 'page',
    's' => 'doctor',
    'posts_per_page' => 10
]);
foreach ($pages as $p) {
    echo $p->post_title . ' -> ' . get_permalink($p->ID) . "\n";
}
