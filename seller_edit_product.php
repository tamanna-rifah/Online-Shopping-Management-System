<?php
// seller_edit_product.php — edit only the logged-in seller's product

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

$errors = [];

/*
|--------------------------------------------------------------------------
| GET PRODUCT ID
|--------------------------------------------------------------------------
*/

$productId = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);

if ($productId <= 0) {
    header('Location: seller_products.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| GET PRODUCT — MUST BELONG TO LOGGED-IN SELLER
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
    WHERE id = ?
      AND seller_id = ?
    LIMIT 1
");

$stmt->bind_param('ii', $productId, $sellerId);
$stmt->execute();

$result = $stmt->get_result();
$product = $result->fetch_assoc();

$stmt->close();

if (!$product) {
    header('Location: seller_products.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| CURRENT FORM VALUES
|--------------------------------------------------------------------------
*/

$category = (string) $product['category'];
$name = (string) $product['name'];
$price = (string) $product['price'];
$currentImage = (string) ($product['image'] ?? '');

/*
|--------------------------------------------------------------------------
| HANDLE UPDATE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CHECK CSRF
    |--------------------------------------------------------------------------
    */

    $postedCsrf = (string) ($_POST['csrf'] ?? '');

    if (
        $postedCsrf === ''
        || !hash_equals($_SESSION['seller_csrf'], $postedCsrf)
    ) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | GET FORM DATA
    |--------------------------------------------------------------------------
    */

    $category = trim((string) ($_POST['category'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    $priceRaw = trim((string) ($_POST['price'] ?? ''));

    $priceValue = is_numeric($priceRaw)
        ? (float) $priceRaw
        : null;

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedCategories = [
        'Children',
        'Men',
        'Women'
    ];

    if (
        $category === ''
        || !in_array($category, $allowedCategories, true)
    ) {
        $errors[] = 'Please choose a valid category.';
    }

    if (
        $name === ''
        || mb_strlen($name) > 255
    ) {
        $errors[] =
            'Product name is required and must be 255 characters or less.';
    }

    if (
        $priceValue === null
        || $priceValue < 0
        || $priceValue > 9999999
    ) {
        $errors[] =
            'Price must be a valid non-negative number.';
    }

    /*
    |--------------------------------------------------------------------------
    | OPTIONAL NEW IMAGE
    |--------------------------------------------------------------------------
    */

    $newImageName = $currentImage;
    $newImagePath = '';

    $hasNewImage =
        isset($_FILES['image'])
        && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE)
        !== UPLOAD_ERR_NO_FILE;

    if ($hasNewImage) {

        $image = $_FILES['image'];

        if (
            ($image['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_OK
        ) {
            $errors[] = 'Image upload failed.';
        } else {

            if ((int) $image['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Image is too large. Maximum size is 5MB.';
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $mime = $finfo->file($image['tmp_name']) ?: '';

            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp'
            ];

            if (!isset($allowedMimes[$mime])) {
                $errors[] =
                    'Only JPG, PNG, GIF, or WEBP images are allowed.';
            }

            if (!$errors) {

                $extension = $allowedMimes[$mime];

                $newImageName =
                    time()
                    . '_'
                    . bin2hex(random_bytes(5))
                    . '.'
                    . $extension;

                $newImagePath =
                    __DIR__
                    . '/uploads/'
                    . $newImageName;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PRODUCT
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        /*
        |--------------------------------------------------------------------------
        | SAVE NEW IMAGE FIRST
        |--------------------------------------------------------------------------
        */

        if ($hasNewImage) {

            if (!is_dir(__DIR__ . '/uploads')) {
                mkdir(__DIR__ . '/uploads', 0755, true);
            }

            if (
                !is_uploaded_file($image['tmp_name'])
                || !move_uploaded_file(
                    $image['tmp_name'],
                    $newImagePath
                )
            ) {
                $errors[] = 'Failed to save the new image.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE DATABASE
        |--------------------------------------------------------------------------
        */

        if (!$errors) {

            $stmt = $conn->prepare("
                UPDATE products
                SET
                    category = ?,
                    name = ?,
                    price = ?,
                    image = ?
                WHERE id = ?
                  AND seller_id = ?
            ");

            $stmt->bind_param(
                'ssdsii',
                $category,
                $name,
                $priceValue,
                $newImageName,
                $productId,
                $sellerId
            );

            if ($stmt->execute()) {

                $stmt->close();

                /*
                |--------------------------------------------------------------------------
                | DELETE OLD IMAGE IF REPLACED
                |--------------------------------------------------------------------------
                */

                if (
                    $hasNewImage
                    && $currentImage !== ''
                    && $currentImage !== $newImageName
                ) {
                    $oldImagePath =
                        __DIR__
                        . '/uploads/'
                        . basename($currentImage);

                    if (is_file($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                header(
                    'Location: seller_products.php?updated=1'
                );
                exit();
            } else {

                $errors[] = 'Failed to update the product.';

                $stmt->close();

                /*
                |--------------------------------------------------------------------------
                | REMOVE NEW FILE IF DATABASE UPDATE FAILED
                |--------------------------------------------------------------------------
                */

                if (
                    $hasNewImage
                    && $newImagePath !== ''
                    && is_file($newImagePath)
                ) {
                    unlink($newImagePath);
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| BUILD PAGE CONTENT
|--------------------------------------------------------------------------
*/

ob_start();
?>

<style>
    .edit-product-wrap {
        max-width: 760px;
        margin: 0 auto;
    }

    .edit-form {
        display: grid;
        gap: 18px;
    }

    .form-group label {
        display: block;
        margin-bottom: 7px;
        font-weight: 700;
    }

    .form-control {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: var(--panel-2);
        color: var(--text);
        font-size: 1rem;
        outline: none;
    }

    .form-control:focus {
        border-color: var(--brand-2);
        box-shadow: 0 0 0 3px rgba(37, 117, 252, .15);
    }

    .current-image {
        width: 130px;
        height: 130px;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid var(--border);
        margin-bottom: 10px;
        display: block;
    }

    .image-note {
        color: var(--muted);
        font-size: .85rem;
        margin-top: 7px;
    }

    .error-box {
        padding: 12px 15px;
        margin-bottom: 18px;
        border-radius: 10px;
        background: rgba(239, 68, 68, .15);
        border: 1px solid rgba(239, 68, 68, .35);
        color: #f87171;
    }

    .error-box ul {
        margin: 0;
        padding-left: 20px;
    }

    .form-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 5px;
    }
</style>

<div class="grid">

    <div class="card col-12">

        <div class="edit-product-wrap">

            <h3>
                Edit Product #<?= $productId ?>
            </h3>

            <?php if ($errors): ?>

                <div class="error-box">

                    <ul>

                        <?php foreach ($errors as $error): ?>

                            <li><?= e($error) ?></li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>

            <form
                method="POST"
                enctype="multipart/form-data"
                class="edit-form">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e($csrf) ?>">

                <input
                    type="hidden"
                    name="product_id"
                    value="<?= $productId ?>">

                <div class="form-group">

                    <label for="category">
                        Category
                    </label>

                    <select
                        name="category"
                        id="category"
                        class="form-control"
                        required>

                        <option
                            value="Children"
                            <?= $category === 'Children'
                                ? 'selected'
                                : ''
                            ?>>
                            Children
                        </option>

                        <option
                            value="Men"
                            <?= $category === 'Men'
                                ? 'selected'
                                : ''
                            ?>>
                            Men
                        </option>

                        <option
                            value="Women"
                            <?= $category === 'Women'
                                ? 'selected'
                                : ''
                            ?>>
                            Women
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label for="name">
                        Product Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="name"
                        class="form-control"
                        maxlength="255"
                        value="<?= e($name) ?>"
                        required>

                </div>

                <div class="form-group">

                    <label for="price">
                        Price
                    </label>

                    <input
                        type="number"
                        name="price"
                        id="price"
                        class="form-control"
                        min="0"
                        step="0.01"
                        value="<?= e($price) ?>"
                        required>

                </div>

                <div class="form-group">

                    <label>
                        Current Image
                    </label>

                    <?php if ($currentImage !== ''): ?>

                        <img
                            src="uploads/<?= e($currentImage) ?>"
                            alt="<?= e($name) ?>"
                            class="current-image">

                    <?php else: ?>

                        <p style="color:var(--muted);">
                            No current image.
                        </p>

                    <?php endif; ?>

                </div>

                <div class="form-group">

                    <label for="image">
                        Change Image
                    </label>

                    <input
                        type="file"
                        name="image"
                        id="image"
                        class="form-control"
                        accept="image/jpeg,image/png,image/gif,image/webp">

                    <div class="image-note">
                        Leave this empty to keep the current image.
                    </div>

                </div>

                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn--brand">
                        Save Changes
                    </button>

                    <a
                        href="seller_products.php"
                        class="btn">
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

<?php

$content = ob_get_clean();

$pageTitle = 'Edit Product';

$actions = [
    [
        'href'  => 'seller_products.php',
        'label' => 'Back to My Products',
        'brand' => false
    ]
];

include __DIR__ . '/seller_layout.php';
