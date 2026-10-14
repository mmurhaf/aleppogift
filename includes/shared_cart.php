<?php
/**
 * Shared cart helpers.
 *
 * A visitor who opens a shared-cart link gets $_SESSION['shared_cart_token'].
 * Any request that changes $_SESSION['cart'] while that token is present is
 * written back to the shared_carts row, so the admin sees the customer's edits
 * no matter which endpoint (cart page, mini-cart, product page, bundles…)
 * made the change.
 *
 * Usage (after session_start()):
 *   require_once __DIR__ . '/shared_cart.php';
 *   sharedCartWatch();
 */

if (!function_exists('sharedCartEnsureTable')) {
    /**
     * Restores the primary key and AUTO_INCREMENT when an import created
     * shared_carts without them. Inserts otherwise all land on id 0.
     */
    function sharedCartEnsureTable($db): void {
        $id = $db->query("SHOW COLUMNS FROM shared_carts LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        if ($id && stripos((string)($id['Extra'] ?? ''), 'auto_increment') !== false) {
            return;
        }

        $rows = $db->query('SELECT id, token FROM shared_carts ORDER BY created_at, token')->fetchAll(PDO::FETCH_ASSOC);
        $next = 1;
        foreach ($rows as $row) {
            $current = (int)$row['id'];
            if ($current > 0) {
                $next = max($next, $current + 1);
                continue;
            }
            $db->query(
                'UPDATE shared_carts SET id = :id WHERE token = :token',
                ['id' => $next, 'token' => $row['token']]
            );
            $next++;
        }

        $names = [];
        foreach ($db->query('SHOW INDEX FROM shared_carts')->fetchAll(PDO::FETCH_ASSOC) as $index) {
            $names[$index['Key_name']] = true;
        }
        if (!isset($names['PRIMARY'])) {
            $db->query('ALTER TABLE shared_carts ADD PRIMARY KEY (id)');
        }
        if (!isset($names['uq_token'])) {
            $db->query('ALTER TABLE shared_carts ADD UNIQUE KEY uq_token (token)');
        }
        if (!isset($names['idx_status'])) {
            $db->query('ALTER TABLE shared_carts ADD KEY idx_status (status)');
        }
        if (!isset($names['idx_expires'])) {
            $db->query('ALTER TABLE shared_carts ADD KEY idx_expires (expires_at)');
        }
        $db->query('ALTER TABLE shared_carts MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');
    }
}

if (!function_exists('sharedCartDb')) {
    function sharedCartDb() {
        require_once __DIR__ . '/../config/config.php';
        require_once __DIR__ . '/Database.php';
        return new Database();
    }
}

if (!function_exists('sharedCartSanitizeItems')) {
    /**
     * Keep only well-formed cart lines (positive product id and quantity).
     */
    function sharedCartSanitizeItems($items): array {
        if (!is_array($items)) {
            return [];
        }
        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productId = (int)($item['product_id'] ?? 0);
            $quantity  = (int)($item['quantity'] ?? 0);
            if ($productId < 1 || $quantity < 1) {
                continue;
            }
            $item['product_id']   = $productId;
            $item['quantity']     = $quantity;
            $item['variation_id'] = !empty($item['variation_id']) ? (int)$item['variation_id'] : null;
            $clean[] = $item;
        }
        return $clean;
    }
}

if (!function_exists('sharedCartIsOpen')) {
    /**
     * True when the shared cart row can still be edited (active and not past expiry).
     */
    function sharedCartIsOpen(array $row): bool {
        if (($row['status'] ?? '') !== 'active') {
            return false;
        }
        return empty($row['expires_at']) || strtotime($row['expires_at']) >= time();
    }
}

if (!function_exists('sharedCartSync')) {
    /**
     * Persist the current session cart to the shared cart linked to this session.
     * Detaches the session from the link if the link is gone, expired or already used.
     */
    function sharedCartSync(): array {
        $token = $_SESSION['shared_cart_token'] ?? '';
        if ($token === '') {
            return ['success' => false, 'message' => 'No shared cart session.'];
        }

        try {
            $db  = sharedCartDb();
            $row = $db->query(
                "SELECT id, status, expires_at FROM shared_carts WHERE token = :token LIMIT 1",
                ['token' => $token]
            )->fetch(PDO::FETCH_ASSOC);

            if (!$row || !sharedCartIsOpen($row)) {
                if ($row && $row['status'] === 'active') {
                    $db->query("UPDATE shared_carts SET status = 'expired' WHERE id = :id", ['id' => (int)$row['id']]);
                }
                unset($_SESSION['shared_cart_token']);
                return ['success' => false, 'message' => 'This cart link is no longer active.'];
            }

            $db->query(
                "UPDATE shared_carts SET cart_data = :cart_data, updated_at = NOW() WHERE id = :id",
                [
                    'cart_data' => json_encode(sharedCartSanitizeItems($_SESSION['cart'] ?? []), JSON_UNESCAPED_UNICODE),
                    'id'        => (int)$row['id'],
                ]
            );
            return ['success' => true, 'message' => 'Cart updated.'];
        } catch (Throwable $e) {
            error_log('Shared cart sync failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error. Please try again.'];
        }
    }
}

if (!function_exists('sharedCartMarkUsed')) {
    /**
     * Call when an order is placed from a shared cart, before the session cart is cleared.
     */
    function sharedCartMarkUsed(): void {
        $token = $_SESSION['shared_cart_token'] ?? '';
        if ($token === '') {
            return;
        }
        try {
            sharedCartDb()->query(
                "UPDATE shared_carts SET status = 'used', updated_at = NOW() WHERE token = :token AND status = 'active'",
                ['token' => $token]
            );
        } catch (Throwable $e) {
            error_log('Shared cart mark-used failed: ' . $e->getMessage());
        }
        unset($_SESSION['shared_cart_token']);
    }
}

if (!function_exists('sharedCartWatch')) {
    /**
     * Snapshot the cart now and sync it at the end of the request if it changed.
     */
    function sharedCartWatch(): void {
        static $registered = false;
        if ($registered || empty($_SESSION['shared_cart_token'])) {
            return;
        }
        $registered = true;
        $before = json_encode(array_values($_SESSION['cart'] ?? []));

        register_shutdown_function(function () use ($before) {
            if (empty($_SESSION['shared_cart_token'])) {
                return;
            }
            if (json_encode(array_values($_SESSION['cart'] ?? [])) !== $before) {
                sharedCartSync();
            }
        });
    }
}
