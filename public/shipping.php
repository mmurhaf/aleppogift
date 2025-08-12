<?php
require_once(__DIR__ . '/../includes/shipping.php');
$shippingCost = null;
$error = null;

// Multibyte encoding for emoji flags
mb_internal_encoding('UTF-8');

function getCountryFlag($countryCode) {
    $flag = '';
    $code = strtoupper($countryCode);
    for ($i = 0; $i < strlen($code); $i++) {
        $flag .= mb_convert_encoding('&#' . (127397 + ord($code[$i])) . ';', 'UTF-8', 'HTML-ENTITIES');
    }
    return $flag;
}

$selectedCountry = $_POST['country'] ?? '';
$city = $_POST['city'] ?? '';
$weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$selectedCountry || $weight <= 0) {
        $error = "Please select a country and enter a valid weight.";
    } else {
        $shippingCost = calculateShippingCost($selectedCountry, $city, $weight);
    }
}

// Fetch countries once and sort
$countryOptions = '';
$apiUrl = "https://restcountries.com/v3.1/all?fields=name,cca2";
$countryData = @file_get_contents($apiUrl);

if ($countryData !== false) {
    $countries = json_decode($countryData, true);
    usort($countries, fn($a, $b) => strcmp($a['name']['common'], $b['name']['common']));

    foreach ($countries as $country) {
        $name = htmlspecialchars($country['name']['common']);
        $code = $country['cca2'] ?? '';
        $flag = $code ? getCountryFlag($code) : '';
        $selected = $selectedCountry === $name ? 'selected' : '';
        $countryOptions .= "<option value=\"$name\" $selected>$flag $name</option>\n";
    }
} else {
    $countryOptions .= '<option value="">⚠️ Failed to load countries</option>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shipping Calculator - AleppoGift</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Calculate your shipping costs with our easy-to-use shipping estimator. Get accurate rates based on your country, city, and package weight.">
    <meta name="keywords" content="shipping, calculator, estimator, rates, AleppoGift">
    <meta name="author" content="AleppoGift Team">
    <style>
        :root {
            --aleppo-primary: #8B0000;  /* Deep red/maroon */
            --aleppo-secondary: #D4AF37;  /* Gold */
            --aleppo-light: #F8F1E5;  /* Cream/off-white */
        }
        .bg-primary {
            background-color: var(--aleppo-primary) !important;
        }
        .btn-primary {
            background-color: var(--aleppo-primary);
            border-color: var(--aleppo-primary);
        }
        .btn-primary:hover {
            background-color: #6d0000;
            border-color: #6d0000;
        }
        .btn-outline-primary {
            color: var(--aleppo-primary);
            border-color: var(--aleppo-primary);
        }
        .btn-outline-primary:hover {
            background-color: var(--aleppo-primary);
            border-color: var(--aleppo-primary);
        }
        .text-primary {
            color: var(--aleppo-primary) !important;
        }
        .border-primary {
            border-color: var(--aleppo-primary) !important;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(139, 0, 0, 0.05);
        }
        .card-header {
            border-bottom: 2px solid var(--aleppo-secondary);
            
        }
        .alert-success {
            border-left: 4px solid var(--aleppo-secondary);
        }
    </style>
</head>
<body style="background-color: var(--aleppo-light);">
<div class="container py-4">

    <div class="text-center mb-5">
            <a class="navbar-brand" href="index.php">
                <img src="uploads/logo.png" alt="AleppoGift Logo" class="d-inline-block align-text-top" style="height: 40px;">
            </a>
        <h1 class="display-5 fw-bold text-primary">
            <i class="fas fa-shipping-fast me-2"></i> Shipping Rates Calculator
        </h1>
        <p class="lead" style="color: var(--aleppo-secondary);">Get instant shipping estimates for your order</p>
    </div>

    <div class="row g-4">
        <!-- Calculator Form Column -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-primary">
                <div class="card-header text-prime py-3">
                    <h3 class="h5 mb-0">
                        <i class="fas fa-calculator me-2"></i> Estimate Your Shipping
                    </h3>
                </div>
                <div class="card-body p-4">
                    <form method="post" id="shippingForm">
                        <div class="mb-4">
                            <label for="country" class="form-label fw-bold">Destination Country *</label>
                            <select id="country" name="country" class="form-select form-select-lg" required>
                                <?php 
                                require_once 'ajax/countries.php'; // Load countries array
                                // Ensure countries are sorted alphabetically   
                                foreach ($countries as $name => $flag): ?>
                                    <option value="<?= htmlspecialchars($name) ?>" <?= $name === 'United Arab Emirates' ? 'selected' : '' ?>>
                                        <?= $flag ?> <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4" id="city-container">
                            <label for="city" class="form-label fw-bold">City</label>
                            <select id="city" name="city" class="form-select form-select-lg">
                                <option value="">-- Select City --</option>
                            </select>
                            <input type="text" id="city-input" name="city" class="form-control form-control-lg mt-2 d-none" placeholder="Enter your city">
                            <small id="city-message" class="form-text text-muted d-none">
                                <i class="fas fa-info-circle me-1"></i> Please type your city manually
                            </small>
                        </div>

                        <div class="mb-4">
                            <label for="weight" class="form-label fw-bold">Package Weight (kg) *</label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0.1" id="weight" name="weight" 
                                       class="form-control form-control-lg" required 
                                       value="<?= htmlspecialchars($weight) ?>">
                                <span class="input-group-text">kg</span>
                            </div>
                            <small class="form-text text-muted">Minimum 0.1 kg</small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 py-3">
                            <i class="fas fa-calculator me-2"></i> Calculate Shipping Cost
                        </button>
                    </form>

                    <?php if ($error): ?>
                        <div class="alert alert-danger mt-4 p-3 rounded">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php elseif ($shippingCost !== null): ?>
                        <div class="alert alert-success mt-4 p-3 rounded shadow-sm">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle fa-2x me-3" style="color: var(--aleppo-secondary);"></i>
                                <div>
                                    <h4 class="mb-1">Estimated Shipping Cost</h4>
                                    <p class="mb-0 fs-3 fw-bold">AED <?= number_format($shippingCost, 2) ?></p>
                                    <small class="text-muted">Final cost confirmed at checkout</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="checkout.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Checkout
                </a>
            </div>
        </div>
        
        <!-- Rates Table Column -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-primary h-100">
                <div class="card-header text-prime py-3">
                    <h3 class="h5 mb-0">
                        <i class="fas fa-clipboard-list me-2"></i> Our Shipping Rates
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Region / Country</th>
                                    <th>Base Rate</th>
                                    <th>Additional Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-4"><strong>UAE</strong></td>
                                    <td>AED 30</td>
                                    <td>Flat rate (AED 50 for Al Gharbia)</td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong>Oman (Muscat)</strong></td>
                                    <td>AED 60 for 5kg</td>
                                    <td>AED 5/kg above 5kg</td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong>Oman (Salalah)</strong></td>
                                    <td>AED 70 for 5kg</td>
                                    <td>AED 7/kg above 5kg</td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong>GCC Countries</strong></td>
                                    <td>AED 120 for 1kg</td>
                                    <td>AED 30/kg above 1kg</td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong>Europe</strong></td>
                                    <td>AED 220 for 1kg</td>
                                    <td>AED 70/kg above 1kg</td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong>USA & Others</strong></td>
                                    <td>AED 300 for 1kg</td>
                                    <td>AED 80/kg above 1kg</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <small class="text-muted">Final shipping cost is calculated during checkout based on your selections.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('country').addEventListener('change', function () {
    const country = this.value;
    const citySelect = document.getElementById('city');
    const cityInput = document.getElementById('city-input');
    const cityMessage = document.getElementById('city-message');

    citySelect.classList.remove('d-none');
    cityInput.classList.add('d-none');
    cityMessage.classList.add('d-none');

    citySelect.innerHTML = '<option value="">Loading cities...</option>';

    fetch('../includes/get-cities.php?country=' + encodeURIComponent(country))
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                citySelect.innerHTML = '<option value="">-- Select City --</option>';
                data.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    citySelect.appendChild(option);
                });
            } else {
                citySelect.classList.add('d-none');
                cityInput.classList.remove('d-none');
                cityMessage.classList.remove('d-none');
            }
        })
        .catch(() => {
            citySelect.classList.add('d-none');
            cityInput.classList.remove('d-none');
            cityMessage.classList.remove('d-none');
        });
});
</script>
</body>
</html>