<?php
// File: shared_cart_create.php
// Creates the shared_carts table required by the Shared Cart feature.
// Run once by opening this URL in your browser (while logged in locally)
// then delete or move it out of public_html.

require_once(__DIR__ . '/config/config.php');
require_once(__DIR__ . '/includes/Database.php');

try {
    $db = new Database();

    echo "Checking for shared_carts table...\n";

    // Check if the table already exists
    $tables = $db->query("SHOW TABLES LIKE 'shared_carts'")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($tables)) {
        echo "- Table shared_carts already exists. No changes made.\n";
    } else {
        $db->query("
            CREATE TABLE `shared_carts` (
                `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
                `token`          VARCHAR(64)      NOT NULL,
                `cart_data`      JSON             NOT NULL,
                `customer_name`  VARCHAR(255)     DEFAULT NULL,
                `customer_phone` VARCHAR(50)      DEFAULT NULL,
                `note`           TEXT             DEFAULT NULL,
                `status`         ENUM('active','used','expired') NOT NULL DEFAULT 'active',
                `expires_at`     DATETIME         DEFAULT NULL,
                `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_token`  (`token`),
                KEY `idx_status`       (`status`),
                KEY `idx_expires`      (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✓ Table shared_carts created successfully.\n";
    }

    echo "Done.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
