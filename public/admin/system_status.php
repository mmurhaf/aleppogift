<?php
/**
 * System Status Dashboard
 * Operational status page intended for the main admin shell.
 */

$root_dir = dirname(dirname(__DIR__));

require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/bootstrap.php');

require_admin_login();

$page_title = 'System Status';

$checks = [];

try {
    $db = new Database();
    $db->query('SELECT 1');
    $checks[] = ['label' => 'Database connection', 'status' => 'success', 'message' => 'Connected successfully'];
} catch (Exception $e) {
    $checks[] = ['label' => 'Database connection', 'status' => 'danger', 'message' => $e->getMessage()];
}

$checks[] = ['label' => 'Environment', 'status' => 'info', 'message' => ENVIRONMENT];
$checks[] = ['label' => 'Site URL', 'status' => 'info', 'message' => SITE_URL];
$checks[] = ['label' => 'Session ID', 'status' => 'secondary', 'message' => session_id() ?: 'No active session'];

try {
    $checks[] = ['label' => 'CSRF token generation', 'status' => 'success', 'message' => substr(Security::generateCSRFToken(), 0, 16) . '...'];
} catch (Exception $e) {
    $checks[] = ['label' => 'CSRF token generation', 'status' => 'danger', 'message' => $e->getMessage()];
}

$checks[] = [
    'label' => 'Email functions',
    'status' => function_exists('sendOrderEmail') || function_exists('sendAdminOrderNotification') ? 'success' : 'warning',
    'message' => function_exists('sendOrderEmail') || function_exists('sendAdminOrderNotification') ? 'Loaded' : 'Not loaded'
];

try {
    $rateLimitOk = Security::checkRateLimit('admin-system-status-check', 5, 300);
    $checks[] = ['label' => 'Rate limiting', 'status' => $rateLimitOk ? 'success' : 'warning', 'message' => $rateLimitOk ? 'Working' : 'Threshold reached'];
} catch (Exception $e) {
    $checks[] = ['label' => 'Rate limiting', 'status' => 'danger', 'message' => $e->getMessage()];
}

$logs_path = $root_dir . '/logs';
$checks[] = [
    'label' => 'Logs directory',
    'status' => is_writable($logs_path) ? 'success' : 'warning',
    'message' => is_writable($logs_path) ? 'Writable' : 'Not writable'
];

try {
    $test_data = 'test encryption';
    $encrypted = Security::encrypt($test_data);
    $decrypted = Security::decrypt($encrypted);
    $checks[] = [
        'label' => 'Encryption',
        'status' => $decrypted === $test_data ? 'success' : 'danger',
        'message' => $decrypted === $test_data ? 'Working' : 'Decrypted value mismatch'
    ];
} catch (Exception $e) {
    $checks[] = ['label' => 'Encryption', 'status' => 'danger', 'message' => $e->getMessage()];
}

$summary = [
    ['label' => 'Environment', 'value' => ENVIRONMENT],
    ['label' => 'Debug Mode', 'value' => env_bool('DEBUG_MODE') ? 'ON' : 'OFF'],
    ['label' => 'Database', 'value' => DB_HOST . '/' . DB_NAME],
    ['label' => 'Email From', 'value' => EMAIL_FROM],
    ['label' => 'SMTP Host', 'value' => SMTP_HOST],
    ['label' => 'Currency', 'value' => CURRENCY . ' (' . CURRENCY_SYMBOL . ')'],
    ['label' => 'Ziina Test Mode', 'value' => ZIINA_TEST_MODE ? 'ON' : 'OFF'],
];

require_once __DIR__ . '/includes/header.php';
?>
<style>
    .status-dashboard {
        display: grid;
        gap: 1.5rem;
    }

    .status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1rem;
    }

    .status-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        padding: 1.25rem;
        border: 1px solid rgba(15, 23, 42, 0.06);
    }

    .status-card__label {
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }

    .status-card__message {
        font-weight: 700;
        color: #111827;
    }

    .status-summary {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }

    .status-summary table {
        margin-bottom: 0;
    }

    .status-page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
        .status-page-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="status-dashboard container-fluid py-4">
    <div class="status-page-header">
        <div>
            <h2 class="h4 mb-1">Operational Health</h2>
            <p class="text-muted mb-0">Live checks for the main platform services and configuration.</p>
        </div>
        <a href="/public/" class="btn btn-outline-secondary">
            <i class="fas fa-external-link-alt me-2"></i>Open Main Site
        </a>
    </div>

    <div class="status-grid">
        <?php foreach ($checks as $check): ?>
            <div class="status-card border-start border-4 border-<?php echo htmlspecialchars($check['status']); ?>">
                <div class="status-card__label"><?php echo htmlspecialchars($check['label']); ?></div>
                <div class="status-card__message"><?php echo htmlspecialchars($check['message']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="status-summary">
        <div class="p-4 border-bottom">
            <h3 class="h5 mb-1">Configuration Summary</h3>
            <p class="text-muted mb-0">Reference values currently loaded by the application bootstrap.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <tbody>
                    <?php foreach ($summary as $row): ?>
                        <tr>
                            <th scope="row" class="px-4 py-3"><?php echo htmlspecialchars($row['label']); ?></th>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($row['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
