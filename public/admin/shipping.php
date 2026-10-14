<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/bootstrap.php';
require_once $root_dir . '/includes/session_helper.php';
require_admin_login();
$db = new Database();
$threshold = free_shipping_threshold($db);
$sameDay = same_day_fee($db);
$examples = [
    ['United Arab Emirates', 'Dubai', 2, 450],
    ['United Arab Emirates', 'Al Gharbia', 1, 600],
    ['Oman', 'Muscat', 6, 100],
];
$page_title = 'Shipping rates';
require __DIR__ . '/header.php';
?>
<div class="container-fluid">
    <div class="card card-body mb-3">
        <h2>Zones</h2>
        <ul>
            <li>UAE standard: 30 AED. Al Gharbia: 60 AED. Free UAE delivery when merchandise is at least AED <?= number_format($threshold, 2) ?>, before gift wrap and VAT.</li>
            <li>Same-day Dubai, Abu Dhabi, and Al Ain: AED <?= number_format($sameDay, 2) ?>. Same-day is not free when the order crosses the threshold.</li>
            <li>Oman: 70 AED for the first 5 kg, then 10 AED per extra kg.</li>
            <li>GCC (Kuwait, Saudi Arabia, Qatar, Bahrain): 120 AED for the first kg of each 8 kg parcel, then 30 AED per extra kg inside the parcel.</li>
            <li>Europe (Germany, France, Italy, Spain, United Kingdom): 220 AED for the first 1 kg, then 70 AED per kg.</li>
            <li>Rest of the world: 300 AED for the first 1 kg, then 80 AED per kg.</li>
        </ul>
    </div>
    <div class="card card-body">
        <h2>Examples</h2>
        <table class="table">
            <thead><tr><th>Country</th><th>City</th><th>Weight</th><th>Merchandise</th><th>Cost</th></tr></thead>
            <tbody>
            <?php foreach ($examples as $example):
                [$country, $city, $weight, $merchandise] = $example;
                $cost = calculateShippingCost($country, $city, $weight, $merchandise, false);
            ?>
                <tr>
                    <td><?= htmlspecialchars($country) ?></td>
                    <td><?= htmlspecialchars($city) ?></td>
                    <td><?= (int)$weight ?> kg</td>
                    <td>AED <?= number_format($merchandise, 2) ?></td>
                    <td>AED <?= number_format($cost, 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php';