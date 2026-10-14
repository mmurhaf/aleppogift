<?php
// Script to fix all admin file paths
$admin_dir = __DIR__ . '/public/admin/';
$files = glob($admin_dir . '*.php');

$old_patterns = [
    "require_once('../../includes/session_helper.php');",
    "require_once('../../config/config.php');",
    "require_once('../../includes/Database.php');"
];

$new_patterns = [
    "\$root_dir = dirname(dirname(__DIR__));\nrequire_once(\$root_dir . '/includes/session_helper.php');",
    "require_once(\$root_dir . '/config/config.php');",
    "require_once(\$root_dir . '/includes/Database.php');"
];

echo "Fixing admin file paths...\n";

foreach ($files as $file) {
    $filename = basename($file);
    
    // Skip files we already fixed
    if (in_array($filename, ['login.php', 'categories.php', 'brands.php'])) {
        echo "Skipping already fixed: $filename\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $original_content = $content;
    
    // Check if file needs fixing
    if (strpos($content, "require_once('../../") !== false) {
        echo "Fixing: $filename\n";
        
        // Replace the patterns
        for ($i = 0; $i < count($old_patterns); $i++) {
            if ($i == 0) {
                // First replacement includes the $root_dir definition
                $content = str_replace($old_patterns[$i], $new_patterns[$i], $content);
            } else {
                $content = str_replace($old_patterns[$i], $new_patterns[$i], $content);
            }
        }
        
        // Write the fixed content back
        if ($content !== $original_content) {
            file_put_contents($file, $content);
            echo "  ✅ Fixed $filename\n";
        } else {
            echo "  ⚠️ No changes needed for $filename\n";
        }
    } else {
        echo "No fixes needed: $filename\n";
    }
}

echo "Done!\n";
?>



