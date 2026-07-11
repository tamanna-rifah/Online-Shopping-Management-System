<?php
// seller_products.php — seller manages only their own products

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
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['seller_csrf'])) {
    $_SESSION['seller_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['seller_csrf'];

$successMessage = '';
$errorMessage = '';

/*
|--------------------------------------------------------------------------
| DELETE PRODUCT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_product'])
) {
    $postedCsrf = (string) ($_POST['csrf'] ?? '');
    $productId = (int) ($_POST['product_id'] ?? 0);

    if (
        $postedCsrf === ''
        || !hash_equals($_SESSION['seller_csrf'], $postedCsrf)
    ) {
        $errorMessage = 'Invalid request. Please refresh and try again.';
    } elseif ($productId <= 0) {

        $errorMessage = 'Invalid product.';
    } else {

        /*
        |--------------------------------------------------------------------------
        | GET PRODUCT — MUST BELONG TO THIS SELLER
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT image
            FROM products
            WHERE id = ?
              AND seller_id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            'ii',
            $productId,
            $sellerId
        );

        $stmt->execute();

        $result = $stmt->get_result();
        $productToDelete = $result->fetch_assoc();

        $stmt->close();

        if (!$productToDelete) {

            $errorMessage =
                'Product not found or you do not have permission to delete it.';
        } else {

            /*
            |--------------------------------------------------------------------------
            | CHECK WHETHER PRODUCT HAS ORDER HISTORY
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM order_items
                WHERE product_id = ?
            ");

            $stmt->bind_param(
                'i',
                $productId
            );

            $stmt->execute();

            $result = $stmt->get_result();
            $orderCheck = $result->fetch_assoc();

            $stmt->close();

            $hasOrderHistory =
                (int) ($orderCheck['total'] ?? 0) > 0;

            if ($hasOrderHistory) {

                $errorMessage =
                    'This product cannot be deleted because it already has order history.';
            } else {

                /*
                |--------------------------------------------------------------------------
                | DELETE PRODUCT
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    DELETE FROM products
                    WHERE id = ?
                      AND seller_id = ?
                ");

                $stmt->bind_param(
                    'ii',
                    $productId,
                    $sellerId
                );

                if ($stmt->execute()) {

                    /*
                    |--------------------------------------------------------------------------
                    | DELETE IMAGE FILE
                    |--------------------------------------------------------------------------
                    */

                    $imageName =
                        (string) ($productToDelete['image'] ?? '');

                    if ($imageName !== '') {

                        $imagePath =
                            __DIR__
                            . '/uploads/'
                            . basename($imageName);

                        if (is_file($imagePath)) {
                            unlink($imagePath);
                        }
                    }

                    $successMessage =
                        'Product deleted successfully.';
                } else {

                    $errorMessage =
                        'Failed to delete product.';
                }

                $stmt->close();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET ONLY THIS SELLER'S PRODUCTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        category,
        name,
        price,
        image
    FROM products
    WHERE seller_id = ?
    ORDER BY id DESC
");

$stmt->bind_param(
    'i',
    $sellerId
);

$stmt->execute();

$result = $stmt->get_result();

$products = $result
    ? $result->fetch_all(MYSQLI_ASSOC)
    : [];

$stmt->close();

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

    .product-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .product-actions form {
        margin: 0;
    }

    .edit-btn {
        padding: 8px 13px;
        border-radius: 9px;
        background: #2563eb;
        color: white;
        text-decoration: none;
        font-size: .85rem;
        font-weight: 700;
        display: inline-block;
    }

    .delete-btn {
        padding: 8px 13px;
        border: none;
        border-radius: 9px;
        background: #dc2626;
        color: white;
        font-size: .85rem;
        font-weight: 700;
        cursor: pointer;
    }

    .edit-btn:hover,
    .delete-btn:hover {
        opacity: .85;
    }
</style>

<div class="grid">

    <div class="card col-12">

        <h3>My Product List</h3>

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

        <div class="table-wrap">

            <table class="table">

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($products)): ?>

                        <tr>

                            <td
                                colspan="6"
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

                                <td>

                                    <div class="product-actions">

                                        <a
                                            href="seller_edit_product.php?id=<?= (int) $product['id'] ?>"
                                            class="edit-btn">
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this product?');">

                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?= e($csrf) ?>">

                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value="<?= (int) $product['id'] ?>">

                                            <button
                                                type="submit"
                                                name="delete_product"
                                                class="delete-btn">
                                                Delete
                                            </button>

                                        </form>

                                    </div>

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

$pageTitle = 'My Products';

$actions = [
    [
        'href'  => 'seller_add_product.php',
        'label' => 'Add Product',
        'brand' => true
    ]
];

include __DIR__ . '/seller_layout.php';
