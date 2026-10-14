<?php

require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/Database.php');
require_once(__DIR__ . '/ArabicPdfRenderer.php');
require_once(__DIR__ . '/ProductImageResolver.php');
require_once(__DIR__ . '/PdfDocument.php');

class PDFInvoiceGenerator {
    private $db;
    private $aedToUsdRate = 0.2723;

    public function __construct() {
        $this->db = new Database();
        if (defined('AED_TO_USD_RATE')) {
            $this->aedToUsdRate = (float)AED_TO_USD_RATE;
        }
    }

    public function generateInvoicePDF($order_id) {
        if (!$order_id || !is_numeric($order_id)) {
            throw new Exception('Invalid order ID provided');
        }

        $order_id_int = (int)$order_id;

        $order = $this->db->query(
            "SELECT o.*, c.fullname AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address, c.city AS customer_city, c.country AS customer_country
             FROM orders o
             LEFT JOIN customers c ON o.customer_id = c.id
             WHERE o.id = ?",
            [$order_id_int]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception("Order #$order_id not found");
        }

        $order_items = $this->db->query(
            "SELECT oi.*, p.name_en, p.name_ar,
                    pv.size AS variation_size, pv.color AS variation_color,
                    COALESCE(
                        (SELECT pi.image_path
                         FROM product_images pi
                         WHERE pi.id = pv.image_id
                         LIMIT 1),
                        (SELECT pi.image_path
                         FROM product_images pi
                         WHERE pi.product_id = oi.product_id
                         ORDER BY pi.is_main DESC, pi.id ASC
                         LIMIT 1)
                    ) AS selected_image
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             LEFT JOIN product_variations pv ON oi.variation_id = pv.id
             WHERE oi.order_id = ?
             ORDER BY oi.id ASC",
            [$order_id_int]
        )->fetchAll(PDO::FETCH_ASSOC);

        $bundleNames = [];
        $bundleDiscount = 0.0;
        try {
            $orderBundles = $this->db->query(
                "SELECT bundle_id, bundle_name_en, discount_amount FROM order_bundles WHERE order_id = ?",
                [$order_id_int]
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($orderBundles as $orderBundle) {
                $bundleDiscount += (float)$orderBundle['discount_amount'];
                if (!empty($orderBundle['bundle_id'])) {
                    $bundleNames[(int)$orderBundle['bundle_id']] = $orderBundle['bundle_name_en'];
                }
            }
        } catch (Exception $e) {
            error_log('Invoice bundle lookup failed: ' . $e->getMessage());
        }

        $invoice_number = 'INV-' . str_pad((string)$order_id_int, 6, '0', STR_PAD_LEFT);
        $invoice_date = date('F j, Y', strtotime($order['created_at'] ?? $order['order_date'] ?? 'now'));

        $e = static function ($value): string {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        };

        $moneyHtml = function ($amount, bool $negative = false): string {
            return PdfDocument::money((float)$amount, $this->aedToUsdRate, $negative);
        };

        $subtotal = 0.0;
        $rows = '';
        $index = 1;

        foreach ($order_items as $item) {
            $qty = (int)($item['quantity'] ?? 0);
            $price = (float)($item['price'] ?? 0);
            $lineTotal = $qty * $price;
            $subtotal += $lineTotal;

            $rawName = trim((string)($item['product_name'] ?? ''));
            if ($rawName === '') {
                $rawName = (string)($item['name_en'] ?: ($item['name_ar'] ?? '') ?: 'Product');
            }
            $rawName = html_entity_decode($rawName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rawName = trim(strip_tags($rawName));
            $nameEn = $e($rawName !== '' ? $rawName : 'Product');

            $variantBits = array_filter([
                trim((string)($item['variation_color'] ?? '')),
                trim((string)($item['variation_size'] ?? '')),
            ], static function ($bit) {
                return $bit !== '';
            });
            $variantLine = $variantBits
                ? '<div class="muted">' . $e(implode(' / ', $variantBits)) . '</div>'
                : '';
            $productIdLine = !empty($item['product_id'])
                ? '<div class="muted">ID: ' . (int)$item['product_id'] . '</div>'
                : '';
            $bundleLine = '';
            if (!empty($item['bundle_id']) && !empty($bundleNames[(int)$item['bundle_id']])) {
                $bundleLine = '<div class="muted">Bundle: ' . $e($bundleNames[(int)$item['bundle_id']]) . '</div>';
            }

            $imageDataUri = ProductImageResolver::resolveAsDataUri(
                (string)($item['selected_image'] ?? ''),
                [
                    __DIR__ . '/../',
                    __DIR__ . '/../public/',
                    __DIR__ . '/../public/uploads/products/'
                ]
            );
            $imageHtml = $imageDataUri
                ? '<img class="product-image" width="42" height="42" src="' . $e($imageDataUri) . '" alt="">'
                : '<span class="muted">—</span>';

            $rowClass = ($index % 2 === 0) ? ' class="alt"' : '';
            $rows .= '<tr' . $rowClass . '>'
                . '<td class="center">' . $index++ . '</td>'
                . '<td class="center">' . $imageHtml . '</td>'
                . '<td class="desc"><div class="pname">' . $nameEn . '</div>' . $variantLine . $bundleLine . $productIdLine . '</td>'
                . '<td class="center">' . $qty . '</td>'
                . '<td class="money">' . $moneyHtml($price) . '</td>'
                . '<td class="money">' . $moneyHtml($lineTotal) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td class="center muted" colspan="6">No items found for this order</td></tr>';
        }

        $shipping = (float)($order['shipping_aed'] ?? $order['shipping_amount'] ?? 0);
        $discount = (float)($order['discount_amount'] ?? 0);
        $grandTotal = (float)($order['total_amount'] ?? 0);

        $address = trim((string)($order['customer_address'] ?? ''));
        $addressLower = function_exists('mb_strtolower') ? mb_strtolower($address) : strtolower($address);
        $locationParts = [];
        foreach (['customer_city', 'customer_country'] as $locationKey) {
            $part = trim((string)($order[$locationKey] ?? ''));
            if ($part === '') {
                continue;
            }
            $partLower = function_exists('mb_strtolower') ? mb_strtolower($part) : strtolower($part);
            if ($addressLower === '' || !str_contains($addressLower, $partLower)) {
                $locationParts[] = $part;
            }
        }

        $billLines = ['<strong>' . $e($order['customer_name'] ?: 'N/A') . '</strong>'];
        if ($address !== '') {
            $billLines[] = nl2br($e($address));
        }
        if ($locationParts) {
            $billLines[] = $e(implode(', ', $locationParts));
        }
        if (trim((string)($order['customer_phone'] ?? '')) !== '') {
            $billLines[] = 'Phone: ' . $e($order['customer_phone']);
        }
        if (trim((string)($order['customer_email'] ?? '')) !== '') {
            $billLines[] = 'Email: ' . $e($order['customer_email']);
        }

        $paymentMethod = trim((string)($order['payment_method'] ?? ''));
        if (strcasecmp($paymentMethod, 'Ziina') === 0) {
            $paymentMethod = 'Card';
        } elseif (strcasecmp($paymentMethod, 'COD') === 0) {
            $paymentMethod = 'Cash on delivery';
        } else {
            $paymentMethod = $paymentMethod !== '' ? ucwords(str_replace('_', ' ', $paymentMethod)) : 'N/A';
        }
        $paymentStatus = trim((string)($order['payment_status'] ?? ''));
        $paymentStatus = $paymentStatus !== '' ? ucfirst($paymentStatus) : 'Pending';

        $shipLines = [];
        if (trim((string)($order['shipping_method'] ?? '')) !== '') {
            $shipLines[] = 'Method: ' . $e($order['shipping_method']);
        }
        $shipLines[] = 'Cost: ' . ($shipping > 0
            ? 'AED ' . number_format($shipping, 2) . ' (USD ' . number_format($shipping * $this->aedToUsdRate, 2) . ')'
            : 'Free');
        if (!empty($order['total_weight']) && (float)$order['total_weight'] > 0) {
            $shipLines[] = 'Weight: ' . $e(number_format((float)$order['total_weight'], 2)) . ' kg';
        }
        if (trim((string)($order['carrier_name'] ?? '')) !== '') {
            $shipLines[] = 'Carrier: ' . $e($order['carrier_name']);
        }
        if (trim((string)($order['tracking_number'] ?? '')) !== '') {
            $shipLines[] = 'Tracking: ' . $e($order['tracking_number']);
        }
        if (trim((string)($order['shipment_status'] ?? '')) !== '') {
            $shipLines[] = 'Status: ' . $e(ucfirst((string)$order['shipment_status']));
        }

        $noteText = trim((string)($order['note'] ?? $order['notes'] ?? ''));
        $notesHtml = '';
        if ($noteText !== '') {
            $notesHtml = '<div class="section-title">NOTES</div><div class="notes">' . nl2br($e($noteText)) . '</div>';
        }

        $discountRow = '';
        if ($bundleDiscount > 0) {
            $discountRow .= '<tr><td class="lbl">Bundle discount</td><td class="money discount">' . $moneyHtml($bundleDiscount, true) . '</td></tr>';
            $subtotal += $bundleDiscount;
        }
        if ($discount > 0) {
            $discountRow .= '<tr><td class="lbl">Discount</td><td class="money discount">' . $moneyHtml($discount, true) . '</td></tr>';
        }

        $shippingValue = $shipping > 0 ? $moneyHtml($shipping) : '<div class="aed">FREE</div>';
        $wrapAmount = (float)($order['gift_wrap_amount'] ?? 0);
        $vatAmount = (float)($order['vat_amount'] ?? 0);
        $extraRows = '';
        if ($wrapAmount > 0) {
            $extraRows .= '<tr><td class="lbl">Gift wrap</td><td class="money">' . $moneyHtml($wrapAmount) . '</td></tr>';
        }
        if ($vatAmount > 0) {
            $extraRows .= '<tr><td class="lbl">VAT' . (!empty($order['trn']) ? ' (TRN ' . $e($order['trn']) . ')' : '') . '</td><td class="money">' . $moneyHtml($vatAmount) . '</td></tr>';
        }

        $html = PdfDocument::document(
            PdfDocument::header('INVOICE', [
                'Date: ' . $e($invoice_date),
                'Invoice #: ' . $e($invoice_number),
                'Order ID: #' . $order_id_int,
            ])
            . PdfDocument::parties(
                'FROM',
                'Aleppo Gift Trading LLC<br>Dubai, United Arab Emirates<br>Phone: +971 56 112 5320<br>Email: info@aleppogift.com',
                'BILL TO',
                implode('<br>', $billLines)
            )
            . PdfDocument::parties(
                'PAYMENT',
                'Method: ' . $e($paymentMethod) . '<br>Status: ' . $e($paymentStatus),
                'SHIPPING',
                implode('<br>', $shipLines)
            )
            . PdfDocument::itemsTable($rows)
            . PdfDocument::totals(
                '<tr><td class="lbl">Subtotal</td><td class="money">' . $moneyHtml($subtotal) . '</td></tr>'
                . '<tr><td class="lbl">Shipping</td><td class="money">' . $shippingValue . '</td></tr>'
                . $discountRow
                . $extraRows
                . '<tr class="grand"><td>GRAND TOTAL</td><td class="money">' . $moneyHtml($grandTotal) . '</td></tr>'
            )
            . $notesHtml
            . PdfDocument::footer('Thank you for your order.')
        );

        $invoiceDir = __DIR__ . '/../invoice';
        if (!is_dir($invoiceDir)) {
            mkdir($invoiceDir, 0755, true);
        }

        $pdfPath = $invoiceDir . '/invoice_' . $order_id_int . '.pdf';
        ArabicPdfRenderer::saveToFile($html, $pdfPath);

        return [
            'success' => true,
            'file_path' => $pdfPath,
            'full_path' => $pdfPath,
            'invoice_number' => $invoice_number
        ];
    }

    public function generatePackingSlipPDF($order_id) {
        $order_id_int = (int)$order_id;
        $order = $this->db->query(
            "SELECT o.*, c.fullname AS customer_name, c.phone AS customer_phone, c.address AS customer_address, c.city AS customer_city, c.country AS customer_country
             FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ?",
            [$order_id_int]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new Exception('Order not found');
        }
        $items = $this->db->query(
            "SELECT oi.*, p.name_en, p.name_ar FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?",
            [$order_id_int]
        )->fetchAll(PDO::FETCH_ASSOC);
        $hide = !empty($order['hide_prices']);
        $rows = '';
        foreach ($items as $item) {
            $name = htmlspecialchars((string)($item['name_en'] ?: $item['name_ar']));
            $price = $hide ? '' : ('<td>' . number_format((float)$item['price'], 2) . '</td>');
            $rows .= '<tr><td>' . $name . '</td><td>' . (int)$item['quantity'] . '</td>' . $price . '</tr>';
        }
        $gift = '';
        if (!empty($order['gift_recipient_name']) || !empty($order['gift_wrap'])) {
            $gift = '<p>Recipient: ' . htmlspecialchars((string)$order['gift_recipient_name'])
                . '<br>Phone: ' . htmlspecialchars((string)$order['gift_recipient_phone'])
                . '<br>Card: ' . htmlspecialchars((string)$order['gift_card_message'])
                . '<br>Gift wrap: ' . (!empty($order['gift_wrap']) ? 'yes' : 'no') . '</p>';
        }
        $html = '<h1>Packing slip #' . $order_id_int . '</h1>' . $gift
            . '<p>' . htmlspecialchars((string)$order['customer_name']) . '<br>'
            . htmlspecialchars((string)$order['customer_address']) . '<br>'
            . htmlspecialchars((string)$order['customer_city']) . ' ' . htmlspecialchars((string)$order['customer_country']) . '</p>'
            . '<table border="1" cellpadding="6"><tr><th>Item</th><th>Qty</th>' . ($hide ? '' : '<th>Price</th>') . '</tr>' . $rows . '</table>';
        $path = __DIR__ . '/../invoice/packing_' . $order_id_int . '.pdf';
        ArabicPdfRenderer::saveToFile($html, $path);
        return $path;
    }
}

if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__) && (isset($_GET['order_id']) || isset($_GET['id']))) {
    $order_id = intval($_GET['order_id'] ?? $_GET['id'] ?? 0);

    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID provided']);
        exit;
    }

    try {
        $generator = new PDFInvoiceGenerator();
        $result = $generator->generateInvoicePDF($order_id);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
