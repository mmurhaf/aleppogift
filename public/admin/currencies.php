<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/includes/session_helper.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/Database.php';

require_admin_login();

$db = new Database();
$page_title = 'Currencies';
$message = '';
$messageType = 'success';

$db->query("CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!function_exists('verifyCsrfToken') || !verifyCsrfToken($token)) {
        $message = 'The form expired. Save the rates again.';
        $messageType = 'danger';
    } elseif (isset($_POST['reset_rates'])) {
        $defaults = [];
        foreach (currency_catalog_defaults() as $code => $row) {
            if ($code !== 'AED') {
                $defaults[$code] = $row['rate'];
            }
        }
        if (save_currency_rates($db, $defaults, '2026-10-05')) {
            $message = 'Rates restored to the 5 October 2026 reference values.';
        } else {
            $message = 'Could not restore the rates.';
            $messageType = 'danger';
        }
    } else {
        $posted = isset($_POST['rate']) && is_array($_POST['rate']) ? $_POST['rate'] : [];
        if (save_currency_rates($db, $posted, date('Y-m-d'))) {
            $message = 'Currency rates saved. The storefront and shipping table use these values now.';
        } else {
            $message = 'Enter a positive rate for every currency. AED stays at 1.';
            $messageType = 'danger';
        }
    }
}

$currencies = currency_catalog(true);
$updatedLabel = currency_rates_label('en');
$groups = [
    1 => 'Gulf countries (GCC)',
    2 => 'US dollar',
    3 => 'Euro',
    4 => 'Other currencies',
];

require __DIR__ . '/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Currency rates</h5>
        <span class="text-muted small">1 AED equals the rate below · updated <?= htmlspecialchars($updatedLabel) ?></span>
    </div>
    <div class="card-body">
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <p class="text-muted">
            Product prices, the cart, checkout display, and the shipping currency table use these rates.
            Checkout is still charged in AED. Gulf currencies are listed first, then the US dollar, the euro, and the rest.
        </p>

        <form method="post">
            <?php if (function_exists('csrfField')) csrfField(); ?>
            <?php
            $currentGroup = null;
            foreach ($currencies as $currency):
                if ($currentGroup !== (int)$currency['group']):
                    $currentGroup = (int)$currency['group'];
            ?>
                <h6 class="mt-4 mb-3"><?= htmlspecialchars($groups[$currentGroup] ?? 'Currencies') ?></h6>
                <div class="table-responsive mb-2">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Currency</th>
                                <th>Country</th>
                                <th style="width: 180px;">1 AED equals</th>
                                <th>100 AED shows as</th>
                            </tr>
                        </thead>
                        <tbody>
            <?php endif; ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($currency['code']) ?></strong>
                                    <div class="text-muted small"><?= htmlspecialchars($currency['name']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($currency['country']) ?></td>
                                <td>
                                    <?php if ($currency['code'] === 'AED'): ?>
                                        <input type="text" class="form-control" value="1" readonly>
                                    <?php else: ?>
                                        <input type="number" class="form-control" name="rate[<?= htmlspecialchars($currency['code']) ?>]"
                                               min="0.0000001" max="10000000" step="any" required
                                               value="<?= htmlspecialchars(rtrim(rtrim(number_format((float)$currency['rate'], 8, '.', ''), '0'), '.')) ?>">
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted">
                                    <?= htmlspecialchars($currency['prefix'] . number_format(100 * (float)$currency['rate'], (int)$currency['decimals'])) ?>
                                </td>
                            </tr>
            <?php
                $codes = array_keys($currencies);
                $index = array_search($currency['code'], $codes, true);
                $next = $codes[$index + 1] ?? null;
                $nextGroup = $next ? (int)$currencies[$next]['group'] : null;
                if ($nextGroup !== $currentGroup):
            ?>
                        </tbody>
                    </table>
                </div>
            <?php
                endif;
            endforeach;
            ?>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Save rates</button>
                <button type="submit" class="btn btn-outline-secondary" name="reset_rates" value="1"
                        onclick="return confirm('Restore the 5 October 2026 reference rates?');">
                    Restore reference rates
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
