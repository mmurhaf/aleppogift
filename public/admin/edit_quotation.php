<?php
// Quotation editor. The form uses the same paper layout as the printed quotation.

$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/session_helper.php';
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/includes/Database.php';
require_once $root_dir . '/includes/PdfDocument.php';
require_once $root_dir . '/includes/ArabicPdfRenderer.php';
require_once $root_dir . '/includes/ProductImageResolver.php';

require_admin_login();

function quotation_plain(string $value): string {
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(strip_tags($value));
}

function quotation_image_src(?string $path): string {
    $path = trim(str_replace('\\', '/', (string)$path));
    $path = preg_replace('#^(\.\./)+#', '', $path);
    $path = ltrim((string)$path, '/');
    if ($path === '') {
        return '';
    }
    $segments = array_map('rawurlencode', explode('/', $path));
    return '../' . implode('/', $segments);
}

function quotation_blank(): array {
    $token = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    return [
        'number' => 'QT-' . date('Ymd') . '-' . $token,
        'valid_until' => date('Y-m-d', strtotime('+7 days')),
        'customer_name' => '',
        'email' => '',
        'phone' => '',
        'address' => '',
        'country' => '',
        'city' => '',
        'weight' => 0.0,
        'shipping' => 0.0,
        'note' => '',
        'items' => [],
        'next_id' => 1,
    ];
}

function quotation_draft(): array {
    $draft = $_SESSION['admin_quotation'] ?? null;
    if (!is_array($draft) || empty($draft['number'])) {
        $draft = quotation_blank();
        $_SESSION['admin_quotation'] = $draft;
    }
    return $draft;
}

function quotation_apply(array $quote, array $post): array {
    $quote['customer_name'] = quotation_plain((string)($post['customer_name'] ?? ''));
    $quote['email'] = quotation_plain((string)($post['email'] ?? ''));
    $quote['phone'] = quotation_plain((string)($post['phone'] ?? ''));
    $quote['address'] = quotation_plain((string)($post['address'] ?? ''));
    $quote['country'] = quotation_plain((string)($post['country'] ?? ''));
    $quote['city'] = quotation_plain((string)($post['city'] ?? ''));
    $quote['note'] = quotation_plain((string)($post['note'] ?? ''));
    $quote['weight'] = max(0, round((float)($post['weight'] ?? 0), 2));
    $quote['shipping'] = max(0, round((float)($post['shipping'] ?? 0), 2));
    $valid = trim((string)($post['valid_until'] ?? ''));
    $validStamp = $valid !== '' ? strtotime($valid) : false;
    if ($validStamp) {
        $quote['valid_until'] = date('Y-m-d', $validStamp);
    }

    $items = [];
    $nextId = 1;
    foreach (($post['items'] ?? []) as $id => $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemId = (int)$id;
        if ($itemId <= 0) {
            continue;
        }
        $name = quotation_plain((string)($item['name'] ?? ''));
        $productId = (int)($item['product_id'] ?? 0);
        if ($name === '' && $productId <= 0) {
            continue;
        }
        $items[] = [
            'id' => $itemId,
            'product_id' => $productId,
            'name' => $name !== '' ? $name : 'Product',
            'variant' => quotation_plain((string)($item['variant'] ?? '')),
            'qty' => max(1, (int)($item['qty'] ?? 1)),
            'price' => max(0, round((float)($item['price'] ?? 0), 2)),
            'image' => trim((string)($item['image'] ?? '')),
        ];
        $nextId = max($nextId, $itemId + 1);
    }
    $quote['items'] = $items;
    $quote['next_id'] = $nextId;
    return $quote;
}

function quotation_subtotal(array $quote): float {
    $subtotal = 0.0;
    foreach ($quote['items'] as $item) {
        $subtotal += (float)$item['qty'] * (float)$item['price'];
    }
    return round($subtotal, 2);
}

function quotation_pdf_html(array $quote, float $rate): string {
    $e = static function ($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    };
    $rows = '';
    $index = 1;
    $bases = [
        dirname(__DIR__, 2) . '/',
        dirname(__DIR__, 2) . '/public/',
        dirname(__DIR__, 2) . '/public/uploads/products/',
    ];
    foreach ($quote['items'] as $item) {
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];
        $lineTotal = $qty * $price;
        $variant = trim((string)$item['variant']);
        $variantLine = $variant !== '' ? '<div class="muted">' . $e($variant) . '</div>' : '';
        $idLine = !empty($item['product_id']) ? '<div class="muted">ID: ' . (int)$item['product_id'] . '</div>' : '';
        $imageDataUri = ProductImageResolver::resolveAsDataUri((string)$item['image'], $bases);
        $imageHtml = $imageDataUri
            ? '<img class="product-image" width="42" height="42" src="' . $e($imageDataUri) . '" alt="">'
            : '<span class="muted">—</span>';
        $rowClass = ($index % 2 === 0) ? ' class="alt"' : '';
        $rows .= '<tr' . $rowClass . '>'
            . '<td class="center">' . $index++ . '</td>'
            . '<td class="center">' . $imageHtml . '</td>'
            . '<td class="desc"><div class="pname">' . $e($item['name']) . '</div>' . $variantLine . $idLine . '</td>'
            . '<td class="center">' . $qty . '</td>'
            . '<td class="money">' . PdfDocument::money($price, $rate) . '</td>'
            . '<td class="money">' . PdfDocument::money($lineTotal, $rate) . '</td>'
            . '</tr>';
    }
    if ($rows === '') {
        $rows = '<tr><td class="center muted" colspan="6">No items on this quotation</td></tr>';
    }

    $prepared = [];
    if ($quote['customer_name'] !== '') {
        $prepared[] = '<strong>' . $e($quote['customer_name']) . '</strong>';
    }
    if ($quote['phone'] !== '') {
        $prepared[] = 'Phone: ' . $e($quote['phone']);
    }
    if ($quote['email'] !== '') {
        $prepared[] = 'Email: ' . $e($quote['email']);
    }
    if ($quote['address'] !== '') {
        $prepared[] = nl2br($e($quote['address']));
    }
    if (!$prepared) {
        $prepared[] = 'Prepared for the customer';
    }

    $shipping = (float)$quote['shipping'];
    $shippingKnown = $shipping > 0;
    $delivery = [];
    if ($quote['country'] !== '') {
        $delivery[] = 'Country: ' . $e($quote['country']);
    }
    if ($quote['city'] !== '') {
        $delivery[] = 'City: ' . $e($quote['city']);
    }
    if ((float)$quote['weight'] > 0) {
        $delivery[] = 'Weight: ' . $e(number_format((float)$quote['weight'], 2)) . ' kg';
    }
    $delivery[] = $shippingKnown
        ? 'Estimated shipping is included below'
        : 'Shipping is calculated at checkout';

    $subtotal = quotation_subtotal($quote);
    $payable = $subtotal + ($shippingKnown ? $shipping : 0);
    $shippingCell = $shippingKnown
        ? PdfDocument::money($shipping, $rate)
        : '<div class="aed">At checkout</div>';
    $validStamp = strtotime((string)$quote['valid_until']) ?: strtotime('+7 days');
    $notice = $shippingKnown
        ? 'Estimated shipping is included in this quotation. The final amount is confirmed when the order is placed. This quotation is valid for 7 days.'
        : 'Shipping is calculated at checkout after the country and city are selected, and is not included in this total. This quotation is valid for 7 days.';
    $notes = trim((string)$quote['note']);
    $notesHtml = $notes !== ''
        ? '<div class="section-title">NOTE</div><div class="notes">' . nl2br($e($notes)) . '</div>'
        : '';

    return PdfDocument::document(
        PdfDocument::header('QUOTATION', [
            'Date: ' . $e(date('F j, Y')),
            'Quotation #: ' . $e($quote['number']),
            'Valid until: ' . $e(date('F j, Y', $validStamp)),
        ])
        . PdfDocument::parties('PREPARED FOR', implode('<br>', $prepared), 'DELIVERY', implode('<br>', $delivery))
        . PdfDocument::itemsTable($rows)
        . PdfDocument::totals(
            '<tr><td class="lbl">Subtotal</td><td class="money">' . PdfDocument::money($subtotal, $rate) . '</td></tr>'
            . '<tr><td class="lbl">Shipping</td><td class="money">' . $shippingCell . '</td></tr>'
            . '<tr class="grand"><td>GRAND TOTAL</td><td class="money">' . PdfDocument::money($payable, $rate) . '</td></tr>'
        )
        . $notesHtml
        . '<div class="notice">' . $e($notice) . '</div>'
        . PdfDocument::footer('Thank you for considering Aleppo Gift.')
    );
}

function quotation_redirect(string $type, string $text): void {
    $_SESSION['quotation_editor_flash'] = ['type' => $type, 'text' => $text];
    header('Location: edit_quotation.php');
    exit;
}

$db = new Database();
$quote = quotation_draft();
$rate = defined('AED_TO_USD_RATE') ? (float)AED_TO_USD_RATE : 0.2723;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $quote = quotation_apply($quote, $_POST);

        if (isset($_POST['add_item'])) {
            $name = quotation_plain((string)($_POST['item_name'] ?? ''));
            $productId = (int)($_POST['item_product_id'] ?? 0);
            $price = ($_POST['item_price'] ?? '') === '' ? -1 : (float)$_POST['item_price'];
            $image = '';
            if ($productId > 0) {
                $product = $db->query(
                    "SELECT id, name_en, name_ar, price,
                            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = products.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image_path
                     FROM products WHERE id = ?",
                    [$productId]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$product) {
                    throw new Exception('Product ID ' . $productId . ' was not found');
                }
                if ($name === '') {
                    $name = quotation_plain((string)($product['name_en'] ?: $product['name_ar']));
                }
                if ($price < 0) {
                    $price = (float)$product['price'];
                }
                $image = (string)($product['image_path'] ?? '');
            }
            if ($name === '' || $price < 0) {
                throw new Exception('Enter a product name and unit price before adding a line.');
            }
            $quote['items'][] = [
                'id' => (int)$quote['next_id'],
                'product_id' => $productId,
                'name' => $name,
                'variant' => quotation_plain((string)($_POST['item_variant'] ?? '')),
                'qty' => max(1, (int)($_POST['item_qty'] ?? 1)),
                'price' => round($price, 2),
                'image' => $image,
            ];
            $quote['next_id'] = (int)$quote['next_id'] + 1;
            $_SESSION['admin_quotation'] = $quote;
            quotation_redirect('success', 'Line added.');
        }

        if (isset($_POST['delete_item'])) {
            $removeId = (int)$_POST['delete_item'];
            $quote['items'] = array_values(array_filter(
                $quote['items'],
                static function ($item) use ($removeId) {
                    return (int)$item['id'] !== $removeId;
                }
            ));
            $_SESSION['admin_quotation'] = $quote;
            quotation_redirect('success', 'Line removed.');
        }

        $_SESSION['admin_quotation'] = $quote;

        if (isset($_POST['view_pdf'])) {
            $html = quotation_pdf_html($quote, $rate);
            $pdf = ArabicPdfRenderer::render($html);
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $quote['number'] . '.pdf"');
            echo $pdf->output();
            exit;
        }

        quotation_redirect('success', 'Quotation saved.');
    } catch (Exception $e) {
        quotation_redirect('error', $e->getMessage());
    }
}

$message = '';
$messageType = '';
if (!empty($_SESSION['quotation_editor_flash']) && is_array($_SESSION['quotation_editor_flash'])) {
    $message = (string)($_SESSION['quotation_editor_flash']['text'] ?? '');
    $messageType = (string)($_SESSION['quotation_editor_flash']['type'] ?? 'success');
    unset($_SESSION['quotation_editor_flash']);
}

$subtotal = quotation_subtotal($quote);
$shippingKnown = (float)$quote['shipping'] > 0;
$payable = $subtotal + ($shippingKnown ? (float)$quote['shipping'] : 0);
$validLabel = date('F j, Y', strtotime((string)$quote['valid_until']) ?: time());
$shippingLabel = $shippingKnown
    ? 'AED ' . number_format((float)$quote['shipping'], 2) . ' (USD ' . number_format((float)$quote['shipping'] * $rate, 2) . ')'
    : 'Shipping is calculated at checkout';
$notice = $shippingKnown
    ? 'Estimated shipping is included in this quotation. The final amount is confirmed when the order is placed. This quotation is valid for 7 days.'
    : 'Shipping is calculated at checkout after the country and city are selected, and is not included in this total. This quotation is valid for 7 days.';

$page_title = 'Edit Quotation';
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/edit-invoice.css">
<div class="edit-invoice-page">
    <?php if ($message): ?>
        <div class="sheet-alert is-<?= $messageType === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off">
        <button type="submit" name="save_quotation" value="1" hidden formnovalidate></button>
        <div class="invoice-sheet">
            <div class="sheet-head">
                <div class="sheet-brand">
                    <div class="brand">Aleppo <span>Gift</span></div>
                    <div class="brand-sub">
                        Premium Syrian Products<br>
                        www.aleppogift.com<br>
                        +971 56 112 5320
                    </div>
                </div>
                <div class="sheet-doc">
                    <div class="kicker">ALEPPO GIFT</div>
                    <div class="title">QUOTATION</div>
                    <div class="meta">
                        Date: <?= htmlspecialchars(date('F j, Y')) ?><br>
                        Quotation #: <?= htmlspecialchars((string)$quote['number']) ?><br>
                        <label class="sheet-line" style="justify-content: flex-end;"><span>Valid until:</span>
                            <input type="date" name="valid_until" class="sheet-input" value="<?= htmlspecialchars((string)$quote['valid_until']) ?>">
                        </label>
                    </div>
                </div>
            </div>
            <div class="sheet-rule"></div>

            <div class="sheet-parties">
                <div class="box">
                    <h3>PREPARED FOR</h3>
                    <input type="text" name="customer_name" class="sheet-input is-strong" value="<?= htmlspecialchars((string)$quote['customer_name']) ?>" placeholder="Customer name">
                    <label class="sheet-line"><span>Phone:</span><input type="text" name="phone" class="sheet-input" value="<?= htmlspecialchars((string)$quote['phone']) ?>"></label>
                    <label class="sheet-line"><span>Email:</span><input type="text" name="email" class="sheet-input" value="<?= htmlspecialchars((string)$quote['email']) ?>"></label>
                    <textarea name="address" class="sheet-input sheet-area" rows="2" placeholder="Address"><?= htmlspecialchars((string)$quote['address']) ?></textarea>
                </div>
                <div class="box">
                    <h3>DELIVERY</h3>
                    <label class="sheet-line"><span>Country:</span><input type="text" name="country" class="sheet-input" value="<?= htmlspecialchars((string)$quote['country']) ?>" placeholder="Country"></label>
                    <label class="sheet-line"><span>City:</span><input type="text" name="city" class="sheet-input" value="<?= htmlspecialchars((string)$quote['city']) ?>" placeholder="City"></label>
                    <label class="sheet-line">
                        <span>Weight:</span>
                        <input type="number" name="weight" class="sheet-input sheet-qty" min="0" step="0.01" value="<?= htmlspecialchars(number_format((float)$quote['weight'], 2, '.', '')) ?>">
                        <span>kg</span>
                    </label>
                    <div class="sheet-line"><span>Shipping:</span><strong id="shippingCostLabel"><?= htmlspecialchars($shippingLabel) ?></strong></div>
                </div>
            </div>

            <div class="items-wrap">
                <table class="items">
                    <colgroup>
                        <col style="width:6%">
                        <col style="width:12%">
                        <col style="width:36%">
                        <col style="width:10%">
                        <col style="width:16%">
                        <col style="width:16%">
                        <col style="width:4%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>IMAGE</th>
                            <th>PRODUCT</th>
                            <th>QTY</th>
                            <th>UNIT PRICE</th>
                            <th>TOTAL</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$quote['items']): ?>
                            <tr>
                                <td class="center muted" colspan="7">No items on this quotation</td>
                            </tr>
                        <?php endif; ?>
                        <?php
                        $itemNumber = 1;
                        foreach ($quote['items'] as $item):
                            $lineTotal = (float)$item['qty'] * (float)$item['price'];
                            $image = quotation_image_src($item['image'] ?? '');
                        ?>
                            <tr class="<?= $itemNumber % 2 === 0 ? 'alt' : '' ?>" data-line>
                                <td class="center"><?= $itemNumber++ ?></td>
                                <td class="center">
                                    <?php if ($image !== ''): ?>
                                        <img src="<?= htmlspecialchars($image) ?>" alt="" class="item-thumb" onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
                                        <div class="item-thumb-empty" hidden><i class="fas fa-image"></i></div>
                                    <?php else: ?>
                                        <div class="item-thumb-empty"><i class="fas fa-image"></i></div>
                                    <?php endif; ?>
                                    <input type="hidden" name="items[<?= (int)$item['id'] ?>][product_id]" value="<?= (int)$item['product_id'] ?>">
                                    <input type="hidden" name="items[<?= (int)$item['id'] ?>][image]" value="<?= htmlspecialchars((string)$item['image']) ?>">
                                </td>
                                <td>
                                    <input type="text" class="sheet-input pname" name="items[<?= (int)$item['id'] ?>][name]" value="<?= htmlspecialchars((string)$item['name']) ?>">
                                    <input type="text" class="sheet-input" name="items[<?= (int)$item['id'] ?>][variant]" value="<?= htmlspecialchars((string)$item['variant']) ?>" placeholder="Color / size">
                                    <?php if (!empty($item['product_id'])): ?>
                                        <div class="muted">ID: <?= (int)$item['product_id'] ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="center">
                                    <input type="number" data-qty class="sheet-input sheet-qty" min="1" name="items[<?= (int)$item['id'] ?>][qty]" value="<?= (int)$item['qty'] ?>">
                                </td>
                                <td class="money">
                                    <div class="aed">AED <input type="number" data-price class="sheet-input sheet-money" min="0" step="0.01" name="items[<?= (int)$item['id'] ?>][price]" value="<?= htmlspecialchars(number_format((float)$item['price'], 2, '.', '')) ?>"></div>
                                    <div class="usd" data-price-usd>USD <?= number_format((float)$item['price'] * $rate, 2) ?></div>
                                </td>
                                <td class="money">
                                    <div class="aed">AED <span data-line-total><?= number_format($lineTotal, 2) ?></span></div>
                                    <div class="usd" data-line-usd>USD <?= number_format($lineTotal * $rate, 2) ?></div>
                                </td>
                                <td class="center">
                                    <button type="submit" name="delete_item" value="<?= (int)$item['id'] ?>" class="sheet-remove" formnovalidate onclick="return confirm('Remove this line from the quotation?');" aria-label="Remove line">&times;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="sheet-add">
                <div class="section-title">ADD A LINE</div>
                <div class="product-search-wrap">
                    <label for="productSearch">Search product</label>
                    <input type="text" id="productSearch" class="sheet-input" placeholder="Name, SKU, or product ID" autocomplete="off">
                    <div id="productSearchResults" class="product-search-results" hidden></div>
                </div>
                <div id="selectedProductPreview" class="selected-product" hidden>
                    <img id="selectedProductImage" src="" alt="" class="item-thumb">
                    <div id="selectedProductImageEmpty" class="item-thumb-empty" hidden><i class="fas fa-image"></i></div>
                    <div>
                        <strong id="selectedProductLabel">Product</strong>
                        <div class="muted">This image is used on the printed quotation.</div>
                    </div>
                </div>
                <input type="hidden" name="item_product_id" id="itemProductId" value="">
                <div class="add-grid">
                    <div>
                        <label for="itemProductName">Product</label>
                        <input type="text" name="item_name" id="itemProductName" class="sheet-input" placeholder="Product name">
                    </div>
                    <div>
                        <label for="itemQuantity">Qty</label>
                        <input type="number" name="item_qty" id="itemQuantity" class="sheet-input" value="1" min="1">
                    </div>
                    <div>
                        <label for="itemProductPrice">Unit price</label>
                        <input type="number" name="item_price" id="itemProductPrice" class="sheet-input" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <button type="submit" name="add_item" class="sheet-add-btn">Add line</button>
                </div>
            </div>

            <div class="totals-wrap">
                <table class="totals">
                    <tr>
                        <td class="lbl">Subtotal</td>
                        <td class="money">
                            <div class="aed">AED <span id="subtotalAmount"><?= number_format($subtotal, 2) ?></span></div>
                            <div class="usd" id="subtotalUsd">USD <?= number_format($subtotal * $rate, 2) ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Shipping</td>
                        <td class="money">
                            <div class="aed"><span id="shippingPrefix"><?= $shippingKnown ? 'AED' : '' ?></span> <input type="number" name="shipping" class="sheet-input sheet-money" min="0" step="0.01" value="<?= htmlspecialchars(number_format((float)$quote['shipping'], 2, '.', '')) ?>"></div>
                            <div class="usd" id="shippingUsd"><?= $shippingKnown ? 'USD ' . number_format((float)$quote['shipping'] * $rate, 2) : 'At checkout' ?></div>
                        </td>
                    </tr>
                    <tr class="grand">
                        <td>GRAND TOTAL</td>
                        <td class="money">
                            <div class="aed">AED <span id="grandAed"><?= number_format($payable, 2) ?></span></div>
                            <div class="usd" id="grandUsd">USD <?= number_format($payable * $rate, 2) ?></div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="notes-block">
                <div class="section-title">NOTE</div>
                <textarea name="note" class="sheet-input" rows="3" placeholder="Printed on the quotation"><?= htmlspecialchars((string)$quote['note']) ?></textarea>
            </div>
            <div class="sheet-notice" id="quoteNotice"><?= htmlspecialchars($notice) ?></div>
            <div class="sheet-foot">
                Thank you for considering Aleppo Gift.<br>
                www.aleppogift.com &nbsp;|&nbsp; info@aleppogift.com<br>
                Amounts are shown in AED with the USD equivalent.
            </div>
        </div>

        <div class="sheet-actions">
            <button type="submit" name="save_quotation" class="sheet-save" formnovalidate>Save quotation</button>
            <button type="submit" name="view_pdf" class="sheet-link" formnovalidate>View PDF</button>
        </div>
    </form>
</div>
<script>
    const usdRate = <?= json_encode($rate) ?>;

    function money(amount) {
        return (Math.round((amount + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    function calculateTotal() {
        const shippingInput = document.querySelector('[name="shipping"]');
        if (!shippingInput) return;
        let subtotal = 0;
        document.querySelectorAll('[data-line]').forEach(function (row) {
            const qtyInput = row.querySelector('[data-qty]');
            const priceInput = row.querySelector('[data-price]');
            if (!qtyInput || !priceInput) return;
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const lineTotal = qty * price;
            subtotal += lineTotal;
            const lineLabel = row.querySelector('[data-line-total]');
            const lineUsd = row.querySelector('[data-line-usd]');
            const priceUsd = row.querySelector('[data-price-usd]');
            if (lineLabel) lineLabel.textContent = money(lineTotal);
            if (lineUsd) lineUsd.textContent = 'USD ' + money(lineTotal * usdRate);
            if (priceUsd) priceUsd.textContent = 'USD ' + money(price * usdRate);
        });
        const shipping = Math.max(0, parseFloat(shippingInput.value) || 0);
        const total = subtotal + (shipping > 0 ? shipping : 0);
        const shippingPrefix = document.getElementById('shippingPrefix');
        const shippingUsd = document.getElementById('shippingUsd');
        const shippingLabel = document.getElementById('shippingCostLabel');
        const notice = document.getElementById('quoteNotice');
        if (document.getElementById('subtotalAmount')) document.getElementById('subtotalAmount').textContent = money(subtotal);
        if (document.getElementById('subtotalUsd')) document.getElementById('subtotalUsd').textContent = 'USD ' + money(subtotal * usdRate);
        if (shippingPrefix) shippingPrefix.textContent = shipping > 0 ? 'AED' : '';
        if (shippingUsd) shippingUsd.textContent = shipping > 0 ? 'USD ' + money(shipping * usdRate) : 'At checkout';
        if (shippingLabel) {
            shippingLabel.textContent = shipping > 0
                ? 'AED ' + money(shipping) + ' (USD ' + money(shipping * usdRate) + ')'
                : 'Shipping is calculated at checkout';
        }
        if (document.getElementById('grandAed')) document.getElementById('grandAed').textContent = money(total);
        if (document.getElementById('grandUsd')) document.getElementById('grandUsd').textContent = 'USD ' + money(total * usdRate);
        if (notice) {
            notice.textContent = shipping > 0
                ? 'Estimated shipping is included in this quotation. The final amount is confirmed when the order is placed. This quotation is valid for 7 days.'
                : 'Shipping is calculated at checkout after the country and city are selected, and is not included in this total. This quotation is valid for 7 days.';
        }
    }

    document.querySelectorAll('[data-qty], [data-price], [name="shipping"]').forEach(function (input) {
        input.addEventListener('input', calculateTotal);
    });

    const addLineButton = document.querySelector('[name="add_item"]');
    if (addLineButton) {
        addLineButton.addEventListener('click', function (event) {
            const name = document.getElementById('itemProductName');
            const price = document.getElementById('itemProductPrice');
            if (!name || !name.value.trim() || !price || price.value === '') {
                event.preventDefault();
                window.alert('Enter a product name and unit price before adding a line.');
                if (name && !name.value.trim()) name.focus();
                else if (price) price.focus();
            }
        });
    }

    function productImageSrc(path) {
        if (!path) return '';
        path = String(path).replace(/\\/g, '/').replace(/^(\.\.\/)+/, '').replace(/^\/+/, '');
        if (!path) return '';
        return '../' + path.split('/').map(encodeURIComponent).join('/');
    }

    function selectQuotationProduct(product) {
        document.getElementById('itemProductId').value = product.id;
        document.getElementById('itemProductName').value = product.name_en || product.name_ar || '';
        document.getElementById('itemProductPrice').value = parseFloat(product.price || 0).toFixed(2);
        document.getElementById('productSearch').value = '';
        document.getElementById('productSearchResults').hidden = true;
        const preview = document.getElementById('selectedProductPreview');
        const image = document.getElementById('selectedProductImage');
        const empty = document.getElementById('selectedProductImageEmpty');
        const src = productImageSrc(product.image_path);
        preview.hidden = false;
        document.getElementById('selectedProductLabel').textContent = (product.name_en || 'Product') + ' · ID ' + product.id;
        if (src) {
            image.hidden = false;
            image.onerror = function () {
                image.hidden = true;
                empty.hidden = false;
            };
            image.src = src;
            empty.hidden = true;
        } else {
            image.hidden = true;
            image.removeAttribute('src');
            empty.hidden = false;
        }
    }

    (function () {
        const input = document.getElementById('productSearch');
        const results = document.getElementById('productSearchResults');
        if (!input || !results) return;
        let timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            const query = input.value.trim();
            if (query.length < 2) {
                results.hidden = true;
                results.innerHTML = '';
                return;
            }
            timer = setTimeout(function () {
                fetch('ajax/search_products.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: query })
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    results.innerHTML = '';
                    if (!data.success || !data.products || !data.products.length) {
                        results.innerHTML = '<div style="padding:10px;color:#8a6d4f;">' + (data.error ? 'Search failed' : 'No products found') + '</div>';
                        results.hidden = false;
                        return;
                    }
                    data.products.forEach(function (product) {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'product-search-item';
                        const src = productImageSrc(product.image_path);
                        button.innerHTML = src
                            ? '<img src="' + src.replace(/"/g, '&quot;') + '" alt="" class="item-thumb">'
                            : '<div class="item-thumb-empty"><i class="fas fa-image"></i></div>';
                        const name = document.createElement('div');
                        const strong = document.createElement('strong');
                        const meta = document.createElement('div');
                        meta.className = 'muted';
                        strong.textContent = product.name_en || product.name_ar || 'Product';
                        meta.textContent = 'ID ' + product.id + ' · AED ' + parseFloat(product.price || 0).toFixed(2);
                        name.appendChild(strong);
                        name.appendChild(meta);
                        button.appendChild(name);
                        button.addEventListener('click', function () { selectQuotationProduct(product); });
                        results.appendChild(button);
                    });
                    results.hidden = false;
                })
                .catch(function () {
                    results.innerHTML = '<div style="padding:10px;color:#8a6d4f;">Search failed</div>';
                    results.hidden = false;
                });
            }, 250);
        });
        document.addEventListener('click', function (event) {
            if (!input.contains(event.target) && !results.contains(event.target)) {
                results.hidden = true;
            }
        });
    })();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
