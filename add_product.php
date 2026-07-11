<?php
// add_product.php — Add Product Page
declare(strict_types=1);
session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit();
}

require __DIR__ . '/db.php';

// --- CSRF helpers ---
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_input(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') . '">';
}

// --- Handle POST (Add) ---
$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    // CSRF
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
        $errors[] = 'Invalid CSRF token.';
    }

    $category = trim((string)($_POST['category'] ?? ''));
    $name     = trim((string)($_POST['name'] ?? ''));
    $priceRaw = (string)($_POST['price'] ?? '');
    $price    = is_numeric($priceRaw) ? (float)$priceRaw : null;

    // Basic validation
    // Basic validation
    $allowedCategories = ['Children', 'Men', 'Women'];
    if ($category === '' || !in_array($category, $allowedCategories, true)) {
        $errors[] = 'Please choose a valid category.';
    }

    if ($name === '' || mb_strlen($name) > 255) {
        $errors[] = 'Name is required and must be ≤ 255 characters.';
    }
    if ($price === null || $price < 0 || $price > 9999999) {
        $errors[] = 'Price must be a valid non-negative number.';
    }

    // Image checks
    if (!isset($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Image is required.';
    } else {
        $image = $_FILES['image'];
        // Limit size to ~5MB
        if ($image['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image too large (max 5MB).';
        }
        // MIME sniffing
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($image['tmp_name']) ?: '';
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        if (!isset($allowedMimes[$mime])) {
            $errors[] = 'Only JPG, PNG, GIF, or WEBP images are allowed.';
        }
    }

    if (!$errors) {
        if (!is_dir(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0755, true);
        }
        $ext = $allowedMimes[$mime] ?? 'dat';
        // Sanitize original filename (optional)
        $safeBaseName = isset($image) ? preg_replace('/[^A-Za-z0-9_\-.]/', '_', basename($image['name'])) : '';
        $imageName = time() . '_' . ($safeBaseName ?: ('img.' . $ext));

        // Ensure extension matches detected mime
        if (!preg_match('/\.' . preg_quote($ext, '/') . '$/i', $imageName)) {
            $imageName .= '.' . $ext;
        }

        $imagePath = __DIR__ . '/uploads/' . $imageName;

        if (is_uploaded_file($image['tmp_name']) && move_uploaded_file($image['tmp_name'], $imagePath)) {
            $stmt = $conn->prepare("INSERT INTO products (category, name, price, image) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssds', $category, $name, $price, $imageName);
            $stmt->execute();

            $notice = 'Product added successfully!';
            // Optional: reset the form fields
            $category = $name = '';
            $price = null;
        } else {
            $errors[] = 'Failed to save uploaded image.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>
    <style>
        body {
            font-family: 'Roboto', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: rgb(90, 177, 208);
            text-align: center;
            color: #333
        }

        h1,
        h2 {
            margin-top: 20px;
            color: #000
        }

        .container {
            max-width: 780px;
            margin: 20px auto
        }

        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .2);
            padding: 20px;
            text-align: left
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold
        }

        input,
        select {
            width: 95%;
            padding: 10px;
            margin-bottom: 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em
        }

        button {
            background: #2ecc71;
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            transition: .2s
        }

        button:hover {
            background: #27ae60
        }

        .back-btn {
            display: inline-block;
            margin: 15px;
            padding: 10px 18px;
            background: #3498db;
            color: #fff;
            text-decoration: none;
            border-radius: 6px
        }

        .back-btn:hover {
            background: #2980b9
        }

        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 12px
        }

        .alert-error {
            background: #ffe6e6;
            color: #900;
            border: 1px solid #f5bcbc
        }

        .alert-ok {
            background: #e9ffef;
            color: #106b2b;
            border: 1px solid #b7f0c8
        }
    </style>
</head>

<body>
    <h1>Add Product</h1>
    <a href="manage_products.php" class="back-btn">⬅️ Back to Manage Products</a>

    <div class="container">
        <div class="card">
            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <ul style="margin:0 0 0 18px">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif ($notice): ?>
                <div class="alert alert-ok"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" novalidate>
                <?= csrf_input(); ?>
                <label for="category">Category</label>
                <select name="category" id="category" required>
                    <option value="">— Select —</option>
                    <option value="Children">Children</option>
                    <option value="Men">Men</option>
                    <option value="Women">Women</option>
                </select>


                <label for="name">Name</label>
                <input type="text" name="name" id="name" maxlength="255" required>

                <label for="price">Price</label>
                <input type="number" step="0.01" name="price" id="price" min="0" required>

                <label for="image">Image</label>
                <input type="file" name="image" id="image" accept="image/*" required>

                <button type="submit" name="add">Add Product</button>
            </form>
        </div>
    </div>
</body>

</html>