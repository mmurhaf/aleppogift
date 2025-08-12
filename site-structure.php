<?php
// site-structure.php
function listDirectoryTree($dir, $prefix = '') {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        echo $prefix . $item . (is_dir($path) ? "/" : "") . "\n";

        if (is_dir($path)) {
            listDirectoryTree($path, $prefix . '  ');
        }
    }
}

header("Content-Type: text/plain");
echo "📂 Website File Structure\n";
echo "==========================\n\n";
listDirectoryTree(__DIR__);
?>