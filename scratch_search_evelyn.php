<?php
function searchDir($dir, $pattern) {
    $results = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($files as $file) {
        if ($file->isDir()) continue;
        $path = $file->getPathname();
        if (strpos($path, '.git') !== false || strpos($path, 'cache') !== false) continue;
        if (substr($path, -4) !== '.php' && substr($path, -3) !== '.js') continue;
        $content = @file_get_contents($path);
        if ($content && stripos($content, $pattern) !== false) {
            $results[] = $path;
        }
    }
    return $results;
}

$found = searchDir('wp-content', 'Evelyn Vance');
print_r($found);
