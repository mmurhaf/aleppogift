<?php
/**
 * Table-based HTML for order emails. Inline styles follow the storefront
 * (warm paper, charcoal ink, terracotta accent) and survive Gmail and Outlook.
 */

function ag_email_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ag_email_money($amount): string
{
    return 'AED ' . number_format((float)$amount, 2);
}

function ag_email_site_url(): string
{
    $base = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : 'https://aleppogift.com';
    return $base !== '' ? $base : 'https://aleppogift.com';
}

function ag_order_is_cash_on_delivery($order): bool
{
    $method = strtolower(trim((string)($order['payment_method'] ?? '')));
    return in_array($method, ['cod', 'cash', 'cash on delivery', 'cash_on_delivery'], true);
}

function ag_order_is_paid($order): bool
{
    $status = strtolower(trim((string)($order['payment_status'] ?? '')));
    return in_array($status, ['paid', 'completed'], true);
}

/** Unpaid cash-on-delivery orders must not receive or display an invoice. */
function ag_order_invoice_allowed($order): bool
{
    if (!is_array($order)) {
        return false;
    }
    if (ag_order_is_cash_on_delivery($order) && !ag_order_is_paid($order)) {
        return false;
    }
    return true;
}

function ag_email_payment_label($order): string
{
    $method = trim((string)($order['payment_method'] ?? ''));
    if (ag_order_is_cash_on_delivery($order)) {
        return 'Cash on delivery';
    }
    if (strcasecmp($method, 'Ziina') === 0 || strcasecmp($method, 'online') === 0 || strcasecmp($method, 'card') === 0) {
        return 'Card';
    }
    return $method !== '' ? $method : '—';
}

function ag_email_status_label($order): string
{
    if (ag_order_is_cash_on_delivery($order) && !ag_order_is_paid($order)) {
        return 'Pay on delivery';
    }
    $status = trim((string)($order['payment_status'] ?? ''));
    return $status !== '' ? ucfirst($status) : '—';
}

function ag_invoice_file_path($order_id): string
{
    return dirname(__DIR__) . '/invoice/invoice_' . (int)$order_id . '.pdf';
}

function ag_ensure_confirmation_email_column($db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $row = $db->query(
            "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'confirmation_email_at'"
        )->fetch(PDO::FETCH_ASSOC);
        if ((int)($row['c'] ?? 0) > 0) {
            $ready = true;
            return true;
        }
        $db->query('ALTER TABLE orders ADD COLUMN confirmation_email_at DATETIME NULL DEFAULT NULL');
        $ready = true;
    } catch (Throwable $e) {
        error_log('confirmation_email_at column: ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

function ag_confirmation_marker_path($order_id): string
{
    $dir = dirname(__DIR__) . '/storage/mail';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/confirmed_' . (int)$order_id . '.txt';
}

function ag_confirmation_already_sent($db, $order_id): bool
{
    if (is_file(ag_confirmation_marker_path($order_id))) {
        return true;
    }
    if (!$db || !ag_ensure_confirmation_email_column($db)) {
        return false;
    }
    try {
        $row = $db->query(
            'SELECT confirmation_email_at FROM orders WHERE id = :id',
            ['id' => (int)$order_id]
        )->fetch(PDO::FETCH_ASSOC);
        return !empty($row['confirmation_email_at']);
    } catch (Throwable $e) {
        return false;
    }
}

function ag_mark_confirmation_sent($db, $order_id): void
{
    @file_put_contents(ag_confirmation_marker_path($order_id), date('c'));
    if (!$db || !ag_ensure_confirmation_email_column($db)) {
        return;
    }
    try {
        $db->query(
            'UPDATE orders SET confirmation_email_at = NOW() WHERE id = :id AND confirmation_email_at IS NULL',
            ['id' => (int)$order_id]
        );
    } catch (Throwable $e) {
        error_log('confirmation_email_at update: ' . $e->getMessage());
    }
}

function ag_email_font_serif(): string
{
    return "Georgia,'Times New Roman',Times,serif";
}

function ag_email_font_sans(): string
{
    return "'Helvetica Neue',Helvetica,Arial,sans-serif";
}

function ag_email_product_image_url($path): string
{
    $path = str_replace('\\', '/', trim((string)$path));
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    while (str_starts_with($path, '../')) {
        $path = substr($path, 3);
    }
    $parts = array_map('rawurlencode', explode('/', ltrim($path, '/')));
    $parts = array_values(array_filter($parts, function ($part) {
        return $part !== '';
    }));
    if ($parts === []) {
        return '';
    }
    return ag_email_site_url() . '/' . implode('/', $parts);
}

function ag_email_button(string $href, string $label): string
{
    $href = ag_email_h($href);
    $label = ag_email_h($label);
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr>'
        . '<td bgcolor="#2d2b22" style="background:#2d2b22;">'
        . '<a href="' . $href . '" style="display:inline-block;padding:14px 28px;font-family:' . ag_email_font_sans() . ';font-size:11px;letter-spacing:0.18em;text-transform:uppercase;line-height:1.2;color:#fdfbf8;text-decoration:none;">'
        . $label . '</a></td></tr></table>';
}

function ag_email_heading(string $text): string
{
    return '<p style="margin:0 0 14px;font-family:' . ag_email_font_serif() . ';font-size:24px;font-weight:400;line-height:1.2;color:#2d2b22;">'
        . ag_email_h($text) . '</p>';
}

function ag_email_fact_cell(string $label, string $value, bool $dividerRight, bool $dividerBottom): string
{
    $border = '';
    if ($dividerRight) {
        $border .= 'border-right:1px solid #dfddd4;';
    }
    if ($dividerBottom) {
        $border .= 'border-bottom:1px solid #dfddd4;';
    }
    return '<td class="ag-fact" width="50%" valign="top" style="width:50%;padding:16px 18px;' . $border . '">'
        . '<div style="font-family:' . ag_email_font_sans() . ';font-size:10px;letter-spacing:0.2em;text-transform:uppercase;color:#75716a;">' . ag_email_h($label) . '</div>'
        . '<div style="margin-top:6px;font-family:' . ag_email_font_serif() . ';font-size:17px;line-height:1.3;color:#2d2b22;">' . ag_email_h($value) . '</div>'
        . '</td>';
}

function ag_email_facts(array $pairs): string
{
    $items = [];
    foreach ($pairs as $label => $value) {
        $items[] = [(string)$label, (string)$value];
    }
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f9f7ee" style="margin:0 0 28px;background:#f9f7ee;border:1px solid #dfddd4;">';
    $count = count($items);
    for ($i = 0; $i < $count; $i += 2) {
        $bottom = ($i + 2) < $count;
        $html .= '<tr>';
        $html .= ag_email_fact_cell($items[$i][0], $items[$i][1], isset($items[$i + 1]), $bottom);
        if (isset($items[$i + 1])) {
            $html .= ag_email_fact_cell($items[$i + 1][0], $items[$i + 1][1], false, $bottom);
        } else {
            $html .= '<td class="ag-fact" width="50%" style="width:50%;">&nbsp;</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</table>';
}

function ag_email_steps(array $steps): string
{
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">';
    $last = count($steps);
    $n = 1;
    foreach ($steps as $step) {
        $pad = $n < $last ? 'padding:0 0 14px;' : 'padding:0;';
        $html .= '<tr>'
            . '<td width="40" valign="top" style="width:40px;' . $pad . 'font-family:' . ag_email_font_serif() . ';font-size:16px;line-height:1.45;color:#b0633d;">'
            . str_pad((string)$n, 2, '0', STR_PAD_LEFT) . '</td>'
            . '<td valign="top" style="' . $pad . 'font-family:' . ag_email_font_sans() . ';font-size:15px;line-height:1.5;color:#3b3b3b;">'
            . ag_email_h($step) . '</td></tr>';
        $n++;
    }
    return $html . '</table>';
}

function ag_email_help_line(): string
{
    $from = defined('EMAIL_FROM') ? EMAIL_FROM : 'sales@aleppogift.com';
    return '<p style="margin:20px 0 24px;font-family:' . ag_email_font_sans() . ';font-size:14px;line-height:1.65;color:#3b3b3b;">'
        . 'Questions? Email <a href="mailto:' . ag_email_h($from) . '" style="color:#2d2b22;">' . ag_email_h($from) . '</a>'
        . ' or <a href="https://wa.me/971561125320" style="color:#2d2b22;">WhatsApp +971 56 112 5320</a>.</p>';
}

function ag_email_shell(string $preheader, string $kicker, string $title, string $innerHtml): string
{
    $site = ag_email_site_url();
    $logo = ag_email_h($site . '/uploads/logo.png');
    $home = ag_email_h($site);
    $track = ag_email_h($site . '/track_order.php');
    $from = defined('EMAIL_FROM') ? EMAIL_FROM : 'sales@aleppogift.com';
    $year = date('Y');
    $serif = ag_email_font_serif();
    $sans = ag_email_font_sans();

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light">'
        . '<meta name="supported-color-schemes" content="light">'
        . '<title>' . ag_email_h($title) . '</title>'
        . '<style type="text/css">@media only screen and (max-width:620px){'
        . '.ag-shell{width:100%!important;}'
        . '.ag-pad{padding-left:20px!important;padding-right:20px!important;}'
        . '.ag-fact{display:block!important;width:100%!important;border-right:0!important;box-sizing:border-box;}'
        . '}</style></head>'
        . '<body style="margin:0;padding:0;background:#f2efe6;">'
        . '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f2efe6;opacity:0;">'
        . ag_email_h($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2efe6" style="background:#f2efe6;">'
        . '<tr><td align="center" style="padding:36px 16px;">'
        . '<table role="presentation" class="ag-shell" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#fdfbf8" style="width:600px;max-width:600px;background:#fdfbf8;border:1px solid #dfddd4;">'
        . '<tr><td class="ag-pad" bgcolor="#fdfbf8" align="center" style="background:#fdfbf8;padding:40px 40px 28px;text-align:center;border-bottom:1px solid #dfddd4;">'
        . '<a href="' . $home . '" style="text-decoration:none;">'
        . '<img src="' . $logo . '" alt="AleppoGift" width="148" style="display:block;margin:0 auto 18px;border:0;height:auto;max-width:148px;">'
        . '</a>'
        . '<p style="margin:0;font-family:' . $sans . ';font-size:11px;letter-spacing:0.28em;text-transform:uppercase;color:#b0633d;">'
        . ag_email_h($kicker) . '</p>'
        . '<h1 style="margin:12px 0 0;font-family:' . $serif . ';font-weight:400;font-size:32px;line-height:1.15;color:#2d2b22;">'
        . ag_email_h($title) . '</h1>'
        . '</td></tr>'
        . '<tr><td class="ag-pad" style="padding:32px 40px 36px;font-family:' . $sans . ';font-size:15px;line-height:1.65;color:#3b3b3b;">'
        . $innerHtml
        . '</td></tr>'
        . '<tr><td class="ag-pad" bgcolor="#f9f7ee" align="center" style="background:#f9f7ee;padding:28px 40px 32px;border-top:1px solid #dfddd4;font-family:' . $sans . ';font-size:12px;line-height:1.7;color:#75716a;text-align:center;">'
        . '<p style="margin:0 0 4px;font-family:' . $serif . ';font-size:20px;line-height:1.2;color:#2d2b22;">AleppoGift</p>'
        . '<p style="margin:0 0 14px;font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#75716a;">Tableware, coffee sets &amp; home gifts</p>'
        . '<p style="margin:0;">'
        . '<a href="' . $home . '" style="color:#2d2b22;text-decoration:none;">Shop</a>'
        . '&nbsp;&nbsp;&middot;&nbsp;&nbsp;'
        . '<a href="' . $track . '" style="color:#2d2b22;text-decoration:none;">Track an order</a>'
        . '&nbsp;&nbsp;&middot;&nbsp;&nbsp;'
        . '<a href="mailto:' . ag_email_h($from) . '" style="color:#2d2b22;text-decoration:none;">' . ag_email_h($from) . '</a>'
        . '</p>'
        . '<p style="margin:16px 0 0;color:#acaaa1;">&copy; ' . $year . ' AleppoGift</p>'
        . '</td></tr></table></td></tr></table></body></html>';
}

function ag_build_order_confirmation_html(array $order, array $items, string $customerName, array $customerData, bool $includeInvoiceLink): string
{
    $orderId = (int)($order['id'] ?? 0);
    $site = ag_email_site_url();
    $name = $customerName !== '' ? $customerName : (string)($customerData['fullname'] ?? 'there');
    $dateRaw = $order['order_date'] ?? $order['created_at'] ?? 'now';
    $dateLabel = date('F j, Y', strtotime((string)$dateRaw) ?: time());
    $cod = ag_order_is_cash_on_delivery($order) && !ag_order_is_paid($order);

    $sans = ag_email_font_sans();
    $serif = ag_email_font_serif();
    $hasImages = false;
    foreach ($items as $item) {
        $imagePath = trim((string)($item['image_path'] ?? $item['image'] ?? $item['selected_image'] ?? ''));
        if ($imagePath !== '') {
            $hasImages = true;
            break;
        }
    }

    $rows = '';
    foreach ($items as $item) {
        $product = trim((string)($item['display_name'] ?? $item['product_name'] ?? $item['name_en'] ?? 'Item'));
        if ($product === '') {
            $product = 'Item';
        }
        $details = [];
        if (!empty($item['size'])) {
            $details[] = 'Size: ' . $item['size'];
        }
        if (!empty($item['color'])) {
            $details[] = 'Color: ' . $item['color'];
        }
        $detailHtml = $details
            ? '<div style="margin-top:3px;font-family:' . $sans . ';font-size:12px;line-height:1.4;color:#75716a;">' . ag_email_h(implode(' · ', $details)) . '</div>'
            : '';
        $nameHtml = ag_email_h($product);
        if (!empty($item['product_id'])) {
            $productUrl = ag_email_h($site . '/product.php?id=' . (int)$item['product_id']);
            $nameHtml = '<a href="' . $productUrl . '" style="color:#2d2b22;text-decoration:none;">' . $nameHtml . '</a>';
        }
        $qty = (int)($item['quantity'] ?? 0);
        $price = (float)($item['price'] ?? 0);
        $line = $price * $qty;
        $unitHtml = $qty > 1
            ? '<div style="margin-top:2px;font-family:' . $sans . ';font-size:11px;color:#75716a;">' . ag_email_h(ag_email_money($price)) . ' each</div>'
            : '';
        $thumb = '';
        if ($hasImages) {
            $src = ag_email_product_image_url($item['image_path'] ?? $item['image'] ?? $item['selected_image'] ?? '');
            $thumbInner = $src !== ''
                ? '<img src="' . ag_email_h($src) . '" alt="" width="64" height="64" style="display:block;width:64px;height:64px;border:0;">'
                : '&nbsp;';
            $thumb = '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td width="64" height="64" bgcolor="#f2efe6" valign="middle" style="width:64px;height:64px;background:#f2efe6;border:1px solid #dfddd4;">'
                . $thumbInner . '</td></tr></table>';
        }
        $cell = 'padding:16px 0;border-bottom:1px solid #dfddd4;vertical-align:top;';
        $productCell = '<td style="' . $cell . '">';
        if ($hasImages) {
            $productCell .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td width="76" valign="top" style="width:76px;padding:0 12px 0 0;">' . $thumb . '</td>'
                . '<td valign="middle" style="font-family:' . $serif . ';font-size:16px;line-height:1.3;color:#2d2b22;">'
                . $nameHtml . $detailHtml . '</td></tr></table>';
        } else {
            $productCell .= '<div style="font-family:' . $serif . ';font-size:16px;line-height:1.3;color:#2d2b22;">' . $nameHtml . '</div>' . $detailHtml;
        }
        $productCell .= '</td>';
        $rows .= '<tr>' . $productCell
            . '<td width="48" align="center" style="' . $cell . 'width:48px;text-align:center;font-family:' . $sans . ';font-size:14px;color:#2d2b22;">' . $qty . '</td>'
            . '<td width="120" align="right" style="' . $cell . 'width:120px;text-align:right;font-family:' . $sans . ';font-size:14px;color:#2d2b22;white-space:nowrap;">'
            . ag_email_h(ag_email_money($line)) . $unitHtml . '</td></tr>';
    }
    if ($rows === '') {
        $rows = '<tr><td colspan="3" style="padding:16px 0;border-bottom:1px solid #dfddd4;font-family:' . $sans . ';font-size:14px;color:#75716a;">Your items are recorded on this order.</td></tr>';
    }

    $discount = (float)($order['discount_amount'] ?? 0);
    $shipping = (float)($order['shipping_aed'] ?? 0);
    $total = (float)($order['total_amount'] ?? 0);
    $subtotal = $total - $shipping + $discount;

    $moneyCell = 'padding:5px 0;text-align:right;font-family:' . $sans . ';font-size:14px;color:#2d2b22;white-space:nowrap;';
    $labelCell = 'padding:5px 0;font-family:' . $sans . ';font-size:14px;color:#75716a;';
    $summary = '<tr><td style="' . $labelCell . '">Subtotal</td>'
        . '<td style="' . $moneyCell . '">' . ag_email_h(ag_email_money($subtotal)) . '</td></tr>'
        . '<tr><td style="' . $labelCell . '">Shipping</td>'
        . '<td style="' . $moneyCell . '">' . ag_email_h(ag_email_money($shipping)) . '</td></tr>';
    if ($discount > 0) {
        $summary .= '<tr><td style="' . $labelCell . '">Discount</td>'
            . '<td style="' . $moneyCell . '">&minus; ' . ag_email_h(ag_email_money($discount)) . '</td></tr>';
    }
    $summary .= '<tr><td style="padding:14px 0 0;border-top:1px solid #2d2b22;font-family:' . $serif . ';font-size:18px;color:#2d2b22;">Total</td>'
        . '<td style="padding:14px 0 0;border-top:1px solid #2d2b22;text-align:right;font-family:' . $serif . ';font-size:18px;color:#b0633d;white-space:nowrap;">' . ag_email_h(ag_email_money($total)) . '</td></tr>';

    $addressLines = array_filter([
        $customerData['fullname'] ?? $name,
        $customerData['address'] ?? '',
        trim((string)($customerData['city'] ?? '') . ((string)($customerData['country'] ?? '') !== '' ? ', ' . $customerData['country'] : '')),
        !empty($customerData['phone']) ? $customerData['phone'] : '',
    ], function ($line) {
        return trim((string)$line) !== '';
    });
    $addressHtml = '';
    foreach ($addressLines as $line) {
        $addressHtml .= ag_email_h($line) . '<br>';
    }
    if ($addressHtml === '') {
        $addressHtml = 'We will use the address saved with this order.';
    }

    $note = trim((string)($order['note'] ?? ''));
    $noteHtml = $note !== ''
        ? '<p style="margin:12px 0 0;font-family:' . $sans . ';font-size:13px;line-height:1.55;color:#75716a;"><span style="color:#2d2b22;">Note.</span> ' . nl2br(ag_email_h($note)) . '</p>'
        : '';

    $panel = 'margin:0 0 28px;background:#f9f7ee;border:1px solid #dfddd4;';
    $invoiceBlock = '';
    if ($includeInvoiceLink) {
        $download = $site . '/download_invoice.php?id=' . $orderId;
        $invoiceBlock = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f9f7ee" style="' . $panel . '">'
            . '<tr><td style="padding:24px 22px;text-align:center;font-family:' . $sans . ';">'
            . '<p style="margin:0 0 6px;font-family:' . $serif . ';font-size:22px;font-weight:400;color:#2d2b22;">Your invoice</p>'
            . '<p style="margin:0 0 16px;font-size:14px;line-height:1.55;color:#3b3b3b;">The invoice PDF is attached to this email.</p>'
            . ag_email_button($download, 'Download invoice')
            . '</td></tr></table>';
    } elseif ($cod) {
        $invoiceBlock = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f9f7ee" style="' . $panel . '">'
            . '<tr><td style="padding:22px;font-family:' . $sans . ';">'
            . '<p style="margin:0 0 6px;font-family:' . $serif . ';font-size:22px;font-weight:400;color:#2d2b22;">No invoice yet</p>'
            . '<p style="margin:0;font-size:14px;line-height:1.6;color:#3b3b3b;">This order is cash on delivery and has not been paid, so no invoice is included. An invoice is issued only after you pay on delivery.</p>'
            . '</td></tr></table>';
    }

    $headCell = 'padding:0 0 8px;border-bottom:1px solid #2d2b22;font-family:' . $sans . ';font-size:10px;letter-spacing:0.16em;text-transform:uppercase;color:#75716a;';
    $steps = [
        'We prepare and check your order.',
        'It is packed and handed to the courier.',
        $cod ? 'You receive it, then pay the courier in cash.' : 'You receive it at the address above.',
    ];
    $inner = '<p style="margin:0 0 8px;font-family:' . $serif . ';font-size:18px;color:#2d2b22;">Dear ' . ag_email_h($name) . ',</p>'
        . '<p style="margin:0 0 22px;">Your order <strong style="font-weight:500;color:#2d2b22;">#' . $orderId . '</strong> is confirmed. We are preparing it now.</p>'
        . ag_email_facts([
            'Order' => '#' . $orderId,
            'Date' => $dateLabel,
            'Payment' => ag_email_payment_label($order),
            'Status' => ag_email_status_label($order),
        ])
        . ag_email_heading('Items')
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;border-collapse:collapse;">'
        . '<tr>'
        . '<td align="left" style="' . $headCell . '">Product</td>'
        . '<td width="48" align="center" style="' . $headCell . 'width:48px;text-align:center;">Qty</td>'
        . '<td width="120" align="right" style="' . $headCell . 'width:120px;text-align:right;">Amount</td>'
        . '</tr>' . $rows . '</table>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 28px;"><tr>'
        . '<td>&nbsp;</td>'
        . '<td width="240" valign="top" style="width:240px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $summary . '</table></td>'
        . '</tr></table>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f9f7ee" style="' . $panel . '">'
        . '<tr><td style="padding:20px 22px;font-family:' . $sans . ';font-size:15px;line-height:1.7;color:#3b3b3b;">'
        . '<p style="margin:0 0 8px;font-family:' . $serif . ';font-size:22px;font-weight:400;line-height:1.2;color:#2d2b22;">Delivery</p>'
        . $addressHtml . $noteHtml
        . '</td></tr></table>'
        . $invoiceBlock
        . ag_email_heading('What happens next')
        . ag_email_steps($steps)
        . ag_email_help_line()
        . ag_email_button($site . '/track_order.php', 'Track your order');

    $title = $cod ? 'Order confirmed' : 'Thank you for your order';
    $preheader = 'AleppoGift order #' . $orderId . ' is confirmed.' . ($cod ? ' Pay on delivery. No invoice is attached.' : '');
    return ag_email_shell($preheader, 'Order #' . $orderId, $title, $inner);
}

function ag_build_admin_invoice_request_html(array $order): string
{
    $orderId = (int)($order['id'] ?? 0);
    $site = ag_email_site_url();
    $lines = '';
    foreach ([
        'Customer' => $order['customer_name'] ?? '',
        'Email' => $order['customer_email'] ?? '',
        'Phone' => $order['customer_phone'] ?? '',
        'Address' => $order['customer_address'] ?? '',
        'City' => trim((string)($order['customer_city'] ?? '') . ((string)($order['customer_country'] ?? '') !== '' ? ', ' . $order['customer_country'] : '')),
        'Total' => isset($order['total_amount']) ? ag_email_money($order['total_amount']) : '',
        'Payment' => 'Cash on delivery — not paid',
    ] as $label => $value) {
        if (trim((string)$value) === '') {
            continue;
        }
        $lines .= '<strong style="font-weight:500;color:#2d2b22;">' . ag_email_h($label) . '</strong> ' . ag_email_h($value) . '<br>';
    }
    $inner = '<p style="margin:0 0 14px;">The customer asked for an invoice for order <strong style="font-weight:500;color:#2d2b22;">#' . $orderId . '</strong>.</p>'
        . '<p style="margin:0 0 18px;">Do not send the invoice yet. This cash-on-delivery order is unpaid, and the invoice is issued only after payment on delivery.</p>'
        . '<p style="margin:0 0 22px;font-size:14px;line-height:1.7;">' . $lines . '</p>'
        . ag_email_button($site . '/admin/order_detail.php?id=' . $orderId, 'Open order');
    return ag_email_shell(
        'Invoice request for unpaid order #' . $orderId,
        'Invoice request',
        'Order #' . $orderId,
        $inner
    );
}

function ag_build_invoice_request_html(array $order, string $customerName): string
{
    $orderId = (int)($order['id'] ?? 0);
    $site = ag_email_site_url();
    $name = $customerName !== '' ? $customerName : 'there';
    $download = $site . '/download_invoice.php?id=' . $orderId;
    $codNote = (ag_order_is_cash_on_delivery($order) && !ag_order_is_paid($order))
        ? '<p style="margin:18px 0 0;font-family:' . ag_email_font_sans() . ';font-size:13px;line-height:1.6;color:#75716a;">This invoice is a request copy. Cash on delivery is still collected when the order arrives.</p>'
        : '';
    $inner = '<p style="margin:0 0 8px;font-family:' . ag_email_font_serif() . ';font-size:18px;color:#2d2b22;">Dear ' . ag_email_h($name) . ',</p>'
        . '<p style="margin:0 0 22px;">You asked for an invoice for order <strong style="font-weight:500;color:#2d2b22;">#' . $orderId . '</strong>. It is attached to this email.</p>'
        . ag_email_button($download, 'Download invoice')
        . $codNote;
    return ag_email_shell(
        'Your requested AleppoGift invoice for order #' . $orderId,
        'Invoice',
        'Invoice for order #' . $orderId,
        $inner
    );
}

function ag_build_simple_confirmation_html($order_id, string $customerName): string
{
    $site = ag_email_site_url();
    $name = $customerName !== '' ? $customerName : 'there';
    $inner = '<p style="margin:0 0 8px;font-family:' . ag_email_font_serif() . ';font-size:18px;color:#2d2b22;">Dear ' . ag_email_h($name) . ',</p>'
        . '<p style="margin:0 0 18px;">Your order <strong style="font-weight:500;color:#2d2b22;">#' . (int)$order_id . '</strong> is confirmed and is being prepared.</p>'
        . ag_email_heading('What happens next')
        . ag_email_steps([
            'We prepare and check your order.',
            'It is packed with care and handed to the courier.',
            'We send tracking details when it ships.',
        ])
        . ag_email_help_line()
        . ag_email_button($site . '/track_order.php', 'Track your order');
    return ag_email_shell(
        'AleppoGift order #' . (int)$order_id . ' is confirmed.',
        'Order #' . (int)$order_id,
        'Your order is confirmed',
        $inner
    );
}

function ag_build_admin_order_html($order_id, $customer_name, $grand_total, $payment_method, array $customer_data): string
{
    $site = ag_email_site_url();
    $lines = '';
    foreach ([
        'Name' => $customer_data['fullname'] ?? $customer_name,
        'Phone' => $customer_data['phone'] ?? '',
        'Address' => $customer_data['address'] ?? '',
        'City' => trim((string)($customer_data['city'] ?? '') . ((string)($customer_data['country'] ?? '') !== '' ? ', ' . $customer_data['country'] : '')),
    ] as $label => $value) {
        if (trim((string)$value) === '') {
            continue;
        }
        $lines .= '<strong style="font-weight:500;color:#2d2b22;">' . ag_email_h($label) . '</strong> ' . ag_email_h($value) . '<br>';
    }
    $inner = '<p style="margin:0 0 18px;">A new order has been placed.</p>'
        . ag_email_facts([
            'Order' => '#' . (int)$order_id,
            'Total' => ag_email_money($grand_total),
            'Customer' => (string)$customer_name,
            'Payment' => ag_email_payment_label(['payment_method' => $payment_method]),
        ])
        . '<p style="margin:0 0 8px;font-size:13px;color:#75716a;">Placed ' . ag_email_h(date('F j, Y g:i A')) . '</p>'
        . ($lines !== '' ? '<p style="margin:0 0 22px;font-size:14px;line-height:1.7;">' . $lines . '</p>' : '')
        . ag_email_button($site . '/admin/orders.php', 'Open orders');
    return ag_email_shell(
        'New AleppoGift order #' . (int)$order_id,
        'New order',
        'Order #' . (int)$order_id,
        $inner
    );
}

function ag_send_html_mail($to, $subject, $html, $pdf_path = null): bool
{
    $fromName = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'AleppoGift';
    $from = defined('EMAIL_FROM') ? EMAIL_FROM : 'sales@aleppogift.com';
    $encodedSubject = '=?UTF-8?B?' . base64_encode((string)$subject) . '?=';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= 'From: ' . $fromName . ' <' . $from . ">\r\n";
    $headers .= 'Reply-To: ' . $from . "\r\n";
    $headers .= "Cc: sales@aleppogift.com\r\n";
    $headers .= "X-Mailer: AleppoGift\r\n";

    if ($pdf_path && is_file($pdf_path)) {
        $boundary = 'ag_' . md5((string)microtime(true));
        $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . "\"\r\n";
        $body = '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";
        $filename = basename($pdf_path);
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Type: application/pdf; name="' . $filename . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= 'Content-Disposition: attachment; filename="' . $filename . "\"\r\n\r\n";
        $body .= chunk_split(base64_encode((string)file_get_contents($pdf_path))) . "\r\n";
        $body .= '--' . $boundary . '--';
    } else {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";
        $body = chunk_split(base64_encode($html));
    }

    return mail($to, $encodedSubject, $body, $headers);
}
