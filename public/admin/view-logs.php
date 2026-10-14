<?php
/**
 * Admin Server Log Viewer
 * Shows application log files from the admin panel.
 */

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');

require_admin_login();

$page_title = 'Server Logs';

// Get log file parameter
$log_file = $_GET['file'] ?? 'error.log';
$allowed_logs = ['error.log', 'security.log', 'app.log'];

// Security: Only allow viewing specific authorized logs
if (!in_array($log_file, $allowed_logs)) {
    $log_file = 'error.log';
}

// Construct full path to log file
$log_path = $root_dir . '/logs/' . $log_file;

// Get number of lines to display
$lines = isset($_GET['lines']) && is_numeric($_GET['lines']) ? (int)$_GET['lines'] : 100;
$lines = min($lines, 1000); // Max 1000 lines

ob_start();
require_once __DIR__ . '/includes/header.php';
?>
<style>
        body { background-color: #f8f9fa; }
        .navbar { background-color: #2c3e50; }
        .navbar-brand { color: white !important; font-weight: bold; }
        .container { margin-top: 30px; }
        .log-container { 
            background: #1e1e1e; 
            color: #d4d4d4; 
            padding: 15px; 
            border-radius: 5px; 
            max-height: 600px; 
            overflow-y: auto; 
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .error { color: #ff6b6b; }
        .warning { color: #ffd93d; }
        .success { color: #51cf66; }
        .info { color: #74c0fc; }
        .log-header { 
            background: #2c3e50; 
            color: white; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 15px;
        }
        .btn-group { margin-bottom: 15px; }
        .file-tabs { 
            display: flex; 
            gap: 10px; 
            margin-bottom: 15px; 
            flex-wrap: wrap;
        }
        .file-tabs a {
            padding: 8px 15px;
            background: #e9ecef;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            border: 2px solid #dee2e6;
            transition: all 0.3s;
        }
        .file-tabs a:hover { background: #dee2e6; }
        .file-tabs a.active { 
            background: #2c3e50; 
            color: white; 
            border-color: #2c3e50;
        }
        .stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #2c3e50;
        }
        .stat-value { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #666; font-size: 12px; }
    </style>

    <div class="container-fluid px-0">
        <!-- Header -->
        <div class="log-header">
            <h2>📋 Server Logs Viewer</h2>
            <p class="mb-0">Debug production issues in real-time</p>
        </div>

        <!-- File Tabs -->
        <div class="file-tabs">
            <?php foreach ($allowed_logs as $log): ?>
                <a href="?file=<?= htmlspecialchars($log) ?>&lines=<?= $lines ?>" 
                   class="<?= $log === $log_file ? 'active' : '' ?>">
                    📄 <?= htmlspecialchars($log) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Controls -->
        <div class="btn-group" role="group">
            <a href="?file=<?= htmlspecialchars($log_file) ?>&lines=50" class="btn btn-outline-secondary">Last 50 Lines</a>
            <a href="?file=<?= htmlspecialchars($log_file) ?>&lines=100" class="btn btn-outline-secondary <?= $lines == 100 ? 'active' : '' ?>">Last 100 Lines</a>
            <a href="?file=<?= htmlspecialchars($log_file) ?>&lines=500" class="btn btn-outline-secondary">Last 500 Lines</a>
            <a href="?file=<?= htmlspecialchars($log_file) ?>&lines=1000" class="btn btn-outline-secondary">Last 1000 Lines</a>
        </div>

        <!-- Statistics -->
        <?php if (file_exists($log_path)):
            $content = file_get_contents($log_path);
            $total_lines = substr_count($content, "\n");
            $file_size = filesize($log_path);
            $size_mb = round($file_size / (1024 * 1024), 2);
            $error_count = substr_count($content, 'error') + substr_count($content, 'Error') + substr_count($content, 'ERROR');
            $warning_count = substr_count($content, 'warning') + substr_count($content, 'Warning') + substr_count($content, 'WARNING');
        ?>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($total_lines) ?></div>
                    <div class="stat-label">Total Lines</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $size_mb ?> MB</div>
                    <div class="stat-label">File Size</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value error"><?= $error_count ?></div>
                    <div class="stat-label">Errors Found</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value warning"><?= $warning_count ?></div>
                    <div class="stat-label">Warnings Found</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Log Display -->
        <div class="log-container">
            <?php if (file_exists($log_path)): ?>
                <?php
                    $all_lines = file($log_path, FILE_IGNORE_NEW_LINES);
                    $display_lines = array_slice($all_lines, -$lines);
                    
                    foreach ($display_lines as $line):
                        // Color-code based on content
                        $class = '';
                        if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                            $class = 'error';
                        } elseif (stripos($line, 'warning') !== false) {
                            $class = 'warning';
                        } elseif (stripos($line, 'success') !== false || stripos($line, '✓') !== false || stripos($line, '✅') !== false) {
                            $class = 'success';
                        } elseif (stripos($line, 'info') !== false) {
                            $class = 'info';
                        }
                        
                        if ($class) {
                            echo "<span class='$class'>" . htmlspecialchars($line) . "</span>\n";
                        } else {
                            echo htmlspecialchars($line) . "\n";
                        }
                    endforeach;
                ?>
            <?php else: ?>
                <span class="info">No log file found: <?= htmlspecialchars($log_path) ?></span>
            <?php endif; ?>
        </div>

        <!-- Refresh Info -->
        <div class="alert alert-info mt-3">
            <strong>💡 Tip:</strong> This page shows the last <?= $lines ?> lines of the <?= htmlspecialchars($log_file) ?> log file. 
            Refresh the page to see the latest entries. Colors indicate log severity: 
            <span style="color: #ff6b6b;">Red = Error</span>, 
            <span style="color: #ffd93d;">Yellow = Warning</span>, 
            <span style="color: #51cf66;">Green = Success</span>, 
            <span style="color: #74c0fc;">Blue = Info</span>
        </div>

        <!-- Back Button -->
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-secondary">← Back to Admin</a>
        </div>
    </div>

    <script>
        // Auto-refresh every 5 seconds
        setTimeout(function() {
            location.reload();
        }, 5000);
    </script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
