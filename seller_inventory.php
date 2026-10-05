<?php
// seller_inventory.php — inventory management for the logged-in seller

declare(strict_types=1);

session_start();

if (empty($_SESSION['seller_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/db.php';

$sellerId = (int) $_SESSION['seller_id'];

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
|
| Products at or below this quantity (but still above zero) are flagged
| as "low stock". Zero (or less) is treated as "out of stock".
|
*/

$lowStockThreshold = 5;

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['seller_csrf'])) {
    $_SESSION['seller_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['seller_csrf'];

$successMessage = '';
$errorMessage   = '';

/*
|--------------------------------------------------------------------------
| UPDATE STOCK
|--------------------------------------------------------------------------
|
| Only the owning seller can update a product's stock. The new value must
| be a non-negative integer.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_stock'])
) {
    $postedCsrf = (string) ($_POST['csrf'] ?? '');
    $productId  = (int) ($_POST['product_id'] ?? 0);
    $newStock   = $_POST['stock'] ?? '';

    if (
        $postedCsrf === ''
        || !hash_equals($_SESSION['seller_csrf'], $postedCsrf)
    ) {
        $errorMessage = 'Invalid request. Please refresh and try again.';
    } elseif ($productId <= 0) {
        $errorMessage = 'Invalid product.';
    } elseif (!is_numeric($newStock) || (int) $newStock < 0) {
        $errorMessage = 'Stock quantity must be a whole number of 0 or more.';
    } else {

        $newStock = (int) $newStock;

        /*
        |------------------------------------------------------------------
        | UPDATE — MUST BELONG TO THIS SELLER
        |------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            UPDATE products
            SET stock = ?
            WHERE id = ?
              AND seller_id = ?
        ");

        $stmt->bind_param(
            'iii',
            $newStock,
            $productId,
            $sellerId
        );

        if ($stmt->execute()) {

            if ($stmt->affected_rows >= 0) {
                $successMessage = 'Stock updated successfully.';
            }
        } else {
            $errorMessage = 'Failed to update stock.';
        }

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| GET ONLY THIS SELLER'S PRODUCTS (WITH STOCK)
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        category,
        name,
        price,
        image,
        stock
    FROM products
    WHERE seller_id = ?
    ORDER BY stock ASC, id DESC
");

$stmt->bind_param('i', $sellerId);
$stmt->execute();

$result = $stmt->get_result();

$products = $result
    ? $result->fetch_all(MYSQLI_ASSOC)
    : [];

$stmt->close();

/*
|--------------------------------------------------------------------------
| INVENTORY SUMMARY
|--------------------------------------------------------------------------
*/

$totalProducts = count($products);
$inStockCount  = 0;
$lowStockCount = 0;
$outOfStock    = [];

foreach ($products as $product) {

    $stock = (int) $product['stock'];

    if ($stock <= 0) {
        $outOfStock[] = $product;
    } elseif ($stock <= $lowStockThreshold) {
        $lowStockCount++;
    } else {
        $inStockCount++;
    }
}

$outOfStockCount = count($outOfStock);

/*
|--------------------------------------------------------------------------
| BUILD PAGE CONTENT
|--------------------------------------------------------------------------
*/

ob_start();
?>

<style>
    .product-message {
        padding: 12px 15px;
        margin-bottom: 18px;
        border-radius: 10px;
        font-weight: 700;
    }

    .product-message--success {
        background: rgba(16, 185, 129, .15);
        border: 1px solid rgba(16, 185, 129, .35);
        color: #34d399;
    }

    .product-message--error {
        background: rgba(239, 68, 68, .15);
        border: 1px solid rgba(239, 68, 68, .35);
        color: #f87171;
    }

    .stock-alert {
        padding: 14px 16px;
        margin-bottom: 18px;
        border-radius: 10px;
        border: 1px solid rgba(239, 68, 68, .45);
        background: rgba(239, 68, 68, .10);
        color: var(--text);
        line-height: 1.6;
    }

    .stock-alert strong {
        color: #f87171;
    }

    .stock-badge {
        display: inline-block;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: .78rem;
        font-weight: 800;
    }

    .stock-badge--in {
        background: rgba(16, 185, 129, .18);
        color: var(--ok);
    }

    .stock-badge--low {
        background: rgba(245, 158, 11, .18);
        color: var(--warn);
    }

    .stock-badge--out {
        background: rgba(239, 68, 68, .18);
        color: #f87171;
    }

    .stock-form {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        flex-wrap: wrap;
    }

    .stock-input {
        width: 90px;
        padding: 8px 10px;
        border-radius: 9px;
        border: 1px solid var(--border);
        background: var(--panel-2);
        color: var(--text);
        font-weight: 700;
    }

    .stock-save-btn {
        padding: 8px 13px;
        border: none;
        border-radius: 9px;
        background: #2563eb;
        color: white;
        font-size: .85rem;
        font-weight: 700;
        cursor: pointer;
    }

    .stock-save-btn:hover {
        opacity: .85;
    }
</style>

<div class="grid">


    <!-- SUMMARY CARDS -->

    <div class="card col-3">
        <h3>Total Products</h3>
        <div class="stat-number">
            <?= (int) $totalProducts ?>
        </div>
    </div>

    <div class="card col-3">
        <h3>In Stock</h3>
        <div class="stat-number" style="color:var(--ok);">
            <?= (int) $inStockCount ?>
        </div>
    </div>

    <div class="card col-3">
        <h3>Low Stock (&le; <?= (int) $lowStockThreshold ?>)</h3>
        <div class="stat-number" style="color:var(--warn);">
            <?= (int) $lowStockCount ?>
        </div>
    </div>

    <div class="card col-3">
        <h3>Out of Stock</h3>
        <div class="stat-number" style="color:#f87171;">
            <?= (int) $outOfStockCount ?>
        </div>
    </div>


    <!-- INVENTORY TABLE -->

    <div class="card col-12">

        <h3>Inventory Management</h3>

        <?php if ($successMessage !== ''): ?>

            <div class="product-message product-message--success">
                <?= e($successMessage) ?>
            </div>

        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>

            <div class="product-message product-message--error">
                <?= e($errorMessage) ?>
            </div>

        <?php endif; ?>

        <?php if ($outOfStockCount > 0): ?>

            <div class="stock-alert">
                <strong>Out-of-stock notice:</strong>
                <?= (int) $outOfStockCount ?>
                <?= $outOfStockCount === 1
                    ? 'product is'
                    : 'products are' ?>
                currently out of stock and cannot be ordered
                by customers. Update the quantity below to restock.
            </div>

        <?php endif; ?>

        <div class="table-wrap">

            <table class="table">

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Current Stock</th>
                        <th>Status</th>
                        <th>Update Stock</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($products)): ?>

                        <tr>
                            <td
                                colspan="8"
                                style="
                                    text-align:center;
                                    color:var(--muted);
                                    padding:30px;
                                ">
                                You have not added any products yet.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($products as $product): ?>

                            <?php
                            $stock = (int) $product['stock'];

                            if ($stock <= 0) {
                                $statusClass = 'stock-badge--out';
                                $statusLabel = 'Out of Stock';
                            } elseif ($stock <= $lowStockThreshold) {
                                $statusClass = 'stock-badge--low';
                                $statusLabel = 'Low Stock';
                            } else {
                                $statusClass = 'stock-badge--in';
                                $statusLabel = 'In Stock';
                            }
                            ?>

                            <tr>

                                <td>
                                    #<?= (int) $product['id'] ?>
                                </td>

                                <td>

                                    <?php if (!empty($product['image'])): ?>

                                        <img
                                            src="uploads/<?= e((string) $product['image']) ?>"
                                            alt="<?= e((string) $product['name']) ?>"
                                            style="
                                                width:70px;
                                                height:70px;
                                                object-fit:cover;
                                                border-radius:10px;
                                                border:1px solid var(--border);
                                            ">

                                    <?php else: ?>

                                        <span style="color:var(--muted);">
                                            No Image
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td style="font-weight:700;">
                                    <?= e((string) $product['name']) ?>
                                </td>

                                <td>
                                    <span class="badge">
                                        <?= e((string) $product['category']) ?>
                                    </span>
                                </td>

                                <td style="font-weight:700;">
                                    Tk <?= number_format(
                                            (float) $product['price'],
                                            2
                                        ) ?>
                                </td>

                                <td style="font-weight:800;font-size:1.05rem;">
                                    <?= $stock ?>
                                </td>

                                <td>
                                    <span class="stock-badge <?= $statusClass ?>">
                                        <?= $statusLabel ?>
                                    </span>
                                </td>

                                <td>

                                    <form
                                        method="POST"
                                        class="stock-form">

                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= e($csrf) ?>">

                                        <input
                                            type="hidden"
                                            name="product_id"
                                            value="<?= (int) $product['id'] ?>">

                                        <input
                                            type="number"
                                            name="stock"
                                            class="stock-input"
                                            min="0"
                                            step="1"
                                            value="<?= $stock ?>">

                                        <button
                                            type="submit"
                                            name="update_stock"
                                            class="stock-save-btn">
                                            Save
                                        </button>

                                    </form>

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

$pageTitle = 'Inventory Management';

$actions = [
    [
        'href'  => 'seller_add_product.php',
        'label' => 'Add Product',
        'brand' => true
    ]
];

include __DIR__ . '/seller_layout.php';
