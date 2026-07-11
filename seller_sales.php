<?php
// seller_sales.php — Seller Sales & Earnings Page

declare(strict_types=1);

session_start();

if (empty($_SESSION['seller_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/db.php';

$sellerId = (int) $_SESSION['seller_id'];


/* SAFE OUTPUT */

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}


/*
|--------------------------------------------------------------------------
| INPUTS
|--------------------------------------------------------------------------
*/

$qFrom = isset($_GET['from'])
    ? trim((string) $_GET['from'])
    : '';

$qTo = isset($_GET['to'])
    ? trim((string) $_GET['to'])
    : '';

$export = isset($_GET['export'])
    ? (int) $_GET['export']
    : 0;


/*
|--------------------------------------------------------------------------
| BUILD DATE FILTER
|--------------------------------------------------------------------------
|
| Delivered orders are always required.
| Optional From and To dates are added when selected.
|
*/

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


/*
|--------------------------------------------------------------------------
| CSV EXPORT
|--------------------------------------------------------------------------
|
| Same process as sales_details.php:
|
| seller_sales.php?export=1
|
| If date filters are active, only filtered results are exported.
|
*/

if ($export === 1) {

    $sql = "
        SELECT
            o.id AS order_id,
            o.user_email,
            o.payment_method,
            o.created_at,

            p.id AS product_id,
            p.name AS product_name,

            oi.quantity,
            oi.price,

            (oi.quantity * oi.price) AS item_total

        FROM order_items oi

        INNER JOIN products p
            ON p.id = oi.product_id

        INNER JOIN orders o
            ON o.id = oi.order_id

        WHERE p.seller_id = ?
          AND LOWER(o.status) = 'delivered'

          $dateWhere

        ORDER BY
            o.created_at DESC,
            o.id DESC
    ";

    $stmt = $conn->prepare($sql);

    $exportTypes = 'i' . $dateTypes;
    $exportParams = array_merge(
        [$sellerId],
        $dateParams
    );

    $stmt->bind_param(
        $exportTypes,
        ...$exportParams
    );

    $stmt->execute();

    $result = $stmt->get_result();


    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD HEADERS
    |--------------------------------------------------------------------------
    */

    header('Content-Type: text/csv; charset=utf-8');

    header(
        'Content-Disposition: attachment; filename=seller_sales_export_'
            . date('Ymd_His')
            . '.csv'
    );


    /*
    |--------------------------------------------------------------------------
    | CREATE CSV
    |--------------------------------------------------------------------------
    */

    $out = fopen('php://output', 'w');

    fputcsv(
        $out,
        [
            'Order ID',
            'Customer Email',
            'Product ID',
            'Product Name',
            'Quantity',
            'Unit Price',
            'Sale Total',
            'Seller Earnings (85%)',
            'Platform Commission (15%)',
            'Payment Method',
            'Sale Date'
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | WRITE CSV ROWS
    |--------------------------------------------------------------------------
    */

    while ($row = $result->fetch_assoc()) {

        $itemTotal =
            (float) $row['item_total'];

        $sellerEarning =
            $itemTotal * 0.85;

        $platformCommission =
            $itemTotal * 0.15;

        fputcsv(
            $out,
            [
                $row['order_id'],

                $row['user_email'],

                $row['product_id'],

                $row['product_name'],

                $row['quantity'],

                number_format(
                    (float) $row['price'],
                    2,
                    '.',
                    ''
                ),

                number_format(
                    $itemTotal,
                    2,
                    '.',
                    ''
                ),

                number_format(
                    $sellerEarning,
                    2,
                    '.',
                    ''
                ),

                number_format(
                    $platformCommission,
                    2,
                    '.',
                    ''
                ),

                strtoupper(
                    (string) $row['payment_method']
                ),

                $row['created_at']
            ]
        );
    }

    fclose($out);

    $stmt->close();

    exit();
}


/*
|--------------------------------------------------------------------------
| GET COMPLETED SALES SUMMARY
|--------------------------------------------------------------------------
*/

$summarySql = "
    SELECT
        COUNT(DISTINCT oi.order_id)
            AS completed_orders,

        COALESCE(
            SUM(oi.quantity * oi.price),
            0
        ) AS total_sales

    FROM order_items oi

    INNER JOIN products p
        ON p.id = oi.product_id

    INNER JOIN orders o
        ON o.id = oi.order_id

    WHERE p.seller_id = ?
      AND LOWER(o.status) = 'delivered'

      $dateWhere
";

$stmt = $conn->prepare($summarySql);

$summaryTypes = 'i' . $dateTypes;

$summaryParams = array_merge(
    [$sellerId],
    $dateParams
);

$stmt->bind_param(
    $summaryTypes,
    ...$summaryParams
);

$stmt->execute();

$result = $stmt->get_result();

$summary = $result
    ? $result->fetch_assoc()
    : [];

$stmt->close();


/* SUMMARY VALUES */

$completedOrders =
    (int) ($summary['completed_orders'] ?? 0);

$totalSales =
    (float) ($summary['total_sales'] ?? 0);

$sellerEarnings =
    $totalSales * 0.85;

$adminCommission =
    $totalSales * 0.15;


/*
|--------------------------------------------------------------------------
| GET DETAILED COMPLETED SALES
|--------------------------------------------------------------------------
*/

$salesSql = "
    SELECT
        o.id AS order_id,
        o.user_email,
        o.payment_method,
        o.created_at,

        p.id AS product_id,
        p.name AS product_name,

        oi.quantity,
        oi.price,

        (oi.quantity * oi.price)
            AS item_total

    FROM order_items oi

    INNER JOIN products p
        ON p.id = oi.product_id

    INNER JOIN orders o
        ON o.id = oi.order_id

    WHERE p.seller_id = ?
      AND LOWER(o.status) = 'delivered'

      $dateWhere

    ORDER BY
        o.created_at DESC,
        o.id DESC
";

$stmt = $conn->prepare($salesSql);

$salesTypes = 'i' . $dateTypes;

$salesParams = array_merge(
    [$sellerId],
    $dateParams
);

$stmt->bind_param(
    $salesTypes,
    ...$salesParams
);

$stmt->execute();

$result = $stmt->get_result();

$sales = $result
    ? $result->fetch_all(MYSQLI_ASSOC)
    : [];

$stmt->close();


/*
|--------------------------------------------------------------------------
| EXPORT URL
|--------------------------------------------------------------------------
*/

$exportQuery = [
    'export' => 1
];

if ($qFrom !== '') {
    $exportQuery['from'] = $qFrom;
}

if ($qTo !== '') {
    $exportQuery['to'] = $qTo;
}

$exportUrl =
    'seller_sales.php?'
    . http_build_query($exportQuery);


/*
|--------------------------------------------------------------------------
| BUILD PAGE CONTENT
|--------------------------------------------------------------------------
*/

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
        background:
            linear-gradient(135deg,
                rgba(16, 185, 129, .08),
                var(--panel));
    }

    .commission-card {
        border: 1px solid rgba(245, 158, 11, .3);
        background:
            linear-gradient(135deg,
                rgba(245, 158, 11, .06),
                var(--panel));
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
            grid-column: span 12;
        }
    }
</style>


<div class="grid">


    <!-- SUMMARY CARDS -->

    <div class="card col-3">

        <h3>Completed Orders</h3>

        <div class="earning-value">
            <?= $completedOrders ?>
        </div>

        <div class="earning-note">
            Delivered orders only
        </div>

    </div>


    <div class="card col-3">

        <h3>Total Sales</h3>

        <div class="earning-value">

            Tk <?= number_format(
                    $totalSales,
                    2
                ) ?>

        </div>

        <div class="earning-note">
            100% product sales value
        </div>

    </div>


    <div class="card col-3 summary-highlight">

        <h3>My Earnings (85%)</h3>

        <div class="earning-value">

            Tk <?= number_format(
                    $sellerEarnings,
                    2
                ) ?>

        </div>

        <div class="earning-note">
            Your share of completed sales
        </div>

    </div>


    <div class="card col-3 commission-card">

        <h3>Platform Commission (15%)</h3>

        <div class="earning-value">

            Tk <?= number_format(
                    $adminCommission,
                    2
                ) ?>

        </div>

        <div class="earning-note">
            Deducted from completed sales
        </div>

    </div>


    <!-- EARNINGS BREAKDOWN -->

    <div class="card col-12">

        <h3>Earnings Breakdown</h3>

        <div class="sales-breakdown">

            <div class="breakdown-row">

                <span class="breakdown-label">
                    Total completed sales
                </span>

                <span class="breakdown-value">

                    Tk <?= number_format(
                            $totalSales,
                            2
                        ) ?>

                </span>

            </div>


            <div class="breakdown-row">

                <span class="breakdown-label">
                    Platform commission (15%)
                </span>

                <span class="breakdown-value">

                    − Tk <?= number_format(
                                $adminCommission,
                                2
                            ) ?>

                </span>

            </div>


            <div class="breakdown-row">

                <span
                    class="breakdown-label"
                    style="
                        color:var(--ok);
                        font-weight:800;
                    ">
                    Your earnings (85%)
                </span>

                <span
                    class="breakdown-value"
                    style="color:var(--ok);">

                    Tk <?= number_format(
                            $sellerEarnings,
                            2
                        ) ?>

                </span>

            </div>

        </div>

    </div>


    <!-- SALES HISTORY -->

    <div class="card col-12">

        <h3>Completed Sales History</h3>


        <!-- FILTER -->

        <form
            method="GET"
            action="seller_sales.php"
            class="sales-filter">

            <div class="filter-field">

                <label
                    for="from"
                    class="filter-label">
                    From
                </label>

                <input
                    type="date"
                    id="from"
                    name="from"
                    value="<?= e($qFrom) ?>"
                    class="filter-input">

            </div>


            <div class="filter-field">

                <label
                    for="to"
                    class="filter-label">
                    To
                </label>

                <input
                    type="date"
                    id="to"
                    name="to"
                    value="<?= e($qTo) ?>"
                    class="filter-input">

            </div>


            <div class="filter-actions">

                <button
                    type="submit"
                    class="btn btn--brand">
                    Filter
                </button>

                <a
                    href="seller_sales.php"
                    class="btn">
                    Reset
                </a>

            </div>

        </form>


        <!-- EXPORT BUTTON -->

        <div
            style="
                display:flex;
                gap:8px;
                margin-bottom:12px;
            ">

            <a
                href="<?= e($exportUrl) ?>"
                class="btn btn--brand">
                Export CSV
            </a>

        </div>


        <!-- TABLE -->

        <div class="table-wrap">

            <table class="table">

                <thead>

                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Sale Total</th>
                        <th>My Earnings</th>
                        <th>Commission</th>
                        <th>Date</th>
                    </tr>

                </thead>

                <tbody>


                    <?php if (empty($sales)): ?>

                        <tr>

                            <td
                                colspan="9"
                                style="
                                    text-align:center;
                                    color:var(--muted);
                                    padding:30px;
                                ">
                                No completed sales found.
                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($sales as $sale): ?>


                            <?php

                            $itemTotal =
                                (float) $sale['item_total'];

                            $itemEarnings =
                                $itemTotal * 0.85;

                            $itemCommission =
                                $itemTotal * 0.15;

                            ?>


                            <tr>

                                <td style="font-weight:800;">
                                    #<?= (int) $sale['order_id'] ?>
                                </td>

                                <td>
                                    <?= e(
                                        (string) $sale['user_email']
                                    ) ?>
                                </td>

                                <td style="font-weight:700;">
                                    <?= e(
                                        (string) $sale['product_name']
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int) $sale['quantity'] ?>
                                </td>

                                <td>
                                    Tk <?= number_format(
                                            (float) $sale['price'],
                                            2
                                        ) ?>
                                </td>

                                <td style="font-weight:800;">
                                    Tk <?= number_format(
                                            $itemTotal,
                                            2
                                        ) ?>
                                </td>

                                <td
                                    style="
                                        color:var(--ok);
                                        font-weight:800;
                                    ">
                                    Tk <?= number_format(
                                            $itemEarnings,
                                            2
                                        ) ?>
                                </td>

                                <td>
                                    Tk <?= number_format(
                                            $itemCommission,
                                            2
                                        ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        date(
                                            'Y-m-d H:i',
                                            strtotime(
                                                (string) $sale['created_at']
                                            )
                                        )
                                    ) ?>
                                </td>

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


/*
|--------------------------------------------------------------------------
| PAGE SETTINGS
|--------------------------------------------------------------------------
*/

$pageTitle = 'Sales & Earnings';

$actions = [
    [
        'href'  => $exportUrl,
        'label' => 'Export CSV',
        'brand' => true
    ]
];


/*
|--------------------------------------------------------------------------
| LOAD SELLER LAYOUT
|--------------------------------------------------------------------------
*/

include __DIR__ . '/seller_layout.php';
