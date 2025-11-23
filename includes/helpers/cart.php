<?php

/**
 * Calculate cart total and weight
 * @param Database $db Database connection
 * @param array $cart Cart items array
 * @return array [total_price, total_weight]
 */
function getCartTotalAndWeight($db, $cart): array {
    $total = 0;
    $weight = 0;

    if (empty($cart)) {
        return [0, 0];
    }

    foreach ($cart as $item) {
        try {
            $product = $db->query(
                "SELECT price, weight FROM products WHERE id = :id AND status = 1", 
                ['id' => $item['product_id']]
            )->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                continue; // Skip if product not found or inactive
            }

            $price = $product['price'];
            $item_weight = $product['weight'] ?? 1;

            // Add variation price if applicable
            if (!empty($item['variation_id'])) {
                $variation = $db->query(
                    "SELECT additional_price FROM product_variations WHERE id = :id", 
                    ['id' => $item['variation_id']]
                )->fetch(PDO::FETCH_ASSOC);
                
                if ($variation) {
                    $price += $variation['additional_price'];
                }
            }

            $quantity = $item['quantity'];
            $total += $price * $quantity;
            $weight += $item_weight * $quantity;

        } catch (Exception $e) {
            error_log("Cart calculation error for item {$item['product_id']}: " . $e->getMessage());
            continue;
        }
    }

    return [$total, $weight];
}

/**
 * Get cart item count
 * @param array $cart Cart items array
 * @return int Total number of items
 */
function getCartItemCount($cart): int {
    if (empty($cart)) {
        return 0;
    }
    
    return array_sum(array_column($cart, 'quantity'));
}

/**
 * Check if product is in cart
 * @param array $cart Cart items array
 * @param int $product_id Product ID to check
 * @param int|null $variation_id Variation ID to check (optional)
 * @return bool|array Returns cart item if found, false otherwise
 */
function findCartItem($cart, $product_id, $variation_id = null) {
    foreach ($cart as $key => $item) {
        if ($item['product_id'] == $product_id && 
            ($item['variation_id'] ?? null) == $variation_id) {
            return ['key' => $key, 'item' => $item];
        }
    }
    return false;
}

/**
 * Validate cart items against database
 * @param Database $db Database connection
 * @param array $cart Cart items array
 * @return array [valid_items, invalid_items]
 */
function validateCartItems($db, $cart): array {
    $valid_items = [];
    $invalid_items = [];

    foreach ($cart as $key => $item) {
        try {
            $product = $db->query(
                "SELECT id, name_en, stock, status FROM products WHERE id = :id", 
                ['id' => $item['product_id']]
            )->fetch(PDO::FETCH_ASSOC);

            if (!$product || $product['status'] != 1) {
                $invalid_items[] = [
                    'key' => $key,
                    'item' => $item,
                    'reason' => 'Product not available'
                ];
                continue;
            }

            // Check stock
            if ($product['stock'] !== null && $product['stock'] < $item['quantity']) {
                $invalid_items[] = [
                    'key' => $key,
                    'item' => $item,
                    'reason' => "Insufficient stock. Available: {$product['stock']}"
                ];
                continue;
            }

            // Check variation if exists
            if (!empty($item['variation_id'])) {
                $variation = $db->query(
                    "SELECT id, stock FROM product_variations WHERE id = :id AND product_id = :product_id", 
                    ['id' => $item['variation_id'], 'product_id' => $item['product_id']]
                )->fetch(PDO::FETCH_ASSOC);

                if (!$variation) {
                    $invalid_items[] = [
                        'key' => $key,
                        'item' => $item,
                        'reason' => 'Product variation not available'
                    ];
                    continue;
                }

                if ($variation['stock'] !== null && $variation['stock'] < $item['quantity']) {
                    $invalid_items[] = [
                        'key' => $key,
                        'item' => $item,
                        'reason' => "Insufficient variation stock. Available: {$variation['stock']}"
                    ];
                    continue;
                }
            }

            $valid_items[] = ['key' => $key, 'item' => $item];

        } catch (Exception $e) {
            error_log("Cart validation error for item {$item['product_id']}: " . $e->getMessage());
            $invalid_items[] = [
                'key' => $key,
                'item' => $item,
                'reason' => 'Validation error'
            ];
        }
    }

    return [$valid_items, $invalid_items];
}
