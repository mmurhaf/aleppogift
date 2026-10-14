<?php
/**
 * Export the current cart as a PDF.
 * prices=no omits unit prices, line totals, and the summary.
 */

require_once(__DIR__ . '/../../includes/bootstrap.php');
require_once(__DIR__ . '/../../includes/ProductImageResolver.php');
require_once(__DIR__ . '/../../includes/ArabicPdfRenderer.php');
require_once(__DIR__ . '/../../includes/PdfDocument.php');

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    http_response_code(400);
    die('Cart is empty');
}

$includePrices = !(isset($_GET['prices']) && $_GET['prices'] === 'no');
$rate = defined('AED_TO_USD_RATE') ? (float)AED_TO_USD_RATE : 0.2723;
$shipping = 30.00;

$e = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

try {
    $db = new Database();
    $pricedCart = function_exists('priceCart')
        ? priceCart($db, $_SESSION['cart'])
        : ['lines' => [], 'bundles' => [], 'catalog_total' => 0, 'bundle_discount' => 0, 'total' => 0];

    $orderedKeys = [];
    foreach ($pricedCart['bundles'] as $bundleGroup) {
        foreach ($bundleGroup['line_keys'] as $key) {
            if (isset($_SESSION['cart'][$key])) {
                $orderedKeys[] = $key;
            }
        }
    }
    $orderedKeys = array_values(array_unique($orderedKeys, SORT_REGULAR));
    $seen = array_flip($orderedKeys);
    foreach (array_keys($_SESSION['cart']) as $key) {
        if (!isset($seen[$key])) {
            $orderedKeys[] = $key;
        }
    }

    $imageBases = [
        __DIR__ . '/../../',
        __DIR__ . '/../../public/',
        __DIR__ . '/../../public/uploads/products/',
        __DIR__ . '/../../uploads/products/',
    ];

    $rows = '';
    $index = 1;
    $pieceCount = 0;

    foreach ($orderedKeys as $key) {
        $item = $_SESSION['cart'][$key] ?? null;
        $line = $pricedCart['lines'][$key] ?? null;
        if (!$item || !$line || empty($line['active'])) {
            continue;
        }

        $product = $db->query(
            "SELECT p.*,
                    COALESCE(pi_variant.image_path, pi_main.image_path) AS selected_image,
                    pv.size AS variation_size,
                    pv.color AS variation_color
             FROM products p
             LEFT JOIN product_variations pv ON pv.id = :variation_id AND pv.product_id = p.id
             LEFT JOIN product_images pi_variant ON pv.image_id = pi_variant.id
             LEFT JOIN product_images pi_main ON pi_main.product_id = p.id AND pi_main.is_main = 1
             WHERE p.id = :product_id
             LIMIT 1",
            [
                'variation_id' => $line['variation_id'] ?? null,
                'product_id' => $line['product_id'],
            ]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            continue;
        }

        $qty = (int)$line['quantity'];
        $pieceCount += $qty;
        $rawName = html_entity_decode((string)loc_field($product, 'name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rawName = trim(strip_tags($rawName));
        $name = $e($rawName !== '' ? $rawName : 'Product');

        $variantBits = array_filter([
            trim((string)($product['variation_color'] ?? '')),
            trim((string)($product['variation_size'] ?? '')),
        ], static function ($bit) {
            return $bit !== '';
        });
        $variantLine = $variantBits
            ? '<div class="muted">' . $e(implode(' / ', $variantBits)) . '</div>'
            : '';

        $bundleName = trim((string)($line['bundle_name_en'] ?? ''));
        $bundleLine = $bundleName !== ''
            ? '<div class="muted">Bundle: ' . $e($bundleName) . '</div>'
            : '';

        $imageDataUri = ProductImageResolver::resolveAsDataUri(
            (string)($product['selected_image'] ?? ''),
            $imageBases
        );
        $imageHtml = $imageDataUri
            ? '<img class="product-image" width="42" height="42" src="' . $e($imageDataUri) . '" alt="">'
            : '<span class="muted">—</span>';

        $rowClass = ($index % 2 === 0) ? ' class="alt"' : '';
        $rows .= '<tr' . $rowClass . '>'
            . '<td class="center">' . $index++ . '</td>'
            . '<td class="center">' . $imageHtml . '</td>'
            . '<td class="desc"><div class="pname">' . $name . '</div>' . $variantLine . $bundleLine . '</td>'
            . '<td class="center">' . $qty . '</td>';

        if ($includePrices) {
            $catalogUnit = (float)$line['catalog_unit'];
            $chargedUnit = $qty > 0 ? (float)$line['line_total'] / $qty : $catalogUnit;
            $unitHtml = PdfDocument::money($chargedUnit, $rate);
            if ((float)$line['line_total'] + 0.001 < (float)$line['catalog_line_total']) {
                $unitHtml .= '<div class="muted">Catalog AED ' . $e(number_format($catalogUnit, 2)) . '</div>';
            }
            $rows .= '<td class="money">' . $unitHtml . '</td>'
                . '<td class="money">' . PdfDocument::money((float)$line['line_total'], $rate) . '</td>';
        }

        $rows .= '</tr>';
    }

    $colspan = $includePrices ? 6 : 4;
    if ($rows === '') {
        $rows = '<tr><td class="center muted" colspan="' . $colspan . '">No available products in this cart</td></tr>';
    }

    $itemCount = max(0, $index - 1);
    $itemLabel = $itemCount === 1 ? '1 product' : $itemCount . ' products';
    $pieceLabel = $pieceCount === 1 ? '1 piece' : $pieceCount . ' pieces';

    $summaryLeft = '<strong>' . $e($itemLabel) . '</strong><br>' . $e($pieceLabel);
    $summaryRight = $includePrices
        ? 'Amounts are listed in AED.<br>The USD equivalent is shown under each amount.<br>Shipping shown is the UAE estimate.'
        : 'Prices are hidden in this copy.<br>Current prices are on aleppogift.com.';

    $body = PdfDocument::header('SHOPPING CART', [
        'Generated: ' . $e(date('F j, Y g:i A')),
        $e($itemLabel . ' · ' . $pieceLabel),
    ])
        . PdfDocument::parties('YOUR CART', $summaryLeft, $includePrices ? 'PRICES' : 'NOTE', $summaryRight)
        . PdfDocument::itemsTable($rows, $includePrices);

    if ($includePrices) {
        $catalogTotal = (float)$pricedCart['catalog_total'];
        $bundleDiscount = (float)$pricedCart['bundle_discount'];
        $goodsTotal = (float)$pricedCart['total'];
        $payable = $goodsTotal + $shipping;

        $totalRows = '<tr><td class="lbl">Subtotal</td><td class="money">' . PdfDocument::money($catalogTotal, $rate) . '</td></tr>';
        if ($bundleDiscount > 0.001) {
            $totalRows .= '<tr class="discount"><td class="lbl">Bundle discount</td><td class="money">'
                . PdfDocument::money($bundleDiscount, $rate, true) . '</td></tr>';
        }
        $totalRows .= '<tr><td class="lbl">Shipping</td><td class="money">' . PdfDocument::money($shipping, $rate) . '</td></tr>'
            . '<tr class="grand"><td>TOTAL</td><td class="money">' . PdfDocument::money($payable, $rate) . '</td></tr>';

        $body .= PdfDocument::totals($totalRows);
    }

    $body .= '<div class="notice">This is a cart export for your reference. To complete your purchase, visit aleppogift.com and proceed to checkout. Prices and availability are subject to change.</div>';
    if ($includePrices) {
        $body .= PdfDocument::footer('Thank you for shopping with Aleppo Gift.');
    } else {
        $body .= '<div class="footer">Thank you for shopping with Aleppo Gift.'
            . '<br>www.aleppogift.com &nbsp;|&nbsp; info@aleppogift.com</div>';
    }

    $filename = 'AleppoGift_Cart_' . date('Y-m-d') . ($includePrices ? '_with_prices' : '_without_prices') . '.pdf';
    ArabicPdfRenderer::streamDownload(PdfDocument::document($body), $filename);
    exit;
} catch (Exception $exception) {
    error_log('Cart PDF Export Error: ' . $exception->getMessage());
    http_response_code(500);
    die('Failed to generate PDF: ' . $exception->getMessage());
}
