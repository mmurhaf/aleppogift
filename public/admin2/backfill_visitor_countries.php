<?php
/**
 * Backfill Country Data for Existing Visitor Records
 * Run this once after adding country tracking to update historical data
 * 
 * @author AleppoGift Development Team
 * @date January 27, 2026
 */

// Set longer execution time for large datasets
set_time_limit(300); // 5 minutes

// Use bootstrap for secure initialization
require_once(__DIR__ . '/../../includes/bootstrap.php');

// Initialize database
$db = new Database();

echo "<!DOCTYPE html><html><head><title>Backfill Country Data</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:900px;margin:40px auto;padding:20px;}
.success{color:green;}.error{color:red;}.info{color:blue;}
table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}
th{background:#f5f5f5;}.progress{background:#e0e0e0;border-radius:5px;overflow:hidden;height:20px;margin:10px 0;}
.progress-bar{background:#4CAF50;height:100%;transition:width 0.3s;}</style></head><body>";

echo "<h2>🌍 Backfill Country Data for Visitors</h2>";

try {
    // First, check if country columns exist
    $columns = $db->fetchAll("DESCRIBE visitors");
    $existingCols = array_column($columns, 'Field');
    
    if (!in_array('country', $existingCols)) {
        echo "<p class='error'>❌ Country column doesn't exist. Please run setup_visitors_table.php first.</p>";
        echo "</body></html>";
        exit;
    }
    
    // Get records without country data (unique IPs to minimize API calls)
    $uniqueIps = $db->fetchAll(
        "SELECT DISTINCT ip_address 
         FROM visitors 
         WHERE country IS NULL OR country = '' OR country = 'Unknown'
         LIMIT 100"  // Process 100 at a time to respect API limits
    );
    
    $totalToProcess = count($uniqueIps);
    
    if ($totalToProcess === 0) {
        echo "<p class='success'>✅ All visitor records already have country data!</p>";
        
        // Show summary
        $summary = $db->fetchAll(
            "SELECT 
                COALESCE(country, 'Unknown') as country,
                country_code,
                COUNT(*) as visits,
                COUNT(DISTINCT ip_address) as unique_visitors
             FROM visitors 
             GROUP BY country, country_code
             ORDER BY visits DESC
             LIMIT 20"
        );
        
        if (!empty($summary)) {
            echo "<h3>Country Summary</h3>";
            echo "<table><tr><th>Country</th><th>Code</th><th>Visits</th><th>Unique Visitors</th></tr>";
            foreach ($summary as $row) {
                echo "<tr><td>" . htmlspecialchars($row['country']) . "</td>";
                echo "<td>" . htmlspecialchars($row['country_code'] ?? 'XX') . "</td>";
                echo "<td>" . number_format($row['visits']) . "</td>";
                echo "<td>" . number_format($row['unique_visitors']) . "</td></tr>";
            }
            echo "</table>";
        }
        
        echo "</body></html>";
        exit;
    }
    
    echo "<p class='info'>📊 Found <strong>$totalToProcess</strong> unique IPs without country data</p>";
    echo "<p class='info'>⏳ Processing... (this may take a minute due to API rate limits)</p>";
    
    // Flush output to show progress
    if (ob_get_level()) ob_flush();
    flush();
    
    $processed = 0;
    $updated = 0;
    $failed = 0;
    $results = [];
    
    foreach ($uniqueIps as $row) {
        $ip = $row['ip_address'];
        $processed++;
        
        // Skip invalid IPs
        if (empty($ip) || $ip === '0.0.0.0' || strpos($ip, ':...:') !== false) {
            $results[] = ['ip' => $ip, 'country' => 'Skipped', 'code' => '-', 'status' => 'skip'];
            continue;
        }
        
        // Skip local IPs
        if ($ip === '127.0.0.1' || $ip === '::1') {
            $db->query(
                "UPDATE visitors SET country = 'Local', country_code = 'LO' WHERE ip_address = ?",
                [$ip]
            );
            $results[] = ['ip' => $ip, 'country' => 'Local', 'code' => 'LO', 'status' => 'local'];
            $updated++;
            continue;
        }
        
        // Lookup country from IP
        $country = lookupCountry($ip);
        
        if ($country['code'] !== 'XX') {
            // Update all records with this IP
            $db->query(
                "UPDATE visitors SET country = ?, country_code = ? WHERE ip_address = ?",
                [$country['country'], $country['code'], $ip]
            );
            $updated++;
            $results[] = ['ip' => $ip, 'country' => $country['country'], 'code' => $country['code'], 'status' => 'success'];
        } else {
            $failed++;
            $results[] = ['ip' => $ip, 'country' => 'Unknown', 'code' => 'XX', 'status' => 'failed'];
        }
        
        // Rate limiting: ip-api.com allows 45 requests per minute
        // Sleep 1.5 seconds between requests to stay under limit
        usleep(1500000);
    }
    
    echo "<h3>Results</h3>";
    echo "<p>✅ Updated: <strong>$updated</strong> | ❌ Failed: <strong>$failed</strong> | ⏭️ Skipped: <strong>" . ($processed - $updated - $failed) . "</strong></p>";
    
    // Show results table
    echo "<table><tr><th>IP Address</th><th>Country</th><th>Code</th><th>Status</th></tr>";
    foreach ($results as $r) {
        $statusIcon = match($r['status']) {
            'success' => '✅',
            'local' => '🏠',
            'skip' => '⏭️',
            default => '❌'
        };
        $shortIp = strlen($r['ip']) > 25 ? substr($r['ip'], 0, 12) . '...' . substr($r['ip'], -8) : $r['ip'];
        echo "<tr><td><code>" . htmlspecialchars($shortIp) . "</code></td>";
        echo "<td>" . htmlspecialchars($r['country']) . "</td>";
        echo "<td>" . htmlspecialchars($r['code']) . "</td>";
        echo "<td>$statusIcon</td></tr>";
    }
    echo "</table>";
    
    // Check if more records need processing
    $remaining = $db->fetch(
        "SELECT COUNT(DISTINCT ip_address) as count 
         FROM visitors 
         WHERE country IS NULL OR country = '' OR country = 'Unknown'"
    )['count'];
    
    if ($remaining > 0) {
        echo "<p class='info'>📋 <strong>$remaining</strong> more unique IPs still need processing.</p>";
        echo "<p><a href='?'>Click here to continue processing</a> (respects API rate limits)</p>";
    } else {
        echo "<p class='success'>🎉 All visitor records now have country data!</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='analytics.php'>← Back to Analytics</a></p>";
echo "</body></html>";

/**
 * Lookup country from IP address using ip-api.com
 */
function lookupCountry($ip) {
    try {
        $url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=status,country,countryCode";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if ($data && isset($data['status']) && $data['status'] === 'success') {
                return [
                    'country' => $data['country'] ?? 'Unknown',
                    'code' => $data['countryCode'] ?? 'XX'
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Country lookup failed for $ip: " . $e->getMessage());
    }
    
    return ['country' => 'Unknown', 'code' => 'XX'];
}
