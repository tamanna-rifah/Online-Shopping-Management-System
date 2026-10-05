<?php
session_start();
require 'db.php';

$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT id, category, name, price, image FROM products";
$params = [];
$types = '';
$conditions = [];

if ($category !== '') {
    $conditions[] = "category = ?";
    $params[] = $category;
    $types .= 's';
}
if ($search !== '') {
    $conditions[] = "name LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= 's';
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Products - Online Cloth Management and Delivery System</title>
    <link href="https://fonts.googleapis.com/css2?family=Merienda&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --primary: #0e7ac4;
            --primary-dark: #0a5e96;
            --text: #1f2937;
            --muted: #6b7280;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: var(--text);
            background: linear-gradient(rgba(90, 177, 208, 0.4), rgba(90, 177, 208, 0.4)),
                url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 28px;
            background: linear-gradient(135deg, rgba(29, 131, 152, 0.95), rgba(49, 106, 134, 0.95));
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .brand {
            font-family: 'Merienda', cursive;
            font-size: 28px;
            margin: 0;
        }

        .nav-links a {
            color: #fff;
            text-decoration: none;
            margin-left: 18px;
            font-weight: bold;
        }

        .container {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 16px;
        }

        .filters {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 12px;
            align-items: center;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .filters input,
        .filters select {
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.9);
        }

        .filters button {
            padding: 12px 16px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }

        .filters button:hover {
            background: var(--primary-dark);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 18px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 14px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform .2s ease, box-shadow .2s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.08);
        }

        .thumb {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #f3f4f6;
        }

        .card-body {
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .name {
            font-weight: 700;
            font-size: 16px;
            margin: 0;
        }

        .meta {
            color: var(--muted);
            font-size: 13px;
        }

        .price {
            font-weight: 700;
            font-size: 18px;
            margin-top: 6px;
        }

        .actions {
            margin-top: auto;
        }

        .btn {
            display: inline-block;
            padding: 10px 12px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .btn:hover {
            background: var(--primary-dark);
        }

        .empty {
            text-align: center;
            color: var(--muted);
            padding: 40px 0;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>

<body>
    <header class="navbar">
        <h1 class="brand">Dress At Your Door!</h1>
        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="all-products">All Products</a>
            <a href="user_registration.php">Register</a>
            <a href="user_login.php">User Login</a>
            <a href="admin_login.php">Admin Login</a>
        </nav>
    </header>

    <main class="container">
        <form class="filters" method="GET" action="all-products">
            <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
            <select name="category">
                <option value="">All Categories</option>
                <option value="Clothes" <?= $category === 'Clothes' ? 'selected' : '' ?>>Clothes</option>
                <option value="Accessories" <?= $category === 'Accessories' ? 'selected' : '' ?>>Accessories</option>
                <option value="Lifestyle" <?= $category === 'Lifestyle' ? 'selected' : '' ?>>Lifestyle</option>
                <option value="Health and Beauty" <?= $category === 'Health and Beauty' ? 'selected' : '' ?>>Health and Beauty</option>
            </select>
            <button type="submit">Apply</button>
        </form>

        <section class="grid">
            <?php if ($products->num_rows > 0): ?>
                <?php while ($p = $products->fetch_assoc()): ?>
                    <?php
                    $imgFile = isset($p['image']) ? 'uploads/' . $p['image'] : '';
                    $hasImage = $imgFile && file_exists($imgFile);
                    $placeholderSvg = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect width="100%" height="100%" fill="#e5e7eb"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#9ca3af" font-size="20" font-family="Arial">No Image</text></svg>');
                    $thumbSrc = $hasImage ? htmlspecialchars($imgFile) : $placeholderSvg;
                    ?>
                    <article class="card">
                        <img class="thumb" src="<?= $thumbSrc ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                        <div class="card-body">
                            <h3 class="name"><?= htmlspecialchars($p['name']) ?></h3>
                            <div class="meta">Category: <?= htmlspecialchars($p['category']) ?></div>
                            <div class="price">$<?= htmlspecialchars($p['price']) ?></div>
                            <div class="actions">
                                <a class="btn" href="add_to_cart.php?product_id=<?= (int)$p['id'] ?>">Add to Cart</a>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <?php for ($i = 0; $i < 8; $i++): ?>
                    <article class="card">
                        <img class="thumb" src="data:image/svg+xml;utf8,<?= rawurlencode('<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'300\'><rect width=\'100%\' height=\'100%\' fill=\'#e5e7eb\'/><text x=\'50%\' y=\'50%\' dominant-baseline=\'middle\' text-anchor=\'middle\' fill=\'#9ca3af\' font-size=\'20\' font-family=\'Arial\'>Product</text></svg>') ?>" alt="Placeholder">
                        <div class="card-body">
                            <h3 class="name">Product Name</h3>
                            <div class="meta">Category</div>
                            <div class="price">$0.00</div>
                            <div class="actions">
                                <a class="btn" href="#">Add to Cart</a>
                            </div>
                        </div>
                    </article>
                <?php endfor; ?>
            <?php endif; ?>
        </section>
    </main>
</body>

</html>
