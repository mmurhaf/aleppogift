<?php
ob_start();
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ArabicPdfRenderer.php';
require_once __DIR__ . '/../includes/ProductImageResolver.php';
require_once __DIR__ . '/../includes/PdfDocument.php';

$db = new Database();
$order_items = $_SESSION['cart'] ?? [];

$cleanInput = static function ($value) {
    return trim((string)$value);
};

$fullname = $cleanInput($_POST['fullname'] ?? ($_SESSION['quotation_fullname'] ?? ''));
$email = $cleanInput($_POST['email'] ?? ($_SESSION['quotation_email'] ?? ''));
$phone = $cleanInput($_POST['phone'] ?? ($_SESSION['quotation_phone'] ?? ''));
$address = $cleanInput($_POST['address'] ?? ($_SESSION['quotation_address'] ?? ''));
$country = $cleanInput($_POST['country'] ?? ($_SESSION['shipping_country'] ?? 'Not specified'));
$city = $cleanInput($_POST['city'] ?? ($_SESSION['shipping_city'] ?? 'Not specified'));
$totalWeight = (float)($_POST['totalWeight'] ?? ($_SESSION['total_weight'] ?? 0));
$note = $cleanInput($_POST['note'] ?? ($_SESSION['customer_note'] ?? ''));
$shippingAED = (float)($_POST['shipping_cost'] ?? ($_SESSION['shipping_cost'] ?? 0));
$aedToUsdRate = defined('AED_TO_USD_RATE') ? (float)AED_TO_USD_RATE : 0.2723;

$e = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

// Keep the latest quotation details in session for subsequent downloads.
if ($fullname !== '') {
    $_SESSION['quotation_fullname'] = $fullname;
}
if ($email !== '') {
    $_SESSION['quotation_email'] = $email;
}
if ($phone !== '') {
    $_SESSION['quotation_phone'] = $phone;
}
if ($address !== '') {
    $_SESSION['quotation_address'] = $address;
}
if ($country !== '') {
    $_SESSION['shipping_country'] = $country;
}
if ($city !== '') {
    $_SESSION['shipping_city'] = $city;
}
if ($note !== '') {
    $_SESSION['customer_note'] = $note;
}
$_SESSION['total_weight'] = $totalWeight;
$_SESSION['shipping_cost'] = $shippingAED;

$quotationDir = __DIR__ . '/quotations';
if (!is_dir($quotationDir)) {
    mkdir($quotationDir, 0755, true);
}

$sessionToken = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', (string)session_id()), 0, 4));
if ($sessionToken === '') {
    $sessionToken = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}
$uniqueId = time() . '_' . $sessionToken;
$quotationNumber = 'QT-' . date('Ymd') . '-' . $sessionToken;
$quotationPath = $quotationDir . '/quotation_' . $uniqueId . '.pdf';
$publicQuotationPath = 'quotations/quotation_' . $uniqueId . '.pdf';

$rows = '';
$grandTotal = 0.0;
$index = 1;

foreach ($order_items as $item) {
    $product = $db->query("SELECT name_en, price, (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = products.id AND pi.is_main = 1 ORDER BY pi.id ASC LIMIT 1) AS main_image FROM products WHERE id = :id", ['id' => $item['product_id']])->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        continue;
    }

    $price = (float)$product['price'];
    $selectedImage = (string)($product['main_image'] ?? '');
    $variation = null;
    if (!empty($item['variation_id'])) {
        $variation = $db->query("SELECT pv.additional_price, pv.size, pv.color, pi.image_path AS variant_image FROM product_variations pv LEFT JOIN product_images pi ON pv.image_id = pi.id WHERE pv.id = :id", ['id' => $item['variation_id']])->fetch(PDO::FETCH_ASSOC);
        $price += (float)($variation['additional_price'] ?? 0);
        if (!empty($variation['variant_image'])) {
            $selectedImage = (string)$variation['variant_image'];
        }
    }

    $qty = (int)($item['quantity'] ?? 0);
    $lineTotal = $price * $qty;
    $grandTotal += $lineTotal;

    $rawName = html_entity_decode((string)($product['name_en'] ?? 'Product'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $rawName = trim(strip_tags($rawName));
    $nameEn = $e($rawName !== '' ? $rawName : 'Product');
    $variantBits = array_filter([
        trim((string)($variation['color'] ?? '')),
        trim((string)($variation['size'] ?? '')),
    ], static function ($bit) {
        return $bit !== '';
    });
    $variantLine = $variantBits ? '<div class="muted">' . $e(implode(' / ', $variantBits)) . '</div>' : '';

    $imageDataUri = ProductImageResolver::resolveAsDataUri(
        $selectedImage,
        [
            __DIR__ . '/../',
            __DIR__ . '/uploads/products/'
        ]
    );
    $imageHtml = $imageDataUri
        ? '<img class="product-image" width="42" height="42" src="' . $e($imageDataUri) . '" alt="">'
        : '<span class="muted">—</span>';

    $rowClass = ($index % 2 === 0) ? ' class="alt"' : '';
    $rows .= '<tr' . $rowClass . '>'
        . '<td class="center">' . $index++ . '</td>'
        . '<td class="center">' . $imageHtml . '</td>'
        . '<td class="desc"><div class="pname">' . $nameEn . '</div>' . $variantLine . '</td>'
        . '<td class="center">' . $qty . '</td>'
        . '<td class="money">' . PdfDocument::money($price, $aedToUsdRate) . '</td>'
        . '<td class="money">' . PdfDocument::money($lineTotal, $aedToUsdRate) . '</td>'
        . '</tr>';
}

if ($rows === '') {
    $rows = '<tr><td class="center muted" colspan="6">Your cart is empty</td></tr>';
}

$notesHtml = '';
if ($note !== '') {
    $notesHtml = '<div class="section-title">NOTE</div><div class="notes">' . nl2br($e($note)) . '</div>';
}

$preparedLines = [];
if ($fullname !== '') {
    $preparedLines[] = '<strong>' . $e($fullname) . '</strong>';
}
if ($phone !== '') {
    $preparedLines[] = 'Phone: ' . $e($phone);
}
if ($email !== '') {
    $preparedLines[] = 'Email: ' . $e($email);
}
if ($address !== '') {
    $preparedLines[] = nl2br($e($address));
}
if (!$preparedLines) {
    $preparedLines[] = 'Prepared from your cart';
}

$deliveryLines = [];
if ($country !== '' && strcasecmp($country, 'not specified') !== 0) {
    $deliveryLines[] = 'Country: ' . $e($country);
}
if ($city !== '' && strcasecmp($city, 'not specified') !== 0) {
    $deliveryLines[] = 'City: ' . $e($city);
}
if ($totalWeight > 0) {
    $deliveryLines[] = 'Weight: ' . $e(number_format($totalWeight, 2)) . ' kg';
}
$shippingKnown = $shippingAED > 0;
$deliveryLines[] = $shippingKnown
    ? 'Estimated shipping is included below'
    : 'Shipping is calculated at checkout';
if (!$deliveryLines) {
    $deliveryLines[] = 'Destination not selected yet';
}

$payable = $grandTotal + ($shippingKnown ? $shippingAED : 0);
$shippingCell = $shippingKnown
    ? PdfDocument::money($shippingAED, $aedToUsdRate)
    : '<div class="aed">At checkout</div>';
$notice = $shippingKnown
    ? 'Estimated shipping is included in this quotation. The final amount is confirmed when the order is placed. This quotation is valid for 7 days.'
    : 'Shipping is calculated at checkout after the country and city are selected, and is not included in this total. This quotation is valid for 7 days.';

$html = PdfDocument::document(
    PdfDocument::header('QUOTATION', [
        'Date: ' . $e(date('F j, Y')),
        'Quotation #: ' . $e($quotationNumber),
        'Valid until: ' . $e(date('F j, Y', strtotime('+7 days'))),
    ])
    . PdfDocument::parties('PREPARED FOR', implode('<br>', $preparedLines), 'DELIVERY', implode('<br>', $deliveryLines))
    . PdfDocument::itemsTable($rows)
    . PdfDocument::totals(
        '<tr><td class="lbl">Subtotal</td><td class="money">' . PdfDocument::money($grandTotal, $aedToUsdRate) . '</td></tr>'
        . '<tr><td class="lbl">Shipping</td><td class="money">' . $shippingCell . '</td></tr>'
        . '<tr class="grand"><td>GRAND TOTAL</td><td class="money">' . PdfDocument::money($payable, $aedToUsdRate) . '</td></tr>'
    )
    . $notesHtml
    . '<div class="notice">' . $e($notice) . '</div>'
    . PdfDocument::footer('Thank you for considering Aleppo Gift.')
);

ArabicPdfRenderer::saveToFile($html, $quotationPath);
header('Location: ' . $publicQuotationPath);
exit;
