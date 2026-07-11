<?php
// seller_dashboard.php

declare(strict_types=1);

session_start();

if (empty($_SESSION['seller_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/db.php';

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

$sellerId = (int) $_SESSION['seller_id'];


/*
|--------------------------------------------------------------------------
| COUNT SELLER'S PRODUCTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM products
    WHERE seller_id = ?
");

$stmt->bind_param('i', $sellerId);
$stmt->execute();
$stmt->bind_result($productCount);
$stmt->fetch();
$stmt->close();


/*
|--------------------------------------------------------------------------
| COUNT SELLER'S UNIQUE ORDERS
|--------------------------------------------------------------------------
|
| DISTINCT is important because one order may contain more than one
| product belonging to the same seller.
|
*/

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT oi.order_id)

    FROM order_items oi

    INNER JOIN products p
        ON p.id = oi.product_id

    WHERE p.seller_id = ?
");

$stmt->bind_param('i', $sellerId);
$stmt->execute();
$stmt->bind_result($orderCount);
$stmt->fetch();
$stmt->close();


/*
|--------------------------------------------------------------------------
| CALCULATE TOTAL SALES
|--------------------------------------------------------------------------
|
| Only DELIVERED orders count as completed sales.
|
| We calculate using:
|
| quantity × item price
|
| Only products belonging to the logged-in seller are included.
|
*/

$stmt = $conn->prepare("
    SELECT
        COALESCE(
            SUM(oi.quantity * oi.price),
            0
        )

    FROM order_items oi

    INNER JOIN products p
        ON p.id = oi.product_id

    INNER JOIN orders o
        ON o.id = oi.order_id

    WHERE p.seller_id = ?
      AND LOWER(o.status) = 'delivered'
");

$stmt->bind_param('i', $sellerId);
$stmt->execute();
$stmt->bind_result($totalSales);
$stmt->fetch();
$stmt->close();


/*
|--------------------------------------------------------------------------
| CALCULATE SELLER EARNINGS
|--------------------------------------------------------------------------
|
| Seller receives 85%.
| Admin/platform commission = 15%.
|
*/

$totalSales = (float) $totalSales;

$sellerEarnings = $totalSales * 0.85;


/*
|--------------------------------------------------------------------------
| BUILD PAGE
|--------------------------------------------------------------------------
*/

ob_start();
?>

<div class="grid">


    <!-- MY PRODUCTS -->

    <div class="card col-3">

        <h3>
            My Products
        </h3>

        <div class="stat-number">

            <?= (int) $productCount ?>

        </div>

    </div>


    <!-- MY ORDERS -->

    <div class="card col-3">

        <h3>
            My Orders
        </h3>

        <div class="stat-number">

            <?= (int) $orderCount ?>

        </div>

    </div>


    <!-- TOTAL SALES -->

    <div class="card col-3">

        <h3>
            Total Sales
        </h3>

        <div class="stat-number">

            Tk <?= number_format(
                    $totalSales,
                    2
                ) ?>

        </div>

    </div>


    <!-- SELLER EARNINGS -->

    <div class="card col-3">

        <h3>
            My Earnings (85%)
        </h3>

        <div class="stat-number">

            Tk <?= number_format(
                    $sellerEarnings,
                    2
                ) ?>

        </div>

    </div>


    <!-- WELCOME CARD -->

    <div class="card col-12">

        <h3>
            Welcome to Your Seller Dashboard
        </h3>

        <p
            style="
                color:var(--muted);
                margin-bottom:0;
                line-height:1.7;
            ">

            You are logged in as

            <strong style="color:var(--text);">

                <?= e(
                    (string)
                    ($_SESSION['seller_name']
                        ?? 'Seller')
                ) ?>

            </strong>

            from

            <strong style="color:var(--text);">

                <?= e(
                    (string)
                    ($_SESSION['seller_shop_name']
                        ?? 'Your Shop')
                ) ?>

            </strong>.

            Your products, orders, sales and earnings
            will appear here.

        </p>

    </div>


</div>

<?php

$content = ob_get_clean();

$pageTitle = 'Seller Dashboard';

$actions = [

    [
        'href' =>
        'seller_add_product.php',

        'label' =>
        'Add Product',

        'brand' =>
        true
    ]

];

include __DIR__ .
    '/seller_layout.php';
