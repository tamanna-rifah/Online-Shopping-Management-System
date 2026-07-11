<?php
// seller_add_product.php — Seller Add Product Page

declare(strict_types=1);

session_start();

/* ==============================
   SELLER ACCESS PROTECTION
============================== */

if (empty($_SESSION['seller_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/db.php';


/* ==============================
   SAFE OUTPUT FUNCTION
============================== */

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


/* ==============================
   SELLER ID
============================== */

$sellerId = (int) $_SESSION['seller_id'];


/* ==============================
   CSRF TOKEN
============================== */

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}


/* ==============================
   VARIABLES
============================== */

$errors = [];
$notice = '';

$category = '';
$name = '';
$priceRaw = '';


/* ==============================
   HANDLE ADD PRODUCT
============================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add'])
) {

    /* CSRF CHECK */

    $postedCsrf = (string) ($_POST['csrf'] ?? '');

    if (
        $postedCsrf === ''
        || !hash_equals($_SESSION['csrf'], $postedCsrf)
    ) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    }


    /* GET FORM DATA */

    $category = trim(
        (string) ($_POST['category'] ?? '')
    );

    $name = trim(
        (string) ($_POST['name'] ?? '')
    );

    $priceRaw = trim(
        (string) ($_POST['price'] ?? '')
    );

    $price = is_numeric($priceRaw)
        ? (float) $priceRaw
        : null;


    /* ==============================
       VALIDATE CATEGORY
    ============================== */

    $allowedCategories = [
        'Children',
        'Men',
        'Women'
    ];

    if (
        $category === ''
        || !in_array(
            $category,
            $allowedCategories,
            true
        )
    ) {
        $errors[] = 'Please choose a valid category.';
    }


    /* ==============================
       VALIDATE PRODUCT NAME
    ============================== */

    if (
        $name === ''
        || mb_strlen($name) > 255
    ) {
        $errors[] =
            'Product name is required and must not exceed 255 characters.';
    }


    /* ==============================
       VALIDATE PRICE
    ============================== */

    if (
        $price === null
        || $price < 0
        || $price > 9999999
    ) {
        $errors[] =
            'Price must be a valid non-negative number.';
    }


    /* ==============================
       VALIDATE IMAGE
    ============================== */

    $image = null;
    $mime = '';

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    if (
        !isset($_FILES['image'])
        || (
            $_FILES['image']['error']
            ?? UPLOAD_ERR_NO_FILE
        ) !== UPLOAD_ERR_OK
    ) {

        $errors[] = 'Product image is required.';
    } else {

        $image = $_FILES['image'];


        /* IMAGE SIZE */

        if (
            (int) $image['size']
            > 5 * 1024 * 1024
        ) {
            $errors[] =
                'Image is too large. Maximum size is 5MB.';
        }


        /* IMAGE MIME TYPE */

        $finfo = new finfo(
            FILEINFO_MIME_TYPE
        );

        $mime = $finfo->file(
            $image['tmp_name']
        ) ?: '';

        if (
            !isset($allowedMimes[$mime])
        ) {
            $errors[] =
                'Only JPG, PNG, GIF or WEBP images are allowed.';
        }
    }


    /* ==============================
       SAVE PRODUCT
    ============================== */

    if (!$errors && $image !== null) {

        /* CREATE UPLOAD FOLDER */

        $uploadDirectory =
            __DIR__ . '/uploads';

        if (!is_dir($uploadDirectory)) {

            if (
                !mkdir(
                    $uploadDirectory,
                    0755,
                    true
                )
                && !is_dir($uploadDirectory)
            ) {
                $errors[] =
                    'Unable to create upload folder.';
            }
        }


        if (!$errors) {

            /* IMAGE EXTENSION */

            $extension =
                $allowedMimes[$mime];


            /* SAFE ORIGINAL NAME */

            $originalName =
                basename(
                    (string) $image['name']
                );

            $safeBaseName =
                preg_replace(
                    '/[^A-Za-z0-9_\-.]/',
                    '_',
                    $originalName
                );


            /* UNIQUE IMAGE NAME */

            $imageName =
                time()
                . '_seller_'
                . $sellerId
                . '_'
                . (
                    $safeBaseName
                    ?: 'product.' . $extension
                );


            /* CORRECT EXTENSION */

            if (
                !preg_match(
                    '/\.'
                        . preg_quote(
                            $extension,
                            '/'
                        )
                        . '$/i',
                    $imageName
                )
            ) {
                $imageName .=
                    '.' . $extension;
            }


            /* FINAL IMAGE PATH */

            $imagePath =
                $uploadDirectory
                . '/'
                . $imageName;


            /* MOVE IMAGE */

            if (
                is_uploaded_file(
                    $image['tmp_name']
                )
                && move_uploaded_file(
                    $image['tmp_name'],
                    $imagePath
                )
            ) {

                /* ==============================
                   INSERT PRODUCT WITH SELLER ID
                ============================== */

                $stmt = $conn->prepare("
                    INSERT INTO products
                    (
                        seller_id,
                        category,
                        name,
                        price,
                        image
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                if (!$stmt) {

                    /* DELETE IMAGE IF DB FAILS */

                    if (is_file($imagePath)) {
                        unlink($imagePath);
                    }

                    $errors[] =
                        'Unable to prepare product save request.';
                } else {

                    $stmt->bind_param(
                        'issds',
                        $sellerId,
                        $category,
                        $name,
                        $price,
                        $imageName
                    );

                    if ($stmt->execute()) {

                        $notice =
                            'Product added successfully!';

                        /* CLEAR FORM */

                        $category = '';
                        $name = '';
                        $priceRaw = '';
                    } else {

                        /* DELETE IMAGE IF INSERT FAILS */

                        if (is_file($imagePath)) {
                            unlink($imagePath);
                        }

                        $errors[] =
                            'Failed to save product.';
                    }

                    $stmt->close();
                }
            } else {

                $errors[] =
                    'Failed to save uploaded image.';
            }
        }
    }
}


/* ==============================
   BUILD PAGE CONTENT
============================== */

ob_start();
?>

<div class="grid">

    <div
        class="card col-12"
        style="
            max-width:800px;
            margin:0 auto;
            width:100%;
        ">

        <h3 style="margin-bottom:20px;">
            Add a New Product
        </h3>


        <?php if ($errors): ?>

            <div
                style="
                    background:rgba(239,68,68,.12);
                    border:1px solid rgba(239,68,68,.35);
                    color:#fca5a5;
                    padding:14px 16px;
                    border-radius:10px;
                    margin-bottom:18px;
                ">

                <strong>
                    Please fix the following:
                </strong>

                <ul
                    style="
                        margin:10px 0 0 20px;
                        padding:0;
                    ">

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= e($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <?php if ($notice !== ''): ?>

            <div
                style="
                    background:rgba(16,185,129,.12);
                    border:1px solid rgba(16,185,129,.35);
                    color:#6ee7b7;
                    padding:14px 16px;
                    border-radius:10px;
                    margin-bottom:18px;
                ">

                <?= e($notice) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            enctype="multipart/form-data"
            novalidate>

            <input
                type="hidden"
                name="csrf"
                value="<?= e($_SESSION['csrf']) ?>">


            <!-- CATEGORY -->

            <div style="margin-bottom:18px;">

                <label
                    for="category"
                    style="
                        display:block;
                        margin-bottom:7px;
                        font-weight:700;
                    ">
                    Category
                </label>

                <select
                    name="category"
                    id="category"
                    required

                    style="
                        width:100%;
                        padding:12px 14px;
                        border:1px solid var(--border);
                        border-radius:10px;
                        background:var(--panel-2);
                        color:var(--text);
                        font-size:1rem;
                        outline:none;
                    ">

                    <option value="">
                        — Select Category —
                    </option>

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


            <!-- PRODUCT NAME -->

            <div style="margin-bottom:18px;">

                <label
                    for="name"
                    style="
                        display:block;
                        margin-bottom:7px;
                        font-weight:700;
                    ">
                    Product Name
                </label>

                <input
                    type="text"
                    name="name"
                    id="name"

                    maxlength="255"

                    value="<?= e($name) ?>"

                    placeholder="Enter product name"

                    required

                    style="
                        width:100%;
                        padding:12px 14px;
                        border:1px solid var(--border);
                        border-radius:10px;
                        background:var(--panel-2);
                        color:var(--text);
                        font-size:1rem;
                        outline:none;
                    ">

            </div>


            <!-- PRICE -->

            <div style="margin-bottom:18px;">

                <label
                    for="price"
                    style="
                        display:block;
                        margin-bottom:7px;
                        font-weight:700;
                    ">
                    Price (Tk)
                </label>

                <input
                    type="number"
                    name="price"
                    id="price"

                    step="0.01"
                    min="0"

                    value="<?= e($priceRaw) ?>"

                    placeholder="Enter product price"

                    required

                    style="
                        width:100%;
                        padding:12px 14px;
                        border:1px solid var(--border);
                        border-radius:10px;
                        background:var(--panel-2);
                        color:var(--text);
                        font-size:1rem;
                        outline:none;
                    ">

            </div>


            <!-- IMAGE -->

            <div style="margin-bottom:22px;">

                <label
                    for="image"
                    style="
                        display:block;
                        margin-bottom:7px;
                        font-weight:700;
                    ">
                    Product Image
                </label>

                <input
                    type="file"
                    name="image"
                    id="image"

                    accept="
                        image/jpeg,
                        image/png,
                        image/gif,
                        image/webp
                    "

                    required

                    style="
                        width:100%;
                        padding:12px;
                        border:1px dashed var(--border);
                        border-radius:10px;
                        background:var(--panel-2);
                        color:var(--text);
                    ">

                <div
                    style="
                        color:var(--muted);
                        font-size:.85rem;
                        margin-top:7px;
                    ">
                    JPG, PNG, GIF or WEBP.
                    Maximum size: 5MB.
                </div>

            </div>


            <!-- BUTTONS -->

            <div
                style="
                    display:flex;
                    gap:10px;
                    flex-wrap:wrap;
                ">

                <button
                    type="submit"
                    name="add"
                    class="btn btn--brand">
                    Add Product
                </button>


                <a
                    href="seller_products.php"
                    class="btn">
                    View My Products
                </a>

            </div>

        </form>

    </div>

</div>

<?php

$content = ob_get_clean();


/* ==============================
   PAGE CONFIG
============================== */

$pageTitle = 'Add Product';

$actions = [

    [
        'href' =>
        'seller_products.php',

        'label' =>
        'My Products',

        'brand' =>
        false
    ]

];


/* ==============================
   LOAD SELLER LAYOUT
============================== */

include __DIR__
    . '/seller_layout.php';
