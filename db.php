<?php
// db.php - Database Connection with auto-bootstrap if DB is missing
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "onlineshopping";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Error code 1049: Unknown database
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        // Connect without specifying DB and create the database, then import schema
        $bootstrap = new mysqli($servername, $username, $password);
        $bootstrap->set_charset('utf8mb4');
        $bootstrap->query("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Import schema from the latest SQL dump if present
        $schemaCandidates = [
            __DIR__ . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'onlineshopping.sql',
            __DIR__ . DIRECTORY_SEPARATOR . 'query.sql',
        ];

        $schemaPath = null;

        foreach ($schemaCandidates as $candidate) {
            if (file_exists($candidate)) {
                $schemaPath = $candidate;
                break;
            }
        }

        if ($schemaPath !== null) {
            $sql = file_get_contents($schemaPath);
            // query.sql may contain CREATE DATABASE and USE statements; run as multi_query on server connection
            $bootstrap->multi_query($sql);
            // flush remaining results
            while ($bootstrap->more_results() && $bootstrap->next_result()) { /* flush */ }
        }
        $bootstrap->close();

        // Reconnect to the newly created DB
        $conn = new mysqli($servername, $username, $password, $dbname);
        $conn->set_charset('utf8mb4');
    } else {
        throw $e;
    }
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    if (!$result) {
        return false;
    }

    $exists = $result->num_rows > 0;
    $result->close();
    return $exists;
}

function ensure_payment_schema(mysqli $conn): void
{
    $requiredColumns = [
        'payment_id' => "ALTER TABLE `payments` ADD COLUMN `payment_id` varchar(100) DEFAULT NULL AFTER `method`",
        'trx_id' => "ALTER TABLE `payments` ADD COLUMN `trx_id` varchar(100) DEFAULT NULL AFTER `transaction_id`",
        'customer_name' => "ALTER TABLE `payments` ADD COLUMN `customer_name` varchar(191) DEFAULT NULL AFTER `amount`",
        'customer_phone' => "ALTER TABLE `payments` ADD COLUMN `customer_phone` varchar(50) DEFAULT NULL AFTER `customer_name`",
        'customer_address' => "ALTER TABLE `payments` ADD COLUMN `customer_address` varchar(255) DEFAULT NULL AFTER `customer_phone`",
        'gateway_response' => "ALTER TABLE `payments` ADD COLUMN `gateway_response` longtext DEFAULT NULL AFTER `customer_address`",
        'updated_at' => "ALTER TABLE `payments` ADD COLUMN `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`",
    ];

    $result = $conn->query("SHOW COLUMNS FROM `payments`");
    if (!$result) {
        return;
    }

    $existing = [];
    while ($row = $result->fetch_assoc()) {
        $existing[$row['Field']] = true;
    }
    $result->close();

    foreach ($requiredColumns as $column => $sql) {
        if (!isset($existing[$column])) {
            $conn->query($sql);
        }
    }

    if (!column_exists($conn, 'orders', 'payment_status')) {
        $conn->query("ALTER TABLE `orders` ADD COLUMN `payment_status` varchar(20) NOT NULL DEFAULT 'pending' AFTER `payment_method`");
    }

    $conn->query("UPDATE `orders` SET `payment_status` = 'pending' WHERE `payment_status` IS NULL OR `payment_status` = ''");
    $conn->query("UPDATE `payments` SET `status` = 'success' WHERE `status` = 'paid'");
    $conn->query("UPDATE `orders` SET `payment_status` = 'success' WHERE LOWER(`status`) = 'paid'");
    $conn->query("UPDATE `orders` SET `payment_status` = 'failed' WHERE LOWER(`status`) = 'cancelled' AND `payment_method` IN ('bkash', 'online')");

    // Expand legacy enums so both old bKash rows and new online rows can coexist safely.
    $conn->query("ALTER TABLE `orders` MODIFY `payment_method` ENUM('cod','bkash','online') NOT NULL");
    $conn->query("ALTER TABLE `payments` MODIFY `method` ENUM('cod','bkash','online') NOT NULL");
    $conn->query("ALTER TABLE `payments` MODIFY `status` ENUM('pending','success','failed','paid') DEFAULT 'pending'");

    // Keep historical bKash rows readable, but move labels to the new online method.
    $conn->query("UPDATE `orders` SET `payment_method` = 'online' WHERE `payment_method` = 'bkash'");
    $conn->query("UPDATE `payments` SET `method` = 'online' WHERE `method` = 'bkash'");
    $conn->query("ALTER TABLE `orders` MODIFY `payment_method` ENUM('cod','online') NOT NULL");
    $conn->query("ALTER TABLE `payments` MODIFY `method` ENUM('cod','online') NOT NULL");
    $conn->query("ALTER TABLE `payments` MODIFY `status` ENUM('pending','success','failed') DEFAULT 'pending'");

    $conn->query("
        UPDATE `orders` o
        LEFT JOIN `payments` p ON p.order_id = o.id
        SET o.payment_status = COALESCE(p.status, o.payment_status, 'pending')
    ");

    $indexResult = $conn->query("SHOW INDEX FROM `payments`");
    if ($indexResult) {
        $indexes = [];
        while ($row = $indexResult->fetch_assoc()) {
            $indexes[$row['Key_name']] = true;
        }
        $indexResult->close();

        if (!isset($indexes['payment_id'])) {
            $conn->query("ALTER TABLE `payments` ADD KEY `payment_id` (`payment_id`)");
        }

        if (!isset($indexes['trx_id'])) {
            $conn->query("ALTER TABLE `payments` ADD KEY `trx_id` (`trx_id`)");
        }
    }
}

function ensure_product_stock_schema(mysqli $conn): void
{
    // Inventory management: guarantee a stock column exists on products.
    if (!column_exists($conn, 'products', 'stock')) {
        $conn->query("ALTER TABLE `products` ADD COLUMN `stock` int(11) NOT NULL DEFAULT 0");
    }
}

ensure_payment_schema($conn);
ensure_product_stock_schema($conn);
?>
