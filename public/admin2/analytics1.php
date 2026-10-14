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
 * Get country flag emoji from country code
 */
function getCountryFlag($countryCode) {
    if (empty($countryCode) || $countryCode === 'XX' || $countryCode === 'LO') {
        return '🌐';
    }
    
    // Convert country code to flag emoji
    $countryCode = strtoupper($countryCode);
    $flag = '';
    for ($i = 0; $i < strlen($countryCode); $i++) {
        $flag .= mb_chr(ord($countryCode[$i]) - ord('A') + 0x1F1E6);
    }
    return $flag;
}

/**
 * Format country for display with flag
 */
function formatCountry($country, $countryCode) {
    $flag = getCountryFlag($countryCode);
    $name = $country ?: 'Unknown';
    return $flag . ' ' . $name;
}

// Get date range from query parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')); // Last 30 days
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
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

// Get visitor statistics
$stats = [];

// Total Unique Visitors
$stats['total_visitors'] = $db->fetch(
    "SELECT COUNT(DISTINCT ip_address) as count FROM visitors WHERE DATE(visit_time) BETWEEN ? AND ?",
    [$startDate, $endDate]
)['count'];

// Total Visits
$stats['total_visits'] = $db->fetch(
    "SELECT COUNT(*) as count FROM visitors WHERE DATE(visit_time) BETWEEN ? AND ?",
    [$startDate, $endDate]
)['count'];

// Average visits per day
$daysDiff = max(1, (strtotime($endDate) - strtotime($startDate)) / 86400 + 1);
$stats['avg_visits_per_day'] = round($stats['total_visits'] / $daysDiff, 1);

// Returning visitors (IPs with more than 1 visit)
$stats['returning_visitors'] = $db->fetch(
    "SELECT COUNT(DISTINCT ip_address) as count 
     FROM visitors 
     WHERE ip_address IN (
         SELECT ip_address 
         FROM visitors 
         WHERE DATE(visit_time) BETWEEN ? AND ?
         GROUP BY ip_address 
         HAVING COUNT(*) > 1
     ) AND DATE(visit_time) BETWEEN ? AND ?",
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

// Daily visitor trend
$dailyVisitors = $db->fetchAll(
    "SELECT 
        DATE(visit_time) as date,
        COUNT(*) as total_visits,
        COUNT(DISTINCT ip_address) as unique_visitors
     FROM visitors 
     WHERE DATE(visit_time) BETWEEN ? AND ?
     GROUP BY DATE(visit_time)
     ORDER BY date ASC",
    [$startDate, $endDate]
);

// Hourly distribution (for current period)
$hourlyDistribution = $db->fetchAll(
    "SELECT 
        HOUR(visit_time) as hour,
        COUNT(*) as visits
     FROM visitors 
     WHERE DATE(visit_time) BETWEEN ? AND ?
     GROUP BY HOUR(visit_time)
     ORDER BY hour ASC",
    [$startDate, $endDate]
);

// Day of week distribution
$weekdayDistribution = $db->fetchAll(
    "SELECT 
        DAYNAME(visit_time) as day_name,
        DAYOFWEEK(visit_time) as day_num,
        COUNT(*) as visits
     FROM visitors 
     WHERE DATE(visit_time) BETWEEN ? AND ?
     GROUP BY DAYNAME(visit_time), DAYOFWEEK(visit_time)
     ORDER BY day_num ASC",
    [$startDate, $endDate]
);

// Top visitor IPs
$topVisitors = $db->fetchAll(
    "SELECT 
        ip_address,
        COUNT(*) as visit_count,
        MIN(visit_time) as first_visit,
        MAX(visit_time) as last_visit,
        MAX(country) as country,
        MAX(country_code) as country_code
     FROM visitors 
     WHERE DATE(visit_time) BETWEEN ? AND ?
     GROUP BY ip_address
     ORDER BY visit_count DESC
     LIMIT 20",
    [$startDate, $endDate]
);

// Recent visitors
$recentVisitors = $db->fetchAll(
    "SELECT ip_address, visit_time, country, country_code 
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
     WHERE DATE(visit_time) BETWEEN ? AND ?
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
    "SELECT COUNT(*) as count FROM visitors WHERE DATE(visit_time) BETWEEN ? AND ?",
    [$prevStartDate, $prevEndDate]
)['count'];

$prevStats['total_visitors'] = $db->fetch(
    "SELECT COUNT(DISTINCT ip_address) as count FROM visitors WHERE DATE(visit_time) BETWEEN ? AND ?",
    [$prevStartDate, $prevEndDate]
)['count'];

// Calculate percentage changes
$visitChange = $prevStats['total_visits'] > 0 
    ? round((($stats['total_visits'] - $prevStats['total_visits']) / $prevStats['total_visits']) * 100, 1)
    : 0;

$visitorChange = $prevStats['total_visitors'] > 0 
    ? round((($stats['total_visitors'] - $prevStats['total_visitors']) / $prevStats['total_visitors']) * 100, 1)
    : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Analytics - AleppoGift</title>
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin-theme.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            --card-hover-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9fa;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }
        
        .page-header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
        }
        
        .page-header .header-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-2px);
        }
        
        .card-header {
            background: white;
            border-bottom: 2px solid #f0f0f0;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }
        
        .card-header h5 {
            margin: 0;
            color: #2d3748;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Stats Cards */
        .stats-card {
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stats-card.border-primary::before {
            background: var(--primary-gradient);
        }
        
        .stats-card.border-success::before {
            background: var(--success-gradient);
        }
        
        .stats-card.border-info::before {
            background: var(--info-gradient);
        }
        
        .stats-card.border-warning::before {
            background: var(--warning-gradient);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.primary {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            color: #667eea;
        }
        
        .stat-icon.success {
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%);
            color: #f5576c;
        }
        
        .stat-icon.info {
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
            color: #4facfe;
        }
        
        .stat-icon.warning {
            background: linear-gradient(135deg, rgba(250, 112, 154, 0.1) 0%, rgba(254, 225, 64, 0.1) 100%);
            color: #fa709a;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin: 0.5rem 0;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-change {
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }
        
        .stat-change.positive {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
        }
        
        .stat-change.negative {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        /* Date Filter */
        .filter-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
        }
        
        .form-select, .form-control {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.625rem 0.875rem;
            transition: all 0.2s;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 0.625rem 1.25rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid #667eea;
            color: #667eea;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-outline-primary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-1px);
        }
        
        /* Tables */
        .table {
            font-size: 0.9rem;
        }
        
        .table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .table thead th {
            font-weight: 600;
            color: #2d3748;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem;
        }
        
        .table tbody tr {
            transition: background 0.2s;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .table tbody td {
            vertical-align: middle;
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table-responsive {
            border-radius: 8px;
        }
        
        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #f8f9fa !important;
        }
        
        /* Badges */
        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .badge.bg-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .badge.bg-secondary {
            background: #64748b !important;
        }
        
        /* Code blocks */
        code {
            background: #f1f5f9;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #334155;
        }
        
        /* Charts */
        canvas {
            max-height: 350px;
        }
        
        /* Country flag and text */
        .country-cell {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Date range info */
        .date-range-info {
            background: #f8f9fa;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-top: 1rem;
        }
        
        /* Scrollbar styling */
        .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .card-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
<?php require_once 'header.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h1><i class="fas fa-chart-line me-2"></i>Visitor Analytics</h1>
                    <div class="header-actions">
                        <a href="backfill_visitor_countries.php" class="btn btn-outline-light btn-sm" target="_blank" title="Backfill missing country data">
                            <i class="fas fa-globe me-1"></i>Backfill Countries
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <!-- Date Range Selector -->
            <div class="filter-card mb-4">
                <h5 class="mb-3"><i class="fas fa-calendar-alt me-2"></i>Select Time Period</h5>
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label small text-muted mb-2">Timeframe</label>
                        <select name="timeframe" class="form-select" id="timeframeSelect">
                            <option value="today" <?php echo $timeframe == 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="7days" <?php echo $timeframe == '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30days" <?php echo $timeframe == '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="90days" <?php echo $timeframe == '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="thismonth" <?php echo $timeframe == 'thismonth' ? 'selected' : ''; ?>>This Month</option>
                            <option value="lastmonth" <?php echo $timeframe == 'lastmonth' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="custom" <?php echo $timeframe == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3" id="customDates" style="display: <?php echo $timeframe == 'custom' ? 'block' : 'none'; ?>;">
                        <label class="form-label small text-muted mb-2">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="col-lg-2 col-md-3" id="customDatesEnd" style="display: <?php echo $timeframe == 'custom' ? 'block' : 'none'; ?>;">
                        <label class="form-label small text-muted mb-2">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Apply Filter
                        </button>
                    </div>
                </form>
                <div class="date-range-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Showing data for:</strong> 
                    <?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>
                    <span class="text-muted">(<?php echo round($daysDiff); ?> days)</span>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card stats-card border-primary h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <div class="stat-label">Total Visits</div>
                                    <div class="stat-value"><?php echo number_format($stats['total_visits']); ?></div>
                                    <?php if ($visitChange != 0): ?>
                                        <span class="stat-change <?php echo $visitChange > 0 ? 'positive' : 'negative'; ?>">
                                            <i class="fas fa-arrow-<?php echo $visitChange > 0 ? 'up' : 'down'; ?>"></i>
                                            <?php echo abs($visitChange); ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="stat-icon primary">
                                    <i class="fas fa-eye"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card stats-card border-success h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <div class="stat-label">Unique Visitors</div>
                                    <div class="stat-value"><?php echo number_format($stats['total_visitors']); ?></div>
                                    <?php if ($visitorChange != 0): ?>
                                        <span class="stat-change <?php echo $visitorChange > 0 ? 'positive' : 'negative'; ?>">
                                            <i class="fas fa-arrow-<?php echo $visitorChange > 0 ? 'up' : 'down'; ?>"></i>
                                            <?php echo abs($visitorChange); ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="stat-icon success">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card stats-card border-info h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <div class="stat-label">Daily Average</div>
                                    <div class="stat-value"><?php echo number_format($stats['avg_visits_per_day'], 1); ?></div>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-user-plus me-1"></i><?php echo number_format($stats['new_visitors']); ?> new
                                        <span class="mx-1">•</span>
                                        <i class="fas fa-redo me-1"></i><?php echo number_format($stats['returning_visitors']); ?> returning
                                    </small>
                                </div>
                                <div class="stat-icon info">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card stats-card border-warning h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <div class="stat-label">Conversion Rate</div>
                                    <div class="stat-value"><?php echo $stats['conversion_rate']; ?>%</div>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-shopping-bag me-1"></i><?php echo number_format($conversions); ?> became customers
                                    </small>
                                </div>
                                <div class="stat-icon warning">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row mb-4">
                <!-- Daily Visitors Trend -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-area me-2"></i>Daily Visitor Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyVisitorsChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Hourly Distribution -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-clock me-2"></i>Peak Hours</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="hourlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row mb-4">
                <!-- Day of Week Distribution -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-calendar-week me-2"></i>Weekly Pattern</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="weekdayChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Visitor Type Distribution -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-user-check me-2"></i>Visitor Types</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="visitorTypeChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Visitors by Country -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-globe-americas me-2"></i>Top Countries</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($visitorsByCountry)): ?>
                                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="sticky-top">
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
                                                    <span class="country-cell">
                                                        <?php echo formatCountry($row['country'], $row['country_code']); ?>
                                                    </span>
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
                                <div class="empty-state">
                                    <i class="fas fa-globe"></i>
                                    <p class="mb-0">No country data yet</p>
                                    <small>Country data will appear for new visitors</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="row mb-4">
                <!-- Top Visitors -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-list-ol me-2"></i>Most Active Visitors</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($topVisitors)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>IP Address</th>
                                                <th>Country</th>
                                                <th class="text-center">Visits</th>
                                                <th>First Visit</th>
                                                <th>Last Visit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($topVisitors, 0, 10) as $visitor): ?>
                                            <tr>
                                                <td>
                                                    <code><?php echo htmlspecialchars(formatIpDisplay($visitor['ip_address'])); ?></code>
                                                </td>
                                                <td>
                                                    <span title="<?php echo htmlspecialchars($visitor['country'] ?? 'Unknown'); ?>">
                                                        <?php echo formatCountry($visitor['country'] ?? null, $visitor['country_code'] ?? null); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary"><?php echo number_format($visitor['visit_count']); ?></span>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo date('M d, H:i', strtotime($visitor['first_visit'])); ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo date('M d, H:i', strtotime($visitor['last_visit'])); ?></small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-users-slash"></i>
                                    <p class="mb-0">No visitor data for this period</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Visitors -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history me-2"></i>Recent Activity</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($recentVisitors)): ?>
                                <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="sticky-top">
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
                                                    <small class="text-muted"><?php echo date('M d, Y H:i:s', strtotime($visitor['visit_time'])); ?></small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-clock"></i>
                                    <p class="mb-0">No recent visitors</p>
                                </div>
                            <?php endif; ?>
                        </div>
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
            const customDatesEnd = document.getElementById('customDatesEnd');
            
            if (this.value === 'custom') {
                customDates.style.display = 'block';
                customDatesEnd.style.display = 'block';
            } else {
                customDates.style.display = 'none';
                customDatesEnd.style.display = 'none';
                this.form.submit();
            }
        });

        // Chart colors - Modern gradient style
        const chartColors = {
            primary: {
                gradient: null,
                solid: 'rgb(102, 126, 234)',
                light: 'rgba(102, 126, 234, 0.1)'
            },
            success: {
                gradient: null,
                solid: 'rgb(245, 87, 108)',
                light: 'rgba(245, 87, 108, 0.1)'
            },
            info: {
                gradient: null,
                solid: 'rgb(79, 172, 254)',
                light: 'rgba(79, 172, 254, 0.1)'
            }
        };

        // Common chart options
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            family: 'Inter',
                            size: 12
                        }
                    }
                }
            }
        };

        // Daily Visitors Chart
        const dailyVisitorsCtx = document.getElementById('dailyVisitorsChart').getContext('2d');
        const dailyGradient1 = dailyVisitorsCtx.createLinearGradient(0, 0, 0, 300);
        dailyGradient1.addColorStop(0, 'rgba(102, 126, 234, 0.2)');
        dailyGradient1.addColorStop(1, 'rgba(102, 126, 234, 0)');
        
        const dailyGradient2 = dailyVisitorsCtx.createLinearGradient(0, 0, 0, 300);
        dailyGradient2.addColorStop(0, 'rgba(245, 87, 108, 0.2)');
        dailyGradient2.addColorStop(1, 'rgba(245, 87, 108, 0)');

        const dailyVisitorsChart = new Chart(dailyVisitorsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($dailyVisitors, 'date')); ?>,
                datasets: [{
                    label: 'Total Visits',
                    data: <?php echo json_encode(array_column($dailyVisitors, 'total_visits')); ?>,
                    borderColor: chartColors.primary.solid,
                    backgroundColor: dailyGradient1,
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 2
                }, {
                    label: 'Unique Visitors',
                    data: <?php echo json_encode(array_column($dailyVisitors, 'unique_visitors')); ?>,
                    borderColor: chartColors.success.solid,
                    backgroundColor: dailyGradient2,
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                family: 'Inter'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Inter'
                            }
                        },
                        grid: {
                            display: false
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
                    backgroundColor: chartColors.info.solid,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                family: 'Inter'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Inter',
                                size: 10
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Weekday Distribution Chart
        const weekdayCtx = document.getElementById('weekdayChart').getContext('2d');
        const weekdayData = <?php echo json_encode($weekdayDistribution); ?>;

        const weekdayColors = [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 206, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
            'rgba(199, 199, 199, 0.8)'
        ];

        const weekdayChart = new Chart(weekdayCtx, {
            type: 'bar',
            data: {
                labels: weekdayData.map(d => d.day_name),
                datasets: [{
                    label: 'Visits',
                    data: weekdayData.map(d => parseInt(d.visits)),
                    backgroundColor: weekdayColors,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                family: 'Inter'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Inter'
                            }
                        },
                        grid: {
                            display: false
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
                    backgroundColor: [
                        chartColors.success.solid,
                        chartColors.primary.solid
                    ],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 10
                }]
            },
            options: {
                ...commonOptions,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    </script>

</body>
</html>
