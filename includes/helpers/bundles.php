<?php

/**
 * Product bundle pricing.
 * Bundle lines keep the admin percent while removed catalog value is covered
 * by products added after that bundle. Extra quantity is always full price.
 */

require_once __DIR__ . '/../i18n.php';

function bundleLocName(array $row): string {
    if (function_exists('loc_field')) {
        return loc_field($row, 'name');
    }
    return (string)($row['name_en'] ?? '');
}

function ensureBundleSchema($db): void {
    static $ready = false;
    if ($ready) {
        return;
    }
    try {
        $hasBundles = (bool)$db->query("SHOW TABLES LIKE 'bundles'")->fetch(PDO::FETCH_NUM);
        $hasColumn = (bool)$db->query("SHOW COLUMNS FROM order_items LIKE 'bundle_id'")->fetch(PDO::FETCH_ASSOC);
        if ($hasBundles && $hasColumn) {
            $ready = true;
            return;
        }
    } catch (Throwable $e) {
        error_log('Bundle schema check failed: ' . $e->getMessage());
        return;
    }

    $statements = [
        "CREATE TABLE IF NOT EXISTS `bundles` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name_en` varchar(255) NOT NULL,
          `name_ar` varchar(255) NOT NULL,
          `description_en` mediumtext DEFAULT NULL,
          `description_ar` mediumtext DEFAULT NULL,
          `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
          `image_path` varchar(255) DEFAULT NULL,
          `status` tinyint(1) NOT NULL DEFAULT 1,
          `show_on_home` tinyint(1) NOT NULL DEFAULT 1,
          `display_order` int(11) NOT NULL DEFAULT 0,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_bundles_home` (`status`, `show_on_home`, `display_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `bundle_items` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `bundle_id` int(11) NOT NULL,
          `product_id` int(11) NOT NULL,
          `variation_id` int(11) DEFAULT NULL,
          `quantity` int(11) NOT NULL DEFAULT 1,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `variation_key` int(11) GENERATED ALWAYS AS (ifnull(`variation_id`, 0)) STORED,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_bundle_product_variation` (`bundle_id`, `product_id`, `variation_key`),
          KEY `idx_bundle_items_product` (`product_id`),
          KEY `idx_bundle_items_variation` (`variation_id`),
          CONSTRAINT `fk_bundle_items_bundle` FOREIGN KEY (`bundle_id`) REFERENCES `bundles` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `order_bundles` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `order_id` int(11) NOT NULL,
          `bundle_id` int(11) DEFAULT NULL,
          `bundle_name_en` varchar(255) NOT NULL,
          `bundle_name_ar` varchar(255) NOT NULL,
          `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
          `removed_value` decimal(10,2) NOT NULL DEFAULT 0.00,
          `replacement_value` decimal(10,2) NOT NULL DEFAULT 0.00,
          `discount_voided` tinyint(1) NOT NULL DEFAULT 0,
          `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_order_bundles_order` (`order_id`),
          KEY `idx_order_bundles_bundle` (`bundle_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE `order_items`
          ADD COLUMN `bundle_id` int(11) DEFAULT NULL AFTER `price`,
          ADD COLUMN `line_role` enum('standalone','bundle','extra','replacement') NOT NULL DEFAULT 'standalone' AFTER `bundle_id`,
          ADD COLUMN `catalog_price` decimal(10,2) DEFAULT NULL AFTER `line_role`,
          ADD COLUMN `bundle_discount_percent` decimal(5,2) DEFAULT NULL AFTER `catalog_price`",
        "ALTER TABLE `order_items` ADD KEY `idx_order_items_bundle` (`bundle_id`)",
    ];

    foreach ($statements as $statement) {
        try {
            $db->conn->exec($statement);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (!str_contains($message, 'Duplicate column')
                && !str_contains($message, 'Duplicate key name')
                && !str_contains($message, 'already exists')) {
                error_log('Bundle schema statement failed: ' . $message);
            }
        }
    }
    $ready = true;
}

function cartLineVariationId(array $item): int {
    return (int)($item['variation_id'] ?? 0);
}

function cartReservedQuantity(array $cart, int $productId, $variationId = null, $exceptKey = null): int {
    $variationId = (int)($variationId ?? 0);
    $sum = 0;
    foreach ($cart as $key => $item) {
        if ($exceptKey !== null && (string)$key === (string)$exceptKey) {
            continue;
        }
        if ((int)($item['product_id'] ?? 0) === $productId && cartLineVariationId($item) === $variationId) {
            $sum += (int)($item['quantity'] ?? 0);
        }
    }
    return $sum;
}

function bundleLineKey(int $productId, $variationId): string {
    return $productId . ':' . (int)($variationId ?? 0);
}

function refreshSessionCoupon(float $merchandiseTotal): float {
    $coupon = $_SESSION['applied_coupon'] ?? null;
    if (!$coupon) {
        return (float)($_SESSION['discount_amount'] ?? 0);
    }

    $type = $coupon['discount_type'] ?? 'fixed';
    $value = (float)($coupon['discount_value'] ?? 0);
    if ($type === 'percent') {
        $amount = round($merchandiseTotal * ($value / 100), 2);
    } else {
        $amount = round(min($value, $merchandiseTotal), 2);
    }

    $_SESSION['discount_amount'] = $amount;
    $_SESSION['applied_coupon']['discount'] = $amount;
    $_SESSION['applied_coupon']['itemTotal'] = $merchandiseTotal;
    return $amount;
}

/**
 * Assign replacement credit to bundles in the order they were added.
 * A credit line counts once, and only for bundles added at or before that credit.
 *
 * @param array $bundles Each item needs added_at and removed_value.
 * @param array $credits Each item needs added_at, remaining, and consumed.
 */
function allocateBundleCredits(array $bundles, array $credits): array {
    foreach ($bundles as &$bundle) {
        $removed = round((float)$bundle['removed_value'], 2);
        $available = 0.0;
        $eligible = [];
        foreach ($credits as $index => $credit) {
            if ($credit['remaining'] > 0 && (int)$credit['added_at'] >= (int)$bundle['added_at']) {
                $eligible[] = $index;
                $available += (float)$credit['remaining'];
            }
        }
        $available = round($available, 2);
        $qualifies = $removed <= 0 || $available + 0.009 >= $removed;
        $consume = $qualifies ? $removed : $available;
        $used = 0.0;

        foreach ($eligible as $index) {
            if ($consume <= 0) {
                break;
            }
            $take = min((float)$credits[$index]['remaining'], $consume);
            $credits[$index]['remaining'] = round((float)$credits[$index]['remaining'] - $take, 2);
            $credits[$index]['consumed'] = round((float)$credits[$index]['consumed'] + $take, 2);
            $consume = round($consume - $take, 2);
            $used = round($used + $take, 2);
        }

        $bundle['removed_value'] = $removed;
        $bundle['replacement_value'] = $used;
        $bundle['qualifies'] = $qualifies;
    }
    unset($bundle);

    return [$bundles, $credits];
}

function priceCart($db, array $cart): array {
    ensureBundleSchema($db);
    $empty = [
        'total' => 0.0,
        'weight' => 0.0,
        'catalog_total' => 0.0,
        'bundle_discount' => 0.0,
        'lines' => [],
        'bundles' => [],
        'checkout_rows' => [],
    ];
    if (empty($cart)) {
        return $empty;
    }

    $productIds = [];
    foreach ($cart as $item) {
        $productIds[(int)$item['product_id']] = true;
        foreach ($item['bundle_composition'] ?? [] as $part) {
            $productIds[(int)$part['product_id']] = true;
        }
    }

    $catalog = [];
    foreach (array_keys($productIds) as $productId) {
        if ($productId < 1) {
            continue;
        }
        try {
            $product = $db->query(
                "SELECT id, name_en, name_ar, price, weight, stock, status FROM products WHERE id = :id",
                ['id' => $productId]
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Bundle price lookup failed for product ' . $productId . ': ' . $e->getMessage());
            continue;
        }
        if (!$product) {
            continue;
        }
        $catalog[$productId] = [
            'product' => $product,
            'variations' => [],
        ];
        $variations = $db->query(
            "SELECT id, additional_price, stock, size, color FROM product_variations WHERE product_id = :id",
            ['id' => $productId]
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($variations as $variation) {
            $catalog[$productId]['variations'][(int)$variation['id']] = $variation;
        }
    }

    $unitPrice = function (int $productId, $variationId) use ($catalog): ?float {
        if (!isset($catalog[$productId])) {
            return null;
        }
        $price = (float)$catalog[$productId]['product']['price'];
        $variationId = (int)($variationId ?? 0);
        if ($variationId > 0) {
            if (!isset($catalog[$productId]['variations'][$variationId])) {
                return null;
            }
            $price += (float)$catalog[$productId]['variations'][$variationId]['additional_price'];
        }
        return $price;
    };

    $instances = [];
    $lineMeta = [];

    foreach ($cart as $key => $item) {
        $productId = (int)$item['product_id'];
        $variationId = cartLineVariationId($item);
        $quantity = max(0, (int)$item['quantity']);
        $role = $item['line_role'] ?? 'standalone';
        $unit = $unitPrice($productId, $variationId);
        $weightEach = (float)($catalog[$productId]['product']['weight'] ?? 1);
        $active = isset($catalog[$productId]) && (int)$catalog[$productId]['product']['status'] === 1 && $unit !== null;

        $lineMeta[$key] = [
            'key' => $key,
            'product_id' => $productId,
            'variation_id' => $variationId > 0 ? $variationId : null,
            'quantity' => $quantity,
            'line_role' => $role,
            'bundle_id' => isset($item['bundle_id']) ? (int)$item['bundle_id'] : null,
            'bundle_instance' => $item['bundle_instance'] ?? null,
            'required_quantity' => (int)($item['required_quantity'] ?? $quantity),
            'added_at' => (int)($item['added_at'] ?? 0),
            'catalog_unit' => $active ? $unit : 0.0,
            'weight' => $active ? $weightEach * $quantity : 0.0,
            'active' => $active,
            'catalog_line_total' => $active ? round($unit * $quantity, 2) : 0.0,
            'charged_unit' => $active ? $unit : 0.0,
            'line_total' => $active ? round($unit * $quantity, 2) : 0.0,
            'discount_percent' => null,
        ];

        if ($role !== 'bundle' || empty($item['bundle_instance'])) {
            continue;
        }

        $instanceId = (string)$item['bundle_instance'];
        if (!isset($instances[$instanceId])) {
            $instances[$instanceId] = [
                'bundle_instance' => $instanceId,
                'bundle_id' => (int)($item['bundle_id'] ?? 0),
                'added_at' => (int)($item['added_at'] ?? 0),
                'name_en' => (string)($item['bundle_name_en'] ?? t('cart.bundle_default_name')),
                'name_ar' => (string)($item['bundle_name_ar'] ?? ''),
                'discount_percent' => (float)($item['bundle_discount_percent'] ?? 0),
                'composition' => $item['bundle_composition'] ?? [],
                'line_keys' => [],
                'present' => [],
            ];
        }
        $instances[$instanceId]['line_keys'][] = $key;
        $instances[$instanceId]['added_at'] = min($instances[$instanceId]['added_at'], (int)($item['added_at'] ?? 0));
        $instances[$instanceId]['present'][bundleLineKey($productId, $variationId)] = $key;
        if (!empty($item['bundle_composition'])) {
            $instances[$instanceId]['composition'] = $item['bundle_composition'];
        }
    }

    $bundleIds = [];
    foreach ($instances as $instance) {
        if ($instance['bundle_id'] > 0) {
            $bundleIds[$instance['bundle_id']] = true;
        }
    }
    $bundleRows = [];
    if ($bundleIds) {
        try {
            $placeholders = implode(',', array_fill(0, count($bundleIds), '?'));
            $rows = $db->query(
                "SELECT id, name_en, name_ar, discount_percent, status FROM bundles WHERE id IN ($placeholders)",
                array_keys($bundleIds)
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $bundleRows[(int)$row['id']] = $row;
            }
        } catch (Exception $e) {
            error_log('Bundle lookup failed: ' . $e->getMessage());
        }
    }

    $credits = [];
    foreach ($instances as $instanceId => &$instance) {
        $bundleRow = $bundleRows[$instance['bundle_id']] ?? null;
        if ($bundleRow && (int)$bundleRow['status'] === 1) {
            $instance['name_en'] = $bundleRow['name_en'];
            $instance['name_ar'] = $bundleRow['name_ar'];
            $instance['discount_percent'] = (float)$bundleRow['discount_percent'];
            $instance['offer_open'] = true;
        } else {
            $instance['offer_open'] = $bundleRow === null ? false : false;
            if ($bundleRow) {
                $instance['name_en'] = $bundleRow['name_en'];
                $instance['name_ar'] = $bundleRow['name_ar'];
                $instance['discount_percent'] = (float)$bundleRow['discount_percent'];
            }
        }

        $removed = 0.0;
        $eligible = 0.0;
        $composition = $instance['composition'];
        if (!$composition) {
            foreach ($instance['line_keys'] as $key) {
                $line = $lineMeta[$key];
                $composition[] = [
                    'product_id' => $line['product_id'],
                    'variation_id' => $line['variation_id'],
                    'required_quantity' => $line['required_quantity'],
                ];
            }
        }

        $seen = [];
        foreach ($composition as $part) {
            $productId = (int)$part['product_id'];
            $variationId = (int)($part['variation_id'] ?? 0);
            $required = max(0, (int)$part['required_quantity']);
            $partKey = bundleLineKey($productId, $variationId);
            $seen[$partKey] = true;
            $unit = $unitPrice($productId, $variationId) ?? 0.0;
            $current = 0;
            if (isset($instance['present'][$partKey]) && !empty($lineMeta[$instance['present'][$partKey]]['active'])) {
                $current = (int)$lineMeta[$instance['present'][$partKey]]['quantity'];
            }
            $removed += max(0, $required - $current) * $unit;
            $eligible += min($current, $required) * $unit;

            if ($current > $required && isset($instance['present'][$partKey])) {
                $line = $lineMeta[$instance['present'][$partKey]];
                $credits[] = [
                    'added_at' => $line['added_at'],
                    'remaining' => round(($current - $required) * $unit, 2),
                    'consumed' => 0.0,
                    'source_key' => $instance['present'][$partKey],
                ];
            }
        }

        foreach ($instance['line_keys'] as $key) {
            $line = $lineMeta[$key];
            $partKey = bundleLineKey($line['product_id'], $line['variation_id']);
            if (isset($seen[$partKey])) {
                continue;
            }
            $required = (int)$line['required_quantity'];
            $unit = (float)$line['catalog_unit'];
            $removed += max(0, $required - $line['quantity']) * $unit;
            $eligible += min($line['quantity'], $required) * $unit;
        }

        $instance['removed_value'] = round($removed, 2);
        $instance['eligible_catalog'] = round($eligible, 2);
        $instance['replacement_value'] = 0.0;
        $instance['qualifies'] = false;
    }
    unset($instance);

    foreach ($lineMeta as $key => $line) {
        if (!$line['active'] || $line['line_role'] === 'bundle') {
            continue;
        }
        $credits[] = [
            'added_at' => $line['added_at'],
            'remaining' => round((float)$line['catalog_line_total'], 2),
            'consumed' => 0.0,
            'source_key' => $key,
        ];
    }

    uasort($instances, function ($a, $b) {
        return $a['added_at'] <=> $b['added_at'];
    });

    $openBundles = [];
    $closedBundles = [];
    foreach ($instances as $instanceId => $instance) {
        if (!empty($bundleRows[$instance['bundle_id']]) && (int)$bundleRows[$instance['bundle_id']]['status'] === 1) {
            $openBundles[$instanceId] = $instance;
        } else {
            $instance['qualifies'] = false;
            $instance['discount_amount'] = 0.0;
            $instance['discount_voided'] = 1;
            $instance['message'] = t('cart.bundle_closed');
            $closedBundles[$instanceId] = $instance;
        }
    }

    [$openBundles, $credits] = allocateBundleCredits(array_values($openBundles), $credits);
    $openById = [];
    foreach ($openBundles as $instance) {
        $percent = max(0, min(100, (float)$instance['discount_percent']));
        $discount = 0.0;
        if (!empty($instance['qualifies'])) {
            $discount = round($instance['eligible_catalog'] * ($percent / 100), 2);
        }
        $instance['discount_percent'] = $percent;
        $instance['discount_amount'] = $discount;
        $instance['discount_voided'] = empty($instance['qualifies']) ? 1 : 0;
        if (!empty($instance['qualifies'])) {
            $shown = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
            $instance['message'] = t('cart.bundle_applied', ['percent' => $shown]);
        } else {
            $gap = max(0, round($instance['removed_value'] - $instance['replacement_value'], 2));
            $instance['message'] = t('cart.bundle_removed', ['amount' => bundleMoney($gap)]);
        }
        $openById[$instance['bundle_instance']] = $instance;
    }

    $bundles = $openById + $closedBundles;
    uasort($bundles, function ($a, $b) {
        return $a['added_at'] <=> $b['added_at'];
    });

    $consumedByLine = [];
    foreach ($credits as $credit) {
        $source = $credit['source_key'];
        $consumedByLine[$source] = ($consumedByLine[$source] ?? 0) + (float)$credit['consumed'];
    }

    $total = 0.0;
    $weight = 0.0;
    $catalogTotal = 0.0;
    $bundleDiscount = 0.0;
    $checkoutRows = [];

    foreach ($bundles as &$bundle) {
        $remainingDiscount = (float)$bundle['discount_amount'];
        $eligibleLeft = (float)$bundle['eligible_catalog'];
        $lastDiscountKey = null;
        foreach ($bundle['line_keys'] as $key) {
            if ($lineMeta[$key]['active'] && min($lineMeta[$key]['quantity'], $lineMeta[$key]['required_quantity']) > 0) {
                $lastDiscountKey = $key;
            }
        }

        foreach ($bundle['line_keys'] as $key) {
            $line = &$lineMeta[$key];
            if (!$line['active']) {
                continue;
            }
            $unit = (float)$line['catalog_unit'];
            $qty = (int)$line['quantity'];
            $required = (int)$line['required_quantity'];
            $discountedQty = min($qty, $required);
            $fullQty = max(0, $qty - $required);
            $lineEligible = round($discountedQty * $unit, 2);
            $lineDiscount = 0.0;
            if (!empty($bundle['qualifies']) && $lineEligible > 0 && $eligibleLeft > 0) {
                if ($key === $lastDiscountKey) {
                    $lineDiscount = round($remainingDiscount, 2);
                } else {
                    $lineDiscount = round($bundle['discount_amount'] * ($lineEligible / $bundle['eligible_catalog']), 2);
                    $remainingDiscount = round($remainingDiscount - $lineDiscount, 2);
                    $eligibleLeft = round($eligibleLeft - $lineEligible, 2);
                }
            }
            $lineTotal = round($lineEligible + ($fullQty * $unit) - $lineDiscount, 2);
            $chargedUnit = $qty > 0 ? round($lineTotal / $qty, 2) : $unit;
            $line['charged_unit'] = $chargedUnit;
            $line['line_total'] = round($chargedUnit * $qty, 2);
            $line['discount_percent'] = ($line['line_total'] + 0.001) < (float)$line['catalog_line_total']
                ? (float)$bundle['discount_percent']
                : null;
            $line['bundle_name_en'] = $bundle['name_en'];
            unset($line);
        }
        $chargedForBundle = 0.0;
        $catalogForBundle = 0.0;
        foreach ($bundle['line_keys'] as $key) {
            if (empty($lineMeta[$key]['active'])) {
                continue;
            }
            $chargedForBundle += (float)$lineMeta[$key]['line_total'];
            $catalogForBundle += (float)$lineMeta[$key]['catalog_line_total'];
        }
        $bundle['discount_amount'] = round(max(0, $catalogForBundle - $chargedForBundle), 2);
        $bundleDiscount += (float)$bundle['discount_amount'];
        unset($bundle);
    }
    unset($bundle);

    foreach ($lineMeta as $key => $line) {
        if (!$line['active']) {
            continue;
        }
        $role = $line['line_role'];
        if ($role !== 'bundle' && ($consumedByLine[$key] ?? 0) > 0) {
            $role = 'replacement';
            $lineMeta[$key]['line_role'] = 'replacement';
        }
        $total += (float)$line['line_total'];
        $weight += (float)$line['weight'];
        $catalogTotal += (float)$line['catalog_line_total'];

        $bundleId = $role === 'standalone' ? null : $line['bundle_id'];
        if ($role === 'extra' || $role === 'replacement') {
            $bundleId = $line['bundle_id'] ?: null;
        }
        $checkoutRows[] = [
            'product_id' => $line['product_id'],
            'variation_id' => $line['variation_id'],
            'quantity' => $line['quantity'],
            'price' => $line['quantity'] > 0 ? round($line['line_total'] / $line['quantity'], 2) : 0,
            'line_total' => $line['line_total'],
            'bundle_id' => $bundleId,
            'line_role' => $role,
            'catalog_price' => $line['catalog_unit'],
            'bundle_discount_percent' => $line['discount_percent'],
        ];
    }

    return [
        'total' => round($total, 2),
        'weight' => round($weight, 2),
        'catalog_total' => round($catalogTotal, 2),
        'bundle_discount' => round($bundleDiscount, 2),
        'lines' => $lineMeta,
        'bundles' => array_values($bundles),
        'checkout_rows' => $checkoutRows,
    ];
}

function bundleMoney(float $amount): string {
    if (function_exists('format_store_money')) {
        return format_store_money($amount);
    }
    return number_format($amount, 2) . ' AED';
}

function loadHomeBundles($db): array {
    ensureBundleSchema($db);
    try {
        $bundles = $db->query(
            "SELECT b.*,
                    (SELECT COUNT(*) FROM bundle_items bi WHERE bi.bundle_id = b.id) AS item_count
             FROM bundles b
             WHERE b.status = 1 AND b.show_on_home = 1
             ORDER BY b.display_order ASC, b.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Home bundles unavailable: ' . $e->getMessage());
        return [];
    }

    $visible = [];
    foreach ($bundles as $bundle) {
        if ((int)$bundle['item_count'] < 2) {
            continue;
        }
        $items = $db->query(
            "SELECT bi.product_id, bi.variation_id, bi.quantity,
                    p.name_en, p.name_ar, p.price, p.stock, p.status,
                    pv.additional_price, pv.stock AS variation_stock, pv.size, pv.color,
                    (SELECT image_path FROM product_images
                     WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS image_path
             FROM bundle_items bi
             JOIN products p ON p.id = bi.product_id
             LEFT JOIN product_variations pv ON pv.id = bi.variation_id AND pv.product_id = p.id
             WHERE bi.bundle_id = :id
             ORDER BY bi.id ASC",
            ['id' => (int)$bundle['id']]
        )->fetchAll(PDO::FETCH_ASSOC);

        $catalog = 0.0;
        $unavailable = [];
        foreach ($items as &$item) {
            $unit = (float)$item['price'];
            if (!empty($item['variation_id'])) {
                $unit += (float)($item['additional_price'] ?? 0);
            }
            $item['unit_price'] = $unit;
            $item['line_total'] = round($unit * (int)$item['quantity'], 2);
            $catalog += $item['line_total'];

            $reason = null;
            $itemName = bundleLocName($item);
            if ((int)$item['status'] !== 1) {
                $reason = t('cart.bundle_item_unavailable', ['name' => $itemName]);
            } elseif (!empty($item['variation_id']) && $item['additional_price'] === null && $item['variation_stock'] === null && $item['size'] === null && $item['color'] === null) {
                $reason = t('cart.bundle_option_unavailable', ['name' => $itemName]);
            } elseif (!empty($item['variation_id']) && $item['variation_stock'] !== null && (int)$item['variation_stock'] < (int)$item['quantity']) {
                $reason = t('cart.bundle_item_low_stock', ['name' => $itemName]);
            } elseif (empty($item['variation_id']) && $item['stock'] !== null && (int)$item['stock'] < (int)$item['quantity']) {
                $reason = t('cart.bundle_item_low_stock', ['name' => $itemName]);
            }
            if ($reason) {
                $unavailable[] = $reason;
            }
        }
        unset($item);

        $percent = (float)$bundle['discount_percent'];
        $bundle['items'] = $items;
        $bundle['catalog_total'] = round($catalog, 2);
        $bundle['discounted_total'] = round($catalog * (1 - ($percent / 100)), 2);
        $bundle['unavailable'] = $unavailable;
        $visible[] = $bundle;
    }

    return $visible;
}

function addBundleToSessionCart($db, array &$cart, int $bundleId): array {
    ensureBundleSchema($db);
    $bundle = $db->query(
        "SELECT * FROM bundles WHERE id = :id AND status = 1",
        ['id' => $bundleId]
    )->fetch(PDO::FETCH_ASSOC);
    if (!$bundle) {
        return ['success' => false, 'message' => t('cart.bundle_not_available')];
    }

    $items = $db->query(
        "SELECT bi.product_id, bi.variation_id, bi.quantity,
                p.name_en, p.name_ar, p.price, p.stock, p.status,
                pv.additional_price, pv.stock AS variation_stock
         FROM bundle_items bi
         JOIN products p ON p.id = bi.product_id
         LEFT JOIN product_variations pv ON pv.id = bi.variation_id AND pv.product_id = p.id
         WHERE bi.bundle_id = :id
         ORDER BY bi.id ASC",
        ['id' => $bundleId]
    )->fetchAll(PDO::FETCH_ASSOC);

    if (count($items) < 1) {
        return ['success' => false, 'message' => t('cart.bundle_no_products')];
    }

    foreach ($items as $item) {
        if ((int)$item['status'] !== 1) {
            return ['success' => false, 'message' => t('cart.product_not_available', ['name' => bundleLocName($item)])];
        }
        if (!empty($item['variation_id']) && $item['variation_stock'] === null && $item['additional_price'] === null) {
            return ['success' => false, 'message' => t('cart.option_not_available', ['name' => bundleLocName($item)])];
        }
        $needed = cartReservedQuantity($cart, (int)$item['product_id'], $item['variation_id']) + (int)$item['quantity'];
        $stock = !empty($item['variation_id']) ? $item['variation_stock'] : $item['stock'];
        if ($stock !== null && $needed > (int)$stock) {
            return ['success' => false, 'message' => t('cart.bundle_stock', ['name' => bundleLocName($item), 'stock' => (int)$stock])];
        }
    }

    $existingKey = null;
    foreach ($cart as $key => $line) {
        if (($line['line_role'] ?? '') === 'bundle' && (int)($line['bundle_id'] ?? 0) === $bundleId) {
            $existingKey = $key;
            break;
        }
    }

    if ($existingKey !== null) {
        $instance = (string)$cart[$existingKey]['bundle_instance'];
        $composition = $cart[$existingKey]['bundle_composition'] ?? [];
    } else {
        $instance = bin2hex(random_bytes(8));
        $composition = [];
    }

    $requiredByKey = [];
    foreach ($composition as $part) {
        $requiredByKey[bundleLineKey((int)$part['product_id'], $part['variation_id'] ?? 0)] = (int)$part['required_quantity'];
    }

    $now = time();
    foreach ($items as $item) {
        $partKey = bundleLineKey((int)$item['product_id'], $item['variation_id']);
        $newRequired = ($requiredByKey[$partKey] ?? 0) + (int)$item['quantity'];
        $requiredByKey[$partKey] = $newRequired;

        $lineKey = null;
        foreach ($cart as $key => $line) {
            if (($line['bundle_instance'] ?? '') === $instance
                && (int)$line['product_id'] === (int)$item['product_id']
                && cartLineVariationId($line) === (int)($item['variation_id'] ?? 0)
                && ($line['line_role'] ?? '') === 'bundle') {
                $lineKey = $key;
                break;
            }
        }

        if ($lineKey !== null) {
            $cart[$lineKey]['quantity'] += (int)$item['quantity'];
            $cart[$lineKey]['required_quantity'] = $newRequired;
        } else {
            $cart[] = [
                'product_id' => (int)$item['product_id'],
                'variation_id' => !empty($item['variation_id']) ? (int)$item['variation_id'] : null,
                'quantity' => (int)$item['quantity'],
                'added_at' => $now,
                'line_role' => 'bundle',
                'bundle_id' => $bundleId,
                'bundle_instance' => $instance,
                'required_quantity' => $newRequired,
                'bundle_name_en' => $bundle['name_en'],
                'bundle_name_ar' => $bundle['name_ar'],
                'bundle_discount_percent' => (float)$bundle['discount_percent'],
                'bundle_composition' => [],
            ];
        }
    }

    $composition = [];
    foreach ($requiredByKey as $partKey => $required) {
        [$productId, $variationId] = array_map('intval', explode(':', $partKey));
        $composition[] = [
            'product_id' => $productId,
            'variation_id' => $variationId > 0 ? $variationId : null,
            'required_quantity' => $required,
        ];
    }

    foreach ($cart as &$line) {
        if (($line['bundle_instance'] ?? '') === $instance && ($line['line_role'] ?? '') === 'bundle') {
            $line['bundle_composition'] = $composition;
            $line['bundle_name_en'] = $bundle['name_en'];
            $line['bundle_name_ar'] = $bundle['name_ar'];
            $line['bundle_discount_percent'] = (float)$bundle['discount_percent'];
            $line['bundle_id'] = $bundleId;
        }
    }
    unset($line);

    return [
        'success' => true,
        'message' => t('cart.bundle_added', ['name' => bundleLocName($bundle)]),
        'bundle_name' => bundleLocName($bundle),
    ];
}
