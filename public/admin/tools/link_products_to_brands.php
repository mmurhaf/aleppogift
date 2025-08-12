<?php
require_once('../../config/config.php');
require_once('../../includes/Database.php');

$db = new Database();

// Define manual brand keyword map
$brand_keywords = [
    'Dior' => ['dior'],
    'Louis Vuitton' => ['louis vuitton', 'lv'],
    'Chanel' => ['chanel'],
    'Gucci' => ['gucci'],
    'Versace' => ['versace'],
    'Hermes' => ['hermes'],
    'Cartier' => ['cartier'],
    'Tiffany' => ['tiffany'],
    'Bvlgari' => ['bvlgari'],
    'Missoni' => ['missoni'],
];

// Fetch all brands from DB
$brands = $db->query("SELECT id, name_en FROM brands")->fetchAll(PDO::FETCH_ASSOC);

// Create name_en → id map
$brand_id_map = [];
foreach ($brands as $brand) {
    $brand_id_map[strtolower($brand['name_en'])] = $brand['id'];
}

// Fetch all products
$products = $db->query("SELECT id, name_en, brand_id FROM products")->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;

foreach ($products as $product) {
    $name = strtolower($product['name_en']);
    $current_brand_id = $product['brand_id'];

    foreach ($brand_keywords as $brandName => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($name, $kw) !== false) {
                $brand_id = $brand_id_map[strtolower($brandName)] ?? null;
                if ($brand_id && $current_brand_id != $brand_id) {
                    // Update product
                    $db->query("UPDATE products SET brand_id = :brand_id WHERE id = :id", [
                        'brand_id' => $brand_id,
                        'id' => $product['id']
                    ]);
                    echo "✔️ Linked Product #{$product['id']} → Brand: $brandName<br>";
                    $updated++;
                }
                break 2; // Stop checking after first match
            }
        }
    }
}

echo "<br>Total updated: $updated";
?>
