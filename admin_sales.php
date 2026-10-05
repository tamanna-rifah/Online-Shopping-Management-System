<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/db.php';

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$hasSellerId = false;

$columnResult = $conn->query("SHOW COLUMNS FROM products LIKE 'seller_id'");
if ($columnResult) {
    $hasSellerId = $columnResult->num_rows > 0;
    $columnResult->close();
}

$qFrom = trim((string) ($_GET['from'] ?? ''));
$qTo = trim((string) ($_GET['to'] ?? ''));
$export = (int) ($_GET['export'] ?? 0);

$dateWhere = '';
$dateParams = [];
$dateTypes = '';

if ($qFrom !== '') {
    $dateWhere .= " AND o.created_at >= ?";
    $dateParams[] = $qFrom . ' 00:00:00';
    $dateTypes .= 's';
}

if ($qTo !== '') {
    $dateWhere .= " AND o.created_at <= ?";
    $dateParams[] = $qTo . ' 23:59:59';
    $dateTypes .= 's';
}

if ($export === 1) {
    $shopNameSelect = $hasSellerId
        ? "COALESCE(s.shop_name, 'Marketplace Seller') AS shop_name"
        : "'Marketplace' AS shop_name";

    $sellerJoin = $hasSellerId
        ? "LEFT JOIN sellers s ON s.id = p.seller_id"
        : '';

    $sql = "
        SELECT
            o.id AS order_id,
            o.user_email,
            o.payment_method,
            o.created_at,
            $shopNameSelect,
            p.name AS product_name,
            oi.quantity,
            oi.price,
            (oi.quantity * oi.price) AS item_total
        FROM order_items oi
        INNER JOIN orders o
            ON o.id = oi.order_id
        INNER JOIN products p
            ON p.id = oi.product_id
        $sellerJoin
        WHERE LOWER(o.status) = 'delivered'
        $dateWhere
        ORDER BY o.created_at DESC, o.id DESC
    ";

    $stmt = $conn->prepare($sql);

    if ($dateParams) {
        $stmt->bind_param($dateTypes, ...$dateParams);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=admin_sales_export_' . date('Ymd_His') . '.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Order ID',
        'Customer Email',
        'Seller Shop',
        'Product Name',
        'Quantity',
        'Unit Price',
        'Sale Total',
        'Seller Share (85%)',
        'Platform Commission (15%)',
        'Payment Method',
        'Delivered Date',
    ]);

    while ($row = $result->fetch_assoc()) {
        $itemTotal = (float) $row['item_total'];

        fputcsv($out, [
            $row['order_id'],
            $row['user_email'],
            $row['shop_name'],
            $row['product_name'],
            $row['quantity'],
            number_format((float) $row['price'], 2, '.', ''),
            number_format($itemTotal, 2, '.', ''),
            number_format($itemTotal * 0.85, 2, '.', ''),
            number_format($itemTotal * 0.15, 2, '.', ''),
            strtoupper((string) $row['payment_method']),
            $row['created_at'],
        ]);
    }

    fclose($out);
    $stmt->close();
    exit();
}

$activeSellerSelect = $hasSellerId
    ? 'COUNT(DISTINCT p.seller_id) AS active_sellers'
    : '0 AS active_sellers';

$summarySql = "
    SELECT
        COUNT(DISTINCT o.id) AS delivered_orders,
        $activeSellerSelect,
        COALESCE(SUM(oi.quantity * oi.price), 0) AS total_sales
    FROM order_items oi
    INNER JOIN orders o
        ON o.id = oi.order_id
    INNER JOIN products p
        ON p.id = oi.product_id
    WHERE LOWER(o.status) = 'delivered'
    $dateWhere
";

$stmt = $conn->prepare($summarySql);

if ($dateParams) {
    $stmt->bind_param($dateTypes, ...$dateParams);
}

$stmt->execute();
$result = $stmt->get_result();
$summary = $result ? $result->fetch_assoc() : [];
$stmt->close();

$totalSales = (float) ($summary['total_sales'] ?? 0);
$sellerShare = $totalSales * 0.85;
$platformCommission = $totalSales * 0.15;

$shopNameSelect = $hasSellerId
    ? "COALESCE(s.shop_name, 'Marketplace Seller') AS shop_name"
    : "'Marketplace' AS shop_name";

$sellerJoin = $hasSellerId
    ? "LEFT JOIN sellers s ON s.id = p.seller_id"
    : '';

$salesSql = "
    SELECT
        o.id AS order_id,
        o.user_email,
        o.payment_method,
        o.created_at,
        $shopNameSelect,
        p.name AS product_name,
        oi.quantity,
        oi.price,
        (oi.quantity * oi.price) AS item_total
    FROM order_items oi
    INNER JOIN orders o
        ON o.id = oi.order_id
    INNER JOIN products p
        ON p.id = oi.product_id
    $sellerJoin
    WHERE LOWER(o.status) = 'delivered'
    $dateWhere
    ORDER BY o.created_at DESC, o.id DESC
";

$stmt = $conn->prepare($salesSql);

if ($dateParams) {
    $stmt->bind_param($dateTypes, ...$dateParams);
}

$stmt->execute();
$result = $stmt->get_result();
$sales = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

$exportQuery = ['export' => 1];

if ($qFrom !== '') {
    $exportQuery['from'] = $qFrom;
}

if ($qTo !== '') {
    $exportQuery['to'] = $qTo;
}

$exportUrl = 'admin_sales.php?' . http_build_query($exportQuery);

ob_start();
?>

<style>
    .earning-value {
        font-size: 1.7rem;
        font-weight: 800;
        margin-top: 7px;
    }

    .earning-note {
        color: var(--muted);
        font-size: .82rem;
        margin-top: 7px;
    }

    .summary-highlight {
        border: 1px solid rgba(16, 185, 129, .35);
        background: linear-gradient(135deg, rgba(16, 185, 129, .08), var(--panel));
    }

    .commission-card {
        border: 1px solid rgba(245, 158, 11, .3);
        background: linear-gradient(135deg, rgba(245, 158, 11, .06), var(--panel));
    }

    .sales-breakdown {
        margin-top: 20px;
        padding: 16px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--panel-2);
    }

    .breakdown-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 10px 0;
        border-bottom: 1px solid var(--border);
    }

    .breakdown-row:last-child {
        border-bottom: none;
    }

    .breakdown-label {
        color: var(--muted);
    }

    .breakdown-value {
        font-weight: 800;
    }

    .table-wrap {
        overflow-x: auto;
    }

    .sales-filter {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 10px;
        margin-bottom: 18px;
    }

    .filter-field {
        grid-column: span 4;
    }

    .filter-actions {
        grid-column: span 4;
        display: flex;
        align-items: flex-end;
        gap: 8px;
    }

    .filter-label {
        display: block;
        font-weight: 800;
        color: var(--muted);
        margin-bottom: 6px;
    }

    .filter-input {
        width: 100%;
        padding: 10px;
        border-radius: 10px;
        background: var(--panel);
        color: var(--text);
        border: 1px solid var(--border);
    }

    @media (max-width: 800px) {
        .filter-field,
        .filter-actions {
            grid-column: span 6;
        }
    }

    @media (max-width: 560px) {
        .filter-field,
        .filter-actions {
            grid-column: span 12;
        }
    }
</style>

<div class="grid">
    <div class="card col-3 summary-highlight">
        <h3>Total Sales</h3>
        <div class="earning-value">Tk <?= number_format($totalSales, 2) ?></div>
        <div class="earning-note">Delivered sales amount</div>
    </div>

    <div class="card col-3">
        <h3>Delivered Orders</h3>
        <div class="earning-value"><?= (int) ($summary['delivered_orders'] ?? 0) ?></div>
        <div class="earning-note">Completed marketplace orders</div>
    </div>

    <div class="card col-3">
        <h3>Seller Share</h3>
        <div class="earning-value">Tk <?= number_format($sellerShare, 2) ?></div>
        <div class="earning-note">Estimated 85% payout</div>
    </div>

    <div class="card col-3 commission-card">
        <h3>Platform Commission</h3>
        <div class="earning-value">Tk <?= number_format($platformCommission, 2) ?></div>
        <div class="earning-note">Estimated 15% commission</div>
    </div>

    <div class="card col-12">
        <h3>Sales & Commission</h3>

        <form method="get" class="sales-filter">
            <div class="filter-field">
                <label class="filter-label" for="from">From Date</label>
                <input class="filter-input" id="from" type="date" name="from" value="<?= e($qFrom) ?>">
            </div>

            <div class="filter-field">
                <label class="filter-label" for="to">To Date</label>
                <input class="filter-input" id="to" type="date" name="to" value="<?= e($qTo) ?>">
            </div>

            <div class="filter-actions">
                <button class="btn" type="submit">Filter</button>
                <a class="btn" href="admin_sales.php">Reset</a>
                <a class="btn" href="<?= e($exportUrl) ?>">Export CSV</a>
            </div>
        </form>

        <div class="sales-breakdown">
            <div class="breakdown-row">
                <div class="breakdown-label">Active Sellers in Delivered Orders</div>
                <div class="breakdown-value"><?= (int) ($summary['active_sellers'] ?? 0) ?></div>
            </div>
            <div class="breakdown-row">
                <div class="breakdown-label">Total Seller Share</div>
                <div class="breakdown-value">Tk <?= number_format($sellerShare, 2) ?></div>
            </div>
            <div class="breakdown-row">
                <div class="breakdown-label">Total Platform Commission</div>
                <div class="breakdown-value">Tk <?= number_format($platformCommission, 2) ?></div>
            </div>
        </div>
    </div>

    <div class="card col-12">
        <h3>Delivered Sales List</h3>

        <div class="table-wrap">
            <table class="table" aria-label="Admin sales table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Seller</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Sale Total</th>
                        <th>Seller Share</th>
                        <th>Commission</th>
                        <th>Payment</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$sales): ?>
                        <tr>
                            <td colspan="10" style="text-align:center; color:var(--muted);">No delivered sales found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $sale): ?>
                            <?php $itemTotal = (float) $sale['item_total']; ?>
                            <tr>
                                <td>
                                    <a class="link" href="sales_details.php?order=<?= (int) $sale['order_id'] ?>">
                                        #<?= (int) $sale['order_id'] ?>
                                    </a>
                                </td>
                                <td><?= e((string) $sale['user_email']) ?></td>
                                <td><?= e((string) $sale['shop_name']) ?></td>
                                <td><?= e((string) $sale['product_name']) ?></td>
                                <td><?= (int) $sale['quantity'] ?></td>
                                <td style="font-weight:800;">Tk <?= number_format($itemTotal, 2) ?></td>
                                <td>Tk <?= number_format($itemTotal * 0.85, 2) ?></td>
                                <td>Tk <?= number_format($itemTotal * 0.15, 2) ?></td>
                                <td><?= e(strtoupper((string) $sale['payment_method'])) ?></td>
                                <td><?= e(date('Y-m-d H:i', strtotime((string) $sale['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Admin Sales';
$actions = [
    [
        'href' => $exportUrl,
        'label' => 'Export CSV',
        'brand' => true,
    ],
];

require __DIR__ . '/layout.php';
