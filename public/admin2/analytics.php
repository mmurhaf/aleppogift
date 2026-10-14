<?php
/**
 * Visitor Analytics & Statistics
 * AleppoGift - E-commerce Platform
 */

date_default_timezone_set('Asia/Dubai');

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();

/**
 * Format IP address for display (shorten IPv6)
 */
function formatIpDisplay($ip) {
    // Check if it's IPv6
    if (strpos($ip, ':') !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // Check for IPv4-mapped IPv6
        if (preg_match('/^::ffff:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', $ip, $matches)) {
            return $matches[1];
        }
        
        // Shorten for display
        $parts = explode(':', $ip);
        if (count($parts) > 4) {
            return substr($parts[0], 0, 4) . ':...:' . substr(end($parts), 0, 4);
        }
        return $ip;
    }
    
    // Already has (IPv6) marker from tracker
    if (strpos($ip, '(IPv6)') !== false) {
        return $ip;
    }
    
    return $ip;
}

/**
 * Format country for display with flag
 */
function formatCountry($country, $countryCode) {
    $countryName = htmlspecialchars($country ?: 'Unknown', ENT_QUOTES, 'UTF-8');

    if (empty($countryCode)) {
        return '<i class="fas fa-globe text-muted me-2"></i> ' . $countryName;
    }

    $code = strtoupper(trim($countryCode));
    if ($code === 'XX' || $code === 'LO' || !preg_match('/^[A-Z]{2}$/', $code)) {
        return '<i class="fas fa-globe text-muted me-2"></i> ' . $countryName;
    }

    $codeLower = strtolower($code);
    $flagAlt = htmlspecialchars($code . ' flag', ENT_QUOTES, 'UTF-8');

    // Use image flags for consistent rendering across Windows/browser font combinations.
    return '<img src="https://flagcdn.com/16x12/' . $codeLower . '.png" '
        . 'srcset="https://flagcdn.com/32x24/' . $codeLower . '.png 2x" '
        . 'width="16" height="12" class="me-2" alt="' . $flagAlt . '" '
        . 'loading="lazy" decoding="async" '
        . 'onerror="this.onerror=null;this.style.display=\'none\';if(this.nextElementSibling){this.nextElementSibling.style.display=\'inline-block\';}">'
        . '<i class="fas fa-globe text-muted me-2" style="display:none;"></i>'
        . $countryName;
}

/**
 * Build a readable suspicion reason for dashboard tables
 */
function buildSuspicionReason($row, $highTrafficThreshold) {
    $reasons = [];

    if (!empty($row['ua_flag'])) {
        $reasons[] = 'Suspicious user agent';
    }
    if (!empty($row['probe_hits']) && (int)$row['probe_hits'] > 0) {
        $reasons[] = 'Scanner/probing URLs';
    }
    if (!empty($row['ref_spam_hits']) && (int)$row['ref_spam_hits'] > 0) {
        $reasons[] = 'Spam referer pattern';
    }
    if (!empty($row['visit_count']) && (int)$row['visit_count'] >= $highTrafficThreshold) {
        $reasons[] = 'Abnormally high activity';
    }

    if (empty($reasons)) {
        return 'Heuristic anomaly';
    }

    return implode(', ', $reasons);
}

/**
 * Format timestamp for display as Dubai local time (+04:00)
 * Input is expected to be already converted in SQL.
 */
function formatDubaiDateTime($dateTime, $format = 'M d, Y H:i:s') {
    if (empty($dateTime)) {
        return '-';
    }

    try {
        $dubai = new DateTime($dateTime, new DateTimeZone('Asia/Dubai'));
        return $dubai->format($format);
    } catch (Exception $e) {
        return date($format, strtotime($dateTime));
    }
}

// Get date range from query parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$timeframe = $_GET['timeframe'] ?? '30days';

// Preset timeframes
if ($timeframe && $timeframe != 'custom') {
    switch ($timeframe) {
        case 'today':
            $startDate = $endDate = date('Y-m-d');
            break;
        case '7days':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            $endDate = date('Y-m-d');
            break;
        case '30days':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $endDate = date('Y-m-d');
            break;
        case '90days':
            $startDate = date('Y-m-d', strtotime('-90 days'));
            $endDate = date('Y-m-d');
            break;
        case 'thismonth':
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-d');
            break;
        case 'lastmonth':
            $startDate = date('Y-m-01', strtotime('first day of last month'));
            $endDate = date('Y-m-t', strtotime('last day of last month'));
            break;
    }
}

// Validate dates
if (strtotime($startDate) === false) $startDate = date('Y-m-d', strtotime('-30 days'));
if (strtotime($endDate) === false) $endDate = date('Y-m-d');
if (strtotime($startDate) > strtotime($endDate)) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

$visitDateExpr = "DATE(CONVERT_TZ(visit_time, '+00:00', '+04:00'))";
$visitDateTimeExpr = "CONVERT_TZ(visit_time, '+00:00', '+04:00')";

// Get visitor statistics
$stats = [];

// Total Unique Visitors
$stats['total_visitors'] = $db->fetch(
    "SELECT COUNT(DISTINCT ip_address) as count FROM visitors WHERE $visitDateExpr BETWEEN ? AND ?",
    [$startDate, $endDate]
)['count'];

// Total Visits
$stats['total_visits'] = $db->fetch(
    "SELECT COUNT(*) as count FROM visitors WHERE $visitDateExpr BETWEEN ? AND ?",
    [$startDate, $endDate]
)['count'];

// Average visits per day
$daysDiff = max(1, (strtotime($endDate) - strtotime($startDate)) / 86400 + 1);
$stats['avg_visits_per_day'] = round($stats['total_visits'] / $daysDiff, 1);

// Traffic quality heuristics (helps separate likely customers from machines/scanners)
$machineUserAgentRegex = 'bot|crawler|spider|headless|selenium|phantom|scrapy|python|curl|wget|go-http-client|httpclient|okhttp|node-fetch|aiohttp|java/';
$suspiciousPageRegex = 'wp-admin|wp-login|xmlrpc|\\.env|phpmyadmin|adminer|/boaform|/cgi-bin|/vendor/phpunit|/\\.git';
$suspiciousRefererRegex = 'semalt|darodar|buttons-for-website|trafficmonetize|free-share-buttons';
$highTrafficThreshold = max(80, (int)($daysDiff * 15));

// Returning visitors (IPs with more than 1 visit)
$stats['returning_visitors'] = $db->fetch(
    "SELECT COUNT(DISTINCT ip_address) as count 
     FROM visitors 
     WHERE ip_address IN (
         SELECT ip_address 
         FROM visitors 
         WHERE $visitDateExpr BETWEEN ? AND ?
         GROUP BY ip_address 
         HAVING COUNT(*) > 1
     ) AND $visitDateExpr BETWEEN ? AND ?",
    [$startDate, $endDate, $startDate, $endDate]
)['count'];

// New visitors (first time in the period)
$stats['new_visitors'] = $stats['total_visitors'] - $stats['returning_visitors'];

// Conversion rate (visitors who became customers)
$conversions = $db->fetch(
    "SELECT COUNT(DISTINCT c.id) as count 
     FROM customers c 
     WHERE DATE(c.created_at) BETWEEN ? AND ?",
    [$startDate, $endDate]
)['count'];
$stats['conversion_rate'] = $stats['total_visitors'] > 0 
    ? round(($conversions / $stats['total_visitors']) * 100, 2) 
    : 0;

// Suspicious / machine-like traffic summary
$suspiciousTraffic = $db->fetch(
    "SELECT
        COUNT(*) AS suspicious_visits,
        COUNT(DISTINCT ip_address) AS suspicious_visitors
     FROM visitors v
     WHERE DATE(CONVERT_TZ(v.visit_time, '+00:00', '+04:00')) BETWEEN ? AND ?
       AND (
            LOWER(COALESCE(v.user_agent, '')) REGEXP ?
            OR LOWER(COALESCE(v.page_url, '')) REGEXP ?
            OR LOWER(COALESCE(v.referer, '')) REGEXP ?
            OR v.ip_address IN (
                SELECT ip_address FROM (
                    SELECT ip_address
                    FROM visitors
                    WHERE $visitDateExpr BETWEEN ? AND ?
                    GROUP BY ip_address
                    HAVING COUNT(*) >= ?
                ) heavy_ips
            )
       )",
    [
        $startDate,
        $endDate,
        $machineUserAgentRegex,
        $suspiciousPageRegex,
        $suspiciousRefererRegex,
        $startDate,
        $endDate,
        $highTrafficThreshold
    ]
);

$stats['suspicious_visits'] = (int)($suspiciousTraffic['suspicious_visits'] ?? 0);
$stats['suspicious_visitors'] = (int)($suspiciousTraffic['suspicious_visitors'] ?? 0);
$stats['estimated_real_visits'] = max(0, (int)$stats['total_visits'] - $stats['suspicious_visits']);
$stats['estimated_real_visitors'] = max(0, (int)$stats['total_visitors'] - $stats['suspicious_visitors']);
$stats['suspicious_rate'] = $stats['total_visits'] > 0
    ? round(($stats['suspicious_visits'] / $stats['total_visits']) * 100, 1)
    : 0;
$stats['quality_conversion_rate'] = $stats['estimated_real_visitors'] > 0
    ? round(($conversions / $stats['estimated_real_visitors']) * 100, 2)
    : 0;

// Top suspicious IPs in the selected period
$suspiciousIps = $db->fetchAll(
    "SELECT
        ip_address,
        COUNT(*) AS visit_count,
          MAX($visitDateTimeExpr) AS last_visit,
        MAX(country) AS country,
        MAX(country_code) AS country_code,
        MAX(CASE WHEN LOWER(COALESCE(user_agent, '')) REGEXP ? THEN 1 ELSE 0 END) AS ua_flag,
        SUM(CASE WHEN LOWER(COALESCE(page_url, '')) REGEXP ? THEN 1 ELSE 0 END) AS probe_hits,
        SUM(CASE WHEN LOWER(COALESCE(referer, '')) REGEXP ? THEN 1 ELSE 0 END) AS ref_spam_hits
     FROM visitors
      WHERE $visitDateExpr BETWEEN ? AND ?
     GROUP BY ip_address
     HAVING ua_flag = 1
        OR probe_hits > 0
        OR ref_spam_hits > 0
        OR visit_count >= ?
     ORDER BY visit_count DESC, last_visit DESC
     LIMIT 20",
    [
        $machineUserAgentRegex,
        $suspiciousPageRegex,
        $suspiciousRefererRegex,
        $startDate,
        $endDate,
        $highTrafficThreshold
    ]
);

$suspiciousIpMap = [];
foreach ($suspiciousIps as $suspiciousIp) {
    $suspiciousIpMap[$suspiciousIp['ip_address']] = $suspiciousIp;
}

// Daily visitor trend
$dailyVisitors = $db->fetchAll(
    "SELECT 
        $visitDateExpr as date,
        COUNT(*) as total_visits,
        COUNT(DISTINCT ip_address) as unique_visitors
     FROM visitors 
     WHERE $visitDateExpr BETWEEN ? AND ?
     GROUP BY $visitDateExpr
     ORDER BY date ASC",
    [$startDate, $endDate]
);

// Hourly distribution (for current period)
$hourlyDistribution = $db->fetchAll(
    "SELECT 
        HOUR($visitDateTimeExpr) as hour,
        COUNT(*) as visits
     FROM visitors 
     WHERE $visitDateExpr BETWEEN ? AND ?
     GROUP BY HOUR($visitDateTimeExpr)
     ORDER BY hour ASC",
    [$startDate, $endDate]
);

// Day of week distribution
$weekdayDistribution = $db->fetchAll(
    "SELECT 
        DAYNAME($visitDateTimeExpr) as day_name,
        DAYOFWEEK($visitDateTimeExpr) as day_num,
        COUNT(*) as visits
     FROM visitors 
     WHERE $visitDateExpr BETWEEN ? AND ?
     GROUP BY DAYNAME($visitDateTimeExpr), DAYOFWEEK($visitDateTimeExpr)
     ORDER BY day_num ASC",
    [$startDate, $endDate]
);

// Top visitor IPs
$topVisitors = $db->fetchAll(
    "SELECT 
        ip_address,
        COUNT(*) as visit_count,
          MIN($visitDateTimeExpr) as first_visit,
          MAX($visitDateTimeExpr) as last_visit,
        MAX(country) as country,
        MAX(country_code) as country_code
     FROM visitors 
      WHERE $visitDateExpr BETWEEN ? AND ?
     GROUP BY ip_address
     ORDER BY visit_count DESC
     LIMIT 20",
    [$startDate, $endDate]
);

// Recent visitors
$recentVisitors = $db->fetchAll(
    "SELECT ip_address, $visitDateTimeExpr AS visit_time, country, country_code 
     FROM visitors 
     ORDER BY visit_time DESC 
     LIMIT 50"
);

// Visitors by country
$visitorsByCountry = $db->fetchAll(
    "SELECT 
        COALESCE(country, 'Unknown') as country,
        COALESCE(country_code, 'XX') as country_code,
        COUNT(*) as visits,
        COUNT(DISTINCT ip_address) as unique_visitors
     FROM visitors 
    WHERE $visitDateExpr BETWEEN ? AND ?
     GROUP BY country, country_code
     ORDER BY visits DESC
     LIMIT 15",
    [$startDate, $endDate]
);

// Compare with previous period
$periodLength = (strtotime($endDate) - strtotime($startDate)) / 86400;
$prevStartDate = date('Y-m-d', strtotime($startDate . " -" . ($periodLength + 1) . " days"));
$prevEndDate = date('Y-m-d', strtotime($startDate . " -1 day"));

$prevStats = [];
$prevStats['total_visits'] = $db->fetch(
    "SELECT COUNT(*) as count FROM visitors WHERE $visitDateExpr BETWEEN ? AND ?",
    [$prevStartDate, $prevEndDate]
)['count'];

$prevStats['total_visitors'] = $db->fetch(
    "SELECT COUNT(DISTINCT ip_address) as count FROM visitors WHERE $visitDateExpr BETWEEN ? AND ?",
    [$prevStartDate, $prevEndDate]
)['count'];

// Calculate percentage changes
$visitChange = $prevStats['total_visits'] > 0 
    ? round((($stats['total_visits'] - $prevStats['total_visits']) / $prevStats['total_visits']) * 100, 1)
    : 0;

$visitorChange = $prevStats['total_visitors'] > 0 
    ? round((($stats['total_visitors'] - $prevStats['total_visitors']) / $prevStats['total_visitors']) * 100, 1)
    : 0;

$pageTitle = "Visitor Analytics";
?>

<?php require_once 'header.php'; ?>

<style>
    .card-header h5 {
        margin: 0;
    }
    .sticky-top {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #fff;
    }
    .table-responsive {
        position: relative;
    }
    canvas {
        max-height: 400px;
    }
</style>

    <div class="page-header">
        <h1><i class="fas fa-chart-line me-2"></i>Visitor Analytics</h1>
        <div class="header-actions">
            <a href="backfill_visitor_countries.php" class="btn btn-outline-primary btn-sm" target="_blank" title="Backfill missing country data">
                <i class="fas fa-globe me-1"></i>Backfill Countries
            </a>
        </div>
    </div>

    <!-- Date Range Selector -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Analytics Period</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-auto">
                    <select name="timeframe" class="form-select form-select-sm" id="timeframeSelect">
                        <option value="today" <?php echo $timeframe == 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="7days" <?php echo $timeframe == '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30days" <?php echo $timeframe == '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90days" <?php echo $timeframe == '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="thismonth" <?php echo $timeframe == 'thismonth' ? 'selected' : ''; ?>>This Month</option>
                        <option value="lastmonth" <?php echo $timeframe == 'lastmonth' ? 'selected' : ''; ?>>Last Month</option>
                        <option value="custom" <?php echo $timeframe == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                <div class="col-auto" id="customDates" style="display: <?php echo $timeframe == 'custom' ? 'block' : 'none'; ?>;">
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-auto" id="customDatesTo" style="display: <?php echo $timeframe == 'custom' ? 'block' : 'none'; ?>;">
                    <span>to</span>
                </div>
                <div class="col-auto" id="customDatesEnd" style="display: <?php echo $timeframe == 'custom' ? 'block' : 'none'; ?>;">
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search me-1"></i>Apply
                    </button>
                </div>
            </form>
            <div class="mt-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    <?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>
                    (<?php echo round($daysDiff); ?> days, timezone: UTC+04:00 Dubai)
                </small>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <!-- Total Visits -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-2">Total Visits</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['total_visits']); ?></h3>
                            <?php if ($visitChange != 0): ?>
                            <small class="<?php echo $visitChange > 0 ? 'text-success' : 'text-danger'; ?>">
                                <i class="fas fa-arrow-<?php echo $visitChange > 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($visitChange); ?>% vs previous period
                            </small>
                            <?php endif; ?>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-eye fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Unique Visitors -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-2">Unique Visitors</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['total_visitors']); ?></h3>
                            <?php if ($visitorChange != 0): ?>
                            <small class="<?php echo $visitorChange > 0 ? 'text-success' : 'text-danger'; ?>">
                                <i class="fas fa-arrow-<?php echo $visitorChange > 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($visitorChange); ?>% vs previous period
                            </small>
                            <?php endif; ?>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Avg Visits/Day -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-2">Avg Visits/Day</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['avg_visits_per_day'], 1); ?></h3>
                            <small class="text-muted">
                                <?php echo number_format($stats['new_visitors']); ?> new / 
                                <?php echo number_format($stats['returning_visitors']); ?> returning
                            </small>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Conversion Rate -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-2">Conversion Rate</h6>
                            <h3 class="mb-0"><?php echo $stats['conversion_rate']; ?>%</h3>
                            <small class="text-muted">
                                <?php echo number_format($conversions); ?> visitors became customers
                            </small>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-shopping-cart fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- End Statistics Cards Row -->

    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="card border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-2">Estimated Real Visitors</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['estimated_real_visitors']); ?></h3>
                            <small class="text-muted">
                                <?php echo number_format($stats['estimated_real_visits']); ?> visits from likely human traffic
                            </small>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-user-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card border-danger h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-2">Suspicious / Machine Traffic</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['suspicious_visitors']); ?></h3>
                            <small class="text-muted">
                                <?php echo number_format($stats['suspicious_visits']); ?> visits (<?php echo $stats['suspicious_rate']; ?>% of traffic)
                            </small>
                        </div>
                        <div class="text-danger">
                            <i class="fas fa-robot fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border mb-4" role="alert">
        <i class="fas fa-shield-alt text-primary me-2"></i>
        Traffic quality filtering is heuristic-based. Estimated customer conversion on likely-human visitors: <strong><?php echo $stats['quality_conversion_rate']; ?>%</strong>.
        High-activity threshold for this period: <strong><?php echo number_format($highTrafficThreshold); ?> visits/IP</strong>.
    </div>

    <!-- Charts Row 1 -->
    <div class="row mb-4">
        <!-- Daily Visitors Trend -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-area me-2"></i>Daily Visitor Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyVisitorsChart" height="80"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Hourly Distribution -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Peak Hours</h5>
                </div>
                <div class="card-body">
                    <canvas id="hourlyChart" height="160"></canvas>
                </div>
            </div>
        </div>
    </div><!-- End Charts Row 1 -->

    <!-- Charts Row 2 -->
    <div class="row mb-4">
        <!-- Day of Week Distribution -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calendar-week me-2"></i>Traffic by Day of Week</h5>
                </div>
                <div class="card-body">
                    <canvas id="weekdayChart" height="150"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Visitor Type Distribution -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-check me-2"></i>Visitor Types</h5>
                </div>
                <div class="card-body">
                    <canvas id="visitorTypeChart" height="150"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Visitors by Country -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Visitors by Country</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($visitorsByCountry)): ?>
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Country</th>
                                    <th class="text-end">Visits</th>
                                    <th class="text-end">Unique</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visitorsByCountry as $row): ?>
                                <tr>
                                    <td>
                                        <?php echo formatCountry($row['country'], $row['country_code']); ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-primary"><?php echo number_format($row['visits']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-secondary"><?php echo number_format($row['unique_visitors']); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No country data yet</p>
                        <small class="text-muted">Country data will appear for new visitors</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div><!-- End Charts Row 2 -->

    <!-- Tables Row -->
    <div class="row mb-4">
        <!-- Top Visitors -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list-ol me-2"></i>Top Visitors by Activity</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($topVisitors)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Country</th>
                                    <th>Visits</th>
                                    <th>First Visit</th>
                                    <th>Last Visit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($topVisitors, 0, 10) as $visitor): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars(formatIpDisplay($visitor['ip_address'])); ?></code>
                                        <?php if (isset($suspiciousIpMap[$visitor['ip_address']])): ?>
                                            <span class="badge bg-danger ms-1">Suspicious</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span title="<?php echo htmlspecialchars($visitor['country'] ?? 'Unknown'); ?>">
                                            <?php echo formatCountry($visitor['country'] ?? null, $visitor['country_code'] ?? null); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo number_format($visitor['visit_count']); ?></span>
                                    </td>
                                    <td>
                                        <small><?php echo formatDubaiDateTime($visitor['first_visit'], 'M d, H:i'); ?></small>
                                    </td>
                                    <td>
                                        <small><?php echo formatDubaiDateTime($visitor['last_visit'], 'M d, H:i'); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No visitor data for this period</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Visitors -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Visitors</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recentVisitors)): ?>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>IP Address</th>
                                    <th>Country</th>
                                    <th>Visit Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentVisitors as $visitor): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars(formatIpDisplay($visitor['ip_address'])); ?></code>
                                    </td>
                                    <td>
                                        <span title="<?php echo htmlspecialchars($visitor['country'] ?? 'Unknown'); ?>">
                                            <?php echo formatCountry($visitor['country'] ?? null, $visitor['country_code'] ?? null); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo formatDubaiDateTime($visitor['visit_time'], 'M d, Y H:i:s'); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No recent visitors</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div><!-- End Tables Row -->

    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-danger">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-danger"><i class="fas fa-user-secret me-2"></i>Suspicious / Machine-like IPs</h5>
                    <small class="text-muted">Heuristic detection</small>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($suspiciousIps)): ?>
                    <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>IP Address</th>
                                    <th>Country</th>
                                    <th class="text-end">Visits</th>
                                    <th>Reason</th>
                                    <th>Last Seen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suspiciousIps as $ipRow): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars(formatIpDisplay($ipRow['ip_address'])); ?></code></td>
                                    <td><?php echo formatCountry($ipRow['country'] ?? null, $ipRow['country_code'] ?? null); ?></td>
                                    <td class="text-end"><span class="badge bg-danger"><?php echo number_format($ipRow['visit_count']); ?></span></td>
                                    <td><small><?php echo htmlspecialchars(buildSuspicionReason($ipRow, $highTrafficThreshold)); ?></small></td>
                                    <td><small><?php echo formatDubaiDateTime($ipRow['last_visit'], 'M d, Y H:i'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted mb-0">No suspicious traffic detected in this date range.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div><!-- End main-content -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Timeframe selector
document.getElementById('timeframeSelect').addEventListener('change', function() {
    const customDates = document.getElementById('customDates');
    const customDatesTo = document.getElementById('customDatesTo');
    const customDatesEnd = document.getElementById('customDatesEnd');
    
    if (this.value === 'custom') {
        customDates.style.display = 'block';
        customDatesTo.style.display = 'block';
        customDatesEnd.style.display = 'block';
    } else {
        customDates.style.display = 'none';
        customDatesTo.style.display = 'none';
        customDatesEnd.style.display = 'none';
        this.form.submit();
    }
});

// Chart colors
const primaryColor = 'rgb(13, 110, 253)';
const successColor = 'rgb(25, 135, 84)';
const infoColor = 'rgb(13, 202, 240)';
const warningColor = 'rgb(255, 193, 7)';
const dangerColor = 'rgb(220, 53, 69)';

// Daily Visitors Chart
const dailyVisitorsCtx = document.getElementById('dailyVisitorsChart').getContext('2d');
const dailyVisitorsChart = new Chart(dailyVisitorsCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($dailyVisitors, 'date')); ?>,
        datasets: [{
            label: 'Total Visits',
            data: <?php echo json_encode(array_column($dailyVisitors, 'total_visits')); ?>,
            borderColor: primaryColor,
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Unique Visitors',
            data: <?php echo json_encode(array_column($dailyVisitors, 'unique_visitors')); ?>,
            borderColor: successColor,
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Hourly Distribution Chart
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
const hourlyData = <?php echo json_encode($hourlyDistribution); ?>;

// Fill in missing hours with 0
const hourlyFilled = [];
for (let i = 0; i < 24; i++) {
    const found = hourlyData.find(h => parseInt(h.hour) === i);
    hourlyFilled.push(found ? parseInt(found.visits) : 0);
}

const hourlyChart = new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: Array.from({length: 24}, (_, i) => i + ':00'),
        datasets: [{
            label: 'Visits by Hour',
            data: hourlyFilled,
            backgroundColor: infoColor,
            borderColor: infoColor,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Weekday Distribution Chart
const weekdayCtx = document.getElementById('weekdayChart').getContext('2d');
const weekdayData = <?php echo json_encode($weekdayDistribution); ?>;

const weekdayChart = new Chart(weekdayCtx, {
    type: 'bar',
    data: {
        labels: weekdayData.map(d => d.day_name),
        datasets: [{
            label: 'Visits',
            data: weekdayData.map(d => parseInt(d.visits)),
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)',
                'rgba(255, 159, 64, 0.7)',
                'rgba(199, 199, 199, 0.7)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Visitor Type Chart (New vs Returning)
const visitorTypeCtx = document.getElementById('visitorTypeChart').getContext('2d');
const visitorTypeChart = new Chart(visitorTypeCtx, {
    type: 'doughnut',
    data: {
        labels: ['New Visitors', 'Returning Visitors'],
        datasets: [{
            data: [<?php echo $stats['new_visitors']; ?>, <?php echo $stats['returning_visitors']; ?>],
            backgroundColor: [successColor, warningColor],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

</div><!-- End main-content -->
</body>
</html>