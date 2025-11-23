<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../includes/Database.php');

$db = new Database();

// Helper function for double-decoding
function fixDoubleEncodedArabic($text) {
    $step1 = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    $step2 = mb_convert_encoding($step1, 'UTF-8', 'ISO-8859-1');
    return $step2;
}

$products = $db->query("SELECT id, name_ar FROM products");

foreach ($products as $product) {
    $id = $product['id'];
    $bad = $product['name_ar'];

    // Check if it contains common double-encoded junk
    if (preg_match('/Ã|Â/', $bad)) {
        $fixed = fixDoubleEncodedArabic($bad);

        // Update the DB with the fixed version
        $db->query("UPDATE products SET name_ar = :fixed WHERE id = :id", [
            'fixed' => $fixed,
            'id' => $id
        ]);

        echo "✅ Fixed ID $id: $fixed<br>";
    } else {
        echo "✔️ Skipped ID $id (already OK)<br>";
    }
}
