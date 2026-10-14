<?php
/**
 * Sitemap Debug - Test database connection
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Sitemap Database Debug ===\n\n";

// Test 1: Bootstrap
echo "1. Testing bootstrap.php...\n";
try {
    require_once(__DIR__ . '/includes/bootstrap.php');
    echo "   ✓ Bootstrap loaded\n";
} catch (Exception $e) {
    echo "   ✗ Bootstrap failed: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Database class
echo "\n2. Testing Database class...\n";
try {
    if (!class_exists('Database')) {
        require_once(__DIR__ . '/includes/Database.php');
    }
    echo "   ✓ Database class exists\n";
} catch (Exception $e) {
    echo "   ✗ Database class error: " . $e->getMessage() . "\n";
    exit;
}

// Test 3: Database connection
echo "\n3. Testing database connection...\n";
try {
    $db = new Database();
    echo "   ✓ Database connected\n";
} catch (Exception $e) {
    echo "   ✗ Connection failed: " . $e->getMessage() . "\n";
    exit;
}

// Test 4: Categories query
echo "\n4. Testing categories query...\n";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM categories WHERE status = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ Found {$result['count']} active categories\n";
} catch (Exception $e) {
    echo "   ✗ Categories query failed: " . $e->getMessage() . "\n";
}

// Test 5: Brands query
echo "\n5. Testing brands query...\n";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM brands WHERE status = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ Found {$result['count']} active brands\n";
} catch (Exception $e) {
    echo "   ✗ Brands query failed: " . $e->getMessage() . "\n";
}

// Test 6: Products query
echo "\n6. Testing products query...\n";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE status = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ Found {$result['count']} active products\n";
} catch (Exception $e) {
    echo "   ✗ Products query failed: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
echo "\nIf all tests pass, the sitemap should work.\n";
echo "Visit: sitemap.php to see the actual sitemap.\n";
