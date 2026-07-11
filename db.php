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

        // Import schema from query.sql if present
        $schemaPath = __DIR__ . DIRECTORY_SEPARATOR . 'query.sql';
        if (file_exists($schemaPath)) {
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
?>