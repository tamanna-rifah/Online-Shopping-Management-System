<?php
session_start();
require 'db.php';

// Fetch products based on category or search term
$products_result = null;

if (isset($_GET['category'])) {
    $category = $_GET['category'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE category = ?");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $products_result = $stmt->get_result();
} elseif (isset($_GET['search'])) {
    $search = $_GET['search'];
    $like = "%" . $search . "%";
    $stmt = $conn->prepare("SELECT * FROM products WHERE name LIKE ?");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $products_result = $stmt->get_result();
} else {
    $products_result = $conn->query("SELECT * FROM products");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Cloth Management and Delivery System</title>
    <link href="https://fonts.googleapis.com/css2?family=Merienda&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: linear-gradient(rgba(90, 177, 208, 0.4), rgba(90, 177, 208, 0.4)),
                url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-attachment: fixed;
        }

        .header {
            padding: 25px 20px;
            background: linear-gradient(135deg, rgba(29, 131, 152, 0.95), rgba(49, 106, 134, 0.95));
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-family: 'Merienda', cursive;
        }

        /* Dropdown menu */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropbtn {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 40px;
            background-color: white;
            min-width: 170px;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.25);
            z-index: 10;
        }

        .dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            border-bottom: 1px solid #eee;
        }

        .dropdown-content a:hover {
            background-color: #007BFF;
            color: white;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        /* Search bar */
        .search-bar {
            margin: 20px auto 0;
            text-align: center;
        }

        .search-bar form {
            display: flex;
            justify-content: center;
            max-width: 500px;
            margin: auto;
        }

        .search-bar input {
            padding: 10px;
            border-radius: 20px;
            border: none;
            width: 100%;
            outline: none;
        }

        .search-bar button {
            background: #007BFF;
            border: none;
            padding: 10px 15px;
            margin-left: -40px;
            border-radius: 50%;
            cursor: pointer;
        }

        .search-bar button img {
            width: 18px;
            height: 18px;
        }

        /* Products */
        .container {
            margin: 30px;
        }

        .products {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .product {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.97), rgba(247, 251, 255, 0.94));
            padding: 22px 20px 24px;
            border-radius: 26px;
            box-shadow: 0 14px 36px rgba(10, 44, 66, 0.16);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(8px);
        }

        .product:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.25);
        }

        .product h2 {
            margin: 8px 0 14px;
            font-size: 15px;
            line-height: 1.35;
            font-weight: 700;
            color: #15263b;
        }

        .product img {
            width: 100%;
            max-height: 190px;
            object-fit: cover;
            border-radius: 18px;
            box-shadow: 0 10px 24px rgba(33, 62, 88, 0.14);
        }

        .product p {
            margin: 4px 0;
            font-size: 12px;
            line-height: 1.45;
            color: #4b5563;
        }

        .btn-group {
            margin-top: 16px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.2px;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.22s ease, box-shadow 0.22s ease, background 0.22s ease;
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.12);
        }

        .btn-cart {
            background: linear-gradient(135deg, #1e88ff, #005fe0);
            color: white;
        }

        .btn-cart:hover {
            background: linear-gradient(135deg, #1976f2, #004fc0);
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(0, 95, 224, 0.28);
        }

        .btn-details {
            background: linear-gradient(135deg, #2fcb6f, #1fa851);
            color: white;
        }

        .btn-details:hover {
            background: linear-gradient(135deg, #27ba63, #178b43);
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(31, 168, 81, 0.24);
        }

        @media (max-width: 768px) {
            .product h2 {
                font-size: 14px;
            }

            .product p {
                font-size: 11px;
            }

            .btn {
                width: 100%;
                max-width: 180px;
                padding: 9px 14px;
                font-size: 11px;
            }
        }

        footer {
            text-align: center;
            margin: 50px 0 20px;
            font-size: 0.9rem;
            color: black;
        }

        .no-products {
            text-align: center;
            padding: 50px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <!-- Header -->
    <div class="header">

        <h1>PICK UP!!!</h1>

        <div class="dropdown">

            <button class="dropbtn">☰</button>

            <div class="dropdown-content">
                <a href="user_registration.php">Register</a>
                <a href="login.php">Login</a>
                <a href="become_seller.php">Become a Seller</a>
            </div>

        </div>

    </div>

    <!-- Search -->
    <div class="search-bar">
        <form method="GET" action="index.php">
            <input type="text" name="search" placeholder="Search products..." required>
            <button type="submit"><img src="search-icon.png" alt="Search"></button>
        </form>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="products">
            <?php if ($products_result->num_rows > 0): ?>
                <?php while ($product = $products_result->fetch_assoc()): ?>
                    <div class="product">
                        <h2><?= htmlspecialchars($product['name']) ?></h2>
                        <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        <p>Category: <?= htmlspecialchars($product['category']) ?></p>
                        <p>Price: Tk <?= htmlspecialchars($product['price']) ?></p>
                        <div class="btn-group">
                            <a href="add_to_cart.php?product_id=<?= $product['id'] ?>" class="btn btn-cart">Add to Cart</a>
                            <a href="guest_p_details.php?id=<?= $product['id'] ?>" class="btn btn-details">View Details</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-products">😢 No products found!</div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        © <?= date("Y") ?> Dress at Your Door | All Rights Reserved
    </footer>
</body>

</html>
