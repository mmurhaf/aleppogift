<?php

function supplier_http_get(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'AleppoGiftImporter/1.0',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? $body : '';
    }
    $body = @file_get_contents($url);
    return is_string($body) ? $body : '';
}

function supplier_price_role(string $url, string $forced): string
{
    if ($forced === 'selling' || $forced === 'cost') {
        return $forced;
    }
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    foreach (['dubgift', 'krmeen', 'saleto'] as $known) {
        if (str_contains($host, $known)) {
            return 'selling';
        }
    }
    return 'cost';
}

function supplier_parse_products(string $html, string $pageUrl): array
{
    $found = [];
    if (preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/si', $html, $matches)) {
        foreach ($matches[1] as $json) {
            $data = json_decode(html_entity_decode($json), true);
            if (!is_array($data)) {
                continue;
            }
            $items = isset($data['@type']) ? [$data] : ($data['@graph'] ?? $data);
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (!is_array($item) || ($item['@type'] ?? '') !== 'Product') {
                    continue;
                }
                $offer = $item['offers'] ?? [];
                if (isset($offer[0])) {
                    $offer = $offer[0];
                }
                $found[] = [
                    'name' => (string)($item['name'] ?? ''),
                    'price' => (float)($offer['price'] ?? 0),
                    'currency' => (string)($offer['priceCurrency'] ?? 'AED'),
                    'image' => is_array($item['image'] ?? null) ? (string)($item['image'][0] ?? '') : (string)($item['image'] ?? ''),
                    'description' => (string)($item['description'] ?? ''),
                    'sku' => (string)($item['sku'] ?? ''),
                    'url' => $pageUrl,
                ];
            }
        }
    }
    return $found;
}

function supplier_next_page(string $html, string $pageUrl): ?string
{
    if (preg_match('/<a[^>]+rel="next"[^>]+href="([^"]+)"/i', $html, $match) || preg_match('/<a[^>]+href="([^"]+)"[^>]+rel="next"/i', $html, $match)) {
        return supplier_absolute_url($pageUrl, html_entity_decode($match[1]));
    }
    return null;
}

function supplier_absolute_url(string $base, string $href): string
{
    if (str_starts_with($href, 'http')) {
        return $href;
    }
    $parts = parse_url($base);
    $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
    if (str_starts_with($href, '/')) {
        return $origin . $href;
    }
    return rtrim($origin . ($parts['path'] ?? '/'), '/') . '/' . $href;
}

function supplier_lookup_id($db, string $table, string $name): ?int
{
    if ($name === '') {
        return null;
    }
    $row = $db->fetch("SELECT id FROM $table WHERE name_en = ? OR name_ar = ? LIMIT 1", [$name, $name]);
    return $row ? (int)$row['id'] : null;
}

function supplier_create_draft($db, array $row, int $batchId): array
{
    $name = trim((string)($row['product_name'] ?? $row['name'] ?? ''));
    $supplier = trim((string)($row['supplier'] ?? ''));
    $supplierSku = trim((string)($row['supplier_sku'] ?? $row['sku'] ?? ''));
    if ($name === '') {
        return ['status' => 'skipped', 'reason' => 'Missing product name'];
    }
    if ($supplier !== '' && $supplierSku !== '') {
        $dup = $db->fetch('SELECT id FROM products WHERE supplier_name = ? AND supplier_sku = ? LIMIT 1', [$supplier, $supplierSku]);
        if ($dup) {
            return ['status' => 'skipped', 'reason' => 'Same supplier SKU already exists', 'name' => $name];
        }
    }
    $currency = strtoupper(trim((string)($row['currency'] ?? 'AED')));
    $cost = convert_amount_to_aed((float)($row['supplier_cost'] ?? 0), $currency);
    $selling = trim((string)($row['selling_price'] ?? '')) !== ''
        ? convert_amount_to_aed((float)$row['selling_price'], 'AED')
        : round($cost * (1 + ((float)($row['markup'] ?? 80) / 100)), 2);
    if (($row['price_role'] ?? '') === 'selling' && $cost <= 0 && (float)($row['price'] ?? 0) > 0) {
        $selling = convert_amount_to_aed((float)$row['price'], $currency);
        $cost = 0;
    }
    if (($row['price_role'] ?? '') === 'cost' && (float)($row['price'] ?? 0) > 0 && $cost <= 0) {
        $cost = convert_amount_to_aed((float)$row['price'], $currency);
        $selling = round($cost * (1 + ((float)($row['markup'] ?? 80) / 100)), 2);
    }
    $categoryId = supplier_lookup_id($db, 'categories', (string)($row['category'] ?? ''));
    $brandId = supplier_lookup_id($db, 'brands', (string)($row['brand'] ?? ''));
    $sku = 'IMP-' . strtoupper(bin2hex(random_bytes(4)));
    $db->query(
        "INSERT INTO products (name_en, name_ar, description_en, price, sku, stock, status, listing_status, supplier_name, supplier_url, supplier_sku, availability, import_batch_id, import_notes, category_id, brand_id)
         VALUES (?, ?, ?, ?, ?, ?, 0, 'draft', ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $name,
            (string)($row['name_ar'] ?? $name),
            (string)($row['description'] ?? ''),
            $selling,
            $sku,
            (int)($row['stock'] ?? $row['availability'] ?? 0),
            $supplier,
            (string)($row['supplier_url'] ?? $row['url'] ?? ''),
            $supplierSku,
            (string)($row['availability'] ?? ''),
            $batchId,
            (string)($row['import_notes'] ?? ''),
            $categoryId,
            $brandId,
        ]
    );
    $productId = (int)$db->lastInsertId();
    $image = trim((string)($row['image_url'] ?? $row['image'] ?? ''));
    if ($image !== '') {
        $db->query('INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, 1)', [$productId, $image]);
    }
    return ['status' => 'created', 'name' => $name, 'id' => $productId];
}

function supplier_import_rows($db, array $rows, string $source, string $supplier, float $markup): array
{
    $db->query('INSERT INTO supplier_import_batches (filename, supplier, markup) VALUES (?, ?, ?)', [$source, $supplier, $markup]);
    $batchId = (int)$db->lastInsertId();
    $results = [];
    $created = 0;
    $skipped = 0;
    foreach ($rows as $row) {
        $row['markup'] = $markup;
        $row['supplier'] = $row['supplier'] ?? $supplier;
        $result = supplier_create_draft($db, $row, $batchId);
        $results[] = $result;
        if ($result['status'] === 'created') {
            $created++;
        } else {
            $skipped++;
        }
    }
    $db->query('UPDATE supplier_import_batches SET created_count = ?, skipped_count = ? WHERE id = ?', [$created, $skipped, $batchId]);
    return $results;
}
