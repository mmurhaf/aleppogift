<?php
/**
 * Idempotent schema for ADDITIONAL_FEATURES_PROMPT.md.
 * Usage: php database/apply_additional_features.php
 */

if (PHP_SAPI !== 'cli' && !defined('AG_MIGRATION_WEB')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

function ag_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function ag_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!ag_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "Added $table.$column\n";
    }
}

function apply_additional_features(): array
{
    $db = new Database();
    $pdo = $db->getPdo();
    $log = [];

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ag_add_column($pdo, 'orders', 'gift_recipient_name', 'VARCHAR(150) NULL');
    ag_add_column($pdo, 'orders', 'gift_recipient_phone', 'VARCHAR(40) NULL');
    ag_add_column($pdo, 'orders', 'gift_card_message', 'TEXT NULL');
    ag_add_column($pdo, 'orders', 'gift_wrap', 'TINYINT(1) NOT NULL DEFAULT 0');
    ag_add_column($pdo, 'orders', 'gift_wrap_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    ag_add_column($pdo, 'orders', 'hide_prices', 'TINYINT(1) NOT NULL DEFAULT 0');
    ag_add_column($pdo, 'orders', 'vat_rate', 'DECIMAL(6,2) NOT NULL DEFAULT 0');
    ag_add_column($pdo, 'orders', 'vat_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    ag_add_column($pdo, 'orders', 'trn', 'VARCHAR(50) NULL');
    ag_add_column($pdo, 'orders', 'same_day', 'TINYINT(1) NOT NULL DEFAULT 0');
    ag_add_column($pdo, 'orders', 'stock_reserved', 'TINYINT(1) NOT NULL DEFAULT 0');
    ag_add_column($pdo, 'orders', 'estimated_delivery', 'VARCHAR(120) NULL');
    ag_add_column($pdo, 'orders', 'confirmation_email_at', 'DATETIME NULL DEFAULT NULL');

    try {
        $pdo->exec("ALTER TABLE orders MODIFY payment_status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        $log[] = 'orders.payment_status is VARCHAR';
    } catch (Throwable $e) {
        $log[] = 'payment_status alter skipped: ' . $e->getMessage();
    }

    ag_add_column($pdo, 'customers', 'password_hash', 'VARCHAR(255) NULL');
    ag_add_column($pdo, 'customers', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    ag_add_column($pdo, 'customers', 'reset_token_hash', 'VARCHAR(255) NULL');
    ag_add_column($pdo, 'customers', 'reset_expires', 'DATETIME NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_addresses (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        label VARCHAR(80) NULL,
        fullname VARCHAR(150) NULL,
        phone VARCHAR(40) NULL,
        address_line1 VARCHAR(255) NULL,
        address_line2 VARCHAR(255) NULL,
        city VARCHAR(100) NULL,
        state VARCHAR(100) NULL,
        country VARCHAR(100) NULL,
        postal_code VARCHAR(30) NULL,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY customer_id (customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS wishlists (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY customer_product (customer_id, product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(40) NULL,
        subject VARCHAR(200) NULL,
        message TEXT NOT NULL,
        ip VARCHAR(64) NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        emailed TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY email_unique (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    ag_add_column($pdo, 'newsletter_subscribers', 'status', "VARCHAR(20) NOT NULL DEFAULT 'active'");
    ag_add_column($pdo, 'newsletter_subscribers', 'token', 'VARCHAR(64) NULL');
    ag_add_column($pdo, 'newsletter_subscribers', 'subscribed_at', 'DATETIME NULL');
    $pdo->exec("UPDATE newsletter_subscribers SET status = 'active' WHERE status IS NULL OR status = ''");
    $pdo->exec("UPDATE newsletter_subscribers SET subscribed_at = created_at WHERE subscribed_at IS NULL");
    $missingTokens = $pdo->query("SELECT id FROM newsletter_subscribers WHERE token IS NULL OR token = ''")->fetchAll(PDO::FETCH_COLUMN);
    $tokenStmt = $pdo->prepare('UPDATE newsletter_subscribers SET token = ? WHERE id = ?');
    foreach ($missingTokens as $subscriberId) {
        $tokenStmt->execute([bin2hex(random_bytes(16)), $subscriberId]);
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_campaigns (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(150) NOT NULL,
        subject VARCHAR(200) NOT NULL,
        html_body MEDIUMTEXT NOT NULL,
        send_at DATETIME NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        sent_at DATETIME NULL,
        sent_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ag_add_column($pdo, 'admin', 'name', 'VARCHAR(100) NULL');
    ag_add_column($pdo, 'admin', 'role', "VARCHAR(30) NOT NULL DEFAULT 'super_admin'");
    ag_add_column($pdo, 'admin', 'active', 'TINYINT(1) NOT NULL DEFAULT 1');
    $pdo->exec("UPDATE admin SET role = 'super_admin' WHERE role IS NULL OR role = ''");

    ag_add_column($pdo, 'products', 'supplier_name', 'VARCHAR(150) NULL');
    ag_add_column($pdo, 'products', 'supplier_url', 'VARCHAR(500) NULL');
    ag_add_column($pdo, 'products', 'supplier_sku', 'VARCHAR(80) NULL');
    ag_add_column($pdo, 'products', 'availability', 'VARCHAR(40) NULL');
    ag_add_column($pdo, 'products', 'listing_status', "VARCHAR(20) NOT NULL DEFAULT 'published'");
    ag_add_column($pdo, 'products', 'import_batch_id', 'INT NULL');
    ag_add_column($pdo, 'products', 'import_notes', 'TEXT NULL');
    $pdo->exec("UPDATE products SET listing_status = 'published' WHERE listing_status IS NULL OR listing_status = ''");

    $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_import_batches (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        filename VARCHAR(255) NULL,
        supplier VARCHAR(150) NULL,
        markup DECIMAL(6,2) NULL,
        created_count INT NOT NULL DEFAULT 0,
        skipped_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ag_add_column($pdo, 'customer_photos', 'privacy_blurred', 'TINYINT(1) NOT NULL DEFAULT 0');

    $pdo->exec(file_get_contents(__DIR__ . '/create_translations_table.sql'));

    $sharedCarts = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shared_carts'")->fetchColumn();
    if ((int)$sharedCarts > 0) {
        ag_add_column($pdo, 'shared_carts', 'status', "VARCHAR(20) NOT NULL DEFAULT 'active'");
        ag_add_column($pdo, 'shared_carts', 'expires_at', 'DATETIME NULL');
        ag_add_column($pdo, 'shared_carts', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');
    }

    $log[] = 'Schema is ready';
    return $log;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    foreach (apply_additional_features() as $line) {
        echo $line . PHP_EOL;
    }
}
