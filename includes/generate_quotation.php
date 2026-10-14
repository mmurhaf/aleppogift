<?php
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ArabicPdfRenderer.php';
require_once __DIR__ . '/ProductImageResolver.php';

$db = new Database();
$order_items = $_SESSION['cart'] ?? [];
$country = $_SESSION['shipping_country'] ?? 'Not specified';
$city = $_SESSION['shipping_city'] ?? 'Not specified';
$totalWeight = (float)($_SESSION['total_weight'] ?? 0);
$note = $_SESSION['customer_note'] ?? '';
$shippingAED = 0.00;
$aedToUsdRate = 0.2723;

$formatAedUsd = static function ($amount) use ($aedToUsdRate) {
    $amount = (float)$amount;
    $usd = $amount * $aedToUsdRate;
    return number_format($amount, 2) . ' AED (USD ' . number_format($usd, 2) . ')';
};

$quotationDir = __DIR__ . '/../public/quotations';
if (!is_dir($quotationDir)) {
    mkdir($quotationDir, 0755, true);
}

$uniqueId = time() . '_' . substr(session_id(), 0, 8);
$quotationPath = $quotationDir . '/quotation_' . $uniqueId . '.pdf';
$publicQuotationPath = '../public/quotations/quotation_' . $uniqueId . '.pdf';

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
    if (!empty($item['variation_id'])) {
        $variation = $db->query("SELECT pv.additional_price, pi.image_path AS variant_image FROM product_variations pv LEFT JOIN product_images pi ON pv.image_id = pi.id WHERE pv.id = :id", ['id' => $item['variation_id']])->fetch(PDO::FETCH_ASSOC);
        $price += (float)($variation['additional_price'] ?? 0);
        if (!empty($variation['variant_image'])) {
            $selectedImage = (string)$variation['variant_image'];
        }
    }

    $qty = (int)($item['quantity'] ?? 0);
    $lineTotal = $price * $qty;
    $grandTotal += $lineTotal;

    $nameEn = htmlspecialchars((string)($product['name_en'] ?? 'Product'), ENT_QUOTES, 'UTF-8');

    $imageDataUri = ProductImageResolver::resolveAsDataUri(
        $selectedImage,
        [
            __DIR__ . '/../',
            __DIR__ . '/../public/',
            __DIR__ . '/../public/uploads/products/'
        ]
    );
    $imageHtml = $imageDataUri
        ? '<img class="product-image" src="' . htmlspecialchars($imageDataUri, ENT_QUOTES, 'UTF-8') . '" alt="Product image">'
        : '<span class="muted">No image</span>';

    $rows .= '<tr>'
        . '<td class="center">' . $index++ . '</td>'
        . '<td class="center">' . $imageHtml . '</td>'
        . '<td><div>' . $nameEn . '</div></td>'
        . '<td class="center">' . $qty . '</td>'
        . '<td class="num">' . $formatAedUsd($price) . '</td>'
        . '<td class="num">' . $formatAedUsd($lineTotal) . '</td>'
        . '</tr>';
}

$notesHtml = '';
if (!empty($note)) {
    $notesHtml = '<div class="section-title">Customer Note</div><div class="notes">' . nl2br(htmlspecialchars((string)$note, ENT_QUOTES, 'UTF-8')) . '</div>';
}

$html = '<!doctype html><html><head><meta charset="UTF-8"><style>'
    . '@page { margin: 16mm 12mm; }'
    . 'body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #2f2f2f; }'
    . '.title { color: #e67b2e; font-size: 22px; font-weight: 700; text-align: right; }'
    . '.meta { text-align: right; color: #555; line-height: 1.5; margin-bottom: 10px; }'
    . '.table { width: 100%; border-collapse: collapse; margin-top: 10px; }'
    . '.table th, .table td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }'
    . '.table th { background: #2c3e50; color: #fff; }'
    . '.product-image { width: 40px; height: 40px; object-fit: cover; border-radius: 2px; }'
    . '.center { text-align: center; } .num { text-align: right; }'
    . '.muted { color: #7a7a7a; font-size: 9px; }'
    . '.summary { width: 280px; margin-left: auto; margin-top: 10px; border-collapse: collapse; }'
    . '.summary td { border: 1px solid #ddd; padding: 6px; }'
    . '.summary .grand { background: #e67b2e; color: #fff; font-weight: 700; }'
    . '.notes, .shipping { border: 1px solid #ddd; padding: 8px; margin-top: 10px; line-height: 1.5; }'
    . '.section-title { margin-top: 10px; font-weight: 700; color: #2c3e50; }'
    . '</style></head><body>'
    . '<div class="title">QUOTATION</div>'
    . '<div class="meta">AleppoGift.com | Your Trusted Gift Shop<br>Date: ' . date('Y-m-d') . '<br>Quotation #: ' . htmlspecialchars($uniqueId, ENT_QUOTES, 'UTF-8') . '<br>Valid until: ' . date('Y-m-d', strtotime('+7 days')) . '</div>'
    . '<div class="shipping"><strong>Shipping Information</strong><br>Country: ' . htmlspecialchars($country, ENT_QUOTES, 'UTF-8') . '<br>City: ' . htmlspecialchars($city, ENT_QUOTES, 'UTF-8') . '<br>Total Weight: ' . number_format($totalWeight, 2) . ' kg</div>'
    . '<table class="table"><tr><th style="width:8%">#</th><th style="width:12%">Image</th><th style="width:40%">Product Description</th><th style="width:10%">Qty</th><th style="width:15%">Unit Price</th><th style="width:15%">Total</th></tr>'
    . $rows
    . '</table>'
    . '<table class="summary"><tr><td>Shipping</td><td class="num">' . $formatAedUsd($shippingAED) . '</td></tr><tr><td class="grand">GRAND TOTAL</td><td class="num grand">' . $formatAedUsd($grandTotal) . '</td></tr></table>'
    . $notesHtml
    . '<div class="notes">Note: Shipping cost is not included in this quotation. It will be calculated at checkout after selecting your country and city.</div>'
    . '</body></html>';

ArabicPdfRenderer::saveToFile($html, $quotationPath);
header('Location: ' . $publicQuotationPath);
exit;
